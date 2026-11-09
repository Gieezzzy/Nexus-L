<?php
/** @var array $reg */
/** @var array $data */
$f          = $data['file'];
$size       = $f['size'] >= 1048576 ? round($f['size'] / 1048576, 1) . ' MB' : max(1, (int) round($f['size'] / 1024)) . ' KB';
$isDocx     = ! empty($data['docx']);
$isPptx     = ! empty($data['pptx']);
$isPdf      = ! empty($data['pdf']);
$canEdit    = ! empty($data['canEdit']);
$convSrc    = $data['convSrc'] ?? null;
$hasConvPdf = ! empty($convSrc);
?>
<section class="ld fv <?= $isDocx ? 'fv-has-docx' : '' ?> <?= $isPptx ? 'fv-has-pptx' : '' ?> <?= $isPdf ? 'fv-has-pdf' : '' ?>" id="fv" data-pdf="<?= $data['pdf'] ? '1' : '0' ?>" data-ext="<?= esc($f['ext'], 'attr') ?>">
    <div class="ld-bar glass fv-bar">
        <div class="ld-bar-l">
            <div class="ld-title">
                <a class="vd-back" id="fv-back" href="<?= esc($data['backUrl'], 'attr') ?>" aria-label="Back to the folder">&#8249; <?= $f['folder'] === '' ? esc($reg['code']) : esc(basename($f['folder'])) ?></a>
                <b><?= esc($f['name']) ?></b>
                <?php if ($isDocx): ?>
                    <span class="ld-tag-badge" style="background:rgba(66,133,244,.15); color:#1a73e8; border:1px solid rgba(66,133,244,.3); border-radius:6px; padding:2px 8px; font-size:11.5px; font-weight:600; margin-left:8px;">GOOGLE WORD / DOCX</span>
                <?php elseif ($isPptx): ?>
                    <span class="ld-tag-badge" style="background:rgba(249,115,22,.15); color:#ea580c; border:1px solid rgba(249,115,22,.3); border-radius:6px; padding:2px 8px; font-size:11.5px; font-weight:600; margin-left:8px;">POWERPOINT PRESENTATION</span>
                <?php endif ?>
            </div>
            <div class="ld-meta"><?= esc($reg['code']) ?><?= $f['folder'] !== '' ? ' &rsaquo; ' . esc($f['folder']) : '' ?> &middot; <?= esc($size) ?><?= $f['date'] ? ' &middot; ' . esc($f['date']) : '' ?><?= $f['of'] > 1 ? ' &middot; ' . (int) $f['at'] . ' of ' . (int) $f['of'] : '' ?></div>
        </div>
        <div class="ld-bar-r">
            <div class="ld-tools">
                <?php if ($isDocx): ?>
                    <?php if ($canEdit): ?>
                        <button type="button" class="ld-btn ld-primary" id="fv-docx-toggle-edit" title="Make document editable in Google Word / Docs editor">
                            <svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true" style="vertical-align:-1px;margin-right:4px;"><path d="M11.013 1.427a1.75 1.75 0 0 1 2.474 0l1.086 1.086a1.75 1.75 0 0 1 0 2.474l-8.61 8.61c-.21.21-.47.364-.756.445l-3.251.93a.75.75 0 0 1-.927-.928l.929-3.25c.081-.286.235-.547.445-.758l8.61-8.61Zm1.414 1.06a.25.25 0 0 0-.354 0L10.811 3.75l1.439 1.44 1.263-1.263a.25.25 0 0 0 0-.354l-1.086-1.086ZM11.189 6.25 9.75 4.81 3.292 11.268a.75.75 0 0 0-.175.297l-.62 2.17 2.17-.62a.75.75 0 0 0 .298-.175L11.189 6.25Z" fill="currentColor"/></svg>
                            Edit
                        </button>
                        <button type="button" class="ld-btn" id="fv-docx-cancel-edit" style="display:none;" title="Exit editing and view original file">&#10005; View Original</button>
                        <button type="button" class="ld-btn ld-btn-save ld-save-primary" id="fv-docx-save" data-url="<?= esc($data['saveDocxUrl'], 'attr') ?>" style="display:none;">&#128190; Save Document</button>
                    <?php endif ?>
                    <button type="button" class="ld-btn" id="fv-fs-btn" title="Fullscreen (F11 / Esc to exit)">&#9974; Full Screen</button>

                <?php elseif ($isPptx): ?>
                    <?php if ($canEdit): ?>
                        <button type="button" class="ld-btn ld-primary" id="fv-ppt-mode-edit" title="Make slides and bullet points editable">
                            <svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true" style="vertical-align:-1px;margin-right:4px;"><path d="M11.013 1.427a1.75 1.75 0 0 1 2.474 0l1.086 1.086a1.75 1.75 0 0 1 0 2.474l-8.61 8.61c-.21.21-.47.364-.756.445l-3.251.93a.75.75 0 0 1-.927-.928l.929-3.25c.081-.286.235-.547.445-.758l8.61-8.61Zm1.414 1.06a.25.25 0 0 0-.354 0L10.811 3.75l1.439 1.44 1.263-1.263a.25.25 0 0 0 0-.354l-1.086-1.086ZM11.189 6.25 9.75 4.81 3.292 11.268a.75.75 0 0 0-.175.297l-.62 2.17 2.17-.62a.75.75 0 0 0 .298-.175L11.189 6.25Z" fill="currentColor"/></svg>
                            Edit
                        </button>
                        <button type="button" class="ld-btn" id="fv-ppt-mode-present" style="display:none;" title="Exit editing and view presentation">&#9654; View Original (Present)</button>
                        <button type="button" class="ld-btn ld-btn-save ld-save-primary" id="fv-ppt-save" data-url="<?= esc($data['savePptUrl'], 'attr') ?>" style="display:none;">&#128190; Save Presentation</button>
                    <?php endif ?>
                    <button type="button" class="ld-btn" id="fv-fs-btn" title="Fullscreen (F11 / Esc to exit)">&#9974; Full Screen</button>

                <?php elseif ($isPdf): ?>
                    <?php if ($canEdit): ?>
                        <button type="button" class="ld-btn ld-primary" id="fv-pdf-toggle-edit" title="Annotate or add markings to this PDF">
                            <svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true" style="vertical-align:-1px;margin-right:4px;"><path d="M11.013 1.427a1.75 1.75 0 0 1 2.474 0l1.086 1.086a1.75 1.75 0 0 1 0 2.474l-8.61 8.61c-.21.21-.47.364-.756.445l-3.251.93a.75.75 0 0 1-.927-.928l.929-3.25c.081-.286.235-.547.445-.758l8.61-8.61Zm1.414 1.06a.25.25 0 0 0-.354 0L10.811 3.75l1.439 1.44 1.263-1.263a.25.25 0 0 0 0-.354l-1.086-1.086ZM11.189 6.25 9.75 4.81 3.292 11.268a.75.75 0 0 0-.175.297l-.62 2.17 2.17-.62a.75.75 0 0 0 .298-.175L11.189 6.25Z" fill="currentColor"/></svg>
                            Edit
                        </button>
                        <button type="button" class="ld-btn" id="fv-pdf-cancel-edit" style="display:none;" title="Exit editing and view original PDF">&#10005; View Original</button>
                        <button type="button" class="ld-btn ld-btn-save ld-save-primary" id="fv-pdf-save" data-url="<?= esc($data['savePdfUrl'], 'attr') ?>" style="display:none;">&#128190; Save Changes</button>
                    <?php endif ?>
                    <button type="button" class="ld-btn" id="fv-fs-btn" title="Fullscreen">&#9974; Full Screen</button>
                <?php endif ?>

                <?php if ($data['prev'] || $data['next']): ?>
                    <span class="bk-zoom" role="group" aria-label="Files of this folder">
                        <a class="ld-btn<?= $data['prev'] ? '' : ' is-off' ?>" id="fv-prev" <?= $data['prev'] ? 'href="' . esc($data['prev'], 'attr') . '"' : 'aria-disabled="true"' ?> title="Previous file (&#8592;)" aria-label="Previous file">&#8249;</a>
                        <a class="ld-btn<?= $data['next'] ? '' : ' is-off' ?>" id="fv-next" <?= $data['next'] ? 'href="' . esc($data['next'], 'attr') . '"' : 'aria-disabled="true"' ?> title="Next file (&#8594;)" aria-label="Next file">&#8250;</a>
                    </span>
                <?php endif ?>
                <button type="button" class="ld-btn" id="fv-print">Print</button>
                <a class="ld-btn" href="<?= esc($data['download'], 'attr') ?>">Download</a>
                <a class="ld-btn" href="<?= esc($data['src'], 'attr') ?>" target="_blank" rel="noopener" title="The original file, in a new tab">Open original</a>
            </div>
        </div>
    </div>

    <!-- PDF Annotation Toolbar Ribbon (Hidden until Edit is clicked) -->
    <?php if ($isPdf && $canEdit): ?>
        <div class="ld-ribbon pdf-ribbon glass" id="pdf-ribbon" style="display:none;">
            <div class="docx-ribbon-group">
                <button type="button" class="docx-btn is-active" id="pdf-tool-select" title="Browse / Select">&#128070; Browse</button>
                <button type="button" class="docx-btn" id="pdf-tool-text" title="Click anywhere to type text">&#128228; Add Text</button>
                <button type="button" class="docx-btn" id="pdf-tool-pen" title="Freehand draw or sign">&#9997; Pen</button>
                <button type="button" class="docx-btn" id="pdf-tool-highlight" title="Highlight">&#128394; Highlight</button>
            </div>
            <div class="docx-ribbon-sep"></div>
            <div class="docx-ribbon-group">
                <span class="pdf-stamp-btn pass" data-stamp="PASSED" title="Add PASSED stamp">&#10004; PASSED</span>
                <span class="pdf-stamp-btn approve" data-stamp="APPROVED" title="Add APPROVED stamp">&#9733; APPROVED</span>
                <span class="pdf-stamp-btn fail" data-stamp="FAILED" title="Add FAILED stamp">&#10006; FAILED</span>
                <span class="pdf-stamp-btn revise" data-stamp="REVISED" title="Add REVISED stamp">&#8634; REVISED</span>
            </div>
            <div class="docx-ribbon-sep"></div>
            <div class="docx-ribbon-group">
                <button type="button" class="docx-btn" id="pdf-clear-all" title="Clear All Annotations">&#10005; Clear Annotations</button>
            </div>
        </div>
    <?php endif ?>

    <div class="ld-stage fv-stage" id="fv-stage" data-ld-stage>
        <?php if ($isDocx): ?>
            <!-- 1. Original View (Shown by default: exact original file view) -->
            <div class="fv-orig-doc-wrap" id="fv-docx-orig-view">
                <?php if ($hasConvPdf): ?>
                    <iframe class="fv-pdf fv-orig-frame" id="fv-docx-orig-frame" title="<?= esc($f['name'], 'attr') ?>" src="<?= esc($convSrc, 'attr') ?>#view=FitH"></iframe>
                <?php else: ?>
                    <div class="gdoc-read-view">
                        <div class="gdoc-page-paper gdoc-read-paper">
                            <?= $data['docx']['html'] ?>
                        </div>
                    </div>
                <?php endif ?>
            </div>

            <!-- 2. Google Word / Google Docs Interactive Studio (Shown on Edit click) -->
            <div class="gdoc-studio" id="fv-docx-edit-view" style="display:none;">
                <!-- Google Docs Header -->
                <div class="gdoc-header">
                    <div class="gdoc-header-l">
                        <div class="gdoc-app-logo" title="Google Word / Google Docs Editor">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="#4285f4"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/><path d="M8 12h8v1.5H8zm0 3h8v1.5H8zm0-6h5v1.5H8z" fill="#fff"/></svg>
                        </div>
                        <div class="gdoc-info-col">
                            <div class="gdoc-title-row">
                                <span class="gdoc-title-text"><?= esc($f['name']) ?></span>
                                <span class="gdoc-save-indicator" id="gdoc-save-status"><span style="color:#16a34a">&#9729;&#10004;</span> Ready to edit in Google Word</span>
                            </div>
                            <div class="gdoc-menu-row">
                                <span class="gdoc-menu-btn">File</span>
                                <span class="gdoc-menu-btn">Edit</span>
                                <span class="gdoc-menu-btn">View</span>
                                <span class="gdoc-menu-btn">Insert</span>
                                <span class="gdoc-menu-btn">Format</span>
                                <span class="gdoc-menu-btn">Tools</span>
                                <span class="gdoc-menu-btn">Help</span>
                            </div>
                        </div>
                    </div>
                    <div class="gdoc-header-r">
                        <a class="gdoc-online-link" href="https://docs.google.com/viewer?url=<?= rawurlencode(site_url('labdocs/' . rawurlencode($reg['code']) . '/f/' . $f['id'])) ?>" target="_blank" rel="noopener" title="Open directly in Google Docs online viewer">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            Open in Google Docs
                        </a>
                    </div>
                </div>

                <!-- Google Docs Ribbon Toolbar -->
                <div class="gdoc-ribbon-bar" id="docx-ribbon">
                    <div class="gdoc-ribbon-inner">
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="undo" title="Undo (Ctrl+Z)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg></button>
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="redo" title="Redo (Ctrl+Y)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13"/></svg></button>
                        <button type="button" class="gdoc-btn" id="gdoc-print-action" title="Print (Ctrl+P)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></button>
                        <div class="gdoc-sep"></div>

                        <!-- Zoom -->
                        <select id="gdoc-zoom-select" class="gdoc-select" title="Zoom">
                            <option value="1">100%</option>
                            <option value="0.75">75%</option>
                            <option value="0.9">90%</option>
                            <option value="1.25">125%</option>
                            <option value="1.5">150%</option>
                        </select>
                        <div class="gdoc-sep"></div>

                        <!-- Styles / Headings -->
                        <select id="docx-format-block" class="gdoc-select" style="min-width:110px" title="Styles">
                            <option value="p">Normal text</option>
                            <option value="h1">Heading 1</option>
                            <option value="h2">Heading 2</option>
                            <option value="h3">Heading 3</option>
                        </select>

                        <!-- Font -->
                        <select id="gdoc-font-family" class="gdoc-select" style="min-width:115px" title="Font">
                            <option value="Arial, sans-serif" selected>Arial</option>
                            <option value="Roboto, sans-serif">Roboto</option>
                            <option value="Calibri, sans-serif">Calibri</option>
                            <option value="'Times New Roman', serif">Times New Roman</option>
                            <option value="Georgia, serif">Georgia</option>
                            <option value="Verdana, sans-serif">Verdana</option>
                            <option value="'Courier New', monospace">Courier New</option>
                        </select>

                        <!-- Font Size -->
                        <div class="gdoc-size-ctrl">
                            <button type="button" class="gdoc-size-btn" id="gdoc-size-dec" title="Decrease font size">-</button>
                            <input type="text" id="gdoc-size-val" class="gdoc-size-box" value="11" readonly>
                            <button type="button" class="gdoc-size-btn" id="gdoc-size-inc" title="Increase font size">+</button>
                        </div>
                        <div class="gdoc-sep"></div>

                        <!-- Text Formatting -->
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="bold" title="Bold (Ctrl+B)"><b>B</b></button>
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="italic" title="Italic (Ctrl+I)"><i>I</i></button>
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="underline" title="Underline (Ctrl+U)"><u>U</u></button>
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>

                        <!-- Colors -->
                        <label class="gdoc-color-picker-label" title="Text color">
                            <span class="gdoc-color-preview-text">A<span class="gdoc-bar-color" id="gdoc-fore-bar" style="background:#0f172a"></span></span>
                            <input type="color" id="docx-fore-color" value="#0f172a">
                        </label>
                        <label class="gdoc-color-picker-label" title="Highlight color">
                            <span class="gdoc-color-preview-text">&#128394;<span class="gdoc-bar-color" id="gdoc-back-bar" style="background:#fef08a"></span></span>
                            <input type="color" id="docx-back-color" value="#fef08a">
                        </label>
                        <div class="gdoc-sep"></div>

                        <!-- Alignments -->
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="justifyLeft" title="Align Left">&#9207;</button>
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="justifyCenter" title="Align Center">&equiv;</button>
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="justifyRight" title="Align Right">&#9206;</button>
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="justifyFull" title="Justify">&#9776;</button>
                        <div class="gdoc-sep"></div>

                        <!-- Lists & Tables -->
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="insertUnorderedList" title="Bulleted list">&bull;&equiv;</button>
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="insertOrderedList" title="Numbered list">1&equiv;</button>
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="outdent" title="Decrease indent">&#8676;</button>
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="indent" title="Increase indent">&#8677;</button>
                        <button type="button" class="gdoc-btn" id="docx-insert-table" title="Insert Table">&#9638; Table</button>
                        <button type="button" class="gdoc-btn docx-btn" data-cmd="removeFormat" title="Clear formatting">&#10005;</button>
                    </div>
                </div>

                <!-- Google Docs Canvas Area -->
                <div class="gdoc-canvas-area" id="docx-wrapper">
                    <div class="gdoc-page-sheet" id="docx-canvas" contenteditable="true" spellcheck="true" data-placeholder="Start typing your document...">
                        <?= $data['docx']['html'] ?>
                    </div>
                </div>
            </div>

        <?php elseif ($isPptx): ?>
            <!-- PPTX Interactive Presentation & Editing Studio -->
            <div class="pptx-studio" id="pptx-studio" data-slides="<?= esc(json_encode($data['pptx']['slides'], JSON_UNESCAPED_UNICODE), 'attr') ?>">
                <!-- Present Mode (Slideshow - default view) -->
                <div class="pptx-present-mode" id="pptx-present-mode">
                    <div class="pptx-slide-canvas" id="pptx-present-canvas">
                        <div class="pptx-slide-inner">
                            <h2 class="pptx-slide-title" id="pptx-present-title">Slide Title</h2>
                            <div class="pptx-slide-body" id="pptx-present-body">
                                <!-- Bullets render here -->
                            </div>
                        </div>
                    </div>
                    <!-- Presenter Controls Dock -->
                    <div class="pptx-dock glass">
                        <button type="button" class="ld-btn" id="pptx-prev-btn" title="Previous Slide (&#8592; / Space)">&#8249; Previous</button>
                        <span class="pptx-counter" id="pptx-counter">Slide 1 of <?= count($data['pptx']['slides']) ?></span>
                        <button type="button" class="ld-btn" id="pptx-next-btn" title="Next Slide (&#8594; / Enter)">Next &#8250;</button>
                        <div class="docx-ribbon-sep"></div>
                        <button type="button" class="ld-btn" id="pptx-notes-toggle" title="Show Speaker Notes">&#128221; Notes</button>
                        <button type="button" class="ld-btn" id="pptx-present-fs" title="Presentation Fullscreen">&#9974; Fullscreen</button>
                    </div>
                    <div class="pptx-notes-drawer glass" id="pptx-notes-drawer" style="display:none;">
                        <b>Speaker Notes:</b>
                        <div id="pptx-notes-content" style="margin-top:6px; color:#475569; font-size:13px; line-height:1.5;"></div>
                    </div>
                </div>

                <!-- Slide Editor Mode (Active only when Edit is clicked) -->
                <div class="pptx-edit-mode" id="pptx-edit-mode" style="display:none;">
                    <div class="pptx-editor-layout">
                        <!-- Left Slides Sidebar -->
                        <div class="pptx-slides-nav" id="pptx-slides-nav">
                            <div class="pptx-slides-nav-header">
                                <span>SLIDES</span>
                                <?php if ($canEdit): ?>
                                    <button type="button" class="ld-btn ld-btn-sm" id="pptx-add-slide-btn" title="Add New Slide">+ Slide</button>
                                <?php endif ?>
                            </div>
                            <div class="pptx-thumb-list" id="pptx-thumb-list"></div>
                        </div>
                        <!-- Center Active Slide Canvas -->
                        <div class="pptx-edit-center">
                            <div class="pptx-slide-card">
                                <div class="pptx-edit-field-group">
                                    <label class="pptx-label">SLIDE TITLE</label>
                                    <input type="text" class="pptx-title-input" id="pptx-edit-title" placeholder="Slide Title..." <?= $canEdit ? '' : 'readonly' ?>>
                                </div>
                                <div class="pptx-edit-field-group" style="flex:1;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                        <label class="pptx-label">BULLET POINTS / CONTENT</label>
                                        <?php if ($canEdit): ?>
                                            <button type="button" class="ld-btn ld-btn-sm" id="pptx-add-bullet-btn">+ Bullet</button>
                                        <?php endif ?>
                                    </div>
                                    <div class="pptx-bullets-editor" id="pptx-bullets-editor"></div>
                                </div>
                                <div class="pptx-edit-field-group">
                                    <label class="pptx-label">SPEAKER NOTES</label>
                                    <textarea class="pptx-notes-input" id="pptx-edit-notes" placeholder="Speaker notes for this slide..." rows="3" <?= $canEdit ? '' : 'readonly' ?>></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($data['text']): ?>
            <div class="fv-text" id="fv-text">
                <?php if ($data['text']['kind'] === 'csv'): ?>
                    <table class="fv-csv"><tbody>
                        <?php foreach ($data['text']['rows'] as $ri => $row): ?><tr><?php foreach ($row as $c): ?><<?= $ri === 0 ? 'th' : 'td' ?>><?= esc($c) ?></<?= $ri === 0 ? 'th' : 'td' ?>><?php endforeach ?></tr><?php endforeach ?>
                    </tbody></table>
                <?php else: ?><pre><?= esc($data['text']['raw'] ?? '') ?></pre><?php endif ?>
            </div>
        <?php elseif ($data['pdf']): ?>
            <div class="pdf-container-wrap" id="pdf-container-wrap">
                <iframe class="fv-pdf" id="fv-frame" title="<?= esc($f['name'], 'attr') ?>" src="<?= esc($data['src'], 'attr') ?>#view=FitH"></iframe>
                <div class="pdf-annotate-overlay" id="pdf-annotate-overlay" style="display:none;">
                    <canvas id="pdf-draw-canvas"></canvas>
                    <div id="pdf-text-layer"></div>
                </div>
            </div>
        <?php elseif (! empty($data['html'])): ?>
            <div class="fv-html-wrap" style="width:100%;height:calc(100vh - 120px);background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                <iframe srcdoc="<?= esc($data['html'], 'attr') ?>" style="width:100%;height:100%;border:none;" sandbox="allow-same-origin allow-scripts"></iframe>
            </div>
        <?php elseif (in_array(strtolower($f['ext'] ?? ''), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true)): ?>
            <div class="fv-img" id="fv-img"><img id="fv-pic" src="<?= esc($data['src'], 'attr') ?>" alt="<?= esc($f['name'], 'attr') ?>"></div>
        <?php else: ?>
            <div class="fv-file-card glass" style="max-width:560px;margin:40px auto;padding:36px 30px;text-align:center;border-radius:12px;background:rgba(255,255,255,0.95);box-shadow:0 4px 20px rgba(0,0,0,0.06);border:1px solid rgba(148,163,184,0.25);">
                <div style="width:68px;height:68px;margin:0 auto 16px;border-radius:14px;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;color:#2563eb;">
                    <svg viewBox="0 0 24 24" width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM14 3v5h5M9 13h6M9 17h6M9 9h2"/></svg>
                </div>
                <h3 style="font-size:18px;font-weight:700;color:#0f172a;margin:0 0 6px;word-break:break-word;"><?= esc($f['name']) ?></h3>
                <p style="font-size:13px;color:#64748b;margin:0 0 24px;"><?= strtoupper(esc($f['ext'])) ?> Document &middot; <?= esc($size) ?><?= $f['date'] ? ' &middot; ' . esc($f['date']) : '' ?></p>
                <div style="display:flex;gap:12px;justify-content:center;">
                    <a class="ld-btn ld-primary" href="<?= esc($data['src'], 'attr') ?>" target="_blank" rel="noopener">Open Original</a>
                    <a class="ld-btn" href="<?= esc($data['download'], 'attr') ?>">Download</a>
                </div>
            </div>
        <?php endif ?>
    </div>
</section>
<?= view('labdocs/_revs', ['labRevs' => $labRevs ?? null, 'labCollab' => $labCollab ?? null]) ?>
