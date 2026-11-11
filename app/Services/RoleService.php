<?php
namespace App\Services;

use App\Models\Role;
use App\Services\AuthService;
use App\Services\ActivityLogService;

class RoleService
{
    private Role $model;

    public function __construct()
    {
        $this->model = new Role();
    }

    public function findAll(): array
    {
        return $this->model->getAll();
    }

    public function find(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function getOptions(): array
    {
        return $this->model->getOptions();
    }

    /**
     * Каталог прав из config/permissions.php
     */
    public function catalog(): array
    {
        $path = dirname(__DIR__) . '/Config/permissions.php';
        if (!is_file($path)) {
            return [];
        }
        $data = require $path;
        return is_array($data) ? $data : [];
    }

    /**
     * Плоский список всех ключей прав
     * @return string[]
     */
    public function allPermissionKeys(): array
    {
        $keys = [];
        foreach ($this->catalog() as $group) {
            foreach (array_keys($group['permissions'] ?? []) as $key) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * @return array{ok: bool, id?: int, errors?: array}
     */
    public function create(array $input): array
    {
        $data = $this->prepare($input);
        $errors = $this->validate($data);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $id = $this->model->create($data['name'], $data['permissions']);

        $userId = AuthService::user()['id'] ?? null;
        if ($userId) {
            ActivityLogService::log(
                (int)$userId,
                'created_role',
                'success',
                'role',
                $id,
                $data['name'],
                ['permissions' => array_keys(array_filter($data['permissions']))]
            );
        }

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @return array{ok: bool, errors?: array}
     */
    public function update(int $id, array $input): array
    {
        $old = $this->model->findById($id);
        if (!$old) {
            return ['ok' => false, 'errors' => ['id' => 'Роль не найдена']];
        }

        $data = $this->prepare($input, $id);
        // системные роли: имя не меняем
        if (in_array($old['name'], ['user', 'moderator', 'admin'], true)) {
            $data['name'] = $old['name'];
        }

        $errors = $this->validate($data, $id);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->model->updateRole($id, $data['name'], $data['permissions']);

        $userId = AuthService::user()['id'] ?? null;
        if ($userId) {
            ActivityLogService::log(
                (int)$userId,
                'updated_role',
                'success',
                'role',
                $id,
                $data['name'],
                [
                    'before' => array_keys(array_filter($old['permissions'] ?? [])),
                    'after'  => array_keys(array_filter($data['permissions'])),
                ]
            );
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function delete(int $id): array
    {
        $old = $this->model->findById($id);
        $result = $this->model->delete($id);

        if (!empty($result['ok']) && $old) {
            $userId = AuthService::user()['id'] ?? null;
            if ($userId) {
                ActivityLogService::log(
                    (int)$userId,
                    'deleted_role',
                    'success',
                    'role',
                    $id,
                    $old['name']
                );
            }
        }

        return $result;
    }

    private function prepare(array $input, ?int $excludeId = null): array
    {
        $name = strtolower(trim($input['name'] ?? ''));
        $name = preg_replace('~[^a-z0-9_]+~', '_', $name) ?? '';
        $name = trim($name, '_');

        $allowedKeys = $this->allPermissionKeys();
        $raw = $input['permissions'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $permissions = [];
        foreach ($allowedKeys as $key) {
            $permissions[$key] = !empty($raw[$key]);
        }

        return [
            'name'        => $name,
            'permissions' => $permissions,
        ];
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'Системное имя роли обязательно (латиница)';
        } elseif (!preg_match('~^[a-z][a-z0-9_]{1,48}$~', $data['name'])) {
            $errors['name'] = 'Имя: латиница, цифры, _ ; от 2 символов';
        } else {
            $existing = $this->model->findByName($data['name']);
            if ($existing && (int)$existing['id'] !== (int)$excludeId) {
                $errors['name'] = 'Такая роль уже существует';
            }
        }

        return $errors;
    }
}
