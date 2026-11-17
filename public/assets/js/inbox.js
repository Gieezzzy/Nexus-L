/* NEXUS-L Inbox: Messenger-style chat. All user text goes through textContent (no HTML injection). */
(() => {
  'use strict';
  const root = document.getElementById('msgr');
  if (!root) return;
  const BASE = root.dataset.base;
  const ACCOUNT_AVATAR_URL = root.dataset.accountAvatar || BASE.replace(/\/inbox\/?$/, '/account/avatar');
  let myAvatar = root.dataset.myAvatar || '';
  const myName = root.dataset.myName || 'Me';
  const ADMIN = root.dataset.admin === '1';
  const $ = (id) => document.getElementById(id);
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const E = window.NexusEmoji || { cats: [], all: [] };
  const DEFAULT_QUICK = ['😆', '🥺', '🥰', '😮', '❤️', '👍'];
  const SYS = { n: 'NEXUS-L', r: 'RMS' };

  const el = { items: $('msgr-items'), none: $('msgr-none'), q: $('msgr-q'), blank: $('msgr-blank'), pane: $('msgr-pane'),
    msgs: $('msgr-msgs'), form: $('msgr-form'), input: $('msgr-input'), name: $('msgr-name'), sub: $('msgr-sub'),
    av: $('msgr-av'), err: $('msgr-err'), back: $('msgr-back'), banner: $('msgr-banner'), atts: $('msgr-atts'),
    ctx: $('msgr-ctx'), attMenu: $('msgr-attach-menu'), emojiPop: $('msgr-emoji-pop'), dlg: $('dlg'), dlgCard: $('dlg-card'),
    sendIco: $('msgr-send-ico'), sendQuick: $('msgr-send-quick'), imgIn: $('msgr-file-img'), anyIn: $('msgr-file-any'),
    particleCanvas: $('msgr-particle-canvas'), cinemaOverlay: $('msgr-cinema-overlay'),
    approvalBanner: $('msgr-approval-banner'), abActions: $('ab-actions'), abApprove: $('ab-btn-approve'), abReject: $('ab-btn-reject'),
    abTitle: $('ab-title'), abDesc: $('ab-desc'), disappearingBar: $('msgr-disappearing-bar'), disappearingTxt: $('msgr-disappearing-txt'),
    filters: $('msgr-filters'), pinBar: $('msgr-pin-bar'), pinText: $('msgr-pin-text'), pinCount: $('msgr-pin-count'),
    infThemeBtn: $('inf-theme-btn'), infThemeName: $('inf-theme-name'), infDisBtn: $('inf-dis-btn'), infDis: $('inf-dis'),
    infMembersBtn: $('inf-members-btn'), infMembersCount: $('inf-members-count'),
    infPhotoBtn: $('inf-photo-btn'), infMyPhotoBtn: $('inf-my-photo-btn'), myAvBtn: $('msgr-my-avatar-btn'), myAv: $('msgr-my-av'),
    infRenameBtn: $('inf-rename-btn'), infAddMembersBtn: $('inf-add-members-btn'),
    infMute: $('inf-mute'), infBlockBtn: $('inf-block-btn'), infLeaveBtn: $('inf-leave-btn') };

  // ---------- state ----------
  let key = null, peer = null, me = '';
  let lastId = 0, oldestId = 0, hasMore = false, loadingOlder = false, lastDay = '', lastSender = null, lastTime = 0, seenEl = null, tick = 0, busy = false;
  let settings = null, nicks = { me: '', peer: '' }, convFilter = 'all', pinIdx = 0;
  const msgData = new Map();
  let pending = [];                       // files waiting to be sent
  let replyTo = null;                     // message being replied to {id, from, text, image, file}
  let people = [], convCache = [];
  let currentThemeKey = 'default';
  let particleAnimId = null;
  let particles = [];
  const triggeredMsgIds = new Set();

  // ---------- helpers ----------
  const initials = (n) => String(n).trim().split(/\s+/).slice(0, 2).map((p) => p[0] || '').join('').toUpperCase() || '?';
  const hue = (s) => { let h = 0; for (const c of String(s)) h = (h * 31 + c.charCodeAt(0)) % 360; return h; };
  const avatar = (node, p) => {
    node.textContent = ''; node.style.background = '';
    if (p.avatar) {
      const img = document.createElement('img'); img.src = p.avatar; img.alt = ''; img.loading = 'lazy';
      img.addEventListener('error', () => { img.remove(); node.textContent = initials(p.name); node.style.background = `hsl(${hue(p.key || p.id)} 55% 42%)`; });
      node.append(img);
    } else { node.textContent = initials(p.name); node.style.background = `hsl(${hue(p.key || p.id)} 55% 42%)`; }
    const isDirect = !p.isGroup && p.src !== 'g' && p.src !== 'rg' && !node.classList.contains('xs') && !node.classList.contains('sm');
    node.classList.toggle('online', isDirect && !!p.online);
    node.classList.toggle('offline', isDirect && !p.online);
  };
  const parse = (s) => new Date(String(s).replace(' ', 'T').replace(/\.(\d{3})\d*$/, '.$1'));
  const fmtTime = (d) => d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  const fmtShort = (s) => { const d = parse(s), n = new Date(); return d.toDateString() === n.toDateString() ? fmtTime(d) : d.toLocaleDateString([], { month: 'short', day: 'numeric' }); };
  const fmtSize = (b) => (b < 1024 ? `${b} B` : b < 1048576 ? `${(b / 1024).toFixed(0)} KB` : `${(b / 1048576).toFixed(1)} MB`);
  const show = (n, on) => n.classList.toggle('hidden', !on);
  const mk = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; };
  const enc = encodeURIComponent;
  const quick = () => {
    try {
      const q = JSON.parse(localStorage.getItem('nexusl:quick') || 'null');
      if (Array.isArray(q) && q.length === 6) return q;
    } catch (_) {}
    if (currentThemeKey === 'christmas') return ['❄️', '☃️', '🎄', '🎅', '🎁', '❤️'];
    if (currentThemeKey === 'sadako' || currentThemeKey === 'holloween') return ['👻', '🎃', '💀', '😱', '🩸', '🔪'];
    if (currentThemeKey === 'valentines') return ['❤️', '🥰', '💖', '🌹', '💌', '✨'];
    if (currentThemeKey === 'anime_cyberpunk') return ['⚡', '🤖', '👾', '✨', '🔥', '🚀'];
    return DEFAULT_QUICK;
  };
  const banner = (t) => { el.banner.textContent = t || ''; show(el.banner, !!t); };
  const flashErr = (t) => { el.err.textContent = t || ''; show(el.err, !!t); if (t) setTimeout(() => show(el.err, false), 6000); };

  // ---------- desktop notifications & chime ----------
  const playChime = () => {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.12);
      gain.gain.setValueAtTime(0.18, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.36);
    } catch (_) {}
  };

  const notifyDesktop = (m) => {
    playChime();
    if ('Notification' in window && Notification.permission === 'granted') {
      const fromName = (nicks && nicks.peer) || (peer && peer.name) || 'New message';
      const body = m.text || (m.image ? '📷 Sent an image' : (m.file ? '📄 ' + m.file.name : 'Sent an attachment'));
      try {
        const notif = new Notification(fromName, {
          body: body,
          icon: (peer && peer.avatar) ? peer.avatar : `${BASE.replace(/\/inbox$/, '')}/assets/favicon.ico`,
          tag: 'msgr-' + (m.id || Date.now()),
        });
        notif.onclick = () => {
          window.focus();
          if (peer && key && !root.classList.contains('open')) {
            root.classList.add('open');
          }
          notif.close();
        };
      } catch (err) {
        console.warn('[Inbox] Notification error:', err);
      }
    }
    const origTitle = document.title.replace(/^\(\d+\)\s*/, '');
    document.title = `(1) ${origTitle}`;
  };

  const saveFcmToken = (token) => {
    if (!token) return;
    post('token', { token, device_type: 'web' }).catch((err) => console.warn('[FCM] Token save error:', err));
  };

  const registerServiceWorker = () => { if (window.nxEnablePush) window.nxEnablePush(); };   // the push subscription is made once, in app.js

  const initNotifBanner = () => {
    const bannerEl = $('msgr-notif-banner');
    if (!bannerEl || !('Notification' in window)) return;
    const dismissed = sessionStorage.getItem('nexusl:notif_banner_dismissed') === '1';
    if (Notification.permission === 'default' && !dismissed) {
      show(bannerEl, true);
    } else {
      show(bannerEl, false);
      if (Notification.permission === 'granted') registerServiceWorker();
    }

    $('notif-btn-allow')?.addEventListener('click', () => {
      Notification.requestPermission().then((permission) => {
        if (permission === 'granted') {
          show(bannerEl, false);
          flashErr('Desktop notifications enabled!');
          playChime();
          registerServiceWorker();
        } else if (permission === 'denied') {
          show(bannerEl, false);
          flashErr('Notifications blocked in browser settings.');
        }
      });
    });

    $('notif-btn-dismiss')?.addEventListener('click', () => {
      sessionStorage.setItem('nexusl:notif_banner_dismissed', '1');
      show(bannerEl, false);
    });
  };

  // ---------- Themes Catalog & Particle Engine ----------
  const THEMES = [
    { key: 'default', name: 'Default RMS Blue', primary: '#2563EB', bubble_me: '#2563EB', bubble_them: '#FFFFFF', emoji: '👍', desc: 'Standard RMS Blue with vector audit & chat pattern' },
    { key: 'christmas', name: 'Christmas Holidays', primary: '#DC2626', bubble_me: '#DC2626', bubble_them: '#DCFCE7', emoji: '❄️', desc: 'HD holiday wallpaper, falling snow & sleigh bells' },
    { key: 'sadako', name: 'Sadako Horror', primary: '#7F1D1D', bubble_me: '#7F1D1D', bubble_them: '#1E293B', emoji: '👻', desc: 'HD cursed well wallpaper, wall cracks & crawling crawler' },
    { key: 'holloween', name: 'Halloween Terror', primary: '#EA580C', bubble_me: '#EA580C', bubble_them: '#1C1917', emoji: '🎃', desc: 'HD haunted Halloween wallpaper with eerie amber glow' },
    { key: 'friday_13th', name: 'Friday the 13th', primary: '#991B1B', bubble_me: '#991B1B', bubble_them: '#18181B', emoji: '🔪', desc: 'HD Camp Crystal Lake wallpaper with blood-moon aura' },
    { key: 'bonifacio', name: 'Bonifacio Day', primary: '#B91C1C', bubble_me: '#B91C1C', bubble_them: '#1E293B', emoji: '🚩', desc: 'HD Katipunan memorial wallpaper with patriotic crimson' },
    { key: 'freddy_krueger', name: 'Nightmare Elm', primary: '#78350F', bubble_me: '#78350F', bubble_them: '#1C1917', emoji: '🩸', desc: 'HD nightmare wallpaper with claw scratch vibes' },
    { key: 'valentines', name: 'Sweet Valentines', primary: '#EC4899', bubble_me: '#EC4899', bubble_them: '#FCE7F3', emoji: '❤️', desc: 'Vector hearts pattern & rising 3D romance hearts burst' },
    { key: 'anime_cyberpunk', name: 'Anime Cyberpunk', primary: '#8B5CF6', bubble_me: '#8B5CF6', bubble_them: '#0F172A', emoji: '⚡', desc: 'Vector neon cyber grid, laser bursts & electric sparks' },
  ];

  const initCanvas = () => {
    if (!el.particleCanvas) return null;
    const c = el.particleCanvas;
    const rect = c.parentElement.getBoundingClientRect();
    c.width = rect.width;
    c.height = rect.height;
    return c.getContext('2d');
  };

  const applyTheme = (themeKey, animate = false) => {
    if (!themeKey) themeKey = 'default';
    currentThemeKey = themeKey;
    root.dataset.theme = themeKey;
    if (el.pane) el.pane.dataset.theme = themeKey;
    const chatEl = $('msgr-chat');
    if (chatEl) chatEl.dataset.theme = themeKey;
    document.body.dataset.theme = themeKey;

    const t = THEMES.find((x) => x.key === themeKey) || THEMES[0];
    if (el.infThemeName) el.infThemeName.textContent = t.name;
    updateSend();

    // Trigger visual/audio animations
    if (animate) {
      if (themeKey === 'christmas') {
        triggerSnowAnimation();
        triggerSleighBells();
      } else if (themeKey === 'sadako' || themeKey === 'friday_13th' || themeKey === 'holloween' || themeKey === 'freddy_krueger') {
        triggerSadakoAnimation();
        triggerHorrorSound();
      } else if (themeKey === 'valentines') {
        triggerHeartsAnimation();
        triggerLoveSound();
      } else if (themeKey === 'anime_cyberpunk') {
        triggerSparklesAnimation();
        triggerLaserSound();
      } else {
        stopParticles();
      }
    }
  };

  const stopParticles = () => {
    if (particleAnimId) {
      cancelAnimationFrame(particleAnimId);
      particleAnimId = null;
    }
    particles = [];
    if (el.particleCanvas) {
      const ctx = el.particleCanvas.getContext('2d');
      if (ctx) ctx.clearRect(0, 0, el.particleCanvas.width, el.particleCanvas.height);
    }
  };

  const triggerSnowAnimation = () => {
    stopParticles();
    const ctx = initCanvas();
    if (!ctx) return;
    const W = el.particleCanvas.width, H = el.particleCanvas.height;
    for (let i = 0; i < 70; i++) {
      particles.push({
        x: Math.random() * W,
        y: Math.random() * H,
        r: Math.random() * 3 + 1.5,
        d: Math.random() * 1.5 + 0.5,
        s: Math.random() * 1.2 - 0.6,
        alpha: Math.random() * 0.7 + 0.3,
      });
    }
    let frames = 0;
    const loop = () => {
      ctx.clearRect(0, 0, W, H);
      particles.forEach((p) => {
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(255, 255, 255, ${p.alpha})`;
        ctx.fill();
        p.y += p.d;
        p.x += Math.sin(p.y * 0.02) * p.s;
        if (p.y > H) { p.y = -5; p.x = Math.random() * W; }
      });
      frames++;
      if (frames < 360) {
        particleAnimId = requestAnimationFrame(loop);
      } else {
        stopParticles();
      }
    };
    particleAnimId = requestAnimationFrame(loop);
  };

  const triggerHeartsAnimation = () => {
    stopParticles();
    const ctx = initCanvas();
    if (!ctx) return;
    const W = el.particleCanvas.width, H = el.particleCanvas.height;
    for (let i = 0; i < 24; i++) {
      particles.push({
        x: W * 0.2 + Math.random() * W * 0.6,
        y: H + Math.random() * 80,
        size: Math.random() * 18 + 14,
        speed: Math.random() * 2 + 1.2,
        drift: Math.random() * 1.6 - 0.8,
        alpha: 1,
      });
    }
    let frames = 0;
    const loop = () => {
      ctx.clearRect(0, 0, W, H);
      particles.forEach((p) => {
        ctx.font = `${p.size}px serif`;
        ctx.globalAlpha = p.alpha;
        ctx.fillText('❤️', p.x, p.y);
        p.y -= p.speed;
        p.x += p.drift;
        p.alpha = Math.max(0, p.alpha - 0.005);
      });
      ctx.globalAlpha = 1;
      frames++;
      if (frames < 240) {
        particleAnimId = requestAnimationFrame(loop);
      } else {
        stopParticles();
      }
    };
    particleAnimId = requestAnimationFrame(loop);
  };

  const triggerSparklesAnimation = () => {
    stopParticles();
    const ctx = initCanvas();
    if (!ctx) return;
    const W = el.particleCanvas.width, H = el.particleCanvas.height;
    for (let i = 0; i < 50; i++) {
      particles.push({
        x: Math.random() * W,
        y: Math.random() * H,
        vx: (Math.random() - 0.5) * 4,
        vy: (Math.random() - 0.5) * 4,
        size: Math.random() * 3 + 2,
        life: 1,
        color: ['#8B5CF6', '#06B6D4', '#F43F5E', '#38BDF8'][Math.floor(Math.random() * 4)],
      });
    }
    let frames = 0;
    const loop = () => {
      ctx.clearRect(0, 0, W, H);
      particles.forEach((p) => {
        ctx.fillStyle = p.color;
        ctx.globalAlpha = p.life;
        ctx.fillRect(p.x, p.y, p.size, p.size);
        p.x += p.vx;
        p.y += p.vy;
        p.life -= 0.015;
      });
      ctx.globalAlpha = 1;
      frames++;
      if (frames < 120) {
        particleAnimId = requestAnimationFrame(loop);
      } else {
        stopParticles();
      }
    };
    particleAnimId = requestAnimationFrame(loop);
  };

  const triggerSadakoAnimation = () => {
    if (!el.cinemaOverlay) return;
    const ov = el.cinemaOverlay;
    ov.textContent = '';
    show(ov, true);

    // Screen Shake
    const chatPane = $('msgr-chat');
    chatPane?.classList.add('msgr-shake');
    setTimeout(() => chatPane?.classList.remove('msgr-shake'), 1200);

    // Well overlay and Wall Cracks
    const bg = mk('div', 'horror-well-bg active');
    const crack = mk('div', 'horror-crack active');
    crack.innerHTML = `
      <svg viewBox="0 0 500 500" style="width:100%;height:100%;stroke:#ef4444;stroke-width:1.8;fill:none;opacity:0.7">
        <path d="M 250,250 L 220,180 L 230,130 L 190,80 L 170,20 M 250,250 L 310,210 L 370,220 L 420,170 L 480,150 M 250,250 L 260,330 L 220,380 L 250,440 M 250,250 L 180,270 L 130,310 L 70,330"/>
      </svg>
    `;
    const crawler = mk('div', 'horror-crawler');
    crawler.innerHTML = `
      <svg viewBox="0 0 100 130" style="width:100%;height:100%">
        <path d="M 50,20 C 35,20 25,35 25,60 C 25,85 30,110 50,110 C 70,110 75,85 75,60 C 75,35 65,20 50,20 Z" fill="#050811"/>
        <path d="M 30,30 Q 50,5 70,30 Q 75,60 70,95 Q 50,110 30,95 Z" fill="#090d16"/>
        <path d="M 45,65 Q 40,85 35,110 M 55,65 Q 60,85 65,110" stroke="#f1f5f9" stroke-width="3" stroke-linecap="round"/>
        <circle cx="44" cy="52" r="3.5" fill="#ef4444"/>
        <circle cx="56" cy="52" r="3.5" fill="#ef4444"/>
      </svg>
    `;
    ov.append(bg, crack, crawler);
    requestAnimationFrame(() => crawler.classList.add('crawling'));

    setTimeout(() => {
      ov.textContent = '';
      show(ov, false);
    }, 3500);
  };

  const triggerSleighBells = () => {
    try {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return;
      const ctx = new AC();
      [0, 0.12, 0.24, 0.4].forEach((delay, idx) => {
        const osc = ctx.createOscillator();
        const g = ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(2489 + idx * 200, ctx.currentTime + delay);
        g.gain.setValueAtTime(0.08, ctx.currentTime + delay);
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.18);
        osc.connect(g);
        g.connect(ctx.destination);
        osc.start(ctx.currentTime + delay);
        osc.stop(ctx.currentTime + delay + 0.19);
      });
    } catch (_) {}
  };

  const triggerHorrorSound = () => {
    try {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return;
      const ctx = new AC();
      const osc = ctx.createOscillator();
      const g = ctx.createGain();
      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(220, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(55, ctx.currentTime + 1.2);
      g.gain.setValueAtTime(0.2, ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.3);
      osc.connect(g);
      g.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 1.35);
    } catch (_) {}
  };

  const triggerLoveSound = () => {
    try {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return;
      const ctx = new AC();
      [523.25, 659.25, 783.99, 1046.5].forEach((f, i) => {
        const osc = ctx.createOscillator();
        const g = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(f, ctx.currentTime + i * 0.08);
        g.gain.setValueAtTime(0.12, ctx.currentTime + i * 0.08);
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.08 + 0.35);
        osc.connect(g);
        g.connect(ctx.destination);
        osc.start(ctx.currentTime + i * 0.08);
        osc.stop(ctx.currentTime + i * 0.08 + 0.36);
      });
    } catch (_) {}
  };

  const triggerLaserSound = () => {
    try {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return;
      const ctx = new AC();
      const osc = ctx.createOscillator();
      const g = ctx.createGain();
      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(880, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(110, ctx.currentTime + 0.25);
      g.gain.setValueAtTime(0.18, ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.26);
      osc.connect(g);
      g.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.27);
    } catch (_) {}
  };

  const checkTriggerEmoji = (text, msgId = 0) => {
    if (!text) return;
    if (msgId > 0) {
      if (triggeredMsgIds.has(msgId)) return;
      triggeredMsgIds.add(msgId);
      if (key) {
        post('triggered/' + enc(key) + '/' + msgId, {}).catch(() => {});
      }
    }
    if (text.includes('❄️') || text.includes('❄') || text.includes('⛄') || text.includes('🎅') || text.includes('🎄')) { triggerSnowAnimation(); triggerSleighBells(); }
    else if (text.includes('👻') || text.includes('🎃') || text.includes('🔪') || text.includes('🩸') || text.includes('💀')) { triggerSadakoAnimation(); triggerHorrorSound(); }
    else if (text.includes('❤️') || text.includes('💖') || text.includes('💕') || text.includes('💘')) { triggerHeartsAnimation(); triggerLoveSound(); }
    else if (text.includes('⚡') || text.includes('⭐') || text.includes('🎉')) { triggerSparklesAnimation(); triggerLaserSound(); }
  };

  const updateDisappearingUI = (status) => {
    if (!el.disappearingBar) return;
    const labels = {
      '1m': '⏱ Disappearing messages: 1 minute. New messages expire automatically.',
      '1h': '⏱ Disappearing messages: 1 hour. New messages expire automatically.',
      '24h': '⏱ Disappearing messages: 24 hours. New messages expire automatically.',
      '7d': '⏱ Disappearing messages: 7 days. New messages expire automatically.',
      'on': '⏱ Disappearing messages are turned ON. Messages disappear automatically.',
      'pending': '⏱ Disappearing messages requested. Waiting for peer approval.'
    };
    if (labels[status]) {
      show(el.disappearingBar, true);
      if (el.disappearingTxt) el.disappearingTxt.textContent = labels[status];
    } else {
      show(el.disappearingBar, false);
    }
    if (el.infDis) {
      el.infDis.textContent = { '1m': '1 minute', '1h': '1 hour', '24h': '24 hours', '7d': '7 days', on: 'On', pending: 'Waiting for approval', off: 'Off' }[status] || 'Off';
    }
  };

  const fmtRemaining = (expStr) => {
    const ms = +parse(expStr) - Date.now();
    if (ms <= 0) return '0s';
    const s = Math.ceil(ms / 1000);
    if (s < 60) return `${s}s`;
    const m = Math.floor(s / 60), rs = s % 60;
    if (m < 60) return rs > 0 && m < 10 ? `${m}m ${rs}s` : `${m}m`;
    const h = Math.floor(m / 60), rm = m % 60;
    if (h < 24) return rm > 0 ? `${h}h ${rm}m` : `${h}h`;
    const d = Math.floor(h / 24), rh = h % 24;
    return rh > 0 ? `${d}d ${rh}h` : `${d}d`;
  };

  const tickExpiringMessages = () => {
    if (!el.msgs) return;
    let removedAny = false;
    el.msgs.querySelectorAll('.m-exp[data-exp]').forEach((badge) => {
      const exp = badge.dataset.exp;
      const ms = +parse(exp) - Date.now();
      if (ms <= 0) {
        const b = badge.closest('.m');
        if (b) {
          const id = +b.dataset.id;
          msgData.delete(id);
          (b.closest('.mq') || b).remove();
          removedAny = true;
        }
      } else {
        badge.textContent = '⏱ ' + fmtRemaining(exp);
      }
    });
    if (removedAny) loadList();
  };
  setInterval(tickExpiringMessages, 1000);

  const updateApprovalBanner = (group) => {
    if (!el.approvalBanner) return;
    const isPending = group && (group.pendingApproval || String(group.policies || '').includes('PendingAdminApproval'));
    if (!isPending) {
      show(el.approvalBanner, false);
      if (el.input) el.input.disabled = false;
      const sendBtn = $('msgr-send');
      if (sendBtn) sendBtn.disabled = false;
      return;
    }
    show(el.approvalBanner, true);
    if (ADMIN) {
      show(el.abActions, true);
      el.abDesc.textContent = `This group has ${group.memberCount || '> 50'} members and is awaiting your approval as an Administrator.`;
      if (el.input) el.input.disabled = false;
    } else {
      show(el.abActions, false);
      el.abDesc.textContent = `This group has ${group.memberCount || '> 50'} members and is pending administrator authorization before messaging can begin.`;
      if (el.input) el.input.disabled = true;
      const sendBtn = $('msgr-send');
      if (sendBtn) sendBtn.disabled = true;
    }
  };

  const api = (path, opts) => fetch(`${BASE}/${path}`, Object.assign({ credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }, opts))
    .then((r) => {
      if (r.status === 401) { location.href = BASE.replace(/inbox$/, 'login'); throw new Error('auth'); }
      return r.json().then((j) => { if (csrfMeta && j.csrf) csrfMeta.content = j.csrf; if (!r.ok) throw new Error(j.error || 'Request failed'); return j; });
    });
  const post = (path, body) => api(path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: JSON.stringify(body) });
  const postForm = (path, fd) => api(path, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: fd });

  // ---------- generic dialog ----------
  const closeDlg = () => { show(el.dlg, false); el.dlgCard.textContent = ''; el.dlgCard.classList.remove('wide'); };
  const openDlg = (title, ...body) => {
    el.dlgCard.textContent = '';
    const h = mk('div', 'dlg-head'); h.append(mk('b', '', title));
    const x = mk('button', 'msgr-icon', '✕'); x.type = 'button'; x.setAttribute('aria-label', 'Close'); x.addEventListener('click', closeDlg); h.append(x);
    el.dlgCard.append(h, ...body); show(el.dlg, true);
  };
  el.dlg.addEventListener('mousedown', (e) => { if (e.target === el.dlg) closeDlg(); });

  // ---------- emoji picker (search + category tabs + grid, as in the app) ----------
  const buildPicker = (onPick) => {
    const box = mk('div', 'pk');
    const sbox = mk('div', 'pk-search'); const si = mk('input'); si.type = 'search'; si.placeholder = 'Search emoji'; si.maxLength = 20; sbox.append(si);
    const tabs = mk('div', 'pk-tabs'); const title = mk('div', 'pk-title'); const grid = mk('div', 'pk-grid');
    const fill = (list) => { grid.textContent = ''; list.forEach((e) => { const b = mk('button', '', e); b.type = 'button'; b.addEventListener('click', () => onPick(e)); grid.append(b); }); };
    const cat = (i) => { [...tabs.children].forEach((t, k) => t.classList.toggle('on', k === i)); title.textContent = E.cats[i].title; fill(E.cats[i].list); grid.scrollTop = 0; };
    E.cats.forEach((c, i) => { const t = mk('button', '', c.icon); t.type = 'button'; t.title = c.title; t.addEventListener('click', () => { si.value = ''; cat(i); }); tabs.append(t); });
    si.addEventListener('input', () => {
      const q = si.value.trim();
      if (!q) { cat(0); return; }
      const hit = E.search ? E.search(q) : E.all.filter((e) => e.includes(q));
      title.textContent = hit.length ? `Search results (${hit.length})` : 'No match - showing all'; fill(hit.length ? hit : E.all);
    });
    box.append(sbox, tabs, title, grid); cat(0);
    return box;
  };

  // ---------- conversation list ----------
  const row = (p, conv) => {
    const b = mk('button', 'row' + (p.key === key ? ' active' : '') + (conv && p.unread > 0 ? ' unread' : '')); b.type = 'button';
    const a = mk('span', 'av'); avatar(a, p);
    const m = mk('span', 'meta'); const n = mk('b', '', p.name);
    if (p.isGroup) {
      n.append(mk('i', 'src ' + p.src, p.src === 'rg' ? 'RMS Group' : 'Group'));
    } else if (ADMIN) {
      n.append(mk('i', 'src ' + p.src, SYS[p.src]));
    }
    const s = mk('small');
    if (p.isGroup && p.pendingApproval) {
      s.append(mk('span', 'grp-pending-chip', '⚠️ Pending Approval'), document.createTextNode(' '));
    }
    if (conv && p.muted) {
      s.append(mk('span', 'muted-chip', '🔕 '), document.createTextNode(''));
    }
    s.append(document.createTextNode(conv ? ((p.mine ? 'You: ' : '') + p.last) : (p.username ? '@' + p.username : (p.dept || ''))));
    m.append(n, s); b.append(a, m);
    if (conv) {
      b.append(mk('span', 'when', fmtShort(p.at)));
      if (p.unread > 0) b.append(mk('span', 'badge-dot' + (p.muted ? ' muted' : ''), p.unread));
    }
    b.addEventListener('click', () => open(p));
    const li = document.createElement('li'); li.append(b); return li;
  };
  const renderList = () => {
    el.items.textContent = '';
    const searching = el.q.value.trim() !== '';
    if (people.length) {
      el.items.append(mk('li', 'sec', 'People'));
      people.forEach((p) => el.items.append(row(p, false)));
    } else {
      const list = convCache.filter((c) => {
        if (convFilter === 'unread') return (c.unread || 0) > 0;
        if (convFilter === 'groups') return !!(c.isGroup || c.src === 'g' || c.src === 'rg');
        return true;
      });
      list.forEach((c) => el.items.append(row(c, true)));
      el.none.textContent = searching ? 'No people found.' : (convFilter === 'unread' ? 'No unread conversations.' : (convFilter === 'groups' ? 'No group chats yet.' : 'No conversations yet. Search for a person to start one.'));
      show(el.none, list.length === 0);
      return;
    }
    el.none.textContent = 'No people found.';
    show(el.none, false);
  };
  el.filters?.addEventListener('click', (e) => {
    const b = e.target.closest('.msgr-filter'); if (!b) return;
    convFilter = b.dataset.filter || 'all';
    el.filters.querySelectorAll('.msgr-filter').forEach((btn) => {
      const on = btn === b;
      btn.classList.toggle('active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    renderList();
  });
  const updateTabTitle = () => {
    const totalUnread = convCache.reduce((acc, c) => acc + (c.unread || 0), 0);
    const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
    document.title = totalUnread > 0 ? `(${totalUnread}) ${baseTitle}` : baseTitle;
  };
  let autoOpenedChat = false;
  const loadList = () => api(`conversations?q=${enc(el.q.value.trim())}`).then((d) => {
    convCache = d.conversations; people = el.q.value.trim() ? (d.people || []) : [];
    banner((d.warnings || []).join(' ')); renderList(); updateTabTitle();
    if (!autoOpenedChat && key === null) {
      const wantKey = new URLSearchParams(location.search).get('chat');
      if (wantKey) {
        autoOpenedChat = true;
        const found = convCache.find((c) => c.key === wantKey) || (wantKey === 'n:interlink_etrf' ? { key: 'n:interlink_etrf', id: 'interlink_etrf', name: 'InterLink 2.0 — Application No.', username: 'interlink_etrf', src: 'n', online: true } : null);
        if (found) open(found);
      }
    }
  }).catch((e) => { if (e.message !== 'auth') banner(e.message); });
  window.addEventListener('focus', () => { updateTabTitle(); });
  let qt; el.q.addEventListener('input', () => { clearTimeout(qt); qt = setTimeout(loadList, 250); });

  // ---------- message rendering ----------
  const resetThread = () => { el.msgs.textContent = ''; msgData.clear(); lastId = 0; oldestId = 0; hasMore = false; loadingOlder = false; pinIdx = 0; lastDay = ''; lastSender = null; lastTime = 0; seenEl = null; show(el.pinBar, false); };
  const reactChips = (m) => {
    const wrap = mk('div', 'reacts');
    const by = new Map();
    (m.reactions || []).forEach((r) => { const g = by.get(r.e) || { n: 0, mine: false, who: [] }; g.n++; g.mine = g.mine || r.u === me; g.who.push(r.n || r.u); by.set(r.e, g); });
    by.forEach((g, e) => {
      const c = mk('button', 'chip' + (g.mine ? ' mine' : ''), g.n > 1 ? `${e} ${g.n}` : e); c.type = 'button'; c.title = g.who.join(', ');
      c.addEventListener('click', () => react(m.id, e)); wrap.append(c);
    });
    return wrap;
  };
  const core = (m) => {
    const mine = m.from === me;
    const b = mk('div', 'm ' + (mine ? 'me' : 'them') + (m.unsent ? ' unsent' : '')); b.dataset.id = m.id; b.title = fmtTime(parse(m.at));
    if (peer && (peer.isGroup || peer.src === 'g' || peer.src === 'rg') && !mine) {
      const snd = mk('div', 'm-group-sender');
      const a = mk('span', 'av xs');
      avatar(a, { id: m.from, name: m.fromName || ('User ' + m.from), avatar: m.fromAvatar, online: false });
      snd.append(a, mk('span', '', m.fromName || ('User ' + m.from)));
      b.append(snd);
    }
    if (m.unsent) {
      b.append(mk('span', 't unsent-txt', mine ? 'You unsent a message' : ((m.fromName ? m.fromName + ' ' : '') + 'unsent a message')));
      const hv = mk('div', 'hv'); hv.setAttribute('role', 'group');
      const mb = mk('button', '', '⋯'); mb.type = 'button'; mb.title = 'More'; mb.setAttribute('aria-label', 'More options');
      mb.addEventListener('click', (e) => { e.stopPropagation(); const r = mb.getBoundingClientRect(); openCtx(msgData.get(m.id) || m, r.left, r.bottom + 4); });
      hv.append(mb); b.append(hv);
      return b;
    }
    if (m.image) {
      const a = mk('a', 'pimg'); a.href = m.image; a.setAttribute('aria-label', 'View image');
      const img = mk('img', 'pic'); img.src = m.image; img.alt = 'Image'; img.loading = 'lazy'; a.append(img); b.append(a); b.classList.add('has-pic');
    }
    if (m.file) {
      const a = mk('a', 'filecard'); a.href = m.file.url; a.rel = 'noopener noreferrer';
      a.append(mk('span', 'fi', '📄'), (() => { const d = mk('span', 'fd'); d.append(mk('b', '', m.file.name), mk('small', '', fmtSize(m.file.size))); return d; })());
      b.append(a); b.classList.add('has-file');
    }
    if (m.text && m.text !== '[Image Attachment]') b.append(mk('span', 't', m.text));
    if (m.expiresAt && !m.unsent) {
      const expBadge = mk('span', 'm-exp', '⏱ ' + fmtRemaining(m.expiresAt));
      expBadge.dataset.exp = m.expiresAt;
      expBadge.title = 'Disappears at ' + fmtTime(parse(m.expiresAt));
      b.append(expBadge);
    }
    // hover actions (reply / more), like Messenger
    const hv = mk('div', 'hv'); hv.setAttribute('role', 'group');
    const rb = mk('button', '', '↩'); rb.type = 'button'; rb.title = 'Reply'; rb.setAttribute('aria-label', 'Reply');
    rb.addEventListener('click', (e) => { e.stopPropagation(); setReply(msgData.get(m.id) || m); });
    const mb = mk('button', '', '⋯'); mb.type = 'button'; mb.title = 'More'; mb.setAttribute('aria-label', 'More options');
    mb.addEventListener('click', (e) => { e.stopPropagation(); const r = mb.getBoundingClientRect(); openCtx(msgData.get(m.id) || m, r.left, r.bottom + 4); });
    hv.append(rb, mb); b.append(hv);
    return b;
  };
  const inner = (n) => (n && n.classList.contains('m') ? n : (n ? n.querySelector('.m') : null));
  const peerLabel = () => (nicks.peer || (peer && peer.name) || 'them');
  const replyLabel = (m) => {
    const mine = m.from === me;
    const qSender = (m.quote && (m.quote.fromName || (m.quote.from === me ? 'You' : peerLabel()))) || 'User';
    const qMine = m.quote && (m.quote.from === me || qSender === 'You' || qSender === 'yourself');
    return mine
      ? (qMine ? 'You replied to yourself' : `You replied to ${qSender}`)
      : (qMine ? `${peerLabel()} replied to you` : `${peerLabel()} replied to ${qSender}`);
  };
  const quoteText = (q) => (q.gone ? 'Original message is not available' : (q.text || (q.image ? '📷 Photo' : (q.file ? '📄 ' + q.file : ''))));
  /** The message element; replies are wrapped with their "replied to" label and quoted message above the bubble. */
  const bubble = (m) => {
    const b = core(m);
    if (!m.quote) return b;
    const w = mk('div', 'mq ' + (m.from === me ? 'me' : 'them'));
    const q = m.quote, qh = mk('div', 'qh', '↩ ' + replyLabel(m));
    const qt = mk('button', 'qt' + (q.gone ? ' gone' : '')); qt.type = 'button'; qt.textContent = quoteText(q);
    if (!q.gone) { qt.title = 'Go to message'; qt.addEventListener('click', () => jumpTo(q.id)); } else qt.disabled = true;
    w.append(qh, qt, b);
    return w;
  };
  const jumpTo = (id) => {
    const t = el.msgs.querySelector(`.m[data-id="${id}"]`);
    if (!t) { flashErr('That message is older than the history loaded here.'); return; }
    t.scrollIntoView({ block: 'center', behavior: 'smooth' }); t.classList.remove('flash'); void t.offsetWidth; t.classList.add('flash');
    setTimeout(() => t.classList.remove('flash'), 1900);
  };
  // ---- reply composer state
  const setReply = (m) => {
    clearReply();
    replyTo = { id: m.id, from: m.from, text: m.text || '', image: !!m.image, file: m.file ? m.file.name : null };
    const bar = $('msgr-reply'); bar.textContent = '';
    const who = m.from === me ? 'yourself' : peerLabel();
    const t = mk('div', 'rb-t'); t.append(mk('b', '', `Replying to ${who}`), mk('span', '', quoteText(replyTo)));
    const x = mk('button', 'msgr-icon', '✕'); x.type = 'button'; x.setAttribute('aria-label', 'Cancel reply'); x.addEventListener('click', clearReply);
    bar.append(t, x); show(bar, true);
    el.msgs.querySelector(`.m[data-id="${m.id}"]`)?.classList.add('replying');
    el.input.focus();
  };
  const clearReply = () => {
    if (replyTo) el.msgs.querySelector(`.m[data-id="${replyTo.id}"]`)?.classList.remove('replying');
    replyTo = null; const bar = $('msgr-reply'); bar.textContent = ''; show(bar, false);
  };
  const decorate = (b, m) => { b.querySelector('.reacts')?.remove(); if ((m.reactions || []).length) { b.append(reactChips(m)); b.classList.add('has-react'); } else b.classList.remove('has-react'); };

  const addMsg = (m) => {
    if (msgData.has(m.id)) return;
    msgData.set(m.id, m);
    oldestId = oldestId === 0 ? m.id : Math.min(oldestId, m.id);
    const d = parse(m.at), day = d.toDateString();
    if (day !== lastDay) { el.msgs.append(mk('div', 'stamp', d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' }) + ' · ' + fmtTime(d))); lastDay = day; lastSender = null; }
    else if (d - lastTime > 15 * 60000) { el.msgs.append(mk('div', 'stamp', fmtTime(d))); lastSender = null; }
    if (m.kind === 'call') { el.msgs.append(mk('div', 'stamp call', '📞 ' + m.text)); lastSender = null; lastTime = +d; lastId = Math.max(lastId, m.id); return; }
    if (m.kind === 'sys') { el.msgs.append(mk('div', 'stamp sys', m.text)); lastSender = null; lastTime = +d; lastId = Math.max(lastId, m.id); return; }
    const w = bubble(m), b = inner(w); decorate(b, m);
    const prev = inner([...el.msgs.children].reverse().find((n) => !n.classList.contains('seen')));
    if (!m.quote && lastSender === m.from && prev) { b.classList.add('cont'); prev.classList.add('more'); }
    else if (prev) w.classList.add('gap');
    el.msgs.append(w); lastSender = m.quote ? null : m.from; lastTime = +d; lastId = Math.max(lastId, m.id);
    if (replyTo && replyTo.id === m.id) b.classList.add('replying');
  };
  const patchMsg = (m) => {
    const old = msgData.get(m.id), b = el.msgs.querySelector(`.m[data-id="${m.id}"]`);
    if (!old || !b) return;
    if (JSON.stringify(old.reactions) === JSON.stringify(m.reactions) && old.text === m.text && !!old.unsent === !!m.unsent) return;
    swap(b, m);
  };
  const swap = (b, m) => {
    const oldOuter = b.closest('.mq') || b;
    const w = bubble(m), nb = inner(w);
    [...b.classList].filter((c) => ['cont', 'more', 'replying'].includes(c)).forEach((c) => nb.classList.add(c));
    if (oldOuter.classList.contains('gap')) w.classList.add('gap');
    decorate(nb, m); oldOuter.replaceWith(w); msgData.set(m.id, m);
  };
  const markSeen = (id) => {
    seenEl?.remove(); seenEl = null;
    if (!id) return;
    const mineAll = [...el.msgs.querySelectorAll('.m.me')], last = mineAll[mineAll.length - 1];
    if (last && +last.dataset.id <= id) { seenEl = mk('div', 'seen', 'Seen'); el.msgs.append(seenEl); }
  };
  const nearBottom = () => el.msgs.scrollHeight - el.msgs.scrollTop - el.msgs.clientHeight < 80;
  let stickUntil = 0;
  const scrollToBottom = (keepSticky = false) => {
    if (keepSticky) stickUntil = Date.now() + 1200;
    el.msgs.scrollTop = el.msgs.scrollHeight;
    requestAnimationFrame(() => { el.msgs.scrollTop = el.msgs.scrollHeight; });
  };
  el.msgs.addEventListener('load', () => {
    if (Date.now() < stickUntil || nearBottom()) el.msgs.scrollTop = el.msgs.scrollHeight;
  }, true);
  el.msgs.addEventListener('wheel', () => { stickUntil = 0; }, { passive: true });
  el.msgs.addEventListener('touchstart', () => { stickUntil = 0; }, { passive: true });

  // ---------- thread loading ----------
  const headerFor = () => {
    if (peer && (peer.isGroup || peer.src === 'g' || peer.src === 'rg')) {
      el.name.textContent = peer.name;
      el.sub.textContent = `Group · ${peer.memberCount || 0} members` + (ADMIN ? ` · ${peer.src === 'rg' ? 'RMS' : 'NEXUS-L'}` : '');
      if (el.infMembersBtn) {
        show(el.infMembersBtn, true);
        if (el.infMembersCount) el.infMembersCount.textContent = `${peer.memberCount || 0} members`;
      }
      return;
    }
    if (el.infMembersBtn) show(el.infMembersBtn, false);
    const nk = nicks.peer;
    el.name.textContent = nk || peer.name;
    el.sub.textContent = (nk ? peer.name + ' · ' : (peer.username ? '@' + peer.username + ' · ' : '')) + (ADMIN ? SYS[peer.src] : '');
    el.sub.textContent = el.sub.textContent.replace(/ · $/, '');
  };
  const ensureLoadOlderBtn = () => {
    $('msgr-load-older')?.remove();
    if (!hasMore || !oldestId) return;
    const btn = mk('button', 'msgr-load-older', 'Load older messages');
    btn.id = 'msgr-load-older'; btn.type = 'button';
    btn.addEventListener('click', loadOlder);
    el.msgs.prepend(btn);
  };
  const loadOlder = () => {
    if (key === null || loadingOlder || !hasMore || !oldestId) return;
    const k = key; loadingOlder = true;
    const btn = $('msgr-load-older'); if (btn) { btn.disabled = true; btn.textContent = 'Loading…'; }
    api(`thread/${enc(k)}?before=${oldestId}`).then((d) => {
      if (k !== key) return;
      hasMore = !!d.hasMore;
      const oldH = el.msgs.scrollHeight, oldTop = el.msgs.scrollTop;
      const frag = document.createDocumentFragment();
      let pDay = '', pSender = null, pTime = 0, prevNode = null;
      (d.messages || []).forEach((m) => {
        if (msgData.has(m.id)) return;
        msgData.set(m.id, m);
        oldestId = oldestId === 0 ? m.id : Math.min(oldestId, m.id);
        const dt = parse(m.at), day = dt.toDateString();
        if (day !== pDay) { frag.append(mk('div', 'stamp', dt.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' }) + ' · ' + fmtTime(dt))); pDay = day; pSender = null; prevNode = null; }
        else if (dt - pTime > 15 * 60000) { frag.append(mk('div', 'stamp', fmtTime(dt))); pSender = null; prevNode = null; }
        if (m.kind === 'call') { frag.append(mk('div', 'stamp call', '📞 ' + m.text)); pSender = null; pTime = +dt; prevNode = null; return; }
        if (m.kind === 'sys') { frag.append(mk('div', 'stamp sys', m.text)); pSender = null; pTime = +dt; prevNode = null; return; }
        const w = bubble(m), b = inner(w); decorate(b, m);
        if (!m.quote && pSender === m.from && prevNode) { b.classList.add('cont'); prevNode.classList.add('more'); }
        else if (prevNode) w.classList.add('gap');
        frag.append(w); prevNode = b; pSender = m.quote ? null : m.from; pTime = +dt;
      });
      $('msgr-load-older')?.remove();
      el.msgs.prepend(frag);
      ensureLoadOlderBtn();
      el.msgs.scrollTop = oldTop + (el.msgs.scrollHeight - oldH);
    }).catch((e) => { if (e.message !== 'auth') flashErr(e.message); }).finally(() => { loadingOlder = false; });
  };
  const pull = (first) => {
    if (key === null || busy) return Promise.resolve();
    const k = key; busy = true; tick++;
    const full = first || tick % 3 === 0;                 // every ~9s also refresh recent messages (reactions/edits by others)
    return api(`thread/${enc(k)}?after=${full && !first ? 0 : lastId}`).then((d) => {
      if (k !== key) return;
      me = String(d.me); nicks = d.nicknames || nicks;
      if (d.peer && peer && !peer.isGroup && !!d.peer.online !== !!peer.online) {
        peer.online = !!d.peer.online;
        avatar(el.av, peer);
        if (!inf.box.classList.contains('hidden')) {
          avatar(inf.av, peer);
          if (peer.src === 'r' || peer.src === 'n') {
            inf.status.textContent = peer.online ? '● Online' : '● Offline';
            inf.status.classList.toggle('on', !!peer.online);
          }
        }
      }
      headerFor();
      if (first) hasMore = !!d.hasMore;
      if (d.group) {
        const prevAv = peer && peer.avatar;
        peer = Object.assign(peer, d.group);
        if (peer.avatar !== prevAv) {
          avatar(el.av, peer);
          if (!inf.box.classList.contains('hidden')) avatar(inf.av, peer);
        }
        if (d.group.theme && d.group.theme !== currentThemeKey) {
          applyTheme(d.group.theme, false);
        }
        updateDisappearingUI(d.group.disappearing || 'off');
        updateApprovalBanner(d.group);
      } else if (d.disappearing !== undefined) {
        if (peer) peer.disappearing = d.disappearing;
        if (settings) settings.disappearing = d.disappearing;
        updateDisappearingUI(d.disappearing || 'off');
      }
      const stick = first || nearBottom(); seenEl?.remove(); seenEl = null;
      let added = 0;
      d.messages.forEach((m) => {
        if (m.is_triggered || first) {
          triggeredMsgIds.add(m.id);
        }
        if (msgData.has(m.id)) {
          patchMsg(m);
        } else {
          addMsg(m);
          added++;
          if (!first && !m.is_triggered && !triggeredMsgIds.has(m.id)) {
            checkTriggerEmoji(m.text, m.id);
            if (m.from !== me) notifyDesktop(m);
          }
        }
      });
      if (first) ensureLoadOlderBtn();
      markSeen(+d.seen || 0);
      if (stick && (added || first)) scrollToBottom(true);
      if (added && !first) loadList();
    }).catch((e) => { if (e.message !== 'auth') flashErr(e.message); }).finally(() => { busy = false; });
  };

  let onPeerChange = () => {};
  const open = (p) => {
    key = p.key; peer = p; replyTo = null; show($('msgr-reply'), false); resetThread(); el.q.value = ''; people = []; nicks = { me: '', peer: '' }; pending = []; renderPending();
    show(el.blank, false); show(el.pane, true); root.classList.add('open'); show(el.err, false);
    avatar(el.av, p); headerFor(); renderList(); closePops(); updateSend();
    applyTheme(p.theme || 'default', false);
    updateDisappearingUI(p.disappearing || 'off');
    updateApprovalBanner(p);
    onPeerChange();
    pull(true).then(() => { loadList(); el.input.focus(); });
  };
  el.back.addEventListener('click', () => root.classList.remove('open'));

  // ---------- sending ----------
  const updateSend = () => {
    const hasText = el.input.value.trim() !== '', hasFiles = pending.length > 0;
    const themeDefault = (THEMES.find((t) => t.key === currentThemeKey) || {}).emoji || '👍';
    const q = settings && settings.emoji ? settings.emoji : themeDefault;
    const showQuick = !hasText && !hasFiles;
    el.sendQuick.textContent = q; show(el.sendQuick, showQuick); show(el.sendIco, !showQuick);
    $('msgr-send').setAttribute('aria-label', showQuick ? 'Send ' + q : 'Send');
  };
  const sendText = (text) => {
    checkTriggerEmoji(text);
    const btn = $('msgr-send'); btn.disabled = true; flashErr('');
    return post(`send/${enc(key)}`, { body: text, reply_to: replyTo ? replyTo.id : 0 }).then((j) => {
      clearReply();
      if (j.message) {
        if (j.message.id) {
          triggeredMsgIds.add(j.message.id);
          post('triggered/' + enc(key) + '/' + j.message.id, {}).catch(() => {});
        }
        addMsg(j.message);
      }
      seenEl?.remove(); seenEl = null; scrollToBottom(true); loadList();
    }).catch((e) => flashErr(e.message)).finally(() => { btn.disabled = false; el.input.focus(); });
  };
  const sendFiles = (caption) => {
    const fd = new FormData(); pending.forEach((f) => fd.append('files[]', f)); fd.append('body', caption); if (replyTo) fd.append('reply_to', replyTo.id);
    const btn = $('msgr-send'); btn.disabled = true; flashErr('');
    return postForm(`upload/${enc(key)}`, fd).then((j) => {
      clearReply(); (j.messages || []).forEach((m) => m && addMsg(m)); pending = []; renderPending(); seenEl?.remove(); seenEl = null;
      scrollToBottom(true); loadList();
    }).catch((e) => flashErr(e.message)).finally(() => { btn.disabled = false; el.input.focus(); updateSend(); });
  };
  const submit = () => {
    if (key === null) return;
    const text = el.input.value.trim();
    if (!text && !pending.length) {
      const themeDefault = (THEMES.find((t) => t.key === currentThemeKey) || {}).emoji || '👍';
      sendText(settings && settings.emoji ? settings.emoji : themeDefault);
      return;
    }
    el.input.value = ''; el.input.style.height = 'auto'; updateSend();
    if (pending.length) sendFiles(text); else sendText(text);
  };
  el.form.addEventListener('submit', (e) => { e.preventDefault(); submit(); });
  el.input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(); } else if (e.key === 'Escape' && replyTo) { e.stopPropagation(); clearReply(); } });
  el.input.addEventListener('input', () => { el.input.style.height = 'auto'; el.input.style.height = Math.min(el.input.scrollHeight, 120) + 'px'; updateSend(); });

  // ---------- attachments ----------
  const renderPending = () => {
    el.atts.textContent = ''; show(el.atts, pending.length > 0);
    pending.forEach((f, i) => {
      const c = mk('div', 'att');
      if (f.type.startsWith('image/')) { const im = mk('img'); im.src = URL.createObjectURL(f); im.alt = f.name; c.append(im); }
      else { c.classList.add('doc'); c.append(mk('span', '', '📄 ' + f.name)); }
      const x = mk('button', 'att-x', '✕'); x.type = 'button'; x.setAttribute('aria-label', 'Remove'); x.addEventListener('click', () => { pending.splice(i, 1); renderPending(); updateSend(); });
      c.append(x); el.atts.append(c);
    });
  };
  const addFiles = (list, imagesOnly) => {
    const rms = peer && peer.src === 'r';
    [...list].forEach((f) => {
      if (pending.length >= 5) { flashErr('You can attach up to 5 files at a time.'); return; }
      if (f.size > 10 * 1048576) { flashErr(`${f.name} is larger than 10 MB.`); return; }
      if ((rms || imagesOnly) && !f.type.startsWith('image/')) { flashErr(rms ? 'Only images can be sent to RMS chats.' : 'Please choose an image.'); return; }
      pending.push(f);
    });
    renderPending(); updateSend();
  };
  el.imgIn.addEventListener('change', () => { addFiles(el.imgIn.files, true); el.imgIn.value = ''; });
  el.anyIn.addEventListener('change', () => { addFiles(el.anyIn.files, false); el.anyIn.value = ''; });
  el.msgs.addEventListener('dragover', (e) => e.preventDefault());
  el.msgs.addEventListener('drop', (e) => { e.preventDefault(); if (key !== null) addFiles(e.dataTransfer.files, false); });
  el.input.addEventListener('paste', (e) => { const fs = [...(e.clipboardData?.files || [])].filter((f) => f.type.startsWith('image/')); if (fs.length) { e.preventDefault(); addFiles(fs, true); } });

  // ---------- popups: attach menu, composer emoji, message menu ----------
  const closePops = () => { [el.ctx, el.attMenu, el.emojiPop].forEach((n) => show(n, false)); };
  const placeAt = (node, x, y) => {
    show(node, true); const r = root.getBoundingClientRect(), w = node.offsetWidth, h = node.offsetHeight;
    node.style.left = Math.max(8, Math.min(x - r.left, r.width - w - 8)) + 'px';
    node.style.top = Math.max(8, Math.min(y - r.top, r.height - h - 8)) + 'px';
  };
  document.addEventListener('mousedown', (e) => { if (![el.ctx, el.attMenu, el.emojiPop, $('msgr-attach'), $('msgr-emoji')].some((n) => n.contains(e.target))) closePops(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closePops(); closeDlg(); } });
  el.msgs.addEventListener('scroll', closePops, { passive: true });

  $('msgr-attach').addEventListener('click', (e) => {
    const was = !el.attMenu.classList.contains('hidden'); closePops(); if (was) return;
    const r = e.currentTarget.getBoundingClientRect(); show(el.attMenu, true);
    placeAt(el.attMenu, r.left, r.top - el.attMenu.offsetHeight - 6);
    $('att-file-btn').disabled = peer && peer.src === 'r';
    $('att-file-btn').title = $('att-file-btn').disabled ? 'RMS chats support images only' : '';
  });
  el.attMenu.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-att]'); if (!b || b.disabled) return;
    closePops(); (b.dataset.att === 'image' ? el.imgIn : el.anyIn).click();
  });
  const pop = buildPicker((e) => { const i = el.input, s = i.selectionStart ?? i.value.length; i.value = i.value.slice(0, s) + e + i.value.slice(i.selectionEnd ?? s); i.focus(); i.selectionStart = i.selectionEnd = s + e.length; updateSend(); });
  el.emojiPop.append(pop);
  $('msgr-emoji').addEventListener('click', (e) => {
    const was = !el.emojiPop.classList.contains('hidden'); closePops(); if (was) return;
    const r = e.currentTarget.getBoundingClientRect(); show(el.emojiPop, true); placeAt(el.emojiPop, r.left, r.top - el.emojiPop.offsetHeight - 6);
  });

  // ---------- message actions ----------
  const react = (id, emoji) => post(`react/${enc(key)}`, { id, emoji }).then((j) => {
    const m = msgData.get(id); if (!m) return; m.reactions = j.reactions;
    const b = el.msgs.querySelector(`.m[data-id="${id}"]`); if (b) decorate(b, m);
  }).catch((e) => flashErr(e.message));
  let ctxMsg = null;
  const openCtx = (m, x, y) => {
    ctxMsg = m; closePops();
    const bar = $('ctx-react'); bar.textContent = '';
    show(bar, !m.unsent);
    if (!m.unsent) {
      quick().forEach((e) => { const b = mk('button', '', e); b.type = 'button'; b.addEventListener('click', () => { closePops(); react(m.id, e); }); bar.append(b); });
      const more = mk('button', 'more', '➕'); more.type = 'button'; more.setAttribute('aria-label', 'More reactions'); more.addEventListener('click', () => { closePops(); reactDialog(m); }); bar.append(more);
    }
    el.ctx.querySelector('[data-act="reply"]').classList.toggle('hidden', !!m.unsent);
    const pinned = settings && settings.pinned && settings.pinned.includes(m.id);
    const pinBtn = el.ctx.querySelector('[data-act="pin"]');
    pinBtn.textContent = pinned ? 'Unpin' : 'Pin';
    pinBtn.classList.toggle('hidden', !!m.unsent);
    el.ctx.querySelector('[data-act="bump"]').classList.toggle('hidden', !!m.unsent || !m.text);
    el.ctx.querySelector('[data-act="copy"]').classList.toggle('hidden', !!m.unsent || !m.text);
    el.ctx.querySelector('[data-act="translate"]').classList.toggle('hidden', !!m.unsent || !m.text);
    el.ctx.querySelector('[data-act="forward"]').classList.toggle('hidden', !!m.unsent || (!m.text && !m.image && !m.file));
    const canEdit = !m.unsent && m.from === me && m.text && !m.file && (Date.now() - parse(m.at)) <= 60000;
    $('ctx-edit').classList.toggle('hidden', !canEdit);
    $('ctx-unsend')?.classList.toggle('hidden', !!m.unsent || m.from !== me);
    $('ctx-delete')?.classList.toggle('hidden', false);
    placeAt(el.ctx, x, y);
  };
  el.msgs.addEventListener('contextmenu', (e) => {
    const b = e.target.closest('.m'); if (!b) return;
    const m = msgData.get(+b.dataset.id); if (!m) return;
    e.preventDefault(); openCtx(m, e.clientX, e.clientY);
  });
  const unsendMsg = async (m) => {
    if (!(await nxConfirm('Unsend this message for everyone?', { okText: 'Unsend' }))) return;
    post(`unsend/${enc(key)}`, { id: m.id }).then((j) => {
      if (j.message) { msgData.delete(m.id); patchReplace(j.message); }
      loadList();
    }).catch((e) => flashErr(e.message));
  };
  const deleteMsgForMe = async (m) => {
    if (!(await nxConfirm('Delete this message for you only?', { okText: 'Delete' }))) return;
    post(`delete/${enc(key)}`, { id: m.id }).then(() => {
      const b = el.msgs.querySelector(`.m[data-id="${m.id}"]`);
      (b?.closest('.mq') || b)?.remove();
      msgData.delete(m.id);
      loadList();
    }).catch((e) => flashErr(e.message));
  };
  el.ctx.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-act]'); if (!b || !ctxMsg) return;
    const m = ctxMsg; closePops();
    ({
      reply: () => setReply(m), pin: () => pinMsg(m), bump: () => bump(m),
      copy: () => copyText(m), translate: () => translateDialog(m),
      forward: () => forwardDialog(m), edit: () => editDialog(m),
      unsend: () => unsendMsg(m), delete: () => deleteMsgForMe(m)
    })[b.dataset.act]();
  });
  const toast = (t) => { flashErr(t); };
  const copyText = (m) => {
    const done = () => toast('Message copied');
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(m.text).then(done, () => {});
    else { const t = mk('textarea'); t.value = m.text; document.body.append(t); t.select(); try { document.execCommand('copy'); done(); } catch (_) {} t.remove(); }
  };
  // Translate: same six languages as the app. Uses the browser's built-in on-device Translator (no text leaves the computer).
  const LANGS = [['English', 'en'], ['Tagalog / Filipino', 'tl'], ['Spanish', 'es'], ['Chinese', 'zh'], ['Japanese', 'ja'], ['Korean', 'ko']];
  const translateDialog = (m) => {
    const ul = mk('ul', 'fw-list');
    LANGS.forEach(([name, code]) => { const l = mk('li'); const b = mk('button', 'row', name); b.type = 'button'; b.addEventListener('click', () => translateRun(m, name, code)); l.append(b); ul.append(l); });
    openDlg('Translate to which language?', ul);
  };
  const translateRun = async (m, name, code) => {
    openDlg(`Translated (${name})`, mk('p', 'dlg-sub', 'Translating…'));
    try {
      if (!('Translator' in self)) throw new Error('unsupported');
      const avail = await Translator.availability({ sourceLanguage: 'en', targetLanguage: code });
      if (avail === 'unavailable') throw new Error('unsupported');
      const tr = await Translator.create({ sourceLanguage: 'en', targetLanguage: code });
      const out = mk('p', 'dlg-result', await tr.translate(m.text));
      const copy = mk('button', 'inf-save', 'Copy'); copy.type = 'button'; copy.addEventListener('click', () => navigator.clipboard && navigator.clipboard.writeText(out.textContent).then(() => { copy.textContent = 'Copied'; }));
      openDlg(`Translated (${name})`, out, copy);
    } catch (e) {
      openDlg(`Translated (${name})`, mk('p', 'dlg-sub', e.message === 'unsupported'
        ? 'Translation is not supported by this browser. Use a recent Chrome or Edge on desktop (it translates on your computer, nothing is sent out).'
        : 'Translation failed: ' + e.message));
    }
  };
  const pinMsg = (m) => {
    const isPinned = settings && settings.pinned && settings.pinned.includes(m.id);
    post(`pin/${enc(key)}`, { id: m.id, pin: !isPinned }).then((j) => { if (settings) { settings.pinned = j.pinned; settings.pinnedCount = j.pinned.length; applySettings(settings); } toast(isPinned ? 'Message unpinned' : 'Message pinned'); })
      .catch((e) => flashErr(e.message));
  };
  const bump = (m) => post(`bump/${enc(key)}`, { id: m.id }).then((j) => { if (j.message) addMsg(j.message); el.msgs.scrollTop = el.msgs.scrollHeight; loadList(); }).catch((e) => flashErr(e.message));

  const reactDialog = (m) => openDlg('React with an emoji', buildPicker((e) => { closeDlg(); react(m.id, e); }));
  const editDialog = (m) => {
    const t = mk('textarea', 'dlg-text'); t.value = m.text; t.maxLength = 2000; t.rows = 3;
    const err = mk('p', 'msgr-err hidden'); const ok = mk('button', 'inf-save', 'Save'); ok.type = 'button'; const cancel = mk('button', 'inf-cancel', 'Cancel'); cancel.type = 'button';
    cancel.addEventListener('click', closeDlg);
    ok.addEventListener('click', () => {
      const v = t.value.trim(); if (!v) return; ok.disabled = true;
      post(`edit/${enc(key)}`, { id: m.id, body: v }).then((j) => { closeDlg(); const old = msgData.get(m.id); if (old) { msgData.delete(m.id); patchReplace(j.message); } })
        .catch((e) => { err.textContent = e.message; show(err, true); ok.disabled = false; });
    });
    const acts = mk('div', 'dlg-acts'); acts.append(cancel, ok); openDlg('Edit message', t, err, acts); t.focus();
  };
  const patchReplace = (m) => { const b = el.msgs.querySelector(`.m[data-id="${m.id}"]`); msgData.set(m.id, m); if (b) swap(b, m); };
  const forwardDialog = (m) => {
    const list = mk('ul', 'fw-list'); const sb = mk('input', 'dlg-in'); sb.type = 'search'; sb.placeholder = 'Search chats or people'; sb.maxLength = 40;
    const isRms = peer && (peer.src === 'r' || peer.src === 'rg');
    let remoteUsers = [];
    const renderItems = () => {
      list.textContent = '';
      const q = sb.value.trim().toLowerCase();
      const seenKeys = new Set();
      const convTargets = convCache.filter((c) => {
        const cIsRms = c.src === 'r' || c.src === 'rg';
        return cIsRms === isRms && c.key !== key && c.name.toLowerCase().includes(q);
      });
      convTargets.forEach((c) => seenKeys.add(c.key));
      const all = [...convTargets];
      if (!isRms) {
        remoteUsers.forEach((u) => {
          const uk = 'n:' + u.id;
          if (uk !== key && !seenKeys.has(uk)) { seenKeys.add(uk); all.push({ key: uk, id: u.id, name: u.name, avatar: u.avatar, src: 'n' }); }
        });
      }
      all.forEach((c) => {
        const li = mk('li'); const b = mk('button', 'row'); b.type = 'button';
        const a = mk('span', 'av sm'); avatar(a, c); b.append(a, mk('span', 'meta', c.name));
        b.addEventListener('click', () => {
          b.disabled = true;
          post(`forward/${enc(key)}`, { id: m.id, to: c.key }).then(() => { closeDlg(); toast('Message forwarded'); loadList(); }).catch((e) => { flashErr(e.message); b.disabled = false; });
        });
        li.append(b); list.append(li);
      });
      if (!list.children.length) list.append(mk('li', 'msgr-empty', 'No matching chats or people.'));
    };
    let ft;
    sb.addEventListener('input', () => {
      renderItems();
      if (!isRms) {
        clearTimeout(ft);
        ft = setTimeout(() => {
          const q = sb.value.trim();
          if (q.length < 2) { remoteUsers = []; renderItems(); return; }
          api(`users?q=${enc(q)}`).then((d) => { remoteUsers = d.users || []; renderItems(); }).catch(() => {});
        }, 250);
      }
    });
    renderItems(); openDlg('Forward message to', sb, list);
  };

  // ---------- chat details sidebar ----------
  const inf = { box: $('msgr-info'), btn: $('msgr-info-btn'), main: $('inf-main'), sub: $('inf-sub'), back: $('inf-back'), title: $('inf-subtitle'),
    av: $('inf-av'), name: $('inf-name'), user: $('inf-user'), status: $('inf-status'), pinN: $('inf-pinned-n'), emoji: $('inf-emoji'),
    dis: $('inf-dis'), rr: $('inf-rr'), sec: $('inf-sec'), err: $('inf-err'), q: $('inf-q'), qbox: $('inf-search-box'),
    list: $('inf-list'), grid: $('inf-grid'), more: $('inf-more'), empty: $('inf-empty') };
  let mediaBefore = 0;
  const infErr = (t) => { inf.err.textContent = t || ''; show(inf.err, !!t); };
  const showView = (v) => { show(inf.main, v === 'main'); show(inf.sub, v !== 'main'); show(inf.qbox, v === 'search'); inf.grid.textContent = ''; inf.list.textContent = ''; show(inf.more, false); show(inf.empty, false); };
  const updatePinnedBar = (s) => {
    if (!el.pinBar) return;
    const pins = (s && s.pinned) || [];
    if (!pins.length) { show(el.pinBar, false); return; }
    if (pinIdx >= pins.length) pinIdx = 0;
    const pid = pins[pinIdx];
    const pm = msgData.get(pid);
    if (el.pinText) el.pinText.textContent = pm ? (pm.text || (pm.image ? '🖼 Image' : (pm.file ? '📄 ' + pm.file.name : 'Pinned message'))) : 'Click to view pinned message';
    if (el.pinCount) el.pinCount.textContent = pins.length > 1 ? `${pinIdx + 1}/${pins.length}` : '';
    show(el.pinBar, true);
  };
  el.pinBar?.addEventListener('click', () => {
    const pins = (settings && settings.pinned) || [];
    if (!pins.length) return;
    const pid = pins[pinIdx % pins.length];
    pinIdx = (pinIdx + 1) % pins.length;
    updatePinnedBar(settings);
    const node = el.msgs.querySelector(`.m[data-id="${pid}"]`);
    if (node) {
      node.scrollIntoView({ behavior: 'smooth', block: 'center' });
      node.classList.add('pin-flash');
      setTimeout(() => node.classList.remove('pin-flash'), 1400);
    } else {
      show(inf.box, true); root.classList.add('info-open'); inf.btn.setAttribute('aria-pressed', 'true');
      document.querySelector('[data-go="pinned"]')?.click();
    }
  });
  const applySettings = (s) => {
    settings = s; inf.rr.checked = !!s.readReceipts; inf.sec.checked = !!s.secured; inf.emoji.textContent = s.emoji || '👍';
    if (el.infMute) el.infMute.checked = !!s.muted;
    inf.pinN.textContent = s.pinned && s.pinned.length ? `${s.pinned.length}/8` : '';
    if (s.theme && s.theme !== currentThemeKey) {
      applyTheme(s.theme, false);
    }
    updateDisappearingUI(s.disappearing || 'off');
    updatePinnedBar(s);
    if (Date.now() < stickUntil || nearBottom()) scrollToBottom(false);
    const isGrp = !!(peer && (peer.isGroup || peer.src === 'g' || peer.src === 'rg'));
    const isGrpAdmin = isGrp && (ADMIN || s.myRole === 'Admin' || s.isGroupAdmin);
    if (el.infPhotoBtn) show(el.infPhotoBtn, isGrp);
    if (el.infRenameBtn) show(el.infRenameBtn, isGrp);
    if (el.infAddMembersBtn) show(el.infAddMembersBtn, isGrpAdmin);
    if (el.infLeaveBtn) show(el.infLeaveBtn, isGrp);
    show($('inf-nick-btn'), !isGrp);
    if (inf.av) {
      inf.av.classList.toggle('editable-av', isGrp);
      inf.av.title = isGrp ? 'Click to customize group photo' : '';
    }
    if (el.infBlockBtn) {
      show(el.infBlockBtn, !isGrp && peer && peer.id !== me && peer.src === 'n');
      el.infBlockBtn.querySelector('span').textContent = s.blocked ? 'Unblock user' : 'Block user';
    }
    if (s.blocked || s.blockedByPeer) {
      if (el.input) { el.input.disabled = true; el.input.placeholder = s.blocked ? 'You blocked this user' : 'You cannot reply to this conversation'; }
    } else if (el.input && !(peer && peer.pendingApproval && !ADMIN)) {
      el.input.disabled = false; el.input.placeholder = 'Aa';
    }
    if (s.members && s.members.length && el.infMembersCount) {
      el.infMembersCount.textContent = `${s.members.length} members`;
    }
    nicks = s.nicknames || nicks; headerFor(); updateSend();
  };
  const loadDetails = () => {
    if (key === null || !peer) return;
    const k = key; infErr(''); showView('main'); show($('inf-files-btn'), peer.src === 'n' || peer.src === 'g');
    inf.name.textContent = nicks.peer || peer.name; inf.user.textContent = peer.username ? '@' + peer.username : ''; avatar(inf.av, peer);
    inf.status.textContent = (peer.src === 'r' || peer.src === 'n') && !peer.isGroup ? (peer.online ? '● Online' : '● Offline') : ''; inf.status.classList.toggle('on', !!peer.online);
    api(`details/${enc(k)}`).then((d) => {
      if (k !== key) return;
      peer = Object.assign(peer, d.peer);
      avatar(inf.av, peer);
      avatar(el.av, peer);
      applySettings(d.settings);
      inf.name.textContent = nicks.peer || peer.name;
      if ((peer.src === 'r' || peer.src === 'n') && !peer.isGroup) {
        inf.status.textContent = peer.online ? '● Online' : '● Offline';
        inf.status.classList.toggle('on', !!peer.online);
      }
    }).catch((e) => infErr(e.message));
  };
  onPeerChange = () => { settings = null; updateSend(); closeDlg(); if (!inf.box.classList.contains('hidden')) loadDetails(); else api(`details/${enc(key)}`).then((d) => { if (d && key === peer.key) applySettings(d.settings); }).catch(() => {}); };

  inf.btn.addEventListener('click', () => { const open = inf.box.classList.contains('hidden'); show(inf.box, open); root.classList.toggle('info-open', open); inf.btn.setAttribute('aria-pressed', open); if (open) loadDetails(); });
  $('inf-emoji-btn').addEventListener('click', () => openDlg('Choose a default emoji', buildPicker((e) => {
    post(`setting/${enc(key)}`, { key: 'CurrentEmoji', value: e }).then((d) => { applySettings(d.settings); closeDlg(); }).catch((x) => infErr(x.message)); })));
  inf.rr.addEventListener('change', () => post(`setting/${enc(key)}`, { key: 'IsReadReceipt', value: inf.rr.checked ? 'Yes' : 'No' }).then((d) => applySettings(d.settings)).catch((x) => { infErr(x.message); inf.rr.checked = !inf.rr.checked; }));
  inf.sec.addEventListener('change', async () => {
    const on = inf.sec.checked;
    if (!on && !(await nxConfirm('Turn off Secure Messaging?\n\nFuture messages in this chat will be sent as unencrypted plain text.', { okText: 'Turn off' }))) { inf.sec.checked = true; return; }
    post(`setting/${enc(key)}`, { key: 'SecuredChat', value: on ? 'Yes' : 'No' }).then((d) => applySettings(d.settings)).catch((x) => { infErr(x.message); inf.sec.checked = !on; });
  });

  // Theme Picker Dialog
  const openThemeDialog = () => {
    const grid = mk('div', 'theme-grid');
    THEMES.forEach((t) => {
      const card = mk('button', 'theme-card' + (t.key === currentThemeKey ? ' active' : ''));
      card.type = 'button';
      const top = mk('div', 'tc-top');
      top.append(mk('span', 'tc-emoji', t.emoji));
      const colors = mk('div', 'tc-colors');
      [t.primary, t.bubble_me, t.bubble_them].forEach((c) => {
        const sw = mk('span', 'tc-swatch');
        sw.style.background = c;
        colors.append(sw);
      });
      top.append(colors);
      card.append(top, mk('span', 'tc-name', t.name), mk('span', 'tc-desc', t.desc));
      card.addEventListener('click', () => {
        const selectedKey = t.key;
        // 1. Instantly apply theme styling & wallpaper silently (0ms delay)
        applyTheme(selectedKey, false);
        // 2. Instantly close modal so chat wallpaper is visible immediately
        closeDlg();
        // 3. Instant toast confirmation
        toast(`Theme applied: ${t.name}!`);
        // 4. Optimistically update local active conversation & cache
        if (peer) peer.theme = selectedKey;
        const cached = convCache.find((c) => c.key === key);
        if (cached) cached.theme = selectedKey;

        // 5. Asynchronously persist to server without blocking UI
        post(`setting/${enc(key)}`, { key: 'CurrentTheme', value: selectedKey })
          .then((d) => {
            if (d && d.settings) {
              settings = d.settings;
              if (el.infThemeName) el.infThemeName.textContent = t.name;
            }
          })
          .catch((err) => {
            console.warn('[Theme] Persist error:', err);
            flashErr('Failed to save theme: ' + err.message);
          });
      });
      grid.append(card);
    });
    openDlg('Choose Chat Theme', grid);
  };
  $('inf-theme-btn')?.addEventListener('click', openThemeDialog);

  // Disappearing Messages Preset Picker
  const openDisappearingDialog = () => {
    const cur = (settings && settings.disappearing) || (peer && peer.disappearing) || 'off';
    const opts = [
      { mode: 'off', title: 'Off', desc: 'Messages stay in this conversation until deleted.' },
      { mode: '1m', title: '1 minute', desc: 'New messages disappear 1 minute after they are sent.' },
      { mode: '1h', title: '1 hour', desc: 'New messages disappear 1 hour after they are sent.' },
      { mode: '24h', title: '24 hours', desc: 'New messages disappear 24 hours after they are sent.' },
      { mode: '7d', title: '7 days', desc: 'New messages disappear 7 days after they are sent.' }
    ];
    const list = mk('div', 'dis-presets');
    opts.forEach((o) => {
      const btn = mk('button', 'dis-opt' + (cur === o.mode || (o.mode === '24h' && cur === 'on') ? ' active' : ''));
      btn.type = 'button';
      btn.append(mk('b', '', o.title), mk('small', '', o.desc));
      btn.addEventListener('click', () => {
        post(`disappearing/${enc(key)}`, { mode: o.mode }).then((j) => {
          if (settings) settings.disappearing = j.disappearing;
          if (peer) peer.disappearing = j.disappearing;
          updateDisappearingUI(j.disappearing);
          closeDlg();
          pull(false);
          loadList();
          toast(j.disappearing === 'off' ? 'Disappearing messages turned OFF' : `Disappearing messages set to ${o.title}`);
        }).catch((e) => flashErr(e.message));
      });
      list.append(btn);
    });
    openDlg('Disappearing messages', list);
  };
  $('inf-dis-btn')?.addEventListener('click', openDisappearingDialog);
  el.disappearingBar?.addEventListener('click', openDisappearingDialog);

  // Mute / Block / Group Management
  el.infMute?.addEventListener('change', () => {
    const on = el.infMute.checked;
    post(`mute/${enc(key)}`, { muted: on }).then((j) => {
      if (settings) settings.muted = !!j.muted;
      loadList();
      toast(j.muted ? 'Notifications muted' : 'Notifications unmuted');
    }).catch((e) => { infErr(e.message); el.infMute.checked = !on; });
  });

  el.infBlockBtn?.addEventListener('click', async () => {
    const next = !(settings && settings.blocked);
    if (!(await nxConfirm(next ? 'Block this user? They will not be able to send you messages.' : 'Unblock this user?', { okText: next ? 'Block' : 'Unblock' }))) return;
    post(`block/${enc(key)}`, { blocked: next }).then(() => loadDetails()).catch((e) => infErr(e.message));
  });

  const renderBadgePng = (icon, c1, c2) => {
    const cv = document.createElement('canvas'); cv.width = 256; cv.height = 256;
    const cx = cv.getContext('2d');
    const grd = cx.createLinearGradient(0, 0, 256, 256);
    grd.addColorStop(0, c1); grd.addColorStop(1, c2);
    cx.fillStyle = grd; cx.fillRect(0, 0, 256, 256);
    cx.font = '128px "Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif';
    cx.textAlign = 'center'; cx.textBaseline = 'middle';
    cx.fillText(icon, 128, 136);
    return cv.toDataURL('image/png');
  };

  const openPhotoStudioDialog = (mode = 'group') => {
    const isProfile = mode === 'profile';
    if (!isProfile && (!peer || !(peer.isGroup || peer.src === 'g' || peer.src === 'rg'))) return;
    const targetObj = isProfile ? { id: me || 'me', name: myName, avatar: myAvatar } : peer;
    let pickedDataUrl = null, removeFlag = false;
    const wrap = mk('div', 'gp-dlg');
    const prevWrap = mk('div', 'gp-prev-wrap');
    const prevAv = mk('span', 'av big gp-preview');
    avatar(prevAv, targetObj);
    prevWrap.append(prevAv);

    // Interactive Crop / Rearrange / Rotate / Zoom / Flip Stage (shown when an image is uploaded)
    const cropBox = mk('div', 'gp-crop-stage hidden');
    const cropCv = mk('canvas', 'gp-crop-cv'); cropCv.width = 260; cropCv.height = 260;
    const cctx = cropCv.getContext('2d');
    const sliders = mk('div', 'gp-crop-sliders');
    const zLab = mk('label', 'gp-crop-sl');
    const zTop = mk('span'); const zVal = mk('b', '', '100%'); zTop.append(document.createTextNode('Zoom '), zVal);
    const zIn = mk('input'); zIn.type = 'range'; zIn.min = '50'; zIn.max = '400'; zIn.value = '100';
    zLab.append(zTop, zIn);
    const aLab = mk('label', 'gp-crop-sl');
    const aTop = mk('span'); const aVal = mk('b', '', '0°'); aTop.append(document.createTextNode('Straighten / Rotate '), aVal);
    const aIn = mk('input'); aIn.type = 'range'; aIn.min = '-180'; aIn.max = '180'; aIn.value = '0';
    aLab.append(aTop, aIn);
    sliders.append(zLab, aLab);

    const toolRow = mk('div', 'gp-crop-btns');
    const btnRotL = mk('button', '', '↶ -90°'); btnRotL.type = 'button';
    const btnRotR = mk('button', '', '↷ +90°'); btnRotR.type = 'button';
    const btnFlipH = mk('button', '', '↔ Flip H'); btnFlipH.type = 'button';
    const btnFlipV = mk('button', '', '↕ Flip V'); btnFlipV.type = 'button';
    const btnReset = mk('button', '', 'Reset'); btnReset.type = 'button';
    toolRow.append(btnRotL, btnRotR, btnFlipH, btnFlipV, btnReset);
    cropBox.append(cropCv, sliders, toolRow);

    let cropImg = null, baseScale = 1;
    let st = { zoom: 1, angle: 0, rot90: 0, flipH: 1, flipV: 1, ox: 0, oy: 0, dragging: false, sx: 0, sy: 0 };

    const drawTransformed = (ctx, w, h, scaleFactor) => {
      ctx.save();
      ctx.translate(w / 2 + st.ox * scaleFactor, h / 2 + st.oy * scaleFactor);
      ctx.rotate(((st.rot90 + st.angle) * Math.PI) / 180);
      ctx.scale(st.flipH * baseScale * st.zoom * scaleFactor, st.flipV * baseScale * st.zoom * scaleFactor);
      ctx.drawImage(cropImg, -cropImg.width / 2, -cropImg.height / 2);
      ctx.restore();
    };

    const updateCroppedOutput = () => {
      if (!cropImg) return;
      const W = cropCv.width, H = cropCv.height, R = 104;
      cctx.clearRect(0, 0, W, H);
      cctx.fillStyle = '#0f172a'; cctx.fillRect(0, 0, W, H);
      drawTransformed(cctx, W, H, 1);

      cctx.save();
      cctx.fillStyle = 'rgba(15, 23, 42, 0.58)';
      cctx.beginPath(); cctx.rect(0, 0, W, H); cctx.arc(W / 2, H / 2, R, 0, Math.PI * 2, true); cctx.fill();
      cctx.strokeStyle = 'rgba(255,255,255,0.9)'; cctx.lineWidth = 2;
      cctx.beginPath(); cctx.arc(W / 2, H / 2, R, 0, Math.PI * 2); cctx.stroke();
      cctx.restore();

      const outCv = document.createElement('canvas'); outCv.width = 400; outCv.height = 400;
      const octx = outCv.getContext('2d');
      octx.fillStyle = '#ffffff'; octx.fillRect(0, 0, 400, 400);
      drawTransformed(octx, 400, 400, 400 / (R * 2));
      pickedDataUrl = outCv.toDataURL('image/png');
      removeFlag = false;
      avatar(prevAv, { id: targetObj.id, name: targetObj.name, avatar: pickedDataUrl });
      save.disabled = false;
    };

    const resetCrop = () => {
      if (!cropImg) return;
      const R = 104 * 2;
      baseScale = Math.max(R / cropImg.width, R / cropImg.height);
      st = { zoom: 1, angle: 0, rot90: 0, flipH: 1, flipV: 1, ox: 0, oy: 0, dragging: false, sx: 0, sy: 0 };
      zIn.value = '100'; aIn.value = '0'; zVal.textContent = '100%'; aVal.textContent = '0°';
      updateCroppedOutput();
    };

    cropCv.addEventListener('pointerdown', (e) => {
      if (!cropImg) return;
      st.dragging = true;
      const r = cropCv.getBoundingClientRect();
      const sc = cropCv.width / r.width;
      st.sx = (e.clientX - r.left) * sc - st.ox;
      st.sy = (e.clientY - r.top) * sc - st.oy;
      cropCv.setPointerCapture(e.pointerId);
    });
    cropCv.addEventListener('pointermove', (e) => {
      if (!st.dragging || !cropImg) return;
      const r = cropCv.getBoundingClientRect();
      const sc = cropCv.width / r.width;
      st.ox = (e.clientX - r.left) * sc - st.sx;
      st.oy = (e.clientY - r.top) * sc - st.sy;
      updateCroppedOutput();
    });
    ['pointerup', 'pointercancel'].forEach((ev) => cropCv.addEventListener(ev, () => { st.dragging = false; }));
    cropCv.addEventListener('wheel', (e) => {
      if (!cropImg) return;
      e.preventDefault();
      const next = Math.max(50, Math.min(400, Number(zIn.value) + (e.deltaY < 0 ? 10 : -10)));
      zIn.value = String(next); st.zoom = next / 100; zVal.textContent = next + '%';
      updateCroppedOutput();
    }, { passive: false });
    zIn.addEventListener('input', () => { st.zoom = Number(zIn.value) / 100; zVal.textContent = zIn.value + '%'; updateCroppedOutput(); });
    aIn.addEventListener('input', () => { st.angle = Number(aIn.value); aVal.textContent = aIn.value + '°'; updateCroppedOutput(); });
    btnRotL.addEventListener('click', () => { st.rot90 = (st.rot90 - 90) % 360; updateCroppedOutput(); });
    btnRotR.addEventListener('click', () => { st.rot90 = (st.rot90 + 90) % 360; updateCroppedOutput(); });
    btnFlipH.addEventListener('click', () => { st.flipH *= -1; updateCroppedOutput(); });
    btnFlipV.addEventListener('click', () => { st.flipV *= -1; updateCroppedOutput(); });
    btnReset.addEventListener('click', resetCrop);

    const fileIn = mk('input', 'hidden'); fileIn.type = 'file'; fileIn.accept = 'image/png,image/jpeg,image/webp';
    const upBtn = mk('button', 'gp-up-btn', '📷 Upload & crop photo from computer'); upBtn.type = 'button';
    upBtn.addEventListener('click', () => { fileIn.value = ''; fileIn.click(); });

    const ICONS = ['🚀', '🧪', '👟', '📦', '🛡️', '⚡', '🔥', '🎯', '🏆', '💡', '📊', '🎉', '💬', '🌟', '👑', '🔬'];
    const GRADS = [
      ['#0084ff', '#6366f1'], ['#10b981', '#059669'], ['#f59e0b', '#ef4444'], ['#ec4899', '#8b5cf6'],
      ['#06b6d4', '#3b82f6'], ['#dc2626', '#991b1b'], ['#8b5cf6', '#4f46e5'], ['#334155', '#0f172a']
    ];
    let curIcon = ICONS[0], curGrad = GRADS[0];

    const applyBadgePreview = () => {
      cropImg = null; show(cropBox, false); removeFlag = false;
      pickedDataUrl = renderBadgePng(curIcon, curGrad[0], curGrad[1]);
      avatar(prevAv, { id: targetObj.id, name: targetObj.name, avatar: pickedDataUrl });
      save.disabled = false;
    };

    fileIn.addEventListener('change', () => {
      const f = fileIn.files && fileIn.files[0];
      if (!f) return;
      const reader = new FileReader();
      reader.onload = () => {
        const im = new Image();
        im.onload = () => {
          cropImg = im;
          show(cropBox, true);
          resetCrop();
        };
        im.src = String(reader.result);
      };
      reader.readAsDataURL(f);
    });

    const secLab = mk('small', 'gp-lab', isProfile ? 'Or choose an avatar badge:' : 'Or create a custom group icon badge:');
    const iconGrid = mk('div', 'gp-icons');
    ICONS.forEach((ic) => {
      const b = mk('button', 'gp-ic', ic); b.type = 'button';
      b.addEventListener('click', () => {
        curIcon = ic;
        iconGrid.querySelectorAll('.gp-ic').forEach((n) => n.classList.toggle('active', n === b));
        applyBadgePreview();
      });
      iconGrid.append(b);
    });

    const gradRow = mk('div', 'gp-grads');
    GRADS.forEach((g, idx) => {
      const b = mk('button', 'gp-gr' + (idx === 0 ? ' active' : '')); b.type = 'button';
      b.style.background = `linear-gradient(135deg, ${g[0]}, ${g[1]})`;
      b.addEventListener('click', () => {
        curGrad = g;
        gradRow.querySelectorAll('.gp-gr').forEach((n) => n.classList.toggle('active', n === b));
        applyBadgePreview();
      });
      gradRow.append(b);
    });

    const err = mk('p', 'msgr-err hidden');
    const acts = mk('div', 'dlg-acts');
    const cancel = mk('button', 'inf-cancel', 'Cancel'); cancel.type = 'button';
    cancel.addEventListener('click', closeDlg);
    if (targetObj.avatar) {
      const rm = mk('button', 'inf-cancel ctx-danger', 'Remove photo'); rm.type = 'button';
      rm.addEventListener('click', () => {
        cropImg = null; show(cropBox, false); pickedDataUrl = null; removeFlag = true;
        avatar(prevAv, { id: targetObj.id, key: targetObj.key, name: targetObj.name, avatar: null });
        save.disabled = false;
      });
      acts.append(rm);
    }
    const save = mk('button', 'inf-save', 'Save photo'); save.type = 'button'; save.disabled = true;
    save.addEventListener('click', () => {
      save.disabled = true; show(err, false);
      if (isProfile) {
        fetch(ACCOUNT_AVATAR_URL, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' },
          body: JSON.stringify(removeFlag ? { remove: true } : { avatar: pickedDataUrl }),
        }).then((r) => r.json().then((j) => {
          if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
          if (!r.ok || j.ok === false) throw new Error(j.error || j.message || 'Could not save profile picture.');
          return j;
        })).then((j) => {
          myAvatar = j.avatar || '';
          syncMyAvatarUI();
          closeDlg();
          loadList();
          if (key) pull(false);
          toast(removeFlag ? 'Profile picture removed' : 'Profile picture updated!');
        }).catch((e) => {
          err.textContent = e.message; show(err, true); save.disabled = false;
        });
        return;
      }

      let req;
      if (removeFlag) {
        req = post(`group/photo/${enc(key)}`, { remove: true });
      } else if (pickedDataUrl) {
        req = post(`group/photo/${enc(key)}`, { avatar: pickedDataUrl });
      } else {
        return;
      }
      req.then((j) => {
        closeDlg();
        if (j.group) peer = Object.assign(peer, j.group);
        avatar(el.av, peer); avatar(inf.av, peer);
        loadList(); pull(false);
        toast(removeFlag ? 'Group photo removed' : 'Group photo updated!');
      }).catch((e) => {
        err.textContent = e.message; show(err, true); save.disabled = false;
      });
    });
    acts.append(cancel, save);
    wrap.append(prevWrap, cropBox, upBtn, fileIn, secLab, iconGrid, gradRow);
    openDlg(isProfile ? 'Change My Profile Picture' : 'Customize Group Photo', wrap, err, acts);
  };

  const syncMyAvatarUI = () => {
    if (el.myAv) avatar(el.myAv, { id: me || 'me', name: myName, avatar: myAvatar || null });
    const topAv = document.getElementById('top-user-avatar');
    if (topAv) {
      if (myAvatar) {
        topAv.style.backgroundImage = `url('${myAvatar}')`;
        topAv.style.backgroundSize = 'cover';
        topAv.style.backgroundPosition = 'center';
        topAv.style.color = 'transparent';
        topAv.textContent = '';
      } else {
        topAv.style.backgroundImage = '';
        topAv.style.color = '';
        topAv.textContent = topAv.dataset.initials || 'U';
      }
    }
  };
  syncMyAvatarUI();

  el.infPhotoBtn?.addEventListener('click', () => openPhotoStudioDialog('group'));
  el.infMyPhotoBtn?.addEventListener('click', () => openPhotoStudioDialog('profile'));
  el.myAvBtn?.addEventListener('click', () => openPhotoStudioDialog('profile'));
  inf.av?.addEventListener('click', () => {
    if (peer && (peer.isGroup || peer.src === 'g' || peer.src === 'rg')) openPhotoStudioDialog('group');
    else if (peer && String(peer.id) === String(me)) openPhotoStudioDialog('profile');
  });

  el.infRenameBtn?.addEventListener('click', () => {
    const inp = mk('input', 'dlg-in'); inp.type = 'text'; inp.maxLength = 100; inp.value = (peer && peer.name) || ''; inp.placeholder = 'Group name';
    const err = mk('p', 'msgr-err hidden'); const cancel = mk('button', 'inf-cancel', 'Cancel'); cancel.type = 'button';
    const save = mk('button', 'inf-save', 'Save'); save.type = 'button';
    cancel.addEventListener('click', closeDlg);
    save.addEventListener('click', () => {
      const name = inp.value.trim(); if (!name) return;
      save.disabled = true;
      post(`group/rename/${enc(key)}`, { name }).then((j) => {
        closeDlg();
        if (j.group) peer = Object.assign(peer, j.group);
        headerFor(); inf.name.textContent = peer.name; loadList(); pull(false); toast('Group renamed');
      }).catch((e) => { err.textContent = e.message; show(err, true); save.disabled = false; });
    });
    inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') save.click(); });
    const acts = mk('div', 'dlg-acts'); acts.append(cancel, save);
    openDlg('Rename group', inp, err, acts); inp.focus();
  });

  el.infAddMembersBtn?.addEventListener('click', () => {
    const existing = new Set(((settings && settings.members) || []).map((m) => String(m.id)));
    const selected = new Set();
    const sb = mk('input', 'dlg-in'); sb.type = 'search'; sb.placeholder = 'Search colleagues to add…'; sb.maxLength = 40;
    const list = mk('ul', 'fw-list');
    const err = mk('p', 'msgr-err hidden');
    const cancel = mk('button', 'inf-cancel', 'Cancel'); cancel.type = 'button';
    const addBtn = mk('button', 'inf-save', 'Add (0)'); addBtn.type = 'button'; addBtn.disabled = true;
    cancel.addEventListener('click', closeDlg);
    const search = (q) => {
      api(`users?q=${enc(q)}`).then((d) => {
        list.textContent = '';
        (d.users || []).filter((u) => !existing.has(String(u.id))).forEach((u) => {
          const li = mk('li'); const b = mk('button', 'row' + (selected.has(String(u.id)) ? ' sel' : '')); b.type = 'button';
          const a = mk('span', 'av sm'); avatar(a, u);
          b.append(a, mk('span', 'meta', u.name));
          b.addEventListener('click', () => {
            const uid = String(u.id);
            if (selected.has(uid)) selected.delete(uid); else selected.add(uid);
            b.classList.toggle('sel', selected.has(uid));
            addBtn.textContent = `Add (${selected.size})`; addBtn.disabled = selected.size === 0;
          });
          li.append(b); list.append(li);
        });
        if (!list.children.length) list.append(mk('li', 'msgr-empty', 'No eligible users found.'));
      }).catch(() => {});
    };
    let at; sb.addEventListener('input', () => { clearTimeout(at); at = setTimeout(() => search(sb.value.trim()), 250); });
    addBtn.addEventListener('click', () => {
      addBtn.disabled = true;
      post(`group/members/add/${enc(key)}`, { members: [...selected] }).then(() => {
        closeDlg(); loadDetails(); loadList(); pull(false); toast('Members added');
      }).catch((e) => { err.textContent = e.message; show(err, true); addBtn.disabled = false; });
    });
    const acts = mk('div', 'dlg-acts'); acts.append(cancel, addBtn);
    openDlg('Add people to group', sb, list, err, acts); search(''); sb.focus();
  });

  el.infLeaveBtn?.addEventListener('click', async () => {
    if (!(await nxConfirm('Leave this group? You will no longer receive messages from it.', { okText: 'Leave group' }))) return;
    post(`group/leave/${enc(key)}`, {}).then(() => {
      key = null; peer = null; show(el.pane, false); show(el.blank, true); show(inf.box, false);
      root.classList.remove('open', 'info-open'); loadList(); toast('You left the group');
    }).catch((e) => infErr(e.message));
  });

  // Group Admin Approval Actions
  el.abApprove?.addEventListener('click', () => {
    post(`group/approve/${enc(key)}`, { action: 'approve' }).then(() => {
      toast('Group approved! Messaging is now active for all members.');
      show(el.approvalBanner, false);
      if (peer) peer.pendingApproval = false;
      loadList();
    }).catch((e) => flashErr(e.message));
  });

  el.abReject?.addEventListener('click', async () => {
    if (!(await nxConfirm('Are you sure you want to reject this group?', { okText: 'Reject' }))) return;
    post(`group/approve/${enc(key)}`, { action: 'reject' }).then(() => {
      toast('Group rejected.');
      show(el.approvalBanner, false);
      loadList();
      root.classList.remove('open');
    }).catch((e) => flashErr(e.message));
  });

  // nicknames: list of members -> click one -> dialog to set a custom nickname (as in the app)
  const nickList = () => {
    const ul = mk('ul', 'nk-list');
    [{ who: 'peer', name: peer.name, nick: nicks.peer, p: peer }, { who: 'me', name: 'You', nick: nicks.me, p: { id: me, key: 'me:' + me, name: me } }].forEach((m) => {
      const li = mk('li'); const b = mk('button', 'row'); b.type = 'button';
      const a = mk('span', 'av sm'); avatar(a, m.who === 'peer' ? m.p : { id: me, key: 'me', name: 'You' });
      const meta = mk('span', 'meta'); meta.append(mk('b', '', m.who === 'me' ? 'You' : m.name), mk('small', m.nick ? '' : 'set', m.nick || 'Set nickname'));
      b.append(a, meta, mk('span', 'chev', '›')); b.addEventListener('click', () => nickEdit(m)); li.append(b); ul.append(li);
    });
    return ul;
  };
  const nickEdit = (m) => {
    const label = m.who === 'me' ? 'You' : m.name;
    const sub = mk('p', 'dlg-sub', `${label} will only see this in this conversation.`);
    const wrap = mk('div', 'dlg-inwrap'); const input = mk('input', 'dlg-in'); input.type = 'text'; input.maxLength = 40; input.placeholder = 'Nickname'; input.value = m.nick || '';
    const clear = mk('button', 'msgr-icon', '✕'); clear.type = 'button'; clear.setAttribute('aria-label', 'Clear'); clear.addEventListener('click', () => { input.value = ''; input.focus(); }); wrap.append(input, clear);
    const err = mk('p', 'msgr-err hidden'); const cancel = mk('button', 'inf-cancel', 'Cancel'); cancel.type = 'button'; const save = mk('button', 'inf-save', 'Save'); save.type = 'button';
    cancel.addEventListener('click', () => openDlg('Nicknames', nickList()));
    save.addEventListener('click', () => { save.disabled = true;
      post(`setting/${enc(key)}`, { nickname_for: m.who, nickname: input.value }).then((d) => { applySettings(d.settings); loadList(); openDlg('Nicknames', nickList()); toast('Nickname saved!'); })
        .catch((x) => { err.textContent = x.message; show(err, true); save.disabled = false; }); });
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') save.click(); });
    const acts = mk('div', 'dlg-acts'); acts.append(cancel, save); openDlg('Edit nickname', sub, wrap, err, acts); input.focus();
  };
  $('inf-nick-btn').addEventListener('click', () => openDlg('Nicknames', nickList()));

  // sub views: pinned / search / media / files / members
  const li = (m, withUnpin) => {
    const l = mk('li'); l.append(mk('span', '', m.text || (m.image ? '🖼 Image' : m.file ? '📄 ' + m.file.name : '')));
    l.append(mk('small', '', `${m.from === me ? 'You' : (nicks.peer || peer.name)} · ${fmtShort(m.at)}`));
    if (withUnpin) { const u = mk('button', 'inf-un', 'Unpin'); u.type = 'button'; u.addEventListener('click', () => post(`pin/${enc(key)}`, { id: m.id, pin: false }).then((j) => { settings.pinned = j.pinned; applySettings(settings); l.remove(); if (!inf.list.children.length) showEmpty('No pinned messages.'); }).catch((e) => infErr(e.message))); l.append(u); }
    return l;
  };
  const showEmpty = (t) => { inf.empty.textContent = t; show(inf.empty, true); };
  const loadMedia = (reset, files) => {
    if (reset) { mediaBefore = 0; inf.grid.textContent = ''; inf.list.textContent = ''; }
    api(`media/${enc(key)}?before=${mediaBefore}${files ? '&files=1' : ''}`).then((d) => {
      d.items.forEach((m) => {
        if (files) { const l = mk('li'); const a = mk('a', '', '📄 ' + m.file.name); a.href = m.file.url; a.rel = 'noopener noreferrer'; l.append(a, mk('small', '', `${fmtSize(m.file.size)} · ${fmtShort(m.at)}`)); inf.list.append(l); }
        else { const a = mk('a'); a.href = m.image; a.dataset.at = m.at; a.dataset.who = m.from === me ? 'You' : (nicks.peer || peer.name); const i = mk('img'); i.src = m.image; i.alt = 'Attachment'; i.loading = 'lazy'; a.append(i); inf.grid.append(a); }
        mediaBefore = m.id;
      });
      show(inf.more, d.items.length >= 24); inf.more.onclick = () => loadMedia(false, files);
      if (!inf.grid.children.length && !inf.list.children.length) showEmpty(files ? 'No files shared yet.' : 'No media shared yet.');
    }).catch((e) => showEmpty(e.message));
  };
  const loadMembers = () => {
    inf.list.textContent = '';
    const mems = (settings && settings.members) || [];
    const canManage = ADMIN || (settings && settings.myRole === 'Admin');
    if (!mems.length) { showEmpty('No members found.'); return; }
    mems.forEach((m) => {
      const it = mk('li', 'roster-item');
      const av = mk('span', 'av sm'); avatar(av, m);
      const meta = mk('div', 'meta');
      meta.append(mk('b', '', m.name || ('User ' + m.id)), mk('small', '', m.username ? '@' + m.username : ''));
      const role = mk('span', 'role-tag' + (m.role === 'Admin' ? ' admin' : ''), m.role || 'Member');
      it.append(av, meta, role);
      if (canManage && String(m.id) !== String(me)) {
        const rm = mk('button', 'inf-un', 'Remove'); rm.type = 'button';
        rm.addEventListener('click', async () => {
          if (!(await nxConfirm(`Remove ${m.name || 'this member'} from the group?`, { okText: 'Remove' }))) return;
          post(`group/members/remove/${enc(key)}`, { user_id: m.id }).then(() => {
            loadDetails(); loadMembers(); loadList(); pull(false); toast('Member removed');
          }).catch((e) => infErr(e.message));
        });
        it.append(rm);
      }
      inf.list.append(it);
    });
  };

  document.querySelectorAll('[data-go]').forEach((b) => b.addEventListener('click', () => {
    const go = b.dataset.go; showView(go);
    inf.title.textContent = { pinned: 'Pinned messages', search: 'Search in conversation', media: 'Media (attachments)', files: 'Files', members: 'Group Members' }[go] || 'Details';
    if (go === 'pinned') api(`pinned/${enc(key)}`).then((d) => { d.messages.forEach((m) => inf.list.append(li(m, true))); if (!d.messages.length) showEmpty('No pinned messages.'); }).catch((e) => showEmpty(e.message));
    if (go === 'search') { inf.q.value = ''; inf.q.focus(); }
    if (go === 'media') loadMedia(true, false);
    if (go === 'files') loadMedia(true, true);
    if (go === 'members') loadMembers();
  }));
  let st; inf.q.addEventListener('input', () => { clearTimeout(st); st = setTimeout(() => {
    const q = inf.q.value.trim(); inf.list.textContent = ''; show(inf.empty, false); if (q.length < 2) return;
    api(`chat-search/${enc(key)}?q=${enc(q)}`).then((d) => { d.messages.forEach((m) => inf.list.append(li(m, false))); if (!d.messages.length) showEmpty('No messages found (searches the latest 600).'); }).catch((e) => showEmpty(e.message));
  }, 300); });
  inf.back.addEventListener('click', () => showView('main'));

  // ---------- full-screen image viewer (like the app's DialogFullScreenImageViewer) ----------
  const lb = { box: null, list: [], i: 0, zoom: false, drag: null };
  const lbBuild = () => {
    const box = mk('div', 'lb hidden'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', 'Image viewer');
    const top = mk('div', 'lb-top');
    const cap = mk('div', 'lb-cap'); cap.append(mk('b'), mk('small'));
    const btn = (txt, label, fn) => { const b = mk('button', 'lb-btn', txt); b.type = 'button'; b.setAttribute('aria-label', label); b.title = label; b.addEventListener('click', fn); return b; };
    const dl = mk('a', 'lb-btn', '⬇'); dl.setAttribute('aria-label', 'Download'); dl.title = 'Download';
    top.append(cap, dl, btn('✕', 'Close', lbClose));
    const stage = mk('div', 'lb-stage'); const img = mk('img'); img.alt = 'Image'; img.draggable = false;
    const prev = btn('‹', 'Previous image', () => lbGo(-1)); prev.classList.add('lb-nav', 'lb-prev');
    const next = btn('›', 'Next image', () => lbGo(1)); next.classList.add('lb-nav', 'lb-next');
    stage.append(img, prev, next);
    const thumbs = mk('div', 'lb-thumbs');
    box.append(top, stage, thumbs); document.body.append(box);
    Object.assign(lb, { box, cap, dl, stage, img, prev, next, thumbs });
    img.addEventListener('click', (e) => { if (lb.moved) { lb.moved = false; return; } lbZoom(!lb.zoom, e); });
    img.addEventListener('pointerdown', (e) => { if (!lb.zoom) return; lb.drag = { x: e.clientX, y: e.clientY, sx: stage.scrollLeft, sy: stage.scrollTop }; lb.moved = false; img.setPointerCapture(e.pointerId); });
    img.addEventListener('pointermove', (e) => { if (!lb.drag) return; const dx = e.clientX - lb.drag.x, dy = e.clientY - lb.drag.y; if (Math.abs(dx) + Math.abs(dy) > 4) lb.moved = true; stage.scrollLeft = lb.drag.sx - dx; stage.scrollTop = lb.drag.sy - dy; });
    img.addEventListener('pointerup', () => { lb.drag = null; });
    stage.addEventListener('mousedown', (e) => { if (e.target === stage && !lb.zoom) lbClose(); });
  };
  const lbZoom = (on, e) => {
    lb.zoom = on; lb.stage.classList.toggle('zoomed', on); lb.box.classList.toggle('is-zoomed', on);
    if (on && e) { const r = lb.img.getBoundingClientRect(); const fx = (e.clientX - r.left) / r.width, fy = (e.clientY - r.top) / r.height;
      requestAnimationFrame(() => { lb.stage.scrollLeft = fx * lb.stage.scrollWidth - lb.stage.clientWidth / 2; lb.stage.scrollTop = fy * lb.stage.scrollHeight - lb.stage.clientHeight / 2; }); }
    if (!on) { lb.stage.scrollLeft = 0; lb.stage.scrollTop = 0; }
  };
  const lbShow = () => {
    const it = lb.list[lb.i]; lbZoom(false);
    lb.img.src = it.url; lb.dl.href = it.url; lb.dl.setAttribute('download', it.name || 'image');
    lb.cap.firstChild.textContent = it.who || ''; lb.cap.lastChild.textContent = `${it.at ? fmtShort(it.at) : ''}${lb.list.length > 1 ? `  ·  ${lb.i + 1} / ${lb.list.length}` : ''}`;
    lb.prev.classList.toggle('hidden', lb.i === 0); lb.next.classList.toggle('hidden', lb.i === lb.list.length - 1);
    [...lb.thumbs.children].forEach((t, k) => { t.classList.toggle('on', k === lb.i); if (k === lb.i) t.scrollIntoView({ inline: 'center', block: 'nearest' }); });
  };
  const lbGo = (d) => { const n = lb.i + d; if (n < 0 || n >= lb.list.length) return; lb.i = n; lbShow(); };
  const lbClose = () => { if (lb.box) { lb.box.classList.add('hidden'); lb.img.removeAttribute('src'); } document.body.classList.remove('lb-open'); };
  const openViewer = (list, index) => {
    if (!list.length) return; if (!lb.box) lbBuild();
    lb.list = list; lb.i = Math.max(0, Math.min(index, list.length - 1)); lb.thumbs.textContent = '';
    list.forEach((it, k) => { const t = mk('button', 'lb-th'); t.type = 'button'; const im = mk('img'); im.src = it.url; im.alt = ''; im.loading = 'lazy'; t.append(im); t.addEventListener('click', () => { lb.i = k; lbShow(); }); lb.thumbs.append(t); });
    lb.thumbs.classList.toggle('hidden', list.length < 2);
    lb.box.classList.remove('hidden'); document.body.classList.add('lb-open'); lbShow();
  };
  document.addEventListener('keydown', (e) => {
    if (!lb.box || lb.box.classList.contains('hidden')) return;
    if (e.key === 'Escape') { e.stopPropagation(); lbClose(); } else if (e.key === 'ArrowLeft' && !lb.zoom) lbGo(-1); else if (e.key === 'ArrowRight' && !lb.zoom) lbGo(1);
  }, true);
  const chatImages = () => [...msgData.values()].filter((m) => m.image).sort((a, b) => a.id - b.id)
    .map((m) => ({ id: m.id, url: m.image, at: m.at, who: m.from === me ? 'You' : (nicks.peer || (peer && peer.name) || ''), name: m.file ? m.file.name : `image-${m.id}.jpg` }));
  el.msgs.addEventListener('click', (e) => {
    const a = e.target.closest('a.pimg'); if (!a) return;
    e.preventDefault(); const id = +a.closest('.m').dataset.id, list = chatImages(); openViewer(list, list.findIndex((x) => x.id === id));
  });
  inf.grid.addEventListener('click', (e) => {
    const a = e.target.closest('a'); if (!a) return; e.preventDefault();
    const items = [...inf.grid.querySelectorAll('a')].map((x) => ({ url: x.href, at: x.dataset.at, who: x.dataset.who, name: 'image' }));
    openViewer(items, [...inf.grid.querySelectorAll('a')].indexOf(a));
  });

  // ---------- New Message & Group Messaging (with 50-member limit) ----------
  const newChatDialog = (forceGroup = false) => {
    const selected = new Map(); // id => person object
    let via = 'n';
    const search = mk('input', 'dlg-in'); search.type = 'search'; search.placeholder = 'Search for Name'; search.maxLength = 50; search.setAttribute('aria-label', 'Search for Name');
    const chips = mk('div', 'nc-chips'), grid = mk('div', 'nc-grid'), note = mk('p', 'nc-note hidden');
    const viaBox = mk('div', 'nc-via hidden');

    // Group specific inputs
    const grpNameWrap = mk('div', 'dlg-inwrap hidden');
    const grpNameInput = mk('input', 'dlg-in'); grpNameInput.type = 'text'; grpNameInput.maxLength = 100; grpNameInput.placeholder = 'Group Name (e.g. Audit QC Team)';
    grpNameWrap.append(grpNameInput);

    const memberCounter = mk('span', 'limit-badge', '0 / 50 members');

    const body = mk('textarea', 'dlg-text'); body.rows = 2; body.maxLength = 2000; body.placeholder = 'Write a message (optional for groups)'; body.setAttribute('aria-label', 'Write a message');
    const send = mk('button', 'msgr-send nc-send'); send.type = 'button'; send.setAttribute('aria-label', 'Submit'); send.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M3.4 20.4l17.4-7.5a1 1 0 000-1.8L3.4 3.6a.8.8 0 00-1.1.9L3.5 10l9 2-9 2-1.2 5.5a.8.8 0 001.1.9z"/></svg>';
    const err = mk('p', 'msgr-err hidden'); err.setAttribute('role', 'alert');
    const row2 = mk('div', 'nc-compose'); row2.append(body, send);
    const lab = (t) => mk('div', 'nc-lab', t);
    const sysName = { n: 'NEXUS-L (web)', r: 'RMS (Android app)' };

    const personFor = (u, src) => ({ key: u.systems[src], id: u.systems[src].split(':')[1], name: u.name, username: u.username, avatar: u.avatar, online: u.online, src, dept: u.dept });

    const renderChips = () => {
      chips.textContent = '';
      const count = selected.size;
      const isGrp = forceGroup || count > 1;
      show(grpNameWrap, isGrp);
      memberCounter.textContent = `${count} / 50 members`;
      memberCounter.classList.toggle('warn', count > 50);

      if (count > 50) {
        note.textContent = `⚠️ You selected ${count} members (> 50). This group will require Administrator approval before regular members can start messaging.`;
        show(note, true);
      } else {
        show(note, false);
      }

      if (count === 0) {
        chips.append(mk('span', 'nc-none', 'No contacts selected yet'));
        return;
      }

      selected.forEach((u) => {
        const c = mk('span', 'nc-chip');
        const a = mk('span', 'av sm');
        avatar(a, { key: u.id, id: u.id, name: u.name, avatar: u.avatar, online: false });
        const x = mk('button', '', '✕'); x.type = 'button'; x.setAttribute('aria-label', 'Remove ' + u.name);
        x.addEventListener('click', () => { selected.delete(u.id); renderChips(); renderVia(); markCards(); });
        c.append(a, mk('span', '', u.name), x); chips.append(c);
      });
    };

    const renderVia = () => {
      viaBox.textContent = '';
      if (!ADMIN) return;
      viaBox.append(mk('span', 'nc-lab', 'System'));
      ['n', 'r'].forEach((s) => {
        const l = mk('label', 'nc-radio');
        const r = mk('input'); r.type = 'radio'; r.name = 'nc-via'; r.value = s; r.checked = via === s;
        r.addEventListener('change', () => { via = s; });
        l.append(r, document.createTextNode(' ' + sysName[s]));
        viaBox.append(l);
      });
      show(viaBox, true);
    };

    const markCards = () => [...grid.children].forEach((c) => c.classList.toggle('on', selected.has(c.dataset.id)));

    const pick = (u) => {
      if (selected.has(u.id)) {
        selected.delete(u.id);
      } else {
        selected.set(u.id, u);
      }
      renderChips(); renderVia(); markCards();
    };

    const card = (u) => {
      const b = mk('button', 'nc-card'); b.type = 'button'; b.dataset.id = u.id;
      const a = mk('span', 'av'); avatar(a, { key: u.id, id: u.id, name: u.name, avatar: u.avatar, online: u.online });
      const m = mk('span', 'meta'); const n = mk('b', '', u.name);
      if (ADMIN) Object.keys(u.systems).forEach((s) => n.append(mk('i', 'src ' + s, s === 'r' ? 'RMS' : 'NEXUS-L')));
      m.append(n, mk('small', '', u.username ? '@' + u.username : (u.dept || '')));
      b.append(a, m); b.addEventListener('click', () => pick(u)); return b;
    };

    const load = (q) => api(`users?q=${enc(q)}`).then((d) => {
      grid.textContent = ''; d.users.forEach((u) => grid.append(card(u)));
      if (!d.users.length) grid.append(mk('p', 'msgr-empty', q ? 'No people found.' : 'No contacts available.'));
      if (d.warnings && d.warnings.length) { err.textContent = d.warnings.join(' '); show(err, true); } else if (!err.dataset.keep) show(err, false);
      markCards();
    }).catch((e) => { err.textContent = e.message; show(err, true); });

    let t; search.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => load(search.value.trim()), 250); });

    const submitNew = () => {
      err.dataset.keep = '';
      const count = selected.size;
      if (count === 0) {
        err.textContent = 'Please select at least one contact.';
        err.dataset.keep = '1'; show(err, true); return;
      }

      const isGrp = forceGroup || count > 1;
      if (isGrp) {
        const grpName = grpNameInput.value.trim() || `Group (${count + 1} members)`;
        const memberIds = [...selected.values()].map((u) => {
          const sys = u.systems[via] || u.systems.n || u.systems.r;
          return sys.split(':')[1];
        });
        send.disabled = true; show(err, false);
        post('group/create', { name: grpName, members: memberIds, source: via }).then((j) => {
          closeDlg();
          toast(j.message || 'Group created!');
          const groupObj = {
            id: j.groupId,
            groupId: j.groupId,
            key: j.key,
            isGroup: true,
            name: j.name,
            pendingApproval: j.pendingApproval,
            memberCount: j.memberCount,
            src: via === 'r' ? 'rg' : 'g',
          };
          open(groupObj);
          loadList();
        }).catch((e) => {
          err.textContent = 'Failed to create group: ' + e.message;
          err.dataset.keep = '1'; show(err, true); send.disabled = false;
        });
      } else {
        const singleSel = [...selected.values()][0];
        const text = body.value.trim();
        if (!text) { err.textContent = 'Please write a message first'; err.dataset.keep = '1'; show(err, true); return; }
        const p = personFor(singleSel, via);
        send.disabled = true; show(err, false);
        post(`send/${enc(p.key)}`, { body: text }).then(() => {
          closeDlg(); toast('Message sent!'); open(p); loadList();
        }).catch((e) => {
          err.textContent = 'Failed to send: ' + e.message;
          err.dataset.keep = '1'; show(err, true); send.disabled = false;
        });
      }
    };

    send.addEventListener('click', submitNew);
    body.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitNew(); } });

    const headerTitle = forceGroup ? 'New Group' : 'New Message';
    const chipsHeader = mk('div', 'dlg-head');
    chipsHeader.append(lab('Selected Members'), memberCounter);

    openDlg(headerTitle, grpNameWrap, search, chipsHeader, chips, note, viaBox, lab('Contacts'), grid, row2, err);
    el.dlgCard.classList.add('wide'); renderChips(); renderVia(); load(''); search.focus();
  };

  $('msgr-new').addEventListener('click', () => newChatDialog(false));
  $('msgr-new-group')?.addEventListener('click', () => newChatDialog(true));

  // ---------- polling (paused while the tab is hidden) ----------
  updateSend(); loadList(); initNotifBanner();
  setInterval(() => { if (!document.hidden) pull(false); }, 4000);
  setInterval(() => { if (!document.hidden && !el.q.value.trim()) loadList(); }, 10000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { pull(false); loadList(); } });
})();
