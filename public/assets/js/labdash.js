/**
 * Lab dashboard. Loads dashboard/data for the chosen period and draws every panel with a small inline-SVG chart kit
 * (line, stacked and grouped columns, horizontal stacked bars, donut, sparkline, ring). Nothing is loaded from the internet.
 */
(() => {
  const root = document.getElementById('ld-root');
  if (!root) return;
  const $ = (s) => root.querySelector(s);
  const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const fmt = (n) => Number(n || 0).toLocaleString('en-US');
  const C = { pass: '#16a34a', tol: '#f59e0b', fail: '#e11d48', ong: '#94a3b8', info: '#2563eb', teal: '#0891b2', violet: '#7c3aed' };
  const MACHINE = ['#2563eb', '#0891b2', '#7c3aed', '#db2777', '#ea580c', '#65a30d'];
  let days = 30;

  // ---- hover card: one floating card for every chart; a chart sets el._tip = (index) => html ---------------------------
  const tipEl = document.createElement('div');
  tipEl.className = 'ld-tipcard';
  tipEl.setAttribute('role', 'tooltip');
  tipEl.hidden = true;
  document.body.appendChild(tipEl);
  const placeTip = (x, y) => {
    const r = tipEl.getBoundingClientRect();
    let left = x + 16, top = y + 16;
    if (left + r.width > window.innerWidth - 8) left = x - r.width - 16;
    if (top + r.height > window.innerHeight - 8) top = Math.max(8, window.innerHeight - r.height - 8);
    tipEl.style.transform = `translate(${Math.max(8, left)}px, ${top}px)`;
  };
  let tipKey = '';
  let pinned = false;
  const unpin = () => { pinned = false; tipEl.classList.remove('ld-pinned'); tipEl.hidden = true; tipKey = ''; root.querySelectorAll('.ld-hot').forEach((h) => h.classList.remove('ld-hot')); };
  // a click on a point keeps its card open (so its links can be used); a click anywhere else or Esc closes it
  root.addEventListener('click', (e) => {
    const hit = e.target.closest('[data-ti]');
    const chart = hit && hit.closest('.ld-chart');
    if (!hit || !chart || !chart._tip) { if (pinned) unpin(); return; }
    pinned = false;
    tipKey = '';
    root.dispatchEvent(new MouseEvent('mousemove', { clientX: e.clientX, clientY: e.clientY }));
    tipEl.innerHTML = chart._tip(+hit.dataset.ti) + '<p class="ld-tt-hint">Click outside this card to close it.</p>';
    tipEl.hidden = false;
    placeTip(e.clientX, e.clientY);
    chart.querySelectorAll(`[data-ti="${hit.dataset.ti}"]`).forEach((h) => h.classList.add('ld-hot'));
    pinned = true;
    tipEl.classList.add('ld-pinned');
    e.stopPropagation();
  });
  document.addEventListener('click', (e) => { if (pinned && !tipEl.contains(e.target) && !root.contains(e.target)) unpin(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && pinned) unpin(); });
  root.addEventListener('mousemove', (e) => {
    if (pinned) return;
    const hit = e.target.closest('[data-ti]');
    const chart = hit && hit.closest('.ld-chart');
    if (!hit || !chart || !chart._tip) { if (!tipEl.hidden) { tipEl.hidden = true; tipKey = ''; root.querySelectorAll('.ld-hot').forEach((h) => h.classList.remove('ld-hot')); } return; }
    const key = chart.id + ':' + hit.dataset.ti;
    if (key !== tipKey) {
      tipKey = key;
      tipEl.innerHTML = chart._tip(+hit.dataset.ti);
      tipEl.hidden = false;
      root.querySelectorAll('.ld-hot').forEach((h) => h.classList.remove('ld-hot'));
      chart.querySelectorAll(`[data-ti="${hit.dataset.ti}"]`).forEach((h) => h.classList.add('ld-hot'));
    }
    placeTip(e.clientX, e.clientY);
  });
  root.addEventListener('mouseleave', () => { if (!pinned) { tipEl.hidden = true; tipKey = ''; } });
  window.addEventListener('scroll', () => { if (pinned) unpin(); else { tipEl.hidden = true; tipKey = ''; } }, { passive: true });
  const tipRows = (title, rows) => `<b class="ld-tt">${esc(title)}</b><table>${rows.map(([n, v, c]) => `<tr><td><i style="background:${c || 'transparent'}"></i>${esc(n)}</td><td>${v}</td></tr>`).join('')}</table>`;
  const seriesTip = (labels, series, unit) => (i) => tipRows(labels[i], series.filter((x) => x.data[i] !== null && x.data[i] !== undefined)
    .map((x) => [x.name, `${fmt(x.data[i])}${unit ? ' ' + esc(unit) : ''}`, x.color]));

  // ---- chart kit ------------------------------------------------------------------------------------------------
  const width = (el) => Math.max(280, Math.floor(el.clientWidth || 600));
  const ticks = (max, n = 4) => {
    const raw = Math.max(max, 1) / n;
    const p = Math.pow(10, Math.floor(Math.log10(raw)));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * p).find((s) => s >= raw) || raw;
    return { step, top: step * n };
  };
  const legend = (items) => `<ul class="ld-legend">${items.map(([n, c]) => `<li><i style="background:${c}"></i>${esc(n)}</li>`).join('')}</ul>`;
  const empty = (msg = 'No data in this period.') => `<p class="ld-empty">${esc(msg)}</p>`;
  const axisY = (W, L, y, t, fmtV = fmt) => {
    let g = '';
    for (let v = 0; v <= t.top + 1e-9; v += t.step) g += `<line x1="${L}" x2="${W - 6}" y1="${y(v)}" y2="${y(v)}" class="gl"/><text x="${L - 8}" y="${y(v) + 4}" text-anchor="end">${fmtV(Math.round(v * 100) / 100)}</text>`;
    return g;
  };
  const xLabels = (labels, x, H, every) => labels.map((l, i) => (i % every === 0 ? `<text x="${x(i)}" y="${H - 6}" text-anchor="middle">${esc(l)}</text>` : '')).join('');

  /** series: [{name, color, data[], area?, dash?}] ; opts: {std, unit} */
  const line = (el, labels, series, opts = {}) => {
    if (!labels.length || !series.some((s) => s.data.some((v) => v))) { el.innerHTML = empty(); return; }
    const W = width(el), H = opts.h || 260, L = 42, B = 26, T = 14;
    const max = Math.max(opts.std || 0, ...series.flatMap((s) => s.data.map((v) => v || 0)));
    const t = ticks(max * 1.1);
    const y = (v) => T + (H - T - B) * (1 - v / t.top);
    const step = labels.length > 1 ? (W - L - 16) / (labels.length - 1) : 0;
    const x = (i) => L + 8 + i * step;
    let g = axisY(W, L, y, t);
    if (opts.std) g += `<line x1="${L}" x2="${W - 6}" y1="${y(opts.std)}" y2="${y(opts.std)}" class="std"/><text x="${W - 8}" y="${y(opts.std) - 6}" text-anchor="end" style="fill:#be123c">${opts.std} ${esc(opts.unit || '')}</text>`;
    series.forEach((s, k) => {
      const pts = s.data.map((v, i) => (v === null || v === undefined ? null : [x(i), y(v)]));
      const segs = [];
      let cur = [];
      pts.forEach((p) => { if (p) cur.push(p); else if (cur.length) { segs.push(cur); cur = []; } });
      if (cur.length) segs.push(cur);
      segs.forEach((sg) => {
        const d = sg.map((p, i) => `${i ? 'L' : 'M'}${p[0].toFixed(1)} ${p[1].toFixed(1)}`).join('');
        if (s.area) g += `<path d="${d}L${sg[sg.length - 1][0]} ${y(0)}L${sg[0][0]} ${y(0)}Z" fill="url(#ld-a${k})"/>`;
        g += `<path d="${d}" fill="none" stroke="${s.color}" stroke-width="${s.width || 2.5}" stroke-linejoin="round" stroke-linecap="round" ${s.dash ? 'stroke-dasharray="4 4"' : ''}/>`;
      });
      if (labels.length <= 31) pts.forEach((p, i) => { if (p) g += `<circle cx="${p[0]}" cy="${p[1]}" r="3.2" fill="#fff" stroke="${s.color}" stroke-width="2" class="ld-dot" data-ti="${i}"/>`; });
    });
    const defs = series.map((s, k) => `<linearGradient id="ld-a${k}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="${s.color}" stop-opacity=".28"/><stop offset="1" stop-color="${s.color}" stop-opacity="0"/></linearGradient>`).join('');
    g += xLabels(labels, x, H, Math.ceil(labels.length / Math.max(4, Math.floor((W - L) / 64))));
    const band = labels.length > 1 ? step : W - L;
    labels.forEach((_, i) => {
      g += `<line x1="${x(i)}" x2="${x(i)}" y1="${T}" y2="${y(0)}" class="ld-guide" data-ti="${i}"/><rect x="${x(i) - band / 2}" y="${T}" width="${band}" height="${y(0) - T}" class="hit" data-ti="${i}"/>`;
    });
    el.innerHTML = `<svg class="ld-svg" viewBox="0 0 ${W} ${H}" height="${H}" role="img" aria-label="${esc(opts.label || 'Line chart')}"><defs>${defs}</defs>${g}</svg>${legend(series.map((s) => [s.name, s.color]))}`;
    el._tip = opts.tip || seriesTip(labels, series, opts.unit);
  };

  /** columns: stacked when opts.stacked, otherwise grouped side by side */
  const columns = (el, labels, series, opts = {}) => {
    const used = series.filter((s) => s.data.some((v) => v));
    if (!labels.length || !used.length) { el.innerHTML = empty(opts.emptyText); return; }
    const W = width(el), H = opts.h || 260, L = 38, B = 26, T = 18;
    const totals = labels.map((_, i) => used.reduce((a, s) => a + (s.data[i] || 0), 0));
    const max = opts.stacked ? Math.max(...totals) : Math.max(...used.flatMap((s) => s.data));
    const t = ticks(max * 1.08);
    const y = (v) => T + (H - T - B) * (1 - v / t.top);
    const cw = (W - L - 8) / labels.length;
    const x = (i) => L + i * cw + cw / 2;
    let g = axisY(W, L, y, t);
    labels.forEach((lab, i) => {
      const bw = Math.min(46, cw * 0.66);
      if (opts.stacked) {
        let acc = 0;
        used.forEach((s) => {
          const v = s.data[i] || 0;
          if (!v) return;
          g += `<rect x="${x(i) - bw / 2}" y="${y(acc + v)}" width="${bw}" height="${Math.max(0, y(acc) - y(acc + v))}" rx="3" fill="${s.color}"/>`;
          acc += v;
        });
        if (totals[i] && cw > 22) g += `<text x="${x(i)}" y="${y(totals[i]) - 5}" text-anchor="middle" class="v">${fmt(totals[i])}</text>`;
      } else {
        const each = bw / used.length;
        used.forEach((s, k) => {
          const v = s.data[i] || 0;
          g += `<rect x="${x(i) - bw / 2 + k * each}" y="${y(v)}" width="${Math.max(1, each - 2)}" height="${Math.max(0, y(0) - y(v))}" rx="3" fill="${s.color}"/>`;
        });
      }
    });
    g += xLabels(labels, x, H, Math.ceil(labels.length / Math.max(4, Math.floor((W - L) / 64))));
    labels.forEach((_, i) => { g += `<rect x="${x(i) - cw / 2}" y="${T}" width="${cw}" height="${y(0) - T}" class="hit" data-ti="${i}"/>`; });
    el.innerHTML = `<svg class="ld-svg" viewBox="0 0 ${W} ${H}" height="${H}" role="img" aria-label="${esc(opts.label || 'Column chart')}">${g}</svg>${legend(used.map((s) => [s.name, s.color]))}`;
    el._tip = opts.tip || ((i) => tipRows(labels[i], used.map((s) => [s.name, fmt(s.data[i] || 0), s.color]).concat(opts.stacked ? [['Total', `<b>${fmt(totals[i])}</b>`]] : [])));
  };

  /** rows: [{label, parts:[{name,value,color}]}] horizontal stacked bars with the total at the end */
  const hbars = (el, rows, opts = {}) => {
    if (!rows.length) { el.innerHTML = empty(); return; }
    const W = width(el), lw = Math.min(opts.labelW || 170, W * 0.38), rowH = 30, H = rows.length * rowH + 4;
    const max = Math.max(1, ...rows.map((r) => r.parts.reduce((a, p) => a + p.value, 0)));
    const bw = W - lw - 52;
    let g = '';
    rows.forEach((r, i) => {
      const yy = i * rowH + 6;
      let acc = 0;
      const tot = r.parts.reduce((a, p) => a + p.value, 0);
      g += `<text x="0" y="${yy + 14}" style="fill:#0f172a">${esc(r.label.length > 26 ? r.label.slice(0, 25) + '…' : r.label)}<title>${esc(r.label)}</title></text>`;
      g += `<rect x="${lw}" y="${yy + 3}" width="${bw}" height="14" rx="7" fill="rgb(148 163 184 / .22)"/>`;
      r.parts.forEach((p) => {
        if (!p.value) return;
        const w = (p.value / max) * bw;
        g += `<rect x="${lw + acc}" y="${yy + 3}" width="${w}" height="14" rx="${acc ? 0 : 7}" fill="${p.color}"/>`;
        acc += w;
      });
      g += `<text x="${W - 4}" y="${yy + 15}" text-anchor="end" class="v">${fmt(tot)}</text><rect x="0" y="${yy - 2}" width="${W}" height="${rowH}" class="hit" data-ti="${i}"/>`;
    });
    const names = [];
    rows.forEach((r) => r.parts.forEach((p) => { if (!names.some((n) => n[0] === p.name)) names.push([p.name, p.color]); }));
    el.innerHTML = `<svg class="ld-svg" viewBox="0 0 ${W} ${H}" height="${H}" role="img" aria-label="${esc(opts.label || 'Bar chart')}">${g}</svg>${opts.legend === false ? '' : legend(names)}`;
    el._tip = opts.tip || ((i) => {
      const r = rows[i];
      const tot = r.parts.reduce((a, p) => a + p.value, 0);
      return tipRows(r.label, r.parts.map((p) => [p.name, `${fmt(p.value)} <small>${tot ? Math.round((p.value / tot) * 100) : 0}%</small>`, p.color]).concat([['Total', `<b>${fmt(tot)}</b>`]]));
    });
  };

  /** parts: [{name, value, color}] */
  const donut = (el, parts, centerLabel) => {
    const tot = parts.reduce((a, p) => a + p.value, 0);
    if (!tot) { el.innerHTML = empty(); return; }
    const R = 70, r = 46, cx = 95, cy = 95;
    let a0 = -Math.PI / 2, g = '';
    parts.forEach((p, i) => {
      if (!p.value) return;
      const a1 = a0 + (p.value / tot) * Math.PI * 2 - 0.0001;
      const big = a1 - a0 > Math.PI ? 1 : 0;
      const pt = (rad, a) => `${(cx + rad * Math.cos(a)).toFixed(2)} ${(cy + rad * Math.sin(a)).toFixed(2)}`;
      g += `<path d="M${pt(R, a0)}A${R} ${R} 0 ${big} 1 ${pt(R, a1)}L${pt(r, a1)}A${r} ${r} 0 ${big} 0 ${pt(r, a0)}Z" fill="${p.color}" class="ld-slice" data-ti="${i}"/>`;
      a0 = a1 + 0.0001;
    });
    g += `<text x="${cx}" y="${cy + 2}" text-anchor="middle" class="v" style="font-size:22px">${fmt(tot)}</text><text x="${cx}" y="${cy + 20}" text-anchor="middle">${esc(centerLabel)}</text>`;
    el.innerHTML = `<div class="ld-donut"><svg class="ld-svg" viewBox="0 0 190 190" role="img" aria-label="${esc(centerLabel)} split">${g}</svg>
      <ul class="ld-legend">${parts.map((p, i) => `<li data-ti="${i}"><i style="background:${p.color}"></i>${esc(p.name)}<b>${fmt(p.value)}</b> <span>${Math.round((p.value / tot) * 100)}%</span></li>`).join('')}</ul></div>`;
    el._tip = (i) => tipRows(parts[i].name, [['Count', `<b>${fmt(parts[i].value)}</b>`, parts[i].color], ['Share', `${Math.round((parts[i].value / tot) * 100)}%`], ['Out of', fmt(tot)]]);
  };

  const spark = (data, color) => {
    if (!data.length) return '';
    const W = 200, H = 38, max = Math.max(1, ...data);
    const x = (i) => (data.length > 1 ? (i / (data.length - 1)) * W : W / 2);
    const y = (v) => H - 3 - (v / max) * (H - 8);
    const d = data.map((v, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)} ${y(v).toFixed(1)}`).join('');
    const id = 's' + Math.random().toString(36).slice(2, 8);
    return `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="${id}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="${color}" stop-opacity=".3"/><stop offset="1" stop-color="${color}" stop-opacity="0"/></linearGradient></defs>
      <path d="${d}L${W} ${H}L0 ${H}Z" fill="url(#${id})"/><path d="${d}" fill="none" stroke="${color}" stroke-width="2" vector-effect="non-scaling-stroke"/></svg>`;
  };

  const ring = (pct, color) => {
    const c = 2 * Math.PI * 18;
    return `<svg class="ld-ring" viewBox="0 0 46 46" role="img" aria-label="${pct}%"><circle cx="23" cy="23" r="18" fill="none" stroke="rgb(148 163 184 / .25)" stroke-width="5"/>
      <circle cx="23" cy="23" r="18" fill="none" stroke="${color}" stroke-width="5" stroke-linecap="round" stroke-dasharray="${(Math.min(100, pct) / 100) * c} ${c}" transform="rotate(-90 23 23)"/>
      <text x="23" y="27" text-anchor="middle">${Math.round(pct)}%</text></svg>`;
  };

  // ---- panels ---------------------------------------------------------------------------------------------------
  const DTR = (root.dataset.dtr || '').replace(/\/?$/, '/');
  const refLink = (slug, sid, ref) => (sid > 0 && slug ? `${DTR.replace(/\/$/, '')}?open=${encodeURIComponent(slug)}:${sid}` : `${DTR.replace(/\/$/, '')}?open=bonding&ref=${encodeURIComponent(ref)}`);
  const resClass = (res) => (/^P/i.test(res) ? 'pass' : /^T/i.test(res) ? 'tol' : /^F/i.test(res) ? 'fail' : '');
  const oeeColor = (v, target) => (v >= target ? C.pass : v >= target * 0.6 ? C.tol : C.fail);

  const kpis = (d) => {
    const s = d.daily.series;
    const tot = s.reduce((a, r) => a + r.total, 0);
    const fails = s.reduce((a, r) => a + r.fail, 0);
    const mix = d.daily.mix || {};
    const judged = (+mix.Pass || 0) + (+mix.Tolerable || 0) + (+mix.Fail || 0);
    const rate = judged ? Math.round((((+mix.Pass || 0) + (+mix.Tolerable || 0)) / judged) * 1000) / 10 : 0;
    const sum = (k) => Object.values(d.tiles).reduce((a, t) => a + (t[k] || 0), 0);
    const rateSeries = s.map((r) => (r.total ? ((r.total - r.fail) / r.total) * 100 : 0));
    const cards = [
      ['Tests finished', fmt(judged), 'in the period', 'k-info', spark(s.map((r) => r.total), C.info)],
      ['Pass rate', `${rate}%`, 'pass and tolerable', 'k-pass', spark(rateSeries, C.pass)],
      ['Failures', fmt(+mix.Fail || 0), `${fails} on the chart days`, 'k-fail', spark(s.map((r) => r.fail), C.fail)],
      ['Ongoing', fmt(sum('ongoing')), 'not finished yet', 'k-info', spark(s.map((r) => r.bonding), C.teal)],
      ['For report', fmt(sum('for_report')), 'waiting for approval', 'k-tol', spark(s.map((r) => r.tol), C.tol)],
    ];
    $('#ld-kpis').innerHTML = cards.map(([l, v, sub, cls, sp]) => `<div class="ld-glass ld-kpi ${cls}"><small>${l}</small><b>${v}</b><em>${sub}</em>${sp}</div>`).join('');
  };

  const gauge = (oee, target) => {
    const v = Math.max(0, Math.min(100, oee));
    $('#ld-g-val').setAttribute('stroke-dasharray', `${v} 100`);
    const rot = (p) => `rotate(${-90 + p * 1.8} 120 120)`;
    $('#ld-g-needle').setAttribute('transform', rot(v));
    $('#ld-g-target').setAttribute('transform', rot(target));
    $('#ld-oee').textContent = `${oee}%`;
    $('#ld-target').textContent = `${target}%`;
  };

  // ---- sections: each one is fetched on its own, drawn on its own and swaps its shimmer for content when ready ---------
  const bar = (l, v) => `<div class="ld-mbar"><span>${l}</span><i><em style="width:${Math.min(100, v)}%"></em></i><b>${v}%</b></div>`;
  const IL = { Completed: C.pass, Submitted: C.info, 'In Progress': C.tol, Draft: C.ong, 'Submit Cancel': C.fail };

  const SECTIONS = {
    overview: {
      fill(d) {
        $('#ld-range').textContent = `${d.days === 1 ? 'Today' : `Last ${d.days} days`}, ${d.from} to ${d.to}. Updated ${d.updated}.`;
        Object.entries(d.tiles).forEach(([g, t]) => Object.entries(t).forEach(([k, n]) => {
          const el = root.querySelector(`[data-tile="${k}.${g}"]`);
          if (el) el.textContent = fmt(n);
        }));
        kpis(d);
      },
      draw(d) {
        const s = d.daily.series;
        line($('#ld-ch-daily'), s.map((r) => r.label), [
          { name: 'Finished', color: C.info, data: s.map((r) => r.total), area: true },
          { name: 'Bonding', color: C.teal, data: s.map((r) => r.bonding) },
          { name: 'Failed', color: C.fail, data: s.map((r) => r.fail) },
        ], {
          label: 'Lab references finished each day',
          tip: (i) => {
            const r = s[i];
            const passed = r.total - r.fail - r.tol;
            const more = r.total - r.refs.length;
            return `${tipRows(r.title, [['Finished (all tests)', `<b>${fmt(r.total)}</b>`, C.info], ['of which bonding', fmt(r.bonding), C.teal],
              ['Passed', fmt(passed), C.pass], ['Tolerable', fmt(r.tol), C.tol], ['Failed', fmt(r.fail), C.fail]])}
              ${r.refs.length ? `<div class="ld-tt-refs"><small>Lab references</small><ul>${r.refs.map((x) => `<li><a class="ld-mono" href="${esc(refLink(x.slug, x.sid, x.ref))}" title="Open ${esc(x.ref)} in ${esc(x.cat)}">${esc(x.ref)}</a><em>${esc(x.cat)}</em><span class="ld-res ${resClass(x.res)}">${esc(x.res)}</span></li>`).join('')}</ul>${more > 0 ? `<small>and ${fmt(more)} more</small>` : ''}</div>` : '<p class="ld-tt-none">Nothing finished on this day.</p>'}`;
          },
        });
        const bm = d.daily.bonding_mix || {};
        donut($('#ld-ch-mix'), [
          { name: 'Pass', value: +bm.Pass || 0, color: C.pass }, { name: 'Tolerable', value: +bm.Tolerable || 0, color: C.tol },
          { name: 'Fail', value: +bm.Fail || 0, color: C.fail }, { name: 'No result', value: +bm.Other || 0, color: C.ong },
        ], 'bonding tests');
      },
    },
    bonding: {
      fill(d) {
        gauge(d.oee.overall, d.oee.target);
        $('#ld-oee-note').textContent = `Average of testers used; capacity ${d.oee.capacity} tests a day, ${d.oee.working_days} working days`;
        $('#ld-machines').innerHTML = d.oee.machines.map((m, i) => `
          <div class="ld-m ${m.tests ? '' : 'idle'}" style="border-top:3px solid ${MACHINE[i % 6]}">
            <header><b>${esc(m.code)}</b>${m.tests ? ring(m.oee, oeeColor(m.oee, d.oee.target)) : '<span class="ld-res">Idle</span>'}</header>
            ${bar('Availability', m.availability)}${bar('Performance', m.performance)}${bar('Quality', m.quality)}
            <footer>${fmt(m.tests)} tests, ${m.fails} failed, last ${m.last_test ? esc(m.last_test) : 'none'}</footer>
          </div>`).join('');
        $('#ld-pull').innerHTML = d.pull.length ? `<table class="ld-table"><thead><tr><th>Method</th><th>Tester</th><th class="num">Samples</th><th class="num">Pulls</th><th class="num">Avg peak</th><th class="num">Min</th><th class="num">Max</th><th>Last test</th></tr></thead><tbody>
          ${d.pull.map((p) => `<tr><td>${esc(p.method)}</td><td>${esc(p.machine)}</td><td class="num">${p.samples}</td><td class="num">${p.pulls}</td><td class="num"><b>${p.avg_peak ?? '–'}</b></td><td class="num">${p.min_peak ?? '–'}</td><td class="num">${p.max_peak ?? '–'}</td><td>${esc(p.last_test)}</td></tr>`).join('')}</tbody></table>`
          : empty('No pull, eyelet or rivet tests in this period.');
        $('#ld-ongoing').innerHTML = d.ongoing.length ? d.ongoing.map((r) => `<tr${r.lab_ref ? ` class="ld-link-row" data-href="${esc(refLink('bonding', +r.bonding_id || 0, r.lab_ref))}"` : ''}><td class="ld-mono">${r.lab_ref ? `<a href="${esc(refLink('bonding', +r.bonding_id || 0, r.lab_ref))}">${esc(r.lab_ref)}</a>` : '–'}</td><td>${esc(r.prod_code)}</td><td>${esc(r.style)}</td><td>${esc(r.line)}</td>
          <td>${esc(r.prod_date)}</td><td>${esc(r.test_date || '–')}</td><td>${esc(r.technician || '–')}</td><td><span class="ld-res ${resClass(r.result)}">${esc(r.result)}</span></td></tr>`).join('')
          : '<tr><td colspan="8">Nothing on the bonding tracker.</td></tr>';
      },
      draw(d) {
        columns($('#ld-ch-trend'), d.trend.labels, d.trend.series.map((x, i) => ({ name: x.name, color: MACHINE[i % 6], data: x.data })),
          { stacked: true, label: 'Bonding tests per day by tester', emptyText: 'No bonding tests on the testers in the last 14 days.' });
        line($('#ld-ch-strength'), d.strength.labels, [
          { name: 'Upper to Foxing', color: C.info, data: d.strength.utf },
          { name: 'Foxing to Outsole', color: C.violet, data: d.strength.fto },
          { name: 'Filler to Outsole', color: C.teal, data: d.strength.fito, dash: true },
        ], { std: d.strength.standard, unit: 'kgf/cm', label: 'Average bond strength' });
        hbars($('#ld-ch-parts'), d.strength.parts.map((p) => ({ label: p.name, parts: [
          { name: 'Pass', value: p.pass, color: C.pass }, { name: 'Tolerable', value: p.tol, color: C.tol }, { name: 'Fail', value: p.fail, color: C.fail }] })), { labelW: 130 });
      },
    },
    materials: {
      fill(d) {
        $('#ld-items-note').textContent = `${d.items.kinds} different test items in this period, most failures first`;
        const im = Math.max(1, ...d.items.by_item.map((r) => r.pass + r.tol + r.fail));
        $('#ld-items').innerHTML = d.items.by_item.length ? `<table class="ld-table"><thead><tr><th>Test item</th><th>Category</th><th class="num">Runs</th><th class="num">Fail</th><th>Split</th></tr></thead><tbody>
          ${d.items.by_item.map((r) => { const n = r.pass + r.tol + r.fail; return `<tr><td><b>${esc(r.test)}</b></td><td>${esc(r.category)}</td><td class="num">${n}</td><td class="num">${r.fail ? `<span class="ld-res fail">${r.fail}</span>` : '0'}</td>
            <td><span class="ld-mini" title="${r.pass} pass, ${r.tol} tolerable, ${r.fail} fail" style="width:${Math.max(30, (n / im) * 120)}px"><em style="flex:${r.pass};background:${C.pass}"></em><em style="flex:${r.tol};background:${C.tol}"></em><em style="flex:${r.fail};background:${C.fail}"></em></span></td></tr>`; }).join('')}</tbody></table>`
          : empty();
        $('#ld-running').innerHTML = d.running.length ? d.running.map((m) => {
          const p = m.target > 0 ? Math.min(100, Math.round((m.current / m.target) * 100)) : 0;
          const run = m.status === 'RUNNING';
          return `<div class="ld-p"><div class="ld-p-h"><b>${esc(m.lab_ref)}</b><small>${esc(m.machine)}, ${esc(String(m.category).replace(/-/g, ' '))}</small></div>
            <div class="ld-prog" role="progressbar" aria-valuenow="${p}" aria-valuemin="0" aria-valuemax="100" aria-label="${esc(m.lab_ref)} cycles"><em class="${!run || p >= 100 ? 'done' : p < 75 ? 'warm' : ''}" style="width:${p}%"></em></div>
            <div class="ld-p-f"><span>${fmt(m.current)} of ${fmt(m.target)} cycles, ${run ? 'running' : 'completed'}</span><b class="${!run || p >= 100 ? 'ld-ok' : ''}">${!run ? 'Done' : p + '%'}</b></div></div>`;
        }).join('') : empty('No flex or abrasion tests recorded.');
      },
      draw(d) {
        hbars($('#ld-ch-cats'), d.category.map((c) => ({ label: c.name, parts: [
          { name: 'Pass', value: c.pass, color: C.pass }, { name: 'Tolerable', value: c.tolerable, color: C.tol },
          { name: 'Fail', value: c.fail, color: C.fail }, { name: 'Ongoing', value: c.ongoing, color: C.ong }] })));
        hbars($('#ld-ch-tests'), d.items.by_test.slice(0, 12).map((t) => ({ label: t.test, parts: [
          { name: 'Passed', value: t.total - t.fail, color: C.info }, { name: 'Failed', value: t.fail, color: C.fail }] })), { labelW: 190 });
      },
    },
    wholeshoe: {
      fill(d) {
        $('#ld-tracking').innerHTML = d.tracking.length ? d.tracking.map((t) => {
          const p = t.total ? Math.round((t.done / t.total) * 100) : 0;
          return `<div class="ld-p"><div class="ld-p-h"><b>${esc(t.test)}</b><small>${esc(t.group)}</small></div>
            <div class="ld-prog" role="progressbar" aria-valuenow="${p}" aria-valuemin="0" aria-valuemax="100" aria-label="${esc(t.test)} progress"><em class="warm" style="width:${p}%"></em></div>
            <div class="ld-p-f"><span>${t.done} of ${t.total} finished${t.fail ? `, ${t.fail} failed` : ''}</span><b>${p}%</b></div></div>`;
        }).join('') : empty('No tracked tests in this period.');
      },
    },
    people: {
      fill(d) {
        $('#ld-supplier').innerHTML = d.supplier.length ? `<table class="ld-table"><thead><tr><th>Supplier</th><th class="num">Tested</th><th class="num">Tolerable</th><th class="num">Failed</th><th class="num">Fail rate</th></tr></thead><tbody>
          ${d.supplier.map((s) => `<tr><td>${esc(s.name)}</td><td class="num">${s.n}</td><td class="num">${s.t}</td><td class="num">${+s.f ? `<span class="ld-res fail">${s.f}</span>` : '0'}</td><td class="num">${s.n ? Math.round((s.f / s.n) * 1000) / 10 : 0}%</td></tr>`).join('')}</tbody></table>`
          : empty();
      },
      draw(d) {
        hbars($('#ld-ch-people'), d.people.map((p) => ({ label: p.name, parts: [
          { name: 'Passed or tolerable', value: p.n - p.f, color: C.info }, { name: 'Failed', value: +p.f, color: C.fail }] })), { labelW: 180 });
      },
    },
    reports: {
      fill(d) {
        $('#ld-approvals').innerHTML = `<div><b style="color:#b45309">${d.reports.pending_approvals}</b><small>Waiting for a signatory</small></div><div><b style="color:#15803d">${d.reports.approved_7d}</b><small>Approved in the last 7 days</small></div>`;
      },
      draw(d) {
        donut($('#ld-ch-il'), d.reports.interlink.map((r, i) => ({ name: r.status, value: +r.n, color: IL[r.status] || MACHINE[i % 6] })), 'applications');
      },
    },
  };
  const HELP = {
    'ld-ch-daily': 'Each point is one day (one week in the 90-day view). Finished: every lab reference that got its final result that day, all test types together. Bonding: the whole shoe bonding ones among them. Failed: the ones whose final result was Fail. Point at a day to see its lab references.',
    'ld-ch-mix': 'Whole shoe bonding lab references finished in the period, split by final result. Tolerable is within the allowed tolerance but below the standard.',
    'ld-g-val': 'Overall equipment effectiveness of the six bonding testers: Availability (days used out of working days) × Performance (tests done against 12 a day) × Quality (pass and tolerable out of all judged). The dashed mark is the 80% target.',
    'ld-machines': 'One card per bonding tester. The ring is its OEE; the three bars are the parts it is made of. Idle means the tester recorded no test in the period.',
    'ld-ch-trend': 'Bonding tests read from each tester per day over the last 14 days, stacked by tester. The number on top is the day total. Point at a day for the split.',
    'ld-ch-strength': 'Average bond strength per day in kgf/cm from the tester readings: Upper to Foxing, Foxing to Outsole and Filler to Outsole (dashed). The red dashed line is the 2.5 kgf/cm minimum.',
    'ld-ch-parts': 'Every bonding reading in the period, per bonded part, by result.',
    'ld-pull': 'Pull tests done on the testers (eyelet, rivet and similar tensile pulls): how many samples and pulls, and the peak force reached in kgf.',
    'ld-ongoing': 'Samples collected from the production lines on the bonding tracker, newest first. Waiting means collected but not tested yet; Shutdown means the line was not running.',
    'ld-ch-cats': 'Lab references per material test category in the period: pass, tolerable, fail, and those still in progress.',
    'ld-items': 'The individual tests inside the reports (tensile, tear, crocking, bonding parts...), how often each ran in the period and how often it failed. Most failures first.',
    'ld-ch-tests': 'The tests run most often in the period, all categories together, with how many failed.',
    'ld-running': 'Flex and abrasion testers: cycles done against the target cycles. Green: completed. Orange: running and under 75%. Blue: running and 75% or more.',
    'ld-tracking': 'Whole shoe test types collected in the period and how many of them already have a final result.',
    'ld-ch-people': 'Lab references each technician finished in the period, with how many failed.',
    'ld-supplier': 'Suppliers of the materials tested in the period, the ones with most failures first. Fail rate is failed out of tested.',
    'ld-ch-il': 'All InterLink applications stored in NEXUS-L by their InterLink job status.',
    'ld-approvals': 'Reports sent to a signatory for approval: still waiting, and approved in the last 7 days.',
  };
  Object.entries(HELP).forEach(([id, text]) => {
    const card = document.getElementById(id)?.closest('.ld-glass');
    const h = card && card.querySelector('.ld-ch h3');
    if (!h || h.querySelector('.ld-info')) return;
    h.insertAdjacentHTML('beforeend', `<span class="ld-info" tabindex="0" role="note" aria-label="${esc(text)}" data-tip="${esc(text)}">i</span>`);
  });

  $('#ld-ongoing').addEventListener('click', (e) => {
    if (e.target.closest('a')) return;
    const tr = e.target.closest('tr[data-href]');
    if (tr) location.href = tr.dataset.href;
  });

  const ORDER = Object.keys(SECTIONS);
  const secEl = (part) => document.getElementById(`ld-s-${part}`);
  const data = {};          // last answer per section, for redrawing on resize
  let gen = 0;              // bumps on every period change, so late answers for an old period are dropped
  const inflight = {};

  // a background tab gets no animation frames, so there the section is drawn on a plain timer instead
  const nextFrame = (fn) => (document.hidden ? setTimeout(fn, 0) : requestAnimationFrame(fn));

  const show = (part, d) => {
    data[part] = d;
    const el = secEl(part);
    nextFrame(() => {                         // one section per frame keeps scrolling smooth while the rest arrives
      const S = SECTIONS[part];
      if (S.fill) S.fill(d);
      if (S.draw) S.draw(d);
      el.classList.remove('ld-wait', 'ld-error');
      el.classList.add('ld-ready');
      el.setAttribute('aria-busy', 'false');
    });
  };

  const fetchPart = async (part, quiet) => {
    if (inflight[part] === gen) return;
    const my = gen;
    inflight[part] = my;
    const el = secEl(part);
    if (!quiet) { el.classList.add('ld-wait'); el.classList.remove('ld-ready'); el.setAttribute('aria-busy', 'true'); }
    try {
      const r = await fetch(`${root.dataset.src}?part=${part}&days=${days}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const d = await r.json();
      if (my !== gen) return;
      if (!d.ok) throw new Error('No data');
      show(part, d);
    } catch (e) {
      if (my !== gen) return;
      el.classList.remove('ld-wait');
      el.classList.add('ld-error');
      if (part === 'overview') $('#ld-range').textContent = 'Could not load the dashboard. It will try again in two minutes.';
    } finally {
      if (inflight[part] === my) delete inflight[part];
    }
  };

  // overview and bonding load at once; the lower sections when they come within reach of the screen
  const near = new Set();
  const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => entries.forEach((en) => {
    const part = en.target.id.replace('ld-s-', '');
    if (!en.isIntersecting) { near.delete(part); return; }
    near.add(part);
    if (ORDER.indexOf(part) > 1 && !data[part]) fetchPart(part);
  }), { rootMargin: '600px 0px' }) : null;

  const start = (quiet) => {
    if (!quiet) {
      gen++;
      ORDER.forEach((p) => { delete data[p]; secEl(p).classList.add('ld-wait'); secEl(p).classList.remove('ld-ready'); });
    }
    fetchPart('overview', quiet).then(() => fetchPart('bonding', quiet));
    ORDER.slice(2).forEach((p) => {
      if (quiet ? data[p] : (!io || near.has(p))) fetchPart(p, quiet);
    });
  };

  ORDER.forEach((p) => { secEl(p).classList.add('ld-wait'); secEl(p).setAttribute('aria-busy', 'true'); if (io) io.observe(secEl(p)); });
  root.querySelector('.ld-seg').addEventListener('click', (e) => {
    const b = e.target.closest('[data-days]');
    if (!b || +b.dataset.days === days) return;
    days = +b.dataset.days;
    root.querySelectorAll('.ld-seg [data-days]').forEach((x) => x.setAttribute('aria-checked', String(x === b)));
    start(false);
  });
  $('#ld-refresh').addEventListener('click', () => start(false));
  let rz;
  window.addEventListener('resize', () => {
    clearTimeout(rz);
    rz = setTimeout(() => ORDER.forEach((p) => { if (data[p] && SECTIONS[p].draw) SECTIONS[p].draw(data[p]); }), 180);
  });
  start(false);
  // quiet refresh every two minutes while the tab is visible: content stays on screen and is swapped when the answer arrives
  const timer = setInterval(() => { if (!document.hidden) start(true); }, 120000);
  window.addEventListener('pagehide', () => clearInterval(timer));
})();
