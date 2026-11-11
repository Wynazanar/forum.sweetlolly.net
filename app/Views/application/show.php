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
        $html .= '<input type="file" name="' . $name . '" ' . $reqAttr . '>';
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
<link rel="stylesheet" href="<?= asset('styles/admin.css'); ?>">
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
