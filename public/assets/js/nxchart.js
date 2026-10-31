/**
 * nxChart: small dependency-free chart drawing for the assistant (bar, stacked bar, line, pie / donut) on a <canvas>.
 * Nothing is loaded from the internet. The same drawing is used for the small chart in the chat, the enlarged view and the PNG / PDF
 * file, so what you save is what you saw.
 *
 *   nxChart.draw(canvas, spec, { w, h })      spec = { kind, title, labels[], series: [{ name, data[] }], total }
 *   nxChart.png(spec, w, h)                   -> data URL (white background)
 */
(() => {
  const FIXED = { Pass: '#16a34a', Tolerable: '#d97706', Fail: '#dc2626', Other: '#94a3b8' };
  const PALETTE = ['#2563eb', '#0891b2', '#7c3aed', '#db2777', '#ea580c', '#65a30d', '#0d9488', '#9333ea', '#ca8a04', '#475569', '#e11d48', '#4f46e5'];
  const INK = '#0f172a';
  const MUTED = '#64748b';
  const GRID = '#e2e8f0';
  const FONT = '"Segoe UI", system-ui, -apple-system, Arial, sans-serif';

  const fmt = (n) => (Math.abs(n) >= 1000 ? n.toLocaleString('en-US') : String(Math.round(n * 100) / 100));
  const colorOf = (name, i) => FIXED[name] || PALETTE[i % PALETTE.length];

  const fit = (ctx, text, max) => {
    text = String(text);
    if (ctx.measureText(text).width <= max) return text;
    while (text.length > 1 && ctx.measureText(text + '...').width > max) text = text.slice(0, -1);
    return text + '...';
  };

  const niceMax = (v) => {
    if (v <= 0) return 1;
    const p = Math.pow(10, Math.floor(Math.log10(v)));
    const f = v / p;
    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * p;
  };

  const setup = (canvas, w, h) => {
    const dpr = Math.min(window.devicePixelRatio || 1, 3);
    canvas.width = Math.round(w * dpr);
    canvas.height = Math.round(h * dpr);
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, h);
    ctx.textBaseline = 'middle';
    return ctx;
  };

  const legend = (ctx, items, x, y, w) => {
    // returns the height used; wraps to several rows
    ctx.font = '11px ' + FONT;
    let cx = x;
    let cy = y;
    items.forEach((it) => {
      const label = it.label;
      const tw = Math.min(ctx.measureText(label).width, w * 0.6);
      if (cx + 16 + tw > x + w) { cx = x; cy += 17; }
      ctx.fillStyle = it.color;
      ctx.fillRect(cx, cy - 5, 10, 10);
      ctx.fillStyle = INK;
      ctx.textAlign = 'left';
      ctx.fillText(fit(ctx, label, w * 0.6), cx + 15, cy);
      cx += 15 + tw + 14;
    });
    return cy - y + 17;
  };

  const drawBars = (ctx, spec, W, H, top, bottom) => {
    const labels = spec.labels;
    const S = spec.series;
    const stacked = S.length > 1;
    ctx.font = '11px ' + FONT;
    const longest = Math.max(...labels.map((l) => ctx.measureText(l).width));
    const horizontal = labels.length > 6 || longest > 70;
    const totals = labels.map((_, i) => S.reduce((a, s) => a + (s.data[i] || 0), 0));
    const max = niceMax(Math.max(...totals, 1));
    const color = (s, si) => colorOf(spec.series.length > 1 ? s.name : s.name === 'Reports' ? PALETTE[0] : s.name, si);
    if (horizontal) {
      const left = Math.min(Math.max(longest + 12, 60), W * 0.38);
      const x0 = left + 8;
      const x1 = W - 34;
      const pb = bottom - 18;                                   // room for the number axis under the bars
      const h = pb - top;
      const row = h / labels.length;
      const bh = Math.max(6, Math.min(26, row * 0.66));
      ctx.strokeStyle = GRID;
      ctx.lineWidth = 1;
      for (let t = 0; t <= 4; t++) {
        const x = x0 + ((x1 - x0) * t) / 4;
        ctx.beginPath(); ctx.moveTo(x, top); ctx.lineTo(x, pb); ctx.stroke();
        ctx.fillStyle = MUTED; ctx.textAlign = 'center'; ctx.fillText(fmt((max * t) / 4), x, pb + 10);
      }
      labels.forEach((lb, i) => {
        const y = top + row * i + row / 2;
        ctx.fillStyle = INK; ctx.textAlign = 'right'; ctx.font = '11px ' + FONT;
        ctx.fillText(fit(ctx, lb, left), left, y);
        let x = x0;
        S.forEach((s, si) => {
          const v = s.data[i] || 0;
          const w = ((x1 - x0) * v) / max;
          if (w > 0) { ctx.fillStyle = color(s, si); ctx.fillRect(x, y - bh / 2, Math.max(1, w), bh); }
          x += w;
        });
        ctx.fillStyle = INK; ctx.textAlign = 'left'; ctx.fillText(fmt(totals[i]), x + 5, y);
      });
      return;
    }
    const left = 38;
    const x0 = left;
    const x1 = W - 12;
    const y0 = top + 8;
    const y1 = bottom - 18;
    ctx.strokeStyle = GRID;
    for (let t = 0; t <= 4; t++) {
      const y = y1 - ((y1 - y0) * t) / 4;
      ctx.beginPath(); ctx.moveTo(x0, y); ctx.lineTo(x1, y); ctx.stroke();
      ctx.fillStyle = MUTED; ctx.textAlign = 'right'; ctx.fillText(fmt((max * t) / 4), x0 - 5, y);
    }
    const slot = (x1 - x0) / labels.length;
    const bw = Math.min(54, slot * 0.62);
    labels.forEach((lb, i) => {
      const cx = x0 + slot * i + slot / 2;
      let y = y1;
      S.forEach((s, si) => {
        const v = s.data[i] || 0;
        const hgt = ((y1 - y0) * v) / max;
        if (hgt > 0) { ctx.fillStyle = color(s, si); ctx.fillRect(cx - bw / 2, y - hgt, bw, Math.max(1, hgt)); }
        y -= hgt;
      });
      ctx.fillStyle = INK; ctx.textAlign = 'center'; ctx.fillText(fmt(totals[i]), cx, y - 8);
      ctx.fillStyle = MUTED; ctx.fillText(fit(ctx, lb, slot - 4), cx, y1 + 11);
    });
    if (!stacked) ctx.textAlign = 'left';
  };

  const drawLine = (ctx, spec, W, H, top, bottom) => {
    const labels = spec.labels;
    const S = spec.series;
    const all = S.flatMap((s) => s.data);
    const max = niceMax(Math.max(...all, 1));
    const x0 = 40;
    const x1 = W - 16;
    const y0 = top + 8;
    const y1 = bottom - 20;
    ctx.font = '11px ' + FONT;
    ctx.strokeStyle = GRID;
    ctx.lineWidth = 1;
    for (let t = 0; t <= 4; t++) {
      const y = y1 - ((y1 - y0) * t) / 4;
      ctx.beginPath(); ctx.moveTo(x0, y); ctx.lineTo(x1, y); ctx.stroke();
      ctx.fillStyle = MUTED; ctx.textAlign = 'right'; ctx.fillText(fmt((max * t) / 4), x0 - 5, y);
    }
    const n = labels.length;
    const step = n > 1 ? (x1 - x0 - 8) / (n - 1) : 0;
    const px = (i) => (n > 1 ? x0 + 4 + step * i : (x0 + x1) / 2);
    const py = (v) => y1 - ((y1 - y0) * v) / max;
    const every = Math.max(1, Math.ceil(ctx.measureText('Mmm 0000').width / Math.max(step, 1)));
    labels.forEach((lb, i) => {
      if (i % every === 0) { ctx.fillStyle = MUTED; ctx.textAlign = 'center'; ctx.fillText(fit(ctx, lb, Math.max(step * every, 40)), px(i), y1 + 12); }
    });
    S.forEach((s, si) => {
      const c = colorOf(s.name === 'Reports' ? 'x0' : s.name, si);
      const col = s.name === 'Reports' ? PALETTE[0] : c;
      ctx.strokeStyle = col; ctx.lineWidth = 2.2; ctx.lineJoin = 'round';
      ctx.beginPath();
      s.data.forEach((v, i) => { if (i === 0) ctx.moveTo(px(i), py(v)); else ctx.lineTo(px(i), py(v)); });
      ctx.stroke();
      ctx.fillStyle = col;
      s.data.forEach((v, i) => {
        ctx.beginPath(); ctx.arc(px(i), py(v), 3.2, 0, Math.PI * 2); ctx.fill();
        if (S.length === 1 && n <= 14) { ctx.fillStyle = INK; ctx.textAlign = 'center'; ctx.fillText(fmt(v), px(i), py(v) - 10); ctx.fillStyle = col; }
      });
    });
  };

  const drawPie = (ctx, spec, W, H, top, bottom) => {
    const data = spec.series[0].data;
    const total = data.reduce((a, b) => a + b, 0) || 1;
    const wide = W > H * 1.15;
    const r = Math.max(40, Math.min(wide ? (bottom - top) / 2 - 4 : Math.min(W - 40, bottom - top - 8) / 2, 170));
    const cx = wide ? Math.min(W * 0.34, r + 24) : W / 2;
    const cy = (top + bottom) / 2;
    let a = -Math.PI / 2;
    data.forEach((v, i) => {
      const ang = (v / total) * Math.PI * 2;
      ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, r, a, a + ang); ctx.closePath();
      ctx.fillStyle = colorOf(spec.labels[i], i); ctx.fill();
      ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.stroke();
      if (v / total > 0.06) {
        const m = a + ang / 2;
        ctx.fillStyle = '#fff'; ctx.font = 'bold 11px ' + FONT; ctx.textAlign = 'center';
        ctx.fillText(Math.round((v / total) * 100) + '%', cx + Math.cos(m) * r * 0.78, cy + Math.sin(m) * r * 0.78);
      }
      a += ang;
    });
    ctx.beginPath(); ctx.arc(cx, cy, r * 0.5, 0, Math.PI * 2); ctx.fillStyle = '#fff'; ctx.fill();
    ctx.fillStyle = INK; ctx.textAlign = 'center'; ctx.font = 'bold 15px ' + FONT; ctx.fillText(fmt(total), cx, cy - 6);
    ctx.fillStyle = MUTED; ctx.font = '10px ' + FONT; ctx.fillText('reports', cx, cy + 9);
    if (wide) {
      let y = top + 8;
      ctx.font = '11px ' + FONT;
      data.forEach((v, i) => {
        if (y > bottom) return;
        ctx.fillStyle = colorOf(spec.labels[i], i); ctx.fillRect(cx + r + 24, y - 5, 10, 10);
        ctx.fillStyle = INK; ctx.textAlign = 'left';
        ctx.fillText(fit(ctx, spec.labels[i] + '  ' + fmt(v), W - (cx + r + 42) - 4), cx + r + 40, y);
        y += 19;
      });
    }
    spec._pieLegendInside = wide;
  };

  const draw = (canvas, spec, opts = {}) => {
    const W = Math.max(240, opts.w || 330);
    const H = Math.max(160, opts.h || 220);
    const ctx = setup(canvas, W, H);
    ctx.font = 'bold 13px ' + FONT;
    ctx.fillStyle = INK; ctx.textAlign = 'left';
    ctx.fillText(fit(ctx, spec.title || 'Chart', W - 20), 10, 14);
    const multi = spec.series.length > 1;
    const needLegend = multi || (spec.kind === 'pie' && !(W > H * 1.15));
    let items = [];
    if (multi) items = spec.series.map((s, i) => ({ label: s.name, color: colorOf(s.name, i) }));
    else if (spec.kind === 'pie') items = spec.labels.map((l, i) => ({ label: l + ' ' + fmt(spec.series[0].data[i]), color: colorOf(l, i) }));
    let bottom = H - 8;
    if (needLegend) {
      const rows = Math.max(1, Math.ceil(items.length / (W > 420 ? 6 : 3)));
      bottom = H - 10 - rows * 17;
      legend(ctx, items, 10, bottom + 12, W - 20);
    }
    const top = 28;
    ctx.lineWidth = 1;
    if (spec.kind === 'pie') drawPie(ctx, spec, W, H, top, bottom);
    else if (spec.kind === 'line') drawLine(ctx, spec, W, H, top, bottom);
    else drawBars(ctx, spec, W, H, top, bottom);
    return canvas;
  };

  const png = (spec, w = 1100, h = 640) => {
    const c = document.createElement('canvas');
    draw(c, spec, { w, h });
    return c.toDataURL('image/png');
  };

  window.nxChart = { draw, png };
})();
