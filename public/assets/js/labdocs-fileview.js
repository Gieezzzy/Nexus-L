/* A PDF or picture of a folder register: previous / next with the arrow keys, zoom for pictures, print, back to the folder (the same scroll position when it was just left) */
(function () {
  'use strict';
  var root = document.getElementById('fv'); if (!root) return;
  var back = document.getElementById('fv-back');
  if (back) back.addEventListener('click', function (e) {                // back goes to the folder as it was, not to the top of it
    try {
      var ref = document.referrer && new URL(document.referrer);
      var to = new URL(back.href, location.href);
      if (ref && ref.origin === location.origin && ref.pathname === to.pathname && ref.search === to.search && history.length > 1) { e.preventDefault(); history.back(); }
    } catch (x) {}
  });
  document.addEventListener('keydown', function (e) {
    if (e.target && /input|textarea|select/i.test(e.target.tagName)) return;
    var a = e.key === 'ArrowLeft' ? document.getElementById('fv-prev') : e.key === 'ArrowRight' ? document.getElementById('fv-next') : null;
    if (a && a.href) location.href = a.href;
  });
  var pic = document.getElementById('fv-pic'), box = document.getElementById('fv-img');
  if (pic && box && window.LdZoom) {
    var stage = document.getElementById('fv-stage'), z;
    z = LdZoom(stage, box, function () { return pic.naturalWidth || 1; });
    if (pic.complete && pic.naturalWidth) z.fit(); else pic.addEventListener('load', function () { z.fit(); });
  }
  document.getElementById('fv-print').onclick = function () {
    var fr = document.getElementById('fv-frame');
    if (fr && fr.contentWindow) { try { fr.contentWindow.focus(); fr.contentWindow.print(); return; } catch (e) {} }
    window.print();
  };
})();
