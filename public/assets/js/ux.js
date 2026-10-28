/**
 * NEXUS-L shared UX helpers (loaded on every signed-in page):
 *  - nxToast(message, type)      in-app notification instead of alert()
 *  - nxConfirm(message, options) in-app confirmation (Promise<boolean>) instead of confirm()
 *  - nxState(el, kind, message)  loading / empty / error state for lists and tables
 *  - labels are tied to their inputs automatically and icon-only buttons get an accessible name
 */
(() => {
  const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

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
    setTimeout(() => { t.classList.add('nx-out'); setTimeout(() => t.remove(), 250); }, ms);
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
    const done = (v) => { back.remove(); document.removeEventListener('keydown', onKey, true); try { prev && prev.focus(); } catch (e) { /* gone */ } resolve(v); };
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
