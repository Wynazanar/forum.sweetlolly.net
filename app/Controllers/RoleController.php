<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\RoleService;

class RoleController extends Controller
{
    private RoleService $service;

    public function __construct()
    {
        $this->service = new RoleService();
    }

    public function create(array $params = [])
    {
        $this->guard();

        $data = [
            'mode'      => 'create',
            'item'      => [
                'name'        => '',
                'permissions' => [],
            ],
            'catalog'   => $this->service->catalog(),
            'errors'    => [],
        ];

        $this->title = 'Создать роль | Модерация';
        return $this->render('moderation/role_form', $data);
    }

    public function store(array $params = [])
    {
        $this->guard();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('/moderation/roles'));
            exit;
        }

        $result = $this->service->create($_POST);

        if (!$result['ok']) {
            $data = [
                'mode'    => 'create',
                'item'    => [
                    'name'        => trim($_POST['name'] ?? ''),
                    'permissions' => $_POST['permissions'] ?? [],
                ],
                'catalog' => $this->service->catalog(),
                'errors'  => $result['errors'] ?? [],
            ];
            $this->title = 'Создать роль | Модерация';
            return $this->render('moderation/role_form', $data);
        }

        header('Location: ' . url('/moderation/roles'));
        exit;
    }

    public function edit(array $params = [])
    {
        $this->guard();

        $id   = (int)($params['id'] ?? 0);
        $item = $this->service->find($id);

        if (!$item) {
            header('Location: ' . url('/moderation/roles'));
            exit;
        }

        $data = [
            'mode'    => 'edit',
            'item'    => $item,
            'catalog' => $this->service->catalog(),
            'errors'  => [],
        ];

        $this->title = 'Редактировать роль | Модерация';
        return $this->render('moderation/role_form', $data);
    }

    public function update(array $params = [])
    {
        $this->guard();

        $id = (int)($params['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/roles'));
            exit;
        }

        $result = $this->service->update($id, $_POST);

        if (!$result['ok']) {
            $item = $this->service->find($id) ?? [];
            $item['name'] = trim($_POST['name'] ?? ($item['name'] ?? ''));
            $item['permissions'] = $_POST['permissions'] ?? [];

            $data = [
                'mode'    => 'edit',
                'item'    => $item,
                'catalog' => $this->service->catalog(),
                'errors'  => $result['errors'] ?? [],
            ];
            $this->title = 'Редактировать роль | Модерация';
            return $this->render('moderation/role_form', $data);
        }

        header('Location: ' . url('/moderation/roles'));
        exit;
    }

    public function delete(array $params = [])
    {
        $this->guard();

        $id = (int)($params['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/roles'));
            exit;
        }

        $this->service->delete($id);

        header('Location: ' . url('/moderation/roles'));
        exit;
    }

    private function guard(): void
    {
        if (!AuthService::check() || (!AuthService::can('manage_roles') && !AuthService::hasRole('admin'))) {
            header('Location: ' . url('/'));
            exit;
        }
    }
}
