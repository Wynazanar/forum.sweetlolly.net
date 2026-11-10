<?php
namespace App\Models;

use Core\Model;

class Application extends Model
{
    /**
     * Дерево категорий + секций + счётчики
     */
    public function getTree(array $filters = []): array
    {
        $sql = "
            SELECT 
                c.id,
                c.parent_id,
                c.title,
                c.slug,
                c.description,
                c.position,
                c.view_role_id,
                c.create_role_id,
                c.reply_role_id,
                c.manage_role_id,
                c.moderate_topics,
                c.allow_attachments,
                c.allow_guest_posts,
                c.status,
                c.is_locked,
                (
                    SELECT COUNT(*) 
                    FROM threads t 
                    WHERE t.category_id = c.id
                ) AS topics_count,
                (
                    SELECT COALESCE(SUM(t.posts_count), 0)
                    FROM threads t 
                    WHERE t.category_id = c.id
                ) AS posts_count
            FROM categories c
            ORDER BY 
                CASE WHEN c.parent_id IS NULL THEN 0 ELSE 1 END,
                COALESCE(c.parent_id, c.id),
                c.position,
                c.id
        ";

        $rows = $this->findMany($sql);

        $categories = [];
        $sectionsByParent = [];

        foreach ($rows as $row) {
            // PDO может вернуть parent_id как строку — сравниваем строго с null после приведения
            $parentId = $row['parent_id'];
            if ($parentId === null || $parentId === '' || $parentId === false) {
                $row['sections'] = [];
                $row['is_category'] = true;
                $categories[(int)$row['id']] = $row;
            } else {
                $row['is_category'] = false;
                $sectionsByParent[(int)$parentId][] = $row;
            }
        }

        foreach ($sectionsByParent as $parentId => $sections) {
            if (isset($categories[$parentId])) {
                $categories[$parentId]['sections'] = $sections;
            }
            // секции без родителя-категории не показываем (битые данные)
        }

        // Фильтр по названию / slug
        if (!empty($filters['q'])) {
            $q = mb_strtolower(trim($filters['q']));
            $categories = array_filter($categories, function ($cat) use ($q) {
                $matchCat = str_contains(mb_strtolower($cat['title']), $q)
                         || str_contains(mb_strtolower($cat['slug'] ?? ''), $q);

                $cat['sections'] = array_values(array_filter(
                    $cat['sections'] ?? [],
                    fn($s) => str_contains(mb_strtolower($s['title']), $q)
                           || str_contains(mb_strtolower($s['slug'] ?? ''), $q)
                ));

                return $matchCat || !empty($cat['sections']);
            });
        }

        // Фильтр по view_role_id
        if (array_key_exists('view_role_id', $filters) && $filters['view_role_id'] !== '' && $filters['view_role_id'] !== null) {
            $want = $filters['view_role_id'];
            $categories = array_filter($categories, function ($cat) use ($want) {
                $catMatch = (string)($cat['view_role_id'] ?? '') === (string)$want
                         || ($want === '' && ($cat['view_role_id'] === null || $cat['view_role_id'] === ''));

                $cat['sections'] = array_values(array_filter(
                    $cat['sections'] ?? [],
                    fn($s) => (string)($s['view_role_id'] ?? '') === (string)$want
                           || ($want === '' && ($s['view_role_id'] === null || $s['view_role_id'] === ''))
                ));

                return $catMatch || !empty($cat['sections']);
            });
        }

        return array_values($categories);
    }

    /**
     * Получить один элемент (категорию или раздел) по ID
     */
    public function findById(int $id): ?array
    {
        $row = $this->findOne(
            "SELECT 
                id, parent_id, title, slug, description, position,
                view_role_id, create_role_id, reply_role_id, manage_role_id,
                moderate_topics, allow_attachments, allow_guest_posts,
                status, is_locked
            FROM categories 
            WHERE id = ?",
            [$id]
        );

        if (!$row) {
            return null;
        }

        $row['is_category'] = ($row['parent_id'] === null || $row['parent_id'] === '');

        // Если это раздел — подтянем название родителя
        if (!$row['is_category']) {
            $parent = $this->findOne(
                "SELECT title FROM categories WHERE id = ?",
                [$row['parent_id']]
            );
            $row['parent_title'] = $parent['title'] ?? null;
        }

        // Счётчики
        $counts = $this->findOne(
            "SELECT 
                COUNT(*) as topics_count,
                COALESCE(SUM(posts_count), 0) as posts_count
            FROM threads 
            WHERE category_id = ?",
            [$id]
        );

        $row['topics_count'] = (int)($counts['topics_count'] ?? 0);
        $row['posts_count']  = (int)($counts['posts_count'] ?? 0);

        return $row;
    }

    /**
     * Опции категорий для фильтра / select родителя
     */
    public function getCategoryOptions(): array
    {
        $rows = $this->findMany(
            "SELECT id, title FROM categories WHERE parent_id IS NULL ORDER BY position, id"
        );

        $options = [];
        foreach ($rows as $row) {
            $options[$row['id']] = $row['title'];
        }
        return $options;
    }

    /**
     * Создание категории или раздела
     */
    public function create(array $data): int
    {
        $parentId = $data['parent_id'] ?? null;
        if ($parentId === '' || $parentId === false) {
            $parentId = null;
        } elseif ($parentId !== null) {
            $parentId = (int)$parentId;
        }

        $title            = trim($data['title'] ?? '');
        $slug             = $this->uniqueSlug($data['slug'] ?? $this->makeSlug($title));
        $desc             = $data['description'] ?? null;
        $position         = (int)($data['position'] ?? $this->nextPosition($parentId));

        $viewRoleId   = array_key_exists('view_role_id', $data) && $data['view_role_id'] !== '' && $data['view_role_id'] !== null
            ? (int)$data['view_role_id'] : null;
        $createRoleId = array_key_exists('create_role_id', $data) && $data['create_role_id'] !== '' && $data['create_role_id'] !== null
            ? (int)$data['create_role_id'] : null;
        $replyRoleId  = array_key_exists('reply_role_id', $data) && $data['reply_role_id'] !== '' && $data['reply_role_id'] !== null
            ? (int)$data['reply_role_id'] : null;
        $manageRoleId = (int)($data['manage_role_id'] ?? 3);

        $moderateTopics   = !empty($data['moderate_topics']) ? 1 : 0;
        $allowAttachments = !empty($data['allow_attachments']) ? 1 : 0;
        $allowGuestPosts  = !empty($data['allow_guest_posts']) ? 1 : 0;
        $status           = $data['status'] ?? 'published';
        $isLocked         = !empty($data['is_locked']) ? 1 : 0;

        $this->execute(
            "INSERT INTO categories (
                parent_id, title, slug, description, position,
                view_role_id, create_role_id, reply_role_id, manage_role_id,
                moderate_topics, allow_attachments, allow_guest_posts,
                status, is_locked
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $parentId, $title, $slug, $desc, $position,
                $viewRoleId, $createRoleId, $replyRoleId, $manageRoleId,
                $moderateTopics, $allowAttachments, $allowGuestPosts,
                $status, $isLocked,
            ]
        );

        return (int)$this->lastInsertId();
    }

    /**
     * Обновление
     */
    public function update(int $id, array $data): bool
    {
        $title = trim($data['title'] ?? '');
        $slug  = $this->uniqueSlug($data['slug'] ?? $this->makeSlug($title), $id);
        $desc  = $data['description'] ?? null;
        $position = isset($data['position']) ? (int)$data['position'] : null;

        $viewRoleId   = array_key_exists('view_role_id', $data) && $data['view_role_id'] !== '' && $data['view_role_id'] !== null
            ? (int)$data['view_role_id'] : null;
        $createRoleId = array_key_exists('create_role_id', $data) && $data['create_role_id'] !== '' && $data['create_role_id'] !== null
            ? (int)$data['create_role_id'] : null;
        $replyRoleId  = array_key_exists('reply_role_id', $data) && $data['reply_role_id'] !== '' && $data['reply_role_id'] !== null
            ? (int)$data['reply_role_id'] : null;
        $manageRoleId = (int)($data['manage_role_id'] ?? 3);

        $moderateTopics   = !empty($data['moderate_topics']) ? 1 : 0;
        $allowAttachments = !empty($data['allow_attachments']) ? 1 : 0;
        $allowGuestPosts  = !empty($data['allow_guest_posts']) ? 1 : 0;
        $status           = $data['status'] ?? 'published';
        $isLocked         = !empty($data['is_locked']) ? 1 : 0;

        $parentId = null;
        if (array_key_exists('parent_id', $data)) {
            $parentId = ($data['parent_id'] === '' || $data['parent_id'] === null)
                ? null
                : (int)$data['parent_id'];
        }

        $fields = [
            'title'              => $title,
            'slug'               => $slug,
            'description'        => $desc,
            'view_role_id'       => $viewRoleId,
            'create_role_id'     => $createRoleId,
            'reply_role_id'      => $replyRoleId,
            'manage_role_id'     => $manageRoleId,
            'moderate_topics'    => $moderateTopics,
            'allow_attachments'  => $allowAttachments,
            'allow_guest_posts'  => $allowGuestPosts,
            'status'             => $status,
            'is_locked'          => $isLocked,
        ];

        if ($position !== null) {
            $fields['position'] = $position;
        }
        if (array_key_exists('parent_id', $data)) {
            $fields['parent_id'] = $parentId;
        }

        $set = [];
        $params = [];
        foreach ($fields as $col => $val) {
            $set[] = "`$col` = ?";
            $params[] = $val;
        }
        $params[] = $id;

        return $this->execute(
            "UPDATE categories SET " . implode(', ', $set) . " WHERE id = ?",
            $params
        );
    }

    /**
     * Удаление (с проверкой на дочерние элементы и темы)
     */
    public function delete(int $id): array
    {
        $children = $this->findOne("SELECT COUNT(*) as cnt FROM categories WHERE parent_id = ?", [$id]);
        if (($children['cnt'] ?? 0) > 0) {
            return ['ok' => false, 'error' => 'Сначала удалите или переместите вложенные разделы'];
        }

        $threads = $this->findOne("SELECT COUNT(*) as cnt FROM threads WHERE category_id = ?", [$id]);
        if (($threads['cnt'] ?? 0) > 0) {
            return ['ok' => false, 'error' => 'В разделе есть темы. Сначала перенесите или удалите их'];
        }

        $this->execute("DELETE FROM categories WHERE id = ?", [$id]);
        return ['ok' => true];
    }

    /**
     * Следующая позиция внутри родителя
     */
    public function nextPosition(?int $parentId): int
    {
        $row = $this->findOne(
            "SELECT COALESCE(MAX(position), 0) + 1 as next_pos 
            FROM categories 
            WHERE " . ($parentId === null ? "parent_id IS NULL" : "parent_id = ?"),
            $parentId === null ? [] : [$parentId]
        );
        return (int)($row['next_pos'] ?? 1);
    }

    /**
     * Уникальный slug
     */
    private function uniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $slug = $this->makeSlug($slug);
        $base = $slug;
        $i = 1;

        while (true) {
            $params = [$slug];
            $sql = "SELECT id FROM categories WHERE slug = ?";
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

    private function makeSlug(string $title): string
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
        return trim($title, '-') ?: 'section';
    }

    public function makeSlugPublic(string $title): string
    {
        return $this->makeSlug($title);
    }
}