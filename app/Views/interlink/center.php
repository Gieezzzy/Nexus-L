<?php
/** InterLink work panel: everyone signs in to InterLink with their own account; NEXUS-L prepares and fills the values. Data: $reports, $openUrl */
?>
<style>
.sc{margin-top:14px;padding:14px}
.sc-head{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between}
.sc-note{margin:8px 0 0;padding:9px 12px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:13px}
.sc-box{border:1px solid var(--line);border-radius:8px;padding:10px;background:#fff;margin-top:12px}
.sc-box h5{margin:0 0 8px;font-size:13px}
.sc-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px}
.sc input[type=text],.sc select{border:1px solid var(--line);border-radius:6px;padding:6px 8px;font:inherit;font-size:13px;background:#fff;color:var(--ink)}
.sc-list{max-height:260px;overflow:auto;border:1px solid var(--line);border-radius:6px}
.sc table{border-collapse:collapse;width:100%;font-size:12px}
.sc th,.sc td{border-bottom:1px solid #e5eaf2;padding:4px 6px;text-align:left}
.sc th{position:sticky;top:0;background:#f1f5f9;color:#475569}
.sc-btn{border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:6px;padding:6px 11px;font:inherit;font-size:13px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.sc-btn.primary{background:var(--brand);border-color:var(--brand);color:#fff}
</style>

<div class="card glass sc" id="sc-root">
    <div class="sc-head">
        <div>
            <h3 style="margin:0">Send a report to InterLink</h3>
            <p class="muted sm" style="margin:2px 0 0">The person in charge (PIC) signs in to InterLink with their own account and submits the report themselves.</p>
        </div>
        <a class="sc-btn primary" id="sc-open" href="<?= esc($openUrl ?? 'https://interlink2.intertek.com/', 'attr') ?>" target="_blank" rel="noopener">Open InterLink (sign in with your account)</a>
    </div>
    <p class="sc-note">NEXUS-L does not log in to InterLink and keeps nobody's InterLink login. It prepares the values of a Lab Reference; you fill the InterLink form in your own browser and press Save / Submit yourself.</p>

    <div class="sc-box">
        <h5>1. Pick the application and the Lab Reference</h5>
        <div class="sc-row">
            <input type="text" id="sc-app" list="sc-app-list" placeholder="Application No. (e.g. A005953693)" style="min-width:220px">
            <datalist id="sc-app-list"><?php foreach (($reports ?? []) as $r): ?><option value="<?= esc($r['application_no'] ?? '') ?>"><?php endforeach ?></datalist>
            <input type="text" id="sc-q" placeholder="Search Lab Ref / TR / material / style" style="flex:1;min-width:180px">
            <select id="sc-cat"><option value="all">All types</option></select>
        </div>
        <div class="sc-list"><table><thead><tr><th></th><th>Lab Ref</th><th>Type</th><th>Material / style</th><th>Result</th></tr></thead><tbody id="sc-labrefs"><tr><td colspan="5" class="muted">Loading…</td></tr></tbody></table></div>
        <div class="sc-row" style="margin-top:8px"><button type="button" class="sc-btn primary" id="sc-fill" disabled>2. Send to InterLink (1 click)</button><span id="sc-msg" class="muted sm"></span></div>
    </div>
</div>
