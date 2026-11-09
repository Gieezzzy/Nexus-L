/**
 * Settings page (Admin): saves the setting cards, tests the InterLink service, tests / switches the database and runs the transfer to
 * another database (a background job whose progress is polled).
 */
(() => {
  const root = document.getElementById('st-root');
  if (!root) return;
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || root.dataset.base || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const $ = (s, c = root) => c.querySelector(s);
  const toast = (m, t = 'info') => (window.nxToast ? window.nxToast(m, t) : alert(m));

  const api = async (path, body) => {
    const opt = body === undefined
      ? { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
      : { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: JSON.stringify(body) };
    const r = await fetch(BASE + path, opt);
    const j = await r.json().catch(() => ({}));
    if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
    if (!r.ok || j.ok === false) throw new Error(j.error || `Request failed (${r.status})`);
    return j;
  };
  const say = (el, text, kind) => { if (!el) return; el.textContent = text; el.className = 'st-msg ' + (kind === 'ok' ? 'st-ok' : kind === 'bad' ? 'st-bad' : ''); };

  // ---- the setting cards ------------------------------------------------------------------------------------------------------
  root.querySelectorAll('[data-save]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const card = document.getElementById(btn.dataset.save);
      const msg = card.querySelector('.st-msg');
      const values = {};
      card.querySelectorAll('[data-key]').forEach((el) => { values[el.dataset.key] = el.dataset.type === 'bool' ? el.checked : el.value; });
      btn.disabled = true;
      say(msg, 'Saving...');
      try {
        const j = await api('settings/save', { values });
        say(msg, j.saved ? 'Saved.' : 'Nothing changed.', 'ok');
        card.querySelectorAll('input[type=password]').forEach((el) => { if (el.value) { el.value = ''; el.placeholder = 'saved - type to replace'; } });
      } catch (e) { say(msg, e.message, 'bad'); }
      btn.disabled = false;
    });
  });

  // ---- Brain Trainer app token: show / generate / copy ---------------------------------------------------------------------------
  const tk = $('#st-trainer-token');
  if (tk) {
    const tmsg = $('#st-trainer-msg');
    $('#st-trainer-show').addEventListener('click', async () => {
      try { const j = await api('settings/trainer-token'); tk.value = j.token || ''; say(tmsg, j.token ? '' : 'No token yet - press Generate.', j.token ? '' : 'bad'); } catch (e) { say(tmsg, e.message, 'bad'); }
    });
    $('#st-trainer-gen').addEventListener('click', async () => {
      if (tk.dataset.had !== '0' && !confirm('Generate a new token? The Brain Trainer app stops working until the new token is typed into it.')) return;
      try { const j = await api('settings/trainer-token', {}); tk.value = j.token; tk.dataset.had = '1'; say(tmsg, 'New token generated and saved. Copy it into the app.', 'ok'); } catch (e) { say(tmsg, e.message, 'bad'); }
    });
    $('#st-trainer-copy').addEventListener('click', async () => {
      if (!tk.value) { say(tmsg, 'Press Show or Generate first.', 'bad'); return; }
      try { await navigator.clipboard.writeText(tk.value); } catch (e) { tk.select(); document.execCommand('copy'); }
      say(tmsg, 'Copied.', 'ok');
    });
  }

  // ---- Notifier app token: show / generate / copy -------------------------------------------------------------------------------
  const nk = $('#st-notify-token');
  if (nk) {
    const nmsg = $('#st-notify-msg');
    $('#st-notify-show').addEventListener('click', async () => {
      try { const j = await api('settings/notifier-token'); nk.value = j.token || ''; say(nmsg, j.token ? '' : 'No token yet - press Generate.', j.token ? '' : 'bad'); } catch (e) { say(nmsg, e.message, 'bad'); }
    });
    $('#st-notify-gen').addEventListener('click', async () => {
      if (nk.dataset.had !== '0' && !confirm('Generate a new token? The Notifier app stops working until the new token is typed into it.')) return;
      try { const j = await api('settings/notifier-token', {}); nk.value = j.token; nk.dataset.had = '1'; say(nmsg, 'New token generated and saved. Copy it into the app.', 'ok'); } catch (e) { say(nmsg, e.message, 'bad'); }
    });
    $('#st-notify-copy').addEventListener('click', async () => {
      if (!nk.value) { say(nmsg, 'Press Show or Generate first.', 'bad'); return; }
      try { await navigator.clipboard.writeText(nk.value); } catch (e) { nk.select(); document.execCommand('copy'); }
      say(nmsg, 'Copied.', 'ok');
    });
  }

  // ---- Outlook Relay app token: show / generate / copy --------------------------------------------------------------------------
  const rk = $('#st-relay-token');
  if (rk) {
    const rmsg = $('#st-relay-msg');
    $('#st-relay-show').addEventListener('click', async () => {
      try { const j = await api('settings/relay-token'); rk.value = j.token || ''; say(rmsg, j.token ? '' : 'No token yet - press Generate.', j.token ? '' : 'bad'); } catch (e) { say(rmsg, e.message, 'bad'); }
    });
    $('#st-relay-gen').addEventListener('click', async () => {
      if (rk.dataset.had !== '0' && !confirm('Generate a new token? The Outlook Relay app stops working until the new token is typed into it.')) return;
      try { const j = await api('settings/relay-token', {}); rk.value = j.token; rk.dataset.had = '1'; say(rmsg, 'New token generated and saved. Copy it into the app.', 'ok'); } catch (e) { say(rmsg, e.message, 'bad'); }
    });
    $('#st-relay-copy').addEventListener('click', async () => {
      if (!rk.value) { say(rmsg, 'Press Show or Generate first.', 'bad'); return; }
      try { await navigator.clipboard.writeText(rk.value); } catch (e) { rk.select(); document.execCommand('copy'); }
      say(rmsg, 'Copied.', 'ok');
    });
  }

  // ---- E-mail (Outlook Relay): is it alive, the latest e-mails, retry, test ---------------------------------------------------------
  const mlBox = $('#ml-status');
  if (mlBox) {
    const esc = (v) => String(v == null ? '' : v).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const list = $('#ml-list');
    const load = async () => {
      try {
        const j = await api('settings/mail/status');
        const r = j.relay || {}, i = r.info || {}, c = j.counts || {};
        const dot = r.online ? 'ok' : 'bad';
        mlBox.innerHTML = '<div class="ml-line"><span class="ml-dot ' + dot + '"></span><b>' + (r.online ? 'Outlook Relay is online' : (r.has_token ? 'Outlook Relay is offline' : 'No Relay token yet - press Generate below')) + '</b>'
          + (r.seen_at ? ' <span class="muted">last seen ' + esc(r.seen_at) + '</span>' : '') + '</div>'
          + (r.online && i.account ? '<div class="muted sm">Outlook: ' + esc(i.account) + (i.smtp ? ' &lt;' + esc(i.smtp) + '&gt;' : '') + (i.host ? ' on ' + esc(i.host) : '') + (i.version ? ' (v' + esc(i.version) + ')' : '') + (i.outlook_connected === false ? ' - <span class="st-bad">Outlook not connected</span>' : '') + '</div>' : '')
          + '<div class="ml-counts"><span>Queued <b>' + (c.queued || 0) + '</b></span><span>Sending <b>' + (c.sending || 0) + '</b></span><span>Sent <b>' + (c.sent || 0) + '</b></span><span>Failed <b>' + (c.failed || 0) + '</b></span></div>';
        const rows = j.rows || [];
        list.innerHTML = rows.length ? '<table class="ml-tbl"><thead><tr><th>#</th><th>To</th><th>Subject</th><th>Status</th><th>Tries</th><th>When</th><th></th></tr></thead><tbody>' + rows.map((m) =>
          '<tr><td>' + m.id + '</td><td>' + esc(m.to_address) + '</td><td title="' + esc(m.last_error || '') + '">' + esc(m.subject) + '</td><td><span class="ml-pill ' + esc(m.status) + '">' + esc(m.status) + '</span>' + (m.last_error ? '<div class="ml-err">' + esc(m.last_error).slice(0, 120) + '</div>' : '') + '</td><td>' + m.attempts + '</td><td>' + esc(m.sent_at || m.created_at) + '</td><td>'
          + (m.status === 'failed' || (m.status === 'queued' && m.next_attempt_at) ? '<button type="button" class="nx-btn" data-retry="' + m.id + '">Retry now</button>' : '') + '</td></tr>').join('') + '</tbody></table>' : '<span class="muted sm">No e-mail yet.</span>';
      } catch (e) { mlBox.innerHTML = '<span class="st-bad">' + esc(e.message) + '</span>'; }
    };
    list.addEventListener('click', async (e) => {
      const b = e.target.closest('[data-retry]'); if (!b) return;
      b.disabled = true;
      try { await api('settings/mail/retry', { id: Number(b.dataset.retry) }); } catch (er) { /* shown by the next refresh */ }
      load();
    });
    $('#ml-test').addEventListener('click', async () => {
      const msg = $('#st-mail-msg'), to = $('#ml-test-to').value.trim();
      if (!to) { say(msg, 'Type the address to send the test to.', 'bad'); return; }
      try { const j = await api('settings/mail/test', { to }); say(msg, 'Test e-mail #' + j.id + ' queued. It goes out within a few seconds.', 'ok'); load(); } catch (e) { say(msg, e.message, 'bad'); }
    });
    load();
    setInterval(() => { if (!document.hidden) load(); }, 8000);
  }

  // ---- AI Assistant & Bot Engine test ------------------------------------------------------------------------------------------
  $('#st-ai-test')?.addEventListener('click', async () => {
    const msg = $('#st-ai-msg');
    say(msg, 'Testing AI service...');
    try {
      const urlInput = $('#st-ai-url');
      const keyInput = $('#st-ai-key');
      const j = await api('settings/ai/test', {
        url: urlInput ? urlInput.value : '',
        key: keyInput ? keyInput.value : ''
      });
      if (!j.reachable) {
        say(msg, 'Service unreachable' + (j.error ? ' (' + j.error + ')' : '') + '. Check URL & Python service status.', 'bad');
        return;
      }
      const parts = [
        'Reachable (' + (j.service || 'nexus-ai-service') + ')',
        'status: ' + (j.status || 'ok'),
        j.docs !== undefined ? j.docs + ' indexed docs' : '',
        j.threshold !== null && j.threshold !== undefined ? 'calibrated threshold: ' + j.threshold : '',
        'latency: ' + (j.latency_ms || 0) + 'ms'
      ].filter(Boolean);
      say(msg, parts.join(' · '), 'ok');
    } catch (e) {
      say(msg, e.message, 'bad');
    }
  });

  $('#st-ai-seed')?.addEventListener('click', async () => {
    const msg = $('#st-ai-msg');
    const btn = $('#st-ai-seed');
    btn.disabled = true;
    say(msg, 'Seeding 200 problem questions and re-indexing AI vector store...');
    try {
      const j = await api('settings/ai/seed', {});
      if (j.ok && j.result) {
        say(msg, `Seeded successfully: ${j.result.total} problems (${j.result.inserted} new, ${j.result.updated} updated). Vector store: ${j.result.indexed ? 'Re-indexed' : 'Saved'}.`, 'ok');
        toast('Knowledge base updated with 200 problem solutions!', 'success');
      } else {
        say(msg, 'Seeding failed: ' + (j.error || 'Unknown error'), 'bad');
      }
    } catch (e) {
      say(msg, e.message, 'bad');
    } finally {
      btn.disabled = false;
    }
  });

  $('#st-git-push')?.addEventListener('click', async () => {
    const msg = $('#st-ai-msg');
    const btn = $('#st-git-push');
    btn.disabled = true;
    say(msg, 'Staging, committing, and pushing to Git repository...');
    try {
      const j = await api('settings/git/sync', {});
      if (j.ok) {
        say(msg, 'Pushed successfully to origin/main!', 'ok');
        toast('Codebase synchronized and pushed to GitHub main branch!', 'success');
      } else {
        say(msg, 'Git push output: ' + (j.output || 'Exit code ' + j.code), j.ok ? 'ok' : 'bad');
      }
    } catch (e) {
      say(msg, e.message, 'bad');
    } finally {
      btn.disabled = false;
    }
  });

  // ---- InterLink service test -------------------------------------------------------------------------------------------------
  $('#st-service-test')?.addEventListener('click', async () => {
    const msg = $('#st-service-msg');
    say(msg, 'Testing...');
    try {
      const j = await api('settings/service/test', { url: ({ 43: '#st-interlink-service-url_43', 45: '#st-interlink-service-url_45' }[$('#st-interlink-service-target').value] ? $({ 43: '#st-interlink-service-url_43', 45: '#st-interlink-service-url_45' }[$('#st-interlink-service-target').value]).value : $('#st-interlink-service-url').value), key: $('#st-interlink-service-key').value });
      if (!j.reachable) { say(msg, 'The service did not answer at that address' + (j.error ? ' (' + j.error + ')' : '') + '. Is the program running, and is the port open?', 'bad'); return; }
      const parts = [`Reachable (version ${j.version || '?'})`, j.keyOk ? 'key accepted' : 'the key was REFUSED - it must match the service program', j.signedIn ? 'InterLink sign-in saved' : 'InterLink sign-in NOT done yet', `${j.waiting} waiting`];
      say(msg, parts.join(' · '), j.keyOk && j.signedIn ? 'ok' : 'bad');
    } catch (e) { say(msg, e.message, 'bad'); }
  });

  // ---- database ---------------------------------------------------------------------------------------------------------------
  const conn = (cardId) => {
    const c = {};
    document.getElementById(cardId).querySelectorAll('[data-conn] [data-f]').forEach((el) => { c[el.dataset.f] = el.value; });
    return c;
  };
  $('#st-db-test')?.addEventListener('click', async () => {
    const msg = $('#st-db-msg');
    say(msg, 'Testing...');
    try {
      const c = conn('st-db');
      const j = await api('settings/db/test', c);
      say(msg, `Connected (MySQL ${j.version}). ${j.tables} tables. ${j.note}`, j.hasSchema ? 'ok' : 'bad');
    } catch (e) { say(msg, e.message, 'bad'); }
  });
  const switchTo = async (c, msg) => {
    say(msg, 'Switching...');
    try {
      const j = await api('settings/db/switch', c);
      say(msg, j.message, 'ok');
      toast('The database was switched. NEXUS-L uses it from the next page on.', 'success');
    } catch (e) { say(msg, e.message, 'bad'); }
  };
  $('#st-db-switch')?.addEventListener('click', () => switchTo(conn('st-db'), $('#st-db-msg')));

  // ---- transfer ---------------------------------------------------------------------------------------------------------------
  let timer = null;
  let lastTarget = null;
  const drawProgress = (job) => {
    $('#st-tr-progress').hidden = false;
    const pct = job.tables_total ? Math.round((job.tables_done / job.tables_total) * 100) : 0;
    $('#st-tr-fill').style.width = (job.status === 'done' ? 100 : pct) + '%';
    $('#st-tr-text').textContent = `${job.status}${job.current ? ' - ' + job.current : ''} · tables ${job.tables_done}/${job.tables_total} · ${Number(job.rows_copied || 0).toLocaleString()} rows copied`
      + ((job.errors || []).length ? ` · ${(job.errors || []).length} problem(s)` : '');
    $('#st-tr-log').textContent = [...(job.log || []), ...(job.errors || []).map((e) => 'PROBLEM: ' + e)].join('\n');
    $('#st-tr-log').scrollTop = $('#st-tr-log').scrollHeight;
  };
  const poll = (id) => {
    clearInterval(timer);
    const tick = async () => {
      try {
        const j = await api('settings/transfer/status?id=' + encodeURIComponent(id));
        drawProgress(j.job);
        if (['done', 'finished-with-errors', 'failed'].includes(j.job.status)) {
          clearInterval(timer);
          $('#st-tr-start').disabled = false;
          const ok = j.job.status === 'done';
          say($('#st-tr-msg'), ok ? 'Transfer finished.' : (j.job.status === 'failed' ? 'The transfer failed.' : 'Finished, with problems (see the list).'), ok ? 'ok' : 'bad');
          if (j.job.status !== 'failed') $('#st-tr-use').hidden = false;
        }
      } catch (e) { say($('#st-tr-msg'), e.message, 'bad'); clearInterval(timer); }
    };
    tick();
    timer = setInterval(tick, 2000);
  };
  $('#st-tr-start')?.addEventListener('click', async () => {
    const msg = $('#st-tr-msg');
    const target = conn('st-transfer');
    if (!target.hostname || !target.database || !target.username) { say(msg, 'Fill in the target server, database and user.', 'bad'); return; }
    if (window.nxConfirm && !(await window.nxConfirm(`Copy the whole database into "${target.database}" on ${target.hostname}?`, { okText: 'Start', danger: false }))) return;
    lastTarget = target;
    $('#st-tr-start').disabled = true;
    $('#st-tr-use').hidden = true;
    say(msg, 'Starting...');
    try {
      const j = await api('settings/transfer/start', { target, create_db: $('#st-tr-create').checked, overwrite: $('#st-tr-overwrite').checked });
      say(msg, 'Running in the background.');
      poll(j.id);
    } catch (e) { say(msg, e.message, 'bad'); $('#st-tr-start').disabled = false; }
  });
  $('#st-tr-use')?.addEventListener('click', () => {
    if (!lastTarget) return;
    const word = prompt('Make NEXUS-L use the new database now? Type SWITCH to confirm.', '');
    if (word !== 'SWITCH') return;
    switchTo(Object.assign({}, lastTarget, { confirm: 'SWITCH' }), $('#st-tr-msg'));
  });
})();
