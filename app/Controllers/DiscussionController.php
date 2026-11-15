<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\DiscussionService;

/**
 * CRUD и модерация веток обсуждений (threads с parent_thread_id).
 * Почти полностью зеркалит TopicController, но с привязкой к родительской теме.
 */
class DiscussionController extends Controller
{
    private DiscussionService $service;

    public function __construct()
    {
        $this->service = new DiscussionService();
    }

    public function create(array $params = [])
    {
        $this->guard('discussions.edit_any');
        $this->title = 'Создать обсуждение | Модерация';
        $parentId = (int)($_GET['parent_thread_id'] ?? 0);
        $item = [];
        if ($parentId > 0) {
            $parent = $this->service->find($parentId);
            if ($parent && empty($parent['parent_thread_id'])) {
                $item['parent_thread_id'] = $parentId;
                $item['category_id'] = (int)$parent['category_id'];
            }
        }
        return $this->render('moderation/discussion_form', [
            'mode'            => 'create',
            'item'            => $item,
            'categoryOptions' => $this->service->categoryOptions(),
            'parentOptions'   => $this->service->parentTopicOptions(100),
            'errors'          => [],
            'user'            => AuthService::user(),
        ]);
    }

    public function store(array $params = [])
    {
        $this->guard('discussions.edit_any');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('/moderation/discussions'));
            exit;
        }
        $input = $_POST;
        $input['require_parent'] = 1;
        // category_id берём у родителя, если не передали
        if (empty($input['category_id']) && !empty($input['parent_thread_id'])) {
            $parent = $this->service->find((int)$input['parent_thread_id']);
            if ($parent) {
                $input['category_id'] = (int)$parent['category_id'];
            }
        }
        $result = $this->service->create($input);
        if (!$result['ok']) {
            $this->title = 'Создать обсуждение | Модерация';
            return $this->render('moderation/discussion_form', [
                'mode'            => 'create',
                'item'            => $input,
                'categoryOptions' => $this->service->categoryOptions(),
                'parentOptions'   => $this->service->parentTopicOptions(100),
                'errors'          => $result['errors'] ?? [],
                'user'            => AuthService::user(),
            ]);
        }
        $qid = (int)$result['id'];
        $qs = '?id=' . $qid;
        if (($result['status'] ?? '') === 'pending') {
            $qs .= '&mod=pending';
        }
        header('Location: ' . url('/moderation/discussions') . $qs);
        exit;
    }

    public function edit(array $params = [])
    {
        $this->guard('discussions.edit_any');
        $id = (int)($params['id'] ?? 0);
        $item = $this->service->find($id);
        if (!$item || empty($item['parent_thread_id'])) {
            header('Location: ' . url('/moderation/discussions'));
            exit;
        }
        $this->title = 'Обсуждение: ' . ($item['title'] ?? '') . ' | Модерация';
        return $this->render('moderation/discussion_form', [
            'mode'            => 'edit',
            'item'            => $item,
            'categoryOptions' => $this->service->categoryOptions(),
            'parentOptions'   => $this->service->parentTopicOptions(100),
            'errors'          => [],
            'user'            => AuthService::user(),
        ]);
    }

    public function update(array $params = [])
    {
        $this->guard('discussions.edit_any');
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/discussions'));
            exit;
        }
        $input = $_POST;
        $input['require_parent'] = 1;
        if (empty($input['category_id']) && !empty($input['parent_thread_id'])) {
            $parent = $this->service->find((int)$input['parent_thread_id']);
            if ($parent) {
                $input['category_id'] = (int)$parent['category_id'];
            }
        }
        $result = $this->service->update($id, $input);
        if (!$result['ok']) {
            $item = $this->service->find($id) ?? $input;
            $this->title = 'Редактирование обсуждения | Модерация';
            return $this->render('moderation/discussion_form', [
                'mode'            => 'edit',
                'item'            => $item,
                'categoryOptions' => $this->service->categoryOptions(),
                'parentOptions'   => $this->service->parentTopicOptions(100),
                'errors'          => $result['errors'] ?? [],
                'user'            => AuthService::user(),
            ]);
        }
        header('Location: ' . url('/moderation/discussions') . '?id=' . $id);
        exit;
    }

    public function lock(array $params = [])
    {
        $this->guard('discussions.lock');
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $locked = !empty($_POST['locked']);
            $this->service->toggleLock($id, $locked);
        }
        header('Location: ' . url('/moderation/discussions') . '?id=' . $id);
        exit;
    }

    public function move(array $params = [])
    {
        $this->guard('discussions.move');
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $parentId = (int)($_POST['parent_thread_id'] ?? 0);
            $item = $this->service->find($id);
            if ($item && $parentId > 0) {
                $parent = $this->service->find($parentId);
                if ($parent && empty($parent['parent_thread_id'])) {
                    // Переносим ветку: меняем parent и category
                    $this->service->update($id, [
                        'title'            => $item['title'],
                        'slug'             => $item['slug'],
                        'category_id'      => (int)$parent['category_id'],
                        'parent_thread_id' => $parentId,
                        'is_sticky'        => !empty($item['is_sticky']),
                        'is_locked'        => !empty($item['is_locked']),
                        'is_hidden'        => !empty($item['is_hidden']),
                        'status'           => $item['status'] ?? 'published',
                        'action'           => 'keep',
                    ]);
                }
            }
        }
        header('Location: ' . url('/moderation/discussions') . '?id=' . $id);
        exit;
    }

    public function hide(array $params = [])
    {
        $this->guard('discussions.hide');
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
            $this->service->hide($id, !empty($_POST['hidden']));
        }
        header('Location: ' . url('/moderation/discussions') . '?id=' . $id);
        exit;
    }

    public function moderate(array $params = [])
    {
        $this->guard('discussions.moderate');
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/discussions'));
            exit;
        }
        $status = $_POST['status'] ?? 'published';
        $actor = AuthService::user();
        $this->service->setStatus($id, $status, (int)$actor['id']);
        header('Location: ' . url('/moderation/discussions') . '?id=' . $id);
        exit;
    }

    private function guard(string $perm = 'discussions.view_mod'): void
    {
        if (!AuthService::check() || (
            !AuthService::can($perm)
            && !AuthService::hasRole('admin')
            // fallback: старые права на темы тоже пускают админов/модеров
            && !AuthService::can(str_replace('discussions.', 'threads.', $perm))
        )) {
            header('Location: ' . url('/'));
            exit;
        }
    }
}
