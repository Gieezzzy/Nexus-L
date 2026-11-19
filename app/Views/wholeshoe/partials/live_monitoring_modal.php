<!-- =======================================================================
     POPUP MODAL: WHOLE SHOE TEST LIVE MONITORING (LMS PARITY POPUP)
     ======================================================================= -->

<!-- NEW TEST MODALS -->
<div id="wst-new-modal-backdrop" class="dtr-modal-backdrop hidden" style="z-index:10040">
    <div class="dtr-modal-dialog wst-theme-modal" style="max-width:1050px;width:95%">
        <div class="dtr-modal-header">
            <div style="display:flex;align-items:center;gap:10px">
                <span class="dtr-pill dtr-pill-blue">NEW TEST SESSION</span>
                <h3 style="margin:0;font-size:16px;font-weight:700">Create Whole Shoe Test</h3>
            </div>
            <button type="button" id="btn-wst-close-new-modal" class="dtr-modal-close" title="Close">&times;</button>
        </div>
        <div class="dtr-modal-body" style="padding:24px 28px">
            <form id="wst-new-form" onsubmit="return false;">
                <div class="dtr-grid-2" style="gap:16px;margin-bottom:16px">
                    <div class="dtr-field">
                        <label>Lab Reference (Auto-Generated)</label>
                        <div style="display:flex;gap:8px">
                            <input type="text" id="wst-new-labref" class="bold mono" value="DTP20261028LEL" readonly style="color:var(--brand,#2563eb);background:#f8fafc">
                            <button type="button" class="dtr-btn-xs dtr-btn-outline" id="btn-wst-regen-labref" style="padding:0 14px">Regen</button>
                        </div>
                    </div>
                    <div class="dtr-field">
                        <label>Test Date</label>
                        <input type="date" id="wst-new-date" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="dtr-grid-3" style="gap:16px;margin-bottom:18px">
                    <div class="dtr-field">
                        <label>Category</label>
                        <select id="wst-new-cat">
                            <option value="bally-flex">Bally Flexing</option>
                            <option value="ross-flex">Ross Flexing</option>
                            <option value="martindale-abrasion">Martindale Abrasion</option>
                            <option value="lace-abrasion">Lace Abrasion</option>
                            <option value="taber-abrasion">Taber Abrasion</option>
                            <option value="crocking">Crocking</option>
                        </select>
                    </div>
                    <div class="dtr-field">
                        <label>Material / Type Specification</label>
                        <select id="wst-new-type">
                            <option value="Standard Flexing">Standard Flexing</option>
                            <option value="Fabric/Leather">Fabric/Leather</option>
                        </select>
                    </div>
                    <div class="dtr-field">
                        <label>Machine Number / Station</label>
                        <input type="text" id="wst-new-machine" placeholder="e.g. MC-FLEX-01" value="MC-FLEX-01">
                    </div>
                </div>
            </form>
        </div>
        <div class="dtr-modal-footer">
            <button type="button" class="dtr-btn dtr-btn-outline" id="btn-wst-new-cancel" style="padding:8px 20px">Cancel</button>
            <button type="button" class="dtr-btn dtr-btn-primary" id="btn-wst-create-submit" style="padding:8px 22px;font-weight:700">
                <svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Create &amp; Open Monitor
            </button>
        </div>
    </div>
</div>

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
                            <span class="wst-meta-label">Material / Type</span>
                            <span class="wst-meta-val" id="wst-card-dt-type" title="Standard Whole Shoe Flexing">Standard Whole Shoe Flexing</span>
                        </div>
                    </div>

                    <!-- TIMED INCUBATION / EXPOSURE MONITORING PANEL (Hydro, Heat Aging, UV, Washing, Dip Test) -->
                    <div id="wst-timer-container" class="card" style="margin-bottom:18px;border:1px solid #cbd5e1;border-radius:12px;background:#f8fafc;padding:20px;box-shadow:0 2px 8px rgba(15,23,42,0.04)">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #e2e8f0">
                            <div>
                                <span class="dtr-pill dtr-pill-blue" id="wst-timer-type-badge" style="font-weight:700;font-size:11.5px;letter-spacing:0.04em">TIMED TEST MONITOR</span>
                                <span class="muted sm" id="wst-timer-nominal-duration" style="margin-left:8px;font-weight:600;color:#64748b">Duration: 7 Days</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px">
                                <span class="dtr-pill" id="wst-timer-state-pill" style="font-weight:700;font-size:12px;background:#e2e8f0;color:#475569">PENDING START</span>
                            </div>
                        </div>

                        <!-- Big Live Digital Countdown Clock -->
                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px 20px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:14px">
                            <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#64748b;margin-bottom:4px">Time Remaining</span>
                            <div id="wst-countdown-clock" class="mono" style="font-size:36px;font-weight:800;color:#0f172a;letter-spacing:1px">--:--:--</div>
                            <div id="wst-countdown-subtext" class="sm muted" style="margin-top:4px;font-size:12px">Press RUN / START to begin timer</div>
                        </div>

                        <!-- Progress Bar for Timer -->
                        <div style="margin-bottom:12px">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                                <span style="font-size:11.5px;font-weight:700;color:#475569" id="wst-timer-elapsed-label">Elapsed: 0s</span>
                                <span class="mono bold" id="wst-timer-pct-label" style="font-size:12px;color:var(--brand,#2563eb)">0%</span>
                            </div>
                            <div class="wst-progress-rail" style="height:10px;border-radius:5px">
                                <div class="wst-progress-fill" id="wst-timer-progress-bar" style="width:0%;transition:width 0.3s ease"></div>
                            </div>
                        </div>

                        <!-- Daily Checkpoint Remarks (Hydro, Heat Aging, Washing, UV, Dip Test) -->
                        <div id="wst-checkpoint-remarks-section" class="hidden" style="margin-top:14px;padding:14px 16px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px">
                                <span style="font-size:12px;font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:0.04em">Daily Checkpoint Readings &amp; Remarks</span>
                                <span class="sm muted" style="font-size:11.5px;color:#64748b">Click an elapsed day to input findings (concatenated for report)</span>
                            </div>
                            <div id="wst-checkpoint-day-buttons" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px"></div>
                            <div id="wst-checkpoint-remarks-list" style="font-size:12px;color:#334155;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px 10px;max-height:110px;overflow-y:auto;white-space:pre-line">No daily checkpoint remarks recorded yet.</div>
                        </div>

                        <!-- Finished Banner Notification Alert -->
                        <div id="wst-timer-finished-alert" class="hidden" style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:14px 18px;margin-top:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:34px;height:34px;border-radius:50%;background:#10b981;color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700">&#10003;</div>
                                <div>
                                    <b style="color:#065f46;font-size:14px;display:block">Test Time Completed!</b>
                                    <span style="font-size:12.5px;color:#047857">The required duration has elapsed. Please inspect the shoe and input findings below.</span>
                                </div>
                            </div>
                            <button type="button" class="dtr-btn dtr-btn-primary wst-trigger-eval-btn" id="btn-wst-trigger-evaluation" style="background:#059669;border-color:#059669;font-weight:700;padding:8px 18px">
                                Input Findings &amp; Evaluation &rarr;
                            </button>
                        </div>

                        <!-- Paused Banner Notification Alert -->
                        <div id="wst-timer-paused-alert" class="hidden" style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:14px 18px;margin-top:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:34px;height:34px;border-radius:50%;background:#f59e0b;color:#ffffff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700">&#10074;&#10074;</div>
                                <div>
                                    <b style="color:#92400e;font-size:14px;display:block">Test Timer Paused</b>
                                    <span style="font-size:12.5px;color:#b45309">Testing is currently paused. You can record findings or resume testing anytime.</span>
                                </div>
                            </div>
                            <button type="button" class="dtr-btn dtr-btn-primary wst-trigger-eval-btn" id="btn-wst-trigger-evaluation-paused" style="background:#2563eb;border-color:#2563eb;font-weight:700;padding:8px 18px">
                                <svg class="ic" aria-hidden="true" style="margin-right:4px"><use href="#i-check"/></svg> Input Findings &amp; Status
                            </button>
                        </div>
                    </div>

                    <!-- CYCLIC PROGRESS MONITORING PANEL FOR WHOLE SHOE FLEXING (5k, 10k, 15k... 125k) -->
                    <div id="wst-flex-container" class="card hidden" style="margin-bottom:18px;border:1px solid #cbd5e1;border-radius:12px;background:#f8fafc;padding:18px;box-shadow:0 2px 8px rgba(15,23,42,0.04)">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #e2e8f0">
                            <div>
                                <span class="dtr-pill dtr-pill-blue" style="font-weight:700;font-size:11.5px;letter-spacing:0.04em">WHOLE SHOE FLEXING</span>
                                <span class="muted sm" style="margin-left:8px;font-weight:600;color:#64748b">Target: 125,000 Cycles</span>
                            </div>
                            <div>
                                <span class="wst-status-pill wst-status-running" id="wst-flex-state-pill">RUNNING</span>
                            </div>
                        </div>

                        <!-- Progress Bar for Flexing Cycles -->
                        <div style="margin-bottom:16px;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                                <span style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#475569">
                                    Flexing Progress (<span id="wst-flex-curr-cycles" class="mono">0</span> / <span id="wst-flex-target-cycles" class="mono">125,000</span> cycles)
                                </span>
                                <span class="mono bold" id="wst-flex-pct-label" style="font-size:12px;color:var(--brand,#2563eb)">0%</span>
                            </div>
                            <div class="wst-progress-rail" style="height:10px;border-radius:5px">
                                <div class="wst-progress-fill" id="wst-flex-progress-bar" style="width:0%;transition:width 0.3s ease"></div>
                            </div>
                        </div>

                        <!-- Specimen & Cycle Checkpoints Table (Material Testing Parity) -->
                        <div class="dtr-table-wrap" style="overflow-x:auto;border-radius:8px;border:1px solid #e2e8f0;background:#ffffff">
                            <table class="dtr-table wst-cycle-table" id="wst-flex-cycle-table">
                                <thead>
                                    <tr>
                                        <th style="min-width:110px">SPECIMEN</th>
                                        <th>CYCLES</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="2" class="muted" style="text-align:center;padding:16px">Loading flexing checkpoints...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Minimalist Color Status Legend Strip -->
                        <div class="wst-legend-strip" style="display:flex;align-items:center;gap:18px;margin-top:10px;padding:6px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-size:11.5px;color:#475569">
                            <span style="font-weight:700;text-transform:uppercase;letter-spacing:0.04em;font-size:10px;color:#64748b">Legend:</span>
                            <span style="display:inline-flex;align-items:center;gap:6px"><span style="width:14px;height:14px;border-radius:3px;background:#86efac;border:1px solid #4ade80;display:inline-flex;align-items:center;justify-content:center;font-size:9px;color:#15803d;font-weight:800">&#10003;</span> Monitored</span>
                            <span style="display:inline-flex;align-items:center;gap:6px"><span style="width:14px;height:14px;border-radius:3px;background:#eff6ff;border:1.5px solid #3b82f6"></span> Active Target</span>
                            <span style="display:inline-flex;align-items:center;gap:6px"><span style="width:14px;height:14px;border-radius:3px;background:#ffffff;border:1px solid #cbd5e1"></span> Pending Checkpoint</span>
                            <span style="margin-left:auto;font-size:11px;color:#94a3b8"><svg class="ic" aria-hidden="true" style="width:12px;height:12px;vertical-align:middle;margin-right:2px"><use href="#i-help"/></svg> Click cell to log checkpoint observation</span>
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
                            <button type="button" class="dtr-btn wst-btn-secondary hidden" id="btn-wst-modal-pause" style="width:100%;background:#f59e0b;color:#ffffff;border-color:#f59e0b;font-weight:700;margin-top:6px;justify-content:center">
                                <svg class="ic" aria-hidden="true" style="width:15px;height:15px;margin-right:4px"><rect x="6" y="4" width="4" height="16" fill="currentColor"/><rect x="14" y="4" width="4" height="16" fill="currentColor"/></svg> PAUSE TIMER
                            </button>
                            <div id="wst-running-indicator-bar" class="dtr-btn wst-btn-running-state hidden" style="width:100%">
                                <span class="wst-pulse-dot wst-pulse-dot-green"></span>
                                <span>Running</span>
                                <span class="wst-runtime-timer" id="wst-modal-runtime-clock">Active</span>
                            </div>
                        </div>

                        <!-- Findings & Evaluation Actions -->
                        <button type="button" class="dtr-btn wst-btn-secondary" id="btn-wst-open-eval-modal" style="width:100%;background:#2563eb;color:#ffffff;border-color:#2563eb;font-weight:700;margin-top:6px;display:flex;align-items:center;justify-content:center;gap:6px">
                            <svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Input Findings &amp; Status
                        </button>

                        <hr style="border:none;border-top:1px solid #e2e8f0;margin:6px 0">

                        <!-- Destructive / Safety Action (Red Outline with Distinct Separation) -->
                        <button type="button" class="dtr-btn wst-btn-danger-outline" id="btn-wst-modal-stop" style="width:100%">
                            <svg class="ic" aria-hidden="true"><use href="#i-close"/></svg> Stop Entire Test
                        </button>
                        <button type="button" class="dtr-btn wst-btn-danger-outline" id="btn-wst-modal-reset" style="width:100%" title="Removes the status and results of this Lab Ref completely: the test starts over">
                            <svg class="ic" aria-hidden="true"><use href="#i-close"/></svg> Remove Status
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
