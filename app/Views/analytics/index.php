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
.an-legend i{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:5px;vertical-align:middle}
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
.br-wrap{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:22px 24px}
.br-tbl{border-collapse:collapse;width:86%;margin:0 0 18px 0;background:#fff;font-family:Arial,Helvetica,sans-serif;color:#000}
.br-tbl th,.br-tbl td{border:1px solid #000;text-align:center;padding:2px 8px}
.br-tbl tr.hd th{background:#d9d9d9;font-size:12px;font-weight:700;padding:14px 8px;border-width:2px 1px 1px}
.br-tbl tr.hd th:first-child,.br-tbl tr.hd th:last-child{border-left-width:2px}
.br-tbl tr.rw td{font-size:10.5px;font-weight:700;height:22px}
.br-tbl tr.rw td.nm{background:#d9d9d9;font-weight:800}
.br-tbl tr.tot td{background:#d9d9d9;font-weight:800;font-size:18px;border-bottom-width:2px}
.br-tbl tr.tot td.red{color:#f00}
.br-tbl td:first-child{border-left-width:2px}.br-tbl td:last-child{border-right-width:2px}.br-tbl th:last-child{border-right-width:2px}
.br-pies{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:20px}
.br-pie{background:radial-gradient(ellipse at 50% 20%,#5b5b5b 0,#2e2e2e 60%,#1c1c1c 100%);padding:22px 20px 14px;color:#fff;text-align:center}
.br-pie h4{margin:0 0 6px;font-size:16px;font-weight:700;color:#f1f1f1}
.br-pie svg{display:block;max-width:480px;margin:0 auto}
.br-leg{display:flex;justify-content:center;gap:22px;margin-top:6px;font-size:12px;color:#d4d4d4;font-family:Arial,sans-serif}
.br-leg i{display:inline-block;width:9px;height:9px;margin-right:5px}</style>

<div class="an">
    <form method="get" action="<?= site_url('analytics') ?>" class="an-filter" id="an-form" data-today="<?= date('Y-m-d') ?>">
        <input type="hidden" name="tab" value="<?= esc($tab, 'attr') ?>">
        <?php if ($tab === 'bonding'): ?>
        <input type="hidden" name="view" value="<?= esc($view, 'attr') ?>">
        <div class="an-tabs" style="margin-right:auto">
            <?php foreach (['analytics' => 'Bonding Analytics', 'result' => 'Bonding Result'] as $vk => $vl): ?>
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
                    <label>Yearly<select id="an-year"><option value="">Select Yearly</option></select></label>
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
<?php else: ?>
    <div class="an-kpis">
        <?php foreach ($kpis as $k): ?>
            <div class="card glass an-kpi"><small><?= esc($k[0]) ?></small><b class="<?= esc($k[3]) ?>"><?= esc($k[1]) ?></b><span><?= esc($k[2]) ?></span></div>
        <?php endforeach ?>
    </div>

    <div class="card glass">
        <h3 style="margin:0 0 10px"><?= esc($chartTitle) ?></h3>
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
            <?php if ($segmented): ?><div class="an-legend"><span><i class="an-p"></i>Pass</span><span><i class="an-t"></i>Tolerable</span><span><i class="an-f"></i>Fail</span></div><?php endif ?>
        <?php endif ?>
    </div>

    <div class="an-two">
        <?php foreach ($lists as [$title, $mode, $rows]): ?>
            <div class="card glass">
                <h3 style="margin:0 0 8px"><?= esc($title) ?></h3>
                <?php $mx = max(1, (int) ($rows[0]['a'] ?? 1)); foreach ($rows as $r): ?>
                    <?php $w = $mode === 'ratio' ? ((int) $r['b'] > 0 ? round((int) $r['a'] / (int) $r['b'] * 100, 1) : 0) : round((int) $r['a'] / $mx * 100); ?>
                    <div class="an-row"><span class="nm" title="<?= esc($r['name'], 'attr') ?>"><?= esc($r['name']) ?></span>
                        <span class="an-meter"><i class="<?= $mode === 'ratio' ? '' : 'an-neutral' ?>" style="width:<?= $w ?>%"></i></span>
                        <span class="v"><?= (int) $r['a'] ?><?= $mode === 'ratio' ? '/' . (int) $r['b'] : '' ?></span></div>
                <?php endforeach ?>
                <?php if (! $rows): ?><p class="muted sm">No data.</p><?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
</div>

<script>
(function () {
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

  // weekly (Monday - Sunday, last 12 weeks), monthly (last 12), yearly (last 6)
  var wk = $('an-week'), mo = $('an-month'), yr = $('an-year');
  var mon = new Date(today); mon.setDate(mon.getDate() - ((mon.getDay() + 6) % 7));
  for (var i = 0; i < 12; i++) {
    var s = new Date(mon); s.setDate(s.getDate() - 7 * i); var e = new Date(s); e.setDate(e.getDate() + 6);
    wk.add(new Option(short(s) + ' – ' + short(e) + (i === 0 ? '  (this week)' : ''), iso(s) + '|' + iso(e)));
  }
  for (var m = 0; m < 12; m++) {
    var ms = new Date(today.getFullYear(), today.getMonth() - m, 1), me = new Date(ms.getFullYear(), ms.getMonth() + 1, 0);
    mo.add(new Option(MONTHS[ms.getMonth()] + ' ' + ms.getFullYear(), iso(ms) + '|' + iso(me)));
  }
  for (var y = 0; y < 6; y++) { var yy = today.getFullYear() - y; yr.add(new Option(String(yy), yy + '-01-01|' + yy + '-12-31')); }
  var pick = function (sel, group) { sel.addEventListener('change', function () { if (!sel.value) return; var p = sel.value.split('|'); go(p[0], p[1], group); }); };
  pick(wk, 'day'); pick(mo, 'day'); pick(yr, 'month');
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
