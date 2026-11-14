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

$prev = date('Y-m-d', strtotime("{$asof} -1 day"));
$next = date('Y-m-d', strtotime("{$asof} +1 day"));
$link = static fn (string $d) => site_url('analytics') . '?' . http_build_query(['tab' => 'daily', 'year' => (int) date('Y', strtotime($d)), 'asof' => $d]);

/* Compute overall 3-day lab testing summary and MTD encoding summary for executive KPI cards */
$latestDay = $dw['days'][0] ?? ['date' => $asof, 'total' => ['q' => 0, 'f' => 0], 'shut' => 0];
$threeDayQty = $threeDayFin = $threeDayShut = 0;
foreach ($dw['days'] as $dItem) {
    $threeDayQty  += $dItem['total']['q'];
    $threeDayFin  += $dItem['total']['f'];
    $threeDayShut += $dItem['shut'];
}
$rowsEncAll = $dw['cat'] + ['Materials' => $dw['materials']];
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
.dw{display:grid;gap:18px;min-width:0;padding-bottom:24px}.dw *{box-sizing:border-box}
.dw-bar{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px 20px;box-shadow:0 2px 8px rgba(15,23,42,.04)}
.dw-bar-title{display:flex;align-items:center;gap:12px}
.dw-bar-icon{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#1d4ed8,#0f172a);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;flex:none}
.dw-bar h2{margin:0;font-size:19px;font-weight:800;color:#0f172a;letter-spacing:-.01em}
.dw-bar p{margin:3px 0 0;font-size:13px;color:#475569;max-width:680px;line-height:1.4}
.dw-ctl{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap}
.dw-ctl label{display:flex;flex-direction:column;gap:4px;font-size:10.5px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.05em}
.dw-ctl select,.dw-ctl input[type=date]{height:38px;padding:0 12px;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;font-size:13px;font-weight:700;color:#0f172a;cursor:pointer}
.dw-nav{display:inline-flex;align-items:flex-end;gap:5px}
.dw-nav a{display:inline-flex;align-items:center;justify-content:center;height:38px;padding:0 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#334155;font-size:12.5px;font-weight:700;text-decoration:none;transition:all .15s}
.dw-nav a:hover{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}

/* 4-Stage Lab Workflow Guide Banner (for visitors/management unfamiliar with lab operations) */
.dw-flow{background:linear-gradient(135deg,#f8fafc 0%,#eff6ff 100%);border:1px solid #bfdbfe;border-radius:16px;padding:14px 18px;display:flex;flex-direction:column;gap:12px}
.dw-flow-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.dw-flow-badge{display:inline-flex;align-items:center;gap:6px;background:#1e3a8a;color:#fff !important;font-size:11px;font-weight:800;padding:5px 12px;border-radius:99px;letter-spacing:.04em;text-transform:uppercase;box-shadow:0 1px 3px rgba(30,58,138,.25)}
.dw-flow-top-note{font-size:12.5px;color:#334155;font-weight:600}
.dw-flow-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
@media(max-width:1100px){.dw-flow-steps{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){.dw-flow-steps{grid-template-columns:1fr}}
.dw-step{background:#fff;border:1.5px solid #dbeafe;border-radius:12px;padding:12px 14px;display:flex;gap:11px;align-items:flex-start;box-shadow:0 1px 3px rgba(15,23,42,.03);position:relative;cursor:pointer;text-align:left;width:100%;transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease,background-color .16s ease}
.dw-step:hover{transform:translateY(-2px);border-color:#60a5fa;box-shadow:0 6px 16px rgba(37,99,235,.12);background:#f8fbff}
.dw-step.is-active,.dw-step.dw-tab-on{border-color:#1d4ed8;background:#eff6ff;box-shadow:0 0 0 3px rgba(29,78,216,.15)}
.dw-step.dw-tab-on .dw-step-num{background:#1d4ed8;color:#fff}
/* each stage is a tab: only the blocks of the open stage are shown */
[data-stage][hidden]{display:none !important}
#dw-sec-workload ~ .dw-kpis{grid-template-columns:repeat(auto-fit,minmax(260px,340px))}
.dw-step-num{width:28px;height:28px;border-radius:8px;background:#dbeafe;color:#1d4ed8;font-size:12.5px;font-weight:900;display:flex;align-items:center;justify-content:center;flex:none;margin-top:1px;transition:all .16s ease}
.dw-step:hover .dw-step-num,.dw-step.is-active .dw-step-num{background:#1d4ed8;color:#fff}
.dw-step-body{flex:1;min-width:0}
.dw-step-head{display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:3px}
.dw-step b{font-size:12.5px;font-weight:800;color:#0f172a}
.dw-step-cta{font-size:10px;font-weight:800;color:#2563eb;background:#dbeafe;padding:2px 7px;border-radius:99px;white-space:nowrap}
.dw-step p{margin:0;font-size:11.5px;color:#475569;line-height:1.4}
.dw-step p strong{font-weight:700;color:#0f172a;display:inline}

/* Section Headers */
.dw-sec{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:6px 0 -4px;flex-wrap:wrap}
.dw-sec-left{display:flex;align-items:center;gap:10px}
.dw-sec-pill{background:#0f172a;color:#fff !important;font-size:10.5px;font-weight:800;padding:3px 9px;border-radius:6px;letter-spacing:.05em;text-transform:uppercase}
.dw-sec h3{margin:0;font-size:15px;font-weight:800;color:#0f172a}
.dw-sec small{font-size:12px;color:#64748b;font-weight:500}

/* Executive KPI Cards (Clickable) */
.dw-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
@media(max-width:1180px){.dw-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
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
.dw-tbl th{background:#f1f5f9;color:#334155;font-size:10.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;padding:9px 10px;border-bottom:2px solid #cbd5e1;text-align:right;white-space:nowrap}
.dw-tbl th:first-child,.dw-tbl td:first-child{text-align:left}
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
</style>

<div class="dw">
    <!-- Top Date Controls -->
    <div class="dw-bar" style="justify-content:flex-end">
        <form method="get" action="<?= site_url('analytics') ?>" class="dw-ctl" id="dw-form">
            <input type="hidden" name="tab" value="daily">
            <label>Monitoring Year
                <select name="year" onchange="this.form.submit()">
                    <?php for ($y = (int) date('Y') + 1; $y >= 2024; $y--): ?><option value="<?= $y ?>"<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option><?php endfor ?>
                </select>
            </label>
            <div class="dw-nav">
                <a href="<?= esc($link($prev), 'attr') ?>" title="Previous day">&#9664; Prev</a>
                <label>Workload As Of Date <input type="date" name="asof" value="<?= esc($asof, 'attr') ?>" onchange="this.form.submit()"></label>
                <a href="<?= esc($link($next), 'attr') ?>" title="Next day">Next &#9654;</a>
                <a href="<?= esc($link(date('Y-m-d')), 'attr') ?>">Today</a>
            </div>
        </form>
    </div>

    <!-- Easy-to-understand 4-Step Lab Process Guide (Clickable Stage Cards) -->
    <div class="dw-flow">
        <div class="dw-flow-steps">
            <button type="button" class="dw-step" data-dw-action="stage-intake">
                <div class="dw-step-num">1</div>
                <div class="dw-step-body">
                    <div class="dw-step-head"><b>Material Intake (IQC)</b><span class="dw-step-cta"><?= number_format($totRecv) ?> batches &rarr;</span></div>
                    <p>Raw materials (Fabric, Leather, Synthetic, Accessories, Rubber) arrive in containers and are logged for lab testing before factory use.</p>
                </div>
            </button>
            <button type="button" class="dw-step" data-dw-action="stage-testing">
                <div class="dw-step-num">2</div>
                <div class="dw-step-body">
                    <div class="dw-step-head"><b>Physical &amp; Bonding Testing</b><span class="dw-step-cta"><?= $threeDayFin ?>/<?= $threeDayQty ?> (3d) &rarr;</span></div>
                    <p>Technicians pull finished shoes daily from assembly lines (Bldg A &amp; B) and test material specimens against VF / Vans standards.</p>
                </div>
            </button>
            <button type="button" class="dw-step" data-dw-action="stage-retest">
                <div class="dw-step-num">3</div>
                <div class="dw-step-body">
                    <div class="dw-step-head"><b>Re-Test &amp; Line Control</b><span class="dw-step-cta"><?= $threeDayShut ?> shutdown &rarr;</span></div>
                    <p>If a line fails bonding, a <strong>Failed Re-Test</strong> is required immediately; inactive production lines are marked as <strong>Shutdown</strong>.</p>
                </div>
            </button>
            <button type="button" class="dw-step" data-dw-action="stage-encoding">
                <div class="dw-step-num">4</div>
                <div class="dw-step-body">
                    <div class="dw-step-head"><b>System Encoding (3 Portals)</b><span class="dw-step-cta">ETRF · IL · ERP &rarr;</span></div>
                    <p>Completed test reports are encoded into <strong>ETRF</strong> (requests), <strong>INTERLINK</strong> (VF global portal), and <strong>ERP</strong> (factory release).</p>
                </div>
            </button>
        </div>
    </div>

    <!-- ===== SECTION 1: Annual Materials Monitoring ===== -->

    <?php
    arsort($typeTot);
    $topType = array_key_first($typeTot);
    $topCnt  = $typeTot[$topType] ?? 0;
    $encPct  = $totRecv > 0 ? round($totEnc / $totRecv * 100, 1) : 0;
    ?>
    <div class="dw-kpis">
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

    <!-- ===== SECTION 2: Daily Lab Workload & Report Encoding Progress ===== -->
    <div class="dw-sec" id="dw-sec-workload">
        <div class="dw-sec-left">
            <span class="dw-sec-pill">Stage 2, 3 &amp; 4</span>
            <h3>Daily Shoe Testing Workload &amp; Monthly System Encoding Progress (as of <?= esc($asof) ?>)</h3>
        </div>
        <small>Click any KPI card below to switch the daily testing table or system encoding portal</small>
    </div>

    <!-- Daily & Encoding Snapshot KPIs (Clickable) -->
    <div class="dw-kpis">
        <button type="button" class="dw-kpi" style="--kc:#0284c7" data-dw-action="day-latest" data-dw-day="<?= esc($latestDay['date'], 'attr') ?>">
            <div class="dw-kpi-top"><small>Tested on <?= esc($latestDay['date']) ?></small><span class="dw-kpi-tag" style="background:#e0f2fe;color:#0369a1"><?= $pctTxt($latestDay['total']['f'], $latestDay['total']['q']) ?> Done</span></div>
            <b><?= $latestDay['total']['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $latestDay['total']['q'] ?> shoes</span></b>
            <div class="dw-kpi-sub"><span><?= $latestDay['shut'] ?> shutdown line<?= $latestDay['shut'] === 1 ? '' : 's' ?> on <?= esc($latestDay['date']) ?></span><span class="dw-kpi-jump">View Daily Log &rarr;</span></div>
            <div class="dw-kpi-bar"><i style="width:<?= $latestDay['total']['q'] ? round($latestDay['total']['f'] / $latestDay['total']['q'] * 100) : 0 ?>%"></i></div>
        </button>
        <button type="button" class="dw-kpi" style="--kc:#2563eb" data-dw-action="sys-portal" data-dw-portal="etrf">
            <div class="dw-kpi-top"><small>ETRF Portal (<?= esc($dw['monthName']) ?>)</small><span class="dw-kpi-tag"><?= $pctTxt($sysTotals['etrf']['f'], $sysTotals['etrf']['q']) ?></span></div>
            <b style="color:#2563eb"><?= $sysTotals['etrf']['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $sysTotals['etrf']['q'] ?></span></b>
            <div class="dw-kpi-sub"><span><?= max(0, $sysTotals['etrf']['q'] - $sysTotals['etrf']['f']) ?> waiting for ETRF</span><span class="dw-kpi-jump">Open ETRF &rarr;</span></div>
            <div class="dw-kpi-bar"><i style="width:<?= $sysTotals['etrf']['q'] ? round($sysTotals['etrf']['f'] / $sysTotals['etrf']['q'] * 100) : 0 ?>%"></i></div>
        </button>
        <button type="button" class="dw-kpi" style="--kc:#7c3aed" data-dw-action="sys-portal" data-dw-portal="il">
            <div class="dw-kpi-top"><small>INTERLINK Portal (<?= esc($dw['monthName']) ?>)</small><span class="dw-kpi-tag"><?= $pctTxt($sysTotals['il']['f'], $sysTotals['il']['q']) ?></span></div>
            <b style="color:#7c3aed"><?= $sysTotals['il']['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $sysTotals['il']['q'] ?></span></b>
            <div class="dw-kpi-sub"><span><?= max(0, $sysTotals['il']['q'] - $sysTotals['il']['f']) ?> waiting for INTERLINK</span><span class="dw-kpi-jump">Open INTERLINK &rarr;</span></div>
            <div class="dw-kpi-bar"><i style="width:<?= $sysTotals['il']['q'] ? round($sysTotals['il']['f'] / $sysTotals['il']['q'] * 100) : 0 ?>%"></i></div>
        </button>
        <button type="button" class="dw-kpi" style="--kc:#059669" data-dw-action="sys-portal" data-dw-portal="erp">
            <div class="dw-kpi-top"><small>Factory ERP (<?= esc($dw['monthName']) ?>)</small><span class="dw-kpi-tag"><?= $pctTxt($sysTotals['erp']['f'], $sysTotals['erp']['q']) ?></span></div>
            <b style="color:#059669"><?= $sysTotals['erp']['f'] ?> <span style="font-size:16px;font-weight:700;color:#64748b">/ <?= $sysTotals['erp']['q'] ?></span></b>
            <div class="dw-kpi-sub"><span><?= max(0, $sysTotals['erp']['q'] - $sysTotals['erp']['f']) ?> waiting for ERP</span><span class="dw-kpi-jump">Open ERP &rarr;</span></div>
            <div class="dw-kpi-bar"><i style="width:<?= $sysTotals['erp']['q'] ? round($sysTotals['erp']['f'] / $sysTotals['erp']['q'] * 100) : 0 ?>%"></i></div>
        </button>
    </div>

    <div class="dw-grid2" style="grid-template-columns:minmax(0,1.35fr) minmax(0,1fr)">
        <!-- Left Card: Daily Bonding & Whole-Shoe Testing Log (with 3-Day Switcher + Compare All) -->
        <div class="dw-card" id="dw-days-card">
            <div class="dw-card-h">
                <div>
                    <div class="dw-card-h-title">Daily Physical Testing Workload (3-Day Rolling Window)</div>
                    <div class="dw-card-h-sub">Shows how many shoe specimens were collected from production lines and finished testing each day</div>
                </div>
            </div>
            <div class="dw-day-tabs">
                <div class="dw-day-btns">
                    <?php foreach ($dw['days'] as $idx => $day): $dd = $day['date']; ?>
                        <button type="button" class="dw-day-btn<?= $idx === 0 ? ' on' : '' ?>" data-day="<?= esc($dd, 'attr') ?>">
                            <?= date('M j, Y', strtotime($dd)) ?>
                            <span><?= $day['total']['f'] ?>/<?= $day['total']['q'] ?> done</span>
                        </button>
                    <?php endforeach ?>
                    <button type="button" class="dw-day-btn" data-day="all">Show All 3 Days Stacked</button>
                </div>
                <span style="font-size:11.5px;color:#64748b;font-weight:600">3-Day Total: <b><?= $threeDayFin ?> / <?= $threeDayQty ?></b> tested</span>
            </div>

            <?php foreach ($dw['days'] as $idx => $day): $dd = $day['date']; ?>
                <div class="dw-day-pane" data-day="<?= esc($dd, 'attr') ?>"<?= $idx === 0 ? '' : ' hidden' ?>>
                    <div class="dw-card-explain">
                        <span><b>Daily Bonding Tested as of <?= esc($dd) ?></b> (<?= date('l', strtotime($dd)) ?>)</span>
                        <span>Finished: <b><?= $day['total']['f'] ?></b> of <b><?= $day['total']['q'] ?></b> specimens · Shutdown Lines: <b><?= $day['shut'] ?></b></span>
                    </div>
                    <div class="dw-tbl-wrap">
                        <table class="dw-tbl">
                            <thead>
                                <tr>
                                    <th>Test Category &amp; Specimen Type</th>
                                    <th style="text-align:left">Production → Tested Date</th>
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
                                            <span class="dw-sechd-title"><?= esc($title) ?> — <?= esc($secFriendly) ?></span>
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
            $dayOfMonth = max(1, (int) date('j', strtotime($asof)));
            foreach (['etrf' => 'ETRF Portal', 'il' => 'INTERLINK System', 'erp' => 'Factory ERP'] as $k => $nm):
                $tq = $tf = 0;
                $rowsE = $dw['cat'] + ['Materials' => $dw['materials']];
            ?>
                <div class="dw-pane" data-k="<?= $k ?>"<?= $k === 'etrf' ? '' : ' hidden' ?>>
                    <div class="dw-card-explain">
                        <span><b><?= esc($nm) ?></b> — <?= esc($sysTotals[$k]['desc']) ?></span>
                        <span>Month-to-Date (<?= esc($dw['monthName']) ?>)</span>
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
                <b>What this table means:</b> Physical testing in the lab finishes first (left table), and technicians then encode the official test reports into <b>ETRF</b>, <b>INTERLINK</b>, and <b>ERP</b>. <i>Remaining</i> shows tested specimens awaiting digital report submission.
            </div>
        </div>
    </div>
</div>

<script>
(function () {
  var encBox = document.getElementById('dw-enc');
  var dayCard = document.getElementById('dw-days-card');
  var matSummaryCard = document.getElementById('dw-card-mat-summary');
  var matTypesCard = document.getElementById('dw-card-mat-types');

  function clearHighlights() {
    document.querySelectorAll('.dw-col-hi, .dw-row-hi, .dw-row-warn').forEach(function (el) {
      el.classList.remove('dw-col-hi', 'dw-row-hi', 'dw-row-warn');
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

  // Stage tabs: stage 1 = Material Intake (materials analytics), 2 = Physical & Bonding Testing (daily log),
  // 3 = Re-Test & Line Control (daily log, re-test rows), 4 = System Encoding (ETRF / INTERLINK / ERP)
  var STAGE_OF = { 'stage-intake': 1, 'mat-recv': 1, 'mat-enc': 1, 'mat-pct': 1, 'mat-type': 1, 'stage-testing': 2, 'day-latest': 2, 'stage-retest': 3, 'stage-encoding': 4, 'sys-portal': 4 };
  var secMat = document.getElementById('dw-sec-materials');
  var secWork = document.getElementById('dw-sec-workload');
  var tag = function (el, list) { if (el) { el.setAttribute('data-stage', list.join(' ')); } };
  for (var n = secMat; n && n !== secWork; n = n.nextElementSibling) { tag(n, [1]); }
  for (var m = secWork; m; m = m.nextElementSibling) {
    if (m.tagName === 'SCRIPT') { break; }
    tag(m, [2, 3, 4]);
  }
  tag(dayCard, [2, 3]);
  tag(encBox, [4]);
  document.querySelectorAll('#dw-sec-workload ~ .dw-kpis [data-dw-action]').forEach(function (k) {
    tag(k, k.getAttribute('data-dw-action') === 'sys-portal' ? [4] : [2, 3]);
  });
  var workGrid = dayCard ? dayCard.parentNode : null;
  function showStage(n) {
    document.querySelectorAll('[data-stage]').forEach(function (el) {
      el.hidden = el.getAttribute('data-stage').split(' ').indexOf(String(n)) < 0;
    });
    document.querySelectorAll('.dw-step').forEach(function (s) {
      s.classList.toggle('dw-tab-on', STAGE_OF[s.getAttribute('data-dw-action')] === n);
    });
    if (workGrid) { workGrid.style.gridTemplateColumns = n === 4 ? 'minmax(0,1fr)' : (n === 2 || n === 3 ? 'minmax(0,1fr)' : ''); }
    try { sessionStorage.setItem('dw_stage', String(n)); } catch (e) { /* storage blocked */ }
  }
  document.querySelectorAll('[data-dw-action]').forEach(function (card) {
    card.addEventListener('click', function () {
      var st = STAGE_OF[card.getAttribute('data-dw-action')];
      if (st) { showStage(st); }
    });
  });
  var startStage = 1;
  try { startStage = parseInt(sessionStorage.getItem('dw_stage'), 10) || 1; } catch (e) { /* default */ }
  showStage(startStage);

  // Clickable Stage Cards & KPI Cards
  var interactiveCards = document.querySelectorAll('[data-dw-action]');
  interactiveCards.forEach(function (card) {
    card.addEventListener('click', function () {
      var act = card.getAttribute('data-dw-action');
      interactiveCards.forEach(function (c) { c.classList.toggle('is-active', c === card); });
      clearHighlights();

      if (act === 'stage-intake' || act === 'mat-recv') {
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
      } else if (act === 'stage-testing' || act === 'day-latest') {
        var targetDay = card.getAttribute('data-dw-day');
        if (targetDay) {
          switchDay(targetDay);
        }
        if (dayCard) {
          dayCard.querySelectorAll('[data-sec-row="daily"], [data-sec-row="first"]').forEach(function (r) {
            r.classList.add('dw-row-hi');
          });
        }
        focusCard(dayCard);
      } else if (act === 'stage-retest') {
        switchDay('all');
        if (dayCard) {
          dayCard.querySelectorAll('[data-sec-row="retest"]').forEach(function (r) {
            r.classList.add('dw-row-warn');
          });
        }
        focusCard(dayCard);
      } else if (act === 'stage-encoding') {
        focusCard(encBox);
      } else if (act === 'sys-portal') {
        var portal = card.getAttribute('data-dw-portal') || 'etrf';
        switchPortal(portal);
        focusCard(encBox);
      }
    });
  });
})();
</script>
