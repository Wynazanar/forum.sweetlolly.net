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
        $this->guard('create_thread');
        $this->title = 'Создать тему | Модерация';
        return $this->render('moderation/topic_form', [
            'mode'                    => 'create',
            'item'                    => [],
            'categoryOptions'         => $this->service->categoryOptions(),
            'sectionOptionsByCategory'=> $this->service->sectionOptionsByCategory(),
            'errors'                  => [],
            'user'                    => AuthService::user(),
        ]);
    }

    public function store(array $params = [])
    {
        $this->guard('create_thread');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('/moderation/topics'));
            exit;
        }
        $result = $this->service->create($_POST);
        if (!$result['ok']) {
            $this->title = 'Создать тему | Модерация';
            return $this->render('moderation/topic_form', [
                'mode'                    => 'create',
                'item'                    => $_POST,
                'categoryOptions'         => $this->service->categoryOptions(),
                'sectionOptionsByCategory'=> $this->service->sectionOptionsByCategory(),
                'errors'                  => $result['errors'] ?? [],
                'user'                    => AuthService::user(),
            ]);
        }
        $qid = (int)$result['id'];
        $qs = '?id=' . $qid;
        if (($result['status'] ?? '') === 'pending') {
            $qs .= '&mod=pending';
        }
        header('Location: ' . url('/moderation/topics') . $qs);
        exit;
    }

    public function edit(array $params = [])
    {
        $this->guard('threads.edit_any');
        $id = (int)($params['id'] ?? 0);
        $item = $this->service->find($id);
        if (!$item) {
            header('Location: ' . url('/moderation/topics'));
            exit;
        }
        $this->title = 'Тема: ' . ($item['title'] ?? '') . ' | Модерация';
        // Для формы: parent category + section
        $item['parent_category_id'] = (new \App\Models\Section())->getParentId((int)($item['category_id'] ?? 0));
        return $this->render('moderation/topic_form', [
            'mode'                    => 'edit',
            'item'                    => $item,
            'categoryOptions'         => $this->service->categoryOptions(),
            'sectionOptionsByCategory'=> $this->service->sectionOptionsByCategory(),
            'errors'                  => [],
            'user'                    => AuthService::user(),
        ]);
    }

    public function update(array $params = [])
    {
        $this->guard('threads.edit_any');
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/topics'));
            exit;
        }
        $result = $this->service->update($id, $_POST);
        if (!$result['ok']) {
            $item = $this->service->find($id) ?? $_POST;
            $item['parent_category_id'] = (new \App\Models\Section())->getParentId((int)($item['category_id'] ?? 0));
            $this->title = 'Редактирование темы | Модерация';
            return $this->render('moderation/topic_form', [
                'mode'                    => 'edit',
                'item'                    => $item,
                'categoryOptions'         => $this->service->categoryOptions(),
                'sectionOptionsByCategory'=> $this->service->sectionOptionsByCategory(),
                'errors'                  => $result['errors'] ?? [],
            ]);
        }
        header('Location: ' . url('/moderation/topics') . '?id=' . $id);
        exit;
    }

    public function lock(array $params = [])
    {
        $this->guardAny(['threads.lock', 'lock_thread']);
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
        $this->guard('threads.pin');
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
        $this->guardAny(['threads.move', 'move_thread']);
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
        $this->guardAny(['threads.hide', 'delete_thread']);
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $this->service->hide($id, !empty($_POST['hidden']));
        }
        header('Location: ' . url('/moderation/topics') . '?id=' . $id);
        exit;
    }

    private function guard(string $perm = 'threads.view_mod'): void
    {
        if (!AuthService::check() || !AuthService::can($perm)) {
            header('Location: ' . url('/'));
            exit;
        }
    }

    /** Любое из перечисленных прав (для legacy-ключей). */
    private function guardAny(array $perms): void
    {
        if (!AuthService::check()) {
            header('Location: ' . url('/'));
            exit;
        }
        foreach ($perms as $perm) {
            if (AuthService::can($perm)) {
                return;
            }
        }
        header('Location: ' . url('/'));
        exit;
    }

    public function moderate(array $params = [])
    {
        $this->guard('threads.moderate');
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/topics'));
            exit;
        }
        $status = $_POST['status'] ?? 'published';
        $actor = AuthService::user();
        $this->service->setStatus($id, $status, (int)$actor['id']);
        header('Location: ' . url('/moderation/topics') . '?id=' . $id);
        exit;
    }
}
