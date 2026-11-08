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

        if ($user && is_string($user['permissions'])) {
            $user['permissions'] = json_decode($user['permissions'], true) ?: [];
        }

        return $user;
    }
}