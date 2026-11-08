/* Shoe Bonding (U60) > Send report: "Attach Image File" asks Upload or Capture. Capture shows a QR code (web + Lab Reference + a
   one-off token, valid 15 minutes); the phone takes the photo and it is attached to the Synology message / e-mail at once. */
(() => {
  'use strict';
  // a click on an attached picture (graph card or photo, in the Synology or e-mail dialog) opens it large; click, X or Esc closes it
  let lb = null;
  const closeLb = () => { lb?.remove(); lb = null; document.removeEventListener('keydown', lbKey, true); };
  function lbKey(e) { if (e.key === 'Escape') { e.stopPropagation(); closeLb(); } }
  const openLb = (img) => {
    if (!img || !img.src) return;
    closeLb();
    const name = img.closest('.syno-preview-card')?.querySelector('.syno-preview-name')?.textContent || '';
    lb = document.createElement('div');
    lb.className = 'syno-lightbox'; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true'); lb.setAttribute('aria-label', 'Enlarged picture');
    lb.innerHTML = '<button type="button" class="syno-lb-x" aria-label="Close">&times;</button><figure><img alt=""><figcaption></figcaption></figure>';
    lb.querySelector('img').src = img.src;
    lb.querySelector('figcaption').textContent = name;
    lb.addEventListener('click', (e) => { if (!e.target.closest('figure img')) closeLb(); });
    document.addEventListener('keydown', lbKey, true);
    document.body.appendChild(lb);
    lb.querySelector('.syno-lb-x').focus();
  };
  document.addEventListener('click', (e) => { const t = e.target.closest('.syno-preview-thumb'); if (t && t.closest('#dtr-synology-modal, #dtr-email-modal')) openLb(t); });
  document.addEventListener('keydown', (e) => { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches?.('.syno-preview-thumb')) { e.preventDefault(); openLb(e.target); } });

  // the Synology dialog and the e-mail dialog each get the same Upload / Capture control; a photo taken in one is shared with the other
  document.querySelectorAll('.syno-attach-zone').forEach((zone) => {
    const label = zone.querySelector('.syno-attach-label');
    const fileInput = zone.querySelector('input[type=file]');
    if (label && fileInput) setup(zone, label, fileInput);
  });
  function setup(zone, label, fileInput) {
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrf = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const toast = (t, k) => (window.nxToast ? window.nxToast(t, k || 'info', 6000) : null);
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

  let menu = null; let cap = null; let capPoll = 0; let capClock = 0; let L = null; let zoom = null; let zoomClock = 0;
  const secsLeft = () => (L ? Math.max(0, Math.round((L.expires - Date.now()) / 1000)) : 0);
  const clock = (s) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;

  // ---- the Upload / Capture choice ----
  const outside = (e) => { if (menu && !menu.contains(e.target) && !label.contains(e.target)) closeMenu(); };
  const menuKey = (e) => { if (e.key === 'Escape') closeMenu(); };
  function closeMenu() { menu?.remove(); menu = null; document.removeEventListener('mousedown', outside, true); document.removeEventListener('keydown', menuKey); }
  const openMenu = () => {
    if (menu) { closeMenu(); return; }
    menu = document.createElement('div'); menu.className = 'syno-attach-menu'; menu.setAttribute('role', 'menu');
    menu.innerHTML = '<button type="button" role="menuitem" data-pick="upload"><b>Upload</b><span>Choose a picture from this computer</span></button><button type="button" role="menuitem" data-pick="capture"><b>Capture</b><span>Take it with your phone: scan a QR code</span></button>';
    const host = label.parentElement; host.style.position = 'relative'; host.appendChild(menu);
    menu.addEventListener('click', (e) => { const b = e.target.closest('[data-pick]'); if (!b) return; closeMenu(); if (b.dataset.pick === 'upload') fileInput.click(); else startCap(); });
    document.addEventListener('mousedown', outside, true); document.addEventListener('keydown', menuKey);
    menu.querySelector('button').focus();
  };
  label.setAttribute('role', 'button'); label.setAttribute('aria-haspopup', 'menu'); label.tabIndex = 0;
  label.addEventListener('click', (e) => { e.preventDefault(); openMenu(); }, true);   // capture phase: the label no longer opens the file window by itself
  label.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openMenu(); } });

  // ---- the QR panel under the attach controls ----
  const stopCap = () => { clearInterval(capPoll); clearInterval(capClock); capPoll = capClock = 0; };
  const hideCap = () => { stopCap(); cap?.remove(); cap = null; L = null; closeZoom(); };
  const paintCap = () => {
    if (!cap || !L) return;
    const s = secsLeft();
    if (L.state === 'expired' || (L.state === 'pending' && s === 0)) {
      cap.innerHTML = '<div class="syno-cap-body"><div class="syno-cap-txt"><b>This code expired</b><span>A code works for 15 minutes.</span><span><button type="button" class="dtr-btn dtr-btn-primary dtr-btn-sm" data-cap="new">Make a new code</button> <button type="button" class="dtr-btn dtr-btn-outline dtr-btn-sm" data-cap="cancel">Cancel</button></span></div></div>';
    } else if (L.state === 'got') {
      cap.innerHTML = '<div class="syno-cap-body"><div class="syno-cap-txt"><b>Photo received</b><span>It is attached below. To replace it, take another one.</span><button type="button" class="dtr-btn dtr-btn-outline dtr-btn-sm" data-cap="again">Take another with the phone</button></div></div>';
    } else {
      cap.innerHTML = `<div class="syno-cap-body"><button type="button" class="syno-cap-qr" data-cap="zoom" title="Click to enlarge"><img src="${esc(L.qr)}" alt="QR code to take the photo with a phone"></button>
        <div class="syno-cap-txt"><b>Scan with the phone camera</b><span>Take the photo: it appears here by itself.</span><span class="syno-cap-time">Valid for <b>${clock(s)}</b></span>${L.scanning ? '<span class="syno-cap-busy">Someone is capturing this right now.</span>' : ''}${L.by ? `<span class="syno-cap-time">Code started by ${esc(L.by)}</span>` : ''}
        <button type="button" class="dtr-btn dtr-btn-outline dtr-btn-sm" data-cap="cancel">Cancel</button></div></div>`;
    }
  };

  async function startCap() {
    const lab = (document.getElementById('bf_LabRefNo')?.value || '').trim();
    if (!lab) { toast('Open a bonding report first: the photo is linked to its Lab Reference.', 'error'); return; }
    stopCap();
    if (!cap) {
      cap = document.createElement('div'); cap.className = 'syno-cap';
      zone.querySelector('.syno-attach-controls')?.insertAdjacentElement('afterend', cap);
      cap.addEventListener('click', (e) => {
        const b = e.target.closest('[data-cap]'); if (!b) return;
        if (b.dataset.cap === 'zoom') openZoom(); else if (b.dataset.cap === 'cancel') hideCap(); else startCap();
      });
    }
    cap.innerHTML = '<div class="syno-cap-body"><div class="syno-cap-txt"><span>Making the QR code...</span></div></div>';
    const { ok, j } = await api('dtr/photo-link', { kind: 'attach', lab_ref: lab, test_type: 'REPORT' });
    if (!ok) { cap?.remove(); cap = null; toast(j.error || 'Could not make the QR code.', 'error'); return; }
    L = { token: j.token, qr: j.qr, lab, expires: new Date(String(j.expires_at).replace(' ', 'T')).getTime(), state: 'pending', v: 0, by: j.reused ? j.by : '', scanning: !!j.scanning };
    if (j.reused && j.by) toast(`A code for this report is already open (started by ${j.by}). Use the same one.`, 'info');
    paintCap();
    capClock = setInterval(() => { if (!cap) { stopCap(); return; } if (L.state === 'pending') paintCap(); }, 1000);
    capPoll = setInterval(async () => {
      if (!cap || !L || L.state === 'expired') return;
      if (zone.closest('.hidden')) { hideCap(); return; }   // this Send report window was closed
      const { j: st } = await api('dtr/photo-status?token=' + encodeURIComponent(L.token));
      if (st.status === 'captured' && st.v !== L.v) {
        L.v = st.v;
        try {
          const r = await fetch(`${BASE}dtr/photo-image?token=${encodeURIComponent(L.token)}&v=${st.v}`, { credentials: 'same-origin' });
          if (!r.ok) throw new Error('image');
          const blob = await r.blob();
          const file = new File([blob], `Phone_${L.lab}_${new Date().toISOString().slice(11, 19).replace(/:/g, '')}.jpg`, { type: 'image/jpeg' });
          document.dispatchEvent(new CustomEvent('syno:attach', { detail: { file } }));   // dtr.js attaches it like an uploaded file
          L.state = 'got'; paintCap(); closeZoom();
          toast(`Photo received for ${L.lab}.`, 'success');
        } catch (e) { toast('The photo arrived but could not be loaded. Try again.', 'error'); }
      } else if ((st.status === 'expired' || Date.now() > L.expires) && L.state === 'pending') { L.state = 'expired'; paintCap(); }
      else if (L.state === 'pending' && !!st.scanning !== !!L.scanning) { L.scanning = !!st.scanning; paintCap(); }
    }, 2000);
  }

  // ---- the enlarged QR ----
  const zoomKey = (e) => { if (e.key === 'Escape') closeZoom(); };
  function closeZoom() { clearInterval(zoomClock); zoom?.remove(); zoom = null; document.removeEventListener('keydown', zoomKey); }
  function openZoom() {
    if (!L) return;
    closeZoom();
    zoom = document.createElement('div'); zoom.className = 'ik-qrzoom';
    zoom.setAttribute('role', 'dialog'); zoom.setAttribute('aria-modal', 'true'); zoom.setAttribute('aria-label', 'Scan to take the photo');
    zoom.innerHTML = '<div class="ik-qrcard"><button type="button" class="ik-qrx" aria-label="Close">&times;</button><h3>Take the photo with your phone</h3><div class="ik-qrbox"></div></div>';
    zoom.addEventListener('click', (e) => { if (e.target === zoom || e.target.closest('.ik-qrx')) closeZoom(); });
    document.addEventListener('keydown', zoomKey);
    document.body.appendChild(zoom);
    const paint = () => {
      if (!zoom || !L) return;
      const s = secsLeft();
      zoom.querySelector('.ik-qrbox').innerHTML = (L.state === 'expired' || s === 0)
        ? '<div class="ik-qrgone"><b>This code expired</b><span>Close this and make a new code.</span></div>'
        : `<img src="${esc(L.qr)}" alt="QR code"><p class="ik-qrlab">${esc(L.lab)}</p><p class="ik-qrnote">1. Scan with the phone camera<br>2. Take the photo<br>3. It is attached to the message by itself</p><p class="ik-qrtime">Valid for <b>${clock(s)}</b></p>${L.scanning ? '<p class="ik-qrbusy">Someone is capturing this right now.</p>' : ''}`;
    };
    paint(); zoomClock = setInterval(paint, 1000);
    zoom.querySelector('.ik-qrx').focus();
  }
  }
})();
