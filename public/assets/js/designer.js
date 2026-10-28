/**
 * NEXUS-L Report Designer (admin).
 * Positions of the UTF/FTO measuring points on the shoe sketch, extra report items, and the Bonding form field setup.
 * Layout JSON is stored by DesignerController (tbl_report_layouts) and applied by dtr.js in the report / form.
 */
(() => {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const baseMeta = $('meta[name="base-url"]');
  const BASE = baseMeta ? baseMeta.content.replace(/\/+$/, '') + '/' : location.origin + '/';
  const csrfMeta = $('meta[name="X-CSRF-TOKEN"]');

  const api = (path, opts = {}) => fetch(BASE + path, opts).then((r) => r.json().then((j) => {
    if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
    if (!r.ok || j.ok === false) throw new Error(j.error || 'Request failed');
    return j;
  }));
  const post = (path, body) => api(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' },
    body: JSON.stringify(body),
  });
  const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const r2 = (n) => Math.round(Number(n) * 100) / 100;
  const clamp = (n, a, b) => Math.min(b, Math.max(a, n));

  // ------------------------------------------------------------------ tabs
  $$('.dsg-tab').forEach((b) => b.addEventListener('click', () => {
    $$('.dsg-tab').forEach((x) => x.classList.toggle('active', x === b));
    $('#dsg-pane-diagram').hidden = b.dataset.tab !== 'diagram';
    $('#dsg-pane-form').hidden = b.dataset.tab !== 'form';
    $('#dsg-pane-excel').hidden = b.dataset.tab !== 'excel';
    $('#dsg-pane-locations').hidden = b.dataset.tab !== 'locations';
    $('#dsg-pane-signatories').hidden = b.dataset.tab !== 'signatories';
    if (b.dataset.tab === 'excel' && !xLoaded) loadMaps();
  }));

  // =================================================================== DIAGRAM
  const stage = $('#dsg-stage');
  let sketchCfgs = null;
  let layout = null;      // {image, utf:{n:box}, fto:{n:box}, extras:[]}
  let selected = null;    // 'utf:3' | 'fto:3' | 'img' | 'x:<id>'
  let sample = {};        // values of the previewed record (UTFpointN, FTOpointN, custom fields ...)
  let recordFields = {};

  const variant = () => $('#dsg-variant').value;
  const side = () => $('#dsg-side').value;
  const blankBox = () => ({ pctLeft: 40, pctTop: 40, pctWidth: 10, pctHeight: 4 });

  const loadSketchCfgs = async () => {
    if (sketchCfgs) return sketchCfgs;
    sketchCfgs = await (await fetch(BASE + 'assets/images/bonding_sketches/sketch_configs.json')).json();
    return sketchCfgs;
  };

  const defaultLayout = (n) => {
    const c = sketchCfgs[n];
    const pick = (b) => ({ pctLeft: b.pctLeft, pctTop: b.pctTop, pctWidth: b.pctWidth, pctHeight: b.pctHeight });
    const L = { image: pick(c.image), imageFile: c.image.file, utf: {}, fto: {}, extras: [] };
    Object.keys(c.utf).forEach((k) => { L.utf[k] = pick(c.utf[k]); });
    Object.keys(c.fto).forEach((k) => { L.fto[k] = pick(c.fto[k]); });
    return L;
  };

  // Starting spots (% of the report) of the outer (UTF) and inner (FTO) dots of the 27-point sketch; other
  // variants reuse them spread over their own point count, then the admin drags each dot onto the shoe.
  const DOTS27 = {
    utf: [[201,638],[187,580],[188,529],[186,477],[175,430],[158,378],[153,330],[156,279],[169,218],[188,171],[240,123],[318,105],[396,123],[450,170],[470,217],[483,277],[487,328],[481,380],[464,431],[452,482],[449,531],[452,580],[452,643],[427,706],[357,747],[271,743],[209,700]],
    fto: [[201,638],[202,579],[205,527],[203,474],[192,426],[176,376],[171,331],[174,282],[184,222],[206,180],[248,139],[318,123],[389,139],[434,181],[455,223],[467,279],[471,328],[465,379],[449,429],[437,482],[433,534],[437,582],[437,642],[418,692],[351,732],[276,728],[222,687]],
  };
  const seedDots = (n) => {
    const d = { utf: {}, fto: {} };
    ['utf', 'fto'].forEach((k) => {
      for (let i = 1; i <= n; i++) {
        const src = DOTS27[k][Math.min(26, Math.round((i - 1) * 26 / Math.max(1, n - 1)))];
        d[k][i] = { x: r2(src[0] / 641 * 100), y: r2(src[1] / 798 * 100) };
      }
    });
    return d;
  };
  const dotBox = (k, i) => {
    const d = layout.dots[k][i];
    return {
      get pctLeft() { return d.x; }, set pctLeft(v) { d.x = v; },
      get pctTop() { return d.y; }, set pctTop(v) { d.y = v; },
      pctWidth: 0, pctHeight: 0,
    };
  };
  const isPt = (key) => key.startsWith('dot') || key === 'start';
  const startBox = () => {
    const d = layout.start;
    return {
      get pctLeft() { return d.x; }, set pctLeft(v) { d.x = v; },
      get pctTop() { return d.y; }, set pctTop(v) { d.y = v; },
      get label() { return d.label; }, set label(v) { d.label = v; },
      pctWidth: 0, pctHeight: 0,
    };
  };
  // leader line from a value box to its dot (box edge nearest to the dot)
  const lineSvg = () => {
    let s = '<svg class="dsg-lines" viewBox="0 0 100 100" preserveAspectRatio="none">';
    ['utf', 'fto'].forEach((k) => Object.keys(layout[k]).forEach((i) => {
      const b = layout[k][i]; const d = (layout.dots[k] || {})[i];
      if (!d || b.hidden) return;
      const r = b.pctLeft + b.pctWidth; const cy = b.pctTop + b.pctHeight / 2;
      let x = d.x < b.pctLeft ? b.pctLeft : d.x > r ? r : b.pctLeft + b.pctWidth / 2;
      let y = x === b.pctLeft || x === r ? cy : (d.y < b.pctTop ? b.pctTop : b.pctTop + b.pctHeight);
      s += `<line x1="${x}" y1="${y}" x2="${d.x}" y2="${d.y}" stroke="${k === 'utf' ? '#2563eb' : '#7c3aed'}" stroke-width="1.2" vector-effect="non-scaling-stroke"/>`;
    }));
    return s + '</svg>';
  };
  const refreshLines = () => { const o = stage.querySelector('.dsg-lines'); if (o) o.outerHTML = lineSvg(); };

  const loadVariant = async () => {
    await loadSketchCfgs();
    const n = variant();
    const base = defaultLayout(n);
    let saved = null;
    try { saved = (await api('dtr/layout?slug=bonding&variant=' + encodeURIComponent(n))).layout; } catch (e) { /* none saved */ }
    layout = base;
    if (saved) {
      if (saved.image) layout.image = saved.image;
      ['utf', 'fto'].forEach((k) => Object.keys(saved[k] || {}).forEach((i) => { layout[k][i] = saved[k][i]; }));
      layout.extras = saved.extras || [];
      if (saved.start) layout.start = saved.start;
      if (saved.vector) { layout.vector = true; layout.dots = saved.dots || seedDots(Number(n)); layout.imageFile = saved.imageFile || 'blank.png'; }
    }
    $('#dsg-vector').checked = !!layout.vector;
    selected = null;
    $('#dsg-status').textContent = saved ? 'Showing the saved layout.' : 'Showing the built-in default (not saved yet).';
    render();
    renderProps();
  };

  const colorFor = (v, std) => {
    const x = parseFloat(v);
    if (!Number.isFinite(x) || x <= 0) return '#94a3b8';
    const lo = std >= 3 ? 2 : std >= 2.5 ? 1.8 : 1.5;
    const mid = std >= 3 ? 3 : std >= 2.5 ? 2.5 : 2;
    return x < lo ? '#dc2626' : x < mid ? '#d97706' : '#1d4ed8';
  };

  const sampleVal = (kind, i) => {
    const key = (kind === 'utf' ? 'UTFpoint' : 'FTOpoint') + i;
    const v = sample[key];
    if (v !== undefined && v !== null && v !== '') return Number(v).toFixed(2);
    return Object.keys(sample).length ? '' : (3 + ((i * 37) % 30) / 10).toFixed(2); // demo number when no record is chosen
  };

  const place = (el, b) => {
    el.style.left = b.pctLeft + '%'; el.style.top = b.pctTop + '%';
    el.style.width = b.pctWidth + '%'; el.style.height = b.pctHeight + '%';
  };

  const extraHtml = (e) => {
    if (e.type === 'text') return esc(e.text || 'Text');
    if (e.type === 'field') return esc(recordFields[e.field] ?? (e.field ? '{' + e.field + '}' : '{field}'));
    if (e.type === 'image') return e.src ? `<img src="${BASE}${esc(e.src)}" alt="">` : '(image)';
    if (e.type === 'line') return '';
    if (e.type === 'chart') {
      const n = Number(variant());
      let bars = '';
      let max = 0;
      for (let i = 1; i <= n; i++) max = Math.max(max, parseFloat(sampleVal('utf', i)) || 0);
      for (let i = 1; i <= n; i++) {
        const v = parseFloat(sampleVal('utf', i)) || 0;
        bars += `<i style="height:${max ? (v / max) * 100 : 0}%;background:${colorFor(v, 3)}"></i>`;
      }
      return `<div class="dsg-chart">${bars}</div>`;
    }
    return '';
  };

  const render = () => {
    if (!layout) return;
    const c = sketchCfgs[variant()];
    stage.style.aspectRatio = c.rptWidth + ' / ' + c.rptHeight;
    stage.innerHTML = '';
    const fs = Math.max(8, stage.clientWidth * 0.019);

    const add = (key, cls, box, html, extra = {}) => {
      const el = document.createElement('div');
      el.className = 'dsg-item ' + cls + (selected === key ? ' sel' : '') + (extra.hidden ? ' hid' : '');
      el.dataset.key = key;
      place(el, box);
      if (extra.style) Object.assign(el.style, extra.style);
      el.innerHTML = html + '<b class="dsg-grip"></b>';
      stage.appendChild(el);
    };

    // sketch image (movable too)
    const imgFile = layout.imageFile || c.image.file;
    add('img', 'dsg-img', layout.image, `<img src="${BASE}assets/images/bonding_sketches/${esc(imgFile)}" alt="" draggable="false">`);
    const n = Number(variant());
    if (layout.vector) {
      stage.insertAdjacentHTML('beforeend', lineSvg());
      ['utf', 'fto'].forEach((k) => {
        for (let i = 1; i <= n; i++) {
          const d = layout.dots[k][i];
          if (!d || (layout[k][i] || {}).hidden) continue;
          const el = document.createElement('div');
          el.className = 'dsg-item dsg-dot dsg-dot-' + k + (selected === `dot${k}:${i}` ? ' sel' : '');
          el.dataset.key = `dot${k}:${i}`;
          el.title = `${k.toUpperCase()} point ${i} dot`;
          el.style.left = d.x + '%'; el.style.top = d.y + '%';
          stage.appendChild(el);
        }
      });
    }
    if (layout.start) {
      const el = document.createElement('div');
      el.className = 'dsg-item dsg-start' + (selected === 'start' ? ' sel' : '');
      el.dataset.key = 'start';
      el.style.left = layout.start.x + '%'; el.style.top = layout.start.y + '%';
      el.innerHTML = '<i></i><span>' + esc(layout.start.label || 'START') + '</span>';
      stage.appendChild(el);
    }
    ['utf', 'fto'].forEach((k) => {
      for (let i = 1; i <= n; i++) {
        const b = layout[k][i];
        if (!b) continue;
        const v = sampleVal(k, i);
        add(`${k}:${i}`, 'dsg-pt dsg-' + k, b, `<span class="n">${k === 'utf' ? '' : 'F'}${i}</span><span class="v" style="color:${colorFor(v, 3)};font-size:${fs}px">${esc(v)}</span>`, { hidden: b.hidden });
      }
    });
    (layout.extras || []).forEach((e) => {
      add('x:' + e.id, 'dsg-x dsg-x-' + e.type, e, extraHtml(e), {
        style: { color: e.color || '#111', fontSize: (e.size || 12) * (stage.clientWidth / 900) + 'px', fontWeight: e.bold ? '700' : '400', background: e.type === 'line' ? (e.color || '#111') : '' },
      });
    });
  };

  const boxOf = (key) => {
    if (key === 'img') return layout.image;
    if (key.startsWith('x:')) return layout.extras.find((e) => 'x:' + e.id === key);
    if (key === 'start') return layout.start ? startBox() : null;
    if (key.startsWith('dot')) { const [k, i] = key.slice(3).split(':'); return dotBox(k, i); }
    const [k, i] = key.split(':');
    return layout[k][i];
  };

  // ---------------------------------------------------------- drag / resize
  let drag = null;
  stage.addEventListener('pointerdown', (ev) => {
    const el = ev.target.closest('.dsg-item');
    if (!el) { selected = null; render(); renderProps(); return; }
    selected = el.dataset.key;
    const b = boxOf(selected);
    drag = { key: selected, resize: ev.target.classList.contains('dsg-grip'), x: ev.clientX, y: ev.clientY, b: { ...b } };
    stage.setPointerCapture(ev.pointerId);
    stage.focus();
    $$('.dsg-item', stage).forEach((n) => n.classList.toggle('sel', n.dataset.key === selected));
    renderProps();
    ev.preventDefault();
  });
  stage.addEventListener('pointermove', (ev) => {
    if (!drag) return;
    const b = boxOf(drag.key);
    const dx = (ev.clientX - drag.x) / stage.clientWidth * 100;
    const dy = (ev.clientY - drag.y) / stage.clientHeight * 100;
    if (drag.resize) {
      b.pctWidth = r2(clamp(drag.b.pctWidth + dx, 1, 100));
      b.pctHeight = r2(clamp(drag.b.pctHeight + dy, 0.3, 100));
    } else {
      b.pctLeft = r2(drag.b.pctLeft + dx);
      b.pctTop = r2(drag.b.pctTop + dy);
    }
    const el = stage.querySelector(`[data-key="${CSS.escape(drag.key)}"]`);
    if (el) { if (isPt(drag.key)) { el.style.left = b.pctLeft + '%'; el.style.top = b.pctTop + '%'; } else place(el, b); }
    refreshLines();
    syncInputs();
  });
  const endDrag = () => { drag = null; };
  stage.addEventListener('pointerup', endDrag);
  stage.addEventListener('pointercancel', endDrag);

  stage.addEventListener('keydown', (ev) => {
    if (!selected) return;
    const step = ev.shiftKey ? 0.5 : 0.1;
    const b = boxOf(selected);
    const map = { ArrowLeft: ['pctLeft', -step], ArrowRight: ['pctLeft', step], ArrowUp: ['pctTop', -step], ArrowDown: ['pctTop', step] };
    if (ev.key === 'Delete' && selected.startsWith('x:')) { removeSelected(); ev.preventDefault(); return; }
    if (!map[ev.key]) return;
    b[map[ev.key][0]] = r2(b[map[ev.key][0]] + map[ev.key][1]);
    const el = stage.querySelector(`[data-key="${CSS.escape(selected)}"]`);
    if (el) { if (isPt(selected)) { el.style.left = b.pctLeft + '%'; el.style.top = b.pctTop + '%'; } else place(el, b); }
    refreshLines();
    syncInputs();
    ev.preventDefault();
  });

  // ------------------------------------------------------------ properties
  const numInput = (label, id, v) => `<label>${label}<input type="number" step="0.1" data-prop="${id}" value="${r2(v)}"></label>`;

  const renderProps = () => {
    const host = $('#dsg-props');
    if (!selected || !layout) { host.innerHTML = '<p class="muted sm">Click an item on the diagram.</p>'; return; }
    const b = boxOf(selected);
    if (!b) { host.innerHTML = ''; return; }
    let html = '<div class="dsg-grid2">' + numInput('Left %', 'pctLeft', b.pctLeft) + numInput('Top %', 'pctTop', b.pctTop) + numInput('Width %', 'pctWidth', b.pctWidth) + numInput('Height %', 'pctHeight', b.pctHeight) + '</div>';

    if (selected === 'start') {
      html = '<p><b>Start point</b></p><label>Label<input type="text" data-prop="label" value="' + esc(b.label || 'START') + '" maxlength="30"></label><div class="dsg-grid2">' + numInput('Left %', 'pctLeft', b.pctLeft) + numInput('Top %', 'pctTop', b.pctTop) + '</div><p class="muted sm">Place it where the test starts, or is expected to start.</p><button type="button" class="dsg-btn dsg-danger" id="dsg-del-start">Remove start marker</button>';
    } else if (isPt(selected)) {
      html = `<p><b>Dot of ${selected.slice(3, 6).toUpperCase()} point ${selected.split(':')[1]}</b></p><div class="dsg-grid2">` + numInput('Left %', 'pctLeft', b.pctLeft) + numInput('Top %', 'pctTop', b.pctTop) + '</div><p class="muted sm">Drag it onto the exact spot of the shoe. Its line follows.</p>';
    } else if (/^(utf|fto):/.test(selected)) {
      const [k, i] = selected.split(':');
      html = `<p><b>${k.toUpperCase()} point ${i}</b></p>` + html +
        `<label class="dsg-chk"><input type="checkbox" data-prop="hidden" ${b.hidden ? 'checked' : ''}> Hide this point in the report</label>` +
        `<label>Swap place with point # <input type="number" min="1" max="${variant()}" id="dsg-swap" placeholder="e.g. 5"></label>` +
        '<button type="button" class="dsg-btn" id="dsg-swap-go">Swap</button>';
    } else if (selected === 'img') {
      html = '<p><b>Shoe sketch image</b></p>' + html;
    } else {
      const e = b;
      html = `<p><b>${esc(e.type)}</b></p>` + html;
      if (e.type === 'text') html += `<label>Text<textarea data-prop="text" rows="2">${esc(e.text)}</textarea></label>`;
      if (e.type === 'field') {
        const opts = [...(window.DSG_FIELDS || []).map((f) => [f.name, f.label]), ...customList().map((c) => [c.key, c.label + ' (custom)'])];
        html += '<label>Record value<select data-prop="field"><option value="">-- choose --</option>' + opts.map(([v, l]) => `<option value="${esc(v)}" ${e.field === v ? 'selected' : ''}>${esc(l)}</option>`).join('') + '</select></label>';
      }
      if (e.type === 'image') html += `<label>Image path (under assets/)<input type="text" data-prop="src" value="${esc(e.src)}" placeholder="assets/images/bonding_sketches/L_shoe.png"></label>`;
      if (e.type !== 'image' && e.type !== 'chart') {
        html += `<label>Size (pt)<input type="number" data-prop="size" value="${e.size || 12}"></label>` +
          `<label>Colour<input type="color" data-prop="color" value="${esc(e.color || '#111111')}"></label>` +
          `<label class="dsg-chk"><input type="checkbox" data-prop="bold" ${e.bold ? 'checked' : ''}> Bold</label>`;
      }
      html += '<button type="button" class="dsg-btn dsg-danger" id="dsg-del">Delete item</button>';
    }
    host.innerHTML = html;
  };

  const syncInputs = () => {
    if (!selected) return;
    const b = boxOf(selected);
    if (!b) return;
    $$('#dsg-props [data-prop]').forEach((inp) => {
      const p = inp.dataset.prop;
      if (['pctLeft', 'pctTop', 'pctWidth', 'pctHeight'].includes(p) && document.activeElement !== inp) inp.value = r2(b[p]);
    });
  };

  $('#dsg-props').addEventListener('input', (ev) => {
    const p = ev.target.dataset.prop;
    if (!p || !selected) return;
    const b = boxOf(selected);
    if (!b) return;
    if (ev.target.type === 'checkbox') b[p] = ev.target.checked;
    else if (ev.target.type === 'number') b[p] = Number(ev.target.value) || 0;
    else b[p] = ev.target.value;
    render();
  });
  $('#dsg-props').addEventListener('click', (ev) => {
    if (ev.target.id === 'dsg-del') removeSelected();
    if (ev.target.id === 'dsg-del-start') { layout.start = null; selected = null; render(); renderProps(); }
    if (ev.target.id === 'dsg-swap-go') {
      const m = $('#dsg-swap').value.trim();
      const [k, i] = selected.split(':');
      if (!m || !layout[k][m] || m === i) return;
      [layout[k][i], layout[k][m]] = [layout[k][m], layout[k][i]];
      selected = `${k}:${m}`;
      render(); renderProps();
    }
  });

  const removeSelected = () => {
    if (!selected || !selected.startsWith('x:')) return;
    layout.extras = layout.extras.filter((e) => 'x:' + e.id !== selected);
    selected = null; render(); renderProps();
  };

  // ------------------------------------------------------------- add items
  const addExtra = (type, over = {}) => {
    const e = Object.assign({ id: 'x' + Date.now().toString(36), type, pctLeft: 30, pctTop: 30, pctWidth: 20, pctHeight: 4, text: '', field: '', size: 12, bold: false, color: '#111111', src: '' }, over);
    layout.extras.push(e);
    selected = 'x:' + e.id;
    render(); renderProps();
  };
  $('#dsg-add-text').onclick = () => addExtra('text', { text: 'New text' });
  $('#dsg-add-field').onclick = () => addExtra('field', { pctWidth: 24 });
  $('#dsg-add-chart').onclick = () => addExtra('chart', { pctWidth: 30, pctHeight: 10 });
  $('#dsg-add-image').onclick = () => addExtra('image', { pctWidth: 12, pctHeight: 8, src: 'assets/images/bonding_sketches/L_shoe.png' });
  $('#dsg-add-line').onclick = () => addExtra('line', { pctWidth: 20, pctHeight: 0.3 });

  $('#dsg-move-all').onclick = () => {
    const dx = Number($('#dsg-dx').value) || 0;
    const dy = Number($('#dsg-dy').value) || 0;
    ['utf', 'fto'].forEach((k) => Object.values(layout[k]).forEach((b) => { b.pctLeft = r2(b.pctLeft + dx); b.pctTop = r2(b.pctTop + dy); }));
    $('#dsg-dx').value = 0; $('#dsg-dy').value = 0;
    render(); renderProps();
  };
  $('#dsg-show-all').onclick = () => {
    ['utf', 'fto'].forEach((k) => Object.values(layout[k]).forEach((b) => { b.hidden = false; }));
    render(); renderProps();
  };

  $('#dsg-save').onclick = async () => {
    try {
      await post('designer/save', { slug: 'bonding', variant: variant(), layout });
      $('#dsg-status').textContent = 'Saved ✔ ' + new Date().toLocaleTimeString() + ' - users see it the next time they open the report.';
    } catch (e) { $('#dsg-status').textContent = 'Save failed: ' + e.message; }
  };
  $('#dsg-reset').onclick = async () => {
    if (!(await nxConfirm('Reset the ' + variant() + '-point layout to the built-in default?', { okText: 'Reset' }))) return;
    try { await post('designer/reset', { slug: 'bonding', variant: variant() }); await loadVariant(); } catch (e) { $('#dsg-status').textContent = 'Reset failed: ' + e.message; }
  };

  $('#dsg-add-start').onclick = () => {
    const d = layout.dots && layout.dots.utf[1];
    layout.start = layout.start || { x: d ? d.x : 30, y: d ? d.y + 4 : 70, label: 'START' };
    selected = 'start'; render(); renderProps();
  };
  $('#dsg-vector').addEventListener('change', (ev) => {
    if (ev.target.checked) {
      layout.vector = true;
      layout.dots = layout.dots || seedDots(Number(variant()));
      layout.origImage = layout.imageFile;
      layout.imageFile = 'blank.png';
    } else {
      layout.vector = false;
      layout.imageFile = layout.origImage || sketchCfgs[variant()].image.file;
    }
    selected = null; render(); renderProps();
  });
  $('#dsg-variant').addEventListener('change', loadVariant);
  $('#dsg-side').addEventListener('change', render);
  window.addEventListener('resize', () => render());

  // ------------------------------------------------- preview record picker
  const loadRecords = async () => {
    try {
      const j = await api('dtr/records?slug=bonding');
      const sel = $('#dsg-record');
      (j.rows || []).slice(0, 100).forEach((r) => {
        const o = document.createElement('option');
        o.value = r.source_id;
        o.textContent = `${r.lab_ref_no || r.source_id} - ${r.model_style || ''}`;
        sel.appendChild(o);
      });
    } catch (e) { /* preview still works with demo values */ }
  };
  $('#dsg-record').addEventListener('change', async (ev) => {
    sample = {}; recordFields = {};
    if (ev.target.value) {
      try {
        const j = await api('dtr/record?slug=bonding&source_id=' + encodeURIComponent(ev.target.value));
        sample = j.bondingPoints || {};
        recordFields = j.record || {};
        const mp = parseInt((j.bondingPoints || {}).MaxPoints || 0, 10);
        if (mp >= 26 && mp <= 34 && String(mp) !== variant()) { $('#dsg-variant').value = String(mp); await loadVariant(); return; }
      } catch (e) { /* ignore */ }
    }
    render();
  });

  // ==================================================================== FORM
  let formCfg = { hiddenFields: [], labels: {}, order: [], customFields: [] };
  const fields = () => window.DSG_FIELDS || [];
  const customList = () => formCfg.customFields || [];

  const orderedFields = () => {
    const names = fields().map((f) => f.name);
    const ord = (formCfg.order || []).filter((n) => names.includes(n));
    names.forEach((n) => { if (!ord.includes(n)) ord.push(n); });
    return ord;
  };

  const renderForm = () => {
    const byName = Object.fromEntries(fields().map((f) => [f.name, f]));
    $('#dsg-fields').innerHTML = orderedFields().map((n, idx) => {
      const hidden = (formCfg.hiddenFields || []).includes(n);
      const label = (formCfg.labels || {})[n] ?? byName[n].label;
      return `<li data-name="${esc(n)}"><label class="dsg-chk"><input type="checkbox" data-act="vis" ${hidden ? '' : 'checked'}> visible</label>` +
        `<input type="text" data-act="label" value="${esc(label)}"><code>${esc(n)}</code>` +
        `<button type="button" class="dsg-mini" data-act="up" ${idx === 0 ? 'disabled' : ''}>▲</button><button type="button" class="dsg-mini" data-act="down">▼</button></li>`;
    }).join('');
    $('#dsg-custom').innerHTML = customList().map((c) =>
      `<li data-key="${esc(c.key)}"><input type="text" data-act="clabel" value="${esc(c.label)}"><code>${esc(c.key)}</code><button type="button" class="dsg-mini dsg-danger" data-act="cdel">✕</button></li>`).join('') ||
      '<li class="muted sm">No custom fields yet.</li>';
  };

  $('#dsg-fields').addEventListener('click', (ev) => {
    const li = ev.target.closest('li'); const act = ev.target.dataset.act;
    if (!li || !act) return;
    const n = li.dataset.name;
    if (act === 'up' || act === 'down') {
      const ord = orderedFields(); const i = ord.indexOf(n); const j = act === 'up' ? i - 1 : i + 1;
      if (j < 0 || j >= ord.length) return;
      [ord[i], ord[j]] = [ord[j], ord[i]]; formCfg.order = ord; renderForm();
    }
  });
  $('#dsg-fields').addEventListener('input', (ev) => {
    const li = ev.target.closest('li'); const act = ev.target.dataset.act;
    if (!li) return;
    const n = li.dataset.name;
    if (act === 'vis') {
      const h = new Set(formCfg.hiddenFields || []);
      if (ev.target.checked) h.delete(n); else h.add(n);
      formCfg.hiddenFields = [...h];
    }
    if (act === 'label') { formCfg.labels = formCfg.labels || {}; formCfg.labels[n] = ev.target.value; }
  });
  $('#dsg-custom').addEventListener('input', (ev) => {
    const li = ev.target.closest('li[data-key]');
    if (li && ev.target.dataset.act === 'clabel') { const c = customList().find((x) => x.key === li.dataset.key); if (c) c.label = ev.target.value; }
  });
  $('#dsg-custom').addEventListener('click', (ev) => {
    const li = ev.target.closest('li[data-key]');
    if (li && ev.target.dataset.act === 'cdel') { formCfg.customFields = customList().filter((x) => x.key !== li.dataset.key); renderForm(); }
  });
  $('#dsg-custom-add').onclick = () => {
    const label = $('#dsg-custom-label').value.trim();
    if (!label) return;
    let key = 'cf_' + label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40);
    if (key === 'cf_') key = 'cf_' + Date.now().toString(36);
    if (customList().some((c) => c.key === key)) key += '_' + Date.now().toString(36).slice(-3);
    formCfg.customFields = [...customList(), { key, label }];
    $('#dsg-custom-label').value = '';
    renderForm();
  };
  $('#dsg-form-save').onclick = async () => {
    try {
      await post('designer/save', { slug: 'bonding', variant: 'form', layout: formCfg });
      $('#dsg-form-status').textContent = 'Saved ✔ ' + new Date().toLocaleTimeString();
    } catch (e) { $('#dsg-form-status').textContent = 'Save failed: ' + e.message; }
  };
  $('#dsg-form-reset').onclick = async () => {
    if (!(await nxConfirm('Show all fields, original labels and order, and remove the custom fields?', { okText: 'Reset' }))) return;
    try { await post('designer/reset', { slug: 'bonding', variant: 'form' }); formCfg = { hiddenFields: [], labels: {}, order: [], customFields: [] }; renderForm(); $('#dsg-form-status').textContent = 'Reset.'; } catch (e) { $('#dsg-form-status').textContent = 'Reset failed: ' + e.message; }
  };

  // ============================================================== EXCEL READERS
  let xMaps = [];
  let xLoaded = false;
  let xActive = null; // {m: mapIndex, r: ruleIndex}
  let xGridMap = -1;

  const RULE_TYPES = {
    row_by_labref: 'Row of the Lab Ref (header row has the Lab Ref)',
    column_range: 'Column of values (spots 1..n)',
    cell: 'One fixed cell',
    anchor_cell: 'Cell next to a label text',
    label_rows: 'Rows labelled M1, M2… (one value per column)',
    point_grid: 'Cells labelled point1, point2… (csv layout)',
    header_value: 'Value under a column header',
  };
  const newRule = (type) => ({
    row_by_labref: { type, labRow: 7, resultRow: 9, startCol: 3, fields: [], fallbackFirstCol: false },
    column_range: { type, col: 3, rowFrom: 12, rowTo: 46, pattern: 'UTFpoint{n}', maxN: 0, stats: '' },
    cell: { type, row: 1, col: 1, field: '' },
    anchor_cell: { type, anchor: 'Lab Ref', rowOffset: 0, colOffset: 1, field: '' },
    label_rows: { type, labelCol: 1, labelRegex: '/^M(\\d+)$/i', cols: [{ col: 3, pattern: 'UTFpoint{n}', maxN: 45, stats: 'UTF' }] },
    point_grid: { type, reads: [{ rowOffset: 2, pattern: 'UTFpoint{n}', maxN: 45, stats: 'UTF' }] },
    header_value: { type, header: 'Test date', field: 'TestedDate', onlyIfMissing: true },
  }[type]);

  const jsonBox = (m, r, k, label, rows = 4) => `<label class="dsg-xl wide">${label}<textarea rows="${rows}" spellcheck="false" data-x="${m}.${r}.${k}" style="font-family:ui-monospace,monospace;font-size:12px">${esc(JSON.stringify(xMaps[m].rules[r][k], null, 1))}</textarea></label>`;

  const num = (m, r, k, label, w = 70) => `<label class="dsg-xl">${label}<input type="number" style="width:${w}px" data-x="${m}.${r}.${k}" value="${esc(xMaps[m].rules[r][k])}"></label>`;
  const txt = (m, r, k, label, w = 150) => `<label class="dsg-xl">${label}<input type="text" style="width:${w}px" data-x="${m}.${r}.${k}" value="${esc(xMaps[m].rules[r][k])}"></label>`;

  const ruleHtml = (mi, ri) => {
    const r = xMaps[mi].rules[ri];
    const act = xActive && xActive.m === mi && xActive.r === ri ? ' act' : '';
    let body = '';
    if (r.type === 'row_by_labref') {
      body = num(mi, ri, 'labRow', 'Lab Ref row') + num(mi, ri, 'resultRow', 'Result row') + num(mi, ri, 'startCol', 'Start col') +
        `<label class="dsg-xl wide">Fields, one per column (comma separated)<input type="text" data-x="${mi}.${ri}.fields" value="${esc((r.fields || []).join(', '))}"></label>` +
        `<label class="dsg-chk dsg-xl"><input type="checkbox" data-x="${mi}.${ri}.fallbackFirstCol" ${r.fallbackFirstCol ? 'checked' : ''}> use first column if the Lab Ref is not in the header</label>`;
    } else if (r.type === 'column_range') {
      body = num(mi, ri, 'col', 'Column') + num(mi, ri, 'rowFrom', 'From row') + num(mi, ri, 'rowTo', 'To row') + num(mi, ri, 'maxN', 'Max count (0 = all)') +
        txt(mi, ri, 'pattern', 'Field name, {n} = 1,2,3…', 170) + txt(mi, ri, 'stats', 'Min/Max/Average prefix', 110);
    } else if (r.type === 'cell') {
      body = num(mi, ri, 'row', 'Row') + num(mi, ri, 'col', 'Column') + txt(mi, ri, 'field', 'Field name') +
        `<label class="dsg-chk dsg-xl"><input type="checkbox" data-x="${mi}.${ri}.afterColon" ${r.afterColon ? 'checked' : ''}> keep only the text after “:”</label>` +
        (r.altCol ? num(mi, ri, 'altCol', 'Use this column instead when the condition matches') + jsonBox(mi, ri, 'altWhen', 'Condition {field, in:[values]}', 3) : '');
    } else if (r.type === 'label_rows') {
      body = num(mi, ri, 'labelCol', 'Label column') + txt(mi, ri, 'labelRegex', 'Label pattern (regex, first group = number)', 190) +
        jsonBox(mi, ri, 'cols', 'Columns: [{col, pattern, maxN, stats}]', 6);
    } else if (r.type === 'point_grid') {
      body = jsonBox(mi, ri, 'reads', 'Reads: [{rowOffset, pattern, maxN, stats}]', 6);
    } else if (r.type === 'header_value') {
      body = txt(mi, ri, 'header', 'Header text', 160) + txt(mi, ri, 'field', 'Field name') +
        `<label class="dsg-chk dsg-xl"><input type="checkbox" data-x="${mi}.${ri}.onlyIfMissing" ${r.onlyIfMissing ? 'checked' : ''}> only if not read already</label>`;
    } else {
      body = txt(mi, ri, 'anchor', 'Label text to find', 160) + num(mi, ri, 'rowOffset', 'Rows down') + num(mi, ri, 'colOffset', 'Cols right') + txt(mi, ri, 'field', 'Field name');
    }
    return `<div class="dsg-xrule${act}" data-m="${mi}" data-r="${ri}"><div class="dsg-xrh"><b>${esc(RULE_TYPES[r.type])}</b><button type="button" class="dsg-mini dsg-danger" data-xdel="${mi}.${ri}">✕</button></div><div class="dsg-xrb">${body}</div></div>`;
  };

  const renderMaps = () => {
    $('#dsg-x-maps').innerHTML = xMaps.map((m, mi) => `
      <div class="card glass dsg-xmap">
        <div class="dsg-xmh">
          <label class="dsg-chk"><input type="checkbox" data-xm="${mi}.enabled" ${m.enabled ? 'checked' : ''}> on</label>
          <input type="text" data-xm="${mi}.label" value="${esc(m.label)}" placeholder="Name">
          <label class="dsg-xl">OUTDATA folder<input type="text" data-xm="${mi}.folder" value="${esc(m.folder)}"></label>
          <label class="dsg-xl">Sheet (name or number)<input type="text" style="width:110px" data-xm="${mi}.sheet" value="${esc(m.sheet)}" placeholder="first"></label>
          <button type="button" class="dsg-btn" data-xprev="${mi}">Preview file</button>
          <button type="button" class="dsg-btn" data-xtest="${mi}">Test</button>
          <button type="button" class="dsg-mini dsg-danger" data-xmdel="${mi}">Delete reader</button>
        </div>
        ${m.rules.map((_, ri) => ruleHtml(mi, ri)).join('') || '<p class="muted sm">No rules yet.</p>'}
        <div class="dsg-row">${Object.keys(RULE_TYPES).map((t) => `<button type="button" class="dsg-mini" data-xadd="${mi}.${t}">+ ${esc(RULE_TYPES[t])}</button>`).join('')}</div>
      </div>`).join('') || '<div class="card glass"><p class="muted sm">No reader for this report type yet. Add one.</p></div>';
  };

  const loadMaps = async () => {
    try {
      const j = await api('designer/maps?slug=' + encodeURIComponent($('#dsg-x-slug').value));
      xMaps = j.maps.map((m) => ({ label: m.label, folder: m.folder, sheet: m.sheet || '', enabled: m.enabled, rules: m.rules || [] }));
      xLoaded = true; xActive = null;
      renderMaps();
      $('#dsg-x-status').textContent = '';
    } catch (e) { $('#dsg-x-status').textContent = 'Could not load: ' + e.message; }
  };
  $('#dsg-x-slug').addEventListener('change', loadMaps);

  const setPath = (obj, k, el) => {
    if (el.type === 'checkbox') obj[k] = el.checked;
    else if (k === 'fields') obj[k] = el.value.split(',').map((x) => x.trim()).filter(Boolean);
    else if (el.tagName === 'TEXTAREA') { try { obj[k] = JSON.parse(el.value); el.style.outline = ''; } catch (e) { el.style.outline = '2px solid #dc2626'; } }
    else if (el.type === 'number') obj[k] = Number(el.value) || 0;
    else obj[k] = el.value;
  };
  $('#dsg-x-maps').addEventListener('input', (ev) => {
    const x = ev.target.dataset.x; const xm = ev.target.dataset.xm;
    if (x) { const [m, r, k] = x.split('.'); setPath(xMaps[m].rules[r], k, ev.target); }
    if (xm) { const [m, k] = xm.split('.'); setPath(xMaps[m], k, ev.target); }
  });
  $('#dsg-x-maps').addEventListener('focusin', (ev) => {
    const rule = ev.target.closest('.dsg-xrule');
    if (!rule) return;
    xActive = { m: Number(rule.dataset.m), r: Number(rule.dataset.r) };
    $$('.dsg-xrule').forEach((n) => n.classList.toggle('act', n === rule));
  });
  $('#dsg-x-maps').addEventListener('click', async (ev) => {
    const d = ev.target.dataset;
    if (d.xdel) { const [m, r] = d.xdel.split('.'); xMaps[m].rules.splice(r, 1); renderMaps(); }
    if (d.xmdel && await nxConfirm('Delete this reader?', { okText: 'Delete' })) { xMaps.splice(Number(d.xmdel), 1); renderMaps(); }
    if (d.xadd) { const [m, t] = d.xadd.split('.'); xMaps[m].rules.push(newRule(t)); xActive = { m: Number(m), r: xMaps[m].rules.length - 1 }; renderMaps(); }
    if (d.xprev !== undefined) previewGrid(Number(d.xprev));
    if (d.xtest !== undefined) testMap(Number(d.xtest));
  });
  $('#dsg-x-addmap').onclick = () => {
    xMaps.push({ label: 'New reader', folder: 'MATERIALS\\NEW', sheet: '', enabled: true, rules: [] });
    renderMaps();
  };

  const colName = (n) => { let s = ''; while (n > 0) { const m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); } return s; };

  const previewGrid = async (mi) => {
    const ref = $('#dsg-x-ref').value.trim();
    if (!ref) { $('#dsg-x-gridnote').textContent = 'Type a Lab Ref No. above first.'; return; }
    xGridMap = mi;
    const m = xMaps[mi];
    const sheet = $('#dsg-x-sheetsel').value || m.sheet || '';
    try {
      const j = await api(`designer/grid?folder=${encodeURIComponent(m.folder)}&lab_ref_no=${encodeURIComponent(ref)}&sheet=${encodeURIComponent(sheet)}`);
      $('#dsg-x-file').textContent = j.file;
      const sel = $('#dsg-x-sheetsel');
      sel.innerHTML = '<option value="">(first sheet)</option>' + (j.sheets || []).map((n, i) => `<option value="${esc(n)}" ${n === sheet ? 'selected' : ''}>${i + 1}. ${esc(n)}</option>`).join('');
      const rows = Object.keys(j.grid).map(Number).sort((a, b) => a - b);
      let maxCol = 0; rows.forEach((r) => Object.keys(j.grid[r]).forEach((c) => { maxCol = Math.max(maxCol, Number(c)); }));
      maxCol = Math.min(maxCol, 20);
      let h = '<table><thead><tr><th></th>' + Array.from({ length: maxCol }, (_, i) => `<th>${colName(i + 1)}<br><small>${i + 1}</small></th>`).join('') + '</tr></thead><tbody>';
      rows.forEach((r) => {
        h += `<tr><th>${r}</th>` + Array.from({ length: maxCol }, (_, i) => `<td data-r="${r}" data-c="${i + 1}">${esc(j.grid[r][i + 1] ?? '')}</td>`).join('') + '</tr>';
      });
      $('#dsg-x-grid').innerHTML = h + '</tbody></table>';
      $('#dsg-x-gridnote').textContent = 'Click a cell to fill the active rule (Shift = last row, Ctrl = Lab Ref row).';
    } catch (e) { $('#dsg-x-gridnote').textContent = e.message; $('#dsg-x-grid').innerHTML = ''; }
  };
  $('#dsg-x-sheetsel').addEventListener('change', () => { if (xGridMap >= 0) previewGrid(xGridMap); });

  $('#dsg-x-grid').addEventListener('click', (ev) => {
    const td = ev.target.closest('td');
    if (!td) return;
    if (!xActive) { $('#dsg-x-gridnote').textContent = 'Click inside a rule first, then click the cell.'; return; }
    const r = Number(td.dataset.r); const c = Number(td.dataset.c);
    const rule = xMaps[xActive.m].rules[xActive.r];
    if (!rule) return;
    if (rule.type === 'column_range') { rule.col = c; if (ev.shiftKey) rule.rowTo = r; else rule.rowFrom = r; }
    else if (rule.type === 'row_by_labref') { if (ev.ctrlKey) rule.labRow = r; else { rule.resultRow = r; rule.startCol = c; } }
    else if (rule.type === 'cell') { rule.row = r; rule.col = c; }
    else if (rule.type === 'anchor_cell') { rule.anchor = td.textContent.trim() || rule.anchor; }
    renderMaps();
  });

  const testMap = async (mi) => {
    const ref = $('#dsg-x-ref').value.trim();
    const box = $('#dsg-x-result');
    if (!ref) { box.textContent = 'Type a Lab Ref No. above first.'; return; }
    box.textContent = 'Testing…';
    try {
      const j = await post('designer/maps/test', { map: xMaps[mi], lab_ref_no: ref });
      const keys = Object.keys(j.fields);
      box.innerHTML = `<b>${esc(j.file)}</b> → ${keys.length} value(s)` + (keys.length ? '<table class="dsg-xt">' + keys.map((k) => `<tr><td>${esc(k)}</td><td>${esc(j.fields[k])}</td></tr>`).join('') + '</table>' : ' - nothing matched, check rows / columns.');
    } catch (e) { box.textContent = e.message; }
  };

  $('#dsg-x-save').onclick = async () => {
    try {
      await post('designer/maps/save', { slug: $('#dsg-x-slug').value, maps: xMaps });
      $('#dsg-x-status').textContent = 'Saved ✔ ' + new Date().toLocaleTimeString() + ' - "Load machine results" uses it immediately.';
    } catch (e) { $('#dsg-x-status').textContent = 'Save failed: ' + e.message; }
  };

  // ------------------------------------------------------------------ init
  const qs = new URLSearchParams(location.search);
  const startTab = qs.get('tab');

  // ------------------------------------------------------------ file locations
  const techRow = (code, name) => `<li><input type="text" class="tc" value="${esc(code)}" placeholder="DTP-QC-001" maxlength="30"><input type="text" class="tn" value="${esc(name)}" placeholder="Technician" maxlength="60"><button type="button" class="dsg-mini dsg-danger tdel">✕</button></li>`;
  let locDefault = '';
  const loadLoc = async () => {
    try {
      const j = await api('designer/settings');
      locDefault = j.default || '';
      $('#dsg-loc-root').value = j.root || '';
      $('#dsg-loc-techs').innerHTML = Object.entries(j.technicians || {}).map(([c, n]) => techRow(c, n)).join('');
    } catch (e) { $('#dsg-loc-status').textContent = e.message; }
  };
  const locTechs = () => { const o = {}; $$('#dsg-loc-techs li').forEach((li) => { const c = $('.tc', li).value.trim(); const n = $('.tn', li).value.trim(); if (c && n) o[c] = n; }); return o; };
  $('#dsg-loc-techs').addEventListener('click', (ev) => { if (ev.target.classList.contains('tdel')) ev.target.closest('li').remove(); });
  $('#dsg-loc-addtech').onclick = () => $('#dsg-loc-techs').insertAdjacentHTML('beforeend', techRow('', ''));
  $('#dsg-loc-default').onclick = () => { $('#dsg-loc-root').value = locDefault; };
  $('#dsg-loc-test').onclick = async () => {
    $('#dsg-loc-status').textContent = 'Testing...';
    try {
      const j = await post('designer/settings/test', { root: $('#dsg-loc-root').value });
      $('#dsg-loc-status').textContent = j.reachable ? 'Location reachable ✔' : 'Location NOT reachable ✖';
      $('#dsg-loc-result').innerHTML = '<table class="dsg-xt"><tr><th>Folder</th><th>Found</th><th>Files</th><th>Latest file</th></tr>' +
        j.folders.map((f) => `<tr><td>${esc(f.folder)}</td><td>${f.found ? '✔' : '✖'}</td><td>${f.files}</td><td>${esc(f.latest)}</td></tr>`).join('') + '</table>';
    } catch (e) { $('#dsg-loc-status').textContent = e.message; }
  };
  $('#dsg-loc-save').onclick = async () => {
    try {
      const j = await post('designer/settings/save', { root: $('#dsg-loc-root').value, technicians: locTechs() });
      $('#dsg-loc-root').value = j.root;
      $('#dsg-loc-status').textContent = 'Saved ✔ ' + new Date().toLocaleTimeString();
    } catch (e) { $('#dsg-loc-status').textContent = 'Save failed: ' + e.message; }
  };
  loadLoc();

  // ------------------------------------------------------------- signatories
  let sigPad = null;
  let sigData = { rows: [], users: [] };
  let sigDirty = false;
  const sigForm = (r) => {
    $('#dsg-sig-id').value = r ? r.id : '';
    $('#dsg-sig-user').value = r ? r.user_id : '';
    $('#dsg-sig-name').value = r ? r.full_name : '';
    $('#dsg-sig-title').value = r ? r.title : 'QA Laboratory Supervisor';
    $('#dsg-sig-active').checked = r ? String(r.is_active) === '1' : true;
    $('#dsg-sig-form-title').textContent = r ? 'Edit ' + r.full_name : 'Add a signatory';
    if (sigPad) { sigPad.clear(); if (r && r.signature_png) sigPad.load(r.signature_png); }
    sigDirty = false;
  };
  const sigRender = () => {
    $('#dsg-sig-user').innerHTML = '<option value="">- choose -</option>' + sigData.users.map((u) => `<option value="${u.UserID}">${esc(u.FullName || u.Username)} (${esc(u.Username)}, ${esc(u.Role)})</option>`).join('');
    $('#dsg-sig-table').innerHTML = '<tr><th>Name</th><th>Position</th><th>Account</th><th>Signature</th><th>Active</th><th></th></tr>' + (sigData.rows.map((r) => `<tr><td>${esc(r.full_name)}</td><td>${esc(r.title)}</td>`
      + `<td>${esc(r.Username || '')}${r.SynologyID ? '' : ' <span class="muted sm">(no Synology ID: web notices only)</span>'}</td>`
      + `<td>${r.signature_png ? `<img class="sig-thumb" src="${r.signature_png}" alt="signature">` : '<span class="muted sm">signs on the page</span>'}</td>`
      + `<td>${String(r.is_active) === '1' ? '✔' : '✖'}</td><td><button type="button" class="dsg-mini" data-sig-edit="${r.id}">Edit</button> <button type="button" class="dsg-mini dsg-danger" data-sig-del="${r.id}">✕</button></td></tr>`).join('')
      || '<tr><td colspan="6" class="muted sm">No signatory yet: reports cannot be approved until one is added.</td></tr>');
  };
  const sigLoad = async () => {
    try { sigData = await api('designer/signatories'); sigRender(); } catch (e) { $('#dsg-sig-status').textContent = e.message; }
  };
  $('#dsg-sig-table').addEventListener('click', async (ev) => {
    const ed = ev.target.dataset.sigEdit;
    const del = ev.target.dataset.sigDel;
    if (ed) sigForm(sigData.rows.find((r) => String(r.id) === ed));
    if (del && confirm('Remove this signatory? Reports they already signed stay signed.')) {
      try { sigData = await post('designer/signatories/delete', { id: del }); sigRender(); sigForm(null); } catch (e) { $('#dsg-sig-status').textContent = e.message; }
    }
  });
  $('#dsg-sig-user').addEventListener('change', () => {
    const u = sigData.users.find((x) => String(x.UserID) === $('#dsg-sig-user').value);
    if (u && !$('#dsg-sig-name').value) $('#dsg-sig-name').value = u.FullName || u.Username;
  });
  $('#dsg-sig-pad').addEventListener('pointerdown', () => { sigDirty = true; });
  $('#dsg-sig-clear').onclick = () => { if (sigPad) sigPad.clear(); sigDirty = false; };
  $('#dsg-sig-upload').onclick = () => $('#dsg-sig-file').click();
  $('#dsg-sig-file').onchange = async (ev) => {
    const f = ev.target.files && ev.target.files[0];
    ev.target.value = '';
    if (!f) return;
    if (!sigPad) sigPad = window.nxSignaturePad($('#dsg-sig-pad'));
    try { await sigPad.loadFile(f); sigDirty = true; $('#dsg-sig-status').textContent = 'Picture loaded: check it in the box, then Save signatory.'; }
    catch (e) { $('#dsg-sig-status').textContent = e.message; }
  };
  $('#dsg-sig-new').onclick = () => sigForm(null);
  $('#dsg-sig-save').onclick = async () => {
    try {
      sigData = await post('designer/signatories/save', {
        id: $('#dsg-sig-id').value, user_id: $('#dsg-sig-user').value, full_name: $('#dsg-sig-name').value, title: $('#dsg-sig-title').value,
        is_active: $('#dsg-sig-active').checked ? 1 : 0, signature_png: sigDirty && sigPad && !sigPad.isEmpty() ? sigPad.toDataURL() : '',
      });
      sigRender();
      $('#dsg-sig-status').textContent = 'Saved ✔ ' + new Date().toLocaleTimeString();
      sigForm(null);
    } catch (e) { $('#dsg-sig-status').textContent = 'Save failed: ' + e.message; }
  };
  // the pad is sized when its tab is first shown (a hidden canvas has no size)
  $$('.dsg-tab').forEach((b) => b.addEventListener('click', () => {
    if (b.dataset.tab === 'signatories' && !sigPad) setTimeout(() => { sigPad = window.nxSignaturePad($('#dsg-sig-pad')); }, 30);
  }));
  sigLoad();

  if (['form', 'excel', 'locations', 'signatories'].includes(startTab)) $(`.dsg-tab[data-tab="${startTab}"]`)?.click();

  (async () => {
    try { const j = await api('dtr/layout?slug=bonding&variant=form'); if (j.layout) formCfg = Object.assign(formCfg, j.layout); } catch (e) { /* default */ }
    renderForm();
    await loadVariant();
    await loadRecords();
    const rec = qs.get('record');
    if (rec && $('#dsg-record')) { $('#dsg-record').value = rec; $('#dsg-record').dispatchEvent(new Event('change')); }
  })();
})();
