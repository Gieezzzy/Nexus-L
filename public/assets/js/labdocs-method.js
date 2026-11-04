/* A VF test method as a normal, locked form (US Letter pages, the format of the PDF). Edit turns the text editable. */
(function () {
  'use strict';
  var dataEl = document.getElementById('vd-data');
  if (!dataEl) return;
  var D = JSON.parse(dataEl.textContent);
  var MM = 96 / 25.4;
  var LAND = D.doc.orient === 'landscape';                          // a specification sheet is landscape, a test method portrait (US Letter)
  var PW = LAND ? 279.4 : 215.9, PH = LAND ? 215.9 : 279.4, ML = LAND ? 12 : 19, MT = LAND ? 26 : 30, MB = LAND ? 10 : 16;
  var BODY_W = (PW - 2 * ML) * MM, BODY_H = (PH - MT - MB) * MM;
  var root = document.getElementById('vd');
  var pagesEl = document.getElementById('vd-pages'), measureEl = document.getElementById('vd-measure'), pagerEl = document.getElementById('vd-pager');
  var statusEl = document.getElementById('vd-status'), ctx = document.getElementById('vd-ctx');
  var blocks = clone(D.doc.blocks), backup = null, edit = false, dirty = false, page = 0, showAll = false, sheets = [], timer = null;
  measureEl.className = 'ld-measure vd-pages';

  function clone(x) { return JSON.parse(JSON.stringify(x)); }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function setStatus(msg, bad) { statusEl.textContent = msg; statusEl.style.color = bad ? '#b91c1c' : ''; }
  root.style.setProperty('--pw', PW + 'mm'); root.style.setProperty('--ph', PH + 'mm'); root.style.setProperty('--ml', ML + 'mm'); root.style.setProperty('--mt', MT + 'mm'); root.style.setProperty('--mb', MB + 'mm');
  var st = document.createElement('style');
  st.textContent = '@page{size:letter ' + (LAND ? 'landscape' : 'portrait') + ';margin:0}';
  document.head.appendChild(st);

  // ---------------------------------------------------------------------------------------------- blocks
  function blockNode(b, i, editable, measuring) {
    var n;
    if (b.t === 'img') {
      n = el('div', 'vb-img'); var im = el('img'); im.src = D.assets + b.src; im.alt = ''; im.style.width = 'min(' + b.w + 'pt,100%)'; n.appendChild(im);
    } else if (b.t === 'table') {
      n = el('div', 'vb-tbl'); var t = el('table', 'vt' + (b.box ? ' box' : '') + (b.plain ? ' plain' : '') + (b.spec ? ' spec' : ''));
      if (b.cw) { var cg = el('colgroup'); b.cw.forEach(function (w) { var k = el('col'); k.style.width = w + '%'; cg.appendChild(k); }); t.appendChild(cg); t.style.tableLayout = 'fixed'; }
      b.rows.forEach(function (r, ri) {
        var tr = el('tr');
        if (b.rh && b.rh[ri]) tr.style.height = Math.max(8, b.rh[ri]) + 'pt';
        r.forEach(function (c, ci) {
          var td = el('td'), o = typeof c === 'string' ? { h: c } : c;
          td.innerHTML = o.h || ''; td.dataset.r = String(ri); td.dataset.c = String(ci);
          if (o.cs > 1) td.colSpan = o.cs;
          if (o.rs > 1) td.rowSpan = o.rs;
          if (o.bg && /^#[0-9a-fA-F]{6}$/.test(o.bg)) td.style.backgroundColor = o.bg;
          if (o.al === 'c') td.style.textAlign = 'center';
          if (editable) td.contentEditable = 'true';
          tr.appendChild(td);
        });
        t.appendChild(tr);
      });
      n.appendChild(t);
      if (b.spec && fitZ[i] && !measuring) n.style.zoom = String(fitZ[i]);       // a specification table taller than the page is shrunk to fit it
    } else {
      n = el('div', 'vb' + (b.list ? ' li' : '') + (b.al === 'c' ? ' c' : b.al === 'r' ? ' r' : ''));
      n.style.paddingLeft = b.list ? 'calc(' + (b.ind || 0) + 'pt + 1.6em)' : (b.ind || 0) + 'pt';
      n.innerHTML = b.html || '';
      if (editable) n.contentEditable = 'true';
    }
    n.dataset.i = String(i);
    return n;
  }
  function isHeading(b) { return b && b.t === 'p' && /^\s*<b>[^<]{2,90}<\/b>\s*:?\s*$/.test(b.html || ''); }

  function sheetNode(idx, no, total, editable) {
    var s = el('div', 'ld-sheet');
    var h = el('div', 'vd-head');
    var lg = el('img'); lg.src = D.logo; lg.alt = ''; h.appendChild(lg);
    h.appendChild(el('div', 'vt', no === 1 ? (D.method.header || 'VF Asia Footwear Laboratory Testing Manual') : (D.method.spec ? (D.method.header || D.method.title) : D.method.code + ' ' + D.method.title)));
    s.appendChild(h);
    var body = el('div', 'vd-body');
    idx.forEach(function (i) { body.appendChild(blockNode(blocks[i], i, editable)); });
    s.appendChild(body);
    var f = el('div', 'vd-foot');
    f.appendChild(el('span', null, 'VF Asia Footwear Performance Testing Manual'));
    f.appendChild(el('span', null, String(no)));
    s.appendChild(f);
    return s;
  }

  // ---------------------------------------------------------------------------------------------- paging
  var fitZ = {};
  function paginate() {
    fitZ = {};
    measureEl.innerHTML = '';
    var s = el('div', 'ld-sheet'); s.style.cssText = 'display:block;height:auto;overflow:visible;margin:0';
    var body = el('div', 'vd-body'); body.style.cssText = 'position:static;width:' + BODY_W + 'px;height:auto;overflow:visible';
    blocks.forEach(function (b, i) { body.appendChild(blockNode(b, i, false, true)); });
    s.appendChild(body); measureEl.appendChild(s);
    var kids = body.children, hs = [], top = body.getBoundingClientRect().top, prev = top;
    for (var k = 0; k < kids.length; k++) { var bt = kids[k].getBoundingClientRect().bottom; hs.push(bt - prev); prev = bt; }
    var pages = [[]], used = 0;
    hs.forEach(function (h, i) {
      if (blocks[i].spec && blocks[i].t === 'table' && used + h > BODY_H) {
        var room = BODY_H - used - 2;
        if (used && room / h >= 0.7) { fitZ[i] = room / h; h = room; }
        else if (h > BODY_H - 2) { fitZ[i] = (BODY_H - 2) / h; h = BODY_H - 2; }
      }
      var need = h + (isHeading(blocks[i]) && hs[i + 1] ? Math.min(hs[i + 1], 40) : 0);       // a heading never ends a page alone
      if (used + need > BODY_H && pages[pages.length - 1].length) { pages.push([]); used = 0; }
      pages[pages.length - 1].push(i); used += h;
    });
    measureEl.innerHTML = '';
    return pages;
  }

  function render() {
    var pages = paginate();
    if (page >= pages.length) page = pages.length - 1;
    pagesEl.innerHTML = '';
    sheets = pages.map(function (idx, n) { var s = sheetNode(idx, n + 1, pages.length, edit); pagesEl.appendChild(s); return s; });
    pagesEl.classList.toggle('ld-all', showAll);
    root.classList.toggle('vd-edit', edit);
    pager(pages.length); show(page); fit();
  }
  function show(n) {
    page = Math.max(0, Math.min(sheets.length - 1, n));
    sheets.forEach(function (s, i) { s.classList.toggle('is-active', i === page); });
    Array.prototype.forEach.call(pagerEl.querySelectorAll('[data-p]'), function (b) { b.classList.toggle('on', !showAll && Number(b.dataset.p) === page); });
  }
  function pager(total) {
    pagerEl.innerHTML = '';
    function btn(txt, fn, extra) { var b = el('button', 'ld-btn', txt); b.type = 'button'; b.onclick = fn; if (extra) Object.keys(extra).forEach(function (k) { b.setAttribute(k, extra[k]); }); pagerEl.appendChild(b); return b; }
    btn('‹', function () { showAll = false; pagesEl.classList.remove('ld-all'); show(page - 1); }, { 'aria-label': 'Previous page' });
    for (var i = 0; i < total; i++) (function (i) { var b = btn('Page ' + (i + 1), function () { showAll = false; pagesEl.classList.remove('ld-all'); show(i); }); b.dataset.p = String(i); })(i);
    btn('›', function () { showAll = false; pagesEl.classList.remove('ld-all'); show(page + 1); }, { 'aria-label': 'Next page' });
    btn('All', function () { showAll = !showAll; pagesEl.classList.toggle('ld-all', showAll); show(page); });
    pagerEl.appendChild(el('span', 'ld-of', total + (total === 1 ? ' page' : ' pages') + ' · Letter'));
  }
  var zoom;
  function fit() {
    if (!zoom) zoom = LdZoom(document.getElementById('vd-stage'), pagesEl, function () { return PW * MM; });
    zoom.fit();
  }

  // ---------------------------------------------------------------------------------------------- editing
  function clean(html) { return html.replace(/<div><br><\/div>/gi, '<br>').replace(/<div>/gi, '<br>').replace(/<\/div>/gi, '').replace(/&nbsp;/g, ' ').replace(/ /g, ' '); }
  function mark() { dirty = true; }
  pagesEl.addEventListener('input', function (e) {
    if (!edit) return;
    var td = e.target.closest('td'), n = e.target.closest('[data-i]'); if (!n) return;
    var b = blocks[Number(n.dataset.i)]; mark();
    if (td && b.t === 'table') setCell(b, td);
    else if (b.t === 'p') b.html = clean(n.innerHTML);
  });
  function setCell(b, td) {
    var r = Number(td.dataset.r), c = Number(td.dataset.c), v = clean(td.innerHTML);
    if (typeof b.rows[r][c] === 'string') b.rows[r][c] = v; else b.rows[r][c].h = v;
  }
  pagesEl.addEventListener('focusout', function () {
    if (!edit) return;
    clearTimeout(timer);
    timer = setTimeout(function () {
      var a = document.activeElement;
      if (a && a.closest && (a.closest('#vd-pages') && a.isContentEditable || a.closest('#vd-ctx') || a.closest('#vd-fmt'))) return;
      render();
    }, 250);
  });
  pagesEl.addEventListener('paste', function (e) {
    if (!edit) return;
    e.preventDefault();
    document.execCommand('insertText', false, ((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\r/g, ''));
  });
  pagesEl.addEventListener('keydown', function (e) {                 // Enter starts a new paragraph, like in a document
    if (!edit || e.key !== 'Enter' || e.shiftKey) return;
    var n = e.target.closest('.vb'); if (!n) return;
    e.preventDefault();
    var i = Number(n.dataset.i), sel = window.getSelection(); if (!sel.rangeCount) return;
    var r = sel.getRangeAt(0), tail = document.createRange();
    tail.setStart(r.endContainer, r.endOffset); tail.setEnd(n, n.childNodes.length);
    var tmp = el('div'); tmp.appendChild(tail.extractContents());
    blocks[i].html = clean(n.innerHTML);
    var nb = { t: 'p', ind: blocks[i].ind, list: blocks[i].list, al: blocks[i].al, sz: blocks[i].sz, html: clean(tmp.innerHTML) };
    blocks.splice(i + 1, 0, nb); mark(); focusBlock(i + 1, true);
  });
  function focusBlock(i, start) {
    render();
    var n = pagesEl.querySelector('[data-i="' + i + '"]');
    if (!n) return;
    show(sheets.indexOf(n.closest('.ld-sheet')));
    var t = n.isContentEditable ? n : n.querySelector('[contenteditable]'); if (!t) return;
    t.focus();
    var r = document.createRange(); r.selectNodeContents(t); r.collapse(!!start);
    var s = window.getSelection(); s.removeAllRanges(); s.addRange(r);
  }
  function insertP(at) { blocks.splice(at, 0, { t: 'p', ind: 0, list: false, al: 'l', sz: 0, html: '' }); mark(); focusBlock(at, true); }
  function del(i) { if (!confirm('Delete this ' + (blocks[i].t === 'p' ? 'paragraph' : blocks[i].t === 'img' ? 'picture' : 'table') + '?')) return; blocks.splice(i, 1); mark(); render(); }
  function move(i, d) { var j = i + d; if (j < 0 || j >= blocks.length) return; var t = blocks[i]; blocks[i] = blocks[j]; blocks[j] = t; mark(); render(); }
  function tbl(i, act, r, c) {
    var b = blocks[i], cols = b.rows[0].length;
    if (b.span) { alert('This table has merged cells: its text can be edited, but rows and columns cannot be added or deleted here.'); return; }
    if (act === 'rowdown') b.rows.splice(r + 1, 0, new Array(cols).fill('')); if (b.rh) b.rh.splice(r + 1, 0, 0);
    if (act === 'rowdel') { if (b.rows.length <= 1) { del(i); return; } b.rows.splice(r, 1); if (b.rh) b.rh.splice(r, 1); }
    if (act === 'coladd') { b.rows.forEach(function (row) { row.splice(c + 1, 0, ''); }); if (b.cw) b.cw.splice(c + 1, 0, 10); }
    if (act === 'coldel') { if (cols <= 1) { del(i); return; } b.rows.forEach(function (row) { row.splice(c, 1); }); if (b.cw) b.cw.splice(c, 1); }
    mark(); render();
  }
  function openCtx(x, y, items) {
    ctx.innerHTML = '';
    items.forEach(function (it) {
      if (it === '-') { ctx.appendChild(el('hr')); return; }
      var li = el('li', null, it.label); li.setAttribute('role', 'menuitem');
      if (it.off) li.setAttribute('aria-disabled', 'true'); else li.onclick = function () { closeCtx(); it.run(); };
      ctx.appendChild(li);
    });
    ctx.hidden = false;
    ctx.style.left = Math.min(x, window.innerWidth - ctx.offsetWidth - 8) + 'px';
    ctx.style.top = Math.min(y, window.innerHeight - ctx.offsetHeight - 8) + 'px';
  }
  function closeCtx() { ctx.hidden = true; }
  document.addEventListener('click', function (e) { if (!ctx.contains(e.target)) closeCtx(); });
  window.addEventListener('scroll', closeCtx, true);
  pagesEl.addEventListener('contextmenu', function (e) {
    if (!edit) return;
    var n = e.target.closest('[data-i]'); if (!n) return;
    e.preventDefault();
    var i = Number(n.dataset.i), b = blocks[i], td = e.target.closest('td'), last = blocks.length - 1;
    var items = [
      { label: 'Insert paragraph above', run: function () { insertP(i); } },
      { label: 'Insert paragraph below', run: function () { insertP(i + 1); } },
      { label: 'Move up', off: i === 0, run: function () { move(i, -1); } },
      { label: 'Move down', off: i === last, run: function () { move(i, 1); } },
      { label: 'Delete ' + (b.t === 'p' ? 'paragraph' : b.t === 'img' ? 'picture' : 'table'), run: function () { del(i); } }
    ];
    if (b.t === 'p') {
      items.push('-',
        { label: b.list ? 'Remove the hanging indent (list)' : 'Make it a list item', run: function () { b.list = !b.list; mark(); render(); } },
        { label: 'Indent more', run: function () { b.ind = (b.ind || 0) + 18; mark(); render(); } },
        { label: 'Indent less', off: !b.ind, run: function () { b.ind = Math.max(0, (b.ind || 0) - 18); mark(); render(); } },
        { label: b.al === 'c' ? 'Align left' : 'Centre', run: function () { b.al = b.al === 'c' ? 'l' : 'c'; mark(); render(); } });
    }
    if (b.t === 'table' && td) {
      var r = Number(td.dataset.r), c = Number(td.dataset.c);
      items.push('-', { label: 'Add row below', run: function () { tbl(i, 'rowdown', r, c); } }, { label: 'Delete row', run: function () { tbl(i, 'rowdel', r, c); } },
        { label: 'Add column right', run: function () { tbl(i, 'coladd', r, c); } }, { label: 'Delete column', run: function () { tbl(i, 'coldel', r, c); } });
    }
    openCtx(e.clientX, e.clientY, items);
  });
  document.getElementById('vd-fmt').addEventListener('mousedown', function (e) { e.preventDefault(); });   // keep the selection while pressing B / I / U
  document.getElementById('vd-fmt').addEventListener('click', function (e) {
    var b = e.target.closest('[data-cmd]'); if (!b || !edit) return;
    document.execCommand(b.dataset.cmd, false, null);
    var n = document.activeElement && document.activeElement.closest('[data-i]');
    if (n) { var bl = blocks[Number(n.dataset.i)], td = document.activeElement.closest('td'); mark();
      if (td && bl.t === 'table') setCell(bl, td); else if (bl.t === 'p') bl.html = clean(n.innerHTML); }
  });

  // ---------------------------------------------------------------------------------------------- toolbar
  function menu(btnId, menuId) {
    var b = document.getElementById(btnId), m = document.getElementById(menuId); if (!b || !m) return;
    b.onclick = function (e) { e.stopPropagation(); var open = m.hidden; document.querySelectorAll('#vd .ld-drop .ld-menu').forEach(function (x) { x.hidden = true; }); m.hidden = !open; b.setAttribute('aria-expanded', String(open)); };
    document.addEventListener('click', function (e) { if (!m.contains(e.target) && e.target !== b) { m.hidden = true; b.setAttribute('aria-expanded', 'false'); } });
  }
  menu('vd-ill-btn', 'vd-ill-menu'); menu('vd-more-btn', 'vd-more-menu');
  var editBtn = document.getElementById('vd-edit'), saveBtn = document.getElementById('vd-save'), cancelBtn = document.getElementById('vd-cancel');
  var hint = document.getElementById('vd-hint'), fmt = document.getElementById('vd-fmt');
  function setEdit(on) {
    edit = on; dirty = false;
    if (editBtn) editBtn.hidden = on;
    if (saveBtn) saveBtn.hidden = !on;
    if (cancelBtn) cancelBtn.hidden = !on;
    hint.hidden = !on; fmt.hidden = !on;
    render();
  }
  if (editBtn) editBtn.onclick = function () { backup = clone(blocks); setEdit(true); setStatus('Editing. Changes are kept only when you press Save.'); };
  if (cancelBtn) cancelBtn.onclick = function () { if (dirty && !confirm('Discard the changes?')) return; blocks = backup ? clone(backup) : blocks; setEdit(false); setStatus('Changes discarded.'); };
  function post(url, body, done) {
    var meta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
    fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta ? meta.content : '' }, body: JSON.stringify(body || {}) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'The server answered with an error (' + r.status + ').' }; }); })
      .then(function (j) { if (meta && j.csrf) meta.content = j.csrf; done(j); })
      .catch(function () { done({ ok: false, message: 'The server could not be reached. Nothing was saved.' }); });
  }
  if (saveBtn) saveBtn.onclick = function () {
    if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
    saveBtn.disabled = true; setStatus('Saving…');
    post(D.saveUrl, { blocks: blocks.filter(function (b) { return !(b.t === 'p' && !String(b.html || '').replace(/<br>/g, '').trim()); }) }, function (j) {
      saveBtn.disabled = false;
      if (!j.ok) { setStatus(j.message || 'Not saved.', true); return; }
      blocks = blocks.filter(function (b) { return !(b.t === 'p' && !String(b.html || '').replace(/<br>/g, '').trim()); });
      backup = null; setEdit(false);
      setStatus('Saved in the database as revision ' + j.revision + '. The original PDF is unchanged and stays available with "Open PDF".');
    });
  };
  var restore = document.getElementById('vd-restore');
  if (restore) restore.onclick = function () {
    if (!confirm('Put the document converted from the original PDF back? Your edits stay in the history.')) return;
    post(D.restoreUrl, {}, function (j) { if (j.ok) location.reload(); else setStatus(j.message || 'Not restored.', true); });
  };
  window.addEventListener('beforeunload', function (e) { if (edit && dirty) { e.preventDefault(); e.returnValue = ''; } });
  document.getElementById('vd-print').onclick = function () {
    if (pdfOn && pdfFrame && pdfFrame.contentWindow) { try { pdfFrame.contentWindow.focus(); pdfFrame.contentWindow.print(); return; } catch (e) {} }
    if (edit && document.activeElement) document.activeElement.blur();
    var was = showAll; showAll = true; pagesEl.classList.add('ld-all');
    setTimeout(function () { window.print(); showAll = was; pagesEl.classList.toggle('ld-all', showAll); show(page); }, 60);
  };
  // two ways to look at the document: the PDF itself (exactly as it is, with the browser's own zoom, search and print) or the editable version rebuilt from it
  var modeBtn = document.getElementById('vd-mode'), pdfFrame = null, pdfOn = false;
  function setMode(pdf) {
    pdfOn = pdf;
    var stage = document.getElementById('vd-stage');
    if (pdf && !pdfFrame) {
      pdfFrame = document.createElement('iframe'); pdfFrame.className = 'vd-pdfframe'; pdfFrame.title = 'PDF';
      pdfFrame.src = D.pdfUrl + '#view=FitH'; stage.parentNode.insertBefore(pdfFrame, stage.nextSibling);
    }
    if (pdfFrame) pdfFrame.hidden = !pdf;
    stage.hidden = pdf; pagerEl.hidden = pdf; root.classList.toggle('vd-pdfmode', pdf);
    document.querySelectorAll('.ld-find, .bk-zoom').forEach(function (n) { n.hidden = pdf; });
    if (modeBtn) { modeBtn.textContent = pdf ? 'Editable view' : 'PDF view'; modeBtn.title = pdf ? 'The document rebuilt as text you can edit' : 'The PDF exactly as it is'; }
    if (!pdf) { window.dispatchEvent(new Event('resize')); }
  }
  if (modeBtn) modeBtn.onclick = function () { setMode(!pdfOn); };
  if (editBtn) editBtn.addEventListener('click', function () { if (pdfOn) setMode(false); }, true);          // editing always happens in the editable view
  render();
  if (modeBtn && !D.method.isRev && !D.method.revisionNo) setMode(true);                        // an unedited document opens as the PDF
})();
