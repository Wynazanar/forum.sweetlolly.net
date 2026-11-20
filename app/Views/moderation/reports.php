<main>
    <div class="container">
        <div class="page-header">
            <h2>Репорты</h2>
            <p>Жалобы на сообщения, темы и игроков. Разбор, уточнения и быстрые действия.</p>
        </div>

        <?php
        use App\Components\AdminTable;
        use App\Services\AuthService;

        $statusOptions = $statusOptions ?? [
            'pending'    => 'Ожидает',
            'needs_info' => 'Нужно уточнение',
            'resolved'   => 'Решён',
            'rejected'   => 'Отклонён',
        ];
        $targetTypeOptions = $targetTypeOptions ?? [
            'post'   => 'Сообщение',
            'thread' => 'Тема',
            'user'   => 'Игрок',
        ];
        $canResolve = $canResolve ?? AuthService::can('resolve_report');
        $messages   = $messages ?? [];

        $columns = [
            [
                'key'    => 'title',
                'label'  => 'Репорт',
                'render' => function (array $row): string {
                    $title    = htmlspecialchars($row['title'] ?? ('R-' . ($row['id'] ?? '')));
                    $context  = htmlspecialchars($row['context'] ?? '');
                    $reporter = htmlspecialchars($row['reporter_name'] ?? '—');
                    $type     = htmlspecialchars($row['type_label'] ?? ($row['target_type'] ?? ''));

                    $html  = '<div class="report-title-cell">';
                    $html .= '<strong>' . $title . '</strong>';
                    $html .= '<div class="text-muted report-meta">' . $type . ' · от ' . $reporter . '</div>';
                    if ($context !== '') {
                        $html .= '<div class="text-muted report-context">' . $context . '</div>';
                    }
                    $html .= '</div>';
                    return $html;
                },
            ],
            [
                'key'  => 'reason',
                'label'=> 'Причина',
                'type' => 'badge',
                'map'  => [
                    'Спам'        => ['label' => 'Спам', 'class' => 'badge-purple'],
                    'Флуд'        => ['label' => 'Флуд', 'class' => 'badge-purple'],
                    'Оскорбления' => ['label' => 'Оскорбления', 'class' => 'badge-orange'],
                    'Токсичность' => ['label' => 'Токсичность', 'class' => 'badge-orange'],
                ],
            ],
            [
                'key'  => 'status',
                'label'=> 'Статус',
                'type' => 'badge',
                'map'  => [
                    'pending'    => ['label' => 'Ожидает', 'class' => 'badge-warning'],
                    'needs_info' => ['label' => 'Уточнение', 'class' => 'badge-info'],
                    'resolved'   => ['label' => 'Решён', 'class' => 'badge-success'],
                    'rejected'   => ['label' => 'Отклонён', 'class' => 'badge-danger'],
                ],
            ],
            [
                'key'   => 'created_at',
                'label' => 'Дата',
                'type'  => 'datetime',
            ],
        ];

        $filterConfig = [
            ['name' => 'q', 'type' => 'search', 'placeholder' => 'ID, ник или текст'],
            ['name' => 'status', 'type' => 'select', 'empty' => 'Все статусы', 'options' => $statusOptions],
            ['name' => 'target_type', 'type' => 'select', 'empty' => 'Все типы', 'options' => $targetTypeOptions],
            ['name' => 'date', 'type' => 'date'],
        ];

        $table = AdminTable::make(
            items: $items ?? [],
            columns: $columns,
            selected: $selected ?? null,
            baseUrl: '/moderation/reports',
            page: (int)($page ?? 1),
            total: (int)($total ?? 0),
            pages: (int)($pages ?? 1),
            filters: $filters ?? [],
            filterConfig: $filterConfig,
            emptyText: 'Репортов пока нет',
        );
        ?>

        <style>
        /* Шире правая колонка — формы и текст не ломаются */
        .logs-layout.reports-layout .log-detail-card.report-detail {
            flex: 0 0 360px;
            max-width: 380px;
            width: 360px;
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .report-detail .log-detail-header {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .report-detail .log-detail-header h3 {
            font-size: 16px;
            margin: 0;
        }

        /* Мета: колонка, не «в одну линию» — длинный текст не схлопывается */
        .report-detail .report-meta-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 0 0 14px;
            padding: 0;
        }
        .report-detail .report-meta-row {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .report-detail .report-meta-row .lbl {
            font-size: 11px;
            color: var(--muted, #999);
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .report-detail .report-meta-row .val {
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
            word-break: break-word;
        }
        .report-detail .report-meta-row .val .sub {
            display: block;
            font-weight: 400;
            font-size: 12px;
            color: var(--muted, #999);
            margin-top: 2px;
        }

        .report-preview {
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(124, 92, 252, .08);
            border: 1px solid rgba(124, 92, 252, .12);
            margin: 0 0 12px;
            line-height: 1.45;
            word-break: break-word;
            max-height: 120px;
            overflow: auto;
        }

        .report-quick {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }
        .report-quick .btn {
            flex: 1 1 auto;
            min-width: 0;
            text-align: center;
            font-size: 12px;
            padding: 8px 10px;
        }

        .report-section {
            border-top: 1px solid var(--border, rgba(0,0,0,.08));
            padding-top: 12px;
            margin-top: 4px;
        }
        .report-section h4 {
            margin: 0 0 10px;
            font-size: 13px;
            font-weight: 700;
        }

        .report-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .report-actions form {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .report-actions .btn,
        .report-dialog .btn {
            width: 100%;
            text-align: center;
        }
        .report-field {
            width: 100%;
            box-sizing: border-box;
            border-radius: 8px;
            border: 1px solid var(--border, #ddd);
            background: var(--surface, #fff);
            color: inherit;
            font: inherit;
            font-size: 13px;
            padding: 8px 10px;
        }
        textarea.report-field {
            resize: vertical;
            min-height: 64px;
        }
        .report-check {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--muted, #777);
            user-select: none;
        }

        .report-dialog { margin-top: 4px; }
        .report-msg-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
            max-height: 220px;
            overflow: auto;
        }
        .report-msg {
            font-size: 13px;
            padding: 8px 10px;
            border-radius: 8px;
            background: var(--table-header, rgba(0,0,0,.06));
            border-left: 3px solid transparent;
            line-height: 1.4;
            word-break: break-word;
        }
        .report-msg--internal { border-left-color: #a78bfa; }
        .report-msg--request  { border-left-color: #fbbf24; }
        .report-msg .meta {
            font-size: 11px;
            color: var(--muted, #999);
            margin-bottom: 4px;
        }

        .report-dialog form {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 12px;
        }
        .report-dialog form:last-child { margin-bottom: 0; }
        .report-dialog label.field-label {
            font-size: 12px;
            color: var(--muted, #999);
        }

        @media (max-width: 960px) {
            .logs-layout.reports-layout {
                flex-direction: column;
            }
            .logs-layout.reports-layout .log-detail-card.report-detail {
                flex: 1 1 auto;
                width: 100%;
                max-width: none;
            }
        }
        </style>

        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger" style="margin-bottom:12px;"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <?= $table->filters() ?>

        <div class="logs-layout reports-layout">
            <div class="logs-table-card">
                <div class="logs-table-header">
                    <h3>Репорты</h3>
                    <span class="logs-count"><?= (int)($total ?? 0) ?> записей</span>
                </div>
                <?= $table ?>
                <p class="logs-note">
                    После разбора статус меняется на «Решён» или «Отклонён». Можно запросить уточнение у репортёра.
                </p>
            </div>

            <aside class="log-detail-card report-detail">
                <?php if (empty($selected)): ?>
                    <div class="empty-detail">
                        <p>Выберите репорт, чтобы посмотреть подробности и действия</p>
                    </div>
                <?php else:
                    $status = $selected['status'] ?? 'pending';
                    $statusClass = match ($status) {
                        'pending'    => 'badge-warning',
                        'needs_info' => 'badge-info',
                        'resolved'   => 'badge-success',
                        'rejected'   => 'badge-danger',
                        default      => 'badge-warning',
                    };
                    $statusText = $statusOptions[$status] ?? $status;
                    $typeLabel = $selected['type_label'] ?? ($selected['target_type'] ?? '—');
                    $rid = (int)$selected['id'];
                    $isOpen = in_array($status, ['pending', 'needs_info'], true);
                ?>
                    <div class="log-detail-header">
                        <h3>R-<?= str_pad((string)$rid, 3, '0', STR_PAD_LEFT) ?></h3>
                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></span>
                    </div>

                    <div class="report-meta-list">
                        <div class="report-meta-row">
                            <span class="lbl">Время</span>
                            <span class="val"><?= date('d.m.Y · H:i', strtotime($selected['created_at'])) ?></span>
                        </div>
                        <div class="report-meta-row">
                            <span class="lbl">Репортёр</span>
                            <span class="val"><?= htmlspecialchars($selected['reporter_name'] ?? '—') ?></span>
                        </div>
                        <div class="report-meta-row">
                            <span class="lbl">Объект</span>
                            <span class="val">
                                <?= htmlspecialchars($typeLabel) ?> #<?= (int)($selected['target_id'] ?? 0) ?>
                                <?php if (!empty($selected['target_name'])): ?>
                                    <span class="sub"><?= htmlspecialchars($selected['target_name']) ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="report-meta-row">
                            <span class="lbl">Причина</span>
                            <span class="val"><?= htmlspecialchars($selected['reason'] ?? '—') ?></span>
                        </div>
                        <?php if (!empty($selected['description'])): ?>
                        <div class="report-meta-row">
                            <span class="lbl">Описание</span>
                            <span class="val"><?= nl2br(htmlspecialchars($selected['description'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($selected['resolver_name'])): ?>
                        <div class="report-meta-row">
                            <span class="lbl">Закрыл</span>
                            <span class="val">
                                <?= htmlspecialchars($selected['resolver_name']) ?>
                                <?php if (!empty($selected['resolved_at'])): ?>
                                    <span class="sub"><?= date('d.m.Y · H:i', strtotime($selected['resolved_at'])) ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($selected['target_preview'])): ?>
                        <div class="report-preview">
                            <?= nl2br(htmlspecialchars($selected['target_preview'])) ?>
                        </div>
                    <?php endif; ?>

                    <div class="report-quick">
                        <?php if (!empty($selected['target_url'])): ?>
                            <a class="btn btn-outline" href="<?= htmlspecialchars($selected['target_url']) ?>">Объект</a>
                        <?php endif; ?>
                        <?php if (!empty($selected['target_user_id'])): ?>
                            <a class="btn btn-outline" href="<?= url('/moderation/players?id=' . (int)$selected['target_user_id']) ?>">Игрок</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($canResolve): ?>
                    <div class="report-section">
                        <h4>Действия</h4>
                        <div class="report-actions">
                            <?php if ($isOpen): ?>
                                <form method="post" action="<?= url('/moderation/reports/resolve/' . $rid) ?>">
                                    <input class="report-field" type="text" name="comment" placeholder="Комментарий (необязательно)">
                                    <button type="submit" class="btn btn-primary"
                                            onclick="return confirm('Отметить репорт как решённый?')">Решено</button>
                                </form>
                                <form method="post" action="<?= url('/moderation/reports/reject/' . $rid) ?>">
                                    <input class="report-field" type="text" name="comment" placeholder="Причина отклонения">
                                    <button type="submit" class="btn btn-outline btn-danger"
                                            onclick="return confirm('Отклонить репорт?')">Отклонить</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= url('/moderation/reports/reopen/' . $rid) ?>">
                                    <button type="submit" class="btn btn-outline"
                                            onclick="return confirm('Вернуть репорт в очередь?')">Вернуть в очередь</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="report-section report-dialog">
                        <h4>Диалог</h4>

                        <?php if (empty($messages)): ?>
                            <p class="text-muted" style="font-size:13px;margin:0 0 10px;">Сообщений пока нет.</p>
                        <?php else: ?>
                            <div class="report-msg-list">
                                <?php foreach ($messages as $m):
                                    $kind = $m['kind'] ?? 'message';
                                    $cls = 'report-msg';
                                    if (!empty($m['is_internal'])) $cls .= ' report-msg--internal';
                                    if ($kind === 'request_info') $cls .= ' report-msg--request';
                                ?>
                                    <div class="<?= $cls ?>">
                                        <div class="meta">
                                            <?= htmlspecialchars($m['username'] ?? '—') ?>
                                            · <?= htmlspecialchars($m['created_at'] ?? '') ?>
                                            <?php if (!empty($m['is_internal'])): ?> · внутр.<?php endif; ?>
                                            <?php if ($kind === 'request_info'): ?> · уточнение<?php endif; ?>
                                        </div>
                                        <?= nl2br(htmlspecialchars($m['body'] ?? '')) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($isOpen): ?>
                        <form method="post" action="<?= url('/moderation/reports/request-info/' . $rid) ?>">
                            <label class="field-label">Запросить уточнение</label>
                            <textarea class="report-field" name="body" rows="2" required placeholder="Что нужно уточнить?"></textarea>
                            <button type="submit" class="btn btn-outline">Запросить</button>
                        </form>

                        <form method="post" action="<?= url('/moderation/reports/reply/' . $rid) ?>">
                            <label class="field-label">Комментарий</label>
                            <textarea class="report-field" name="body" rows="2" required placeholder="Сообщение…"></textarea>
                            <label class="report-check">
                                <input type="checkbox" name="is_internal" value="1" checked>
                                Только для модераторов
                            </label>
                            <button type="submit" class="btn btn-outline">Отправить</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</main>
