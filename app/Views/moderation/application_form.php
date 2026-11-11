<?php
$mode          = $mode ?? 'create';
$item          = $item ?? [];
$errors        = $errors ?? [];
$layoutOptions = $layoutOptions ?? [];
$layoutsMeta   = $layoutsMeta ?? [];
$typeOptions   = $typeOptions ?? ['' => '— Не указан —', 'team' => 'В команду', 'unban' => 'Апелляция / Разбан', 'other' => 'Другое'];
$statusOptions = $statusOptions ?? ['draft' => 'Черновик', 'published' => 'Опубликован', 'archived' => 'В архиве'];
$roleOptions   = $roleOptions ?? [];

$questions = $item['questions'] ?? [];
if (!is_array($questions)) $questions = [];
$allowed = $item['allowed_submitters'] ?? [];
if (!is_array($allowed)) $allowed = [];
$visibility = $item['visibility'] ?? [];
if (!is_array($visibility)) $visibility = [];
$notify = $item['notify_user_ids'] ?? [];
if (!is_array($notify)) $notify = [];
?>
<link rel="stylesheet" href="<?= asset('styles/admin.css'); ?>">
<main>
    <div class="container">
        <div class="page-header flex">
            <div>
                <h2><?= $mode === 'edit' ? 'Редактирование шаблона' : 'Создание шаблона заявки' ?></h2>
                <p>Заявки · Макет, разделы и визуальная сетка вопросов.</p>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger" style="margin-bottom:16px;">
                <ul style="margin:0;padding-left:18px;">
                    <?php foreach ($errors as $msg): ?>
                        <li><?= htmlspecialchars(is_array($msg) ? implode(', ', $msg) : (string)$msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post"
              action="<?= $mode === 'edit'
                  ? url('/moderation/applications/update/' . (int)($item['id'] ?? 0))
                  : url('/moderation/applications/store') ?>"
              class="display-layout"
              id="application-form">

            <div class="section-form">
                <div class="form-section">
                    <h3>Основные настройки</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Название *</label>
                            <input type="text" name="name" required value="<?= htmlspecialchars($item['name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Тип заявки</label>
                            <select name="application_type">
                                <?php foreach ($typeOptions as $val => $label): ?>
                                    <option value="<?= htmlspecialchars((string)$val) ?>" <?= (string)($item['application_type'] ?? '') === (string)$val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Описание для игроков</label>
                        <textarea name="description" rows="3"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                    </div>

                    <!-- <div class="form-group">
                        <label>Slug</label>
                        <input type="text" name="slug" value="<?= htmlspecialchars($item['slug'] ?? '') ?>" placeholder="Авто">
                    </div> -->

                    <div class="form-row" style="align-items: start;">
                        <div class="form-group">
                            <label>Макет *</label>
                            <select name="layout_id" id="layout_id">
                                <option value="">— Выберите макет —</option>
                                <?php foreach ($layoutOptions as $id => $title): ?>
                                    <option value="<?= (int)$id ?>" <?= (int)($item['layout_id'] ?? 0) === (int)$id ? 'selected' : '' ?>><?= htmlspecialchars($title) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-muted" style="font-size:12px;margin-top:4px;">
                                <a href="<?= url('/moderation/applications/layouts/create') ?>">Создать макет</a>
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Статус</label>
                            <select name="status">
                                <?php foreach ($statusOptions as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= ($item['status'] ?? 'draft') === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Кто может отправлять</h3>
                    <div class="form-group" style="display:flex;flex-wrap:wrap;gap:12px;">
                        <?php foreach ($roleOptions as $rid => $rlabel): ?>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="allowed_submitters[]" value="<?= (int)$rid ?>"
                                    <?= in_array((int)$rid, array_map('intval', $allowed), true) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($rlabel) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Кто видит заявки</h3>
                    <div class="form-group" style="display:flex;flex-wrap:wrap;gap:12px;">
                        <?php foreach ($roleOptions as $rid => $rlabel): ?>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="visibility[]" value="<?= (int)$rid ?>"
                                    <?= in_array((int)$rid, array_map('intval', $visibility), true) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($rlabel) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Уведомления</h3>
                    <div class="form-group">
                        <label>ID пользователей через запятую</label>
                        <input type="text" name="notify_user_ids" value="<?= htmlspecialchars(implode(', ', $notify)) ?>" placeholder="1, 5, 12">
                    </div>
                </div>

                <div class="form-section">
                    <h3>Вопросы · визуальная сетка</h3>
                    <p class="text-muted" style="font-size:13px;margin-bottom:12px;">
                        Выберите макет → появятся разделы. Перетаскивайте вопросы по сетке, меняйте ширину кнопками.
                    </p>

                    <div class="ve-toolbar">
                        <button type="button" class="btn btn-outline" id="ve-add-q">＋ Добавить вопрос</button>
                        <span class="text-muted" style="font-size:12px;" id="ve-hint">Сначала выберите макет</span>
                    </div>

                    <div id="ve-root" class="ve-wrap" style="margin-top:12px;"></div>
                    <input type="hidden" name="questions" id="questions-json" value="">
                </div>
            </div>

            <div class="form-actions">
                <h3>Параметры публикации</h3>
                
                <div class="form-toggles">
                    <label class="toggle">
                        <span>Принимать новые заявки</span>
                        <input type="checkbox" name="accepting" value="1" <?= !empty($item['accepting']) ? 'checked' : '' ?>>
                        <span class="switch"></span>
                    </label>
                </div>

                <p id="form-notes">Публикация создаст шаблон анкеты, а не заявку от имени игрока. Права на роль автоматически не выдаются.</p>

                <div class="form-action-btn">
                    <button type="submit" class="btn-form-primary"><?= $mode === 'edit' ? 'Сохранить изменения' : 'Создать шаблон' ?></button>
                    <a href="<?= url('/moderation/applications') ?>?sub=forms" class="btn-form-secondary">Отмена</a>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
(function () {
    const fieldTypes = [
        { value: 'text', label: 'Текст' },
        { value: 'textarea', label: 'Многострочный' },
        { value: 'select', label: 'Список' },
        { value: 'radio', label: 'Радио' },
        { value: 'checkbox', label: 'Чекбоксы' },
        { value: 'number', label: 'Число' },
        { value: 'email', label: 'Email' },
        { value: 'url', label: 'URL' },
        { value: 'date', label: 'Дата' },
        { value: 'file', label: 'Файл' },
    ];

    const layoutsMeta = <?= json_encode($layoutsMeta, JSON_UNESCAPED_UNICODE) ?> || {};
    let questions = <?= json_encode($questions, JSON_UNESCAPED_UNICODE) ?> || [];
    let selectedId = null;
    let dragId = null;

    const root = document.getElementById('ve-root');
    const hidden = document.getElementById('questions-json');
    const layoutSelect = document.getElementById('layout_id');
    const hint = document.getElementById('ve-hint');

    function uid() { return 'q_' + Math.random().toString(36).slice(2, 9); }

    function currentLayout() {
        const id = layoutSelect.value;
        return id && layoutsMeta[id] ? layoutsMeta[id] : null;
    }

    function sections() {
        const L = currentLayout();
        return L ? (L.sections || []) : [];
    }

    function cols() {
        const L = currentLayout();
        return Math.max(1, Math.min(24, (L && L.columns) || 12));
    }

    function syncHidden() {
        hidden.value = JSON.stringify(questions.filter(q => (q.label || '').trim() !== ''));
    }

    function ensurePlacement(q) {
        const secs = sections();
        if (!q.section_id && secs[0]) q.section_id = secs[0].id;
        q.col = Math.max(1, Math.min(cols(), parseInt(q.col, 10) || 1));
        q.row = Math.max(1, parseInt(q.row, 10) || 1);
        q.span = Math.max(1, Math.min(cols(), parseInt(q.span, 10) || cols()));
        q.rowSpan = Math.max(1, Math.min(20, parseInt(q.rowSpan, 10) || 1));
        return q;
    }

    function typeLabel(t) {
        const f = fieldTypes.find(x => x.value === t);
        return f ? f.label : t;
    }

    function renderCard(q, inPool) {
        const el = document.createElement('div');
        el.className = 've-card' + (selectedId === q.id ? ' is-selected' : '');
        el.draggable = true;
        el.dataset.id = q.id;
        if (!inPool) {
            el.style.gridColumn = q.col + ' / span ' + q.span;
            el.style.gridRow = q.row + ' / span ' + (q.rowSpan || 1);
        }
        el.innerHTML = `
            <div class="ve-label">${escapeHtml(q.label || 'Без названия')}</div>
            <div class="ve-meta">${escapeHtml(typeLabel(q.type || 'text'))}
                ${q.required ? ' · обяз.' : ''}
                · c${q.col} s${q.span} r${q.row}</div>
            <div class="ve-actions">
                <button type="button" data-a="span4" title="Ширина 4">¼</button>
                <button type="button" data-a="span6" title="Ширина 6">½</button>
                <button type="button" data-a="span12" title="На всю строку">1</button>
                <button type="button" data-a="taller" title="Выше">↕</button>
                <button type="button" data-a="edit">✎</button>
                <button type="button" data-a="del">✕</button>
            </div>
        `;

        el.addEventListener('click', (e) => {
            if (e.target.closest('[data-a]')) return;
            selectedId = q.id;
            render();
            openEdit(q.id);
        });

        el.addEventListener('dragstart', (e) => {
            dragId = q.id;
            e.dataTransfer.setData('text/plain', q.id);
            e.dataTransfer.effectAllowed = 'move';
        });
        el.addEventListener('dragend', () => { dragId = null; });

        el.querySelectorAll('[data-a]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const a = btn.dataset.a;
                const item = questions.find(x => x.id === q.id);
                if (!item) return;
                if (a === 'span4') item.span = Math.min(cols(), 4);
                if (a === 'span6') item.span = Math.min(cols(), 6);
                if (a === 'span12') item.span = cols();
                if (a === 'taller') item.rowSpan = Math.min(20, (item.rowSpan || 1) + 1);
                if (a === 'edit') { selectedId = item.id; openEdit(item.id); }
                if (a === 'del') {
                    questions = questions.filter(x => x.id !== item.id);
                    if (selectedId === item.id) selectedId = null;
                }
                render();
            });
        });

        return el;
    }

    function openEdit(id) {
        const panel = document.getElementById('ve-edit-panel');
        if (!panel) return;
        const q = questions.find(x => x.id === id);
        if (!q) { panel.classList.remove('is-open'); return; }
        panel.classList.add('is-open');
        panel.querySelector('[data-f=label]').value = q.label || '';
        panel.querySelector('[data-f=type]').value = q.type || 'text';
        panel.querySelector('[data-f=required]').checked = !!q.required;
        panel.querySelector('[data-f=help]').value = q.help || '';
        panel.querySelector('[data-f=col]').value = q.col || 1;
        panel.querySelector('[data-f=row]').value = q.row || 1;
        panel.querySelector('[data-f=span]').value = q.span || cols();
        panel.querySelector('[data-f=rowSpan]').value = q.rowSpan || 1;
        const secSel = panel.querySelector('[data-f=section_id]');
        secSel.innerHTML = sections().map(s =>
            `<option value="${s.id}" ${s.id === q.section_id ? 'selected' : ''}>${escapeHtml(s.title)}</option>`
        ).join('') || '<option value="">—</option>';
        const optBlock = panel.querySelector('.ve-options');
        const showOpt = ['select','radio','checkbox'].includes(q.type || 'text');
        optBlock.style.display = showOpt ? '' : 'none';
        panel.querySelector('[data-f=options]').value = (q.options || []).join('\n');
    }

    function applyEdit() {
        const panel = document.getElementById('ve-edit-panel');
        if (!panel || !selectedId) return;
        const q = questions.find(x => x.id === selectedId);
        if (!q) return;
        q.label = panel.querySelector('[data-f=label]').value.trim();
        q.type = panel.querySelector('[data-f=type]').value;
        q.required = panel.querySelector('[data-f=required]').checked;
        q.help = panel.querySelector('[data-f=help]').value.trim() || null;
        q.section_id = panel.querySelector('[data-f=section_id]').value || null;
        q.col = parseInt(panel.querySelector('[data-f=col]').value, 10) || 1;
        q.row = parseInt(panel.querySelector('[data-f=row]').value, 10) || 1;
        q.span = parseInt(panel.querySelector('[data-f=span]').value, 10) || cols();
        q.rowSpan = parseInt(panel.querySelector('[data-f=rowSpan]').value, 10) || 1;
        if (['select','radio','checkbox'].includes(q.type)) {
            q.options = panel.querySelector('[data-f=options]').value.split('\n').map(s => s.trim()).filter(Boolean);
        }
        ensurePlacement(q);
        render();
        openEdit(q.id);
    }

    function onGridDrop(sectionId, gridEl, e) {
        e.preventDefault();
        gridEl.classList.remove('is-dragover');
        const id = e.dataTransfer.getData('text/plain') || dragId;
        const q = questions.find(x => x.id === id);
        if (!q) return;

        const rect = gridEl.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const c = cols();
        const colW = rect.width / c;
        const rowH = 56; // approx
        let col = Math.floor(x / colW) + 1;
        let row = Math.floor(y / rowH) + 1;
        col = Math.max(1, Math.min(c, col));
        row = Math.max(1, row);
        // не вылезать за сетку по span
        if (col + (q.span || 1) - 1 > c) {
            col = Math.max(1, c - (q.span || 1) + 1);
        }
        q.section_id = sectionId;
        q.col = col;
        q.row = row;
        ensurePlacement(q);
        selectedId = q.id;
        render();
        openEdit(q.id);
    }

    function render() {
        const secs = sections();
        const c = cols();
        questions.forEach(ensurePlacement);

        if (!secs.length) {
            hint.textContent = 'Сначала выберите макет с разделами';
            root.innerHTML = '<p class="text-muted">Нет разделов. Создайте макет и выберите его.</p>';
            syncHidden();
            return;
        }
        hint.textContent = 'Перетащите вопрос в раздел · кнопки ¼ ½ 1 меняют ширину';

        const placedIds = new Set();
        let htmlSections = '';

        // build structure
        root.innerHTML = '';

        // edit panel (shared)
        const edit = document.createElement('div');
        edit.id = 've-edit-panel';
        edit.className = 've-edit-panel';
        edit.innerHTML = `
            <h4 style="margin:0 0 10px;">Редактирование вопроса</h4>
            <div class="form-row">
                <div class="form-group" style="flex:2"><label>Текст</label><input type="text" data-f="label"></div>
                <div class="form-group" style="flex:1"><label>Тип</label>
                    <select data-f="type">${fieldTypes.map(t => `<option value="${t.value}">${t.label}</option>`).join('')}</select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Раздел</label><select data-f="section_id"></select></div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:8px;">
                    <label style="display:flex;gap:6px;align-items:center;cursor:pointer;">
                        <input type="checkbox" data-f="required"> Обязательный
                    </label>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>col</label><input type="number" data-f="col" min="1" max="24"></div>
                <div class="form-group"><label>row</label><input type="number" data-f="row" min="1"></div>
                <div class="form-group"><label>span</label><input type="number" data-f="span" min="1" max="24"></div>
                <div class="form-group"><label>rowSpan</label><input type="number" data-f="rowSpan" min="1" max="20"></div>
            </div>
            <div class="form-group"><label>Подсказка</label><input type="text" data-f="help"></div>
            <div class="form-group ve-options"><label>Варианты (с новой строки)</label><textarea data-f="options" rows="3"></textarea></div>
            <button type="button" class="btn btn-primary btn-sm" id="ve-apply-edit">Применить</button>
        `;
        edit.querySelector('#ve-apply-edit').addEventListener('click', applyEdit);
        edit.querySelector('[data-f=type]').addEventListener('change', () => {
            const t = edit.querySelector('[data-f=type]').value;
            edit.querySelector('.ve-options').style.display = ['select','radio','checkbox'].includes(t) ? '' : 'none';
        });

        secs.forEach(sec => {
            const box = document.createElement('div');
            box.className = 've-section';
            box.innerHTML = `<h4>${escapeHtml(sec.title || sec.id)}</h4>`;
            const grid = document.createElement('div');
            grid.className = 've-grid';
            grid.style.gridTemplateColumns = `repeat(${c}, 1fr)`;
            grid.dataset.sectionId = sec.id;

            grid.addEventListener('dragover', (e) => {
                e.preventDefault();
                grid.classList.add('is-dragover');
            });
            grid.addEventListener('dragleave', () => grid.classList.remove('is-dragover'));
            grid.addEventListener('drop', (e) => onGridDrop(sec.id, grid, e));

            questions.filter(q => q.section_id === sec.id).forEach(q => {
                placedIds.add(q.id);
                grid.appendChild(renderCard(q, false));
            });

            box.appendChild(grid);
            root.appendChild(box);
        });

        // pool: вопросы без раздела или «снять с сетки»
        const poolQs = questions.filter(q => !q.section_id || !secs.some(s => s.id === q.section_id));
        const pool = document.createElement('div');
        pool.className = 've-pool';
        pool.innerHTML = `<div class="ve-pool-title">Не размещённые на сетке (перетащите в раздел)</div>`;
        const poolList = document.createElement('div');
        poolList.className = 've-pool-list';
        poolQs.forEach(q => poolList.appendChild(renderCard(q, true)));
        if (!poolQs.length) {
            poolList.innerHTML = '<span class="text-muted" style="font-size:12px;">Все вопросы размещены</span>';
        }
        pool.appendChild(poolList);
        root.appendChild(pool);
        root.appendChild(edit);

        if (selectedId) openEdit(selectedId);
        syncHidden();
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    document.getElementById('ve-add-q').addEventListener('click', () => {
        const secs = sections();
        const q = {
            id: uid(),
            type: 'text',
            label: 'Новый вопрос',
            required: false,
            section_id: secs[0]?.id || null,
            col: 1,
            row: 1,
            span: cols(),
            rowSpan: 1,
            options: [],
        };
        // поставить на следующую свободную строку в первом разделе
        if (secs[0]) {
            const inSec = questions.filter(x => x.section_id === secs[0].id);
            q.row = inSec.reduce((m, x) => Math.max(m, (x.row || 1) + (x.rowSpan || 1) - 1), 0) + 1;
        }
        questions.push(q);
        selectedId = q.id;
        render();
        openEdit(q.id);
    });

    layoutSelect.addEventListener('change', () => {
        const secs = sections();
        questions.forEach(q => {
            if (!secs.some(s => s.id === q.section_id)) {
                q.section_id = secs[0]?.id || null;
            }
            ensurePlacement(q);
        });
        render();
    });

    document.getElementById('application-form').addEventListener('submit', () => {
        syncHidden();
    });

    render();
})();
</script>
