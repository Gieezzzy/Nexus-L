<?php
/** The page a shoe-photo QR code opens on the phone. Data: $ok, and when ok: $r (link row), $upload (address), $left (seconds) */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Shoe photo - NEXUS-L</title>
<style>
:root { --ink: #0f172a; --mute: #5b6a7e; --line: #dbe3ee; --brand: #2563eb; }
* { box-sizing: border-box; }
body { margin: 0; min-height: 100vh; background: #f1f5f9; color: var(--ink); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; display: flex; align-items: flex-start; justify-content: center; padding: 16px; }
.card { width: 100%; max-width: 480px; background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 20px 18px; box-shadow: 0 8px 28px rgba(15,23,42,.08); }
h1 { margin: 0 0 4px; font-size: 20px; }
.sub { margin: 0 0 14px; color: var(--mute); font-size: 14px; }
.facts { display: grid; gap: 4px; margin: 0 0 14px; padding: 10px 12px; background: #f8fafc; border-radius: 10px; font-size: 14px; }
.facts b { font-variant-numeric: tabular-nums; }
.pick { display: block; width: 100%; min-height: 56px; border: 0; border-radius: 12px; background: var(--brand); color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
.pick:active { background: #1d4ed8; }
.pick.alt { background: #fff; color: var(--ink); border: 1px solid #cbd5e1; margin-top: 10px; }
.pick:disabled { opacity: .55; }
#prev { display: none; width: 100%; max-height: 52vh; object-fit: contain; border-radius: 12px; background: #0f172a; margin-bottom: 12px; }
.msg { margin: 12px 0 0; font-size: 14px; min-height: 1.4em; }
.msg.err { color: #b91c1c; } .msg.ok { color: #15803d; font-weight: 600; }
.time { font-size: 13px; color: var(--mute); margin-top: 10px; }
.gone { text-align: center; padding: 28px 8px; }
.gone h2 { margin: 0 0 4px; font-size: 18px; }
.gone .x.amber { background: #fef3c7; color: #b45309; }
.gone .x { width: 52px; height: 52px; border-radius: 50%; background: #fee2e2; color: #b91c1c; display: inline-grid; place-items: center; font-size: 28px; font-weight: 700; margin-bottom: 8px; }
</style>
</head>
<body>
<div class="card">
<?php if (! $ok): ?>
    <div class="gone">
        <div class="x" aria-hidden="true">!</div>
        <h1>This link is not valid</h1>
        <p class="sub">It has expired (a link works for 15 minutes) or it does not match. Ask for a new QR code on the computer.</p>
    </div>
<?php else: ?>
    <h1><?= $r['mtl'] === '' ? 'Take the report photo' : 'Take the shoe photo' ?></h1>
    <p class="sub"><?= $r['mtl'] === '' ? 'The picture goes straight to the report message on the computer.' : 'The picture goes straight to the Lab Tracker on the computer.' ?></p>
    <div class="facts">
        <span>Lab reference: <b><?= esc($r['lab_ref']) ?></b></span>
        <span>Test type: <b><?= esc($r['test_type']) ?></b></span>
        <?php if ($r['prod_code'] !== ''): ?><span>Production code: <b><?= esc($r['prod_code']) ?></b></span><?php endif ?>
    </div>
    <div id="wait" class="gone" hidden>
        <div class="x amber" id="wait-x" aria-hidden="true">!</div>
        <h2 id="wait-t">Another user is capturing this shoe</h2>
        <p class="sub" id="wait-s">Wait for them to finish. This page checks again by itself.</p>
    </div>
    <div id="ui" hidden>
    <img id="prev" alt="Photo preview">
    <input type="file" id="cam" accept="image/*" capture="environment" hidden>
    <input type="file" id="gal" accept="image/*" hidden>
    <button type="button" class="pick" id="take">Open camera</button>
    <button type="button" class="pick alt" id="choose">Choose from gallery</button>
    <p class="msg" id="msg" role="status"></p>
    <p class="time" id="time"></p>
    </div>
<script>
(() => {
  const $ = (id) => document.getElementById(id);
  const url = <?= json_encode($upload) ?>;
  const api = <?= json_encode(site_url('photo/' . $r['token'])) ?>;
  const q = <?= json_encode('?l=' . rawurlencode($r['lab_ref']) . '&t=' . rawurlencode($r['test_type'])) ?>;
  let left = <?= (int) $left ?>;
  // this phone's id: the same on every visit, so a reload does not lock it out of its own capture
  let dev = ''; try { dev = localStorage.getItem('nxl_dev') || ''; } catch (e) { /* private mode */ }
  if (!/^[A-Za-z0-9]{8,40}$/.test(dev)) { dev = Array.from(crypto.getRandomValues(new Uint8Array(12)), (b) => (b % 36).toString(36)).join(''); try { localStorage.setItem('nxl_dev', dev); } catch (e) { /* ignore */ } }
  const D = '&d=' + dev;
  const msg = (t, c) => { $('msg').textContent = t; $('msg').className = 'msg' + (c ? ' ' + c : ''); };
  let holding = false; let ping = 0; let poll = 0; let over = false;
  const expire = () => { over = true; clearInterval(ping); clearTimeout(poll); $('ui').hidden = false; $('wait').hidden = true; $('time').textContent = ''; msg('This link has expired. Ask for a new QR code on the computer.', 'err'); $('take').disabled = $('choose').disabled = true; };
  const tick = () => { if (left <= 0) { expire(); return; } $('time').textContent = 'Link valid for ' + Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0') + ' more'; left--; setTimeout(tick, 1000); };
  tick();

  // ask to capture; busy -> wait screen and try again every few seconds; free -> show the camera buttons and keep pinging
  const claim = async () => {
    if (over) return;
    let r; let j = {};
    try { r = await fetch(api + '/claim' + q + D, { method: 'POST' }); j = await r.json(); } catch (e) { poll = setTimeout(claim, 3000); return; }
    if (j.state === 'you') {
      holding = true; $('wait').hidden = true; $('ui').hidden = false;
      clearInterval(ping); ping = setInterval(() => { fetch(api + '/claim' + q + D, { method: 'POST' }).then((x) => x.json()).then((y) => { if (y.state === 'busy' || y.state === 'done') location.reload(); }).catch(() => {}); }, 5000);
    } else if (j.state === 'busy') {
      holding = false; $('ui').hidden = true; $('wait').hidden = false;
      $('wait-t').textContent = 'Another user is capturing this shoe'; $('wait-s').textContent = 'Wait for them to finish. This page checks again by itself.';
      poll = setTimeout(claim, 3000);
    } else if (j.state === 'done') {
      $('ui').hidden = true; $('wait').hidden = false; $('wait-x').textContent = '\u2713'; $('wait-t').textContent = 'The photo was already taken'; $('wait-s').textContent = 'Another user has sent the photo for this. Nothing more to do.';
    } else { expire(); }
  };
  // leaving or closing the page frees the shoe at once for the next phone
  const release = () => { if (holding) { try { navigator.sendBeacon(api + '/release' + q + D); } catch (e) { /* the ping timeout frees it */ } } };
  addEventListener('pagehide', release);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') release(); else if (!over) claim(); });
  claim();

  const send = async (file) => {
    if (!file) return;
    $('prev').src = URL.createObjectURL(file); $('prev').style.display = 'block';
    msg('Sending...'); $('take').disabled = $('choose').disabled = true;
    try {
      const fd = new FormData(); fd.append('photo', file, file.name || 'photo.jpg');
      const r = await fetch(url + D, { method: 'POST', body: fd });
      const j = await r.json();
      if (!r.ok || !j.ok) throw new Error(j.error || 'Not sent');
      msg('Sent. You can look at the computer now. To replace it, take another photo.', 'ok');
      $('take').textContent = 'Take another photo';
    } catch (e) { msg(e.message, 'err'); }
    $('take').disabled = $('choose').disabled = left <= 0;
  };
  $('take').onclick = () => $('cam').click();
  $('choose').onclick = () => $('gal').click();
  $('cam').onchange = (e) => send(e.target.files[0]);
  $('gal').onchange = (e) => send(e.target.files[0]);
})();
</script>
<?php endif ?>
</div>
</body>
</html>
