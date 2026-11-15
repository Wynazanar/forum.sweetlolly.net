<?php
use App\Components\AdminTable;
use App\Services\AuthService;

$discussions = $discussions ?? [];
$selected = $selected ?? null;
$filters = $filters ?? [];
$total = (int)($total ?? 0);
$page = (int)($page ?? 1);
$pages = (int)($pages ?? 1);
$categoryOptions = $categoryOptions ?? [];


$table = AdminTable::make(
    items: $discussions,
    columns: $columns,
    selected: $selected,
    baseUrl: '/moderation/discussions',
    page: $page,
    total: $total,
    pages: $pages,
    filters: $filters,
    filterConfig: $filterConfig,
    emptyText: 'Обсуждений не найдено',
    idKey: 'id',
);
?>
<main>
    <div class="container">
        КОММЕНТАРИИ ПОСТА
    </div>
</main>