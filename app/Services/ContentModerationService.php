<?php
namespace App\Services;

/**
 * Автопроверка текста/блоков перед публикацией.
 */
class ContentModerationService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        if ($config !== null) {
            $this->config = $config;
            return;
        }
        $path = dirname(__DIR__) . '/Config/moderation_filters.php';
        $this->config = is_file($path) ? (require $path) : ['enabled' => false];
    }

    /**
     * @param array $blocks content_blocks
     * @return array{ok: bool, reasons: string[]}  ok=true — можно published
     */
    public function checkBlocks(array $blocks): array
    {
        $text = '';
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'text') {
                $text .= ' ' . ($b['md'] ?? '');
            }
            if (($b['type'] ?? '') === 'images') {
                foreach ($b['items'] ?? [] as $it) {
                    if (!empty($it['href'])) {
                        $text .= ' ' . $it['href'];
                    }
                }
            }
        }
        return $this->checkText($text);
    }

    /**
     * @return array{ok: bool, reasons: string[]}
     */
    public function checkText(string $text): array
    {
        if (empty($this->config['enabled'])) {
            return ['ok' => true, 'reasons' => []];
        }

        $reasons = [];
        $lower = mb_strtolower($text);

        foreach ($this->config['stop_words'] ?? [] as $word) {
            $word = trim((string)$word);
            if ($word === '') {
                continue;
            }
            if (mb_strpos($lower, mb_strtolower($word)) !== false) {
                $reasons[] = 'Стоп-слово: «' . $word . '»';
            }
        }

        foreach ($this->config['bad_link_parts'] ?? [] as $part) {
            $part = trim((string)$part);
            if ($part === '') {
                continue;
            }
            if (mb_strpos($lower, mb_strtolower($part)) !== false) {
                $reasons[] = 'Подозрительная ссылка: «' . $part . '»';
            }
        }

        $maxLinks = (int)($this->config['max_links'] ?? 0);
        if ($maxLinks > 0) {
            preg_match_all('~https?://[^\s<>"\']++~iu', $text, $m);
            $count = count($m[0] ?? []);
            if ($count > $maxLinks) {
                $reasons[] = 'Слишком много ссылок: ' . $count . ' (лимит ' . $maxLinks . ')';
            }
        }

        $reasons = array_values(array_unique($reasons));
        return [
            'ok'      => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * Итог статус для публикации (не трогает draft).
     */
    public function resolvePublishStatus(array $blocks, bool $canBypass): string
    {
        if ($canBypass) {
            return 'published';
        }
        $check = $this->checkBlocks($blocks);
        return $check['ok'] ? 'published' : 'pending';
    }
}
