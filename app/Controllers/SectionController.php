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
            'mode'            => 'create',
            'item'            => [
                'title'       => '',
                'slug'        => '',
                'description' => '',
                'parent_id'   => $parentId,
                'position'    => '',
                'access'      => 'public',
                'status'      => 'published',
            ],
            'parentId'        => $parentId,
            'categoryOptions'  => $this->service->getCategoryOptions(),
            'accessOptions'    => $this->service->accessOptions(),
            'audienceOptions'  => $this->service->audienceOptions(),
            'managedByOptions' => $this->service->managedByOptions(),
            'statusOptions'    => $this->service->statusOptions(),
            'errors'          => [],
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
            // Возвращаем форму с ошибками
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== ''
                ? (int)$_POST['parent_id']
                : null;

            $data = [
                'mode'            => 'create',
                'item'            => [
                    'title'       => trim($_POST['title'] ?? ''),
                    'slug'        => trim($_POST['slug'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'parent_id'   => $parentId,
                    'position'    => $_POST['position'] ?? '',
                    'access'      => $_POST['access'] ?? 'public',
                    'status'      => $_POST['status'] ?? 'published',
                ],
                'parentId'        => $parentId,
                'categoryOptions' => $this->service->getCategoryOptions(),
                'accessOptions'   => $this->service->accessOptions(),
                'statusOptions'   => $this->service->statusOptions(),
                'audienceOptions'  => $this->service->audienceOptions(),
                'managedByOptions' => $this->service->managedByOptions(),
                'errors'          => $result['errors'] ?? [],
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
            // Подставляем отправленные значения
            $item = array_merge($item, [
                'title'       => trim($_POST['title'] ?? ''),
                'slug'        => trim($_POST['slug'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'parent_id'   => ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null,
                'position'    => $_POST['position'] ?? '',
                'access'      => $_POST['access'] ?? 'public',
                'status'      => $_POST['status'] ?? 'published',
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
                'errors'           => [],
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

        $result = $this->service->delete($id);

        // Пока просто редиректим. Потом можно добавить flash-сообщение.
        // if (!$result['ok']) { ... }

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