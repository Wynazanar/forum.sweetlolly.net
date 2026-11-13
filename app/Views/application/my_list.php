<?php
$applications = $applications ?? [];
$statusOpts = $statusOpts ?? [];
?>
<style>
.my-apps { max-width: 720px; margin: 0 auto; padding: 24px 16px 48px; }
.my-apps h1 { margin: 0 0 8px; }
.my-apps .lead { color: var(--muted,#999); margin-bottom: 20px; }
.my-card {
    display: block; text-decoration: none; color: inherit;
    border: 1px solid var(--border,#2a2a32); border-radius: 12px;
    padding: 16px 18px; margin-bottom: 12px; background: var(--card-bg,#1a1a1f);
}
.my-card:hover { border-color: #7c5cfc; }
.my-card h2 { margin: 0 0 6px; font-size: 1.05rem; }
.my-card .meta { font-size: 13px; color: var(--muted,#999); }
.badge {
    display: inline-block; font-size: 11px; padding: 3px 8px; border-radius: 999px;
    background: rgba(124,92,252,.15); color: #b9a6ff;
}
.badge-needs { background: rgba(201,162,39,.15); color: #e0c35a; }
.badge-ok { background: rgba(46,160,67,.15); color: #3fb950; }
.badge-bad { background: rgba(248,81,73,.12); color: #f85149; }
</style>
<main class="my-apps">
    <h1>Мои заявки</h1>
    <p class="lead">Статусы, уточнения от команды и ваши ответы.</p>
    <p style="margin-bottom:16px;"><a href="<?= url('/applications') ?>">Открытые анкеты →</a></p>

    <?php if (empty($applications)): ?>
        <p class="text-muted">Вы ещё не подавали заявок.</p>
    <?php else: ?>
        <?php foreach ($applications as $a):
            $st = $a['status'] ?? '';
            $badge = 'badge';
            if ($st === 'needs_info') $badge .= ' badge-needs';
            elseif ($st === 'accepted') $badge .= ' badge-ok';
            elseif (in_array($st, ['rejected', 'withdrawn'], true)) $badge .= ' badge-bad';
        ?>
            <a class="my-card" href="<?= url('/my/applications/' . (int)$a['id']) ?>">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                    <h2><?= htmlspecialchars($a['form_name'] ?? 'Заявка') ?></h2>
                    <span class="<?= $badge ?>"><?= htmlspecialchars($statusOpts[$st] ?? $st) ?></span>
                </div>
                <div class="meta">#<?= (int)$a['id'] ?> · <?= htmlspecialchars($a['created_at'] ?? '') ?></div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
