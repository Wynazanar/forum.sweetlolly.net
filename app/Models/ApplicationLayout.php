<?php
namespace App\Models;

use Core\Model;

class ApplicationLayout extends Model
{
    public function findAll(): array
    {
        return $this->findMany(
            "SELECT id, name, slug, description, columns_count, gap, settings,
                    is_system, created_by, created_at, updated_at
             FROM application_layouts
             ORDER BY is_system DESC, name ASC"
        );
    }

    public function findById(int $id): ?array
    {
        $row = $this->findOne(
            "SELECT id, name, slug, description, columns_count, gap, settings,
                    is_system, created_by, created_at, updated_at
             FROM application_layouts
             WHERE id = ?",
            [$id]
        );

        if ($row && isset($row['settings']) && is_string($row['settings'])) {
            $row['settings'] = json_decode($row['settings'], true) ?: [];
        }

        return $row;
    }

    public function findBySlug(string $slug): ?array
    {
        $row = $this->findOne(
            "SELECT id, name, slug, description, columns_count, gap, settings,
                    is_system, created_by, created_at, updated_at
             FROM application_layouts
             WHERE slug = ?",
            [$slug]
        );

        if ($row && isset($row['settings']) && is_string($row['settings'])) {
            $row['settings'] = json_decode($row['settings'], true) ?: [];
        }

        return $row;
    }

    public function create(array $data): int
    {
        $settings = $data['settings'] ?? [];
        if (!is_string($settings)) {
            $settings = json_encode($settings, JSON_UNESCAPED_UNICODE);
        }

        $this->execute(
            "INSERT INTO application_layouts
                (name, slug, description, columns_count, gap, settings, is_system, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['slug'],
                $data['description'] ?? null,
                (int)($data['columns_count'] ?? 12),
                (int)($data['gap'] ?? 16),
                $settings,
                !empty($data['is_system']) ? 1 : 0,
                $data['created_by'] ?? null,
            ]
        );

        return (int)$this->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $settings = $data['settings'] ?? [];
        if (!is_string($settings)) {
            $settings = json_encode($settings, JSON_UNESCAPED_UNICODE);
        }

        $this->execute(
            "UPDATE application_layouts SET
                name = ?, slug = ?, description = ?,
                columns_count = ?, gap = ?, settings = ?,
                is_system = ?
             WHERE id = ?",
            [
                $data['name'],
                $data['slug'],
                $data['description'] ?? null,
                (int)($data['columns_count'] ?? 12),
                (int)($data['gap'] ?? 16),
                $settings,
                !empty($data['is_system']) ? 1 : 0,
                $id,
            ]
        );

        return $this->affectedRows() >= 0;
    }

    public function delete(int $id): array
    {
        $item = $this->findById($id);
        if (!$item) {
            return ['ok' => false, 'error' => 'Макет не найден'];
        }

        if (!empty($item['is_system'])) {
            return ['ok' => false, 'error' => 'Системный макет нельзя удалить'];
        }

        // Отвязываем шаблоны от макета, затем удаляем
        $this->execute(
            "UPDATE application_forms SET layout_id = NULL WHERE layout_id = ?",
            [$id]
        );

        $this->execute("DELETE FROM application_layouts WHERE id = ?", [$id]);

        return ['ok' => true];
    }

    public function uniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $base = $this->makeSlug($slug);
        $slug = $base;
        $i = 1;

        while (true) {
            $sql = "SELECT id FROM application_layouts WHERE slug = ?";
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
        return trim($title, '-') ?: 'layout';
    }

    public function makeSlugPublic(string $title): string
    {
        return $this->makeSlug($title);
    }

    /**
     * Опции для select
     * @return array<int, string>
     */
    public function getOptions(): array
    {
        $rows = $this->findAll();
        $options = [];
        foreach ($rows as $row) {
            $options[(int)$row['id']] = $row['name'];
        }
        return $options;
    }
}
