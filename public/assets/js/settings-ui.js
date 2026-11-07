(() => {
  'use strict';
  const root = document.getElementById('st-root') || document.querySelector('.st');
  if (!root) return;
  const nav = document.getElementById('st-nav');
  const cards = [...root.querySelectorAll(':scope > .card[id]')];

  // the section being read is marked in the navigator
  if (nav) {
    const links = new Map([...nav.querySelectorAll('a')].map((a) => [a.getAttribute('href').slice(1), a]));
    let tick = false;
    const mark = () => {
      tick = false;
      let cur = null;
      const vis = cards.filter((c) => !c.classList.contains('st-hide'));
      vis.forEach((c) => { if (c.getBoundingClientRect().top <= 200) cur = c; });          // the last card whose top has passed the header
      if (vis.length && innerHeight + scrollY >= document.documentElement.scrollHeight - 6) cur = vis[vis.length - 1];   // the end of the page: the last card
      links.forEach((a, id) => a.classList.toggle('on', !!cur && cur.id === id));
    };
    addEventListener('scroll', () => { if (!tick) { tick = true; requestAnimationFrame(mark); } }, { passive: true });
    mark();
  }
  if (nav) nav.addEventListener('click', (e) => {
    const a = e.target.closest('a[href^="#"]'); if (!a) return;
    const t = document.getElementById(a.getAttribute('href').slice(1)); if (!t) return;
    e.preventDefault(); t.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    try { history.replaceState(null, '', a.getAttribute('href')); } catch (x) { /* ignore */ }
  });

  // "find a setting": hides the fields that do not match, and a card with nothing left
  const find = document.getElementById('st-find'); const empty = document.getElementById('st-empty');
  if (find) find.addEventListener('input', () => {
    const q = find.value.trim().toLowerCase(); let shown = 0;
    cards.forEach((c) => {
      if (c.id === 'su-root') { c.classList.toggle('st-hide', q !== '' && !'users roles access role admin account'.includes(q) && !c.innerText.toLowerCase().includes(q)); if (!c.classList.contains('st-hide')) shown++; return; }
      const rows = [...c.querySelectorAll('.st-row, .st-check')];
      let any = false;
      rows.forEach((r) => { const hit = !q || r.innerText.toLowerCase().includes(q); r.classList.toggle('st-hide', !hit); if (hit) any = true; });
      const titleHit = q && (c.querySelector('h3') || { innerText: '' }).innerText.toLowerCase().includes(q);
      if (titleHit) rows.forEach((r) => r.classList.remove('st-hide'));
      const ok = !q || any || titleHit || !rows.length && c.innerText.toLowerCase().includes(q);
      c.classList.toggle('st-hide', !ok); if (ok) shown++;
    });
    if (empty) empty.hidden = shown > 0;
  });
})();
