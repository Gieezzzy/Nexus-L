<?php
/** @var array $reg */
/** @var array $m */
/** @var array $data */
/** @var array $revs */
$canEdit = ! empty($data['canEdit']);
?>
<section class="ld vd" id="vd" data-code="<?= esc($m['code'], 'attr') ?>">
    <div class="ld-bar glass vd-bar">
        <div class="ld-bar-l">
            <div class="ld-title"><a class="vd-back" href="<?= esc($data['backUrl'], 'attr') ?>" aria-label="Back">‹ <?= empty($data['method']['spec']) ? 'Test methods' : 'Specification sheets' ?></a>
                <b><?= esc($m['code']) ?></b> <span><?= esc($m['title']) ?></span></div>
            <div class="ld-meta" id="vd-status" role="status" aria-live="polite">
                Updated <?= esc($m['rev_label'] ?: '-') ?><?php if ($m['edited_at']): ?> · edited <?= esc(date('d M Y H:i', strtotime((string) $m['edited_at']))) ?> by <?= esc($m['edited_by'] ?: '-') ?> (revision <?= (int) $m['revision_no'] ?>)<?php endif ?>
            </div>
        </div>
        <div class="ld-bar-r">
            <div class="ld-pager" id="vd-pager" aria-label="Pages"></div>
            <div class="ld-tools">
                <span class="vd-fmt" id="vd-fmt" hidden>
                    <button type="button" class="ld-btn" data-cmd="bold" title="Bold"><b>B</b></button>
                    <button type="button" class="ld-btn" data-cmd="italic" title="Italic"><i>I</i></button>
                    <button type="button" class="ld-btn" data-cmd="underline" title="Underline"><u>U</u></button>
                </span>
                <?php if ($canEdit): ?>
                    <button type="button" class="ld-btn" id="vd-edit">Edit</button>
                    <button type="button" class="ld-btn ld-primary" id="vd-save" hidden>Save</button>
                    <button type="button" class="ld-btn" id="vd-cancel" hidden>Cancel</button>
                <?php endif ?>
                <a class="ld-btn" href="<?= esc($data['pdfUrl'], 'attr') ?>" target="_blank" rel="noopener" title="The original PDF, in a new tab">Open PDF</a>
                <button type="button" class="ld-btn" id="vd-print">Print</button>
                <?php if ($data['illustrated']): ?>
                    <div class="ld-drop">
                        <button type="button" class="ld-btn" id="vd-ill-btn" aria-haspopup="true" aria-expanded="false">Illustrated<?= count($data['illustrated']) > 1 ? ' ▾' : '' ?></button>
                        <div class="ld-menu" id="vd-ill-menu" hidden>
                            <?php foreach ($data['illustrated'] as $il): ?><a class="vd-menu-a" href="<?= esc($il['url'], 'attr') ?>" target="_blank" rel="noopener"><?= esc($il['label']) ?></a><?php endforeach ?>
                        </div>
                    </div>
                <?php endif ?>
                <div class="ld-drop">
                    <button type="button" class="ld-btn" id="vd-more-btn" aria-haspopup="true" aria-expanded="false">More ▾</button>
                    <div class="ld-menu" id="vd-more-menu" hidden>
                        <div class="vd-menu-h">History</div>
                        <?php if (! $revs): ?><div class="vd-menu-t">No edits yet: this is the document converted from the PDF.</div><?php endif ?>
                        <?php foreach ($revs as $r): ?><div class="vd-menu-t">Revision <?= (int) $r['rev_no'] ?> · <?= esc(date('d M Y H:i', strtotime((string) $r['saved_at']))) ?> · <?= esc($r['saved_by']) ?></div><?php endforeach ?>
                        <?php if ($canEdit): ?><button type="button" class="vd-menu-a" id="vd-restore">Restore the original (from the PDF)</button><?php endif ?>
                        <a class="vd-menu-a" href="<?= esc($data['pdfUrl'], 'attr') ?>" download>Download the original PDF</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <p class="ld-hint" id="vd-hint" hidden>Edit mode: click in the text to change it. Use <b>B / I / U</b> for the selected text. <b>Right-click</b> a paragraph or table cell for insert, delete, move and indent. Nothing is saved until you press <b>Save</b>; the original PDF is never changed.</p>
    <div class="ld-stage" id="vd-stage"><div class="ld-pages vd-pages" id="vd-pages"></div></div>
    <ul class="ld-menu ld-ctx" id="vd-ctx" hidden role="menu"></ul>
    <div class="ld-measure" id="vd-measure" aria-hidden="true"></div>
</section>
<script type="application/json" id="vd-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?= view('labdocs/_revs', ['labRevs' => $labRevs ?? null]) ?>
