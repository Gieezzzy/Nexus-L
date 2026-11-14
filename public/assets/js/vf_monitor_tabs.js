/* VF Test Monitoring: tabs (Email Submission / Transit Monitoring / Request Material Monitoring).
 * The "Email Submission" tab is the original page (vf_requests.js). This file adds the other two. */
(() => {
  const root = document.getElementById('vfm-root');
  if (!root) return;
  const base = String(root.dataset.base || window.NEXUS_BASE || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const call = async (path, opt = {}) => {
    const post = opt.method && String(opt.method).toUpperCase() !== 'GET';
    const headers = Object.assign({ 'Accept': 'application/json' }, post ? { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' } : {}, opt.headers || {});
    const r = await fetch(base + path, Object.assign({ credentials: 'same-origin' }, opt, { headers }));
    const j = await r.json().catch(() => null);
    if (j && j.csrf && csrfMeta) csrfMeta.content = j.csrf;
    if (!r.ok || !j || j.ok === false) throw new Error((j && j.error) || `Request failed (${r.status})`);
    return j;
  };
  const postJson = (path, body) => call(path, { method: 'POST', body: JSON.stringify(body || {}) });
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const toast = (m, t = 'info', ms = 7000) => (window.nxToast ? window.nxToast(m, t, ms) : alert(m));
  const $ = (id) => document.getElementById(id);
  const fmtDT = (s) => (s ? String(s).slice(5, 16).replace('-', '/') : '-');

  // a short beep for a new alarm (browsers only allow it after the user has clicked in the page: then it is silently skipped)
  const beep = () => {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      [880, 1175].forEach((f, i) => {
        const o = ctx.createOscillator(); const g = ctx.createGain();
        o.type = 'sine'; o.frequency.value = f; g.gain.setValueAtTime(0.18, ctx.currentTime + i * 0.18);
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.18 + 0.16);
        o.connect(g); g.connect(ctx.destination); o.start(ctx.currentTime + i * 0.18); o.stop(ctx.currentTime + i * 0.18 + 0.17);
      });
    } catch (e) { /* no sound */ }
  };

  // ------------------------------------------------------------------------------------------------ tabs
  const panes = { email: $('vfm-pane-email'), transit: $('vfm-pane-transit'), material: $('vfm-pane-material') };
  let tab = 'email';
  const showTab = (name) => {
    if (!panes[name]) name = 'email';
    tab = name;
    Object.entries(panes).forEach(([k, el]) => { if (el) el.hidden = k !== name; });
    document.querySelectorAll('#vfm-tabs .vfm-tab').forEach((b) => b.classList.toggle('active', b.dataset.tab === name));
    try { localStorage.setItem('vfmTab', name); } catch (e) { /* private mode */ }
    if (name === 'transit') loadTransit();
    if (name === 'material') loadMaterial();
  };
  $('vfm-tabs').addEventListener('click', (e) => { const b = e.target.closest('.vfm-tab'); if (b) showTab(b.dataset.tab); });

  // ------------------------------------------------------------------------------------------------ chips (To / CC)
  const EMAIL_RE = /^[^\s@<>;,]+@[^\s@<>;,]+\.[^\s@<>;,]+$/;
  const chipBox = (id) => {
    const box = $(id), input = box.querySelector('input');
    const addrs = () => [...box.querySelectorAll('.vfm-chip')].map((c) => c.dataset.a);
    const add = (raw) => {
      String(raw).split(/[;,\s\n\r\t]+/).map((s) => s.trim().replace(/^[<'"]+|[>'"]+$/g, '')).filter(Boolean).forEach((a) => {
        if (addrs().some((x) => x.toLowerCase() === a.toLowerCase())) return;
        const chip = document.createElement('span');
        chip.className = 'vfm-chip' + (EMAIL_RE.test(a) ? '' : ' bad');
        chip.dataset.a = a;
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
  const toBox = chipBox('vtm-to'), ccBox = chipBox('vtm-cc');

  // ------------------------------------------------------------------------------------------------ Transit Monitoring
  let ships = [];
  let checkUrl = 'https://www.sf-international.com/us/en/support/querySupport/waybill';   // from Settings (vf.transit_url)
  let carrier = 'SF';
  let hours = 3;
  const open = new Set();           // shipments whose requests are shown
  const alarmed = new Set();        // alarms already announced (so each one beeps once)
  let transitTimer = null;
  const stageBadge = (s) => { const u = String(s || '').toUpperCase(); return u === 'COMPLETED' ? 'vfm-ok' : (u === 'FOLLOW_UP' || u === 'FAILED' ? 'vfm-err' : (u === 'LAB_RECEIVED' || u === 'IN_TRANSIT' ? 'vfm-info' : 'vfm-warn')); };
  const left = (sec) => { if (sec <= 0) return 'now'; const h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60); return h ? `in ${h} h ${m} min` : `in ${m} min`; };

  const announce = (list) => {
    let fresh = 0;
    list.forEach((s) => {
      const keys = [];
      if (s.alarm) keys.push(`u:${s.id}:${s.alarm_at}`);
      if (s.due && !Number(s.delivered)) keys.push(`d:${s.id}:${s.next_check_at}`);
      keys.forEach((k) => {
        if (alarmed.has(k)) return;
        alarmed.add(k);
        fresh++;
        toast(k[0] === 'u' ? `Tracking update: ${s.tracking_no} - ${s.last_status}` : `Time to check ${s.tracking_no} on ${carrier} (every ${hours} hours).`, k[0] === 'u' ? 'success' : 'info', 12000);
      });
    });
    if (fresh) beep();
  };

  const setBadge = () => {
    const n = ships.filter((s) => s.alarm || (s.due && !Number(s.delivered))).length;
    const b = $('vfm-tb-transit');
    b.hidden = n === 0;
    b.textContent = n;
    b.classList.toggle('pulse', ships.some((s) => s.alarm));
  };

  let tTab = 'CPSIA';
  try { tTab = localStorage.getItem('vfmTransitTab') || 'CPSIA'; } catch (e) { /* private mode */ }
  $('vt-tabs').addEventListener('click', (e) => {
    const b = e.target.closest('[data-tt]'); if (!b) return;
    tTab = b.dataset.tt;
    try { localStorage.setItem('vfmTransitTab', tTab); } catch (err) { /* private mode */ }
    renderTransit();
  });
  const renderTransit = () => {
    const tq = ((tab === 'material' ? $('vm-q') : $('vt-q')) || { value: '' }).value.trim().toLowerCase();
    const hit = (s) => !tq || [s.tracking_no, s.subject, s.last_status].concat((s.items || []).map((i) => [i.tr_no, i.prod_code, i.mtl_no, i.style_name].join(' '))).join(' ').toLowerCase().includes(tq);
    const sorter = (a, b) => (Number(b.alarm) - Number(a.alarm)) || (Number(b.due && !Number(b.delivered)) - Number(a.due && !Number(a.delivered))) || (b.id - a.id);
    // Transit Monitoring is divided into CPSIA and KC Test (the material "In transit" view shows both)
    const grp = (s) => (isKc(s) ? 'KC' : 'CPSIA');
    const cnt = { CPSIA: 0, KC: 0 };
    ships.forEach((s) => { cnt[grp(s)]++; });
    ['CPSIA', 'KC'].forEach((k) => { const el = $('kt-' + k); if (el) el.textContent = cnt[k]; });
    document.querySelectorAll('#vt-tabs .vfm-subtab').forEach((x) => x.classList.toggle('active', x.dataset.tt === tTab));
    const rows = ships.filter((s) => (tab !== 'transit' || grp(s) === tTab) && hit(s)).sort((a, b) => (Number(b.alarm) - Number(a.alarm)) || (Number(b.due && !Number(b.delivered)) - Number(a.due && !Number(a.delivered))) || (b.id - a.id));
    const html = (list) => list.length ? list.map((s) => {
      const items = s.items || [];
      const isOpen = open.has(String(s.id)) || s.alarm;
      const delivered = Number(s.delivered) === 1;
      const mail = s.mailed_at
        ? (s.outbox_status === 'failed' ? `<span class="vfm-badge vfm-err" title="${esc(s.outbox_error || '')}">E-mail failed</span>` : (s.outbox_status === 'sent' ? `<span class="vfm-badge vfm-ok">E-mailed ${esc(fmtDT(s.mailed_at))}</span>` : '<span class="vfm-badge vfm-info">E-mail queued</span>'))
        : '';
      const state = [
        s.alarm ? '<span class="vfm-badge vfm-err">UPDATE</span>' : '',
        s.at_lab ? '<span class="vfm-badge vfm-ok">At the lab</span>' : (delivered ? '<span class="vfm-badge vfm-ok">Delivered</span>' : (s.due ? '<span class="vfm-badge vfm-warn">Check due</span>' : '<span class="vfm-badge vfm-info">In transit</span>')),
        mail,
      ].join(' ');
      const kids = items.map((x) => `
        <tr>
          <td><input type="checkbox" class="vt-pick" data-s="${s.id}" data-i="${x.id}" ${open.has('p' + x.id) ? 'checked' : ''}></td>
          <td class="mono"><b>${esc(x.prod_code || '-')}</b></td>
          <td class="mono"><b>${esc(x.mtl_no)}</b></td>
          <td>${esc(x.style)}</td>
          <td class="mono"><b>${esc(x.application_no || '-')}</b>${x.linked ? '' : ' <span class="vfm-badge vfm-warn" title="No request with this Application No. / MTL# was found in NEXUS-L">not linked</span>'}</td>
          <td>${x.stage ? `<span class="vfm-badge ${stageBadge(x.stage)}">${esc(x.stage)}</span>` : '-'}</td>
          <td>${esc(x.crd || '-')}</td>
        </tr>`).join('');
      return `
      <tr class="vfm-grp ${isOpen ? 'open' : ''} ${s.alarm ? 'vt-alarm' : (s.due && !delivered ? 'vt-due' : '')}" data-g="${s.id}">
        <td><span class="vfm-chev">&#9656;</span><span class="mono">${esc(s.tracking_no)}</span><div class="vfm-sub" style="padding-left:14px">${esc(String(s.anchor_at).slice(0, 16))} &middot; ${esc(String(s.subject || '').slice(0, 70))}</div></td>
        <td><b>${items.length}</b> request(s)</td>
        <td>${s.last_status ? `${esc(s.last_status)}<div class="vfm-sub">${esc(fmtDT(s.last_event_at))}</div>` : '<span class="muted">not checked yet</span>'}</td>
        <td><div class="vfm-sub">last: ${s.last_check_at ? esc(fmtDT(s.last_check_at)) + (s.last_check_by ? ' by ' + esc(s.last_check_by) : '') : '-'}</div><div class="vfm-sub">${s.at_lab ? 'arrived at the lab' : (delivered ? 'done' : 'next: ' + esc(fmtDT(s.next_check_at)) + ' (' + left(Number(s.seconds_to_check)) + ')')}</div></td>
        <td>${state}</td>
        <td><div class="vt-actions">
          ${s.at_lab || isKc(s) ? '' : `<button type="button" class="vfm-btn sm ${s.due && !delivered ? 'warn' : ''}" data-act="sf" data-id="${s.id}" title="Copies the number and opens the ${esc(carrier)} page">Check on ${esc(carrier)}</button>
          <button type="button" class="vfm-btn sm" data-act="rec" data-id="${s.id}">Record check</button>`}
          ${isKc(s) && !s.at_lab ? `<button type="button" class="vfm-btn sm" data-act="kclab" data-id="${s.id}" title="KOTITI received the specimens: the requests go to Testing">Lab received</button>` : ''}
          <button type="button" class="vfm-btn sm primary" data-act="mail" data-id="${s.id}">${isKc(s) ? 'Send KC update e-mail' : 'Send to e-mail'}</button>
          ${s.alarm ? `<button type="button" class="vfm-btn sm red" data-act="seen" data-id="${s.id}">Seen</button>` : ''}
        </div></td>
      </tr>
      <tr class="vfm-kidwrap ${isOpen ? 'open' : ''}" data-kids="${s.id}"><td colspan="6">
        <table class="vfm-kids"><thead><tr><th><input type="checkbox" class="vt-pickall" data-s="${s.id}" title="Select all"></th><th>Prod Code</th><th>MTL#</th><th>Style</th><th>Application No.</th><th>Stage</th><th>CRD</th></tr></thead>
        <tbody>${kids || '<tr><td colspan="7" class="muted">No table rows were found in the e-mail.</td></tr>'}</tbody></table>
      </td></tr>`;
    }).join('') : '<tr><td colspan="6" class="muted" style="text-align:center;padding:16px">No shipment yet. When an e-mail says the samples were sent (with an SF tracking number), it appears here after "Sync Outlook Emails Now".</td></tr>';
    $('vt-body').innerHTML = html(rows);
    // the "In transit" status of Request Material Monitoring shows the same shipments (only those still on their way)
    if (tab === 'material' && matStatus === 'IN_TRANSIT') $('vm-transit-body').innerHTML = html(rows.filter((x) => !x.at_lab && !Number(x.delivered)));
  };

  async function loadTransit() {
    // keep what is ticked while the list refreshes
    document.querySelectorAll('.vt-pick').forEach((c) => { if (c.checked) open.add('p' + c.dataset.i); else open.delete('p' + c.dataset.i); });
    try {
      const j = await call('lab-monitoring/vf-requests/transit');
      ships = j.shipments || [];
      checkUrl = j.check_url || checkUrl;
      carrier = j.carrier || carrier;
      hours = Number(j.hours) || hours;
      $('vt-help').innerHTML = `One row per tracking number from the e-mails that say the samples were sent. It is checked <b>every ${hours} hours</b>, counted from the time that e-mail arrived. The ${esc(carrier)} page may ask for a CAPTCHA that only a person can answer, so: press <b>Check on ${esc(carrier)}</b> (the number is copied and the page opens), read the latest status there, then press <b>Record check</b>. A changed status raises the alarm. Tick the requests and press <b>Send to e-mail</b> to tell the lab. The page to open is set in Settings.`;
      $('kt-ship').textContent = (j.kpi || {}).shipments || 0;
      $('kt-due').textContent = (j.kpi || {}).due || 0;
      $('kt-upd').textContent = (j.kpi || {}).updates || 0;
      $('kt-del').textContent = (j.kpi || {}).delivered || 0;
      announce(ships);
      setBadge();
      if (tab === 'transit' || (tab === 'material' && matStatus === 'IN_TRANSIT')) renderTransit();
    } catch (e) { /* the next round tries again */ }
  }

  const KC_RE = /(^|[^A-Za-z])KC([^A-Za-z]|$)/;
  function isKc(s) { return s.carrier === 'KC' || (s.items || []).some((i) => KC_RE.test(i.package || '')); }
  const onTransitClick = async (e) => {
    const btn = e.target.closest('button[data-act]');
    if (btn) {
      const id = Number(btn.dataset.id);
      const s = ships.find((x) => Number(x.id) === id);   // ids come from MySQL as text
      if (!s) return;
      if (btn.dataset.act === 'sf') {
        try { await navigator.clipboard.writeText(s.tracking_no); } catch (err) { /* copy by hand */ }
        const withNo = checkUrl.includes('{tracking}');
        window.open(withNo ? checkUrl.split('{tracking}').join(encodeURIComponent(s.tracking_no)) : checkUrl, '_blank', 'noopener');
        toast(withNo ? `${s.tracking_no} was put in the address of the ${carrier} page. Read the latest status there, then come back and press "Record check".` : `${s.tracking_no} is copied. On the ${carrier} page paste it in the tracking box, answer any check, read the latest status, then come back and press "Record check".`, 'info', 14000);
      } else if (btn.dataset.act === 'rec') openCheck(s);
      else if (btn.dataset.act === 'mail') openMail(s);
      else if (btn.dataset.act === 'kclab') {
        if (!window.confirm('Did KOTITI (the lab) receive the specimens of ' + s.tracking_no + '? The requests move to Testing; the lab report e-mail completes them.')) return;
        try { const j = await postJson('lab-monitoring/vf-requests/kc/lab-received', { shipment_id: id }); toast(`${j.updated} request(s) are now in Testing.`, 'success'); loadTransit(); } catch (err) { toast(err.message, 'error'); }
      }
      else if (btn.dataset.act === 'seen') { await postJson('lab-monitoring/vf-requests/transit/ack', { shipment_id: id }); loadTransit(); }
      return;
    }
    const pa = e.target.closest('.vt-pickall');
    if (pa) { document.querySelectorAll(`.vt-pick[data-s="${pa.dataset.s}"]`).forEach((c) => { c.checked = pa.checked; }); return; }
    if (e.target.closest('input,label,a,button')) return;
    const grp = e.target.closest('tr.vfm-grp');
    if (!grp) return;
    const id = grp.dataset.g, openNow = !grp.classList.contains('open');
    grp.classList.toggle('open', openNow);
    const kid = e.currentTarget.querySelector(`tr[data-kids="${id}"]`);
    if (kid) kid.classList.toggle('open', openNow);
    if (openNow) open.add(String(id)); else open.delete(String(id));
  };
  $('vt-body').addEventListener('click', onTransitClick);
  $('vm-transit-body').addEventListener('click', onTransitClick);

  // record what the SF page shows
  const openCheck = (s) => {
    $('vc-id').value = s.id;
    $('vc-trk').textContent = s.tracking_no;
    $('vc-status').value = s.last_status || '';
    $('vc-delivered').checked = Number(s.delivered) === 1;
    const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    $('vc-when').value = d.toISOString().slice(0, 16);
    $('vc-hist').innerHTML = (s.history || []).map((h) => `<div>${esc(String(h.at).slice(5, 16))} &middot; ${esc(h.what)}${h.by ? ' <span class="muted">(' + esc(h.by) + ')</span>' : ''}</div>`).join('') || '<span class="muted">Nothing yet.</span>';
    $('vft-check-modal').classList.add('open');
  };
  $('vc-cancel').addEventListener('click', () => $('vft-check-modal').classList.remove('open'));
  $('vc-save').addEventListener('click', async () => {
    const btn = $('vc-save'); btn.disabled = true;
    try {
      const j = await postJson('lab-monitoring/vf-requests/transit/check', { shipment_id: Number($('vc-id').value), status_text: $('vc-status').value, event_at: $('vc-when').value, delivered: $('vc-delivered').checked });
      $('vft-check-modal').classList.remove('open');
      toast(j.changed ? 'Saved: the status changed, so this shipment is marked UPDATE. Tick its requests and press "Send to e-mail" to tell the lab.' : 'Saved: no change since the last check. The next check is scheduled.', j.changed ? 'success' : 'info');
      loadTransit();
    } catch (e) { toast(e.message, 'error'); }
    btn.disabled = false;
  });

  // e-mail the lab
  const mailCtx = { id: 0, items: [], kc: false, extra: [] };
  const renderAttached = (auto) => {
    $('vtm-attached').innerHTML = '<thead><tr><th>File</th><th>Size</th><th></th></tr></thead><tbody>'
      + (auto || []).map((f) => `<tr><td class="mono">${esc(f.filename)}</td><td>${Math.max(1, Math.round(f.bytes / 1024))} KB</td><td><span class="vfm-badge vfm-ok">automatic</span></td></tr>`).join('')
      + mailCtx.extra.map((f, i) => `<tr><td class="mono">${esc(f.name)}</td><td>${Math.max(1, Math.round(f.size / 1024))} KB</td><td><button type="button" class="vfm-btn sm" data-rm="${i}">Remove</button></td></tr>`).join('') + '</tbody>';
  };
  let lastAuto = [];
  const loadPreview = async () => {
    const j = await postJson('lab-monitoring/vf-requests/transit/email-preview', { shipment_id: mailCtx.id, item_ids: mailCtx.items, to: toBox.get().join('; '), cc: ccBox.get().join('; '), subject: $('vtm-subj').value, tracking_no: mailCtx.kc ? $('vtm-trk').value : '' });
    return j;
  };
  const openMail = async (s) => {
    const ticked = [...document.querySelectorAll(`.vt-pick[data-s="${s.id}"]:checked`)].filter((c) => c.offsetParent).map((c) => Number(c.dataset.i));
    const all = (s.items || []).map((x) => x.id);
    mailCtx.id = s.id;
    mailCtx.items = ticked.length ? ticked : all;
    mailCtx.kc = isKc(s);
    mailCtx.extra = [];
    $('vtm-files').value = '';
    $('vtm-title').textContent = s.tracking_no;
    $('vtm-note').textContent = ticked.length ? `The ${ticked.length} ticked request(s) are in the e-mail.` : 'No row is ticked: every request of this shipment is in the e-mail.';
    $('vtm-kc').style.display = mailCtx.kc ? '' : 'none';
    try {
      const j = await postJson('lab-monitoring/vf-requests/transit/email-preview', { shipment_id: s.id, item_ids: mailCtx.items });
      toBox.set(j.to); ccBox.set(j.cc);
      $('vtm-subj').value = j.subject;
      $('vtm-preview').srcdoc = j.body;
      $('vtm-count').textContent = j.count;
      if (mailCtx.kc) { $('vtm-trk').value = j.pending ? '' : (j.tracking_no || ''); lastAuto = j.files || []; renderAttached(lastAuto); }
      $('vft-mail-modal').classList.add('open');
    } catch (e) { toast(e.message, 'error'); }
  };
  let trkTmr = null;
  $('vtm-trk').addEventListener('input', () => { clearTimeout(trkTmr); trkTmr = setTimeout(async () => { try { const j = await loadPreview(); $('vtm-preview').srcdoc = j.body; } catch (e) { /* the send reports it */ } }, 400); });
  $('vtm-files').addEventListener('change', async (e) => {
    for (const f of e.target.files) {
      if (f.size > 20 * 1024 * 1024) { toast(f.name + ' is larger than 20 MB.', 'error'); continue; }
      const data = await new Promise((ok, no) => { const r = new FileReader(); r.onload = () => ok(String(r.result).split(',')[1] || ''); r.onerror = no; r.readAsDataURL(f); });
      mailCtx.extra.push({ name: f.name, size: f.size, data });
    }
    e.target.value = '';
    renderAttached(lastAuto);
  });
  $('vtm-attached').addEventListener('click', (e) => { const b = e.target.closest('[data-rm]'); if (b) { mailCtx.extra.splice(Number(b.dataset.rm), 1); renderAttached(lastAuto); } });
  $('vtm-cancel').addEventListener('click', () => $('vft-mail-modal').classList.remove('open'));
  $('vtm-send').addEventListener('click', async () => {
    const toList = toBox.get(), ccList = ccBox.get();
    const bad = [...toList, ...ccList].filter((a) => !EMAIL_RE.test(a));
    if (!toList.length) { toast('Add at least one recipient in To.', 'error'); return; }
    if (bad.length) { toast('These are not valid e-mail addresses: ' + bad.join(', '), 'error'); return; }
    const btn = $('vtm-send'); btn.disabled = true;
    try {
      const j = await postJson('lab-monitoring/vf-requests/transit/send-email', { shipment_id: mailCtx.id, item_ids: mailCtx.items, to: toList.join('; '), cc: ccList.join('; '), subject: $('vtm-subj').value, tracking_no: mailCtx.kc ? $('vtm-trk').value : '', extra_files: mailCtx.kc ? mailCtx.extra.map((f) => ({ name: f.name, data: f.data })) : [] });
      $('vft-mail-modal').classList.remove('open');
      toast(`E-mail queued (#${j.outbox_id}) for ${j.count} request(s) to ${j.to}. The Hub sends it; its status shows on the shipment.`, 'success');
      loadTransit();
    } catch (e) { toast(e.message, 'error'); }
    btn.disabled = false;
  });
  let vtT = 0; $('vt-q').addEventListener('input', () => { clearTimeout(vtT); vtT = setTimeout(renderTransit, 150); });
  $('vt-sync').addEventListener('click', async () => {
    const b = $('vt-sync'); b.disabled = true;
    try {
      const j = await call('interlink/vf-test-request/sync-emails', { method: 'POST', body: '{}' });
      toast(`Scanned ${j.scanned} e-mail(s): ${j.transit_updates} transit row(s), ${j.lab_received} lab received, ${j.certificates_saved} certificate(s).`, 'success');
      loadTransit();
    } catch (e) { toast(e.message, 'error'); }
    b.disabled = false;
  });

  // ------------------------------------------------------------------------------------------------ Request Material Monitoring
  // One tab per status; inside a tab the requests are grouped by the e-mail that asked for them (like Email Submission), and each status shows its own columns.
  const STATUS_HELP = {
    SENDING: 'Submitted to InterLink / e-mailed back, but the samples are not on their way yet.',
    IN_TRANSIT: 'The samples were sent (tracking number from the shipment e-mail). See Transit Monitoring for the checks.',
    TESTING: 'The lab received the samples: Report No. and Expected Due Date came from the lab\'s order notification.',
    DONE: 'The lab report (certificate) arrived and is attached to the request.',
    FOLLOW_UP: 'The lab asks for something (the remark is shown where the Expected Due Date would be) or the test failed.',
  };
  const kcell = {
    prod: (r) => `<td class="mono"><b>${esc(r.prod_code || '-')}</b></td>`,
    mtl: (r) => `<td class="mono"><b>${esc(r.mtl_no)}</b></td>`,
    style: (r) => `<td>${esc(r.style_no)}</td>`,
    app: (r) => `<td class="mono"><b>${esc(r.application_no || '-')}</b></td>`,
    interlink: (r) => `<td>${r.interlink_status ? `<span class="vfm-badge ${/submitted|done|ok/i.test(r.interlink_status) ? 'vfm-ok' : (/fail/i.test(r.interlink_status) ? 'vfm-err' : 'vfm-warn')}">${esc(r.interlink_status)}</span>` : '-'}</td>`,
    pdf: (r) => `<td>${r.has_etrf_pdf ? `<a href="${base}lab-monitoring/vf-requests/pdf/etrf/${r.id}" target="_blank">View PDF</a>` : '<span class="muted">-</span>'}</td>`,
    mailed: (r) => `<td>${r.etrf_emailed_at ? `<span class="vfm-badge vfm-ok">E-mailed ${esc(fmtDT(r.etrf_emailed_at))}</span>` : '<span class="vfm-badge vfm-warn">Not yet</span>'}</td>`,
    track: (r) => `<td class="mono">${esc(r.container_no || '-')}</td>`,
    tstatus: (r) => `<td title="${esc(r.ship_status || r.transit_status || '')}">${esc(String(r.ship_status || r.transit_status || '-').slice(0, 60))}${r.ship_event_at ? `<div class="vfm-sub">${esc(fmtDT(r.ship_event_at))}</div>` : ''}</td>`,
    lab: (r) => `<td>${esc(r.lab_name || '-')}</td>`,
    report: (r) => `<td class="mono">${esc(r.lab_report_no || '-')}</td>`,
    due: (r) => `<td>${r.expected_due_remark ? `<span class="vfm-remark" title="${esc(r.expected_due_remark)}">${esc(r.expected_due_remark)}</span>` : esc(r.expected_due_date || '-')}</td>`,
    result: (r) => `<td>${r.test_result ? `<span class="vfm-badge ${/^pass/i.test(r.test_result) ? 'vfm-ok' : 'vfm-err'}">${esc(r.test_result)}</span>` : '-'}</td>`,
    issued: (r) => `<td>${esc(r.issue_date || '-')}</td>`,
    valid: (r) => `<td>${esc(r.validity_date || '-')}</td>`,
    cert: (r) => `<td>${r.has_certificate ? `<a href="${base}lab-monitoring/vf-requests/pdf/cert/${r.id}" target="_blank">Certificate PDF</a>` : '<span class="muted">-</span>'}</td>`,
    mail: (r) => `<td>${r.has_email || r.follow_reason || r.expected_due_remark ? `<button type="button" class="vfm-btn sm" data-mail="${r.id}">View e-mail</button>` : '<span class="muted">-</span>'}</td>`,
    reason: (r) => `<td><span class="vfm-remark" title="${esc(r.follow_reason || '')}">${esc(r.expected_due_remark || r.follow_reason || '-')}</span></td>`,
  };
  const COMMON = [['Prod Code', 'prod'], ['MTL#', 'mtl'], ['Style', 'style'], ['Application No.', 'app']];
  const LAYOUT = {
    TESTING: [...COMMON, ['Tracking no.', 'track'], ['Lab', 'lab'], ['Lab Report No.', 'report'], ['Expected Due Date', 'due']],
    DONE: [...COMMON, ['Lab Report No.', 'report'], ['Result', 'result'], ['Issued', 'issued'], ['Valid until', 'valid'], ['Certificate', 'cert'], ['E-mail', 'mail']],
    FOLLOW_UP: [...COMMON, ['Lab Report No.', 'report'], ['Remark / reason', 'reason'], ['Result', 'result'], ['E-mail', 'mail']],
  };
  let matStatus = 'TESTING', matPage = 1, matFirst = true, matGroups = [], matTimer = null;
  const MAT_GROUPS = 10, MAT_KIDS = 120;
  const matOpen = new Set(), matShown = {};

  const groupRows = (rows) => {
    const by = new Map();
    rows.forEach((r) => {
      const key = r.batch_id ? String(r.batch_id) : '0';
      if (!by.has(key)) by.set(key, { key, subject: r.batch_subject || '', sender: r.batch_sender_name || r.batch_sender || '', email: r.batch_sender || '', at: r.batch_received_at || '', pkg: r.test_package, rows: [] });
      by.get(key).rows.push(r);
    });
    return [...by.values()].sort((a, b) => (a.key === '0') - (b.key === '0') || String(b.at).localeCompare(String(a.at)) || Number(b.key) - Number(a.key));
  };

  const kidsHtml = (g) => {
    const cols = LAYOUT[matStatus];
    const shown = matShown[g.key] || MAT_KIDS;
    const list = g.rows.slice(0, shown);
    return `<table class="vfm-kids"><thead><tr>${cols.map((c) => `<th>${esc(c[0])}</th>`).join('')}</tr></thead>
      <tbody>${list.map((r) => `<tr>${cols.map((c) => kcell[c[1]](r)).join('')}</tr>`).join('')}</tbody></table>
      ${g.rows.length > shown ? `<div style="padding:10px 18px"><button type="button" class="vfm-btn sm" data-more="${g.key}">Show ${Math.min(MAT_KIDS, g.rows.length - shown)} more (${g.rows.length - shown} left)</button></div>` : ''}`;
  };

  const renderMaterial = () => {
    const pages = Math.max(1, Math.ceil(matGroups.length / MAT_GROUPS));
    if (matPage > pages) matPage = pages;
    const view = matGroups.slice((matPage - 1) * MAT_GROUPS, matPage * MAT_GROUPS);
    $('vm-body').innerHTML = view.length ? view.map((g) => {
      const isOpen = matOpen.has(g.key);
      const title = g.key === '0'
        ? '<b>Imported history</b><div class="vfm-sub" style="padding-left:14px">requests that did not come from a request e-mail in NEXUS-L</div>'
        : `<b>#${esc(g.key)} &middot; ${esc(g.sender || g.email || 'E-mail')}</b>${g.email && g.sender ? ` <span class="vfm-sub">&lt;${esc(g.email)}&gt;</span>` : ''}<div class="vfm-sub" style="padding-left:14px">${esc(String(g.at || '').slice(0, 16))}</div>`;
      return `
      <tr class="vfm-grp ${isOpen ? 'open' : ''}" data-mg="${g.key}">
        <td><span class="vfm-chev">&#9656;</span>${title}</td>
        <td>${esc(String(g.subject || '').slice(0, 90)) || '<span class="muted">-</span>'}</td>
        <td>${esc(g.pkg || '-')}</td>
        <td><b>${g.rows.length}</b> request(s)</td>
      </tr>
      <tr class="vfm-kidwrap ${isOpen ? 'open' : ''}" data-mk="${g.key}"><td colspan="4">${isOpen ? kidsHtml(g) : ''}</td></tr>`;
    }).join('') : `<tr><td colspan="4" class="muted" style="text-align:center;padding:22px">No request is in this status.</td></tr>`;
    const info = matGroups.length ? `${(matPage - 1) * MAT_GROUPS + 1}-${Math.min(matGroups.length, matPage * MAT_GROUPS)} of ${matGroups.length} e-mail group(s)` : '';
    $('vm-pager').innerHTML = pages > 1
      ? `<span class="vfm-pg-info">${info}</span><button type="button" class="vfm-btn sm" data-mp="${matPage - 1}" ${matPage <= 1 ? 'disabled' : ''}>&lsaquo; Prev</button><span>Page ${matPage} / ${pages}</span><button type="button" class="vfm-btn sm" data-mp="${matPage + 1}" ${matPage >= pages ? 'disabled' : ''}>Next &rsaquo;</button>`
      : `<span class="vfm-pg-info">${info}</span>`;
  };

  async function loadMaterial() {
    const q = new URLSearchParams({ status: matStatus, package: $('vm-pkg').value, q: $('vm-q').value.trim() });
    try {
      const j = await call('lab-monitoring/vf-requests/material?' + q.toString());
      const counts = j.counts || {};
      Object.entries(counts).forEach(([k, v]) => { const el = $('km-' + k); if (el) el.textContent = v; });
      if (matFirst) {
        matFirst = false;
        if (!Number(counts[matStatus]) && Object.keys(counts).some((k) => Number(counts[k]) > 0)) {
          matStatus = Object.keys(LAYOUT).find((k) => Number(counts[k]) > 0) || matStatus;
          document.querySelectorAll('#vm-tabs .vfm-subtab').forEach((b) => b.classList.toggle('active', b.dataset.status === matStatus));
          return loadMaterial();
        }
      }
      $('vm-help').textContent = STATUS_HELP[matStatus] || '';
      const isT = matStatus === 'IN_TRANSIT';
      $('vm-main').hidden = isT; $('vm-pager').hidden = isT; $('vm-transit').hidden = !isT;
      if (isT) { await loadTransit(); renderTransit(); return; }
      matGroups = groupRows(j.rows || []);
      renderMaterial();
    } catch (e) { $('vm-body').innerHTML = `<tr><td colspan="4" class="muted" style="padding:16px">${esc(e.message)}</td></tr>`; }
  }
  $('vm-tabs').addEventListener('click', (e) => {
    const b = e.target.closest('.vfm-subtab');
    if (!b) return;
    matStatus = b.dataset.status; matPage = 1;
    document.querySelectorAll('#vm-tabs .vfm-subtab').forEach((x) => x.classList.toggle('active', x === b));
    loadMaterial();
  });
  $('vm-pkg').addEventListener('change', () => { matPage = 1; loadMaterial(); });
  $('vm-q').addEventListener('input', () => { clearTimeout(matTimer); matTimer = setTimeout(() => { matPage = 1; loadMaterial(); }, 250); });
  $('vm-pager').addEventListener('click', (e) => { const b = e.target.closest('[data-mp]'); if (b && !b.disabled) { matPage = Number(b.dataset.mp); renderMaterial(); } });
  $('vm-body').addEventListener('click', async (e) => {
    const more = e.target.closest('[data-more]');
    if (more) { const k = more.dataset.more; matShown[k] = (matShown[k] || MAT_KIDS) + MAT_KIDS; renderMaterial(); return; }
    const mail = e.target.closest('[data-mail]');
    if (mail) {
      try {
        const j = await call('lab-monitoring/vf-requests/completion-email/' + mail.dataset.mail);
        const m = j.mail;
        $('mv-title').textContent = m.cert_email_subject || 'Follow-up';
        $('mv-meta').textContent = `${m.prod_code || ''} ${m.mtl_no || ''} ${m.generated_application_no ? '· ' + m.generated_application_no : ''} ${m.lab_report_no ? '· Report ' + m.lab_report_no : ''} ${m.cert_email_at ? '· received ' + m.cert_email_at : ''} ${m.certificate_pdf_name ? '· PDF: ' + m.certificate_pdf_name : ''}`;
        const f = $('mv-fail');
        const why = [m.expected_due_remark ? 'Lab remark: ' + m.expected_due_remark : '', m.fail_reason ? 'Follow-up: ' + m.fail_reason : ''].filter(Boolean).join('\n');
        f.style.display = why ? '' : 'none'; f.textContent = why;
        $('mv-body').textContent = m.cert_email_body || '(no message text was kept)';
        $('vfm-mailview-modal').classList.add('open');
      } catch (err) { toast(err.message, 'error'); }
      return;
    }
    if (e.target.closest('input,label,a,button')) return;
    const grp = e.target.closest('tr.vfm-grp');
    if (!grp) return;
    const k = grp.dataset.mg, now = !grp.classList.contains('open');
    if (now) matOpen.add(k); else matOpen.delete(k);
    renderMaterial();
  });
  $('mv-close').addEventListener('click', () => $('vfm-mailview-modal').classList.remove('open'));

  // ------------------------------------------------------------------------------------------------ start + live refresh
  // the badge / alarm are kept current on every tab; the lists refresh while their tab is open
  loadTransit();
  // live: every 6 s (the server reads a newly stored Outlook e-mail at once), nothing is touched while a dialog is open
  const livePoll = () => {
    if (document.hidden || document.querySelector('.open[id^="vf"][id$="-modal"]')) return;
    loadTransit();
    if (tab === 'material' && matStatus !== 'IN_TRANSIT') loadMaterial();
    if (tab === 'material' && matStatus === 'IN_TRANSIT') loadMaterial();
  };
  transitTimer = setInterval(livePoll, 6000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) livePoll(); });
  let startTab = 'email';
  try { startTab = localStorage.getItem('vfmTab') || 'email'; } catch (e) { /* private mode */ }
  if (location.hash === '#transit' || location.hash === '#material') startTab = location.hash.slice(1);
  showTab(startTab);
})();
