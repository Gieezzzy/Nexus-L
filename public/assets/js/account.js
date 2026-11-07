(() => {
  'use strict';
  const root = document.getElementById('ac-root');
  if (!root) return;
  const $ = (s, c = document) => c.querySelector(s);
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const base = (document.querySelector('meta[name="base-url"]') || {}).content || '/';
  const post = (path, body) => fetch(base + path, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' },
    body: JSON.stringify(body || {}),
  }).then((r) => r.json().then((j) => {
    if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
    if (!r.ok || j.ok === false) throw new Error(j.error || j.message || 'Could not save.');
    return j;
  }));
  const say = (id, text, ok) => { const e = $('#' + id); if (e) { e.textContent = text; e.className = 'ac-msg ' + (ok ? 'ok' : 'err'); } };
  const run = async (btn, msgId, fn) => {
    btn.disabled = true; say(msgId, 'Saving...', true);
    try { const j = await fn(); say(msgId, j.message || 'Saved.', true); return j; } catch (e) { say(msgId, e.message, false); } finally { btn.disabled = false; }
    return null;
  };

  // tabs (the open one is kept in the address as ?tab=)
  const show = (tab) => {
    const names = [...root.querySelectorAll('.ac-pane')].map((p) => p.dataset.pane);
    if (!names.includes(tab)) tab = 'profile';
    root.querySelectorAll('.ac-pane').forEach((p) => p.classList.toggle('on', p.dataset.pane === tab));
    root.querySelectorAll('.ac-tabs button').forEach((b) => b.classList.toggle('on', b.dataset.tab === tab));
    try { history.replaceState(null, '', base + 'account?tab=' + tab); } catch (e) { /* ignore */ }
  };
  root.querySelector('.ac-tabs').addEventListener('click', (e) => { const b = e.target.closest('button[data-tab]'); if (b) show(b.dataset.tab); });
  show(root.dataset.tab);

  // profile
  $('#p-save').addEventListener('click', (e) => run(e.currentTarget, 'p-msg', () => post('account/save', {
    FullName: $('#p-FullName').value, Email: $('#p-Email').value, Phone: $('#p-Phone').value,
    Department: $('#p-Department').value, JobTitle: $('#p-JobTitle').value, EmployeeNo: $('#p-EmployeeNo').value,
  })));

  // password
  const strength = (p) => {
    let s = 0;
    if (p.length >= Number(root.dataset.min || 8)) s++;
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
    if (/\d/.test(p)) s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    if (p.length >= 14) s++;
    return s;
  };
  $('#s-new').addEventListener('input', () => {
    const p = $('#s-new').value; const s = p ? strength(p) : 0;
    const bar = $('#s-bar'); bar.style.width = (s * 20) + '%'; bar.style.background = s <= 2 ? '#ef4444' : (s <= 3 ? '#f59e0b' : '#16a34a');
    $('#s-hint').textContent = !p ? 'Type a new password to see its strength.' : (s <= 2 ? 'Weak' : (s <= 3 ? 'Fair' : (s === 4 ? 'Good' : 'Strong')));
  });
  $('#s-save').addEventListener('click', async (e) => {
    const j = await run(e.currentTarget, 's-msg', () => post('account/password', { current: $('#s-current').value, password: $('#s-new').value, confirm: $('#s-confirm').value }));
    if (j) { ['#s-current', '#s-new', '#s-confirm'].forEach((i) => { $(i).value = ''; }); $('#s-bar').style.width = '0'; }
  });

  // my settings
  $('#f-save').addEventListener('click', async (e) => {
    const j = await run(e.currentTarget, 'f-msg', () => post('account/prefs', {
      landing: $('#f-landing').value, sidebar: $('#f-sidebar').value, page_size: Number($('#f-page').value),
      notify_chat: $('#f-chat').checked, notify_approval: $('#f-appr').checked, toasts: $('#f-toast').checked,
    }));
    if (j) { try { localStorage.setItem('nx.side', $('#f-sidebar').value === 'collapsed' ? '1' : '0'); localStorage.setItem('nx.toasts', $('#f-toast').checked ? '1' : '0'); } catch (x) { /* private mode */ } }
  });

  // signature (Manager, Supervisor, Admin)
  const cv = $('#g-canvas');
  if (cv) {
    const ctx = cv.getContext('2d'); let draw = false; let dirty = false;
    ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.strokeStyle = '#111827';
    const pos = (e) => { const r = cv.getBoundingClientRect(); return [(e.clientX - r.left) * cv.width / r.width, (e.clientY - r.top) * cv.height / r.height]; };
    cv.addEventListener('pointerdown', (e) => { draw = true; const [x, y] = pos(e); ctx.beginPath(); ctx.moveTo(x, y); cv.setPointerCapture(e.pointerId); });
    cv.addEventListener('pointermove', (e) => { if (!draw) return; const [x, y] = pos(e); ctx.lineTo(x, y); ctx.stroke(); dirty = true; });
    ['pointerup', 'pointercancel'].forEach((ev) => cv.addEventListener(ev, () => { draw = false; }));
    $('#g-clear').addEventListener('click', () => { ctx.clearRect(0, 0, cv.width, cv.height); dirty = false; });
    $('#g-save').addEventListener('click', async (e) => {
      if (!dirty) { say('g-msg', 'Draw your signature first.', false); return; }
      const j = await run(e.currentTarget, 'g-msg', () => post('account/signature', { png: cv.toDataURL('image/png') }));
      if (j) setTimeout(() => location.reload(), 700);
    });
    const rm = $('#g-remove');
    if (rm) rm.addEventListener('click', async (e) => { const j = await run(e.currentTarget, 'g-msg', () => post('account/signature', { png: '' })); if (j) setTimeout(() => location.reload(), 700); });
  }
})();
