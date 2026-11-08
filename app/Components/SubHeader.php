<?php
namespace App\Components;

class SubHeader
{
    private array $items;
    private string $leftText;
    private string $class;

    public function __construct(
        array $items = [],
        string $leftText = 'Твой следующий мир начинается здесь ✦',
        string $class = 'sub-header'
    ) {
        $this->items    = $items;
        $this->leftText = $leftText;
        $this->class    = $class;
    }

    public function render(): string
    {
        $linksHtml = '';

        foreach ($this->items as $item) {
            $title  = htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $href   = htmlspecialchars($item['href'] ?? '#', ENT_QUOTES, 'UTF-8');
            $active = !empty($item['active']) ? ' is-active' : '';

            $linksHtml .= sprintf(
                '<a href="%s" class="sub-header-link%s">%s</a>',
                $href,
                $active,
                $title
            );
        }

        return sprintf(
            '<div class="%s">
                <div class="container sub-header-inner">
                    <div class="sub-header-left">%s</div>
                    <nav class="sub-header-nav">%s</nav>
                </div>
            </div>',
            htmlspecialchars($this->class, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->leftText, ENT_QUOTES, 'UTF-8'),
            $linksHtml
        );
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public static function make(
        array $items = [],
        string $leftText = 'Твой следующий мир начинается здесь ✦',
        string $class = 'sub-header'
    ): self {
        return new self($items, $leftText, $class);
    }
}