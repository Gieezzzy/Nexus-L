<?php
/**
 * Material Test Analytics — Pass / Tolerable / Fail Executive Presentation (included by analytics/index.php).
 * Powered by dbexcelbackup.tbl_materialtesting (sheet_kind = 'DATA', Jan–Nov).
 */
$ma         = $matAudit ?? [];
$year       = (int) ($ma['year'] ?? date('Y'));
$periodText = (string) ($ma['periodText'] ?? "January – November {$year}");
$spanText   = (string) ($ma['monthSpanText'] ?? "Jan – Nov {$year}");
$tot        = (int) ($ma['total'] ?? 0);
$pass       = (int) ($ma['pass'] ?? 0);
$tol        = (int) ($ma['tol'] ?? 0);
$fail       = (int) ($ma['fail'] ?? 0);
$passPct    = (float) ($ma['passPct'] ?? 0);
$tolPct     = (float) ($ma['tolPct'] ?? 0);
$failPct    = (float) ($ma['failPct'] ?? 0);
$retestCnt  = (int) ($ma['retestCount'] ?? 0);
$monthly    = $ma['monthly'] ?? [];
$params     = $ma['params'] ?? [];
$suppliers  = $ma['suppliers'] ?? [];
$failLog    = $ma['failLog'] ?? [];
$tolLog     = $ma['tolLog'] ?? [];
$bestMonth  = $ma['bestMonth'] ?? null;
$peakMonth  = $ma['peakMonth'] ?? null;
?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/analytics-materials_audit-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/analytics-materials_audit-1.css') ?>">

<div class="mta-wrap">
<?php if (($view ?? 'analytics') === 'hotspots'): ?>
    <!-- Dedicated Tab: Top Physical Test Parameters & Supplier Quality Scorecard -->
    <div class="mta-two">
        <div class="mta-card">
            <div class="mta-navy-hd">
                <div>
                    <h3>Top Physical Test Parameters (Pass / Tolerable / Fail)<?= anTip('A parameter is one thing measured on the material in the lab. Each row below is one of those measurements.') ?></h3>
                    <p>Parameters with the highest Tolerable and Failed occurrences &middot; <?= esc($periodText) ?></p>
                </div>
                <span style="padding:3px 9px;border-radius:999px;background:rgba(255,255,255,.15);font-size:11px;font-weight:700"><?= count($params) ?> Shown</span>
            </div>
            <div class="mta-card-bd" style="gap:8px;justify-content:flex-start">
                <?php if (! $params): ?>
                    <p class="muted sm" style="margin:18px 0;text-align:center">No parameter-level test details recorded in this period.</p>
                <?php else: foreach ($params as $pm):
                    $pn = max(1, (int) $pm['total']);
                    $pp = round((int) $pm['pass'] / $pn * 100, 1);
                    $tp = round((int) $pm['tol'] / $pn * 100, 1);
                    $fp = round((int) $pm['fail'] / $pn * 100, 1);
                ?>
                    <div class="mta-hot-row">
                        <div class="mta-hot-top">
                            <span class="mta-hot-nm" title="<?= esc($pm['name'], 'attr') ?>"><?= esc($pm['name']) ?> <span style="color:#64748b;font-weight:600">(n=<?= number_format((int) $pm['total']) ?>)</span></span>
                            <span class="mta-hot-badges">
                                <span class="mta-b-p"><?= number_format($pp, 1) ?>% P</span>
                                <?php if ((int) $pm['tol'] > 0): ?><span class="mta-b-t"><?= (int) $pm['tol'] ?> T</span><?php endif ?>
                                <?php if ((int) $pm['fail'] > 0): ?><span class="mta-b-f"><?= (int) $pm['fail'] ?> F</span><?php endif ?>
                            </span>
                        </div>
                        <div class="mta-hot-bar" title="Pass <?= (int) $pm['pass'] ?> · Tolerable <?= (int) $pm['tol'] ?> · Failed <?= (int) $pm['fail'] ?>">
                            <i style="width:<?= $pp ?>%;background:#10b981"></i>
                            <i style="width:<?= $tp ?>%;background:#f59e0b"></i>
                            <i style="width:<?= $fp ?>%;background:#ef4444"></i>
                        </div>
                    </div>
                <?php endforeach; endif ?>
            </div>
        </div>

        <div class="mta-card">
            <div class="mta-navy-hd">
                <div>
                    <h3>Supplier Quality Scorecard (Pass / Tolerable / Fail)<?= anTip('A scorecard is a report card: one row per supplier showing how many of their batches passed, were tolerable or failed.') ?></h3>
                    <p>Suppliers ranked by Tolerable &amp; Failed material batches &middot; <?= esc($periodText) ?></p>
                    <p class="an-cap">The suppliers at the top have the most problem batches. Start your supplier follow-up there.</p>
                </div>
                <span style="padding:3px 9px;border-radius:999px;background:rgba(255,255,255,.15);font-size:11px;font-weight:700">Top <?= count($suppliers) ?></span>
            </div>
            <div class="mta-card-bd" style="gap:8px;justify-content:flex-start">
                <?php if (! $suppliers): ?>
                    <p class="muted sm" style="margin:18px 0;text-align:center">No supplier records found in this period.</p>
                <?php else: foreach ($suppliers as $sp):
                    $sn = max(1, (int) $sp['total']);
                    $pp = round((int) $sp['pass'] / $sn * 100, 1);
                    $tp = round((int) $sp['tol'] / $sn * 100, 1);
                    $fp = round((int) $sp['fail'] / $sn * 100, 1);
                ?>
                    <div class="mta-hot-row">
                        <div class="mta-hot-top">
                            <span class="mta-hot-nm" title="<?= esc($sp['name'], 'attr') ?>"><?= esc($sp['name']) ?> <span style="color:#64748b;font-weight:600">(<?= number_format((int) $sp['total']) ?>)</span></span>
                            <span class="mta-hot-badges">
                                <span class="mta-b-p"><?= (int) $sp['pass'] ?> P</span>
                                <?php if ((int) $sp['tol'] > 0): ?><span class="mta-b-t"><?= (int) $sp['tol'] ?> T</span><?php endif ?>
                                <?php if ((int) $sp['fail'] > 0): ?><span class="mta-b-f"><?= (int) $sp['fail'] ?> F</span><?php endif ?>
                            </span>
                        </div>
                        <div class="mta-hot-bar" title="Pass <?= $pp ?>% · Tolerable <?= $tp ?>% · Failed <?= $fp ?>%">
                            <i style="width:<?= $pp ?>%;background:#10b981"></i>
                            <i style="width:<?= $tp ?>%;background:#f59e0b"></i>
                            <i style="width:<?= $fp ?>%;background:#ef4444"></i>
                        </div>
                    </div>
                <?php endforeach; endif ?>
            </div>
        </div>
    </div>
<?php elseif (($view ?? 'analytics') === 'batches'): ?>
    <!-- Solo Tab: Failed & Tolerable Material Batches -->
    <?php if ($failLog || $tolLog): ?>
    <div class="mta-card" id="mta-log-card">
        <div class="mta-navy-hd">
            <div>
                <h3>Failed &amp; Tolerable Material Batches</h3>
                <p>Drill-down record for executive review &mdash; switch tabs or search by supplier, Lab Ref, or material &middot; <?= esc($periodText) ?></p>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <input type="search" id="mta-log-search" class="mta-log-search" placeholder="Filter supplier, lab ref, material...">
                <div class="mta-log-tabs">
                    <button type="button" class="mta-log-tab <?= $failLog ? 'on' : '' ?>" data-mta-tab="fail">Failed <span class="cnt"><?= count($failLog) ?></span></button>
                    <button type="button" class="mta-log-tab <?= ! $failLog ? 'on' : '' ?>" data-mta-tab="tol">Tolerable <span class="cnt"><?= count($tolLog) ?></span></button>
                </div>
            </div>
        </div>
        <div class="mta-log-scroll" style="max-height:70vh">
            <table class="mta-log-tbl">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Lab Ref No.</th>
                        <th>Supplier</th>
                        <th>Material Description</th>
                        <th>Flagged Test(s)</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody id="mta-log-body"></tbody>
            </table>
        </div>
    </div>
    <script>
    (function () {
        var DATA = {
            fail: <?= json_encode($failLog) ?>,
            tol:  <?= json_encode($tolLog) ?>
        };
        var curTab = DATA.fail.length ? 'fail' : 'tol';
        var tbody  = document.getElementById('mta-log-body');
        var search = document.getElementById('mta-log-search');
        var card   = document.getElementById('mta-log-card');
        if (!tbody || !card) return;

        function esc(s) {
            return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function render() {
            var q = (search ? search.value : '').trim().toLowerCase();
            var rows = (DATA[curTab] || []).filter(function (r) {
                if (!q) return true;
                return (r.date + ' ' + r.labNo + ' ' + r.supplier + ' ' + r.material + ' ' + (r.tests || []).join(' ')).toLowerCase().indexOf(q) !== -1;
            });
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#64748b;font-weight:600">No matching ' + (curTab === 'fail' ? 'failed' : 'tolerable') + ' material records.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(function (r) {
                var chips = (r.tests && r.tests.length)
                    ? r.tests.map(function (t) { return '<span class="mta-param-chip">' + esc(t) + '</span>'; }).join('')
                    : '<span style="color:#94a3b8;font-size:11px">—</span>';
                var pill = curTab === 'fail'
                    ? '<span class="mta-tag-fail">' + esc(r.remark || 'FAILED') + '</span>'
                    : '<span class="mta-tag-tol">' + esc(r.remark || 'TOLERABLE') + '</span>';
                return '<tr>'
                    + '<td style="white-space:nowrap;font-weight:700">' + esc(r.date || r.month) + '</td>'
                    + '<td style="white-space:nowrap"><span class="mta-ref-code">' + esc(r.labNo || '—') + '</span></td>'
                    + '<td style="white-space:nowrap;font-weight:700">' + esc(r.supplier || '—') + '</td>'
                    + '<td style="min-width:220px;max-width:360px">' + esc(r.material || '—') + '</td>'
                    + '<td style="min-width:150px">' + chips + '</td>'
                    + '<td style="white-space:nowrap">' + pill + '</td>'
                    + '</tr>';
            }).join('');
        }
        card.querySelectorAll('[data-mta-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                curTab = btn.getAttribute('data-mta-tab');
                card.querySelectorAll('[data-mta-tab]').forEach(function (b) { b.classList.toggle('on', b === btn); });
                render();
            });
        });
        if (search) search.addEventListener('input', render);
        render();
    })();
    </script>
    <?php else: ?>
    <div class="mta-card"><div class="mta-card-bd"><p class="muted sm" style="margin:18px 0;text-align:center">No failed or tolerable material batches recorded in <?= esc($periodText) ?>.</p></div></div>
    <?php endif ?>
<?php else: ?>
    <div id="ms-yearly-pane" hidden>
        <?php
        $ypSubtitle   = 'Whole-year material test status distribution (PASS, FAILED, TOLERABLE) across all 12 months — click any month title to open its breakdown';
        $ypBackLabel  = 'Back to Material Analytics';
        $ypMonthQuery = 'tab=materials&view=analytics&group=month';
        include __DIR__ . '/bonding_yearly_pies.php';
        ?>
    </div>
    <div id="ms-monthly-pane">
    <!-- 3 KPI Cards: Passed | Tolerable | Failed -->
    <?php if ($tot > 0): ?>
        <?= anSummary(
            number_format($passPct, 1) . '% of material batches passed their tests (' . $periodText . ').',
            number_format($tot) . ' batches were tested: ' . number_format($pass) . ' passed, ' . number_format($tol) . ' were borderline (tolerable) and ' . number_format($fail) . ' failed.'
                . ($bestMonth || $peakMonth ? ' Scroll down for the suppliers and tests that fail most.' : '')
                . ' A pass rate of 95% or more is good; below 85% needs action.',
            anTone($passPct)
        ) ?>
    <?php endif ?>
    <div class="mta-kpis">
        <div class="mta-kpi k-tot">
            <div class="mta-kpi-top">
                <span class="mta-kpi-lbl">Total Tested</span>
                <span class="mta-kpi-ico">&#931;</span>
            </div>
            <div class="mta-kpi-valrow">
                <span class="mta-kpi-val"><?= number_format($tot) ?></span>
                <?php if ($retestCnt > 0): ?><span class="mta-kpi-pill"><?= number_format($retestCnt) ?> retests</span><?php endif ?>
            </div>
            <div class="mta-kpi-bar"><i style="width:100%;background:#3b82f6"></i></div>
            <span class="mta-kpi-sub">All material tests evaluated in this period</span>
        </div>

        <div class="mta-kpi k-pass">
            <div class="mta-kpi-top">
                <span class="mta-kpi-lbl">Passed</span>
                <span class="mta-kpi-ico">&#10003;</span>
            </div>
            <div class="mta-kpi-valrow">
                <span class="mta-kpi-val"><?= number_format($pass) ?></span>
                <span class="mta-kpi-pill"><?= number_format($passPct, 1) ?>%</span>
            </div>
            <div class="mta-kpi-bar"><i style="width:<?= min(100, $passPct) ?>%;background:#10b981"></i></div>
            <span class="mta-kpi-sub">Meets VF / Vans material specification</span>
        </div>

        <div class="mta-kpi k-tol">
            <div class="mta-kpi-top">
                <span class="mta-kpi-lbl">Tolerable</span>
                <span class="mta-kpi-ico">&#9888;</span>
            </div>
            <div class="mta-kpi-valrow">
                <span class="mta-kpi-val"><?= number_format($tol) ?></span>
                <span class="mta-kpi-pill"><?= number_format($tolPct, 1) ?>%</span>
            </div>
            <div class="mta-kpi-bar"><i style="width:<?= min(100, max($tol > 0 ? 3 : 0, $tolPct)) ?>%;background:#f59e0b"></i></div>
            <span class="mta-kpi-sub">Within acceptable tolerance band</span>
        </div>

        <div class="mta-kpi k-fail">
            <div class="mta-kpi-top">
                <span class="mta-kpi-lbl">Failed</span>
                <span class="mta-kpi-ico">&#10005;</span>
            </div>
            <div class="mta-kpi-valrow">
                <span class="mta-kpi-val"><?= number_format($fail) ?></span>
                <span class="mta-kpi-pill"><?= number_format($failPct, 1) ?>%</span>
            </div>
            <div class="mta-kpi-bar"><i style="width:<?= min(100, max($fail > 0 ? 3 : 0, $failPct)) ?>%;background:#ef4444"></i></div>
            <span class="mta-kpi-sub">Non-conforming &middot; requires corrective action</span>
        </div>
    </div>

    <!-- Row 1: Current Month Summary Donut + Monthly Pass/Tolerable/Fail Trend -->
    <?php
    // Left donut card shows the current month's summary (or the selected month when filtered)
    $curMonthNo = (int) date('n');
    $curMo = null;
    foreach ($monthly as $moItem) {
        if ((int) $moItem['monthNo'] === $curMonthNo) {
            $curMo = $moItem;
            break;
        }
    }
    if ($curMo === null && $monthly) {
        $curMo = end($monthly);
    }
    $cmName    = $curMo ? (string) $curMo['full'] : date('F');
    $cmTot     = $curMo ? (int) $curMo['total'] : 0;
    $cmPass    = $curMo ? (int) $curMo['pass'] : 0;
    $cmTol     = $curMo ? (int) $curMo['tol'] : 0;
    $cmFail    = $curMo ? (int) $curMo['fail'] : 0;
    $cmPassPct = $curMo ? (float) $curMo['passPct'] : 0.0;
    $cmTolPct  = $curMo ? (float) $curMo['tolPct'] : 0.0;
    $cmFailPct = $curMo ? (float) $curMo['failPct'] : 0.0;
    ?>
    <div class="mta-main-grid">
        <!-- Left: Current Month Donut Ring -->
        <div class="mta-card">
            <div class="mta-card-hd">
                <div>
                    <h3><?= esc($cmName) ?> Summary</h3>
                    <p>Pass, Tolerable, and Fail share for <?= esc($cmName) ?> <?= esc($year) ?></p>
                </div>
                <span style="font-size:11px;font-weight:800;color:#475569;background:#f1f5f9;padding:3px 9px;border-radius:999px;white-space:nowrap"><?= number_format($cmTot) ?> Tested</span>
            </div>
            <div class="mta-card-bd">
                <?php
                $dR = 72; $dC = 2 * M_PI * $dR; $dOff = 0;
                $dSvg = '<svg viewBox="0 0 190 190" width="188" height="188" style="display:block;margin:2px auto;font-family:Inter,system-ui,sans-serif">';
                $dSvg .= '<circle cx="95" cy="95" r="' . $dR . '" fill="none" stroke="#e2e8f0" stroke-width="24"/>';
                if ($cmTot > 0) {
                    foreach ([['Passed', $cmPass, '#10b981'], ['Tolerable', $cmTol, '#f59e0b'], ['Failed', $cmFail, '#ef4444']] as [$nm, $val, $col]) {
                        if ($val <= 0) { continue; }
                        $len = ($val / $cmTot) * $dC;
                        $dSvg .= '<circle cx="95" cy="95" r="' . $dR . '" fill="none" stroke="' . $col . '" stroke-width="24" stroke-dasharray="' . round($len, 2) . ' ' . round($dC - $len, 2) . '" stroke-dashoffset="' . round(-$dOff, 2) . '" transform="rotate(-90 95 95)"><title>' . esc($cmName) . ' · ' . $nm . ': ' . number_format($val) . ' (' . round($val / $cmTot * 100, 1) . '%)</title></circle>';
                        $dOff += $len;
                    }
                }
                $dSvg .= '<text x="95" y="92" text-anchor="middle" font-size="25" font-weight="900" fill="currentColor">' . number_format($cmPassPct, 1) . '%</text>';
                $dSvg .= '<text x="95" y="111" text-anchor="middle" font-size="10.5" font-weight="800" letter-spacing=".06em" fill="#64748b">PASS RATE</text>';
                $dSvg .= '</svg>';
                echo $dSvg;
                ?>
                <div class="mta-share-list">
                    <?php foreach ([
                        ['Passed',    $cmPass, $cmPassPct, '#10b981'],
                        ['Tolerable', $cmTol,  $cmTolPct,  '#f59e0b'],
                        ['Failed',    $cmFail, $cmFailPct, '#ef4444'],
                    ] as [$nm, $val, $pctV, $col]): ?>
                    <div class="mta-share-item">
                        <div class="mta-share-top">
                            <span class="nm"><i style="background:<?= $col ?>"></i><?= $nm ?></span>
                            <span><?= number_format($val) ?> <span style="color:#64748b;font-weight:700">(<?= number_format($pctV, 1) ?>%)</span></span>
                        </div>
                        <div class="mta-share-track"><span style="width:<?= min(100, max($val > 0 ? 2 : 0, $pctV)) ?>%;background:<?= $col ?>"></span></div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <!-- Right: Dark Monthly Volume + Pass Rate Line -->
        <div class="mta-dark-card">
            <div class="mta-dark-hd">
                <div>
                    <h3>Monthly Pass / Tolerable / Fail Trend</h3>
                    <p>Stacked monthly material test volume with Pass Rate (%) trend overlay</p>
                    <p class="an-cap">Each bar is one month. The line is the pass rate: going up is good, going down means more problems.</p>
                </div>
                <div class="mta-leg">
                    <span><i style="background:#10b981"></i>Passed</span>
                    <span><i style="background:#f59e0b"></i>Tolerable</span>
                    <span><i style="background:#ef4444"></i>Failed</span>
                    <span><i class="ln"></i>Pass Rate (%)</span>
                </div>
            </div>
            <?php if (! $monthly): ?>
                <div style="padding:56px 16px;text-align:center;color:#94a3b8;font-weight:600">No material test records in this period.</div>
            <?php else:
                $W = 860; $H = 330; $L = 46; $R = 52; $T = 28; $B = 38;
                $pw = $W - $L - $R; $ph = $H - $T - $B;
                $maxV = 20;
                $minRate = 100.0;
                foreach ($monthly as $mo) {
                    $maxV = max($maxV, (int) $mo['total']);
                    if ((int) $mo['total'] > 0) {
                        $minRate = min($minRate, (float) $mo['passPct']);
                    }
                }
                $maxV = (int) (ceil($maxV / 50) * 50);
                if ($maxV < 50) {
                    $maxV = (int) (ceil($maxV / 10) * 10);
                }
                $rateFloor = max(0, (int) (floor(($minRate - 5) / 10) * 10));
                if ($rateFloor > 80) {
                    $rateFloor = 80;
                }
                $rateSpan = max(10, 100 - $rateFloor);
                $nM   = count($monthly);
                $slot = $pw / max(1, $nM);
                $bw   = min(40, max(18, $slot * 0.54));

                $svg = '<defs>'
                    . '<linearGradient id="mtaGP" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#34d399"/><stop offset="100%" stop-color="#059669"/></linearGradient>'
                    . '<linearGradient id="mtaGT" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#fbbf24"/><stop offset="100%" stop-color="#d97706"/></linearGradient>'
                    . '<linearGradient id="mtaGF" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f87171"/><stop offset="100%" stop-color="#dc2626"/></linearGradient>'
                    . '</defs>';

                for ($gi = 0; $gi <= 5; $gi++) {
                    $gy = $T + $ph - ($gi / 5) * $ph;
                    $gv = round($gi / 5 * $maxV);
                    $gr = round($rateFloor + ($gi / 5) * $rateSpan);
                    $svg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . round($gy, 1) . '" y2="' . round($gy, 1) . '" stroke="rgba(148,163,184,.18)" stroke-dasharray="4 4" stroke-width="1"/>';
                    $svg .= '<text x="' . ($L - 8) . '" y="' . round($gy + 4, 1) . '" text-anchor="end" fill="#94a3b8" font-size="11" font-weight="600">' . $gv . '</text>';
                    $svg .= '<text x="' . ($W - $R + 8) . '" y="' . round($gy + 4, 1) . '" fill="#7dd3fc" font-size="10.5" font-weight="700">' . $gr . '%</text>';
                }
                $svg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . ($T + $ph) . '" y2="' . ($T + $ph) . '" stroke="rgba(148,163,184,.35)" stroke-width="1.2"/>';

                $linePts = [];
                $dotsSvg = '';
                foreach ($monthly as $idx => $mo) {
                    $cx   = $L + $slot * ($idx + 0.5);
                    $x0   = round($cx - $bw / 2, 1);
                    $curY = $T + $ph;
                    foreach ([
                        ['pass', 'url(#mtaGP)', 'Passed'],
                        ['tol',  'url(#mtaGT)', 'Tolerable'],
                        ['fail', 'url(#mtaGF)', 'Failed'],
                    ] as [$k, $fill, $lbl]) {
                        $val = (int) ($mo[$k] ?? 0);
                        if ($val <= 0) { continue; }
                        $sh = ($val / $maxV) * $ph;
                        $curY -= $sh;
                        $svg .= '<rect x="' . $x0 . '" y="' . round($curY, 1) . '" width="' . round($bw, 1) . '" height="' . round(max(2, $sh), 1) . '" rx="2.5" fill="' . $fill . '"><title>' . esc($mo['full']) . ' · ' . $lbl . ': ' . $val . '</title></rect>';
                    }
                    $totM = (int) $mo['total'];
                    if ($totM > 0) {
                        $topY = $T + $ph - ($totM / $maxV) * $ph;
                        $svg .= '<text x="' . round($cx, 1) . '" y="' . round($topY - 7, 1) . '" text-anchor="middle" fill="#f8fafc" font-size="11" font-weight="800">' . $totM . '</text>';
                        $pr = (float) $mo['passPct'];
                        $py = $T + $ph - (max(0, min($rateSpan, $pr - $rateFloor)) / $rateSpan) * $ph;
                        $linePts[] = round($cx, 1) . ',' . round($py, 1);
                        $lblY = (abs($py - ($topY - 7)) < 18 && $py >= $topY - 12) ? ($py + 18) : ($py - 10);
                        $dotsSvg .= '<circle cx="' . round($cx, 1) . '" cy="' . round($py, 1) . '" r="4.2" fill="#0f172a" stroke="#38bdf8" stroke-width="2.4"><title>' . esc($mo['full']) . ' Pass Rate: ' . number_format($pr, 1) . '%</title></circle>';
                        $dotsSvg .= '<rect x="' . round($cx - 19, 1) . '" y="' . round($lblY - 10, 1) . '" width="38" height="13.5" rx="4" fill="rgba(15,23,42,.85)" stroke="rgba(56,189,248,.35)" stroke-width="0.7"/>';
                        $dotsSvg .= '<text x="' . round($cx, 1) . '" y="' . round($lblY, 1) . '" text-anchor="middle" fill="#7dd3fc" font-size="9.5" font-weight="800">' . number_format($pr, 1) . '%</text>';
                    }
                    $svg .= '<text x="' . round($cx, 1) . '" y="' . ($T + $ph + 20) . '" text-anchor="middle" fill="#cbd5e1" font-size="11.5" font-weight="700">' . esc($mo['short']) . '</text>';
                }
                if (count($linePts) >= 2) {
                    $svg .= '<polyline fill="none" stroke="#38bdf8" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round" points="' . implode(' ', $linePts) . '"/>';
                }
                $svg .= $dotsSvg;
            ?>
                <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" style="display:block;font-family:Inter,system-ui,sans-serif;overflow:visible"><?= $svg ?></svg>
            <?php endif ?>
        </div>
    </div>

    <!-- Row 2: Monthly Pass / Tolerable / Fail Scorecard Table -->
    <?php if ($monthly): ?>
    <div class="mta-card">
        <div class="mta-navy-hd">
            <div>
                <h3>Monthly Pass / Tolerable / Fail Scorecard (<?= esc($periodText) ?>)</h3>
                <p>Month-by-month material test disposition counts, share percentages, and quality pass rates</p>
            </div>
            <span style="padding:4px 11px;border-radius:999px;background:rgba(255,255,255,.15);font-size:11.5px;font-weight:800"><?= count($monthly) ?> Month<?= count($monthly) === 1 ? '' : 's' ?></span>
        </div>
        <div class="mta-tbl-wrap">
            <table class="mta-tbl">
                <thead>
                    <tr>
                        <th class="l">Month</th>
                        <th>Total Tested</th>
                        <th>Passed</th>
                        <th>Tolerable</th>
                        <th>Failed</th>
                        <th>Pass / Tolerable / Fail Split</th>
                        <th>Pass Rate</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($monthly as $mo):
                    $mTot = (int) $mo['total'];
                    $mP   = (int) $mo['pass'];
                    $mT   = (int) $mo['tol'];
                    $mF   = (int) $mo['fail'];
                    $pP   = (float) $mo['passPct'];
                    $tP   = (float) $mo['tolPct'];
                    $fP   = (float) $mo['failPct'];
                    $rCls = $pP >= 95 ? 'mta-rate-hi' : ($pP >= 90 ? 'mta-rate-mid' : 'mta-rate-lo');
                ?>
                    <tr>
                        <td class="l"><?= esc($mo['full']) ?> <?= esc($year) ?></td>
                        <td><b><?= number_format($mTot) ?></b></td>
                        <td><span class="mta-num-g"><?= number_format($mP) ?></span><span class="mta-sub-pct">(<?= number_format($pP, 1) ?>%)</span></td>
                        <td>
                            <?php if ($mT > 0): ?>
                                <span class="mta-num-a"><?= number_format($mT) ?></span><span class="mta-sub-pct">(<?= number_format($tP, 1) ?>%)</span>
                            <?php else: ?>
                                <span class="mta-num-z">0</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if ($mF > 0): ?>
                                <span class="mta-num-r"><?= number_format($mF) ?></span><span class="mta-sub-pct">(<?= number_format($fP, 1) ?>%)</span>
                            <?php else: ?>
                                <span class="mta-num-z">0</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <div class="mta-ratio-bar" title="Pass <?= $pP ?>% · Tolerable <?= $tP ?>% · Failed <?= $fP ?>%">
                                <i style="width:<?= $pP ?>%;background:#10b981"></i>
                                <i style="width:<?= $tP ?>%;background:#f59e0b"></i>
                                <i style="width:<?= $fP ?>%;background:#ef4444"></i>
                            </div>
                        </td>
                        <td><span class="mta-rate-pill <?= $rCls ?>"><?= number_format($pP, 1) ?>%</span></td>
                    </tr>
                <?php endforeach ?>
                    <tr class="mta-tot">
                        <td class="l">TOTAL (<?= esc($spanText) ?>)</td>
                        <td><?= number_format($tot) ?></td>
                        <td><span class="mta-num-g"><?= number_format($pass) ?></span><span class="mta-sub-pct">(<?= number_format($passPct, 1) ?>%)</span></td>
                        <td><span class="mta-num-a"><?= number_format($tol) ?></span><span class="mta-sub-pct">(<?= number_format($tolPct, 1) ?>%)</span></td>
                        <td><span class="mta-num-r"><?= number_format($fail) ?></span><span class="mta-sub-pct">(<?= number_format($failPct, 1) ?>%)</span></td>
                        <td>
                            <div class="mta-ratio-bar">
                                <i style="width:<?= $passPct ?>%;background:#10b981"></i>
                                <i style="width:<?= $tolPct ?>%;background:#f59e0b"></i>
                                <i style="width:<?= $failPct ?>%;background:#ef4444"></i>
                            </div>
                        </td>
                        <td><span class="mta-rate-pill <?= $passPct >= 95 ? 'mta-rate-hi' : ($passPct >= 90 ? 'mta-rate-mid' : 'mta-rate-lo') ?>"><?= number_format($passPct, 1) ?>%</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif ?>
    </div>
    <script>
    (function () {
      var btn = document.getElementById('btn-ms-yearly');
      var back = document.getElementById('btn-ms-back-monthly');
      var yp = document.getElementById('ms-yearly-pane');
      var mp = document.getElementById('ms-monthly-pane');
      if (!btn || !yp || !mp) return;
      var setYearly = function (on) {
        yp.hidden = !on;
        mp.hidden = on;
        btn.style.background = on ? 'linear-gradient(135deg,#2563eb,#1d4ed8)' : '';
        btn.style.borderColor = on ? '#1d4ed8' : '';
        btn.style.color = on ? '#fff' : '';
      };
      btn.addEventListener('click', function () { setYearly(yp.hidden); });
      if (back) back.addEventListener('click', function () { setYearly(false); });
      if (new URLSearchParams(window.location.search).get('yearly') === '1') setYearly(true);
    })();
    </script>
<?php endif ?>
</div>
