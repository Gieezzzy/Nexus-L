<?php /** ERP Extract: control page for the ERP Extract Agent (admin only, under settings/). */ ?>
<link rel="stylesheet" href="<?= base_url('assets/css/views/settings-erp_extract-1.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/views/settings-erp_extract-1.css') ?>">
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
