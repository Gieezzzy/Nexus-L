<?php $dtrIsAdmin = strcasecmp((string) (service('auth')->user()['role'] ?? ''), 'admin') === 0; ?>
<?php
/**
 * NEXUS-L QC Lab Digital Test Reports (#Digital Test Report & Bonding LIS System)
 * Migrated 100% from MS Access & VB.NET into MySQL dbnexus (367,333 rows across 31 tables).
 */
?>

<!-- Top Summary KPI Bar -->
<div class="dtr-top-kpis">
    <div class="card glass dtr-kpi">
        <div>
            <small>Total Lab Test Reports</small>
            <b id="dtr-kpi-total"><?= number_format((int) $totalReports) ?></b>
            <span class="dtr-kpi-sub">10 Material Forms + Shoe Bonding</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-blue"><svg class="ic" aria-hidden="true"><use href="#i-flask"/></svg></span>
    </div>
    <div class="card glass dtr-kpi">
        <div>
            <small>Passed Reports</small>
            <b class="dtr-txt-pass"><?= number_format((int) $totalPass) ?></b>
            <span class="dtr-kpi-sub">Meets VF / Vans Specification</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-pass">✓</span>
    </div>
    <div class="card glass dtr-kpi">
        <div>
            <small>Tolerable / Data</small>
            <b class="dtr-txt-warn"><?= number_format((int) $totalTol) ?></b>
            <span class="dtr-kpi-sub">Within Tolerance Band</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-warn"><svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg></span>
    </div>
    <div class="card glass dtr-kpi">
        <div>
            <small>Failed Reports</small>
            <b class="dtr-txt-fail"><?= number_format((int) $totalFail) ?></b>
            <span class="dtr-kpi-sub">Requires Retest / Action</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-fail">✕</span>
    </div>
    <div class="card glass dtr-kpi">
        <div>
            <small>Master Lookup Records</small>
            <b>271,627</b>
            <span class="dtr-kpi-sub">DSR (154k) · Shoe (74k) · Mat (41k) · Std (1k)</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-purple"><svg class="ic" aria-hidden="true"><use href="#i-database"/></svg></span>
    </div>
</div>

<!-- Toolbar of the open panel (the sidebar switches panels) -->
<div class="card glass dtr-nav-bar">
    <div class="dtr-nav-actions" data-dtr-for="tracker" <?= $activeTab === 'tracker' ? '' : 'hidden' ?>>
        <select id="dtr-tracker-cat" class="dtr-tracker-cat" aria-label="Report category">
            <option value="all">All Reports (<?= number_format((int) $totalReports) ?>)</option>
            <?php foreach ($catRows as $cr): ?>
                <option value="<?= esc($cr['form_slug']) ?>"><?= esc($cr['form_category']) ?> (<?= number_format((int) $cr['cnt']) ?>)</option>
            <?php endforeach ?>
        </select>
        <div class="dtr-search-box">
            <input type="text" id="dtr-tracker-search" placeholder="Search LabRefNo, TR No, Material Code, Style, Supplier, MTL...">
            <button type="button" id="dtr-tracker-btn-search" class="dtr-btn dtr-btn-primary">Search</button>
        </div>
    </div>
    <div class="dtr-nav-actions dtr-form-actions" data-dtr-for="material" <?= $activeTab === 'material' ? '' : 'hidden' ?>>
        <button type="button" id="btn-dtr-new" class="dtr-btn dtr-btn-outline">+ New Record</button>
        <button type="button" id="btn-dtr-load-machine" class="dtr-btn dtr-btn-amber" title="Reads the machine result file (.xlsx / .csv) for this Lab Ref No. The source file is never modified.">
            <svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> Load Machine Results
        </button>
        <button type="button" id="btn-dtr-refresh-calc" class="dtr-btn dtr-btn-outline" title="Runs ApplyStandardValuesSmart + Thickness + Directional Averages + 6-Point Colorfastness + EvaluateRatings">
            <svg class="ic" aria-hidden="true"><use href="#i-refresh"/></svg> Refresh &amp; Apply Smart Standards
        </button>
        <button type="button" id="btn-dtr-print-preview" class="dtr-btn dtr-btn-purple" title="View, print or save the official test report sheet">
            <svg class="ic" aria-hidden="true"><use href="#i-printer"/></svg> Print / Preview Report
        </button>
        <button type="button" id="btn-dtr-save" class="dtr-btn dtr-btn-primary">
            <svg class="ic" aria-hidden="true"><use href="#i-save"/></svg> Save Report to NEXUS-L
        </button>
    </div>
    <div class="dtr-nav-actions dtr-form-actions" data-dtr-for="bonding" <?= $activeTab === 'bonding' ? '' : 'hidden' ?>>
        <select id="dtr-report-kind" class="dtr-tracker-cat" aria-label="Report type">
            <option value="bonding">+ Bonding Report</option>
            <option value="pull">+ Pull Test Report</option>
        </select>
        <button type="button" id="btn-bond-recalc" class="dtr-btn dtr-btn-outline">
            <svg class="ic" aria-hidden="true"><use href="#i-refresh"/></svg> <span id="btn-bond-recalc-txt">Recalculate Spots &amp; Result</span>
        </button>
        <button type="button" id="btn-bond-print-preview" class="dtr-btn dtr-btn-purple" title="View, print or save the official report sheet">
            <svg class="ic" aria-hidden="true"><use href="#i-printer"/></svg> Print / Preview Report
        </button>
        <?php if (! empty($dtrIsAdmin)): ?>
        <a id="btn-bond-designer" class="dtr-btn dtr-btn-outline dtr-only-bonding" href="<?= site_url('designer') ?>?tab=diagram" title="Admin only: place the points on the shoe, hide or add fields">
            <svg class="ic" aria-hidden="true"><use href="#i-sliders"/></svg> Adjust shoe points / layout (admin)
        </a>
        <?php endif ?>
        <button type="button" id="btn-bond-save" class="dtr-btn dtr-btn-primary">
            <svg class="ic" aria-hidden="true"><use href="#i-save"/></svg> Save Report
        </button>
    </div>
    <div class="dtr-nav-actions dtr-form-actions" data-dtr-for="calculators" <?= $activeTab === 'calculators' ? '' : 'hidden' ?>>
        <?php if (! empty($dtrIsAdmin)): ?>
        <a class="dtr-btn dtr-btn-purple" href="<?= site_url('designer') ?>?tab=diagram" title="Admin: match the shoe lines and results">Shoe diagram &amp; points</a>
        <a class="dtr-btn dtr-btn-outline" href="<?= site_url('designer') ?>?tab=form" title="Admin: rearrange or hide inputs, add fields">Form fields</a>
        <a class="dtr-btn dtr-btn-amber" href="<?= site_url('designer') ?>?tab=excel" title="Admin: change which Excel cells are read per report type">Excel readers</a>
        <?php endif ?>
    </div>
    <div class="dtr-nav-right">
        <a href="<?= site_url('interlink') ?>" class="dtr-btn dtr-btn-outline">
            <svg class="ic" aria-hidden="true"><use href="#i-link"/></svg> InterLink Sync
        </a>
    </div>
</div>

<!-- =======================================================================
     TAB 1: LAB TRACKER & IQC CONTAINER / MATERIAL LOG
     ======================================================================= -->
<section id="dtr-panel-tracker" class="dtr-panel <?= $activeTab === 'tracker' ? '' : 'hidden' ?>">
    <!-- Category Pills -->
    <div class="card glass dtr-section-card">
        <div class="dtr-card-head">
            <div>
                <h3>Digital Test Report Master Tracker (All 11 Lab Report Types)</h3>
                <p class="muted sm">Pick a category above to filter or click <b>Open in Form</b> to view/edit full test readings and automatic calculations.</p>
            </div>
        </div>

        <div class="dtr-table-wrap">
            <table class="dtr-table" id="dtr-tracker-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>LabRefNo</th>
                        <th>TR / ETRF No.</th>
                        <th>Form Category</th>
                        <th>Material Type</th>
                        <th>Material Code</th>
                        <th>Material / Product Description</th>
                        <th>Supplier / Factory</th>
                        <th>Style / Model No.</th>
                        <th>Color</th>
                        <th>PO / Lot No.</th>
                        <th>MTL #</th>
                        <th>Tested Date</th>
                        <th>Technician</th>
                        <th>Status</th>
                        <th>Overall Result</th>
                    </tr>
                </thead>
                <tbody id="dtr-tracker-tbody">
                    <?php foreach ($recentReports as $r): ?>
                        <?php
                            $resUpper = strtoupper((string) ($r['overall_result'] ?? 'PASS'));
                            $badgeCls = $resUpper === 'FAIL' ? 'b-err' : ($resUpper === 'TOLERABLE' ? 'b-warn' : 'b-ok');
                        ?>
                        <tr>
                            <td style="white-space:nowrap">
                                <button type="button" class="dtr-btn-xs dtr-btn-primary" data-open-record="<?= esc($r['form_slug']) ?>" data-source-id="<?= (int) $r['source_id'] ?>" data-labref="<?= esc($r['lab_ref_no']) ?>">
                                    Open Form
                                </button>
                            </td>
                            <td class="mono"><b><?= esc($r['lab_ref_no']) ?></b></td>
                            <td class="mono"><?= esc($r['tr_no'] ?: '-') ?></td>
                            <td><span class="dtr-tag"><?= esc($r['form_category']) ?></span></td>
                            <td><?= esc($r['material_type'] ?: '-') ?></td>
                            <td class="mono"><?= esc($r['material_code'] ?: '-') ?></td>
                            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= esc($r['material_description']) ?>"><?= esc($r['material_description'] ?: '-') ?></td>
                            <td><?= esc($r['supplier'] ?: '-') ?></td>
                            <td class="mono"><?= esc($r['model_style'] ?: '-') ?></td>
                            <td><?= esc($r['color'] ?: '-') ?></td>
                            <td class="mono"><?= esc($r['po_lot_no'] ?: '-') ?></td>
                            <td class="mono"><?= esc($r['mtl_no'] ?: '-') ?></td>
                            <td><?= esc($r['tested_date'] ?: $r['received_date'] ?: '-') ?></td>
                            <td><?= esc($r['technician'] ?: '-') ?></td>
                            <td><?= esc($r['status'] ?: 'Complete') ?></td>
                            <td><span class="badge <?= $badgeCls ?>"><?= esc($resUpper) ?></span></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- IQC Container & Material Receiving Log (tbl_dtr_containers & tbl_dtr_materials_master) -->
    <div class="dtr-grid-2">
        <div class="card glass dtr-section-card">
            <div class="dtr-card-head">
                <div>
                    <h3><svg class="ic" aria-hidden="true"><use href="#i-box"/></svg> IQC Container Receiving Log </h3>
                    <p class="muted sm">Container receiving log (129 containers).</p>
                </div>
            </div>
            <div class="dtr-table-wrap" style="max-height:290px">
                <table class="dtr-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Container No.</th>
                            <th>Date Arrived</th>
                            <th>Received in Lab</th>
                            <th>Encoded By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($containers as $c): ?>
                            <tr>
                                <td class="mono"><?= (int) ($c['ContainerID'] ?? 0) ?></td>
                                <td class="mono"><b><?= esc($c['ContainerNo'] ?? '') ?></b></td>
                                <td><?= esc(substr((string) ($c['DateArrived'] ?? ''), 0, 10)) ?></td>
                                <td><?= esc(substr((string) ($c['DateReceivedInLab'] ?? ''), 0, 10)) ?></td>
                                <td><?= esc($c['Encodedby'] ?? '') ?></td>
                                <td><?= esc($c['Remarks'] ?? '') ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card glass dtr-section-card">
            <div class="dtr-card-head">
                <div>
                    <h3><svg class="ic" aria-hidden="true"><use href="#i-clipboard"/></svg> IQC Materials Master Queue </h3>
                    <p class="muted sm">Material receiving queue (778 material batches).</p>
                </div>
            </div>
            <div class="dtr-table-wrap" style="max-height:290px">
                <table class="dtr-table">
                    <thead>
                        <tr>
                            <th>LabReference</th>
                            <th>Material Type</th>
                            <th>Material Code</th>
                            <th>Material Name</th>
                            <th>Supplier</th>
                            <th>Status</th>
                            <th>Final</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materialsMaster as $mm): ?>
                            <tr>
                                <td class="mono"><b><?= esc($mm['LabReference'] ?? '') ?></b></td>
                                <td><?= esc($mm['MaterialType'] ?? '') ?></td>
                                <td class="mono"><?= esc($mm['MaterialCode'] ?? '') ?></td>
                                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc($mm['MaterialName'] ?? '') ?></td>
                                <td><?= esc($mm['Supplier'] ?? '') ?></td>
                                <td><?= esc($mm['TestingStatus'] ?? '') ?></td>
                                <td><span class="badge b-ok"><?= esc($mm['FinalStatus'] ?? 'Done') ?></span></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- =======================================================================
     TAB 2: MATERIAL TEST REPORT FORMS (ALL 10 ACCESS FORMS + AUTO COMPUTE)
     ======================================================================= -->
<section id="dtr-panel-material" class="dtr-panel <?= $activeTab === 'material' ? '' : 'hidden' ?>">
    <!-- Form Selector & Toolbar -->
    <div class="card glass dtr-section-card">
        <div class="dtr-form-toolbar">
            <div class="dtr-form-pickers">
                <div class="dtr-field">
                    <label>1. Select Material Test Form (10 report types)</label>
                    <select id="dtr-mat-form-slug" class="dtr-select-lg">
                        <?php foreach ($formConfig as $slug => $cfg): ?>
                            <?php if ($slug === 'bonding' || $slug === 'pull') continue; ?>
                            <option value="<?= esc($slug) ?>"><?= esc($cfg['label']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="dtr-field">
                    <label>2. Load Existing LabRefNo Record from NEXUS-L</label>
                    <select id="dtr-mat-record-select" class="dtr-select-lg"></select>
                </div>
            </div>
        </div>

        <!-- Overall Rating Stamp Banner -->
        <div class="dtr-stamp-banner" id="dtr-mat-banner">
            <div>
                <span class="dtr-eyebrow" id="dtr-active-form-label">Fabric Test Report (FBU / FBL)</span>
                <h3 id="dtr-active-labref-title">Loading record...</h3>
                <span class="muted sm" id="dtr-machine-status">Ready · Automatic calculations active</span>
            </div>
            <div class="dtr-stamp-box">
                <div class="dtr-stamp dtr-stamp-pass" id="dtr-stamp-badge">PASS</div>
            </div>
        </div>

        <form id="dtr-material-form" onsubmit="return false;">
            <input type="hidden" name="ID" id="mf_ID" value="">

            <!-- SECTION A: HEADER, SAMPLE METADATA & AUTO-LOOKUPS -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>A. Sample Identification &amp; Automatic Master Lookups</h4>
                    <div class="dtr-subcard-btns">
                        <button type="button" id="btn-dtr-lookup-all" class="dtr-btn-xs dtr-btn-primary"><svg class="ic" aria-hidden="true"><use href="#i-search"/></svg> Auto-Lookup Material / PO / DSR</button>
                        <button type="button" id="btn-dtr-review" class="dtr-btn-xs dtr-btn-outline"><svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Stamp Reviewed</button>
                    </div>
                </div>
                <div class="dtr-grid-6">
                    <div class="dtr-field">
                        <label>* Lab Reference No.</label>
                        <input type="text" name="LabRefNo" id="mf_LabRefNo" class="mono bold" placeholder="e.g. DTP20261009FBU-1">
                    </div>
                    <div class="dtr-field">
                        <label>Test Report No.</label>
                        <input type="text" name="TR" id="mf_TR" class="mono bold" placeholder="e.g. TR20261009001">
                    </div>
                    <div class="dtr-field">
                        <label>* Material Type</label>
                        <input type="text" name="MaterialType" id="mf_MaterialType" list="dtr-std-mattypes" placeholder="e.g. Canvas, Suede, Rubber">
                        <datalist id="dtr-std-mattypes">
                            <?php foreach ($standardMatTypes as $smt): ?>
                                <option value="<?= esc($smt) ?>"></option>
                            <?php endforeach ?>
                        </datalist>
                    </div>
                    <div class="dtr-field">
                        <label>Material Code</label>
                        <input type="text" name="MaterialCode" id="mf_MaterialCode" class="mono" placeholder="Type the code, then Tab">
                    </div>
                    <div class="dtr-field" style="grid-column:span 2">
                        <label>Material Description</label>
                        <input type="text" name="Material Description" id="mf_MaterialDescription" placeholder="Auto-filled from the material master">
                    </div>

                    <div class="dtr-field">
                        <label>Supplier / Factory</label>
                        <input type="text" name="Supplier/Factory" id="mf_SupplierFactory" placeholder="Auto-filled from the material master">
                    </div>
                    <div class="dtr-field">
                        <label>PO / Lot No.</label>
                        <input type="text" name="PO/Lot No" id="mf_POLotNo" class="mono" placeholder="Type the production code, then Tab">
                    </div>
                    <div class="dtr-field">
                        <label>Model No. / Style</label>
                        <input type="text" name="Model No/Style" id="mf_ModelNoStyle" placeholder="Auto-filled from the style master">
                    </div>
                    <div class="dtr-field">
                        <label>Color / Colorway</label>
                        <input type="text" name="Color" id="mf_Color" placeholder="Auto-filled from the style master">
                    </div>
                    <div class="dtr-field">
                        <label>PO Quantity</label>
                        <input type="text" name="PO Quantity" id="mf_POQuantity" placeholder="Quantity">
                    </div>
                    <div class="dtr-field">
                        <label>MTL #</label>
                        <input type="text" name="MTL" id="mf_MTL" class="mono" placeholder="e.g. MTL-12345">
                    </div>

                    <!-- DSR Auto-Filled Row -->
                    <div class="dtr-field">
                        <label>DSR Style Number</label>
                        <input type="text" name="StyleNumber_DSR" id="mf_StyleNumber_DSR" placeholder="From DSR">
                    </div>
                    <div class="dtr-field">
                        <label>DSR Style Description</label>
                        <input type="text" name="StyleDescription_DSR" id="mf_StyleDescription_DSR" placeholder="From DSR">
                    </div>
                    <div class="dtr-field">
                        <label>DSR Component / Location</label>
                        <input type="text" name="ComponentorLocation_DSR" id="mf_ComponentorLocation_DSR" placeholder="e.g. Vamp / Quarter">
                    </div>
                    <div class="dtr-field">
                        <label>DSR VF Material Code</label>
                        <input type="text" name="VFMaterialCode_DSR" id="mf_VFMaterialCode_DSR" class="mono">
                    </div>
                    <div class="dtr-field">
                        <label>DSR Internal Material Name</label>
                        <input type="text" name="InternalName_DSR" id="mf_InternalName_DSR">
                    </div>
                    <div class="dtr-field">
                        <label>DSR Color Name</label>
                        <input type="text" name="ColorName_DSR" id="mf_ColorName_DSR">
                    </div>

                    <!-- Dates, Technician, Status & Overall Result -->
                    <div class="dtr-field">
                        <label>Received Date</label>
                        <input type="text" name="ReceivedDate" id="mf_ReceivedDate" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="dtr-field">
                        <label>Tested Date</label>
                        <input type="text" name="TestedDate" id="mf_TestedDate" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="dtr-field">
                        <label>Finished Date</label>
                        <input type="text" name="FinishedDate" id="mf_FinishedDate" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="dtr-field">
                        <label>Tested By / Technician</label>
                        <input type="text" name="Tested by" id="mf_TestedBy" placeholder="Technician">
                    </div>
                    <div class="dtr-field">
                        <label>Status (Complete sets the rating)</label>
                        <select name="Status" id="mf_Status">
                            <option value="Complete">Complete</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Hold">Hold</option>
                        </select>
                    </div>
                    <div class="dtr-field">
                        <label>* Overall Rating</label>
                        <input type="text" name="Remarks" id="mf_Remarks" class="bold mono" readonly style="background:#f8fafc">
                    </div>
                </div>
            </div>

            <!-- SECTION B: THICKNESS / HARDNESS (6-POINT AUTO MIN/MAX/AVE + SMART STANDARDS) -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>B. Thickness (mm) &amp; Durometer Hardness (Shore A) — 6-Point Auto Min / Max / Ave</h4>
                    <span class="muted sm">Changing Thickness 1..6 automatically computes Min, Max, Average and updates thickness-dependent Standards!</span>
                </div>
                <div class="dtr-grid-6">
                    <div class="dtr-field"><label>Thickness 1</label><input type="number" step="any" name="Thickness1" id="mf_Thickness1" class="dtr-calc-thick"></div>
                    <div class="dtr-field"><label>Thickness 2</label><input type="number" step="any" name="Thickness2" id="mf_Thickness2" class="dtr-calc-thick"></div>
                    <div class="dtr-field"><label>Thickness 3</label><input type="number" step="any" name="Thickness3" id="mf_Thickness3" class="dtr-calc-thick"></div>
                    <div class="dtr-field"><label>Thickness 4</label><input type="number" step="any" name="Thickness4" id="mf_Thickness4" class="dtr-calc-thick"></div>
                    <div class="dtr-field"><label>Thickness 5</label><input type="number" step="any" name="Thickness5" id="mf_Thickness5" class="dtr-calc-thick"></div>
                    <div class="dtr-field"><label>Thickness 6</label><input type="number" step="any" name="Thickness6" id="mf_Thickness6" class="dtr-calc-thick"></div>

                    <div class="dtr-field"><label>Thickness Min</label><input type="text" name="TMin" id="mf_TMin" readonly class="dtr-ro"></div>
                    <div class="dtr-field"><label>Thickness Max</label><input type="text" name="TMax" id="mf_TMax" readonly class="dtr-ro"></div>
                    <div class="dtr-field"><label><b>Thickness Ave</b></label><input type="text" name="TAve" id="mf_TAve" readonly class="dtr-ro bold"></div>
                    <div class="dtr-field"><label>Thickness Std</label><input type="text" name="T_S" id="mf_T_S" class="dtr-std-inp"></div>
                    <div class="dtr-field"><label>Thickness Rating</label><input type="text" name="TRating" id="mf_TRating" class="dtr-rate-inp"></div>
                    <div class="dtr-field"><label>Hardness Ave / Rate (Rubber/FTHL)</label>
                        <div style="display:flex;gap:4px">
                            <input type="text" name="Hardness_Ave" id="mf_Hardness_Ave" placeholder="Hard Ave">
                            <input type="text" name="Hardness_Rate" id="mf_Hardness_Rate" placeholder="Rating" class="dtr-rate-inp">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION C: WEIGHT, SPECIFIC GRAVITY, COMPRESSION, DIN ABRASION, ROSS FLEX & SHRINKAGE -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>C. Weight, Specific Gravity, Compression Set, DIN Abrasion, Ross Flex &amp; Shrinkage</h4>
                    <div class="dtr-subcard-btns">
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="weight"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> Weight Calc</button>
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="sg"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> SG Calc</button>
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="comp"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> Compression Calc</button>
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="din"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> DIN Calc</button>
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="ross"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> Ross Flex Calc</button>
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="shrink"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> Shrinkage Calc</button>
                    </div>
                </div>
                <div class="dtr-grid-6">
                    <!-- Weight -->
                    <div class="dtr-field"><label>Weight 1</label><input type="number" step="any" name="Weight1" id="mf_Weight1" class="dtr-calc-weight"></div>
                    <div class="dtr-field"><label>Weight 2</label><input type="number" step="any" name="Weight2" id="mf_Weight2" class="dtr-calc-weight"></div>
                    <div class="dtr-field"><label>Weight 3</label><input type="number" step="any" name="Weight3" id="mf_Weight3" class="dtr-calc-weight"></div>
                    <div class="dtr-field"><label><b>Weight Ave</b></label><input type="text" name="WAve" id="mf_WAve" readonly class="dtr-ro bold"></div>
                    <div class="dtr-field"><label>Weight Std</label><input type="text" name="Weight_S" id="mf_Weight_S" class="dtr-std-inp"></div>
                    <div class="dtr-field"><label>Weight Rating</label><input type="text" name="WRating" id="mf_WRating" class="dtr-rate-inp"></div>

                    <!-- Specific Gravity -->
                    <div class="dtr-field"><label>Spec. Gravity 1</label><input type="number" step="any" name="SG1" id="mf_SG1" class="dtr-calc-sg"></div>
                    <div class="dtr-field"><label>Spec. Gravity 2</label><input type="number" step="any" name="SG2" id="mf_SG2" class="dtr-calc-sg"></div>
                    <div class="dtr-field"><label>Spec. Gravity 3</label><input type="number" step="any" name="SG3" id="mf_SG3" class="dtr-calc-sg"></div>
                    <div class="dtr-field"><label><b>SG Ave</b></label><input type="text" name="SG_Ave" id="mf_SG_Ave" readonly class="dtr-ro bold"></div>
                    <div class="dtr-field"><label>SG Std (±0.02 Tol)</label><input type="text" name="SG_Std" id="mf_SG_Std" class="dtr-std-inp"></div>
                    <div class="dtr-field"><label>SG Rating</label><input type="text" name="SG_Rate" id="mf_SG_Rate" class="dtr-rate-inp"></div>

                    <!-- DIN Abrasion, Ross Flex, Shrinkage -->
                    <div class="dtr-field"><label>DIN Abrasion 1..3 (≤150)</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="any" name="DIN_1" id="mf_DIN_1" class="dtr-calc-din" placeholder="1">
                            <input type="number" step="any" name="DIN_2" id="mf_DIN_2" class="dtr-calc-din" placeholder="2">
                            <input type="number" step="any" name="DIN_3" id="mf_DIN_3" class="dtr-calc-din" placeholder="3">
                        </div>
                    </div>
                    <div class="dtr-field"><label>DIN Rating</label><input type="text" name="DIN_Rate" id="mf_DIN_Rate" class="dtr-rate-inp"></div>
                    <div class="dtr-field"><label>Ross Flex 1..2 (≤50%)</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="any" name="RossFlex_1" id="mf_RossFlex_1" class="dtr-calc-ross" placeholder="1">
                            <input type="number" step="any" name="RossFlex_2" id="mf_RossFlex_2" class="dtr-calc-ross" placeholder="2">
                        </div>
                    </div>
                    <div class="dtr-field"><label>Ross Flex Rate</label><input type="text" name="RossFlex_Rate" id="mf_RossFlex_Rate" class="dtr-rate-inp"></div>
                    <div class="dtr-field"><label>Shrinkage L / W (%)</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="any" name="ShrinkageL" id="mf_ShrinkageL" class="dtr-calc-shrink" placeholder="L%">
                            <input type="number" step="any" name="ShrinkageW" id="mf_ShrinkageW" class="dtr-calc-shrink" placeholder="W%">
                        </div>
                    </div>
                    <div class="dtr-field"><label>Shrinkage Std &amp; Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="text" name="Shrinkage_S" id="mf_Shrinkage_S" placeholder="Std" class="dtr-std-inp">
                            <input type="text" name="ShrinkageLRating" id="mf_ShrinkageLRating" placeholder="L Rate" class="dtr-rate-inp">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION D: PHYSICAL & MECHANICAL DIRECTIONAL TESTS (AUTO L/W AVERAGES & RATINGS) -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>D. Physical &amp; Mechanical Tests — Directional Specimen Readings, Auto-Averages &amp; Pass/Fail</h4>
                    <span class="muted sm">Formula: <code>Ave = Round((Spec1 + Spec2 + Spec3) / 3, 2)</code> · Rating = <code>PASS</code> if <code>Ave >= Standard</code> else <code>FAIL</code></span>
                </div>
                <div class="dtr-table-wrap">
                    <table class="dtr-table dtr-mech-table">
                        <thead>
                            <tr>
                                <th style="width:210px">Test Property &amp; Direction</th>
                                <th>Spec 1</th>
                                <th>Spec 2</th>
                                <th>Spec 3</th>
                                <th>Computed Average</th>
                                <th>VF Standard Spec</th>
                                <th>Auto Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Tensile L & W -->
                            <tr>
                                <td><b>Tensile Strength — Length (L)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="TensileL1" id="mf_TensileL1" class="dtr-dir-inp" data-dir-group="TensileL"></td>
                                <td><input type="number" step="any" name="TensileL2" id="mf_TensileL2" class="dtr-dir-inp" data-dir-group="TensileL"></td>
                                <td><input type="number" step="any" name="TensileL3" id="mf_TensileL3" class="dtr-dir-inp" data-dir-group="TensileL"></td>
                                <td><input type="text" name="TensileLAve" id="mf_TensileLAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="Tensile_L" id="mf_Tensile_L" class="dtr-std-inp" data-dir-group="TensileL"></td>
                                <td><input type="text" name="TensileLRating" id="mf_TensileLRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>Tensile Strength — Width (W)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="TensileW1" id="mf_TensileW1" class="dtr-dir-inp" data-dir-group="TensileW"></td>
                                <td><input type="number" step="any" name="TensileW2" id="mf_TensileW2" class="dtr-dir-inp" data-dir-group="TensileW"></td>
                                <td><input type="number" step="any" name="TensileW3" id="mf_TensileW3" class="dtr-dir-inp" data-dir-group="TensileW"></td>
                                <td><input type="text" name="TensileWAve" id="mf_TensileWAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="Tensile_W" id="mf_Tensile_W" class="dtr-std-inp" data-dir-group="TensileW"></td>
                                <td><input type="text" name="TensileWRating" id="mf_TensileWRating" class="dtr-rate-inp"></td>
                            </tr>
                            <!-- Elongation L & W -->
                            <tr>
                                <td><b>Elongation — Length (L)</b> <small class="muted">(%)</small></td>
                                <td><input type="number" step="any" name="ElongationL1" id="mf_ElongationL1" class="dtr-dir-inp" data-dir-group="ElongationL"></td>
                                <td><input type="number" step="any" name="ElongationL2" id="mf_ElongationL2" class="dtr-dir-inp" data-dir-group="ElongationL"></td>
                                <td><input type="number" step="any" name="ElongationL3" id="mf_ElongationL3" class="dtr-dir-inp" data-dir-group="ElongationL"></td>
                                <td><input type="text" name="ElongationLAve" id="mf_ElongationLAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="Elongation_L" id="mf_Elongation_L" class="dtr-std-inp" data-dir-group="ElongationL"></td>
                                <td><input type="text" name="ElongationLRating" id="mf_ElongationLRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>Elongation — Width (W)</b> <small class="muted">(%)</small></td>
                                <td><input type="number" step="any" name="ElongationW1" id="mf_ElongationW1" class="dtr-dir-inp" data-dir-group="ElongationW"></td>
                                <td><input type="number" step="any" name="ElongationW2" id="mf_ElongationW2" class="dtr-dir-inp" data-dir-group="ElongationW"></td>
                                <td><input type="number" step="any" name="ElongationW3" id="mf_ElongationW3" class="dtr-dir-inp" data-dir-group="ElongationW"></td>
                                <td><input type="text" name="ElongationWAve" id="mf_ElongationWAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="Elongation_W" id="mf_Elongation_W" class="dtr-std-inp" data-dir-group="ElongationW"></td>
                                <td><input type="text" name="ElongationWRating" id="mf_ElongationWRating" class="dtr-rate-inp"></td>
                            </tr>
                            <!-- Trouser Tear L & W -->
                            <tr>
                                <td><b>Trouser / Wing Tear — Length (L)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="TrouserTearL1" id="mf_TrouserTearL1" class="dtr-dir-inp" data-dir-group="TrouserTearL"></td>
                                <td><input type="number" step="any" name="TrouserTearL2" id="mf_TrouserTearL2" class="dtr-dir-inp" data-dir-group="TrouserTearL"></td>
                                <td><input type="number" step="any" name="TrouserTearL3" id="mf_TrouserTearL3" class="dtr-dir-inp" data-dir-group="TrouserTearL"></td>
                                <td><input type="text" name="TrouserTearLAve" id="mf_TrouserTearLAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="TrouserTear_L" id="mf_TrouserTear_L" class="dtr-std-inp" data-dir-group="TrouserTearL"></td>
                                <td><input type="text" name="TrouserTearLRating" id="mf_TrouserTearLRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>Trouser / Wing Tear — Width (W)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="TrouserTearW1" id="mf_TrouserTearW1" class="dtr-dir-inp" data-dir-group="TrouserTearW"></td>
                                <td><input type="number" step="any" name="TrouserTearW2" id="mf_TrouserTearW2" class="dtr-dir-inp" data-dir-group="TrouserTearW"></td>
                                <td><input type="number" step="any" name="TrouserTearW3" id="mf_TrouserTearW3" class="dtr-dir-inp" data-dir-group="TrouserTearW"></td>
                                <td><input type="text" name="TrouserTearWAve" id="mf_TrouserTearWAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="TrouserTear_W" id="mf_TrouserTear_W" class="dtr-std-inp" data-dir-group="TrouserTearW"></td>
                                <td><input type="text" name="TrouserTearWRating" id="mf_TrouserTearWRating" class="dtr-rate-inp"></td>
                            </tr>
                            <!-- Stitch Tear L & W -->
                            <tr>
                                <td><b>Stitch Tear — Length (L)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="StitchTearL1" id="mf_StitchTearL1" class="dtr-dir-inp" data-dir-group="StitchTearL"></td>
                                <td><input type="number" step="any" name="StitchTearL2" id="mf_StitchTearL2" class="dtr-dir-inp" data-dir-group="StitchTearL"></td>
                                <td><input type="number" step="any" name="StitchTearL3" id="mf_StitchTearL3" class="dtr-dir-inp" data-dir-group="StitchTearL"></td>
                                <td><input type="text" name="StitchTearLAve" id="mf_StitchTearLAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="StitchTear_L" id="mf_StitchTear_L" class="dtr-std-inp" data-dir-group="StitchTearL"></td>
                                <td><input type="text" name="StitchTearLRating" id="mf_StitchTearLRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>Stitch Tear — Width (W)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="StitchTearW1" id="mf_StitchTearW1" class="dtr-dir-inp" data-dir-group="StitchTearW"></td>
                                <td><input type="number" step="any" name="StitchTearW2" id="mf_StitchTearW2" class="dtr-dir-inp" data-dir-group="StitchTearW"></td>
                                <td><input type="number" step="any" name="StitchTearW3" id="mf_StitchTearW3" class="dtr-dir-inp" data-dir-group="StitchTearW"></td>
                                <td><input type="text" name="StitchTearWAve" id="mf_StitchTearWAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="StitchTear_W" id="mf_StitchTear_W" class="dtr-std-inp" data-dir-group="StitchTearW"></td>
                                <td><input type="text" name="StitchTearWRating" id="mf_StitchTearWRating" class="dtr-rate-inp"></td>
                            </tr>
                            <!-- Mullen Burst & Edge Crush -->
                            <tr>
                                <td><b>Mullen Bursting Strength</b> <small class="muted">(kg/cm²)</small></td>
                                <td><input type="number" step="any" name="MullenBurst1" id="mf_MullenBurst1" class="dtr-dir-inp" data-dir-group="MullenBurst"></td>
                                <td><input type="number" step="any" name="MullenBurst2" id="mf_MullenBurst2" class="dtr-dir-inp" data-dir-group="MullenBurst"></td>
                                <td><input type="number" step="any" name="MullenBurst3" id="mf_MullenBurst3" class="dtr-dir-inp" data-dir-group="MullenBurst"></td>
                                <td><input type="text" name="Text3977" id="mf_Text3977" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="MullenBurst" id="mf_MullenBurst" class="dtr-std-inp" data-dir-group="MullenBurst"></td>
                                <td><input type="text" name="MullenBurstRating" id="mf_MullenBurstRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>Die C Tear / Edge Crush (Box/Rubber)</b></td>
                                <td><input type="number" step="any" name="DieC_1" id="mf_DieC_1" class="dtr-dir-inp" data-dir-group="DieC"></td>
                                <td><input type="number" step="any" name="DieC_2" id="mf_DieC_2" class="dtr-dir-inp" data-dir-group="DieC"></td>
                                <td><input type="number" step="any" name="DieC_3" id="mf_DieC_3" class="dtr-dir-inp" data-dir-group="DieC"></td>
                                <td><input type="text" name="DieC_Ave" id="mf_DieC_Ave" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="DieC_Std" id="mf_DieC_Std" class="dtr-std-inp" data-dir-group="DieC"></td>
                                <td><input type="text" name="DieC_Rate" id="mf_DieC_Rate" class="dtr-rate-inp"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION E: COLORFASTNESS (6-POINT MULTI-FIBER STRIP + CROCKING + WEATHERING) -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>E. Colorfastness (6-Fiber Multi-Fiber Evaluation, Crocking, UV, Heat Aging &amp; Hydrolysis)</h4>
                    <span class="muted sm">6-Fiber Rule: Any fiber grade &lt; Standard = <code>FAIL</code>; all 6 fibers &ge; Standard = <code>PASS</code>.</span>
                </div>
                <div class="dtr-table-wrap">
                    <table class="dtr-table dtr-mech-table">
                        <thead>
                            <tr>
                                <th style="width:210px">6-Fiber Colorfastness Test</th>
                                <th>Wool</th>
                                <th>Acrylic</th>
                                <th>Polyester</th>
                                <th>Nylon</th>
                                <th>Cotton</th>
                                <th>Acetate</th>
                                <th>Standard</th>
                                <th>Auto Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><b>CF to Perspiration (Top)</b></td>
                                <td><input type="number" step="0.5" name="CtPWool" id="mf_CtPWool" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CtPAcrylic" id="mf_CtPAcrylic" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CtPPolyester" id="mf_CtPPolyester" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CtPNylon" id="mf_CtPNylon" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CtPCotton" id="mf_CtPCotton" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CtPAcetate" id="mf_CtPAcetate" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CTP" id="mf_CTP" class="dtr-std-inp" data-6pt="CTP"></td>
                                <td><input type="text" name="CTPRating" id="mf_CTPRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>CF to Perspiration (Back)</b></td>
                                <td><input type="number" step="0.5" name="CtPBWool" id="mf_CtPBWool" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPBAcrylic" id="mf_CtPBAcrylic" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPBPolyester" id="mf_CtPBPolyester" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPBNylon" id="mf_CtPBNylon" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPBCotton" id="mf_CtPBCotton" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPBAcetate" id="mf_CtPBAcetate" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPB_S" id="mf_CtPB_S" class="dtr-std-inp" data-6pt="CTPB"></td>
                                <td><input type="text" name="CTPB_Rating" id="mf_CTPB_Rating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>CF to Water (Top)</b></td>
                                <td><input type="number" step="0.5" name="CtWWool" id="mf_CtWWool" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CtWAcrylic" id="mf_CtWAcrylic" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CtWPolyester" id="mf_CtWPolyester" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CtWNylon" id="mf_CtWNylon" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CtWCotton" id="mf_CtWCotton" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CtWAcetate" id="mf_CtWAcetate" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CTW" id="mf_CTW" class="dtr-std-inp" data-6pt="CTW"></td>
                                <td><input type="text" name="CTWRating" id="mf_CTWRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>CF to Water (Back)</b></td>
                                <td><input type="number" step="0.5" name="CtWBackSideWool" id="mf_CtWBackSideWool" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWBackSideAcrylic" id="mf_CtWBackSideAcrylic" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWBackSidePolyester" id="mf_CtWBackSidePolyester" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWBackSideNylon" id="mf_CtWBackSideNylon" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWBackSideCotton" id="mf_CtWBackSideCotton" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWBackSideAcetate" id="mf_CtWBackSideAcetate" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWB_S" id="mf_CtWB_S" class="dtr-std-inp" data-6pt="CTWB"></td>
                                <td><input type="text" name="CtWB_Rating" id="mf_CtWB_Rating" class="dtr-rate-inp"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Crocking, UV, Heat Aging, Hydrolysis -->
                <div class="dtr-grid-6" style="margin-top:10px">
                    <div class="dtr-field">
                        <label>CF Crocking Dry / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="CtCDry" id="mf_CtCDry" class="dtr-crock-inp" placeholder="Val">
                            <input type="number" step="0.5" name="CtCDry_S" id="mf_CtCDry_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="CTCDRYRating" id="mf_CTCDRYRating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                    <div class="dtr-field">
                        <label>CF Crocking Wet / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="CtCWet" id="mf_CtCWet" class="dtr-crock-inp" placeholder="Val">
                            <input type="number" step="0.5" name="CtCWet_S" id="mf_CtCWet_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="CTCWETRating" id="mf_CTCWETRating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                    <div class="dtr-field">
                        <label>UV Light / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="UV" id="mf_UV" class="dtr-crock-inp" placeholder="UV">
                            <input type="number" step="0.5" name="UV_S" id="mf_UV_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="UVRating" id="mf_UVRating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                    <div class="dtr-field">
                        <label>Heat Aging 1..2 / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="HeatAging1" id="mf_HeatAging1" class="dtr-crock-inp" placeholder="1">
                            <input type="number" step="0.5" name="HeatAging2" id="mf_HeatAging2" class="dtr-crock-inp" placeholder="2">
                            <input type="number" step="0.5" name="HeatAging_S" id="mf_HeatAging_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="HeatAgingRating" id="mf_HeatAgingRating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                    <div class="dtr-field">
                        <label>Hydrolysis 1..2 / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="Hydrolysis1" id="mf_Hydrolysis1" class="dtr-crock-inp" placeholder="1">
                            <input type="number" step="0.5" name="Hydrolysis2" id="mf_Hydrolysis2" class="dtr-crock-inp" placeholder="2">
                            <input type="number" step="0.5" name="Hydrolysis_S" id="mf_Hydrolysis_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="HydrolysisRating" id="mf_HydrolysisRating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                    <div class="dtr-field">
                        <label>PVC Migration 1..2 / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="PVC_1" id="mf_PVC_1" class="dtr-crock-inp" placeholder="1">
                            <input type="number" step="0.5" name="PVC_2" id="mf_PVC_2" class="dtr-crock-inp" placeholder="2">
                            <input type="number" step="0.5" name="PVC_S" id="mf_PVC_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="PVC_Rating" id="mf_PVC_Rating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION F: COMPLETE DATABASE COLUMNS INSPECTOR (ALL 68..250 COLUMNS) -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>F. Complete Field Editor (<span id="dtr-raw-col-count">250</span> Fields)</h4>
                    <button type="button" id="btn-toggle-raw-cols" class="dtr-btn-xs dtr-btn-outline">Show / Hide All Fields</button>
                </div>
                <div id="dtr-raw-cols-container" class="dtr-grid-6" style="margin-top:8px;max-height:360px;overflow-y:auto;padding-right:6px">
                    <!-- Dynamically populated with every column of the selected table -->
                </div>
            </div>
        </form>
    </div>
</section>

<!-- =======================================================================
     TAB 3: WHOLE SHOE BONDING TEST FORM (U60 — BondingTestInfo + BondingTestPoints)
     ======================================================================= -->
<section id="dtr-panel-bonding" class="dtr-panel <?= $activeTab === 'bonding' ? '' : 'hidden' ?>">
    <div class="card glass dtr-section-card">
        <div class="dtr-form-pickers" hidden>
            <div class="dtr-field">
                <label>Select Whole Shoe Bonding Record (1,091 Reports)</label>
                <select id="dtr-bonding-record-select" class="dtr-select-lg"></select>
            </div>
        </div>

        <form id="dtr-bonding-form" onsubmit="return false;">
            <input type="hidden" name="BondingTestID" id="bf_BondingTestID" value="">

            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>Bonding Test Info &amp; Age Category Spot Rules (<code>TODDLER</code>: 14 spots · <code>KIDS</code>: 16 spots · <code>CHILDREN</code>: 24 spots · <code>ADULT</code>: 35 spots)</h4>                </div>

                <!-- VF-F01 "paper sheet" layout — mirrors the official PRODUCTION bonding template.xlsx -->
                <div class="vf-sheet">
                    <div class="vf-tpl-head">
                        <img class="vf-tpl-logo" src="<?= base_url('assets/images/vf-logo.webp') ?>" alt="VF">
                        <div class="vf-tpl-title">
                            <div class="vf-tpl-title-en">Bonding Test Results</div>
                            <div class="vf-tpl-title-cn">胶着力测试</div>
                        </div>
                        <img class="vf-tpl-satra" src="<?= base_url('assets/images/satra-accredited.png') ?>" alt="SATRA Accredited Laboratory">
                    </div>

                    <div class="vf-tpl-ids">
                        <div class="vf-tpl-box vf-tpl-formno">Form # : Lab VF 001</div>
                        <div class="vf-tpl-box"><label for="bf_ETRFNo">ETRF No:</label><input type="text" name="ETRFNo" id="bf_ETRFNo" class="mono"></div>
                        <div class="vf-tpl-box"><label for="bf_LabRefNo">* Lab Ref.No:</label><input type="text" name="LabRefNo" id="bf_LabRefNo" class="mono bold" list="bf_LabRefNo_list" autocomplete="off" placeholder="Select Lab Ref.No"><datalist id="bf_LabRefNo_list"></datalist></div>
                    </div>

                    <div class="vf-tpl-body">
                        <div class="vf-tpl-col">
                            <div class="vf-tpl-line"><label for="bf_Factory">Factory (工厂):</label><input type="text" name="Factory" id="bf_Factory"></div>
                            <div class="vf-tpl-line"><label for="bf_AdhesiveSupplier">Adhesive supplier 胶水供应商:</label><input type="text" name="AdhesiveSupplier" id="bf_AdhesiveSupplier"></div>
                            <div class="vf-tpl-line"><label for="bf_ShippedDate">Shipped Date (出货日期):</label><input type="text" name="ShippedDate" id="bf_ShippedDate"></div>
                            <div class="vf-tpl-line"><label for="bf_ModelStyle">Model/Style/PGSC (英文鞋型):</label><input type="text" name="ModelStyle" id="bf_ModelStyle"></div>
                            <div class="vf-tpl-line"><label for="bf_Color">Color (颜色):</label><input type="text" name="Color" id="bf_Color"></div>
                            <div class="vf-tpl-line"><label for="bf_ProductionDate">Production Date (生产日期):</label><input type="text" name="ProductionDate" id="bf_ProductionDate"></div>
                            <div class="vf-tpl-line"><label for="bf_POLotNO">PO/Lot No (订单号码):</label><input type="text" name="POLotNO" id="bf_POLotNO" class="mono"></div>
                            <div class="vf-tpl-line"><label for="bf_POQuantity">PO Quantity (订单数量):</label><input type="text" name="POQuantity" id="bf_POQuantity"></div>
                            <div class="vf-tpl-line"><label for="bf_Sendby">Send by (送达):</label><input type="text" name="Sendby" id="bf_Sendby"></div>
                        </div>

                        <div class="vf-tpl-col">
                            <select name="MaterialSource" id="bf_MaterialSource" class="vf-tpl-hidden" tabindex="-1" aria-hidden="true">
                                <option value="Prod. Material">Prod. Material (量产)</option>
                                <option value="Pro. Shoes">Pro. Shoes (量产鞋)</option>
                                <option value="Pro. Re-test">Pro. Re-test (量产重测)</option>
                                <option value="NewDevelopment">New / Development (开发)</option>
                                <option value="Sales Sample">Sales Sample (销样)</option>
                                <option value="Trial experiment">Trial experiment (试胶试验)</option>
                            </select>
                            <div class="vf-tpl-radios" id="bf_MaterialSource_radios" role="radiogroup" aria-label="Test Type">
                                <label><input type="radio" name="bf_ms_radio" value="Prod. Material"> Prod. Material 量产</label>
                                <label><input type="radio" name="bf_ms_radio" value="Pro. Shoes"> Pro. Shoes 量产鞋</label>
                                <label><input type="radio" name="bf_ms_radio" value="Pro. Re-test"> Pro. Re-test 量产重测</label>
                                <label><input type="radio" name="bf_ms_radio" value="NewDevelopment"> New/Development 开发</label>
                                <label><input type="radio" name="bf_ms_radio" value="Sales Sample"> Sales Sample 销样</label>
                                <label><input type="radio" name="bf_ms_radio" value="Trial experiment"> Trial experiment 试胶试验</label>
                            </div>
                            <div class="vf-tpl-line"><label for="bf_ReceivedDate">Received Date 收到日期:</label><input type="text" name="ReceivedDate" id="bf_ReceivedDate"></div>
                            <div class="vf-tpl-line"><label for="bf_TestedDate">Tested Date 测试日期:</label><input type="text" name="TestedDate" id="bf_TestedDate"></div>
                            <div class="vf-tpl-line"><label for="bf_FinishedDate">Finished Date 完成日期:</label><input type="text" name="FinishedDate" id="bf_FinishedDate"></div>
                            <div class="vf-tpl-line"><label for="bf_Technician">Technician 技术员:</label><input type="text" name="Technician" id="bf_Technician"></div>
                        </div>
                    </div>

                    <div class="vf-sheet-grid vf-sheet-grid--extra">
                        <div class="vf-sheet-cell"><label>* Age Category <span class="cn">年龄分类</span></label>
                            <select name="AgeCategory" id="bf_AgeCategory">
                                <option value="ADULT">ADULT (35 Spots)</option>
                                <option value="CHILDREN">CHILDREN (24 Spots)</option>
                                <option value="KIDS">KIDS (16 Spots)</option>
                                <option value="TODDLER">TODDLER (14 Spots)</option>
                            </select>
                        </div>
                        <div class="vf-sheet-cell"><label>* Standard Spec (kgf/cm)</label>
                            <select name="STD" id="bf_STD">
                                <option value="3.0">3.0 kgf/cm (Adult / VF-F01)</option>
                                <option value="2.5">2.5 kgf/cm (Children)</option>
                                <option value="2.0">2.0 kgf/cm (Kids/Toddler)</option>
                            </select>
                        </div>
                        <div class="vf-sheet-cell"><label>Shoe Side</label>
                            <select name="Side" id="bf_Side">
                                <option value="Left">Left</option>
                                <option value="Right">Right</option>
                            </select>
                        </div>
                        <div class="vf-sheet-cell"><label>* Overall Result</label><input type="text" name="Result" id="bf_Result" class="bold mono dtr-rate-inp" readonly></div>
                        <div class="vf-sheet-cell"><label>Mode of Failure(s) <span class="cn">失败的方式</span></label><input type="text" name="ModeOfFailure" id="bf_ModeOfFailure"></div>
                        <div class="vf-sheet-cell"><label>Possible Cause(s) <span class="cn">可能的原因</span></label><input type="text" name="PossibleCause" id="bf_PossibleCause"></div>
                    </div>
                    <script>
                    (function () {
                        var sel = document.getElementById('bf_MaterialSource');
                        var wrap = document.getElementById('bf_MaterialSource_radios');
                        if (!sel || !wrap) return;
                        var radios = wrap.querySelectorAll('input[type=radio]');
                        function sync() { radios.forEach(function (r) { r.checked = (r.value === sel.value); }); }
                        var d = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
                        Object.defineProperty(sel, 'value', {
                            configurable: true,
                            get: function () { return d.get.call(this); },
                            set: function (v) { d.set.call(this, v); sync(); }
                        });
                        radios.forEach(function (r) {
                            r.addEventListener('change', function () {
                                if (!r.checked) return;
                                d.set.call(sel, r.value);
                                sel.dispatchEvent(new Event('change', { bubbles: true }));
                            });
                        });
                        sync();
                    })();
                    </script>
                </div>
            </div>

            <!-- Results table / shoe diagram / sign-off — mirrors the VF-F01 template body -->
            <div class="vf-sheet vf-body" id="vf-body">
                <table class="vf-res">
                    <thead>
                        <tr>
                            <th rowspan="2" class="vf-res-part">Shoe Part</th>
                            <th rowspan="2" class="vf-res-w1">VF Test<br>Method</th>
                            <th rowspan="2" class="vf-res-w2">STD<br>kgf/cm</th>
                            <th colspan="3">Test Result<br>kgf/cm</th>
                            <th colspan="2">Status</th>
                            <th rowspan="2" class="vf-res-mode">Mode of Failure(s)<br><span class="cn">失败的方式</span></th>
                            <th rowspan="2" class="vf-res-cause">Possible Cause(s)<br><span class="cn">可能的原因</span></th>
                        </tr>
                        <tr>
                            <th class="vf-res-n">Min</th><th class="vf-res-n">Max</th><th class="vf-res-n">Avg</th>
                            <th class="vf-res-s">Pass</th><th class="vf-res-s">Fail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="vf-res-part-cell">UPPER TO FOXING</td>
                            <td class="vf-res-c">VF-F01<br>(2019/05)</td>
                            <td class="vf-res-c vf-res-std"><b class="vf-std-disp">3.00</b></td>
                            <td><input type="text" id="bp_UTFMin" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_UTFMax" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_UTFAverage" readonly class="vf-res-inp bold"></td>
                            <td class="vf-res-mark" data-for="bp_UTF_Result" data-side="pass"></td>
                            <td class="vf-res-mark" data-for="bp_UTF_Result" data-side="fail"></td>
                            <td rowspan="8" class="vf-res-legend">
                                <p><b>AF=</b> Failure at the adhesive/substrate interface or the adhesive/primer interface</p>
                                <p><b>CF=</b> Cohesive Failure of the adhesive (failure within the adhesive or between the two layers of adhesive).</p>
                                <p><b>PF=</b> Failure at the primer/substrate interface</p>
                                <p><b>SF=</b> Failure of the substrate, including the location of the failure within the substrate</p>
                            </td>
                            <td rowspan="8" class="vf-res-legend vf-res-causes">
                                <p>(a) Poor Priming <span class="cn">药水不良</span></p>
                                <p>(b) Poor cementing <span class="cn">胶水不良</span></p>
                                <p>(c) Over-dried cement <span class="cn">积胶</span></p>
                                <p>(d) Wet primer <span class="cn">湿的药水</span></p>
                                <p>(e) Wet cement <span class="cn">湿的胶水</span></p>
                                <p>(f) Poor pressing <span class="cn">压机不良</span></p>
                                <p>(g) Poor Roughing <span class="cn">打粗不良</span></p>
                                <p>(h) Upper material - weak <span class="cn">鞋面材料不良</span></p>
                                <p>(i) EVA-Weak <span class="cn">EVA 不良</span></p>
                                <p>(j) Dusty component <span class="cn">粉沫</span></p>
                                <p>(k) Splitting of foxing tape <span class="cn">围条分层</span></p>
                            </td>
                        </tr>
                        <tr>
                            <td class="vf-res-part-cell">FOXING TO OUTSOLE</td>
                            <td class="vf-res-c">VF-F01<br>(2019/05)</td>
                            <td class="vf-res-c vf-res-std"><b class="vf-std-disp">3.00</b></td>
                            <td><input type="text" id="bp_FTOMin" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_FTOMax" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_FTOAverage" readonly class="vf-res-inp bold"></td>
                            <td class="vf-res-mark" data-for="bp_FTO_Result" data-side="pass"></td>
                            <td class="vf-res-mark" data-for="bp_FTO_Result" data-side="fail"></td>
                        </tr>
                        <tr>
                            <td class="vf-res-part-cell">FILLER TO OUTSOLE <span class="vf-res-sub">(1..13)</span></td>
                            <td class="vf-res-c">VF-F01<br>(2019/05)</td>
                            <td class="vf-res-c vf-res-std"><b>1.90</b></td>
                            <td><input type="text" id="bp_FiTOMin" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_FiTOMax" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_FiTOAverage" readonly class="vf-res-inp bold"></td>
                            <td class="vf-res-mark" data-for="bp_FiTO_Result" data-side="pass"></td>
                            <td class="vf-res-mark" data-for="bp_FiTO_Result" data-side="fail"></td>
                        </tr>
                        <tr class="vf-res-blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr class="vf-res-blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr class="vf-res-blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr class="vf-res-blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr class="vf-res-blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                    </tbody>
                </table>

                <!-- hidden originals kept so dtr.js can read / write the per-part result -->
                <div class="vf-tpl-hidden" aria-hidden="true">
                    <input type="text" id="bp_UTF_Result" readonly class="dtr-rate-inp" tabindex="-1">
                    <input type="text" id="bp_FTO_Result" readonly class="dtr-rate-inp" tabindex="-1">
                    <input type="text" id="bp_FiTO_Result" readonly class="dtr-rate-inp" tabindex="-1">
                </div>

                <div class="vf-diag">
                    <div class="vf-diag-notes">
                        <div class="vf-diag-note-l">1.Outside Value: Fabric / PU / EVA (Left)kgf/cm<br>2.Inside Value:</div>
                        <div class="vf-diag-note-c">Center: Fabric / PU / EVA kgf/cm</div>
                        <div class="vf-diag-note-r">1.Outside Value:Fabric / PU / EVA kgf/cm<br>2.Inside Value:</div>
                    </div>
                    <div class="vf-diag-row">
                        <div id="vf-diag-left" class="vf-shoe-card vf-diag-card"><img src="<?= base_url('assets/images/bonding_sketches/blank.png') ?>" class="vf-shoe-img" style="left:12%;top:8%;width:76%;height:84%" alt="Left shoe"></div>
                        <div class="vf-diag-strips">
                            <div><span>Left</span><div class="vf-diag-strip"></div></div>
                            <div><span>Right</span><div class="vf-diag-strip"></div></div>
                        </div>
                        <div id="vf-diag-right" class="vf-shoe-card vf-diag-card"><img src="<?= base_url('assets/images/bonding_sketches/blank.png') ?>" class="vf-shoe-img" style="left:12%;top:8%;width:76%;height:84%" alt="Right shoe"></div>
                    </div>
                </div>

                <div class="vf-foot">
                    <div class="vf-foot-comments"><label for="bf_Remarks">Comments <span class="cn">备注:</span></label><input type="text" name="Remarks" id="bf_Remarks"></div>
                    <div class="vf-foot-signs">
                        <div class="vf-foot-sign"><label for="bf_ReviewedBy">Reviewed by <span class="cn">审查:</span></label><input type="text" name="ReviewedBy" id="bf_ReviewedBy"></div>
                        <div class="vf-foot-sign"><label for="vf_ApprovedBy">Approved by <span class="cn">核准:</span></label><input type="text" id="vf_ApprovedBy" value="QA Laboratory Head" readonly tabindex="-1" title="Set by the approval signatory (Report Designer → Signatories); not saved with the report"></div>
                    </div>
                </div>

                <script>
                (function () {
                    var d = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
                    function paint(id) {
                        var el = document.getElementById(id);
                        var v = String((el && d.get.call(el)) || '').toUpperCase();
                        document.querySelectorAll('.vf-res-mark[data-for="' + id + '"]').forEach(function (td) {
                            var pass = td.getAttribute('data-side') === 'pass';
                            td.className = 'vf-res-mark';
                            td.textContent = '';
                            if (v === 'PASS' && pass) { td.textContent = '✓'; td.classList.add('is-pass'); }
                            else if (v.indexOf('TOL') === 0 && pass) { td.textContent = 'TOL'; td.classList.add('is-tol'); }
                            else if (v === 'FAIL' && !pass) { td.textContent = '✗'; td.classList.add('is-fail'); }
                        });
                    }
                    ['bp_UTF_Result', 'bp_FTO_Result', 'bp_FiTO_Result'].forEach(function (id) {
                        var el = document.getElementById(id);
                        if (!el) return;
                        Object.defineProperty(el, 'value', {
                            configurable: true,
                            get: function () { return d.get.call(this); },
                            set: function (v) { d.set.call(this, v); paint(id); }
                        });
                        paint(id);
                    });

                    var std = document.getElementById('bf_STD');
                    function showStd() {
                        var n = parseFloat(std && std.value);
                        var txt = isNaN(n) ? '' : n.toFixed(2);
                        document.querySelectorAll('.vf-std-disp').forEach(function (b) { b.textContent = txt; });
                    }
                    if (std) {
                        var sd = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
                        Object.defineProperty(std, 'value', {
                            configurable: true,
                            get: function () { return sd.get.call(this); },
                            set: function (v) { sd.set.call(this, v); showStd(); }
                        });
                        std.addEventListener('change', showStd);
                        showStd();
                    }
                })();
                </script>
            </div>

            <!-- 35-Spot Interactive Grid -->
            <div class="dtr-subcard" id="dtr-bonding-spots-card" hidden>
                <div class="dtr-subcard-head">
                    <h4>Bonding Spot Readings (Points 1 to 35)</h4>
                    <span class="muted sm">Tolerance Rule (80% of Std, per VF-F01): Std 3.0 (Adult) &rarr; TOLERABLE 2.4..2.99, FAIL &lt;2.4. Std 2.5 (Children) &rarr; TOLERABLE 2.0..2.49, FAIL &lt;2.0. Std 2.0 (Kids/Toddler) &rarr; TOLERABLE 1.6..1.99, FAIL &lt;1.6.</span>
                </div>
                <div class="dtr-table-wrap" style="max-height:420px">
                    <table class="dtr-table dtr-mech-table">
                        <thead>
                            <tr>
                                <th style="width:90px">Spot #</th>
                                <th>Upper to Foxing (kg/cm)</th>
                                <th>Foxing to Outsole (kg/cm)</th>
                                <th>Filler to Outsole (1..13 kg/cm)</th>
                            </tr>
                        </thead>
                        <tbody id="dtr-bonding-points-tbody">
                            <?php for ($pt = 1; $pt <= 35; $pt++): ?>
                                <tr data-spot-row="<?= $pt ?>">
                                    <td class="mono"><b>Spot <?= $pt ?></b></td>
                                    <td><input type="number" step="any" id="bp_UTFpoint<?= $pt ?>" data-bp-col="UTFpoint<?= $pt ?>" class="dtr-bp-inp"></td>
                                    <td><input type="number" step="any" id="bp_FTOpoint<?= $pt ?>" data-bp-col="FTOpoint<?= $pt ?>" class="dtr-bp-inp"></td>
                                    <td>
                                        <?php if ($pt <= 13): ?>
                                            <input type="number" step="any" id="bp_FiTO<?= $pt ?>" data-bp-col="FiTO<?= $pt ?>" class="dtr-bp-inp">
                                        <?php else: ?>
                                            <span class="muted sm">—</span>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endfor ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        <!-- ===== Pull Footwear Test Results (Form # Lab VF 003), shown when "Pull Test Report" is picked ===== -->
        <?php
            $pullItems = [
                ['Eyelet claw strength', ''],
                ['Hook&amp;eyelet attachment metal', ''],
                ['Hook&amp;eyelet attachment carted metal', ''],
                ['Hook&amp;eyelet attachment - plastic', ''],
                ['Webbing loop holder', ''],
                ['Webbing Heel Pull Tab', '35'],
            ];
            $pullSketch = static function (string $side): string {
                $eyelets = '';
                foreach ([[44, 70], [40, 108], [38, 146], [38, 184], [40, 222]] as $p) {
                    $eyelets .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="4"/><circle cx="' . (200 - $p[0]) . '" cy="' . $p[1] . '" r="4"/>';
                }

                return '<svg viewBox="0 0 200 300" class="pf-shoe" role="img" aria-label="' . $side . ' shoe"><text x="100" y="18" text-anchor="middle" class="pf-shoe-t">' . $side . '</text>'
                    . '<path d="M34 270 C26 200 28 120 52 64 C70 28 130 28 148 64 C172 120 174 200 166 270 Z" fill="#fff" stroke="#111" stroke-width="1.5"/>'
                    . '<path d="M60 250 C56 190 58 130 74 90 C84 66 116 66 126 90 C142 130 144 190 140 250" fill="none" stroke="#111" stroke-width="1"/>'
                    . '<g fill="#fff" stroke="#111" stroke-width="1.2">' . $eyelets . '</g></svg>';
            };
        ?>
        <form id="dtr-pull-form" onsubmit="return false;" hidden>
            <input type="hidden" name="ID" id="pf_ID" value="">
            <div class="vf-sheet pf-sheet">
                <div class="vf-tpl-head">
                    <img class="vf-tpl-logo" src="<?= base_url('assets/images/vf-logo.webp') ?>" alt="VF">
                    <div class="vf-tpl-title">
                        <div class="vf-tpl-title-en">Pull Footwear Test Results</div>
                        <div class="vf-tpl-title-cn">整鞋部件拉力测试</div>
                    </div>
                    <img class="vf-tpl-satra" src="<?= base_url('assets/images/satra-accredited.png') ?>" alt="SATRA Accredited Laboratory">
                </div>

                <div class="vf-tpl-ids">
                    <div class="vf-tpl-box vf-tpl-formno">Form # : Lab VF 003</div>
                    <div class="vf-tpl-box"><label for="pf_ETRFNo">ETRF No:</label><input type="text" name="ETRFNo" id="pf_ETRFNo" class="mono"></div>
                    <div class="vf-tpl-box"><label for="pf_LabRefNo">* Lab Ref.No:</label><input type="text" name="LabRefNo" id="pf_LabRefNo" class="mono bold"></div>
                </div>

                <div class="vf-tpl-body">
                    <div class="vf-tpl-col">
                        <div class="vf-tpl-line"><label for="pf_Supplier">Supplier/Factory (厂商):</label><input type="text" name="Supplier" id="pf_Supplier"></div>
                        <div class="vf-tpl-line"><label for="pf_SupplierCountry">Supplier's country (国家):</label><input type="text" name="SupplierCountry" id="pf_SupplierCountry"></div>
                        <div class="vf-tpl-line"><label for="pf_MaterialDescription">Material Description (材料):</label><input type="text" name="MaterialDescription" id="pf_MaterialDescription"></div>
                        <div class="vf-tpl-line"><label for="pf_ModelNoStyle">Model No./Style (鞋体):</label><input type="text" name="ModelNoStyle" id="pf_ModelNoStyle"></div>
                        <div class="vf-tpl-line"><label for="pf_Color">Color (颜色):</label><input type="text" name="Color" id="pf_Color"></div>
                        <div class="vf-tpl-line"><label for="pf_POLotNo">P.O/Lot No (订单号码):</label><input type="text" name="POLotNo" id="pf_POLotNo" class="mono"></div>
                        <div class="vf-tpl-line"><label for="pf_POQuantity">PO Quantity (订单数量):</label><input type="text" name="POQuantity" id="pf_POQuantity"></div>
                        <div class="vf-tpl-line"><label for="pf_SendBy">Send by (送达方):</label><input type="text" name="SendBy" id="pf_SendBy"></div>
                    </div>
                    <div class="vf-tpl-col">
                        <input type="hidden" name="MaterialSource" id="pf_MaterialSource">
                        <div class="vf-tpl-radios" id="pf_MaterialSource_radios" role="radiogroup" aria-label="Test Type">
                            <label><input type="radio" name="pf_ms_radio" value="Prod. Material"> Prod. Material 量产材料</label>
                            <label><input type="radio" name="pf_ms_radio" value="Prod. Re-test"> Prod. Re-test 量产重测</label>
                            <label><input type="radio" name="pf_ms_radio" value="New/Development"> New/Development 开发新材料</label>
                            <label><input type="radio" name="pf_ms_radio" value="Trial Test"> Trial Test 试胶测试</label>
                            <label><input type="radio" name="pf_ms_radio" value="Sales Sample"> Sales Sample 销售样品</label>
                        </div>
                        <input type="hidden" name="TechSlot" id="pf_TechSlot">
                        <div class="vf-tpl-radios pf-techs" id="pf_TechSlot_radios" role="radiogroup" aria-label="Technician slot">
                            <?php for ($t = 1; $t <= 4; $t++): ?>
                                <label><input type="radio" name="pf_tech_radio" value="Tech <?= $t ?>"> Tech <?= $t ?></label>
                            <?php endfor ?>
                        </div>
                        <div class="vf-tpl-line"><label for="pf_ReceivedDate">Received Date 收到日期:</label><input type="text" name="ReceivedDate" id="pf_ReceivedDate"></div>
                        <div class="vf-tpl-line"><label for="pf_TestedDate">Tested Date 测试日期:</label><input type="text" name="TestedDate" id="pf_TestedDate"></div>
                        <div class="vf-tpl-line"><label for="pf_FinishedDate">Finished Date 完成日期:</label><input type="text" name="FinishedDate" id="pf_FinishedDate"></div>
                        <div class="vf-tpl-line"><label for="pf_Technician">Technician 技术员:</label><input type="text" name="Technician" id="pf_Technician"></div>
                        <div class="vf-tpl-line pf-result"><label for="pf_Result">* Overall Result:</label><input type="text" name="Result" id="pf_Result" class="bold mono dtr-rate-inp" readonly></div>
                    </div>
                </div>

                <div class="pf-section">Closures - Eyelet , D-ring , Hook , Webbing Pull Strength</div>
                <div class="pf-wrap">
                <table class="vf-res pf-res">
                    <thead>
                        <tr>
                            <th rowspan="2" class="pf-c-item">Test Items<br><span class="cn">测试项目</span></th>
                            <th rowspan="2" class="pf-c-m">VF Test Method<br><span class="cn">测试方法</span></th>
                            <th rowspan="2" class="pf-c-u">Units<br><span class="cn">单位</span></th>
                            <th rowspan="2" class="pf-c-sk">Test Sketch<br><span class="cn">测试部位</span></th>
                            <th colspan="2">Test Results<br><span class="cn">测试结果</span></th>
                            <th rowspan="2" class="pf-c-n">Maximum</th>
                            <th rowspan="2" class="pf-c-n">Standard<br><span class="cn">标准</span></th>
                            <th rowspan="2" class="pf-c-n">Tolerance<br><span class="cn">容许度</span></th>
                            <th rowspan="2" class="pf-c-n">Rating<br><span class="cn">等级</span></th>
                        </tr>
                        <tr><th class="pf-c-r">Lateral side:</th><th class="pf-c-r">Medial side:</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (['left' => 'Left', 'right' => 'Right'] as $sk => $sideLabel): ?>
                            <?php foreach ($pullItems as $i => $it): ?>
                                <tr>
                                    <td class="vf-res-part-cell"><?= $it[0] ?></td>
                                    <td class="vf-res-c">VF-B04<br>(2019/05)</td>
                                    <td class="vf-res-c">kgf</td>
                                    <?php if ($i === 0): ?><td rowspan="<?= count($pullItems) ?>" class="pf-sketch"><?= $pullSketch($sideLabel) ?></td><?php endif ?>
                                    <td><input type="number" step="any" class="vf-res-inp" data-pull="<?= $sk ?>.<?= $i ?>.lat"></td>
                                    <td><input type="number" step="any" class="vf-res-inp" data-pull="<?= $sk ?>.<?= $i ?>.med"></td>
                                    <td><input type="text" readonly class="vf-res-inp bold" data-pull="<?= $sk ?>.<?= $i ?>.max"></td>
                                    <td><input type="text" class="vf-res-inp pf-std" data-pull="<?= $sk ?>.<?= $i ?>.std" value="<?= $it[1] ?>"></td>
                                    <td><input type="text" class="vf-res-inp pf-tol" data-pull="<?= $sk ?>.<?= $i ?>.tol" value="<?= $it[1] !== '' ? 'minimum' : '' ?>"></td>
                                    <td><input type="text" readonly class="vf-res-inp bold dtr-rate-inp" data-pull="<?= $sk ?>.<?= $i ?>.rating"></td>
                                </tr>
                            <?php endforeach ?>
                        <?php endforeach ?>
                        <tr><td colspan="10" class="pf-other-head">Other Types of Pull Test</td></tr>
                        <?php for ($o = 0; $o < 4; $o++): ?>
                            <tr>
                                <td><input type="text" class="vf-res-inp" data-pull="other.<?= $o ?>.item"></td>
                                <td><input type="text" class="vf-res-inp" data-pull="other.<?= $o ?>.method"></td>
                                <td><input type="text" class="vf-res-inp" data-pull="other.<?= $o ?>.unit"></td>
                                <td></td>
                                <td><input type="number" step="any" class="vf-res-inp" data-pull="other.<?= $o ?>.lat"></td>
                                <td><input type="number" step="any" class="vf-res-inp" data-pull="other.<?= $o ?>.med"></td>
                                <td><input type="text" readonly class="vf-res-inp bold" data-pull="other.<?= $o ?>.max"></td>
                                <td><input type="text" class="vf-res-inp pf-std" data-pull="other.<?= $o ?>.std"></td>
                                <td><input type="text" class="vf-res-inp pf-tol" data-pull="other.<?= $o ?>.tol"></td>
                                <td><input type="text" readonly class="vf-res-inp bold dtr-rate-inp" data-pull="other.<?= $o ?>.rating"></td>
                            </tr>
                        <?php endfor ?>
                    </tbody>
                </table>
                </div>

                <div class="pf-foot">
                    <div class="pf-comments"><label for="pf_Comments">Comments 备注 :</label><textarea name="Comments" id="pf_Comments" rows="2"></textarea></div>
                    <div class="pf-sign">
                        <div class="vf-tpl-line"><label for="pf_ReviewedBy">Reviewed by 检查:</label><input type="text" name="ReviewedBy" id="pf_ReviewedBy"></div>
                        <div class="vf-tpl-line"><label for="pf_ApprovedBy">Approved by 核准:</label><input type="text" name="ApprovedBy" id="pf_ApprovedBy"></div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<!-- =======================================================================
     TAB 4: BUILT-IN LAB CALCULATORS & STANDARDS / DSR LOOKUP EXPLORER
     ======================================================================= -->
<section id="dtr-panel-calculators" class="dtr-panel <?= $activeTab === 'calculators' ? '' : 'hidden' ?>">
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
            <div id="dtr-print-sheet-container">
                <!-- Dynamically rendered printable report sheet -->
            </div>
        </div>
        <div class="dtr-modal-footer">
            <button type="button" id="btn-dtr-cancel-print" class="dtr-btn dtr-btn-outline">Close</button>
            <button type="button" id="btn-dtr-download-pdf" class="dtr-btn dtr-btn-outline" style="border-color:#2563eb;color:#2563eb">
                <svg class="ic" aria-hidden="true"><use href="#i-file"/></svg> Download PDF
            </button>
            <button type="button" id="btn-dtr-do-print" class="dtr-btn dtr-btn-primary">
                <svg class="ic" aria-hidden="true"><use href="#i-printer"/></svg> Print Report / Save as PDF
            </button>
        </div>
    </div>
</div>


<!-- Toast Notification -->
<div id="dtr-toast" class="dtr-toast hidden"></div>
