// expandable sidebar groups (InterLink, Analytics): start collapsed on fresh session, remember toggles across views
document.querySelectorAll('.nav-group').forEach(function (g) {
  var btn = g.querySelector('.nav-group-toggle'), box = g.querySelector('.nav-group-items'), chev = g.querySelector('.nav-chev');
  var key = 'nav-group-' + g.dataset.navGroup;
  var savedOpen = false;
  try { savedOpen = sessionStorage.getItem(key) === '1'; } catch (e) {}
  var set = function (open) {
    box.hidden = !open; btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    chev.style.transform = open ? 'rotate(90deg)' : '';
    try { sessionStorage.setItem(key, open ? '1' : '0'); } catch (e) {}
  };
  set(savedOpen);
  btn.addEventListener('click', function () { set(box.hidden); });
  // collapsed sidebar: the sub-menu is a flyout next to the icon
  var root = document.documentElement, t;
  var show = function () {
    if (!root.classList.contains('side-collapsed')) return;
    clearTimeout(t);
    document.querySelectorAll('.nav-group.fly').forEach(function (o) { if (o !== g) o.classList.remove('fly'); });
    g.classList.add('fly');
    var r = btn.getBoundingClientRect(), h = box.offsetHeight || 80;
    box.style.top = Math.max(8, Math.min(r.top, window.innerHeight - h - 8)) + 'px';
  };
  var hide = function () { t = setTimeout(function () { g.classList.remove('fly'); }, 160); };
  g.addEventListener('mouseenter', show); g.addEventListener('mouseleave', hide);
  g.addEventListener('focusin', show); g.addEventListener('focusout', hide);
});
