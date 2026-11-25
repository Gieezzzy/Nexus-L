/* Warehouse 3D Tracker: box form (Box Code and Date Range made from the Box Label) and the contents of a box:
   Add Contents (pick Lab References as cards), the contents list with check columns (QIMA, CFA/Loading, ...), and a clickable Box Code. */
(() => {
  'use strict';
  const root = document.getElementById('wh-root');
  if (!root) return;
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  let csrf = root.dataset.csrfHash || '';
  const el = (tag, cls, txt) => { const n = document.createElement(tag); if (cls) n.className = cls; if (txt != null) n.textContent = txt; return n; };
  const toast = (t, k) => (window.nxToast ? window.nxToast(t, k || 'info', 5000) : alert(t));
  const api = (path, body) => fetch(BASE + path, Object.assign({
    credentials: 'same-origin',
    headers: Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : csrf } : {}),
  }, body ? { method: 'POST', body: JSON.stringify(body) } : {})).then((r) => r.json().catch(() => ({})).then((j) => {
    if (j.csrf) { csrf = j.csrf; if (csrfMeta) csrfMeta.content = j.csrf; }
    if (!r.ok || j.ok === false) throw new Error(j.error || j.message || ('Request failed (' + r.status + ')'));
    return j;
  }));

  // ------------------------------------------------------------------ Box Code and Date Range from the Box Label (same rules as WarehouseLabel.php)
  const MONTHS = { JAN: 1, FEB: 2, MAR: 3, APR: 4, MAY: 5, JUN: 6, JUL: 7, AUG: 8, SEP: 9, OCT: 10, NOV: 11, DEC: 12 };
  const codeOf = (label) => String(label || '').toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60);
  const dateOf = (label) => {
    const m = String(label || '').match(/\b(JAN(?:UARY)?|FEB(?:RUARY)?|MAR(?:CH)?|APR(?:IL)?|MAY|JUNE?|JULY?|AUG(?:UST)?|SEP(?:T(?:EMBER)?)?|OCT(?:OBER)?|NOV(?:EMBER)?|DEC(?:EMBER)?)\.?(?:\s*(\d{1,2})(?!\d)(?:\s*[-–]\s*(\d{1,2})(?!\d))?)?(?:\s*,?\s*(20\d{2}))?/i);
    if (!m || (!m[2] && !m[4])) return null;
    const mon = m[1].slice(0, 3).toUpperCase();
    const y = m[4] || String(new Date().getFullYear());
    return { range: mon + (m[2] ? ' ' + m[2] + (m[3] ? '-' + m[3] : '') : '') + ' ' + y, month: y + '-' + String(MONTHS[mon] || 1).padStart(2, '0') };
  };
  const f = { id: document.getElementById('box-form-id'), label: document.getElementById('box-form-label'), code: document.getElementById('box-form-code'),
    date: document.getElementById('box-form-date'), month: document.getElementById('box-form-month') };
  const hint = (input, text) => {
    let h = input.parentNode.querySelector('.wh-hint');
    if (!h) { h = el('small', 'wh-hint'); input.parentNode.appendChild(h); }
    h.textContent = text;
  };
  let dateWasAuto = false;
  const syncForm = (fromOpen) => {
    if (!f.label || !f.code || !f.date) return;
    const isNew = !f.id || f.id.value === '0' || f.id.value === '';
    if (isNew) {
      f.code.value = codeOf(f.label.value);
      f.code.readOnly = true;
      hint(f.code, 'Made from the Box Label');
    } else {
      f.code.readOnly = true;                     // a saved box keeps its code: the contents list is tied to it
      hint(f.code, 'The code of a saved box does not change');
    }
    const d = dateOf(f.label.value);
    if (d) {
      f.date.value = d.range; f.date.readOnly = true; dateWasAuto = true;
      if (f.month) f.month.value = d.month;
      hint(f.date, 'Taken from the date in the Box Label');
    } else {
      if (dateWasAuto && !fromOpen) f.date.value = '';
      dateWasAuto = false; f.date.readOnly = false;
      hint(f.date, 'No date in the Box Label: type the date range');
    }
  };
  if (f.label) {
    f.label.addEventListener('input', () => syncForm(false));
    const back = document.getElementById('wh-box-modal-backdrop');
    if (back) new MutationObserver(() => { if (!back.classList.contains('hidden')) setTimeout(() => syncForm(true), 0); }).observe(back, { attributes: true, attributeFilter: ['class'] });
  }

  // ------------------------------------------------------------------ dialogs
  const dialog = (title, wide) => {
    const ov = el('div', 'whc-ov'); ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true'); ov.setAttribute('aria-label', title);
    const box = el('div', 'whc-box' + (wide ? ' wide' : ''));
    const head = el('header', 'whc-head'); head.append(el('h3', '', title));
    const x = el('button', 'whc-x', '×'); x.type = 'button'; x.setAttribute('aria-label', 'Close'); head.append(x);
    const body = el('div', 'whc-body'); const foot = el('footer', 'whc-foot');
    box.append(head, body, foot); ov.append(box); document.body.append(ov);
    const close = () => { ov.remove(); document.removeEventListener('keydown', key); };
    const key = (e) => { if (e.key === 'Escape' && document.body.lastElementChild === ov) close(); };
    document.addEventListener('keydown', key); x.addEventListener('click', close); ov.addEventListener('mousedown', (e) => { if (e.target === ov) close(); });
    return { ov, body, foot, close, head };
  };
  const btn = (txt, cls) => { const b = el('button', 'whc-btn ' + (cls || ''), txt); b.type = 'button'; return b; };
  const GROUP_LABEL = { bonding: 'Bonding', wholeshoe: 'Whole Shoe', material: 'Material' };
  const STATUS_LABEL = { IN_BOX: 'In the box', TRANSFERRED: 'Transferred', REMOVED: 'Removed' };

  // ------------------------------------------------------------------ Add Contents
  const addContents = (box) => {
    const d = dialog('Add Contents - ' + box.label, true);
    const bar = el('div', 'whc-filters');
    const mk = (label, input) => { const l = el('label', 'whc-f'); l.append(el('span', '', label), input); return l; };
    const from = el('input'); from.type = 'date'; const to = el('input'); to.type = 'date';
    const grp = el('select'); [['', 'All tests'], ['material', 'Material test'], ['bonding', 'Bonding'], ['wholeshoe', 'Whole Shoe test']].forEach(([v, t]) => { const o = el('option', '', t); o.value = v; grp.append(o); });
    const q = el('input'); q.type = 'search'; q.placeholder = 'Lab Ref, style, colour, MTL ...';
    const show = btn('Show Lab References', 'primary');
    bar.append(mk('Tested from', from), mk('Tested to', to), mk('Test', grp), mk('Search', q), show);
    const info = el('div', 'whc-info', 'Choose the dates and the test, then Show Lab References.'); info.setAttribute('role', 'status');
    const grid = el('div', 'whc-cards');
    d.body.append(bar, info, grid);
    const selected = new Set();
    const count = el('span', 'whc-count', '0 selected');
    const all = btn('Select all shown'); const none = btn('Clear'); const cancel = btn('Cancel'); const save = btn('Save to box', 'primary'); save.disabled = true;
    d.foot.append(count, all, none, el('span', 'whc-grow'), cancel, save);
    const upd = () => { count.textContent = selected.size + ' selected'; save.disabled = selected.size === 0; };
    const cards = [];
    const draw = (items) => {
      grid.textContent = ''; cards.length = 0;
      items.forEach((it) => {
        const lab = el('label', 'whc-card' + (it.in_this_box ? ' is-in' : ''));
        const cb = el('input'); cb.type = 'checkbox'; cb.value = it.lab_ref; cb.disabled = it.in_this_box || !!it.in_box; cb.checked = selected.has(it.lab_ref);
        cb.addEventListener('change', () => { if (cb.checked) selected.add(it.lab_ref); else selected.delete(it.lab_ref); lab.classList.toggle('on', cb.checked); upd(); });
        const t = el('div', 'whc-card-t'); t.append(el('b', '', it.lab_ref), el('span', 'whc-tag ' + it.group, GROUP_LABEL[it.group] || it.group));
        const s = el('div', 'whc-card-s', [it.style, it.color].filter(Boolean).join(' / ') || '-');
        const m = el('div', 'whc-card-m', [it.date || 'no date', it.mtl].filter(Boolean).join(' - '));
        lab.classList.toggle('on', cb.checked);
        lab.append(cb, t, s, m);
        if (it.in_this_box) lab.append(el('div', 'whc-card-n', 'Already in this box'));
        else if (it.also_in) lab.append(el('div', 'whc-card-n', 'Also in: ' + it.also_in));
        else if (it.in_box) { lab.classList.add('is-in'); lab.append(el('div', 'whc-card-n warn', 'Already in box: ' + it.in_box + ' (mark it Transferred or Removed there first)')); }
        grid.append(lab); cards.push({ cb, it });
      });
    };
    const load = async () => {
      show.disabled = true; info.textContent = 'Loading...';
      try {
        const j = await api('warehouse-tracker/labrefs?box=' + encodeURIComponent(box.code) + '&from=' + from.value + '&to=' + to.value + '&group=' + grp.value + '&q=' + encodeURIComponent(q.value));
        draw(j.items);
        info.textContent = j.items.length ? j.items.length + (j.more ? '+ ' : ' ') + 'Lab Reference(s) found' + (j.more ? ' (first 400: narrow the dates)' : '') + '. Tick the ones that go in this box.' : 'No Lab References for that filter.';
      } catch (e) { info.textContent = e.message; } finally { show.disabled = false; }
    };
    show.addEventListener('click', load);
    [from, to, grp].forEach((n) => n.addEventListener('change', load));
    q.addEventListener('keydown', (e) => { if (e.key === 'Enter') load(); });
    all.addEventListener('click', () => { cards.forEach(({ cb, it }) => { if (!cb.disabled) { cb.checked = true; selected.add(it.lab_ref); cb.parentNode.classList.add('on'); } }); upd(); });
    none.addEventListener('click', () => { selected.clear(); cards.forEach(({ cb }) => { cb.checked = false; cb.parentNode.classList.remove('on'); }); upd(); });
    cancel.addEventListener('click', d.close);
    save.addEventListener('click', async () => {
      save.disabled = true; info.textContent = 'Saving...';
      try {
        const j = await api('warehouse-tracker/box-items/add', { box_code: box.code, labs: [...selected] });
        toast(j.added + ' Lab Reference(s) put in the box' + (j.skipped ? ' (' + j.skipped + ' skipped: already in a box)' : '') + '.', j.skipped ? 'info' : 'success');
        d.close(); openList(box);
      } catch (e) { info.textContent = e.message; save.disabled = false; }
    });
    from.focus();
  };

  // ------------------------------------------------------------------ where a Lab Reference moves to
  const pickBox = (fromCode, lab) => new Promise(async (resolve) => {
    const d = dialog('Transfer ' + lab + ' to which box?');
    let done = false;
    const finish = (v) => { if (done) return; done = true; d.close(); resolve(v); };
    new MutationObserver(() => { if (!document.body.contains(d.ov)) finish(null); }).observe(document.body, { childList: true });
    d.body.append(el('p', 'whc-info', 'Choose the box where it will be placed. It stays in this box list marked as Transferred, and shows in the new box as "Transferred from".'));
    const q = el('input'); q.type = 'search'; q.className = 'whc-q'; q.placeholder = 'Search a box code or label ...'; d.body.append(q);
    const list = el('div', 'whc-boxes'); d.body.append(list);
    let all = [];
    try { all = (await api('warehouse-tracker/boxes')).boxes || []; } catch (e) { list.textContent = e.message; }
    all = all.filter((b) => b.box_code !== fromCode && String(b.status || 'ACTIVE').toUpperCase() !== 'ARCHIVED');
    const draw = () => {
      const t = q.value.trim().toLowerCase(); list.textContent = '';
      all.filter((b) => !t || (b.box_code + ' ' + b.label + ' ' + (b.rack_name || '')).toLowerCase().includes(t)).slice(0, 60).forEach((b) => {
        const row = el('button', 'whc-boxrow'); row.type = 'button';
        row.append(el('b', '', b.box_code), el('span', '', b.label), el('small', '', (b.rack_name || b.rack_id) + ' - Layer ' + b.tier_level + ', Slot ' + b.slot_index + (b.date_range ? ' - ' + b.date_range : '')));
        row.addEventListener('click', () => finish({ code: b.box_code, label: b.label }));
        list.append(row);
      });
      if (!list.children.length) list.append(el('p', 'whc-info', 'No box found.'));
    };
    q.addEventListener('input', draw); draw(); q.focus();
    const cancel = btn('Cancel'); cancel.addEventListener('click', () => finish(null)); d.foot.append(el('span', 'whc-grow'), cancel);
  });

  // ------------------------------------------------------------------ the list of a box
  const openList = async (box) => {
    const d = dialog('Box ' + box.code, true);
    d.body.append(el('p', 'whc-info', 'Loading...'));
    let data;
    try { data = await api('warehouse-tracker/box-items?box=' + encodeURIComponent(box.code)); } catch (e) { d.body.textContent = e.message; return; }
    const draw = () => {
      d.body.textContent = ''; d.foot.textContent = '';
      const top = el('div', 'whc-top');
      top.append(el('div', 'whc-sub', data.box.label + (data.box.range ? ' - ' + data.box.range : '')));
      d.body.append(top);
      if (!data.can_edit) d.body.append(el('p', 'whc-note', 'You can look at this list. Admin, Manager, Supervisor, Warehouse PIC, Bonding PIC and Encoding can tick and save.'));
      const wrap = el('div', 'whc-tbl-wrap'); const tbl = el('table', 'whc-tbl');
      const hr = el('tr');
      ['Lab Reference', 'Test', 'Tested', 'Style / Colorway'].forEach((h) => hr.append(el('th', '', h)));
      data.cols.forEach((c) => {
        const th = el('th', 'whc-ck', c.label);
        if (data.is_admin) { const x = el('button', 'whc-th-x', '×'); x.type = 'button'; x.title = 'Hide the column ' + c.label; x.addEventListener('click', async () => { if (!confirm('Hide the column "' + c.label + '"? What was ticked stays saved.')) return; try { await api('warehouse-tracker/item-cols/hide', { key: c.key }); data = await api('warehouse-tracker/box-items?box=' + encodeURIComponent(box.code)); draw(); } catch (e) { toast(e.message, 'error'); } }); th.append(x); }
        hr.append(th);
      });
      hr.append(el('th', '', 'Status'), el('th', '', 'Note'));
      if (data.can_edit) hr.append(el('th', '', ''));
      tbl.append(el('thead')); tbl.firstChild.append(hr);
      const tb = el('tbody');
      const rows = [];
      const fmt = (t) => (t ? String(t).slice(0, 10) : '');
      data.items.forEach((it) => {
        const moved = it.status === 'TRANSFERRED';
        const tr = el('tr', moved ? 'is-moved' : it.status === 'REMOVED' ? 'is-out' : it.from ? 'is-from' : '');
        const ref = el('td', 'mono', it.lab_ref);
        if (it.from) ref.append(el('div', 'whc-chip from', 'Transferred from ' + it.from + (it.moved_at ? ' on ' + fmt(it.moved_at) : '') + (it.moved_by ? ' by ' + it.moved_by : '')));
        tr.append(ref, el('td', '', GROUP_LABEL[it.type] || it.type), el('td', '', it.date), el('td', '', [it.style, it.color].filter(Boolean).join(' / ')));
        const cbs = {};
        data.cols.forEach((c) => {
          const td = el('td', 'whc-ck'); const cb = el('input'); cb.type = 'checkbox'; cb.checked = !!(it.flags && it.flags[c.key]); cb.disabled = !data.can_edit; cb.setAttribute('aria-label', c.label + ' ' + it.lab_ref);
          td.append(cb); tr.append(td); cbs[c.key] = cb;
        });
        const rowObj = { it, cbs, dest: '' };
        const stTd = el('td', 'whc-st'); const trail = el('div', 'whc-chip');
        const sel = el('select'); sel.disabled = !data.can_edit || moved; Object.keys(STATUS_LABEL).forEach((k) => { const o = el('option', '', STATUS_LABEL[k]); o.value = k; sel.append(o); }); sel.value = it.status;
        const paint = () => {
          const v = sel.value; tr.className = v === 'TRANSFERRED' ? 'is-moved' : v === 'REMOVED' ? 'is-out' : it.from ? 'is-from' : '';
          trail.className = 'whc-chip ' + (v === 'TRANSFERRED' ? 'to' : v === 'REMOVED' ? 'out' : 'hide');
          if (v === 'TRANSFERRED') trail.textContent = moved ? 'Transferred to ' + (it.to || it.to_code) + (it.moved_at ? ' on ' + fmt(it.moved_at) : '') + (it.moved_by ? ' by ' + it.moved_by : '') : (rowObj.dest ? 'Will move to ' + rowObj.destLabel + ' (click Save changes)' : '');
          else if (v === 'REMOVED') trail.textContent = 'Marked as removed from the box';
          else trail.textContent = '';
        };
        sel.addEventListener('change', async () => {
          const prev = rowObj.prevStatus || it.status;
          if (sel.value === 'TRANSFERRED' && !moved) {
            const to = await pickBox(box.code, it.lab_ref);
            if (!to) { sel.value = prev; rowObj.dest = ''; paint(); return; }
            rowObj.dest = to.code; rowObj.destLabel = to.label;
          } else rowObj.dest = '';
          rowObj.prevStatus = sel.value; paint();
        });
        stTd.append(sel, trail); paint();
        const note = el('input'); note.type = 'text'; note.maxLength = 255; note.value = it.note; note.disabled = !data.can_edit; note.placeholder = it.by ? 'by ' + it.by : ''; note.title = it.note;
        const noteTd = el('td'); noteTd.append(note); tr.append(stTd, noteTd);
        if (data.can_edit) {
          const rm = el('td'); const b = btn('Remove', 'danger'); b.disabled = moved; b.title = moved ? 'A transferred row is the record of the move and stays' : 'Take it out of this box list (picked by mistake)';
          b.addEventListener('click', async () => {
            if (!confirm('Take ' + it.lab_ref + ' out of box ' + box.code + '?\n\nUse this only when it was picked by mistake. (If it really left the box, set its Status to Transferred or Removed instead.)')) return;
            b.disabled = true;
            try { await api('warehouse-tracker/box-items/remove', { box_code: box.code, id: it.id }); data = await api('warehouse-tracker/box-items?box=' + encodeURIComponent(box.code)); draw(); toast(it.lab_ref + ' was taken out of the box list.', 'success'); } catch (e) { toast(e.message, 'error'); b.disabled = moved; }
          });
          rm.append(b); tr.append(rm);
        }
        Object.assign(rowObj, { sel, note }); tb.append(tr); rows.push(rowObj);
      });
      if (!data.items.length) { const tr = el('tr'); const td = el('td', 'whc-empty', 'Nothing in this box yet.' + (data.can_edit ? ' Use Add Contents.' : '')); td.colSpan = 7 + data.cols.length; tr.append(td); tb.append(tr); }
      tbl.append(tb); wrap.append(tbl); d.body.append(wrap);
      if (data.is_admin) {
        const row = el('div', 'whc-addcol'); const inp = el('input'); inp.type = 'text'; inp.placeholder = 'New check column, e.g. Customer audit'; inp.maxLength = 40;
        const add = btn('Add column'); add.addEventListener('click', async () => { if (!inp.value.trim()) return; add.disabled = true; try { await api('warehouse-tracker/item-cols/add', { label: inp.value.trim() }); data = await api('warehouse-tracker/box-items?box=' + encodeURIComponent(box.code)); draw(); } catch (e) { toast(e.message, 'error'); add.disabled = false; } });
        row.append(el('span', '', 'Admin:'), inp, add); d.body.append(row);
      }
      const msg = el('span', 'whc-grow'); msg.setAttribute('role', 'status');
      const close = btn('Close'); close.addEventListener('click', d.close);
      d.foot.append(el('span', 'whc-count', data.items.length + ' Lab Reference(s)'), msg);
      if (data.can_edit) {
        const ac = btn('Add Contents'); ac.addEventListener('click', () => { d.close(); addContents(box); });
        const save = btn('Save changes', 'primary');
        save.addEventListener('click', async () => {
          save.disabled = true; msg.textContent = 'Saving...';
          try {
            const j = await api('warehouse-tracker/box-items/save', { box_code: box.code, rows: rows.map((r) => ({ id: r.it.id, status: r.sel.value, dest: r.dest, note: r.note.value, flags: Object.fromEntries(Object.entries(r.cbs).map(([k, c]) => [k, c.checked])) })) });
            msg.textContent = j.saved + ' row(s) saved.' + (j.errors && j.errors.length ? ' Not saved: ' + j.errors.join(' ') : ''); data = await api('warehouse-tracker/box-items?box=' + encodeURIComponent(box.code)); draw();
          } catch (e) { msg.textContent = e.message; save.disabled = false; }
        });
        d.foot.append(ac, close, save);
      } else d.foot.append(close);
    };
    draw();
  };

  // ------------------------------------------------------------------ hooks: Box Code (inspector and table), Add Contents button in the table
  const boxes = () => (window.NX_WH_BOXES || []);
  const find = (code) => boxes().find((b) => String(b.box_code) === String(code));
  const fetchBox = async (code) => {
    let b = find(code);
    if (!b) { try { const j = await api('warehouse-tracker/boxes'); window.NX_WH_BOXES = j.boxes; b = find(code); } catch (e) { /* below */ } }
    return b ? { code: b.box_code, label: b.label } : { code, label: code };
  };
  document.addEventListener('click', async (e) => {
    const link = e.target.closest('.wh-code-link');
    if (link) { e.preventDefault(); openList(await fetchBox(link.dataset.code || link.textContent.trim())); return; }
    const add = e.target.closest('.btn-contents');
    if (add) { e.preventDefault(); addContents(await fetchBox(add.dataset.code)); }
  });
  const biCode = document.getElementById('bi-box-code');
  if (biCode) {
    biCode.classList.add('wh-code-link'); biCode.setAttribute('role', 'button'); biCode.tabIndex = 0; biCode.title = 'Open the contents of this box';
    biCode.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); biCode.click(); } });
  }
  window.NxWarehouseContents = { openList, addContents };
})();
