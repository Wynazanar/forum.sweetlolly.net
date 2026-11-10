<main>
    <div class="container">
        <div class="page-header flex">
            <div>
                <h2>Разделы</h2>
                <p>Структура форума: категории, вложенные разделы, доступ и порядок отображения.</p>
            </div>
            <a href="<?= url('moderation/sections/create') ?>" class="btn btn-primary">＋ Создать раздел</a>
        </div>

        <?php
        use App\Components\AdminTable;

        $accessOptions = $accessOptions ?? [
            'public'       => 'Публичный',
            'players'      => 'Игроки',
            'moderators'   => 'Модераторы',
            'admins'       => 'Администраторы',
            'closed'       => 'Закрытый',
        ];

        $visibilityOptions = $visibilityOptions ?? [
            'all'    => 'Любая видимость',
            'public' => 'Публичный',
            'closed' => 'Закрытый',
        ];

        // Подготовка плоского списка для таблицы (категории + секции)
        $flatItems = [];
        foreach ($categories as $category) {
            $flatItems[] = [
                'id'            => $category['id'],
                'parent_id'     => null,
                'title'         => $category['title'],
                'description'   => $category['description'] ?? '',
                'slug'          => $category['slug'] ?? '',
                'access'        => $category['access'] ?? 'public',
                'position'      => $category['position'] ?? 0,
                'topics_count'  => $category['topics_count'] ?? 0,
                'posts_count'   => $category['posts_count'] ?? 0,
                'is_category'   => true,
                'status'        => $category['status'] ?? 'published',
            ];

            foreach ($category['sections'] ?? [] as $section) {
                $flatItems[] = [
                    'id'            => $section['id'],
                    'parent_id'     => $category['id'],
                    'title'         => $section['title'],
                    'description'   => $section['description'] ?? '',
                    'slug'          => $section['slug'] ?? '',
                    'access'        => $section['access'] ?? 'players',
                    'position'      => $section['position'] ?? 0,
                    'topics_count'  => $section['topics_count'] ?? 0,
                    'posts_count'   => $section['posts_count'] ?? 0,
                    'is_category'   => false,
                    'status'        => $section['status'] ?? 'published',
                    'parent_title'  => $category['title'],
                ];
            }
        }

        $columns = [
            [
                'key'    => 'position',
                'label'  => 'Порядок',
                'width'  => '80px',
                'render' => function (array $row): string {
                    $pos = str_pad((string)($row['position'] ?? 0), 2, '0', STR_PAD_LEFT);
                    return '<span class="drag-handle">⋮⋮</span> <span class="pos-number">' . $pos . '</span>';
                },
            ],
            [
                'key'    => 'title',
                'label'  => 'Категория / Раздел',
                'render' => function (array $row): string {
                    $title = htmlspecialchars($row['title']);
                    $desc  = htmlspecialchars($row['description'] ?? '');
                    $isCat = $row['is_category'] ?? false;

                    $html = '<div class="section-title-cell">';
                    if ($isCat) {
                        $html .= '<span class="tree-bullet">•</span> ';
                        $html .= '<strong>' . $title . '</strong>';
                    } else {
                        $html .= '<span class="tree-indent">↳</span> ';
                        $html .= $title;
                    }
                    if ($desc !== '') {
                        $html .= '<div class="text-muted section-desc">' . $desc . '</div>';
                    }
                    $html .= '</div>';
                    return $html;
                },
            ],
            [
                'key'  => 'access',
                'label'=> 'Доступ',
                'type' => 'badge',
                'map'  => [
                    'public'     => ['label' => 'Публичный',     'class' => 'badge-success'],
                    'players'    => ['label' => 'Игроки',        'class' => 'badge-info'],
                    'moderators' => ['label' => 'Модераторы',    'class' => 'badge-warning'],
                    'admins'     => ['label' => 'Администраторы','class' => 'badge-danger'],
                    'closed'     => ['label' => 'Закрытый',      'class' => 'badge-secondary'],
                ],
            ],
            [
                'key'   => 'topics_count',
                'label' => 'Темы',
                'align' => 'center',
            ],
            [
                'key'   => 'posts_count',
                'label' => 'Ответы',
                'align' => 'center',
            ],
            [
                'key'    => 'actions',
                'label'  => 'Действия',
                'render' => function (array $row): string {
                    if ($row['is_category'] ?? false) {
                        return '<a href="'.url('/moderation/sections/create?parent=' . $row['id']) . '" class="btn-link">+ Раздел</a>
                                <button class="btn-icon" data-action="more">⋯</button>';
                    }
                    return '<a href="'.url('/moderation/sections/edit/' . $row['id']) . '" class="btn-link">Изменить</a>
                            <button class="btn-icon" data-action="more">⋯</button>';
                },
            ],
        ];

        $filterConfig = [
            ['name' => 'q', 'type' => 'search', 'placeholder' => 'Название раздела или slug'],
            ['name' => 'category_id', 'type' => 'select', 'empty' => 'Все категории', 'options' => $categoryOptions ?? []],
            ['name' => 'access', 'type' => 'select', 'empty' => 'Любая видимость', 'options' => $visibilityOptions],
        ];

        $table = AdminTable::make(
            items: $flatItems,
            columns: $columns,
            selected: $selected ?? null,
            baseUrl: '/moderation/sections',
            page: (int)($currentPage ?? 1),
            total: (int)($total ?? count($flatItems)),
            pages: (int)($totalPages ?? 1),
            filters: $filters ?? [],
            filterConfig: $filterConfig,
            emptyText: 'Разделов пока нет',
            rowClass: function (array $row) use ($selected) {
                $classes = [];
                if (($selected['id'] ?? null) == $row['id']) {
                    $classes[] = 'is-selected';
                }
                if ($row['is_category'] ?? false) {
                    $classes[] = 'is-category';
                }
                return implode(' ', $classes);
            },
        );
        ?>

        <!-- Фильтры -->
        <?= $table->filters() ?>

        <div class="logs-layout">
            <div class="logs-table-card">
                <div class="logs-table-header">
                    <h3>Дерево разделов</h3>
                    <span class="logs-count">
                        <?= count(array_filter($flatItems, fn($i) => $i['is_category'])) ?> категории · 
                        <?= count(array_filter($flatItems, fn($i) => !$i['is_category'])) ?> разделов
                    </span>
                </div>

                <?= $table ?>

                <p class="logs-note">
                    Порядок задаётся внутри родительской категории.
                </p>
            </div>

            <!-- Правая панель -->
            <aside class="log-detail-card">
                <?php if (empty($selected)): ?>
                    <div class="empty-detail">
                        <p>Выберите раздел или категорию, чтобы посмотреть подробности</p>
                    </div>
                <?php else: ?>
                    <?php
                        $isCategory = $selected['is_category'] ?? false;
                        $status = $selected['status'] ?? 'published';
                        $statusClass = match ($status) {
                            'published' => 'badge-success',
                            'draft'     => 'badge-warning',
                            'hidden'    => 'badge-secondary',
                            default     => 'badge-success',
                        };
                        $statusText = match ($status) {
                            'published' => 'Опубликован',
                            'draft'     => 'Черновик',
                            'hidden'    => 'Скрыт',
                            default     => $status,
                        };

                        $accessLabel = $accessOptions[$selected['access'] ?? ''] ?? ($selected['access'] ?? '—');
                    ?>

                    <div class="log-detail-header">
                        <h3><?= htmlspecialchars($selected['title']) ?></h3>
                        <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></span>
                    </div>

                    <dl class="log-detail-list">
                        <?php if (!$isCategory && !empty($selected['parent_title'])): ?>
                        <div>
                            <dt>Категория</dt>
                            <dd><?= htmlspecialchars($selected['parent_title']) ?></dd>
                        </div>
                        <?php endif; ?>

                        <div>
                            <dt>Адрес</dt>
                            <dd>/<?= htmlspecialchars($selected['slug'] ?? '') ?></dd>
                        </div>

                        <div>
                            <dt>Просмотр</dt>
                            <dd><?= htmlspecialchars($accessLabel) ?></dd>
                        </div>

                        <div>
                            <dt>Создание тем</dt>
                            <dd><?= htmlspecialchars($selected['can_create_topics'] ?? 'Игроки форума') ?></dd>
                        </div>

                        <div>
                            <dt>Модерация</dt>
                            <dd><?= htmlspecialchars($selected['moderation'] ?? 'Администраторы') ?></dd>
                        </div>

                        <div>
                            <dt>Порядок</dt>
                            <dd>
                                <?= (int)($selected['position'] ?? 0) ?>
                                <?= $isCategory ? 'в списке категорий' : 'в категории' ?>
                            </dd>
                        </div>
                    </dl>

                    <div class="detail-note">
                        Изменение доступа применяется к разделу и его темам. Закрытые материалы не видны гостям.
                    </div>

                    <div class="detail-actions">
                        <a href="<?= url('moderation/sections/edit/'.(int)$selected['id']) ?>" class="btn btn-secondary">
                            Изменить <?= $isCategory ? 'категорию' : 'раздел' ?>
                        </a>

                        <?php if ($isCategory): ?>
                            <a href="<?= url('/moderation/sections/create?parent='.(int)$selected['id']) ?>" class="btn btn-outline">
                                + Вложенный раздел
                            </a>
                        <?php endif; ?>

                        <form method="post"
                            action="<?= url('/moderation/sections/delete/' . (int)$selected['id']) ?>"
                            onsubmit="return confirm('Удалить этот раздел? Это действие нельзя отменить.');"
                            style="display: inline;">
                            <button type="submit" class="btn btn-outline btn-danger">
                                Удалить
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</main>