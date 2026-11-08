<?php
/**
 * NEXUS-L Calendar Monitoring View
 * Liquid Glass UI for tracking Bonding, Material, and Whole Shoe testing throughput.
 */
$u = service('auth')->user();
?>
<div class="cal-root" id="cal-root" 
     data-events-url="<?= esc(site_url('calendar-monitoring/events')) ?>" 
     data-details-url="<?= esc(site_url('calendar-monitoring/day-details')) ?>" 
     data-today="<?= esc($today) ?>" 
     data-year="<?= esc($curYear) ?>" 
     data-month="<?= esc($curMonth) ?>">

    <!-- Ambient glowing glass backlight -->
    <div class="cal-bg" aria-hidden="true"><i></i><i></i><i></i></div>

    <!-- Header Glass Control Strip -->
    <header class="cal-glass cal-head">
        <div class="cal-title-block">
            <div class="cal-title-row">
                <div class="cal-badge-pill">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>Lab Operations</span>
                </div>
                <h2>Calendar Monitoring</h2>
            </div>
            <p class="cal-sub" id="cal-sub-text">Laboratory test scheduling, daily throughput &amp; status tracking across all active domains</p>
        </div>

        <!-- Center: Date Controls & Navigation -->
        <div class="cal-nav-group">
            <div class="cal-nav-btns" role="group" aria-label="Calendar Navigation">
                <button type="button" class="cal-btn-icon" id="cal-prev" title="Previous period">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button type="button" class="cal-btn-text" id="cal-today">Today</button>
                <button type="button" class="cal-btn-icon" id="cal-next" title="Next period">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
            <div class="cal-period-display" id="cal-period-display">
                <span class="cal-period-title" id="cal-title-label">Loading…</span>
            </div>
        </div>

        <!-- Right: View Mode Toggle & Quick Action -->
        <div class="cal-actions-group">
            <div class="cal-view-toggle" role="radiogroup" aria-label="Calendar View Mode">
                <button type="button" class="cal-toggle-btn active" data-view="month" role="radio" aria-checked="true">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Month</span>
                </button>
                <button type="button" class="cal-toggle-btn" data-view="week" role="radio" aria-checked="false">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    <span>Week</span>
                </button>
            </div>
            <button type="button" class="cal-btn-glass" id="cal-refresh" title="Reload live events">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 11a8 8 0 10-2.3 5.7M20 4v7h-7"/></svg>
                <span>Refresh</span>
            </button>
        </div>
    </header>

    <!-- Top KPI Liquid Glass Ribbon -->
    <section class="cal-kpis" id="cal-kpis" aria-label="Testing summary statistics">
        <div class="cal-glass cal-kpi-card">
            <div class="cal-kpi-header">
                <span class="cal-kpi-icon cal-icon-total">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                </span>
                <span class="cal-kpi-label">Total Tests</span>
            </div>
            <div class="cal-kpi-val" id="kpi-total">–</div>
            <div class="cal-kpi-footer">Across Bonding, Material &amp; Whole Shoe</div>
        </div>

        <div class="cal-glass cal-kpi-card">
            <div class="cal-kpi-header">
                <span class="cal-kpi-icon cal-icon-ongoing">
                    <span class="cal-pulse-dot"></span>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </span>
                <span class="cal-kpi-label">Ongoing Tests</span>
            </div>
            <div class="cal-kpi-val cal-val-ongoing" id="kpi-ongoing">–</div>
            <div class="cal-kpi-footer">Active machine &amp; shift tests in progress</div>
        </div>

        <div class="cal-glass cal-kpi-card">
            <div class="cal-kpi-header">
                <span class="cal-kpi-icon cal-icon-pending">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </span>
                <span class="cal-kpi-label">Pending Tests</span>
            </div>
            <div class="cal-kpi-val cal-val-pending" id="kpi-pending">–</div>
            <div class="cal-kpi-footer">Queued or awaiting final sign-off</div>
        </div>

        <div class="cal-glass cal-kpi-card">
            <div class="cal-kpi-header">
                <span class="cal-kpi-icon cal-icon-completed">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </span>
                <span class="cal-kpi-label">Completed Tests</span>
            </div>
            <div class="cal-kpi-val cal-val-completed" id="kpi-completed">–</div>
            <div class="cal-kpi-footer">Logged with finalized test results</div>
        </div>

        <div class="cal-glass cal-kpi-card">
            <div class="cal-kpi-header">
                <span class="cal-kpi-icon cal-icon-rate">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </span>
                <span class="cal-kpi-label">Quality Rate</span>
            </div>
            <div class="cal-kpi-val cal-val-rate" id="kpi-rate">–%</div>
            <div class="cal-kpi-footer" id="kpi-rate-sub">Pass / Tolerable vs Fail</div>
        </div>
    </section>

    <!-- Interactive Filtering & Search Ribbon -->
    <div class="cal-glass cal-filters-bar">
        <div class="cal-filters-left">
            <span class="cal-filter-label">Category:</span>
            <div class="cal-filter-chips" id="cal-domain-filter" role="radiogroup" aria-label="Category Filter">
                <button type="button" class="cal-chip active" data-domain="all">All Domains</button>
                <button type="button" class="cal-chip chip-bonding" data-domain="bonding">
                    <i class="cal-dot dot-bonding"></i>Bonding (U60)
                </button>
                <button type="button" class="cal-chip chip-material" data-domain="material">
                    <i class="cal-dot dot-material"></i>Material Testing
                </button>
                <button type="button" class="cal-chip chip-wholeshoe" data-domain="wholeshoe">
                    <i class="cal-dot dot-wholeshoe"></i>Whole Shoe
                </button>
            </div>
        </div>

        <div class="cal-filters-center">
            <span class="cal-filter-label">Status:</span>
            <div class="cal-filter-chips" id="cal-status-filter" role="radiogroup" aria-label="Status Filter">
                <button type="button" class="cal-chip active" data-status="all">All</button>
                <button type="button" class="cal-chip chip-ongoing" data-status="ongoing">
                    <span class="cal-pulse-mini"></span>Ongoing
                </button>
                <button type="button" class="cal-chip chip-pending" data-status="pending">Pending</button>
                <button type="button" class="cal-chip chip-completed" data-status="completed">Completed</button>
            </div>
        </div>

        <div class="cal-filters-right">
            <div class="cal-search-box">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="cal-search" placeholder="Search lab ref, style, tech…" autocomplete="off">
            </div>
        </div>
    </div>

    <!-- MAIN VIEW 1: MONTH CALENDAR GRID (matches media_1794143440670_e30e4814.png) -->
    <div class="cal-view-container" id="cal-month-view">
        <div class="cal-glass cal-calendar-sheet">
            <!-- Weekday Column Headers -->
            <div class="cal-weekday-header">
                <div class="cal-weekday">Sun</div>
                <div class="cal-weekday">Mon</div>
                <div class="cal-weekday">Tue</div>
                <div class="cal-weekday">Wed</div>
                <div class="cal-weekday">Thu</div>
                <div class="cal-weekday">Fri</div>
                <div class="cal-weekday">Sat</div>
            </div>

            <!-- 6x7 Month Grid populated dynamically by calendar-monitoring.js -->
            <div class="cal-month-grid" id="cal-month-grid" role="grid" aria-label="Month View Grid">
                <!-- Dynamically injected day cells -->
            </div>
        </div>
    </div>

    <!-- MAIN VIEW 2: WEEK TIMELINE VIEW (matches media_1794143464231_8f0bf7cf.png) -->
    <div class="cal-view-container hidden" id="cal-week-view">
        <div class="cal-glass cal-week-sheet">
            <div class="cal-week-grid" id="cal-week-grid" role="grid" aria-label="Week View Grid">
                <!-- 7 Day Columns (Monday to Sunday) populated dynamically by calendar-monitoring.js -->
            </div>
        </div>
    </div>

    <!-- Liquid Glass Drilldown Slide-Over Drawer (On click of date or card) -->
    <div class="cal-drawer-backdrop" id="cal-drawer-backdrop" aria-hidden="true"></div>
    <aside class="cal-drawer cal-glass" id="cal-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title" aria-hidden="true">
        <header class="cal-drawer-head">
            <div class="cal-drawer-title-box">
                <div class="cal-drawer-date-badge" id="drawer-date-badge">Nov 08, 2026</div>
                <h3 id="drawer-title">Test Details &amp; Records</h3>
                <p id="drawer-subtitle">Daily breakdown across laboratory test procedures</p>
            </div>
            <button type="button" class="cal-drawer-close" id="cal-drawer-close" aria-label="Close drawer">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </header>

        <!-- Mini stats banner inside drawer -->
        <div class="cal-drawer-stats" id="drawer-stats">
            <div class="cal-dstat-item">
                <span class="dstat-num" id="dstat-total">0</span>
                <span class="dstat-lbl">Total</span>
            </div>
            <div class="cal-dstat-item dstat-bonding">
                <span class="dstat-num" id="dstat-bonding">0</span>
                <span class="dstat-lbl">Bonding</span>
            </div>
            <div class="cal-dstat-item dstat-material">
                <span class="dstat-num" id="dstat-material">0</span>
                <span class="dstat-lbl">Material</span>
            </div>
            <div class="cal-dstat-item dstat-wholeshoe">
                <span class="dstat-num" id="dstat-wholeshoe">0</span>
                <span class="dstat-lbl">Whole Shoe</span>
            </div>
            <div class="cal-dstat-item dstat-ongoing">
                <span class="dstat-num" id="dstat-ongoing">0</span>
                <span class="dstat-lbl">Ongoing</span>
            </div>
            <div class="cal-dstat-item dstat-completed">
                <span class="dstat-num" id="dstat-completed">0</span>
                <span class="dstat-lbl">Completed</span>
            </div>
        </div>

        <!-- Filter tabs inside drawer -->
        <div class="cal-drawer-toolbar">
            <div class="cal-drawer-tabs" id="drawer-domain-tabs">
                <button type="button" class="cal-tab-btn active" data-tab="all">All (<span id="dtab-count-all">0</span>)</button>
                <button type="button" class="cal-tab-btn" data-tab="bonding">Bonding (<span id="dtab-count-bonding">0</span>)</button>
                <button type="button" class="cal-tab-btn" data-tab="material">Material (<span id="dtab-count-material">0</span>)</button>
                <button type="button" class="cal-tab-btn" data-tab="wholeshoe">Whole Shoe (<span id="dtab-count-wholeshoe">0</span>)</button>
            </div>
            <div class="cal-drawer-search">
                <input type="search" id="drawer-filter-input" placeholder="Filter this day’s tests…" autocomplete="off">
            </div>
        </div>

        <!-- List / Cards Container inside drawer -->
        <div class="cal-drawer-body" id="drawer-items-list">
            <div class="cal-drawer-loading">
                <div class="cal-spinner"></div>
                <p>Loading test records…</p>
            </div>
        </div>

        <footer class="cal-drawer-foot">
            <div class="cal-drawer-foot-note">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>Click any record to inspect full laboratory sheets &amp; parameters</span>
            </div>
            <button type="button" class="cal-btn-glass" id="drawer-btn-dismiss">Close</button>
        </footer>
    </aside>
</div>
