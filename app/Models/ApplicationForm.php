<?php
namespace App\Models;

use Core\Model;

class ApplicationForm extends Model
{
    /**
     * Список шаблонов с фильтрами
     */
    public function findAll(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'f.status = ?';
            $params[] = $filters['status'];
        }

        if (array_key_exists('accepting', $filters) && $filters['accepting'] !== '' && $filters['accepting'] !== null) {
            $where[]  = 'f.accepting = ?';
            $params[] = (int)$filters['accepting'];
        }

        if (!empty($filters['application_type'])) {
            $where[]  = 'f.application_type = ?';
            $params[] = $filters['application_type'];
        }

        if (!empty($filters['q'])) {
            $where[]  = '(f.name LIKE ? OR f.slug LIKE ? OR f.description LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql = "
            SELECT
                f.id, f.layout_id, f.name, f.slug, f.application_type,
                f.description, f.allowed_submitters, f.notify_user_ids,
                f.visibility, f.questions, f.accepting, f.status,
                f.created_by, f.updated_by, f.created_at, f.updated_at,
                l.name AS layout_name,
                (
                    SELECT COUNT(*) FROM applications a WHERE a.form_id = f.id
                ) AS submissions_count,
                (
                    SELECT COUNT(*) FROM applications a
                    WHERE a.form_id = f.id AND a.status = 'pending'
                ) AS pending_count
            FROM application_forms f
            LEFT JOIN application_layouts l ON l.id = f.layout_id
        ";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY f.updated_at DESC, f.id DESC';

        $rows = $this->findMany($sql, $params);

        foreach ($rows as &$row) {
            $row = $this->decodeJsonFields($row);
        }
        unset($row);

        return $rows;
    }

    public function findById(int $id): ?array
    {
        $row = $this->findOne(
            "SELECT
                f.id, f.layout_id, f.name, f.slug, f.application_type,
                f.description, f.allowed_submitters, f.notify_user_ids,
                f.visibility, f.questions, f.accepting, f.status,
                f.created_by, f.updated_by, f.created_at, f.updated_at,
                l.name AS layout_name, l.columns_count, l.gap, l.settings AS layout_settings
             FROM application_forms f
             LEFT JOIN application_layouts l ON l.id = f.layout_id
             WHERE f.id = ?",
            [$id]
        );

        return $row ? $this->decodeJsonFields($row) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $row = $this->findOne(
            "SELECT
                f.id, f.layout_id, f.name, f.slug, f.application_type,
                f.description, f.allowed_submitters, f.notify_user_ids,
                f.visibility, f.questions, f.accepting, f.status,
                f.created_by, f.updated_by, f.created_at, f.updated_at
             FROM application_forms f
             WHERE f.slug = ?",
            [$slug]
        );

        return $row ? $this->decodeJsonFields($row) : null;
    }

    /**
     * Опубликованные и принимающие заявки (для публичной части)
     */
    public function findPublishedAccepting(): array
    {
        $rows = $this->findMany(
            "SELECT id, name, slug, application_type, description, questions, layout_id
             FROM application_forms
             WHERE status = 'published' AND accepting = 1
             ORDER BY name ASC"
        );

        foreach ($rows as &$row) {
            $row = $this->decodeJsonFields($row);
        }
        unset($row);

        return $rows;
    }

    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO application_forms
                (layout_id, name, slug, application_type, description,
                 allowed_submitters, notify_user_ids, visibility, questions,
                 accepting, status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['layout_id'] ?? null,
                $data['name'],
                $data['slug'],
                $data['application_type'] ?? null,
                $data['description'] ?? null,
                $this->encodeJson($data['allowed_submitters'] ?? []),
                $this->encodeJson($data['notify_user_ids'] ?? []),
                $this->encodeJson($data['visibility'] ?? []),
                $this->encodeJson($data['questions'] ?? []),
                !empty($data['accepting']) ? 1 : 0,
                $data['status'] ?? 'draft',
                $data['created_by'] ?? null,
                $data['updated_by'] ?? null,
            ]
        );

        return (int)$this->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $this->execute(
            "UPDATE application_forms SET
                layout_id = ?, name = ?, slug = ?, application_type = ?,
                description = ?, allowed_submitters = ?, notify_user_ids = ?,
                visibility = ?, questions = ?, accepting = ?, status = ?,
                updated_by = ?
             WHERE id = ?",
            [
                $data['layout_id'] ?? null,
                $data['name'],
                $data['slug'],
                $data['application_type'] ?? null,
                $data['description'] ?? null,
                $this->encodeJson($data['allowed_submitters'] ?? []),
                $this->encodeJson($data['notify_user_ids'] ?? []),
                $this->encodeJson($data['visibility'] ?? []),
                $this->encodeJson($data['questions'] ?? []),
                !empty($data['accepting']) ? 1 : 0,
                $data['status'] ?? 'draft',
                $data['updated_by'] ?? null,
                $id,
            ]
        );

        return $this->affectedRows() >= 0;
    }

    public function delete(int $id): array
    {
        $item = $this->findById($id);
        if (!$item) {
            return ['ok' => false, 'error' => 'Шаблон не найден'];
        }

        $used = $this->findOne(
            "SELECT COUNT(*) AS cnt FROM applications WHERE form_id = ?",
            [$id]
        );

        if ($used && (int)$used['cnt'] > 0) {
            // Не удаляем, а архивируем
            $this->execute(
                "UPDATE application_forms SET status = 'archived', accepting = 0 WHERE id = ?",
                [$id]
            );
            return ['ok' => true, 'archived' => true];
        }

        $this->execute("DELETE FROM application_forms WHERE id = ?", [$id]);

        return ['ok' => true];
    }

    public function uniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $base = $this->makeSlug($slug);
        $slug = $base;
        $i = 1;

        while (true) {
            $sql = "SELECT id FROM application_forms WHERE slug = ?";
            $params = [$slug];

            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }

            $exists = $this->findOne($sql, $params);
            if (!$exists) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }

    public function makeSlug(string $title): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh',
            'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
            'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts',
            'ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        ];
        $title = mb_strtolower(trim($title));
        $title = strtr($title, $map);
        $title = preg_replace('~[^a-z0-9]+~u', '-', $title);
        return trim($title, '-') ?: 'form';
    }

    public function makeSlugPublic(string $title): string
    {
        return $this->makeSlug($title);
    }

    private function encodeJson($value): string
    {
        if (is_string($value)) {
            // уже JSON?
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }
        return json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
    }

    private function decodeJsonFields(array $row): array
    {
        foreach (['allowed_submitters', 'notify_user_ids', 'visibility', 'questions', 'layout_settings'] as $key) {
            if (isset($row[$key]) && is_string($row[$key])) {
                $row[$key] = json_decode($row[$key], true) ?: [];
            }
        }
        return $row;
    }
}
