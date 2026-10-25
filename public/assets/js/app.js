/* NEXUS-L front-end helpers (no dependencies) */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const STORE = 'nexusl:lastUser';

  // Never show a protected page restored from the back/forward cache after logout.
  window.addEventListener('pageshow', (e) => { if (e.persisted) location.reload(); });

  // ---- Show / hide password ----
  $$('[data-toggle-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.togglePassword);
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show);
      btn.querySelector('[data-eye]')?.classList.toggle('hidden', show);
      btn.querySelector('[data-eye-off]')?.classList.toggle('hidden', !show);
    });
  });

  // ---- Caps-lock warning ----
  $$('input[type=password]').forEach((input) => {
    const warn = $(`[data-capslock-for="${input.id}"]`);
    if (!warn) return;
    const check = (e) => warn.classList.toggle('hidden', !e.getModifierState?.('CapsLock'));
    input.addEventListener('keyup', check);
    input.addEventListener('keydown', check);
    input.addEventListener('blur', () => warn.classList.add('hidden'));
  });

  // ---- Password strength meter ----
  $$('[data-strength-for]').forEach((meter) => {
    const input = document.getElementById(meter.dataset.strengthFor);
    const bar = $('[data-bar]', meter), label = $('[data-label]', meter);
    const levels = [
      ['Very weak', 's0', '15%'], ['Weak', 's1', '35%'],
      ['Fair', 's2', '55%'], ['Good', 's3', '78%'], ['Strong', 's4', '100%'],
    ];
    input.addEventListener('input', () => {
      const v = input.value;
      let s = 0;
      if (v.length >= 8) s++;
      if (v.length >= 12) s++;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
      if (/\d/.test(v)) s++;
      if (/[^A-Za-z0-9]/.test(v)) s++;
      const [txt, cls, w] = levels[Math.max(0, Math.min(4, s - 1))];
      bar.className = `bar ${v ? cls : ''}`;
      bar.style.width = v ? w : '0';
      label.textContent = v ? txt : '';
    });
  });

  // ---- Confirm-password live match ----
  $$('[data-match]').forEach((confirm) => {
    const src = document.getElementById(confirm.dataset.match);
    const hint = $(`[data-match-hint="${confirm.id}"]`);
    const run = () => {
      if (!confirm.value) { hint?.classList.add('hidden'); return; }
      const ok = confirm.value === src.value;
      confirm.setCustomValidity(ok ? '' : 'Passwords do not match');
      if (hint) { hint.classList.remove('hidden'); hint.textContent = ok ? '✓ Passwords match' : '✗ Passwords do not match'; hint.className = `hint ${ok ? 'ok' : 'bad'}`; }
    };
    confirm.addEventListener('input', run); src.addEventListener('input', run);
  });

  // ---- Prevent double submit + loading state ----
  $$('form[data-loading]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (!form.checkValidity()) return;
      if (form.dataset.submitted) { e.preventDefault(); return; }
      form.dataset.submitted = '1';
      const btn = $('button[type=submit]', form);
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spin"></span> Please wait…';
      }
    });
  });

  // ---- Remember last username locally (never the password) ----
  const loginForm = $('#login-form');
  if (loginForm) {
    const loginInput = $('#login'), remember = $('#remember');
    try {
      const saved = localStorage.getItem(STORE);
      if (saved && !loginInput.value) { loginInput.value = saved; remember.checked = true; $('#password').focus(); }
    } catch (_) { /* storage disabled */ }
    loginForm.addEventListener('submit', () => {
      try { remember.checked ? localStorage.setItem(STORE, loginInput.value.trim()) : localStorage.removeItem(STORE); } catch (_) {}
    });
  }

  // ---- Wipe per-session browser storage on logout ----
  $$('form[action$="/logout"]').forEach((f) => f.addEventListener('submit', () => { try { sessionStorage.clear(); } catch (_) {} }));

  // ---- 6-digit OTP boxes ----
  const otp = $('[data-otp]');
  if (otp) {
    const boxes = $$('input', otp), hidden = $('#code');
    const sync = () => { hidden.value = boxes.map((b) => b.value).join(''); };
    boxes.forEach((box, i) => {
      box.addEventListener('input', () => {
        box.value = box.value.replace(/\D/g, '').slice(-1);
        if (box.value && boxes[i + 1]) boxes[i + 1].focus();
        sync();
        if (hidden.value.length === 6) otp.closest('form').requestSubmit();
      });
      box.addEventListener('keydown', (e) => { if (e.key === 'Backspace' && !box.value && boxes[i - 1]) boxes[i - 1].focus(); });
      box.addEventListener('paste', (e) => {
        const digits = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
        if (!digits) return;
        e.preventDefault();
        digits.split('').forEach((d, k) => { if (boxes[k]) boxes[k].value = d; });
        sync();
        (boxes[digits.length] || boxes[5]).focus();
        if (digits.length === 6) otp.closest('form').requestSubmit();
      });
    });
    boxes[0].focus();
  }

  // ---- Countdown (code expiry / resend cooldown) ----
  $$('[data-countdown]').forEach((el) => {
    let s = parseInt(el.dataset.countdown, 10);
    const target = el.dataset.enable ? document.getElementById(el.dataset.enable) : null;
    const tick = () => {
      el.textContent = `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
      if (s-- <= 0) { clearInterval(t); el.textContent = '00:00'; target?.removeAttribute('disabled'); }
    };
    const t = setInterval(tick, 1000); tick();
  });

  // ---- Mobile sidebar ----
  const sb = $('#sidebar'), bd = $('#sidebar-backdrop');
  const toggle = (open) => { sb?.classList.toggle('open', open); bd?.classList.toggle('hidden', !open); };
  $('#sidebar-toggle')?.addEventListener('click', () => toggle(!sb.classList.contains('open')));
  bd?.addEventListener('click', () => toggle(false));

  // ---- Inbox unread badge (sidebar), polled lightly ----
  const badge = $('#nav-unread');
  if (badge) {
    const base = document.querySelector('a[href$="/inbox"]')?.getAttribute('href');
    const poll = () => {
      if (document.hidden || !base) return;
      fetch(base + '/unread', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((d) => { if (!d) return; badge.textContent = d.unread > 99 ? '99+' : d.unread; badge.classList.toggle('hidden', !d.unread); })
        .catch(() => {});
    };
    poll(); setInterval(poll, 30000);
  }

  // ---- Auto-dismiss success alerts ----
  $$('[data-autodismiss]').forEach((el) => setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 6000));
})();
