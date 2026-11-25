<?php
/**
 * Bonding Failure Overview: designed to be understood in 3 seconds at first glance.
 * Shows:
 *  1) 4 Plain-English Executive KPI Cards (Total Failed Shoes, Most Affected Building, Most Common Shoe Part, Main Root Cause)
 *  2) 3 Step-by-Step Visual Cards (1. Which Building Failed? -> 2. Which Shoe Part Separated? -> 3. Why Did It Fail?)
 *     each with a Center-Total Donut Chart + Ranked Horizontal Progress Bar Breakdown + Building A vs. B split
 *  3) Side-by-Side Building A vs. Building B Comparison & Scannable Failed Shoes Log
 */
$fb      = $fb ?? ['bld' => [], 'por' => [], 'rea' => [], 'byB' => [], 'log' => [], 'failed' => 0, 'noPortion' => 0, 'noReason' => 0];
$asOf    = ($to ?? '') !== '' ? $to : date('Y-m-d');
$yearLbl = date('Y', strtotime($asOf));
$period  = (($from ?? '') !== '' || ($to ?? '') !== '') ? (($from ?: '…') . ' → ' . ($to ?: '…')) : 'All dates';

$bldData = $fb['bld'] ?? ['A' => 0, 'B' => 0];
$porData = $fb['por'] ?? [];
$reaData = $fb['rea'] ?? [];
$byB     = $fb['byB'] ?? ['A' => ['por' => [], 'rea' => []], 'B' => ['por' => [], 'rea' => []]];
$logRows = $fb['log'] ?? [];

$bldTot  = array_sum($bldData);
$porTot  = array_sum($porData);
$reaTot  = array_sum($reaData);
$otherFailed = max(0, (int) $fb['failed'] - $bldTot);

$topItem = static function (array $arr): array {
    $tot = array_sum($arr);
    if ($tot <= 0 || empty($arr)) {
        return ['key' => 'None', 'val' => 0, 'pct' => 0];
    }
    $bestK = (string) array_key_first($arr);
    $bestV = (int) reset($arr);
    foreach ($arr as $k => $v) {
        if ((int) $v > $bestV) {
            $bestV = (int) $v;
            $bestK = (string) $k;
        }
    }
    return ['key' => $bestK, 'val' => $bestV, 'pct' => round(($bestV / $tot) * 100, 1)];
};

$dlUrl = site_url('analytics/export-failure-overview') . '?' . http_build_query(array_filter([
    'from' => $from ?? '',
    'to'   => $to ?? '',
    'all'  => (($from ?? '') === '' && ($to ?? '') === '') ? '1' : null,
], static fn ($v) => $v !== null && $v !== ''));

$topBld = $topItem($bldData);
$topPor = $topItem($porData);
$topRea = $topItem($reaData);

$donutSvg = static function (array $data, array $colorMap, string $centerSub): string {
    $tot = array_sum($data);
    $cx  = 150;
    $cy  = 136;
    $r   = 100;
    $ri  = 54;
    $o   = '<svg viewBox="0 0 300 272" class="bf-svg" role="img">';
    if ($tot <= 0) {
        return $o
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="#e2e8f0"/>'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $ri . '" class="bf-donut-hole" fill="#ffffff"/>'
            . '<text x="' . $cx . '" y="' . ($cy - 2) . '" text-anchor="middle" fill="#64748b" font-size="18" font-weight="800">0</text>'
            . '<text x="' . $cx . '" y="' . ($cy + 15) . '" text-anchor="middle" fill="#94a3b8" font-size="10.5" font-weight="700">NO FAILURES</text></svg>';
    }

    $a0 = -M_PI / 2;
    $nz = count(array_filter($data, static fn ($v) => $v > 0));
    $slices = [];
    $idx = 0;
    $fallbackCols = ['#2563eb', '#f97316', '#e11d48', '#0d9488'];
    foreach ($data as $k => $v) {
        $col = $colorMap[$k] ?? $fallbackCols[$idx++ % count($fallbackCols)];
        if ($v <= 0) {
            continue;
        }
        $pct = $v / $tot;
        $a1  = $a0 + $pct * 2 * M_PI;
        $slices[] = ['k' => (string) $k, 'v' => (int) $v, 'pct' => $pct, 'a0' => $a0, 'a1' => $a1, 'col' => $col];
        $a0 = $a1;
    }

    foreach ($slices as $s) {
        if ($nz === 1) {
            $o .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $s['col'] . '" stroke="#ffffff" stroke-width="2.5"/>';
            break;
        }
        $x0 = $cx + $r * cos($s['a0']);
        $y0 = $cy + $r * sin($s['a0']);
        $x1 = $cx + $r * cos($s['a1']);
        $y1 = $cy + $r * sin($s['a1']);
        $lg = $s['pct'] > 0.5 ? 1 : 0;
        $o .= '<path d="M' . $cx . ',' . $cy . ' L' . round($x0, 2) . ',' . round($y0, 2)
            . ' A' . $r . ',' . $r . ' 0 ' . $lg . ',1 ' . round($x1, 2) . ',' . round($y1, 2)
            . ' Z" fill="' . $s['col'] . '" stroke="#ffffff" stroke-width="2.5" stroke-linejoin="round"><title>' . esc($s['k']) . ': ' . $s['v'] . ' (' . round($s['pct'] * 100, 1) . '%)</title></path>';
    }

    // Center hole for clean executive donut look
    $o .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $ri . '" class="bf-donut-hole" fill="#ffffff"/>';
    $o .= '<text x="' . $cx . '" y="' . ($cy + 2) . '" text-anchor="middle" class="bf-donut-num" fill="#0f172a" font-size="25" font-weight="900">' . (int) $tot . '</text>';
    $o .= '<text x="' . $cx . '" y="' . ($cy + 18) . '" text-anchor="middle" class="bf-donut-sub" fill="#64748b" font-size="9.5" font-weight="800" letter-spacing=".06em">' . esc(strtoupper($centerSub)) . '</text>';

    // Slice percentage labels
    $rm = ($r + $ri) / 2;
    foreach ($slices as $s) {
        $pctNum = (int) round($s['pct'] * 100);
        if ($pctNum <= 0) {
            continue;
        }
        if ($nz === 1) {
            $o .= '<text x="' . $cx . '" y="' . ($cy - $rm + 4) . '" text-anchor="middle" fill="#ffffff" font-size="12.5" font-weight="800">100%</text>';
            break;
        }
        $am = ($s['a0'] + $s['a1']) / 2;
        if ($s['pct'] < 0.09) {
            // Small slice: outer callout pill so it never clips
            $lx = round($cx + ($r + 18) * cos($am), 1);
            $ly = round($cy + ($r + 18) * sin($am), 1);
            $ix = round($cx + ($r - 5) * cos($am), 1);
            $iy = round($cy + ($r - 5) * sin($am), 1);
            $ox = round($cx + ($r + 7) * cos($am), 1);
            $oy = round($cy + ($r + 7) * sin($am), 1);
            $o .= '<line x1="' . $ix . '" y1="' . $iy . '" x2="' . $ox . '" y2="' . $oy . '" stroke="' . $s['col'] . '" stroke-width="2" stroke-linecap="round"/>';
            $o .= '<rect x="' . ($lx - 17) . '" y="' . ($ly - 9) . '" width="34" height="18" rx="5" fill="' . $s['col'] . '" stroke="#ffffff" stroke-width="1.5"/>';
            $o .= '<text x="' . $lx . '" y="' . ($ly + 3.5) . '" text-anchor="middle" fill="#ffffff" font-size="10.5" font-weight="800">' . $pctNum . '%</text>';
        } else {
            $tx = round($cx + $rm * cos($am), 1);
            $ty = round($cy + $rm * sin($am) + 4.5, 1);
            $o .= '<text x="' . $tx . '" y="' . $ty . '" text-anchor="middle" fill="#ffffff" font-size="13" font-weight="800" style="text-shadow:0 1px 2px rgba(15,23,42,.5)">' . $pctNum . '%</text>';
        }
    }

    return $o . '</svg>';
};

$cards = [
    [
        'step'      => 'STEP 1 · FACTORY LOCATION',
        'title'     => 'Which Building Failed?',
        'subtitle'  => 'Compares failed production-line shoes between Building A and Building B',
        'excelHdr'  => 'FAILED BONDING BY BUILDING ' . $yearLbl . ': A & B',
        'centerSub' => 'Bldg A & B',
        'data'      => $bldData,
        'colors'    => ['A' => '#2563eb', 'B' => '#f97316'],
        'labels'    => [
            'A' => ['title' => 'Building A (Lines A1–A12)', 'desc' => 'Assembly lines in Building A'],
            'B' => ['title' => 'Building B (Lines B1–B14)', 'desc' => 'Assembly lines in Building B'],
        ],
        'byBKey'    => null,
        'footNote'  => $otherFailed > 0 ? ($otherFailed . ' additional failed report' . ($otherFailed === 1 ? ' is a' : 's are') . ' Trial / Sample (no A/B line code)') : 'All failed reports belong to Building A or Building B',
    ],
    [
        'step'      => 'STEP 2 · SHOE LAYER (WHERE)',
        'title'     => 'Which Shoe Part Separated?',
        'subtitle'  => 'Shows which bonded layer pulled apart during peel/sole bonding tests',
        'excelHdr'  => 'BUILDING A & B: PORTION OF FAILURE ' . $yearLbl,
        'centerSub' => 'Failed Parts',
        'data'      => $porData,
        'colors'    => [
            'Upper to Foxing'   => '#0284c7',
            'Foxing to Outsole' => '#d97706',
            'Filler to Outsole' => '#64748b',
        ],
        'labels'    => [
            'Upper to Foxing'   => ['title' => 'Upper to Foxing',   'desc' => 'Sidewall foxing tape separated from upper canvas/suede'],
            'Foxing to Outsole' => ['title' => 'Foxing to Outsole', 'desc' => 'Foxing tape separated from the bottom rubber outsole'],
            'Filler to Outsole' => ['title' => 'Filler to Outsole', 'desc' => 'Bottom filler layer separated from the rubber outsole'],
        ],
        'byBKey'    => 'por',
        'footNote'  => !empty($fb['noPortion']) ? ((int) $fb['noPortion'] . ' failed report(s) had no portion specified') : 'A single shoe can fail on more than one portion',
    ],
    [
        'step'      => 'STEP 3 · ROOT CAUSE (WHY)',
        'title'     => 'Why Did the Bond Fail?',
        'subtitle'  => 'Groups the recorded failure causes into Workmanship, Outsole/Foxing, or Material',
        'excelHdr'  => 'BUILDING A & B: REASON OF FAILURE ' . $yearLbl,
        'centerSub' => 'Root Causes',
        'data'      => $reaData,
        'colors'    => [
            'Workmanship'           => '#e11d48',
            'Rubber Foxing/Outsole' => '#f97316',
            'Material'              => '#4f46e5',
        ],
        'labels'    => [
            'Workmanship'           => ['title' => 'Workmanship (Process)', 'desc' => 'Overdried cement, poor pressing, poor priming/buffing/cementing'],
            'Rubber Foxing/Outsole' => ['title' => 'Rubber Foxing / Outsole', 'desc' => 'Dusty or splitting foxing tape / rubber outsole surface'],
            'Material'              => ['title' => 'Weak Material', 'desc' => 'Upper or lining material tore before the adhesive glue gave way'],
        ],
        'byBKey'    => 'rea',
        'footNote'  => !empty($fb['noReason']) ? ((int) $fb['noReason'] . ' failed report(s) had no root cause recorded') : 'Workmanship issues are actionable on the assembly line',
    ],
];
?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/analytics-bonding_failure-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/analytics-bonding_failure-1.css') ?>">

<?php if ((int) $fb['failed'] > 0): ?>
    <div style="margin-bottom:12px"><?= anSummary(
        number_format((int) $fb['failed']) . ' shoes failed the bonding test (' . $period . ').',
        ($topBld['val'] > 0 ? 'Most failures were in Building ' . $topBld['key'] . ' (' . $topBld['pct'] . '%). ' : '')
            . ($topPor['val'] > 0 ? 'The part that separated most often: ' . $topPor['key'] . '. ' : '')
            . ($topRea['val'] > 0 ? 'The most common cause: ' . $topRea['key'] . '. ' : '')
            . 'Start with that cause to reduce failures fastest.',
        'warn'
    ) ?></div>
<?php else: ?>
    <div style="margin-bottom:12px"><?= anSummary('No failed bonding shoes were recorded for this period (' . $period . ').', 'Nothing needs follow-up here.', 'ok') ?></div>
<?php endif ?>

<!-- 4 Executive Summary KPI Cards -->
<div class="bf-kpis">
    <div class="bf-kpi" style="--kpi-accent:#e11d48">
        <div>
            <div class="bf-kpi-top">
                <span class="bf-kpi-lbl">1. Total Failed Shoes</span>
                <span class="bf-kpi-tag" style="background:#fef2f2;color:#be123c">As of <?= esc($asOf) ?></span>
            </div>
            <div class="bf-kpi-val" style="color:#e11d48"><?= number_format((int) $fb['failed']) ?> <span style="font-size:13px;font-weight:700;color:#64748b">shoes</span></div>
        </div>
        <div class="bf-kpi-sub">
            <?php if ($otherFailed > 0): ?>
                <strong><?= (int) $bldTot ?></strong> on Building A &amp; B lines · <strong><?= (int) $otherFailed ?></strong> Trial/Sample
            <?php else: ?>
                All <strong><?= (int) $bldTot ?></strong> failed shoes from Building A &amp; B lines
            <?php endif ?>
        </div>
    </div>

    <div class="bf-kpi" style="--kpi-accent:#2563eb">
        <div>
            <div class="bf-kpi-top">
                <span class="bf-kpi-lbl">2. Most Affected Building</span>
                <span class="bf-kpi-tag" style="background:#eff6ff;color:#1d4ed8"><?= $topBld['pct'] ?>% of Line Fails</span>
            </div>
            <div class="bf-kpi-val"><?= $topBld['val'] > 0 ? 'Building ' . esc($topBld['key']) : 'No Line Failures' ?></div>
        </div>
        <div>
            <div class="bf-kpi-sub">
                Building A: <strong><?= (int) ($bldData['A'] ?? 0) ?></strong> · Building B: <strong><?= (int) ($bldData['B'] ?? 0) ?></strong>
            </div>
            <?php if ($bldTot > 0): ?>
            <div class="bf-mini-split" title="Building A vs Building B share">
                <span style="width:<?= round((($bldData['A'] ?? 0) / $bldTot) * 100, 1) ?>%;background:#2563eb"></span>
                <span style="width:<?= round((($bldData['B'] ?? 0) / $bldTot) * 100, 1) ?>%;background:#f97316"></span>
            </div>
            <?php endif ?>
        </div>
    </div>

    <div class="bf-kpi" style="--kpi-accent:#d97706">
        <div>
            <div class="bf-kpi-top">
                <span class="bf-kpi-lbl">3. Top Failed Part (Where)</span>
                <span class="bf-kpi-tag" style="background:#fffbeb;color:#b45309"><?= $topPor['pct'] ?>% Share</span>
            </div>
            <div class="bf-kpi-val"><?= esc($topPor['key']) ?></div>
        </div>
        <div class="bf-kpi-sub">
            Separated in <strong><?= (int) $topPor['val'] ?></strong> of <strong><?= (int) $porTot ?></strong> recorded layer failures
        </div>
    </div>

    <div class="bf-kpi" style="--kpi-accent:#be123c">
        <div>
            <div class="bf-kpi-top">
                <span class="bf-kpi-lbl">4. Main Root Cause (Why)</span>
                <span class="bf-kpi-tag" style="background:#fef2f2;color:#be123c"><?= $topRea['pct'] ?>% Share</span>
            </div>
            <div class="bf-kpi-val"><?= esc($topRea['key']) ?></div>
        </div>
        <div class="bf-kpi-sub">
            Accounted for <strong><?= (int) $topRea['val'] ?></strong> of <strong><?= (int) $reaTot ?></strong> recorded failure causes
        </div>
    </div>
</div>

<!-- 3 Visual Step-by-Step Breakdown Cards -->
<div class="bf-wrap">
    <?php foreach ($cards as $c):
        $data = $c['data'];
        $tot  = array_sum($data);
        $maxV = $data ? max($data) : 0;
    ?>
    <div class="bf-card">
        <div class="bf-card-hd">
            <div class="bf-card-hd-row">
                <p style="margin:0"><?= esc($c['subtitle']) ?></p>
                <span class="bf-card-hd-tot"><?= (int) $tot ?> Total</span>
            </div>
        </div>

        <div class="bf-pie">
            <?= $donutSvg($data, $c['colors'], $c['centerSub']) ?>
        </div>

        <div class="bf-list">
            <div class="bf-list-rows">
                <?php foreach ($data as $k => $v):
                    $col   = $c['colors'][$k] ?? '#2563eb';
                    $pct   = $tot > 0 ? round(($v / $tot) * 100, 1) : 0;
                    $meta  = $c['labels'][$k] ?? ['title' => (string) $k, 'desc' => ''];
                    $isTop = ($v > 0 && $v === $maxV);
                    $bKey  = $c['byBKey'];
                    $cntA  = $bKey ? (int) ($byB['A'][$bKey][$k] ?? 0) : null;
                    $cntB  = $bKey ? (int) ($byB['B'][$bKey][$k] ?? 0) : null;
                ?>
                <div class="bf-item<?= $isTop ? ' bf-item-top' : '' ?>">
                    <div class="bf-item-head">
                        <div class="bf-item-name">
                            <span class="bf-item-dot" style="background:<?= esc($col) ?>"></span>
                            <span><?= esc($meta['title']) ?></span>
                        </div>
                        <div class="bf-item-nums">
                            <span class="bf-item-cnt"><?= (int) $v ?></span>
                            <span class="bf-item-pct" style="<?= $v > 0 ? 'background:' . esc($col) . '1a;color:' . esc($col) : '' ?>"><?= $pct ?>%</span>
                        </div>
                    </div>
                    <?php if ($meta['desc'] !== ''): ?>
                        <div class="bf-item-desc"><?= esc($meta['desc']) ?></div>
                    <?php endif ?>
                    <div class="bf-bar-track">
                        <div class="bf-bar-fill" style="width:<?= $pct ?>%;background:<?= esc($col) ?>"></div>
                    </div>
                    <?php if ($bKey !== null && ($cntA > 0 || $cntB > 0)): ?>
                        <div class="bf-item-blds">
                            <span class="bf-bld-chip">Building A: <strong><?= $cntA ?></strong></span>
                            <span class="bf-bld-chip">Building B: <strong><?= $cntB ?></strong></span>
                        </div>
                    <?php endif ?>
                </div>
                <?php endforeach ?>
            </div>

            <div class="bf-card-foot">
                <span class="bf-card-foot-lbl">Total Failure as of <?= esc($asOf) ?></span>
                <span class="bf-card-foot-val"><?= (int) $tot ?></span>
                <?php if (!empty($c['footNote'])): ?>
                    <div class="bf-card-foot-note"><?= esc($c['footNote']) ?></div>
                <?php endif ?>
            </div>
        </div>
    </div>
    <?php endforeach ?>
</div>

<?php if (!empty($logRows)): ?>
<div class="bf-log-card">
    <div class="bf-log-hd">
        <div>
            <h4>Failed Bonding Specimens Log (<?= count($logRows) ?>)</h4>
            <span>Exact production lines, shoe styles, separated portions, and root causes recorded in this period<?= anTip('Separated portion = the layer of the shoe that peeled apart during the test. Root cause = the recorded reason it failed.') ?></span>
        </div>
        <span>As of <?= esc($asOf) ?></span>
    </div>
    <div class="bf-log-scroll">
        <table class="bf-log-tbl">
            <thead>
                <tr>
                    <th>Prod. Date</th>
                    <th>Line / Shoe</th>
                    <th>Lab Ref No</th>
                    <th>Style &amp; Colorway</th>
                    <th>Where It Separated (Portion)</th>
                    <th>Why It Failed (Root Cause)</th>
                    <th>Detailed Cause</th>
                    <th>Technician</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logRows as $row):
                    $ln = (string) ($row['line'] ?? '');
                    $lnCls = str_starts_with($ln, 'A') ? 'b-a' : (str_starts_with($ln, 'B') ? 'b-b' : 'b-o');
                ?>
                <tr>
                    <td style="white-space:nowrap;font-weight:700"><?= esc($row['prod'] ?: '—') ?></td>
                    <td style="white-space:nowrap">
                        <span class="bf-line-pill <?= $lnCls ?>"><?= esc($ln !== '' ? 'Line ' . $ln : 'Trial / Sample') ?></span>
                    </td>
                    <td style="white-space:nowrap;font-family:monospace;font-weight:700"><?= esc($row['ref']) ?></td>
                    <td>
                        <div style="font-weight:700"><?= esc($row['style'] ?: '—') ?></div>
                        <?php if (!empty($row['color'])): ?><div style="font-size:11px;color:#64748b"><?= esc($row['color']) ?></div><?php endif ?>
                    </td>
                    <td><?= !empty($row['portion']) ? '<strong>' . esc($row['portion']) . '</strong>' : '<span style="color:#94a3b8">Not specified</span>' ?></td>
                    <td>
                        <?php if (!empty($row['reason'])): ?>
                            <span class="bf-rea-pill"><?= esc($row['reason']) ?></span>
                        <?php else: ?>
                            <span style="color:#94a3b8">Not recorded</span>
                        <?php endif ?>
                    </td>
                    <td style="font-size:11.5px;color:#475569"><?= esc($row['cause'] ?: '—') ?></td>
                    <td style="white-space:nowrap;color:#475569"><?= esc($row['tech'] ?: '—') ?></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif ?>
