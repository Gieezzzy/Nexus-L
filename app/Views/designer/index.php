<?php
/** Report Designer (admin). Data: $formFields = [['name'=>..,'label'=>..], ...] */
?>
<div class="card glass dsg-head">
    <div>
        <h3>Report Designer <span class="dsg-badge">Admin only</span></h3>
        <p class="muted sm">Place every measuring point on the shoe, hide or rename form fields, add custom fields and extra items to the Whole Shoe Bonding report. Changes apply to everyone after <b>Save</b>.</p>
    </div>
    <div class="dsg-tabs" role="tablist">
        <button type="button" class="dsg-tab active" data-tab="diagram">Shoe diagram</button>
        <button type="button" class="dsg-tab" data-tab="form">Form fields</button>
        <button type="button" class="dsg-tab" data-tab="excel">Excel readers</button>
        <button type="button" class="dsg-tab" data-tab="locations">File locations</button>
        <button type="button" class="dsg-tab" data-tab="signatories">Signatories</button>
    </div>
</div>

<script>window.DSG_FIELDS = <?= json_encode($formFields, JSON_UNESCAPED_UNICODE) ?>;</script>

<!-- ============================ DIAGRAM ============================ -->
<section id="dsg-pane-diagram" class="dsg-pane">
    <div class="card glass dsg-bar">
        <label>Number of points
            <select id="dsg-variant">
                <?php foreach ([26, 27, 28, 29, 30, 31, 32, 33, 34] as $n): ?><option value="<?= $n ?>" <?= $n === 27 ? 'selected' : '' ?>><?= $n ?> points</option><?php endforeach ?>
            </select>
        </label>
        <label>Preview record
            <select id="dsg-record"><option value="">(sample values)</option></select>
        </label>
        <label>Side
            <select id="dsg-side"><option value="Left">Left</option><option value="Right">Right</option></select>
        </label>
        <label class="dsg-chk" title="Draws the dots and leader lines separately on a clean shoe outline so you can drag every dot onto the exact spot of the shoe"><input type="checkbox" id="dsg-vector"> Movable dots &amp; lines</label>
        <span class="dsg-sp"></span>
        <button type="button" class="dsg-btn" id="dsg-add-start">+ Start point</button>
        <button type="button" class="dsg-btn" id="dsg-add-text">+ Text</button>
        <button type="button" class="dsg-btn" id="dsg-add-field">+ Value from record</button>
        <button type="button" class="dsg-btn" id="dsg-add-chart">+ Bar chart</button>
        <button type="button" class="dsg-btn" id="dsg-add-image">+ Image</button>
        <button type="button" class="dsg-btn" id="dsg-add-line">+ Line</button>
    </div>

    <div class="dsg-work">
        <div class="card glass dsg-stage-wrap">
            <div id="dsg-stage" class="dsg-stage" tabindex="0"></div>
            <p class="muted sm">Drag an item to move it, drag the small square in its corner to resize, arrow keys nudge (Shift = 5×). Blue = UTF points, purple = FTO points.</p>
        </div>

        <aside class="card glass dsg-side">
            <h4>Selected item</h4>
            <div id="dsg-props" class="dsg-props"><p class="muted sm">Click an item on the diagram.</p></div>

            <h4>Whole diagram</h4>
            <div class="dsg-grid2">
                <label>Move all points X %<input type="number" step="0.1" id="dsg-dx" value="0"></label>
                <label>Move all points Y %<input type="number" step="0.1" id="dsg-dy" value="0"></label>
            </div>
            <div class="dsg-row">
                <button type="button" class="dsg-btn" id="dsg-move-all">Apply move</button>
                <button type="button" class="dsg-btn" id="dsg-show-all">Show all points</button>
            </div>
            <div class="dsg-row">
                <button type="button" class="dsg-btn dsg-primary" id="dsg-save"><svg class="ic" aria-hidden="true"><use href="#i-save"/></svg> Save layout</button>
                <button type="button" class="dsg-btn dsg-danger" id="dsg-reset">Reset to default</button>
            </div>
            <p id="dsg-status" class="muted sm"></p>
        </aside>
    </div>
</section>

<!-- ============================ FORM FIELDS ============================ -->
<section id="dsg-pane-form" class="dsg-pane" hidden>
    <div class="card glass">
        <h4>Whole Shoe Bonding form</h4>
        <p class="muted sm">Untick to hide a field from users, rename the label, and use ▲▼ to change the order. Hidden fields keep their saved data.</p>
        <ul id="dsg-fields" class="dsg-fields"></ul>

        <h4>Custom fields</h4>
        <p class="muted sm">Extra text boxes added to the form. Their values are stored per report and can be placed on the diagram with “+ Value from record”.</p>
        <ul id="dsg-custom" class="dsg-fields"></ul>
        <div class="dsg-row">
            <input type="text" id="dsg-custom-label" placeholder="Label of the new field (e.g. Remarks 2)" maxlength="60">
            <button type="button" class="dsg-btn" id="dsg-custom-add">+ Add field</button>
        </div>

        <div class="dsg-row">
            <button type="button" class="dsg-btn dsg-primary" id="dsg-form-save"><svg class="ic" aria-hidden="true"><use href="#i-save"/></svg> Save form fields</button>
            <button type="button" class="dsg-btn dsg-danger" id="dsg-form-reset">Reset to default</button>
            <span id="dsg-form-status" class="muted sm"></span>
        </div>
    </div>
</section>

<!-- ============================ EXCEL READERS ============================ -->
<section id="dsg-pane-excel" class="dsg-pane" hidden>
    <div class="card glass dsg-bar">
        <label>Report type
            <select id="dsg-x-slug">
                <?php foreach (['bonding' => 'Whole shoe bonding (U60)', 'fabric' => 'Fabric', 'leather' => 'Leather', 'wst' => 'Webbing / lace / thread', 'fthl' => 'Foxing tape & heel logo', 'hardware' => 'Hardware', 'label' => 'Labels', 'sklfm' => 'Sockliner & foam', 'rubber' => 'Rubber outsole', 'box' => 'Carton box', 'insole' => 'Insole board'] as $k => $l): ?>
                    <option value="<?= $k ?>"><?= esc($l) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>Test with Lab Ref No.
            <input type="text" id="dsg-x-ref" placeholder="e.g. DTP20261012FAA8-L">
        </label>
        <span class="dsg-sp"></span>
        <button type="button" class="dsg-btn" id="dsg-x-addmap">+ Add reader (file type)</button>
        <button type="button" class="dsg-btn dsg-primary" id="dsg-x-save"><svg class="ic" aria-hidden="true"><use href="#i-save"/></svg> Save readers</button>
        <span id="dsg-x-status" class="muted sm"></span>
    </div>
    <p class="muted sm">A <b>reader</b> says which OUTDATA folder and sheet to open and which cells fill which report fields. When the machine's columns, rows or sheet change, edit the reader here: use <b>Preview file</b>, click a cell in the grid to fill the active rule's row / column, then <b>Test</b> to see exactly what would be loaded.</p>
    <div class="dsg-work dsg-xwork">
        <div id="dsg-x-maps"></div>
        <aside class="card glass dsg-side">
            <h4>File preview <span id="dsg-x-file" class="muted sm"></span></h4>
            <div class="dsg-row"><select id="dsg-x-sheetsel"><option value="">(first sheet)</option></select><span class="muted sm" id="dsg-x-gridnote">Choose a reader's “Preview file”.</span></div>
            <div id="dsg-x-grid" class="dsg-xgrid"></div>
            <h4>Test result</h4>
            <div id="dsg-x-result" class="dsg-xres muted sm">Nothing tested yet.</div>
        </aside>
    </div>
</section>

<!-- ============================ SIGNATORIES ============================ -->
<section id="dsg-pane-signatories" class="dsg-pane" hidden>
    <div class="card glass">
        <h4>Managers &amp; Supervisors who approve reports</h4>
        <p class="muted sm">A report can be sent to InterLink only after one of these people approves it: they scan the QR code on the Upload Form (or open the link from Synology Chat), sign in with their NEXUS-L account, check the report and sign. Their signature and the date are stamped on the report PDF.</p>
        <table class="dsg-xt" id="dsg-sig-table"><tr><td class="muted sm">Loading…</td></tr></table>
    </div>
    <div class="card glass">
        <h4 id="dsg-sig-form-title">Add a signatory</h4>
        <input type="hidden" id="dsg-sig-id">
        <div class="dsg-sig-grid">
            <label class="dsg-xl">NEXUS-L account (signs in with)
                <select id="dsg-sig-user"></select>
            </label>
            <label class="dsg-xl">Name printed on reports
                <input type="text" id="dsg-sig-name" maxlength="120" placeholder="e.g. Nila B. Estrada">
            </label>
            <label class="dsg-xl">Position
                <input type="text" id="dsg-sig-title" maxlength="80" list="dsg-sig-titles" placeholder="QA Laboratory Supervisor">
                <datalist id="dsg-sig-titles"><option value="QA Laboratory Supervisor"><option value="QA Laboratory Head"><option value="QA Manager"><option value="Laboratory Manager"></datalist>
            </label>
            <label class="dsg-xl dsg-sig-active"><input type="checkbox" id="dsg-sig-active" checked> Active (can approve)</label>
        </div>
        <p class="muted sm">Signature (draw with the mouse, a pen or a finger). It is offered on the approval page; the signatory can also sign there.</p>
        <canvas id="dsg-sig-pad" class="sig-pad" aria-label="Signature box"></canvas>
        <div class="dsg-row">
            <button type="button" class="dsg-btn" id="dsg-sig-upload">Upload PNG / JPG</button>
            <input type="file" id="dsg-sig-file" accept="image/png,image/jpeg" hidden>
            <button type="button" class="dsg-btn" id="dsg-sig-clear">Clear signature</button>
            <button type="button" class="dsg-btn dsg-primary" id="dsg-sig-save"><svg class="ic" aria-hidden="true"><use href="#i-save"/></svg> Save signatory</button>
            <button type="button" class="dsg-btn" id="dsg-sig-new">New</button>
            <span id="dsg-sig-status" class="muted sm"></span>
        </div>
    </div>
</section>

<!-- ============================ FILE LOCATIONS ============================ -->
<section id="dsg-pane-locations" class="dsg-pane" hidden>
    <div class="card glass">
        <h4>Where the machine result files are</h4>
        <p class="muted sm">The folder that holds the report-type folders (BONDING, MATERIALS\TENSILE, ...). When the files move to another PC or share, change it here: no code change. NEXUS-L only ever <b>reads</b> from it (files are copied to a local cache).</p>
        <label class="dsg-xl wide">Root location
            <input type="text" id="dsg-loc-root" placeholder="\\192.168.32.45\OUTDATA">
        </label>
        <div class="dsg-row">
            <button type="button" class="dsg-btn" id="dsg-loc-test">Test location</button>
            <button type="button" class="dsg-btn dsg-primary" id="dsg-loc-save"><svg class="ic" aria-hidden="true"><use href="#i-save"/></svg> Save location</button>
            <button type="button" class="dsg-btn" id="dsg-loc-default">Use default</button>
            <span id="dsg-loc-status" class="muted sm"></span>
        </div>
        <div id="dsg-loc-result" class="dsg-xres"></div>

        <h4>Equipment code → technician (bonding)</h4>
        <p class="muted sm">Filled in automatically when the U60 file is loaded.</p>
        <ul id="dsg-loc-techs" class="dsg-fields"></ul>
        <div class="dsg-row"><button type="button" class="dsg-btn" id="dsg-loc-addtech">+ Add equipment</button></div>
    </div>
</section>
