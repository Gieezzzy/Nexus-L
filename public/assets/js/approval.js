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
      setTimeout(() => { location.href = home + 'dashboard'; }, 2500);
    } catch (e) {
      hideLoading();
      msg(e.message, true);
      root.querySelectorAll('button').forEach((b) => { b.disabled = false; });
    }
  };
  $('#apv-approve')?.addEventListener('click', () => decide('approve'));
  $('#apv-reject')?.addEventListener('click', () => decide('reject'));
})();
