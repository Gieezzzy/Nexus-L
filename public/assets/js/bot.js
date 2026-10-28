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
  fab.id = 'nxbot-fab'; fab.type = 'button'; fab.setAttribute('aria-label', 'Open the NEXUS-L assistant'); fab.title = 'Ask the assistant';
  fab.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-help"/></svg><span class="nxbot-dot" hidden></span>';
  const bubble = document.createElement('div');
  bubble.id = 'nxbot-bubble'; bubble.hidden = true;
  const panel = document.createElement('section');
  panel.id = 'nxbot-panel'; panel.hidden = true; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', 'NEXUS-L assistant');
  panel.innerHTML = `
    <header><div><b>NEXUS-L Assistant</b><small>Ask how to do something, or describe a problem</small></div>
      <div class="nxbot-hbtns"><button type="button" data-a="history" title="My recent activity">History</button><button type="button" data-a="close" aria-label="Close assistant">&times;</button></div></header>
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
    return d;
  };

  const renderReply = (r, chatId) => {
    let h = '';
    if (r.title) h += `<b class="nxbot-t">${esc(r.title)}</b>`;
    h += `<p>${esc(r.text).replace(/\n/g, '<br>')}</p>`;
    if (r.steps && r.steps.length) h += '<ol>' + r.steps.map((s) => `<li>${esc(s)}</li>`).join('') + '</ol>';
    if (r.diagnosis && r.diagnosis.text) h += `<div class="nxbot-diag ${r.diagnosis.ok ? 'ok' : 'bad'}"><b>${r.diagnosis.ok ? 'Checked: OK' : 'Checked: problem found'}</b> ${esc(r.diagnosis.text)}</div>`;
    if (r.actions && r.actions.length) h += '<div class="nxbot-acts">' + r.actions.map((a, i) => `<button type="button" data-act="${i}">${esc(a.label)}</button>`).join('') + '</div>';
    if (r.related && r.related.length) h += '<div class="nxbot-rel"><small>Related:</small> ' + r.related.map((x) => `<button type="button" data-topic="${x.id}">${esc(x.title)}</button>`).join('') + '</div>';
    if (chatId) h += `<div class="nxbot-fb"><button type="button" data-fb="1" data-chat="${chatId}">Helpful</button><button type="button" data-fb="-1" data-chat="${chatId}">Not helpful</button>${r.suggest_admin ? '<button type="button" data-a="admin" class="nxbot-esc">Talk to an administrator</button>' : ''}</div>`;
    const d = add('bot', h, chatId);
    d._actions = r.actions || [];
    return d;
  };

  const renderRow = (m) => {
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
      status = j.thread.status;
      j.messages.forEach((m) => { if (!msgs.querySelector(`[data-id="${m.id}"]`)) renderRow(m); lastId = Math.max(lastId, Number(m.id)); });
      panel.classList.toggle('escalated', status === 'escalated');
      panel.querySelector('footer [data-a=admin]').textContent = status === 'escalated' ? 'With an administrator' : 'Talk to an administrator';
      if (status === 'resolved') { threadId = 0; lastId = 0; }
    } catch (e) { /* offline */ }
  };

  const startPolling = () => { clearInterval(pollTimer); pollTimer = setInterval(() => { if (!panel.hidden && !document.hidden) loadThread(); }, 6000); };

  const open = async () => {
    panel.hidden = false; bubble.hidden = true; dot.hidden = true; fab.setAttribute('aria-expanded', 'true');
    if (!opened) {
      opened = true;
      await loadThread();
      if (!msgs.children.length) {
        add('bot', '<p>Hello! I am the NEXUS-L assistant. Ask how to do something in this system, or describe a problem and I will help you fix it.</p>');
        try {
          const s = await api('bot/suggest?page=' + encodeURIComponent(pagePath()));
          chips.innerHTML = (s.items || []).map((t) => `<button type="button" data-q="${esc(t)}">${esc(t)}</button>`).join('');
        } catch (e) { /* none */ }
      }
    }
    startPolling();
    setTimeout(() => input.focus(), 50);
  };
  const close = () => { panel.hidden = true; fab.setAttribute('aria-expanded', 'false'); fab.focus(); };
  fab.addEventListener('click', () => (panel.hidden ? open() : close()));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) close(); });

  const ask = async (text) => {
    text = text.trim();
    if (!text) return;
    chips.innerHTML = '';
    busy = true;
    const mine = add('user', `<p>${esc(text)}</p>`);
    const wait = add('bot typing', '<p>Thinking…</p>');
    try {
      const j = await post('bot/ask', { message: text, thread_id: threadId, page: pagePath() });
      threadId = j.thread_id;
      if (j.user_chat_id) { mine.dataset.id = j.user_chat_id; lastId = Math.max(lastId, Number(j.user_chat_id)); }
      wait.remove();
      if (j.escalated) { status = 'escalated'; panel.classList.add('escalated'); add('bot', '<p>Your message was sent to the administrator.</p>'); }
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
      if (status === 'escalated') { add('bot', '<p>This chat is already with an administrator. Write your message below; they will reply here.</p>'); return; }
      if (!threadId) { add('bot', '<p>Write your question first, then press the button again, so the administrator knows what you need.</p>'); return; }
      try { await post('bot/escalate', { thread_id: threadId }); status = 'escalated'; panel.classList.add('escalated'); loadThread(); } catch (x) { add('bot', '<p>Could not reach an administrator right now.</p>'); }
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
      if (j.user_unread > 0 && panel.hidden) fab.title = 'You have a reply from an administrator';
      const nb = document.getElementById('nav-brain');
      if (nb) { nb.textContent = j.admin_unread || ''; nb.classList.toggle('hidden', !j.admin_unread); }
    } catch (e) { /* ignore */ }
  };
  setTimeout(badge, 2500);
  setInterval(badge, 45000);
})();
