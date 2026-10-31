/**
 * Brain console: the live "Universe" (every answer the assistant knows as a star; questions fly to the brain that answers them; new knowledge
 * appears as it is learned) and the "Train AI" tab (train now, and the review task that teaches the brain what is right).
 */
(() => {
  const L = window.nxBrainLoaders;
  if (!L) return;
  const $ = (s, c = document) => c.querySelector(s);
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const api = (path, opts = {}) => fetch(BASE + path, opts).then((r) => r.json().then((j) => {
    if (j.csrf && csrfMeta) csrfMeta.content = j.csrf;
    if (!r.ok || j.ok === false) throw new Error(j.error || 'Request failed');
    return j;
  }));
  const post = (path, body) => api(path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' }, body: JSON.stringify(body || {}) });
  const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // =============================================================================================== universe
  const COLORS = { 'InterLink': '#38bdf8', 'InterLink service': '#22d3ee', 'Bonding': '#f472b6', 'Materials & tests': '#a78bfa', 'Approvals & reports': '#4ade80', 'Settings & admin': '#fbbf24',
    'Chat & inbox': '#fb923c', 'Errors & fixes': '#f87171', 'Account & access': '#94a3b8', 'Designer': '#2dd4bf', 'Charts & files': '#c084fc', 'General': '#cbd5e1' };
  const BRAIN = { primary: { color: '#22d3ee', label: 'AI brain (primary)' }, secondary: { color: '#fbbf24', label: 'PHP brain (secondary)' }, none: { color: '#f87171', label: 'Neither was sure' }, rule: { color: '#a3e635', label: 'Built-in skill' } };

  const U = { nodes: [], byId: new Map(), clusters: [], W: 0, H: 0, dpr: 1, layer: null, fx: [], last: 0, timer: 0, raf: 0, running: false, stats: {}, mouse: null, graphAt: 0 };

  const layout = () => {
    const { W, H } = U;
    const cx = W / 2;
    const cy = H * 0.44;
    const groups = new Map();
    U.nodes.forEach((n) => { if (!groups.has(n.c)) groups.set(n.c, []); groups.get(n.c).push(n); });
    U.clusters = [...groups.entries()].sort((a, b) => b[1].length - a[1].length).map(([name, list], i, all) => ({ name, list, i, n: all.length }));
    // every cluster is a disc whose size follows its number of stars; the discs are pushed apart until none overlap
    const N = Math.max(1, U.nodes.length);
    const area = W * (H * 0.78) * 0.42;
    const sp = Math.max(3.2, Math.min(7.5, Math.sqrt(area / (Math.PI * N))));
    const top = 34; const bottom = H * 0.8;
    U.clusters.forEach((c) => {
      c.r = sp * Math.sqrt(c.list.length + 0.6) + 5;
      const a = (c.i / c.n) * Math.PI * 2 - Math.PI / 2;
      c.x = cx + Math.cos(a) * W * 0.28;
      c.y = cy + Math.sin(a) * (bottom - top) * 0.3;
    });
    for (let it = 0; it < 400; it++) {
      let moved = false;
      for (let i = 0; i < U.clusters.length; i++) {
        for (let j = i + 1; j < U.clusters.length; j++) {
          const A = U.clusters[i]; const B = U.clusters[j];
          let dx = B.x - A.x; let dy = B.y - A.y;
          const d = Math.hypot(dx, dy) || 0.01;
          const need = A.r + B.r + 18;
          if (d < need) {
            const push = (need - d) / 2; dx /= d; dy /= d;
            A.x -= dx * push; A.y -= dy * push; B.x += dx * push; B.y += dy * push; moved = true;
          }
        }
      }
      U.clusters.forEach((c) => {
        c.x += (cx - c.x) * 0.004; c.y += ((top + bottom) / 2 - c.y) * 0.004;                 // a gentle pull to the middle
        c.x = Math.max(c.r + 8, Math.min(W - c.r - 8, c.x));
        c.y = Math.max(top + c.r, Math.min(bottom - c.r, c.y));
      });
      if (!moved && it > 40) break;
    }
    U.clusters.forEach((c) => {
      c.list.forEach((n, k) => {
        const r = sp * Math.sqrt(k + 0.6);
        const t = k * 2.39996323;
        n.x = c.x + Math.cos(t) * r;
        n.y = c.y + Math.sin(t) * r;
      });
    });
    drawLayer();
  };

  const rad = (n) => 1.5 + Math.min(4.5, Math.log2(1 + n.h) * 0.55 + Math.max(0, n.u) * 0.2);

  const drawLayer = () => {
    const { W, H, dpr } = U;
    const c = document.createElement('canvas');
    c.width = W * dpr; c.height = H * dpr;
    const x = c.getContext('2d');
    x.scale(dpr, dpr);
    // the sky
    const g = x.createRadialGradient(W / 2, H * 0.44, 10, W / 2, H * 0.44, Math.max(W, H) * 0.7);
    g.addColorStop(0, '#0b1633'); g.addColorStop(1, '#030712');
    x.fillStyle = g; x.fillRect(0, 0, W, H);
    for (let i = 0; i < 90; i++) { x.fillStyle = 'rgba(255,255,255,' + (0.1 + ((i * 37) % 10) / 40) + ')'; x.fillRect((i * 97.3) % W, (i * 53.7) % H, 1.2, 1.2); }
    // cluster glow and names
    U.clusters.forEach((cl) => {
      const col = COLORS[cl.name] || '#cbd5e1';
      const rr = cl.r;
      const gg = x.createRadialGradient(cl.x, cl.y, 2, cl.x, cl.y, rr * 1.6);
      gg.addColorStop(0, col + '33'); gg.addColorStop(1, col + '00');
      x.fillStyle = gg; x.beginPath(); x.arc(cl.x, cl.y, rr * 1.6, 0, 7); x.fill();
    });
    U.nodes.forEach((n) => {
      const col = COLORS[n.c] || '#cbd5e1';
      x.globalAlpha = 0.82;
      x.fillStyle = col;
      x.beginPath(); x.arc(n.x, n.y, rad(n), 0, 7); x.fill();
    });
    x.globalAlpha = 1;
    x.font = '600 11px "Segoe UI", sans-serif'; x.textAlign = 'center';
    U.clusters.forEach((cl) => {
      x.fillStyle = (COLORS[cl.name] || '#cbd5e1');
      x.fillText(cl.name + ' (' + cl.list.length + ')', cl.x, cl.y - cl.r - 3);
    });
    U.layer = c;
  };

  const orb = (which) => {
    const { W, H } = U;
    return which === 'primary' ? { x: W * 0.32, y: H * 0.9 } : which === 'secondary' ? { x: W * 0.68, y: H * 0.9 } : { x: W * 0.5, y: H * 0.78 };
  };

  const spawn = (e) => {
    const now = performance.now();
    if (e.kind === 'ask') {
      const b = e.brain || 'none';
      const from = { x: U.W * 0.5, y: U.H + 6 };
      const o = orb(b === 'rule' ? 'none' : b);
      const node = e.kb_id ? U.byId.get(Number(e.kb_id)) : null;
      U.fx.push({ t: now, type: 'ask', brain: b, from, orb: o, node, q: e.label, conf: e.conf, dur: reduced ? 400 : 2200 });
    } else if (e.kind === 'learn') {
      const node = e.kb_id ? U.byId.get(Number(e.kb_id)) : null;
      U.fx.push({ t: now, type: 'learn', node, at: node || { x: U.W / 2, y: U.H * 0.44 }, label: e.label, dur: reduced ? 600 : 3200 });
    } else if (e.kind === 'feedback') {
      const node = e.kb_id ? U.byId.get(Number(e.kb_id)) : null;
      if (node) U.fx.push({ t: now, type: 'vote', node, up: Number(e.conf) > 0, dur: reduced ? 400 : 1400 });
    } else if (e.kind === 'train' || e.kind === 'import') {
      U.fx.push({ t: now, type: 'wave', dur: reduced ? 500 : 2600, label: e.label });
    }
  };

  const frame = (ts) => {
    U.raf = 0;
    if (!U.running) return;
    const cv = $('#bu-canvas');
    if (!cv || !U.layer) { U.raf = requestAnimationFrame(frame); return; }
    const x = cv.getContext('2d');
    x.setTransform(U.dpr, 0, 0, U.dpr, 0, 0);
    x.drawImage(U.layer, 0, 0, U.W, U.H);
    const now = performance.now();
    // the two brains
    ['primary', 'secondary'].forEach((b) => {
      const o = orb(b); const col = BRAIN[b].color;
      const online = b === 'secondary' || U.stats.ai_online;
      const pulse = 1 + Math.sin(now / 600 + (b === 'primary' ? 0 : 2)) * 0.06;
      const g = x.createRadialGradient(o.x, o.y, 2, o.x, o.y, 46 * pulse);
      g.addColorStop(0, col + (online ? 'cc' : '44')); g.addColorStop(1, col + '00');
      x.fillStyle = g; x.beginPath(); x.arc(o.x, o.y, 46 * pulse, 0, 7); x.fill();
      x.fillStyle = online ? col : '#64748b'; x.beginPath(); x.arc(o.x, o.y, 11, 0, 7); x.fill();
      x.fillStyle = '#e2e8f0'; x.font = '600 11px "Segoe UI", sans-serif'; x.textAlign = 'center';
      x.fillText(BRAIN[b].label + (online ? '' : ' - offline'), o.x, o.y + 30);
    });
    // effects
    U.fx = U.fx.filter((f) => now - f.t < f.dur + 400);
    U.fx.forEach((f) => {
      const p = Math.min(1, (now - f.t) / f.dur);
      if (f.type === 'ask') {
        const col = (BRAIN[f.brain] || BRAIN.none).color;
        const seg = (a, b, t) => ({ x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t });
        let pt;
        if (p < 0.35) pt = seg(f.from, f.orb, p / 0.35);
        else if (f.node) pt = seg(f.orb, f.node, Math.min(1, (p - 0.35) / 0.4));
        else pt = f.orb;
        x.fillStyle = col; x.shadowColor = col; x.shadowBlur = 14;
        x.beginPath(); x.arc(pt.x, pt.y, 4, 0, 7); x.fill(); x.shadowBlur = 0;
        if (p > 0.75 && f.node) {
          const k = (p - 0.75) / 0.25;
          x.strokeStyle = col; x.globalAlpha = 1 - k; x.lineWidth = 2;
          x.beginPath(); x.arc(f.node.x, f.node.y, 6 + k * 26, 0, 7); x.stroke(); x.globalAlpha = 1;
        }
        if (f.q && p > 0.3) {
          x.globalAlpha = Math.min(1, (1 - p) * 3);
          x.font = '11px "Segoe UI", sans-serif'; x.textAlign = 'center'; x.fillStyle = '#f8fafc';
          x.fillText('"' + String(f.q).slice(0, 60) + '"', f.node ? f.node.x : f.orb.x, (f.node ? f.node.y : f.orb.y) - 18);
          x.globalAlpha = 1;
        }
        if (!f.node && p > 0.35) { x.strokeStyle = col; x.globalAlpha = 1 - p; x.beginPath(); x.arc(f.orb.x, f.orb.y, 14 + p * 40, 0, 7); x.stroke(); x.globalAlpha = 1; }
      } else if (f.type === 'learn') {
        const gold = '#fde047';
        for (let i = 0; i < 3; i++) {
          const k = (p * 1.6 - i * 0.22);
          if (k <= 0 || k >= 1) continue;
          x.strokeStyle = gold; x.globalAlpha = (1 - k) * 0.9; x.lineWidth = 2;
          x.beginPath(); x.arc(f.at.x, f.at.y, 6 + k * 60, 0, 7); x.stroke();
        }
        x.globalAlpha = 1; x.fillStyle = gold; x.shadowColor = gold; x.shadowBlur = 18;
        x.beginPath(); x.arc(f.at.x, f.at.y, 4.5 + Math.sin(now / 90) * 1.2, 0, 7); x.fill(); x.shadowBlur = 0;
        if (f.label) { x.globalAlpha = Math.min(1, (1 - p) * 2.5); x.fillStyle = '#fef9c3'; x.font = '600 12px "Segoe UI", sans-serif'; x.textAlign = 'center'; x.fillText('New: ' + String(f.label).slice(0, 56), f.at.x, f.at.y - 22); x.globalAlpha = 1; }
      } else if (f.type === 'vote') {
        const col = f.up ? '#4ade80' : '#f87171';
        x.strokeStyle = col; x.globalAlpha = 1 - p; x.lineWidth = 2;
        x.beginPath(); x.arc(f.node.x, f.node.y, 4 + p * 18, 0, 7); x.stroke(); x.globalAlpha = 1;
      } else if (f.type === 'wave') {
        const cx = U.W / 2; const cy = U.H * 0.44;
        x.strokeStyle = '#38bdf8'; x.globalAlpha = (1 - p) * 0.8; x.lineWidth = 3;
        x.beginPath(); x.arc(cx, cy, p * Math.max(U.W, U.H) * 0.6, 0, 7); x.stroke(); x.globalAlpha = 1;
        if (f.label) { x.fillStyle = '#bae6fd'; x.font = '600 13px "Segoe UI", sans-serif'; x.textAlign = 'center'; x.globalAlpha = 1 - p; x.fillText(f.label, cx, cy); x.globalAlpha = 1; }
      }
    });
    // recently learned or changed stars wear a ring
    const day = Date.now() / 1000 - 86400;
    x.lineWidth = 1; x.strokeStyle = 'rgba(253,224,71,.8)';
    U.nodes.forEach((n) => { if (n.ts > day) { x.beginPath(); x.arc(n.x, n.y, rad(n) + 2.2, 0, 7); x.stroke(); } });
    U.raf = requestAnimationFrame(frame);
  };

  const sizeCanvas = () => {
    const cv = $('#bu-canvas');
    if (!cv) return;
    const stage = cv.parentElement;
    const W = Math.max(320, stage.clientWidth);
    const H = Math.max(360, Math.min(640, Math.round(W * 0.6)));
    U.dpr = Math.min(window.devicePixelRatio || 1, 2);
    if (W === U.W && H === U.H) return;
    U.W = W; U.H = H;
    cv.width = W * U.dpr; cv.height = H * U.dpr;
    cv.style.width = W + 'px'; cv.style.height = H + 'px';
    if (U.nodes.length) layout();
  };

  const statsHtml = () => {
    const s = U.stats || {};
    const b = s.by_brain || {};
    const total = Math.max(1, s.asked_today || 0);
    const pct = (k) => Math.round(100 * ((b[k] && b[k].n) || 0) / total);
    const chip = (t, v, c) => `<span class="bu-chip" style="--c:${c || '#38bdf8'}"><b>${v}</b><small>${t}</small></span>`;
    return chip('answers known', (s.knowledge || 0).toLocaleString(), '#a78bfa') + chip('questions today', s.asked_today || 0, '#38bdf8') + chip('by AI brain', pct('primary') + '%', '#22d3ee')
      + chip('by PHP brain', pct('secondary') + '%', '#fbbf24') + chip('learned today', s.learned_today || 0, '#fde047') + chip('votes today', s.votes_today || 0, '#4ade80')
      + `<span class="bu-chip" style="--c:${s.ai_online ? '#4ade80' : '#f87171'}"><b>${s.ai_online ? 'online' : 'offline'}</b><small>AI brain</small></span>`;
  };

  const feedLine = (e) => {
    const t = (e.created_at || '').slice(11, 19);
    let c = '#94a3b8'; let h = '';
    if (e.kind === 'ask') {
      const b = BRAIN[e.brain] || { color: '#94a3b8', label: 'answered' };
      c = b.color; h = `<b>${esc(b.label)}</b> ${e.conf != null ? e.conf + '%' : ''} &middot; ${esc(e.label)}`;
    } else if (e.kind === 'learn') { c = '#fde047'; h = `<b>New knowledge</b> &middot; ${esc(e.label)}`; }
    else if (e.kind === 'feedback') { c = Number(e.conf) > 0 ? '#4ade80' : '#f87171'; h = `<b>${Number(e.conf) > 0 ? 'Thumbs up' : 'Thumbs down'}</b>`; }
    else if (e.kind === 'train') { c = '#38bdf8'; h = `<b>Training</b> &middot; ${esc(e.label)}`; }
    else { h = `<b>${esc(e.kind)}</b> ${esc(e.label)}`; }
    return `<li style="--c:${c}"><small>${t}</small> ${h}</li>`;
  };

  const pump = async () => {
    if (!U.running) return;
    try {
      if (!document.hidden) {
        const j = await api('brain/universe/feed?since=' + U.last);
        U.stats = j.stats || U.stats;
        $('#bu-stats').innerHTML = statsHtml();
        if (j.events.length) {
          const first = U.last === 0;
          const ul = $('#bu-feed');
          ul.insertAdjacentHTML('afterbegin', j.events.slice().reverse().map(feedLine).join(''));
          while (ul.children.length > 60) ul.lastChild.remove();
          if (!first) j.events.forEach(spawn);
          U.last = j.last;
          if (j.events.some((e) => e.kind === 'learn' || e.kind === 'import')) await loadGraph(true);
        }
        if (Date.now() - U.graphAt > 60000) await loadGraph(true);
      }
    } catch (e) { /* the next poll tries again */ }
    U.timer = setTimeout(pump, 1500);
  };

  const loadGraph = async (quiet) => {
    const j = await api('brain/universe/graph');
    const known = new Set(U.nodes.map((n) => n.id));
    const fresh = j.nodes.filter((n) => !known.has(n.id));
    U.nodes = j.nodes;
    U.byId = new Map(U.nodes.map((n) => [n.id, n]));
    U.stats = j.stats;
    U.graphAt = Date.now();
    sizeCanvas();
    layout();
    $('#bu-stats').innerHTML = statsHtml();
    $('#bu-legend').innerHTML = Object.keys(COLORS).filter((k) => U.nodes.some((n) => n.c === k)).map((k) => `<span><i style="background:${COLORS[k]}"></i>${esc(k)}</span>`).join('')
      + '<span><i class="ring"></i>new or changed today</span>';
    if (quiet) fresh.slice(0, 6).forEach((n) => U.fx.push({ t: performance.now(), type: 'learn', node: U.byId.get(n.id), at: U.byId.get(n.id), label: n.t, dur: 3200 }));
  };

  L.universe = async () => {
    U.running = true;
    try { await loadGraph(false); } catch (e) { $('#bu-stats').textContent = e.message; return; }
    if (!U.raf) U.raf = requestAnimationFrame(frame);
    clearTimeout(U.timer);
    if (U.last === 0) $('#bu-feed').innerHTML = '';
    pump();
  };
  // stop animating when another tab is shown
  document.querySelectorAll('.br-tab').forEach((b) => b.addEventListener('click', () => { if (b.dataset.tab !== 'universe') { U.running = false; clearTimeout(U.timer); } }));
  window.addEventListener('resize', () => { if (U.running) sizeCanvas(); });
  document.addEventListener('mousemove', (e) => {
    const cv = $('#bu-canvas'); const tip = $('#bu-tip');
    if (!cv || !U.running || !tip) return;
    const r = cv.getBoundingClientRect();
    const mx = e.clientX - r.left; const my = e.clientY - r.top;
    if (mx < 0 || my < 0 || mx > r.width || my > r.height) { tip.hidden = true; return; }
    let best = null; let bd = 100;
    for (const n of U.nodes) { const d = (n.x - mx) ** 2 + (n.y - my) ** 2; if (d < bd) { bd = d; best = n; } }
    if (!best) { tip.hidden = true; return; }
    tip.hidden = false;
    tip.innerHTML = `<b>${esc(best.t)}</b><br><small>${esc(best.c)} &middot; used ${best.h}&times; &middot; helpful ${best.u}</small>`;
    tip.style.left = Math.min(r.width - 230, mx + 14) + 'px'; tip.style.top = Math.max(4, my - 10) + 'px';
  });

  // =============================================================================================== train AI
  let trainTimer = 0;
  const pct = (v) => (v == null ? '-' : Math.round(v * 1000) / 10 + '%');
  const renderStatus = (s) => {
    const el = $('#bt-status');
    if (!s.online) {
      el.innerHTML = `<h4>The two brains</h4><p><b style="color:#b91c1c">The AI brain is offline.</b> The classic PHP brain is answering alone. Start the AI brain by double-clicking <code>run-ai.cmd</code> in the NEXUS-L folder.</p>`;
      return;
    }
    const a = s.ai || {}; const v = a.active || {}; const m = v.metrics || {};
    const lg = m.legacy || {}; const fl = m.full || {};
    el.innerHTML = `<h4>The two brains</h4>
      <div class="bt-grid">
        <div><b style="color:#0891b2">AI brain (primary)</b><br><small>${a.docs || 0} answers, ${a.corrections || 0} admin corrections indexed<br>
          reranker: <b>${a.reranker ? 'on' : 'off'}</b> &middot; calibrated confidence: <b>${a.calibrated ? 'yes' : 'not yet'}</b><br>version ${v.version || '-'} &middot; shows an answer at confidence &ge; ${v.threshold != null ? Math.round(v.threshold * 100) + '%' : '-'}</small></div>
        <div><b style="color:#b45309">PHP brain (secondary)</b><br><small>${s.php.knowledge} answers, word-matching, always on<br>answers whenever the AI brain is offline, unsure, or abstains</small></div>
        <div><b>Mode</b><br><small>${esc(s.mode)} ${s.mode === 'live' ? '(AI brain first, PHP brain as the safety net)' : s.mode === 'shadow' ? '(PHP answers; AI brain only watches)' : '(AI brain off)'}<br>change it in Settings</small></div>
      </div>
      ${v.holdout_cases ? `<p class="sm">On ${v.holdout_cases} questions it had never seen: <b>precision ${pct(v.holdout_precision)}</b> (how often a shown answer is right), <b>recall ${pct(v.holdout_recall)}</b> (how many it answers), abstains on ${pct(v.holdout_abstain)}.</p>` : ''}
      ${lg.precision != null && fl.precision != null ? `<p class="sm muted">Old method: precision ${pct(lg.precision)}, recall ${pct(lg.recall)} &middot; New method: precision ${pct(fl.precision)}, recall ${pct(fl.recall)}.</p>` : ''}
      <p class="sm muted">Labelled cases: ${a.cases ? a.cases.total : 0} (${a.review ? a.review.labelled_cases : 0} from your reviews) &middot; questions waiting for you: ${a.review ? a.review.pending : 0}</p>`;
    $('#br-n-train').textContent = a.review && a.review.pending ? a.review.pending : '';
    const job = a.job || {};
    const bar = $('#bt-bar');
    if (job.state === 'running') {
      bar.hidden = false; $('#bt-fill').style.width = (job.progress || 1) + '%';
      $('#bt-msg').textContent = job.step || 'Training...'; $('#bt-train').disabled = true;
    } else {
      $('#bt-train').disabled = false;
      if (job.state === 'done' && job.result) {
        bar.hidden = false; $('#bt-fill').style.width = '100%';
        const r = job.result;
        $('#bt-msg').textContent = r.skipped ? r.skipped : (r.note || 'Finished.');
        const mm = r.metrics;
        $('#bt-result').innerHTML = mm ? `<p class="sm">Test on ${mm.holdout_cases} unseen questions &mdash; ${mm.best === 'legacy' ? 'kept the old method' : 'new (' + mm.best + ')'}: precision ${pct(mm.full.precision)}, recall ${pct(mm.full.recall)} (threshold ${mm.full.threshold != null ? Math.round(mm.full.threshold * 100) + '%' : 'none reached'}); old: precision ${pct(mm.legacy.precision)}, recall ${pct(mm.legacy.recall)}. Took ${r.seconds}s.</p>` : '';
      } else if (job.state === 'error') { $('#bt-msg').textContent = 'Training failed: ' + (job.error || 'unknown error'); }
    }
    return job.state === 'running';
  };
  const refreshStatus = async () => {
    clearTimeout(trainTimer);
    try {
      const s = await api('brain/ai/status');
      const running = renderStatus(s);
      if (running || !$('#br-trainai').hidden) trainTimer = setTimeout(refreshStatus, running ? 2500 : 15000);
    } catch (e) { $('#bt-status').innerHTML = `<p class="muted sm">${esc(e.message)}</p>`; }
  };
  $('#bt-train').addEventListener('click', async () => {
    $('#bt-msg').textContent = 'Starting...'; $('#bt-result').innerHTML = '';
    try { await post('brain/ai/train', {}); refreshStatus(); } catch (e) { $('#bt-msg').textContent = e.message; }
  });

  const loadItem = async () => {
    const box = $('#bt-item');
    try {
      const j = await api('brain/ai/review');
      if (!j.item_id) { box.innerHTML = `<p class="sm">Nothing is waiting. ${j.labelled_cases || 0} cases labelled so far. <button type="button" class="dsg-btn" id="bt-more">Bring in new real questions</button></p>`; return; }
      box.innerHTML = `<p class="bt-q">&ldquo;${esc(j.question)}&rdquo; <small class="muted">(${j.pending} waiting)</small></p>
        ${j.candidates.map((c, i) => `<div class="bt-cand"><div><b>${esc(c.title)}</b> <small class="muted">${c.kind === 'correction' ? 'admin correction' : 'answer'} &middot; ${Math.round(c.score * 100)}% sure</small><p class="sm">${esc(c.body)}</p></div><button type="button" class="dsg-btn dsg-primary" data-pick="${c.kb_id || ''}" data-i="${i}">This is right</button></div>`).join('') || '<p class="muted sm">The brain found nothing close.</p>'}
        <details><summary>None of these fits</summary><textarea id="bt-ans" rows="3" placeholder="If you know the right answer, type it here: it goes live at once."></textarea><div class="br-row"><button type="button" class="dsg-btn" data-none="1">None fits</button></div></details>
        <div class="br-row"><button type="button" class="dsg-btn" data-off="1">Not about NEXUS-L</button><button type="button" class="dsg-btn" data-skip="1">Skip</button></div>`;
      box.dataset.item = String(j.item_id);
    } catch (e) { box.innerHTML = `<p class="muted sm">${esc(e.message)}</p>`; }
  };
  $('#bt-item').addEventListener('click', async (e) => {
    const b = e.target.closest('button'); if (!b) return;
    const box = $('#bt-item'); const id = Number(box.dataset.item || 0);
    if (b.id === 'bt-more') { b.disabled = true; try { const j = await post('brain/ai/review/seed', {}); box.innerHTML = `<p class="sm">${j.added} new question(s) added.</p>`; } catch (x) { box.textContent = x.message; } return loadItem(); }
    let body = null;
    if (b.dataset.pick) body = { choice: 'kb', kb_id: Number(b.dataset.pick) };
    else if (b.dataset.none) body = { choice: 'none', answer: ($('#bt-ans') || {}).value || '' };
    else if (b.dataset.off) body = { choice: 'offtopic' };
    else if (b.dataset.skip) body = { choice: 'skip' };
    if (!body || !id) return;
    box.querySelectorAll('button').forEach((x) => { x.disabled = true; });
    try { await post('brain/ai/label', Object.assign({ item_id: id }, body)); } catch (x) { box.insertAdjacentHTML('afterbegin', `<p class="sm" style="color:#b91c1c">${esc(x.message)}</p>`); }
    loadItem(); refreshStatus();
  });

  L.trainai = () => { refreshStatus(); loadItem(); };
})();
