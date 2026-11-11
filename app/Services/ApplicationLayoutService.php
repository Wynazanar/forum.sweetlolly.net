<?php
namespace App\Services;

use App\Models\ApplicationLayout;
use App\Services\AuthService;
use App\Services\ActivityLogService;

class ApplicationLayoutService
{
    private ApplicationLayout $model;

    public function __construct()
    {
        $this->model = new ApplicationLayout();
    }

    public function findAll(): array
    {
        return $this->model->findAll();
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
        $id = $this->model->create($data);

        if ($userId && $id) {
            ActivityLogService::applicationLayoutCreated(
                (int)$userId,
                $id,
                $data['name'],
                ['sections_count' => count($data['settings']['sections'] ?? [])]
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
            return ['ok' => false, 'errors' => ['id' => 'Макет не найден']];
        }

        $data = $this->prepareData($input, $id);
        $errors = $this->validate($data, $id);

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->model->update($id, $data);

        $userId = AuthService::user()['id'] ?? null;
        if ($userId) {
            ActivityLogService::applicationLayoutUpdated(
                (int)$userId,
                $id,
                $data['name'],
                ['name' => $old['name'] ?? null],
                ['name' => $data['name'], 'sections_count' => count($data['settings']['sections'] ?? [])]
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
                ActivityLogService::applicationLayoutDeleted(
                    (int)$userId,
                    $id,
                    $old['name'] ?? ('#' . $id)
                );
            }
        }

        return $result;
    }

    private function prepareData(array $input, ?int $excludeId = null): array
    {
        $name = trim($input['name'] ?? '');
        $slug = trim($input['slug'] ?? '');

        if ($slug === '') {
            $slug = $this->model->makeSlugPublic($name);
        }

        $slug = $this->model->uniqueSlug($slug, $excludeId);

        // sections из POST: settings[sections] или JSON в settings
        $sections = $input['sections'] ?? null;
        if ($sections === null && isset($input['settings'])) {
            $settingsRaw = $input['settings'];
            if (is_string($settingsRaw)) {
                $settingsRaw = json_decode($settingsRaw, true) ?: [];
            }
            $sections = $settingsRaw['sections'] ?? [];
        }
        if (is_string($sections)) {
            $sections = json_decode($sections, true) ?: [];
        }
        if (!is_array($sections)) {
            $sections = [];
        }

        $sections = $this->normalizeSections($sections);

        $settings = [
            'columns'  => max(1, min(24, (int)($input['columns_count'] ?? 12))),
            'gap'      => max(0, (int)($input['gap'] ?? 16)),
            'sections' => $sections,
        ];

        return [
            'name'          => $name,
            'slug'          => $slug,
            'description'   => trim($input['description'] ?? '') ?: null,
            'columns_count' => $settings['columns'],
            'gap'           => $settings['gap'],
            'settings'      => $settings,
            'is_system'     => !empty($input['is_system']) ? 1 : 0,
        ];
    }

    /**
     * Нормализация разделов макета.
     * Обязателен хотя бы один с непустым title.
     */
    public function normalizeSections(array $sections): array
    {
        $out = [];
        foreach ($sections as $i => $sec) {
            if (!is_array($sec)) {
                continue;
            }
            $title = trim($sec['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $id = trim((string)($sec['id'] ?? ''));
            if ($id === '') {
                $id = 'sec_' . substr(md5($title . $i . microtime(true)), 0, 8);
            }
            $out[] = [
                'id'          => $id,
                'title'       => $title,
                'description' => trim($sec['description'] ?? '') ?: null,
                'order'       => (int)($sec['order'] ?? ($i + 1)),
            ];
        }

        usort($out, fn($a, $b) => $a['order'] <=> $b['order']);

        // перенумеровать order
        foreach ($out as $i => &$s) {
            $s['order'] = $i + 1;
        }
        unset($s);

        return array_values($out);
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'Название обязательно';
        } elseif (mb_strlen($data['name']) > 100) {
            $errors['name'] = 'Название слишком длинное (макс. 100)';
        }

        if ($data['slug'] === '') {
            $errors['slug'] = 'Slug не может быть пустым';
        } elseif (!preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $data['slug'])) {
            $errors['slug'] = 'Slug может содержать только латиницу, цифры и дефис';
        }

        $sections = $data['settings']['sections'] ?? [];
        if (empty($sections)) {
            $errors['sections'] = 'Нужен хотя бы один раздел';
        }

        return $errors;
    }
}
