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
      const j = await api('settings/service/test', { url: $('#st-interlink-service-url').value, key: $('#st-interlink-service-key').value });
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
