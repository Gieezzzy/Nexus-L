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
  // PIC's own Chrome (their own InterLink sign-in) opens the form, fills it, attaches the PDF and saves it ---------------
  // 'none' = not installed, 'old' = installed but older than 1.3.1 (its Home -> grid navigation and reading do not work),
  // 'ok'. A page that was open before the extension was updated still carries the old version, so a reload fixes 'old'.
  const MIN_SENDER = '1.5.5';   // 1.5.5 reports script errors and page changes instead of stopping silently
  const senderState = () => {
    if (document.documentElement.dataset.nxlSender !== '1') return 'none';
    const a = String(document.documentElement.dataset.nxlSenderVersion || '0').split('.').map(Number);
    const b = MIN_SENDER.split('.').map(Number);
    for (let i = 0; i < 3; i++) { if ((a[i] || 0) !== (b[i] || 0)) return (a[i] || 0) > (b[i] || 0) ? 'ok' : 'old'; }
    return 'ok';
  };
  const hasSender = () => senderState() === 'ok';
  // Which browser this is, so the right package and steps are shown (Edge is Chromium: same package as Chrome)
  const browserKind = () => {
    const ua = navigator.userAgent || '';
    if (/\bFirefox\//.test(ua)) return 'firefox';
    if (/\bEdg(e|A|iOS)?\//.test(ua)) return 'edge';
    if (/\b(Chrome|Chromium|CriOS)\//.test(ua)) return 'chrome';
    return 'other';
  };
  const BROWSERS = {
    chrome: { name: 'Chrome', zip: 'nexus-l-interlink-sender.zip', steps: [
      'Download the sender and unzip it to a folder you keep (for example Documents).',
      'Open <b>chrome://extensions</b> and turn on <b>Developer mode</b> (top right).',
      'Press <b>Load unpacked</b> and choose the unzipped folder.',
      'Reload this page (Ctrl+F5) and press Send again.'] },
    edge: { name: 'Microsoft Edge', zip: 'nexus-l-interlink-sender.zip', steps: [
      'Download the sender and unzip it to a folder you keep (for example Documents).',
      'Open <b>edge://extensions</b> and turn on <b>Developer mode</b> (left side).',
      'Press <b>Load unpacked</b> and choose the unzipped folder.',
      'Reload this page (Ctrl+F5) and press Send again.'] },
    firefox: { name: 'Firefox', zip: 'nexus-l-interlink-sender-firefox.zip', steps: [
      'Download the sender and unzip it to a folder you keep (Firefox 128 or newer).',
      'Open <b>about:debugging#/runtime/this-firefox</b> and press <b>Load Temporary Add-on</b>, then pick <b>manifest.json</b> in the unzipped folder (a temporary add-on is removed when Firefox closes).',
      'Open <b>about:addons</b> → the add-on → <b>Permissions</b> and switch on access to <b>interlink2.intertek.com</b> and <b>192.168.32.43</b>.',
      'Reload this page (Ctrl+F5) and press Send again.'] },
  };
  const installHelp = () => {
    const kind = browserKind();
    const mine = BROWSERS[kind] || BROWSERS.chrome;
    const sameZip = Object.entries(BROWSERS).filter(([k]) => k !== kind && BROWSERS[k].zip === mine.zip).map(([, v]) => v.name);
    const back = document.createElement('div');
    back.className = 'nx-modal-back';
    back.innerHTML = `<div class="nx-modal" role="dialog" aria-modal="true" style="max-width:560px">
      <p><b>Install the NEXUS-L InterLink Sender (one time)</b><br>${kind === 'other'
        ? 'This browser is not recognised. Choose the package for the browser you use.'
        : `You are using <b>${mine.name}</b>: this is the package and the steps for it.`}</p>
      <ol style="margin:0 0 12px 18px;padding:0;font-size:13px;line-height:1.6">${mine.steps.map((x) => `<li>${x}</li>`).join('')}</ol>
      <div class="sc-row"><a class="nx-btn nx-primary" href="${BASE}downloads/${mine.zip}" download>Download for ${mine.name}</a></div>
      ${sameZip.length ? `<p class="muted sm" style="margin:6px 0 0">The same file also works in ${sameZip.join(' and ')}.</p>` : ''}
      <p class="muted sm" style="margin:10px 0 4px">Using a different browser?</p>
      <div class="sc-row" style="gap:6px;flex-wrap:wrap">${Object.entries(BROWSERS).filter(([k]) => k !== kind).map(([, v]) => `<a class="nx-btn" href="${BASE}downloads/${v.zip}" download>Download for ${v.name}</a>`).join(' ')}</div>
      <div class="nx-modal-actions"><button type="button" class="nx-btn" data-a="close">Close</button></div></div>`;
    document.body.appendChild(back);
    back.addEventListener('click', (e) => { if (e.target === back || e.target.dataset.a === 'close') back.remove(); });
  };

  // The one thing this page can honestly show: whether the sender extension is present in THIS browser right now.
  // Whether you are signed in to InterLink itself is never shown here - NEXUS-L keeps nobody's InterLink session, so
  // 1-Click Send always opens InterLink fresh and lets InterLink ask you to sign in there if you are not already.
  const renderSenderStatus = () => {
    const dot = $('#sc-status-dot');
    const text = $('#sc-status-text');
    const install = $('#sc-status-install');
    if (!dot) return;
    const st = senderState();
    if (st === 'ok') {
      dot.className = 'sc-dot sc-dot-ok';
      text.textContent = `Sender extension v${document.documentElement.dataset.nxlSenderVersion}: detected in this browser. When you press "Send to InterLink", InterLink opens and asks you to sign in there if you are not already - NEXUS-L cannot check that in advance.`;
      install.hidden = true;
    } else if (st === 'old') {
      dot.className = 'sc-dot sc-dot-off';
      const v = document.documentElement.dataset.nxlSenderVersion;
      text.textContent = `Sender extension${v ? ' v' + v : ''}: out of date (needs v${MIN_SENDER} or newer). Download it, reload it in chrome://extensions, then reload this page.`;
      install.hidden = false;
    } else {
      dot.className = 'sc-dot sc-dot-off';
      text.textContent = 'Sender extension: not detected in this browser. If you just installed or updated it, reload this page.';
      install.hidden = false;
    }
  };
  $('#sc-status-install')?.addEventListener('click', installHelp);
  { const b = $('#sc-status-install'); if (b) b.textContent = `Install the sender for ${(BROWSERS[browserKind()] || BROWSERS.chrome).name}`; }
  renderSenderStatus();
  window.addEventListener('focus', renderSenderStatus);   // catches "I just installed it and switched back to this tab"
  setInterval(renderSenderStatus, 4000);

  window.addEventListener('message', (e) => {
    if (e.source !== window || !e.data || e.data.type !== 'NXL_SEND_STATUS') return;
    nxToast(e.data.message, e.data.ok ? 'success' : (e.data.done ? 'error' : 'info'), e.data.done ? 12000 : 5000);
  });
  window.nxFillHelper = async (appNo, fields, labRef) => {
    if (!hasSender()) { installHelp(); return; }
    let data;
    try {
      nxToast(`Preparing ${appNo} for InterLink…`, 'info', 4000);
      data = await post('interlink/center/fill', labRef ? (fields ? { application_no: appNo, lab_ref_no: labRef, fields } : { application_no: appNo, lab_ref_no: labRef }) : { application_no: appNo, fields });
    } catch (e) { nxToast('Could not prepare the values: ' + e.message, 'error', 7000); return; }
    // the PDF already generated or browsed on the Upload Form for this application is sent as is
    // only a report signed by a Manager / Supervisor goes to InterLink: the signed PDF attached on the Upload Form, or
    // the signed PDF of the Lab Reference's approval
    let att = window.nxAttachedPdf && window.nxAttachedPdf.app === appNo && window.nxAttachedPdf.approved ? window.nxAttachedPdf : null;
    if (!att && data.lab) {
      try {
        const st = await api(`dtr/approval/status?lab_ref=${encodeURIComponent(data.lab.lab_ref_no)}&application_no=${encodeURIComponent(appNo)}`);
        if (st.approval && st.approval.status === 'approved') {
          const r = await fetch(st.approval.file, { credentials: 'same-origin' });
          const buf = new Uint8Array(await r.arrayBuffer());
          let bin = '';
          for (let i = 0; i < buf.length; i += 0x8000) bin += String.fromCharCode.apply(null, buf.subarray(i, i + 0x8000));
          att = { name: window.nxPdfName ? window.nxPdfName(appNo, data.lab.lab_ref_no) : `${appNo}.pdf`, b64: btoa(bin) };
        }
      } catch (e) { /* reported below */ }
    }
    if (!att) {
      nxToast(`${appNo} cannot be sent yet: its report needs a Manager / Supervisor approval. Open the Upload Form → Generate Report → Request approval (QR).`, 'error', 10000);
      return;
    }
    window.postMessage({ type: 'NXL_SEND', job: {
      application_no: data.application_no, target_url: data.target_url, controls: data.controls,
      pdf: { name: att.name || `${appNo}.pdf`, b64: att.b64 },
      tables: data.tables || { samples: [], items: [], readings: [] },
      // the extension asks NEXUS-L again before it opens InterLink and before it presses Save: no approval, no Save
      verify: { url: new URL(BASE + 'dtr/approval/verify?lab_ref=' + encodeURIComponent((att && att.lab_ref_no) || (data.lab ? data.lab.lab_ref_no : (labRef || ''))) + '&application_no=' + encodeURIComponent(appNo), location.origin).href },
    } }, location.origin);
    nxToast(`Sending ${appNo}: InterLink opens in a new tab and is filled, attached and saved for you.`, 'info', 7000);
  };

  loadLabrefs();
})();
