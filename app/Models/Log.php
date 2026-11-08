<?php
namespace App\Models;

use Core\Model;

class Log extends Model
{
    /**
     * Список логов с пагинацией
     */
    public function getList(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $items = $this->findMany(
            "SELECT 
                al.*,
                u.username AS admin_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );

        $totalRow = $this->findOne("SELECT COUNT(*) AS cnt FROM activity_logs");
        $total = (int)($totalRow['cnt'] ?? 0);

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Один лог по ID (для правой панели)
     */
    public function findById(int $id): ?array
    {
        return $this->findOne(
            "SELECT 
                al.*,
                u.username AS admin_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.id = ?
             LIMIT 1",
            [$id]
        );
    }
}