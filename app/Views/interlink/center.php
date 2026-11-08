<?php
/** InterLink work panel: everyone signs in to InterLink with their own account; NEXUS-L prepares and fills the values. Data: $reports, $openUrl */
?>
<style>
.sc{margin-top:16px;padding:22px 24px!important}
.sc-head{display:flex;flex-wrap:wrap;gap:12px 20px;align-items:center;justify-content:space-between}
.sc-head h3{margin:0;font-size:20px;letter-spacing:-.01em}
.sc-head p{margin:4px 0 0;max-width:62ch}
.sc-note{margin:14px 0 0;padding:12px 14px;border-radius:10px;background:#eff6ff;color:#1e3a8a;font-size:13.5px;line-height:1.5}
.sc-status{margin:12px 0 0;display:flex;flex-wrap:wrap;align-items:center;gap:8px 10px;font-size:13.5px;padding:10px 14px;border-radius:10px;background:#f8fafc;border:1px solid #e1e7ef}
.sc-dot{width:10px;height:10px;border-radius:50%;background:#94a3b8;flex:none}
.sc-dot-ok{background:#16a34a;box-shadow:0 0 0 4px rgba(22,163,74,.15)}
.sc-dot-off{background:#dc2626;box-shadow:0 0 0 4px rgba(220,38,38,.12)}
#sc-status-text{flex:1;min-width:220px}
.sc-steps{display:grid;gap:14px;margin-top:16px}
.sc-box{border:1px solid #e1e7ef;border-radius:12px;padding:16px 18px;background:#fff}
.sc-box h5{margin:0 0 12px;font-size:15px;display:flex;align-items:center;gap:10px}
.sc-n{width:26px;height:26px;border-radius:50%;background:#2563eb;color:#fff;display:inline-grid;place-items:center;font-size:13px;font-weight:700;flex:none}
.sc-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px}
.sc input[type=text],.sc select{min-height:40px;border:1px solid #cbd5e1;border-radius:9px;padding:7px 12px;font:inherit;font-size:14px;background:#fff;color:#0f172a}
.sc input[type=text]:focus-visible,.sc select:focus-visible{outline:2px solid #2563eb;outline-offset:0;border-color:#2563eb}
.sc-list{max-height:300px;overflow:auto;border:1px solid #e1e7ef;border-radius:10px}
.sc table{border-collapse:collapse;width:100%;font-size:13.5px}
.sc th,.sc td{border-bottom:1px solid #edf1f6;padding:9px 10px;text-align:left;vertical-align:top}
.sc th{position:sticky;top:0;background:#f1f5f9;color:#334155;font-weight:700;font-size:12.5px;z-index:1}
.sc tbody tr{cursor:pointer}
.sc tbody tr:hover{background:#f8fafc}
.sc tbody tr:has(input:checked){background:#eaf1ff;box-shadow:inset 3px 0 0 #2563eb}
.sc input[type=radio]{width:18px;height:18px;accent-color:#2563eb;margin:1px 0 0}
.sc-btn{min-height:40px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:9px;padding:7px 16px;font:inherit;font-size:13.5px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px}
.sc-btn:hover{background:#f1f5f9;text-decoration:none}
.sc-btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}
.sc-btn.primary:hover:not(:disabled){background:#1d4ed8}
.sc-btn:disabled{opacity:.5;cursor:not-allowed}
.sc-btn:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
.sc-send{min-height:46px;padding:0 24px;font-size:14.5px}
@media (max-width:640px){.sc{padding:16px!important}.sc-head .sc-btn,.sc-send{width:100%}.sc-row>input,.sc-row>select{flex:1 1 100%}}
</style>

<div class="card glass sc" id="sc-root">
    <div class="sc-head">
        <div>
            <h3>Send a report to InterLink</h3>
            <p class="muted sm">The person in charge (PIC) signs in to InterLink with their own account and submits the report themselves.</p>
        </div>
        <a class="sc-btn primary" id="sc-open" href="<?= esc($openUrl ?? 'https://interlink2.intertek.com/', 'attr') ?>" target="_blank" rel="noopener">Open InterLink (sign in with your account)</a>
    </div>
    <p class="sc-note">NEXUS-L does not log in to InterLink and keeps nobody's InterLink login. It prepares the values of a Lab Reference; you fill the InterLink form in your own browser and press Save / Submit yourself.</p>
    <!-- the one thing NEXUS-L can honestly show: whether the sender extension is present in THIS browser. Whether you are
         currently signed in to InterLink itself can never be shown here - NEXUS-L stores nobody's InterLink session - so
         1-Click Send always opens InterLink fresh and lets InterLink itself ask you to sign in if you are not already. -->
    <div class="sc-status" id="sc-status">
        <span class="sc-dot" id="sc-status-dot"></span>
        <span id="sc-status-text">Checking the NEXUS-L InterLink Sender extension…</span>
        <button type="button" class="sc-btn" id="sc-status-install" hidden>Install the sender</button>
    </div>

    <div class="sc-steps">
    <div class="sc-box">
        <h5><span class="sc-n" aria-hidden="true">1</span>Pick the application and the Lab Reference</h5>
        <div class="sc-row">
            <input type="text" id="sc-app" list="sc-app-list" placeholder="Application No. (e.g. A005953693)" style="min-width:220px">
            <datalist id="sc-app-list"><?php foreach (($reports ?? []) as $r): ?><option value="<?= esc($r['application_no'] ?? '') ?>"><?php endforeach ?></datalist>
            <input type="text" id="sc-q" placeholder="Search Lab Ref / TR / material / style" style="flex:1;min-width:180px">
            <select id="sc-cat"><option value="all">All types</option></select>
        </div>
        <div class="sc-list"><table><thead><tr><th></th><th>Lab Ref</th><th>Type</th><th>Material / style</th><th>Result</th></tr></thead><tbody id="sc-labrefs" data-nx-load><tr><td colspan="5" class="muted">Loading…</td></tr></tbody></table></div>
        <div class="sc-row" style="margin:14px 0 0"><span class="sc-n" aria-hidden="true">2</span><button type="button" class="sc-btn primary sc-send" id="sc-fill" disabled>Send to InterLink (1 click)</button><span id="sc-msg" class="muted sm"></span></div>
    </div>

    <div class="sc-box" id="sc-etrf-box">
        <h5 style="margin:0 0 4px">eTRF requests moved</h5>
        <p class="muted sm" style="margin:0;line-height:1.5">Creating a new request or test in InterLink (VF eTRF - Material, Product, PULLTESTTemplate, bonding, Product WHOLE SHOE TEST) is now in the sidebar: <a href="<?= site_url('interlink/etrf') ?>"><b>InterLink &rarr; VF eTRF</b></a>.</p>
    </div>
    </div>
</div>
