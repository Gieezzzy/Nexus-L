(() => {
  const root = document.getElementById('vfm-root');
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

  const pkg = document.getElementById('vfm-pkg');
  const stage = document.getElementById('vfm-stage');
  const q = document.getElementById('vfm-q');
  const body = document.getElementById('vfm-body');
  const batchesEl = document.getElementById('vfm-batches');
  let allRows = [];
  let page = 1;
  let pageSize = Number((function () { try { return localStorage.getItem('vfmPageSize'); } catch (e) { return ''; } })()) || 50;
  let allBatches = [];
  let bPage = 1;
  const B_SIZE = 10;

  const badge = (s) => {
    const u = String(s || '').toUpperCase();
    if (u === 'COMPLETED' || u === 'PASS') return 'vfm-ok';
    if (u === 'SUBMITTED_INTERLINK' || u === 'EMAIL_SENT' || u === 'LAB_RECEIVED') return 'vfm-info';
    if (u === 'FAILED') return 'vfm-err';
    return 'vfm-warn';
  };

  function renderPager(id, cur, pages, total, size, withSize, goto, setSize) {
    const el = document.getElementById(id);
    if (!el) return;
    if (total <= (withSize ? 0 : size)) { el.innerHTML = withSize ? '<span class="muted">0 records</span>' : ''; return; }
    const from = (cur - 1) * size + 1, to = Math.min(total, cur * size);
    const nums = [];
    for (let n = 1; n <= pages; n++) { if (n === 1 || n === pages || Math.abs(n - cur) <= 1) nums.push(n); else if (nums[nums.length - 1] !== '...') nums.push('...'); }
    el.innerHTML = `<span class="vfm-pg-info">${from}-${to} of ${total}</span>`
      + `<button type="button" class="vfm-btn sm" data-pg="${cur - 1}" ${cur <= 1 ? 'disabled' : ''}>&lsaquo; Prev</button>`
      + nums.map((n) => (n === '...' ? '<span class="muted">...</span>' : `<button type="button" class="vfm-btn sm ${n === cur ? 'primary' : ''}" data-pg="${n}">${n}</button>`)).join('')
      + `<button type="button" class="vfm-btn sm" data-pg="${cur + 1}" ${cur >= pages ? 'disabled' : ''}>Next &rsaquo;</button>`
      + (withSize ? `<label class="vfm-pg-size">Rows <select data-size>${[25, 50, 100, 250].map((n) => `<option value="${n}" ${n === size ? 'selected' : ''}>${n}</option>`).join('')}</select></label>` : '');
    el.querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { const n = Number(b.dataset.pg); if (n >= 1 && n <= pages) goto(n); }));
    const sel = el.querySelector('[data-size]');
    if (sel && setSize) sel.addEventListener('change', () => setSize(Number(sel.value)));
  }

  // the real state of the e-mail: queued / sending are NOT e-mailed; only the sender (Outlook Relay / Hub) saying "sent" makes it so
  const mailBadge = (x) => {
    if (x.etrf_emailed_at) return `<span class="vfm-badge vfm-ok" title="${esc(x.etrf_emailed_at)}">E-mailed ${esc(String(x.etrf_emailed_at).slice(5, 16))}</span>`;
    const st = String(x.outbox_status || '').toLowerCase();
    if (st === 'failed') return `<span class="vfm-badge vfm-err" title="${esc(x.outbox_error || 'The mail program could not send it')}">Failed - tick and send again</span>`;
    if (st === 'queued' || st === 'sending') return `<span class="vfm-badge vfm-info" title="Waiting for the Outlook Relay / Hub to send it">${st === 'sending' ? 'Sending...' : 'Queued - not sent yet'}</span>`;
    return '<span class="muted">-</span>';
  };

  // what each request looked like at the last refresh: a change (mail sent / failed, PDF arrived) pops a toast without any reload
  let seen = null;
  function announceChanges(rows) {
    const now = new Map(rows.map((x) => [String(x.id), { mail: x.etrf_emailed_at ? 'sent' : String(x.outbox_status || '').toLowerCase(), pdf: !!x.etrf_pdf_path, app: x.generated_application_no || '', x }]));
    if (seen) {
      const sent = [], failed = [], pdfs = [], apps = [];
      now.forEach((v, id) => {
        const o = seen.get(id);
        if (!o) return;
        if (v.mail === 'sent' && o.mail !== 'sent') sent.push(v.x);
        if (v.mail === 'failed' && o.mail !== 'failed') failed.push(v.x);
        if (v.pdf && !o.pdf) pdfs.push(v.x);
        if (v.app && !o.app) apps.push(v.x);
      });
      if (sent.length) toast(`E-mail sent: ${sent.length} request(s) (${sent.slice(0, 3).map((x) => x.mtl_no).join(', ')}${sent.length > 3 ? ', ...' : ''}). A message was also put in your Inbox.`, 'success');
      if (failed.length) toast(`E-mail could not be sent for ${failed.length} request(s): ${failed[0].outbox_error || 'see the Hub'}`, 'error');
      if (pdfs.length) toast(`${pdfs.length} eTRF PDF(s) arrived from InterLink.`, 'success');
      if (apps.length) toast(`${apps.length} request(s) got an Application No.`, 'success');
    }
    seen = now;
  }

  async function load(quiet) {
    const url = `${base}lab-monitoring/vf-requests/data?package=${encodeURIComponent(pkg.value)}&stage=${encodeURIComponent(stage.value)}&q=${encodeURIComponent(q.value)}`;
    const r = await fetch(url, { credentials: 'same-origin' });
    const j = await r.json().catch(() => null);
    if (!j || !j.ok) return;
    const tickedBefore = new Set([...batchesEl.querySelectorAll('.vfm-pick:checked')].map((c) => c.dataset.id));
    allRows = j.rows || [];
    announceChanges(allRows);
    if (j.linked && Number(j.linked.linked) > 0) toast(`${j.linked.linked} request(s) were linked to Application Nos. that already exist in InterLink` + (Number(j.linked.pdfs) ? ` (${j.linked.pdfs} eTRF PDF(s) taken from the e-mail they were sent in).` : '.'), 'success');
    const sh = j.sync_health, shEl = document.getElementById('vfm-sync-health');
    if (sh && shEl) {
      shEl.style.display = '';
      if (sh.stale) {
        shEl.style.borderLeft = '4px solid #dc2626';
        shEl.innerHTML = '<b style="color:#dc2626">No new e-mail from Outlook sync.</b> ' + (sh.last_mail ? 'Newest stored e-mail was received ' + esc(sh.last_mail) + ' (' + esc(sh.age_hours) + ' h ago, station ' + esc(sh.station || '?') + ').' : 'No e-mail has been stored yet.') + ' Make sure OutlookAttachmentSync is running on the PC whose Outlook receives djc_lab / SGS / KOTITI mail (the mailbox that gets the VF requests), or use Paste / Import HTML as a backup.';
      } else {
        shEl.style.borderLeft = '4px solid #059669';
        shEl.innerHTML = 'Outlook sync OK - newest e-mail received ' + esc(sh.last_mail) + ' (station ' + esc(sh.station || '?') + ').';
      }
    }

    const live = allRows.filter((x) => x.source_channel !== 'excel_import');
    document.getElementById('k-total').textContent = live.length;
    document.getElementById('k-sub').textContent = live.filter((x) => x.generated_application_no).length;
    document.getElementById('k-trn').textContent = live.filter((x) => x.container_no).length;
    document.getElementById('k-lab').textContent = live.filter((x) => x.lab_order_no || x.lab_report_no).length;
    document.getElementById('k-done').textContent = live.filter((x) => x.stage === 'COMPLETED').length;
    document.getElementById('k-hist').textContent = allRows.length - live.length;

    allBatches = j.batches || [];
    const bPages = Math.max(1, Math.ceil(allBatches.length / B_SIZE));
    if (bPage > bPages) bPage = bPages;
    const kidsOf = (id) => allRows.filter((x) => String(x.batch_id) === String(id));
    if (!window.__vfmOpenInit) { window.__vfmOpenInit = true; window.__vfmOpen = new Set(allBatches.filter((b) => kidsOf(b.id).some((x) => x.generated_application_no && !x.etrf_emailed_at)).slice(0, 3).map((b) => String(b.id))); }
    batchesEl.innerHTML = allBatches.length ? allBatches.slice((bPage - 1) * B_SIZE, bPage * B_SIZE).map((b) => {
      const kids = kidsOf(b.id);
      const sub = kids.filter((x) => x.generated_application_no);
      const mailed = kids.filter((x) => x.etrf_emailed_at);
      const open = window.__vfmOpen.has(String(b.id));
      const toSend = sub.filter((x) => !x.etrf_emailed_at).length;
      const sender = b.sender_name || b.sender_email || ('E-mail #' + b.id);
      const kidRows = kids.map((x) => `
        <tr>
          <td><input type="checkbox" class="vfm-pick" data-b="${b.id}" data-id="${x.id}" ${x.generated_application_no ? '' : 'disabled title="Not submitted to InterLink yet - no Application No."'}></td>
          <td class="mono"><b>${esc(x.prod_code || '-')}</b></td>
          <td class="mono"><b>${esc(x.mtl_no)}</b></td>
          <td>${esc(x.style_no)}</td>
          <td class="mono"><b>${esc(x.generated_application_no || '-')}</b></td>
          <td>${x.etrf_pdf_path ? `<a href="${base}lab-monitoring/vf-requests/pdf/etrf/${x.id}" target="_blank">View PDF</a>` : (x.generated_application_no ? '<span class="vfm-badge vfm-warn">No PDF</span>' : '<span class="muted">-</span>')}</td>
          <td><span class="vfm-badge ${badge(x.stage)}">${esc(x.stage)}</span></td>
          <td>${mailBadge(x)}</td>
        </tr>`).join('');
      return `
      <tr class="vfm-grp ${open ? 'open' : ''}" data-g="${b.id}">
        <td><span class="vfm-chev">&#9656;</span>#${b.id} &middot; ${esc(sender)}${b.sender_email && b.sender_name ? ` <span class="vfm-sub">&lt;${esc(b.sender_email)}&gt;</span>` : ''}<div class="vfm-sub" style="padding-left:14px">${esc(String(b.received_at || b.created_at || '').slice(0, 16))}</div></td>
        <td>${esc(String(b.subject || '').slice(0, 90))}</td>
        <td>${esc(b.test_package || b.test_type_short)}</td>
        <td><b>${sub.length}</b> submitted / ${kids.length || b.item_count} requests<div class="vfm-sub">${mailed.length} e-mailed${toSend ? `, <b style="color:#92400e">${toSend} waiting to be e-mailed</b>` : ''}</div></td>
        <td>${(() => {
          const st = kids.map((x) => (x.etrf_emailed_at ? 'sent' : String(x.outbox_status || '').toLowerCase()));
          if (st.includes('failed')) return '<span class="vfm-badge vfm-err">E-mail failed</span>';
          if (st.includes('queued') || st.includes('sending')) return '<span class="vfm-badge vfm-info">Queued - not sent yet</span>';
          if (sub.length && mailed.length >= sub.length) return `<span class="vfm-badge vfm-ok">Sent ${esc(String(b.email_sent_at || '').slice(5, 16))}</span>`;
          return mailed.length ? '<span class="vfm-badge vfm-warn">Partly sent</span>' : '<span class="vfm-badge vfm-warn">Pending</span>';
        })()}
          <label class="vfm-sub" style="margin-left:6px" title="Send automatically when every request of this e-mail is submitted"><input type="checkbox" class="vfm-auto" data-id="${b.id}" ${Number(b.auto_send_email) ? 'checked' : ''}> auto</label>
          ${sub.some((x) => !x.etrf_pdf_path) ? `<button type="button" class="vfm-btn sm vfm-getpdf" data-id="${b.id}" style="margin-left:6px" title="Let the InterLink service print the missing eTRF PDFs">Get ${sub.filter((x) => !x.etrf_pdf_path).length} PDF(s) from InterLink</button>` : ''}
          <button type="button" class="vfm-btn sm primary vfm-send" data-id="${b.id}" style="margin-left:6px">Send Batch PDF Email</button></td>
      </tr>
      <tr class="vfm-kidwrap ${open ? 'open' : ''}" data-kids="${b.id}"><td colspan="5">
        <table class="vfm-kids"><thead><tr><th><input type="checkbox" class="vfm-pickall" data-b="${b.id}" title="Select all"></th><th>Prod Code</th><th>MTL#</th><th>Style</th><th>Application No.</th><th>eTRF PDF</th><th>Stage</th><th>E-mailed</th></tr></thead>
        <tbody>${kidRows || '<tr><td colspan="8" class="muted">No requests under this e-mail with the current filters.</td></tr>'}</tbody></table>
      </td></tr>`;
    }).join('') : '<tr><td colspan="5" class="muted" style="text-align:center;padding:12px">No email batches yet.</td></tr>';

    batchesEl.querySelectorAll('.vfm-pick').forEach((c) => { if (tickedBefore.has(c.dataset.id) && !c.disabled) c.checked = true; });
    const cnt = document.getElementById('vfm-count');
    if (cnt) cnt.textContent = allRows.length + ' request(s) - scroll sideways for all columns (Test Type and Prod Code stay in view)';
    const pages = Math.max(1, Math.ceil(allRows.length / pageSize));
    if (page > pages) page = pages;
    const pageRows = allRows.slice((page - 1) * pageSize, page * pageSize);
    body.innerHTML = allRows.length ? pageRows.map((r0) => `
      <tr>
        <td>${esc(r0.test_type_short)}</td>
        <td class="mono"><b>${esc(r0.prod_code || '-')}</b></td>
        <td class="mono"><b>${esc(r0.mtl_no)}</b></td>
        <td>${esc(r0.style_no)}</td>
        <td>${esc(r0.po_no)}</td>
        <td>${esc(r0.crd)}</td>
        <td>${esc(r0.season || '-')}</td>
        <td class="mono">${esc(r0.tr_no || '-')}</td>
        <td class="mono"><b>${esc(r0.generated_application_no || '-')}</b></td>
        <td>${r0.etrf_pdf_path ? `<a href="${base}lab-monitoring/vf-requests/pdf/etrf/${r0.id}" target="_blank">View PDF</a>` : (r0.pdf_capture_warning ? `<span class="vfm-badge vfm-warn" title="${esc(r0.pdf_capture_warning)}">No PDF</span>` : '<span class="muted">-</span>')}</td>
        <td class="mono">${esc(r0.container_no || '-')}</td>
        <td class="mono">${esc(r0.lab_order_no || '-')}</td>
        <td class="mono">${esc(r0.lab_report_no || '-')}</td>
        <td>${esc(r0.expected_due_date || '-')}</td>
        <td>${r0.test_result ? `<span class="vfm-badge ${badge(r0.test_result)}">${esc(r0.test_result)}</span>` : '-'}</td>
        <td>${esc(r0.issue_date || '-')}</td>
        <td>${esc(r0.validity_date || '-')}</td>
        <td title="${esc(r0.fail_reason || '')}">${esc(String(r0.fail_reason || '').slice(0, 40) || '-')}</td>
        <td>${r0.certificate_pdf_path ? `<a href="${base}lab-monitoring/vf-requests/pdf/cert/${r0.id}" target="_blank">Certificate</a>` : '<span class="muted">-</span>'}</td>
        <td><span class="vfm-badge ${badge(r0.stage)}">${esc(r0.stage)}</span></td>
        <td><button type="button" class="vfm-btn sm vfm-edit" data-id="${r0.id}">Edit</button></td>
      </tr>
    `).join('') : '<tr><td colspan="22" class="muted" style="text-align:center;padding:18px">No matching VF Test Requests.</td></tr>';
    renderPager('vfm-pager', page, pages, allRows.length, pageSize, true, (n) => { page = n; load(); }, (n) => { pageSize = n; page = 1; try { localStorage.setItem('vfmPageSize', String(n)); } catch (e) { /* private mode */ } load(); });
    renderPager('vfm-bpager', bPage, bPages, allBatches.length, B_SIZE, false, (n) => { bPage = n; load(); });
  }

  pkg.addEventListener('change', () => { page = 1; load(); });
  stage.addEventListener('change', () => { page = 1; load(); });
  let tmr = null;
  q.addEventListener('input', () => { clearTimeout(tmr); tmr = setTimeout(() => { page = 1; load(); }, 250); });

  batchesEl.addEventListener('change', async (e) => {
    const chk = e.target.closest('.vfm-auto');
    if (!chk) return;
    await fetch(`${base}lab-monitoring/vf-requests/toggle-auto`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ batch_id: chk.dataset.id, auto_send_email: chk.checked ? 1 : 0 }),
    });
  });

  const ticked = (bid) => [...batchesEl.querySelectorAll(`.vfm-pick[data-b="${bid}"]:checked`)].map((c) => Number(c.dataset.id));

  batchesEl.addEventListener('change', (e) => {
    const all = e.target.closest('.vfm-pickall');
    if (all) batchesEl.querySelectorAll(`.vfm-pick[data-b="${all.dataset.b}"]:not(:disabled)`).forEach((c) => { c.checked = all.checked; });
  });

  batchesEl.addEventListener('click', async (e) => {
    const send = e.target.closest('.vfm-send');
    if (send) { openMailDialog(Number(send.dataset.id), ticked(send.dataset.id)); return; }
    const getpdf = e.target.closest('.vfm-getpdf');
    if (getpdf) {
      getpdf.disabled = true;
      const r = await fetch(`${base}lab-monitoring/vf-requests/fetch-pdfs`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ batch_id: Number(getpdf.dataset.id), request_ids: ticked(getpdf.dataset.id) }) });
      const j = await r.json().catch(() => null);
      toast(j && j.ok ? `Asked the InterLink service for ${j.queued} PDF(s). They appear here as it prints them (about a minute each); reload to refresh.` : ((j && j.error) || 'Could not queue the PDFs.'), j && j.ok ? 'success' : 'error');
      getpdf.disabled = false;
      return;
    }
    if (e.target.closest('input,label,a,button')) return;
    const grp = e.target.closest('tr.vfm-grp');
    if (!grp) return;
    const id = grp.dataset.g, open = !grp.classList.contains('open');
    grp.classList.toggle('open', open);
    const kids = batchesEl.querySelector(`tr[data-kids="${id}"]`);
    if (kids) kids.classList.toggle('open', open);
    if (open) window.__vfmOpen.add(id); else window.__vfmOpen.delete(id);
  });

  // ---- recipients as chips: each complete address gets its own pill, like in any mail program
  const EMAIL_RE = /^[^\s@<>;,]+@[^\s@<>;,]+\.[^\s@<>;,]+$/;
  const chipBox = (id) => {
    const box = document.getElementById(id), input = box.querySelector('input');
    const addrs = () => [...box.querySelectorAll('.vfm-chip')].map((c) => c.dataset.a);
    const add = (raw) => {
      String(raw).split(/[;,\s\n\r\t]+/).map((s) => s.trim().replace(/^[<'"]+|[>'"]+$/g, '')).filter(Boolean).forEach((a) => {
        if (addrs().some((x) => x.toLowerCase() === a.toLowerCase())) return;
        const chip = document.createElement('span');
        chip.className = 'vfm-chip' + (EMAIL_RE.test(a) ? '' : ' bad');
        chip.dataset.a = a;
        chip.title = EMAIL_RE.test(a) ? a : 'Not a valid e-mail address';
        chip.innerHTML = `${esc(a)}<button type="button" aria-label="Remove">&times;</button>`;
        box.insertBefore(chip, input);
      });
    };
    const flush = () => { if (input.value.trim()) { add(input.value); input.value = ''; } };
    box.addEventListener('click', (e) => { const x = e.target.closest('.vfm-chip button'); if (x) x.closest('.vfm-chip').remove(); else input.focus(); });
    input.addEventListener('keydown', (e) => {
      if (['Enter', ',', ';', ' ', 'Tab'].includes(e.key) && input.value.trim()) { e.preventDefault(); flush(); }
      else if (e.key === 'Backspace' && !input.value) { const last = box.querySelector('.vfm-chip:last-of-type'); if (last) last.remove(); }
    });
    input.addEventListener('paste', (e) => { e.preventDefault(); add((e.clipboardData || window.clipboardData).getData('text')); });
    input.addEventListener('blur', flush);
    return { set: (list) => { box.querySelectorAll('.vfm-chip').forEach((c) => c.remove()); input.value = ''; add(list || ''); }, get: () => { flush(); return addrs(); } };
  };
  const toBox = chipBox('m-to'), ccBox = chipBox('m-cc');

  // ---- the e-mail dialog: what will be sent (a reply to the request e-mail), before it is sent
  const mailModal = document.getElementById('vfm-mail-modal');
  let mailCtx = null;
  async function openMailDialog(batchId, ids) {
    const r = await fetch(`${base}lab-monitoring/vf-requests/email-preview`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ batch_id: batchId, request_ids: ids }) });
    const j = await r.json().catch(() => null);
    if (!j || !j.ok) { toast((j && j.error) || 'Could not prepare the e-mail.', 'error'); return; }
    mailCtx = { batchId, ids: j.request_ids };
    document.getElementById('m-title').textContent = `- batch #${batchId}, ${j.count} item(s), ${j.package}`;
    document.getElementById('m-note').textContent = ids.length ? 'Only the ticked requests are included.' : 'No row was ticked: every submitted request not e-mailed yet is included.';
    toBox.set(j.to);
    ccBox.set(j.cc);
    document.getElementById('m-subj').value = j.subject;
    const rep = document.getElementById('m-reply');
    rep.checked = !!j.reply && !!j.can_reply;
    rep.disabled = !j.can_reply;
    document.getElementById('m-reply-lbl').textContent = j.can_reply ? 'Reply to the e-mail that asked for these tests (same thread, the original is quoted below)' : 'The original e-mail cannot be found for this batch (pasted table): a new e-mail with the RE: subject is sent';
    const w = document.getElementById('m-warn');
    w.style.display = (j.warnings || []).length ? '' : 'none';
    w.innerHTML = (j.warnings || []).map(esc).join('<br>');
    document.getElementById('m-count').textContent = (j.files || []).filter((f) => f.ok).length;
    document.getElementById('m-files').innerHTML = '<thead><tr><th>File name</th><th>Size</th><th></th></tr></thead><tbody>' + (j.files || []).map((f) => `<tr><td class="mono">${esc(f.filename)}</td><td>${f.ok ? Math.max(1, Math.round(f.bytes / 1024)) + ' KB' : '-'}</td><td>${f.ok ? '<span class="vfm-badge vfm-ok">attached</span>' : '<span class="vfm-badge vfm-warn">no PDF</span>'}</td></tr>`).join('') + '</tbody>';
    document.getElementById('m-preview').srcdoc = j.body;
    mailModal.classList.add('open');
  }
  document.getElementById('m-cancel').addEventListener('click', () => mailModal.classList.remove('open'));
  document.getElementById('m-send').addEventListener('click', async () => {
    if (!mailCtx) return;
    const toList = toBox.get(), ccList = ccBox.get();
    const bad = [...toList, ...ccList].filter((a) => !EMAIL_RE.test(a));
    if (!toList.length) { toast('Add at least one recipient in To.', 'error'); return; }
    if (bad.length) { toast('These are not valid e-mail addresses: ' + bad.join(', '), 'error'); return; }
    const btn = document.getElementById('m-send');
    btn.disabled = true;
    try {
      const r = await fetch(`${base}lab-monitoring/vf-requests/send-email`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ batch_id: mailCtx.batchId, request_ids: mailCtx.ids, to: toList.join('; '), cc: ccList.join('; '), subject: document.getElementById('m-subj').value, reply: document.getElementById('m-reply').checked }) });
      const j = await r.json().catch(() => null);
      toast(j && j.ok ? `Queued ${j.reply ? 'reply' : 'e-mail'} (#${j.outbox_id}) with ${j.attached_pdfs} PDF(s) to ${j.to}.` : ((j && j.error) || 'Failed to queue email'), j && j.ok ? 'success' : 'error');
      if (j && j.ok) { mailModal.classList.remove('open'); load(); }
    } finally { btn.disabled = false; }
  });

  document.getElementById('vfm-sync-emails').addEventListener('click', async () => {
    const r = await fetch(`${base}interlink/vf-test-request/sync-emails`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: '{}' });
    const j = await r.json().catch(() => null);
    toast(j && j.ok ? `Scanned ${j.scanned}: +${j.items_added} reqs (${j.items_skipped || 0} skipped), +${j.transit_updates} transit, +${j.lab_received} lab recv, +${j.certificates_saved} certs.` : ((j && j.error) || 'Sync failed'), j && j.ok ? 'success' : 'error');
    load();
  });

  const modal = document.getElementById('vfm-edit-modal');
  document.getElementById('e-cancel').addEventListener('click', () => modal.classList.remove('open'));
  body.addEventListener('click', (e) => {
    const btn = e.target.closest('.vfm-edit');
    if (!btn) return;
    const row = allRows.find((x) => String(x.id) === btn.dataset.id);
    if (!row) return;
    document.getElementById('e-id').value = row.id;
    document.getElementById('e-title').textContent = `${row.prod_code} / ${row.mtl_no}`;
    document.getElementById('e-app').value = row.generated_application_no || '';
    document.getElementById('e-container').value = row.container_no || '';
    document.getElementById('e-rep').value = row.lab_report_no || '';
    document.getElementById('e-due').value = row.expected_due_date || '';
    document.getElementById('e-res').value = row.test_result || '';
    document.getElementById('e-stage').value = row.stage || 'REQUESTED';
    modal.classList.add('open');
  });

  document.getElementById('e-save').addEventListener('click', async () => {
    const r = await fetch(`${base}lab-monitoring/vf-requests/update-row`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: document.getElementById('e-id').value,
        generated_application_no: document.getElementById('e-app').value.trim(),
        container_no: document.getElementById('e-container').value.trim(),
        lab_report_no: document.getElementById('e-rep').value.trim(),
        expected_due_date: document.getElementById('e-due').value,
        test_result: document.getElementById('e-res').value,
        stage: document.getElementById('e-stage').value,
      }),
    });
    const j = await r.json().catch(() => null);
    toast(j && j.ok ? 'Saved.' : ((j && j.error) || 'Save failed'), j && j.ok ? 'success' : 'error');
    modal.classList.remove('open');
    load();
  });

  load();

  // live: refresh every 6 s while the tab is visible and no dialog is open (also right when the tab comes back)
  let polling = false;
  const poll = async () => {
    if (polling || document.hidden || document.querySelector('.open[id^="vfm-"][id$="-modal"]')) return;
    polling = true;
    try { await load(true); } catch (e) { /* next round */ } finally { polling = false; }
  };
  setInterval(poll, 6000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();
