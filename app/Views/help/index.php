<?php /** Help page. Data: $isAdmin */ ?>
<style>
.hp{max-width:920px}
.hp h3{margin:0 0 4px}
.hp .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:12px}
.hp .tile{border:1px solid var(--line);border-radius:10px;padding:12px;background:#fff}
.hp .tile h4{margin:0 0 6px;display:flex;align-items:center;gap:8px;font-size:14px}
.hp .tile p,.hp .tile li{font-size:13px;color:var(--mute);line-height:1.5}
.hp .tile ul{margin:6px 0 0 16px;padding:0}
.hp details{border:1px solid var(--line);border-radius:8px;padding:8px 12px;margin-top:8px;background:#fff}
.hp summary{cursor:pointer;font-weight:600;font-size:14px}
.hp details p{margin:8px 0 2px;font-size:13px;color:var(--mute);line-height:1.5}
.hp kbd{border:1px solid var(--line);border-bottom-width:2px;border-radius:4px;padding:0 5px;font-size:12px;background:#f8fafc}
</style>

<div class="hp">
    <div class="card glass">
        <h3>Help &amp; quick guide</h3>
        <p class="muted sm">NEXUS-L is the laboratory workspace: record test results, produce the official reports and keep InterLink in step. Pick a topic below.</p>
    </div>

    <div class="grid">
        <div class="tile"><h4><svg class="ic" aria-hidden="true"><use href="#i-chart"/></svg> Lab Tracker</h4>
            <p>Every lab report in one list.</p>
            <ul><li>Use the category buttons or the search box (Lab Ref No, TR No, material, style, supplier).</li><li><b>Open Form</b> opens the report to view or edit.</li></ul></div>
        <div class="tile"><h4><svg class="ic" aria-hidden="true"><use href="#i-layers"/></svg> Material Tests</h4>
            <p>Fabric, leather, labels, rubber and the other material forms.</p>
            <ul><li>Choose the form, then the Lab Ref No (or <b>+ New Record</b>).</li><li><b>Load Machine Results</b> fills the readings from the machine file.</li><li><b>Save Report</b> stores it; <b>Print / Preview</b> makes the official sheet.</li></ul></div>
        <div class="tile"><h4><svg class="ic" aria-hidden="true"><use href="#i-grid"/></svg> Shoe Bonding (U60)</h4>
            <p>Whole shoe bonding reports with the shoe diagram.</p>
            <ul><li><b>Load U60 Machine File</b> reads every spot value.</li><li>The report shows each value at its point on the shoe, with the left and right sheets.</li></ul></div>
        <div class="tile"><h4><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> Calculators &amp; Standards</h4>
            <p>Weight, shrinkage, specific gravity, abrasion and the standards lookup.</p></div>
        <div class="tile"><h4><svg class="ic" aria-hidden="true"><use href="#i-link"/></svg> InterLink Sync</h4>
            <p>Send lab results to InterLink 2.0.</p>
            <ul><li><b>Sync Center</b>: sign in to InterLink once; it then keeps working in the background.</li><li>Tick the Lab References to send, then <b>Queue selected</b>.</li><li>If the status turns red, press <b>Sign in to InterLink</b> again.</li></ul></div>
        <?php if ($isAdmin): ?>
        <div class="tile"><h4><svg class="ic" aria-hidden="true"><use href="#i-sliders"/></svg> Report Designer (admin)</h4>
            <p>Move every shoe point and dot, add a START marker, hide or rename form fields, set the machine-file location and the Excel reading rules.</p></div>
        <?php endif ?>
    </div>

    <div class="card glass" style="margin-top:12px">
        <h3>Common questions</h3>
        <details><summary>Download PDF or Print does nothing, or the report is blank</summary>
            <p>Refresh the page with <kbd>Ctrl</kbd> + <kbd>F5</kbd> and open the preview again. <b>Download PDF</b> needs Chrome or Edge on the server; if it still fails, use <b>Print Report / Save as PDF</b>. Both fit the report on one A4 page.</p></details>
        <details><summary>“No matching machine output file found”</summary>
            <p>The machine has not saved the file yet, or the Lab Ref No is typed differently. Check the Lab Ref No and try again. An admin can check where the files are in <b>Report Designer → File locations</b>.</p></details>
        <details><summary>InterLink says the session ended</summary>
            <p>InterLink signs people out after a while. Press <b>Sign in to InterLink</b> in the Sync Center and sign in on the window that opens. Anything you queued is kept and continues by itself.</p></details>
        <details><summary>Some values look wrong after loading machine results</summary>
            <p>Press <b>Refresh &amp; Apply Smart Standards</b> to recalculate averages and pass/fail. If a machine file layout changed, tell an admin so the reading rules can be updated.</p></details>
        <details><summary>I cannot see the Report Designer</summary>
            <p>It is only for administrators. Ask an admin if you need a layout changed.</p></details>
    </div>
</div>
