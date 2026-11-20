<?php
namespace App\Models;

use Core\Model;

/**
 * Сообщения в диалоге по репорту (уточнения / ответы).
 */
class ReportMessage extends Model
{
    public function findByReport(int $reportId, bool $includeInternal = true): array
    {
        $sql = "SELECT m.*, u.username
                FROM report_messages m
                LEFT JOIN users u ON u.id = m.user_id
                WHERE m.report_id = ?";
        $params = [$reportId];

        if (!$includeInternal) {
            $sql .= " AND m.is_internal = 0";
        }

        $sql .= " ORDER BY m.created_at ASC, m.id ASC";

        try {
            return $this->findMany($sql, $params);
        } catch (\Throwable $e) {
            // таблица ещё не создана
            return [];
        }
    }

    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO report_messages
                (report_id, user_id, body, is_internal, kind)
             VALUES (?, ?, ?, ?, ?)",
            [
                (int)$data['report_id'],
                (int)$data['user_id'],
                trim($data['body'] ?? ''),
                !empty($data['is_internal']) ? 1 : 0,
                $data['kind'] ?? 'message',
            ]
        );

        return (int)$this->lastInsertId();
    }
}
