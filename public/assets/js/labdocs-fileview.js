/* Interactive File Viewer: Google Word / DOCX Processor, PPTX Presentation & Slide Editor, PDF Annotation, Zoom, Print, and Navigation */
(function () {
  'use strict';

  var root = document.getElementById('fv');
  if (!root) return;

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]') || document.querySelector('input[name="csrf_test_name"]');
    return meta ? (meta.content || meta.value) : (window.NEXUS_CSRF || '');
  }

  function setCsrfToken(val) {
    if (!val) return;
    window.NEXUS_CSRF = val;
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.content = val;
    var inp = document.querySelector('input[name="csrf_test_name"]');
    if (inp) inp.value = val;
  }

  /* -------------------------------------------------------------
     1. Base Navigation, Zoom & Print
     ------------------------------------------------------------- */
  var back = document.getElementById('fv-back');
  if (back) {
    back.addEventListener('click', function (e) {
      try {
        var ref = document.referrer && new URL(document.referrer);
        var to = new URL(back.href, location.href);
        if (ref && ref.origin === location.origin && ref.pathname === to.pathname && ref.search === to.search && history.length > 1) {
          e.preventDefault();
          history.back();
        }
      } catch (x) {}
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.target && /input|textarea|select/i.test(e.target.tagName)) return;
    if (e.target && e.target.isContentEditable) return;
    var a = e.key === 'ArrowLeft' ? document.getElementById('fv-prev') : e.key === 'ArrowRight' ? document.getElementById('fv-next') : null;
    if (a && a.href) location.href = a.href;
  });

  var pic = document.getElementById('fv-pic'), box = document.getElementById('fv-img');
  if (pic && box && window.LdZoom) {
    var stage = document.getElementById('fv-stage'), z;
    z = LdZoom(stage, box, function () { return pic.naturalWidth || 1; });
    if (pic.complete && pic.naturalWidth) z.fit(); else pic.addEventListener('load', function () { z.fit(); });
  }

  var printBtn = document.getElementById('fv-print');
  if (printBtn) {
    printBtn.onclick = function () {
      var fr = document.getElementById('fv-frame') || document.getElementById('fv-docx-orig-frame');
      if (fr && fr.contentWindow) {
        try { fr.contentWindow.focus(); fr.contentWindow.print(); return; } catch (e) {}
      }
      window.print();
    };
  }

  /* -------------------------------------------------------------
     2. Fullscreen Management
     ------------------------------------------------------------- */
  var fsBtn = document.getElementById('fv-fs-btn');
  function toggleFullScreen(elem) {
    var target = elem || root;
    if (!document.fullscreenElement) {
      if (target.requestFullscreen) {
        target.requestFullscreen();
      } else if (target.webkitRequestFullscreen) {
        target.webkitRequestFullscreen();
      } else if (target.msRequestFullscreen) {
        target.msRequestFullscreen();
      }
      target.classList.add('is-fullscreen');
      if (fsBtn) fsBtn.innerHTML = '&#9974; Exit Fullscreen';
    } else {
      if (document.exitFullscreen) {
        document.exitFullscreen();
      }
      target.classList.remove('is-fullscreen');
      if (fsBtn) fsBtn.innerHTML = '&#9974; Full Screen';
    }
  }

  if (fsBtn) {
    fsBtn.addEventListener('click', function () {
      toggleFullScreen(root);
    });
  }

  document.addEventListener('fullscreenchange', function () {
    if (!document.fullscreenElement) {
      root.classList.remove('is-fullscreen');
      if (fsBtn) fsBtn.innerHTML = '&#9974; Full Screen';
    }
  });

  /* -------------------------------------------------------------
     3. DOCX / Google Word Interactive Word Processor
     ------------------------------------------------------------- */
  var docxToggleEdit = document.getElementById('fv-docx-toggle-edit');
  var docxCancelEdit = document.getElementById('fv-docx-cancel-edit');
  var docxSaveBtn    = document.getElementById('fv-docx-save');
  var docxOrigView   = document.getElementById('fv-docx-orig-view');
  var docxEditView   = document.getElementById('fv-docx-edit-view');
  var docxCanvas     = document.getElementById('docx-canvas');
  var docxRibbon     = document.getElementById('docx-ribbon');

  if (docxToggleEdit && docxEditView) {
    docxToggleEdit.addEventListener('click', function () {
      if (docxOrigView) docxOrigView.style.display = 'none';
      docxEditView.style.display = 'flex';
      docxToggleEdit.style.display = 'none';
      if (docxCancelEdit) docxCancelEdit.style.display = 'inline-flex';
      if (docxSaveBtn) docxSaveBtn.style.display = 'inline-flex';
      if (docxCanvas) {
        docxCanvas.setAttribute('contenteditable', 'true');
        docxCanvas.focus();
      }
    });
  }

  if (docxCancelEdit && docxEditView) {
    docxCancelEdit.addEventListener('click', function () {
      if (docxOrigView) docxOrigView.style.display = '';
      docxEditView.style.display = 'none';
      if (docxToggleEdit) docxToggleEdit.style.display = 'inline-flex';
      docxCancelEdit.style.display = 'none';
      if (docxSaveBtn) docxSaveBtn.style.display = 'none';
    });
  }

  if (docxCanvas && docxRibbon) {
    // Toolbar command buttons
    docxRibbon.addEventListener('click', function (e) {
      var btn = e.target.closest('.docx-btn');
      if (!btn) return;
      var cmd = btn.getAttribute('data-cmd');
      if (cmd) {
        e.preventDefault();
        document.execCommand(cmd, false, null);
        docxCanvas.focus();
      }
    });

    // Format block (headings / paragraph)
    var fmtSelect = document.getElementById('docx-format-block');
    if (fmtSelect) {
      fmtSelect.addEventListener('change', function () {
        var tag = fmtSelect.value;
        document.execCommand('formatBlock', false, tag);
        docxCanvas.focus();
      });
    }

    // Font Family selector
    var fontSelect = document.getElementById('gdoc-font-family');
    if (fontSelect) {
      fontSelect.addEventListener('change', function () {
        document.execCommand('fontName', false, fontSelect.value);
        docxCanvas.focus();
      });
    }

    // Font Size Controls
    var sizeVal = document.getElementById('gdoc-size-val');
    var sizeInc = document.getElementById('gdoc-size-inc');
    var sizeDec = document.getElementById('gdoc-size-dec');
    if (sizeVal && sizeInc && sizeDec) {
      sizeInc.addEventListener('click', function () {
        var s = Math.min(72, parseInt(sizeVal.value || '11', 10) + 1);
        sizeVal.value = s;
        document.execCommand('fontSize', false, '4');
        docxCanvas.focus();
      });
      sizeDec.addEventListener('click', function () {
        var s = Math.max(8, parseInt(sizeVal.value || '11', 10) - 1);
        sizeVal.value = s;
        document.execCommand('fontSize', false, '2');
        docxCanvas.focus();
      });
    }

    // Zoom
    var zoomSel = document.getElementById('gdoc-zoom-select');
    if (zoomSel && docxCanvas) {
      zoomSel.addEventListener('change', function () {
        docxCanvas.style.transform = 'scale(' + zoomSel.value + ')';
        docxCanvas.style.transformOrigin = 'top center';
      });
    }

    // Print shortcut
    var gdocPrint = document.getElementById('gdoc-print-action');
    if (gdocPrint) {
      gdocPrint.addEventListener('click', function () {
        window.print();
      });
    }

    // Colors
    var foreColor = document.getElementById('docx-fore-color');
    var foreBar   = document.getElementById('gdoc-fore-bar');
    if (foreColor) {
      foreColor.addEventListener('input', function () {
        if (foreBar) foreBar.style.background = foreColor.value;
        document.execCommand('foreColor', false, foreColor.value);
        docxCanvas.focus();
      });
    }
    var backColor = document.getElementById('docx-back-color');
    var backBar   = document.getElementById('gdoc-back-bar');
    if (backColor) {
      backColor.addEventListener('input', function () {
        if (backBar) backBar.style.background = backColor.value;
        document.execCommand('hiliteColor', false, backColor.value);
        docxCanvas.focus();
      });
    }

    // Insert Table
    var tableBtn = document.getElementById('docx-insert-table');
    if (tableBtn) {
      tableBtn.addEventListener('click', function () {
        var tblHtml = '<table class="docx-table" style="width:100%; border-collapse:collapse; margin:14px 0;"><tbody>' +
          '<tr><th style="border:1px solid #cbd5e1; padding:8px 12px; background:#f1f5f9;"><b>Header 1</b></th><th style="border:1px solid #cbd5e1; padding:8px 12px; background:#f1f5f9;"><b>Header 2</b></th></tr>' +
          '<tr><td style="border:1px solid #cbd5e1; padding:8px 12px;">Cell 1</td><td style="border:1px solid #cbd5e1; padding:8px 12px;">Cell 2</td></tr>' +
          '</tbody></table><p><br></p>';
        document.execCommand('insertHTML', false, tblHtml);
        docxCanvas.focus();
      });
    }

    // Save DOCX
    if (docxSaveBtn) {
      docxSaveBtn.addEventListener('click', function () {
        var url = docxSaveBtn.getAttribute('data-url');
        if (!url) return;
        var originalText = docxSaveBtn.innerHTML;
        docxSaveBtn.disabled = true;
        docxSaveBtn.innerHTML = '&#9203; Saving...';

        var cleanHtml = docxCanvas.innerHTML;
        var csrf = getCsrfToken();

        fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf
          },
          body: JSON.stringify({ html: cleanHtml, csrf_test_name: csrf })
        })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data.csrf) setCsrfToken(data.csrf);
            if (data.ok) {
              docxSaveBtn.innerHTML = '&#10004; Saved!';
              var stat = document.getElementById('gdoc-save-status');
              if (stat) stat.innerHTML = '<span style="color:#16a34a">&#9729;&#10004;</span> Changes saved to NEXUS-L';
              setTimeout(function () {
                docxSaveBtn.disabled = false;
                docxSaveBtn.innerHTML = originalText;
              }, 1800);
            } else {
              alert(data.message || 'Error saving document.');
              docxSaveBtn.disabled = false;
              docxSaveBtn.innerHTML = originalText;
            }
          })
          .catch(function (err) {
            alert('Failed to save document. Please check connection.');
            docxSaveBtn.disabled = false;
            docxSaveBtn.innerHTML = originalText;
          });
      });
    }
  }

  /* -------------------------------------------------------------
     4. PPTX Presentation & Slide Editor Studio
     ------------------------------------------------------------- */
  var pptxStudio = document.getElementById('pptx-studio');
  if (pptxStudio) {
    var rawSlides = pptxStudio.getAttribute('data-slides');
    var slides = [];
    try {
      slides = JSON.parse(rawSlides) || [];
    } catch (e) {
      slides = [{ id: 1, title: 'Presentation', content: ['No slides loaded.'], notes: '' }];
    }
    if (!slides.length) {
      slides = [{ id: 1, title: 'New Slide', content: ['Start typing points here...'], notes: '' }];
    }

    var curIndex = 0;
    var isEditMode = false;

    var presentView = document.getElementById('pptx-present-mode');
    var editView = document.getElementById('pptx-edit-mode');
    var modePresentBtn = document.getElementById('fv-ppt-mode-present');
    var modeEditBtn = document.getElementById('fv-ppt-mode-edit');
    var pptSaveBtn = document.getElementById('fv-ppt-save');

    // Presentation elements
    var presTitle = document.getElementById('pptx-present-title');
    var presBody = document.getElementById('pptx-present-body');
    var presCounter = document.getElementById('pptx-counter');
    var presPrev = document.getElementById('pptx-prev-btn');
    var presNext = document.getElementById('pptx-next-btn');
    var presNotesToggle = document.getElementById('pptx-notes-toggle');
    var presNotesDrawer = document.getElementById('pptx-notes-drawer');
    var presNotesContent = document.getElementById('pptx-notes-content');
    var presFs = document.getElementById('pptx-present-fs');

    // Editor elements
    var thumbList = document.getElementById('pptx-thumb-list');
    var editTitle = document.getElementById('pptx-edit-title');
    var editBullets = document.getElementById('pptx-bullets-editor');
    var editNotes = document.getElementById('pptx-edit-notes');
    var addSlideBtn = document.getElementById('pptx-add-slide-btn');
    var addBulletBtn = document.getElementById('pptx-add-bullet-btn');

    function renderPresentSlide() {
      if (!slides[curIndex]) return;
      var s = slides[curIndex];
      if (presTitle) presTitle.textContent = s.title || 'Slide ' + (curIndex + 1);
      if (presBody) {
        presBody.innerHTML = '';
        var list = s.content || [];
        if (!list.length) list = ['(Empty slide)'];
        list.forEach(function (pt) {
          var item = document.createElement('div');
          item.className = 'pptx-bullet-item';
          item.innerHTML = '<span class="pptx-bullet-icon">&#9658;</span><span>' + (pt || '') + '</span>';
          presBody.appendChild(item);
        });
      }
      if (presCounter) presCounter.textContent = 'Slide ' + (curIndex + 1) + ' of ' + slides.length;
      if (presNotesContent) presNotesContent.textContent = s.notes || '(No notes for this slide)';
    }

    function renderEditorThumbs() {
      if (!thumbList) return;
      thumbList.innerHTML = '';
      slides.forEach(function (s, idx) {
        var card = document.createElement('div');
        card.className = 'pptx-thumb-card' + (idx === curIndex ? ' is-active' : '');
        card.innerHTML = '<span class="pptx-thumb-num">' + (idx + 1) + '</span><span class="pptx-thumb-title">' + (s.title || 'Slide ' + (idx + 1)) + '</span>';
        card.addEventListener('click', function () {
          saveCurrentEditorState();
          curIndex = idx;
          renderEditorThumbs();
          loadSlideToEditor();
        });
        thumbList.appendChild(card);
      });
    }

    function renderBulletEditorItems() {
      if (!editBullets || !slides[curIndex]) return;
      editBullets.innerHTML = '';
      var list = slides[curIndex].content || [];
      list.forEach(function (pt, pIdx) {
        var row = document.createElement('div');
        row.className = 'pptx-bullet-row';

        var dot = document.createElement('span');
        dot.className = 'pptx-bullet-dot';
        dot.innerHTML = '&bull;';

        var inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'pptx-bullet-input';
        inp.value = pt;
        inp.addEventListener('input', function () {
          slides[curIndex].content[pIdx] = inp.value;
        });

        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'pptx-bullet-del';
        del.title = 'Remove point';
        del.innerHTML = '&times;';
        del.addEventListener('click', function () {
          slides[curIndex].content.splice(pIdx, 1);
          renderBulletEditorItems();
        });

        row.appendChild(dot);
        row.appendChild(inp);
        row.appendChild(del);
        editBullets.appendChild(row);
      });
    }

    function loadSlideToEditor() {
      if (!slides[curIndex]) return;
      var s = slides[curIndex];
      if (editTitle) editTitle.value = s.title || '';
      if (editNotes) editNotes.value = s.notes || '';
      renderBulletEditorItems();
    }

    function saveCurrentEditorState() {
      if (!slides[curIndex]) return;
      if (editTitle) slides[curIndex].title = editTitle.value;
      if (editNotes) slides[curIndex].notes = editNotes.value;
    }

    // Mode Toggle
    if (modePresentBtn) {
      modePresentBtn.addEventListener('click', function () {
        saveCurrentEditorState();
        isEditMode = false;
        editView.style.display = 'none';
        presentView.style.display = 'flex';
        modePresentBtn.style.display = 'none';
        if (modeEditBtn) modeEditBtn.style.display = 'inline-flex';
        if (pptSaveBtn) pptSaveBtn.style.display = 'none';
        renderPresentSlide();
      });
    }

    if (modeEditBtn) {
      modeEditBtn.addEventListener('click', function () {
        isEditMode = true;
        presentView.style.display = 'none';
        editView.style.display = 'flex';
        modeEditBtn.style.display = 'none';
        if (modePresentBtn) modePresentBtn.style.display = 'inline-flex';
        if (pptSaveBtn) pptSaveBtn.style.display = 'inline-flex';
        renderEditorThumbs();
        loadSlideToEditor();
      });
    }

    // Presentation navigation
    if (presPrev) {
      presPrev.addEventListener('click', function () {
        if (curIndex > 0) {
          curIndex--;
          renderPresentSlide();
        }
      });
    }
    if (presNext) {
      presNext.addEventListener('click', function () {
        if (curIndex < slides.length - 1) {
          curIndex++;
          renderPresentSlide();
        }
      });
    }
    if (presNotesToggle && presNotesDrawer) {
      presNotesToggle.addEventListener('click', function () {
        var isShown = presNotesDrawer.style.display !== 'none';
        presNotesDrawer.style.display = isShown ? 'none' : 'block';
      });
    }
    if (presFs) {
      presFs.addEventListener('click', function () {
        toggleFullScreen(presentView);
      });
    }

    // Keyboard navigation in present mode
    document.addEventListener('keydown', function (e) {
      if (isEditMode) return;
      if (e.target && /input|textarea/i.test(e.target.tagName)) return;
      if (e.key === 'ArrowRight' || e.key === 'Space' || e.key === 'PageDown') {
        if (curIndex < slides.length - 1) {
          curIndex++;
          renderPresentSlide();
        }
      } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
        if (curIndex > 0) {
          curIndex--;
          renderPresentSlide();
        }
      }
    });

    // Editor add slide / bullet
    if (addSlideBtn) {
      addSlideBtn.addEventListener('click', function () {
        saveCurrentEditorState();
        var newSlide = {
          id: slides.length + 1,
          title: 'Slide ' + (slides.length + 1),
          content: ['Click here to edit bullet point...'],
          notes: ''
        };
        slides.push(newSlide);
        curIndex = slides.length - 1;
        renderEditorThumbs();
        loadSlideToEditor();
      });
    }
    if (addBulletBtn) {
      addBulletBtn.addEventListener('click', function () {
        if (!slides[curIndex]) return;
        if (!slides[curIndex].content) slides[curIndex].content = [];
        slides[curIndex].content.push('New point...');
        renderBulletEditorItems();
      });
    }

    // Save presentation
    if (pptSaveBtn) {
      pptSaveBtn.addEventListener('click', function () {
        saveCurrentEditorState();
        var url = pptSaveBtn.getAttribute('data-url');
        if (!url) return;
        var originalText = pptSaveBtn.innerHTML;
        pptSaveBtn.disabled = true;
        pptSaveBtn.innerHTML = '&#9203; Saving...';

        var csrf = getCsrfToken();
        fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf
          },
          body: JSON.stringify({ slides: slides, csrf_test_name: csrf })
        })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data.csrf) setCsrfToken(data.csrf);
            if (data.ok) {
              pptSaveBtn.innerHTML = '&#10004; Saved!';
              setTimeout(function () {
                pptSaveBtn.disabled = false;
                pptSaveBtn.innerHTML = originalText;
              }, 1800);
            } else {
              alert(data.message || 'Error saving presentation.');
              pptSaveBtn.disabled = false;
              pptSaveBtn.innerHTML = originalText;
            }
          })
          .catch(function () {
            alert('Failed to save presentation. Check network connection.');
            pptSaveBtn.disabled = false;
            pptSaveBtn.innerHTML = originalText;
          });
      });
    }

    // Initial Present render
    renderPresentSlide();
  }

  /* -------------------------------------------------------------
     5. PDF Interactive Annotation / Edit Studio
     ------------------------------------------------------------- */
  var pdfToggleBtn = document.getElementById('fv-pdf-toggle-edit');
  var pdfCancelBtn = document.getElementById('fv-pdf-cancel-edit');
  var pdfRibbon = document.getElementById('pdf-ribbon');
  var pdfOverlay = document.getElementById('pdf-annotate-overlay');
  var pdfSaveBtn = document.getElementById('fv-pdf-save');
  var pdfCanvas = document.getElementById('pdf-draw-canvas');
  var pdfTextLayer = document.getElementById('pdf-text-layer');

  if (pdfToggleBtn && pdfOverlay && pdfCanvas) {
    var isPdfEdit = false;
    var currentPdfTool = 'select'; // 'select', 'text', 'pen', 'highlight'
    var isDrawing = false;
    var ctx = pdfCanvas.getContext('2d');
    var annotations = [];

    function resizePdfCanvas() {
      var wrap = document.getElementById('pdf-container-wrap');
      if (wrap) {
        pdfCanvas.width = wrap.clientWidth;
        pdfCanvas.height = wrap.clientHeight;
      }
    }

    pdfToggleBtn.addEventListener('click', function () {
      isPdfEdit = true;
      pdfToggleBtn.style.display = 'none';
      if (pdfCancelBtn) pdfCancelBtn.style.display = 'inline-flex';
      if (pdfSaveBtn) pdfSaveBtn.style.display = 'inline-flex';
      if (pdfRibbon) pdfRibbon.style.display = 'flex';
      pdfOverlay.style.display = 'block';
      resizePdfCanvas();
    });

    if (pdfCancelBtn) {
      pdfCancelBtn.addEventListener('click', function () {
        isPdfEdit = false;
        pdfCancelBtn.style.display = 'none';
        if (pdfSaveBtn) pdfSaveBtn.style.display = 'none';
        if (pdfRibbon) pdfRibbon.style.display = 'none';
        pdfOverlay.style.display = 'none';
        pdfToggleBtn.style.display = 'inline-flex';
      });
    }

    window.addEventListener('resize', function () {
      if (isPdfEdit) resizePdfCanvas();
    });

    // Tool switching
    if (pdfRibbon) {
      pdfRibbon.addEventListener('click', function (e) {
        var toolBtn = e.target.closest('.docx-btn');
        if (toolBtn && toolBtn.id) {
          pdfRibbon.querySelectorAll('.docx-btn').forEach(function (b) { b.classList.remove('is-active'); });
          toolBtn.classList.add('is-active');
          if (toolBtn.id === 'pdf-tool-select') currentPdfTool = 'select';
          if (toolBtn.id === 'pdf-tool-text') currentPdfTool = 'text';
          if (toolBtn.id === 'pdf-tool-pen') currentPdfTool = 'pen';
          if (toolBtn.id === 'pdf-tool-highlight') currentPdfTool = 'highlight';
        }

        // Stamps
        var stampBtn = e.target.closest('.pdf-stamp-btn');
        if (stampBtn) {
          var stampText = stampBtn.getAttribute('data-stamp');
          addPdfStamp(stampText, stampBtn.className);
        }

        // Clear
        if (e.target.id === 'pdf-clear-all') {
          if (confirm('Clear all drawings, text annotations, and stamps?')) {
            ctx.clearRect(0, 0, pdfCanvas.width, pdfCanvas.height);
            if (pdfTextLayer) pdfTextLayer.innerHTML = '';
            annotations = [];
          }
        }
      });
    }

    // Freehand Drawing on Canvas
    function getMousePos(e) {
      var rect = pdfCanvas.getBoundingClientRect();
      return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top
      };
    }

    pdfCanvas.addEventListener('mousedown', function (e) {
      if (!isPdfEdit) return;
      if (currentPdfTool === 'pen' || currentPdfTool === 'highlight') {
        isDrawing = true;
        var p = getMousePos(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        if (currentPdfTool === 'highlight') {
          ctx.strokeStyle = 'rgba(254, 240, 138, 0.45)';
          ctx.lineWidth = 18;
        } else {
          ctx.strokeStyle = '#dc2626';
          ctx.lineWidth = 2.5;
        }
      }
    });

    pdfCanvas.addEventListener('mousemove', function (e) {
      if (!isDrawing) return;
      var p = getMousePos(e);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
    });

    window.addEventListener('mouseup', function () {
      if (isDrawing) {
        isDrawing = false;
        ctx.closePath();
      }
    });

    // Add Text Annotation
    pdfOverlay.addEventListener('click', function (e) {
      if (!isPdfEdit || currentPdfTool !== 'text') return;
      if (e.target.closest('.pdf-placed-textbox') || e.target.closest('.pdf-placed-stamp')) return;

      var rect = pdfOverlay.getBoundingClientRect();
      var x = e.clientX - rect.left;
      var y = e.clientY - rect.top;

      var box = document.createElement('div');
      box.className = 'pdf-placed-textbox';
      box.contentEditable = 'true';
      box.style.left = x + 'px';
      box.style.top = y + 'px';
      box.textContent = 'Type annotation...';

      box.addEventListener('blur', function () {
        if (!box.textContent.trim()) box.remove();
      });

      pdfTextLayer.appendChild(box);
      box.focus();
    });

    // Add Stamp
    function addPdfStamp(text, cls) {
      if (!pdfTextLayer) return;
      var stamp = document.createElement('div');
      stamp.className = 'pdf-placed-stamp ' + cls;
      stamp.textContent = text;
      stamp.style.left = (pdfCanvas.width / 2 - 60) + 'px';
      stamp.style.top = (pdfCanvas.height / 2 - 30) + 'px';

      // Drag stamp
      makeDraggable(stamp);
      pdfTextLayer.appendChild(stamp);
    }

    function makeDraggable(elem) {
      var isDragging = false, startX, startY, origLeft, origTop;
      elem.addEventListener('mousedown', function (e) {
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        origLeft = parseInt(elem.style.left || '0', 10);
        origTop = parseInt(elem.style.top || '0', 10);
        e.stopPropagation();
      });
      window.addEventListener('mousemove', function (e) {
        if (!isDragging) return;
        elem.style.left = (origLeft + e.clientX - startX) + 'px';
        elem.style.top = (origTop + e.clientY - startY) + 'px';
      });
      window.addEventListener('mouseup', function () {
        isDragging = false;
      });
    }

    // Save PDF Annotations
    if (pdfSaveBtn) {
      pdfSaveBtn.addEventListener('click', function () {
        var url = pdfSaveBtn.getAttribute('data-url');
        if (!url) return;
        var originalText = pdfSaveBtn.innerHTML;
        pdfSaveBtn.disabled = true;
        pdfSaveBtn.innerHTML = '&#9203; Saving...';

        var drawData = pdfCanvas.toDataURL('image/png');
        var textItems = [];
        pdfTextLayer.querySelectorAll('.pdf-placed-textbox').forEach(function (t) {
          textItems.push({ text: t.textContent, x: t.style.left, y: t.style.top });
        });
        var stamps = [];
        pdfTextLayer.querySelectorAll('.pdf-placed-stamp').forEach(function (s) {
          stamps.push({ text: s.textContent, cls: s.className, x: s.style.left, y: s.style.top });
        });

        var payload = {
          draw: drawData,
          texts: textItems,
          stamps: stamps,
          csrf_test_name: getCsrfToken()
        };

        fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken()
          },
          body: JSON.stringify(payload)
        })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data.csrf) setCsrfToken(data.csrf);
            pdfSaveBtn.innerHTML = '&#10004; Saved!';
            setTimeout(function () {
              pdfSaveBtn.disabled = false;
              pdfSaveBtn.innerHTML = originalText;
            }, 1800);
          })
          .catch(function () {
            alert('Failed to save annotations.');
            pdfSaveBtn.disabled = false;
            pdfSaveBtn.innerHTML = originalText;
          });
      });
    }
  }

})();
