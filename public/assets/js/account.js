(() => {
  'use strict';
  const root = document.getElementById('ac-root');
  if (!root) return;
  const $ = (s, c = document) => c.querySelector(s);
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const base = (document.querySelector('meta[name="base-url"]') || {}).content || '/';
  const post = (path, body) => fetch(base + path, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' },
    body: JSON.stringify(body || {}),
  }).then((r) => r.json().then((j) => {
    if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
    if (!r.ok || j.ok === false) throw new Error(j.error || j.message || 'Could not save.');
    return j;
  }));
  const say = (id, text, ok) => { const e = $('#' + id); if (e) { e.textContent = text; e.className = 'ac-msg ' + (ok ? 'ok' : 'err'); } };
  const run = async (btn, msgId, fn) => {
    btn.disabled = true; say(msgId, 'Saving...', true);
    try { const j = await fn(); say(msgId, j.message || 'Saved.', true); return j; } catch (e) { say(msgId, e.message, false); } finally { btn.disabled = false; }
    return null;
  };

  // tabs (the open one is kept in the address as ?tab=)
  const show = (tab) => {
    const names = [...root.querySelectorAll('.ac-pane')].map((p) => p.dataset.pane);
    if (!names.includes(tab)) tab = 'profile';
    root.querySelectorAll('.ac-pane').forEach((p) => p.classList.toggle('on', p.dataset.pane === tab));
    root.querySelectorAll('.ac-tabs button').forEach((b) => b.classList.toggle('on', b.dataset.tab === tab));
    try { history.replaceState(null, '', base + 'account?tab=' + tab); } catch (e) { /* ignore */ }
  };
  root.querySelector('.ac-tabs').addEventListener('click', (e) => { const b = e.target.closest('button[data-tab]'); if (b) show(b.dataset.tab); });
  show(root.dataset.tab);

  // profile
  const pSave = $('#p-save');
  if (pSave) {
    pSave.addEventListener('click', (e) => run(e.currentTarget, 'p-msg', () => post('account/save', {
      FullName: $('#p-FullName').value, Email: $('#p-Email').value, Phone: $('#p-Phone').value,
      Department: $('#p-Department').value, JobTitle: $('#p-JobTitle').value, EmployeeNo: $('#p-EmployeeNo').value,
    })));
  }

  // Interactive Profile Picture Cropper (drag to rearrange, zoom, rotate, flip)
  const fileIn = $('#ac-avatar-file');
  const cropModal = $('#ac-crop-modal');
  const cropCv = $('#ac-crop-canvas');
  const prevCv = $('#ac-crop-preview');
  const applyAvatarToDom = (url) => {
    const initials = root.dataset.initials || 'U';
    ['#ac-head-avatar', '#ac-avatar-preview', '#top-user-avatar'].forEach((sel) => {
      const el = document.querySelector(sel);
      if (!el) return;
      if (url) {
        el.style.backgroundImage = `url('${url}')`;
        el.style.backgroundSize = 'cover';
        el.style.backgroundPosition = 'center';
        el.style.color = 'transparent';
        el.textContent = '';
      } else {
        el.style.backgroundImage = '';
        el.style.color = '';
        el.textContent = el.dataset.initials || initials;
      }
    });
    const rmBtn = $('#ac-avatar-remove-btn');
    if (rmBtn) rmBtn.hidden = !url;
  };

  if (fileIn && cropModal && cropCv && prevCv) {
    const cctx = cropCv.getContext('2d');
    const pctx = prevCv.getContext('2d');
    const zoomIn = $('#ac-crop-zoom');
    const angleIn = $('#ac-crop-angle');
    const zoomVal = $('#ac-crop-zoom-val');
    const angleVal = $('#ac-crop-angle-val');
    let img = null;
    let baseScale = 1;
    let st = { zoom: 1, angle: 0, rot90: 0, flipH: 1, flipV: 1, ox: 0, oy: 0, dragging: false, sx: 0, sy: 0 };

    const resetTransform = () => {
      if (!img) return;
      const r = 128 * 2;
      baseScale = Math.max(r / img.width, r / img.height);
      st = { zoom: 1, angle: 0, rot90: 0, flipH: 1, flipV: 1, ox: 0, oy: 0, dragging: false, sx: 0, sy: 0 };
      zoomIn.value = '100';
      angleIn.value = '0';
      zoomVal.textContent = '100%';
      angleVal.textContent = '0°';
      drawCrop();
    };

    const drawTransformed = (ctx, w, h, scaleFactor) => {
      ctx.save();
      ctx.translate(w / 2 + st.ox * scaleFactor, h / 2 + st.oy * scaleFactor);
      const rad = ((st.rot90 + st.angle) * Math.PI) / 180;
      ctx.rotate(rad);
      ctx.scale(st.flipH * baseScale * st.zoom * scaleFactor, st.flipV * baseScale * st.zoom * scaleFactor);
      ctx.drawImage(img, -img.width / 2, -img.height / 2);
      ctx.restore();
    };

    const drawCrop = () => {
      if (!img) return;
      const W = cropCv.width;
      const H = cropCv.height;
      const R = 128; // circle radius inside 320x320 stage
      cctx.clearRect(0, 0, W, H);
      cctx.fillStyle = '#0f172a';
      cctx.fillRect(0, 0, W, H);
      drawTransformed(cctx, W, H, 1);

      // Darken area outside circular crop mask
      cctx.save();
      cctx.fillStyle = 'rgba(15, 23, 42, 0.58)';
      cctx.beginPath();
      cctx.rect(0, 0, W, H);
      cctx.arc(W / 2, H / 2, R, 0, Math.PI * 2, true);
      cctx.fill();

      // Circular ring & rule-of-thirds grid
      cctx.strokeStyle = 'rgba(255,255,255,0.9)';
      cctx.lineWidth = 2;
      cctx.beginPath();
      cctx.arc(W / 2, H / 2, R, 0, Math.PI * 2);
      cctx.stroke();

      cctx.save();
      cctx.beginPath();
      cctx.arc(W / 2, H / 2, R, 0, Math.PI * 2);
      cctx.clip();
      cctx.strokeStyle = 'rgba(255,255,255,0.22)';
      cctx.lineWidth = 1;
      for (let i = 1; i <= 2; i++) {
        const d = (W / 2 - R) + (2 * R * i) / 3;
        cctx.beginPath(); cctx.moveTo(d, H / 2 - R); cctx.lineTo(d, H / 2 + R); cctx.stroke();
        cctx.beginPath(); cctx.moveTo(W / 2 - R, d); cctx.lineTo(W / 2 + R, d); cctx.stroke();
      }
      cctx.restore();
      cctx.restore();

      // Live circular preview (76x76)
      const PW = prevCv.width;
      const PH = prevCv.height;
      pctx.clearRect(0, 0, PW, PH);
      pctx.save();
      pctx.beginPath();
      pctx.arc(PW / 2, PH / 2, PW / 2, 0, Math.PI * 2);
      pctx.clip();
      pctx.fillStyle = '#0f172a';
      pctx.fillRect(0, 0, PW, PH);
      drawTransformed(pctx, PW, PH, PW / (R * 2));
      pctx.restore();
    };

    const exportCroppedDataUrl = () => {
      const OUT = 400;
      const R = 128;
      const outCv = document.createElement('canvas');
      outCv.width = OUT;
      outCv.height = OUT;
      const octx = outCv.getContext('2d');
      octx.fillStyle = '#ffffff';
      octx.fillRect(0, 0, OUT, OUT);
      drawTransformed(octx, OUT, OUT, OUT / (R * 2));
      return outCv.toDataURL('image/png');
    };

    const openFilePicker = () => { fileIn.value = ''; fileIn.click(); };
    const upBtn = $('#ac-avatar-upload-btn');
    if (upBtn) upBtn.addEventListener('click', openFilePicker);
    const headTrig = $('#ac-head-avatar-trigger');
    if (headTrig) {
      headTrig.addEventListener('click', openFilePicker);
      headTrig.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openFilePicker(); } });
    }
    $('#ac-crop-pick-another').addEventListener('click', openFilePicker);

    fileIn.addEventListener('change', () => {
      const f = fileIn.files && fileIn.files[0];
      if (!f) return;
      if (!/^image\/(png|jpeg|webp)$/i.test(f.type)) {
        say('ac-avatar-msg', 'Please select a PNG, JPG, or WebP image.', false);
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        const im = new Image();
        im.onload = () => {
          img = im;
          cropModal.hidden = false;
          resetTransform();
        };
        im.src = reader.result;
      };
      reader.readAsDataURL(f);
    });

    const closeCrop = () => { cropModal.hidden = true; img = null; };
    $('#ac-crop-close').addEventListener('click', closeCrop);
    $('#ac-crop-cancel').addEventListener('click', closeCrop);

    cropCv.addEventListener('pointerdown', (e) => {
      if (!img) return;
      st.dragging = true;
      const rect = cropCv.getBoundingClientRect();
      const scale = cropCv.width / rect.width;
      st.sx = (e.clientX - rect.left) * scale - st.ox;
      st.sy = (e.clientY - rect.top) * scale - st.oy;
      cropCv.setPointerCapture(e.pointerId);
    });
    cropCv.addEventListener('pointermove', (e) => {
      if (!st.dragging || !img) return;
      const rect = cropCv.getBoundingClientRect();
      const scale = cropCv.width / rect.width;
      st.ox = (e.clientX - rect.left) * scale - st.sx;
      st.oy = (e.clientY - rect.top) * scale - st.sy;
      drawCrop();
    });
    ['pointerup', 'pointercancel'].forEach((ev) => cropCv.addEventListener(ev, () => { st.dragging = false; }));

    cropCv.addEventListener('wheel', (e) => {
      if (!img) return;
      e.preventDefault();
      const next = Math.max(50, Math.min(400, Number(zoomIn.value) + (e.deltaY < 0 ? 10 : -10)));
      zoomIn.value = String(next);
      st.zoom = next / 100;
      zoomVal.textContent = next + '%';
      drawCrop();
    }, { passive: false });

    zoomIn.addEventListener('input', () => {
      st.zoom = Number(zoomIn.value) / 100;
      zoomVal.textContent = zoomIn.value + '%';
      drawCrop();
    });
    angleIn.addEventListener('input', () => {
      st.angle = Number(angleIn.value);
      angleVal.textContent = angleIn.value + '°';
      drawCrop();
    });
    $('#ac-crop-rot-l').addEventListener('click', () => { st.rot90 = (st.rot90 - 90) % 360; drawCrop(); });
    $('#ac-crop-rot-r').addEventListener('click', () => { st.rot90 = (st.rot90 + 90) % 360; drawCrop(); });
    $('#ac-crop-flip-h').addEventListener('click', () => { st.flipH *= -1; drawCrop(); });
    $('#ac-crop-flip-v').addEventListener('click', () => { st.flipV *= -1; drawCrop(); });
    $('#ac-crop-reset').addEventListener('click', resetTransform);

    $('#ac-crop-save').addEventListener('click', async (e) => {
      if (!img) return;
      const dataUrl = exportCroppedDataUrl();
      const btn = e.currentTarget;
      btn.disabled = true;
      try {
        const j = await post('account/avatar', { avatar: dataUrl });
        applyAvatarToDom(j.avatar || dataUrl);
        say('ac-avatar-msg', j.message || 'Profile picture updated.', true);
        closeCrop();
      } catch (err) {
        say('ac-avatar-msg', err.message || 'Could not save profile picture.', false);
      } finally {
        btn.disabled = false;
      }
    });

    const rmBtn = $('#ac-avatar-remove-btn');
    if (rmBtn) {
      rmBtn.addEventListener('click', async (e) => {
        const j = await run(e.currentTarget, 'ac-avatar-msg', () => post('account/avatar', { remove: true }));
        if (j) applyAvatarToDom(null);
      });
    }
  }

  // password
  const strength = (p) => {
    let s = 0;
    if (p.length >= Number(root.dataset.min || 8)) s++;
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
    if (/\d/.test(p)) s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    if (p.length >= 14) s++;
    return s;
  };
  $('#s-new').addEventListener('input', () => {
    const p = $('#s-new').value; const s = p ? strength(p) : 0;
    const bar = $('#s-bar'); bar.style.width = (s * 20) + '%'; bar.style.background = s <= 2 ? '#ef4444' : (s <= 3 ? '#f59e0b' : '#16a34a');
    $('#s-hint').textContent = !p ? 'Type a new password to see its strength.' : (s <= 2 ? 'Weak' : (s <= 3 ? 'Fair' : (s === 4 ? 'Good' : 'Strong')));
  });
  $('#s-save').addEventListener('click', async (e) => {
    const j = await run(e.currentTarget, 's-msg', () => post('account/password', { current: $('#s-current').value, password: $('#s-new').value, confirm: $('#s-confirm').value }));
    if (j) { ['#s-current', '#s-new', '#s-confirm'].forEach((i) => { $(i).value = ''; }); $('#s-bar').style.width = '0'; }
  });

  // my settings
  $('#f-save').addEventListener('click', async (e) => {
    const themeSel = $('#f-theme') ? $('#f-theme').value : (document.documentElement.dataset.theme || 'light');
    const j = await run(e.currentTarget, 'f-msg', () => post('account/prefs', {
      landing: $('#f-landing').value, sidebar: $('#f-sidebar').value, theme: themeSel, page_size: Number($('#f-page').value),
      chat_active: $('#f-active') ? $('#f-active').checked : true,
      notify_chat: $('#f-chat').checked, notify_approval: $('#f-appr').checked, toasts: $('#f-toast').checked,
    }));
    if (j) {
      try {
        localStorage.setItem('nx.side', $('#f-sidebar').value === 'collapsed' ? '1' : '0');
        localStorage.setItem('nx.toasts', $('#f-toast').checked ? '1' : '0');
        const uid = document.documentElement.dataset.uid || '0';
        localStorage.setItem('nx.theme.' + uid, themeSel);
      } catch (x) { /* private mode */ }
      if (window.nxSetTheme) window.nxSetTheme(themeSel, true);
    }
  });

  // signature (Manager, Supervisor, Admin)
  const cv = $('#g-canvas');
  if (cv) {
    const ctx = cv.getContext('2d'); let draw = false; let dirty = false;
    ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.strokeStyle = '#111827';
    const pos = (e) => { const r = cv.getBoundingClientRect(); return [(e.clientX - r.left) * cv.width / r.width, (e.clientY - r.top) * cv.height / r.height]; };
    cv.addEventListener('pointerdown', (e) => { draw = true; const [x, y] = pos(e); ctx.beginPath(); ctx.moveTo(x, y); cv.setPointerCapture(e.pointerId); });
    cv.addEventListener('pointermove', (e) => { if (!draw) return; const [x, y] = pos(e); ctx.lineTo(x, y); ctx.stroke(); dirty = true; });
    ['pointerup', 'pointercancel'].forEach((ev) => cv.addEventListener(ev, () => { draw = false; }));
    $('#g-clear').addEventListener('click', () => { ctx.clearRect(0, 0, cv.width, cv.height); dirty = false; });
    $('#g-save').addEventListener('click', async (e) => {
      if (!dirty) { say('g-msg', 'Draw your signature first.', false); return; }
      const j = await run(e.currentTarget, 'g-msg', () => post('account/signature', { png: cv.toDataURL('image/png') }));
      if (j) setTimeout(() => location.reload(), 700);
    });
    const rm = $('#g-remove');
    if (rm) rm.addEventListener('click', async (e) => { const j = await run(e.currentTarget, 'g-msg', () => post('account/signature', { png: '' })); if (j) setTimeout(() => location.reload(), 700); });
  }
})();
