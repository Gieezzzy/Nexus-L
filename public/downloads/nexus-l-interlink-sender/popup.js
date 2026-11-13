// Saves the sign-in cookies of this browser (InterLink + Microsoft sign-in) as a file the NexusInterlinkService can import
const msg = document.getElementById('msg');
const say = (t, ok) => { msg.textContent = t; msg.className = ok ? 'ok' : 'err'; };
const SAME_SITE = { no_restriction: 'None', lax: 'Lax', strict: 'Strict', unspecified: 'Lax' };

document.getElementById('go').addEventListener('click', async () => {
  const btn = document.getElementById('go');
  btn.disabled = true;
  try {
    const urls = ['https://interlink2.intertek.com/', 'https://intertek.com/', 'https://login.microsoftonline.com/', 'https://login.live.com/', 'https://microsoftonline.com/'];
    const seen = new Map();
    const domains = ['intertek.com', 'microsoftonline.com', 'live.com', 'microsoft.com'];
    for (const d of domains) for (const c of await chrome.cookies.getAll({ domain: d })) seen.set(`${c.domain}|${c.path}|${c.name}`, c);
    for (const u of urls) for (const c of await chrome.cookies.getAll({ url: u })) seen.set(`${c.domain}|${c.path}|${c.name}`, c);
    const cookies = [...seen.values()].map((c) => ({
      name: c.name, value: c.value, domain: c.domain, path: c.path,
      expires: c.session || !c.expirationDate ? -1 : c.expirationDate,
      httpOnly: !!c.httpOnly, secure: !!c.secure, sameSite: SAME_SITE[c.sameSite] || 'Lax',
    }));
    if (!cookies.some((c) => /intertek/i.test(c.domain))) { say('No InterLink cookies found. Sign in to InterLink in this browser first.', false); btn.disabled = false; return; }
    const blob = new Blob([JSON.stringify({ cookies, origins: [], exportedAt: new Date().toISOString() })], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'interlink-session.json';
    document.body.appendChild(a);
    a.click();
    a.remove();
    say(`Saved interlink-session.json (${cookies.length} cookies). Copy it to the PC running the service.`, true);
  } catch (e) { say('Could not export: ' + e.message, false); }
  btn.disabled = false;
});
