<?php
namespace App\Components;

class AuditTable
{
    private array $items;
    private ?array $selected;
    private string $baseUrl;
    private int $page;

    public function __construct(
        array $items,
        ?array $selected = null,
        string $baseUrl = '/moderation/logs',
        int $page = 1
    ) {
        $this->items    = $items;
        $this->selected = $selected;
        $this->baseUrl  = $baseUrl;
        $this->page     = $page;
    }

    public function render(): string
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

            $admin  = htmlspecialchars($log['admin_name'] ?? '—', ENT_QUOTES, 'UTF-8');
            $action = htmlspecialchars($log['action'] ?? '', ENT_QUOTES, 'UTF-8');
            $object = htmlspecialchars($log['object_title'] ?? $log['target_type'] ?? '—', ENT_QUOTES, 'UTF-8');

            // Формируем относительный путь, а url() сделает полный
            $path = $this->baseUrl . '?id=' . (int)$log['id'] . '&page=' . $this->page;
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
                            <th>Администратор</th>
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

    public function __toString(): string
    {
        return $this->render();
    }

    public static function make(
        array $items,
        ?array $selected = null,
        string $baseUrl = '/moderation/logs',
        int $page = 1
    ): self {
        return new self($items, $selected, $baseUrl, $page);
    }
}