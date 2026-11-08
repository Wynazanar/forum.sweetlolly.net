<?php
namespace App\Components;

class Breadcrumbs
{
    private array $items;
    private string $class;
    private string $separator;

    public function __construct(
        array $items,
        string $class = 'breadcrumbs',
        string $separator = '/'
    ) {
        $this->items     = $items;
        $this->class     = $class;
        $this->separator = $separator;
    }

    public function render(): string
    {
        if (empty($this->items)) {
            return '';
        }

        $html = [];
        $lastIndex = count($this->items) - 1;

        foreach ($this->items as $index => $item) {
            $title = htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $href  = $item['href'] ?? null;

            // Последний элемент — текущая страница (не ссылка)
            if ($index === $lastIndex || empty($href)) {
                $html[] = '<span class="breadcrumb-current">' . $title . '</span>';
            } else {
                $href = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
                $html[] = '<a href="' . $href . '">' . $title . '</a>';
            }
        }

        $separator = '<span class="breadcrumb-separator">' . htmlspecialchars($this->separator, ENT_QUOTES, 'UTF-8') . '</span>';

        return sprintf(
            '<nav class="%s" aria-label="Навигация">%s</nav>',
            htmlspecialchars($this->class, ENT_QUOTES, 'UTF-8'),
            implode($separator, $html)
        );
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public static function make(array $items, string $class = 'breadcrumbs', string $separator = '/'): self
    {
        return new self($items, $class, $separator);
    }
}