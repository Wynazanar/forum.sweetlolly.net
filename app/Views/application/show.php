<?php
$form = $form ?? [];
$layout = $layout ?? null;
$user = $user ?? null;
$canSubmit = $canSubmit ?? false;
$alreadyPending = $alreadyPending ?? false;
$closed = $closed ?? false;
$errors = $errors ?? [];
$old = $old ?? [];
$success = $success ?? false;

$questions = $form['questions'] ?? [];
if (!is_array($questions)) $questions = [];

$settings = [];
if ($layout) {
    $settings = $layout['settings'] ?? [];
    if (is_string($settings)) {
        $settings = json_decode($settings, true) ?: [];
    }
}
$sections = $settings['sections'] ?? [];
if (!is_array($sections)) $sections = [];

$columns = (int)($layout['columns_count'] ?? ($settings['columns'] ?? 12));
$gap = (int)($layout['gap'] ?? ($settings['gap'] ?? 16));
if ($columns < 1) $columns = 12;

// Группируем вопросы по section_id
$bySection = [];
$orphan = [];
foreach ($questions as $q) {
    $sid = $q['section_id'] ?? null;
    if ($sid) {
        $bySection[$sid][] = $q;
    } else {
        $orphan[] = $q;
    }
}

// Порядок секций из макета; пустые скрываем
$orderedSections = [];
foreach ($sections as $sec) {
    $sid = $sec['id'] ?? '';
    if ($sid === '' || empty($bySection[$sid])) {
        continue;
    }
    $orderedSections[] = [
        'meta' => $sec,
        'questions' => $bySection[$sid],
    ];
}
// Вопросы без section_id — в конец, один «виртуальный» блок
if ($orphan) {
    $orderedSections[] = [
        'meta' => ['id' => '_other', 'title' => null, 'description' => null],
        'questions' => $orphan,
    ];
}

$formErrors = $errors['form'] ?? null;

function app_render_field(array $q, array $old, array $errors): string {
    $qid = $q['id'] ?? 'q';
    $type = $q['type'] ?? 'text';
    $label = $q['label'] ?? $qid;
    $required = !empty($q['required']);
    $help = $q['help'] ?? '';
    $placeholder = $q['placeholder'] ?? '';
    $value = $old[$qid] ?? '';
    $fieldError = $errors[$qid] ?? null;
    $options = $q['options'] ?? [];
    $span = max(1, (int)($q['span'] ?? 12));
    $rowSpan = max(1, (int)($q['rowSpan'] ?? 1));
    $col = max(1, (int)($q['col'] ?? 1));
    $row = max(1, (int)($q['row'] ?? 1));

    $style = sprintf(
        'grid-column: %d / span %d; grid-row: %d / span %d;',
        $col, $span, $row, $rowSpan
    );

    $html = '<div class="app-field" style="' . $style . '">';
    $html .= '<label>' . htmlspecialchars($label);
    if ($required) $html .= ' <span class="req">*</span>';
    $html .= '</label>';
    if ($help) {
        $html .= '<div class="help">' . htmlspecialchars($help) . '</div>';
    }

    $name = 'answers[' . htmlspecialchars($qid) . ']';
    $reqAttr = $required ? 'required' : '';

    if ($type === 'textarea') {
        $html .= '<textarea name="' . $name . '" ' . $reqAttr . ' placeholder="' . htmlspecialchars($placeholder) . '">'
            . htmlspecialchars(is_array($value) ? '' : (string)$value) . '</textarea>';
    } elseif ($type === 'select') {
        $html .= '<select name="' . $name . '" ' . $reqAttr . '><option value="">— Выберите —</option>';
        foreach ($options as $opt) {
            $sel = (string)$value === (string)$opt ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($opt) . '" ' . $sel . '>' . htmlspecialchars($opt) . '</option>';
        }
        $html .= '</select>';
    } elseif ($type === 'radio') {
        $html .= '<div class="app-check">';
        foreach ($options as $opt) {
            $chk = (string)$value === (string)$opt ? 'checked' : '';
            $html .= '<label><input type="radio" name="' . $name . '" value="' . htmlspecialchars($opt) . '" ' . $chk . ' ' . $reqAttr . '> '
                . htmlspecialchars($opt) . '</label>';
        }
        $html .= '</div>';
    } elseif ($type === 'checkbox') {
        $selected = is_array($value) ? $value : ($value !== '' && $value !== null ? [$value] : []);
        $html .= '<div class="app-check">';
        foreach ($options as $opt) {
            $chk = in_array($opt, $selected, true) ? 'checked' : '';
            $html .= '<label><input type="checkbox" name="' . $name . '[]" value="' . htmlspecialchars($opt) . '" ' . $chk . '> '
                . htmlspecialchars($opt) . '</label>';
        }
        $html .= '</div>';
    } elseif ($type === 'file') {
        $acceptList = $q['accept'] ?? [];
        $acceptAttr = '';
        if (is_array($acceptList) && $acceptList) {
            $acceptAttr = ' accept="' . htmlspecialchars(implode(',', array_map(fn($e) => '.' . ltrim($e, '.'), $acceptList))) . '"';
        }
        $html .= '<input type="file" name="' . $name . '" ' . $reqAttr . $acceptAttr . '>';
    } else {
        $inputType = match ($type) {
            'email' => 'email', 'number' => 'number', 'url' => 'url', 'date' => 'date', default => 'text',
        };
        $html .= '<input type="' . $inputType . '" name="' . $name . '" value="'
            . htmlspecialchars(is_array($value) ? '' : (string)$value) . '" placeholder="'
            . htmlspecialchars($placeholder) . '" ' . $reqAttr . '>';
    }

    if ($fieldError) {
        $html .= '<div class="error">' . htmlspecialchars((string)$fieldError) . '</div>';
    }
    $html .= '</div>';
    return $html;
}
?>
<style>
.app-page { max-width: 860px; margin: 0 auto; padding: 24px 16px 48px; }
.app-card {
    background: var(--card-bg, #1a1a1f);
    border: 1px solid var(--border, #2a2a32);
    border-radius: 12px;
    padding: 24px;
}
.app-card h1 { font-size: 1.5rem; margin: 0 0 8px; }
.app-card .app-desc { color: var(--muted, #999); margin-bottom: 20px; line-height: 1.5; }
.app-section {
    margin-bottom: 0;
    padding-bottom: 28px;
}
.app-section + .app-section {
    margin-top: 32px;
    padding-top: 28px;
    border-top: 1px solid var(--border, #2a2a32);
}
.app-section:last-child {
    padding-bottom: 0;
}
.app-section-title {
    font-size: 1.15rem;
    margin: 0 0 10px;
    font-weight: 700;
}
.app-section-desc {
    color: var(--muted, #999);
    font-size: 13px;
    margin-bottom: 14px;
}
.app-alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
.app-alert-success { background: rgba(46, 160, 67, 0.15); border: 1px solid rgba(46, 160, 67, 0.4); color: #3fb950; }
.app-alert-error { background: rgba(248, 81, 73, 0.12); border: 1px solid rgba(248, 81, 73, 0.35); color: #f85149; }
.app-alert-info { background: rgba(56, 139, 253, 0.12); border: 1px solid rgba(56, 139, 253, 0.35); color: #58a6ff; }
.app-grid {
    display: grid;
    grid-template-columns: repeat(<?= (int)$columns ?>, minmax(0, 1fr));
    gap: <?= (int)$gap ?>px;
}
.app-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.app-field label { font-weight: 600; font-size: 14px; }
.app-field .req { color: #f85149; }
.app-field .help { font-size: 12px; color: var(--muted, #999); }
.app-field input[type=text],
.app-field input[type=email],
.app-field input[type=number],
.app-field input[type=url],
.app-field input[type=date],
.app-field input[type=file],
.app-field select,
.app-field textarea {
    width: 100%; padding: 10px 12px; border-radius: 8px;
    border: 1px solid var(--border, #2a2a32);
    background: var(--input-bg, #121216); color: inherit; font: inherit;
}
.app-field textarea { min-height: 100px; resize: vertical; height: 100%; }
.app-field .error { color: #f85149; font-size: 12px; }
.app-check { display: flex; flex-direction: column; gap: 6px; }
.app-check label { font-weight: 400; display: flex; align-items: center; gap: 8px; cursor: pointer; }
.app-actions { margin-top: 24px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.app-actions .btn-primary {
    background: #7c5cfc; color: #fff; border: none; padding: 10px 20px;
    border-radius: 8px; cursor: pointer; font-weight: 600;
}
.app-actions a { color: var(--muted, #999); }
@media (max-width: 640px) {
    .app-grid { grid-template-columns: 1fr !important; }
    .app-field { grid-column: 1 / -1 !important; grid-row: auto !important; }
}
</style>

<main class="app-page">
    <div class="app-card">
        <h1><?= htmlspecialchars($form['name'] ?? 'Заявка') ?></h1>
        <?php if (!empty($form['description'])): ?>
            <div class="app-desc"><?= nl2br(htmlspecialchars($form['description'])) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="app-alert app-alert-success">
                Заявка успешно отправлена<?= !empty($applicationId) ? ' (№' . (int)$applicationId . ')' : '' ?>.
                Ожидайте рассмотрения.
            </div>
            <p><a href="<?= url('/applications') ?>">← К списку заявок</a></p>
        <?php elseif ($alreadyPending): ?>
            <div class="app-alert app-alert-info">У вас уже есть активная заявка по этой анкете.</div>
            <p><a href="<?= url('/applications') ?>">← К списку заявок</a></p>
        <?php elseif ($closed): ?>
            <div class="app-alert app-alert-info">Приём заявок по этой анкете сейчас закрыт.</div>
            <p><a href="<?= url('/applications') ?>">← К списку заявок</a></p>
        <?php elseif (!$user): ?>
            <div class="app-alert app-alert-info">Чтобы подать заявку, нужно <strong>войти</strong> в аккаунт.</div>
        <?php elseif (!$canSubmit): ?>
            <div class="app-alert app-alert-error">У вас нет прав на подачу этой заявки.</div>
        <?php else: ?>

            <?php if ($formErrors): ?>
                <div class="app-alert app-alert-error"><?= htmlspecialchars((string)$formErrors) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= url('/application/' . (int)$form['id'] . '/submit') ?>" enctype="multipart/form-data">
                <?php if (empty($orderedSections)): ?>
                    <p style="color:var(--muted,#999);">В этой анкете пока нет вопросов.</p>
                <?php else: ?>
                    <?php foreach ($orderedSections as $block):
                        $meta = $block['meta'];
                        $qs = $block['questions'];
                    ?>
                    <div class="app-section">
                        <?php if (!empty($meta['title'])): ?>
                            <h2 class="app-section-title"><?= htmlspecialchars($meta['title']) ?></h2>
                        <?php endif; ?>
                        <?php if (!empty($meta['description'])): ?>
                            <div class="app-section-desc"><?= htmlspecialchars($meta['description']) ?></div>
                        <?php endif; ?>
                        <div class="app-grid">
                            <?php foreach ($qs as $q): ?>
                                <?= app_render_field($q, $old, $errors) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="app-actions">
                        <button type="submit" class="btn-primary">Отправить заявку</button>
                        <a href="<?= url('/applications') ?>">К списку</a>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</main>
