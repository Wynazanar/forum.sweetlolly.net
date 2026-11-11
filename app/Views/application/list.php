<?php
$forms = $forms ?? [];
$user = $user ?? null;
?>
<main class="app-page">
    <div class="container">
        <h1>Заявки</h1>
        <p class="lead">Открытые анкеты: в команду, апелляции и другие формы.</p>
    
        <?php if (empty($forms)): ?>
            <div class="app-empty">Сейчас нет открытых заявок.</div>
        <?php else: ?>
            <div class="app-list">
                <?php foreach ($forms as $f): ?>
                    <a class="app-item" href="<?= url('/application/' . (int)$f['id']) ?>">
                        <h2><?= htmlspecialchars($f['name'] ?? '') ?></h2>
                        <?php if (!empty($f['description'])): ?>
                            <p><?= htmlspecialchars(mb_strimwidth($f['description'], 0, 160, '…')) ?></p>
                        <?php endif; ?>
                        <div class="meta">
                            <?php
                            $type = $f['application_type'] ?? '';
                            $typeLabel = match ($type) {
                                'team' => 'В команду',
                                'unban' => 'Апелляция / Разбан',
                                'other' => 'Другое',
                                default => $type ?: 'Анкета',
                            };
                            echo htmlspecialchars($typeLabel);
                            $qc = is_array($f['questions'] ?? null) ? count($f['questions']) : 0;
                            echo ' · ' . $qc . ' вопр.';
                            ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
