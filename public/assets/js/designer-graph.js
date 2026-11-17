/* Report Designer > Graph report (PNG): a draggable A4 page for the Bonding Test-VF graph report.
   Uses NxBondGraph (bond-graph.js) to draw the page, so what you see here is exactly what Generate Report saves as a PNG. */
(() => {
  'use strict';
  const pane = document.getElementById('dsg-pane-graph');
  if (!pane || !window.NxBondGraph) return;
  const G = window.NxBondGraph;
  const $ = (s) => pane.querySelector(s);
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const COLS = window.DSG_GRAPH_COLS || [];
  const el = (tag, cls, txt) => { const n = document.createElement(tag); if (cls) n.className = cls; if (txt != null) n.textContent = txt; return n; };
  const say = (t, bad) => { const s = $('#dg-status'); s.textContent = t || ''; s.style.color = bad ? '#b91c1c' : ''; };

  let layout = G.defaultLayout();
  let data = sampleData();
  let sel = null;                      // selected item id
  let scale = 0.6;                     // screen px per page px
  const canvas = $('#dg-canvas'), over = $('#dg-over'), stage = $('#dg-stage');

  // ---- sample values shown until a real record is picked
  function sampleData() {
    const d = { LabRefNo: 'DTP20260000SHB0-R', ShoeName: 'UA LX OLD SKOOL 36 Pearlized Woodland NAVY', EquipmentCode: 'DTP-QC-064', TestDate: '2026-11-11', Width: '10', AgeCategory: 'Adult', Technician: 'J. Barrera', Result: 'PASS', UTFCount: '30', FTOCount: '30' };
    let g1 = '0;0', g2 = '0;0', s1 = '', s2 = '';
    for (let i = 1; i <= 30; i++) {
      d['UTFpoint' + i] = (3.4 + Math.abs(Math.sin(i)) * 1.8).toFixed(2); d['FTOpoint' + i] = (3.2 + Math.abs(Math.cos(i)) * 1.6).toFixed(2);
      s1 += (i > 1 ? ';' : '') + (i * 18) + ';' + d['UTFpoint' + i]; s2 += (i > 1 ? ';' : '') + (i * 17 + 5) + ';' + d['FTOpoint' + i];
    }
    for (let t = 1; t <= 550; t += 3) { g1 += ';' + t + ';' + (3.6 + Math.abs(Math.sin(t / 25)) * 1.7 + Math.random() * 0.4).toFixed(2); g2 += ';' + t + ';' + (3.4 + Math.abs(Math.cos(t / 31)) * 1.9 + Math.random() * 0.4).toFixed(2); }
    d.UTFGraph = g1; d.FTOGraph = g2; d.UTFSpots = s1; d.FTOSpots = s2;
    return d;
  }

  // ---- drawing + frames
  let raf = 0;
  const redraw = () => { cancelAnimationFrame(raf); raf = requestAnimationFrame(() => { G.render(canvas, layout, data); frames(); }); };
  const fit = () => {
    const w = stage.parentElement.clientWidth - 24;
    scale = Math.max(0.2, Math.min(1, w / (layout.w || 1240)));
    canvas.style.width = (layout.w * scale) + 'px'; canvas.style.height = (layout.h * scale) + 'px';
    over.style.width = canvas.style.width; over.style.height = canvas.style.height;
    redraw();
  };
  const frames = () => {
    over.textContent = '';
    layout.items.forEach((it) => {
      const b = G.bounds(layout, it, data);
      const f = el('div', 'dg-frame' + (it.id === sel ? ' sel' : '')); f.dataset.id = it.id;
      f.style.cssText = `left:${b.x * scale}px;top:${b.y * scale}px;width:${Math.max(8, b.w * scale)}px;height:${Math.max(8, b.h * scale)}px`;
      f.title = it.type + (it.type === 'field' ? ': ' + it.template : '');
      if (it.id === sel && it.type !== 'text' && it.type !== 'field') { const g = el('i', 'dg-grip'); g.dataset.grip = '1'; f.append(g); }
      over.append(f);
    });
  };
  const item = (id) => layout.items.find((i) => i.id === id);
  const uid = (t) => t + Math.random().toString(36).slice(2, 6);

  // ---- drag / resize
  let drag = null;
  over.addEventListener('pointerdown', (e) => {
    const f = e.target.closest('.dg-frame'); if (!f) { select(null); return; }
    const it = item(f.dataset.id); select(it.id);
    drag = { it, mode: e.target.dataset.grip ? 'size' : 'move', sx: e.clientX, sy: e.clientY, ox: it.x, oy: it.y, ow: it.w, oh: it.h };
    over.setPointerCapture(e.pointerId); e.preventDefault();
  });
  over.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const dx = (e.clientX - drag.sx) / scale, dy = (e.clientY - drag.sy) / scale, it = drag.it;
    if (drag.mode === 'move') { it.x = Math.round(drag.ox + dx); it.y = Math.round(drag.oy + dy); }
    else { it.w = Math.max(40, Math.round(drag.ow + dx)); if (it.type === 'graph') it.h = Math.max(100, Math.round((drag.oh || 600) + dy)); }
    redraw(); props();
  });
  over.addEventListener('pointerup', () => { drag = null; });
  stage.addEventListener('keydown', (e) => {
    if (!sel || e.target.closest('input,select,textarea')) return;
    const it = item(sel), k = e.shiftKey ? 10 : 1;
    if (e.key === 'ArrowLeft') it.x -= k; else if (e.key === 'ArrowRight') it.x += k; else if (e.key === 'ArrowUp') it.y -= k; else if (e.key === 'ArrowDown') it.y += k;
    else if (e.key === 'Delete') { layout.items = layout.items.filter((i) => i.id !== sel); select(null); return; } else return;
    e.preventDefault(); redraw(); props();
  });

  // ---- properties
  const select = (id) => { sel = id; frames(); props(); };
  const field = (label, input) => { const l = el('label', 'dg-fld'); l.append(el('span', '', label), input); return l; };
  const numIn = (it, key, step) => { const i = el('input'); i.type = 'number'; i.step = step || 1; i.value = it[key] ?? ''; i.addEventListener('input', () => { it[key] = i.value === '' ? 0 : Number(i.value); redraw(); }); return i; };
  const txtIn = (it, key) => { const i = el('input'); i.type = 'text'; i.value = it[key] ?? ''; i.addEventListener('input', () => { it[key] = i.value; redraw(); }); return i; };
  const chk = (it, key, label) => { const l = el('label', 'dg-chk'); const i = el('input'); i.type = 'checkbox'; i.checked = !!it[key]; i.addEventListener('change', () => { it[key] = i.checked; redraw(); }); l.append(i, document.createTextNode(' ' + label)); return l; };
  const selIn = (it, key, opts) => { const s = el('select'); opts.forEach(([v, t]) => { const o = el('option', '', t); o.value = v; s.append(o); }); s.value = it[key] ?? opts[0][0]; s.addEventListener('change', () => { it[key] = s.value; redraw(); }); return s; };
  const seriesPick = (it) => {
    const w = el('div', 'dg-sers');
    layout.series.forEach((s) => { const l = el('label', 'dg-chk'); const i = el('input'); i.type = 'checkbox'; i.checked = (it.series || []).includes(s.key);
      i.addEventListener('change', () => { const cur = new Set(it.series || []); if (i.checked) cur.add(s.key); else cur.delete(s.key); it.series = layout.series.map((x) => x.key).filter((k) => cur.has(k)); redraw(); });
      l.append(i, document.createTextNode(' ' + s.label)); w.append(l); });
    return w;
  };
  function props() {
    const box = $('#dg-props'); box.textContent = '';
    const it = sel && item(sel);
    if (!it) { box.append(el('p', 'muted sm', 'Click an item on the page.')); return; }
    box.append(el('p', 'dg-type', it.type.toUpperCase()));
    const g = el('div', 'dg-grid'); g.append(field('X', numIn(it, 'x')), field('Y', numIn(it, 'y')), field('Width', numIn(it, 'w')));
    if (it.type === 'graph') g.append(field('Height', numIn(it, 'h')));
    box.append(g);
    if (it.type === 'text') box.append(field('Text', txtIn(it, 'text')));
    if (it.type === 'field') {
      box.append(field('Shows (use {Column})', txtIn(it, 'template')), chk(it, 'autoResult', 'Colour by result (PASS green, FAIL red)'));
      const add = el('select'); add.append(Object.assign(el('option', '', 'Insert a column...'), { value: '' }));
      COLS.forEach((c) => add.append(Object.assign(el('option', '', c.name), { value: c.name })));
      add.addEventListener('change', () => { if (add.value) { it.template = (it.template || '') + '{' + add.value + '}'; props(); redraw(); } });
      box.append(field('Add column', add));
    }
    if (it.type === 'text' || it.type === 'field') {
      box.append(field('Size', numIn(it, 'size')), field('Align', selIn(it, 'align', [['left', 'Left'], ['center', 'Centre'], ['right', 'Right']])), chk(it, 'bold', 'Bold'));
      const c = el('input'); c.type = 'color'; c.value = it.color || '#000000'; c.addEventListener('input', () => { it.color = c.value; redraw(); }); box.append(field('Colour', c));
    }
    if (it.type === 'tests') box.append(field('Font size', numIn(it, 'size')), field('Row height', numIn(it, 'rowH')), field('Header height', numIn(it, 'headH')), field('Speed (mm/min)', txtIn(it, 'speed')), field('Tests shown', seriesPick(it)));
    if (it.type === 'points') box.append(field('From point', numIn(it, 'from')), field('To point', numIn(it, 'to')), field('Font size', numIn(it, 'size')), field('Row height', numIn(it, 'rowH')), field('Header height', numIn(it, 'headH')), field('Rows (tests)', seriesPick(it)));
    if (it.type === 'graph') box.append(field('Curves', seriesPick(it)), field('Top of kgf axis', numIn(it, 'yMax')), field('Seconds axis (0 = automatic)', numIn(it, 'xMax')), field('Left label', txtIn(it, 'yLabel')), field('Bottom label', txtIn(it, 'xLabel')), field('Font size', numIn(it, 'size')), chk(it, 'markers', 'Show the spot marks (X)'));
    if (it.type === 'line') box.append(field('Thickness', numIn(it, 'size')));
    const del = el('button', 'dsg-btn dsg-danger', 'Delete this item'); del.type = 'button'; del.addEventListener('click', () => { layout.items = layout.items.filter((i) => i.id !== it.id); select(null); redraw(); });
    box.append(del);
  }

  // ---- toolbox: add items, columns (drag onto the page), series table
  pane.querySelectorAll('[data-add]').forEach((b) => b.addEventListener('click', () => {
    const t = b.dataset.add, base = { id: uid(t), type: t, x: 80, y: 80, w: 400 };
    const mk = {
      text: { text: 'New text', size: 22, align: 'left', color: '#000000' },
      tests: { w: 1121, size: 19, rowH: 34, headH: 62, speed: '100', series: layout.series.map((s) => s.key) },
      points: { w: 1111, size: 17, rowH: 46, headH: 62, from: 1, to: 15, series: layout.series.map((s) => s.key) },
      graph: { w: 1131, h: 600, series: layout.series.map((s) => s.key), yMax: 10, xMax: 0, markers: true, xLabel: 'sec', yLabel: 'kgf', size: 16 },
      line: { w: 400, size: 1, color: '#000000' },
    }[t];
    layout.items.push(Object.assign(base, mk)); select(base.id); redraw();
  }));
  const cols = $('#dg-cols');
  const drawCols = () => {
    cols.textContent = ''; const q = ($('#dg-colq').value || '').toLowerCase(); let grp = '';
    COLS.filter((c) => !q || c.name.toLowerCase().includes(q)).forEach((c) => {
      if (c.group !== grp) { grp = c.group; cols.append(el('div', 'dg-grp', grp)); }
      const chip = el('div', 'dg-chip', c.name); chip.draggable = true; chip.dataset.col = c.name; chip.title = 'Drag onto the page';
      chip.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/plain', c.name); e.dataTransfer.effectAllowed = 'copy'; });
      cols.append(chip);
    });
  };
  $('#dg-colq').addEventListener('input', drawCols);
  stage.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; });
  stage.addEventListener('drop', (e) => {
    e.preventDefault(); const name = e.dataTransfer.getData('text/plain'); if (!name) return;
    const r = canvas.getBoundingClientRect(), x = Math.round((e.clientX - r.left) / scale), y = Math.round((e.clientY - r.top) / scale);
    const it = { id: uid('f'), type: 'field', x, y, w: 300, template: (name === 'now' ? '{now}' : name + ': {' + name + '}'), size: 19, align: 'left', color: '#000000' };
    layout.items.push(it); select(it.id); redraw();
  });

  const colSelect = (s, key, filter) => {
    const sel1 = el('select'); sel1.append(Object.assign(el('option', '', '(none)'), { value: '' }));
    COLS.filter(filter).forEach((c) => sel1.append(Object.assign(el('option', '', c.name), { value: c.name })));
    if (s[key] && ![...sel1.options].some((o) => o.value === s[key])) sel1.append(Object.assign(el('option', '', s[key]), { value: s[key] }));
    sel1.value = s[key] || ''; sel1.addEventListener('change', () => { s[key] = sel1.value; redraw(); }); return sel1;
  };
  const drawSeries = () => {
    const box = $('#dg-series'); box.textContent = '';
    layout.series.forEach((s) => {
      const card = el('div', 'dg-ser');
      const head = el('div', 'dg-ser-h'); const on = el('input'); on.type = 'checkbox'; on.checked = s.on !== false; on.addEventListener('change', () => { s.on = on.checked; redraw(); });
      const name = el('input'); name.type = 'text'; name.value = s.label; name.addEventListener('input', () => { s.label = name.value; redraw(); });
      const col = el('input'); col.type = 'color'; col.value = s.color || '#d62728'; col.addEventListener('input', () => { s.color = col.value; redraw(); });
      head.append(on, name, col); card.append(head);
      const pre = el('input'); pre.type = 'text'; pre.value = s.points; pre.title = 'Prefix of the reading columns, e.g. UTFpoint gives UTFpoint1, UTFpoint2...'; pre.addEventListener('input', () => { s.points = pre.value; redraw(); });
      card.append(field('Readings prefix', pre), field('Count column', colSelect(s, 'count', (c) => /Count$/.test(c.name))), field('Curve column', colSelect(s, 'graph', (c) => /Graph$/.test(c.name))), field('Spot marks column', colSelect(s, 'spots', (c) => /Spots$/.test(c.name))));
      box.append(card);
    });
  };

  // ---- records, load, save
  const api = (path, body) => fetch(BASE + path, Object.assign({ credentials: 'same-origin', headers: Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' } : {}) }, body ? { method: 'POST', body: JSON.stringify(body) } : {}))
    .then((r) => r.json().then((j) => { if (csrfMeta && j.csrf) csrfMeta.content = j.csrf; if (!r.ok || j.ok === false) throw new Error(j.error || 'Request failed'); return j; }));
  api('designer/graph-records').then((j) => {
    const s = $('#dg-record'); s.textContent = ''; s.append(Object.assign(el('option', '', '(sample values)'), { value: '' }));
    j.records.forEach((r) => s.append(Object.assign(el('option', '', `${r.LabRefNo}  ${r.ShoeName || ''}`), { value: r.LabRefNo })));
  }).catch(() => {});
  $('#dg-record').addEventListener('change', (e) => {
    if (!e.target.value) { data = sampleData(); redraw(); return; }
    say('Loading ' + e.target.value + '...');
    api('dtr/bonding-graph-data?lab_ref=' + encodeURIComponent(e.target.value)).then((j) => { data = j.data; say(''); redraw(); }).catch((x) => say(x.message, true));
  });
  api('dtr/bonding-graph-layout').then((j) => { if (j.layout && j.layout.items) layout = j.layout; if (!layout.series || !layout.series.length) layout.series = G.defaultLayout().series; init(); }).catch(() => init());
  function init() { drawCols(); drawSeries(); fit(); }
  $('#dg-fit').addEventListener('click', fit);
  window.addEventListener('resize', () => { if (!pane.hidden) fit(); });
  document.querySelectorAll('.dsg-tab').forEach((b) => b.addEventListener('click', () => { if (b.dataset.tab === 'graph') setTimeout(fit, 30); }));
  $('#dg-save').addEventListener('click', () => {
    say('Saving...');
    api('designer/graph-layout/save', { layout }).then(() => say('Saved. Generate Report now draws this page.')).catch((e) => say('Save failed: ' + e.message, true));
  });
  $('#dg-reset').addEventListener('click', () => {
    if (!window.confirm('Go back to the built-in page? Your saved layout is removed.')) return;
    api('designer/graph-layout/save', { reset: true }).then(() => { layout = G.defaultLayout(); sel = null; init(); props(); say('Back to the default layout.'); }).catch((e) => say(e.message, true));
  });
  $('#dg-png').addEventListener('click', () => { const a = document.createElement('a'); a.href = canvas.toDataURL('image/png'); a.download = 'Bonding Test-VF preview.png'; document.body.append(a); a.click(); a.remove(); });
})();
