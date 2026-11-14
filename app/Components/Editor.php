<?php
namespace App\Components;

/**
 * Универсальный редактор блоков (текст + сетка 1–3 фото).
 * mode: full | compact (ответ в теме)
 */
class Editor
{
    /**
     * @param array{
     *   name?: string,
     *   blocks?: array,
     *   placeholder?: string,
     *   id?: string,
     *   mode?: string
     * } $opts
     */
    public static function make(array $opts = []): string
    {
        $name = $opts['name'] ?? 'content_blocks';
        $blocks = $opts['blocks'] ?? [];
        if (!is_array($blocks)) {
            $blocks = [];
        }
        if ($blocks === []) {
            $blocks = [['type' => 'text', 'md' => '']];
        }
        $id = $opts['id'] ?? ('editor-' . bin2hex(random_bytes(4)));
        $placeholder = htmlspecialchars($opts['placeholder'] ?? 'Напишите текст…', ENT_QUOTES, 'UTF-8');
        $mode = ($opts['mode'] ?? 'full') === 'compact' ? 'compact' : 'full';
        $json = htmlspecialchars(json_encode($blocks, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $uploadUrl = htmlspecialchars(url('/attachments/upload'), ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
<link rel="stylesheet" href="<?= asset('styles/editor.css') ?>">
<div class="sl-editor sl-editor--<?= $mode ?>"
     id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
     data-name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
     data-mode="<?= $mode ?>"
     data-upload-url="<?= $uploadUrl ?>">
    <?php if ($mode === 'compact'): ?>
    <div class="sl-editor__toolbar sl-editor__toolbar--compact">
        <button type="button" class="sl-editor__btn" data-cmd="bold" title="Жирный"><b>B</b></button>
        <button type="button" class="sl-editor__btn" data-cmd="italic" title="Курсив"><i>I</i></button>
        <button type="button" class="sl-editor__btn" data-cmd="link" title="Ссылка">Ссылка</button>
        <button type="button" class="sl-editor__btn" data-cmd="add-image" title="Изображение">Изображение</button>
    </div>
    <?php else: ?>
    <div class="sl-editor__toolbar">
        <select class="sl-editor__block-type" title="Новый блок">
            <option value="text">Текст</option>
            <option value="images-1">1 фото</option>
            <option value="images-2">2 фото</option>
            <option value="images-3">3 фото</option>
        </select>
        <button type="button" class="sl-editor__btn" data-cmd="add-block">＋ Блок</button>
        <span class="sl-editor__sep"></span>
        <button type="button" class="sl-editor__btn" data-cmd="bold" title="Жирный">B</button>
        <button type="button" class="sl-editor__btn" data-cmd="italic" title="Курсив"><em>I</em></button>
        <button type="button" class="sl-editor__btn" data-cmd="link" title="Ссылка">🔗</button>
    </div>
    <?php endif; ?>
    <div class="sl-editor__blocks" data-placeholder="<?= $placeholder ?>"></div>
    <input type="hidden" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" class="sl-editor__input" value="<?= $json ?>">
    <?php if ($mode === 'compact'): ?>
    <div class="sl-editor__footer">
        <label class="sl-editor__attach">
            <input type="file" accept="image/png,image/jpeg,image/gif,image/webp" class="sl-editor__attach-input" hidden>
            📎 Прикрепить файл
        </label>
    </div>
    <?php endif; ?>
</div>
<script src="<?= asset('scripts/editor.js') ?>" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.SlEditor) {
        window.SlEditor.mount(document.getElementById(<?= json_encode($id) ?>));
    }
});
</script>
        <?php
        return (string)ob_get_clean();
    }
}
