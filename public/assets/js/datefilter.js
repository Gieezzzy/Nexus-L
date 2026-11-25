/* Shared calendar filter (Quick Presets + By Week/Month/Year + Daily/Weekly/Monthly/Year calendar) - same as the Lab Tracker one.
 * NxDateFilter.mount({ host, btn, from, to, allowAll, onApply })
 *   host    : element that holds the button (position:relative); the popup is added inside it
 *   btn     : the button that opens the popup
 *   from/to : the range now active ('YYYY-MM-DD', '' for all dates)
 *   allowAll: show "Reset to all dates"
 *   onApply : function (from, to) - from = to = '' means all dates
 */
(function () {
  'use strict';
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };
  var ymd = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
  var parse = function (s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); };
  var MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var nice = function (d) { return MON[d.getMonth()] + ' ' + d.getDate(); };
  var addDays = function (d, n) { var x = new Date(d); x.setDate(x.getDate() + n); return x; };

  var TITLES = { daily: 'Daily / Custom Date Range', weekly: 'Weekly Schedule (Mon–Sun)', monthly: 'Monthly Schedule', year: 'Yearly Schedule' };
  var HINTS = { daily: 'Click a day, or click start → end date', weekly: 'Click any day to select its Monday–Sunday week', monthly: 'Click a month to filter that full month', year: 'Click a year to filter that full year' };

  function mount(o) {
    var host = o.host, btn = o.btn;
    if (!host || !btn) return;
    host.classList.add('nxdf');
    var t0 = new Date(); t0.setHours(0, 0, 0, 0);
    var todayS = ymd(t0);
    var dp = { mode: 'daily', from: o.from || '', to: o.to || '', view: new Date() };
    dp.view = dp.from ? parse(dp.from) : new Date();
    dp.view.setDate(1);

    var pop = document.createElement('div');
    pop.className = 'nxdf-pop';
    pop.hidden = true;
    pop.innerHTML =
      '<div class="dp-right"><div class="dp-modes" role="tablist">' +
      '<button type="button" class="dp-mode-btn on" data-dp-mode="daily">Daily</button>' +
      '<button type="button" class="dp-mode-btn" data-dp-mode="weekly">Weekly</button>' +
      '<button type="button" class="dp-mode-btn" data-dp-mode="monthly">Monthly</button>' +
      '<button type="button" class="dp-mode-btn" data-dp-mode="year">Year</button></div>' +
      '<div class="dp-right-head"><h4 data-el="head"></h4><small class="dp-hint" data-el="hint"></small></div>' +
      '<div class="dp-inputs"><div class="dp-in-box"><small>FROM</small><input type="text" data-el="from" placeholder="YYYY-MM-DD" readonly></div>' +
      '<span class="dp-in-sep">&rarr;</span>' +
      '<div class="dp-in-box"><small>TO</small><input type="text" data-el="to" placeholder="YYYY-MM-DD" readonly></div></div>' +
      '<div class="dp-cal"><div class="dp-cal-head"><button type="button" class="dp-nav" data-el="prev" aria-label="Previous">&#9664;</button><b data-el="title"></b><button type="button" class="dp-nav" data-el="next" aria-label="Next">&#9654;</button></div>' +
      '<div class="dp-grid" data-el="grid"></div></div>' +
      '<div class="dp-actions"><button type="button" class="dp-clear-cal" data-el="clear">Clear</button><button type="button" class="dp-apply" data-el="apply">Apply Filter</button></div></div>';
    host.appendChild(pop);
    var el = function (n) { return pop.querySelector('[data-el="' + n + '"]'); };
    var sel = function (n) { return pop.querySelector('[data-sel="' + n + '"]'); };

    var fire = function (a, b) {
      pop.hidden = true; btn.setAttribute('aria-expanded', 'false');
      if (o.onApply) o.onApply(a, b);
    };

    // calendar (Daily / Weekly / Monthly / Year)
    var grid = el('grid');
    var range = function (a, b) {
      if (a === b) return a === todayS ? 'Today · ' + nice(parse(a)) : nice(parse(a)) + ', ' + a.slice(0, 4);
      return a + ' → ' + b;
    };
    var render = function () {
      var mode = dp.mode, y = dp.view.getFullYear(), mth = dp.view.getMonth();
      var now = new Date(), cY = now.getFullYear(), cM = now.getMonth();
      pop.querySelectorAll('.dp-mode-btn').forEach(function (b) { b.classList.toggle('on', b.dataset.dpMode === mode); });
      el('head').textContent = TITLES[mode];
      el('from').value = dp.from; el('to').value = dp.to;
      el('hint').textContent = !dp.from ? HINTS[mode] : (mode === 'daily' && !dp.to ? 'Click an end date, or Apply Filter for 1 day' : range(dp.from, dp.to || dp.from));
      grid.classList.toggle('dp-grid-period', mode === 'monthly' || mode === 'year');
      var out = '';
      if (mode === 'monthly') {
        el('title').textContent = String(y);
        for (var k = 0; k < 12; k++) {
          var f = ymd(new Date(y, k, 1)), t = ymd(new Date(y, k + 1, 0));
          out += '<button type="button" class="dp-period-btn' + (dp.from === f && dp.to === t ? ' edge' : '') + (y === cY && k === cM ? ' today' : '') + '" data-from="' + f + '" data-to="' + t + '">' + MON[k] + ' ' + y + '</button>';
        }
      } else if (mode === 'year') {
        var sY = Math.floor(y / 12) * 12;
        el('title').textContent = sY + ' – ' + (sY + 11);
        for (k = 0; k < 12; k++) {
          var y2 = sY + k;
          out += '<button type="button" class="dp-period-btn' + (dp.from === y2 + '-01-01' && dp.to === y2 + '-12-31' ? ' edge' : '') + (y2 === cY ? ' today' : '') + '" data-from="' + y2 + '-01-01" data-to="' + y2 + '-12-31">' + y2 + '</button>';
        }
      } else {
        el('title').textContent = MONTHS[mth] + ' ' + y;
        var first = new Date(y, mth, 1), start = new Date(y, mth, 1 - first.getDay());
        out = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map(function (d) { return '<span class="dp-dow">' + d + '</span>'; }).join('');
        for (k = 0; k < 42; k++) {
          var d = addDays(start, k), s3 = ymd(d), inM = d.getMonth() === mth;
          var hi = dp.from && (dp.to ? (s3 >= dp.from && s3 <= dp.to) : s3 === dp.from);
          var edge = s3 === dp.from || s3 === dp.to;
          var showHi = mode === 'weekly' ? hi : (hi && inM), showEdge = mode === 'weekly' ? edge : (edge && inM);
          out += '<button type="button" class="dp-day' + (inM ? '' : ' out') + (showHi ? ' in' : '') + (showEdge ? ' edge' : '') + (s3 === todayS ? ' today' : '') + '" data-d="' + s3 + '">' + d.getDate() + '</button>';
        }
      }
      grid.innerHTML = out;
    };
    pop.querySelectorAll('.dp-mode-btn').forEach(function (b) {
      b.addEventListener('click', function (e) { e.stopPropagation(); dp.mode = b.dataset.dpMode; render(); });
    });
    grid.addEventListener('click', function (e) {
      var pb = e.target.closest('.dp-period-btn');
      if (pb) { dp.from = pb.dataset.from; dp.to = pb.dataset.to; render(); return; }
      var b = e.target.closest('.dp-day');
      if (!b || (dp.mode === 'daily' && b.classList.contains('out'))) return;
      var s4 = b.dataset.d;
      if (dp.mode === 'weekly') {
        var dt = parse(s4), mn = addDays(dt, -((dt.getDay() + 6) % 7));
        dp.from = ymd(mn); dp.to = ymd(addDays(mn, 6)); render(); return;
      }
      if (!dp.from || dp.to) { dp.from = s4; dp.to = ''; }
      else if (s4 < dp.from) { dp.to = dp.from; dp.from = s4; }
      else dp.to = s4;
      render();
    });
    grid.addEventListener('dblclick', function (e) {
      if (e.target.closest('.dp-period-btn') || e.target.closest('.dp-day')) { if (dp.from) fire(dp.from, dp.to || dp.from); }
    });
    el('prev').addEventListener('click', function () {
      if (dp.mode === 'monthly') dp.view.setFullYear(dp.view.getFullYear() - 1);
      else if (dp.mode === 'year') dp.view.setFullYear(dp.view.getFullYear() - 12);
      else dp.view.setMonth(dp.view.getMonth() - 1);
      render();
    });
    el('next').addEventListener('click', function () {
      if (dp.mode === 'monthly') dp.view.setFullYear(dp.view.getFullYear() + 1);
      else if (dp.mode === 'year') dp.view.setFullYear(dp.view.getFullYear() + 12);
      else dp.view.setMonth(dp.view.getMonth() + 1);
      render();
    });
    el('clear').addEventListener('click', function () { dp.from = ''; dp.to = ''; if (o.allowAll) { fire('', ''); return; } render(); });
    el('apply').addEventListener('click', function () { if (dp.from) fire(dp.from, dp.to || dp.from); });

    // open / close
    btn.addEventListener('click', function (e) { e.stopPropagation(); pop.hidden = !pop.hidden; btn.setAttribute('aria-expanded', String(!pop.hidden)); });
    document.addEventListener('click', function (e) {
      if (pop.hidden) return;
      var path = e.composedPath ? e.composedPath() : [];
      if (path.indexOf(pop) < 0 && path.indexOf(btn) < 0) { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') pop.hidden = true; });
    render();
  }

  window.NxDateFilter = { mount: mount };
})();
