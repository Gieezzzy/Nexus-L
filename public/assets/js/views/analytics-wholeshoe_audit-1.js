(function () {
  var btn = document.getElementById('btn-ms-yearly');
  var back = document.getElementById('btn-ms-back-monthly');
  var yp = document.getElementById('ms-yearly-pane');
  var mp = document.getElementById('ms-monthly-pane');
  if (btn && yp && mp) {
    var setYearly = function (on) {
      yp.hidden = !on;
      mp.hidden = on;
      btn.style.background = on ? 'linear-gradient(135deg,#2563eb,#1d4ed8)' : '';
      btn.style.borderColor = on ? '#1d4ed8' : '';
      btn.style.color = on ? '#fff' : '';
    };
    btn.addEventListener('click', function () { setYearly(yp.hidden); });
    if (back) back.addEventListener('click', function () { setYearly(false); });
    if (new URLSearchParams(window.location.search).get('yearly') === '1') setYearly(true);
  }

  var calChip = document.getElementById('wsa-cal-trigger');
  if (calChip) {
    calChip.addEventListener('click', function (e) {
      e.stopPropagation();
      var drBtn = document.getElementById('an-dr-btn');
      if (drBtn) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(function () { drBtn.click(); }, 180);
      }
    });
  }

  var tbl = document.getElementById('wsa-rec-tbl');
  if (!tbl) return;
  var rows = Array.prototype.slice.call(tbl.querySelectorAll('tbody tr'));
  var qIn = document.getElementById('wsa-tbl-q');
  var tabs = document.querySelectorAll('#wsa-records-card .bld-btn');
  var cntEl = document.getElementById('wsa-tbl-count');
  var pgInfo = document.getElementById('wsa-pg-info');
  var pgSizeSel = document.getElementById('wsa-pg-size');
  var pgBtns = document.getElementById('wsa-pg-btns');
  var activeSt = 'all';
  var curPage = 1;
  var pageSize = 15;
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
        ? 'No matching samples'
        : 'Showing ' + (start + 1).toLocaleString() + '–' + end.toLocaleString() + ' of ' + total.toLocaleString() + ' samples';
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
        var d = document.createElement('span');
        d.className = 'wsa-pg-dots';
        d.textContent = '…';
        pgBtns.appendChild(d);
      }
      mk(String(p), p, false, p === curPage);
      prev = p;
    });
    mk('Next ›', curPage + 1, curPage >= pages, false);
  };

  var applyFilter = function () {
    var q = (qIn ? qIn.value : '').trim().toLowerCase();
    matched = [];
    var sFail = 0, sRated = 0;
    var sp = [0,0,0,0,0,0], sf = [0,0,0,0,0,0], st = [0,0,0,0,0,0];
    for (var i = 0; i < rows.length; i++) {
      var tr = rows[i];
      var rSt = tr.getAttribute('data-st') || '';
      var okSt = (activeSt === 'all' || rSt === activeSt);
      var okQ  = (!q || (tr.getAttribute('data-q') || '').indexOf(q) !== -1);
      if (okSt && okQ) {
        matched.push(tr);
        if (rSt === 'fail') { sFail++; sRated++; }
        else if (rSt === 'pass' || rSt === 'tol') { sRated++; }
        var ms = (tr.getAttribute('data-m') || '').split(',');
        for (var j = 0; j < 6; j++) {
          var c = ms[j] || 'z';
          if (c === 'p') { sp[j]++; st[j]++; }
          else if (c === 't') { st[j]++; }
          else if (c === 'f' || c === 'r') { sf[j]++; st[j]++; }
        }
      }
    }
    var gp = 0, gf = 0, gt = 0;
    for (var k = 0; k < 6; k++) {
      gp += sp[k]; gf += sf[k]; gt += st[k];
      var ep = tbl.querySelector('[data-sum-p="' + k + '"]');
      var ef = tbl.querySelector('[data-sum-f="' + k + '"]');
      var et = tbl.querySelector('[data-sum-t="' + k + '"]');
      var er = tbl.querySelector('[data-sum-r="' + k + '"]');
      if (ep) { ep.textContent = sp[k]; ep.className = sp[k] === 0 ? 'z-sum' : ''; }
      if (ef) { ef.textContent = sf[k]; ef.className = sf[k] === 0 ? 'z-sum' : ''; }
      if (et) { et.textContent = st[k]; et.className = st[k] === 0 ? 'z-sum' : ''; }
      if (er) {
        er.textContent = st[k] > 0 ? (sf[k] > 0 ? (Math.round(sf[k] / st[k] * 1000) / 10) + '%' : '0%') : '–';
        er.className = sf[k] > 0 ? 'has-pct' : 'z-sum';
      }
    }
    var gpe = document.getElementById('wsa-sum-p-tot');
    var gfe = document.getElementById('wsa-sum-f-tot');
    var gte = document.getElementById('wsa-sum-t-tot');
    var gre = document.getElementById('wsa-sum-r-tot');
    if (gpe) gpe.textContent = gp;
    if (gfe) gfe.textContent = gf;
    if (gte) gte.textContent = gt;
    if (gre) gre.textContent = gt > 0 ? (gf / gt * 100).toFixed(2) + '%' : '0.00%';
    if (cntEl) {
      var fp = sRated > 0 ? (sFail / sRated * 100).toFixed(1) : '0.0';
      cntEl.textContent = 'Total Samples: ' + matched.length.toLocaleString() + ' · Failed: ' + sFail.toLocaleString() + ' of ' + sRated.toLocaleString() + ' Rated (' + fp + '%)';
    }
    curPage = 1;
    renderPage();
  };

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.remove('on'); });
      t.classList.add('on');
      activeSt = t.getAttribute('data-st') || 'all';
      applyFilter();
    });
  });
  if (qIn) qIn.addEventListener('input', applyFilter);
  if (pgSizeSel) pgSizeSel.addEventListener('change', function () {
    pageSize = parseInt(pgSizeSel.value, 10) || 0;
    curPage = 1;
    renderPage();
  });
  renderPage();
})();
