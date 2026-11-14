
(function (global) {
  function parseBlocks(input) {
    try { return JSON.parse(input.value || '[]'); } catch (e) { return []; }
  }

  function mount(root) {
    if (!root || root.dataset.mounted === '1') return;
    root.dataset.mounted = '1';
    var blocksEl = root.querySelector('.sl-editor__blocks');
    var input = root.querySelector('.sl-editor__input');
    var typeSelect = root.querySelector('.sl-editor__block-type');
    var uploadUrl = root.getAttribute('data-upload-url') || '/attachments/upload';
    var mode = root.getAttribute('data-mode') || 'full';
    var blocks = parseBlocks(input);
    if (!blocks.length) blocks = [{ type: 'text', md: '' }];

    function sync() {
      input.value = JSON.stringify(blocks);
    }

    function uploadFile(file) {
      var fd = new FormData();
      fd.append('file', file);
      fd.append('context', 'editor');
      return fetch(uploadUrl, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      }).then(function (r) { return r.json(); });
    }

    function ensureTextBlock() {
      if (!blocks.length || blocks[0].type !== 'text') {
        blocks.unshift({ type: 'text', md: '' });
      }
    }

    function addImageBlock(layout) {
      layout = layout || '1';
      blocks.push({ type: 'images', layout: String(layout), items: [] });
      sync();
      render();
    }

    function render() {
      blocksEl.innerHTML = '';
      blocks.forEach(function (block, index) {
        var el = document.createElement('div');
        el.className = 'sl-block';
        el.dataset.index = String(index);

        if (mode === 'full') {
          var bar = document.createElement('div');
          bar.className = 'sl-block__bar';
          bar.innerHTML =
            '<span>' + (block.type === 'images' ? ('Фото ×' + (block.layout || '1')) : 'Текст') + '</span>' +
            '<button type="button" data-act="up">↑</button>' +
            '<button type="button" data-act="down">↓</button>' +
            '<button type="button" data-act="remove">✕</button>';
          el.appendChild(bar);
          bar.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) return;
            var act = btn.getAttribute('data-act');
            if (act === 'remove') {
              blocks.splice(index, 1);
              if (!blocks.length) blocks.push({ type: 'text', md: '' });
              sync(); render();
            } else if (act === 'up' && index > 0) {
              var t = blocks[index - 1]; blocks[index - 1] = blocks[index]; blocks[index] = t;
              sync(); render();
            } else if (act === 'down' && index < blocks.length - 1) {
              var t2 = blocks[index + 1]; blocks[index + 1] = blocks[index]; blocks[index] = t2;
              sync(); render();
            }
          });
        }

        if (block.type === 'text') {
          var ta = document.createElement('textarea');
          ta.placeholder = blocksEl.dataset.placeholder || '';
          ta.value = block.md || '';
          if (mode === 'compact') {
            ta.rows = 5;
            ta.className = 'sl-editor__main-text';
          }
          ta.addEventListener('input', function () {
            blocks[index].md = ta.value;
            sync();
          });
          el.appendChild(ta);
        } else if (block.type === 'images') {
          var layout = String(block.layout || '1');
          var n = parseInt(layout, 10) || 1;
          if (!block.items) block.items = [];
          while (block.items.length < n) block.items.push({ attachment_id: 0, href: '', url: '' });
          block.items = block.items.slice(0, n);

          if (mode === 'compact') {
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'sl-editor__btn';
            rm.textContent = 'Убрать фото';
            rm.style.marginBottom = '6px';
            rm.addEventListener('click', function () {
              blocks.splice(index, 1);
              sync(); render();
            });
            el.appendChild(rm);
          }

          var grid = document.createElement('div');
          grid.className = 'sl-images-grid layout-' + layout;
          for (var i = 0; i < n; i++) {
            (function (slotIndex) {
              var item = block.items[slotIndex] || { attachment_id: 0, href: '', url: '' };
              var slot = document.createElement('div');
              slot.className = 'sl-image-slot' + (item.attachment_id ? ' has-image' : '');

              var preview = document.createElement('div');
              preview.className = 'sl-image-preview';
              if (item.url || item.attachment_id) {
                var src = item.url || (uploadUrl.replace(/\/upload$/, '') + '/' + item.attachment_id + '/download');
                preview.innerHTML = '<img src="' + src + '" alt="">';
              } else {
                preview.innerHTML = '<span class="sl-image-placeholder">Выбрать фото</span>';
              }
              slot.appendChild(preview);

              var fileInput = document.createElement('input');
              fileInput.type = 'file';
              fileInput.accept = 'image/png,image/jpeg,image/gif,image/webp';
              fileInput.hidden = true;

              var hrefInput = document.createElement('input');
              hrefInput.type = 'text';
              hrefInput.placeholder = 'Ссылка по клику (необяз.)';
              hrefInput.value = item.href || '';
              hrefInput.addEventListener('input', function () {
                blocks[index].items[slotIndex].href = hrefInput.value;
                sync();
              });

              var status = document.createElement('div');
              status.className = 'sl-image-status';

              preview.style.cursor = 'pointer';
              preview.addEventListener('click', function () { fileInput.click(); });

              fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                if (!file) return;
                status.textContent = 'Загрузка…';
                uploadFile(file).then(function (res) {
                  if (!res || !res.ok) {
                    status.textContent = (res && res.error) ? res.error : 'Ошибка';
                    return;
                  }
                  blocks[index].items[slotIndex].attachment_id = res.attachment_id;
                  blocks[index].items[slotIndex].url = res.url || res.path || '';
                  sync();
                  render();
                }).catch(function () {
                  status.textContent = 'Ошибка сети';
                });
              });

              slot.appendChild(fileInput);
              slot.appendChild(hrefInput);
              slot.appendChild(status);
              grid.appendChild(slot);
            })(i);
          }
          el.appendChild(grid);
        }

        blocksEl.appendChild(el);
      });
      sync();
    }

    var addBlockBtn = root.querySelector('[data-cmd="add-block"]');
    if (addBlockBtn) {
      addBlockBtn.addEventListener('click', function () {
        var v = typeSelect ? typeSelect.value : 'text';
        if (v === 'text') blocks.push({ type: 'text', md: '' });
        else if (v.indexOf('images-') === 0) {
          blocks.push({ type: 'images', layout: v.split('-')[1] || '1', items: [] });
        }
        sync(); render();
      });
    }

    var addImageBtn = root.querySelector('[data-cmd="add-image"]');
    if (addImageBtn) {
      addImageBtn.addEventListener('click', function () {
        addImageBlock('1');
      });
    }

    var attachInput = root.querySelector('.sl-editor__attach-input');
    if (attachInput) {
      attachInput.addEventListener('change', function () {
        var file = attachInput.files && attachInput.files[0];
        if (!file) return;
        uploadFile(file).then(function (res) {
          if (!res || !res.ok) {
            alert((res && res.error) || 'Ошибка загрузки');
            return;
          }
          blocks.push({
            type: 'images',
            layout: '1',
            items: [{ attachment_id: res.attachment_id, url: res.url || res.path || '', href: '' }]
          });
          sync();
          render();
          attachInput.value = '';
        });
      });
    }

    function wrapActiveText(before, after) {
      var ta = blocksEl.querySelector('.sl-block.is-active textarea') || blocksEl.querySelector('textarea');
      if (!ta) return;
      var start = ta.selectionStart, end = ta.selectionEnd;
      var val = ta.value;
      var selected = val.slice(start, end) || 'текст';
      ta.value = val.slice(0, start) + before + selected + after + val.slice(end);
      ta.dispatchEvent(new Event('input'));
      ta.focus();
    }

    root.querySelectorAll('[data-cmd="bold"]').forEach(function (b) {
      b.addEventListener('click', function () { wrapActiveText('**', '**'); });
    });
    root.querySelectorAll('[data-cmd="italic"]').forEach(function (b) {
      b.addEventListener('click', function () { wrapActiveText('*', '*'); });
    });
    root.querySelectorAll('[data-cmd="link"]').forEach(function (b) {
      b.addEventListener('click', function () {
        var url = prompt('URL ссылки', 'https://');
        if (!url) return;
        wrapActiveText('[', '](' + url + ')');
      });
    });

    blocksEl.addEventListener('focusin', function (e) {
      var block = e.target.closest('.sl-block');
      blocksEl.querySelectorAll('.sl-block').forEach(function (b) { b.classList.remove('is-active'); });
      if (block) block.classList.add('is-active');
    });

    render();
  }

  global.SlEditor = { mount: mount };
})(window);
