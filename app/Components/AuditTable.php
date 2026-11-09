<?php
namespace App\Components;

/**
 * Компонент таблицы аудита с фильтрами и пагинацией.
 *
 * Ожидаемые ключи в $items (каждый лог):
 *   id, created_at, admin_name, action, object_title|target_type, result
 *
 * $filters — текущие значения фильтров (из query):
 *   q, date, admin_id, action, result
 *
 * $filterOptions — справочники для селектов:
 *   admins  => [['id' => 1, 'name' => '...'], ...]
 *   actions => ['ban_user' => 'Блокировка', ...]  или просто список строк
 *   results => ['success' => 'Успешно', 'denied' => 'Отказано', 'error' => 'Ошибка']
 */
class AuditTable
{
    private array $items;
    private ?array $selected;
    private string $baseUrl;
    private int $page;
    private int $total;
    private int $pages;
    private array $filters;
    private array $filterOptions;

    public function __construct(
        array $items,
        ?array $selected = null,
        string $baseUrl = '/moderation/logs',
        int $page = 1,
        int $total = 0,
        int $pages = 1,
        array $filters = [],
        array $filterOptions = []
    ) {
        $this->items         = $items;
        $this->selected      = $selected;
        $this->baseUrl       = rtrim($baseUrl, '?&');
        $this->page          = max(1, $page);
        $this->total         = max(0, $total);
        $this->pages         = max(1, $pages);
        $this->filters       = $filters;
        $this->filterOptions = $filterOptions;
    }

    /**
     * Таблица + пагинация (без фильтров).
     * Фильтры выводятся отдельно через filters() — над карточкой.
     */
    public function render(): string
    {
        return $this->renderTable()
             . $this->renderPagination();
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /** HTML панели фильтров — вызывать над карточкой таблицы */
    public function filters(): string
    {
        return $this->renderFilters();
    }

    public static function make(
        array $items,
        ?array $selected = null,
        string $baseUrl = '/moderation/logs',
        int $page = 1,
        int $total = 0,
        int $pages = 1,
        array $filters = [],
        array $filterOptions = []
    ): self {
        return new self($items, $selected, $baseUrl, $page, $total, $pages, $filters, $filterOptions);
    }

    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    private function renderFilters(): string
    {
        $q          = htmlspecialchars((string)($this->filters['q'] ?? ''), ENT_QUOTES, 'UTF-8');
        $date       = htmlspecialchars((string)($this->filters['date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $adminId    = (string)($this->filters['admin_id'] ?? '');
        $action     = (string)($this->filters['action'] ?? '');
        $result     = (string)($this->filters['result'] ?? '');
        $role       = (string)($this->filters['role'] ?? '');

        $admins  = $this->filterOptions['admins']  ?? [];
        $actions = $this->filterOptions['actions'] ?? [];
        $roles   = $this->filterOptions['roles']   ?? [];
        $results = $this->filterOptions['results'] ?? [
            'success' => 'Успешно',
            'denied'  => 'Отказано',
            'error'   => 'Ошибка',
        ];

        $adminOptions = $this->buildSelectOptions(
            $admins,
            $adminId,
            'Все участники',
            static function ($item) {
                $name = $item['name'] ?? ('ID ' . ($item['id'] ?? ''));
                $role = $item['role_label'] ?? '';
                $label = $role !== '' ? $name . ' · ' . $role : $name;
                return [(string)$item['id'], $label];
            }
        );

        $roleOptions = $this->buildSelectOptions(
            $roles,
            $role,
            'Все роли',
            static function ($item, $key) {
                if (is_string($key) && !is_numeric($key)) {
                    return [(string)$key, (string)$item];
                }
                return [(string)$item, (string)$item];
            }
        );

        // actions может быть assoc [value => label] или list of strings
        $actionOptions = $this->buildSelectOptions(
            $actions,
            $action,
            'Все события',
            static function ($item, $key) {
                if (is_array($item)) {
                    return [(string)($item['value'] ?? $item['id'] ?? $key), $item['label'] ?? $item['name'] ?? (string)$key];
                }
                // assoc: key = value, item = label
                if (is_string($key) && !is_numeric($key)) {
                    return [(string)$key, (string)$item];
                }
                return [(string)$item, (string)$item];
            }
        );

        $resultOptions = $this->buildSelectOptions(
            $results,
            $result,
            'Любой результат',
            static function ($item, $key) {
                if (is_string($key) && !is_numeric($key)) {
                    return [(string)$key, (string)$item];
                }
                return [(string)$item, (string)$item];
            }
        );

        // date input: если пришла дата в Y-m-d — показываем как есть (браузер сам отформатирует)
        $dateValue = $date;
        if ($dateValue !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue) === 0) {
            // попробуем распарсить «8 ноября 2026» и т.п. — оставляем как есть, type=date требует Y-m-d
            $ts = strtotime($dateValue);
            $dateValue = $ts ? date('Y-m-d', $ts) : '';
        }

        $actionUrl = htmlspecialchars(url($this->baseUrl), ENT_QUOTES, 'UTF-8');

        // Кнопка «Применить» не нужна — фильтры применяются сами (JS ниже)
        return <<<HTML
<form class="logs-filters" method="get" action="{$actionUrl}" id="logs-filters-form" data-auto-submit>
    <div class="logs-filters-row">
        <div class="filter-search">
            <input
                type="search"
                name="q"
                value="{$q}"
                placeholder="Событие, объект или ID"
                autocomplete="off"
                data-filter-search
            >
        </div>

        <div class="filter-date">
            <input
                type="date"
                name="date"
                value="{$dateValue}"
                title="Дата"
                data-filter-instant
            >
        </div>

        <div class="filter-select">
            <select name="admin_id" data-filter-instant>
                {$adminOptions}
            </select>
        </div>

        <div class="filter-select">
            <select name="role" data-filter-instant>
                {$roleOptions}
            </select>
        </div>

        <div class="filter-select">
            <select name="action" data-filter-instant>
                {$actionOptions}
            </select>
        </div>

        <div class="filter-select">
            <select name="result" data-filter-instant>
                {$resultOptions}
            </select>
        </div>

        <div class="filter-actions">
            <a href="{$actionUrl}" class="btn btn-secondary">Сбросить</a>
        </div>
    </div>
</form>
<script>
(function () {
    var form = document.getElementById('logs-filters-form');
    if (!form || form.dataset.bound === '1') return;
    form.dataset.bound = '1';

    var timer = null;
    var DEBOUNCE_MS = 400;

    function submitFilters() {
        // убираем пустые поля, чтобы URL был чище
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
        // Enter — сразу
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
     * @param callable $mapper  fn($item, $key): [value, label]
     */
    private function buildSelectOptions(array $items, string $selected, string $emptyLabel, callable $mapper): string
    {
        $html = '<option value="">' . htmlspecialchars($emptyLabel, ENT_QUOTES, 'UTF-8') . '</option>';

        foreach ($items as $key => $item) {
            [$value, $label] = $mapper($item, $key);
            $isSelected = ((string)$value === $selected) ? ' selected' : '';
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'),
                $isSelected,
                htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8')
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
            return '<p class="empty-state">Логов пока нет</p>';
        }

        $rows = [];

        foreach ($this->items as $log) {
            $isActive = $this->selected && (int)$this->selected['id'] === (int)$log['id'];
            $time     = date('H:i:s', strtotime($log['created_at']));

            $result = $log['result'] ?? 'success';
            $resultClass = match ($result) {
                'success' => 'badge-success',
                'denied'  => 'badge-danger',
                'error'   => 'badge-warning',
                default   => 'badge-success',
            };
            $resultText = match ($result) {
                'success' => 'Успешно',
                'denied'  => 'Отказано',
                'error'   => 'Ошибка',
                default   => 'Успешно',
            };

            $actorName = $log['actor_name'] ?? $log['admin_name'] ?? '—';
            $roleLabel = $log['role_label'] ?? '';
            $admin  = htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8');
            if ($roleLabel !== '') {
                $admin .= '<br><span class="log-role-tag log-role-' . htmlspecialchars($log['role_name'] ?? 'unknown', ENT_QUOTES, 'UTF-8') . '">'
                       . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            $action = htmlspecialchars($log['action_label'] ?? $log['action'] ?? '', ENT_QUOTES, 'UTF-8');
            $object = htmlspecialchars($log['object_title'] ?? $log['target_type'] ?? '—', ENT_QUOTES, 'UTF-8');

            // Сохраняем текущие фильтры в ссылке на деталь
            $path = $this->buildUrl(['id' => (int)$log['id'], 'page' => $this->page]);
            $href = url($path);

            $rows[] = sprintf(
                '<tr class="%s">
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td><span class="badge %s">%s</span></td>
                    <td><a href="%s" class="btn-icon">···</a></td>
                </tr>',
                $isActive ? 'is-active' : '',
                $time,
                $admin,
                $action,
                $object,
                $resultClass,
                $resultText,
                htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
            );
        }

        return sprintf(
            '<div class="table-wrapper">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Время</th>
                            <th>Участник</th>
                            <th>Событие</th>
                            <th>Объект</th>
                            <th>Результат</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        %s
                    </tbody>
                </table>
            </div>',
            implode("\n", $rows)
        );
    }

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------

    private function renderPagination(): string
    {
        if ($this->pages <= 1 && $this->total <= count($this->items)) {
            // Даже при одной странице можно показать «Показано N из M», если total известен
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
        $params = array_filter([
            'q'        => $this->filters['q']        ?? null,
            'date'     => $this->filters['date']     ?? null,
            'admin_id' => $this->filters['admin_id'] ?? null,
            'action'   => $this->filters['action']   ?? null,
            'result'   => $this->filters['result']   ?? null,
            'page'     => $this->page > 1 ? $this->page : null,
        ], static fn($v) => $v !== null && $v !== '');

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