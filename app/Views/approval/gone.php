<?php /** Shown for an approval link that was already used, has expired or never existed. No report details are shown. */ ?>
<div class="apv">
    <div class="card glass">
        <h3>This approval link is no longer valid</h3>
        <p class="muted sm">Approval links work once and expire after a short time. If a report still needs your approval, ask the person who requested it to press <b>Request approval</b> again on the InterLink Upload Form: a new link is sent to you by Synology Chat and shown as a new QR code.</p>
        <a class="nx-btn nx-primary" href="<?= site_url('dashboard') ?>">Back to NEXUS-L</a>
    </div>
</div>
