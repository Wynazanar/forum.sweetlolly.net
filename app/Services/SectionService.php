<?php
namespace App\Services;

use App\Models\Section;
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
                    'slug'   => $data['slug'],
                    'access' => $data['access'],
                    'status' => $data['status'],
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
        $item = $this->model->findById($id);
        if (!$item) {
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
                $old ? [
                    'title'  => $old['title'],
                    'slug'   => $old['slug'] ?? null,
                    'access' => $old['access'] ?? null,
                    'status' => $old['status'] ?? null,
                ] : null,
                [
                    'title'  => $data['title'],
                    'slug'   => $data['slug'],
                    'access' => $data['access'],
                    'status' => $data['status'],
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

        return [
            'parent_id'          => $parentId,
            'title'              => $title,
            'slug'               => $slug,
            'description'        => trim($input['description'] ?? '') ?: null,
            'position'           => isset($input['position']) && $input['position'] !== ''
                ? (int)$input['position']
                : null,
            'access'             => $input['access'] ?? 'public',
            'can_create_topics'  => $input['can_create_topics'] ?? 'players',
            'can_reply'          => $input['can_reply'] ?? 'players',
            'managed_by'         => $input['managed_by'] ?? 'admins',
            'moderate_topics'   => !empty($input['moderate_topics']) ? 1 : 0,
            'allow_attachments' => !empty($input['allow_attachments']) ? 1 : 0,
            'allow_guest_posts' => !empty($input['allow_guest_posts']) ? 1 : 0,
            'status'             => $input['status'] ?? 'published',
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

        $allowedAccess = ['public', 'players', 'moderators', 'admins', 'closed'];
        if (!in_array($data['access'], $allowedAccess, true)) {
            $errors['access'] = 'Недопустимое значение доступа';
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

        return $errors;
    }

    public function accessOptions(): array
    {
        return [
            'public'     => 'Публичный',
            'players'    => 'Игроки',
            'moderators' => 'Модераторы',
            'admins'     => 'Администраторы',
            'closed'     => 'Закрытый',
        ];
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
        return [
            'all'        => 'Все посетители',
            'players'    => 'Зарегистрированные игроки',
            'moderators' => 'Модераторы',
            'admins'     => 'Администраторы',
        ];
    }

    public function managedByOptions(): array
    {
        return [
            'moderators' => 'Модераторы',
            'admins'     => 'Администраторы',
        ];
    }
}