<?php
/** @var array $reg */
/** @var array $books */
$hue = ['amendment-2026' => 24, 'accredited-2026' => 164, 'record-sheet' => 262];
?>
<section class="ld vm" id="vm">
    <div class="ld-bar glass">
        <div class="ld-bar-l">
            <div class="ld-title"><b><?= esc($reg['code']) ?></b> <span><?= esc($reg['title']) ?></span></div>
            <div class="ld-meta">Folder <code><?= esc($reg['folder_name']) ?></code> · <?= count($books) ?> Excel workbooks</div>
        </div>
    </div>
    <div class="vm-grid bk-cards">
        <?php foreach ($books as $b): ?>
            <article class="vm-card bk-card" style="--h:<?= (int) ($hue[$b['key']] ?? 212) ?>">
                <a class="vm-main" href="<?= esc($b['url'], 'attr') ?>" aria-label="Open <?= esc($b['label'], 'attr') ?>">
                    <span class="bk-kind">Excel</span>
                    <span class="vm-name bk-name"><?= esc($b['label']) ?></span>
                    <span class="bk-file"><?= esc($b['file']) ?></span>
                    <span class="bk-sheets"><?= count($b['sheets']) ?> sheet<?= count($b['sheets']) === 1 ? '' : 's' ?>: <?= esc(mb_strimwidth(implode(' · ', $b['sheets']), 0, 80, '…')) ?></span>
                    <span class="vm-upd">File dated <b><?= esc($b['modified'] ?: '-') ?></b><?php if ($b['edited']): ?> · edited here <?= esc($b['edited']) ?><?php endif ?></span>
                </a>
                <div class="vm-foot">
                    <a class="vm-btn" href="<?= esc($b['url'], 'attr') ?>">Open</a>
                    <span class="vm-pg"><?= (int) $b['rows'] ?> rows</span>
                </div>
            </article>
        <?php endforeach ?>
    </div>
    <?php if (! $books): ?><p class="vm-none">No workbook has been imported yet. Run <code>php spark labdocs:import-books</code>.</p><?php endif ?>
</section>
