<?php
/**
 * Report approval page (opened from the QR code on the InterLink Upload Form). Only a live link reaches this page
 * (pending and not expired), so the approver sees just the report and the signing controls.
 * Data: $approval (tbl_dtr_report_approvals row), $signatory (tbl_dtr_signatories row or null)
 */
helper('nx'); // nx_asset(): the page body is built before the layout loads it
$a = $approval;
?>
<div class="apv" id="apv-root" data-token="<?= esc($a['token']) ?>" data-next="<?= esc($nextUrl ?? '', 'attr') ?>">
    <header class="apv-top">
        <div class="apv-title">
            <h2>Report approval</h2>
            <p class="apv-facts-line">
                <b><?= esc($a['lab_ref_no']) ?></b>
                <span><?= esc($a['application_no']) ?></span>
                <span>Requested by <?= esc($a['requested_name'] ?: 'the lab') ?></span>
            </p>
        </div>
        <div class="apv-meta">
            <span class="apv-expiry" title="This link works once">Link expires at <b><?= esc(date('H:i', strtotime((string) $a['expires_at']))) ?></b></span>
            <?php if (! empty($nextUrl)): ?>
                <a class="nx-btn apv-next" id="apv-next" href="<?= esc($nextUrl, 'attr') ?>">Next report <span class="apv-count"><?= (int) $moreCount ?> waiting</span> <span aria-hidden="true">&rarr;</span></a>
            <?php endif ?>
        </div>
    </header>

    <div class="apv-layout">
        <section class="apv-doc" aria-label="Report to check">
            <?php if (($sheet ?? '') !== ''): ?>
                <link rel="stylesheet" href="<?= nx_asset('css/dtr.css') ?>">
                <div class="apv-sheet" id="apv-sheet"><?= $sheet /* scrubbed when it was stored (ApprovalController::saveSheet) */ ?></div>
            <?php else: ?>
                <div class="apv-note"><p>The report view was not prepared for this request. Ask the person who requested it to press <b>Request approval</b> again on the InterLink Upload Form.</p></div>
            <?php endif ?>
        </section>

        <aside class="apv-sign" aria-label="Your decision">
            <?php if (! $signatory): ?>
                <h3>Not an approver</h3>
                <p class="sm">Only a registered Manager or Supervisor can approve reports. Ask an admin to add your account in <b>Report Designer → Signatories</b>.</p>
            <?php elseif (! empty($a['required_name']) && ! \App\Libraries\ReportPdf::nameMatches((string) $a['required_name'], (string) $signatory['full_name'])): ?>
                <h3>Another approver</h3>
                <p class="sm">This report is signed off by <b><?= esc($a['required_name']) ?></b>. Only <?= esc($a['required_name']) ?> can approve it; you are signed in as <?= esc($signatory['full_name']) ?>.</p>
                <?php if (! empty($nextUrl)): ?><p><a class="nx-btn nx-primary" href="<?= esc($nextUrl, 'attr') ?>">Go to the next report</a></p><?php endif ?>
            <?php else: ?>
                <h3>Your decision</h3>
                <p class="apv-who"><b><?= esc($signatory['full_name']) ?></b><span><?= esc($signatory['title']) ?></span></p>
                <p class="sm muted">Your signature and the date are stamped on the official lab report sent to InterLink.</p>
                <label class="apv-lbl" for="apv-pad">Signature</label>
                <canvas id="apv-pad" class="apv-pad" width="560" height="180" aria-label="Signature box"></canvas>
                <div class="apv-row">
                    <?php if (! empty($signatory['signature_png'])): ?>
                        <button type="button" class="nx-btn" id="apv-use-saved" data-sig="<?= esc($signatory['signature_png']) ?>">Use my saved signature</button>
                    <?php endif ?>
                    <button type="button" class="nx-btn" id="apv-upload">Upload image</button>
                    <input type="file" id="apv-file" accept="image/png,image/jpeg" hidden>
                    <button type="button" class="nx-btn" id="apv-clear">Clear</button>
                </div>
                <label class="apv-lbl" for="apv-remarks">Remarks <span class="muted">(required when returning the report)</span></label>
                <textarea id="apv-remarks" rows="3" maxlength="1000"></textarea>
                <div class="apv-actions">
                    <button type="button" class="nx-btn nx-primary" id="apv-approve">Approve &amp; sign</button>
                    <button type="button" class="nx-btn apv-reject" id="apv-reject">Return for correction</button>
                </div>
                <p class="sm muted" id="apv-msg" role="status"></p>
            <?php endif ?>
        </aside>
    </div>
</div>
