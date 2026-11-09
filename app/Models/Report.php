<?php
namespace App\Models;

use Core\Model;

/**
 * Модель репортов (жалоб на посты / темы / пользователей).
 */
class Report extends Model
{
    /**
     * Базовый SELECT с репортёром и модератором, который закрыл репорт.
     */
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
     * Список репортов с пагинацией и фильтрами.
     *
     * @param int   $page
     * @param int   $perPage
     * @param array $filters  q, status, target_type, date
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
                FIELD(r.status, 'pending', 'resolved', 'rejected'),
                r.created_at DESC,
                r.id DESC
             LIMIT " . (int)$perPage . " OFFSET " . (int)$offset,
            $params
        );

        // Дополняем человекочитаемыми полями
        foreach ($items as &$item) {
            $item = $this->enrich($item);
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ];
    }

    /**
     * Один репорт по ID.
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->findOne(
            $this->selectWithUsers() . ' WHERE r.id = ?',
            [(string)$id]
        );

        return $row ? $this->enrich($row) : null;
    }

    /**
     * Добавляет title / context для отображения в таблице.
     */
    private function enrich(array $row): array
    {
        $typeLabels = [
            'post'   => 'Сообщение',
            'thread' => 'Тема',
            'user'   => 'Пользователь',
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

        // Краткий контекст
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

        return $row;
    }

    /**
     * Опции статусов для фильтра.
     */
    public function getStatusOptions(): array
    {
        return [
            'pending'  => 'Ожидает',
            'resolved' => 'Решён',
            'rejected' => 'Отклонён',
        ];
    }

    /**
     * Опции типов цели.
     */
    public function getTargetTypeOptions(): array
    {
        return [
            'post'   => 'Сообщение',
            'thread' => 'Тема',
            'user'   => 'Пользователь',
        ];
    }
}