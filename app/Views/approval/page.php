<?php
/**
 * Report approval page (opened from the QR code on the InterLink Upload Form). Only a live link reaches this page
 * (pending and not expired), so the approver sees just the report and the signing controls.
 * Data: $approval (tbl_dtr_report_approvals row), $signatory (tbl_dtr_signatories row or null)
 */
helper('nx'); // nx_asset(): the page body is built before the layout loads it
$a = $approval;
?>
<div class="apv" id="apv-root" data-token="<?= esc($a['token']) ?>">
    <div class="apv-bar">
        <b>Report approval</b>
        <span class="muted sm">Check the report below, then sign. This link works once and expires at <?= esc(date('H:i', strtotime((string) $a['expires_at']))) ?>.</span>
    </div>

    <?php if (($sheet ?? '') !== ''): ?>
        <!-- the report sheet itself, as on the DTR page: no PDF viewer, nothing to download; the page grows with the report -->
        <link rel="stylesheet" href="<?= nx_asset('css/dtr.css') ?>">
        <div class="apv-sheet" id="apv-sheet"><?= $sheet /* scrubbed when it was stored (ApprovalController::saveSheet) */ ?></div>
    <?php else: ?>
        <div class="card glass"><p class="sm">The report view was not prepared for this request. Ask the person who requested it to press <b>Request approval</b> again on the InterLink Upload Form.</p></div>
    <?php endif ?>

    <div class="card glass apv-sign">
        <?php if (! $signatory): ?>
            <p class="sm">Only a registered Manager or Supervisor can approve reports. Ask an admin to add your account in <b>Report Designer → Signatories</b>.</p>
        <?php elseif (! empty($a['required_name']) && ! \App\Libraries\ReportPdf::nameMatches((string) $a['required_name'], (string) $signatory['full_name'])): ?>
            <p class="sm">This report is signed off by <b><?= esc($a['required_name']) ?></b>. Only <?= esc($a['required_name']) ?> can approve it; you are signed in as <?= esc($signatory['full_name']) ?>.</p>
        <?php else: ?>
            <p class="sm">Signing as <b><?= esc($signatory['full_name']) ?></b> · <?= esc($signatory['title']) ?></p>
            <p class="sm muted">Your signature and the date are stamped on the official lab report (the PDF sent to InterLink) for the same test record shown above.</p>
            <canvas id="apv-pad" class="apv-pad" width="560" height="180" aria-label="Signature box"></canvas>
            <div class="apv-row">
                <?php if (! empty($signatory['signature_png'])): ?>
                    <button type="button" class="nx-btn" id="apv-use-saved" data-sig="<?= esc($signatory['signature_png']) ?>">Use my saved signature</button>
                <?php endif ?>
                <button type="button" class="nx-btn" id="apv-upload">Upload PNG / JPG</button>
                <input type="file" id="apv-file" accept="image/png,image/jpeg" hidden>
                <button type="button" class="nx-btn" id="apv-clear">Clear</button>
            </div>
            <label class="sm apv-lbl" for="apv-remarks">Remarks (needed when returning the report)</label>
            <textarea id="apv-remarks" rows="2" maxlength="1000"></textarea>
            <div class="apv-row">
                <button type="button" class="nx-btn nx-primary" id="apv-approve">Approve &amp; sign</button>
                <button type="button" class="nx-btn apv-reject" id="apv-reject">Return for correction</button>
            </div>
            <p class="sm muted" id="apv-msg" role="status"></p>
        <?php endif ?>
    </div>
</div>
