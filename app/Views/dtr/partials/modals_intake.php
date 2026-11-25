<?php /* DTR partial: Specimen / Whole Shoe intake and Material receive modals. Included by dtr/index.php, shares its variables. */ ?>
<!-- =======================================================================
     SPECIMEN INTAKE MODAL (+ Shoe Collected: Building A / Building B)
     ======================================================================= -->
<!-- Bonding report picture editor: circle / highlight the failed spots, attach a PASS / FAILED / TOLERABLE stamp -->
<div id="bimg-backdrop" class="dtr-modal-backdrop hidden">
    <div class="bimg-dialog">
        <div class="bimg-bar">
            <button type="button" class="bimg-btn bimg-burger" id="bimg-burger" title="Editing tools" aria-label="Editing tools">&#9776;</button>
            <b id="bimg-title">Report</b>
            <span class="bimg-sep"></span>
            <span id="bimg-msg" class="muted sm"></span>
            <button type="button" class="dtr-btn dtr-btn-outline" id="bimg-close">Close</button>
            <button type="button" class="dtr-btn dtr-btn-primary" id="bimg-save" title="Save as the new report; the report in place becomes the next old version (_1, _2 ...)">Save</button>
        </div>
        <div id="bimg-menu" class="bimg-menu hidden">
            <button type="button" class="bimg-tool active" data-tool="circle" title="Drag to draw a circle around a failed spot">&#9711; Circle</button>
            <button type="button" class="bimg-tool" data-tool="mark" title="Drag to highlight an area">&#9608; Highlight</button>
            <button type="button" class="bimg-tool" data-tool="text" title="Drag a box, then type: a white box with your value written over the old one">T Text box</button>
            <button type="button" class="bimg-tool" data-tool="eyedropper" title="Click any text or colour on the report to copy its colour">&#128396; Pick Colour</button>
            <button type="button" class="bimg-tool" data-tool="move" title="Select and move or resize anything (drag the blue corner to resize)">&#10021; Move</button>
            <span class="bimg-sep"></span>
            <span class="bimg-zoom" title="Zoom (Ctrl + mouse wheel also works)">
                <button type="button" class="bimg-btn" id="bimg-zout" aria-label="Zoom out">&minus;</button>
                <b id="bimg-zlbl">100%</b>
                <button type="button" class="bimg-btn" id="bimg-zin" aria-label="Zoom in">+</button>
                <button type="button" class="bimg-btn" id="bimg-fit">Fit</button>
                <button type="button" class="bimg-btn" id="bimg-z100">100%</button>
            </span>
            <span class="bimg-sep"></span>
            <button type="button" class="bimg-stamp" data-stamp="PASS"><img src="<?= base_url('assets/images/stamps/pass.png') ?>" alt="PASS"></button>
            <button type="button" class="bimg-stamp" data-stamp="FAILED"><img src="<?= base_url('assets/images/stamps/failed.png') ?>" alt="FAILED"></button>
            <button type="button" class="bimg-stamp" data-stamp="TOLERABLE"><img src="<?= base_url('assets/images/stamps/tolerable.png') ?>" alt="TOLERABLE"></button>
            <span class="bimg-sep"></span>
            <label class="bimg-size" title="Font style of the selected text box (also used for the next ones)">Font <select id="bimg-font"><option value="&quot;Segoe UI&quot;, &quot;Segoe UI Variable&quot;, Calibri, Arial, sans-serif">Segoe UI</option><option value="Arial, sans-serif">Arial</option><option value="Calibri, sans-serif">Calibri</option><option value="Verdana, sans-serif">Verdana</option><option value="Tahoma, sans-serif">Tahoma</option><option value="&quot;Times New Roman&quot;, serif">Times New Roman</option><option value="Georgia, serif">Georgia</option><option value="&quot;Courier New&quot;, monospace">Courier New</option></select></label>
            <label class="bimg-size" title="Font colour of the selected text box (also used for the next ones)">Colour <input type="color" id="bimg-color" value="#000000"></label>
            <span class="bimg-swatches" title="Quick report ink colours">
                <button type="button" class="bimg-swatch active" data-color="#000000" style="background:#000000" title="Report Black (#000000)"></button>
                <button type="button" class="bimg-swatch" data-color="#0284c7" style="background:#0284c7" title="Report Blue (#0284c7)"></button>
                <button type="button" class="bimg-swatch" data-color="#1d4ed8" style="background:#1d4ed8" title="Dark Blue (#1d4ed8)"></button>
                <button type="button" class="bimg-swatch" data-color="#dc2626" style="background:#dc2626" title="Result Red (#dc2626)"></button>
                <button type="button" class="bimg-swatch" data-color="#15803d" style="background:#15803d" title="Result Green (#15803d)"></button>
                <button type="button" class="bimg-swatch" data-color="#475569" style="background:#475569" title="Report Gray (#475569)"></button>
            </span>
            <button type="button" class="bimg-btn" id="bimg-bold" title="Toggle Bold font weight"><b>B</b></button>
            <button type="button" class="bimg-btn" id="bimg-align" data-align="center" title="Switch text alignment (Center / Left)">Align: Center</button>
            <button type="button" class="bimg-btn" id="bimg-copyfmt" title="Copy font, colour, bold and alignment from the selected text box (or current style)">Copy Format</button>
            <button type="button" class="bimg-btn" id="bimg-pastefmt" disabled title="Apply copied format to the selected text box">Apply Format</button>
            <span class="bimg-sep"></span>
            <label class="bimg-size" title="Size of the selected stamp">Size <input type="range" id="bimg-size" min="8" max="70" value="28"></label>
            <button type="button" class="bimg-btn" id="bimg-del" title="Delete the selected item (Delete key)">Delete</button>
            <button type="button" class="bimg-btn" id="bimg-undo">Undo</button>
            <button type="button" class="bimg-btn" id="bimg-orig" title="Throw away the unsaved edits and reload the saved report">Reload</button>
        </div>
        <div class="bimg-stage"><div class="bimg-wrap"><canvas id="bimg-canvas"></canvas><input type="text" id="bimg-text" class="hidden" autocomplete="off"></div></div>
    </div>
</div>

<div id="dtr-intake-backdrop" class="dtr-modal-backdrop hidden">
    <div class="dtr-modal-dialog dtr-intake-dialog">
        <div class="dtr-modal-header dtr-intake-head">
            <div>
                <h3>Specimen Intake</h3>
                <small>Enter every specimen collected per line, including mixed tests and retests.</small>
            </div>
            <div class="dtr-intake-batch">
                <b id="dtr-intake-batch-label">A &bull; <?= date('Y-m-d') ?></b>
                <button type="button" id="btn-intake-close" class="dtr-btn-xs" style="background:transparent;color:#fff;border:none;font-size:20px;cursor:pointer" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="dtr-intake-bar">
            <div class="dtr-intake-field">
                <label>Building</label>
                <div class="dtr-intake-toggle" id="dtr-intake-building">
                    <button type="button" class="active" data-building="A">Bldg A</button>
                    <button type="button" data-building="B">Bldg B</button>
                </div>
            </div>
            <div class="dtr-intake-field">
                <label for="dtr-intake-collection">Collection Date</label>
                <input type="date" id="dtr-intake-collection" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="dtr-intake-field">
                <label for="dtr-intake-production">Production Date</label>
                <input type="date" id="dtr-intake-production" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="dtr-intake-field" style="margin-left:auto">
                <label for="dtr-intake-encoder">Encoded By</label>
                <input type="text" id="dtr-intake-encoder" value="<?= esc((string) (service('auth')->user()['name'] ?? ''), 'attr') ?>" placeholder="Name of encoder" style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px;min-width:220px">
            </div>
        </div>
        <div class="dtr-modal-body dtr-intake-body">
            <table class="dtr-table dtr-intake-table" id="dtr-intake-table">
                <thead>
                    <tr>
                        <th>Active</th><th>Line</th><th>Side</th><th>Lab Ref No</th><th>Test Type</th><th>Production Code</th><th>MTL #</th><th>Colorway</th><th>Style</th>
                        <th>Image</th><th>Pull Type</th><th>Lacking <span class="ik-advh">/ Advance</span></th><th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="dtr-intake-tbody"></tbody>
            </table>
        </div>
        <div class="dtr-modal-footer">
            <span id="dtr-intake-msg" class="muted sm" style="margin-right:auto"></span>
            <button type="button" id="btn-intake-cancel" class="dtr-btn dtr-btn-outline">Cancel</button>
            <button type="button" id="btn-intake-save" class="dtr-btn dtr-btn-primary">Save Batch</button>
        </div>
    </div>
</div>

<!-- =======================================================================
     WHOLE SHOE INTAKE MODAL (+ Whole Shoe Collected -> tbl_dtr_shoe_list)
     ======================================================================= -->
<div id="dtr-wshoe-backdrop" class="dtr-modal-backdrop hidden">
    <div class="dtr-modal-dialog dtr-intake-dialog dtr-wshoe-dialog">
        <div class="dtr-modal-header dtr-intake-head">
            <div>
                <h3>Whole Shoe Intake</h3>
                <small>Enter every whole shoe collected, one per line. Existing Production Codes are skipped.</small>
            </div>
            <button type="button" id="btn-wshoe-close" class="dtr-btn-xs" style="background:transparent;color:#fff;border:none;font-size:20px;cursor:pointer" aria-label="Close">&times;</button>
        </div>
        <div class="dtr-intake-bar">
            <div class="dtr-intake-field">
                <label>Building</label>
                <div class="dtr-intake-toggle" id="dtr-wshoe-building">
                    <button type="button" class="active" data-building="A">Bldg A</button>
                    <button type="button" data-building="B">Bldg B</button>
                </div>
            </div>
            <div class="dtr-intake-field">
                <label for="dtr-wshoe-date">Collection Date</label>
                <input type="date" id="dtr-wshoe-date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="dtr-intake-field">
                <label for="dtr-wshoe-production">Production Date</label>
                <input type="date" id="dtr-wshoe-production" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="dtr-intake-field" style="margin-left:auto">
                <label for="dtr-wshoe-encoder">Encoded By</label>
                <input type="text" id="dtr-wshoe-encoder" value="<?= esc((string) (service('auth')->user()['name'] ?? ''), 'attr') ?>" style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px;min-width:220px">
            </div>
        </div>
        <div class="dtr-modal-body dtr-intake-body">
            <table class="dtr-table dtr-intake-table dtr-wshoe-table">
                <thead>
                    <tr><th>Type</th><th>Line</th><th>Side</th><th>Test Type Lab Ref No</th><th>Production Code</th><th>Style No.</th><th>Colorway</th><th>MTL #</th><th>Shoe Master</th><th>No. of Shoes</th><th>Test Type</th><th></th></tr>
                </thead>
                <tbody id="dtr-wshoe-tbody"></tbody>
            </table>
        </div>
        <div class="dtr-modal-footer">
            <span id="dtr-wshoe-msg" class="muted sm" style="margin-right:auto"></span>
            <button type="button" id="btn-wshoe-cancel" class="dtr-btn dtr-btn-outline">Cancel</button>
            <button type="button" id="btn-wshoe-save" class="dtr-btn dtr-btn-primary">Save Batch</button>
        </div>
    </div>
</div>

<!-- =======================================================================
     MATERIAL RECEIVE MODAL (+ Material Receive -> IQC Container Receiving Log)
     ======================================================================= -->
<div id="dtr-mrec-backdrop" class="dtr-modal-backdrop hidden">
    <div class="dtr-modal-dialog dtr-intake-dialog dtr-mrec-dialog">
        <div class="dtr-modal-header dtr-intake-head">
            <div>
                <h3>Material Receive</h3>
                <small>Log incoming materials into the IQC Container Receiving queue. Pick a Production Code and Material Code to auto-fill details.</small>
            </div>
            <div style="display:flex;align-items:center;gap:12px">
                <span id="dtr-mrec-count-badge" class="mrec-count-badge">4 lines</span>
                <button type="button" id="btn-mrec-close" class="dtr-btn-xs" style="background:transparent;color:#fff;border:none;font-size:22px;cursor:pointer;line-height:1" aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="dtr-intake-bar mrec-top-bar">
            <div class="dtr-intake-field">
                <label for="dtr-mrec-date">Received Date</label>
                <input type="date" id="dtr-mrec-date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="dtr-intake-field">
                <label for="dtr-mrec-arrived">Date Arrived</label>
                <input type="date" id="dtr-mrec-arrived" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="dtr-intake-field" style="margin-left:auto">
                <label for="dtr-mrec-encoder">Encoded By</label>
                <input type="text" id="dtr-mrec-encoder" value="<?= esc((string) (service('auth')->user()['name'] ?? ''), 'attr') ?>" style="padding:7px 11px;border:1px solid #cbd5e1;border-radius:6px;font-size:12.5px;min-width:220px;background:#fff">
            </div>
        </div>
        <div class="dtr-modal-body dtr-intake-body mrec-body-scroll">
            <table class="dtr-table dtr-intake-table dtr-mrec-table" data-tests="<?= esc(json_encode(array_keys(\App\Controllers\DtrController::MATERIAL_TESTS)), 'attr') ?>">
                <colgroup>
                    <col style="width:42px"><!-- # -->
                    <col style="width:132px"><!-- Production Code -->
                    <col style="width:140px"><!-- Colorway -->
                    <col style="width:112px"><!-- MTL # -->
                    <col style="width:128px"><!-- Material Code -->
                    <col style="width:185px"><!-- Material Name -->
                    <col style="width:170px"><!-- Material Type + quantity to test -->
                    <col style="width:125px"><!-- Supplier -->
                    <col style="width:108px"><!-- Container # -->
                    <col style="width:98px"><!-- Batch # -->
                    <col style="width:74px"><!-- Qty -->
                    <col style="width:170px"><!-- Test selection -->
                    <col style="width:40px"><!-- Action -->
                </colgroup>
                <thead>
                    <tr class="mrec-grp-row">
                        <th rowspan="2" class="mrec-th-num">#</th>
                        <th colspan="3" class="mrec-grp-hd grp-erp">Production Order (ERP)</th>
                        <th colspan="4" class="mrec-grp-hd grp-mat">Material Details</th>
                        <th colspan="3" class="mrec-grp-hd grp-shp">Shipment &amp; Quantity</th>
                        <th rowspan="2" class="mrec-grp-hd grp-tst">Test Selection</th>
                        <th rowspan="2" class="mrec-th-act"></th>
                    </tr>
                    <tr class="mrec-sub-row">
                        <th>Production Code</th>
                        <th>Colorway</th>
                        <th>MTL #</th>
                        <th>Material Code <span class="req-dot" title="Required">*</span></th>
                        <th>Material Name <span class="req-dot" title="Lookup or type material">*</span></th>
                        <th>Material Type</th>
                        <th>Supplier</th>
                        <th>Container #</th>
                        <th>Batch #</th>
                        <th>Qty</th>
                    </tr>
                </thead>
                <tbody id="dtr-mrec-tbody"></tbody>
            </table>
        </div>
        <div class="dtr-modal-footer mrec-footer">
            <div style="display:flex;align-items:center;gap:12px;margin-right:auto">
                <button type="button" id="btn-mrec-addrow-foot" class="dtr-btn dtr-btn-outline" style="padding:6px 12px;font-size:12px">+ Add another line</button>
                <span class="mrec-hint">Tip: Type in <b>Production Code</b> or <b>Material Code</b> to search and auto-fill related fields.</span>
                <span id="dtr-mrec-msg" class="mrec-err-msg"></span>
            </div>
            <button type="button" id="btn-mrec-cancel" class="dtr-btn dtr-btn-outline">Cancel</button>
            <button type="button" id="btn-mrec-save" class="dtr-btn dtr-btn-primary" style="padding:8px 22px;font-weight:700">Save Material Receive</button>
        </div>
    </div>
</div>

<!-- Retest picker: failed Lab Ref Nos that need a retest, with the analysis / reason -->
<div id="dtr-retest-backdrop" class="dtr-modal-backdrop hidden" style="z-index:10020">
    <div class="dtr-modal-dialog" style="max-width:1100px">
        <div class="dtr-modal-header">
            <h3>Retest needed &mdash; select the Lab Ref No.</h3>
            <button type="button" id="btn-retest-close" class="dtr-btn-xs" style="background:transparent;color:#fff;border:none;font-size:20px;cursor:pointer" aria-label="Close">&times;</button>
        </div>
        <div class="dtr-modal-body" style="padding:0;background:#fff">
            <div style="padding:12px 16px;border-bottom:1px solid #e2e8f0">
                <input type="text" id="dtr-retest-search" placeholder="Search Lab Ref No., style or color…" style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #cbd5e1;border-radius:4px;font-size:13px">
            </div>
            <table class="dtr-table" style="width:100%">
                <thead><tr><th>Lab Ref No.</th><th>Style</th><th>Color</th><th>Result</th><th>Mode of Failure</th><th>Analysis / Reason</th><th>Tested</th><th></th></tr></thead>
                <tbody id="dtr-retest-tbody"></tbody>
            </table>
        </div>
        <div class="dtr-modal-footer">
            <button type="button" id="btn-retest-cancel" class="dtr-btn dtr-btn-outline">Cancel</button>
        </div>
    </div>
</div>
