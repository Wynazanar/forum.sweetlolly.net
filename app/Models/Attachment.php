<?php
namespace App\Models;

use Core\Model;

class Attachment extends Model
{
    public function findById(int $id): ?array
    {
        return $this->findOne("SELECT * FROM attachments WHERE id = ?", [$id]);
    }

    public function findByTarget(string $type, int $targetId): array
    {
        return $this->findMany(
            "SELECT * FROM attachments
             WHERE target_type = ? AND target_id = ?
             ORDER BY id ASC",
            [$type, $targetId]
        );
    }

    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO attachments
                (target_type, target_id, field_key, user_id, original_name, stored_name, path, size, mime_type, meta)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['target_type'],
                (int)$data['target_id'],
                $data['field_key'] ?? null,
                (int)$data['user_id'],
                $data['original_name'],
                $data['stored_name'],
                $data['path'],
                (int)$data['size'],
                $data['mime_type'],
                isset($data['meta']) ? json_encode($data['meta'], JSON_UNESCAPED_UNICODE) : null,
            ]
        );
        return (int)$this->lastInsertId();
    }

    public function deleteById(int $id): bool
    {
        $this->execute("DELETE FROM attachments WHERE id = ?", [$id]);
        return $this->affectedRows() >= 0;
    }
}
