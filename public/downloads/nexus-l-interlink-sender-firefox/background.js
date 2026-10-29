// NEXUS-L InterLink Sender. A job = { application_no, target_url, controls: {inputName: value}, pdf: {name, b64} }.
//
// Rewritten 2026-10-28 after reading the real, live site (not from memory/guesswork):
//   - The old version opened a hardcoded deep link to ONE SPECIFIC application's Upload Form (DEFAULT_UPLOAD_URL /
//     a stored u_form_url). Verified live: those args=/iudsReqId= tokens belong to one browser session and die with
//     it, AND u_form_url is essentially never actually stored for real applications - so almost every send was
//     opening some OTHER (and usually already-dead) application's form, not the one being sent.
//   - The real Upload Form (UploadForm [10072]) has NO "Submit" button at all on its Upload Result tab. Confirmed
//     live: pressing Save alone changed the grid's Job Status to "Submitted".
// Fixed approach, nothing hardcoded or guessed: open InterLink's stable site root, search the LIVE grid for the exact
// Application No. (same filter box a person types into, located by its live alignment under the "Application No."
// header so it keeps working if columns are ever reordered), click that row's real Upload icon - InterLink then opens
// a genuine, fresh, correctly-scoped popup every time - and commit with Save (or Submit, if a page variant has one).

const PORTAL_HOME = 'https://interlink2.intertek.com/InterLink2/aspx/DlkPage/Portal2/Home.aspx';   // straight to the portal home: no extra redirect through the site root

const key = (tabId) => `job_${tabId}`;
const getJob = async (tabId) => (await chrome.storage.session.get(key(tabId)))[key(tabId)];
const setJob = (job) => chrome.storage.session.set({ [key(job.tabId)]: job });
const endJob = (job) => chrome.storage.session.remove(key(job.tabId));
const status = (job, ok, done, message) => chrome.tabs.sendMessage(job.nexusTab, { type: 'status', ok, done, message }).catch(() => {});
const inPage = (tabId, func, args) => chrome.scripting.executeScript({ target: { tabId }, world: 'MAIN', func, args })
  .then((r) => (r && r[0] ? r[0].result : null));

// Nothing is saved in InterLink unless NEXUS-L confirms, right now, that this report is APPROVED by a Manager / Supervisor and
// the file about to be attached is exactly the approved (signed) PDF. Anything else - no answer, not signed in to NEXUS-L, an old
// page without the check, a different file - stops here. Returns '' when allowed, else the reason.
// The check is made by the NEXUS-L page itself (its content script, same site and same sign-in), which works in every browser; a direct
// request from this background script is only the fallback (Firefox needs the site permission granted for it).
const askNexus = (tabId, url) => new Promise((resolve) => {
  try { chrome.tabs.sendMessage(tabId, { type: 'verify', url }, (r) => resolve(chrome.runtime.lastError ? null : r)); } catch (e) { resolve(null); }
});
async function approvalProblem(job, nexusTab) {
  try {
    if (!job.verify || !job.verify.url || !job.pdf || !job.pdf.b64) return 'this page does not send the approval check. Reload NEXUS-L (Ctrl+F5) and try again.';
    let viaPage = nexusTab || job.nexusTab ? await askNexus(nexusTab || job.nexusTab, job.verify.url) : null;
    let ok, j;
    if (viaPage && viaPage.j) { ok = viaPage.ok; j = viaPage.j; }
    else {
      const r = await fetch(job.verify.url, { credentials: 'include', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      j = await r.json(); ok = r.ok;
    }
    if (!ok || !j.ok) return 'NEXUS-L could not confirm the approval. Sign in to NEXUS-L and try again.';
    if (!j.approved) return 'this report is not approved yet. A Manager / Supervisor must approve it (QR) before it can be saved in InterLink.';
    const bin = atob(job.pdf.b64);
    const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    const hash = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', bytes))).map((b) => b.toString(16).padStart(2, '0')).join('');
    if (hash !== j.sha256) return 'the file to attach is not the approved report. Generate it again and request approval.';
    return '';
  } catch (e) {
    return 'NEXUS-L could not confirm the approval (' + e.message + ').';
  }
}

chrome.runtime.onMessage.addListener((m, sender) => {
  if (m.type === 'send' && sender.tab) {
    const gate = { nexusTab: sender.tab.id, application_no: m.job && m.job.application_no };
    approvalProblem(m.job || {}, sender.tab.id).then(async (problem) => {
      if (problem) { status(gate, false, true, `${gate.application_no || 'The report'} was NOT sent: ${problem}`); return; }
      // Firefox runs an extension on a site only after the user allows it (about:addons -> the add-on -> Permissions); without that the
      // InterLink tab would open and nothing would ever happen in it, so say so before opening anything
      try {
        const allowed = await chrome.permissions.contains({ origins: ['https://interlink2.intertek.com/*'] });
        if (!allowed) { status(gate, false, true, `${gate.application_no || 'The report'} was NOT sent: this extension is not allowed to run on interlink2.intertek.com. In Firefox open about:addons, click the NEXUS-L InterLink Sender, choose Permissions and switch on access to interlink2.intertek.com, then try again.`); return; }
      } catch (e) { /* browsers without this check: carry on */ }
      chrome.tabs.create({ url: PORTAL_HOME, active: true }, (t) => {
        const job = Object.assign({}, m.job, { nexusTab: sender.tab.id, tabId: t.id, step: 'search', loads: 0 });
        setJob(job);
        status(job, true, false, `InterLink is opening - searching for ${job.application_no}…`);
        // if the new tab never reports in (sign-in page, blocked site) the user is told instead of waiting for nothing
        const watch = (ms, text) => setTimeout(async () => {
          try {
            const j = await getJob(t.id);
            if (j && j.step === 'search' && !j.loads) status(j, false, false, text);
          } catch (e) { /* the tab is gone */ }
        }, ms);
        watch(45000, 'InterLink is still loading in its tab (it can be slow). Sending continues by itself as soon as the page is ready; if the tab shows a sign-in page, sign in there.');
        watch(120000, 'InterLink has not finished loading after 2 minutes. Check that tab: sign in if it asks, or reload it (F5). If it is stuck on a "Working…" sign-in page, try the same in a private window or clear interlink2.intertek.com site data, then send again.');
      });
    });
  }
  if (m.type === 'ready' && sender.tab) step(sender.tab.id);
  if (m.type === 'pull-grid' && sender.tab) queuePull(sender.tab.id, m);
});

// ---- Live grid reading (NEXUS-L's InterLink Applications view) ---------------------------------------------------------
// NEXUS-L's server never holds an InterLink login, so this is the only way its grid can show current data. A request is
// { reqId, firstPage, count, silent }: read `count` consecutive InterLink grid pages starting at `firstPage`.
//  - The stable site root lands on the portal Home page, which has no grid. The grid is reached through Home's own
//    "Search Requests / Reports" link, which carries a fresh, valid token every session (nothing stored or guessed).
//  - Pages are changed with InterLink's own pager function, exactly as its page links do (verified live: page 227).
//  - A dedicated background tab does the reading, so the user's own InterLink tabs are never navigated or disturbed.
//    It is reused for follow-up page clicks and closed about a minute after the last one.
//  - A silent (auto-refresh) request never opens a tab; it only uses the helper tab if one is already open.
const GRID_ID = 'PH1_SF1_GVBUILDER_BuilderGridView';
const TIMED_OUT = Symbol('timed out');
// a scripted page that never answers (stuck load, sign-in page) must not hang a read: every wait is bounded
const bounded = (promise, ms) => Promise.race([promise.catch(() => null), new Promise((r) => setTimeout(() => r(TIMED_OUT), ms))]);
let pullChain = Promise.resolve();                      // one read at a time: they all share the helper tab
const queuePull = (nexusTabId, req) => { pullChain = pullChain.then(() => pullGrid(nexusTabId, req)).catch(() => {}); };

async function pullGrid(nexusTabId, req) {
  const reply = (msg) => chrome.tabs.sendMessage(nexusTabId, Object.assign({ reqId: req.reqId }, msg)).catch(() => {});
  const progress = (message) => { if (!req.silent) reply({ type: 'grid-progress', message }); };
  let keepOpen = false;                                 // a tab left for the user to sign in must not be closed under them
  try {
    const helper = await helperTab(!!req.silent, progress);
    if (helper.error) { keepOpen = !!helper.signIn; reply({ type: 'grid-status', ok: false, message: helper.error }); return; }
    const pages = [];
    const first = Math.max(1, Number(req.firstPage) || 1);
    const count = Math.max(1, Math.min(Number(req.count) || 1, 8));
    for (let p = first; p < first + count; p++) {
      progress(`Reading InterLink page ${p}…`);
      const r = await bounded(inPage(helper.id, gotoPageAndRead, [p]), 80000);
      if (r === TIMED_OUT) { reply({ type: 'grid-status', ok: false, message: `InterLink did not show page ${p} in time.` }); return; }
      if (!r || !r.ok) { reply({ type: 'grid-status', ok: false, message: (r && r.message) || 'Could not read the InterLink grid.' }); return; }
      pages.push(r.html);
      if (r.page !== p) break;                          // asked past the last page: InterLink's last page was read instead
    }
    reply({ type: 'grid-html', pages });
  } catch (e) {
    reply({ type: 'grid-status', ok: false, message: e.message });
  } finally {
    if (!req.silent && !keepOpen) chrome.alarms.create('close-grid-tab', { delayInMinutes: 1 });   // timer-driven reads do not extend its life
  }
}

/** The background tab that reads the grid: reused if it is still open, otherwise opened (unless silent) and taken to the
 *  grid. Returns { id } or { error }. */
async function helperTab(silent, progress = () => {}) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const { gridTab } = await chrome.storage.session.get('gridTab');
  let tab = gridTab ? await chrome.tabs.get(gridTab).catch(() => null) : null;
  if (!tab) {
    if (silent) return { error: 'The InterLink reader tab is not open.' };
    // never start a second sign-in while one is already open: two at once can leave Microsoft's sign-in state tangled
    const signingIn = (await chrome.tabs.query({ url: ['https://login.microsoftonline.com/*'] })).length > 0;
    if (signingIn) return { error: 'A sign-in to InterLink is already open in another tab. Finish it there, then try again.', signIn: true };
    progress('Opening InterLink in a background tab…');
    tab = await chrome.tabs.create({ url: PORTAL_HOME, active: false });
    await chrome.storage.session.set({ gridTab: tab.id });
  }
  if (!silent) chrome.alarms.clear('close-grid-tab');
  let failures = 0;
  let navigatedAt = 0;
  for (let i = 0; i < 90; i++) {                        // about 45 s to reach the grid
    const st = await bounded(inPage(tab.id, gridProbe, []), 6000);
    if (!st || st === TIMED_OUT) {                      // not scriptable: InterLink's sign-in (Microsoft) page, or still loading
      failures += 1;
      if (failures === 6) progress('Waiting for InterLink to finish signing in…');
      if (failures >= 16 && !silent) {
        await chrome.tabs.update(tab.id, { active: true }).catch(() => {});
        return { error: 'Sign in to InterLink in the tab that just opened, then try again.', signIn: true };
      }
    } else {
      failures = 0;
      if (st.grid) return { id: tab.id };
      if (st.home && Date.now() - navigatedAt > 10000) {
        progress('Going to InterLink\'s search page…');
        await bounded(inPage(tab.id, goToGridFromHome, []), 6000);
        navigatedAt = Date.now();
      }
    }
    await sleep(500);
  }
  return { error: 'InterLink did not open the search grid in time.' };
}

function gridProbe() {
  return {
    grid: !!document.getElementById('PH1_SF1_GVBUILDER_BuilderGridView') && typeof ASPx !== 'undefined' && typeof ASPx.GVPagerOnClick === 'function',
    home: /\/Portal2\/Home\.aspx/i.test(location.pathname),
  };
}

/** On the portal Home page: follows its own "Search Requests / Reports" -> Inhouse & 3rd party Lab Upload link. */
function goToGridFromHome() {
  const a = [...document.querySelectorAll('a')].find((x) => /Inhouse\s*&\s*3rd party Lab Upload/i.test(x.textContent || '') && /SearchFormWithHeader/.test(x.getAttribute('href') || ''));
  const m = a && (a.getAttribute('href') || '').match(/window\.location\.href\s*=\s*'([^']+)'/);
  if (!m) return { ok: false };
  location.href = m[1];
  return { ok: true };
}

/** Takes the grid to page `p` (InterLink's own pager call, as its page links make) and returns the grid's HTML. */
async function gotoPageAndRead(p) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const id = 'PH1_SF1_GVBUILDER_BuilderGridView';
  const grid = () => document.getElementById(id);
  const cur = () => {
    const m = ((document.querySelector('.dxp-lead.dxp-summary') || {}).textContent || '').match(/Page\s+(\d+)\s+of\s+(\d+)\s+\((\d+)\s+items\)/i);
    return m ? { page: +m[1], total: +m[2] } : null;
  };
  const rowSig = () => { const r = document.querySelector('tr.dxgvDataRow'); return r ? r.textContent.slice(0, 300) : ''; };
  if (!grid()) return { ok: false, message: 'the search grid was not found on this InterLink page.' };
  let c = cur();
  if (!c) return { ok: false, message: 'the InterLink grid has no page counter yet.' };
  const target = Math.min(Math.max(1, p), c.total);
  if (c.page !== target) {
    const before = rowSig();
    ASPx.GVPagerOnClick(id, 'PN' + (target - 1));
    for (let i = 0; i < 90; i++) {                      // InterLink can take 10+ s for a far page
      await sleep(500);
      c = cur();
      if (c && c.page === target && rowSig() !== before) break;
    }
    if (!c || c.page !== target) return { ok: false, message: `InterLink did not show page ${target} in time.` };
  }
  await sleep(300);
  return { ok: true, html: grid().outerHTML, page: target };
}

chrome.alarms.onAlarm.addListener(async (a) => {
  if (a.name !== 'close-grid-tab') return;
  const { gridTab } = await chrome.storage.session.get('gridTab');
  if (gridTab) chrome.tabs.remove(gridTab).catch(() => {});
  chrome.storage.session.remove('gridTab');
});

chrome.tabs.onRemoved.addListener(async (tabId) => {
  const { gridTab } = await chrome.storage.session.get('gridTab');
  if (gridTab === tabId) chrome.storage.session.remove('gridTab');
  const job = await getJob(tabId);
  if (job) { status(job, false, true, `The InterLink tab for ${job.application_no} was closed before it was sent.`); endJob(job); }
});

// Catches the popup InterLink opens when the grid's Upload icon is clicked: Chrome reports its opener tab id, which is
// the grid tab we were just searching in (job.tabId at that point, before this reassigns it to the popup).
// Tabs that were just created: Firefox opens the popup and finishes loading it BEFORE the grid script has even reported its click,
// so a popup can arrive before its job is marked "awaiting-popup". It is remembered here and adopted as soon as the job is marked.
const recentTabs = [];
const running = new Set();
async function adoptPopup(job, tabId) {
  await endJob(job);                                   // drop the entry keyed by the grid tab
  job.tabId = tabId;
  job.step = 'open';
  job.loads = 0;
  await setJob(job);                                    // re-keyed to the popup tab
  status(job, true, false, `InterLink opened the Upload Form for ${job.application_no}. Filling it in…`);
  setTimeout(() => step(tabId), 1500);                 // the popup may already have loaded and pinged "ready" before it was ours
}
chrome.tabs.onCreated.addListener(async (tab) => {
  recentTabs.push({ id: tab.id, at: Date.now() });
  while (recentTabs.length > 10) recentTabs.shift();
  let job = tab.openerTabId != null ? await getJob(tab.openerTabId) : null;
  if (!job || job.step !== 'awaiting-popup') {
    // Firefox does not report the opener of a popup window: the one job that has just clicked the Upload icon is the owner
    const all = await chrome.storage.session.get(null);
    const waiting = Object.values(all).filter((j) => j && j.step === 'awaiting-popup' && Date.now() - (j.popupAt || 0) < 45000);
    job = waiting.length === 1 ? waiting[0] : null;
  }
  if (!job) return;
  await adoptPopup(job, tab.id);
});

async function step(tabId) {
  if (running.has(tabId)) return;                       // the same tab pinged twice: one run at a time (a ping that is lost this way is caught by the 12 s check below)
  running.add(tabId);
  try { await stepOnce(tabId); } finally { running.delete(tabId); }
}
async function stepOnce(tabId) {
  const job = await getJob(tabId);
  if (!job) return;
  job.loads += 1;
  const giveUpAt = job.step === 'search' ? 5 : 8 + 3 * (((job.tables && job.tables.items) || []).length);        // Home, then the grid (a sign-in page in between adds none: it is not scripted)
  if (job.loads > giveUpAt) {
    status(job, false, true, job.step === 'search'
      ? `Could not find ${job.application_no} in the InterLink grid. Please open its Upload Form yourself.`
      : `InterLink did not finish saving ${job.application_no}. Please finish it in the InterLink tab.`);
    endJob(job);
    return;
  }
  await setJob(job);
  // say which InterLink page the sender is on (Home -> search grid -> Upload Form), so a stall shows exactly where it stopped
  try { const tb = await chrome.tabs.get(tabId); status(job, true, false, `Step ${job.loads} (${job.step}): InterLink page ${String(new URL(tb.url).pathname).split('/').pop() || '/'}`); } catch (e) { /* tab info not available */ }
  try {
    if (job.step === 'search') {
      const r = await inPage(tabId, searchAndOpenUpload, [job.application_no]);
      if (!r) return;
      if (r.state === 'signin') { status(job, false, false, 'Sign in to InterLink in the opened tab; sending continues by itself afterwards.'); job.loads -= 1; await setJob(job); return; }
      if (r.state === 'navigating') { status(job, true, false, 'On the InterLink home page: going to the search grid…'); return; }   // "ready" fires again there
      if (r.state === 'error') { status(job, false, true, `${job.application_no}: ${r.message}`); endJob(job); return; }
      // r.state === 'clicked': the popup opens separately and is picked up by chrome.tabs.onCreated above
      job.step = 'awaiting-popup';
      job.popupAt = Date.now();
      await setJob(job);
      // the popup may have been created a moment ago (before this point): take it now
      const early = recentTabs.filter((x) => x.id !== tabId && Date.now() - x.at < 15000).pop();
      if (early) { recentTabs.length = 0; await adoptPopup(job, early.id); return; }
      status(job, true, false, `Found ${job.application_no} - opening its Upload Form…`);
      return;
    }
    if (job.step === 'submitting') {
      const r = await inPage(tabId, checkSubmitted, []);
      if (r && r.ok) status(job, true, true, `${job.application_no} was saved to InterLink with ${job.pdf.name}. Please confirm its Job Status shows Submitted in the search grid.`);
      else status(job, false, true, `InterLink did not accept ${job.application_no}: ${(r && r.message) || 'please check the InterLink tab.'}`);
      endJob(job);
      return;
    }
    const r = await inPage(tabId, fillAndAttach, [job]);
    if (!r) { status(job, true, false, `Step ${job.loads}: the InterLink page changed while it was being filled (waiting for it to finish loading)…`); return; }
    if (r.state === 'signin') { status(job, false, false, 'Sign in to InterLink in the opened tab; sending continues by itself afterwards.'); job.loads -= 1; await setJob(job); return; }
    if (r.patch) { Object.assign(job, r.patch); await setJob(job); }
    if (r.state === 'posting') {                           // Edit / a tab / a reading page was opened; the page reloads and calls "ready" again
      const loadsAt = job.loads;
      setTimeout(async () => { try { const j = await getJob(tabId); if (j && j.loads === loadsAt && j.step === 'open') step(tabId); } catch (e) { /* tab gone */ } }, 12000);   // no reload came: look again
      return;
    }
    if (r.state === 'error') { status(job, false, true, `${job.application_no}: ${r.message}`); endJob(job); return; }
    const problem = await approvalProblem(job);          // asked again: the approval may have been replaced since the job started
    if (problem) { status(job, false, true, `${job.application_no} was NOT saved: ${problem}`); endJob(job); return; }
    const noteText = r.notes && r.notes.length ? ` Not added to InterLink: ${r.notes.join('; ')}.` : '';
    status(job, true, false, `${r.filled} field(s) filled${r.missing && r.missing.length ? ' (not found: ' + r.missing.join(', ') + ')' : ''} and ${job.pdf.name} attached.${noteText} Pressing Save…`);
    job.step = 'submitting';
    await setJob(job);
    const s = await inPage(tabId, pressCommit, []);
    if (s && s.state === 'error') { status(job, false, true, `${job.application_no}: ${s.message}`); endJob(job); return; }
    status(job, true, false, `Save pressed for ${job.application_no}${s && s.button ? ' (' + s.button + ')' : ''}. Waiting for InterLink to confirm…`);
    // Save posts the page and "ready" fires again. If nothing happened after 9 s the click did not reach InterLink's own handler:
    // press it once more through its postback
    setTimeout(async () => {
      try {
        const j = await getJob(tabId);
        if (!j || j.step !== 'submitting' || j.retried) return;
        j.retried = true; await setJob(j);
        const t = await inPage(tabId, forceSave, []);
        if (t && t.state === 'error') { status(j, false, true, `${j.application_no}: ${t.message}`); endJob(j); }
        else status(j, true, false, `Save sent again for ${j.application_no}…`);
      } catch (e) { /* the tab is gone or already moved on */ }
    }, 9000);
    // a successful Save posts back; checkSubmitted runs on the next "ready"
  } catch (e) {
    status(job, false, true, `${job.application_no}: ${e.message}`);
    endJob(job);
  }
}

// ---- functions below run inside the InterLink page (main world, so the DevExpress client API is reachable) -----------

/** Searches the live SearchFormWithHeader grid for an exact Application No. and clicks that row's Upload icon. The
 *  filter box is located by its live screen position under the "Application No." header, not a hardcoded column
 *  index, so this keeps working if InterLink ever reorders its columns. */
async function searchAndOpenUpload(appNo) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const visible = (el) => !!el && el.offsetParent !== null && el.getBoundingClientRect().width > 0;
  if (/login|signin|sign-in/i.test(location.pathname)) return { state: 'signin' };
  for (let i = 0; i < 20 && !window.ASPxClientControl; i++) await sleep(250);
  await sleep(500);

  // The stable site root lands on the portal Home page, which has no grid: follow its own link to the search page
  // (its token is fresh every session - nothing stored), and run again once that page has loaded.
  if (!document.getElementById('PH1_SF1_GVBUILDER_BuilderGridView')) {
    const link = [...document.querySelectorAll('a')].find((x) => /Inhouse\s*&\s*3rd party Lab Upload/i.test(x.textContent || '') && /SearchFormWithHeader/.test(x.getAttribute('href') || ''));
    const m = link && (link.getAttribute('href') || '').match(/window\.location\.href\s*=\s*'([^']+)'/);
    if (!m) return { state: 'error', message: 'InterLink\'s search page link was not found on its home page.' };
    location.href = m[1];
    return { state: 'navigating' };
  }

  const header = [...document.querySelectorAll('*')]
    .find((el) => el.children.length === 0 && el.textContent.trim() === 'Application No.' && visible(el));
  if (!header) return { state: 'error', message: 'the InterLink search grid was not found on this page.' };
  const hr = header.getBoundingClientRect();
  const filterInputs = [...document.querySelectorAll('input[type=text]')].filter(visible);
  const inp = filterInputs.reduce((best, f) => {
    const r = f.getBoundingClientRect();
    if (r.top <= hr.top) return best;                      // the filter row sits below the header row
    const dx = Math.abs(r.left - hr.left);
    return (dx < 40 && (!best || dx < best.dx)) ? { f, dx } : best;
  }, null);
  if (!inp) return { state: 'error', message: 'the Application No. search box was not found on the grid.' };
  const field = inp.f;
  field.focus();
  field.value = appNo;
  field.dispatchEvent(new Event('input', { bubbles: true }));
  ['keydown', 'keypress', 'keyup'].forEach((t) => field.dispatchEvent(new KeyboardEvent(t, { bubbles: true, key: 'Enter', keyCode: 13, which: 13 })));

  let upload = null;
  for (let i = 0; i < 30 && !upload; i++) {                  // up to ~15 s for the grid's callback to settle
    await sleep(500);
    const cell = [...document.querySelectorAll('td.dxgv')].find((td) => td.textContent.trim() === appNo);
    const row = cell && cell.closest('tr');
    upload = row && row.querySelector('input[type=image][name*="imgBtnUForm" i]');
  }
  if (!upload) return { state: 'error', message: `${appNo} was not found in the InterLink search grid (check it exists and you have access to it).` };
  upload.click();                                            // triggers InterLink's own OpenPopupForSearchDefault(...)
  return { state: 'clicked' };
}

async function fillAndAttach(job) {
 try { return await (async () => {      // any script error is reported instead of silently ending the send
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const visible = (el) => !!el && el.offsetParent !== null && el.getBoundingClientRect().width > 0;
  // the main button bar's ids start with UF1_ButtonBar1_ on the confirmed form; other variants may differ, so that
  // prefix is preferred when several buttons share a label, but is never required
  const barButton = (label) => {
    const all = [...document.querySelectorAll('input[type=submit],input[type=button]')]
      .filter((b) => visible(b) && !/_pc/.test(b.id) && b.value.trim().toLowerCase() === label.toLowerCase());
    return all.find((b) => b.id.startsWith('UF1_ButtonBar1_')) || all[0];
  };
  if (/login|signin|sign-in/i.test(location.pathname)) return { state: 'signin' };
  for (let i = 0; i < 20 && !window.ASPxClientControl; i++) await sleep(250);
  await sleep(800);

  const editors = [...document.querySelectorAll('input[name*="DXEditor"],textarea[name*="DXEditor"],select[name*="DXEditor"]')];
  if (!editors.length) {
    const edit = barButton('Edit');
    if (edit) { edit.click(); return { state: 'posting' }; }
    return { state: 'error', message: 'This InterLink page is not an Upload form that can be edited.' };
  }

  const coll = window.ASPxClientControl && ASPxClientControl.GetControlCollection();
  let filled = 0;
  const missing = [];
  // NEXUS-L's control names are the ones the portal posts back; the live page's names carry extra "$TC$" parts and differ per form
  // (Material [10076], Product [10072]), so the three Upload Result values are found by their stable endings instead, and nothing
  // else on the form is touched (the rest is filled by InterLink itself from the application).
  const ctl0 = job.controls || {};
  const pick = (re) => { const k = Object.keys(ctl0).find((n) => re.test(n)); return k === undefined ? undefined : ctl0[k]; };
  const want = { reportNo: pick(/\$DXEditor5$/) ?? pick(/\$DXEditor17$/), issueDate: pick(/\$DXEditor6$/), result: pick(/TRPT_RESULT_DISP/) };
  const live = (list) => list.find(visible) || list[0];
  const setEl = (el, value) => {
    const ctl = coll && (coll.Get(el.name.replace(/\$/g, '_')) || coll.Get(el.id.replace(/_I$/, '')) || coll.Get(el.id));
    try {
      if (ctl && typeof ctl.GetItemCount === 'function') {
        let idx = -1;
        for (let k = 0; k < ctl.GetItemCount(); k++) { if (String(ctl.GetItem(k).text).trim().toLowerCase() === String(value).trim().toLowerCase()) { idx = k; break; } }
        if (idx >= 0) ctl.SetSelectedIndex(idx); else ctl.SetText(value);
      } else if (ctl && typeof ctl.SetValue === 'function') {
        ctl.SetValue(value);
      } else {
        el.value = value;
      }
      ['input', 'change', 'blur'].forEach((t) => el.dispatchEvent(new Event(t, { bubbles: true })));
      if (ctl && ctl.RaiseValueChangedEvent) ctl.RaiseValueChangedEvent();
      return true;
    } catch (e) { return false; }
  };
  // "Sample Information" rows: the first row is already on the form, more are added with its own Add button
  const fillSamples = async (samples) => {
    let done = 0;
    for (let i = 0; i < samples.length && i < 10; i++) {
      let id = document.querySelector(`input[name$="JOB_DUMMY_007_REF_FLD01_SAMPLE_ITEM_${i}"]`);
      if (!id) {
        const add = document.querySelector('input[name$="TpEdr_JOB_DUMMY_007$cbpTableInput$btAdd"]');
        if (!add) break;
        add.click();
        for (let k = 0; k < 20 && !id; k++) { await sleep(400); id = document.querySelector(`input[name$="JOB_DUMMY_007_REF_FLD01_SAMPLE_ITEM_${i}"]`); }
        if (!id) break;
      }
      const set = (suffix, value) => {
        const el = document.querySelector(`input[name$="JOB_DUMMY_007_REF_FLD${suffix}_SAMPLE_ITEM_${i}"]`);
        if (el && value !== undefined && value !== null) { el.value = String(value); ['input', 'change', 'blur'].forEach((t) => el.dispatchEvent(new Event(t, { bubbles: true }))); }
      };
      set('01', samples[i].sample_id_code || `S${String(i + 1).padStart(3, '0')}`);
      set('24', samples[i].sample_description || '');
      set('25', samples[i].sample_color || '');
      done++;
    }
    return done;
  };
  // ---- Test Item Result rows and their readings (Product form [10072], Upload Result tab) --------------------------------------
  // Each NEXUS-L test is matched to InterLink's own list by its method code (VF-E04 ...), picked from the list popup, added with
  // its Sample ID and result, and then its one reading row (value, requirement, rating) is filled on the reading page.
  const codeOf = (str) => ((String(str || '').match(/\b(VF-[A-Z]\d{2}|SATRA TM ?\d+)/i) || [])[1] || '').toUpperCase();
  const T = job.tables || {};
  const wanted = (T.items || []).map((it) => {
    const code = codeOf(it.test_code || it.test_method);
    const rd = (T.readings || []).filter((r) => String(r.test_item_name || '') === String(it.test_name || ''));
    return {
      code, name: it.test_name, sample: it.sample_id_code || 'S001', result: it.result_status || it.test_result || 'Pass',
      readings: rd.map((r) => ({ element: String(r.test_element || '').trim(), value: r.reading_value_raw, requirement: r.target_spec || '', unit: r.unit || '', rating: r.reading_status })),
      value: it.actual_reading || it.actual_result_summary || (rd[0] && rd[0].reading_value_raw) || '',
      requirement: (rd.find((r) => r.target_spec) || {}).target_spec || '', unit: (rd.find((r) => r.unit) || {}).unit || '',
    };
  }).filter((x) => x.code);
  const ratingIdx = (v) => ({ pass: '1', fail: '2', data: '3' })[String(v || '').toLowerCase()] || '0';
  const setNative = (el, v) => { el.value = v; ['input', 'change', 'blur'].forEach((t) => el.dispatchEvent(new Event(t, { bubbles: true }))); };
  const onReading = !!document.querySelector('input[name$="butOk"][value*="Update and Go Back"]') && visible(document.querySelector('input[name$="butOk"][value*="Update and Go Back"]'));
  const done = job.readDone || [];
  const notes = [];
  if (onReading) {
    // the reading page of one test: its method code is in the first row
    const m = document.querySelector('textarea[name$="JOB_DUMMY_013_REF_FLD27_TEST_ITEM_READING_INPUT_0"]');
    const code = codeOf(m && m.value);
    const it = wanted.find((x) => x.code === code);
    if (it) {
      const idxs = [...document.querySelectorAll('textarea[name*="JOB_DUMMY_013_REF_FLD27_TEST_ITEM_READING_INPUT_"]')].map((t) => t.name.match(/INPUT_(\d+)$/)[1]);
      const cell = (i, f) => document.querySelector(`[name$="JOB_DUMMY_013_REF_${f}_TEST_ITEM_READING_INPUT_${i}"]`);
      const fillRow = (i, v, requirement, unit, rating) => {
        const ind = cell(i, 'FLD20'); if (ind) setNative(ind, '3');                    // "="
        const val = cell(i, 'FLD47'); if (val) setNative(val, String(v));
        const req = cell(i, 'FLD04'); if (req && requirement) setNative(req, String(requirement));
        const uom = cell(i, 'FLD07'); if (uom && unit) setNative(uom, String(unit));
        const rat = cell(i, 'FLD23'); if (rat) setNative(rat, ratingIdx(rating));
      };
      const elementOf = (i) => String((cell(i, 'FLD03') || {}).value || '').trim().toLowerCase();
      // 1) rows whose element is named like one of the report's readings ("Specimen 1", "Specimen 2"): each gets its own reading
      let used = 0;
      for (const i of idxs) {
        const rd = it.readings.find((x) => x.element && x.element.toLowerCase() === elementOf(i));
        if (rd) { fillRow(i, rd.value, rd.requirement, rd.unit, rd.rating); used++; }
      }
      if (!used) {
        // 2) otherwise ONE row holds the whole test: the row whose condition numbers (60℃ / 95% / 7 days) appear in the test's name,
        // else the first row
        const nums = (str) => (String(str).match(/\d+/g) || []);
        const want = new Set(nums(it.name));
        let bestIdx = idxs[0], bestScore = -1;
        for (const i of idxs) {
          const sc = nums(elementOf(i)).filter((x) => want.has(x)).length;
          if (sc > bestScore) { bestScore = sc; bestIdx = i; }
        }
        fillRow(bestIdx, it.value, it.requirement, it.unit, it.result);
      }
    }
    await sleep(500);
    document.querySelector('input[name$="butOk"][value*="Update and Go Back"]').click();
    return { state: 'posting', patch: { phase: 'readings', readDone: done.concat(code ? [code] : []) } };
  }
  const onTab2 = !!document.querySelector('[name*="cbpTestReportUploader"]');
  if (onTab2 && job.phase === 'readings') {
    // back on the main page after a reading: next test's reading, or on to the attachment
    const rows = [...document.querySelectorAll('input[name*="FLD07_TEST_ITEM_INP_"][name$="txtSingleValuePickerDisp"]')];
    const next = rows.map((el, n) => ({ n: Number((el.name.match(/TEST_ITEM_INP_(\d+)_txtSingle/) || [])[1]), code: codeOf(el.value) }))
      .find((r) => r.code && !done.includes(r.code) && wanted.some((x) => x.code === r.code));
    if (next) {
      const icon = document.querySelector(`input[name$="uc1_TEST_ITEM_INP_${next.n}$btnAddReading"]`);
      if (icon) { icon.click(); return { state: 'posting', patch: { phase: 'readings' } }; }
    }
    // nothing left to read: carry on below (attachment), the rest of the page is already filled
  }
  if (!onTab2) {
    // Product form [10072] opens on its first tab: Report No. there, then over to "Upload Result" (a postback; this script runs again)
    const rn = live([...document.querySelectorAll('input[name$="$DXEditor17"]')]);
    if (rn && want.reportNo !== undefined && !rn.value) { setEl(rn, want.reportNo); filled++; }
    const tabLink = document.getElementById('UF1_NewTabControl_aTabPage_1');
    if (tabLink) { tabLink.click(); return { state: 'posting' }; }
  } else {
    const fields = [
      [live([...document.querySelectorAll('input[name$="$DXEditor5"]')]), want.reportNo, 'Report No.'],
      [live([...document.querySelectorAll('input[name$="$DXEditor6"]')]), want.issueDate, 'Report Issue Date'],
      [live([...document.querySelectorAll('input[name*="TpEdr_TRPT_RESULT_DISP"][name$="tb1_textbox"],input[name*="TpEdr_TRPT_RESULT_DISP"][name$="tb1"]')]), want.result, 'Overall Result'],
    ];
    for (const [el, value, label] of fields) {
      if (!el || value === undefined || value === '') { if (value !== undefined && value !== '') missing.push(label); continue; }
      if (setEl(el, value)) filled++; else missing.push(label);
    }
    const nSamples = await fillSamples((job.tables && job.tables.samples) || []);
    if (nSamples) filled += nSamples; else if (job.tables && job.tables.samples && job.tables.samples.length) missing.push('Sample Information');

    // Test Item Result: pick each test from InterLink's list popup by its method code, then Add (a callback, the page stays)
    if (job.phase !== 'readings' && wanted.length) {
      const picker = (n) => document.querySelector(`input[name$="FLD07_TEST_ITEM_INP_${n}$btnPickSingleValuePicker"]`);
      const dispOf = (n) => document.querySelector(`input[name*="FLD07_TEST_ITEM_INP_${n}_txtSingleValuePickerDisp"]`);
      const addBtn = () => document.querySelector('input[name$="TpEdr_JOB_DUMMY_008$cbpTableInput$btAdd"]');
      let added = 0;
      for (const it of wanted) {
        // the empty input row: the first row whose test name is still empty
        let n = 0;
        while (dispOf(n) && dispOf(n).value) n++;
        if (!dispOf(n) || !picker(n)) { notes.push(`${it.name}: no free row`); break; }
        const words = (t) => String(t || '').toLowerCase().split(/[^a-z0-9]+/).filter((w) => w.length > 2);
        const score = (r) => { const w = new Set(words(it.name)); return words(r.innerText).filter((x) => w.has(x)).length; };
        const closePopup = async () => {
          // the list popup is a DevExpress popup control: hide it through its own API (clicking around it does not close it)
          try { const pc = ASPxClientControl.GetControlCollection().GetByName('UF1_LOVRL1_ASPxLOVIFramePopup'); if (pc && pc.Hide) pc.Hide(); } catch (e) { /* not there */ }
          await sleep(800);
        };
        // opens the list for row n and finds the test with this method code; `search` types the code into the list's own filter
        // (needed for the full list: 1,110 tests over 111 pages)
        const openAndFind = async (search) => {
          picker(n).click();
          for (let k = 0; k < 40; k++) {
            await sleep(500);
            try {
              const f = document.getElementById('UF1_LOVRL1_ASPxLOVIFramePopup_CIF-1');
              const fd = f && f.contentDocument, fw = f && f.contentWindow;
              if (!fd || !fd.querySelectorAll('tr.dxgvDataRow').length) continue;
              const matching = () => [...fd.querySelectorAll('tr.dxgvDataRow')].filter((r) => r.innerText.toUpperCase().includes(`(${it.code})`));
              const bestOf = (list) => list.sort((x, y) => score(y) - score(x))[0] || null;
              const cur = Number((fd.body.innerText.match(/Page (\d+) of/) || [])[1] || 1);
              if (cur !== 1) {                                            // the list remembers the page it was on: start from page 1
                try { fw.ASPx.GVPagerOnClick('cgvLOVAppList', 'PN0'); } catch (e) { /* single page */ }
                await sleep(1800);
              }
              if (search) {
                const box = [...fd.querySelectorAll('input[type=text]')].find((i) => /DXFREditor/.test(i.id));
                if (!box) return null;
                box.focus(); box.value = it.code;
                ['input', 'change'].forEach((t) => box.dispatchEvent(new fw.Event(t, { bubbles: true })));
                ['keydown', 'keypress', 'keyup'].forEach((t) => box.dispatchEvent(new fw.KeyboardEvent(t, { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true })));
                await sleep(3500);
                const r = bestOf(matching());
                return r ? { row: r, fw } : null;
              }
              let r = bestOf(matching());
              if (!r) {                                                   // the package list has more pages: look on them too
                const pages = (fd.body.innerText.match(/Page \d+ of (\d+)/) || [])[1];
                for (let pg = 1; pg < Number(pages || 1) && !r; pg++) {
                  try { fw.ASPx.GVPagerOnClick('cgvLOVAppList', 'PN' + pg); } catch (e) { break; }
                  await sleep(1800);
                  r = bestOf(matching());
                }
              }
              return r ? { row: r, fw } : null;
            } catch (e) { /* the frame is still loading */ }
          }
          return null;
        };
        // "Enable Full Test Item List" -> "Enable": the test list then holds every InterLink test, searched by code
        const enableFullList = async () => {
          const box = document.querySelector('input[name$="TpEdr_TRPT_LINE_ITEM$tb1_textbox"]');
          if (box && box.value) return true;
          const btn = document.querySelector('[id*="TpEdr_TRPT_LINE_ITEM"][id*="btPick"]');
          if (!btn) return false;
          btn.click();
          for (let k = 0; k < 30; k++) {
            await sleep(500);
            try {
              const f = document.getElementById('UF1_LOVRL1_ASPxLOVIFramePopup_CIF-1');
              const fd = f && f.contentDocument, fw = f && f.contentWindow;
              const r = fd && [...fd.querySelectorAll('tr.dxgvDataRow')].find((x) => /enable/i.test(x.innerText));
              if (r) { r.querySelector('td').dispatchEvent(new fw.MouseEvent('click', { bubbles: true, cancelable: true, view: fw })); await sleep(3500); return !!(box && box.value); }
            } catch (e) { /* loading */ }
          }
          return false;
        };
        // "Enable Full Test Item List" is switched on BEFORE any test is picked: every test is then found by its method code in the
        // full list. If that cannot be done, the package's own shorter list is used.
        await closePopup();
        const fullOn = await enableFullList();
        if (fullOn && !job.fullNoted) { job.fullNoted = true; notes.push('"Enable Full Test Item List" was switched on first'); }
        await closePopup();
        let hit = await openAndFind(fullOn);
        if (!hit && fullOn) { await closePopup(); hit = await openAndFind(false); }
        if (!hit) {
          notes.push(`${it.name} (${it.code}) was not found in InterLink's test list`);
          await closePopup();
          continue;
        }
        hit.row.querySelector('td').dispatchEvent(new hit.fw.MouseEvent('click', { bubbles: true, cancelable: true, view: hit.fw }));
        await sleep(1800);
        const sid = document.querySelector(`input[name$="JOB_DUMMY_008_REF_FLD03_TEST_ITEM_INP_${n}"]`);
        if (sid) setNative(sid, it.sample);
        const sel = document.querySelector(`select[name$="JOB_DUMMY_008_REF_FLD15_TEST_ITEM_INP_${n}"]`);
        if (sel) setNative(sel, ratingIdx(it.result));
        if (addBtn()) { addBtn().click(); added++; }
        for (let k = 0; k < 24 && !dispOf(n + 1); k++) await sleep(400);   // the new empty row shows when the callback is done
      }
      if (added) filled += added;
      // then each added test's reading is filled on its own page (see "readings" above)
      const rowsNow = [...document.querySelectorAll('input[name*="FLD07_TEST_ITEM_INP_"][name$="txtSingleValuePickerDisp"]')].filter((el) => el.value);
      if (added && rowsNow.length) {
        const first = rowsNow[0];
        const n0 = Number((first.name.match(/TEST_ITEM_INP_(\d+)_txtSingle/) || [])[1]);
        const icon = document.querySelector(`input[name$="uc1_TEST_ITEM_INP_${n0}$btnAddReading"]`);
        if (icon) { icon.click(); return { state: 'posting', patch: { phase: 'readings', notes } }; }
      }
    }
  }

  // The Report Attachment upload box: the real Upload Form's markup order does NOT match its visual layout (the file
  // input ends up placed in the DOM after the "Supplementary Attachment" section, even though it sits under the
  // "Report Attachment" label on screen, and there are usually extra hidden template slots for the widget's internal
  // use). So the right input is found by screen position - the one closest to the "Report Attachment" label - not by
  // DOM order or by just grabbing the first file input on the page.
  const label = [...document.querySelectorAll('body *')]
    .find((el) => el.children.length === 0 && /report attachment/i.test(el.textContent || '') && el.textContent.trim().length < 40);
  const fileInputs = [...document.querySelectorAll('input[type=file]')].filter(visible);
  let file = null;
  // Product form [10072]: the Report Attachment is an upload control inside a same-origin frame (TempTestReportUploader.aspx); its
  // file box uploads by itself when a file is chosen
  const upFrame = document.querySelector('iframe[id*="cbpTestReportUploader"]');
  if (upFrame) { try { file = (upFrame.contentDocument && upFrame.contentDocument.querySelector('input[type=file]')) || null; } catch (e) { file = null; } }
  if (!file && label && fileInputs.length) {
    const lr = label.getBoundingClientRect();
    file = fileInputs.reduce((best, f) => {
      const r = f.getBoundingClientRect();
      const d = Math.hypot(r.left - lr.left, r.top - lr.top);
      return (!best || d < best.d) ? { f, d } : best;
    }, null).f;
  }
  if (!file) file = fileInputs[0] || document.querySelector('input[type=file]');   // fall back rather than fail outright
  if (!file) return { state: 'error', message: `filled ${filled} field(s) but no attachment box was found on the form.` };

  const bin = atob(job.pdf.b64);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  const dt = new DataTransfer();
  dt.items.add(new File([bytes], job.pdf.name, { type: 'application/pdf' }));
  file.files = dt.files;
  file.dispatchEvent(new Event('change', { bubbles: true }));
  await sleep(1200);
  const up = [...document.querySelectorAll('a,input[type=button],input[type=submit],span,div')]
    .find((b) => visible(b) && /^upload$/i.test((b.value || b.textContent || '').trim()) && (b.closest('[id*="Upload" i]') || b.id.match(/upload/i)));
  if (up) up.click();
  const shown = () => {
    if (document.body.innerText.includes(job.pdf.name)) return true;
    if ([...document.querySelectorAll('input[name*="cbpTestReportUploader"]')].some((i) => String(i.value).includes(job.pdf.name))) return true;
    try { return !!(upFrame && upFrame.contentDocument && upFrame.contentDocument.body.innerText.includes(job.pdf.name)); } catch (e) { return false; }
  };
  for (let i = 0; i < 60; i++) {                                     // wait (up to 30 s) until the file name shows on the page
    if (shown()) break;
    await sleep(500);
  }
  if (!shown()) return { state: 'error', message: `the attachment ${job.pdf.name} did not finish uploading. Please attach it in the InterLink tab.` };
  return { state: 'filled', filled, missing, notes: (job.notes || []).concat(notes) };
 })(); } catch (e) { return { state: 'error', message: 'a script error stopped the form filling: ' + (e && e.message) }; }
}

/** Commits the form: a real "Submit" button if the page has one, else "Save" - confirmed to be the actual commit action
 *  on the Upload Result tab, which has no Submit button at all. */
async function pressCommit() {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const visible = (el) => !!el && el.offsetParent !== null && el.getBoundingClientRect().width > 0;
  const bar = [...document.querySelectorAll('input[type=submit],input[type=button]')].filter((b) => visible(b) && !/_pc/.test(b.id));
  const byLabel = (label) => bar.find((b) => new RegExp(`^${label}$`, 'i').test(b.value.trim()));
  const commit = byLabel('submit') || byLabel('save');
  if (!commit) return { state: 'error', message: 'neither a Submit nor a Save button was found on the InterLink form.' };
  commit.click();
  window.__nxSaveBtn = commit.name || commit.id;
  for (let i = 0; i < 10; i++) {                                     // confirm boxes ("Are you sure?") when InterLink shows one
    await sleep(500);
    const byId = ['UF1_ButtonBar1_pcConfirmOkCancel_ASPxConfirmOk', 'UF1_PopupYesNoCancel1_pcYesNoCancel_btnPopupYNCYes'].map((id) => document.getElementById(id)).find(visible);
    const byText = [...document.querySelectorAll('input[type=submit],input[type=button],a')].find((b) => visible(b) && /^(ok|yes)$/i.test((b.value || b.textContent || '').trim()));
    const ok = byId || byText;
    if (ok) { ok.click(); break; }
  }
  return { state: 'posting', button: commit.value };
}

/** Second attempt at Save: InterLink's own postback for the Save button (also confirms a "yes / ok" box if one shows). */
async function forceSave() {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const btn = document.getElementById('UF1_ButtonBar1_ASPxButton_1') || [...document.querySelectorAll('input[type=submit],input[type=button]')].find((b) => /^save$/i.test((b.value || '').trim()));
  if (!btn) return { state: 'error', message: 'the Save button could not be found to press it again.' };
  try { if (typeof __doPostBack === 'function' && btn.name) __doPostBack(btn.name, ''); else btn.click(); } catch (e) { btn.click(); }
  for (let i = 0; i < 6; i++) {
    await sleep(500);
    const ok = [...document.querySelectorAll('input[type=submit],input[type=button],a')].find((b) => b.offsetParent !== null && /^(ok|yes)$/i.test((b.value || b.textContent || '').trim()));
    if (ok) { ok.click(); break; }
  }
  return { state: 'posting' };
}

function checkSubmitted() {
  const errs = [...document.querySelectorAll('.dxeErrorCell,.dxeErrorFrame td,[id*="ErrorMessage" i],.validation-summary-errors')]
    .filter((e) => e.offsetParent !== null && e.textContent.trim());
  if (errs.length) return { ok: false, message: errs.map((e) => e.textContent.trim()).slice(0, 3).join('; ') };
  // There is no reliable "still editing" signal that holds across form variants (some have no Submit button at all,
  // so its disappearance can't be used as a success marker). Absence of a validation error is treated as success;
  // the NEXUS-L side asks the user to confirm the Job Status afterward rather than claiming certainty here.
  return { ok: true };
}
