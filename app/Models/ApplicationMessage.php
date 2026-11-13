<?php
namespace App\Models;

use Core\Model;

class ApplicationMessage extends Model
{
    public function findByApplication(int $applicationId, bool $includeInternal = false): array
    {
        $sql = "SELECT m.*, u.username
                FROM application_messages m
                LEFT JOIN users u ON u.id = m.user_id
                WHERE m.application_id = ?";
        $params = [$applicationId];

        if (!$includeInternal) {
            $sql .= " AND m.is_internal = 0";
        }

        $sql .= " ORDER BY m.created_at ASC, m.id ASC";

        return $this->findMany($sql, $params);
    }

    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO application_messages
                (application_id, user_id, body, is_internal, kind)
             VALUES (?, ?, ?, ?, ?)",
            [
                (int)$data['application_id'],
                (int)$data['user_id'],
                trim($data['body'] ?? ''),
                !empty($data['is_internal']) ? 1 : 0,
                $data['kind'] ?? 'message',
            ]
        );

        return (int)$this->lastInsertId();
    }
}
