<?php
namespace App\Models;

use Core\Model;

/**
 * Модель репортов (жалоб на посты / темы / пользователей).
 */
class Report extends Model
{
    private function selectWithUsers(): string
    {
        return "SELECT
                r.*,
                reporter.username AS reporter_name,
                resolver.username AS resolver_name
             FROM reports r
             LEFT JOIN users reporter ON reporter.id = r.reporter_id
             LEFT JOIN users resolver ON resolver.id = r.resolved_by";
    }

    /**
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public function getList(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = '(r.reason LIKE ?
                        OR r.description LIKE ?
                        OR reporter.username LIKE ?
                        OR CAST(r.id AS CHAR) = ?
                        OR CAST(r.target_id AS CHAR) = ?)';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $filters['q'];
            $params[] = $filters['q'];
        }

        if (!empty($filters['status'])) {
            $where[]  = 'r.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['target_type'])) {
            $where[]  = 'r.target_type = ?';
            $params[] = $filters['target_type'];
        }

        if (!empty($filters['date'])) {
            $where[]  = 'DATE(r.created_at) = ?';
            $params[] = $filters['date'];
        }

        $whereSql = implode(' AND ', $where);

        $totalRow = $this->findOne(
            "SELECT COUNT(*) AS cnt
             FROM reports r
             LEFT JOIN users reporter ON reporter.id = r.reporter_id
             WHERE {$whereSql}",
            $params
        );
        $total = (int)($totalRow['cnt'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));

        if ($page > $pages) {
            $page   = $pages;
            $offset = ($page - 1) * $perPage;
        }

        $items = $this->findMany(
            $this->selectWithUsers() . "
             WHERE {$whereSql}
             ORDER BY
                FIELD(r.status, 'pending', 'needs_info', 'resolved', 'rejected'),
                r.created_at DESC,
                r.id DESC
             LIMIT " . (int)$perPage . " OFFSET " . (int)$offset,
            $params
        );

        foreach ($items as &$item) {
            $item = $this->enrich($item);
        }
        unset($item);

        return compact('items', 'total', 'page', 'pages');
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->findOne(
            $this->selectWithUsers() . ' WHERE r.id = ?',
            [(string)$id]
        );

        return $row ? $this->enrich($row, true) : null;
    }

    public function setStatus(int $id, string $status, ?int $resolverId = null): bool
    {
        $allowed = ['pending', 'needs_info', 'resolved', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        if (in_array($status, ['resolved', 'rejected'], true)) {
            $this->execute(
                'UPDATE reports SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?',
                [$status, $resolverId, $id]
            );
        } else {
            $this->execute(
                'UPDATE reports SET status = ?, resolved_by = NULL, resolved_at = NULL WHERE id = ?',
                [$status, $id]
            );
        }
        return true;
    }

    private function enrich(array $row, bool $deep = false): array
    {
        $typeLabels = [
            'post'   => 'Сообщение',
            'thread' => 'Тема',
            'user'   => 'Игрок',
        ];

        $type = $row['target_type'] ?? '';
        $id   = (int)($row['target_id'] ?? 0);

        $row['type_label'] = $typeLabels[$type] ?? $type;
        $row['title']      = sprintf(
            'R-%03d · %s #%d',
            (int)$row['id'],
            $row['type_label'],
            $id
        );

        $parts = [];
        if (!empty($row['reason'])) {
            $parts[] = $row['reason'];
        }
        if (!empty($row['description'])) {
            $desc = mb_strlen($row['description']) > 80
                ? mb_substr($row['description'], 0, 80) . '…'
                : $row['description'];
            $parts[] = $desc;
        }
        $row['context'] = implode(' — ', $parts);

        $row['target_preview'] = null;
        $row['target_url']     = null;
        $row['target_name']    = null;
        $row['target_user_id'] = null;

        if ($deep && $id > 0) {
            $row = $this->attachTarget($row, $type, $id);
        }

        return $row;
    }

    private function attachTarget(array $row, string $type, int $id): array
    {
        try {
            if ($type === 'post') {
                $post = $this->findOne(
                    "SELECT p.id, p.content, p.thread_id, p.user_id, p.status,
                            t.title AS thread_title, u.username AS author_name
                     FROM posts p
                     LEFT JOIN threads t ON t.id = p.thread_id
                     LEFT JOIN users u ON u.id = p.user_id
                     WHERE p.id = ?
                     LIMIT 1",
                    [$id]
                );
                if ($post) {
                    $snippet = trim(strip_tags((string)($post['content'] ?? '')));
                    if (mb_strlen($snippet) > 200) {
                        $snippet = mb_substr($snippet, 0, 200) . '…';
                    }
                    $row['target_preview'] = $snippet;
                    $row['target_name']    = ($post['author_name'] ?? '—') . ' · ' . ($post['thread_title'] ?? 'тема');
                    $row['target_url']     = url('/moderation/discussions?id=' . (int)$post['thread_id'] . '&post_id=' . $id);
                    $row['target_user_id'] = (int)($post['user_id'] ?? 0);
                }
            } elseif ($type === 'thread') {
                $thread = $this->findOne(
                    "SELECT t.id, t.title, t.user_id, t.status, u.username AS author_name
                     FROM threads t
                     LEFT JOIN users u ON u.id = t.user_id
                     WHERE t.id = ?
                     LIMIT 1",
                    [$id]
                );
                if ($thread) {
                    $row['target_preview'] = $thread['title'] ?? '';
                    $row['target_name']    = ($thread['author_name'] ?? '—') . ' · тема';
                    $row['target_url']     = url('/moderation/topics?id=' . $id);
                    $row['target_user_id'] = (int)($thread['user_id'] ?? 0);
                }
            } elseif ($type === 'user') {
                $user = $this->findOne(
                    "SELECT id, username FROM users WHERE id = ? LIMIT 1",
                    [$id]
                );
                if ($user) {
                    $row['target_preview'] = $user['username'] ?? '';
                    $row['target_name']    = $user['username'] ?? '—';
                    $row['target_url']     = url('/moderation/players?id=' . $id);
                    $row['target_user_id'] = (int)$user['id'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $row;
    }

    public function getStatusOptions(): array
    {
        return [
            'pending'    => 'Ожидает',
            'needs_info' => 'Нужно уточнение',
            'resolved'   => 'Решён',
            'rejected'   => 'Отклонён',
        ];
    }

    public function getTargetTypeOptions(): array
    {
        return [
            'post'   => 'Сообщение',
            'thread' => 'Тема',
            'user'   => 'Игрок',
        ];
    }

    /**
     * Репорты, созданные пользователем (личный кабинет).
     *
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public function findByReporter(int $userId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $totalRow = $this->findOne(
            'SELECT COUNT(*) AS cnt FROM reports WHERE reporter_id = ?',
            [$userId]
        );
        $total = (int)($totalRow['cnt'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
            $offset = ($page - 1) * $perPage;
        }

        $items = $this->findMany(
            $this->selectWithUsers() . "
             WHERE r.reporter_id = ?
             ORDER BY FIELD(r.status, 'pending', 'needs_info', 'resolved', 'rejected'),
                      r.created_at DESC, r.id DESC
             LIMIT " . (int)$perPage . " OFFSET " . (int)$offset,
            [$userId]
        );
        foreach ($items as &$item) {
            $item = $this->enrich($item);
        }
        unset($item);

        return compact('items', 'total', 'page', 'pages');
    }

    /**
     * Репорт принадлежит репортёру?
     */
    public function belongsToReporter(int $reportId, int $userId): bool
    {
        $row = $this->findOne(
            'SELECT id FROM reports WHERE id = ? AND reporter_id = ? LIMIT 1',
            [$reportId, $userId]
        );
        return (bool)$row;
    }

}
