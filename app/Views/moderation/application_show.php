<?php
$app = $application ?? [];
$messages = $messages ?? [];
$statusOpts = $statusOpts ?? [];
$status = $app['status'] ?? 'pending';
$snapshot = $app['form_snapshot'] ?? [];
$questions = $snapshot['questions'] ?? [];
$answers = $app['answers'] ?? [];
$open = in_array($status, ['pending', 'needs_info'], true);
$id = (int)($app['id'] ?? 0);
?>
<link rel="stylesheet" href="<?= asset('styles/admin.css'); ?>">
<main>
<div class="container">
    <div class="page-header flex">
        <div>
            <h2><?= htmlspecialchars($app['form_name'] ?? 'Заявка') ?> · #<?= $id ?></h2>
            <p class="text-muted">
                <?= htmlspecialchars($app['username'] ?? 'Игрок') ?> ·
                <?= htmlspecialchars($app['created_at'] ?? '') ?> ·
                <span class="badge"><?= htmlspecialchars($statusOpts[$status] ?? $status) ?></span>
            </p>
        </div>
        <a href="<?= url('/moderation/applications') ?>?sub=submissions" class="btn btn-outline">← К списку</a>
    </div>

    <div class="app-review">
        <div>
            <div class="app-panel">
                <h3>Ответы игрока</h3>
                <?php if (empty($questions)): ?>
                    <p class="text-muted">Нет снимка вопросов</p>
                    <?php foreach ($answers as $k => $v): ?>
                        <div class="ans-block">
                            <div class="q"><?= htmlspecialchars((string)$k) ?></div>
                            <div class="a"><?= htmlspecialchars(is_array($v) ? implode(', ', $v) : (string)$v) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($questions as $q):
                        $qid = $q['id'] ?? '';
                        $val = $answers[$qid] ?? null;
                        if ($val === null || $val === '') continue;
                    ?>
                        <div class="ans-block">
                            <div class="q"><?= htmlspecialchars($q['label'] ?? $qid) ?></div>
                            <div class="a"><?php
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
                <?php endif; ?>
            </div>

            <div class="app-panel">
                <h3>Переписка</h3>
                <?php foreach ($messages as $m):
                    $kind = $m['kind'] ?? 'message';
                    $cls = 'msg';
                    if (!empty($m['is_internal'])) $cls .= ' msg-internal';
                    elseif ($kind === 'system') $cls .= ' msg-system';
                    elseif ((int)($m['user_id'] ?? 0) === (int)($app['user_id'] ?? 0)) $cls .= ' msg-player';
                    else $cls .= ' msg-staff';
                ?>
                    <div class="<?= $cls ?>">
                        <div class="meta">
                            <?= htmlspecialchars($m['username'] ?? '—') ?> ·
                            <?= htmlspecialchars($m['created_at'] ?? '') ?>
                            <?php if (!empty($m['is_internal'])): ?> · внутренняя<?php endif; ?>
                            <?php if ($kind === 'clarification'): ?> · уточнение<?php endif; ?>
                        </div>
                        <div><?= nl2br(htmlspecialchars($m['body'] ?? '')) ?></div>
                    </div>
                <?php endforeach; ?>

                <?php if ($open): ?>
                <form method="post" action="<?= url('/moderation/applications/review/' . $id) ?>" style="margin-top:12px;">
                    <input type="hidden" name="action" value="message">
                    <div class="form-group">
                        <textarea name="comment" rows="2" required placeholder="Сообщение игроку…" style="width:100%;"></textarea>
                    </div>
                    <label class="toggle" style="margin-block: 10px;">
                        <span>Заметка <b style="font-weight: normal; font-size: 12px; color: var(--muted);">(не видна игроку)</b></span>
                        <input type="checkbox" name="internal" value="1">
                        <span class="switch"></span>
                    </label>
                    <button type="submit" class="btn btn-outline">Отправить</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="app-panel">
                <h3>Рассмотрение</h3>
                <?php if (!$open): ?>
                    <p class="text-muted">Заявка закрыта: <?= htmlspecialchars($statusOpts[$status] ?? $status) ?></p>
                    <?php if (!empty($app['admin_comment'])): ?>
                        <p><?= nl2br(htmlspecialchars($app['admin_comment'])) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="post" action="<?= url('/moderation/applications/review/' . $id) ?>" style="margin-bottom:12px;">
                        <input type="hidden" name="action" value="clarify">
                        <div class="form-group">
                            <label>Запросить уточнение</label>
                            <textarea name="comment" rows="3" required placeholder="Что нужно уточнить?" style="width:100%;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-outline" style="width:100%;margin-top:8px;">Запросить уточнение</button>
                    </form>

                    <form method="post" action="<?= url('/moderation/applications/review/' . $id) ?>" style="margin-bottom:8px;"
                          onsubmit="return confirm('Одобрить заявку?');">
                        <input type="hidden" name="action" value="accept">
                        <!-- <input type="text" name="comment" placeholder="Комментарий (необяз.)" style="width:100%;margin-bottom:8px;"> -->
                        <button type="submit" class="btn btn-success" style="width:100%;">Одобрить</button>
                    </form>

                    <form method="post" action="<?= url('/moderation/applications/review/' . $id) ?>"
                          onsubmit="return confirm('Отклонить заявку?');">
                        <input type="hidden" name="action" value="reject">
                        <input type="text" name="comment" placeholder="Причина отклонения" style="width:100%;margin-bottom:8px;">
                        <button type="submit" class="btn btn-outline btn-danger" style="width:100%;">Отклонить</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</main>
