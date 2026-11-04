/* Preview on hover: a card with data-prev shows a larger picture of its file (first page of the PDF, the picture itself) next to the pointer.
   Only for a mouse (hover devices); keyboard focus on the card shows it too. The click still opens the file. */
(function () {
  'use strict';
  var cards = document.querySelectorAll('[data-prev]');
  if (!cards.length) return;
  var hoverOk = window.matchMedia && window.matchMedia('(hover: hover)').matches;
  var pop = document.createElement('div'); pop.className = 'ld-prev'; pop.hidden = true; pop.setAttribute('aria-hidden', 'true');
  var img = document.createElement('img'); img.alt = ''; pop.appendChild(img);
  var cap = document.createElement('div'); cap.className = 'ld-prev-c'; pop.appendChild(cap);
  document.body.appendChild(pop);
  var timer = 0, cur = null;

  function place(x, y) {
    var w = pop.offsetWidth || 380, h = pop.offsetHeight || 300, pad = 14;
    var left = x + 22; if (left + w > window.innerWidth - pad) left = Math.max(pad, x - w - 22);
    var top = Math.min(Math.max(pad, y - h / 3), window.innerHeight - h - pad);
    pop.style.left = left + 'px'; pop.style.top = top + 'px';
  }
  function open(card, x, y) {
    cur = card;
    img.onload = function () { if (cur === card) place(x, y); };
    img.src = card.dataset.prev;
    var n = card.querySelector('.vm-name, .vm-code'); cap.textContent = n ? n.textContent.trim() : '';
    pop.hidden = false; place(x, y);
  }
  function shut() { clearTimeout(timer); cur = null; pop.hidden = true; }

  cards.forEach(function (c) {
    if (hoverOk) {
      c.addEventListener('mouseenter', function (e) { clearTimeout(timer); var x = e.clientX, y = e.clientY; timer = setTimeout(function () { open(c, x, y); }, 280); });
      c.addEventListener('mousemove', function (e) { if (cur === c) place(e.clientX, e.clientY); });
      c.addEventListener('mouseleave', shut);
    }
    c.addEventListener('focusin', function () { var r = c.getBoundingClientRect(); open(c, r.right - 40, r.top + 40); });
    c.addEventListener('focusout', shut);
    c.addEventListener('click', shut);
  });
  window.addEventListener('scroll', shut, true);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') shut(); });
})();
