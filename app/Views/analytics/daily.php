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
$subTabUrl = static function (string $vk) use ($areaTab, $period, $year, $asof, $weekVal, $selMonth): string {
    if (in_array($vk, ['workload', 'encoding'], true)) {
        $q = ['tab' => $areaTab, 'view' => $vk, 'period' => $period, 'year' => $year];
        if ($period === 'weekly') {
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
<style>
.dw{display:grid;gap:12px;min-width:0;padding-bottom:24px}.dw *{box-sizing:border-box}
.dw-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;border-bottom:1px solid #e2e8f0;padding-bottom:10px}
.dw-tabs{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.dw-tab{display:inline-flex;align-items:center;gap:7px;padding:7px 14px;border-radius:10px;border:1px solid transparent;background:transparent;color:#334155;font:inherit;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;transition:all .15s ease}
.dw-tab svg{width:16px;height:16px;flex:none;color:#475569}
.dw-tab:hover{background:#f1f5f9;color:#0f172a}
.dw-tab.dw-tab-on{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8;font-weight:700}
.dw-tab.dw-tab-on svg{color:#2563eb}
.dw-ctl{display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;background:#fff;border:1px solid #dbe3ee;border-radius:10px;padding:4px 8px;box-shadow:0 1px 2px rgba(15,23,42,.04);margin-left:auto}
.dw-ctl label{display:inline-flex;align-items:center;gap:6px;font-size:10.5px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
.dw-ctl select,.dw-ctl input[type=date],.dw-ctl input[type=week]{height:30px;padding:0 8px;border:1px solid #cbd5e1;border-radius:7px;background:#f8fafc;font-size:12px;font-weight:700;color:#0f172a;cursor:pointer}
.dw-mode{display:inline-flex;align-items:center;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;padding:2px;gap:2px}
.dw-mode a{display:inline-flex;align-items:center;justify-content:center;height:24px;padding:0 10px;border-radius:6px;font-size:11.5px;font-weight:700;color:#475569;text-decoration:none;transition:all .15s}
.dw-mode a:hover{color:#0f172a}
.dw-mode a.on{background:#2563eb;color:#fff;box-shadow:0 1px 2px rgba(37,99,235,.25)}
.dw-nav{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
.dw-nav a{display:inline-flex;align-items:center;justify-content:center;height:30px;padding:0 10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#334155;font-size:12px;font-weight:700;text-decoration:none;transition:all .15s}
.dw-nav a:hover{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}

/* each stage is a tab: only the blocks of the open stage are shown */
.dw-stage-pane{display:grid;gap:12px;min-width:0}
.dw-stage-pane[hidden]{display:none !important}

/* Executive KPI Cards (Clickable) */
.dw-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
@media(max-width:1180px){.dw-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.dw-kpis{grid-template-columns:1fr}}
.dw-kpi{background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:14px 16px;box-shadow:0 1px 3px rgba(15,23,42,.04);display:flex;flex-direction:column;justify-content:space-between;gap:6px;position:relative;overflow:hidden;cursor:pointer;text-align:left;width:100%;transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease,background-color .16s ease}
.dw-kpi::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--kc,#3b82f6)}
.dw-kpi:hover{transform:translateY(-2px);border-color:var(--kc,#3b82f6);box-shadow:0 8px 18px rgba(15,23,42,.08)}
.dw-kpi.is-active{border-color:var(--kc,#3b82f6);background:#f8fafc;box-shadow:0 0 0 3px rgba(37,99,235,.14)}
.dw-kpi-top{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%}
.dw-kpi-top small{font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.04em}
.dw-kpi-tag{font-size:10.5px;font-weight:800;padding:2px 8px;border-radius:99px;background:#f1f5f9;color:#334155}
.dw-kpi b{font-size:26px;font-weight:900;color:#0f172a;line-height:1.1;letter-spacing:-.02em}
.dw-kpi-sub{font-size:11.5px;color:#64748b;line-height:1.35;display:flex;align-items:center;justify-content:space-between;gap:6px}
.dw-kpi-jump{font-size:10.5px;font-weight:800;color:#2563eb;white-space:nowrap;opacity:.85}
.dw-kpi:hover .dw-kpi-jump{opacity:1;text-decoration:underline}
.dw-kpi-bar{height:6px;border-radius:99px;background:#e2e8f0;overflow:hidden;margin-top:2px;width:100%}
.dw-kpi-bar i{display:block;height:100%;border-radius:99px;background:var(--kc,#3b82f6)}

/* Spotlight pulse when scrolled to / filtered via card click */
@keyframes dwPulseRing {
  0% { box-shadow: 0 0 0 0 rgba(37,99,235,.45); }
  70% { box-shadow: 0 0 0 10px rgba(37,99,235,0); }
  100% { box-shadow: 0 0 0 0 rgba(37,99,235,0); }
}
.dw-card.dw-focus{border-color:#2563eb;animation:dwPulseRing 1.1s ease-out}
.dw-tbl tr.dw-row-hi td,.dw-tbl td.dw-col-hi,.dw-tbl th.dw-col-hi{background:#eff6ff !important}
.dw-tbl tr.dw-row-warn td{background:#fffbeb !important}

/* Two-column layout */
.dw-grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:start}
@media(max-width:1180px){.dw-grid2{grid-template-columns:1fr}}
.dw-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 4px 14px rgba(15,23,42,.04);overflow:hidden;min-width:0}
.dw-card-h{padding:13px 16px;background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#fff;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.dw-card-h-title{font-size:13px;font-weight:800;letter-spacing:.03em;text-transform:uppercase}
.dw-card-h-sub{font-size:11.5px;color:#cbd5e1;font-weight:500;margin-top:2px}
.dw-card-explain{padding:9px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12px;color:#475569;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}

/* Charts */
.dw-chart{background:linear-gradient(145deg,#0f172a,#1e293b);padding:14px 14px 10px;border-bottom:1px solid #e2e8f0}
.dw-chart-title{color:#f8fafc;font-size:12px;font-weight:700;text-align:center;margin-bottom:6px;letter-spacing:.02em}
.dw-chart svg{display:block;width:100%;height:auto;font-family:Inter,system-ui,sans-serif}
.dw-leg{display:flex;flex-wrap:wrap;gap:8px 16px;justify-content:center;padding:6px 0 2px;font-size:11px;color:#e2e8f0;font-weight:600}
.dw-leg span{display:inline-flex;align-items:center;gap:6px}
.dw-leg i{width:10px;height:10px;border-radius:3px;display:inline-block}
.dw-leg i.ln{height:3px;width:16px}

/* Tables */
.dw-tbl-wrap{overflow-x:auto}
.dw-tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:12.5px;font-variant-numeric:tabular-nums}
.dw .dw-tbl th:not([class*="luckysheet"]){background:#f1f5f9;color:#334155;font-size:10.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;padding:9px 10px;border-bottom:2px solid #cbd5e1;text-align:right;white-space:nowrap}
.dw .dw-tbl th:first-child:not([class*="luckysheet"]),.dw .dw-tbl td:first-child{text-align:left}
.dw-tbl td{padding:7.5px 10px;border-bottom:1px solid #eef2f7;text-align:right;color:#1e293b;vertical-align:middle}
.dw-tbl td:first-child{font-weight:700;color:#0f172a}
.dw-tbl tbody tr:hover td{background:#f8fafc}
.dw-tbl tr.dw-tot td{background:#eff6ff;font-weight:800;color:#1d4ed8;border-top:2px solid #93c5fd;border-bottom:0;font-size:13px}
.dw-tbl .z{color:#cbd5e1}
.dw-pct{display:inline-flex;align-items:center;gap:7px;justify-content:flex-end;min-width:95px}
.dw-pct i{display:inline-block;width:48px;height:6px;border-radius:99px;background:#e2e8f0;overflow:hidden}
.dw-pct i b{display:block;height:100%;background:#10b981;border-radius:99px}
.dw-type-cell{display:inline-flex;align-items:baseline;gap:4px;justify-content:flex-end}
.dw-type-pct{color:#64748b;font-size:10.5px;font-weight:600}

/* Pills & Badges */
.dw-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:10.5px;font-weight:800;white-space:nowrap}
.dw-done{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.dw-na{background:#f1f5f9;color:#94a3b8}
.dw-wait{background:#fef3c7;color:#b45309;border:1px solid #fde68a}

/* Day Switcher & Workload Table */
.dw-day-tabs{display:flex;gap:8px;padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-wrap:wrap;align-items:center;justify-content:space-between}
.dw-day-btns{display:flex;gap:6px;flex-wrap:wrap}
.dw-day-btn{border:1px solid #cbd5e1;background:#fff;color:#334155;padding:7px 12px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:all .15s}
.dw-day-btn:hover{border-color:#93c5fd;background:#eff6ff}
.dw-day-btn.on{background:#1d4ed8;color:#fff;border-color:#1d4ed8;box-shadow:0 2px 6px rgba(29,78,216,.25)}
.dw-day-btn span{font-size:11px;padding:1px 7px;border-radius:99px;background:rgba(15,23,42,.08);color:inherit;font-weight:800}
.dw-day-btn.on span{background:rgba(255,255,255,.22);color:#fff}
.dw-sechd td{background:#f1f5f9!important;color:#0f172a!important;padding:8px 12px!important;border-top:1px solid #cbd5e1!important;border-bottom:1px solid #e2e8f0!important}
.dw-sechd-wrap{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.dw-sechd-title{font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#1e3a8a;display:inline-flex;align-items:center;gap:6px}
.dw-sechd-desc{font-size:11px;font-weight:500;color:#64748b}
.dw-dt{color:#475569!important;font-size:11.5px;font-weight:600;white-space:nowrap}
.dw-note{padding:11px 16px;font-size:12px;color:#92400e;background:#fffbeb;border-top:1px solid #fde68a;font-weight:700;display:flex;align-items:center;gap:8px}

/* System Encoding Cards */
.dw-sys-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
.dw-sys-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px 12px;cursor:pointer;transition:all .15s;text-align:left}
.dw-sys-card:hover{border-color:#93c5fd}
.dw-sys-card.on{border:2px solid #1d4ed8;background:#eff6ff;box-shadow:0 2px 8px rgba(29,78,216,.1)}
.dw-sys-card small{display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.dw-sys-card b{display:block;font-size:18px;font-weight:900;color:#0f172a;margin:2px 0}
.dw-sys-card span{display:block;font-size:10.5px;color:#475569;line-height:1.25}
.dw-pane[hidden]{display:none}
.dw-foot{padding:11px 16px;font-size:11.5px;color:#475569;background:#f8fafc;border-top:1px solid #e2e8f0;line-height:1.45}

/* Liquid Glass Dark Mode Overrides */
html[data-theme="dark"] .dw-bar{background:rgba(17,25,44,.76);border-color:rgba(148,163,184,.22);box-shadow:inset 1px 1px 0 rgba(255,255,255,.1),0 8px 28px rgba(2,6,23,.4);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
html[data-theme="dark"] .dw-bar h2{color:#f1f5f9}
html[data-theme="dark"] .dw-bar p{color:#94a3b8}
html[data-theme="dark"] .dw-ctl label{color:#94a3b8}
html[data-theme="dark"] .dw-ctl select,html[data-theme="dark"] .dw-ctl input[type=date]{background:rgba(15,23,42,.82);border-color:rgba(148,163,184,.26);color:#f1f5f9}
html[data-theme="dark"] .dw-nav a{background:rgba(17,25,44,.76);border-color:rgba(148,163,184,.24);color:#cbd5e1}
html[data-theme="dark"] .dw-nav a:hover{background:rgba(37,99,235,.22);border-color:rgba(96,165,250,.55);color:#93c5fd}
html[data-theme="dark"] .dw-flow{background:rgba(17,25,44,.76);border-color:rgba(148,163,184,.22);box-shadow:inset 1px 1px 0 rgba(255,255,255,.1),0 8px 28px rgba(2,6,23,.4);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
html[data-theme="dark"] .dw-flow-top-note{color:#cbd5e1}
html[data-theme="dark"] .dw-step{background:rgba(15,23,42,.68);border-color:rgba(148,163,184,.22)}
html[data-theme="dark"] .dw-step:hover{background:rgba(30,41,59,.85);border-color:rgba(96,165,250,.55);box-shadow:0 8px 22px rgba(2,6,23,.45)}
html[data-theme="dark"] .dw-step.is-active,html[data-theme="dark"] .dw-step.dw-tab-on{background:rgba(37,99,235,.22);border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.22)}
html[data-theme="dark"] .dw-step-num{background:rgba(37,99,235,.25);color:#93c5fd}
html[data-theme="dark"] .dw-step b{color:#f1f5f9}
html[data-theme="dark"] .dw-step-cta{background:rgba(37,99,235,.24);color:#93c5fd}
html[data-theme="dark"] .dw-step p{color:#94a3b8}
html[data-theme="dark"] .dw-step p strong{color:#f1f5f9}
html[data-theme="dark"] .dw-sec h3{color:#f1f5f9}
html[data-theme="dark"] .dw-sec small{color:#94a3b8}
html[data-theme="dark"] .dw-sec-pill{background:rgba(37,99,235,.32);border:1px solid rgba(96,165,250,.4)}
html[data-theme="dark"] .dw-kpi{background:rgba(17,25,44,.76);border-color:rgba(148,163,184,.22);box-shadow:inset 1px 1px 0 rgba(255,255,255,.1),0 6px 20px rgba(2,6,23,.35);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
html[data-theme="dark"] .dw-kpi.is-active{background:rgba(30,41,59,.88);box-shadow:0 0 0 3px rgba(59,130,246,.24)}
html[data-theme="dark"] .dw-kpi-top small{color:#94a3b8}
html[data-theme="dark"] .dw-kpi-tag{background:rgba(30,41,59,.85);color:#cbd5e1;border:1px solid rgba(148,163,184,.22)}
html[data-theme="dark"] .dw-kpi b{color:#f1f5f9}
html[data-theme="dark"] .dw-kpi-sub{color:#94a3b8}
html[data-theme="dark"] .dw-kpi-jump{color:#60a5fa}
html[data-theme="dark"] .dw-kpi-bar{background:rgba(30,41,59,.85)}
html[data-theme="dark"] .dw-card{background:rgba(17,25,44,.76);border-color:rgba(148,163,184,.22);box-shadow:inset 1px 1px 0 rgba(255,255,255,.1),0 8px 28px rgba(2,6,23,.4);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
html[data-theme="dark"] .dw-card-hd{background:rgba(15,23,42,.65);border-bottom-color:rgba(148,163,184,.2)}
html[data-theme="dark"] .dw-card-hd h3{color:#f1f5f9}
html[data-theme="dark"] .dw-card-hd p{color:#94a3b8}
html[data-theme="dark"] .dw-card-explain{background:rgba(15,23,42,.48);border-bottom-color:rgba(148,163,184,.16);color:#cbd5e1}
html[data-theme="dark"] .dw-card-explain b{color:#f1f5f9}
html[data-theme="dark"] .dw-chart{border:1px solid rgba(148,163,184,.24);box-shadow:inset 1px 1px 0 rgba(255,255,255,.1),0 10px 30px rgba(2,6,23,.45)}
html[data-theme="dark"] .dw-tbl th{background:rgba(15,23,42,.82);color:#cbd5e1;border-bottom-color:rgba(148,163,184,.24)}
html[data-theme="dark"] .dw-tbl td{color:#e2e8f0;border-bottom-color:rgba(148,163,184,.14)}
html[data-theme="dark"] .dw-tbl td:first-child{color:#f1f5f9}
html[data-theme="dark"] .dw-tbl tbody tr:hover td{background:rgba(51,65,85,.35)}
html[data-theme="dark"] .dw-tbl tr.dw-tot td{background:rgba(30,41,59,.85);color:#f8fafc;border-top-color:rgba(148,163,184,.28)}
html[data-theme="dark"] .dw-tbl tr.dw-row-hi td,html[data-theme="dark"] .dw-tbl td.dw-col-hi,html[data-theme="dark"] .dw-tbl th.dw-col-hi{background:rgba(37,99,235,.18)!important}
html[data-theme="dark"] .dw-tbl tr.dw-row-warn td{background:rgba(245,158,11,.14)!important}
html[data-theme="dark"] .dw-tbl .z{color:#475569}
html[data-theme="dark"] .dw-pct i{background:rgba(30,41,59,.85);border:1px solid rgba(148,163,184,.18)}
html[data-theme="dark"] .dw-type-pct{color:#94a3b8}
html[data-theme="dark"] .dw-done{background:rgba(16,185,129,.18);color:#6ee7b7;border-color:rgba(16,185,129,.32)}
html[data-theme="dark"] .dw-na{background:rgba(51,65,85,.55);color:#94a3b8}
html[data-theme="dark"] .dw-wait{background:rgba(245,158,11,.18);color:#fcd34d;border-color:rgba(245,158,11,.32)}
html[data-theme="dark"] .dw-day-tabs{background:rgba(15,23,42,.62);border-bottom-color:rgba(148,163,184,.2)}
html[data-theme="dark"] .dw-day-btn{background:rgba(17,25,44,.78);border-color:rgba(148,163,184,.24);color:#cbd5e1}
html[data-theme="dark"] .dw-day-btn:hover{border-color:rgba(96,165,250,.55);background:rgba(37,99,235,.22);color:#f8fafc}
html[data-theme="dark"] .dw-day-btn.on{background:#2563eb;color:#fff;border-color:#2563eb}
html[data-theme="dark"] .dw-day-btn span{background:rgba(255,255,255,.1)}
html[data-theme="dark"] .dw-sechd td{background:rgba(30,41,59,.78)!important;color:#f1f5f9!important;border-top-color:rgba(148,163,184,.26)!important;border-bottom-color:rgba(148,163,184,.2)!important}
html[data-theme="dark"] .dw-sechd-title{color:#93c5fd}
html[data-theme="dark"] .dw-sechd-desc{color:#94a3b8}
html[data-theme="dark"] .dw-dt{color:#94a3b8!important}
html[data-theme="dark"] .dw-note{background:rgba(120,53,15,.32);border-top-color:rgba(251,191,36,.3);color:#fde68a}
html[data-theme="dark"] .dw-sys-grid{background:rgba(15,23,42,.58);border-bottom-color:rgba(148,163,184,.2)}
html[data-theme="dark"] .dw-sys-card{background:rgba(17,25,44,.78);border-color:rgba(148,163,184,.22)}
html[data-theme="dark"] .dw-sys-card:hover{border-color:rgba(96,165,250,.55)}
html[data-theme="dark"] .dw-sys-card.on{border-color:#3b82f6;background:rgba(37,99,235,.2);box-shadow:0 4px 14px rgba(37,99,235,.25)}
html[data-theme="dark"] .dw-sys-card small{color:#94a3b8}
html[data-theme="dark"] .dw-sys-card b{color:#f1f5f9}
html[data-theme="dark"] .dw-sys-card span{color:#cbd5e1}
html[data-theme="dark"] .dw-foot{background:rgba(15,23,42,.62);border-top-color:rgba(148,163,184,.2);color:#94a3b8}
</style>

<?php
$areaSubTabs = match ($areaTab) {
    'wholeshoe' => ['workload' => 'Whole Shoe Workload', 'encoding' => 'System Encoding (3 Portals)', 'analytics' => 'Whole Shoe Analytics'],
    'materials' => ['workload' => 'Material Intake (IQC)', 'encoding' => 'System Encoding (3 Portals)', 'analytics' => 'Material Test Analytics'],
    default     => ['workload' => 'Daily Workload', 'encoding' => 'System Encoding (3 Portals)', 'daily' => 'Daily Bonding', 'monthly' => 'Monthly Summary', 'analytics' => 'Bonding Analytics', 'result' => 'Cement Analytics'],
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
        <form method="get" action="<?= site_url('analytics') ?>" class="dw-ctl" id="dw-form">
            <input type="hidden" name="tab" value="<?= esc($areaTab, 'attr') ?>">
            <input type="hidden" name="view" value="<?= esc($areaView, 'attr') ?>">
            <input type="hidden" name="period" value="<?= esc($period, 'attr') ?>">
            <div class="dw-mode" role="group" aria-label="Monitoring Period">
                <a href="<?= esc($modeUrl('daily'), 'attr') ?>" class="<?= $period === 'daily' ? 'on' : '' ?>">Daily</a>
                <a href="<?= esc($modeUrl('weekly'), 'attr') ?>" class="<?= $period === 'weekly' ? 'on' : '' ?>">Weekly</a>
                <a href="<?= esc($modeUrl('monthly'), 'attr') ?>" class="<?= $period === 'monthly' ? 'on' : '' ?>">Monthly</a>
            </div>
            <label>Year
                <select name="year" id="dw-year-sel">
                    <?php for ($y = (int) date('Y') + 1; $y >= 2024; $y--): ?><option value="<?= $y ?>"<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option><?php endfor ?>
                </select>
            </label>
            <div class="dw-nav">
                <?php if ($period === 'weekly'): ?>
                    <label>Week <input type="week" name="week" id="dw-week-inp" value="<?= esc($weekVal, 'attr') ?>" onchange="this.form.submit()"></label>
                <?php elseif ($period === 'monthly'): ?>
                    <label>Month
                        <select name="month" onchange="this.form.submit()">
                            <?php foreach ($mNames as $mi => $mn): $mNum = $mi + 1; ?>
                                <option value="<?= $mNum ?>"<?= $mNum === $selMonth ? ' selected' : '' ?>><?= esc($mn) ?></option>
                            <?php endforeach ?>
                        </select>
                    </label>
                <?php else: ?>
                    <label>Date <input type="date" name="asof" id="dw-date-inp" value="<?= esc($asof, 'attr') ?>" onchange="this.form.submit()"></label>
                <?php endif ?>
            </div>
        </form>
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
        <?php else: ?>
        <div class="dw-kpis">
            <button type="button" class="dw-kpi" style="--kc:#0284c7" data-dw-action="day-latest" data-dw-day="<?= esc($latestDay['date'], 'attr') ?>">
                <div class="dw-kpi-top"><small><?= esc($period === 'monthly' ? 'Latest Week' : 'Tested on') ?> (<?= esc($latestDay['short'] ?? $latestDay['date']) ?>)</small><span class="dw-kpi-tag" style="background:#e0f2fe;color:#0369a1"><?= $pctTxt($latestDay['total']['f'], $latestDay['total']['q']) ?> Done</span></div>
                <b><?= $latestDay['total']['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $latestDay['total']['q'] ?> shoes</span></b>
                <div class="dw-kpi-sub"><span><?= $latestDay['shut'] ?> shutdown line<?= $latestDay['shut'] === 1 ? '' : 's' ?> in <?= esc($latestDay['short'] ?? $latestDay['date']) ?></span><span class="dw-kpi-jump">View Latest &rarr;</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $latestDay['total']['q'] ? round($latestDay['total']['f'] / $latestDay['total']['q'] * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#ea580c" data-dw-action="test-sec" data-dw-sec="retest">
                <div class="dw-kpi-top"><small>Failed Retest Bonding (<?= esc($windowTag) ?>)</small><span class="dw-kpi-tag" style="background:#fff7ed;color:#c2410c"><?= $pctTxt($retestSec['f'], $retestSec['q']) ?> Done</span></div>
                <b style="color:#ea580c"><?= $retestSec['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $retestSec['q'] ?></span></b>
                <div class="dw-kpi-sub"><span>Corrective re-tests on failed lines</span><span class="dw-kpi-jump">Highlight Retest &rarr;</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $retestSec['q'] ? round($retestSec['f'] / $retestSec['q'] * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#2563eb" data-dw-action="test-sec" data-dw-sec="trial">
                <div class="dw-kpi-top"><small>Tech Trial (<?= esc($windowTag) ?>)</small><span class="dw-kpi-tag"><?= $pctTxt($trialSec['f'], $trialSec['q']) ?> Done</span></div>
                <b style="color:#2563eb"><?= $trialSec['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $trialSec['q'] ?></span></b>
                <div class="dw-kpi-sub"><span>Technical &amp; adhesive trial runs</span><span class="dw-kpi-jump">Highlight Trials &rarr;</span></div>
                <div class="dw-kpi-bar"><i style="width:<?= $trialSec['q'] ? round($trialSec['f'] / $trialSec['q'] * 100) : 0 ?>%"></i></div>
            </button>
            <button type="button" class="dw-kpi" style="--kc:#7c3aed" data-dw-action="test-sec" data-dw-sec="first sample">
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
                    <div class="dw-card-h-sub">Shows how many <?= $areaTab === 'wholeshoe' ? 'whole shoe' : 'bonding shoe' ?> specimens were collected and finished testing per <?= $period === 'monthly' ? 'week' : 'day' ?> (<?= esc($windowLabel) ?>)</div>
                </div>
            </div>
            <?php if (count($dw['days']) > 1): ?>
            <div class="dw-day-tabs">
                <div class="dw-day-btns">
                    <?php foreach ($dw['days'] as $idx => $day): $dd = $day['date']; ?>
                        <button type="button" class="dw-day-btn<?= $idx === 0 ? ' on' : '' ?>" data-day="<?= esc($dd, 'attr') ?>">
                            <?= esc($day['short'] ?? date('M j, Y', strtotime($dd))) ?>
                            <span><?= $day['total']['f'] ?>/<?= $day['total']['q'] ?> done</span>
                        </button>
                    <?php endforeach ?>
                </div>
                <span style="font-size:11.5px;color:#64748b;font-weight:600"><?= esc($windowShort) ?> Total: <b><?= $threeDayFin ?> / <?= $threeDayQty ?></b> tested</span>
            </div>
            <?php endif ?>

            <?php foreach ($dw['days'] as $idx => $day): $dd = $day['date']; ?>
                <div class="dw-day-pane" data-day="<?= esc($dd, 'attr') ?>"<?= $idx === 0 ? '' : ' hidden' ?>>
                    <div class="dw-card-explain">
                        <span><b>Daily Bonding Tested: <?= esc($day['label'] ?? $dd) ?></b><?= $period !== 'monthly' ? ' (' . date('l', strtotime($dd)) . ')' : '' ?></span>
                        <span>Finished: <b><?= $day['total']['f'] ?></b> of <b><?= $day['total']['q'] ?></b> specimens · Shutdown Lines: <b><?= $day['shut'] ?></b></span>
                    </div>
                    <div class="dw-tbl-wrap">
                        <table class="dw-tbl">
                            <thead>
                                <tr>
                                    <th>Test Category &amp; Specimen Type</th>
                                    <th style="text-align:left">Production &rarr; Tested Date</th>
                                    <th>Quantity</th>
                                    <th>Finished</th>
                                    <th>Remaining</th>
                                    <th>Status / Timeline</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($dw['sections'] as $sk => [$title, $labels]):
                                [$secFriendly, $secExplain] = $sectionDescriptions[$sk] ?? [$title, ''];
                            ?>
                                <tr class="dw-sechd" data-sec="<?= esc($sk, 'attr') ?>">
                                    <td colspan="6">
                                        <div class="dw-sechd-wrap">
                                            <span class="dw-sechd-title"><?= esc($title) ?> &mdash; <?= esc($secFriendly) ?></span>
                                            <span class="dw-sechd-desc"><?= esc($secExplain) ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php foreach ($labels as $lb):
                                    $c = $day['cells'][$sk][$lb];
                                    $rem = $c['q'] - $c['f'];
                                    $e = $est($c['q'], $c['f'], $dd);
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
                                    <td>Total Specimens (<?= esc($dd) ?>)</td>
                                    <td></td>
                                    <td><?= $day['total']['q'] ?></td>
                                    <td><?= $day['total']['f'] ?></td>
                                    <td><?= $day['total']['q'] - $day['total']['f'] ?></td>
                                    <td><?= $day['total']['q'] > 0 ? round($day['total']['f'] / $day['total']['q'] * 100) . '% Complete' : '–' ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($day['shut'] > 0): ?>
                        <div class="dw-note">
                            <span>&#9888; Production Line Notice: <b><?= $day['shut'] ?></b> daily bonding shoe line<?= $day['shut'] === 1 ? ' was' : 's were' ?> marked <b>SHUTDOWN</b> (inactive / no production) on <?= esc($dd) ?>.</span>
                        </div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
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
