<?php
$mode = $mode ?? 'create';
$item = $item ?? [];
$catalog = $catalog ?? [];
$errors = $errors ?? [];
$perms = $item['permissions'] ?? [];
if (!is_array($perms)) $perms = [];
$isSystem = $mode === 'edit' && in_array($item['name'] ?? '', ['user', 'moderator', 'admin'], true);
?>
<link rel="stylesheet" href="<?= asset('styles/admin.css'); ?>">
<main>
    <div class="container">
        <div class="page-header flex">
            <div>
                <h2><?= $mode === 'edit' ? 'Редактирование роли' : 'Создание роли' ?></h2>
                <p>Отметьте права. Хелпер может только смотреть заявки, модератор — ещё принимать.</p>
            </div>
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
                  ? url('/moderation/roles/update/' . (int)($item['id'] ?? 0))
                  : url('/moderation/roles/store') ?>"
              class="display-layout">

            <div class="section-form">
                <div class="form-section">
                    <h3>Имя роли</h3>
                    <div class="form-group">
                        <label>Системное имя * <span class="text-muted">(латиница, например helper)</span></label>
                        <input type="text" name="name" required
                               value="<?= htmlspecialchars($item['name'] ?? '') ?>"
                               <?= $isSystem ? 'readonly' : '' ?>
                               placeholder="helper">
                    </div>
                </div>

                <?php foreach ($catalog as $groupKey => $group): ?>
                <div class="form-section">
                    <h3><?= htmlspecialchars($group['label'] ?? $groupKey) ?></h3>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));column-gap:25px;row-gap:10px;">
                        <?php foreach (($group['permissions'] ?? []) as $key => $label):
                            $checked = !empty($perms[$key]);
                        ?>
                            <label class="toggle">
                                <span>
                                    <strong style="font-size:13px;"><?= htmlspecialchars($label) ?></strong>
                                    <div class="text-muted" style="font-size:11px;"><code><?= htmlspecialchars($key) ?></code></div>
                                </span>
                                <input type="checkbox" name="permissions[<?= htmlspecialchars($key) ?>]" value="1"
                                    <?= $checked ? 'checked' : '' ?> style="margin-top:3px;">
                                <span class="switch"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="form-actions">
                <h3>Сохранение</h3>
                <div class="form-action-btn">
                    <button type="submit" class="btn-form-primary"><?= $mode === 'edit' ? 'Сохранить' : 'Создать роль' ?></button>
                    <a href="<?= url('/moderation/roles') ?>" class="btn-form-secondary">Отмена</a>
                </div>
            </div>
        </form>
    </div>
</main>
