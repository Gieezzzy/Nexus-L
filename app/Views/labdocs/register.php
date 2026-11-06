<?php
/** @var array $reg */
/** @var array $data */
/** @var array $files */
/** @var array $log */
$canEdit = ! empty($data['canEdit']);
?>
<section class="ld" id="ld" data-code="<?= esc($reg['code'], 'attr') ?>">
    <div class="ld-bar glass">
        <div class="ld-bar-l">
            <div class="ld-title"><b><?= esc($reg['code']) ?></b> <span><?= esc($reg['title']) ?></span></div>
            <div class="ld-meta">
                <?php if ($files): ?>
                    Folder <code><?= esc($reg['folder_name']) ?></code> · <?= count($files) ?> files:
                    <?php foreach ($files as $f): ?><span class="ld-chip" title="<?= esc($f['name'] . ' · ' . number_format(((int) $f['size']) / 1024, 0) . ' KB · ' . $f['modified'], 'attr') ?>"><?= esc(mb_strimwidth($f['name'], 0, 34, '…')) ?></span><?php endforeach ?>
                <?php endif ?>
            </div>
            <div class="ld-meta" id="ld-status" role="status" aria-live="polite">
                <?php if (! empty($reg['updated_at'])): ?>Last saved <?= esc(date('d M Y H:i', strtotime((string) $reg['updated_at']))) ?> by <?= esc($reg['updated_by'] ?: '-') ?><?= ! empty($reg['file_status']) ? ' · file: ' . esc($reg['file_status']) : '' ?><?php endif ?>
            </div>
        </div>
        <div class="ld-bar-r">
            <div class="ld-pager" id="ld-pager" aria-label="Pages"></div>
            <div class="ld-tools">
                <div class="ld-drop">
                    <button type="button" class="ld-btn" id="ld-cols-btn" aria-haspopup="true" aria-expanded="false">Columns</button>
                    <div class="ld-menu" id="ld-cols-menu" hidden></div>
                </div>
                <?php if ($canEdit): ?>
                    <button type="button" class="ld-btn" id="ld-edit">Edit</button>
                    <button type="button" class="ld-btn ld-primary" id="ld-save" hidden>Save</button>
                    <button type="button" class="ld-btn" id="ld-cancel" hidden>Cancel</button>
                <?php endif ?>
                <button type="button" class="ld-btn" id="ld-print">Print</button>
            </div>
        </div>
    </div>
    <p class="ld-hint" id="ld-hint" hidden>Edit mode: click a cell to change it. <b>Right-click</b> a cell for row and column actions (add, delete, move, width). Nothing is saved until you press <b>Save</b>; <b>Cancel</b> puts everything back.</p>

    <div class="ld-stage" id="ld-stage"><div class="ld-pages" id="ld-pages"></div></div>

    <ul class="ld-menu ld-ctx" id="ld-ctx" hidden role="menu"></ul>
    <div class="ld-measure" id="ld-measure" aria-hidden="true"></div>
</section>
<script type="application/json" id="ld-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?= view('labdocs/_revs', ['labRevs' => $labRevs ?? null, 'labCollab' => $labCollab ?? null]) ?>
