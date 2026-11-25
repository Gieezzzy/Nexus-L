<?php /* DTR partial: Print preview, findings, stop-test, Synology and e-mail modals. Included by dtr/index.php, shares its variables. */ ?>
<!-- =======================================================================
     PRINT PREVIEW / PDF REPORT MODAL (ACCESS PARITY)
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

                        <hr style="border:none;border-top:1px solid #e2e8f0;margin:6px 0">

                        <!-- Report Action -->
                        <button type="button" class="dtr-btn wst-btn-secondary" id="btn-wst-modal-print" style="width:100%">
                            <svg class="ic" aria-hidden="true"><use href="#i-printer"/></svg> Print Summary
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

<!-- =======================================================================
     PRINT PREVIEW / PDF REPORT MODAL (ACCESS PARITY)
     ======================================================================= -->
<div id="dtr-print-modal-backdrop" class="dtr-modal-backdrop hidden">
    <div class="dtr-modal-dialog">
        <div class="dtr-modal-header">
            <h3 id="dtr-print-modal-title">Official Laboratory Test Report Preview</h3>
            <button type="button" id="btn-dtr-close-print-modal" class="dtr-btn-xs" style="background:transparent;color:#fff;border:none;font-size:18px;cursor:pointer">&times;</button>
        </div>
        <div class="dtr-modal-body">
            <div class="dtr-a4-paper">
                <div id="dtr-print-sheet-container">
                    <!-- Dynamically rendered printable report sheet -->
                </div>
            </div>
            <!-- page 2: the report picture of this Lab Ref (the one shown in the Lab Tracker), Shoe Bonding (U60) only -->
            <div class="dtr-a4-paper dtr-a4-page2 hidden" id="dtr-print-page2">
                <div class="dtr-page2-cap" id="dtr-print-page2-cap"></div>
                <img id="dtr-print-page2-img" alt="Report picture">
            </div>
        </div>
        <div class="dtr-modal-footer">
            <button type="button" id="btn-dtr-cancel-print" class="dtr-btn dtr-btn-outline">Close</button>
                        <a id="btn-dtr-download-access-pdf" href="#" target="_blank" class="dtr-btn dtr-btn-outline hidden" style="border-color:#16a34a;color:#16a34a;text-decoration:none">
                <svg class="ic" aria-hidden="true"><use href="#i-download"/></svg> Download Official PDF
            </a>
            <button type="button" id="btn-dtr-download-pdf" class="dtr-btn dtr-btn-outline" style="border-color:#2563eb;color:#2563eb">
                <svg class="ic" aria-hidden="true"><use href="#i-file"/></svg> Download PDF
            </button>
            <button type="button" id="btn-dtr-do-print" class="dtr-btn dtr-btn-primary">
                <svg class="ic" aria-hidden="true"><use href="#i-printer"/></svg> Print Report / Save as PDF
            </button>
        </div>
    </div>
</div>

<!-- =======================================================================
     SYNOLOGY SEND REPORT MODAL
     ======================================================================= -->
<div id="dtr-synology-modal-backdrop" class="dtr-modal-backdrop hidden" style="z-index:10050">
    <div id="dtr-synology-modal" class="syno-modal-dialog">
        <div class="syno-modal-head">
            <h3 class="syno-modal-title">Synology Send Report</h3>
            <button type="button" id="btn-close-syno-modal" class="syno-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="syno-modal-body">
            <div class="syno-channel-bar">
                <div>
                    <span class="syno-live-dot"></span>
                    <span>Target Channel: <strong>Shoe Bonding Lab Alerts</strong> (192.168.32.130)</span>
                </div>
                <span class="syno-badge">Incoming Webhook</span>
            </div>

            <div class="syno-templates-wrap">
                <span class="syno-section-lbl">Message Template:</span>
                <div class="syno-tpl-pills">
                    <button type="button" class="syno-tpl-pill is-active" data-tpl="first-article">First Article / Result Notice</button>
                    <button type="button" class="syno-tpl-pill" data-tpl="failure-alert">Failure Notice (Defect &amp; Cement)</button>
                    <button type="button" class="syno-tpl-pill" data-tpl="daily-summary">Daily Bonding Summary</button>
                    <button type="button" class="syno-tpl-pill" data-tpl="custom">Custom Message</button>
                </div>
            </div>

            <div class="syno-templates-wrap">
                <span class="syno-section-lbl">Insert Quick Details:</span>
                <div class="syno-tags-wrap">
                    <button type="button" class="syno-tag-btn" data-insert="{RESULT}">+ Result</button>
                    <button type="button" class="syno-tag-btn" data-insert="{STYLE}">+ Style</button>
                    <button type="button" class="syno-tag-btn" data-insert="{COLOR}">+ Color</button>
                    <button type="button" class="syno-tag-btn" data-insert="{LINE}">+ Line / Bldg</button>
                    <button type="button" class="syno-tag-btn" data-insert="{PROD_DATE}">+ Prod Date</button>
                    <button type="button" class="syno-tag-btn" data-insert="{TEST_DATE}">+ Test Date</button>
                    <button type="button" class="syno-tag-btn" data-insert="{DEFECT}">+ Defect / Cause</button>
                    <button type="button" class="syno-tag-btn" data-insert="{CEMENT}">+ Cement</button>
                    <button type="button" class="syno-tag-btn" data-insert="{TESTED_BY}">+ Tested By</button>
                    <button type="button" class="syno-tag-btn" data-insert="{CHECKED_BY}">+ Checked By</button>
                </div>
            </div>

            <div>
                <label for="syno-message-input" class="syno-section-lbl" style="display:block;margin-bottom:6px">Message Content:</label>
                <textarea id="syno-message-input" rows="8" class="syno-textarea" placeholder="Enter report message to send to Synology Chat..."></textarea>
            </div>

            <div class="syno-attach-zone">
                <div class="syno-attach-controls">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <label class="syno-attach-label" for="syno-file-upload">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            <span>Attach Image File</span>
                        </label>
                        <input type="file" id="syno-file-upload" accept="image/*" multiple style="display:none">
                        <button type="button" id="btn-syno-capture-card" class="dtr-btn dtr-btn-outline dtr-btn-sm" title="Generate snapshot image of the current bonding report sheet / graph">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span>Snapshot Report Graph Card</span>
                        </button>
                    </div>
                    <small class="muted">The report graph card is sent first, then up to 10 photos (upload several at once, or scan the QR code again for each photo). PNG, JPG or WebP.</small>
                </div>

                <div id="syno-preview-wrap" class="syno-preview-card hidden">
                    <img id="syno-preview-img" class="syno-preview-thumb" tabindex="0" role="button" title="Click to enlarge" src="" alt="Report graph card preview: click to enlarge">
                    <div class="syno-preview-meta">
                        <span class="syno-preview-kind">Image 1: report graph card</span>
                        <span id="syno-preview-name" class="syno-preview-name">attachment.png</span>
                        <span id="syno-preview-size" class="syno-preview-size">0 KB</span>
                        <button type="button" id="btn-syno-remove-attach" class="syno-remove-attach">&times; Remove this image</button>
                    </div>
                </div>
                <div id="syno-photo-list" class="email-attach-list"></div>
            </div>
        </div>
        <div class="syno-modal-footer">
            <span id="syno-status-text" class="syno-status">Ready to send</span>
            <button type="button" id="btn-close-syno-cancel" class="dtr-btn dtr-btn-outline">Cancel</button>
            <button type="button" id="btn-send-synology-submit" class="dtr-btn dtr-btn-primary" style="background:#0284c7;border-color:#0284c7">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                <span>Send to Synology Chat</span>
            </button>
        </div>
    </div>
</div>

<!-- =======================================================================
     EMAIL SEND REPORT MODAL
     ======================================================================= -->
<div id="dtr-email-modal-backdrop" class="dtr-modal-backdrop hidden" style="z-index:10050">
    <div id="dtr-email-modal" class="syno-modal-dialog">
        <div class="syno-modal-head">
            <h3 class="syno-modal-title">Send Report Email</h3>
            <button type="button" id="btn-close-email-modal" class="syno-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="syno-modal-body">
            <div>
                <label for="email-to-input" class="syno-section-lbl" style="display:block;margin-bottom:6px">Recipient Email (To):</label>
                <input type="email" id="email-to-input" class="dtr-input" placeholder="e.g. supervisor@dtshoes.com, qa@dtshoes.com" style="width:100%">
            </div>
            <div>
                <label for="email-subject-input" class="syno-section-lbl" style="display:block;margin-bottom:6px">Subject:</label>
                <input type="text" id="email-subject-input" class="dtr-input" placeholder="Shoe Bonding Test Report Notification" style="width:100%">
            </div>
            <div>
                <label for="email-message-input" class="syno-section-lbl" style="display:block;margin-bottom:6px">Message Content:</label>
                <textarea id="email-message-input" rows="7" class="syno-textarea" placeholder="Enter email message body..."></textarea>
            </div>
            <div class="syno-attach-zone">
                <div class="syno-attach-controls">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <label class="syno-attach-label" for="email-file-upload">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            <span>Attach Image File</span>
                        </label>
                        <input type="file" id="email-file-upload" accept="image/*" multiple style="display:none">
                        <button type="button" id="btn-email-capture-card" class="dtr-btn dtr-btn-outline dtr-btn-sm" title="Generate snapshot image of the current bonding report sheet / graph">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span>Snapshot Report Graph Card</span>
                        </button>
                    </div>
                    <small class="muted">Up to 10 photos. The photos you took or uploaded for this report are kept here too, and the other way round.</small>
                </div>
                <div id="email-attach-list" class="email-attach-list"></div>
            </div>
            <div id="email-attach-summary" class="muted sm"></div>
        </div>
        <div class="syno-modal-footer">
            <span id="email-status-text" class="syno-status">Ready</span>
            <button type="button" id="btn-close-email-cancel" class="dtr-btn dtr-btn-outline">Cancel</button>
            <button type="button" id="btn-send-email-submit" class="dtr-btn dtr-btn-primary" style="background:#ea580c;border-color:#ea580c">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                <span>Send Email</span>
            </button>
        </div>
    </div>
</div>


<!-- Toast Notification -->
<div id="dtr-toast" class="dtr-toast hidden"></div>
<!-- Technicians with their machine no. (machine - name): used by the Technician fields -->
<datalist id="dtr-tech-list">
    <option value="DTP-QC-048 - L. Aquidado"></option>
    <option value="DTP-QC-002 - S. Aquino"></option>
    <option value="DTP-QC-001 - L. Tuddao"></option>
    <option value="DTP-QC-064 - J. Barrera"></option>
    <option value="DTP-QC-066 - J. Parrilla"></option>
    <option value="DTP-QC-067 - R. Cabigas"></option>
</datalist>
