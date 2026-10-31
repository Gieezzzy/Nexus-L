<?php
/**
 * NEXUS-L Combined Test Results Summary
 * Executive Consolidated Results, Technician Verification & Specimen Checkpoints for Whole Shoe & Material Testing suites.
 */
?>

<style>
/* ==========================================================================
   Test Results Summary - Executive Clean Layout (Whole Shoe / Material Parity)
   ========================================================================== */
#summary-panel {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 30px;
}

@media (max-width: 1200px) {
    .wst-kpi-strip { grid-template-columns: repeat(3, 1fr) !important; }
}
@media (max-width: 768px) {
    .wst-kpi-strip { grid-template-columns: repeat(2, 1fr) !important; }
}
@media (max-width: 480px) {
    .wst-kpi-strip { grid-template-columns: 1fr !important; }
}

/* Custom Badges & Pills */
.sum-suite-wholeshoe {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    background: #f0f9ff;
    color: #0369a1;
    border: 1px solid #bae6fd;
}
.sum-suite-material {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    background: #faf5ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
}

.sum-pill-outcome {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}
.sum-outcome-pass {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #86efac;
}
.sum-outcome-fail {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}
.sum-outcome-stopped {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fcd34d;
}
.sum-outcome-running {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #7dd3fc;
}
.sum-outcome-pending {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #cbd5e1;
}

/* Progress bar inside table */
.sum-progress-bar-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 140px;
}
.sum-progress-track {
    width: 100%;
    height: 6px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}
.sum-progress-fill {
    height: 100%;
    border-radius: 999px;
    transition: width .3s ease;
}
.sum-progress-fill.done { background: #22c55e; }
.sum-progress-fill.run  { background: #0284c7; }
.sum-progress-fill.fail { background: #ef4444; }
.sum-progress-fill.stop { background: #f59e0b; }

/* Technician Tag */
.sum-tech-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11.5px;
    font-weight: 600;
    color: #1d4ed8;
}
.sum-tech-tag svg {
    width: 12px;
    height: 12px;
    color: #3b82f6;
}

/* Pulse Dot */
.sum-pulse-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
    animation: sumPulse 1.8s infinite;
}
@keyframes sumPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(34, 197, 94, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

.sum-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 13px;
    font-size: 12.5px;
    font-weight: 600;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    cursor: pointer;
    transition: all .15s ease;
}
.sum-btn-action:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}
.sum-btn-action.primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
}
.sum-btn-action.primary:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
}
</style>

<section id="summary-panel" class="dtr-panel">
    <!-- Top Sub-Nav & Category / Suite Selection (Matching Whole Shoe Page) -->
    <div class="card glass dtr-section-card" style="margin-bottom:16px">
        <div class="wst-header-banner">
            <div class="wst-banner-left">
                <span class="wst-badge">LABORATORY TEST SUMMARY &amp; ARCHIVE</span>
                <h3 style="margin-top:4px">Test Results Summary</h3>
                <p class="muted sm">Consolidated performance monitoring, checkpoint specimen evaluations, and verified technician sign-offs across <b>Whole Shoe Testing</b> (Hydrolysis, Heat Aging, Washing, UV Light, Flexing, Deep Test) and <b>Material Testing</b> suites.</p>
            </div>
            <div class="wst-banner-actions" style="display:flex;align-items:center;gap:10px">
                <button type="button" id="btn-export-summary-csv" class="sum-btn-action">
                    <svg class="ic" style="width:14px;height:14px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                    Export CSV
                </button>
                <button type="button" id="btn-print-summary" class="sum-btn-action">
                    <svg class="ic" style="width:14px;height:14px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-12 0v5h12v-5"/></svg>
                    Print Summary
                </button>
            </div>
        </div>

        <!-- Suite Switcher Tabs -->
        <div class="wst-category-tabs" role="tablist">
            <button type="button" class="wst-cat-tab active summary-suite-pill" data-suite="all">
                All Test Suites (<span id="summary-pill-cnt-all">0</span>)
            </button>
            <button type="button" class="wst-cat-tab summary-suite-pill" data-suite="wholeshoe">
                Whole Shoe Tests (<span id="summary-pill-cnt-wholeshoe">0</span>)
            </button>
            <button type="button" class="wst-cat-tab summary-suite-pill" data-suite="material">
                Material Tests (<span id="summary-pill-cnt-material">0</span>)
            </button>
        </div>
    </div>

    <!-- Quick KPI Summary Strip (Exact Structure & Classes as Whole Shoe Page) -->
    <div class="wst-kpi-strip" style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:14px">
        <!-- Total Evaluated -->
        <div class="card glass wst-kpi-card" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between">
            <div>
                <small class="muted uppercase" style="font-size:11px;font-weight:700">Total Evaluated</small>
                <b id="summary-kpi-total" style="display:block;font-size:22px;color:#0f172a;margin-top:2px">0</b>
            </div>
            <div class="wst-kpi-ico" style="background:rgba(59,130,246,0.12);color:#2563eb;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center">
                <svg class="ic" aria-hidden="true"><use href="#i-grid"/></svg>
            </div>
        </div>

        <!-- Passed Tests -->
        <div class="card glass wst-kpi-card" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between">
            <div>
                <small class="muted uppercase" style="font-size:11px;font-weight:700">Passed Tests</small>
                <div style="display:flex;align-items:center;gap:6px;margin-top:2px">
                    <b id="summary-kpi-pass" style="font-size:22px;color:#22c55e">0</b>
                    <span id="summary-kpi-passrate" class="sum-pill-outcome sum-outcome-pass" style="font-size:10px;padding:1px 6px">0%</span>
                </div>
            </div>
            <div class="wst-kpi-ico" style="background:rgba(34,197,94,0.15);color:#22c55e;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center">
                <svg class="ic" aria-hidden="true"><use href="#i-check"/></svg>
            </div>
        </div>

        <!-- Active Running -->
        <div class="card glass wst-kpi-card" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between">
            <div>
                <small class="muted uppercase" style="font-size:11px;font-weight:700">Active Running</small>
                <b id="summary-kpi-running" style="display:block;font-size:22px;color:#38bdf8;margin-top:2px">0</b>
            </div>
            <div class="wst-kpi-ico" style="background:rgba(56,189,248,0.15);color:#38bdf8;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center">
                <svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg>
            </div>
        </div>

        <!-- Defects / Failed -->
        <div class="card glass wst-kpi-card" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between">
            <div>
                <small class="muted uppercase" style="font-size:11px;font-weight:700">Defects / Failed</small>
                <b id="summary-kpi-fail" style="display:block;font-size:22px;color:#ef4444;margin-top:2px">0</b>
            </div>
            <div class="wst-kpi-ico" style="background:rgba(239,68,68,0.15);color:#ef4444;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center">
                <svg class="ic" aria-hidden="true"><use href="#i-close"/></svg>
            </div>
        </div>

        <!-- Stopped / Halted -->
        <div class="card glass wst-kpi-card" style="padding:14px 18px;display:flex;align-items:center;justify-content:space-between">
            <div>
                <small class="muted uppercase" style="font-size:11px;font-weight:700">Stopped / Halted</small>
                <b id="summary-kpi-stopped" style="display:block;font-size:22px;color:#f59e0b;margin-top:2px">0</b>
            </div>
            <div class="wst-kpi-ico" style="background:rgba(245,158,11,0.15);color:#f59e0b;width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center">
                <svg class="ic" aria-hidden="true"><use href="#i-clock"/></svg>
            </div>
        </div>
    </div>

    <!-- Filter & Results Main Card -->
    <div class="card glass dtr-section-card">
        <div class="dtr-card-head" style="margin-bottom:14px">
            <!-- Row 1: Search Input + Category + Outcome + Status Dropdowns -->
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <!-- Search Input with Search Icon -->
                <div style="position:relative;display:flex;align-items:center;flex:1;min-width:280px">
                    <svg style="position:absolute;left:11px;width:15px;height:15px;color:#94a3b8;pointer-events:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" id="summary-search" class="dtr-input" placeholder="Search Lab Reference, Technician, Operator, Specification, Machine..." style="width:100%;padding:8px 14px 8px 34px">
                </div>

                <!-- Category Filter -->
                <select id="summary-filter-category" class="dtr-input" style="padding:7px 12px;font-size:12.5px;min-width:180px">
                    <option value="all">All Categories</option>
                    <optgroup label="Whole Shoe Tests">
                        <option value="hydrolysis">Hydrolysis (70&deg;C x 95% RH)</option>
                        <option value="heat-aging">Heat Aging (70&deg;C)</option>
                        <option value="washing">Washing</option>
                        <option value="uv-light">UV Light Exposure</option>
                        <option value="flexing">Whole Shoe Flexing</option>
                        <option value="deeptest">Deep Test</option>
                    </optgroup>
                    <optgroup label="Material Tests">
                        <option value="martindale-abrasion">Martindale Abrasion</option>
                        <option value="taber-abrasion">Taber Abrasion</option>
                        <option value="lace-abrasion">Lace Abrasion</option>
                        <option value="crocking">Crocking / Color Fastness</option>
                        <option value="ross-flex">Ross Flexing</option>
                    </optgroup>
                </select>

                <!-- Outcome Filter -->
                <select id="summary-filter-outcome" class="dtr-input" style="padding:7px 12px;font-size:12.5px;min-width:145px">
                    <option value="all">All Outcomes</option>
                    <option value="PASS">Passed (PASS)</option>
                    <option value="FAIL">Defect Found (FAIL)</option>
                    <option value="STOPPED">Halted / Stopped</option>
                    <option value="RUNNING">In Progress (Running)</option>
                    <option value="PENDING">Pending Start</option>
                </select>

                <!-- Status Filter -->
                <select id="summary-filter-status" class="dtr-input" style="padding:7px 12px;font-size:12.5px;min-width:135px">
                    <option value="all">All Statuses</option>
                    <option value="COMPLETED">Completed</option>
                    <option value="RUNNING">Running</option>
                    <option value="PENDING">Pending</option>
                </select>
            </div>

            <!-- Row 2: Showing X record(s) on Left, Date Range + Reset on Right -->
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-top:10px;padding-top:10px;border-top:1px solid rgba(148,163,184,0.15)">
                <span class="muted sm" id="summary-record-count">Showing 0 record(s)</span>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="muted sm" style="font-size:12px;font-weight:600">Date Range:</span>
                    <input type="date" id="summary-filter-from" class="dtr-input" style="padding:5px 8px;font-size:12px" title="From Date">
                    <span class="muted sm">to</span>
                    <input type="date" id="summary-filter-to" class="dtr-input" style="padding:5px 8px;font-size:12px" title="To Date">
                    <button type="button" id="btn-summary-reset" class="btn btn-outline-secondary btn-sm" style="padding:5px 10px;font-size:12px;display:inline-flex;align-items:center;gap:4px" title="Reset all filters">
                        <svg class="ic" aria-hidden="true" style="width:12px;height:12px"><use href="#i-refresh"/></svg> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Table Wrap -->
        <div class="dtr-table-wrap">
            <table class="dtr-table" id="summary-table">
                <thead>
                    <tr>
                        <th style="width:45px;text-align:center">#</th>
                        <th style="min-width:160px">LAB REFERENCE</th>
                        <th style="min-width:115px">SUITE</th>
                        <th style="min-width:145px">CATEGORY</th>
                        <th style="min-width:165px">SPECIFICATION / MATERIAL</th>
                        <th style="min-width:175px">MACHINE &amp; TECHNICIAN</th>
                        <th style="min-width:165px">PROGRESS / DURATION</th>
                        <th style="min-width:110px">OUTCOME</th>
                        <th style="min-width:95px">TEST DATE</th>
                        <th style="min-width:115px;text-align:right">ACTION</th>
                    </tr>
                </thead>
                <tbody id="summary-table-tbody">
                    <tr>
                        <td colspan="10" style="text-align:center;padding:36px;color:#64748b">
                            <div style="display:flex;align-items:center;justify-content:center;gap:8px">
                                <span class="sum-pulse-dot"></span>
                                <span>Loading consolidated test records...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Table Footer -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;padding-top:10px;border-top:1px solid #f1f5f9;font-size:12px;color:#64748b">
            <span id="summary-pagination-info">Showing active test records</span>
            <span class="muted sm">Displaying up to <b>100</b> recent sessions</span>
        </div>
    </div>
</section>

<!-- =======================================================================
     MODAL: DETAILED TEST RESULT & SPECIMEN CHECKPOINTS
     ======================================================================= -->
<div id="summary-detail-modal-backdrop" class="dtr-modal-backdrop hidden">
    <div class="dtr-modal-dialog wst-theme-modal" style="max-width:1050px;width:95%">
        <!-- Modal Header -->
        <div class="dtr-modal-header" style="padding:16px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="display:flex;align-items:center;gap:10px">
                    <h3 id="modal-summary-labref" style="margin:0;font-size:18px;font-weight:700;color:#0f172a;letter-spacing:-0.01em">DTP20261030HYD</h3>
                    <span id="modal-summary-suite-pill" class="sum-suite-wholeshoe" style="font-size:11px">Whole Shoe</span>
                    <span id="modal-summary-outcome-pill" class="sum-pill-outcome sum-outcome-pass" style="font-size:11px">PASS</span>
                </div>
                <small class="muted" id="modal-summary-subtitle" style="font-size:12px">Hydrolysis Test &bull; Standard Hydrolysis (70&deg;C x 95% RH)</small>
            </div>
            <button type="button" class="dtr-modal-close" id="btn-close-summary-modal" style="background:none;border:none;font-size:24px;cursor:pointer;color:#64748b">&times;</button>
        </div>

        <!-- Modal Body -->
        <div class="dtr-modal-body" style="padding:20px 24px;max-height:75vh;overflow-y:auto">
            <!-- Test Meta Information Grid -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;margin-bottom:20px;background:#f8fafc;padding:14px 18px;border-radius:10px;border:1px solid #e2e8f0">
                <div>
                    <small class="muted uppercase" style="font-size:10px;font-weight:700">Test Category</small>
                    <b id="modal-meta-category" style="display:block;font-size:13px;color:#0f172a;margin-top:2px">&mdash;</b>
                </div>
                <div>
                    <small class="muted uppercase" style="font-size:10px;font-weight:700">Specification / Material</small>
                    <b id="modal-meta-type" style="display:block;font-size:13px;color:#0f172a;margin-top:2px">&mdash;</b>
                </div>
                <div>
                    <small class="muted uppercase" style="font-size:10px;font-weight:700">Assigned Machine</small>
                    <b id="modal-meta-machine" style="display:block;font-size:13px;color:#0f172a;margin-top:2px">&mdash;</b>
                </div>
                <div>
                    <small class="muted uppercase" style="font-size:10px;font-weight:700;color:#1d4ed8">Completion Technician</small>
                    <b id="modal-meta-technician" style="display:block;font-size:13px;color:#1d4ed8;margin-top:2px">&mdash;</b>
                </div>
                <div>
                    <small class="muted uppercase" style="font-size:10px;font-weight:700">Test Date</small>
                    <span id="modal-meta-date" style="display:block;font-size:13px;font-family:monospace;margin-top:2px">&mdash;</b>
                </div>
                <div>
                    <small class="muted uppercase" style="font-size:10px;font-weight:700">Progress Completed</small>
                    <b id="modal-meta-progress" style="display:block;font-size:13px;color:#0f172a;margin-top:2px">&mdash;</b>
                </div>
                <div>
                    <small class="muted uppercase" style="font-size:10px;font-weight:700">Sample Configuration</small>
                    <span id="modal-meta-samples" style="display:block;font-size:13px;margin-top:2px">&mdash;</span>
                </div>
                <div>
                    <small class="muted uppercase" style="font-size:10px;font-weight:700">Created / Updated</small>
                    <span id="modal-meta-timestamps" style="display:block;font-size:11px;color:#64748b;margin-top:2px">&mdash;</span>
                </div>
            </div>

            <!-- General Remarks Banner if available -->
            <div id="modal-remarks-container" style="display:none;margin-bottom:16px;padding:10px 14px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px">
                <small class="muted uppercase" style="font-size:10px;font-weight:700;color:#92400e">Test Session Remarks</small>
                <div id="modal-remarks-text" style="font-size:13px;color:#78350f;margin-top:2px"></div>
            </div>

            <!-- Specimen Evaluation Table -->
            <div style="margin-top:12px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <h4 style="margin:0;font-size:13.5px;font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:.04em">Specimen Summary &amp; Checkpoints</h4>
                    <span class="muted sm" id="modal-findings-count">0 specimen sample(s)</span>
                </div>

                <div class="dtr-table-wrap" style="max-height:360px">
                    <table class="dtr-table">
                        <thead>
                            <tr>
                                <th style="width:75px">SAMPLE</th>
                                <th style="width:110px">GROUP</th>
                                <th style="min-width:140px">RECORDED CYCLES</th>
                                <th>REMARKS / FINDINGS</th>
                                <th style="width:90px;text-align:center">RESULT</th>
                                <th style="width:150px">RECORDED BY</th>
                                <th style="width:130px">TIMESTAMP</th>
                            </tr>
                        </thead>
                        <tbody id="modal-findings-tbody">
                            <tr><td colspan="7" class="muted" style="text-align:center;padding:18px">Loading specimen findings...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="dtr-modal-footer" style="padding:14px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc">
            <div>
                <a id="modal-btn-open-workspace" href="#" class="sum-btn-action primary" style="text-decoration:none">
                    <svg style="width:14px;height:14px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    Open Live Monitor Workspace
                </a>
            </div>
            <button type="button" class="sum-btn-action" id="btn-close-summary-modal-footer">Close</button>
        </div>
    </div>
</div>
