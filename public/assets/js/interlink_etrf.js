/**
 * InterLink > VF eTRF. Create a request (or a test) in InterLink from the five VF templates:
 *   pick a template -> pick a Lab Reference (optional) -> edit what the form needs -> the server says what is still missing -> save a draft / submit.
 * Nothing is invented here or on the server: an empty value stays empty, is listed as missing, and a request with something missing is not sent.
 */
(() => {
  const root = document.getElementById('et-root');
  if (!root) return;
  const $ = (s, c = root) => c.querySelector(s);
  const $$ = (s, c = root) => [...c.querySelectorAll(s)];
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const api = (path, opts = {}) => fetch(BASE + path, opts).then((r) => r.json().then((j) => {
    if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
    if (!r.ok || j.ok === false) { const e = new Error(j.error || 'Request failed'); e.code = j.code; e.missing = j.missing; throw e; }
    return j;
  }));
  const post = (path, body) => api(path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: JSON.stringify(body || {}) });
  const toast = (m, t = 'info', ms = 5000) => (window.nxToast ? window.nxToast(m, t, ms) : null);

  // what each box of the form is called in InterLink's field codes (the preview returns the codes)
  const CODE = { company_name: 'JOB_STD_APP_DESC', interlink_code: 'JOB_STD_APP', contact_person: 'APPT_CONTACT', email_recipients: 'EMAIL_ADHOC_LIST', test_stage: 'TRPT_TESTING_STATUS',
    service_test_type: 'CAL_CDTL_11', buyer_brand: 'TRPT_CDI_BRAND_NAME', country_of_origin: 'TRPT_COUNTRY_ORIGIN', factory_name: 'TRPT_FACTORY_NAME', factory_id: 'FACTORY_ID',
    product_export_to: 'CAL_PO_COUNTRY', age_group: 'TRPT_AGE_RANGE', cargo_receive_date: 'INSP_SHIP_DATE', end_use: 'CAL_CDTL_1', material_sub_type: 'CAL_CDTL_8', season: 'TRPT_SEASON',
    test_packages: 'CAL_CDTL_5', individual_tests: 'E2_LTEXT_001', lab_remarks: 'TRPT_RMK', previous_report_no: 'TRPT_PREV_NO', improvements: 'E1_TEXT_050' };
  const PRODUCT_COLS = [['style_no', 'Style No. *'], ['product_name', 'Product name *'], ['style_color_no', 'Style color No. *'], ['color_code', 'Color code *'], ['color_description', 'Color description *'], ['season', 'Season *'], ['size_category', 'Size category *']];
  const MATERIAL_COLS = [['material_code', 'Material code *'], ['material_description', 'Material description *'], ['material_type', 'Material type *'], ['color_code', 'Color code'], ['color_description', 'Color description'], ['weight', 'Weight'], ['weight_uom', 'Weight UoM'], ['thickness', 'Thickness'], ['claim_type', 'Claim type']];
  const SIZE_CATS = ['UNISEX', 'MALE', 'FEMALE', 'KIDS', 'TODDLER'];
  const LABEL_TO_F = { 'Company name': 'company_name', 'InterLink code': 'interlink_code', 'Contact person': 'contact_person', 'Brand': 'buyer_brand', 'Test stage': 'test_stage', 'Factory name': 'factory_name',
    'Factory ID': 'factory_id', 'Supplier name': 'vendor_name', 'Supplier ID': 'vendor_id', 'Vendor name': 'vendor_name', 'Vendor ID': 'vendor_id', 'Cargo receive date': 'cargo_receive_date', 'Test package or individual tests': 'test_packages' };

  const S = { code: '', mat: false, ref: '', rows: [], serviceReady: false, serviceEnabled: false, busy: false, labrefs: [], timer: 0, q: 0 };

  // ------------------------------------------------------------------------------------------------ the form
  const val = (f) => ($(`[data-f="${f}"]`) || {}).value || '';
  const setVal = (f, v) => { const el = $(`[data-f="${f}"]`); if (el && v != null) el.value = String(v); };
  const cols = () => (S.mat ? MATERIAL_COLS : PRODUCT_COLS);

  const renderRows = () => {
    S.rows.forEach((r) => { if (!S.mat && !r.size_category) r.size_category = 'UNISEX'; });     // the box shows UNISEX when nothing is chosen, so the request must say so too
    $('#et-rows-title').textContent = S.mat ? 'Material information' : 'Style information';
    $('#et-rows-head').innerHTML = '<tr>' + cols().map(([, l]) => `<th>${esc(l)}</th>`).join('') + '<th></th></tr>';
    $('#et-rows-body').innerHTML = S.rows.map((r, i) => '<tr>' + cols().map(([k]) => (k === 'size_category'
      ? `<td><select data-r="${i}" data-k="${k}">${SIZE_CATS.map((c) => `<option ${String(r[k] || '').toUpperCase() === c ? 'selected' : ''}>${c}</option>`).join('')}</select></td>`
      : `<td><input type="text" data-r="${i}" data-k="${k}" value="${esc(r[k] ?? '')}"></td>`)).join('') + `<td>${S.rows.length > 1 ? `<button type="button" class="et-btn" data-del="${i}" title="Remove this row">&times;</button>` : ''}</td></tr>`).join('');
  };

  const applyTemplate = (code, mat) => {
    S.code = code; S.mat = mat;
    $$('.et-card').forEach((c) => c.classList.toggle('on', c.dataset.code === code));
    $$('[data-only]').forEach((d) => { d.style.display = d.dataset.only === (mat ? 'material' : 'product') ? '' : 'none'; });
    $('#et-l-vendor').textContent = mat ? 'Supplier name *' : 'Vendor name *';
    $('#et-l-vendor-id').textContent = mat ? 'Supplier ID *' : 'Vendor ID *';
    const c = $(`.et-card[data-code="${CSS.escape(code)}"]`);
    $('#et-badge').textContent = c ? c.querySelector('b').textContent : (code || 'choose a template');
  };

  const fillFromPreview = (j) => {
    const f = j.fields || {};
    const s = j.summary || {};
    const mat = j.sample_type === 'Material';
    Object.entries(CODE).forEach(([k, code]) => { if (f[code] != null && f[code] !== '') setVal(k, f[code]); else if (s[k] != null) setVal(k, s[k]); });
    setVal('vendor_name', mat ? f.MSTA_FIELD_6 : f.TRPT_SUPPLIER_NAME);
    setVal('vendor_id', mat ? f.MSTA_FIELD_10 : f.SUPPLIER_ID);
    setVal('is_retest', f.TRPT_RETEST === '1' ? 'Yes' : 'No');
    if (!mat) setVal('season', '');
    S.rows = (j.rows && j.rows.length ? j.rows : [{}]).map((r) => { const o = {}; cols().forEach(([k]) => { o[k] = r[k] ?? ''; }); return o; });
    renderRows();
  };

  const payload = (action) => {
    const p = { etrf_type: S.code, lab_ref_no: S.ref, action };
    $$('[data-f]').forEach((el) => {
      if (el.closest('[data-only]') && el.closest('[data-only]').style.display === 'none') return;
      const v = el.value.trim();
      if (v !== '') p[el.dataset.f] = v;
    });
    if (S.mat) { if (p.vendor_name) p.supplier_name = p.vendor_name; if (p.vendor_id) p.supplier_id = p.vendor_id; }
    p.rows = S.rows.map((r) => { const o = {}; cols().forEach(([k]) => { o[k] = String(r[k] ?? '').trim(); }); return o; });
    return p;
  };

  // ------------------------------------------------------------------------------------------------ check
  const showCheck = (j, err) => {
    const box = $('#et-check');
    $$('.bad').forEach((x) => x.classList.remove('bad'));
    if (err && !j) { box.className = 'et-check no'; box.textContent = err.message; setSend(false); return; }
    const miss = j.missing || [];
    miss.forEach((m) => {
      const base = m.replace(/ \(row \d+\)$/, '');
      const row = (m.match(/\(row (\d+)\)$/) || [])[1];
      const f = LABEL_TO_F[base];
      if (f) $(`[data-f="${f}"]`)?.classList.add('bad');
      const colKey = cols().find(([, l]) => l.replace(/ \*$/, '') === base);
      if (colKey) $$(`#et-rows-body [data-k="${colKey[0]}"]`).forEach((el) => { if (!row || String(Number(el.dataset.r) + 1) === row) el.classList.add('bad'); });
    });
    if (miss.length) {
      box.className = 'et-check no';
      box.innerHTML = `<b>Not ready.</b> InterLink needs these and they are empty:<ul>${miss.map((m) => `<li>${esc(m)}</li>`).join('')}</ul><span class="sm">Fill them in (or pick a Lab Reference that has them). Nothing is guessed for you.</span>`;
    } else {
      box.className = 'et-check ok';
      box.innerHTML = `<b>Ready.</b> ${esc(j.template_label || '')}: ${S.rows.length} row${S.rows.length > 1 ? 's' : ''}. The form has everything InterLink asks for in the checked sections. InterLink itself still validates when the service fills the form, and tells you if anything else is required.`;
    }
    setSend(!miss.length);
  };
  const setSend = (ready) => {
    const ok = ready && S.code && S.serviceEnabled && !S.busy;
    $('#et-save').disabled = !ok;
    $('#et-submit').disabled = !ok;
  };
  const check = async () => {
    if (!S.code) return;
    try { showCheck(await post('interlink/etrf-check', payload('save'))); } catch (e) { showCheck(null, e); }
  };
  const checkSoon = () => { clearTimeout(S.timer); S.timer = setTimeout(check, 500); };

  const preview = async (keepTemplate) => {
    const q = new URLSearchParams();
    if (S.ref) q.set('lab_ref_no', S.ref);
    if (S.code && keepTemplate !== false) q.set('etrf_type', S.code);
    $('#et-check').className = 'et-check';
    $('#et-check').textContent = 'Reading the Lab Reference...';
    try {
      const j = await api('interlink/etrf-preview?' + q.toString());
      const card = $(`.et-card[data-code="${CSS.escape(j.etrf_type)}"]`);
      applyTemplate(j.etrf_type, j.sample_type === 'Material' || (card && card.dataset.mat === '1'));
      fillFromPreview(j);
      showCheck(j);
    } catch (e) { showCheck(null, e); }
  };

  // ------------------------------------------------------------------------------------------------ send
  const send = async (action) => {
    if (S.busy || !S.code) return;
    if (action === 'submit') {
      const ok = window.nxConfirm
        ? await window.nxConfirm(`Submit this ${$('#et-badge').textContent} request to Intertek InterLink for real? It cannot be taken back from here. (Save as draft lets you look at it in InterLink first.)`, { okText: 'Submit', danger: true })
        : confirm('Submit this request to InterLink for real?');
      if (!ok) return;
    }
    S.busy = true; setSend(false);
    $('#et-msg').textContent = action === 'save' ? 'Sending the draft to the service...' : 'Sending to the service...';
    try {
      const j = await post('interlink/service/etrf', payload(action));
      if (window.nxSvcWatch) window.nxSvcWatch();
      $('#et-msg').textContent = '';
      toast(`${j.template_label || 'eTRF'} is queued${j.ahead ? ` (${j.ahead} ahead)` : ' and is next'}. You will be told here and on Synology when it is ${action === 'save' ? 'saved as a draft' : 'submitted'}.`, 'success', 8000);
      loadHistory();
    } catch (e) {
      $('#et-msg').textContent = '';
      if (e.code === 'etrf_incomplete') { check(); toast(e.message, 'error', 9000); }
      else if (e.code === 'service_unreachable') toast(e.message + ' Start NexusInterlinkService on the service computer, or correct its address in Settings.', 'error', 12000);
      else toast(e.message, 'error', 9000);
    }
    S.busy = false; check();
  };

  // ------------------------------------------------------------------------------------------------ service, Lab References, history
  const loadStatus = async () => {
    const dot = $('#et-dot'); const txt = $('#et-status-text');
    try {
      const j = await api('interlink/service/status');
      S.serviceEnabled = !!j.enabled;
      if (!j.enabled) { dot.className = 'et-dot off'; txt.textContent = 'The InterLink background service is not set up. An administrator enables it in Settings > InterLink service.'; }
      else if (!j.online) { dot.className = 'et-dot off'; txt.textContent = 'The service does not answer at the address in Settings. Start NexusInterlinkService on the service computer, or an administrator corrects the address.'; }
      else if (!j.signedIn) { dot.className = 'et-dot warn'; txt.textContent = 'The service is running but not signed in to InterLink. On the service computer: tray icon > Sign in to InterLink.'; }
      else { dot.className = 'et-dot ok'; txt.textContent = `The service is online and signed in to InterLink${j.waiting ? ` (${j.waiting} waiting)` : ''}.`; }
      S.serviceReady = !!(j.enabled && j.online && j.signedIn);
    } catch (e) { dot.className = 'et-dot off'; txt.textContent = 'Could not read the service status: ' + e.message; }
    setSend(!$('#et-check').classList.contains('no') && $('#et-check').classList.contains('ok'));
  };

  const renderLabrefs = () => {
    $('#et-labrefs').innerHTML = S.labrefs.map((r) => `<tr class="pick ${S.ref === r.lab_ref_no ? 'on' : ''}" data-ref="${esc(r.lab_ref_no)}"><td><b>${esc(r.lab_ref_no)}</b></td><td>${esc(r.form_category)}</td><td>${esc(r.material_description || r.model_style || r.material_code)}</td><td>${esc(r.supplier)}</td><td>${esc(r.overall_result)}</td></tr>`).join('') || '<tr><td colspan="5" class="muted">No match.</td></tr>';
  };
  const loadLabrefs = async () => {
    try {
      const j = await api(`interlink/labrefs?limit=60&q=${encodeURIComponent($('#et-q').value.trim())}&category=${encodeURIComponent($('#et-cat').value)}`);
      S.labrefs = j.rows || [];
      const sel = $('#et-cat');
      if (sel.options.length === 1) [...new Map(S.labrefs.filter((r) => r.form_slug).map((r) => [r.form_slug, r.form_category || r.form_slug])).entries()].forEach(([v, l]) => sel.insertAdjacentHTML('beforeend', `<option value="${esc(v)}">${esc(l)}</option>`));
      renderLabrefs();
    } catch (e) { $('#et-labrefs').innerHTML = `<tr><td colspan="5">${esc(e.message)}</td></tr>`; }
  };

  const loadHistory = async () => {
    try {
      const j = await api('interlink/etrf-history');
      const rows = j.rows || [];
      $('#et-history').innerHTML = rows.map((r) => `<tr><td>#${esc(r.id)}</td><td><b>${esc(r.etrf_type)}</b><br><span class="muted sm">${esc(r.template_label)}</span></td><td>${esc(r.lab_ref_no || '-')}</td>
        <td>${esc(r.style_no || r.material_code || '-')}<br><span class="muted sm">${esc(r.product_name || '')}</span></td><td>${esc(r.action_mode === 'save' ? 'draft' : r.action_mode || '')}</td><td><b>${esc(r.application_no || '-')}</b></td>
        <td><span class="et-chip ${esc(r.status)}">${esc(r.status)}</span>${r.status === 'failed' && r.status_message ? `<br><span class="sm" style="color:#991b1b">${esc(r.status_message)}</span>` : ''}</td><td>${esc(r.requested_by_name || '')}</td><td>${esc(r.submitted_at || r.created_at || '')}</td></tr>`).join('') || '<tr><td colspan="9" class="muted">Nothing sent yet.</td></tr>';
      return rows.some((r) => r.status === 'queued' || r.status === 'running');
    } catch (e) { return false; }
  };
  const poll = async () => {
    if (!document.hidden) { const live = await loadHistory(); clearTimeout(S.q); S.q = setTimeout(poll, live ? 4000 : 30000); } else { clearTimeout(S.q); S.q = setTimeout(poll, 8000); }
  };

  // ------------------------------------------------------------------------------------------------ events
  $('#et-cards').addEventListener('click', (e) => {
    const c = e.target.closest('.et-card'); if (!c) return;
    applyTemplate(c.dataset.code, c.dataset.mat === '1');
    preview(true);
  });
  $('#et-labrefs').addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-ref]'); if (!tr) return;
    S.ref = tr.dataset.ref; $('#et-ref-note').textContent = 'Using ' + S.ref;
    renderLabrefs();
    preview(!!S.code);
  });
  $('#et-clear-ref').addEventListener('click', () => { S.ref = ''; $('#et-ref-note').textContent = ''; renderLabrefs(); if (S.code) preview(true); });
  let qt = 0;
  $('#et-q').addEventListener('input', () => { clearTimeout(qt); qt = setTimeout(loadLabrefs, 350); });
  $('#et-cat').addEventListener('change', loadLabrefs);
  root.addEventListener('input', (e) => {
    const t = e.target;
    if (t.dataset && t.dataset.r !== undefined) S.rows[Number(t.dataset.r)][t.dataset.k] = t.value;
    if (t.matches('[data-f],[data-k]')) checkSoon();
  });
  root.addEventListener('change', (e) => { const t = e.target; if (t.dataset && t.dataset.r !== undefined) { S.rows[Number(t.dataset.r)][t.dataset.k] = t.value; checkSoon(); } });
  $('#et-add-row').addEventListener('click', () => {
    if (!S.code) { toast('Choose a template first.', 'info'); return; }
    const last = S.rows[S.rows.length - 1] || {};
    S.rows.push(S.mat ? {} : { season: last.season || '', size_category: last.size_category || 'UNISEX' });     // the same season and size group as the row above, as a start
    renderRows(); checkSoon();
  });
  $('#et-rows-body').addEventListener('click', (e) => { const b = e.target.closest('[data-del]'); if (!b) return; S.rows.splice(Number(b.dataset.del), 1); renderRows(); checkSoon(); });
  $('#et-save').addEventListener('click', () => send('save'));
  $('#et-submit').addEventListener('click', () => send('submit'));

  S.rows = [{}];
  applyTemplate('', false);
  $$('[data-only="material"]').forEach((d) => { d.style.display = 'none'; });
  renderRows();
  loadStatus(); setInterval(loadStatus, 20000);
  loadLabrefs();
  poll();
})();
