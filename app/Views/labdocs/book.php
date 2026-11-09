<?php
/** @var array $reg */
/** @var array $b */
/** @var array $data */
$canEdit = ! empty($data['canEdit']);
?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/luckysheet/plugins/css/pluginsCss.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/luckysheet/plugins/plugins.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/luckysheet/css/luckysheet.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/luckysheet/assets/iconfont/iconfont.css') ?>">

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
                    <button type="button" class="ld-btn ld-primary" id="bk-edit" title="Edit in Excel spreadsheet mode">
                        <svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true" style="vertical-align:-1px;margin-right:4px;"><path d="M11.013 1.427a1.75 1.75 0 0 1 2.474 0l1.086 1.086a1.75 1.75 0 0 1 0 2.474l-8.61 8.61c-.21.21-.47.364-.756.445l-3.251.93a.75.75 0 0 1-.927-.928l.929-3.25c.081-.286.235-.547.445-.758l8.61-8.61Zm1.414 1.06a.25.25 0 0 0-.354 0L10.811 3.75l1.439 1.44 1.263-1.263a.25.25 0 0 0 0-.354l-1.086-1.086ZM11.189 6.25 9.75 4.81 3.292 11.268a.75.75 0 0 0-.175.297l-.62 2.17 2.17-.62a.75.75 0 0 0 .298-.175L11.189 6.25Z" fill="currentColor"/></svg>
                        Edit
                    </button>
                    <button type="button" class="ld-btn ld-primary" id="bk-save" hidden title="Save workbook and patch Excel file on share">Save</button>
                    <button type="button" class="ld-btn" id="bk-cancel" hidden title="Discard edits and exit">Cancel</button>
                    <button type="button" class="ld-btn" id="bk-fullscreen" hidden title="Full screen edit mode">
                        <svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true" style="vertical-align:-1px;margin-right:4px;"><path d="M1.5 1a.5.5 0 0 0-.5.5v4a.5.5 0 0 0 1 0v-3.5h3.5a.5.5 0 0 0 0-1h-4zm13 0a.5.5 0 0 0-.5.5v3.5a.5.5 0 0 0 1 0v-4a.5.5 0 0 0-.5-.5h-4a.5.5 0 0 0 0 1h3.5zm-13 14a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 0-1H2v-3.5a.5.5 0 0 0-1 0v4a.5.5 0 0 0 .5.5zm13 0a.5.5 0 0 0 .5-.5v-4a.5.5 0 0 0-1 0V14h-3.5a.5.5 0 0 0 0 1h4z" fill="currentColor"/></svg>
                        <span id="bk-fs-label">Full Screen</span>
                    </button>
                <?php endif ?>
                <button type="button" class="ld-btn" id="bk-print">Print</button>
                <?php if (! empty($data['downloadUrl'])): ?>
                    <a class="ld-btn" id="bk-download" href="<?= esc($data['downloadUrl'], 'attr') ?>" title="Download original Excel workbook (.xlsx)">Download</a>
                <?php endif ?>
            </div>
        </div>
    </div>
    <p class="ld-hint" id="bk-hint" hidden>Edit mode: formula bar, cell formatting, multi-sheet tabs, and formulas are active. Click <b>Save</b> to update the database, create a revision snapshot, and patch the Excel file on the share.</p>
    <div class="bk-tabs" id="bk-tabs" role="tablist" aria-label="Sheets"></div>
    <div class="bk-stage" id="bk-stage"><div class="bk-paper" id="bk-paper"></div></div>
    <div class="bk-lucky-wrap" id="bk-lucky-wrap" hidden>
        <div class="bk-fs-topbar" id="bk-fs-topbar">
            <div class="bk-fs-title">
                <span class="bk-fs-dot"></span>
                <b><?= esc($b['label']) ?></b> &mdash; <span><?= esc($b['file_name']) ?></span>
                <span class="bk-fs-badge">Interactive Editor</span>
            </div>
            <div class="bk-fs-actions">
                <button type="button" class="ld-btn ld-primary" id="bk-fs-save" title="Save workbook and patch Excel file (Ctrl+S)">
                    <svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true" style="vertical-align:-1px;margin-right:4px;"><path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.751.751 0 0 1 .018-1.042.751.751 0 0 1 1.042-.018L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z" fill="currentColor"/></svg>
                    Save
                </button>
                <button type="button" class="ld-btn" id="bk-fs-cancel" title="Discard unsaved edits">Cancel</button>
                <button type="button" class="ld-btn" id="bk-fs-exit" title="Exit full screen (Esc)">
                    <svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true" style="vertical-align:-1px;margin-right:4px;"><path d="M4 1.5a.5.5 0 0 1 .5.5v2.5H7a.5.5 0 0 1 0 1H4a.5.5 0 0 1-.5-.5V2a.5.5 0 0 1 .5-.5zm8 0a.5.5 0 0 1 .5.5v2.5a.5.5 0 0 1-.5.5H9a.5.5 0 0 1 0-1h2.5V2a.5.5 0 0 1 .5-.5zM1.5 12a.5.5 0 0 1 .5-.5H4.5a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-2.5H2a.5.5 0 0 1-.5-.5zm13 0a.5.5 0 0 1-.5.5H11.5v2.5a.5.5 0 0 1-1 0v-3a.5.5 0 0 1 .5-.5H14a.5.5 0 0 1 .5.5z" fill="currentColor"/></svg>
                    Exit Full Screen
                </button>
            </div>
        </div>
        <div id="bk-luckysheet"></div>
    </div>
    <ul class="ld-menu ld-ctx" id="bk-ctx" hidden role="menu"></ul>
</section>
<script type="application/json" id="bk-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= base_url('assets/vendor/luckysheet/plugins/js/plugin.js') ?>"></script>
<script src="<?= base_url('assets/vendor/luckysheet/luckysheet.umd.js') ?>"></script>
<script src="<?= base_url('assets/vendor/luckysheet/luckyexcel.umd.js') ?>"></script>
<script src="<?= base_url('assets/vendor/luckysheet/exceljs.min.js') ?>"></script>
<?= view('labdocs/_revs', ['labRevs' => $labRevs ?? null, 'labCollab' => $labCollab ?? null]) ?>
