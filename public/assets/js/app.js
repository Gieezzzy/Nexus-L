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

  // ---- Site-wide Browser Notification Prompt on Website Open ----
  const initBrowserNotificationPrompt = () => {
    if (!('Notification' in window)) return;

    const promptEl = $('#nexus-browser-notif-prompt');
    const getBaseUrl = () => {
      const meta = document.querySelector('meta[name="base-url"]');
      if (meta && meta.content) return meta.content.replace(/\/+$/, '') + '/';
      const a = document.querySelector('a[href$="/inbox"]');
      if (a && a.href) return a.href.replace(/\/inbox\/?$/, '') + '/';
      return location.origin + '/';
    };
    const BASE_URL = getBaseUrl();
    const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');

    const saveFcmToken = (token) => {
      if (!token) return;
      const headers = {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      };
      if (csrfMeta && csrfMeta.content) headers['X-CSRF-TOKEN'] = csrfMeta.content;

      fetch(`${BASE_URL}inbox/token`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers,
        body: JSON.stringify({ token, device_type: 'web' })
      })
      .then((r) => r.json())
      .then((j) => {
        if (csrfMeta && j && j.csrf) csrfMeta.content = j.csrf;
      })
      .catch((err) => console.warn('[FCM] Token register error:', err));
    };

    const registerServiceWorker = () => {
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(`${BASE_URL}firebase-messaging-sw.js`)
          .then((reg) => {
            if (reg.pushManager) {
              reg.pushManager.getSubscription().then((sub) => {
                if (sub && sub.endpoint) saveFcmToken(sub.endpoint);
              }).catch(() => {});
            }
          })
          .catch((err) => console.warn('[FCM] SW registration failed:', err));
      }
    };

    const playNotificationChime = () => {
      try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const now = ctx.currentTime;
        const playTone = (freq, start, duration) => {
          const osc = ctx.createOscillator();
          const gain = ctx.createGain();
          osc.type = 'sine';
          osc.frequency.setValueAtTime(freq, start);
          gain.gain.setValueAtTime(0, start);
          gain.gain.linearRampToValueAtTime(0.2, start + 0.03);
          gain.gain.exponentialRampToValueAtTime(0.001, start + duration);
          osc.connect(gain);
          gain.connect(ctx.destination);
          osc.start(start);
          osc.stop(start + duration);
        };
        playTone(523.25, now, 0.15);
        playTone(659.25, now + 0.12, 0.15);
        playTone(783.99, now + 0.24, 0.28);
      } catch (_) {}
    };

    const showSiteToast = (msg) => {
      const toast = document.createElement('div');
      toast.className = 'glass';
      toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:10000;padding:12px 18px;border-radius:10px;background:#10b981;color:#fff;font-weight:600;font-size:13px;box-shadow:0 8px 24px rgba(16,185,129,.35);transition:all .3s ease;';
      toast.innerHTML = `<span style="margin-right:6px">✓</span> ${msg}`;
      document.body.appendChild(toast);
      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 350);
      }, 4000);
    };

    const hidePrompt = () => {
      if (!promptEl) return;
      promptEl.classList.add('nb-fade-out');
      setTimeout(() => promptEl.classList.add('hidden'), 260);
    };

    if (Notification.permission === 'granted') {
      registerServiceWorker();
      return;
    }

    if (Notification.permission === 'default' && promptEl) {
      const dismissed = sessionStorage.getItem('nexusl:notif_prompt_dismissed') === '1';
      if (!dismissed) {
        setTimeout(() => {
          promptEl.classList.remove('hidden');
        }, 800);
      }

      $('#nb-btn-allow')?.addEventListener('click', () => {
        Notification.requestPermission().then((permission) => {
          hidePrompt();
          if (permission === 'granted') {
            playNotificationChime();
            registerServiceWorker();
            showSiteToast('Desktop & browser notifications enabled successfully!');
          }
        });
      });

      const dismissAction = () => {
        try { sessionStorage.setItem('nexusl:notif_prompt_dismissed', '1'); } catch (_) {}
        hidePrompt();
      };

      $('#nb-btn-dismiss')?.addEventListener('click', dismissAction);
      $('#nb-btn-close')?.addEventListener('click', dismissAction);
    }
  };

  initBrowserNotificationPrompt();
})();