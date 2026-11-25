/**
 * Quality Insights: line map, failure modes, supplier scorecard.
 * Every view is drawn at once from a short-lived cache when it was seen a moment ago, otherwise a shimmer stands in while the numbers load.
 */
(() => {
  'use strict';
  const root = document.getElementById('qi-root');
  if (!root) return;
  const base = root.dataset.base.replace(/\/?$/, '/');
  const today = root.dataset.today;
  const $ = (s, c = root) => c.querySelector(s);
  const $$ = (s, c = root) => [...c.querySelectorAll(s)];
  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const state = { tab: 'lines', view: 'month', cur: new Date(today + 'T12:00:00'), group: 'line', supMode: 'period', min: 5 };
  const cache = new Map();                 // url -> { t, data }
  const FRESH = 20000;
  const pending = {};                      // one live request per panel

  // ---------------------------------------------------------------- data
  async function get(path, params, panel, force) {
    const url = base + path + '?' + new URLSearchParams(params).toString();
    const hit = cache.get(url);
    if (hit && !force && Date.now() - hit.t < FRESH) return { data: hit.data, cached: true };
    if (pending[panel]) pending[panel].abort();
    const ac = pending[panel] = new AbortController();
    const res = await fetch(url, { headers: { Accept: 'application/json' }, signal: ac.signal, credentials: 'same-origin' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    if (!data || !data.ok) throw new Error((data && data.error) || 'Bad answer');
    cache.set(url, { t: Date.now(), data });
    return { data, cached: false };
  }
  const peek = (path, params) => { const h = cache.get(base + path + '?' + new URLSearchParams(params).toString()); return h ? h.data : null; };

  // ---------------------------------------------------------------- early warnings (a line / style / cement that keeps failing)
  async function loadWarnings() {
    const box = $('#qi-warn'); if (!box) return;
    try {
      const r = await fetch(base + 'warnings', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const j = r.ok ? await r.json() : null;
      if (!j || !j.ok || !j.warnings.length) { box.hidden = true; return; }
      const c = j.config;
      box.innerHTML = '<div class="qi-warn-h"><b>Early warning</b><small>' + j.warnings.length + ' to watch: ' + c.streak + '+ Lab Refs failing in a row, or ' + c.rate_pct + '%+ of ' + c.rate_min + '+ Lab Refs failing in 7 days</small></div>'
        + '<div class="qi-warn-list">' + j.warnings.slice(0, 12).map((w) => '<span class="qi-warn-chip ' + esc(w.severity) + '" title="' + esc(w.text) + '"><b>' + esc(w.group) + ' ' + esc(w.name) + '</b> '
        + (w.kind === 'streak' ? esc(w.streak) + ' in a row' : esc(w.fail_rate) + '% (' + esc(w.fails) + '/' + esc(w.rated) + ')') + '</span>').join('') + '</div>';
      box.hidden = false;
    } catch (e) { box.hidden = true; }
  }
  loadWarnings();

  // ---------------------------------------------------------------- Failed sample CAPA: Excel fields, up to 3 photos, 72h deleted-photo audit, batch links & real-time updates
  const capa = { status: 'Open', edit: false, cases: [], page: 1, pageSize: 8, selected: new Set(), sig: '' };
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const capaLb = $('#qi-capa-lb');
  capaLb?.addEventListener('click', () => { capaLb.hidden = true; });
  const renderCapa = () => {
    const list = $('#qi-capa-list');
    const pager = $('#qi-capa-pager');
    const q = ($('#qi-capa-q')?.value || '').trim().toLowerCase();
    const rows = q ? capa.cases.filter((c) => [c.ref, c.line, c.style, c.cement, c.mode, c.prepared_by, c.retest].join(' ').toLowerCase().includes(q)) : capa.cases;
    const total = rows.length;
    const pages = Math.max(1, Math.ceil(total / capa.pageSize));
    if (capa.page > pages) capa.page = pages;
    if (capa.page < 1) capa.page = 1;
    const start = (capa.page - 1) * capa.pageSize;
    const slice = rows.slice(start, start + capa.pageSize);
    list.innerHTML = slice.length ? slice.map(capaCard).join('') : '<p class="qi-empty">' + (q ? 'No CAPA cases match "' + esc(q) + '".' : 'No CAPA cases in this view.') + '</p>';
    const mkBtn = $('#qi-capa-mklink');
    if (mkBtn) mkBtn.textContent = capa.selected.size ? ('🔗 Create CAPA Link (' + capa.selected.size + ')') : '🔗 Create CAPA Link';
    if (pager) {
      if (total <= capa.pageSize) {
        pager.hidden = true;
        pager.innerHTML = '';
      } else {
        pager.hidden = false;
        const from = start + 1, to = Math.min(total, start + slice.length);
        pager.innerHTML = '<span class="qi-capa-pinfo">Showing ' + from + '–' + to + ' of ' + total + '</span>'
          + '<div class="qi-capa-pbtns">'
          + '<button type="button" class="cal-btn-glass" data-p="prev"' + (capa.page <= 1 ? ' disabled' : '') + '>&larr; Previous</button>'
          + '<span class="qi-capa-pnum">Page ' + capa.page + ' of ' + pages + '</span>'
          + '<button type="button" class="cal-btn-glass" data-p="next"' + (capa.page >= pages ? ' disabled' : '') + '>Next &rarr;</button>'
          + '</div>';
      }
    }
  };
  async function loadCapa(silent = false) {
    const list = $('#qi-capa-list');
    if (!silent) {
      list.innerHTML = skelRows(4);
      const pager = $('#qi-capa-pager'); if (pager) pager.hidden = true;
    }
    try {
      const r = await fetch(base + 'capa?status=' + capa.status, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const j = await r.json(); if (!j.ok) throw new Error('bad');
      const nextSig = JSON.stringify([j.open, j.closed, j.overdue, (j.cases || []).map((x) => [x.id, x.status, x.submitted_at, (x.photos || []).length, (x.deleted_photos || []).length, x.root_cause, x.action, x.preventive_action, x.retest])]);
      if (silent && nextSig === capa.sig) return;
      capa.sig = nextSig;
      capa.edit = !!j.can_edit;
      capa.cases = j.cases || [];
      $('#qi-capa-hint').textContent = 'Failed bonding Lab Refs since ' + j.since + ' (real-time photo & link updates active).';
      $('#qi-capa-kpis').innerHTML = [['Open Cases', j.open, j.open ? 'amber' : 'green'], ['Open 3+ Days', j.overdue, j.overdue ? 'red' : 'green'], ['Closed Cases', j.closed, 'green']]
        .map(([l, n, c]) => '<div class="qi-kpi ' + c + '"><b>' + n + '</b><span>' + l + '</span></div>').join('');
      const badge = $('#qi-capa-badge'); badge.hidden = !j.open; badge.textContent = j.open;
      const mkBtn = $('#qi-capa-mklink'); if (mkBtn) mkBtn.hidden = !capa.edit;
      renderCapa();
    } catch (e) { if (!silent) list.innerHTML = '<p class="qi-note">Could not load the CAPA cases.</p>'; }
  }
  const capaCard = (c) => {
    const closed = c.status === 'Closed', late = !closed && c.age >= 3, ro = closed || !capa.edit ? ' disabled' : '';
    const photos = c.photos || [], delPhotos = c.deleted_photos || [], hist = c.history || [];
    const hasCause = !!(c.root_cause || '').trim();
    const hasAction = !!(c.action || '').trim();
    const hasPhoto = photos.length > 0;
    const hasRetest = !!(c.retest || '').trim();
    const readyToClose = !closed && hasCause && hasAction && hasRetest;
    const stepPill = (ok, lbl) => '<span class="qi-step ' + (ok ? 'ok' : 'miss') + '">' + (ok ? '&#10003;' : '&#9675;') + ' ' + lbl + '</span>';
    const cands = (c.candidates || []).map((x) => '<button type="button" class="qi-chip qi-capa-cand" data-pick="' + esc(x.ref) + '" title="Passed ' + esc(x.tested) + '">&#9889; ' + esc(x.ref) + ' passed</button>').join('');
    const badgeCls = closed ? 'green' : (readyToClose ? 'green' : (late ? 'red' : 'amber'));
    const badgeTxt = closed ? 'CLOSED' : (readyToClose ? 'READY' : (late ? 'OVERDUE · ' + c.age + 'd' : 'OPEN · ' + c.age + 'd'));
    const checked = capa.selected.has(c.id) ? ' checked' : '';
    const slotsHtml = [0, 1, 2].map((idx) => {
      const p = photos[idx];
      if (p) {
        return '<div class="qi-capa-thumb" data-pid="' + p.id + '"><img src="' + esc(p.url) + '" alt="CAPA photo ' + (idx + 1) + '" loading="lazy" decoding="async">'
          + (closed || !capa.edit ? '' : '<button type="button" class="qi-capa-rm" data-rm-photo="' + p.id + '" title="Remove photo (kept 72h in audit history)">&times;</button>') + '</div>';
      }
      return '<div class="qi-capa-slot-empty" title="Empty photo slot ' + (idx + 1) + ' of 3">' + (idx + 1) + '</div>';
    }).join('');
    const delHtml = delPhotos.length ? '<details class="qi-capa-delhist"><summary>Deleted photos (' + delPhotos.length + ' · 72h audit)</summary><div class="qi-capa-delgrid">'
      + delPhotos.map((p) => '<div class="qi-capa-delitem"><img src="' + esc(p.url) + '" alt="Removed photo"><div><b>' + esc(p.orig_name || 'Photo') + '</b><small>Removed by ' + esc(p.deleted_by || 'Staff') + ' · ' + esc((p.deleted_at || '').slice(0, 16)) + ' (' + (p.hours_left || 1) + 'h left)</small></div></div>').join('')
      + '</div></details>' : '';
    const histHtml = hist.length ? '<details class="qi-capa-delhist"><summary>History (' + hist.length + ')</summary><ul class="qi-capa-hlist">'
      + hist.slice(0, 6).map((h) => '<li><b>' + esc(h.actor) + '</b>: ' + esc(h.detail) + ' <small>' + esc((h.created_at || '').slice(5, 16)) + '</small></li>').join('')
      + '</ul></details>' : '';

    return '<article class="cal-glass qi-capa ' + (closed ? 'done' : (readyToClose ? 'ready' : (late ? 'late' : ''))) + '" data-id="' + c.id + '">'
      + '<header class="qi-capa-head">'
      + '<div class="qi-capa-id">'
      + (!closed && capa.edit ? '<input type="checkbox" class="qi-capa-sel" data-sel="' + c.id + '" title="Select for batch CAPA link"' + checked + '>' : '')
      + (c.line ? '<span class="qi-capa-line">' + esc(c.line) + '</span>' : '')
      + '<b class="qi-capa-ref">' + esc(c.ref) + '</b>'
      + '<span class="qi-badge ' + badgeCls + '" style="margin-left:auto">' + esc(badgeTxt) + '</span>'
      + '<span class="qi-capa-meta">' + esc(c.style || '—') + (c.cement ? ' · ' + esc(c.cement) : '') + '</span>'
      + '</div>'
      + '<div class="qi-capa-right">'
      + (c.submitted_at ? '<span class="qi-pill ok" title="Submitted via link by ' + esc(c.submitted_by) + '">Link Submitted</span>' : '')
      + (c.mode ? '<span class="qi-pill bad" title="' + esc(c.mode) + '">' + esc(c.mode) + '</span>' : '')
      + '<span class="qi-capa-date">Failed ' + esc(c.failed_date || '—') + '</span>'
      + '</div>'
      + '<div class="qi-capa-steps" aria-label="Completion checklist">'
      + stepPill(hasCause, 'Cause') + stepPill(hasAction, 'Action') + stepPill(hasPhoto, 'Photo') + stepPill(hasRetest, 'Retest')
      + '</div>'
      + '</header>'
      + '<div class="qi-capa-meta-in">'
      + '<label><span>Prepared by</span><input type="text" data-f="prepared_by" value="' + esc(c.prepared_by || '') + '" placeholder="Name"' + ro + '></label>'
      + '<label><span>Production Date</span><input type="date" data-f="production_date" value="' + esc(c.production_date || '') + '"' + ro + '></label>'
      + '</div>'
      + '<div class="qi-capa-grid">'
      + '<label class="' + (hasCause ? 'is-ok' : 'is-miss') + '"><span>Problem Statement</span><textarea rows="2" data-f="root_cause" placeholder="Describe why it failed…"' + ro + '>' + esc(c.root_cause) + '</textarea></label>'
      + '<label class="' + (hasAction ? 'is-ok' : 'is-miss') + '"><span>Action Plan</span><textarea rows="2" data-f="corrective_action" placeholder="Action taken on line / adhesive / process…"' + ro + '>' + esc(c.action) + '</textarea></label>'
      + '<label><span>Preventive Action</span><textarea rows="1" data-f="preventive_action" placeholder="Preventive measure…"' + ro + '>' + esc(c.preventive_action || '') + '</textarea></label>'
      + '</div>'
      + '<div class="qi-capa-photos">'
      + '<div class="qi-capa-ptop"><span>Photos (' + photos.length + ' / 3)</span>'
      + (closed || !capa.edit ? '' : '<div class="qi-capa-pbtns-sm"><input type="file" accept="image/*" capture="environment" class="qi-f-cam" hidden><input type="file" accept="image/*" class="qi-f-up" hidden>'
        + '<button type="button" class="qi-mini-btn" data-cam' + (photos.length >= 3 ? ' disabled' : '') + ' title="Take photo with camera">📷</button>'
        + '<button type="button" class="qi-mini-btn" data-up' + (photos.length >= 3 ? ' disabled' : '') + ' title="Upload image">📁 Upload</button></div>')
      + '</div>'
      + '<div class="qi-capa-thumbs">' + slotsHtml + '</div>'
      + delHtml + histHtml
      + '</div>'
      + '<div class="qi-capa-retest">'
      + '<label class="' + (hasRetest ? 'is-ok' : 'is-miss') + '"><span>Retest Lab Ref</span><input type="text" data-f="retest_lab_ref" value="' + esc(c.retest) + '"' + ro + ' placeholder="e.g. ' + esc(c.ref) + '-R01"></label>'
      + (closed || !capa.edit ? '' : '<div class="qi-capa-cands">' + (cands ? '<span>Passing:</span> ' + cands : '<small>No passing retest saved yet.</small>') + '</div>')
      + '</div>'
      + (closed
        ? '<footer class="qi-capa-foot"><small class="qi-capa-closed">Closed by <b>' + esc(c.closed_by || '—') + '</b>' + (c.closed_at ? ' on ' + esc(c.closed_at.slice(0, 10)) : '') + '</small></footer>'
        : (!capa.edit ? '' : '<footer class="qi-capa-act"><button type="button" class="cal-btn-glass" data-act="save">Save draft</button><button type="button" class="qi-capa-close-btn" data-act="close">Verify &amp; Close</button><small class="qi-capa-msg" role="status"></small></footer>'))
      + '</article>';
  };
  $('#qi-capa-q')?.addEventListener('input', () => { capa.page = 1; renderCapa(); });
  $('#qi-capa-pager')?.addEventListener('click', (e) => {
    const b = e.target.closest('[data-p]'); if (!b || b.disabled) return;
    capa.page += (b.dataset.p === 'next' ? 1 : -1);
    renderCapa();
    $('#qi-panel-capa')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  $('#qi-capa-export')?.addEventListener('click', () => { window.location.href = base + 'capa/export?status=' + encodeURIComponent(capa.status); });
  $('#qi-capa-mklink')?.addEventListener('click', async () => {
    let ids = [...capa.selected];
    if (!ids.length) {
      ids = capa.cases.filter((c) => c.status === 'Open').slice(0, 8).map((c) => c.id);
    }
    if (!ids.length) { alert('No open CAPA cases available to include in a link.'); return; }
    const box = $('#qi-capa-linkbox');
    const btn = $('#qi-capa-mklink'); btn.disabled = true;
    try {
      const r = await fetch(base + 'capa/link', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' },
        body: JSON.stringify({ ids })
      });
      const j = await r.json();
      if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
      if (!r.ok || !j.ok) throw new Error(j.message || 'Could not create link.');
      if (box) {
        box.hidden = false;
        box.innerHTML = '<div><b>CAPA Link Ready (' + j.count + ' card' + (j.count > 1 ? 's' : '') + '):</b> <input type="text" readonly value="' + esc(j.url) + '" id="qi-capa-link-in"></div>'
          + '<div class="qi-capa-lacts"><button type="button" class="cal-btn-glass" id="qi-capa-copy">Copy Link</button><a class="cal-btn-glass" href="' + esc(j.url) + '" target="_blank" rel="noopener">Open</a><button type="button" class="cal-btn-text" id="qi-capa-lclose">&times;</button></div>';
        $('#qi-capa-copy')?.addEventListener('click', () => {
          const inp = $('#qi-capa-link-in'); inp?.select();
          navigator.clipboard?.writeText(j.url).catch(() => document.execCommand('copy'));
          $('#qi-capa-copy').textContent = 'Copied!';
        });
        $('#qi-capa-lclose')?.addEventListener('click', () => { box.hidden = true; });
      }
      capa.selected.clear();
      renderCapa();
    } catch (err) { alert(err.message); }
    btn.disabled = false;
  });
  $('#qi-capa-list').addEventListener('change', async (e) => {
    const sel = e.target.closest('.qi-capa-sel');
    if (sel) {
      const id = +sel.dataset.sel;
      if (sel.checked) capa.selected.add(id); else capa.selected.delete(id);
      const mkBtn = $('#qi-capa-mklink');
      if (mkBtn) mkBtn.textContent = capa.selected.size ? ('🔗 Create CAPA Link (' + capa.selected.size + ')') : '🔗 Create CAPA Link';
      return;
    }
    const fin = e.target.closest('.qi-f-cam,.qi-f-up');
    if (fin && fin.files && fin.files[0]) {
      const card = fin.closest('.qi-capa'), msg = card.querySelector('.qi-capa-msg');
      const fd = new FormData();
      fd.append('capa_id', card.dataset.id);
      fd.append('photo', fin.files[0]);
      if (msg) { msg.textContent = 'Uploading photo...'; msg.className = 'qi-capa-msg ok'; }
      try {
        const r = await fetch(base + 'capa/photo', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: fd });
        const j = await r.json();
        if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
        if (!r.ok || !j.ok) throw new Error(j.message || 'Upload failed.');
        await loadCapa(true);
      } catch (err) { if (msg) { msg.textContent = err.message; msg.className = 'qi-capa-msg bad'; } }
      fin.value = '';
    }
  });
  $('#qi-capa-list').addEventListener('click', async (e) => {
    const img = e.target.closest('.qi-capa-thumb img, .qi-capa-delitem img');
    if (img && capaLb) { capaLb.querySelector('img').src = img.src; capaLb.hidden = false; return; }
    const card = e.target.closest('.qi-capa'); if (!card) return;
    if (e.target.closest('[data-cam]')) { card.querySelector('.qi-f-cam')?.click(); return; }
    if (e.target.closest('[data-up]')) { card.querySelector('.qi-f-up')?.click(); return; }
    const rmBtn = e.target.closest('[data-rm-photo]');
    if (rmBtn) {
      if (!confirm('Remove this photo? (It will be logged in history and kept for 72 hours.)')) return;
      rmBtn.disabled = true;
      try {
        const r = await fetch(base + 'capa/photo/delete', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' },
          body: JSON.stringify({ capa_id: +card.dataset.id, photo_id: +rmBtn.dataset.rmPhoto })
        });
        const j = await r.json();
        if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
        if (!r.ok || !j.ok) throw new Error(j.message || 'Could not remove.');
        await loadCapa(true);
      } catch (err) { alert(err.message); rmBtn.disabled = false; }
      return;
    }
    const pick = e.target.closest('[data-pick]');
    if (pick) { card.querySelector('[data-f="retest_lab_ref"]').value = pick.dataset.pick; return; }
    const b = e.target.closest('[data-act]'); if (!b) return;
    const msg = card.querySelector('.qi-capa-msg'); b.disabled = true; msg.textContent = '';
    const body = { id: +card.dataset.id, close: b.dataset.act === 'close' };
    card.querySelectorAll('[data-f]').forEach((x) => { body[x.dataset.f] = x.value; });
    try {
      const r = await fetch(base + 'capa/save', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: JSON.stringify(body) });
      const j = await r.json().catch(() => ({}));
      if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
      msg.textContent = j.message || (r.ok ? 'Saved.' : 'Not saved.'); msg.className = 'qi-capa-msg ' + (r.ok ? 'ok' : 'bad');
      if (r.ok && body.close) setTimeout(() => loadCapa(false), 900);
    } catch (err) { msg.textContent = 'Could not save.'; msg.className = 'qi-capa-msg bad'; }
    b.disabled = false;
  });
  // Real-time CAPA refresh every 25s while CAPA tab is visible and user is not typing in a card
  setInterval(() => {
    const panel = $('#qi-panel-capa');
    if (!panel || panel.classList.contains('hidden') || document.hidden) return;
    if (document.activeElement && document.activeElement.closest('#qi-capa-list')) return;
    loadCapa(true);
  }, 25000);
  $$('#qi-capa-status button').forEach((b) => b.addEventListener('click', () => { $$('#qi-capa-status button').forEach((x) => x.classList.toggle('active', x === b)); capa.status = b.dataset.st; capa.page = 1; capa.sig = ''; loadCapa(); }));
  // Lightweight open count on the tab (single COUNT query, avoids loading all cases on initial page load)
  fetch(base + 'capa?badge=1', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then((r) => r.json()).then((j) => { if (j && j.ok) { const b = $('#qi-capa-badge'); b.hidden = !j.open; b.textContent = j.open; } }).catch(() => {});

  // ---------------------------------------------------------------- shimmer
  const skelKpis = (n) => Array.from({ length: n }, () => '<div class="qi-kpi"><i class="qi-skel" style="width:48px;height:24px"></i><i class="qi-skel" style="width:90px;height:11px;margin-top:8px"></i></div>').join('');
  const skelTiles = () => ['A', 'B'].map(() => '<div class="qi-building"><i class="qi-skel" style="width:90px;height:14px;margin-bottom:10px"></i><div class="qi-lines">'
    + Array.from({ length: 12 }, () => '<i class="qi-skel" style="height:92px;border-radius:14px"></i>').join('') + '</div></div>').join('');
  const skelRows = (n) => '<div>' + Array.from({ length: n }, () => '<i class="qi-skel" style="height:18px;margin:12px 0"></i>').join('') + '</div>';

  const err = (el, msg, retry) => { el.innerHTML = `<div class="qi-empty">${esc(msg)} <button type="button" class="cal-btn-glass" data-retry>Try again</button></div>`; const b = $('[data-retry]', el); if (b) b.addEventListener('click', retry); };

  // ---------------------------------------------------------------- line map
  const dayStr = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const ymd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  /** the period chosen with the calendar control: a day, a Monday-Sunday week, or a month */
  function period() {
    const c = new Date(state.cur);
    if (state.view === 'day') return { from: ymd(c), to: ymd(c) };
    if (state.view === 'week') {
      const mon = new Date(c); mon.setDate(c.getDate() + ((c.getDay() === 0 ? -6 : 1) - c.getDay()));
      const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
      return { from: ymd(mon), to: ymd(sun) };
    }
    return { from: ymd(new Date(c.getFullYear(), c.getMonth(), 1)), to: ymd(new Date(c.getFullYear(), c.getMonth() + 1, 0)) };
  }
  function periodTitle() {
    const p = period(); const a = new Date(p.from + 'T12:00:00'); const b = new Date(p.to + 'T12:00:00');
    if (state.view === 'day') return a.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    if (state.view === 'month') return MONTHS[a.getMonth()] + ', ' + a.getFullYear();
    const sm = MONTHS[a.getMonth()].slice(0, 3); const em = MONTHS[b.getMonth()].slice(0, 3);
    return sm === em ? `${sm} ${a.getDate()} – ${b.getDate()}, ${b.getFullYear()}` : `${sm} ${a.getDate()} – ${em} ${b.getDate()}, ${b.getFullYear()}`;
  }
  const periodWords = () => state.view === 'day' ? 'this day' : state.view === 'week' ? 'this week' : 'this month';
  const shiftDay = (s, n) => { const d = new Date(s + 'T12:00:00'); d.setDate(d.getDate() + n); return dayStr(d); };
  const fmtDay = (s) => new Date(s + 'T12:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

  const kpi = (n, label, cls) => `<div class="qi-kpi ${cls || ''}"><b>${esc(n)}</b><span>${esc(label)}</span></div>`;

  const plural = (n, one, many) => n + ' ' + (n === 1 ? one : (many || one + 's'));
  const stack = (l) => {
    const tot = l.pass + l.tolerable + l.fail + l.pending;
    if (!tot) return '<div class="qi-stack"></div>';
    const w = (n) => (n * 100 / tot).toFixed(1) + '%';
    return '<div class="qi-stack" aria-hidden="true">' + (l.pass ? `<i class="p" style="width:${w(l.pass)}"></i>` : '') + (l.tolerable ? `<i class="t" style="width:${w(l.tolerable)}"></i>` : '')
      + (l.fail ? `<i class="f" style="width:${w(l.fail)}"></i>` : '') + (l.pending ? `<i class="w" style="width:${w(l.pending)}"></i>` : '') + '</div>';
  };
  const BADGE = { red: 'FAILED', amber: 'WATCH', green: 'ALL PASSED', grey: 'NO TESTS' };
  const reason = (l) => l.color === 'red' ? plural(l.fail, 'test failed', 'tests failed') : l.color === 'amber' ? (l.tolerable ? plural(l.tolerable, 'tolerable result') : plural(l.pending, 'sample') + ' waiting') : l.color === 'green' ? 'Everything passed' : 'Nothing collected or tested';

  const lineCard = (l) => {
    const nums = [];
    if (l.pass) nums.push(`<span class="p">✔ ${l.pass} passed</span>`);
    if (l.tolerable) nums.push(`<span class="t">▲ ${l.tolerable} tolerable</span>`);
    if (l.fail) nums.push(`<span class="f">✖ ${l.fail} failed</span>`);
    if (l.pending) nums.push(`<span class="w">⏳ ${l.pending} waiting</span>`);
    const any = l.tested + l.pending > 0;
    return `<button type="button" class="qi-line ${l.color}" data-line="${esc(l.line)}" ${any ? '' : 'disabled'} aria-label="Line ${esc(l.line)}: ${esc(BADGE[l.color])}. ${esc(reason(l))}">`
      + `<div class="qi-line-top"><b>Line ${esc(l.line)}</b><span class="qi-badge ${l.color}">${BADGE[l.color]}</span></div>${stack(l)}`
      + `<div class="qi-line-nums">${nums.join('') || '<span class="n">No tests</span>'}</div></button>`;
  };

  function drawLines(d) {
    const t = d.totals;
    const all = d.buildings.flatMap((b) => b.lines);
    const red = all.filter((l) => l.color === 'red');
    $('#qi-line-kpis').innerHTML = kpi(t.tested, 'Tested') + kpi(t.pass, 'Passed', 'green') + kpi(t.tolerable, 'Tolerable', 'amber') + kpi(t.fail, 'Failed', 'red') + kpi(t.pending, 'Waiting to be tested', 'amber');

    const head = !t.tested && !t.pending ? '<div class="qi-alert none"><b>No tests in this period.</b> Move to another period, or press Today.</div>'
      : red.length ? `<div class="qi-alert bad"><div><b>${plural(red.length, 'line has', 'lines have')} a failed test</b><span> - open one to see the lab references</span></div><div class="qi-alert-lines">${red.map((l) =>
        `<button type="button" class="qi-chip red" data-line="${esc(l.line)}">${esc(l.line)} <small>${plural(l.fail, 'failed')}${l.fail_refs && l.fail_refs[0] ? ' - ' + esc(l.fail_refs[0]) : ''}</small></button>`).join('')}</div></div>`
      : `<div class="qi-alert ok"><b>No failed tests in this period.</b>${t.tolerable || t.pending ? ' Amber lines have a tolerable result or samples still waiting to be tested.' : ''}</div>`;

    if (!d.buildings.length) { $('#qi-line-body').innerHTML = head + '<div class="qi-empty">No lines yet. Lines appear once shoes are collected from them.</div>'; return; }
    const showAll = $('#qi-show-empty') && $('#qi-show-empty').checked;
    $('#qi-line-body').innerHTML = head + d.buildings.map((b) => {
      const lines = showAll ? b.lines : b.lines.filter((l) => l.color !== 'grey');
      const bad = b.lines.filter((l) => l.color === 'red').length;
      const quiet = b.lines.length - b.lines.filter((l) => l.color !== 'grey').length;
      return `<section class="qi-building"><h3>Building ${esc(b.building)} <small>${plural(b.lines.length - quiet, 'line')} with tests${bad ? ` - <span class="r">${bad} with a failure</span>` : ''}</small></h3>`
        + (lines.length ? `<div class="qi-lines">${lines.map(lineCard).join('')}</div>` : '<div class="qi-empty slim">No tests from this building on this day.</div>') + '</section>';
    }).join('');
    $('#qi-sub').textContent = periodTitle() + ' - ' + t.tested + ' tested, ' + t.fail + ' failed, ' + t.pending + ' waiting';
  }

  // the line map counts by the day a result was TESTED (default) or by the day the shoes were PRODUCED on the line
  const basis = () => { try { return localStorage.getItem('nxl:qi-basis') === 'production' ? 'production' : 'tested'; } catch (e) { return 'tested'; } };
  const linesParams = () => Object.assign(period(), { basis: basis() });
  async function loadLines(force) {
    const params = linesParams();
    $('#qi-line-hint').textContent = 'Each card adds up every test of ' + periodWords() + '.';
    const hit = peek('line-map', params);
    if (hit) drawLines(hit); else { $('#qi-line-kpis').innerHTML = skelKpis(5); $('#qi-line-body').innerHTML = skelTiles(); }
    try { const r = await get('line-map', params, 'lines', force); if (!r.cached || !hit) drawLines(r.data); }
    catch (e) { if (e.name !== 'AbortError' && !hit) err($('#qi-line-body'), 'Could not load the line map.', () => loadLines(true)); }
  }

  // ---------------------------------------------------------------- failure modes
  const GROUP_LABEL = { line: 'Line', cement: 'Cement supplier', style: 'Style', mode: 'Failure mode' };

  function drawFail(d) {
    const rate = d.tested ? (d.bad * 100 / d.tested).toFixed(1) : '0';
    $('#qi-fail-kpis').innerHTML = kpi(d.tested, 'Tested') + kpi(d.bad, 'Failed or tolerable', 'red') + kpi(rate + '%', 'Not passed') + kpi(d.recorded + ' / ' + d.bad, 'With a failure mode', d.bad && d.recorded < d.bad ? 'amber' : 'green');
    const worst = d.groups.find((g) => g.fail + g.tolerable > 0);
    $('#qi-fail-title').textContent = 'By ' + GROUP_LABEL[d.group].toLowerCase() + (worst && d.group !== 'mode' ? ' - most trouble: ' + worst.key : '');
    const rows = d.groups;
    $('#qi-fail-table').innerHTML = rows.length ? `<table class="qi-table"><thead><tr><th>${esc(GROUP_LABEL[d.group])}</th><th class="n">Tested</th><th>Result mix</th><th class="n">Failed</th><th class="n">Tolerable</th><th class="n">Fail rate</th>${d.group === 'mode' ? '' : '<th>Most common mode</th>'}</tr></thead><tbody>${rows.map((r) =>
      `<tr class="click" data-key="${esc(r.key)}" tabindex="0"><td><b>${esc(r.key)}</b></td><td class="n">${r.tested}</td><td>${stack({ pass: r.pass, tolerable: r.tolerable, fail: r.fail, pending: 0 })}</td><td class="n">${r.fail}</td><td class="n">${r.tolerable}</td><td class="n"><span class="qi-pill ${r.fail_rate >= 5 ? 'bad' : r.fail_rate > 0 ? 'warn' : 'ok'}">${r.fail_rate}%</span></td>${d.group === 'mode' ? '' : `<td>${esc(r.top_mode || '-')}</td>`}</tr>`).join('')}</tbody></table>`
      : '<div class="qi-empty">No tests in this period.</div>';
    const max = Math.max(1, ...d.modes.map((m) => m.count));
    $('#qi-modes').innerHTML = d.modes.length ? d.modes.map((m) => `<div class="qi-mode"><div class="top"><span>${esc(m.mode)}</span><b>${m.count} <small>(${m.share}%)</small></b></div><div class="qi-bar"><i style="width:${Math.round(m.count * 100 / max)}%"></i></div></div>`).join('')
      + (d.bad && d.recorded < d.bad ? `<p class="qi-note">"Not recorded" means the report was saved as Fail / Tolerable with no failure mode. The bonding form now asks for it.</p>` : '')
      : '<div class="qi-empty">No failures or tolerable results in this period.</div>';
    $('#qi-sub').textContent = periodTitle() + ' - ' + d.tested + ' tested, ' + d.bad + ' not passed';
  }

  function failParams() { return Object.assign({}, period(), { group: state.group }); }

  async function loadFail(force) {
    const params = failParams();
    $('#qi-fail-hint').textContent = 'Fail and tolerable results of ' + periodWords() + '.';
    const hit = peek('failures', params);
    if (hit) drawFail(hit); else { $('#qi-fail-kpis').innerHTML = skelKpis(4); $('#qi-fail-table').innerHTML = skelRows(7); $('#qi-modes').innerHTML = skelRows(5); }
    try { const r = await get('failures', params, 'fail', force); if (!r.cached || !hit) drawFail(r.data); }
    catch (e) { if (e.name !== 'AbortError' && !hit) err($('#qi-fail-table'), 'Could not load the failures.', () => loadFail(true)); }
  }

  // ---------------------------------------------------------------- suppliers
  function drawSup(d) {
    $('#qi-sup-table').innerHTML = d.rows.length ? `<table class="qi-table"><thead><tr><th>Supplier</th><th>Material</th><th class="n">Tested</th><th>Result mix</th><th class="n">Pass</th><th class="n">Tolerable</th><th class="n">Fail</th><th class="n">Pass rate</th><th></th></tr></thead><tbody>${d.rows.map((r) =>
      `<tr class="click" data-sup="${esc(r.supplier)}" data-mat="${esc(r.material)}" tabindex="0"><td><b>${esc(r.supplier)}</b></td><td>${esc(r.material)}</td><td class="n">${r.tested}</td><td>${stack({ pass: r.pass, tolerable: r.tolerable, fail: r.fail, pending: 0 })}</td><td class="n">${r.pass}</td><td class="n">${r.tolerable}</td><td class="n">${r.fail}</td>`
      + `<td class="n"><span class="qi-pill ${r.pass_rate >= 95 ? 'ok' : r.pass_rate >= 85 ? 'warn' : 'bad'}">${r.pass_rate}%</span></td><td>${r.low_sample ? '<span class="qi-pill low" title="Too few tests to judge">low sample</span>' : ''}</td></tr>`).join('')}</tbody></table>`
      : '<div class="qi-empty">No supplier results in this period.' + (state.supMode === 'period' ? ' Suppliers are written on material and bonding reports; try <b>Last 90 days</b> for a bigger sample.' : '') + '</div>';
    $('#qi-sup-note').textContent = (d.without_supplier ? d.without_supplier + ' test(s) have no supplier written on the report and are not counted. ' : '') + 'Click a row to see its failed and tolerable tests. Same supplier written two ways? Merge them in Settings > Supplier spelling merges.';
    $('#qi-sub').textContent = supWords() + ' - ' + d.rows.length + ' supplier / material pairs';
  }

  function supParams() {
    if (state.supMode === 'period') return Object.assign({}, period(), { min: state.min });
    return { from: shiftDay(today, -(+state.supMode - 1)), to: today, min: state.min };
  }
  const supWords = () => state.supMode === 'period' ? periodTitle() : 'last ' + state.supMode + ' days';

  async function loadSup(force) {
    const params = supParams();
    const hit = peek('suppliers', params);
    if (hit) drawSup(hit); else $('#qi-sup-table').innerHTML = skelRows(8);
    try { const r = await get('suppliers', params, 'sup', force); if (!r.cached || !hit) drawSup(r.data); }
    catch (e) { if (e.name !== 'AbortError' && !hit) err($('#qi-sup-table'), 'Could not load the suppliers.', () => loadSup(true)); }
  }

  // ---------------------------------------------------------------- side panel
  const dr = { el: $('#qi-drawer'), back: $('#qi-backdrop'), body: $('#qi-drawer-body') };
  function openDrawer(badge, title, sub) {
    $('#qi-drawer-badge').textContent = badge; $('#qi-drawer-title').textContent = title; $('#qi-drawer-sub').textContent = sub || '';
    dr.body.innerHTML = Array.from({ length: 4 }, () => '<div class="cal-dcard cal-skel-dcard"><i class="qi-skel" style="height:16px;width:50%"></i><div class="cal-skel-grid"><i class="qi-skel" style="height:14px"></i><i class="qi-skel" style="height:14px"></i><i class="qi-skel" style="height:14px"></i><i class="qi-skel" style="height:14px"></i></div></div>').join('');
    dr.back.classList.add('active'); dr.el.classList.add('active'); dr.el.setAttribute('aria-hidden', 'false');
  }
  const closeDrawer = () => { dr.back.classList.remove('active'); dr.el.classList.remove('active'); dr.el.setAttribute('aria-hidden', 'true'); };
  const fmtDate = (d) => { if (!d) return '–'; const t = new Date(d + 'T00:00:00'); return isNaN(t) ? d : t.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); };

  function drawItems(items, emptyMsg) {
    if (!items.length) { dr.body.innerHTML = `<div class="cal-empty-day"><p>${esc(emptyMsg)}</p></div>`; return; }
    dr.body.innerHTML = items.map((i) => {
      const st = i.status === 'ongoing' ? '<span class="cal-status-badge badge-ongoing">Ongoing</span>' : i.status === 'pending' ? '<span class="cal-status-badge badge-pending">Pending</span>' : '<span class="cal-status-badge badge-completed">Completed</span>';
      const r = (i.result || '').toUpperCase();
      const rp = r === 'PASS' ? '<span class="cal-result-pill res-pass">PASS</span>' : r === 'FAIL' ? '<span class="cal-result-pill res-fail">FAIL</span>' : r === 'TOLERABLE' ? '<span class="cal-result-pill res-tol">TOLERABLE</span>' : '';
      return `<div class="cal-dcard"><div class="cal-dcard-head"><div><span class="cal-dcard-domain ${esc(i.domain)}">${esc(i.domain_label)}</span><div class="cal-dcard-ref">${esc(i.lab_ref_no || 'No Lab Ref')}</div></div><div style="display:flex;align-items:center;gap:6px">${st}${rp}</div></div>`
        + `<div class="cal-dcard-grid"><div class="cal-dcard-field"><span class="cal-df-label">Test</span><span class="cal-df-val">${esc(i.test_type || '–')}</span></div><div class="cal-dcard-field"><span class="cal-df-label">Style</span><span class="cal-df-val">${esc(i.style || '–')}</span></div>`
        + `<div class="cal-dcard-field"><span class="cal-df-label">Test Date</span><span class="cal-df-val">${esc(fmtDate(i.test_date))}</span></div><div class="cal-dcard-field"><span class="cal-df-label">Technician</span><span class="cal-df-val">${esc(i.technician || '–')}</span></div>`
        + (i.remarks ? `<div class="cal-dcard-field" style="grid-column:span 2"><span class="cal-df-label">Remarks</span><span class="cal-df-val" style="color:#64748b;font-weight:400;white-space:pre-line">${esc(i.remarks)}</span></div>` : '') + '</div>'
        + `<div class="cal-dcard-foot"><span style="font-size:11px;color:#94a3b8">${esc(i.category || '')}</span>${i.view_url ? `<a href="${esc(i.view_url)}" target="_blank" class="cal-dcard-link"><span>View Full Sheet</span><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg></a>` : ''}</div></div>`;
    }).join('');
  }

  async function showDetails(badge, title, sub, path, params) {
    openDrawer(badge, title, sub);
    try { const r = await get(path, params, 'drawer'); drawItems(r.data.items || [], 'Nothing to show.'); }
    catch (e) { if (e.name !== 'AbortError') dr.body.innerHTML = '<div class="cal-empty-day" style="color:#ef4444"><p>Could not load the tests.</p></div>'; }
  }

  // ---------------------------------------------------------------- wiring
  function show(tab) {
    state.tab = tab;
    $$('.qi-tab').forEach((b) => { const on = b.dataset.tab === tab; b.classList.toggle('active', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
    ['lines', 'fail', 'sup', 'capa'].forEach((t) => $('#qi-panel-' + t).classList.toggle('hidden', t !== tab));
    try { history.replaceState(null, '', '#' + tab); } catch (e) { /* ignore */ }
    if (tab === 'lines') loadLines(); else if (tab === 'fail') loadFail(); else if (tab === 'capa') loadCapa(); else loadSup();
  }

  function refreshAll() {
    $('#qi-title-label').textContent = periodTitle();
    show(state.tab);
  }
  $('#qi-prev').addEventListener('click', () => { if (state.view === 'month') { state.cur.setDate(1); state.cur.setMonth(state.cur.getMonth() - 1); } else state.cur.setDate(state.cur.getDate() - (state.view === 'week' ? 7 : 1)); refreshAll(); });
  $('#qi-next').addEventListener('click', () => { if (state.view === 'month') { state.cur.setDate(1); state.cur.setMonth(state.cur.getMonth() + 1); } else state.cur.setDate(state.cur.getDate() + (state.view === 'week' ? 7 : 1)); refreshAll(); });
  $('#qi-today').addEventListener('click', () => { state.cur = new Date(today + 'T12:00:00'); refreshAll(); });
  $$('.qi-view').forEach((b) => b.addEventListener('click', () => {
    state.view = b.dataset.view;
    $$('.qi-view').forEach((x) => { const on = x === b; x.classList.toggle('active', on); x.setAttribute('aria-checked', on ? 'true' : 'false'); });
    refreshAll();
  }));
  $$('.qi-tab').forEach((b) => b.addEventListener('click', () => show(b.dataset.tab)));
  $('#qi-refresh').addEventListener('click', () => { cache.clear(); show(state.tab); });

  $('#qi-show-empty').addEventListener('change', () => { const h = peek('line-map', linesParams()); if (h) drawLines(h); });
  { const sel = $('#qi-basis'); if (sel) { sel.value = basis(); sel.addEventListener('change', () => { try { localStorage.setItem('nxl:qi-basis', sel.value); } catch (e) { /* not saved */ } loadLines(); }); } }
  $('#qi-line-body').addEventListener('click', (e) => {
    const t = e.target.closest('[data-line]'); if (!t || t.disabled) return;
    showDetails(periodTitle(), 'Line ' + t.dataset.line, 'Tests of ' + periodWords(), 'line-details', Object.assign({}, linesParams(), { line: t.dataset.line }));
  });

  $('#qi-group').addEventListener('change', (e) => { state.group = e.target.value; loadFail(); });
  const rowOpen = (tr) => { const p = Object.assign({}, failParams(), { key: tr.dataset.key }); showDetails('Not passed', tr.dataset.key, GROUP_LABEL[state.group] + ', ' + periodTitle(), 'failure-details', p); };
  $('#qi-fail-table').addEventListener('click', (e) => { const tr = e.target.closest('tr[data-key]'); if (tr) rowOpen(tr); });
  $('#qi-fail-table').addEventListener('keydown', (e) => { if (e.key === 'Enter') { const tr = e.target.closest('tr[data-key]'); if (tr) rowOpen(tr); } });

  $$('#qi-sup-period button').forEach((b) => b.addEventListener('click', () => { $$('#qi-sup-period button').forEach((x) => x.classList.toggle('active', x === b)); state.supMode = b.dataset.mode; loadSup(); }));
  $('#qi-min').addEventListener('change', (e) => { state.min = Math.max(1, Math.min(50, +e.target.value || 5)); loadSup(); });
  const supOpen = (tr) => showDetails('Not passed', tr.dataset.sup, tr.dataset.mat + ', ' + supWords(), 'supplier-details', Object.assign({}, supParams(), { supplier: tr.dataset.sup, material: tr.dataset.mat }));
  $('#qi-sup-table').addEventListener('click', (e) => { const tr = e.target.closest('tr[data-sup]'); if (tr) supOpen(tr); });
  $('#qi-sup-table').addEventListener('keydown', (e) => { if (e.key === 'Enter') { const tr = e.target.closest('tr[data-sup]'); if (tr) supOpen(tr); } });

  $('#qi-drawer-close').addEventListener('click', closeDrawer);
  dr.back.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && dr.el.classList.contains('active')) closeDrawer(); });

  const start = ['lines', 'fail', 'sup', 'capa'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'lines';
  state.tab = start;
  $$('.qi-tab').forEach((b) => { const on = b.dataset.tab === start; b.classList.toggle('active', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
  refreshAll();
})();
