/**
 * InterLink panel. Nobody's InterLink login is stored by NEXUS-L: the PIC signs in to InterLink in their own browser.
 * This file lists Lab References and hands the values plus the report PDF to the "NEXUS-L InterLink Sender" extension.
 */
(() => {
  const root = document.getElementById('sc-root');
  if (!root) return;
  const $ = (s, c = root) => c.querySelector(s);
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  const api = (path, opts = {}) => fetch(BASE + path, opts).then((r) => r.json().then((j) => {
    if (csrfMeta && j.csrf) csrfMeta.content = j.csrf;
    if (!r.ok || j.ok === false) throw new Error(j.error || 'Request failed');
    return j;
  }));
  const post = (path, body) => api(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' },
    body: JSON.stringify(body || {}),
  });

  let labrefs = [];
  let picked = '';
  let t = null;

  const renderLabrefs = () => {
    $('#sc-labrefs').innerHTML = labrefs.map((r) => `<tr><td><input type="radio" name="sc-pick" data-ref="${esc(r.lab_ref_no)}" ${picked === r.lab_ref_no ? 'checked' : ''}></td><td>${esc(r.lab_ref_no)}</td><td>${esc(r.form_category)}</td><td>${esc(r.material_description || r.model_style || r.material_code)}</td><td>${esc(r.overall_result)}</td></tr>`).join('') || '<tr><td colspan="5" class="muted">No match.</td></tr>';
    $('#sc-fill').disabled = !picked;
  };
  const loadLabrefs = async () => {
    try {
      const j = await api(`interlink/labrefs?limit=150&q=${encodeURIComponent($('#sc-q').value.trim())}&category=${encodeURIComponent($('#sc-cat').value)}`);
      labrefs = j.rows || [];
      const cats = [...new Map(labrefs.filter((r) => r.form_slug).map((r) => [r.form_slug, r.form_category || r.form_slug])).entries()];
      const sel = $('#sc-cat');
      if (sel.options.length === 1) cats.forEach(([v, l]) => sel.insertAdjacentHTML('beforeend', `<option value="${esc(v)}">${esc(l)}</option>`));
      renderLabrefs();
    } catch (e) { $('#sc-labrefs').innerHTML = `<tr><td colspan="5">${esc(e.message)}</td></tr>`; }
  };
  $('#sc-q').addEventListener('input', () => { clearTimeout(t); t = setTimeout(loadLabrefs, 350); });
  $('#sc-cat').addEventListener('change', loadLabrefs);
  $('#sc-labrefs').addEventListener('change', (e) => { if (e.target.dataset.ref) { picked = e.target.dataset.ref; $('#sc-fill').disabled = false; } });
  $('#sc-fill').addEventListener('click', () => {
    const app = $('#sc-app').value.trim();
    if (!app || !picked) { $('#sc-msg').textContent = 'Type the Application No. and pick a Lab Reference.'; return; }
    $('#sc-msg').textContent = '';
    window.nxFillHelper(app, null, picked);
  });

  // ---- One-click send: NEXUS-L prepares the values and the report PDF; the "NEXUS-L InterLink Sender" extension in the
  // PIC's own Chrome (their own InterLink sign-in) opens the form, fills it, attaches the PDF and presses Submit ----------
  const hasSender = () => document.documentElement.dataset.nxlSender === '1';
  const reportPdf = (lab) => window.nxReportPdf(lab); // defined in interlink.js
  const installHelp = () => {
    const back = document.createElement('div');
    back.className = 'nx-modal-back';
    back.innerHTML = `<div class="nx-modal" role="dialog" aria-modal="true" style="max-width:520px">
      <p><b>Install the NEXUS-L InterLink Sender (one time)</b><br>One-click sending runs in your own Chrome, with your own InterLink sign-in.</p>
      <ol style="margin:0 0 12px 18px;padding:0;font-size:13px;line-height:1.6">
        <li>Download the sender and unzip it to a folder you keep (for example Documents).</li>
        <li>Open <b>chrome://extensions</b>, turn on <b>Developer mode</b> (top right).</li>
        <li>Press <b>Load unpacked</b> and choose the unzipped folder.</li>
        <li>Reload this page and press Send again.</li>
      </ol>
      <div class="sc-row"><a class="nx-btn nx-primary" href="${BASE}downloads/nexus-l-interlink-sender.zip" download>Download the sender</a></div>
      <div class="nx-modal-actions"><button type="button" class="nx-btn" data-a="close">Close</button></div></div>`;
    document.body.appendChild(back);
    back.addEventListener('click', (e) => { if (e.target === back || e.target.dataset.a === 'close') back.remove(); });
  };
  window.addEventListener('message', (e) => {
    if (e.source !== window || !e.data || e.data.type !== 'NXL_SEND_STATUS') return;
    nxToast(e.data.message, e.data.ok ? 'success' : (e.data.done ? 'error' : 'info'), e.data.done ? 12000 : 5000);
  });
  window.nxFillHelper = async (appNo, fields, labRef) => {
    if (!hasSender()) { installHelp(); return; }
    let data;
    try {
      nxToast(`Preparing ${appNo} for InterLink…`, 'info', 4000);
      data = await post('interlink/center/fill', labRef ? { application_no: appNo, lab_ref_no: labRef } : { application_no: appNo, fields });
    } catch (e) { nxToast('Could not prepare the values: ' + e.message, 'error', 7000); return; }
    // the PDF already generated or browsed on the Upload Form for this application is sent as is
    const att = window.nxAttachedPdf && window.nxAttachedPdf.app === appNo ? window.nxAttachedPdf : null;
    if (!data.lab && !att) { nxToast(`No Lab Reference is linked to ${appNo}, so there is no report to attach.`, 'error', 8000); return; }
    let b64 = att ? att.b64 : '';
    if (!b64) {
      try {
        nxToast(`Creating the report PDF for ${data.lab.lab_ref_no}…`, 'info', 6000);
        b64 = await reportPdf(data.lab);
      } catch (e) { nxToast('Could not create the report PDF: ' + e.message, 'error', 8000); return; }
    }
    window.postMessage({ type: 'NXL_SEND', job: {
      application_no: data.application_no, target_url: data.target_url, controls: data.controls,
      pdf: { name: att ? att.name : `${data.lab.lab_ref_no}_TestReport.pdf`, b64 },
    } }, location.origin);
    nxToast(`Sending ${appNo}: InterLink opens in a new tab and is filled, attached and submitted for you.`, 'info', 7000);
  };

  loadLabrefs();
})();
