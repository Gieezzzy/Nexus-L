/** Brain console (admin). */
(() => {
  const root = document.getElementById('br-overview');
  if (!root) return;
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const api = (path, opts = {}) => fetch(BASE + path, opts).then((r) => r.json().then((j) => {
    if (j.csrf) csrfMeta.content = j.csrf;
    if (!r.ok || j.ok === false) throw new Error(j.error || 'Request failed');
    return j;
  }));
  const post = (path, body) => api(path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta.content }, body: JSON.stringify(body || {}) });

  // ---------------------------------------------------------------- tabs
  const loaders = {};
  const show = (tab) => {
    $$('.br-tab').forEach((b) => b.classList.toggle('active', b.dataset.tab === tab));
    $$('.br-pane').forEach((p) => { p.hidden = p.id !== 'br-' + tab; });
    if (loaders[tab]) loaders[tab]();
  };
  $$('.br-tab').forEach((b) => b.addEventListener('click', () => show(b.dataset.tab)));

  // ------------------------------------------------------------ overview
  loaders.overview = async () => {
    try {
      const o = await api('brain/overview');
      const card = (t, v, sub) => `<div class="card glass br-card"><small>${t}</small><b>${v}</b><span class="muted sm">${sub || ''}</span></div>`;
      $('#br-cards').innerHTML =
        card('Knowledge', o.knowledge.approved, `${o.knowledge.draft} drafts`) +
        card('Chats with admins', o.threads.open, `${o.threads.total} conversations in total`) +
        card('Unanswered', o.unanswered, 'questions to teach') +
        card('Problems', o.issues, `${o.errors7d} errors in 7 days`) +
        card('Activity (7 days)', o.events7d, 'recorded actions') +
        card('Answered by the bot', o.answerRate === null ? '-' : o.answerRate + '%', 'of questions, last 30 days');
      $('#br-pages').innerHTML = o.topPages.length ? '<ol>' + o.topPages.map((p) => `<li>${esc(p.page)} <b>${p.c}</b></li>`).join('') + '</ol>' : 'No activity recorded yet.';
      $('#br-n-chats').textContent = o.threads.open || ''; $('#br-n-un').textContent = o.unanswered || ''; $('#br-n-iss').textContent = o.issues || '';
    } catch (e) { $('#br-pages').textContent = e.message; }
  };

  // ---------------------------------------------------------------- chats
  let curThread = 0;
  loaders.chats = async () => {
    const st = $('#br-chat-status').value;
    try {
      const j = await api('brain/threads?status=' + encodeURIComponent(st));
      $('#br-threads').innerHTML = j.rows.map((t) => `<li data-t="${t.id}" class="${t.id === curThread ? 'act' : ''}"><b>${esc(t.username || 'user')}</b> ${t.admin_unread > 0 ? '<span class="br-n">new</span>' : ''}<br><span class="muted sm">${esc(t.topic || '')}</span><br><small>${esc(t.status)} · ${esc(t.updated_at)}</small></li>`).join('') || '<li class="muted">No conversations.</li>';
    } catch (e) { $('#br-threads').innerHTML = `<li>${esc(e.message)}</li>`; }
  };
  $('#br-chat-status').addEventListener('change', loaders.chats);
  const openThread = async (id) => {
    curThread = id;
    const j = await api('brain/thread/' + id);
    const t = j.thread;
    $('#br-thread-box').innerHTML = `
      <h4>${esc(t.username)} <small class="muted">${esc(t.status)}</small></h4>
      <div class="br-msgs">${j.messages.map((m) => `<div class="br-m ${esc(m.sender)}"><small>${esc(m.sender_name || m.sender)} · ${esc(m.created_at)}</small><p>${esc(m.message).replace(/\n/g, '<br>')}</p></div>`).join('')}</div>
      <details><summary>What the user did just before (${j.events.length})</summary><ul class="br-ev">${j.events.map((e) => `<li><small>${esc(e.created_at)}</small> ${esc(e.event_type)} · ${esc(e.target || e.page)} ${e.status ? '(' + e.status + ')' : ''} ${esc(e.detail || '')}</li>`).join('')}</ul></details>
      <textarea id="br-reply" rows="3" placeholder="Write your reply to ${esc(t.username)}…"></textarea>
      <div class="br-row"><button type="button" class="dsg-btn dsg-primary" id="br-send">Send reply</button><button type="button" class="dsg-btn" id="br-resolve">Resolve and teach the assistant…</button></div>`;
    loaders.chats();
  };
  $('#br-threads').addEventListener('click', (e) => { const li = e.target.closest('[data-t]'); if (li) openThread(Number(li.dataset.t)); });
  $('#br-thread-box').addEventListener('click', async (e) => {
    if (e.target.id === 'br-send') {
      const m = $('#br-reply').value.trim(); if (!m) return;
      await post('brain/reply', { thread_id: curThread, message: m }); openThread(curThread);
    }
    if (e.target.id === 'br-resolve') {
      const d = await api('brain/learn-draft?thread_id=' + curThread);
      loadTeach(d.draft, 'Teach from this conversation, then resolve');
      $('#br-t-msg').dataset.resolve = String(curThread);
      show('teach');
    }
  });

  // ------------------------------------------------------------- teach form
  const readTeach = () => ({
    id: Number($('#br-t-id').value) || undefined, title: $('#br-t-title').value, keywords: $('#br-t-kw').value, body: $('#br-t-body').value, steps: $('#br-t-steps').value,
    page_match: $('#br-t-page').value, role_min: $('#br-t-role').value,
    actions: $('#br-t-open').value.trim() ? [{ type: 'open', label: 'Open the page', url: $('#br-t-open').value.trim() }] : [],
  });
  const loadTeach = (k, title) => {
    $('#br-teach-title').textContent = title || 'Teach one answer';
    $('#br-t-id').value = k.id || ''; $('#br-t-title').value = k.title || ''; $('#br-t-kw').value = k.keywords || ''; $('#br-t-body').value = k.body || '';
    const steps = Array.isArray(k.steps) ? k.steps : (() => { try { return JSON.parse(k.steps_json || '[]'); } catch (e) { return []; } })();
    $('#br-t-steps').value = steps.join('\n'); $('#br-t-page').value = k.page_match || ''; $('#br-t-role').value = k.role_min || 'user';
    $('#br-t-open').value = (() => { try { return (JSON.parse(k.actions_json || '[]')[0] || {}).url || ''; } catch (e) { return ''; } })();
    $('#br-t-msg').textContent = '';
  };
  $('#br-t-clear').onclick = () => { loadTeach({}); delete $('#br-t-msg').dataset.resolve; delete $('#br-t-msg').dataset.unanswered; delete $('#br-t-msg').dataset.issue; };
  $('#br-t-save').onclick = async () => {
    const m = $('#br-t-msg');
    try {
      const k = readTeach();
      if (m.dataset.resolve) { await post('brain/resolve', { thread_id: Number(m.dataset.resolve), learn: k }); delete m.dataset.resolve; }
      else if (m.dataset.unanswered) { await post('brain/unanswered/answer', Object.assign({ id: Number(m.dataset.unanswered) }, k)); delete m.dataset.unanswered; }
      else if (m.dataset.issue) { await post('brain/issues/resolve', { id: Number(m.dataset.issue), learn: k }); delete m.dataset.issue; }
      else await post('brain/knowledge/save', k);
      m.textContent = 'Saved. The assistant knows this now.'; loadTeach({});
    } catch (e) { m.textContent = e.message; }
  };
  $('#br-i-go').onclick = async () => {
    try { const j = await post('brain/import', { format: $('#br-i-fmt').value, content: $('#br-i-body').value }); $('#br-i-msg').textContent = `Added ${j.added} of ${j.seen} (duplicates skipped).`; } catch (e) { $('#br-i-msg').textContent = e.message; }
  };

  // ---------------------------------------------------------- unanswered + issues
  loaders.unanswered = async () => {
    const j = await api('brain/unanswered');
    $('#br-un-list').innerHTML = j.rows.map((r) => `<li><b>${esc(r.sample)}</b> <span class="br-n">${r.times}×</span><br><small class="muted">${esc(r.page || '')} · last ${esc(r.last_seen)}</small><div class="br-row"><button type="button" class="dsg-mini" data-ans="${r.id}" data-s="${esc(r.sample)}">Answer</button><button type="button" class="dsg-mini" data-ign="${r.id}">Ignore</button></div></li>`).join('') || '<li class="muted">Nothing waiting. 🎉</li>';
  };
  $('#br-un-list').addEventListener('click', async (e) => {
    const b = e.target;
    if (b.dataset.ans) { loadTeach({ title: b.dataset.s, keywords: '' }, 'Answer this question'); $('#br-t-msg').dataset.unanswered = b.dataset.ans; show('teach'); }
    if (b.dataset.ign) { await post('brain/unanswered/answer', { id: Number(b.dataset.ign), ignore: true }); loaders.unanswered(); }
  });
  loaders.issues = async () => {
    const j = await api('brain/issues');
    $('#br-iss-list').innerHTML = j.rows.map((r) => `<li><b>${esc(r.summary)}</b> <span class="br-n">${r.times}×</span> <span class="br-n">${r.users} user(s)</span><br><small class="muted">${esc(r.page || '')} · last ${esc(r.last_seen)}</small><div class="br-row"><button type="button" class="dsg-mini" data-fix="${r.id}" data-s="${esc(r.summary)}">Write the fix</button><button type="button" class="dsg-mini" data-iign="${r.id}">Ignore</button></div></li>`).join('') || '<li class="muted">No repeated problems.</li>';
  };
  $('#br-iss-list').addEventListener('click', async (e) => {
    const b = e.target;
    if (b.dataset.fix) { loadTeach({ title: 'Problem: ' + b.dataset.s, keywords: '' }, 'Write the fix for this problem'); $('#br-t-msg').dataset.issue = b.dataset.fix; show('teach'); }
    if (b.dataset.iign) { await post('brain/issues/resolve', { id: Number(b.dataset.iign), ignore: true }); loaders.issues(); }
  });

  // ------------------------------------------------------------- knowledge
  let kTimer = null;
  loaders.knowledge = async () => {
    const j = await api(`brain/knowledge?q=${encodeURIComponent($('#br-k-q').value)}&status=${encodeURIComponent($('#br-k-status').value)}`);
    $('#br-k-rows').innerHTML = j.rows.map((r) => `<tr><td>${esc(r.title)}</td><td>${esc(r.kind)}</td><td>${esc(r.source)}</td><td>${r.hits}</td><td>${r.helpful}</td><td>${esc(r.status)}</td><td><button class="dsg-mini" data-edit='${esc(JSON.stringify(r))}'>Edit</button> <button class="dsg-mini" data-st="${r.id}:${r.status === 'approved' ? 'retired' : 'approved'}">${r.status === 'approved' ? 'Retire' : 'Approve'}</button></td></tr>`).join('');
  };
  $('#br-k-q').addEventListener('input', () => { clearTimeout(kTimer); kTimer = setTimeout(loaders.knowledge, 300); });
  $('#br-k-status').addEventListener('change', loaders.knowledge);
  $('#br-k-rows').addEventListener('click', async (e) => {
    const b = e.target;
    if (b.dataset.edit) { loadTeach(JSON.parse(b.dataset.edit), 'Edit answer'); show('teach'); }
    if (b.dataset.st) { const [id, st] = b.dataset.st.split(':'); await post('brain/knowledge/status', { id: Number(id), status: st }); loaders.knowledge(); }
  });

  // ---------------------------------------------------------------- health
  loaders.health = async () => {
    const h = await api('brain/health');
    $('#br-h-checks').innerHTML = h.checks.map((c) => `<li class="${c.ok ? 'ok' : 'bad'}"><b>${c.ok ? '✔' : '✖'} ${esc(c.label)}</b><br><span class="muted sm">${esc(c.text)}</span></li>`).join('');
    $('#br-h-dirs').innerHTML = Object.entries(h.dirs).map(([d, s]) => `<div>${esc(d)} — ${s.ok ? (s.writable ? 'writable' : 'NOT writable') : 'missing'}${s.free_gb !== null ? ', ' + s.free_gb + ' GB free' : ''}${h.knowledgeBackModified[d] ? ', knowledge.back updated ' + esc(h.knowledgeBackModified[d]) : ''}</div>`).join('') + `<div>Waiting to be exported: ${h.unflushed}</div>`;
    const e = h.env;
    $('#br-h-env').innerHTML = `PHP ${esc(e.php)} · mode <b>${esc(e.environment)}</b> · compression ${e.deflate === null ? 'unknown' : (e.deflate ? 'on' : '<b>off</b> (enable mod_deflate in Apache)')} · sessions stored in <b>${esc(e.session_handler.split('\\').pop())}</b> · memory ${esc(e.memory_limit)} · max run time ${esc(e.max_execution_time)}s`;
  };
  $('#br-h-flush').onclick = async () => { const j = await post('brain/flush'); $('#br-h-msg').textContent = 'Exported: ' + JSON.stringify(j.result); loaders.health(); };
  $('#br-h-restore').onclick = async () => {
    if (!(await nxConfirm('Rebuild the knowledge from knowledge.back? Newer entries in the database are kept when they are newer than the file.', { okText: 'Restore' }))) return;
    const j = await post('brain/restore'); $('#br-h-msg').textContent = 'Restored ' + j.restored + ' entries.';
  };

  const params = new URLSearchParams(location.search);
  show(['overview', 'chats', 'unanswered', 'issues', 'knowledge', 'teach', 'health'].includes(params.get('tab')) ? params.get('tab') : 'overview');
  loaders.overview();
})();
