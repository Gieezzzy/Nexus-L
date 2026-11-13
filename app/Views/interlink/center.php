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

<div class="card glass sc" id="sc-moved">
    <h3>Sending to InterLink moved</h3>
    <p class="muted sm" style="line-height:1.6">The browser-extension sender (which opened an InterLink tab) was removed. Every submission now goes through the NexusInterlinkService, which works in its own browser, so no new tab opens here.</p>
    <div class="sc-row" style="margin-top:12px">
        <a class="sc-btn primary" href="<?= site_url('interlink/etrf') ?>">VF eTRF (send a report)</a>
        <a class="sc-btn" href="<?= site_url('interlink/vf-test-request') ?>">VF - Test Request</a>
        <a class="sc-btn" href="<?= site_url('lab-monitoring/vf-requests') ?>">VF Test Monitoring</a>
    </div>
</div>
