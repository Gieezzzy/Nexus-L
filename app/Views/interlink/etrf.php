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
.et-step>h4{margin:0 0 8px;font-size:14px;display:flex;align-items:center;gap:8px}
.et-step>h4 i{font-style:normal;display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:var(--brand,#2563eb);color:#fff;font-size:12px}
.et-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}
.et-card{border:2px solid var(--line);border-radius:12px;padding:11px 12px;background:#fff;cursor:pointer;text-align:left;font:inherit;color:var(--ink);transition:border-color .12s,box-shadow .12s,background-color .12s}
.et-card:hover{border-color:#93c5fd}
.et-card.on{border-color:var(--brand,#2563eb);background:#eff6ff;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.et-card b{display:block;font-size:13.5px}
.et-card small{display:block;color:#64748b;margin-top:4px;font-size:11.5px;line-height:1.35}
.et-tag{display:inline-block;margin-top:6px;padding:1px 8px;border-radius:99px;background:#f1f5f9;border:1px solid #cbd5e1;font-size:11px;color:#475569}
.et-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.et input[type=text],.et input[type=date],.et select,.et textarea{border:1px solid var(--line);border-radius:7px;padding:6px 8px;font:inherit;font-size:13px;background:#fff;color:var(--ink);width:100%;box-sizing:border-box}
.et input.bad{border-color:#dc2626;background:#fef2f2}
.et-list{max-height:210px;overflow:auto;border:1px solid var(--line);border-radius:8px;margin-top:8px}
.et table{border-collapse:collapse;width:100%;font-size:12.5px}
.et th,.et td{border-bottom:1px solid #e5eaf2;padding:5px 7px;text-align:left;vertical-align:top}
.et th{position:sticky;top:0;background:#f1f5f9;color:#475569;font-weight:600}
.et tr.pick{cursor:pointer}.et tr.pick:hover{background:#f0f9ff}.et tr.pick.on{background:#dbeafe}
.et-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:10px}
.et-grid label,.et-sec label{display:block;font-size:11px;font-weight:600;color:#475569;margin-bottom:3px}
.et-sec{margin-top:12px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:#fff}
.et-sec>h5{margin:0 0 8px;font-size:12.5px;text-transform:uppercase;letter-spacing:.04em;color:#334155}
.et-rows td input{min-width:110px}
.et-btn{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:8px;padding:7px 13px;font:inherit;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.et-btn:hover{border-color:#93c5fd;background:#f0f9ff}
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
.et-fixed{font-size:12px;color:#64748b}
@media (prefers-reduced-motion:reduce){.et-card{transition:none}}
</style>

<div class="card glass et" id="et-root">
    <div>
        <h3>VF eTRF &mdash; create a request or a test in InterLink</h3>
        <p class="et-sub sm">Pick a template, pick the Lab Reference (or type the details), check what is missing, then save a draft or submit. The InterLink background service does the typing in InterLink; nothing is invented: what is missing is listed and the request is not sent until it is filled.</p>
    </div>

    <div class="et-status" id="et-status"><span class="et-dot" id="et-dot"></span><span id="et-status-text">Checking the InterLink background service...</span></div>

    <div class="et-step">
        <h4><i>1</i> Template (InterLink Home &rarr; Submit a Request)</h4>
        <div class="et-cards" id="et-cards">
            <?php foreach ($templates as $t): ?>
            <button type="button" class="et-card" data-code="<?= esc($t['code'], 'attr') ?>" data-mat="<?= $t['sample_type'] === 'Material' ? '1' : '0' ?>">
                <b><?= esc($t['label']) ?></b>
                <small><?= $t['sample_type'] === 'Material' ? 'A material or component test (fabric, leather, rubber, hardware...).' : (str_contains($t['label'], 'bonding') ? 'Shoe bonding tests (sole to upper).' : (str_contains($t['label'], 'WHOLE') ? 'Whole-shoe tests: heat aging, hydrolysis, flexing, bonding.' : (str_contains($t['label'], 'PULL') ? 'Pull-strength test package for a finished product.' : 'A finished product (style) test request.'))) ?></small>
                <span class="et-tag"><?= esc($t['sample_type']) ?></span><?php if ($t['package'] !== ''): ?> <span class="et-tag"><?= esc($t['package']) ?></span><?php endif ?>
            </button>
            <?php endforeach ?>
        </div>
    </div>

    <div class="et-step">
        <h4><i>2</i> Lab Reference <small class="muted" style="font-weight:400">(optional: it fills the form for you)</small></h4>
        <div class="et-row">
            <input type="text" id="et-q" placeholder="Search Lab Ref / TR No. / material / style / supplier" style="flex:1;min-width:240px;width:auto">
            <select id="et-cat" style="width:auto"><option value="all">All types</option></select>
            <button type="button" class="et-btn" id="et-clear-ref">No Lab Reference</button>
            <span class="muted sm" id="et-ref-note"></span>
        </div>
        <div class="et-list"><table><thead><tr><th>Lab Ref</th><th>Type</th><th>Material / style</th><th>Supplier</th><th>Result</th></tr></thead><tbody id="et-labrefs"><tr><td colspan="5" class="muted">Loading...</td></tr></tbody></table></div>
    </div>

    <div class="et-step">
        <h4><i>3</i> The request <span class="et-tag" id="et-badge">choose a template</span></h4>

        <div class="et-sec"><h5>Applicant</h5><div class="et-grid">
            <div><label>Company *</label><input type="text" data-f="company_name"></div>
            <div><label>InterLink code *</label><input type="text" data-f="interlink_code"></div>
            <div><label>Contact person *</label><input type="text" data-f="contact_person"></div>
            <div><label>E-mail recipients</label><input type="text" data-f="email_recipients"></div>
        </div></div>

        <div class="et-sec"><h5>Service requested</h5><div class="et-grid">
            <div><label>Test stage *</label><input type="text" data-f="test_stage"></div>
            <div><label>Service test type</label><input type="text" data-f="service_test_type"></div>
            <div><label>Retest?</label><select data-f="is_retest"><option value="No">No</option><option value="Yes">Yes</option></select></div>
            <div><label>Previous report No.</label><input type="text" data-f="previous_report_no"></div>
            <div style="grid-column:span 2"><label>What was improved (retest)</label><input type="text" data-f="improvements"></div>
        </div></div>

        <div class="et-sec"><h5>Parties and shipment</h5><div class="et-grid">
            <div><label>Brand *</label><input type="text" data-f="buyer_brand"></div>
            <div><label>Country of origin</label><input type="text" data-f="country_of_origin"></div>
            <div><label id="et-l-vendor">Vendor name *</label><input type="text" data-f="vendor_name"></div>
            <div><label id="et-l-vendor-id">Vendor ID *</label><input type="text" data-f="vendor_id"></div>
            <div><label>Factory name *</label><input type="text" data-f="factory_name"></div>
            <div><label>Factory ID *</label><input type="text" data-f="factory_id"></div>
            <div data-only="product"><label>Product exported to</label><input type="text" data-f="product_export_to"></div>
            <div data-only="product"><label>Age group</label><input type="text" data-f="age_group"></div>
            <div data-only="product"><label>Cargo receive date *</label><input type="text" data-f="cargo_receive_date" placeholder="e.g. 07 Nov 2026"></div>
            <div data-only="material"><label>Season</label><input type="text" data-f="season"></div>
            <div data-only="material"><label>End use</label><input type="text" data-f="end_use" placeholder="e.g. Upper"></div>
            <div data-only="material"><label>Material sub type</label><input type="text" data-f="material_sub_type"></div>
        </div></div>

        <div class="et-sec"><h5 id="et-rows-title">Style information</h5>
            <div class="et-list" style="max-height:none"><table class="et-rows"><thead id="et-rows-head"></thead><tbody id="et-rows-body"></tbody></table></div>
            <div class="et-row" style="margin-top:8px"><button type="button" class="et-btn" id="et-add-row">+ Add a row</button><span class="muted sm">One row per style (or per material) in the request.</span></div>
        </div>

        <div class="et-sec"><h5>Tests requested</h5><div class="et-grid">
            <div><label>Test package(s) *</label><input type="text" data-f="test_packages"></div>
            <div style="grid-column:span 2"><label>Individual tests</label><input type="text" data-f="individual_tests"></div>
            <div style="grid-column:1/-1"><label>Remarks</label><input type="text" data-f="lab_remarks"></div>
        </div>
        <p class="et-fixed" style="margin:8px 0 0">The fixed values InterLink needs (address, phone, cost centre, divisions) come from <b>Settings &rarr; InterLink report defaults</b>.</p></div>
    </div>

    <div class="et-step">
        <h4><i>4</i> Check and send</h4>
        <div class="et-check" id="et-check">Choose a template to begin.</div>
        <div class="et-row" style="margin-top:10px">
            <button type="button" class="et-btn primary" id="et-save" disabled>Save as draft in InterLink</button>
            <button type="button" class="et-btn warn" id="et-submit" disabled>Submit to InterLink (Print &amp; Submit)</button>
            <span class="muted sm" id="et-msg"></span>
        </div>
        <p class="muted sm" style="margin:8px 0 0">A <b>draft</b> is saved in your InterLink account so you can look at it first. <b>Submit</b> sends the request to Intertek for real.</p>
    </div>

    <div class="et-step">
        <h4>Requests sent from NEXUS-L</h4>
        <div class="et-list" style="max-height:260px"><table><thead><tr><th>#</th><th>Template</th><th>Lab Ref</th><th>Style / material</th><th>Mode</th><th>Application No.</th><th>Status</th><th>By</th><th>When</th></tr></thead><tbody id="et-history"><tr><td colspan="9" class="muted">Loading...</td></tr></tbody></table></div>
    </div>
</div>
