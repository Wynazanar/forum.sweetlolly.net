<?php
$layouts = $layouts ?? [];
?>
<link rel="stylesheet" href="<?= asset('styles/admin.css'); ?>">
<main>
    <div class="container">
        <div class="page-header flex">
            <div>
                <h2>Макеты заявок</h2>
                <p>Сетки для расположения вопросов в анкете. Можно выбрать макет при создании шаблона.</p>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="<?= url('/moderation/applications?sub=forms') ?>" class="btn btn-outline">← К шаблонам</a>
                <a href="<?= url('/moderation/applications/layouts/create') ?>" class="btn btn-primary">＋ Создать макет</a>
            </div>
        </div>

        <div class="logs-table-card">
            <div class="logs-table-header">
                <h3>Список макетов</h3>
                <span class="logs-count"><?= count($layouts) ?></span>
            </div>

            <?php if (empty($layouts)): ?>
                <div style="padding:24px;" class="text-muted">Макетов пока нет. Создайте первый — или используйте сетку по умолчанию.</div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Название</th>
                            <th>Slug</th>
                            <th>Колонки</th>
                            <th>Отступ</th>
                            <th>Системный</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($layouts as $layout): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($layout['name'] ?? '') ?></strong>
                                <?php if (!empty($layout['description'])): ?>
                                    <div class="text-muted section-desc"><?= htmlspecialchars($layout['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><code><?= htmlspecialchars($layout['slug'] ?? '') ?></code></td>
                            <td><?= (int)($layout['columns_count'] ?? 12) ?></td>
                            <td><?= (int)($layout['gap'] ?? 16) ?>px</td>
                            <td>
                                <?= !empty($layout['is_system'])
                                    ? '<span class="badge badge-info">да</span>'
                                    : '<span class="text-muted">нет</span>' ?>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="<?= url('/moderation/applications/layouts/edit/' . (int)$layout['id']) ?>" class="btn btn-outline btn-sm">Изменить</a>
                                <?php if (empty($layout['is_system'])): ?>
                                <form method="post"
                                      action="<?= url('/moderation/applications/layouts/delete/' . (int)$layout['id']) ?>"
                                      style="display:inline;"
                                      onsubmit="return confirm('Удалить макет?');">
                                    <button type="submit" class="btn btn-outline btn-danger btn-sm">Удалить</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>
