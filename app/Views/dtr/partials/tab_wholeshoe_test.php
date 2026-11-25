<?php /* DTR partial: Whole Shoe Test (laboratory monitoring workflow). Included by dtr/index.php, shares its variables. */ ?>
<!-- =======================================================================
     TAB: WHOLE SHOE TEST (LABORATORY MONITORING WORKFLOW - MARC)
     ======================================================================= -->
<section id="dtr-panel-wholeshoetest" class="dtr-panel <?= in_array($activeTab, ['wholeshoetest', 'Whole Shoe Test'], true) ? '' : 'hidden' ?>">
    <!-- Top Sub-Nav & Category Selection -->
    <div class="card glass dtr-section-card" style="margin-bottom:16px">
        <div class="wst-header-banner">
            <div class="wst-banner-left">
                <span class="wst-badge">WHOLE SHOE TEST SUITE</span>
                <h3 style="margin-top:4px">Whole Shoe Testing &amp; Monitoring</h3>
                <p class="muted sm">Whole shoe hydrolysis, heat aging, washing, UV light exposure, and flexing monitoring workflow.</p>
            </div>
        </div>

        <!-- Category Tabs -->
        <div class="wst-category-tabs" role="tablist">
            <button type="button" class="wst-cat-tab active" data-wst-cat="hydrolysis">Hydrolysis</button>
            <button type="button" class="wst-cat-tab" data-wst-cat="heat-aging">Heat Aging</button>
            <button type="button" class="wst-cat-tab" data-wst-cat="washing">Washing</button>
            <button type="button" class="wst-cat-tab" data-wst-cat="uv-light">UV Light</button>
            <button type="button" class="wst-cat-tab" data-wst-cat="flexing">Flexing</button>
            <button type="button" class="wst-cat-tab" data-wst-cat="deeptest">Deep Test</button>
        </div>
    </div>

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

        <div class="card glass dtr-section-card">
            <div class="dtr-card-head" style="margin-bottom:14px">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <div style="position:relative;display:flex;align-items:center">
                        <input type="text" id="wst-list-search" class="dtr-input" placeholder="Search Lab Reference, Operator, Machine..." style="min-width:320px;padding:8px 14px">
                    </div>
                    <span class="muted sm" id="wst-record-count">Loading records...</span>
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
                            <th>TIME / STATUS</th>
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

<section id="dtr-panel-calculators"class="dtr-panel <?= $activeTab === 'calculators' ? '' : 'hidden' ?>">
    <div class="dtr-grid-3">
        <!-- 1. Weight Calculator -->
        <div class="card glass dtr-section-card" id="calc-card-weight">
            <h3>1. Weight Calculator (Fabric / Leather / Insole)</h3>
            <p class="muted sm">Formula: <code>Weight_i = Round((Raw_i / 100) * 10000, 2)</code> · <code>WAve = Round((W1+W2+W3)/3, 2)</code></p>
            <div class="dtr-grid-3" style="margin-top:8px">
                <div class="dtr-field"><label>Raw Weight 1 (g)</label><input type="number" step="any" id="calc_raw_w1" value="2.45"></div>
                <div class="dtr-field"><label>Raw Weight 2 (g)</label><input type="number" step="any" id="calc_raw_w2" value="2.48"></div>
                <div class="dtr-field"><label>Raw Weight 3 (g)</label><input type="number" step="any" id="calc_raw_w3" value="2.46"></div>
                <div class="dtr-field"><label>Weight 1 (g/m²)</label><input type="text" id="calc_res_w1" readonly class="dtr-ro"></div>
                <div class="dtr-field"><label>Weight 2 (g/m²)</label><input type="text" id="calc_res_w2" readonly class="dtr-ro"></div>
                <div class="dtr-field"><label>Weight 3 (g/m²)</label><input type="text" id="calc_res_w3" readonly class="dtr-ro"></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                <div>Average: <b id="calc_res_wave" class="mono">246.33</b> g/m²</div>
                <button type="button" id="btn-apply-calc-weight" class="dtr-btn-xs dtr-btn-primary">Apply to Material Form</button>
            </div>
        </div>

        <!-- 2. Specific Gravity Calculator -->
        <div class="card glass dtr-section-card" id="calc-card-sg">
            <h3>2. Specific Gravity Calculator (SKL / FM / Rubber)</h3>
            <p class="muted sm">Formula: <code>SG_i = Round(RawW_i / ((10 * 10) * (Thickness_i / 10)), 3)</code></p>
            <div class="dtr-grid-3" style="margin-top:8px">
                <div class="dtr-field"><label>Raw W1 (g)</label><input type="number" step="any" id="calc_sg_w1" value="18.5"></div>
                <div class="dtr-field"><label>Raw W2 (g)</label><input type="number" step="any" id="calc_sg_w2" value="18.6"></div>
                <div class="dtr-field"><label>Raw W3 (g)</label><input type="number" step="any" id="calc_sg_w3" value="18.4"></div>
                <div class="dtr-field"><label>Thick T1 (mm)</label><input type="number" step="any" id="calc_sg_t1" value="1.6"></div>
                <div class="dtr-field"><label>Thick T2 (mm)</label><input type="number" step="any" id="calc_sg_t2" value="1.6"></div>
                <div class="dtr-field"><label>Thick T3 (mm)</label><input type="number" step="any" id="calc_sg_t3" value="1.6"></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                <div>SG1..3 / Ave: <b id="calc_res_sg_ave" class="mono">1.156</b></div>
                <button type="button" id="btn-apply-calc-sg" class="dtr-btn-xs dtr-btn-primary">Apply to Material Form</button>
            </div>
        </div>

        <!-- 3. Compression Set Calculator -->
        <div class="card glass dtr-section-card" id="calc-card-comp">
            <h3>3. Compression Set Calculator (Standard &amp; PU Foam)</h3>
            <p class="muted sm">Formula: <code>pT_i = Round(((Before_i - After_i) / (Before_i - Spacer)) * 100, 1)</code></p>
            <div class="dtr-grid-3" style="margin-top:8px">
                <div class="dtr-field"><label>Before 1 (mm)</label><input type="number" step="any" id="calc_cp_b1" value="10.0"></div>
                <div class="dtr-field"><label>After 1 (mm)</label><input type="number" step="any" id="calc_cp_a1" value="9.2"></div>
                <div class="dtr-field"><label>Spacer (mm, 0=PU)</label><input type="number" step="any" id="calc_cp_sp" value="5.0"></div>
                <div class="dtr-field"><label>Before 2 (mm)</label><input type="number" step="any" id="calc_cp_b2" value="10.0"></div>
                <div class="dtr-field"><label>After 2 (mm)</label><input type="number" step="any" id="calc_cp_a2" value="9.3"></div>
                <div class="dtr-field"><label>After 3 / Bef 3</label><input type="number" step="any" id="calc_cp_a3" value="9.2"></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                <div>Compression Set Ave: <b id="calc_res_cp_ave" class="mono">15.3%</b></div>
                <button type="button" id="btn-apply-calc-comp" class="dtr-btn-xs dtr-btn-primary">Apply to Material Form</button>
            </div>
        </div>

        <!-- 4. DIN Abrasion Calculator -->
        <div class="card glass dtr-section-card" id="calc-card-din">
            <h3>4. DIN Abrasion Calculator (Rubber Outsole)</h3>
            <p class="muted sm">Formula: <code>DIN_i = Round((((W_before - W_after) / SG) * (200 / SA)) * 1000, 2)</code></p>
            <div class="dtr-grid-4" style="margin-top:8px">
                <div class="dtr-field"><label>W Before (g)</label><input type="number" step="any" id="calc_din_wb" value="1.250"></div>
                <div class="dtr-field"><label>W After (g)</label><input type="number" step="any" id="calc_din_wa" value="1.115"></div>
                <div class="dtr-field"><label>SG</label><input type="number" step="any" id="calc_din_sg" value="1.15"></div>
                <div class="dtr-field"><label>SA Factor</label><input type="number" step="any" id="calc_din_sa" value="200"></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                <div>Computed DIN Volume Loss: <b id="calc_res_din" class="mono">117.39</b> mm³ (Std &le; 150)</div>
                <button type="button" id="btn-apply-calc-din" class="dtr-btn-xs dtr-btn-primary">Apply to Material Form</button>
            </div>
        </div>

        <!-- 5. Ross Flex Calculator -->
        <div class="card glass dtr-section-card" id="calc-card-ross">
            <h3>5. Ross Flexing Calculator (Rubber Outsole)</h3>
            <p class="muted sm">Formula: <code>RossFlex_i = Round(((Lf_i - Li_i) / Li_i) * 100, 2)</code> (Default <code>Li = 2.5</code>)</p>
            <div class="dtr-grid-4" style="margin-top:8px">
                <div class="dtr-field"><label>Initial L1 (mm)</label><input type="number" step="any" id="calc_rf_li1" value="2.5"></div>
                <div class="dtr-field"><label>Final Lf1 (mm)</label><input type="number" step="any" id="calc_rf_lf1" value="3.0"></div>
                <div class="dtr-field"><label>Initial L2 (mm)</label><input type="number" step="any" id="calc_rf_li2" value="2.5"></div>
                <div class="dtr-field"><label>Final Lf2 (mm)</label><input type="number" step="any" id="calc_rf_lf2" value="3.2"></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                <div>Cut Growth 1 &amp; 2: <b id="calc_res_rf" class="mono">20% / 28%</b> (Std &le; 50%)</div>
                <button type="button" id="btn-apply-calc-ross" class="dtr-btn-xs dtr-btn-primary">Apply to Material Form</button>
            </div>
        </div>

        <!-- 6. Shrinkage Calculator -->
        <div class="card glass dtr-section-card" id="calc-card-shrink">
            <h3>6. Shrinkage Calculator (Fabric / Leather / SKLFM / Rubber)</h3>
            <p class="muted sm">Formula: <code>Shrinkage = Round(((Before - After) / Before) * 100, 2)</code></p>
            <div class="dtr-grid-4" style="margin-top:8px">
                <div class="dtr-field"><label>Length Before</label><input type="number" step="any" id="calc_sh_lb" value="100"></div>
                <div class="dtr-field"><label>Length After</label><input type="number" step="any" id="calc_sh_la" value="98.8"></div>
                <div class="dtr-field"><label>Width Before</label><input type="number" step="any" id="calc_sh_wb" value="100"></div>
                <div class="dtr-field"><label>Width After</label><input type="number" step="any" id="calc_sh_wa" value="99.1"></div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                <div>Shrinkage L / W: <b id="calc_res_sh" class="mono">1.2% / 0.9%</b></div>
                <button type="button" id="btn-apply-calc-shrink" class="dtr-btn-xs dtr-btn-primary">Apply to Material Form</button>
            </div>
        </div>
    </div>

    <!-- Master Standards & DSR / Material / Shoe Lookup Tester -->
    <div class="card glass dtr-section-card" style="margin-top:14px">
        <div class="dtr-card-head">
            <div>
                <h3><svg class="ic" aria-hidden="true"><use href="#i-search"/></svg> Live Master Lookup Explorer</h3>
                <p class="muted sm">Test smart thickness-based standards or look up any Material Code (41,374), Production PO Code (74,741), or DSR MTL # (154,426).</p>
            </div>
        </div>
        <div class="dtr-grid-4" style="margin-bottom:10px">
            <div class="dtr-field">
                <label>Select Material Type for Standards</label>
                <select id="exp_mat_type" class="dtr-select-lg">
                    <?php foreach ($standardMatTypes as $smt): ?>
                        <option value="<?= esc($smt) ?>"><?= esc($smt) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="dtr-field">
                <label>Sample Thickness (mm)</label>
                <input type="number" step="any" id="exp_t_ave" value="1.25">
            </div>
            <div class="dtr-field">
                <label>Lookup MaterialCode / ProdCode / MTL #</label>
                <input type="text" id="exp_lookup_q" placeholder="Enter MaterialCode, PO, or MTL...">
            </div>
            <div class="dtr-field" style="display:flex;align-items:end">
                <button type="button" id="btn-exp-run" class="dtr-btn dtr-btn-primary" style="width:100%">Run Smart Standards &amp; Lookup</button>
            </div>
        </div>
        <div class="dtr-table-wrap" style="max-height:260px">
            <table class="dtr-table">
                <thead>
                    <tr>
                        <th>Material Type</th>
                        <th>Test Method / Control</th>
                        <th>Standard Value</th>
                        <th>Tolerance</th>
                        <th>Thickness Min</th>
                        <th>Thickness Max</th>
                    </tr>
                </thead>
                <tbody id="exp-standards-tbody">
                    <tr><td colspan="6" class="muted" style="text-align:center;padding:12px">Select a Material Type above or click "Run Smart Standards &amp; Lookup" to view matching specifications.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
