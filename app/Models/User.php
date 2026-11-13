<?php
namespace App\Models;

use Core\Model;

class User extends Model
{
    public function findByLogin(string $login): ?array
    {
        $login = mysqli_real_escape_string(self::$link, $login);

        $row = $this->findOne(
            "SELECT * FROM players 
             WHERE nickname = '$login' OR email = '$login' 
             LIMIT 1"
        );

        return $row ?: null;
    }

    public function findByNickname(string $nickname): ?array
    {
        $nickname = mysqli_real_escape_string(self::$link, $nickname);

        $row = $this->findOne(
            "SELECT id, nickname, email, created_at, updated_at 
            FROM players 
            WHERE nickname = '$nickname' 
            LIMIT 1"
        );

        return $row ?: null;
    }

    public function create(string $nickname, string $email, string $password): int
    {
        $hash     = password_hash($password, PASSWORD_DEFAULT);
        $nickname = mysqli_real_escape_string(self::$link, $nickname);
        $email    = mysqli_real_escape_string(self::$link, $email);
        $hash     = mysqli_real_escape_string(self::$link, $hash);

        mysqli_query(
            self::$link,
            "INSERT INTO players (nickname, email, password_hash) 
             VALUES ('$nickname', '$email', '$hash')"
        ) or die(mysqli_error(self::$link));

        return (int) mysqli_insert_id(self::$link);
    }

    public function exists(string $nickname, string $email): bool
    {
        $nickname = mysqli_real_escape_string(self::$link, $nickname);
        $email    = mysqli_real_escape_string(self::$link, $email);

        $row = $this->findOne(
            "SELECT id FROM players 
             WHERE nickname = '$nickname' OR email = '$email' 
             LIMIT 1"
        );

        return (bool) $row;
    }

    /**
     * Пользователь + роль + permissions
     */
    /**
     * Поиск для логина: username или email (+ hash и роль)
     */
    public function findForAuth(string $login): ?array
    {
        $user = $this->findOne(
            "SELECT 
                u.id,
                u.username,
                u.email,
                u.password_hash,
                u.role_id,
                u.is_banned,
                u.ban_reason,
                u.is_active,
                r.name AS role_name,
                r.permissions
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.username = ? OR u.email = ?
            LIMIT 1",
            [$login, $login]
        );

        if ($user && is_string($user['permissions'] ?? null)) {
            $user['permissions'] = json_decode($user['permissions'], true) ?: [];
        }

        return $user;
    }

    public function findWithRole(int $id): ?array
    {
        $user = $this->findOne(
            "SELECT 
                u.*,
                r.name AS role_name,
                r.permissions
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.id = ?
            LIMIT 1",
            [$id]
        );

        if ($user) {
            if (is_string($user['permissions'] ?? null)) {
                $user['permissions'] = json_decode($user['permissions'], true) ?: [];
            }
            unset($user['password_hash']);
        }

        return $user;
    }

    /**
     * Список для админки с пагинацией
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public function findAdminList(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = '(u.username LIKE ? OR u.email LIKE ? OR CAST(u.id AS CHAR) = ?)';
            $params[] = $q;
            $params[] = $q;
            $params[] = $filters['q'];
        }

        if (!empty($filters['role_id'])) {
            $where[] = 'u.role_id = ?';
            $params[] = (int)$filters['role_id'];
        }

        if (isset($filters['banned']) && $filters['banned'] !== '' && $filters['banned'] !== null) {
            if ($filters['banned'] === '1' || $filters['banned'] === 1) {
                $where[] = 'u.is_banned = 1';
            } elseif ($filters['banned'] === '0' || $filters['banned'] === 0) {
                $where[] = 'u.is_banned = 0';
            }
        }

        if (isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null) {
            if ($filters['active'] === '1' || $filters['active'] === 1) {
                $where[] = 'COALESCE(u.is_active, 1) = 1';
            } elseif ($filters['active'] === '0' || $filters['active'] === 0) {
                $where[] = 'u.is_active = 0';
            }
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countRow = $this->findOne(
            "SELECT COUNT(*) AS cnt
             FROM users u
             $whereSql",
            $params
        );
        $total = (int)($countRow['cnt'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));

        $items = $this->findMany(
            "SELECT
                u.id, u.username, u.email, u.avatar, u.role_id,
                u.posts_count, u.created_at, u.last_seen,
                u.is_banned, u.ban_reason, u.is_active,
                r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             $whereSql
             ORDER BY u.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ];
    }

    public function updateProfile(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        foreach (['username', 'email', 'avatar', 'role_id'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "$key = ?";
                $params[] = $data[$key];
            }
        }
        if (!$fields) {
            return false;
        }
        $params[] = $id;
        $this->execute(
            "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );
        return $this->affectedRows() >= 0;
    }

    public function setBanned(int $id, bool $banned, ?string $reason = null): bool
    {
        $this->execute(
            "UPDATE users SET is_banned = ?, ban_reason = ? WHERE id = ?",
            [$banned ? 1 : 0, $reason, $id]
        );
        return $this->affectedRows() >= 0;
    }

    public function createUser(string $username, string $email, string $passwordHash, int $roleId = 1): int
    {
        $this->execute(
            "INSERT INTO users (username, email, password_hash, role_id)
             VALUES (?, ?, ?, ?)",
            [$username, $email, $passwordHash, $roleId]
        );
        return (int)$this->lastInsertId();
    }

    public function setActive(int $id, bool $active): bool
    {
        $this->execute(
            "UPDATE users SET is_active = ? WHERE id = ?",
            [$active ? 1 : 0, $id]
        );
        return $this->affectedRows() >= 0;
    }

    public function touchLastSeen(int $id): bool
    {
        $this->execute(
            "UPDATE users SET last_seen = NOW() WHERE id = ?",
            [$id]
        );
        return $this->affectedRows() >= 0;
    }
}
