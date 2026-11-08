/* Lab Tracker > + Bonding Shoe Collected: when the ERP has no picture of the shoe, the row shows a QR code. The phone scans it, takes
   a photo (the link is the row's Lab Ref + Test Type + a 15-minute token), and the picture replaces the QR here at once. */
(() => {
  'use strict';
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrf = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const links = new Map();   // <tr> -> { token, mtl, qr, expires (ms), state: pending|expired }
  let timer = 0; let overlay = null; let overlayTr = null;

  const api = async (url, body) => {
    const r = await fetch(BASE + url, {
      method: body ? 'POST' : 'GET', credentials: 'same-origin',
      headers: Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.content : '' } : {}),
      body: body ? JSON.stringify(body) : undefined,
    });
    const j = await r.json().catch(() => ({}));
    if (csrf && j.csrf) csrf.content = j.csrf;
    return { ok: r.ok && j.ok !== false, j };
  };
  const toast = (t, k) => (window.nxToast ? window.nxToast(t, k || 'info', 6000) : null);
  const cellOf = (tr) => tr.querySelector('.ik-imgcell');
  const mtlOf = (tr) => (tr.querySelector('.ik-mtl')?.value || '').trim();

  const clearQr = (tr) => { cellOf(tr)?.querySelector('.ik-qr')?.remove(); links.delete(tr); if (overlayTr === tr) closeOverlay(); };

  const paintQr = (tr) => {
    const L = links.get(tr); const cell = cellOf(tr); if (!L || !cell) return;
    let b = cell.querySelector('.ik-qr');
    if (!b) { b = document.createElement('button'); b.type = 'button'; b.className = 'ik-qr'; cell.appendChild(b); }
    b.classList.toggle('exp', L.state === 'expired');
    b.classList.toggle('scan', !!L.scanning && L.state !== 'expired');
    b.title = L.state === 'expired' ? 'The code expired: click for a new one' : (L.scanning ? 'Someone is capturing this shoe with a phone right now' : 'Scan with your phone to take the photo (click to enlarge)') + (L.by ? ' - code started by ' + L.by : '');
    b.innerHTML = L.state === 'expired' ? '<span>Code expired<br>click to renew</span>' : `<img src="${esc(L.qr)}" alt="QR code to take the shoe photo"><span>Scan to add photo</span>`;
  };

  const newLink = async (tr) => {
    const mtl = mtlOf(tr);
    const lab = (tr.querySelector('.ik-lr')?.textContent || '').trim() || (tr.querySelector('.ik-code')?.value || '').trim();
    if (!mtl || !lab) return;
    const { ok, j } = await api('dtr/photo-link', { lab_ref: lab, test_type: tr.querySelector('.ik-type')?.value || '', mtl, code: (tr.querySelector('.ik-code')?.value || '').trim() });
    if (!ok) { toast(j.error || 'Could not make the QR code.', 'error'); return; }
    // when someone else already has a live code for this shoe, the server hands back that same code (one capture at a time)
    links.set(tr, { token: j.token, mtl, qr: j.qr, expires: new Date(String(j.expires_at).replace(' ', 'T')).getTime(), state: 'pending', lab: j.lab_ref || lab, by: j.reused ? j.by : '', scanning: !!j.scanning });
    if (j.reused && j.by) toast(`A code for this shoe is already open (started by ${j.by}). Use the same one.`, 'info');
    paintQr(tr); poll(); if (overlayTr === tr) paintOverlay();
  };

  // ---- the picture arrived: put it in the row instead of the QR ----
  const showPhoto = (tr, v) => {
    const img = tr.querySelector('.ik-img'); const L = links.get(tr);
    if (img && L) { img.onload = () => { img.hidden = false; }; img.src = `${BASE}dtr/erp-image?mtl=${encodeURIComponent(L.mtl)}&v=${v}`; }
    toast(`Photo received for ${L ? L.lab : 'the shoe'}.`, 'success');
    clearQr(tr);
  };

  // ---- following the links: one poll for all of them ----
  const poll = () => {
    if (timer || !links.size) return;
    timer = setInterval(async () => {
      if (!links.size) { clearInterval(timer); timer = 0; return; }
      for (const [tr, L] of [...links]) {
        if (!document.body.contains(tr)) { links.delete(tr); continue; }
        if (L.state === 'expired') continue;
        const { j } = await api('dtr/photo-status?token=' + encodeURIComponent(L.token));
        if (j.status === 'captured') { showPhoto(tr, j.v); }
        else if (j.status === 'expired' || Date.now() > L.expires) { L.state = 'expired'; paintQr(tr); if (overlayTr === tr) paintOverlay(); }
        else if (!!j.scanning !== !!L.scanning) { L.scanning = !!j.scanning; paintQr(tr); if (overlayTr === tr) paintOverlay(); }
      }
    }, 2000);
  };

  // ---- the enlarged QR ----
  function closeOverlay() { if (overlay) { clearInterval(overlay.t); overlay.remove(); } overlay = null; overlayTr = null; document.removeEventListener('keydown', onKey); }
  const onKey = (e) => { if (e.key === 'Escape') closeOverlay(); };
  function paintOverlay() {
    if (!overlay || !overlayTr) return;
    const L = links.get(overlayTr); if (!L) { closeOverlay(); return; }
    const secs = Math.max(0, Math.round((L.expires - Date.now()) / 1000));
    overlay.querySelector('.ik-qrbox').innerHTML = L.state === 'expired' || secs === 0
      ? '<div class="ik-qrgone"><b>This code expired</b><span>A code works for 15 minutes.</span><button type="button" class="dtr-btn dtr-btn-primary" id="ik-qr-new">Make a new code</button></div>'
      : `<img src="${esc(L.qr)}" alt="QR code"><p class="ik-qrlab">${esc(L.lab)}</p><p class="ik-qrnote">1. Scan with the phone camera<br>2. Take the photo of the shoe<br>3. It appears in the row by itself</p><p class="ik-qrtime">Valid for <b>${Math.floor(secs / 60)}:${String(secs % 60).padStart(2, '0')}</b></p>${L.scanning ? '<p class="ik-qrbusy">Someone is capturing this shoe right now.</p>' : ''}${L.by ? `<p class="ik-qrtime">Code started by ${esc(L.by)}</p>` : ''}`;
    overlay.querySelector('#ik-qr-new')?.addEventListener('click', async () => { links.delete(overlayTr); await newLink(overlayTr); });
  }
  const openOverlay = (tr) => {
    closeOverlay();
    overlayTr = tr;
    overlay = document.createElement('div');
    overlay.className = 'ik-qrzoom';
    overlay.setAttribute('role', 'dialog'); overlay.setAttribute('aria-modal', 'true'); overlay.setAttribute('aria-label', 'Scan to add the shoe photo');
    overlay.innerHTML = '<div class="ik-qrcard"><button type="button" class="ik-qrx" aria-label="Close">&times;</button><h3>Take the shoe photo with your phone</h3><div class="ik-qrbox"></div></div>';
    overlay.addEventListener('click', (e) => { if (e.target === overlay || e.target.closest('.ik-qrx')) closeOverlay(); });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(overlay);
    paintOverlay();
    const t = setInterval(() => { if (!overlay) { clearInterval(t); return; } paintOverlay(); }, 1000);
    overlay.t = t;
    overlay.querySelector('.ik-qrx').focus();
  };

  document.addEventListener('click', (e) => {
    const b = e.target.closest('.ik-qr'); if (!b) return;
    const tr = b.closest('tr'); const L = links.get(tr);
    if (L && L.state === 'expired') { links.delete(tr); newLink(tr).then(() => openOverlay(tr)); return; }
    openOverlay(tr);
  });

  // dtr.js tells us what the row's picture is doing: ok (the ERP has it), missing (the ERP has none for this MTL #), none (no MTL # yet)
  document.addEventListener('ik:image', (e) => {
    const tr = e.target.closest ? e.target.closest('tr') : null; if (!tr) return;
    const st = e.detail && e.detail.state;
    if (st === 'missing') { const L = links.get(tr); if (!L || L.mtl !== mtlOf(tr) || L.state === 'expired') newLink(tr); }
    else { const L = links.get(tr); if (st === 'none' || (L && L.mtl !== mtlOf(tr)) || st === 'ok') clearQr(tr); }
  });
})();
