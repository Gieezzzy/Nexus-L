<?php
/** Analytics page. Data: $tab, $group, $from, $to, $kpis, $chartTitle, $months, $lists, [$empty] */
$maxM = 1;
foreach ($months as $m) {
    $maxM = max($maxM, (int) $m['total']);
}
$tabs = ['bonding' => 'Shoe Bonding', 'wholeshoe' => 'Whole Shoe', 'materials' => 'Materials'];
$segmented = $months && array_key_exists('pass', $months[0]);

// Plain-language helpers shared by every Analytics view (included files reuse them).
if (! function_exists('anTip')) {
    /** Small "i" badge; the explanation shows on hover, keyboard focus or tap. */
    function anTip(string $text): string
    {
        $t = esc($text, 'attr');

        return ' <span class="an-i" tabindex="0" role="note" aria-label="' . $t . '" data-tip="' . $t . '">i</span>';
    }
}
if (! function_exists('anSummary')) {
    /** Executive status insight banner. $tone: ok | warn | bad | info. */
    function anSummary(string $headline, string $detail = '', string $tone = 'info'): string
    {
        $tone = in_array($tone, ['ok', 'warn', 'bad', 'info'], true) ? $tone : 'info';
        // Normalize any raw en-dash / em-dash to safe HTML entity / ASCII dash to prevent charset mojibake
        $headline = str_replace(["\xE2\x80\x93", "\xE2\x80\x94", 'â€“', 'â€”'], '-', $headline);
        $detail   = str_replace(["\xE2\x80\x93", "\xE2\x80\x94", 'â€“', 'â€”'], '-', $detail);

        $hasScale = stripos($detail, '95%') !== false && stripos($detail, '85%') !== false;
        if ($hasScale) {
            $detail = trim((string) preg_replace('/\s*A pass rate of 95% or more is good;\s*below 85% needs action\.?\s*/i', ' ', $detail));
        }

        $badgeLabel = match ($tone) {
            'ok'   => 'On Target',
            'warn' => 'Needs Watch',
            'bad'  => 'Action Needed',
            default => 'Summary',
        };
        $iconSvg = match ($tone) {
            'ok'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'warn' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            'bad'  => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
            default => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        };

        // Emphasize leading percentage in the headline (e.g., "92.1%")
        $hlEsc = esc($headline);
        $hlHtml = preg_replace('/^(\d+(?:\.\d+)?%)/', '<em class="an-sum-pct">$1</em>', $hlEsc, 1) ?? $hlEsc;

        $scaleHtml = '';
        if ($hasScale) {
            $scaleHtml = '<div class="an-sum-scale" aria-label="Pass rate target guide">'
                . '<span class="an-sum-scale-lbl">Pass Rate Guide</span>'
                . '<div class="an-sum-tiers">'
                . '<span class="an-tier t-ok' . ($tone === 'ok' ? ' on' : '') . '"><i></i>&ge;95% Good</span>'
                . '<span class="an-tier t-warn' . ($tone === 'warn' ? ' on' : '') . '"><i></i>85&ndash;94% Watch</span>'
                . '<span class="an-tier t-bad' . ($tone === 'bad' ? ' on' : '') . '"><i></i>&lt;85% Action</span>'
                . '</div></div>';
        }

        return '<div class="an-sum an-sum-' . esc($tone, 'attr') . '" role="status">'
            . '<div class="an-sum-ico" aria-hidden="true">' . $iconSvg . '</div>'
            . '<div class="an-sum-body">'
            . '<div class="an-sum-top"><span class="an-sum-badge"><i></i>' . $badgeLabel . '</span><b>' . $hlHtml . '</b></div>'
            . ($detail !== '' ? '<p class="an-sum-det">' . esc($detail) . '</p>' : '')
            . '</div>'
            . $scaleHtml
            . '</div>';
    }
}
if (! function_exists('anTone')) {
    /** Colour tone for a pass rate (%). */
    function anTone(float $passPct): string
    {
        return $passPct >= 95 ? 'ok' : ($passPct >= 85 ? 'warn' : 'bad');
    }
}
?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/analytics-index-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/analytics-index-1.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/datefilter.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/datefilter.css') ?>">
<script src="<?= base_url('assets/js/datefilter.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/datefilter.js') ?>"></script>

<div class="an">
    <form method="get" action="<?= site_url('analytics') ?>" class="an-filter" id="an-form" data-today="<?= date('Y-m-d') ?>">
        <input type="hidden" name="tab" value="<?= esc($tab, 'attr') ?>">
        <input type="hidden" name="view" value="<?= esc($view, 'attr') ?>">
        <?php
        $areaSubTabs = match ($tab) {
            'wholeshoe' => ['analytics' => 'Monthly Summary', 'monthfail' => 'Monthly Failure by Test'],
            'materials' => ['analytics' => 'Monthly Summary', 'hotspots' => 'Parameters & Supplier Scorecard', 'batches' => 'Failed & Tolerable Batches', 'workload' => 'Material Intake (IQC)'],
            default     => ['monthly' => 'Monthly Summary', 'workload' => 'Daily Workload', 'daily' => 'Daily Bonding', 'pulltest' => 'Rivets Monitoring', 'failure' => 'Bonding Failure Overview', 'retest' => 'Retest', 'result' => 'Cement Analytics', 'utm' => 'Universal Tensile Machines Daily Productivity'],
        };
        ?>
        <div class="an-tabs" style="margin-right:auto">
            <?php foreach ($areaSubTabs as $vk => $vl): ?>
                <?php /* Daily Workload opens on today; Rivets Monitoring opens on the full-year summary (calendar filter narrows it) */ ?>
                <a href="<?= esc(site_url('analytics') . '?' . http_build_query(in_array($vk, ['workload', 'pulltest'], true) ? ['tab' => $tab, 'view' => $vk] : array_filter(['tab' => $tab, 'view' => $vk, 'group' => $group, 'from' => $from, 'to' => $to, 'all' => ($from === '' && $to === '') ? '1' : null], static fn ($v) => $v !== null && $v !== '')), 'attr') ?>" class="<?= $view === $vk ? 'on' : '' ?>"><?= esc($vl) ?></a>
            <?php endforeach ?>
        </div>
        <input type="hidden" name="group" id="an-group" value="<?= esc($group, 'attr') ?>">
        <input type="hidden" name="from" id="an-from" value="<?= esc($from, 'attr') ?>">
        <input type="hidden" name="to" id="an-to" value="<?= esc($to, 'attr') ?>">
        <input type="hidden" id="an-allflag" value="">

        <?php
        $isAreaMonthly = ($tab === 'bonding' && $view === 'monthly') || (in_array($tab, ['wholeshoe', 'materials'], true) && $view === 'analytics');
        ?>
        <?php if ($isAreaMonthly): ?>
            <button type="button" id="btn-ms-yearly" class="an-dr-btn" title="Show whole-year monthly pie charts" style="margin-right:6px;padding:8px 13px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                <span>Yearly Pie Summary</span>
            </button>
        <?php endif ?>
        <?php
        $yrParam = $from !== '' ? substr($from, 0, 4) : ($to !== '' ? substr($to, 0, 4) : date('Y'));
        $dlUrl   = null;
        $dlTitle = 'Download Monthly Summary Report (12-Month Pie Charts) as Excel';
        if ($isAreaMonthly) {
            $dlUrl = site_url('analytics/export-yearly-pies') . '?tab=' . urlencode($tab) . '&year=' . urlencode($yrParam);
        } elseif ($tab === 'bonding' && in_array($view, ['daily', 'failure'], true)) {
            $dlRoute = $view === 'daily' ? 'analytics/export-failed-portion' : 'analytics/export-failure-overview';
            $dlTitle = $view === 'daily'
                ? 'Download the Overall Failed Portion Times Monitoring of these dates as an Excel file'
                : 'Download Excel report for the selected dates';
            $dlUrl = site_url($dlRoute) . '?' . http_build_query(array_filter(['from' => $from, 'to' => $to, 'all' => ($from === '' && $to === '') ? '1' : null], static fn ($v) => $v !== null && $v !== ''));
        }
        ?>
        <?php if ($dlUrl !== null): ?>
            <a class="an-dr-btn an-dl-btn" href="<?= esc($dlUrl, 'attr') ?>" title="<?= esc($dlTitle, 'attr') ?>" aria-label="Download Excel" style="text-decoration:none;margin-right:6px;padding:9px 11px">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                <span style="display:none">Download Excel</span>
            </a>
            <script>
            // download through a hidden frame so the Analytics page stays exactly where it is
            (function () {
                var a = document.querySelector('a.an-dl-btn');
                if (!a) { return; }
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    var f = document.getElementById('an-dl-frame');
                    if (!f) {
                        f = document.createElement('iframe');
                        f.id = 'an-dl-frame';
                        f.style.display = 'none';
                        document.body.appendChild(f);
                    }
                    f.src = a.href;
                });
            })();
            </script>
        <?php endif ?>
        <a href="<?= esc(site_url('analytics/monthly-report') . '?tab=' . urlencode($tab) . '&year=' . urlencode($yrParam), 'attr') ?>" target="_blank" rel="noopener" class="an-dr-btn" style="text-decoration:none;margin-right:6px;padding:9px 11px" title="Monthly summary (print) — ready to print or save as PDF" aria-label="Monthly summary (print)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        </a>
        <div class="an-dr">
            <button type="button" id="an-dr-btn" class="an-dr-btn" aria-expanded="false" aria-label="Select date range" title="<?= ($from !== '' || $to !== '') ? esc(($from ?: '…') . ' → ' . ($to ?: '…'), 'attr') : 'All dates' ?>" style="padding:9px 11px">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <span id="an-dr-label" style="display:none"><?= ($from !== '' || $to !== '') ? esc(($from ?: '…') . '  →  ' . ($to ?: '…')) : 'All dates' ?></span>
            </button>

            <?php /* the popup (Quick Presets / By Week / Month / Year / calendar) is built by assets/js/datefilter.js - the same one as the Lab Tracker */ ?>
        </div>
    </form>
    <?php if (! empty($empty)): ?><div class="card glass"><p class="muted sm" style="margin:0"><?= esc($empty) ?></p></div><?php endif ?>

<?php if ($view === 'result'): ?>
    <?php
    $sum = ['total' => 0, 'pass' => 0, 'tol' => 0, 'fail' => 0];
    foreach ($suppliers as $s) {
        foreach ($sum as $k => $_) {
            $sum[$k] += (int) $s[$k];
        }
    }
    $pc  = static fn ($n, $d) => $d > 0 ? number_format($n / $d * 100, 2) . '%' : '0.00%';
    $pc1 = static fn ($n, $d) => $d > 0 ? round($n / $d * 100, 1) : 0.0;
    $supLabel = static fn (string $n) => match (strtoupper(trim($n))) {
        'NANPAO'  => 'Nan Pao',
        'ZHONGBU' => 'Zhong Bu',
        default   => ucwords(strtolower(trim($n))),
    };
    $supDonut = static function (array $s): string {
        $tot = max(0, (int) $s['total']);
        $cx  = 110;
        $cy  = 110;
        $r   = 78;
        $c   = 2 * M_PI * $r;
        $o   = '<svg viewBox="0 0 220 220" width="185" height="185" role="img" style="display:block;margin:0 auto;flex-shrink:0">';
        $o  .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="#e2e8f0" stroke-width="24"/>';
        if ($tot > 0) {
            $off = 0;
            foreach ([['Passed', (int) $s['pass'], '#10b981'], ['Tolerable', (int) $s['tol'], '#f59e0b'], ['Failed', (int) $s['fail'], '#ef4444']] as [$nm, $v, $col]) {
                if ($v <= 0) {
                    continue;
                }
                $len = ($v / $tot) * $c;
                $o  .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="' . $col . '" stroke-width="24" stroke-dasharray="' . round($len, 2) . ' ' . round($c - $len, 2) . '" stroke-dashoffset="' . round(-$off, 2) . '" transform="rotate(-90 ' . $cx . ' ' . $cy . ')"><title>' . esc($nm) . ': ' . $v . '</title></circle>';
                $off += $len;
            }
        }
        $o .= '<text x="' . $cx . '" y="' . ($cy + 2) . '" text-anchor="middle" font-size="25" font-weight="900" fill="#0f172a">' . number_format($tot) . '</text>';
        $o .= '<text x="' . $cx . '" y="' . ($cy + 19) . '" text-anchor="middle" font-size="10" font-weight="800" letter-spacing=".06em" fill="#64748b">SPECIMENS</text></svg>';
        return $o;
    };
    ?>
    <?php if ((int) $sum['total'] > 0): $cemPass = round(((int) $sum['pass']) / ((int) $sum['total']) * 100, 1); ?>
        <?= anSummary(
            $cemPass . '% of tested shoes with a recorded cement (glue) supplier passed bonding.',
            number_format((int) $sum['total']) . ' specimens were tested: ' . number_format((int) $sum['pass']) . ' passed, ' . number_format((int) $sum['tol']) . ' were borderline and ' . number_format((int) $sum['fail']) . ' failed. The table below compares each supplier. A pass rate of 95% or more is good; below 85% needs action.',
            anTone((float) $cemPass)
        ) ?>
    <?php endif ?>
    <!-- Top Executive KPI Cards for Cement Analytics -->
    <div class="an-kpis">
        <div class="card glass ms-kpi-card">
            <div>
                <small>Total Cement Specimens</small>
                <b><?= number_format($sum['total']) ?></b>
                <span class="sub">Tested shoes with recorded adhesive supplier</span>
            </div>
            <span class="ms-kpi-ico ms-ico-blue"><svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-flask"/></svg></span>
        </div>
        <div class="card glass ms-kpi-card">
            <div>
                <small>Passed Specimens</small>
                <div class="kpi-val-row">
                    <b class="an-pass"><?= number_format($sum['pass']) ?></b>
                    <span class="kpi-pct pct-pass"><?= $pc1($sum['pass'], $sum['total']) ?>%</span>
                </div>
                <span class="sub">Meets VF / Vans bonding peel specification</span>
            </div>
            <span class="ms-kpi-ico ms-ico-pass">&#10003;</span>
        </div>
        <div class="card glass ms-kpi-card">
            <div>
                <small>Tolerable Specimens</small>
                <div class="kpi-val-row">
                    <b class="an-tol"><?= number_format($sum['tol']) ?></b>
                    <span class="kpi-pct pct-tol"><?= $pc1($sum['tol'], $sum['total']) ?>%</span>
                </div>
                <span class="sub">Within acceptable tolerance band</span>
            </div>
            <span class="ms-kpi-ico ms-ico-warn"><svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-bolt"/></svg></span>
        </div>
        <div class="card glass ms-kpi-card">
            <div>
                <small>Failed Specimens</small>
                <div class="kpi-val-row">
                    <b class="an-fail"><?= number_format($sum['fail']) ?></b>
                    <span class="kpi-pct pct-fail"><?= $pc($sum['fail'], $sum['total']) ?></span>
                </div>
                <span class="sub">Bond separation below minimum threshold</span>
            </div>
            <span class="ms-kpi-ico ms-ico-fail">&#10005;</span>
        </div>
    </div>

    <?php if (! $suppliers): ?>
        <div class="card glass"><p class="muted sm" style="margin:0">No bonding results with a cement supplier in this period.</p></div>
    <?php else: ?>
        <!-- Side-by-Side Supplier Performance Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(380px,100%),1fr));gap:16px">
            <?php foreach ($suppliers as $s):
                $sTot  = (int) $s['total'];
                $sPass = (int) $s['pass'];
                $sTol  = (int) $s['tol'];
                $sFail = (int) $s['fail'];
                $fPct  = $pc($sFail, $sTot);
            ?>
            <div style="background:#fff;border:1px solid #cbd5e1;border-radius:16px;box-shadow:0 4px 12px rgba(15,23,42,.05);overflow:hidden;display:flex;flex-direction:column">
                <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);padding:13px 18px;color:#fff;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                    <div>
                        <span style="display:block;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#93c5fd">ADHESIVE / CEMENT SUPPLIER</span>
                        <h4 style="margin:2px 0 0;font-size:16px;font-weight:800;color:#fff"><?= esc($supLabel((string) $s['name'])) ?> <span style="font-size:12px;font-weight:600;color:#cbd5e1">(<?= esc($s['name']) ?>)</span></h4>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.15);color:#fff;font-size:11.5px;font-weight:800"><?= number_format($sTot) ?> Specimens</span>
                        <span style="padding:4px 10px;border-radius:999px;background:<?= $sFail > 0 ? '#fef2f2' : '#f0fdf4' ?>;color:<?= $sFail > 0 ? '#dc2626' : '#15803d' ?>;font-size:11.5px;font-weight:800"><?= $fPct ?> Failed</span>
                    </div>
                </div>
                <div style="padding:18px;display:grid;grid-template-columns:185px minmax(0,1fr);gap:20px;align-items:center">
                    <?= $supDonut($s) ?>
                    <div style="display:flex;flex-direction:column;gap:10px">
                        <?php foreach ([
                            ['Passed (Meets Spec)', $sPass, '#10b981', '#ecfdf5', '#059669'],
                            ['Tolerable (Borderline)', $sTol, '#f59e0b', '#fffbeb', '#d97706'],
                            ['Failed (Below Spec)', $sFail, '#ef4444', '#fef2f2', '#dc2626'],
                        ] as [$lbl, $val, $barCol, $bgCol, $txtCol]):
                            $pVal = $sTot > 0 ? round(($val / $sTot) * 100, 2) : 0;
                        ?>
                        <div style="padding:9px 12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:5px">
                                <span style="display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:800;color:#0f172a">
                                    <i style="width:10px;height:10px;border-radius:50%;background:<?= $barCol ?>;display:inline-block"></i>
                                    <?= esc($lbl) ?>
                                </span>
                                <span style="display:inline-flex;align-items:baseline;gap:6px">
                                    <b style="font-size:15px;font-weight:900;color:#0f172a"><?= number_format($val) ?></b>
                                    <span style="padding:1px 7px;border-radius:999px;font-size:11px;font-weight:800;background:<?= $bgCol ?>;color:<?= $txtCol ?>"><?= number_format($pVal, 2) ?>%</span>
                                </span>
                            </div>
                            <div style="height:7px;border-radius:999px;background:#e2e8f0;overflow:hidden">
                                <div style="height:100%;width:<?= min(100, $pVal) ?>%;background:<?= $barCol ?>;border-radius:999px"></div>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
            <?php endforeach ?>
        </div>

        <!-- Comparative Supplier Summary Table -->
        <div class="br-wrap" style="padding:18px 20px">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px">
                <div>
                    <h3 style="margin:0;font-size:15px;font-weight:800;color:#0f172a">Cement Supplier Comparison Table<?= anTip('One row per glue supplier, showing how their glue performed in the bonding tests. Look for the suppliers with the most red.') ?></h3>
                    <p style="margin:2px 0 0;font-size:12px;color:#64748b">Side-by-side pass, tolerable, and failure counts per adhesive supplier</p>
                </div>
            </div>
            <table class="br-tbl" style="margin:0">
                <tr class="hd">
                    <th style="text-align:left">CEMENT SUPPLIER</th>
                    <th>QTY PASS</th>
                    <th>QTY TOLERABLE</th>
                    <th>QTY FAILED</th>
                    <th>TOTAL SPECIMENS</th>
                    <th>FAILURE RATE (%)</th>
                </tr>
                <?php foreach ($suppliers as $s): ?>
                    <tr class="rw">
                        <td class="nm"><?= esc($supLabel((string) $s['name'])) ?> <span style="font-size:11px;color:#64748b;font-weight:600">(<?= esc($s['name']) ?>)</span></td>
                        <td style="color:#059669"><?= number_format((int) $s['pass']) ?></td>
                        <td style="color:#d97706"><?= number_format((int) $s['tol']) ?></td>
                        <td style="color:#dc2626"><?= number_format((int) $s['fail']) ?></td>
                        <td><b><?= number_format((int) $s['total']) ?></b></td>
                        <td><span style="padding:3px 10px;border-radius:999px;font-weight:800;font-size:12px;background:<?= (int) $s['fail'] > 0 ? '#fef2f2' : '#f0fdf4' ?>;color:<?= (int) $s['fail'] > 0 ? '#dc2626' : '#15803d' ?>"><?= $pc((int) $s['fail'], (int) $s['total']) ?></span></td>
                    </tr>
                <?php endforeach ?>
                <tr class="tot">
                    <td>TOTAL ALL SUPPLIERS</td>
                    <td style="color:#059669"><?= number_format($sum['pass']) ?></td>
                    <td style="color:#d97706"><?= number_format($sum['tol']) ?></td>
                    <td style="color:#dc2626"><?= number_format($sum['fail']) ?></td>
                    <td><?= number_format($sum['total']) ?></td>
                    <td class="red"><?= $pc($sum['fail'], $sum['total']) ?></td>
                </tr>
            </table>
        </div>
    <?php endif ?>
<?php elseif ($view === 'monthly'): ?>
    <?php
    // "All dates" has no from-date: use the LATEST month with a real production date (the first row can be a bad / blank date)
    $validDays = array_values(array_filter(array_column($days, 'd'), static fn ($x) => (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $x, $mm) && checkdate((int) $mm[2], (int) $mm[3], (int) $mm[1]) && (int) $mm[1] >= 2000));
    $anchorDay = $from !== '' ? $from : ($validDays ? end($validDays) : date('Y-m-d'));
    $monthName = strtoupper(date('F Y', strtotime($anchorDay))); // e.g. OCTOBER 2026
    $monthOnly = strtoupper(date('F', strtotime($anchorDay)));
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
    <?php
    $passPct1 = $ratedTot > 0 ? round((int) $status['pass'] / $ratedTot * 100, 1) : 0.0;
    $tolPct1  = $ratedTot > 0 ? round((int) $status['tol'] / $ratedTot * 100, 1) : 0.0;
    // every production day with results counts in the rates, including the latest one (a day still being tested is no longer left out)
    $pendDay = ''; $pendT = 0; $pendF = 0;
    $daysTested = array_sum(array_map(static fn ($r) => (int) $r['tested'], $days)) - $pendT;
    $failPct1 = $daysTested > 0 ? round(((int) $status['fail'] - $pendF) / $daysTested * 100, 2) : 0.0;   // failed of all tested specimens: same base as the Failed-by-Line table rate below
    $shutVal  = (int) ($status['shut'] ?? 0);
    ?>
    <div id="ms-yearly-pane" hidden>
        <?php include __DIR__ . '/bonding_yearly_pies.php'; ?>
    </div>
    <div id="ms-monthly-pane">
    <?php if ($ratedTot > 0): ?>
        <?= anSummary(
            $passPct1 . '% of bonding reports passed (' . $periodLbl . ').',
            number_format($ratedTot) . ' reports have a result: ' . number_format((int) $status['pass']) . ' passed, ' . number_format((int) $status['tol']) . ' were borderline and ' . number_format((int) $status['fail']) . ' failed'
                . ($shutVal > 0 ? '. ' . number_format($shutVal) . ' line(s) were shut down (not producing).' : '.')
                . ' A pass rate of 95% or more is good; below 85% needs action.',
            anTone((float) $passPct1)
        ) ?>
    <?php endif ?>
    <div class="ms2-kpis ms2-kpis-4">
        <div class="ms2-kpi k-tot">
            <div class="ms2-kpi-top">
                <span class="ms2-kpi-lbl">Total Tested</span>
                <span class="ms2-kpi-ico">&#8721;</span>
            </div>
            <div class="ms2-kpi-valrow">
                <span class="ms2-kpi-val"><?= number_format($ratedTot) ?></span>
            </div>
            <div class="ms2-kpi-bar"><i style="width:100%;background:#3b82f6"></i></div>
            <span class="ms2-kpi-sub">Passed + Tolerable + Failed</span>
        </div>

        <div class="ms2-kpi k-pass">
            <div class="ms2-kpi-top">
                <span class="ms2-kpi-lbl">Passed Reports</span>
                <span class="ms2-kpi-ico">&#10003;</span>
            </div>
            <div class="ms2-kpi-valrow">
                <span class="ms2-kpi-val"><?= number_format((int) $status['pass']) ?></span>
                <span class="ms2-kpi-pill"><?= number_format($passPct1, 1) ?>%</span>
            </div>
            <div class="ms2-kpi-bar"><i style="width:<?= min(100, $passPct1) ?>%;background:#10b981"></i></div>
            <span class="ms2-kpi-sub">Meets VF / Vans Specification</span>
        </div>

        <div class="ms2-kpi k-tol">
            <div class="ms2-kpi-top">
                <span class="ms2-kpi-lbl">Tolerable / Data</span>
                <span class="ms2-kpi-ico">&#9888;</span>
            </div>
            <div class="ms2-kpi-valrow">
                <span class="ms2-kpi-val"><?= number_format((int) $status['tol']) ?></span>
                <span class="ms2-kpi-pill"><?= number_format($tolPct1, 1) ?>%</span>
            </div>
            <div class="ms2-kpi-bar"><i style="width:<?= min(100, max((int) $status['tol'] > 0 ? 3 : 0, $tolPct1)) ?>%;background:#f59e0b"></i></div>
            <span class="ms2-kpi-sub">Within Tolerance Band</span>
        </div>

        <div class="ms2-kpi k-fail">
            <div class="ms2-kpi-top">
                <span class="ms2-kpi-lbl">Failed Reports</span>
                <span class="ms2-kpi-ico">&#10005;</span>
            </div>
            <div class="ms2-kpi-valrow">
                <span class="ms2-kpi-val"><?= number_format((int) $status['fail']) ?></span>
                <span class="ms2-kpi-pill"><?= number_format($failPct1, 2) ?>%</span>
            </div>
            <div class="ms2-kpi-bar"><i style="width:<?= min(100, max((int) $status['fail'] > 0 ? 3 : 0, $failPct1)) ?>%;background:#ef4444"></i></div>
            <span class="ms2-kpi-sub">Requires Retest / Corrective Action</span>
        </div>
    </div>
    <?php if (! $days): ?><div class="card glass"><p class="muted sm" style="margin:0">No bonding tests with a production date in this period.</p></div><?php else: ?>

    <?php
    // ---- 1. failure trend (bars: failed + tested, line: % failed) ----
    $W = 1000; $H = 420; $L = 48; $R = 58; $T = 32; $B = 64;
    $pw = $W - $L - $R; $ph = $H - $T - $B;
    $maxT = 5; $maxP = 5;
    foreach ($days as $d) { $maxT = max($maxT, (int) $d['tested']); $maxP = max($maxP, $d['tested'] ? ceil((int) $d['failed'] / (int) $d['tested'] * 100 / 5) * 5 : 0); }
    $maxT = (int) (ceil($maxT / 5) * 5);
    $n = count($days); $slot = $pw / $n; $bw = min(24, $slot * 0.36);
    $yT = static fn ($v) => $T + $ph - $v / $maxT * $ph;
    $yP = static fn ($v) => $T + $ph - $v / $maxP * $ph;
    $svg = '<defs><linearGradient id="gFail" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f87171"/><stop offset="100%" stop-color="#dc2626"/></linearGradient><linearGradient id="gTest" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#34d399"/><stop offset="100%" stop-color="#059669"/></linearGradient></defs>';
    for ($i = 0; $i <= 6; $i++) {
        $gy = $T + $ph - $i / 6 * $ph;
        $svg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $gy . '" y2="' . $gy . '" stroke="rgba(148,163,184,0.18)" stroke-dasharray="4 4" stroke-width="1"/>';
        $svg .= '<text x="' . ($L - 8) . '" y="' . ($gy + 4) . '" text-anchor="end" fill="#94a3b8" font-size="11" font-weight="600">' . round($i / 6 * $maxT, 1) . '</text>';
        $svg .= '<text x="' . ($W - $R + 8) . '" y="' . ($gy + 4) . '" fill="#7dd3fc" font-size="10.5" font-weight="700">' . number_format($i / 6 * $maxP, 1) . '%</text>';
    }
    $svg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . ($T + $ph) . '" y2="' . ($T + $ph) . '" stroke="rgba(148,163,184,.35)" stroke-width="1.2"/>';
    $pts = []; $dots = ''; $lbls = '';
    foreach ($days as $i => $d) {
        $cx = $L + $slot * ($i + 0.5); $te = (int) $d['tested']; $fa = (int) $d['failed'];
        $hFa = max(0, $T + $ph - $yT($fa));
        $hTe = max(0, $T + $ph - $yT($te));
        $svg .= '<rect x="' . ($cx - $bw - 1.5) . '" y="' . $yT($fa) . '" width="' . $bw . '" height="' . $hFa . '" rx="3" fill="url(#gFail)"><title>' . $mdY($d['d']) . ' · Failed: ' . $fa . '</title></rect>';
        $svg .= '<rect x="' . ($cx + 1.5) . '" y="' . $yT($te) . '" width="' . $bw . '" height="' . $hTe . '" rx="3" fill="url(#gTest)"><title>' . $mdY($d['d']) . ' · Tested: ' . $te . '</title></rect>';
        $svg .= '<text x="' . ($cx - $bw / 2 - 1.5) . '" y="' . ($yT($fa) - 5) . '" text-anchor="middle" fill="#fca5a5" font-size="10.5" font-weight="800">' . $fa . '</text>';
        $svg .= '<text x="' . ($cx + $bw / 2 + 1.5) . '" y="' . ($yT($te) - 5) . '" text-anchor="middle" fill="#6ee7b7" font-size="10.5" font-weight="800">' . $te . '</text>';
        $pv = $te ? $fa / $te * 100 : 0;
        $py = round($yP($pv), 1);
        $pts[] = round($cx, 1) . ',' . $py;
        $dots .= '<circle cx="' . round($cx, 1) . '" cy="' . $py . '" r="4" fill="#0f172a" stroke="#38bdf8" stroke-width="2.2"><title>' . $mdY($d['d']) . ' · Failed Rate: ' . number_format($pv, 2) . '%</title></circle>';
        $lblTxt = number_format($pv, 1) . '%';
        $ly = $py - 10;
        if ($ly < $T + 6) {
            $ly = $py + 18;
        }
        $lbls .= '<rect x="' . round($cx - 21, 1) . '" y="' . round($ly - 10, 1) . '" width="42" height="13.5" rx="4" fill="rgba(15,23,42,.86)" stroke="rgba(56,189,248,.4)" stroke-width="0.7"/>';
        $lbls .= '<text x="' . round($cx, 1) . '" y="' . round($ly, 1) . '" text-anchor="middle" fill="#7dd3fc" font-size="9.5" font-weight="800">' . $lblTxt . '</text>';
        $svg .= '<text transform="translate(' . ($cx + 3) . ',' . ($T + $ph + 14) . ') rotate(-38)" text-anchor="end" fill="#cbd5e1" font-size="10.5" font-weight="600">' . $mdY($d['d']) . '</text>';
    }
    $svg .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="#38bdf8" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>' . $dots . $lbls;

    // ---- 2. status summary donut (clean ring + share cards) ----
    $dR = 72; $dC = 2 * M_PI * $dR; $dOff = 0;
    $dSvg = '<svg viewBox="0 0 190 190" width="188" height="188" style="display:block;margin:2px auto;font-family:Inter,system-ui,sans-serif">';
    $dSvg .= '<circle cx="95" cy="95" r="' . $dR . '" fill="none" stroke="#e2e8f0" stroke-width="24"/>';
    if ($ratedTot > 0) {
        foreach ([['Passed', (int) $status['pass'], '#10b981'], ['Tolerable', (int) $status['tol'], '#f59e0b'], ['Failed', (int) $status['fail'], '#ef4444']] as [$nm, $val, $col]) {
            if ($val <= 0) { continue; }
            $len = ($val / $ratedTot) * $dC;
            $dSvg .= '<circle cx="95" cy="95" r="' . $dR . '" fill="none" stroke="' . $col . '" stroke-width="24" stroke-dasharray="' . round($len, 2) . ' ' . round($dC - $len, 2) . '" stroke-dashoffset="' . round(-$dOff, 2) . '" transform="rotate(-90 95 95)"><title>' . $nm . ': ' . number_format($val) . ' (' . round($val / $ratedTot * 100, 1) . '%)</title></circle>';
            $dOff += $len;
        }
    }
    $dSvg .= '<text x="95" y="92" text-anchor="middle" font-size="25" font-weight="900" fill="currentColor">' . number_format($passPct1, 1) . '%</text>';
    $dSvg .= '<text x="95" y="111" text-anchor="middle" font-size="10.5" font-weight="800" letter-spacing=".06em" fill="#64748b">PASS RATE</text>';
    $dSvg .= '</svg>';
    ?>
    <div class="ms-chart-grid">
        <div class="ms-pie-card">
            <div class="ms-pie-head">
                <div>
                    <h3><?= ucfirst(strtolower($monthOnly)) ?> Summary</h3>
                    <p>Pass, Tolerable, and Fail share across <?= esc($periodLbl) ?></p>
                </div>
                <span class="ms-rated-pill"><?= number_format($ratedTot) ?> Rated</span>
            </div>
            <div class="ms-pie-body">
                <?= $dSvg ?>
                <div class="ms2-share-list">
                    <?php foreach ([
                        ['Passed',    (int) $status['pass'], $passPct1, '#10b981'],
                        ['Tolerable', (int) $status['tol'],  $tolPct1,  '#f59e0b'],
                        ['Failed',    (int) $status['fail'], $failPct1, '#ef4444'],
                    ] as [$nm, $val, $pctV, $col]): ?>
                    <div class="ms2-share-item">
                        <div class="ms2-share-top">
                            <span class="nm"><i style="background:<?= $col ?>"></i><?= $nm ?></span>
                            <span><?= number_format($val) ?> <span style="color:#64748b;font-weight:700">(<?= number_format($pctV, 1) ?>%)</span></span>
                        </div>
                        <div class="ms2-share-track"><span style="width:<?= min(100, max($val > 0 ? 2 : 0, $pctV)) ?>%;background:<?= $col ?>"></span></div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <div class="ms-trend-card">
            <div class="ms-trend-head">
                <div>
                    <h3>Bonding Failure Trend by Production Date &mdash; <?= esc($monthName) ?><?= anTip('Production date = the day the shoes were made (not the day they were tested). The line shows the failure rate for each day; a rising line means more problems.') ?></h3>
                    <p>Daily tested specimens vs. bonding failures and failure rate percentage</p>
                </div>
                <div class="ms-trend-leg">
                    <span><i style="background:#ef4444"></i>Failed Specimen</span>
                    <span><i style="background:#10b981"></i>Tested Specimen</span>
                    <span><i class="ln"></i>Percentage Failed</span>
                </div>
            </div>
            <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" style="display:block;font-family:Inter,system-ui,sans-serif;overflow:visible"><?= $svg ?></svg>
        </div>
    </div>

    <?php
    // ---- 3. failed specimens per line and production date (Building A & B tabs) ----
    $withTests = $validDays ?: array_column($days, 'd');   // real dates only (a blank / bad date would give "As of 2026-90-40")
    $rangeFrom = $from !== '' ? strtotime($from) : strtotime(date('Y-m-01', strtotime($anchorDay)));
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
    // typed totals (Edit mode) replace the counted ones; the percentage row and the right-hand totals follow them
    $ovr = $ovr ?? ['B' => [], 'F' => [], 'T' => []];
    foreach ($bldStats as $bk => &$bs) {
        foreach ($dates as $d) { if (isset($ovr['B'][$bk][$d])) { $bs['bTot'][$d] = $ovr['B'][$bk][$d]; $bs['sum'] = array_sum($bs['bTot']); } }
    }
    unset($bs);
    foreach ($dates as $d) {
        if (isset($ovr['F'][$d])) { $totF[$d] = $ovr['F'][$d]; }
        if (isset($ovr['T'][$d])) { $totT[$d] = $ovr['T'][$d]; }
    }
    $grand = array_sum($totF); $grandT = array_sum($totT);
    if ($pendDay !== '') { $grand -= (int) ($totF[$pendDay] ?? 0); $grandT -= (int) ($totT[$pendDay] ?? 0); }   // shown in the table, not counted in the totals / rate
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
                <p>As of <?= esc($lastD) ?> &middot; Total Failed: <b><?= number_format($grand) ?></b> of <b><?= number_format($grandT) ?></b> Tested Specimens (<b><?= $grandT ? number_format($grand / $grandT * 100, 2) . '%' : '0.00%' ?></b>)<?= $pendDay !== '' ? ' &middot; ' . esc(date('m-d', strtotime($pendDay))) . ' is shown but not counted yet (testing in progress)' : '' ?></p>
            </div>
            <div class="mx-head-r">
                <div class="mx-legend">
                    <span><i style="background:#ffe4e6;border:1px solid #fda4af"></i>Sunday</span>
                    <span><i style="background:#dc2626"></i>Failed Count</span>
                    <span><i style="background:#cbd5e1"></i>SD = Shutdown Line</span>
                    <span><i style="background:#ea580c"></i>Highest Failed Line</span>
                </div>
                <button type="button" id="mx-edit-btn" class="mx-edit-btn" role="checkbox" aria-checked="false" aria-label="Edit marks" title="Tick to edit: type 0 or 1 in a cell; click the cell itself to make it SHUTDOWN (click again to remove)"></button>
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
                    <table class="mx-tbl">
                        <colgroup>
                            <col style="width:162px">
                            <?php foreach ($dates as $d): ?><col<?= date('w', strtotime($d)) === '0' ? ' class="sun"' : '' ?>><?php endforeach ?>
                            <col style="width:68px">
                        </colgroup>
                        <thead>
                            <tr class="dh">
                                <th class="lc">Line &middot; Date</th>
                                <?php foreach ($dates as $d):
                                    $tsD = strtotime($d);
                                ?>
                                    <th<?= date('w', $tsD) === '0' ? ' class="sun"' : '' ?> title="<?= esc(date('F j, Y (l)', $tsD), 'attr') ?>">
                                        <span class="dh-d"><?= date('j', $tsD) ?></span>
                                        <span class="dh-w"><?= date('D', $tsD) ?></span>
                                    </th>
                                <?php endforeach ?>
                                <th class="tc">Total<br>Failed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nums as $nn): ?>
                                <tr>
                                    <td class="lc"><span class="line-tag">Line <?= esc($bld . $nn) ?></span></td>
                                    <?php foreach ($dates as $d):
                                        $c = (int) ($cells[$bld][$nn][$d] ?? 0);
                                        $isSun = date('w', strtotime($d)) === '0';
                                    ?>
                                        <?php $mk = (string) ($marks[$bld][$nn][$d] ?? ''); $ed = ' data-b="' . esc($bld, 'attr') . '" data-n="' . (int) $nn . '" data-d="' . $d . '" data-mark="' . $mk . '"'; ?>
                                        <?php if (! empty($shut[$bld][$nn][$d]) && $c === 0): ?>
                                            <td class="sd mx-ed" title="Line <?= esc($bld . $nn) ?> Shutdown on <?= esc($d) ?> (in edit mode: click to remove)"<?= $ed ?> data-shut="1"><b>SD</b></td>
                                        <?php elseif ($c > 0): ?>
                                            <td class="has-fail mx-ed<?= $isSun ? ' sun-cell' : '' ?>"<?= $ed ?> data-val="<?= $c ?>"><span class="mx-v"><?= $c ?></span><input class="mx-in" type="text" inputmode="numeric" maxlength="1" value="<?= $c > 1 ? 1 : $c ?>" aria-label="Failed specimens (0 or 1)"></td>
                                        <?php else: ?>
                                            <td class="z mx-ed<?= $isSun ? ' sun-cell' : '' ?>"<?= $ed ?> data-val="0"><span class="mx-v"><?= (int) ($totT[$d] ?? 0) > 0 ? '0' : '&ndash;' ?></span><input class="mx-in" type="text" inputmode="numeric" maxlength="1" value="0" aria-label="Failed specimens (0 or 1)"></td>
                                        <?php endif ?>
                                    <?php endforeach ?>
                                    <td class="tc<?= ($mxRow > 0 && $rowT[$nn] === $mxRow) ? ' mx' : '' ?>"><?= $rowT[$nn] ?></td>
                                </tr>
                            <?php endforeach ?>
                            <tr class="bt">
                                <td class="lc">Building <?= esc($bld) ?> Failed / Day</td>
                                <?php foreach ($dates as $d): $bv = (int) $bTot[$d]; ?>
                                    <td class="mx-tt<?= $bv === 0 ? ' z-sum' : '' ?>" data-kind="B" data-b="<?= esc($bld, 'attr') ?>" data-d="<?= $d ?>" data-ovr="<?= isset($ovr['B'][$bld][$d]) ? 1 : 0 ?>"><span class="mx-v"><?= $bv ?></span><input class="mx-in mx-tin" type="text" inputmode="numeric" maxlength="5" value="<?= $bv ?>" aria-label="Total failed"></td>
                                <?php endforeach ?>
                                <td class="tc blue"><?= $stB['sum'] ?></td>
                            </tr>
                            <tr class="rd">
                                <td class="lc">All Buildings Failed (A + B)</td>
                                <?php foreach ($dates as $d): $fv = (int) ($totF[$d] ?? 0); ?>
                                    <td class="mx-tt<?= $fv === 0 ? ' z-sum' : '' ?>" data-kind="F" data-d="<?= $d ?>" data-ovr="<?= isset($ovr['F'][$d]) ? 1 : 0 ?>"><span class="mx-v"><?= $fv ?></span><input class="mx-in mx-tin" type="text" inputmode="numeric" maxlength="5" value="<?= $fv ?>" aria-label="Total failed per date"></td>
                                <?php endforeach ?>
                                <td class="tc"><?= $grand ?></td>
                            </tr>
                            <tr class="rt">
                                <td class="lc">Total Shoes Tested (A + B)</td>
                                <?php foreach ($dates as $d): $tv = (int) $totT[$d]; ?>
                                    <td class="mx-tt<?= $tv === 0 ? ' z-sum' : '' ?>" data-kind="T" data-d="<?= $d ?>" data-ovr="<?= isset($ovr['T'][$d]) ? 1 : 0 ?>"><span class="mx-v"><?= $tv ?></span><input class="mx-in mx-tin" type="text" inputmode="numeric" maxlength="5" value="<?= $tv ?>" aria-label="Total tested per date"></td>
                                <?php endforeach ?>
                                <td class="tc"><?= $grandT ?></td>
                            </tr>
                            <tr class="rd pct-row">
                                <td class="lc">Daily Failure Rate (%)</td>
                                <?php foreach ($dates as $d):
                                    $fv = (int) ($totF[$d] ?? 0);
                                    $tv = (int) $totT[$d];
                                    $hasPct = $tv > 0 && $fv > 0;
                                ?>
                                    <td class="<?= $hasPct ? 'has-pct' : 'z-sum' ?>"><?= $tv > 0 ? ($fv > 0 ? number_format($fv / $tv * 100, 2) . '%' : '0.00%') : '&ndash;' ?></td>
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
    </div>
    <script>
    (function () {
      var btn = document.getElementById('btn-ms-yearly'), back = document.getElementById('btn-ms-back-monthly');
      var mp = document.getElementById('ms-monthly-pane'), yp = document.getElementById('ms-yearly-pane');
      if (!btn || !mp || !yp) return;
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
<?php elseif ($view === 'failure'): ?>
    <?php include __DIR__ . '/bonding_failure.php'; ?>
<?php elseif ($view === 'retest'): ?>
    <?php include __DIR__ . '/bonding_retest.php'; ?>
<?php elseif ($view === 'utm'): ?>
    <?php include __DIR__ . '/bonding_utm.php'; ?>
<?php elseif ($view === 'daily'): ?>
    <?php include __DIR__ . '/bonding_daily.php'; ?>
<?php elseif ($view === 'pulltest'): ?>
    <?php include __DIR__ . '/bonding_pulltest.php'; ?>
<?php elseif ($tab === 'wholeshoe' && ! empty($wsAudit)): ?>
    <?php include __DIR__ . '/wholeshoe_audit.php'; ?>
<?php elseif ($tab === 'materials' && ! empty($matAudit)): ?>
    <?php include __DIR__ . '/materials_audit.php'; ?>
<?php else: ?>
    <!-- Executive KPI Cards for Whole Shoe Analytics / Material Test Analytics -->
    <div class="an-kpis">
        <?php foreach ($kpis as $k): ?>
            <?php
            $kStyle = $k[3] ?? '';
            $kBadge = $k[4] ?? '';
            if ($kBadge === '' && preg_match('/(\d+(?:\.\d+)?%)/', (string) ($k[2] ?? ''), $bm)) {
                $kBadge = $bm[1];
            }
            $icoCls = match ($kStyle) {
                'an-pass' => 'ms-ico-pass',
                'an-tol'  => 'ms-ico-warn',
                'an-fail' => 'ms-ico-fail',
                default   => 'ms-ico-blue',
            };
            $pctCls = match ($kStyle) {
                'an-pass' => 'pct-pass',
                'an-tol'  => 'pct-tol',
                'an-fail' => 'pct-fail',
                default   => 'pct-pass',
            };
            $icoSym = match ($kStyle) {
                'an-pass' => '&#10003;',
                'an-tol'  => '<svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-bolt"/></svg>',
                'an-fail' => '&#10005;',
                default   => '<svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-flask"/></svg>',
            };
            ?>
            <div class="card glass ms-kpi-card">
                <div>
                    <small><?= esc($k[0]) ?></small>
                    <div class="kpi-val-row">
                        <b class="<?= esc($kStyle) ?>"><?= esc($k[1]) ?></b>
                        <?php if ($kBadge !== ''): ?>
                            <span class="kpi-pct <?= $pctCls ?>" style="<?= $kStyle === '' ? 'background:#eff6ff;color:#1d4ed8' : '' ?>"><?= esc($kBadge) ?></span>
                        <?php endif ?>
                    </div>
                    <span class="sub"><?= esc($k[2]) ?></span>
                </div>
                <span class="ms-kpi-ico <?= $icoCls ?>"><?= $icoSym ?></span>
            </div>
        <?php endforeach ?>
    </div>

    <?php
    // ---- Build Summary Donut + Executive SVG Trend Chart ----
    $sd = $summaryDonut ?? null;
    $sdParts = $sd['parts'] ?? [];
    $sdTot   = array_sum(array_map(static fn ($p) => (int) ($p[1] ?? 0), $sdParts));

    // SVG Trend Chart
    $W = 860; $H = 320; $L = 46; $R = 20; $T = 28; $B = 54;
    $pw = $W - $L - $R; $ph = $H - $T - $B;
    $maxBar = 5;
    foreach ($months as $m) {
        $maxBar = max($maxBar, (int) $m['total']);
    }
    $stepBar = max(1, (int) ceil($maxBar / 5));
    $maxBar  = $stepBar * 5;
    $nM      = max(1, count($months));
    $slotW   = $pw / $nM;
    $barW    = min(36, max(10, $slotW * 0.52));

    $trendSvg = '<defs>'
        . '<linearGradient id="anGPass" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#34d399"/><stop offset="100%" stop-color="#059669"/></linearGradient>'
        . '<linearGradient id="anGTol" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#fbbf24"/><stop offset="100%" stop-color="#d97706"/></linearGradient>'
        . '<linearGradient id="anGFail" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f87171"/><stop offset="100%" stop-color="#dc2626"/></linearGradient>'
        . '<linearGradient id="anGBlue" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#60a5fa"/><stop offset="100%" stop-color="#2563eb"/></linearGradient>'
        . '</defs>';

    for ($gi = 0; $gi <= 5; $gi++) {
        $gy = $T + $ph - ($gi / 5) * $ph;
        $gv = round(($gi / 5) * $maxBar);
        $trendSvg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . round($gy, 1) . '" y2="' . round($gy, 1) . '" stroke="rgba(148,163,184,0.18)" stroke-dasharray="4 4" stroke-width="1"/>';
        $trendSvg .= '<text x="' . ($L - 8) . '" y="' . round($gy + 4, 1) . '" text-anchor="end" fill="#94a3b8" font-size="11" font-weight="600">' . $gv . '</text>';
    }

    foreach ($months as $idx => $m) {
        $cx  = $L + $slotW * ($idx + 0.5);
        $tot = (int) $m['total'];
        $hTot = ($tot / $maxBar) * $ph;
        $yTop = $T + $ph - $hTot;
        $x0   = round($cx - $barW / 2, 1);

        if ($segmented && $tot > 0) {
            $pCnt = (int) ($m['pass'] ?? 0);
            $tCnt = (int) ($m['tol'] ?? 0);
            $fCnt = (int) ($m['fail'] ?? 0);
            $curY = $T + $ph;
            foreach ([
                [$pCnt, 'url(#anGPass)', 'Passed'],
                [$tCnt, 'url(#anGTol)',  'Tolerable'],
                [$fCnt, 'url(#anGFail)', 'Failed'],
            ] as [$segV, $segFill, $segNm]) {
                if ($segV <= 0) {
                    continue;
                }
                $segH = ($segV / $maxBar) * $ph;
                $curY -= $segH;
                $trendSvg .= '<rect x="' . $x0 . '" y="' . round($curY, 1) . '" width="' . round($barW, 1) . '" height="' . round($segH, 1) . '" rx="2" fill="' . $segFill . '"><title>' . esc($m['m']) . ' · ' . $segNm . ': ' . $segV . '</title></rect>';
            }
        } elseif ($tot > 0) {
            $trendSvg .= '<rect x="' . $x0 . '" y="' . round($yTop, 1) . '" width="' . round($barW, 1) . '" height="' . round($hTot, 1) . '" rx="4" fill="url(#anGBlue)"><title>' . esc($m['m']) . ': ' . $tot . '</title></rect>';
        }

        if ($tot > 0) {
            $trendSvg .= '<text x="' . round($cx, 1) . '" y="' . round($yTop - 6, 1) . '" text-anchor="middle" fill="#f8fafc" font-size="' . ($nM > 20 ? '10' : '11.5') . '" font-weight="800">' . $tot . '</text>';
        }
        $lblTxt = $group === 'day' ? substr((string) $m['m'], 5) : (string) $m['m'];
        if ($nM > 14) {
            $trendSvg .= '<text transform="translate(' . round($cx + 3, 1) . ',' . ($T + $ph + 14) . ') rotate(-36)" text-anchor="end" fill="#cbd5e1" font-size="10" font-weight="600">' . esc($lblTxt) . '</text>';
        } else {
            $trendSvg .= '<text x="' . round($cx, 1) . '" y="' . ($T + $ph + 20) . '" text-anchor="middle" fill="#cbd5e1" font-size="11" font-weight="600">' . esc($lblTxt) . '</text>';
        }
    }
    ?>

    <div class="ms-chart-grid" style="<?= $sd ? '' : 'grid-template-columns:1fr' ?>">
        <?php if ($sd): ?>
        <div class="ms-pie-card" style="justify-content:flex-start;gap:12px">
            <div class="ms-pie-head">
                <h3><?= esc($sd['title']) ?></h3>
                <span><?= esc($sd['center']) ?> <?= esc(ucfirst(strtolower($sd['sub']))) ?></span>
            </div>
            <?php
            $dR = 66; $dC = 2 * M_PI * $dR; $dOff = 0;
            $dSvg = '<svg viewBox="0 0 180 180" width="175" height="175" style="display:block;margin:2px auto"><circle cx="90" cy="90" r="' . $dR . '" fill="none" stroke="#e2e8f0" stroke-width="22"/>';
            if ($sdTot > 0) {
                foreach ($sdParts as [$pNm, $pVal, $pCol]) {
                    if ($pVal <= 0) {
                        continue;
                    }
                    $pLen = ($pVal / $sdTot) * $dC;
                    $dSvg .= '<circle cx="90" cy="90" r="' . $dR . '" fill="none" stroke="' . $pCol . '" stroke-width="22" stroke-dasharray="' . round($pLen, 2) . ' ' . round($dC - $pLen, 2) . '" stroke-dashoffset="' . round(-$dOff, 2) . '" transform="rotate(-90 90 90)"><title>' . esc($pNm) . ': ' . $pVal . '</title></circle>';
                    $dOff += $pLen;
                }
            }
            $dSvg .= '<text x="90" y="92" text-anchor="middle" font-size="23" font-weight="900" fill="#0f172a">' . esc($sd['center']) . '</text>';
            $dSvg .= '<text x="90" y="109" text-anchor="middle" font-size="10" font-weight="800" letter-spacing=".06em" fill="#64748b">' . esc($sd['sub']) . '</text></svg>';
            echo $dSvg;
            ?>
            <div style="display:flex;flex-direction:column;gap:8px">
                <?php foreach ($sdParts as [$pNm, $pVal, $pCol]):
                    $pShare = $sdTot > 0 ? round(($pVal / $sdTot) * 100, 1) : 0;
                ?>
                <div style="padding:7px 10px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc">
                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:800;color:#0f172a;margin-bottom:4px">
                        <span style="display:inline-flex;align-items:center;gap:7px">
                            <i style="width:9px;height:9px;border-radius:50%;background:<?= esc($pCol) ?>;display:inline-block"></i>
                            <?= esc($pNm) ?>
                        </span>
                        <span><?= number_format((int) $pVal) ?> <span style="color:#64748b;font-weight:700">(<?= $pShare ?>%)</span></span>
                    </div>
                    <div style="height:6px;border-radius:999px;background:#e2e8f0;overflow:hidden">
                        <div style="height:100%;width:<?= min(100, $pShare) ?>%;background:<?= esc($pCol) ?>;border-radius:999px"></div>
                    </div>
                </div>
                <?php endforeach ?>
            </div>
        </div>
        <?php endif ?>

        <div class="ms-trend-card">
            <div class="ms-trend-head">
                <div>
                    <h3><?= esc($chartTitle) ?></h3>
                    <p>Distribution of test volume and results across the selected period</p>
                </div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <?php if ($segmented): ?>
                        <div class="ms-trend-leg">
                            <span><i style="background:#10b981"></i>Passed</span>
                            <span><i style="background:#f59e0b"></i>Tolerable</span>
                            <span><i style="background:#ef4444"></i>Failed</span>
                        </div>
                    <?php else: ?>
                        <div class="ms-trend-leg">
                            <span><i style="background:#3b82f6"></i>Collected Specimens</span>
                        </div>
                    <?php endif ?>
                    <div style="display:inline-flex;background:rgba(15,23,42,.55);padding:3px;border-radius:9px;border:1px solid rgba(255,255,255,.14);gap:2px">
                        <?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $gk => $gl):
                            $gUrl = site_url('analytics') . '?' . http_build_query(array_filter([
                                'tab'   => $tab,
                                'view'  => $view,
                                'group' => $gk,
                                'from'  => $from,
                                'to'    => $to,
                                'all'   => ($from === '' && $to === '') ? '1' : null,
                            ], static fn ($v) => $v !== null && $v !== ''));
                        ?>
                            <a href="<?= esc($gUrl, 'attr') ?>" style="padding:4px 10px;border-radius:6px;font-size:11.5px;font-weight:700;text-decoration:none;<?= $group === $gk ? 'background:#2563eb;color:#fff' : 'color:#cbd5e1' ?>"><?= esc($gl) ?></a>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
            <?php if (! $months): ?>
                <div style="flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;color:#94a3b8;font-size:13px;font-weight:600">
                    No dated records found in this period. Try selecting "Show all dates" or a broader date range.
                </div>
            <?php else: ?>
                <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" style="display:block;font-family:Inter,system-ui,sans-serif"><?= $trendSvg ?></svg>
            <?php endif ?>
        </div>
    </div>

    <!-- 3 Ranked Breakdown Cards -->
    <div class="an-two" style="grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr));gap:16px">
        <?php foreach ($lists as $listItem):
            $title = $listItem[0];
            $mode  = $listItem[1];
            $rows  = $listItem[2];
            $sub   = $listItem[3] ?? ($mode === 'ratio' ? 'Ranked by failure ratio and test volume' : 'Ranked by total volume in the selected period');
        ?>
            <div style="background:#fff;border:1px solid #cbd5e1;border-radius:16px;box-shadow:0 4px 12px rgba(15,23,42,.05);overflow:hidden;display:flex;flex-direction:column">
                <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);padding:12px 16px;color:#fff">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                        <h3 style="margin:0;font-size:14px;font-weight:800;color:#fff;letter-spacing:.01em"><?= esc($title) ?></h3>
                        <span style="padding:2px 9px;border-radius:999px;background:rgba(255,255,255,.16);color:#f8fafc;font-size:11px;font-weight:700"><?= count($rows) ?> Listed</span>
                    </div>
                    <p style="margin:3px 0 0;font-size:11.5px;color:#cbd5e1"><?= esc($sub) ?></p>
                </div>
                <div style="padding:14px 16px;display:flex;flex-direction:column;gap:9px;flex:1">
                    <?php if (! $rows): ?>
                        <p class="muted sm" style="margin:12px 0;text-align:center">No records in this period.</p>
                    <?php else: ?>
                        <?php
                        $mx = max(1, (int) ($rows[0]['a'] ?? 1));
                        foreach ($rows as $rIdx => $r):
                            $aVal = (int) ($r['a'] ?? 0);
                            $bVal = (int) ($r['b'] ?? 0);
                            $w    = $mode === 'ratio' ? ($bVal > 0 ? round(($aVal / $bVal) * 100, 1) : 0) : round(($aVal / $mx) * 100, 1);
                            $barCol = $mode === 'ratio' ? ($aVal > 0 ? '#ef4444' : '#10b981') : '#2563eb';
                        ?>
                        <div style="padding:8px 10px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:5px">
                                <span style="display:inline-flex;align-items:center;gap:7px;min-width:0">
                                    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;padding:0 5px;border-radius:6px;font-size:10.5px;font-weight:800;background:<?= $rIdx === 0 ? '#dbeafe' : '#e2e8f0' ?>;color:<?= $rIdx === 0 ? '#1d4ed8' : '#475569' ?>">#<?= $rIdx + 1 ?></span>
                                    <span style="font-size:12.5px;font-weight:800;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= esc($r['name'], 'attr') ?>"><?= esc($r['name']) ?></span>
                                </span>
                                <span style="display:inline-flex;align-items:baseline;gap:6px;white-space:nowrap;flex-shrink:0">
                                    <?php if ($mode === 'ratio'): ?>
                                        <b style="font-size:13.5px;font-weight:900;color:<?= $aVal > 0 ? '#dc2626' : '#059669' ?>"><?= $aVal ?></b>
                                        <span style="font-size:11.5px;color:#64748b;font-weight:700">/ <?= $bVal ?></span>
                                        <span style="padding:1px 6px;border-radius:999px;font-size:10.5px;font-weight:800;background:<?= $aVal > 0 ? '#fef2f2' : '#f0fdf4' ?>;color:<?= $aVal > 0 ? '#dc2626' : '#15803d' ?>"><?= $w ?>%</span>
                                    <?php else: ?>
                                        <b style="font-size:14px;font-weight:900;color:#0f172a"><?= number_format($aVal) ?></b>
                                    <?php endif ?>
                                </span>
                            </div>
                            <div style="height:6px;border-radius:999px;background:#e2e8f0;overflow:hidden">
                                <div style="height:100%;width:<?= min(100, max($aVal > 0 ? 4 : 0, $w)) ?>%;background:<?= $barCol ?>;border-radius:999px"></div>
                            </div>
                        </div>
                        <?php endforeach ?>
                    <?php endif ?>
                </div>
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
  var editBtn = document.getElementById('mx-edit-btn');
  if (bldCard && editBtn) {
    editBtn.addEventListener('click', function () {
      var on = bldCard.classList.toggle('editing');
      editBtn.innerHTML = on ? '&#10003;' : '';
      editBtn.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    var send = function (td, next) {
      var body = new URLSearchParams({ building: td.dataset.b, line: td.dataset.n, date: td.dataset.d, mark: next });
      var tok = document.querySelector('meta[name="X-CSRF-TOKEN"]');
      td.style.opacity = '.5';
      fetch('<?= site_url('analytics/bonding-mark') ?>', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': tok ? tok.content : '' } })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j && j.ok) { location.reload(); } else { td.style.opacity = ''; alert((j && j.message) || 'Could not save the mark.'); } })
        .catch(function () { td.style.opacity = ''; alert('Could not save the mark.'); });
    };
    // type 0 or 1 in a cell (Enter / leaving the box saves it)
    bldCard.addEventListener('change', function (e) {
      var inp = e.target; if (!inp.classList || !inp.classList.contains('mx-in')) return;
      if (inp.classList.contains('mx-tin')) {   // bottom rows: a typed total (empty = back to the counted one)
        var tt = inp.closest('td.mx-tt'), val = inp.value.trim();
        if (val !== '' && !/^\d{1,5}$/.test(val)) { inp.value = inp.defaultValue; return; }
        if (val === inp.defaultValue) return;
        var tb = new URLSearchParams({ kind: tt.dataset.kind, building: tt.dataset.b || '', date: tt.dataset.d, value: val });
        var tk = document.querySelector('meta[name="X-CSRF-TOKEN"]');
        tt.style.opacity = '.5';
        fetch('<?= site_url('analytics/bonding-total') ?>', { method: 'POST', body: tb, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': tk ? tk.content : '' } })
          .then(function (r) { return r.json(); })
          .then(function (j) { if (j && j.ok) { location.reload(); } else { tt.style.opacity = ''; alert((j && j.message) || 'Could not save the total.'); } })
          .catch(function () { tt.style.opacity = ''; alert('Could not save the total.'); });
        return;
      }
      var td = inp.closest('td.mx-ed'), v = inp.value.trim();
      var was = Number(td.dataset.val || 0) > 0 ? 1 : 0;
      if (v !== '0' && v !== '1') { inp.value = was; return; }
      if (Number(v) === was && (td.dataset.mark || '') === '') return;
      send(td, v === '1' ? '1' : (Number(td.dataset.val || 0) > 0 ? '0' : ''));
    });
    bldCard.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('mx-in')) { e.preventDefault(); e.target.blur(); }
    });
    // click the cell itself (not the 0/1 box): make it SHUTDOWN, or take the shutdown away
    bldCard.addEventListener('click', function (e) {
      var td = e.target.closest && e.target.closest('td.mx-ed');
      if (!td || !bldCard.classList.contains('editing') || e.target.classList.contains('mx-in')) return;
      var mk = td.getAttribute('data-mark') || '', isShut = td.dataset.shut === '1';
      var next = mk === 'N' ? '' : (isShut ? (mk === 'S' ? '' : 'N') : 'S');
      send(td, next);
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

  // calendar filter: same popup as the Lab Tracker (shared assets/js/datefilter.js)
  window.NxDateFilter.mount({
    host: document.querySelector('.an-dr'), btn: $('an-dr-btn'), from: $('an-from').value, to: $('an-to').value, allowAll: true,
    onApply: function (f, t) {
      if (!f) { go('', '', 'month'); return; }
      var days = Math.round((parse(t) - parse(f)) / 86400000) + 1;
      go(f, t, days <= 31 ? 'day' : (days <= 120 ? 'week' : 'month'));
    }
  });
})();
</script>
