// On NEXUS-L pages: tells the page the sender is installed (and which version) and relays jobs/pulls and progress both ways.
document.documentElement.dataset.nxlSender = '1';
document.documentElement.dataset.nxlSenderVersion = chrome.runtime.getManifest().version;

const post = (m) => window.postMessage(m, location.origin);
// After the extension is updated or reloaded, a page that was already open keeps this old copy of the script with a dead
// connection to the extension. Say so at once instead of letting the page wait for an answer that cannot come.
const alive = () => { try { return !!(chrome.runtime && chrome.runtime.id); } catch (e) { return false; } };
const STALE = 'The extension was updated or reloaded after this page opened. Reload this page (Ctrl+F5) and try again.';

window.addEventListener('message', (e) => {
  if (e.source !== window || !e.data) return;
  if (e.data.type === 'NXL_SEND' && e.data.job) {
    if (!alive()) { post({ type: 'NXL_SEND_STATUS', ok: false, done: true, message: STALE }); return; }
    try { chrome.runtime.sendMessage({ type: 'send', job: e.data.job }); } catch (err) { post({ type: 'NXL_SEND_STATUS', ok: false, done: true, message: STALE }); }
  }
  if (e.data.type === 'NXL_PULL_GRID') {
    if (!alive()) { post({ type: 'NXL_GRID_STATUS', reqId: e.data.reqId, ok: false, message: STALE }); return; }
    post({ type: 'NXL_GRID_ACK', reqId: e.data.reqId });          // the page knows within a moment that something is listening
    try {
      chrome.runtime.sendMessage({ type: 'pull-grid', reqId: e.data.reqId, firstPage: e.data.firstPage, count: e.data.count, silent: !!e.data.silent });
    } catch (err) { post({ type: 'NXL_GRID_STATUS', reqId: e.data.reqId, ok: false, message: STALE }); }
  }
});
// the extension asks this page (same site, same sign-in) whether a report is approved
chrome.runtime.onMessage.addListener((m, _sender, sendResponse) => {
  if (!m || m.type !== 'verify') return false;
  let u;
  try { u = new URL(m.url, location.href); } catch (e) { sendResponse({ error: 'bad address' }); return false; }
  if (u.origin !== location.origin) { sendResponse({ error: 'not this site' }); return false; }
  fetch(u.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then((r) => r.json().then((j) => sendResponse({ ok: r.ok, j })))
    .catch((e) => sendResponse({ error: e.message }));
  return true;
});
chrome.runtime.onMessage.addListener((m) => {
  if (!m) return;
  if (m.type === 'status') post({ type: 'NXL_SEND_STATUS', ok: m.ok, done: m.done, message: m.message });
  if (m.type === 'grid-html') post({ type: 'NXL_GRID_HTML', reqId: m.reqId, pages: m.pages });
  if (m.type === 'grid-status') post({ type: 'NXL_GRID_STATUS', reqId: m.reqId, ok: m.ok, message: m.message });
  if (m.type === 'grid-progress') post({ type: 'NXL_GRID_PROGRESS', reqId: m.reqId, message: m.message });
});
