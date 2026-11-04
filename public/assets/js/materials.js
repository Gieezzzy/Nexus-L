/**
 * NEXUS-L Material Testing & Cyclic Monitoring Suite (LMS Flow Layout & dbnexus API Sync)
 */
(() => {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const baseMeta = $('meta[name="base-url"]');
  const BASE_URL = baseMeta ? baseMeta.content.replace(/\/+$/, '') + '/' : location.origin + '/';

  const showToast = (msg, type = 'info') => {
    if (window.nxToast) {
      window.nxToast(msg, type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'));
      return;
    }
    const t = $('#dtr-toast');
    if (!t) return;
    t.textContent = msg;
    t.style.background = type === 'error' ? '#dc2626' : '#0f172a';
    t.classList.remove('hidden');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.add('hidden'), 4200);
  };

  const esc = (str) =>
    String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

  const apiFetch = async (path, opts = {}) => {
    const headers = Object.assign(
      {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="X-CSRF-TOKEN"]')?.content || '',
      },
      opts.body ? { 'Content-Type': 'application/json' } : {},
      opts.headers || {}
    );
    const res = await fetch(BASE_URL + path.replace(/^\/+/, ''), Object.assign({}, opts, { headers }));
    return res.json();
  };

  let currentWstCategory = $('.dtr-subtab[data-wst-cat].active, .wst-cat-tab.active')?.dataset?.wstCat || 'martindale-abrasion';
  let currentWstTestId = null;
  let activeWstGroup = 'dry';

  // Modal open / close handlers for "Create New Test" popup
  const openWstNewModal = () => {
    refreshNextLabref();
    $('#wst-new-modal-backdrop')?.classList.remove('hidden');
  };

  const closeWstNewModal = () => {
    $('#wst-new-modal-backdrop')?.classList.add('hidden');
  };

  $('#btn-wst-open-new-modal')?.addEventListener('click', openWstNewModal);
  $('#btn-wst-trigger-new')?.addEventListener('click', openWstNewModal);
  $('#btn-wst-close-new-modal')?.addEventListener('click', closeWstNewModal);
  $('#btn-wst-new-cancel')?.addEventListener('click', closeWstNewModal);

  // Filter state for Material Test list
  const wstFilters = {
    suite: 'material',
    category: currentWstCategory || 'martindale-abrasion',
    outcome: 'all',
    status: 'all',
    q: '',
    from_date: '',
    to_date: '',
  };

  // Fetch list of records from dbnexus
  const loadWstRecords = async () => {
    const tbody = $('#wst-list-tbody');
    const cntBadge = $('#wst-record-count');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="8" class="muted" style="text-align:center;padding:24px"><svg class="ic animate-spin" aria-hidden="true" style="margin-right:6px"><use href="#i-sync"/></svg> Loading test sessions from dbnexus...</td></tr>';
    try {
      const params = new URLSearchParams();
      params.set('suite', 'material');
      if (wstFilters.category) params.set('category', wstFilters.category);
      if (wstFilters.outcome && wstFilters.outcome !== 'all') params.set('outcome', wstFilters.outcome);
      if (wstFilters.status && wstFilters.status !== 'all') params.set('status', wstFilters.status);
      if (wstFilters.q) params.set('q', wstFilters.q);
      if (wstFilters.from_date) params.set('from_date', wstFilters.from_date);
      if (wstFilters.to_date) params.set('to_date', wstFilters.to_date);

      const res = await apiFetch(`wst/records?${params.toString()}`);
      if (!res.success || !res.records || res.records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="muted" style="text-align:center;padding:28px">No test sessions match the current filter criteria. Click "+ New Test" to create one.</td></tr>';
        if (cntBadge) cntBadge.textContent = 'Showing 0 records';
        $('#wst-kpi-total') && ($('#wst-kpi-total').textContent = '0');
        $('#wst-kpi-running') && ($('#wst-kpi-running').textContent = '0');
        $('#wst-kpi-completed') && ($('#wst-kpi-completed').textContent = '0');
        $('#wst-kpi-pending') && ($('#wst-kpi-pending').textContent = '0');
        return;
      }

      // Compute quick KPIs
      let runCount = 0;
      let doneCount = 0;
      let pendCount = 0;
      res.records.forEach((r) => {
        if (r.is_running == 1 || r.status === 'RUNNING') runCount++;
        else if (r.status === 'COMPLETED') doneCount++;
        else pendCount++;
      });

      $('#wst-kpi-total') && ($('#wst-kpi-total').textContent = (res.kpis?.total ?? res.records.length).toLocaleString());
      $('#wst-kpi-running') && ($('#wst-kpi-running').textContent = (res.kpis?.running ?? runCount).toLocaleString());
      $('#wst-kpi-completed') && ($('#wst-kpi-completed').textContent = (res.kpis?.completed ?? doneCount).toLocaleString());
      $('#wst-kpi-pending') && ($('#wst-kpi-pending').textContent = (res.kpis?.pending ?? pendCount).toLocaleString());

      if (cntBadge) cntBadge.textContent = `Showing ${res.records.length} record(s)`;
      tbody.innerHTML = res.records.map((r, i) => {
        const isRun = r.is_running == 1 || r.status === 'RUNNING';
        const isDone = r.status === 'COMPLETED';
        let pillClass = isRun ? 'dtr-pill-blue' : (isDone ? 'dtr-pill-pass' : 'dtr-pill-warn');
        let statusLabel = esc(r.status || 'PENDING');

        if (r.outcome === 'PASS') {
          pillClass = 'dtr-pill-pass';
          statusLabel = 'PASS';
        } else if (r.outcome === 'FAIL') {
          pillClass = 'dtr-pill-fail';
          statusLabel = 'FAIL / DEFECT';
        } else if (r.outcome === 'STOPPED') {
          pillClass = 'dtr-pill-warn';
          statusLabel = 'STOPPED';
        } else if (isDone) {
          pillClass = 'dtr-pill-pass';
          statusLabel = 'COMPLETED';
        } else if (isRun) {
          pillClass = 'dtr-pill-blue';
          statusLabel = 'RUNNING';
        }

        const targetCycles = Number(r.target_cycles || 100000);
        const currCycles = Number(r.current_cycles || 0);
        const pct = Math.min(100, Math.round((currCycles / targetCycles) * 100));

        return `
          <tr class="clickable-row wst-row" data-id="${r.id}" style="cursor:pointer">
            <td class="mono">${i + 1}</td>
            <td><b class="mono" style="color:var(--brand,#2563eb);font-size:13.5px">${esc(r.labreference)}</b></td>
            <td>${esc(r.testdate || '—')}</td>
            <td><span style="font-weight:600">${esc(r.type || 'Standard')}</span></td>
            <td><span class="mono sm muted">${esc(r.machine_number || 'MC-01')} &bull; ${esc(r.technician || r.operator || 'QC')}</span></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="flex:1;background:rgba(255,255,255,0.06);height:6px;border-radius:3px;overflow:hidden;min-width:70px">
                  <div style="background:${r.outcome === 'FAIL' ? '#ef4444' : (isDone ? '#22c55e' : '#3b82f6')};width:${pct}%;height:100%"></div>
                </div>
                <span class="mono sm" style="white-space:nowrap">${currCycles.toLocaleString()} / ${targetCycles.toLocaleString()}</span>
              </div>
            </td>
            <td><span class="dtr-pill ${pillClass}">${statusLabel}</span></td>
            <td style="text-align:right">
              <button type="button" class="dtr-btn-xs dtr-btn-primary wst-open-record-btn" data-id="${r.id}" style="box-shadow:0 1px 4px rgba(37,99,235,0.25)">
                <svg class="ic" aria-hidden="true" style="margin-right:2px"><use href="#i-grid"/></svg> Open Monitor
              </button>
            </td>
          </tr>
        `;
      }).join('');

      tbody.querySelectorAll('.wst-open-record-btn, .wst-row').forEach((el) => {
        el.addEventListener('click', (e) => {
          e.stopPropagation();
          const tr = el.closest('tr');
          const id = tr?.dataset.id;
          if (id) openWstMonitorModal(id);
        });
      });
    } catch (err) {
      console.error('loadWstRecords failed:', err);
      tbody.innerHTML = `<tr><td colspan="8" style="color:#ef4444;text-align:center;padding:24px">Failed to load test sessions from dbnexus: ${esc(err.message)}<br><button type="button" class="dtr-btn-xs dtr-btn-outline" id="btn-wst-retry-load" style="margin-top:10px;display:inline-flex;align-items:center;gap:4px"><svg class="ic" aria-hidden="true"><use href="#i-sync"/></svg> Retry</button></td></tr>`;
      $('#btn-wst-retry-load')?.addEventListener('click', () => loadWstRecords());
    }
  };

  // Search filter
  let wstSearchDebounce = null;
  $('#wst-list-search')?.addEventListener('input', (e) => {
    clearTimeout(wstSearchDebounce);
    wstSearchDebounce = setTimeout(() => {
      wstFilters.q = e.target.value.trim();
      loadWstRecords();
    }, 250);
  });

  // Category dropdown filter
  $('#wst-filter-category')?.addEventListener('change', (e) => {
    const val = e.target.value;
    wstFilters.category = val;
    if (val !== 'all') {
      currentWstCategory = val;
      $$('.dtr-subtab[data-wst-cat], .wst-cat-tab').forEach((t) => {
        t.classList.toggle('active', t.dataset.wstCat === val);
      });
      const title = val.replace('-', ' ').toUpperCase();
      if ($('#wst-modal-title')) $('#wst-modal-title').textContent = `${title} MONITORING`;
      if ($('#wst-new-cat')) $('#wst-new-cat').value = val;
      refreshNextLabref();
    } else {
      $$('.dtr-subtab[data-wst-cat], .wst-cat-tab').forEach((t) => t.classList.remove('active'));
    }
    loadWstRecords();
  });

  // Outcome dropdown filter
  $('#wst-filter-outcome')?.addEventListener('change', (e) => {
    wstFilters.outcome = e.target.value;
    loadWstRecords();
  });

  // Status dropdown filter
  $('#wst-filter-status')?.addEventListener('change', (e) => {
    wstFilters.status = e.target.value;
    loadWstRecords();
  });

  // Date range filter
  $('#wst-filter-from')?.addEventListener('change', (e) => {
    wstFilters.from_date = e.target.value;
    loadWstRecords();
  });
  $('#wst-filter-to')?.addEventListener('change', (e) => {
    wstFilters.to_date = e.target.value;
    loadWstRecords();
  });

  // Reset button
  $('#btn-wst-reset-filter')?.addEventListener('click', () => {
    wstFilters.q = '';
    wstFilters.outcome = 'all';
    wstFilters.status = 'all';
    wstFilters.from_date = '';
    wstFilters.to_date = '';
    wstFilters.category = currentWstCategory;

    if ($('#wst-list-search')) $('#wst-list-search').value = '';
    if ($('#wst-filter-outcome')) $('#wst-filter-outcome').value = 'all';
    if ($('#wst-filter-status')) $('#wst-filter-status').value = 'all';
    if ($('#wst-filter-from')) $('#wst-filter-from').value = '';
    if ($('#wst-filter-to')) $('#wst-filter-to').value = '';
    if ($('#wst-filter-category')) $('#wst-filter-category').value = currentWstCategory;

    $$('.dtr-subtab[data-wst-cat], .wst-cat-tab').forEach((t) => {
      t.classList.toggle('active', t.dataset.wstCat === currentWstCategory);
    });

    loadWstRecords();
    showToast('Reset all filters');
  });

  // Switch category tabs
  $$('.dtr-subtab[data-wst-cat], .wst-cat-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      $$('.dtr-subtab[data-wst-cat], .wst-cat-tab').forEach((t) => t.classList.remove('active'));
      tab.classList.add('active');
      currentWstCategory = tab.dataset.wstCat;
      wstFilters.category = currentWstCategory;
      if ($('#wst-filter-category')) $('#wst-filter-category').value = currentWstCategory;
      const title = tab.textContent.trim().toUpperCase();
      if ($('#wst-modal-title')) $('#wst-modal-title').textContent = `${title} MONITORING`;
      if ($('#wst-new-cat')) $('#wst-new-cat').value = currentWstCategory;
      refreshNextLabref();
      loadWstRecords();
      showToast(`Category: ${tab.textContent.trim()}`);
    });
  });

  let wstCatalogData = {};

  const refreshNextLabref = async () => {
    const typeSelect = $('#wst-new-type');
    const type = typeSelect?.value || '';
    const date = $('#wst-new-date')?.value || new Date().toISOString().slice(0, 10);
    try {
      const res = await apiFetch(`wst/next-labref?category=${encodeURIComponent(currentWstCategory)}&type=${encodeURIComponent(type)}&date=${encodeURIComponent(date)}`);
      if (res.success) {
        wstCatalogData = res.catalog || {};

        if (res.materials && typeSelect) {
          const currentVal = typeSelect.value;
          typeSelect.innerHTML = res.materials.map((m) => `<option value="${esc(m)}" ${m === currentVal ? 'selected' : ''}>${esc(m)}</option>`).join('');
          if (!typeSelect.value && res.materials.length > 0) {
            typeSelect.value = res.materials[0];
          }
        }
        if (res.labref && $('#wst-new-labref')) {
          $('#wst-new-labref').value = res.labref;
        }

        syncNewSampleGroups();
      }
    } catch (_) {}
  };

  // Calculate LMS batch breakdown helper
  const batchSummary = (count, batchSize) => {
    const batches = [];
    for (let remaining = count; remaining > 0; remaining -= batchSize) {
      batches.push(Math.min(remaining, batchSize));
    }
    return batches.map((size, index) => `Batch ${index + 1}: ${size}`).join(' · ');
  };

  const syncNewSampleGroups = (resetCounts = false) => {
    const type = $('#wst-new-type')?.value;
    const mat = wstCatalogData[type];
    const groups = mat?.groups || {};

    ['dry', 'wet', 'hydro'].forEach((g) => {
      const check = $(`#wst-check-${g}`);
      const input = $(`#wst-count-${g}`);
      const prev = $(`#wst-preview-${g}`);
      const opt = $(`#wst-option-${g}`);
      if (!check || !input || !prev || !opt) return;

      const ok = Boolean(groups[g]);
      check.disabled = !ok;
      opt.classList.toggle('is-disabled', !ok);

      if (!ok) {
        check.checked = false;
        input.disabled = true;
        prev.textContent = '';
      } else {
        if (resetCounts) {
          check.checked = true;
          input.value = String(groups[g].count || 4);
        }
        input.disabled = !check.checked;
      }

      const standardCount = Number(groups[g]?.count || 0);
      const count = check.checked ? Number(input.value) || 0 : 0;
      if (standardCount && count) {
        prev.textContent = batchSummary(count, standardCount);
      } else if (standardCount) {
        prev.textContent = `Standard batch: ${standardCount}`;
      } else {
        prev.textContent = '';
      }
    });

    // Keep at least one selected if available
    const anyChecked = ['dry', 'wet', 'hydro'].some((g) => $(`#wst-check-${g}`)?.checked);
    if (!anyChecked) {
      const dry = $('#wst-check-dry');
      if (dry && !dry.disabled) {
        dry.checked = true;
        const dryInput = $('#wst-count-dry');
        if (dryInput) dryInput.disabled = false;
      }
    }

    // Update trigger button label e.g. "4D / 4W"
    const shortLabels = { dry: 'D', wet: 'W', hydro: 'H' };
    const selectedParts = [];
    ['dry', 'wet', 'hydro'].forEach((g) => {
      const check = $(`#wst-check-${g}`);
      const input = $(`#wst-count-${g}`);
      if (check?.checked) {
        const val = Number(input?.value) || 0;
        if (val > 0) selectedParts.push(`${val}${shortLabels[g]}`);
      }
    });
    const trigger = $('#wst-new-samples-trigger');
    if (trigger) {
      trigger.textContent = selectedParts.length ? selectedParts.join(' / ') : 'Choose sample types and amount';
    }
  };

  // Modal open / close handlers for sample modal
  const openSampleModal = () => {
    syncNewSampleGroups(false);
    $('#wst-sample-modal-backdrop')?.classList.remove('hidden');
  };
  const closeSampleModal = () => {
    $('#wst-sample-modal-backdrop')?.classList.add('hidden');
  };

  $('#wst-new-samples-trigger')?.addEventListener('click', openSampleModal);
  $('#btn-wst-open-sample-modal')?.addEventListener('click', openSampleModal);
  $('#btn-wst-close-sample-modal')?.addEventListener('click', closeSampleModal);
  $('#btn-wst-done-sample-modal')?.addEventListener('click', () => {
    syncNewSampleGroups(false);
    closeSampleModal();
  });
  $('#wst-sample-modal-backdrop')?.addEventListener('click', (e) => {
    if (e.target.id === 'wst-sample-modal-backdrop') closeSampleModal();
  });

  ['dry', 'wet', 'hydro'].forEach((g) => {
    $(`#wst-check-${g}`)?.addEventListener('change', () => syncNewSampleGroups(false));
    $(`#wst-count-${g}`)?.addEventListener('input', () => syncNewSampleGroups(false));
  });

  $('#btn-wst-regen-labref')?.addEventListener('click', refreshNextLabref);
  $('#wst-new-type')?.addEventListener('change', () => {
    syncNewSampleGroups(true);
    refreshNextLabref();
  });
  $('#wst-new-date')?.addEventListener('change', refreshNextLabref);
  $('#wst-new-cat')?.addEventListener('change', (e) => {
    currentWstCategory = e.target.value;
    $$('.dtr-subtab[data-wst-cat], .wst-cat-tab').forEach((t) => t.classList.toggle('active', t.dataset.wstCat === currentWstCategory));
    refreshNextLabref();
  });

  // Cached state for active monitor test
  let currentWstGroups = {};
  let currentWstState = {};

  // Live monitor modal renderer
  const openWstMonitorModal = async (testId) => {
    currentWstTestId = testId;
    const tableWrap = $('.wst-monitoring-container .dtr-table-wrap');
    if (tableWrap) {
      tableWrap.innerHTML = '<div style="text-align:center;padding:24px;color:#64748b"><svg class="ic animate-spin" aria-hidden="true" style="margin-right:6px"><use href="#i-sync"/></svg> Loading checkpoint matrix...</div>';
    }
    $('#wst-monitor-modal-backdrop')?.classList.remove('hidden');

    try {
      const res = await apiFetch(`wst/record/${testId}`);
      if (!res.success || !res.record) {
        showToast('Failed to load test record.', 'error');
        return;
      }

      const r = res.record;
      currentWstGroups = res.groups || {};
      currentWstState = res.state || {};

      if ($('#wst-modal-dt-labref')) $('#wst-modal-dt-labref').value = r.labreference;
      if ($('#wst-card-dt-labref')) $('#wst-card-dt-labref').textContent = r.labreference;
      if ($('#wst-modal-labref-badge')) $('#wst-modal-labref-badge').textContent = r.labreference;
      if ($('#wst-modal-dt-date')) $('#wst-modal-dt-date').value = r.testdate || '';
      if ($('#wst-card-dt-date')) $('#wst-card-dt-date').textContent = r.testdate || '—';
      const sampleSummary = `${r.samplecountdry}D / ${r.samplecountwet}W / ${r.samplecounthydro}H`;
      if ($('#wst-modal-dt-samples')) $('#wst-modal-dt-samples').value = sampleSummary;
      if ($('#wst-card-dt-samples')) $('#wst-card-dt-samples').textContent = sampleSummary;
      if ($('#wst-modal-dt-type')) $('#wst-modal-dt-type').value = r.type || 'Standard';
      if ($('#wst-card-dt-type')) $('#wst-card-dt-type').textContent = r.type || 'Standard Flexing';

      if ($('#wst-view-op')) $('#wst-view-op').textContent = r.operator || 'QC Specialist';
      if ($('#wst-view-mc')) $('#wst-view-mc').textContent = r.machine_number || 'MC-FLEX-01';
      if ($('#wst-view-temp')) $('#wst-view-temp').innerHTML = `${Number(r.temperature || 23.0).toFixed(1)} &deg;C`;
      if ($('#wst-view-hum')) $('#wst-view-hum').textContent = `${Number(r.humidity || 50.0).toFixed(1)} %RH`;
      if ($('#wst-view-note')) $('#wst-view-note').textContent = r.remarks || (r.status === 'COMPLETED' ? 'Session Completed' : 'In Progress');

      const isRun = r.is_running == 1 || r.status === 'RUNNING';
      const isDone = r.status === 'COMPLETED' || res.isCompleted;

      // Update dynamic status badge and pulsing dot
      const badge = $('#wst-modal-status-badge');
      const badgeText = $('#wst-modal-status-text');
      const pulseDot = $('#wst-modal-pulse-dot');
      if (badge) {
        const statusStr = isDone ? 'COMPLETED' : (isRun ? 'RUNNING' : (r.status || 'PENDING'));
        if (badgeText) badgeText.textContent = statusStr;
        else badge.textContent = statusStr;
        badge.className = `dtr-pill ${isRun ? 'dtr-pill-pass' : (isDone ? 'dtr-pill-blue' : 'dtr-pill-warn')}`;
      }
      if (pulseDot) {
        pulseDot.className = `wst-pulse-dot ${isRun ? 'wst-pulse-dot-green' : (isDone ? 'wst-pulse-dot-blue' : 'wst-pulse-dot-amber')}`;
      }

      // State-Aware Action Rail: Toggle RUN vs Active indicator vs Disabled on Complete
      const startBtn = $('#btn-wst-modal-start');
      const runningBar = $('#wst-running-indicator-bar');
      const findingsBtn = $('#btn-wst-modal-findings');
      const stopSampleBtn = $('#btn-wst-modal-stop-sample');
      const stopTestBtn = $('#btn-wst-modal-stop');

      if (isRun && !isDone) {
        startBtn?.classList.add('hidden');
        runningBar?.classList.remove('hidden');
        if (findingsBtn) {
          findingsBtn.classList.remove('wst-btn-secondary');
          findingsBtn.classList.add('dtr-btn-primary');
          findingsBtn.style.background = '#2563eb';
          findingsBtn.style.borderColor = '#2563eb';
          findingsBtn.style.color = '#ffffff';
        }
      } else {
        startBtn?.classList.remove('hidden');
        runningBar?.classList.add('hidden');
        if (startBtn) {
          if (isDone) {
            startBtn.disabled = true;
            startBtn.style.background = '#f1f5f9';
            startBtn.style.borderColor = '#cbd5e1';
            startBtn.style.color = '#16a34a';
            startBtn.style.opacity = '1';
            startBtn.style.cursor = 'default';
            startBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Test Completed';
          } else {
            startBtn.disabled = false;
            startBtn.style.background = '#16a34a';
            startBtn.style.borderColor = '#16a34a';
            startBtn.style.color = '#ffffff';
            startBtn.style.opacity = '1';
            startBtn.style.cursor = 'pointer';
            startBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> RUN / START';
          }
        }
        if (findingsBtn) {
          findingsBtn.classList.remove('dtr-btn-primary');
          findingsBtn.classList.add('wst-btn-secondary');
          findingsBtn.style.background = '';
          findingsBtn.style.borderColor = '';
          findingsBtn.style.color = '';
        }
      }

      // When test is completed (all samples finished or stop test triggered)
      if (findingsBtn) {
        findingsBtn.disabled = isDone;
        findingsBtn.style.opacity = isDone ? '0.45' : '1';
        findingsBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
      }
      if (stopSampleBtn) {
        stopSampleBtn.disabled = isDone;
        stopSampleBtn.style.opacity = isDone ? '0.45' : '1';
        stopSampleBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
      }
      if (stopTestBtn) {
        stopTestBtn.disabled = isDone;
        stopTestBtn.style.opacity = isDone ? '0.45' : '1';
        stopTestBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
      }

      renderMonitoringTable(res.groups, res.state);
      showToast(`Loaded monitor: ${r.labreference}`);
    } catch (err) {
      showToast(`Error opening monitor: ${err.message}`, 'error');
    }
  };

  const closeWstMonitorModal = () => {
    $('#wst-monitor-modal-backdrop')?.classList.add('hidden');
    loadWstRecords(currentWstCategory);
  };

  $('#btn-wst-open-modal-monitor')?.addEventListener('click', () => {
    const firstRow = $('#wst-list-tbody tr.wst-row');
    if (firstRow?.dataset.id) openWstMonitorModal(firstRow.dataset.id);
    else showToast('Please select a test from the queue first.');
  });
  $('#btn-wst-close-monitor-modal')?.addEventListener('click', closeWstMonitorModal);
  $('#btn-wst-cancel-monitor-modal')?.addEventListener('click', closeWstMonitorModal);

  // Render monitoring cycle grid
  const renderMonitoringTable = (groups, state) => {
    const groupTabsContainer = $('.wst-monitoring-container .wst-group-tabs');
    const tableWrap = $('.wst-monitoring-container .dtr-table-wrap');
    if (!groupTabsContainer || !tableWrap) return;

    const availableGroups = Object.keys(groups || {});
    if (availableGroups.length === 0) {
      tableWrap.innerHTML = '<p class="muted" style="padding:16px;text-align:center">No sample groups configured for this test.</p>';
      return;
    }

    if (!availableGroups.includes(activeWstGroup)) {
      activeWstGroup = availableGroups[0];
    }

    // Populate the findings modal cycle dropdown with current category's exact cycles
    const findingsCycleSelect = $('#wst-findings-cycle');
    if (findingsCycleSelect && groups[activeWstGroup]?.cycles) {
      findingsCycleSelect.innerHTML = groups[activeWstGroup].cycles.map((c) => {
        return `<option value="${c}">${Number(c).toLocaleString()} Cycles</option>`;
      }).join('');
    }

    groupTabsContainer.innerHTML = availableGroups.map((gKey) => {
      const g = groups[gKey];
      return `<button type="button" class="wst-gt-btn wst-modal-gt-btn ${gKey === activeWstGroup ? 'active' : ''}" data-modal-gt="${gKey}">${g.label} (${g.count})</button>`;
    }).join('');

    groupTabsContainer.querySelectorAll('.wst-modal-gt-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        activeWstGroup = btn.dataset.modalGt;
        renderMonitoringTable(groups, state);
      });
    });

    const curr = groups[activeWstGroup];
    if (!curr) return;

    const cycles = curr.cycles || [];
    const count = curr.count || 1;
    const recordedMap = state?.recorded?.[activeWstGroup] || {};
    const stoppedMap = state?.stopped?.[activeWstGroup] || {};

    // Determine currently active target cycle (the lowest cycle with unrecorded running samples)
    let activeCycle = null;
    let maxRecordedCycle = 0;
    for (const c of cycles) {
      let cycleHasUnrecorded = false;
      for (let s = 1; s <= count; s++) {
        const isStopped = stoppedMap[s];
        const isRecorded = (recordedMap[s] || []).includes(c);
        if (isRecorded && c > maxRecordedCycle) maxRecordedCycle = c;
        if (!isStopped && !isRecorded) {
          cycleHasUnrecorded = true;
        }
      }
      if (cycleHasUnrecorded && activeCycle === null) {
        activeCycle = c;
      }
    }

    // Update Overall Test Progress Bar
    const targetCycleLimit = cycles[cycles.length - 1] || 100000;
    const pct = Math.min(100, Math.round((maxRecordedCycle / targetCycleLimit) * 100));
    if ($('#wst-progress-cycles')) $('#wst-progress-cycles').textContent = maxRecordedCycle.toLocaleString();
    if ($('#wst-progress-target')) $('#wst-progress-target').textContent = targetCycleLimit.toLocaleString();
    if ($('#wst-progress-pct')) $('#wst-progress-pct').textContent = `${pct}%`;
    if ($('#wst-progress-bar-fill')) $('#wst-progress-bar-fill').style.width = `${pct}%`;

    let html = `
      <table class="dtr-table wst-cycle-table">
        <thead>
          <tr>
            <th style="min-width:110px">SPECIMEN</th>
            ${cycles.map((c) => {
              const isActive = c === activeCycle;
              return `<th class="${isActive ? 'wst-cell-active' : ''}" style="${isActive ? 'border-top:2px solid #3b82f6;' : ''}">${Number(c).toLocaleString()}</th>`;
            }).join('')}
          </tr>
        </thead>
        <tbody>
    `;

    const batchSize = Number(curr?.batchSize || curr?.standardCount || 4);

    for (let s = 1; s <= count; s++) {
      const rec = recordedMap[s] || [];
      const isStopped = Boolean(stoppedMap[s]);
      const batch = Math.floor((s - 1) / batchSize) + 1;
      const isBatchStart = (s - 1) % batchSize === 0;
      const rowClass = isBatchStart ? 'sample-batch-start' : '';
      const specimenLabel = isBatchStart
        ? `<div class="specimen-label-wrap"><span class="specimen-id">${s}-${curr.prefix}</span><span class="sample-batch-pill">Batch ${batch}</span></div>`
        : `<div class="specimen-label-wrap"><span class="specimen-id">${s}-${curr.prefix}</span></div>`;

      html += `<tr class="${rowClass}"><td class="mono sample-label" style="font-weight:700">${specimenLabel}</td>`;
      cycles.forEach((c) => {
        const isRecorded = rec.includes(c);
        const isTarget = (!isRecorded && !isStopped && c === activeCycle);
        let cellClass = 'cycle-cell';
        let cellContent = '';
        let titleAttr = '';

        if (isRecorded) {
          cellClass += ' is-recorded';
          cellContent = '✓';
          const fInfo = state?.findings?.[activeWstGroup]?.[s]?.[c];
          if (fInfo) {
            titleAttr = `Specimen ${s}-${curr.prefix} · ${Number(c).toLocaleString()} Cycles: ${fInfo.findings || 'Passed'} (${fInfo.result || 'PASS'})${fInfo.by ? ' by ' + fInfo.by : ''}`;
          } else {
            titleAttr = `Specimen ${s}-${curr.prefix} · ${Number(c).toLocaleString()} Cycles: Passed Checkpoint`;
          }
        } else if (isStopped) {
          cellClass += ' is-stopped';
          titleAttr = `Specimen ${s}-${curr.prefix} was stopped prior to this checkpoint.`;
        } else if (isTarget) {
          cellClass += ' is-target';
          titleAttr = `Next target checkpoint for Specimen ${s}-${curr.prefix}: ${Number(c).toLocaleString()} Cycles. Click to log findings.`;
        } else {
          titleAttr = `Specimen ${s}-${curr.prefix} · ${Number(c).toLocaleString()} Cycles: Pending checkpoint`;
        }

        html += `<td class="${cellClass}" data-group="${activeWstGroup}" data-sample="${s}" data-cycle="${c}" title="${titleAttr}">${cellContent}</td>`;
      });
      html += `</tr>`;
    }

    html += `</tbody></table>`;
    tableWrap.innerHTML = html;

    // Direct cell click handler: Quick log or view findings
    tableWrap.querySelectorAll('.cycle-cell').forEach((td) => {
      td.addEventListener('click', () => {
        const sampleNum = parseInt(td.dataset.sample, 10);
        const cycleNum = parseInt(td.dataset.cycle, 10);
        const isRecorded = td.classList.contains('is-recorded');
        const isStopped = td.classList.contains('is-stopped');

        if (isStopped) {
          showToast(`Specimen ${sampleNum}-${curr.prefix} is stopped.`, 'warn');
          return;
        }

        if (isRecorded) {
          const fInfo = state?.findings?.[activeWstGroup]?.[sampleNum]?.[cycleNum];
          const desc = fInfo ? `${fInfo.findings || 'Passed'} [${fInfo.result || 'PASS'}]` : 'Passed Checkpoint';
          showToast(`Specimen ${sampleNum}-${curr.prefix} @ ${Number(cycleNum).toLocaleString()} Cycles: ${desc}`);
          return;
        }

        // Open findings modal focused on this sample and cycle
        openFindingsModal();
        if ($('#wst-findings-cycle')) $('#wst-findings-cycle').value = cycleNum;
        if ($('#wst-findings-start')) $('#wst-findings-start').value = sampleNum;
        if ($('#wst-findings-end')) $('#wst-findings-end').value = sampleNum;
        $('#wst-findings-presets')?.querySelectorAll('.lf-range-preset').forEach((p) => p.classList.remove('is-active'));
        updateFindingsRangeLabel();
      });
    });
  };

  // Findings modal controls
  const openFindingsModal = () => {
    const curr = currentWstGroups[activeWstGroup];
    const count = curr?.count || 4;
    const groupName = curr?.label || `${activeWstGroup.toUpperCase()} SAMPLES`;
    const cycles = curr?.cycles || [];
    const recordedMap = currentWstState?.recorded?.[activeWstGroup] || {};
    const stoppedMap = currentWstState?.stopped?.[activeWstGroup] || {};

    // Auto-detect next cycle (LMS behavior): lowest cycle that has unrecorded running specimens
    let nextUnrecordedCycle = cycles[cycles.length - 1] || 25000;
    for (const c of cycles) {
      let allDone = true;
      for (let s = 1; s <= count; s++) {
        const isStopped = Boolean(stoppedMap[s]);
        const isRecorded = (recordedMap[s] || []).includes(c);
        if (!isStopped && !isRecorded) {
          allDone = false;
          break;
        }
      }
      if (!allDone) {
        nextUnrecordedCycle = c;
        break;
      }
    }

    if ($('#wst-findings-cycle')) {
      $('#wst-findings-cycle').value = nextUnrecordedCycle;
    }

    // Set remark placeholder and text based on category mode (LMS parity)
    const isFlexCategory = ['bally-flex', 'ross-flex'].includes(currentWstCategory);
    if ($('#wst-findings-text')) {
      if (isFlexCategory) {
        $('#wst-findings-text').value = 'NO DAMAGE OF COATING';
        $('#wst-findings-text').placeholder = 'No cracking, peeling, or delamination observed.';
      } else {
        $('#wst-findings-text').value = 'No noticeable abrasion or surface damage.';
        $('#wst-findings-text').placeholder = 'Enter inspection details (e.g. slight color transfer, minor wear, acceptable)...';
      }
    }

    if ($('#wst-findings-group-display')) $('#wst-findings-group-display').value = groupName;
    if ($('#wst-findings-start')) {
      $('#wst-findings-start').value = 1;
      $('#wst-findings-start').max = count;
    }
    if ($('#wst-findings-end')) {
      $('#wst-findings-end').value = count;
      $('#wst-findings-end').max = count;
    }

    // Render batch & all preset range buttons (LMS Parity)
    const presetsContainer = $('#wst-findings-presets');
    if (presetsContainer) {
      const batchSize = Number(curr?.batchSize || curr?.standardCount || 4);
      let presetBtns = '';
      for (let bStart = 1, bNum = 1; bStart <= count; bStart += batchSize, bNum++) {
        const bEnd = Math.min(count, bStart + batchSize - 1);
        presetBtns += `<button type="button" class="lf-range-preset" data-first="${bStart}" data-last="${bEnd}">Batch ${bNum} (${bStart}–${bEnd})</button>`;
      }
      presetBtns += `<button type="button" class="lf-range-preset is-active" data-first="1" data-last="${count}">All (1–${count})</button>`;
      presetsContainer.innerHTML = presetBtns;

      presetsContainer.querySelectorAll('.lf-range-preset').forEach((btn) => {
        btn.addEventListener('click', () => {
          if ($('#wst-findings-start')) $('#wst-findings-start').value = btn.dataset.first;
          if ($('#wst-findings-end')) $('#wst-findings-end').value = btn.dataset.last;
          presetsContainer.querySelectorAll('.lf-range-preset').forEach((p) => p.classList.toggle('is-active', p === btn));
          updateFindingsRangeLabel();
        });
      });
    }

    updateFindingsRangeLabel();
    $('#wst-findings-modal-backdrop')?.classList.remove('hidden');
  };

  const closeFindingsModal = () => {
    $('#wst-findings-modal-backdrop')?.classList.add('hidden');
  };

  const updateFindingsRangeLabel = () => {
    const s = parseInt($('#wst-findings-start')?.value || '1', 10);
    const e = parseInt($('#wst-findings-end')?.value || '1', 10);
    const minS = Math.min(s, e);
    const maxS = Math.max(s, e);
    const stoppedMap = currentWstState?.stopped?.[activeWstGroup] || {};

    let runningSelected = 0;
    let stoppedSelected = 0;

    for (let i = minS; i <= maxS; i++) {
      if (stoppedMap[i]) {
        stoppedSelected++;
      } else {
        runningSelected++;
      }
    }

    if ($('#wst-findings-range-info')) {
      const parts = [`${runningSelected} running specimen(s)`];
      if (stoppedSelected > 0) parts.push(`${stoppedSelected} stopped (will be skipped)`);
      $('#wst-findings-range-info').textContent = parts.join(' · ');
    }
  };

  $('#wst-findings-start')?.addEventListener('input', updateFindingsRangeLabel);
  $('#wst-findings-end')?.addEventListener('input', updateFindingsRangeLabel);

  $('#btn-wst-action-monitored')?.addEventListener('click', openFindingsModal);
  $('#btn-wst-modal-findings')?.addEventListener('click', openFindingsModal);
  $('#btn-wst-close-findings-modal')?.addEventListener('click', closeFindingsModal);
  $('#btn-wst-cancel-findings')?.addEventListener('click', closeFindingsModal);

  $('#btn-wst-save-findings')?.addEventListener('click', async () => {
    if (!currentWstTestId) {
      showToast('No active test selected.', 'error');
      return;
    }
    const cycle = parseInt($('#wst-findings-cycle')?.value || '25000', 10);
    const findings = $('#wst-findings-text')?.value.trim() || 'Passed inspection';
    const pass = $('#wst-findings-pass')?.checked;

    const startNum = parseInt($('#wst-findings-start')?.value || '1', 10);
    const endNum = parseInt($('#wst-findings-end')?.value || '1', 10);
    const stoppedMap = currentWstState?.stopped?.[activeWstGroup] || {};
    const sampleArr = [];
    for (let i = Math.min(startNum, endNum); i <= Math.max(startNum, endNum); i++) {
      if (!stoppedMap[i]) {
        sampleArr.push(i);
      }
    }

    if (sampleArr.length === 0) {
      showToast('All specimens in the selected range are stopped. No specimens to record.', 'error');
      return;
    }

    try {
      const res = await apiFetch(`wst/log-findings/${currentWstTestId}`, {
        method: 'POST',
        body: JSON.stringify({
          cycle,
          group: activeWstGroup,
          findings,
          pass,
          samples: sampleArr,
        }),
      });

      if (res.success) {
        closeFindingsModal();
        openWstMonitorModal(currentWstTestId);
        showToast(res.message || 'Saved checkpoint findings.', 'success');
      } else {
        showToast(res.message || 'Failed to save findings.', 'error');
      }
    } catch (err) {
      showToast(`Error: ${err.message}`, 'error');
    }
  });

  // =========================================================================
  // STOP SAMPLE MODAL CONTROLS (LMS PARITY)
  // =========================================================================
  const openStopSampleModal = () => {
    const list = $('#wst-stop-sample-list');
    if (!list) return;

    const curr = currentWstGroups[activeWstGroup];
    const count = curr?.count || 4;
    const stoppedMap = currentWstState?.stopped?.[activeWstGroup] || {};
    const recordedMap = currentWstState?.recorded?.[activeWstGroup] || {};
    const cycles = curr?.cycles || [];
    const lastCycle = cycles.length > 0 ? cycles[cycles.length - 1] : 0;

    let itemsHtml = '';
    let runningCount = 0;

    for (let s = 1; s <= count; s++) {
      const isStopped = Boolean(stoppedMap[s]);
      const isComplete = lastCycle > 0 && (recordedMap[s] || []).includes(lastCycle);
      const isFinished = isStopped || isComplete;
      if (!isFinished) runningCount++;

      itemsHtml += `
        <label style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:8px;background:rgba(255,255,255,${isFinished ? '0.01' : '0.04'});border:1px solid rgba(148,163,184,${isFinished ? '0.08' : '0.2'});cursor:${isFinished ? 'default' : 'pointer'};opacity:${isFinished ? '0.5' : '1'}">
          <div style="display:flex;align-items:center;gap:10px">
            <input type="checkbox" class="wst-stop-sample-chk" value="${s}" ${isStopped ? 'checked disabled' : (isComplete ? 'disabled' : '')} style="width:16px;height:16px">
            <span class="mono" style="font-weight:700;font-size:13.5px">${s}-${curr.prefix}</span>
          </div>
          <span class="sm mono" style="color:${isStopped ? '#ef4444' : (isComplete ? '#22c55e' : '#38bdf8')}">
            ${isStopped ? 'STOPPED' : (isComplete ? 'COMPLETED' : 'RUNNING')}
          </span>
        </label>
      `;
    }

    if (runningCount === 0) {
      itemsHtml += `<p class="muted" style="text-align:center;padding:16px">No running specimens remaining for this group.</p>`;
    }

    list.innerHTML = itemsHtml;
    $('#wst-stop-sample-modal-backdrop')?.classList.remove('hidden');
  };

  const closeStopSampleModal = () => {
    $('#wst-stop-sample-modal-backdrop')?.classList.add('hidden');
  };

  $('#btn-wst-modal-stop-sample')?.addEventListener('click', openStopSampleModal);
  $('#btn-wst-close-stop-sample')?.addEventListener('click', closeStopSampleModal);
  $('#btn-wst-cancel-stop-sample')?.addEventListener('click', closeStopSampleModal);

  $('#btn-wst-confirm-stop-sample')?.addEventListener('click', async () => {
    if (!currentWstTestId) return;

    const checked = $$('#wst-stop-sample-list .wst-stop-sample-chk:checked:not(:disabled)');
    const samplesToStop = Array.from(checked).map((c) => ({
      group: activeWstGroup,
      sample: parseInt(c.value, 10),
    }));

    if (samplesToStop.length === 0) {
      showToast('Please select at least one running sample to stop.', 'error');
      return;
    }

    try {
      const res = await apiFetch(`wst/stop-sample/${currentWstTestId}`, {
        method: 'POST',
        body: JSON.stringify({ samples: samplesToStop }),
      });

      if (res.success) {
        closeStopSampleModal();
        openWstMonitorModal(currentWstTestId);
        showToast(res.message || 'Specimen stopped successfully.', 'success');
      } else {
        showToast(res.message || 'Failed to stop specimen.', 'error');
      }
    } catch (err) {
      showToast(`Error: ${err.message}`, 'error');
    }
  });

  // Modal actions (Start / Stop)
  $('#btn-wst-modal-start')?.addEventListener('click', async () => {
    if (!currentWstTestId) return;
    try {
      const res = await apiFetch(`wst/start/${currentWstTestId}`, { method: 'POST' });
      if (res.success) {
        openWstMonitorModal(currentWstTestId);
        showToast('Started test session.', 'success');
      }
    } catch (err) {
      showToast(`Error: ${err.message}`, 'error');
    }
  });

  // Dedicated Stop Entire Test Modal Handlers
  const openStopTestModal = () => {
    $('#wst-stop-test-modal-backdrop')?.classList.remove('hidden');
  };
  const closeStopTestModal = () => {
    $('#wst-stop-test-modal-backdrop')?.classList.add('hidden');
  };

  $('#btn-wst-modal-stop')?.addEventListener('click', openStopTestModal);
  $('#btn-wst-close-stop-test')?.addEventListener('click', closeStopTestModal);
  $('#btn-wst-cancel-stop-test')?.addEventListener('click', closeStopTestModal);

  $('#btn-wst-confirm-stop-test')?.addEventListener('click', async () => {
    if (!currentWstTestId) return;
    const confirmBtn = $('#btn-wst-confirm-stop-test');
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Stopping...';
    }

    try {
      const res = await apiFetch(`wst/stop/${currentWstTestId}`, { method: 'POST' });
      if (res.success) {
        closeStopTestModal();
        openWstMonitorModal(currentWstTestId);
        showToast(res.message || 'Entire test session stopped and marked COMPLETED.', 'success');
      } else {
        showToast(res.message || 'Failed to stop test session.', 'error');
      }
    } catch (err) {
      showToast(`Error: ${err.message}`, 'error');
    } finally {
      if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-close"/></svg> Stop Entire Test';
      }
    }
  });

  // Create new test submit handler
  $('#btn-wst-create-submit')?.addEventListener('click', async () => {
    const category = $('#wst-new-cat')?.value || currentWstCategory;
    const type = $('#wst-new-type')?.value || 'Standard Flexing';
    const date = $('#wst-new-date')?.value || new Date().toISOString().slice(0, 10);
    const labreference = $('#wst-new-labref')?.value.trim();
    const machine = $('#wst-new-machine')?.value.trim() || 'MC-FLEX-01';
    const dry = $('#wst-check-dry')?.checked ? parseInt($('#wst-count-dry')?.value || '0', 10) : 0;
    const wet = $('#wst-check-wet')?.checked ? parseInt($('#wst-count-wet')?.value || '0', 10) : 0;
    const hydro = $('#wst-check-hydro')?.checked ? parseInt($('#wst-count-hydro')?.value || '0', 10) : 0;

    try {
      const res = await apiFetch('wst/create', {
        method: 'POST',
        body: JSON.stringify({
          category,
          type,
          date,
          labreference,
          machine_number: machine,
          dry,
          wet,
          hydro,
          target_cycles: 0,
        }),
      });

      if (res.success && res.id) {
        closeWstNewModal();
        loadWstRecords(currentWstCategory);
        openWstMonitorModal(res.id);
        showToast(res.message || 'Created new Material Testing session!', 'success');
      } else {
        showToast(res.message || 'Could not create test session.', 'error');
      }
    } catch (err) {
      showToast(`Creation error: ${err.message}`, 'error');
    }
  });

  $('#btn-wst-refresh-queue')?.addEventListener('click', () => {
    loadWstRecords(currentWstCategory);
    showToast('Refreshed test queue.');
  });

  // Print summary button inside modal
  $('#btn-wst-modal-print')?.addEventListener('click', () => {
    window.print();
  });

  // Background Live State Polling (Matches LMS 8s sync)
  let wstSyncInterval = null;
  const startWstLivePolling = () => {
    if (wstSyncInterval) clearInterval(wstSyncInterval);
    wstSyncInterval = setInterval(async () => {
      const modal = $('#wst-monitor-modal-backdrop');
      if (!modal || modal.classList.contains('hidden') || !currentWstTestId) return;

      try {
        const res = await apiFetch(`wst/state/${currentWstTestId}`);
        if (res.success) {
          currentWstState.recorded = res.recorded || {};
          currentWstState.stopped = res.stopped || {};

          const isRun = res.is_running === 1;
          const isDone = res.completed || res.status === 'COMPLETED';

          const badge = $('#wst-modal-status-badge');
          const badgeText = $('#wst-modal-status-text');
          const pulseDot = $('#wst-modal-pulse-dot');
          if (badge) {
            const statusStr = isDone ? 'COMPLETED' : (isRun ? 'RUNNING' : (res.status || 'PENDING'));
            if (badgeText) badgeText.textContent = statusStr;
            else badge.textContent = statusStr;
            badge.className = `dtr-pill ${isRun ? 'dtr-pill-pass' : (isDone ? 'dtr-pill-blue' : 'dtr-pill-warn')}`;
          }
          if (pulseDot) {
            pulseDot.className = `wst-pulse-dot ${isRun ? 'wst-pulse-dot-green' : (isDone ? 'wst-pulse-dot-blue' : 'wst-pulse-dot-amber')}`;
          }

          const startBtn = $('#btn-wst-modal-start');
          const runningBar = $('#wst-running-indicator-bar');
          const findingsBtn = $('#btn-wst-modal-findings');
          const stopSampleBtn = $('#btn-wst-modal-stop-sample');
          const stopTestBtn = $('#btn-wst-modal-stop');

          if (isRun && !isDone) {
            startBtn?.classList.add('hidden');
            runningBar?.classList.remove('hidden');
          } else {
            startBtn?.classList.remove('hidden');
            runningBar?.classList.add('hidden');
            if (startBtn) {
              startBtn.disabled = isDone;
              startBtn.style.opacity = isDone ? '0.45' : '1';
              startBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
            }
          }

          if (findingsBtn) {
            findingsBtn.disabled = isDone;
            findingsBtn.style.opacity = isDone ? '0.45' : '1';
            findingsBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
          }
          if (stopSampleBtn) {
            stopSampleBtn.disabled = isDone;
            stopSampleBtn.style.opacity = isDone ? '0.45' : '1';
            stopSampleBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
          }
          if (stopTestBtn) {
            stopTestBtn.disabled = isDone;
            stopTestBtn.style.opacity = isDone ? '0.45' : '1';
            stopTestBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
          }

          renderMonitoringTable(currentWstGroups, currentWstState);
        }
      } catch (_) {}
    }, 8000);
  };
  startWstLivePolling();

  // Trigger initial list load on page load
  loadWstRecords(currentWstCategory);
})();
