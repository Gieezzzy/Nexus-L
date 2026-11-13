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
  // the number printed next to the words "Application No" (THANK YOU page, or the "Application No.: A... - Draft" header) comes first:
  // a copied form can also mention the Application it was copied from
  const appNoOf = () => {
    const b = body();
    return b.match(/Application\s*No\.?\s*:?\s*(A\d{8,12})\b/i) || (b + ' ' + location.href).match(/\b(A\d{8,12})\b/);
  };

  if (/THANK\s*YOU/i.test(body())) { const m = appNoOf(); return { state: 'thank-you', appNo: m ? m[1] : '' }; }

  const errs = [...document.querySelectorAll('.dxeErrorCell,.dxeErrorFrame td,[id*="ErrorMessage" i],.validation-summary-errors')]
    .filter((e) => visible(e) && text(e) && text(e) !== '*' && /missing|required|invalid|cannot|error/i.test(text(e)));
  if (errs.length) return { state: 'error', message: errs.map(text).slice(0, 4).join('; ') };

  // InterLink refuses a Print & Submit with a small toast ("VF eTRF - Product: - Factory ID is missing!"): that is an error, not a view to wait on
  const toast = [...document.querySelectorAll('div,span,td,li,p')].filter(visible)
    .find((e) => !e.querySelector('div,span,td,li,p') && /\b(is|are) (missing|required)\b|must be (entered|selected|filled)/i.test(text(e)) && text(e).length < 250);
  if (toast && !/certify|I hereby/i.test(text(toast))) return { state: 'error', message: 'InterLink refused the eTRF: ' + text(toast) };

  // 1. the confirmation check box: an unticked box whose surrounding text is a confirmation / agreement
  const confirmRe = /confirm|agree|accept|declare|certify|acknowledg|terms|understand|read and/i;
  const formUp = !!(document.getElementById('PH1_NormalFormSelector1_NF1_EGVNF') || document.getElementById('NormalFormSelector1_NF1_EGVNF')) && [...document.querySelectorAll('[id*="TpEdr_"]')].some(visible);
  // a refusal toast ("... Factory ID is missing!") may be gone again after a few seconds: the page script keeps what it saw (see fillNormalFormEtrf)
  let storedRefusals = []; try { storedRefusals = JSON.parse(sessionStorage.getItem('__nxRefusals') || '[]'); } catch (e) { /* no storage */ }
  const lastRefusal = [...storedRefusals, ...(window.__nxRefusals || [])].filter((t) => !/certify|I hereby/i.test(t)).slice(-1)[0];
  if (formUp && lastRefusal) return { state: 'error', message: 'InterLink refused the eTRF: ' + lastRefusal };
  const draftNo = () => { const m = body().match(/Application\s*No\.?\s*:?\s*(A\d{8,12})\b/i); return m ? m[1] : ''; };
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

  // 1b. InterLink's "I hereby certify that I have read and understood the terms and conditions" box is a DevExpress
  //     ASPxCheckBox (no <input type=checkbox>), so tick it through the client API, falling back to clicking its box.
  if (!ticked && /certify|read and understood/i.test(body())) {
    try {
      if (typeof ASPxClientControl !== 'undefined' && ASPxClientControl.GetControlCollection) {
        ASPxClientControl.GetControlCollection().ForEachControl((c) => {
          if (ticked || !c || typeof c.GetChecked !== 'function' || typeof c.SetChecked !== 'function') return;
          const el = c.GetMainElement ? c.GetMainElement() : null;
          if (el && !visible(el)) return;
          if (!c.GetChecked()) { c.SetChecked(true); if (c.CheckedChanged && c.CheckedChanged.FireEvent) c.CheckedChanged.FireEvent(c, {}); ticked = true; }
          else ticked = true;
        });
      }
    } catch (e) { /* fall through to the click */ }
    if (!ticked) {
      const lbl = [...document.querySelectorAll('span,td,div,label')].filter(visible).find((e) => /certify/i.test(e.textContent || '') && (e.textContent || '').length < 200 && !e.querySelector('table'));
      const box = lbl && [...(lbl.parentElement ? lbl.parentElement.querySelectorAll('span,input,td,img,div') : [])].filter(visible).find((e) => e !== lbl && !/certify/i.test(e.textContent || '') && e.getBoundingClientRect().width > 8 && e.getBoundingClientRect().width < 60);
      if (box) { box.click(); ticked = true; }
    }
    if (ticked) await sleep(500);
  }

  // 2. the button that moves on (never Print & Submit / Save again)
  const buttons = [...document.querySelectorAll('input[type=submit],input[type=button],button,a,div.dxbButton,span.dxb')]
    .filter((b) => visible(b) && text(b) && text(b).length < 40);
  const next = buttons.find((b) => /^(next|confirm|proceed|continue|submit|ok|yes)$/i.test(text(b)))
    || buttons.find((b) => /^next\b|confirm/i.test(text(b)));
  if (next && (ticked || !boxes.length) && !/print\s*&\s*submit|^save/i.test(text(next))) {
    if (!ticked && formUp) return { state: 'waiting', buttons: buttons.map(text).slice(0, 10), appNo: draftNo() };   // the form itself is still up: not a confirmation view
    const label = text(next);
    next.click();
    await sleep(600);
    const m = appNoOf();
    return { state: 'advanced', clicked: label, appNo: m ? m[1] : '' };
  }
  return { state: 'waiting', buttons: buttons.map(text).slice(0, 10), boxes: boxes.length, appNo: draftNo() };
}
async function inhouseGridSetPageSize() {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const gridId = 'PH1_SF1_GVBUILDER_BuilderGridView';
  for (let i = 0; i < 20 && !document.getElementById(gridId); i++) await sleep(300);
  if (!document.getElementById(gridId)) return { state: 'error', message: 'The Inhouse & 3rd party search grid was not found.' };
  const before = document.querySelectorAll('tr.dxgvDataRow').length;
  // a plain <select> in the pager ("Page size: 15")
  const sel = [...document.querySelectorAll('select')].find((s) => /page\s*size/i.test((s.parentElement && s.parentElement.innerText) || '') || [...s.options].every((o) => /^\d+$/.test(o.value || o.text)) && s.options.length >= 3 && s.closest('[id*="Pager"],[class*="Pager"],td'));
  let via = '';
  if (sel) {
    const opts = [...sel.options].map((o) => ({ v: o.value, n: parseInt(o.value || o.text, 10) })).filter((o) => o.n > 0);
    const best = opts.sort((a, b) => b.n - a.n)[0];
    if (best) { sel.value = best.v; sel.dispatchEvent(new Event('change', { bubbles: true })); via = 'select ' + best.n; }
  }
  // a DevExpress ASPxComboBox in the pager
  if (!via && typeof ASPxClientControl !== 'undefined' && ASPxClientControl.GetControlCollection) {
    let done = false;
    ASPxClientControl.GetControlCollection().ForEachControl((c) => {
      if (done || !c.GetItemCount || !c.SetSelectedIndex) return;
      const n = c.GetItemCount();
      const nums = []; for (let i = 0; i < n; i++) nums.push(parseInt(c.GetItem(i).text, 10));
      if (n >= 3 && nums.every((x) => x > 0) && nums.includes(15)) {
        const idx = nums.indexOf(Math.max(...nums));
        c.SetSelectedIndex(idx);
        if (c.RaiseValueChanged) c.RaiseValueChanged();
        via = 'combo ' + nums[idx]; done = true;
      }
    });
  }
  if (via) await sleep(2500);
  return { state: 'ok', via, rowsBefore: before, rowsAfter: document.querySelectorAll('tr.dxgvDataRow').length };
}
async function inhouseGridReadPage(wantedJson) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const gridId = 'PH1_SF1_GVBUILDER_BuilderGridView';
  for (let i = 0; i < 20 && !document.getElementById(gridId); i++) await sleep(300);
  const tbl = document.getElementById(gridId);
  if (!tbl) return { state: 'error', message: 'Search grid not found.' };
  const wanted = JSON.parse(wantedJson || '[]').map((w) => String(w).toLowerCase());
  const hdrRow = tbl.querySelector('tr[id*="DXHeadersRow"], tr.dxgvHeader');
  const headers = hdrRow ? [...hdrRow.children].map((td) => (td.innerText || '').replace(/\s+/g, ' ').trim()) : [];
  const pkgIdx = headers.findIndex((h) => /test\s*package/i.test(h));
  const all = [...tbl.querySelectorAll('tr.dxgvDataRow')].map((tr) => [...tr.children].map((td) => (td.innerText || '').replace(/\s+/g, ' ').trim()));
  const rows = (pkgIdx >= 0 && wanted.length)
    ? all.filter((r) => wanted.some((w) => String(r[pkgIdx] || '').toLowerCase().includes(w.replace(/\s*package$/, '').replace(/^gb - full.*/, 'gb')) || String(r[pkgIdx] || '').toLowerCase().includes(w)))
    : all;
  const pagerText = ((document.querySelector('[id*="Pager"],[class*="PagerBottom"],[class*="pagerBottom"]') || {}).innerText || document.body.innerText.match(/Page\s+\d+\s+of\s+\d+\s*\(\d+\s*items?\)/i)?.[0] || '');
  const pm = String(pagerText).match(/Page\s+(\d+)\s+of\s+(\d+)\s*\((\d+)\s*items?\)/i);
  return { state: 'ok', headers, pkgIdx, rows, scanned: all.length, page: pm ? +pm[1] : 0, pages: pm ? +pm[2] : 0, items: pm ? +pm[3] : 0, firstKey: all[0] ? all[0].join('|').slice(0, 120) : '' };
}
async function inhouseGridGoToPage(pageIndex, previousFirstKey) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const gridId = 'PH1_SF1_GVBUILDER_BuilderGridView';
  try { ASPx.GVPagerOnClick(gridId, 'PN' + pageIndex); } catch (e) { return { state: 'error', message: 'The grid pager could not be used: ' + e.message }; }
  for (let i = 0; i < 40; i++) {
    await sleep(400);
    const first = document.querySelector('tr.dxgvDataRow');
    const key = first ? [...first.children].map((td) => (td.innerText || '').replace(/\s+/g, ' ').trim()).join('|').slice(0, 120) : '';
    if (key && key !== previousFirstKey) return { state: 'ok' };
  }
  return { state: 'unchanged' };
}
/** On Home.aspx: navigates to "VF - Test Request/Report Search (Applicant)". */
function goToApplicantGridFromHome() {
  const links = [...document.querySelectorAll('a')];
  const target = links.find((x) => /Test\s*Request\/Report\s*Search\s*\(\s*Applicant\s*\)/i.test(x.textContent || '') && /SearchFormWithHeader/i.test(x.getAttribute('href') || ''))
    || links.find((x) => /Applicant/i.test(x.textContent || '') && /SearchFormWithHeader/i.test(x.getAttribute('href') || '') && !/Inhouse/i.test(x.textContent || ''));
  if (!target) return { ok: false };
  const href = target.getAttribute('href') || '';
  const m = href.match(/window\.location\.href\s*=\s*'([^']+)'/i);
  location.href = m ? m[1] : href;
  return { ok: true };
}

/** Finds a grid column filter box by column header label and sets its value + Enter. */
async function filterApplicantGridColumn(headerLabel, value) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const visible = (el) => !!el && el.offsetParent !== null && el.getBoundingClientRect().width > 0;
  const header = [...document.querySelectorAll('td.dxgvHeader, tr.dxgvHeader td, *')]
    .find((el) => visible(el) && el.innerText && el.innerText.replace(/\s+/g, ' ').trim().toLowerCase() === String(headerLabel).toLowerCase());
  if (!header) return false;
  const hr = header.getBoundingClientRect();
  const inp = [...document.querySelectorAll('input[type=text]')].filter(visible).reduce((best, f) => {
    const r = f.getBoundingClientRect();
    if (r.top <= hr.top) return best;
    const dx = Math.abs(r.left - hr.left);
    return (dx < 50 && (!best || dx < best.dx)) ? { f, dx } : best;
  }, null);
  if (!inp) return false;
  const el = inp.f;
  // what the grid shows now (pager text + first rows): the filter is applied when this changes
  const sig = () => ((document.body.innerText.match(/Page \d+ of \d+ \([\d,]+ items?\)|No data to display|\(0 items\)/) || [''])[0]) + '|'
    + [...document.querySelectorAll('tr.dxgvDataRow')].slice(0, 3).map((t) => t.innerText.slice(0, 60)).join('/');
  const before = sig();
  el.focus();
  el.value = value;
  el.dispatchEvent(new Event('input', { bubbles: true }));
  ['keydown', 'keypress', 'keyup'].forEach((t) => el.dispatchEvent(new KeyboardEvent(t, { bubbles: true, key: 'Enter', keyCode: 13, which: 13 })));
  for (let i = 0; i < 30; i++) { await sleep(500); if (sig() !== before) break; }   // up to 15 s for InterLink to answer
  await sleep(1500);                                                                  // then let the rows finish drawing
  return true;
}

/** Opens the Application No. popup from the Applicant Search Grid (clicks the Application No. link in row). */
async function openApplicationPopup(appNo, testPackage) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const visible = (el) => !!el && el.offsetParent !== null && el.getBoundingClientRect().width > 0;
  for (let i = 0; i < 20 && !document.getElementById('PH1_SF1_GVBUILDER_BuilderGridView'); i++) await sleep(300);
  if (!document.getElementById('PH1_SF1_GVBUILDER_BuilderGridView')) {
    return { state: 'error', message: 'Applicant search grid not loaded.' };
  }
  let link = null;
  let filterOk = null;
  for (let attempt = 0; attempt < 3 && !link; attempt++) {
    if (appNo) filterOk = await filterApplicantGridColumn('Application No.', appNo);
    else if (testPackage) filterOk = await filterApplicantGridColumn('Test Package(s)', testPackage);
    for (let w = 0; w < 16 && !link; w++) {
      await sleep(500);
      const rows = [...document.querySelectorAll('tr.dxgvDataRow')];
      for (const r of rows) {
        if (appNo && !(r.innerText || '').includes(appNo)) continue;
        const eapp = r.querySelector('input[id*="imgBtnEApp"], input[title="Online eApplication"]');
        if (eapp && visible(eapp)) { link = eapp; link.__app = ((r.innerText || '').match(/\bA\d{8,12}\b/) || [''])[0]; break; }
        const a = [...r.querySelectorAll('a')].find((x) => /^A\d{6,}$/i.test((x.textContent || '').trim()) || (appNo && (x.textContent || '').includes(appNo)));
        if (a && visible(a)) { link = a; break; }
      }
    }
  }
  if (!link) {
    const hdr = [...document.querySelectorAll('tr.dxgvHeader td, td.dxgvHeader')].map((td) => (td.innerText || '').replace(/\s+/g, ' ').trim()).filter(Boolean).slice(0, 14).join(' | ');
    const first = ((document.querySelector('tr.dxgvDataRow') || {}).innerText || '').replace(/\s+/g, ' ').slice(0, 90);
    const diag = ` [filter ${filterOk === true ? 'was typed' : 'could not find its box'}; ${document.querySelectorAll('tr.dxgvDataRow').length} row(s) shown, first: ${first}; columns: ${hdr}]`;
    return { state: 'error', message: (appNo ? `Application ${appNo} not found on Applicant Search Grid.` : `No rows found for Test Package ${testPackage}.`) + diag };
  }
  const chosenApp = link.__app || (link.textContent || '').trim();
  link.click();
  return { state: 'clicked', applicationNo: chosenApp };
}

/** Reads visible grid rows on VF - Test Request/Report Search (Applicant) filtered by package label. */
async function scrapeApplicantPackageRows(testPackage) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  for (let i = 0; i < 20 && !document.getElementById('PH1_SF1_GVBUILDER_BuilderGridView'); i++) await sleep(300);
  if (!document.getElementById('PH1_SF1_GVBUILDER_BuilderGridView')) return { state: 'error', message: 'Search grid not found.' };
  if (testPackage) {
    await filterApplicantGridColumn('Test Package(s)', testPackage);
    await sleep(1200);
  }
  const tbl = document.getElementById('PH1_SF1_GVBUILDER_BuilderGridView');
  const hdrRow = tbl.querySelector('tr.dxgvHeader, tr[id*="DXHeadersRow"]');
  const headers = hdrRow ? [...hdrRow.children].map((td) => (td.innerText || '').replace(/\s+/g, ' ').trim()) : [];
  const dataRows = [...tbl.querySelectorAll('tr.dxgvDataRow')].map((tr) =>
    [...tr.children].map((td) => (td.innerText || '').replace(/\s+/g, ' ').trim())
  );
  return { state: 'ok', headers, rows: dataRows };
}

/** Inside an opened VF eTRF - Product view popup: clicks "Copy as New". */
function clickCopyAsNewButton() {
  const visible = (el) => !!el && el.offsetParent !== null && el.getBoundingClientRect().width > 0;
  const textOf = (el) => String(el.value || el.innerText || el.textContent || '').replace(/\s+/g, ' ').trim();
  const btn = [...document.querySelectorAll('input[type="button"],input[type="submit"],button,a,div.dxbButton')]
    .find((b) => visible(b) && /^copy\s+as\s+new$/i.test(textOf(b)));
  if (!btn) return { state: 'waiting' };
  btn.click();
  return { state: 'clicked' };
}

/** Inside an opened submitted Application No. popup: clicks the "Print" button (never "Print & Submit"). */
function clickPrintButton() {
  const visible = (el) => !!el && el.offsetParent !== null && el.getBoundingClientRect().width > 0;
  const textOf = (el) => String(el.value || el.innerText || el.textContent || '').replace(/\s+/g, ' ').trim();
  const btn = [...document.querySelectorAll('input[type="button"],input[type="submit"],button,a,div.dxbButton')]
    .find((b) => visible(b) && /^print$/i.test(textOf(b)));
  if (!btn) return { state: 'waiting' };
  btn.click();
  return { state: 'clicked' };
}
async function inhouseGridHtml() {
  const gridId = 'PH1_SF1_GVBUILDER_BuilderGridView';
  const el = document.getElementById(gridId);
  if (!el) return { state: 'error', message: 'Search grid not found.' };
  const clone = document.documentElement.cloneNode(true);
  clone.querySelectorAll('script,style,link,noscript,img[src^="data:"]').forEach((n) => n.remove());
  const first = document.querySelector('tr.dxgvDataRow');
  const key = first ? [...first.children].map((td) => (td.innerText || '').replace(/\s+/g, ' ').trim()).join('|').slice(0, 120) : '';
  return { state: 'ok', html: '<html>' + clone.innerHTML + '</html>', firstKey: key };
}

/** The Factory ID of the bonding template is empty until a row is picked in the Vendor lookup ("Factory ID is missing!"). Picks it the way a person does. */
async function ensureVendorFactory(vendorId) {
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
  const boxOf = () => {
    const lab = [...document.querySelectorAll('label')].find((l) => l.textContent.replace(/[*\s]/g, '').toLowerCase() === 'factoryid');
    return lab && lab.htmlFor ? document.getElementById(lab.htmlFor) : null;
  };
  let box = boxOf();
  if (!box) return { state: 'skip', message: 'this form has no Factory ID box' };
  if (String(box.value || '').trim()) return { state: 'ok', factoryId: box.value, via: 'already filled' };
  let via = '';
  const btn = document.querySelector('img[id*="TRPT_SUPPLIER_NAME"][id$="btPick_0"]');
  if (btn) {
    try { const r = await lovPick(btn.id, vendorId || '0000507450', 0); via = 'lookup: ' + (r && r.state) + (r && r.message ? ' ' + r.message : ''); } catch (e) { via = 'lookup failed: ' + (e && e.message); }
    await sleep(1500);
    box = boxOf() || box;
  }
  if (!String(box.value || '').trim() && vendorId) {
    const desc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    desc.set.call(box, '0000507452');
    ['input', 'change', 'blur'].forEach((ev) => box.dispatchEvent(new Event(ev, { bubbles: true })));
    via += ' | value set directly';
  }
  return { state: 'ok', factoryId: box.value, via };
}
