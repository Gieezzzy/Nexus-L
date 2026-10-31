/**
 * NEXUS-L shared UX helpers (loaded on every signed-in page):
 *  - nxToast(message, type)      in-app notification instead of alert()
 *  - nxConfirm(message, options) in-app confirmation (Promise<boolean>) instead of confirm()
 *  - nxState(el, kind, message)  loading / empty / error state for lists and tables
 *  - labels are tied to their inputs automatically and icon-only buttons get an accessible name
 */
(() => {
  const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  // ------------------------------------------- page behind an open dialog
  // While any dialog is open the page behind it does not scroll (the dialog itself still does).
  const DIALOGS = '.nx-modal-back, #nx-rv, #nx-ask, .il-modal-backdrop:not(.hidden), .dtr-modal-backdrop:not(.hidden), dialog[open]';
  let locked = false;
  let lockTimer = 0;
  const syncScrollLock = () => {
    lockTimer = 0;
    const open = !!document.querySelector(DIALOGS);
    if (open === locked) return;
    locked = open;
    const root = document.documentElement;
    if (open) {
      const bar = window.innerWidth - root.clientWidth;               // keeps the page from jumping sideways when the scrollbar goes
      root.dataset.nxScrollLock = '1';
      root.style.overflow = 'hidden';
      if (bar > 0) root.style.paddingRight = bar + 'px';
    } else {
      delete root.dataset.nxScrollLock;
      root.style.overflow = '';
      root.style.paddingRight = '';
    }
  };
  new MutationObserver(() => { if (!lockTimer) lockTimer = requestAnimationFrame(syncScrollLock); })
    .observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'open'] });
  syncScrollLock();

  // ------------------------------------------------------------------ toast
  let toastHost = null;
  window.nxToast = (message, type = 'info', ms = 4200) => {
    if (!toastHost) {
      toastHost = document.createElement('div');
      toastHost.className = 'nx-toasts';
      toastHost.setAttribute('role', 'status');
      toastHost.setAttribute('aria-live', 'polite');
      document.body.appendChild(toastHost);
    }
    const t = document.createElement('div');
    t.className = 'nx-toast nx-toast-' + type;
    t.textContent = String(message);
    toastHost.appendChild(t);
    if (typeof window.anime === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      window.anime({
        targets: t,
        opacity: [0, 1],
        translateY: [14, 0],
        scale: [0.95, 1],
        duration: 240,
        easing: 'easeOutBack(1.4)'
      });
    }
    setTimeout(() => {
      if (typeof window.anime === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        window.anime({
          targets: t,
          opacity: [1, 0],
          translateY: [0, 8],
          duration: 180,
          easing: 'easeInQuad',
          complete: () => t.remove()
        });
      } else {
        t.classList.add('nx-out');
        setTimeout(() => t.remove(), 250);
      }
    }, ms);
  };

  // ---------------------------------------------------------------- confirm
  window.nxConfirm = (message, opts = {}) => new Promise((resolve) => {
    const prev = document.activeElement;
    const back = document.createElement('div');
    back.className = 'nx-modal-back';
    back.innerHTML = `<div class="nx-modal" role="alertdialog" aria-modal="true" aria-labelledby="nx-modal-msg">
        <p id="nx-modal-msg">${esc(message).replace(/\n/g, '<br>')}</p>
        <div class="nx-modal-actions">
          <button type="button" class="nx-btn" data-r="0">${esc(opts.cancelText || 'Cancel')}</button>
          <button type="button" class="nx-btn ${opts.danger === false ? 'nx-primary' : 'nx-danger'}" data-r="1">${esc(opts.okText || 'Yes, continue')}</button>
        </div></div>`;
    const done = (v) => {
      const modal = back.querySelector('.nx-modal');
      if (typeof window.anime === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches && modal) {
        window.anime({
          targets: [back, modal],
          opacity: [1, 0],
          duration: 140,
          easing: 'easeInQuad',
          complete: () => { back.remove(); }
        });
      } else {
        back.remove();
      }
      document.removeEventListener('keydown', onKey, true);
      try { prev && prev.focus(); } catch (e) { /* gone */ }
      resolve(v);
    };
    const onKey = (e) => {
      if (e.key === 'Escape') { e.preventDefault(); done(false); }
      if (e.key === 'Tab') { // keep focus inside the dialog
        const b = [...back.querySelectorAll('button')];
        const i = b.indexOf(document.activeElement);
        e.preventDefault();
        b[(i + (e.shiftKey ? -1 : 1) + b.length) % b.length].focus();
      }
    };
    back.addEventListener('click', (e) => { if (e.target === back) done(false); const r = e.target.dataset && e.target.dataset.r; if (r !== undefined) done(r === '1'); });
    document.addEventListener('keydown', onKey, true);
    document.body.appendChild(back);
    const modalEl = back.querySelector('.nx-modal');
    if (typeof window.anime === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches && modalEl) {
      window.anime({ targets: back, opacity: [0, 1], duration: 160, easing: 'easeOutQuad' });
      window.anime({ targets: modalEl, scale: [0.93, 1], opacity: [0, 1], duration: 220, easing: 'easeOutBack(1.4)' });
    }
    back.querySelector('[data-r="0"]').focus();
  });

  // ---------------------------------------------------- loading / empty / error
  /** nxState(tbodyOrList, 'loading'|'empty'|'error', 'text', colspan?) */
  window.nxState = (el, kind, text, cols) => {
    if (!el) return;
    const label = text || { loading: 'Loading…', empty: 'Nothing to show yet.', error: 'Something went wrong. Try again.' }[kind];
    const cell = `<div class="nx-state nx-state-${kind}" role="${kind === 'error' ? 'alert' : 'status'}">${kind === 'loading' ? '<span class="nx-spin" aria-hidden="true"></span>' : ''}${esc(label)}</div>`;
    el.innerHTML = el.tagName === 'TBODY' ? `<tr><td colspan="${cols || 20}">${cell}</td></tr>` : cell;
  };

  // --------------------------------------------- accessible names and labels
  let n = 0;
  const controlOf = (l) => {
    if (l.querySelector('input,select,textarea')) return null; // already wraps its control
    let c = l.nextElementSibling;
    if (c && /^(INPUT|SELECT|TEXTAREA)$/.test(c.tagName)) return c;
    if (c) { const inner = c.querySelector && c.querySelector('input:not([type=hidden]),select,textarea'); if (inner) return inner; }
    const p = l.parentElement;
    if (!p) return null;
    const all = p.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]),select,textarea');
    return all.length === 1 ? all[0] : null;
  };
  const enhance = (root) => {
    (root || document).querySelectorAll('label:not([for])').forEach((l) => {
      const c = controlOf(l);
      if (!c) return;
      if (!c.id) c.id = 'nx_f' + (++n);
      l.htmlFor = c.id;
    });
    (root || document).querySelectorAll('button:not([aria-label]),a.btn:not([aria-label])').forEach((b) => {
      const t = (b.textContent || '').replace(/\s+/g, '');
      if (t.length <= 2 && !b.querySelector('img[alt]')) b.setAttribute('aria-label', b.getAttribute('title') || (/[×✕x]/i.test(t) ? 'Close' : 'Action'));
    });
    // controls with no label at all (e.g. grid filter boxes): use the placeholder / title / column name
    (root || document).querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([aria-label]):not([id]),select:not([aria-label]):not([id]),textarea:not([aria-label]):not([id])').forEach((c) => {
      if (c.closest('label')) return;
      const t = c.getAttribute('placeholder') || c.getAttribute('title') || c.dataset.col || c.name;
      if (t) c.setAttribute('aria-label', t);
    });
    (root || document).querySelectorAll('img:not([alt])').forEach((i) => i.setAttribute('alt', ''));
  };
  let timer = null;
  const later = () => { clearTimeout(timer); timer = setTimeout(() => enhance(document), 250); };
  const start = () => { enhance(document); new MutationObserver(later).observe(document.body, { childList: true, subtree: true }); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();

// ---- collapsible sidebar on large screens (state is remembered per browser) --------------------------------
(() => {
  const root = document.documentElement;
  const btn = document.getElementById('sidebar-toggle');
  if (!btn) return;
  const isDesktop = () => window.innerWidth >= 1024;
  const sync = () => {
    const c = root.classList.contains('side-collapsed');
    btn.setAttribute('aria-expanded', String(!c));
    btn.setAttribute('aria-label', c ? 'Expand sidebar' : 'Collapse sidebar');
    btn.title = c ? 'Expand sidebar' : 'Collapse sidebar';
    document.querySelectorAll('#sidebar .nav-link').forEach((a) => {
      if (!a.dataset.label) a.dataset.label = (a.textContent || '').replace(/\s+/g, ' ').trim();
      if (c) a.title = a.dataset.label; else a.removeAttribute('title');
    });
  };
  btn.addEventListener('click', (e) => {
    if (!isDesktop()) return;          // phones keep the slide-in menu
    e.stopImmediatePropagation();
    root.classList.toggle('side-collapsed');
    try { localStorage.setItem('nx.side', root.classList.contains('side-collapsed') ? '1' : '0'); } catch (x) { /* private mode */ }
    sync();
    setTimeout(() => window.dispatchEvent(new Event('resize')), 260); // let charts / the designer stage re-fit
  }, true);
  window.addEventListener('resize', () => { if (!isDesktop()) root.classList.remove('side-collapsed'); else { try { if (localStorage.getItem('nx.side') === '1') root.classList.add('side-collapsed'); } catch (x) { /* ignore */ } } sync(); });
  sync();
})();

// Signature pad on a <canvas> (report approval page, Designer → Signatories). Returns {clear, isEmpty, toDataURL, load}.
window.nxSignaturePad = (canvas) => {
  const ctx = canvas.getContext('2d');
  let drawing = false;
  let empty = true;
  const fit = () => {                       // keep the drawing sharp at the displayed size
    const r = canvas.getBoundingClientRect();
    if (!r.width) return;
    const keep = empty ? null : canvas.toDataURL();
    canvas.width = Math.round(r.width * (window.devicePixelRatio || 1));
    canvas.height = Math.round(r.height * (window.devicePixelRatio || 1));
    ctx.lineWidth = 2.4 * (window.devicePixelRatio || 1);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1e3a8a';
    if (keep) pad.load(keep);
  };
  const at = (e) => {
    const r = canvas.getBoundingClientRect();
    return [(e.clientX - r.left) * (canvas.width / r.width), (e.clientY - r.top) * (canvas.height / r.height)];
  };
  canvas.addEventListener('pointerdown', (e) => { drawing = true; empty = false; canvas.setPointerCapture(e.pointerId); ctx.beginPath(); ctx.moveTo(...at(e)); });
  canvas.addEventListener('pointermove', (e) => { if (!drawing) return; ctx.lineTo(...at(e)); ctx.stroke(); });
  ['pointerup', 'pointercancel'].forEach((t) => canvas.addEventListener(t, () => { drawing = false; }));
  const pad = {
    clear: () => { ctx.clearRect(0, 0, canvas.width, canvas.height); empty = true; },
    isEmpty: () => empty,
    toDataURL: () => canvas.toDataURL('image/png'),
    load: (src) => new Promise((res) => {
      const img = new Image();
      img.onload = () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const k = Math.min(canvas.width / img.width, canvas.height / img.height);
        ctx.drawImage(img, (canvas.width - img.width * k) / 2, (canvas.height - img.height * k) / 2, img.width * k, img.height * k);
        empty = false;
        res();
      };
      img.src = src;
    }),
  };
  // A picture of a signature (PNG / JPG, up to 2 MB) drawn into the box. A white or near-white background becomes
  // transparent, so a scanned signature does not put a white block over the report when it is stamped.
  pad.loadFile = (file) => new Promise((resolve, reject) => {
    if (!file || !/^image\/(png|jpeg)$/.test(file.type)) { reject(new Error('Choose a PNG or JPG picture of the signature.')); return; }
    if (file.size > 2 * 1024 * 1024) { reject(new Error('The picture is larger than 2 MB.')); return; }
    const rd = new FileReader();
    rd.onerror = () => reject(new Error('The picture could not be read.'));
    rd.onload = () => {
      pad.load(String(rd.result)).then(() => {
        const px = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const d = px.data;
        let hasAlpha = false;
        for (let i = 3; i < d.length; i += 4) { if (d[i] < 250) { hasAlpha = true; break; } }
        if (!hasAlpha) {
          for (let i = 0; i < d.length; i += 4) {
            const lum = (d[i] + d[i + 1] + d[i + 2]) / 3;
            if (lum > 240) d[i + 3] = 0;
            else if (lum > 200) d[i + 3] = Math.round(((240 - lum) / 40) * 255);
          }
          ctx.putImageData(px, 0, 0);
        }
        resolve();
      }).catch(() => reject(new Error('The picture could not be opened.')));
    };
    rd.readAsDataURL(file);
  });
  fit();
  window.addEventListener('resize', fit);
  return pad;
};

// -----------------------------------------------------------------------------
// NEXUS-L Animation Engine (Anime.js integration): Ultra-lightweight & GPU-accelerated
// -----------------------------------------------------------------------------
(() => {
  const prefersReduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  window.nxMotion = {
    reduced: prefersReduced,

    // Safe execution wrapper for Anime.js
    animate(opts) {
      if (prefersReduced() || typeof window.anime !== 'function') {
        if (opts.complete) opts.complete();
        return null;
      }
      return window.anime(opts);
    },

    // Smooth staggered entrance for cards, stats, and sections on navigation
    staggerIn(selectorOrEls, delay = 0) {
      if (prefersReduced() || typeof window.anime !== 'function') return;
      const targets = typeof selectorOrEls === 'string' ? [...document.querySelectorAll(selectorOrEls)] : (Array.isArray(selectorOrEls) ? selectorOrEls : [selectorOrEls]);
      const valid = targets.filter((el) => el && !el.dataset.nxAnimated && !el.closest('[hidden]'));
      if (!valid.length) return;
      valid.forEach((el) => { el.dataset.nxAnimated = '1'; });
      window.anime({
        targets: valid,
        opacity: [0, 1],
        translateY: [10, 0],
        delay: window.anime.stagger(25, { start: delay }),
        duration: 260,
        easing: 'easeOutCubic'
      });
    },

    // Bouncy spring micro-pop for buttons, icons, and pills
    pop(el) {
      if (prefersReduced() || typeof window.anime !== 'function' || !el) return;
      window.anime({
        targets: el,
        scale: [{ value: 0.94, duration: 60, easing: 'easeInQuad' }, { value: 1, duration: 160, easing: 'easeOutBack(2)' }]
      });
    },

    // Smooth tab cross-fade and subtle slide
    switchTab(pane) {
      if (prefersReduced() || typeof window.anime !== 'function' || !pane) return;
      window.anime({
        targets: pane,
        opacity: [0, 1],
        translateX: [-6, 0],
        duration: 200,
        easing: 'easeOutCubic'
      });
    }
  };

  // Staggered card entrance on initial page load / navigation
  const initPageEntrance = () => {
    if (document.hidden) return;
    requestAnimationFrame(() => {
      window.nxMotion.staggerIn('main .card, main .stat, main .g-hero', 15);
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPageEntrance);
  } else {
    initPageEntrance();
  }

  // Hook global tab clicks (.br-tab, .dtr-tab-btn, .st-tab) for smooth transitions
  document.addEventListener('click', (e) => {
    const tab = e.target.closest('.br-tab, .dtr-tab-btn, .st-tab, [data-tab]');
    if (!tab) return;
    window.nxMotion.pop(tab);
    setTimeout(() => {
      const activePane = document.querySelector('.br-pane:not([hidden]), .dtr-pane:not([hidden]), .st-pane:not([hidden])');
      if (activePane) window.nxMotion.switchTab(activePane);
    }, 10);
  }, true);
})();
