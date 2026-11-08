<main>
    <div class="container">
        <div class="page-header">
            <h2>Логи</h2>
            <p>Журнал административных действий: кто, когда и что изменил в форуме.</p>
        </div>

        <div class="logs-layout">
            <!-- Левая часть -->
            <div class="logs-table-card">
                <div class="logs-table-header">
                    <h3>Аудит действий</h3>
                    <span class="logs-count"><?= (int)($logsTotal ?? 0) ?> событий</span>
                </div>

                <?php if (empty($logs)): ?>
                    <p class="empty-state">Логов пока нет</p>
                <?php else: ?>
                    <?= \App\Components\AuditTable::make(
                        $logs ?? [],
                        $selectedLog ?? null,
                        '/moderation/logs',
                        (int)($logsPage ?? 1)
                    ) ?>

                    <?php if (($logsPages ?? 1) > 1): ?>
                        <div class="pagination">
                            <span>Показано <?= count($logs) ?> из <?= (int)$logsTotal ?> · Страница <?= (int)$logsPage ?> из <?= (int)$logsPages ?></span>
                            <div class="pagination-pages">
                                <?php for ($i = 1; $i <= $logsPages; $i++): ?>
                                    <a href="<?= url('/moderation/logs?page='.$i) ?>"
                                       class="<?= $i === (int)$logsPage ? 'is-active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

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
                        $data   = json_decode($selectedLog['data'] ?? '{}', true) ?: [];
                        $before = $data['before'] ?? [];
                        $after  = $data['after'] ?? [];

                        $result = $selectedLog['result'] ?? 'success';
                        $resultClass = $result === 'success' ? 'badge-success' : 'badge-danger';
                        $resultText  = $result === 'success' ? 'Успешно' : 'Отказано';
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
                            <dt>Администратор</dt>
                            <dd><?= htmlspecialchars($selectedLog['admin_name'] ?? '—') ?></dd>
                        </div>
                        <div>
                            <dt>Действие</dt>
                            <dd><?= htmlspecialchars($selectedLog['action']) ?></dd>
                        </div>
                        <div>
                            <dt>Объект</dt>
                            <dd><?= htmlspecialchars($selectedLog['object_title'] ?? $selectedLog['target_type'] ?? '—') ?></dd>
                        </div>
                    </dl>

                    <?php if (!empty($before) || !empty($after)): ?>
                        <div class="log-changes">
                            <h4>Изменённые значения</h4>

                            <?php if (!empty($before)): ?>
                                <div class="change-block change-before">
                                    <span class="change-label">ДО</span>
                                    <?php foreach ($before as $key => $value): ?>
                                        <div>
                                            <strong><?= htmlspecialchars((string)$key) ?>:</strong>
                                            <?= htmlspecialchars($value === null || $value === '' ? 'не назначен' : (string)$value) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($after)): ?>
                                <div class="change-block change-after">
                                    <span class="change-label">ПОСЛЕ</span>
                                    <?php foreach ($after as $key => $value): ?>
                                        <div>
                                            <strong><?= htmlspecialchars((string)$key) ?>:</strong>
                                            <?= htmlspecialchars($value === null || $value === '' ? 'не назначен' : (string)$value) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($selectedLog['comment'])): ?>
                        <div class="log-comment">
                            <strong>Комментарий</strong>
                            <p><?= htmlspecialchars($selectedLog['comment']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($data['related']['complaint_id'])): ?>
                        <a href="/moderation/complaints?id=<?= (int)$data['related']['complaint_id'] ?>" class="btn btn-secondary">
                            Показать связанное обращение
                        </a>
                    <?php endif; ?>

                    <p class="log-detail-note">
                        Журнал фиксирует изменение, но не подтверждает обоснованность жалобы.
                    </p>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</main>