<?php
namespace App\Models;

use Core\Model;

class Thread extends Model
{
    /**
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public function findAdminList(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(t.title LIKE ? OR t.slug LIKE ? OR CAST(t.id AS CHAR) = ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $filters['q'];
        }

        if (!empty($filters['category_id'])) {
            $where[] = 't.category_id = ?';
            $params[] = (int)$filters['category_id'];
        }

        if (isset($filters['locked']) && $filters['locked'] !== '' && $filters['locked'] !== null) {
            $where[] = 't.is_locked = ?';
            $params[] = (int)$filters['locked'];
        }

        if (isset($filters['sticky']) && $filters['sticky'] !== '' && $filters['sticky'] !== null) {
            $where[] = 't.is_sticky = ?';
            $params[] = (int)$filters['sticky'];
        }

        if (isset($filters['hidden']) && $filters['hidden'] !== '' && $filters['hidden'] !== null) {
            $where[] = 't.is_hidden = ?';
            $params[] = (int)$filters['hidden'];
        } else {
            // по умолчанию скрытые не показываем, если явно не фильтруют
            if (empty($filters['show_hidden'])) {
                $where[] = 'COALESCE(t.is_hidden, 0) = 0';
            }
        }

        // Черновики: по умолчанию только свои (author_id) + опубликованные/pending чужие
        if (!empty($filters['status'])) {
            $where[] = 'COALESCE(t.status, \'published\') = ?';
            $params[] = $filters['status'];
        } elseif (empty($filters['show_all_drafts'])) {
            $uid = (int)($filters['viewer_id'] ?? 0);
            if ($uid > 0) {
                $where[] = '(COALESCE(t.status, \'published\') != \'draft\' OR t.user_id = ?)';
                $params[] = $uid;
            } else {
                $where[] = 'COALESCE(t.status, \'published\') != \'draft\'';
            }
        }

        $whereSql = implode(' AND ', $where);

        $count = $this->findOne(
            "SELECT COUNT(*) AS cnt
             FROM threads t
             WHERE $whereSql",
            $params
        );
        $total = (int)($count['cnt'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));

        $items = $this->findMany(
            "SELECT
                t.*,
                u.username AS author_name,
                c.title AS category_title,
                GREATEST(0, t.posts_count - 1) AS replies_count
             FROM threads t
             JOIN users u ON u.id = t.user_id
             JOIN categories c ON c.id = t.category_id
             WHERE $whereSql
             ORDER BY t.is_sticky DESC, t.last_post_at DESC, t.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return compact('items', 'total', 'page', 'pages');
    }

    public function findById(int $id): ?array
    {
        return $this->findOne(
            "SELECT
                t.*,
                u.username AS author_name,
                c.title AS category_title,
                GREATEST(0, t.posts_count - 1) AS replies_count
             FROM threads t
             JOIN users u ON u.id = t.user_id
             JOIN categories c ON c.id = t.category_id
             WHERE t.id = ?
             LIMIT 1",
            [$id]
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->findOne(
            "SELECT t.* FROM threads t WHERE t.slug = ? LIMIT 1",
            [$slug]
        );
    }

    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO threads
                (category_id, user_id, title, slug, is_sticky, is_locked, is_hidden, status, views, posts_count, last_post_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW(), NOW())",
            [
                (int)$data['category_id'],
                (int)$data['user_id'],
                $data['title'],
                $data['slug'],
                !empty($data['is_sticky']) ? 1 : 0,
                !empty($data['is_locked']) ? 1 : 0,
                !empty($data['is_hidden']) ? 1 : 0,
                $data['status'] ?? 'published',
            ]
        );
        return (int)$this->lastInsertId();
    }

    public function updateThread(int $id, array $data): bool
    {
        $this->execute(
            "UPDATE threads SET
                category_id = ?,
                title = ?,
                slug = ?,
                is_sticky = ?,
                is_locked = ?,
                is_hidden = ?,
                status = ?,
                updated_at = NOW()
             WHERE id = ?",
            [
                (int)$data['category_id'],
                $data['title'],
                $data['slug'],
                !empty($data['is_sticky']) ? 1 : 0,
                !empty($data['is_locked']) ? 1 : 0,
                !empty($data['is_hidden']) ? 1 : 0,
                $data['status'] ?? 'published',
                $id,
            ]
        );
        return true;
    }

    public function setFlags(int $id, array $flags): bool
    {
        $sets = [];
        $params = [];
        foreach (['is_sticky', 'is_locked', 'is_hidden'] as $f) {
            if (array_key_exists($f, $flags)) {
                $sets[] = "$f = ?";
                $params[] = !empty($flags[$f]) ? 1 : 0;
            }
        }
        if (!$sets) {
            return false;
        }
        $params[] = $id;
        $this->execute(
            "UPDATE threads SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?",
            $params
        );
        return true;
    }

    public function move(int $id, int $categoryId): bool
    {
        $this->execute(
            "UPDATE threads SET category_id = ?, updated_at = NOW() WHERE id = ?",
            [$categoryId, $id]
        );
        return true;
    }

    public function deleteHard(int $id): bool
    {
        $this->execute("DELETE FROM threads WHERE id = ?", [$id]);
        return true;
    }

    public function makeSlug(string $title): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z',
            'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
            'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        ];
        $s = mb_strtolower(trim($title), 'UTF-8');
        $s = strtr($s, $map);
        $s = preg_replace('~[^a-z0-9]+~', '-', $s) ?? '';
        $s = trim($s, '-') ?: 'thread';
        $base = mb_substr($s, 0, 180);
        $slug = $base;
        $i = 1;
        while ($this->findBySlug($slug)) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }
}
