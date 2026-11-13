<?php
use App\Components\AdminTable;
use App\Models\Role;

$players     = $players ?? [];
$selected    = $selected ?? null;
$filters     = $filters ?? [];
$total       = (int)($total ?? 0);
$page        = (int)($page ?? 1);
$pages       = (int)($pages ?? 1);
$roleOptions = $roleOptions ?? [];
$roleModel   = new Role();

$auth = \App\Services\AuthService::class;
$canCreate     = $auth::can('players.create') || $auth::hasRole('admin');
$canEdit       = $auth::can('players.edit') || $auth::hasRole('admin');
$canEditRole   = $auth::can('players.edit_role') || $auth::hasRole('admin');
$canDeactivate = $auth::can('players.deactivate') || $auth::hasRole('admin');
$canBan        = $auth::can('players.ban') || $auth::hasRole('admin');
$canMute       = $auth::can('players.mute') || $auth::hasRole('admin');
$canRestrict   = $auth::can('players.restrict') || $auth::hasRole('admin');
$canPunish     = $canBan || $canMute || $canRestrict;

$columns = [
    [
        'key'    => 'username',
        'label'  => 'Игрок',
        'render' => function (array $row) use ($roleModel): string {
            $name = htmlspecialchars($row['username'] ?? '');
            $id   = (int)($row['id'] ?? 0);
            $av   = $row['avatar'] ?? null;
            $letter = htmlspecialchars(mb_strtoupper(mb_substr($row['username'] ?? '?', 0, 1)));
            $avatarHtml = $av
                ? '<img src="' . htmlspecialchars(url(ltrim((string)$av, '/'))) . '" alt="" class="player-av">'
                : '<span class="player-av player-av--ph">' . $letter . '</span>';
            return $avatarHtml
                . '<div><strong>' . $name . '</strong>'
                . '<div class="text-muted" style="font-size:12px;">ID ' . $id . '</div></div>';
        },
    ],
    [
        'key'    => 'role_name',
        'label'  => 'Роль',
        'render' => fn(array $row): string => htmlspecialchars($roleModel->label($row['role_name'] ?? '')),
    ],
    [
        'key'   => 'created_at',
        'label' => 'Регистрация',
        'type'  => 'date',
    ],
    [
        'key'    => 'last_seen',
        'label'  => 'Был',
        'render' => function (array $row): string {
            $ls = $row['last_seen'] ?? null;
            if (!$ls) return '—';
            return htmlspecialchars(date('d.m.y H:i', strtotime($ls)));
        },
    ],
    [
        'key'    => 'sanctions',
        'label'  => 'Санкции',
        'render' => function (array $row): string {
            $bits = [];
            if (isset($row['is_active']) && (int)$row['is_active'] === 0) {
                $bits[] = '<span class="badge badge-secondary">Выкл</span>';
            }
            if (!empty($row['is_banned'])) {
                $bits[] = '<span class="badge badge-danger">Бан</span>';
            }
            return $bits ? implode(' ', $bits) : '<span class="text-muted">Нет</span>';
        },
    ],
];

$filterConfig = [
    ['name' => 'q', 'type' => 'search', 'placeholder' => 'Никнейм, email или ID'],
    [
        'name'    => 'role_id',
        'type'    => 'select',
        'empty'   => 'Все роли',
        'options' => $roleOptions,
    ],
    [
        'name'    => 'banned',
        'type'    => 'select',
        'empty'   => 'Бан',
        'options' => ['0' => 'Без бана', '1' => 'Забанены'],
    ],
    [
        'name'    => 'active',
        'type'    => 'select',
        'empty'   => 'Активность',
        'options' => ['1' => 'Активные', '0' => 'Деактивированные'],
    ],
];

$table = AdminTable::make(
    items: $players,
    columns: $columns,
    selected: $selected,
    baseUrl: '/moderation/players',
    page: $page,
    total: $total,
    pages: $pages,
    filters: $filters,
    filterConfig: $filterConfig,
    emptyText: 'Игроки не найдены',
    idKey: 'id',
);
?>
<style>
.player-av {
    width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
    display: inline-flex; align-items: center; justify-content: center;
    margin-right: 10px; vertical-align: middle;
}
.player-av--ph { background: #7c5cfc; color: #fff; font-weight: 700; font-size: 14px; }
.players-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start; }
@media (max-width: 960px) { .players-layout { grid-template-columns: 1fr; } }
.player-card .meta-row { display: flex; justify-content: space-between; gap: 8px; margin: 6px 0; font-size: 13px; }
.player-card .meta-row span:first-child { color: var(--muted, #999); }
.sanction-item { font-size: 13px; padding: 8px; border-radius: 8px; background: rgba(0,0,0,.12); margin-bottom: 6px; }
</style>

<main>
<div class="container">
    <div class="page-header flex">
        <div>
            <h2>Игроки</h2>
            <p>Учётные записи форума, роли и ограничения.</p>
        </div>
        <?php if ($canCreate): ?>
            <a href="<?= url('/moderation/players/create') ?>" class="btn btn-primary">＋ Создать</a>
        <?php endif; ?>
    </div>

    <?= $table->filters() ?>

    <div class="players-layout">
        <div class="logs-table-card">
            <div class="logs-table-header">
                <h3>Игроки форума</h3>
                <span class="logs-count"><?= (int)$total ?> аккаунтов</span>
            </div>
            <?= $table->render() ?>
        </div>

        <aside class="logs-table-card player-card" style="padding:16px;">
            <?php if (!$selected): ?>
                <p class="text-muted">Выберите игрока в таблице</p>
            <?php else:
                $s = $selected;
                $roleLabel = $roleModel->label($s['role_name'] ?? '');
            ?>
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;">
                    <?php
                    $letter = htmlspecialchars(mb_strtoupper(mb_substr($s['username'] ?? '?', 0, 1)));
                    if (!empty($s['avatar'])): ?>
                        <img class="player-av" src="<?= htmlspecialchars(url(ltrim($s['avatar'], '/'))) ?>" alt="">
                    <?php else: ?>
                        <span class="player-av player-av--ph"><?= $letter ?></span>
                    <?php endif; ?>
                    <div>
                        <strong><?= htmlspecialchars($s['username'] ?? '') ?></strong>
                        <div class="text-muted" style="font-size:12px;">ID <?= (int)$s['id'] ?></div>
                    </div>
                </div>

                <div class="meta-row"><span>Роль</span><span><?= htmlspecialchars($roleLabel) ?></span></div>
                <div class="meta-row"><span>Email</span><span><?= htmlspecialchars($s['email'] ?? '') ?></span></div>
                <div class="meta-row"><span>Регистрация</span><span><?= htmlspecialchars($s['created_at'] ?? '') ?></span></div>
                <div class="meta-row"><span>Последний вход</span><span><?= htmlspecialchars($s['last_seen'] ?? '—') ?></span></div>
                <div class="meta-row"><span>Посты</span><span><?= (int)($s['posts_count'] ?? 0) ?></span></div>
                <div class="meta-row">
                    <span>Санкции</span>
                    <span><?= !empty($s['is_banned']) ? 'Бан' : (empty($s['sanctions']) ? 'Нет активных' : count($s['sanctions'])) ?></span>
                </div>

                <?php if (!empty($s['sanctions'])): ?>
                    <h4 style="margin:14px 0 8px;font-size:13px;">Активные ограничения</h4>
                    <?php foreach ($s['sanctions'] as $san): ?>
                        <div class="sanction-item">
                            <strong><?= htmlspecialchars($san['type'] ?? 'ban') ?></strong>
                            — <?= htmlspecialchars($san['reason'] ?? '') ?>
                            <div class="text-muted" style="font-size:11px;">
                                до <?= htmlspecialchars($san['expires_at'] ?? 'бессрочно') ?>
                                · <?= htmlspecialchars($san['banned_by_name'] ?? '—') ?>
                            </div>
                            <?php if ($canPunish): ?>
                            <form method="post" action="<?= url('/moderation/players/revoke/' . (int)$s['id']) ?>" style="margin-top:6px;">
                                <input type="hidden" name="type" value="<?= htmlspecialchars($san['type'] ?? 'ban') ?>">
                                <button type="submit" class="btn btn-outline btn-sm"
                                        onclick="return confirm('Снять ограничение?')">Снять</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px;">
                    <?php if ($canEdit || $canEditRole): ?>
                        <a class="btn btn-outline" href="<?= url('/moderation/players/edit/' . (int)$s['id']) ?>">Изменить</a>
                    <?php endif; ?>

                    <?php if ($canDeactivate): ?>
                        <?php $isActive = !isset($s['is_active']) || (int)$s['is_active'] === 1; ?>
                        <form method="post" action="<?= url('/moderation/players/deactivate/' . (int)$s['id']) ?>">
                            <?php if ($isActive): ?>
                                <input type="hidden" name="active" value="0">
                                <button type="submit" class="btn btn-outline btn-danger" style="width:100%;"
                                        onclick="return confirm('Деактивировать аккаунт? Войти будет нельзя.')">Деактивировать</button>
                            <?php else: ?>
                                <input type="hidden" name="active" value="1">
                                <button type="submit" class="btn btn-outline" style="width:100%;"
                                        onclick="return confirm('Снова активировать аккаунт?')">Активировать</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>

                    <?php if ($canPunish): ?>
                        <details>
                            <summary class="btn btn-outline" style="cursor:pointer;">Выдать наказание</summary>
                            <form method="post" action="<?= url('/moderation/players/punish/' . (int)$s['id']) ?>" style="margin-top:10px;">
                                <div class="form-group">
                                    <label>Тип</label>
                                    <select name="type">
                                        <?php if ($canBan): ?><option value="ban">Бан</option><?php endif; ?>
                                        <?php if ($canMute): ?><option value="mute">Мут</option><?php endif; ?>
                                        <?php if ($canRestrict): ?><option value="restrict">Ограничение</option><?php endif; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Причина *</label>
                                    <input type="text" name="reason" required placeholder="Причина">
                                </div>
                                <div class="form-group">
                                    <label>До (необяз.)</label>
                                    <input type="datetime-local" name="expires_at">
                                </div>
                                <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;"
                                        onclick="return confirm('Выдать наказание?')">Применить</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>
</main>
