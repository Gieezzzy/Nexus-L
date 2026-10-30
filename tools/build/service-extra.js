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
