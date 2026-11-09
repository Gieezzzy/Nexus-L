<?php
/**
 * Quality Insights: line map, failure modes, supplier scorecard. Styles from calendar.css (glass header, side panel) + quality.css.
 */
?>
<div class="cal-root qi-root" id="qi-root" data-base="<?= esc(site_url('quality-insights')) ?>" data-today="<?= esc($today) ?>">
    <div class="cal-bg" aria-hidden="true"><i></i><i></i><i></i></div>

    <header class="cal-glass cal-head">
        <div class="cal-title-block">
            <div class="cal-title-row">
                <div class="cal-badge-pill"><span>Lab Operations</span></div>
                <h2>Quality Insights</h2>
            </div>
            <p class="cal-sub" id="qi-sub">Where the quality problems are: by line, by failure mode and by supplier</p>
        </div>
        <!-- Center: Date Controls & Navigation -->
        <div class="cal-nav-group">
            <div class="cal-nav-btns" role="group" aria-label="Calendar Navigation">
                <button type="button" class="cal-btn-icon" id="qi-prev" title="Previous period">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button type="button" class="cal-btn-text" id="qi-today">Today</button>
                <button type="button" class="cal-btn-icon" id="qi-next" title="Next period">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
            <div class="cal-period-display" id="qi-period-display">
                <span class="cal-period-title" id="qi-title-label">Loading…</span>
            </div>
        </div>

        <div class="cal-actions-group">
            <div class="cal-view-toggle" role="radiogroup" aria-label="Calendar View Mode">
                <button type="button" class="cal-toggle-btn qi-view active" data-view="month" role="radio" aria-checked="true">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span>Month</span>
                </button>
                <button type="button" class="cal-toggle-btn qi-view" data-view="week" role="radio" aria-checked="false">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    <span>Week</span>
                </button>
                <button type="button" class="cal-toggle-btn qi-view" data-view="day" role="radio" aria-checked="false">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="5" width="16" height="15" rx="2"/><line x1="4" y1="10" x2="20" y2="10"/><line x1="9" y1="3" x2="9" y2="7"/><line x1="15" y1="3" x2="15" y2="7"/></svg>
                    <span>Day</span>
                </button>
            </div>
            <button type="button" class="cal-btn-glass" id="qi-refresh" title="Reload from the lab data">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 11a8 8 0 10-2.3 5.7M20 4v7h-7"/></svg>
                <span>Refresh</span>
            </button>
        </div>
    </header>

    <nav class="qi-tabs cal-glass" role="tablist" aria-label="Quality Insights views">
        <button type="button" class="qi-tab active" role="tab" data-tab="lines" aria-selected="true">Line map</button>
        <button type="button" class="qi-tab" role="tab" data-tab="fail" aria-selected="false">Failure modes</button>
        <button type="button" class="qi-tab" role="tab" data-tab="sup" aria-selected="false">Suppliers</button>
    </nav>

    <!-- ===== Line map ===== -->
    <section class="qi-panel" id="qi-panel-lines" role="tabpanel">
        <div class="qi-toolbar cal-glass"><span class="qi-hint" id="qi-line-hint"></span><label class="qi-field"><input type="checkbox" id="qi-show-empty"> Show lines with no tests</label></div>
        <div class="qi-kpis" id="qi-line-kpis"></div>
        <div class="qi-help"><b>How to read it:</b> each card is one assembly line, adding up every test in the period chosen above.
            <span class="qi-badge green">ALL PASSED</span> everything tested passed &middot;
            <span class="qi-badge amber">WATCH</span> no failure, but a tolerable result or shoes still waiting to be tested &middot;
            <span class="qi-badge red">FAILED</span> at least one test failed. The bar shows the mix: green passed, amber tolerable, red failed, grey waiting. Click a card to see its tests.</div>
        <div id="qi-line-body"></div>
    </section>

    <!-- ===== Failure modes ===== -->
    <section class="qi-panel hidden" id="qi-panel-fail" role="tabpanel">
        <div class="qi-toolbar cal-glass">
            <span class="qi-hint" id="qi-fail-hint"></span>
            <label class="qi-field">Group by
                <select id="qi-group">
                    <option value="line">Line</option>
                    <option value="cement">Cement supplier</option>
                    <option value="style">Style</option>
                    <option value="mode">Failure mode</option>
                </select>
            </label>
        </div>
        <div class="qi-kpis" id="qi-fail-kpis"></div>
        <div class="qi-grid2">
            <div class="cal-glass qi-card"><h3 id="qi-fail-title">By line</h3><div id="qi-fail-table"></div></div>
            <div class="cal-glass qi-card"><h3>Failure modes</h3><div id="qi-modes"></div></div>
        </div>
    </section>

    <!-- ===== Suppliers ===== -->
    <section class="qi-panel hidden" id="qi-panel-sup" role="tabpanel">
        <div class="qi-toolbar cal-glass">
            <div class="qi-seg" role="group" aria-label="Range" id="qi-sup-period">
                <button type="button" data-mode="period" class="active" title="The month, week or day chosen above">This period</button>
                <button type="button" data-mode="90">Last 90 days</button>
                <button type="button" data-mode="180">Last 180 days</button>
            </div>
            <label class="qi-field">Low sample below
                <input type="number" id="qi-min" min="1" max="50" value="5" style="width:64px"> tests
            </label>
        </div>
        <div class="cal-glass qi-card"><div id="qi-sup-table"></div><p class="qi-note" id="qi-sup-note"></p></div>
    </section>

    <!-- Side panel (same look as Calendar Monitoring) -->
    <div class="cal-drawer-backdrop" id="qi-backdrop" aria-hidden="true"></div>
    <aside class="cal-drawer cal-glass" id="qi-drawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="qi-drawer-title">
        <header class="cal-drawer-head">
            <div class="cal-drawer-title-box">
                <div class="cal-drawer-date-badge" id="qi-drawer-badge"></div>
                <h3 id="qi-drawer-title">Tests</h3>
                <p id="qi-drawer-sub"></p>
            </div>
            <button type="button" class="cal-drawer-close" id="qi-drawer-close" aria-label="Close">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </header>
        <div class="cal-drawer-body" id="qi-drawer-body"></div>
    </aside>
</div>
