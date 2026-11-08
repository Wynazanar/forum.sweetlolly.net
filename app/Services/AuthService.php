<?php
namespace App\Services;

use App\Models\User;

class AuthService
{
    private static ?array $user = null;

    /**
     * Получить текущего пользователя (из сессии)
     */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $userModel = new User();
        self::$user = $userModel->findWithRole((int)$_SESSION['user_id']);

        return self::$user;
    }

    /**
     * Проверка, авторизован ли пользователь
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Проверка конкретного права
     */
    public static function can(string $permission): bool
    {
        $user = self::user();

        if (!$user) {
            return false;
        }

        $permissions = $user['permissions'] ?? [];

        // Если permissions пришли строкой JSON
        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true) ?: [];
        }

        return !empty($permissions[$permission]);
    }

    /**
     * Проверка роли
     */
    public static function hasRole(string $roleName): bool
    {
        $user = self::user();
        return $user && ($user['role_name'] ?? '') === $roleName;
    }

    /**
     * Войти под пользователем (для тестов / будущей авторизации)
     */
    public static function loginAs(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        self::$user = null; // сбрасываем кэш
    }

    /**
     * Выйти
     */
    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        self::$user = null;
    }
}