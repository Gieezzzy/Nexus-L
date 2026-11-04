/* Shared zoom for the Laboratory Documents sheets: opens fitted to the window, - / + / 100% / Fit buttons, Ctrl + mouse wheel, scroll bars */
(function () {
  'use strict';
  window.LdZoom = function (stage, target, needPx) {
    var Z = 1, auto = true, label;
    function set(z, manual) {
      if (manual) auto = false;
      Z = Math.max(0.25, Math.min(3, Math.round(z * 100) / 100));
      target.style.zoom = String(Z);
      if (label) label.textContent = Math.round(Z * 100) + '%';
    }
    function fit() {
      auto = true;
      var avail = stage.clientWidth - 12, need = needPx();
      set(need > avail && avail > 0 ? avail / need : 1);
    }
    var tools = document.querySelector('.ld-tools');
    if (tools) {
      var g = document.createElement('span'); g.className = 'bk-zoom'; g.setAttribute('role', 'group'); g.setAttribute('aria-label', 'Zoom');
      function btn(txt, title, fn) { var b = document.createElement('button'); b.type = 'button'; b.className = 'ld-btn'; b.textContent = txt; b.title = title; b.setAttribute('aria-label', title); b.onclick = fn; g.appendChild(b); return b; }
      btn('−', 'Zoom out (Ctrl + scroll down)', function () { set(Z - 0.1, true); });
      label = btn('100%', 'Actual size', function () { set(1, true); });
      btn('+', 'Zoom in (Ctrl + scroll up)', function () { set(Z + 0.1, true); });
      btn('Fit', 'Fit to the window', fit);
      tools.insertBefore(g, tools.firstChild);
    }
    stage.addEventListener('wheel', function (e) { if (!e.ctrlKey) return; e.preventDefault(); set(Z + (e.deltaY < 0 ? 0.1 : -0.1), true); }, { passive: false });
    window.addEventListener('resize', function () { if (auto) fit(); });
    return { fit: function () { if (auto) fit(); else set(Z); }, set: set };
  };
})();
