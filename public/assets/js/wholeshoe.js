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

  const formatCountdown = (totalSec) => {
    const s = Math.max(0, Math.floor(totalSec || 0));
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (n) => String(n).padStart(2, '0');
    if (d > 0) {
      return `${d}d ${pad(h)}:${pad(m)}:${pad(sec)}`;
    }
    return `${pad(h)}:${pad(m)}:${pad(sec)}`;
  };

  const buildProgressCircle = (pct, color, size = 26, tooltipText = '') => {
    const r = (size / 2) - 3;
    const circ = 2 * Math.PI * r;
    const clamped = Math.max(0, Math.min(100, Math.round(pct)));
    const offset = circ - (clamped / 100) * circ;
    return `
      <div class="wst-progress-circle-wrap" style="width:${size}px;height:${size}px">
        <svg class="wst-progress-circle-svg" viewBox="0 0 ${size} ${size}" style="width:${size}px;height:${size}px">
          <circle class="wst-progress-circle-bg" cx="${size/2}" cy="${size/2}" r="${r}"></circle>
          <circle class="wst-progress-circle-bar" cx="${size/2}" cy="${size/2}" r="${r}"
            stroke="${color}"
            stroke-dasharray="${circ.toFixed(2)}"
            stroke-dashoffset="${offset.toFixed(2)}"></circle>
        </svg>
        <span class="wst-progress-circle-pct" style="font-size:${size <= 28 ? '8px' : '10px'}">${clamped}%</span>
        ${tooltipText ? `<div class="wst-circle-tooltip mono">${tooltipText}</div>` : ''}
      </div>
    `;
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

  let currentWstCategory = $('.dtr-subtab[data-wst-cat].active, .wst-cat-tab.active')?.dataset?.wstCat || 'hydrolysis';
  let currentWstTestId = null;
  let activeWstGroup = 'dry';

  const getWstUnit = (cat = currentWstCategory) => {
    if (cat === 'hydrolysis' || cat === 'heat-aging' || cat === 'uv-light') return 'Hrs';
    if (cat === 'washing') return 'Washes';
    return 'Cycles';
  };

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

  // Filter state for Whole Shoe list
  const wstFilters = {
    suite: 'wholeshoe',
    category: currentWstCategory || 'hydrolysis',
    outcome: 'all',
    status: 'all',
    q: '',
    from_date: '',
    to_date: '',
  };

  // Sound chime helper
  const playFinishChime = () => {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const now = ctx.currentTime;
      const playTone = (freq, start, duration) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, start);
        gain.gain.setValueAtTime(0, start);
        gain.gain.linearRampToValueAtTime(0.25, start + 0.03);
        gain.gain.exponentialRampToValueAtTime(0.001, start + duration);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(start);
        osc.stop(start + duration);
      };
      playTone(523.25, now, 0.15); // C5
      playTone(659.25, now + 0.12, 0.15); // E5
      playTone(783.99, now + 0.24, 0.35); // G5
    } catch (_) {}
  };

  // Prompts technician with notification banner/modal when a test completes
  const promptTestFinished = (testId, testLabel, labRef) => {
    playFinishChime();
    showToast(`🔔 Test Finished: ${testLabel || 'Test'} (${labRef || ''}) is complete! Please input findings.`, 'success');

    // Also trigger Browser Notification API if permitted
    try {
      if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        new Notification('Whole Shoe Test Finished!', {
          body: `${testLabel || 'Whole Shoe Test'} (${labRef || ''}) has completed its duration. Click to input findings.`,
          icon: '/favicon.ico'
        });
      } else if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
        Notification.requestPermission();
      }
    } catch (_) {}

    // Check if prompt dialog already exists, or create an interactive prompt modal
    let promptEl = $('#wst-finish-prompt-toast');
    if (!promptEl) {
      promptEl = document.createElement('div');
      promptEl.id = 'wst-finish-prompt-toast';
      promptEl.style.cssText = 'position:fixed;top:28px;right:28px;z-index:99999;background:#ffffff;border:2px solid #10b981;border-radius:12px;box-shadow:0 12px 32px rgba(16,185,129,0.3);padding:18px 22px;max-width:400px;display:flex;flex-direction:column;gap:10px;animation:slideInRight 0.35s cubic-bezier(0.16,1,0.3,1);font-family:inherit;';
      document.body.appendChild(promptEl);
    }

    promptEl.innerHTML = `
      <div style="display:flex;align-items:flex-start;gap:12px">
        <div style="width:38px;height:38px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#10b981">
          <svg style="width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        </div>
        <div style="flex:1">
          <div style="font-weight:700;color:#0f172a;font-size:15px;line-height:1.2">Test Timer Finished!</div>
          <div style="font-size:13px;color:#475569;margin-top:4px">
            <b>${esc(testLabel || 'Test')}</b> (${esc(labRef || '')}) has completed its required duration.
          </div>
        </div>
        <button type="button" id="btn-close-finish-prompt" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:18px;line-height:1">&times;</button>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:4px">
        <button type="button" id="btn-dismiss-finish-prompt" class="dtr-btn-xs dtr-btn-outline" style="border-radius:6px">Later</button>
        <button type="button" id="btn-action-finish-prompt" class="dtr-btn-xs dtr-btn-primary" style="background:#10b981;border-color:#10b981;border-radius:6px;font-weight:700;color:#fff">
          Input Findings Now &rarr;
        </button>
      </div>
    `;

    const closePrompt = () => {
      promptEl.style.opacity = '0';
      promptEl.style.transform = 'translateY(-10px)';
      promptEl.style.transition = 'all 0.3s ease';
      setTimeout(() => promptEl.remove(), 350);
    };

    promptEl.querySelector('#btn-close-finish-prompt')?.addEventListener('click', closePrompt);
    promptEl.querySelector('#btn-dismiss-finish-prompt')?.addEventListener('click', closePrompt);
    promptEl.querySelector('#btn-action-finish-prompt')?.addEventListener('click', () => {
      closePrompt();
      if (testId) {
        openWstMonitorModal(testId).then(() => {
          setTimeout(() => {
            if (activeRecordForEval) {
              const cat = activeRecordForEval.category || currentWstCategory;
              if (cat === 'flexing') {
                openFlexEvaluationModal(activeRecordForEval, null);
              } else {
                openEvaluationModal(activeRecordForEval);
              }
            }
          }, 350);
        });
      }
    });
  };

  // Real-time table countdown ticker for active running tests
  let wstTableTickerInterval = null;
  const startTableTicker = () => {
    if (wstTableTickerInterval) {
      clearInterval(wstTableTickerInterval);
      wstTableTickerInterval = null;
    }
    wstTableTickerInterval = setInterval(() => {
      const activeRows = $$('.wst-row[data-timer-running="true"]');
      if (!activeRows.length) return;

      activeRows.forEach((row) => {
        let remaining = parseInt(row.dataset.timerRemaining || '0', 10);
        let duration = parseInt(row.dataset.timerDuration || '0', 10);
        let elapsed = parseInt(row.dataset.timerElapsed || '0', 10);

        if (remaining > 0) {
          remaining--;
          elapsed++;
          row.dataset.timerRemaining = remaining;
          row.dataset.timerElapsed = elapsed;

          const tipEl = row.querySelector('.wst-circle-tooltip');
          if (tipEl) {
            tipEl.innerHTML = `<span style="color:#60a5fa">${formatCountdown(remaining)} left</span>`;
          }

          const pct = duration > 0 ? Math.min(100, Math.round((elapsed / duration) * 100)) : 0;
          const pctEl = row.querySelector('.wst-progress-circle-pct');
          if (pctEl) {
            pctEl.textContent = `${pct}%`;
          }

          const barEl = row.querySelector('.wst-progress-circle-bar');
          if (barEl) {
            const size = 28;
            const r = (size / 2) - 3;
            const circ = 2 * Math.PI * r;
            const offset = circ - (pct / 100) * circ;
            barEl.style.strokeDashoffset = offset.toFixed(2);
          }

          if (remaining <= 0) {
            row.dataset.timerRunning = 'false';
            const tipEl = row.querySelector('.wst-circle-tooltip');
            if (tipEl) {
              tipEl.innerHTML = '<span style="color:#34d399">Finished</span>';
            }
            const barEl = row.querySelector('.wst-progress-circle-bar');
            if (barEl) barEl.setAttribute('stroke', '#10b981');
          }
        }
      });
    }, 1000);
  };

  // Fetch list of records from dbnexus
  const loadWstRecords = async () => {
    const tbody = $('#wst-list-tbody');
    const cntBadge = $('#wst-record-count');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="8" class="muted" style="text-align:center;padding:24px"><svg class="ic animate-spin" aria-hidden="true" style="margin-right:6px"><use href="#i-sync"/></svg> Loading test sessions from dbnexus...</td></tr>';
    try {
      const params = new URLSearchParams();
      params.set('suite', 'wholeshoe');
      if (wstFilters.category) params.set('category', wstFilters.category);
      if (wstFilters.outcome && wstFilters.outcome !== 'all') params.set('outcome', wstFilters.outcome);
      if (wstFilters.status && wstFilters.status !== 'all') params.set('status', wstFilters.status);
      if (wstFilters.q) params.set('q', wstFilters.q);
      if (wstFilters.from_date) params.set('from_date', wstFilters.from_date);
      if (wstFilters.to_date) params.set('to_date', wstFilters.to_date);

      const res = await apiFetch(`wst/records?${params.toString()}`);
      if (!res.success || !res.records || res.records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="muted" style="text-align:center;padding:28px">No test sessions match the current filter criteria.</td></tr>';
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
      const thProg = $('#wst-th-progress');
      if (thProg) {
        thProg.textContent = (currentWstCategory === 'flexing') ? 'CYCLES / PROGRESS' : 'TIME / STATUS';
      }
      tbody.innerHTML = res.records.map((r, i) => {
        const cat = r.category || currentWstCategory;
        const catCfg = TIMED_CATEGORIES[cat] || { label: 'Whole Shoe', nominal: 'Standard', durationSec: 86400 };
        const timer = r.timer || {};
        const isTimerRunning = Boolean(timer.is_running);
        const isTimerDone = Boolean(timer.is_finished);
        const isTimerPaused = Boolean(timer.is_paused) || r.status === 'PAUSED';

        const isRun = isTimerRunning || r.status === 'RUNNING';
        const isDone = r.status === 'COMPLETED' || isTimerDone;

        // Accurate Status Label & Distinct Color Pill Class
        let pillClass = 'wst-status-pending';
        let statusLabel = 'PENDING';

        if (r.outcome === 'FAIL') {
          pillClass = 'wst-status-fail';
          statusLabel = 'FAIL';
        } else if (r.outcome === 'TOLERABLE') {
          pillClass = 'wst-status-tolerable';
          statusLabel = 'TOLERABLE';
        } else if (r.outcome === 'PASS') {
          pillClass = 'wst-status-completed';
          statusLabel = 'PASS';
        } else if (r.outcome === 'STOPPED' || r.status === 'STOPPED') {
          pillClass = 'wst-status-stopped';
          statusLabel = 'STOPPED';
        } else if (isDone) {
          pillClass = 'wst-status-completed';
          statusLabel = 'COMPLETED';
        } else if (isRun) {
          pillClass = 'wst-status-running';
          statusLabel = 'RUNNING';
        } else if (isTimerPaused) {
          pillClass = 'wst-status-paused';
          statusLabel = 'PAUSED';
        } else {
          pillClass = 'wst-status-pending';
          statusLabel = 'PENDING';
        }

        const isFlex = (cat === 'flexing');
        let progPct = 0;
        let progText = '';
        let timerDuration = timer.duration_sec || catCfg.durationSec || 0;
        let timerElapsed = timer.elapsed_sec || 0;
        let timerRemaining = Math.max(0, timer.remaining_sec != null ? timer.remaining_sec : (timerDuration - timerElapsed));

        if (isFlex) {
          const targetC = Number(r.target_cycles) || 125000;
          const currC = Number(r.current_cycles) || 0;
          progPct = Math.min(100, Math.round((currC / targetC) * 100));

          if (isDone || r.outcome === 'PASS' || r.outcome === 'PASSED') {
            progText = `<span style="color:#10b981;font-weight:700">Completed (125k)</span>`;
          } else if (isRun) {
            progText = `<span style="color:#2563eb;font-weight:700">${currC.toLocaleString()} / ${(targetC/1000)}k cycles</span>`;
          } else if (isTimerPaused) {
            progText = `<span style="color:#d97706;font-weight:700">Paused (${currC.toLocaleString()})</span>`;
          } else {
            progText = `<span style="color:#64748b">0 / 125k cycles</span>`;
          }
        } else {
          progPct = timerDuration > 0 ? Math.min(100, Math.round((timerElapsed / timerDuration) * 100)) : (isDone ? 100 : 0);

          if (isDone || isTimerDone) {
            progText = '<span style="color:#10b981;font-weight:700">Finished</span>';
          } else if (isRun) {
            progText = `<span style="color:#2563eb;font-weight:700">${formatCountdown(timerRemaining)} left</span>`;
          } else if (isTimerPaused) {
            progText = `<span style="color:#d97706;font-weight:700">Paused (${formatCountdown(timerRemaining)})</span>`;
          } else {
            progText = `<span style="color:#64748b">${catCfg.nominal}</span>`;
          }
        }

        let circleColor = '#3b82f6'; // running / default blue
        if (r.outcome === 'FAIL' || r.outcome === 'FAILED') {
          circleColor = '#ef4444'; // red
        } else if (isDone || isTimerDone || r.outcome === 'PASS' || r.outcome === 'PASSED') {
          circleColor = '#10b981'; // green
        } else if (isTimerPaused) {
          circleColor = '#f59e0b'; // amber
        }

        const timerPct = progPct;
        const timerText = progText;

        const isRealtimeRunning = (!isFlex && isRun && !isDone && !isTimerDone && !isTimerPaused && timerRemaining > 0);
        return `
          <tr class="clickable-row wst-row" data-id="${r.id}"
              data-timer-running="${isRealtimeRunning ? 'true' : 'false'}"
              data-timer-remaining="${timerRemaining || 0}"
              data-timer-elapsed="${timerElapsed || 0}"
              data-timer-duration="${timerDuration || 0}"
              style="cursor:pointer">
            <td class="mono">${i + 1}</td>
            <td><b class="mono" style="color:var(--brand,#2563eb);font-size:13.5px">${esc(r.labreference)}</b></td>
            <td>${esc(r.testdate || '—')}</td>
            <td><span style="font-weight:600">${esc(r.type || catCfg.label)}</span></td>
            <td><span class="mono sm muted">${esc(r.machine_number || 'MC-01')} &bull; ${esc(r.technician || r.operator || 'QC')}</span></td>
            <td>
              ${isFlex ? `
                <div style="min-width:130px;max-width:170px">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;font-size:11px">
                    <span class="mono bold" style="color:#334155">${progText}</span>
                    <span class="mono bold" style="color:var(--brand,#2563eb);font-size:10.5px">${progPct}%</span>
                  </div>
                  <div class="wst-progress-rail" style="height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden">
                    <div class="wst-progress-fill" style="width:${progPct}%;height:100%;background:${circleColor};transition:width 0.3s ease"></div>
                  </div>
                </div>
              ` : `
                <div style="display:flex;align-items:center;gap:10px">
                  ${buildProgressCircle(timerPct, circleColor, 28, timerText)}
                </div>
              `}
            </td>
            <td><span class="wst-status-pill ${pillClass}">${statusLabel}</span></td>
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

      startTableTicker();
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
    // Filters reset silently
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
      const thProg = $('#wst-th-progress');
      if (thProg) {
        thProg.textContent = (currentWstCategory === 'flexing') ? 'CYCLES / PROGRESS' : 'TIME / STATUS';
      }
      refreshNextLabref();
      loadWstRecords();
      // Category tab switched silently
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

        const defaultMachines = {
          'hydrolysis': 'CH-HYDRO-01',
          'heat-aging': 'OVEN-01',
          'washing': 'WASH-MC-01',
          'uv-light': 'UV-CHAMBER-01',
          'flexing': 'MC-FLEX-01',
          'bally-flex': 'MC-FLEX-01',
          'deeptest': 'MC-DEEP-01',
          'deep-test': 'MC-DEEP-01',
        };
        if ($('#wst-new-machine') && defaultMachines[currentWstCategory]) {
          $('#wst-new-machine').value = defaultMachines[currentWstCategory];
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

  let currentFlexOutcome = 'PASSED';

  const updateFlexFindingsFormula = () => {
    const cycle = $('#wst-flex-eval-cycle')?.value || '125000';
    const latLen = parseFloat($('#wst-flex-lat-len')?.value || '0') || 0;
    const medLen = parseFloat($('#wst-flex-med-len')?.value || '0') || 0;
    const latDepth = parseFloat($('#wst-flex-lat-depth')?.value || '0') || 0;
    const medDepth = parseFloat($('#wst-flex-med-depth')?.value || '0') || 0;

    const preview = $('#wst-flex-findings-preview');
    if (preview) {
      preview.value = `45° degrees ${cycle} cycles - (L: ${latLen} mm, M: ${medLen} mm; D:${latDepth} mm, D:${medDepth} mm)`;
    }
  };

  const openFlexEvaluationModal = (record, targetCycle = null) => {
    activeRecordForEval = record;
    const modal = $('#wst-flex-eval-modal-backdrop');
    if (!modal) return;

    const cycles = [5000, 10000, 15000, 25000, 35000, 45000, 55000, 65000, 75000, 85000, 95000, 105000, 115000, 125000];

    // Compute recorded cycles to automatically find the next pending checkpoint
    const recordedMap = currentWstState?.recorded?.['dry'] || currentWstState?.recorded?.['flexing'] || {};
    const recordedCycles = Object.values(recordedMap).flat().map(Number);
    const maxRec = recordedCycles.length > 0 ? Math.max(...recordedCycles) : (Number(record.current_cycles) || 0);

    // Auto-iterate to next unrecorded cycle checkpoint
    let autoNextCycle = cycles[0];
    for (const c of cycles) {
      if (!recordedCycles.includes(c) && c > maxRec) {
        autoNextCycle = c;
        break;
      }
    }
    // If all completed or none found, fallback to targetCycle or final cycle
    const effectiveCycle = targetCycle !== null ? targetCycle : autoNextCycle;

    const title = $('#wst-flex-eval-title');
    if (title) title.textContent = `Whole Shoe Flexing Test Result (${record.labreference || ''})`;

    if ($('#wst-flex-eval-cycle')) {
      $('#wst-flex-eval-cycle').value = String(effectiveCycle);
    }
    if ($('#wst-flex-eval-cycle-display')) {
      $('#wst-flex-eval-cycle-display').textContent = `${Number(effectiveCycle).toLocaleString()} Cycles`;
    }
    const autoBadge = $('#wst-flex-auto-badge');
    if (autoBadge) {
      const isAuto = (effectiveCycle === autoNextCycle);
      autoBadge.textContent = isAuto ? 'Active Checkpoint (Auto-Detected)' : 'Checkpoint Observation';
      autoBadge.className = `dtr-pill ${isAuto ? 'dtr-pill-blue' : 'dtr-pill-warn'}`;
    }

    // Populate existing findings if already saved
    const fJson = record.findings_json || {};
    if ($('#wst-flex-lat-len')) $('#wst-flex-lat-len').value = fJson.lat_len ?? 0;
    if ($('#wst-flex-med-len')) $('#wst-flex-med-len').value = fJson.med_len ?? 0;
    if ($('#wst-flex-lat-depth')) $('#wst-flex-lat-depth').value = fJson.lat_depth ?? 0;
    if ($('#wst-flex-med-depth')) $('#wst-flex-med-depth').value = fJson.med_depth ?? 0;

    const rawOutcome = (record.overall_outcome || fJson.outcome || 'PASSED').toUpperCase();
    currentFlexOutcome = (rawOutcome === 'PASS' || rawOutcome === 'PASSED' || rawOutcome === 'RUNNING') ? 'PASSED' : ((rawOutcome === 'FAIL' || rawOutcome === 'FAILED') ? 'FAILED' : 'PENDING');
    $('.wst-flex-result-btn').forEach((btn) => {
      const match = btn.dataset.flexResult.toUpperCase() === currentFlexOutcome;
      btn.classList.toggle('active', match);
      btn.style.background = match ? (currentFlexOutcome === 'PASSED' ? '#2563eb' : (currentFlexOutcome === 'FAILED' ? '#ef4444' : '#64748b')) : '#ffffff';
      btn.style.color = match ? '#ffffff' : '#475569';
      btn.style.borderColor = match ? (currentFlexOutcome === 'PASSED' ? '#2563eb' : (currentFlexOutcome === 'FAILED' ? '#ef4444' : '#64748b')) : '#cbd5e1';
    });

    updateFlexFindingsFormula();
    modal.classList.remove('hidden');
  };

  const closeFlexEvaluationModal = () => {
    $('#wst-flex-eval-modal-backdrop')?.classList.add('hidden');
  };

  const renderFlexingCycleTable = (record, groups, state) => {
    const flexContainer = $('#wst-flex-container');
    const tableWrap = $('#wst-flex-cycle-table');
    if (!flexContainer || !tableWrap) return;

    const cycles = [5000, 10000, 15000, 25000, 35000, 45000, 55000, 65000, 75000, 85000, 95000, 105000, 115000, 125000];
    const recordedMap = state?.recorded?.['dry'] || state?.recorded?.['flexing'] || {};
    const recordedCycles = Object.values(recordedMap).flat().map(Number);
    const maxRec = recordedCycles.length > 0 ? Math.max(...recordedCycles) : (Number(record.current_cycles) || 0);

    const targetCycles = 125000;
    const pct = Math.min(100, Math.round((maxRec / targetCycles) * 100));

    if ($('#wst-flex-curr-cycles')) $('#wst-flex-curr-cycles').textContent = maxRec.toLocaleString();
    if ($('#wst-flex-target-cycles')) $('#wst-flex-target-cycles').textContent = targetCycles.toLocaleString();
    if ($('#wst-flex-pct-label')) $('#wst-flex-pct-label').textContent = `${pct}%`;
    if ($('#wst-flex-progress-bar')) $('#wst-flex-progress-bar').style.width = `${pct}%`;

    const statePill = $('#wst-flex-state-pill');
    if (statePill) {
      if (record.status === 'COMPLETED' || record.overall_outcome === 'PASS' || record.overall_outcome === 'PASSED') {
        statePill.className = 'wst-status-pill wst-status-completed';
        statePill.textContent = 'COMPLETED';
      } else if (record.status === 'RUNNING' || record.is_running == 1) {
        statePill.className = 'wst-status-pill wst-status-running';
        statePill.textContent = 'RUNNING';
      } else if (record.status === 'PAUSED') {
        statePill.className = 'wst-status-pill wst-status-paused';
        statePill.textContent = 'PAUSED';
      } else {
        statePill.className = 'wst-status-pill wst-status-pending';
        statePill.textContent = 'PENDING';
      }
    }

    // Find next unrecorded cycle
    let nextUnrecorded = cycles[0];
    for (const c of cycles) {
      if (!recordedCycles.includes(c)) {
        nextUnrecorded = c;
        break;
      }
    }

    let thead = `<tr><th style="min-width:120px;padding:8px 12px;background:#f8fafc">SPECIMEN</th>`;
    cycles.forEach((c) => {
      const isTarget = (c === nextUnrecorded && record.status !== 'COMPLETED');
      thead += `<th style="padding:8px 10px;text-align:center;background:${isTarget ? '#eff6ff' : '#f8fafc'};color:${isTarget ? '#1d4ed8' : '#475569'};font-size:11.5px;font-weight:700;border-top:${isTarget ? '2px solid #3b82f6' : 'none'}">${(c/1000)}k</th>`;
    });
    thead += `</tr>`;

    let tbody = `<tr><td class="mono bold" style="padding:10px 12px;font-size:12.5px;color:#0f172a">1-Whole Shoe</td>`;
    cycles.forEach((c) => {
      const isRecorded = recordedCycles.includes(c) || (maxRec >= c);
      const isTarget = (c === nextUnrecorded && record.status !== 'COMPLETED');

      let bg = '#ffffff';
      let icon = '';
      let cursor = 'pointer';
      let title = `Log findings for ${c.toLocaleString()} cycles`;

      if (isRecorded) {
        bg = '#ecfdf5';
        icon = '<span style="color:#059669;font-weight:800;font-size:12px">&#10003;</span>';
        title = `Recorded at ${c.toLocaleString()} cycles`;
      } else if (isTarget) {
        bg = '#eff6ff';
        icon = '<span style="color:#2563eb;font-weight:700;font-size:10px">&#9679;</span>';
      }

      tbody += `<td class="wst-flex-cell" data-cycle="${c}" title="${title}" style="text-align:center;padding:10px 4px;background:${bg};border:1px solid #e2e8f0;cursor:${cursor}">${icon}</td>`;
    });
    tbody += `</tr>`;

    tableWrap.innerHTML = `<thead>${thead}</thead><tbody>${tbody}</tbody>`;

    tableWrap.querySelectorAll('.wst-flex-cell').forEach((cell) => {
      cell.addEventListener('click', () => {
        const c = parseInt(cell.dataset.cycle, 10);
        openFlexEvaluationModal(record, c);
      });
    });
  };

  // Live monitor modal renderer
  const openWstMonitorModal = async (testId) => {
    currentWstTestId = testId;
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

      if ($('#wst-view-op')) $('#wst-view-op').textContent = r.technician || r.operator || 'QC Specialist';
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
      const isTimerPausedModal = Boolean(res.timer?.is_paused) || r.status === 'PAUSED';
      if (badge) {
        let statusStr = isDone ? 'COMPLETED' : (isRun ? 'RUNNING' : (isTimerPausedModal ? 'PAUSED' : (r.status || 'PENDING')));
        if (badgeText) badgeText.textContent = statusStr;
        else badge.textContent = statusStr;
        
        let bClass = 'wst-status-pending';
        if (r.overall_outcome === 'FAIL') bClass = 'wst-status-fail';
        else if (isDone) bClass = 'wst-status-completed';
        else if (isRun) bClass = 'wst-status-running';
        else if (isTimerPausedModal) bClass = 'wst-status-paused';

        badge.className = `wst-status-pill ${bClass}`;
      }
      if (pulseDot) {
        pulseDot.className = `wst-pulse-dot ${isRun ? 'wst-pulse-dot-green' : (isDone ? 'wst-pulse-dot-blue' : (isTimerPausedModal ? 'wst-pulse-dot-amber' : ''))}`;
      }

      // State-Aware Action Rail: Run / Pause / Stop / Input Findings
      const startBtn = $('#btn-wst-modal-start');
      const pauseBtn = $('#btn-wst-modal-pause');
      const runningBar = $('#wst-running-indicator-bar');
      const stopTestBtn = $('#btn-wst-modal-stop');
      const evalBtn = $('#btn-wst-open-eval-modal');

      const isFlexCategory = (r.category === 'flexing');
      const isTimerRunning = Boolean(res.timer?.is_running) || (r.status === 'RUNNING');
      const isTimerPaused = Boolean(res.timer?.is_paused) || (r.status === 'PAUSED');
      const isTimerDone = Boolean(res.timer?.is_finished);

      if (isDone || isTimerDone) {
        startBtn?.classList.remove('hidden');
        runningBar?.classList.add('hidden');
        if (startBtn) {
          startBtn.disabled = true;
          startBtn.style.opacity = '0.45';
          startBtn.style.cursor = 'not-allowed';
          startBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> RUN / START';
        }
        pauseBtn?.classList.add('hidden');
        if (stopTestBtn) {
          stopTestBtn.disabled = true;
          stopTestBtn.style.opacity = '0.45';
          stopTestBtn.style.cursor = 'not-allowed';
        }
        evalBtn?.classList.remove('hidden');
      } else if (isTimerPaused) {
        startBtn?.classList.remove('hidden');
        runningBar?.classList.add('hidden');
        if (startBtn) {
          startBtn.disabled = false;
          startBtn.style.opacity = '1';
          startBtn.style.cursor = 'pointer';
          startBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> RESUME / START';
        }
        pauseBtn?.classList.add('hidden');
        if (stopTestBtn) {
          stopTestBtn.disabled = false;
          stopTestBtn.style.opacity = '1';
          stopTestBtn.style.cursor = 'pointer';
        }
        evalBtn?.classList.remove('hidden');
      } else if (isTimerRunning) {
        startBtn?.classList.add('hidden');
        if (isFlexCategory) {
          pauseBtn?.classList.add('hidden');
          runningBar?.classList.remove('hidden');
          evalBtn?.classList.remove('hidden');
        } else {
          runningBar?.classList.add('hidden');
          pauseBtn?.classList.remove('hidden');
          if (pauseBtn) {
            pauseBtn.innerHTML = '<svg class="ic" aria-hidden="true" style="width:15px;height:15px;margin-right:4px"><rect x="6" y="4" width="4" height="16" fill="currentColor"/><rect x="14" y="4" width="4" height="16" fill="currentColor"/></svg> PAUSE TIMER';
          }
          evalBtn?.classList.add('hidden');
        }
        if (stopTestBtn) {
          stopTestBtn.disabled = false;
          stopTestBtn.style.opacity = '1';
          stopTestBtn.style.cursor = 'pointer';
        }
      } else {
        startBtn?.classList.remove('hidden');
        runningBar?.classList.add('hidden');
        if (startBtn) {
          startBtn.disabled = false;
          startBtn.style.opacity = '1';
          startBtn.style.cursor = 'pointer';
          startBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> RUN / START';
        }
        pauseBtn?.classList.add('hidden');
        if (stopTestBtn) {
          stopTestBtn.disabled = false;
          stopTestBtn.style.opacity = '1';
          stopTestBtn.style.cursor = 'pointer';
        }
        if (isFlexCategory) {
          evalBtn?.classList.remove('hidden');
        } else {
          evalBtn?.classList.add('hidden');
        }
      }

      activeRecordForEval = r;

      const timerContainer = $('#wst-timer-container');
      const flexContainer = $('#wst-flex-container');

      if (isFlexCategory) {
        timerContainer?.classList.add('hidden');
        flexContainer?.classList.remove('hidden');
        pauseBtn?.classList.add('hidden'); // No timer pause for cyclic flexing
        if (evalBtn) {
          evalBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Input Flexing Result';
        }
        renderFlexingCycleTable(r, res.groups, res.state);
      } else {
        timerContainer?.classList.remove('hidden');
        flexContainer?.classList.add('hidden');
        if (evalBtn) {
          evalBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Input Findings &amp; Status';
        }
        if (res.timer) {
          startCountdownTicker(res.timer, r);
        }
      }

      // Monitor opened silently
    } catch (err) {
      showToast(`Error opening monitor: ${err.message}`, 'error');
    }
  };

  const closeWstMonitorModal = () => {
    if (wstTimerInterval) {
      clearInterval(wstTimerInterval);
      wstTimerInterval = null;
    }
    $('#wst-monitor-modal-backdrop')?.classList.add('hidden');
    loadWstRecords();
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
        return `<option value="${c}">${Number(c).toLocaleString()} ${getWstUnit()}</option>`;
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
        let cellClass = 'cycle-cell';

        if (isRecorded) {
          cellClass += ' is-recorded';
        } else if (isStopped) {
          cellClass += ' is-stopped';
        }

        html += `<td class="${cellClass}" data-group="${activeWstGroup}" data-sample="${s}" data-cycle="${c}"></td>`;
      });
      html += `</tr>`;
    }

    html += `</tbody></table>`;
    tableWrap.innerHTML = html;
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
        showToast('Test session started successfully.', 'success');
        await openWstMonitorModal(currentWstTestId);
        loadWstRecords(currentWstCategory);
      } else {
        showToast(res.message || 'Could not start test.', 'error');
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
        await openWstMonitorModal(currentWstTestId);
        showToast(res.message || 'Test stopped. Please input your findings and final status.', 'info');
        if (activeRecordForEval) {
          if (activeRecordForEval.category === 'flexing') {
            openFlexEvaluationModal(activeRecordForEval, null);
          } else {
            openEvaluationModal(activeRecordForEval);
          }
        }
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
    const dry = 1;
    const wet = 0;
    const hydro = 0;

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
    // Queue refreshed silently
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

          if (currentWstCategory === 'flexing') { if (activeRecordForEval) renderFlexingCycleTable(activeRecordForEval, currentWstGroups, currentWstState); } else { renderMonitoringTable(currentWstGroups, currentWstState); }
        }
      } catch (_) {}
    }, 8000);
  };
  startWstLivePolling();

  // Trigger initial list load on page load
  
  // =========================================================================
  // WHOLE SHOE TEST: INCUBATION TIMERS & FINDINGS EVALUATION
  // Hydrolysis: 7 Days | Heat Aging: 3 Days | UV Light: 1 Day | Washing: 1 Hr | Dip Test: 1 Min
  // =========================================================================
  const TIMED_CATEGORIES = {
    'hydrolysis': { label: 'Hydrolysis', duration: 7 * 86400, nominal: '7 Days' },
    'heat-aging': { label: 'Heat Aging', duration: 3 * 86400, nominal: '3 Days' },
    'uv-light':   { label: 'UV Light', duration: 1 * 86400, nominal: '1 Day (24 Hrs)' },
    'washing':    { label: 'Washing', duration: 1 * 3600, nominal: '1 Hour' },
    'deeptest':   { label: 'Dip Test', duration: 60, nominal: '1 Minute' },
    'deep-test':  { label: 'Dip Test', duration: 60, nominal: '1 Minute' },
  };

  const isTimedCategory = (cat) => !!TIMED_CATEGORIES[cat];


  let wstTimerInterval = null;

  const startCountdownTicker = (initialTimer, record) => {
    if (wstTimerInterval) {
      clearInterval(wstTimerInterval);
      wstTimerInterval = null;
    }

    const timerBox = $('#wst-timer-container');
    const clock = $('#wst-countdown-clock');
    const subtext = $('#wst-countdown-subtext');
    const statePill = $('#wst-timer-state-pill');
    const pBar = $('#wst-timer-progress-bar');
    const pLabel = $('#wst-timer-pct-label');
    const elLabel = $('#wst-timer-elapsed-label');
    const finishedAlert = $('#wst-timer-finished-alert');
    const pausedAlert = $('#wst-timer-paused-alert');
    const typeBadge = $('#wst-timer-type-badge');
    const nominalDuration = $('#wst-timer-nominal-duration');
    const pauseBtn = $('#btn-wst-modal-pause');
    const startBtn = $('#btn-wst-modal-start');
    const evalBtn = $('#btn-wst-open-eval-modal');

    const cat = record.category || currentWstCategory;
    const isTimed = isTimedCategory(cat);

    if (!timerBox) return;

    if (!isTimed) {
      timerBox.classList.add('hidden');
      pauseBtn?.classList.add('hidden');
      return;
    }

    timerBox.classList.remove('hidden');
    const catCfg = TIMED_CATEGORIES[cat];
    if (typeBadge) typeBadge.textContent = `${catCfg.label.toUpperCase()} TIMER`;
    if (nominalDuration) nominalDuration.textContent = `Nominal Duration: ${catCfg.nominal}`;

    const duration = Number(initialTimer.duration_sec || catCfg.duration);
    let elapsed = Number(initialTimer.elapsed_sec || 0);
    let isRunning = !!initialTimer.is_running;
    let isFinished = !!initialTimer.is_finished || (record.status === 'COMPLETED');
    let isPaused = !!initialTimer.is_paused;

    const renderTick = () => {
      const remaining = Math.max(0, duration - elapsed);
      const pct = duration > 0 ? Math.min(100, Math.round((elapsed / duration) * 100)) : 0;

      if (clock) clock.textContent = formatCountdown(remaining);
      if (pBar) pBar.style.width = `${pct}%`;
      if (pLabel) pLabel.textContent = `${pct}%`;
      if (elLabel) elLabel.textContent = `Elapsed: ${formatCountdown(elapsed)}`;

      if (isFinished || remaining <= 0) {
        if (statePill) {
          statePill.textContent = 'FINISHED';
          statePill.style.background = '#dcfce7';
          statePill.style.color = '#15803d';
        }
        if (subtext) subtext.textContent = 'Required test duration elapsed. Ready for evaluation.';
        finishedAlert?.classList.remove('hidden');
        pausedAlert?.classList.add('hidden');
        pauseBtn?.classList.add('hidden');
        evalBtn?.classList.remove('hidden');
        if (startBtn) {
          startBtn.classList.remove('hidden');
          startBtn.disabled = true;
          startBtn.style.opacity = '0.5';
          startBtn.style.cursor = 'not-allowed';
        }
        if (wstTimerInterval) {
          clearInterval(wstTimerInterval);
          wstTimerInterval = null;
        }
      } else if (isPaused) {
        if (statePill) {
          statePill.textContent = 'PAUSED';
          statePill.style.background = '#fef3c7';
          statePill.style.color = '#b45309';
        }
        if (subtext) subtext.textContent = 'Timer paused. Press RESUME / START to continue testing, or input findings.';
        finishedAlert?.classList.add('hidden');
        pausedAlert?.classList.remove('hidden');
        pauseBtn?.classList.add('hidden');
        evalBtn?.classList.remove('hidden');
        if (startBtn) {
          startBtn.classList.remove('hidden');
          startBtn.disabled = false;
          startBtn.style.opacity = '1';
          startBtn.style.cursor = 'pointer';
          startBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> RESUME / START';
        }
      } else if (isRunning) {
        if (statePill) {
          statePill.textContent = 'RUNNING';
          statePill.style.background = '#dbeafe';
          statePill.style.color = '#1d4ed8';
        }
        if (subtext) subtext.textContent = `Timer active · Test incubation running (${catCfg.nominal})`;
        finishedAlert?.classList.add('hidden');
        pausedAlert?.classList.add('hidden');
        pauseBtn?.classList.remove('hidden');
        if (pauseBtn) pauseBtn.innerHTML = '<svg class="ic" aria-hidden="true" style="width:15px;height:15px;margin-right:4px"><rect x="6" y="4" width="4" height="16" fill="currentColor"/><rect x="14" y="4" width="4" height="16" fill="currentColor"/></svg> PAUSE TIMER';
        evalBtn?.classList.add('hidden');
      } else {
        if (statePill) {
          statePill.textContent = 'PENDING START';
          statePill.style.background = '#e2e8f0';
          statePill.style.color = '#475569';
        }
        if (subtext) subtext.textContent = `Press RUN / START to begin test timer (${catCfg.nominal})`;
        finishedAlert?.classList.add('hidden');
        pausedAlert?.classList.add('hidden');
        pauseBtn?.classList.add('hidden');
        evalBtn?.classList.add('hidden');
      }
    };

    renderTick();

    if (isRunning && !isFinished && duration > elapsed) {
      wstTimerInterval = setInterval(() => {
        elapsed++;
        renderTick();
        if (elapsed >= duration) {
          isFinished = true;
          isRunning = false;
          renderTick();
          showToast(`Test duration for ${catCfg.label} has completed! Please record findings.`, 'success');
        }
      }, 1000);
    }
  };

  // Evaluation Modal Handlers
  let currentEvalOutcome = 'PASS';
  let activeRecordForEval = null;

  const openEvaluationModal = (record) => {
    activeRecordForEval = record;
    const modal = $('#wst-eval-modal-backdrop');
    if (!modal) return;

    const titleEl = $('#wst-eval-title');
    const cat = record.category || currentWstCategory;
    const catName = TIMED_CATEGORIES[cat]?.label || 'Whole Shoe';
    if (titleEl) titleEl.textContent = `${catName.toUpperCase()} FINDINGS & EVALUATION (${record.labreference || ''})`;

    const endTimeInput = $('#wst-eval-end-time');
    if (endTimeInput) {
      const now = new Date();
      endTimeInput.value = now.toLocaleString('en-US', {
        month: '2-digit', day: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
      });
    }

    // Reset default selections
    $$('.wst-choice-btn').forEach((btn) => {
      const isDefault = btn.dataset.val.toLowerCase().startsWith('no ');
      btn.classList.toggle('active', isDefault);
      btn.style.background = isDefault ? '#2563eb' : '#ffffff';
      btn.style.color = isDefault ? '#ffffff' : '#1e293b';
      btn.style.borderColor = isDefault ? '#2563eb' : '#cbd5e1';
    });

    // Reset outcome buttons
    currentEvalOutcome = (record.overall_outcome || 'PASS').toUpperCase();
    $$('.wst-outcome-btn').forEach((btn) => {
      const match = btn.dataset.outcome === currentEvalOutcome;
      btn.classList.toggle('active', match);
    });

    const otherInp = $('#wst-eval-other-findings');
    if (otherInp) otherInp.value = record.remarks || '';

    modal.classList.remove('hidden');
  };

  const closeEvaluationModal = () => {
    $('#wst-eval-modal-backdrop')?.classList.add('hidden');
  };

  // Choice buttons selection behavior (single-select per group)
  $$('.wst-choice-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const parent = btn.closest('.wst-btn-pill-group');
      if (parent) {
        parent.querySelectorAll('.wst-choice-btn').forEach((b) => {
          b.classList.remove('active');
          b.style.background = '#ffffff';
          b.style.color = '#1e293b';
          b.style.borderColor = '#cbd5e1';
        });
      }
      btn.classList.add('active');
      btn.style.background = '#2563eb';
      btn.style.color = '#ffffff';
      btn.style.borderColor = '#2563eb';
    });
  });

  // Outcome buttons selection
  $$('.wst-outcome-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      $$('.wst-outcome-btn').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      currentEvalOutcome = btn.dataset.outcome;
    });
  });

  // Wire up Evaluation Modal buttons
  $('#btn-wst-close-eval-modal')?.addEventListener('click', closeEvaluationModal);
  $('#btn-wst-cancel-eval-modal')?.addEventListener('click', closeEvaluationModal);
  $('#btn-wst-open-eval-modal')?.addEventListener('click', () => {
    if (currentWstTestId && activeRecordForEval) {
      if (activeRecordForEval.category === 'flexing') {
        openFlexEvaluationModal(activeRecordForEval, null);
      } else {
        openEvaluationModal(activeRecordForEval);
      }
    }
  });
  $('#btn-wst-trigger-evaluation')?.addEventListener('click', () => {
    if (currentWstTestId && activeRecordForEval) {
      openEvaluationModal(activeRecordForEval);
    }
  });
  $('#btn-wst-trigger-evaluation-paused')?.addEventListener('click', () => {
    if (currentWstTestId && activeRecordForEval) {
      openEvaluationModal(activeRecordForEval);
    }
  });

  // Save Evaluation & Complete
  $('#btn-wst-save-eval')?.addEventListener('click', async () => {
    if (!currentWstTestId) return;
    const saveBtn = $('#btn-wst-save-eval');
    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<svg class="ic animate-spin" aria-hidden="true" style="margin-right:6px"><use href="#i-sync"/></svg> Saving...';
    }

    const payload = {
      outcome: currentEvalOutcome,
      delamination: $('.wst-btn-pill-group[data-eval-field="delamination"] .wst-choice-btn.active')?.dataset.val || 'No delamination',
      bleeding: $('.wst-btn-pill-group[data-eval-field="bleeding"] .wst-choice-btn.active')?.dataset.val || 'No bleeding',
      color_change: $('.wst-btn-pill-group[data-eval-field="color_change"] .wst-choice-btn.active')?.dataset.val || 'No color change',
      outsole_warping: $('.wst-btn-pill-group[data-eval-field="outsole_warping"] .wst-choice-btn.active')?.dataset.val || 'No Outsole warping',
      blooming: $('.wst-btn-pill-group[data-eval-field="blooming"] .wst-choice-btn.active')?.dataset.val || 'No blooming',
      other_findings: $('#wst-eval-other-findings')?.value.trim() || '',
    };

    try {
      const res = await apiFetch(`wst/save-eval/${currentWstTestId}`, {
        method: 'POST',
        body: JSON.stringify(payload),
      });

      if (res.success) {
        showToast(res.message || 'Evaluation saved successfully!', 'success');
        closeEvaluationModal();
        openWstMonitorModal(currentWstTestId);
        loadWstRecords();
      } else {
        showToast(res.message || 'Failed to save evaluation', 'error');
      }
    } catch (err) {
      showToast(`Error: ${err.message}`, 'error');
    } finally {
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Save Evaluation & Complete';
      }
    }
  });

  // Pause Timer Button Handler
  $('#btn-wst-modal-pause')?.addEventListener('click', async () => {
    if (!currentWstTestId) return;
    try {
      const res = await apiFetch(`wst/pause/${currentWstTestId}`, { method: 'POST' });
      if (res.success) {
        showToast('Timer paused successfully.', 'info');
        await openWstMonitorModal(currentWstTestId);
        loadWstRecords(currentWstCategory);
      } else {
        showToast(res.message || 'Failed to pause timer.', 'error');
      }
    } catch (err) {
      showToast(`Error: ${err.message}`, 'error');
    }
  });


  // Flexing Modal Event Handlers
  $('#btn-wst-close-flex-eval')?.addEventListener('click', closeFlexEvaluationModal);
  $('#btn-wst-cancel-flex-eval')?.addEventListener('click', closeFlexEvaluationModal);

  ['#wst-flex-lat-len', '#wst-flex-med-len', '#wst-flex-lat-depth', '#wst-flex-med-depth', '#wst-flex-eval-cycle'].forEach((sel) => {
    $(sel)?.addEventListener('input', updateFlexFindingsFormula);
    $(sel)?.addEventListener('change', updateFlexFindingsFormula);
  });

  $$('.wst-flex-result-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      $$('.wst-flex-result-btn').forEach((b) => {
        b.classList.remove('active');
        b.style.background = '#ffffff';
        b.style.color = '#475569';
        b.style.borderColor = '#cbd5e1';
      });
      btn.classList.add('active');
      currentFlexOutcome = btn.dataset.flexResult.toUpperCase();
      const c = currentFlexOutcome === 'PASSED' ? '#2563eb' : (currentFlexOutcome === 'FAILED' ? '#ef4444' : '#64748b');
      btn.style.background = c;
      btn.style.color = '#ffffff';
      btn.style.borderColor = c;
    });
  });

  $('#btn-wst-save-flex-eval')?.addEventListener('click', async () => {
    if (!currentWstTestId) return;
    const saveBtn = $('#btn-wst-save-flex-eval');
    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<svg class="ic animate-spin" aria-hidden="true" style="margin-right:6px"><use href="#i-sync"/></svg> Saving...';
    }

    const cycle = parseInt($('#wst-flex-eval-cycle')?.value || '125000', 10);
    const latLen = parseFloat($('#wst-flex-lat-len')?.value || '0') || 0;
    const medLen = parseFloat($('#wst-flex-med-len')?.value || '0') || 0;
    const latDepth = parseFloat($('#wst-flex-lat-depth')?.value || '0') || 0;
    const medDepth = parseFloat($('#wst-flex-med-depth')?.value || '0') || 0;
    const findings = $('#wst-flex-findings-preview')?.value.trim() || `45° degrees ${cycle} cycles - (L: ${latLen} mm, M: ${medLen} mm; D:${latDepth} mm, D:${medDepth} mm)`;
    const outcome = currentFlexOutcome === 'PASSED' ? 'PASS' : (currentFlexOutcome === 'FAILED' ? 'FAIL' : 'PENDING');

    try {
      // 1. Record the checkpoint in tbl_material_test_results
      await apiFetch(`wst/log-findings/${currentWstTestId}`, {
        method: 'POST',
        body: JSON.stringify({
          cycle,
          group: 'dry',
          findings,
          pass: (outcome === 'PASS'),
          samples: [1],
        }),
      });

      // 2. Save evaluation outcome and findings JSON
      const res = await apiFetch(`wst/save-eval/${currentWstTestId}`, {
        method: 'POST',
        body: JSON.stringify({
          outcome,
          cycle,
          lat_len: latLen,
          med_len: medLen,
          lat_depth: latDepth,
          med_depth: medDepth,
          findings,
        }),
      });

      if (res.success) {
        showToast(res.message || 'Saved flexing inspection checkpoint!', 'success');
        closeFlexEvaluationModal();
        openWstMonitorModal(currentWstTestId);
        loadWstRecords();
      } else {
        showToast(res.message || 'Failed to save flexing evaluation', 'error');
      }
    } catch (err) {
      showToast(`Error: ${err.message}`, 'error');
    } finally {
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Save Flexing Result';
      }
    }
  });

  loadWstRecords(currentWstCategory);
})();
