<?php
/**
 * Whole Shoe Physical Test — Monthly Summary (uniform executive UI matching Shoe Bonding & Materials).
 * Data: $wsAudit = ['year', 'periodText', 'monthSpanText', 'totalSamples', 'ratedTotal', 'passTotal', 'tolTotal', 'failTotal', 'pendTotal', 'passPct', 'tolPct', 'failPct', 'methodCount', 'monthly', 'methods', 'silhouettes', 'retestMethods']
 */
$wa          = $wsAudit ?? [];
$year        = (int) ($wa['year'] ?? date('Y'));
$periodText  = (string) ($wa['periodText'] ?? '');
$spanText    = (string) ($wa['monthSpanText'] ?? '');
$totSamp     = (int) ($wa['totalSamples'] ?? 0);
$ratedTot    = (int) ($wa['ratedTotal'] ?? 0);
$passTot     = (int) ($wa['passTotal'] ?? 0);
$tolTot      = (int) ($wa['tolTotal'] ?? 0);
$failTot     = (int) ($wa['failTotal'] ?? 0);
$pendTot     = (int) ($wa['pendTotal'] ?? 0);
$passPct     = (float) ($wa['passPct'] ?? 0);
$tolPct      = (float) ($wa['tolPct'] ?? 0);
$failPct     = (float) ($wa['failPct'] ?? 0);
$monthly     = $wa['monthly'] ?? [];
$methods     = $wa['methods'] ?? [];
$silhouettes = $wa['silhouettes'] ?? [];
$retestM     = $wa['retestMethods'] ?? [];
$wsRecords   = $wa['records'] ?? [];
?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/analytics-wholeshoe_audit-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/analytics-wholeshoe_audit-1.css') ?>">

<?php if (! in_array(($view ?? 'analytics'), ['breakdown', 'monthfail'], true)): ?>
<div id="ms-yearly-pane" hidden>
    <?php
    $ypSubtitle   = 'Whole-year whole shoe test status distribution (PASS, FAILED, TOLERABLE) across all 12 months — click any month title to open its breakdown';
    $ypBackLabel  = 'Back to Monthly Summary';
    $ypMonthQuery = 'tab=wholeshoe&view=analytics&group=month';
    include __DIR__ . '/bonding_yearly_pies.php';
    ?>
</div>
<div id="ms-monthly-pane">
<div class="wsa-wrap">
    <?php if ($ratedTot > 0 && function_exists('anSummary')): ?>
        <?= anSummary(
            number_format($passPct, 1) . '% of whole shoes passed their tests' . ($periodText !== '' ? ' (' . $periodText . ')' : '') . '.',
            number_format($totSamp) . ' shoes were tested: ' . number_format($passTot) . ' passed, ' . number_format($tolTot) . ' were borderline (tolerable) and ' . number_format($failTot) . ' failed.'
                . ($pendTot > 0 ? ' ' . number_format($pendTot) . ' are still waiting for a result.' : '')
                . ' A pass rate of 95% or more is good; below 85% needs action.',
            anTone($passPct)
        ) ?>
    <?php endif ?>

    <!-- 4 Top-Gradient KPI Cards: Total Tested | Passed | Tolerable | Failed -->
    <div class="wsa-kpis">
        <div class="wsa-kpi k-tot">
            <div class="wsa-kpi-top">
                <span class="wsa-kpi-lbl">Total Tested</span>
                <span class="wsa-kpi-ico">&#931;</span>
            </div>
            <div class="wsa-kpi-valrow">
                <span class="wsa-kpi-val"><?= number_format($totSamp) ?></span>
                <?php if ($pendTot > 0): ?><span class="wsa-kpi-pill"><?= number_format($pendTot) ?> pending</span><?php endif ?>
            </div>
            <div class="wsa-kpi-bar"><i style="width:<?= $totSamp > 0 ? min(100, round($ratedTot / $totSamp * 100, 1)) : 0 ?>%;background:#3b82f6"></i></div>
            <span class="wsa-kpi-sub"><?= number_format($ratedTot) ?> rated &middot; all whole shoe samples in this period</span>
        </div>

        <div class="wsa-kpi k-pass">
            <div class="wsa-kpi-top">
                <span class="wsa-kpi-lbl">Passed</span>
                <span class="wsa-kpi-ico">&#10003;</span>
            </div>
            <div class="wsa-kpi-valrow">
                <span class="wsa-kpi-val"><?= number_format($passTot) ?></span>
                <?php if ($ratedTot > 0): ?><span class="wsa-kpi-pill"><?= number_format($passPct, 1) ?>%</span><?php endif ?>
            </div>
            <div class="wsa-kpi-bar"><i style="width:<?= min(100, $passPct) ?>%;background:#10b981"></i></div>
            <span class="wsa-kpi-sub">Meets VF / Vans physical test standard</span>
        </div>

        <div class="wsa-kpi k-tol">
            <div class="wsa-kpi-top">
                <span class="wsa-kpi-lbl">Tolerable</span>
                <span class="wsa-kpi-ico">&#9888;</span>
            </div>
            <div class="wsa-kpi-valrow">
                <span class="wsa-kpi-val"><?= number_format($tolTot) ?></span>
                <?php if ($ratedTot > 0): ?><span class="wsa-kpi-pill"><?= number_format($tolPct, 1) ?>%</span><?php endif ?>
            </div>
            <div class="wsa-kpi-bar"><i style="width:<?= min(100, max($tolTot > 0 ? 3 : 0, $tolPct)) ?>%;background:#f59e0b"></i></div>
            <span class="wsa-kpi-sub">Within acceptable tolerance band</span>
        </div>

        <div class="wsa-kpi k-fail">
            <div class="wsa-kpi-top">
                <span class="wsa-kpi-lbl">Failed</span>
                <span class="wsa-kpi-ico">&#10005;</span>
            </div>
            <div class="wsa-kpi-valrow">
                <span class="wsa-kpi-val"><?= number_format($failTot) ?></span>
                <?php if ($ratedTot > 0): ?><span class="wsa-kpi-pill"><?= number_format($failPct, 1) ?>%</span><?php endif ?>
            </div>
            <div class="wsa-kpi-bar"><i style="width:<?= min(100, max($failTot > 0 ? 3 : 0, $failPct)) ?>%;background:#ef4444"></i></div>
            <span class="wsa-kpi-sub">Non-conforming &middot; requires corrective action</span>
        </div>
    </div>

    <!-- Row 1: Summary Donut + Dark Navy Monthly Pass/Tolerable/Fail Trend -->
    <?php
    // Left donut card: latest rated month or overall rated summary when current month has no remarks yet
    $curMonthNo = (int) date('n');
    $curMo = null;
    foreach ($monthly as $moItem) {
        if ((int) $moItem['monthNo'] === $curMonthNo && ((int) $moItem['pass'] + (int) $moItem['tol'] + (int) $moItem['fail']) > 0) {
            $curMo = $moItem;
            break;
        }
    }
    if ($curMo === null && count($monthly) === 1) {
        $curMo = $monthly[0];
    }
    if ($curMo !== null) {
        $dnTitle   = $curMo['full'] . ' Summary';
        $dnSub     = 'Pass, Tolerable, and Fail share for ' . $curMo['full'] . ' ' . $year;
        $dnPass    = (int) $curMo['pass'];
        $dnTol     = (int) $curMo['tol'];
        $dnFail    = (int) $curMo['fail'];
        $dnRated   = $dnPass + $dnTol + $dnFail;
        $dnPassPct = $dnRated > 0 ? round($dnPass / $dnRated * 100, 1) : 0.0;
        $dnTolPct  = $dnRated > 0 ? round($dnTol / $dnRated * 100, 1) : 0.0;
        $dnFailPct = $dnRated > 0 ? round($dnFail / $dnRated * 100, 1) : 0.0;
    } else {
        $dnTitle   = 'Overall Summary';
        $dnSub     = 'Pass, Tolerable, and Fail share across ' . ($periodText ?: $year);
        $dnPass    = $passTot;
        $dnTol     = $tolTot;
        $dnFail    = $failTot;
        $dnRated   = $ratedTot;
        $dnPassPct = $passPct;
        $dnTolPct  = $tolPct;
        $dnFailPct = $failPct;
    }
    ?>
    <div class="wsa-main-grid">
        <!-- Left: Summary Donut Card -->
        <div class="wsa-card">
            <div class="wsa-card-hd">
                <div>
                    <h3><?= esc($dnTitle) ?></h3>
                    <p><?= esc($dnSub) ?></p>
                </div>
                <span class="wsa-rated-pill"><?= number_format($dnRated) ?> Rated</span>
            </div>
            <div class="wsa-card-bd">
                <?php
                $dR = 72; $dC = 2 * M_PI * $dR; $dOff = 0;
                $dSvg = '<svg viewBox="0 0 190 190" width="188" height="188" style="display:block;margin:2px auto;font-family:Inter,system-ui,sans-serif">';
                $dSvg .= '<circle cx="95" cy="95" r="' . $dR . '" fill="none" stroke="#e2e8f0" stroke-width="24"/>';
                if ($dnRated > 0) {
                    foreach ([['Passed', $dnPass, '#10b981'], ['Tolerable', $dnTol, '#f59e0b'], ['Failed', $dnFail, '#ef4444']] as [$nm, $val, $col]) {
                        if ($val <= 0) { continue; }
                        $len = ($val / $dnRated) * $dC;
                        $dSvg .= '<circle cx="95" cy="95" r="' . $dR . '" fill="none" stroke="' . $col . '" stroke-width="24" stroke-dasharray="' . round($len, 2) . ' ' . round($dC - $len, 2) . '" stroke-dashoffset="' . round(-$dOff, 2) . '" transform="rotate(-90 95 95)"><title>' . $nm . ': ' . number_format($val) . ' (' . round($val / $dnRated * 100, 1) . '%)</title></circle>';
                        $dOff += $len;
                    }
                }
                $dSvg .= '<text x="95" y="92" text-anchor="middle" font-size="25" font-weight="900" fill="currentColor">' . ($dnRated > 0 ? number_format($dnPassPct, 1) . '%' : '—') . '</text>';
                $dSvg .= '<text x="95" y="111" text-anchor="middle" font-size="10.5" font-weight="800" letter-spacing=".06em" fill="#64748b">PASS RATE</text>';
                $dSvg .= '</svg>';
                echo $dSvg;
                ?>
                <div class="wsa-share-list">
                    <?php foreach ([
                        ['Passed',    $dnPass, $dnPassPct, '#10b981'],
                        ['Tolerable', $dnTol,  $dnTolPct,  '#f59e0b'],
                        ['Failed',    $dnFail, $dnFailPct, '#ef4444'],
                    ] as [$nm, $val, $pctV, $col]): ?>
                    <div class="wsa-share-item">
                        <div class="wsa-share-top">
                            <span class="nm"><i style="background:<?= $col ?>"></i><?= $nm ?></span>
                            <span><?= number_format($val) ?> <span style="color:#64748b;font-weight:700">(<?= number_format($pctV, 1) ?>%)</span></span>
                        </div>
                        <div class="wsa-share-track"><span style="width:<?= min(100, max($val > 0 ? 2 : 0, $pctV)) ?>%;background:<?= $col ?>"></span></div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <!-- Right: Dark Navy Monthly Pass / Tolerable / Fail Trend -->
        <div class="wsa-dark-card">
            <div class="wsa-dark-hd">
                <div>
                    <h3>Monthly Pass / Tolerable / Fail Trend</h3>
                    <p>Stacked monthly whole shoe testing volume with Pass Rate (%) trend overlay</p>
                </div>
                <div class="wsa-leg-dk">
                    <span><i style="background:#10b981"></i>Passed</span>
                    <span><i style="background:#f59e0b"></i>Tolerable</span>
                    <span><i style="background:#ef4444"></i>Failed</span>
                    <span><i style="background:#94a3b8"></i>Pending</span>
                    <span><i class="ln"></i>Pass Rate (%)</span>
                </div>
            </div>
            <?php if (! $monthly): ?>
                <div style="padding:56px 16px;text-align:center;color:#94a3b8;font-weight:600">No monthly whole shoe samples found for the selected period.</div>
            <?php else:
                $W1 = 860; $H1 = 330; $L1 = 46; $R1 = 52; $T1 = 28; $B1 = 38;
                $pw1 = $W1 - $L1 - $R1; $ph1 = $H1 - $T1 - $B1;
                $maxV1 = 20;
                $minRate1 = 100.0;
                foreach ($monthly as $mo) {
                    $maxV1 = max($maxV1, (int) $mo['total']);
                    $mr = (int) $mo['pass'] + (int) $mo['tol'] + (int) $mo['fail'];
                    if ($mr > 0) {
                        $minRate1 = min($minRate1, round((int) $mo['pass'] / $mr * 100, 1));
                    }
                }
                $maxV1 = (int) (ceil($maxV1 / 20) * 20);
                $rateFloor1 = max(0, (int) (floor(($minRate1 - 5) / 10) * 10));
                if ($rateFloor1 > 70) { $rateFloor1 = 70; }
                $rateSpan1 = max(10, 100 - $rateFloor1);
                $nM1   = count($monthly);
                $slot1 = $pw1 / max(1, $nM1);
                $bw1   = min(40, max(18, $slot1 * 0.54));

                $s1 = '<defs>'
                    . '<linearGradient id="wsaGP" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#34d399"/><stop offset="100%" stop-color="#059669"/></linearGradient>'
                    . '<linearGradient id="wsaGT" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#fbbf24"/><stop offset="100%" stop-color="#d97706"/></linearGradient>'
                    . '<linearGradient id="wsaGF" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f87171"/><stop offset="100%" stop-color="#dc2626"/></linearGradient>'
                    . '<linearGradient id="wsaGD" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#94a3b8"/><stop offset="100%" stop-color="#64748b"/></linearGradient>'
                    . '</defs>';

                for ($gi = 0; $gi <= 5; $gi++) {
                    $gy = $T1 + $ph1 - ($gi / 5) * $ph1;
                    $gv = round($gi / 5 * $maxV1);
                    $gr = round($rateFloor1 + ($gi / 5) * $rateSpan1);
                    $s1 .= '<line x1="' . $L1 . '" x2="' . ($W1 - $R1) . '" y1="' . round($gy, 1) . '" y2="' . round($gy, 1) . '" stroke="rgba(148,163,184,.18)" stroke-dasharray="4 4" stroke-width="1"/>';
                    $s1 .= '<text x="' . ($L1 - 8) . '" y="' . round($gy + 4, 1) . '" text-anchor="end" fill="#94a3b8" font-size="11" font-weight="600">' . $gv . '</text>';
                    $s1 .= '<text x="' . ($W1 - $R1 + 8) . '" y="' . round($gy + 4, 1) . '" fill="#7dd3fc" font-size="10.5" font-weight="700">' . $gr . '%</text>';
                }
                $s1 .= '<line x1="' . $L1 . '" x2="' . ($W1 - $R1) . '" y1="' . ($T1 + $ph1) . '" y2="' . ($T1 + $ph1) . '" stroke="rgba(148,163,184,.35)" stroke-width="1.2"/>';

                $linePts1 = [];
                $dotsSvg1 = '';
                foreach ($monthly as $idx => $mo) {
                    $cx   = $L1 + $slot1 * ($idx + 0.5);
                    $x0   = round($cx - $bw1 / 2, 1);
                    $curY = $T1 + $ph1;
                    foreach ([
                        ['pass', 'url(#wsaGP)', 'Passed'],
                        ['tol',  'url(#wsaGT)', 'Tolerable'],
                        ['fail', 'url(#wsaGF)', 'Failed'],
                        ['pend', 'url(#wsaGD)', 'Pending Remark'],
                    ] as [$k, $fill, $lbl]) {
                        $val = (int) ($mo[$k] ?? 0);
                        if ($val <= 0) { continue; }
                        $sh = ($val / $maxV1) * $ph1;
                        $curY -= $sh;
                        $s1 .= '<rect x="' . $x0 . '" y="' . round($curY, 1) . '" width="' . round($bw1, 1) . '" height="' . round(max(2, $sh), 1) . '" rx="2.5" fill="' . $fill . '"><title>' . esc($mo['full']) . ' · ' . $lbl . ': ' . $val . '</title></rect>';
                    }
                    $totM = (int) $mo['total'];
                    if ($totM > 0) {
                        $topY = $T1 + $ph1 - ($totM / $maxV1) * $ph1;
                        $mr = (int) $mo['pass'] + (int) $mo['tol'] + (int) $mo['fail'];
                        $totLblY = $topY - 7;
                        if ($mr > 0) {
                            $pr = round((int) $mo['pass'] / $mr * 100, 1);
                            $py = $T1 + $ph1 - (max(0, min($rateSpan1, $pr - $rateFloor1)) / $rateSpan1) * $ph1;
                            $linePts1[] = round($cx, 1) . ',' . round($py, 1);
                            if (abs($py - $totLblY) < 15) {
                                $totLblY = $topY + 13;
                            }
                            $lblY = (abs($py - $totLblY) < 18 && $py >= $totLblY - 6) ? ($py + 18) : ($py - 11);
                            $dotsSvg1 .= '<circle cx="' . round($cx, 1) . '" cy="' . round($py, 1) . '" r="4.2" fill="#0f172a" stroke="#38bdf8" stroke-width="2.4"><title>' . esc($mo['full']) . ' Pass Rate: ' . number_format($pr, 1) . '%</title></circle>';
                            $dotsSvg1 .= '<rect x="' . round($cx - 19, 1) . '" y="' . round($lblY - 10, 1) . '" width="38" height="13.5" rx="4" fill="rgba(15,23,42,.88)" stroke="rgba(56,189,248,.4)" stroke-width="0.7"/>';
                            $dotsSvg1 .= '<text x="' . round($cx, 1) . '" y="' . round($lblY, 1) . '" text-anchor="middle" fill="#7dd3fc" font-size="9.5" font-weight="800">' . number_format($pr, 1) . '%</text>';
                        }
                        $s1 .= '<text x="' . round($cx, 1) . '" y="' . round($totLblY, 1) . '" text-anchor="middle" fill="#f8fafc" font-size="11" font-weight="800" style="paint-order:stroke;stroke:#0f172a;stroke-width:3px">' . $totM . '</text>';
                    }
                    $s1 .= '<text x="' . round($cx, 1) . '" y="' . ($T1 + $ph1 + 20) . '" text-anchor="middle" fill="#cbd5e1" font-size="11.5" font-weight="700">' . esc($mo['short']) . '</text>';
                }
                if (count($linePts1) >= 2) {
                    $s1 .= '<polyline fill="none" stroke="#38bdf8" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round" points="' . implode(' ', $linePts1) . '"/>';
                }
                $s1 .= $dotsSvg1;
            ?>
                <svg viewBox="0 0 <?= $W1 ?> <?= $H1 ?>" width="100%" style="display:block;font-family:Inter,system-ui,sans-serif;overflow:visible"><?= $s1 ?></svg>
            <?php endif ?>
        </div>
    </div>

    <!-- Row 2: Whole Shoe Test Results Matrix (uniform with Shoe Bonding Matrix Card) -->
    <?php
    $wsMethodCols = ['bonding', 'flexing', 'aging', 'hydrolysis', 'uv_light', 'washing'];
    $wsCellClassify = static function (string $raw): array {
        $v = trim($raw);
        if ($v === '') {
            return ['z', 'c-z', '&ndash;'];
        }
        $u = strtoupper($v);
        if ($u === 'P' || $u === 'PASS') {
            return ['p', 'c-pass', '<span>P</span>'];
        }
        if ($u === 'T' || str_starts_with($u, 'TOL')) {
            return ['t', 'c-tol', '<span>' . esc($u === 'T' ? 'T' : $v) . '</span>'];
        }
        if ($u === 'F' || $u === 'FAIL' || $u === 'FAILED') {
            return ['f', 'c-fail', '<span>F</span>'];
        }
        if (str_starts_with($u, 'F') && (str_contains($u, 'RETEST') || str_contains($u, 'PASS'))) {
            return ['r', 'c-ret', '<span title="' . esc($v, 'attr') . '">R-P</span>'];
        }
        $short = str_starts_with($u, 'NOT REQUIRED') ? 'N/R' : (strlen($v) > 12 ? substr($v, 0, 11) . '…' : $v);
        return ['n', 'c-note', '<b title="' . esc($v, 'attr') . '">' . esc($short) . '</b>'];
    };

    $sumPass = array_fill_keys($wsMethodCols, 0);
    $sumFail = array_fill_keys($wsMethodCols, 0);
    $sumEval = array_fill_keys($wsMethodCols, 0);
    foreach ($wsRecords as $rec) {
        foreach ($wsMethodCols as $mc) {
            [$code] = $wsCellClassify($rec[$mc] ?? '');
            if ($code === 'p') {
                $sumPass[$mc]++;
                $sumEval[$mc]++;
            } elseif ($code === 't') {
                $sumEval[$mc]++;
            } elseif ($code === 'f' || $code === 'r') {
                $sumFail[$mc]++;
                $sumEval[$mc]++;
            }
        }
    }
    $grandPassM = array_sum($sumPass);
    $grandFailM = array_sum($sumFail);
    $grandEvalM = array_sum($sumEval);
    ?>
    <div class="mx-card" id="wsa-records-card">
        <div class="mx-head">
            <div class="mx-head-l">
                <h3>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                    WHOLE SHOE TEST RESULTS MATRIX &mdash; <?= esc(strtoupper($periodText ?: (string) $year)) ?>
                </h3>
                <p id="wsa-tbl-count">Total Samples: <?= number_format(count($wsRecords)) ?> &middot; Failed: <?= number_format($failTot) ?> of <?= number_format($ratedTot) ?> Rated (<?= number_format($failPct, 1) ?>%)</p>
            </div>
            <div class="mx-head-r">
                <div class="mx-legend">
                    <span><i style="background:#10b981"></i>Pass (P)</span>
                    <span><i style="background:#f59e0b"></i>Tolerable (T)</span>
                    <span><i style="background:#dc2626"></i>Failed (F)</span>
                    <span><i style="background:#ea580c"></i>Retest Pass</span>
                    <span><i style="background:#cbd5e1"></i>N/R &middot; Note</span>
                </div>
                <div class="wsa-mx-tools">
                    <input type="search" id="wsa-tbl-q" class="wsa-mx-search" placeholder="Search Style, MTL#, Lab NO...">
                    <?php
                    $calActive = (($from ?? '') !== '' || ($to ?? '') !== '');
                    $calLabel  = $calActive ? ($periodText ?: (($from ?: 'Start') . ' - ' . ($to ?: 'Present'))) : 'All Dates (' . ($periodText ?: $year) . ')';
                    ?>
                    <button type="button" id="wsa-cal-trigger" class="wsa-cal-chip" title="Change date range using the top-right Calendar Filter">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <span><?= esc($calLabel) ?></span>
                    </button>
                    <?php if ($calActive): ?>
                        <a href="<?= esc(site_url('analytics') . '?tab=wholeshoe&view=analytics&group=month&all=1', 'attr') ?>" class="wsa-cal-clr" title="Reset calendar filter to show all dates">&times; Clear</a>
                    <?php endif ?>
                    <div class="bld-toggle" role="tablist" aria-label="Filter by status">
                        <button type="button" class="bld-btn on" data-st="all">All <span class="bld-pill zero"><?= number_format(count($wsRecords)) ?></span></button>
                        <button type="button" class="bld-btn" data-st="fail">Failed <span class="bld-pill<?= $failTot === 0 ? ' zero' : '' ?>"><?= number_format($failTot) ?></span></button>
                        <button type="button" class="bld-btn" data-st="tol">Tolerable <span class="bld-pill zero"><?= number_format($tolTot) ?></span></button>
                        <button type="button" class="bld-btn" data-st="pass">Passed <span class="bld-pill zero"><?= number_format($passTot) ?></span></button>
                        <?php if ($pendTot > 0): ?>
                        <button type="button" class="bld-btn" data-st="pend">Pending <span class="bld-pill zero"><?= number_format($pendTot) ?></span></button>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
        <?php if (! $wsRecords): ?>
            <div class="wsa-empty">
                No whole shoe test records found for <strong><?= esc($periodText ?: (string) $year) ?></strong>.
                <?php if (($from ?? '') !== '' || ($to ?? '') !== ''): ?>
                    <div style="margin-top:10px">
                        <a href="<?= esc(site_url('analytics') . '?tab=wholeshoe&view=analytics&group=month&all=1', 'attr') ?>" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;background:#2563eb;color:#fff;font-size:12px;font-weight:700;text-decoration:none">Show All Dates</a>
                    </div>
                <?php endif ?>
            </div>
        <?php else: ?>
        <div class="wsa-mx-scroll">
            <table class="wsa-mx-tbl" id="wsa-rec-tbl">
                <thead>
                    <tr class="dh">
                        <th class="lc">Test Date</th>
                        <th class="lc">Style</th>
                        <th class="lc">MTL#</th>
                        <th class="lc div-r">Lab NO</th>
                        <th><span class="dh-d">Bonding</span><span class="dh-w">Peel</span></th>
                        <th><span class="dh-d">Flexing</span><span class="dh-w">Test</span></th>
                        <th><span class="dh-d">Aging</span><span class="dh-w">Test</span></th>
                        <th><span class="dh-d">Hydrolysis</span><span class="dh-w">Test</span></th>
                        <th><span class="dh-d">UV Light</span><span class="dh-w">Yellowing</span></th>
                        <th><span class="dh-d">Washing</span><span class="dh-w">Test</span></th>
                        <th class="tc">Overall<br>Result</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($wsRecords as $rec):
                    $searchHay = strtolower($rec['date'] . ' ' . $rec['monthName'] . ' ' . $rec['style'] . ' ' . $rec['mtl'] . ' ' . $rec['labNo'] . ' ' . $rec['bonding'] . ' ' . $rec['flexing'] . ' ' . $rec['aging'] . ' ' . $rec['hydrolysis'] . ' ' . $rec['uv_light'] . ' ' . $rec['washing']);
                    $cellData = [];
                    $codeList = [];
                    foreach ($wsMethodCols as $mc) {
                        $c = $wsCellClassify($rec[$mc] ?? '');
                        $cellData[$mc] = $c;
                        $codeList[] = $c[0];
                    }
                    $ovCls = match ($rec['status']) {
                        'fail' => 'ov-fail',
                        'pass' => 'ov-pass',
                        'tol'  => 'ov-tol',
                        default => 'ov-pend',
                    };
                    $ovLbl = match ($rec['status']) {
                        'fail' => 'FAILED',
                        'pass' => 'PASS',
                        'tol'  => 'TOL',
                        default => 'PENDING',
                    };
                ?>
                    <tr data-st="<?= esc($rec['status'], 'attr') ?>" data-mo="<?= (int) $rec['monthNo'] ?>" data-q="<?= esc($searchHay, 'attr') ?>" data-m="<?= esc(implode(',', $codeList), 'attr') ?>">
                        <td class="lc"><span class="wsa-date-tag"><?= $rec['date'] !== '' ? esc($rec['date']) : esc($rec['monthName']) ?></span></td>
                        <td class="lc wsa-style-txt"><?= $rec['style'] !== '' ? esc($rec['style']) : '&ndash;' ?></td>
                        <td class="lc wsa-mtl-txt"><?= $rec['mtl'] !== '' ? esc($rec['mtl']) : '&ndash;' ?></td>
                        <td class="lc div-r wsa-lab-txt"><?= $rec['labNo'] !== '' ? esc($rec['labNo']) : '&ndash;' ?></td>
                        <?php foreach ($wsMethodCols as $mc): ?>
                            <td class="<?= $cellData[$mc][1] ?>"><?= $cellData[$mc][2] ?></td>
                        <?php endforeach ?>
                        <td class="tc <?= $ovCls ?>"><?= $ovLbl ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
                <tfoot>
                    <tr class="sum-bt">
                        <td class="lc div-r" colspan="4">Passed Tests / Method</td>
                        <?php foreach ($wsMethodCols as $mi => $mc): ?>
                            <td data-sum-p="<?= $mi ?>" class="<?= $sumPass[$mc] === 0 ? 'z-sum' : '' ?>"><?= $sumPass[$mc] ?></td>
                        <?php endforeach ?>
                        <td class="tc" id="wsa-sum-p-tot"><?= $grandPassM ?></td>
                    </tr>
                    <tr class="sum-rd">
                        <td class="lc div-r" colspan="4">Failed Tests / Method (incl. Retest)</td>
                        <?php foreach ($wsMethodCols as $mi => $mc): ?>
                            <td data-sum-f="<?= $mi ?>" class="<?= $sumFail[$mc] === 0 ? 'z-sum' : '' ?>"><?= $sumFail[$mc] ?></td>
                        <?php endforeach ?>
                        <td class="tc" id="wsa-sum-f-tot"><?= $grandFailM ?></td>
                    </tr>
                    <tr class="sum-rt">
                        <td class="lc div-r" colspan="4">Total Evaluated / Method</td>
                        <?php foreach ($wsMethodCols as $mi => $mc): ?>
                            <td data-sum-t="<?= $mi ?>" class="<?= $sumEval[$mc] === 0 ? 'z-sum' : '' ?>"><?= $sumEval[$mc] ?></td>
                        <?php endforeach ?>
                        <td class="tc" id="wsa-sum-t-tot"><?= $grandEvalM ?></td>
                    </tr>
                    <tr class="sum-pct">
                        <td class="lc div-r" colspan="4">Method Failure Rate (%)</td>
                        <?php foreach ($wsMethodCols as $mi => $mc):
                            $ev = $sumEval[$mc]; $fl = $sumFail[$mc];
                            $pctS = $ev > 0 ? ($fl > 0 ? round($fl / $ev * 100, 1) . '%' : '0%') : '&ndash;';
                        ?>
                            <td data-sum-r="<?= $mi ?>" class="<?= $fl > 0 ? 'has-pct' : 'z-sum' ?>"><?= $pctS ?></td>
                        <?php endforeach ?>
                        <td class="tc" id="wsa-sum-r-tot"><?= $grandEvalM > 0 ? number_format($grandFailM / $grandEvalM * 100, 2) . '%' : '0.00%' ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="wsa-pg-bar" id="wsa-pg-bar">
            <span id="wsa-pg-info">Showing 1&ndash;<?= min(15, count($wsRecords)) ?> of <?= number_format(count($wsRecords)) ?> samples</span>
            <div class="wsa-pg-ctl">
                <label for="wsa-pg-size" style="font-size:11.5px;font-weight:700">Rows:</label>
                <select id="wsa-pg-size" aria-label="Rows per page">
                    <option value="15" selected>15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="0">All</option>
                </select>
                <div class="wsa-pg-btns" id="wsa-pg-btns"></div>
            </div>
        </div>
        <?php endif ?>
    </div>
</div>
</div>
<script src="<?= base_url('assets/js/views/analytics-wholeshoe_audit-1.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/views/analytics-wholeshoe_audit-1.js') ?>"></script>
<?php endif ?>

<?php if (($view ?? 'analytics') === 'monthfail'):
    $mfLabels  = $wa['methodLabels'] ?? [];
    $mfData    = $wa['methodMonthly'] ?? [];
    $audMeta   = $wa['auditMeta'] ?? ['trCount' => 0, 'labCount' => 0, 'blankRemarkCount' => 0, 'trPct' => 0.0, 'labPct' => 0.0];
    $mfMonths  = array_map(static fn ($r) => (int) $r['monthNo'], $monthly);
    sort($mfMonths);
    $mfShort   = static fn (int $mn): string => date('M', mktime(0, 0, 0, $mn, 1, 2000));
    $mfFull    = static fn (int $mn): string => date('F', mktime(0, 0, 0, $mn, 1, 2000));
    $mfClass   = static function (?float $p): string {
        if ($p === null) { return 'mf-nil'; }
        if ($p <= 0) { return 'mf-zero'; }
        if ($p < 5) { return 'mf-low'; }
        if ($p < 10) { return 'mf-mid'; }
        return 'mf-hi';
    };

    // Precompute audit summary metrics across all physical tests & months
    $colFail = array_fill_keys($mfMonths, 0);
    $colEval = array_fill_keys($mfMonths, 0);
    $rowSums = [];
    $gfAll = 0; $geAll = 0; $gRetAll = 0; $gOpenAll = 0;
    $worstMethod = null;
    foreach ($mfLabels as $key => $label) {
        $tf = 0; $te = 0; $tr = 0;
        foreach ($mfMonths as $mn) {
            $c  = $mfData[$key][$mn] ?? null;
            $ev = $c ? ($c['pass'] + $c['tol'] + $c['fail']) : 0;
            $fl = (int) ($c['fail'] ?? 0);
            $rt = (int) ($c['retest'] ?? 0);
            $tf += $fl; $te += $ev; $tr += $rt;
            $colFail[$mn] += $fl;
            $colEval[$mn] += $ev;
        }
        $po = $te > 0 ? round($tf / $te * 100, 1) : null;
        $rowSums[$key] = ['fail' => $tf, 'eval' => $te, 'retest' => $tr, 'pct' => $po];
        $gfAll += $tf; $geAll += $te; $gRetAll += $tr; $gOpenAll += max(0, $tf - $tr);
        if ($po !== null && ($worstMethod === null || $po > $worstMethod['pct'] || ($po === $worstMethod['pct'] && $tf > $worstMethod['fail']))) {
            $worstMethod = ['key' => $key, 'label' => $label, 'pct' => $po, 'fail' => $tf, 'eval' => $te];
        }
    }
    $peakMonth = null;
    foreach ($mfMonths as $mn) {
        if ($colEval[$mn] > 0) {
            $mp = round($colFail[$mn] / $colEval[$mn] * 100, 1);
            if ($peakMonth === null || $mp > $peakMonth['pct']) {
                $peakMonth = ['monthNo' => $mn, 'name' => $mfFull($mn), 'pct' => $mp, 'fail' => $colFail[$mn], 'eval' => $colEval[$mn]];
            }
        }
    }
    $overallFailPct = $geAll > 0 ? round($gfAll / $geAll * 100, 1) : 0.0;
    $overallPassPct = $geAll > 0 ? round(($geAll - $gfAll) / $geAll * 100, 1) : 100.0;
    $retestRecPct   = $gfAll > 0 ? (int) round($gRetAll / $gfAll * 100) : 0;

    // Build Non-Conforming & Retest Traceability Register (all samples with any F / F (RETEST - PASS) / FAILED remark)
    $ncRecords = [];
    $ncOpenCnt = 0; $ncRetCnt = 0;
    foreach ($wsRecords as $rec) {
        $failedItems = [];
        $hasUnresolved = false;
        $hasRetestPass = false;
        $mKeys = [];
        foreach ($mfLabels as $mCol => $mLbl) {
            $rv = strtoupper(trim((string) ($rec[$mCol] ?? '')));
            if ($rv === '') { continue; }
            if ($rv === 'F' || $rv === 'FAIL' || $rv === 'FAILED') {
                $failedItems[] = ['key' => $mCol, 'label' => $mLbl, 'type' => 'open', 'raw' => $rec[$mCol]];
                $hasUnresolved = true;
                $mKeys[] = $mCol;
            } elseif (str_starts_with($rv, 'F') && (str_contains($rv, 'RETEST') || str_contains($rv, 'PASS'))) {
                $failedItems[] = ['key' => $mCol, 'label' => $mLbl, 'type' => 'retest', 'raw' => $rec[$mCol]];
                $hasRetestPass = true;
                $mKeys[] = $mCol;
            }
        }
        if (! $failedItems && $rec['status'] !== 'fail') {
            continue;
        }
        $disp = $hasUnresolved || $rec['status'] === 'fail' ? 'open' : 'retest';
        if ($disp === 'open') { $ncOpenCnt++; } else { $ncRetCnt++; }
        $ncRecords[] = $rec + ['failedItems' => $failedItems, 'disp' => $disp, 'mKeys' => array_unique($mKeys)];
    }
?>
<div class="wsa-wrap">
    <?php if ($geAll > 0 && function_exists('anSummary')): ?>
        <?= anSummary(
            number_format($overallFailPct, 1) . '% overall test failure rate across ' . number_format($geAll) . ' physical evaluations (' . ($periodText ?: $year) . ').',
            number_format($gfAll) . ' initial failures recorded'
                . ($worstMethod && $worstMethod['fail'] > 0 ? ' — highest in ' . $worstMethod['label'] . ' (' . number_format($worstMethod['pct'], 1) . '%, ' . $worstMethod['fail'] . '/' . $worstMethod['eval'] . ')' : '')
                . ($peakMonth && $peakMonth['fail'] > 0 ? ' and peaking in ' . $peakMonth['name'] . ' (' . number_format($peakMonth['pct'], 1) . '%)' : '')
                . '. ' . number_format($gRetAll) . ' closed via retest (' . $retestRecPct . '% recovery), ' . number_format($gOpenAll) . ' unresolved. Click any failing cell in the heatmap to drill into the audit register below.',
            $overallFailPct <= 2.0 ? 'ok' : ($overallFailPct <= 5.0 ? 'warn' : 'bad')
        ) ?>
    <?php endif ?>

    <!-- 4 Audit KPI Cards -->
    <div class="wsa-kpis">
        <div class="wsa-kpi k-fail">
            <div class="wsa-kpi-top">
                <span class="wsa-kpi-lbl">Overall Test Failure Rate</span>
                <span class="wsa-kpi-ico">&#10005;</span>
            </div>
            <div class="wsa-kpi-valrow">
                <span class="wsa-kpi-val"><?= number_format($overallFailPct, 1) ?>%</span>
                <span class="wsa-kpi-pill"><?= number_format($gfAll) ?> fails</span>
            </div>
            <div class="wsa-kpi-bar"><i style="width:<?= min(100, max($gfAll > 0 ? 4 : 0, $overallFailPct * 4)) ?>%;background:#ef4444"></i></div>
            <span class="wsa-kpi-sub"><?= number_format($gfAll) ?> failed of <?= number_format($geAll) ?> evaluated tests</span>
        </div>

        <div class="wsa-kpi k-tol">
            <div class="wsa-kpi-top">
                <span class="wsa-kpi-lbl">Highest-Risk Method</span>
                <span class="wsa-kpi-ico">&#9888;</span>
            </div>
            <div class="wsa-kpi-valrow">
                <span class="wsa-kpi-val" style="font-size:23px"><?= esc($worstMethod && $worstMethod['fail'] > 0 ? $worstMethod['label'] : 'None') ?></span>
                <?php if ($worstMethod && $worstMethod['fail'] > 0): ?><span class="wsa-kpi-pill"><?= number_format($worstMethod['pct'], 1) ?>%</span><?php endif ?>
            </div>
            <div class="wsa-kpi-bar"><i style="width:<?= $worstMethod ? min(100, $worstMethod['pct'] * 4) : 0 ?>%;background:#f59e0b"></i></div>
            <span class="wsa-kpi-sub"><?= $worstMethod && $worstMethod['fail'] > 0 ? $worstMethod['fail'] . ' of ' . $worstMethod['eval'] . ' evaluated tests failed' : 'Zero failures across all methods' ?></span>
        </div>

        <div class="wsa-kpi k-tot">
            <div class="wsa-kpi-top">
                <span class="wsa-kpi-lbl">Peak Failure Month</span>
                <span class="wsa-kpi-ico"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>
            </div>
            <div class="wsa-kpi-valrow">
                <span class="wsa-kpi-val" style="font-size:23px"><?= esc($peakMonth && $peakMonth['fail'] > 0 ? $peakMonth['name'] : 'None') ?></span>
                <?php if ($peakMonth && $peakMonth['fail'] > 0): ?><span class="wsa-kpi-pill" style="background:#fee2e2;color:#b91c1c"><?= number_format($peakMonth['pct'], 1) ?>%</span><?php endif ?>
            </div>
            <div class="wsa-kpi-bar"><i style="width:<?= $peakMonth ? min(100, $peakMonth['pct'] * 4) : 0 ?>%;background:#2563eb"></i></div>
            <span class="wsa-kpi-sub"><?= $peakMonth && $peakMonth['fail'] > 0 ? $peakMonth['fail'] . ' fails out of ' . $peakMonth['eval'] . ' tests in ' . $peakMonth['name'] : 'No monthly failure spikes' ?></span>
        </div>

        <div class="wsa-kpi k-pass">
            <div class="wsa-kpi-top">
                <span class="wsa-kpi-lbl">Retest Closure &amp; TR Traceability</span>
                <span class="wsa-kpi-ico">&#10003;</span>
            </div>
            <div class="wsa-kpi-valrow">
                <span class="wsa-kpi-val"><?= $retestRecPct ?>%</span>
                <span class="wsa-kpi-pill"><?= number_format($gRetAll) ?> closed</span>
            </div>
            <div class="wsa-kpi-bar"><i style="width:<?= min(100, $retestRecPct) ?>%;background:#10b981"></i></div>
            <span class="wsa-kpi-sub"><?= number_format($gOpenAll) ?> open &middot; TR# <?= number_format((float) $audMeta['trPct'], 1) ?>% &middot; Lab# <?= number_format((float) $audMeta['labPct'], 1) ?>%</span>
        </div>
    </div>

    <!-- Hero Card: Interactive Monthly Failure % Heatmap by Test Method -->
    <div class="wsa-card">
        <div class="wsa-navy-hd">
            <div>
                <h3>Failure % of Each Test per Month &mdash; <?= esc($periodText ?: (string) $year) ?></h3>
                <p>Failed &divide; evaluated (pass + tolerable + failed) per test method. Click any failing cell to filter the Audit Traceability Register below.</p>
            </div>
            <div class="wsa-mf-scale">
                <span><i style="background:#10b981"></i>0% Zero</span>
                <span><i style="background:#facc15"></i>&lt;5% Low</span>
                <span><i style="background:#fb923c"></i>5&ndash;9.9% Watch</span>
                <span><i style="background:#ef4444"></i>&ge;10% Spike</span>
            </div>
        </div>
        <div style="overflow-x:auto">
        <?php if (! $mfLabels || ! $mfMonths): ?>
            <div class="wsa-empty">No evaluated tests found for the selected period.</div>
        <?php else: ?>
            <table class="wsa-mf-tbl" id="wsa-mf-tbl">
                <thead>
                    <tr>
                        <th class="l">Test Method</th>
                        <?php foreach ($mfMonths as $mn): ?>
                            <th><?= esc($mfShort($mn)) ?></th>
                        <?php endforeach ?>
                        <th class="ov">Overall</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mfLabels as $key => $label):
                    $rs = $rowSums[$key];
                ?>
                    <tr>
                        <td class="l"><?= esc($label) ?></td>
                        <?php foreach ($mfMonths as $mn):
                            $c  = $mfData[$key][$mn] ?? null;
                            $ev = $c ? ($c['pass'] + $c['tol'] + $c['fail']) : 0;
                            $fl = (int) ($c['fail'] ?? 0);
                            $rt = (int) ($c['retest'] ?? 0);
                            $pc = $ev > 0 ? round($fl / $ev * 100, 1) : null;
                            $tip = $ev > 0
                                ? ($label . ' · ' . $mfFull($mn) . ': ' . $fl . ' failed (' . $rt . ' retest-pass, ' . max(0, $fl - $rt) . ' unresolved) / ' . $ev . ' evaluated')
                                : ($label . ' · ' . $mfFull($mn) . ': No evaluated tests');
                        ?>
                            <td class="<?= $mfClass($pc) ?><?= $fl > 0 ? ' mf-click' : '' ?>"
                                title="<?= esc($tip, 'attr') ?>"
                                <?php if ($fl > 0): ?>data-mf-method="<?= esc($key, 'attr') ?>" data-mf-month="<?= $mn ?>" data-mf-label="<?= esc($label . ' — ' . $mfFull($mn), 'attr') ?>"<?php endif ?>>
                                <?php if ($pc === null): ?>&ndash;<?php else: ?>
                                    <span class="wsa-mf-pct"><?= number_format($pc, 1) ?>%</span>
                                    <span class="wsa-mf-cnt"><?= $fl ?>/<?= $ev ?></span>
                                <?php endif ?>
                            </td>
                        <?php endforeach ?>
                        <td class="ov <?= $mfClass($rs['pct']) ?><?= $rs['fail'] > 0 ? ' mf-click' : '' ?>"
                            title="<?= esc($label . ' Overall: ' . $rs['fail'] . ' failed (' . $rs['retest'] . ' retest-pass) / ' . $rs['eval'] . ' evaluated', 'attr') ?>"
                            <?php if ($rs['fail'] > 0): ?>data-mf-method="<?= esc($key, 'attr') ?>" data-mf-month="" data-mf-label="<?= esc($label . ' — All Months', 'attr') ?>"<?php endif ?>>
                            <?php if ($rs['pct'] === null): ?>&ndash;<?php else: ?>
                                <span class="wsa-mf-pct"><?= number_format($rs['pct'], 1) ?>%</span>
                                <span class="wsa-mf-cnt"><?= $rs['fail'] ?>/<?= $rs['eval'] ?></span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="l">All Tests</td>
                        <?php foreach ($mfMonths as $mn):
                            $pc = $colEval[$mn] > 0 ? round($colFail[$mn] / $colEval[$mn] * 100, 1) : null;
                        ?>
                            <td class="<?= $colFail[$mn] > 0 ? 'mf-click' : '' ?>"
                                <?php if ($colFail[$mn] > 0): ?>data-mf-method="" data-mf-month="<?= $mn ?>" data-mf-label="<?= esc('All Tests — ' . $mfFull($mn), 'attr') ?>"<?php endif ?>>
                                <?php if ($pc === null): ?>&ndash;<?php else: ?>
                                    <span class="wsa-mf-pct"><?= number_format($pc, 1) ?>%</span>
                                    <span class="wsa-mf-cnt"><?= $colFail[$mn] ?>/<?= $colEval[$mn] ?></span>
                                <?php endif ?>
                            </td>
                        <?php endforeach ?>
                        <td class="ov">
                            <?php if ($geAll > 0): ?>
                                <span class="wsa-mf-pct"><?= number_format($overallFailPct, 1) ?>%</span>
                                <span class="wsa-mf-cnt"><?= $gfAll ?>/<?= $geAll ?></span>
                            <?php else: ?>&ndash;<?php endif ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        <?php endif ?>
        </div>
    </div>

</div>
<script src="<?= base_url('assets/js/views/analytics-wholeshoe_audit-2.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/views/analytics-wholeshoe_audit-2.js') ?>"></script>
<?php endif ?>

<?php if (($view ?? 'analytics') === 'breakdown'): ?>
<div class="wsa-wrap">
    <!-- Row 1 (Breakdown Tab): Test Method Distribution & Retest Recovery -->
    <div class="wsa-grid">
        <!-- Test Method Pass / Tolerable / Fail Distribution (%) -->
        <div class="wsa-card">
            <div class="wsa-navy-hd">
                <div>
                    <h3>Test Method Pass / Tolerable / Fail Distribution (%)</h3>
                    <p>Pass, tolerable, and failure share across all whole shoe physical test methods</p>
                </div>
                <div class="wsa-leg">
                    <span><i style="background:#10b981"></i>Pass</span>
                    <span><i style="background:#f59e0b"></i>Tolerable</span>
                    <span><i style="background:#ef4444"></i>Fail</span>
                </div>
            </div>
            <div class="wsa-card-bd">
            <?php if (! $methods): ?>
                <div class="wsa-empty">No evaluated test methods found for the selected period.</div>
            <?php else:
                $W2 = 620; $H2 = 290; $L2 = 148; $R2 = 58; $T2 = 12; $B2 = 38;
                $pw2 = $W2 - $L2 - $R2; $ph2 = $H2 - $T2 - $B2;
                $nMe = count($methods);
                $rowH2 = $ph2 / max(1, $nMe);
                $bh2 = min(22, max(13, $rowH2 * 0.58));
                $s2 = '';
                for ($gi = 0; $gi <= 5; $gi++) {
                    $gx = $L2 + ($gi / 5) * $pw2;
                    $s2 .= '<line class="wsa-gridline" x1="' . round($gx, 1) . '" x2="' . round($gx, 1) . '" y1="' . $T2 . '" y2="' . ($T2 + $ph2) . '" stroke="#e2e8f0" stroke-dasharray="3 3" stroke-width="1"/>';
                    $s2 .= '<text class="wsa-ax" x="' . round($gx, 1) . '" y="' . ($T2 + $ph2 + 16) . '" text-anchor="middle" fill="#64748b" font-size="10.5" font-weight="600">' . ($gi * 20) . '</text>';
                }
                $s2 .= '<text class="wsa-ax" x="' . round($L2 + $pw2 / 2, 1) . '" y="' . ($H2 - 4) . '" text-anchor="middle" fill="#475569" font-size="11" font-weight="700">Percentage of Evaluated Tests (%)</text>';
                foreach ($methods as $mi => $me) {
                    $cy = $T2 + $rowH2 * ($mi + 0.5);
                    $y0 = round($cy - $bh2 / 2, 1);
                    $pPct = (float) $me['passPct'];
                    $tPct = (float) $me['tolPct'];
                    $fPct = (float) $me['failPct'];
                    $s2 .= '<text class="wsa-lbl" x="' . ($L2 - 10) . '" y="' . round($cy + 4, 1) . '" text-anchor="end" fill="#1e293b" font-size="11.5" font-weight="700">' . esc($me['label']) . ' (n=' . (int) $me['n'] . ')</text>';
                    $curX = (float) $L2;
                    $wP = ($pPct / 100) * $pw2;
                    $wT = ($tPct / 100) * $pw2;
                    $wF = max(0, $L2 + $pw2 - $curX - $wP - $wT);
                    if ($wP > 0) {
                        $s2 .= '<rect x="' . round($curX, 1) . '" y="' . $y0 . '" width="' . round($wP, 1) . '" height="' . round($bh2, 1) . '" fill="#10b981" rx="2"><title>' . esc($me['label']) . ' · Pass: ' . (int) $me['pass'] . ' (' . number_format($pPct, 1) . '%)</title></rect>';
                        if ($wP >= 48) {
                            $s2 .= '<text x="' . round($curX + $wP - 7, 1) . '" y="' . round($cy + 3.8, 1) . '" text-anchor="end" fill="#ffffff" font-size="10.5" font-weight="800">' . number_format($pPct, 1) . '%</text>';
                        }
                        $curX += $wP;
                    }
                    if ($wT > 0) {
                        $s2 .= '<rect x="' . round($curX, 1) . '" y="' . $y0 . '" width="' . round($wT, 1) . '" height="' . round($bh2, 1) . '" fill="#f59e0b"><title>' . esc($me['label']) . ' · Tolerable: ' . (int) $me['tol'] . ' (' . number_format($tPct, 1) . '%)</title></rect>';
                        $curX += $wT;
                    }
                    if ((int) $me['failTotal'] > 0 && $wF > 0) {
                        $s2 .= '<rect x="' . round($curX, 1) . '" y="' . $y0 . '" width="' . round(max(2.5, $wF), 1) . '" height="' . round($bh2, 1) . '" fill="#dc2626" rx="2"><title>' . esc($me['label']) . ' · Initial Fail: ' . (int) $me['failTotal'] . ' (' . number_format($fPct, 1) . '%)</title></rect>';
                        $s2 .= '<text x="' . ($L2 + $pw2 + 6) . '" y="' . round($cy + 3.8, 1) . '" text-anchor="start" fill="#dc2626" font-size="10.5" font-weight="800">F: ' . number_format($fPct, 1) . '%</text>';
                    }
                }
            ?>
                <svg class="wsa-svg" viewBox="0 0 <?= $W2 ?> <?= $H2 ?>"><?= $s2 ?></svg>
            <?php endif ?>
            </div>
        </div>

        <!-- Retest Recovery Rate Across Failing Test Methods -->
        <div class="wsa-card">
            <div class="wsa-navy-hd">
                <div>
                    <h3>Retest Recovery Rate Across Failing Test Methods</h3>
                    <p>Unresolved failures vs. specimens that passed on retest</p>
                </div>
                <div class="wsa-leg">
                    <span><i style="background:#ef4444"></i>Unresolved (F)</span>
                    <span><i style="background:#10b981"></i>Passed on Retest</span>
                </div>
            </div>
            <div class="wsa-card-bd">
            <?php if (! $retestM): ?>
                <div class="wsa-empty">No failing test methods recorded in this period.</div>
            <?php else:
                $W4 = 620; $H4 = 290; $L4 = 48; $R4 = 20; $T4 = 34; $B4 = 46;
                $pw4 = $W4 - $L4 - $R4; $ph4 = $H4 - $T4 - $B4;
                $maxR4 = 10;
                foreach ($retestM as $rm) {
                    $maxR4 = max($maxR4, (int) $rm['failTotal']);
                }
                $maxR4 = (int) (ceil($maxR4 / 10) * 10);
                $nRm   = count($retestM);
                $slot4 = $pw4 / max(1, $nRm);
                $bw4   = min(74, max(36, $slot4 * 0.46));
                $s4    = '';
                $ticks4 = min(6, max(4, (int) ($maxR4 / 10)));
                for ($gi = 0; $gi <= $ticks4; $gi++) {
                    $gv = round($gi / $ticks4 * $maxR4);
                    $gy = $T4 + $ph4 - ($gi / $ticks4 * $ph4);
                    $s4 .= '<line class="wsa-gridline" x1="' . $L4 . '" x2="' . ($W4 - $R4) . '" y1="' . round($gy, 1) . '" y2="' . round($gy, 1) . '" stroke="#e2e8f0" stroke-dasharray="3 3" stroke-width="1"/>';
                    $s4 .= '<text class="wsa-ax" x="' . ($L4 - 8) . '" y="' . round($gy + 4, 1) . '" text-anchor="end" fill="#64748b" font-size="10.5" font-weight="600">' . $gv . '</text>';
                }
                $s4 .= '<line class="wsa-axisline" x1="' . $L4 . '" x2="' . ($W4 - $R4) . '" y1="' . ($T4 + $ph4) . '" y2="' . ($T4 + $ph4) . '" stroke="#cbd5e1" stroke-width="1.2"/>';
                $s4 .= '<text class="wsa-ax" transform="translate(14,' . ($T4 + $ph4 / 2) . ') rotate(-90)" text-anchor="middle" fill="#475569" font-size="11" font-weight="700">Total Initial Failures</text>';
                foreach ($retestM as $ri => $rm) {
                    $cx = $L4 + $slot4 * ($ri + 0.5);
                    $x0 = round($cx - $bw4 / 2, 1);
                    $fPure = (int) $rm['failPure'];
                    $fRet  = (int) $rm['failRetest'];
                    $fTot  = (int) $rm['failTotal'];
                    $rec   = (int) $rm['recoveryPct'];
                    $curY  = $T4 + $ph4;
                    if ($fPure > 0) {
                        $hP = ($fPure / $maxR4) * $ph4;
                        $curY -= $hP;
                        $s4 .= '<rect x="' . $x0 . '" y="' . round($curY, 1) . '" width="' . round($bw4, 1) . '" height="' . round($hP, 1) . '" fill="#b91c1c" rx="2.5"><title>' . esc($rm['label']) . ' · Unresolved / Pure Fail: ' . $fPure . '</title></rect>';
                        if ($hP >= 14) {
                            $s4 .= '<text x="' . round($cx, 1) . '" y="' . round($curY + $hP / 2 + 4, 1) . '" text-anchor="middle" fill="#ffffff" font-size="11.5" font-weight="800">' . $fPure . '</text>';
                        }
                    }
                    if ($fRet > 0) {
                        $hR = ($fRet / $maxR4) * $ph4;
                        $curY -= $hR;
                        $s4 .= '<rect x="' . $x0 . '" y="' . round($curY, 1) . '" width="' . round($bw4, 1) . '" height="' . round($hR, 1) . '" fill="#10b981" rx="2.5"><title>' . esc($rm['label']) . ' · Passed on Retest: ' . $fRet . '</title></rect>';
                        if ($hR >= 14) {
                            $s4 .= '<text x="' . round($cx, 1) . '" y="' . round($curY + $hR / 2 + 4, 1) . '" text-anchor="middle" fill="#ffffff" font-size="11.5" font-weight="800">' . $fRet . '</text>';
                        }
                    }
                    $s4 .= '<text class="wsa-lbl" x="' . round($cx, 1) . '" y="' . round($curY - 17, 1) . '" text-anchor="middle" fill="' . ($rec > 0 ? '#047857' : '#64748b') . '" font-size="10.5" font-weight="800">' . $rec . '% Retest</text>';
                    $s4 .= '<text class="wsa-lbl" x="' . round($cx, 1) . '" y="' . round($curY - 5, 1) . '" text-anchor="middle" fill="' . ($rec > 0 ? '#047857' : '#64748b') . '" font-size="10.5" font-weight="800">Recovery</text>';
                    $shortLbl = str_replace(' Test', '', (string) $rm['label']);
                    $s4 .= '<text class="wsa-lbl" x="' . round($cx, 1) . '" y="' . ($T4 + $ph4 + 17) . '" text-anchor="middle" fill="#1e293b" font-size="11.5" font-weight="800">' . esc($shortLbl) . '</text>';
                    $s4 .= '<text class="wsa-ax" x="' . round($cx, 1) . '" y="' . ($T4 + $ph4 + 31) . '" text-anchor="middle" fill="#64748b" font-size="10.5" font-weight="600">(' . $fTot . ' Fail' . ($fTot === 1 ? '' : 's') . ')</text>';
                }
            ?>
                <svg class="wsa-svg" viewBox="0 0 <?= $W4 ?> <?= $H4 ?>"><?= $s4 ?></svg>
            <?php endif ?>
            </div>
        </div>
    </div>
</div>
<?php endif ?>
