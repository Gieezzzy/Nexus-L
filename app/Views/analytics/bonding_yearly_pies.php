<?php
/**
 * Shoe Bonding > Monthly Summary > Whole-Year Pie Summary view (included by analytics/index.php).
 * Data: $yearlyPies (12 items: month, name, pass, fail, tol, total), $yearlyYear (int)
 */
$yPies = $yearlyPies ?? [];
$yYr   = (int) ($yearlyYear ?? date('Y'));
$ySub  = (string) ($ypSubtitle ?? 'Whole-year bonding test status distribution (PASS, FAILED, TOLERABLE) across all 12 months — click any month title to open its daily breakdown');
$yBack = (string) ($ypBackLabel ?? 'Back to Monthly View');
$yQry  = (string) ($ypMonthQuery ?? 'tab=bonding&view=monthly&group=day');
$yrPass = $yrFail = $yrTol = $yrTot = 0;
foreach ($yPies as $mp) {
    $yrPass += (int) ($mp['pass'] ?? 0);
    $yrFail += (int) ($mp['fail'] ?? 0);
    $yrTol  += (int) ($mp['tol'] ?? 0);
    $yrTot  += (int) ($mp['total'] ?? 0);
}
$hdrColors = [
    1 => '#7e22ce', 2 => '#b91c1c', 3 => '#15803d', 4 => '#1d4ed8',
    5 => '#b45309', 6 => '#0f766e', 7 => '#4338ca', 8 => '#be123c',
    9 => '#047857', 10 => '#c2410c', 11 => '#0369a1', 12 => '#334155',
];

$renderPieSvg = static function (int $pass, int $fail, int $tol): string {
    $tot = $pass + $fail + $tol;
    $W = 316; $H = 204; $cx = 158; $cy = 104; $r = 66;
    if ($tot <= 0) {
        return '<svg class="yp-svg" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="No data">'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="2" stroke-dasharray="5 4"/>'
            . '<text x="' . $cx . '" y="' . ($cy + 4) . '" text-anchor="middle" font-size="12" font-weight="700" fill="#94a3b8">No data yet</text>'
            . '</svg>';
    }
    $slices = [
        ['label' => 'PASS',      'v' => $pass, 'color' => '#3b82f6'],
        ['label' => 'FAILED',    'v' => $fail, 'color' => '#f97316'],
        ['label' => 'TOLERABLE', 'v' => $tol,  'color' => '#94a3b8'],
    ];
    $paths = '';
    $callouts = '';
    $ang = -M_PI / 2; // start at 12 o'clock
    $nonZero = array_values(array_filter($slices, static fn ($s) => $s['v'] > 0));
    if (count($nonZero) === 1) {
        $s = $nonZero[0];
        $paths .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $s['color'] . '" stroke="#fff" stroke-width="1.5"/>';
        $callouts .= '<text x="' . $cx . '" y="' . ($cy + 4) . '" text-anchor="middle" font-size="12" font-weight="800" fill="#fff">' . $s['label'] . ' 100%</text>';
    } else {
        $bySide = ['left' => [], 'right' => []];
        foreach ($slices as $s) {
            if ($s['v'] <= 0) {
                continue;
            }
            $frac = $s['v'] / $tot;
            $sweep = $frac * 2 * M_PI;
            $end = $ang + $sweep;
            $x1 = $cx + $r * cos($ang);
            $y1 = $cy + $r * sin($ang);
            $x2 = $cx + $r * cos($end);
            $y2 = $cy + $r * sin($end);
            $large = $sweep > M_PI ? 1 : 0;
            $pct = (int) round($frac * 100);
            $paths .= sprintf(
                '<path d="M %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f Z" fill="%s" stroke="#ffffff" stroke-width="1.5"><title>%s: %d (%d%%)</title></path>',
                $cx, $cy, $x1, $y1, $r, $r, $large, $x2, $y2, $s['color'], $s['label'], $s['v'], $pct
            );

            $mid  = $ang + $sweep / 2;
            $side = cos($mid) >= 0 ? 'right' : 'left';
            $bySide[$side][] = [
                'lx1'   => $cx + ($r - 2) * cos($mid),
                'ly1'   => $cy + ($r - 2) * sin($mid),
                'lx2'   => $cx + ($r + 16) * cos($mid),
                'by'    => $cy + ($r + 16) * sin($mid),
                'label' => $s['label'],
                'color' => $s['color'],
                'pct'   => $pct,
            ];
            $ang = $end;
        }

        // Two-pass vertical collision resolution per side so callout boxes NEVER overlap
        $bw = 62;
        $bh = 26;
        $minGap = 32;
        $minY = 18;
        $maxY = $H - 18;
        foreach (['left', 'right'] as $side) {
            $items = $bySide[$side];
            if (! $items) {
                continue;
            }
            usort($items, static fn ($a, $b) => $a['by'] <=> $b['by']);
            // Forward pass (top to bottom)
            foreach ($items as $idx => &$it) {
                $it['by'] = max($minY, min($maxY, $it['by']));
                if ($idx > 0 && $it['by'] < $items[$idx - 1]['by'] + $minGap) {
                    $it['by'] = $items[$idx - 1]['by'] + $minGap;
                }
            }
            unset($it);
            // Backward pass (bottom to top)
            $cnt = count($items);
            if ($items[$cnt - 1]['by'] > $maxY) {
                $items[$cnt - 1]['by'] = $maxY;
                for ($idx = $cnt - 2; $idx >= 0; $idx--) {
                    if ($items[$idx]['by'] > $items[$idx + 1]['by'] - $minGap) {
                        $items[$idx]['by'] = $items[$idx + 1]['by'] - $minGap;
                    }
                }
            }

            $bx = $side === 'right' ? ($W - $bw - 6) : 6;
            $anchorX = $side === 'right' ? $bx : ($bx + $bw);
            foreach ($items as $it) {
                $by = $it['by'];
                $elbowX = $side === 'right' ? max($it['lx2'], $cx + $r + 10) : min($it['lx2'], $cx - $r - 10);
                $callouts .= sprintf(
                    '<polyline points="%.1f,%.1f %.1f,%.1f %.1f,%.1f" fill="none" stroke="#64748b" stroke-width="1"/>',
                    $it['lx1'], $it['ly1'], $elbowX, $by, $anchorX, $by
                );
                $callouts .= sprintf(
                    '<rect x="%.1f" y="%.1f" width="%d" height="%d" rx="4" fill="#ffffff" stroke="#94a3b8" stroke-width="1"/>',
                    $bx, $by - $bh / 2, $bw, $bh
                );
                $callouts .= sprintf(
                    '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="8.5" font-weight="800" fill="#0f172a">%s</text>',
                    $bx + $bw / 2, $by - 2, $it['label']
                );
                $callouts .= sprintf(
                    '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="9.5" font-weight="900" fill="%s">%d%%</text>',
                    $bx + $bw / 2, $by + 9, $it['color'] === '#94a3b8' ? '#475569' : $it['color'], $it['pct']
                );
            }
        }
    }
    return '<svg class="yp-svg" viewBox="0 0 ' . $W . ' ' . $H . '" role="img">' . $paths . $callouts . '</svg>';
};
?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/analytics-bonding_yearly_pies-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/analytics-bonding_yearly_pies-1.css') ?>">

<div class="yp-head">
    <div class="yp-head-l">
        <h2><?= $yYr ?> Monthly Summary Report</h2>
        <p><?= esc($ySub) ?></p>
    </div>
    <div class="yp-head-r">
        <span class="yp-pill">PASS: <?= number_format($yrPass) ?> (<?= $yrTot > 0 ? round($yrPass / $yrTot * 100, 1) : 0 ?>%)</span>
        <span class="yp-pill">FAILED: <?= number_format($yrFail) ?> (<?= $yrTot > 0 ? round($yrFail / $yrTot * 100, 1) : 0 ?>%)</span>
        <span class="yp-pill">TOLERABLE: <?= number_format($yrTol) ?> (<?= $yrTot > 0 ? round($yrTol / $yrTot * 100, 1) : 0 ?>%)</span>
        <span class="yp-pill">Total: <?= number_format($yrTot) ?></span>
        <button type="button" class="yp-back-btn" id="btn-ms-back-monthly">&larr; <?= esc($yBack) ?></button>
    </div>
</div>

<div class="yp-grid">
<?php foreach ($yPies as $mp):
    $mNo   = (int) $mp['month'];
    $mName = (string) $mp['name'];
    $pCnt  = (int) $mp['pass'];
    $fCnt  = (int) $mp['fail'];
    $tCnt  = (int) $mp['tol'];
    $totC  = (int) $mp['total'];
    $mFrom = sprintf('%04d-%02d-01', $yYr, $mNo);
    $mTo   = date('Y-m-t', strtotime($mFrom));
    $mUrl  = site_url('analytics') . '?' . $yQry . '&from=' . $mFrom . '&to=' . $mTo;
    $hCol  = $hdrColors[$mNo] ?? '#1e3a8a';
?>
    <div class="yp-card">
        <div class="yp-card-top">
            <a href="<?= esc($mUrl, 'attr') ?>" title="Open <?= esc($mName . ' ' . $yYr) ?> Monthly Summary"><?= esc($mName) ?> Summary</a>
        </div>
        <?= $renderPieSvg($pCnt, $fCnt, $tCnt) ?>
        <table class="yp-tbl">
            <thead>
                <tr class="yp-mhdr"><th colspan="2" style="background:<?= $hCol ?>"><?= esc(strtoupper($mName)) ?></th></tr>
                <tr class="yp-shdr"><th class="l">Status</th><th class="r">Count</th></tr>
            </thead>
            <tbody>
                <tr><td><i class="yp-dot" style="background:#3b82f6"></i>PASS</td><td class="r"><?= number_format($pCnt) ?></td></tr>
                <tr><td><i class="yp-dot" style="background:#f97316"></i>FAILED</td><td class="r"><?= number_format($fCnt) ?></td></tr>
                <tr><td><i class="yp-dot" style="background:#94a3b8"></i>TOLERABLE</td><td class="r"><?= number_format($tCnt) ?></td></tr>
                <tr class="yp-tot"><td>Total:</td><td class="r"><?= number_format($totC) ?></td></tr>
            </tbody>
        </table>
    </div>
<?php endforeach ?>
</div>
