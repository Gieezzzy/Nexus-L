/* An Excel workbook of a "#" folder (#DTP-Lab-102) and other documents:
   High-fidelity document view with print & zoom, interactive Luckysheet spreadsheet editor on Edit,
   multi-sheet tabs, real-time formulas and formatting, save to the database and patch the Excel file on the share. */
(function () {
  'use strict';
  var dataEl = document.getElementById('bk-data');
  if (!dataEl) return;
  var D = JSON.parse(dataEl.textContent);
  var MM = 96 / 25.4;
  var root = document.getElementById('bk'), paper = document.getElementById('bk-paper'), tabsEl = document.getElementById('bk-tabs');
  var stage = document.getElementById('bk-stage'), luckyWrap = document.getElementById('bk-lucky-wrap');
  var statusEl = document.getElementById('bk-status'), ctx = document.getElementById('bk-ctx');
  var sheets = clone(D.sheets), backup = null, edit = false, cur = 0, dirty = {}, luckyDirty = false, tableEl = null;

  function clone(x) { return JSON.parse(JSON.stringify(x)); }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function S() { return sheets[cur]; }
  function mark() { dirty[cur] = true; luckyDirty = true; }
  function setStatus(msg, bad) { if (statusEl) { statusEl.textContent = msg; statusEl.style.color = bad ? '#b91c1c' : ''; } }

  var st = document.createElement('style');
  st.textContent = (D.styles || []).map(function (css, i) { return '.xs' + i + '{' + css + '}'; }).join('\n');
  document.head.appendChild(st);
  var pageStyle = document.createElement('style'); document.head.appendChild(pageStyle);

  function border(i, side) { var m = ((D.styles || [])[i] || '').match(new RegExp('border-' + side + ':[^;]+')); return m ? m[0] : ''; }

  // ---------------------------------------------------------------------------------------------- rendering
  function build() {
    var s = S(), span = {}, covered = {};
    (s.merges || []).forEach(function (m) {
      span[m[0] + ':' + m[1]] = m;
      for (var r = m[0]; r <= m[2]; r++) for (var c = m[1]; c <= m[3]; c++) if (r !== m[0] || c !== m[1]) covered[r + ':' + c] = true;
    });
    var t = el('table', 'bk-t'), cg = el('colgroup'), total = 0;
    (s.cols || []).forEach(function (c) { var k = el('col'); k.style.width = c.w + 'px'; cg.appendChild(k); total += c.w; });
    t.appendChild(cg); t.style.width = total + 'px';
    var tb = el('tbody');
    (s.grid || []).forEach(function (line, ri) {
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
          var rb = border(s.grid[m[0] - 1][m[3] - 1].s, 'right'), bb = border(s.grid[m[2] - 1][m[1] - 1].s, 'bottom');
          td.style.cssText = (rb ? rb + ';' : '') + (bb ? bb : '');
        }
        var stStyle = (D.styles || [])[cell.s || 0] || '';
        if (cell.v && !m && !/pre-wrap/.test(stStyle) && !/text-align:(center|right)/.test(stStyle) && !/^[-+\d.,%\s]+$/.test(cell.v)) {
          var k = ci + 1;
          while (k < line.length && !line[k].v && !covered[r + ':' + (k + 1)] && !span[r + ':' + (k + 1)]) k++;
          if (k > ci + 1) td.style.overflow = 'visible';
        }
        tr.appendChild(td);
      });
      tb.appendChild(tr);
    });
    t.appendChild(tb);
    return t;
  }

  // a sheet that has not been fetched yet (workbooks with many sheets load one sheet at a time)
  function load(i) {
    setStatus('Loading sheet ' + (i + 1) + ' of ' + sheets.length + '…');
    return fetch(D.sheetUrl + i, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j.ok) throw new Error(); sheets[i] = j.sheet; setStatus(''); return j.sheet; })
      .catch(function () { setStatus('That sheet could not be loaded. Try again.', true); throw new Error('load'); });
  }

  function render() {
    if (sheets[cur] && sheets[cur].lazy) { load(cur).then(render, function () {}); return; }
    doRender();
  }

  function doRender() {
    paper.innerHTML = '';
    tableEl = build();
    paper.appendChild(tableEl);
    root.classList.toggle('bk-edit', edit);
    tabs(); fitText(); fit();
  }

  function fitText() {
    var hit = [];
    tableEl.querySelectorAll('td').forEach(function (td) {
      if (td.textContent && td.style.overflow !== 'visible' && td.scrollWidth > td.clientWidth + 1 && getComputedStyle(td).whiteSpace === 'nowrap') hit.push(td);
    });
    hit.forEach(function (td) { td.classList.add('bk-wrapfix'); });
  }

  // zoom: the sheet opens fitted to the width of the window
  var Z = 1, zl = document.getElementById('bk-zl');
  function setZoom(z) {
    Z = Math.max(0.25, Math.min(3, Math.round(z * 100) / 100));
    paper.style.zoom = String(Z); if (zl) zl.textContent = Math.round(Z * 100) + '%';
    placeImages();
  }

  function fit() {
    paper.style.zoom = '1';
    var w = tableEl ? tableEl.getBoundingClientRect().width : 0, avail = stage ? stage.clientWidth - 30 : 800;
    setZoom(w > avail && w > 0 ? avail / w : 1);
  }

  var zin = document.getElementById('bk-zin'), zout = document.getElementById('bk-zout');
  var zfit = document.getElementById('bk-zfit'), z100 = document.getElementById('bk-z100');
  if (zin) zin.onclick = function () { setZoom(Z + 0.1); };
  if (zout) zout.onclick = function () { setZoom(Z - 0.1); };
  if (zfit) zfit.onclick = fit;
  if (z100) z100.onclick = function () { setZoom(1); };

  if (stage) {
    stage.addEventListener('wheel', function (e) {
      if (!e.ctrlKey) return;
      e.preventDefault();
      setZoom(Z + (e.deltaY < 0 ? 0.1 : -0.1));
    }, { passive: false });
  }

  var MANY = 7;
  function tabs() {
    tabsEl.innerHTML = '';
    tabsEl.classList.toggle('bk-many', sheets.length >= MANY);
    var st = document.getElementById('bk-stage');
    if (st && st.parentNode) {
      if (sheets.length >= MANY) { if (tabsEl.previousElementSibling !== st) st.parentNode.insertBefore(tabsEl, st.nextSibling); }
      else if (tabsEl.nextElementSibling !== st && tabsEl.parentNode === st.parentNode) st.parentNode.insertBefore(tabsEl, st);
    }
    if (sheets.length >= MANY) { switcher(); return; }
    sheets.forEach(function (s, i) {
      var b = el('button', 'bk-tab' + (i === cur ? ' on' : '') + (dirty[i] ? ' dirty' : ''), s.name);
      b.type = 'button'; b.setAttribute('role', 'tab'); b.setAttribute('aria-selected', String(i === cur));
      b.onclick = function () { if (i === cur) return; cur = i; render(); };
      tabsEl.appendChild(b);
    });
  }

  var IC = {
    left: '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M10 3.5 5.5 8l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    right: '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M6 3.5 10.5 8 6 12.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    down: '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M3.5 6 8 10.5 12.5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    search: '<svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true"><circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m10.5 10.5 3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    check: '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
  };

  function switcher() {
    function go(i) { if (i < 0 || i >= sheets.length || i === cur) return; cur = i; render(); }
    var bar = el('div', 'bk-sw-bar'), prev = el('button', 'bk-sw-nav'), next = el('button', 'bk-sw-nav'), cap = el('button', 'bk-sw-cur'), pop = el('div', 'bk-sw'), sw = el('label', 'bk-sw-s'), q = el('input', 'bk-sw-q'), list = el('ul', 'bk-sw-l');
    prev.type = next.type = cap.type = 'button'; prev.innerHTML = IC.left; next.innerHTML = IC.right;
    prev.title = 'Previous sheet'; next.title = 'Next sheet'; prev.setAttribute('aria-label', 'Previous sheet'); next.setAttribute('aria-label', 'Next sheet');
    prev.disabled = cur === 0; next.disabled = cur === sheets.length - 1;
    cap.setAttribute('aria-haspopup', 'listbox'); cap.setAttribute('aria-expanded', 'false'); cap.title = 'Choose a sheet';
    cap.appendChild(el('span', 'bk-sw-name', sheets[cur].name + (dirty[cur] ? ' •' : '')));
    var meta = el('span', 'bk-sw-meta'); meta.appendChild(el('span', '', (cur + 1) + ' / ' + sheets.length)); meta.insertAdjacentHTML('beforeend', IC.down); cap.appendChild(meta);
    q.type = 'search'; q.placeholder = 'Find a sheet'; q.setAttribute('aria-label', 'Find a sheet'); q.autocomplete = 'off';
    sw.insertAdjacentHTML('beforeend', IC.search); sw.appendChild(q);
    pop.hidden = true; pop.appendChild(sw); pop.appendChild(list);
    function fill() {
      var f = q.value.trim().toLowerCase(), n = 0; list.innerHTML = '';
      sheets.forEach(function (sh, i) {
        if (f && sh.name.toLowerCase().indexOf(f) < 0) return;
        var li = el('li'), b = el('button', 'bk-sw-i' + (i === cur ? ' on' : '')); b.type = 'button'; b.setAttribute('role', 'option'); b.setAttribute('aria-selected', String(i === cur));
        b.appendChild(el('span', 'bk-sw-no', String(i + 1))); b.appendChild(el('span', 'bk-sw-nm', sh.name + (dirty[i] ? ' •' : '')));
        if (i === cur) b.insertAdjacentHTML('beforeend', IC.check);
        b.onclick = function () { pop.hidden = true; go(i); }; li.appendChild(b); list.appendChild(li); n++;
      });
      if (!n) list.appendChild(el('li', 'bk-sw-none', 'No sheet matches.'));
    }
    function openPop() { pop.hidden = false; bar.classList.add('open'); cap.setAttribute('aria-expanded', 'true'); q.value = ''; fill(); q.focus(); var on = list.querySelector('.on'); if (on) on.scrollIntoView({ block: 'center' }); }
    function shut() { pop.hidden = true; bar.classList.remove('open'); cap.setAttribute('aria-expanded', 'false'); }
    cap.onclick = function (e) { e.stopPropagation(); if (pop.hidden) openPop(); else shut(); };
    prev.onclick = function () { go(cur - 1); }; next.onclick = function () { go(cur + 1); };
    q.addEventListener('input', fill);
    q.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { shut(); cap.focus(); }
      if (e.key === 'Enter') { var f = list.querySelector('.bk-sw-i'); if (f) f.click(); }
      if (e.key === 'ArrowDown') { var f2 = list.querySelector('.bk-sw-i'); if (f2) { e.preventDefault(); f2.focus(); } }
    });
    list.addEventListener('keydown', function (e) {
      var b = document.activeElement; if (!b || !b.classList.contains('bk-sw-i')) return;
      var li = b.parentNode, to = e.key === 'ArrowDown' ? li.nextElementSibling : e.key === 'ArrowUp' ? li.previousElementSibling : null;
      if (to) { e.preventDefault(); var t = to.querySelector('.bk-sw-i'); if (t) t.focus(); } else if (e.key === 'ArrowUp') { q.focus(); }
      if (e.key === 'Escape') { shut(); cap.focus(); }
    });
    pop.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('click', function () { if (!pop.hidden) shut(); });
    bar.appendChild(prev); bar.appendChild(cap); bar.appendChild(next); bar.appendChild(pop);
    tabsEl.appendChild(bar);
  }

  function placeImages() {
    paper.querySelectorAll('.bk-img').forEach(function (n) { n.remove(); });
    var s = S(), pr = paper.getBoundingClientRect(), k = (pr.width / (paper.offsetWidth || 1)) || 1;
    (s.images || []).forEach(function (im) {
      var td = tableEl ? tableEl.querySelector('td[data-r="' + im.r + '"][data-c="' + im.c + '"]') : null;
      if (!td && s.merges) {
        s.merges.forEach(function (m) { if (im.r >= m[0] && im.r <= m[2] && im.c >= m[1] && im.c <= m[3] && tableEl) td = tableEl.querySelector('td[data-r="' + m[0] + '"][data-c="' + m[1] + '"]'); });
      }
      if (!td) return;
      var r = td.getBoundingClientRect(), d = el('div', 'bk-img'), i = el('img');
      i.src = D.assets + im.src; i.alt = ''; i.width = im.w; i.height = im.h;
      d.style.left = ((r.left - pr.left) / k + im.dx) + 'px'; d.style.top = ((r.top - pr.top) / k + im.dy) + 'px';
      d.appendChild(i); paper.appendChild(d);
    });
  }
  window.addEventListener('resize', function () {
    if (edit) {
      if (window.luckysheet && window.luckysheet.resize) {
        window.luckysheet.resize();
      }
    } else {
      fit();
    }
  });

  // ---------------------------------------------------------------------------------------------- Luckysheet Model Conversion
  function parseCellStyle(val, styleIdx, css) {
    var str = String(val == null ? '' : val).trim();
    var vObj = { v: val, m: String(val == null ? '' : val) };

    if (/^[-+]?\d+(\.\d+)?$/.test(str)) {
      vObj.v = parseFloat(str);
      vObj.m = str;
      vObj.ct = { fa: 'General', t: 'n' };
    } else if (/^[-+]?\$?\d{1,3}(,\d{3})*(\.\d+)?$/.test(str)) {
      var num = parseFloat(str.replace(/[$,]/g, ''));
      if (!isNaN(num)) {
        vObj.v = num;
        vObj.m = str;
        vObj.ct = { fa: '#,##0.00', t: 'n' };
      }
    } else if (/^[-+]?\d+(\.\d+)?%$/.test(str)) {
      var pct = parseFloat(str.replace('%', '')) / 100;
      if (!isNaN(pct)) {
        vObj.v = pct;
        vObj.m = str;
        vObj.ct = { fa: '0.00%', t: 'n' };
      }
    } else {
      vObj.ct = { fa: 'General', t: 'g' };
    }
    if (!css) return vObj;

    if (/font-weight:\s*(?:bold|[7-9]00)/i.test(css)) vObj.bl = 1;
    if (/font-style:\s*italic/i.test(css)) vObj.it = 1;
    if (/text-decoration:\s*underline/i.test(css)) vObj.un = 1;
    if (/text-decoration:\s*line-through/i.test(css)) vObj.cl = 1;

    if (/text-align:\s*center/i.test(css)) vObj.ht = 0;
    else if (/text-align:\s*left/i.test(css)) vObj.ht = 1;
    else if (/text-align:\s*right/i.test(css)) vObj.ht = 2;

    if (/vertical-align:\s*middle/i.test(css)) vObj.vt = 0;
    else if (/vertical-align:\s*top/i.test(css)) vObj.vt = 1;
    else if (/vertical-align:\s*bottom/i.test(css)) vObj.vt = 2;

    var colorMatch = css.match(/(?:^|;)\s*color:\s*(#[0-9a-fA-F]{3,8}|rgb\([^)]+\))/i);
    if (colorMatch) vObj.fc = colorMatch[1];

    var bgMatch = css.match(/(?:^|;)\s*background(?:-color)?:\s*(#[0-9a-fA-F]{3,8}|rgb\([^)]+\))/i);
    if (bgMatch) vObj.bg = bgMatch[1];

    var fsMatch = css.match(/(?:^|;)\s*font-size:\s*(\d+(?:\.\d+)?)\s*(pt|px)/i);
    if (fsMatch) {
      var pt = fsMatch[2].toLowerCase() === 'px' ? Math.round(parseFloat(fsMatch[1]) * 0.75) : Math.round(parseFloat(fsMatch[1]));
      if (pt >= 6 && pt <= 72) vObj.fs = pt;
    }

    return vObj;
  }

  function nexusToLuckysheet(sheet, index, isActive) {
    var maxR = Math.max(sheet.grid ? sheet.grid.length : 30, 30);
    var maxC = Math.max(sheet.cols ? sheet.cols.length : 26, 26);
    var config = {
      merge: {},
      rowlen: {},
      columnlen: {},
      customHeight: {},
      customWidth: {}
    };

    (sheet.cols || []).forEach(function (c, ci) {
      if (c && c.w) {
        config.columnlen[ci] = Math.max(14, Math.min(900, c.w));
        config.customWidth[ci] = 1;
      }
    });

    (sheet.rows || []).forEach(function (r, ri) {
      if (r && r.h) {
        config.rowlen[ri] = Math.max(8, Math.min(900, r.h));
        config.customHeight[ri] = 1;
      }
    });

    var mergeLookup = {};
    (sheet.merges || []).forEach(function (m) {
      var r1 = m[0] - 1, c1 = m[1] - 1, r2 = m[2] - 1, c2 = m[3] - 1;
      var rs = r2 - r1 + 1, cs = c2 - c1 + 1;
      config.merge[r1 + '_' + c1] = { r: r1, c: c1, rs: rs, cs: cs };
      for (var mr = r1; mr <= r2; mr++) {
        for (var mc = c1; mc <= c2; mc++) {
          if (mr === r1 && mc === c1) {
            mergeLookup[mr + '_' + mc] = { r: r1, c: c1, rs: rs, cs: cs };
          } else {
            mergeLookup[mr + '_' + mc] = { r: r1, c: c1 };
          }
        }
      }
    });

    var celldata = [];
    var cellDataMap = {};
    (sheet.grid || []).forEach(function (row, ri) {
      row.forEach(function (cell, ci) {
        if (!cell) return;
        var hasV = cell.v != null && cell.v !== '';
        var hasF = cell.f != null && cell.f !== '';
        var hasS = cell.s != null && cell.s > 0;
        var mInfo = mergeLookup[ri + '_' + ci];
        if (hasV || hasF || hasS || mInfo) {
          var css = (D.styles || [])[cell.s || 0] || '';
          var vObj = parseCellStyle(cell.v, cell.s, css);
          if (hasF) {
            vObj.f = String(cell.f).charAt(0) === '=' ? String(cell.f) : ('=' + String(cell.f));
          } else if (typeof cell.v === 'string' && cell.v.charAt(0) === '=' && cell.v.length > 1) {
            vObj.f = cell.v;
          }
          if (mInfo) vObj.mc = mInfo;
          cellDataMap[ri + '_' + ci] = true;
          celldata.push({
            r: ri,
            c: ci,
            v: vObj
          });
        }
      });
    });

    Object.keys(mergeLookup).forEach(function (key) {
      if (!cellDataMap[key]) {
        var parts = key.split('_');
        var mr = parseInt(parts[0], 10), mc = parseInt(parts[1], 10);
        celldata.push({
          r: mr,
          c: mc,
          v: {
            v: '',
            m: '',
            ct: { fa: 'General', t: 'g' },
            mc: mergeLookup[key]
          }
        });
      }
    });

    return {
      name: sheet.name || ('Sheet' + (index + 1)),
      index: index,
      status: isActive ? 1 : 0,
      order: index,
      row: maxR,
      column: maxC,
      defaultRowHeight: sheet.defH || 20,
      defaultColWidth: 72,
      config: config,
      celldata: celldata
    };
  }

  function luckysheetToNexus(luckySheet, origSheet, nStyles) {
    var rawData = luckySheet.data || [];
    var maxR = 0, maxC = 0;

    if (origSheet && origSheet.grid) {
      maxR = origSheet.grid.length;
      maxC = origSheet.cols ? origSheet.cols.length : 0;
    }

    for (var r = 0; r < rawData.length; r++) {
      var row = rawData[r] || [];
      for (var c = 0; c < row.length; c++) {
        var cell = row[c];
        if (cell && ((cell.v != null && cell.v !== '') || cell.f)) {
          if (r + 1 > maxR) maxR = r + 1;
          if (c + 1 > maxC) maxC = c + 1;
        }
      }
    }

    var mergeObj = (luckySheet.config && luckySheet.config.merge) || {};
    Object.keys(mergeObj).forEach(function (k) {
      var m = mergeObj[k];
      if (m) {
        if (m.r + m.rs > maxR) maxR = m.r + m.rs;
        if (m.c + m.cs > maxC) maxC = m.c + m.cs;
      }
    });

    maxR = Math.max(1, Math.min(2000, maxR));
    maxC = Math.max(1, Math.min(60, maxC));

    var colLen = (luckySheet.config && luckySheet.config.columnlen) || {};
    var cols = [];
    for (var ci = 0; ci < maxC; ci++) {
      var origW = (origSheet && origSheet.cols && origSheet.cols[ci]) ? origSheet.cols[ci].w : 64;
      var origK = (origSheet && origSheet.cols && origSheet.cols[ci]) ? origSheet.cols[ci].k : null;
      var w = colLen[ci] ? Math.max(14, Math.min(900, Math.round(colLen[ci]))) : origW;
      cols.push({ w: w, k: origK });
    }

    var rowLen = (luckySheet.config && luckySheet.config.rowlen) || {};
    var rows = [];
    for (var ri = 0; ri < maxR; ri++) {
      var origH = (origSheet && origSheet.rows && origSheet.rows[ri]) ? origSheet.rows[ri].h : null;
      var origK = (origSheet && origSheet.rows && origSheet.rows[ri]) ? origSheet.rows[ri].k : null;
      var h = rowLen[ri] ? Math.max(8, Math.min(900, Math.round(rowLen[ri]))) : origH;
      rows.push({ h: h, k: origK });
    }

    var grid = [];
    for (var ri2 = 0; ri2 < maxR; ri2++) {
      var line = [];
      var rawRow = rawData[ri2] || [];
      for (var ci2 = 0; ci2 < maxC; ci2++) {
        var luckyCell = rawRow[ci2];
        var origCell = (origSheet && origSheet.grid && origSheet.grid[ri2] && origSheet.grid[ri2][ci2]) ? origSheet.grid[ri2][ci2] : null;

        var val = '';
        var formula = '';
        if (luckyCell != null) {
          if (luckyCell.f != null && luckyCell.f !== '') {
            formula = String(luckyCell.f);
          }
          if (luckyCell.m != null && luckyCell.m !== '') {
            val = String(luckyCell.m);
          } else if (luckyCell.v != null && luckyCell.v !== '') {
            val = String(luckyCell.v);
          } else if (formula) {
            val = formula;
          }
        } else if (origCell) {
          val = String(origCell.v || '');
          formula = String(origCell.f || '');
        }

        val = val.replace(/\r/g, '');
        var s = origCell ? (origCell.s || 0) : 0;
        if (s < 0 || s >= nStyles) s = 0;

        var cellObj = { v: val, s: s };
        if (formula) {
          cellObj.f = formula.charAt(0) === '=' ? formula : ('=' + formula);
        }
        if (origCell && origCell.k) cellObj.k = origCell.k;
        line.push(cellObj);
      }
      grid.push(line);
    }

    var merges = [];
    var taken = {};
    Object.keys(mergeObj).forEach(function (k) {
      var m = mergeObj[k];
      if (!m) return;
      var r1 = m.r + 1, c1 = m.c + 1, r2 = m.r + m.rs, c2 = m.c + m.cs;
      if (r1 <= maxR && c1 <= maxC && r2 <= maxR && c2 <= maxC && (r2 > r1 || c2 > c1)) {
        var overlap = false;
        for (var tr = r1; tr <= r2; tr++) {
          for (var tc = c1; tc <= c2; tc++) {
            if (taken[tr + ':' + tc]) overlap = true;
          }
        }
        if (!overlap) {
          for (var tr2 = r1; tr2 <= r2; tr2++) {
            for (var tc2 = c1; tc2 <= c2; tc2++) {
              taken[tr2 + ':' + tc2] = true;
            }
          }
          merges.push([r1, c1, r2, c2]);
        }
      }
    });

    return {
      name: luckySheet.name || (origSheet ? origSheet.name : 'Sheet'),
      cols: cols,
      rows: rows,
      grid: grid,
      merges: merges,
      images: (origSheet && origSheet.images) ? origSheet.images : [],
      defH: (origSheet && origSheet.defH) ? origSheet.defH : 20,
      print: (origSheet && origSheet.print) ? origSheet.print : { orient: 'portrait', fit: false }
    };
  }

  // ---------------------------------------------------------------------------------------------- Toolbar & Luckysheet Lifecycle
  var editBtn = document.getElementById('bk-edit'), saveBtn = document.getElementById('bk-save');
  var cancelBtn = document.getElementById('bk-cancel'), printBtn = document.getElementById('bk-print');
  var fsBtn = document.getElementById('bk-fullscreen'), fsLabel = document.getElementById('bk-fs-label');
  var fsSaveBtn = document.getElementById('bk-fs-save'), fsCancelBtn = document.getElementById('bk-fs-cancel'), fsExitBtn = document.getElementById('bk-fs-exit');
  var hint = document.getElementById('bk-hint'), zoomGroup = document.querySelector('.bk-zoom');
  var isFs = false;

  function setFullScreen(enable) {
    isFs = !!enable;
    if (luckyWrap) {
      luckyWrap.classList.toggle('is-fullscreen', isFs);
    }
    if (fsLabel) {
      fsLabel.textContent = isFs ? 'Exit Full Screen' : 'Full Screen';
    }
    if (fsBtn) {
      fsBtn.title = isFs ? 'Exit full screen (Esc)' : 'Full screen edit mode';
    }
    setTimeout(function () {
      if (window.luckysheet && window.luckysheet.resize) {
        window.luckysheet.resize();
      }
    }, 60);
  }

  function toggleFullScreen() {
    setFullScreen(!isFs);
  }

  if (fsBtn) fsBtn.onclick = toggleFullScreen;
  if (fsExitBtn) fsExitBtn.onclick = function () { setFullScreen(false); };
  if (fsSaveBtn) fsSaveBtn.onclick = function () { if (saveBtn && !saveBtn.disabled) saveBtn.click(); };
  if (fsCancelBtn) fsCancelBtn.onclick = function () { if (cancelBtn) cancelBtn.click(); };

  function initLuckysheetInstance(luckyData) {
    if (!window.luckysheet) {
      setStatus('Luckysheet engine is not ready.', true);
      return;
    }
    try { window.luckysheet.destroy(); } catch (e) {}

    window.luckysheet.create({
      container: 'bk-luckysheet',
      title: (D.book && D.book.label) ? D.book.label : 'Workbook',
      lang: 'en',
      showinfobar: false,
      showsheetbar: true,
      showsheetbarConfig: {
        add: true,
        menu: true,
        sheet: true
      },
      showstatisticBar: true,
      showstatisticBarConfig: {
        count: true,
        view: true,
        zoom: true
      },
      sheetFormulaBar: true,
      enableAddRow: true,
      enableAddBackTop: false,
      devicePixelRatio: window.devicePixelRatio || 1,
      fullscreenmode: false,
      data: luckyData,
      hook: {
        updated: function () {
          luckyDirty = true;
        },
        cellUpdated: function () {
          luckyDirty = true;
        }
      }
    });

    setTimeout(function () {
      if (window.luckysheet && window.luckysheet.resize) {
        window.luckysheet.resize();
      }
    }, 120);
  }

  function openLuckysheet() {
    backup = clone(sheets);
    edit = true;
    luckyDirty = false;

    if (stage) stage.style.display = 'none';
    if (tabsEl) tabsEl.style.display = 'none';
    if (zoomGroup) zoomGroup.style.display = 'none';
    if (luckyWrap) luckyWrap.hidden = false;

    if (editBtn) editBtn.hidden = true;
    if (saveBtn) { saveBtn.hidden = false; saveBtn.disabled = false; }
    if (cancelBtn) cancelBtn.hidden = false;
    if (fsBtn) fsBtn.hidden = false;
    if (printBtn) printBtn.hidden = true;
    if (hint) hint.hidden = false;

    setStatus('Spreadsheet edit mode: interactive Excel view. Modify formulas, cells, or formatting, then click Save.');

    var luckyData = sheets.map(function (s, idx) {
      return nexusToLuckysheet(s, idx, idx === cur);
    });

    // Allow DOM reflow and layout stabilization before Luckysheet reads element offset heights
    requestAnimationFrame(function () {
      setTimeout(function () {
        initLuckysheetInstance(luckyData);
      }, 40);
    });
  }

  function closeLuckysheet() {
    setFullScreen(false);
    if (window.luckysheet) {
      try { window.luckysheet.destroy(); } catch (e) {}
    }
    if (luckyWrap) luckyWrap.hidden = true;
    if (stage) stage.style.display = '';
    if (tabsEl) tabsEl.style.display = '';
    if (zoomGroup) zoomGroup.style.display = '';

    if (editBtn) editBtn.hidden = false;
    if (saveBtn) saveBtn.hidden = true;
    if (cancelBtn) cancelBtn.hidden = true;
    if (fsBtn) fsBtn.hidden = true;
    if (printBtn) printBtn.hidden = false;
    if (hint) hint.hidden = true;

    edit = false;
    luckyDirty = false;
  }

  if (editBtn) {
    editBtn.onclick = function () {
      var lazyFetches = [];
      sheets.forEach(function (s, idx) {
        if (s && s.lazy) lazyFetches.push(load(idx));
      });
      if (lazyFetches.length > 0) {
        setStatus('Preparing all sheets for spreadsheet editor…');
        Promise.all(lazyFetches).then(function () {
          openLuckysheet();
        }).catch(function () {
          setStatus('Failed to load all sheets.', true);
        });
      } else {
        openLuckysheet();
      }
    };
  }

  if (cancelBtn) {
    cancelBtn.onclick = function () {
      if (luckyDirty && !confirm('Discard all unsaved edits?')) return;
      sheets = backup ? clone(backup) : sheets;
      closeLuckysheet();
      render();
      setStatus('Edits discarded.');
    };
  }

  if (saveBtn) {
    saveBtn.onclick = function () {
      if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
      var allLucky = (window.luckysheet && window.luckysheet.getAllSheets) ? window.luckysheet.getAllSheets() : null;
      if (!allLucky || !allLucky.length) {
        setStatus('Nothing to save.', true);
        return;
      }

      saveBtn.disabled = true;
      setStatus('Saving workbook and updating Excel file on share…');

      var out = {}, base = {};
      allLucky.forEach(function (luckySheet, i) {
        var origSheet = sheets[i] || null;
        var model = luckysheetToNexus(luckySheet, origSheet, (D.styles || []).length);
        out[i] = model;
        base[i] = (origSheet && origSheet.rev != null) ? origSheet.rev : 0;
      });

      LdCollab.post(D.saveUrl, { sheets: out, base: base })
        .then(function (j) {
          saveBtn.disabled = false;
          if (!j.ok) {
            setStatus(j.message || 'Not saved.', true);
            return;
          }
          LdCollab.adopt();
          Object.keys(j.sheets || {}).forEach(function (i) {
            sheets[i] = j.sheets[i];
          });
          backup = null;
          luckyDirty = false;

          closeLuckysheet();
          render();

          var nSh = Object.keys(j.sheets).length;
          var msg = 'Saved in the database (' + nSh + ' sheet' + (nSh === 1 ? '' : 's') + '). ';
          if (j.file && j.file.ok) {
            msg += 'The Excel file on the share was updated (a backup copy was kept).';
            setStatus(msg, false);
          } else {
            msg += 'The Excel file on the share was NOT updated: ' + (j.file ? j.file.message : 'unknown') + ' (database copy kept).';
            setStatus(msg, true);
          }

          if (window.LdRevs && typeof LdRevs.reload === 'function') {
            LdRevs.reload();
          }
        })
        .catch(function (err) {
          saveBtn.disabled = false;
          setStatus('Failed to save: ' + (err.message || 'Server error'), true);
        });
    };
  }

  window.addEventListener('keydown', function (e) {
    if (edit) {
      if (e.key === 'Escape' && isFs) {
        e.preventDefault();
        setFullScreen(false);
      } else if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
        e.preventDefault();
        if (saveBtn && !saveBtn.disabled) saveBtn.click();
      }
    }
  });

  window.addEventListener('beforeunload', function (e) {
    if (edit && luckyDirty) { e.preventDefault(); e.returnValue = ''; }
  });

  if (printBtn) {
    printBtn.onclick = function () {
      if (edit) {
        window.print();
        return;
      }
      paper.style.zoom = '1';
      var s = S(), land = s.print && s.print.orient === 'landscape', M = 6.4, availW = ((land ? 297 : 210) - 2 * M) * MM;
      var w = tableEl ? tableEl.getBoundingClientRect().width : 800;
      var z = w > availW ? availW / w : 1;
      pageStyle.textContent = '@page{size:A4 ' + (land ? 'landscape' : 'portrait') + ';margin:' + M + 'mm}@media print{.bk-t{zoom:' + z.toFixed(4) + '}}';
      setTimeout(function () { window.print(); setZoom(Z); }, 60);
    };
  }

  function colName(n) {
    var s = '';
    while (n > 0) {
      var m = (n - 1) % 26;
      s = String.fromCharCode(65 + m) + s;
      n = Math.floor((n - 1) / 26);
    }
    return s;
  }

  LdCollab.bind({
    root: paper,
    posOf: function (t) { var td = t.closest && t.closest('td'); return td && td.dataset.r ? 's' + cur + ':' + td.dataset.r + ':' + td.dataset.c : null; },
    find: function (pos) { var m = /^s(\d+):(\d+):(\d+)$/.exec(pos); return m && Number(m[1]) === cur ? paper.querySelector('td[data-r="' + m[2] + '"][data-c="' + m[3] + '"]') : null; },
    describe: function (pos) { var m = /^s(\d+):(\d+):(\d+)$/.exec(pos); return m && sheets[m[1]] ? sheets[m[1]].name + ' · ' + colName(Number(m[3])) + m[2] : ''; },
    editing: function () { return edit; }
  });

  render();
})();
