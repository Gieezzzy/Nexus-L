<?php /** Third Party Test page, laid out like Lab Tracker. Data: $type, $types */ ?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/thirdparty-index-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/thirdparty-index-1.css') ?>">
<style>
/* readability polish */
.tp-kpis{gap:14px;margin-bottom:16px}
.tp-kpi{padding:16px 18px;border-radius:14px;border:1px solid rgba(148,163,184,.22);box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 16px -10px rgba(15,23,42,.18)}
.tp-kpi small{font-size:11px;letter-spacing:.06em}
.tp-kpi b{font-size:26px;line-height:1.15;font-variant-numeric:tabular-nums}
.tp-ico{width:44px;height:44px;border-radius:12px}
.tp-tabs{gap:4px;margin-bottom:14px}
.tp-tab{padding:10px 16px;border-radius:8px 8px 0 0;transition:background .15s,color .15s}
.tp-tab:hover{background:rgba(37,99,235,.06)}
.tp-wrap{max-height:clamp(280px,calc(100vh - 340px),620px);min-height:240px;border-radius:12px;background:#fff}
.tp-table{font-size:13px;line-height:1.45}
.tp-table th{padding:11px 14px;font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;color:#475569;box-shadow:inset 0 -1px 0 #cbd5e1;border-bottom:0;z-index:2}
.tp-table td{padding:11px 14px;color:#1e293b;vertical-align:middle}
.tp-table tbody tr:nth-child(even) td{background:rgba(241,245,249,.55)}
.tp-table tbody tr:hover td{background:rgba(37,99,235,.07)}
.tp-badge{padding:3px 10px;font-weight:600}
.tp-res{padding:4px 8px;border-radius:8px}
.tp-start,.tp-upload{border-radius:8px}
.tp-start{padding:3px 10px}
.tp-empty{padding:40px 10px;font-style:italic}
</style>

<div class="tp-kpis">
    <div class="card glass tp-kpi">
        <div><small>Total Lab Test Reports</small><b id="tp-kpi-total"><?= $type === 'qima' ? number_format(count($orders ?? [])) : '0' ?></b><span class="tp-kpi-sub"><?= esc($types[$type][0]) ?> Reports</span></div>
        <span class="tp-ico tp-ico-blue"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 3h6M10 3v6L5 19a2 2 0 001.8 3h10.4A2 2 0 0019 19l-5-10V3"/></svg></span>
    </div>
    <div class="card glass tp-kpi">
        <div><small>Passed Reports</small><b class="tp-pass">0</b><span class="tp-kpi-sub">Meets the buyer's requirement</span></div>
        <span class="tp-ico tp-ico-pass">✓</span>
    </div>
    <div class="card glass tp-kpi">
        <div><small>Tolerable / Data</small><b class="tp-warn">0</b><span class="tp-kpi-sub">Within Tolerance Band</span></div>
        <span class="tp-ico tp-ico-warn"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></span>
    </div>
    <div class="card glass tp-kpi">
        <div><small>Failed Reports</small><b class="tp-fail">0</b><span class="tp-kpi-sub">Requires Retest / Action</span></div>
        <span class="tp-ico tp-ico-fail">✕</span>
    </div>
</div>

<div class="card glass" style="padding:16px">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">
        <div>
            <b><?= esc($types[$type][0]) ?></b>
            <div class="muted" style="font-size:12.5px"><?= esc($types[$type][1]) ?></div>
        </div>
        <?php if ($type === 'qima'): ?>
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
            <input type="search" id="tp-filter" placeholder="Search MTL #, Production Code, P.O#, Style or Colorway..." style="width:min(380px,100%);flex:1 1 220px;padding:7px 10px;border:1px solid var(--border,#cbd5e1);border-radius:6px;font:inherit;font-size:13px">
            <button type="button" id="tp-upload" class="tp-upload" title="Upload the QIMA schedule (.xlsx)">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M12 16V4m0 0l-4 4m4-4l4 4M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/></svg> Upload
            </button>
            <input type="file" id="tp-file" accept=".xlsx" hidden>
        </div>
        <?php endif ?>
    </div>
    <?php if ($type === 'qima'): ?>
    <div class="tp-wrap">
        <table class="tp-table" id="tp-orders">
            <thead>
                <tr><th style="width:56px"></th><th>MTL #</th><th>Production Code</th><th>P.O#</th><th>Style</th><th>Colorway</th><th>CRD</th><th>Pairs</th><th>Result</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($orders ?? [] as $o): ?>
                    <tr>
                        <td style="text-align:center;padding:3px"><?php if (! empty($o['mtl_no'])): ?><img class="tp-thumb" alt="" hidden src="<?= site_url('thirdparty/qima-image') ?>?mtl=<?= rawurlencode((string) $o['mtl_no']) ?>" onload="this.hidden=false" onerror="this.hidden=true"><?php endif ?></td>
                        <td class="mono"><?= esc($o['mtl_no'] ?? '') ?></td>
                        <td class="mono"><?= esc($o['prod_code'] ?? '') ?></td>
                        <td><?= esc($o['po_no'] ?? '') ?></td>
                        <td><?= esc($o['style_no'] ?? '') ?></td>
                        <td><?= esc($o['colorway'] ?? '') ?></td>
                        <td style="white-space:nowrap"><?= esc(substr((string) ($o['crd'] ?? ''), 0, 10)) ?></td>
                        <td style="text-align:right"><?= $o['pairs'] !== null && $o['pairs'] !== '' ? number_format((int) $o['pairs']) : '' ?></td>
                        <?php
                            // Done = the lab's tests for this MTL # have a result; Ongoing Testing = tests exist but no result yet; Pending = no test yet
                            $res = strtoupper(trim((string) ($o['result'] ?? '')));
                            if ($res !== '') {
                                $status = 'Done';
                            } elseif ((int) ($o['tests'] ?? 0) > 0) {
                                $status = 'Ongoing Testing';
                            } else {
                                $status = 'Pending';
                            }
                            $resCls = $res === 'FAIL' ? 'tp-b-fail' : ($res === 'TOLERABLE' ? 'tp-b-warn' : 'tp-b-ok');
                        ?>
                        <td><?= $res !== '' ? '<span class="tp-badge ' . $resCls . '">' . esc($res) . '</span>' : '-' ?></td>
                        <td style="white-space:nowrap"><span class="tp-badge tp-s-<?= strtolower(str_replace(' ', '', $status)) ?>"><?= esc($status) ?></span></td>
                    </tr>
                <?php endforeach ?>
                <?php if (empty($orders)): ?><tr><td colspan="10" class="tp-empty">No QIMA records found.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
    <script>
    (function () {
        document.getElementById('tp-orders').addEventListener('click', function (e) {   // thumbnail -> larger picture (click or Esc to close)
            var im = e.target.closest('.tp-thumb');
            if (!im || im.hidden) return;
            var z = document.createElement('div');
            z.className = 'tp-zoom';
            z.innerHTML = '<img src="' + im.src + '" alt="">';
            var close = function () { z.remove(); document.removeEventListener('keydown', onKey); };
            var onKey = function (k) { if (k.key === 'Escape') close(); };
            z.addEventListener('click', close);
            document.addEventListener('keydown', onKey);
            document.body.appendChild(z);
        });
        var up = document.getElementById('tp-upload'), file = document.getElementById('tp-file');
        up.addEventListener('click', function () { file.click(); });
        file.addEventListener('change', function () {   // QIMA schedule workbook -> tbl_qima + pictures saved by MTL #
            if (!file.files.length) return;
            var fd = new FormData();
            fd.append('file', file.files[0]);
            up.disabled = true;
            up.lastChild.textContent = ' Uploading...';
            var meta = document.querySelector('meta[name="X-CSRF-TOKEN"]');   // thirdparty/* is behind the CSRF check, unlike dtr/*
            if (meta) fd.append('<?= csrf_token() ?>', meta.content);          // sent as header and as form field
            fetch('<?= site_url('thirdparty/qima-import') ?>', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta ? meta.content : '' }, body: fd })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    alert(j && j.message ? j.message : 'Upload failed.');
                    if (j && j.ok) { location.reload(); }
                })
                .catch(function () { alert('Upload failed.'); })
                .finally(function () { up.disabled = false; up.lastChild.textContent = ' Upload'; file.value = ''; });
        });
        var f = document.getElementById('tp-filter'), rows = document.querySelectorAll('#tp-orders tbody tr');
        if (!f) return;
        f.addEventListener('input', function () {
            var q = f.value.trim().toLowerCase();
            rows.forEach(function (r) { r.hidden = q !== '' && r.textContent.toLowerCase().indexOf(q) === -1; });
        });
    })();
    </script>
    <?php else: ?>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">
        <input type="search" id="cfa-search" placeholder="Search Lab Ref, Prod. Code, MTL, Style, Test Name, TR No..." style="width:min(380px,100%);flex:1 1 220px;padding:7px 10px;border:1px solid var(--border,#cbd5e1);border-radius:6px;font:inherit;font-size:13px" aria-label="Search CFA reports">
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--muted,#64748b)">
            <span id="cfa-count">0 record(s)</span>
            <button type="button" id="cfa-prev" class="tp-start" style="margin-left:0">&lsaquo; Prev</button>
            <span id="cfa-page" style="font-family:ui-monospace,monospace">1 / 1</span>
            <button type="button" id="cfa-next" class="tp-start" style="margin-left:0">Next &rsaquo;</button>
        </div>
    </div>
    <div id="cfa-cap-wrap" style="margin-bottom:12px;display:none"></div>
    <div class="tp-wrap">
        <table class="tp-table">
            <thead>
                <tr>
                    <th>Lab Ref No</th>
                    <th>Prod. Code</th>
                    <th>Prod. Date</th>
                    <th>Style / Colorway</th>
                    <th>MTL #</th>
                    <th>Test Name</th>
                    <th>TR Refs</th>
                    <th>CFA Ack / Requests</th>
                    <th>Doc Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody id="cfa-tbody">
                <tr><td colspan="10" class="tp-empty">Loading CFA &amp; CAP records...</td></tr>
            </tbody>
        </table>
    </div>
    <script>
    (function () {
        var page = 1, limit = 15, total = 0, timer = null;
        var tbody = document.getElementById('cfa-tbody');
        var capWrap = document.getElementById('cfa-cap-wrap');
        var cntEl = document.getElementById('cfa-count');
        var pgEl = document.getElementById('cfa-page');
        var kpiTot = document.getElementById('tp-kpi-total');
        var qEl = document.getElementById('cfa-search');
        var esc = function (s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        };
        var badge = function (val) {
            var s = String(val || '').trim();
            if (!s) return '-';
            var u = s.toUpperCase();
            var cls = (u === 'COMPLETE' || u === 'PASS' || u === 'DONE') ? 'tp-b-ok'
                : (u.indexOf('FAIL') !== -1 ? 'tp-b-fail' : 'tp-b-warn');
            return '<span class="tp-badge ' + cls + '">' + esc(s) + '</span>';
        };
        function load(p) {
            page = Math.max(1, p || 1);
            var q = (qEl.value || '').trim();
            tbody.innerHTML = '<tr><td colspan="10" class="tp-empty">Loading records...</td></tr>';
            fetch('<?= site_url('dtr/appsheet-archive') ?>?type=cfa&q=' + encodeURIComponent(q) + '&page=' + page + '&limit=' + limit, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.ok) throw new Error('Fetch failed');
                total = Number(res.total || 0);
                page = Number(res.page || page);
                var pages = Math.max(1, Math.ceil(total / limit));
                cntEl.textContent = total.toLocaleString() + ' record(s)';
                pgEl.textContent = page + ' / ' + pages;
                if (kpiTot && q === '') kpiTot.textContent = total.toLocaleString();
                if (Array.isArray(res.capRows) && res.capRows.length) {
                    capWrap.style.display = '';
                    capWrap.innerHTML = '<div style="padding:10px 12px;border:1px solid rgba(148,163,184,0.28);border-radius:10px;background:rgba(15,23,42,0.03)">' +
                        '<b style="font-size:12.5px">Bonding CAP Submissions (' + res.capRows.length + ')</b>' +
                        '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">' +
                        res.capRows.map(function (c) {
                            return '<span class="tp-badge tp-b-warn">' + esc(c.production_date || '-') + ' · Line ' + esc(c.line_no || '-') + ' · ' + esc(c.submitted_by || '-') + ' (Lab: ' + esc(c.date_uploaded_by_lab || '-') + ')</span>';
                        }).join('') + '</div></div>';
                } else if (page === 1) {
                    capWrap.style.display = 'none';
                }
                var rows = Array.isArray(res.rows) ? res.rows : [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="10" class="tp-empty">No CFA &amp; CAP records found.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r) {
                    var trs = [r.tr_bonding ? 'B:' + r.tr_bonding : '', r.tr_flexing ? 'F:' + r.tr_flexing : '', r.tr_mtr ? 'M:' + r.tr_mtr : ''].filter(Boolean).join(' · ');
                    var ack = (r.ack_count || 0) + ' ack' + (r.ack_pics ? ' (' + r.ack_pics + ')' : '') + (Number(r.req_count || 0) > 0 ? ' · ' + r.req_count + ' req (' + (r.latest_req_status || 'Open') + ')' : '');
                    return '<tr>' +
                        '<td style="font-family:ui-monospace,monospace"><b>' + esc(r.lab_ref_no || r.record_id || '-') + '</b></td>' +
                        '<td style="font-family:ui-monospace,monospace">' + esc(r.production_code || '-') + '</td>' +
                        '<td style="font-family:ui-monospace,monospace;white-space:nowrap">' + esc(r.production_date || '-') + '</td>' +
                        '<td>' + esc([r.style, r.colorway].filter(Boolean).join(' / ') || '-') + '</td>' +
                        '<td style="font-family:ui-monospace,monospace">' + esc(r.mtl || '-') + '</td>' +
                        '<td>' + esc(r.test_name || '-') + '</td>' +
                        '<td style="font-family:ui-monospace,monospace">' + esc(trs || '-') + '</td>' +
                        '<td>' + esc(ack) + '</td>' +
                        '<td>' + badge(r.document_status || r.sent_cfa) + '</td>' +
                        '<td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' + esc(r.remarks || '') + '">' + esc(r.remarks || '-') + '</td>' +
                        '</tr>';
                }).join('');
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="10" class="tp-empty">Could not load CFA &amp; CAP records.</td></tr>';
            });
        }
        document.getElementById('cfa-prev').addEventListener('click', function () { if (page > 1) load(page - 1); });
        document.getElementById('cfa-next').addEventListener('click', function () { if (page * limit < total) load(page + 1); });
        qEl.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { load(1); }, 250); });
        load(1);
    })();
    </script>
    <?php endif ?>
</div>
