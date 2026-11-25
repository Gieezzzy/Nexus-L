/**
 * Material Testing: the materials received in the Lab Tracker, one collapsible group per Lab Reference with the tests of its material type as children.
 * Data: GET materials/list; a test is saved with POST materials/test-update (laboratory staff only).
 */
(() => {
  'use strict';
  const root = document.getElementById('mt-root');
  if (!root) return;
  const base = root.dataset.base.replace(/\/?$/, '/');
  const canEdit = root.dataset.canEdit === '1';
  const me = root.dataset.user || '';
  const todayStr = () => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };   // the browser's day
  const $ = (s, c = root) => c.querySelector(s);
  const $$ = (s, c = root) => [...c.querySelectorAll(s)];
  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const state = { cat: '', st: 'pending', page: 1, rows: [], open: new Set(), seq: 0 };
  const PER = 20;
  const STS = [['pending', 'Pending'], ['completed', 'Completed'], ['failed', 'Failed']];
  const stOf = (r) => (r.fail > 0 ? 'failed' : (r.state === 'Completed' ? 'completed' : 'pending'));   // failed first, so a material is in one tab only
  let timer = 0;
  const day = (d) => (d ? String(d).slice(0, 10) : '');

  async function load() {
    const my = ++state.seq;
    const p = new URLSearchParams({ q: $('#mt-q').value.trim(), category: state.cat, status: '', from: $('#mt-from').value, to: $('#mt-to').value });
    try {
      const r = await fetch(base + 'list?' + p, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const j = await r.json();
      if (my !== state.seq) return;
      if (!j.ok) throw new Error('bad');
      state.rows = j.rows;
      $$('[data-count]').forEach((s) => { s.textContent = j.counts[s.dataset.count] || 0; });
      $('#mt-count-all').textContent = j.total;
      kpis(j.rows);
      draw();
    } catch (e) {
      $('#mt-list').innerHTML = '<p class="mt-empty">Could not load the materials. Try Refresh.</p>';
    }
  }

  function kpis(rows) {
    const tests = rows.reduce((a, r) => a + r.total, 0);
    const done = rows.reduce((a, r) => a + r.done, 0);
    const fail = rows.reduce((a, r) => a + r.fail, 0);
    $('#mt-kpis').innerHTML = [['Materials', rows.length, ''], ['Tests', tests, ''], ['Completed', done, 'ok'], ['Still to test', tests - done, tests - done ? 'warn' : 'ok'], ['Failed', fail, fail ? 'bad' : 'ok']]
      .map(([l, n, c]) => `<div class="mt-kpi ${c}"><b>${n}</b><span>${l}</span></div>`).join('');
  }

  const post = async (path, body) => {
    const r = await fetch(base + path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: JSON.stringify(body) });
    const j = await r.json().catch(() => ({ ok: false, message: 'The server did not answer.' })); if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
    return j;
  };
  const note = (msg, ok) => {
    const d = document.createElement('div'); d.className = 'mt-toast ' + (ok === false ? 'bad' : 'ok'); d.textContent = msg; document.body.appendChild(d);
    setTimeout(() => { d.classList.add('out'); setTimeout(() => d.remove(), 400); }, 3200);
  };
  const chip = (s) => `<span class="mt-chip s-${esc(String(s).toLowerCase().replace(/\s+/g, '-'))}">${esc(s)}</span>`;
  const tint = (r) => ({ FAIL: 't-fail', TOLERABLE: 't-tol', PASS: 't-pass' }[String(r || '').toUpperCase()] || '');
  const resChip = (r) => (r ? `<span class="mt-res r-${esc(r.toLowerCase())}">${esc(r)}</span>` : '<span class="mt-none">-</span>');

  function childRows(r) {
    if (!r.tests.length) return '<p class="mt-empty">This material type has no test list.</p>';
    const head = '<thead><tr><th>#</th><th>Test</th><th>Child Lab Ref</th><th>Status</th><th>Result</th><th>Tested</th><th>Technician</th>' + (canEdit && r.source !== 'bench' ? '<th class="mt-act"></th>' : '') + '</tr></thead>';
    const body = r.tests.map((t, i) => {
      const meta = [t.method, t.unit].filter(Boolean).join(' · ');
      const name = `<b>${esc(t.name)}</b>${meta ? `<small>${esc(meta)}</small>` : ''}`;
      const link = r.source === 'bench' ? `<span class="mono">${esc(t.child)}</span>` : `<button type="button" class="mt-link mono" data-open-test="${t.id}" data-mother="${esc(r.lab_ref)}" title="Open the test sheet">${esc(t.child)}</button>`;
      {
        return `<tr class="${tint(t.result)}"><td>${i + 1}</td><td>${name}</td><td>${link}</td><td>${chip(t.status)}</td><td>${resChip(t.result)}</td><td>${esc(day(t.tested)) || '-'}</td><td>${esc(t.tech) || '-'}</td>${canEdit && r.source !== 'bench' ? `<td class="mt-act"><button type="button" class="mt-x" data-rm-test="${t.id}" data-src="${t.src || ''}" data-name="${esc(t.name)}" data-lab="${esc(r.lab_ref)}" title="Delete this test completely" aria-label="Delete ${esc(t.name)}">&#128465; Delete</button></td>` : ''}</tr>`;
      }
      const sel = (opts, cur) => opts.map((o) => `<option value="${esc(o)}"${o === cur ? ' selected' : ''}>${o || '-'}</option>`).join('');
      return `<tr data-tid="${t.id}"><td>${i + 1}</td><td>${name}</td><td>${link}</td>`
        + `<td><select data-f="status" aria-label="Status of ${esc(t.name)}">${sel(['Pending', 'Ongoing', 'Completed'], t.status)}</select></td>`
        + `<td><select data-f="result" aria-label="Result of ${esc(t.name)}">${sel(['', 'PASS', 'TOLERABLE', 'FAIL'], t.result.toUpperCase())}</select></td>`
        + `<td><input type="date" data-f="tested_date" value="${esc(day(t.tested))}"></td>`
        + `<td><input type="text" data-f="technician" value="${esc(t.tech)}" maxlength="120" placeholder="Technician"></td>`
        + '<td class="mt-act"><button type="button" class="dtr-btn dtr-btn-primary" data-save>Save</button><small class="mt-msg" role="status"></small></td></tr>';
    }).join('');
    return `<div class="mt-tw${r.tests.length > 10 ? ' mt-tw-scroll' : ''}"><table class="mt-t">${head}<tbody>${body}</tbody></table></div>`;
  }

  function group(r) {
    const open = state.open.has(String(r.id));
    const pct = r.total ? Math.round((r.done * 100) / r.total) : 0;
    const info = [['Material code', r.code], ['Supplier', r.supplier], ['Style', r.style], ['Colorway', r.colorway], ['MTL #', r.mtl], ['Prod. code', r.prod], ['Quantity', [r.qty, r.unit].filter(Boolean).join(' ')], ['QC quantity', r.qcqty], ['Arrived', day(r.arrived)]]
      .filter(([, v]) => v).map(([l, v]) => `<span><em>${l}</em> ${esc(v)}</span>`).join('');
    const gt = r.fail ? 't-fail' : (r.tol ? 't-tol' : (r.state === 'Completed' ? 't-pass' : ''));   // red: a test failed, blue: tolerable, green: every test passed
    return `<article class="mt-group card glass${open ? ' open' : ''} ${gt}" data-id="${r.id}">`
      + `<button type="button" class="mt-head" aria-expanded="${open}"><svg class="mt-chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>`
      + `<b class="mt-ref mt-reflink" role="link" tabindex="0" data-open-mother="${esc(r.lab_ref)}" title="Open the whole view: every test and its status">${esc(r.lab_ref)}</b><span class="mt-name">${esc(r.name || r.code)}</span>`
      + `<span class="mt-type">${esc(r.category_name ? r.category_name + ' · ' : '')}${esc(r.type)}</span><span class="mt-date">${esc(day(r.received))}</span>`
      + `<span class="mt-prog" title="${r.done} of ${r.total} tests completed"><i style="width:${pct}%"></i></span><span class="mt-count">${r.done}/${r.total}</span>${r.fail ? `<span class="mt-res r-fail">${r.fail} fail</span>` : ''}${chip(r.state)}</button>`
      + `<div class="mt-body"${open ? '' : ' hidden'}><div class="mt-info">${info}${r.tests.length && r.source !== 'bench' ? `<button type="button" class="dtr-btn dtr-btn-primary mt-opens" data-open-mother="${esc(r.lab_ref)}">Open test sheet</button>` : ''}${canEdit && r.source !== 'bench' ? `<span class="mt-delwrap">${canEdit && r.source !== 'bench' ? `<button type="button" class="mt-x mt-delmat" data-del-mat="${esc(r.lab_ref)}" data-n="${r.tests.length}" title="Delete this whole material with all its tests">&#128465; Delete material</button>` : ''}</span><span class="mt-addtest" data-addwrap="${esc(r.lab_ref)}"><button type="button" class="dtr-btn dtr-btn-outline" data-add-test="${esc(r.lab_ref)}" title="Put a test of this material type back on the list">+ Add test</button></span>` : ''}</div>${childRows(r)}</div></article>`;
  }

  function draw() {
    const list = $('#mt-list');
    const cnt = { pending: 0, completed: 0, failed: 0 };
    state.rows.forEach((r) => { cnt[stOf(r)]++; });
    $('#mt-stabs').innerHTML = STS.map(([k, l]) => `<button type="button" role="tab" class="mt-stab s-${k}${state.st === k ? ' active' : ''}" aria-selected="${state.st === k}" data-st="${k}">${l}<small>${cnt[k]}</small></button>`).join('');
    const rows = state.rows.filter((r) => stOf(r) === state.st);
    const pages = Math.max(1, Math.ceil(rows.length / PER));
    if (state.page > pages) state.page = pages;
    const from = (state.page - 1) * PER;
    list.innerHTML = rows.length ? rows.slice(from, from + PER).map(group).join('') : `<p class="mt-empty">No ${state.st} materials${state.cat ? ' in this category' : ''}.</p>`;
    $('#mt-pager').innerHTML = rows.length > PER ? `<button type="button" class="dtr-btn dtr-btn-outline" data-pg="-1"${state.page <= 1 ? ' disabled' : ''}>‹ Prev</button><span>Page ${state.page} of ${pages} · ${rows.length} materials</span><button type="button" class="dtr-btn dtr-btn-outline" data-pg="1"${state.page >= pages ? ' disabled' : ''}>Next ›</button>` : '';
  }

  root.addEventListener('click', async (e) => {
    const head = e.target.closest('.mt-head');
    if (head) {
      const g = head.closest('.mt-group'); const id = g.dataset.id; const on = !state.open.has(id);
      if (on) state.open.add(id); else state.open.delete(id);
      g.classList.toggle('open', on); head.setAttribute('aria-expanded', on ? 'true' : 'false'); $('.mt-body', g).hidden = !on;
      return;
    }
    const stb = e.target.closest('[data-st]');
    if (stb) { state.st = stb.dataset.st; state.page = 1; draw(); return; }
    const pg = e.target.closest('[data-pg]');
    if (pg) { state.page += +pg.dataset.pg; draw(); $('#mt-stabs').scrollIntoView({ block: 'start' }); return; }
    const cat = e.target.closest('.mt-cat');
    if (cat) { state.cat = cat.dataset.cat; state.page = 1; $$('.mt-cat').forEach((b) => b.classList.toggle('active', b === cat)); load(); return; }
    const save = e.target.closest('[data-save]');
    if (save) {
      const tr = save.closest('tr'); const msg = $('.mt-msg', tr); save.disabled = true; msg.textContent = '';
      const body = { id: +tr.dataset.tid };
      $$('[data-f]', tr).forEach((x) => { body[x.dataset.f] = x.value; });
      try {
        const r = await fetch(base + 'test-update', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: JSON.stringify(body) });
        const j = await r.json().catch(() => ({}));
        if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
        msg.textContent = j.message || (r.ok ? 'Saved.' : 'Not saved.'); msg.className = 'mt-msg ' + (r.ok ? 'ok' : 'bad');
        if (r.ok) setTimeout(load, 500);
      } catch (err) { msg.textContent = 'Could not save.'; msg.className = 'mt-msg bad'; }
      save.disabled = false;
    }
  });
  root.addEventListener('change', (e) => {
    const f = e.target.closest('[data-f="status"]');
    if (f && f.value === 'Completed') { const tr = f.closest('tr'); const d = $('[data-f="tested_date"]', tr); if (d && !d.value) d.value = new Date().toISOString().slice(0, 10); }
  });

  // ================================================================ the test sheet: values, average, standard and rating of a Lab Ref (as on the Access forms)
  const sheet = { mother: '', test: 0, canEdit: false, tests: [], timer: 0, seq: 0, sibs: [], dirty: false };
  const fib = { wool: 'Wool', acrylic: 'Acrylic', polyester: 'Polyester', nylon: 'Nylon', cotton: 'Cotton', acetate: 'Acetate' };
  const fmt = (v) => (v === null || v === undefined || v === '' ? '' : String(v));
  const ratingChip = (r) => (r ? `<span class="mt-res r-${esc(r.toLowerCase())}">${esc(r)}</span>` : '<span class="mt-none">-</span>');

  function inputCells(t, r) {
    const v = t.values || {}; const ro = sheet.canEdit ? '' : ' disabled';
    const num = (f, ph) => `<input type="number" step="any" class="mt-num" data-f="${esc(f)}" value="${esc(fmt(v[f]))}"${ph ? ` placeholder="${esc(ph)}"` : ''}${ro}>`;
    if (r.mode === 'plain') return `<div class="mt-specs">${Array.from({ length: r.n }, (_, i) => num(r.k + (i + 1))).join('')}</div>`;
    if (r.mode === 'calc') {
      const c = ((t.calc && t.calc.rows) || {})[r.k] || {}; const vals = c.vals || [];
      return `<div class="mt-specs">${r.specs.map((sp, i) => `<div class="mt-calc">${r.specs.length > 1 ? `<small class="mt-calc-n">#${i + 1}</small>` : ''}${Object.keys(sp).map((f) => `<label class="mt-fib"><small>${esc(sp[f])}</small>${num(r.k + '_' + f + (i + 1))}</label>`).join('')}<span class="mt-calc-v"><small>${esc(r.out)}</small><b data-out="v${i}">${fmt(vals[i])}</b></span></div>`).join('')}</div>`;
    }
    if (r.mode === 'avg' || r.mode === 'each') return `<div class="mt-specs">${Array.from({ length: r.n }, (_, i) => (r.n > 1 ? `<label class="mt-fib"><small>Sample ${i + 1}</small>${num(r.k + (i + 1))}</label>` : num(r.k + (i + 1)))).join('')}</div>`;
    if (r.mode === 'fibre') return `<div class="mt-specs">${Object.keys(fib).map((x) => `<label class="mt-fib"><small>${fib[x]}</small>${num(r.k + '_' + x)}</label>`).join('')}</div>`;
    if (r.mode === 'text' || r.mode === 'mart' || r.mode === 'numtext') {
      const cur = fmt(v[r.mode === 'mart' || r.mode === 'numtext' ? r.k + 't' : r.k]);
      const sel = `<select data-f="${esc(r.mode === 'mart' || r.mode === 'numtext' ? r.k + 't' : r.k)}"${ro}><option value=""></option>${r.texts.map(([l]) => `<option${l.toLowerCase() === cur.toLowerCase() ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select>`;
      const sel2 = r.mode === 'text' || r.texts.length ? sel : '';
      if (r.mode === 'mart') return `<div class="mt-specs"><label class="mt-fib"><small>Cycles</small>${num(r.k + 'c')}</label><label class="mt-fib"><small>Observation</small>${sel}</label></div>`;
      if (r.mode === 'numtext') return `<div class="mt-specs"><label class="mt-fib"><small>Rating no.</small>${num(r.k + 'c')}</label>${sel2 ? `<span class="mt-or">or</span><label class="mt-fib"><small>Words</small>${sel2}</label>` : ''}</div>`;
      return `<div class="mt-specs">${sel}</div>`;
    }
    return '';
  }

  function ratingCell(t, r, c) {
    if (c && c.manual && sheet.canEdit) {
      const cur = fmt((t.values || {})[r.k + 'R']);
      return `<select data-f="${esc(r.k)}R" aria-label="Rating">${['', 'PASS', 'TOLERABLE', 'FAIL'].map((o) => `<option${o === cur ? ' selected' : ''}>${o}</option>`).join('')}</select>`;
    }
    return ratingChip(c ? c.rating : '');
  }

  function stdText(r, c) {
    if (c && c.std !== null && c.std !== undefined) return (r.dir === 'max' ? '≤ ' : '') + c.std;
    if (r.thick) return '<span class="mt-none">needs the Gauge</span>';
    if (r.mode === 'plain') return '';
    if (r.manual || (r.std && !r.std.length && r.mode !== 'text')) return 'Refer to specific requirements';
    return '<span class="mt-none">-</span>';
  }

  const nOf = (r) => (r.mode === 'avg' || r.mode === 'each' || r.mode === 'plain' ? r.n : (r.mode === 'calc' ? r.specs.length : 0));
  const ovOf = (t) => { const o = t.override || {}; return { hide: [].concat(o.hide || []), n: Array.isArray(o.n) ? {} : Object.assign({}, o.n || {}) }; };

  function testSection(t) {
    const rows = t.layout; const calc = (t.calc && t.calc.rows) || {};
    const cnt = (r) => (r.mode === 'avg' || r.mode === 'each') ? r.n : (r.mode === 'calc' ? r.specs.length : (r.mode === 'fibre' ? 6 : (r.mode === 'plain' ? 0 : 1)));
    const sampleInfo = rows.filter((r) => cnt(r) > 0).map((r) => `${r.label ? r.label + ': ' : ''}${cnt(r)} ${r.mode === 'fibre' ? 'fibres' : (cnt(r) === 1 ? 'sample' : 'samples')}`).join(' · ');
    const head = `<header class="mt-sh-h"><div><b>${esc(t.name)}</b><small>${esc([t.method].filter(Boolean).join(' · '))}</small><small class="mt-sh-n">${esc(sampleInfo)}</small></div><div class="mt-sh-r"><span class="mt-sh-state" data-state>${ratingChip(t.result)}</span>`
      + `<span class="mono mt-sh-child">${esc(t.child)}</span>${sheet.canEdit ? '<button type="button" class="dtr-btn dtr-btn-outline mt-rmtest" data-sh-remove title="This test does not apply to this material: take it off the list">Remove test</button><button type="button" class="dtr-btn dtr-btn-primary" data-sh-save>Save</button>' : ''}<small class="mt-msg" role="status"></small></div></header>`;
    const body = rows.map((r) => {
      const c = calc[r.k] || {};
      const mm = (r.mode === 'avg' && r.n > 1) || (r.mode === 'calc' && r.specs.length > 1) ? `<span data-out="max">${fmt(c.max)}</span><span data-out="min">${fmt(c.min)}</span>` : '';
      const ave = r.mode === 'avg' || r.mode === 'calc' ? `<b data-out="ave">${fmt(c.ave)}</b>` : '';
      const nctl = sheet.canEdit && nOf(r) > 0 ? `<div class="mt-nctl" title="How many samples this item has (the default may be 3: it can be 2 or 5)"><span>Samples</span><button type="button" data-n-dec="${esc(r.k)}"${nOf(r) <= 1 ? ' disabled' : ''} aria-label="One sample less">&minus;</button><b>${nOf(r)}</b><button type="button" data-n-inc="${esc(r.k)}"${nOf(r) >= 10 ? ' disabled' : ''} aria-label="One sample more">+</button></div>` : '';
      const rmrow = sheet.canEdit && rows.length > 1 ? `<button type="button" class="mt-x" data-rm-row="${esc(r.k)}" title="Remove this test item (it does not apply)" aria-label="Remove ${esc(r.label || r.k)}">&#10005;</button>` : '';
      return `<tr data-k="${esc(r.k)}"><td class="mt-sh-lab">${rmrow}${esc(r.label || '')}</td><td>${inputCells(t, r)}${nctl}${r.hint ? `<small class="mt-hint">${esc(r.hint)}</small>` : ''}</td><td class="mt-sh-unit">${esc(r.unit || '')}</td><td class="mt-sh-mm">${mm}</td><td class="mt-sh-ave">${ave}</td>`
        + `<td class="mt-sh-std" data-out="std">${stdText(r, c)}</td><td class="mt-sh-rt" data-out="rating">${ratingCell(t, r, c)}</td></tr>`;
    }).join('');
    return `<section class="mt-sh ${tint(t.result)}" data-id="${t.id}" data-code="${esc(t.code)}">${head}<table class="mt-sh-t"><thead><tr><th></th><th>Test items</th><th>Unit</th><th>Max / Min</th><th>Average</th><th>Standard</th><th>Rating</th></tr></thead><tbody>${body}</tbody></table>${sheet.canEdit && (t.removed || []).length ? `<div class="mt-sh-removed"><span>Removed items:</span>${t.removed.map((x) => `<button type="button" class="mt-chipbtn" data-rs-row="${esc(x.k)}" title="Bring this item back">${esc(x.label)} &#8634;</button>`).join('')}</div>` : ''}</section>`;
  }

  function sheetHtml(j) {
    const m = j.mother;
    const info = [['Material', m.name], ['Material code', m.code], ['Material type', m.type], ['Supplier', m.supplier], ['Style', m.style], ['Colorway', m.colorway], ['MTL #', m.mtl], ['Prod. code', m.prod], ['Quantity', [m.qty, m.unit].filter(Boolean).join(' ')], ['Received', day(m.received)]]
      .filter(([, v]) => v).map(([l, v]) => `<span><em>${l}</em> ${esc(v)}</span>`).join('');
    const first = j.tests[0] || {};
    const common = `<div class="mt-sh-common"><label>Technician <input type="text" id="mt-sh-tech" maxlength="120" value="${esc(first.tech || (sheet.canEdit ? me : ''))}"${sheet.canEdit ? '' : ' disabled'}></label>`
      + `<label>Tested date <input type="date" id="mt-sh-date" value="${esc(day(first.tested) || (sheet.canEdit ? todayStr() : ''))}"${sheet.canEdit ? '' : ' disabled'}></label>${j.gauge !== null && j.gauge !== undefined ? `<span class="mt-sh-g">Gauge average <b>${j.gauge}</b> mm decides the standards of tensile, elongation, tear and burst</span>` : ''}</div>`;
    return `<div class="mt-info">${info}</div>${common}${j.tests.map(testSection).join('') || '<p class="mt-empty">This material type has no test list.</p>'}`;
  }

  async function openSheet(mother, test) {
    Object.assign(sheet, { mother, test: test || 0, seq: sheet.seq + 1 });
    const dlg = $('#mt-sheet'); dlg.hidden = false; document.body.classList.add('mt-noscroll');
    $('#mt-sh-title').textContent = test ? 'Test sheet · ' + mother : 'Lab ' + mother + ' · Test results';
    $('#mt-sh-body').innerHTML = '<p class="muted">Loading...</p>'; $('#mt-sh-saveall').hidden = true;
    try {
      const r = await fetch(base + 'sheet?mother=' + encodeURIComponent(mother) + (test ? '&test=' + test : ''), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const j = await r.json(); if (!j.ok) throw new Error(j.message || 'bad');
      sheet.canEdit = !!j.can_edit; sheet.tests = j.tests; sheet.dirty = false; sibNav();
      $('#mt-sh-title').textContent = (test && j.tests[0] ? j.tests[0].name + ' · ' : '') + 'Lab ' + mother + ' · ' + (j.mother.type || '') + ' Test Results';
      $('#mt-sh-body').innerHTML = sheetHtml(j);
      $('#mt-sh-saveall').hidden = !(sheet.canEdit && j.tests.length > 1);
    } catch (e) {
      const g = state.rows.find((x) => x.lab_ref === mother);
      // Material Receive items have no test sheet yet: show the monitoring view of what is on record
      if (g && g.tests.length) { $('#mt-sh-body').innerHTML = statusView(g, test); $('#mt-sh-title').textContent = (test ? 'Test status · ' : 'Lab ') + (test ? ((g.tests.find((t) => t.id === test) || {}).child || mother) : mother + ' · All tests'); return; }
      $('#mt-sh-body').innerHTML = `<p class="mt-empty">${esc(e.message || 'Could not open the sheet.')}</p>`;
    }
  }
  // read-only monitoring view: every test of the Lab Ref with its own Lab Ref, status and result (or just the clicked test)
  function statusView(g, test) {
    const list = test ? g.tests.filter((t) => t.id === test) : g.tests;
    const info = [['Material', g.name || g.code], ['Type', g.type], ['Supplier', g.supplier], ['Style', g.style], ['Received', day(g.received)], ['Progress', g.done + ' / ' + g.total + ' tests completed']]
      .filter(([, v]) => v).map(([l, v]) => `<span><em>${l}</em> ${esc(v)}</span>`).join('');
    const rows = list.map((t, i) => `<tr class="${tint(t.result)}"><td>${i + 1}</td><td><b>${esc(t.name)}</b></td><td>${test ? `<span class="mono">${esc(t.child)}</span>` : `<button type="button" class="mt-link mono" data-open-test="${t.id}" data-mother="${esc(g.lab_ref)}">${esc(t.child)}</button>`}</td><td>${chip(t.status)}</td><td>${resChip(t.result)}</td><td>${esc(day(t.tested)) || '-'}</td><td>${esc(t.tech) || '-'}</td><td>${esc(t.remarks) || ''}</td></tr>`).join('');
    return `<div class="mt-info">${info}</div>${test ? `<p><button type="button" class="dtr-btn dtr-btn-outline" data-open-mother="${esc(g.lab_ref)}">&#8249; All tests of ${esc(g.lab_ref)}</button></p>` : ''}<div class="mt-tw${list.length > 10 ? ' mt-tw-scroll' : ''}"><table class="mt-t"><thead><tr><th>#</th><th>Test</th><th>Lab Ref</th><th>Status</th><th>Result</th><th>Tested</th><th>Technician</th><th>Remarks</th></tr></thead><tbody>${rows}</tbody></table></div>`;
  }
  // next / previous test of the same Lab Ref (only when ONE test is open): the tests as the list shows them
  function sibNav() {
    const g = state.rows.find((r) => r.lab_ref === sheet.mother && r.source !== 'bench');
    sheet.sibs = g ? g.tests : [];
    const i = sheet.test ? sheet.sibs.findIndex((t) => t.id === sheet.test) : -1;
    const nx = $('#mt-sh-next'); const pv = $('#mt-sh-prev');
    if (!nx || !pv) return;
    const next = i >= 0 ? sheet.sibs[i + 1] : null; const prev = i > 0 ? sheet.sibs[i - 1] : null;
    nx.hidden = !next; pv.hidden = !prev;
    if (next) { nx.textContent = 'Next: ' + next.name + ' \u203A'; nx.dataset.id = next.id; }
    if (prev) { pv.textContent = '\u2039 ' + prev.name; pv.dataset.id = prev.id; }
  }
  async function goTest(id) {          // what was typed is kept (saved) before moving on, so nothing is lost
    if (!id) return;
    if (sheet.canEdit && sheet.dirty) { for (const sec of $$('.mt-sh')) { if (Object.keys(sectionValues(sec)).length) await saveSection(sec, true); } await load(); }
    await openSheet(sheet.mother, +id);
  }
  const closeSheet = () => { $('#mt-sheet').hidden = true; document.body.classList.remove('mt-noscroll'); load(); };

  const sectionValues = (sec) => { const v = {}; $$('[data-f]', sec).forEach((x) => { if (x.value !== '') v[x.dataset.f] = x.value; }); return v; };

  async function recalc() {
    const my = ++sheet.seq;
    const tests = $$('.mt-sh').map((s) => { const tt = sheet.tests.find((x) => x.id === +s.dataset.id); return { id: +s.dataset.id, code: s.dataset.code, values: sectionValues(s), layout: tt ? ovOf(tt) : undefined }; });
    try {
      const r = await fetch(base + 'sheet-calc', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: JSON.stringify({ mother: sheet.mother, tests }) });
      const j = await r.json(); if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
      if (!j.ok || my !== sheet.seq) return;
      $$('.mt-sh').forEach((sec) => {
        const t = sheet.tests.find((x) => x.id === +sec.dataset.id); const c = j.tests[+sec.dataset.id]; if (!t || !c) return;
        $('[data-state]', sec).innerHTML = ratingChip(c.result); sec.classList.remove('t-fail', 't-pass', 't-tol'); if (tint(c.result)) sec.classList.add(tint(c.result));
        $$('tbody tr', sec).forEach((tr) => {
          const r = t.layout.find((x) => x.k === tr.dataset.k); const rc = c.rows[tr.dataset.k]; if (!r || !rc) return;
          const set = (n, h) => { const el = $(`[data-out="${n}"]`, tr); if (el) el.innerHTML = h; };
          set('ave', fmt(rc.ave)); set('max', fmt(rc.max)); set('min', fmt(rc.min)); set('std', stdText(r, rc));
          (rc.vals || []).forEach((x, i) => { const el = $(`[data-out="v${i}"]`, tr); if (el) el.textContent = fmt(x); });
          const cell = $('[data-out="rating"]', tr); if (!(rc.manual && sheet.canEdit)) cell.innerHTML = ratingChip(rc.rating);
        });
      });
      const g = $('.mt-sh-g'); if (g && j.gauge !== null) { const b = $('b', g); if (b) b.textContent = j.gauge; }
    } catch (e) { /* the numbers stay as they were */ }
  }

  async function changeLayout(sec, mutate) {
    const t = sheet.tests.find((x) => x.id === +sec.dataset.id); if (!t) return;
    const ov = ovOf(t); mutate(ov, t);
    const values = sectionValues(sec);
    const j = await post('sheet-layout', { id: t.id, layout: ov, values });
    if (!j.ok) { note(j.message || 'Could not change the test items.', false); return; }
    Object.assign(t, { layout: j.layout, override: j.override, removed: j.removed, calc: j.calc, values });
    const tmp = document.createElement('div'); tmp.innerHTML = testSection(t); sec.replaceWith(tmp.firstElementChild);
    sheet.dirty = true;
  }

  async function saveSection(sec, quiet) {
    const msg = $('.mt-msg', sec); const id = +sec.dataset.id;
    const tt = sheet.tests.find((x) => x.id === id);
    const body = { id, values: sectionValues(sec), technician: $('#mt-sh-tech').value, tested_date: $('#mt-sh-date').value, layout: tt ? ovOf(tt) : undefined };
    try {
      const r = await fetch(base + 'sheet-save', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: JSON.stringify(body) });
      const j = await r.json().catch(() => ({})); if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
      if (!quiet) { msg.textContent = j.message || (r.ok ? 'Saved.' : 'Not saved.'); msg.className = 'mt-msg ' + (r.ok ? 'ok' : 'bad'); }
      if (r.ok) { sheet.dirty = false; $('[data-state]', sec).innerHTML = ratingChip(j.result); if (!$('#mt-sh-date').value) { /* the server chose the date */ } }
      return r.ok;
    } catch (e) { if (!quiet) { msg.textContent = 'Could not save.'; msg.className = 'mt-msg bad'; } return false; }
  }

  root.addEventListener('click', async (e) => {
    const om = e.target.closest('[data-open-mother]');
    if (om) { e.stopPropagation(); openSheet(om.dataset.openMother, 0); return; }
    const ot = e.target.closest('[data-open-test]');
    if (ot) { openSheet(ot.dataset.mother, +ot.dataset.openTest); return; }
    if (e.target.closest('#mt-sh-close') || e.target === $('#mt-sheet')) { closeSheet(); return; }
    const nxb = e.target.closest('#mt-sh-next, #mt-sh-prev');
    if (nxb) { nxb.disabled = true; await goTest(nxb.dataset.id); nxb.disabled = false; return; }
    const rmr = e.target.closest('[data-rm-row]');
    if (rmr) { const sec = rmr.closest('.mt-sh'); const k = rmr.dataset.rmRow; await changeLayout(sec, (ov) => { if (!ov.hide.includes(k)) ov.hide.push(k); }); return; }
    const rsr = e.target.closest('[data-rs-row]');
    if (rsr) { const sec = rsr.closest('.mt-sh'); const k = rsr.dataset.rsRow; await changeLayout(sec, (ov) => { ov.hide = ov.hide.filter((x) => x !== k); }); return; }
    const nb = e.target.closest('[data-n-dec], [data-n-inc]');
    if (nb) {
      const sec = nb.closest('.mt-sh'); const k = nb.dataset.nDec || nb.dataset.nInc; const d = nb.dataset.nDec ? -1 : 1;
      await changeLayout(sec, (ov, t) => { const r = t.layout.find((x) => x.k === k); if (r) ov.n[k] = Math.max(1, Math.min(10, nOf(r) + d)); });
      return;
    }
    const rms = e.target.closest('[data-sh-remove]');
    if (rms) {
      const sec = rms.closest('.mt-sh'); const t = sheet.tests.find((x) => x.id === +sec.dataset.id); if (!t) return;
      if (!window.confirm('Remove "' + t.name + '" from ' + sheet.mother + '?\n\nUse this when the test does not apply to this material. Any result already entered for it is deleted. You can add it back with "+ Add test".')) return;
      const j = await post('test-remove', { id: t.id });
      if (!j.ok) { note(j.message || 'Could not remove the test.', false); return; }
      note(j.message, true); await load();
      if (sheet.test) { const at = sheet.sibs.findIndex((x) => x.id === t.id); const go = sheet.sibs[at + 1] || sheet.sibs[at - 1]; sibNav(); if (go) await openSheet(sheet.mother, go.id); else closeSheet(); } else await openSheet(sheet.mother, 0);
      return;
    }
    const dm = e.target.closest('[data-del-mat]');
    if (dm) {
      if (!window.confirm('Delete ' + dm.dataset.delMat + ' completely?\n\nThe material line, its ' + dm.dataset.n + ' test(s) with their Lab References and every result entered are deleted. This cannot be undone.')) return;
      const j = await post('delete', { mother: dm.dataset.delMat });
      if (j.ok) { note(j.message, true); await load(); } else note(j.message || 'Could not delete the material.', false);
      return;
    }
    const rmt = e.target.closest('[data-rm-test]');
    if (rmt) {
      if (!window.confirm('Remove "' + rmt.dataset.name + '" from ' + rmt.dataset.lab + '?\n\nUse this when the test does not apply to this material. Any result already entered for it is deleted. You can add it back with "+ Add test".')) return;
      const j = await post('test-remove', { id: +rmt.dataset.rmTest, src: rmt.dataset.src || '' });
      if (j.ok) { note(j.message, true); await load(); } else note(j.message || 'Could not remove the test.', false);
      return;
    }
    const adt = e.target.closest('[data-add-test]');
    if (adt) {
      const lab = adt.dataset.addTest; const wrap = adt.closest('.mt-addtest');
      try {
        const r = await fetch(base + 'test-options?mother=' + encodeURIComponent(lab), { credentials: 'same-origin', headers: { Accept: 'application/json' } }); const j = await r.json();
        if (!j.options || !j.options.length) { note('Every test of this material type is already on the list.', true); return; }
        wrap.classList.add('open');
        wrap.innerHTML = `<div class="mt-addbox"><div class="mt-addhead"><b>Add tests</b><span class="mt-addcount" data-add-count>0 selected</span><input type="search" class="mt-addsearch" data-add-q placeholder="Search tests..." aria-label="Search tests"><button type="button" class="mt-addlink" data-add-all>Select all</button><button type="button" class="mt-addlink" data-add-none>Clear</button></div><div class="mt-addlist">${j.options.map((o) => `<label class="mt-addopt" data-n="${esc((o.name + ' ' + o.code).toLowerCase())}"><input type="checkbox" data-add-chk value="${esc(o.code)}"><span>${esc(o.name)}${o.method ? ' <small>' + esc(o.method) + '</small>' : ''}</span></label>`).join('')}</div><div class="mt-addbar"><button type="button" class="dtr-btn dtr-btn-primary" data-add-go="${esc(lab)}">Add selected</button><button type="button" class="dtr-btn dtr-btn-outline" data-add-cancel>Cancel</button></div></div>`;
      } catch (err) { note('Could not read the tests of this material type.', false); }
      return;
    }
    const aw = e.target.closest('.mt-addtest');
    if (aw && (e.target.closest('[data-add-all]') || e.target.closest('[data-add-none]'))) {
      const on = !!e.target.closest('[data-add-all]');
      aw.querySelectorAll('.mt-addopt:not([hidden]) [data-add-chk]').forEach((c) => { c.checked = on; });
      aw.dispatchEvent(new Event('change', { bubbles: true })); return;
    }
    const ago = e.target.closest('[data-add-go]');
    if (ago) {
      const codes = [...ago.closest('.mt-addtest').querySelectorAll('[data-add-chk]:checked')].map((c) => c.value);
      if (!codes.length) { note('Tick at least one test to add.', false); return; }
      ago.disabled = true; let okN = 0; let bad = '';
      for (const code of codes) { const j = await post('test-add', { mother: ago.dataset.addGo, code }); if (j.ok) okN++; else bad = j.message || 'Could not add a test.'; }
      if (okN) { note(okN + ' test' + (okN === 1 ? '' : 's') + ' added.' + (bad ? ' ' + bad : ''), !bad); await load(); } else { ago.disabled = false; note(bad || 'Could not add the tests.', false); }
      return;
    }
    if (e.target.closest('[data-add-cancel]')) { await load(); return; }
    const sv = e.target.closest('[data-sh-save]');
    if (sv) { sv.disabled = true; await saveSection(sv.closest('.mt-sh')); sv.disabled = false; return; }
    if (e.target.closest('#mt-sh-saveall')) {
      const b = e.target.closest('button'); b.disabled = true;
      for (const sec of $$('.mt-sh')) { if (Object.keys(sectionValues(sec)).length) await saveSection(sec, true); }
      await openSheet(sheet.mother, sheet.test); b.disabled = false;
    }
  });
  root.addEventListener('input', (e) => { if (e.target.closest('.mt-sh') && e.target.matches('[data-f]')) { sheet.dirty = true; clearTimeout(sheet.timer); sheet.timer = setTimeout(recalc, 280); } });
  root.addEventListener('change', (e) => { if (e.target.closest('.mt-sh') && e.target.matches('select[data-f]')) { sheet.dirty = true; recalc(); } });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !$('#mt-sheet').hidden) closeSheet(); });
  const reload = () => { state.page = 1; clearTimeout(timer); timer = setTimeout(load, 250); };
  $('#mt-q').addEventListener('input', reload);
  ['#mt-from', '#mt-to'].forEach((s) => $(s).addEventListener('change', () => { state.page = 1; load(); }));
  $('#mt-refresh').addEventListener('click', load);
  $('#mt-expand').addEventListener('click', () => { state.rows.filter((r) => stOf(r) === state.st).slice((state.page - 1) * PER, state.page * PER).forEach((r) => state.open.add(String(r.id))); draw(); });
  $('#mt-collapse').addEventListener('click', () => { state.open.clear(); draw(); });
  load();

  document.addEventListener('change', (e) => {
    const w = e.target.closest && e.target.closest('.mt-addtest'); if (!w) return;
    const n = w.querySelectorAll('[data-add-chk]:checked').length;
    const c = w.querySelector('[data-add-count]'); if (c) c.textContent = n + ' selected';
    w.querySelectorAll('.mt-addopt').forEach((l) => l.classList.toggle('on', l.querySelector('input').checked));
  });
  document.addEventListener('input', (e) => {
    if (!e.target.matches || !e.target.matches('[data-add-q]')) return;
    const q = e.target.value.trim().toLowerCase();
    e.target.closest('.mt-addtest').querySelectorAll('.mt-addopt').forEach((l) => { l.hidden = q && !l.dataset.n.includes(q); });
  });
})();
