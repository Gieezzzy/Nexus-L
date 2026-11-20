<?php
/**
 * NEXUS-L Whole Shoe Test & Cyclic Monitoring Suite
 * Laboratory Monitoring & Physical Test Cycles (Bally Flex, Ross Flex, Martindale, Lace, Taber, Crocking)
 */
?>

<!-- =======================================================================
     WHOLE SHOE TEST (LABORATORY MONITORING WORKFLOW)
     ======================================================================= -->
<section id="dtr-panel-wholeshoetest" class="dtr-panel">
    <!-- SUB-VIEW 1: TEST LIST / QUEUE -->
    <div id="wst-view-list" class="wst-subview">
        <!-- Quick KPI Summary Strip -->
        <div class="wst-kpi-strip" style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:14px">
            <div class="card glass wst-kpi-card" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between">
                <div>
                    <small class="muted uppercase" style="font-size:11px;font-weight:700">Active Running</small>
                    <b id="wst-kpi-running" style="display:block;font-size:22px;color:#38bdf8;margin-top:2px">0</b>
                </div>
                <div class="wst-kpi-ico" style="background:rgba(56,189,248,0.15);color:#38bdf8;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center">
                    <svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg>
                </div>
            </div>
            <div class="card glass wst-kpi-card" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between">
                <div>
                    <small class="muted uppercase" style="font-size:11px;font-weight:700">Completed</small>
                    <b id="wst-kpi-completed" style="display:block;font-size:22px;color:#4ade80;margin-top:2px">0</b>
                </div>
                <div class="wst-kpi-ico" style="background:rgba(34,197,94,0.15);color:#22c55e;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center">
                    <svg class="ic" aria-hidden="true"><use href="#i-check"/></svg>
                </div>
            </div>
            <div class="card glass wst-kpi-card" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between">
                <div>
                    <small class="muted uppercase" style="font-size:11px;font-weight:700">Pending</small>
                    <b id="wst-kpi-pending" style="display:block;font-size:22px;color:#fbbf24;margin-top:2px">0</b>
                </div>
                <div class="wst-kpi-ico" style="background:rgba(245,158,11,0.15);color:#f59e0b;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center">
                    <svg class="ic" aria-hidden="true"><use href="#i-clock"/></svg>
                </div>
            </div>
        </div>

        <!-- Category Tabs -->
        <div class="dtr-subtabs wst-category-tabs" role="tablist">
            <button type="button" role="tab" class="dtr-subtab active" id="wst-tab-shoes"><svg class="ic" aria-hidden="true"><use href="#i-shoe-flex"/></svg> Whole Shoes</button>
            <button type="button" role="tab" class="dtr-subtab wst-cat-tab" data-wst-cat="hydrolysis"><svg class="ic" aria-hidden="true"><use href="#i-droplet"/></svg> Hydrolysis</button>
            <button type="button" role="tab" class="dtr-subtab wst-cat-tab" data-wst-cat="heat-aging"><svg class="ic" aria-hidden="true"><use href="#i-thermometer"/></svg> Heat Aging</button>
            <button type="button" role="tab" class="dtr-subtab wst-cat-tab" data-wst-cat="washing"><svg class="ic" aria-hidden="true"><use href="#i-washing-machine"/></svg> Washing</button>
            <button type="button" role="tab" class="dtr-subtab wst-cat-tab" data-wst-cat="uv-light"><svg class="ic" aria-hidden="true"><use href="#i-lamp"/></svg> UV Light</button>
            <button type="button" role="tab" class="dtr-subtab wst-cat-tab" data-wst-cat="flexing"><svg class="ic" aria-hidden="true"><use href="#i-shoe-flex"/></svg> Flexing</button>
            <button type="button" role="tab" class="dtr-subtab wst-cat-tab" data-wst-cat="deeptest"><svg class="ic" aria-hidden="true"><use href="#i-bucket"/></svg> Dip Test</button>
        </div>

        <!-- Whole Shoes: one line per whole shoe (parent Lab Ref); click the Lab Ref to open all its tests -->
        <div class="card glass dtr-section-card" id="wst-shoes-view">
            <div class="dtr-card-head" style="margin-bottom:14px">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;width:100%">
                    <div style="position:relative;display:flex;align-items:center;flex:1;min-width:260px;max-width:460px">
                        <svg style="position:absolute;left:11px;width:15px;height:15px;color:#94a3b8;pointer-events:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" id="ws-search" class="dtr-input" placeholder="Search Lab Reference, Production Code, Style, Test Lab Ref..." style="width:100%;padding:8px 14px 8px 34px">
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;margin-left:auto">
                        <span class="muted sm" id="ws-count">0 record(s)</span>
                        <div style="display:flex;align-items:center;gap:8px">
                            <button class="dtr-btn-xs dtr-btn-outline" id="ws-prev" style="padding:5px 12px;font-weight:600;border-radius:8px" disabled>&lsaquo; Prev</button>
                            <span class="muted sm" id="ws-page" style="min-width:48px;text-align:center;font-weight:600">1 / 1</span>
                            <button class="dtr-btn-xs dtr-btn-outline" id="ws-next" style="padding:5px 12px;font-weight:600;border-radius:8px" disabled>Next &rsaquo;</button>
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:10px">
                    <div style="display:flex;align-items:center;gap:8px;margin-left:auto">
                        <span class="muted sm" style="font-size:12px;font-weight:600">Date Range:</span>
                        <input type="date" id="ws-from" class="dtr-input" style="padding:5px 8px;font-size:12px" title="From Date">
                        <span class="muted sm">to</span>
                        <input type="date" id="ws-to" class="dtr-input" style="padding:5px 8px;font-size:12px" title="To Date">
                        <button type="button" id="ws-reset" class="btn btn-outline-secondary btn-sm" style="padding:5px 10px;font-size:12px;display:inline-flex;align-items:center;gap:4px" title="Reset all filters">
                            <svg class="ic" aria-hidden="true" style="width:12px;height:12px"><use href="#i-refresh"/></svg> Reset
                        </button>
                    </div>
                </div>
            </div>
            <div class="dtr-table-wrap">
                <table class="dtr-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">NO</th>
                            <th>LAB REFERENCE</th>
                            <th>TEST DATE</th>
                            <th>SPECIFICATION / MATERIAL</th>
                            <th>PRODUCTION CODE / MTL</th>
                            <th>TR NO</th>
                            <th>TESTS</th>
                            <th>STATUS</th>
                            <th style="text-align:right">ACTION</th>
                        </tr>
                    </thead>
                    <tbody id="ws-list"><tr><td colspan="9" class="muted" style="text-align:center;padding:24px">Loading whole shoes...</td></tr></tbody>
                </table>
            </div>
        </div>

        <div id="wst-cat-view" style="display:none">
        <div class="card glass dtr-section-card">
                <div class="dtr-card-head" style="margin-bottom:14px">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;width:100%">
                    <div style="position:relative;display:flex;align-items:center;flex:1;min-width:260px;max-width:460px">
                        <svg style="position:absolute;left:11px;width:15px;height:15px;color:#94a3b8;pointer-events:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" id="wst-list-search" class="dtr-input" placeholder="Search Lab Reference, Technician, Operator, Specification, Machine..." style="width:100%;padding:8px 14px 8px 34px">
                    </div>

                    <div style="display:flex;align-items:center;gap:10px;margin-left:auto">
                        <span class="muted sm" id="wst-pagination-count">0 record(s)</span>
                        <div style="display:flex;align-items:center;gap:8px">
                            <button class="dtr-btn-xs dtr-btn-outline" id="btn-wst-prev-page" style="padding:5px 12px;font-weight:600;border-radius:8px" disabled>&lsaquo; Prev</button>
                            <span class="muted sm" id="wst-pagination-info" style="min-width:48px;text-align:center;font-weight:600">1 / 1</span>
                            <button class="dtr-btn-xs dtr-btn-outline" id="btn-wst-next-page" style="padding:5px 12px;font-weight:600;border-radius:8px" disabled>Next &rsaquo;</button>
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:10px">
                    <!-- Category Filter -->
                    <select id="wst-filter-category" class="dtr-input" style="padding:7px 12px;font-size:12.5px;min-width:160px">
                        <option value="all">All Categories</option>
                        <option value="hydrolysis">Hydrolysis (70&deg;C x 95% RH)</option>
                        <option value="heat-aging">Heat Aging (70&deg;C)</option>
                        <option value="washing">Washing</option>
                        <option value="uv-light">UV Light Exposure</option>
                        <option value="flexing">Whole Shoe Flexing</option>
                        <option value="deeptest">Dip Test</option>
                    </select>

                    <!-- Outcome Filter -->
                    <select id="wst-filter-outcome" class="dtr-input" style="padding:7px 12px;font-size:12.5px;min-width:135px">
                        <option value="all">All Outcomes</option>
                        <option value="PASS">Passed (PASS)</option>
                        <option value="FAIL">Defect Found (FAIL)</option>
                        <option value="STOPPED">Halted / Stopped</option>
                        <option value="RUNNING">In Progress (Running)</option>
                        <option value="PENDING">Pending Start</option>
                    </select>

                    <!-- Status Filter -->
                    <select id="wst-filter-status" class="dtr-input" style="padding:7px 12px;font-size:12.5px;min-width:125px">
                        <option value="all">All Statuses</option>
                        <option value="COMPLETED">Completed</option>
                        <option value="RUNNING">Running</option>
                        <option value="PENDING">Pending</option>
                    </select>
                    <div style="display:flex;align-items:center;gap:8px;margin-left:auto">
                        <span class="muted sm" style="font-size:12px;font-weight:600">Date Range:</span>
                        <input type="date" id="wst-filter-from" class="dtr-input" style="padding:5px 8px;font-size:12px" title="From Date">
                        <span class="muted sm">to</span>
                        <input type="date" id="wst-filter-to" class="dtr-input" style="padding:5px 8px;font-size:12px" title="To Date">
                        <button type="button" id="btn-wst-reset-filter" class="btn btn-outline-secondary btn-sm" style="padding:5px 10px;font-size:12px;display:inline-flex;align-items:center;gap:4px" title="Reset all filters">
                            <svg class="ic" aria-hidden="true" style="width:12px;height:12px"><use href="#i-refresh"/></svg> Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="dtr-table-wrap">
                <table class="dtr-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">NO</th>
                            <th>LAB REFERENCE</th>
                            <th>TEST DATE</th>
                            <th>SPECIFICATION / MATERIAL</th>
                            <th>MACHINE / TECHNICIAN</th>
                            <th id="wst-th-progress">TIME / STATUS</th>
                            <th>STATUS</th>
                            <th style="text-align:right">ACTION</th>
                        </tr>
                    </thead>
                    <tbody id="wst-list-tbody">
                        <tr><td colspan="8" class="muted" style="text-align:center;padding:24px">Loading test sessions...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        </div><!-- /wst-cat-view -->
    </div>
</section>

<?= view('wholeshoe/partials/live_monitoring_modal') ?>
<?= view('wholeshoe/partials/stop_confirm_modal') ?>
<?= view('wholeshoe/partials/flexing_eval_modal') ?>
<?= view('wholeshoe/partials/findings_eval_modal') ?>
