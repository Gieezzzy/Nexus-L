/** Report approval page (approve/<token>): sign and approve, or return the report with a remark. */
(() => {
  const root = document.getElementById('apv-root');
  if (!root) return;
  const $ = (s) => root.querySelector(s);
  const csrf = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const canvas = $('#apv-pad');
  const pad = canvas ? window.nxSignaturePad(canvas) : null;
  const msg = (t, bad) => { const m = $('#apv-msg'); if (m) { m.textContent = t; m.style.color = bad ? '#b91c1c' : ''; } };

  $('#apv-clear')?.addEventListener('click', () => pad && pad.clear());
  $('#apv-upload')?.addEventListener('click', () => $('#apv-file')?.click());
  $('#apv-file')?.addEventListener('change', async (e) => {
    const f = e.target.files && e.target.files[0];
    e.target.value = '';
    if (!f || !pad) return;
    try { await pad.loadFile(f); msg('Picture loaded: check it in the box, then Approve & sign.'); } catch (err) { msg(err.message, true); }
  });
  $('#apv-use-saved')?.addEventListener('click', (e) => pad && pad.load(e.currentTarget.dataset.sig));

  // Full-page loading screen while the request is processed (the signature is stamped on the report, then the requester is told)
  let busy = null;
  const loading = (title, detail) => {
    if (!busy) {
      busy = document.createElement('div');
      busy.className = 'apv-busy';
      busy.setAttribute('role', 'alert');
      busy.setAttribute('aria-live', 'assertive');
      document.body.appendChild(busy);
    }
    busy.innerHTML = `<div class="apv-busy-box"><div class="apv-spin" aria-hidden="true"></div><b>${title}</b><span>${detail}</span></div>`;
  };
  const done = (title, detail, ok) => {
    if (!busy) return;
    busy.innerHTML = `<div class="apv-busy-box"><div class="apv-tick ${ok ? 'ok' : 'bad'}" aria-hidden="true">${ok ? '✓' : '!'}</div><b>${title}</b><span>${detail}</span></div>`;
  };
  const hideLoading = () => { if (busy) { busy.remove(); busy = null; } };

  const decide = async (action) => {
    const remarks = ($('#apv-remarks')?.value || '').trim();
    if (action === 'approve' && (!pad || pad.isEmpty())) { msg('Sign in the box first (or use your saved signature).', true); return; }
    if (action === 'reject' && !remarks) { msg('Write what needs to be corrected.', true); return; }
    root.querySelectorAll('button').forEach((b) => { b.disabled = true; });
    msg(action === 'approve' ? 'Signing the report…' : 'Returning the report…');
    if (action === 'approve') loading('Approving your report…', 'Putting your signature and the date on the report. Please wait and do not close this page.');
    else loading('Returning the report…', 'Sending your remarks to the requester. Please wait.');
    try {
      const r = await fetch(location.pathname, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf ? csrf.content : '' },
        body: JSON.stringify({ action, remarks, signature: action === 'approve' ? pad.toDataURL() : '' }),
      });
      const j = await r.json();
      if (csrf && j.csrf) csrf.content = j.csrf;
      if (!r.ok || !j.ok) throw new Error(j.error || 'Request failed');
      // the link is dead now: leave this page instead of reloading it
      msg(action === 'approve' ? 'Approved and signed. The requester was notified and can now send it to InterLink. This link no longer works.' : 'Returned. The requester was notified. This link no longer works.');
      done(action === 'approve' ? 'Report approved and signed' : 'Report returned', action === 'approve' ? 'The requester was notified and can now send it to InterLink. Taking you back…' : 'The requester was notified. Taking you back…', true);
      const home = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
      const next = root.dataset.next || '';   // more reports waiting for this approver: go straight to the next one
      if (next) done(action === 'approve' ? 'Report approved and signed' : 'Report returned', 'Opening the next report waiting for you…', true);
      setTimeout(() => { location.href = next || home + 'dashboard'; }, next ? 1200 : 2500);
    } catch (e) {
      hideLoading();
      msg(e.message, true);
      root.querySelectorAll('button').forEach((b) => { b.disabled = false; });
    }
  };
  $('#apv-approve')?.addEventListener('click', () => decide('approve'));
  $('#apv-reject')?.addEventListener('click', () => decide('reject'));

  // ---- one approver at a time (live). Every 2 s the page tells the server it is open and learns who holds the report.
  const lockBox = $('#apv-lock');
  const APV_ID = root.dataset.id, TOKEN = root.dataset.token, ME = root.dataset.me;
  if (!lockBox || !APV_ID || !$('#apv-approve')) return;            // only for someone who can decide here
  const home = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const controls = () => [...root.querySelectorAll('.apv-sign button, .apv-sign textarea, .apv-sign input')];
  const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  let role = '', deciding = false, closing = null, timer = 0;
  const setLocked = (on) => {
    controls().forEach((n) => { if (!n.closest('#apv-lock')) n.disabled = on; });
    if (canvas) canvas.style.pointerEvents = on ? 'none' : '';
    root.querySelector('.apv-sign')?.classList.toggle('apv-locked', on);
  };
  const ping = async (what, extra) => {
    const r = await fetch(home + 'approve/presence/' + APV_ID, {
      method: 'POST', credentials: 'same-origin', keepalive: what === 'leave',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf ? csrf.content : '' },
      body: JSON.stringify(Object.assign({ token: TOKEN, do: what || 'ping' }, extra || {})),
    });
    const j = await r.json().catch(() => ({ ok: false, status: 'gone' }));
    if (csrf && j.csrf) csrf.content = j.csrf;
    return j;
  };
  const showClosing = (d, status) => {
    if (closing) return;
    clearInterval(timer);
    setLocked(true);
    let left = Math.max(0, Number(d.closesIn) || 0);
    const what = status === 'approved' ? 'approved and signed' : status === 'rejected' ? 'returned for correction' : 'closed';
    closing = document.createElement('div');
    closing.className = 'apv-busy apv-closing';
    closing.setAttribute('role', 'alertdialog'); closing.setAttribute('aria-live', 'assertive');
    const paint = () => {
      closing.innerHTML = `<div class="apv-busy-box"><div class="apv-tick bad" aria-hidden="true">!</div>`
        + `<b>${status === 'expired' ? 'This approval link has expired' : `This report was already ${what}`}</b>`
        + `<span>${d.by ? `by <b>${esc(d.by)}</b> at ${esc(d.at)}. ` : ''}This page closes in <b>${left}</b> second${left === 1 ? '' : 's'} and the link no longer works.</span></div>`;
    };
    paint(); document.body.appendChild(closing);
    const t = setInterval(() => {
      left -= 1; paint();
      if (left <= 0) { clearInterval(t); location.href = home + 'dashboard'; }
    }, 1000);
    if (left <= 0) { clearInterval(t); location.href = home + 'dashboard'; }
  };
  const render = (j) => {
    if (j.status && j.status !== 'pending') {
      if (deciding || (j.decided && j.decided.mine)) return;        // my own decision: the normal "approved" screen handles it
      showClosing(j.decided || {}, j.status);
      return;
    }
    const was = role; role = j.me;
    lockBox.hidden = false;
    // redraw only when something changed (a redraw while someone clicks "Let in" would swallow the click)
    const sig = JSON.stringify([j.me, j.holder && j.holder.id, (j.waiting || []).map((w) => w.id), j.place]);
    if (sig === lockBox.dataset.sig) return;
    lockBox.dataset.sig = sig;
    if (role === 'holder') {
      setLocked(false);
      const wait = j.waiting || [];
      lockBox.className = 'apv-lock holder';
      lockBox.innerHTML = `<b>🔓 You are checking this report.</b> Only you can approve or return it now.`
        + (wait.length
          ? `<div class="apv-lock-list"><span>${wait.length} other approver${wait.length === 1 ? '' : 's'} want${wait.length === 1 ? 's' : ''} to open it:</span>`
            + wait.map((w) => `<div class="apv-lock-row"><span>${esc(w.name)} <small>since ${esc(w.since)}</small></span><button type="button" class="nx-btn" data-allow="${w.id}">Let in</button></div>`).join('')
            + `<small>Letting someone in hands the report to them; you then wait in line.</small></div>`
          : '');
      if (was === 'waiting') msg((j.holder && j.holder.grantedBy && j.holder.grantedBy !== '(next in line)' ? j.holder.grantedBy + ' let you in. ' : 'It is your turn. ') + 'You can approve or return the report now.');
    } else {
      setLocked(true);
      lockBox.className = 'apv-lock waiting';
      const h = j.holder || {};
      lockBox.innerHTML = `<b>🔒 ${esc(h.name || 'Another approver')} is checking this report now</b> (since ${esc(h.since || '')}).`
        + `<span>Only one approver can work on a report at a time. You are <b>#${j.place || 1}</b> in line; ${esc(h.name || 'they')} can let you in, or you get it when they leave.</span>`;
    }
  };
  lockBox.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-allow]'); if (!b) return;
    b.disabled = true;
    const j = await ping('allow', { user_id: Number(b.dataset.allow) }).catch(() => null);
    if (j) render(j);
  });
  const tick = async () => {
    if (deciding || closing) return;
    try { render(await ping('ping')); } catch (e) { /* the next tick tries again */ }
  };
  // my decision is in flight: no "already decided" screen for myself
  ['#apv-approve', '#apv-reject'].forEach((s) => $(s)?.addEventListener('click', () => { deciding = true; setTimeout(() => { if (!busy) deciding = false; }, 1500); }, true));
  setLocked(true);                                                   // locked until the server says this page holds the report
  tick(); timer = setInterval(tick, 2000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
  window.addEventListener('pagehide', () => { if (role && !closing) ping('leave').catch(() => {}); });
})();
