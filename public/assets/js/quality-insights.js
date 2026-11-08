/**
 * Quality Insights: line map, failure modes, supplier scorecard.
 * Every view is drawn at once from a short-lived cache when it was seen a moment ago, otherwise a shimmer stands in while the numbers load.
 */
(() => {
  'use strict';
  const root = document.getElementById('qi-root');
  if (!root) return;
  const base = root.dataset.base.replace(/\/?$/, '/');
  const today = root.dataset.today;
  const $ = (s, c = root) => c.querySelector(s);
  const $$ = (s, c = root) => [...c.querySelectorAll(s)];
  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const state = { tab: 'lines', view: 'month', cur: new Date(today + 'T12:00:00'), group: 'line', supMode: 'period', min: 5 };
  const cache = new Map();                 // url -> { t, data }
  const FRESH = 20000;
  const pending = {};                      // one live request per panel

  // ---------------------------------------------------------------- data
  async function get(path, params, panel, force) {
    const url = base + path + '?' + new URLSearchParams(params).toString();
    const hit = cache.get(url);
    if (hit && !force && Date.now() - hit.t < FRESH) return { data: hit.data, cached: true };
    if (pending[panel]) pending[panel].abort();
    const ac = pending[panel] = new AbortController();
    const res = await fetch(url, { headers: { Accept: 'application/json' }, signal: ac.signal, credentials: 'same-origin' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    if (!data || !data.ok) throw new Error((data && data.error) || 'Bad answer');
    cache.set(url, { t: Date.now(), data });
    return { data, cached: false };
  }
  const peek = (path, params) => { const h = cache.get(base + path + '?' + new URLSearchParams(params).toString()); return h ? h.data : null; };

  // ---------------------------------------------------------------- shimmer
  const skelKpis = (n) => Array.from({ length: n }, () => '<div class="qi-kpi"><i class="qi-skel" style="width:48px;height:24px"></i><i class="qi-skel" style="width:90px;height:11px;margin-top:8px"></i></div>').join('');
  const skelTiles = () => ['A', 'B'].map(() => '<div class="qi-building"><i class="qi-skel" style="width:90px;height:14px;margin-bottom:10px"></i><div class="qi-lines">'
    + Array.from({ length: 12 }, () => '<i class="qi-skel" style="height:92px;border-radius:14px"></i>').join('') + '</div></div>').join('');
  const skelRows = (n) => '<div>' + Array.from({ length: n }, () => '<i class="qi-skel" style="height:18px;margin:12px 0"></i>').join('') + '</div>';

  const err = (el, msg, retry) => { el.innerHTML = `<div class="qi-empty">${esc(msg)} <button type="button" class="cal-btn-glass" data-retry>Try again</button></div>`; const b = $('[data-retry]', el); if (b) b.addEventListener('click', retry); };

  // ---------------------------------------------------------------- line map
  const dayStr = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const ymd = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  /** the period chosen with the calendar control: a day, a Monday-Sunday week, or a month */
  function period() {
    const c = new Date(state.cur);
    if (state.view === 'day') return { from: ymd(c), to: ymd(c) };
    if (state.view === 'week') {
      const mon = new Date(c); mon.setDate(c.getDate() + ((c.getDay() === 0 ? -6 : 1) - c.getDay()));
      const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
      return { from: ymd(mon), to: ymd(sun) };
    }
    return { from: ymd(new Date(c.getFullYear(), c.getMonth(), 1)), to: ymd(new Date(c.getFullYear(), c.getMonth() + 1, 0)) };
  }
  function periodTitle() {
    const p = period(); const a = new Date(p.from + 'T12:00:00'); const b = new Date(p.to + 'T12:00:00');
    if (state.view === 'day') return a.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    if (state.view === 'month') return MONTHS[a.getMonth()] + ', ' + a.getFullYear();
    const sm = MONTHS[a.getMonth()].slice(0, 3); const em = MONTHS[b.getMonth()].slice(0, 3);
    return sm === em ? `${sm} ${a.getDate()} – ${b.getDate()}, ${b.getFullYear()}` : `${sm} ${a.getDate()} – ${em} ${b.getDate()}, ${b.getFullYear()}`;
  }
  const periodWords = () => state.view === 'day' ? 'this day' : state.view === 'week' ? 'this week' : 'this month';
  const shiftDay = (s, n) => { const d = new Date(s + 'T12:00:00'); d.setDate(d.getDate() + n); return dayStr(d); };
  const fmtDay = (s) => new Date(s + 'T12:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

  const kpi = (n, label, cls) => `<div class="qi-kpi ${cls || ''}"><b>${esc(n)}</b><span>${esc(label)}</span></div>`;

  const plural = (n, one, many) => n + ' ' + (n === 1 ? one : (many || one + 's'));
  const stack = (l) => {
    const tot = l.pass + l.tolerable + l.fail + l.pending;
    if (!tot) return '<div class="qi-stack"></div>';
    const w = (n) => (n * 100 / tot).toFixed(1) + '%';
    return '<div class="qi-stack" aria-hidden="true">' + (l.pass ? `<i class="p" style="width:${w(l.pass)}"></i>` : '') + (l.tolerable ? `<i class="t" style="width:${w(l.tolerable)}"></i>` : '')
      + (l.fail ? `<i class="f" style="width:${w(l.fail)}"></i>` : '') + (l.pending ? `<i class="w" style="width:${w(l.pending)}"></i>` : '') + '</div>';
  };
  const BADGE = { red: 'FAILED', amber: 'WATCH', green: 'ALL PASSED', grey: 'NO TESTS' };
  const reason = (l) => l.color === 'red' ? plural(l.fail, 'test failed', 'tests failed') : l.color === 'amber' ? (l.tolerable ? plural(l.tolerable, 'tolerable result') : plural(l.pending, 'sample') + ' waiting') : l.color === 'green' ? 'Everything passed' : 'Nothing collected or tested';

  const lineCard = (l) => {
    const nums = [];
    if (l.pass) nums.push(`<span class="p">✔ ${l.pass} passed</span>`);
    if (l.tolerable) nums.push(`<span class="t">▲ ${l.tolerable} tolerable</span>`);
    if (l.fail) nums.push(`<span class="f">✖ ${l.fail} failed</span>`);
    if (l.pending) nums.push(`<span class="w">⏳ ${l.pending} waiting</span>`);
    const any = l.tested + l.pending > 0;
    return `<button type="button" class="qi-line ${l.color}" data-line="${esc(l.line)}" ${any ? '' : 'disabled'} aria-label="Line ${esc(l.line)}: ${esc(BADGE[l.color])}. ${esc(reason(l))}">`
      + `<div class="qi-line-top"><b>Line ${esc(l.line)}</b><span class="qi-badge ${l.color}">${BADGE[l.color]}</span></div>${stack(l)}`
      + `<div class="qi-line-nums">${nums.join('') || '<span class="n">No tests</span>'}</div></button>`;
  };

  function drawLines(d) {
    const t = d.totals;
    const all = d.buildings.flatMap((b) => b.lines);
    const red = all.filter((l) => l.color === 'red');
    $('#qi-line-kpis').innerHTML = kpi(t.tested, 'Tested') + kpi(t.pass, 'Passed', 'green') + kpi(t.tolerable, 'Tolerable', 'amber') + kpi(t.fail, 'Failed', 'red') + kpi(t.pending, 'Waiting to be tested', 'amber');

    const head = !t.tested && !t.pending ? '<div class="qi-alert none"><b>No tests in this period.</b> Move to another period, or press Today.</div>'
      : red.length ? `<div class="qi-alert bad"><div><b>${plural(red.length, 'line has', 'lines have')} a failed test</b><span> - open one to see the lab references</span></div><div class="qi-alert-lines">${red.map((l) =>
        `<button type="button" class="qi-chip red" data-line="${esc(l.line)}">${esc(l.line)} <small>${plural(l.fail, 'failed')}${l.fail_refs && l.fail_refs[0] ? ' - ' + esc(l.fail_refs[0]) : ''}</small></button>`).join('')}</div></div>`
      : `<div class="qi-alert ok"><b>No failed tests in this period.</b>${t.tolerable || t.pending ? ' Amber lines have a tolerable result or samples still waiting to be tested.' : ''}</div>`;

    if (!d.buildings.length) { $('#qi-line-body').innerHTML = head + '<div class="qi-empty">No lines yet. Lines appear once shoes are collected from them.</div>'; return; }
    const showAll = $('#qi-show-empty') && $('#qi-show-empty').checked;
    $('#qi-line-body').innerHTML = head + d.buildings.map((b) => {
      const lines = showAll ? b.lines : b.lines.filter((l) => l.color !== 'grey');
      const bad = b.lines.filter((l) => l.color === 'red').length;
      const quiet = b.lines.length - b.lines.filter((l) => l.color !== 'grey').length;
      return `<section class="qi-building"><h3>Building ${esc(b.building)} <small>${plural(b.lines.length - quiet, 'line')} with tests${bad ? ` - <span class="r">${bad} with a failure</span>` : ''}</small></h3>`
        + (lines.length ? `<div class="qi-lines">${lines.map(lineCard).join('')}</div>` : '<div class="qi-empty slim">No tests from this building on this day.</div>') + '</section>';
    }).join('');
    $('#qi-sub').textContent = periodTitle() + ' - ' + t.tested + ' tested, ' + t.fail + ' failed, ' + t.pending + ' waiting';
  }

  async function loadLines(force) {
    const params = period();
    $('#qi-line-hint').textContent = 'Each card adds up every test of ' + periodWords() + '.';
    const hit = peek('line-map', params);
    if (hit) drawLines(hit); else { $('#qi-line-kpis').innerHTML = skelKpis(5); $('#qi-line-body').innerHTML = skelTiles(); }
    try { const r = await get('line-map', params, 'lines', force); if (!r.cached || !hit) drawLines(r.data); }
    catch (e) { if (e.name !== 'AbortError' && !hit) err($('#qi-line-body'), 'Could not load the line map.', () => loadLines(true)); }
  }

  // ---------------------------------------------------------------- failure modes
  const GROUP_LABEL = { line: 'Line', cement: 'Cement supplier', style: 'Style', mode: 'Failure mode' };

  function drawFail(d) {
    const rate = d.tested ? (d.bad * 100 / d.tested).toFixed(1) : '0';
    $('#qi-fail-kpis').innerHTML = kpi(d.tested, 'Tested') + kpi(d.bad, 'Failed or tolerable', 'red') + kpi(rate + '%', 'Not passed') + kpi(d.recorded + ' / ' + d.bad, 'With a failure mode', d.bad && d.recorded < d.bad ? 'amber' : 'green');
    const worst = d.groups.find((g) => g.fail + g.tolerable > 0);
    $('#qi-fail-title').textContent = 'By ' + GROUP_LABEL[d.group].toLowerCase() + (worst && d.group !== 'mode' ? ' - most trouble: ' + worst.key : '');
    const rows = d.groups;
    $('#qi-fail-table').innerHTML = rows.length ? `<table class="qi-table"><thead><tr><th>${esc(GROUP_LABEL[d.group])}</th><th class="n">Tested</th><th>Result mix</th><th class="n">Failed</th><th class="n">Tolerable</th><th class="n">Fail rate</th>${d.group === 'mode' ? '' : '<th>Most common mode</th>'}</tr></thead><tbody>${rows.map((r) =>
      `<tr class="click" data-key="${esc(r.key)}" tabindex="0"><td><b>${esc(r.key)}</b></td><td class="n">${r.tested}</td><td>${stack({ pass: r.pass, tolerable: r.tolerable, fail: r.fail, pending: 0 })}</td><td class="n">${r.fail}</td><td class="n">${r.tolerable}</td><td class="n"><span class="qi-pill ${r.fail_rate >= 5 ? 'bad' : r.fail_rate > 0 ? 'warn' : 'ok'}">${r.fail_rate}%</span></td>${d.group === 'mode' ? '' : `<td>${esc(r.top_mode || '-')}</td>`}</tr>`).join('')}</tbody></table>`
      : '<div class="qi-empty">No tests in this period.</div>';
    const max = Math.max(1, ...d.modes.map((m) => m.count));
    $('#qi-modes').innerHTML = d.modes.length ? d.modes.map((m) => `<div class="qi-mode"><div class="top"><span>${esc(m.mode)}</span><b>${m.count} <small>(${m.share}%)</small></b></div><div class="qi-bar"><i style="width:${Math.round(m.count * 100 / max)}%"></i></div></div>`).join('')
      + (d.bad && d.recorded < d.bad ? `<p class="qi-note">"Not recorded" means the report was saved as Fail / Tolerable with no failure mode. The bonding form now asks for it.</p>` : '')
      : '<div class="qi-empty">No failures or tolerable results in this period.</div>';
    $('#qi-sub').textContent = periodTitle() + ' - ' + d.tested + ' tested, ' + d.bad + ' not passed';
  }

  function failParams() { return Object.assign({}, period(), { group: state.group }); }

  async function loadFail(force) {
    const params = failParams();
    $('#qi-fail-hint').textContent = 'Fail and tolerable results of ' + periodWords() + '.';
    const hit = peek('failures', params);
    if (hit) drawFail(hit); else { $('#qi-fail-kpis').innerHTML = skelKpis(4); $('#qi-fail-table').innerHTML = skelRows(7); $('#qi-modes').innerHTML = skelRows(5); }
    try { const r = await get('failures', params, 'fail', force); if (!r.cached || !hit) drawFail(r.data); }
    catch (e) { if (e.name !== 'AbortError' && !hit) err($('#qi-fail-table'), 'Could not load the failures.', () => loadFail(true)); }
  }

  // ---------------------------------------------------------------- suppliers
  function drawSup(d) {
    $('#qi-sup-table').innerHTML = d.rows.length ? `<table class="qi-table"><thead><tr><th>Supplier</th><th>Material</th><th class="n">Tested</th><th>Result mix</th><th class="n">Pass</th><th class="n">Tolerable</th><th class="n">Fail</th><th class="n">Pass rate</th><th></th></tr></thead><tbody>${d.rows.map((r) =>
      `<tr class="click" data-sup="${esc(r.supplier)}" data-mat="${esc(r.material)}" tabindex="0"><td><b>${esc(r.supplier)}</b></td><td>${esc(r.material)}</td><td class="n">${r.tested}</td><td>${stack({ pass: r.pass, tolerable: r.tolerable, fail: r.fail, pending: 0 })}</td><td class="n">${r.pass}</td><td class="n">${r.tolerable}</td><td class="n">${r.fail}</td>`
      + `<td class="n"><span class="qi-pill ${r.pass_rate >= 95 ? 'ok' : r.pass_rate >= 85 ? 'warn' : 'bad'}">${r.pass_rate}%</span></td><td>${r.low_sample ? '<span class="qi-pill low" title="Too few tests to judge">low sample</span>' : ''}</td></tr>`).join('')}</tbody></table>`
      : '<div class="qi-empty">No supplier results in this period.' + (state.supMode === 'period' ? ' Suppliers are written on material and bonding reports; try <b>Last 90 days</b> for a bigger sample.' : '') + '</div>';
    $('#qi-sup-note').textContent = (d.without_supplier ? d.without_supplier + ' test(s) have no supplier written on the report and are not counted. ' : '') + 'Click a row to see its failed and tolerable tests. Same supplier written two ways? Merge them in Settings > Supplier spelling merges.';
    $('#qi-sub').textContent = supWords() + ' - ' + d.rows.length + ' supplier / material pairs';
  }

  function supParams() {
    if (state.supMode === 'period') return Object.assign({}, period(), { min: state.min });
    return { from: shiftDay(today, -(+state.supMode - 1)), to: today, min: state.min };
  }
  const supWords = () => state.supMode === 'period' ? periodTitle() : 'last ' + state.supMode + ' days';

  async function loadSup(force) {
    const params = supParams();
    const hit = peek('suppliers', params);
    if (hit) drawSup(hit); else $('#qi-sup-table').innerHTML = skelRows(8);
    try { const r = await get('suppliers', params, 'sup', force); if (!r.cached || !hit) drawSup(r.data); }
    catch (e) { if (e.name !== 'AbortError' && !hit) err($('#qi-sup-table'), 'Could not load the suppliers.', () => loadSup(true)); }
  }

  // ---------------------------------------------------------------- side panel
  const dr = { el: $('#qi-drawer'), back: $('#qi-backdrop'), body: $('#qi-drawer-body') };
  function openDrawer(badge, title, sub) {
    $('#qi-drawer-badge').textContent = badge; $('#qi-drawer-title').textContent = title; $('#qi-drawer-sub').textContent = sub || '';
    dr.body.innerHTML = Array.from({ length: 4 }, () => '<div class="cal-dcard cal-skel-dcard"><i class="qi-skel" style="height:16px;width:50%"></i><div class="cal-skel-grid"><i class="qi-skel" style="height:14px"></i><i class="qi-skel" style="height:14px"></i><i class="qi-skel" style="height:14px"></i><i class="qi-skel" style="height:14px"></i></div></div>').join('');
    dr.back.classList.add('active'); dr.el.classList.add('active'); dr.el.setAttribute('aria-hidden', 'false');
  }
  const closeDrawer = () => { dr.back.classList.remove('active'); dr.el.classList.remove('active'); dr.el.setAttribute('aria-hidden', 'true'); };
  const fmtDate = (d) => { if (!d) return '–'; const t = new Date(d + 'T00:00:00'); return isNaN(t) ? d : t.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); };

  function drawItems(items, emptyMsg) {
    if (!items.length) { dr.body.innerHTML = `<div class="cal-empty-day"><p>${esc(emptyMsg)}</p></div>`; return; }
    dr.body.innerHTML = items.map((i) => {
      const st = i.status === 'ongoing' ? '<span class="cal-status-badge badge-ongoing">Ongoing</span>' : i.status === 'pending' ? '<span class="cal-status-badge badge-pending">Pending</span>' : '<span class="cal-status-badge badge-completed">Completed</span>';
      const r = (i.result || '').toUpperCase();
      const rp = r === 'PASS' ? '<span class="cal-result-pill res-pass">PASS</span>' : r === 'FAIL' ? '<span class="cal-result-pill res-fail">FAIL</span>' : r === 'TOLERABLE' ? '<span class="cal-result-pill res-tol">TOLERABLE</span>' : '';
      return `<div class="cal-dcard"><div class="cal-dcard-head"><div><span class="cal-dcard-domain ${esc(i.domain)}">${esc(i.domain_label)}</span><div class="cal-dcard-ref">${esc(i.lab_ref_no || 'No Lab Ref')}</div></div><div style="display:flex;align-items:center;gap:6px">${st}${rp}</div></div>`
        + `<div class="cal-dcard-grid"><div class="cal-dcard-field"><span class="cal-df-label">Test</span><span class="cal-df-val">${esc(i.test_type || '–')}</span></div><div class="cal-dcard-field"><span class="cal-df-label">Style</span><span class="cal-df-val">${esc(i.style || '–')}</span></div>`
        + `<div class="cal-dcard-field"><span class="cal-df-label">Test Date</span><span class="cal-df-val">${esc(fmtDate(i.test_date))}</span></div><div class="cal-dcard-field"><span class="cal-df-label">Technician</span><span class="cal-df-val">${esc(i.technician || '–')}</span></div>`
        + (i.remarks ? `<div class="cal-dcard-field" style="grid-column:span 2"><span class="cal-df-label">Remarks</span><span class="cal-df-val" style="color:#64748b;font-weight:400">${esc(i.remarks)}</span></div>` : '') + '</div>'
        + `<div class="cal-dcard-foot"><span style="font-size:11px;color:#94a3b8">${esc(i.category || '')}</span>${i.view_url ? `<a href="${esc(i.view_url)}" target="_blank" class="cal-dcard-link"><span>View Full Sheet</span><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg></a>` : ''}</div></div>`;
    }).join('');
  }

  async function showDetails(badge, title, sub, path, params) {
    openDrawer(badge, title, sub);
    try { const r = await get(path, params, 'drawer'); drawItems(r.data.items || [], 'Nothing to show.'); }
    catch (e) { if (e.name !== 'AbortError') dr.body.innerHTML = '<div class="cal-empty-day" style="color:#ef4444"><p>Could not load the tests.</p></div>'; }
  }

  // ---------------------------------------------------------------- wiring
  function show(tab) {
    state.tab = tab;
    $$('.qi-tab').forEach((b) => { const on = b.dataset.tab === tab; b.classList.toggle('active', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
    ['lines', 'fail', 'sup'].forEach((t) => $('#qi-panel-' + t).classList.toggle('hidden', t !== tab));
    try { history.replaceState(null, '', '#' + tab); } catch (e) { /* ignore */ }
    if (tab === 'lines') loadLines(); else if (tab === 'fail') loadFail(); else loadSup();
  }

  function refreshAll() {
    $('#qi-title-label').textContent = periodTitle();
    show(state.tab);
  }
  $('#qi-prev').addEventListener('click', () => { if (state.view === 'month') { state.cur.setDate(1); state.cur.setMonth(state.cur.getMonth() - 1); } else state.cur.setDate(state.cur.getDate() - (state.view === 'week' ? 7 : 1)); refreshAll(); });
  $('#qi-next').addEventListener('click', () => { if (state.view === 'month') { state.cur.setDate(1); state.cur.setMonth(state.cur.getMonth() + 1); } else state.cur.setDate(state.cur.getDate() + (state.view === 'week' ? 7 : 1)); refreshAll(); });
  $('#qi-today').addEventListener('click', () => { state.cur = new Date(today + 'T12:00:00'); refreshAll(); });
  $$('.qi-view').forEach((b) => b.addEventListener('click', () => {
    state.view = b.dataset.view;
    $$('.qi-view').forEach((x) => { const on = x === b; x.classList.toggle('active', on); x.setAttribute('aria-checked', on ? 'true' : 'false'); });
    refreshAll();
  }));
  $$('.qi-tab').forEach((b) => b.addEventListener('click', () => show(b.dataset.tab)));
  $('#qi-refresh').addEventListener('click', () => { cache.clear(); show(state.tab); });

  $('#qi-show-empty').addEventListener('change', () => { const h = peek('line-map', period()); if (h) drawLines(h); });
  $('#qi-line-body').addEventListener('click', (e) => {
    const t = e.target.closest('[data-line]'); if (!t || t.disabled) return;
    showDetails(periodTitle(), 'Line ' + t.dataset.line, 'Tests of ' + periodWords(), 'line-details', Object.assign({}, period(), { line: t.dataset.line }));
  });

  $('#qi-group').addEventListener('change', (e) => { state.group = e.target.value; loadFail(); });
  const rowOpen = (tr) => { const p = Object.assign({}, failParams(), { key: tr.dataset.key }); showDetails('Not passed', tr.dataset.key, GROUP_LABEL[state.group] + ', ' + periodTitle(), 'failure-details', p); };
  $('#qi-fail-table').addEventListener('click', (e) => { const tr = e.target.closest('tr[data-key]'); if (tr) rowOpen(tr); });
  $('#qi-fail-table').addEventListener('keydown', (e) => { if (e.key === 'Enter') { const tr = e.target.closest('tr[data-key]'); if (tr) rowOpen(tr); } });

  $$('#qi-sup-period button').forEach((b) => b.addEventListener('click', () => { $$('#qi-sup-period button').forEach((x) => x.classList.toggle('active', x === b)); state.supMode = b.dataset.mode; loadSup(); }));
  $('#qi-min').addEventListener('change', (e) => { state.min = Math.max(1, Math.min(50, +e.target.value || 5)); loadSup(); });
  const supOpen = (tr) => showDetails('Not passed', tr.dataset.sup, tr.dataset.mat + ', ' + supWords(), 'supplier-details', Object.assign({}, supParams(), { supplier: tr.dataset.sup, material: tr.dataset.mat }));
  $('#qi-sup-table').addEventListener('click', (e) => { const tr = e.target.closest('tr[data-sup]'); if (tr) supOpen(tr); });
  $('#qi-sup-table').addEventListener('keydown', (e) => { if (e.key === 'Enter') { const tr = e.target.closest('tr[data-sup]'); if (tr) supOpen(tr); } });

  $('#qi-drawer-close').addEventListener('click', closeDrawer);
  dr.back.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && dr.el.classList.contains('active')) closeDrawer(); });

  const start = ['lines', 'fail', 'sup'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'lines';
  state.tab = start;
  $$('.qi-tab').forEach((b) => { const on = b.dataset.tab === start; b.classList.toggle('active', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
  refreshAll();
})();
