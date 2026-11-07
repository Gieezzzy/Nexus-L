/**
 * InterLink > VF eTRF. Create a request (or a test) in InterLink from the five VF templates:
 *   pick a template -> pick a Lab Reference (and/or look up MTL# in DSR/CFM share) -> edit what the form needs -> save a draft / submit.
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
    if (!r.ok || j.ok === false) { const e = new Error(j.error || j.message || 'Request failed'); e.code = j.code; e.missing = j.missing; throw e; }
    return j;
  }));
  const post = (path, body) => api(path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: JSON.stringify(body || {}) });
  const toast = (m, t = 'info', ms = 5000) => (window.nxToast ? window.nxToast(m, t, ms) : null);

  // Exact mapping of form data-f keys to InterLink NormalFormWithHeader.aspx field codes
  const CODE = {
    // Section 1: Applicant Information
    company_name: 'JOB_STD_APP_DESC',
    interlink_code: 'JOB_STD_APP',
    applicant_address: 'APPT_ADDR_1',
    applicant_country: 'APPT_ADDR_3',
    postal_code: 'E1_TEXT_001',
    contact_person: 'APPT_CONTACT',
    applicant_email: 'APPT_EMAIL',
    applicant_phone: 'APPT_TEL',
    email_recipients: 'EMAIL_ADHOC_LIST',

    // Section 2: Invoice Information
    invoice_to_applicant: 'E1_TEXT_002',
    invoice_company_name: 'E1_TEXT_003',
    invoice_address: 'E1_TEXT_004',
    invoice_country: 'E1_TEXT_005',
    invoice_postal_code: 'E1_TEXT_006',
    invoice_contact_person: 'E1_TEXT_007',
    invoice_email: 'E1_TEXT_008',
    invoice_telephone: 'E1_TEXT_009',

    // Section 3: Vendor / Factory / Supplier Information
    factory_name: 'TRPT_FACTORY_NAME',
    factory_id: 'FACTORY_ID',

    // Section 4: Service Requested
    product_export_to: 'CAL_PO_COUNTRY',
    service_test_type: 'CAL_CDTL_11',
    test_stage: 'TRPT_TESTING_STATUS',
    service_provider: 'E1_TEXT_032',
    lab_location: 'ITS_OFFICE_DISP',
    service_required: 'TRPT_SERV_TYPE',
    lab_address: 'E1_TEXT_012',
    quotation_required: 'E1_TEXT_013',
    previous_report_no: 'TRPT_PREV_NO',
    improvements: 'E1_TEXT_050',
    return_sample: 'E1_TEXT_014',
    any_related_trf: 'E1_TEXT_042',
    related_trf_no: 'CAL_CDTL_10',

    // Section 5: Material / Product Information
    sample_type: 'TRPT_CLT_SUB_PRD_CAT',
    product_category: 'TRPT_PRD_CAT',
    buyer_brand: 'TRPT_CDI_BRAND_NAME',
    requirement_spec_no: 'CAL_UPC',
    material_sub_type: 'CAL_CDTL_8',
    season: 'TRPT_SEASON',
    finishing: 'TXT_FINISH',
    end_use: 'CAL_CDTL_1',
    country_of_origin: 'TRPT_COUNTRY_ORIGIN',
    cuttable_width: 'E1_TEXT_053',
    warp_per_inch: 'E1_TEXT_060',
    weft_per_inch: 'E1_TEXT_061',
    trim_size: 'E1_TEXT_052',
    yarn_specifications: 'E1_TEXT_054',
    washing_instruction: 'MSTA_FIELD_4',
    age_group: 'TRPT_AGE_RANGE',
    cargo_receive_date: 'INSP_SHIP_DATE',
    product_claim_type: 'CAL_CDTL_3',
    care_label_1: 'CARE_LABEL_1',
    fiber_content_text: 'TXT_FIBER_CONTENT',

    // Section 6: Test Requirements
    gb_standard_code: 'E1_TEXT_034',
    gb_safety_code: 'E1_TEXT_035',
    test_packages: 'CAL_CDTL_5',
    other_gb_hypercare: 'E2_LTEXT_003',
    other_gb_voc: 'E2_LTEXT_004',
    individual_tests: 'E2_LTEXT_001',
    lab_remarks: 'TRPT_RMK',

    // Section 7: Additional Documentation & CPSC Certificate of Compliance
    gcc_cpc_required: 'TRPT_HTS_CODE',
    product_id: 'TRPT_PRODUCT_ID',
    manufacture_date: 'TRPT_MANUFACTURE_DATE_STR',
    factory_address: 'DETOX_FACILITY_ADD_ADDRESS',
    factory_apt_suite: 'DETOX_FACILITY_HOUSE_NO',
    factory_city: 'DETOX_FACILITY_CITY',
    factory_state_province: 'DETOX_FACILITY_PROVINCE',
    factory_zip_code: 'DETOX_FACILITY_ZIP_CODE',
    factory_country: 'DETOX_FACILITY_COUNTRY',
    factory_email: 'DETOX_FACILITY_EMAIL',
    factory_phone: 'DETOX_FACILITY_PHONE',
  };

  // Exact InterLink column order for JOB_DUMMY_009
  const PRODUCT_COLS = [
    ['style_no', 'Style No. (e.g. NF0A8BK9) *'],
    ['product_name', 'Product Name *'],
    ['style_color_no', 'Style Color No. (e.g. NF0A8BK9MVO) *'],
    ['color_description', 'Color Description *'],
    ['color_code', 'Color Code *'],
    ['season', 'Season *'],
    ['size_category', 'Size Category *'],
  ];
  const MATERIAL_COLS = [
    ['material_code', 'Material Code *'],
    ['material_description', 'Material Description *'],
    ['material_type', 'Material Type *'],
    ['color_code', 'Color Code'],
    ['color_description', 'Color Description'],
    ['weight', 'Weight'],
    ['weight_uom', 'Weight UOM'],
    ['thickness', 'Thickness'],
    ['claim_type', 'Claim type'],
  ];

  const LABEL_TO_F = {
    'Company name': 'company_name',
    'InterLink code': 'interlink_code',
    'Address': 'applicant_address',
    'Country': 'applicant_country',
    'Contact person': 'contact_person',
    'Email': 'applicant_email',
    'Email notification recipients': 'email_recipients',
    'Brand': 'buyer_brand',
    'Test stage': 'test_stage',
    'Service test type': 'service_test_type',
    'Service provider': 'service_provider',
    'Lab location': 'lab_location',
    'Lab address': 'lab_address',
    'Factory name': 'factory_name',
    'Factory ID': 'factory_id',
    'Facility name': 'factory_name',
    'Facility ID': 'factory_id',
    'Supplier name': 'vendor_name',
    'Supplier ID': 'vendor_id',
    'Vendor name': 'vendor_name',
    'Vendor ID': 'vendor_id',
    'Product export to': 'product_export_to',
    'Age group': 'age_group',
    'Sourcing office': 'sourcing_office',
    'Country of origin': 'country_of_origin',
    'Cargo receive date': 'cargo_receive_date',
    'Test package or individual tests': 'test_packages',
  };

  const S = {
    code: '', mat: false, ref: '', rows: [], fibers: [], serviceReady: false, serviceEnabled: false, busy: false,
    labrefs: [], timer: 0, q: 0,
    dsrFiles: [], dsrActiveRel: '', dsrHeader: {}, dsrComps: [], labHint: '',
  };

  // ------------------------------------------------------------------------------------------------ the form
  const val = (f) => ($(`[data-f="${f}"]`) || {}).value || '';
  const setVal = (f, v) => {
    const el = $(`[data-f="${f}"]`);
    if (el && v != null) el.value = String(v);
  };
  const cols = () => (S.mat ? MATERIAL_COLS : PRODUCT_COLS);
  const rowLetter = (i) => String.fromCharCode(65 + (i % 26)) + (i >= 26 ? String(Math.floor(i / 26)) : '');

  const syncExportPillsFromText = () => {
    const raw = val('product_export_to');
    const parts = raw.split(/[;,]+/).map((x) => x.trim().toLowerCase()).filter(Boolean);
    $$('.et-exp-cb').forEach((cb) => {
      cb.checked = parts.includes(cb.value.toLowerCase());
    });
  };

  const syncExportTextFromPills = () => {
    const picked = $$('.et-exp-cb').filter((cb) => cb.checked).map((cb) => cb.value);
    setVal('product_export_to', picked.join('; '));
    checkSoon();
  };

  const syncInvoiceVisibility = () => {
    const invBox = $('#et-inv-fields');
    if (invBox) invBox.style.display = val('invoice_to_applicant') === 'No' ? '' : 'none';
  };

  const renderRows = () => {
    S.rows.forEach((r) => { if (!S.mat && !r.size_category) r.size_category = 'UA'; });
    $('#et-rows-title').textContent = S.mat ? 'Material Information *' : 'Style Information *';
    $('#et-add-row').textContent = S.mat ? '+ Add Material Information' : '+ Add Style Information';
    $('#et-rows-head').innerHTML = '<tr><th style="width:36px">Row</th>' + cols().map(([, l]) => `<th>${esc(l)}</th>`).join('') + '<th style="width:42px"></th></tr>';
    $('#et-rows-body').innerHTML = S.rows.map((r, i) => '<tr>'
      + `<td><span class="et-row-letter">${esc(rowLetter(i))}</span></td>`
      + cols().map(([k]) => {
        const v = r[k] ?? (k === 'size_category' ? 'UA' : '');
        // Multi-line textarea input areas matching InterLink's grid cells
        if (k === 'material_description' || k === 'product_name' || k === 'claim_type' || (!S.mat && k === 'color_description')) {
          return `<td><textarea rows="2" data-r="${i}" data-k="${k}">${esc(v)}</textarea></td>`;
        }
        if (k === 'size_category') {
          return `<td><input type="text" data-r="${i}" data-k="${k}" value="${esc(v)}" list="dl-size-cats" style="min-width:85px"></td>`;
        }
        if (k === 'season') {
          return `<td><input type="text" data-r="${i}" data-k="${k}" value="${esc(v)}" list="dl-seasons" style="min-width:75px"></td>`;
        }
        if (k === 'weight_uom') {
          return `<td><input type="text" data-r="${i}" data-k="${k}" value="${esc(v)}" list="dl-weight-uom" style="min-width:80px"></td>`;
        }
        const listAttr = k === 'material_type' ? ' list="dl-mat-types"' : '';
        return `<td><input type="text" data-r="${i}" data-k="${k}" value="${esc(v)}"${listAttr}></td>`;
      }).join('')
      + `<td>${S.rows.length > 1 ? `<button type="button" class="et-btn sm" data-del="${i}" title="Remove row ${esc(rowLetter(i))}">&times;</button>` : ''}</td></tr>`).join('');
  };

  const renderFibers = () => {
    const partTh = $('#et-fiber-part-th');
    if (partTh) partTh.textContent = S.mat ? 'Material Part' : 'Product Part';
    const tbody = $('#et-fibers-body');
    if (!tbody) return;
    if (!S.fibers.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="muted sm">No fiber composition rows added (optional — click "+ Add Fiber Content" if required).</td></tr>';
      return;
    }
    tbody.innerHTML = S.fibers.map((f, i) => `
      <tr>
        <td><span class="et-row-letter">${i + 1}</span></td>
        <td><input type="text" data-fb="${i}" data-fbk="part" value="${esc(f.part ?? '')}" list="dl-fiber-parts" placeholder="${S.mat ? 'Material Part' : 'Product Part'}"></td>
        <td><input type="text" data-fb="${i}" data-fbk="fiber" value="${esc(f.fiber ?? '')}" list="dl-fibers" placeholder="e.g. Cotton, Polyester, Rubber"></td>
        <td><input type="text" data-fb="${i}" data-fbk="pct" value="${esc(f.pct ?? '0.00')}" placeholder="0.00"></td>
        <td><button type="button" class="et-btn sm" data-del-fiber="${i}" title="Remove fiber row">&times;</button></td>
      </tr>
    `).join('');
  };

  const applyTemplate = (code, mat) => {
    S.code = code; S.mat = mat;
    $$('.et-card').forEach((c) => c.classList.toggle('on', c.dataset.code === code));
    $$('[data-only]').forEach((d) => { d.style.display = d.dataset.only === (mat ? 'material' : 'product') ? '' : 'none'; });
    $('#et-sec3-title').textContent = mat ? '3. Supplier Information' : '3. Vendor / Factory / Supplier Information';
    $('#et-sec5-title').textContent = mat ? '5. Material Information (All)' : '5. Product Information (All)';
    $('#et-l-vendor').textContent = mat ? 'Supplier Name *' : 'Vendor Name *';
    $('#et-l-vendor-id').textContent = mat ? 'Supplier ID *' : 'Vendor ID *';
    $('#et-l-factory').textContent = mat ? 'Facility Name *' : 'Factory Name *';
    $('#et-l-factory-id').textContent = mat ? 'Facility ID *' : 'Factory ID *';
    $('#et-l-care').innerHTML = (mat ? 'Washing Instruction' : 'Care Instruction') + ' <small class="muted">(input area, max 500 chars)</small>';
    setVal('sample_type', mat ? 'Material' : 'Finished Product');
    const c = $(`.et-card[data-code="${CSS.escape(code)}"]`);
    const acc = c ? (c.dataset.account || '') : '';
    $('#et-badge').textContent = c ? `${c.querySelector('b').textContent}${acc ? ` (${acc})` : ''}` : (code || 'choose a template');
    renderFibers();
  };

  const fillFromPreview = (j) => {
    const f = j.fields || {};
    const s = j.summary || {};
    const mat = j.sample_type === 'Material';
    Object.entries(CODE).forEach(([k, code]) => {
      if (f[code] != null && f[code] !== '') setVal(k, f[code]);
      else if (s[k] != null) setVal(k, s[k]);
      else setVal(k, '');
    });
    setVal('vendor_name', mat ? (f.MSTA_FIELD_6 || s.vendor_name || '') : (f.TRPT_SUPPLIER_NAME || s.vendor_name || ''));
    setVal('vendor_id', mat ? (f.MSTA_FIELD_10 || s.vendor_id || '') : (f.SUPPLIER_ID || s.vendor_id || ''));
    setVal('sourcing_office', s.sourcing_office || f.E1_TEXT_023 || 'ASO');
    setVal('finishing', f.TXT_FINISH || s.finishing || '');
    setVal('requirement_spec_no', f.CAL_UPC || s.requirement_spec_no || '');
    setVal('is_retest', f.TRPT_RETEST === '1' ? 'Yes' : 'No');
    setVal('service_required', f.TRPT_SERV_TYPE || '4');
    setVal('quotation_required', f.E1_TEXT_013 || '2');
    setVal('return_sample', f.E1_TEXT_014 || 'No');
    setVal('invoice_to_applicant', f.E1_TEXT_002 || 'Yes');
    setVal('sample_type', f.TRPT_CLT_SUB_PRD_CAT || (mat ? 'Material' : 'Finished Product'));
    setVal('product_category', f.TRPT_PRD_CAT || 'Footwear');
    setVal('gcc_cpc_required', f.TRPT_HTS_CODE || 'No');
    setVal('supplementary_attachment_notes', s.supplementary_attachment_notes || '');
    if (!mat) setVal('season', '');

    syncExportPillsFromText();
    syncInvoiceVisibility();

    S.rows = (j.rows && j.rows.length ? j.rows : [{}]).map((r) => {
      const o = {};
      cols().forEach(([k]) => { o[k] = r[k] ?? ''; });
      return o;
    });
    S.fibers = Array.isArray(j.fibers) ? j.fibers.map((fb) => ({
      part: fb.part ?? fb.REF_FLD05 ?? '',
      fiber: fb.fiber ?? fb.REF_FLD07 ?? '',
      pct: fb.pct ?? fb.REF_FLD15 ?? '0.00',
    })) : [];
    S.labHint = String((S.rows[0] && S.rows[0].material_description) || s.product_name || s.material_type || '').trim();
    renderRows();
    renderFibers();
  };

  const payload = (action) => {
    const p = { etrf_type: S.code, lab_ref_no: S.ref, action };
    $$('[data-f]').forEach((el) => {
      if (el.closest('[data-only]') && el.closest('[data-only]').style.display === 'none') return;
      const v = el.value.trim();
      if (v !== '') p[el.dataset.f] = v;
    });
    if (S.mat) {
      if (p.vendor_name) p.supplier_name = p.vendor_name;
      if (p.vendor_id) p.supplier_id = p.vendor_id;
    }
    p.rows = S.rows.map((r) => {
      const o = {};
      cols().forEach(([k]) => { o[k] = String(r[k] ?? '').trim(); });
      return o;
    });
    p.fibers = S.fibers
      .map((fb) => ({
        part: String(fb.part ?? '').trim(),
        fiber: String(fb.fiber ?? '').trim(),
        pct: String(fb.pct ?? '0.00').trim() || '0.00',
      }))
      .filter((fb) => fb.part !== '' || fb.fiber !== '');
    return p;
  };

  // ------------------------------------------------------------------------------------------------ DSR / CFM Lookup
  const scoreComponentMatch = (c) => {
    const hint = (S.labHint + ' ' + ((S.rows[0] && S.rows[0].material_description) || '')).toLowerCase();
    if (!hint.trim()) return 0;
    let score = 0;
    const spec = String(c.material_spec || '').toLowerCase();
    const comp = String(c.component_name || '').toLowerCase();
    const supCode = String(c.supplier_code || '').toLowerCase();
    const colorCode = String(c.color_code || '').toLowerCase();
    if (supCode && supCode.length >= 3 && hint.includes(supCode)) score += 10;
    if (colorCode && colorCode.length >= 4 && hint.includes(colorCode)) score += 8;
    if (hint.includes('foxing') && (comp.includes('foxing') || spec.includes('foxing'))) score += 6;
    if (hint.includes('outsole') && (comp.includes('outsole') || spec.includes('outsole'))) score += 6;
    if (hint.includes('lining') && (comp.includes('lining') || spec.includes('lining'))) score += 5;
    if (hint.includes('suede') && spec.includes('suede')) score += 5;
    if (hint.includes('canvas') && spec.includes('canvas')) score += 5;
    if (hint.includes('lace') && (comp.includes('lace') || spec.includes('lace'))) score += 5;
    return score;
  };

  const renderDsrComps = () => {
    const kw = ($('#et-dsr-comp-filter')?.value || '').trim().toLowerCase();
    const scored = S.dsrComps.map((c, idx) => ({ ...c, _idx: idx, _score: scoreComponentMatch(c) }));
    const filtered = scored.filter((c) => {
      if (!kw) return true;
      const hay = `${c.section_no} ${c.component_name} ${c.vf_material_code} ${c.supplier_code} ${c.material_spec} ${c.color_code} ${c.color_name} ${c.supplier_name}`.toLowerCase();
      return hay.includes(kw);
    });
    if (!kw) {
      filtered.sort((a, b) => (b._score - a._score) || (a._idx - b._idx));
    }
    $('#et-dsr-comp-count').textContent = `${filtered.length} of ${S.dsrComps.length} component(s)`;
    $('#et-dsr-comps').innerHTML = filtered.map((c) => `
      <tr class="pick ${c._score >= 5 ? 'match' : ''}" data-cidx="${c._idx}">
        <td>${esc(c.section_no)}</td>
        <td><b>${esc(c.component_name)}</b>${c._score >= 5 ? ' <span class="et-chip match">Match</span>' : ''}</td>
        <td><b>${esc(c.vf_material_code || '-')}</b></td>
        <td>${esc(c.supplier_code || '-')}</td>
        <td>${esc(c.material_spec || '')}</td>
        <td><b>${esc(c.color_code || '-')}</b></td>
        <td>${esc(c.color_name || c.color_raw || '-')}</td>
        <td>${esc(c.supplier_name || '-')}</td>
        <td>${esc(c.size_val || '-')}</td>
        <td><button type="button" class="et-btn sm primary" data-use-comp="${c._idx}">Use</button></td>
      </tr>
    `).join('') || '<tr><td colspan="10" class="muted">No matching BOM components in this file.</td></tr>';
  };

  const applyDsrHeader = (silent = false) => {
    const h = S.dsrHeader || {};
    if (!h.mtl_no && !h.style_no && !h.season) return;
    if (h.season) setVal('season', h.season);
    if (!S.mat) {
      if (!S.rows.length) S.rows = [{ size_category: 'UA' }];
      const r = S.rows[0];
      if (h.style_no || h.mtl_no) r.style_no = h.style_no || h.mtl_no;
      if (h.style_desc) r.product_name = h.style_desc;
      if (h.mtl_no) r.style_color_no = h.mtl_no;
      const vMatch = String(h.mtl_no || '').match(/^VN[A-Z0-9]{6}([A-Z0-9]{3})$/i);
      if (h.color_code) r.color_code = h.color_code;
      else if (vMatch && !r.color_code) r.color_code = vMatch[1].toUpperCase();
      if (h.colorway) r.color_description = h.colorway;
      if (h.season) r.season = h.season;
      if (h.mtl_no || h.style_no) setVal('product_id', h.mtl_no || h.style_no);
      renderRows();
    }
    checkSoon();
    if (!silent) toast('Applied DSR Style & Season header to the eTRF form.', 'success', 3500);
  };

  const applyDsrComponent = (idx) => {
    const c = S.dsrComps[idx];
    if (!c) return;
    const h = S.dsrHeader || {};
    if (h.season || c.season) setVal('season', h.season || c.season);
    if (!S.rows.length) S.rows = [S.mat ? {} : { size_category: 'UA' }];
    // A material request has one row per component: a clicked component goes into the first row that is still empty
    // (A, then B, ...) and a new row is added when every row is already filled. The same component is not added twice.
    let r = S.rows[0];
    if (S.mat) {
      const code = (c.vf_material_code && c.vf_material_code !== 'TBD') ? c.vf_material_code : (c.supplier_code || '');
      const filled = (x) => String(x.material_code || '').trim() !== '' || String(x.material_description || '').trim() !== '';
      if (code && S.rows.some((x) => String(x.material_code || '').trim() === String(code).trim() && String(x.color_code || '') === String(c.color_code || ''))) {
        toast(`Component ${code} (${c.color_code || 'no colour'}) is already in the Material Information rows.`, 'warning', 4000);
        return;
      }
      r = S.rows.find((x) => !filled(x));
      if (!r) { r = {}; S.rows.push(r); }
    }

    if (S.mat) {
      const keepDesc = $('#et-dsr-keep-desc')?.checked;
      if (c.vf_material_code && c.vf_material_code !== 'TBD') r.material_code = c.vf_material_code;
      else if (c.supplier_code) r.material_code = c.supplier_code;
      if (!keepDesc || !String(r.material_description || '').trim()) {
        r.material_description = [c.size_val, c.component_name, c.supplier_code, c.material_spec].filter(Boolean).join(' ').trim() || c.material_spec || '';
      }
      if (c.interlink_material_type) r.material_type = c.interlink_material_type;
      if (c.color_code) r.color_code = c.color_code;
      if (c.color_name) r.color_description = c.color_name;
      else if (c.color_raw) r.color_description = c.color_raw;
      if (c.size_val && !r.thickness && /mm/i.test(c.size_val)) r.thickness = c.size_val;
      if (c.material_sub_type) setVal('material_sub_type', c.material_sub_type);
      if (c.component_name) setVal('end_use', c.component_name);
      if (c.test_package && !val('test_packages')) setVal('test_packages', c.test_package);
    } else {
      if (h.style_no || h.mtl_no) r.style_no = h.style_no || h.mtl_no;
      if (h.style_desc) r.product_name = h.style_desc;
      if (h.mtl_no) r.style_color_no = h.mtl_no;
      const vMatch = String(h.mtl_no || '').match(/^VN[A-Z0-9]{6}([A-Z0-9]{3})$/i);
      r.color_code = c.color_code || h.color_code || (vMatch ? vMatch[1].toUpperCase() : '') || r.color_code || '';
      r.color_description = c.color_name || h.colorway || r.color_description || '';
      if (h.season || c.season) r.season = h.season || c.season;
      if (h.mtl_no || h.style_no) setVal('product_id', h.mtl_no || h.style_no);
    }

    renderRows();
    checkSoon();
    toast(`Populated eTRF with component "${c.component_name}" (Material Code: ${c.vf_material_code || c.supplier_code || '-'}, Color: ${c.color_code || '-'} ${c.color_name || ''}).`, 'success', 4500);
  };

  const loadDsrFile = async (relPath, mtlNo) => {
    S.dsrActiveRel = relPath || mtlNo;
    $$('#et-dsr-files tr.pick').forEach((tr) => tr.classList.toggle('on', tr.dataset.rel === relPath));
    $('#et-dsr-note').textContent = 'Reading CFM / DSR Excel components...';
    try {
      const q = new URLSearchParams();
      if (relPath) {
        q.set('rel_path', relPath);
        q.set('file', relPath);
      }
      if (mtlNo) q.set('mtl_no', mtlNo);
      const j = await api('interlink/dsr-file?' + q.toString());
      S.dsrHeader = j.header || {};
      S.dsrComps = j.components || [];
      const h = S.dsrHeader;
      $$('#et-dsr-files tr.pick').forEach((tr) => {
        if (tr.dataset.rel === relPath) {
          const tds = tr.querySelectorAll('td');
          if (tds[2] && h.mtl_no) tds[2].innerHTML = `<code>${esc(h.mtl_no)}</code>`;
          if (tds[3] && h.style_no) tds[3].textContent = h.style_no;
          if (tds[4] && h.style_desc) tds[4].textContent = h.style_desc;
          if (tds[5] && h.colorway) tds[5].textContent = h.colorway;
        }
      });
      $('#et-dsr-hdr-text').innerHTML = `
        <b>MTL#:</b> <code>${esc(h.mtl_no || mtlNo || '-')}</code> &nbsp;|&nbsp;
        <b>Season:</b> <code>${esc(h.season || '-')}</code> &nbsp;|&nbsp;
        <b>Style#:</b> <code>${esc(h.style_no || '-')}</code> &nbsp;|&nbsp;
        <b>Style Name:</b> ${esc(h.style_desc || '-')} &nbsp;|&nbsp;
        <b>Colorway:</b> ${esc(h.color_code ? `${h.color_code} - ${h.colorway}` : (h.colorway || '-'))}
      `;
      $('#et-dsr-comp-wrap').style.display = '';
      renderDsrComps();
      $('#et-dsr-note').textContent = `Loaded ${S.dsrComps.length} component(s) from ${j.file?.file_name || h.source_file || h.mtl_no || 'DSR'}. Click any component row to populate the eTRF.`;
    } catch (e) {
      $('#et-dsr-note').textContent = 'Could not read DSR file: ' + e.message;
    }
  };

  // ---------------------------------------------------------------------- Order Summary PDFs (season / style per MTL#)
  const OS_MTL = /^VN0[0-9A-Z]{7,}(-[A-Z]+)?$/i;
  const osSig = (j) => (j.files || []).map((f) => f.name + '|' + f.modified).join(';');
  const applySeason = (s) => {
    setVal('season', s);
    if (!S.mat) {
      if (!S.rows.length) S.rows = [{ size_category: 'UA' }];
      S.rows.forEach((r) => { r.season = s; });
      renderRows();
    }
    checkSoon();
    toast(`Season ${s} applied from the Order Summary.`, 'success', 3000);
  };
  const tabBadge = (id, n) => { const el = $('#' + id); if (el) el.textContent = n ? String(n) : ''; };
  const renderOs = (j) => {
    const files = j.files || [];
    $('#et-os-files').textContent = files.length
      ? `${files.length} PDF(s), latest change ${files[0].modified}` : (j.reachable ? 'No PDFs in the folder' : 'Folder not reachable');
    const box = $('#et-os-res');
    if (j.errors && j.errors.length) $('#et-os-note').textContent = j.errors[0];
    if (!j.mtl) { box.innerHTML = ''; return; }
    const m = j.matches || [];
    tabBadge('et-tb-os', m.length);
    if (!m.length) {
      box.innerHTML = '';
      $('#et-os-note').textContent = j.reachable ? `${j.mtl} is not in the Order Summary PDFs.` : 'Cannot reach the Order Summary folder from the server.';
      return;
    }
    $('#et-os-note').textContent = `${m.length} entr${m.length === 1 ? 'y' : 'ies'} for ${j.mtl}.`;
    box.innerHTML = `<div class="et-list" style="max-height:220px;background:#fff"><table>
      <thead><tr><th>File / list</th><th>Style</th><th>Colour</th><th>Season</th><th>Risk</th><th>Note</th><th>Remark</th><th>Cutting die</th></tr></thead><tbody>${m.map((x) => `
      <tr><td><b>${esc(x.section || '')}</b><div class="muted sm">${esc(x.file)}${x.suffix ? ' &middot; code ' + esc(x.mtl) : ''}</div></td>
        <td>${esc(x.style || '-')}</td><td>${esc(x.color || '-')}</td>
        <td>${(x.seasons || []).map((s) => `<button type="button" class="et-btn sm primary" data-os-season="${esc(s)}">${esc(s)}</button>`).join(' ') || '-'}${x.origin_season ? `<div class="muted sm">first season ${esc(x.origin_season)}</div>` : ''}</td>
        <td>${esc(x.risk || '-')}</td><td>${esc(x.note || '-')}</td><td>${esc(x.remark || '-')}</td><td>${esc(x.cutting_die || '-')}</td></tr>`).join('')}</tbody></table></div>`;
    // fill the season on its own only when the form has none and the PDFs agree on exactly one
    const all = [...new Set(m.flatMap((x) => x.seasons || []))];
    const cur = ($('[data-f="season"]') || {}).value || '';
    if (all.length === 1 && !cur.trim()) applySeason(all[0]);
    else if (all.length > 1) $('#et-os-note').textContent += ' More than one season is ticked, so click the one this request is for.';
  };
  const lookupOs = async (mtl, quiet = false) => {
    const t = String(mtl || '').trim();
    if (t && !OS_MTL.test(t)) return;
    S.osMtl = t;
    if ($('#et-os-q') && t) $('#et-os-q').value = t;
    if (!quiet) $('#et-os-note').textContent = t ? `Reading the Order Summary PDFs for ${t}...` : '';
    try {
      const j = await api('interlink/order-summary?mtl=' + encodeURIComponent(t));
      S.osSig = osSig(j);
      renderOs(j);
    } catch (e) { $('#et-os-note').textContent = 'Order Summary lookup failed: ' + e.message; }
  };
  // a PDF the PIC drops in or replaces is picked up within half a minute while the page is open
  setInterval(async () => {
    if (document.hidden || !S.osMtl) return;
    try {
      const j = await api('interlink/order-summary?mtl=' + encodeURIComponent(S.osMtl));
      if (osSig(j) !== S.osSig) { S.osSig = osSig(j); renderOs(j); toast('The Order Summary PDFs were updated; results refreshed.', 'info', 3500); }
    } catch (e) { /* next tick */ }
  }, 30000);

  const searchDsr = async (termOverride) => {
    const raw = (termOverride !== undefined ? String(termOverride) : $('#et-dsr-q').value).trim();
    if (OS_MTL.test(raw)) lookupOs(raw);
    if (!raw) {
      $('#et-dsr-note').textContent = 'Enter an MTL# or Production Code / PO No.';
      return;
    }
    $('#et-dsr-q').value = raw;
    $('#et-dsr-note').textContent = `Searching DSR share for "${raw}"...`;
    $('#et-dsr-comp-wrap').style.display = 'none';
    try {
      const j = await api('interlink/dsr-search?q=' + encodeURIComponent(raw));
      S.dsrFiles = j.files || [];
      tabBadge('et-tb-dsr', S.dsrFiles.length);
      if (!S.dsrFiles.length) {
        $('#et-dsr-files-wrap').style.display = 'none';
        $('#et-dsr-note').textContent = `No DSR / CFM file found for "${raw}"${j.resolved_mtl && j.resolved_mtl !== raw ? ` (MTL: ${j.resolved_mtl})` : ''}.`;
        return;
      }
      $('#et-dsr-files-wrap').style.display = '';
      $('#et-dsr-files').innerHTML = S.dsrFiles.map((f) => `
        <tr class="pick ${S.dsrActiveRel === f.rel_path ? 'on' : ''}" data-rel="${esc(f.rel_path || '')}" data-mtl="${esc(f.mtl_no || j.resolved_mtl || '')}">
          <td><b>${esc(f.file_name)}</b></td>
          <td>${esc(f.season_folder || f.season || '-')}</td>
          <td><code>${esc(f.mtl_no || '-')}</code></td>
          <td>${esc(f.style_no || '-')}</td>
          <td>${esc(f.style_desc || '-')}</td>
          <td>${esc(f.colorway || '-')}</td>
        </tr>
      `).join('');
      $('#et-dsr-note').textContent = `Found ${S.dsrFiles.length} DSR file(s)${j.resolved_mtl && j.resolved_mtl.toUpperCase() !== raw.toUpperCase() ? ` (resolved PO ${raw} -> MTL# ${j.resolved_mtl})` : ''}.`;
      const first = S.dsrFiles[0];
      loadDsrFile(first.rel_path || '', first.mtl_no || j.resolved_mtl || raw);
    } catch (e) {
      $('#et-dsr-note').textContent = 'DSR search failed: ' + e.message;
    }
  };

  // ------------------------------------------------------------------------------------------------ check
  const normColLabel = (s) => String(s || '').replace(/\s*\(e\.g\.[^)]*\)/gi, '').replace(/\s*\*\s*$/, '').trim().toLowerCase();

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
      const colKey = cols().find(([, l]) => normColLabel(l) === normColLabel(base));
      if (colKey) {
        $$(`#et-rows-body [data-k="${colKey[0]}"]`).forEach((el) => {
          if (!row || String(Number(el.dataset.r) + 1) === row) el.classList.add('bad');
        });
      }
    });
    if (miss.length) {
      box.className = 'et-check no';
      box.innerHTML = `<b>Not ready.</b> InterLink needs these and they are empty:<ul>${miss.map((m) => `<li>${esc(m)}</li>`).join('')}</ul><span class="sm">Fill them in (or click a matching component in the DSR / CFM Lookup table above). Nothing is guessed for you.</span>`;
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
  const checkSoon = () => { clearTimeout(S.timer); S.timer = setTimeout(check, 450); };

  const preview = async (keepTemplate, autoDsr = false) => {
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
      if (autoDsr) {
        const dsrTerm = String(j.mtl_no || j.summary?.mtl_no || j.po_lot_no || j.summary?.po_lot_no || '').trim();
        if (dsrTerm) searchDsr(dsrTerm);
      }
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
      toast(`${j.template_label || 'eTRF'} is queued${j.ahead ? ` (${j.ahead} ahead)` : ' and is next'}. You will be told here and on Synology when it is ${action === 'save' ? 'saved as a draft' : 'submitted'} with the generated Application No.`, 'success', 8000);
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
    const dotAsst = $('#et-dot-labasst'); const dotLab = $('#et-dot-lab');
    try {
      const j = await api('interlink/service/status');
      S.serviceEnabled = !!j.enabled;
      const accMap = j.accounts || {};
      const accList = Array.isArray(accMap) ? accMap : Object.values(accMap);
      const asstAcc = accMap['dtp_qcasst@dtshoes.com'] || accList.find((a) => String(a.account || a.email || '').toLowerCase() === 'dtp_qcasst@dtshoes.com');
      const labAcc = accMap['dtp_lab@dtshoes.com'] || accList.find((a) => String(a.account || a.email || '').toLowerCase() === 'dtp_lab@dtshoes.com');
      if (dotAsst) dotAsst.className = 'et-dot ' + (!j.online ? 'off' : (asstAcc?.signedIn ? 'ok' : 'warn'));
      if (dotLab) dotLab.className = 'et-dot ' + (!j.online ? 'off' : (labAcc?.signedIn ? 'ok' : 'warn'));

      if (!j.enabled) { dot.className = 'et-dot off'; txt.textContent = 'The InterLink background service is not set up. An administrator enables it in Settings > InterLink service.'; }
      else if (!j.online) { dot.className = 'et-dot off'; txt.textContent = 'The service does not answer at the address in Settings. Start NexusInterlinkService on the service computer, or an administrator corrects the address.'; }
      else if (!j.signedIn) { dot.className = 'et-dot warn'; txt.textContent = 'The service is running — open the Nexus Interlink Service tray app on the service computer and click Login for each account.'; }
      else {
        const signedCount = (asstAcc?.signedIn ? 1 : 0) + (labAcc?.signedIn ? 1 : 0);
        dot.className = 'et-dot ok';
        txt.textContent = `The service is online (${signedCount || 1}/2 accounts signed in${j.waiting ? `, ${j.waiting} waiting` : ''}).`;
      }
      S.serviceReady = !!(j.enabled && j.online && j.signedIn);
    } catch (e) { dot.className = 'et-dot off'; txt.textContent = 'Could not read the service status: ' + e.message; }
    setSend(!$('#et-check').classList.contains('no') && $('#et-check').classList.contains('ok'));
  };

  const triggerAccountSignIn = async (account) => {
    try {
      const j = await post('interlink/service/signin', { account });
      toast(j.message || `Opened InterLink sign-in window on the service computer for ${account}. Complete sign-in there and close the browser when Home.aspx loads.`, 'info', 9000);
      let polls = 0;
      const iv = setInterval(() => {
        polls += 1;
        loadStatus();
        if (polls >= 20) clearInterval(iv);
      }, 3000);
    } catch (e) {
      toast('Could not start sign-in for ' + account + ': ' + e.message, 'error', 8000);
    }
  };

  const renderLabrefs = () => {
    $('#et-labrefs').innerHTML = S.labrefs.map((r) => `<tr class="pick ${S.ref === r.lab_ref_no ? 'on' : ''}" data-ref="${esc(r.lab_ref_no)}" data-mtl="${esc(r.mtl_no || '')}" data-po="${esc(r.po_lot_no || '')}">
      <td><b>${esc(r.lab_ref_no)}</b>${r.tr_no ? `<br><span class="muted sm">TR: ${esc(r.tr_no)}</span>` : ''}</td>
      <td>${esc(r.form_category)}</td>
      <td><code>${esc(r.mtl_no || '-')}</code></td>
      <td>${esc(r.po_lot_no || '-')}</td>
      <td>${esc(r.material_description || r.model_style || r.material_code)}</td>
      <td>${esc(r.supplier)}</td>
      <td>${esc(r.overall_result)}</td>
    </tr>`).join('') || '<tr><td colspan="7" class="muted">No match.</td></tr>';
  };
  const loadLabrefs = async () => {
    try {
      const j = await api(`interlink/labrefs?limit=60&q=${encodeURIComponent($('#et-q').value.trim())}&category=${encodeURIComponent($('#et-cat').value)}`);
      S.labrefs = j.rows || [];
      const sel = $('#et-cat');
      if (sel.options.length === 1) [...new Map(S.labrefs.filter((r) => r.form_slug).map((r) => [r.form_slug, r.form_category || r.form_slug])).entries()].forEach(([v, l]) => sel.insertAdjacentHTML('beforeend', `<option value="${esc(v)}">${esc(l)}</option>`));
      renderLabrefs();
    } catch (e) { $('#et-labrefs').innerHTML = `<tr><td colspan="7">${esc(e.message)}</td></tr>`; }
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
    preview(true, false);
  });
  $('#et-labrefs').addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-ref]'); if (!tr) return;
    S.ref = tr.dataset.ref; $('#et-ref-note').textContent = 'Using ' + S.ref;
    renderLabrefs();
    preview(!!S.code, true);
  });
  $('#et-clear-ref').addEventListener('click', () => { S.ref = ''; S.labHint = ''; $('#et-ref-note').textContent = ''; renderLabrefs(); if (S.code) preview(true, false); });

  // ---------------------------------------------------------------------- InterLink pick lists (stored by the service job)
  const lovTpl = () => { const t = (($('#et-badge') || {}).textContent || '').trim(); return /^VF eTRF/i.test(t) ? t : 'VF eTRF - Material'; };   // the supplier list is the same on every template
  const lovStatus = async () => {
    try {
      const j = await api('interlink/lov-status');
      const n = (j.fields || []).length;
      const when = (j.fields || []).map((f) => f.updated_at).filter(Boolean).sort().pop();
      $('#et-lov-state').textContent = n ? `${n} lists stored, last read ${when || ''}` : 'Not read yet';
    } catch (e) { $('#et-lov-state').textContent = ''; }
  };
  const lovSearch = async () => {
    const q = $('#et-lov-q').value.trim();
    if (!q) { $('#et-lov-note').textContent = 'Type part of a supplier / facility name or ID.'; return; }
    $('#et-lov-note').textContent = 'Searching...';
    try {
      const j = await api('interlink/lov?template=' + encodeURIComponent(lovTpl()) + '&field=MSTA_FIELD_6&limit=40&q=' + encodeURIComponent(q));
      tabBadge('et-tb-lov', j.total);
      $('#et-lov-note').textContent = `${j.total} match${j.total === 1 ? '' : 'es'}${j.total > 40 ? ' (first 40 shown, type more)' : ''}.`;
      S.lovRows = j.rows;
      $('#et-lov-res').innerHTML = j.rows.length ? `<div class="et-list" style="max-height:220px;background:#fff"><table><thead><tr><th>Facility ID</th><th>Facility Name</th><th>Supplier ID</th><th>Supplier Name</th><th></th></tr></thead><tbody>${j.rows.map((r, i) => `<tr><td><code>${esc(r[0])}</code></td><td>${esc(r[1])}</td><td><code>${esc(r[2])}</code></td><td>${esc(r[3])}</td><td><button type="button" class="et-btn sm primary" data-lov-use="${i}">Use</button></td></tr>`).join('')}</tbody></table></div>` : '';
    } catch (e) { $('#et-lov-note').textContent = e.message; $('#et-lov-res').innerHTML = ''; }
  };
  $('#et-lov-btn').addEventListener('click', lovSearch);
  $('#et-lov-q').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); lovSearch(); } });
  $('#et-lov-res').addEventListener('click', (e) => {
    const b = e.target.closest('[data-lov-use]'); if (!b) return;
    const r = (S.lovRows || [])[Number(b.dataset.lovUse)]; if (!r) return;
    setVal('factory_id', r[0]); setVal('factory_name', r[1]); setVal('vendor_id', r[2]); setVal('vendor_name', r[3]);
    checkSoon();
    toast('Facility and supplier applied from the InterLink list.', 'success', 3000);
  });
  $('#et-lov-sync').addEventListener('click', async () => {
    const ok = window.nxConfirm ? await window.nxConfirm('Read every pick list of the five eTRF templates from InterLink now? The service opens each form (nothing is saved or submitted) and this can take a long while.', { okText: 'Read lists' }) : confirm('Read all pick lists from InterLink now?');
    if (!ok) return;
    try {
      const j = await post('interlink/lov-sync', {});
      const bad = (j.queued || []).filter((x) => !x.ok);
      toast(bad.length ? `Queued, but ${bad.length} template(s) were refused: ${bad[0].error}` : 'Reading the pick lists. The service works through the five templates in turn.', bad.length ? 'warning' : 'success', 6000);
    } catch (e) { toast(e.message, 'error', 6000); }
  });
  lovStatus();
  setInterval(() => { if (!document.hidden) lovStatus(); }, 60000);

  // lookup tabs (Order Summary / pick lists / DSR): one card instead of three tall boxes
  $('#et-lkbar').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-lk]'); if (!b) return;
    $$('#et-lkbar button').forEach((x) => x.classList.toggle('on', x === b));
    $$('.et-lk').forEach((x) => x.classList.toggle('on', x.id === b.dataset.lk));
  });

  // Order Summary events
  $('#et-os-btn').addEventListener('click', () => {
    const v = $('#et-os-q').value.trim();
    if (!OS_MTL.test(v)) { $('#et-os-note').textContent = 'Enter a full MTL# such as VN000CQABOP.'; return; }
    lookupOs(v);
  });
  $('#et-os-q').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); $('#et-os-btn').click(); } });
  $('#et-os-res').addEventListener('click', (e) => {
    const b = e.target.closest('[data-os-season]'); if (b) applySeason(b.dataset.osSeason);
  });
  lookupOs('', true);

  // DSR / CFM Lookup events
  $('#et-dsr-btn').addEventListener('click', () => searchDsr());
  $('#et-dsr-q').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); searchDsr(); } });
  $('#et-dsr-files').addEventListener('click', (e) => {
    const tr = e.target.closest('tr[data-rel]'); if (!tr) return;
    loadDsrFile(tr.dataset.rel, tr.dataset.mtl);
  });
  $('#et-dsr-comp-filter').addEventListener('input', renderDsrComps);
  $('#et-dsr-apply-hdr').addEventListener('click', () => applyDsrHeader(false));
  $('#et-dsr-comps').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-use-comp]');
    const tr = e.target.closest('tr[data-cidx]');
    const idx = btn ? Number(btn.dataset.useComp) : (tr ? Number(tr.dataset.cidx) : -1);
    if (idx >= 0) applyDsrComponent(idx);
  });

  // Supplier / Facility presets
  $('#et-preset-own').addEventListener('click', () => {
    setVal('vendor_name', 'OWN SOURCE SUPPLIER');
    setVal('vendor_id', 'G00000');
    setVal('factory_name', 'OWN SOURCE SUPPLIER');
    setVal('factory_id', 'G00000000000F001');
    checkSoon();
  });
  $('#et-preset-key').addEventListener('click', () => {
    setVal('vendor_name', 'KEY TRADE CO., LTD');
    setVal('vendor_id', '0000507450');
    setVal('factory_name', 'DATIAN SUBIC SHOES INC.');
    setVal('factory_id', '0000507452');
    checkSoon();
  });

  // Product Export To multi-region helpers
  $$('.et-exp-cb').forEach((cb) => cb.addEventListener('change', syncExportTextFromPills));
  $('#et-exp-std')?.addEventListener('click', () => {
    setVal('product_export_to', 'APAC - Non China; Canada; CASA; EMEA; Mexico');
    syncExportPillsFromText();
    checkSoon();
  });
  $('#et-exp-all')?.addEventListener('click', () => {
    $$('.et-exp-cb').forEach((cb) => { cb.checked = true; });
    syncExportTextFromPills();
  });
  $('[data-f="product_export_to"]')?.addEventListener('input', syncExportPillsFromText);

  // Invoice to Applicant toggle
  $('#et-inv-toggle')?.addEventListener('change', () => {
    syncInvoiceVisibility();
    checkSoon();
  });

  // Quick-pick chips for textarea / input fields
  root.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-pick-f]');
    if (!chip) return;
    const f = chip.dataset.pickF;
    const v = chip.dataset.pickVal || '';
    if (chip.dataset.pickAppend === '1') {
      const cur = val(f).trim();
      if (!cur) setVal(f, v);
      else if (!cur.toLowerCase().includes(v.toLowerCase())) setVal(f, cur + '; ' + v);
    } else {
      setVal(f, v);
    }
    checkSoon();
  });

  let qt = 0;
  $('#et-q').addEventListener('input', () => { clearTimeout(qt); qt = setTimeout(loadLabrefs, 350); });
  $('#et-cat').addEventListener('change', loadLabrefs);
  root.addEventListener('input', (e) => {
    const t = e.target;
    if (t.dataset && t.dataset.r !== undefined) S.rows[Number(t.dataset.r)][t.dataset.k] = t.value;
    if (t.dataset && t.dataset.fb !== undefined) S.fibers[Number(t.dataset.fb)][t.dataset.fbk] = t.value;
    if (t.matches('[data-f],[data-k],[data-fbk]')) checkSoon();
  });
  root.addEventListener('change', (e) => {
    const t = e.target;
    if (t.dataset && t.dataset.r !== undefined) { S.rows[Number(t.dataset.r)][t.dataset.k] = t.value; checkSoon(); }
    if (t.dataset && t.dataset.fb !== undefined) { S.fibers[Number(t.dataset.fb)][t.dataset.fbk] = t.value; checkSoon(); }
    if (t.matches('select[data-f]')) checkSoon();
  });
  $('#et-add-row').addEventListener('click', () => {
    if (!S.code) { toast('Choose a template first.', 'info'); return; }
    const last = S.rows[S.rows.length - 1] || {};
    S.rows.push(S.mat ? {} : { season: last.season || '', size_category: last.size_category || 'UA' });
    renderRows(); checkSoon();
  });
  $('#et-rows-body').addEventListener('click', (e) => {
    const b = e.target.closest('[data-del]'); if (!b) return;
    S.rows.splice(Number(b.dataset.del), 1);
    renderRows(); checkSoon();
  });
  $('#et-add-fiber')?.addEventListener('click', () => {
    S.fibers.push({ part: '', fiber: '', pct: '100.00' });
    renderFibers();
    checkSoon();
  });
  $('#et-fibers-body')?.addEventListener('click', (e) => {
    const b = e.target.closest('[data-del-fiber]'); if (!b) return;
    S.fibers.splice(Number(b.dataset.delFiber), 1);
    renderFibers();
    checkSoon();
  });
  $('#et-save').addEventListener('click', () => send('save'));
  $('#et-submit').addEventListener('click', () => send('submit'));

  window.addEventListener('nxl:interlink-service', (e) => {
    const ev = (e && e.detail) || {};
    loadHistory();
    loadLabrefs();
    const banner = $('#et-thankyou-banner');
    if (banner && ev.kind === 'success' && ev.application_no) {
      const appNo = String(ev.application_no);
      const chatUrl = BASE + 'inbox?chat=' + encodeURIComponent(ev.chat_key || 'n:interlink_etrf');
      banner.style.display = '';
      banner.innerHTML = `
        <div style="border:2px solid #eab308;background:#fefce8;border-radius:11px;padding:12px 14px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px">
          <div>
            <div style="font-weight:900;font-size:15px;color:#ca8a04;letter-spacing:.02em">THANK YOU! For your online job submission</div>
            <div style="margin-top:3px;font-size:13.5px;color:#0f172a">
              <b>Application No:</b> <code style="font-size:16px;font-weight:900;background:#fff;padding:2px 8px;border:1px solid #eab308;border-radius:6px">${esc(appNo)}</code>
              ${ev.template ? `&nbsp;&middot;&nbsp;<b>Template:</b> ${esc(ev.template)}` : ''}
              ${ev.lab_ref_no ? `&nbsp;&middot;&nbsp;<b>Lab Ref:</b> <code>${esc(ev.lab_ref_no)}</code>` : ''}
            </div>
          </div>
          <div class="et-row" style="gap:6px">
            <button type="button" class="et-btn sm primary" id="et-copy-appno">Copy Application No.</button>
            <a href="${esc(chatUrl)}" class="et-btn sm" style="text-decoration:none;background:#1e3a8a;color:#fff;border-color:#1e3a8a">💬 View in InterLink Chat</a>
          </div>
        </div>
      `;
      $('#et-copy-appno')?.addEventListener('click', (evBtn) => {
        if (navigator.clipboard) navigator.clipboard.writeText(appNo).catch(() => {});
        evBtn.currentTarget.textContent = 'Copied!';
      });
    }
  });

  S.rows = [{}];
  S.fibers = [];
  applyTemplate('', false);
  $$('[data-only="material"]').forEach((d) => { d.style.display = 'none'; });
  renderRows();
  renderFibers();
  loadStatus(); setInterval(loadStatus, 20000);
  loadLabrefs();
  poll();
})();
