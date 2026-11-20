(() => {
  'use strict';
  const root = document.getElementById('lm-root');
  if (!root) return;
  const $ = (s) => root.querySelector(s);
  const ME = Number(root.dataset.me || 0);
  const csrf = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const base = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  // two tabs: Bonding (the original view) and Whole Shoe (same view, the TR numbers of the other eTRF types)
  const savedKind = (() => { try { return localStorage.getItem('nxl:erp-kind'); } catch (e) { return null; } })();
  const hashKind = /kind=wholeshoe/.test(location.hash) ? 'wholeshoe' : (/kind=bonding/.test(location.hash) ? 'bonding' : '');
  const S = { kind: hashKind || (savedKind === 'wholeshoe' ? 'wholeshoe' : 'bonding'), date: '', side: '', status: '', q: '', rows: [], timer: 0, select: false, sel: new Set(), edit: 0 };
  const isWS = () => S.kind === 'wholeshoe';
  const COLS = () => (isWS() ? 15 : 12);
  const HEAD = {
    bonding: '<tr><th class="lm-editcol">Edit</th><th class="lm-sel"><input type="checkbox" id="lm-pick-all" aria-label="Select all"></th><th>Tested date</th><th>Production date</th><th>Line</th><th>Lab reference</th><th>VSP code</th><th>Bonding TR no.</th><th>Pull test TR no.</th><th>InterLink status</th><th>Result</th><th>ERP status</th></tr>',
    wholeshoe: '<tr><th class="lm-editcol">Edit</th><th class="lm-sel"><input type="checkbox" id="lm-pick-all" aria-label="Select all"></th><th>Tested date</th><th>Production date</th><th>Line</th><th>Lab reference</th><th>VSP code</th><th>Flexing Test</th><th>Aging Test</th><th>Hydrolysis Test</th><th>Washing Test</th><th>UV Light Test</th><th>InterLink status</th><th>Result</th><th>ERP status</th></tr>',
  };

  const get = async (url) => {
    const r = await fetch(base + url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const j = await r.json();
    if (csrf && j.csrf) csrf.content = j.csrf;
    if (!r.ok || j.ok === false) throw new Error(j.error || 'Request failed');
    return j;
  };

  const fmtDay = (d) => { const t = String(d); if (!/^\d{4}-\d{2}-\d{2}/.test(t)) return t; const [y, m, dd] = t.slice(0, 10).split('-'); return `${m}-${dd}-${y}`; };
  const monthName = (ym) => { const [y, m] = ym.split('-'); return new Date(Number(y), Number(m) - 1, 1).toLocaleString('en', { month: 'long', year: 'numeric' }); };
  const IL = { ok: 'INTERLINK OK', pending: 'PENDING', bad: 'NOT SUBMITTED' };
  const ERPL = { ok: 'ERP OK', pending: 'ONGOING', bad: 'NOT SUBMITTED' };

  const apps = (cur, hist) => {
    if (!cur) return '<span class="lm-none">-</span>';
    const h = (hist || '').split(';').filter(Boolean);
    return `<span class="lm-num">${esc(cur)}</span>${h.length ? `<span class="lm-hist" title="Earlier: ${esc(h.join('; '))}">+${h.length} earlier</span>` : ''}`;
  };

  // ---- Edit button: every cell of the row can be typed except the Line and the Lab reference ----
  const editBtn = (r) => `<td class="lm-editcell"><button type="button" class="lm-edit-btn" data-edit="${r.id}" aria-label="Edit ${esc(r.lab_ref)}">Edit</button></td>`;
  const optList = (list, cur) => list.map(([v, t]) => `<option value="${v}" ${v === cur ? 'selected' : ''}>${t}</option>`).join('');
  const IL_OPTS = [['ok', 'INTERLINK OK'], ['pending', 'PENDING'], ['bad', 'NOT SUBMITTED']];
  const RES_OPTS = [['', '-'], ['Pass', 'Pass'], ['Tolerable', 'Tolerable'], ['Fail', 'Fail']];
  const ERP_OPTS = [['', 'Not submitted'], ['OK', 'ERP OK'], ['Submitted', 'Submitted'], ['Ongoing', 'Ongoing'], ['Pending', 'Pending']];
  const resVal = (r) => { const u = String(r.result || '').toUpperCase(); return u.startsWith('FAIL') ? 'Fail' : u.startsWith('TOL') ? 'Tolerable' : u ? 'Pass' : ''; };
  const inp = (f, v, ph, w) => `<input class="lm-in" data-ef="${f}" value="${esc(v || '')}" placeholder="${ph || ''}" ${w ? `style="width:${w}px"` : ''} autocomplete="off">`;
  const trIn = (f, v) => inp(f, v, 'A00…', 112);
  const editRow = (r) => {
    const common = `<td class="lm-editcell"><button type="button" class="lm-save-btn" data-save="${r.id}">Save</button><button type="button" class="lm-cancel-btn" data-cancel="1">Cancel</button></td><td class="lm-sel"></td>
      <td><input type="date" class="lm-in" data-ef="tested" value="${esc(r.tested || '')}"></td>
      <td>${isWS() ? `<b>${esc(fmtDay(r.day))}</b>` : `<input type="date" class="lm-in" data-ef="day" value="${esc(r.day || '')}">`}</td>
      <td>${esc(r.line)}</td>
      <td class="lm-lab">${esc(r.lab_ref)}</td>
      <td>${inp('vsp', r.vsp, 'VSP', 110)}</td>`;
    const trs = isWS()
      ? ['flexing', 'aging', 'hydrolysis', 'washing', 'uv'].map((k) => `<td>${trIn(k, r[k])}</td>`).join('')
      : `<td>${trIn('bonding', r.bonding)}</td><td>${trIn('pull', r.pull)}</td>`;
    return `<tr class="lm-editing" data-id="${r.id}">${common}${trs}
      <td><select class="lm-in" data-ef="il">${optList(IL_OPTS, r.il)}</select></td>
      <td><select class="lm-in" data-ef="result">${optList(RES_OPTS, resVal(r))}</select></td>
      <td><select class="lm-in" data-ef="erp">${optList(ERP_OPTS, r.erp_raw === 'OK' ? 'OK' : (r.erp_raw || ''))}</select></td></tr>`;
  };

  const rowHtmlWS = (r) => {
    if (S.edit === r.id) return editRow(r);
    const erpSel = ['OK', 'Submitted', 'Ongoing', 'Pending', ''].map((v) => `<option value="${v}" ${v === r.erp_raw ? 'selected' : ''}>${v === '' ? 'Not submitted' : v === 'OK' ? 'ERP OK' : v}</option>`).join('');
    const can = r.erp !== 'ok';   // ERP OK / Submitted lines are done: nothing to send again
    return `<tr class="lm-click" data-id="${r.id}" data-lab="${esc(r.lab_ref)}" tabindex="0" aria-label="Open ${esc(r.lab_ref)}">
      ${editBtn(r)}<td class="lm-sel"><input type="checkbox" data-pick="${r.id}" ${S.sel.has(r.id) ? 'checked' : ''} ${can ? '' : 'disabled'} aria-label="Select ${esc(r.lab_ref)} for ERP"></td>
      <td>${r.tested ? esc(fmtDay(r.tested)) : '<span class="lm-none">-</span>'}</td>
      <td><b>${esc(fmtDay(r.day))}</b></td>
      <td>${esc(r.line)}</td>
      <td class="lm-lab">${esc(r.lab_ref)}${r.style ? `<small class="lm-style">${esc(r.style)}</small>` : ''}</td>
      <td class="lm-num">${esc(r.vsp) || '<span class="lm-none">-</span>'}</td>
      <td>${apps(r.flexing, r.flexing_hist)}</td>
      <td>${apps(r.aging, r.aging_hist)}</td>
      <td>${apps(r.hydrolysis, r.hydrolysis_hist)}</td>
      <td>${apps(r.washing, r.washing_hist)}</td>
      <td>${apps(r.uv, r.uv_hist)}</td>
      <td class="s ${r.il}">${IL[r.il]}</td>
      <td class="lm-res ${r.level}">${r.result ? `<b>${esc(r.result)}</b>` : '<span class="lm-none">-</span>'}</td>
      <td class="s ${r.erp}"><select data-id="${r.id}" aria-label="ERP status of ${esc(r.lab_ref)}">${erpSel}</select></td>
    </tr>`;
  };
  const rowHtml = (r) => {
    if (isWS()) return rowHtmlWS(r);
    if (r.shutdown) return `<tr class="lm-shut"><td class="lm-editcell"></td><td class="lm-sel"></td><td>SHUTDOWN</td><td>${esc(fmtDay(r.day))}</td><td>${esc(r.line)}</td><td class="lm-lab">${esc(r.lab_ref)}</td><td colspan="6">SHUTDOWN</td></tr>`;
    if (S.edit === r.id) return editRow(r);
    const notes = [r.notes, r.cause].filter(Boolean).join('\n');
    const erpSel = ['OK', 'Submitted', 'Ongoing', 'Pending', ''].map((v) => `<option value="${v}" ${v === r.erp_raw ? 'selected' : ''}>${v === '' ? 'Not submitted' : v === 'OK' ? 'ERP OK' : v}</option>`).join('');
    const can = r.erp !== 'ok';   // ERP OK / Submitted rows are done: nothing to send again
    return `<tr class="lm-click" data-id="${r.id}" data-lab="${esc(r.lab_ref)}" tabindex="0" aria-label="Open ${esc(r.lab_ref)}">
      ${editBtn(r)}<td class="lm-sel"><input type="checkbox" data-pick="${r.id}" ${S.sel.has(r.id) ? 'checked' : ''} ${can ? '' : 'disabled'} aria-label="Select ${esc(r.lab_ref)} for ERP"></td>
      <td>${r.tested ? esc(fmtDay(r.tested)) : '<span class="lm-none">-</span>'}</td>
      <td><b>${esc(fmtDay(r.day))}</b></td>
      <td>${esc(r.line)}</td>
      <td class="lm-lab">${esc(r.lab_ref)}</td>
      <td class="lm-num">${esc(r.vsp) || '<span class="lm-none">-</span>'}</td>
      <td>${apps(r.bonding, r.bonding_hist)}</td>
      <td>${apps(r.pull, r.pull_hist)}</td>
      <td class="s ${r.il}">${IL[r.il]}</td>
      <td class="lm-res ${r.level}">${r.result ? `<b>${esc(r.result)}</b>` : '<span class="lm-none">-</span>'}${notes ? `<small>${esc(notes)}</small>` : ''}</td>
      <td class="s ${r.erp}"><select data-id="${r.id}" aria-label="ERP status of ${esc(r.lab_ref)}">${erpSel}</select></td>
    </tr>`;
  };

  const renderTable = () => {
    const body = $('#lm-body');
    if (!S.rows.length) { body.innerHTML = `<tr><td colspan="${COLS()}" class="lm-empty">No lab references match.</td></tr>`; return; }
    const part = (side, title) => {
      const rs = S.rows.filter((r) => r.side === side);
      return rs.length ? `<tr class="lm-sec"><td colspan="${COLS()}">${title}</td></tr>${rs.map(rowHtml).join('')}` : '';
    };
    const other = S.rows.filter((r) => r.side !== 'R' && r.side !== 'L');
    body.innerHTML = part('R', 'RIGHT SHOES') + part('L', 'LEFT SHOES') + (other.length ? `<tr class="lm-sec"><td colspan="${COLS()}">OTHER</td></tr>${other.map(rowHtml).join('')}` : '');
  };

  // ---- Submit (bulk): checkboxes appear on the left of every row ----
  const eligible = () => S.rows.filter((r) => !r.shutdown && r.erp !== 'ok');
  const syncSelectUi = () => {
    $('#lm-table').classList.toggle('lm-selmode', S.select);
    const btn = $('#lm-submit'); const go = $('#lm-submit-go'); const cancel = $('#lm-submit-cancel');
    btn.hidden = S.select; go.hidden = !S.select; cancel.hidden = !S.select;
    go.textContent = S.sel.size ? `Submit ${S.sel.size} to ERP` : 'Submit to ERP';
    go.disabled = S.sel.size === 0;
    const all = $('#lm-pick-all'); const el = eligible();
    all.checked = el.length > 0 && el.every((r) => S.sel.has(r.id));
    all.indeterminate = !all.checked && el.some((r) => S.sel.has(r.id));
  };
  const setSelect = (on) => { S.select = on; if (!on) S.sel.clear(); renderTable(); syncSelectUi(); };

  const sendToErp = async (ids, mode) => {
    const r = await fetch(base + 'lab-monitoring/erp-submit', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf ? csrf.content : '' },
      body: JSON.stringify({ ids, mode, kind: S.kind }),
    });
    const j = await r.json(); if (csrf && j.csrf) csrf.content = j.csrf;
    if (!r.ok || !j.ok) throw new Error(j.error || 'Not sent');
    return j;
  };
  const queued = (j) => {
    if (j.invalid && j.invalid.length && window.nxToast) {   // not sent: what ERP needs is missing
      const first = j.invalid.slice(0, 3).map((x) => `${x.lab_ref_no}: ${x.missing.join(', ')}`).join(' | ');
      window.nxToast(`${j.invalid.length} report${j.invalid.length > 1 ? 's' : ''} not queued, missing data - ${first}${j.invalid.length > 3 ? ' ...' : ''}`, 'error', 14000);
    }
    if (!j.queued && j.invalid && j.invalid.length) { load(true); return; }
    if (j.queued && window.nxToast) {   // the report is now in the ERP PC's queue (or waiting for it)
      if (j.dispatch && j.dispatch.ok) window.nxToast(`Successfully submitted ${j.queued} report${j.queued > 1 ? 's' : ''} to ERP. They are entered one by one in the background; you will be told when each is completely sent.`, 'success', 8000);
      else window.nxToast(`${j.queued} report${j.queued > 1 ? 's' : ''} queued, but the ERP PC could not be reached yet${j.dispatch && j.dispatch.error ? ' (' + j.dispatch.error + ')' : ''}. They stay in the queue and are sent when it is back.`, 'error', 12000);
      load(true); loadReqs(); agentBadge(); return;
    }
    if (window.nxToast) window.nxToast(j.queued ? `${j.queued} report${j.queued > 1 ? 's' : ''} queued for ERP${j.skipped ? ` (${j.skipped} skipped: already sent or queued)` : ''}. You will be told when ${j.queued > 1 ? 'they are' : 'it is'} completely sent.` : 'Nothing to send: already sent or queued.', j.queued ? 'success' : 'info', 8000);
    load(true); loadReqs();
  };

  // ---- the read-only Shoe Bonding (U60) report of a lab reference, in a dialog ----
  const dlg = $('#lm-dlg'); const frame = $('#lm-frame'); let dlgRow = null; let lastFocus = null;
  const openDlg = (id) => {
    const r = S.rows.find((x) => x.id === id); if (!r) return;
    dlgRow = r; lastFocus = document.activeElement;
    $('#lm-dlg-title').textContent = r.lab_ref;
    $('#lm-dlg-meta').innerHTML = `<span class="lm-badge ${r.il}">InterLink: ${IL[r.il]}</span> <span class="lm-badge ${r.erp}">ERP: ${ERPL[r.erp]}</span>`;
    $('#lm-dlg-send').hidden = false;
    $('#lm-dlg-send').disabled = r.erp === 'ok';
    $('#lm-dlg-send').textContent = r.erp === 'ok' ? 'Already in ERP' : 'Submit to ERP';
    const ttl = root.querySelector('.lm-dlg-ttl small'); if (ttl) ttl.textContent = isWS() ? 'Whole Shoe report - read only' : 'Shoe Bonding (U60) - read only';
    $('#lm-dlg-load').hidden = false;
    frame.src = base + (isWS() ? 'dtr?open=wholeshoe&ref=' : 'dtr?open=bonding&ref=') + encodeURIComponent(r.lab_ref) + '&embed=1';
    dlg.hidden = false; document.body.classList.add('lm-noscroll');
    $('#lm-dlg-close').focus();
  };
  const closeDlg = () => { dlg.hidden = true; frame.src = 'about:blank'; document.body.classList.remove('lm-noscroll'); dlgRow = null; if (lastFocus && lastFocus.focus) lastFocus.focus(); };
  // the bonding page is the normal NEXUS-L page: inside the dialog only the report is kept, and nothing in it can be edited
  frame.addEventListener('load', () => {
    if (frame.src === 'about:blank' || !frame.src) return;
    try {
      const d = frame.contentDocument;
      const st = d.createElement('style');
      st.textContent = '.dtr-top-kpis,#dtr-nav-bar,.dtr-form-pickers,.side,.top,.skip-link,.backdrop,#nx-assistant,.nx-toasts,footer,.nx-fab,[class*="brain-fab"]{display:none!important}.shell{margin:0!important;padding:0!important}main{padding:10px!important;max-width:none!important}'
        + 'main input,main select,main textarea,main button,main a,main label,main [contenteditable]{pointer-events:none!important}main input,main select,main textarea{background:#f8fafc!important}';
      d.head.appendChild(st);
      d.body.setAttribute('inert', '');
      d.body.style.overflow = 'auto';
    } catch (e) { /* same origin: not expected to fail */ }
    setTimeout(() => { $('#lm-dlg-load').hidden = true; }, 1600);   // the form fills in asynchronously after load
  });

  const renderSummary = (m) => {
    $('#lm-s-total b').textContent = m.total;
    const set = (id, vals) => root.querySelectorAll(`${id} .lm-chip b`).forEach((b, i) => { b.textContent = vals[i]; });
    set('#lm-s-il', [m.il_ok, m.il_pending, m.il_bad]);
    set('#lm-s-erp', [m.erp_ok, m.erp_pending, m.erp_bad]);
    root.querySelectorAll('[data-f]').forEach((b) => {
      const f = b.dataset.f;
      const mine = f === '' ? S.status === '' : (f.startsWith('erp_') ? S.status === f : S.status === f || S.status === 'il_' + f);
      b.classList.toggle('on', S.status !== '' ? mine : false);
    });
  };

  const load = async (quiet) => {
    try {
      const p = new URLSearchParams({ kind: S.kind, month: S.date, q: S.q, side: S.side, status: S.status });
      const j = await get('lab-monitoring/data?' + p.toString());
      S.date = j.month; S.rows = j.rows;
      const sel = $('#lm-date');
      if (!sel.options.length || sel.dataset.n !== String(j.days.length)) {
        sel.innerHTML = j.days.map((d) => `<option value="${esc(d.d)}">${esc(monthName(d.d))} (${d.c})</option>`).join('');
        sel.dataset.n = String(j.days.length);
      }
      sel.value = S.date;
      renderSummary(j.summary);
      renderTable();
      syncSelectUi();
      $('#lm-note').textContent = `Updated ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
    } catch (e) {
      if (!quiet) $('#lm-body').innerHTML = `<tr><td colspan="${COLS()}" class="lm-empty">${esc(e.message)}</td></tr>`;
    }
  };

  // ---- the ERP submission log, followed live; the submitter gets a toast when a report is completely sent ----
  const known = new Map();
  let first = true;
  const loadReqs = async () => {
    try {
      const j = await get('lab-monitoring/erp-log');
      $('#lm-req').innerHTML = j.rows.length ? j.rows.map((r) => `<tr>
        <td>#${r.id}</td><td class="lm-lab">${esc(r.lab_ref_no)}</td><td class="lm-num">${esc(fmtDay(r.production_date || ''))}</td>
        <td>${r.mode === 'bulk' ? 'Bulk' : 'Single'}</td><td class="lm-num">${esc(r.batch_id) || '-'}</td>
        <td><span class="lm-badge ${r.state}">${esc(r.status)}</span></td><td>${esc(r.message) || '-'}</td><td>${esc(r.requested_by_name)}</td><td class="lm-num">${esc(r.created_at)}</td></tr>`).join('')
        : '<tr><td colspan="9" class="lm-empty">Nothing has been sent to ERP yet.</td></tr>';
      const done = new Map();   // per batch, so a bulk send gives one toast, not one per report
      j.rows.forEach((r) => {
        const was = known.get(r.id);
        if (!first && was && was !== r.state && (r.state === 'ok' || r.state === 'bad') && Number(r.requested_by_id) === ME) {
          const k = (r.batch_id || 'one-' + r.id) + r.state;
          const c = done.get(k) || { n: 0, r }; c.n++; done.set(k, c);
        }
        known.set(r.id, r.state);
      });
      done.forEach(({ n, r }) => {
        if (!window.nxToast) return;
        const what = n > 1 ? `${n} reports` : `Report ${r.lab_ref_no}`;
        if (r.state === 'ok') window.nxToast(`${what} completely sent to ERP.`, 'success', 9000);
        else window.nxToast(`${what} not sent to ERP${r.message ? ': ' + r.message : ''}.`, 'error', 12000);
        load(true);
      });
      first = false;
    } catch (e) { /* the next poll tries again */ }
  };

  // ---- is the ERP PC (agent) reachable ----
  const agentBadge = async () => {
    const el = $('#lm-agent'); if (!el) return;
    try {
      const j = await get('lab-monitoring/agent-status');
      el.className = 'lm-agent ' + (j.online ? 'on' : 'off');
      el.textContent = j.online ? `ERP PC online${j.paused ? ' (paused)' : ''} - ${j.queued} waiting${j.working ? ', working on ' + j.working : ''}` : 'ERP PC offline';
      el.title = j.online ? `${j.where}. ${j.erp || ''}${j.save_in_erp === false ? ' REHEARSAL MODE: reports are filled and cancelled, not saved.' : ''}` : (j.error || j.where);
    } catch (e) { el.className = 'lm-agent off'; el.textContent = 'ERP PC offline'; }
  };

  // ---- events ----
  $('#lm-date').addEventListener('change', (e) => { S.date = e.target.value; load(); });
  $('#lm-refresh').addEventListener('click', () => { load(); loadReqs(); });
  $('#lm-q').addEventListener('input', (e) => { clearTimeout(S.timer); S.timer = setTimeout(() => { S.q = e.target.value.trim(); load(); }, 250); });
  root.querySelector('.lm-seg').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-side]'); if (!b) return;
    S.side = b.dataset.side; root.querySelectorAll('.lm-seg button').forEach((x) => x.classList.toggle('on', x === b)); load();
  });
  $('#lm-sum').addEventListener('click', (e) => {
    const b = e.target.closest('[data-f]'); if (!b) return;
    const f = b.dataset.f;
    const key = f === '' ? '' : (f.startsWith('erp_') ? f : f);
    S.status = S.status === key ? '' : key;
    load();
  });
  $('#lm-body').addEventListener('change', async (e) => {
    const sel = e.target.closest('select[data-id]'); if (!sel) return;
    try {
      const r = await fetch(base + 'lab-monitoring/erp-status', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf ? csrf.content : '' },
        body: JSON.stringify({ id: Number(sel.dataset.id), status: sel.value }),
      });
      const j = await r.json(); if (csrf && j.csrf) csrf.content = j.csrf;
      if (!r.ok || !j.ok) throw new Error(j.error || 'Not saved');
      load(true);
    } catch (x) { if (window.nxToast) window.nxToast(x.message, 'error', 6000); load(true); }
  });

  // rows
  $('#lm-body').addEventListener('click', (e) => {
    if (e.target.closest('tr.lm-editing')) return;   // typing in a row: it does not open the report
    if (e.target.closest('select, input, a, button')) {
      const pick = e.target.closest('input[data-pick]');
      if (pick) { const id = Number(pick.dataset.pick); if (pick.checked) S.sel.add(id); else S.sel.delete(id); syncSelectUi(); }
      return;
    }
    const tr = e.target.closest('tr.lm-click'); if (!tr) return;
    openDlg(Number(tr.dataset.id));
  });
  // Edit / Save / Cancel of a row
  const postSave = async (id, fields) => {
    const r = await fetch(base + 'lab-monitoring/row-save', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf ? csrf.content : '' },
      body: JSON.stringify({ id, kind: S.kind, fields }),
    });
    const j = await r.json(); if (csrf && j.csrf) csrf.content = j.csrf;
    if (!r.ok || !j.ok) throw new Error(j.error || 'Not saved');
    return j;
  };
  $('#lm-body').addEventListener('click', async (e) => {
    const ed = e.target.closest('[data-edit]');
    if (ed) { S.edit = Number(ed.dataset.edit); renderTable(); const f = $('tr.lm-editing .lm-in'); if (f) f.focus(); return; }
    if (e.target.closest('[data-cancel]')) { S.edit = 0; renderTable(); return; }
    const sv = e.target.closest('[data-save]');
    if (!sv) return;
    const tr = sv.closest('tr'); const fields = {};
    tr.querySelectorAll('[data-ef]').forEach((el) => { fields[el.dataset.ef] = el.value; });
    sv.disabled = true;
    try {
      const j = await postSave(Number(sv.dataset.save), fields);
      S.edit = 0;
      if (window.nxToast) window.nxToast(j.saved && j.saved.length ? `Saved: ${j.saved.join(', ')}.` : 'Nothing changed.', 'success', 4000);
      load(true);
    } catch (x) { if (window.nxToast) window.nxToast(x.message, 'error', 8000); sv.disabled = false; }
  });
  $('#lm-body').addEventListener('keydown', (e) => {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr.lm-click')) { e.preventDefault(); openDlg(Number(e.target.dataset.id)); }
  });
  // dialog
  $('#lm-dlg-close').addEventListener('click', closeDlg);
  dlg.addEventListener('click', (e) => { if (e.target === dlg) closeDlg(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !dlg.hidden) closeDlg(); });
  $('#lm-dlg-send').addEventListener('click', async () => {
    if (!dlgRow) return;
    const b = $('#lm-dlg-send'); b.disabled = true;
    try { const j = await sendToErp([dlgRow.id], 'single'); queued(j); closeDlg(); } catch (x) { if (window.nxToast) window.nxToast(x.message, 'error', 7000); b.disabled = false; }
  });
  // bulk Submit
  $('#lm-submit').addEventListener('click', () => setSelect(true));
  $('#lm-submit-cancel').addEventListener('click', () => setSelect(false));
  $('#lm-submit-go').addEventListener('click', async () => {
    const ids = [...S.sel]; if (!ids.length) return;
    const b = $('#lm-submit-go'); b.disabled = true;
    try { const j = await sendToErp(ids, ids.length > 1 ? 'bulk' : 'single'); setSelect(false); queued(j); } catch (x) { if (window.nxToast) window.nxToast(x.message, 'error', 7000); b.disabled = false; }
  });

  // ---- Bonding / Whole Shoe tabs ----
  const paintTab = () => {
    $('#lm-thead').innerHTML = HEAD[S.kind];
    root.querySelectorAll('.lm-tab').forEach((b) => { const on = b.dataset.kind === S.kind; b.classList.toggle('on', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
    const sub = $('#lm-sub');
    if (sub) sub.textContent = isWS() ? 'Whole shoe lab references per production day: the TR numbers of the whole shoe tests (Flexing, Aging, Hydrolysis, Washing, UV Light) as the ERP keeps them, and the ERP status.' : 'Bonding lab references per production day: InterLink numbers and ERP status.';
    const pick = $('#lm-pick-all'); if (pick) pick.addEventListener('change', (e) => { eligible().forEach((r) => { if (e.target.checked) S.sel.add(r.id); else S.sel.delete(r.id); }); renderTable(); syncSelectUi(); });
  };
  root.querySelector('.lm-tabs').addEventListener('click', (e) => {
    const b = e.target.closest('.lm-tab'); if (!b || b.dataset.kind === S.kind) return;
    S.kind = b.dataset.kind; S.date = ''; S.status = ''; S.side = ''; S.q = ''; S.select = false; S.sel.clear(); S.rows = [];
    $('#lm-q').value = ''; root.querySelectorAll('.lm-seg button').forEach((x) => x.classList.toggle('on', x.dataset.side === ''));
    $('#lm-date').innerHTML = ''; $('#lm-date').dataset.n = '';
    try { localStorage.setItem('nxl:erp-kind', S.kind); } catch (x) { /* not saved */ }
    paintTab(); syncSelectUi(); $('#lm-body').innerHTML = `<tr><td colspan="${COLS()}" class="lm-empty">Loading...</td></tr>`; load();
  });
  paintTab();
  load();
  loadReqs();
  agentBadge();
  setInterval(() => { if (!document.hidden) agentBadge(); }, 15000);
  setInterval(() => { if (!document.hidden) loadReqs(); }, 15000);
  setInterval(() => { if (!document.hidden && !S.edit) load(true); }, 45000);
})();
