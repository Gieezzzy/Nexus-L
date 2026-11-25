<?php
$dtrIsAdmin = strcasecmp((string) (service('auth')->user()['role'] ?? ''), 'admin') === 0;
$bondingShoes = $bondingShoes ?? [];
// Row colour of a list by its result: FAIL(ED) red, PASS green, TOLERABLE blue; no result / pending = no colour
$rowTint = static function (string $res): string {
    $r = strtoupper(trim($res));
    if ($r === '') {
        return '';
    }

    return (str_starts_with($r, 'FAIL') || $r === 'ERROR') ? 'row-fail' : (str_starts_with($r, 'TOL') ? 'row-tol' : (str_starts_with($r, 'PASS') ? 'row-pass' : ''));
};
// Result chip: FAIL(ED) = red, PASS = green, TOLERABLE = blue; nothing / pending stays plain
$resChip = static function (string $res, string $none = '-'): string {
    $r = strtoupper(trim($res));
    if ($r === '') {
        return $none;
    }
    $cls = (str_starts_with($r, 'FAIL') || $r === 'ERROR') ? 'res-fail' : (str_starts_with($r, 'TOL') ? 'res-tol' : (str_starts_with($r, 'PASS') ? 'res-pass' : ''));

    return $cls === '' ? esc($r) : '<span class="badge res-chip ' . $cls . '">' . esc($r) . '</span>';
};
$wholeShoes = $wholeShoes ?? [];
$bondingTotal = $bondingTotal ?? 0;
$shoeTotal = $shoeTotal ?? 0;
$containers = $containers ?? [];
$materialsMaster = $materialsMaster ?? [];
$recentReports = $recentReports ?? [];
$catRows = $catRows ?? [];
$totalReports = $totalReports ?? 0;
$totalPass = $totalPass ?? 0;
$totalFail = $totalFail ?? 0;
$labImg    = $labImg ?? ['bonding' => true, 'shoes' => false, 'containers' => false];
// the small report picture beside a Lab Ref No (Settings > Lab Report Settings decides per tab whether it is shown and where it is read from)
$labThumb  = static function (string $tab, string $ref) use ($labImg): string {
    if (empty($labImg[$tab]) || trim($ref) === '') {
        return '';
    }

    return '<span class="bs-thumb-wrap" title="Open the report picture (circle / highlight / stamp)"><img class="bs-thumb" src="' . esc(site_url('dtr/bonding-image') . '?thumb=1&tab=' . $tab . '&lab_ref=' . rawurlencode($ref), 'attr') . '" data-lab-ref="' . esc($ref, 'attr') . '" data-tab="' . $tab . '" alt="" loading="lazy" onerror="window.bsThumbErr?window.bsThumbErr(this):this.parentNode.classList.add(\'none\')"><i class="bs-thumb-ph"></i></span> ';
};
$totalTol = $totalTol ?? 0;
$standardMatTypes = $standardMatTypes ?? [];
$activeTab = $activeTab ?? (string) (service('request')->getGet('tab') ?? 'tracker');
?>
<?php
/**
 * NEXUS-L QC Lab Digital Test Reports (#Digital Test Report & Bonding LIS System)
 * Migrated 100% from MS Access & VB.NET into MySQL dbnexus (367,333 rows across 31 tables).
 */
?>

<?= view('partials/plain_help') ?>
<?= anGuide('dtr', 'New here? How this page works', [
    'Use the left menu to pick what you need: the Lab Tracker (every test and how far along it is), Material tests, Shoe Bonding, Whole Shoe tests, or the Calculators.',
    'Find a report by typing its Lab Ref No, TR No, style or supplier in the search box, then press Search.',
    'Click a row to open its report. The colour of a result tells you the outcome: green = passed, blue = tolerable (slightly outside standard but accepted), red = failed.',
    'Fill in or check the readings. Averages and Pass / Fail are worked out for you, so you only type what the machine or the operator measured.',
    'When the report is final, use the preview to check how the official sheet will look, then print it or send it by email.',
], 'Tip: the small "i" badges explain unfamiliar words. Hover over one, or tap it on a phone.') ?>

<!-- Top Summary KPI Bar -->
<?php
$totInit  = (int) $totalReports;
$passInit = (int) $totalPass;
$tolInit  = (int) $totalTol;
$failInit = (int) $totalFail;
$finInit  = $passInit + $tolInit + $failInit;
$ongInit  = max(0, $totInit - $finInit);
$kpiPct   = static fn (int $n, int $d): string => $d > 0 ? rtrim(rtrim(number_format($n / $d * 100, 1), '0'), '.') . '%' : '0%';
?>
<div class="dtr-top-kpis">
    <div class="card glass dtr-kpi">
        <div>
            <small>Total Lab Test Reports</small>
            <div class="dtr-kpi-row">
                <b id="dtr-kpi-total"><?= number_format($totInit) ?></b>
                <em id="dtr-kpi-total-pct" class="dtr-kpi-pct pct-blue"><?= $kpiPct($finInit, $totInit) ?> Done</em>
            </div>
            <span class="dtr-kpi-sub">10 Material Forms + Shoe Bonding</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-blue"><svg class="ic" aria-hidden="true"><use href="#i-flask"/></svg></span>
    </div>
    <div class="card glass dtr-kpi" data-kno="1" title="Shoes that already have a result (Passed + Tolerable + Failed)">
        <div>
            <small>Finished Shoes Tested</small>
            <div class="dtr-kpi-row">
                <b id="dtr-kpi-fin"><?= number_format($finInit) ?></b>
                <em id="dtr-kpi-fin-pct" class="dtr-kpi-pct pct-blue"><?= $kpiPct($finInit, $totInit) ?></em>
            </div>
            <span class="dtr-kpi-sub">With a test result</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-blue">&#10004;</span>
    </div>
    <div class="card glass dtr-kpi" data-kno="1" title="Specimens currently pending / undergoing lab testing">
        <div>
            <small>On Going Testing</small>
            <div class="dtr-kpi-row">
                <b id="dtr-kpi-ongoing"><?= number_format($ongInit) ?></b>
                <em id="dtr-kpi-ongoing-pct" class="dtr-kpi-pct pct-slate"><?= $kpiPct($ongInit, $totInit) ?></em>
            </div>
            <span class="dtr-kpi-sub">Pending test result</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-blue"><svg class="ic" aria-hidden="true"><use href="#i-refresh"/></svg></span>
    </div>
    <div class="card glass dtr-kpi" data-kno="1" title="Tests scheduled on the previous working day that did not get a result: carried to this day and counted in the Total">
        <div>
            <small>Pending</small>
            <div class="dtr-kpi-row">
                <b id="dtr-kpi-pending" class="dtr-txt-warn">0</b>
                <em id="dtr-kpi-pending-pct" class="dtr-kpi-pct pct-warn">0%</em>
            </div>
            <span class="dtr-kpi-sub">Carried from the last working day</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-warn">&#9203;</span>
    </div>
    <div class="card glass dtr-kpi">
        <div>
            <small>Passed Reports</small>
            <div class="dtr-kpi-row">
                <b id="dtr-kpi-pass" class="dtr-txt-pass"><?= number_format($passInit) ?></b>
                <em id="dtr-kpi-pass-pct" class="dtr-kpi-pct pct-pass"><?= $kpiPct($passInit, $totInit) ?></em>
            </div>
            <span class="dtr-kpi-sub">Meets VF / Vans Specification</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-pass">✓</span>
    </div>
    <div class="card glass dtr-kpi">
        <div>
            <small>Tolerable / Data</small>
            <div class="dtr-kpi-row">
                <b id="dtr-kpi-tol" class="dtr-txt-warn"><?= number_format($tolInit) ?></b>
                <em id="dtr-kpi-tol-pct" class="dtr-kpi-pct pct-warn"><?= $kpiPct($tolInit, $totInit) ?></em>
            </div>
            <span class="dtr-kpi-sub">Within Tolerance Band</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-warn"><svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg></span>
    </div>
    <div class="card glass dtr-kpi">
        <div>
            <small>Failed Reports</small>
            <div class="dtr-kpi-row">
                <b id="dtr-kpi-fail" class="dtr-txt-fail"><?= number_format($failInit) ?></b>
                <em id="dtr-kpi-fail-pct" class="dtr-kpi-pct pct-fail"><?= $kpiPct($failInit, $totInit) ?></em>
            </div>
            <span class="dtr-kpi-sub">Requires Retest / Action</span>
        </div>
        <span class="dtr-kpi-ico dtr-ico-fail">✕</span>
    </div>

</div>

<!-- Toolbar of the open panel (the sidebar switches panels) -->
<div id="dtr-nav-bar" class="card glass dtr-nav-bar" <?= $activeTab === 'labtracker' ? 'hidden' : '' ?>>
    <div class="dtr-nav-actions" data-dtr-for="tracker" <?= $activeTab === 'tracker' ? '' : 'hidden' ?>>
        <div class="dtr-search-box">
            <input type="text" id="dtr-tracker-search" placeholder="Search LabRefNo, TR No, Material Code, Style, Supplier, MTL...">
            <button type="button" id="dtr-tracker-btn-search" class="dtr-btn dtr-btn-primary">Search</button>
        </div>
    </div>
    <div class="dtr-nav-actions" data-dtr-for="labtracker" <?= $activeTab === 'labtracker' ? '' : 'hidden' ?>></div>
    <div class="dtr-nav-actions dtr-form-actions" data-dtr-for="material"<?= $activeTab === 'material' ? '' : 'hidden' ?>>
        <select id="dtr-mat-form-slug" class="dtr-tracker-cat" aria-label="Material test form">
            <?php foreach ($formConfig as $slug => $cfg): ?>
                <?php if ($slug === 'bonding' || $slug === 'pull') continue; ?>
                <option value="<?= esc($slug) ?>"><?= esc($cfg['label']) ?></option>
            <?php endforeach ?>
        </select>
        <button type="button" id="btn-dtr-load-machine" class="dtr-btn dtr-btn-amber" hidden style="display:none" title="Reads the machine result file (.xlsx / .csv) for this Lab Ref No. The source file is never modified.">
            <svg class="ic" aria-hidden="true"><use href="#i-bolt"/></svg> Load Machine Results
        </button>
        <span id="dtr-mat-viewonly" class="dtr-viewonly" title="Material test reports are read only. An Admin or a Material PIC can press Edit.">View only</span>
        <?php if (! empty($canEditMaterial)): ?>
        <button type="button" id="btn-mat-edit" class="dtr-btn dtr-btn-amber" title="Make this report editable (it opens read only)">
            <svg class="ic" aria-hidden="true"><use href="#i-edit"/></svg> Edit
        </button>
        <?php endif ?>
        <button type="button" id="btn-dtr-refresh-calc" class="dtr-btn dtr-btn-outline" title="Runs ApplyStandardValuesSmart + Thickness + Directional Averages + 6-Point Colorfastness + EvaluateRatings">
            <svg class="ic" aria-hidden="true"><use href="#i-refresh"/></svg> Refresh &amp; Apply Smart Standards
        </button>
        <button type="button" id="btn-dtr-print-preview" class="dtr-btn dtr-btn-purple" title="View, print or save the official test report sheet">
            <svg class="ic" aria-hidden="true"><use href="#i-printer"/></svg> Print / Preview Report
        </button>
        <?= view('dtr/_send_menu') ?>
        <button type="button" id="btn-dtr-save" class="dtr-btn dtr-btn-primary">
            <svg class="ic" aria-hidden="true"><use href="#i-save"/></svg> Save Report to NEXUS-L
        </button>
    </div>
    <div class="dtr-nav-actions dtr-form-actions" data-dtr-for="bonding" <?= $activeTab === 'bonding' ? '' : 'hidden' ?>>
        <a href="<?= site_url('dtr?tab=labtracker') ?>" id="btn-bond-back" class="dtr-btn dtr-btn-outline" title="Back to the Lab Tracker">
            <svg class="ic" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg> Back to Lab Tracker
        </a>
        <select id="dtr-report-kind" class="dtr-tracker-cat" aria-label="Report type" style="display:none" hidden>
            <option value="bonding">+ Bonding Report</option>
            <option value="pull">+ Pull Test Report</option>
        </select>
        <button type="button" id="btn-bond-recalc" class="dtr-btn dtr-btn-outline">
            <svg class="ic" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> <span id="btn-bond-recalc-txt">Edit</span>
        </button>
        <button type="button" id="btn-bond-generate" class="dtr-btn dtr-btn-outline" title="Draw the Bonding Test-VF report of this Lab Ref (readings and curve) as a PNG picture">
            <svg class="ic" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg> Generate Report
        </button>
        <button type="button" id="btn-bond-print-preview" class="dtr-btn dtr-btn-purple" title="View, print or save the official report sheet">
            <svg class="ic" aria-hidden="true"><use href="#i-printer"/></svg> Print / Preview Report
        </button>
        <?= view('dtr/_send_menu') ?>
        <?php if (! empty($dtrIsAdmin)): ?>
        <a id="btn-bond-designer" class="dtr-btn dtr-btn-outline dtr-only-bonding" href="<?= site_url('designer') ?>?tab=diagram" title="Admin only: place the points on the shoe, hide or add fields">
            <svg class="ic" aria-hidden="true"><use href="#i-sliders"/></svg> Adjust shoe points / layout (admin)
        </a>
        <?php endif ?>
        <button type="button" id="btn-bond-save" class="dtr-btn dtr-btn-primary">
            <svg class="ic" aria-hidden="true"><use href="#i-save"/></svg> Save Report
        </button>
    </div>
    <div class="dtr-nav-actions dtr-form-actions" data-dtr-for="wholeshoe" <?= $activeTab === 'wholeshoe' ? '' : 'hidden' ?>>
        <button type="button" id="btn-ws-edit" class="dtr-btn dtr-btn-outline" title="Enable editing on this Whole Shoe Test Report">
            <svg class="ic" aria-hidden="true"><use href="#i-edit"/></svg> Edit Report
        </button>
        <button type="button" id="btn-ws-cancel" class="dtr-btn dtr-btn-outline" hidden title="Cancel edits and restore previous values">
            Cancel
        </button>
        <button type="button" id="btn-ws-preview" class="dtr-btn dtr-btn-purple" title="View, print or save the Whole Footwear Test Results sheet">
            <svg class="ic" aria-hidden="true"><use href="#i-printer"/></svg> Print / Preview Report
        </button>
        <?= view('dtr/_send_menu') ?>
        <button type="button" id="btn-ws-save" class="dtr-btn dtr-btn-primary" disabled>
            <svg class="ic" aria-hidden="true"><use href="#i-save"/></svg> Save Report
        </button>
        <span id="ws-side-toggle" class="wsf-side-toggle" hidden aria-label="Shoe Side">
            <span class="wsf-side-lbl">Side:</span>
            <button type="button" class="wsf-side-btn" data-side="L" title="Append -L (Left) to Whole Shoe Lab Ref">L</button>
            <button type="button" class="wsf-side-btn" data-side="R" title="Append -R (Right) to Whole Shoe Lab Ref">R</button>
        </span>
    </div>
    <div class="dtr-nav-actions dtr-form-actions" data-dtr-for="wholeshoetest" <?= in_array($activeTab, ['wholeshoetest', 'Whole Shoe Test'], true) ? '' : 'hidden' ?>>
        <a href="<?= site_url('wholeshoe') ?>" class="dtr-btn dtr-btn-primary" target="_blank">
            <svg class="ic" aria-hidden="true"><use href="#i-shoe"/></svg> Open Whole Shoe Test Workspace
        </a>
        <a href="<?= site_url('test-summary') ?>" class="dtr-btn dtr-btn-outline" target="_blank">
            <svg class="ic" aria-hidden="true"><use href="#i-chart"/></svg> View Test Results Summary
        </a>
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

<?php include __DIR__ . '/partials/tab_lab_tracker.php'; ?>
<?php include __DIR__ . '/partials/tab_material_forms.php'; ?>
<?php include __DIR__ . '/partials/tab_bonding_form.php'; ?>
<?php include __DIR__ . '/partials/tab_calculators_wsreport.php'; ?>
<?php include __DIR__ . '/partials/tab_wholeshoe_test.php'; ?>
<?php include __DIR__ . '/partials/modals_intake.php'; ?>
<?php include __DIR__ . '/partials/modals_report.php'; ?>
