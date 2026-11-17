<main>
    <div class="container">
        <div class="page-header">
            <h2>Логи</h2>
            <p>Журнал административных действий: кто, когда и что изменил в форуме.</p>
        </div>

        <?php
$auditTable = \App\Components\AuditTable::make(
    $logs ?? [],
    $selectedLog ?? null,
    '/moderation/logs',
    (int)($logsPage ?? 1),
    (int)($logsTotal ?? 0),
    (int)($logsPages ?? 1),
    $filters ?? [],
    $filterOptions ?? []
);
?>

<!-- Фильтры сверху -->
<?= $auditTable->filters() ?>

<div class="logs-layout">
    <div class="logs-table-card">
        <div class="logs-table-header">
            <h3>Аудит действий</h3>
            <span class="logs-count"><?= (int)($logsTotal ?? 0) ?> событий</span>
        </div>

        <?= $auditTable ?>

        <p class="logs-note">
            Журнал доступен только администраторам. Пароли, токены и сетевые адреса в этом представлении не отображаются.
        </p>
    </div>

    <!-- Правая часть (aside) -->
    <aside class="log-detail-card">
        <?php if (empty($selectedLog)): ?>
            <div class="empty-detail">
                <p>Выберите событие, чтобы посмотреть подробности</p>
            </div>
        <?php else: ?>
            <?php
                $payload = json_decode($selectedLog['data'] ?? '{}', true) ?: [];
                $before  = $payload['before'] ?? [];
                $after   = $payload['after'] ?? [];

                $result = $selectedLog['result'] ?? 'success';
                $resultClass = match ($result) {
                    'success' => 'badge-success',
                    'denied', 'fail', 'failed', 'failure' => 'badge-danger',
                    'error'   => 'badge-warning',
                    default   => 'badge-secondary',
                };
                $resultText = match ($result) {
                    'success' => 'Успешно',
                    'denied'  => 'Отказано',
                    'fail', 'failed', 'failure' => 'Ошибка',
                    'error'   => 'Ошибка',
                    default   => (string)$result,
                };

                $actionLabel = $selectedLog['action_label'] ?? $selectedLog['action'] ?? '—';
                $objectTitle = $selectedLog['object_title']
                    ?? ($selectedLog['target_type'] ? $selectedLog['target_type'] . ' #' . ($selectedLog['target_id'] ?? '') : '—');
                $objectUrl   = $selectedLog['object_url'] ?? null;
            ?>

            <div class="log-detail-header">
                <h3>Событие L-<?= str_pad((string)$selectedLog['id'], 3, '0', STR_PAD_LEFT) ?></h3>
                <span class="badge <?= $resultClass ?>"><?= $resultText ?></span>
            </div>

            <dl class="log-detail-list">
                <div>
                    <dt>Время</dt>
                    <dd><?= date('d.m.Y · H:i:s', strtotime($selectedLog['created_at'])) ?></dd>
                </div>
                <div>
                    <dt>Участник</dt>
                    <dd>
                        <?= htmlspecialchars($selectedLog['actor_name'] ?? $selectedLog['admin_name'] ?? '—') ?>
                        <?php if (!empty($selectedLog['role_label'])): ?>
                            <br><small class="log-role-tag log-role-<?= htmlspecialchars($selectedLog['role_name'] ?? '') ?>">
                                <?= htmlspecialchars($selectedLog['role_label']) ?>
                            </small>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt>Действие</dt>
                    <dd><?= htmlspecialchars($actionLabel) ?>
                        <small class="text-muted">(<?= htmlspecialchars($selectedLog['action'] ?? '') ?>)</small>
                    </dd>
                </div>
                <div>
                    <dt>Объект</dt>
                    <dd>
                        <?php if ($objectUrl): ?>
                            <a href="<?= htmlspecialchars($objectUrl) ?>" class="log-object-link" target="_blank" rel="noopener">
                                <?= htmlspecialchars($objectTitle) ?>
                            </a>
                        <?php else: ?>
                            <?= htmlspecialchars($objectTitle) ?>
                        <?php endif; ?>
                        <?php if (!empty($selectedLog['target_type'])): ?>
                            <br><small class="text-muted">
                                <?= htmlspecialchars($selectedLog['target_type']) ?>
                                #<?= (int)($selectedLog['target_id'] ?? 0) ?>
                            </small>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>

            <?php
            $payloadAdded   = $payload['added']   ?? null;
            $payloadRemoved = $payload['removed'] ?? null;
            $payloadSummary = $payload['summary'] ?? null;
            $payloadWeight  = $payload['weight']  ?? null;

            $isList = static function ($v): bool {
                if (!is_array($v) || $v === []) {
                    return false;
                }
                $keys = array_keys($v);
                return $keys === range(0, count($v) - 1) && !is_array(reset($v));
            };
            $fmtVal = static function ($v): string {
                if ($v === null || $v === '') {
                    return '—';
                }
                if (is_bool($v)) {
                    return $v ? 'да' : 'нет';
                }
                if (is_array($v)) {
                    return htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE));
                }
                return htmlspecialchars((string)$v);
            };

            $addedList = is_array($payloadAdded) ? array_values($payloadAdded) : [];
            $removedList = is_array($payloadRemoved) ? array_values($payloadRemoved) : [];
            if (!$addedList && !$removedList && $isList($before) && $isList($after)) {
                $addedList = array_values(array_diff($after, $before));
                $removedList = array_values(array_diff($before, $after));
            }
            $hasChanges = $addedList || $removedList || !empty($before) || !empty($after) || $payloadSummary
                || (is_array($payloadWeight) && (($payloadWeight['before'] ?? null) != ($payloadWeight['after'] ?? null)));
            ?>
            <?php if ($hasChanges): ?>
                <div class="log-changes">
                    <h4>Изменения</h4>

                    <?php if ($payloadSummary): ?>
                        <p class="log-summary" style="margin:0 0 10px;font-size:13px;line-height:1.45;">
                            <?= htmlspecialchars((string)$payloadSummary) ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($addedList || $removedList): ?>
                        <?php if ($addedList): ?>
                            <div class="change-block change-after">
                                <span class="change-label">Добавлено (<?= count($addedList) ?>)</span>
                                <div class="log-perm-list">
                                    <?php foreach ($addedList as $p): ?>
                                        <code class="log-perm log-perm--add"><?= htmlspecialchars((string)$p) ?></code>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($removedList): ?>
                            <div class="change-block change-before">
                                <span class="change-label">Убрано (<?= count($removedList) ?>)</span>
                                <div class="log-perm-list">
                                    <?php foreach ($removedList as $p): ?>
                                        <code class="log-perm log-perm--del"><?= htmlspecialchars((string)$p) ?></code>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php elseif (!empty($before) || !empty($after)): ?>
                        <?php
                        $allKeys = array_unique(array_merge(
                            array_keys(is_array($before) ? $before : []),
                            array_keys(is_array($after) ? $after : [])
                        ));
                        $rows = [];
                        foreach ($allKeys as $k) {
                            $bv = is_array($before) && array_key_exists($k, $before) ? $before[$k] : null;
                            $av = is_array($after) && array_key_exists($k, $after) ? $after[$k] : null;
                            if ($bv == $av) {
                                continue;
                            }
                            $rows[] = [$k, $bv, $av];
                        }
                        ?>
                        <?php if ($rows): ?>
                            <div class="change-block">
                                <?php foreach ($rows as [$k, $bv, $av]): ?>
                                    <div style="margin:6px 0;font-size:13px;">
                                        <strong><?= htmlspecialchars((string)$k) ?>:</strong>
                                        <span class="text-muted"><?= $fmtVal($bv) ?></span>
                                        →
                                        <span><?= $fmtVal($av) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted" style="font-size:13px;margin:0;">Нет отличий в значениях.</p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (is_array($payloadWeight) && (($payloadWeight['before'] ?? null) != ($payloadWeight['after'] ?? null))): ?>
                        <div class="change-block" style="margin-top:8px;">
                            <span class="change-label">Вес роли</span>
                            <div style="font-size:13px;">
                                <?= (int)($payloadWeight['before'] ?? 0) ?> → <?= (int)($payloadWeight['after'] ?? 0) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <style>
            .log-perm-list { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
            .log-perm { font-size:11px; padding:2px 7px; border-radius:6px; }
            .log-perm--add { background: rgba(34,197,94,.15); color: #86efac; }
            .log-perm--del { background: rgba(239,68,68,.15); color: #fca5a5; }
            .pagination-ellipsis { padding: 0 6px; opacity: .5; user-select: none; }
            .pagination-nav { min-width: 28px; text-align: center; }
            .pagination-nav.is-disabled { opacity: .35; pointer-events: none; }
            </style>

            <?php if (!empty($selectedLog['comment'])): ?>
                <div class="log-comment">
                    <strong>Комментарий</strong>
                    <p><?= htmlspecialchars($selectedLog['comment']) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($objectUrl): ?>
                <a href="<?= htmlspecialchars($objectUrl) ?>" class="btn btn-secondary" target="_blank" rel="noopener">
                    Открыть объект
                </a>
            <?php endif; ?>

            <?php if (!empty($payload['related']['complaint_id'])): ?>
                <a href="<?= url('/moderation/complaints?id=' . (int)$payload['related']['complaint_id']) ?>" class="btn btn-secondary">
                    Показать связанное обращение
                </a>
            <?php endif; ?>

            <?php
            // История изменений этого объекта (несколько логов с одним target)
            $history = $logHistory ?? [];
            if (count($history) > 1):
            ?>
                <div class="log-history">
                    <h4>История объекта</h4>
                    <p class="log-history-note">
                        Все события по
                        <strong><?= htmlspecialchars($selectedLog['target_type'] ?? '') ?> #<?= (int)($selectedLog['target_id'] ?? 0) ?></strong>
                    </p>
                    <ol class="log-timeline">
                        <?php foreach ($history as $h): ?>
                            <?php
                                $isCurrent = (int)$h['id'] === (int)$selectedLog['id'];
                                $hResult   = $h['result'] ?? 'success';
                                $hBadge    = match ($hResult) {
                                    'success' => 'badge-success',
                                    'denied'  => 'badge-danger',
                                    'error'   => 'badge-warning',
                                    default   => 'badge-success',
                                };
                                $hLabel = $h['action_label'] ?? $h['action'] ?? '—';
                                // ссылка на этот же лог в списке
                                $hHref = url('/moderation/logs?' . http_build_query(array_filter([
                                    'id'       => (int)$h['id'],
                                    'page'     => $logsPage ?? 1,
                                    'q'        => $filters['q'] ?? null,
                                    'date'     => $filters['date'] ?? null,
                                    'admin_id' => $filters['admin_id'] ?? null,
                                    'action'   => $filters['action'] ?? null,
                                    'result'   => $filters['result'] ?? null,
                                    'role'     => $filters['role'] ?? null,
                                ])));
                            ?>
                            <li class="log-timeline-item <?= $isCurrent ? 'is-current' : '' ?>">
                                <div class="log-timeline-time">
                                    <?= date('d.m.Y H:i', strtotime($h['created_at'])) ?>
                                </div>
                                <div class="log-timeline-body">
                                    <a href="<?= htmlspecialchars($hHref) ?>" class="log-timeline-action">
                                        <?= htmlspecialchars($hLabel) ?>
                                    </a>
                                    <span class="badge <?= $hBadge ?>"><?= htmlspecialchars($hResult) ?></span>
                                    <?php
                                        $hName = $h['actor_name'] ?? $h['admin_name'] ?? null;
                                        $hRole = $h['role_label'] ?? null;
                                    ?>
                                    <?php if ($hName): ?>
                                        <span class="log-timeline-admin">· <?= htmlspecialchars($hName) ?><?php if ($hRole): ?> <span class="log-role-tag log-role-<?= htmlspecialchars($h['role_name'] ?? '') ?>"><?= htmlspecialchars($hRole) ?></span><?php endif; ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($h['comment'])): ?>
                                        <div class="log-timeline-comment"><?= htmlspecialchars($h['comment']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>

            <p class="log-detail-note">
                Журнал фиксирует изменение, но не подтверждает обоснованность жалобы.
            </p>
        <?php endif; ?>
    </aside>
</div>
    </div>
</main>