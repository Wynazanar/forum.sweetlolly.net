<?php
namespace App\Services;

use App\Models\User;
use App\Models\Ban;
use App\Models\Role;
use App\Services\AuthService;
use App\Services\ActivityLogService;

class PlayerService
{
    private User $users;
    private Ban $bans;
    private Role $roles;

    public function __construct()
    {
        $this->users = new User();
        $this->bans  = new Ban();
        $this->roles = new Role();
    }

    /**
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->users->findAdminList($filters, $page, $perPage);
    }

    public function find(int $id): ?array
    {
        $user = $this->users->findWithRole($id);
        if (!$user) {
            return null;
        }
        $user['sanctions'] = $this->bans->findActiveByUser($id);
        $user['sanction_history'] = $this->bans->findHistoryByUser($id);
        return $user;
    }

    public function roleOptions(): array
    {
        return $this->roles->getOptions();
    }

    /**
     * @return array{ok: bool, id?: int, errors?: array}
     */
    public function create(array $input): array
    {
        $username = trim($input['username'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $roleId = (int)($input['role_id'] ?? 1);

        $errors = [];
        if ($username === '' || mb_strlen($username) < 2) {
            $errors['username'] = 'Никнейм обязателен';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }
        if (strlen($password) < 6) {
            $errors['password'] = 'Пароль не короче 6 символов';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $id = $this->users->createUser(
            $username,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $roleId
        );

        $actor = AuthService::user()['id'] ?? null;
        if ($actor) {
            ActivityLogService::log(
                (int)$actor,
                'created_player',
                'success',
                'user',
                $id,
                $username
            );
        }

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @return array{ok: bool, errors?: array}
     */
    public function update(int $id, array $input): array
    {
        $user = $this->users->findWithRole($id);
        if (!$user) {
            return ['ok' => false, 'errors' => ['id' => 'Игрок не найден']];
        }

        $data = [];
        if (isset($input['username'])) {
            $data['username'] = trim($input['username']);
        }
        if (isset($input['email'])) {
            $data['email'] = trim($input['email']);
        }
        if (isset($input['role_id'])) {
            $data['role_id'] = (int)$input['role_id'];
        }
        if (array_key_exists('avatar', $input)) {
            $data['avatar'] = $input['avatar'] !== '' ? $input['avatar'] : null;
        }

        $this->users->updateProfile($id, $data);

        $actor = AuthService::user()['id'] ?? null;
        if ($actor) {
            ActivityLogService::log(
                (int)$actor,
                'updated_player',
                'success',
                'user',
                $id,
                $data['username'] ?? $user['username'],
                ['before' => ['role_id' => $user['role_id']], 'after' => $data]
            );
        }

        return ['ok' => true];
    }

    /**
     * Выдать наказание: ban | mute | restrict
     */
    public function punish(int $userId, string $type, string $reason, ?string $expiresAt, int $actorId): array
    {
        $user = $this->users->findWithRole($userId);
        if (!$user) {
            return ['ok' => false, 'errors' => ['id' => 'Игрок не найден']];
        }

        if (!in_array($type, ['ban', 'mute', 'restrict'], true)) {
            return ['ok' => false, 'errors' => ['type' => 'Неизвестный тип']];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'errors' => ['reason' => 'Укажите причину']];
        }

        // Снять предыдущие активного того же типа
        $this->bans->deactivateActiveByUser($userId, $type);

        $this->bans->create([
            'user_id'    => $userId,
            'banned_by'  => $actorId,
            'reason'     => $reason,
            'expires_at' => $expiresAt ?: null,
            'type'       => $type,
        ]);

        if ($type === 'ban') {
            $this->users->setBanned($userId, true, $reason);
            ActivityLogService::userBanned($actorId, $userId, $user['username'], $reason, $expiresAt ? ('до ' . $expiresAt) : null);
        } else {
            ActivityLogService::log(
                $actorId,
                $type === 'mute' ? 'muted_user' : 'restricted_user',
                'success',
                'user',
                $userId,
                $user['username'],
                ['type' => $type, 'expires_at' => $expiresAt],
                $reason
            );
        }

        return ['ok' => true];
    }

    public function revokeSanction(int $userId, string $type, int $actorId): array
    {
        $user = $this->users->findWithRole($userId);
        if (!$user) {
            return ['ok' => false, 'errors' => ['id' => 'Игрок не найден']];
        }

        $this->bans->deactivateActiveByUser($userId, $type);

        if ($type === 'ban') {
            $this->users->setBanned($userId, false, null);
            ActivityLogService::userUnbanned($actorId, $userId, $user['username']);
        } else {
            ActivityLogService::log(
                $actorId,
                $type === 'mute' ? 'unmuted_user' : 'unrestricted_user',
                'success',
                'user',
                $userId,
                $user['username']
            );
        }

        return ['ok' => true];
    }

    /**
     * Деактивировать / активировать (вместо удаления)
     */
    public function setActive(int $userId, bool $active, int $actorId): array
    {
        $user = $this->users->findWithRole($userId);
        if (!$user) {
            return ['ok' => false, 'errors' => ['id' => 'Игрок не найден']];
        }

        // Нельзя деактивировать самого себя
        if (!$active && $userId === $actorId) {
            return ['ok' => false, 'errors' => ['id' => 'Нельзя деактивировать себя']];
        }

        $this->users->setActive($userId, $active);

        ActivityLogService::log(
            $actorId,
            $active ? 'activated_player' : 'deactivated_player',
            'success',
            'user',
            $userId,
            $user['username'] ?? ('#' . $userId)
        );

        return ['ok' => true];
    }
}
