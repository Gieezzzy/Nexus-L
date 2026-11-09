(() => {
  'use strict';
  const root = document.getElementById('su-root');
  if (!root) return;
  const $ = (s, c = root) => c.querySelector(s);
  const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const base = (document.querySelector('meta[name="base-url"]') || {}).content || '/';
  const call = (path, body) => fetch(base + path, body === undefined
    ? { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
    : { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: JSON.stringify(body) })
    .then((r) => r.json().then((j) => {
      if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
      if (!r.ok || j.ok === false) throw new Error(j.error || 'Request failed');
      return j;
    }));
  const note = (text, ok = true) => { const e = $('#su-note'); e.textContent = text; e.className = 'su-note ' + (ok ? 'ok' : 'err'); };
  // the access grid is far below the top note: its own message sits beside the Save button
  const mnote = (text, ok = true) => { const e = $('#su-mnote'); if (!e) return note(text, ok); e.textContent = text; e.className = 'su-mnote ' + (ok ? 'ok' : 'err'); };
  const COLOR = { Admin: '#b91c1c', Manager: '#1d4ed8', Supervisor: '#0f766e', Encoding: '#b45309', Materials: '#15803d', Bonding: '#9a3412', Technician: '#475569' };
  const hue = (s) => { let h = 0; for (const c of String(s)) h = (h * 31 + c.charCodeAt(0)) % 360; return h; };
  const initials = (n) => String(n).split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('');
  let ROLES = []; let USERS = []; let editing = null;

  // ---------------------------------------------------------------- the list
  const stats = () => {
    const act = USERS.filter((u) => u.active).length;
    const adm = USERS.filter((u) => u.active && u.roles.includes('Admin')).length;
    const none = USERS.filter((u) => u.legacy && u.active).length;
    $('#su-stats').innerHTML = [['Users', USERS.length, ''], ['Active', act, ''], ['Admins', adm, ''], ['No role yet', none, none ? 'warn' : '']]
      .map(([l, n, c]) => `<div class="su-stat ${c}"><b>${n}</b><span>${l}</span></div>`).join('');
  };
  const render = () => {
    const q = $('#su-q').value.trim().toLowerCase(); const fr = $('#su-frole').value; const fs = $('#su-fstat').value;
    const list = USERS.filter((u) => (!q || (u.name + ' ' + u.username + ' ' + u.email + ' ' + u.department + ' ' + u.job_title + ' ' + u.roles.join(' ')).toLowerCase().includes(q))
      && (!fr || u.roles.includes(fr)) && (!fs || (fs === 'active' ? u.active : fs === 'disabled' ? !u.active : u.legacy)));
    const head = '<div class="su-row su-th" role="row"><span>Person</span><span>Contact</span><span>Roles</span><span>Status</span><span>Last sign-in</span><span></span></div>';
    $('#su-list').innerHTML = head + (list.map((u) => `
      <div class="su-row" role="row" data-id="${u.id}">
        <div class="su-person"><span class="su-av" style="background:hsl(${hue(u.username)} 38% 38%)" aria-hidden="true">${esc(initials(u.name))}</span><span class="su-nm"><b>${esc(u.name)}</b><span class="su-sub">@${esc(u.username)}</span></span></div>
        <div class="su-contact"><span class="su-sub">${esc(u.email || 'no e-mail')}</span><span class="su-sub">${esc([u.department, u.job_title].filter(Boolean).join(' · ') || 'no department')}</span></div>
        <div class="su-chips">${u.roles.length ? u.roles.map((r) => `<span class="su-chip"><i style="--c:${COLOR[r] || '#64748b'}"></i>${esc(r)}</span>`).join('') : '<span class="su-chip none">No role yet (old open access)</span>'}</div>
        <div><span class="su-st ${!u.active ? 'off' : u.locked ? 'lock' : ''}">${!u.active ? 'Disabled' : u.locked ? 'Locked' : 'Active'}</span></div>
        <div class="su-last">${u.last_login ? esc(String(u.last_login).slice(0, 16)) : 'never'}</div>
        <button type="button" class="nx-btn su-edit" aria-label="Edit ${esc(u.name)}">Edit</button>
      </div>`).join('') || '<div class="su-empty">No user matches the search or filter.</div>');
    stats();
  };

  const load = async () => {
    try {
      const j = await call('settings/users'); ROLES = j.roles; USERS = j.users;
      if ($('#su-frole').options.length === 1) ROLES.forEach((r) => { $('#su-frole').insertAdjacentHTML('beforeend', `<option>${esc(r)}</option>`); });
      render();
    } catch (e) { note(e.message, false); }
  };
  ['#su-q', '#su-frole', '#su-fstat'].forEach((i) => $(i).addEventListener('input', render));

  // ---------------------------------------------------------------- the dialog (edit and new)
  const dlg = $('#su-dlg');
  const result = (html, err) => { const r = $('#d-result'); r.hidden = !html; r.className = 'su-result' + (err ? ' err' : ''); r.innerHTML = html || ''; };
  const open = (u) => {
    editing = u; result('');
    $('#su-dtitle').textContent = u ? 'Edit ' + u.name : 'New user';
    $('#d-username').value = u ? u.username : ''; $('#d-username').disabled = !!u;
    $('#d-name').value = u ? u.name : ''; $('#d-email').value = u ? u.email : ''; $('#d-dept').value = u ? u.department : ''; $('#d-job').value = u ? u.job_title : '';
    $('#d-syn').value = u && u.synology_id ? u.synology_id : ''; $('#d-syn').disabled = !u;
    $('#d-roles').innerHTML = ROLES.map((r) => `<label><input type="checkbox" value="${esc(r)}" ${u && u.roles.includes(r) ? 'checked' : ''}> ${esc(r)}</label>`).join('');
    $('#d-active').checked = u ? u.active : true; $('#d-activewrap').hidden = !u;
    $('#d-reset').hidden = !u; $('#d-save').textContent = u ? 'Save' : 'Create account';
    dlg.showModal(); $('#d-name').focus();
  };
  const close = () => dlg.close();
  $('#su-add').addEventListener('click', () => open(null));
  $('#su-list').addEventListener('click', (e) => { const b = e.target.closest('.su-edit'); if (b) open(USERS.find((u) => u.id === Number(b.closest('[data-id]').dataset.id))); });
  $('#su-dclose').addEventListener('click', close); $('#d-cancel').addEventListener('click', close);
  dlg.addEventListener('click', (e) => { if (e.target === dlg) close(); });

  $('#d-save').addEventListener('click', async (e) => {
    const btn = e.currentTarget; btn.disabled = true; result('');
    const roles = [...dlg.querySelectorAll('#d-roles input:checked')].map((c) => c.value);
    try {
      if (editing) {
        const j = await call('settings/users/save', { id: editing.id, name: $('#d-name').value, email: $('#d-email').value, department: $('#d-dept').value, job_title: $('#d-job').value, synology_id: $('#d-syn').value, roles, active: $('#d-active').checked });
        USERS = USERS.map((u) => (u.id === editing.id ? j.user : u)); render(); note(j.message); close();
      } else {
        const j = await call('settings/users/create', { username: $('#d-username').value, name: $('#d-name').value, email: $('#d-email').value, department: $('#d-dept').value, job_title: $('#d-job').value, roles });
        USERS.push(j.user); render(); note(j.message);
        editing = j.user; $('#su-dtitle').textContent = 'Account created'; $('#d-save').hidden = true;
        result(`Temporary password for <b>${esc(j.user.username)}</b> (shown once): <code>${esc(j.temp_password)}</code> Give it to the person; they change it under Account Profile.`);
      }
    } catch (err) { result(esc(err.message), true); } finally { btn.disabled = false; }
  });
  $('#d-reset').addEventListener('click', async () => {
    if (!editing || !confirm('Make a new temporary password for ' + editing.name + '? Their open sessions are signed out.')) return;
    try {
      const j = await call('settings/users/reset', { id: editing.id });
      result(`New temporary password for <b>${esc(editing.username)}</b> (shown once): <code>${esc(j.temp_password)}</code> The account is unlocked.`);
    } catch (err) { result(esc(err.message), true); }
  });
  dlg.addEventListener('close', () => { $('#d-save').hidden = false; });

  // ---------------------------------------------------------------- the access grid
  let M = null;
  const NAME = ['None', 'View', 'Edit'];
  const draw = () => {
    const roles = Object.keys(M.defaults);
    $('#su-matrix').innerHTML = `<thead><tr><th scope="col">Part of the system</th><th scope="col">Admin</th>${roles.map((r) => `<th scope="col">${esc(r)}</th>`).join('')}</tr></thead><tbody>${
      Object.entries(M.modules).map(([k, label]) => {
        const lock = M.admin_only.includes(k);
        return `<tr><th scope="row">${esc(label)}</th><td><span class="su-pill l2">Edit</span></td>${roles.map((r) => lock
          ? '<td><span class="su-lock">Admin only</span></td>'
          : `<td><button type="button" class="su-pill l${M.matrix[r][k] || 0}" data-r="${esc(r)}" data-m="${esc(k)}" aria-label="${esc(label)} for ${esc(r)}: ${NAME[M.matrix[r][k] || 0]}. Press to change">${NAME[M.matrix[r][k] || 0]}</button></td>`).join('')}</tr>`;
      }).join('')}</tbody>`;
  };
  const loadM = async () => { try { M = await call('settings/roles/matrix'); draw(); } catch (e) { note(e.message, false); } };
  $('#su-matrix').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-r]'); if (!b) return;
    const r = b.dataset.r; const m = b.dataset.m; M.matrix[r][m] = ((M.matrix[r][m] || 0) + 1) % 3; draw(); mnote('Not saved yet: press Save the access.', false);
    const nb = $(`button[data-r="${r}"][data-m="${m}"]`); if (nb) nb.focus();
  });
  $('#su-msave').addEventListener('click', async () => {
    const btn = $('#su-msave'); btn.disabled = true; mnote('Saving...');
    try { const j = await call('settings/roles/matrix', { matrix: M.matrix }); M.matrix = j.matrix; draw(); mnote('Saved. ' + j.message); }
    catch (e) { mnote('Not saved: ' + e.message + ' (reload the page and try again)', false); }
    finally { btn.disabled = false; }
  });
  $('#su-mreset').addEventListener('click', async () => {
    if (!confirm('Put every role back to the default access?')) return;
    try { const j = await call('settings/roles/matrix', { reset: true }); M.matrix = j.matrix; draw(); mnote(j.message); } catch (e) { mnote(e.message, false); }
  });

  load(); loadM();
})();
