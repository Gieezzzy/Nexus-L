<?php
/**
 * Lab Monitoring > VF Test Monitoring
 * Tracks all VF Test Requests through: InterLink Submission -> Grouped PDF Email -> In-Transit Container No -> Lab Received (Expect Date / Report No) -> PASS Certificate PDF.
 */
?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/labmonitor-vf_requests-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/labmonitor-vf_requests-1.css') ?>">

<div class="vfm-tabs" id="vfm-tabs" role="tablist">
    <button type="button" class="vfm-tab active" data-tab="email" role="tab">Email Submission</button>
    <button type="button" class="vfm-tab" data-tab="transit" role="tab">Transit Monitoring <span class="vfm-tabbadge" id="vfm-tb-transit" hidden></span></button>
    <button type="button" class="vfm-tab" data-tab="material" role="tab">Request Material Monitoring</button>
</div>

<div id="vfm-pane-email" data-pane="email">
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
    <div class="vfm-subtabs" id="vfm-btabs" role="tablist" style="margin-bottom:10px">
        <button type="button" class="vfm-subtab active" data-bt="CPSIA" role="tab">CPSIA <span class="vfm-cnt" id="kb-CPSIA">0</span></button>
        <button type="button" class="vfm-subtab" data-bt="KC" role="tab">KC Test <span class="vfm-cnt" id="kb-KC">0</span></button>
        <button type="button" class="vfm-subtab" data-bt="OTHER" role="tab" id="vfm-bt-other" hidden>Other packages <span class="vfm-cnt" id="kb-OTHER">0</span></button>
    </div>
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
            <option value="FOLLOW_UP">FOLLOW_UP (lab asks / failed)</option>
            <option value="COMPLETED">COMPLETED</option>
            <option value="HISTORY">HISTORY (Excel)</option>
            <option value="FAILED">FAILED</option>
        </select>
        <input type="search" id="vfm-q" placeholder="Search Prod Code, MTL#, Style, App No, Container#, Report#..." style="min-width:260px;flex:1">
        <a class="vfm-btn" id="vfm-export" download href="<?= esc(base_url('lab-monitoring/vf-requests/export-summary'), 'attr') ?>" title="Download the CPSIA, KC, RSL and GB test summary as one Excel file (all requests)">Export Summary (Excel)</a>
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
</div><!-- /vfm-pane-email -->

<!-- ===================== Transit Monitoring ===================== -->
<div id="vfm-pane-transit" data-pane="transit" hidden>
    <div class="vfm-kpis">
        <div class="card glass vfm-kpi"><small>Shipments (tracking numbers)</small><b id="kt-ship">0</b></div>
        <div class="card glass vfm-kpi"><small>Check due now</small><b id="kt-due" style="color:#d97706">0</b></div>
        <div class="card glass vfm-kpi"><small>Updates waiting</small><b id="kt-upd" style="color:#dc2626">0</b></div>
        <div class="card glass vfm-kpi"><small>Delivered</small><b id="kt-del" style="color:#059669">0</b></div>
    </div>
    <div class="card glass vfm-wrap">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px">
            <h4 style="margin:0">Transit Monitoring &mdash; samples on their way to the lab</h4>
            <div class="vfm-bar" style="margin:0">
                <input type="search" id="vt-q" placeholder="Search Tracking No, App No, Prod Code, MTL#..." style="min-width:280px">
                <button type="button" class="vfm-btn primary" id="vt-sync">Sync Outlook Emails Now</button>
            </div>
        </div>
        <div class="vfm-subtabs" id="vt-tabs" role="tablist" style="margin-bottom:10px">
            <button type="button" class="vfm-subtab active" data-tt="CPSIA" role="tab">CPSIA <span class="vfm-cnt" id="kt-CPSIA">0</span></button>
            <button type="button" class="vfm-subtab" data-tt="KC" role="tab">KC Test <span class="vfm-cnt" id="kt-KC">0</span></button>
        </div>
        <div class="muted vfm-help" id="vt-help"></div>
        <div class="vfm-tbl-wrap vfm-roomy" style="max-height:620px;min-height:0">
            <table class="vfm-tbl vfm-tree" style="width:100%">
                <thead><tr><th>Tracking no. / e-mail</th><th>Requests</th><th>Latest SF status</th><th>Checks</th><th>State</th><th>Actions</th></tr></thead>
                <tbody id="vt-body"><tr><td colspan="6" class="muted" style="text-align:center;padding:14px">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===================== Request Material Monitoring ===================== -->
<div id="vfm-pane-material" data-pane="material" hidden>
    <div class="card glass vfm-wrap vfm-pad">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px">
            <h4 style="margin:0">Request Material Monitoring &mdash; where every request stands</h4>
            <div class="vfm-bar" style="margin:0">
                <select id="vm-pkg">
                    <option value="">All Test Packages</option>
                    <?php foreach ($packages as $pkg): ?><option value="<?= esc($pkg, 'attr') ?>"><?= esc($pkg) ?></option><?php endforeach ?>
                </select>
                <input type="search" id="vm-q" placeholder="Search Prod Code, MTL#, Style, App No, Tracking, Report#..." style="min-width:280px">
            </div>
        </div>
        <div class="vfm-subtabs" id="vm-tabs" role="tablist">
            <button type="button" class="vfm-subtab active" data-status="TESTING" role="tab">Testing <span class="vfm-cnt" id="km-TESTING">0</span></button>
            <button type="button" class="vfm-subtab" data-status="DONE" role="tab">Done / Complete <span class="vfm-cnt" id="km-DONE">0</span></button>
            <button type="button" class="vfm-subtab" data-status="FOLLOW_UP" role="tab">For follow-up / Failed <span class="vfm-cnt vfm-cnt-red" id="km-FOLLOW_UP">0</span></button>
        </div>
        <div class="muted vfm-help" id="vm-help"></div>
        <div class="vfm-tbl-wrap vfm-roomy" id="vm-transit" style="max-height:660px;min-height:0" hidden>
            <table class="vfm-tbl vfm-tree" style="width:100%">
                <thead><tr><th>Tracking no. / e-mail</th><th>Requests</th><th>Latest status</th><th>Checks</th><th>State</th><th>Actions</th></tr></thead>
                <tbody id="vm-transit-body"></tbody>
            </table>
        </div>
        <div class="vfm-tbl-wrap vfm-roomy" id="vm-main" style="max-height:660px;min-height:0">
            <table class="vfm-tbl vfm-tree" style="width:100%">
                <thead><tr><th>Request e-mail / sender</th><th>Subject</th><th>Test Package</th><th>Requests</th></tr></thead>
                <tbody id="vm-body"></tbody>
            </table>
        </div>
        <div class="vfm-pager" id="vm-pager"></div>
    </div>
</div>

<!-- check on SF: what the person saw on the carrier's page -->
<div class="vfm-modal" id="vft-check-modal">
    <div class="vfm-card">
        <h4 style="margin:0 0 4px">Record check &mdash; <span id="vc-trk" class="mono"></span></h4>
        <div class="muted" style="font-size:12px;margin-bottom:8px">Type the latest status line the tracking page shows for this number.</div>
        <input type="hidden" id="vc-id">
        <div class="vfm-grid" style="grid-template-columns:1fr">
            <div><label for="vc-status">Latest status on the tracking page</label><textarea id="vc-status" rows="3" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:7px;padding:6px 8px;font:inherit;font-size:13px" placeholder="e.g. Departed from Shenzhen / Arrived at Guangzhou hub / Delivered"></textarea></div>
            <div><label for="vc-when">Time of that status</label><input type="datetime-local" id="vc-when"></div>
        </div>
        <label style="display:flex;gap:6px;align-items:center;margin:10px 0 0;font-size:12.5px"><input type="checkbox" id="vc-delivered"> Delivered / signed for</label>
        <div style="font-size:12px;font-weight:700;margin:12px 0 4px">History</div>
        <div id="vc-hist" style="max-height:140px;overflow:auto;border:1px solid #e2e8f0;border-radius:8px;padding:6px 10px;font-size:12px;color:#475569"></div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
            <button type="button" class="vfm-btn" id="vc-cancel">Cancel</button>
            <button type="button" class="vfm-btn primary" id="vc-save">Save check</button>
        </div>
    </div>
</div>

<!-- send the shipment to the lab by e-mail -->
<div class="vfm-modal" id="vft-mail-modal">
    <div class="vfm-card" style="width:min(920px,100%);max-height:94vh;overflow:auto">
        <h4 style="margin:0 0 6px">E-mail the lab &mdash; samples sent <span id="vtm-title" class="mono" style="font-weight:400;font-size:12px"></span></h4>
        <div class="muted" id="vtm-note" style="font-size:12px;margin-bottom:8px"></div>
        <div class="vfm-grid" style="grid-template-columns:1fr">
            <div><label for="vtm-to-in">To</label><div class="vfm-chips" id="vtm-to" data-chips><input type="text" id="vtm-to-in" placeholder="Add an address, then comma, semicolon, space or Enter"></div></div>
            <div><label for="vtm-cc-in">CC</label><div class="vfm-chips" id="vtm-cc" data-chips><input type="text" id="vtm-cc-in" placeholder="Add an address, then comma, semicolon, space or Enter"></div></div>
            <div><label for="vtm-subj">Subject</label><input type="text" id="vtm-subj"></div>
        </div>
        <div id="vtm-kc" style="display:none;margin-top:10px;padding:10px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc">
            <div class="vfm-grid" style="grid-template-columns:1fr">
                <div><label for="vtm-trk">Tracking No. (KC courier)</label><input type="text" id="vtm-trk" placeholder="e.g. 87705245817"></div>
            </div>
            <div style="font-size:12px;font-weight:700;margin:10px 0 4px">Attachments <span class="muted" style="font-weight:400">(the eTRF PDFs, Invoice and Packing List are added automatically; add the proof of transit and anything else here)</span></div>
            <input type="file" id="vtm-files" multiple>
            <table class="vfm-kids" id="vtm-attached" style="margin-top:6px"></table>
        </div>
        <div style="font-size:12px;font-weight:700;margin:10px 0 4px">Message (<span id="vtm-count">0</span> request(s) in the table)</div>
        <iframe id="vtm-preview" sandbox="" style="width:100%;height:340px;border:1px solid #e2e8f0;border-radius:8px;background:#fff"></iframe>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
            <button type="button" class="vfm-btn" id="vtm-cancel">Cancel</button>
            <button type="button" class="vfm-btn primary" id="vtm-send">Send e-mail</button>
        </div>
    </div>
</div>

<!-- KC Test: the Invoice and Packing List that go with the eTRF e-mail (editable; Amount and TOTAL stay formulas in the Excel files) -->
<div class="vfm-modal" id="vfm-kc-modal">
    <div class="vfm-card" style="width:min(1100px,100%);max-height:94vh;overflow:auto">
        <h4 style="margin:0 0 6px">KC Test &mdash; Invoice &amp; Packing List <span id="kc-title" class="mono" style="font-weight:400;font-size:12px"></span></h4>
        <div class="muted" style="font-size:12px;margin-bottom:8px">Both Excel files are built from your sample workbooks with the data below and attached to the e-mail automatically. Amount = Qty &times; Unit price (a live formula in Excel); the total in words follows.</div>
        <div class="vfm-grid" style="grid-template-columns:repeat(3,1fr)">
            <div><label for="kc-inv">Invoice No.</label><input type="text" id="kc-inv"></div>
            <div><label for="kc-date">Date</label><input type="date" id="kc-date"></div>
            <div><label for="kc-ctn">Cartons</label><input type="number" min="1" id="kc-ctn"></div>
            <div><label for="kc-nw">N.W.</label><input type="text" id="kc-nw"></div>
            <div><label for="kc-gw">G.W.</label><input type="text" id="kc-gw"></div>
            <div><label for="kc-ms">Measurement</label><input type="text" id="kc-ms"></div>
        </div>
        <div style="overflow:auto;margin-top:10px"><table class="vfm-kids" id="kc-lines"></table></div>
        <div style="display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap">
            <button type="button" class="vfm-btn sm" id="kc-add">+ Line</button>
            <button type="button" class="vfm-btn sm" id="kc-sample" title="Adds the 19 material lines of the sample workbook (edit them to the shoe)">+ Sample material lines</button>
            <b style="margin-left:auto">Total: US$ <span id="kc-total">0.00</span></b>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;flex-wrap:wrap">
            <a class="vfm-btn" id="kc-dl-inv" download href="#">Download Invoice</a>
            <a class="vfm-btn" id="kc-dl-pack" download href="#">Download Packing List</a>
            <button type="button" class="vfm-btn" id="kc-close">Close</button>
            <button type="button" class="vfm-btn primary" id="kc-save">Save</button>
        </div>
    </div>
</div>

<!-- the e-mail a report came in / the follow-up remark -->
<div class="vfm-modal" id="vfm-mailview-modal">
    <div class="vfm-card" style="width:min(720px,100%);max-height:90vh;overflow:auto">
        <h4 style="margin:0 0 6px" id="mv-title">E-mail</h4>
        <div class="muted" id="mv-meta" style="font-size:12px;margin-bottom:8px"></div>
        <div id="mv-fail" style="display:none;background:#fee2e2;color:#991b1b;border-radius:8px;padding:8px 10px;font-size:12.5px;margin-bottom:8px"></div>
        <pre id="mv-body" style="white-space:pre-wrap;word-break:break-word;font:inherit;font-size:13px;margin:0;border:1px solid #e2e8f0;border-radius:8px;padding:10px;max-height:46vh;overflow:auto"></pre>
        <div style="display:flex;justify-content:flex-end;margin-top:12px"><button type="button" class="vfm-btn" id="mv-close">Close</button></div>
    </div>
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
            <div><label>Stage</label><select id="e-stage"><option>REQUESTED</option><option>SUBMITTED_INTERLINK</option><option>EMAIL_SENT</option><option>IN_TRANSIT</option><option>LAB_RECEIVED</option><option>FOLLOW_UP</option><option>COMPLETED</option><option>FAILED</option></select></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
            <button type="button" class="vfm-btn" id="e-cancel">Cancel</button>
            <button type="button" class="vfm-btn primary" id="e-save">Save</button>
        </div>
    </div>
</div>
