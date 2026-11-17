<?php
$ov = $overall ?? ['tested' => 0, 'pass' => 0, 'fail' => 0, 'tol' => 0, 'pend' => 0, 'shut' => 0];
$totTested = (int) $ov['tested'];
$totPass   = (int) $ov['pass'];
$totFail   = (int) $ov['fail'];
$totTol    = (int) $ov['tol'];
$passRate  = $totTested > 0 ? round($totPass / $totTested * 100, 1) : 0.0;
$failRate  = $totTested > 0 ? round($totFail / $totTested * 100, 2) : 0.0;

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
<style>
.bd-wrap{display:flex;flex-direction:column;gap:16px}
.bd-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
@media (max-width:1180px){.bd-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:600px){.bd-kpis{grid-template-columns:1fr}}
.bd-kpis .ms-kpi-card{min-width:0;padding:13px 15px}
.bd-kpis .ms-kpi-card>div{min-width:0}
.bd-bldg-row{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin:2px 0}
.bd-bldg-item{display:inline-flex;align-items:baseline;gap:5px;white-space:nowrap;font-size:13px;font-weight:700;color:#475569}
.bd-bldg-item b{font-size:19px !important;margin:0 !important}
.bd-pill{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;padding:0 5px;border-radius:5px;font-size:10.5px;font-weight:800;letter-spacing:.02em}
.bd-p{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.bd-f{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
.bd-t{background:#fef3c7;color:#b45309;border:1px solid #fde68a}
.bd-sd{background:#e2e8f0;color:#475569;border:1px solid #cbd5e1}
.bd-e{color:#cbd5e1;font-weight:600}

.bd-grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
@media (max-width:1024px){.bd-grid2{grid-template-columns:1fr}}
.bd-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px 20px;box-shadow:0 4px 14px rgba(15,23,42,.04);min-width:0}
.bd-card-hd{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap}
.bd-card-hd h3{margin:0;font-size:15px;font-weight:800;color:#0f172a}
.bd-card-hd p{margin:3px 0 0;font-size:12px;color:#64748b}

.bd-bar-list{display:flex;flex-direction:column;gap:10px}
.bd-bar-row{display:flex;flex-direction:column;gap:4px}
.bd-bar-top{display:flex;justify-content:space-between;align-items:baseline;font-size:12.5px;font-weight:700;color:#1e293b;gap:8px}
.bd-bar-hint{font-size:11px;color:#64748b;font-weight:500}
.bd-track{height:9px;background:#f1f5f9;border-radius:999px;overflow:hidden}
.bd-fill{height:100%;border-radius:999px;transition:width .35s ease}

.bd-sup-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:14px}
.bd-sup-box{border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;background:#f8fafc}
.bd-sup-box .nm{font-size:13.5px;font-weight:800;color:#0f172a;margin-bottom:6px}
.bd-sup-nums{display:flex;justify-content:space-between;font-size:12px;color:#475569;margin-bottom:6px}
.bd-sup-pct{font-size:20px;font-weight:900;color:#dc2626}

.bd-perf-split{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
@media (max-width:640px){.bd-perf-split{grid-template-columns:1fr}}
.bd-perf-box{border-radius:12px;padding:11px 13px;border:1px solid #e2e8f0}
.bd-perf-box.top{background:#f0fdf4;border-color:#bbf7d0}
.bd-perf-box.low{background:#fef2f2;border-color:#fecaca}
.bd-perf-box h4{margin:0 0 8px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.03em}
.bd-perf-box.top h4{color:#15803d}
.bd-perf-box.low h4{color:#b91c1c}
.bd-tag-row{display:flex;flex-wrap:wrap;gap:6px}
.bd-ltag{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px solid rgba(15,23,42,.1);border-radius:8px;padding:4px 8px;font-size:11.5px;font-weight:700;color:#0f172a}

.bd-rtbl{width:100%;border-collapse:collapse;font-size:12px}
.bd-rtbl th{background:#f8fafc;color:#475569;font-weight:800;font-size:11px;text-transform:uppercase;letter-spacing:.03em;padding:8px 10px;text-align:left;border-bottom:1px solid #e2e8f0}
.bd-rtbl td{padding:7px 10px;border-bottom:1px solid #f1f5f9;color:#1e293b;font-weight:600}
.bd-rtbl tr:hover td{background:#f8fafc}
.bd-rank-b{display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:22px;border-radius:6px;font-size:11px;font-weight:800;background:#eff6ff;color:#1d4ed8}
.bd-rank-b.gold{background:#dcfce7;color:#15803d}
.bd-rank-b.warn{background:#fee2e2;color:#b91c1c}

.bd-mx-controls{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.bd-seg{display:inline-flex;background:#f1f5f9;padding:3px;border-radius:10px;gap:2px}
.bd-seg button{border:0;background:transparent;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:700;color:#475569;cursor:pointer}
.bd-seg button.on{background:#1d4ed8;color:#fff;box-shadow:0 2px 6px rgba(29,78,216,.25)}

.bd-mx-scroll{overflow-x:auto;border:1px solid #cbd5e1;border-radius:12px}
.bd-mx{border-collapse:collapse;width:100%;font-size:11px;white-space:nowrap}
.bd-mx th,.bd-mx td{border-right:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;padding:5px 4px;text-align:center}
.bd-mx th{background:#0f172a;color:#f8fafc;font-weight:800;font-size:10.5px}
.bd-mx tr.subhd th{background:#1e293b;color:#cbd5e1;font-size:10px;padding:4px}
.bd-mx td.dt{background:#f8fafc;font-weight:700;color:#0f172a;padding:5px 8px;text-align:left}
.bd-mx td.dow{background:#f8fafc;font-weight:700;color:#475569;padding:5px 6px}
.bd-mx tr.row-fail td.dt{background:#fef2f2;color:#991b1b}
.bd-mx tr.sum-pass td{background:#f0fdf4;font-weight:800;color:#15803d}
.bd-mx tr.sum-fail td{background:#fef2f2;font-weight:800;color:#b91c1c}
.bd-mx tr.sum-tot td{background:#f1f5f9;font-weight:800;color:#0f172a}

/* Liquid Glass Dark Mode Overrides */
html[data-theme="dark"] .bd-card{background:rgba(17,25,44,.76);border-color:rgba(148,163,184,.22);box-shadow:inset 1px 1px 0 rgba(255,255,255,.1),0 8px 28px rgba(2,6,23,.4);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
html[data-theme="dark"] .bd-card-hd h3{color:#f1f5f9}
html[data-theme="dark"] .bd-card-hd p{color:#94a3b8}
html[data-theme="dark"] .bd-bar-top{color:#e2e8f0}
html[data-theme="dark"] .bd-bar-hint{color:#94a3b8}
html[data-theme="dark"] .bd-track{background:rgba(30,41,59,.85);border:1px solid rgba(148,163,184,.15)}
html[data-theme="dark"] .bd-sup-box{background:rgba(15,23,42,.55);border-color:rgba(148,163,184,.2)}
html[data-theme="dark"] .bd-sup-box .nm{color:#f1f5f9}
html[data-theme="dark"] .bd-sup-nums{color:#94a3b8}
html[data-theme="dark"] .bd-sup-pct{color:#f87171}
html[data-theme="dark"] .bd-perf-box.top{background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.28)}
html[data-theme="dark"] .bd-perf-box.low{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.28)}
html[data-theme="dark"] .bd-perf-box.top h4{color:#6ee7b7}
html[data-theme="dark"] .bd-perf-box.low h4{color:#fca5a5}
html[data-theme="dark"] .bd-ltag{background:rgba(15,23,42,.72);border-color:rgba(148,163,184,.24);color:#e2e8f0}
html[data-theme="dark"] .bd-rtbl th{background:rgba(15,23,42,.75);color:#cbd5e1;border-bottom-color:rgba(148,163,184,.2)}
html[data-theme="dark"] .bd-rtbl td{color:#e2e8f0;border-bottom-color:rgba(148,163,184,.14)}
html[data-theme="dark"] .bd-rtbl tr:hover td{background:rgba(51,65,85,.35)}
html[data-theme="dark"] .bd-rank-b.gold{background:rgba(16,185,129,.18);color:#6ee7b7}
html[data-theme="dark"] .bd-rank-b.warn{background:rgba(239,68,68,.18);color:#fca5a5}
html[data-theme="dark"] .bd-seg{background:rgba(15,23,42,.68);border:1px solid rgba(148,163,184,.2)}
html[data-theme="dark"] .bd-seg button{color:#cbd5e1}
html[data-theme="dark"] .bd-seg button:hover{color:#f8fafc}
html[data-theme="dark"] .bd-seg button.on{background:#2563eb;color:#fff;box-shadow:0 2px 8px rgba(37,99,235,.4)}
html[data-theme="dark"] .bd-mx-scroll{border-color:rgba(148,163,184,.22);background:rgba(11,18,34,.55)}
html[data-theme="dark"] .bd-mx th,html[data-theme="dark"] .bd-mx td{border-right-color:rgba(148,163,184,.16);border-bottom-color:rgba(148,163,184,.16)}
html[data-theme="dark"] .bd-mx th{background:rgba(15,23,42,.92);color:#e2e8f0}
html[data-theme="dark"] .bd-mx tr.subhd th{background:rgba(30,41,59,.88);color:#cbd5e1}
html[data-theme="dark"] .bd-mx td.dt{background:rgba(15,23,42,.72);color:#f1f5f9}
html[data-theme="dark"] .bd-mx td.dow{background:rgba(15,23,42,.6);color:#94a3b8}
html[data-theme="dark"] .bd-mx tr.row-fail td.dt{background:rgba(127,29,29,.35);color:#fca5a5}
html[data-theme="dark"] .bd-mx tr.sum-pass td{background:rgba(6,78,59,.35);color:#6ee7b7}
html[data-theme="dark"] .bd-mx tr.sum-fail td{background:rgba(127,29,29,.35);color:#fca5a5}
html[data-theme="dark"] .bd-mx tr.sum-tot td{background:rgba(30,41,59,.78);color:#f1f5f9}
html[data-theme="dark"] .bd-p{background:rgba(16,185,129,.18);color:#6ee7b7;border-color:rgba(16,185,129,.32)}
html[data-theme="dark"] .bd-f{background:rgba(239,68,68,.18);color:#fca5a5;border-color:rgba(239,68,68,.32)}
html[data-theme="dark"] .bd-t{background:rgba(245,158,11,.18);color:#fcd34d;border-color:rgba(245,158,11,.32)}
html[data-theme="dark"] .bd-sd{background:rgba(100,116,139,.25);color:#cbd5e1;border-color:rgba(148,163,184,.3)}
html[data-theme="dark"] .bd-e{color:#475569}
html[data-theme="dark"] .bd-wrap .ms-kpi-card{background:rgba(15,23,42,.65);border-color:rgba(148,163,184,.2);box-shadow:inset 1px 1px 0 rgba(255,255,255,.08)}
html[data-theme="dark"] .bd-wrap .ms-kpi-card small{color:#94a3b8}
html[data-theme="dark"] .bd-wrap .ms-kpi-card b{color:#f1f5f9}
html[data-theme="dark"] .bd-wrap .ms-kpi-card span.sub{color:#94a3b8}
html[data-theme="dark"] .bd-wrap .ms-kpi-card b[style*="#0f172a"]{color:#f1f5f9!important}
html[data-theme="dark"] .bd-wrap .ms-kpi-card b[style*="#dc2626"]{color:#f87171!important}
html[data-theme="dark"] .bd-wrap div[style*="color:#475569"]{color:#94a3b8!important}
html[data-theme="dark"] .bd-wrap td[style*="color:#334155"]{color:#cbd5e1!important}
html[data-theme="dark"] .bd-wrap td[style*="color:#15803d"],html[data-theme="dark"] .bd-wrap b[style*="color:#15803d"]{color:#6ee7b7!important}
html[data-theme="dark"] .bd-wrap b[style*="color:#dc2626"],html[data-theme="dark"] .bd-wrap span[style*="color:#dc2626"]{color:#fca5a5!important}
</style>

<div class="bd-wrap">
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

    <!-- Failure Portions & Root Causes -->
    <div class="bd-card">
        <div class="bd-card-hd">
            <div>
                <h3>Where &amp; Why Shoes Failed</h3>
                <p>Shows which shoe bond layer separated and the root cause behind each failure.</p>
            </div>
        </div>
        <div class="bd-grid2">
            <div>
                <?php $pMax = max(1, (int) max($portionCounts ?: [1])); ?>
                <div style="font-size:11.5px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.03em;margin-bottom:8px">By Shoe Portion (Layer)</div>
                <div class="bd-bar-list">
                    <?php foreach ($portionCounts as $pName => $pCnt): if ($pName === 'Unspecified' && $pCnt === 0) continue; ?>
                        <div class="bd-bar-row">
                            <div class="bd-bar-top">
                                <span><?= esc($pName) ?></span>
                                <span><?= (int) $pCnt ?> <?= $totFail > 0 ? '(' . round($pCnt / $totFail * 100) . '%)' : '' ?></span>
                            </div>
                            <div class="bd-track"><div class="bd-fill" style="width:<?= round($pCnt / $pMax * 100) ?>%;background:#2563eb"></div></div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
            <div>
                <div style="font-size:11.5px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.03em;margin-bottom:8px">By Root Cause</div>
                <?php if (empty($causeCounts)): ?>
                    <p class="muted sm" style="margin:0">No failure root causes recorded in this period.</p>
                <?php else: ?>
                    <?php $cMax = max(1, (int) max($causeCounts)); ?>
                    <div class="bd-bar-list">
                        <?php foreach (array_slice($causeCounts, 0, 8, true) as $cName => $cCnt): ?>
                            <div class="bd-bar-row">
                                <div class="bd-bar-top">
                                    <span><?= esc($cName) ?> <?php if (isset($causeHints[$cName])): ?><span class="bd-bar-hint">&mdash; <?= esc($causeHints[$cName]) ?></span><?php endif ?></span>
                                    <span style="color:#dc2626"><?= (int) $cCnt ?></span>
                                </div>
                                <div class="bd-track"><div class="bd-fill" style="width:<?= round($cCnt / $cMax * 100) ?>%;background:#ef4444"></div></div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <!-- Building A & Building B Line Performance & Rankings -->
    <div class="bd-grid2">
        <?php foreach (['A' => 'Building A (Lines A1–A12)', 'B' => 'Building B (Lines B1–B14)'] as $bKey => $bTitle): ?>
            <?php $rk = $rankings[$bKey] ?? ['lines' => [], 'top' => [], 'low' => []]; ?>
            <div class="bd-card">
                <div class="bd-card-hd">
                    <div>
                        <h3><?= esc($bTitle) ?> &mdash; Line Rankings</h3>
                        <p>Top performing lines vs. lines needing attention, ranked by Pass Score.</p>
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
