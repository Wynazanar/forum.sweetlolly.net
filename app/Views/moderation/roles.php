<?php
$roles = $roles ?? [];
$roleModel = new \App\Models\Role();
?>

<main>
    <div class="container">
        <div class="page-header flex">
            <div>
                <h2>Роли</h2>
                <p>Права доступа: кто что может на форуме, в модерации и в заявках.</p>
            </div>
            <a href="<?= url('/moderation/roles/create') ?>" class="btn btn-primary">＋ Создать роль</a>
        </div>

        <div class="logs-table-card">
            <div class="logs-table-header">
                <h3>Список ролей</h3>
                <span class="logs-count"><?= count($roles) ?></span>
            </div>

            <?php if (empty($roles)): ?>
                <div style="padding:24px;" class="text-muted">Ролей нет.</div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Системное имя</th>
                            <th>Права</th>
                            <th>Игроков</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role):
                            $perms = $role['permissions'] ?? [];
                            $enabled = array_keys(array_filter($perms));
                            $isSystem = in_array($role['name'], ['user', 'moderator', 'admin'], true);
                        ?>
                        <tr>
                            <td><?= (int)$role['id'] ?></td>
                            <td><strong><?= htmlspecialchars($roleModel->label($role['name'])) ?></strong></td>
                            <td><code><?= htmlspecialchars($role['name']) ?></code></td>
                            <td>
                                <span class="badge badge-info"><?= count($enabled) ?></span>
                                <span class="text-muted" style="font-size:12px;">
                                    <?= htmlspecialchars(implode(', ', array_slice($enabled, 0, 4))) ?>
                                    <?= count($enabled) > 4 ? '…' : '' ?>
                                </span>
                            </td>
                            <td><?= (int)($role['users_count'] ?? 0) ?></td>
                            <td style="white-space:nowrap;">
                                <a href="<?= url('/moderation/roles/edit/' . (int)$role['id']) ?>" class="btn btn-outline btn-sm">Изменить</a>
                                <?php if (!$isSystem): ?>
                                <form method="post" action="<?= url('/moderation/roles/delete/' . (int)$role['id']) ?>"
                                      style="display:inline;"
                                      onsubmit="return confirm('Удалить роль?');">
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
