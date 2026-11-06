/* Search inside an open Laboratory Document: every match is highlighted, Enter / the arrows go from one to the next (and to its page) */
(function () {
  'use strict';
  var box = document.getElementById('ld-pages') || document.getElementById('vd-pages') || document.getElementById('bk-paper') || document.getElementById('fv-text');
  var tools = document.querySelector('.ld-tools');
  if (!box || !tools) return;
  var hits = [], cur = -1, q = '';

  var wrap = document.createElement('span'); wrap.className = 'ld-find';
  wrap.innerHTML = '<input type="search" class="ld-find-in" placeholder="Find in document" aria-label="Find in this document" autocomplete="off">' +
    '<span class="ld-find-n" aria-live="polite"></span>' +
    '<button type="button" class="ld-btn ld-find-b" data-d="-1" title="Previous match (Shift+Enter)" aria-label="Previous match">↑</button>' +
    '<button type="button" class="ld-btn ld-find-b" data-d="1" title="Next match (Enter)" aria-label="Next match">↓</button>';
  tools.insertBefore(wrap, tools.firstChild);
  var input = wrap.querySelector('input'), num = wrap.querySelector('.ld-find-n');

  function clear() {
    box.querySelectorAll('mark.ld-hit').forEach(function (m) { var p = m.parentNode; p.replaceChild(document.createTextNode(m.textContent), m); p.normalize(); });
    hits = []; cur = -1;
  }
  function run() {
    clear(); q = input.value.trim().toLowerCase();
    if (q.length < 1) { num.textContent = ''; return; }
    var w = document.createTreeWalker(box, NodeFilter.SHOW_TEXT, { acceptNode: function (n) {
      return n.nodeValue && n.nodeValue.toLowerCase().indexOf(q) >= 0 && !n.parentNode.closest('script,style,.ld-measure') ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT; } });
    var nodes = []; while (w.nextNode()) nodes.push(w.currentNode);
    nodes.forEach(function (n) {
      var t = n.nodeValue, low = t.toLowerCase(), pos = 0, i, frag = document.createDocumentFragment();
      while ((i = low.indexOf(q, pos)) >= 0) {
        if (i > pos) frag.appendChild(document.createTextNode(t.slice(pos, i)));
        var m = document.createElement('mark'); m.className = 'ld-hit'; m.textContent = t.slice(i, i + q.length); frag.appendChild(m); hits.push(m); pos = i + q.length;
      }
      if (pos < t.length) frag.appendChild(document.createTextNode(t.slice(pos)));
      n.parentNode.replaceChild(frag, n);
    });
    if (!hits.length) { num.textContent = 'No match'; return; }
    go(0);
  }
  function go(i) {
    if (!hits.length) return;
    if (cur >= 0 && hits[cur]) hits[cur].classList.remove('cur');
    cur = (i + hits.length) % hits.length;
    var m = hits[cur]; m.classList.add('cur');
    var sheet = m.closest('.ld-sheet');
    if (sheet && !sheet.classList.contains('is-active')) {                  // the match is on another page: open that page
      var all = Array.prototype.slice.call(box.querySelectorAll('.ld-sheet')), idx = all.indexOf(sheet);
      var b = document.querySelector('.ld-pager [data-p="' + idx + '"]');
      if (b && !box.classList.contains('ld-all')) b.click();
    }
    m.scrollIntoView({ block: 'center', inline: 'center' });
    num.textContent = (cur + 1) + ' / ' + hits.length;
  }
  var t; input.addEventListener('input', function () { clearTimeout(t); t = setTimeout(run, 160); });
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); if (!hits.length) run(); else go(cur + (e.shiftKey ? -1 : 1)); } if (e.key === 'Escape') { input.value = ''; run(); } });
  wrap.addEventListener('click', function (e) { var b = e.target.closest('[data-d]'); if (b) go(cur + Number(b.dataset.d)); });
  // editing works on clean text: the highlight is taken off when a cell or paragraph is entered
  box.addEventListener('focusin', function () { if (hits.length) { clear(); num.textContent = ''; } });
  document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f' && !e.shiftKey) { e.preventDefault(); input.focus(); input.select(); } });
  // a workbook shows one sheet at a time: the search is run again on the sheet that is opened
  var tabs = document.getElementById('bk-tabs');
  if (tabs) tabs.addEventListener('click', function () { setTimeout(function () { if (input.value) run(); }, 120); });
})();
