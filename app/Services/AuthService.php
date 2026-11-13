<?php
namespace App\Services;

use App\Models\User;
use App\Models\Ban;

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
        $uid = (int)$_SESSION['user_id'];
        self::$user = $userModel->findWithRole($uid);

        // last_seen: не чаще раза в 5 минут (не UPDATE на каждый хит)
        if (self::$user) {
            self::maybeTouchLastSeen($uid, self::$user['last_seen'] ?? null);
        }

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
            'players.view'                => ['ban_user', 'players.edit_role'],
            'players.create'              => ['players.edit_role', 'manage_roles'],
            'players.edit'                => ['players.edit_role', 'ban_user'],
            'players.edit_role'           => ['manage_roles', 'ban_user'],
            'players.deactivate'          => ['ban_user', 'manage_roles'],
            'players.ban'                 => ['ban_user'],
            'players.mute'                => ['ban_user'],
            'players.restrict'            => ['ban_user'],
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

        $userId = (int)$row['id'];

        // Просроченные наказания снимаем в момент логина (только этот user_id)
        self::syncExpiredSanctions($userId);

        // is_active — деактивация админом
        if (array_key_exists('is_active', $row) && (int)$row['is_active'] === 0) {
            return ['ok' => false, 'error' => 'Аккаунт деактивирован'];
        }

        // Актуальный бан (после sync)
        $ban = (new Ban())->findActiveBan($userId);
        if ($ban) {
            $msg = 'Аккаунт заблокирован';
            if (!empty($ban['reason'])) {
                $msg .= ': ' . $ban['reason'];
            }
            if (!empty($ban['expires_at'])) {
                $msg .= '. До: ' . $ban['expires_at'];
            } else {
                $msg .= ' (бессрочно)';
            }
            // подстрахуем флаг
            (new User())->setBanned($userId, true, $ban['reason'] ?? null);
            return ['ok' => false, 'error' => $msg];
        }

        // Бана нет — сбросить устаревший is_banned, если висел
        if (!empty($row['is_banned'])) {
            (new User())->setBanned($userId, false, null);
        }

        self::login($userId);
        return ['ok' => true];
    }

    /**
     * Установить сессию по id
     */
    public static function login(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        self::$user = null;
        (new User())->touchLastSeen($userId);
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

    /**
     * Снять просроченные sanctions у одного пользователя + синхронизировать is_banned
     */
    public static function syncExpiredSanctions(int $userId): void
    {
        (new Ban())->expireOverdueForUser($userId);
    }

    /**
     * Обновить last_seen, если прошло ≥ 5 минут с последнего значения
     */
    private static function maybeTouchLastSeen(int $userId, ?string $lastSeen): void
    {
        $need = true;
        if ($lastSeen) {
            $ts = strtotime($lastSeen);
            if ($ts !== false && (time() - $ts) < 300) {
                $need = false;
            }
        }
        if (!$need) {
            return;
        }
        (new User())->touchLastSeen($userId);
        if (self::$user !== null) {
            self::$user['last_seen'] = date('Y-m-d H:i:s');
        }
    }
}
