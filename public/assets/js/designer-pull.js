/* Report Designer > Pull Test Graphs:
   Per-category draggable A4 designer (Rivet, Velcro, Eyelet, Lace Holder, Heel/Tongue Pull Tab)
   with up to 3 connected database tables, join builder, and drag-and-drop column placement. */
(() => {
  'use strict';
  const pane = document.getElementById('dsg-pane-pull');
  if (!pane || !window.NxPullGraph) return;
  const G = window.NxPullGraph;
  const $ = (s) => pane.querySelector(s);
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const el = (tag, cls, txt) => { const n = document.createElement(tag); if (cls) n.className = cls; if (txt != null) n.textContent = txt; return n; };
  const say = (t, bad) => { const s = $('#pg-status'); if (s) { s.textContent = t || ''; s.style.color = bad ? '#b91c1c' : ''; } };
  const api = (path, body) => fetch(BASE + path, Object.assign({
    credentials: 'same-origin',
    headers: Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' } : {})
  }, body ? { method: 'POST', body: JSON.stringify(body) } : {}))
    .then((r) => r.json().catch(() => ({})).then((j) => {
      if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
      if (!r.ok || j.ok === false) throw new Error(j.error || j.message || 'Request failed (' + r.status + ')');
      return j;
    }));

  let cat = $('#pg-cat')?.value || 'eyelet';
  let layout = G.defaultLayout(cat);
  let data = sampleData(cat);
  let allTables = [];
  let colsByTable = {};   // { tblName: [col1, col2...] }
  let specialCols = ['MaxPeak', 'MinPeak', 'AvgPeak', 'TestCountUsed', 'now'];
  let sel = null;
  let scale = 0.6;
  const canvas = $('#pg-canvas'), over = $('#pg-over'), stage = $('#pg-stage');

  function sampleData(c) {
    const m = G.catMeta(c);
    const d = {
      LabRefNo: 'DTP20261116EYA3-L',
      Factory: 'DATIAN SUBIC SHOES INC. (DTP)',
      ModelStyle: 'UA OLD SKOOL',
      Color: 'SALT WASH Gold Ochre',
      EquipmentCode: 'DTP-QC-001',
      MachineName: 'DTP-QC-001',
      TestedDate: '2026-11-17',
      Technician: 'L. Tuddao',
      Result: 'PASS',
      StartTimestamp: '2026-11-17 08:27:16',
      FinishTimestamp: '2026-11-17 08:38:27',
      Speed: '100',
      SpeedUnit: 'mm/min',
      TestType: m.testType,
      cat: c
    };
    const peaks = [35.94, 45.00, 38.04, 27.90, 34.94, 36.84, 43.90, 53.74];
    const snos = ['L1', '3', '5', '7', 'M1', '3', '5', '7'];
    for (let i = 0; i < peaks.length; i++) {
      d['Peak' + (i + 1)] = String(peaks[i]);
      d['SampleNo' + (i + 1)] = snos[i];
      let g = '0;0';
      const maxT = 6 + (i % 7);
      for (let t = 0.4; t <= maxT; t += 0.4) {
        const y = Math.min(peaks[i], (peaks[i] * (t / maxT)) * (0.85 + 0.15 * Math.sin(t * 1.3 + i)));
        g += ';' + t.toFixed(2) + ';' + y.toFixed(2);
      }
      g += ';' + maxT.toFixed(2) + ';' + (peaks[i] * 0.45).toFixed(2);
      d['Graph' + (i + 1)] = g;
    }
    return d;
  }

  function ensureSources() {
    if (!layout.sources || !Array.isArray(layout.sources.tables)) layout.sources = G.defaultSources();
    while (layout.sources.tables.length < 3) layout.sources.tables.push('');
    if (!Array.isArray(layout.sources.joins)) layout.sources.joins = [null, { leftTable: 0, leftCol: '', rightCol: '' }, { leftTable: 0, leftCol: '', rightCol: '' }];
    while (layout.sources.joins.length < 3) layout.sources.joins.push({ leftTable: 0, leftCol: '', rightCol: '' });
  }

  let raf = 0;
  const redraw = () => { cancelAnimationFrame(raf); raf = requestAnimationFrame(() => { G.render(canvas, layout, data); frames(); }); };
  const fit = () => {
    const w = (stage.parentElement?.clientWidth || 760) - 24;
    scale = Math.max(0.2, Math.min(1, w / (layout.w || 1240)));
    canvas.style.width = (layout.w * scale) + 'px';
    canvas.style.height = (layout.h * scale) + 'px';
    over.style.width = canvas.style.width;
    over.style.height = canvas.style.height;
    redraw();
  };

  const item = (id) => layout.items.find((i) => i.id === id);
  const uid = (t) => t + Math.random().toString(36).slice(2, 6);

  const frames = () => {
    over.textContent = '';
    layout.items.forEach((it) => {
      const b = G.bounds(layout, it, data);
      const f = el('div', 'dg-frame' + (it.id === sel ? ' sel' : ''));
      f.dataset.id = it.id;
      f.style.cssText = `left:${b.x * scale}px;top:${b.y * scale}px;width:${Math.max(12, b.w * scale)}px;height:${Math.max(10, b.h * scale)}px`;
      f.title = it.type + (it.template ? ': ' + it.template : (it.text ? ': ' + it.text : ''));
      if (it.id === sel && it.type !== 'text' && it.type !== 'field') {
        const g = el('i', 'dg-grip');
        g.dataset.grip = '1';
        f.append(g);
      }
      over.append(f);
    });
  };

  // ---- pointer drag & resize on canvas overlay
  let drag = null;
  over.addEventListener('pointerdown', (e) => {
    const f = e.target.closest('.dg-frame');
    if (!f) { select(null); return; }
    const it = item(f.dataset.id);
    if (!it) return;
    select(it.id);
    drag = { it, mode: e.target.dataset.grip ? 'size' : 'move', sx: e.clientX, sy: e.clientY, ox: it.x, oy: it.y, ow: it.w, oh: it.h };
    over.setPointerCapture(e.pointerId);
    e.preventDefault();
  });
  over.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const dx = (e.clientX - drag.sx) / scale, dy = (e.clientY - drag.sy) / scale, it = drag.it;
    if (drag.mode === 'move') { it.x = Math.round(drag.ox + dx); it.y = Math.round(drag.oy + dy); }
    else {
      it.w = Math.max(40, Math.round((drag.ow || 300) + dx));
      if (it.type === 'pullgraph') it.h = Math.max(120, Math.round((drag.oh || 430) + dy));
    }
    redraw();
    props();
  });
  over.addEventListener('pointerup', () => { drag = null; });

  // ---- HTML5 drag & drop from Columns toolbox onto canvas
  over.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; over.classList.add('pg-drop-active'); });
  over.addEventListener('dragleave', () => over.classList.remove('pg-drop-active'));
  over.addEventListener('drop', (e) => {
    e.preventDefault();
    over.classList.remove('pg-drop-active');
    const colToken = e.dataTransfer.getData('text/plain');
    if (!colToken) return;
    const rect = over.getBoundingClientRect();
    const x = Math.max(10, Math.round((e.clientX - rect.left) / scale));
    const y = Math.max(10, Math.round((e.clientY - rect.top) / scale));
    const shortName = colToken.includes('.') ? colToken.split('.').pop() : colToken;
    const it = { id: uid('fld'), type: 'field', x, y, w: 360, template: shortName + ': {' + colToken + '}', size: 16, align: 'left', color: '#111111' };
    layout.items.push(it);
    select(it.id);
    redraw();
  });

  stage.addEventListener('keydown', (e) => {
    if (!sel || e.target.closest('input,select,textarea')) return;
    const it = item(sel), k = e.shiftKey ? 10 : 1;
    if (!it) return;
    if (e.key === 'ArrowLeft') it.x -= k;
    else if (e.key === 'ArrowRight') it.x += k;
    else if (e.key === 'ArrowUp') it.y -= k;
    else if (e.key === 'ArrowDown') it.y += k;
    else if (e.key === 'Delete') { layout.items = layout.items.filter((i) => i.id !== sel); select(null); redraw(); return; }
    else return;
    e.preventDefault();
    redraw();
    props();
  });

  // ---- 3-Table Connector UI
  function renderSources() {
    ensureSources();
    const box = $('#pg-sources');
    box.textContent = '';
    const tbls = layout.sources.tables;
    const joins = layout.sources.joins;

    for (let i = 0; i < 3; i++) {
      const card = el('div', 'pg-src-card');
      const lbl = el('div', 'pg-src-head', i === 0 ? 'Table 1 (Primary)' : `Table ${i + 1} (Optional Join)`);
      const selTbl = el('select', 'pg-src-sel');
      selTbl.append(Object.assign(el('option', '', i === 0 ? '-- Select primary table --' : '-- None --'), { value: '' }));
      allTables.forEach((t) => selTbl.append(Object.assign(el('option', '', t), { value: t })));
      selTbl.value = tbls[i] || '';
      selTbl.addEventListener('change', async () => {
        tbls[i] = selTbl.value;
        await refreshColumns();
        if (i === 0) await loadRecords();
        renderSources();
      });
      card.append(lbl, selTbl);

      if (i > 0 && tbls[i]) {
        const j = joins[i] || (joins[i] = { leftTable: 0, leftCol: '', rightCol: '' });
        const jRow = el('div', 'pg-join-row');
        const ltSel = el('select');
        for (let k = 0; k < i; k++) {
          if (tbls[k]) ltSel.append(Object.assign(el('option', '', `T${k + 1}: ${tbls[k]}`), { value: String(k) }));
        }
        ltSel.value = String(j.leftTable || 0);

        const lcSel = el('select');
        const fillLc = () => {
          lcSel.textContent = '';
          lcSel.append(Object.assign(el('option', '', '-- left col --'), { value: '' }));
          (colsByTable[tbls[Number(ltSel.value)]] || []).forEach((c) => lcSel.append(Object.assign(el('option', '', c), { value: c })));
          lcSel.value = j.leftCol || '';
        };
        fillLc();

        const rcSel = el('select');
        rcSel.append(Object.assign(el('option', '', '-- right col --'), { value: '' }));
        (colsByTable[tbls[i]] || []).forEach((c) => rcSel.append(Object.assign(el('option', '', c), { value: c })));
        rcSel.value = j.rightCol || '';

        ltSel.addEventListener('change', () => { j.leftTable = Number(ltSel.value); j.leftCol = ''; fillLc(); });
        lcSel.addEventListener('change', () => { j.leftCol = lcSel.value; });
        rcSel.addEventListener('change', () => { j.rightCol = rcSel.value; });

        jRow.append(el('span', 'muted sm', 'Connect:'), ltSel, lcSel, el('span', 'muted sm', '='), rcSel);
        card.append(jRow);
      }
      box.append(card);
    }
  }

  async function refreshColumns() {
    ensureSources();
    const active = layout.sources.tables.filter(Boolean);
    if (!active.length) { colsByTable = {}; renderCols(); return; }
    try {
      const r = await api('designer/db-columns?tables=' + encodeURIComponent(active.join(',')));
      colsByTable = r.columns || {};
      specialCols = r.special || specialCols;
    } catch (e) {
      say(e.message, true);
    }
    renderCols();
  }

  function allColumnTokens() {
    const list = [];
    Object.keys(colsByTable).forEach((tbl) => {
      (colsByTable[tbl] || []).forEach((col) => list.push({ group: tbl, token: tbl + '.' + col, label: col }));
    });
    specialCols.forEach((s) => list.push({ group: 'Computed & Special', token: s, label: s }));
    return list;
  }

  function renderCols() {
    const box = $('#pg-cols');
    box.textContent = '';
    const q = ($('#pg-colq')?.value || '').trim().toLowerCase();
    const byGrp = new Map();
    allColumnTokens().forEach((c) => {
      if (q && !c.token.toLowerCase().includes(q) && !c.group.toLowerCase().includes(q)) return;
      if (!byGrp.has(c.group)) byGrp.set(c.group, []);
      byGrp.get(c.group).push(c);
    });
    byGrp.forEach((arr, grp) => {
      box.append(el('div', 'dg-grp', grp));
      arr.forEach((c) => {
        const b = el('button', 'dg-col', c.label);
        b.type = 'button';
        b.draggable = true;
        b.title = 'Drag onto page or click to insert {' + c.token + '}';
        b.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/plain', c.token); e.dataTransfer.effectAllowed = 'copy'; });
        b.addEventListener('click', () => {
          const cur = sel && item(sel);
          if (cur && cur.type === 'field') {
            cur.template = (cur.template || '') + '{' + c.token + '}';
            props();
            redraw();
            return;
          }
          const it = { id: uid('fld'), type: 'field', x: 60, y: 120 + (layout.items.length % 8) * 28, w: 360, template: c.label + ': {' + c.token + '}', size: 16, align: 'left', color: '#111111' };
          layout.items.push(it);
          select(it.id);
          redraw();
        });
        box.append(b);
      });
    });
  }
  $('#pg-colq')?.addEventListener('input', renderCols);

  // ---- Properties inspector
  const select = (id) => { sel = id; frames(); props(); };
  const field = (label, input) => { const l = el('label', 'dg-fld'); l.append(el('span', '', label), input); return l; };
  const numIn = (it, key, step) => { const i = el('input'); i.type = 'number'; i.step = step || 1; i.value = it[key] ?? ''; i.addEventListener('input', () => { it[key] = i.value === '' ? 0 : Number(i.value); redraw(); }); return i; };
  const txtIn = (it, key) => { const i = el('input'); i.type = 'text'; i.value = it[key] ?? ''; i.addEventListener('input', () => { it[key] = i.value; redraw(); }); return i; };
  const chk = (it, key, label) => { const l = el('label', 'dg-chk'); const i = el('input'); i.type = 'checkbox'; i.checked = !!it[key]; i.addEventListener('change', () => { it[key] = i.checked; redraw(); }); l.append(i, document.createTextNode(' ' + label)); return l; };
  const selIn = (it, key, opts) => { const s = el('select'); opts.forEach(([v, t]) => { const o = el('option', '', t); o.value = v; s.append(o); }); s.value = it[key] ?? opts[0][0]; s.addEventListener('change', () => { it[key] = s.value; redraw(); }); return s; };

  function colPickerDropdown(onPick) {
    const add = el('select');
    add.append(Object.assign(el('option', '', 'Insert a column...'), { value: '' }));
    allColumnTokens().forEach((c) => add.append(Object.assign(el('option', '', `${c.group} → ${c.label}`), { value: c.token })));
    add.addEventListener('change', () => { if (add.value) { onPick(add.value); add.value = ''; } });
    return add;
  }

  function props() {
    const box = $('#pg-props');
    box.textContent = '';
    const it = sel && item(sel);
    if (!it) { box.append(el('p', 'muted sm', 'Click an item on the page or drag a column onto the page.')); return; }
    box.append(el('p', 'dg-type', it.type.toUpperCase()));
    const g = el('div', 'dg-grid');
    g.append(field('X', numIn(it, 'x')), field('Y', numIn(it, 'y')), field('Width', numIn(it, 'w')));
    if (it.type === 'pullgraph') g.append(field('Height', numIn(it, 'h')));
    box.append(g);

    if (it.type === 'text') box.append(field('Text', txtIn(it, 'text')));
    if (it.type === 'field') {
      box.append(field('Shows (use {Table.Column})', txtIn(it, 'template')), chk(it, 'autoResult', 'Colour by result (PASS green, FAIL red)'));
      box.append(field('Insert column', colPickerDropdown((tok) => { it.template = (it.template || '') + '{' + tok + '}'; props(); redraw(); })));
    }
    if (it.type === 'text' || it.type === 'field') {
      box.append(field('Size', numIn(it, 'size')), field('Align', selIn(it, 'align', [['left', 'Left'], ['center', 'Centre'], ['right', 'Right']])), chk(it, 'bold', 'Bold'));
      const c = el('input'); c.type = 'color'; c.value = it.color || '#111111'; c.addEventListener('input', () => { it.color = c.value; redraw(); });
      box.append(field('Colour', c));
    }
    if (it.type === 'infogrid') {
      box.append(field('Font size', numIn(it, 'size')), field('Row height', numIn(it, 'rowH')));
      ['left', 'right'].forEach((side) => {
        const hdr = el('div', 'dg-grp', side === 'left' ? 'Left column rows' : 'Right column rows');
        box.append(hdr);
        const arr = it[side] || (it[side] = []);
        arr.forEach((pair, idx) => {
          const row = el('div', 'pg-pair-row');
          const lIn = el('input'); lIn.type = 'text'; lIn.value = pair[0] || ''; lIn.placeholder = 'Label';
          const vIn = el('input'); vIn.type = 'text'; vIn.value = pair[1] || ''; vIn.placeholder = '{Column}';
          const rm = el('button', 'dsg-btn dsg-danger', '×'); rm.type = 'button'; rm.style.padding = '2px 7px';
          lIn.addEventListener('input', () => { pair[0] = lIn.value; redraw(); });
          vIn.addEventListener('input', () => { pair[1] = vIn.value; redraw(); });
          rm.addEventListener('click', () => { arr.splice(idx, 1); props(); redraw(); });
          row.append(lIn, vIn, rm);
          box.append(row);
        });
        const addBtn = el('button', 'dsg-btn', '+ Add ' + side + ' row');
        addBtn.type = 'button';
        addBtn.addEventListener('click', () => { arr.push(['Label', '{Column}']); props(); redraw(); });
        box.append(addBtn);
      });
    }
    if (it.type === 'pulltable') {
      box.append(field('Font size', numIn(it, 'size')), field('Row height', numIn(it, 'rowH')), field('Header height', numIn(it, 'headH')), field('Max rows', numIn(it, 'maxRows')), chk(it, 'showSummary', 'Show Max / Min / Avg summary'));
      box.append(el('div', 'dg-grp', 'Table columns'));
      const cols = it.columns || (it.columns = []);
      cols.forEach((c, idx) => {
        const row = el('div', 'pg-pair-row');
        const lIn = el('input'); lIn.type = 'text'; lIn.value = c.label || ''; lIn.placeholder = 'Header';
        const vIn = el('input'); vIn.type = 'text'; vIn.value = c.tpl || ''; vIn.placeholder = '{Field}';
        const wIn = el('input'); wIn.type = 'number'; wIn.step = '0.02'; wIn.value = c.w ?? 0.15; wIn.style.width = '56px';
        const rm = el('button', 'dsg-btn dsg-danger', '×'); rm.type = 'button'; rm.style.padding = '2px 7px';
        lIn.addEventListener('input', () => { c.label = lIn.value; redraw(); });
        vIn.addEventListener('input', () => { c.tpl = vIn.value; redraw(); });
        wIn.addEventListener('input', () => { c.w = Number(wIn.value) || 0.1; redraw(); });
        rm.addEventListener('click', () => { cols.splice(idx, 1); props(); redraw(); });
        row.append(lIn, vIn, wIn, rm);
        box.append(row);
      });
      const addCol = el('button', 'dsg-btn', '+ Add column');
      addCol.type = 'button';
      addCol.addEventListener('click', () => { cols.push({ label: 'Column', tpl: '{Peak}', w: 0.15, align: 'left' }); props(); redraw(); });
      box.append(addCol);
    }
    if (it.type === 'pullgraph') {
      box.append(
        field('Top kgf axis (0 = auto)', numIn(it, 'yMax')),
        field('Max sec axis (0 = auto)', numIn(it, 'xMax')),
        field('Y-axis label', txtIn(it, 'yLabel')),
        field('X-axis label', txtIn(it, 'xLabel')),
        field('Font size', numIn(it, 'size')),
        chk(it, 'markers', 'Show peak (X) markers'),
        chk(it, 'labels', 'Show curve numbers (1, 2...)')
      );
    }
    if (it.type === 'line') {
      const c = el('input'); c.type = 'color'; c.value = it.color || '#cbd5e1'; c.addEventListener('input', () => { it.color = c.value; redraw(); });
      box.append(field('Thickness', numIn(it, 'size')), field('Colour', c));
    }

    const del = el('button', 'dsg-btn dsg-danger', 'Delete this item');
    del.type = 'button';
    del.style.marginTop = '10px';
    del.addEventListener('click', () => { layout.items = layout.items.filter((i) => i.id !== it.id); select(null); redraw(); });
    box.append(del);
  }

  // ---- Toolbox add buttons
  pane.querySelectorAll('[data-padd]').forEach((b) => b.addEventListener('click', () => {
    const t = b.dataset.padd, id = uid(t);
    const def = G.defaultLayout(cat);
    const base = {
      text: { id, type: 'text', x: 620, y: 110, w: 500, text: 'Heading', size: 22, bold: true, align: 'center', color: '#111111' },
      field: { id, type: 'field', x: 60, y: 110, w: 360, template: '{LabRefNo}', size: 16, align: 'left', color: '#111111' },
      infogrid: Object.assign({}, def.items.find((x) => x.type === 'infogrid'), { id, y: 130 }),
      pulltable: Object.assign({}, def.items.find((x) => x.type === 'pulltable'), { id, y: 290 }),
      pullgraph: Object.assign({}, def.items.find((x) => x.type === 'pullgraph'), { id, y: 610 }),
      line: { id, type: 'line', x: 56, y: 270, w: 1128, size: 1, color: '#cbd5e1' }
    }[t];
    if (!base) return;
    layout.items.push(base);
    select(id);
    redraw();
  }));

  // ---- Load records & category layout
  async function loadRecords() {
    ensureSources();
    const rec = $('#pg-record');
    if (!rec) return;
    const t1 = layout.sources.tables[0] || 'tbl_dtr_pull_tests';
    rec.innerHTML = '<option value="">Sample preview data</option>';
    try {
      const r = await api(`designer/pull-records?table=${encodeURIComponent(t1)}&cat=${encodeURIComponent(cat)}`);
      const pk = r.pk || 'PullInfoID';
      (r.records || []).forEach((x) => {
        const ref = x.LabRefNo || '';
        const pkVal = x[pk] || '';
        const label = [ref || ('#' + pkVal), x.TestType || '', x.ModelStyle || '', x.TestedDate || ''].filter(Boolean).join(' · ');
        const opt = el('option', '', label);
        opt.value = JSON.stringify({ ref, pkVal });
        rec.append(opt);
      });
    } catch (_) {}
  }

  $('#pg-record')?.addEventListener('change', async (e) => {
    const v = e.target.value;
    if (!v) { data = sampleData(cat); redraw(); return; }
    try {
      const parsed = JSON.parse(v);
      say('Loading record...');
      const r = await api(`dtr/pull-graph-data?cat=${encodeURIComponent(cat)}&lab_ref=${encodeURIComponent(parsed.ref || '')}&pk_val=${encodeURIComponent(parsed.pkVal || '')}`);
      data = Object.assign({}, r.data, { cat });
      say('');
      redraw();
    } catch (err) {
      say(err.message, true);
    }
  });

  async function loadCategory(newCat) {
    cat = newCat || 'eyelet';
    sel = null;
    say('Loading layout...');
    try {
      const r = await api('dtr/pull-graph-layout?cat=' + encodeURIComponent(cat));
      layout = (r.layout && r.layout.items) ? r.layout : G.defaultLayout(cat);
    } catch (_) {
      layout = G.defaultLayout(cat);
    }
    ensureSources();
    data = sampleData(cat);
    renderSources();
    await refreshColumns();
    await loadRecords();
    fit();
    props();
    say('');
  }

  $('#pg-cat')?.addEventListener('change', (e) => loadCategory(e.target.value));
  $('#pg-fit')?.addEventListener('click', fit);
  $('#pg-png')?.addEventListener('click', () => {
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = `pull-${cat}-preview.png`;
    a.click();
  });
  $('#pg-save')?.addEventListener('click', async () => {
    say('Saving...');
    try {
      await api('designer/pull-layout/save', { cat, layout });
      say('Saved for ' + G.catMeta(cat).label + '!');
    } catch (e) {
      say(e.message, true);
    }
  });
  $('#pg-reset')?.addEventListener('click', async () => {
    if (!confirm('Reset ' + G.catMeta(cat).label + ' back to the default layout?')) return;
    try {
      await api('designer/pull-layout/save', { cat, reset: true });
      layout = G.defaultLayout(cat);
      ensureSources();
      renderSources();
      await refreshColumns();
      select(null);
      redraw();
      say('Reset to default.');
    } catch (e) {
      say(e.message, true);
    }
  });

  let booted = false;
  document.querySelectorAll('.dsg-tab').forEach((t) => t.addEventListener('click', async () => {
    if (t.dataset.tab !== 'pull') return;
    if (!booted) {
      booted = true;
      try {
        const r = await api('designer/db-tables');
        allTables = r.tables || [];
      } catch (_) {
        allTables = ['tbl_dtr_pull_tests', 'tbl_dtr_bonding_info', 'tbl_dtr_bonding_points'];
      }
      await loadCategory($('#pg-cat')?.value || 'eyelet');
    } else {
      setTimeout(fit, 30);
    }
  }));
  window.addEventListener('resize', () => { if (!pane.hidden) fit(); });
})();
