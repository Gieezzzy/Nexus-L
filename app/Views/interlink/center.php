<?php
/** InterLink work panel: everyone signs in to InterLink with their own account; NEXUS-L prepares and fills the values. Data: $reports, $openUrl */
?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/interlink-center-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/interlink-center-1.css') ?>">

<div class="card glass sc" id="sc-moved">
    <h3>Sending to InterLink moved</h3>
    <p class="muted sm" style="line-height:1.6">The browser-extension sender (which opened an InterLink tab) was removed. Every submission now goes through the NexusInterlinkService, which works in its own browser, so no new tab opens here.</p>
    <div class="sc-row" style="margin-top:12px">
        <a class="sc-btn primary" href="<?= site_url('interlink/etrf') ?>">VF eTRF (send a report)</a>
        <a class="sc-btn" href="<?= site_url('interlink/vf-test-request') ?>">VF - Test Request</a>
        <a class="sc-btn" href="<?= site_url('lab-monitoring/vf-requests') ?>">VF Test Monitoring</a>
    </div>
</div>
