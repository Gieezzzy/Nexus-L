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

const PORTAL_HOME = 'https://interlink2.intertek.com/';

const key = (tabId) => `job_${tabId}`;
const getJob = async (tabId) => (await chrome.storage.session.get(key(tabId)))[key(tabId)];
const setJob = (job) => chrome.storage.session.set({ [key(job.tabId)]: job });
const endJob = (job) => chrome.storage.session.remove(key(job.tabId));
const status = (job, ok, done, message) => chrome.tabs.sendMessage(job.nexusTab, { type: 'status', ok, done, message }).catch(() => {});
const inPage = (tabId, func, args) => chrome.scripting.executeScript({ target: { tabId }, world: 'MAIN', func, args })
  .then((r) => (r && r[0] ? r[0].result : null));

chrome.runtime.onMessage.addListener((m, sender) => {
  if (m.type === 'send' && sender.tab) {
    chrome.tabs.create({ url: PORTAL_HOME, active: true }, (t) => {
      const job = Object.assign({}, m.job, { nexusTab: sender.tab.id, tabId: t.id, step: 'search', loads: 0 });
      setJob(job);
      status(job, true, false, `InterLink is opening - searching for ${job.application_no}…`);
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
  try {
    const helper = await helperTab(!!req.silent, progress);
    if (helper.error) { reply({ type: 'grid-status', ok: false, message: helper.error }); return; }
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
    if (!req.silent) chrome.alarms.create('close-grid-tab', { delayInMinutes: 1 });   // timer-driven reads do not extend its life
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
        return { error: 'Sign in to InterLink in the tab that just opened, then try again.' };
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
chrome.tabs.onCreated.addListener(async (tab) => {
  if (tab.openerTabId == null) return;
  const job = await getJob(tab.openerTabId);
  if (!job || job.step !== 'awaiting-popup') return;
  await endJob(job);                                   // drop the entry keyed by the grid tab
  job.tabId = tab.id;
  job.step = 'open';
  job.loads = 0;
  await setJob(job);                                    // re-keyed to the popup tab; its own "ready" ping drives it on
  status(job, true, false, `InterLink opened the Upload Form for ${job.application_no}. Filling it in…`);
});

async function step(tabId) {
  const job = await getJob(tabId);
  if (!job) return;
  job.loads += 1;
  const giveUpAt = job.step === 'search' ? 5 : 8;        // Home, then the grid (a sign-in page in between adds none: it is not scripted)
  if (job.loads > giveUpAt) {
    status(job, false, true, job.step === 'search'
      ? `Could not find ${job.application_no} in the InterLink grid. Please open its Upload Form yourself.`
      : `InterLink did not finish saving ${job.application_no}. Please finish it in the InterLink tab.`);
    endJob(job);
    return;
  }
  await setJob(job);
  try {
    if (job.step === 'search') {
      const r = await inPage(tabId, searchAndOpenUpload, [job.application_no]);
      if (!r) return;
      if (r.state === 'signin') { status(job, false, false, 'Sign in to InterLink in the opened tab; sending continues by itself afterwards.'); job.loads -= 1; await setJob(job); return; }
      if (r.state === 'navigating') return;                  // on the portal Home page: heading to the grid, "ready" fires again there
      if (r.state === 'error') { status(job, false, true, `${job.application_no}: ${r.message}`); endJob(job); return; }
      // r.state === 'clicked': the popup opens separately and is picked up by chrome.tabs.onCreated above
      job.step = 'awaiting-popup';
      await setJob(job);
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
    if (!r) return;
    if (r.state === 'signin') { status(job, false, false, 'Sign in to InterLink in the opened tab; sending continues by itself afterwards.'); job.loads -= 1; await setJob(job); return; }
    if (r.state === 'posting') return;                     // Edit was pressed; the page reloads and calls "ready" again
    if (r.state === 'error') { status(job, false, true, `${job.application_no}: ${r.message}`); endJob(job); return; }
    status(job, true, false, `${r.filled} field(s) filled and ${job.pdf.name} attached. Saving…`);
    job.step = 'submitting';
    await setJob(job);
    const s = await inPage(tabId, pressCommit, []);
    if (s && s.state === 'error') { status(job, false, true, `${job.application_no}: ${s.message}`); endJob(job); }
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
  for (const [name, value] of Object.entries(job.controls || {})) {
    const el = document.getElementsByName(name)[0];
    if (!el) { missing.push(name); continue; }
    const ctl = coll && (coll.Get(name.replace(/\$/g, '_')) || coll.Get(el.id.replace(/_I$/, '')));
    try {
      if (ctl && typeof ctl.GetItemCount === 'function') {          // combo box: pick the item whose text matches
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
      filled++;
    } catch (e) { missing.push(name); }
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
  if (label && fileInputs.length) {
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
  for (let i = 0; i < 60; i++) {                                     // wait (up to 30 s) until the file name shows on the page
    if (document.body.innerText.includes(job.pdf.name)) break;
    await sleep(500);
  }
  if (!document.body.innerText.includes(job.pdf.name)) return { state: 'error', message: `the attachment ${job.pdf.name} did not finish uploading. Please attach it in the InterLink tab.` };
  return { state: 'filled', filled, missing };
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
  for (let i = 0; i < 10; i++) {                                     // confirm boxes ("Are you sure?") when InterLink shows one
    await sleep(500);
    const byId = ['UF1_ButtonBar1_pcConfirmOkCancel_ASPxConfirmOk', 'UF1_PopupYesNoCancel1_pcYesNoCancel_btnPopupYNCYes'].map((id) => document.getElementById(id)).find(visible);
    const byText = [...document.querySelectorAll('input[type=submit],input[type=button],a')].find((b) => visible(b) && /^(ok|yes)$/i.test((b.value || b.textContent || '').trim()));
    const ok = byId || byText;
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
