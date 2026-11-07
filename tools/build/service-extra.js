// Page functions that only the background service uses (appended to interlink-core.js by build-service-scripts.js).

/** On InterLink's search grid: finds the application's row and reads its Report No., Report Issue Date and Job Status. */
async function readGridRow(appNo) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const visible = (el) => !!el && el.offsetParent !== null && el.getBoundingClientRect().width > 0;
  const header = [...document.querySelectorAll('*')].find((el) => el.children.length === 0 && el.textContent.trim() === 'Application No.' && visible(el));
  if (!header) return { state: 'error', message: 'the InterLink search grid was not found.' };
  const hr = header.getBoundingClientRect();
  const inp = [...document.querySelectorAll('input[type=text]')].filter(visible).reduce((best, f) => {
    const r = f.getBoundingClientRect();
    if (r.top <= hr.top) return best;
    const dx = Math.abs(r.left - hr.left);
    return (dx < 40 && (!best || dx < best.dx)) ? { f, dx } : best;
  }, null);
  if (!inp) return { state: 'error', message: 'the Application No. search box was not found.' };
  inp.f.focus();
  inp.f.value = appNo;
  inp.f.dispatchEvent(new Event('input', { bubbles: true }));
  ['keydown', 'keypress', 'keyup'].forEach((t) => inp.f.dispatchEvent(new KeyboardEvent(t, { bubbles: true, key: 'Enter', keyCode: 13, which: 13 })));
  let row = null;
  for (let i = 0; i < 30 && !row; i++) {
    await sleep(500);
    const cell = [...document.querySelectorAll('td.dxgv')].find((td) => td.textContent.trim() === appNo);
    row = cell && cell.closest('tr');
  }
  if (!row) return { state: 'ok', found: false };
  const at = (label) => {
    const h = [...document.querySelectorAll('td.dxgvHeader, tr.dxgvHeader td')].find((x) => x.innerText.trim() === label);
    if (!h) return '';
    const hx = h.getBoundingClientRect();
    const c = [...row.querySelectorAll('td')].find((td) => { const r = td.getBoundingClientRect(); const cx = r.left + r.width / 2; return cx >= hx.left && cx <= hx.right; });
    return c ? c.innerText.trim() : '';
  };
  return { state: 'ok', found: true, reportNo: at('Report No.'), issueDate: at('Report Issue Date'), status: at('Job Status'), result: at('Overall Result') };
}

/** The messages InterLink shows in a pop-up bubble (a "toast") after Save, e.g. "A report with the entered report number has already exists!". */
function readNotices() {
  const visible = (el) => !!el && el.offsetParent !== null && el.getBoundingClientRect().width > 0;
  const word = /(already exist|has already|duplicate|invalid|cannot|can not|unable|error|failed|not allowed|is required|must be)/i;
  const out = [];
  for (const el of document.querySelectorAll('body *')) {
    if (el.children.length > 2 || !visible(el)) continue;
    const t = (el.innerText || '').replace(/\s+/g, ' ').trim();
    if (t.length < 12 || t.length > 220 || !word.test(t)) continue;
    // a bubble floats over the page: it, or something around it, is fixed or absolutely placed
    let p = el, floating = false;
    for (let i = 0; i < 6 && p && !floating; i++, p = p.parentElement) {
      const pos = getComputedStyle(p).position;
      floating = (pos === 'fixed' || pos === 'absolute') && p.getBoundingClientRect().width < 700;
    }
    if (floating && !out.includes(t)) out.push(t);
  }
  return out.slice(0, 5);
}

/** On an open eTRF form: every pick-list ("search") button with its field code, label, InterLink list id and columns. Reads only. */
async function lovInventory() {
  const out = [];
  const seen = new Set();
  for (const b of document.querySelectorAll('img[id$="_btPick"], input[id*="btnPickSingleValuePicker"]')) {
    const oc = b.getAttribute('onclick') || '';
    const strs = [...oc.matchAll(/'([^']*)'/g)].map((m) => m[1]);
    const mi = strs.findIndex((s) => /^(GetRefListValByTypeLOV|GetRefListVal)/i.test(s));
    const codeM = b.id.match(/TpEdr_(.+?)_btPick/);
    const code = codeM ? codeM[1] : (b.id.match(/(JOB_DUMMY_\d+_[A-Z0-9_]+?)_FIBER|(JOB_DUMMY_\d+)/) || [])[0] || b.id.slice(-40);
    let label = '';
    for (let n = b.parentElement, i = 0; n && i < 6 && !label; n = n.parentElement, i++) {
      const t = (n.innerText || '').split('\n').map((s) => s.trim()).filter(Boolean)[0] || '';
      if (t && t.length < 80) label = t.replace(/^\*\s*/, '');
    }
    const key = b.id;
    if (seen.has(key)) continue;
    seen.add(key);
    // the list argument reads "<list id>|<filter column>|[<parent field>]": the list is narrowed by what the parent field holds
    const parts = mi >= 0 ? String(strs[mi + 1] || '').split('|') : [''];
    out.push({ id: b.id, field: code, label, list_id: parts[0] || '', filter_col: parts[1] || '', parent_field: (parts[2] || '').replace(/[\[\]]/g, ''), method: mi >= 0 ? strs[mi] : '', columns: strs[1] || '' });
  }
  return { state: 'ok', title: document.title, pickers: out };
}

/** Opens one pick-list popup, reads its pages from startPage on (until about budgetMs is used) and closes it without choosing anything. */
async function lovHarvest(btnId, startPage, budgetMs) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const t0 = Date.now();
  const btn = document.getElementById(btnId);
  if (!btn) return { state: 'error', message: 'pick-list button not found: ' + btnId };
  const pop = ASPxClientControl.GetControlCollection().GetByName('PH1_NormalFormSelector1_NF1_LOVRL1_ASPxLOVIFramePopup');
  const frame = () => document.querySelector('iframe[id*="LOVRL1_ASPxLOVIFramePopup"]');
  try { pop.Hide(); } catch (e) { /* not open */ }
  await sleep(500);
  btn.click();
  let w = null;
  for (let i = 0; i < 40; i++) {
    await sleep(400);
    try {
      const fw = frame().contentWindow;
      if (fw && fw.cgvLOVAppList && fw.document.querySelector('tr[id*="DXHeadersRow"]')) { w = fw; break; }
    } catch (e) { /* loading */ }
  }
  if (!w) { try { pop.Hide(); } catch (e) { /* ignore */ } return { state: 'error', message: 'the list did not open' }; }
  await sleep(600);
  const g = w.cgvLOVAppList;
  const hdrCells = [...w.document.querySelectorAll('tr[id*="DXHeadersRow"]')[0].children];
  const cols = hdrCells.map((c, i) => ({ i, t: (c.innerText || '').replace(/\s+/g, ' ').trim() })).filter((c) => c.t && c.t !== '#');
  const readRows = () => [...w.document.querySelectorAll('tr[id*="DXDataRow"]')].map((r) => {
    const cells = [...r.children].map((c) => (c.innerText || '').replace(/\s+/g, ' ').trim());
    return cols.map((c) => cells[c.i] ?? '');
  });
  const pc = g.pageCount || 1;
  const empty = /No data to display/i.test(w.document.body.innerText) && !w.document.querySelector('tr[id*="DXDataRow"]');
  const rows = [];
  let p = Math.max(0, startPage || 0);
  let first = true;
  for (; p < pc; p++) {
    if (!first && Date.now() - t0 > (budgetMs || 25000)) break;
    if (p > 0 || (startPage || 0) > 0) {
      const before = JSON.stringify(readRows().slice(0, 2));
      g.GotoPage(p);
      for (let i = 0; i < 25; i++) { await sleep(250); if (JSON.stringify(readRows().slice(0, 2)) !== before || pc === 1) break; }
    }
    rows.push(...readRows());
    first = false;
  }
  try { pop.Hide(); } catch (e) { /* ignore */ }
  return { state: p >= pc ? 'done' : 'partial', headers: cols.map((c) => c.t), rows, page_count: pc, next_page: p, empty };
}

/** Opens a pick-list popup, goes to page `page` and clicks the row whose first column is `text`, exactly like choosing it by hand (sets the field and the fields that depend on it). */
async function lovPick(btnId, text, page) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const btn = document.getElementById(btnId);
  if (!btn) return { state: 'error', message: 'pick-list button not found' };
  const pop = ASPxClientControl.GetControlCollection().GetByName('PH1_NormalFormSelector1_NF1_LOVRL1_ASPxLOVIFramePopup');
  try { pop.Hide(); } catch (e) { /* not open */ }
  await sleep(400);
  btn.click();
  let w = null;
  for (let i = 0; i < 40; i++) {
    await sleep(400);
    try { const fw = document.querySelector('iframe[id*="LOVRL1_ASPxLOVIFramePopup"]').contentWindow; if (fw.cgvLOVAppList && fw.document.querySelector('tr[id*="DXDataRow"]')) { w = fw; break; } } catch (e) { /* loading */ }
  }
  if (!w) return { state: 'error', message: 'the list did not open' };
  await sleep(500);
  const rows = () => [...w.document.querySelectorAll('tr[id*="DXDataRow"]')];
  const firstCol = (r) => { const hdr = [...w.document.querySelectorAll('tr[id*="DXHeadersRow"]')[0].children]; const i = hdr.findIndex((c) => { const t = (c.innerText || '').trim(); return t && t !== '#'; }); return ((r.children[i] || {}).innerText || '').replace(/\s+/g, ' ').trim(); };
  if (page > 0) {
    const before = JSON.stringify(rows().slice(0, 2).map((r) => r.innerText));
    w.cgvLOVAppList.GotoPage(page);
    for (let i = 0; i < 25; i++) { await sleep(250); if (JSON.stringify(rows().slice(0, 2).map((r) => r.innerText)) !== before) break; }
  }
  const multi = !!w.document.getElementById('btnUpToParent');
  if (multi) {
    // a multi-select list keeps what was chosen before: clear that first so only this one value is set
    const clear = [...w.document.querySelectorAll('input,div,span,a')].find((e) => e.children.length === 0 && (e.value || e.innerText || '').trim() === 'Clear Selection');
    if (clear && w.cgvLOVAppList.GetSelectedRowCount && w.cgvLOVAppList.GetSelectedRowCount() > 0) { clear.click(); await sleep(1200); }
  }
  const row = rows().find((r) => firstCol(r) === text);
  if (!row) { try { pop.Hide(); } catch (e) { /* ignore */ } return { state: 'error', message: 'row not found: ' + text }; }
  (row.querySelector('td') || row).click();
  await sleep(1000);
  if (multi) { const ok = w.document.getElementById('btnUpToParent'); if (ok) ok.click(); await sleep(1500); }   // the OK button hands the choice to the form
  else await sleep(300);
  return { state: 'ok' };
}

/**
 * After Print & Submit: InterLink shows a confirmation view (a confirmation check box and a Next button) and then the
 * "THANK YOU! For your online job submission" page with the Application No. One call does one step:
 *   {state:'thank-you', appNo}   the last page is reached
 *   {state:'advanced', clicked}  the confirmation box was ticked and Next (or Confirm / Proceed ...) pressed
 *   {state:'error', message}     InterLink shows a validation message
 *   {state:'waiting', buttons}   nothing to do yet (the page is loading); buttons = what is on screen, for the log
 * It never presses Print & Submit or Save again.
 */
async function advanceNormalFormSubmitStep() {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const visible = (el) => !!el && el.offsetParent !== null && el.getBoundingClientRect().width > 0;
  const text = (el) => String(el.value || el.textContent || '').replace(/\s+/g, ' ').trim();
  const body = () => (document.body && document.body.innerText) || '';
  const appNoOf = () => (body() + ' ' + location.href).match(/\b(A\d{8,12})\b/);

  if (/THANK\s*YOU/i.test(body())) { const m = appNoOf(); return { state: 'thank-you', appNo: m ? m[1] : '' }; }

  const errs = [...document.querySelectorAll('.dxeErrorCell,.dxeErrorFrame td,[id*="ErrorMessage" i],.validation-summary-errors')]
    .filter((e) => visible(e) && text(e) && text(e) !== '*' && /missing|required|invalid|cannot|error/i.test(text(e)));
  if (errs.length) return { state: 'error', message: errs.map(text).slice(0, 4).join('; ') };

  // 1. the confirmation check box: an unticked box whose surrounding text is a confirmation / agreement
  const confirmRe = /confirm|agree|accept|declare|certify|acknowledg|terms|understand|read and/i;
  const formUp = !!document.getElementById('PH1_NormalFormSelector1_NF1_EGVNF') && [...document.querySelectorAll('[id*="TpEdr_"]')].some(visible);
  const boxes = [
    ...[...document.querySelectorAll('input[type="checkbox"]')].filter((b) => !b.checked).map((b) => ({ el: b, hit: b })),
    ...[...document.querySelectorAll('span.dxWeb_edtCheckBoxUnchecked,span.dxICheckBox')].filter((b) => /Unchecked/i.test(b.className)).map((b) => ({ el: b, hit: b })),
  ].filter((x) => visible(x.hit) || visible(x.el.parentElement));
  let ticked = false;
  for (const x of boxes) {
    let n = x.el, ctx = '';
    for (let i = 0; i < 4 && n && !ctx; i++) { n = n.parentElement; const t = n ? text(n) : ''; if (t && t.length < 400) ctx = t; }
    if (!formUp || confirmRe.test(ctx)) {
      if (confirmRe.test(ctx) || boxes.length === 1) { (visible(x.hit) ? x.hit : x.el.parentElement).click(); ticked = true; await sleep(300); break; }
    }
  }

  // 2. the button that moves on (never Print & Submit / Save again)
  const buttons = [...document.querySelectorAll('input[type=submit],input[type=button],button,a,div.dxbButton,span.dxb')]
    .filter((b) => visible(b) && text(b) && text(b).length < 40);
  const next = buttons.find((b) => /^(next|confirm|proceed|continue|submit|ok|yes)$/i.test(text(b)))
    || buttons.find((b) => /^next\b|confirm/i.test(text(b)));
  if (next && (ticked || !boxes.length) && !/print\s*&\s*submit|^save/i.test(text(next))) {
    if (!ticked && formUp) return { state: 'waiting', buttons: buttons.map(text).slice(0, 10) };   // the form itself is still up: not a confirmation view
    const label = text(next);
    next.click();
    await sleep(600);
    const m = appNoOf();
    return { state: 'advanced', clicked: label, appNo: m ? m[1] : '' };
  }
  return { state: 'waiting', buttons: buttons.map(text).slice(0, 10), boxes: boxes.length };
}
