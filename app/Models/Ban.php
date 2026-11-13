<?php
namespace App\Models;

use Core\Model;

class Ban extends Model
{
    /**
     * type: ban | mute | restrict
     */
    public function findActiveByUser(int $userId): array
    {
        return $this->findMany(
            "SELECT b.*, u.username AS banned_by_name
             FROM bans b
             LEFT JOIN users u ON u.id = b.banned_by
             WHERE b.user_id = ? AND b.is_active = 1
               AND (b.expires_at IS NULL OR b.expires_at > NOW())
             ORDER BY b.created_at DESC",
            [$userId]
        );
    }

    public function findHistoryByUser(int $userId, int $limit = 50): array
    {
        return $this->findMany(
            "SELECT b.*, u.username AS banned_by_name
             FROM bans b
             LEFT JOIN users u ON u.id = b.banned_by
             WHERE b.user_id = ?
             ORDER BY b.created_at DESC
             LIMIT " . (int)$limit,
            [$userId]
        );
    }

    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO bans (user_id, banned_by, reason, expires_at, is_active, type)
             VALUES (?, ?, ?, ?, 1, ?)",
            [
                (int)$data['user_id'],
                $data['banned_by'] ?? null,
                $data['reason'] ?? '',
                $data['expires_at'] ?? null,
                $data['type'] ?? 'ban',
            ]
        );
        return (int)$this->lastInsertId();
    }

    public function deactivate(int $id): bool
    {
        $this->execute("UPDATE bans SET is_active = 0 WHERE id = ?", [$id]);
        return $this->affectedRows() >= 0;
    }

    public function deactivateActiveByUser(int $userId, ?string $type = null): bool
    {
        if ($type) {
            $this->execute(
                "UPDATE bans SET is_active = 0 WHERE user_id = ? AND is_active = 1 AND type = ?",
                [$userId, $type]
            );
        } else {
            $this->execute(
                "UPDATE bans SET is_active = 0 WHERE user_id = ? AND is_active = 1",
                [$userId]
            );
        }
        return true;
    }

    /**
     * Снять просроченные наказания пользователя (is_active = 0)
     */
    public function expireOverdueForUser(int $userId): int
    {
        $this->execute(
            "UPDATE bans
             SET is_active = 0
             WHERE user_id = ?
               AND is_active = 1
               AND expires_at IS NOT NULL
               AND expires_at <= NOW()",
            [$userId]
        );
        return (int)$this->affectedRows();
    }

    /**
     * Есть ли активный бан (type=ban)
     */
    public function findActiveBan(int $userId): ?array
    {
        return $this->findOne(
            "SELECT b.*, u.username AS banned_by_name
             FROM bans b
             LEFT JOIN users u ON u.id = b.banned_by
             WHERE b.user_id = ?
               AND b.is_active = 1
               AND b.type = 'ban'
               AND (b.expires_at IS NULL OR b.expires_at > NOW())
             ORDER BY b.created_at DESC
             LIMIT 1",
            [$userId]
        );
    }
}
