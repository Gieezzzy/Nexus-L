(function () {
  // Machine Productivity Ranking: Daily (the day chosen in the timeline) or Whole period
  var applyRk = function (d) {
    var mode = window.__utmRkMode || 'day';
    document.querySelectorAll('.ut-rk-pane').forEach(function (p) {
      var key = p.getAttribute('data-rk');
      p.classList.toggle('active', mode === 'period' ? key === 'period' : key === d);
    });
    document.querySelectorAll('.ut-rk-mode').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-rk-mode') === mode); });
  };
  var activateDay = function (d, scrollTl) {
    if (!d) return;
    window.__utmSelectedDay = d;
    var sel = document.getElementById('ut-day-select');
    if (sel && sel.value !== d) sel.value = d;
    document.querySelectorAll('.ut-day-pane').forEach(function (p) {
      p.classList.toggle('active', p.getAttribute('data-utm-pane') === d);
    });
    applyRk(d);
    if (scrollTl) {
      var pane = document.querySelector('.ut-day-pane[data-utm-pane="' + d + '"]');
      if (pane) pane.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  };
  document.addEventListener('change', function (e) {
    if (e.target && e.target.id === 'ut-day-select') activateDay(e.target.value, false);
  });
  document.addEventListener('click', function (e) {
    var rm = e.target.closest('.ut-rk-mode');
    if (rm) {
      window.__utmRkMode = rm.getAttribute('data-rk-mode');
      var sel0 = document.getElementById('ut-day-select');
      applyRk(window.__utmSelectedDay || (sel0 && sel0.value) || '');
      return;
    }
    var step = e.target.closest('#ut-day-prev, #ut-day-next');
    if (step) {
      var sel = document.getElementById('ut-day-select');
      if (sel && sel.options.length > 0) {
        var ni = sel.selectedIndex + (step.id === 'ut-day-next' ? 1 : -1);
        if (ni >= 0 && ni < sel.options.length) {
          sel.selectedIndex = ni;
          activateDay(sel.value, false);
        }
      }
      return;
    }
    var jump = e.target.closest('[data-utm-jump]');
    if (jump) { activateDay(jump.getAttribute('data-utm-jump'), true); }
  });

  // Live update: ask the page again every 8 s and swap the numbers when a test was saved on a machine
  var box = document.getElementById('utm-live'), bar = document.getElementById('ut-live-bar'), txt = document.getElementById('ut-live-txt');
  if (!box || !bar) return;
  var busy = false, last = box.innerHTML.replace(/\s+/g, ' ');
  var stamp = function (s) { var d = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; }; txt.textContent = s + ' · updated ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds()); };
  var tick = function () {
    if (busy || document.hidden) return;
    busy = true;
    fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html'), fresh = doc.getElementById('utm-live');
        if (!fresh) throw new Error('no data');
        var now = fresh.innerHTML.replace(/\s+/g, ' ');
        bar.classList.remove('off');
        if (now !== last) {
          box.innerHTML = fresh.innerHTML; last = now;
          if (window.__utmSelectedDay) activateDay(window.__utmSelectedDay);
          bar.classList.add('flash'); setTimeout(function () { bar.classList.remove('flash'); }, 1500);
          stamp('Live · new result');
        } else { stamp('Live'); }
      })
      .catch(function () { bar.classList.add('off'); txt.textContent = 'Reconnecting…'; })
      .then(function () { busy = false; });
  };
  setInterval(tick, 8000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
})();
