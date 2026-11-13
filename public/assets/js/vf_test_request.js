(() => {
  const root = document.getElementById('vfr-root');
  if (!root) return;
  const base = String(root.dataset.base || window.NEXUS_BASE || '/').replace(/\/?$/, '/');
  // every POST needs the CSRF token (the global filter no longer exempts interlink/*)
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const _fetch = window.fetch.bind(window);
  const fetch = (url, opt = {}) => {
    if (opt.method && String(opt.method).toUpperCase() !== 'GET') {
      opt = Object.assign({}, opt, { headers: Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, opt.headers || {}) });
    }
    return _fetch(url, opt).then((r) => { r.clone().json().then((j) => { if (j && j.csrf && csrfMeta) csrfMeta.content = j.csrf; }).catch(() => {}); return r; });
  };
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const toast = (m, t = 'info') => (window.nxToast ? window.nxToast(m, t, 6000) : alert(m));

  const pkgSel = document.getElementById('vfr-pkg');
  const qInp = document.getElementById('vfr-q');
  const reqBody = document.getElementById('vfr-req-body');
  const ilBody = document.getElementById('vfr-il-body');

  let state = { interlink_rows: [], requests: [] };

  const openModal = (id) => document.getElementById(id)?.classList.add('open');
  const closeModal = (id) => document.getElementById(id)?.classList.remove('open');
  document.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => closeModal(b.dataset.close)));

  const badgeClass = (st) => {
    const u = String(st || '').toUpperCase();
    if (u === 'COMPLETED' || u === 'SUBMITTED_INTERLINK' || u === 'EMAIL_SENT') return 'vfr-b-ok';
    if (u === 'SUBMITTING' || u === 'LAB_RECEIVED' || u === 'IN_TRANSIT') return 'vfr-b-sub';
    if (u === 'FAILED') return 'vfr-b-err';
    return 'vfr-b-req';
  };


  // ---- InterLink sign-in: if the service is up but the shared account is not signed in, open the sign-in browser (on the service PC) after 5 seconds
  const QC_ACCOUNT = 'dtp_qcasst@dtshoes.com';
  let signInStarted = false;
  async function serviceStatus() {
    try {
      const r = await fetch(`${base}interlink/service/status`, { credentials: 'same-origin' });
      return await r.json();
    } catch (e) { return null; }
  }
  const accountSignedIn = (st) => {
    const m = (st && st.accounts) || {};
    const a = m[QC_ACCOUNT] || Object.values(m).find((x) => String(x.account || x.email || '').toLowerCase() === QC_ACCOUNT);
    return !!(a && a.signedIn);
  };
  async function ensureInterlinkSignIn(delayMs) {
    const st = await serviceStatus();
    if (!st || !st.enabled) return { ready: false, reason: 'The InterLink background service is not enabled in Settings.' };
    if (!st.online) return { ready: false, reason: 'The InterLink service does not answer. Start NexusInterlinkService (and check the address in Settings > InterLink service).' };
    if (accountSignedIn(st)) return { ready: true };
    if (signInStarted) return { ready: false, reason: 'Waiting for the InterLink sign-in to finish.' };
    signInStarted = true;
    toast(`InterLink is not signed in as ${QC_ACCOUNT}. The sign-in browser opens in ${Math.round(delayMs / 1000)} seconds on the service computer - sign in there, then wait for Home.aspx.`, 'info');
    setTimeout(async () => {
      try {
        const r = await fetch(`${base}interlink/service/signin`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ account: QC_ACCOUNT }) });
        const j = await r.json().catch(() => ({}));
        toast(j.ok ? (j.message || 'InterLink sign-in browser opened.') : ('Could not open the sign-in browser: ' + (j.error || r.status)), j.ok ? 'info' : 'error');
      } catch (e) { toast('Could not open the sign-in browser: ' + e.message, 'error'); signInStarted = false; return; }
      let polls = 0;
      const iv = setInterval(async () => {
        polls += 1;
        const s2 = await serviceStatus();
        if (accountSignedIn(s2)) { clearInterval(iv); signInStarted = false; toast('InterLink sign-in complete. You can sync the grid now.', 'success'); }
        else if (polls >= 40) { clearInterval(iv); signInStarted = false; }
      }, 3000);
    }, delayMs);
    return { ready: false, reason: 'InterLink sign-in is opening - sign in, then press the button again.' };
  }
  ensureInterlinkSignIn(5000);

  const pg = { req: 1, il: 1 };
  const PG_SIZE = 25;
  function renderPager(id, key, total, rerender) {
    const el = document.getElementById(id);
    if (!el) return;
    const pages = Math.max(1, Math.ceil(total / PG_SIZE));
    if (pg[key] > pages) pg[key] = pages;
    if (total <= PG_SIZE) { el.innerHTML = ''; return; }
    const cur = pg[key], from = (cur - 1) * PG_SIZE + 1, to = Math.min(total, cur * PG_SIZE);
    const nums = [];
    for (let n = 1; n <= pages; n++) { if (n === 1 || n === pages || Math.abs(n - cur) <= 1) nums.push(n); else if (nums[nums.length - 1] !== '...') nums.push('...'); }
    el.innerHTML = `<span style="margin-right:auto;color:#64748b">${from}-${to} of ${total}</span>`
      + `<button type="button" class="vfr-btn sm" data-pg="${cur - 1}" ${cur <= 1 ? 'disabled' : ''}>&lsaquo; Prev</button>`
      + nums.map((n) => (n === '...' ? '<span>...</span>' : `<button type="button" class="vfr-btn sm ${n === cur ? 'primary' : ''}" data-pg="${n}">${n}</button>`)).join('')
      + `<button type="button" class="vfr-btn sm" data-pg="${cur + 1}" ${cur >= pages ? 'disabled' : ''}>Next &rsaquo;</button>`;
    el.querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { const n = Number(b.dataset.pg); if (n >= 1 && n <= pages) { pg[key] = n; rerender(); } }));
  }

  async function loadData() {
    const url = `${base}interlink/vf-test-request/data?package=${encodeURIComponent(pkgSel.value)}&q=${encodeURIComponent(qInp.value)}`;
    let r = null, j = null, failMsg = '';
    try {
      r = await fetch(url, { credentials: 'same-origin' });
      j = await r.json().catch(() => null);
      if (!r.ok || !j || !j.ok) failMsg = (j && (j.error || j.message)) || ('The server answered ' + r.status + (r.redirected ? ' (you may be signed out - reload the page)' : ''));
    } catch (e) { failMsg = 'Could not reach NEXUS-L: ' + e.message; }
    if (failMsg) {
      const row = '<tr><td colspan="14" style="text-align:center;padding:18px;color:#dc2626">Could not load: ' + esc(failMsg) + '</td></tr>';
      reqBody.innerHTML = row;
      if (typeof ilBody !== 'undefined' && ilBody) ilBody.innerHTML = row;
      return;
    }
    state = j;

    // submitted rows leave "Incoming" and are listed below the InterLink list, with a status based on the e-mail
    const SENT_STAGES = ['SUBMITTED_INTERLINK', 'EMAIL_SENT', 'IN_TRANSIT', 'LAB_RECEIVED', 'COMPLETED'];
    const allReq = j.requests;
    const stg = (it) => String(it.stage || '').toUpperCase();
    const isDone = (it) => SENT_STAGES.includes(stg(it)) && (it.generated_application_no || '');
    // the sent list also shows what is being sent now and what failed (a failed request stays in Incoming too, so it can be tried again)
    j.sent = allReq.filter((it) => isDone(it) || stg(it) === 'SUBMITTING' || stg(it) === 'FAILED');
    j.requests = allReq;   // the tree lists everything that was asked, finished or not
    document.getElementById('vfr-il-count').textContent = `(${j.interlink_rows.length} record(s))`;

    // how far a request went: finished things are marked, so nothing is asked twice
    const procLabel = (it) => {
      const u = stg(it);
      if (u === 'COMPLETED') return ['Completed' + (it.lab_report_no ? ' - ' + it.lab_report_no : ''), 'vfr-b-ok', true];
      if (u === 'LAB_RECEIVED') return ['Lab received', 'vfr-b-sub', true];
      if (u === 'IN_TRANSIT') return ['In transit' + (it.container_no ? ' - ' + it.container_no : ''), 'vfr-b-sub', true];
      if (u === 'EMAIL_SENT') return ['Submitted + e-mailed ' + (it.generated_application_no || ''), 'vfr-b-ok', true];
      if (u === 'SUBMITTED_INTERLINK') return ['Submitted ' + (it.generated_application_no || ''), 'vfr-b-ok', true];
      if (u === 'SUBMITTING') return ['Sending to InterLink...', 'vfr-b-sub', false];
      if (u === 'FAILED') return ['Failed', 'vfr-b-err', false];
      if (it.il_app) return ['Already in InterLink: ' + it.il_app + (it.il_status ? ' (' + it.il_status + (it.il_result ? ', ' + it.il_result : '') + ')' : ''), 'vfr-b-ok', true];
      if (Number(it.in_history)) return ['In history (asked before)', 'vfr-b-req', false];
      return ['Waiting - not submitted yet', 'vfr-b-req', false];
    };
    // ---- Incoming requests grouped by e-mail (sender): the e-mail is the parent row, its requested items are the children
    const batchById = {};
    (j.batches || []).forEach((b) => { batchById[String(b.id)] = b; });
    const groups = [];
    const gIndex = {};
    const reqFilter = (document.getElementById('vfr-req-filter') || {}).value || 'all';
    j.requests.filter((it) => reqFilter === 'all' || (reqFilter === 'done' ? procLabel(it)[2] : !procLabel(it)[2])).forEach((it) => {
      const k = String(it.batch_id || 0);
      if (!(k in gIndex)) { gIndex[k] = groups.length; groups.push({ id: k, batch: batchById[k] || null, items: [] }); }
      groups[gIndex[k]].items.push(it);
    });
    const gTime = (g) => String((g.batch && g.batch.received_at) || (g.items[0] && g.items[0].created_at) || '');
    groups.sort((x, y) => gTime(y).localeCompare(gTime(x)));
    if (!window.__vfrOpenInit) { window.__vfrOpenInit = true; window.__vfrOpen = new Set(groups.filter((g) => g.items.some((i) => !procLabel(i)[2])).slice(0, 3).map((g) => g.id)); }
    const nDone = j.requests.filter((it) => procLabel(it)[2]).length;
    document.getElementById('vfr-req-count').textContent = `(${groups.length} e-mail(s), ${j.requests.length} request(s): ${j.requests.length - nDone} to do, ${nDone} finished)`;

    const kidRow = (it) => `
      <tr class="vfr-item" data-id="${it.id}" style="${procLabel(it)[2] ? 'opacity:.78' : ''}" title="Open Copy as New for this request">
        <td><span class="vfr-doc">&#9998;</span></td>
        <td>${esc(it.factory)}</td>
        <td class="mono"><b>${esc(it.prod_code)}</b>${Number(it.erp_ambiguous) ? ' <span class="vfr-badge vfr-b-req" title="MTL# has multiple Prod Codes in ERP">Pick Prod</span>' : (!Number(it.erp_matched) ? ' <span class="vfr-badge vfr-b-err" title="Prod Code not matched in ERP">No ERP</span>' : '')}</td>
        <td>${esc(it.style_no)}</td>
        <td class="mono"><b>${esc(it.mtl_no)}</b></td>
        <td>${esc(it.colorway_c || it.colorway)}</td>
        <td>${esc(it.crd)}</td>
        <td>${esc(it.email_state)}</td>
        <td>${(() => { const [lb, cl] = procLabel(it); return `<span class="vfr-badge ${cl}">${esc(lb)}</span>`; })()}${stg(it) === 'FAILED' && it.interlink_message ? `<div class="vfr-sub" style="color:#b91c1c;max-width:260px;white-space:normal">${esc(it.interlink_message)}</div>` : ''}</td>
      </tr>`;
    const renderReq = () => {
      const sl = groups.slice((pg.req - 1) * PG_SIZE, pg.req * PG_SIZE);
      reqBody.innerHTML = groups.length ? sl.map((g) => {
        const b = g.batch || {};
        const sender = b.sender_name || b.sender_email || (b.source_channel === 'manual_paste' ? 'Pasted table' : 'E-mail #' + g.id);
        const open = window.__vfrOpen.has(g.id);
        const pkgName = b.test_package || (g.items[0] && g.items[0].test_package) || '';
        const needsReview = Number(b.package_needs_review) || g.items.some((i) => Number(i.package_needs_review));
        const failed = g.items.filter((i) => stg(i) === 'FAILED').length;
        return `
        <tr class="vfr-grp ${open ? 'open' : ''}" data-g="${esc(g.id)}">
          <td><span class="vfr-chev">&#9656;</span>${esc(sender)}${b.sender_email && b.sender_name ? ` <span class="vfr-sub">&lt;${esc(b.sender_email)}&gt;</span>` : ''}</td>
          <td>${esc(String(b.subject || '').slice(0, 90))}</td>
          <td>${esc(pkgName)}${needsReview ? ' <span class="vfr-badge vfr-b-req" title="The subject did not state the Test Package; check it before submitting">Review Pkg</span>' : ''}</td>
          <td>${esc(String(b.received_at || gTime(g)).slice(0, 16))}</td>
          <td>${(() => { const done = g.items.filter((i) => procLabel(i)[2]).length; return `<span class="vfr-badge vfr-b-ok">${done} finished</span> <span class="vfr-badge vfr-b-req">${g.items.length - done} to do</span>`; })()}<span class="vfr-cnt" title="requests in this e-mail">${g.items.length}</span>${failed ? ` <span class="vfr-badge vfr-b-err">${failed} failed</span>` : ''}</td>
        </tr>
        <tr class="vfr-kidwrap ${open ? 'open' : ''}" data-kids="${esc(g.id)}"><td colspan="5">
          <table class="vfr-kids"><thead><tr><th></th><th>Factory</th><th>Prod Code (指令編號)</th><th>Style (型體代號)</th><th>MTL#</th><th>Color</th><th>CRD (DTP交期)</th><th>State</th><th>Processing</th></tr></thead>
          <tbody>${g.items.map(kidRow).join('')}</tbody></table>
        </td></tr>`;
      }).join('') : '<tr><td colspan="5" class="muted" style="text-align:center;padding:18px">No requested items yet. Click "Sync Outlook Emails" or "Paste / Import Email Table".</td></tr>';
    };
    renderReq(); renderPager('vfr-req-pager', 'req', groups.length, renderReq);

    const ilLabel = (it) => {
      const u = String(it.stage || '').toUpperCase();
      if (u === 'SUBMITTING') return ['Sending to InterLink...', 'vfr-b-sub'];
      if (u === 'FAILED') return ['Failed', 'vfr-b-err'];
      if (it.pdf_capture_warning) return ['Submitted - PDF missing', 'vfr-b-req'];
      return ['Submitted', 'vfr-b-ok'];
    };
    const sentLabel = (it) => {
      const u = String(it.stage || '').toUpperCase();
      if (u === 'SUBMITTING' || u === 'FAILED') return ['-', 'vfr-b-req'];
      if (u === 'COMPLETED') return ['Completed', 'vfr-b-ok'];
      if (u === 'LAB_RECEIVED') return ['Received by lab', 'vfr-b-sub'];
      if (u === 'IN_TRANSIT') return ['In transit' + (it.container_no ? ' - ' + it.container_no : ''), 'vfr-b-sub'];
      if (u === 'EMAIL_SENT' || Number(it.batch_email_sent)) return ['E-mail sent' + (it.batch_email_sent_at ? ' ' + String(it.batch_email_sent_at).slice(0, 16) : ''), 'vfr-b-ok'];
      return ['Waiting to e-mail', 'vfr-b-req'];
    };
    const sentBody = document.getElementById('vfr-sent-body');
    if (sentBody) {
      document.getElementById('vfr-sent-count').textContent = `(${j.sent.length} item(s))`;
      sentBody.innerHTML = j.sent.length ? j.sent.map((it) => { const [lb, cl] = sentLabel(it); const [il, ic] = ilLabel(it); const failed = stg(it) === 'FAILED'; return `
        <tr>
          <td class="mono"><b>${esc(it.generated_application_no || it.tr_no || '-')}</b></td>
          <td class="mono">${esc(it.prod_code)}</td>
          <td>${esc(it.style_no)}</td>
          <td class="mono">${esc(it.mtl_no)}</td>
          <td>${esc(it.test_package)}</td>
          <td>${esc(String(it.submitted_at || '').slice(0, 16))}</td>
          <td>${it.etrf_pdf_name ? esc(it.etrf_pdf_name) : '<span class="muted">-</span>'}</td>
          <td><span class="vfr-badge ${ic}">${esc(il)}</span>${failed || it.pdf_capture_warning ? `<div style="font-size:11px;color:#b91c1c;margin-top:3px;max-width:320px;white-space:normal">${esc(failed ? it.interlink_message : it.pdf_capture_warning)}</div>` : ''}${failed ? ` <button type="button" class="vfr-btn sm vfr-prepare" data-id="${it.id}" style="margin-top:3px">Try again</button>` : ''}</td>
          <td><span class="vfr-badge ${cl}">${esc(lb)}</span></td>
        </tr>`; }).join('') : '<tr><td colspan="9" class="muted" style="text-align:center;padding:18px">Nothing submitted yet.</td></tr>';
    }

    const renderIl = () => { const sl = j.interlink_rows.slice((pg.il - 1) * PG_SIZE, pg.il * PG_SIZE); ilBody.innerHTML = j.interlink_rows.length ? sl.map((r0) => `
      <tr>
        <td><button type="button" class="vfr-link vfr-app-open" data-app="${esc(r0.application_no)}">${esc(r0.application_no)}</button></td>
        <td>${esc(r0.test_packages)}</td>
        <td>${esc(r0.job_status)}</td>
        <td>${esc(r0.overall_result)}</td>
        <td class="mono">${esc(r0.style_no)}</td>
        <td class="mono">${esc(r0.style_color_no)}</td>
        <td>${esc(r0.product_name)}</td>
        <td>${esc(r0.colorway_name)}</td>
        <td>${esc(r0.season)}</td>
        <td>${esc(r0.trf_submission_date)}</td>
        <td class="mono">${esc(r0.report_no)}</td>
      </tr>
    `).join('') : '<tr><td colspan="11" class="muted" style="text-align:center;padding:18px">No InterLink Applicant rows for selected Test Package yet. Click "Sync InterLink Applicant Grid".</td></tr>'; };
    // InterLink rows are shown only while the InterLink Service is connected and signed in; otherwise just the connection error
    const stIl = await serviceStatus();
    if (!(stIl && stIl.enabled && stIl.online && accountSignedIn(stIl))) {
      const why = !stIl || !stIl.enabled ? 'the service is not set up (Settings > InterLink service)' : (!stIl.online ? 'the service does not answer at the address in Settings > InterLink service' : 'the service is not signed in to InterLink');
      ilBody.innerHTML = '<tr><td colspan="11" class="muted" style="text-align:center;padding:18px"><b style="color:#dc2626">Connection to InterLink Service error</b> - ' + esc(why) + '. No InterLink data is shown until it is connected.</td></tr>';
      document.getElementById('vfr-il-count').textContent = '(not connected)';
      const ilp = document.getElementById('vfr-il-pager'); if (ilp) ilp.innerHTML = '';
    } else {
      if (live) renderLive(); else { renderIl(); renderPager('vfr-il-pager', 'il', j.interlink_rows.length, renderIl); }
    }
  }

  // ---- InterLink list = ONE InterLink page at a time, read live through the service (never every page) ----
  let live = null, liveBusy = false;
  const g = (o, k) => String((o && o[k]) || '');
  function renderLive() {
    const ilBody2 = document.getElementById('vfr-il-body') || ilBody;
    const rows = live.rows;
    ilBody2.innerHTML = rows.length ? rows.map((r0) => `
      <tr>
        <td><button type="button" class="vfr-link vfr-app-open" data-app="${esc(g(r0, 'Application No.'))}">${esc(g(r0, 'Application No.'))}</button></td>
        <td>${esc(g(r0, 'Test Package(s)'))}</td><td>${esc(g(r0, 'Job Status'))}</td><td>${esc(g(r0, 'Overall Result'))}</td>
        <td class="mono">${esc(g(r0, 'Style No.'))}</td><td class="mono">${esc(g(r0, 'SKU No.') || g(r0, 'Product ID'))}</td>
        <td>${esc(g(r0, 'Product Name'))}</td><td>${esc(g(r0, 'Color Description'))}</td><td>${esc(g(r0, 'Season'))}</td>
        <td>${esc(g(r0, 'Online TRF Submission Date'))}</td><td class="mono">${esc(g(r0, 'Report No.'))}</td>
      </tr>`).join('') : '<tr><td colspan="11" class="muted" style="text-align:center;padding:18px">InterLink has no matching rows on this page.</td></tr>';
    document.getElementById('vfr-il-count').textContent = `(page ${live.page}${live.pages ? ' of ' + live.pages : ''}, ${rows.length} record(s)${live.items ? ' - ' + live.items + ' in InterLink' : ''}${live.age != null ? ' - updated ' + (live.age < 90 ? live.age + 's' : Math.round(live.age / 60) + ' min') + ' ago' : ''}${live.refreshing ? ' - refreshing...' : ''})`;
    const el = document.getElementById('vfr-il-pager'); if (!el) return;
    const cur = live.page, pages = live.pages || cur;
    const nums = [];
    for (let n = 1; n <= pages; n++) { if (n === 1 || n === pages || Math.abs(n - cur) <= 1) nums.push(n); else if (nums[nums.length - 1] !== '...') nums.push('...'); }
    el.innerHTML = `<span style="margin-right:auto;color:#64748b">InterLink page ${cur}${pages > 1 ? ' of ' + pages : ''}</span>`
      + `<button type="button" class="vfr-btn sm" data-lp="${cur - 1}" ${cur <= 1 ? 'disabled' : ''}>&lsaquo; Prev</button>`
      + nums.map((n) => (n === '...' ? '<span>...</span>' : `<button type="button" class="vfr-btn sm ${n === cur ? 'primary' : ''}" data-lp="${n}">${n}</button>`)).join('')
      + `<button type="button" class="vfr-btn sm" data-lp="${cur + 1}" ${cur >= pages ? 'disabled' : ''}>Next &rsaquo;</button>`;
    el.querySelectorAll('[data-lp]').forEach((b) => b.addEventListener('click', () => { const n = Number(b.dataset.lp); if (n >= 1 && n <= pages) liveLookup(n, true); }));
  }
  let liveSeq = 0;
  // Reads the SAVED copy of one InterLink page from NEXUS-L (shown at once). When a copy is old NEXUS-L asks the service for ONE refresh
  // (shared by every user) and this page keeps asking until it is done. "All 4 VF Packages" = the same page of each package, merged
  // (InterLink filters one package at a time; its unfiltered list is mostly other packages). A newer search / page replaces this one.
  async function liveLookup(page, announce, fresh) {
    const my = ++liveSeq;
    liveBusy = true;
    const ilEl = document.getElementById('vfr-il-body') || ilBody;
    const want = page || 1;
    const q = qInp.value.trim();
    const pkgs = pkgSel.value ? [pkgSel.value] : [...pkgSel.options].map((o) => o.value).filter(Boolean);
    const merged = pkgs.length > 1;
    const parts = {};
    const draw = () => {
      const got = Object.values(parts).filter((x) => x && x.hit);
      if (!got.length) return false;
      const rows = got.flatMap((x) => x.rows || []).sort((a, b) => String(b['Application No.'] || '').localeCompare(String(a['Application No.'] || '')));
      live = { rows, page: want, pages: Math.max(...got.map((x) => x.pages || 0)), items: got.reduce((n, x) => n + (x.items || 0), 0), age: Math.max(...got.map((x) => x.age || 0)), refreshing: Object.values(parts).some((x) => x && x.refreshing) };
      renderLive();
      return true;
    };
    try {
      await Promise.all(pkgs.map(async (pkg) => {
        for (let i = 0; i < 90; i++) {
          const qs = new URLSearchParams({ package: pkg, q, page: String(want), fresh: (i === 0 && fresh) ? '1' : '0' });
          const j = await fetch(`${base}interlink/vf-test-request/grid?${qs}`, { credentials: 'same-origin' }).then((x) => x.json()).catch(() => null);
          if (my !== liveSeq) return;
          if (!j || !j.ok) { if (announce && !merged) toast('Could not read the InterLink list.', 'error'); return; }
          if (Number(j.req_page) !== want) return;               // never show another page's rows
          parts[pkg] = j;
          if (!draw() && Object.values(parts).length && !Object.values(parts).some((x) => x && x.refreshing)) {
            live = { rows: [], page: want, pages: 0, items: 0, age: null, refreshing: false }; renderLive();
          } else if (!live || !Object.values(parts).some((x) => x && x.hit)) {
            ilEl.innerHTML = '<tr><td colspan="11" class="muted" style="text-align:center;padding:18px">Reading InterLink page ' + want + (q ? ' for ' + esc(q) : '') + '...</td></tr>';
          }
          if (!j.refreshing) { if (j.error && !merged && (announce || !j.hit)) toast(j.error, 'error'); return; }
          await new Promise((res) => setTimeout(res, 2000));
        }
      }));
    } finally { if (my === liveSeq) liveBusy = false; }
  }

  pkgSel.addEventListener('change', () => { pg.req = 1; pg.il = 1; live = null; loadData().then(() => liveLookup(1, false)); });
  let qTmr = null, lTmr = null;
  qInp.addEventListener('input', () => { clearTimeout(qTmr); qTmr = setTimeout(() => { pg.req = 1; pg.il = 1; loadData(); }, 250); clearTimeout(lTmr); lTmr = setTimeout(() => { live = null; liveLookup(1, false); }, 1200); });

  // Open detail modal on click of Application No.
  ilBody.addEventListener('click', async (e) => {
    const btn = e.target.closest('.vfr-app-open');
    if (!btn) return;
    const appNo = btn.dataset.app;
    const r = await fetch(`${base}interlink/vf-test-request/detail/${encodeURIComponent(appNo)}`, { credentials: 'same-origin' });
    const j = await r.json().catch(() => null);
    if (!j || !j.ok) { toast((j && j.error) || 'Failed to load application details', 'error'); return; }
    const rep = j.report;
    document.getElementById('vfr-det-app').textContent = rep.application_no;
    const pairs = [
      ['Test Package(s)', rep.test_packages],
      ['Service Test Type', rep.service_test_type],
      ['Service Provider', rep.service_provider],
      ['Job Status', rep.job_status],
      ['Company', rep.company_name],
      ['Factory', rep.factory_name],
      ['Style No.', rep.style_no],
      ['Style Color No.', rep.style_color_no],
      ['Product Name', rep.product_name],
      ['Color Description', rep.colorway_name],
      ['Season', rep.season],
      ['Cargo Receive Date', rep.cargo_receive_date],
    ];
    document.getElementById('vfr-det-kv').innerHTML = pairs.map(([k, v]) => `<div><small>${esc(k)}</small><b>${esc(v || '-')}</b></div>`).join('');
    document.getElementById('vfr-det-copy').dataset.app = rep.application_no;
    document.getElementById('vfr-det-copy').dataset.pkg = rep.test_packages || '';
    openModal('vfr-detail-modal');
  });

  document.getElementById('vfr-det-copy').addEventListener('click', (e) => {
    closeModal('vfr-detail-modal');
    openCopyModal({ srcApp: e.currentTarget.dataset.app || '', pkg: e.currentTarget.dataset.pkg || pkgSel.value || '' });
  });

  document.getElementById('vfr-direct-copy').addEventListener('click', () => {
    const firstIl = (state.interlink_rows || [])[0];
    openCopyModal({ srcApp: firstIl ? firstIl.application_no : '', pkg: pkgSel.value || (firstIl ? firstIl.test_packages : 'CPSIA Product Testing Package') });
  });

  const reqFilterEl = document.getElementById('vfr-req-filter');
  if (reqFilterEl) reqFilterEl.addEventListener('change', () => { pg.req = 1; loadData(); });
  const onReqClick = (e) => {
    const grp = e.target.closest('tr.vfr-grp');
    if (grp) {
      const id = grp.dataset.g;
      const open = !grp.classList.contains('open');
      grp.classList.toggle('open', open);
      const kids = reqBody.querySelector(`tr[data-kids="${CSS.escape(id)}"]`);
      if (kids) kids.classList.toggle('open', open);
      if (open) window.__vfrOpen.add(id); else window.__vfrOpen.delete(id);
      return;
    }
    const btn = e.target.closest('.vfr-prepare, tr.vfr-item');
    if (!btn) return;
    const item = (state.requests || []).find((x) => String(x.id) === btn.dataset.id);
    if (!item) return;
    const matchIl = (state.interlink_rows || []).find((x) => String(x.test_packages || '').toLowerCase().includes(String(item.test_package || '').toLowerCase().slice(0, 4)));
    openCopyModal({
      requestId: item.id,
      mtl: item.mtl_no || '',
      prodCode: item.prod_code || '',
      pkg: item.test_package || pkgSel.value || 'CPSIA Product Testing Package',
      srcApp: item.source_application_no || (matchIl ? matchIl.application_no : ''),
    });
  };
  reqBody.addEventListener('click', onReqClick);
  document.getElementById('vfr-sent-body').addEventListener('click', onReqClick);

  // ERP bidirectional lookup inside Copy as New Modal
  const cMtl = document.getElementById('c-mtl');
  const cProd = document.getElementById('c-prod');
  const cProdPickWrap = document.getElementById('c-prod-pick-wrap');
  const cProdPick = document.getElementById('c-prod-pick');
  const cMtlPickWrap = document.getElementById('c-mtl-pick-wrap');
  const cMtlPick = document.getElementById('c-mtl-pick');
  const cErpHint = document.getElementById('c-erp-hint');

  async function refreshErpList(q = '') {
    const r = await fetch(`${base}interlink/vf-test-request/erp-lookup?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
    const j = await r.json().catch(() => null);
    if (!j || !j.ok) return;
    document.getElementById('c-mtl-list').innerHTML = (j.mtl_options || []).map((m) => `<option value="${esc(m.mtl_no)}">${esc(m.style_no)} | ${esc(m.colorway)} (${m.cnt} order)</option>`).join('');
    document.getElementById('c-prod-list').innerHTML = (j.prod_options || []).map((p) => `<option value="${esc(p.prod_code)}">${esc(p.mtl_no)} | PO:${esc(p.po_no)} | CRD:${esc(p.crd)}</option>`).join('');
  }

  async function lookupByMtl() {
    const val = cMtl.value.trim();
    cProdPickWrap.style.display = 'none';
    if (val.length < 3) return;
    const r = await fetch(`${base}interlink/vf-test-request/erp-lookup?mtl=${encodeURIComponent(val)}`, { credentials: 'same-origin' });
    const j = await r.json().catch(() => null);
    if (!j || !j.ok) return;
    const opts = j.prod_options_for_mtl || [];
    if (opts.length > 1) {
      cProdPickWrap.style.display = '';
      cProdPick.innerHTML = `<option value="">-- Choose 1 of ${opts.length} Prod Codes --</option>` +
        opts.map((o) => `<option value="${esc(o.prod_code)}">${esc(o.prod_code)} | PO:${esc(o.po_no)} | CRD:${esc(o.crd)} | ${esc(o.country)}</option>`).join('');
      if (cProd.value.trim()) cProdPick.value = cProd.value.trim();
      cErpHint.textContent = `MTL# ${val} has ${opts.length} Prod Codes in dberp.production_orders. Choose one.`;
    } else if (opts.length === 1) {
      if (!cProd.value.trim()) cProd.value = opts[0].prod_code;
      cErpHint.textContent = `Matched ERP Order: ${opts[0].prod_code} | PO ${opts[0].po_no} | CRD ${opts[0].crd}`;
    } else {
      cErpHint.textContent = `No exact ERP row found for MTL# ${val}.`;
    }
  }

  async function lookupByProd() {
    const val = cProd.value.trim();
    cMtlPickWrap.style.display = 'none';
    if (val.length < 3) return;
    const r = await fetch(`${base}interlink/vf-test-request/erp-lookup?prod_code=${encodeURIComponent(val)}`, { credentials: 'same-origin' });
    const j = await r.json().catch(() => null);
    if (!j || !j.ok) return;
    const mtls = j.mtl_options_for_prod || [];
    const orders = j.orders_by_prod || [];
    if (mtls.length > 1) {
      cMtlPickWrap.style.display = '';
      cMtlPick.innerHTML = `<option value="">-- Choose MTL# --</option>` + mtls.map((m) => `<option value="${esc(m)}">${esc(m)}</option>`).join('');
      cErpHint.textContent = `Prod Code ${val} matches ${mtls.length} MTL# values.`;
    } else if (mtls.length === 1) {
      cMtl.value = mtls[0];
      const o = orders[0] || {};
      cErpHint.textContent = `Matched MTL# ${mtls[0]} | PO ${o.po_no || '-'} | CRD ${o.crd || '-'}`;
    }
  }

  let erpTmr = null;
  cMtl.addEventListener('input', () => { clearTimeout(erpTmr); erpTmr = setTimeout(() => { refreshErpList(cMtl.value.trim()); lookupByMtl(); }, 250); });
  cProd.addEventListener('input', () => { clearTimeout(erpTmr); erpTmr = setTimeout(() => { refreshErpList(cProd.value.trim()); lookupByProd(); }, 250); });
  cProdPick.addEventListener('change', () => { if (cProdPick.value) { cProd.value = cProdPick.value; fillCopyForm(); } });
  cMtlPick.addEventListener('change', () => { if (cMtlPick.value) { cMtl.value = cMtlPick.value; fillCopyForm(); } });

  async function fillCopyForm() {
    const r = await fetch(`${base}interlink/vf-test-request/fill-form`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        request_id: document.getElementById('c-req-id').value,
        source_application_no: document.getElementById('c-src-app').value,
        mtl_no: cMtl.value.trim(),
        prod_code: cProd.value.trim(),
        test_package: document.getElementById('c-pkg').value,
      }),
    });
    const j = await r.json().catch(() => null);
    if (!j || !j.ok || !j.form) { toast((j && j.error) || 'Could not fill form', 'error'); return; }
    const f = j.form;
    if (f.mtl_no) cMtl.value = f.mtl_no;
    if (f.prod_code) cProd.value = f.prod_code;
    document.getElementById('c-pkg').value = f.test_package;
    document.getElementById('c-style-no').value = f.style_no || '';
    document.getElementById('c-prod-name').value = f.product_name || '';
    document.getElementById('c-style-color').value = f.style_color_no || '';
    document.getElementById('c-color-desc').value = f.color_description || '';
    document.getElementById('c-color-code').value = f.color_code || '';
    document.getElementById('c-season').value = f.season || '';
    document.getElementById('c-size-cat').value = f.size_category || 'UNISEX';
    document.getElementById('c-age').value = f.age_group || '';
    document.getElementById('c-crd').value = f.cargo_receive_date || '';
    document.getElementById('c-po').value = f.po_no || '';
    document.getElementById('c-remarks').value = f.remarks || '';

    const warnEl = document.getElementById('c-guard-banner');
    const unmatchWrap = document.getElementById('c-allow-unmatch-wrap');
    const unmatchChk = document.getElementById('c-allow-unmatch');
    const retestWrap = document.getElementById('c-allow-retest-wrap');
    const retestChk = document.getElementById('c-allow-retest');
    const warns = [];
    if (f.erp_ambiguous) {
      warns.push(`MTL# ${f.mtl_no} has ${f.erp_prod_choices_count} Prod Codes in ERP — pick the exact Prod Code above before submitting.`);
    } else if (!f.erp_matched) {
      warns.push('No exact row found in dberp.production_orders. Select a valid ERP Prod Code or check "Allow Unmatched ERP".');
    }
    if (f.crd_differs) {
      warns.push(`CRD differs: Email DTP交期 is ${f.email_crd}, ERP CRD is ${f.erp_crd} (using ERP CRD).`);
    }
    const existingItem = (state.requests || []).find((x) => String(x.id) === String(document.getElementById('c-req-id').value));
    if (existingItem && existingItem.generated_application_no) {
      warns.push(`Already submitted as ${existingItem.generated_application_no}. Check "Allow Retest" only if creating a new application.`);
    }
    if (warnEl) {
      warnEl.style.display = warns.length ? '' : 'none';
      warnEl.textContent = warns.join(' ');
    }
    if (unmatchWrap) unmatchWrap.style.display = !f.erp_matched && !f.erp_ambiguous ? 'inline-flex' : 'none';
    if (unmatchChk) unmatchChk.checked = false;
    if (retestWrap) retestWrap.style.display = existingItem && existingItem.generated_application_no ? 'inline-flex' : 'none';
    if (retestChk) retestChk.checked = false;
    toast('Form fields populated from Source Application + ERP Production Order.', 'success');
  }

  document.getElementById('c-fill-btn').addEventListener('click', fillCopyForm);

  // ---- past Applications of the chosen Test Package (same style / MTL# first): pick one to Copy as New from
  let pastSeq = 0;
  async function loadPastApps() {
    const sel = document.getElementById('c-past'); if (!sel) return;
    const my = ++pastSeq;
    const pkg = document.getElementById('c-pkg').value;
    const mtl = (cMtl.value || document.getElementById('c-style-color').value || '').trim();
    const style = document.getElementById('c-style-no').value.trim();
    sel.innerHTML = '<option value="">Loading past Applications...</option>';
    const qs = new URLSearchParams({ package: pkg, mtl, style });
    const j = await fetch(`${base}interlink/vf-test-request/past-apps?${qs}`, { credentials: 'same-origin' }).then((x) => x.json()).catch(() => null);
    if (my !== pastSeq) return;
    const apps = (j && j.ok && j.apps) || [];
    const label = (a) => [a.application_no, a.style_no, a.style_color_no, a.product_name, a.colorway_name, a.trf_submission_date, a.job_status, Number(a.own) ? '(sent from NEXUS-L)' : ''].filter(Boolean).join(' · ');
    const opt = (a) => `<option value="${esc(a.application_no)}">${esc(label(a))}</option>`;
    const same = apps.filter((a) => Number(a.same)), other = apps.filter((a) => !Number(a.same));
    sel.innerHTML = '<option value="">- pick a past Application No. -</option>'
      + (same.length ? `<optgroup label="Same style / MTL# (${same.length})">${same.map(opt).join('')}</optgroup>` : '')
      + (other.length ? `<optgroup label="Other ${esc(pkg)} Applications (${other.length})">${other.map(opt).join('')}</optgroup>` : '');
    const cur = document.getElementById('c-src-app').value.trim();
    if (cur && apps.some((a) => a.application_no === cur)) sel.value = cur;
    document.getElementById('c-past-hint').textContent = apps.length ? `(${apps.length} found${same.length ? ', ' + same.length + ' with the same style / MTL#' : ''})` : '(none saved yet - press Sync InterLink Requests first)';
  }
  document.getElementById('c-past').addEventListener('change', (e) => { if (e.target.value) document.getElementById('c-src-app').value = e.target.value; });
  document.getElementById('c-pkg').addEventListener('change', loadPastApps);

  function openCopyModal({ requestId = 0, srcApp = '', mtl = '', prodCode = '', pkg = '' } = {}) {
    document.getElementById('c-req-id').value = String(requestId || 0);
    document.getElementById('c-src-app').value = srcApp || '';
    cMtl.value = mtl || '';
    cProd.value = prodCode || '';
    cProdPickWrap.style.display = 'none';
    cMtlPickWrap.style.display = 'none';
    const warnEl = document.getElementById('c-guard-banner');
    if (warnEl) { warnEl.style.display = 'none'; warnEl.textContent = ''; }
    if (document.getElementById('c-allow-unmatch')) document.getElementById('c-allow-unmatch').checked = false;
    if (document.getElementById('c-allow-retest')) document.getElementById('c-allow-retest').checked = false;
    if (pkg) {
      const opt = [...document.getElementById('c-pkg').options].find((o) => pkg.toLowerCase().includes(o.value.toLowerCase().slice(0, 4)));
      if (opt) document.getElementById('c-pkg').value = opt.value;
    }
    refreshErpList(mtl || prodCode || '');
    openModal('vfr-copy-modal');
    loadPastApps();
    if (mtl || prodCode) {
      lookupByMtl().then(() => fillCopyForm()).then(() => loadPastApps());
    }
  }

  document.getElementById('c-submit-btn').addEventListener('click', async () => {
    if (cProdPickWrap.style.display !== 'none' && !cProdPick.value && !cProd.value.trim()) {
      toast('Select the exact Prod Code for this MTL# before submitting.', 'error');
      return;
    }
    const chk = await ensureInterlinkSignIn(5000);
    if (!chk.ready) { toast(chk.reason, 'error'); return; }
    const btn = document.getElementById('c-submit-btn');
    btn.disabled = true;
    try {
      const r = await fetch(`${base}interlink/vf-test-request/submit`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          request_id: document.getElementById('c-req-id').value,
          source_application_no: document.getElementById('c-src-app').value.trim(),
          test_package: document.getElementById('c-pkg').value,
          mtl_no: cMtl.value.trim(),
          prod_code: cProd.value.trim(),
          style_no: document.getElementById('c-style-no').value.trim(),
          product_name: document.getElementById('c-prod-name').value.trim(),
          color_description: document.getElementById('c-color-desc').value.trim(),
          color_code: document.getElementById('c-color-code').value.trim(),
          season: document.getElementById('c-season').value.trim(),
          size_category: document.getElementById('c-size-cat').value.trim(),
          age_group: document.getElementById('c-age').value.trim(),
          cargo_receive_date: document.getElementById('c-crd').value.trim(),
          remarks: document.getElementById('c-remarks').value.trim(),
          allow_unmatched_erp: document.getElementById('c-allow-unmatch')?.checked ? 1 : 0,
          allow_retest: document.getElementById('c-allow-retest')?.checked ? 1 : 0,
        }),
      });
      const j = await r.json().catch(() => null);
      if (!r.ok || !j || !j.ok) {
        if (j && j.code === 'ERP_NOT_FOUND' && document.getElementById('c-allow-unmatch-wrap')) {
          document.getElementById('c-allow-unmatch-wrap').style.display = 'inline-flex';
        }
        if (j && j.code === 'ALREADY_SUBMITTED' && document.getElementById('c-allow-retest-wrap')) {
          document.getElementById('c-allow-retest-wrap').style.display = 'inline-flex';
        }
        toast((j && j.error) || 'Failed to queue InterLink Copy as New job.', 'error');
        return;
      }
      toast(j.message || 'Submitted to InterLink background service!', 'success');
      closeModal('vfr-copy-modal');
      loadData();
    } finally {
      btn.disabled = false;
    }
  });

  document.getElementById('vfr-sync-emails').addEventListener('click', async () => {
    const r = await fetch(`${base}interlink/vf-test-request/sync-emails`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: '{}' });
    const j = await r.json().catch(() => null);
    if (!r.ok || !j || !j.ok) { toast((j && j.error) || 'Outlook sync error', 'error'); return; }
    toast(`Scanned ${j.scanned} email(s): +${j.items_added} requested (${j.items_skipped || 0} skipped), +${j.transit_updates} transit, +${j.lab_received} lab received, +${j.certificates_saved} cert PDF(s).`, 'success');
    loadData();
  });

  document.getElementById('vfr-sync-grid').addEventListener('click', () => { live = null; liveLookup(1, true, true); });

  document.getElementById('vfr-open-paste').addEventListener('click', () => openModal('vfr-paste-modal'));
  document.getElementById('p-import-btn').addEventListener('click', async () => {
    const r = await fetch(`${base}interlink/vf-test-request/import-html`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        subject: document.getElementById('p-subj').value,
        test_package: document.getElementById('p-pkg').value,
        source_id: Number((document.getElementById('p-source') || {}).value || 0),
        sender: ((document.getElementById('p-sender') || {}).value || '').trim(),
        html: document.getElementById('p-html').value,
      }),
    });
    const j = await r.json().catch(() => null);
    if (!r.ok || !j || !j.ok) { toast((j && j.error) || 'Import failed', 'error'); return; }
    toast(`Imported ${j.added} item(s) under ${j.package}.`, 'success');
    closeModal('vfr-paste-modal');
    loadData();
  });


  // ---- E-mail sources: which senders are read as test requests and how their table is read (configurable, nothing fixed in code)
  let srcData = { sources: [], fields: {}, packages: [] };
  const srcEl = (id) => document.getElementById(id);
  const joinAliases = (a) => (Array.isArray(a) ? a.join(', ') : '');
  async function loadSources() {
    const j = await fetch(`${base}interlink/vf-test-request/sources`, { credentials: 'same-origin' }).then((x) => x.json()).catch(() => null);
    if (!j || !j.ok) return;
    srcData = j;
    const names = srcEl('vfr-src-names');
    const on = j.sources.filter((s) => Number(s.enabled));
    if (names) names.textContent = on.length ? on.map((s) => s.name).join(', ') : 'no e-mail source set up';
    const sel = srcEl('p-source');
    if (sel) { const cur = sel.value; sel.innerHTML = '<option value="">Automatic (by sender below)</option>' + j.sources.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join(''); sel.value = cur; }
  }
  function renderSourceList() {
    const box = srcEl('src-list'); if (!box) return;
    const cur = srcEl('s-id').value;
    box.innerHTML = srcData.sources.map((s) => `<button type="button" class="vfr-btn sm ${String(s.id) === cur ? 'primary' : ''}" data-src="${s.id}" style="text-align:left;white-space:normal">${esc(s.name)}${Number(s.enabled) ? '' : ' <small>(off)</small>'}<br><small>${esc(String(s.sender_patterns || s.subject_keywords).slice(0, 40))}</small></button>`).join('') || '<div class="muted">No source yet.</div>';
    box.querySelectorAll('[data-src]').forEach((b) => b.addEventListener('click', () => editSource(Number(b.dataset.src))));
  }
  function editSource(id) {
    const s = srcData.sources.find((x) => Number(x.id) === id) || { id: 0, name: '', sender_patterns: '', subject_keywords: '', default_package: '', skip_state_regex: '', priority: 100, enabled: 1, column_map: {} };
    srcEl('s-id').value = s.id || 0;
    srcEl('s-name').value = s.name || '';
    srcEl('s-senders').value = s.sender_patterns || '';
    srcEl('s-subjects').value = s.subject_keywords || '';
    srcEl('s-pkg').value = s.default_package || '';
    srcEl('s-skip').value = s.skip_state_regex || '';
    srcEl('s-priority').value = s.priority || 100;
    srcEl('s-enabled').checked = !!Number(s.enabled === undefined ? 1 : s.enabled);
    srcEl('s-map').innerHTML = Object.entries(srcData.fields || {}).map(([f, label]) => `<div><label for="sm-${f}">${esc(label)}</label><input type="text" id="sm-${f}" data-field="${f}" value="${esc(joinAliases((s.column_map || {})[f]))}" placeholder="built-in"></div>`).join('');
    srcEl('s-prev-out').innerHTML = '';
    srcEl('s-delete').style.display = s.id ? '' : 'none';
    renderSourceList();
  }
  const readMap = () => { const m = {}; document.querySelectorAll('#s-map [data-field]').forEach((i) => { if (i.value.trim()) m[i.dataset.field] = i.value; }); return m; };
  async function postJson(path, body) {
    const r = await fetch(`${base}interlink/vf-test-request/${path}`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    return { ok: r.ok, j: await r.json().catch(() => null) };
  }
  const openSources = async () => { await loadSources(); openModal('vfr-sources-modal'); editSource((srcData.sources[0] || {}).id || 0); };
  ['vfr-open-sources', 'vfr-open-sources-btn'].forEach((id) => { const b = srcEl(id); if (b) b.addEventListener('click', (e) => { e.preventDefault(); openSources(); }); });
  srcEl('src-new').addEventListener('click', () => editSource(0));
  srcEl('s-save').addEventListener('click', async () => {
    const { ok, j } = await postJson('sources/save', { id: Number(srcEl('s-id').value), name: srcEl('s-name').value, sender_patterns: srcEl('s-senders').value, subject_keywords: srcEl('s-subjects').value, default_package: srcEl('s-pkg').value, skip_state_regex: srcEl('s-skip').value, priority: Number(srcEl('s-priority').value), enabled: srcEl('s-enabled').checked, column_map: readMap() });
    if (!ok || !j || !j.ok) { toast((j && j.error) || 'Could not save the source.', 'error'); return; }
    srcData.sources = j.sources; await loadSources(); editSource(j.id); toast('Source saved. ' + (j.shared && j.shared.ok ? 'Its senders were given to the Outlook Attachment Sync database (every Control Center picks them up within a minute).' : 'The senders could NOT be shared with the Outlook apps (' + ((j.shared && j.shared.error) || 'unknown') + '); add them in the Control Center.'), (j.shared && j.shared.ok) ? 'success' : 'info');
  });
  srcEl('s-delete').addEventListener('click', async () => {
    const id = Number(srcEl('s-id').value); if (!id || !confirm('Delete this source? Requests already read stay.')) return;
    const { j } = await postJson('sources/delete', { id }); if (j && j.ok) { srcData.sources = j.sources; await loadSources(); editSource((srcData.sources[0] || {}).id || 0); }
  });
  srcEl('s-preview').addEventListener('click', async () => {
    const out = srcEl('s-prev-out'); out.innerHTML = 'Reading...';
    const { j } = await postJson('sources/preview', { html: srcEl('s-prev-html').value, column_map: readMap() });
    if (!j) { out.textContent = 'Preview failed.'; return; }
    const hdr = (j.headers || []).map((h) => `<th style="padding:2px 6px;border:1px solid #e2e8f0">${esc(h)}</th>`).join('');
    const mapped = Object.entries(j.mapped || {}).map(([f, h]) => `<span class="vfr-badge vfr-b-ok" style="margin-right:4px">${esc((srcData.fields || {})[f] || f)} &larr; ${esc(h)}</span>`).join('');
    const rows = (j.rows || []).map((r) => '<tr>' + Object.keys(srcData.fields || {}).map((f) => `<td style="padding:2px 6px;border:1px solid #e2e8f0">${esc(r[f])}</td>`).join('') + '</tr>').join('');
    out.innerHTML = (j.ok ? '' : `<div style="color:#b91c1c;margin-bottom:4px">${esc(j.error || '')}</div>`) + `<div style="margin-bottom:4px"><b>Headers found:</b></div><table style="border-collapse:collapse"><tr>${hdr}</tr></table>` + (mapped ? `<div style="margin:6px 0">${mapped}</div>` : '')
      + (rows ? `<table style="border-collapse:collapse"><tr>${Object.values(srcData.fields || {}).map((l) => `<th style="padding:2px 6px;border:1px solid #e2e8f0">${esc(l)}</th>`).join('')}</tr>${rows}</table>` : '');
  });
  loadSources();

  loadData().then(() => liveLookup(1, false));
})();
