<?php
use App\Components\Editor;

$mode = $mode ?? 'create';
$item = $item ?? [];
$errors = $errors ?? [];
$categoryOptions = $categoryOptions ?? [];
$user = $user ?? \App\Services\AuthService::user();
$blocks = $item['content_blocks'] ?? [];
if (is_string($blocks)) {
    $blocks = json_decode($blocks, true) ?: [];
}
if (!$blocks && !empty($item['content'])) {
    $blocks = [['type' => 'text', 'md' => $item['content']]];
}
$status = $item['status'] ?? 'draft';
?>
<link rel="stylesheet" href="<?= asset('styles/admin.css'); ?>">
<main>
<div class="container">
    <div class="page-header flex">
        <div>
            <h2><?= $mode === 'edit' ? 'Редактирование темы' : 'Создание темы' ?></h2>
            <p>Тема задаёт основной материал; ответы появятся в обсуждении.</p>
        </div>
        <span class="text-muted" style="font-size:13px;">
            <?= $status === 'draft' ? 'Черновик' : ($status === 'pending' ? 'На проверке' : 'Публикация') ?>
        </span>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger" style="margin-bottom:16px;">
            <ul style="margin:0;padding-left:18px;">
                <?php foreach ($errors as $msg): ?>
                    <li><?= htmlspecialchars(is_array($msg) ? implode(', ', $msg) : (string)$msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post"
          action="<?= $mode === 'edit'
              ? url('/moderation/topics/update/' . (int)($item['id'] ?? 0))
              : url('/moderation/topics/store') ?>"
          class="topic-form-layout">
        <div class="topic-form-main">
            <div class="logs-table-card" style="padding:20px;margin-bottom:16px;">
                <h3 style="margin-top:0;">Основная публикация</h3>
                <div class="form-group">
                    <label>Заголовок темы *</label>
                    <input type="text" name="title" required
                           value="<?= htmlspecialchars($item['title'] ?? '') ?>"
                           placeholder="Название темы">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>Раздел *</label>
                        <select name="category_id" required>
                            <option value="">— Выберите —</option>
                            <?php foreach ($categoryOptions as $cid => $ctitle): ?>
                                <option value="<?= (int)$cid ?>" <?= (int)($item['category_id'] ?? 0) === (int)$cid ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ctitle) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Автор</label>
                        <input type="text" disabled
                               value="<?= htmlspecialchars(($user['username'] ?? '') . ' · ' . ($user['role_name'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Текст темы *</label>
                    <?= Editor::make([
                        'name'        => 'content_blocks',
                        'blocks'      => $blocks,
                        'placeholder' => 'Напишите текст… Можно добавить блоки с 1–3 фото.',
                    ]) ?>
                </div>
            </div>
            <div class="logs-table-card" style="padding:16px;">
                <h3 style="margin-top:0;">Вложения</h3>
                <p class="text-muted" style="font-size:13px;margin:0;">
                    Нажмите на слот фото, чтобы загрузить изображение (png/jpg/webp, до 8 МБ).
                </p>
            </div>
        </div>

        <aside class="topic-form-side">
            <div class="logs-table-card" style="padding:16px;margin-bottom:16px;">
                <h3 style="margin-top:0;">Публикация</h3>
                <div class="form-toggles" style="display:flex;flex-direction:column;gap:10px;margin:12px 0;">
                    <label class="toggle"><span>Закрепить тему</span>
                        <input type="checkbox" name="is_sticky" value="1" <?= !empty($item['is_sticky']) ? 'checked' : '' ?>>
                    </label>
                    <label class="toggle"><span>Закрыть ответы</span>
                        <input type="checkbox" name="is_locked" value="1" <?= !empty($item['is_locked']) ? 'checked' : '' ?>>
                    </label>
                    <label class="toggle"><span>Скрыта</span>
                        <input type="checkbox" name="is_hidden" value="1" <?= !empty($item['is_hidden']) ? 'checked' : '' ?>>
                    </label>
                </div>
                <p style="font-size:13px;">Статус:
                    <strong><?= htmlspecialchars($status) ?></strong>
                </p>
                <button type="submit" name="action" value="publish" class="btn btn-primary" style="width:100%;margin-bottom:8px;">
                    Опубликовать тему
                </button>
                <button type="submit" name="action" value="draft" class="btn btn-outline" style="width:100%;margin-bottom:8px;">
                    Сохранить черновик
                </button>
                <a href="<?= url('/moderation/topics') ?>" class="btn btn-outline" style="width:100%;text-align:center;">Отмена</a>
            </div>
            <div class="logs-table-card" style="padding:16px;">
                <h3 style="margin-top:0;">Перед публикацией</h3>
                <p class="text-muted" style="font-size:13px;">
                    Черновик виден только вам. Автопроверка слов/ссылок и очередь модерации — следующие этапы.
                </p>
            </div>
        </aside>
    </form>
</div>
</main>
<style>
.topic-form-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 20px;
  align-items: start;
}
@media (max-width: 960px) {
  .topic-form-layout { grid-template-columns: 1fr; }
}
</style>
