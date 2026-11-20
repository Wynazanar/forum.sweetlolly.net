<?php
$report = $report ?? [];
$messages = $messages ?? [];
$statusOptions = $statusOptions ?? [];
$errors = $errors ?? [];
$status = $report['status'] ?? '';
$id = (int)($report['id'] ?? 0);
$open = in_array($status, ['pending', 'needs_info'], true);
$typeLabel = $report['type_label'] ?? ($report['target_type'] ?? '—');
?>
<style>
.my-show { max-width: 720px; margin: 0 auto; padding: 24px 16px 48px; }
.panel {
    border: 1px solid var(--border,#2a2a32); border-radius: 12px;
    padding: 18px; margin-bottom: 16px; background: var(--card-bg,#1a1a1f);
}
.panel h2 { margin: 0 0 12px; font-size: 1.1rem; }
.meta-grid { display: grid; gap: 10px; }
.meta-grid .row { display: flex; flex-direction: column; gap: 2px; }
.meta-grid .lbl { font-size: 11px; color: var(--muted,#999); text-transform: uppercase; letter-spacing: .03em; }
.meta-grid .val { font-size: 14px; font-weight: 600; line-height: 1.4; word-break: break-word; }
.preview {
    margin-top: 12px; padding: 10px 12px; border-radius: 10px;
    background: rgba(124,92,252,.08); border: 1px solid rgba(124,92,252,.12);
    font-size: 13px; line-height: 1.45; word-break: break-word;
}
.msg { padding: 10px 12px; border-radius: 8px; margin-bottom: 8px; font-size: 13px; background: rgba(255,255,255,.04); }
.msg-staff { background: rgba(124,92,252,.12); }
.msg-request { border-left: 3px solid #fbbf24; }
.msg .meta { font-size: 11px; color: var(--muted,#999); margin-bottom: 4px; }
textarea {
    width: 100%; padding: 10px; border-radius: 8px; box-sizing: border-box;
    border: 1px solid var(--border,#333); background: #121216; color: inherit; font: inherit;
}
.btn-primary {
    background: #7c5cfc; color: #fff; border: none; padding: 10px 16px;
    border-radius: 8px; cursor: pointer; font-weight: 600;
}
.err { color: #f85149; font-size: 13px; margin-bottom: 8px; }
.badge {
    display: inline-block; font-size: 11px; padding: 3px 8px; border-radius: 999px;
    background: rgba(124,92,252,.15); color: #b9a6ff;
}
.badge-needs { background: rgba(201,162,39,.15); color: #e0c35a; }
.badge-ok { background: rgba(46,160,67,.15); color: #3fb950; }
.badge-bad { background: rgba(248,81,73,.12); color: #f85149; }
.badge-wait { background: rgba(210,153,34,.15); color: #d29922; }
.notice {
    padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; font-size: 13px;
    background: rgba(201,162,39,.12); border: 1px solid rgba(201,162,39,.25);
}
</style>
<main class="my-show">
    <p><a href="<?= url('/my/reports') ?>">← Мои репорты</a></p>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:8px 0 4px;">
        <h1 style="margin:0;">Репорт R-<?= str_pad((string)$id, 3, '0', STR_PAD_LEFT) ?></h1>
        <?php
            $st = $status;
            $b = 'badge';
            if ($st === 'needs_info') $b .= ' badge-needs';
            elseif ($st === 'resolved') $b .= ' badge-ok';
            elseif ($st === 'rejected') $b .= ' badge-bad';
            elseif ($st === 'pending') $b .= ' badge-wait';
        ?>
        <span class="<?= $b ?>"><?= htmlspecialchars($statusOptions[$status] ?? $status) ?></span>
    </div>
    <p style="color:var(--muted,#999);margin-top:0;"><?= htmlspecialchars($report['created_at'] ?? '') ?></p>

    <?php if ($status === 'needs_info'): ?>
        <div class="notice">Модерация запросила уточнение. Ответьте в форме ниже — репорт снова попадёт в очередь.</div>
    <?php endif; ?>

    <div class="panel">
        <h2>Сведения</h2>
        <div class="meta-grid">
            <div class="row">
                <span class="lbl">Объект</span>
                <span class="val">
                    <?= htmlspecialchars($typeLabel) ?> #<?= (int)($report['target_id'] ?? 0) ?>
                    <?php if (!empty($report['target_name'])): ?>
                        · <?= htmlspecialchars($report['target_name']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="row">
                <span class="lbl">Причина</span>
                <span class="val"><?= htmlspecialchars($report['reason'] ?? '—') ?></span>
            </div>
            <?php if (!empty($report['description'])): ?>
            <div class="row">
                <span class="lbl">Описание</span>
                <span class="val"><?= nl2br(htmlspecialchars($report['description'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($report['resolver_name']) && in_array($status, ['resolved', 'rejected'], true)): ?>
            <div class="row">
                <span class="lbl">Рассмотрел</span>
                <span class="val">
                    <?= htmlspecialchars($report['resolver_name']) ?>
                    <?php if (!empty($report['resolved_at'])): ?>
                        · <?= htmlspecialchars($report['resolved_at']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($report['target_preview'])): ?>
            <div class="preview"><?= nl2br(htmlspecialchars($report['target_preview'])) ?></div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Переписка</h2>
        <?php if (empty($messages)): ?>
            <p class="text-muted" style="font-size:13px;margin:0;">Сообщений пока нет.</p>
        <?php else: ?>
            <?php foreach ($messages as $m):
                $kind = $m['kind'] ?? 'message';
                $cls = 'msg';
                $isStaff = (int)($m['user_id'] ?? 0) !== (int)($report['reporter_id'] ?? 0);
                if ($isStaff) $cls .= ' msg-staff';
                if ($kind === 'request_info') $cls .= ' msg-request';
            ?>
                <div class="<?= $cls ?>">
                    <div class="meta">
                        <?= htmlspecialchars($m['username'] ?? '—') ?>
                        · <?= htmlspecialchars($m['created_at'] ?? '') ?>
                        <?php if ($kind === 'request_info'): ?> · запрос уточнения<?php endif; ?>
                    </div>
                    <div><?= nl2br(htmlspecialchars($m['body'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($open): ?>
            <?php if (!empty($errors['body'])): ?><div class="err"><?= htmlspecialchars($errors['body']) ?></div><?php endif; ?>
            <form method="post" action="<?= url('/my/reports/' . $id . '/reply') ?>" style="margin-top:12px;">
                <textarea name="body" rows="3" required
                    placeholder="<?= $status === 'needs_info' ? 'Ответ на уточнение…' : 'Дополнить репорт…' ?>"></textarea>
                <div style="margin-top:10px;">
                    <button type="submit" class="btn-primary">
                        <?= $status === 'needs_info' ? 'Отправить уточнение' : 'Отправить' ?>
                    </button>
                </div>
            </form>
        <?php else: ?>
            <p class="text-muted" style="font-size:13px;margin-top:12px;">
                Репорт закрыт — новые сообщения отправить нельзя.
            </p>
        <?php endif; ?>
    </div>
</main>
