<?php
$items = $items ?? [];
$statusOptions = $statusOptions ?? [];
$page = (int)($page ?? 1);
$pages = (int)($pages ?? 1);
$total = (int)($total ?? 0);
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
.my-card .ctx { font-size: 13px; margin-top: 6px; color: var(--text-secondary, #bbb); line-height: 1.4; }
.badge {
    display: inline-block; font-size: 11px; padding: 3px 8px; border-radius: 999px;
    background: rgba(124,92,252,.15); color: #b9a6ff; white-space: nowrap;
}
.badge-needs { background: rgba(201,162,39,.15); color: #e0c35a; }
.badge-ok { background: rgba(46,160,67,.15); color: #3fb950; }
.badge-bad { background: rgba(248,81,73,.12); color: #f85149; }
.badge-wait { background: rgba(210,153,34,.15); color: #d29922; }
.my-pager { display:flex; gap:8px; margin-top:16px; flex-wrap:wrap; }
.my-pager a, .my-pager span {
    padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border,#333);
    font-size: 13px; text-decoration: none; color: inherit;
}
.my-pager .is-active { background: rgba(124,92,252,.2); border-color: #7c5cfc; }
</style>
<main class="my-apps">
    <h1>Мои репорты</h1>
    <p class="lead">Жалобы, которые вы отправили. Здесь можно ответить на уточнения модерации.</p>

    <?php if (empty($items)): ?>
        <p class="text-muted">Вы ещё не отправляли репортов.</p>
    <?php else: ?>
        <?php foreach ($items as $r):
            $st = $r['status'] ?? '';
            $badge = 'badge';
            if ($st === 'needs_info') $badge .= ' badge-needs';
            elseif ($st === 'resolved') $badge .= ' badge-ok';
            elseif ($st === 'rejected') $badge .= ' badge-bad';
            elseif ($st === 'pending') $badge .= ' badge-wait';
            $title = $r['title'] ?? ('R-' . (int)($r['id'] ?? 0));
            $ctx = $r['context'] ?? ($r['reason'] ?? '');
        ?>
            <a class="my-card" href="<?= url('/my/reports/' . (int)$r['id']) ?>">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                    <h2><?= htmlspecialchars($title) ?></h2>
                    <span class="<?= $badge ?>"><?= htmlspecialchars($statusOptions[$st] ?? $st) ?></span>
                </div>
                <div class="meta">
                    <?= htmlspecialchars($r['type_label'] ?? ($r['target_type'] ?? '')) ?>
                    · <?= htmlspecialchars($r['created_at'] ?? '') ?>
                </div>
                <?php if ($ctx !== ''): ?>
                    <div class="ctx"><?= htmlspecialchars(mb_strlen($ctx) > 140 ? mb_substr($ctx, 0, 140) . '…' : $ctx) ?></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <?php if ($pages > 1): ?>
            <div class="my-pager">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="is-active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= url('/my/reports?page=' . $i) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
