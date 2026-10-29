/**
 * NEXUS-L <-> Intertek InterLink 2.0 Portal & 1-Click Report Sync Controller
 * Covers:
 * - SearchFormWithHeader.aspx [10102] (Top Criteria Panel + 32-Column Grid with E & U actions)
 * - NormalForm.aspx           [10000] (Online Application Form - 'E' button)
 * - UploadForm.aspx           [10072] (Finished Product Form - Tab 1 + Tab 2 Upload Result)
 * - UploadForm.aspx           [10076] (Material Form - Tab 1 + Tab 2 Upload Result)
 * - Pre-Sync Verification Dialog & 1-Click Live Report Submission
 */
(() => {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const baseMeta = $('meta[name="base-url"]');
  const BASE_URL = baseMeta ? baseMeta.content.replace(/\/+$/, '') + '/' : location.origin + '/';
  const csrfMeta = $('meta[name="X-CSRF-TOKEN"]');

  const initJsonEl = $('#il-initial-data');
  let state = {
    reports: [],
    logs: [],
    labrefs: [],
    totalLabRefs: 0,
    selectedLabRef: null,
    currentView: 'search', // 'search' | 'upload' | 'normal'
    activeAppNo: null,
    activeSamples: [],
    activeItems: [],
    activeReadings: [],
    historyStack: ['search'],
    historyIndex: 0,
    currentPage: 1,
    totalPages: 517,
    totalItems: 7749,
    pageSize: 15,
    changedApps: [],
    isFetchingPage: false,
    autoSyncTimer: null,
  };

  if (initJsonEl) {
    try {
      const parsed = JSON.parse(initJsonEl.textContent);
      state.reports = parsed.reports || [];
      state.logs = parsed.logs || [];
      state.labrefs = parsed.labrefs || [];
      state.totalLabRefs = parsed.totalLabRefs || 0;
    } catch (e) {
      console.error('[InterLink] Failed to parse initial JSON:', e);
    }
  }

  // Complete list of UploadForm.aspx [10072] / [10076] fields for the Pre-Sync Confirmation Dialog
  const FORM_FIELD_DEFINITIONS = [
    // Section 1: Top Service & Header (Tab 1)
    { key: 'service_provider', label: '* Service Provider', section: 'Tab 1 · Top Header' },
    { key: 'lab_location', label: '* Lab Location', section: 'Tab 1 · Top Header' },
    { key: 'application_no', label: 'Application No.', section: 'Tab 1 · Top Header', readonly: true },
    { key: 'service_test_type', label: '* Service Test Type', section: 'Tab 1 · Top Header' },
    { key: 'test_stage', label: '* Test Stage', section: 'Tab 1 · Top Header' },
    { key: 'service_required', label: '* Service Required', section: 'Tab 1 · Top Header' },
    { key: 'report_no', label: '* Report No.', section: 'Tab 1 · Top Header' },
    { key: 'is_retest', label: '* Is this a Retest?', section: 'Tab 1 · Top Header', type: 'select', options: ['No', 'Yes'] },
    { key: 'previous_report_no', label: 'Previous Test Report No', section: 'Tab 1 · Top Header' },
    { key: 'related_trf_no', label: 'Related TRF Number', section: 'Tab 1 · Top Header' },
    { key: 'email_recipients', label: 'Email Notification Recipient(s)', section: 'Tab 1 · Top Header' },

    // Section 2: Vendor / Factory / Supplier / Facility Information (Tab 1)
    { key: 'company_name', label: '* Company Name', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'interlink_code', label: '* Interlink Code', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'vendor_name', label: '* Vendor Name', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'vendor_id', label: '* Vendor ID', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'factory_name', label: '* Factory Name', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'factory_id', label: '* Factory ID', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'supplier_name', label: 'Supplier Name', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'supplier_id', label: 'Supplier ID', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'facility_name', label: 'Facility Name (Material)', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'facility_id', label: 'Facility ID (Material)', section: 'Tab 1 · Vendor/Factory/Supplier' },

    // Section 3A: Product / Material Information (ALL) (Tab 1)
    { key: 'sample_type', label: '* Sample Type', section: 'Tab 1 · Product/Material Info (ALL)', readonly: true },
    { key: 'style_no', label: '* Style No.', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'style_color_no', label: '* Style Color No. / SKU No.', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'product_name', label: '* Product Name', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'buyer_brand', label: '* Brand Name', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'color_code', label: 'Color Code', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'colorway_name', label: '* Color Description', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'product_export_to', label: '* Product Export To', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'country_of_origin', label: '* Country of Origin', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'season', label: '* Season', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'sourcing_office', label: '* Sourcing Office', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'size_category', label: '* Size Category', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'age_group', label: '* Age Group', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'cargo_receive_date', label: '* Cargo Receive Date', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'product_category', label: '* Product Category', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'test_packages', label: '* Test Package(s)', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'material_code', label: 'Material Code', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'material_name', label: 'Material Description', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'material_type', label: 'Material Type', section: 'Tab 1 · Product/Material Info (ALL)' },

    // Section 3B: GCC/CPC & Factory Address (Tab 1)
    { key: 'gcc_cpc_required', label: 'GCC/CPC Required?', section: 'Tab 1 · GCC/CPC & Factory Address', type: 'select', options: ['No', 'Yes'] },
    { key: 'product_id', label: 'Product ID', section: 'Tab 1 · GCC/CPC & Factory Address' },
    { key: 'manufacture_date', label: 'Manufacture Date', section: 'Tab 1 · GCC/CPC & Factory Address' },
    { key: 'factory_address', label: 'Factory Address', section: 'Tab 1 · GCC/CPC & Factory Address' },
    { key: 'factory_country', label: 'Factory Country', section: 'Tab 1 · GCC/CPC & Factory Address' },
    { key: 'factory_city', label: 'Factory City', section: 'Tab 1 · GCC/CPC & Factory Address' },
    { key: 'factory_state_province', label: 'Factory State/Province', section: 'Tab 1 · GCC/CPC & Factory Address' },

    // Section 4: Upload Result Tab (Tab 2)
    { key: 'report_issue_date', label: '* Report Issue Date', section: 'Tab 2 · Upload Result' },
    { key: 'overall_result', label: '* Overall Result', section: 'Tab 2 · Upload Result', type: 'select', options: ['Pass', 'Fail', 'Data'] },
    { key: 'attachment_name', label: '* Report Attachment PDF', section: 'Tab 2 · Upload Result' },
  ];

  const escHtml = (str) => {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
  };

  const showToast = (msg, isError = false) => {
    const t = $('#il-toast');
    if (!t) return;
    t.textContent = msg;
    t.style.background = isError ? '#b91c1c' : '#065f46';
    t.classList.remove('hidden');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.add('hidden'), 4600);
  };

  const updateCsrf = (token) => {
    if (token && csrfMeta) csrfMeta.content = token;
  };

  const apiFetch = async (path, options = {}) => {
    const headers = Object.assign({
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
    }, options.headers || {});

    if (csrfMeta && csrfMeta.content) {
      headers['X-CSRF-TOKEN'] = csrfMeta.content;
    }

    const res = await fetch(BASE_URL + path, Object.assign({}, options, {
      credentials: 'same-origin',
      headers,
    }));
    const data = await res.json().catch(() => ({}));
    if (data && data.csrf) updateCsrf(data.csrf);
    if (!res.ok) throw new Error(data.error || `Request failed (${res.status})`);
    return data;
  };

  // Report PDF of a Digital Test Report record ({slug, id: DTR record id}), as base64: the DTR page opens the record in a
  // hidden frame, builds its print sheet and turns it into a PDF with dtr/sheet-pdf (see the &export=1 hook in dtr.js)
  window.nxReportPdf = (lab) => new Promise((resolve, reject) => {
    const f = document.createElement('iframe');
    f.style.cssText = 'position:fixed;left:-9999px;top:0;width:1200px;height:900px;border:0';
    f.src = `${BASE_URL}dtr?open=${encodeURIComponent(lab.slug)}:${encodeURIComponent(lab.id)}&preview=1&export=1`;
    const done = (fn, v) => { clearTimeout(timer); window.removeEventListener('message', on); f.remove(); fn(v); };
    const on = (e) => { if (e.source === f.contentWindow && e.data && e.data.type === 'NXL_REPORT_PDF') { e.data.ok ? done(resolve, e.data.b64) : done(reject, new Error(e.data.error)); } };
    const timer = setTimeout(() => done(reject, new Error('The report PDF took too long to create.')), 120000);
    window.addEventListener('message', on);
    document.body.appendChild(f);
  });

  // The finished report sheet (HTML) of a Digital Test Report record: what a Manager / Supervisor reads before signing
  window.nxReportHtml = (lab) => new Promise((resolve, reject) => {
    const f = document.createElement('iframe');
    f.style.cssText = 'position:fixed;left:-9999px;top:0;width:1200px;height:900px;border:0';
    f.src = `${BASE_URL}dtr?open=${encodeURIComponent(lab.slug)}:${encodeURIComponent(lab.id)}&preview=1&export=html`;
    const done = (fn, v) => { clearTimeout(timer); window.removeEventListener('message', on); f.remove(); fn(v); };
    const on = (e) => { if (e.source === f.contentWindow && e.data && e.data.type === 'NXL_REPORT_HTML') { e.data.ok ? done(resolve, e.data.html) : done(reject, new Error(e.data.error)); } };
    const timer = setTimeout(() => done(reject, new Error('The report sheet took too long to prepare.')), 60000);
    window.addEventListener('message', on);
    document.body.appendChild(f);
  });

  // =========================================================================
  // 1. RENDER SEARCH GRID (SearchFormWithHeader.aspx [10102] - All 38 Columns & 517 Pages)
  // =========================================================================
  const getActiveFilters = () => {
    const filters = {};
    $$('#itk-search-table .itk-filter-row input[data-col]').forEach((inp) => {
      const v = inp.value.trim().toLowerCase();
      if (v) filters[inp.dataset.col] = v;
    });
    $$('#itk-search-criteria-panel [data-top-filter]').forEach((inp) => {
      const v = inp.value.trim().toLowerCase();
      if (v) filters[inp.dataset.topFilter] = v;
    });
    const gs = ($('#itk-general-search')?.value || '').trim().toLowerCase();
    if (gs) filters.__all = gs;
    return filters;
  };

  const renderPager = () => {
    if (state.searchMode) {
      const info = state.searchInfo || {};
      const cp = $('#itk-curr-page'); const tp = $('#itk-total-pages');
      if (cp) cp.textContent = '1';
      if (tp) tp.textContent = '1';
      const nums = $('#itk-pager-nums');
      if (nums) nums.innerHTML = `<span style="padding:4px 8px;font-size:12px;color:#475569">Search results: ${state.reports.length}${info.capped ? '+' : ''} of ${info.loaded || 0} loaded applications${info.complete ? '' : ' (still loading the rest…)'}</span>`;
      return;
    }
    const currPageEl = $('#itk-curr-page');
    const totalPagesEl = $('#itk-total-pages');
    const totalItemsEl = $('#itk-total-items');
    const numsEl = $('#itk-pager-nums');
    const jumpInput = $('#itk-jump-input');

    if (currPageEl) currPageEl.textContent = state.currentPage;
    if (totalPagesEl) totalPagesEl.textContent = state.totalPages;
    if (totalItemsEl) totalItemsEl.textContent = state.totalItems;
    if (jumpInput) {
      jumpInput.max = state.totalPages;
      if (document.activeElement !== jumpInput) {
        jumpInput.value = state.currentPage;
      }
    }

    if (!numsEl) return;

    const p = state.currentPage;
    const total = state.totalPages;
    const items = [];

    // First & Prev
    items.push(`<button type="button" class="itk-pg-btn" data-goto-page="1" ${p <= 1 ? 'disabled' : ''} title="First Page">«</button>`);
    items.push(`<button type="button" class="itk-pg-btn" data-goto-page="${Math.max(1, p - 1)}" ${p <= 1 ? 'disabled' : ''} title="Previous Page">‹</button>`);

    // Window of pages around currentPage plus last 3 pages (just like DevExpress DXPagerTop: [1] 2 3 4 5 6 7 ... 515 516 517)
    const pagesToShow = new Set();
    if (p <= 6) {
      for (let i = 1; i <= Math.min(7, total); i++) pagesToShow.add(i);
    } else {
      pagesToShow.add(1);
      pagesToShow.add(2);
      for (let i = Math.max(1, p - 2); i <= Math.min(total, p + 2); i++) pagesToShow.add(i);
    }
    for (let i = Math.max(1, total - 2); i <= total; i++) {
      pagesToShow.add(i);
    }

    const sorted = [...pagesToShow].sort((a, b) => a - b);
    let prev = 0;
    sorted.forEach((pg) => {
      if (prev && pg - prev > 1) {
        items.push(`<span class="itk-pg-ellipsis">...</span>`);
      }
      if (pg === p) {
        items.push(`<b class="itk-pg-curr">[${pg}]</b>`);
      } else {
        items.push(`<a href="javascript:void(0)" class="itk-pg-link" data-goto-page="${pg}">${pg}</a>`);
      }
      prev = pg;
    });

    // Next & Last
    items.push(`<button type="button" class="itk-pg-btn" data-goto-page="${Math.min(total, p + 1)}" ${p >= total ? 'disabled' : ''} title="Next Page">›</button>`);
    items.push(`<button type="button" class="itk-pg-btn" data-goto-page="${total}" ${p >= total ? 'disabled' : ''} title="Last Page (${total})">»</button>`);

    numsEl.innerHTML = items.join('');
  };

  const renderSearchGrid = () => {
    const tbody = $('#itk-grid-tbody');
    if (!tbody) return;

    renderPager();

    const filters = getActiveFilters();
    const filtered = state.searchMode ? state.reports : state.reports.filter((r) => {
      return Object.keys(filters).every((col) => {
        const cellVal = String(r[col] ?? '').toLowerCase();
        return cellVal.includes(filters[col]);
      });
    });

    const totalCount = state.totalItems || state.reports.length;
    const syncedCount = state.reports.filter((r) =>
      Number(r.synced_to_interlink) === 1 ||
      (r.interlink_report_no && r.interlink_report_no !== '') ||
      r.job_status === 'In Progress' ||
      r.job_status === 'Completed'
    ).length;
    const pendingCount = Math.max(0, state.reports.length - syncedCount);

    if ($('#stat-total')) $('#stat-total').textContent = `${state.reports.length} / ${totalCount}`;
    if ($('#stat-pending')) $('#stat-pending').textContent = pendingCount;
    if ($('#stat-synced')) $('#stat-synced').textContent = syncedCount;

    if (!filtered.length) {
      tbody.innerHTML = `<tr><td colspan="38" style="text-align:center;padding:20px;color:#6b7280">${state.searchMode ? `Nothing matches in all ${(state.searchInfo && state.searchInfo.loaded) || 0} loaded applications.${state.searchInfo && !state.searchInfo.complete ? ' The rest of InterLink is still being loaded in the background; try again in a few minutes.' : ''}` : `No matching InterLink applications found on Page ${state.currentPage}.`}</td></tr>`;
      return;
    }

    tbody.innerHTML = filtered.map((r) => {
      const isSynced = Number(r.synced_to_interlink) === 1;
      const liveRepNo = r.interlink_report_no !== undefined ? r.interlink_report_no : (isSynced ? r.report_no : '');
      const liveIssueDate = r.interlink_report_issue_date !== undefined ? r.interlink_report_issue_date : (isSynced ? r.report_issue_date : '');
      const liveOverall = r.interlink_overall_result !== undefined ? r.interlink_overall_result : (isSynced ? r.overall_result : '');

      const hasReport = Boolean(r.has_detail_page || liveRepNo || isSynced);
      const shownReportNo = hasReport ? escHtml(liveRepNo || r.report_no || '') : '';
      const shownIssueDate = hasReport ? escHtml(liveIssueDate || r.report_issue_date || '') : '';
      const shownResult = hasReport && (liveOverall || r.overall_result)
        ? `<span class="itk-sync-pill ${String(liveOverall || r.overall_result).toLowerCase() === 'fail' ? 'itk-sync-no' : 'itk-sync-yes'}">${escHtml(liveOverall || r.overall_result)}</span>`
        : '';

      const jobStatus = r.job_status || 'Submitted';
      let statusHtml = escHtml(jobStatus);
      if (jobStatus === 'In Progress' || jobStatus === 'Completed') {
        statusHtml = `<span class="itk-sync-pill itk-sync-yes">${escHtml(jobStatus)}</span>`;
      } else if (isSynced) {
        statusHtml = `<span class="itk-sync-pill itk-sync-yes">Synced ✓</span>`;
      }

      const isChanged = state.changedApps && state.changedApps.includes(r.application_no);

      return `
        <tr data-app-row="${escHtml(r.application_no)}" class="${isChanged ? 'itk-row-changed' : ''}">
          <td style="text-align:center">
            ${hasReport
              ? `<button type="button" class="itk-eu-btn" data-open-detail="${escHtml(r.application_no)}" title="Report Detail Page"><svg class="ic" aria-hidden="true"><use href="#i-file"/></svg></button>`
              : ''}
          </td>
          <td style="text-align:center">${hasReport ? `<button type="button" class="itk-eu-btn" data-open-clip="${escHtml(r.application_no)}" title="Report Attachment"><svg class="ic" aria-hidden="true"><use href="#i-paperclip"/></svg></button>` : ''}</td>
          <td>
            <div class="itk-action-cell">
              <button type="button" class="itk-eu-btn" data-open-normal="${escHtml(r.application_no)}" title="Open Online Application Form NormalForm.aspx [10000] (E)">E</button>
              <button type="button" class="itk-eu-btn" data-open-umenu="${escHtml(r.application_no)}" title="Upload Form: Add or Edit report">U</button>
              <button type="button" class="itk-eu-btn" data-open-upload="${escHtml(r.application_no)}" title="Open the filled-up upload form with the attachment">&#8593;</button>
            </div>
          </td>
          <td></td>
          <td>${escHtml(r.sample_type || '')}</td>
          <td>
            <a href="javascript:void(0)" class="itk-app-link" data-open-upload="${escHtml(r.application_no)}">${escHtml(r.application_no)}</a>
          </td>
          <td>${escHtml(r.trf_submission_date || '')}</td>
          <td><b>${shownReportNo}</b></td>
          <td>${shownIssueDate}</td>
          <td>${statusHtml}</td>
          <td>${shownResult}</td>
          <td>${escHtml(r.company_name || '')}</td>
          <td>${escHtml(r.vendor_id || '')}</td>
          <td>${escHtml(r.vendor_name || '')}</td>
          <td>${escHtml(r.factory_id || '')}</td>
          <td>${escHtml(r.factory_name || '')}</td>
          <td>${escHtml(r.supplier_id || '')}</td>
          <td>${escHtml(r.supplier_name || '')}</td>
          <td>${escHtml(r.buyer_brand || 'Vans')}</td>
          <td>${escHtml(r.test_stage || 'Production')}</td>
          <td>${escHtml(r.product_export_to || '')}</td>
          <td>${escHtml(r.service_test_type || 'In-House Lab Testing')}</td>
          <td>${escHtml(r.service_provider || 'DATIAN SUBIC SHOES INC.')}</td>
          <td>${escHtml(r.gcc_cpc_required || 'No')}</td>
          <td><b>${escHtml(r.style_no || '')}</b></td>
          <td><b>${escHtml(r.material_code || '')}</b></td>
          <td><span class="mono">${escHtml(r.style_color_no || '')}</span></td>
          <td>${escHtml(r.season || 'S27')}</td>
          <td>${escHtml(r.sourcing_office || 'ASO')}</td>
          <td>${escHtml(r.test_packages || '')}</td>
          <td>${escHtml(r.color_code || '')}</td>
          <td>${escHtml(r.colorway_name || '')}</td>
          <td>${escHtml(r.lab_location || 'PHILIPPINES')}</td>
          <td>${escHtml(r.review_result || '')}</td>
          <td>${escHtml(r.review_status || '')}</td>
          <td>${escHtml(r.related_trf_no || '')}</td>
          <td>${escHtml(r.latest_review_comment || '')}</td>
          <td>${escHtml(r.cancellation_reason || '')}</td>
        </tr>
      `;
    }).join('');
  };

  /**
   * Fetches any page (1..517) and pageSize live from InterLink 2.0's SearchFormWithHeader.aspx
   * BuilderGridView, upserts into dbnexus, and updates the grid in real time.
   */
  // Reading InterLink itself: NEXUS-L's server holds no InterLink login (see pullInterlinkGridPage() server-side), so the
  // only way to show InterLink's own pages is through the sender extension, in the user's own signed-in browser. It reads
  // InterLink's grid pages (15 rows each) in a background tab it opens and closes by itself - nobody has to keep InterLink
  // open. Without the extension this view can only show NEXUS-L's saved copy, which holds far fewer rows than InterLink.
  // Reading needs sender 1.3.1 or newer (older ones answer in a format this page cannot match, which used to look like an
  // endless "loading"): the page checks the version, and every request must be acknowledged within a few seconds.
  const MIN_SENDER = '1.3.1';
  const senderVersion = () => document.documentElement.dataset.nxlSenderVersion || '';
  const versionAtLeast = (v, min) => {
    const a = String(v || '0').split('.').map(Number);
    const b = min.split('.').map(Number);
    for (let i = 0; i < 3; i++) { if ((a[i] || 0) !== (b[i] || 0)) return (a[i] || 0) > (b[i] || 0); }
    return true;
  };
  const senderInstalled = () => document.documentElement.dataset.nxlSender === '1';
  const hasGridSender = () => senderInstalled() && versionAtLeast(senderVersion(), MIN_SENDER);
  let pullSeq = 0;
  const nxPullGrid = ({ firstPage, count, silent }) => new Promise((resolve, reject) => {
    const reqId = `g${Date.now()}_${++pullSeq}`;
    const cleanup = () => { clearTimeout(timer); clearTimeout(ackTimer); window.removeEventListener('message', onMsg); };
    const onMsg = (e) => {
      if (e.source !== window || !e.data || e.data.reqId !== reqId) return;
      if (e.data.type === 'NXL_GRID_ACK') clearTimeout(ackTimer);
      if (e.data.type === 'NXL_GRID_PROGRESS' && !silent) { const sub = $('#il-loading-sub'); if (sub && e.data.message) sub.textContent = e.data.message; }
      if (e.data.type === 'NXL_GRID_HTML') { cleanup(); resolve(e.data.pages || []); }
      if (e.data.type === 'NXL_GRID_STATUS' && e.data.ok === false) { cleanup(); reject(new Error(e.data.message || 'Could not read InterLink.')); }
    };
    const ackTimer = setTimeout(() => { cleanup(); reject(new Error('The extension did not answer. Reload this page (Ctrl+F5); if that does not help, update the NEXUS-L InterLink Sender extension.')); }, 5000);
    const timer = setTimeout(() => { cleanup(); reject(new Error('InterLink took too long to answer.')); }, 150000);
    window.addEventListener('message', onMsg);
    window.postMessage({ type: 'NXL_PULL_GRID', reqId, firstPage, count, silent: !!silent }, location.origin);
  });
  // The NEXUS-L view page `page` of `pageSize` rows, read from InterLink: the InterLink pages that cover it are read, saved
  // into NEXUS-L's copy, and the rows shown are InterLink's own, in InterLink's order.
  const liveViewPage = async (page, pageSize, silent) => {
    const start = (page - 1) * pageSize;
    const firstPage = Math.floor(start / 15) + 1;
    const lastPage = Math.floor((start + pageSize - 1) / 15) + 1;
    const pages = await nxPullGrid({ firstPage, count: lastPage - firstPage + 1, silent });
    return apiFetch('interlink/pull-grid-html', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ pages, firstPage, page, pageSize }),
    });
  };

  const fetchLiveInterlinkPage = async (page = state.currentPage, pageSize = state.pageSize, forceRefresh = false, silent = false) => {
    if (state.isFetchingPage) return;
    state.isFetchingPage = true;

    const liveDot = $('#itk-live-dot');
    const liveText = $('#itk-live-text');
    if (liveDot) liveDot.className = 'itk-live-dot is-syncing';
    if (liveText) liveText.textContent = `Syncing Page ${page}...`;

    // Page clicks and the Live Refresh button read InterLink. Timer-driven and first-load calls (silent) only do so while
    // the reader tab is still open from a recent read (it closes by itself about a minute after the last one): they never
    // open a tab on their own, so nothing pops up while you work.
    const useSender = hasGridSender() && (!silent || Date.now() < (state.liveWarmUntil || 0));
    if (!silent && senderInstalled() && !hasGridSender() && !state.warnedOldSender) {
      state.warnedOldSender = true;
      showToast(`Your NEXUS-L InterLink Sender is out of date${senderVersion() ? ` (v${senderVersion()})` : ''}: pages are shown from NEXUS-L's saved copy only. Download v${MIN_SENDER} or newer with "Install the sender", reload it in chrome://extensions, then reload this page.`, true);
    }

    try {
      if (!silent) {
        beginLoading(useSender ? `Reading page ${page} from InterLink through your browser… (the first read can take about 20 seconds)` : `Loading page ${page} of ${state.totalPages} from NEXUS-L…`, useSender ? 150000 : 30000);
      }

      let res = null;
      if (useSender) {
        try {
          res = await liveViewPage(page, pageSize, silent);
          if (!silent) state.liveWarmUntil = Date.now() + 55000;   // only your own clicks keep the reader tab in use
        } catch (err) {
          if (!silent) showToast(`${err.message} Showing NEXUS-L's saved copy instead.`, true);
        }
      }
      if (!res) {
        res = await apiFetch('interlink/fetch-live-page', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            page: Number(page) || 1,
            pageSize: Number(pageSize) || 15,
            forceRefresh: Boolean(forceRefresh),
          }),
        });
      }

      if (res && Array.isArray(res.rows)) {
        state.currentPage = Number(res.currentPage) || page;
        state.totalPages = Number(res.totalPages) || 517;
        state.totalItems = Number(res.totalItems) || 7749;
        state.pageSize = Number(res.pageSize) || pageSize;
        state.reports = res.rows;
        state.changedApps = res.changedApps || [];

        renderSearchGrid();

        const nowStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        if (liveDot) liveDot.className = res.live ? 'itk-live-dot is-online' : 'itk-live-dot';
        if (liveText) {
          liveText.textContent = res.live
            ? `Live Synced (${nowStr})`
            : `Cached DB (${nowStr})`;
        }

        if (state.changedApps.length > 0 && silent) {
          showToast(`Real-time InterLink update detected on ${state.changedApps.join(', ')}!`);
        } else if (!silent) {
          showToast(`✓ Loaded Page ${state.currentPage} of ${state.totalPages} (${state.reports.length} rows, ${state.totalItems} total items).`);
        }
      }
    } catch (err) {
      if (liveDot) liveDot.className = 'itk-live-dot is-error';
      if (liveText) liveText.textContent = 'Sync Offline';
      if (!silent) showToast(err.message || 'Could not fetch live page from InterLink.', true);
    } finally {
      state.isFetchingPage = false;
      if (!silent) endLoading();
    }
  };

  // =========================================================================
  // 2. NAVIGATION BETWEEN SearchFormWithHeader, UploadForm, AND NormalForm
  // =========================================================================
  const showLoading = (subtitle, duration = 340) => {
    const overlay = $('#il-loading-overlay');
    const sub = $('#il-loading-sub');
    if (!overlay) return Promise.resolve();
    if (sub && subtitle) sub.textContent = subtitle;
    overlay.classList.remove('hidden');
    return new Promise((resolve) => {
      setTimeout(() => {
        overlay.classList.add('hidden');
        resolve();
      }, duration);
    });
  };

  // Loading that stays on screen until the live data has arrived (the InterLink request can take several seconds)
  let loadTimer = null;
  let loadFailsafe = null;
  const beginLoading = (subtitle, maxMs = 30000) => {
    const overlay = $('#il-loading-overlay');
    const sub = $('#il-loading-sub');
    if (sub && subtitle) sub.textContent = subtitle;
    overlay?.classList.remove('hidden');
    clearTimeout(loadTimer);
    loadTimer = setTimeout(() => { if (sub) sub.textContent = 'Still loading from InterLink… this can take a few seconds.'; }, 2500);
    clearTimeout(loadFailsafe);
    loadFailsafe = setTimeout(endLoading, maxMs); // never leave the screen covered
  };
  const endLoading = () => {
    clearTimeout(loadTimer);
    clearTimeout(loadFailsafe);
    $('#il-loading-overlay')?.classList.add('hidden');
  };

  const getCurrentFormValues = () => {
    const vals = {};
    $$('[data-uf-field]').forEach((el) => {
      vals[el.dataset.ufField] = el.value;
    });
    const checkedRadio = $('input[name="uf_is_retest"]:checked');
    vals.is_retest = checkedRadio ? checkedRadio.value : 'No';
    return vals;
  };

  const clearSyncedHighlights = () => {
    $$('.uf-field.is-synced').forEach((w) => w.classList.remove('is-synced'));
    $$('.uf-sync-tag').forEach((t) => {
      if (t.id !== 'uf-samples-badge') t.classList.add('hidden');
    });
  };

  const renderTab2SubGrids = (samples = [], items = [], readings = []) => {
    const sampleTbody = $('#uf-sample-items-tbody');
    if (sampleTbody) {
      sampleTbody.innerHTML = samples.length
        ? samples.map((s) => `
            <tr>
              <td><input type="text" value="${escHtml(s.sample_id_code || 'S001')}"></td>
              <td><input type="text" value="${escHtml(s.sample_description || '')}"></td>
              <td><input type="text" value="${escHtml(s.sample_color || '')}"></td>
            </tr>
          `).join('')
        : `<tr><td colspan="3" class="muted" style="text-align:center">No sample items.</td></tr>`;
    }

    const itemsTbody = $('#uf-test-items-tbody');
    if (itemsTbody) {
      itemsTbody.innerHTML = items.length
        ? items.map((it) => `
            <tr>
              <td><input type="text" value="${escHtml(it.sample_id_code || 'S001')}"></td>
              <td><input type="text" value="${escHtml(it.test_name || '')}"></td>
              <td><input type="text" value="${escHtml(it.test_method_standard || '')}"></td>
              <td><input type="text" value="${escHtml(it.actual_result_summary || '')}"></td>
              <td>
                <select>
                  <option value="Pass" ${it.test_result === 'Pass' ? 'selected' : ''}>Pass</option>
                  <option value="Fail" ${it.test_result === 'Fail' ? 'selected' : ''}>Fail</option>
                  <option value="Data" ${it.test_result === 'Data' ? 'selected' : ''}>Data</option>
                </select>
              </td>
            </tr>
          `).join('')
        : `<tr><td colspan="5" class="muted" style="text-align:center">No test items.</td></tr>`;
    }

    const readingsTbody = $('#uf-test-readings-tbody');
    if (readingsTbody) {
      readingsTbody.innerHTML = readings.length
        ? readings.map((rd) => `
            <tr>
              <td><input type="text" value="${escHtml(rd.sample_id_code || 'S001')}"></td>
              <td><input type="text" value="${escHtml(rd.test_item_name || '')}"></td>
              <td><input type="text" value="${escHtml(rd.test_method || '')}"></td>
              <td><input type="text" value="${escHtml(rd.sample_component || '')}"></td>
              <td><input type="text" value="${escHtml(rd.test_element || '')}"></td>
              <td><input type="text" value="${escHtml(rd.test_value_indicator || '=')}"></td>
              <td><input type="text" value="${escHtml(rd.reading_value_raw || '')}"></td>
              <td><input type="text" value="${escHtml(rd.unit || '')}"></td>
              <td><input type="text" value="${escHtml(rd.target_spec || '')}"></td>
              <td>
                <select>
                  <option value="Pass" ${rd.reading_status === 'Pass' ? 'selected' : ''}>Pass</option>
                  <option value="Fail" ${rd.reading_status === 'Fail' ? 'selected' : ''}>Fail</option>
                  <option value="Data" ${rd.reading_status === 'Data' ? 'selected' : ''}>Data</option>
                </select>
              </td>
            </tr>
          `).join('')
        : `<tr><td colspan="10" class="muted" style="text-align:center">No readings.</td></tr>`;
    }
  };

  // Test grids of an application that has no saved ones yet: taken from its Lab Reference (the one linked by TR No.)
  const fillGridsFromLabRef = async () => {
    if (state.activeItems && state.activeItems.length) return;
    const labRef = currentLabRef();
    if (!labRef) return;
    try {
      const res = await apiFetch(`interlink/labref-detail?lab_ref_no=${encodeURIComponent(labRef)}`);
      if (!res || !res.labref) return;
      state.activeSamples = res.samples || [];
      state.activeItems = res.items || [];
      state.activeReadings = res.readings || [];
      renderTab2SubGrids(state.activeSamples, state.activeItems, state.activeReadings);
    } catch (e) { /* the grids stay as they were */ }
  };

  const populateInitialUploadForm = (report) => {
    clearSyncedHighlights();
    const isAlreadySynced = Number(report.synced_to_interlink) === 1;
    const isMaterial = report.sample_type === 'Material';

    const mainTabBtn = $('#uf-tab-btn-main');
    if (mainTabBtn) {
      mainTabBtn.textContent = isMaterial
        ? 'VF Corporation Report Online Upload Form - Material [10076]'
        : 'VF Corporation Report Online Upload Form - Product [10072]';
    }

    const detectPill = $('#uf-detect-form-pill');
    if (detectPill) {
      detectPill.textContent = isMaterial
        ? '✓ UploadForm.aspx [10076 Material] Reached'
        : '✓ UploadForm.aspx [10072 Product] Reached';
    }

    const modelSpan = $('#uf-banner-model');
    if (modelSpan) {
      modelSpan.textContent = isMaterial
        ? (report.material_name || report.material_code || 'Material')
        : `${report.product_name || 'Footwear'} · ${report.style_color_no || report.style_no || ''}`;
    }

    const initialMap = {
      service_provider: report.service_provider || 'DATIAN SUBIC SHOES INC.',
      lab_location: report.lab_location || 'PHILIPPINES',
      application_no: report.application_no || '',
      service_test_type: report.service_test_type || 'In-House Lab Testing',
      test_stage: report.test_stage || 'Production',
      service_required: report.service_required || 'Regular',
      report_no: isAlreadySynced ? (report.report_no || '') : '',
      previous_report_no: isAlreadySynced ? (report.previous_report_no || '') : '',
      related_trf_no: report.related_trf_no || '',
      email_recipients: report.email_recipients || 'dtp_qcasst@dtshoes.com',
      company_name: report.company_name || 'KEY TRADE CO., LTD',
      interlink_code: report.interlink_code || 'E126863',
      vendor_name: report.vendor_name || 'KEY TRADE CO., LTD',
      vendor_id: report.vendor_id || '0000507450',
      factory_name: report.factory_name || 'DATIAN SUBIC SHOES INC.',
      factory_id: report.factory_id || '0000507452',
      supplier_name: report.supplier_name || '',
      supplier_id: report.supplier_id || '',
      facility_name: report.facility_name || '',
      facility_id: report.facility_id || '',
      sample_type: report.sample_type || 'Finished Product',
      style_no: report.style_no || '',
      style_color_no: report.style_color_no || '',
      product_name: report.product_name || '',
      buyer_brand: report.buyer_brand || 'Vans',
      color_code: report.color_code || '',
      colorway_name: report.colorway_name || '',
      product_export_to: report.product_export_to || 'APAC - Non China',
      country_of_origin: report.country_of_origin || 'Philippines',
      season: report.season || 'S27',
      sourcing_office: report.sourcing_office || 'ASO',
      size_category: report.size_category || 'UNISEX',
      age_group: report.age_group || 'Adult',
      cargo_receive_date: report.cargo_receive_date || '06 Nov 2026',
      product_category: report.product_category || 'Footwear',
      test_packages: report.test_packages || 'VANS Pull Strength',
      care_instruction: report.care_instruction || '',
      fiber_composition: report.fiber_composition || '',
      material_code: report.material_code || '',
      material_name: report.material_name || '',
      material_type: report.material_type || '',
      thickness: report.thickness || '',
      finishing: report.finishing || '',
      end_use: report.end_use || '',
      lab_remarks: report.lab_remarks || '',
      gcc_cpc_required: report.gcc_cpc_required || 'No',
      product_id: report.product_id || report.style_no || '',
      manufacture_date: report.manufacture_date || '10/2026',
      factory_address: report.factory_address || 'Lot 1P7-A,Phase 2,Subic Bay GatewayPark, Subic Bay Freeport Zone',
      factory_country: report.factory_country || 'Philippines',
      factory_city: report.factory_city || 'Subic Bay',
      factory_state_province: report.factory_state_province || '03',
      factory_zip_postal: report.factory_zip_postal || '2222',
      factory_email: report.factory_email || 'dtp_qcasst@dtshoes.com',
      report_issue_date: isAlreadySynced ? (report.report_issue_date || '') : '',
      overall_result: isAlreadySynced ? (report.overall_result || 'Pass') : '',
      attachment_name: isAlreadySynced ? (report.attachment_name || '') : '',
    };

    Object.keys(initialMap).forEach((k) => {
      const el = $(`[data-uf-field="${k}"]`);
      if (el) el.value = initialMap[k];
    });

    const tab2Rep = $('#uf_tab2_report_no');
    if (tab2Rep) tab2Rep.value = initialMap.report_no;

    // a report PDF generated or browsed for another application is not shown here
    if (window.nxAttachedPdf && window.nxAttachedPdf.app !== report.application_no) {
      window.nxAttachedPdf = null;
      $('#uf-att-meta')?.classList.add('hidden');
      $('#uf-apv')?.classList.add('hidden');
      clearInterval(apvTimer);
    }

    const retestVal = isAlreadySynced ? (report.is_retest || 'No') : 'No';
    $$('input[name="uf_is_retest"]').forEach((r) => {
      r.checked = r.value === retestVal;
    });
  };

  const navigateToUploadForm = async (appNo, pushHistory = true) => {
    beginLoading(`Opening UploadForm for ${appNo}…`);

    let report = state.reports.find((r) => r.application_no === appNo) || { application_no: appNo };
    state.currentView = 'upload';
    state.activeAppNo = appNo;

    if (pushHistory) {
      state.historyStack = state.historyStack.slice(0, state.historyIndex + 1);
      state.historyStack.push(`upload:${appNo}`);
      state.historyIndex = state.historyStack.length - 1;
    }

    const reveal = () => {
      populateInitialUploadForm(report);
      setTimeout(() => restoreApproval(), 300);   // show the approval state of this application's report, if one was requested
      setTimeout(() => { if (!window.nxAttachedPdf) restoreApproval(); }, 2000);   // again once the Lab Reference list has loaded
      setTimeout(() => fillGridsFromLabRef(), 500);

      // Switch viewport
      $('#il-view-search')?.classList.add('hidden');
      $('#il-view-normal')?.classList.add('hidden');
      $('#il-view-upload')?.classList.remove('hidden');

      // Update Address Bar with the real u_form_url if available
      const urlInput = $('#il-url-input');
      if (urlInput) {
        urlInput.value = report.u_form_url || `${urlInput.dataset.uploadUrl}&appNo=${encodeURIComponent(appNo)}`;
      }

      const badge = $('#il-page-badge');
      if (badge) {
        const fCode = report.sample_type === 'Material' ? '10076' : '10072';
        badge.textContent = `UploadForm.aspx [${fCode}]`;
        badge.className = 'il-page-badge il-badge-upload';
      }

      // Reveal the SYNC and 1-Click Send buttons now that the technician has reached UploadForm.aspx
      $('#il-step-hint')?.classList.add('hidden');
      $('#il-upload-actions')?.classList.remove('hidden');
      const appTag = $('#sync-btn-appno');
      if (appTag) appTag.textContent = appNo;

      const bannerApp = $('#uf-banner-appno');
      if (bannerApp) bannerApp.textContent = appNo;

      updateNavButtons();

    };
    reveal();

    // Fetch live UploadForm.aspx [10072] / [10076] fields + Sample Items, Test Items, and Test Readings
    try {
      const res = await apiFetch('interlink/fetch-live-form', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          application_no: appNo,
          form_type: 'U',
          target_url: report.u_form_url || '',
        }),
      });
      if (res && res.report) {
        report = Object.assign({}, report, res.report);
        const idx = state.reports.findIndex((r) => r.application_no === appNo);
        if (idx >= 0) state.reports[idx] = report;
      }
      state.activeSamples = res.samples || [];
      state.activeItems = res.items || [];
      state.activeReadings = res.readings || [];
      renderTab2SubGrids(state.activeSamples, state.activeItems, state.activeReadings);
    } catch (_) {
      try {
        const fallbackRes = await apiFetch(`interlink/report/${encodeURIComponent(appNo)}`);
        if (fallbackRes.report) report = Object.assign({}, report, fallbackRes.report);
        state.activeSamples = fallbackRes.samples || [];
        state.activeItems = fallbackRes.items || [];
        state.activeReadings = fallbackRes.readings || [];
        renderTab2SubGrids(state.activeSamples, state.activeItems, state.activeReadings);
      } catch (__) {
        renderTab2SubGrids([], [], []);
      }
    }

    reveal();
    endLoading();
  };

  const navigateToNormalForm = async (appNo, pushHistory = true) => {
    beginLoading(`Opening the application form for ${appNo}…`);

    let report = state.reports.find((r) => r.application_no === appNo) || { application_no: appNo };
    state.currentView = 'normal';
    state.activeAppNo = appNo;

    if (pushHistory) {
      state.historyStack = state.historyStack.slice(0, state.historyIndex + 1);
      state.historyStack.push(`normal:${appNo}`);
      state.historyIndex = state.historyStack.length - 1;
    }

    const reveal = () => {
      $('#nf-banner-appno').textContent = appNo;
      $('#nf_application_no').value = report.application_no || appNo;
      $('#nf_company_name').value = report.company_name || 'KEY TRADE CO., LTD';
      $('#nf_interlink_code').value = report.interlink_code || 'E126863';
      $('#nf_applicant_address').value = report.applicant_address || 'Lot 1P7-A,Phase 2,Subic Bay GatewayPark, Subic Bay Freeport Zone';
      $('#nf_contact_person').value = report.contact_person || 'Danica Agbuya';
      $('#nf_contact_email').value = `${report.contact_email || 'dtp_qcasst@dtshoes.com'} | ${report.contact_phone || '(047) 252-2899'}`;
      $('#nf_service_provider').value = report.service_provider || 'DATIAN SUBIC SHOES INC.';
      $('#nf_service_test_type').value = report.service_test_type || 'In-House Lab Testing';
      $('#nf_test_packages').value = report.test_packages || 'VANS Pull Strength';
      $('#nf_style_sku').value = `${report.style_no || report.material_code || ''} / ${report.style_color_no || ''}`;
      $('#nf_product_name').value = report.product_name || report.material_name || '';
      $('#nf_colorway_name').value = report.colorway_name || '';

      $('#il-view-search')?.classList.add('hidden');
      $('#il-view-upload')?.classList.add('hidden');
      $('#il-view-normal')?.classList.remove('hidden');

      const urlInput = $('#il-url-input');
      if (urlInput) {
        urlInput.value = report.e_form_url || urlInput.dataset.normalUrl || urlInput.dataset.searchUrl;
      }

      const badge = $('#il-page-badge');
      if (badge) {
        badge.textContent = 'NormalForm.aspx [10000]';
        badge.className = 'il-page-badge il-badge-search';
      }

      $('#il-upload-actions')?.classList.add('hidden');
      $('#il-step-hint')?.classList.remove('hidden');
      updateNavButtons();

    };
    reveal();

    try {
      const res = await apiFetch('interlink/fetch-live-form', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          application_no: appNo,
          form_type: 'E',
          target_url: report.e_form_url || '',
        }),
      });
      if (res && res.report) {
        report = Object.assign({}, report, res.report);
        const idx = state.reports.findIndex((r) => r.application_no === appNo);
        if (idx >= 0) state.reports[idx] = report;
      }
    } catch (_) {}

    reveal();
    endLoading();
  };

  const navigateToSearchList = async (pushHistory = true) => {
    await showLoading('Returning to SearchFormWithHeader.aspx [10102]...', 240);

    state.currentView = 'search';
    state.activeAppNo = null;

    if (pushHistory) {
      state.historyStack = state.historyStack.slice(0, state.historyIndex + 1);
      state.historyStack.push('search');
      state.historyIndex = state.historyStack.length - 1;
    }

    $('#il-view-upload')?.classList.add('hidden');
    $('#il-view-normal')?.classList.add('hidden');
    $('#il-view-search')?.classList.remove('hidden');

    const urlInput = $('#il-url-input');
    if (urlInput) {
      urlInput.value = urlInput.dataset.searchUrl;
    }

    const badge = $('#il-page-badge');
    if (badge) {
      badge.textContent = 'SearchFormWithHeader.aspx [10102]';
      badge.className = 'il-page-badge il-badge-search';
    }

    $('#il-upload-actions')?.classList.add('hidden');
    $('#il-step-hint')?.classList.remove('hidden');

    renderSearchGrid();
    updateNavButtons();
  };

  const updateNavButtons = () => {
    const backBtn = $('#il-btn-back');
    const fwdBtn = $('#il-btn-forward');
    if (backBtn) backBtn.disabled = state.historyIndex <= 0;
    if (fwdBtn) fwdBtn.disabled = state.historyIndex >= state.historyStack.length - 1;
  };

  // =========================================================================
  // 3. PRE-SYNC CONFIRMATION DIALOG (Shows all data to sync before filling)
  // =========================================================================
  const updateSelectedFieldCount = () => {
    const checks = $$('#sync-fields-tbody .sync-field-check');
    const checkedCount = checks.filter((c) => c.checked).length;
    const countEl = $('#sync-selected-count');
    if (countEl) countEl.textContent = `${checkedCount} of ${checks.length}`;
    const checkAll = $('#sync-check-all');
    if (checkAll) checkAll.checked = checks.length > 0 && checkedCount === checks.length;
  };

  const renderSyncDialogRows = (companyReport) => {
    const tbody = $('#sync-fields-tbody');
    if (!tbody) return;

    const currentFormVals = getCurrentFormValues();

    tbody.innerHTML = FORM_FIELD_DEFINITIONS.map((def) => {
      const currVal = currentFormVals[def.key] ?? '';
      const nexusVal = companyReport[def.key] ?? '';

      let inputControl = '';
      if (def.type === 'select') {
        const opts = (def.options || []).map((o) =>
          `<option value="${escHtml(o)}" ${String(nexusVal) === o ? 'selected' : ''}>${escHtml(o)}</option>`
        ).join('');
        inputControl = `<select data-sync-input="${escHtml(def.key)}">${opts}</select>`;
      } else {
        inputControl = `<input type="text" data-sync-input="${escHtml(def.key)}" value="${escHtml(nexusVal)}" ${def.readonly ? 'readonly' : ''}>`;
      }

      return `
        <tr>
          <td style="text-align:center">
            <input type="checkbox" class="sync-field-check" data-sync-check="${escHtml(def.key)}" checked>
          </td>
          <td>
            <b>${escHtml(def.label)}</b>
            <small class="muted" style="display:block;font-size:11px">${escHtml(def.section)}</small>
          </td>
          <td class="il-curr-val">${currVal !== '' ? escHtml(currVal) : '<em>(empty)</em>'}</td>
          <td>${inputControl}</td>
        </tr>
      `;
    }).join('');

    $$('#sync-fields-tbody .sync-field-check').forEach((chk) => {
      chk.addEventListener('change', updateSelectedFieldCount);
    });
    updateSelectedFieldCount();
  };

  const renderLabRefComboOptions = (rows, selectedId = null) => {
    const combo = $('#dlg-labref-combo');
    if (!combo) return;
    if (!rows || !rows.length) {
      combo.innerHTML = `<option value="">-- No matching LabRefNo found in NEXUS-L --</option>`;
      return;
    }
    combo.innerHTML = rows.map((lr) => {
      const resTag = (lr.overall_result || 'Pass').toUpperCase();
      const styleOrMat = lr.model_style || lr.material_code || lr.material_type || '';
      const trTag = lr.tr_no ? ` · TR: ${lr.tr_no}` : '';
      return `<option value="${escHtml(lr.id)}" data-labref="${escHtml(lr.lab_ref_no)}" ${String(lr.id) === String(selectedId) ? 'selected' : ''}>
        ${escHtml(lr.lab_ref_no)} [${escHtml(lr.form_category)}]${trTag}${styleOrMat ? ' · ' + escHtml(styleOrMat) : ''} · ${escHtml(resTag)}
      </option>`;
    }).join('');
  };

  const renderLabRefTestsPreview = (items, readings) => {
    const tbody = $('#dlg-labref-tests-tbody');
    const countEl = $('#dlg-lr-tests-count');
    if (countEl) {
      countEl.textContent = `${(items || []).length} Test Items · ${(readings || []).length} Specimen Readings`;
    }
    if (!tbody) return;
    if (!items || !items.length) {
      tbody.innerHTML = `<tr><td colspan="6" class="muted" style="text-align:center;padding:10px">No individual test rows recorded on this LabRefNo (Overall Result will still sync).</td></tr>`;
      return;
    }
    tbody.innerHTML = items.map((it) => {
      const st = String(it.result_status || 'Pass');
      const badgeCls = st.toLowerCase() === 'fail' ? 'b-err' : 'b-ok';
      return `
        <tr>
          <td class="mono" style="font-size:11.5px"><b>${escHtml(it.test_code || '')}</b></td>
          <td><b>${escHtml(it.test_name || '')}</b></td>
          <td class="muted" style="font-size:11.5px">${escHtml(it.test_method || '')}</td>
          <td class="mono" style="font-size:11.5px">${escHtml(it.requirement_spec || '')}</td>
          <td class="mono" style="font-size:11.5px"><b>${escHtml(it.actual_reading || '')}</b></td>
          <td><span class="badge ${badgeCls}">${escHtml(it.raw_rating || st)}</span></td>
        </tr>
      `;
    }).join('');
  };

  const applyLabRefToSyncDialog = async (labRefId) => {
    if (!labRefId) return;
    try {
      const res = await apiFetch(`interlink/labref-detail?id=${encodeURIComponent(labRefId)}`);
      if (!res || !res.labref) return;
      const lr = res.labref;
      const dsr = res.dsr || {};
      const mat = res.material || {};
      state.selectedLabRef = lr;

      // Update summary inputs
      const badge = $('#dlg-lr-cat-badge');
      if (badge) badge.textContent = lr.form_category || 'Digital Test Report';
      const title = $('#dlg-lr-title');
      if (title) {
        title.textContent = `${lr.lab_ref_no} — ${lr.material_description || dsr.Material || mat.MaterialName || lr.material_type || 'Lab Test Report'} (${lr.overall_result || 'Pass'})`;
      }

      const reportNoVal = lr.tr_no || lr.lab_ref_no || '';
      const overallVal = (lr.overall_result === 'Fail' || lr.overall_result === 'FAIL')
        ? 'Fail'
        : (lr.overall_result === 'Tolerable' ? 'Data' : 'Pass');
      const matCodeVal = lr.material_code || dsr.VFMaterialCode || mat.MaterialCode || '';
      const matDescVal = lr.material_description || dsr.Material || mat.MaterialName || lr.material_type || '';
      const supplierVal = lr.supplier || dsr.Supplier || mat.Supplier || '';
      const styleVal = lr.model_style || dsr.StyleNumber || '';
      const colorVal = lr.color || dsr.Colorway || dsr.ColorName || '';
      const poLotVal = lr.po_lot_no ? `${lr.po_lot_no}${lr.technician ? ' · ' + lr.technician : ''}` : (lr.technician || '');

      if ($('#dlg-lr-labref')) $('#dlg-lr-labref').value = lr.lab_ref_no || '';
      if ($('#dlg-lr-trno')) $('#dlg-lr-trno').value = reportNoVal;
      if ($('#dlg-lr-result')) $('#dlg-lr-result').value = overallVal;
      if ($('#dlg-lr-mattype')) $('#dlg-lr-mattype').value = lr.material_type || '';
      if ($('#dlg-lr-matcode')) $('#dlg-lr-matcode').value = matCodeVal;
      if ($('#dlg-lr-mtl')) $('#dlg-lr-mtl').value = lr.mtl_no || '';
      if ($('#dlg-lr-matdesc')) $('#dlg-lr-matdesc').value = matDescVal;
      if ($('#dlg-lr-supplier')) $('#dlg-lr-supplier').value = supplierVal;
      if ($('#dlg-lr-style')) $('#dlg-lr-style').value = styleVal;
      if ($('#dlg-lr-color')) $('#dlg-lr-color').value = colorVal;
      if ($('#dlg-lr-polot')) $('#dlg-lr-polot').value = poLotVal;

      // Update Tab 2 items & readings in state and render preview
      state.activeItems = res.items || [];
      state.activeReadings = res.readings || [];
      // Sample Information, Test Items and Readings are the ones of this Lab Reference's own record (server: ReportTests)
      state.activeSamples = (res.samples && res.samples.length) ? res.samples : [{
        sample_id_code: 'S001',
        sample_description: matDescVal || styleVal || lr.lab_ref_no,
        sample_color: colorVal || '',
      }];
      renderLabRefTestsPreview(state.activeItems, state.activeReadings);

      // Map into the 32+ InterLink Form Control inputs inside #sync-fields-tbody
      const appNo = state.activeAppNo || $('#dlg-readonly-appno')?.value || 'A005967935';
      const baseReport = state.reports.find((r) => r.application_no === appNo) || { application_no: appNo };

      const merged = Object.assign({}, baseReport, {
        application_no: appNo,
        report_no: reportNoVal,
        overall_result: overallVal,
        report_issue_date: lr.finished_date || baseReport.report_issue_date || new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
        attachment_name: fileNameFor(appNo, lr),
      });
      if (supplierVal) merged.supplier_name = supplierVal;
      if (styleVal) {
        merged.style_no = styleVal;
        merged.product_id = styleVal;
      }
      if (matDescVal) {
        merged.product_name = matDescVal;
        merged.material_name = matDescVal;
      }
      if (matCodeVal) merged.material_code = matCodeVal;
      if (colorVal) merged.colorway_name = colorVal;
      if (lr.thickness_ave || lr.weight_ave) {
        merged.fabric_weight = [
          lr.thickness_ave ? `Thickness: ${lr.thickness_ave} mm` : '',
          lr.weight_ave ? `Weight/SG: ${lr.weight_ave}` : ''
        ].filter(Boolean).join(' | ');
      }
      merged.lab_remarks = `LabRefNo: ${lr.lab_ref_no} | Category: ${lr.form_category} | Result: ${lr.overall_result || 'Pass'}${lr.mtl_no ? ' | MTL: ' + lr.mtl_no : ''}`;

      renderSyncDialogRows(merged);
    } catch (err) {
      console.warn('[InterLink] Failed to load LabRef detail:', err);
    }
  };

  const filterAndLoadLabRefs = async (autoSelectMatch = false, currentReport = null) => {
    const q = ($('#dlg-labref-search')?.value || '').trim();
    const cat = $('#dlg-labref-category')?.value || 'all';
    try {
      const res = await apiFetch(`interlink/labrefs?category=${encodeURIComponent(cat)}&q=${encodeURIComponent(q)}&limit=400`);
      if (res && res.rows) {
        state.labrefs = res.rows;
      }
    } catch (_) {}

    let selectedId = state.labrefs[0]?.id || null;
    if (autoSelectMatch && currentReport && state.labrefs.length) {
      // Try matching by lab_ref_no, style_no, or material_code
      const targetLabRef = String(currentReport.lab_ref_no || '').toUpperCase().trim();
      const targetStyle = String(currentReport.style_no || '').toUpperCase().trim();
      const targetMat = String(currentReport.material_code || '').toUpperCase().trim();
      const match = state.labrefs.find((lr) =>
        (targetLabRef && String(lr.lab_ref_no || '').toUpperCase().includes(targetLabRef)) ||
        (targetStyle && String(lr.model_style || '').toUpperCase().includes(targetStyle)) ||
        (targetMat && String(lr.material_code || '').toUpperCase().includes(targetMat))
      );
      if (match) selectedId = match.id;
    }

    renderLabRefComboOptions(state.labrefs, selectedId);
    if (selectedId) {
      await applyLabRefToSyncDialog(selectedId);
    } else if (currentReport) {
      renderSyncDialogRows(currentReport);
    }
  };

  const openSyncDialog = async () => {
    const appNo = state.activeAppNo || $('#uf_application_no')?.value || 'A005967935';
    const modal = $('#sync-dialog-backdrop');
    if (!modal) return;

    // 1. Set Readonly Selected Application No.
    const appNoReadonly = $('#dlg-readonly-appno');
    if (appNoReadonly) {
      appNoReadonly.value = appNo;
    }

    let currentReport = state.reports.find((r) => r.application_no === appNo) || { application_no: appNo };
    try {
      const res = await apiFetch(`interlink/report/${encodeURIComponent(appNo)}`);
      if (res.report) currentReport = Object.assign({}, currentReport, res.report);
      state.activeSamples = res.samples || [];
      state.activeItems = res.items || [];
      state.activeReadings = res.readings || [];
    } catch (_) {}

    renderSyncDialogRows(currentReport);

    // 2. Populate LabReference (LabRefNo) ComboBox from dbnexus (#Digital Test Report)
    if (state.labrefs && state.labrefs.length > 0) {
      let selectedId = state.labrefs[0].id;
      const targetLabRef = String(currentReport.lab_ref_no || '').toUpperCase().trim();
      const targetStyle = String(currentReport.style_no || '').toUpperCase().trim();
      const match = state.labrefs.find((lr) =>
        (targetLabRef && String(lr.lab_ref_no || '').toUpperCase().includes(targetLabRef)) ||
        (targetStyle && String(lr.model_style || '').toUpperCase().includes(targetStyle))
      );
      if (match) selectedId = match.id;
      renderLabRefComboOptions(state.labrefs, selectedId);
      await applyLabRefToSyncDialog(selectedId);
    } else {
      await filterAndLoadLabRefs(true, currentReport);
    }

    modal.classList.remove('hidden');
  };

  const closeSyncDialog = () => {
    $('#sync-dialog-backdrop')?.classList.add('hidden');
  };

  // =========================================================================
  // 4. EXECUTE FORM FILL & 1-CLICK LIVE REPORT SEND
  // =========================================================================
  const executeSync = async (sendLive = false) => {
    const confirmBtn = $('#btn-confirm-sync');
    const liveBtn = $('#btn-confirm-and-send-live');
    const appNo = state.activeAppNo || $('#uf_application_no')?.value || 'A005967935';

    const fieldsToSync = {};
    const checks = $$('#sync-fields-tbody .sync-field-check');

    if (checks.length > 0) {
      checks.forEach((chk) => {
        if (!chk.checked) return;
        const key = chk.dataset.syncCheck;
        const inp = $(`[data-sync-input="${key}"]`);
        if (inp) {
          fieldsToSync[key] = inp.value.trim();
        }
      });
    } else {
      const rep = state.reports.find((r) => r.application_no === appNo) || {};
      FORM_FIELD_DEFINITIONS.forEach((d) => {
        fieldsToSync[d.key] = String(rep[d.key] ?? '');
      });
    }

    if (Object.keys(fieldsToSync).length === 0) {
      showToast('Please select at least one field to sync.', true);
      return;
    }

    if (confirmBtn) confirmBtn.disabled = true;
    if (liveBtn) liveBtn.disabled = true;

    try {
      const runBot = Boolean($('#sync-run-playwright')?.checked) || sendLive;
      const targetUrl = $('#il-url-input')?.value || '';

      const res = await apiFetch('interlink/sync', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          application_no: appNo,
          fields: fieldsToSync,
          sync_mode: sendLive ? '1click_live_postback' : 'portal_sync',
          run_bot: runBot,
          send_live: sendLive,
          target_url: targetUrl,
        }),
      });

      // Sending is done by the person responsible, in their own browser: show the helper that fills the InterLink form
      if (sendLive && window.nxFillHelper) window.nxFillHelper(appNo, fieldsToSync);

      // Fill every selected field into UploadForm.aspx (Tab 1 + Tab 2) with visual highlight
      Object.keys(fieldsToSync).forEach((key) => {
        const val = fieldsToSync[key];

        if (key === 'is_retest') {
          $$('input[name="uf_is_retest"]').forEach((r) => {
            r.checked = r.value === val;
          });
        } else {
          const inputEl = $(`[data-uf-field="${key}"]`);
          if (inputEl) {
            inputEl.value = val;
          }
        }

        const wrap = $(`[data-field-wrap="${key}"]`);
        if (wrap) {
          wrap.classList.add('is-synced');
          wrap.querySelector('.uf-sync-tag')?.classList.remove('hidden');
        }
      });

      if (fieldsToSync.report_no && $('#uf_tab2_report_no')) {
        $('#uf_tab2_report_no').value = fieldsToSync.report_no;
      }

      // this Lab Reference may already have a report approved and signed: show it as the attachment (under the user's file
      // name format) instead of a name made up by the sync
      restoreApproval($('#dlg-lr-labref')?.value || '');

      // Render Tab 2 Sample Info, Test Items, and Test Readings from the selected LabRefNo
      renderTab2SubGrids(state.activeSamples, state.activeItems, state.activeReadings);

      if (res.report) {
        const idx = state.reports.findIndex((r) => r.application_no === res.report.application_no);
        if (idx >= 0) {
          state.reports[idx] = res.report;
        } else {
          state.reports.unshift(res.report);
        }
      }
      if (res.logs) {
        state.logs = res.logs;
        renderSyncLogsTable();
      }

      renderSearchGrid();
      closeSyncDialog();

      if (sendLive) {
        // Also switch to Tab 2 (Upload Result) briefly or highlight completion
        showToast(`1-Click Report Sent! Synced ${Object.keys(fieldsToSync).length} fields + Sample & Test Readings for ${appNo} (${fieldsToSync.report_no || ''}) to InterLink 2.0!`);
      } else {
        showToast(`✓ Synced ${Object.keys(fieldsToSync).length} fields from NEXUS-L into UploadForm.aspx (${appNo})!`);
      }
    } catch (err) {
      showToast(err.message || 'Sync failed.', true);
    } finally {
      if (confirmBtn) confirmBtn.disabled = false;
      if (liveBtn) liveBtn.disabled = false;
    }
  };

  // =========================================================================
  // 5. MANAGE COMPANY REPORTS & SYNC LOGS MODALS
  // =========================================================================
  const renderSyncLogsTable = () => {
    const tbody = $('#sync-logs-tbody');
    if (!tbody) return;
    if (!state.logs.length) {
      tbody.innerHTML = `<tr><td colspan="6" class="muted" style="text-align:center;padding:18px">No sync history yet.</td></tr>`;
      return;
    }
    tbody.innerHTML = state.logs.map((lg) => `
      <tr>
        <td>${escHtml(lg.created_at || '')}</td>
        <td><b>${escHtml(lg.application_no || '')}</b></td>
        <td class="mono">${escHtml(lg.report_no || '')}</td>
        <td>${escHtml(lg.user_name || '')}</td>
        <td><span class="badge b-ok">${escHtml(lg.sync_mode || 'portal_sync')}</span></td>
        <td><span class="badge b-ok">${escHtml(lg.status || 'success')}</span></td>
      </tr>
    `).join('');
  };

  // ---- Report Detail Page (read-only copy of what InterLink shows) and the U menu ---------------------------------
  const openUMenu = (btn, appNo) => {
    document.querySelectorAll('.itk-umenu').forEach((m) => m.remove());
    const m = document.createElement('div');
    m.className = 'itk-umenu';
    m.innerHTML = '<button type="button" data-a="add">Add (new report)</button><button type="button" data-a="edit">Edit (current report)</button>';
    const rc = btn.getBoundingClientRect();
    m.style.cssText = `position:fixed;left:${rc.left}px;top:${rc.bottom + 4}px;z-index:9000`;
    document.body.appendChild(m);
    const off = (ev) => { if (!m.contains(ev.target)) { m.remove(); document.removeEventListener('mousedown', off); } };
    setTimeout(() => document.addEventListener('mousedown', off), 0);
    m.addEventListener('click', (ev) => { if (!ev.target.dataset.a) return; m.remove(); navigateToUploadForm(appNo); });
  };
  // paper clip: the attached report is the Lab Reference's report sheet (the DTR print preview), opened in its own tab
  // The paperclip (and the attachment name in the detail page): the PDF of the report made for that application, shown right here.
  // An approved report shows the signed PDF; otherwise the report as generated now from the NEXUS-L record, marked as not approved.
  const viewRowReport = async (appNo) => {
    beginLoading(`Loading the report for ${appNo}…`, 60000);
    try {
      let b64 = '';
      let status = '';
      let labRef = '';
      const st = await apiFetch(`dtr/approval/status?lab_ref=&application_no=${encodeURIComponent(appNo)}`).catch(() => null);
      const a = st && st.approval;
      const key = a && a.file && (a.file.match(/[a-f0-9]{40}/) || [])[0];
      if (key) {
        b64 = await pdfAsB64('dtr/approval/file/' + key);
        labRef = a.lab_ref_no;
        status = a.status === 'approved' ? `Signed by ${a.decided_name} · ${a.decided_at}` : (a.status === 'rejected' ? 'Returned for correction' : 'Waiting for approval - not signed yet');
      } else {
        const j = await fetch(`${BASE_URL}interlink/labrefs?limit=5&q=${encodeURIComponent(appNo)}`, { credentials: 'same-origin' }).then((r) => r.json());
        const lr = (j.rows || []).find((x) => x.tr_no === appNo) || (j.rows || [])[0];
        if (!lr) throw new Error(`No Lab Reference is linked to ${appNo}, so there is no report to show.`);
        labRef = lr.lab_ref_no;
        b64 = lr.form_slug === 'bonding'
          ? await window.nxReportPdf({ slug: lr.form_slug, id: lr.source_id || lr.id })
          : await pdfAsB64(`dtr/report-pdf?lab_ref=${encodeURIComponent(lr.lab_ref_no)}&app=${encodeURIComponent(appNo)}`);
        status = 'Generated now from the NEXUS-L record - not approved yet';
      }
      const url = URL.createObjectURL(b64ToBlob(b64));
      document.getElementById('nx-rowpdf')?.remove();
      const m = document.createElement('div');
      m.id = 'nx-rowpdf';
      m.style.cssText = 'position:fixed;inset:0;z-index:10050;background:rgba(15,23,42,.6);display:flex;justify-content:center;align-items:center;padding:14px';
      m.innerHTML = `<div style="background:#fff;border-radius:10px;width:100%;max-width:1000px;height:94vh;display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,.35)">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 16px;background:#0f172a;color:#fff;border-radius:10px 10px 0 0">
          <div><b>Report ${escHtml(appNo)}</b> <span style="opacity:.8;font-size:12px">· ${escHtml(labRef)} · ${escHtml(status)}</span></div>
          <div style="display:flex;gap:8px;align-items:center"><a class="uf-action-btn" style="text-decoration:none" href="${url}" download="${escHtml(appNo)}.pdf">Download</a>
          <button type="button" data-rp="close" style="background:none;border:0;color:#fff;font-size:22px;cursor:pointer" aria-label="Close">×</button></div></div>
        <iframe title="Report ${escHtml(appNo)}" src="${url}" style="flex:1;border:0;width:100%;background:#e2e8f0"></iframe></div>`;
      m.addEventListener('click', (e) => { if (e.target === m || (e.target.dataset && e.target.dataset.rp === 'close')) { m.remove(); setTimeout(() => URL.revokeObjectURL(url), 5000); } });
      document.body.appendChild(m);
    } catch (e) {
      nxToast(e.message || 'Could not show the report.', 'error', 7000);
    } finally { endLoading(); }
  };
  document.addEventListener('click', (e) => {
    const l = e.target.closest && e.target.closest('[data-view-report]');
    if (l) { e.preventDefault(); viewRowReport(l.dataset.viewReport); }
  });
  const openAttachment = async (appNo) => {
    const w = window.open('', '_blank');
    try {
      const j = await fetch(`${BASE_URL}interlink/labrefs?limit=5&q=${encodeURIComponent(appNo)}`).then((r) => r.json());
      const lr = (j.rows || []).find((x) => x.tr_no === appNo) || (j.rows || [])[0];
      if (!lr) throw new Error('No Lab Reference is linked to this application.');
      const url = `${BASE_URL}dtr?open=${encodeURIComponent(lr.form_slug)}:${lr.id}&preview=1`;
      if (w) w.location.href = url; else window.location.href = url;
    } catch (e) { if (w) w.close(); nxToast(e.message || 'Could not open the report.', 'error', 6000); }
  };
  const openDetail = (appNo, focusAttach) => {
    const r = state.reports.find((x) => x.application_no === appNo) || { application_no: appNo };
    const v = (k, d = '') => escHtml(r[k] || d);
    const idp = (n, i) => v(n) + (r[i] ? ' / ' + v(i) : '');
    const pairs = [
      ['Application No.', v('application_no')], ['Online TRF Submission Date', v('trf_submission_date')], ['Report No.', v('report_no')], ['Report Issue Date', v('report_issue_date')],
      ['Sample Type', v('sample_type')], ['Brand Name', v('buyer_brand', 'Vans')], ['Company Name', v('company_name')], ['Interlink Code', v('interlink_code')],
      ['Vendor Name/ID', idp('vendor_name', 'vendor_id')], ['Factory Name/ID', idp('factory_name', 'factory_id')],
      ['Supplier Name/ID', idp('supplier_name', 'supplier_id')], ['Style No.', v('style_no')], ['SKU No.', v('style_color_no')],
      ['Material Code', v('material_code')], ['Material Description', v('material_description')], ['Product Category', v('product_category')], ['Product Name', v('product_name')],
      ['Season', v('season')], ['Age Group', v('age_group')], ['Sourcing Office', v('sourcing_office')], ['Service Test Type', v('service_test_type', 'In-House Lab Testing')],
      ['Test Stage', v('test_stage', 'Production')], ['Product Export To', v('product_export_to')], ['Service Provider', v('service_provider', 'DATIAN SUBIC SHOES INC.')],
      ['Lab Location', v('lab_location', 'PHILIPPINES')], ['Related TRF Number', v('related_trf_no')], ['Remarks', v('remarks')], ['Overall Result', v('overall_result')],
    ];
    const att = r.report_no ? `${escHtml(r.report_no)}.pdf` : '';
    const back = document.createElement('div');
    back.className = 'nx-modal-back';
    back.innerHTML = `<div class="nx-modal itk-detail" role="dialog" aria-modal="true" aria-label="Report Detail Page">
      <h3 class="itk-detail-h">VF Corporation - Report Detail Page</h3>
      <div class="itk-detail-grid">${pairs.map(([l, val]) => `<div class="itk-dl">${l}</div><div class="itk-dv">${val || '&nbsp;'}</div>`).join('')}</div>
      <div class="itk-detail-sec ${focusAttach ? 'itk-focus' : ''}"><b>Report Attachment</b><div>${att ? `<a href="#" data-view-report="${escHtml(r.application_no)}" style="color:#1d4ed8;font-weight:600">${att}</a>` : '<span class="muted">No report attached</span>'}</div></div>
      <div class="itk-detail-sec"><b>Supplementary Attachment</b><table class="itk-grid"><thead><tr><th>File Name</th><th>Date Added</th></tr></thead><tbody><tr><td colspan="2" class="muted">Empty</td></tr></tbody></table></div>
      <div class="itk-detail-sec"><b>Report Review</b><div>Review Result: ${v('review_result')} &nbsp; Review Status Code: ${v('review_status')}</div>
        <table class="itk-grid"><thead><tr><th>Date/Time</th><th>Comment</th><th>By Whom</th><th>Company</th></tr></thead><tbody><tr><td colspan="4">${v('latest_review_comment') || '<span class="muted">No comments</span>'}</td></tr></tbody></table></div>
      <div class="nx-modal-actions"><button type="button" class="nx-btn" data-a="close">Close</button></div></div>`;
    document.body.appendChild(back);
    back.addEventListener('click', (e) => { if (e.target === back || e.target.dataset.a === 'close') back.remove(); });
  };

  // =========================================================================
  // 6. EVENT LISTENERS
  // =========================================================================
  // Delegate clicks on "U", "E", or Application No. in SearchFormWithHeader.aspx
  $('#itk-grid-tbody')?.addEventListener('click', (e) => {
    const dt = e.target.closest('[data-open-detail],[data-open-clip]');
    if (dt && dt.dataset.openClip) { viewRowReport(dt.dataset.openClip); return; }
    if (dt) { openDetail(dt.dataset.openDetail, false); return; }
    const um = e.target.closest('[data-open-umenu]');
    if (um) { openUMenu(um, um.dataset.openUmenu); return; }
    const uploadTrigger = e.target.closest('[data-open-upload]');
    if (uploadTrigger) {
      const appNo = uploadTrigger.dataset.openUpload;
      if (appNo) navigateToUploadForm(appNo);
      return;
    }
    const normalTrigger = e.target.closest('[data-open-normal]');
    if (normalTrigger) {
      const appNo = normalTrigger.dataset.openNormal;
      if (appNo) navigateToNormalForm(appNo);
    }
  });

  // Toggle top Search Criteria panel
  $('#itk-btn-toggle-criteria')?.addEventListener('click', () => {
    $('#itk-search-criteria-panel')?.classList.toggle('hidden');
  });

  // Live filter inputs in SearchFormWithHeader.aspx (both grid filters and top criteria panel)
  let searchTimer = null;
  let searchSeq = 0;
  const runSearch = async () => {
    const f = getActiveFilters();
    if (!Object.keys(f).length) {
      if (state.searchMode) {                      // filters cleared: back to the live page the person was on
        state.searchMode = false;
        await fetchLiveInterlinkPage(state.liveReturnPage || 1, state.pageSize, false, false);
      } else renderSearchGrid();
      return;
    }
    if (!state.searchMode) state.liveReturnPage = state.currentPage;
    state.searchMode = true;
    const seq = ++searchSeq;
    const tbody = $('#itk-grid-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="38" style="text-align:center;padding:20px;color:#6b7280"><span class="nx-spin" aria-hidden="true"></span> Searching all InterLink applications…</td></tr>';
    try {
      const res = await apiFetch('interlink/search?filters=' + encodeURIComponent(JSON.stringify(f)));
      if (seq !== searchSeq) return;               // a newer search started
      state.reports = res.rows || [];
      state.searchInfo = res;
      renderSearchGrid();
    } catch (err) {
      if (seq === searchSeq) showToast(err.message || 'Search failed.', true);
    }
  };
  const searchSoon = () => { clearTimeout(searchTimer); searchTimer = setTimeout(runSearch, 350); };
  $('#itk-general-search')?.addEventListener('input', searchSoon);
  $('#itk-general-search')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { clearTimeout(searchTimer); runSearch(); } });
  $$('#itk-search-table .itk-filter-row input[data-col], #itk-search-criteria-panel [data-top-filter]').forEach((inp) => {
    inp.addEventListener('input', searchSoon);
    inp.addEventListener('change', searchSoon);
  });

  $('#itk-btn-clear-filter')?.addEventListener('click', () => {
    $$('#itk-search-table .itk-filter-row input[data-col], #itk-search-criteria-panel [data-top-filter]').forEach((inp) => {
      if (!inp.readOnly) inp.value = '';
    });
    if ($('#itk-general-search')) $('#itk-general-search').value = '';
    runSearch();
  });

  $('#itk-btn-refresh')?.addEventListener('click', () => {
    fetchLiveInterlinkPage(state.currentPage, state.pageSize, true, false);
  });

  // Live 517-page Pager clicks
  $('#itk-pager-nums')?.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-goto-page]');
    if (!trigger || trigger.disabled) return;
    const pg = Number(trigger.dataset.gotoPage);
    if (state.searchMode) return;
    if (pg && pg >= 1 && pg <= state.totalPages && pg !== state.currentPage) {
      fetchLiveInterlinkPage(pg, state.pageSize, false, false);
    }
  });

  // Jump-to-page (1..517)
  const triggerJumpPage = () => {
    const inp = $('#itk-jump-input');
    if (!inp) return;
    const pg = Math.max(1, Math.min(state.totalPages, Number(inp.value) || 1));
    inp.value = pg;
    fetchLiveInterlinkPage(pg, state.pageSize, false, false);
  };
  $('#itk-jump-btn')?.addEventListener('click', triggerJumpPage);
  $('#itk-jump-input')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      triggerJumpPage();
    }
  });

  // Page size selector (10 / 15 / 20 / 50 / 100)
  $('#itk-pagesize-select')?.addEventListener('change', (e) => {
    const newSize = Number(e.target.value) || 15;
    state.pageSize = newSize;
    fetchLiveInterlinkPage(1, newSize, true, false);
  });

  // Manual Live Refresh button
  $('#itk-btn-live-refresh')?.addEventListener('click', () => {
    fetchLiveInterlinkPage(state.currentPage, state.pageSize, true, false);
  });

  // 15-Second Real-Time Auto-Sync Polling
  const startOrStopAutoSync = () => {
    if (state.autoSyncTimer) {
      clearInterval(state.autoSyncTimer);
      state.autoSyncTimer = null;
    }
    const toggle = $('#itk-autosync-toggle');
    if (toggle && toggle.checked) {
      state.autoSyncTimer = setInterval(() => {
        if (state.currentView === 'search' && !document.hidden) {
          fetchLiveInterlinkPage(state.currentPage, state.pageSize, false, true);
        }
      }, 15000);
    }
  };
  $('#itk-autosync-toggle')?.addEventListener('change', startOrStopAutoSync);

  // Browser navigation bar buttons
  $('#il-btn-home')?.addEventListener('click', () => navigateToSearchList(true));
  $('#btn-back-to-search')?.addEventListener('click', () => navigateToSearchList(true));
  $('#btn-nf-back-search')?.addEventListener('click', () => navigateToSearchList(true));
  $('#btn-nf-open-upload')?.addEventListener('click', () => {
    if (state.activeAppNo) navigateToUploadForm(state.activeAppNo, true);
  });
  $('#btn-view-eform')?.addEventListener('click', () => {
    if (state.activeAppNo) navigateToNormalForm(state.activeAppNo, true);
  });

  $('#il-btn-back')?.addEventListener('click', () => {
    if (state.historyIndex <= 0) return;
    state.historyIndex--;
    const entry = state.historyStack[state.historyIndex];
    if (entry === 'search') navigateToSearchList(false);
    else if (entry.startsWith('upload:')) navigateToUploadForm(entry.split(':')[1], false);
    else if (entry.startsWith('normal:')) navigateToNormalForm(entry.split(':')[1], false);
  });

  $('#il-btn-forward')?.addEventListener('click', () => {
    if (state.historyIndex >= state.historyStack.length - 1) return;
    state.historyIndex++;
    const entry = state.historyStack[state.historyIndex];
    if (entry === 'search') navigateToSearchList(false);
    else if (entry.startsWith('upload:')) navigateToUploadForm(entry.split(':')[1], false);
    else if (entry.startsWith('normal:')) navigateToNormalForm(entry.split(':')[1], false);
  });

  $('#il-btn-reload')?.addEventListener('click', () => {
    if (state.currentView === 'upload' && state.activeAppNo) navigateToUploadForm(state.activeAppNo, false);
    else if (state.currentView === 'normal' && state.activeAppNo) navigateToNormalForm(state.activeAppNo, false);
    else navigateToSearchList(false);
  });

  // UploadForm.aspx Tab Switching (Product/Material Form <-> Upload Result)
  $$('[data-uf-tab]').forEach((tabBtn) => {
    tabBtn.addEventListener('click', () => {
      $$('[data-uf-tab]').forEach((b) => b.classList.remove('active'));
      tabBtn.classList.add('active');
      const target = tabBtn.dataset.ufTab;
      $('#uf-tab-product')?.classList.toggle('hidden', target !== 'product');
      $('#uf-tab-result')?.classList.toggle('hidden', target !== 'result');
      if (target === 'result') {
        $('#uf-btn-prev')?.removeAttribute('disabled');
        if ($('#uf_tab2_report_no') && $('#uf_report_no')) {
          $('#uf_tab2_report_no').value = $('#uf_report_no').value;
        }
      } else {
        $('#uf-btn-prev')?.setAttribute('disabled', 'disabled');
      }
    });
  });

  $('#uf-btn-next')?.addEventListener('click', () => {
    $('[data-uf-tab="result"]')?.click();
  });

  $('#uf-btn-prev')?.addEventListener('click', () => {
    $('[data-uf-tab="product"]')?.click();
  });

  $('#uf-btn-save')?.addEventListener('click', () => {
    const repNo = $('#uf_report_no')?.value.trim();
    if (!repNo) {
      showToast('Please click "Sync NEXUS-L Data" first to fill Report No. and required fields.', true);
      return;
    }
    showToast(`✓ InterLink UploadForm.aspx saved for Application ${state.activeAppNo || ''} (Report: ${repNo}).`);
  });

  $('#uf-btn-new')?.addEventListener('click', () => {
    if (state.activeAppNo) {
      const report = state.reports.find((r) => r.application_no === state.activeAppNo) || { application_no: state.activeAppNo };
      populateInitialUploadForm(Object.assign({}, report, { synced_to_interlink: 0 }));
      showToast('UploadForm.aspx reset to unsynced state.');
    }
  });

  // Accordion toggles inside UploadForm.aspx
  $$('[data-acc-target]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.accTarget;
      if (id) $(`#${id}`)?.classList.toggle('hidden');
    });
  });

  // Add Email Recipient '+' button
  $('#uf-btn-add-email')?.addEventListener('click', () => {
    const inp = $('#uf_email_recipients');
    if (!inp) return;
    const extra = prompt('Enter additional email notification recipient:', 'qa_lab@dtshoes.com');
    if (extra && extra.trim()) {
      inp.value = inp.value ? `${inp.value}; ${extra.trim()}` : extra.trim();
    }
  });

  // ---- Report Attachment (PDF): generated from the Lab Reference through the Digital Test Report sheet, or browsed ----
  // The chosen PDF is kept in window.nxAttachedPdf so the 1-click sender attaches this exact file to InterLink.
  const b64ToBlob = (b64) => {
    const bin = atob(b64);
    const buf = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
    return new Blob([buf], { type: 'application/pdf' });
  };
  const setAttachedPdf = (pdf) => {
    window.nxAttachedPdf = pdf;
    const inp = $('#uf_attachment_name');
    if (inp) inp.value = pdf.name;
    const wrap = inp?.closest('[data-field-wrap]');
    wrap?.querySelector('.uf-sync-tag')?.classList.remove('hidden');
    wrap?.classList.add('uf-field-synced');
    const kb = Math.max(1, Math.round((pdf.b64.length * 3) / 4 / 1024));
    if ($('#uf-att-info')) $('#uf-att-info').textContent = `${pdf.approved ? 'Signed by ' + pdf.approved_by : (pdf.source === 'generated' ? 'Generated from Lab Ref ' + pdf.lab_ref_no : 'Selected file')} · ${kb} KB`;
    $('#uf-att-meta')?.classList.remove('hidden');
    if (!pdf.approved) checkApproval(pdf.lab_ref_no);
  };
  // The attachment is tied to its Lab Reference's saved report, never to its file name: if the copy held by the page is missing or
  // empty (a sync, a reload, another tab), the saved report is fetched again from NEXUS-L before it is viewed or downloaded.
  const ensureAttachedPdf = async () => {
    let pdf = window.nxAttachedPdf;
    if (!pdf || !pdf.b64 || pdf.b64.length < 2000) {
      await restoreApproval(pdf && pdf.lab_ref_no ? pdf.lab_ref_no : currentLabRef());
      pdf = window.nxAttachedPdf;
      if ((!pdf || !pdf.b64 || pdf.b64.length < 2000) && pdf && pdf.lab_ref_no) {
        const j = await apiFetch(`dtr/approval/status?lab_ref=${encodeURIComponent(pdf.lab_ref_no)}`);
        const key = j.approval && j.approval.file && (j.approval.file.match(/[a-f0-9]{40}/) || [])[0];
        if (key) { pdf.b64 = await pdfAsB64('dtr/approval/file/' + key); pdf.sheet = null; }
      }
    }
    if (!pdf || !pdf.b64 || pdf.b64.length < 2000) throw new Error('No saved report was found for this Lab Reference. Press Generate Report.');
    return pdf;
  };
  const openAttachedPdf = async (download) => {
    let pdf;
    try { pdf = await ensureAttachedPdf(); } catch (e) { showToast(e.message, true); return; }
    const url = URL.createObjectURL(b64ToBlob(pdf.b64));
    if (download) {
      const a = document.createElement('a');
      a.href = url; a.download = pdf.name;
      document.body.appendChild(a); a.click(); a.remove();
    } else {
      window.open(url, '_blank', 'noopener');
    }
    setTimeout(() => URL.revokeObjectURL(url), 60000);
  };

  // Lab Reference of the open application: the one picked in the sync dialog, the one saved on the report, or the
  // Lab Reference whose TR No. is this application
  const currentLabRef = () => {
    const appNo = state.activeAppNo || $('#uf_application_no')?.value || '';
    const rep = state.reports.find((r) => r.application_no === appNo) || {};
    const byTr = state.labrefs.find((l) => appNo && String(l.tr_no || '').trim() === appNo);
    const fromName = (($('#uf_attachment_name')?.value || '').match(/^(.+?)_TestReport\.pdf$/i) || [])[1];
    return String($('#dlg-lr-labref')?.value || rep.lab_ref_no || byTr?.lab_ref_no || fromName || '').trim();
  };

  // Report view (like Print Preview): the finished report sheet is read on screen before any file is downloaded
  const showReportView = (html, title) => {
    if (!document.getElementById('nx-sheet-css')) {
      const l = document.createElement('link');
      l.id = 'nx-sheet-css'; l.rel = 'stylesheet'; l.href = BASE_URL + 'assets/css/dtr.css';
      document.head.appendChild(l);
    }
    document.getElementById('nx-rv')?.remove();
    const m = document.createElement('div');
    m.id = 'nx-rv';
    m.style.cssText = 'position:fixed;inset:0;z-index:10050;background:rgba(15,23,42,.6);overflow:auto;padding:20px 12px;display:flex;justify-content:center;align-items:flex-start';
    m.innerHTML = `<div style="background:#fff;border-radius:10px;width:100%;max-width:1000px;box-shadow:0 20px 50px rgba(0,0,0,.35)">
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:#0f172a;color:#fff;border-radius:10px 10px 0 0;position:sticky;top:0;z-index:1">
        <b>Report view — ${escHtml(title || '')}</b><button type="button" data-rv="close" style="background:none;border:0;color:#fff;font-size:20px;cursor:pointer" aria-label="Close">×</button></div>
      <div style="padding:16px;overflow-x:auto">${html}</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;padding:12px 16px;border-top:1px solid #e2e8f0;position:sticky;bottom:0;background:#fff;border-radius:0 0 10px 10px">
        <button type="button" class="uf-action-btn" data-rv="close">Close</button>
        <button type="button" class="uf-action-btn" data-rv="download">Download PDF</button>
        <button type="button" class="uf-action-btn uf-btn-gen" data-rv="request">Request approval (QR)</button></div></div>`;
    m.addEventListener('click', (e) => {
      const a = e.target.dataset && e.target.dataset.rv;
      if (e.target === m || a === 'close') m.remove();
      if (a === 'download') openAttachedPdf(true);
      if (a === 'request') { m.remove(); const b = $('#uf-apv-request'); b?.classList.remove('hidden'); b?.click(); }
    });
    document.body.appendChild(m);
  };
  // the sheet of a record: the one kept with the attached report, else read it from the record
  const sheetFor = async (pdf) => {
    if (pdf.sheet) return pdf.sheet;
    // the report as printed (page images of this very PDF): always what is signed and sent, and nothing depends on the DTR page
    try {
      const j = await apiFetch('dtr/approval/pdf-pages', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ pdf_b64: pdf.b64 }) });
      if (j && j.html) { pdf.sheet = j.html; return pdf.sheet; }
    } catch (e) { /* fall back to the DTR sheet below */ }
    const det = await apiFetch(`interlink/labref-detail?lab_ref_no=${encodeURIComponent(pdf.lab_ref_no)}`);
    const lr = det.labref || {};
    if (!lr.form_slug) throw new Error('This report has no sheet to show.');
    pdf.sheet = await window.nxReportHtml({ slug: lr.form_slug, id: lr.source_id || lr.id });
    return pdf.sheet;
  };

  const pdfAsB64 = async (path) => {
    const r = await fetch(BASE_URL + path, { credentials: 'same-origin' });
    if (!r.ok) throw new Error((await r.text()).slice(0, 200) || 'The report PDF could not be made.');
    const buf = new Uint8Array(await r.arrayBuffer());
    let bin = '';
    for (let i = 0; i < buf.length; i += 0x8000) bin += String.fromCharCode.apply(null, buf.subarray(i, i + 0x8000));
    return btoa(bin);
  };

  // ---- Sign-off: the report must be approved (signed) by a registered Manager / Supervisor before InterLink ----
  // ---- File name of the report sent to InterLink: each user has their own format, and can still rename one report ----
  state.pdfNameFmt = '{app}';
  const cleanPdfName = (n) => {
    const base = String(n || '').replace(/[\\/:*?"<>|\u0000-\u001f]+/g, '_').replace(/\s+/g, ' ').trim().replace(/\.pdf$/i, '').slice(0, 120);
    return (base || 'report') + '.pdf';
  };
  const fileNameFor = (app, lr) => {
    const d = new Date();
    const ymd = `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}`;
    const v = {
      app: app || '', tr: (lr && lr.tr_no) || app || '', labref: (lr && lr.lab_ref_no) || '', style: (lr && lr.model_style) || '',
      material: (lr && lr.material_description) || '', result: (lr && lr.overall_result) || '', date: ymd,
    };
    return cleanPdfName(String(state.pdfNameFmt || '{app}').replace(/\{(\w+)\}/g, (m, k) => (k in v ? v[k] : '')) || app);
  };
  const labrefRow = (labRef) => state.labrefs.find((l) => l.lab_ref_no === labRef) || { lab_ref_no: labRef };
  window.nxPdfName = (app, labRef) => fileNameFor(app, labrefRow(labRef));
  const showNameFmt = () => {
    if ($('#uf-namefmt-cur')) $('#uf-namefmt-cur').textContent = state.pdfNameFmt;
    const app = state.activeAppNo || $('#uf_application_no')?.value || 'A005953693';
    const lab = (window.nxAttachedPdf && window.nxAttachedPdf.lab_ref_no) || currentLabRef();
    if ($('#uf-namefmt-ex')) $('#uf-namefmt-ex').textContent = `→ ${fileNameFor(app, labrefRow(lab))}`;
  };
  apiFetch('dtr/approval/name-format').then((j) => { if (j && j.format) { state.pdfNameFmt = j.format; showNameFmt(); } }).catch(() => {});
  $('#uf-namefmt-edit')?.addEventListener('click', async () => {
    const f = prompt('Your file name format (saved for your account).\nTokens: {app} Application No.  {tr}  {labref} Lab Ref  {style}  {material}  {result}  {date} YYYYMMDD\nExample: {app}_{labref}\nLeave empty to go back to {app}.', state.pdfNameFmt);
    if (f === null) return;
    try {
      const j = await apiFetch('dtr/approval/name-format', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ format: f.trim() }) });
      state.pdfNameFmt = j.format;
      showNameFmt();
      // the report already attached takes the new name; a name typed by hand for it is replaced
      const cur = window.nxAttachedPdf;
      if (cur && cur.source === 'generated') { cur.name = fileNameFor(cur.app, labrefRow(cur.lab_ref_no)); if ($('#uf_attachment_name')) $('#uf_attachment_name').value = cur.name; }
      showToast('File name format saved.');
    } catch (e) { showToast(e.message, true); }
  });
  // renaming this one report by hand
  $('#uf_attachment_name')?.addEventListener('change', (e) => {
    const v = cleanPdfName(e.target.value);
    e.target.value = v;
    if (window.nxAttachedPdf) { window.nxAttachedPdf.name = v; showToast(`File name for InterLink: ${v}`); }
  });

  let apvTimer = null;
  let restoreApproval = () => {};   // set below, called when the Upload Form opens
  const showApproval = (a, signers) => {
    const box = $('#uf-apv');
    if (!box) return;
    box.classList.remove('hidden', 'is-approved', 'is-rejected');
    $('#uf-apv-qr-wrap')?.classList.add('hidden');
    const req = $('#uf-apv-request');
    if (req) { req.classList.remove('hidden'); req.disabled = false; }
    clearInterval(apvTimer);
    if (!a) {
      $('#uf-apv-status').textContent = signers ? 'Needs approval by a Manager / Supervisor before it can be sent to InterLink.'
        : 'Needs approval, but no Manager / Supervisor is registered yet (Designer → Signatories).';
      return;
    }
    if (a.status === 'approved' && window.nxAttachedPdf && window.nxAttachedPdf.regenerate) {
      // a new version of an already approved report: the approved copy stays until the new one is approved
      $('#uf-apv-status').textContent = `A new version was generated. The report approved by ${a.decided_name} on ${a.decided_at} stays valid until this one is approved: request approval to replace it.`;
      if (req) req.textContent = 'Request approval for the new version (QR)';
      return;
    }
    if (a.status === 'approved') {
      box.classList.add('is-approved');
      req?.classList.add('hidden');
      $('#uf-apv-status').textContent = `Approved by ${a.decided_name} (${a.decided_title}) · ${a.decided_at}. The signed report is attached.`;
      return;
    }
    if (a.status === 'rejected') {
      box.classList.add('is-rejected');
      $('#uf-apv-status').textContent = `Returned by ${a.decided_name}: ${a.remarks || ''} — correct the report, Generate it again and request approval.`;
      if (req) req.textContent = 'Request approval again (QR)';
      return;
    }
    // pending, but the temporary link ran out: a new one has to be made (the old QR code no longer works)
    if (a.expired) {
      $('#uf-apv-status').textContent = 'The approval link expired before anyone used it. Make a new link (it is sent to the Managers / Supervisors again).';
      if (req) req.textContent = 'Get a new link (QR)';
      return;
    }
    // pending: QR code of the temporary single-use link + live wait for the decision
    req?.classList.add('hidden');
    const until = Date.now() + (a.expires_in || 0) * 1000;
    const left = () => { const m = Math.max(0, Math.ceil((until - Date.now()) / 60000)); return m > 1 ? `${m} min` : 'under a minute'; };
    $('#uf-apv-status').textContent = `Waiting for approval · requested by ${a.requested_name} ${a.requested_at} · link works once, expires in ${left()}`;
    const qr = window.qrcode ? window.qrcode(0, 'M') : null;
    if (qr) { qr.addData(a.url); qr.make(); $('#uf-apv-qr').innerHTML = qr.createSvgTag({ cellSize: 3, margin: 2 }); }
    $('#uf-apv-link').href = a.url;
    $('#uf-apv-qr-wrap')?.classList.remove('hidden');
    apvTimer = setInterval(() => {
      if (Date.now() >= until) { checkApproval(a.lab_ref_no, false); return; }   // expired: show the "new link" state
      $('#uf-apv-status').textContent = `Waiting for approval · requested by ${a.requested_name} ${a.requested_at} · link works once, expires in ${left()}`;
      checkApproval(a.lab_ref_no, true);
    }, 3000);
  };
  // the signed PDF of an approved report becomes the attachment
  const attachSigned = async (a) => {
    const b64 = await pdfAsB64('dtr/approval/file/' + ((String(a.file || '').match(/[a-f0-9]{40}/) || [])[0] || a.token));   // the file key; the live link token is never sent for a decided report
    const app = a.application_no || state.activeAppNo || '';
    setAttachedPdf({ app, lab_ref_no: a.lab_ref_no, name: fileNameFor(app, labrefRow(a.lab_ref_no)), b64, source: 'generated', approved: true, approved_by: a.decided_name });
  };
  const checkApproval = async (labRef, quiet) => {
    if (!labRef) return;
    try {
      const j = await apiFetch(`dtr/approval/status?lab_ref=${encodeURIComponent(labRef)}`);
      const a = j.approval;
      if (a && a.status === 'approved' && !(window.nxAttachedPdf && (window.nxAttachedPdf.approved || window.nxAttachedPdf.regenerate))) {
        await attachSigned(a);
        if (quiet) showToast(`${a.lab_ref_no} was approved by ${a.decided_name}. The signed report is attached; you can send it to InterLink.`);
      }
      if (a && a.status === 'rejected' && quiet) showToast(`${a.lab_ref_no} was returned by ${a.decided_name}: ${a.remarks || ''}`, true);
      if (!quiet || !a || a.status !== 'pending') showApproval(a, j.signatories);
    } catch (e) { /* the status shows on the next check */ }
  };
  window.addEventListener('nxl:approval', (e) => {
    const cur = window.nxAttachedPdf;
    if (cur && e.detail && e.detail.lab_ref === cur.lab_ref_no) checkApproval(cur.lab_ref_no, true);
  });
  // Opening the form (or reloading the page) brings the approval panel back as it stands: waiting with its QR code,
  // approved with the signed report attached, or returned. Nothing has to be generated again to see it.
  restoreApproval = async (chosen) => {
    let labRef = chosen || (window.nxAttachedPdf && window.nxAttachedPdf.lab_ref_no) || currentLabRef();
    const appNo = state.activeAppNo || $('#uf_application_no')?.value || '';
    if (!labRef && !appNo) return;
    try {
      // opened on an application whose Lab Reference is not known yet: the report already made for it is found by Application No.
      const j = await apiFetch(`dtr/approval/status?lab_ref=${encodeURIComponent(labRef)}${labRef ? '' : '&application_no=' + encodeURIComponent(appNo)}`);
      const a = j.approval;
      if (!labRef && a) labRef = a.lab_ref_no;
      const cur = window.nxAttachedPdf;
      // another Lab Reference was chosen: the report attached for the previous one is not this one
      if (cur && cur.lab_ref_no && cur.lab_ref_no !== labRef && cur.source === 'generated') { window.nxAttachedPdf = null; $('#uf-att-meta')?.classList.add('hidden'); }
      if (!a) { if (chosen) $('#uf-apv')?.classList.add('hidden'); showNameFmt(); return; }
      if (a.status === 'approved' && !(window.nxAttachedPdf && (window.nxAttachedPdf.approved || window.nxAttachedPdf.regenerate))) await attachSigned(a);
      else if (a.status !== 'approved' && !window.nxAttachedPdf && a.file) {
        // after a reload: the report already made for this Lab Ref (waiting for approval, returned or expired) can still be viewed and downloaded
        try {
          const b64 = await pdfAsB64('dtr/approval/file/' + (a.file.match(/[a-f0-9]{40}/) || [''])[0]);
          const app = a.application_no || state.activeAppNo || '';
          setAttachedPdf({ app, lab_ref_no: a.lab_ref_no, name: fileNameFor(app, labrefRow(a.lab_ref_no)), b64, source: 'generated' });
        } catch (e) { /* no file to show */ }
      }
      showApproval(a, j.signatories);
      showNameFmt();
    } catch (e) { /* the panel stays hidden until the report is generated */ }
  };
  // coming back to the tab (browsers slow background timers) checks at once
  const refreshApproval = () => {
    if (document.hidden || state.currentView !== 'upload') return;
    const cur = window.nxAttachedPdf;
    if (cur && cur.lab_ref_no && !cur.approved) checkApproval(cur.lab_ref_no, true);
  };
  document.addEventListener('visibilitychange', refreshApproval);
  window.addEventListener('focus', refreshApproval);
  $('#uf-apv-request')?.addEventListener('click', async () => {
    const pdf = window.nxAttachedPdf;
    if (!pdf || !pdf.lab_ref_no) { showToast('Generate the report (or Browse for it) first.', true); return; }
    const btn = $('#uf-apv-request');
    btn.disabled = true;
    try {
      // the sheet the approver reads comes from the same record as the PDF, captured now so it is current
      showToast('Preparing the report for the approver…');
      let sheet = '';
      try { sheet = await sheetFor(pdf); } catch (e) { throw new Error('the report view could not be prepared (' + e.message + '). Generate the report again.'); }
      const j = await apiFetch('dtr/approval/request', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lab_ref: pdf.lab_ref_no, application_no: pdf.app, pdf_b64: pdf.b64, sheet_html: sheet, replace: !!pdf.regenerate }),
      });
      pdf.regenerate = false;                       // the new version is now the report on record
      showApproval(j.approval, 1);
      showToast('Approval requested. The Managers / Supervisors were notified; they can also scan the QR code.');
    } catch (e) {
      showToast('Could not request approval: ' + e.message, true);
      btn.disabled = false;
    }
  });
  // a different temporary link every time: the previous QR code / link stops working
  $('#uf-apv-new')?.addEventListener('click', () => { const b = $('#uf-apv-request'); b?.classList.remove('hidden'); b?.click(); });
  $('#uf-apv-copy')?.addEventListener('click', () => {
    const url = $('#uf-apv-link')?.href || '';
    (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(() => showToast('Approval link copied.')).catch(() => prompt('Approval link:', url));
  });

  $('#uf-btn-generate-pdf')?.addEventListener('click', async () => {
    const btn = $('#uf-btn-generate-pdf');
    let labRef = currentLabRef();
    if (!labRef) {
      labRef = (prompt('No Lab Reference is linked to this application yet. Enter the Lab Ref. No. to build the report from:', '') || '').trim();
      if (!labRef) return;
    }
    // a report already exists for this Lab Reference: show it and let the user choose to make a new version
    let regenerate = false;
    try {
      const st = await apiFetch(`dtr/approval/status?lab_ref=${encodeURIComponent(labRef)}`);
      const ex = st.approval;
      if (ex && ex.status === 'approved') {
        if (!confirm(`A report for ${labRef} is already approved (by ${ex.decided_name}, ${ex.decided_at}).\n\nGenerate it again? Use this when the report was changed or the sign-off names were wrong.\nThe new version needs approval again; the approved one stays valid until then.`)) return;
        regenerate = true;
      } else if (ex && ex.status === 'pending' && !ex.expired) {
        if (!confirm(`A report for ${labRef} is waiting for approval.\n\nGenerate it again? The waiting link is replaced by a new one for the new report.`)) return;
      }
    } catch (e) { /* no status: generate as usual */ }
    const label = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Generating...';
    try {
      const det = await apiFetch(`interlink/labref-detail?lab_ref_no=${encodeURIComponent(labRef)}`);
      const lr = det.labref || {};
      if (!lr.form_slug || !(lr.source_id || lr.id)) throw new Error(`${labRef} has no Digital Test Report form to print.`);
      const app = state.activeAppNo || $('#uf_application_no')?.value || lr.tr_no || lr.lab_ref_no;
      showToast(`Creating the ${lr.form_category || ''} report PDF for ${lr.lab_ref_no}…`);
      // material reports: the lab's official Access report; bonding: the VF sheet made in the browser
      const b64 = lr.form_slug === 'bonding'
        ? await window.nxReportPdf({ slug: lr.form_slug, id: lr.source_id || lr.id })
        : await pdfAsB64(`dtr/report-pdf?lab_ref=${encodeURIComponent(lr.lab_ref_no)}&app=${encodeURIComponent(app)}`);
      setAttachedPdf({ app, lab_ref_no: lr.lab_ref_no, name: fileNameFor(app, lr), b64, source: 'generated', regenerate });
      showToast(`Report ready: ${app}.pdf. Check the report view, then request approval.`);
      try { showReportView(await sheetFor(window.nxAttachedPdf), lr.lab_ref_no); } catch (e) { showToast('The report view could not be prepared: ' + e.message, true); }
    } catch (e) {
      showToast('Could not generate the report: ' + e.message, true);
    }
    btn.disabled = false;
    btn.textContent = label;
  });

  $('#uf-btn-browse-pdf')?.addEventListener('click', () => $('#uf-file-pdf')?.click());
  $('#uf-file-pdf')?.addEventListener('change', (e) => {
    const file = e.target.files && e.target.files[0];
    e.target.value = '';
    if (!file) return;
    if (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') {
      showToast('Please choose a PDF file.', true);
      return;
    }
    const rd = new FileReader();
    rd.onload = () => {
      setAttachedPdf({
        app: state.activeAppNo || $('#uf_application_no')?.value || '',
        lab_ref_no: currentLabRef(),
        name: file.name,
        b64: String(rd.result).split(',')[1] || '',
        source: 'file',
      });
      showToast(`Selected PDF attachment: ${file.name}`);
    };
    rd.readAsDataURL(file);
  });
  $('#uf-btn-view-pdf')?.addEventListener('click', async () => {
    const link = $('#uf-btn-view-pdf');
    if (link && link.dataset.busy) return;                                   // a second click while it is loading does nothing
    if (link) { link.dataset.busy = '1'; link.dataset.label = link.textContent; link.textContent = 'Loading…'; link.style.opacity = '.6'; }
    beginLoading('Loading the report…', 60000);                              // so it is clear something is happening
    try {
      let pdf;
      try { pdf = await ensureAttachedPdf(); } catch (e) { showToast(e.message, true); return; }
      try { showReportView(await sheetFor(pdf), pdf.lab_ref_no); } catch (e) { openAttachedPdf(false); }   // a browsed file has no sheet: show the file
    } finally {
      endLoading();
      if (link) { link.textContent = link.dataset.label || 'View'; link.style.opacity = ''; delete link.dataset.busy; }
    }
  });
  $('#uf-btn-save-pdf')?.addEventListener('click', async () => {
    const link = $('#uf-btn-save-pdf');
    if (link && link.dataset.busy) return;
    if (link) { link.dataset.busy = '1'; link.dataset.label = link.textContent; link.textContent = 'Preparing…'; link.style.opacity = '.6'; }
    beginLoading('Preparing the download…', 60000);
    try { await openAttachedPdf(true); } finally {
      endLoading();
      if (link) { link.textContent = link.dataset.label || 'Download'; link.style.opacity = ''; delete link.dataset.busy; }
    }
  });

  // Open Pre-Sync Confirmation Dialog
  $('#btn-open-sync-dialog')?.addEventListener('click', openSyncDialog);
  $('#btn-banner-sync')?.addEventListener('click', openSyncDialog);
  $('#btn-oneclick-send-top')?.addEventListener('click', openSyncDialog);
  $('#btn-banner-oneclick')?.addEventListener('click', openSyncDialog);
  $('#uf-btn-submit-live')?.addEventListener('click', openSyncDialog);
  $('#btn-close-sync-dialog')?.addEventListener('click', closeSyncDialog);
  $('#btn-cancel-sync')?.addEventListener('click', closeSyncDialog);

  // Switch source NEXUS-L report or LabRefNo inside the Pre-Sync Dialog
  $('#dlg-labref-combo')?.addEventListener('change', (e) => {
    if (e.target.value) applyLabRefToSyncDialog(e.target.value);
  });

  $('#dlg-labref-category')?.addEventListener('change', () => {
    filterAndLoadLabRefs(false, null);
  });

  let labRefSearchTimer = null;
  $('#dlg-labref-search')?.addEventListener('input', () => {
    clearTimeout(labRefSearchTimer);
    labRefSearchTimer = setTimeout(() => filterAndLoadLabRefs(false, null), 220);
  });

  // Sync quick-edit summary fields directly into [data-sync-input] controls
  const bindQuickFieldSync = (srcSelector, targetKeys) => {
    $(srcSelector)?.addEventListener('input', (e) => {
      const val = e.target.value;
      targetKeys.forEach((k) => {
        const inp = $(`[data-sync-input="${k}"]`);
        if (inp) inp.value = val;
      });
    });
    $(srcSelector)?.addEventListener('change', (e) => {
      const val = e.target.value;
      targetKeys.forEach((k) => {
        const inp = $(`[data-sync-input="${k}"]`);
        if (inp) inp.value = val;
      });
    });
  };
  bindQuickFieldSync('#dlg-lr-trno', ['report_no']);
  bindQuickFieldSync('#dlg-lr-result', ['overall_result']);
  bindQuickFieldSync('#dlg-lr-matcode', ['material_code']);
  bindQuickFieldSync('#dlg-lr-matdesc', ['product_name', 'material_name']);
  bindQuickFieldSync('#dlg-lr-supplier', ['supplier_name']);
  bindQuickFieldSync('#dlg-lr-style', ['style_no', 'product_id']);
  bindQuickFieldSync('#dlg-lr-color', ['colorway_name']);

  $('#sync-source-select')?.addEventListener('change', (e) => {
    const selectedAppNo = e.target.value;
    const found = state.reports.find((r) => r.application_no === selectedAppNo);
    if (found) renderSyncDialogRows(found);
  });

  // Check / uncheck all fields in Pre-Sync Dialog
  $('#sync-check-all')?.addEventListener('change', (e) => {
    const checked = e.target.checked;
    $$('#sync-fields-tbody .sync-field-check').forEach((c) => { c.checked = checked; });
    updateSelectedFieldCount();
  });

  // Confirm & Fill Form OR Confirm, Fill & 1-Click Send Report
  $('#btn-confirm-sync')?.addEventListener('click', () => executeSync(false));
  $('#btn-confirm-and-send-live')?.addEventListener('click', () => executeSync(true));

  // Manage Company Reports Modal
  $('#btn-manage-reports')?.addEventListener('click', () => {
    const active = state.reports.find((r) => r.application_no === state.activeAppNo) || state.reports[0];
    if (active) {
      Object.keys(active).forEach((k) => {
        const el = $(`#cr_${k}`);
        if (el) el.value = active[k] ?? '';
      });
    }
    $('#manage-reports-backdrop')?.classList.remove('hidden');
  });

  $('#btn-close-manage-modal')?.addEventListener('click', () => {
    $('#manage-reports-backdrop')?.classList.add('hidden');
  });

  $('#company-report-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const payload = Object.fromEntries(formData.entries());

    try {
      const res = await apiFetch('interlink/report/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      if (res.report) {
        const idx = state.reports.findIndex((r) => r.application_no === res.report.application_no);
        if (idx >= 0) state.reports[idx] = res.report;
        else state.reports.unshift(res.report);
      }
      renderSearchGrid();
      $('#manage-reports-backdrop')?.classList.add('hidden');
      showToast(`✓ Saved NEXUS-L company report data for ${payload.application_no}`);
    } catch (err) {
      showToast(err.message || 'Could not save report.', true);
    }
  });

  // Sync History Modal
  $('#btn-sync-logs')?.addEventListener('click', () => {
    renderSyncLogsTable();
    $('#sync-logs-backdrop')?.classList.remove('hidden');
  });

  $('#btn-close-logs-modal')?.addEventListener('click', () => {
    $('#sync-logs-backdrop')?.classList.add('hidden');
  });

  // Initial render + immediate live sync of Page 1 (15 rows out of 517 pages / 7,749 items) + start 15s auto-sync
  renderSearchGrid();
  fetchLiveInterlinkPage(1, 15, false, true);
  startOrStopAutoSync();
})();
