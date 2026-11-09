<main>
    <div class="container">
        <div class="page-header">
            <h2>Репорты</h2>
            <p>Сигналы о конкретных темах и сообщениях форума: спам, токсичность, запрещённые ссылки.</p>
        </div>

        <?php

        use App\Components\AdminTable;

        $statusOptions = $statusOptions ?? [
            'pending'  => 'Ожидает',
            'resolved' => 'Решён',
            'rejected' => 'Отклонён',
        ];

        $targetTypeOptions = $targetTypeOptions ?? [
            'post'   => 'Сообщение',
            'thread' => 'Тема',
            'user'   => 'Пользователь',
        ];

        $columns = [
            [
                'key'    => 'title',
                'label'  => 'Публикация / контекст',
                'render' => function (array $row): string {
                    $title   = htmlspecialchars($row['title'] ?? ('R-' . ($row['id'] ?? '')));
                    $context = htmlspecialchars($row['context'] ?? '');
                    $reporter = htmlspecialchars($row['reporter_name'] ?? '—');

                    $html = '<div class="report-title-cell">';
                    $html .= '<strong>' . $title . '</strong>';
                    if ($context !== '') {
                        $html .= '<div class="text-muted report-context">' . $context . '</div>';
                    }
                    $html .= '<div class="text-muted report-meta">от ' . $reporter . '</div>';
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
                    'pending'  => ['label' => 'Ожидает', 'class' => 'badge-warning'],
                    'resolved' => ['label' => 'Решён', 'class' => 'badge-success'],
                    'rejected' => ['label' => 'Отклонён', 'class' => 'badge-danger'],
                ],
            ],
            [
                'key'   => 'created_at',
                'label' => 'Дата',
                'type'  => 'datetime',
            ],
        ];

        $filterConfig = [
            ['name' => 'q', 'type' => 'search', 'placeholder' => 'ID репорта или текст'],
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

        <!-- Фильтры сверху -->
        <?= $table->filters() ?>

        <div class="logs-layout">
            <div class="logs-table-card">
                <div class="logs-table-header">
                    <h3>Репорты</h3>
                    <span class="logs-count"><?= (int)($total ?? 0) ?> записей</span>
                </div>

                <?= $table ?>

                <p class="logs-note">
                    Репорты доступны модераторам и администраторам. После разбора статус меняется на «Решён» или «Отклонён».
                </p>
            </div>

            <!-- Правая часть (aside) -->
            <aside class="log-detail-card">
                <?php if (empty($selected)): ?>
                    <div class="empty-detail">
                        <p>Выберите репорт, чтобы посмотреть подробности</p>
                    </div>
                <?php else: ?>
                    <?php
                        $status = $selected['status'] ?? 'pending';
                        $statusClass = match ($status) {
                            'pending'  => 'badge-warning',
                            'resolved' => 'badge-success',
                            'rejected' => 'badge-danger',
                            default    => 'badge-warning',
                        };
                        $statusText = $statusOptions[$status] ?? $status;

                        $typeLabel = $selected['type_label']
                            ?? match ($selected['target_type'] ?? '') {
                                'post'   => 'Сообщение',
                                'thread' => 'Тема',
                                'user'   => 'Пользователь',
                                default  => $selected['target_type'] ?? '—',
                            };
                    ?>

                    <div class="log-detail-header">
                        <h3>Репорт R-<?= str_pad((string)$selected['id'], 3, '0', STR_PAD_LEFT) ?></h3>
                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></span>
                    </div>

                    <dl class="log-detail-list">
                        <div>
                            <dt>Время</dt>
                            <dd><?= date('d.m.Y · H:i:s', strtotime($selected['created_at'])) ?></dd>
                        </div>
                        <div>
                            <dt>Репортёр</dt>
                            <dd><?= htmlspecialchars($selected['reporter_name'] ?? '—') ?></dd>
                        </div>
                        <div>
                            <dt>Объект</dt>
                            <dd>
                                <?= htmlspecialchars($typeLabel) ?>
                                #<?= (int)($selected['target_id'] ?? 0) ?>
                                <br><small class="text-muted"><?= htmlspecialchars($selected['target_type'] ?? '') ?></small>
                            </dd>
                        </div>
                        <div>
                            <dt>Причина</dt>
                            <dd><?= htmlspecialchars($selected['reason'] ?? '—') ?></dd>
                        </div>
                        <?php if (!empty($selected['description'])): ?>
                        <div>
                            <dt>Описание</dt>
                            <dd><?= nl2br(htmlspecialchars($selected['description'])) ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($selected['resolver_name'])): ?>
                        <div>
                            <dt>Закрыл</dt>
                            <dd>
                                <?= htmlspecialchars($selected['resolver_name']) ?>
                                <?php if (!empty($selected['resolved_at'])): ?>
                                    <br><small class="text-muted"><?= date('d.m.Y · H:i', strtotime($selected['resolved_at'])) ?></small>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</main>