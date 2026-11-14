<?php /** Shown for an approval link that was already used, has expired or never existed. For an approver, a link that was decided says by whom
 * (and, right after the decision, counts down the 20 seconds before it closes). Nothing else about the report is shown. */
$late = $late ?? null;
?>
<div class="apv">
    <div class="card glass">
        <?php if ($late): ?>
            <h3>This report was already <?= $late['status'] === 'approved' ? 'approved and signed' : 'returned for correction' ?></h3>
            <p class="sm"><b><?= esc($late['lab']) ?></b> was <?= esc($late['status']) ?> by <b><?= esc($late['by']) ?></b> on <?= esc($late['at']) ?>. Nothing more to do: the approval link is closed.</p>
            <?php if ($late['closesIn'] > 0): ?>
                <p class="sm muted" id="apv-gone-count" data-left="<?= (int) $late['closesIn'] ?>" role="status">This page closes in <b><?= (int) $late['closesIn'] ?></b> seconds.</p>
                <script>
                (function () {
                  var n = document.getElementById('apv-gone-count'), left = Number(n.dataset.left);
                  var t = setInterval(function () {
                    left -= 1; n.innerHTML = 'This page closes in <b>' + Math.max(0, left) + '</b> second' + (left === 1 ? '' : 's') + '.';
                    if (left <= 0) { clearInterval(t); location.href = <?= json_encode(site_url('dashboard')) ?>; }
                  }, 1000);
                })();
                </script>
            <?php endif ?>
        <?php else: ?>
            <h3>This approval link is no longer valid</h3>
            <p class="muted sm">Approval links work once and expire after a short time. If a report still needs your approval, ask the person who requested it to press <b>Request approval</b> again on the InterLink Upload Form: a new link is sent to you by Synology Chat and shown as a new QR code.</p>
        <?php endif ?>
        <a class="nx-btn nx-primary" href="<?= site_url('dashboard') ?>">Back to NEXUS-L</a>
    </div>
</div>
