<?php
namespace App\Services;

use App\Models\Section;
use App\Models\Role;
use App\Services\AuthService;
use App\Services\ActivityLogService;

class SectionService
{
    private Section $model;

    public function __construct()
    {
        $this->model = new Section();
    }

    /**
     * Дерево категорий + секций (для списка)
     */
    public function getTree(array $filters = []): array
    {
        return $this->model->getTree($filters);
    }

    /**
     * Одна запись по ID
     */
    public function find(int $id): ?array
    {
        return $this->model->findById($id);
    }

    /**
     * Опции категорий для select
     */
    public function getCategoryOptions(): array
    {
        return $this->model->getCategoryOptions();
    }

    /**
     * Создание раздела / категории
     * @return array{ok: bool, id?: int, errors?: array}
     */
    public function create(array $input): array
    {
        $data = $this->prepareData($input);
        $errors = $this->validate($data);

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $id = $this->model->create($data);

        $userId = AuthService::user()['id'] ?? null;
        if ($userId && $id) {
            ActivityLogService::sectionCreated(
                (int)$userId,
                $id,
                $data['title'],
                $data['parent_id'],
                [
                    'slug'           => $data['slug'],
                    'view_role_id'   => $data['view_role_id'],
                    'create_role_id' => $data['create_role_id'],
                    'reply_role_id'  => $data['reply_role_id'],
                    'manage_role_id' => $data['manage_role_id'],
                    'status'         => $data['status'],
                ]
            );
        }

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Обновление
     * @return array{ok: bool, errors?: array}
     */
    public function update(int $id, array $input): array
    {
        $old = $this->model->findById($id);
        if (!$old) {
            return ['ok' => false, 'errors' => ['id' => 'Раздел не найден']];
        }

        $data = $this->prepareData($input, $id);
        $errors = $this->validate($data, $id);

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->model->update($id, $data);

        $userId = AuthService::user()['id'] ?? null;
        if ($userId) {
            ActivityLogService::sectionUpdated(
                (int)$userId,
                $id,
                $data['title'],
                [
                    'title'          => $old['title'],
                    'slug'           => $old['slug'] ?? null,
                    'view_role_id'   => $old['view_role_id'] ?? null,
                    'status'         => $old['status'] ?? null,
                ],
                [
                    'title'          => $data['title'],
                    'slug'           => $data['slug'],
                    'view_role_id'   => $data['view_role_id'],
                    'status'         => $data['status'],
                ]
            );
        }

        return ['ok' => true];
    }

    /**
     * Удаление
     * @return array{ok: bool, error?: string}
     */
    public function delete(int $id): array
    {
        $item = $this->model->findById($id);
        if (!$item) {
            return ['ok' => false, 'error' => 'Раздел не найден'];
        }

        $result = $this->model->delete($id);

        if ($result['ok']) {
            $userId = AuthService::user()['id'] ?? null;
            if ($userId) {
                ActivityLogService::sectionDeleted(
                    (int)$userId,
                    $id,
                    $item['title']
                );
            }
        }

        return $result;
    }

    /**
     * Подготовка и нормализация данных
     */
    private function prepareData(array $input, ?int $excludeId = null): array
    {
        $title = trim($input['title'] ?? '');
        $slug  = trim($input['slug'] ?? '');

        if ($slug === '') {
            $slug = $this->model->makeSlugPublic($title);
        }

        $parentId = array_key_exists('parent_id', $input) && $input['parent_id'] !== ''
            ? (int)$input['parent_id']
            : null;

        if ($excludeId && $parentId === $excludeId) {
            $parentId = null;
        }

        // '' / null → NULL в БД (public / all)
        $viewRoleId = array_key_exists('view_role_id', $input) && $input['view_role_id'] !== '' && $input['view_role_id'] !== null
            ? (int)$input['view_role_id']
            : null;

        $createRoleId = array_key_exists('create_role_id', $input) && $input['create_role_id'] !== '' && $input['create_role_id'] !== null
            ? (int)$input['create_role_id']
            : null;

        $replyRoleId = array_key_exists('reply_role_id', $input) && $input['reply_role_id'] !== '' && $input['reply_role_id'] !== null
            ? (int)$input['reply_role_id']
            : null;

        $manageRoleId = (int)($input['manage_role_id'] ?? 3);

        return [
            'parent_id'         => $parentId,
            'title'             => $title,
            'slug'              => $slug,
            'description'       => trim($input['description'] ?? '') ?: null,
            'position'          => isset($input['position']) && $input['position'] !== ''
                ? (int)$input['position']
                : null,
            'view_role_id'      => $viewRoleId,
            'create_role_id'    => $createRoleId,
            'reply_role_id'     => $replyRoleId,
            'manage_role_id'    => $manageRoleId,
            'moderate_topics'   => !empty($input['moderate_topics']) ? 1 : 0,
            'allow_attachments' => !empty($input['allow_attachments']) ? 1 : 0,
            'allow_guest_posts' => !empty($input['allow_guest_posts']) ? 1 : 0,
            'status'            => $input['status'] ?? 'published',
            'is_locked'         => !empty($input['is_locked']) ? 1 : 0,
        ];
    }

    /**
     * Валидация
     */
    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['title'] === '') {
            $errors['title'] = 'Название обязательно';
        } elseif (mb_strlen($data['title']) > 150) {
            $errors['title'] = 'Название слишком длинное (макс. 150 символов)';
        }

        if ($data['slug'] === '') {
            $errors['slug'] = 'Slug не может быть пустым';
        } elseif (!preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $data['slug'])) {
            $errors['slug'] = 'Slug может содержать только латиницу, цифры и дефис';
        }

        $allowedStatus = ['published', 'draft', 'hidden'];
        if (!in_array($data['status'], $allowedStatus, true)) {
            $errors['status'] = 'Недопустимый статус';
        }

        // Проверка существования родителя
        if ($data['parent_id'] !== null) {
            $parent = $this->model->findById($data['parent_id']);
            if (!$parent) {
                $errors['parent_id'] = 'Родительская категория не найдена';
            } elseif (!$parent['is_category']) {
                $errors['parent_id'] = 'Родителем может быть только категория верхнего уровня';
            }
        }

        $roleKeys = array_map('strval', array_keys($this->roleOptions())); // ['1','2','3',...]

        // view_role_id: null = public, иначе id роли
        if ($data['view_role_id'] !== null && !in_array((string)$data['view_role_id'], $roleKeys, true)) {
            $errors['view_role_id'] = 'Недопустимое значение доступа';
        }

        // create / reply: null = all, иначе id роли
        foreach (['create_role_id', 'reply_role_id'] as $field) {
            if ($data[$field] !== null && !in_array((string)$data[$field], $roleKeys, true)) {
                $errors[$field] = 'Недопустимое значение';
            }
        }

        // manage — всегда id роли
        if (!in_array((string)$data['manage_role_id'], $roleKeys, true)) {
            $errors['manage_role_id'] = 'Недопустимое значение';
        }

        return $errors;
    }

    public function accessOptions(): array
    {
        // '' = public
        $options = ['' => 'Все посетители'];
        return $options + $this->roleOptions();
    }

    public function statusOptions(): array
    {
        return [
            'published' => 'Опубликован',
            'draft'     => 'Черновик',
            'hidden'    => 'Скрыт',
        ];
    }

    public function audienceOptions(): array
    {
        // '' = all
        $options = ['' => 'Все посетители'];
        return $options + $this->roleOptions();
    }

    public function managedByOptions(): array
    {
        return $this->roleOptions();
    }

    private function roleOptions(): array
    {
        static $cache = null;

        if ($cache === null) {
            $cache = (new Role())->getOptions();
        }

        return $cache;
    }
}