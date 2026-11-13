<?php
$forms = $forms ?? [];
$user = $user ?? null;
?>
<style>
.app-page { max-width: 900px; margin: 0 auto; padding: 24px 16px 48px; }
.app-page h1 { margin: 0 0 8px; font-size: 1.6rem; }
.app-page .lead { color: var(--muted, #999); margin-bottom: 24px; }
.app-list { display: grid; gap: 12px; }
.app-item {
    display: block;
    padding: 16px 18px;
    border-radius: 12px;
    border: 1px solid var(--border, #2a2a32);
    background: var(--card-bg, #1a1a1f);
    text-decoration: none;
    color: inherit;
    transition: border-color .15s;
}
.app-item:hover { border-color: #7c5cfc; }
.app-item h2 { margin: 0 0 6px; font-size: 1.1rem; }
.app-item p { margin: 0; color: var(--muted, #999); font-size: 14px; line-height: 1.4; }
.app-item .meta { margin-top: 8px; font-size: 12px; color: var(--muted, #888); }
.app-empty { padding: 32px; text-align: center; color: var(--muted, #999); }
</style>

<main class="app-page">
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
</main>
