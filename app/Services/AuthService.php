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
    /** Имена ролей, которые считаются администраторами (полный доступ). */
    private static array $adminRoleNames = [
        'admin', 'administrator', 'админ', 'администратор', 'root', 'owner',
    ];

    public static function isAdmin(): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        $name = mb_strtolower(trim((string)($user['role_name'] ?? '')));
        return in_array($name, self::$adminRoleNames, true);
    }

    public static function can(string $permission): bool
    {
        $user = self::user();

        if (!$user) {
            return false;
        }

        // Роль администратора (admin / администратор / root / owner и т.д.) — полный доступ.
        if (self::isAdmin()) {
            return true;
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
            // players.* — только явные права или legacy ban_user; create НЕ наследуется от manage_roles
            'players.view'                => ['ban_user'],
            'players.create'              => [],
            'players.edit'                => ['ban_user'],
            'players.edit_role'           => ['manage_roles'],
            'players.deactivate'          => ['ban_user'],
            'players.ban'                 => ['ban_user'],
            'players.mute'                => ['ban_user'],
            'players.restrict'            => ['ban_user'],
            // threads.* — без «lock_thread открывает pin/move/edit/hide»
            'threads.view_mod'            => ['lock_thread', 'move_thread', 'delete_thread'],
            'discussions.view_drafts'     => [],
            'threads.publish'             => [],
            'threads.moderate'            => [],
            'threads.pin'                 => [],
            'threads.lock'                => ['lock_thread'],
            'threads.move'                => ['move_thread'],
            'threads.hide'                => ['delete_thread'],
            'threads.edit_any'            => ['edit_any_post'],
            'discussions.edit_any'        => ['edit_any_post'],
            'discussions.reply'           => ['create_post'],
            'posts.attach'                => ['create_post', 'create_thread'],
        ];
        foreach ($legacy[$permission] ?? [] as $oldKey) {
            if (!empty($permissions[$oldKey])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Права, которые открывают хотя бы один раздел модерации.
     * Единый список для Header, guard и редиректов.
     */
    public static function moderationPermissions(): array
    {
        return [
            // вкладки
            'players.view',
            'manage_roles',
            'resolve_report',
            'applications.view',
            'manage_categories',
            'threads.view_mod',
            'discussions.view_mod',
            'view_logs',
            // legacy-ключи, которые всё ещё дают доступ к табам
            'lock_thread',
            'move_thread',
            'delete_thread',
            'ban_user',
        ];
    }

    /**
     * Есть ли доступ в админ-панель (хотя бы просмотр одного раздела).
     */
    public static function canAccessModeration(): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::isAdmin()) {
            return true;
        }
        foreach (self::moderationPermissions() as $perm) {
            if (self::can($perm)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Проверка роли (точное совпадение или админ-синоним)
     */
    public static function hasRole(string $roleName): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        $current = mb_strtolower(trim((string)($user['role_name'] ?? '')));
        $wanted  = mb_strtolower(trim($roleName));
        if ($current === $wanted) {
            return true;
        }
        // Если спрашивают 'admin' — принимаем любые админ-синонимы
        if ($wanted === 'admin' && in_array($current, self::$adminRoleNames, true)) {
            return true;
        }
        return false;
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
