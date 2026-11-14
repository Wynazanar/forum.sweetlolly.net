<?php
use App\Components\AdminTable;
use App\Services\AuthService;

$topics = $topics ?? [];
$selected = $selected ?? null;
$filters = $filters ?? [];
$total = (int)($total ?? 0);
$page = (int)($page ?? 1);
$pages = (int)($pages ?? 1);
$categoryOptions = $categoryOptions ?? [];

$canManage = AuthService::can('lock_thread') || AuthService::can('move_thread') || AuthService::hasRole('admin');

$columns = [
    [
        'key'    => 'title',
        'label'  => 'Тема',
        'render' => function (array $row): string {
            $title = htmlspecialchars($row['title'] ?? '');
            $id = (int)($row['id'] ?? 0);
            $updated = $row['last_post_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? '';
            $when = $updated ? date('d.m.Y, H:i', strtotime($updated)) : '';
            return '<div><strong>' . $title . '</strong>'
                . '<div class="text-muted" style="font-size:12px;">T-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT)
                . ($when ? ' · ' . htmlspecialchars($when) : '')
                . '</div></div>';
        },
    ],
    [
        'key'    => 'category_title',
        'label'  => 'Раздел',
        'render' => fn(array $row): string => htmlspecialchars($row['category_title'] ?? '—'),
    ],
    [
        'key'    => 'author_name',
        'label'  => 'Автор',
        'render' => fn(array $row): string => htmlspecialchars($row['author_name'] ?? '—'),
    ],
    [
        'key'    => 'replies_count',
        'label'  => 'Ответы',
        'render' => fn(array $row): string => (string)(int)($row['replies_count'] ?? max(0, ((int)($row['posts_count'] ?? 1)) - 1)),
    ],
    [
        'key'    => 'views',
        'label'  => 'Просмотры',
        'render' => fn(array $row): string => (string)(int)($row['views'] ?? 0),
    ],
    [
        'key'    => 'state',
        'label'  => 'Состояние',
        'render' => function (array $row): string {
            $status = $row['status'] ?? 'published';
            if ($status === 'draft') {
                return '<span class="badge badge-secondary">Черновик</span>';
            }
            if ($status === 'pending') {
                return '<span class="badge badge-warning">На проверке</span>';
            }
            if ($status === 'rejected') {
                return '<span class="badge badge-danger">Отклонена</span>';
            }
            if (!empty($row['is_hidden'])) {
                return '<span class="badge badge-secondary">Скрыта</span>';
            }
            if (!empty($row['is_sticky']) && !empty($row['is_locked'])) {
                return '<span class="badge badge-warning">Закреплена · закрыта</span>';
            }
            if (!empty($row['is_sticky'])) {
                return '<span class="badge badge-warning">Закреплена</span>';
            }
            if (!empty($row['is_locked'])) {
                return '<span class="badge badge-secondary">Закрыта</span>';
            }
            return '<span class="badge badge-success">Открыта</span>';
        },
    ],
];

$filterConfig = [
    ['name' => 'q', 'type' => 'search', 'placeholder' => 'Название, slug или ID'],
    [
        'name'    => 'category_id',
        'type'    => 'select',
        'empty'   => 'Все разделы',
        'options' => $categoryOptions,
    ],
    [
        'name'    => 'locked',
        'type'    => 'select',
        'empty'   => 'Любое состояние',
        'options' => ['0' => 'Открытые', '1' => 'Закрытые'],
    ],
    [
        'name'    => 'sticky',
        'type'    => 'select',
        'empty'   => 'Закрепление',
        'options' => ['1' => 'Только закреплённые', '0' => 'Без закрепа'],
    ],
];

$table = AdminTable::make(
    items: $topics,
    columns: $columns,
    selected: $selected,
    baseUrl: '/moderation/topics',
    page: $page,
    total: $total,
    pages: $pages,
    filters: $filters,
    filterConfig: $filterConfig,
    emptyText: 'Тем не найдено',
    idKey: 'id',
);
?>
<style>
.topics-layout { display: grid; grid-template-columns: 1fr 300px; gap: 20px; align-items: start; }
@media (max-width: 960px) { .topics-layout { grid-template-columns: 1fr; } }
.topic-card .meta-row { display: flex; justify-content: space-between; gap: 8px; margin: 6px 0; font-size: 13px; }
.topic-card .meta-row span:first-child { color: var(--muted, #999); }
</style>

<main>
<div class="container">
    <div class="page-header flex">
        <div>
            <h2>Темы форума</h2>
            <p>Список тем, закрепление, закрытие и перенос между разделами.</p>
        </div>
        <?php if ($canManage): ?>
            <a href="<?= url('/moderation/topics/create') ?>" class="btn btn-primary">＋ Создать тему</a>
        <?php endif; ?>
    </div>

    <?= $table->filters() ?>

    <div class="topics-layout">
        <div class="logs-table-card">
            <div class="logs-table-header">
                <h3>Темы</h3>
                <span class="logs-count"><?= (int)$total ?></span>
            </div>
            <?= $table->render() ?>
        </div>

        <aside class="logs-table-card topic-card" style="padding:16px;">
            <?php if (!$selected): ?>
                <p class="text-muted">Выберите тему в таблице</p>
            <?php else:
                $s = $selected;
                $tid = (int)$s['id'];
            ?>
                <h3 style="margin:0 0 8px;font-size:1rem;"><?= htmlspecialchars($s['title'] ?? '') ?></h3>
                <div class="text-muted" style="font-size:12px;margin-bottom:12px;">
                    T-<?= str_pad((string)$tid, 3, '0', STR_PAD_LEFT) ?> · <?= htmlspecialchars($s['slug'] ?? '') ?>
                </div>

                <div class="meta-row"><span>Раздел</span><span><?= htmlspecialchars($s['category_title'] ?? '') ?></span></div>
                <div class="meta-row"><span>Автор</span><span><?= htmlspecialchars($s['author_name'] ?? '') ?></span></div>
                <div class="meta-row"><span>Ответы</span><span><?= (int)($s['replies_count'] ?? 0) ?></span></div>
                <div class="meta-row"><span>Просмотры</span><span><?= (int)($s['views'] ?? 0) ?></span></div>
                <div class="meta-row"><span>Создана</span><span><?= htmlspecialchars($s['created_at'] ?? '') ?></span></div>
                <div class="meta-row"><span>Активность</span><span><?= htmlspecialchars($s['last_post_at'] ?? '—') ?></span></div>

                <?php if ($canManage): ?>
                <div style="margin-top:14px;display:flex;flex-direction:column;gap:8px;">
                    <a class="btn btn-outline" href="<?= url('/moderation/topics/edit/' . $tid) ?>">Редактировать</a>

                    <form method="post" action="<?= url('/moderation/topics/sticky/' . $tid) ?>">
                        <?php if (empty($s['is_sticky'])): ?>
                            <input type="hidden" name="sticky" value="1">
                            <button type="submit" class="btn btn-outline" style="width:100%;">Закрепить</button>
                        <?php else: ?>
                            <input type="hidden" name="sticky" value="0">
                            <button type="submit" class="btn btn-outline" style="width:100%;">Открепить</button>
                        <?php endif; ?>
                    </form>

                    <form method="post" action="<?= url('/moderation/topics/lock/' . $tid) ?>">
                        <?php if (empty($s['is_locked'])): ?>
                            <input type="hidden" name="locked" value="1">
                            <button type="submit" class="btn btn-outline" style="width:100%;">Закрыть</button>
                        <?php else: ?>
                            <input type="hidden" name="locked" value="0">
                            <button type="submit" class="btn btn-outline" style="width:100%;">Открыть</button>
                        <?php endif; ?>
                    </form>

                    <form method="post" action="<?= url('/moderation/topics/move/' . $tid) ?>">
                        <label class="text-muted" style="font-size:12px;">Перенести в раздел</label>
                        <select name="category_id" style="width:100%;margin:6px 0;">
                            <?php foreach ($categoryOptions as $cid => $ctitle): ?>
                                <option value="<?= (int)$cid ?>" <?= (int)($s['category_id'] ?? 0) === (int)$cid ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ctitle) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline" style="width:100%;">Перенести</button>
                    </form>

                    <form method="post" action="<?= url('/moderation/topics/hide/' . $tid) ?>"
                          onsubmit="return confirm('Скрыть тему из публичного списка?');">
                        <input type="hidden" name="hidden" value="1">
                        <button type="submit" class="btn btn-outline btn-danger" style="width:100%;">Скрыть</button>
                    </form>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </aside>
    </div>
</div>
</main>
