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

        if (!empty($permissions[$permission])) {
            return true;
        }

        // Совместимость со старыми ключами, пока роли не обновлены в UI
        $legacy = [
            'applications.view'           => ['resolve_report'],
            'applications.review'         => ['resolve_report'],
            'applications.forms.manage'   => ['resolve_report', 'manage_categories'],
            'applications.layouts.manage' => ['resolve_report', 'manage_categories'],
            'players.view'                => ['ban_user'],
            'players.edit_role'           => ['ban_user', 'manage_roles'],
        ];
        foreach ($legacy[$permission] ?? [] as $oldKey) {
            if (!empty($permissions[$oldKey])) {
                return true;
            }
        }

        return false;
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
     * Вход по логину (username/email) и паролю.
     * @return array{ok: bool, error?: string}
     */
    public static function attempt(string $login, string $password): array
    {
        $login = trim($login);
        if ($login === '' || $password === '') {
            return ['ok' => false, 'error' => 'Введите логин и пароль'];
        }

        $userModel = new User();
        $row = $userModel->findForAuth($login);

        if (!$row || empty($row['password_hash'])) {
            return ['ok' => false, 'error' => 'Неверный логин или пароль'];
        }

        if (!password_verify($password, $row['password_hash'])) {
            return ['ok' => false, 'error' => 'Неверный логин или пароль'];
        }

        if (!empty($row['is_banned'])) {
            $reason = $row['ban_reason'] ?? '';
            return [
                'ok' => false,
                'error' => 'Аккаунт заблокирован' . ($reason !== '' ? ': ' . $reason : ''),
            ];
        }

        self::login((int)$row['id']);
        return ['ok' => true];
    }

    /**
     * Установить сессию по id
     */
    public static function login(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        self::$user = null;
    }

    /**
     * Войти под пользователем (только для локальных тестов)
     */
    public static function loginAs(int $userId): void
    {
        self::login($userId);
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