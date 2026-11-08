<?php
namespace App\Components;

class Tabs
{
    private array $items;
    private string $active;
    private string $class;

    /**
     * @param array $items [
     *   [
     *     'id'    => 'overview',          // обязательно
     *     'title' => 'Обзор',
     *     'icon'  => '<svg>...</svg>',    // готовый SVG или null
     *     'href'  => '/moderation/overview', // опционально
     *   ],
     *   ...
     * ]
     */
    public function __construct(
        array $items,
        string $active = '',
        string $class = 'tabs'
    ) {
        $this->items  = $items;
        $this->active = $active;
        $this->class  = $class;
    }

    public function render(): string
    {
        if (empty($this->items)) {
            return '';
        }

        $html = [];

        foreach ($this->items as $item) {
            $id    = $item['id'] ?? '';
            $title = htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $icon  = $item['icon'] ?? '';
            $href  = $item['href'] ?? ('/moderation/' . $id);

            $isActive = ($id === $this->active);

            $classes = ['tab'];
            if ($isActive) {
                $classes[] = 'active';
            }

            $html[] = sprintf(
                '<a href="%s" class="%s">%s%s</a>',
                htmlspecialchars($href, ENT_QUOTES, 'UTF-8'),
                implode(' ', $classes),
                $icon,
                $title
            );
        }

        return sprintf(
            '<section class="%s">
                <div class="container">
                    <div class="tabs-grid">
                        %s
                    </div>
                </div>
            </section>',
            htmlspecialchars($this->class, ENT_QUOTES, 'UTF-8'),
            implode("\n", $html)
        );
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public static function make(array $items, string $active = '', string $class = 'tabs'): self
    {
        return new self($items, $active, $class);
    }
}