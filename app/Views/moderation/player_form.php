<?php
$mode = $mode ?? 'create';
$item = $item ?? [];
$errors = $errors ?? [];
$roleOptions = $roleOptions ?? [];
$canEditRole = $canEditRole ?? true;
$canEdit = $canEdit ?? true;
?>
<link rel="stylesheet" href="<?= asset('styles/admin.css'); ?>">
<main>
<div class="container">
    <div class="page-header flex">
        <div>
            <h2><?= $mode === 'edit' ? 'Редактирование игрока' : 'Создание игрока' ?></h2>
            <p>Учётная запись форума</p>
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
              ? url('/moderation/players/update/' . (int)($item['id'] ?? 0))
              : url('/moderation/players/store') ?>"
          class="display-layout">
        <div class="section-form">
            <div class="form-section">
                <div class="form-group">
                    <label>Никнейм *</label>
                    <input type="text" name="username" required
                           value="<?= htmlspecialchars($item['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($item['email'] ?? '') ?>">
                </div>
                <?php if ($mode === 'create'): ?>
                <div class="form-group">
                    <label>Пароль *</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <?php endif; ?>
                <?php if ($canEditRole): ?>
                <div class="form-group">
                    <label>Роль</label>
                    <select name="role_id">
                        <?php foreach ($roleOptions as $rid => $label): ?>
                            <option value="<?= (int)$rid ?>"
                                <?= (int)($item['role_id'] ?? 1) === (int)$rid ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-actions">
            <div class="form-action-btn">
                <button type="submit" class="btn-form-primary">Сохранить</button>
                <a href="<?= url('/moderation/players') . (!empty($item['id']) ? '?id='.(int)$item['id'] : '') ?>"
                   class="btn-form-secondary">Отмена</a>
            </div>
        </div>
    </form>
</div>
</main>
