<?php
namespace App\Services;

use Core\Model;

/**
 * Иерархия ролей.
 *
 * Правила:
 * 1) Нельзя действовать на себя.
 * 2) Роль admin (по имени) может действовать на кого угодно (кроме себя).
 * 3) Не-admin НЕ может действовать на admin.
 * 4) Иначе: weight(actor) > weight(target).
 *
 * Если колонка weight ещё не проставлена (у всех 10) —
 * для admin-имени срабатывает правило 2.
 */
class RoleHierarchyService
{
    private const ADMIN_NAMES = [
        'admin', 'administrator', 'админ', 'администратор', 'root', 'owner',
    ];

    /** fallback, если weight одинаковый / не задан */
    private const DEFAULT_WEIGHTS = [
        'player'         => 10,
        'user'           => 10,
        'игрок'          => 10,
        'helper'         => 30,
        'хелпер'         => 30,
        'moderator'      => 50,
        'модер'          => 50,
        'модератор'      => 50,
        'admin'          => 100,
        'administrator'  => 100,
        'админ'          => 100,
        'администратор'  => 100,
        'root'           => 100,
        'owner'          => 100,
    ];

    /**
     * @return array{ok:bool, error?:string, actor_weight?:int, target_weight?:int}
     */
    public function canActOnUser(int $actorUserId, int $targetUserId): array
    {
        if ($actorUserId <= 0 || $targetUserId <= 0) {
            return ['ok' => false, 'error' => 'Некорректные пользователи'];
        }
        if ($actorUserId === $targetUserId) {
            return ['ok' => false, 'error' => 'Нельзя применить действие к самому себе'];
        }

        $actor  = $this->userRank($actorUserId);
        $target = $this->userRank($targetUserId);

        // Admin по имени — полный доступ к другим
        if ($this->isAdminName($actor['name'])) {
            return [
                'ok'            => true,
                'actor_weight'  => $actor['weight'],
                'target_weight' => $target['weight'],
            ];
        }

        // Не-admin не трогает admin
        if ($this->isAdminName($target['name'])) {
            return [
                'ok'            => false,
                'error'         => 'Нельзя действовать на администратора',
                'actor_weight'  => $actor['weight'],
                'target_weight' => $target['weight'],
            ];
        }

        if ($actor['weight'] <= $target['weight']) {
            return [
                'ok'            => false,
                'error'         => 'Нельзя действовать на пользователя с равной или более высокой ролью',
                'actor_weight'  => $actor['weight'],
                'target_weight' => $target['weight'],
            ];
        }

        return [
            'ok'            => true,
            'actor_weight'  => $actor['weight'],
            'target_weight' => $target['weight'],
        ];
    }

    /**
     * @return array{ok:bool, error?:string}
     */
    public function canAssignRole(int $actorUserId, int $roleId): array
    {
        $actor = $this->userRank($actorUserId);
        $role  = $this->roleRank($roleId);

        if ($this->isAdminName($actor['name'])) {
            return ['ok' => true];
        }

        if ($this->isAdminName($role['name'])) {
            return ['ok' => false, 'error' => 'Нельзя назначить роль администратора'];
        }

        if ($role['weight'] >= $actor['weight']) {
            return [
                'ok'    => false,
                'error' => 'Нельзя назначить роль того же или более высокого уровня, чем ваша',
            ];
        }
        return ['ok' => true];
    }

    public function weightByUserId(int $userId): int
    {
        return $this->userRank($userId)['weight'];
    }

    public function weightByRoleId(int $roleId): int
    {
        return $this->roleRank($roleId)['weight'];
    }

    private function isAdminName(?string $name): bool
    {
        $n = mb_strtolower(trim((string)$name));
        return in_array($n, self::ADMIN_NAMES, true);
    }

    /** @return array{weight:int, name:string} */
    private function userRank(int $userId): array
    {
        $model = new class extends Model {
            public function q(int $userId): ?array
            {
                try {
                    return $this->findOne(
                        "SELECT COALESCE(r.weight, 10) AS weight, r.name AS role_name
                         FROM users u
                         JOIN roles r ON r.id = u.role_id
                         WHERE u.id = ?
                         LIMIT 1",
                        [$userId]
                    );
                } catch (\Throwable $e) {
                    // нет колонки weight
                    return $this->findOne(
                        "SELECT 10 AS weight, r.name AS role_name
                         FROM users u
                         JOIN roles r ON r.id = u.role_id
                         WHERE u.id = ?
                         LIMIT 1",
                        [$userId]
                    );
                }
            }
        };

        $row = $model->q($userId);
        if (!$row) {
            return ['weight' => 10, 'name' => ''];
        }

        $name = (string)($row['role_name'] ?? '');
        $weight = (int)($row['weight'] ?? 10);
        $weight = $this->resolveWeight($name, $weight);

        return ['weight' => $weight, 'name' => $name];
    }

    /** @return array{weight:int, name:string} */
    private function roleRank(int $roleId): array
    {
        $model = new class extends Model {
            public function q(int $roleId): ?array
            {
                try {
                    return $this->findOne(
                        "SELECT COALESCE(weight, 10) AS weight, name FROM roles WHERE id = ? LIMIT 1",
                        [$roleId]
                    );
                } catch (\Throwable $e) {
                    return $this->findOne(
                        "SELECT 10 AS weight, name FROM roles WHERE id = ? LIMIT 1",
                        [$roleId]
                    );
                }
            }
        };

        $row = $model->q($roleId);
        if (!$row) {
            return ['weight' => 10, 'name' => ''];
        }

        $name = (string)($row['name'] ?? '');
        $weight = (int)($row['weight'] ?? 10);
        $weight = $this->resolveWeight($name, $weight);

        return ['weight' => $weight, 'name' => $name];
    }

    /**
     * Если в БД у всех weight=10 — подставляем типовой вес по имени роли.
     */
    private function resolveWeight(string $name, int $dbWeight): int
    {
        $key = mb_strtolower(trim($name));
        $fallback = self::DEFAULT_WEIGHTS[$key] ?? null;

        // Явно выставленный weight > 10 оставляем
        if ($dbWeight > 10) {
            return $dbWeight;
        }
        // admin в БД с weight 10 → 100
        if ($fallback !== null) {
            return $fallback;
        }
        return $dbWeight;
    }
}
