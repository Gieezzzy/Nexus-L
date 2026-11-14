/* Folder of files: "Show more" (cards come 48 at a time); for the people who may edit: New folder, Upload, Refresh from share, Delete;
   for SATRA / Admin a full file manager: right-click menu, multi-select (Ctrl+click), rename (F2), cut / copy / paste (Ctrl+X / C / V),
   delete files and folders (Delete), new Word / Excel / text file. Every change is made in NEXUS-L and on \\192.168.32.130. */
(function () {
  'use strict';
  var root = document.getElementById('vm'); if (!root) return;
  var base = root.dataset.base, path = root.dataset.path || '', PAGE = Number(root.dataset.page) || 48;
  var CODE = root.dataset.code || '', EDIT = root.dataset.edit === '1', MANAGE = root.dataset.manage === '1', SEARCH = root.dataset.search === '1';
  var CLIP_KEY = 'nexusl:labdocs:clip';

  function el(tag, cls, txt) { var n = document.createElement(tag); if (cls) n.className = cls; if (txt != null) n.textContent = txt; return n; }
  function csrf() { return document.querySelector('meta[name="X-CSRF-TOKEN"]'); }
  function send(url, body, form) {
    var meta = csrf(), h = { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta ? meta.content : '' };
    if (!form) h['Content-Type'] = 'application/json';
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: h, body: form || JSON.stringify(body || {}) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'The server answered with an error (' + r.status + ').' }; }); })
      .then(function (j) { if (meta && j.csrf) meta.content = j.csrf; return j; })
      .catch(function () { return { ok: false, message: 'The server could not be reached.' }; });
  }
  function status(txt, bad) {
    var n = document.getElementById('fl-status');
    if (!n) { n = el('p', 'ld-hint'); n.id = 'fl-status'; n.setAttribute('role', 'status'); var bar = root.querySelector('.ld-bar'); bar.parentNode.insertBefore(n, bar.nextSibling); }
    n.textContent = txt; n.hidden = !txt; n.style.color = bad ? '#9b1c1c' : '';
  }
  function dialog(title, text, field, okLabel, danger, value) {          // a small question: resolves to the typed text / true, or null when cancelled
    return new Promise(function (res) {
      var ov = el('div', 'ld-dlg'), box = el('div', 'ld-dlg-b');
      ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true');
      box.appendChild(el('h3', '', title)); if (text) box.appendChild(el('p', '', text));
      var inp = null; if (field) { inp = el('input', 'vm-search'); inp.type = 'text'; inp.placeholder = field; inp.maxLength = 120; inp.style.cssText = 'width:100%;box-sizing:border-box;margin-bottom:12px'; if (value) inp.value = value; box.appendChild(inp); }
      var bar = el('div', 'ld-dlg-r'), no = el('button', 'ld-btn', 'Cancel'), yes = el('button', 'ld-btn ld-primary', okLabel); no.type = yes.type = 'button';
      if (danger) yes.style.cssText = 'background:#b42318;border-color:#912018';
      bar.appendChild(no); bar.appendChild(yes); box.appendChild(bar); ov.appendChild(box); document.body.appendChild(ov);
      if (inp) { inp.focus(); var dot = value ? value.lastIndexOf('.') : -1; inp.setSelectionRange(0, dot > 0 ? dot : inp.value.length); } else no.focus();
      function end(v) { ov.remove(); res(v); }
      no.onclick = function () { end(null); }; yes.onclick = function () { end(inp ? inp.value.trim() : true); };
      ov.addEventListener('keydown', function (e) { e.stopPropagation(); if (e.key === 'Escape') end(null); if (e.key === 'Enter') end(inp ? inp.value.trim() : true); });
      ov.addEventListener('click', function (e) { if (e.target === ov) end(null); });
    });
  }
  function op(body, okMsg) {
    status('Working…');
    return send(base + '/files/op', body).then(function (j) {
      if (!j.ok) { status(j.message || 'Not done.', true); return j; }
      status((j.message || okMsg || 'Done.') + (j.pending ? ' The share is updated within seconds.' : ''));
      setTimeout(function () { location.reload(); }, 700);
      return j;
    });
  }

  // ---- cards 48 at a time
  var more = document.getElementById('fl-more'), left = document.getElementById('fl-left');
  if (more) more.onclick = function () {
    var hid = root.querySelectorAll('.fl-card[hidden]');
    for (var i = 0; i < Math.min(PAGE, hid.length); i++) hid[i].hidden = false;
    var n = root.querySelectorAll('.fl-card[hidden]').length;
    if (left) left.textContent = n; if (!n) more.parentNode.remove();
  };

  // ---- the toolbar actions (also used by the right-click menu)
  var fi = document.getElementById('fl-file');
  function newFolder() {
    dialog('New folder', path ? 'It is made inside “' + path + '”.' : 'It is made at the top of this register.', 'Folder name', 'Create').then(function (name) {
      if (!name) return;
      send(base + '/files/folder', { p: path, name: name }).then(function (j) { if (j.ok) location.reload(); else status(j.message || 'The folder was not made.', true); });
    });
  }
  function newFile(type) {
    var label = { txt: 'Text document', docx: 'Word document', xlsx: 'Excel workbook' }[type];
    dialog('New ' + label, path ? 'It is made inside “' + path + '”.' : 'It is made at the top of this register.', 'File name', 'Create', false, 'New ' + label).then(function (name) {
      if (!name) return; op({ op: 'new-file', p: path, name: name, type: type }, label + ' made.');
    });
  }
  function pickType() {
    var ov = el('div', 'ld-dlg'), box = el('div', 'ld-dlg-b'); ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true');
    box.appendChild(el('h3', '', 'New file'));
    [['docx', 'Word document (.docx)'], ['xlsx', 'Excel workbook (.xlsx)'], ['txt', 'Text document (.txt)']].forEach(function (t) {
      var b = el('button', 'ld-btn', t[1]); b.type = 'button'; b.style.cssText = 'display:block;width:100%;margin:0 0 8px;text-align:left';
      b.onclick = function () { ov.remove(); newFile(t[0]); }; box.appendChild(b);
    });
    var c = el('button', 'ld-btn', 'Cancel'); c.type = 'button'; c.onclick = function () { ov.remove(); }; box.appendChild(c);
    ov.addEventListener('click', function (e) { if (e.target === ov) ov.remove(); }); ov.addEventListener('keydown', function (e) { if (e.key === 'Escape') ov.remove(); });
    ov.appendChild(box); document.body.appendChild(ov); box.querySelector('button').focus();
  }
  function upload() { if (fi) fi.click(); }
  function refresh() {
    var rf = document.getElementById('fl-refresh'); if (rf) rf.disabled = true; status('Looking in the folder on the share…');
    send(base + '/files/refresh', {}).then(function (j) {
      if (rf) rf.disabled = false;
      if (!j.ok) { status(j.message || 'The share could not be read.', true); return; }
      status(j['new'] + ' new and ' + j.changed + ' changed file' + (j['new'] + j.changed === 1 ? '' : 's') + ' taken in' + (j.skipped ? ', ' + j.skipped + ' too big to take in' : '') + '.');
      if (j['new'] + j.changed) setTimeout(function () { location.reload(); }, 1200);
    });
  }
  var nf = document.getElementById('fl-newfolder'), nfi = document.getElementById('fl-newfile'), up = document.getElementById('fl-upload'), rf0 = document.getElementById('fl-refresh');
  if (nf) nf.onclick = newFolder;
  if (nfi) nfi.onclick = pickType;
  if (rf0) rf0.onclick = refresh;
  if (up && fi) {
    up.onclick = upload;
    fi.onchange = function () {
      if (!fi.files.length) return;
      var fd = new FormData(); fd.append('p', path); for (var i = 0; i < fi.files.length; i++) fd.append('files[]', fi.files[i]);
      up.disabled = true; status('Uploading ' + fi.files.length + ' file' + (fi.files.length === 1 ? '' : 's') + '…');
      send(base + '/files/upload', null, fd).then(function (j) {
        up.disabled = false; fi.value = '';
        if (j.ok) { status((j.done || []).length + ' uploaded.' + ((j.bad || []).length ? ' Not uploaded: ' + j.bad.join(', ') : '')); setTimeout(function () { location.reload(); }, 900); }
        else status(j.message || 'Nothing was uploaded.', true);
      });
    };
  }

  // ---- selection (Ctrl / Shift + click), like Explorer
  function items() { return Array.prototype.slice.call(root.querySelectorAll('.fl-card:not([hidden]), .fl-folder')); }
  function selected() { return Array.prototype.slice.call(root.querySelectorAll('.fl-sel')); }
  function clearSel() { selected().forEach(function (n) { n.classList.remove('fl-sel'); n.setAttribute('aria-selected', 'false'); }); }
  function select(n, add) { if (!add) clearSel(); n.classList.add('fl-sel'); n.setAttribute('aria-selected', 'true'); anchor = n; }
  var anchor = null;
  root.addEventListener('click', function (e) {
    var n = e.target.closest('.fl-card, .fl-folder'); if (!n || e.target.closest('[data-del]')) return;
    if (!MANAGE || !(e.ctrlKey || e.metaKey || e.shiftKey)) return;          // a plain click opens, as before
    e.preventDefault();
    if (e.shiftKey && anchor) {
      var list = items(), a = list.indexOf(anchor), b = list.indexOf(n);
      clearSel(); list.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function (x) { x.classList.add('fl-sel'); });
    } else if (n.classList.contains('fl-sel')) { n.classList.remove('fl-sel'); } else select(n, true);
  });
  document.addEventListener('mousedown', function (e) { if (!e.target.closest('.fl-card, .fl-folder, .fl-ctx, .ld-dlg') && e.button === 0) clearSel(); });

  function pick(nodes) {                        // {ids, folders, names}
    var r = { ids: [], folders: [], names: [] };
    nodes.forEach(function (n) {
      if (n.classList.contains('fl-folder')) { r.folders.push(n.dataset.path); r.names.push(n.dataset.name + ' (folder)'); }
      else { r.ids.push(Number(n.dataset.id)); r.names.push(n.dataset.name); }
    });
    return r;
  }

  // ---- clipboard (this browser tab)
  function clip() { try { return JSON.parse(sessionStorage.getItem(CLIP_KEY) || 'null'); } catch (e) { return null; } }
  function setClip(mode, nodes) {
    var p = pick(nodes); if (!p.ids.length && !p.folders.length) return;
    try { sessionStorage.setItem(CLIP_KEY, JSON.stringify({ mode: mode, code: CODE, ids: p.ids, folders: p.folders, names: p.names })); } catch (e) {}
    root.querySelectorAll('.fl-cut').forEach(function (n) { n.classList.remove('fl-cut'); });
    if (mode === 'cut') nodes.forEach(function (n) { n.classList.add('fl-cut'); });
    status((mode === 'cut' ? 'Cut: ' : 'Copied: ') + p.names.join(', ') + '. Open the folder you want and Paste (right-click or Ctrl+V).');
  }
  function paste(into) {
    var c = clip(); if (!c) { status('Nothing to paste. Cut or copy a file or folder first.', true); return; }
    if (c.code !== CODE) { status('What you copied is in another register (' + c.code + '). Paste it into a folder of that register.', true); return; }
    op({ op: 'paste', mode: c.mode, ids: c.ids, folders: c.folders, to: into, from_code: c.code }, (c.mode === 'cut' ? 'Moved' : 'Copied') + ' into ' + (into || 'the top folder') + '.')
      .then(function (j) { if (j.ok && c.mode === 'cut') { try { sessionStorage.removeItem(CLIP_KEY); } catch (e) {} } });
  }

  // ---- delete / rename
  function del(nodes) {
    if (!nodes.length) return;
    var p = pick(nodes), many = p.names.length > 1;
    var msg = (p.folders.length ? 'Folders are deleted with everything inside. ' : '') + 'It is deleted in NEXUS-L and on \\\\192.168.32.130 within seconds. A copy stays in the NEXUS-L database and the deletion is logged.';
    dialog('Delete ' + (many ? p.names.length + ' items' : '“' + p.names[0] + '”') + '?', msg, null, 'Delete', true).then(function (ok) {
      if (!ok) return;
      if (MANAGE) { op({ op: 'delete', ids: p.ids, folders: p.folders }, 'Deleted.'); return; }
      if (p.folders.length) { status('Only SATRA and Admin can delete folders.', true); return; }
      Promise.all(p.ids.map(function (id) { return send(base + '/files/' + id + '/delete', {}); })).then(function (rs) {
        var bad = rs.filter(function (j) { return !j.ok; });
        if (bad.length) status(bad[0].message || 'Not deleted.', true); else { nodes.forEach(function (n) { n.remove(); }); status('Deleted. The share is updated within seconds.'); }
      });
    });
  }
  function rename(n) {
    var isFolder = n.classList.contains('fl-folder'), cur = n.dataset.name;
    dialog('Rename ' + (isFolder ? 'folder' : 'file'), 'The new name is used in NEXUS-L and on \\\\192.168.32.130.', 'New name', 'Rename', false, cur).then(function (name) {
      if (!name || name === cur) return;
      op(isFolder ? { op: 'rename-folder', folders: [n.dataset.path], name: name } : { op: 'rename-file', id: Number(n.dataset.id), name: name }, 'Renamed.');
    });
  }
  function props(n) {
    var isFolder = n.classList.contains('fl-folder');
    var lines = isFolder
      ? ['Folder: ' + n.dataset.path, 'Register: ' + CODE]
      : ['Name: ' + n.dataset.name, 'Folder: ' + (n.dataset.folder || '(top)'), 'Size: ' + (n.dataset.size || '-'), 'Modified: ' + (n.dataset.date || '-'), 'Register: ' + CODE];
    dialog('Properties', lines.join('\n'), null, 'Close').then(function () {});
  }
  function copyLink(n) {
    var url = new URL(n.getAttribute('href') || n.dataset.url, location.href).href;
    (navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () { status('Link copied.'); }, function () { dialog('Link', url, null, 'Close'); });
  }

  // ---- the right-click menu
  var menu = null;
  function closeMenu() { if (menu) { menu.remove(); menu = null; } }
  function item(label, key, fn, opts) {
    var b = el('button', 'fl-ctx-i' + (opts && opts.danger ? ' danger' : ''), null); b.type = 'button'; b.setAttribute('role', 'menuitem');
    b.appendChild(el('span', '', label)); if (key) b.appendChild(el('kbd', '', key));
    if (opts && opts.disabled) b.disabled = true;
    b.onclick = function () { closeMenu(); fn(); }; return b;
  }
  function sep() { return el('div', 'fl-ctx-sep'); }
  function openMenu(x, y, target) {
    closeMenu();
    menu = el('div', 'fl-ctx glass'); menu.setAttribute('role', 'menu');
    var c = clip(), canPaste = MANAGE && c && c.code === CODE && !SEARCH;
    if (target) {
      var sel = selected(); if (sel.indexOf(target) < 0) { select(target, false); sel = [target]; }
      var one = sel.length === 1, isFolder = target.classList.contains('fl-folder');
      if (one) {
        menu.appendChild(item('Open', 'Enter', function () { location.href = target.getAttribute('href') || target.dataset.url; }));
        menu.appendChild(item('Open in new tab', '', function () { window.open(target.getAttribute('href') || target.dataset.url, '_blank', 'noopener'); }));
        if (!isFolder) menu.appendChild(item('Download', '', function () { location.href = base + '/f/' + target.dataset.id + '?dl=1'; }));
      } else {
        menu.appendChild(el('div', 'fl-ctx-h', sel.length + ' selected'));
      }
      if (MANAGE) {
        menu.appendChild(sep());
        menu.appendChild(item('Cut', 'Ctrl+X', function () { setClip('cut', sel); }));
        menu.appendChild(item('Copy', 'Ctrl+C', function () { setClip('copy', sel); }));
        if (one && isFolder) menu.appendChild(item('Paste into this folder', '', function () { paste(target.dataset.path); }, { disabled: !c || c.code !== CODE }));
        if (one) menu.appendChild(item('Rename', 'F2', function () { rename(target); }));
      }
      if (MANAGE || (EDIT && !sel.some(function (n) { return n.classList.contains('fl-folder'); }))) {
        menu.appendChild(sep());
        menu.appendChild(item('Delete', 'Del', function () { del(sel); }, { danger: true }));
      }
      if (one) { menu.appendChild(sep()); menu.appendChild(item('Copy link', '', function () { copyLink(target); })); menu.appendChild(item('Properties', '', function () { props(target); })); }
    } else {
      clearSel();
      if (EDIT && !SEARCH) {
        menu.appendChild(item('New folder', '', newFolder));
        if (MANAGE) { menu.appendChild(item('New Word document', '', function () { newFile('docx'); })); menu.appendChild(item('New Excel workbook', '', function () { newFile('xlsx'); })); menu.appendChild(item('New text document', '', function () { newFile('txt'); })); }
        menu.appendChild(item('Upload files…', '', upload));
        menu.appendChild(sep());
      }
      if (MANAGE) menu.appendChild(item('Paste' + (c && c.code === CODE ? ' (' + c.names.length + ')' : ''), 'Ctrl+V', function () { paste(path); }, { disabled: !canPaste }));
      if (MANAGE) menu.appendChild(item('Select all', 'Ctrl+A', function () { items().forEach(function (n) { n.classList.add('fl-sel'); }); }));
      if (EDIT) menu.appendChild(item('Refresh from share', '', refresh));
      if (!menu.children.length) { menu = null; return false; }
    }
    document.body.appendChild(menu);
    var w = menu.offsetWidth, h = menu.offsetHeight;
    menu.style.left = Math.max(6, Math.min(x, window.innerWidth - w - 6)) + 'px';
    menu.style.top = Math.max(6, Math.min(y, window.innerHeight - h - 6)) + 'px';
    var first = menu.querySelector('button:not([disabled])'); if (first) first.focus();
    return true;
  }
  root.addEventListener('contextmenu', function (e) {
    if (!EDIT && !e.target.closest('.fl-card, .fl-folder')) return;          // viewers keep the browser menu on empty space
    var t = e.target.closest('.fl-card, .fl-folder');
    if (openMenu(e.clientX, e.clientY, t) !== false) e.preventDefault();
  });
  document.addEventListener('mousedown', function (e) { if (menu && !menu.contains(e.target)) closeMenu(); });
  window.addEventListener('scroll', closeMenu, { passive: true });
  window.addEventListener('blur', closeMenu);
  document.addEventListener('keydown', function (e) {
    if (menu) {
      if (e.key === 'Escape') { closeMenu(); return; }
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        var bs = Array.prototype.slice.call(menu.querySelectorAll('button:not([disabled])')), i = bs.indexOf(document.activeElement);
        e.preventDefault(); bs[(i + (e.key === 'ArrowDown' ? 1 : bs.length - 1)) % bs.length].focus(); return;
      }
    }
    if (e.target.closest('input, textarea, select, [contenteditable], .ld-dlg')) return;
    var sel = selected(), k = e.key.toLowerCase(), ctrl = e.ctrlKey || e.metaKey;
    if (MANAGE && ctrl && k === 'a' && !SEARCH) { e.preventDefault(); items().forEach(function (n) { n.classList.add('fl-sel'); }); }
    else if (MANAGE && ctrl && k === 'c' && sel.length && !window.getSelection().toString()) { e.preventDefault(); setClip('copy', sel); }
    else if (MANAGE && ctrl && k === 'x' && sel.length) { e.preventDefault(); setClip('cut', sel); }
    else if (MANAGE && ctrl && k === 'v' && !SEARCH) { e.preventDefault(); paste(path); }
    else if ((MANAGE || EDIT) && e.key === 'Delete' && sel.length) { e.preventDefault(); del(sel); }
    else if (MANAGE && e.key === 'F2' && sel.length === 1) { e.preventDefault(); rename(sel[0]); }
    else if (e.key === 'Escape') clearSel();
  });
  // the menu key / Shift+F10 on a focused card
  root.addEventListener('keydown', function (e) {
    if (!(e.key === 'ContextMenu' || (e.shiftKey && e.key === 'F10'))) return;
    var t = e.target.closest('.fl-card, .fl-folder'); if (!t) return;
    e.preventDefault(); var r = t.getBoundingClientRect(); openMenu(r.left + 20, r.top + 20, t);
  });

  // ---- the trash button on a card (editors)
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-del]'); if (!b) return;
    e.preventDefault(); e.stopPropagation();
    var card = b.closest('.fl-card'); if (card) del([card]);
  }, true);

  // ---- a cut item still waiting to be pasted looks faded
  var c0 = clip();
  if (c0 && c0.mode === 'cut' && c0.code === CODE) {
    root.querySelectorAll('.fl-card').forEach(function (n) { if (c0.ids.indexOf(Number(n.dataset.id)) >= 0) n.classList.add('fl-cut'); });
    root.querySelectorAll('.fl-folder').forEach(function (n) { if (c0.folders.indexOf(n.dataset.path) >= 0) n.classList.add('fl-cut'); });
  }
})();
