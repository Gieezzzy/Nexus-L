<?php
/** @var array $d */
/** @var array $sheets */
/** @var string $fileUrl */
?>
<section class="ld">
    <div class="ld-bar glass">
        <div class="ld-bar-l">
            <div class="ld-title"><b><?= esc($d['label']) ?></b></div>
            <div class="ld-meta"><?= esc($d['file_name']) ?> · read only here; download it to change it</div>
        </div>
        <div class="ld-bar-r"><a class="ld-btn" href="<?= esc($fileUrl, 'attr') ?>">Download</a><button type="button" class="ld-btn" onclick="window.print()">Print</button></div>
    </div>
    <?php foreach ($sheets as $s): ?>
        <h2 class="dv-h"><?= esc($s['name']) ?></h2>
        <div class="dv-wrap"><table class="dv-t">
            <?php foreach ($s['rows'] as $rn => $cells): ?>
                <tr><?php for ($c = 1; $c <= $s['cols']; $c++): ?><td><?= nl2br(esc((string) ($cells[$c] ?? ''))) ?></td><?php endfor ?></tr>
            <?php endforeach ?>
        </table></div>
    <?php endforeach ?>
    <?php if (! $sheets): ?><p class="ld-hint">This workbook could not be shown here. Use Download.</p><?php endif ?>
</section>
