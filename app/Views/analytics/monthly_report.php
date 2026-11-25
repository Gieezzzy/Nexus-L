<?php
/**
 * Print-ready Monthly Summary Report (12-Month Pie Summary Grid matching the Yearly Pie Summary screen).
 * Data: $tab ('bonding'|'wholeshoe'|'materials'), $yearlyYear (int), $yearlyPies (12 months), $printedBy (string)
 */
$tab  = in_array($tab ?? 'bonding', ['bonding', 'wholeshoe', 'materials'], true) ? $tab : 'bonding';
$yYr  = (int) ($yearlyYear ?? date('Y'));
$areaName = match ($tab) {
    'wholeshoe' => 'Whole Shoe',
    'materials' => 'Materials',
    default     => 'Shoe Bonding',
};
$ypSubtitle = match ($tab) {
    'wholeshoe' => 'Whole-year whole shoe test status distribution (PASS, FAILED, TOLERABLE) across all 12 months',
    'materials' => 'Whole-year material test status distribution (PASS, FAILED, TOLERABLE) across all 12 months',
    default     => 'Whole-year bonding test status distribution (PASS, FAILED, TOLERABLE) across all 12 months',
};
$ypMonthQuery = match ($tab) {
    'wholeshoe' => 'tab=wholeshoe&view=analytics&group=month',
    'materials' => 'tab=materials&view=analytics&group=month',
    default     => 'tab=bonding&view=monthly&group=day',
};
$backUrl = site_url('analytics') . '?tab=' . $tab;
$dlUrl   = site_url('analytics/export-yearly-pies') . '?tab=' . $tab . '&year=' . $yYr;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $yYr ?> <?= esc($areaName) ?> Monthly Summary Report — NEXUS-L</title>
<link rel="stylesheet" href="<?= base_url('assets/css/views/analytics-monthly_report-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/analytics-monthly_report-1.css') ?>">
</head>
<body>
<div class="pr-bar">
    <form method="get" action="<?= site_url('analytics/monthly-report') ?>" class="pr-bar-l">
        <select name="tab" class="pr-sel" onchange="this.form.submit()" aria-label="Area">
            <option value="bonding"<?= $tab === 'bonding' ? ' selected' : '' ?>>Shoe Bonding</option>
            <option value="wholeshoe"<?= $tab === 'wholeshoe' ? ' selected' : '' ?>>Whole Shoe</option>
            <option value="materials"<?= $tab === 'materials' ? ' selected' : '' ?>>Materials</option>
        </select>
        <select name="year" class="pr-sel" onchange="this.form.submit()" aria-label="Year">
            <?php for ($y = (int) date('Y'); $y >= 2024; $y--): ?>
                <option value="<?= $y ?>"<?= $y === $yYr ? ' selected' : '' ?>><?= $y ?></option>
            <?php endfor ?>
        </select>
    </form>
    <div class="pr-bar-r">
        <button type="button" class="pr-btn primary" onclick="window.print()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print / Save as PDF
        </button>
        <a href="<?= esc($dlUrl, 'attr') ?>" class="pr-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
            Download Excel
        </a>
        <a href="<?= esc($backUrl, 'attr') ?>" class="pr-btn">&larr; Back to Analytics</a>
    </div>
</div>

<div class="pr-sheet">
    <?php include __DIR__ . '/bonding_yearly_pies.php'; ?>
    <div class="pr-foot">
        <span>NEXUS-L Quality Laboratory &middot; <?= esc($areaName) ?> <?= $yYr ?> Monthly Summary Report</span>
        <span>Generated <?= date('Y-m-d H:i') ?><?= ! empty($printedBy) ? ' · ' . esc($printedBy) : '' ?></span>
    </div>
</div>
</body>
</html>
