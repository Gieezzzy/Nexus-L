/* #DTP-Lab-101 VF test methods: search, series filter, the "Illustrated" menu of a card */
(function () {
  'use strict';
  var root = document.getElementById('vm');
  if (!root) return;
  var cards = Array.prototype.slice.call(root.querySelectorAll('.vm-card'));
  var search = document.getElementById('vm-search'), count = document.getElementById('vm-count'), none = document.getElementById('vm-none');
  var chips = root.querySelectorAll('.vm-chip'), series = '', pop = document.getElementById('vm-pop');

  function apply() {
    var q = (search.value || '').toLowerCase().trim().replace(/\s+/g, ' '), n = 0;
    cards.forEach(function (c) {
      var ok = (!series || c.dataset.s === series) && (!q || c.dataset.t.indexOf(q) >= 0 || c.dataset.t.replace(/[\s-]/g, '').indexOf(q.replace(/[\s-]/g, '')) >= 0);
      c.hidden = !ok; if (ok) n++;
    });
    count.textContent = n; none.hidden = n > 0;
    try { sessionStorage.setItem('vm-filter' + location.pathname, JSON.stringify({ q: search.value, s: series })); } catch (e) {}
  }
  search.addEventListener('input', apply);
  Array.prototype.forEach.call(chips, function (b) {
    b.addEventListener('click', function () {
      series = b.dataset.s;
      Array.prototype.forEach.call(chips, function (x) { x.classList.toggle('on', x === b); });
      apply();
    });
  });
  try {                                                    // coming back from a method keeps the search and the series
    var f = JSON.parse(sessionStorage.getItem('vm-filter' + location.pathname) || 'null');
    if (f) { search.value = f.q || ''; series = f.s || ''; Array.prototype.forEach.call(chips, function (x) { x.classList.toggle('on', x.dataset.s === series); }); }
  } catch (e) {}
  apply();

  // the "Illustrated" button of a method that has several illustrated PDFs
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ill]');
    if (!b) { pop.hidden = true; return; }
    e.stopPropagation();
    var list = JSON.parse(b.dataset.ill);
    pop.innerHTML = '';
    list.forEach(function (it) {
      var li = document.createElement('li'), a = document.createElement('a');
      a.href = it.url; a.target = '_blank'; a.rel = 'noopener'; a.textContent = it.label; li.appendChild(a); pop.appendChild(li);
    });
    pop.hidden = false;
    var r = b.getBoundingClientRect();
    pop.style.left = Math.min(r.left, window.innerWidth - pop.offsetWidth - 10) + 'px';
    pop.style.top = Math.min(r.bottom + 4, window.innerHeight - pop.offsetHeight - 10) + 'px';
  });
  document.addEventListener('click', function (e) { if (!pop.contains(e.target)) pop.hidden = true; });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') pop.hidden = true; });
  window.addEventListener('scroll', function () { pop.hidden = true; }, true);
})();
