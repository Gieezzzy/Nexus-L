<?php
/** Analytics > Daily Works Monitoring. Data: $dw (year, asof, types, months, days, sections, cat, materials, monthName) */
$year   = $dw['year'];
$asof   = $dw['asof'];
$types  = $dw['types'];
$months = $dw['months'];
$mNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$tColor = [
    'Fabric'      => '#fb923c',
    'Leather'     => '#94a3b8',
    'Synthetic'   => '#f43f5e',
    'Accessories' => '#facc15',
    'Rubber'      => '#3b82f6',
];
$tBadgeBg = [
    'Fabric'      => '#fff7ed;color:#c2410c;border:1px solid #fed7aa',
    'Leather'     => '#f1f5f9;color:#334155;border:1px solid #cbd5e1',
    'Synthetic'   => '#fff1f2;color:#be123c;border:1px solid #fecdd3',
    'Accessories' => '#fefce8;color:#a16207;border:1px solid #fde047',
    'Rubber'      => '#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe',
];
$curYm  = (int) date('Y') * 12 + (int) date('n');
$pctTxt = static fn (int $n, int $d): string => $d > 0 ? number_format($n / $d * 100, 1) . '%' : '0%';

$totRecv = $totEnc = 0;
$typeTot = array_fill_keys($types, 0);
$activeMonths = 0;
foreach ($months as $m) {
    $totRecv += $m['recv'];
    $totEnc  += $m['enc'];
    if ($m['recv'] > 0) {
        $activeMonths++;
    }
    foreach ($types as $t) {
        $typeTot[$t] += $m['types'][$t];
    }
}
$maxRecv = max(10, ...array_map(static fn ($m) => $m['recv'], $months));
$maxRecv = (int) (ceil($maxRecv / 100) * 100);

/* Chart 1: Monthly Received vs Encoded + % Done Line */
$W = 680; $H = 290; $L = 46; $R = 48; $T = 22; $B = 50; $pw = $W - $L - $R; $ph = $H - $T - $B; $slot = $pw / 12;
$c1 = '';
for ($i = 0; $i <= 4; $i++) {
    $gy = $T + $ph - $i / 4 * $ph;
    $c1 .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $gy . '" y2="' . $gy . '" stroke="rgba(148,163,184,.18)" stroke-dasharray="4 4"/>';
    $c1 .= '<text x="' . ($L - 8) . '" y="' . ($gy + 4) . '" text-anchor="end" fill="#94a3b8" font-size="10.5" font-weight="600">' . round($i / 4 * $maxRecv) . '</text>';
    $c1 .= '<text x="' . ($W - $R + 8) . '" y="' . ($gy + 4) . '" fill="#34d399" font-size="10.5" font-weight="600">' . ($i * 25) . '%</text>';
}
$pts = []; $dots = '';
foreach ($months as $mo => $m) {
    $cx = $L + $slot * ($mo - 0.5); $bw = min(15, $slot * .33);
    $hr = $m['recv'] / $maxRecv * $ph; $he = $m['enc'] / $maxRecv * $ph;
    if ($m['recv'] > 0) {
        $c1 .= '<rect x="' . ($cx - $bw - 1.5) . '" y="' . ($T + $ph - $hr) . '" width="' . $bw . '" height="' . $hr . '" rx="3" fill="#38bdf8"><title>' . $mNames[$mo - 1] . ': ' . number_format($m['recv']) . ' received</title></rect>';
        $c1 .= '<rect x="' . ($cx + 1.5) . '" y="' . ($T + $ph - $he) . '" width="' . $bw . '" height="' . $he . '" rx="3" fill="#fb923c"><title>' . $mNames[$mo - 1] . ': ' . number_format($m['enc']) . ' encoded</title></rect>';
        if ($hr > 18) {
            $c1 .= '<text x="' . round($cx - $bw / 2 - 1.5, 1) . '" y="' . round($T + $ph - $hr - 4, 1) . '" text-anchor="middle" fill="#bae6fd" font-size="9" font-weight="700">' . $m['recv'] . '</text>';
        }
        $py = $T + $ph - min(100, $m['enc'] / $m['recv'] * 100) / 100 * $ph;
        $pts[] = round($cx, 1) . ',' . round($py, 1);
        $dots .= '<circle cx="' . round($cx, 1) . '" cy="' . round($py, 1) . '" r="3.5" fill="#10b981" stroke="#0f172a" stroke-width="1.5"><title>' . $mNames[$mo - 1] . ': ' . round($m['enc'] / $m['recv'] * 100, 1) . '% done</title></circle>';
    }
    $c1 .= '<text x="' . round($cx, 1) . '" y="' . ($T + $ph + 18) . '" text-anchor="middle" fill="#cbd5e1" font-size="10.5" font-weight="600">' . substr($mNames[$mo - 1], 0, 3) . '</text>';
}
if (count($pts) > 1) {
    $c1 .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="#10b981" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>';
}
$c1 .= $dots;

/* Chart 2: Monthly Stacked Breakdown by Material Type */
$c2 = '';
for ($i = 0; $i <= 4; $i++) {
    $gy = $T + $ph - $i / 4 * $ph;
    $c2 .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $gy . '" y2="' . $gy . '" stroke="rgba(148,163,184,.18)" stroke-dasharray="4 4"/>';
    $c2 .= '<text x="' . ($L - 8) . '" y="' . ($gy + 4) . '" text-anchor="end" fill="#94a3b8" font-size="10.5" font-weight="600">' . round($i / 4 * $maxRecv) . '</text>';
}
foreach ($months as $mo => $m) {
    $cx = $L + $slot * ($mo - 0.5); $bw = min(28, $slot * .54); $y = $T + $ph;
    foreach ($types as $t) {
        $v = $m['types'][$t];
        if ($v <= 0) {
            continue;
        }
        $h = $v / $maxRecv * $ph;
        $y -= $h;
        $pctType = $m['recv'] > 0 ? number_format($v / $m['recv'] * 100, 1) . '%' : '0%';
        $c2 .= '<rect x="' . ($cx - $bw / 2) . '" y="' . $y . '" width="' . $bw . '" height="' . $h . '" fill="' . $tColor[$t] . '"><title>' . $mNames[$mo - 1] . ' · ' . $t . ': ' . $v . ' (' . $pctType . ')</title></rect>';
    }
    if ($m['recv'] > 0) {
        $c2 .= '<text x="' . round($cx, 1) . '" y="' . round($y - 4, 1) . '" text-anchor="middle" fill="#e2e8f0" font-size="9" font-weight="700">' . $m['recv'] . '</text>';
    }
    $c2 .= '<text x="' . round($cx, 1) . '" y="' . ($T + $ph + 18) . '" text-anchor="middle" fill="#cbd5e1" font-size="10.5" font-weight="600">' . substr($mNames[$mo - 1], 0, 3) . '</text>';
}

/* Section explanations for non-lab users */
$sectionDescriptions = [
    'daily'  => ['Routine Daily Production Line Sampling', 'Pairs pulled every day from active assembly lines (Buildings A & B) to verify sole bonding strength before mass packing.'],
    'retest' => ['Corrective Re-Tests on Failed Lines', 'Follow-up specimens pulled from a line after a bonding failure to verify the production line fixed the issue.'],
    'first'  => ['First Article Qualification (New PO / Style)', 'First production pairs tested before a new model or PO is cleared for full-scale mass production.'],
    'sample' => ['Development, Strip & Component Samples', 'Pre-production shoes, rubber strip peel specimens, and rivet pull samples tested for R&D or material qualification.'],
    'trial'  => ['Technical & Adhesive Trials', 'Experimental bonding runs evaluating new adhesives, primers, or heating conditions.'],
];

$est = static function (int $q, int $f, string $d, ?float $rate = null): string {
    if ($q <= 0) {
        return 'No Intake';
    }
    $rem = $q - $f;
    if ($rem <= 0) {
        return 'COMPLETED';
    }
    $days = $rate !== null ? (int) min(30, max(1, ceil($rem / max(1.0, $rate)))) : 1;

    return 'Est. ' . date('M j', strtotime("{$d} +{$days} day"));
};

$areaTab     = $tab ?? 'bonding';
$areaView    = $view ?? 'workload';
$period      = $dw['period'] ?? 'daily';
$weekVal     = $dw['week'] ?? date('o-\WW', strtotime($asof));
$selMonth    = (int) ($dw['month'] ?? date('n', strtotime($asof)));
$windowTag   = $dw['windowTag'] ?? date('M j', strtotime($asof));
$windowShort = $dw['windowShort'] ?? 'Daily';
$windowLabel = $dw['windowLabel'] ?? 'Daily (' . date('M j, Y', strtotime($asof)) . ')';
$modeUrl = static function (string $p) use ($areaTab, $areaView, $year, $asof, $weekVal, $selMonth): string {
    $q = ['tab' => $areaTab, 'view' => $areaView, 'period' => $p, 'year' => $year];
    if ($p === 'weekly') {
        $q['week'] = $weekVal;
    } elseif ($p === 'monthly') {
        $q['month'] = $selMonth;
    } else {
        $q['asof'] = $asof;
    }
    return site_url('analytics') . '?' . http_build_query($q);
};
$wFrom = (string) ($dw['winFrom'] ?? $asof);
$wTo   = (string) ($dw['winTo'] ?? $asof);
$subTabUrl = static function (string $vk) use ($areaTab, $period, $year, $asof, $weekVal, $selMonth, $wFrom, $wTo): string {
    if (in_array($vk, ['workload', 'encoding'], true)) {
        $q = ['tab' => $areaTab, 'view' => $vk, 'period' => $period, 'year' => $year];
        if ($period === 'custom') {
            $q = ['tab' => $areaTab, 'view' => $vk, 'from' => $wFrom, 'to' => $wTo];
        } elseif ($period === 'weekly') {
            $q['week'] = $weekVal;
        } elseif ($period === 'monthly') {
            $q['month'] = $selMonth;
        } else {
            $q['asof'] = $asof;
        }
        return site_url('analytics') . '?' . http_build_query($q);
    }
    return site_url('analytics') . '?' . http_build_query(['tab' => $areaTab, 'view' => $vk]);
};

/* Scope testing sections and 3-portal encoding rows to the active area (bonding / wholeshoe / materials) */
if ($areaTab === 'wholeshoe') {
    $dw['sections'] = ['first' => ['WHOLE SHOE TESTING', ['Whole Shoe']]];
    foreach ($dw['days'] as &$dRef) {
        $wsCell = $dRef['cells']['first']['Whole Shoe'] ?? ['q' => 0, 'f' => 0, 'prod' => '', 'test' => ''];
        $dRef['total'] = ['q' => $wsCell['q'], 'f' => $wsCell['f']];
        $dRef['shut']  = 0;
    }
    unset($dRef);
    $rowsEncAll = ['Whole Shoe' => $dw['cat']['Whole Shoe'] ?? ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0]];
} elseif ($areaTab === 'materials') {
    $rowsEncAll = ['Materials' => $dw['materials']];
} else {
    $dw['sections']['first'][1] = ['Bonding Shoe'];
    foreach ($dw['days'] as &$dRef) {
        $wsCell = $dRef['cells']['first']['Whole Shoe'] ?? ['q' => 0, 'f' => 0];
        $dRef['total']['q'] = max(0, $dRef['total']['q'] - $wsCell['q']);
        $dRef['total']['f'] = max(0, $dRef['total']['f'] - $wsCell['f']);
    }
    unset($dRef);
    $rowsEncAll = [
        'Bonding'   => $dw['cat']['Bonding'] ?? ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0],
        'Pull Test' => $dw['cat']['Pull Test'] ?? ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0],
        'Re-Test'   => $dw['cat']['Re-Test'] ?? ['q' => 0, 'etrf' => 0, 'il' => 0, 'erp' => 0],
    ];
}
$latestDay = $dw['days'][0] ?? ['date' => $asof, 'total' => ['q' => 0, 'f' => 0], 'shut' => 0];
$threeDayQty = $threeDayFin = $threeDayShut = 0;
foreach ($dw['days'] as $dItem) {
    $threeDayQty  += $dItem['total']['q'];
    $threeDayFin  += $dItem['total']['f'];
    $threeDayShut += $dItem['shut'];
}
$sysTotals = [
    'etrf' => ['name' => 'ETRF Portal', 'desc' => 'Electronic Test Request Form (Test intake & request approval)', 'q' => 0, 'f' => 0, 'color' => '#2563eb', 'bg' => '#eff6ff'],
    'il'   => ['name' => 'INTERLINK System', 'desc' => 'VF Global Quality System (Official brand compliance upload)', 'q' => 0, 'f' => 0, 'color' => '#7c3aed', 'bg' => '#f5f3ff'],
    'erp'  => ['name' => 'Factory ERP', 'desc' => 'Internal Factory ERP (Shipment & production release)', 'q' => 0, 'f' => 0, 'color' => '#059669', 'bg' => '#ecfdf5'],
];
foreach ($rowsEncAll as $rn => $r) {
    foreach (['etrf', 'il', 'erp'] as $sk) {
        if ($sk === 'erp' && $rn === 'Whole Shoe') {
            continue;
        }
        $sysTotals[$sk]['q'] += $r['q'];
        $sysTotals[$sk]['f'] += min($r['q'], $r[$sk]);
    }
}
?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/analytics-daily-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/analytics-daily-1.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/datefilter.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/datefilter.css') ?>">
<script src="<?= base_url('assets/js/datefilter.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/datefilter.js') ?>"></script>

<?php
$areaSubTabs = match ($areaTab) {
    'wholeshoe' => ['analytics' => 'Monthly Summary', 'monthfail' => 'Monthly Failure by Test'],
    'materials' => ['analytics' => 'Monthly Summary', 'hotspots' => 'Parameters & Supplier Scorecard', 'batches' => 'Failed & Tolerable Batches', 'workload' => 'Material Intake (IQC)'],
    default     => ['monthly' => 'Monthly Summary', 'workload' => 'Daily Workload', 'daily' => 'Daily Bonding', 'pulltest' => 'Rivets Monitoring', 'failure' => 'Bonding Failure Overview', 'retest' => 'Retest', 'result' => 'Cement Analytics', 'utm' => 'Universal Tensile Machines Daily Productivity'],
};
$activeStageNum = $areaView === 'encoding' ? 4 : ($areaTab === 'materials' ? 1 : 2);
?>
<div class="dw">
    <!-- Area Sub-Tabs & Date Controls -->
    <div class="dw-bar">
        <div class="dw-tabs" role="tablist" aria-label="Analytics Area Views">
            <?php foreach ($areaSubTabs as $vk => $vl): ?>
                <a href="<?= esc($subTabUrl($vk), 'attr') ?>" class="dw-tab<?= $areaView === $vk ? ' dw-tab-on' : '' ?>" style="text-decoration:none"><?= esc($vl) ?></a>
            <?php endforeach ?>
        </div>
        <div class="an-dr" id="dw-form" data-today="<?= date('Y-m-d') ?>" data-tab="<?= esc($areaTab, 'attr') ?>" data-view="<?= esc($areaView, 'attr') ?>" data-from="<?= esc($wFrom, 'attr') ?>" data-to="<?= esc($wTo, 'attr') ?>" data-action="<?= esc(site_url('analytics'), 'attr') ?>" style="margin-left:auto">
            <?php $dwRangeText = $wFrom === $wTo ? $wFrom : ($wFrom . ' → ' . $wTo); ?>
            <button type="button" id="dw-dr-btn" class="an-dr-btn" aria-expanded="false" title="<?= esc('Filter Date: ' . $dwRangeText, 'attr') ?>" aria-label="<?= esc('Filter Date: ' . $dwRangeText, 'attr') ?>" style="padding:9px 11px;justify-content:center">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </button>
            <?php /* popup built by assets/js/datefilter.js (same as the Lab Tracker) */ ?>
        </div>
    </div>

    <!-- ===== SECTION 1: Annual Materials Monitoring ===== -->
    <div class="dw-stage-pane" data-stage-panel="1">

    <?php
    arsort($typeTot);
    $topType = array_key_first($typeTot);
    $topCnt  = $typeTot[$topType] ?? 0;
    $encPct  = $totRecv > 0 ? round($totEnc / $totRecv * 100, 1) : 0;
    ?>
    <div class="dw-kpis" id="dw-stage1-start">
        <button type="button" class="dw-kpi" style="--kc:#2563eb" data-dw-action="mat-recv">
            <div class="dw-kpi-top"><small>Total Materials Received</small><span class="dw-kpi-tag"><?= $activeMonths ?> active mos</span></div>
            <b><?= number_format($totRecv) ?></b>
            <div class="dw-kpi-sub"><span>Material batches logged in <?= $year ?></span><span class="dw-kpi-jump">View Monthly &rarr;</span></div>
            <div class="dw-kpi-bar"><i style="width:100%"></i></div>
        </button>
        <button type="button" class="dw-kpi" style="--kc:#ea580c" data-dw-action="mat-enc">
            <div class="dw-kpi-top"><small>Completed &amp; Encoded</small><span class="dw-kpi-tag" style="background:#fff7ed;color:#c2410c"><?= $pctTxt($totEnc, $totRecv) ?></span></div>
            <b style="color:#ea580c"><?= number_format($totEnc) ?></b>
            <div class="dw-kpi-sub"><span>Encoded in INTERLINK (<?= number_format(max(0, $totRecv - $totEnc)) ?> left)</span><span class="dw-kpi-jump">View Encoded &rarr;</span></div>
            <div class="dw-kpi-bar"><i style="width:<?= min(100, $encPct) ?>%"></i></div>
        </button>
        <button type="button" class="dw-kpi" style="--kc:#059669" data-dw-action="mat-pct">
            <div class="dw-kpi-top"><small>Annual Completion Rate</small><span class="dw-kpi-tag" style="background:#dcfce7;color:#15803d"><?= $encPct >= 95 ? 'On Track' : 'In Progress' ?></span></div>
            <b style="color:#059669"><?= $pctTxt($totEnc, $totRecv) ?></b>
            <div class="dw-kpi-sub"><span>Share of <?= $year ?> materials completed</span><span class="dw-kpi-jump">View % Done &rarr;</span></div>
            <div class="dw-kpi-bar"><i style="width:<?= min(100, $encPct) ?>%"></i></div>
        </button>
        <button type="button" class="dw-kpi" style="--kc:#eab308" data-dw-action="mat-type" data-dw-type="<?= esc($topType, 'attr') ?>">
            <div class="dw-kpi-top"><small>Primary Material Category</small><span class="dw-kpi-tag" style="background:#fefce8;color:#a16207"><?= $totRecv ? $pctTxt($topCnt, $totRecv) : '0%' ?></span></div>
            <b><?= $totRecv ? esc($topType) : '–' ?></b>
            <div class="dw-kpi-sub"><span><?= number_format($topCnt) ?> of <?= number_format($totRecv) ?> batches</span><span class="dw-kpi-jump">View <?= esc($topType) ?> &rarr;</span></div>
            <div class="dw-kpi-bar"><i style="width:<?= $totRecv ? min(100, round($topCnt / $totRecv * 100)) : 0 ?>%"></i></div>
        </button>
    </div>

    <div class="dw-grid2">
        <!-- Card 1A: Monthly Summary of Materials Received & Encoded -->
        <div class="dw-card" id="dw-card-mat-summary">
            <div class="dw-card-h">
                <div>
                    <div class="dw-card-h-title"><?= $year ?> Monthly Summary of Materials Received &amp; Encoded in INTERLINK</div>
                    <div class="dw-card-h-sub">Compares monthly material arrivals (blue) against completed encodings (orange)</div>
                </div>
            </div>
            <div class="dw-chart">
                <div class="dw-chart-title"><?= $year ?> Monthly Materials Received vs. Encoded &amp; Completion %</div>
                <svg viewBox="0 0 <?= $W ?> <?= $H ?>"><?= $c1 ?></svg>
                <div class="dw-leg">
                    <span><i style="background:#38bdf8"></i>Total Quantity of Materials Received</span>
                    <span><i style="background:#fb923c"></i>Encoded in INTERLINK System</span>
                    <span><i class="ln" style="background:#10b981"></i>Percentage Done (%)</span>
                </div>
            </div>
            <div class="dw-tbl-wrap">
                <table class="dw-tbl" id="dw-tbl-mat-summary">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th data-col="recv">Total Materials Received</th>
                            <th data-col="enc">Encoded in INTERLINK</th>
                            <th data-col="rem">Remaining</th>
                            <th data-col="pct">Percentage Done</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($months as $mo => $m):
                        $future = ($year * 12 + $mo) > $curYm;
                        $p = $m['recv'] > 0 ? min(100, $m['enc'] / $m['recv'] * 100) : 0;
                        $remM = max(0, $m['recv'] - $m['enc']);
                    ?>
                        <tr>
                            <td><?= $mNames[$mo - 1] ?></td>
                            <?php if ($m['recv'] === 0): ?>
                                <td class="z" data-col="recv">–</td><td class="z" data-col="enc">–</td><td class="z" data-col="rem">–</td>
                                <td class="z" data-col="pct"><?= $future ? 'Upcoming' : '0%' ?></td>
                            <?php else: ?>
                                <td data-col="recv" style="font-weight:700"><?= number_format($m['recv']) ?></td>
                                <td data-col="enc" style="font-weight:700;color:#ea580c"><?= number_format($m['enc']) ?></td>
                                <td data-col="rem" class="<?= $remM > 0 ? '' : 'z' ?>" <?= $remM > 0 ? 'style="color:#b45309;font-weight:700"' : '' ?>><?= $remM > 0 ? number_format($remM) : '0' ?></td>
                                <td data-col="pct">
                                    <span class="dw-pct">
                                        <i><b style="width:<?= round($p) ?>%;<?= $p < 100 ? 'background:#f59e0b' : '' ?>"></b></i>
                                        <span style="font-weight:800;color:<?= $p >= 100 ? '#059669' : '#b45309' ?>"><?= round($p) ?>%</span>
                                    </span>
                                </td>
                            <?php endif ?>
                        </tr>
                    <?php endforeach ?>
                        <tr class="dw-tot">
                            <td>Total (<?= $year ?>)</td>
                            <td data-col="recv"><?= number_format($totRecv) ?></td>
                            <td data-col="enc"><?= number_format($totEnc) ?></td>
                            <td data-col="rem"><?= number_format(max(0, $totRecv - $totEnc)) ?></td>
                            <td data-col="pct"><?= $totRecv ? round(min(100, $totEnc / $totRecv * 100)) . '%' : '0%' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Card 1B: Monthly Breakdown by Material Type -->
        <div class="dw-card" id="dw-card-mat-types">
            <div class="dw-card-h">
                <div>
                    <div class="dw-card-h-title"><?= $year ?> Monthly Breakdown of Materials Received per Type (%)</div>
                    <div class="dw-card-h-sub">Distribution of incoming materials across the 5 laboratory testing categories</div>
                </div>
            </div>
            <div class="dw-chart">
                <div class="dw-chart-title"><?= $year ?> Monthly Material Volume Stacked by Category</div>
                <svg viewBox="0 0 <?= $W ?> <?= $H ?>"><?= $c2 ?></svg>
                <div class="dw-leg">
                    <?php foreach ($types as $t): ?>
                        <span><i style="background:<?= $tColor[$t] ?>"></i><?= $t ?> (<?= number_format($typeTot[$t]) ?> · <?= $totRecv ? number_format($typeTot[$t] / $totRecv * 100, 1) . '%' : '0%' ?>)</span>
                    <?php endforeach ?>
                </div>
            </div>
            <div class="dw-tbl-wrap">
                <table class="dw-tbl" id="dw-tbl-mat-types">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <?php foreach ($types as $t): ?>
                                <th data-col="<?= esc($t, 'attr') ?>"><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:<?= $tColor[$t] ?>;margin-right:4px"></span><?= $t ?></th>
                            <?php endforeach ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($months as $mo => $m): ?>
                        <tr>
                            <td><?= $mNames[$mo - 1] ?></td>
                            <?php foreach ($types as $t): $v = $m['types'][$t]; ?>
                                <?php if ($m['recv'] === 0): ?>
                                    <td class="z" data-col="<?= esc($t, 'attr') ?>">–</td>
                                <?php else: ?>
                                    <td data-col="<?= esc($t, 'attr') ?>">
                                        <span class="dw-type-cell">
                                            <b><?= $v ?></b>
                                            <span class="dw-type-pct">(<?= number_format($v / $m['recv'] * 100, 1) ?>%)</span>
                                        </span>
                                    </td>
                                <?php endif ?>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                        <tr class="dw-tot">
                            <td>Total (<?= $year ?>)</td>
                            <?php foreach ($types as $t): ?>
                                <td data-col="<?= esc($t, 'attr') ?>">
                                    <?= number_format($typeTot[$t]) ?>
                                    <span style="font-weight:700;font-size:10.5px;opacity:.85">(<?= $totRecv ? number_format($typeTot[$t] / $totRecv * 100, 1) . '%' : '0%' ?>)</span>
                                </td>
                            <?php endforeach ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div><!-- /data-stage-panel="1" -->

    <?php
    /* Stage 2 aggregates: 3-day section totals & chronological charts */
    $secTotals = [];
    foreach ($dw['sections'] as $sk => [$sTitle, $sLabels]) {
        $sq = $sf = 0;
        foreach ($dw['days'] as $dItem) {
            foreach ($sLabels as $lb) {
                $cell = $dItem['cells'][$sk][$lb] ?? ['q' => 0, 'f' => 0];
                $sq += $cell['q'];
                $sf += $cell['f'];
            }
        }
        $secTotals[$sk] = ['title' => $sTitle, 'q' => $sq, 'f' => $sf];
    }
    $retestSec    = $secTotals['retest'] ?? ['q' => 0, 'f' => 0];
    $trialSec     = $secTotals['trial'] ?? ['q' => 0, 'f' => 0];
    $firstSampleQ = ($secTotals['first']['q'] ?? 0) + ($secTotals['sample']['q'] ?? 0);
    $firstSampleF = ($secTotals['first']['f'] ?? 0) + ($secTotals['sample']['f'] ?? 0);

    $daysChron = array_reverse($dw['days']);
    $maxDayQ   = 10;
    foreach ($daysChron as $dItem) {
        $maxDayQ = max($maxDayQ, $dItem['total']['q']);
    }
    $maxDayQ = (int) (ceil($maxDayQ / 10) * 10);

    /* Stage 2 Left SVG: 3-Day Testing Submitted vs Finished + % Done */
    $w2 = 580; $h2 = 215; $l2 = 42; $r2 = 44; $t2 = 20; $b2 = 34; $pw2 = $w2 - $l2 - $r2; $ph2 = $h2 - $t2 - $b2;
    $cTestDays = '';
    for ($i = 0; $i <= 4; $i++) {
        $gy = $t2 + $ph2 - $i / 4 * $ph2;
        $cTestDays .= '<line x1="' . $l2 . '" x2="' . ($w2 - $r2) . '" y1="' . $gy . '" y2="' . $gy . '" stroke="rgba(148,163,184,.18)" stroke-dasharray="4 4"/>';
        $cTestDays .= '<text x="' . ($l2 - 7) . '" y="' . ($gy + 4) . '" text-anchor="end" fill="#94a3b8" font-size="10" font-weight="600">' . round($i / 4 * $maxDayQ) . '</text>';
        $cTestDays .= '<text x="' . ($w2 - $r2 + 7) . '" y="' . ($gy + 4) . '" fill="#34d399" font-size="10" font-weight="600">' . ($i * 25) . '%</text>';
    }
    $nD = max(1, count($daysChron));
    $slot2 = $pw2 / $nD;
    $pts2 = []; $dots2 = '';
    foreach ($daysChron as $di => $dItem) {
        $cx = $l2 + $slot2 * ($di + 0.5);
        $bw = min(32, $slot2 * .24);
        $dq = $dItem['total']['q'];
        $df = $dItem['total']['f'];
        $hq = $dq / $maxDayQ * $ph2;
        $hf = $df / $maxDayQ * $ph2;
        $dLbl = esc($dItem['short'] ?? date('M j', strtotime($dItem['date'])));
        if ($dq > 0) {
            $cTestDays .= '<rect x="' . ($cx - $bw - 3) . '" y="' . ($t2 + $ph2 - $hq) . '" width="' . $bw . '" height="' . $hq . '" rx="4" fill="#38bdf8"><title>' . $dLbl . ': ' . $dq . ' submitted</title></rect>';
            $cTestDays .= '<rect x="' . ($cx + 3) . '" y="' . ($t2 + $ph2 - $hf) . '" width="' . $bw . '" height="' . $hf . '" rx="4" fill="#10b981"><title>' . $dLbl . ': ' . $df . ' finished</title></rect>';
            $cTestDays .= '<text x="' . round($cx - $bw / 2 - 3, 1) . '" y="' . round($t2 + $ph2 - $hq - 4, 1) . '" text-anchor="middle" fill="#bae6fd" font-size="10" font-weight="700">' . $dq . '</text>';
            $cTestDays .= '<text x="' . round($cx + $bw / 2 + 3, 1) . '" y="' . round($t2 + $ph2 - $hf - 4, 1) . '" text-anchor="middle" fill="#a7f3d0" font-size="10" font-weight="700">' . $df . '</text>';
            $py = $t2 + $ph2 - min(100, $df / $dq * 100) / 100 * $ph2;
            $pts2[] = round($cx, 1) . ',' . round($py, 1);
            $dots2 .= '<circle cx="' . round($cx, 1) . '" cy="' . round($py, 1) . '" r="4" fill="#facc15" stroke="#0f172a" stroke-width="1.5"><title>' . $dLbl . ': ' . round($df / $dq * 100, 1) . '% done</title></circle>';
        }
        $cTestDays .= '<text x="' . round($cx, 1) . '" y="' . ($t2 + $ph2 + 18) . '" text-anchor="middle" fill="#cbd5e1" font-size="11" font-weight="700">' . $dLbl . '</text>';
    }
    if (count($pts2) > 1) {
        $cTestDays .= '<polyline points="' . implode(' ', $pts2) . '" fill="none" stroke="#facc15" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>';
    }
    $cTestDays .= $dots2;

    /* Stage 2 Right SVG: 3-Day Volume Stacked by Category (Finished vs Remaining) */
    $secShort = ['daily' => 'Daily Bond', 'retest' => 'Re-Test', 'first' => 'First Art.', 'sample' => 'Samples', 'trial' => 'Trials'];
    $maxSecQ = 10;
    foreach ($secTotals as $stItem) {
        $maxSecQ = max($maxSecQ, $stItem['q']);
    }
    $maxSecQ = (int) (ceil($maxSecQ / 10) * 10);
    $cTestSec = '';
    for ($i = 0; $i <= 4; $i++) {
        $gy = $t2 + $ph2 - $i / 4 * $ph2;
        $cTestSec .= '<line x1="' . $l2 . '" x2="' . ($w2 - 20) . '" y1="' . $gy . '" y2="' . $gy . '" stroke="rgba(148,163,184,.18)" stroke-dasharray="4 4"/>';
        $cTestSec .= '<text x="' . ($l2 - 7) . '" y="' . ($gy + 4) . '" text-anchor="end" fill="#94a3b8" font-size="10" font-weight="600">' . round($i / 4 * $maxSecQ) . '</text>';
    }
    $nS = max(1, count($secTotals));
    $slotS = ($w2 - $l2 - 20) / $nS;
    $si = 0;
    foreach ($secTotals as $sk => $stItem) {
        $cx = $l2 + $slotS * ($si + 0.5);
        $bw = min(38, $slotS * .52);
        $sq = $stItem['q'];
        $sf = min($sq, $stItem['f']);
        $sr = max(0, $sq - $sf);
        $y = $t2 + $ph2;
        if ($sf > 0) {
            $hf = $sf / $maxSecQ * $ph2;
            $y -= $hf;
            $cTestSec .= '<rect x="' . ($cx - $bw / 2) . '" y="' . $y . '" width="' . $bw . '" height="' . $hf . '" fill="#10b981"><title>' . esc($stItem['title']) . ': ' . $sf . ' finished</title></rect>';
        }
        if ($sr > 0) {
            $hr = $sr / $maxSecQ * $ph2;
            $y -= $hr;
            $cTestSec .= '<rect x="' . ($cx - $bw / 2) . '" y="' . $y . '" width="' . $bw . '" height="' . $hr . '" fill="#f59e0b"><title>' . esc($stItem['title']) . ': ' . $sr . ' remaining</title></rect>';
        }
        if ($sq > 0) {
            $cTestSec .= '<text x="' . round($cx, 1) . '" y="' . round($y - 4, 1) . '" text-anchor="middle" fill="#e2e8f0" font-size="10" font-weight="700">' . $sf . '/' . $sq . '</text>';
        }
        $cTestSec .= '<text x="' . round($cx, 1) . '" y="' . ($t2 + $ph2 + 18) . '" text-anchor="middle" fill="#cbd5e1" font-size="10.5" font-weight="600">' . esc($secShort[$sk] ?? ucfirst($sk)) . '</text>';
        $si++;
    }
    ?>

    <!-- ===== STAGE 2: Physical & Bonding / Whole Shoe Testing ===== -->
    <div class="dw-stage-pane" data-stage-panel="2" hidden>
        <?php if ($areaTab === 'wholeshoe'):
            $wsRem = max(0, $threeDayQty - $threeDayFin);
            $wsEnc = $dw['cat']['Whole Shoe'] ?? ['q' => 0, 'etrf' => 0, 'il' => 0];
        ?>
        <div class="dw-kpis">
            <button type="button" class="dw-kpi" style="--kc:#0284c7" data-dw-action="day-latest" data-dw-day="<?= esc($latestDay['date'], 'attr') ?>">
                <div class="dw-kpi-top"><small><?= esc($period === 'monthly' ? 'Latest Week' : 'Tested on') ?> (<?= esc($latestDay['short'] ?? $latestDay['date']) ?>)</small><span class="dw-kpi-tag" style="background:#e0f2fe;color:#0369a1"><?= $pctTxt($latestDay['total']['f'], $latestDay['total']['q']) ?> Done</span></div>
                <b><?= $latestDay['total']['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $latestDay['total']['q'] ?> shoes</span></b>
                <div class="dw-kpi-sub"><span>Whole shoe specimens in <?= esc($latestDay['short'] ?? $latestDay['date']) ?></span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $latestDay['total']['q'] ? round($latestDay['total']['f'] / $latestDay['total']['q'] * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#059669" data-dw-action="test-all">
                <div class="dw-kpi-top"><small>Finished Whole Shoe (<?= esc($windowTag) ?>)</small><span class="dw-kpi-tag" style="background:#dcfce7;color:#15803d"><?= $pctTxt($threeDayFin, $threeDayQty) ?> Done</span></div>
                <b style="color:#059669"><?= $threeDayFin ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $threeDayQty ?></span></b>
                <div class="dw-kpi-sub"><span>Completed whole shoe tests</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $threeDayQty ? round($threeDayFin / $threeDayQty * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#d97706" data-dw-action="test-all">
                <div class="dw-kpi-top"><small>On Going Whole Shoe (<?= esc($windowTag) ?>)</small><span class="dw-kpi-tag" style="background:#fef3c7;color:#b45309"><?= $pctTxt($wsRem, $threeDayQty) ?></span></div>
                <b style="color:#d97706"><?= $wsRem ?></b>
                <div class="dw-kpi-sub"><span>Pending whole shoe test result</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $threeDayQty ? round($wsRem / $threeDayQty * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#7c3aed" data-dw-action="test-all">
                <div class="dw-kpi-top"><small>ETRF / INTERLINK (<?= esc($windowTag) ?>)</small><span class="dw-kpi-tag" style="background:#f5f3ff;color:#6d28d9"><?= $pctTxt($wsEnc['etrf'], $wsEnc['q']) ?></span></div>
                <b style="color:#7c3aed"><?= $wsEnc['etrf'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $wsEnc['q'] ?></span></b>
                <div class="dw-kpi-sub"><span>Whole shoe reports encoded</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $wsEnc['q'] ? round($wsEnc['etrf'] / $wsEnc['q'] * 100) : 0 ?>%"></i></div>
            </button>
        </div>
        <?php else:
            // KPI cards open the Lab Tracker (Bonding list) for the date shown, or the Bonding Failure Overview
            $ltUrl = static fn (string $f, string $t, string $lt = ''): string => site_url('dtr') . '?' . http_build_query(array_filter(['tab' => 'labtracker', 'subtab' => 'bonding', 'lk' => 'bonding', 'from' => $f, 'to' => $t, 'lt' => $lt], static fn ($v) => $v !== ''));
            $lDayF  = (string) ($latestDay['from'] ?? $latestDay['date']);
            $lDayT  = (string) ($latestDay['to'] ?? $latestDay['date']);
            $hrefTested = $ltUrl($lDayF, $lDayT);
            $hrefFail   = site_url('analytics') . '?' . http_build_query(['tab' => $areaTab, 'view' => 'failure', 'group' => 'day', 'from' => $wFrom, 'to' => $wTo]);
            $hrefTrial  = $ltUrl($wFrom, $wTo, 'trial');
            $hrefFirst  = $ltUrl($wFrom, $wTo, 'first');
        ?>
        <div class="dw-kpis">
            <button type="button" class="dw-kpi" style="--kc:#0284c7" data-dw-href="<?= esc($hrefTested, 'attr') ?>" title="Open the Lab Tracker for this date" data-dw-action="day-latest" data-dw-day="<?= esc($latestDay['date'], 'attr') ?>">
                <div class="dw-kpi-top"><small><?= esc($period === 'monthly' ? 'Latest Week' : 'Tested on') ?> (<?= esc($latestDay['short'] ?? $latestDay['date']) ?>)</small><span class="dw-kpi-tag" style="background:#e0f2fe;color:#0369a1"><?= $pctTxt($latestDay['total']['f'], $latestDay['total']['q']) ?> Done</span></div>
                <b><?= $latestDay['total']['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $latestDay['total']['q'] ?> shoes</span></b>
                <div class="dw-kpi-sub"><span><?= $latestDay['shut'] ?> shutdown line<?= $latestDay['shut'] === 1 ? '' : 's' ?> in <?= esc($latestDay['short'] ?? $latestDay['date']) ?></span><span class="dw-kpi-jump">View Latest &rarr;</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $latestDay['total']['q'] ? round($latestDay['total']['f'] / $latestDay['total']['q'] * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#ea580c" data-dw-href="<?= esc($hrefFail, 'attr') ?>" title="Open the Bonding Failure Overview" data-dw-action="test-sec" data-dw-sec="retest">
                <div class="dw-kpi-top"><small>Failed Retest Bonding (<?= esc($windowTag) ?>)</small><span class="dw-kpi-tag" style="background:#fff7ed;color:#c2410c"><?= $pctTxt($retestSec['f'], $retestSec['q']) ?> Done</span></div>
                <b style="color:#ea580c"><?= $retestSec['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $retestSec['q'] ?></span></b>
                <div class="dw-kpi-sub"><span>Corrective re-tests on failed lines</span><span class="dw-kpi-jump">Highlight Retest &rarr;</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $retestSec['q'] ? round($retestSec['f'] / $retestSec['q'] * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#2563eb" data-dw-href="<?= esc($hrefTrial, 'attr') ?>" title="Open the Lab Tracker: Tech Trial only" data-dw-action="test-sec" data-dw-sec="trial">
                <div class="dw-kpi-top"><small>Tech Trial (<?= esc($windowTag) ?>)</small><span class="dw-kpi-tag"><?= $pctTxt($trialSec['f'], $trialSec['q']) ?> Done</span></div>
                <b style="color:#2563eb"><?= $trialSec['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $trialSec['q'] ?></span></b>
                <div class="dw-kpi-sub"><span>Technical &amp; adhesive trial runs</span><span class="dw-kpi-jump">Highlight Trials &rarr;</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $trialSec['q'] ? round($trialSec['f'] / $trialSec['q'] * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#7c3aed" data-dw-href="<?= esc($hrefFirst, 'attr') ?>" title="Open the Lab Tracker: First Article only" data-dw-action="test-sec" data-dw-sec="first sample">
                <div class="dw-kpi-top"><small>First Article &amp; Samples (<?= esc($windowTag) ?>)</small><span class="dw-kpi-tag" style="background:#f5f3ff;color:#6d28d9"><?= $pctTxt($firstSampleF, $firstSampleQ) ?> Done</span></div>
                <b style="color:#7c3aed"><?= $firstSampleF ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $firstSampleQ ?></span></b>
                <div class="dw-kpi-sub"><span>Qualification &amp; development samples</span><span class="dw-kpi-jump">Highlight Rows &rarr;</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $firstSampleQ ? round($firstSampleF / $firstSampleQ * 100) : 0 ?>%"></i></div>
            </button>
        </div>
        <?php endif ?>

        <!-- Daily Bonding & Whole-Shoe Testing Log -->
        <div class="dw-card" id="dw-days-card">
            <div class="dw-card-h">
                <div>
                    <div class="dw-card-h-title"><?= $areaTab === 'wholeshoe' ? 'Whole Shoe Testing Workload' : 'Daily Bonding Testing' ?></div>
                    <div class="dw-card-h-sub">Shows how many <?= $areaTab === 'wholeshoe' ? 'whole shoe' : 'bonding shoe' ?> specimens were collected and finished testing (<?= esc($windowLabel) ?>)</div>
                </div>
            </div>
            <?php
            // Combine all days in the selected window into a single summary table so no day pill strip is needed
            $aggCells = [];
            foreach ($dw['sections'] as $sk => [$tUnused, $labels]) {
                foreach ($labels as $lb) {
                    $qSum = 0; $fSum = 0; $nfSum = 0; $pDates = []; $tDates = [];
                    foreach ($dw['days'] as $dItem) {
                        $cell = $dItem['cells'][$sk][$lb] ?? ['q' => 0, 'f' => 0, 'prod' => '', 'test' => ''];
                        $qSum += (int) ($cell['q'] ?? 0);
                        $fSum += (int) ($cell['f'] ?? 0);
                        $nfSum += (int) ($cell['nf'] ?? 0);
                        if (!empty($cell['prod'])) {
                            $pDates[] = (string) $cell['prod'];
                        }
                        if (!empty($cell['test']) && $cell['test'] !== 'Pending') {
                            $tDates[] = (string) $cell['test'];
                        }
                    }
                    $aggCells[$sk][$lb] = [
                        'q'    => $qSum,
                        'f'    => $fSum,
                        'nf'   => $nfSum,
                        'prod' => $pDates ? (min($pDates) === max($pDates) ? min($pDates) : min($pDates) . ' – ' . max($pDates)) : '',
                        'test' => $tDates ? (min($tDates) === max($tDates) ? min($tDates) : min($tDates) . ' – ' . max($tDates)) : '',
                    ];
                }
            }
            $isMultiDay = count($dw['days']) > 1;
            $singleDay  = $dw['days'][0] ?? ['date' => $asof, 'label' => $asof];
            $paneDate   = $isMultiDay ? $wTo : (string) $singleDay['date'];
            $paneLabel  = $isMultiDay
                ? $windowLabel
                : ('Daily Bonding Tested: ' . ($singleDay['label'] ?? $paneDate) . ' (' . date('l', strtotime($paneDate)) . ')');
            ?>
            <div class="dw-day-pane" data-day="<?= esc($paneDate, 'attr') ?>">
                <div class="dw-card-explain">
                    <span><b><?= esc($paneLabel) ?></b></span>
                    <span>Finished: <b><?= $threeDayFin ?></b> of <b><?= $threeDayQty ?></b> specimens · Shutdown Lines: <b><?= $threeDayShut ?></b></span>
                </div>
                <div class="dw-tbl-wrap">
                    <table class="dw-tbl">
                        <thead>
                            <tr>
                                <th>Test Category &amp; Specimen Type</th>
                                <th style="text-align:left">Production &rarr; Tested Date</th>
                                <th>Quantity</th>
                                <th>Finished</th>
                                <th title="Finished tests whose result is FAILED (or rejected); a tolerable result is not counted here">Failed</th>
                                <th>Remaining</th>
                                <th>Status / Timeline</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $totNF = 0; foreach ($dw['sections'] as $sk => [$title, $labels]):
                            [$secFriendly, $secExplain] = $sectionDescriptions[$sk] ?? [$title, ''];
                        ?>
                            <tr class="dw-sechd" data-sec="<?= esc($sk, 'attr') ?>">
                                <td colspan="7">
                                    <div class="dw-sechd-wrap">
                                        <span class="dw-sechd-title"><?= esc($title) ?> &mdash; <?= esc($secFriendly) ?></span>
                                        <span class="dw-sechd-desc"><?= esc($secExplain) ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php foreach ($labels as $lb):
                                $c = $aggCells[$sk][$lb];
                                $rem = $c['q'] - $c['f'];
                                $e = $est($c['q'], $c['f'], $paneDate);
                            ?>
                                <tr data-sec-row="<?= esc($sk, 'attr') ?>">
                                    <td style="font-weight:600;padding-left:16px"><?= esc($lb) ?></td>
                                    <td class="dw-dt" style="text-align:left">
                                        <?php if ($c['q'] > 0): ?>
                                            Prod: <b><?= esc($c['prod'] ?: '–') ?></b> &rarr; Tested: <b><?= esc($c['test'] ?: 'Pending') ?></b>
                                        <?php else: ?>
                                            <span class="z">No specimens submitted</span>
                                        <?php endif ?>
                                    </td>
                                    <td class="<?= $c['q'] ? '' : 'z' ?>" style="font-weight:700"><?= $c['q'] ?></td>
                                    <td class="<?= $c['f'] ? '' : 'z' ?>" style="font-weight:700;color:#059669"><?= $c['f'] ?></td>
                                    <?php $nf = (int) ($c['nf'] ?? 0); $totNF += $nf; ?>
                                    <td class="<?= $nf ? '' : 'z' ?>" <?= $nf ? 'style="color:#dc2626;font-weight:800"' : '' ?>><?= $nf ?></td>
                                    <td class="<?= $rem > 0 ? '' : 'z' ?>" <?= $rem > 0 ? 'style="color:#b45309;font-weight:800"' : '' ?>><?= $rem ?></td>
                                    <td>
                                        <span class="dw-pill <?= $e === 'COMPLETED' ? 'dw-done' : ($e === 'No Intake' ? 'dw-na' : 'dw-wait') ?>">
                                            <?= $e === 'COMPLETED' ? '&#10003; DONE' : esc($e) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        <?php endforeach ?>
                            <tr class="dw-tot">
                                <td>Total Specimens (<?= esc($isMultiDay ? $windowTag : $paneDate) ?>)</td>
                                <td></td>
                                <td><?= $threeDayQty ?></td>
                                <td><?= $threeDayFin ?></td>
                                <td<?= $totNF > 0 ? ' style="color:#dc2626"' : '' ?>><?= $totNF ?></td>
                                <td><?= max(0, $threeDayQty - $threeDayFin) ?></td>
                                <td><?= $threeDayQty > 0 ? round($threeDayFin / $threeDayQty * 100) . '% Complete' : '–' ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php if ($threeDayShut > 0): ?>
                    <div class="dw-note">
                        <span>&#9888; Production Line Notice: <b><?= $threeDayShut ?></b> daily bonding shoe line<?= $threeDayShut === 1 ? ' was' : 's were' ?> marked <b>SHUTDOWN</b> (inactive / no production) in <?= esc($isMultiDay ? $windowLabel : $paneDate) ?>.</span>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div><!-- /data-stage-panel="2" -->

    <?php
    /* Stage 4 aggregates & SVG grouped comparison chart across ETRF / INTERLINK / ERP */
    $combQ = $sysTotals['etrf']['q'] + $sysTotals['il']['q'] + $sysTotals['erp']['q'];
    $combF = $sysTotals['etrf']['f'] + $sysTotals['il']['f'] + $sysTotals['erp']['f'];

    $w4 = 580; $h4 = 225; $l4 = 42; $r4 = 20; $t4 = 22; $b4 = 34; $pw4 = $w4 - $l4 - $r4; $ph4 = $h4 - $t4 - $b4;
    $cEncComp = '';
    for ($i = 0; $i <= 4; $i++) {
        $gy = $t4 + $ph4 - $i / 4 * $ph4;
        $cEncComp .= '<line x1="' . $l4 . '" x2="' . ($w4 - $r4) . '" y1="' . $gy . '" y2="' . $gy . '" stroke="rgba(148,163,184,.18)" stroke-dasharray="4 4"/>';
        $cEncComp .= '<text x="' . ($l4 - 7) . '" y="' . ($gy + 4) . '" text-anchor="end" fill="#94a3b8" font-size="10" font-weight="600">' . ($i * 25) . '%</text>';
    }
    $nCat = max(1, count($rowsEncAll));
    $slot4 = $pw4 / $nCat;
    $ci4 = 0;
    $pColors = ['etrf' => '#38bdf8', 'il' => '#a78bfa', 'erp' => '#34d399'];
    foreach ($rowsEncAll as $rn => $r) {
        $cx = $l4 + $slot4 * ($ci4 + 0.5);
        $keysRow = $rn === 'Whole Shoe' ? ['etrf', 'il'] : ['etrf', 'il', 'erp'];
        $cntP = count($keysRow);
        $bw = min(24, ($slot4 * .68) / $cntP);
        $gap = 4;
        $groupW = $cntP * $bw + ($cntP - 1) * $gap;
        $xStart = $cx - $groupW / 2;
        foreach ($keysRow as $pi => $pk) {
            $qVal = $r['q'];
            $fVal = min($qVal, $r[$pk]);
            $pctVal = $qVal > 0 ? round($fVal / $qVal * 100, 1) : 0;
            $bh = $pctVal / 100 * $ph4;
            $bx = $xStart + $pi * ($bw + $gap);
            if ($bh > 0) {
                $cEncComp .= '<rect x="' . round($bx, 1) . '" y="' . round($t4 + $ph4 - $bh, 1) . '" width="' . round($bw, 1) . '" height="' . round($bh, 1) . '" rx="3" fill="' . $pColors[$pk] . '"><title>' . esc($rn) . ' · ' . strtoupper($pk) . ': ' . $fVal . '/' . $qVal . ' (' . $pctVal . '%)</title></rect>';
            }
            $cEncComp .= '<text x="' . round($bx + $bw / 2, 1) . '" y="' . round($t4 + $ph4 - $bh - 4, 1) . '" text-anchor="middle" fill="#e2e8f0" font-size="9" font-weight="700">' . round($pctVal) . '%</text>';
        }
        $catLbl = $rn === 'Materials' ? 'Materials (' . substr($dw['monthName'], 0, 3) . ')' : $rn;
        $cEncComp .= '<text x="' . round($cx, 1) . '" y="' . ($t4 + $ph4 + 18) . '" text-anchor="middle" fill="#cbd5e1" font-size="11" font-weight="700">' . esc($catLbl) . '</text>';
        $ci4++;
    }
    ?>

    <!-- ===== STAGE 4: System Encoding (3 Portals) ===== -->
    <div class="dw-stage-pane" data-stage-panel="4" hidden>
        <div class="dw-kpis">
            <button type="button" class="dw-kpi" style="--kc:#2563eb" data-dw-action="sys-portal" data-dw-portal="etrf">
                <div class="dw-kpi-top"><small>ETRF Portal (<?= esc($dw['monthName']) ?>)</small><span class="dw-kpi-tag"><?= $pctTxt($sysTotals['etrf']['f'], $sysTotals['etrf']['q']) ?></span></div>
                <b style="color:#2563eb"><?= $sysTotals['etrf']['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $sysTotals['etrf']['q'] ?></span></b>
                <div class="dw-kpi-sub"><span><?= max(0, $sysTotals['etrf']['q'] - $sysTotals['etrf']['f']) ?> waiting for ETRF</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $sysTotals['etrf']['q'] ? round($sysTotals['etrf']['f'] / $sysTotals['etrf']['q'] * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#7c3aed" data-dw-action="sys-portal" data-dw-portal="il">
                <div class="dw-kpi-top"><small>INTERLINK Portal (<?= esc($dw['monthName']) ?>)</small><span class="dw-kpi-tag"><?= $pctTxt($sysTotals['il']['f'], $sysTotals['il']['q']) ?></span></div>
                <b style="color:#7c3aed"><?= $sysTotals['il']['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $sysTotals['il']['q'] ?></span></b>
                <div class="dw-kpi-sub"><span><?= max(0, $sysTotals['il']['q'] - $sysTotals['il']['f']) ?> waiting for INTERLINK</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $sysTotals['il']['q'] ? round($sysTotals['il']['f'] / $sysTotals['il']['q'] * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#059669" data-dw-action="sys-portal" data-dw-portal="erp">
                <div class="dw-kpi-top"><small>Factory ERP (<?= esc($dw['monthName']) ?>)</small><span class="dw-kpi-tag"><?= $pctTxt($sysTotals['erp']['f'], $sysTotals['erp']['q']) ?></span></div>
                <b style="color:#059669"><?= $sysTotals['erp']['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $sysTotals['erp']['q'] ?></span></b>
                <div class="dw-kpi-sub"><span><?= max(0, $sysTotals['erp']['q'] - $sysTotals['erp']['f']) ?> waiting for ERP</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $sysTotals['erp']['q'] ? round($sysTotals['erp']['f'] / $sysTotals['erp']['q'] * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#0284c7" data-dw-action="enc-matrix">
                <div class="dw-kpi-top"><small>Combined 3-Portal Rate</small><span class="dw-kpi-tag" style="background:#e0f2fe;color:#0369a1"><?= $pctTxt($combF, $combQ) ?></span></div>
                <b style="color:#0284c7"><?= $pctTxt($combF, $combQ) ?></b>
                <div class="dw-kpi-sub"><span><?= number_format($combF) ?> / <?= number_format($combQ) ?> total uploads</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $combQ ? round($combF / $combQ * 100) : 0 ?>%"></i></div>
            </button>
        </div>

        <div class="dw-grid2" style="grid-template-columns:minmax(0,1fr) minmax(0,1.15fr)">
            <!-- Left Card: 3-Portal Comparison Chart & Cross-Portal Matrix -->
            <div class="dw-card" id="dw-card-enc-compare">
                <div class="dw-card-h">
                    <div>
                        <div class="dw-card-h-title">3-Portal Completion Comparison (<?= esc($dw['monthName']) ?>)</div>
                        <div class="dw-card-h-sub">Side-by-side encoding % across ETRF, INTERLINK, and Factory ERP by test category</div>
                    </div>
                </div>
                <div class="dw-chart">
                    <svg viewBox="0 0 <?= $w4 ?> <?= $h4 ?>" role="img" aria-label="3-portal completion comparison bar chart"><?= $cEncComp ?></svg>
                    <div class="dw-leg">
                        <span><i style="background:#38bdf8"></i>ETRF Portal</span>
                        <span><i style="background:#a78bfa"></i>INTERLINK System</span>
                        <span><i style="background:#34d399"></i>Factory ERP</span>
                    </div>
                </div>
                <div class="dw-tbl-wrap">
                    <table class="dw-tbl" id="dw-tbl-enc-matrix">
                        <thead>
                            <tr>
                                <th>Report Category</th>
                                <th>Total Qty</th>
                                <th>ETRF</th>
                                <th>INTERLINK</th>
                                <th>Factory ERP</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rowsEncAll as $rn => $r):
                            $q = $r['q'];
                            $eF = min($q, $r['etrf']);
                            $iF = min($q, $r['il']);
                            $rF = min($q, $r['erp']);
                        ?>
                            <tr>
                                <td><?= esc($rn === 'Materials' ? 'Materials (' . $dw['monthName'] . ')' : $rn) ?></td>
                                <td style="font-weight:700"><?= $q ?></td>
                                <td><b><?= $eF ?></b> <span class="dw-type-pct">(<?= $pctTxt($eF, $q) ?>)</span></td>
                                <td><b><?= $iF ?></b> <span class="dw-type-pct">(<?= $pctTxt($iF, $q) ?>)</span></td>
                                <td>
                                    <?php if ($rn === 'Whole Shoe'): ?>
                                        <span class="z">N/A</span>
                                    <?php else: ?>
                                        <b><?= $rF ?></b> <span class="dw-type-pct">(<?= $pctTxt($rF, $q) ?>)</span>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                            <tr class="dw-tot">
                                <td>Portal Totals (<?= esc($dw['monthName']) ?>)</td>
                                <td><?= $sysTotals['etrf']['q'] ?></td>
                                <td><?= $sysTotals['etrf']['f'] ?> (<?= $pctTxt($sysTotals['etrf']['f'], $sysTotals['etrf']['q']) ?>)</td>
                                <td><?= $sysTotals['il']['f'] ?> (<?= $pctTxt($sysTotals['il']['f'], $sysTotals['il']['q']) ?>)</td>
                                <td><?= $sysTotals['erp']['f'] ?>/<?= $sysTotals['erp']['q'] ?> (<?= $pctTxt($sysTotals['erp']['f'], $sysTotals['erp']['q']) ?>)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Card: Test Reports Encoded (ETRF / INTERLINK / ERP) -->
            <div class="dw-card" id="dw-enc">
                <div class="dw-card-h">
                    <div>
                        <div class="dw-card-h-title">Test Reports Encoded as of <?= esc($asof) ?> (<?= esc($dw['monthName']) ?>)</div>
                        <div class="dw-card-h-sub">Click any system below to inspect report encoding backlog by test type</div>
                    </div>
                </div>

                <!-- Interactive 3-System Selector Cards -->
                <div class="dw-sys-grid">
                    <?php foreach ($sysTotals as $sk => $st):
                        $sp = $st['q'] > 0 ? round($st['f'] / $st['q'] * 100, 1) : 0;
                    ?>
                        <button type="button" class="dw-sys-card<?= $sk === 'etrf' ? ' on' : '' ?>" data-k="<?= $sk ?>">
                            <small><?= esc($st['name']) ?></small>
                            <b style="color:<?= $st['color'] ?>"><?= $sp ?>% <span style="font-size:12px;font-weight:700;color:#64748b">(<?= $st['f'] ?>/<?= $st['q'] ?>)</span></b>
                            <span><?= esc($st['desc']) ?></span>
                        </button>
                    <?php endforeach ?>
                </div>

                <?php
                $dayOfMonth = $period === 'weekly' ? 7 : ($period === 'monthly' ? max(1, (int) date('t', strtotime($asof))) : 1);
                $encScopeLbl = $period === 'weekly' ? 'Weekly (' . $dw['monthName'] . ')' : ($period === 'monthly' ? 'Full Month (' . $dw['monthName'] . ')' : 'Daily (' . $dw['monthName'] . ')');
                foreach (['etrf' => 'ETRF Portal', 'il' => 'INTERLINK System', 'erp' => 'Factory ERP'] as $k => $nm):
                    $tq = $tf = 0;
                    $rowsE = $dw['cat'] + ['Materials' => $dw['materials']];
                ?>
                    <div class="dw-pane" data-k="<?= $k ?>"<?= $k === 'etrf' ? '' : ' hidden' ?>>
                        <div class="dw-card-explain">
                            <span><b><?= esc($nm) ?></b> &mdash; <?= esc($sysTotals[$k]['desc']) ?></span>
                            <span><?= esc($encScopeLbl) ?></span>
                        </div>
                        <div class="dw-tbl-wrap">
                            <table class="dw-tbl">
                                <thead>
                                    <tr>
                                        <th>Report Category</th>
                                        <th>Total Qty</th>
                                        <th>Encoded</th>
                                        <th>Remaining</th>
                                        <th>% Done</th>
                                        <th>Est. Completion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($rowsE as $rn => $r):
                                    if ($k === 'erp' && $rn === 'Whole Shoe') {
                                        continue;
                                    }
                                    $q = $r['q'];
                                    $f = min($q, $r['etrf'] * ($k === 'etrf') + $r['il'] * ($k === 'il') + $r['erp'] * ($k === 'erp'));
                                    $rem = $q - $f;
                                    $tq += $q;
                                    $tf += $f;
                                    $pRow = $q > 0 ? round($f / $q * 100) : 0;
                                    $e = $est($q, $f, $asof, $f / $dayOfMonth);
                                ?>
                                    <tr>
                                        <td>
                                            <b><?= esc($rn === 'Materials' ? 'Materials (' . $dw['monthName'] . ')' : $rn) ?></b>
                                        </td>
                                        <td class="<?= $q ? '' : 'z' ?>" style="font-weight:700"><?= $q ?></td>
                                        <td class="<?= $f ? '' : 'z' ?>" style="font-weight:700;color:#059669"><?= $f ?></td>
                                        <td <?= $rem > 0 ? 'style="color:#b45309;font-weight:800"' : 'class="z"' ?>><?= $rem ?></td>
                                        <td>
                                            <span class="dw-pct">
                                                <i><b style="width:<?= $pRow ?>%;<?= $pRow < 100 ? 'background:#f59e0b' : '' ?>"></b></i>
                                                <span><?= $pRow ?>%</span>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="dw-pill <?= $e === 'COMPLETED' ? 'dw-done' : ($e === 'No Intake' ? 'dw-na' : 'dw-wait') ?>">
                                                <?= $e === 'COMPLETED' ? '&#10003; DONE' : esc($e) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                                    <tr class="dw-tot">
                                        <td>Total (<?= esc($nm) ?>)</td>
                                        <td><?= $tq ?></td>
                                        <td><?= $tf ?></td>
                                        <td><?= $tq - $tf ?></td>
                                        <td><?= $pctTxt($tf, $tq) ?></td>
                                        <td><?= $est($tq, $tf, $asof, $tf / $dayOfMonth) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach ?>
                <div class="dw-foot">
                    <b>What this table means:</b> Physical testing in the lab finishes first, and technicians then encode the official test reports into <b>ETRF</b>, <b>INTERLINK</b>, and <b>ERP</b>. <i>Remaining</i> shows tested specimens awaiting digital report submission.
                </div>
            </div>
        </div>
    </div><!-- /data-stage-panel="4" -->
</div>

<script>
(function () {
  var encBox = document.getElementById('dw-enc');
  var dayCard = document.getElementById('dw-days-card');
  var matSummaryCard = document.getElementById('dw-card-mat-summary');
  var matTypesCard = document.getElementById('dw-card-mat-types');

  function clearHighlights() {
    document.querySelectorAll('.dw-col-hi, .dw-row-hi').forEach(function (el) {
      el.classList.remove('dw-col-hi', 'dw-row-hi');
    });
    document.querySelectorAll('.dw-card.dw-focus').forEach(function (el) {
      el.classList.remove('dw-focus');
    });
  }

  function focusCard(card) {
    if (!card) return;
    card.classList.remove('dw-focus');
    void card.offsetWidth;
    card.classList.add('dw-focus');
    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function highlightTableColumn(tableId, colKey) {
    var tbl = document.getElementById(tableId);
    if (!tbl || !colKey) return;
    tbl.querySelectorAll('[data-col="' + colKey + '"]').forEach(function (cell) {
      cell.classList.add('dw-col-hi');
    });
  }

  function switchPortal(k) {
    if (!encBox) return;
    encBox.querySelectorAll('.dw-sys-card').forEach(function (x) {
      x.classList.toggle('on', x.getAttribute('data-k') === k);
    });
    encBox.querySelectorAll('.dw-pane').forEach(function (p) {
      p.hidden = p.getAttribute('data-k') !== k;
    });
  }

  function switchDay(d) {
    if (!dayCard) return;
    dayCard.querySelectorAll('.dw-day-btn').forEach(function (x) {
      x.classList.toggle('on', x.getAttribute('data-day') === d);
    });
    dayCard.querySelectorAll('.dw-day-pane').forEach(function (p) {
      p.hidden = (d !== 'all' && p.getAttribute('data-day') !== d);
    });
  }

  // System Encoding Switcher (ETRF / INTERLINK / ERP)
  if (encBox) {
    encBox.querySelectorAll('.dw-sys-card').forEach(function (btn) {
      btn.addEventListener('click', function () {
        switchPortal(btn.getAttribute('data-k'));
      });
    });
  }

  // 3-Day Rolling Workload Switcher (Single Day or All 3 Days Stacked)
  if (dayCard) {
    dayCard.querySelectorAll('.dw-day-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        switchDay(btn.getAttribute('data-day'));
      });
    });
  }

  // Date picker (same as Daily Bonding): Today / Yesterday, Weekly, Monthly, Custom calendar range
  (function () {
    var root = document.getElementById('dw-form'); if (!root) return;
    var $ = function (id) { return document.getElementById(id); };
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var iso = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
    var parse = function (s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); };
    var today = parse(root.dataset.today);
    var MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    var short = function (d) { return MONTHS[d.getMonth()].slice(0, 3) + ' ' + d.getDate(); };
    var isoWeek = function (d) {
      var t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
      var dn = t.getUTCDay() || 7; t.setUTCDate(t.getUTCDate() + 4 - dn);
      var y0 = new Date(Date.UTC(t.getUTCFullYear(), 0, 1));
      return t.getUTCFullYear() + '-W' + pad(Math.ceil(((t - y0) / 86400000 + 1) / 7));
    };
    var go = function (q) {
      q.tab = root.dataset.tab; q.view = root.dataset.view;
      window.location.href = root.dataset.action + '?' + new URLSearchParams(q).toString();
    };
    var goDay = function (d) { go({ period: 'daily', asof: iso(d), year: d.getFullYear() }); };
    window.NxDateFilter.mount({ host: root, btn: $('dw-dr-btn'), from: root.dataset.from, to: root.dataset.to, allowAll: false, onApply: function (f, t) { if (f === t) { goDay(parse(f)); return; } go({ from: f, to: t }); } });
  })();

  // Keep week/date synced when the Year dropdown changes, then submit
  var dwForm = document.getElementById('dw-form');
  var dwYearSel = document.getElementById('dw-year-sel');
  if (dwForm && dwYearSel) {
    dwYearSel.addEventListener('change', function () {
      var y = dwYearSel.value;
      var wInp = document.getElementById('dw-week-inp');
      var dInp = document.getElementById('dw-date-inp');
      if (wInp && wInp.value) {
        var wk = (wInp.value.split('-W')[1] || '01');
        wInp.value = y + '-W' + wk;
      }
      if (dInp && dInp.value) {
        var rest = dInp.value.slice(4);
        dInp.value = y + (rest === '-02-29' && (+y % 4 !== 0 || (+y % 100 === 0 && +y % 400 !== 0)) ? '-02-28' : rest);
      }
      dwForm.submit();
    });
  }

  // Stage tabs: 1 = Material Intake, 2 = Physical & Bonding Testing, 3 = Re-Test & Line Control, 4 = System Encoding
  var STAGE_OF = {
    'stage-intake': 1, 'mat-recv': 1, 'mat-enc': 1, 'mat-pct': 1, 'mat-type': 1,
    'stage-testing': 2, 'day-latest': 2, 'test-all': 2, 'test-sec': 2,
    'stage-retest': 3, 'rt-log': 3, 'rt-lines': 3,
    'stage-encoding': 4, 'sys-portal': 4, 'enc-matrix': 4
  };
  var rtLogCard = document.getElementById('dw-card-retest-log');
  var rtLineCard = document.getElementById('dw-card-line-ctrl');
  var encCompCard = document.getElementById('dw-card-enc-compare');

  function showStage(n) {
    document.querySelectorAll('[data-stage-panel]').forEach(function (el) {
      el.hidden = el.getAttribute('data-stage-panel') !== String(n);
    });
  }

  showStage(<?= (int) $activeStageNum ?>);

  // Clickable Stage Tabs & KPI Cards
  var interactiveCards = document.querySelectorAll('[data-dw-action]');
  interactiveCards.forEach(function (card) {
    card.addEventListener('click', function () {
      var href = card.getAttribute('data-dw-href');
      if (href) { window.location.href = href; return; }
      var act = card.getAttribute('data-dw-action');
      var st = STAGE_OF[act];
      if (st) { showStage(st); }
      interactiveCards.forEach(function (c) { c.classList.toggle('is-active', c === card); });
      clearHighlights();

      if (act === 'stage-intake' || act === 'stage-testing' || act === 'stage-retest' || act === 'stage-encoding') {
        return;
      } else if (act === 'mat-recv') {
        highlightTableColumn('dw-tbl-mat-summary', 'recv');
        focusCard(matSummaryCard);
      } else if (act === 'mat-enc') {
        highlightTableColumn('dw-tbl-mat-summary', 'enc');
        focusCard(matSummaryCard);
      } else if (act === 'mat-pct') {
        highlightTableColumn('dw-tbl-mat-summary', 'pct');
        focusCard(matSummaryCard);
      } else if (act === 'mat-type') {
        var tp = card.getAttribute('data-dw-type') || 'Accessories';
        highlightTableColumn('dw-tbl-mat-types', tp);
        focusCard(matTypesCard);
      } else if (act === 'day-latest') {
        var targetDay = card.getAttribute('data-dw-day');
        if (targetDay) { switchDay(targetDay); }
        focusCard(dayCard);
      } else if (act === 'test-all') {
        switchDay('all');
        focusCard(dayCard);
      } else if (act === 'test-sec') {
        var secs = (card.getAttribute('data-dw-sec') || 'daily').split(' ');
        switchDay('all');
        if (dayCard) {
          secs.forEach(function (secKey) {
            dayCard.querySelectorAll('[data-sec-row="' + secKey + '"]').forEach(function (r) {
              r.classList.add('dw-row-hi');
            });
          });
        }
        focusCard(dayCard);
      } else if (act === 'rt-log') {
        focusCard(rtLogCard);
      } else if (act === 'rt-lines') {
        focusCard(rtLineCard);
      } else if (act === 'sys-portal') {
        var portal = card.getAttribute('data-dw-portal') || 'etrf';
        switchPortal(portal);
        focusCard(encBox);
      } else if (act === 'enc-matrix') {
        focusCard(encCompCard);
      }
    });
  });
})();
</script>
