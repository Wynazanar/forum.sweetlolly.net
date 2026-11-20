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

// can() уже учитывает isAdmin() — отдельные hasRole('admin') не нужны
$canCreate     = \App\Services\AuthService::can('players.create');
$canEdit       = \App\Services\AuthService::can('players.edit');
$canEditRole   = \App\Services\AuthService::can('players.edit_role');
$canDeactivate = \App\Services\AuthService::can('players.deactivate');
$canBan        = \App\Services\AuthService::can('players.ban');
$canMute       = \App\Services\AuthService::can('players.mute');
$canRestrict   = \App\Services\AuthService::can('players.restrict');
$canViewLogs   = \App\Services\AuthService::can('view_logs');
$canExport     = \App\Services\AuthService::can('players.view') && \App\Services\AuthService::isAdmin();

$columns = [
    [
        'key'    => 'username',
        'label'  => 'Игрок',
        'render' => function (array $row) use ($roleModel): string {
            $rawName = (string)($row['username'] ?? '');
            $name    = htmlspecialchars($rawName);
            $id      = (int)($row['id'] ?? 0);
            $letter  = htmlspecialchars(mb_strtoupper(mb_substr($rawName !== '' ? $rawName : '?', 0, 1)));
            // Бюст скина Minecraft по нику (nmsr.nickac.dev)
            $bust = $rawName !== ''
                ? 'https://nmsr.nickac.dev/bust/' . rawurlencode($rawName)
                : '';
            $avatarHtml = $bust !== ''
                ? '<img src="' . htmlspecialchars($bust) . '" alt="" class="player-av" loading="lazy"'
                  . ' onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline-flex\';">'
                  . '<span class="player-av player-av--ph" style="display:none">' . $letter . '</span>'
                : '<span class="player-av player-av--ph">' . $letter . '</span>';
            return 
                "<div style='display: flex; align-items: start;'>"
                . $avatarHtml
                . '<div><strong>' . $name . '</strong>'
                . '<div class="text-muted" style="font-size:12px;">ID F-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . '</div></div></div>';
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
            return $bits ? implode(' ', $bits) : '<span class="text-muted">—</span>';
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
        'empty'   => 'Наказание',
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
.text-muted {
    color: var(--muted);
}
.player-av {
    width: 40px; height: 40px; border-radius: 10px; object-fit: cover;
    display: inline-flex; align-items: center; justify-content: center;
    margin-right: 10px; vertical-align: middle; flex-shrink: 0;
}
.player-av--ph { background: #7c5cfc; color: #fff; font-weight: 700; font-size: 14px; border-radius: 50%; }
.player-av--lg { width: 52px; height: 52px; font-size: 18px; margin-right: 12px; border-radius: 12px; }
.players-layout { display: grid; grid-template-columns: 1fr 300px; gap: 20px; align-items: start; }
@media (max-width: 960px) { .players-layout { grid-template-columns: 1fr; } }
.player-card .meta-row { display: flex; justify-content: space-between; gap: 8px; margin: 6px 0; font-size: 13px; }
.player-card .meta-row span:first-child { color: var(--muted, #999); }
.player-card .role-badge {
    display: inline-block; margin-top: 8px; padding: 4px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    background: rgba(124,92,252,.18); color: var(--accent-deep);
}
.player-card .card-actions { margin-top: 16px; display: flex; flex-direction: column; gap: 8px; }
.player-card .card-actions .btn { width: 100%; text-align: center; }
.player-card .card-note { font-size: 12px; color: var(--muted,#999); margin: 10px 0 0; line-height: 1.4; }
.sanction-item {
    font-size: 13px;
    padding: 8px;
    border-radius: 8px;
    background: var(--table-header);
    color: var(--muted);
    margin-bottom: 6px;
}
.pagination-ellipsis { padding: 0 6px; opacity: .5; user-select: none; }
.pagination-nav { min-width: 28px; text-align: center; }
.pagination-nav.is-disabled { opacity: .35; pointer-events: none; }
</style>

<main>
<div class="container">
    <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>
<div class="page-header flex">
        <div>
            <h2>Игроки</h2>
            <p>Учётные записи форума, роли доступа и история ограничений.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ($canCreate): ?>
                <a href="<?= url('/moderation/players/create') ?>" class="btn btn-primary">＋ Создать аккаунт</a>
            <?php endif; ?>
        </div>
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
            <h3 style="margin:0 0 12px;font-size:18px;font-weight:700;">Карточка игрока</h3>
            <?php if (!$selected): ?>
                <p class="text-muted detail-note" style="margin-top:-16px;font-size: 13px;">Нажмите на строку в таблице, чтобы открыть профиль, историю и доступные действия.<br><br>Все действия — в карточке</p>
            <?php else:
                $s = $selected;

                $actorId = (int)(\App\Services\AuthService::user()['id'] ?? 0);
                $targetId = (int)($s['id'] ?? 0);
                $isSelf = $actorId > 0 && $actorId === $targetId;

                $rank = (new \App\Services\RoleHierarchyService())->canActOnUser($actorId, $targetId);
                $act = !$isSelf && !empty($rank['ok']);
                $canEditTarget = $isSelf || !empty($rank['ok']);

                $canDeactivateThis = $canDeactivate && $act;
                $canBanThis        = $canBan && $act;
                $canMuteThis       = $canMute && $act;
                $canRestrictThis   = $canRestrict && $act;
                $canEditThis       = $canEdit && $canEditTarget;
                $canEditRoleThis   = $canEditRole && $act;
                $canPunish         = $canBanThis || $canMuteThis || $canRestrictThis;
                $roleLabel = $roleModel->label($s['role_name'] ?? '');
                $roleKey = strtoupper((string)($s['role_name'] ?? 'user'));
                $threadsCount = (int)($s['threads_count'] ?? 0);
                $postsCount = (int)($s['posts_count'] ?? 0);

                // var_dump($s);

            ?>
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:4px;">
                    <?php
                    $cardName = (string)($s['username'] ?? '');
                    $letter = htmlspecialchars(mb_strtoupper(mb_substr($cardName !== '' ? $cardName : '?', 0, 1)));
                    $bustUrl = $cardName !== '' ? 'https://nmsr.nickac.dev/bust/' . rawurlencode($cardName) : '';
                    if ($bustUrl !== ''): ?>
                        <img class="player-av player-av--lg" src="<?= htmlspecialchars($bustUrl) ?>" alt="" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                        <span class="player-av player-av--ph player-av--lg" style="display:none"><?= $letter ?></span>
                    <?php else: ?>
                        <span class="player-av player-av--ph player-av--lg"><?= $letter ?></span>
                    <?php endif; ?>
                    <div>
                        <strong style="font-size:15px;"><?= htmlspecialchars($cardName) ?></strong>
                        <div class="text-muted" style="font-size:12px;">
                            ID F-<?= str_pad((string)(int)$s['id'], 3, '0', STR_PAD_LEFT) ?>
                            <?= $isSelf ? ' · ваш аккаунт' : '· выбранный аккаунт' ?>
                        </div>
                        <span class="role-badge"><?= htmlspecialchars($roleKey) ?></span>
                    </div>
                </div>

                <div class="meta-row"><span>Доступ</span><span><?= htmlspecialchars($roleLabel) ?></span></div>
                <div class="meta-row"><span>Email</span><span style="word-break:break-all;"><?= htmlspecialchars($s['email'] ?? '—') ?></span></div>
                <div class="meta-row"><span>Регистрация</span><span><?= htmlspecialchars($s['created_at'] ?? '—') ?></span></div>
                <div class="meta-row"><span>Последний вход</span><span><?= htmlspecialchars($s['last_seen'] ?? '—') ?></span></div>
                <div class="meta-row"><span>Темы / ответы</span><span><?= $threadsCount ?> / <?= $postsCount ?></span></div>
                <div class="meta-row">
                    <span>Санкции</span>
                    <span><?= !empty($s['is_banned']) ? 'Бан' : (empty($s['sanctions']) ? 'Нет активных' : count($s['sanctions'])) ?></span>
                </div>

                <?php if (!empty($s['sanction_history'])): ?>
                    <details class="users-accordion">
                        <summary>История наказаний</summary>
                        <?php foreach ($s['sanction_history'] as $san): ?>
                            <div class="sanction-item" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?= htmlspecialchars($san['type'] ?? 'ban') ?></strong>
                                — <?= htmlspecialchars($san['reason'] ?? '') ?>
                                <div class="text-muted" style="font-size:11px;">
                                    до <?= htmlspecialchars($san['expires_at'] ?? 'бессрочно') ?>
                                    · <?= htmlspecialchars($san['banned_by_name'] ?? '—') ?>
                                </div>
                            </div>
                            </div>
                        <?php endforeach; ?>
                    </details>
                <?php endif; ?>

                <?php if (!empty($s['sanctions'])): ?>
                    <h4 style="color:var(--muted);font-size:13px;">Активные наказания</h4>
                    <div>
                    <?php foreach ($s['sanctions'] as $san): ?>
                        <div class="sanction-item" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?= htmlspecialchars($san['type'] ?? 'ban') ?></strong>
                                — <?= htmlspecialchars($san['reason'] ?? '') ?>
                                <div class="text-muted" style="font-size:11px;">
                                    до <?= htmlspecialchars($san['expires_at'] ?? 'бессрочно') ?>
                                    · <?= htmlspecialchars($san['banned_by_name'] ?? '—') ?>
                                </div>
                            </div>
                            <?php if ($canPunish): ?>
                            <form method="post" action="<?= url('/moderation/players/revoke/' . (int)$s['id']) ?>">
                                <input type="hidden" name="type" value="<?= htmlspecialchars($san['type'] ?? 'ban') ?>">
                                <button type="submit" class="btn btn-outline btn-sm"
                                        onclick="return confirm('Снять ограничение?')">Снять</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="card-actions">
                    <?php if ($canEditThis || $canEditRoleThis): ?>
                        <a class="btn btn-outline" href="<?= url('/moderation/players/edit/' . (int)$s['id']) ?>">
                            <?= $canEditRoleThis ? 'Изменить роль / профиль' : 'Изменить профиль' ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($isSelf): ?>
                        <p class="card-note detail-note">Это ваш аккаунт. Можно изменять профиль; наказывать или деактивировать себя нельзя.</p>
                    <?php elseif (!$act): ?>
                        <p class="card-note">Нельзя менять этого игрока: роль равна вашей или выше.</p>
                    <?php endif; ?>

                    <?php if ($canDeactivateThis): ?>
                        <?php $isActive = !isset($s['is_active']) || (int)$s['is_active'] === 1; ?>
                        <form method="post" action="<?= url('/moderation/players/deactivate/' . (int)$s['id']) ?>">
                            <?php if ($isActive): ?>
                                <input type="hidden" name="active" value="0">
                                <button type="submit" class="btn btn-outline btn-danger"
                                        onclick="return confirm('Деактивировать аккаунт? Войти будет нельзя.')">Деактивировать</button>
                            <?php else: ?>
                                <input type="hidden" name="active" value="1">
                                <button type="submit" class="btn btn-outline btn-success"
                                        onclick="return confirm('Снова активировать аккаунт?')">Активировать</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>

                    <?php if ($canPunish): ?>
                        <details>
                            <summary class="btn btn-outline" style="cursor:pointer;list-style:none;">Выдать наказание</summary>
                            <form method="post" action="<?= url('/moderation/players/punish/' . (int)$s['id']) ?>" style="margin-top:10px;">
                                <div class="form-group">
                                    <label>Тип</label>
                                    <select name="type">
                                        <?php if ($canBanThis): ?><option value="ban">Бан</option><?php endif; ?>
                                        <?php if ($canMuteThis): ?><option value="mute">Мут</option><?php endif; ?>
                                        <?php if ($canRestrictThis): ?><option value="restrict">Ограничение</option><?php endif; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Причина *</label>
                                    <input type="text" name="reason" required placeholder="Причина">
                                </div>
                                <div class="form-group">
                                    <label>До (необязательно)</label>
                                    <input type="datetime-local" name="expires_at">
                                </div>
                                <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;"
                                        onclick="return confirm('Выдать наказание?')">Применить</button>
                            </form>
                        </details>
                    <?php endif; ?>

                    <p class="card-note detail-note">Ограничения форума не меняют доступ к игровому серверу.</p>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>
</main>
