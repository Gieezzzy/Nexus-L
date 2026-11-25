<?php
/**
 * Rivets Monitoring (RIVETS RESULT of the 2026 HARDWARE workbook) — included by analytics/index.php (?tab=bonding&view=pulltest).
 * Powered by dbexcelbackup.tbl_RIVETS (one row per colorway and month: Pass / Tolerable / Failed / Total qty) up to BondingAnalytics::RIVETS_EXCEL_UNTIL,
 * then live: tbl_dtr_BondingTracker (PullTestType = RIVET) with the result of tbl_dtr_pull_tests by LabRefNo.
 */
$pt          = $pullTest ?? [];
$year        = (int) ($pt['year'] ?? date('Y'));
$periodText  = (string) ($pt['periodText'] ?? (string) $year);
$missingNote = (string) ($pt['missingNote'] ?? '');
$total       = (int) ($pt['total'] ?? 0);
$pass        = (int) ($pt['pass'] ?? 0);
$tol         = (int) ($pt['tol'] ?? 0);
$fail        = (int) ($pt['fail'] ?? 0);
$blank       = (int) ($pt['blank'] ?? 0);
$passPct     = (float) ($pt['passPct'] ?? 0.0);
$tolPct      = (float) ($pt['tolPct'] ?? 0.0);
$failPct     = (float) ($pt['failPct'] ?? 0.0);
$monthly     = $pt['monthly'] ?? [];
$records     = $pt['records'] ?? [];

$ptJson = json_encode([
    'year'       => $year,
    'periodText' => $periodText,
    'months'     => array_map(static fn ($m) => ['no' => (int) $m['monthNo'], 'short' => $m['short'], 'full' => $m['full']], $monthly),
    'records'    => $records,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<style>
.ptm-wrap{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;min-width:0}
.ptm-wrap>*{min-width:0}

/* 3 Top-Gradient KPI Cards (uniform with Shoe Bonding / Whole Shoe / Materials) */
.ptm-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
@media(max-width:760px){.ptm-kpis{grid-template-columns:1fr}}
.ptm-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:15px;padding:16px 18px 14px;box-shadow:0 4px 14px rgba(15,23,42,.04);position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;gap:8px;transition:transform .16s ease,box-shadow .16s ease}
.ptm-kpi:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(15,23,42,.08)}
.ptm-kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:4px}
.ptm-kpi.k-pass::before{background:linear-gradient(90deg,#059669,#34d399)}
.ptm-kpi.k-tol::before{background:linear-gradient(90deg,#d97706,#fbbf24)}
.ptm-kpi.k-fail::before{background:linear-gradient(90deg,#dc2626,#f87171)}
.ptm-kpi-top{display:flex;justify-content:space-between;align-items:center;gap:8px}
.ptm-kpi-lbl{font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#64748b}
.ptm-kpi-ico{width:34px;height:34px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-size:15px;font-weight:900;flex-shrink:0}
.k-pass .ptm-kpi-ico{background:#ecfdf5;color:#059669}
.k-tol .ptm-kpi-ico{background:#fffbeb;color:#d97706}
.k-fail .ptm-kpi-ico{background:#fef2f2;color:#dc2626}
.ptm-kpi-valrow{display:flex;align-items:baseline;gap:9px;flex-wrap:wrap}
.ptm-kpi-val{font-size:29px;font-weight:900;line-height:1.05;color:#0f172a;font-variant-numeric:tabular-nums}
.k-pass .ptm-kpi-val{color:#059669}
.k-tol .ptm-kpi-val{color:#d97706}
.k-fail .ptm-kpi-val{color:#dc2626}
.ptm-kpi-pill{padding:2px 8px;border-radius:999px;font-size:11.5px;font-weight:800}
.k-pass .ptm-kpi-pill{background:#dcfce7;color:#15803d}
.k-tol .ptm-kpi-pill{background:#fef3c7;color:#b45309}
.k-fail .ptm-kpi-pill{background:#fee2e2;color:#b91c1c}
.ptm-kpi-bar{height:6px;border-radius:999px;background:#f1f5f9;overflow:hidden;display:flex}
.ptm-kpi-bar i{display:block;height:100%}
.ptm-kpi-sub{font-size:11.5px;color:#64748b;font-weight:600}

/* Sub-View Switcher Toolbar + Live Stat Strip */
.ptm-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:10px 14px;box-shadow:0 3px 12px rgba(15,23,42,.04)}
.ptm-tabs{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
.ptm-tab{border:1px solid #e2e8f0;background:#f8fafc;color:#475569;padding:7px 14px;border-radius:9px;font-size:12.5px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:all .15s ease}
.ptm-tab:hover{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
.ptm-tab.on{background:linear-gradient(135deg,#2563eb,#1d4ed8);border-color:#1d4ed8;color:#fff;box-shadow:0 3px 10px rgba(37,99,235,.28)}
.ptm-stats{display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:12px;font-weight:700;color:#475569;font-variant-numeric:tabular-nums}
.ptm-stats b{font-weight:900;color:#0f172a}
.ptm-stats .s-p{color:#059669}
.ptm-stats .s-t{color:#d97706}
.ptm-stats .s-f{color:#dc2626}
.ptm-stats .s-riv{padding:3px 9px;border-radius:999px;background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;font-size:11.5px;font-weight:800}

.ptm-pane[hidden]{display:none!important}

/* Pane 1: Full-Width Dark Navy Monthly Trend Card (matches NEXUS-L Analytics & Screenshot 1 without right sidebar) */
.ptm-dark-card{background:linear-gradient(155deg,#0f172a 0%,#172554 55%,#1e293b 100%);border:1px solid #1e293b;border-radius:16px;padding:18px 22px 14px;color:#fff;box-shadow:0 10px 28px rgba(15,23,42,.18);display:flex;flex-direction:column;gap:12px;min-width:0;overflow:hidden;position:relative}
.ptm-dark-hd{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
.ptm-dark-hd h3{margin:0;font-size:15px;font-weight:800;letter-spacing:.02em;color:#f8fafc;text-transform:uppercase}
.ptm-dark-hd p{margin:3px 0 0;font-size:11.5px;color:#94a3b8}
.ptm-dark-ft{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding-top:8px;border-top:1px solid rgba(148,163,184,.18);font-size:11.5px;font-weight:700;color:#cbd5e1}
.ptm-leg{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.ptm-leg span{display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
.ptm-leg i{width:10px;height:10px;border-radius:3px;display:inline-block}
.ptm-leg i.ln{width:18px;height:3px;border-radius:2px;background:#38bdf8}
.ptm-bar-col{cursor:pointer;transition:opacity .15s ease}
.ptm-bar-col:hover{opacity:.9}
.ptm-tip{position:fixed;z-index:999;pointer-events:none;background:rgba(15,23,42,.96);border:1px solid rgba(96,165,250,.45);border-radius:11px;padding:10px 13px;color:#f8fafc;font-size:12px;box-shadow:0 14px 32px rgba(2,6,23,.55);min-width:175px;display:none}
.ptm-tip h4{margin:0 0 6px;font-size:12.5px;font-weight:800;color:#fff;border-bottom:1px solid rgba(148,163,184,.2);padding-bottom:4px}
.ptm-tip-r{display:flex;justify-content:space-between;gap:14px;margin:2px 0;font-variant-numeric:tabular-nums}
.ptm-tip-r b{font-weight:800}

/* Pane 2: Colorways Donut Cards Grid (Screenshot 2 — upgraded for NEXUS-L Light & Dark) */
.ptm-cw-bar{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:11px 14px;box-shadow:0 3px 12px rgba(15,23,42,.04)}
.ptm-cw-filters{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ptm-sel,.ptm-inp{height:34px;padding:0 11px;border:1px solid #cbd5e1;border-radius:9px;background:#f8fafc;color:#0f172a;font-size:12px;font-weight:700;outline:none}
.ptm-inp{width:210px;font-weight:600}
.ptm-sel:focus,.ptm-inp:focus{border-color:#2563eb;background:#fff;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.ptm-cw-pg{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:#64748b;font-variant-numeric:tabular-nums}
.ptm-pg-btn{height:32px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;font-size:12px;font-weight:800;cursor:pointer}
.ptm-pg-btn:hover:not(:disabled){background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}
.ptm-pg-btn:disabled{opacity:.42;cursor:not-allowed}
.ptm-cw-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
@media(max-width:1180px){.ptm-cw-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:720px){.ptm-cw-grid{grid-template-columns:1fr}}
.ptm-cw-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(15,23,42,.05);display:flex;flex-direction:column;transition:transform .16s ease,box-shadow .16s ease}
.ptm-cw-card:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(15,23,42,.09)}
.ptm-cw-hd{padding:14px 16px 12px;border-bottom:1px solid #eef2f7;display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
.ptm-cw-hd h4{margin:0;font-size:13.5px;font-weight:800;color:#0f172a;line-height:1.25;word-break:break-word}
.ptm-cw-hd p{margin:3px 0 0;font-size:11px;font-weight:600;color:#64748b;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.ptm-fam-tag{padding:1px 7px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:800}
.ptm-rated-pill{padding:3px 9px;border-radius:999px;background:#f1f5f9;border:1px solid #e2e8f0;color:#334155;font-size:11px;font-weight:800;white-space:nowrap;flex-shrink:0}
.ptm-cw-bd{padding:14px 16px 16px;display:flex;flex-direction:column;gap:12px;flex:1;justify-content:space-between}
.ptm-share-list{display:flex;flex-direction:column;gap:8px}
.ptm-share-item{padding:8px 11px;border-radius:10px;border:1px solid #e2e8f0;background:#f8fafc}
.ptm-share-top{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:5px;font-size:12px;font-weight:800;color:#0f172a;font-variant-numeric:tabular-nums}
.ptm-share-top span.nm{display:inline-flex;align-items:center;gap:7px}
.ptm-share-top i{width:8px;height:8px;border-radius:50%;display:inline-block}
.ptm-share-track{height:6px;border-radius:999px;background:#e2e8f0;overflow:hidden}
.ptm-share-track>span{display:block;height:100%;border-radius:999px}

/* Pane 3 & 4: Lines & Defect Causes Executive Cards */
.ptm-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(15,23,42,.05);display:flex;flex-direction:column;min-width:0}
.ptm-navy-hd{background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);color:#fff;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.ptm-navy-hd h3{margin:0;font-size:14.5px;font-weight:800;color:#fff;letter-spacing:.01em}
.ptm-navy-hd p{margin:2px 0 0;font-size:11.5px;color:#cbd5e1}
.ptm-card-bd{padding:16px 18px;display:flex;flex-direction:column;gap:10px}
.ptm-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:stretch}
@media(max-width:1050px){.ptm-two{grid-template-columns:1fr}}
.ptm-row-box{padding:10px 12px;border-radius:11px;border:1px solid #e2e8f0;background:#f8fafc;display:flex;flex-direction:column;gap:6px}
.ptm-row-top{display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:12.5px;font-weight:800;color:#0f172a;flex-wrap:wrap}
.ptm-badges{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:800}
.ptm-bp{padding:2px 7px;border-radius:999px;background:#dcfce7;color:#15803d}
.ptm-bt{padding:2px 7px;border-radius:999px;background:#fef3c7;color:#b45309}
.ptm-bf{padding:2px 7px;border-radius:999px;background:#fee2e2;color:#b91c1c}
.ptm-stack-bar{display:flex;height:8px;border-radius:999px;overflow:hidden;background:#e2e8f0}
.ptm-stack-bar i{display:block;height:100%}
.ptm-tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:12px;font-variant-numeric:tabular-nums}
.ptm-tbl th:not([class*="luckysheet"]){padding:9px 10px;background:#f8fafc;color:#475569;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid #cbd5e1;text-align:left;white-space:nowrap}
.ptm-tbl th.c:not([class*="luckysheet"]),.ptm-tbl td.c:not([class*="luckysheet"]){text-align:center}
.ptm-tbl td:not([class*="luckysheet"]){padding:8px 10px;border-bottom:1px solid #eef2f7;color:#1e293b;vertical-align:middle}
.ptm-tbl tbody tr:hover td:not([class*="luckysheet"]){background:#f8fafc}
.ptm-empty{padding:40px 16px;text-align:center;color:#94a3b8;font-size:13px;font-weight:600}

/* Dark mode */
html[data-theme="dark"] .ptm-kpi,html[data-theme="dark"] .ptm-bar,html[data-theme="dark"] .ptm-cw-bar,html[data-theme="dark"] .ptm-cw-card,html[data-theme="dark"] .ptm-card{background:rgba(17,25,44,.84);border-color:rgba(148,163,184,.22)}
html[data-theme="dark"] .ptm-kpi-val,html[data-theme="dark"] .ptm-stats b,html[data-theme="dark"] .ptm-cw-hd h4,html[data-theme="dark"] .ptm-share-top,html[data-theme="dark"] .ptm-row-top{color:#f8fafc}
html[data-theme="dark"] .ptm-kpi-lbl,html[data-theme="dark"] .ptm-kpi-sub,html[data-theme="dark"] .ptm-stats,html[data-theme="dark"] .ptm-cw-hd p,html[data-theme="dark"] .ptm-cw-pg{color:#94a3b8}
html[data-theme="dark"] .ptm-tab{background:rgba(30,41,59,.75);border-color:rgba(148,163,184,.24);color:#cbd5e1}
html[data-theme="dark"] .ptm-tab:hover{background:rgba(37,99,235,.22);border-color:#60a5fa;color:#fff}
html[data-theme="dark"] .ptm-tab.on{background:linear-gradient(135deg,#2563eb,#1d4ed8);border-color:#2563eb;color:#fff}
html[data-theme="dark"] .ptm-sel,html[data-theme="dark"] .ptm-inp,html[data-theme="dark"] .ptm-pg-btn{background:#0f172a;border-color:rgba(148,163,184,.28);color:#f8fafc}
html[data-theme="dark"] .ptm-cw-hd{border-bottom-color:rgba(148,163,184,.18)}
html[data-theme="dark"] .ptm-rated-pill{background:rgba(30,41,59,.85);border-color:rgba(148,163,184,.22);color:#cbd5e1}
html[data-theme="dark"] .ptm-fam-tag{background:rgba(37,99,235,.25);color:#93c5fd}
html[data-theme="dark"] .ptm-share-item,html[data-theme="dark"] .ptm-row-box{background:rgba(15,23,42,.65);border-color:rgba(148,163,184,.2)}
html[data-theme="dark"] .ptm-kpi-bar,html[data-theme="dark"] .ptm-share-track,html[data-theme="dark"] .ptm-stack-bar{background:#334155}
html[data-theme="dark"] .ptm-stats .s-riv{background:rgba(16,185,129,.18);border-color:rgba(52,211,153,.35);color:#6ee7b7}
html[data-theme="dark"] .ptm-tbl th:not([class*="luckysheet"]){background:#0f172a;color:#cbd5e1;border-bottom-color:#334155}
html[data-theme="dark"] .ptm-tbl td:not([class*="luckysheet"]){color:#e2e8f0;border-bottom-color:#334155}
html[data-theme="dark"] .ptm-tbl tbody tr:hover td:not([class*="luckysheet"]){background:rgba(30,41,59,.6)}
</style>

<div class="ptm-wrap" id="ptm-root">
    <?php if ($total > 0 && function_exists('anSummary')): ?>
        <?= anSummary(
            number_format($passPct, 1) . '% of rivets passed (' . $periodText . ').',
            number_format($total) . ' rivet pieces tested: ' . number_format($pass) . ' passed, ' . number_format($tol) . ' were borderline (tolerable), ' . number_format($fail) . ' failed'
                . ($blank > 0 ? ' and ' . number_format($blank) . ' have no result' : '')
                . '. A pass rate of 95% or more is good; below 85% needs action.',
            anTone($passPct)
        ) ?>
    <?php endif ?>

    <!-- 3 Top-Gradient KPI Cards: Passed | Tolerable | Failed -->
    <div class="ptm-kpis">
        <div class="ptm-kpi k-pass">
            <div class="ptm-kpi-top">
                <span class="ptm-kpi-lbl">Passed Rivets</span>
                <span class="ptm-kpi-ico">&#10003;</span>
            </div>
            <div class="ptm-kpi-valrow">
                <span class="ptm-kpi-val"><?= number_format($pass) ?></span>
                <span class="ptm-kpi-pill"><?= number_format($passPct, 1) ?>%</span>
            </div>
            <div class="ptm-kpi-bar"><i style="width:<?= min(100, $passPct) ?>%;background:#10b981"></i></div>
            <span class="ptm-kpi-sub">Meets the VF / Vans rivet pull spec</span>
        </div>

        <div class="ptm-kpi k-tol">
            <div class="ptm-kpi-top">
                <span class="ptm-kpi-lbl">Tolerable</span>
                <span class="ptm-kpi-ico">&#9888;</span>
            </div>
            <div class="ptm-kpi-valrow">
                <span class="ptm-kpi-val"><?= number_format($tol) ?></span>
                <span class="ptm-kpi-pill"><?= number_format($tolPct, 1) ?>%</span>
            </div>
            <div class="ptm-kpi-bar"><i style="width:<?= min(100, max($tol > 0 ? 3 : 0, $tolPct)) ?>%;background:#f59e0b"></i></div>
            <span class="ptm-kpi-sub">Borderline peel / pressing — monitor closely</span>
        </div>

        <div class="ptm-kpi k-fail">
            <div class="ptm-kpi-top">
                <span class="ptm-kpi-lbl">Failed</span>
                <span class="ptm-kpi-ico">&#10005;</span>
            </div>
            <div class="ptm-kpi-valrow">
                <span class="ptm-kpi-val"><?= number_format($fail) ?></span>
                <span class="ptm-kpi-pill"><?= number_format($failPct, 1) ?>%</span>
            </div>
            <div class="ptm-kpi-bar"><i style="width:<?= min(100, max($fail > 0 ? 3 : 0, $failPct)) ?>%;background:#ef4444"></i></div>
            <span class="ptm-kpi-sub">Non-conforming &middot; requires corrective action</span>
        </div>
    </div>

    <!-- Sub-Tab Switcher (Monthly Trend | Colorways) + Summary Strip -->
    <div class="ptm-bar">
        <div class="ptm-tabs" role="tablist" aria-label="Rivets Monitoring views">
            <button type="button" class="ptm-tab on" data-ptm-tab="trend">Monthly Trend</button>
            <button type="button" class="ptm-tab" data-ptm-tab="colorways">Colorways</button>
        </div>
        <div class="ptm-stats">
            <span><b><?= number_format($total) ?></b> rivets</span>
            <span class="s-p"><b><?= number_format($passPct, 1) ?>%</b> Pass</span>
            <span class="s-t"><b><?= number_format($tolPct, 1) ?>%</b> Tol</span>
            <span class="s-f"><b><?= number_format($failPct, 1) ?>%</b> Fail</span>
        </div>
    </div>

    <!-- PANE 1: MONTHLY TREND (Full-width stacked bars + Pass % line) -->
    <div class="ptm-pane" data-ptm-pane="trend">
        <div class="ptm-dark-card">
            <div class="ptm-dark-hd">
                <div>
                    <h3>Monthly Rivets Trend &mdash; <?= esc($periodText) ?></h3>
                    <p>Stacked monthly volume (Pass, Tolerable, Failed, Blank) with Pass Rate (%) trend overlay</p>
                </div>
                <div class="ptm-leg">
                    <span><i style="background:#10b981"></i>Pass (<?= number_format($pass) ?>)</span>
                    <span><i style="background:#f59e0b"></i>Tolerable (<?= number_format($tol) ?>)</span>
                    <span><i style="background:#ef4444"></i>Failed (<?= number_format($fail) ?>)</span>
                    <?php if ($blank > 0): ?><span><i style="background:#94a3b8"></i>Blank (<?= number_format($blank) ?>)</span><?php endif ?>
                    <span><i class="ln"></i>Pass %</span>
                </div>
            </div>
            <?php if (! $monthly): ?>
                <div class="ptm-empty">No rivets records found in <code>dbexcelbackup.tbl_RIVETS</code> for the selected period.</div>
            <?php else:
                $W = 1040; $H = 360; $L = 48; $R = 54; $T = 30; $B = 38;
                $pw = $W - $L - $R; $ph = $H - $T - $B;
                $maxV = 20;
                foreach ($monthly as $mo) { $maxV = max($maxV, (int) $mo['total']); }
                $stepV = $maxV <= 60 ? 15 : ($maxV <= 120 ? 30 : 60);
                $maxV = (int) (ceil($maxV / $stepV) * $stepV);
                $nM   = count($monthly);
                $slot = $pw / max(1, $nM);
                $bw   = min(46, max(20, $slot * 0.44));

                $svg = '<defs>'
                    . '<linearGradient id="ptmGP" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#34d399"/><stop offset="100%" stop-color="#059669"/></linearGradient>'
                    . '<linearGradient id="ptmGT" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#fbbf24"/><stop offset="100%" stop-color="#d97706"/></linearGradient>'
                    . '<linearGradient id="ptmGF" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f87171"/><stop offset="100%" stop-color="#dc2626"/></linearGradient>'
                    . '<linearGradient id="ptmGB" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#94a3b8"/><stop offset="100%" stop-color="#64748b"/></linearGradient>'
                    . '</defs>';

                for ($gi = 0; $gi <= 4; $gi++) {
                    $gy = $T + $ph - ($gi / 4) * $ph;
                    $gv = round($gi / 4 * $maxV);
                    $gp = $gi * 25;
                    $svg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . round($gy, 1) . '" y2="' . round($gy, 1) . '" stroke="rgba(148,163,184,.18)" stroke-dasharray="4 4" stroke-width="1"/>';
                    $svg .= '<text x="' . ($L - 9) . '" y="' . round($gy + 4, 1) . '" text-anchor="end" fill="#94a3b8" font-size="11.5" font-weight="600">' . $gv . '</text>';
                    $svg .= '<text x="' . ($W - $R + 9) . '" y="' . round($gy + 4, 1) . '" fill="#7dd3fc" font-size="11" font-weight="700">' . $gp . '%</text>';
                }
                $svg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . ($T + $ph) . '" y2="' . ($T + $ph) . '" stroke="rgba(148,163,184,.35)" stroke-width="1.2"/>';

                $pts = []; $dots = '';
                foreach ($monthly as $idx => $mo) {
                    $cx   = $L + $slot * ($idx + 0.5);
                    $x0   = round($cx - $bw / 2, 1);
                    $curY = $T + $ph;
                    $totM = (int) $mo['total'];
                    $pCnt = (int) $mo['pass'];
                    $tCnt = (int) $mo['tol'];
                    $fCnt = (int) $mo['fail'];
                    $bCnt = (int) $mo['blank'];
                    $pr   = (float) $mo['passPct'];

                    $dataAttr = ' data-mo="' . esc($mo['full'] . ' ' . $year, 'attr') . '"'
                        . ' data-tot="' . $totM . '" data-p="' . $pCnt . '" data-t="' . $tCnt . '"'
                        . ' data-f="' . $fCnt . '" data-b="' . $bCnt . '" data-pr="' . number_format($pr, 1) . '"';

                    $svg .= '<g class="ptm-bar-col"' . $dataAttr . '>';
                    // Invisible full-slot hover hit area
                    $svg .= '<rect x="' . round($cx - $slot / 2, 1) . '" y="' . $T . '" width="' . round($slot, 1) . '" height="' . $ph . '" fill="transparent"/>';
                    foreach ([
                        [$pCnt, 'url(#ptmGP)'],
                        [$tCnt, 'url(#ptmGT)'],
                        [$fCnt, 'url(#ptmGF)'],
                        [$bCnt, 'url(#ptmGB)'],
                    ] as [$v, $fill]) {
                        if ($v <= 0) { continue; }
                        $sh = ($v / $maxV) * $ph;
                        $curY -= $sh;
                        $svg .= '<rect x="' . $x0 . '" y="' . round($curY, 1) . '" width="' . round($bw, 1) . '" height="' . round(max(2, $sh), 1) . '" rx="3" fill="' . $fill . '"/>';
                    }
                    $topY = $T + $ph - ($totM / $maxV) * $ph;
                    $py   = $T + $ph - (max(0, min(100, $pr)) / 100) * $ph;
                    $pts[] = round($cx, 1) . ',' . round($py, 1);

                    $totLblY = $topY - 8;
                    if (abs($py - $totLblY) < 16) {
                        $totLblY = $topY + 14;
                    }
                    $svg .= '<text x="' . round($cx, 1) . '" y="' . round($totLblY, 1) . '" text-anchor="middle" fill="#f8fafc" font-size="12" font-weight="800" style="paint-order:stroke;stroke:#0f172a;stroke-width:3px">' . $totM . '</text>';
                    $svg .= '<text x="' . round($cx, 1) . '" y="' . ($T + $ph + 22) . '" text-anchor="middle" fill="#cbd5e1" font-size="12" font-weight="700">' . esc($mo['short']) . '</text>';
                    $svg .= '</g>';

                    $lblY = ($py < $T + 20) ? ($py + 19) : ((abs($py - $totLblY) < 20 && $py >= $totLblY - 6) ? ($py + 19) : ($py - 11));
                    $dots .= '<circle cx="' . round($cx, 1) . '" cy="' . round($py, 1) . '" r="4.6" fill="#0f172a" stroke="#38bdf8" stroke-width="2.6" pointer-events="none"/>';
                    $dots .= '<rect x="' . round($cx - 20, 1) . '" y="' . round($lblY - 10, 1) . '" width="40" height="14" rx="4" fill="rgba(15,23,42,.9)" stroke="rgba(56,189,248,.45)" stroke-width="0.8" pointer-events="none"/>';
                    $dots .= '<text x="' . round($cx, 1) . '" y="' . round($lblY + 0.5, 1) . '" text-anchor="middle" fill="#7dd3fc" font-size="9.5" font-weight="800" pointer-events="none">' . number_format($pr, 1) . '%</text>';
                }
                if (count($pts) >= 2) {
                    $svg .= '<polyline fill="none" stroke="#38bdf8" stroke-width="2.8" stroke-linejoin="round" stroke-linecap="round" points="' . implode(' ', $pts) . '" pointer-events="none"/>';
                }
                $svg .= $dots;
            ?>
                <svg id="ptm-trend-svg" viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" style="display:block;font-family:Inter,system-ui,sans-serif;overflow:visible"><?= $svg ?></svg>
            <?php endif ?>
            <div class="ptm-dark-ft">
                <span>Source: <b>dbexcelbackup.tbl_RIVETS</b> up to <?= esc(date('M j', strtotime(\App\Services\Analytics\BondingAnalytics::RIVETS_EXCEL_UNTIL))) ?> &middot; then live <b>Lab Tracker</b> (PullTestType = RIVET, result from the pull tests)</span>
                <span><?= esc($periodText) ?><?= $missingNote !== '' ? ' (' . esc($missingNote) . ')' : '' ?> &middot; Hover any month bar for detailed breakdown</span>
            </div>
        </div>
    </div>

    <!-- PANE 2: COLORWAYS (3-Column Donut Cards matching Screenshot 2) -->
    <div class="ptm-pane" data-ptm-pane="colorways" hidden>
        <div class="ptm-cw-bar">
            <div class="ptm-cw-filters">
                <select id="ptm-cw-mo" class="ptm-sel" aria-label="Filter colorways by month">
                    <option value="">All Months (<?= esc($periodText) ?>)</option>
                    <?php foreach ($monthly as $mo): ?>
                        <option value="<?= (int) $mo['monthNo'] ?>"><?= esc($mo['full'] . ' ' . $year) ?> (<?= (int) $mo['total'] ?>)</option>
                    <?php endforeach ?>
                </select>
                <select id="ptm-cw-fam" class="ptm-sel" aria-label="Filter by colorway family">
                    <option value="">All Families</option>
                </select>
                <select id="ptm-cw-sort" class="ptm-sel" aria-label="Sort colorways">
                    <option value="qty">Sort: Rated Qty</option>
                    <option value="failpct">Sort: Highest Fail %</option>
                    <option value="failcnt">Sort: Most Fails</option>
                    <option value="passpct">Sort: Highest Pass %</option>
                    <option value="name">Sort: Colorway A–Z</option>
                </select>
                <input type="search" id="ptm-cw-q" class="ptm-inp" placeholder="Search colorway...">
                <select id="ptm-cw-size" class="ptm-sel" aria-label="Cards per page">
                    <option value="3">3 / page</option>
                    <option value="6" selected>6 / page</option>
                    <option value="9">9 / page</option>
                    <option value="0">All</option>
                </select>
            </div>
            <div class="ptm-cw-pg">
                <span id="ptm-cw-info">1–6</span>
                <button type="button" class="ptm-pg-btn" id="ptm-cw-prev">Prev</button>
                <button type="button" class="ptm-pg-btn" id="ptm-cw-next">Next</button>
            </div>
        </div>
        <div class="ptm-cw-grid" id="ptm-cw-grid"></div>
    </div>

</div>

<div class="ptm-tip" id="ptm-tip" role="tooltip"></div>

<script>
(function () {
  var DATA = <?= $ptJson ?>;

  // Sub-tab switcher
  var tabBtns = document.querySelectorAll('#ptm-root [data-ptm-tab]');
  var panes   = document.querySelectorAll('#ptm-root [data-ptm-pane]');
  var openTab = function (name) {
    tabBtns.forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-ptm-tab') === name); });
    panes.forEach(function (p) { p.hidden = p.getAttribute('data-ptm-pane') !== name; });
    if (name === 'colorways') renderColorways();
  };
  tabBtns.forEach(function (b) {
    b.addEventListener('click', function () { openTab(b.getAttribute('data-ptm-tab')); });
  });

  // Hover tooltip on Monthly Trend bars
  var tip = document.getElementById('ptm-tip');
  var cols = document.querySelectorAll('#ptm-trend-svg .ptm-bar-col');
  if (tip && cols.length) {
    cols.forEach(function (g) {
      g.addEventListener('mouseenter', function () {
        var b = parseInt(g.getAttribute('data-b') || '0', 10);
        tip.innerHTML = '<h4>' + g.getAttribute('data-mo') + '</h4>'
          + '<div class="ptm-tip-r"><span>Total Tests</span><b>' + g.getAttribute('data-tot') + '</b></div>'
          + '<div class="ptm-tip-r" style="color:#6ee7b7"><span>Passed</span><b>' + g.getAttribute('data-p') + ' (' + g.getAttribute('data-pr') + '%)</b></div>'
          + '<div class="ptm-tip-r" style="color:#fde68a"><span>Tolerable</span><b>' + g.getAttribute('data-t') + '</b></div>'
          + '<div class="ptm-tip-r" style="color:#fca5a5"><span>Failed</span><b>' + g.getAttribute('data-f') + '</b></div>'
          + (b > 0 ? '<div class="ptm-tip-r" style="color:#cbd5e1"><span>Blank</span><b>' + b + '</b></div>' : '');
        tip.style.display = 'block';
      });
      g.addEventListener('mousemove', function (e) {
        var x = e.clientX + 14, y = e.clientY - 12;
        if (x + 200 > window.innerWidth) x = e.clientX - 200;
        if (y + 150 > window.innerHeight) y = window.innerHeight - 160;
        tip.style.left = x + 'px';
        tip.style.top = Math.max(8, y) + 'px';
      });
      g.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
    });
  }

  // Pane 2: Colorways Donut Cards (interactive month, family, sort, search, pagination)
  var cwMo   = document.getElementById('ptm-cw-mo');
  var cwFam  = document.getElementById('ptm-cw-fam');
  var cwSort = document.getElementById('ptm-cw-sort');
  var cwQ    = document.getElementById('ptm-cw-q');
  var cwSize = document.getElementById('ptm-cw-size');
  var cwGrid = document.getElementById('ptm-cw-grid');
  var cwInfo = document.getElementById('ptm-cw-info');
  var cwPrev = document.getElementById('ptm-cw-prev');
  var cwNext = document.getElementById('ptm-cw-next');
  var cwPage = 1;

  // Populate Family dropdown with unique colorway counts
  if (cwFam) {
    var famCw = {};
    DATA.records.forEach(function (r) {
      if (!famCw[r.fam]) famCw[r.fam] = {};
      famCw[r.fam][r.cw] = 1;
    });
    var totalUniq = Object.keys(DATA.records.reduce(function (acc, r) { acc[r.cw] = 1; return acc; }, {})).length;
    cwFam.options[0].textContent = 'All Families (' + totalUniq + ')';
    Object.keys(famCw).sort(function (a, b) {
      return Object.keys(famCw[b]).length - Object.keys(famCw[a]).length || a.localeCompare(b);
    }).forEach(function (fam) {
      var opt = document.createElement('option');
      opt.value = fam;
      opt.textContent = fam + ' (' + Object.keys(famCw[fam]).length + ')';
      cwFam.appendChild(opt);
    });
  }

  var escHtml = function (s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  var buildDonut = function (pass, tol, fail, rated, passPct) {
    var r = 64, c = 2 * Math.PI * r, off = 0;
    var svg = '<svg viewBox="0 0 176 176" width="166" height="166" style="display:block;margin:2px auto;font-family:Inter,system-ui,sans-serif">'
      + '<circle cx="88" cy="88" r="' + r + '" fill="none" stroke="rgba(148,163,184,.22)" stroke-width="22"/>';
    if (rated > 0) {
      [[pass, '#10b981', 'Passed'], [tol, '#f59e0b', 'Tolerable'], [fail, '#ef4444', 'Failed']].forEach(function (s) {
        if (s[0] <= 0) return;
        var len = (s[0] / rated) * c;
        svg += '<circle cx="88" cy="88" r="' + r + '" fill="none" stroke="' + s[1] + '" stroke-width="22" stroke-dasharray="' + len.toFixed(2) + ' ' + (c - len).toFixed(2) + '" stroke-dashoffset="' + (-off).toFixed(2) + '" transform="rotate(-90 88 88)"><title>' + s[2] + ': ' + s[0] + '</title></circle>';
        off += len;
      });
    }
    svg += '<text x="88" y="86" text-anchor="middle" font-size="24" font-weight="900" fill="currentColor">' + (rated > 0 ? passPct.toFixed(1) + '%' : '—') + '</text>';
    svg += '<text x="88" y="104" text-anchor="middle" font-size="9.5" font-weight="800" letter-spacing=".06em" fill="#64748b">PASS RATE</text></svg>';
    return svg;
  };

  var renderColorways = function () {
    if (!cwGrid) return;
    var moVal  = cwMo ? cwMo.value : '';
    var famVal = cwFam ? cwFam.value : '';
    var sortBy = cwSort ? cwSort.value : 'qty';
    var q      = (cwQ ? cwQ.value : '').trim().toLowerCase();
    var pSize  = cwSize ? (parseInt(cwSize.value, 10) || 0) : 6;
    var moLabel = (cwMo && cwMo.value && cwMo.selectedOptions[0]) ? cwMo.selectedOptions[0].textContent.replace(/\s*\(\d+\)$/, '') : DATA.periodText;

    var map = {};
    DATA.records.forEach(function (r) {
      if (moVal && String(r.m) !== moVal) return;
      if (famVal && r.fam !== famVal) return;
      if (q && r.cw.toLowerCase().indexOf(q) === -1 && r.fam.toLowerCase().indexOf(q) === -1) return;
      if (!map[r.cw]) map[r.cw] = { cw: r.cw, fam: r.fam, pass: 0, tol: 0, fail: 0, rated: 0, total: 0 };
      var it = map[r.cw];
      it.total += r.n;
      it.pass  += r.p;
      it.tol   += r.t;
      it.fail  += r.f;
      it.rated += r.p + r.t + r.f;
    });

    var list = Object.keys(map).map(function (k) {
      var it = map[k];
      it.passPct = it.rated > 0 ? (it.pass / it.rated * 100) : 0;
      it.tolPct  = it.rated > 0 ? (it.tol / it.rated * 100) : 0;
      it.failPct = it.rated > 0 ? (it.fail / it.rated * 100) : 0;
      return it;
    });

    list.sort(function (a, b) {
      if (sortBy === 'failpct') return (b.failPct - a.failPct) || (b.fail - a.fail) || (b.rated - a.rated);
      if (sortBy === 'failcnt') return (b.fail - a.fail) || (b.failPct - a.failPct) || (b.rated - a.rated);
      if (sortBy === 'passpct') return (b.passPct - a.passPct) || (b.rated - a.rated);
      if (sortBy === 'name')    return a.cw.localeCompare(b.cw);
      return (b.rated - a.rated) || (b.total - a.total) || a.cw.localeCompare(b.cw);
    });

    var total = list.length;
    var pages = pSize > 0 ? Math.max(1, Math.ceil(total / pSize)) : 1;
    if (cwPage > pages) cwPage = pages;
    if (cwPage < 1) cwPage = 1;
    var start = pSize > 0 ? (cwPage - 1) * pSize : 0;
    var end   = pSize > 0 ? Math.min(total, start + pSize) : total;
    var slice = list.slice(start, end);

    if (cwInfo) cwInfo.textContent = total === 0 ? '0 of 0' : ((start + 1) + '–' + end + ' of ' + total);
    if (cwPrev) cwPrev.disabled = (cwPage <= 1 || total === 0);
    if (cwNext) cwNext.disabled = (cwPage >= pages || total === 0);

    if (!slice.length) {
      cwGrid.innerHTML = '<div class="ptm-card" style="grid-column:1/-1"><div class="ptm-empty">No colorways match your current filter.</div></div>';
      return;
    }

    cwGrid.innerHTML = slice.map(function (it) {
      var shareRow = function (lbl, val, pct, col) {
        var w = Math.min(100, Math.max(val > 0 ? 3 : 0, pct));
        return '<div class="ptm-share-item">'
          + '<div class="ptm-share-top"><span class="nm"><i style="background:' + col + '"></i>' + lbl + '</span>'
          + '<span>' + val.toLocaleString() + ' <span style="color:#64748b;font-weight:700">(' + pct.toFixed(1) + '%)</span></span></div>'
          + '<div class="ptm-share-track"><span style="width:' + w.toFixed(1) + '%;background:' + col + '"></span></div>'
          + '</div>';
      };
      return '<div class="ptm-cw-card">'
        + '<div class="ptm-cw-hd"><div><h4>' + escHtml(it.cw) + '</h4>'
        + '<p><span>' + escHtml(moLabel) + '</span><span class="ptm-fam-tag">' + escHtml(it.fam) + '</span></p></div>'
        + '<span class="ptm-rated-pill">' + it.rated.toLocaleString() + ' Rated</span></div>'
        + '<div class="ptm-cw-bd">'
        + buildDonut(it.pass, it.tol, it.fail, it.rated, it.passPct)
        + '<div class="ptm-share-list">'
        + shareRow('Passed', it.pass, it.passPct, '#10b981')
        + shareRow('Tolerable', it.tol, it.tolPct, '#f59e0b')
        + shareRow('Failed', it.fail, it.failPct, '#ef4444')
        + '</div></div></div>';
    }).join('');
  };

  [cwMo, cwFam, cwSort, cwSize].forEach(function (el) {
    if (el) el.addEventListener('change', function () { cwPage = 1; renderColorways(); });
  });
  if (cwQ) cwQ.addEventListener('input', function () { cwPage = 1; renderColorways(); });
  if (cwPrev) cwPrev.addEventListener('click', function () { if (cwPage > 1) { cwPage--; renderColorways(); } });
  if (cwNext) cwNext.addEventListener('click', function () { cwPage++; renderColorways(); });

})();
</script>
