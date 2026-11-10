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
        use App\Models\Role;

        $roleOptions = $roleOptions ?? (new Role())->getOptions(); // id => label

        // options для фильтра: '' = Публичный + роли
        $accessOptions = $accessOptions ?? (['' => 'Публичный'] + $roleOptions);

        // Подготовка плоского списка для таблицы (категории + секции)
        $flatItems = [];
        foreach ($categories as $category) {
            $viewRoleId = $category['view_role_id'] ?? null;
            $flatItems[] = [
                'id'            => $category['id'],
                'parent_id'     => null,
                'title'         => $category['title'],
                'description'   => $category['description'] ?? '',
                'slug'          => $category['slug'] ?? '',
                'view_role_id'  => $viewRoleId,
                'position'      => $category['position'] ?? 0,
                'topics_count'  => $category['topics_count'] ?? 0,
                'posts_count'   => $category['posts_count'] ?? 0,
                'is_category'   => true,
                'status'        => $category['status'] ?? 'published',
                'is_locked'     => $category['is_locked'] ?? 0,
            ];

            foreach ($category['sections'] ?? [] as $section) {
                $viewRoleId = $section['view_role_id'] ?? null;
                $flatItems[] = [
                    'id'            => $section['id'],
                    'parent_id'     => $category['id'],
                    'title'         => $section['title'],
                    'description'   => $section['description'] ?? '',
                    'slug'          => $section['slug'] ?? '',
                    'view_role_id'  => $viewRoleId,
                    'position'      => $section['position'] ?? 0,
                    'topics_count'  => $section['topics_count'] ?? 0,
                    'posts_count'   => $section['posts_count'] ?? 0,
                    'is_category'   => false,
                    'status'        => $section['status'] ?? 'published',
                    'parent_title'  => $category['title'],
                    'is_locked'     => $section['is_locked'] ?? 0,
                ];
            }
        }

        // Бейджи доступа: динамически из ролей + public/closed
        $accessBadgeMap = [
            ''  => ['label' => 'Публичный', 'class' => 'badge-success'],
        ];
        foreach ($roleOptions as $id => $label) {
            $class = match ((int)$id) {
                1 => 'badge-info',
                2 => 'badge-warning',
                3 => 'badge-danger',
                default => 'badge-secondary',
            };
            $accessBadgeMap[(string)$id] = ['label' => $label, 'class' => $class];
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
                'key'  => 'view_role_id',
                'label'=> 'Доступ',
                'type' => 'badge',
                'map'  => $accessBadgeMap,
                'render' => function (array $row) use ($accessBadgeMap): string {
                    if (!empty($row['is_locked'])) {
                        return '<span class="badge badge-secondary">Закрытый</span>';
                    }
                    $key = $row['view_role_id'] === null || $row['view_role_id'] === ''
                        ? ''
                        : (string)$row['view_role_id'];
                    $badge = $accessBadgeMap[$key] ?? ['label' => $key ?: 'Публичный', 'class' => 'badge-secondary'];
                    return '<span class="badge ' . $badge['class'] . '">' . htmlspecialchars($badge['label']) . '</span>';
                },
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
            ['name' => 'view_role_id', 'type' => 'select', 'empty' => 'Любая видимость', 'options' => $accessOptions],
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

                        // Просмотр
                        if (!empty($selected['is_locked'])) {
                            $viewLabel = 'Закрытый';
                        } else {
                            $vr = $selected['view_role_id'] ?? null;
                            $viewLabel = ($vr === null || $vr === '')
                                ? 'Публичный'
                                : ($roleOptions[(string)$vr] ?? $vr);
                        }

                        // Создание тем
                        $cr = $selected['create_role_id'] ?? null;
                        $createLabel = ($cr === null || $cr === '')
                            ? 'Все'
                            : ($roleOptions[(string)$cr] ?? $cr);

                        // Управление
                        $mr = $selected['manage_role_id'] ?? null;
                        $manageLabel = ($mr === null || $mr === '')
                            ? '—'
                            : ($roleOptions[(string)$mr] ?? $mr);
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
                            <dd><?= htmlspecialchars($viewLabel) ?></dd>
                        </div>

                        <div>
                            <dt>Создание тем</dt>
                            <dd><?= htmlspecialchars($createLabel) ?></dd>
                        </div>

                        <div>
                            <dt>Управление</dt>
                            <dd><?= htmlspecialchars($manageLabel) ?></dd>
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