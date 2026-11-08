/**
 * Help center: search across every answer, a topic list that follows the scroll, and "/" to jump to the search box.
 */
(() => {
  'use strict';
  const root = document.getElementById('hp');
  if (!root) return;
  const q = document.getElementById('hp-q');
  const status = document.getElementById('hp-status');
  const empty = document.getElementById('hp-empty');
  const term = document.getElementById('hp-term');
  const nav = [...document.querySelectorAll('#hp-nav a')];
  const topics = [...document.querySelectorAll('.hp-topic')];
  const total = document.querySelectorAll('.hp-art').length;

  // ---- search ---------------------------------------------------------------------------------------------------------------
  let opened = new Set();   // answers the search opened, closed again when it is cleared
  function run() {
    const words = q.value.toLowerCase().split(/\s+/).filter(Boolean);
    let hits = 0;
    topics.forEach((t) => {
      const rows = [...t.querySelectorAll('.hp-art, .hp-art-row')];
      let any = 0;
      rows.forEach((r) => {
        const ok = !words.length || words.every((w) => (r.dataset.text || '').includes(w));
        r.hidden = !ok;
        if (ok) any++;
        if (r.tagName === 'DETAILS') {
          if (words.length && ok) { if (!r.open) { r.open = true; opened.add(r); } }
          else if (opened.has(r)) { r.open = false; opened.delete(r); }
        }
      });
      const show = !words.length || any > 0;
      t.hidden = !show;
      const link = nav.find((a) => a.dataset.topic === t.dataset.topic);
      if (link) link.classList.toggle('is-off', !show);
      if (words.length) hits += any;
    });
    if (!words.length) {
      status.textContent = total + ' answers in ' + topics.filter((t) => t.id !== 'new').length + ' topics';
      empty.hidden = true;
    } else {
      status.textContent = hits === 0 ? 'No match' : hits + (hits === 1 ? ' answer matches' : ' answers match');
      empty.hidden = hits !== 0;
      term.textContent = q.value.trim();
    }
  }
  let t0; q.addEventListener('input', () => { clearTimeout(t0); t0 = setTimeout(run, 80); });
  q.addEventListener('keydown', (e) => { if (e.key === 'Escape') { q.value = ''; run(); q.blur(); } });
  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !/^(input|textarea|select)$/i.test(document.activeElement.tagName) && !document.activeElement.isContentEditable) { e.preventDefault(); q.focus(); q.select(); }
  });

  // ---- the topic list follows the page ---------------------------------------------------------------------------------------
  function mark(id) { nav.forEach((a) => a.classList.toggle('is-on', a.dataset.topic === id)); }
  if ('IntersectionObserver' in window) {
    const seen = new Map();
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => seen.set(en.target.id, en.isIntersecting ? en.boundingClientRect.top : null));
      const live = [...seen].filter(([, v]) => v !== null).sort((a, b) => a[1] - b[1]);
      if (live.length) mark(live[0][0]);
    }, { rootMargin: '-84px 0px -65% 0px', threshold: 0 });
    topics.forEach((t) => io.observe(t));
  }
  nav.forEach((a) => a.addEventListener('click', () => {
    const id = a.dataset.topic;
    if (q.value) { q.value = ''; run(); }
    mark(id);
  }));

  // a link such as help#calendar opens that topic's first answer
  const hash = location.hash.slice(1);
  if (hash) { const t = document.getElementById(hash); const d = t && t.querySelector('details'); if (d) d.open = true; }
})();
