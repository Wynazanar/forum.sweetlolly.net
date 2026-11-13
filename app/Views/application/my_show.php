<?php
$app = $application ?? [];
$messages = $messages ?? [];
$statusOpts = $statusOpts ?? [];
$errors = $errors ?? [];
$status = $app['status'] ?? '';
$id = (int)($app['id'] ?? 0);
$open = in_array($status, ['pending', 'needs_info'], true);
$snapshot = $app['form_snapshot'] ?? [];
$questions = $snapshot['questions'] ?? [];
$answers = $app['answers'] ?? [];
?>
<style>
.my-show { max-width: 720px; margin: 0 auto; padding: 24px 16px 48px; }
.panel { border: 1px solid var(--border,#2a2a32); border-radius: 12px; padding: 18px; margin-bottom: 16px; background: var(--card-bg,#1a1a1f); }
.panel h2 { margin: 0 0 12px; font-size: 1.1rem; }
.msg { padding: 10px 12px; border-radius: 8px; margin-bottom: 8px; font-size: 13px; background: rgba(255,255,255,.04); }
.msg-staff { background: rgba(124,92,252,.12); }
.msg-system { opacity: .7; font-size: 12px; }
.msg .meta { font-size: 11px; color: var(--muted,#999); margin-bottom: 4px; }
.ans { margin-bottom: 10px; }
.ans .q { font-size: 12px; color: var(--muted,#999); }
textarea, input[type=text] { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border,#333); background: #121216; color: inherit; }
.btn-primary { background: #7c5cfc; color: #fff; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; }
.err { color: #f85149; font-size: 13px; margin-bottom: 8px; }
</style>
<main class="my-show">
    <p><a href="<?= url('/my/applications') ?>">← Мои заявки</a></p>
    <h1><?= htmlspecialchars($app['form_name'] ?? 'Заявка') ?> · #<?= $id ?></h1>
    <p style="color:var(--muted,#999);"><?= htmlspecialchars($statusOpts[$status] ?? $status) ?> · <?= htmlspecialchars($app['created_at'] ?? '') ?></p>

    <div class="panel">
        <h2>Ваши ответы</h2>
        <?php foreach ($questions as $q):
            $qid = $q['id'] ?? '';
            $val = $answers[$qid] ?? null;
            if ($val === null || $val === '') continue;
        ?>
            <div class="ans">
                <div class="q"><?= htmlspecialchars($q['label'] ?? $qid) ?></div>
                <div><?php
                                if (is_array($val) && isset($val['attachment_id'])) {
                                    $aid = (int)$val['attachment_id'];
                                    $aname = htmlspecialchars($val['name'] ?? 'file');
                                    $asize = isset($val['size']) ? ' · ' . round($val['size']/1024) . ' КБ' : '';
                                    echo '<a href="' . htmlspecialchars(url('/attachments/' . $aid . '/download')) . '">' . $aname . $asize . '</a>';
                                } else {
                                    echo htmlspecialchars(is_array($val) ? implode(', ', $val) : (string)$val);
                                }
                                ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="panel">
        <h2>История и сообщения</h2>
        <?php foreach ($messages as $m):
            $kind = $m['kind'] ?? 'message';
            $cls = 'msg';
            if ($kind === 'system') $cls .= ' msg-system';
            elseif ((int)($m['user_id'] ?? 0) !== (int)($app['user_id'] ?? 0)) $cls .= ' msg-staff';
        ?>
            <div class="<?= $cls ?>">
                <div class="meta"><?= htmlspecialchars($m['username'] ?? '') ?> · <?= htmlspecialchars($m['created_at'] ?? '') ?></div>
                <div><?= nl2br(htmlspecialchars($m['body'] ?? '')) ?></div>
            </div>
        <?php endforeach; ?>

        <?php if ($open): ?>
            <?php if (!empty($errors['body'])): ?><div class="err"><?= htmlspecialchars($errors['body']) ?></div><?php endif; ?>
            <form method="post" action="<?= url('/my/applications/' . $id . '/reply') ?>" style="margin-top:12px;">
                <textarea name="body" rows="3" required placeholder="<?= $status === 'needs_info' ? 'Ответ на уточнение…' : 'Сообщение команде…' ?>"></textarea>
                <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary"><?= $status === 'needs_info' ? 'Отправить уточнение' : 'Отправить' ?></button>
                </div>
            </form>
            <form method="post" action="<?= url('/my/applications/' . $id . '/withdraw') ?>" style="margin-top:12px;"
                  onsubmit="return confirm('Отозвать заявку?');">
                <button type="submit" style="background:transparent;border:1px solid var(--border,#444);color:inherit;padding:8px 12px;border-radius:8px;cursor:pointer;">Отозвать заявку</button>
            </form>
        <?php endif; ?>
    </div>
</main>
