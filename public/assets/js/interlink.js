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
    { key: 'service_provider', label: '* Service Provider (DXEditor11)', section: 'Tab 1 · Top Header' },
    { key: 'lab_location', label: '* Lab Location (ITS_OFFICE_DISP)', section: 'Tab 1 · Top Header' },
    { key: 'application_no', label: 'Application No. (DXEditor13)', section: 'Tab 1 · Top Header', readonly: true },
    { key: 'service_test_type', label: '* Service Test Type (CAL_CDTL_11)', section: 'Tab 1 · Top Header' },
    { key: 'test_stage', label: '* Test Stage (DXEditor15)', section: 'Tab 1 · Top Header' },
    { key: 'service_required', label: '* Service Required (DXEditor16)', section: 'Tab 1 · Top Header' },
    { key: 'report_no', label: '* Report No. (DXEditor17)', section: 'Tab 1 · Top Header' },
    { key: 'is_retest', label: '* Is this a Retest? (Radio)', section: 'Tab 1 · Top Header', type: 'select', options: ['No', 'Yes'] },
    { key: 'previous_report_no', label: 'Previous Test Report No (DXEditor19)', section: 'Tab 1 · Top Header' },
    { key: 'related_trf_no', label: 'Related TRF Number ([10076])', section: 'Tab 1 · Top Header' },
    { key: 'email_recipients', label: 'Email Notification Recipient(s)', section: 'Tab 1 · Top Header' },

    // Section 2: Vendor / Factory / Supplier / Facility Information (Tab 1)
    { key: 'company_name', label: '* Company Name (DXEditor22)', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'interlink_code', label: '* Interlink Code (DXEditor23)', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'vendor_name', label: '* Vendor Name (TRPT_SUPPLIER_DISP)', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'vendor_id', label: '* Vendor ID (DXEditor25)', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'factory_name', label: '* Factory Name (DXEditor26)', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'factory_id', label: '* Factory ID (DXEditor27)', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'supplier_name', label: 'Supplier Name (DXEditor28)', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'supplier_id', label: 'Supplier ID (DXEditor29)', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'facility_name', label: 'Facility Name ([10076] Material)', section: 'Tab 1 · Vendor/Factory/Supplier' },
    { key: 'facility_id', label: 'Facility ID ([10076] Material)', section: 'Tab 1 · Vendor/Factory/Supplier' },

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
    { key: 'age_group', label: '* Age Group (DXEditor44)', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'cargo_receive_date', label: '* Cargo Receive Date (DXEditor45)', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'product_category', label: '* Product Category', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'test_packages', label: '* Test Package(s)', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'material_code', label: 'Material Code ([10076])', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'material_name', label: 'Material Description ([10076])', section: 'Tab 1 · Product/Material Info (ALL)' },
    { key: 'material_type', label: 'Material Type ([10076])', section: 'Tab 1 · Product/Material Info (ALL)' },

    // Section 3B: GCC/CPC & Factory Address (Tab 1)
    { key: 'gcc_cpc_required', label: 'GCC/CPC Required? (DXEditor52)', section: 'Tab 1 · GCC/CPC & Factory Address', type: 'select', options: ['No', 'Yes'] },
    { key: 'product_id', label: 'Product ID', section: 'Tab 1 · GCC/CPC & Factory Address' },
    { key: 'manufacture_date', label: 'Manufacture Date (DXEditor54)', section: 'Tab 1 · GCC/CPC & Factory Address' },
    { key: 'factory_address', label: 'Factory Address (DXEditor55)', section: 'Tab 1 · GCC/CPC & Factory Address' },
    { key: 'factory_country', label: 'Factory Country (DXEditor56)', section: 'Tab 1 · GCC/CPC & Factory Address' },
    { key: 'factory_city', label: 'Factory City (DXEditor58)', section: 'Tab 1 · GCC/CPC & Factory Address' },
    { key: 'factory_state_province', label: 'Factory State/Province (DXEditor59)', section: 'Tab 1 · GCC/CPC & Factory Address' },

    // Section 4: Upload Result Tab (Tab 2)
    { key: 'report_issue_date', label: '* Report Issue Date (DXEditor6_I)', section: 'Tab 2 · Upload Result' },
    { key: 'overall_result', label: '* Overall Result (TRPT_RESULT_DISP_0)', section: 'Tab 2 · Upload Result', type: 'select', options: ['Pass', 'Fail', 'Data'] },
    { key: 'attachment_name', label: '* Report Attachment PDF (JOB_DUMMY_004)', section: 'Tab 2 · Upload Result' },
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
    return filters;
  };

  const renderPager = () => {
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
    const filtered = state.reports.filter((r) => {
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
      tbody.innerHTML = `<tr><td colspan="38" style="text-align:center;padding:20px;color:#6b7280">No matching InterLink applications found on Page ${state.currentPage}.</td></tr>`;
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
              ? `<button type="button" class="itk-eu-btn" data-open-upload="${escHtml(r.application_no)}" title="Open Report Detail Page (${escHtml(r.application_no)})">📄</button>`
              : ''}
          </td>
          <td style="text-align:center">${hasReport ? '📎' : ''}</td>
          <td>
            <div class="itk-action-cell">
              <button type="button" class="itk-eu-btn" data-open-normal="${escHtml(r.application_no)}" title="Open Online Application Form NormalForm.aspx [10000] (E)">E</button>
              <button type="button" class="itk-eu-btn" data-open-upload="${escHtml(r.application_no)}" title="Open Online Upload Form UploadForm.aspx [${escHtml(r.form_code || (r.sample_type === 'Material' ? '10076' : '10072'))}] (U)">U</button>
            </div>
          </td>
          <td style="text-align:center">📋</td>
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
  const fetchLiveInterlinkPage = async (page = state.currentPage, pageSize = state.pageSize, forceRefresh = false, silent = false) => {
    if (state.isFetchingPage) return;
    state.isFetchingPage = true;

    const liveDot = $('#itk-live-dot');
    const liveText = $('#itk-live-text');
    if (liveDot) liveDot.className = 'itk-live-dot is-syncing';
    if (liveText) liveText.textContent = `Syncing Page ${page}...`;

    try {
      if (!silent) {
        await showLoading(`PortalMainLoadingPanel.Show() · Pulling Page ${page} of ${state.totalPages} live from InterLink 2.0...`, 180);
      }

      const res = await apiFetch('interlink/fetch-live-page', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          page: Number(page) || 1,
          pageSize: Number(pageSize) || 15,
          forceRefresh: Boolean(forceRefresh),
        }),
      });

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
          showToast(`⚡ Real-time InterLink update detected on ${state.changedApps.join(', ')}!`);
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

    const retestVal = isAlreadySynced ? (report.is_retest || 'No') : 'No';
    $$('input[name="uf_is_retest"]').forEach((r) => {
      r.checked = r.value === retestVal;
    });
  };

  const navigateToUploadForm = async (appNo, pushHistory = true) => {
    await showLoading(`PortalMainLoadingPanel.Show() · Opening live UploadForm.aspx for ${appNo}...`, 280);

    let report = state.reports.find((r) => r.application_no === appNo) || { application_no: appNo };
    state.currentView = 'upload';
    state.activeAppNo = appNo;

    if (pushHistory) {
      state.historyStack = state.historyStack.slice(0, state.historyIndex + 1);
      state.historyStack.push(`upload:${appNo}`);
      state.historyIndex = state.historyStack.length - 1;
    }

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

    populateInitialUploadForm(report);

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

  const navigateToNormalForm = async (appNo, pushHistory = true) => {
    await showLoading(`Opening live NormalForm.aspx [10000] (Online Application Form) for ${appNo}...`, 260);

    let report = state.reports.find((r) => r.application_no === appNo) || { application_no: appNo };
    state.currentView = 'normal';
    state.activeAppNo = appNo;

    if (pushHistory) {
      state.historyStack = state.historyStack.slice(0, state.historyIndex + 1);
      state.historyStack.push(`normal:${appNo}`);
      state.historyIndex = state.historyStack.length - 1;
    }

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
      combo.innerHTML = `<option value="">-- No matching LabRefNo found in dbnexus --</option>`;
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
      state.activeSamples = [{
        sample_no: '001',
        item_name: matDescVal || styleVal || lr.lab_ref_no,
        material_no: matCodeVal || lr.mtl_no || '-',
        colorway: colorVal || '-',
        sample_qty: lr.po_quantity || '1 pc',
        sample_condition: `LabRef: ${lr.lab_ref_no} (${lr.status || 'Complete'})`,
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
        attachment_name: `${lr.lab_ref_no}_TestReport.pdf`,
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
        showToast(`⚡ 1-Click Report Sent! Synced ${Object.keys(fieldsToSync).length} fields + Sample & Test Readings for ${appNo} (${fieldsToSync.report_no || ''}) to InterLink 2.0!`);
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

  // =========================================================================
  // 6. EVENT LISTENERS
  // =========================================================================
  // Delegate clicks on "U", "E", or Application No. in SearchFormWithHeader.aspx
  $('#itk-grid-tbody')?.addEventListener('click', (e) => {
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
  $$('#itk-search-table .itk-filter-row input[data-col], #itk-search-criteria-panel [data-top-filter]').forEach((inp) => {
    inp.addEventListener('input', renderSearchGrid);
    inp.addEventListener('change', renderSearchGrid);
  });

  $('#itk-btn-clear-filter')?.addEventListener('click', () => {
    $$('#itk-search-table .itk-filter-row input[data-col], #itk-search-criteria-panel [data-top-filter]').forEach((inp) => {
      if (!inp.readOnly) inp.value = '';
    });
    renderSearchGrid();
  });

  $('#itk-btn-refresh')?.addEventListener('click', () => {
    fetchLiveInterlinkPage(state.currentPage, state.pageSize, true, false);
  });

  // Live 517-page Pager clicks
  $('#itk-pager-nums')?.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-goto-page]');
    if (!trigger || trigger.disabled) return;
    const pg = Number(trigger.dataset.gotoPage);
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

  $('#uf-btn-browse-pdf')?.addEventListener('click', () => {
    const appNo = state.activeAppNo || 'A005967935';
    const inp = $('#uf_attachment_name');
    if (inp && !inp.value) {
      inp.value = `TRF_${appNo}_VansReport.pdf`;
    }
    showToast(`Selected PDF attachment: ${inp?.value || 'Report.pdf'}`);
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
