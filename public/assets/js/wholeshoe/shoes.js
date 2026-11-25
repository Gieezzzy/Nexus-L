/**
 * Whole Shoe Test > Whole Shoes: one line per whole shoe (parent Lab Ref) in the same table design as the other tabs.
 * Click the Lab Ref to open all its tests with their status; there a test is added ("+ Add test") or removed (x) when it does not apply.
 */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const list = $('#ws-list');
  if (!list) return;
  const baseMeta = $('meta[name="base-url"]');
  const BASE = baseMeta ? baseMeta.content.replace(/\/+$/, '') + '/' : location.origin + '/';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  let page = 1;
  let data = { shoes: [], can_edit: false, page: 1, per_page: 12 };
  let timer = null;
  let modal = null;
  let curParent = '';
  let loadCtl = null;

  const csrf = () => document.querySelector('meta[name="X-CSRF-TOKEN"]')?.content || '';
  const api = async (path, body, signal) => {
    const res = await fetch(BASE + path, {
      method: body ? 'POST' : 'GET',
      signal,
      headers: Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() }, body ? { 'Content-Type': 'application/json' } : {}),
      body: body ? JSON.stringify(body) : undefined,
    });
    let j = {};
    try { j = await res.json(); } catch (e) { j = { ok: false, message: 'The server did not answer.' }; }
    j._status = res.status;
    if (j.csrf) { const m = document.querySelector('meta[name="X-CSRF-TOKEN"]'); if (m) m.content = j.csrf; }
    return j;
  };
  const note = (msg, ok = true) => {
    const d = document.createElement('div');
    d.className = 'mt-toast ' + (ok ? 'ok' : 'bad');
    d.textContent = msg;
    document.body.appendChild(d);
    setTimeout(() => d.classList.add('out'), 2600);
    setTimeout(() => d.remove(), 3200);
  };

  const dateOf = (d) => { const t = Date.parse(d); return t ? new Date(t).toLocaleDateString('en-US') : '—'; };
  const resCls = (r) => (r.startsWith('FAIL') ? 'ws-fail' : r.startsWith('TOL') ? 'ws-tol' : r.startsWith('PASS') ? 'ws-pass' : '');
  const pill = (st) => {
    const u = String(st || '').toUpperCase();
    const k = u === 'COMPLETED' ? ['wst-status-completed', 'COMPLETED'] : u === 'RUNNING' ? ['wst-status-running', 'RUNNING'] : u === 'PAUSED' ? ['wst-status-paused', 'PAUSED']
      : u === 'STOPPED' ? ['wst-status-stopped', 'STOPPED'] : ['wst-status-pending', 'PENDING'];
    return `<span class="wst-status-pill ${k[0]}">${k[1]}</span>`;
  };
  const shoeStatus = (s) => {
    const t = s.tests;
    if (!t.length) return 'PENDING';
    if (t.every((x) => x.status === 'COMPLETED')) return 'COMPLETED';
    if (t.some((x) => x.status === 'RUNNING' || x.status === 'PAUSED' || x.status === 'COMPLETED')) return 'RUNNING';
    return 'PENDING';
  };

  const render = () => {
    if (!data.shoes.length) {
      list.innerHTML = '<tr><td colspan="9" class="muted" style="text-align:center;padding:24px">No whole shoes found.</td></tr>';
      return;
    }
    const base = (data.page - 1) * (data.per_page || 12);
    list.innerHTML = data.shoes.map((s, i) => {
      const done = s.tests.filter((t) => t.status === 'COMPLETED').length;
      return `<tr class="clickable-row wst-row" data-ws-parent="${esc(s.parent)}" style="cursor:pointer">
        <td class="mono">${base + i + 1}</td>
        <td><b class="mono ws-lablink" style="color:var(--brand,#2563eb);font-size:13.5px">${esc(s.parent)}</b>${/WH[AB]\d/.test(s.parent) ? `<div class="muted sm mono" title="Lab Ref in AppSheet">AppSheet: ${esc(s.parent.replace(/WH([AB]\d)/, 'WS$1'))}</div>` : ''}</td>
        <td>${esc(dateOf(s.date))}</td>
        <td><span style="font-weight:600">${esc((s.style + ' ' + s.color).trim() || '—')}</span></td>
        <td><span class="mono sm muted">${esc(s.prod || '—')}${s.mtl ? ' &bull; ' + esc(s.mtl) : ''}</span></td>
        <td>${s.tr ? `<span class="mono" style="font-weight:700;color:#1d4ed8">${esc(s.tr)}</span>${s.erp ? ' <span class="ws-chip ws-erp">ERP ' + esc(s.erp) + '</span>' : ''}` : '<span class="muted">—</span>'}</td>
        <td><span class="mono bold">${done} / ${s.tests.length}</span> <span class="muted sm">done</span></td>
        <td>${pill(shoeStatus(s))}</td>
        <td style="text-align:right"><button type="button" class="dtr-btn-xs dtr-btn-primary" style="box-shadow:0 1px 4px rgba(37,99,235,0.25)"><svg class="ic" aria-hidden="true" style="margin-right:2px"><use href="#i-grid"/></svg> Open Tests</button></td>
      </tr>`;
    }).join('');
  };

  // ---- all the tests of one whole shoe (opens on the Lab Ref) ----
  const closeModal = () => { if (modal) { modal.remove(); modal = null; } curParent = ''; };
  const renderModal = () => {
    const s = data.shoes.find((x) => x.parent === curParent);
    if (!s) { closeModal(); return; }
    const edit = !!data.can_edit;
    const done = s.tests.filter((t) => t.status === 'COMPLETED').length;
    modal.querySelector('.dtr-modal-dialog').innerHTML = `
      <div class="dtr-modal-header">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          ${pill(shoeStatus(s))}<h3 style="margin:0;font-size:15px;font-weight:700" class="mono">${esc(s.parent)}</h3>
          ${s.tr ? `<span class="ws-chip ws-tr">${esc(s.tr)}</span>` : ''}${s.erp ? `<span class="ws-chip ws-erp">ERP ${esc(s.erp)}</span>` : ''}
        </div>
        <button type="button" class="dtr-modal-close" data-ws-close>&times;</button>
      </div>
      <div class="dtr-modal-body" style="padding:16px 20px">
        <div class="ws-info">
          <div><small class="muted uppercase">Specification</small><b>${esc((s.style + ' ' + s.color).trim() || '—')}</b></div>
          <div><small class="muted uppercase">Production Code / MTL</small><b class="mono">${esc(s.prod || '—')}${s.mtl ? ' &bull; ' + esc(s.mtl) : ''}</b></div>
          <div><small class="muted uppercase">Date</small><b>${esc(dateOf(s.date))}</b></div>
          <div><small class="muted uppercase">Tests</small><b>${done} / ${s.tests.length} done</b></div>
        </div>
        ${s.tests.length ? `<div class="dtr-table-wrap"><table class="dtr-table"><thead><tr><th>TEST</th><th>TEST LAB REFERENCE</th><th>STATUS</th><th>RESULT</th><th>TECHNICIAN</th><th style="text-align:right">ACTION</th></tr></thead><tbody>
          ${s.tests.map((t) => `<tr>
            <td style="font-weight:600">${esc(t.type || t.key)}</td>
            <td><b class="mono" style="color:var(--brand,#2563eb)">${esc(t.lab_ref)}</b></td>
            <td>${pill(t.status)}</td>
            <td class="${resCls(t.result)}">${esc(t.result || '—')}</td>
            <td><span class="mono sm muted">${esc(t.tech || '—')}</span></td>
            <td style="text-align:right;white-space:nowrap">
              ${t.wst_id ? `<button type="button" class="dtr-btn-xs dtr-btn-primary" data-ws-open="${t.wst_id}"><svg class="ic" aria-hidden="true" style="margin-right:2px"><use href="#i-grid"/></svg> Open Monitor</button>` : ''}
              ${edit ? `<button type="button" class="mt-x" title="This test does not apply: remove it" data-ws-rm="${t.id}" data-ws-lab="${esc(t.lab_ref)}">&#10005;</button>` : ''}
            </td></tr>`).join('')}</tbody></table></div>` : '<p class="muted" style="text-align:center;padding:16px">No test on this whole shoe yet.</p>'}
        ${edit ? `<div style="margin-top:12px;position:relative"><button type="button" class="mt-chipbtn" data-ws-add="${esc(s.parent)}">+ Add test</button></div>` : ''}
      </div>`;
  };
  const openModal = (parent) => {
    curParent = parent;
    if (!modal) {
      modal = document.createElement('div');
      modal.className = 'dtr-modal-backdrop';
      modal.style.zIndex = '10060';
      modal.innerHTML = '<div class="dtr-modal-dialog wst-theme-modal" style="max-width:860px;width:94%"></div>';
      modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
      document.body.appendChild(modal);
    }
    renderModal();
  };

  const load = async () => {
    if (loadCtl) { try { loadCtl.abort(); } catch (_) {} }
    loadCtl = new AbortController();
    const q = encodeURIComponent($('#ws-search').value.trim());
    list.innerHTML = '<tr><td colspan="9" class="muted" style="text-align:center;padding:24px">Loading page ' + page + '...</td></tr>';
    let r;
    try {
      r = await api(`wst/shoes?q=${q}&from=${$('#ws-from').value}&to=${$('#ws-to').value}&page=${page}`, null, loadCtl.signal);
    } catch (e) {
      if (e && e.name === 'AbortError') return;
      list.innerHTML = '<tr><td colspan="9" class="muted" style="padding:20px;text-align:center">Could not load the whole shoes.</td></tr>';
      return;
    }
    if (!r.ok) { list.innerHTML = '<tr><td colspan="9" class="muted" style="padding:20px;text-align:center">Could not load the whole shoes.</td></tr>'; return; }
    data = r;
    page = r.page || page;
    $('#ws-count').textContent = `${r.total} record(s)`;
    $('#ws-page').textContent = `${r.page} / ${r.pages}`;
    $('#ws-prev').disabled = r.page <= 1;
    $('#ws-next').disabled = r.page >= r.pages;
    render();
    if (modal) renderModal();
  };

  const addTest = async (parent, btn) => {
    document.querySelectorAll('.ws-pop').forEach((p) => p.remove());
    const r = await api('wst/shoe-test-options?parent=' + encodeURIComponent(parent));
    const pop = document.createElement('div');
    pop.className = 'ws-pop';
    pop.innerHTML = r.options && r.options.length
      ? '<div class="muted sm" style="padding:4px 8px">Add which test?</div>' + r.options.map((o) => `<button type="button" data-ws-pick="${esc(o.type)}" data-ws-side="${esc(o.side)}">${esc(o.type)} &middot; ${o.side === 'R' ? 'Right' : 'Left'} (${esc(o.side)})</button>`).join('')
      : '<div class="muted sm" style="padding:6px 10px">Every test (Left and Right) is already recorded on this whole shoe.</div>';
    btn.parentNode.appendChild(pop);
    pop.addEventListener('click', async (e) => {
      const sd = e.target.closest('[data-ws-pick]');
      if (!sd) return;
      pop.remove();
      const a = await api('wst/shoe-test-add', { parent, type: sd.dataset.wsPick, side: sd.dataset.wsSide });
      note(a.message || (a.ok ? 'Added.' : 'Could not add the test.'), !!a.ok);
      if (a.ok) load();
    });
  };

  const remove = async (id, lab) => {
    let r = await api('wst/shoe-test-remove', { id });
    if (r._status === 409 && r.needs_confirm) {
      if (!window.confirm(`${r.message}\n\nRemove it anyway? Its bench session and recorded readings are deleted too.`)) return;
      r = await api('wst/shoe-test-remove', { id, force: true });
    }
    note(r.message || (r.ok ? 'Removed.' : 'Could not remove ' + lab), !!r.ok);
    if (r.ok) load();
  };

  list.addEventListener('click', (e) => {
    const row = e.target.closest('[data-ws-parent]');
    if (row) openModal(row.dataset.wsParent);
  });
  document.addEventListener('click', (e) => {
    if (!modal || !modal.contains(e.target)) {
      if (!e.target.closest('.ws-pop')) document.querySelectorAll('.ws-pop').forEach((p) => p.remove());
      return;
    }
    const t = e.target;
    const rm = t.closest('[data-ws-rm]');
    if (rm) { remove(+rm.dataset.wsRm, rm.dataset.wsLab); return; }
    const ad = t.closest('[data-ws-add]');
    if (ad) { addTest(ad.dataset.wsAdd, ad); return; }
    const op = t.closest('[data-ws-open]');
    if (op) { const id = op.dataset.wsOpen; closeModal(); if (window.WstOpenMonitor) window.WstOpenMonitor(id); return; }
    if (t.closest('[data-ws-close]')) closeModal();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal) closeModal(); });

  $('#ws-search').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { page = 1; load(); }, 300); });
  ['#ws-from', '#ws-to'].forEach((s) => $(s).addEventListener('change', () => { page = 1; load(); }));
  $('#ws-reset').addEventListener('click', () => { $('#ws-search').value = ''; $('#ws-from').value = ''; $('#ws-to').value = ''; page = 1; load(); });
  $('#ws-prev').addEventListener('click', () => { page = Math.max(1, page - 1); load(); });
  $('#ws-next').addEventListener('click', () => { page += 1; load(); });

  // Whole Shoes <-> the category lists
  const shoesBox = $('#wst-shoes-view');
  const catBox = $('#wst-cat-view');
  const tab = $('#wst-tab-shoes');
  const show = (shoes) => {
    shoesBox.style.display = shoes ? '' : 'none';
    catBox.style.display = shoes ? 'none' : '';
    tab.classList.toggle('active', shoes);
    if (!shoes) closeModal();
    if (shoes) load();
  };
  tab.addEventListener('click', () => {
    document.querySelectorAll('.wst-cat-tab').forEach((t) => t.classList.remove('active'));
    show(true);
  });
  document.querySelectorAll('.wst-cat-tab').forEach((t) => t.addEventListener('click', () => show(false), true));
  load();
})();
