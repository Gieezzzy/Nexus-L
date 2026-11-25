(function () {
  var tbl = document.getElementById('wsa-nc-tbl');
  if (!tbl) return;
  var rows = Array.prototype.slice.call(tbl.querySelectorAll('tbody tr'));
  var qIn = document.getElementById('wsa-nc-q');
  var mSel = document.getElementById('wsa-nc-method');
  var tabs = document.querySelectorAll('#wsa-nc-card .bld-btn');
  var pgInfo = document.getElementById('wsa-nc-pg-info');
  var pgSizeSel = document.getElementById('wsa-nc-pg-size');
  var pgBtns = document.getElementById('wsa-nc-pg-btns');
  var cellBanner = document.getElementById('wsa-nc-cell-banner');
  var cellTxt = document.getElementById('wsa-nc-cell-txt');
  var cellClr = document.getElementById('wsa-nc-cell-clr');
  var mfCells = document.querySelectorAll('#wsa-mf-tbl td.mf-click');

  var activeDisp = 'all', activeMonth = '', activeMethod = '', curPage = 1, pageSize = 10;
  var matched = rows.slice();

  var renderPage = function () {
    var total = matched.length;
    var pages = pageSize > 0 ? Math.max(1, Math.ceil(total / pageSize)) : 1;
    if (curPage > pages) curPage = pages;
    if (curPage < 1) curPage = 1;
    var start = pageSize > 0 ? (curPage - 1) * pageSize : 0;
    var end = pageSize > 0 ? Math.min(total, start + pageSize) : total;
    for (var i = 0; i < rows.length; i++) rows[i].style.display = 'none';
    for (var m = start; m < end; m++) matched[m].style.display = '';
    if (pgInfo) {
      pgInfo.textContent = total === 0
        ? 'No matching non-conforming samples'
        : 'Showing ' + (start + 1).toLocaleString() + '–' + end.toLocaleString() + ' of ' + total.toLocaleString() + ' non-conforming samples';
    }
    if (!pgBtns) return;
    pgBtns.innerHTML = '';
    if (pages <= 1) return;
    var mk = function (label, target, disabled, active) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'wsa-pg-btn' + (active ? ' on' : '');
      b.textContent = label;
      b.disabled = !!disabled;
      b.addEventListener('click', function () { curPage = target; renderPage(); });
      pgBtns.appendChild(b);
    };
    mk('‹ Prev', curPage - 1, curPage <= 1, false);
    var list = [1];
    for (var p = Math.max(2, curPage - 1); p <= Math.min(pages - 1, curPage + 1); p++) list.push(p);
    if (pages > 1) list.push(pages);
    var prev = 0;
    list.forEach(function (p) {
      if (prev && p - prev > 1) {
        var d = document.createElement('span'); d.className = 'wsa-pg-dots'; d.textContent = '…'; pgBtns.appendChild(d);
      }
      mk(String(p), p, false, p === curPage);
      prev = p;
    });
    mk('Next ›', curPage + 1, curPage >= pages, false);
  };

  var applyFilter = function () {
    var q = (qIn ? qIn.value : '').trim().toLowerCase();
    var mVal = activeMethod || (mSel ? mSel.value : '');
    matched = [];
    for (var i = 0; i < rows.length; i++) {
      var tr = rows[i];
      var okD = (activeDisp === 'all' || tr.getAttribute('data-disp') === activeDisp);
      var okMo = (!activeMonth || tr.getAttribute('data-mo') === activeMonth);
      var okM = (!mVal || (tr.getAttribute('data-methods') || '').indexOf(',' + mVal + ',') !== -1);
      var okQ = (!q || (tr.getAttribute('data-q') || '').indexOf(q) !== -1);
      if (okD && okMo && okM && okQ) matched.push(tr);
    }
    curPage = 1;
    renderPage();
  };

  var clearCell = function () {
    activeMonth = ''; activeMethod = '';
    if (mSel) mSel.value = '';
    mfCells.forEach(function (c) { c.classList.remove('mf-active'); });
    if (cellBanner) cellBanner.classList.remove('on');
    applyFilter();
  };

  mfCells.forEach(function (td) {
    td.addEventListener('click', function () {
      var wasActive = td.classList.contains('mf-active');
      mfCells.forEach(function (c) { c.classList.remove('mf-active'); });
      if (wasActive) { clearCell(); return; }
      td.classList.add('mf-active');
      activeMethod = td.getAttribute('data-mf-method') || '';
      activeMonth  = td.getAttribute('data-mf-month') || '';
      if (mSel) mSel.value = activeMethod;
      if (cellBanner && cellTxt) {
        cellTxt.textContent = 'Drill-down active: ' + (td.getAttribute('data-mf-label') || 'Filtered cell');
        cellBanner.classList.add('on');
      }
      applyFilter();
      var card = document.getElementById('wsa-nc-card');
      if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });

  if (cellClr) cellClr.addEventListener('click', clearCell);
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.remove('on'); });
      t.classList.add('on');
      activeDisp = t.getAttribute('data-disp') || 'all';
      applyFilter();
    });
  });
  if (qIn) qIn.addEventListener('input', applyFilter);
  if (mSel) mSel.addEventListener('change', function () {
    activeMethod = mSel.value;
    applyFilter();
  });
  if (pgSizeSel) pgSizeSel.addEventListener('change', function () {
    pageSize = parseInt(pgSizeSel.value, 10) || 0;
    curPage = 1;
    renderPage();
  });
  renderPage();
})();
