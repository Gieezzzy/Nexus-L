/* "Revisions" panel beside an open Laboratory Document: the versions kept before each save. Click one to open it next to the original (read-only);
   the trash deletes its file in the Logs folder (a copy stays in the database). Only the original can be edited. */
(function () {
  'use strict';
  var node = document.getElementById('ld-revs');
  var stage = document.querySelector('[data-ld-stage]') || document.getElementById('ld-stage') || document.getElementById('vd-stage') || document.getElementById('bk-stage');
  if (!node || !stage) return;
  var R; try { R = JSON.parse(node.textContent); } catch (e) { return; }
  if (!R || R.ro) return;                                              // a revision shown in the frame has no panel of its own

  function el(tag, cls, txt) { var n = document.createElement(tag); if (cls) n.className = cls; if (txt != null) n.textContent = txt; return n; }
  var work = el('div', 'ld-work'), colA = el('div', 'ld-col'), colB = el('div', 'ld-col ld-col-rev'), side = el('aside', 'ld-revs');
  side.setAttribute('aria-label', 'Revisions');
  stage.parentNode.insertBefore(work, stage);
  var hA = el('div', 'ld-col-h', 'Original'); hA.hidden = true;
  colA.appendChild(hA); colA.appendChild(stage);
  var hB = el('div', 'ld-col-h'), close = el('button', 'ld-btn ld-col-x', '×'); close.type = 'button'; close.title = 'Close the revision'; close.setAttribute('aria-label', 'Close the revision');
  hB.appendChild(el('span', '', '')); hB.appendChild(close);
  var frame = el('iframe', 'ld-frame'); frame.title = 'Revision';
  colB.appendChild(hB); colB.appendChild(frame); colB.hidden = true;
  work.appendChild(colA); work.appendChild(colB); work.appendChild(side);

  var open = 0, path0 = location.pathname;
  function layoutChanged() { setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 60); }
  function mark() { side.querySelectorAll('.ld-rev').forEach(function (n) { n.classList.toggle('on', Number(n.dataset.id) === open); }); }
  function show(it) {
    open = it.id;
    hB.firstChild.textContent = 'Revision #' + it.no + ' · ' + it.at + ' · read-only';
    frame.src = path0 + '?rev=' + it.id + '&embed=1';
    colB.hidden = false; hA.hidden = false; work.classList.add('ld-cmp'); mark(); layoutChanged();
  }
  function shut() { open = 0; colB.hidden = true; hA.hidden = true; frame.removeAttribute('src'); work.classList.remove('ld-cmp'); mark(); layoutChanged(); }
  close.onclick = shut;

  function build() {
    side.innerHTML = '';
    side.appendChild(el('div', 'ld-revs-h', 'Revisions'));
    if (!R.items.length) { side.appendChild(el('p', 'ld-revs-none', 'No saved revisions yet. The version before every save is kept here.')); return; }
    R.items.forEach(function (it) {
      var row = el('div', 'ld-rev'); row.dataset.id = String(it.id);
      var b = el('button', 'ld-rev-open'); b.type = 'button'; b.title = 'Open Rev' + it.no + ' next to the original';
      b.innerHTML = '<svg class="ld-rev-i" viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><rect x="3" y="1" width="18" height="22" rx="3" fill="#e5533d"/><path d="M7 8h10M7 12h10M7 16h6" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>';
      var t = el('span', 'ld-rev-t'); t.appendChild(el('b', '', 'Rev' + it.no)); t.appendChild(el('span', '', it.at)); if (it.by) t.appendChild(el('span', '', it.by));
      b.appendChild(t); b.onclick = function () { if (open === it.id) shut(); else show(it); };
      row.appendChild(b);
      if (R.canDelete) {
        var d = el('button', 'ld-rev-del', ''); d.type = 'button'; d.title = 'Delete the Logs file of Rev' + it.no; d.setAttribute('aria-label', 'Delete Rev' + it.no);
        d.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        d.onclick = function () { ask(it); };
        row.appendChild(d);
      }
      side.appendChild(row);
    });
    mark();
  }
  function ask(it) {                                                   // the confirmation
    var ov = el('div', 'ld-dlg'), box = el('div', 'ld-dlg-b');
    ov.setAttribute('role', 'alertdialog'); ov.setAttribute('aria-modal', 'true');
    box.appendChild(el('h3', '', 'Delete Rev' + it.no + '?'));
    box.appendChild(el('p', '', 'The file of this revision is deleted from the Logs folder. A secure copy stays in the database.'));
    var bar = el('div', 'ld-dlg-r'), no = el('button', 'ld-btn', 'Cancel'), yes = el('button', 'ld-btn ld-primary', 'Delete'); no.type = yes.type = 'button';
    bar.appendChild(no); bar.appendChild(yes); box.appendChild(bar); ov.appendChild(box); document.body.appendChild(ov); no.focus();
    function end() { ov.remove(); document.removeEventListener('keydown', esc); }
    function esc(e) { if (e.key === 'Escape') end(); }
    document.addEventListener('keydown', esc); no.onclick = end; ov.addEventListener('click', function (e) { if (e.target === ov) end(); });
    yes.onclick = function () {
      yes.disabled = true;
      var meta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
      fetch(R.delUrl + it.id + '/delete', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta ? meta.content : '' } })
        .then(function (r) { return r.json(); }).then(function (j) {
          if (meta && j.csrf) meta.content = j.csrf;
          end();
          if (j.ok) { if (open === it.id) shut(); R.items = R.items.filter(function (x) { return x.id !== it.id; }); build(); }
          else alert(j.message || 'The revision could not be deleted.');
        }).catch(function () { end(); alert('The server could not be reached.'); });
    };
  }
  build();
})();
