<?php
/**
 * Вкладка «Заявки» в модерации.
 */
use App\Components\AdminTable;

$appTab = $appTab ?? ($_GET['sub'] ?? 'submissions');
if (!in_array($appTab, ['submissions', 'forms'], true)) {
    $appTab = 'submissions';
}

$counts         = $counts ?? ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'total' => 0];
$appStatusOpts  = $appStatusOpts ?? [
    'pending'   => 'На рассмотрении',
    'accepted'  => 'Принята',
    'rejected'  => 'Отклонена',
    'cancelled' => 'Отменена',
];
$statusOptions  = $statusOptions ?? [
    'draft'     => 'Черновик',
    'published' => 'Опубликован',
    'archived'  => 'В архиве',
];
$typeOptions    = $typeOptions ?? [
    ''      => '— Не указан —',
    'team'  => 'В команду',
    'unban' => 'Апелляция / Разбан',
    'other' => 'Другое',
];
$filters        = $filters ?? [];
// sub всегда в фильтрах, чтобы AdminTable не терял вкладку
if (empty($filters['sub'])) {
    $filters['sub'] = $appTab;
}
$applications   = $applications ?? [];
$forms          = $forms ?? [];
$formsList      = $formsList ?? [];
$selected       = $selected ?? null;
?>

<main>
    <div class="container">
        <div class="page-header flex">
            <div>
                <h2>Заявки</h2>
                <p>Заявления в команду, апелляции и другие анкеты. Здесь рассматриваются ответы игроков и настраиваются шаблоны.</p>
            </div>
            <div class="page-header-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="<?= url('/moderation/applications/layouts') ?>" class="btn btn-outline">Макеты</a>
                <a href="<?= url('/moderation/applications/create') ?>" class="btn btn-primary">＋ Создать шаблон</a>
            </div>
        </div>

        <div class="sub-tabs" style="display:flex;gap:4px;margin-bottom:16px;">
            <a href="<?= url('/moderation/applications') . '?sub=submissions' ?>"
               class="btn <?= $appTab === 'submissions' ? 'btn-primary' : 'btn-outline' ?>">
                Поступившие
                <?php if (($counts['pending'] ?? 0) > 0): ?>
                    <span class="badge badge-warning" style="margin-left:6px;"><?= (int)$counts['pending'] ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('/moderation/applications') . '?sub=forms' ?>"
               class="btn <?= $appTab === 'forms' ? 'btn-primary' : 'btn-outline' ?>">
                Шаблоны
            </a>
        </div>

        <?php if ($appTab === 'submissions'): ?>
            <?php
            $statusBadgeMap = [
                'pending'   => ['label' => 'На рассмотрении', 'class' => 'badge-warning'],
                'accepted'  => ['label' => 'Принята',         'class' => 'badge-success'],
                'rejected'  => ['label' => 'Отклонена',       'class' => 'badge-danger'],
                'cancelled' => ['label' => 'Отменена',        'class' => 'badge-secondary'],
            ];

            $formFilterOptions = ['' => 'Все шаблоны'];
            foreach ($formsList as $f) {
                $formFilterOptions[(string)$f['id']] = $f['name'];
            }

            $columns = [
                [
                    'key'   => 'created_at',
                    'label' => 'Дата',
                    'type'  => 'datetime',
                    'width' => '140px',
                ],
                [
                    'key'    => 'username',
                    'label'  => 'Игрок',
                    'render' => function (array $row): string {
                        return '<strong>' . htmlspecialchars($row['username'] ?? '—') . '</strong>';
                    },
                ],
                [
                    'key'    => 'form_name',
                    'label'  => 'Шаблон',
                    'render' => function (array $row) use ($typeOptions): string {
                        $name = htmlspecialchars($row['form_name'] ?? '—');
                        $type = $row['application_type'] ?? '';
                        $typeLabel = $typeOptions[$type] ?? $type;
                        $html = $name;
                        if ($type !== '') {
                            $html .= '<div class="text-muted" style="font-size:12px;">' . htmlspecialchars($typeLabel) . '</div>';
                        }
                        return $html;
                    },
                ],
                [
                    'key'   => 'status',
                    'label' => 'Статус',
                    'type'  => 'badge',
                    'map'   => $statusBadgeMap,
                ],
            ];

            $filterConfig = [
                ['name' => 'q', 'type' => 'search', 'placeholder' => 'Игрок или шаблон…'],
                [
                    'name'    => 'status',
                    'type'    => 'select',
                    'empty'   => 'Все статусы',
                    'options' => $appStatusOpts,
                ],
                [
                    'name'    => 'form_id',
                    'type'    => 'select',
                    'empty'   => 'Все шаблоны',
                    'options' => $formFilterOptions,
                ],
            ];

            $table = AdminTable::make(
                items: $applications,
                columns: $columns,
                selected: $selected,
                baseUrl: '/moderation/applications',
                page: (int)($currentPage ?? 1),
                total: (int)($total ?? count($applications)),
                pages: (int)($totalPages ?? 1),
                filters: $filters,
                filterConfig: $filterConfig,
                emptyText: 'Заявок пока нет',
                rowClass: function (array $row) use ($selected) {
                    $classes = [];
                    if (($selected['id'] ?? null) == ($row['id'] ?? null)) {
                        $classes[] = 'is-selected';
                    }
                    if (($row['status'] ?? '') === 'pending') {
                        $classes[] = 'is-pending';
                    }
                    return implode(' ', $classes);
                },
            );
            ?>

            <?= $table->filters() ?>

            <div class="logs-layout">
                <div class="logs-table-card">
                    <div class="logs-table-header">
                        <h3>Поступившие заявки</h3>
                        <span class="logs-count">
                            <?= (int)($counts['pending'] ?? 0) ?> на рассмотрении ·
                            <?= (int)($counts['total'] ?? count($applications)) ?> всего
                        </span>
                    </div>
                    <?= $table ?>
                </div>

                <aside class="log-detail-card">
                    <?php if (empty($selected)): ?>
                        <div class="empty-detail">
                            <p>Выберите заявку, чтобы посмотреть ответы и принять решение</p>
                        </div>
                    <?php else: ?>
                        <?php
                        $st = $selected['status'] ?? 'pending';
                        $stMap = $statusBadgeMap[$st] ?? ['label' => $st, 'class' => 'badge-secondary'];
                        $snapshot = $selected['form_snapshot'] ?? [];
                        $questions = $snapshot['questions'] ?? [];
                        $answers   = $selected['answers'] ?? [];
                        ?>
                        <div class="log-detail-header">
                            <h3>#<?= (int)$selected['id'] ?> · <?= htmlspecialchars($selected['form_name'] ?? '') ?></h3>
                            <span class="badge <?= $stMap['class'] ?>"><?= htmlspecialchars($stMap['label']) ?></span>
                        </div>

                        <dl class="log-detail-list">
                            <div>
                                <dt>Игрок</dt>
                                <dd><?= htmlspecialchars($selected['username'] ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt>Подана</dt>
                                <dd><?= htmlspecialchars($selected['created_at'] ?? '—') ?></dd>
                            </div>
                            <?php if (!empty($selected['reviewed_at'])): ?>
                            <div>
                                <dt>Рассмотрена</dt>
                                <dd>
                                    <?= htmlspecialchars($selected['reviewed_at']) ?>
                                    <?php if (!empty($selected['reviewer_name'])): ?>
                                        · <?= htmlspecialchars($selected['reviewer_name']) ?>
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <?php endif; ?>
                        </dl>

                        <div class="detail-note" style="margin-top:12px;">
                            <strong>Ответы</strong>
                        </div>
                        <dl class="log-detail-list">
                            <?php foreach ($questions as $q):
                                $qid = $q['id'] ?? '';
                                $val = $answers[$qid] ?? null;
                                if (is_array($val)) {
                                    $val = implode(', ', $val);
                                }
                            ?>
                            <div>
                                <dt><?= htmlspecialchars($q['label'] ?? $qid) ?></dt>
                                <dd><?= $val !== null && $val !== '' ? nl2br(htmlspecialchars((string)$val)) : '<span class="text-muted">—</span>' ?></dd>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($questions)): ?>
                            <div><dd class="text-muted">Нет данных анкеты</dd></div>
                            <?php endif; ?>
                        </dl>

                        <?php if (!empty($selected['admin_comment'])): ?>
                        <div class="detail-note">
                            <strong>Комментарий:</strong>
                            <?= nl2br(htmlspecialchars($selected['admin_comment'])) ?>
                        </div>
                        <?php endif; ?>

                        <div class="detail-actions">
                            <a href="<?= url('/moderation/applications/show/' . (int)$selected['id']) ?>" class="btn btn-secondary">
                                Открыть полностью
                            </a>

                            <?php if (in_array($selected['status'] ?? '', ['pending', 'needs_info'], true)): ?>
                            <form method="post" action="<?= url('/moderation/applications/review/' . (int)$selected['id']) ?>" style="display:inline;">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit" class="btn btn-primary" onclick="return confirm('Принять заявку?')">Принять</button>
                            </form>
                            <form method="post" action="<?= url('/moderation/applications/review/' . (int)$selected['id']) ?>" style="display:inline;">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-outline btn-danger" onclick="return confirm('Отклонить заявку?')">Отклонить</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>

        <?php else: ?>
            <?php
            $formStatusMap = [
                'draft'     => ['label' => 'Черновик',     'class' => 'badge-warning'],
                'published' => ['label' => 'Опубликован',  'class' => 'badge-success'],
                'archived'  => ['label' => 'В архиве',     'class' => 'badge-secondary'],
            ];

            $columns = [
                [
                    'key'    => 'name',
                    'label'  => 'Шаблон',
                    'render' => function (array $row) use ($typeOptions): string {
                        $name = htmlspecialchars($row['name'] ?? '');
                        $desc = htmlspecialchars($row['description'] ?? '');
                        $type = $row['application_type'] ?? '';
                        $html = '<strong>' . $name . '</strong>';
                        if ($type !== '') {
                            $html .= ' <span class="badge badge-info" style="font-size:11px;">' . htmlspecialchars($typeOptions[$type] ?? $type) . '</span>';
                        }
                        if ($desc !== '') {
                            $html .= '<div class="text-muted section-desc">' . $desc . '</div>';
                        }
                        return $html;
                    },
                ],
                [
                    'key'    => 'accepting',
                    'label'  => 'Приём',
                    'width'  => '90px',
                    'render' => function (array $row): string {
                        return !empty($row['accepting'])
                            ? '<span class="badge badge-success">Открыт</span>'
                            : '<span class="badge badge-secondary">Закрыт</span>';
                    },
                ],
                [
                    'key'   => 'status',
                    'label' => 'Статус',
                    'type'  => 'badge',
                    'map'   => $formStatusMap,
                ],
                [
                    'key'    => 'pending_count',
                    'label'  => 'Ожидают',
                    'width'  => '90px',
                    'render' => function (array $row): string {
                        $n = (int)($row['pending_count'] ?? 0);
                        return $n > 0
                            ? '<span class="badge badge-warning">' . $n . '</span>'
                            : '<span class="text-muted">0</span>';
                    },
                ],
                [
                    'key'    => 'submissions_count',
                    'label'  => 'Всего',
                    'width'  => '70px',
                    'render' => fn(array $row): string => (string)(int)($row['submissions_count'] ?? 0),
                ],
            ];

            $filterConfig = [
                ['name' => 'q', 'type' => 'search', 'placeholder' => 'Название шаблона…'],
                [
                    'name'    => 'status',
                    'type'    => 'select',
                    'empty'   => 'Все статусы',
                    'options' => $statusOptions,
                ],
            ];

            $table = AdminTable::make(
                items: $forms,
                columns: $columns,
                selected: $selected,
                baseUrl: '/moderation/applications',
                page: (int)($currentPage ?? 1),
                total: (int)($total ?? count($forms)),
                pages: (int)($totalPages ?? 1),
                filters: $filters,
                filterConfig: $filterConfig,
                emptyText: 'Шаблонов пока нет. Создайте первый.',
                rowClass: function (array $row) use ($selected) {
                    return (($selected['id'] ?? null) == ($row['id'] ?? null)) ? 'is-selected' : '';
                },
            );
            ?>

            <?= $table->filters() ?>

            <div class="logs-layout">
                <div class="logs-table-card">
                    <div class="logs-table-header">
                        <h3>Шаблоны заявок</h3>
                        <span class="logs-count"><?= count($forms) ?> шаблонов</span>
                    </div>
                    <?= $table ?>
                </div>

                <aside class="log-detail-card">
                    <?php if (empty($selected)): ?>
                        <div class="empty-detail">
                            <p>Выберите шаблон, чтобы увидеть настройки и перейти к редактированию</p>
                        </div>
                    <?php else: ?>
                        <?php
                        $st = $selected['status'] ?? 'draft';
                        $stMap = $formStatusMap[$st] ?? ['label' => $st, 'class' => 'badge-secondary'];
                        $qCount = is_array($selected['questions'] ?? null) ? count($selected['questions']) : 0;
                        ?>
                        <div class="log-detail-header">
                            <h3><?= htmlspecialchars($selected['name'] ?? '') ?></h3>
                            <span class="badge <?= $stMap['class'] ?>"><?= htmlspecialchars($stMap['label']) ?></span>
                        </div>

                        <dl class="log-detail-list">
                            <div>
                                <dt>Slug</dt>
                                <dd>/<?= htmlspecialchars($selected['slug'] ?? '') ?></dd>
                            </div>
                            <div>
                                <dt>Тип</dt>
                                <dd><?= htmlspecialchars($typeOptions[$selected['application_type'] ?? ''] ?? '—') ?></dd>
                            </div>
                            <div>
                                <dt>Приём заявок</dt>
                                <dd><?= !empty($selected['accepting']) ? 'Открыт' : 'Закрыт' ?></dd>
                            </div>
                            <div>
                                <dt>Вопросов</dt>
                                <dd><?= $qCount ?></dd>
                            </div>
                            <div>
                                <dt>Подано / ожидают</dt>
                                <dd>
                                    <?= (int)($selected['submissions_count'] ?? 0) ?> /
                                    <?= (int)($selected['pending_count'] ?? 0) ?>
                                </dd>
                            </div>
                        </dl>

                        <div class="detail-actions">
                            <a href="<?= url('/moderation/applications/edit/' . (int)$selected['id']) ?>" class="btn btn-secondary">
                                Изменить шаблон
                            </a>
                            <a href="<?= url('/moderation/applications') . '?sub=submissions&form_id=' . (int)$selected['id'] ?>" class="btn btn-outline">
                                Заявки по шаблону
                            </a>
                            <form method="post"
                                  action="<?= url('/moderation/applications/delete/' . (int)$selected['id']) ?>"
                                  onsubmit="return confirm('Удалить или архивировать этот шаблон?');"
                                  style="display:inline;">
                                <button type="submit" class="btn btn-outline btn-danger">Удалить</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</main>
