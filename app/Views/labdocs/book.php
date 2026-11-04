<?php
/** @var array $reg */
/** @var array $b */
/** @var array $data */
$canEdit = ! empty($data['canEdit']);
?>
<section class="ld bk" id="bk" data-book="<?= esc($b['book_key'], 'attr') ?>">
    <div class="ld-bar glass bk-bar">
        <div class="ld-bar-l">
            <div class="ld-title"><?php if ($data['backUrl'] !== ''): ?><a class="vd-back" href="<?= esc($data['backUrl'], 'attr') ?>" aria-label="Back to the workbooks">‹ <?= esc($reg['code']) ?></a> <?php else: ?><span class="vd-code"><?= esc($reg['code']) ?></span> <?php endif ?><b><?= esc($b['label']) ?></b></div>
            <div class="ld-meta"><?= esc($b['file_name']) ?></div>
            <div class="ld-meta" id="bk-status" role="status" aria-live="polite">
                <?php if ($b['updated_at']): ?>Last saved <?= esc(date('d M Y H:i', strtotime((string) $b['updated_at']))) ?> by <?= esc($b['updated_by'] ?: '-') ?><?= $b['file_status'] ? ' · file: ' . esc($b['file_status']) : '' ?><?php endif ?>
            </div>
        </div>
        <div class="ld-bar-r">
            <div class="ld-tools">
                <span class="bk-zoom" role="group" aria-label="Zoom">
                    <button type="button" class="ld-btn" id="bk-zout" title="Zoom out (Ctrl + scroll down)" aria-label="Zoom out">−</button>
                    <button type="button" class="ld-btn" id="bk-z100" title="Actual size"><span id="bk-zl">100%</span></button>
                    <button type="button" class="ld-btn" id="bk-zin" title="Zoom in (Ctrl + scroll up)" aria-label="Zoom in">+</button>
                    <button type="button" class="ld-btn" id="bk-zfit" title="Fit to the window">Fit</button>
                </span>
                <?php if ($canEdit): ?>
                    <button type="button" class="ld-btn" id="bk-edit">Edit</button>
                    <button type="button" class="ld-btn ld-primary" id="bk-save" hidden>Save</button>
                    <button type="button" class="ld-btn" id="bk-cancel" hidden>Cancel</button>
                <?php endif ?>
                <button type="button" class="ld-btn" id="bk-print">Print</button>
            </div>
        </div>
    </div>
    <p class="ld-hint" id="bk-hint" hidden>Edit mode: click a cell to change it. <b>Right-click</b> a cell for rows and columns (insert, delete, duplicate, move, width, height), clear, and unmerge. Nothing is saved until you press <b>Save</b>; the Excel file on the share is updated at the same time (a backup copy is kept).</p>
    <div class="bk-tabs" id="bk-tabs" role="tablist" aria-label="Sheets"></div>
    <div class="bk-stage" id="bk-stage"><div class="bk-paper" id="bk-paper"></div></div>
    <ul class="ld-menu ld-ctx" id="bk-ctx" hidden role="menu"></ul>
</section>
<script type="application/json" id="bk-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?= view('labdocs/_revs', ['labRevs' => $labRevs ?? null]) ?>
