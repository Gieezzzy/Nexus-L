/* Laboratory Documents: a register as A4 sheets. View, edit (right-click for rows and columns), page by page, print. */
(function () {
  'use strict';
  var dataEl = document.getElementById('ld-data');
  if (!dataEl) return;
  var D = JSON.parse(dataEl.textContent);
  var MM = 96 / 25.4;
  var M = D.register.marginMm || 6.4;                      // narrow margin, mm
  var FOOT = 6;                                            // mm kept free at the bottom for "Page n of N"
  var root = document.getElementById('ld');
  var pagesEl = document.getElementById('ld-pages');
  var measureEl = document.getElementById('ld-measure');
  var pagerEl = document.getElementById('ld-pager');
  var statusEl = document.getElementById('ld-status');
  var ctx = document.getElementById('ld-ctx');
  var state = { columns: clone(D.columns), rows: clone(D.rows) };
  var backup = null, edit = false, dirty = false, page = 0, showAll = false, sheets = [], timer = null;

  function clone(x) { return JSON.parse(JSON.stringify(x)); }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function visible() { return state.columns.filter(function (c) { return c.visible; }); }
  function setStatus(msg, bad) { statusEl.textContent = msg; statusEl.style.color = bad ? '#b91c1c' : ''; }

  // A4 portrait with narrow margins; printing uses the same margin
  var st = document.createElement('style');
  st.textContent = '@page{size:A4 portrait;margin:' + M + 'mm}.ld{--ld-m:' + M + 'mm;--ld-print-h:' + (297 - 2 * M - 0.5) + 'mm}';
  document.head.appendChild(st);

  // ---------------------------------------------------------------------------------------------- one sheet
  function sheetNode(rowIdx, no, total, editable) {
    var cols = visible(), sum = cols.reduce(function (a, c) { return a + (c.width || 10); }, 0) || 1;
    var s = el('div', 'ld-sheet');
    var head = el('div', 'ld-head');
    if (D.register.logoLeft) { var l = el('img'); l.src = D.register.logoLeft; l.alt = ''; head.appendChild(l); }
    var co = el('div', 'ld-co');
    co.appendChild(el('div', 'cn', D.register.companyCn));
    co.appendChild(el('div', 'en', D.register.companyEn));
    co.appendChild(el('div', 'ad', D.register.address));
    head.appendChild(co);
    if (D.register.logoRight) { var r = el('img', 'ld-logo-r'); r.src = D.register.logoRight; r.alt = ''; head.appendChild(r); }
    s.appendChild(head);
    s.appendChild(el('div', 'ld-ttl', D.register.listTitle));
    s.appendChild(el('div', 'ld-gap'));
    var t = el('table', 'ld-t'), cg = el('colgroup');
    cols.forEach(function (c) { var k = el('col'); k.style.width = (100 * (c.width || 10) / sum).toFixed(2) + '%'; cg.appendChild(k); });
    t.appendChild(cg);
    var th = el('thead'), tr = el('tr');
    cols.forEach(function (c) {
      var h = el('th', 'a-' + (c.align === 'right' ? 'right' : c.align === 'left' ? 'left' : 'center'), c.label);
      h.dataset.k = c.key; h.dataset.r = '-1';
      if (editable) h.contentEditable = 'true';
      tr.appendChild(h);
    });
    th.appendChild(tr); t.appendChild(th);
    var tb = el('tbody');
    rowIdx.forEach(function (i) {
      var row = state.rows[i], rt = el('tr');
      cols.forEach(function (c) {
        var td = el('td', 'a-' + (c.align || 'left'), row[c.key] || '');
        td.dataset.k = c.key; td.dataset.r = String(i);
        if (editable) td.contentEditable = 'true';
        rt.appendChild(td);
      });
      tb.appendChild(rt);
    });
    t.appendChild(tb); s.appendChild(t);
    var f = el('div', 'ld-foot');
    f.appendChild(el('span', null, D.register.code + ' · ' + D.register.listTitle));
    f.appendChild(el('span', null, 'Page ' + no + ' of ' + total));
    s.appendChild(f);
    return s;
  }

  // ---------------------------------------------------------------------------------------------- paging
  // every row is measured at its real width, then rows are packed into A4 pages (header block and column titles repeat on each page)
  function paginate() {
    measureEl.innerHTML = '';
    var m = sheetNode(state.rows.map(function (_, i) { return i; }), 1, 1, false);
    m.style.width = (210 * MM) + 'px'; m.style.padding = (M * MM) + 'px';
    measureEl.appendChild(m);
    var trs = m.querySelectorAll('tbody tr');
    var tbTop = m.querySelector('tbody').getBoundingClientRect().top;
    var heights = [], prev = tbTop;
    Array.prototype.forEach.call(trs, function (r) { var b = r.getBoundingClientRect().bottom; heights.push(b - prev); prev = b; });
    var innerTop = m.getBoundingClientRect().top + M * MM;
    var capacity = (297 - M - FOOT) * MM - (tbTop - m.getBoundingClientRect().top);        // room under the column titles
    var pages = [[]], used = 0;
    heights.forEach(function (h, i) {
      if (used + h > capacity && pages[pages.length - 1].length) { pages.push([]); used = 0; }
      pages[pages.length - 1].push(i); used += h;
    });
    void innerTop;
    measureEl.innerHTML = '';
    return pages;
  }

  function render() {
    var pages = paginate();
    if (page >= pages.length) page = pages.length - 1;
    pagesEl.innerHTML = '';
    sheets = pages.map(function (idx, n) {
      var s = sheetNode(idx, n + 1, pages.length, edit);
      pagesEl.appendChild(s);
      return s;
    });
    pagesEl.classList.toggle('ld-all', showAll);
    root.classList.toggle('ld-edit', edit);
    pager(pages.length);
    show(page);
    fit();
  }

  function show(n) {
    page = Math.max(0, Math.min(sheets.length - 1, n));
    sheets.forEach(function (s, i) { s.classList.toggle('is-active', i === page); });
    Array.prototype.forEach.call(pagerEl.querySelectorAll('[data-p]'), function (b) { b.classList.toggle('on', !showAll && Number(b.dataset.p) === page); });
  }

  function pager(total) {
    pagerEl.innerHTML = '';
    var prev = el('button', 'ld-btn', '‹'); prev.type = 'button'; prev.setAttribute('aria-label', 'Previous page');
    prev.onclick = function () { showAll = false; pagesEl.classList.remove('ld-all'); show(page - 1); };
    pagerEl.appendChild(prev);
    for (var i = 0; i < total; i++) {
      (function (i) {
        var b = el('button', 'ld-btn', 'Page ' + (i + 1)); b.type = 'button'; b.dataset.p = String(i);
        b.onclick = function () { showAll = false; pagesEl.classList.remove('ld-all'); show(i); };
        pagerEl.appendChild(b);
      })(i);
    }
    var next = el('button', 'ld-btn', '›'); next.type = 'button'; next.setAttribute('aria-label', 'Next page');
    next.onclick = function () { showAll = false; pagesEl.classList.remove('ld-all'); show(page + 1); };
    pagerEl.appendChild(next);
    var all = el('button', 'ld-btn', 'All'); all.type = 'button'; all.title = 'Show every page one under the other';
    all.onclick = function () { showAll = !showAll; pagesEl.classList.toggle('ld-all', showAll); show(page); };
    pagerEl.appendChild(all);
    pagerEl.appendChild(el('span', 'ld-of', total + (total === 1 ? ' page' : ' pages') + ' · A4'));
  }

  function fit() {                                          // a narrow window scales the sheet down instead of scrolling
    var stage = document.getElementById('ld-stage');
    var w = stage.clientWidth - 8, need = 210 * MM;
    pagesEl.style.zoom = w < need ? String(Math.max(0.3, w / need)) : '1';
  }
  window.addEventListener('resize', fit);

  // ---------------------------------------------------------------------------------------------- editing
  function colIndex(key) { return state.columns.findIndex(function (c) { return c.key === key; }); }
  function text(node) { return node.innerText.replace(/ /g, ' ').replace(/\n+$/, ''); }
  function mark() { dirty = true; }

  pagesEl.addEventListener('input', function (e) {
    var n = e.target.closest('td,th'); if (!n || !edit) return;
    mark();
    if (n.tagName === 'TH') { var c = state.columns[colIndex(n.dataset.k)]; if (c) c.label = text(n); }
    else { var r = state.rows[Number(n.dataset.r)]; if (r) r[n.dataset.k] = text(n); }
  });
  pagesEl.addEventListener('focusout', function () {        // re-flow into pages once the cursor has left the sheet
    if (!edit) return;
    clearTimeout(timer);
    timer = setTimeout(function () {
      var a = document.activeElement;
      if (a && a.closest && a.closest('#ld-pages') && a.isContentEditable) return;
      if (a && a.closest && a.closest('#ld-ctx')) return;
      render();
    }, 250);
  });
  pagesEl.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { document.activeElement.blur(); }
  });
  pagesEl.addEventListener('paste', function (e) {           // plain text only
    if (!edit) return;
    e.preventDefault();
    var t = (e.clipboardData || window.clipboardData).getData('text');
    document.execCommand('insertText', false, t.replace(/\r/g, ''));
  });

  function newRow() { var r = {}; state.columns.forEach(function (c) { r[c.key] = ''; }); return r; }
  function focusCell(r, key) {
    render();
    var n = pagesEl.querySelector('[data-r="' + r + '"][data-k="' + key + '"]');
    if (n) {
      var s = n.closest('.ld-sheet'); show(sheets.indexOf(s));
      n.focus();
    }
  }
  function addRow(at) {
    state.rows.splice(at, 0, newRow()); mark();
    var v = visible()[0]; focusCell(at, v ? v.key : state.columns[0].key);
  }
  function delRow(i) {
    if (!confirm('Delete this row?')) return;
    state.rows.splice(i, 1); mark(); render();
  }
  function moveRow(i, d) {
    var j = i + d; if (j < 0 || j >= state.rows.length) return;
    var t = state.rows[i]; state.rows[i] = state.rows[j]; state.rows[j] = t; mark(); render();
  }
  function addCol(at) {
    var n = 1; while (colIndex('c' + n) >= 0 || state.columns.some(function (c) { return c.key === 'c' + n; })) n++;
    var key = 'c' + n;
    var vis = visible(), w = Math.max(10, Math.round(100 / (vis.length + 1)));
    state.columns.splice(at, 0, { key: key, label: 'NEW COLUMN', width: w, align: 'left', visible: true });
    state.rows.forEach(function (r) { r[key] = ''; });
    mark(); render();
    var h = pagesEl.querySelector('th[data-k="' + key + '"]'); if (h) { show(sheets.indexOf(h.closest('.ld-sheet'))); h.focus(); document.execCommand('selectAll'); }
  }
  function delCol(i) {
    if (visible().length <= 1 && state.columns[i].visible) { alert('At least one column has to stay.'); return; }
    if (!confirm('Delete the column "' + state.columns[i].label + '" and all its data?')) return;
    var key = state.columns[i].key;
    state.columns.splice(i, 1); state.rows.forEach(function (r) { delete r[key]; }); mark(); render();
  }
  function moveCol(i, d) {
    var j = i + d; if (j < 0 || j >= state.columns.length) return;
    var t = state.columns[i]; state.columns[i] = state.columns[j]; state.columns[j] = t; mark(); render();
  }
  function widthCol(i) {
    var c = state.columns[i], v = prompt('Width of "' + c.label + '" (relative number, the other columns share the rest; now ' + c.width + '):', String(c.width));
    if (v === null) return;
    var n = parseFloat(v); if (!(n >= 4 && n <= 80)) { alert('Use a number from 4 to 80.'); return; }
    c.width = n; mark(); render();
  }
  function alignCol(i, a) { state.columns[i].align = a; mark(); render(); }
  function hideCol(i) {
    if (visible().length <= 1) { alert('At least one column has to be shown.'); return; }
    state.columns[i].visible = false; mark(); render();
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
    var w = ctx.offsetWidth, h = ctx.offsetHeight;
    ctx.style.left = Math.min(x, window.innerWidth - w - 8) + 'px';
    ctx.style.top = Math.min(y, window.innerHeight - h - 8) + 'px';
  }
  function closeCtx() { ctx.hidden = true; }
  document.addEventListener('click', function (e) { if (!ctx.contains(e.target)) closeCtx(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeCtx(); closeCols(); } });
  window.addEventListener('scroll', closeCtx, true);

  pagesEl.addEventListener('contextmenu', function (e) {
    if (!edit) return;
    var n = e.target.closest('td,th'); if (!n) return;
    e.preventDefault();
    var r = Number(n.dataset.r), ci = colIndex(n.dataset.k), isHead = r < 0, last = state.rows.length - 1;
    openCtx(e.clientX, e.clientY, [
      { label: isHead ? 'Add row at the top' : 'Insert row above', run: function () { addRow(isHead ? 0 : r); } },
      { label: 'Insert row below', off: isHead, run: function () { addRow(r + 1); } },
      { label: 'Move row up', off: isHead || r === 0, run: function () { moveRow(r, -1); } },
      { label: 'Move row down', off: isHead || r === last, run: function () { moveRow(r, 1); } },
      { label: 'Delete row', off: isHead, run: function () { delRow(r); } },
      '-',
      { label: 'Insert column left', run: function () { addCol(ci); } },
      { label: 'Insert column right', run: function () { addCol(ci + 1); } },
      { label: 'Move column left', off: ci === 0, run: function () { moveCol(ci, -1); } },
      { label: 'Move column right', off: ci === state.columns.length - 1, run: function () { moveCol(ci, 1); } },
      { label: 'Column width…', run: function () { widthCol(ci); } },
      { label: 'Align column: left / centre', run: function () { alignCol(ci, state.columns[ci].align === 'center' ? 'left' : 'center'); } },
      { label: 'Hide column', run: function () { hideCol(ci); } },
      { label: 'Delete column', run: function () { delCol(ci); } }
    ]);
  });

  // ---------------------------------------------------------------------------------------------- toolbar
  var colsBtn = document.getElementById('ld-cols-btn'), colsMenu = document.getElementById('ld-cols-menu');
  function closeCols() { colsMenu.hidden = true; colsBtn.setAttribute('aria-expanded', 'false'); }
  colsBtn.onclick = function (e) {
    e.stopPropagation();
    if (!colsMenu.hidden) { closeCols(); return; }
    colsMenu.innerHTML = '';
    state.columns.forEach(function (c, i) {
      var lab = el('label'), cb = document.createElement('input');
      cb.type = 'checkbox'; cb.checked = c.visible;
      cb.onchange = function () {
        if (!cb.checked && visible().length <= 1) { cb.checked = true; return; }
        c.visible = cb.checked; if (edit) mark(); render();
      };
      lab.appendChild(cb); lab.appendChild(document.createTextNode(c.label)); colsMenu.appendChild(lab);
    });
    colsMenu.hidden = false; colsBtn.setAttribute('aria-expanded', 'true');
  };
  document.addEventListener('click', function (e) { if (!colsMenu.contains(e.target) && e.target !== colsBtn) closeCols(); });

  var editBtn = document.getElementById('ld-edit'), saveBtn = document.getElementById('ld-save'), cancelBtn = document.getElementById('ld-cancel'), hint = document.getElementById('ld-hint');
  var addBtns = [];
  function setEdit(on) {
    edit = on; dirty = false;
    if (editBtn) editBtn.hidden = on;
    if (saveBtn) saveBtn.hidden = !on;
    if (cancelBtn) cancelBtn.hidden = !on;
    if (hint) hint.hidden = !on;
    addBtns.forEach(function (b) { b.remove(); }); addBtns = [];
    if (on) {
      var tools = document.querySelector('.ld-tools');
      [['+ Row', function () { addRow(state.rows.length); }], ['+ Column', function () { addCol(state.columns.length); }]].forEach(function (a) {
        var b = el('button', 'ld-btn', a[0]); b.type = 'button'; b.onclick = a[1]; tools.insertBefore(b, saveBtn); addBtns.push(b);
      });
    }
    render();
  }
  if (editBtn) editBtn.onclick = function () { backup = clone(state); setEdit(true); setStatus('Editing. Changes are kept only when you press Save.'); };
  if (cancelBtn) cancelBtn.onclick = function () {
    if (dirty && !confirm('Discard the changes?')) return;
    state = backup ? clone(backup) : state; setEdit(false); setStatus('Changes discarded.');
  };
  if (saveBtn) saveBtn.onclick = function () {
    if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
    saveBtn.disabled = true; setStatus('Saving…');
    var meta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
    fetch(D.saveUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta ? meta.content : '' },
      body: JSON.stringify({ columns: state.columns, rows: state.rows })
    }).then(function (r) { return r.json().catch(function () { return { ok: false, message: 'The server answered with an error (' + r.status + ').' }; }); })
      .then(function (j) {
        if (meta && j.csrf) meta.content = j.csrf;
        saveBtn.disabled = false;
        if (!j.ok) { setStatus(j.message || 'Not saved.', true); return; }
        state.rows = state.rows.filter(function (r) { return state.columns.some(function (c) { return String(r[c.key] || '') !== ''; }); });
        backup = null; dirty = false; setEdit(false);
        setStatus('Saved in the database (' + j.rows + ' rows, ' + j.columns + ' columns). ' + (j.file && j.file.ok ? 'The workbook on the share was updated (a backup copy was kept).'
          : 'The workbook on the share was NOT updated: ' + (j.file ? j.file.message : 'unknown') + ' Press Edit and Save again once that is fixed.'), !(j.file && j.file.ok));
      }).catch(function () { saveBtn.disabled = false; setStatus('The server could not be reached. Nothing was saved.', true); });
  };
  window.addEventListener('beforeunload', function (e) { if (edit && dirty) { e.preventDefault(); e.returnValue = ''; } });

  document.getElementById('ld-print').onclick = function () {
    if (edit && document.activeElement) document.activeElement.blur();
    var was = showAll; showAll = true; pagesEl.classList.add('ld-all');
    setTimeout(function () { window.print(); showAll = was; pagesEl.classList.toggle('ld-all', showAll); show(page); }, 60);
  };

  render();
})();
