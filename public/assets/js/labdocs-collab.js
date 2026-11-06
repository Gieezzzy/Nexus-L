/* People on the same document: a stack of initials in the top bar (you on top), each person's cursor shown as a box in their own colour,
   a notice when somebody saves a newer version, and the "newer version" question when you save over it.
   Pages call LdCollab.bind({root, posOf(el), find(pos)}) so the cursor positions mean something to them, LdCollab.post() to save and LdCollab.adopt() after their own save. */
(function () {
  'use strict';
  var node = document.getElementById('ld-collab'), C = null;
  try { C = node ? JSON.parse(node.textContent) : null; } catch (e) { C = null; }
  var adapter = null, users = [], myPos = '', token = C ? C.token : '', lastBy = '', adoptNext = false, stack = null, banner = null, layer = null;

  function el(tag, cls, txt) { var n = document.createElement(tag); if (cls) n.className = cls; if (txt != null) n.textContent = txt; return n; }
  function csrf() { return document.querySelector('meta[name="X-CSRF-TOKEN"]'); }
  function initials(n) { var p = String(n || '?').trim().split(/\s+/); return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase(); }

  // ------------------------------------------------------------------ saving with the "newer version" question
  function ask(j) {
    return new Promise(function (res) {
      var ov = el('div', 'ld-dlg'), box = el('div', 'ld-dlg-b');
      ov.setAttribute('role', 'alertdialog'); ov.setAttribute('aria-modal', 'true');
      box.appendChild(el('h3', '', 'A newer version exists'));
      box.appendChild(el('p', '', (j.by ? j.by : 'Somebody') + ' saved this document' + (j.at ? ' at ' + j.at : '') + ' after you opened it. Saving now replaces their version with yours; theirs is kept in the Revisions list.'));
      var bar = el('div', 'ld-dlg-r'), a = el('button', 'ld-btn', 'Reload their version'), b = el('button', 'ld-btn', 'Cancel'), c = el('button', 'ld-btn ld-primary', 'Save mine anyway');
      a.type = b.type = c.type = 'button'; bar.appendChild(a); bar.appendChild(b); bar.appendChild(c); box.appendChild(bar); ov.appendChild(box); document.body.appendChild(ov); b.focus();
      function end(v) { ov.remove(); res(v); }
      a.onclick = function () { end('reload'); }; b.onclick = function () { end('cancel'); }; c.onclick = function () { end('force'); };
      ov.addEventListener('keydown', function (e) { if (e.key === 'Escape') end('cancel'); });
    });
  }
  function post(url, body) {
    var meta = csrf();
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta ? meta.content : '' }, body: JSON.stringify(body || {}) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'The server answered with an error (' + r.status + ').' }; }); })
      .then(function (j) {
        if (meta && j.csrf) meta.content = j.csrf;
        if (j && j.conflict) {
          return ask(j).then(function (v) {
            if (v === 'force') { var b2 = {}; Object.keys(body || {}).forEach(function (k) { b2[k] = body[k]; }); b2.force = true; return post(url, b2); }
            if (v === 'reload') { location.reload(); return new Promise(function () {}); }
            return { ok: false, message: 'Not saved: a newer version exists. Your changes are still on the screen.' };
          });
        }
        return j;
      })
      .catch(function () { return { ok: false, message: 'The server could not be reached. Nothing was saved.' }; });
  }

  // ------------------------------------------------------------------ people
  function dot(u, size) {
    var d = el('span', 'ld-av', initials(u.name)); d.style.background = u.color; if (size) { d.style.width = d.style.height = size + 'px'; }
    d.title = u.name + (u.me ? ' (you)' : ''); return d;
  }
  function paintStack() {
    if (!stack) return;
    stack.innerHTML = '';
    var me = users.filter(function (u) { return u.me; })[0] || { name: C.me.name, color: C.me.color, me: true };
    var others = users.filter(function (u) { return !u.me; }), shown = others.slice(0, 4);
    var list = [me].concat(shown);
    list.forEach(function (u, i) { var d = dot(u); d.style.zIndex = String(20 - i); if (i) d.style.marginLeft = '-12px'; if (u.editing && !u.me) d.classList.add('is-editing'); stack.appendChild(d); });
    if (others.length > 4) { var more = el('span', 'ld-av ld-av-more', '+' + (others.length - 4)); more.style.marginLeft = '-12px'; stack.appendChild(more); }
    stack.setAttribute('aria-label', (others.length ? others.length + ' other ' + (others.length === 1 ? 'person has' : 'people have') + ' this document open' : 'Only you have this document open'));
    stack.title = others.length ? 'Who has this document open' : 'Only you have this document open';
  }
  function people() {
    var ov = el('div', 'ld-dlg'), box = el('div', 'ld-dlg-b ld-who-dlg');
    ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true');
    box.appendChild(el('h3', '', 'Open on this document now'));
    var ul = el('ul', 'ld-who-l');
    users.slice().sort(function (a, b) { return (b.me ? 1 : 0) - (a.me ? 1 : 0); }).forEach(function (u) {
      var li = el('li'); li.appendChild(dot(u, 34));
      var t = el('span', 'ld-who-t'); t.appendChild(el('b', '', u.name + (u.me ? ' (you)' : ''))); t.appendChild(el('span', '', (u.roles || '') + (u.editing ? ' · editing' : '') + (u.pos && !u.me ? ' · ' + (adapter && adapter.describe ? adapter.describe(u.pos) : '') : '')));
      li.appendChild(t); ul.appendChild(li);
    });
    box.appendChild(ul);
    var bar = el('div', 'ld-dlg-r'), ok = el('button', 'ld-btn ld-primary', 'Close'); ok.type = 'button'; bar.appendChild(ok); box.appendChild(bar); ov.appendChild(box); document.body.appendChild(ov); ok.focus();
    function end() { ov.remove(); document.removeEventListener('keydown', esc); }
    function esc(e) { if (e.key === 'Escape') end(); }
    document.addEventListener('keydown', esc); ok.onclick = end; ov.addEventListener('click', function (e) { if (e.target === ov) end(); });
  }
  function notice(txt, withReload) {
    if (!banner) { banner = el('div', 'ld-newer'); banner.setAttribute('role', 'status'); var bar = document.querySelector('.ld-bar'); if (bar && bar.parentNode) bar.parentNode.insertBefore(banner, bar.nextSibling); else document.body.prepend(banner); }
    banner.innerHTML = ''; banner.appendChild(el('span', '', txt));
    if (withReload) { var b = el('button', 'ld-btn', 'Reload'); b.type = 'button'; b.onclick = function () { location.reload(); }; banner.appendChild(b); }
    banner.hidden = false;
  }

  // ------------------------------------------------------------------ cursors of the others
  function drawCursors() {
    if (!adapter || !adapter.find) return;
    if (!layer) { layer = el('div', 'ld-cursors'); layer.setAttribute('aria-hidden', 'true'); document.body.appendChild(layer); }
    layer.innerHTML = '';
    users.forEach(function (u) {
      if (u.me || !u.pos) return;
      var t = adapter.find(u.pos); if (!t) return;
      var r = t.getBoundingClientRect(); if (r.width === 0 && r.height === 0) return;
      if (r.bottom < 0 || r.top > window.innerHeight || r.right < 0 || r.left > window.innerWidth) return;
      var box = el('div', 'ld-cur'); box.style.cssText = 'left:' + r.left + 'px;top:' + r.top + 'px;width:' + r.width + 'px;height:' + r.height + 'px;border-color:' + u.color + ';background:' + u.color + '1f';
      var tag = el('span', 'ld-cur-t', u.name); tag.style.background = u.color; box.appendChild(tag); layer.appendChild(box);
    });
  }
  var raf = 0;
  function redraw() { if (raf) return; raf = requestAnimationFrame(function () { raf = 0; drawCursors(); }); }
  window.addEventListener('scroll', redraw, true); window.addEventListener('resize', redraw);

  // ------------------------------------------------------------------ polling
  function tick() {
    if (!C) return;
    var body = { doc: C.doc, pos: myPos, editing: !!(adapter && adapter.editing && adapter.editing()) };
    post(C.url, body).then(function (j) {
      if (!j || !j.ok) return;
      users = j.users || []; paintStack(); drawCursors();
      if (adoptNext) { token = j.token; adoptNext = false; }
      else if (j.token !== token && token !== '') {
        var someone = users.filter(function (u) { return !u.me; }).length > 0;
        notice((j.by && j.by !== C.me.name ? j.by : 'Somebody') + ' saved a newer version of this document' + (someone ? '' : '') + '. Reload to see it' + ((adapter && adapter.editing && adapter.editing()) ? '; your unsaved changes would be lost, so save or copy them first.' : '.'), true);
      }
    });
  }
  function loop() { tick(); setTimeout(loop, document.hidden ? 12000 : 2500); }

  function bind(a) {
    adapter = a;
    if (a && a.root) {
      var set = function (e) { var p = a.posOf ? a.posOf(e.target) : null; if (p) myPos = p; };
      a.root.addEventListener('focusin', set, true); a.root.addEventListener('click', set, true); a.root.addEventListener('keyup', set, true);
    }
  }

  window.LdCollab = { post: post, bind: bind, redraw: redraw, adopt: function () { adoptNext = true; if (banner) banner.hidden = true; } };

  if (C && !C.ro) {                                                  // a revision shown beside the original is not an open document
    var barR = document.querySelector('.ld-bar-r');
    if (barR) {
      stack = el('button', 'ld-who'); stack.type = 'button'; stack.addEventListener('click', people);
      barR.insertBefore(stack, barR.firstChild);
      paintStack();
    }
    loop();
  }
})();
