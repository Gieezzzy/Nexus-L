/**
 * NEXUS-L assistant (local, system-only) + activity recorder. Loaded on every signed-in page.
 *  - Recorder: page views, button clicks (label only), errors, failed requests, long waits and repeated clicks.
 *    Never reads what is typed into fields, never records passwords.
 *  - Chat: answers come from the Brain (knowledge on the server); can open pages, run checks, and hand the
 *    conversation to an administrator, whose replies appear in the same chat.
 */
(() => {
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  if (!document.body || !csrfMeta) return;
  const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const pagePath = () => location.pathname.replace(new RegExp('^' + BASE.replace(/^https?:\/\/[^/]+/, '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&')), '').replace(/^\/+/, '') || 'dashboard';
  const origFetch = window.fetch.bind(window);
  const relPath = (u) => { try { const p = new URL(u, location.href).pathname; const b = new URL(BASE, location.href).pathname; return (p.startsWith(b) ? p.slice(b.length) : p.replace(/^\//, '')); } catch (e) { return String(u).split('?')[0]; } };

  // ------------------------------------------------------------------------------------------- requests
  const token = () => csrfMeta.content;
  const api = async (path, opts = {}, retry = true) => {
    const r = await origFetch(BASE + path, Object.assign({ credentials: 'same-origin' }, opts, {
      headers: Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token() }, opts.headers || {}),
    }));
    if (r.status === 403 && retry) {                 // stale CSRF token: fetch a fresh one and try once more
      const t = await origFetch(BASE + 'bot/badge', { credentials: 'same-origin' }).then((x) => x.json()).catch(() => null);
      if (t && t.csrf) { csrfMeta.content = t.csrf; return api(path, opts, false); }
    }
    const j = await r.json().catch(() => ({}));
    if (j.csrf) csrfMeta.content = j.csrf;
    if (!r.ok || j.ok === false) throw Object.assign(new Error(j.error || 'Request failed'), { status: r.status });
    return j;
  };
  const post = (path, body, extra = {}) => api(path, Object.assign({ method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) }, extra));

  // ------------------------------------------------------------------------------------------- recorder
  let queue = [];
  const SAVE_RE = /save|submit|confirm|send|print|download|load|queue|sync|delete|reset|sign in|connect/i;
  const record = (type, detail = {}) => {
    queue.push(Object.assign({ type, page: pagePath() }, detail));
    if (queue.length >= 25) flush();
  };
  const flush = (keepalive = false) => {
    if (!queue.length) return;
    const events = queue.splice(0, 40);
    post('bot/events', { events }, keepalive ? { keepalive: true } : {}).catch(() => {});
  };
  setInterval(() => flush(), 8000);
  document.addEventListener('visibilitychange', () => { if (document.hidden) flush(true); });
  window.addEventListener('pagehide', () => flush(true));

  record('view', { target: document.title.replace(/ · NEXUS-L$/, '') });

  const labelOf = (el) => {
    const t = (el.textContent || el.getAttribute('aria-label') || el.value || el.title || '').replace(/\s+/g, ' ').trim();
    return t.slice(0, 60) || (el.id ? '#' + el.id : el.tagName.toLowerCase());
  };
  let lastClick = { key: '', at: 0, n: 0 };
  document.addEventListener('click', (e) => {
    const el = e.target.closest('button, a, [role=button], input[type=submit], input[type=button], .dtr-pill, .dtr-tab-btn');
    if (!el || el.closest('#nxbot-panel, #nxbot-fab') || el.closest('[data-nx-private]')) return;
    const label = labelOf(el);
    record(SAVE_RE.test(label) ? 'save' : 'click', { target: label });
    const now = Date.now();
    lastClick = (lastClick.key === label && now - lastClick.at < 1500) ? { key: label, at: now, n: lastClick.n + 1 } : { key: label, at: now, n: 1 };
    if (lastClick.n === 4) { record('stuck', { target: label, detail: 'repeated clicks' }); offerHelp('Something not responding?'); }
  }, true);

  window.addEventListener('error', (e) => {
    if (!e.message || /ResizeObserver|Script error/i.test(e.message)) return;
    record('error', { target: (e.filename || '').split('/').pop(), detail: e.message });
    offerHelp('Something went wrong on this page.');
  });
  window.addEventListener('unhandledrejection', (e) => {
    const m = String((e.reason && e.reason.message) || e.reason || '');
    if (m) record('error', { detail: 'promise: ' + m });
  });

  // every request of the page: failures and long waits become events (never the content)
  window.fetch = async (...a) => {
    const url = String(typeof a[0] === 'string' ? a[0] : (a[0] && a[0].url) || '');
    const own = url.includes('/bot/') || url.includes('bot/events');
    const t0 = performance.now();
    let slow = null;
    if (!own) slow = setTimeout(() => record('stuck', { target: relPath(url), detail: 'waiting more than 15 seconds' }), 15000);
    try {
      const r = await origFetch(...a);
      if (!own && !r.ok) {
        const path = relPath(url);
        record('http_error', { target: path, status: r.status, detail: 'HTTP ' + r.status + ' on ' + path });
        if (r.status >= 500) offerHelp('The server reported an error.');
      }
      return r;
    } catch (err) {
      if (!own) { record('error', { target: relPath(url), detail: 'network: ' + (err && err.message) }); offerHelp('Cannot reach the server.'); }
      throw err;
    } finally { if (slow) clearTimeout(slow); void t0; }
  };

  // ------------------------------------------------------------------------------------------- widget
  const fab = document.createElement('button');
  fab.id = 'nxbot-fab'; fab.type = 'button'; fab.setAttribute('aria-label', 'Open LEXIS AI'); fab.title = 'Ask LEXIS AI';
  fab.innerHTML = '<img class="nxbot-fab-icon" src="' + BASE + 'assets/images/lexis-icon.png" alt="LEXIS AI" width="36" height="36"><span class="nxbot-dot" hidden></span>';
  const bubble = document.createElement('div');
  bubble.id = 'nxbot-bubble'; bubble.hidden = true;
  const panel = document.createElement('section');
  panel.id = 'nxbot-panel'; panel.hidden = true; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', 'LEXIS AI');
  panel.innerHTML = `
    <header>
      <div class="nxbot-hbrand">
        <img class="nxbot-hlogo" src="${BASE}assets/images/lexis-icon.png" alt="LEXIS AI" width="34" height="34">
        <div><b>LEXIS AI</b><small>Laboratory Execution Intelligence System</small></div>
      </div>
      <div class="nxbot-hbtns"><button type="button" data-a="history" title="My recent activity">History</button><button type="button" data-a="close" aria-label="Close assistant">&times;</button></div>
    </header>
    <div class="nxbot-live" id="nxbot-live" hidden></div>
    <div class="nxbot-msgs" id="nxbot-msgs" aria-live="polite"></div>
    <div class="nxbot-chips" id="nxbot-chips"></div>
    <form id="nxbot-form" autocomplete="off"><input id="nxbot-input" type="text" maxlength="1000" placeholder="Type your question…" aria-label="Your question"><button type="submit">Send</button></form>
    <footer><button type="button" data-a="admin">Talk to an administrator</button><span>Your actions here are recorded to improve help. See the Terms &amp; Privacy notice.</span></footer>`;
  document.body.append(fab, bubble, panel);

  const msgs = panel.querySelector('#nxbot-msgs');
  const chips = panel.querySelector('#nxbot-chips');
  const input = panel.querySelector('#nxbot-input');
  const dot = fab.querySelector('.nxbot-dot');
  let threadId = 0;
  let lastId = 0;
  let status = 'bot';
  let pollTimer = null;
  let opened = false;
  let busy = false;

  const scroll = () => { msgs.scrollTop = msgs.scrollHeight; };
  const add = (cls, html, id) => {
    const d = document.createElement('div');
    d.className = 'nxbot-m ' + cls;
    if (id) d.dataset.id = id;
    d.innerHTML = html;
    msgs.appendChild(d);
    scroll();
    if (typeof window.anime === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      window.anime({
        targets: d,
        opacity: [0, 1],
        translateY: [10, 0],
        duration: 220,
        easing: 'easeOutCubic'
      });
    }
    return d;
  };

  const renderReply = (r, chatId) => {
    let h = '';
    if (r.title) h += `<b class="nxbot-t">${esc(r.title)}</b>`;
    h += `<p>${esc(r.text).replace(/\n/g, '<br>')}</p>`;
    if (r.steps && r.steps.length) h += '<ol>' + r.steps.map((s) => `<li>${esc(s)}</li>`).join('') + '</ol>';
    if (r.diagnosis && r.diagnosis.text) h += `<div class="nxbot-diag ${r.diagnosis.ok ? 'ok' : 'bad'}"><b>${r.diagnosis.ok ? 'Checked: OK' : 'Checked: problem found'}</b> ${esc(r.diagnosis.text)}</div>`;
    if (r.actions && r.actions.length) h += '<div class="nxbot-acts">' + r.actions.map((a, i) => `<button type="button" data-act="${i}">${esc(a.label)}</button>`).join('') + '</div>';
    if (r.related && r.related.length) h += '<div class="nxbot-rel"><small>Related:</small> ' + r.related.map((x) => `<button type="button" data-q="${esc(x.title || x.question || x.label || x)}" data-topic="${esc(x.id || x.kb_id || '')}">${esc(x.title || x.question || x.label || x)}</button>`).join('') + '</div>';
    if (chatId) h += `<div class="nxbot-fb"><button type="button" data-fb="1" data-chat="${chatId}">Helpful</button><button type="button" data-fb="-1" data-chat="${chatId}">Not helpful</button>${r.suggest_admin ? '<button type="button" data-a="admin" class="nxbot-esc">Talk to an administrator</button>' : ''}</div>`;
    const d = add('bot', h, chatId);
    d._actions = r.actions || [];
    return d;
  };

  const renderRow = (m) => {
    if (m.sender === 'system') return add('sys', `<span>${esc(m.message)}</span>`, m.id);
    if (m.sender === 'user') return add('user', `<p>${esc(m.message).replace(/\n/g, '<br>')}</p>`, m.id);
    if (m.sender === 'admin') return add('admin', `<small>${esc(m.sender_name || 'Administrator')}</small><p>${esc(m.message).replace(/\n/g, '<br>')}</p>`, m.id);
    return add('bot', `<p>${esc(m.message).replace(/\n/g, '<br>')}</p>`, m.id);
  };

  const loadThread = async () => {
    if (busy) return;
    try {
      const j = await api('bot/thread?thread_id=' + threadId + '&after=' + lastId);
      if (!j.thread) return;
      if (threadId !== j.thread.id) { threadId = j.thread.id; }
      const fresh = j.messages.filter((m) => !msgs.querySelector(`[data-id="${m.id}"]`));
      fresh.forEach((m) => { renderRow(m); lastId = Math.max(lastId, Number(m.id)); });
      if (panel.hidden && fresh.some((m) => m.sender !== 'user')) dot.hidden = false;
      setLive(j.thread.status, j.session);
      if (status === 'resolved') { threadId = 0; lastId = 0; }
    } catch (e) { /* offline */ }
  };

  // live session with an administrator: green header, banner with who is answering and the 2-minute idle rule
  const live = panel.querySelector('#nxbot-live');
  const setLive = (st, s) => {
    const on = st === 'escalated' && s && s.status === 'open';
    status = on ? 'escalated' : (st === 'escalated' ? 'bot' : st);
    panel.classList.toggle('escalated', on);
    panel.querySelector('footer [data-a=admin]').textContent = on ? 'With an administrator' : 'Talk to an administrator';
    live.hidden = !on;
    if (on) {
      const who = s.admin_name ? `<b>${esc(s.admin_name)}</b> is answering you` : 'Waiting for an administrator…';
      live.innerHTML = `${who}<small>Closes after 2 minutes without messages${s.seconds_left < 60 ? ` (in ${s.seconds_left}s)` : ''}</small>`;
    }
    startPolling();
  };

  // every 3 s during a live session (even with the panel closed, so replies light the dot), otherwise 6 s while open
  let pollEvery = 0;
  const startPolling = () => {
    const every = status === 'escalated' ? 3000 : 6000;
    if (every === pollEvery && pollTimer) return;
    pollEvery = every;
    clearInterval(pollTimer);
    pollTimer = setInterval(() => { if (!document.hidden && (status === 'escalated' || !panel.hidden)) loadThread(); }, every);
  };

  const renderChips = (items) => {
    if (!items || !items.length) { chips.innerHTML = ''; return; }
    chips.innerHTML = items.map((t) => `<button type="button" data-q="${esc(t)}">${esc(t)}</button>`).join('');
    if (typeof window.anime === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      window.anime({
        targets: chips.children,
        opacity: [0, 1],
        translateY: [6, 0],
        scale: [0.93, 1],
        delay: window.anime.stagger(28),
        duration: 200,
        easing: 'easeOutCubic'
      });
    }
  };

  const open = async () => {
    panel.hidden = false; bubble.hidden = true; dot.hidden = true; fab.setAttribute('aria-expanded', 'true');
    if (typeof window.anime === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      window.anime.remove(panel);
      panel.style.transformOrigin = 'bottom right';
      window.anime({
        targets: panel,
        opacity: [0, 1],
        translateY: [22, 0],
        scale: [0.93, 1],
        duration: 260,
        easing: 'easeOutBack(1.3)'
      });
    }
    if (!opened) {
      opened = true;
      await loadThread();
      if (!msgs.children.length) {
        add('bot', `<div class="nxbot-welcome"><img class="nxbot-wlogo" src="${BASE}assets/images/lexis-logo.png" alt="LEXIS AI"><p>Hello! I am <b>LEXIS AI</b> (Laboratory Execution Intelligence System). Ask how to do something in this system, calculate test results, or check report status and I will help you.</p></div>`);
        try {
          const s = await api('bot/suggest?page=' + encodeURIComponent(pagePath()));
          renderChips(s.items || []);
        } catch (e) { /* none */ }
      }
    }
    startPolling();
    setTimeout(() => input.focus(), 50);
  };
  const close = () => {
    fab.setAttribute('aria-expanded', 'false');
    fab.focus();
    if (typeof window.anime === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      window.anime.remove(panel);
      window.anime({
        targets: panel,
        opacity: [1, 0],
        translateY: [0, 16],
        scale: [1, 0.95],
        duration: 180,
        easing: 'easeInQuad',
        complete: () => { panel.hidden = true; }
      });
    } else {
      panel.hidden = true;
    }
  };
  fab.addEventListener('click', () => {
    if (typeof window.anime === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      window.anime({
        targets: fab,
        scale: [{ value: 0.91, duration: 70, easing: 'easeInQuad' }, { value: 1, duration: 180, easing: 'easeOutBack(2)' }]
      });
    }
    panel.hidden ? open() : close();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) close(); });

  // Ambient idle breathing reminder (very light pulse every 24s if closed)
  setInterval(() => {
    if (panel.hidden && !document.hidden && typeof window.anime === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      window.anime({
        targets: fab,
        scale: [1, 1.06, 1],
        duration: 900,
        easing: 'easeInOutQuad'
      });
    }
  }, 24000);

  const ask = async (text) => {
    text = text.trim();
    if (!text) return;
    chips.innerHTML = '';
    busy = true;
    const mine = add('user', `<p>${esc(text)}</p>`);
    const wait = add('bot typing', '<div class="nxbot-typing-dots"><span></span><span></span><span></span></div>');
    try {
      const j = await post('bot/ask', { message: text, thread_id: threadId, page: pagePath() });
      threadId = j.thread_id;
      if (j.user_chat_id) { mine.dataset.id = j.user_chat_id; lastId = Math.max(lastId, Number(j.user_chat_id)); }
      wait.remove();
      if (j.escalated) setLive('escalated', j.session);
      else if (j.reply) { renderReply(j.reply, j.reply.chat_id); lastId = Math.max(lastId, Number(j.reply.chat_id || 0)); }
      record('bot', { target: 'asked the assistant' });
    } catch (e) {
      wait.remove();
      add('bot', `<p>${esc(e.status === 401 ? 'You were signed out. Please sign in again.' : 'I could not answer right now. Please try again in a moment.')}</p>`);
    } finally {
      busy = false;
      const seen = new Set();
      [...msgs.querySelectorAll('[data-id]')].forEach((n) => { if (seen.has(n.dataset.id)) n.remove(); else seen.add(n.dataset.id); });
    }
  };

  panel.querySelector('#nxbot-form').addEventListener('submit', (e) => { e.preventDefault(); const v = input.value; input.value = ''; ask(v); });

  panel.addEventListener('click', async (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    if (b.dataset.q) return ask(b.dataset.q);
    if (b.dataset.a === 'close') return close();
    if (b.dataset.a === 'admin') {
      if (status === 'escalated') { input.focus(); return; }
      b.disabled = true;
      try {
        const j = await post('bot/escalate', { thread_id: threadId, page: pagePath() });
        threadId = j.thread_id;
        record('bot', { target: 'talk to an administrator' });
        await loadThread();
        input.focus();
      } catch (x) { add('bot', '<p>Could not reach an administrator right now. Please try again in a moment.</p>'); }
      b.disabled = false;
      return;
    }
    if (b.dataset.a === 'history') {
      try {
        const h = await api('bot/history');
        add('bot', '<b class="nxbot-t">Your recent activity (only you can see this)</b><ul class="nxbot-hist">' + h.events.slice(0, 12).map((x) => `<li><small>${esc(x.created_at)}</small> ${esc(x.event_type)} · ${esc(x.target || x.page || '')}</li>`).join('') + '</ul>');
      } catch (x) { /* ignore */ }
      return;
    }
    if (b.dataset.topic) {
      try { const j = await api('bot/topic?id=' + b.dataset.topic); renderReply(j.reply, 0); } catch (x) { /* ignore */ }
      return;
    }
    if (b.dataset.fb) {
      try { await post('bot/feedback', { chat_id: Number(b.dataset.chat), value: Number(b.dataset.fb) }); b.closest('.nxbot-fb').innerHTML = '<small>Thanks for the feedback.</small>' + (b.dataset.fb === '-1' ? '<button type="button" data-a="admin" class="nxbot-esc">Talk to an administrator</button>' : ''); } catch (x) { /* ignore */ }
      return;
    }
    if (b.dataset.act !== undefined) {
      const a = (b.closest('.nxbot-m')._actions || [])[Number(b.dataset.act)];
      if (a && a.type === 'open' && a.url) { record('bot', { target: 'opened ' + a.label }); location.href = a.url; }
    }
  });

  // gentle prompt when something goes wrong (not more than once every 2 minutes)
  let lastOffer = 0;
  function offerHelp(text) {
    if (!panel.hidden || Date.now() - lastOffer < 120000) return;
    lastOffer = Date.now();
    bubble.innerHTML = `<span>${esc(text)} <button type="button">Ask the assistant</button></span><i aria-hidden="true">&times;</i>`;
    bubble.hidden = false;
    setTimeout(() => { bubble.hidden = true; }, 12000);
  }
  bubble.addEventListener('click', (e) => { if (e.target.tagName === 'BUTTON') open(); else bubble.hidden = true; });

  // unread replies from an administrator + the admin's sidebar badge
  const badge = async () => {
    if (document.hidden) return;
    try {
      const j = await api('bot/badge');
      dot.hidden = !(j.user_unread > 0) || !panel.hidden;
      // a live chat with an administrator continues on every page
      if (j.user_live && status !== 'escalated') { opened = true; await loadThread(); }
      if (j.user_unread > 0 && panel.hidden) fab.title = 'You have a reply from an administrator';
      const nb = document.getElementById('nav-brain');
      if (nb) { nb.textContent = j.admin_unread || ''; nb.classList.toggle('hidden', !j.admin_unread); }
    } catch (e) { /* ignore */ }
  };
  setTimeout(badge, 800);
  setInterval(badge, 45000);

  // ------------------------------------------------------------------------- administrators: live chats
  // Only admins see the Brain menu item. An attention card lists the users waiting in a live session; "Open chat"
  // opens the chat named <username>-S<id>. The admin can close it; it also closes after 2 minutes without messages.
  if (!document.getElementById('nav-brain')) return;

  const card = document.createElement('aside');
  card.id = 'nxadm-card'; card.hidden = true; card.setAttribute('role', 'status');
  const win = document.createElement('section');
  win.id = 'nxadm-chat'; win.hidden = true; win.setAttribute('role', 'dialog'); win.setAttribute('aria-label', 'Chat with a user');
  win.innerHTML = `
    <header><div><b id="nxadm-name"></b><small id="nxadm-sub"></small></div>
      <div class="nxbot-hbtns"><button type="button" data-w="end" title="Close this chat for both sides">Close chat</button><button type="button" data-w="hide" aria-label="Hide">&times;</button></div></header>
    <div class="nxbot-live" id="nxadm-state"></div>
    <div class="nxbot-msgs" id="nxadm-msgs" aria-live="polite"></div>
    <form id="nxadm-form" autocomplete="off"><input id="nxadm-input" type="text" maxlength="1000" placeholder="Reply to the user…" aria-label="Your reply"><button type="submit">Send</button></form>`;
  document.body.append(card, win);

  const wMsgs = win.querySelector('#nxadm-msgs');
  const wInput = win.querySelector('#nxadm-input');
  let cur = null;          // open chat: { id, last, timer, status }
  let dismissed = '';      // what the admin dismissed; the card comes back when something changes
  const seenSessions = new Set();
  const nb = document.getElementById('nav-brain');
  const mmss = (s) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;

  const liveTick = async () => {
    if (document.hidden) return;
    let j;
    try { j = await api('brain/live'); } catch (e) { return; }
    const need = j.sessions.filter((s) => !s.admin_user || s.admin_unread > 0);
    if (nb) { nb.textContent = need.length || ''; nb.classList.toggle('hidden', !need.length); }
    j.sessions.forEach((s) => {
      if (!seenSessions.has(s.id)) {
        seenSessions.add(s.id);
        if (!s.admin_user && 'Notification' in window && Notification.permission === 'granted') {
          try { new Notification('NEXUS-L: an administrator is needed', { body: `${s.user_name || s.username} (${s.code})`, tag: 'nxsess-' + s.id }); } catch (e) { /* ignore */ }
        }
      }
    });
    const key = need.map((s) => s.id + ':' + s.admin_unread).join(',');
    const show = need.filter((s) => !(cur && cur.id === s.id && !win.hidden));
    card.hidden = !show.length || key === dismissed;
    if (card.hidden) return;
    card.dataset.key = key;
    card.innerHTML = `<header><b>Needs admin attention</b><button type="button" data-c="dismiss" aria-label="Hide">&times;</button></header>` +
      show.map((s) => `<div class="nxadm-item"><div><b>${esc(s.user_name || s.username)}</b> <small>${esc(s.code)}</small>
        <p>${esc(s.topic || 'Talk to an administrator')}</p>
        <small>${s.admin_user ? `${s.admin_unread} new message${s.admin_unread === 1 ? '' : 's'} · ${esc(s.admin_name || s.admin_user)}` : 'Waiting for an administrator'} · closes in ${mmss(s.seconds_left)}</small></div>
        <button type="button" data-open="${s.id}">Open chat</button></div>`).join('');
  };
  card.addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    if (b.dataset.c === 'dismiss') { dismissed = card.dataset.key || ''; card.hidden = true; return; }
    if (b.dataset.open) openChat(Number(b.dataset.open));
  });

  const wRow = (m) => {
    if (wMsgs.querySelector(`[data-id="${m.id}"]`)) return;
    const d = document.createElement('div');
    d.dataset.id = m.id;
    if (m.sender === 'system') { d.className = 'nxbot-m sys'; d.innerHTML = `<span>${esc(m.message)}</span>`; }
    else {
      // in this window the admin's own lines are on the right, the user's on the left
      d.className = 'nxbot-m ' + (m.sender === 'admin' ? 'user' : (m.sender === 'user' ? 'admin' : 'bot')) + (m.earlier ? ' earlier' : '');
      d.innerHTML = `<small>${esc(m.sender_name || m.sender)} · ${esc(String(m.created_at || '').slice(11, 16))}</small><p>${esc(m.message).replace(/\n/g, '<br>')}</p>`;
    }
    wMsgs.appendChild(d);
    wMsgs.scrollTop = wMsgs.scrollHeight;
  };
  const wState = (s) => {
    cur.status = s.status;
    win.querySelector('#nxadm-name').textContent = s.code;
    win.querySelector('#nxadm-sub').textContent = `${s.user_name || s.username}${s.page ? ' · on ' + s.page : ''}`;
    const open = s.status === 'open';
    win.classList.toggle('closed', !open);
    win.querySelector('[data-w=end]').hidden = !open;
    wInput.disabled = !open; win.querySelector('#nxadm-form button').disabled = !open;
    wInput.placeholder = open ? 'Reply to the user…' : 'This chat is closed.';
    win.querySelector('#nxadm-state').innerHTML = open
      ? `Live chat · closes after 2 minutes without messages <small>(in ${mmss(s.seconds_left)})</small>`
      : `Closed ${s.closed_by === 'timeout' ? 'after 2 minutes without messages' : 'by ' + esc(s.closed_by || 'an administrator')}. Nobody can write into it any more.`;
  };
  const pullChat = async () => {
    if (!cur) return;
    try {
      const j = await api(`brain/session/${cur.id}?after=${cur.last}`);
      j.messages.forEach((m) => { wRow(m); if (!m.earlier) cur.last = Math.max(cur.last, Number(m.id)); });
      wState(j.session);
      if (j.session.status !== 'open') { clearInterval(cur.timer); cur.timer = null; }
    } catch (e) { if (e.status === 404) { clearInterval(cur.timer); } }
  };
  const openChat = async (id) => {
    if (cur && cur.timer) clearInterval(cur.timer);
    cur = { id, last: 0, timer: null, status: '' };
    wMsgs.innerHTML = '';
    win.hidden = false; card.hidden = true;
    await pullChat();
    if (cur.status === 'open') cur.timer = setInterval(() => { if (!document.hidden) pullChat(); }, 2500);
    wInput.focus();
  };
  window.nxAdminChat = openChat;

  win.querySelector('#nxadm-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = wInput.value.trim();
    if (!text || !cur) return;
    wInput.value = '';
    try { await post('brain/reply', { session_id: cur.id, message: text }); await pullChat(); }
    catch (x) { wInput.value = text; win.querySelector('#nxadm-state').textContent = x.message; pullChat(); }
  });
  win.addEventListener('click', async (e) => {
    const b = e.target.closest('button');
    if (!b || !cur) return;
    if (b.dataset.w === 'hide') { win.hidden = true; liveTick(); return; }
    if (b.dataset.w === 'end') {
      if (!confirm(`Close the chat ${win.querySelector('#nxadm-name').textContent}? Neither you nor the user can write into it after this.`)) return;
      try { await post('brain/session/close', { session_id: cur.id }); } catch (x) { /* shown by the poll */ }
      await pullChat();
      liveTick();
    }
  });

  // link from the Synology Chat message: <any page>?nxsession=<id>
  const deep = Number(new URLSearchParams(location.search).get('nxsession') || 0);
  if (deep) openChat(deep);
  setTimeout(liveTick, 1200);
  setInterval(liveTick, 4000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) liveTick(); });
})();
