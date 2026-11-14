<?php
namespace App\Services;

/**
 * Блоки контента темы/поста.
 *
 * text:   { "type":"text", "md":"..." }
 * images: { "type":"images", "layout":"1"|"2"|"3", "items":[ {attachment_id, href?} ] }
 */
class ContentBlocksService
{
    /**
     * Нормализация из POST (editor payload JSON string or array)
     */
    public function normalize(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = $block['type'] ?? '';
            if ($type === 'text') {
                $md = trim((string)($block['md'] ?? ''));
                if ($md === '') {
                    continue;
                }
                $out[] = ['type' => 'text', 'md' => $md];
            } elseif ($type === 'images') {
                $layout = (string)($block['layout'] ?? '1');
                if (!in_array($layout, ['1', '2', '3'], true)) {
                    $layout = '1';
                }
                $items = [];
                foreach (($block['items'] ?? []) as $it) {
                    if (!is_array($it)) {
                        continue;
                    }
                    $aid = (int)($it['attachment_id'] ?? 0);
                    if ($aid <= 0) {
                        continue;
                    }
                    $items[] = [
                        'attachment_id' => $aid,
                        'href'          => isset($it['href']) && $it['href'] !== '' ? (string)$it['href'] : null,
                    ];
                }
                $max = (int)$layout;
                $items = array_slice($items, 0, $max);
                if ($items === []) {
                    continue;
                }
                // если фото меньше layout — подрезаем layout
                $layout = (string)min($max, count($items));
                $out[] = ['type' => 'images', 'layout' => $layout, 'items' => $items];
            }
        }
        return $out;
    }

    /**
     * Простой безопасный HTML для показа (этап 1: без upload — images-заглушки)
     */
    public function toHtml(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'text') {
                $md = htmlspecialchars($block['md'] ?? '', ENT_QUOTES, 'UTF-8');
                $md = nl2br($md);
                // простой bold/italic/link markdown-lite
                $md = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $md) ?? $md;
                $md = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $md) ?? $md;
                $md = preg_replace(
                    '/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/',
                    '<a href="$2" rel="nofollow noopener" target="_blank">$1</a>',
                    $md
                ) ?? $md;
                $html .= '<div class="content-block content-block--text"><p>' . $md . '</p></div>';
            } elseif (($block['type'] ?? '') === 'images') {
                $layout = htmlspecialchars($block['layout'] ?? '1', ENT_QUOTES, 'UTF-8');
                $html .= '<div class="content-block content-block--images layout-' . $layout . '">';
                foreach ($block['items'] as $it) {
                    $aid = (int)($it['attachment_id'] ?? 0);
                    $href = $it['href'] ?? null;
                    $src = $it['url'] ?? '';
                    if ($src === '' && $aid > 0 && function_exists('url')) {
                        $src = url('attachments/' . $aid . '/download');
                    }
                    if ($src !== '') {
                        $img = '<img class="content-block__img" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="">';
                    } else {
                        $img = '<div class="content-block__img-ph">📷</div>';
                    }
                    if ($href) {
                        $html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="nofollow noopener">' . $img . '</a>';
                    } else {
                        $html .= $img;
                    }
                }
                $html .= '</div>';
            }
        }
        return $html;
    }

    /**
     * Из обычного текста (старые посты / простой submit) — один text-блок
     */
    public function fromPlainText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        return [['type' => 'text', 'md' => $text]];
    }
}
