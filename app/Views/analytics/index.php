<?php
/** Analytics page. Data: $tab, $group, $from, $to, $kpis, $chartTitle, $months, $lists, [$empty] */
$maxM = 1;
foreach ($months as $m) {
    $maxM = max($maxM, (int) $m['total']);
}
$tabs = ['bonding' => 'Shoe Bonding', 'wholeshoe' => 'Whole Shoe', 'materials' => 'Materials'];
$segmented = $months && array_key_exists('pass', $months[0]);
?>
<style>
.an{display:grid;grid-template-columns:minmax(0,1fr);gap:12px;min-width:0;max-width:100%}.an>*{min-width:0}
.an-filter{display:flex;justify-content:flex-end;align-items:center;gap:12px;flex-wrap:wrap;position:relative}
.an-seg{display:inline-flex;border:1px solid #dbe3ee;border-radius:10px;overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.05)}
.an-seg button{border:0;background:transparent;padding:9px 15px;font-size:12.5px;font-weight:600;color:#475569;cursor:pointer;border-right:1px solid #eef2f7;transition:background .15s,color .15s}
.an-seg button:last-child{border-right:0}
.an-seg button:hover{background:#f1f5f9}
.an-seg button.on{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff}
.an-dr{position:relative}
.an-dr-btn{display:inline-flex;align-items:center;gap:9px;padding:9px 14px;border:1px solid #dbe3ee;border-radius:10px;background:#fff;font-size:12.5px;font-weight:600;color:#0f172a;cursor:pointer;box-shadow:0 1px 2px rgba(15,23,42,.05)}
.an-dr-btn:hover{border-color:#93c5fd}
.an-pop{position:absolute;right:0;top:calc(100% + 8px);z-index:60;display:flex;gap:22px;padding:18px 20px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 18px 40px rgba(15,23,42,.18);width:max-content;max-width:94vw}
.an-pop[hidden]{display:none}
.an-pop-title{font-weight:800;font-size:13px;color:#0f172a;margin-bottom:12px}
.an-pop-l{width:190px;display:flex;flex-direction:column;gap:10px}
.an-quick{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.an-quick button,.an-custom-btn{padding:9px 10px;border:1px solid #dbe3ee;border-radius:9px;background:#f8fafc;font-size:12.5px;font-weight:600;color:#334155;cursor:pointer}
.an-quick button:hover,.an-custom-btn:hover{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}
.an-quick button.on,.an-custom-btn.on{background:#2563eb;border-color:#2563eb;color:#fff}
.an-pop-l label{display:flex;flex-direction:column;gap:4px;font-size:11.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
.an-pop-l select{padding:8px 10px;border:1px solid #dbe3ee;border-radius:9px;background:#f8fafc;font-size:12.5px;color:#0f172a;text-transform:none;letter-spacing:0;font-weight:500}
.an-clear-btn{border:0;background:none;color:#2563eb;font-size:12px;font-weight:600;cursor:pointer;text-align:left;padding:2px 0}
.an-pop-r{width:286px;display:flex;flex-direction:column;align-items:stretch}
.an-pick{display:flex;align-items:center;gap:8px;margin-bottom:12px}
.an-pick input{flex:1;min-width:0;padding:8px 10px;border:1px solid #dbe3ee;border-radius:9px;background:#f8fafc;font-size:12.5px;text-align:center;color:#0f172a}
.an-pick span{color:#94a3b8}
.an-cal{border:1px solid #e2e8f0;border-radius:12px;padding:10px;background:#fff}
.an-cal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;font-size:13px}
.an-cal-head button{border:0;background:#f1f5f9;width:26px;height:26px;border-radius:7px;cursor:pointer;color:#475569;font-size:10px}
.an-cal-head button:hover{background:#dbeafe;color:#1d4ed8}
.an-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center}
.an-cal-grid .dow{font-size:10.5px;font-weight:800;color:#94a3b8;padding:4px 0}
.an-cal-grid button{border:0;background:transparent;height:32px;border-radius:8px;font-size:12.5px;color:#0f172a;cursor:pointer}
.an-cal-grid button.out{color:#cbd5e1}
.an-cal-grid button:hover{background:#eff6ff}
.an-cal-grid button.today{box-shadow:inset 0 0 0 1px #93c5fd}
.an-cal-grid button.mid{background:#dbeafe;border-radius:0;color:#1e3a8a}
.an-cal-grid button.edge{background:#2563eb;color:#fff;font-weight:700}
.an-apply{margin-top:14px;align-self:center;border:0;background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;border-radius:9px;padding:9px 26px;font-size:12.5px;font-weight:700;cursor:pointer}
.an-apply:hover{background:#2563eb}
@media (max-width:760px){.an-pop{flex-direction:column;right:auto;left:0}.an-pop-l,.an-pop-r{width:100%}}
.an-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}.an-kpis .card{padding:12px 16px}
.an-kpi small{display:block;color:var(--muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.an-kpi b{display:block;font-size:22px;margin-top:2px}
.an-kpi span{font-size:12px;color:var(--muted)}
.an-pass{color:#059669}.an-tol{color:#d97706}.an-fail{color:#dc2626}
.an-bars{display:flex;align-items:flex-end;gap:4px;height:190px;overflow:hidden;padding:8px 4px 0;border-bottom:1px solid #cbd5e1}
.an-col{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;min-width:0}
.an-stack{width:100%;max-width:40px;display:flex;flex-direction:column-reverse;border-radius:4px 4px 0 0;overflow:hidden}
.an-stack i{display:block;width:100%}
.an-b{background:#3b82f6}.an-neutral{background:#3b82f6!important}.an-p{background:#10b981}.an-t{background:#f59e0b}.an-f{background:#ef4444}
.an-lbl{font-size:10px;color:var(--muted);margin-top:5px;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:clip}
.an-num{font-size:11px;font-weight:700;margin-bottom:3px}
.an-legend{display:flex;gap:16px;font-size:12px;margin-top:10px;color:var(--muted)}
.an-leg i{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:5px;vertical-align:middle}
.an-two{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px}
.an-row{display:grid;grid-template-columns:minmax(0,1.6fr) 1fr 50px;gap:10px;align-items:center;padding:4px 0;border-bottom:1px solid #eef2f7;font-size:12.5px}
.an-row .nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.an-meter{height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden}
.an-meter i{display:block;height:100%;background:#ef4444}
.an-row .v{text-align:right;font-weight:700}
.an-tabs{display:inline-flex;border:1px solid #dbe3ee;border-radius:10px;overflow:hidden;background:#fff}
.an-tabs a{padding:9px 16px;font-size:12.5px;font-weight:700;color:#475569;text-decoration:none;border-right:1px solid #eef2f7}
.an-tabs a:last-child{border-right:0}.an-tabs a:hover{background:#f1f5f9}
.an-tabs a.on{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff}
.ms-kpi-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:2px}
.ms-kpi-card{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.ms-kpi-card small{display:block;font-size:10px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
.ms-kpi-card b{display:block;font-size:20px;font-weight:800;line-height:1.15;margin:2px 0;color:#0f172a}
.ms-kpi-card .kpi-val-row{display:flex;align-items:baseline;gap:7px;margin:2px 0}
.ms-kpi-card .kpi-val-row b{margin:0}
.ms-kpi-card .kpi-pct{display:inline-flex;align-items:center;padding:1px 7px;border-radius:999px;font-size:11px;font-weight:800;line-height:1.3}
.ms-kpi-card .pct-pass{background:rgba(16,185,129,.14);color:#059669}
.ms-kpi-card .pct-tol{background:rgba(245,158,11,.16);color:#d97706}
.ms-kpi-card .pct-fail{background:rgba(239,68,68,.14);color:#dc2626}
.ms-kpi-card span.sub{display:block;font-size:10.5px;color:#64748b}
.ms-kpi-ico{width:34px;height:34px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0}
.ms-ico-blue{background:rgba(37,99,235,.12);color:#2563eb}
.ms-ico-pass{background:rgba(16,185,129,.14);color:#059669}
.ms-ico-warn{background:rgba(245,158,11,.16);color:#d97706}
.ms-ico-fail{background:rgba(239,68,68,.14);color:#dc2626}
.ms-ico-shut{background:rgba(100,116,139,.14);color:#334155}
.an-top-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
@media (max-width:1100px){.an-top-kpis{grid-template-columns:repeat(auto-fit,minmax(160px,1fr))}}
.ms-tbl{border-collapse:collapse;width:100%;font-family:Inter,system-ui,-apple-system,sans-serif;font-size:12px;align-self:flex-start;margin-top:0}
.ms-tbl th,.ms-tbl td{border:1px solid #e2e8f0;padding:4px 8px;text-align:left}
.ms-tbl th{background:#f1f5f9;text-align:center;font-weight:700;color:#334155}
.ms-tbl th.ttl{background:#2563eb;color:#fff}
.ms-tbl tr.tt td{font-weight:800}

/* Upgraded Matrix Card & Building Toggle */
.mx-card{background:#fff;border:1px solid #cbd5e1;border-radius:16px;box-shadow:0 4px 16px rgba(15,23,42,.05);overflow:hidden}
.mx-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:14px 20px;background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);color:#fff}
.mx-head-l h3{margin:0;font-size:14px;font-weight:800;letter-spacing:.03em;text-transform:uppercase;color:#fff;display:flex;align-items:center;gap:8px}
.mx-head-l p{margin:3px 0 0;font-size:12px;color:#cbd5e1;font-weight:500}
.mx-head-r{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.mx-legend{display:flex;align-items:center;gap:12px;font-size:11.5px;color:#e2e8f0;font-weight:600}
.mx-legend span{display:inline-flex;align-items:center;gap:5px}
.mx-legend i{display:inline-block;width:10px;height:10px;border-radius:3px}
.bld-toggle{display:inline-flex;background:rgba(15,23,42,.45);padding:4px;border-radius:11px;border:1px solid rgba(255,255,255,.16);gap:4px}
.bld-btn{display:inline-flex;align-items:center;gap:8px;border:0;background:transparent;color:#cbd5e1;padding:6px 14px;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer;transition:all .18s ease}
.bld-btn:hover{color:#fff;background:rgba(255,255,255,.08)}
.bld-btn.on{background:#fff;color:#0f172a;box-shadow:0 2px 8px rgba(0,0,0,.18)}
.bld-btn .bld-pill{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;padding:0 6px;border-radius:999px;font-size:11px;font-weight:800;background:rgba(255,255,255,.15);color:#fff}
.bld-btn.on .bld-pill{background:#fee2e2;color:#dc2626}
.bld-btn.on .bld-pill.zero{background:#e2e8f0;color:#475569}
.bld-pane[hidden]{display:none!important}
.mx-scroll{overflow-x:auto;max-width:100%}
.mx-tbl{border-collapse:separate;border-spacing:0;width:100%;table-layout:fixed;font-family:Inter,system-ui,-apple-system,sans-serif;font-size:11.5px;font-variant-numeric:tabular-nums;text-align:center;background:#fff}
.mx-tbl th,.mx-tbl td{border-right:1px solid #cbd5e1;border-bottom:1px solid #cbd5e1;padding:5px 3px;height:28px;line-height:1.2;font-weight:600;color:#334155;overflow:hidden}
.mx-tbl tr.dh th{background:#f8fafc;color:#334155;font-size:11px;font-weight:800;height:36px;border-bottom:2px solid #94a3b8;letter-spacing:.01em}
.mx-tbl .lc{text-align:left;padding:5px 14px;font-weight:800;white-space:nowrap;color:#0f172a;background:#fff;position:sticky;left:0;z-index:2;border-right:2px solid #94a3b8}
.mx-tbl tr.dh th.lc{background:#f1f5f9;z-index:3;color:#1e293b;font-size:11px;letter-spacing:.03em}
.mx-tbl .line-tag{display:inline-flex;align-items:center;justify-content:center;min-width:36px;padding:2px 8px;border-radius:6px;background:#f1f5f9;color:#0f172a;font-weight:800;font-size:11.5px;border:1px solid #cbd5e1}
.mx-tbl tbody tr:hover td{background:#f8fafc}
.mx-tbl tbody tr:hover td.lc{background:#f1f5f9}
.mx-tbl col.sun{background:#fff1f2}
.mx-tbl th.sun{background:#ffe4e6!important;color:#e11d48!important}
.mx-tbl td.sun-cell{background:#fff5f6}
.mx-tbl tbody tr:hover td.sun-cell{background:#ffe4e6}
.mx-tbl td.z{color:#94a3b8;font-weight:600}
.mx-tbl td.has-fail{background:#fef2f2!important;color:#dc2626;font-weight:800}
.mx-tbl td.has-fail span{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;padding:0 5px;border-radius:6px;background:#dc2626;color:#fff;font-size:11px;font-weight:800;box-shadow:0 1px 3px rgba(220,38,38,.3)}
.mx-tbl .tc{width:100px;font-weight:800;background:#f8fafc;position:sticky;right:0;z-index:2;border-left:2px solid #94a3b8;border-right:0}
.mx-tbl tr.dh th.tc{background:#f1f5f9;z-index:3;font-size:9.5px;line-height:1.25;color:#1e293b}
.mx-tbl tr.bt td{background:#fff7ed!important;color:#9a3412;height:32px;font-size:11.5px;font-weight:800;border-top:2px solid #fdba74;border-bottom:2px solid #fdba74;border-right:1px solid #fed7aa}
.mx-tbl tr.bt td.z-sum{color:#fdba74;font-weight:600}
.mx-tbl tr.bt td.lc{background:#ffedd5!important;color:#9a3412;font-size:11px;letter-spacing:.02em;border-right:2px solid #94a3b8}
.mx-tbl tr.bt td.blue{background:#ffedd5!important;color:#1d4ed8;font-size:13px;font-weight:900;border-right:0}
.mx-tbl td.sd{background:#e2e8f0!important;color:#475569;font-size:8px;font-weight:800;letter-spacing:.02em;padding:0}
.mx-tbl td.mx{background:#ea580c!important;color:#fff!important;font-weight:900;font-size:12.5px}
.mx-tbl tr.rd td{background:#fef2f2!important;color:#dc2626;height:30px;font-size:11.5px;font-weight:800;border-right:1px solid #fecaca;border-bottom:1px solid #fecaca}
.mx-tbl tr.rd td.z-sum{color:#fca5a5;font-weight:600}
.mx-tbl tr.rd td.lc{background:#fee2e2!important;color:#991b1b;font-size:10.5px;letter-spacing:.01em;border-right:2px solid #94a3b8}
.mx-tbl tr.rd td.tc{background:#fee2e2!important;color:#b91c1c;font-size:12.5px;border-right:0}
.mx-tbl tr.rt td{background:#f8fafc!important;color:#0f172a;height:30px;font-size:11.5px;font-weight:800;border-right:1px solid #cbd5e1;border-bottom:1px solid #cbd5e1}
.mx-tbl tr.rt td.z-sum{color:#94a3b8;font-weight:600}
.mx-tbl tr.rt td.lc{background:#f1f5f9!important;color:#1e293b;font-size:10.5px;letter-spacing:.01em;border-right:2px solid #94a3b8}
.mx-tbl tr.rt td.tc{background:#f1f5f9!important;color:#0f172a;font-size:12.5px;border-right:0}
.mx-tbl tr.pct-row td{font-size:10px;letter-spacing:-.02em;padding:5px 2px}
.mx-tbl tr.pct-row td.has-pct{background:#fee2e2!important;color:#b91c1c;font-weight:900;font-size:10.5px}
.mx-tbl tr.pct-row td.tc{font-size:12px;letter-spacing:0}

/* Monthly Summary Pie & Trend Cards */
.ms-chart-grid{display:grid;grid-template-columns:380px minmax(0,1fr);gap:16px;align-items:stretch}
@media (max-width:1080px){.ms-chart-grid{grid-template-columns:1fr}}
.ms-pie-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px 20px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 4px 14px rgba(15,23,42,.04)}
.ms-pie-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px}
.ms-pie-head h3{margin:0;font-size:15.5px;font-weight:800;color:#0f172a}
.ms-pie-head span{font-size:11.5px;font-weight:700;color:#475569;background:#f1f5f9;padding:4px 10px;border-radius:999px}
.ms-pie-body{flex:1;display:flex;align-items:center;justify-content:center;min-height:0;padding:8px 0}
.ms-trend-card{background:linear-gradient(145deg,#0f172a 0%,#1e293b 100%);border:1px solid #1e293b;border-radius:16px;padding:18px 20px 12px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 8px 24px rgba(15,23,42,.14);color:#fff;min-width:0;overflow:hidden}
.ms-trend-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:8px}
.ms-trend-head h3{margin:0;font-size:14.5px;font-weight:800;letter-spacing:.03em;color:#f8fafc;text-transform:uppercase}
.ms-trend-head p{margin:2px 0 0;font-size:11.5px;color:#94a3b8}
.ms-trend-leg{display:flex;align-items:center;gap:14px;font-size:11.5px;color:#cbd5e1;font-weight:600}
.ms-trend-leg span{display:inline-flex;align-items:center;gap:6px}
.ms-trend-leg i{display:inline-block;width:10px;height:10px;border-radius:3px}
.ms-trend-leg i.ln{width:18px;height:2.5px;border-radius:2px;background:#38bdf8}

/* Cement Analytics */
.br-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:22px 24px;box-shadow:0 4px 14px rgba(15,23,42,.04)}
.br-tbl{border-collapse:separate;border-spacing:0;width:100%;margin:0 0 22px 0;background:#fff;font-family:Inter,system-ui,-apple-system,sans-serif;color:#0f172a;border:1px solid #cbd5e1;border-radius:12px;overflow:hidden}
.br-tbl th,.br-tbl td{border-right:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;text-align:center;padding:10px 14px}
.br-tbl th:last-child,.br-tbl td:last-child{border-right:0}
.br-tbl tr.hd th{background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#fff;font-size:11.5px;font-weight:800;letter-spacing:.04em;padding:12px 14px;border-bottom:0}
.br-tbl tr.rw td{font-size:13px;font-weight:700;color:#334155}
.br-tbl tr.rw:hover td{background:#f8fafc}
.br-tbl tr.rw td.nm{background:#f8fafc;font-weight:800;color:#0f172a;text-align:left}
.br-tbl tr.tot td{background:#f1f5f9;font-weight:800;font-size:15px;color:#0f172a;border-bottom:0;border-top:2px solid #cbd5e1}
.br-tbl tr.tot td:first-child{text-align:left}
.br-tbl tr.tot td.red{color:#dc2626;background:#fef2f2}
.br-pies{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:20px}
.br-pie{background:linear-gradient(145deg,#0f172a 0%,#1e293b 100%);border-radius:16px;padding:22px 20px 16px;color:#fff;text-align:center;box-shadow:0 6px 20px rgba(15,23,42,.12)}
.br-pie h4{margin:0 0 6px;font-size:15px;font-weight:800;color:#f8fafc;letter-spacing:.03em}
.br-pie svg{display:block;max-width:480px;margin:0 auto}
.br-leg{display:flex;justify-content:center;gap:22px;margin-top:8px;font-size:12px;color:#cbd5e1;font-weight:600}
.br-leg i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:6px;vertical-align:middle}</style>

<div class="an">
    <form method="get" action="<?= site_url('analytics') ?>" class="an-filter" id="an-form" data-today="<?= date('Y-m-d') ?>">
        <input type="hidden" name="tab" value="<?= esc($tab, 'attr') ?>">
        <?php if ($tab === 'bonding'): ?>
        <input type="hidden" name="view" value="<?= esc($view, 'attr') ?>">
        <div class="an-tabs" style="margin-right:auto">
            <?php foreach (['monthly' => 'Monthly Summary', 'daily' => 'Daily Bonding', 'analytics' => 'Bonding Analytics', 'result' => 'Cement Analytics'] as $vk => $vl): ?>
                <a href="<?= esc(site_url('analytics') . '?' . http_build_query(array_filter(['tab' => 'bonding', 'view' => $vk, 'group' => $group, 'from' => $from, 'to' => $to, 'all' => ($from === '' && $to === '') ? '1' : null], static fn ($v) => $v !== null && $v !== '')), 'attr') ?>" class="<?= $view === $vk ? 'on' : '' ?>"><?= esc($vl) ?></a>
            <?php endforeach ?>
        </div>
        <?php endif ?>
        <input type="hidden" name="group" id="an-group" value="<?= esc($group, 'attr') ?>">
        <input type="hidden" name="from" id="an-from" value="<?= esc($from, 'attr') ?>">
        <input type="hidden" name="to" id="an-to" value="<?= esc($to, 'attr') ?>">
        <input type="hidden" id="an-allflag" value="">



        <div class="an-dr">
            <button type="button" id="an-dr-btn" class="an-dr-btn" aria-expanded="false">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <span id="an-dr-label"><?= ($from !== '' || $to !== '') ? esc(($from ?: '…') . '  →  ' . ($to ?: '…')) : 'All dates' ?></span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 9l6 6 6-6"/></svg>
            </button>

            <div class="an-pop" id="an-pop" hidden>
                <div class="an-pop-l">
                    <div class="an-pop-title">Select Date Range</div>
                    <div class="an-quick">
                        <button type="button" data-quick="today">Today</button>
                        <button type="button" data-quick="yesterday">Yesterday</button>
                    </div>
                    <label>Weekly<select id="an-week"><option value="">Select Weekly</option></select></label>
                    <label>Monthly<select id="an-month"><option value="">Select Monthly</option></select></label>
                    <button type="button" class="an-custom-btn" id="an-custom">Custom</button>
                    <button type="button" class="an-clear-btn" id="an-all">Show all dates</button>
                </div>
                <div class="an-pop-r">
                    <div class="an-pop-title">Custom Record</div>
                    <div class="an-pick">
                        <input type="text" id="an-pick-from" placeholder="yyyy-mm-dd" readonly>
                        <span>–</span>
                        <input type="text" id="an-pick-to" placeholder="yyyy-mm-dd" readonly>
                    </div>
                    <div class="an-cal">
                        <div class="an-cal-head"><button type="button" id="an-prev" aria-label="Previous month">&#9664;</button><b id="an-cal-title"></b><button type="button" id="an-next" aria-label="Next month">&#9654;</button></div>
                        <div class="an-cal-grid" id="an-cal-grid"></div>
                    </div>
                    <button type="button" class="an-apply" id="an-apply">Apply</button>
                </div>
            </div>
        </div>
    </form>
    <?php if (! empty($empty)): ?><div class="card glass"><p class="muted sm" style="margin:0"><?= esc($empty) ?></p></div><?php endif ?>

<?php if ($view === 'result'): ?>
    <?php
    $sum = ['total' => 0, 'pass' => 0, 'tol' => 0, 'fail' => 0];
    foreach ($suppliers as $s) { foreach ($sum as $k => $_) { $sum[$k] += (int) $s[$k]; } }
    $pc = static fn ($n, $d) => $d > 0 ? number_format($n / $d * 100, 2) . '%' : '0.00%';
    // 3D pie: ellipse top + extruded side, slices start at 12 o'clock and run clockwise
    $pie = static function (array $parts, int $t): string {
        $cx = 220; $cy = 120; $rx = 205; $ry = 98; $dp = 42;
        $pt = static fn (float $a, float $k = 1, int $dy = 0) => [round($cx + $rx * $k * sin(deg2rad($a)), 1), round($cy - $ry * $k * cos(deg2rad($a)) + $dy, 1)];
        $sides = ''; $tops = ''; $lbl = ''; $acc = 0;
        if (array_sum(array_column($parts, 1)) <= 0) {
            return '<svg viewBox="0 0 440 280" width="100%" role="img"><path d="M15,120 v42 A205,98 0 0 0 425,162 v-42 Z" fill="#4a4a4a"/><ellipse cx="220" cy="120" rx="205" ry="98" fill="#6b6b6b"/><text x="220" y="120" text-anchor="middle" dominant-baseline="middle" fill="#e5e7eb" font-size="18" font-weight="700">No tests in this period</text></svg>';
        }
        foreach ($parts as [$n, $v, $col, $dark]) {
            if ($v <= 0) { continue; }
            $a0 = $acc / $t * 360; $acc += $v; $a1 = $acc / $t * 360;
            if ($v >= $t) {
                $sides .= "<path d=\"M" . ($cx - $rx) . ",$cy v$dp A$rx,$ry 0 0 0 " . ($cx + $rx) . "," . ($cy + $dp) . " v-$dp Z\" fill=\"$dark\"/>";
                $tops .= "<ellipse cx=\"$cx\" cy=\"$cy\" rx=\"$rx\" ry=\"$ry\" fill=\"$col\"/>";
            } else {
                $lo = max($a0, 90); $hi = min($a1, 270);
                if ($lo < $hi) {
                    [$x1, $y1] = $pt($lo); [$x2, $y2] = $pt($hi);
                    $sides .= "<path d=\"M$x1,$y1 A$rx,$ry 0 0 1 $x2,$y2 L$x2," . ($y2 + $dp) . " A$rx,$ry 0 0 0 $x1," . ($y1 + $dp) . " Z\" fill=\"$dark\"/>";
                }
                [$x1, $y1] = $pt($a0); [$x2, $y2] = $pt($a1);
                $big = ($a1 - $a0) > 180 ? 1 : 0;
                $tops .= "<path d=\"M$cx,$cy L$x1,$y1 A$rx,$ry 0 $big 1 $x2,$y2 Z\" fill=\"$col\"/>";
            }
            [$lx, $ly] = $pt(($a0 + $a1) / 2, 0.55);
            $lbl .= "<text x=\"$lx\" y=\"$ly\" text-anchor=\"middle\" dominant-baseline=\"middle\" fill=\"#e5e7eb\" font-size=\"19\" font-weight=\"700\">" . round($v / $t * 100) . "%</text>";
        }

        return '<svg viewBox="0 0 440 280" width="100%" role="img">' . $sides . $tops . $lbl . '</svg>';
    };
    ?>
    <div class="br-wrap">
        <?php if (! $suppliers): ?><div class="card glass"><p class="muted sm" style="margin:0">No bonding results with a cement supplier in this period.</p></div><?php else: ?>
        <?php
        $supLabel = static fn (string $n) => match (strtoupper(trim($n))) {
            'NANPAO'  => 'Nan Pao',
            'ZHONGBU' => 'Zhong Bu',
            default   => ucwords(strtolower(trim($n))),
        };
        ?>
        <div style="margin-bottom:20px">
            <h3 style="margin:0 0 3px;font-size:15.5px;font-weight:800;color:#0f172a">Cement Suppliers of Failed Bonding</h3>
            <p style="margin:0 0 12px;font-size:12px;color:#64748b">Compares failure rates between adhesive glue suppliers used on tested shoes.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                <?php foreach ($suppliers as $s): ?>
                    <div style="border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;background:#f8fafc">
                        <div style="font-size:13.5px;font-weight:800;color:#0f172a;margin-bottom:6px"><?= esc($supLabel((string) $s['name'])) ?></div>
                        <div style="display:flex;justify-content:space-between;font-size:12px;color:#475569;margin-bottom:6px">
                            <span>Specimens: <b><?= number_format((int) $s['total']) ?></b></span>
                            <span>Failed: <b style="color:#dc2626"><?= number_format((int) $s['fail']) ?></b></span>
                        </div>
                        <div style="font-size:20px;font-weight:900;color:#dc2626"><?= $pc((int) $s['fail'], (int) $s['total']) ?> <span style="font-size:11.5px;font-weight:600;color:#64748b">Failed</span></div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
        <table class="br-tbl">
            <tr class="hd"><th>CEMENT SUPPLIER</th><th>QTY PASS</th><th>QTY FAILED</th><th>QTY TOLERABLE</th><th>QTY&nbsp; SPECIMENS</th><th>PERCENTAGE FAILED<br>PER CEMENT SUPPLIER</th></tr>
            <?php foreach ($suppliers as $s): ?>
                <tr class="rw"><td class="nm"><?= esc($s['name']) ?></td><td><?= (int) $s['pass'] ?></td><td><?= (int) $s['fail'] ?></td><td><?= (int) $s['tol'] ?></td><td><?= (int) $s['total'] ?></td><td><?= $pc((int) $s['fail'], (int) $s['total']) ?></td></tr>
            <?php endforeach ?>
            <tr class="tot"><td>TOTAL</td><td><?= $sum['pass'] ?></td><td><?= $sum['fail'] ?></td><td><?= $sum['tol'] ?></td><td><?= $sum['total'] ?></td><td class="red"><?= $pc($sum['fail'], $sum['total']) ?></td></tr>
        </table>
        <div class="br-pies">
            <?php foreach ($suppliers as $s): ?>
                <?php $parts = [['QTY PASS', (int) $s['pass'], '#4f94d8', '#2c5f94'], ['QTY FAILED', (int) $s['fail'], '#ee7d2d', '#a8501a'], ['QTY TOLERABLE', (int) $s['tol'], '#a5a5a5', '#6b6b6b']]; ?>
                <div class="br-pie">
                    <h4>CEMENT: <?= esc($s['name']) ?></h4>
                    <?= $pie($parts, max(1, (int) $s['total'])) ?>
                    <div class="br-leg"><?php foreach ($parts as [$n, $_v, $col]): ?><span><i style="background:<?= $col ?>"></i><?= esc($n) ?></span><?php endforeach ?></div>
                </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </div>
<?php elseif ($view === 'monthly'): ?>
    <?php
    $monthName = strtoupper(date('F Y', strtotime(($from !== '' ? $from : ($days ? $days[0]['d'] : date('Y-m-d')))))); // e.g. OCTOBER 2026
    $monthOnly = strtoupper(date('F', strtotime(($from !== '' ? $from : ($days ? $days[0]['d'] : date('Y-m-d'))))));
    $mdY = static fn (string $d) => date('m-d-Y', strtotime($d));
    $totRep = (int) ($status['total'] ?? ((int) $status['pass'] + (int) $status['fail'] + (int) $status['tol']));
    $ratedTot = (int) $status['pass'] + (int) $status['fail'] + (int) $status['tol'];
    $stT = max(1, $ratedTot);
    $passPct = round((int) $status['pass'] / $stT * 100);
    $tolPct  = round((int) $status['tol'] / $stT * 100);
    $failPct = round((int) $status['fail'] / $stT * 100);
    $pendRep = max(0, $totRep - $ratedTot);
    $periodLbl = ($from !== '' || $to !== '') ? ucfirst(strtolower($monthName)) : 'all dates';
    ?>
    <div class="an-top-kpis">
        <div class="card glass ms-kpi-card">
            <div>
                <small>Total Lab Test Reports</small>
                <b><?= number_format($totRep) ?></b>
                <span class="sub">Bonding Shoe Collection &middot; <?= esc($periodLbl) ?><?= $pendRep > 0 ? ' (' . number_format($pendRep) . ' pending)' : '' ?></span>
            </div>
            <span class="ms-kpi-ico ms-ico-blue"><svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-flask"/></svg></span>
        </div>
        <div class="card glass ms-kpi-card">
            <div>
                <small>Passed Reports</small>
                <div class="kpi-val-row">
                    <b class="an-pass"><?= number_format((int) $status['pass']) ?></b>
                    <span class="kpi-pct pct-pass"><?= $passPct ?>%</span>
                </div>
                <span class="sub"><?= $passPct ?>% of rated &middot; Meets VF / Vans Specification</span>
            </div>
            <span class="ms-kpi-ico ms-ico-pass">&#10003;</span>
        </div>
        <div class="card glass ms-kpi-card">
            <div>
                <small>Tolerable / Data</small>
                <div class="kpi-val-row">
                    <b class="an-tol"><?= number_format((int) $status['tol']) ?></b>
                    <span class="kpi-pct pct-tol"><?= $tolPct ?>%</span>
                </div>
                <span class="sub"><?= $tolPct ?>% of rated &middot; Within Tolerance Band</span>
            </div>
            <span class="ms-kpi-ico ms-ico-warn"><svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-bolt"/></svg></span>
        </div>
        <div class="card glass ms-kpi-card">
            <div>
                <small>Failed Reports</small>
                <div class="kpi-val-row">
                    <b class="an-fail"><?= number_format((int) $status['fail']) ?></b>
                    <span class="kpi-pct pct-fail"><?= $failPct ?>%</span>
                </div>
                <span class="sub"><?= $failPct ?>% of rated &middot; Requires Retest / Action</span>
            </div>
            <span class="ms-kpi-ico ms-ico-fail">&#10005;</span>
        </div>
        <div class="card glass ms-kpi-card">
            <div>
                <small>Shutdown Lines</small>
                <b><?= number_format((int) ($status['shut'] ?? 0)) ?></b>
                <span class="sub">lines marked SHUTDOWN &middot; <?= esc($periodLbl) ?></span>
            </div>
            <span class="ms-kpi-ico ms-ico-shut"><svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-bolt"/></svg></span>
        </div>
    </div>
    <?php if (! $days): ?><div class="card glass"><p class="muted sm" style="margin:0">No bonding tests with a production date in this period.</p></div><?php else: ?>

    <?php
    // ---- 1. failure trend (bars: failed + tested, line: % failed) ----
    $W = 1000; $H = 440; $L = 48; $R = 58; $T = 32; $B = 64;
    $pw = $W - $L - $R; $ph = $H - $T - $B;
    $maxT = 5; $maxP = 5;
    foreach ($days as $d) { $maxT = max($maxT, (int) $d['tested']); $maxP = max($maxP, $d['tested'] ? ceil((int) $d['failed'] / (int) $d['tested'] * 100 / 5) * 5 : 0); }
    $maxT = (int) (ceil($maxT / 5) * 5);
    $n = count($days); $slot = $pw / $n; $bw = min(24, $slot * 0.36);
    $yT = static fn ($v) => $T + $ph - $v / $maxT * $ph;
    $yP = static fn ($v) => $T + $ph - $v / $maxP * $ph;
    $svg = '<defs><linearGradient id="gFail" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f87171"/><stop offset="100%" stop-color="#dc2626"/></linearGradient><linearGradient id="gTest" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#fb923c"/><stop offset="100%" stop-color="#ea580c"/></linearGradient></defs>';
    for ($i = 0; $i <= 6; $i++) {
        $gy = $T + $ph - $i / 6 * $ph;
        $svg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $gy . '" y2="' . $gy . '" stroke="rgba(148,163,184,0.18)" stroke-dasharray="4 4" stroke-width="1"/>';
        $svg .= '<text x="' . ($L - 8) . '" y="' . ($gy + 4) . '" text-anchor="end" fill="#94a3b8" font-size="11" font-weight="600">' . round($i / 6 * $maxT, 1) . '</text>';
        $svg .= '<text x="' . ($W - $R + 8) . '" y="' . ($gy + 4) . '" fill="#94a3b8" font-size="11" font-weight="600">' . number_format($i / 6 * $maxP, 2) . '%</text>';
    }
    $pts = []; $dots = ''; $lbls = '';
    foreach ($days as $i => $d) {
        $cx = $L + $slot * ($i + 0.5); $te = (int) $d['tested']; $fa = (int) $d['failed'];
        $hFa = max(0, $T + $ph - $yT($fa));
        $hTe = max(0, $T + $ph - $yT($te));
        $svg .= '<rect x="' . ($cx - $bw - 1.5) . '" y="' . $yT($fa) . '" width="' . $bw . '" height="' . $hFa . '" rx="3" fill="url(#gFail)"/>';
        $svg .= '<rect x="' . ($cx + 1.5) . '" y="' . $yT($te) . '" width="' . $bw . '" height="' . $hTe . '" rx="3" fill="url(#gTest)"/>';
        $svg .= '<text x="' . ($cx - $bw / 2 - 1.5) . '" y="' . ($yT($fa) - 5) . '" text-anchor="middle" fill="#fca5a5" font-size="10.5" font-weight="800">' . $fa . '</text>';
        $svg .= '<text x="' . ($cx + $bw / 2 + 1.5) . '" y="' . ($yT($te) - 5) . '" text-anchor="middle" fill="#fdba74" font-size="10.5" font-weight="800">' . $te . '</text>';
        $pv = $te ? $fa / $te * 100 : 0;
        $py = round($yP($pv), 1);
        $pts[] = round($cx, 1) . ',' . $py;
        $dots .= '<circle cx="' . round($cx, 1) . '" cy="' . $py . '" r="3.5" fill="#38bdf8" stroke="#0f172a" stroke-width="1.8"/>';
        $lbls .= '<text x="' . $cx . '" y="' . ($py - 8) . '" text-anchor="middle" fill="#f8fafc" font-size="10.5" font-weight="800" stroke="#0f172a" stroke-width="3" paint-order="stroke">' . number_format($pv, 2) . '%</text>';
        $svg .= '<text transform="translate(' . ($cx + 3) . ',' . ($T + $ph + 14) . ') rotate(-38)" text-anchor="end" fill="#cbd5e1" font-size="10.5" font-weight="600">' . $mdY($d['d']) . '</text>';
    }
    $svg .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="#38bdf8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>' . $dots . $lbls;

    // ---- 2. status summary donut ----
    $sp = [['PASS', (int) $status['pass'], '#10b981'], ['FAILED', (int) $status['fail'], '#ef4444'], ['TOLERABLE', (int) $status['tol'], '#f59e0b']];
    $cxp = 160; $cyp = 152; $rp = 114; $acc = 0; $ps = ''; $pl = '';
    foreach ($sp as [$nm, $v, $col]) {
        if ($v <= 0) { continue; }
        $a0 = $acc / $stT * 360; $acc += $v; $a1 = $acc / $stT * 360;
        if ($v >= $stT) { $ps .= '<circle cx="' . $cxp . '" cy="' . $cyp . '" r="' . $rp . '" fill="' . $col . '"/>'; }
        else {
            $x0 = $cxp + $rp * sin(deg2rad($a0)); $y0 = $cyp - $rp * cos(deg2rad($a0)); $x1 = $cxp + $rp * sin(deg2rad($a1)); $y1 = $cyp - $rp * cos(deg2rad($a1));
            $ps .= '<path d="M' . $cxp . ',' . $cyp . ' L' . round($x0, 1) . ',' . round($y0, 1) . ' A' . $rp . ',' . $rp . ' 0 ' . (($a1 - $a0) > 180 ? 1 : 0) . ' 1 ' . round($x1, 1) . ',' . round($y1, 1) . ' Z" fill="' . $col . '" stroke="#fff" stroke-width="2.5"/>';
        }
        $mid = deg2rad(($a0 + $a1) / 2);
        $lx = $cxp + ($rp + 27) * sin($mid); $ly = $cyp - ($rp + 20) * cos($mid);
        $pl .= '<g transform="translate(' . round($lx, 1) . ',' . round($ly, 1) . ')"><text y="-2" text-anchor="middle" font-size="11.5" font-weight="800" fill="#0f172a" stroke="#fff" stroke-width="3.5" paint-order="stroke">' . $nm . '</text><text y="12" text-anchor="middle" font-size="12" font-weight="800" fill="' . $col . '" stroke="#fff" stroke-width="3.5" paint-order="stroke">' . round($v / $stT * 100) . '%</text></g>';
    }
    $ps .= '<circle cx="' . $cxp . '" cy="' . $cyp . '" r="52" fill="#fff"/><text x="' . $cxp . '" y="' . ($cyp - 3) . '" text-anchor="middle" font-size="22" font-weight="800" fill="#0f172a">' . number_format($ratedTot) . '</text><text x="' . $cxp . '" y="' . ($cyp + 14) . '" text-anchor="middle" font-size="10" font-weight="800" letter-spacing="0.06em" fill="#64748b">RATED</text>';
    ?>
    <div class="ms-chart-grid">
        <div class="ms-pie-card">
            <div class="ms-pie-head">
                <h3><?= ucfirst(strtolower($monthOnly)) ?> Summary</h3>
                <span><?= number_format($ratedTot) ?> Rated</span>
            </div>
            <div class="ms-pie-body">
                <svg viewBox="-12 8 344 288" width="100%" style="display:block;width:100%;max-width:345px;height:auto;margin:auto;font-family:Inter,system-ui,sans-serif"><?= $ps . $pl ?></svg>
            </div>
        </div>

        <div class="ms-trend-card">
            <div class="ms-trend-head">
                <div>
                    <h3>Bonding Failure Trend by Production Date &mdash; <?= esc($monthName) ?></h3>
                    <p>Daily tested specimens vs. bonding failures and failure rate percentage</p>
                </div>
                <div class="ms-trend-leg">
                    <span><i style="background:#ef4444"></i>Failed Specimen</span>
                    <span><i style="background:#f97316"></i>Tested Specimen</span>
                    <span><i class="ln"></i>Percentage Failed</span>
                </div>
            </div>
            <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" style="display:block;font-family:Inter,system-ui,sans-serif"><?= $svg ?></svg>
        </div>
    </div>

    <?php
    // ---- 3. failed specimens per line and production date (Building A & B tabs) ----
    $withTests = array_column($days, 'd');
    $rangeFrom = $from !== '' ? strtotime($from) : strtotime(date('Y-m-01', strtotime($withTests[0])));
    $rangeTo   = ($to ?? '') !== '' ? strtotime($to) : strtotime(date('Y-m-t', $rangeFrom));
    $dates = [];
    for ($t = $rangeFrom; $t <= $rangeTo && count($dates) < 62; $t = strtotime('+1 day', $t)) { $dates[] = date('Y-m-d', $t); }
    $totF = array_fill_keys($dates, 0);
    $totT = array_fill_keys($dates, 0);
    $grand = 0; $grandT = 0;
    foreach ($days as $d) { $totT[$d['d']] = (int) $d['tested']; $grandT += (int) $d['tested']; }
    $lastD = end($withTests);
    $mdHead = static fn (string $d) => count($dates) > 10 ? date('m-d', strtotime($d)) : date('m-d-Y', strtotime($d));

    $bldStats = [];
    foreach ($lines as $bld => $nums) {
        $bTot = array_fill_keys($dates, 0);
        $rowT = [];
        foreach ($nums as $nn) {
            $rowT[$nn] = 0;
            foreach ($dates as $d) {
                $c = (int) ($cells[$bld][$nn][$d] ?? 0);
                $rowT[$nn] += $c;
                $bTot[$d]  += $c;
                $totF[$d]   = ($totF[$d] ?? 0) + $c;
            }
        }
        $bSum = array_sum($rowT);
        $grand += $bSum;
        $bldStats[$bld] = [
            'nums'  => $nums,
            'bTot'  => $bTot,
            'rowT'  => $rowT,
            'mxRow' => $rowT ? max($rowT) : 0,
            'sum'   => $bSum,
        ];
    }
    $bldKeys = array_keys($bldStats);
    $firstBld = $bldKeys[0] ?? 'A';
    ?>
    <div class="mx-card" id="mx-bld-card">
        <div class="mx-head">
            <div class="mx-head-l">
                <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                    Count of Bonding-Failed Specimens by Line &amp; Production Date &mdash; <?= esc($monthName) ?>
                </h3>
                <p>As of <?= esc($lastD) ?> &middot; Total Failed: <b><?= number_format($grand) ?></b> of <b><?= number_format($grandT) ?></b> Tested Specimens (<b><?= $grandT ? number_format($grand / $grandT * 100, 2) . '%' : '0.00%' ?></b>)</p>
            </div>
            <div class="mx-head-r">
                <div class="mx-legend">
                    <span><i style="background:#ffe4e6;border:1px solid #fda4af"></i>Sunday</span>
                    <span><i style="background:#dc2626"></i>Failed &gt; 0</span>
                    <span><i style="background:#ea580c"></i>Highest Line</span>
                </div>
                <div class="bld-toggle" role="tablist" aria-label="Select Building">
                    <?php foreach ($bldStats as $bld => $stB): ?>
                        <button type="button" role="tab" class="bld-btn <?= $bld === $firstBld ? 'on' : '' ?>" data-bld-target="<?= esc($bld, 'attr') ?>" aria-selected="<?= $bld === $firstBld ? 'true' : 'false' ?>">
                            Building <?= esc($bld) ?>
                            <span class="bld-pill <?= $stB['sum'] === 0 ? 'zero' : '' ?>" title="<?= $stB['sum'] ?> failed specimen(s) in Building <?= esc($bld) ?>"><?= number_format($stB['sum']) ?></span>
                        </button>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <?php foreach ($bldStats as $bld => $stB): ?>
            <?php
            $nums  = $stB['nums'];
            $bTot  = $stB['bTot'];
            $rowT  = $stB['rowT'];
            $mxRow = $stB['mxRow'];
            ?>
            <div class="bld-pane" data-bld-pane="<?= esc($bld, 'attr') ?>" <?= $bld === $firstBld ? '' : 'hidden' ?>>
                <div class="mx-scroll">
                    <table class="mx-tbl" style="min-width:<?= 380 + count($dates) * 44 ?>px">
                        <colgroup>
                            <col style="width:276px">
                            <?php foreach ($dates as $d): ?><col<?= date('w', strtotime($d)) === '0' ? ' class="sun"' : '' ?>><?php endforeach ?>
                            <col style="width:104px">
                        </colgroup>
                        <thead>
                            <tr class="dh">
                                <th class="lc">BUILDING <?= esc($bld) ?> &middot; PRODUCTION DATE</th>
                                <?php foreach ($dates as $d): ?>
                                    <th<?= date('w', strtotime($d)) === '0' ? ' class="sun"' : '' ?>><?= $mdHead($d) ?></th>
                                <?php endforeach ?>
                                <th class="tc">TOTAL FAILED<br>PER LINE AS OF<br><?= esc($lastD) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nums as $nn): ?>
                                <tr>
                                    <td class="lc"><span class="line-tag"><?= esc($bld . $nn) ?></span></td>
                                    <?php foreach ($dates as $d):
                                        $c = (int) ($cells[$bld][$nn][$d] ?? 0);
                                        $isSun = date('w', strtotime($d)) === '0';
                                    ?>
                                        <?php if (! empty($shut[$bld][$nn][$d]) && $c === 0): ?>
                                            <td class="sd" title="Shutdown">SHUTDOWN</td>
                                        <?php elseif ($c > 0): ?>
                                            <td class="has-fail<?= $isSun ? ' sun-cell' : '' ?>"><span><?= $c ?></span></td>
                                        <?php else: ?>
                                            <td class="z<?= $isSun ? ' sun-cell' : '' ?>">0</td>
                                        <?php endif ?>
                                    <?php endforeach ?>
                                    <td class="tc<?= ($mxRow > 0 && $rowT[$nn] === $mxRow) ? ' mx' : '' ?>"><?= $rowT[$nn] ?></td>
                                </tr>
                            <?php endforeach ?>
                            <tr class="bt">
                                <td class="lc">TOTAL FAILED BLDG <?= esc($bld) ?></td>
                                <?php foreach ($dates as $d): $bv = (int) $bTot[$d]; ?>
                                    <td<?= $bv === 0 ? ' class="z-sum"' : '' ?>><?= $bv ?></td>
                                <?php endforeach ?>
                                <td class="tc blue"><?= $stB['sum'] ?></td>
                            </tr>
                            <tr class="rd">
                                <td class="lc">TOTAL FAILED PER PRODUCTION DATE</td>
                                <?php foreach ($dates as $d): $fv = (int) ($totF[$d] ?? 0); ?>
                                    <td<?= $fv === 0 ? ' class="z-sum"' : '' ?>><?= $fv ?></td>
                                <?php endforeach ?>
                                <td class="tc"><?= $grand ?></td>
                            </tr>
                            <tr class="rt">
                                <td class="lc">TOTAL TESTED PER PRODUCTION DATE</td>
                                <?php foreach ($dates as $d): $tv = (int) $totT[$d]; ?>
                                    <td<?= $tv === 0 ? ' class="z-sum"' : '' ?>><?= $tv ?></td>
                                <?php endforeach ?>
                                <td class="tc"><?= $grandT ?></td>
                            </tr>
                            <tr class="rd pct-row">
                                <td class="lc">TOTAL FAILURE PERCENTAGE PER PRODUCTION DATE</td>
                                <?php foreach ($dates as $d):
                                    $fv = (int) ($totF[$d] ?? 0);
                                    $tv = (int) $totT[$d];
                                    $hasPct = $tv > 0 && $fv > 0;
                                ?>
                                    <td class="<?= $hasPct ? 'has-pct' : 'z-sum' ?>"><?= $tv ? number_format($fv / $tv * 100, 2) . '%' : '0.00%' ?></td>
                                <?php endforeach ?>
                                <td class="tc"><?= $grandT ? number_format($grand / $grandT * 100, 2) . '%' : '0.00%' ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <?php endif ?>
<?php elseif ($view === 'daily'): ?>
    <?php include __DIR__ . '/bonding_daily.php'; ?>
<?php else: ?>
    <div class="an-kpis">
        <?php foreach ($kpis as $k): ?>
            <?php
            $icoCls = match ($k[3] ?? '') {
                'an-pass' => 'ms-ico-pass',
                'an-tol'  => 'ms-ico-warn',
                'an-fail' => 'ms-ico-fail',
                default   => 'ms-ico-blue',
            };
            $icoSym = match ($k[3] ?? '') {
                'an-pass' => '&#10003;',
                'an-tol'  => '<svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-bolt"/></svg>',
                'an-fail' => '&#10005;',
                default   => '<svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-flask"/></svg>',
            };
            ?>
            <div class="card glass ms-kpi-card">
                <div>
                    <small><?= esc($k[0]) ?></small>
                    <b class="<?= esc($k[3]) ?>"><?= esc($k[1]) ?></b>
                    <span class="sub"><?= esc($k[2]) ?></span>
                </div>
                <span class="ms-kpi-ico <?= $icoCls ?>"><?= $icoSym ?></span>
            </div>
        <?php endforeach ?>
    </div>

    <div class="card glass" style="padding:18px 20px;border-radius:16px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px">
            <div>
                <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a"><?= esc($chartTitle) ?></h3>
                <span style="font-size:11.5px;color:#64748b">Distribution of test volume and results across the selected period</span>
            </div>
            <?php if ($segmented): ?>
                <div class="an-legend" style="margin:0;font-weight:600">
                    <span><i class="an-p"></i>Pass</span>
                    <span><i class="an-t"></i>Tolerable</span>
                    <span><i class="an-f"></i>Fail</span>
                </div>
            <?php endif ?>
        </div>
        <?php if (! $months): ?>
            <p class="muted sm">No dated records yet.</p>
        <?php else: ?>
            <div class="an-bars">
                <?php foreach ($months as $m): ?>
                    <?php $t = (int) $m['total']; $h = $t / $maxM * 100; ?>
                    <div class="an-col" title="<?= esc($m['m']) ?>: <?= $segmented ? (int) $m['pass'] . ' pass, ' . (int) $m['tol'] . ' tolerable, ' . (int) $m['fail'] . ' fail' : $t ?>">
                        <span class="an-num"><?= $t ?></span>
                        <div class="an-stack" style="height:<?= round($h * 1.35, 1) ?>px">
                            <?php if ($segmented): ?>
                                <i class="an-p" style="height:<?= $t ? round($m['pass'] / $t * 100, 2) : 0 ?>%"></i>
                                <i class="an-t" style="height:<?= $t ? round($m['tol'] / $t * 100, 2) : 0 ?>%"></i>
                                <i class="an-f" style="height:<?= $t ? round($m['fail'] / $t * 100, 2) : 0 ?>%"></i>
                            <?php else: ?>
                                <i class="an-b" style="height:100%"></i>
                            <?php endif ?>
                        </div>
                        <span class="an-lbl"><?= esc($group === 'day' ? substr((string) $m['m'], 5) : $m['m']) ?></span>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

    <div class="an-two">
        <?php foreach ($lists as [$title, $mode, $rows]): ?>
            <div class="card glass" style="padding:18px 20px;border-radius:16px">
                <h3 style="margin:0 0 12px;font-size:14.5px;font-weight:800;color:#0f172a"><?= esc($title) ?></h3>
                <?php $mx = max(1, (int) ($rows[0]['a'] ?? 1)); foreach ($rows as $r): ?>
                    <?php $w = $mode === 'ratio' ? ((int) $r['b'] > 0 ? round((int) $r['a'] / (int) $r['b'] * 100, 1) : 0) : round((int) $r['a'] / $mx * 100); ?>
                    <div class="an-row" style="padding:7px 0"><span class="nm" style="font-weight:600;color:#1e293b" title="<?= esc($r['name'], 'attr') ?>"><?= esc($r['name']) ?></span>
                        <span class="an-meter"><i class="<?= $mode === 'ratio' ? '' : 'an-neutral' ?>" style="width:<?= $w ?>%"></i></span>
                        <span class="v" style="font-size:12px;color:#0f172a"><?= (int) $r['a'] ?><?= $mode === 'ratio' ? '<span style="color:#64748b;font-weight:500">/' . (int) $r['b'] . '</span>' : '' ?></span></div>
                <?php endforeach ?>
                <?php if (! $rows): ?><p class="muted sm">No data.</p><?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
</div>

<script>
(function () {
  var bldCard = document.getElementById('mx-bld-card');
  if (bldCard) {
    var btns = bldCard.querySelectorAll('[data-bld-target]');
    var panes = bldCard.querySelectorAll('[data-bld-pane]');
    btns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = btn.getAttribute('data-bld-target');
        btns.forEach(function (b) {
          var active = b.getAttribute('data-bld-target') === target;
          b.classList.toggle('on', active);
          b.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panes.forEach(function (p) {
          p.hidden = p.getAttribute('data-bld-pane') !== target;
        });
      });
    });
  }
  var form = document.getElementById('an-form'); if (!form) return;
  var $ = function (id) { return document.getElementById(id); };
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };
  var iso = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
  var parse = function (s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); };
  var today = parse(form.dataset.today);
  var MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  var short = function (d) { return MONTHS[d.getMonth()].slice(0, 3) + ' ' + d.getDate(); };

  var go = function (from, to, group) {
    $('an-from').value = from; $('an-to').value = to;
    if (group) $('an-group').value = group;
    if (!from && !to) { var f = $('an-allflag'); f.name = 'all'; f.value = '1'; }   // explicit All dates
    form.submit();
  };

  // popover
  var pop = $('an-pop'), btn = $('an-dr-btn');
  btn.addEventListener('click', function (e) { e.stopPropagation(); pop.hidden = !pop.hidden; btn.setAttribute('aria-expanded', String(!pop.hidden)); });
  document.addEventListener('click', function (e) { if (!pop.hidden && !pop.contains(e.target) && !btn.contains(e.target)) { pop.hidden = true; } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') pop.hidden = true; });

  // quick picks
  form.querySelectorAll('[data-quick]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = new Date(today); if (b.dataset.quick === 'yesterday') d.setDate(d.getDate() - 1);
      go(iso(d), iso(d), 'day');
    });
  });

  // weekly (Monday - Sunday, last 12 weeks), monthly (last 12)
  var wk = $('an-week'), mo = $('an-month');
  var mon = new Date(today); mon.setDate(mon.getDate() - ((mon.getDay() + 6) % 7));
  for (var i = 0; i < 12; i++) {
    var s = new Date(mon); s.setDate(s.getDate() - 7 * i); var e = new Date(s); e.setDate(e.getDate() + 6);
    wk.add(new Option(short(s) + ' – ' + short(e) + (i === 0 ? '  (this week)' : ''), iso(s) + '|' + iso(e)));
  }
  for (var m = 0; m < 12; m++) {
    var ms = new Date(today.getFullYear(), today.getMonth() - m, 1), me = new Date(ms.getFullYear(), ms.getMonth() + 1, 0);
    mo.add(new Option(MONTHS[ms.getMonth()] + ' ' + ms.getFullYear(), iso(ms) + '|' + iso(me)));
  }
  var pick = function (sel, group) { sel.addEventListener('change', function () { if (!sel.value) return; var p = sel.value.split('|'); go(p[0], p[1], group); }); };
  pick(wk, 'day'); pick(mo, 'day');
  $('an-all').addEventListener('click', function () { go('', '', 'month'); });

  // custom range calendar
  var from = $('an-from').value ? parse($('an-from').value) : null, to = $('an-to').value ? parse($('an-to').value) : null;
  var view = new Date((to || from || today).getFullYear(), (to || from || today).getMonth(), 1);
  var grid = $('an-cal-grid');
  var sync = function () { $('an-pick-from').value = from ? iso(from) : ''; $('an-pick-to').value = to ? iso(to) : ''; };
  var draw = function () {
    $('an-cal-title').textContent = MONTHS[view.getMonth()] + ' ' + view.getFullYear();
    var h = ['S','M','T','W','T','F','S'].map(function (x) { return '<span class="dow">' + x + '</span>'; }).join('');
    var first = new Date(view.getFullYear(), view.getMonth(), 1), start = new Date(first); start.setDate(1 - first.getDay());
    for (var k = 0; k < 42; k++) {
      var d = new Date(start); d.setDate(start.getDate() + k);
      var cls = [];
      if (d.getMonth() !== view.getMonth()) cls.push('out');
      if (iso(d) === iso(today)) cls.push('today');
      if (from && iso(d) === iso(from)) cls.push('edge');
      if (to && iso(d) === iso(to)) cls.push('edge');
      if (from && to && d > from && d < to) cls.push('mid');
      h += '<button type="button" data-d="' + iso(d) + '" class="' + cls.join(' ') + '">' + d.getDate() + '</button>';
    }
    grid.innerHTML = h; sync();
  };
  grid.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-d]'); if (!b) return;
    var d = parse(b.dataset.d);
    if (!from || (from && to)) { from = d; to = null; }
    else if (d < from) { to = from; from = d; }
    else { to = d; }
    draw();
  });
  $('an-prev').addEventListener('click', function () { view.setMonth(view.getMonth() - 1); draw(); });
  $('an-next').addEventListener('click', function () { view.setMonth(view.getMonth() + 1); draw(); });
  $('an-custom').addEventListener('click', function () { this.classList.add('on'); });
  $('an-apply').addEventListener('click', function () {
    if (!from) return;
    var f = from, t = to || from, days = Math.round((t - f) / 86400000) + 1;
    go(iso(f), iso(t), days <= 31 ? 'day' : (days <= 120 ? 'week' : 'month'));
  });
  draw();
})();
</script>
