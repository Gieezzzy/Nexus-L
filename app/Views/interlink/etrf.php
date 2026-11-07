<?php
/**
 * InterLink > VF eTRF: create a request (or a test) in InterLink from the five VF templates. The background service opens InterLink Home,
 * Submit a Request, picks the template, fills it, and saves a draft (or submits when asked). Data: $templates [{code,label,sample_type,package,individual}]
 */
?>
<style>
.et{padding:16px;margin-top:6px}
.et h3{margin:0}
.et-sub{margin:2px 0 0;color:var(--muted)}
.et-status{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:12px 0;padding:9px 12px;border:1px solid var(--line);border-radius:10px;background:#f8fafc;font-size:13px}
.et-dot{width:10px;height:10px;border-radius:50%;background:#94a3b8;flex:none}
.et-dot.ok{background:#16a34a}.et-dot.off{background:#dc2626}.et-dot.warn{background:#d97706}
.et-step{margin-top:16px}
.et-step>h4{margin:0 0 8px;font-size:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.et-step>h4 i{font-style:normal;display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:var(--brand,#2563eb);color:#fff;font-size:12px}
.et-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}
.et-card{border:2px solid var(--line);border-radius:12px;padding:11px 12px;background:#fff;cursor:pointer;text-align:left;font:inherit;color:var(--ink);transition:border-color .12s,box-shadow .12s,background-color .12s}
.et-card:hover{border-color:#93c5fd}
.et-card.on{border-color:var(--brand,#2563eb);background:#eff6ff;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.et-card b{display:block;font-size:13.5px}
.et-card small{display:block;color:#64748b;margin-top:4px;font-size:11.5px;line-height:1.35}
.et-tag{display:inline-block;margin-top:6px;padding:1px 8px;border-radius:99px;background:#f1f5f9;border:1px solid #cbd5e1;font-size:11px;color:#475569}
.et-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.et input[type=text],.et input[type=date],.et input[type=email],.et select,.et textarea{border:1px solid var(--line);border-radius:7px;padding:6px 8px;font:inherit;font-size:13px;background:#fff;color:var(--ink);width:100%;box-sizing:border-box}
.et textarea{resize:vertical;min-height:48px;line-height:1.38}
.et input.bad,.et select.bad,.et textarea.bad{border-color:#dc2626;background:#fef2f2}
.et-list{max-height:220px;overflow:auto;border:1px solid var(--line);border-radius:8px;margin-top:8px}
.et table{border-collapse:collapse;width:100%;font-size:12.5px}
.et th,.et td{border-bottom:1px solid #e5eaf2;padding:5px 7px;text-align:left;vertical-align:top}
.et th{position:sticky;top:0;background:#f1f5f9;color:#475569;font-weight:600;z-index:1}
.et tr.pick{cursor:pointer}.et tr.pick:hover{background:#f0f9ff}.et tr.pick.on{background:#dbeafe}
.et tr.pick.match{background:#fefce8}.et tr.pick.match:hover{background:#fef08a}
.et-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:10px}
.et-grid label,.et-sec label{display:block;font-size:11px;font-weight:600;color:#475569;margin-bottom:3px}
.et-sec{margin-top:12px;padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:#fff}
.et-sec>h5{margin:0 0 10px;padding-bottom:6px;border-bottom:2px solid #eab308;font-size:12.5px;text-transform:uppercase;letter-spacing:.04em;color:#0f172a;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.et-subsec{margin-top:12px;padding-top:10px;border-top:1px dashed #cbd5e1}
.et-subsec-title{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#475569;margin:0 0 8px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px}
.et-rows td input,.et-rows td textarea,.et-rows td select{min-width:115px}
.et-rows td textarea{min-height:42px;min-width:170px}
.et-row-letter{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:6px;background:#fef9c3;border:1px solid #facc15;font-weight:700;font-size:12px;color:#854d0e}
.et-btn{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:8px;padding:6px 12px;font:inherit;font-size:12.5px;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.et-btn:hover{border-color:#93c5fd;background:#f0f9ff}
.et-btn.sm{padding:3px 8px;font-size:11.5px;border-radius:6px}
.et-btn.xs{padding:2px 6px;font-size:11px;border-radius:5px;background:#f8fafc}
.et-btn.primary{background:var(--brand,#2563eb);border-color:var(--brand,#2563eb);color:#fff}
.et-btn.primary:hover{filter:brightness(1.06)}
.et-btn.warn{background:#fff7ed;border-color:#fdba74;color:#9a3412}
.et-btn:disabled{opacity:.5;cursor:not-allowed}
.et-check{margin-top:12px;padding:10px 12px;border-radius:10px;border:1px solid var(--line);font-size:13px}
.et-check.ok{background:#f0fdf4;border-color:#86efac;color:#14532d}
.et-check.no{background:#fef2f2;border-color:#fca5a5;color:#7f1d1d}
.et-check ul{margin:6px 0 0 18px;padding:0}
.et-chip{display:inline-block;padding:1px 8px;border-radius:99px;font-size:11.5px;font-weight:600;background:#e2e8f0;color:#334155}
.et-chip.done,.et-chip.submitted{background:#dcfce7;color:#166534}.et-chip.failed{background:#fee2e2;color:#991b1b}.et-chip.running,.et-chip.queued{background:#dbeafe;color:#1e40af}
.et-chip.match{background:#fef08a;color:#854d0e;border:1px solid #fde047}
.et-fixed{font-size:12px;color:#64748b}
.et-dsr-box{margin-top:10px;padding:11px 12px;border:1px solid #bfdbfe;border-radius:10px;background:#f8fbff}
.et-lkbar{display:flex;flex-wrap:wrap;gap:4px;margin-top:14px;border-bottom:2px solid #dbeafe}
.et-lkbar button{border:0;background:transparent;padding:8px 14px;font:inherit;font-size:12.5px;font-weight:600;color:#475569;cursor:pointer;border-radius:8px 8px 0 0;margin-bottom:-2px;border-bottom:2px solid transparent}
.et-lkbar button:hover{background:#eff6ff}
.et-lkbar button.on{color:#1e3a8a;background:#f8fbff;border-bottom-color:#1d4ed8}
.et-lkbar .et-tb{display:inline-block;min-width:18px;padding:0 6px;margin-left:5px;border-radius:9px;background:#1d4ed8;color:#fff;font-size:11px;line-height:18px;text-align:center}
.et-lkbar .et-tb:empty{display:none}
.et-lk{display:none;margin-top:0;border-top-left-radius:0}
.et-lk.on{display:block}
.et-dsr-hdr{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;padding:7px 10px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;margin-top:8px;font-size:12.5px}
.et-CheckPills{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:5px}
.et-CheckPill{display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;font-size:11px;font-weight:500;color:#334155;cursor:pointer;user-select:none}
.et-CheckPill:hover{border-color:#93c5fd;background:#eff6ff}
.et-CheckPill input{margin:0;width:auto}
.et-quick-chips{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px}
@media (prefers-reduced-motion:reduce){.et-card{transition:none}}
</style>

<div class="card glass et" id="et-root">
    <div>
        <h3>VF eTRF &mdash; create a request or a test in InterLink</h3>
        <p class="et-sub sm">Pick a template, pick the Lab Reference (and/or look up the MTL# in the DSR / CFM share), review or edit all InterLink form sections, then save a draft or submit. When InterLink generates the Application No., NEXUS-L automatically saves it back to the test report.</p>
    </div>

    <div class="et-status" id="et-status" style="justify-content:space-between">
        <div class="et-row" style="gap:8px">
            <span class="et-dot" id="et-dot"></span>
            <span id="et-status-text">Checking the InterLink background service...</span>
        </div>
        <div class="et-row" id="et-accounts-bar" style="gap:8px">
            <span class="muted sm">Nexus Interlink Service Accounts:</span>
            <span class="et-tag" style="display:inline-flex;align-items:center;gap:6px;margin:0;padding:3px 9px" title="Sign in inside the Nexus Interlink Service desktop tray app — used for VF eTRF Material, Product, Whole Shoe &amp; Bonding">
                <span class="et-dot" id="et-dot-labasst"></span>
                <b>dtp_qcasst@dtshoes.com</b>
                <span class="muted sm">(Mat &middot; Prod &middot; Whole Shoe &middot; Bonding)</span>
            </span>
            <span class="et-tag" style="display:inline-flex;align-items:center;gap:6px;margin:0;padding:3px 9px" title="Sign in inside the Nexus Interlink Service desktop tray app — used for VF eTRF PULLTESTTemplate">
                <span class="et-dot" id="et-dot-lab"></span>
                <b>dtp_lab@dtshoes.com</b>
                <span class="muted sm">(PULLTESTTemplate)</span>
            </span>
        </div>
    </div>

    <div class="et-step">
        <h4><i>1</i> Template (InterLink Home &rarr; Submit a Request)</h4>
        <div class="et-cards" id="et-cards">
            <?php foreach ($templates as $t): ?>
            <button type="button" class="et-card" data-code="<?= esc($t['code'], 'attr') ?>" data-mat="<?= $t['sample_type'] === 'Material' ? '1' : '0' ?>" data-account="<?= esc($t['account'] ?? 'dtp_qcasst@dtshoes.com', 'attr') ?>">
                <b><?= esc($t['label']) ?></b>
                <small><?= $t['sample_type'] === 'Material' ? 'A material or component test (fabric, leather, rubber, foxing tape, hardware...).' : (str_contains($t['label'], 'bonding') ? 'Shoe bonding tests (sole to upper).' : (str_contains($t['label'], 'WHOLE') ? 'Whole-shoe tests: heat aging, hydrolysis, flexing, bonding.' : (str_contains($t['label'], 'PULL') ? 'Pull-strength test package for a finished product.' : 'A finished product (style) test request.'))) ?></small>
                <span class="et-tag"><?= esc($t['sample_type']) ?></span><?php if ($t['package'] !== ''): ?> <span class="et-tag"><?= esc($t['package']) ?></span><?php endif ?>
                <span class="et-tag" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af"><?= esc($t['account'] ?? 'dtp_qcasst@dtshoes.com') ?></span>
            </button>
            <?php endforeach ?>
        </div>
    </div>

    <div class="et-step">
        <h4><i>2</i> Lab Reference &amp; lookups <small class="muted" style="font-weight:400">(pick a Lab Ref, then check season, supplier and BOM details in the tabs below)</small></h4>
        <div class="et-row">
            <input type="text" id="et-q" placeholder="Search Lab Ref / TR No. / MTL# / PO or ProdCode / material / style / supplier" style="flex:1;min-width:240px;width:auto">
            <select id="et-cat" style="width:auto"><option value="all">All types</option></select>
            <button type="button" class="et-btn" id="et-clear-ref">No Lab Reference</button>
            <span class="muted sm" id="et-ref-note"></span>
        </div>
        <div class="et-list"><table><thead><tr><th>Lab Ref</th><th>Type</th><th>MTL#</th><th>PO / Lot No.</th><th>Material / Style</th><th>Supplier</th><th>Result</th></tr></thead><tbody id="et-labrefs"><tr><td colspan="7" class="muted">Loading...</td></tr></tbody></table></div>

        <div class="et-lkbar" id="et-lkbar" role="tablist">
            <button type="button" class="on" data-lk="et-os-panel" role="tab">Order Summary <span class="et-tb" id="et-tb-os"></span></button>
            <button type="button" data-lk="et-lov-panel" role="tab">InterLink pick lists <span class="et-tb" id="et-tb-lov"></span></button>
            <button type="button" data-lk="et-dsr-panel" role="tab">DSR / CFM files <span class="et-tb" id="et-tb-dsr"></span></button>
        </div>

        <!-- Step 2A: Order Summary PDFs (season ticks and style details per MTL#) -->
        <div class="et-dsr-box et-lk on" id="et-os-panel">
            <div class="et-row" style="justify-content:space-between">
                <div>
                    <b style="font-size:13px;color:#1e3a8a">Order Summary (<code>\\192.168.32.130\qc lab files\#ORDER SUMMARY</code>)</b>
                    <span class="muted sm" style="margin-left:6px">Season (F26 / H26 / S27) and style details for the MTL#, read from the PDFs in that folder. New or changed PDFs are picked up automatically.</span>
                </div>
                <span class="muted sm" id="et-os-files"></span>
            </div>
            <div class="et-row" style="margin-top:8px">
                <input type="text" id="et-os-q" placeholder="MTL# (e.g. VN000CQABOP)" style="flex:1;min-width:200px;width:auto">
                <button type="button" class="et-btn primary" id="et-os-btn">Look up season</button>
                <span class="muted sm" id="et-os-note"></span>
            </div>
            <div id="et-os-res" style="margin-top:8px"></div>
        </div>

        <!-- InterLink pick lists (Supplier / Facility, Season, Country, Fiber Content, End Use, Brand ... read from InterLink itself) -->
        <div class="et-dsr-box et-lk" id="et-lov-panel">
            <div class="et-row" style="justify-content:space-between">
                <div>
                    <b style="font-size:13px;color:#1e3a8a">InterLink pick lists</b>
                    <span class="muted sm" style="margin-left:6px">The values InterLink's own search buttons offer, read from InterLink for each template. Search a supplier / facility here and apply it to the form.</span>
                </div>
                <span><span class="muted sm" id="et-lov-state"></span> <button type="button" class="et-btn sm" id="et-lov-sync">Read pick lists from InterLink</button></span>
            </div>
            <div class="et-row" style="margin-top:8px">
                <input type="text" id="et-lov-q" placeholder="Supplier or facility name / ID (e.g. STAHL, G00546, ADVANCE DENIM)" style="flex:1;min-width:240px;width:auto">
                <button type="button" class="et-btn primary" id="et-lov-btn">Search</button>
                <span class="muted sm" id="et-lov-note"></span>
            </div>
            <div id="et-lov-res" style="margin-top:8px"></div>
        </div>

        <!-- Step 2B: DSR / CFM Share Lookup (\\192.168.0.8\团队文件-確認dsr) -->
        <div class="et-dsr-box et-lk" id="et-dsr-panel">
            <div class="et-row" style="justify-content:space-between">
                <div>
                    <b style="font-size:13px;color:#1e3a8a">DSR / CFM Excel Lookup (<code>\\192.168.0.8\团队文件-確認dsr</code>)</b>
                    <span class="muted sm" style="margin-left:6px">Search by <b>MTL#</b> (e.g. <code>VN000ZB8BKO</code>) or <b>ProductionCode / PO No.</b> (e.g. <code>640427A</code>) to pull VF Material Code, Color Code, Season &amp; Style info.</span>
                </div>
                <label style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:#334155;cursor:pointer;margin:0">
                    <input type="checkbox" id="et-dsr-keep-desc" checked> Keep Lab Ref Material Description when picking a component
                </label>
            </div>
            <div class="et-row" style="margin-top:8px">
                <input type="text" id="et-dsr-q" placeholder="Enter MTL# (e.g. VN000ZB8BKO) or Production Code / PO No. (e.g. 640427A)" style="flex:1;min-width:240px;width:auto">
                <button type="button" class="et-btn primary" id="et-dsr-btn">Search DSR</button>
                <span class="muted sm" id="et-dsr-note"></span>
            </div>

            <div id="et-dsr-files-wrap" style="display:none;margin-top:8px">
                <div class="muted sm" style="margin-bottom:4px">Matching CFM / DSR Excel files (click a file to view its BOM components):</div>
                <div class="et-list" style="max-height:160px;background:#fff">
                    <table>
                        <thead><tr><th>CFM / DSR File Name</th><th>Folder / Season</th><th>MTL#</th><th>Style No.</th><th>Style Name</th><th>Colorway</th></tr></thead>
                        <tbody id="et-dsr-files"></tbody>
                    </table>
                </div>
            </div>

            <div id="et-dsr-comp-wrap" style="display:none;margin-top:8px">
                <div class="et-dsr-hdr" id="et-dsr-hdr-bar">
                    <div id="et-dsr-hdr-text"></div>
                    <div class="et-row" style="gap:6px">
                        <button type="button" class="et-btn sm primary" id="et-dsr-apply-hdr">Apply Style / Season Header to Form</button>
                    </div>
                </div>
                <div class="et-row" style="margin-top:8px">
                    <input type="text" id="et-dsr-comp-filter" placeholder="Filter BOM components (e.g. foxing, RF-65A-FSC, 112646, upper, lining, outsole...)" style="flex:1;min-width:220px;width:auto">
                    <span class="muted sm" id="et-dsr-comp-count"></span>
                </div>
                <div class="et-list" style="max-height:250px;background:#fff">
                    <table>
                        <thead>
                            <tr>
                                <th>Sec#</th>
                                <th>Component / Location</th>
                                <th>VF Material Code</th>
                                <th>Supplier Code</th>
                                <th>Material Spec / Description</th>
                                <th>Color Code</th>
                                <th>Color Description</th>
                                <th>Supplier</th>
                                <th>Size</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="et-dsr-comps"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="et-step">
        <h4><i>3</i> The request (InterLink 2.0 eTRF Form) <span class="et-tag" id="et-badge">choose a template</span></h4>

        <!-- SECTION 1: APPLICANT INFORMATION -->
        <div class="et-sec">
            <h5>
                <span>1. Applicant Information</span>
                <span class="muted sm" style="text-transform:none;letter-spacing:0;font-weight:400">Matches InterLink Applicant Information header</span>
            </h5>
            <div class="et-grid">
                <div><label>Company Name *</label><input type="text" data-f="company_name" placeholder="KEY TRADE CO., LTD"></div>
                <div><label>Interlink Code *</label><input type="text" data-f="interlink_code" placeholder="E126863"></div>
                <div><label>Contact Person *</label><input type="text" data-f="contact_person" list="dl-contacts" placeholder="Danica Agbuya / Faye Ann Elaysa"></div>
                <div><label>Email *</label><input type="text" data-f="applicant_email" list="dl-emails" placeholder="dtp_qcasst@dtshoes.com"></div>
                <div style="grid-column:span 2"><label>Address * <small class="muted">(multi-line input area, max 150 chars)</small></label><textarea rows="2" maxlength="150" data-f="applicant_address" placeholder="Lot 1P7-A Subic Bay Gateway Park Phase II,, Subic Bay Freeport Zone, Philippines"></textarea></div>
                <div><label>Country *</label><input type="text" data-f="applicant_country" list="dl-countries" placeholder="Philippines"></div>
                <div><label>Postal Code</label><input type="text" data-f="postal_code" placeholder="2222"></div>
                <div><label>Telephone</label><input type="text" data-f="applicant_phone" placeholder="(047) 252-2899"></div>
                <div style="grid-column:span 3"><label>Email Notification Recipient(s) * <small class="muted">(separate multiple emails with <code>;</code>)</small></label><textarea rows="2" data-f="email_recipients" placeholder="dtp_qcasst@dtshoes.com"></textarea></div>
            </div>
        </div>

        <!-- SECTION 2: INVOICE INFORMATION -->
        <div class="et-sec">
            <h5>
                <span>2. Invoice Information</span>
                <span class="et-row" style="text-transform:none;letter-spacing:0;font-weight:500">
                    <label style="margin:0;display:inline-flex;align-items:center;gap:6px;font-size:12px">
                        <span>Invoice to Applicant? *</span>
                        <select data-f="invoice_to_applicant" id="et-inv-toggle" style="width:auto;padding:3px 8px">
                            <option value="Yes">Yes</option>
                            <option value="No">No (specify separate Invoice recipient)</option>
                        </select>
                    </label>
                </span>
            </h5>
            <div class="et-grid" id="et-inv-fields" style="display:none">
                <div><label>Invoice Company Name</label><input type="text" data-f="invoice_company_name"></div>
                <div><label>Invoice Contact Person</label><input type="text" data-f="invoice_contact_person"></div>
                <div><label>Invoice Email</label><input type="text" data-f="invoice_email"></div>
                <div><label>Invoice Telephone</label><input type="text" data-f="invoice_telephone"></div>
                <div style="grid-column:span 2"><label>Invoice Address <small class="muted">(input area, max 300 chars)</small></label><textarea rows="2" maxlength="300" data-f="invoice_address"></textarea></div>
                <div><label>Invoice Country</label><input type="text" data-f="invoice_country" list="dl-countries"></div>
                <div><label>Postal Code</label><input type="text" data-f="invoice_postal_code"></div>
            </div>
        </div>

        <!-- SECTION 3: SUPPLIER / VENDOR / FACTORY INFORMATION -->
        <div class="et-sec">
            <h5>
                <span id="et-sec3-title">3. Vendor / Factory / Supplier Information</span>
                <span class="et-row" style="text-transform:none;letter-spacing:0;font-weight:500">
                    <button type="button" class="et-btn sm" id="et-preset-own" title="Set Supplier &amp; Facility to OWN SOURCE SUPPLIER (G00000 / G00000000000F001)">Preset: OWN SOURCE SUPPLIER (G00000)</button>
                    <button type="button" class="et-btn sm" id="et-preset-key" title="Set Vendor to KEY TRADE (0000507450) &amp; Factory to DATIAN SUBIC (0000507452)">Preset: KEY TRADE / DATIAN SUBIC</button>
                </span>
            </h5>
            <div class="et-grid">
                <div><label id="et-l-vendor">Vendor Name *</label><input type="text" data-f="vendor_name" placeholder="KEY TRADE CO., LTD / OWN SOURCE SUPPLIER"></div>
                <div><label id="et-l-vendor-id">Vendor ID *</label><input type="text" data-f="vendor_id" placeholder="0000507450 / G00000"></div>
                <div><label id="et-l-factory">Factory Name *</label><input type="text" data-f="factory_name" placeholder="DATIAN SUBIC SHOES INC. / OWN SOURCE SUPPLIER"></div>
                <div><label id="et-l-factory-id">Factory ID *</label><input type="text" data-f="factory_id" placeholder="0000507452 / G00000000000F001"></div>
            </div>
        </div>

        <!-- SECTION 4: SERVICE REQUESTED -->
        <div class="et-sec">
            <h5><span>4. Service Requested</span></h5>
            <div class="et-grid">
                <div data-only="product" style="grid-column:1/-1">
                    <label>Product Export To * <small class="muted">(click regions to toggle or edit the input area directly; separated by <code>; </code>)</small></label>
                    <div class="et-CheckPills" id="et-export-pills">
                        <?php foreach (['APAC - China', 'APAC - Non China', 'Canada', 'CASA', 'EMEA', 'Mexico', 'N/A', 'USA'] as $reg): ?>
                        <label class="et-CheckPill"><input type="checkbox" value="<?= esc($reg, 'attr') ?>" class="et-exp-cb"> <?= esc($reg) ?></label>
                        <?php endforeach ?>
                        <button type="button" class="et-btn xs" id="et-exp-std">Set Standard (APAC-Non China; Canada; CASA; EMEA; Mexico)</button>
                        <button type="button" class="et-btn xs" id="et-exp-all">Select All</button>
                    </div>
                    <textarea rows="2" data-f="product_export_to" placeholder="APAC - Non China; Canada; CASA; EMEA; Mexico"></textarea>
                </div>

                <div><label>Service Test Type *</label><input type="text" data-f="service_test_type" list="dl-service-type" placeholder="In-House Lab Testing"></div>
                <div><label>Test Stage *</label><input type="text" data-f="test_stage" list="dl-test-stage" placeholder="Production"></div>
                <div><label>Service Provider *</label><input type="text" data-f="service_provider" list="dl-service-provider" placeholder="DATIAN SUBIC SHOES INC."></div>
                <div><label>Lab Location *</label><input type="text" data-f="lab_location" list="dl-lab-location" placeholder="PHILIPPINES"></div>
                <div>
                    <label>Service Required *</label>
                    <select data-f="service_required">
                        <option value="4">Regular</option>
                        <option value="3">Express</option>
                        <option value="2">Shuttle</option>
                        <option value="1">Emergency</option>
                    </select>
                </div>
                <div>
                    <label>Quotation required before testing? *</label>
                    <select data-f="quotation_required">
                        <option value="2">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div style="grid-column:span 2"><label>Lab Address * <small class="muted">(input area)</small></label><textarea rows="2" data-f="lab_address" placeholder="Lot 1P7-A Subic Bay Gateway Park Phase II,, Subic Bay Freeport Zone, Philippines"></textarea></div>
                <div>
                    <label>Is this a Retest? *</label>
                    <select data-f="is_retest">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div><label>Previous Test Report No</label><input type="text" data-f="previous_report_no" placeholder="Previous TR / Application No."></div>
                <div style="grid-column:span 2"><label>Any Improvements Taken <small class="muted">(input area, max 250 chars)</small></label><textarea rows="2" maxlength="250" data-f="improvements" placeholder="Describe improvements made if this is a retest"></textarea></div>
                <div>
                    <label>Return Sample? *</label>
                    <select data-f="return_sample">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div data-only="material">
                    <label>Any Related TRF Number?</label>
                    <select data-f="any_related_trf">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div data-only="material"><label>Related TRF Number</label><input type="text" data-f="related_trf_no" placeholder="Related TRF / Application No."></div>
            </div>
        </div>

        <!-- SECTION 5: MATERIAL INFORMATION (ALL) / PRODUCT INFORMATION (ALL) -->
        <div class="et-sec">
            <h5><span id="et-sec5-title">5. Product Information (All)</span></h5>

            <div class="et-grid" style="margin-bottom:10px">
                <div>
                    <label>Sample Type *</label>
                    <select data-f="sample_type">
                        <option value="Finished Product">Finished Product</option>
                        <option value="Material">Material</option>
                    </select>
                </div>
                <div>
                    <label>Product Category *</label>
                    <select data-f="product_category">
                        <option value="Footwear">Footwear</option>
                        <option value="Apparel">Apparel</option>
                        <option value="Equipment">Equipment</option>
                        <option value="Accessories">Accessories</option>
                    </select>
                </div>
                <div><label>Brand Name *</label><input type="text" data-f="buyer_brand" list="dl-brands" placeholder="Vans"></div>
            </div>

            <!-- JOB_DUMMY_009 Multi-row Table: Style Information / Material Information -->
            <div class="et-subsec" style="margin-top:6px">
                <div class="et-subsec-title">
                    <span id="et-rows-title">Style Information *</span>
                    <div class="et-row" style="gap:6px">
                        <button type="button" class="et-btn sm primary" id="et-add-row">+ Add Row</button>
                        <span class="muted sm" style="text-transform:none;letter-spacing:0;font-weight:400">Matches InterLink multi-row grid (rows A, B, C...)</span>
                    </div>
                </div>
                <div class="et-list" style="max-height:none;margin-top:4px">
                    <table class="et-rows">
                        <thead id="et-rows-head"></thead>
                        <tbody id="et-rows-body"></tbody>
                    </table>
                </div>
            </div>

            <!-- Material- & Product-Specific Details below JOB_DUMMY_009 -->
            <div class="et-subsec">
                <div class="et-subsec-title"><span>Specifications, Origin &amp; Care Details</span></div>
                <div class="et-grid">
                    <!-- Material-only fields -->
                    <div data-only="material"><label>Requirement Spec No.</label><input type="text" data-f="requirement_spec_no" placeholder="e.g. VF Spec No."></div>
                    <div data-only="material">
                        <label>Material Sub-Type <small class="muted">(input area)</small></label>
                        <textarea rows="2" data-f="material_sub_type" placeholder="Others / Woven / Knit / Nonwoven / Laminated Fabric"></textarea>
                        <div class="et-quick-chips">
                            <?php foreach (['Others', 'Woven', 'Nonwoven', 'Knit', 'Knit (Brush or Fleece)', 'Denim', 'Laminated Fabric'] as $ms): ?>
                            <button type="button" class="et-btn xs" data-pick-f="material_sub_type" data-pick-val="<?= esc($ms, 'attr') ?>"><?= esc($ms) ?></button>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div data-only="material">
                        <label>Season <small class="muted">(input area)</small></label>
                        <textarea rows="2" data-f="season" placeholder="e.g. S27"></textarea>
                        <div class="et-quick-chips">
                            <?php foreach (['S27', 'F26', 'S26', 'F27'] as $ss): ?>
                            <button type="button" class="et-btn xs" data-pick-f="season" data-pick-val="<?= esc($ss, 'attr') ?>"><?= esc($ss) ?></button>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div data-only="material"><label>Finishing</label><input type="text" data-f="finishing" list="dl-finishing" placeholder="e.g. Dyed, Coated, Printed"></div>
                    <div data-only="material"><label>End Use</label><input type="text" data-f="end_use" list="dl-end-use" placeholder="e.g. Foxing, Upper, Lining, Outsole"></div>

                    <!-- Product-only fields -->
                    <div data-only="product">
                        <label>Age Group * <small class="muted">(input area)</small></label>
                        <textarea rows="2" data-f="age_group" placeholder="Adult"></textarea>
                        <div class="et-quick-chips">
                            <?php foreach (['Adult', 'Children', 'Infant/Toddler', 'Adult; Children'] as $ag): ?>
                            <button type="button" class="et-btn xs" data-pick-f="age_group" data-pick-val="<?= esc($ag, 'attr') ?>"><?= esc($ag) ?></button>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div data-only="product"><label>Cargo Receive Date *</label><input type="text" data-f="cargo_receive_date" placeholder="e.g. 07 Nov 2026"></div>

                    <!-- Shared Sourcing Office & Country of Origin -->
                    <div><label>Sourcing Office *</label><input type="text" data-f="sourcing_office" list="dl-sourcing-office" placeholder="ASO"></div>
                    <div><label id="et-l-coo">Country of Origin *</label><input type="text" data-f="country_of_origin" list="dl-countries" placeholder="CHINA or Philippines"></div>

                    <!-- Additional Material physical construction specs -->
                    <div data-only="material"><label>Cuttable Width</label><input type="text" data-f="cuttable_width" placeholder="e.g. 58&quot;"></div>
                    <div data-only="material"><label>Warp per inch OR wales per inch</label><input type="text" data-f="warp_per_inch"></div>
                    <div data-only="material"><label>Weft per inch OR Course per inch</label><input type="text" data-f="weft_per_inch"></div>
                    <div data-only="material"><label>Trim Size</label><input type="text" data-f="trim_size"></div>
                    <div data-only="material" style="grid-column:span 2"><label>Yarn Specifications <small class="muted">(input area)</small></label><textarea rows="2" data-f="yarn_specifications" placeholder="Yarn count / denier / construction"></textarea></div>

                    <!-- Product Claim Type & Care Symbols -->
                    <div data-only="product" style="grid-column:span 2">
                        <label>Claim Type <small class="muted">(input area)</small></label>
                        <textarea rows="2" data-f="product_claim_type" placeholder="Optional product claim type (e.g. Waterproof, DWR, Anti Odor)"></textarea>
                        <div class="et-quick-chips">
                            <?php foreach (['Durable Water Repellent (DWR)', 'Waterproof', 'Breathability', 'Anti Odor / Anti Bacterial', 'Windproof'] as $ct): ?>
                            <button type="button" class="et-btn xs" data-pick-f="product_claim_type" data-pick-val="<?= esc($ct, 'attr') ?>"><?= esc($ct) ?></button>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div data-only="product"><label>Care Symbol (Care Label 1 / 2 / 3)</label><input type="text" data-f="care_label_1" placeholder="Optional care label code"></div>

                    <!-- Shared Washing / Care Instruction textarea -->
                    <div style="grid-column:span 2">
                        <label id="et-l-care">Care / Washing Instruction <small class="muted">(input area, max 500 chars)</small></label>
                        <textarea rows="2" maxlength="500" data-f="washing_instruction" placeholder="Optional washing or care instruction"></textarea>
                    </div>
                </div>
            </div>

            <!-- JOB_DUMMY_006: Fiber Composition Sub-Table -->
            <div class="et-subsec">
                <div class="et-subsec-title">
                    <span>Fiber Composition <small class="muted" style="text-transform:none;font-weight:400">(optional InterLink Fiber Content table)</small></span>
                    <div class="et-row" style="gap:6px">
                        <button type="button" class="et-btn sm" id="et-add-fiber">+ Add Fiber Content</button>
                    </div>
                </div>
                <div class="et-list" style="max-height:none;margin-top:4px">
                    <table class="et-rows">
                        <thead>
                            <tr>
                                <th style="width:42px">#</th>
                                <th id="et-fiber-part-th">Material / Product Part</th>
                                <th>Fiber Content</th>
                                <th style="width:130px">%</th>
                                <th style="width:46px"></th>
                            </tr>
                        </thead>
                        <tbody id="et-fibers-body"></tbody>
                    </table>
                </div>
                <div style="margin-top:8px">
                    <label>Fiber Content Summary Text <small class="muted">(optional summary field <code>TXT_FIBER_CONTENT</code>)</small></label>
                    <input type="text" data-f="fiber_content_text" placeholder="e.g. Upper: 100% Cotton; Outsole: 100% Rubber">
                </div>
            </div>
        </div>

        <!-- SECTION 6: TEST REQUIREMENTS -->
        <div class="et-sec">
            <h5><span>6. Test Requirements</span></h5>
            <div class="et-grid">
                <div><label>GB Standard Code</label><input type="text" data-f="gb_standard_code" placeholder="Optional GB Standard Code"></div>
                <div><label>GB Safety Code</label><input type="text" data-f="gb_safety_code" placeholder="Optional GB Safety Code"></div>
                <div><label>Other GB - Hyper-care Package</label><input type="text" data-f="other_gb_hypercare"></div>
                <div><label>Other GB - VOC Package</label><input type="text" data-f="other_gb_voc"></div>

                <div style="grid-column:span 2">
                    <label>Test Package(s) * <small class="muted">(input area; click a quick-pick chip or type directly)</small></label>
                    <textarea rows="2" data-f="test_packages" placeholder="e.g. VANS Bonding Strength / VANS Foxing Tape - Rubber / *Select Individual Test Item"></textarea>
                    <div class="et-quick-chips">
                        <?php foreach ([
                            'VANS Bonding Strength',
                            'VANS Whole Shoe Performance',
                            'VANS Pull Strength',
                            'VANS Foxing Tape - Rubber',
                            'VANS Outsole - Rubber',
                            'Carton Box (#ODUSACB)',
                            '*Select Individual Test Item',
                            'CPSIA Product Testing package',
                            'ChemST general testing package',
                        ] as $tp): ?>
                        <button type="button" class="et-btn xs" data-pick-f="test_packages" data-pick-val="<?= esc($tp, 'attr') ?>"><?= esc($tp) ?></button>
                        <?php endforeach ?>
                    </div>
                </div>

                <div style="grid-column:span 2">
                    <label>Individual Tests <small class="muted">(input area)</small></label>
                    <textarea rows="2" data-f="individual_tests" placeholder="Individual test items (when *Select Individual Test Item or supplementary tests are needed)"></textarea>
                    <div class="et-quick-chips">
                        <?php foreach ([
                            '11.1 Bonding Strength (Sole to Upper)',
                            '11.2 Heat Aging & Bonding',
                            '11.3 Hydrolysis & Bonding',
                            '11.4 Whole Shoe Flexing',
                            'Pull Test - Small Parts / Eyelets',
                            'Blooming Test (SATRA TM361)',
                        ] as $it): ?>
                        <button type="button" class="et-btn xs" data-pick-f="individual_tests" data-pick-append="1" data-pick-val="<?= esc($it, 'attr') ?>">+ <?= esc($it) ?></button>
                        <?php endforeach ?>
                    </div>
                </div>

                <div style="grid-column:1/-1">
                    <label>Remarks <small class="muted">(input area, max 950 chars)</small></label>
                    <textarea rows="3" maxlength="950" data-f="lab_remarks" placeholder="Lab Ref No., PO/Lot No., special testing instructions, or remarks for InterLink"></textarea>
                </div>
            </div>
        </div>

        <!-- SECTION 7: ADDITIONAL DOCUMENTATION & CPSC CERTIFICATE OF COMPLIANCE -->
        <div class="et-sec">
            <h5><span>7. Additional Documentation &amp; CPSC Certificate of Compliance</span></h5>
            <div class="et-grid">
                <div style="grid-column:1/-1">
                    <label>Additional Documentation / Supplementary Attachment Notes <small class="muted">(input area)</small></label>
                    <textarea rows="2" data-f="supplementary_attachment_notes" placeholder="Optional notes regarding supplementary attachments or reference documents"></textarea>
                </div>

                <div data-only="product">
                    <label>GCC/CPC Required?</label>
                    <select data-f="gcc_cpc_required">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div data-only="product"><label>Product ID</label><input type="text" data-f="product_id" placeholder="Auto-filled from Style Color No."></div>
                <div data-only="product"><label>Manufacture Date (MM/YYYY)</label><input type="text" data-f="manufacture_date" placeholder="__/____"></div>
                <div data-only="product"><label>Factory Apt/Suite Number</label><input type="text" data-f="factory_apt_suite"></div>
                <div data-only="product" style="grid-column:span 2"><label>Factory Address <small class="muted">(input area, max 150 chars)</small></label><textarea rows="2" maxlength="150" data-f="factory_address" placeholder="Lot 1P7-A,Phase 2,Subic Bay Gateway, Park, Subic Bay Freeport Zone"></textarea></div>
                <div data-only="product"><label>Factory City</label><input type="text" data-f="factory_city" placeholder="Subic Bay"></div>
                <div data-only="product"><label>Factory State/Province</label><input type="text" data-f="factory_state_province" placeholder="03"></div>
                <div data-only="product"><label>Factory Zip/Postal Code</label><input type="text" data-f="factory_zip_code"></div>
                <div data-only="product"><label>Factory Country</label><input type="text" data-f="factory_country" list="dl-countries" placeholder="Philippines"></div>
                <div data-only="product"><label>Factory Email</label><input type="text" data-f="factory_email"></div>
                <div data-only="product"><label>Factory Phone</label><input type="text" data-f="factory_phone"></div>
            </div>
            <p class="et-fixed" style="margin:8px 0 0">Default company, address, cost centre (<code>VF-P0676</code>) and division codes are pre-loaded from <b>Settings &rarr; InterLink report defaults</b> and can be edited directly above when needed.</p>
        </div>
    </div>

    <div class="et-step">
        <h4><i>4</i> Check and send</h4>
        <div id="et-thankyou-banner" style="display:none;margin-bottom:10px"></div>
        <div class="et-check" id="et-check">Choose a template to begin.</div>
        <div class="et-row" style="margin-top:10px">
            <button type="button" class="et-btn primary" id="et-save" disabled>Save as draft in InterLink</button>
            <button type="button" class="et-btn warn" id="et-submit" disabled>Submit to InterLink (Print &amp; Submit)</button>
            <span class="muted sm" id="et-msg"></span>
        </div>
        <p class="muted sm" style="margin:8px 0 0">A <b>draft</b> is saved in your InterLink account so you can look at it first. <b>Submit</b> checks the InterLink confirmation checkbox, clicks Next, and notifies you in Chat &amp; Toast with the generated <b>Application No.</b></p>
    </div>

    <div class="et-step">
        <h4>Requests sent from NEXUS-L</h4>
        <div class="et-list" style="max-height:260px"><table><thead><tr><th>#</th><th>Template</th><th>Lab Ref</th><th>Style / material</th><th>Mode</th><th>Application No.</th><th>Status</th><th>By</th><th>When</th></tr></thead><tbody id="et-history"><tr><td colspan="9" class="muted">Loading...</td></tr></tbody></table></div>
    </div>

    <!-- Datalists for InterLink 2.0 standard options -->
    <datalist id="dl-contacts">
        <option value="Danica Agbuya">
        <option value="Faye Ann Elaysa">
    </datalist>
    <datalist id="dl-emails">
        <option value="dtp_qcasst@dtshoes.com">
        <option value="dtp_lab@dtshoes.com">
        <option value="dtp_qcasst@dtshoes.com">
    </datalist>
    <datalist id="dl-countries">
        <option value="Philippines">
        <option value="CHINA">
        <option value="PHILIPPINES">
        <option value="VIETNAM">
        <option value="TAIWAN">
        <option value="KOREA">
        <option value="INDONESIA">
        <option value="CAMBODIA">
        <option value="THAILAND">
        <option value="INDIA">
        <option value="ITALY">
        <option value="USA">
    </datalist>
    <datalist id="dl-export-to">
        <option value="APAC - Non China">
        <option value="APAC - Non China; Canada; CASA; EMEA; Mexico">
        <option value="APAC - China; APAC - Non China; Canada; CASA; EMEA; Mexico; N/A; USA">
        <option value="EMEA">
        <option value="USA">
    </datalist>
    <datalist id="dl-seasons">
        <option value="S27">
        <option value="F26">
        <option value="S26">
        <option value="F27">
    </datalist>
    <datalist id="dl-size-cats">
        <option value="UA">
        <option value="UNISEX">
        <option value="MALE">
        <option value="FEMALE">
        <option value="KIDS">
        <option value="TODDLER">
    </datalist>
    <datalist id="dl-mat-types">
        <option value="Bottom Components (Outsole / Midsole / Footbed / Foxing Tape)">
        <option value="Fabric">
        <option value="Leather">
        <option value="Synthetic Leather / PU / TPU">
        <option value="Foam / EVA / Sponge">
        <option value="Laces / Webbing / Tapes / Thread">
        <option value="Hardware &amp; trims">
        <option value="Labels / Heat Transfers / Prints">
        <option value="Packaging">
    </datalist>
    <datalist id="dl-mat-subtype">
        <option value="Others">
        <option value="Woven">
        <option value="Nonwoven">
        <option value="Knit">
        <option value="Knit (Brush or Fleece)">
        <option value="Denim">
        <option value="Laminated Fabric">
    </datalist>
    <datalist id="dl-weight-uom">
        <option value="g/m2">
        <option value="oz/yd2">
        <option value="g/pc">
        <option value="mm">
    </datalist>
    <datalist id="dl-finishing">
        <option value="Dyed">
        <option value="Printed">
        <option value="Coated">
        <option value="DWR">
        <option value="Vulcanized">
        <option value="Unfinished">
    </datalist>
    <datalist id="dl-end-use">
        <option value="Foxing">
        <option value="Outsole">
        <option value="Upper">
        <option value="Lining">
        <option value="Insole / Footbed">
        <option value="Shoelace">
        <option value="Binding / Webbing">
        <option value="Packaging / Carton Box">
    </datalist>
    <datalist id="dl-sourcing-office">
        <option value="ASO">
        <option value="Brand Direct Source">
        <option value="ESO">
        <option value="PSO">
    </datalist>
    <datalist id="dl-service-provider">
        <option value="DATIAN SUBIC SHOES INC.">
        <option value="ITS">
    </datalist>
    <datalist id="dl-lab-location">
        <option value="PHILIPPINES">
        <option value="SHENZHEN">
        <option value="GUANGZHOU">
        <option value="SHANGHAI">
        <option value="HO CHI MINH">
    </datalist>
    <datalist id="dl-test-packages">
        <option value="VANS Bonding Strength">
        <option value="VANS Whole Shoe Performance">
        <option value="VANS Pull Strength">
        <option value="VANS Foxing Tape - Rubber">
        <option value="VANS Outsole - Rubber">
        <option value="Carton Box (#ODUSACB)">
        <option value="*Select Individual Test Item">
        <option value="CPSIA Product Testing package">
        <option value="ChemST general testing package">
    </datalist>
    <datalist id="dl-test-stage">
        <option value="Production">
        <option value="Development">
        <option value="Buy Ready">
        <option value="SMS">
        <option value="Pre-Production">
        <option value="Confirmation">
    </datalist>
    <datalist id="dl-service-type">
        <option value="In-House Lab Testing">
        <option value="Full Package Testing">
        <option value="Analytical Testing">
    </datalist>
    <datalist id="dl-brands">
        <option value="Vans">
        <option value="The North Face">
        <option value="Timberland">
        <option value="Dickies">
        <option value="Altra">
        <option value="Smartwool">
        <option value="Icebreaker">
        <option value="JanSport">
        <option value="Kipling">
        <option value="Eastpak">
        <option value="Napapijri">
    </datalist>
    <datalist id="dl-age-group">
        <option value="Adult">
        <option value="Children">
        <option value="Infant/Toddler">
    </datalist>
    <datalist id="dl-fiber-parts">
        <option value="Upper">
        <option value="Lining">
        <option value="Insole">
        <option value="Outsole">
        <option value="Shell">
        <option value="Main Fabric">
        <option value="Trim / Lace">
    </datalist>
    <datalist id="dl-fibers">
        <option value="Cotton">
        <option value="Polyester">
        <option value="Nylon / Polyamide">
        <option value="Rubber">
        <option value="Leather">
        <option value="Polyurethane (PU)">
        <option value="EVA">
        <option value="Spandex / Elastane">
        <option value="Rayon / Viscose">
        <option value="Recycled Polyester">
    </datalist>
</div>
