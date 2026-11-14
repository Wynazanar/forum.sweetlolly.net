<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\ThreadService;

class TopicController extends Controller
{
    private ThreadService $service;

    public function __construct()
    {
        $this->service = new ThreadService();
    }

    public function create(array $params = [])
    {
        $this->guard();
        $this->title = 'Создать тему | Модерация';
        return $this->render('moderation/topic_form', [
            'mode'             => 'create',
            'item'             => [],
            'categoryOptions'  => $this->service->categoryOptions(),
            'errors'           => [],
            'user'             => AuthService::user(),
        ]);
    }

    public function store(array $params = [])
    {
        $this->guard();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('/moderation/topics'));
            exit;
        }
        $result = $this->service->create($_POST);
        if (!$result['ok']) {
            $this->title = 'Создать тему | Модерация';
            return $this->render('moderation/topic_form', [
                'mode'            => 'create',
                'item'            => $_POST,
                'categoryOptions' => $this->service->categoryOptions(),
                'errors'          => $result['errors'] ?? [],
                'user'            => AuthService::user(),
            ]);
        }
        header('Location: ' . url('/moderation/topics') . '?id=' . (int)$result['id']);
        exit;
    }

    public function edit(array $params = [])
    {
        $this->guard();
        $id = (int)($params['id'] ?? 0);
        $item = $this->service->find($id);
        if (!$item) {
            header('Location: ' . url('/moderation/topics'));
            exit;
        }
        $this->title = 'Тема: ' . ($item['title'] ?? '') . ' | Модерация';
        return $this->render('moderation/topic_form', [
            'mode'            => 'edit',
            'item'            => $item,
            'categoryOptions' => $this->service->categoryOptions(),
            'errors'          => [],
            'user'            => AuthService::user(),
        ]);
    }

    public function update(array $params = [])
    {
        $this->guard();
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/topics'));
            exit;
        }
        $result = $this->service->update($id, $_POST);
        if (!$result['ok']) {
            $item = $this->service->find($id) ?? $_POST;
            $this->title = 'Редактирование темы | Модерация';
            return $this->render('moderation/topic_form', [
                'mode'            => 'edit',
                'item'            => $item,
                'categoryOptions' => $this->service->categoryOptions(),
                'errors'          => $result['errors'] ?? [],
            ]);
        }
        header('Location: ' . url('/moderation/topics') . '?id=' . $id);
        exit;
    }

    public function lock(array $params = [])
    {
        $this->guard();
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $locked = !empty($_POST['locked']);
            $this->service->toggleLock($id, $locked);
        }
        header('Location: ' . url('/moderation/topics') . '?id=' . $id);
        exit;
    }

    public function sticky(array $params = [])
    {
        $this->guard();
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $sticky = !empty($_POST['sticky']);
            $this->service->toggleSticky($id, $sticky);
        }
        header('Location: ' . url('/moderation/topics') . '?id=' . $id);
        exit;
    }

    public function move(array $params = [])
    {
        $this->guard();
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $cat = (int)($_POST['category_id'] ?? 0);
            if ($cat > 0) {
                $this->service->move($id, $cat);
            }
        }
        header('Location: ' . url('/moderation/topics') . '?id=' . $id);
        exit;
    }

    public function hide(array $params = [])
    {
        $this->guard();
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $this->service->hide($id, !empty($_POST['hidden']));
        }
        header('Location: ' . url('/moderation/topics') . '?id=' . $id);
        exit;
    }

    private function guard(): void
    {
        if (!AuthService::check() || (
            !AuthService::can('lock_thread')
            && !AuthService::can('move_thread')
            && !AuthService::can('delete_thread')
            && !AuthService::hasRole('admin')
        )) {
            header('Location: ' . url('/'));
            exit;
        }
    }
}
