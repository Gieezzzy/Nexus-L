<?php
/**
 * Retest Overview: designed to be understood at first glance.
 * Shows 4 plain-English KPI cards, 2 visual donut + progress-bar breakdown cards, and a daily/monthly comparison chart.
 * Expects $rt (per period) and $asOf from AnalyticsController::bondingRetest().
 */
$rt   = $rt ?? [];
$asOf = $asOf ?? date('Y-m-d');
?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/analytics-bonding_retest-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/analytics-bonding_retest-1.css') ?>">

<div class="rt-kpis">
    <div class="rt-kpi" style="--rt-accent:#ef4444">
        <div class="rt-kpi-top">
            <small>1. Failed Shoes (Need Retest)</small>
            <span class="rt-kpi-badge" id="rt-fail-pct" style="background:#fef2f2;color:#be123c">Baseline</span>
        </div>
        <b id="rt-fail" style="color:#ef4444">0</b>
        <span class="rt-kpi-sub" id="rt-fail-sub">Failed bonding reports requiring corrective re-test</span>
    </div>

    <div class="rt-kpi" style="--rt-accent:#f97316">
        <div class="rt-kpi-top">
            <small>2. Retests Collected</small>
            <span class="rt-kpi-badge" id="rt-p" style="background:#fff7ed;color:#c2410c">0% Retested</span>
        </div>
        <b id="rt-n" style="color:#f97316">0</b>
        <span class="rt-kpi-sub" id="rt-p2">Corrective re-test shoes submitted to lab</span>
    </div>

    <div class="rt-kpi" style="--rt-accent:#16a34a">
        <div class="rt-kpi-top">
            <small>3. Retests Passed</small>
            <span class="rt-kpi-badge" id="rt-ok-pct" style="background:#f0fdf4;color:#15803d">0% Pass</span>
        </div>
        <b id="rt-ok" style="color:#16a34a">0</b>
        <span class="rt-kpi-sub" id="rt-ok2">Cleared to resume normal production</span>
    </div>
</div>

<div class="rt-grid">
    <div class="rt-card">
        <div class="rt-card-hd">
            <div>
                <p>Retest Specimen Collected</p>
            </div>
        </div>
        <div class="rt-card-bd"><div class="rt-ringwrap" id="rt-ring"></div></div>
    </div>
    <div class="rt-card">
        <div class="rt-card-hd">
            <div>
                <p>Breakdown of retest lab results: Passed, Tolerable, Failed, or Pending</p>
            </div>
        </div>
        <div class="rt-card-bd"><div class="rt-ringwrap" id="rt-out"></div></div>
    </div>
</div>

<div class="rt-card" style="margin-bottom:14px" id="rt">
    <div class="rt-card-hd">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <h4 id="rt-trend-t">Failed Bonding Lines &amp; Retest Status</h4>
            <span class="rt-chart-pill" id="rt-range" style="background:rgba(255,255,255,.16)"></span>
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="rt-chart-leg" id="rt-status-filters">
                <button type="button" class="rt-flt-btn on" data-rt-flt="all">All Failed <span class="rt-flt-cnt" id="rt-cnt-all">0</span></button>
                <button type="button" class="rt-flt-btn" data-rt-flt="done">&#10003; Retest Done <span class="rt-flt-cnt" id="rt-cnt-done">0</span></button>
                <button type="button" class="rt-flt-btn" data-rt-flt="no">&#10005; No Retest Yet <span class="rt-flt-cnt" id="rt-cnt-no">0</span></button>
            </div>
            <div style="display:inline-flex;background:rgba(15,23,42,.55);padding:3px;border-radius:9px;border:1px solid rgba(255,255,255,.14);gap:2px">
                <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $k => $l): ?>
                    <button type="button" data-rt="<?= $k ?>" class="<?= $k === 'monthly' ? 'on' : '' ?>" style="border:0;padding:4px 10px;border-radius:6px;font-size:11.5px;font-weight:700;cursor:pointer;background:transparent;color:#cbd5e1"><?= $l ?></button>
                <?php endforeach ?>
            </div>
        </div>
    </div>
    <div class="rt-tbl-wrap">
        <table class="rt-tbl">
            <thead>
                <tr>
                    <th>Prod. Date</th>
                    <th>Failed Line</th>
                    <th>Failed Lab Ref No. &amp; Style</th>
                    <th>Why It Failed (Portion &amp; Cause)</th>
                    <th>Days Before Collected</th>
                    <th>Retest Lab Ref</th>
                    <th>Result</th>
                    <th>Retest Done?</th>
                </tr>
            </thead>
            <tbody id="rt-tbl-body"></tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var D = <?= json_encode($rt) ?>;
    var ROWS = <?= json_encode($rtRows ?? []) ?>;
    var COL = {pass: '#16a34a', tol: '#f59e0b', fail: '#ef4444', pend: '#94a3b8'};
    var NAME = {pass: 'Passed (Meets Spec)', tol: 'Tolerable (Within Band)', fail: 'Failed Again', pend: 'Pending Lab Result'};
    var root = document.getElementById('rt');
    var curKey = 'monthly';
    var curFlt = 'all';
    function $(id) { return document.getElementById(id); }
    var DTR_URL = <?= json_encode(site_url('dtr')) ?>;
    // a Lab Ref opens its bonding report (readings + the failed parts / points) in Shoe Bonding (U60)
    function refLink(ref, cls) {
        if (!ref) { return ''; }
        return '<a class="' + cls + ' rt-ref-link" href="' + DTR_URL + '?tab=bonding&open=bonding&ref=' + encodeURIComponent(ref) + '" title="Open the bonding report of this Lab Ref: shows which part / point failed">' + esc(ref) + '</a>';
    }
    function pct(a, b) { return b > 0 ? Math.round(a / b * 1000) / 10 : 0; }
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function donut(el, parts, centerTop, centerSub) {
        var R = 68, C = 2 * Math.PI * R, tot = parts.reduce(function (s, p) { return s + p.v; }, 0), off = 0;
        var s = '<svg viewBox="0 0 180 180"><circle cx="90" cy="90" r="' + R + '" fill="none" stroke="#e2e8f0" stroke-width="22"/>';
        if (tot > 0) {
            parts.forEach(function (p) {
                if (p.v <= 0) { return; }
                var len = p.v / tot * C;
                s += '<circle cx="90" cy="90" r="' + R + '" fill="none" stroke="' + p.c + '" stroke-width="22" stroke-dasharray="' + len + ' ' + (C - len) + '" stroke-dashoffset="' + (-off) + '" transform="rotate(-90 90 90)"><title>' + p.n + ': ' + p.v + '</title></circle>';
                off += len;
            });
        }
        s += '<text x="90" y="92" text-anchor="middle" font-size="24" font-weight="900" fill="#0f172a">' + centerTop + '</text><text x="90" y="110" text-anchor="middle" font-size="10.5" fill="#64748b" font-weight="700">' + centerSub + '</text></svg>';
        var leg = '<div class="rt-leg">' + parts.map(function (p) {
            var pVal = p.pct !== undefined ? p.pct : pct(p.v, tot);
            return '<div class="rt-leg-row">'
                + '<div class="rt-leg-top">'
                + '<span class="rt-leg-name"><i class="rt-leg-dot" style="background:' + p.c + '"></i>' + p.n + '</span>'
                + '<span class="rt-leg-val"><span>' + p.v + '</span><span class="rt-leg-pct">' + pVal + '%</span></span>'
                + '</div>'
                + '<div class="rt-bar-track"><div class="rt-bar-fill" style="width:' + Math.min(100, pVal) + '%;background:' + p.c + '"></div></div>'
                + '</div>';
        }).join('') + '</div>';
        el.innerHTML = s + leg;
    }

    function renderTable(x) {
        var periodRows = ROWS.filter(function (r) {
            return r.date >= x.from && r.date <= x.to;
        });
        var doneCnt = periodRows.filter(function (r) { return r.retestDone; }).length;
        var noCnt   = periodRows.length - doneCnt;
        $('rt-cnt-all').textContent  = periodRows.length;
        $('rt-cnt-done').textContent = doneCnt;
        $('rt-cnt-no').textContent   = noCnt;

        var filtered = periodRows.filter(function (r) {
            if (curFlt === 'done') return r.retestDone;
            if (curFlt === 'no')   return !r.retestDone;
            return true;
        }).filter(function (r) {
            // not listed: failed shoes still waiting for a retest after more than 3 days (display only; the counts and percentages above are unchanged)
            return r.retestDone || r.daysToRetest === null || r.daysToRetest === undefined || Number(r.daysToRetest) <= 3;
        });

        var tbody = $('rt-tbl-body');
        if (!filtered.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:28px 14px;color:#64748b;font-weight:600">No failed daily bonding records match this filter for ' + esc(x.from === x.to ? x.from : (x.from + ' → ' + x.to)) + '.</td></tr>';
            return;
        }

        tbody.innerHTML = filtered.map(function (r) {
            var lnCls = r.building === 'A' ? 'b-a' : (r.building === 'B' ? 'b-b' : 'b-o');
            var lnTxt = r.line ? ('Line ' + r.line) : 'Daily';
            var styleHtml = r.style ? '<div class="rt-style-sub">' + esc(r.style) + (r.color ? ' · ' + esc(r.color) : '') + '</div>' : '';

            var whyHtml = '';
            if (r.portion) {
                whyHtml += '<div><span class="rt-why-por">' + esc(r.portion) + '</span></div>';
            }
            whyHtml += '<div class="rt-why-txt">' + (r.why ? esc(r.why) : '<span style="color:#94a3b8;font-weight:600">No failure cause recorded</span>') + '</div>';

            var markHtml = r.retestDone
                ? '<span class="rt-mark-done">&#10003; Retest Done</span>'
                : '<span class="rt-mark-no">&#10005; No Retest Yet</span>';

            var daysHtml = '<span style="color:#94a3b8">&mdash;</span>';
            if (r.daysToRetest !== null && r.daysToRetest !== undefined && Number(r.daysToRetest) <= 3) {   // more than 3 days is hidden here only; the totals / percentages are not affected
                var dv = Number(r.daysToRetest);
                if (r.retestDone) {
                    var dCls = dv <= 1 ? 'rt-days-fast' : (dv <= 3 ? 'rt-days-mid' : 'rt-days-slow');
                    var dLbl = dv === 0 ? 'Same day (0d)' : (dv + ' day' + (dv === 1 ? '' : 's'));
                    daysHtml = '<span class="rt-days-pill ' + dCls + '">' + esc(dLbl) + '</span>';
                } else {
                    var wLbl = dv === 0 ? 'Failed today' : (dv + ' day' + (dv === 1 ? '' : 's') + ' waiting');
                    daysHtml = '<span class="rt-days-pill rt-days-wait">' + esc(wLbl) + '</span>';
                }
            }

            var rtDetailHtml = '', rtResultHtml = '';
            if (r.retestDone) {
                var resLbl = r.retestResult || 'PENDING';
                rtDetailHtml = '<div>' + refLink(r.retestRef, 'rt-ref-code') + '</div>'
                    + (r.retestDate ? '<div class="rt-style-sub">Collected / Tested: ' + esc(r.retestDate) + '</div>' : '');
                rtResultHtml = '<span class="rt-res-pill rt-res-' + esc(resLbl) + '">' + esc(resLbl) + '</span>';
            } else {
                rtDetailHtml = '<span style="color:#94a3b8;font-size:12px;font-weight:600">Awaiting retest collection</span>';
                rtResultHtml = '<span style="color:#94a3b8">&mdash;</span>';
            }

            return '<tr class="' + (r.retestDone ? 'rt-row-done' : 'rt-row-miss') + '">'
                + '<td style="white-space:nowrap;font-weight:700">' + esc(r.date) + '</td>'
                + '<td style="white-space:nowrap"><span class="rt-line-badge ' + lnCls + '">' + esc(lnTxt) + '</span></td>'
                + '<td><div>' + refLink(r.ref, 'rt-ref-code') + '</div>' + styleHtml + '</td>'
                + '<td>' + whyHtml + '</td>'
                + '<td style="white-space:nowrap">' + daysHtml + '</td>'
                + '<td>' + rtDetailHtml + '</td>'
                + '<td style="white-space:nowrap">' + rtResultHtml + '</td>'
                + '<td style="white-space:nowrap">' + markHtml + '</td>'
                + '</tr>';
        }).join('');
    }

    function show(k) {
        curKey = k;
        var x = D[k] || {failed: 0, retest: 0, pass: 0, tol: 0, fail: 0, pend: 0, pct: 0, series: []};
        var out = x.pass + x.tol + x.fail + x.pend;
        var open = Math.max(x.failed - x.retest, 0);
        var passPct = pct(x.pass, out);
        root.querySelectorAll('[data-rt]').forEach(function (b) { b.classList.toggle('on', b.dataset.rt === k); });
        $('rt-range').textContent = x.from === x.to ? x.from : (x.from + ' → ' + x.to);
        $('rt-fail').textContent = x.failed;
        $('rt-fail-pct').textContent = x.tested ? (x.passPct + '% Pass · ' + x.failPct + '% Failed') : 'Baseline';
        $('rt-fail-sub').textContent = x.tested ? (x.failed + ' failed of ' + x.tested + ' tested shoes (' + x.passed + ' passed)') : 'Failed bonding reports requiring corrective re-test';
        $('rt-n').textContent = x.retest;
        $('rt-p').textContent = x.pct + '% Retested';
        $('rt-p2').textContent = x.retest + ' retests collected for ' + x.failed + ' failed shoes';
        $('rt-ok').textContent = x.pass;
        $('rt-ok-pct').textContent = passPct + '% Pass';
        $('rt-ok2').textContent = x.pass + ' passed out of ' + out + ' tested retest report' + (out === 1 ? '' : 's');

        donut(
            $('rt-ring'),
            [
                {n: 'Retests Collected', v: x.retest, c: '#f97316', pct: pct(x.retest, x.failed)},
                {n: 'Failed Shoes Not Yet Retested', v: open, c: '#3b82f6', pct: pct(open, x.failed)}
            ],
            x.pct + '%',
            'of ' + x.failed + ' failed'
        );
        donut(
            $('rt-out'),
            ['pass', 'tol', 'fail', 'pend'].map(function (c) {
                return {n: NAME[c], v: x[c], c: COL[c], pct: pct(x[c], out)};
            }),
            pct(x.pass, out) + '%',
            'of ' + out + ' retests'
        );
        $('rt-trend-t').textContent = 'Failed Bonding Lines & Retest Status';
        renderTable(x);
    }
    root.addEventListener('click', function (e) {
        var b = e.target.closest('[data-rt]');
        if (b) { show(b.dataset.rt); return; }
        var f = e.target.closest('[data-rt-flt]');
        if (f) {
            curFlt = f.dataset.rtFlt;
            root.querySelectorAll('[data-rt-flt]').forEach(function (btn) { btn.classList.toggle('on', btn.dataset.rtFlt === curFlt); });
            renderTable(D[curKey] || {from: '', to: ''});
        }
    });
    show('monthly');
})();
</script>
