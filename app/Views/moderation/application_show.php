<?php
/** @var array $application */
/** @var array $statusOptions */

$app = $application ?? [];
$statusOptions = $statusOptions ?? [
    'pending' => 'На рассмотрении',
    'accepted' => 'Принята',
    'rejected' => 'Отклонена',
    'cancelled' => 'Отменена',
];
$status = $app['status'] ?? 'pending';
$snapshot = $app['form_snapshot'] ?? [];
$questions = $snapshot['questions'] ?? [];
$answers = $app['answers'] ?? [];

$statusClass = match ($status) {
    'pending' => 'badge-warning',
    'accepted' => 'badge-success',
    'rejected' => 'badge-danger',
    default => 'badge-secondary',
};
?>
<link rel="stylesheet" href="<?= asset('styles/admin.css'); ?>">
<main>
    <div class="container">
        <div class="page-header flex">
            <div>
                <h2>Заявка #<?= (int)($app['id'] ?? 0) ?></h2>
                <p>
                    <?= htmlspecialchars($app['form_name'] ?? 'Шаблон') ?>
                    · <?= htmlspecialchars($app['username'] ?? 'Игрок') ?>
                    · <?= htmlspecialchars($app['created_at'] ?? '') ?>
                </p>
            </div>
            <div>
                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusOptions[$status] ?? $status) ?></span>
            </div>
        </div>

        <div class="logs-layout" style="grid-template-columns: 1fr 320px;">
            <div class="logs-table-card">
                <div class="logs-table-header">
                    <h3>Ответы анкеты</h3>
                </div>
                <div style="padding:16px;">
                    <?php if (empty($questions)): ?>
                        <p class="text-muted">Снимок вопросов пуст.</p>
                    <?php else: ?>
                        <dl class="log-detail-list">
                            <?php foreach ($questions as $q):
                                $qid = $q['id'] ?? '';
                                $val = $answers[$qid] ?? null;
                                if (is_array($val)) $val = implode(', ', $val);
                            ?>
                            <div style="margin-bottom:14px;">
                                <dt style="font-weight:600;margin-bottom:4px;">
                                    <?= htmlspecialchars($q['label'] ?? $qid) ?>
                                    <?php if (!empty($q['required'])): ?><span style="color:var(--danger,#e55);">*</span><?php endif; ?>
                                </dt>
                                <dd style="white-space:pre-wrap;">
                                    <?= $val !== null && $val !== ''
                                        ? nl2br(htmlspecialchars((string)$val))
                                        : '<span class="text-muted">—</span>' ?>
                                </dd>
                            </div>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="log-detail-card">
                <div class="log-detail-header">
                    <h3>Решение</h3>
                </div>

                <dl class="log-detail-list">
                    <div>
                        <dt>Игрок</dt>
                        <dd><?= htmlspecialchars($app['username'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd><?= htmlspecialchars($app['email'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt>Шаблон</dt>
                        <dd><?= htmlspecialchars($app['form_name'] ?? '—') ?></dd>
                    </div>
                    <?php if (!empty($app['reviewed_at'])): ?>
                    <div>
                        <dt>Рассмотрел</dt>
                        <dd>
                            <?= htmlspecialchars($app['reviewer_name'] ?? '—') ?>
                            <div class="text-muted"><?= htmlspecialchars($app['reviewed_at']) ?></div>
                        </dd>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($app['admin_comment'])): ?>
                    <div>
                        <dt>Комментарий</dt>
                        <dd><?= nl2br(htmlspecialchars($app['admin_comment'])) ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>

                <?php if ($status === 'pending'): ?>
                <form method="post" action="<?= url('/moderation/applications/review/' . (int)$app['id']) ?>" style="margin-top:16px;">
                    <div class="form-group">
                        <label>Комментарий (необязательно)</label>
                        <textarea name="admin_comment" rows="3" placeholder="Причина решения"></textarea>
                    </div>
                    <div class="detail-actions" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="submit" name="status" value="accepted" class="btn btn-primary"
                                onclick="return confirm('Принять заявку?')">Принять</button>
                        <button type="submit" name="status" value="rejected" class="btn btn-outline btn-danger"
                                onclick="return confirm('Отклонить заявку?')">Отклонить</button>
                    </div>
                </form>
                <?php endif; ?>

                <div style="margin-top:16px;">
                    <a href="<?= url('/moderation/applications?sub=submissions') ?>" class="btn btn-outline">← К списку</a>
                </div>
            </aside>
        </div>
    </div>
</main>
