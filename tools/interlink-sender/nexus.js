// On NEXUS-L pages: tells the page the sender is installed and relays jobs/pulls and their progress both ways.
document.documentElement.dataset.nxlSender = '1';
window.addEventListener('message', (e) => {
  if (e.source !== window || !e.data) return;
  if (e.data.type === 'NXL_SEND' && e.data.job) chrome.runtime.sendMessage({ type: 'send', job: e.data.job });
  if (e.data.type === 'NXL_PULL_GRID') chrome.runtime.sendMessage({ type: 'pull-grid', silent: !!e.data.silent });
});
chrome.runtime.onMessage.addListener((m) => {
  if (!m) return;
  if (m.type === 'status') window.postMessage({ type: 'NXL_SEND_STATUS', ok: m.ok, done: m.done, message: m.message }, location.origin);
  if (m.type === 'grid-html') window.postMessage({ type: 'NXL_GRID_HTML', html: m.html }, location.origin);
  if (m.type === 'grid-status') window.postMessage({ type: 'NXL_GRID_STATUS', ok: m.ok, message: m.message }, location.origin);
});
