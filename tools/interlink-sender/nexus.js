// On NEXUS-L pages: tells the page the sender is installed and relays "send" jobs and their progress.
document.documentElement.dataset.nxlSender = '1';
window.addEventListener('message', (e) => {
  if (e.source !== window || !e.data || e.data.type !== 'NXL_SEND' || !e.data.job) return;
  chrome.runtime.sendMessage({ type: 'send', job: e.data.job });
});
chrome.runtime.onMessage.addListener((m) => {
  if (m && m.type === 'status') window.postMessage({ type: 'NXL_SEND_STATUS', ok: m.ok, done: m.done, message: m.message }, location.origin);
});
