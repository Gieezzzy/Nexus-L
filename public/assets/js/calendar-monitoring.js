/**
 * NEXUS-L Calendar Monitoring JavaScript Engine
 * Liquid Glass Calendar & Operations Tracking
 */
(function () {
  'use strict';

  // DOM elements
  const root = document.getElementById('cal-root');
  if (!root) return;

  const eventsUrl = root.dataset.eventsUrl;
  const detailsUrl = root.dataset.detailsUrl;
  const todayStr = root.dataset.today || new Date().toISOString().slice(0, 10);
  const initYear = parseInt(root.dataset.year, 10) || new Date().getFullYear();
  const initMonth = parseInt(root.dataset.month, 10) || (new Date().getMonth() + 1);

  // State
  const state = {
    view: 'month', // 'month' | 'week'
    currentDate: new Date(initYear, initMonth - 1, new Date().getDate()),
    eventsCache: null,
    loading: false,
    domainFilter: 'all', // 'all' | 'bonding' | 'material' | 'wholeshoe'
    statusFilter: 'all', // 'all' | 'ongoing' | 'pending' | 'completed'
    searchQuery: '',
    drawerDate: null,
    drawerItems: [],
    drawerDomainTab: 'all',
    drawerFilter: ''
  };

  // Selectors
  const dom = {
    titleLabel: document.getElementById('cal-title-label'),
    subText: document.getElementById('cal-sub-text'),
    btnPrev: document.getElementById('cal-prev'),
    btnNext: document.getElementById('cal-next'),
    btnToday: document.getElementById('cal-today'),
    btnRefresh: document.getElementById('cal-refresh'),
    viewToggles: document.querySelectorAll('.cal-toggle-btn'),
    monthView: document.getElementById('cal-month-view'),
    weekView: document.getElementById('cal-week-view'),
    monthGrid: document.getElementById('cal-month-grid'),
    weekGrid: document.getElementById('cal-week-grid'),
    searchInput: document.getElementById('cal-search'),
    domainChips: document.querySelectorAll('#cal-domain-filter .cal-chip'),
    statusChips: document.querySelectorAll('#cal-status-filter .cal-chip'),
    // KPIs
    kpiTotal: document.getElementById('kpi-total'),
    kpiOngoing: document.getElementById('kpi-ongoing'),
    kpiPending: document.getElementById('kpi-pending'),
    kpiCompleted: document.getElementById('kpi-completed'),
    kpiRate: document.getElementById('kpi-rate'),
    kpiRateSub: document.getElementById('kpi-rate-sub'),
    // Drawer
    drawer: document.getElementById('cal-drawer'),
    backdrop: document.getElementById('cal-drawer-backdrop'),
    drawerClose: document.getElementById('cal-drawer-close'),
    drawerDismiss: document.getElementById('drawer-btn-dismiss'),
    drawerDateBadge: document.getElementById('drawer-date-badge'),
    drawerTitle: document.getElementById('drawer-title'),
    drawerSubtitle: document.getElementById('drawer-subtitle'),
    drawerItemsList: document.getElementById('drawer-items-list'),
    drawerSearchInput: document.getElementById('drawer-filter-input'),
    drawerTabs: document.querySelectorAll('#drawer-domain-tabs .cal-tab-btn'),
    // Drawer mini stats
    dstatTotal: document.getElementById('dstat-total'),
    dstatBonding: document.getElementById('dstat-bonding'),
    dstatMaterial: document.getElementById('dstat-material'),
    dstatWholeshoe: document.getElementById('dstat-wholeshoe'),
    dstatOngoing: document.getElementById('dstat-ongoing'),
    dstatCompleted: document.getElementById('dstat-completed'),
    dtabCountAll: document.getElementById('dtab-count-all'),
    dtabCountBonding: document.getElementById('dtab-count-bonding'),
    dtabCountMaterial: document.getElementById('dtab-count-material'),
    dtabCountWholeshoe: document.getElementById('dtab-count-wholeshoe')
  };

  // Utilities
  function pad2(n) {
    return n < 10 ? '0' + n : '' + n;
  }

  function formatYmd(d) {
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
  }

  function parseYmd(str) {
    const p = str.split('-');
    return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
  }

  function formatDisplayDate(dateStr) {
    try {
      const d = parseYmd(dateStr);
      return d.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
    } catch (e) {
      return dateStr;
    }
  }

  function getInitials(name) {
    if (!name) return 'LB';
    const clean = name.replace(/[^a-zA-Z\s]/g, '').trim();
    const parts = clean.split(/\s+/);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return clean.slice(0, 2).toUpperCase() || 'LB';
  }

  // Range calculation
  function getActiveRange() {
    if (state.view === 'month') {
      const y = state.currentDate.getFullYear();
      const m = state.currentDate.getMonth();
      const firstOfMonth = new Date(y, m, 1);
      const lastOfMonth = new Date(y, m + 1, 0);

      // Start on preceding Sunday
      const start = new Date(firstOfMonth);
      start.setDate(firstOfMonth.getDate() - firstOfMonth.getDay());

      // End on following Saturday
      const end = new Date(lastOfMonth);
      end.setDate(lastOfMonth.getDate() + (6 - lastOfMonth.getDay()));

      return {
        start: formatYmd(start),
        end: formatYmd(end),
        firstOfMonth,
        lastOfMonth
      };
    } else {
      // Week View: Monday to Sunday
      const curr = new Date(state.currentDate);
      const day = curr.getDay();
      const diffToMon = (day === 0 ? -6 : 1) - day;
      const mon = new Date(curr);
      mon.setDate(curr.getDate() + diffToMon);
      const sun = new Date(mon);
      sun.setDate(mon.getDate() + 6);

      return {
        start: formatYmd(mon),
        end: formatYmd(sun),
        mon,
        sun
      };
    }
  }

  // Update period header title
  function updateTitle() {
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    if (state.view === 'month') {
      const m = months[state.currentDate.getMonth()];
      const y = state.currentDate.getFullYear();
      dom.titleLabel.textContent = m + ', ' + y;
      dom.subText.textContent = 'Monthly overview of laboratory testing throughput & status distribution';
    } else {
      const range = getActiveRange();
      const startMonth = months[range.mon.getMonth()].slice(0, 3);
      const endMonth = months[range.sun.getMonth()].slice(0, 3);
      const y = range.mon.getFullYear();
      if (startMonth === endMonth) {
        dom.titleLabel.textContent = startMonth + ' ' + range.mon.getDate() + ' – ' + range.sun.getDate() + ', ' + y;
      } else {
        dom.titleLabel.textContent = startMonth + ' ' + range.mon.getDate() + ' – ' + endMonth + ' ' + range.sun.getDate() + ', ' + y;
      }
      dom.subText.textContent = 'Weekly detailed operations timeline & time slot allocation';
    }
  }

  // Fetch Events from API
  async function loadEvents() {
    const range = getActiveRange();
    updateTitle();

    try {
      state.loading = true;
      const url = eventsUrl + '?start=' + encodeURIComponent(range.start) + '&end=' + encodeURIComponent(range.end);
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) {
        console.warn('Calendar events HTTP error:', res.status);
        return;
      }
      const data = await res.json();

      if (data && data.ok) {
        state.eventsCache = data;
        renderKPIs(data.kpis);
        if (state.view === 'month') {
          renderMonthGrid(data);
        } else {
          renderWeekGrid(data);
        }
      }
    } catch (err) {
      console.error('Failed to load calendar events:', err);
    } finally {
      state.loading = false;
    }
  }

  // Render KPI Ribbon
  function renderKPIs(kpis) {
    if (!kpis) return;
    dom.kpiTotal.textContent = (kpis.total_tests || 0).toLocaleString();
    dom.kpiOngoing.textContent = (kpis.ongoing || 0).toLocaleString();
    dom.kpiPending.textContent = (kpis.pending || 0).toLocaleString();
    dom.kpiCompleted.textContent = (kpis.completed || 0).toLocaleString();

    const pass = (kpis.passed || 0) + (kpis.tolerable || 0);
    const completed = kpis.completed || 0;
    const rate = completed > 0 ? Math.round((pass / completed) * 100) : 100;
    dom.kpiRate.textContent = rate + '%';
    dom.kpiRateSub.textContent = 'Pass: ' + (kpis.passed || 0) + ' | Fail: ' + (kpis.failed || 0) + ' | Tol: ' + (kpis.tolerable || 0);
  }

  // Check if a day passes current filters
  function filterMatches(dayData) {
    if (!dayData) return false;
    if (state.domainFilter !== 'all') {
      const dCount = (dayData[state.domainFilter] && dayData[state.domainFilter].total) || 0;
      if (dCount === 0) return false;
    }
    if (state.statusFilter !== 'all') {
      const sCount = dayData[state.statusFilter] || 0;
      if (sCount === 0) return false;
    }
    return true;
  }

  // =========================================================================
  // MONTH VIEW RENDERER (matches media_1794143440670_e30e4814.png)
  // =========================================================================
  function renderMonthGrid(data) {
    const range = getActiveRange();
    const daysData = data.days || {};
    const curMonth = state.currentDate.getMonth();
    const curYear = state.currentDate.getFullYear();

    const grid = dom.monthGrid;
    grid.innerHTML = '';

    const startObj = parseYmd(range.start);
    const endObj = parseYmd(range.end);
    let curr = new Date(startObj);

    while (curr <= endObj) {
      const dStr = formatYmd(curr);
      const isOtherMonth = (curr.getMonth() !== curMonth || curr.getFullYear() !== curYear);
      const isToday = (dStr === todayStr);
      const dayData = daysData[dStr] || {
        date: dStr,
        total: 0,
        ongoing: 0,
        pending: 0,
        completed: 0,
        bonding: { total: 0 },
        material: { total: 0 },
        wholeshoe: { total: 0 },
        preview_events: []
      };

      const cell = document.createElement('div');
      cell.className = 'cal-day-cell' + (isOtherMonth ? ' other-month' : '') + (isToday ? ' is-today' : '');
      cell.dataset.date = dStr;

      // Header row
      let badgesHtml = '';
      if (dayData.total > 0) {
        badgesHtml += '<span class="cal-badge-pill-sm cal-badge-total">' + dayData.total + '</span>';
        if (dayData.ongoing > 0) {
          badgesHtml += '<span class="cal-badge-pill-sm cal-badge-ongoing" title="' + dayData.ongoing + ' Ongoing tests"><span class="cal-pulse-mini"></span>' + dayData.ongoing + '</span>';
        }
      }

      // Domain tags
      let domainHtml = '';
      if (dayData.bonding.total > 0 && (state.domainFilter === 'all' || state.domainFilter === 'bonding')) {
        domainHtml += '<span class="cal-domain-tag tag-bonding">B:' + dayData.bonding.total + '</span>';
      }
      if (dayData.material.total > 0 && (state.domainFilter === 'all' || state.domainFilter === 'material')) {
        domainHtml += '<span class="cal-domain-tag tag-material">M:' + dayData.material.total + '</span>';
      }
      if (dayData.wholeshoe.total > 0 && (state.domainFilter === 'all' || state.domainFilter === 'wholeshoe')) {
        domainHtml += '<span class="cal-domain-tag tag-wholeshoe">W:' + dayData.wholeshoe.total + '</span>';
      }

      // Event preview chips (matching user image e30e4814.png)
      let eventsHtml = '';
      const previews = dayData.preview_events || [];
      const filteredPreviews = previews.filter(item => {
        if (state.domainFilter !== 'all' && item.domain !== state.domainFilter) return false;
        if (state.statusFilter !== 'all' && item.status !== state.statusFilter) return false;
        if (state.searchQuery) {
          const q = state.searchQuery.toLowerCase();
          const match = (item.lab_ref && item.lab_ref.toLowerCase().includes(q)) ||
                        (item.style && item.style.toLowerCase().includes(q)) ||
                        (item.test_type && item.test_type.toLowerCase().includes(q));
          if (!match) return false;
        }
        return true;
      });

      if (filteredPreviews.length > 0) {
        eventsHtml += '<div class="cal-day-events">';
        filteredPreviews.slice(0, 3).forEach(ev => {
          let dotColor = '#f59e0b';
          if (ev.domain === 'material') dotColor = '#0284c7';
          if (ev.domain === 'wholeshoe') dotColor = '#8b5cf6';

          const label = ev.style || ev.lab_ref || ev.test_type;
          eventsHtml += `
            <div class="cal-event-chip" title="${escapeHtml(ev.lab_ref)}: ${escapeHtml(ev.style)}">
              <span class="chip-dot" style="background:${dotColor}"></span>
              <span>${escapeHtml(label)}</span>
              <span class="chip-time">${escapeHtml(ev.time_slot)}</span>
            </div>
          `;
        });
        if (dayData.total > 3) {
          eventsHtml += '<div class="cal-more-chip">+' + (dayData.total - 3) + ' more</div>';
        }
        eventsHtml += '</div>';
      }

      cell.innerHTML = `
        <div class="cal-day-header">
          <span class="cal-day-num">${curr.getDate()}</span>
          <div class="cal-day-badges">${badgesHtml}</div>
        </div>
        <div class="cal-day-domains">${domainHtml}</div>
        ${eventsHtml}
      `;

      // Click to open detailed drilldown
      cell.addEventListener('click', () => {
        openDrawer(dStr, dayData);
      });

      grid.appendChild(cell);
      curr.setDate(curr.getDate() + 1);
    }
  }

  // =========================================================================
  // WEEK VIEW RENDERER (matches media_1794143464231_8f0bf7cf.png)
  // =========================================================================
  function renderWeekGrid(data) {
    const range = getActiveRange();
    const daysData = data.days || {};
    const weekItems = data.week_items || [];
    const grid = dom.weekGrid;
    grid.innerHTML = '';

    const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    let curr = new Date(range.mon);

    for (let i = 0; i < 7; i++) {
      const dStr = formatYmd(curr);
      const isToday = (dStr === todayStr);
      const dayData = daysData[dStr] || {
        date: dStr,
        total: 0,
        ongoing: 0,
        pending: 0,
        completed: 0
      };

      const col = document.createElement('div');
      col.className = 'cal-week-col' + (isToday ? ' is-today' : '');
      col.dataset.date = dStr;

      // Filter items for this specific day
      const colItems = weekItems.filter(item => {
        if (item.date_key !== dStr) return false;
        if (state.domainFilter !== 'all' && item.domain !== state.domainFilter) return false;
        if (state.statusFilter !== 'all' && item.status !== state.statusFilter) return false;
        if (state.searchQuery) {
          const q = state.searchQuery.toLowerCase();
          const match = (item.lab_ref && item.lab_ref.toLowerCase().includes(q)) ||
                        (item.style && item.style.toLowerCase().includes(q)) ||
                        (item.technician && item.technician.toLowerCase().includes(q)) ||
                        (item.test_type && item.test_type.toLowerCase().includes(q));
          if (!match) return false;
        }
        return true;
      });

      // Cards list html
      let cardsHtml = '';
      if (colItems.length === 0) {
        cardsHtml = `
          <div class="cal-empty-day">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>No tests scheduled</span>
          </div>
        `;
      } else {
        colItems.forEach(item => {
          const initials = getInitials(item.technician);
          let statusClass = 'badge-completed';
          let statusLabel = 'Completed';
          if (item.status === 'ongoing') {
            statusClass = 'badge-ongoing';
            statusLabel = 'Ongoing';
          } else if (item.status === 'pending') {
            statusClass = 'badge-pending';
            statusLabel = 'Pending';
          }

          let resultPill = '';
          const res = (item.result || '').toUpperCase();
          if (res === 'PASS') {
            resultPill = '<span class="cal-result-pill res-pass">PASS</span>';
          } else if (res === 'FAIL' || res === 'FAILED') {
            resultPill = '<span class="cal-result-pill res-fail">FAIL</span>';
          } else if (res === 'TOLERABLE') {
            resultPill = '<span class="cal-result-pill res-tol">TOLERABLE</span>';
          }

          cardsHtml += `
            <div class="cal-test-card card-${escapeHtml(item.domain)} ${item.status === 'ongoing' ? 'is-ongoing' : ''}" data-id="${escapeHtml(item.id)}">
              <div class="cal-tcard-top">
                <span class="cal-tcard-time">${escapeHtml(item.time_slot || '09:00')}</span>
                <div class="cal-tcard-avatars">
                  <span class="cal-tech-avatar" title="Technician: ${escapeHtml(item.technician || 'Staff')}">${escapeHtml(initials)}</span>
                </div>
              </div>
              <div class="cal-tcard-title">${escapeHtml(item.lab_ref || item.test_type)}</div>
              <div class="cal-tcard-meta">
                <span>${escapeHtml(item.style || item.test_type)}</span>
              </div>
              <div class="cal-tcard-footer">
                <span class="cal-status-badge ${statusClass}">
                  ${item.status === 'ongoing' ? '<span class="cal-pulse-mini"></span>' : ''}
                  ${statusLabel}
                </span>
                ${resultPill}
              </div>
            </div>
          `;
        });
      }

      col.innerHTML = `
        <div class="cal-week-col-accent accent-day-${i}"></div>
        <div class="cal-week-col-head">
          <div class="cal-week-day-title">
            <span class="cal-week-day-num">${curr.getDate()}</span>
            <span class="cal-week-day-name">${dayNames[i]}</span>
          </div>
          <div class="cal-week-sub-bar">
            <span class="cal-week-task-count">${dayData.total > 0 ? dayData.total + ' tests' : 'No tasks'}</span>
            <span>${dayData.ongoing > 0 ? dayData.ongoing + ' ongoing' : (dayData.completed > 0 ? '100% done' : '')}</span>
          </div>
        </div>
        <div class="cal-week-cards-stack">
          ${cardsHtml}
        </div>
      `;

      // Clicking column header or cards opens drawer
      col.querySelector('.cal-week-col-head').addEventListener('click', () => {
        openDrawer(dStr, dayData);
      });
      col.querySelectorAll('.cal-test-card').forEach(card => {
        card.addEventListener('click', (e) => {
          e.stopPropagation();
          openDrawer(dStr, dayData);
        });
      });

      grid.appendChild(col);
      curr.setDate(curr.getDate() + 1);
    }
  }

  // =========================================================================
  // DRILLDOWN SLIDE-OVER DRAWER
  // =========================================================================
  async function openDrawer(dateStr, daySummary) {
    state.drawerDate = dateStr;
    state.drawerDomainTab = 'all';
    state.drawerFilter = '';

    dom.drawerDateBadge.textContent = dateStr;
    dom.drawerTitle.textContent = formatDisplayDate(dateStr);
    dom.drawerSubtitle.textContent = 'Laboratory test records & quality results';

    // Show loading indicator
    dom.drawerItemsList.innerHTML = `
      <div class="cal-drawer-loading">
        <div class="cal-spinner"></div>
        <p>Loading test records for ${escapeHtml(dateStr)}…</p>
      </div>
    `;

    // Reset tabs active state
    dom.drawerTabs.forEach(t => {
      t.classList.toggle('active', t.dataset.tab === 'all');
    });
    if (dom.drawerSearchInput) dom.drawerSearchInput.value = '';

    // Show drawer
    dom.backdrop.classList.add('active');
    dom.drawer.classList.add('active');
    dom.drawer.setAttribute('aria-hidden', 'false');

    try {
      const res = await fetch(detailsUrl + '?date=' + encodeURIComponent(dateStr), {
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();

      if (data && data.ok) {
        state.drawerItems = data.items || [];
        updateDrawerStats(data.summary || {});
        renderDrawerItems();
      } else {
        dom.drawerItemsList.innerHTML = `
          <div class="cal-empty-day">
            <p>No records found for this date.</p>
          </div>
        `;
      }
    } catch (err) {
      console.error('Failed to load day details:', err);
      dom.drawerItemsList.innerHTML = `
        <div class="cal-empty-day" style="color:#ef4444">
          <p>Failed to load records. Please try again.</p>
        </div>
      `;
    }
  }

  function closeDrawer() {
    dom.backdrop.classList.remove('active');
    dom.drawer.classList.remove('active');
    dom.drawer.setAttribute('aria-hidden', 'true');
  }

  function updateDrawerStats(summary) {
    const total = summary.total || state.drawerItems.length;
    const bonding = summary.bonding || 0;
    const material = summary.material || 0;
    const wholeshoe = summary.wholeshoe || 0;
    const ongoing = summary.ongoing || 0;
    const completed = summary.completed || 0;

    dom.dstatTotal.textContent = total;
    dom.dstatBonding.textContent = bonding;
    dom.dstatMaterial.textContent = material;
    dom.dstatWholeshoe.textContent = wholeshoe;
    dom.dstatOngoing.textContent = ongoing;
    dom.dstatCompleted.textContent = completed;

    dom.dtabCountAll.textContent = total;
    dom.dtabCountBonding.textContent = bonding;
    dom.dtabCountMaterial.textContent = material;
    dom.dtabCountWholeshoe.textContent = wholeshoe;
  }

  function renderDrawerItems() {
    const list = dom.drawerItemsList;
    const q = (state.drawerFilter || '').toLowerCase().trim();
    const tab = state.drawerDomainTab;

    const filtered = state.drawerItems.filter(item => {
      if (tab !== 'all' && item.domain !== tab) return false;
      if (q) {
        const matches = (item.lab_ref_no && item.lab_ref_no.toLowerCase().includes(q)) ||
                        (item.tr_no && item.tr_no.toLowerCase().includes(q)) ||
                        (item.style && item.style.toLowerCase().includes(q)) ||
                        (item.color && item.color.toLowerCase().includes(q)) ||
                        (item.test_type && item.test_type.toLowerCase().includes(q)) ||
                        (item.technician && item.technician.toLowerCase().includes(q)) ||
                        (item.result && item.result.toLowerCase().includes(q));
        if (!matches) return false;
      }
      return true;
    });

    if (filtered.length === 0) {
      list.innerHTML = `
        <div class="cal-empty-day">
          <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          <p>No tests match the current filter or search criteria.</p>
        </div>
      `;
      return;
    }

    let html = '';
    filtered.forEach(item => {
      let statusBadge = '';
      if (item.status === 'ongoing') {
        statusBadge = '<span class="cal-status-badge badge-ongoing"><span class="cal-pulse-mini"></span>Ongoing</span>';
      } else if (item.status === 'pending') {
        statusBadge = '<span class="cal-status-badge badge-pending">Pending</span>';
      } else {
        statusBadge = '<span class="cal-status-badge badge-completed">Completed</span>';
      }

      let resultPill = '';
      const res = (item.result || '').toUpperCase();
      if (res === 'PASS') {
        resultPill = '<span class="cal-result-pill res-pass">PASS</span>';
      } else if (res === 'FAIL' || res === 'FAILED') {
        resultPill = '<span class="cal-result-pill res-fail">FAIL</span>';
      } else if (res === 'TOLERABLE') {
        resultPill = '<span class="cal-result-pill res-tol">TOLERABLE</span>';
      } else if (res) {
        resultPill = `<span class="cal-result-pill" style="background:#e2e8f0;color:#334155">${escapeHtml(res)}</span>`;
      }

      html += `
        <div class="cal-dcard">
          <div class="cal-dcard-head">
            <div>
              <span class="cal-dcard-domain ${escapeHtml(item.domain)}">${escapeHtml(item.domain_label || item.domain)}</span>
              <div class="cal-dcard-ref">${escapeHtml(item.lab_ref_no || 'No Lab Ref')}</div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
              ${statusBadge}
              ${resultPill}
            </div>
          </div>

          <div class="cal-dcard-grid">
            <div class="cal-dcard-field">
              <span class="cal-df-label">Test Procedure</span>
              <span class="cal-df-val">${escapeHtml(item.test_type || '–')}</span>
            </div>
            <div class="cal-dcard-field">
              <span class="cal-df-label">Model Style / Article</span>
              <span class="cal-df-val">${escapeHtml(item.style || '–')}</span>
            </div>
            <div class="cal-dcard-field">
              <span class="cal-df-label">Colorway / Material</span>
              <span class="cal-df-val">${escapeHtml(item.color || item.mtl || '–')}</span>
            </div>
            <div class="cal-dcard-field">
              <span class="cal-df-label">Technician</span>
              <span class="cal-df-val">${escapeHtml(item.technician || '–')}</span>
            </div>
            <div class="cal-dcard-field">
              <span class="cal-df-label">Test Date</span>
              <span class="cal-df-val">${escapeHtml(fmtDate(item.test_date))}</span>
            </div>
            <div class="cal-dcard-field">
              <span class="cal-df-label">Production Date</span>
              <span class="cal-df-val">${escapeHtml(fmtDate(item.prod_date))}</span>
            </div>
            ${item.tr_no || item.mtl ? `
              <div class="cal-dcard-field">
                <span class="cal-df-label">TR No. / MTL</span>
                <span class="cal-df-val">${escapeHtml([item.tr_no, item.mtl].filter(Boolean).join(' / '))}</span>
              </div>` : ''}
            ${item.erp_status ? `
              <div class="cal-dcard-field">
                <span class="cal-df-label">ERP</span>
                <span class="cal-df-val">${escapeHtml(item.erp_status)}</span>
              </div>` : ''}
            ${item.remarks ? `
              <div class="cal-dcard-field" style="grid-column: span 2;">
                <span class="cal-df-label">Remarks / Findings</span>
                <span class="cal-df-val" style="color:#64748b;font-weight:400">${escapeHtml(item.remarks)}</span>
              </div>
            ` : ''}
          </div>

          <div class="cal-dcard-foot">
            <span style="font-size:11px;color:#94a3b8">${escapeHtml(item.category || '')}</span>
            ${item.view_url ? `
              <a href="${escapeHtml(item.view_url)}" target="_blank" class="cal-dcard-link">
                <span>View Full Sheet</span>
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
              </a>
            ` : ''}
          </div>
        </div>
      `;
    });

    list.innerHTML = html;
  }

  function fmtDate(d) {
    if (!d) return '–';
    const t = new Date(d + 'T00:00:00');
    return isNaN(t) ? d : t.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // =========================================================================
  // EVENT LISTENERS & SETUP
  // =========================================================================
  function bindEvents() {
    // Navigation: Prev
    dom.btnPrev.addEventListener('click', () => {
      if (state.view === 'month') {
        state.currentDate.setDate(1); state.currentDate.setMonth(state.currentDate.getMonth() - 1);
      } else {
        state.currentDate.setDate(state.currentDate.getDate() - 7);
      }
      loadEvents();
    });

    // Navigation: Next
    dom.btnNext.addEventListener('click', () => {
      if (state.view === 'month') {
        state.currentDate.setDate(1); state.currentDate.setMonth(state.currentDate.getMonth() + 1);
      } else {
        state.currentDate.setDate(state.currentDate.getDate() + 7);
      }
      loadEvents();
    });

    // Navigation: Today
    dom.btnToday.addEventListener('click', () => {
      state.currentDate = parseYmd(todayStr);
      loadEvents();
    });

    // Refresh
    dom.btnRefresh.addEventListener('click', () => {
      loadEvents();
    });

    // View toggles (Month vs Week)
    dom.viewToggles.forEach(btn => {
      btn.addEventListener('click', () => {
        const v = btn.dataset.view;
        if (state.view === v) return;
        state.view = v;
        dom.viewToggles.forEach(b => {
          const isActive = b.dataset.view === v;
          b.classList.toggle('active', isActive);
          b.setAttribute('aria-checked', isActive ? 'true' : 'false');
        });
        if (v === 'month') {
          dom.monthView.classList.remove('hidden');
          dom.weekView.classList.add('hidden');
        } else {
          dom.monthView.classList.add('hidden');
          dom.weekView.classList.remove('hidden');
        }
        loadEvents();
      });
    });

    // Domain chips filter
    dom.domainChips.forEach(chip => {
      chip.addEventListener('click', () => {
        dom.domainChips.forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        state.domainFilter = chip.dataset.domain;
        if (state.eventsCache) {
          if (state.view === 'month') renderMonthGrid(state.eventsCache);
          else renderWeekGrid(state.eventsCache);
        }
      });
    });

    // Status chips filter
    dom.statusChips.forEach(chip => {
      chip.addEventListener('click', () => {
        dom.statusChips.forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        state.statusFilter = chip.dataset.status;
        if (state.eventsCache) {
          if (state.view === 'month') renderMonthGrid(state.eventsCache);
          else renderWeekGrid(state.eventsCache);
        }
      });
    });

    // Quick search input
    let searchDebounce = null;
    dom.searchInput.addEventListener('input', (e) => {
      clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => {
        state.searchQuery = e.target.value.trim();
        if (state.eventsCache) {
          if (state.view === 'month') renderMonthGrid(state.eventsCache);
          else renderWeekGrid(state.eventsCache);
        }
      }, 150);
    });

    // Drawer close buttons
    dom.drawerClose.addEventListener('click', closeDrawer);
    dom.drawerDismiss.addEventListener('click', closeDrawer);
    dom.backdrop.addEventListener('click', closeDrawer);

    // Drawer domain tabs
    dom.drawerTabs.forEach(tab => {
      tab.addEventListener('click', () => {
        dom.drawerTabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        state.drawerDomainTab = tab.dataset.tab;
        renderDrawerItems();
      });
    });

    // Drawer search input
    dom.drawerSearchInput.addEventListener('input', (e) => {
      state.drawerFilter = e.target.value;
      renderDrawerItems();
    });

    // Keyboard navigation (Escape to close drawer)
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && dom.drawer.classList.contains('active')) {
        closeDrawer();
      }
    });
  }

  // Initial load
  bindEvents();
  loadEvents();
})();
