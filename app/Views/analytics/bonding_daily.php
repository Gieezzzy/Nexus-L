<?php
$ov = $overall ?? ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0, 'pend' => 0, 'shut' => 0];
$totTested = (int) $ov['tested'];
$totPass   = (int) $ov['pass'];
$totFail   = (int) $ov['fail'];
$totTol    = (int) $ov['tol'];
$passRate  = $totTested > 0 ? round($totPass / $totTested * 100, 1) : 0.0;
$rateBase  = (int) ($ov['rate_base'] ?? 0) > 0 ? (int) $ov['rate_base'] : $totTested;   // same base as the Monthly Summary failed rate
$failRate  = $rateBase > 0 ? round($totFail / $rateBase * 100, 2) : 0.0;

$bA = $bldgStats['A'] ?? ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0, 'shut' => 0];
$bB = $bldgStats['B'] ?? ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0, 'shut' => 0];
$bAFailPct = $bA['tested'] > 0 ? round($bA['fail'] / $bA['tested'] * 100, 2) : 0.0;
$bBFailPct = $bB['tested'] > 0 ? round($bB['fail'] / $bB['tested'] * 100, 2) : 0.0;

$periodLabel = ($from !== '' || $to !== '')
    ? (($from ?: '…') . ' to ' . ($to ?: '…'))
    : 'All Dates';

$causeHints = [
    'Poor Pressing'              => 'Press machine pressure or dwell time was insufficient',
    'Weak Material'              => 'Material tore before the glue bond gave way',
    'Hairy Leather'              => 'Fuzzy/unbuffed leather fibers prevented full glue grip',
    'Splitting Of Foxing Tape'   => 'Side rubber strip split internally during peel test',
    'Ink Delamination'           => 'Printed ink layer peeled off the upper material',
    'Poor Cement'                => 'Adhesive bond strength was too weak',
    'Poor Cementing'             => 'Uneven or insufficient glue application',
    'Overdried Cement'           => 'Glue dried too long before pressing',
    'Poor Primer'                => 'Surface primer did not activate bonding properly',
    'Poor Priming'               => 'Uneven or missed primer coat on surface',
    'Poor Buffing'               => 'Rubber/upper surface was not roughened enough',
    'Insufficient Skirt'         => 'Not enough overlap margin on sole skirt',
    'Splitting Of Rubber Outsole'=> 'Outsole rubber tore internally',
    'Dusty Outsole'              => 'Dust on outsole blocked adhesive contact',
];
?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/analytics-bonding_daily-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/analytics-bonding_daily-1.css') ?>">

<div class="bd-wrap">
    <?php if ($totTested > 0): ?>
        <?= anSummary(
            $passRate . '% of tested shoes passed the bonding test (' . $periodLabel . ').',
            number_format($totTested) . ' shoes were tested and ' . number_format($totFail) . ' failed (' . $failRate . '%). '
                . 'Building A failed ' . $bAFailPct . '% and Building B ' . $bBFailPct . '%'
                . ($bAFailPct > 5 || $bBFailPct > 5 ? ' — a building above 5% needs attention.' : ' — both are within the 5% watch level.'),
            ($bAFailPct > 5 || $bBFailPct > 5) ? 'warn' : anTone((float) $passRate)
        ) ?>
    <?php endif ?>
    <!-- Executive KPI Cards -->
    <div class="bd-kpis">
        <div class="card glass ms-kpi-card">
            <div>
                <small>Total Tested Shoes</small>
                <b><?= number_format($totTested) ?></b>
                <span class="sub">Left + Right specimens &middot; <?= esc($periodLabel) ?></span>
            </div>
            <span class="ms-kpi-ico ms-ico-blue"><svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-flask"/></svg></span>
        </div>
        <div class="card glass ms-kpi-card">
            <div>
                <small>Passed Shoes</small>
                <div class="kpi-val-row">
                    <b class="an-pass"><?= number_format($totPass) ?></b>
                    <span class="kpi-pct pct-pass"><?= $passRate ?>%</span>
                </div>
                <span class="sub">Meets VF / Vans Peel Strength Standard</span>
            </div>
            <span class="ms-kpi-ico ms-ico-pass">&#10003;</span>
        </div>
        <div class="card glass ms-kpi-card">
            <div>
                <small>Failed Shoes</small>
                <div class="kpi-val-row">
                    <b class="an-fail"><?= number_format($totFail) ?></b>
                    <span class="kpi-pct pct-fail"><?= $failRate ?>%</span>
                </div>
                <span class="sub"><?= $totTol > 0 ? number_format($totTol) . ' tolerable &middot; ' : '' ?>Requires corrective action</span>
            </div>
            <span class="ms-kpi-ico ms-ico-fail">&#10005;</span>
        </div>
        <div class="card glass ms-kpi-card">
            <div>
                <small>Building Failure Rate</small>
                <div class="bd-bldg-row">
                    <span class="bd-bldg-item">Bldg A: <b style="color:<?= $bAFailPct > 5 ? '#dc2626' : '#0f172a' ?>"><?= $bAFailPct ?>%</b></span>
                    <span style="color:#cbd5e1">|</span>
                    <span class="bd-bldg-item">Bldg B: <b style="color:<?= $bBFailPct > 5 ? '#dc2626' : '#0f172a' ?>"><?= $bBFailPct ?>%</b></span>
                </div>
                <span class="sub">A: <?= $bA['fail'] ?>/<?= $bA['tested'] ?> failed &middot; B: <?= $bB['fail'] ?>/<?= $bB['tested'] ?> failed</span>
            </div>
            <span class="ms-kpi-ico ms-ico-warn"><svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-bolt"/></svg></span>
        </div>
    </div>

    <!-- Distribution of Failure Cause (%) -->
    <?php
    $pieTot = array_sum($causeCounts ?? []);
    $piePalette = ['#5B9BD5', '#A5A5A5', '#FFC000', '#4472C4', '#ED7D31', '#70AD47', '#255E91', '#9E480E', '#636363', '#997300', '#7CAFDD', '#F4B183'];
    $pieN = 0;
    ?>
    <div class="bd-card">
        <div class="bd-card-hd">
            <div>
                <h3>Distribution of Failure Cause (%) &mdash; <?= esc($periodLabel) ?><?= anTip('Root cause = the reason the lab recorded for why the glue bond failed.') ?></h3>
                <p class="an-cap" style="margin:0 0 4px">The biggest slice is the most common reason for failure, so fix that one first.</p>
                <p>Why the failed shoes failed, by root cause<?= ! empty($noCause) ? ' &middot; ' . (int) $noCause . ' failed shoe(s) have no cause recorded and are not in the chart' : '' ?>.</p>
            </div>
        </div>
        <?php if ($pieTot <= 0): ?>
            <p class="muted sm" style="margin:0">No failure root causes recorded in this period.</p>
        <?php else: ?>
            <?php
            $cx = 150; $cy = 150; $rad = 112; $ang = -M_PI / 2; $svg = ''; $legend = ''; $tbl = '';
            foreach ($causeCounts as $cName => $cCnt) {
                $col = $piePalette[$pieN++ % count($piePalette)];
                $frac = $cCnt / $pieTot; $a2 = $ang + $frac * 2 * M_PI;
                $pct  = (int) round($frac * 100);
                if ($frac >= 0.9999) {
                    $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $rad . '" fill="' . $col . '" stroke="#fff" stroke-width="1.5"/>';
                } else {
                    $x1 = $cx + $rad * cos($ang); $y1 = $cy + $rad * sin($ang); $x2 = $cx + $rad * cos($a2); $y2 = $cy + $rad * sin($a2);
                    $svg .= '<path d="M' . $cx . ' ' . $cy . ' L' . round($x1, 2) . ' ' . round($y1, 2) . ' A' . $rad . ' ' . $rad . ' 0 ' . ($frac > 0.5 ? 1 : 0) . ' 1 ' . round($x2, 2) . ' ' . round($y2, 2) . ' Z" fill="' . $col . '" stroke="#fff" stroke-width="1.5"><title>' . esc($cName) . ': ' . (int) $cCnt . ' (' . $pct . '%)</title></path>';
                }
                if ($frac >= 0.04) {
                    $mid = ($ang + $a2) / 2;
                    $svg .= '<text x="' . round($cx + $rad * 0.66 * cos($mid), 1) . '" y="' . round($cy + $rad * 0.66 * sin($mid) + 4, 1) . '" text-anchor="middle" font-size="12" font-weight="800" fill="#fff" stroke="rgba(15,23,42,.35)" stroke-width=".4">' . $pct . '%</text>';
                }
                $legend .= '<div class="bd-leg-row"><i style="background:' . $col . '"></i><span>' . esc(strtoupper($cName)) . '</span><b>' . (int) $cCnt . ' (' . $pct . '%)</b></div>';
                $tbl    .= '<tr>'
                    . '<td><span style="display:inline-flex;align-items:center;gap:7px"><i style="width:9px;height:9px;border-radius:2px;background:' . $col . ';display:inline-block;flex-shrink:0"></i><b>' . esc(strtoupper($cName)) . '</b></span></td>'
                    . '<td><b>' . (int) $cCnt . '</b></td>'
                    . '<td><span style="padding:2px 7px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:800">' . number_format($frac * 100, 2) . '%</span></td>'
                    . '</tr>';
                $ang = $a2;
            }
            $tbl .= '<tr class="bd-tot-row"><td>TOTAL RECORDED CAUSES</td><td>' . (int) $pieTot . '</td><td>100.00%</td></tr>';
            ?>
            <div class="bd-pie-split">
                <div class="bd-pie-tbl">
                    <div class="bd-pie-tt">
                        <span>FAILURE CAUSE DISTRIBUTION</span>
                        <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:rgba(255,255,255,.16)"><?= (int) $pieTot ?> Total</span>
                    </div>
                    <table>
                        <thead>
                            <tr><th>Root Cause</th><th>Count</th><th>Share</th></tr>
                        </thead>
                        <tbody><?= $tbl ?></tbody>
                    </table>
                </div>
                <div class="bd-pie-box">
                    <div class="bd-pie-bt">Distribution of Failure Cause (%) &ndash; <?= esc(strtoupper($periodLabel)) ?></div>
                    <div class="bd-pie-wrap">
                        <svg viewBox="0 0 300 300" width="300" height="300" role="img" aria-label="Distribution of failure cause"><?= $svg ?></svg>
                        <div class="bd-leg"><?= $legend ?></div>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
    <!-- Building A & Building B Line Performance & Rankings -->
    <div class="bd-grid2">
        <?php foreach (['A' => 'Building A (Lines A1–A12)', 'B' => 'Building B (Lines B1–B14)'] as $bKey => $bTitle): ?>
            <?php $rk = $rankings[$bKey] ?? ['lines' => [], 'top' => [], 'low' => []]; ?>
            <div class="bd-card">
                <div class="bd-card-hd">
                    <div>
                        <h3><?= esc($bTitle) ?> &mdash; Line Rankings</h3>
                        <p>Top performing lines vs. lines needing attention, ranked by Pass Score.<?= anTip('Pass Score = the percentage of a line\'s tested shoes that passed. Higher is better.') ?></p>
                    </div>
                    <span class="bd-rank-b">Fail Rate: <?= $bKey === 'A' ? $bAFailPct : $bBFailPct ?>%</span>
                </div>
                <div class="bd-perf-split">
                    <div class="bd-perf-box top">
                        <h4>Top Performing Lines</h4>
                        <div class="bd-tag-row">
                            <?php if (empty($rk['top'])): ?><span class="muted sm">No tested lines</span><?php else: ?>
                                <?php foreach ($rk['top'] as $tl): ?>
                                    <span class="bd-ltag"><?= esc($tl['line']) ?> <b style="color:#15803d"><?= $tl['score'] ?>%</b></span>
                                <?php endforeach ?>
                            <?php endif ?>
                        </div>
                    </div>
                    <div class="bd-perf-box low">
                        <h4>Needs Attention</h4>
                        <div class="bd-tag-row">
                            <?php if (empty($rk['low'])): ?><span style="font-size:12px;color:#15803d;font-weight:700">All tested lines passed!</span><?php else: ?>
                                <?php foreach ($rk['low'] as $ll): ?>
                                    <span class="bd-ltag"><?= esc($ll['line']) ?> <b style="color:#dc2626"><?= $ll['failed'] ?> fail</b></span>
                                <?php endforeach ?>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
                <div style="max-height:310px;overflow:auto;border:1px solid #e2e8f0;border-radius:10px">
                    <table class="bd-rtbl">
                        <thead>
                            <tr><th>Line</th><th>Passed</th><th>Failed</th><th>Tested</th><th>Pass Score</th><th>Rank</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rk['lines'] as $lr): ?>
                                <?php
                                $sc = $lr['score'];
                                $rkCls = $lr['rank'] === 1 ? 'gold' : ($lr['failed'] > 0 ? 'warn' : '');
                                ?>
                                <tr>
                                    <td><b><?= esc($lr['line']) ?></b></td>
                                    <td style="color:#15803d"><?= (int) $lr['passed'] ?></td>
                                    <td style="color:<?= $lr['failed'] > 0 ? '#dc2626' : '#94a3b8' ?>"><?= (int) $lr['failed'] ?></td>
                                    <td><?= (int) $lr['tested'] ?></td>
                                    <td>
                                        <?php if ($sc === null): ?>
                                            <span style="color:#94a3b8">No tests</span>
                                        <?php else: ?>
                                            <div style="display:flex;align-items:center;gap:6px">
                                                <div class="bd-track" style="width:70px;height:7px"><div class="bd-fill" style="width:<?= $sc ?>%;background:<?= $sc >= 95 ? '#16a34a' : ($sc >= 85 ? '#f59e0b' : '#dc2626') ?>"></div></div>
                                                <span><?= $sc ?>%</span>
                                            </div>
                                        <?php endif ?>
                                    </td>
                                    <td><?= $lr['rank'] !== null ? '<span class="bd-rank-b ' . $rkCls . '">#' . (int) $lr['rank'] . '</span>' : '<span style="color:#cbd5e1">&mdash;</span>' ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>
