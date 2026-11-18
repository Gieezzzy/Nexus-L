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
  const $ = (s, c = root) => c.querySelector(s);
  const $$ = (s, c = root) => [...c.querySelectorAll(s)];
  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const state = { cat: '', rows: [], open: new Set(), seq: 0 };
  let timer = 0;
  const day = (d) => (d ? String(d).slice(0, 10) : '');

  async function load() {
    const my = ++state.seq;
    const p = new URLSearchParams({ q: $('#mt-q').value.trim(), category: state.cat, status: $('#mt-status').value, from: $('#mt-from').value, to: $('#mt-to').value });
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

  const chip = (s) => `<span class="mt-chip s-${esc(String(s).toLowerCase().replace(/\s+/g, '-'))}">${esc(s)}</span>`;
  const resChip = (r) => (r ? `<span class="mt-res r-${esc(r.toLowerCase())}">${esc(r)}</span>` : '<span class="mt-none">-</span>');

  function childRows(r) {
    if (!r.tests.length) return '<p class="mt-empty">This material type has no test list.</p>';
    const head = '<thead><tr><th>#</th><th>Test</th><th>Child Lab Ref</th><th>Status</th><th>Result</th><th>Tested</th><th>Technician</th>' + (canEdit ? '<th></th>' : '') + '</tr></thead>';
    const body = r.tests.map((t, i) => {
      const meta = [t.method, t.unit].filter(Boolean).join(' · ');
      const name = `<b>${esc(t.name)}</b>${meta ? `<small>${esc(meta)}</small>` : ''}`;
      if (!canEdit) {
        return `<tr><td>${i + 1}</td><td>${name}</td><td class="mono">${esc(t.child)}</td><td>${chip(t.status)}</td><td>${resChip(t.result)}</td><td>${esc(day(t.tested)) || '-'}</td><td>${esc(t.tech) || '-'}</td></tr>`;
      }
      const sel = (opts, cur) => opts.map((o) => `<option value="${esc(o)}"${o === cur ? ' selected' : ''}>${o || '-'}</option>`).join('');
      return `<tr data-tid="${t.id}"><td>${i + 1}</td><td>${name}</td><td class="mono">${esc(t.child)}</td>`
        + `<td><select data-f="status" aria-label="Status of ${esc(t.name)}">${sel(['Pending', 'Ongoing', 'Completed'], t.status)}</select></td>`
        + `<td><select data-f="result" aria-label="Result of ${esc(t.name)}">${sel(['', 'PASS', 'TOLERABLE', 'FAIL'], t.result.toUpperCase())}</select></td>`
        + `<td><input type="date" data-f="tested_date" value="${esc(day(t.tested))}"></td>`
        + `<td><input type="text" data-f="technician" value="${esc(t.tech)}" maxlength="120" placeholder="Technician"></td>`
        + '<td class="mt-act"><button type="button" class="dtr-btn dtr-btn-primary" data-save>Save</button><small class="mt-msg" role="status"></small></td></tr>';
    }).join('');
    return `<div class="mt-tw"><table class="mt-t">${head}<tbody>${body}</tbody></table></div>`;
  }

  function group(r) {
    const open = state.open.has(r.id);
    const pct = r.total ? Math.round((r.done * 100) / r.total) : 0;
    const info = [['Material code', r.code], ['Supplier', r.supplier], ['Style', r.style], ['Colorway', r.colorway], ['MTL #', r.mtl], ['Prod. code', r.prod], ['Quantity', [r.qty, r.unit].filter(Boolean).join(' ')], ['QC quantity', r.qcqty], ['Arrived', day(r.arrived)]]
      .filter(([, v]) => v).map(([l, v]) => `<span><em>${l}</em> ${esc(v)}</span>`).join('');
    return `<article class="mt-group card glass${open ? ' open' : ''}" data-id="${r.id}">`
      + `<button type="button" class="mt-head" aria-expanded="${open}"><svg class="mt-chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>`
      + `<b class="mt-ref">${esc(r.lab_ref)}</b><span class="mt-name">${esc(r.name || r.code)}</span>`
      + `<span class="mt-type">${esc(r.category_name ? r.category_name + ' · ' : '')}${esc(r.type)}</span><span class="mt-date">${esc(day(r.received))}</span>`
      + `<span class="mt-prog" title="${r.done} of ${r.total} tests completed"><i style="width:${pct}%"></i></span><span class="mt-count">${r.done}/${r.total}</span>${r.fail ? `<span class="mt-res r-fail">${r.fail} fail</span>` : ''}${chip(r.state)}</button>`
      + `<div class="mt-body"${open ? '' : ' hidden'}><div class="mt-info">${info}</div>${childRows(r)}</div></article>`;
  }

  function draw() {
    const list = $('#mt-list');
    list.innerHTML = state.rows.length ? state.rows.map(group).join('') : '<p class="mt-empty">No received materials match. Materials are added in the Lab Tracker > Material Received.</p>';
  }

  root.addEventListener('click', async (e) => {
    const head = e.target.closest('.mt-head');
    if (head) {
      const g = head.closest('.mt-group'); const id = +g.dataset.id; const on = !state.open.has(id);
      if (on) state.open.add(id); else state.open.delete(id);
      g.classList.toggle('open', on); head.setAttribute('aria-expanded', on ? 'true' : 'false'); $('.mt-body', g).hidden = !on;
      return;
    }
    const cat = e.target.closest('.mt-cat');
    if (cat) { state.cat = cat.dataset.cat; $$('.mt-cat').forEach((b) => b.classList.toggle('active', b === cat)); load(); return; }
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
  const reload = () => { clearTimeout(timer); timer = setTimeout(load, 250); };
  $('#mt-q').addEventListener('input', reload);
  ['#mt-status', '#mt-from', '#mt-to'].forEach((s) => $(s).addEventListener('change', load));
  $('#mt-refresh').addEventListener('click', load);
  $('#mt-expand').addEventListener('click', () => { state.rows.forEach((r) => state.open.add(r.id)); draw(); });
  $('#mt-collapse').addEventListener('click', () => { state.open.clear(); draw(); });
  load();
})();
