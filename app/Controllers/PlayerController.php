<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\PlayerService;

class PlayerController extends Controller
{
    private PlayerService $service;

    public function __construct()
    {
        $this->service = new PlayerService();
    }

    public function create(array $params = [])
    {
        $this->requirePerm('players.create');
        $this->title = 'Создать игрока | Модерация';
        return $this->render('moderation/player_form', [
            'mode'        => 'create',
            'item'        => [],
            'roleOptions' => $this->service->roleOptions(),
            'errors'      => [],
            'canEditRole' => AuthService::can('players.edit_role') || AuthService::hasRole('admin'),
        ]);
    }

    public function store(array $params = [])
    {
        $this->requirePerm('players.create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('/moderation/players'));
            exit;
        }
        $input = $_POST;
        if (!AuthService::can('players.edit_role') && !AuthService::hasRole('admin')) {
            $input['role_id'] = 1; // только игрок
        }
        $result = $this->service->create($input);
        if (!$result['ok']) {
            $this->title = 'Создать игрока | Модерация';
            return $this->render('moderation/player_form', [
                'mode'        => 'create',
                'item'        => $input,
                'roleOptions' => $this->service->roleOptions(),
                'errors'      => $result['errors'] ?? [],
                'canEditRole' => AuthService::can('players.edit_role') || AuthService::hasRole('admin'),
            ]);
        }
        header('Location: ' . url('/moderation/players') . '?id=' . (int)$result['id']);
        exit;
    }

    public function edit(array $params = [])
    {
        if (!AuthService::can('players.edit') && !AuthService::can('players.edit_role') && !AuthService::hasRole('admin')) {
            $this->deny();
        }
        $id = (int)($params['id'] ?? 0);
        $item = $this->service->find($id);
        if (!$item) {
            header('Location: ' . url('/moderation/players'));
            exit;
        }
        $this->title = 'Игрок ' . ($item['username'] ?? '') . ' | Модерация';
        return $this->render('moderation/player_form', [
            'mode'        => 'edit',
            'item'        => $item,
            'roleOptions' => $this->service->roleOptions(),
            'errors'      => [],
            'canEditRole' => AuthService::can('players.edit_role') || AuthService::hasRole('admin'),
            'canEdit'     => AuthService::can('players.edit') || AuthService::hasRole('admin'),
        ]);
    }

    public function update(array $params = [])
    {
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/players'));
            exit;
        }

        $canEdit = AuthService::can('players.edit') || AuthService::hasRole('admin');
        $canRole = AuthService::can('players.edit_role') || AuthService::hasRole('admin');
        if (!$canEdit && !$canRole) {
            $this->deny();
        }

        $input = [];
        if ($canEdit) {
            $input['username'] = $_POST['username'] ?? null;
            $input['email'] = $_POST['email'] ?? null;
        }
        if ($canRole && isset($_POST['role_id'])) {
            $input['role_id'] = (int)$_POST['role_id'];
        }

        $result = $this->service->update($id, $input);
        if (!$result['ok']) {
            $item = $this->service->find($id) ?? $_POST;
            $this->title = 'Игрок | Модерация';
            return $this->render('moderation/player_form', [
                'mode'        => 'edit',
                'item'        => $item,
                'roleOptions' => $this->service->roleOptions(),
                'errors'      => $result['errors'] ?? [],
                'canEditRole' => $canRole,
                'canEdit'     => $canEdit,
            ]);
        }
        header('Location: ' . url('/moderation/players') . '?id=' . $id);
        exit;
    }

    public function punish(array $params = [])
    {
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/players'));
            exit;
        }

        $type = $_POST['type'] ?? 'ban';
        $perm = match ($type) {
            'mute' => 'players.mute',
            'restrict' => 'players.restrict',
            default => 'players.ban',
        };
        $this->requirePerm($perm);

        $actor = AuthService::user();
        $reason = $_POST['reason'] ?? '';
        $expires = trim($_POST['expires_at'] ?? '') ?: null;
        // datetime-local → SQL
        if ($expires && str_contains($expires, 'T')) {
            $expires = str_replace('T', ' ', $expires) . ':00';
            $expires = substr($expires, 0, 19);
        }

        $this->service->punish($id, $type, $reason, $expires, (int)$actor['id']);
        header('Location: ' . url('/moderation/players') . '?id=' . $id);
        exit;
    }

    public function revoke(array $params = [])
    {
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/players'));
            exit;
        }

        $type = $_POST['type'] ?? 'ban';
        $perm = match ($type) {
            'mute' => 'players.mute',
            'restrict' => 'players.restrict',
            default => 'players.ban',
        };
        $this->requirePerm($perm);

        $actor = AuthService::user();
        $this->service->revokeSanction($id, $type, (int)$actor['id']);
        header('Location: ' . url('/moderation/players') . '?id=' . $id);
        exit;
    }

    public function deactivate(array $params = [])
    {
        $this->requirePerm('players.deactivate');
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/players'));
            exit;
        }
        $actor = AuthService::user();
        $active = !empty($_POST['active']);
        $this->service->setActive($id, $active, (int)$actor['id']);
        header('Location: ' . url('/moderation/players') . '?id=' . $id);
        exit;
    }

    private function requirePerm(string $perm): void
    {
        if (!AuthService::check() || (!AuthService::can($perm) && !AuthService::hasRole('admin'))) {
            $this->deny();
        }
    }

    private function deny(): void
    {
        header('Location: ' . url('/'));
        exit;
    }
}
