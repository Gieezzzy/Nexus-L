<?php
/**
 * Shoe Bonding > Universal Tensile Machines Daily Productivity (included by analytics/index.php).
 * Data: $utm = ['machines', 'cell', 'dayTot', 'machDay', 'dayDetail', 'summary', 'unassigned', 'unBond', 'unPull']
 */
$mach      = $utm['machines'] ?? [];
$cell      = $utm['cell'] ?? [];
$dayTot    = $utm['dayTot'] ?? [];
$machDay   = $utm['machDay'] ?? [];
$dayDetail = $utm['dayDetail'] ?? [];
$sum       = $utm['summary'] ?? [];
$unAss     = (int) ($utm['unassigned'] ?? 0);
$total     = array_sum($dayTot);
$nDays     = count($dayTot);
$nMach     = count($mach);
$palette   = ['#2563eb', '#f97316', '#10b981', '#8b5cf6', '#ef4444', '#06b6d4', '#eab308', '#ec4899', '#64748b', '#84cc16'];
$colOf     = [];
$ci        = 0;
foreach (array_keys($mach) as $mc) {
    $colOf[$mc] = $palette[$ci++ % count($palette)];
}

$fleetUtilPct   = (float) ($sum['fleetUtilPct'] ?? 0.0);
$activeSec      = (int) ($sum['activeSec'] ?? 0);
$scheduledSec   = (int) ($sum['scheduledSec'] ?? 0);
$completed      = (int) ($sum['completed'] ?? $total);
$target         = (int) ($sum['target'] ?? $total);
$targetPct      = (float) ($sum['targetPct'] ?? ($target > 0 ? round($completed / $target * 100, 1) : 0.0));
$avgCycleSec    = (int) ($sum['avgCycleSec'] ?? 0);
$timedCount     = (int) ($sum['timedCount'] ?? 0);
$downtimeSec    = (int) ($sum['downtimeSec'] ?? 0);
$downtimeEvents = (int) ($sum['downtimeEvents'] ?? 0);
$downtimeNotes  = $sum['downtimeNotes'] ?? [];
$totalFailed    = (int) ($sum['totalFailed'] ?? 0);
$shiftHours     = (int) ($sum['shiftHours'] ?? 8);

$sumPass = $sumTol = $sumFail = 0;
foreach ($mach as $mItem) {
    $sumPass += (int) ($mItem['pass'] ?? 0);
    $sumTol  += (int) ($mItem['tol'] ?? 0);
    $sumFail += (int) ($mItem['fail'] ?? 0);
}
$avgMD = 0.0;
if ($nMach && $nDays) {
    $act = 0;
    foreach ($mach as $m) {
        $act += count($m['days'] ?? []);
    }
    $avgMD = $act ? $total / $act : 0;
}

$dates      = array_keys($dayTot);
$busiestDay = '';
$busiestCnt = 0;
foreach ($dayTot as $dd => $dc) {
    if ((int) $dc > $busiestCnt) {
        $busiestCnt = (int) $dc;
        $busiestDay = (string) $dd;
    }
}

$short  = static fn (string $c): string => preg_replace('/^DTP-QC-0*/i', 'QC-', $c);
$dayLbl = static fn (string $d): string => date('M j', strtotime($d));
$wdLbl  = static fn (string $d): string => date('D', strtotime($d));
$fmtDur = static function (int $sec): string {
    if ($sec <= 0) {
        return '0m';
    }
    $h = intdiv($sec, 3600);
    $m = intdiv($sec % 3600, 60);
    if ($h > 0) {
        return $h . 'h ' . sprintf('%02dm', $m);
    }
    return max(1, $m) . 'm';
};
$fmtCycle = static function (int $sec): string {
    if ($sec <= 0) {
        return '—';
    }
    $m = intdiv($sec, 60);
    $s = $sec % 60;
    if ($m >= 60) {
        return intdiv($m, 60) . 'h ' . ($m % 60) . 'm';
    }
    if ($m > 0) {
        return $m . 'm ' . sprintf('%02ds', $s);
    }
    return $s . 's';
};

// Pick default day for timeline: latest day with recorded start/finish duration, or last active day
$defaultDay = $dates !== [] ? end($dates) : '';
foreach (array_reverse($dates) as $dd) {
    if ((int) ($dayDetail[$dd]['activeSec'] ?? 0) > 0) {
        $defaultDay = $dd;
        break;
    }
}

$topDownNote = $downtimeNotes !== [] ? (string) array_key_first($downtimeNotes) : '';
if (strlen($topDownNote) > 54) {
    $topDownNote = substr($topDownNote, 0, 51) . '...';
}
?>
<div class="ut-live" id="ut-live-bar" hidden style="display:none"><i></i><span id="ut-live-txt">Live &middot; updated <?= date('H:i:s') ?></span></div>
<div id="utm-live">
<link rel="stylesheet" href="<?= base_url('assets/css/views/analytics-bonding_utm-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/analytics-bonding_utm-1.css') ?>">
<?php if ($nMach === 0): ?>
    <div class="ut-card"><div class="ut-body" style="padding:18px"><p class="muted" style="margin:0">No test with a universal tensile machine (DTP-QC-xxx) was found for these dates.<?= $unAss ? ' ' . number_format($unAss) . ' test(s) of the period were saved without a machine.' : '' ?></p></div></div>
<?php else: ?>

<!-- Daily Machine Output Matrix -->
<div class="ut-card">
    <div class="ut-h">
        <div>
            <span class="ut-h-step">DAILY WORKLOAD MATRIX</span>
            <h3>Machine &times; Day Breakdown<?= anTip('UTM = Universal Testing Machine, the tensile machine that pulls the shoe parts to test bonding strength.') ?></h3>
            <p>Finished shoes, operating duration, and failure badges per machine across all active dates</p>
        </div>
    </div>
    <div class="ut-legend">
        <div class="ut-leg-items">
            <span class="ut-leg-item"><span class="ut-leg-heat"><i style="background:rgba(37,99,235,.18)"></i><i style="background:rgba(37,99,235,.45)"></i><i style="background:rgba(37,99,235,.78)"></i></span> Workload Intensity</span>
            <span class="ut-leg-item"><span class="ut-leg-peak"></span> Machine Peak Day</span>
            <span class="ut-leg-item"><span class="ut-cell-f" style="margin-left:0">F</span> Failed Shoes</span>
            <span class="ut-leg-item"><strong>Xh Ym</strong> Active Working Time</span>
        </div>
        <span class="ut-leg-tip">Click any date header or bar to inspect its timeline</span>
    </div>
    <div class="ut-body ut-mx">
        <table class="ut-tbl">
            <thead>
                <tr>
                    <th>Machine</th>
                    <?php foreach ($dates as $d):
                        $isSun = date('w', strtotime($d)) === '0';
                    ?>
                        <th class="ut-day-th<?= $isSun ? ' ut-sun' : '' ?>" data-utm-jump="<?= esc($d) ?>" title="Inspect <?= esc($dayLbl($d)) ?> timeline">
                            <?= esc($dayLbl($d)) ?><br>
                            <small style="font-weight:600;opacity:.85"><?= esc($wdLbl($d)) ?></small>
                        </th>
                    <?php endforeach ?>
                    <th class="ut-tot-col">Total</th>
                    <th class="ut-tot-col">Avg/Day</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($mach as $mc => $m): $ad = max(1, count($m['days'])); ?>
                <tr>
                    <td>
                        <span class="ut-mc" title="<?= esc($mc, 'attr') ?>">
                            <i style="background:<?= $colOf[$mc] ?>"></i>
                            <span><?= esc($mc) ?></span>
                        </span>
                    </td>
                    <?php foreach ($dates as $d):
                        $v = (int) ($cell[$mc][$d] ?? 0);
                        $md = $machDay[$mc][$d] ?? null;
                        $span = (int) ($md['spanSec'] ?? 0);
                        $work = (int) ($md['activeSec'] ?? 0);
                        if ($work <= 0) $work = $span;
                        $idle = (int) ($md['idleSec'] ?? 0);
                        $falC = (int) ($md['fail'] ?? 0);
                        $ratio = $v > 0 ? min(1, $v / max(1, $m['peak'])) : 0;
                        $al = $v > 0 ? round(0.14 + 0.66 * $ratio, 2) : 0;
                        $isPeak = ($v > 0 && $v === (int) $m['peak']);
                        $txtCol = $ratio >= 0.58 ? '#ffffff' : '';
                        $tip = $v > 0
                            ? ($mc . ' · ' . $dayLbl($d) . ': ' . $v . ' finished' . ($work > 0 ? ' · Worked ' . $fmtDur($work) : '') . ($md && $md['start'] ? ' (Span ' . $md['start'] . '–' . $md['finish'] . ($idle >= 60 ? ', Idle ' . $fmtDur($idle) : '') . ')' : '') . ($falC > 0 ? ', ' . $falC . ' failed' : ''))
                            : ($md && ! empty($md['note']) ? $md['note'] : 'No runs');
                    ?>
                        <td class="ut-mx-cell<?= $isPeak ? ' ut-cell-peak' : '' ?>" data-utm-jump="<?= esc($d) ?>" title="<?= esc($tip, 'attr') ?>"<?= $v > 0 ? ' style="background:rgba(37,99,235,' . $al . ');font-weight:800;' . ($txtCol ? 'color:' . $txtCol . ';' : '') . '"' : '' ?>>
                            <?php if ($v > 0): ?>
                                <span class="ut-cell-top"><?= $v ?><?php if ($falC > 0): ?><span class="ut-cell-f"><?= $falC ?>F</span><?php endif ?></span>
                                <?php if ($work > 0): ?><span class="ut-cell-sub"><?= esc($fmtDur($work)) ?></span><?php endif ?>
                            <?php else: ?>
                                <span class="ut-z">&ndash;</span>
                            <?php endif ?>
                        </td>
                    <?php endforeach ?>
                    <td class="ut-tot-col"><?= number_format($m['tests']) ?></td>
                    <td class="ut-tot-col" style="color:#475569"><?= number_format($m['tests'] / $ad, 1) ?></td>
                </tr>
            <?php endforeach ?>
                <tr class="ut-tot-row">
                    <td>ALL MACHINES</td>
                    <?php foreach ($dates as $d): $dt = (int) $dayTot[$d]; $df = (int) ($dayDetail[$d]['failed'] ?? 0); ?>
                        <td style="<?= ($busiestDay !== '' && $d === $busiestDay) ? 'color:#2563eb' : '' ?>;cursor:pointer" data-utm-jump="<?= esc($d) ?>">
                            <span class="ut-cell-top"><?= $dt ?><?php if ($df > 0): ?><span class="ut-cell-f"><?= $df ?>F</span><?php endif ?></span>
                        </td>
                    <?php endforeach ?>
                    <td class="ut-tot-col" style="color:#2563eb"><?= number_format($total) ?></td>
                    <td class="ut-tot-col"><?= number_format($total / max(1, $nDays), 1) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Machine Start -> Finish Time Duration (Full Width Timeline below Breakdown) -->
<div class="ut-card">
    <div class="ut-h">
        <div>
            <span class="ut-h-step">SHIFT TIMELINE &amp; DURATION</span>
            <h3>Machine Start &rarr; Finish Time Duration</h3>
            <p>Colored blocks show actual testing intervals; light grey areas show idle time between runs</p>
        </div>
    </div>
    <div class="ut-body ut-tl-wrap">
        <div class="ut-tl-topbar">
            <div class="ut-tl-nav">
                <button type="button" class="ut-tl-step-btn" id="ut-day-prev" title="Previous day">&#9664;</button>
                <select class="ut-tl-select" id="ut-day-select" aria-label="Select date">
                    <?php foreach ($dates as $dd):
                        $dInfOpt = $dayDetail[$dd] ?? ['finished' => 0, 'failed' => 0];
                    ?>
                        <option value="<?= esc($dd) ?>"<?= $dd === $defaultDay ? ' selected' : '' ?>>
                            <?= esc(date('D, M j, Y', strtotime($dd))) ?> &mdash; <?= (int) $dInfOpt['finished'] ?> finished<?= (int) $dInfOpt['failed'] > 0 ? ' · ' . (int) $dInfOpt['failed'] . ' failed' : '' ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <button type="button" class="ut-tl-step-btn" id="ut-day-next" title="Next day">&#9654;</button>
            </div>
            <div class="ut-tl-leg">
                <span><i class="ut-tl-sw-work"></i> Active Working Time</span>
                <span><i class="ut-tl-sw-idle"></i> Idle / No Testing</span>
                <span class="ut-tl-lunch-note"><i class="ut-tl-sw-lunch"></i> Note: 12:00 PM &ndash; 1:00 PM Lunch Break</span>
            </div>
        </div>

        <?php
        $winStart = 7 * 3600;
        $winEnd   = 18 * 3600;
        $winSpan  = $winEnd - $winStart;
        foreach ($dates as $dd):
            $isAct = ($dd === $defaultDay);
        ?>
            <div class="ut-day-pane<?= $isAct ? ' active' : '' ?>" data-utm-pane="<?= esc($dd) ?>" style="overflow:auto">
                <table class="ut-tl-tbl">
                    <thead>
                        <tr>
                            <th class="l" style="width:140px">Machine</th>
                            <th style="padding-left:10px;padding-right:10px">
                                <div class="ut-tl-ticks">
                                    <span>07:00</span><span>08:00</span><span>09:00</span><span>10:00</span><span>11:00</span><span>12:00<i class="ut-tl-lunch-lbl">Lunch</i></span><span>13:00</span><span>14:00</span><span>15:00</span><span>16:00</span><span>17:00<span>18:00</span></span>
                                </div>
                            </th>
                            <th style="width:98px">Start &ndash; Finish</th>
                            <th style="width:78px">Worked</th>
                            <th style="width:74px">Idle</th>
                            <th style="width:72px">Output</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mach as $mc => $mInfo):
                        $md = $machDay[$mc][$dd] ?? null;
                        $used = $md && ! empty($md['used']) && ((int) ($md['finished'] ?? 0) > 0);
                        $sSec = (int) ($md['startSec'] ?? 0);
                        $fSec = (int) ($md['finishSec'] ?? 0);
                        $span = (int) ($md['spanSec'] ?? 0);
                        $work = (int) ($md['activeSec'] ?? $span);
                        $idle = (int) ($md['idleSec'] ?? 0);
                        $segs = $md['segments'] ?? [];
                        $hasSegs = ! empty($segs);
                        $finM = (int) ($md['finished'] ?? ($cell[$mc][$dd] ?? 0));
                        $falM = (int) ($md['fail'] ?? 0);
                        $leftPct  = 0.0;
                        $widthPct = 0.0;
                        if (! $hasSegs && $used && $sSec > 0 && $fSec >= $sSec) {
                            $clampedS = max($winStart, min($winEnd - 600, $sSec));
                            $clampedF = max($clampedS + 600, min($winEnd, $fSec));
                            $leftPct  = (($clampedS - $winStart) / $winSpan) * 100;
                            $widthPct = max(2.0, (($clampedF - $clampedS) / $winSpan) * 100);
                        }
                        $idleNote = trim((string) ($md['note'] ?? ''));
                        $trackTip = $idleNote ?: ($used && $md['start']
                            ? ($hasSegs
                                ? count($segs) . ' active session(s) · Worked ' . $fmtDur($work) . ($idle >= 60 ? ' · Idle ' . $fmtDur($idle) : '') . ' (' . $md['start'] . '–' . $md['finish'] . ')'
                                : 'Shift ' . $md['start'] . '–' . $md['finish'] . ' (' . $fmtDur($span) . ', daily Excel log)')
                            : 'No runs');
                    ?>
                        <tr>
                            <td class="l">
                                <span class="ut-mc"><i style="background:<?= $colOf[$mc] ?>"></i><?= esc($mc) ?></span>
                                <?php if ($used && $hasSegs): ?>
                                    <span class="ut-tl-sess"><?= count($segs) ?> working block<?= count($segs) === 1 ? '' : 's' ?></span>
                                <?php elseif (! $used && $md && ! ($md['used'])): ?>
                                    <span class="ut-tl-sess" style="color:#b45309">Idle shift</span>
                                <?php endif ?>
                            </td>
                            <td class="tl-track-cell">
                                <div class="ut-tl-track" title="<?= esc($trackTip, 'attr') ?>">
                                    <div class="ut-tl-grid"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                                    <div class="ut-tl-lunch-band" title="12:00 PM – 1:00 PM: Lunch Break"></div>
                                    <?php if ($used && $hasSegs): ?>
                                        <?php foreach ($segs as $sg):
                                            $cS = max($winStart, min($winEnd - 120, (int) $sg['s']));
                                            $cE = max($cS + 120, min($winEnd, (int) $sg['e']));
                                            $sLeft  = (($cS - $winStart) / $winSpan) * 100;
                                            $sWidth = max(0.8, (($cE - $cS) / $winSpan) * 100);
                                            $segTip = $mc . ' working ' . $sg['st'] . ' → ' . $sg['ft'] . ' (' . $fmtDur((int) $sg['dur']) . ')';
                                        ?>
                                            <div class="ut-tl-bar" title="<?= esc($segTip, 'attr') ?>" style="left:<?= round($sLeft, 2) ?>%;width:<?= round($sWidth, 2) ?>%;background:linear-gradient(90deg,<?= $colOf[$mc] ?>,<?= $colOf[$mc] ?>dd)">
                                                <?php if ($sWidth >= 9): ?>
                                                    <?= esc($sg['st']) ?>&ndash;<?= esc($sg['ft']) ?>
                                                <?php endif ?>
                                            </div>
                                        <?php endforeach ?>
                                    <?php elseif ($used && $sSec > 0 && $fSec >= $sSec): ?>
                                        <div class="ut-tl-bar" style="left:<?= round($leftPct, 2) ?>%;width:<?= round($widthPct, 2) ?>%;background:linear-gradient(90deg,<?= $colOf[$mc] ?>,<?= $colOf[$mc] ?>dd)">
                                            <?php if ($widthPct >= 14): ?>
                                                <?= esc($md['start']) ?> &rarr; <?= esc($md['finish']) ?>
                                            <?php endif ?>
                                        </div>
                                    <?php elseif ($used): ?>
                                        <div class="ut-tl-idle-msg"><?= $finM ?> finished (no timestamps)</div>
                                    <?php else: ?>
                                        <div class="ut-tl-idle-msg"><?= $idleNote !== '' ? 'Idle: ' . esc(strlen($idleNote) > 65 ? substr($idleNote, 0, 62) . '...' : $idleNote) : 'No runs recorded' ?></div>
                                    <?php endif ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($used && $md['start']): ?>
                                    <span class="ut-tl-time"><?= esc($md['start'] . ' – ' . $md['finish']) ?></span>
                                <?php else: ?>
                                    <span class="ut-z">&mdash;</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if ($used && $work > 0): ?>
                                    <span class="ut-tl-dur"><?= esc($fmtDur($work)) ?></span>
                                <?php else: ?>
                                    <span class="ut-z">&mdash;</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if ($used && $idle >= 60): ?>
                                    <span class="ut-tl-idle-tag"><?= esc($fmtDur($idle)) ?></span>
                                <?php elseif (! $used && $md && ! ($md['used'])): ?>
                                    <span class="ut-tl-idle-tag">8h 00m</span>
                                <?php else: ?>
                                    <span class="ut-z">&mdash;</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if ($used): ?>
                                    <span class="ut-tl-out"><?= $finM ?><?php if ($falM > 0): ?><span class="ut-cell-f" style="margin-left:0"><?= $falM ?>F</span><?php endif ?></span>
                                <?php else: ?>
                                    <span class="ut-z">&mdash;</span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach ?>
    </div>
</div>

<!-- Bottom 2-Column Row: Finished/Failed Chart + Machine Productivity Ranking -->
<?php
$maxBar = 1;
$targetHits = 0;
foreach ($dates as $dd) {
    $finD = (int) ($dayDetail[$dd]['finished'] ?? $dayTot[$dd] ?? 0);
    $tgtD = (int) ($dayDetail[$dd]['target'] ?? $finD);
    if ($finD > $maxBar) $maxBar = $finD;
    if ($tgtD > $maxBar) $maxBar = $tgtD;
    if ($tgtD > 0 && $finD >= $tgtD) $targetHits++;
}
$svgW = 620; $svgH = 228; $padL = 34; $padR = 12; $padT = 22; $padB = 40;
$plotW = $svgW - $padL - $padR;
$plotH = $svgH - $padT - $padB;
$nCols = max(1, count($dates));
$colW  = $plotW / $nCols;
$gridMax = max(5, (int) (ceil($maxBar / 5) * 5));
?>
<div class="ut-split">
    <!-- Col 1: Daily Finished Shoes vs Failed Shoes -->
    <div class="ut-card">
        <div class="ut-h">
            <div>
                <span class="ut-h-step">DAILY OUTPUT &amp; QUALITY</span>
                <h3>Finished Shoes vs. Failed Shoes per Day</h3>
                <p>Daily shoes completed vs. failures encountered &middot; <?= $targetHits ?>/<?= $nDays ?> days hit target</p>
            </div>
            <div class="ut-h-badges">
                <span class="ut-h-pill"><i style="background:#10b981"></i> Finished (<?= number_format($completed) ?>)</span>
                <span class="ut-h-pill"><i style="background:#ef4444"></i> Failed (<?= number_format($totalFailed) ?>)</span>
            </div>
        </div>
        <div class="ut-body ut-chart-wrap">
            <svg class="ut-chart-svg" viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" role="img" aria-label="Finished Shoes vs Failed Shoes per Day">
                <defs>
                    <linearGradient id="utGradFin" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#34d399"/><stop offset="100%" stop-color="#059669"/>
                    </linearGradient>
                    <linearGradient id="utGradFail" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#f87171"/><stop offset="100%" stop-color="#dc2626"/>
                    </linearGradient>
                </defs>
                <?php for ($g = 0; $g <= 4; $g++):
                    $val = (int) round($gridMax * $g / 4);
                    $gy  = $padT + $plotH - ($plotH * ($val / $gridMax));
                ?>
                    <line x1="<?= $padL ?>" y1="<?= $gy ?>" x2="<?= $svgW - $padR ?>" y2="<?= $gy ?>" stroke="#e2e8f0" stroke-dasharray="<?= $g === 0 ? '0' : '4 4' ?>" />
                    <text x="<?= $padL - 7 ?>" y="<?= $gy + 4 ?>" text-anchor="end" font-size="10.5" font-weight="700" fill="#64748b"><?= $val ?></text>
                <?php endfor ?>

                <?php $idx = 0; foreach ($dates as $dd):
                    $finD = (int) ($dayDetail[$dd]['finished'] ?? $dayTot[$dd] ?? 0);
                    $falD = (int) ($dayDetail[$dd]['failed'] ?? 0);
                    $tgtD = (int) ($dayDetail[$dd]['target'] ?? $finD);
                    $cx   = $padL + ($idx + 0.5) * $colW;
                    $bw   = min(22, max(10, $colW * 0.44));
                    $fw   = min(11, max(7, $bw * 0.5));
                    $fh   = $finD > 0 ? max(3, ($finD / $gridMax) * $plotH) : 0;
                    $fy   = $padT + $plotH - $fh;
                    $rh   = $falD > 0 ? max(5, ($falD / $gridMax) * $plotH) : 0;
                    $ry   = $padT + $plotH - $rh;
                    $ty   = $tgtD > 0 ? ($padT + $plotH - min(1, $tgtD / $gridMax) * $plotH) : null;
                    $idx++;
                ?>
                    <g class="ut-bar-grp" data-utm-jump="<?= esc($dd) ?>">
                        <title><?= esc($dayLbl($dd)) ?> (<?= esc($wdLbl($dd)) ?>): <?= $finD ?> finished / <?= $tgtD ?> target, <?= $falD ?> failed — click to inspect shift timeline</title>
                        <?php if ($ty !== null && $tgtD > $finD): ?>
                            <line x1="<?= $cx - $bw * 0.72 ?>" y1="<?= $ty ?>" x2="<?= $cx + $bw * 0.72 ?>" y2="<?= $ty ?>" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="3 2" />
                        <?php endif ?>
                        <rect x="<?= $cx - $bw * 0.62 ?>" y="<?= $fy ?>" width="<?= $bw ?>" height="<?= $fh ?>" rx="4" fill="url(#utGradFin)" />
                        <?php if ($finD > 0): ?>
                            <text x="<?= $cx - $bw * 0.12 ?>" y="<?= max(12, $fy - 5) ?>" text-anchor="middle" font-size="10.5" font-weight="800" fill="#047857"><?= $finD ?></text>
                        <?php endif ?>
                        <?php if ($falD > 0): ?>
                            <rect x="<?= $cx + $bw * 0.44 ?>" y="<?= $ry ?>" width="<?= $fw ?>" height="<?= $rh ?>" rx="3" fill="url(#utGradFail)" />
                            <text x="<?= $cx + $bw * 0.44 + $fw / 2 ?>" y="<?= max(12, $ry - 4) ?>" text-anchor="middle" font-size="10" font-weight="900" fill="#dc2626"><?= $falD ?></text>
                        <?php endif ?>
                        <text x="<?= $cx ?>" y="<?= $padT + $plotH + 15 ?>" text-anchor="middle" font-size="10" font-weight="700" fill="#334155"><?= esc(date('n/j', strtotime($dd))) ?></text>
                        <text x="<?= $cx ?>" y="<?= $padT + $plotH + 28 ?>" text-anchor="middle" font-size="9.5" font-weight="600" fill="<?= $wdLbl($dd) === 'Sun' ? '#e11d48' : '#64748b' ?>"><?= esc($wdLbl($dd)) ?></text>
                    </g>
                <?php endforeach ?>
            </svg>

            <div class="ut-day-strip">
                <span class="ut-stat-chip">Avg/Day: <b><?= number_format($completed / max(1, $nDays), 1) ?></b></span>
                <span class="ut-stat-chip">Fail Rate: <b><?= $completed > 0 ? number_format($totalFailed / $completed * 100, 1) : '0.0' ?>%</b></span>
            </div>
        </div>
    </div>

    <!-- Col 2: Machine Productivity Ranking & Breakdown -->
    <div class="ut-card">
    <div class="ut-h">
        <div>
            <span class="ut-h-step">MACHINE WORKLOAD &amp; OUTPUT</span>
            <h3>Machine Productivity Ranking<?= anTip('Cycle time = how long one test takes on a machine. Workload share = the part of all tests that this machine handled.') ?></h3>
            <p>Compares average cycle time, completed volume, workload share, pass rate, and failures across all tensile machines &middot; follows the day chosen in the timeline above</p>
        </div>
        <div class="ut-h-badges">
            <button type="button" class="ut-h-pill ut-rk-mode on" data-rk-mode="day">Daily</button>
            <button type="button" class="ut-h-pill ut-rk-mode" data-rk-mode="period">Whole period</button>
        </div>
    </div>
    <div class="ut-body" style="overflow:auto">
        <div class="ut-rk-pane" data-rk="period">
        <table class="ut-tbl">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th class="l">Machine</th>
                    <th>Avg Cycle</th>
                    <th>Finished</th>
                    <th class="l">Share of Work</th>
                    <th>Pass Rate</th>
                    <th>Failed</th>
                </tr>
            </thead>
            <tbody>
            <?php $rk = 0; foreach ($mach as $mc => $m): $rk++;
                $fin = $m['pass'] + $m['tol'] + $m['fail'];
                $pr  = $fin > 0 ? round($m['pass'] / $fin * 100, 1) : null;
                $sh  = $total > 0 ? $m['tests'] / $total * 100 : 0;
                $mCyc = ($m['specCnt'] ?? 0) > 0
                    ? (int) round($m['specSec'] / $m['specCnt'])
                    : (($m['activeSec'] > 0 && $m['tests'] > 0) ? (int) round($m['activeSec'] / $m['tests']) : 0);
            ?>
                <tr>
                    <td><span class="ut-rank<?= $rk === 1 ? ' top1' : '' ?>">#<?= $rk ?></span></td>
                    <td class="l"><span class="ut-mc" style="white-space:nowrap"><i style="background:<?= $colOf[$mc] ?>"></i><?= esc($mc) ?></span></td>
                    <td><b><?= esc($fmtCycle($mCyc)) ?></b></td>
                    <td><b style="font-size:14px"><?= number_format($m['tests']) ?></b></td>
                    <td class="l">
                        <div class="ut-bar">
                            <span><i style="width:<?= round($sh, 1) ?>%;background:<?= $colOf[$mc] ?>"></i></span>
                            <b><?= number_format($sh, 1) ?>%</b>
                        </div>
                    </td>
                    <td><?= $pr === null ? '<span class="ut-z">&mdash;</span>' : '<span class="ut-pill ' . ($pr >= 95 ? 'ut-ok' : ($pr >= 85 ? 'ut-warn' : 'ut-bad')) . '">' . $pr . '%</span>' ?></td>
                    <td><?= $m['fail'] > 0 ? '<span class="ut-pill ut-bad">' . (int) $m['fail'] . '</span>' : '<span class="ut-z">0</span>' ?></td>
                </tr>
            <?php endforeach ?>
            <?php
            // results of tests that have no machine number: shown apart so the total row equals the Failed / Finished counts of the chart on the left
            $unP = (int) ($utm['unrecordedPass'] ?? 0);
            $unT = (int) ($utm['unrecordedTol'] ?? 0);
            $unF = (int) ($utm['unrecordedFail'] ?? 0);
            if ($unP + $unT + $unF > 0):
                $unFin = $unP + $unT + $unF;
                $unPr  = $unFin > 0 ? round($unP / $unFin * 100, 1) : null;
            ?>
                <tr>
                    <td><span class="ut-rank">&ndash;</span></td>
                    <td class="l"><span class="ut-mc" style="white-space:nowrap"><i style="background:#94a3b8"></i>No machine recorded</span></td>
                    <td><span class="ut-z">&mdash;</span></td>
                    <td><span class="ut-z">&mdash;</span></td>
                    <td class="l"><span class="ut-z">counted in the machines above</span></td>
                    <td><?= $unPr === null ? '<span class="ut-z">&mdash;</span>' : '<span class="ut-pill ' . ($unPr >= 95 ? 'ut-ok' : ($unPr >= 85 ? 'ut-warn' : 'ut-bad')) . '">' . $unPr . '%</span>' ?></td>
                    <td><?= $unF > 0 ? '<span class="ut-pill ut-bad">' . $unF . '</span>' : '<span class="ut-z">0</span>' ?></td>
                </tr>
            <?php endif ?>
                <tr class="ut-tot-row">
                    <td colspan="2" class="l" style="padding-left:14px">ALL MACHINES TOTAL</td>
                    <td><?= esc($fmtCycle($avgCycleSec)) ?></td>
                    <td style="color:#2563eb;font-size:15px"><?= number_format($total) ?></td>
                    <td class="l">
                        <div class="ut-bar">
                            <span><i style="width:100%;background:#2563eb"></i></span>
                            <b>100%</b>
                        </div>
                    </td>
                    <?php
                    $allPass = $sumPass + (int) ($utm['unrecordedPass'] ?? 0);
                    $allTol  = $sumTol + (int) ($utm['unrecordedTol'] ?? 0);
                    $allFail = $sumFail + (int) ($utm['unrecordedFail'] ?? 0);
                    $totFin  = $allPass + $allTol + $allFail;
                    ?>
                    <td><?= $totFin > 0 ? '<span class="ut-pill ' . (round($allPass / $totFin * 100, 1) >= 95 ? 'ut-ok' : 'ut-warn') . '">' . round($allPass / $totFin * 100, 1) . '%</span>' : '<span class="ut-z">&mdash;</span>' ?></td>
                    <td><?= $allFail > 0 ? '<span class="ut-pill ut-bad">' . $allFail . '</span>' : '<span class="ut-z">0</span>' ?></td>
                </tr>
            </tbody>
        </table>
        </div>

        <?php /* ---- daily ranking: one pane per day, shown for the day chosen in the timeline ---- */
        foreach ($dates as $dd):
            $rowsD = [];
            foreach ($machDay as $mcD => $byD) {
                if (! empty($byD[$dd]['used']) && (int) ($byD[$dd]['finished'] ?? 0) > 0) {
                    $rowsD[$mcD] = $byD[$dd];
                }
            }
            uasort($rowsD, static fn ($a, $b) => (int) $b['finished'] <=> (int) $a['finished']);
            $dayFin = 0;
            $dP = $dT = $dF = 0;
            $dAct = 0;
            foreach ($rowsD as $r) {
                $dayFin += (int) $r['finished'];
                $dP += (int) $r['pass'];
                $dT += (int) $r['tol'];
                $dF += (int) $r['fail'];
                $dAct += (int) ($r['activeSec'] ?? 0);
            }
            $dd0 = $dayDetail[$dd] ?? [];
            $uP = max(0, (int) ($dd0['pass'] ?? 0) - $dP);
            $uT = max(0, (int) ($dd0['tol'] ?? 0) - $dT);
            $uF = max(0, (int) ($dd0['failed'] ?? 0) - $dF);
            $aP = $dP + $uP; $aT = $dT + $uT; $aF = $dF + $uF; $aFin = $aP + $aT + $aF;
            $pill = static fn (?float $v): string => $v === null ? '<span class="ut-z">&mdash;</span>' : '<span class="ut-pill ' . ($v >= 95 ? 'ut-ok' : ($v >= 85 ? 'ut-warn' : 'ut-bad')) . '">' . $v . '%</span>';
        ?>
        <div class="ut-rk-pane<?= $dd === $defaultDay ? ' active' : '' ?>" data-rk="<?= esc($dd, 'attr') ?>">
            <div class="ut-rk-cap"><?= esc(date('l, M j, Y', strtotime($dd))) ?> &middot; <?= (int) $dayFin ?> finished<?= $aF > 0 ? ' &middot; ' . $aF . ' failed' : '' ?></div>
            <table class="ut-tbl">
                <thead>
                    <tr><th>Rank</th><th class="l">Machine</th><th>Avg Cycle</th><th>Finished</th><th class="l">Share of Day</th><th>Pass Rate</th><th>Failed</th></tr>
                </thead>
                <tbody>
                <?php if ($rowsD === []): ?>
                    <tr><td colspan="7" class="ut-z" style="padding:22px 0">No machine runs recorded on this day.</td></tr>
                <?php endif ?>
                <?php $rkD = 0; foreach ($rowsD as $mcD => $r): $rkD++;
                    $fn  = (int) $r['finished'];
                    $shD = $dayFin > 0 ? $fn / $dayFin * 100 : 0;
                    $cyD = ((int) ($r['specCnt'] ?? 0) > 0) ? (int) round($r['specSec'] / $r['specCnt']) : (((int) ($r['activeSec'] ?? 0) > 0) ? (int) round($r['activeSec'] / $fn) : 0);
                    $rf  = (int) $r['pass'] + (int) $r['tol'] + (int) $r['fail'];
                ?>
                    <tr>
                        <td><span class="ut-rank<?= $rkD === 1 ? ' top1' : '' ?>">#<?= $rkD ?></span></td>
                        <td class="l"><span class="ut-mc" style="white-space:nowrap"><i style="background:<?= $colOf[$mcD] ?? '#64748b' ?>"></i><?= esc($mcD) ?></span></td>
                        <td><b><?= esc($fmtCycle($cyD)) ?></b></td>
                        <td><b style="font-size:14px"><?= number_format($fn) ?></b></td>
                        <td class="l"><div class="ut-bar"><span><i style="width:<?= round($shD, 1) ?>%;background:<?= $colOf[$mcD] ?? '#64748b' ?>"></i></span><b><?= number_format($shD, 1) ?>%</b></div></td>
                        <td><?= $pill($rf > 0 ? round($r['pass'] / $rf * 100, 1) : null) ?></td>
                        <td><?= (int) $r['fail'] > 0 ? '<span class="ut-pill ut-bad">' . (int) $r['fail'] . '</span>' : '<span class="ut-z">0</span>' ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($uP + $uT + $uF > 0): ?>
                    <tr>
                        <td><span class="ut-rank">&ndash;</span></td>
                        <td class="l"><span class="ut-mc" style="white-space:nowrap"><i style="background:#94a3b8"></i>No machine recorded</span></td>
                        <td><span class="ut-z">&mdash;</span></td><td><span class="ut-z">&mdash;</span></td>
                        <td class="l"><span class="ut-z">counted in the machines above</span></td>
                        <td><?= $pill(round($uP / ($uP + $uT + $uF) * 100, 1)) ?></td>
                        <td><?= $uF > 0 ? '<span class="ut-pill ut-bad">' . $uF . '</span>' : '<span class="ut-z">0</span>' ?></td>
                    </tr>
                <?php endif ?>
                    <tr class="ut-tot-row">
                        <td colspan="2" class="l" style="padding-left:14px">DAY TOTAL</td>
                        <td><?= esc($fmtCycle($dayFin > 0 && $dAct > 0 ? (int) round($dAct / $dayFin) : 0)) ?></td>
                        <td style="color:#2563eb;font-size:15px"><?= number_format($dayFin) ?></td>
                        <td class="l"><div class="ut-bar"><span><i style="width:100%;background:#2563eb"></i></span><b>100%</b></div></td>
                        <td><?= $pill($aFin > 0 ? round($aP / $aFin * 100, 1) : null) ?></td>
                        <td><?= $aF > 0 ? '<span class="ut-pill ut-bad">' . $aF . '</span>' : '<span class="ut-z">0</span>' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endforeach ?>
    </div>
    </div>
</div>

<p class="ut-note">
    <strong>Metric definitions:</strong> Shift utilization assumes an 8-hour scheduled shift per machine per active lab day. Start &rarr; Finish duration and unplanned downtime come from daily UCAN machine monitoring logs through <?= esc($utm['xlUntil'] ?: 'historical cutoff') ?> and live Lab Tracker specimen timestamps thereafter; per-machine failure counts match recorded bonding results via LabRefNo.
    <?php if ($unAss > 0): ?>
        <strong><?= number_format($unAss) ?></strong> test(s) in the live period had no machine assigned (<?= number_format((int) ($utm['unBond'] ?? 0)) ?> bonding, <?= number_format((int) ($utm['unPull'] ?? 0)) ?> pull).
    <?php endif ?>
</p>
<?php endif ?>
</div>
<script src="<?= base_url('assets/js/views/analytics-bonding_utm-1.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/views/analytics-bonding_utm-1.js') ?>"></script>
