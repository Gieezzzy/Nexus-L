/**
 * InterLink "Batch Approval": every Application No. we have, the Lab Reference chosen for it (searchable list), a checkbox to pick
 * rows, Generate Request (makes the report PDFs), Request approval (sends the generated ones to the chosen approvers in one go),
 * a live approval status per row, an eye to view the document, and "Move to InterLink" which opens the application on the Upload Form,
 * syncs the Lab Reference and shows the signed report attached.
 */
(() => {
  const openBtn = document.getElementById('btn-batch-approval');
  if (!openBtn) return;
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const api = async (path, opts = {}) => {
    const headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, opts.headers || {});
    if (csrfMeta && csrfMeta.content) headers['X-CSRF-TOKEN'] = csrfMeta.content;
    const r = await fetch(BASE + path, Object.assign({}, opts, { credentials: 'same-origin', headers }));
    const j = await r.json().catch(() => ({}));
    if (j && j.csrf && csrfMeta) csrfMeta.content = j.csrf;
    if (!r.ok || j.ok === false) throw new Error(j.error || `Request failed (${r.status})`);
    return j;
  };
  const post = (path, body) => api(path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  const pdfAsB64 = async (path) => {
    const r = await fetch(BASE + path, { credentials: 'same-origin' });
    if (!r.ok) throw new Error((await r.text()).slice(0, 200) || 'The report PDF could not be made.');
    const buf = new Uint8Array(await r.arrayBuffer());
    let bin = '';
    for (let i = 0; i < buf.length; i += 0x8000) bin += String.fromCharCode.apply(null, buf.subarray(i, i + 0x8000));
    return btoa(bin);
  };
  const blobOf = (b64) => new Blob([Uint8Array.from(atob(b64), (c) => c.charCodeAt(0))], { type: 'application/pdf' });

  let rows = [];          // {application_no, report_no, style_no, vendor_name, lab_ref_no, approval, doc: base64 | null, sel, busy, note}
  let box = null;
  let timer = null;
  let labrefsLoaded = false;
  let labrefs = [];
  let ddFor = null;
  let page = 0;
  const PER = 15;
  const pageRows = () => rows.slice(page * PER, page * PER + PER);

  const STATUS = { approved: ['Approved', 'ok'], pending: ['Waiting for approval', 'wait'], rejected: ['Returned', 'bad'], expired: ['Link expired', 'bad'] };
  const statusHtml = (r) => {
    if (r.busy) return `<span class="bb-pill wait">${esc(r.busy)}</span>`;
    const a = r.approval;
    if (a) {
      const [t, c] = STATUS[a.status] || [a.status, 'wait'];
      const who = a.status === 'approved' ? ` · ${esc(a.decided_name)} ${esc(a.decided_at)}` : (a.status === 'rejected' ? ` · ${esc(a.remarks || '')}` : '');
      return `<span class="bb-pill ${c}">${t}</span><small>${who}</small>`;
    }
    return r.doc ? '<span class="bb-pill">Generated, not requested</span>' : (r.note ? `<small class="bb-err">${esc(r.note)}</small>` : '<small>—</small>');
  };
  const eye = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  const rowEl = (app) => box.querySelector(`tr[data-app="${CSS.escape(app)}"]`);
  const paintRow = (r) => {
    const tr = rowEl(r.application_no);
    if (!tr) return;
    tr.querySelector('.bb-st').innerHTML = statusHtml(r);
    tr.querySelector('.bb-view').innerHTML = (r.doc || (r.approval && r.approval.file)) ? `<button type="button" class="bb-eye" data-act="view" title="View the document">${eye}</button>` : '';
    tr.querySelector('.bb-mv').innerHTML = `<button type="button" class="il-btn il-btn-outline" data-act="move" ${r.lab_ref_no ? '' : 'disabled'}>Move to InterLink</button>`;
    tr.querySelector('.bb-sel').checked = !!r.sel;
  };
  const summary = () => {
    const n = rows.filter((r) => r.sel).length;
    box.querySelector('#bb-count').textContent = `${n} selected of ${rows.length}`;
  };
  const say = (t, bad) => { const m = box.querySelector('#bb-msg'); m.textContent = t || ''; m.className = bad ? 'bb-err' : ''; };

  const build = () => {
    box = document.createElement('div');
    box.id = 'bb-modal';
    box.innerHTML = `<div class="bb-card">
      <div class="bb-head"><b>Batch Approval</b><button type="button" data-act="close" aria-label="Close">×</button></div>
      <div class="bb-bar">
        <input type="search" id="bb-q" placeholder="Search Application No., style, vendor…">
        <button type="button" class="il-btn il-btn-outline" data-act="gen">Generate Request</button>
        <button type="button" class="il-btn" data-act="req">Request for approval</button>
        <span id="bb-count"></span>
      </div>
      <div id="bb-msg"></div>
      <div class="bb-scroll"><table class="bb-table"><thead><tr>
        <th><input type="checkbox" id="bb-all" title="Select all"></th><th>Application No.</th><th>Lab Reference</th><th>Approval status</th><th>View Doc</th><th>Move to InterLink</th>
      </tr></thead><tbody id="bb-body"><tr><td colspan="6">Loading…</td></tr></tbody></table></div>
      <div class="bb-bar bb-pager"><button type="button" class="il-btn il-btn-outline" data-act="prev">‹ Prev</button><span id="bb-page"></span><button type="button" class="il-btn il-btn-outline" data-act="next">Next ›</button></div>
      <div id="bb-dd" class="hidden"></div></div>`;
    document.body.appendChild(box);
    let t = null;
    box.querySelector('#bb-q').addEventListener('input', () => { clearTimeout(t); t = setTimeout(load, 350); });
    box.addEventListener('click', onClick);
    box.addEventListener('change', onChange);
    box.addEventListener('input', (e) => { if (e.target.classList.contains('bb-lab')) { const r = find(e.target.closest('tr')); r.lab_ref_no = e.target.value.trim(); r.doc = null; paintRow(r); openDd(e.target); } });
    box.addEventListener('focusin', (e) => { if (e.target.classList.contains('bb-lab')) openDd(e.target); });
    box.addEventListener('focusout', (e) => { if (e.target.classList.contains('bb-lab')) setTimeout(() => box.querySelector('#bb-dd').classList.add('hidden'), 150); });
    box.addEventListener('keydown', (e) => { if (e.key === 'Escape' && e.target.classList.contains('bb-lab')) box.querySelector('#bb-dd').classList.add('hidden'); });
    box.querySelector('#bb-dd').addEventListener('mousedown', (e) => {
      const it = e.target.closest('[data-v]');
      if (!it || !ddFor) return;
      e.preventDefault();
      const tr = ddFor.closest('tr'), r = find(tr);
      ddFor.value = r.lab_ref_no = it.dataset.v; r.doc = null; paintRow(r);
      box.querySelector('#bb-dd').classList.add('hidden');
    });
  };
  // searchable list under the Lab Reference box: matches the Lab Ref., TR No., style, material or type; first 40 shown
  const openDd = (inp) => {
    const dd = box.querySelector('#bb-dd');
    const q = inp.value.trim().toLowerCase();
    const hit = labrefs.filter((l) => !q || [l.lab_ref_no, l.tr_no, l.model_style, l.material_description, l.form_category].some((v) => String(v || '').toLowerCase().includes(q))).slice(0, 40);
    ddFor = inp;
    dd.innerHTML = hit.length ? hit.map((l) => `<div class="bb-opt" data-v="${esc(l.lab_ref_no)}"><b>${esc(l.lab_ref_no)}</b><small>${esc([l.form_category, l.tr_no, l.model_style || l.material_description, l.overall_result].filter(Boolean).join(' · '))}</small></div>`).join('') : '<div class="bb-opt"><small>No Lab Reference found</small></div>';
    const b = inp.getBoundingClientRect();
    dd.style.cssText = `left:${b.left}px;top:${b.bottom + 2}px;width:${Math.max(b.width, 320)}px`;
    dd.classList.remove('hidden');
  };
  const find = (tr) => rows.find((r) => r.application_no === tr.dataset.app);

  const render = () => {
    const body = box.querySelector('#bb-body');
    const pages = Math.max(1, Math.ceil(rows.length / PER));
    if (page >= pages) page = pages - 1;
    box.querySelector('#bb-page').textContent = `Page ${page + 1} of ${pages} (${rows.length} applications)`;
    body.innerHTML = pageRows().map((r) => `<tr data-app="${esc(r.application_no)}">
      <td><input type="checkbox" class="bb-sel"></td>
      <td><b>${esc(r.application_no)}</b><br><small>${esc([r.style_no, r.vendor_name].filter(Boolean).join(' · '))}</small></td>
      <td><input type="text" class="bb-lab" autocomplete="off" value="${esc(r.lab_ref_no)}" placeholder="Search / select Lab Ref."></td>
      <td class="bb-st"></td><td class="bb-view"></td><td class="bb-mv"></td></tr>`).join('') || '<tr><td colspan="6">No applications.</td></tr>';
    pageRows().forEach(paintRow);
    summary();
  };

  const load = async () => {
    try {
      const keep = new Map(rows.map((r) => [r.application_no, r]));
      const j = await api(`interlink/batch/data?q=${encodeURIComponent(box.querySelector('#bb-q').value.trim())}`);
      rows = (j.rows || []).map((r) => Object.assign(r, { doc: keep.get(r.application_no)?.doc || null, sel: !!keep.get(r.application_no)?.sel }, keep.get(r.application_no)?.lab_ref_no ? { lab_ref_no: keep.get(r.application_no).lab_ref_no } : {}));
      page = 0;
      render();
    } catch (e) { say('Could not load the applications: ' + e.message, true); }
    if (!labrefsLoaded) {
      try {
        const j = await api('interlink/labrefs?limit=1000');
        labrefs = j.rows || [];
        labrefsLoaded = true;
      } catch (e) { /* typing a Lab Reference still works */ }
    }
  };

  // the live status: every row that is waiting (or has no answer yet) is asked again every few seconds while this window is open
  const poll = async () => {
    if (!box || !box.isConnected) return;
    const shown = new Set(pageRows());
    const apps = rows.filter((r) => shown.has(r) || (r.approval && r.approval.status === 'pending')).map((r) => r.application_no);
    if (!apps.length) return;
    try {
      const j = await api(`interlink/batch/status?apps=${encodeURIComponent(apps.join(','))}`);
      rows.forEach((r) => {
        const a = (j.approvals || {})[r.application_no] || null;
        if (JSON.stringify(a) !== JSON.stringify(r.approval) && a) {
          const was = r.approval && r.approval.status;
          r.approval = a;
          if (a.lab_ref_no && !r.lab_ref_no) { r.lab_ref_no = a.lab_ref_no; const inp = rowEl(r.application_no)?.querySelector('.bb-lab'); if (inp) inp.value = a.lab_ref_no; }
          paintRow(r);
          if (was === 'pending' && a.status === 'approved') say(`${r.application_no} was approved by ${a.decided_name}.`);
        }
      });
    } catch (e) { /* tried again at the next tick */ }
  };

  // The report PDF of a Lab Reference: the same way the Upload Form's "Generate Report" makes it
  const makePdf = async (app, labRef) => {
    const det = await api(`interlink/labref-detail?lab_ref_no=${encodeURIComponent(labRef)}`);
    const lr = det.labref || {};
    if (!lr.form_slug || !(lr.source_id || lr.id)) throw new Error(`${labRef} has no Digital Test Report form to print.`);
    const isPull = lr.form_slug === 'pull' || lr.form_slug === 'pulltest' || /pull\s*test/i.test(lr.form_category || '') || /^DTP\d{8}(HP|EY|DR|RVT|LLH)/i.test(lr.lab_ref_no || '');
    const isWs = lr.form_slug === 'wholeshoe' || /whole\s*shoe/i.test(lr.form_category || '') || /^DTP\d{8}(WH|FX|HA|HY|WG|UV|DT)/i.test(lr.lab_ref_no || '');
    const b64 = (lr.form_slug === 'bonding' || isPull)
      ? await window.nxReportPdf({ slug: isPull ? 'pull' : 'bonding', id: lr.source_id || lr.id })
      : isWs ? await pdfAsB64(`dtr/wholeshoe-report-pdf?inline=1&lab_ref=${encodeURIComponent(lr.lab_ref_no)}`)
        : await pdfAsB64(`dtr/report-pdf?lab_ref=${encodeURIComponent(lr.lab_ref_no)}&app=${encodeURIComponent(app)}`);
    if (!b64 || b64.length < 3000 || !b64.startsWith('JVBER')) throw new Error('the report PDF came out empty');
    return b64;
  };

  const picked = () => rows.filter((r) => r.sel);

  const generate = async () => {
    const list = picked();
    if (!list.length) { say('Tick the applications first.', true); return; }
    let ok = 0;
    for (const r of list) {
      if (!r.lab_ref_no) { r.note = 'Choose a Lab Reference'; paintRow(r); continue; }
      if (r.approval && r.approval.status === 'approved') { r.note = ''; continue; }   // already signed
      r.busy = 'Generating…'; r.note = ''; paintRow(r);
      try { r.doc = await makePdf(r.application_no, r.lab_ref_no); ok++; } catch (e) { r.doc = null; r.note = e.message; }
      r.busy = ''; paintRow(r);
    }
    say(`${ok} of ${list.length} report(s) generated. Press Request for approval to send them.`, ok === 0);
  };

  const pickApprovers = async () => {
    const j = await api('dtr/approval/approvers');
    const people = j.rows || [];
    if (!people.length) return [];
    return new Promise((resolve) => {
      const m = document.createElement('div');
      m.className = 'bb-pick';
      m.innerHTML = `<div class="bb-card bb-small"><b>Request approval</b><p>Choose up to 2 approvers for all the selected reports.</p>
        ${people.map((p) => `<label><input type="checkbox" value="${p.id}"> <b>${esc(p.full_name)}</b> <small>${esc(p.title || '')}</small></label>`).join('')}
        <div id="bb-pm" class="bb-err"></div><div class="bb-bar"><button type="button" class="il-btn il-btn-outline" data-k="no">Cancel</button><button type="button" class="il-btn" data-k="yes">Send for approval</button></div></div>`;
      m.addEventListener('change', (e) => { if (m.querySelectorAll('input:checked').length > 2) { e.target.checked = false; m.querySelector('#bb-pm').textContent = 'At most 2 approvers.'; } });
      m.addEventListener('click', (e) => {
        const k = e.target.dataset.k;
        if (k === 'yes') { const ids = [...m.querySelectorAll('input:checked')].map((i) => +i.value); if (!ids.length) { m.querySelector('#bb-pm').textContent = 'Choose at least 1 approver.'; return; } m.remove(); resolve(ids); }
        if (k === 'no') { m.remove(); resolve(null); }
      });
      box.appendChild(m);
    });
  };

  const request = async () => {
    const list = picked().filter((r) => r.doc && !(r.approval && ['approved', 'pending'].includes(r.approval.status) && r.approval.status !== 'expired'));
    if (!list.length) { say('Nothing to send: tick applications that were generated (and are not already waiting or approved).', true); return; }
    let ids;
    try { ids = await pickApprovers(); } catch (e) { say('Could not load the approvers: ' + e.message, true); return; }
    if (ids === null) return;
    let ok = 0;
    for (const r of list) {
      r.busy = 'Sending…'; paintRow(r);
      try {
        const j = await post('dtr/approval/request', { lab_ref: r.lab_ref_no, application_no: r.application_no, pdf_b64: r.doc, sheet_html: '', replace: false, approver_ids: ids });
        const a = j.approval || {};
        r.approval = { status: a.status, lab_ref_no: a.lab_ref_no || r.lab_ref_no, requested_name: a.requested_name, requested_at: a.requested_at, decided_name: a.decided_name, decided_at: a.decided_at, remarks: a.remarks, file: a.file };
        r.note = ''; ok++;
      } catch (e) { r.note = e.message; }
      r.busy = ''; paintRow(r);
    }
    say(`${ok} of ${list.length} sent for approval. The status of each updates here as the approvers answer.`, ok === 0);
  };

  function onClick(e) {
    const act = e.target.closest('[data-act]')?.dataset.act;
    if (!act) { if (e.target === box) close(); return; }
    const tr = e.target.closest('tr[data-app]');
    const r = tr && find(tr);
    if (act === 'close') close();
    else if (act === 'prev' || act === 'next') { page = Math.max(0, page + (act === 'next' ? 1 : -1)); render(); }
    else if (act === 'gen') runOnce(e.target.closest('button'), generate);
    else if (act === 'req') runOnce(e.target.closest('button'), request);
    else if (act === 'view' && r) {
      if (r.doc) window.open(URL.createObjectURL(blobOf(r.doc)), '_blank');
      else if (r.approval && r.approval.file) window.open(r.approval.file, '_blank');
    } else if (act === 'move' && r) {
      close();
      window.nxMoveToInterlink && window.nxMoveToInterlink(r.application_no, r.lab_ref_no);
    }
  }
  function onChange(e) {
    if (e.target.id === 'bb-all') { pageRows().forEach((r) => { r.sel = e.target.checked; paintRow(r); }); summary(); }
    else if (e.target.classList.contains('bb-sel')) { find(e.target.closest('tr')).sel = e.target.checked; summary(); }
  }
  const runOnce = async (btn, fn) => {
    if (btn.disabled) return;
    btn.disabled = true;
    try { await fn(); } finally { btn.disabled = false; }
  };
  const close = () => { clearInterval(timer); box.classList.add('hidden'); };

  openBtn.addEventListener('click', () => {
    if (!box) build();
    box.classList.remove('hidden');
    say('');
    load();
    clearInterval(timer);
    timer = setInterval(poll, 5000);
  });
})();
