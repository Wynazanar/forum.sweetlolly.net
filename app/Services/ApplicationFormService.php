<?php
namespace App\Services;

use App\Models\ApplicationForm;
use App\Models\ApplicationLayout;
use App\Models\Role;
use App\Services\AuthService;
use App\Services\ActivityLogService;

class ApplicationFormService
{
    private ApplicationForm $model;
    private ApplicationLayout $layoutModel;

    public function __construct()
    {
        $this->model = new ApplicationForm();
        $this->layoutModel = new ApplicationLayout();
    }

    public function findAll(array $filters = []): array
    {
        return $this->model->findAll($filters);
    }

    public function find(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->model->findBySlug($slug);
    }

    public function findPublishedAccepting(): array
    {
        return $this->model->findPublishedAccepting();
    }

    /**
     * @return array{ok: bool, id?: int, errors?: array}
     */
    public function create(array $input): array
    {
        $data = $this->prepareData($input);
        $errors = $this->validate($data);

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $userId = AuthService::user()['id'] ?? null;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        $id = $this->model->create($data);

        if ($userId && $id) {
            ActivityLogService::applicationFormCreated(
                (int)$userId,
                $id,
                $data['name'],
                [
                    'slug' => $data['slug'],
                    'status' => $data['status'],
                    'layout_id' => $data['layout_id'],
                    'application_type' => $data['application_type'],
                ]
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
            return ['ok' => false, 'errors' => ['id' => 'Шаблон не найден']];
        }

        $data = $this->prepareData($input, $id);
        $errors = $this->validate($data, $id);

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $userId = AuthService::user()['id'] ?? null;
        $data['updated_by'] = $userId;

        $this->model->update($id, $data);

        if ($userId) {
            ActivityLogService::applicationFormUpdated(
                (int)$userId,
                $id,
                $data['name'],
                [
                    'name' => $old['name'] ?? null,
                    'status' => $old['status'] ?? null,
                    'accepting' => $old['accepting'] ?? null,
                    'layout_id' => $old['layout_id'] ?? null,
                ],
                [
                    'name' => $data['name'],
                    'status' => $data['status'],
                    'accepting' => $data['accepting'],
                    'layout_id' => $data['layout_id'],
                ]
            );
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string, archived?: bool}
     */
    public function delete(int $id): array
    {
        $old = $this->model->findById($id);
        $result = $this->model->delete($id);

        if (!empty($result['ok']) && $old) {
            $userId = AuthService::user()['id'] ?? null;
            if ($userId) {
                ActivityLogService::applicationFormDeleted(
                    (int)$userId,
                    $id,
                    $old['name'] ?? ('#' . $id),
                    !empty($result['archived'])
                );
            }
        }

        return $result;
    }

    // ========== Options ==========

    public function statusOptions(): array
    {
        return [
            'draft'     => 'Черновик',
            'published' => 'Опубликован',
            'archived'  => 'В архиве',
        ];
    }

    public function typeOptions(): array
    {
        return [
            ''       => '— Не указан —',
            'team'   => 'В команду',
            'unban'  => 'Апелляция / Разбан',
            'other'  => 'Другое',
        ];
    }

    public function layoutOptions(): array
    {
        return $this->layoutModel->getOptions();
    }

    public function roleOptions(): array
    {
        $roleModel = new Role();
        return $roleModel->getOptions();
    }

    // ========== Private ==========

    private function prepareData(array $input, ?int $excludeId = null): array
    {
        $name = trim($input['name'] ?? '');
        $slug = trim($input['slug'] ?? '');

        if ($slug === '') {
            $slug = $this->model->makeSlugPublic($name);
        }
        $slug = $this->model->uniqueSlug($slug, $excludeId);

        $layoutId = array_key_exists('layout_id', $input) && $input['layout_id'] !== '' && $input['layout_id'] !== null
            ? (int)$input['layout_id']
            : null;

        // allowed_submitters — массив role_id (пустой = все зарегистрированные)
        $allowed = $input['allowed_submitters'] ?? [];
        if (is_string($allowed)) {
            $allowed = json_decode($allowed, true) ?: [];
        }
        if (!is_array($allowed)) {
            $allowed = [];
        }
        $allowed = array_values(array_filter(array_map('intval', $allowed)));

        // notify_user_ids — массив user_id (или строка "1, 2, 5")
        $notify = $input['notify_user_ids'] ?? [];
        if (is_string($notify)) {
            $decoded = json_decode($notify, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $notify = $decoded;
            } else {
                $notify = array_filter(array_map('trim', explode(',', $notify)));
            }
        }
        if (!is_array($notify)) {
            $notify = [];
        }
        $notify = array_values(array_filter(array_map('intval', $notify)));

        // visibility — массив role_id (пустой = только те, у кого есть право resolve_report)
        $visibility = $input['visibility'] ?? [];
        if (is_string($visibility)) {
            $visibility = json_decode($visibility, true) ?: [];
        }
        if (!is_array($visibility)) {
            $visibility = [];
        }
        $visibility = array_values(array_filter(array_map('intval', $visibility)));

        // questions
        $questions = $input['questions'] ?? [];
        if (is_string($questions)) {
            $questions = json_decode($questions, true) ?: [];
        }
        if (!is_array($questions)) {
            $questions = [];
        }

        // Нормализуем каждый вопрос
        $questions = $this->normalizeQuestions($questions);

        return [
            'layout_id'          => $layoutId,
            'name'               => $name,
            'slug'               => $slug,
            'application_type'   => trim($input['application_type'] ?? '') ?: null,
            'description'        => trim($input['description'] ?? '') ?: null,
            'allowed_submitters' => $allowed,
            'notify_user_ids'    => $notify,
            'visibility'         => $visibility,
            'questions'          => $questions,
            'accepting'          => !empty($input['accepting']) ? 1 : 0,
            'status'             => $input['status'] ?? 'draft',
        ];
    }

    private function normalizeQuestions(array $questions): array
    {
        $normalized = [];
        $allowedTypes = ['text', 'textarea', 'select', 'radio', 'checkbox', 'number', 'email', 'url', 'file', 'date'];

        foreach ($questions as $i => $q) {
            if (!is_array($q)) {
                continue;
            }

            $type = $q['type'] ?? 'text';
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'text';
            }

            $item = [
                'id'          => $q['id'] ?? ('q_' . ($i + 1)),
                'type'        => $type,
                'label'       => trim($q['label'] ?? ''),
                'required'    => !empty($q['required']),
                'help'        => trim($q['help'] ?? '') ?: null,
                'placeholder' => trim($q['placeholder'] ?? '') ?: null,
                // Раздел макета
                'section_id'  => trim((string)($q['section_id'] ?? '')) ?: null,
                // Позиция в сетке раздела
                'col'         => max(1, min(12, (int)($q['col'] ?? 1))),
                'row'         => max(1, (int)($q['row'] ?? ($i + 1))),
                'span'        => max(1, min(12, (int)($q['span'] ?? 12))),
                'rowSpan'     => max(1, min(20, (int)($q['rowSpan'] ?? 1))),
            ];

            // Для file: допустимые расширения и лимит размера
            if ($type === 'file') {
                $accept = $q['accept'] ?? [];
                if (is_string($accept)) {
                    $accept = preg_split('~[,\s]+~', $accept) ?: [];
                }
                if (!is_array($accept)) {
                    $accept = [];
                }
                $accept = array_values(array_filter(array_map(
                    static fn($e) => strtolower(ltrim(trim((string)$e), '.')),
                    $accept
                )));
                $item['accept'] = $accept; // [] = дефолтный безопасный список на сервере
                $item['max_size'] = max(1, (int)($q['max_size'] ?? 10485760));
            }


            if (in_array($type, ['select', 'radio', 'checkbox'], true)) {
                $opts = $q['options'] ?? [];
                if (is_string($opts)) {
                    $opts = array_filter(array_map('trim', explode("\n", $opts)));
                }
                $item['options'] = is_array($opts) ? array_values($opts) : [];
            }

            if ($item['label'] !== '') {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'Название обязательно';
        } elseif (mb_strlen($data['name']) > 150) {
            $errors['name'] = 'Название слишком длинное (макс. 150)';
        }

        if ($data['slug'] === '') {
            $errors['slug'] = 'Slug не может быть пустым';
        } elseif (!preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $data['slug'])) {
            $errors['slug'] = 'Slug может содержать только латиницу, цифры и дефис';
        }

        $allowedStatus = ['draft', 'published', 'archived'];
        if (!in_array($data['status'], $allowedStatus, true)) {
            $errors['status'] = 'Недопустимый статус';
        }

        if ($data['layout_id'] !== null) {
            $layout = $this->layoutModel->findById($data['layout_id']);
            if (!$layout) {
                $errors['layout_id'] = 'Макет не найден';
            }
        }

        if (empty($data['questions'])) {
            // Можно разрешить пустые вопросы в draft
            if ($data['status'] === 'published') {
                $errors['questions'] = 'Для публикации нужен хотя бы один вопрос';
            }
        }

        return $errors;
    }
}
