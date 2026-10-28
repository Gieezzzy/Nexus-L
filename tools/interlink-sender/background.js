// NEXUS-L InterLink Sender. A job = { application_no, target_url, controls: {inputName: value}, pdf: {name, b64} }.
// It opens the InterLink Upload form in the user's own (signed-in) browser, switches it to Edit when needed, fills the
// boxes, attaches the report PDF, presses Submit and reports back to the NEXUS-L tab. Nothing is stored after the job.

const key = (tabId) => `job_${tabId}`;
const getJob = async (tabId) => (await chrome.storage.session.get(key(tabId)))[key(tabId)];
const setJob = (job) => chrome.storage.session.set({ [key(job.tabId)]: job });
const endJob = (job) => chrome.storage.session.remove(key(job.tabId));
const status = (job, ok, done, message) => chrome.tabs.sendMessage(job.nexusTab, { type: 'status', ok, done, message }).catch(() => {});
const inPage = (tabId, func, args) => chrome.scripting.executeScript({ target: { tabId }, world: 'MAIN', func, args })
  .then((r) => (r && r[0] ? r[0].result : null));

chrome.runtime.onMessage.addListener((m, sender) => {
  if (m.type === 'send' && sender.tab) {
    chrome.tabs.create({ url: m.job.target_url, active: true }, (t) => {
      const job = Object.assign({}, m.job, { nexusTab: sender.tab.id, tabId: t.id, step: 'open', loads: 0 });
      setJob(job);
      status(job, true, false, `InterLink is opening for ${job.application_no}…`);
    });
  }
  if (m.type === 'ready' && sender.tab) step(sender.tab.id);
});
chrome.tabs.onRemoved.addListener(async (tabId) => {
  const job = await getJob(tabId);
  if (job) { status(job, false, true, `The InterLink tab for ${job.application_no} was closed before it was sent.`); endJob(job); }
});

async function step(tabId) {
  const job = await getJob(tabId);
  if (!job) return;
  job.loads += 1;
  if (job.loads > 8) { status(job, false, true, `InterLink did not reach the Submit step for ${job.application_no}. Please finish it in the InterLink tab.`); endJob(job); return; }
  await setJob(job);
  try {
    if (job.step === 'submitting') {
      const r = await inPage(tabId, checkSubmitted, []);
      if (r && r.ok) status(job, true, true, `${job.application_no} was submitted to InterLink with ${job.pdf.name}.`);
      else status(job, false, true, `InterLink did not accept ${job.application_no}: ${(r && r.message) || 'please check the InterLink tab.'}`);
      endJob(job);
      return;
    }
    const r = await inPage(tabId, fillAndAttach, [job]);
    if (!r) return;
    if (r.state === 'signin') { status(job, false, false, 'Sign in to InterLink in the opened tab; sending continues by itself afterwards.'); job.loads -= 1; await setJob(job); return; }
    if (r.state === 'posting') return;                     // Edit was pressed; the page reloads and calls "ready" again
    if (r.state === 'error') { status(job, false, true, `${job.application_no}: ${r.message}`); endJob(job); return; }
    status(job, true, false, `${r.filled} field(s) filled and ${job.pdf.name} attached. Submitting…`);
    job.step = 'submitting';
    await setJob(job);
    const s = await inPage(tabId, pressSubmit, []);
    if (s && s.state === 'error') { status(job, false, true, `${job.application_no}: ${s.message}`); endJob(job); }
    // a successful Submit posts back; checkSubmitted runs on the next "ready"
  } catch (e) {
    status(job, false, true, `${job.application_no}: ${e.message}`);
    endJob(job);
  }
}

// ---- functions below run inside the InterLink page (main world, so the DevExpress client API is reachable) -----------

async function fillAndAttach(job) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const visible = (el) => !!el && el.offsetParent !== null;
  const barButton = (label) => [...document.querySelectorAll('input[type=submit],input[type=button]')]
    .find((b) => b.id.startsWith('UF1_ButtonBar1_') && !/_pc/.test(b.id) && b.value.trim() === label && visible(b));
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

  // the report attachment: put the PDF into the page's file box, then start the upload when the page has an Upload button
  const file = [...document.querySelectorAll('input[type=file]')][0];
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

async function pressSubmit() {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const visible = (el) => !!el && el.offsetParent !== null;
  const bar = [...document.querySelectorAll('input[type=submit],input[type=button]')];
  const submit = bar.find((b) => b.id.startsWith('UF1_ButtonBar1_') && !/_pc/.test(b.id) && /^submit$/i.test(b.value.trim()) && visible(b));
  if (!submit) return { state: 'error', message: 'the Submit button was not found on the InterLink form.' };
  submit.click();
  for (let i = 0; i < 10; i++) {                                     // confirm boxes ("Are you sure?") when InterLink shows one
    await sleep(500);
    const ok = ['UF1_ButtonBar1_pcConfirmOkCancel_ASPxConfirmOk', 'UF1_PopupYesNoCancel1_pcYesNoCancel_btnPopupYNCYes'].map((id) => document.getElementById(id)).find(visible);
    if (ok) { ok.click(); break; }
  }
  return { state: 'posting' };
}

function checkSubmitted() {
  const errs = [...document.querySelectorAll('.dxeErrorCell,.dxeErrorFrame td,[id*="ErrorMessage" i],.validation-summary-errors')]
    .filter((e) => e.offsetParent !== null && e.textContent.trim());
  if (errs.length) return { ok: false, message: errs.map((e) => e.textContent.trim()).slice(0, 3).join('; ') };
  const stillEditing = document.querySelectorAll('input[name*="DXEditor"]').length > 0
    && [...document.querySelectorAll('input[type=submit]')].some((b) => /^submit$/i.test(b.value.trim()) && b.id.startsWith('UF1_ButtonBar1_') && !/_pc/.test(b.id) && b.offsetParent !== null);
  if (stillEditing) return { ok: false, message: 'the form is still open for editing; check the InterLink tab for a message.' };
  return { ok: true };
}
