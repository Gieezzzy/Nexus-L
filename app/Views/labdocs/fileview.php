<?php
/** @var array $reg */
/** @var array $data */
$f    = $data['file'];
$size = $f['size'] >= 1048576 ? round($f['size'] / 1048576, 1) . ' MB' : max(1, (int) round($f['size'] / 1024)) . ' KB';
?>
<section class="ld fv" id="fv" data-pdf="<?= $data['pdf'] ? '1' : '0' ?>">
    <div class="ld-bar glass fv-bar">
        <div class="ld-bar-l">
            <div class="ld-title"><a class="vd-back" id="fv-back" href="<?= esc($data['backUrl'], 'attr') ?>" aria-label="Back to the folder">‹ <?= $f['folder'] === '' ? esc($reg['code']) : esc(basename($f['folder'])) ?></a> <b><?= esc($f['name']) ?></b></div>
            <div class="ld-meta"><?= esc($reg['code']) ?><?= $f['folder'] !== '' ? ' › ' . esc($f['folder']) : '' ?> · <?= esc($size) ?><?= $f['date'] ? ' · ' . esc($f['date']) : '' ?><?= $f['of'] > 1 ? ' · ' . (int) $f['at'] . ' of ' . (int) $f['of'] : '' ?></div>
        </div>
        <div class="ld-bar-r">
            <div class="ld-tools">
                <?php if ($data['prev'] || $data['next']): ?>
                    <span class="bk-zoom" role="group" aria-label="Files of this folder">
                        <a class="ld-btn<?= $data['prev'] ? '' : ' is-off' ?>" id="fv-prev" <?= $data['prev'] ? 'href="' . esc($data['prev'], 'attr') . '"' : 'aria-disabled="true"' ?> title="Previous file (←)" aria-label="Previous file">‹</a>
                        <a class="ld-btn<?= $data['next'] ? '' : ' is-off' ?>" id="fv-next" <?= $data['next'] ? 'href="' . esc($data['next'], 'attr') . '"' : 'aria-disabled="true"' ?> title="Next file (→)" aria-label="Next file">›</a>
                    </span>
                <?php endif ?>
                <button type="button" class="ld-btn" id="fv-print">Print</button>
                <a class="ld-btn" href="<?= esc($data['download'], 'attr') ?>">Download</a>
                <a class="ld-btn" href="<?= esc($data['src'], 'attr') ?>" target="_blank" rel="noopener" title="The original file, in a new tab">Open original</a>
            </div>
        </div>
    </div>
    <div class="ld-stage fv-stage" id="fv-stage" data-ld-stage>
        <?php if ($data['text']): ?>
            <div class="fv-text" id="fv-text">
                <?php if ($data['text']['kind'] === 'csv'): ?>
                    <table class="fv-csv"><tbody>
                        <?php foreach ($data['text']['rows'] as $ri => $row): ?><tr><?php foreach ($row as $c): ?><<?= $ri === 0 ? 'th' : 'td' ?>><?= esc($c) ?></<?= $ri === 0 ? 'th' : 'td' ?>><?php endforeach ?></tr><?php endforeach ?>
                    </tbody></table>
                <?php else: ?><pre><?= esc($data['text']['raw'] ?? '') ?></pre><?php endif ?>
            </div>
        <?php elseif ($data['pdf']): ?>
            <iframe class="fv-pdf" id="fv-frame" title="<?= esc($f['name'], 'attr') ?>" src="<?= esc($data['src'], 'attr') ?>#view=FitH"></iframe>
        <?php else: ?>
            <div class="fv-img" id="fv-img"><img id="fv-pic" src="<?= esc($data['src'], 'attr') ?>" alt="<?= esc($f['name'], 'attr') ?>"></div>
        <?php endif ?>
    </div>
</section>
<?= view('labdocs/_revs', ['labRevs' => $labRevs ?? null, 'labCollab' => $labCollab ?? null]) ?>
