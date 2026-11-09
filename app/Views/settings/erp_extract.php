<?php /** ERP Extract: control page for the ERP Extract Agent (admin only, under settings/). */ ?>
<style>
.ex{max-width:980px;margin:0 auto;color:#0f172a}
.ex h1{font-size:26px;margin:4px 0 4px}.ex p.s{color:#475569;margin:0 0 18px}
.ex .bar{display:flex;flex-wrap:wrap;gap:10px 18px;align-items:center;padding:12px 16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;margin-bottom:16px}
.ex .dot{width:10px;height:10px;border-radius:50%;background:#94a3b8;display:inline-block;margin-right:6px}
.ex .dot.on{background:#16a34a}.ex .dot.warn{background:#d97706}.ex .dot.off{background:#dc2626}
.ex form{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px 16px;padding:16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
.ex label{display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:600;color:#334155}
.ex input,.ex select{height:40px;padding:0 10px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;font-weight:400}
.ex .act{grid-column:1/-1;display:flex;gap:10px;flex-wrap:wrap}
.ex button{min-height:42px;padding:0 18px;border-radius:10px;border:1px solid #0f766e;background:#0f766e;color:#fff;font-weight:600;cursor:pointer}
.ex button.alt{background:#fff;color:#b91c1c;border-color:#fca5a5}.ex button:disabled{opacity:.45;cursor:not-allowed}
.ex .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin:16px 0}
.ex .stats div{border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;background:#fff}.ex .stats b{display:block;font-size:24px}
.ex .stats span{font-size:12px;color:#64748b}
.ex pre{height:300px;overflow:auto;background:#0f172a;color:#e2e8f0;border-radius:12px;padding:12px;font-size:12.5px;line-height:1.5;white-space:pre-wrap}
.ex .msg{margin:10px 0;color:#b91c1c;min-height:20px}
</style>
<div class="ex" id="ex" data-base="<?= esc(site_url('settings/erp-extract')) ?>">
    <p class="s">Starts, stops and watches the extract agent on the ERP PC. The agent writes the production orders, detail rows, section materials and photos into the central database.
        Address, port and token: <a href="<?= esc(site_url('settings')) ?>">Settings</a> &gt; ERP agent.</p>
    <div class="bar"><span><i class="dot" id="exDot"></i><b id="exState">Checking...</b></span><span id="exWho"></span><span id="exErp"></span></div>
    <form id="exForm" autocomplete="off">
        <label>Company
            <select name="company"><option value="CURRENT">Current in the ERP</option><option>B-DA TIAN</option><option>P-DTP SUBIC</option><option>T-D2P SUBIC</option><option value="ALL">All three</option></select></label>
        <label>Date filter on<select name="date_type"><option value="created">Created date</option><option value="crd">CRD</option></select></label>
        <label>From<input type="date" name="date_from"></label>
        <label>To<input type="date" name="date_to"></label>
        <label>Start at row<input type="number" name="start_row" min="1" value="1"></label>
        <label>Stop at row (empty = all)<input type="number" name="end_row" min="1"></label>
        <label>Max rows (empty = all)<input type="number" name="max_rows" min="1"></label>
        <label>Start at order code<input name="start_prod_code" maxlength="40"></label>
        <div class="act"><button type="submit" id="exStart">Start extraction</button><button type="button" class="alt" id="exStop">Stop</button></div>
    </form>
    <div class="msg" id="exMsg" role="status"></div>
    <div class="stats" id="exStats"></div>
    <pre id="exLog" aria-label="Agent log"></pre>
</div>
<script>
(() => {
  const root = document.getElementById('ex'), base = root.dataset.base, $ = (i) => document.getElementById(i);
  const csrf = <?= json_encode([csrf_token() => csrf_hash()]) ?>;
  let since = 0;
  const post = (p, b) => fetch(base + '/' + p, {method: 'POST', headers: Object.assign({'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, {'X-CSRF-TOKEN': Object.values(csrf)[0]}), body: JSON.stringify(b || {})}).then(r => r.json());
  const get = (p) => fetch(base + '/' + p, {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(r => r.json());
  const esc = (s) => String(s).replace(/[&<>]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;'}[c]));
  const L = {companies: 'Companies', orders: 'Orders', details: 'Detail rows', section_materials: 'Section materials', images: 'Photos', total_rows: 'Rows in list'};
  async function tick() {
    try {
      const s = await get('status');
      if (!s.ok) { $('exDot').className = 'dot off'; $('exState').textContent = 'Not reachable'; $('exMsg').textContent = s.error || ''; $('exWho').textContent = ''; $('exErp').textContent = ''; $('exStart').disabled = true; return; }
      $('exDot').className = 'dot ' + (s.running ? 'warn' : 'on');
      $('exState').textContent = s.running ? 'Extracting' : 'Idle';
      $('exWho').textContent = 'PC: ' + (s.name || '');
      $('exErp').textContent = s.erp_open ? 'ERP client open' : 'ERP client NOT open';
      $('exStart').disabled = !!s.running || !s.erp_open; $('exStop').disabled = !s.running;
      const st = s.stats || {};
      $('exStats').innerHTML = Object.keys(L).filter(k => k in st).map(k => '<div><b>' + st[k] + '</b><span>' + L[k] + '</span></div>').join('');
      if (s.error) $('exMsg').textContent = s.error;
      const g = await get('log?since=' + since);
      if (g.ok && g.lines && g.lines.length) {
        const box = $('exLog'), near = box.scrollTop + box.clientHeight >= box.scrollHeight - 30;
        box.insertAdjacentHTML('beforeend', g.lines.map(l => esc(l[1])).join('\n') + '\n');
        since = g.last; if (near) box.scrollTop = box.scrollHeight;
      }
    } catch (e) { $('exState').textContent = 'Not reachable'; }
  }
  $('exForm').addEventListener('submit', async (e) => {
    e.preventDefault(); const f = new FormData(e.target), c = f.get('company');
    const body = {targets: c === 'ALL' ? ['B-DA TIAN', 'P-DTP SUBIC', 'T-D2P SUBIC'] : [c]};
    ['date_type', 'date_from', 'date_to', 'start_row', 'end_row', 'max_rows', 'start_prod_code'].forEach(k => body[k] = f.get(k));
    if (!confirm('Start extracting into the central database now?')) return;
    const r = await post('start', body); $('exMsg').textContent = r.ok ? '' : (r.error || 'Could not start.'); tick();
  });
  $('exStop').addEventListener('click', async () => { const r = await post('stop'); $('exMsg').textContent = r.ok ? 'Stop requested.' : (r.error || ''); tick(); });
  tick(); setInterval(tick, 3000);
})();
</script>
