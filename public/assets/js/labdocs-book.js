/* An Excel workbook of a "#" folder (#DTP-Lab-102): sheets as tabs, the sheet as a table with its formatting, edit with a right-click menu,
   save to the database and into the Excel file, print. */
(function () {
  'use strict';
  var dataEl = document.getElementById('bk-data');
  if (!dataEl) return;
  var D = JSON.parse(dataEl.textContent);
  var MM = 96 / 25.4;
  var root = document.getElementById('bk'), paper = document.getElementById('bk-paper'), tabsEl = document.getElementById('bk-tabs');
  var statusEl = document.getElementById('bk-status'), ctx = document.getElementById('bk-ctx');
  var sheets = clone(D.sheets), backup = null, edit = false, cur = 0, dirty = {}, tableEl = null;

  function clone(x) { return JSON.parse(JSON.stringify(x)); }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function S() { return sheets[cur]; }
  function mark() { dirty[cur] = true; }
  function setStatus(msg, bad) { statusEl.textContent = msg; statusEl.style.color = bad ? '#b91c1c' : ''; }

  var st = document.createElement('style');
  st.textContent = D.styles.map(function (css, i) { return '.xs' + i + '{' + css + '}'; }).join('\n');
  document.head.appendChild(st);
  var pageStyle = document.createElement('style'); document.head.appendChild(pageStyle);

  function border(i, side) { var m = (D.styles[i] || '').match(new RegExp('border-' + side + ':[^;]+')); return m ? m[0] : ''; }

  // ---------------------------------------------------------------------------------------------- rendering
  function build() {
    var s = S(), span = {}, covered = {};
    s.merges.forEach(function (m) {
      span[m[0] + ':' + m[1]] = m;
      for (var r = m[0]; r <= m[2]; r++) for (var c = m[1]; c <= m[3]; c++) if (r !== m[0] || c !== m[1]) covered[r + ':' + c] = true;
    });
    var t = el('table', 'bk-t'), cg = el('colgroup'), total = 0;
    s.cols.forEach(function (c) { var k = el('col'); k.style.width = c.w + 'px'; cg.appendChild(k); total += c.w; });
    t.appendChild(cg); t.style.width = total + 'px';
    var tb = el('tbody');
    s.grid.forEach(function (line, ri) {
      var r = ri + 1, tr = el('tr'), h = s.rows[ri] && s.rows[ri].h;
      tr.style.height = (h || s.defH || 20) + 'px';
      line.forEach(function (cell, ci) {
        var c = ci + 1;
        if (covered[r + ':' + c]) return;
        var td = el('td', 'xs' + (cell.s || 0));
        td.textContent = cell.v || '';
        td.dataset.r = String(r); td.dataset.c = String(c);
        var m = span[r + ':' + c];
        if (m) {
          td.rowSpan = m[2] - m[0] + 1; td.colSpan = m[3] - m[1] + 1;
          var rb = border(s.grid[m[0] - 1][m[3] - 1].s, 'right'), bb = border(s.grid[m[2] - 1][m[1] - 1].s, 'bottom');   // the frame of a merged cell comes from its far edges
          td.style.cssText = (rb ? rb + ';' : '') + (bb ? bb : '');
        }
        // like Excel: text that does not wrap runs on over the empty cells to its right instead of being cut off
        var st = D.styles[cell.s || 0] || '';
        if (cell.v && !m && !/pre-wrap/.test(st) && !/text-align:(center|right)/.test(st) && !/^[-+\d.,%\s]+$/.test(cell.v)) {
          var k = ci + 1;
          while (k < line.length && !line[k].v && !covered[r + ':' + (k + 1)] && !span[r + ':' + (k + 1)]) k++;
          if (k > ci + 1) td.style.overflow = 'visible';
        }
        if (edit) td.contentEditable = 'true';
        tr.appendChild(td);
      });
      tb.appendChild(tr);
    });
    t.appendChild(tb);
    return t;
  }

  function render() {
    paper.innerHTML = '';
    tableEl = build();
    paper.appendChild(tableEl);
    root.classList.toggle('bk-edit', edit);
    tabs(); fitText(); fit();
  }
  // a cell whose text is longer than the cell (and cannot run on into empty cells next to it) shows all of its text on several lines instead of cutting it
  function fitText() {
    var hit = [];
    tableEl.querySelectorAll('td').forEach(function (td) {
      if (td.textContent && td.style.overflow !== 'visible' && td.scrollWidth > td.clientWidth + 1 && getComputedStyle(td).whiteSpace === 'nowrap') hit.push(td);
    });
    hit.forEach(function (td) { td.classList.add('bk-wrapfix'); });
  }
  // zoom: the sheet opens fitted to the width of the window; the buttons, Ctrl + mouse wheel and the scroll bars change the view
  var Z = 1, stage = document.getElementById('bk-stage'), zl = document.getElementById('bk-zl');
  function setZoom(z) {
    Z = Math.max(0.25, Math.min(3, Math.round(z * 100) / 100));
    paper.style.zoom = String(Z); if (zl) zl.textContent = Math.round(Z * 100) + '%';
    placeImages();
  }
  function fit() {
    paper.style.zoom = '1';
    var w = tableEl.getBoundingClientRect().width, avail = stage.clientWidth - 30;
    setZoom(w > avail && w > 0 ? avail / w : 1);
  }
  document.getElementById('bk-zin').onclick = function () { setZoom(Z + 0.1); };
  document.getElementById('bk-zout').onclick = function () { setZoom(Z - 0.1); };
  document.getElementById('bk-zfit').onclick = fit;
  document.getElementById('bk-z100').onclick = function () { setZoom(1); };
  stage.addEventListener('wheel', function (e) {
    if (!e.ctrlKey) return;
    e.preventDefault();
    setZoom(Z + (e.deltaY < 0 ? 0.1 : -0.1));
  }, { passive: false });
  function tabs() {
    tabsEl.innerHTML = '';
    sheets.forEach(function (s, i) {
      var b = el('button', 'bk-tab' + (i === cur ? ' on' : '') + (dirty[i] ? ' dirty' : ''), s.name); b.type = 'button'; b.setAttribute('role', 'tab'); b.setAttribute('aria-selected', String(i === cur));
      b.onclick = function () { if (i === cur) return; cur = i; render(); };
      tabsEl.appendChild(b);
    });
  }
  function placeImages() {                                           // pictures sit over the cell they are anchored to
    paper.querySelectorAll('.bk-img').forEach(function (n) { n.remove(); });
    var s = S(), pr = paper.getBoundingClientRect(), k = (pr.width / (paper.offsetWidth || 1)) || 1;
    (s.images || []).forEach(function (im) {
      var td = tableEl.querySelector('td[data-r="' + im.r + '"][data-c="' + im.c + '"]');
      if (!td) {                                                      // anchored inside a merged cell: use the merged cell's first cell
        s.merges.forEach(function (m) { if (im.r >= m[0] && im.r <= m[2] && im.c >= m[1] && im.c <= m[3]) td = tableEl.querySelector('td[data-r="' + m[0] + '"][data-c="' + m[1] + '"]'); });
      }
      if (!td) return;
      var r = td.getBoundingClientRect(), d = el('div', 'bk-img'), i = el('img');
      i.src = D.assets + im.src; i.alt = ''; i.width = im.w; i.height = im.h;
      d.style.left = ((r.left - pr.left) / k + im.dx) + 'px'; d.style.top = ((r.top - pr.top) / k + im.dy) + 'px';
      d.appendChild(i); paper.appendChild(d);
    });
  }
  window.addEventListener('resize', function () { fit(); });

  // ---------------------------------------------------------------------------------------------- structure (the same rules as Excel)
  function blankRow(from, copyValues) {
    var s = S(), src = from ? s.grid[from - 1] : null;
    return s.cols.map(function (_, ci) { return { v: copyValues && src ? src[ci].v : '', s: src ? src[ci].s : 0 }; });
  }
  function insertRow(R, from, copy) {
    var s = S();
    s.grid.splice(R - 1, 0, blankRow(from, copy));
    s.rows.splice(R - 1, 0, { h: from && s.rows[from - 1] ? s.rows[from - 1].h : null, k: null });
    s.merges.forEach(function (m) { if (m[0] >= R) { m[0]++; m[2]++; } else if (m[2] >= R) m[2]++; });
    (s.images || []).forEach(function (im) { if (im.r >= R) im.r++; });
    mark(); render();
  }
  function deleteRow(R) {
    var s = S(); if (s.grid.length <= 1) { alert('A sheet needs at least one row.'); return; }
    if (!confirm('Delete row ' + R + '?')) return;
    s.grid.splice(R - 1, 1); s.rows.splice(R - 1, 1);
    s.merges = s.merges.filter(function (m) {
      if (R < m[0]) { m[0]--; m[2]--; } else if (R <= m[2]) m[2]--;
      return m[2] >= m[0] && !(m[0] === m[2] && m[1] === m[3]);
    });
    (s.images || []).forEach(function (im) { if (im.r > R) im.r--; });
    mark(); render();
  }
  function moveRow(R, d) {
    var s = S(), T = R + d;
    if (T < 1 || T > s.grid.length) return;
    var hit = s.merges.some(function (m) { return (R >= m[0] && R <= m[2]) || (T >= m[0] && T <= m[2]); });
    if (hit) { alert('A row with merged cells cannot be moved.'); return; }
    var g = s.grid[R - 1]; s.grid[R - 1] = s.grid[T - 1]; s.grid[T - 1] = g;
    var h = s.rows[R - 1]; s.rows[R - 1] = s.rows[T - 1]; s.rows[T - 1] = h;
    mark(); render();
  }
  function insertCol(C, from) {
    var s = S();
    s.cols.splice(C - 1, 0, { w: from ? s.cols[from - 1].w : 90, k: null });
    s.grid.forEach(function (line) { line.splice(C - 1, 0, { v: '', s: from ? line[from - 1].s : 0 }); });
    s.merges.forEach(function (m) { if (m[1] >= C) { m[1]++; m[3]++; } else if (m[3] >= C) m[3]++; });
    (s.images || []).forEach(function (im) { if (im.c >= C) im.c++; });
    mark(); render();
  }
  function deleteCol(C) {
    var s = S(); if (s.cols.length <= 1) { alert('A sheet needs at least one column.'); return; }
    if (!confirm('Delete column ' + colName(C) + '?')) return;
    s.cols.splice(C - 1, 1); s.grid.forEach(function (line) { line.splice(C - 1, 1); });
    s.merges = s.merges.filter(function (m) {
      if (C < m[1]) { m[1]--; m[3]--; } else if (C <= m[3]) m[3]--;
      return m[3] >= m[1] && !(m[0] === m[2] && m[1] === m[3]);
    });
    (s.images || []).forEach(function (im) { if (im.c > C) im.c--; });
    mark(); render();
  }
  function colName(n) { var s = ''; while (n > 0) { var m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); } return s; }
  function inMerge(r, c) { return S().merges.find(function (m) { return r >= m[0] && r <= m[2] && c >= m[1] && c <= m[3]; }); }
  function mergeWith(r, c, dr, dc) {
    var s = S(), a = inMerge(r, c), r2 = a ? a[2] + dr : r + dr, c2 = a ? a[3] + dc : c + dc;
    if (r2 > s.grid.length || c2 > s.cols.length) return;
    var R1 = a ? a[0] : r, C1 = a ? a[1] : c, taken = false;
    s.merges.forEach(function (m) { if (m !== a && !(m[2] < R1 || m[0] > r2 || m[3] < C1 || m[1] > c2)) taken = true; });
    if (taken) { alert('Those cells are already part of another merged area.'); return; }
    if (a) s.merges.splice(s.merges.indexOf(a), 1);
    s.merges.push([R1, C1, r2, c2]); mark(); render();
  }
  function setW(C) {
    var s = S(), v = prompt('Width of column ' + colName(C) + ' in pixels (now ' + s.cols[C - 1].w + '):', String(s.cols[C - 1].w)); if (v === null) return;
    var n = parseInt(v, 10); if (!(n >= 14 && n <= 900)) { alert('Use a number from 14 to 900.'); return; }
    s.cols[C - 1].w = n; mark(); render();
  }
  function setH(R) {
    var s = S(), h = s.rows[R - 1].h, v = prompt('Height of row ' + R + ' in pixels (empty = automatic; now ' + (h || 'automatic') + '):', h ? String(h) : ''); if (v === null) return;
    if (v.trim() === '') { s.rows[R - 1].h = null; } else { var n = parseInt(v, 10); if (!(n >= 8 && n <= 900)) { alert('Use a number from 8 to 900.'); return; } s.rows[R - 1].h = n; }
    mark(); render();
  }
  function focusCell(r, c) {
    render();
    var td = tableEl.querySelector('td[data-r="' + r + '"][data-c="' + c + '"]'); if (td) { td.focus(); }
  }

  // ---------------------------------------------------------------------------------------------- editing
  paper.addEventListener('input', function (e) {
    if (!edit) return;
    var td = e.target.closest('td'); if (!td) return;
    var cell = S().grid[Number(td.dataset.r) - 1][Number(td.dataset.c) - 1];
    cell.v = td.innerText.replace(/ /g, ' ').replace(/\n$/, ''); delete cell.k; mark();
    tabsEl.querySelectorAll('.bk-tab')[cur].classList.add('dirty');
  });
  paper.addEventListener('keydown', function (e) {
    if (!edit) return;
    var td = e.target.closest('td'); if (!td) return;
    if (e.key === 'Tab') {                                            // Tab goes to the next cell
      e.preventDefault();
      var all = Array.prototype.slice.call(tableEl.querySelectorAll('td')), i = all.indexOf(td) + (e.shiftKey ? -1 : 1);
      if (all[i]) { all[i].focus(); var r = document.createRange(); r.selectNodeContents(all[i]); var s = getSelection(); s.removeAllRanges(); s.addRange(r); }
    } else if (e.key === 'Escape') { td.blur(); }
  });
  paper.addEventListener('paste', function (e) {
    if (!edit) return;
    e.preventDefault();
    document.execCommand('insertText', false, ((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\r/g, ''));
  });
  paper.addEventListener('focusout', function () { setTimeout(placeImages, 50); });

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
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeCtx(); });
  window.addEventListener('scroll', closeCtx, true);
  paper.addEventListener('contextmenu', function (e) {
    if (!edit) return;
    var td = e.target.closest('td'); if (!td) return;
    e.preventDefault();
    var r = Number(td.dataset.r), c = Number(td.dataset.c), s = S(), m = inMerge(r, c), nR = s.grid.length, nC = s.cols.length;
    openCtx(e.clientX, e.clientY, [
      { label: 'Insert row above', run: function () { insertRow(r, r, false); } },
      { label: 'Insert row below', run: function () { insertRow((m ? m[2] : r) + 1, m ? m[2] : r, false); } },
      { label: 'Duplicate row', run: function () { insertRow(r + 1, r, true); } },
      { label: 'Move row up', off: r === 1, run: function () { moveRow(r, -1); } },
      { label: 'Move row down', off: r === nR, run: function () { moveRow(r, 1); } },
      { label: 'Delete row', run: function () { deleteRow(r); } },
      { label: 'Row height…', run: function () { setH(r); } },
      '-',
      { label: 'Insert column left', run: function () { insertCol(c, c); } },
      { label: 'Insert column right', run: function () { insertCol(c + 1, c); } },
      { label: 'Delete column', run: function () { deleteCol(c); } },
      { label: 'Column width…', run: function () { setW(c); } },
      '-',
      { label: 'Merge with the cell on the right', off: (m ? m[3] : c) >= nC, run: function () { mergeWith(r, c, 0, 1); } },
      { label: 'Merge with the cell below', off: (m ? m[2] : r) >= nR, run: function () { mergeWith(r, c, 1, 0); } },
      { label: 'Unmerge cells', off: !m, run: function () { s.merges.splice(s.merges.indexOf(m), 1); mark(); render(); } },
      '-',
      { label: 'Clear this cell', run: function () { s.grid[r - 1][c - 1].v = ''; delete s.grid[r - 1][c - 1].k; mark(); render(); } },
      { label: 'Clear this row', run: function () { s.grid[r - 1].forEach(function (x) { x.v = ''; delete x.k; }); mark(); render(); } }
    ]);
  });

  // ---------------------------------------------------------------------------------------------- toolbar
  var editBtn = document.getElementById('bk-edit'), saveBtn = document.getElementById('bk-save'), cancelBtn = document.getElementById('bk-cancel'), hint = document.getElementById('bk-hint');
  function setEdit(on) {
    edit = on; if (!on) dirty = {};
    if (editBtn) editBtn.hidden = on;
    if (saveBtn) saveBtn.hidden = !on;
    if (cancelBtn) cancelBtn.hidden = !on;
    hint.hidden = !on;
    render();
  }
  if (editBtn) editBtn.onclick = function () { backup = clone(sheets); setEdit(true); setStatus('Editing. Changes are kept only when you press Save.'); };
  if (cancelBtn) cancelBtn.onclick = function () {
    if (Object.keys(dirty).length && !confirm('Discard the changes?')) return;
    sheets = backup ? clone(backup) : sheets; setEdit(false); setStatus('Changes discarded.');
  };
  if (saveBtn) saveBtn.onclick = function () {
    if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
    var out = {};
    Object.keys(dirty).forEach(function (i) { var m = clone(sheets[i]); delete m.rev; out[i] = m; });
    if (!Object.keys(out).length) { setEdit(false); setStatus('Nothing was changed.'); return; }
    saveBtn.disabled = true; setStatus('Saving…');
    var meta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
    fetch(D.saveUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta ? meta.content : '' }, body: JSON.stringify({ sheets: out }) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'The server answered with an error (' + r.status + ').' }; }); })
      .then(function (j) {
        if (meta && j.csrf) meta.content = j.csrf;
        saveBtn.disabled = false;
        if (!j.ok) { setStatus(j.message || 'Not saved.', true); return; }
        Object.keys(j.sheets || {}).forEach(function (i) { sheets[i] = j.sheets[i]; });
        backup = null; setEdit(false);
        setStatus('Saved in the database (' + Object.keys(j.sheets).length + ' sheet' + (Object.keys(j.sheets).length === 1 ? '' : 's') + '). ' + (j.file && j.file.ok ? 'The Excel file on the share was updated (a backup copy was kept).'
          : 'The Excel file on the share was NOT updated: ' + (j.file ? j.file.message : 'unknown') + ' Press Edit and Save again once that is fixed.'), !(j.file && j.file.ok));
      }).catch(function () { saveBtn.disabled = false; setStatus('The server could not be reached. Nothing was saved.', true); });
  };
  window.addEventListener('beforeunload', function (e) { if (edit && Object.keys(dirty).length) { e.preventDefault(); e.returnValue = ''; } });

  document.getElementById('bk-print').onclick = function () {       // the sheet on A4 with narrow margins, scaled down to the page width when it is wider
    if (edit && document.activeElement) document.activeElement.blur();
    paper.style.zoom = '1';
    var s = S(), land = s.print && s.print.orient === 'landscape', M = 6.4, availW = ((land ? 297 : 210) - 2 * M) * MM, w = tableEl.getBoundingClientRect().width;
    var z = w > availW ? availW / w : 1;
    pageStyle.textContent = '@page{size:A4 ' + (land ? 'landscape' : 'portrait') + ';margin:' + M + 'mm}@media print{.bk-t{zoom:' + z.toFixed(4) + '}}';
    setTimeout(function () { window.print(); setZoom(Z); }, 60);
  };
  render();
})();
