<link rel="stylesheet" href="<?= asset('styles/admin.css'); ?>">
<main>
    <div class="container">
        <div class="page-header flex">
            <div>
                <h2><?= $mode === 'edit' 
                    ? 'Редактирование раздела'
                    : 'Создание раздела' ?>
                </h2>
                <p>Разделы / Новый раздел · Настройте место в структуре форума и права участников.</p>
            </div>
            <a href="<?= url('/moderation/sections') ?>" class="btn btn-secondary">← Назад</a>
        </div>

        <form method="post"
              action="<?= $mode === 'edit'
                  ? url('/moderation/sections/update/' . (int)$item['id'])
                  : url('/moderation/sections/store') ?>"
              class="section-form card">

            <div class="form-section">
                <h3>Название и расположение</h3>

                <div class="form-group">
                    <label>Название *</label>
                    <input type="text" name="title" required
                           value="<?= htmlspecialchars($item['title'] ?? '') ?>"
                           placeholder="Например: Идеи и предложения">
                </div>

                <div class="form-group">
                    <label>Описание</label>
                    <textarea name="description" rows="3"
                            placeholder="Краткое описание раздела"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Родительская категория *</label>
                        <select name="parent_id">
                            <option value="">— Верхний уровень (категория) —</option>
                            <?php foreach ($categoryOptions as $id => $title): ?>
                                <option value="<?= $id ?>"
                                    <?= (isset($parentId) && (int)$parentId === (int)$id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($title) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Адрес раздела · slug *</label>
                        <input type="text" name="slug"
                            value="<?= htmlspecialchars($item['slug'] ?? '') ?>"
                            placeholder="Оставьте пустым - сгенерируется автоматически">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Права и модерация</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Кто может просматривать</label>
                        <select name="access">
                            <?php foreach ($accessOptions as $val => $label): ?>
                                <option value="<?= $val ?>"
                                    <?= ($item['access'] ?? 'public') === $val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Кто может создавать темы</label>
                        <select name="can_create_topics">
                            <?php foreach ($audienceOptions as $val => $label): ?>
                                <option value="<?= $val ?>"
                                    <?= ($item['can_create_topics'] ?? 'players') === $val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Кто может отвечать</label>
                        <select name="can_reply">
                            <?php foreach ($audienceOptions as $val => $label): ?>
                                <option value="<?= $val ?>"
                                    <?= ($item['can_reply'] ?? 'players') === $val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Кто управляет разделом</label>
                        <select name="managed_by">
                            <?php foreach ($managedByOptions as $val => $label): ?>
                                <option value="<?= $val ?>"
                                    <?= ($item['managed_by'] ?? 'admins') === $val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Статус</label>
                    <select name="status">
                        <?php foreach ($statusOptions as $val => $label): ?>
                            <option value="<?= $val ?>"
                                <?= ($item['status'] ?? 'published') === $val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-toggles">
                    <label class="toggle">
                        <span>Проверять новые темы перед публикацией</span>
                        <input type="checkbox" name="moderate_topics" value="1"
                            <?= !empty($item['moderate_topics']) ? 'checked' : '' ?>>
                        <span class="switch"></span>
                    </label>

                    <label class="toggle">
                        <span>Разрешить вложения</span>
                        <input type="checkbox" name="allow_attachments" value="1"
                            <?= !empty($item['allow_attachments']) ? 'checked' : '' ?>>
                        <span class="switch"></span>
                    </label>

                    <label class="toggle">
                        <span>Разрешить гостям создавать сообщения</span>
                        <input type="checkbox" name="allow_guest_posts" value="1"
                            <?= !empty($item['allow_guest_posts']) ? 'checked' : '' ?>>
                        <span class="switch"></span>
                    </label>
                </div>
            </div>


            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $mode === 'edit' ? 'Сохранить изменения' : 'Создать раздел' ?>
                </button>
                <a href="<?= url('/moderation/sections') ?>" class="btn btn-outline">Отмена</a>
            </div>
        </form>
    </div>
</main>