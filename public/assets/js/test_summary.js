/**
 * NEXUS-L Combined Test Results Summary
 * Whole Shoe Testing & Material Testing Suites Results, Specimen Checkpoints & Technician Sign-offs
 */
(() => {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const baseMeta = $('meta[name="base-url"]');
  const BASE_URL = baseMeta ? baseMeta.content.replace(/\/+$/, '') + '/' : location.origin + '/';

  const esc = (str) =>
    String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

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

  // State
  const state = {
    suite: 'all',
    category: 'all',
    outcome: 'all',
    status: 'all',
    q: '',
    from_date: '',
    to_date: '',
    records: [],
    kpis: {},
    isLoading: false,
  };

  let searchDebounceTimer = null;

  // Build query string based on current state
  const buildQueryString = () => {
    const params = new URLSearchParams();
    if (state.suite && state.suite !== 'all') params.set('suite', state.suite);
    if (state.category && state.category !== 'all') params.set('category', state.category);
    if (state.outcome && state.outcome !== 'all') params.set('outcome', state.outcome);
    if (state.status && state.status !== 'all') params.set('status', state.status);
    if (state.q) params.set('q', state.q);
    if (state.from_date) params.set('from_date', state.from_date);
    if (state.to_date) params.set('to_date', state.to_date);
    return params.toString();
  };

  // Fetch Summary Data & KPIs
  const loadSummaryData = async () => {
    if (state.isLoading) return;
    state.isLoading = true;

    const tbody = $('#summary-table-tbody');
    const cntBadge = $('#summary-record-count');
    if (cntBadge) cntBadge.textContent = 'Loading records...';

    try {
      const qs = buildQueryString();
      const res = await apiFetch(`wst/summary-data${qs ? '?' + qs : ''}`);

      if (!res.success) {
        throw new Error(res.message || 'Failed to load test summary data');
      }

      state.records = res.records || [];
      state.kpis = res.kpis || {};

      renderKpis(state.kpis);
      renderTable(state.records);
    } catch (err) {
      console.error('Error fetching test summary:', err);
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="10" class="muted" style="text-align:center;padding:32px;color:#ef4444">Failed to load test summary data. Please try again.</td></tr>`;
      }
      showToast('Failed to load test summary', 'error');
    } finally {
      state.isLoading = false;
    }
  };

  // Render KPI Metrics Cards
  const renderKpis = (kpi) => {
    const totalEl = $('#summary-kpi-total');
    const passEl = $('#summary-kpi-pass');
    const passRateEl = $('#summary-kpi-passrate');
    const runningEl = $('#summary-kpi-running');
    const failEl = $('#summary-kpi-fail');
    const stoppedEl = $('#summary-kpi-stopped');

    const total = Number(kpi.total || 0);
    const pass = Number(kpi.pass || 0);
    const passRate = Number(kpi.pass_rate || 0);
    const running = Number(kpi.running || 0);
    const fail = Number(kpi.fail || 0);
    const stopped = Number(kpi.stopped || 0);
    const wholeShoe = Number(kpi.whole_shoe || 0);
    const material = Number(kpi.material || 0);

    if (totalEl) totalEl.textContent = total.toLocaleString();
    if (passEl) passEl.textContent = pass.toLocaleString();
    if (passRateEl) passRateEl.textContent = `${passRate}%`;
    if (runningEl) runningEl.textContent = running.toLocaleString();
    if (failEl) failEl.textContent = fail.toLocaleString();
    if (stoppedEl) stoppedEl.textContent = stopped.toLocaleString();

    // Suite tab counters
    const pillAll = $('#summary-pill-cnt-all');
    const pillWs = $('#summary-pill-cnt-wholeshoe');
    const pillMat = $('#summary-pill-cnt-material');

    if (pillAll) pillAll.textContent = total.toLocaleString();
    if (pillWs) pillWs.textContent = wholeShoe.toLocaleString();
    if (pillMat) pillMat.textContent = material.toLocaleString();

    const subTotalEl = $('#summary-kpi-sub-total');
    if (subTotalEl) {
      if (state.suite === 'wholeshoe') subTotalEl.textContent = 'Whole Shoe Test Suite';
      else if (state.suite === 'material') subTotalEl.textContent = 'Material Testing Suite';
      else subTotalEl.textContent = 'Across all suites';
    }
  };

  // Render Summary Records Table
  const renderTable = (records) => {
    const tbody = $('#summary-table-tbody');
    const cntBadge = $('#summary-record-count');
    const pageInfo = $('#summary-pagination-info');

    if (!tbody) return;

    if (!records || records.length === 0) {
      tbody.innerHTML = `<tr><td colspan="10" class="muted" style="text-align:center;padding:36px">No test sessions match the current filter criteria.</td></tr>`;
      if (cntBadge) cntBadge.textContent = 'Showing 0 records';
      if (pageInfo) pageInfo.textContent = '0 test records found';
      return;
    }

    if (cntBadge) cntBadge.textContent = `Showing ${records.length} record(s)`;
    if (pageInfo) pageInfo.textContent = `Displaying ${records.length} matching test record(s)`;

    tbody.innerHTML = records.map((r, i) => {
      const isWholeShoe = r.suite_key === 'wholeshoe' || r.suite === 'Whole Shoe';
      const suiteBadgeClass = isWholeShoe ? 'sum-suite-wholeshoe' : 'sum-suite-material';

      // Outcome Pill Styling
      let outcomeClass = 'sum-outcome-pending';
      let outcomeLabel = r.outcome || 'PENDING';
      let pulseDot = '';

      if (r.outcome === 'PASS') {
        outcomeClass = 'sum-outcome-pass';
        outcomeLabel = 'PASS';
      } else if (r.outcome === 'FAIL') {
        outcomeClass = 'sum-outcome-fail';
        outcomeLabel = 'FAIL / DEFECT';
      } else if (r.outcome === 'STOPPED') {
        outcomeClass = 'sum-outcome-stopped';
        outcomeLabel = 'STOPPED';
      } else if (r.outcome === 'RUNNING') {
        outcomeClass = 'sum-outcome-running';
        pulseDot = '<span class="sum-pulse-dot" style="margin-right:2px"></span>';
      }

      const target = Number(r.target_cycles || 0);
      const current = Number(r.current_cycles || 0);
      const unit = esc(r.unit || 'Cycles');
      const pct = Math.min(100, Math.max(0, Number(r.progress_pct || 0)));
      const isDone = r.outcome === 'PASS' || r.status === 'COMPLETED';

      let barClass = 'run';
      if (isDone) barClass = 'done';
      else if (r.outcome === 'FAIL') barClass = 'fail';
      else if (r.outcome === 'STOPPED') barClass = 'stop';

      const monitorUrl = isWholeShoe
        ? `${BASE_URL}wholeshoe?category=${encodeURIComponent(r.category)}`
        : `${BASE_URL}materials?category=${encodeURIComponent(r.category)}`;

      return `
        <tr class="summary-row" data-id="${r.id}">
          <td style="text-align:center;color:#94a3b8;font-family:monospace;font-size:11.5px">${i + 1}</td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <b style="font-family:monospace;color:#2563eb;font-size:13px">${esc(r.labreference)}</b>
              <button type="button" class="btn-copy-labref" data-ref="${esc(r.labreference)}" title="Copy Lab Reference" style="background:none;border:none;cursor:pointer;padding:3px;color:#94a3b8;display:flex;align-items:center;border-radius:4px">
                <svg style="width:13px;height:13px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
              </button>
            </div>
          </td>
          <td>
            <span class="${suiteBadgeClass}">
              ${esc(r.suite)}
            </span>
          </td>
          <td>
            <span style="font-weight:600;font-size:12.5px;color:#0f172a">${esc(r.category_name)}</span>
          </td>
          <td>
            <span style="font-size:12.5px;color:#334155">${esc(r.type || 'Standard')}</span>
          </td>
          <td>
            <div style="display:flex;flex-direction:column;gap:1px">
              <span style="font-family:monospace;font-size:12px;font-weight:600;color:#0f172a">${esc(r.machine_number || 'MC-01')}</span>
              <span class="sum-tech-tag" title="Verified Completion Technician">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                ${esc(r.technician || 'QC Specialist')}
              </span>
            </div>
          </td>
          <td>
            <div class="sum-progress-bar-wrap">
              <div style="display:flex;justify-content:space-between;align-items:center;font-size:11.5px">
                <span style="font-family:monospace;color:#334155;font-weight:500">${current.toLocaleString()} / ${target.toLocaleString()} ${unit}</span>
                <span style="font-family:monospace;font-size:11px;color:#64748b;font-weight:600">${pct}%</span>
              </div>
              <div class="sum-progress-track">
                <div class="sum-progress-fill ${barClass}" style="width:${pct}%"></div>
              </div>
              ${r.recorded_cycles ? `<div style="font-family:monospace;font-size:10.5px;color:#2563eb;letter-spacing:-0.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px" title="Recorded cycles: ${esc(r.recorded_cycles)}">Cycles: ${esc(r.recorded_cycles)}</div>` : ''}
              ${r.recorded_remarks || r.remarks ? `<div style="font-family:monospace;font-size:10.5px;color:#0284c7;letter-spacing:-0.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px" title="Recorded remarks: ${esc(r.recorded_remarks || r.remarks)}">Remarks: ${esc(r.recorded_remarks || r.remarks)}</div>` : ''}
            </div>
          </td>
          <td>
            <span class="sum-pill-outcome ${outcomeClass}">
              ${pulseDot}
              <span>${outcomeLabel}</span>
            </span>
          </td>
          <td style="font-family:monospace;font-size:11.5px;color:#64748b">${esc(r.testdate || '—')}</td>
          <td style="text-align:right">
            <div style="display:flex;gap:5px;justify-content:flex-end">
              <button type="button" class="sum-btn-action btn-view-summary-detail" data-id="${r.id}" style="padding:4px 9px;font-size:11.5px" title="View specimen findings and technician report">
                Details
              </button>
              <a href="${monitorUrl}" class="sum-btn-action primary" style="padding:4px 9px;font-size:11.5px;text-decoration:none" title="Open in Test Monitor Workspace">
                Monitor
              </a>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  };

  // Open Detail Modal & Fetch Test Findings
  const openDetailModal = async (testId) => {
    const modal = $('#summary-detail-modal-backdrop');
    const tbody = $('#modal-findings-tbody');
    if (!modal) return;

    modal.classList.remove('hidden');
    if (tbody) {
      tbody.innerHTML = '<tr><td colspan="7" class="muted" style="text-align:center;padding:20px">Loading specimen checkpoints...</td></tr>';
    }

    try {
      const res = await apiFetch(`wst/record/${testId}`);
      if (!res.success || !res.record) {
        throw new Error(res.message || 'Unable to load test record details');
      }

      const rec = res.record;
      const groups = res.groups || {};
      const stateData = res.state || {};
      const recorded = stateData.recorded || {};
      const stopped = stateData.stopped || {};
      const findings = stateData.findings || {};

      const isWholeShoe = ['hydrolysis', 'heat-aging', 'washing', 'uv-light', 'flexing', 'deeptest', 'deep-test'].includes(rec.category);
      const suiteName = isWholeShoe ? 'Whole Shoe Test' : 'Material Test';

      // Header Meta
      $('#modal-summary-labref').textContent = rec.labreference || '—';
      $('#modal-summary-subtitle').textContent = `${suiteName} • ${rec.category || ''} • ${rec.type || ''}`;

      const suitePill = $('#modal-summary-suite-pill');
      if (suitePill) {
        suitePill.textContent = suiteName;
        suitePill.className = isWholeShoe ? 'sum-suite-wholeshoe' : 'sum-suite-material';
        suitePill.removeAttribute('style');
      }

      // Check Outcome
      let hasFail = false;
      let hasStopped = false;
      let lastTech = '';

      Object.keys(findings).forEach((gKey) => {
        Object.keys(findings[gKey] || {}).forEach((sNum) => {
          Object.keys(findings[gKey][sNum] || {}).forEach((cVal) => {
            const f = findings[gKey][sNum][cVal];
            if (f.result === 'FAIL') hasFail = true;
            if (f.by) lastTech = f.by;
          });
        });
      });

      Object.keys(stopped).forEach((gKey) => {
        if (Object.values(stopped[gKey] || {}).some(Boolean)) hasStopped = true;
      });

      const isDone = ['COMPLETED', 'COMPLETE'].includes(String(rec.status || '').toUpperCase());
      const isRun = Number(rec.is_running || 0) === 1 || String(rec.status || '').toUpperCase() === 'RUNNING';

      let outcome = 'PENDING';
      let outcomeClass = 'sum-outcome-pending';
      if (hasFail) {
        outcome = 'FAIL';
        outcomeClass = 'sum-outcome-fail';
      } else if (hasStopped) {
        outcome = 'STOPPED';
        outcomeClass = 'sum-outcome-stopped';
      } else if (isDone) {
        outcome = 'PASS';
        outcomeClass = 'sum-outcome-pass';
      } else if (isRun) {
        outcome = 'RUNNING';
        outcomeClass = 'sum-outcome-running';
      }

      const outcomePill = $('#modal-summary-outcome-pill');
      if (outcomePill) {
        outcomePill.textContent = outcome;
        outcomePill.className = `sum-pill-outcome ${outcomeClass}`;
      }

      // Fill Meta Grid
      $('#modal-meta-category').textContent = rec.category || '—';
      $('#modal-meta-type').textContent = rec.type || 'Standard';
      $('#modal-meta-machine').textContent = rec.machine_number || 'MC-01';

      const techName = rec.technician || lastTech || rec.operator || 'QC Specialist';
      $('#modal-meta-technician').textContent = techName;
      $('#modal-meta-date').textContent = rec.testdate || '—';

      const target = Number(rec.target_cycles || 0);
      const current = Number(rec.current_cycles || 0);
      const unit = ['hydrolysis', 'heat-aging', 'uv-light'].includes(rec.category) ? 'Hrs' : (rec.category === 'washing' ? 'Washes' : 'Cycles');
      $('#modal-meta-progress').textContent = `${current.toLocaleString()} / ${target.toLocaleString()} ${unit}`;

      const dry = Number(rec.samplecountdry || 0);
      const wet = Number(rec.samplecountwet || 0);
      const hydro = Number(rec.samplecounthydro || 0);
      const smp = [];
      if (dry > 0) smp.push(`Dry: ${dry}`);
      if (wet > 0) smp.push(`Wet: ${wet}`);
      if (hydro > 0) smp.push(`Hydro: ${hydro}`);
      $('#modal-meta-samples').textContent = smp.join(', ') || 'Standard Specimen Batch';

      $('#modal-meta-timestamps').textContent = `Created: ${rec.created_at || '—'} • Updated: ${rec.updated_at || '—'}`;

      // Remarks Banner
      const remarksWrap = $('#modal-remarks-container');
      const remarksText = $('#modal-remarks-text');
      if (rec.remarks && rec.remarks.trim()) {
        remarksWrap.style.display = 'block';
        remarksText.textContent = rec.remarks;
      } else {
        remarksWrap.style.display = 'none';
      }

      // Set Workspace Action Link
      const workspaceBtn = $('#modal-btn-open-workspace');
      if (workspaceBtn) {
        workspaceBtn.href = isWholeShoe
          ? `${BASE_URL}wholeshoe?category=${encodeURIComponent(rec.category)}`
          : `${BASE_URL}materials?category=${encodeURIComponent(rec.category)}`;
      }

      // Build Summarized Specimen Findings (1 clean row per sample with concatenated cycles)
      const rows = [];
      Object.keys(groups).forEach((gKey) => {
        const gDef = groups[gKey];
        const gCount = Number(gDef.count || 0);
        const gLabel = gDef.label || gKey.toUpperCase();

        for (let s = 1; s <= gCount; s++) {
          const sStopped = Boolean(stopped[gKey]?.[s]);
          const sRecorded = (recorded[gKey]?.[s] || []).map(Number).sort((a, b) => a - b);
          const sampleCyclesConcat = (stateData.sample_cycles?.[gKey]?.[s]) || (sRecorded.length > 0 ? sRecorded.join(';') : '');

          let checkpointDisplay = '—';
          if (sampleCyclesConcat) {
            checkpointDisplay = sStopped ? `${sampleCyclesConcat} (Stopped)` : sampleCyclesConcat;
          } else if (sStopped) {
            checkpointDisplay = 'Stopped';
          } else {
            checkpointDisplay = 'Pending';
          }

          let sampleResult = 'PENDING';
          let latestObservation = 'Pending evaluation';
          let latestTech = techName;
          let latestTimestamp = '—';

          if (sRecorded.length > 0) {
            sampleResult = 'PASS';
            const latestCycle = sRecorded[sRecorded.length - 1];
            const latestF = findings[gKey]?.[s]?.[latestCycle] || {};
            latestObservation = latestF.findings || 'No defects observed';
            if (latestF.by) latestTech = latestF.by;
            if (latestF.at) latestTimestamp = latestF.at;

            // Check if any cycle had a FAIL
            sRecorded.forEach((c) => {
              const f = findings[gKey]?.[s]?.[c] || {};
              if (f.result === 'FAIL') {
                sampleResult = 'FAIL';
                latestObservation = f.findings ? `Defect found at ${c.toLocaleString()} ${unit}: ${f.findings}` : `Failed at ${c.toLocaleString()} ${unit}`;
                if (f.by) latestTech = f.by;
                if (f.at) latestTimestamp = f.at;
              }
            });
          }

          if (sStopped) {
            sampleResult = 'STOPPED';
            if (sampleResult !== 'FAIL') {
              latestObservation = 'Specimen halted/stopped early';
            }
            latestTimestamp = rec.updated_at || latestTimestamp;
          }

          // Build concatenated specimen remarks matching checkpoints (e.g. No defects observed;No defects observed;Minor crease)
          let sampleRemarksConcat = (stateData.sample_remarks?.[gKey]?.[s]) || '';
          if (!sampleRemarksConcat && sRecorded.length > 0) {
            const remList = sRecorded.map((c) => {
              const f = findings[gKey]?.[s]?.[c];
              return (f && f.findings) ? f.findings : 'No defects observed';
            });
            sampleRemarksConcat = remList.join(';');
          }

          let observationsDisplay = sampleRemarksConcat || latestObservation;
          if (sStopped && !observationsDisplay) {
            observationsDisplay = 'Specimen halted/stopped early';
          }

          rows.push({
            sample: `#${s}`,
            group: gLabel,
            checkpoint: checkpointDisplay,
            findings: observationsDisplay,
            result: sampleResult,
            by: latestTech || '—',
            at: latestTimestamp || '—',
          });
        }
      });

      const countBadge = $('#modal-findings-count');
      if (countBadge) countBadge.textContent = `${rows.length} specimen sample(s)`;

      if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="muted" style="text-align:center;padding:24px">No specimen samples configured for this session.</td></tr>';
      } else {
        tbody.innerHTML = rows.map((row) => {
          let resClass = 'sum-outcome-pending';
          if (row.result === 'PASS') resClass = 'sum-outcome-pass';
          else if (row.result === 'FAIL') resClass = 'sum-outcome-fail';
          else if (row.result === 'STOPPED') resClass = 'sum-outcome-stopped';

          return `
            <tr>
              <td style="font-family:monospace;font-weight:700;color:#0f172a">${esc(row.sample)}</td>
              <td><span style="font-size:12px;color:#64748b">${esc(row.group)}</span></td>
              <td>
                <span style="font-family:monospace;font-size:12px;font-weight:600;color:${row.checkpoint === '—' || row.checkpoint === 'Pending' ? '#94a3b8' : '#2563eb'}">
                  ${esc(row.checkpoint)}
                </span>
              </td>
              <td><span style="font-size:12.5px;color:#1e293b">${esc(row.findings)}</span></td>
              <td style="text-align:center">
                <span class="sum-pill-outcome ${resClass}" style="font-size:10px;padding:2px 7px">${esc(row.result)}</span>
              </td>
              <td><span style="font-size:12px;color:#1d4ed8;font-weight:600">${esc(row.by)}</span></td>
              <td style="font-family:monospace;font-size:11px;color:#64748b">${esc(row.at)}</td>
            </tr>
          `;
        }).join('');
      }
    } catch (err) {
      console.error('Error loading detail modal:', err);
      showToast('Failed to load specimen details', 'error');
      if (tbody) {
        tbody.innerHTML = '<tr><td colspan="7" class="muted" style="text-align:center;padding:24px;color:#ef4444">Error loading record details.</td></tr>';
      }
    }
  };

  const closeDetailModal = () => {
    const modal = $('#summary-detail-modal-backdrop');
    if (modal) modal.classList.add('hidden');
  };

  // Event Listeners & Setup
  const setupEvents = () => {
    // Suite Switcher Pills
    $$('.summary-suite-pill').forEach((btn) => {
      btn.addEventListener('click', () => {
        $$('.summary-suite-pill').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        state.suite = btn.dataset.suite || 'all';
        loadSummaryData();
      });
    });

    // Category Dropdown Filter
    const catSelect = $('#summary-filter-category');
    if (catSelect) {
      catSelect.addEventListener('change', () => {
        state.category = catSelect.value;
        loadSummaryData();
      });
    }

    // Outcome Dropdown Filter
    const outcomeSelect = $('#summary-filter-outcome');
    if (outcomeSelect) {
      outcomeSelect.addEventListener('change', () => {
        state.outcome = outcomeSelect.value;
        loadSummaryData();
      });
    }

    // Status Dropdown Filter
    const statusSelect = $('#summary-filter-status');
    if (statusSelect) {
      statusSelect.addEventListener('change', () => {
        state.status = statusSelect.value;
        loadSummaryData();
      });
    }

    // Date Range Filters
    const fromInput = $('#summary-filter-from');
    const toInput = $('#summary-filter-to');
    if (fromInput) {
      fromInput.addEventListener('change', () => {
        state.from_date = fromInput.value;
        loadSummaryData();
      });
    }
    if (toInput) {
      toInput.addEventListener('change', () => {
        state.to_date = toInput.value;
        loadSummaryData();
      });
    }

    // Search Input with Debounce
    const searchInput = $('#summary-search');
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
          state.q = searchInput.value.trim();
          loadSummaryData();
        }, 300);
      });
    }

    // Reset Filters Button
    const resetBtn = $('#btn-summary-reset');
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        state.suite = 'all';
        state.category = 'all';
        state.outcome = 'all';
        state.status = 'all';
        state.q = '';
        state.from_date = '';
        state.to_date = '';

        if (catSelect) catSelect.value = 'all';
        if (outcomeSelect) outcomeSelect.value = 'all';
        if (statusSelect) statusSelect.value = 'all';
        if (fromInput) fromInput.value = '';
        if (toInput) toInput.value = '';
        if (searchInput) searchInput.value = '';

        $$('.summary-suite-pill').forEach((b) => b.classList.remove('active'));
        const allPill = $('.summary-suite-pill[data-suite="all"]');
        if (allPill) allPill.classList.add('active');

        loadSummaryData();
      });
    }

    // Export CSV Button
    const exportBtn = $('#btn-export-summary-csv');
    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        const qs = buildQueryString();
        window.location.href = `${BASE_URL}wst/export-summary${qs ? '?' + qs : ''}`;
      });
    }

    // Print Summary Button
    const printBtn = $('#btn-print-summary');
    if (printBtn) {
      printBtn.addEventListener('click', () => {
        window.print();
      });
    }

    // Table Delegated Click: View Details & Copy Lab Reference
    const tableWrap = $('#summary-table');
    if (tableWrap) {
      tableWrap.addEventListener('click', (e) => {
        const detailBtn = e.target.closest('.btn-view-summary-detail');
        if (detailBtn) {
          const testId = Number(detailBtn.dataset.id);
          if (testId) openDetailModal(testId);
          return;
        }

        const copyBtn = e.target.closest('.btn-copy-labref');
        if (copyBtn) {
          const ref = copyBtn.dataset.ref || '';
          if (ref) {
            navigator.clipboard.writeText(ref).then(() => {
              showToast(`Copied ${ref} to clipboard`, 'success');
            }).catch(() => {
              showToast(ref, 'info');
            });
          }
          return;
        }
      });
    }

    // Modal Close Triggers
    const closeBtn = $('#btn-close-summary-modal');
    const closeFooterBtn = $('#btn-close-summary-modal-footer');
    const modalBackdrop = $('#summary-detail-modal-backdrop');

    if (closeBtn) closeBtn.addEventListener('click', closeDetailModal);
    if (closeFooterBtn) closeFooterBtn.addEventListener('click', closeDetailModal);
    if (modalBackdrop) {
      modalBackdrop.addEventListener('click', (e) => {
        if (e.target === modalBackdrop) closeDetailModal();
      });
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modalBackdrop && !modalBackdrop.classList.contains('hidden')) {
        closeDetailModal();
      }
    });
  };

  // Initialize
  const init = () => {
    setupEvents();
    loadSummaryData();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
