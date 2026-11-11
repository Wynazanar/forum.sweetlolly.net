<?php
namespace App\Components;

/**
 * Универсальная таблица для админ-панели (репорты, жалобы, игроки, логи и т.д.)
 *
 * Пример колонок:
 * [
 *   [
 *     'key'    => 'title',
 *     'label'  => 'Публикация / контекст',
 *     'render' => fn(array $row): string => '...',
 *     // или
 *     'type'   => 'badge',          // text|badge|date|datetime|raw
 *     'map'    => [                 // для badge
 *       'spam' => ['label' => 'Спам', 'class' => 'badge-purple'],
 *     ],
 *     'class'  => 'col-title',      // доп. класс на <td>
 *     'headerClass' => '',          // доп. класс на <th>
 *   ],
 * ]
 *
 * Пример фильтров:
 * [
 *   ['name' => 'q', 'type' => 'search', 'placeholder' => 'Поиск...'],
 *   ['name' => 'status', 'type' => 'select', 'empty' => 'Все статусы', 'options' => ['new' => 'Новый']],
 *   ['name' => 'date', 'type' => 'date'],
 * ]
 */
class AdminTable
{
    private array $items;
    private array $columns;
    private ?array $selected;
    private string $baseUrl;
    private int $page;
    private int $total;
    private int $pages;
    private array $filters;          // текущие значения из query
    private array $filterConfig;     // описание фильтров
    private string $emptyText;
    private string $idKey;           // ключ id в строке (для selected и ссылок)
    private ?\Closure $rowClass;     // fn(array $row): string
    private string $tableClass;
    private bool $showActionsColumn; // добавлять ли колонку «···» автоматически

    public function __construct(
        array $items = [],
        array $columns = [],
        ?array $selected = null,
        string $baseUrl = '',
        int $page = 1,
        int $total = 0,
        int $pages = 1,
        array $filters = [],
        array $filterConfig = [],
        string $emptyText = 'Ничего не найдено',
        string $idKey = 'id',
        ?callable $rowClass = null,
        string $tableClass = 'logs-table',
        bool $showActionsColumn = true
    ) {
        $this->items            = $items;
        $this->columns          = $columns;
        $this->selected         = $selected;
        $this->baseUrl          = rtrim($baseUrl, '?&');
        $this->page             = max(1, $page);
        $this->total            = max(0, $total);
        $this->pages            = max(1, $pages);
        $this->filters          = $filters;
        $this->filterConfig     = $filterConfig;
        $this->emptyText        = $emptyText;
        $this->idKey            = $idKey;
        $this->rowClass         = $rowClass ? \Closure::fromCallable($rowClass) : null;
        $this->tableClass       = $tableClass;
        $this->showActionsColumn = $showActionsColumn;
    }

    public static function make(
        array $items = [],
        array $columns = [],
        ?array $selected = null,
        string $baseUrl = '',
        int $page = 1,
        int $total = 0,
        int $pages = 1,
        array $filters = [],
        array $filterConfig = [],
        string $emptyText = 'Ничего не найдено',
        string $idKey = 'id',
        ?callable $rowClass = null,
        string $tableClass = 'logs-table',
        bool $showActionsColumn = true
    ): self {
        return new self(
            $items,
            $columns,
            $selected,
            $baseUrl,
            $page,
            $total,
            $pages,
            $filters,
            $filterConfig,
            $emptyText,
            $idKey,
            $rowClass,
            $tableClass,
            $showActionsColumn
        );
    }

    /** Таблица + пагинация */
    public function render(): string
    {
        return $this->renderTable() . $this->renderPagination();
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /** HTML панели фильтров — вызывать над карточкой таблицы */
    public function filters(): string
    {
        if (empty($this->filterConfig)) {
            return '';
        }
        return $this->renderFilters();
    }

    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    private function renderFilters(): string
    {
        $actionUrl = htmlspecialchars(url($this->baseUrl), ENT_QUOTES, 'UTF-8');
        $parts     = [];

        foreach ($this->filterConfig as $cfg) {
            $name  = $cfg['name'] ?? '';
            $type  = $cfg['type'] ?? 'text';
            $value = (string)($this->filters[$name] ?? '');

            if ($type === 'search') {
                $placeholder = htmlspecialchars($cfg['placeholder'] ?? 'Поиск…', ENT_QUOTES, 'UTF-8');
                $val         = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $parts[]     = <<<HTML
        <div class="filter-search">
            <input
                type="search"
                name="{$name}"
                value="{$val}"
                placeholder="{$placeholder}"
                autocomplete="off"
                data-filter-search
            >
        </div>
HTML;
            } elseif ($type === 'date') {
                $dateValue = $value;
                if ($dateValue !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue) === 0) {
                    $ts = strtotime($dateValue);
                    $dateValue = $ts ? date('Y-m-d', $ts) : '';
                }
                $dateValue = htmlspecialchars($dateValue, ENT_QUOTES, 'UTF-8');
                $parts[] = <<<HTML
        <div class="filter-date">
            <input
                type="date"
                name="{$name}"
                value="{$dateValue}"
                title="Дата"
                data-filter-instant
            >
        </div>
HTML;
            } elseif ($type === 'select') {
                $emptyLabel = $cfg['empty'] ?? 'Все';
                $options    = $cfg['options'] ?? [];
                $optsHtml   = $this->buildSelectOptions($options, $value, $emptyLabel);
                $parts[]    = <<<HTML
        <div class="filter-select">
            <select name="{$name}" data-filter-instant>
                {$optsHtml}
            </select>
        </div>
HTML;
            }
        }

                // Сохраняем служебные параметры (sub и т.п.), которых нет в filterConfig
        $configNames = [];
        foreach ($this->filterConfig as $cfg) {
            if (!empty($cfg['name'])) {
                $configNames[$cfg['name']] = true;
            }
        }
        $hiddenParts = [];
        foreach ($this->filters as $fname => $fval) {
            if (isset($configNames[$fname])) {
                continue;
            }
            if ($fval === '' || $fval === null) {
                continue;
            }
            $hiddenParts[] = sprintf(
                '<input type="hidden" name="%s" value="%s">',
                htmlspecialchars((string)$fname, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string)$fval, ENT_QUOTES, 'UTF-8')
            );
        }
        $hiddenHtml = implode("\n", $hiddenParts);

        $filtersHtml = implode("\n", $parts);

        $resetParams = [];
        foreach ($this->filters as $fname => $fval) {
            if (!isset($configNames[$fname]) && $fval !== '' && $fval !== null) {
                $resetParams[$fname] = $fval;
            }
        }
        $resetQuery = $resetParams ? ('?' . http_build_query($resetParams)) : '';
        $resetHref  = htmlspecialchars(url($this->baseUrl) . $resetQuery, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<form class="logs-filters" method="get" action="{$actionUrl}" id="admin-table-filters" data-auto-submit>
    {$hiddenHtml}
    <div class="logs-filters-row">
        {$filtersHtml}
        <div class="filter-actions">
            <a href="{$resetHref}" class="btn btn-secondary">Сбросить</a>
        </div>
    </div>
</form>
<script>
(function () {
    var form = document.getElementById('admin-table-filters');
    if (!form || form.dataset.bound === '1') return;
    form.dataset.bound = '1';

    var timer = null;
    var DEBOUNCE_MS = 400;

    function submitFilters() {
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name) return;
            if (el.type === 'submit' || el.type === 'button') return;
            if (el.value === '' || el.value == null) {
                el.disabled = true;
            }
        });
        form.submit();
    }

    form.querySelectorAll('[data-filter-instant]').forEach(function (el) {
        el.addEventListener('change', submitFilters);
    });

    var search = form.querySelector('[data-filter-search]');
    if (search) {
        search.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(submitFilters, DEBOUNCE_MS);
        });
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(timer);
                submitFilters();
            }
        });
    }
})();
</script>
HTML;
    }

    /**
     * @param array $items
     * @param string $selected
     * @param string $emptyLabel
     */
    private function buildSelectOptions(array $items, string $selected, string $emptyLabel): string
    {
        $html = '<option value="">' . htmlspecialchars($emptyLabel, ENT_QUOTES, 'UTF-8') . '</option>';

        foreach ($items as $key => $item) {
            // Поддержка: ['value' => 'label'] или [['id'=>1,'name'=>'...']] или список строк
            if (is_array($item)) {
                $value = (string)($item['value'] ?? $item['id'] ?? $key);
                $label = (string)($item['label'] ?? $item['name'] ?? $value);
            } elseif (is_string($key) && !is_numeric($key)) {
                $value = (string)$key;
                $label = (string)$item;
            } else {
                $value = (string)$item;
                $label = (string)$item;
            }

            $isSelected = ($value === $selected) ? ' selected' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
                $isSelected,
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            );
        }

        return $html;
    }

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    private function renderTable(): string
    {
        if (empty($this->items)) {
            return '<p class="empty-state">' . htmlspecialchars($this->emptyText, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $headerCells = [];
        foreach ($this->columns as $col) {
            $label = htmlspecialchars($col['label'] ?? '', ENT_QUOTES, 'UTF-8');
            $cls   = !empty($col['headerClass']) ? ' class="' . htmlspecialchars($col['headerClass'], ENT_QUOTES, 'UTF-8') . '"' : '';
            $headerCells[] = "<th{$cls}>{$label}</th>";
        }
        if ($this->showActionsColumn) {
            $headerCells[] = '<th></th>';
        }

        $rows = [];
        foreach ($this->items as $row) {
            $isActive = false;
            if ($this->selected !== null) {
                $selId = $this->selected[$this->idKey] ?? null;
                $rowId = $row[$this->idKey] ?? null;
                $isActive = $selId !== null && $rowId !== null && (string)$selId === (string)$rowId;
            }

            $extraClass = '';
            if ($this->rowClass) {
                $extraClass = ' ' . trim(($this->rowClass)($row));
            }

            $cells = [];
            foreach ($this->columns as $col) {
                $cells[] = $this->renderCell($col, $row);
            }

            if ($this->showActionsColumn) {
                $id   = $row[$this->idKey] ?? 0;
                $path = $this->buildUrl(['id' => $id, 'page' => $this->page]);
                $href = htmlspecialchars(url($path), ENT_QUOTES, 'UTF-8');
                $cells[] = '<td><a href="' . $href . '" class="btn-icon">···</a></td>';
            }

            $rows[] = sprintf(
                '<tr class="%s">%s</tr>',
                ($isActive ? 'is-active' : '') . $extraClass,
                implode('', $cells)
            );
        }

        return sprintf(
            '<div class="table-wrapper">
                <table class="%s">
                    <thead>
                        <tr>%s</tr>
                    </thead>
                    <tbody>
                        %s
                    </tbody>
                </table>
            </div>',
            htmlspecialchars($this->tableClass, ENT_QUOTES, 'UTF-8'),
            implode('', $headerCells),
            implode("\n", $rows)
        );
    }

    private function renderCell(array $col, array $row): string
    {
        $key   = $col['key'] ?? '';
        $value = $row[$key] ?? null;
        $cls   = !empty($col['class']) ? ' class="' . htmlspecialchars($col['class'], ENT_QUOTES, 'UTF-8') . '"' : '';

        // Кастомный рендер имеет приоритет
        if (isset($col['render']) && is_callable($col['render'])) {
            $content = ($col['render'])($row, $value);
            return "<td{$cls}>{$content}</td>";
        }

        $type = $col['type'] ?? 'text';

        if ($type === 'badge') {
            $map   = $col['map'] ?? [];
            $raw   = (string)($value ?? '');
            $entry = $map[$raw] ?? null;

            if (is_array($entry)) {
                $label = $entry['label'] ?? $raw;
                $badgeClass = $entry['class'] ?? 'badge';
            } else {
                $label = $entry !== null ? (string)$entry : $raw;
                $badgeClass = 'badge';
            }

            $content = sprintf(
                '<span class="badge %s">%s</span>',
                htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            );
            return "<td{$cls}>{$content}</td>";
        }

        if ($type === 'date' || $type === 'datetime') {
            if (empty($value)) {
                $content = '—';
            } else {
                $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
                $fmt = $type === 'date' ? 'd.m.Y' : 'd.m.Y H:i';
                $content = $ts ? date($fmt, $ts) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            }
            return "<td{$cls}>{$content}</td>";
        }

        if ($type === 'raw') {
            return "<td{$cls}>" . (string)$value . "</td>";
        }

        // text (по умолчанию)
        $content = $value === null || $value === '' ? '—' : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        return "<td{$cls}>{$content}</td>";
    }

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------

    private function renderPagination(): string
    {
        if ($this->pages <= 1 && $this->total <= count($this->items)) {
            if ($this->total > 0) {
                return sprintf(
                    '<div class="pagination">
                        <span>Показано %d из %d</span>
                    </div>',
                    count($this->items),
                    $this->total
                );
            }
            return '';
        }

        $pagesHtml = '';
        for ($i = 1; $i <= $this->pages; $i++) {
            $url = url($this->buildUrl(['page' => $i]));
            $pagesHtml .= sprintf(
                '<a href="%s" class="%s">%d</a>',
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                $i === $this->page ? 'is-active' : '',
                $i
            );
        }

        return sprintf(
            '<div class="pagination">
                <span>Показано %d из %d · Страница %d из %d</span>
                <div class="pagination-pages">
                    %s
                </div>
            </div>',
            count($this->items),
            $this->total,
            $this->page,
            $this->pages,
            $pagesHtml
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Собирает relative path с query, сохраняя текущие фильтры
     * и перезаписывая переданные параметры.
     */
    private function buildUrl(array $override = []): string
    {
        $params = [];

        // Все текущие фильтры (включая служебные вроде sub)
        foreach ($this->filters as $name => $val) {
            if ($val !== null && $val !== '') {
                $params[$name] = $val;
            }
        }

        // page (только если > 1)
        if ($this->page > 1) {
            $params['page'] = $this->page;
        }

        // override
        foreach ($override as $k => $v) {
            if ($v === null || $v === '' || ($k === 'page' && (int)$v <= 1)) {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }

        if (empty($params)) {
            return $this->baseUrl;
        }

        return $this->baseUrl . '?' . http_build_query($params);
    }
}