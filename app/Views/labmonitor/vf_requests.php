<?php
/**
 * Lab Monitoring > VF Test Monitoring
 * Tracks all VF Test Requests through: InterLink Submission -> Grouped PDF Email -> In-Transit Container No -> Lab Received (Expect Date / Report No) -> PASS Certificate PDF.
 */
?>
<style>
.vfm-wrap{padding:16px}
.vfm-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:14px}
.vfm-kpi{padding:12px 14px}
.vfm-kpi small{display:block;font-size:11px;font-weight:700;text-transform:uppercase;color:#64748b}
.vfm-kpi b{font-size:22px;font-weight:800}
.vfm-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}
.vfm-bar select,.vfm-bar input{border:1px solid var(--line,#cbd5e1);border-radius:8px;padding:6px 10px;font:inherit;font-size:13px;background:#fff}
.vfm-btn{border:1px solid var(--line,#cbd5e1);border-radius:8px;padding:6px 12px;font:inherit;font-size:12.5px;font-weight:600;background:#fff;cursor:pointer}
.vfm-btn.primary{background:var(--brand,#2563eb);border-color:var(--brand,#2563eb);color:#fff}
.vfm-btn.sm{padding:3px 8px;font-size:11.5px;border-radius:6px}
.vfm-tbl-wrap{max-height:calc(100vh - 330px);min-height:320px;overflow:auto;border:1px solid var(--line,#e2e8f0);border-radius:10px;background:#fff}
.vfm-tbl-wrap.vfm-small{max-height:220px;min-height:0;margin-bottom:12px}
.vfm-tbl{width:max-content;min-width:100%;border-collapse:separate;border-spacing:0;font-size:12.5px}
.vfm-tbl th{position:sticky;top:0;z-index:2;background:#f1f5f9;color:#334155;font-weight:700;padding:9px 12px;text-align:left;border-bottom:1px solid #cbd5e1;white-space:nowrap}
.vfm-tbl td{padding:8px 12px;border-bottom:1px solid #e2e8f0;vertical-align:middle;white-space:nowrap;max-width:260px;overflow:hidden;text-overflow:ellipsis}
.vfm-tbl tbody tr:nth-child(even) td{background:#fafbfd}
.vfm-tbl tbody tr:hover td{background:#eef4ff}
#vfm-body td:first-child,#vfm-body td:nth-child(2){position:sticky;background:inherit}
#vfm-body td:first-child{left:0;z-index:1;min-width:84px}
#vfm-body td:nth-child(2){left:84px;z-index:1;box-shadow:2px 0 0 #e2e8f0}
#vfm-body tr td:first-child,#vfm-body tr td:nth-child(2){background:#fff}
#vfm-body tr:nth-child(even) td:first-child,#vfm-body tr:nth-child(even) td:nth-child(2){background:#fafbfd}
.vfm-tbl thead th:first-child,.vfm-tbl thead th:nth-child(2){z-index:3}
#vfm-body-wrap th:first-child,#vfm-body-wrap th:nth-child(2){position:sticky;z-index:3}
#vfm-body-wrap th:first-child{left:0;min-width:84px}
#vfm-body-wrap th:nth-child(2){left:84px}
.vfm-tree .vfm-grp td{background:#f8fafc;cursor:pointer;font-weight:600;white-space:normal;max-width:none}
.vfm-tree .vfm-grp:hover td{background:#eff6ff}
.vfm-tree .vfm-chev{display:inline-block;width:14px;color:#64748b;transition:transform .15s}
.vfm-tree .vfm-grp.open .vfm-chev{transform:rotate(90deg)}
.vfm-tree .vfm-kidwrap{display:none}
.vfm-tree .vfm-kidwrap.open{display:table-row}
.vfm-tree .vfm-kidwrap > td{padding:0 0 4px 34px;background:#fff;max-width:none;white-space:normal}
.vfm-kids{width:100%;border-collapse:collapse;font-size:12.5px}
.vfm-kids th{background:transparent;color:#64748b;font-size:11px;letter-spacing:.04em;text-transform:uppercase;font-weight:700;padding:10px 14px;text-align:left;border-bottom:1px solid #e2e8f0;white-space:nowrap;position:static}
.vfm-kids td{padding:9px 14px;border-bottom:1px solid #eef2f7;vertical-align:middle;white-space:nowrap}
.vfm-kids tr:hover td{background:#f8fafc}
.vfm-kids input[type=checkbox]{width:16px;height:16px;cursor:pointer}
.vfm-tree .vfm-sub{font-weight:400;color:#64748b;font-size:11.5px}
.vfm-pager{display:flex;flex-wrap:wrap;gap:6px;align-items:center;justify-content:flex-end;margin:10px 0 0;font-size:12.5px}
.vfm-pager .vfm-btn[disabled]{opacity:.45;cursor:default}
.vfm-pg-info{margin-right:auto;color:#64748b}
.vfm-pg-size{display:flex;gap:6px;align-items:center;margin-left:10px;color:#64748b}
.vfm-pg-size select{border:1px solid #cbd5e1;border-radius:6px;padding:3px 6px;font:inherit;font-size:12px}
.vfm-count{font-size:12px;color:#64748b;margin:0 0 8px}
.vfm-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap}
.vfm-ok{background:#dcfce7;color:#166534}.vfm-info{background:#dbeafe;color:#1e40af}.vfm-warn{background:#fef3c7;color:#92400e}.vfm-err{background:#fee2e2;color:#991b1b}
.vfm-modal{position:fixed;inset:0;z-index:9990;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:16px}
.vfm-modal.open{display:flex}
.vfm-card{background:#fff;width:min(540px,100%);border-radius:14px;padding:18px 20px;box-shadow:0 24px 60px rgba(15,23,42,.35)}
.vfm-chips{display:flex;flex-wrap:wrap;gap:5px;align-items:center;border:1px solid #cbd5e1;border-radius:7px;padding:5px 7px;min-height:38px;background:#fff;cursor:text;max-height:120px;overflow:auto}
.vfm-chips:focus-within{border-color:#2563eb;box-shadow:0 0 0 2px #dbeafe}
.vfm-chip{display:inline-flex;align-items:center;gap:5px;background:#e0ecff;color:#1e3a8a;border:1px solid #bfd4fb;border-radius:999px;padding:2px 4px 2px 10px;font-size:12.5px;font-weight:600;max-width:100%}
.vfm-chip.bad{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
.vfm-chip button{border:0;background:rgba(15,23,42,.12);color:inherit;width:18px;height:18px;border-radius:50%;cursor:pointer;line-height:16px;padding:0;font-size:13px}
.vfm-chips input{border:0 !important;outline:0;flex:1;min-width:160px;padding:4px 2px !important;font:inherit;font-size:13px;background:transparent}
.vfm-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px}
.vfm-grid label{display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:3px}
.vfm-grid input,.vfm-grid select{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:7px;padding:6px 8px;font:inherit;font-size:13px}
</style>

<div id="vfm-sync-health" class="card glass" style="display:none;margin-bottom:10px;padding:10px 14px;font-size:13px"></div>
<div class="vfm-kpis" id="vfm-kpis">
    <div class="card glass vfm-kpi"><small>New requests (live)</small><b id="k-total">0</b></div>
    <div class="card glass vfm-kpi"><small>Submitted to InterLink</small><b id="k-sub" style="color:#2563eb">0</b></div>
    <div class="card glass vfm-kpi"><small>In Transit (Container)</small><b id="k-trn" style="color:#d97706">0</b></div>
    <div class="card glass vfm-kpi"><small>Lab Received</small><b id="k-lab" style="color:#7c3aed">0</b></div>
    <div class="card glass vfm-kpi"><small>Completed</small><b id="k-done" style="color:#059669">0</b></div>
    <div class="card glass vfm-kpi"><small>Excel history (records)</small><b id="k-hist" style="color:#64748b">0</b></div>
</div>

<div class="card glass vfm-wrap" style="margin-bottom:14px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px">
        <h4 style="margin:0">Email Request Batches &amp; Outgoing PDF Notifications</h4>
        <button type="button" class="vfm-btn primary" id="vfm-sync-emails">Sync Outlook Emails Now</button>
    </div>
    <div class="muted" style="font-size:12px;margin-bottom:8px">One row per e-mail that asked for tests. Open it to see its requests; tick the rows whose printed eTRF PDFs should be e-mailed back, then press <b>Send Batch PDF Email</b>. The e-mail is sent as a <b>reply</b> to the request e-mail.</div>
    <div class="vfm-tbl-wrap" style="max-height:560px;min-height:0;margin-bottom:12px">
        <table class="vfm-tbl vfm-tree" style="width:100%">
            <thead><tr><th>Batch / sender</th><th>Subject</th><th>Test Package</th><th>Progress</th><th>E-mail</th></tr></thead>
            <tbody id="vfm-batches"></tbody>
        </table>
    </div>
    <div class="vfm-pager" id="vfm-bpager"></div>
</div>

<div class="card glass vfm-wrap" id="vfm-root" data-base="<?= esc(base_url(), 'attr') ?>">
    <div class="vfm-bar">
        <select id="vfm-pkg">
            <option value="">All Test Packages</option>
            <?php foreach ($packages as $pkg): ?><option value="<?= esc($pkg, 'attr') ?>"><?= esc($pkg) ?></option><?php endforeach ?>
        </select>
        <select id="vfm-stage">
            <option value="">All Stages</option>
            <option value="REQUESTED">REQUESTED</option>
            <option value="SUBMITTING">SUBMITTING</option>
            <option value="SUBMITTED_INTERLINK">SUBMITTED_INTERLINK</option>
            <option value="EMAIL_SENT">EMAIL_SENT</option>
            <option value="IN_TRANSIT">IN_TRANSIT</option>
            <option value="LAB_RECEIVED">LAB_RECEIVED</option>
            <option value="COMPLETED">COMPLETED</option>
            <option value="HISTORY">HISTORY (Excel)</option>
            <option value="FAILED">FAILED</option>
        </select>
        <input type="search" id="vfm-q" placeholder="Search Prod Code, MTL#, Style, App No, Container#, Report#..." style="min-width:260px;flex:1">
    </div>
    <div class="vfm-count" id="vfm-count"></div>
    <div class="vfm-tbl-wrap" id="vfm-body-wrap">
        <table class="vfm-tbl">
            <thead>
                <tr>
                    <th>Test Type</th>
                    <th>Prod Code</th>
                    <th>MTL#</th>
                    <th>Style</th>
                    <th>PO#</th>
                    <th>CRD</th>
                    <th>Season</th>
                    <th>TR No.</th>
                    <th>Generated App No.</th>
                    <th>InterLink PDF</th>
                    <th>Container No. (Transit)</th>
                    <th>Lab Order No. (SGS)</th>
                    <th>Lab Report No.</th>
                    <th>Expected Date</th>
                    <th>Result</th>
                    <th>Issued</th>
                    <th>Valid until</th>
                    <th>Fail reason</th>
                    <th>Certificate PDF</th>
                    <th>Stage</th>
                    <th>Edit</th>
                </tr>
            </thead>
            <tbody id="vfm-body"></tbody>
        </table>
    </div>
    <div class="vfm-pager" id="vfm-pager"></div>
</div>

<div class="vfm-modal" id="vfm-mail-modal">
    <div class="vfm-card" style="width:min(920px,100%);max-height:94vh;overflow:auto">
        <h4 style="margin:0 0 6px">Send eTRF PDFs &mdash; reply e-mail <span id="m-title" class="mono" style="font-weight:400;font-size:12px"></span></h4>
        <div class="muted" id="m-note" style="font-size:12px;margin-bottom:8px"></div>
        <div id="m-warn" style="display:none;background:#fef3c7;color:#92400e;border-radius:8px;padding:8px 10px;font-size:12.5px;margin-bottom:8px"></div>
        <div class="vfm-grid" style="grid-template-columns:1fr">
            <div><label for="m-to-in">To</label><div class="vfm-chips" id="m-to" data-chips><input type="text" id="m-to-in" placeholder="Add an address, then comma, semicolon, space or Enter"></div></div>
            <div><label for="m-cc-in">CC</label><div class="vfm-chips" id="m-cc" data-chips><input type="text" id="m-cc-in" placeholder="Add an address, then comma, semicolon, space or Enter"></div></div>
            <div><label for="m-subj">Subject</label><input type="text" id="m-subj"></div>
        </div>
        <label style="display:flex;gap:6px;align-items:center;margin:10px 0 6px;font-size:12.5px"><input type="checkbox" id="m-reply" checked> <span id="m-reply-lbl">Reply to the e-mail that asked for these tests (same thread)</span></label>
        <div style="font-size:12px;font-weight:700;margin:6px 0 4px">Attachments (<span id="m-count">0</span>)</div>
        <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:10px"><table class="vfm-kids" id="m-files"></table></div>
        <div style="font-size:12px;font-weight:700;margin:6px 0 4px">Message</div>
        <iframe id="m-preview" sandbox="" style="width:100%;height:300px;border:1px solid #e2e8f0;border-radius:8px;background:#fff"></iframe>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
            <button type="button" class="vfm-btn" id="m-cancel">Cancel</button>
            <button type="button" class="vfm-btn primary" id="m-send">Send e-mail</button>
        </div>
    </div>
</div>

<div class="vfm-modal" id="vfm-edit-modal">
    <div class="vfm-card">
        <h4 style="margin:0 0 8px">Update Tracking Details &mdash; <span id="e-title" class="mono"></span></h4>
        <input type="hidden" id="e-id">
        <div class="vfm-grid">
            <div><label>Generated App No.</label><input type="text" id="e-app"></div>
            <div><label>Container No. (In Transit)</label><input type="text" id="e-container"></div>
            <div><label>Lab Report No.</label><input type="text" id="e-rep"></div>
            <div><label>Expected Due Date</label><input type="date" id="e-due"></div>
            <div><label>Test Result</label><select id="e-res"><option value="">-</option><option value="PASS">PASS</option><option value="FAILED">FAILED</option><option value="PENDING_REVIEW">PENDING_REVIEW</option></select></div>
            <div><label>Stage</label><select id="e-stage"><option>REQUESTED</option><option>SUBMITTED_INTERLINK</option><option>EMAIL_SENT</option><option>IN_TRANSIT</option><option>LAB_RECEIVED</option><option>COMPLETED</option><option>FAILED</option></select></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
            <button type="button" class="vfm-btn" id="e-cancel">Cancel</button>
            <button type="button" class="vfm-btn primary" id="e-save">Save</button>
        </div>
    </div>
</div>
