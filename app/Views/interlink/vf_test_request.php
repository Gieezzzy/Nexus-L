<?php
/**
 * InterLink > VF - Test Request
 * Monitors djc_lab@dtshoes.com test request tables, filters InterLink Applicant requests to the 4 VF Test Packages
 * (KC Test Package, GB - Full Package, CPSIA Product Testing Package, RSL), and automates Copy as New with dberp.production_orders.
 */
?>
<style>
.vfr-wrap{padding:16px}
.vfr-top{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:14px}
.vfr-controls{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}
.vfr-controls select,.vfr-controls input{border:1px solid var(--line,#cbd5e1);border-radius:8px;padding:7px 10px;font:inherit;font-size:13px;background:#fff;color:var(--ink)}
.vfr-btn{border:1px solid var(--line,#cbd5e1);border-radius:8px;padding:7px 12px;font:inherit;font-size:12.5px;font-weight:600;background:#fff;color:var(--ink);cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.vfr-btn:hover{border-color:#93c5fd;background:#eff6ff}
.vfr-btn.primary{background:var(--brand,#2563eb);border-color:var(--brand,#2563eb);color:#fff}
.vfr-btn.primary:hover{filter:brightness(1.06)}
.vfr-btn.sm{padding:3px 9px;font-size:11.5px;border-radius:6px}
.vfr-tbl-wrap{max-height:360px;overflow:auto;border:1px solid var(--line,#e2e8f0);border-radius:10px;background:#fff}
.vfr-tbl{width:100%;border-collapse:collapse;font-size:12.5px}
.vfr-tbl th{position:sticky;top:0;background:#f1f5f9;color:#334155;font-weight:700;padding:11px 16px;text-align:left;border-bottom:1px solid #cbd5e1;white-space:nowrap;z-index:1}
.vfr-tbl td{padding:10px 16px;border-bottom:1px solid #e2e8f0;vertical-align:middle}
.vfr-tbl th:first-child,.vfr-tbl td:first-child{padding-left:20px}
.vfr-tbl th:last-child,.vfr-tbl td:last-child{padding-right:20px}
.vfr-tbl tr:hover td{background:#f8fafc}
.vfr-link{color:#2563eb;font-weight:700;cursor:pointer;text-decoration:underline;background:none;border:0;font:inherit;padding:0}
.vfr-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap}
.vfr-b-req{background:#fef3c7;color:#92400e}
.vfr-b-sub{background:#dbeafe;color:#1e40af}
.vfr-b-ok{background:#dcfce7;color:#166534}
.vfr-b-err{background:#fee2e2;color:#991b1b}
.vfr-tree .vfr-grp td{background:#f8fafc;cursor:pointer;font-weight:600;border-bottom:1px solid #e2e8f0}
.vfr-tree .vfr-grp:hover td{background:#eff6ff}
.vfr-tree .vfr-chev{display:inline-block;width:14px;color:#64748b;transition:transform .15s}
.vfr-tree .vfr-grp.open .vfr-chev{transform:rotate(90deg)}
.vfr-tree .vfr-cnt{display:inline-block;min-width:20px;padding:1px 7px;margin-left:6px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:11px;font-weight:700;text-align:center}
.vfr-tree .vfr-kidwrap{display:none}
.vfr-tree .vfr-kidwrap.open{display:table-row}
.vfr-tree .vfr-kidwrap > td{padding:0 0 4px 34px;background:#fff}
.vfr-kids{width:100%;border-collapse:collapse;font-size:12.5px}
.vfr-kids th{background:transparent;color:#64748b;font-size:11px;letter-spacing:.04em;text-transform:uppercase;font-weight:700;padding:10px 14px;text-align:left;border-bottom:1px solid #e2e8f0;white-space:nowrap}
.vfr-kids td{padding:10px 14px;border-bottom:1px solid #eef2f7;vertical-align:middle}
.vfr-kids tr.vfr-item{cursor:pointer}
.vfr-kids tr.vfr-item:hover td{background:#eff6ff}
.vfr-doc{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:1px solid #bfdbfe;border-radius:7px;background:#eff6ff;color:#2563eb;font-size:13px}
.vfr-sub{font-weight:400;color:#64748b;font-size:11.5px}
.vfr-sec{margin-top:16px}
.vfr-sec h4{margin:0 0 8px;font-size:14px;display:flex;align-items:center;gap:8px}
.vfr-modal{position:fixed;inset:0;z-index:9990;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:16px}
.vfr-modal.open{display:flex}
.vfr-card{background:#fff;color:var(--ink,#0f172a);width:min(860px,100%);max-height:92vh;overflow:auto;border-radius:14px;padding:18px 20px;box-shadow:0 24px 60px rgba(15,23,42,.35)}
.vfr-card h4{margin:0 0 10px;display:flex;align-items:center;justify-content:space-between}
.vfr-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:8px}
.vfr-grid label{display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:3px}
.vfr-grid input,.vfr-grid select,.vfr-grid textarea{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:7px;padding:6px 8px;font:inherit;font-size:13px}
.vfr-erp-box{padding:11px 12px;border:1px solid #93c5fd;border-radius:10px;background:#eff6ff;margin-bottom:12px}
.vfr-kv{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;font-size:12.5px}
.vfr-kv div{background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;padding:6px 9px}
.vfr-kv small{display:block;color:#64748b;font-size:11px}
</style>

<div class="card glass vfr-wrap" id="vfr-root" data-base="<?= esc(base_url(), 'attr') ?>">
    <div class="vfr-top">
        <div>
            <h3 style="margin:0">VF &mdash; Test Request</h3>
            <div class="muted" style="font-size:12.5px">Incoming test requests from the e-mail sources you set up (<a href="#" id="vfr-open-sources">E-mail sources</a>), InterLink Applicant packages (KC, GB - Full Package, CPSIA, RSL) &amp; Copy as New with <code>dberp.production_orders</code></div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            <button type="button" class="vfr-btn" id="vfr-sync-emails">Sync Outlook Emails</button>
            <button type="button" class="vfr-btn" id="vfr-open-paste">Paste / Import Email Table</button>
            <button type="button" class="vfr-btn" id="vfr-open-sources-btn">E-mail sources</button>
            <button type="button" class="vfr-btn primary" id="vfr-sync-grid">Sync InterLink Requests</button>
        </div>
    </div>

    <div class="vfr-controls">
        <label for="vfr-pkg" style="font-size:12.5px;font-weight:700">Test Package:</label>
        <select id="vfr-pkg" style="min-width:250px">
            <option value="">All (<?= count($searchItems) ?>) VF Packages</option>
            <?php foreach ($searchItems as $pkg): ?>
                <option value="<?= esc($pkg, 'attr') ?>"><?= esc($pkg) ?></option>
            <?php endforeach ?>
        </select>
        <a href="<?= esc(base_url('settings'), 'attr') ?>" class="muted" style="font-size:12px" title="Add or remove dropdown items: Settings > VF > Test Package dropdown">edit list</a>
        <input type="search" id="vfr-q" placeholder="Filter by Application No., Prod Code, MTL#, Style, PO# or Color..." style="min-width:300px;flex:1">
        <button type="button" class="vfr-btn primary" id="vfr-direct-copy">Copy as New (Direct)</button>
    </div>

    <div class="vfr-sec">
        <h4>Incoming Requested Items (<span id="vfr-src-names">e-mail sources</span> / Imported Batches) <span class="muted" id="vfr-req-count" style="font-weight:400;font-size:12px"></span></h4>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px;font-size:12.5px">
            <label for="vfr-req-filter" style="font-weight:700">Show:</label>
            <select id="vfr-req-filter" style="border:1px solid #cbd5e1;border-radius:8px;padding:5px 9px;font:inherit;font-size:12.5px">
                <option value="all">All requests</option>
                <option value="todo">Still to do (waiting / sending / failed)</option>
                <option value="done">Finished (submitted, in InterLink, completed)</option>
            </select>
            <span class="muted" id="vfr-req-legend">Every item an e-mail asked for is listed, with how far it went.</span>
        </div>
        <div class="vfr-tbl-wrap" style="max-height:520px">
            <table class="vfr-tbl vfr-tree">
                <thead>
                    <tr>
                        <th>E-mail / sender</th>
                        <th>Subject</th>
                        <th>Test Package</th>
                        <th>Received</th>
                        <th>Requests</th>
                    </tr>
                </thead>
                <tbody id="vfr-req-body"><tr><td colspan="5" class="muted" style="text-align:center;padding:18px">Loading...</td></tr></tbody>
            </table>
        </div>
        <div id="vfr-req-pager" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;justify-content:flex-end;margin-top:8px;font-size:12.5px"></div>
    </div>

    <div class="vfr-sec">
        <h4>VF - Test Request List (InterLink 2.0 Applicant Packages) <span class="muted" id="vfr-il-count" style="font-weight:400;font-size:12px"></span></h4>
        <div class="vfr-tbl-wrap">
            <table class="vfr-tbl">
                <thead>
                    <tr>
                        <th>Application No.</th>
                        <th>Test Package(s)</th>
                        <th>Job Status</th>
                        <th>Overall Result</th>
                        <th>Style No.</th>
                        <th>Style Color / MTL#</th>
                        <th>Product Name</th>
                        <th>Color Description</th>
                        <th>Season</th>
                        <th>Submission Date</th>
                        <th>Report No.</th>
                    </tr>
                </thead>
                <tbody id="vfr-il-body"><tr><td colspan="11" class="muted" style="text-align:center;padding:18px">Loading...</td></tr></tbody>
            </table>
        </div>
        <div id="vfr-il-pager" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;justify-content:flex-end;margin-top:8px;font-size:12.5px"></div>
    </div>

    <div class="vfr-sec">
        <h4>Requests sent from NEXUS-L <span class="muted" id="vfr-sent-count" style="font-weight:400;font-size:12px"></span></h4>
        <div class="vfr-tbl-wrap" style="max-height:300px">
            <table class="vfr-tbl">
                <thead>
                    <tr>
                        <th>Application No.</th>
                        <th>Prod Code</th>
                        <th>Style</th>
                        <th>MTL#</th>
                        <th>Test Package</th>
                        <th>Submitted</th>
                        <th>PDF</th>
                        <th>InterLink status</th>
                        <th>Status (e-mail)</th>
                    </tr>
                </thead>
                <tbody id="vfr-sent-body"><tr><td colspan="9" class="muted" style="text-align:center;padding:18px">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- E-mail sources: which senders are read as test requests and how their table is read -->
<div class="vfr-modal" id="vfr-sources-modal">
    <div class="vfr-card" style="width:min(1040px,100%)">
        <h4>
            <span>E-mail sources</span>
            <button type="button" class="vfr-btn sm" data-close="vfr-sources-modal">Close</button>
        </h4>
        <div class="muted" style="font-size:12px;margin-bottom:10px">
            Mail from these senders is read as test requests; nothing is fixed in code. Changes apply to the next sync. The sender must also be allowed in the Outlook Attachment Sync admin app (its Control Center rules), or the mail never reaches NEXUS-L.
        </div>
        <div style="display:grid;grid-template-columns:230px 1fr;gap:14px;align-items:start">
            <div>
                <div id="src-list" style="display:grid;gap:6px"></div>
                <button type="button" class="vfr-btn sm primary" id="src-new" style="margin-top:8px;width:100%">+ New source</button>
            </div>
            <div id="src-form">
                <input type="hidden" id="s-id" value="0">
                <div class="vfr-grid" style="margin-top:0">
                    <div><label for="s-name">Name</label><input type="text" id="s-name" placeholder="e.g. DJC Lab test requests"></div>
                    <div><label for="s-pkg">Test Package for this source</label>
                        <select id="s-pkg"><option value="">Detect from the subject / table</option>
                            <?php foreach ($packages as $pkg): ?><option value="<?= esc($pkg, 'attr') ?>"><?= esc($pkg) ?></option><?php endforeach ?>
                        </select></div>
                    <div><label for="s-senders">Senders (e-mail, *@domain, or domain; comma or new line)</label><textarea id="s-senders" rows="3" placeholder="djc_lab@dtshoes.com, *@vendor.com"></textarea></div>
                    <div><label for="s-subjects">Subject must contain (optional, any one)</label><textarea id="s-subjects" rows="3" placeholder="Test Request, 测试"></textarea></div>
                    <div><label for="s-skip">Skip rows whose State matches (optional pattern)</label><input type="text" id="s-skip" placeholder="cancel|hold|done"></div>
                    <div><label for="s-priority">Order (lower runs first)</label><input type="number" id="s-priority" value="100" min="1" max="9999"></div>
                </div>
                <label style="display:flex;gap:6px;align-items:center;margin:8px 0;font-size:12.5px"><input type="checkbox" id="s-enabled" checked> Enabled</label>
                <div style="font-size:12px;font-weight:700;margin:10px 0 4px">Column names in this sender's table <span class="muted" style="font-weight:400">(leave blank to use the built-in names; several names separated by commas; <code>=Name</code> = whole header)</span></div>
                <div class="vfr-grid" id="s-map" style="margin-top:0"></div>
                <div style="font-size:12px;font-weight:700;margin:12px 0 4px">Try it: paste a mail table, then Preview</div>
                <textarea id="s-prev-html" rows="4" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:8px;font:inherit;font-size:12.5px" placeholder="Paste the e-mail table (HTML or copied rows)..."></textarea>
                <div id="s-prev-out" style="margin-top:6px;font-size:12px;overflow:auto;max-height:200px"></div>
                <div style="display:flex;justify-content:space-between;gap:8px;margin-top:12px">
                    <button type="button" class="vfr-btn" id="s-delete" style="color:#b91c1c">Delete</button>
                    <span style="display:flex;gap:8px">
                        <button type="button" class="vfr-btn" id="s-preview">Preview</button>
                        <button type="button" class="vfr-btn primary" id="s-save">Save source</button>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal on click of Application No. -->
<div class="vfr-modal" id="vfr-detail-modal">
    <div class="vfr-card">
        <h4>
            <span>VF eTRF Request &mdash; <span id="vfr-det-app" class="mono"></span></span>
            <button type="button" class="vfr-btn sm" data-close="vfr-detail-modal">Close</button>
        </h4>
        <div class="vfr-kv" id="vfr-det-kv"></div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
            <button type="button" class="vfr-btn" data-close="vfr-detail-modal">Close</button>
            <button type="button" class="vfr-btn primary" id="vfr-det-copy">Copy as New</button>
        </div>
    </div>
</div>

<!-- Copy as New + ERP Production Order Lookup + Fill Form + Submit Modal -->
<div class="vfr-modal" id="vfr-copy-modal">
    <div class="vfr-card">
        <h4>
            <span>Copy as New &mdash; VF Test Request Submission</span>
            <button type="button" class="vfr-btn sm" data-close="vfr-copy-modal">Close</button>
        </h4>
        <div class="vfr-erp-box">
            <div style="font-size:12px;font-weight:700;color:#1e3a8a;margin-bottom:6px">1. Link to <code>dberp.production_orders</code> (Choose MTL# or Prod Code)</div>
            <div class="vfr-grid" style="margin-top:0">
                <div>
                    <label for="c-mtl">MTL# (typable dropdown)</label>
                    <input type="text" id="c-mtl" list="c-mtl-list" placeholder="Type e.g. VN000CTG6MZ" autocomplete="off">
                    <datalist id="c-mtl-list"></datalist>
                </div>
                <div>
                    <label for="c-prod">Prod Code (text or pick when MTL# has multiple)</label>
                    <input type="text" id="c-prod" list="c-prod-list" placeholder="Type e.g. VSP26100172-CP" autocomplete="off">
                    <datalist id="c-prod-list"></datalist>
                </div>
                <div id="c-prod-pick-wrap" style="display:none">
                    <label for="c-prod-pick">Multiple Prod Codes found for this MTL# &mdash; Pick one:</label>
                    <select id="c-prod-pick"></select>
                </div>
                <div id="c-mtl-pick-wrap" style="display:none">
                    <label for="c-mtl-pick">Related MTL# for Prod Code:</label>
                    <select id="c-mtl-pick"></select>
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:10px">
                <span id="c-erp-hint" class="muted" style="font-size:12px">Select or type MTL# / Prod Code, then click Fill Form.</span>
                <button type="button" class="vfr-btn primary" id="c-fill-btn">Fill Form</button>
            </div>
        </div>

        <input type="hidden" id="c-req-id" value="0">
        <div class="vfr-erp-box" style="background:#f0fdf4;border-color:#86efac">
            <label for="c-past" style="display:block;font-size:12px;font-weight:700;color:#166534;margin-bottom:5px">2. Copy from a past Application of this Test Package <span class="muted" style="font-weight:400" id="c-past-hint"></span></label>
            <select id="c-past" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:7px;padding:6px 8px;font:inherit;font-size:13px"><option value="">Loading past Applications...</option></select>
        </div>
        <div class="vfr-grid">
            <div>
                <label for="c-src-app">Source Application No. (to Copy as New)</label>
                <input type="text" id="c-src-app" placeholder="Auto or e.g. A005941921">
            </div>
            <div>
                <label for="c-pkg">Test Package</label>
                <select id="c-pkg">
                    <?php foreach ($packages as $pkg): ?>
                        <option value="<?= esc($pkg, 'attr') ?>"><?= esc($pkg) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div><label for="c-style-no">Style No.</label><input type="text" id="c-style-no"></div>
            <div><label for="c-prod-name">Product Name</label><input type="text" id="c-prod-name"></div>
            <div><label for="c-style-color">Style Color No. (MTL#)</label><input type="text" id="c-style-color"></div>
            <div><label for="c-color-desc">Color Description</label><input type="text" id="c-color-desc"></div>
            <div><label for="c-color-code">Color Code</label><input type="text" id="c-color-code"></div>
            <div><label for="c-season">Season</label><input type="text" id="c-season"></div>
            <div><label for="c-size-cat">Size Category</label><input type="text" id="c-size-cat" value="UNISEX"></div>
            <div><label for="c-age">Age Group</label><input type="text" id="c-age" value="Children (3-12 years)"></div>
            <div><label for="c-crd">Cargo Receive Date (e.g. 22 Jan 2027)</label><input type="text" id="c-crd"></div>
            <div><label for="c-po">PO# (Reference)</label><input type="text" id="c-po" readonly></div>
        </div>
        <div style="margin-top:8px">
            <label for="c-remarks" style="display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:3px">Remarks</label>
            <input type="text" id="c-remarks" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:7px;padding:6px 8px;font:inherit;font-size:13px">
        </div>
        <div id="c-guard-banner" style="display:none;margin-top:10px;padding:8px 10px;border-radius:8px;background:#fef3c7;border:1px solid #f59e0b;color:#92400e;font-size:12px"></div>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:12px">
            <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:12px;color:#475569">
                <label id="c-allow-unmatch-wrap" style="display:none;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" id="c-allow-unmatch"> Allow Unmatched ERP</label>
                <label id="c-allow-retest-wrap" style="display:none;align-items:center;gap:4px;cursor:pointer"><input type="checkbox" id="c-allow-retest"> Allow Retest (New App No.)</label>
            </div>
            <div style="display:flex;gap:8px">
                <button type="button" class="vfr-btn" data-close="vfr-copy-modal">Cancel</button>
                <button type="button" class="vfr-btn primary" id="c-submit-btn">Submit to InterLink</button>
            </div>
        </div>
    </div>
</div>

<!-- Paste / Import Email Table Modal -->
<div class="vfr-modal" id="vfr-paste-modal">
    <div class="vfr-card">
        <h4>
            <span>Import / Paste Test Request Email Table</span>
            <button type="button" class="vfr-btn sm" data-close="vfr-paste-modal">Close</button>
        </h4>
        <div class="vfr-grid" style="margin-top:0">
            <div><label for="p-source">E-mail source (how the table is read)</label><select id="p-source"><option value="">Automatic (by sender below)</option></select></div>
            <div><label for="p-sender">Sender e-mail (optional)</label><input type="text" id="p-sender" placeholder="name@company.com"></div>
            <div><label for="p-subj">Email Subject</label><input type="text" id="p-subj" value="RE: DTP 的 CPSIA测试"></div>
            <div>
                <label for="p-pkg">Test Package (auto if blank)</label>
                <select id="p-pkg">
                    <option value="">Auto-detect from subject / table</option>
                    <?php foreach ($packages as $pkg): ?>
                        <option value="<?= esc($pkg, 'attr') ?>"><?= esc($pkg) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div style="margin-top:10px">
            <label for="p-html" style="display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:3px">Paste HTML Table or Copy-Pasted Email Table Rows:</label>
            <textarea id="p-html" rows="8" style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:8px;font:inherit;font-size:12.5px" placeholder="Paste the e-mail table here (HTML or copied table rows)..."></textarea>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
            <button type="button" class="vfr-btn" data-close="vfr-paste-modal">Cancel</button>
            <button type="button" class="vfr-btn primary" id="p-import-btn">Import Rows</button>
        </div>
    </div>
</div>
