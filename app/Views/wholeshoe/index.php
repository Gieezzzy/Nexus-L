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
        <div class="dtr-subtabs" role="tablist">
            <button type="button" role="tab" class="dtr-subtab active" data-wst-cat="hydrolysis"><svg class="ic" aria-hidden="true"><use href="#i-droplet"/></svg> Hydrolysis</button>
            <button type="button" role="tab" class="dtr-subtab" data-wst-cat="heat-aging"><svg class="ic" aria-hidden="true"><use href="#i-thermometer"/></svg> Heat Aging</button>
            <button type="button" role="tab" class="dtr-subtab" data-wst-cat="washing"><svg class="ic" aria-hidden="true"><use href="#i-washing-machine"/></svg> Washing</button>
            <button type="button" role="tab" class="dtr-subtab" data-wst-cat="uv-light"><svg class="ic" aria-hidden="true"><use href="#i-lamp"/></svg> UV Light</button>
            <button type="button" role="tab" class="dtr-subtab" data-wst-cat="flexing"><svg class="ic" aria-hidden="true"><use href="#i-shoe-flex"/></svg> Flexing</button>
            <button type="button" role="tab" class="dtr-subtab" data-wst-cat="deeptest"><svg class="ic" aria-hidden="true"><use href="#i-bucket"/></svg> Deep Test</button>
            <button type="button" class="dtr-btn dtr-btn-primary dtr-subtabs-right" id="btn-wst-open-new-modal" style="font-weight:600">
                <svg class="ic" aria-hidden="true"><use href="#i-plus"/></svg> + New Test
            </button>
        </div>

        <div class="card glass dtr-section-card">
                <div class="dtr-card-head" style="margin-bottom:14px">
                <!-- Row 1: Search Input + Category + Outcome + Status Dropdowns -->
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <div style="position:relative;display:flex;align-items:center;flex:1;min-width:280px">
                        <svg style="position:absolute;left:11px;width:15px;height:15px;color:#94a3b8;pointer-events:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" id="wst-list-search" class="dtr-input" placeholder="Search Lab Reference, Technician, Operator, Specification, Machine..." style="width:100%;padding:8px 14px 8px 34px">
                    </div>

                    <!-- Category Filter -->
                    <select id="wst-filter-category" class="dtr-input" style="padding:7px 12px;font-size:12.5px;min-width:160px">
                        <option value="all">All Categories</option>
                        <option value="hydrolysis">Hydrolysis (70&deg;C x 95% RH)</option>
                        <option value="heat-aging">Heat Aging (70&deg;C)</option>
                        <option value="washing">Washing</option>
                        <option value="uv-light">UV Light Exposure</option>
                        <option value="flexing">Whole Shoe Flexing</option>
                        <option value="deeptest">Deep Test</option>
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
                </div>

                <!-- Row 2: Showing X record(s) on Left, Date Range + Reset on Right -->
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-top:10px;padding-top:10px;border-top:1px solid rgba(148,163,184,0.15)">
                    <span class="muted sm" id="wst-record-count">Showing 0 record(s)</span>
                    <div style="display:flex;align-items:center;gap:8px">
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
                            <th>CYCLE PROGRESS</th>
                            <th>STATUS</th>
                            <th style="text-align:right">ACTION</th>
                        </tr>
                    </thead>
                    <tbody id="wst-list-tbody">
                        <tr><td colspan="8" class="muted" style="text-align:center;padding:24px">Loading test sessions...</td></tr>
                    </tbody>
                </table>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding-top:10px;border-top:1px solid rgba(148,163,184,0.18)">
                <span class="muted sm" id="wst-pagination-info">Showing active records</span>
                <div style="display:flex;gap:6px">
                    <button class="dtr-btn-xs dtr-btn-outline" id="btn-wst-prev-page" disabled>&lt;</button>
                    <button class="dtr-btn-xs dtr-btn-outline" id="btn-wst-next-page" disabled>&gt;</button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =======================================================================
     POPUP MODAL: WHOLE SHOE TEST LIVE MONITORING (LMS PARITY POPUP)
     ======================================================================= -->
<div id="wst-monitor-modal-backdrop" class="dtr-modal-backdrop hidden">
    <div class="dtr-modal-dialog wst-theme-modal" style="max-width:1380px;width:97%">
        <!-- Modal Header: Crisp Hierarchy & Pulsing Dot Indicator -->
        <div class="dtr-modal-header" style="padding:16px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="display:flex;align-items:center;gap:10px">
                    <h3 id="wst-modal-title" style="margin:0;font-size:17px;font-weight:700;color:#0f172a;letter-spacing:-0.01em">WHOLE SHOE FLEXING MONITORING</h3>
                    <span class="dtr-pill dtr-pill-pass" id="wst-modal-status-badge" style="display:inline-flex;align-items:center;font-size:11px;font-weight:700;padding:3px 10px">
                        <span class="wst-pulse-dot wst-pulse-dot-green" id="wst-modal-pulse-dot"></span>
                        <span id="wst-modal-status-text">RUNNING</span>
                    </span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:3px">
                    <span class="mono sm text-slate-500" style="color:#64748b;font-size:12px">Test ID:</span>
                    <span class="mono bold" id="wst-modal-labref-badge" style="color:#0f172a;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:1px 7px;font-size:12px">DTP20261028WSF01</span>
                </div>
            </div>
            <button type="button" id="btn-wst-close-monitor-modal" class="dtr-modal-close" title="Close Monitor" style="font-size:22px">&times;</button>
        </div>

        <div class="dtr-modal-body" style="padding:22px 24px;max-height:calc(90vh - 120px);overflow-y:auto;background:#ffffff">
            <!-- Modal Grid: Monitor View on Left, Actions on Right -->
            <div class="wst-details-grid" style="display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:20px;align-items:start">
                <div>
                    <!-- Read-Only Key-Value Stat Cards (Modern Telemetry Cards) -->
                    <div class="dtr-grid-4" style="gap:12px;margin-bottom:18px">
                        <div class="wst-meta-card">
                            <span class="wst-meta-label">Lab Reference</span>
                            <span class="wst-meta-val mono" id="wst-card-dt-labref" style="color:var(--brand,#2563eb)">DTP20261028WSF01</span>
                        </div>
                        <div class="wst-meta-card">
                            <span class="wst-meta-label">Test Date</span>
                            <span class="wst-meta-val" id="wst-card-dt-date">2026-10-28</span>
                        </div>
                        <div class="wst-meta-card">
                            <span class="wst-meta-label">Samples Configured</span>
                            <span class="wst-meta-val" id="wst-card-dt-samples">4D / 4W / 0H</span>
                        </div>
                        <div class="wst-meta-card">
                            <span class="wst-meta-label">Material / Type</span>
                            <span class="wst-meta-val" id="wst-card-dt-type" title="Standard Whole Shoe Flexing">Standard Whole Shoe Flexing</span>
                        </div>
                    </div>

                    <!-- Overall Completion Progress Bar -->
                    <div style="margin-bottom:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                            <span style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#475569">
                                Overall Test Progress (<span id="wst-progress-cycles">0</span> / <span id="wst-progress-target">100,000</span> cycles)
                            </span>
                            <span class="mono bold" id="wst-progress-pct" style="font-size:12px;color:var(--brand,#2563eb)">0%</span>
                        </div>
                        <div class="wst-progress-rail">
                            <div class="wst-progress-fill" id="wst-progress-bar-fill" style="width:0%"></div>
                        </div>
                    </div>

                    <!-- Monitoring Sub-tabs (Dry / Wet / Hydro) -->
                    <div class="wst-monitoring-container">
                        <div class="wst-group-tabs" role="tablist">
                            <button type="button" class="wst-gt-btn active wst-modal-gt-btn" data-modal-gt="dry">DRY SAMPLES</button>
                            <button type="button" class="wst-gt-btn wst-modal-gt-btn" data-modal-gt="wet">WET SAMPLES</button>
                        </div>

                        <!-- Monitoring Cycle Table -->
                        <div class="dtr-table-wrap" style="overflow-x:auto;margin-top:10px;border-radius:8px;border:1px solid #e2e8f0;background:#ffffff">
                            <table class="dtr-table wst-cycle-table">
                                <thead>
                                    <tr>
                                        <th style="min-width:110px">SPECIMEN</th>
                                        <th>CYCLES</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="2" class="muted" style="text-align:center;padding:16px">Loading specimen monitoring grid...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Collapsible Drawer: Environmental & Station Specs -->
                    <details style="margin-top:16px;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;background:#f8fafc">
                        <summary style="cursor:pointer;font-weight:600;font-size:12.5px;color:var(--brand,#2563eb);user-select:none;display:flex;align-items:center;gap:6px">
                            <span>&#9660;</span> View Full Test Parameter Specs &amp; Environmental Conditions
                        </summary>
                        <div style="margin-top:12px;padding-top:10px;border-top:1px solid #e2e8f0" class="dtr-grid-3">
                            <div><small class="wst-meta-label">Technician:</small> <div class="mono bold" id="wst-view-op" style="font-size:13px;color:#0f172a">Lab QC Specialist</div></div>
                            <div><small class="wst-meta-label">Machine Station:</small> <div class="mono bold" id="wst-view-mc" style="font-size:13px;color:#0f172a">MC-FLEX-01</div></div>
                            <div><small class="wst-meta-label">Ambient Temp:</small> <div class="mono bold" id="wst-view-temp" style="font-size:13px;color:#0f172a">23.0 &deg;C</div></div>
                            <div><small class="wst-meta-label">Ambient Humidity:</small> <div class="mono bold" id="wst-view-hum" style="font-size:13px;color:#0f172a">50.0 %RH</div></div>
                            <div><small class="wst-meta-label">Standard Spec:</small> <div class="mono bold" style="font-size:13px;color:#0f172a">&ge; 100,000 cycles without cracking</div></div>
                            <div><small class="wst-meta-label">Status Note:</small> <div class="mono bold" style="font-size:13px;color:#16a34a" id="wst-view-note">In Progress</div></div>
                        </div>
                    </details>
                </div>

                <!-- Right Action Rail: State-Aware Controls & Distinct Safety Actions -->
                <div class="wst-actions-card" style="padding:16px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc">
                    <h4 style="margin:0 0 12px;font-size:11px;letter-spacing:0.06em;text-transform:uppercase;color:#64748b;font-weight:700;border-bottom:1px solid #e2e8f0;padding-bottom:8px">
                        CONTROL ACTIONS
                    </h4>
                    <div class="wst-actions-rail">
                        <!-- State-Aware Primary CTA: Run vs Paused vs Start -->
                        <div id="wst-primary-action-wrap">
                            <button type="button" class="dtr-btn dtr-btn-primary" id="btn-wst-modal-start" style="width:100%;background:#16a34a;border-color:#16a34a;justify-content:center;font-weight:700">
                                <svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> RUN / START
                            </button>
                            <div id="wst-running-indicator-bar" class="dtr-btn wst-btn-running-state hidden" style="width:100%">
                                <span class="wst-pulse-dot wst-pulse-dot-green"></span>
                                <span>Running</span>
                                <span class="wst-runtime-timer" id="wst-modal-runtime-clock">Active</span>
                            </div>
                        </div>

                        <!-- Secondary Operational Actions -->
                        <button type="button" class="dtr-btn wst-btn-secondary" id="btn-wst-modal-findings" style="width:100%">
                            <svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Log Findings
                        </button>
                        <button type="button" class="dtr-btn wst-btn-secondary" id="btn-wst-modal-stop-sample" style="width:100%">
                            <svg class="ic" aria-hidden="true"><use href="#i-layers"/></svg> Stop Sample...
                        </button>

                        <hr style="border:none;border-top:1px solid #e2e8f0;margin:6px 0">

                        <!-- Destructive / Safety Action (Red Outline with Distinct Separation) -->
                        <button type="button" class="dtr-btn wst-btn-danger-outline" id="btn-wst-modal-stop" style="width:100%">
                            <svg class="ic" aria-hidden="true"><use href="#i-close"/></svg> Stop Entire Test
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer: Clear Breadcrumbs & Background Execution Caption -->
        <div class="dtr-modal-footer" style="padding:12px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
            <span class="muted sm" style="color:#64748b;font-size:12px">Whole Shoe Monitoring System &bull; dbnexus</span>
            <div style="display:flex;align-items:center;gap:12px">
                <span class="muted sm" style="color:#94a3b8;font-size:11.5px">Runs in background while closed</span>
                <button type="button" id="btn-wst-cancel-monitor-modal" class="dtr-btn wst-btn-secondary" style="padding:6px 18px">Close Monitor</button>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================================
     POPUP MODAL: LOG FINDINGS DIALOG (LMS PARITY MODAL)
     ======================================================================= -->
<div id="wst-findings-modal-backdrop" class="dtr-modal-backdrop hidden" style="z-index:10050">
    <div class="dtr-modal-dialog wst-theme-modal" style="max-width:760px;width:94%">
        <div class="dtr-modal-header">
            <div style="display:flex;align-items:center;gap:10px">
                <span class="dtr-pill dtr-pill-blue">LOG FINDINGS</span>
                <h3 style="margin:0;font-size:16px;font-weight:700">Record Checkpoint Observation</h3>
            </div>
            <button type="button" id="btn-wst-close-findings-modal" class="dtr-modal-close">&times;</button>
        </div>
        <div class="dtr-modal-body" style="padding:22px 24px">
            <div class="dtr-grid-2" style="gap:14px;margin-bottom:14px">
                <div class="dtr-field">
                    <label>Active Specimen Group</label>
                    <input type="text" id="wst-findings-group-display" class="bold mono" value="DRY SAMPLES" readonly style="color:var(--brand,#2563eb);background:#f8fafc">
                </div>
                <div class="dtr-field">
                    <label>Checkpoint Cycle</label>
                    <select id="wst-findings-cycle">
                        <option value="25000">25,000 Cycles</option>
                        <option value="35000">35,000 Cycles</option>
                        <option value="45000">45,000 Cycles</option>
                        <option value="55000">55,000 Cycles</option>
                        <option value="65000">65,000 Cycles</option>
                        <option value="75000">75,000 Cycles</option>
                        <option value="100000">100,000 Cycles</option>
                    </select>
                </div>
            </div>

            <!-- LMS Specimen Range selector -->
            <div class="dtr-subcard" style="margin-bottom:14px;padding:12px 14px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc">
                <div class="lf-range-presets" id="wst-findings-presets" style="margin-bottom:8px"></div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="muted sm" style="font-weight:600">Specimens:</span>
                    <input type="number" id="wst-findings-start" min="1" max="20" value="1" style="width:65px;padding:5px 8px;text-align:center">
                    <span class="muted sm" style="font-weight:600">to through:</span>
                    <input type="number" id="wst-findings-end" min="1" max="20" value="4" style="width:65px;padding:5px 8px;text-align:center">
                    <span class="muted sm" id="wst-findings-range-info" style="color:var(--brand,#2563eb);margin-left:auto;font-weight:600">4 selected</span>
                </div>
                <p class="lf-help" style="margin:8px 0 0 0;font-size:11.5px;color:#64748b">Choosing a later cycle also records all earlier checkpoints for the selected specimens.</p>
            </div>

            <div class="dtr-field" style="margin-bottom:14px">
                <label>Observation / Defect Assessment</label>
                <textarea class="dtr-input" id="wst-findings-text" rows="3" placeholder="Enter findings (e.g. No peeling, slight wrinkle on vamp fold, acceptable)...">No peeling or delamination observed. Vamp flex crease normal.</textarea>
            </div>
            <div style="display:flex;gap:10px;align-items:center">
                <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer">
                    <input type="checkbox" checked id="wst-findings-pass" style="width:16px;height:16px"> Pass Checkpoint Criteria (No Delamination)
                </label>
            </div>
        </div>
        <div class="dtr-modal-footer">
            <button type="button" class="dtr-btn dtr-btn-outline" id="btn-wst-cancel-findings" style="padding:8px 18px">Cancel</button>
            <button type="button" class="dtr-btn dtr-btn-primary" id="btn-wst-save-findings" style="padding:8px 22px;font-weight:700">
                <svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Save Checkpoint Findings
            </button>
        </div>
    </div>
</div>

<!-- =======================================================================
     POPUP MODAL: STOP TEST CONFIRMATION MODAL (LMS PARITY SAFEGUARD)
     ======================================================================= -->
<div id="wst-stop-test-modal-backdrop" class="dtr-modal-backdrop hidden" style="z-index:10070">
    <div class="dtr-modal-dialog wst-theme-modal" style="max-width:480px;width:92%">
        <div class="dtr-modal-header" style="border-bottom:1px solid #fee2e2">
            <div style="display:flex;align-items:center;gap:10px">
                <span class="dtr-pill dtr-pill-fail" style="font-weight:700">EMERGENCY STOP</span>
                <h3 style="margin:0;font-size:15px;font-weight:700;color:#991b1b">Stop Entire Test Session</h3>
            </div>
            <button type="button" id="btn-wst-close-stop-test" class="dtr-modal-close">&times;</button>
        </div>
        <div class="dtr-modal-body" style="padding:20px">
            <p style="font-size:13.5px;color:#334155;line-height:1.5;margin:0 0 14px">
                Are you sure you want to stop this test session?
            </p>
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:12px;font-size:12.5px;color:#991b1b;margin-bottom:12px">
                <strong>Warning:</strong> All running specimens will be halted and the test status will immediately be marked as <strong>COMPLETED (STOPPED)</strong>. Further automatic cyclic telemetry will cease.
            </div>
        </div>
        <div class="dtr-modal-footer" style="padding:12px 20px;display:flex;justify-content:flex-end;gap:10px">
            <button type="button" class="dtr-btn wst-btn-secondary" id="btn-wst-cancel-stop-test" style="padding:7px 18px">Cancel</button>
            <button type="button" class="dtr-btn wst-btn-danger-outline" id="btn-wst-confirm-stop-test" style="background:#dc2626 !important;color:#ffffff !important;border-color:#dc2626 !important;padding:7px 20px;font-weight:700">
                <svg class="ic" aria-hidden="true"><use href="#i-close"/></svg> Stop Entire Test
            </button>
        </div>
    </div>
</div>

<!-- =======================================================================
     POPUP MODAL: STOP SAMPLE SELECTOR (LMS PARITY MODAL)
     ======================================================================= -->
<div id="wst-stop-sample-modal-backdrop" class="dtr-modal-backdrop hidden" style="z-index:10060">
    <div class="dtr-modal-dialog wst-theme-modal" style="max-width:520px;width:90%">
        <div class="dtr-modal-header">
            <div style="display:flex;align-items:center;gap:10px">
                <span class="dtr-pill dtr-pill-warn" style="font-weight:700">STOP SPECIMEN</span>
                <h3 style="margin:0;font-size:15px;font-weight:700">Select Specimens to Stop</h3>
            </div>
            <button type="button" id="btn-wst-close-stop-sample" class="dtr-modal-close">&times;</button>
        </div>
        <div class="dtr-modal-body" style="padding:18px 20px">
            <p class="muted sm" style="margin-bottom:12px">
                Select individual specimen(s) to halt due to damage or early test completion.
            </p>
            <div id="wst-stop-sample-list" style="display:flex;flex-direction:column;gap:8px;max-height:240px;overflow-y:auto;padding-right:2px">
                <!-- Dynamically filled with specimen items -->
            </div>
        </div>
        <div class="dtr-modal-footer">
            <button type="button" class="dtr-btn dtr-btn-outline" id="btn-wst-cancel-stop-sample" style="padding:8px 18px">Cancel</button>
            <button type="button" class="dtr-btn dtr-btn-outline" id="btn-wst-confirm-stop-sample" style="border-color:#d97706;color:#d97706;padding:8px 20px;font-weight:700">
                <svg class="ic" aria-hidden="true"><use href="#i-close"/></svg> Stop Selected Specimen(s)
            </button>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="dtr-toast" class="dtr-toast hidden"></div>
