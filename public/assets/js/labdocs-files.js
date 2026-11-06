/* Folder of files: "Show more" (cards come 48 at a time), and for the people who may edit: New folder, Upload, Refresh from share, Delete */
(function () {
  'use strict';
  var root = document.getElementById('vm'); if (!root) return;
  var base = root.dataset.base, path = root.dataset.path || '', PAGE = Number(root.dataset.page) || 48;

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
  function dialog(title, text, field, okLabel, danger) {                // a small question: resolves to the typed text / true, or null when cancelled
    return new Promise(function (res) {
      var ov = el('div', 'ld-dlg'), box = el('div', 'ld-dlg-b');
      ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true');
      box.appendChild(el('h3', '', title)); if (text) box.appendChild(el('p', '', text));
      var inp = null; if (field) { inp = el('input', 'vm-search'); inp.type = 'text'; inp.placeholder = field; inp.style.cssText = 'width:100%;box-sizing:border-box;margin-bottom:12px'; box.appendChild(inp); }
      var bar = el('div', 'ld-dlg-r'), no = el('button', 'ld-btn', 'Cancel'), yes = el('button', 'ld-btn ld-primary', okLabel); no.type = yes.type = 'button';
      if (danger) yes.style.cssText = 'background:#b42318;border-color:#912018';
      bar.appendChild(no); bar.appendChild(yes); box.appendChild(bar); ov.appendChild(box); document.body.appendChild(ov); (inp || no).focus();
      function end(v) { ov.remove(); res(v); }
      no.onclick = function () { end(null); }; yes.onclick = function () { end(inp ? inp.value.trim() : true); };
      ov.addEventListener('keydown', function (e) { if (e.key === 'Escape') end(null); if (e.key === 'Enter' && inp) end(inp.value.trim()); });
      ov.addEventListener('click', function (e) { if (e.target === ov) end(null); });
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

  // ---- editing
  var nf = document.getElementById('fl-newfolder'), up = document.getElementById('fl-upload'), rf = document.getElementById('fl-refresh'), fi = document.getElementById('fl-file');
  if (nf) nf.onclick = function () {
    dialog('New folder', path ? 'It is made inside “' + path + '”.' : 'It is made at the top of this register.', 'Folder name', 'Create').then(function (name) {
      if (!name) return;
      send(base + '/files/folder', { p: path, name: name }).then(function (j) { if (j.ok) location.reload(); else status(j.message || 'The folder was not made.', true); });
    });
  };
  if (up && fi) {
    up.onclick = function () { fi.click(); };
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
  if (rf) rf.onclick = function () {
    rf.disabled = true; status('Looking in the folder on the share…');
    send(base + '/files/refresh', {}).then(function (j) {
      rf.disabled = false;
      if (!j.ok) { status(j.message || 'The share could not be read.', true); return; }
      status(j['new'] + ' new and ' + j.changed + ' changed file' + (j['new'] + j.changed === 1 ? '' : 's') + ' taken in' + (j.skipped ? ', ' + j.skipped + ' too big to take in' : '') + '.');
      if (j['new'] + j.changed) setTimeout(function () { location.reload(); }, 1200);
    });
  };
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-del]'); if (!b) return;
    e.preventDefault(); e.stopPropagation();
    var card = b.closest('.fl-card'), name = card ? card.dataset.name : 'this file';
    dialog('Delete “' + name + '”?', 'It disappears from this list. A copy stays in the database.', null, 'Delete', true).then(function (ok) {
      if (!ok) return;
      send(base + '/files/' + b.dataset.del + '/delete', {}).then(function (j) { if (j.ok && card) card.remove(); else if (!j.ok) status(j.message || 'Not deleted.', true); });
    });
  }, true);
})();
