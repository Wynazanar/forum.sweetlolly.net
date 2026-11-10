<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\SectionService;

class SectionController extends Controller
{
    private SectionService $service;

    public function __construct()
    {
        $this->service = new SectionService();
    }

    public function create(array $params = [])
    {
        $this->guard();

        $parentId = isset($_GET['parent']) ? (int)$_GET['parent'] : null;

        $data = [
            'mode'             => 'create',
            'item'             => [
                'title'          => '',
                'slug'           => '',
                'description'    => '',
                'parent_id'      => $parentId,
                'position'       => '',
                'view_role_id'   => '',
                'create_role_id' => '1',
                'reply_role_id'  => '1',
                'manage_role_id' => '3',
                'is_locked'      => 0,
                'status'         => 'published',
            ],
            'parentId'         => $parentId,
            'categoryOptions'  => $this->service->getCategoryOptions(),
            'accessOptions'    => $this->service->accessOptions(),
            'audienceOptions'  => $this->service->audienceOptions(),
            'managedByOptions' => $this->service->managedByOptions(),
            'statusOptions'    => $this->service->statusOptions(),
            'errors'           => [],
        ];

        $this->title = 'Создать раздел | Модерация';
        return $this->render('moderation/section_form', $data);
    }

    public function store(array $params = [])
    {
        $this->guard();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('/moderation/sections'));
            exit;
        }

        $result = $this->service->create($_POST);

        if (!$result['ok']) {
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== ''
                ? (int)$_POST['parent_id']
                : null;

            $data = [
                'mode'             => 'create',
                'item'             => [
                    'title'          => trim($_POST['title'] ?? ''),
                    'slug'           => trim($_POST['slug'] ?? ''),
                    'description'    => trim($_POST['description'] ?? ''),
                    'parent_id'      => $parentId,
                    'position'       => $_POST['position'] ?? '',
                    'view_role_id'   => $_POST['view_role_id'] ?? '',
                    'create_role_id' => $_POST['create_role_id'] ?? '',
                    'reply_role_id'  => $_POST['reply_role_id'] ?? '',
                    'manage_role_id' => $_POST['manage_role_id'] ?? '3',
                    'is_locked'      => !empty($_POST['is_locked']) ? 1 : 0,
                    'status'         => $_POST['status'] ?? 'published',
                    'moderate_topics'  => !empty($_POST['moderate_topics']) ? 1 : 0,
                    'allow_attachments'=> !empty($_POST['allow_attachments']) ? 1 : 0,
                    'allow_guest_posts'=> !empty($_POST['allow_guest_posts']) ? 1 : 0,
                ],
                'parentId'         => $parentId,
                'categoryOptions'  => $this->service->getCategoryOptions(),
                'accessOptions'    => $this->service->accessOptions(),
                'audienceOptions'  => $this->service->audienceOptions(),
                'managedByOptions' => $this->service->managedByOptions(),
                'statusOptions'    => $this->service->statusOptions(),
                'errors'           => $result['errors'] ?? [],
            ];

            $this->title = 'Создать раздел | Модерация';
            return $this->render('moderation/section_form', $data);
        }

        header('Location: ' . url('/moderation/sections?id=' . $result['id']));
        exit;
    }

    public function edit(array $params = [])
    {
        $this->guard();

        $id   = (int)($params['id'] ?? 0);
        $item = $this->service->find($id);

        if (!$item) {
            header('Location: ' . url('/moderation/sections'));
            exit;
        }

        $data = [
            'mode'             => 'edit',
            'item'             => $item,
            'parentId'         => $item['parent_id'] ?? null,
            'categoryOptions'  => $this->service->getCategoryOptions(),
            'accessOptions'    => $this->service->accessOptions(),
            'audienceOptions'  => $this->service->audienceOptions(),
            'managedByOptions' => $this->service->managedByOptions(),
            'statusOptions'    => $this->service->statusOptions(),
            'errors'           => [],
        ];

        $this->title = 'Редактировать раздел | Модерация';
        return $this->render('moderation/section_form', $data);
    }

    public function update(array $params = [])
    {
        $this->guard();

        $id = (int)($params['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/sections'));
            exit;
        }

        $result = $this->service->update($id, $_POST);

        if (!$result['ok']) {
            $item = $this->service->find($id) ?? [];
            $item = array_merge($item, [
                'title'          => trim($_POST['title'] ?? ''),
                'slug'           => trim($_POST['slug'] ?? ''),
                'description'    => trim($_POST['description'] ?? ''),
                'parent_id'      => ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null,
                'position'       => $_POST['position'] ?? '',
                'view_role_id'   => $_POST['view_role_id'] ?? '',
                'create_role_id' => $_POST['create_role_id'] ?? '',
                'reply_role_id'  => $_POST['reply_role_id'] ?? '',
                'manage_role_id' => $_POST['manage_role_id'] ?? '3',
                'is_locked'      => !empty($_POST['is_locked']) ? 1 : 0,
                'status'         => $_POST['status'] ?? 'published',
                'moderate_topics'  => !empty($_POST['moderate_topics']) ? 1 : 0,
                'allow_attachments'=> !empty($_POST['allow_attachments']) ? 1 : 0,
                'allow_guest_posts'=> !empty($_POST['allow_guest_posts']) ? 1 : 0,
            ]);

            $data = [
                'mode'             => 'edit',
                'item'             => $item,
                'parentId'         => $item['parent_id'] ?? null,
                'categoryOptions'  => $this->service->getCategoryOptions(),
                'accessOptions'    => $this->service->accessOptions(),
                'audienceOptions'  => $this->service->audienceOptions(),
                'managedByOptions' => $this->service->managedByOptions(),
                'statusOptions'    => $this->service->statusOptions(),
                'errors'           => $result['errors'] ?? [],
            ];

            $this->title = 'Редактировать раздел | Модерация';
            return $this->render('moderation/section_form', $data);
        }

        header('Location: ' . url('/moderation/sections?id=' . $id));
        exit;
    }

    public function delete(array $params = [])
    {
        $this->guard();

        $id = (int)($params['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/sections'));
            exit;
        }

        $this->service->delete($id);

        header('Location: ' . url('/moderation/sections'));
        exit;
    }

    private function guard(): void
    {
        if (!AuthService::check() || !AuthService::can('manage_categories')) {
            header('Location: ' . url('/'));
            exit;
        }
    }
}