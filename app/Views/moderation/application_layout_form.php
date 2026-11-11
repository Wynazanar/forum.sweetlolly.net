<?php
$mode = $mode ?? 'create';
$item = $item ?? [];
$errors = $errors ?? [];

$settings = $item['settings'] ?? [];
if (is_string($settings)) {
    $settings = json_decode($settings, true) ?: [];
}
$sections = $settings['sections'] ?? [];
if (!is_array($sections)) $sections = [];
?>
<link rel="stylesheet" href="<?= asset('styles/admin.css'); ?>">
<main>
    <div class="container">
        <div class="page-header flex">
            <div>
                <h2><?= $mode === 'edit' ? 'Редактирование макета' : 'Создание макета' ?></h2>
                <p>Заявки / Макеты · Разделы и сетка для вопросов анкеты.</p>
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
                  ? url('/moderation/applications/layouts/update/' . (int)($item['id'] ?? 0))
                  : url('/moderation/applications/layouts/store') ?>"
              class="display-layout"
              id="layout-form">

            <div class="section-form">
                <div class="form-section">
                    <h3>Основные параметры</h3>

                    <div class="form-group">
                        <label>Название *</label>
                        <input type="text" name="name" required
                               value="<?= htmlspecialchars($item['name'] ?? '') ?>"
                               placeholder="Например: Заявка в команду">
                    </div>

                    <div class="form-group">
                        <label>Описание</label>
                        <input type="text" name="description"
                               value="<?= htmlspecialchars($item['description'] ?? '') ?>"
                               placeholder="Кратко">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Slug</label>
                            <input type="text" name="slug"
                                   value="<?= htmlspecialchars($item['slug'] ?? '') ?>"
                                   placeholder="Авто из названия">
                        </div>
                        <div class="form-group">
                            <label>Колонок в сетке</label>
                            <input type="number" name="columns_count" min="1" max="24"
                                   value="<?= (int)($item['columns_count'] ?? 12) ?>">
                        </div>
                        <div class="form-group">
                            <label>Отступ (px)</label>
                            <input type="number" name="gap" min="0" max="64"
                                   value="<?= (int)($item['gap'] ?? 16) ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Разделы *</h3>
                    <p class="text-muted" style="font-size:13px;margin-bottom:12px;">
                        Нужен хотя бы один раздел. В шаблоне заявки вопросы привязываются к разделам.
                    </p>

                    <div id="sections-list"></div>
                    <button type="button" class="btn btn-outline" id="add-section" style="margin-top:10px;">
                        ＋ Добавить раздел
                    </button>
                    <input type="hidden" name="sections" id="sections-json" value="">
                </div>
            </div>

            <div class="form-actions">
                <h3>Сохранение</h3>
                <div class="form-action-btn">
                    <button type="submit" class="btn-form-primary">
                        <?= $mode === 'edit' ? 'Сохранить' : 'Создать макет' ?>
                    </button>
                    <a href="<?= url('/moderation/applications/layouts') ?>" class="btn-form-secondary">Отмена</a>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
(function () {
    let sections = <?= json_encode($sections, JSON_UNESCAPED_UNICODE) ?> || [];
    const listEl = document.getElementById('sections-list');
    const hiddenEl = document.getElementById('sections-json');

    function uid() {
        return 'sec_' + Math.random().toString(36).slice(2, 10);
    }

    function sync() {
        const cards = listEl.querySelectorAll('.section-card');
        sections = [];
        cards.forEach((card, idx) => {
            const title = card.querySelector('[data-f=title]').value.trim();
            const description = card.querySelector('[data-f=description]').value.trim();
            const id = card.dataset.id || uid();
            if (!title) return;
            sections.push({ id, title, description: description || null, order: idx + 1 });
        });
        hiddenEl.value = JSON.stringify(sections);
    }

    function escapeAttr(s) {
        return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
    }

    function render() {
        listEl.innerHTML = '';
        if (!sections.length) {
            listEl.innerHTML = '<p class="text-muted">Добавьте первый раздел (например: «Кто вы»).</p>';
        }
        sections.forEach((sec, idx) => {
            const card = document.createElement('div');
            card.className = 'section-card form-section';
            card.dataset.id = sec.id || uid();
            card.style.cssText = 'border:1px solid var(--border,#333);border-radius:8px;padding:12px;margin-bottom:10px;';
            card.innerHTML = `
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Название раздела *</label>
                        <input type="text" data-f="title" value="${escapeAttr(sec.title || '')}" placeholder="Кто вы">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>ID</label>
                        <input type="text" value="${escapeAttr(sec.id || '')}" disabled style="opacity:.7;">
                    </div>
                </div>
                <div class="form-group">
                    <label>Описание</label>
                    <input type="text" data-f="description" value="${escapeAttr(sec.description || '')}" placeholder="Необязательно">
                </div>
                <div style="text-align:right;display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" class="btn btn-outline btn-sm move-up" ${idx===0?'disabled':''}>↑</button>
                    <button type="button" class="btn btn-outline btn-sm move-down">↓</button>
                    <button type="button" class="btn btn-outline btn-danger btn-sm remove-sec">Удалить</button>
                </div>
            `;
            card.querySelectorAll('input').forEach(el => {
                el.addEventListener('input', sync);
                el.addEventListener('change', sync);
            });
            card.querySelector('.remove-sec').addEventListener('click', () => {
                card.remove();
                sync();
                if (!listEl.querySelector('.section-card')) {
                    listEl.innerHTML = '<p class="text-muted">Добавьте первый раздел.</p>';
                }
            });
            card.querySelector('.move-up').addEventListener('click', () => {
                sync();
                const i = sections.findIndex(s => s.id === card.dataset.id);
                if (i > 0) {
                    [sections[i-1], sections[i]] = [sections[i], sections[i-1]];
                    render();
                }
            });
            card.querySelector('.move-down').addEventListener('click', () => {
                sync();
                const i = sections.findIndex(s => s.id === card.dataset.id);
                if (i >= 0 && i < sections.length - 1) {
                    [sections[i], sections[i+1]] = [sections[i+1], sections[i]];
                    render();
                }
            });
            listEl.appendChild(card);
        });
        sync();
    }

    document.getElementById('add-section').addEventListener('click', () => {
        sections.push({ id: uid(), title: '', description: null, order: sections.length + 1 });
        render();
        const last = listEl.querySelector('.section-card:last-child input[data-f=title]');
        if (last) last.focus();
    });

    document.getElementById('layout-form').addEventListener('submit', () => sync());

    if (!sections.length) {
        sections.push({ id: uid(), title: 'Основное', description: null, order: 1 });
    }
    render();
})();
</script>
