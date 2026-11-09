<?php /** Third Party Test page, laid out like Lab Tracker. Data: $type, $types */ ?>
<style>
.tp-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:14px}
@media(max-width:1100px){.tp-kpis{grid-template-columns:repeat(2,1fr)}}
.tp-kpi{padding:14px 16px;display:flex;justify-content:space-between;align-items:center;gap:8px}
.tp-kpi small{display:block;font-size:12px;color:var(--muted,#64748b);font-weight:600;text-transform:uppercase;letter-spacing:.03em}
.tp-kpi b{display:block;font-size:22px;font-weight:800;margin:2px 0}
.tp-kpi-sub{font-size:12px;color:var(--muted,#64748b)}
.tp-ico{flex:none;width:38px;height:38px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-size:18px;font-weight:800}
.tp-ico svg{width:22px;height:22px}
.tp-ico-blue{background:rgba(37,99,235,.12);color:#2563eb}
.tp-ico-pass{background:rgba(16,185,129,.14);color:#059669}
.tp-ico-warn{background:rgba(245,158,11,.16);color:#d97706}
.tp-ico-fail{background:rgba(239,68,68,.14);color:#dc2626}
.tp-ico-purple{background:rgba(124,58,237,.12);color:#7c3aed}
.tp-pass{color:#059669}.tp-warn{color:#d97706}.tp-fail{color:#dc2626}
.tp-tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;border-bottom:1px solid rgba(148,163,184,.25)}
.tp-tab{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;font:inherit;font-size:13px;font-weight:600;color:var(--muted);text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-1px}
.tp-tab:hover{color:var(--text,inherit)}
.tp-tab.active{color:#2563eb;border-bottom-color:#2563eb}
.tp-tab svg{width:15px;height:15px}
.tp-wrap{max-height:420px;overflow:auto;border:1px solid var(--border,#e2e8f0);border-radius:8px}
.tp-table{width:100%;border-collapse:collapse;font-size:12.5px}
.tp-table th{position:sticky;top:0;background:#f1f5f9;color:#334155;font-weight:700;text-align:left;padding:8px 10px;border-bottom:1px solid #cbd5e1;white-space:nowrap}
.tp-table td{padding:10px;border-bottom:1px solid #e2e8f0}
.tp-badge{display:inline-block;padding:1px 8px;border-radius:999px;font-size:11.5px;white-space:nowrap}
.tp-b-ok{background:#dcfce7;color:#166534}.tp-b-warn{background:#fef3c7;color:#92400e}.tp-b-fail{background:#fee2e2;color:#991b1b}
.tp-s-done{background:#dcfce7;color:#166534}.tp-s-ongoingtesting{background:#dbeafe;color:#1e40af}.tp-s-pending{background:#f1f5f9;color:#475569}
.tp-res{font:inherit;font-size:12px;padding:2px 4px;border:1px solid #cbd5e1;border-radius:6px;background:#fff}
.tp-res.tp-b-ok{background:#dcfce7;color:#166534}.tp-res.tp-b-warn{background:#fef3c7;color:#92400e}.tp-res.tp-b-fail{background:#fee2e2;color:#991b1b}
.tp-start{margin-left:6px;font:inherit;font-size:11.5px;padding:1px 8px;border:1px solid #2563eb;border-radius:999px;background:#fff;color:#2563eb;cursor:pointer}
.tp-start:hover{background:#2563eb;color:#fff}
.tp-upload{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;font:inherit;font-size:13px;font-weight:600;color:#fff;background:#2563eb;border:0;border-radius:6px;cursor:pointer}
.tp-upload:hover{background:#1d4ed8}.tp-upload:disabled{opacity:.6;cursor:wait}
.tp-thumb{height:30px;width:40px;object-fit:cover;border-radius:4px;border:1px solid #cbd5e1;background:#fff;cursor:zoom-in;vertical-align:middle}
.tp-thumb:hover{border-color:#2563eb}
.tp-zoom{position:fixed;inset:0;z-index:10050;background:rgba(15,23,42,.72);display:flex;align-items:center;justify-content:center;cursor:zoom-out}
.tp-zoom img{max-width:86vw;max-height:86vh;border-radius:8px;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.5)}
.tp-empty{text-align:center;color:var(--muted);padding:26px 10px}
</style>

<div class="tp-kpis">
    <div class="card glass tp-kpi">
        <div><small>Total Lab Test Reports</small><b>0</b><span class="tp-kpi-sub">QIMA · CFA · RSL · KC · CPSIA · GB · COC · BOM</span></div>
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

<div class="tp-tabs" role="tablist">
    <?php foreach ($types as $k => $t): ?>
        <a href="<?= site_url('thirdparty/' . $k) ?>" role="tab" class="tp-tab <?= $k === $type ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM14 3v5h5M9 13h6M9 17h6"/></svg>
            <?= esc($t[0]) ?>
        </a>
    <?php endforeach ?>
</div>

<div class="card glass" style="padding:16px">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">
        <div>
            <b><?= esc($types[$type][0]) ?></b>
            <div class="muted" style="font-size:12.5px"><?= esc($types[$type][1]) ?></div>
        </div>
        <?php if ($type === 'qima'): ?>
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
            <input type="search" id="tp-filter" placeholder="Search MTL #, Production Code, P.O#, Style or Colorway..." style="width:380px;max-width:100%;padding:7px 10px;border:1px solid var(--border,#cbd5e1);border-radius:6px;font:inherit;font-size:13px">
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
    <div class="tp-wrap">
        <table class="tp-table">
            <thead>
                <tr>
                    <th>Report No.</th>
                    <th>Lab Ref No.</th>
                    <th>Material / Style</th>
                    <th>Supplier</th>
                    <th>Test Date</th>
                    <th>Result</th>
                    <th>Status</th>
                    <th>Encoder</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="8" class="tp-empty">No <?= esc($types[$type][0]) ?> records have been added yet.</td></tr>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>
