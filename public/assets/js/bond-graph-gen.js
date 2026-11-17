/* Shoe Bonding (U60) > Generate Report: lets the user choose between FT Adhesion Graph (existing)
   and the 6 Pull Test Graphs (Rivet, Velcro, Eyelet, Lace Holder, Heel Pull Tab, Tongue Pull Tab),
   using each category's saved Report Designer layout, then previews and saves/downloads the PNG. */
(() => {
  'use strict';
  const btn = document.getElementById('btn-bond-generate');
  if (!btn || (!window.NxBondGraph && !window.NxPullGraph)) return;
  const G = window.NxBondGraph;
  const PG = window.NxPullGraph;
  const BASE = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/?$/, '/');
  const csrfMeta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  const toast = (t, k) => (window.nxToast ? window.nxToast(t, k || 'info', 6000) : alert(t));
  const el = (tag, cls, txt) => { const n = document.createElement(tag); if (cls) n.className = cls; if (txt != null) n.textContent = txt; return n; };
  const api = (path, body) => fetch(BASE + path, Object.assign({ credentials: 'same-origin', headers: Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '' } : {}) }, body ? { method: 'POST', body: JSON.stringify(body) } : {}))
    .then((r) => r.json().catch(() => ({})).then((j) => { if (csrfMeta && j.csrf) csrfMeta.content = j.csrf; if (!r.ok || j.ok === false) throw new Error(j.error || j.message || 'Request failed (' + r.status + ')'); return j; }));

  // A pull test (Rivet, Eyelet, D-ring ...) has its OWN Lab Ref (DTP20261116EYA3-L), different from the bonding one: when the report open is a pull test
  // the pull form's Lab Ref is the one drawn and saved; the bonding Lab Ref is only used for a bonding report.
  const isPull = () => (document.getElementById('dtr-report-kind')?.value || 'bonding') === 'pull';
  const labRef = () => ((isPull() ? document.getElementById('pf_LabRefNo')?.value : document.getElementById('bf_LabRefNo')?.value) || '').trim();
  // the kind of pull test from the letters of its Lab Ref (building letter A / B removed): EYA -> EY, RVTA -> RVT ...
  const catOf = (ref) => {
    const m = /^DTP\d{8}([A-Z]+?)(?:-|\d)/i.exec(ref) || /^DTP\d{8}([A-Z]+)/i.exec(ref);
    const code = m ? m[1].toUpperCase().replace(/[AB]$/, '') : '';
    if (/^(EY|EYE|DR)/.test(code)) return 'eyelet';          // eyelet, and the D-ring (same columns)
    if (/^(RVT|RV|RIVET)/.test(code)) return 'rivet';
    if (/^(LLH|LL|LH)/.test(code)) return 'lace_holder';
    if (/^(HP|HPT)/.test(code)) return 'heel_pull_tab';
    if (/^(TP|TPT|TT)/.test(code)) return 'tongue_pull_tab';
    if (/^(VL|VEL)/.test(code)) return 'velcro';
    return '';
  };

  const REPORT_TYPES = [
    { key: 'ft_adhesion',     label: 'FT Adhesion Graph',     desc: 'Upper to Foxing / Foxing to Outsole / Filler to Outsole bonding curve' },
    { key: 'rivet',           label: 'Rivet Graph',           desc: 'Rivet pull test results table & force-time curves' },
    { key: 'velcro',          label: 'Velcro Graph',          desc: 'Velcro peel / shear test results table & curves' },
    { key: 'eyelet',          label: 'Eyelet Graph',          desc: 'Eyelet pull test results table & force-time curves' },
    { key: 'lace_holder',     label: 'Lace Holder Graph',     desc: 'Lace holder pull test results table & force-time curves' },
    { key: 'heel_pull_tab',   label: 'Heel Pull Tab Graph',   desc: 'Heel pull tab test results table & force-time curves' },
    { key: 'tongue_pull_tab', label: 'Tongue Pull Tab Graph', desc: 'Tongue pull tab test results table & force-time curves' }
  ];

  const openDialog = (ref, canvas, titlePrefix, fileSuffix) => {
    const ov = el('div', 'bg-ov'); ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true'); ov.setAttribute('aria-label', titlePrefix);
    const box = el('div', 'bg-box');
    box.append(el('h3', '', `${titlePrefix} - ${ref}`));
    const view = el('div', 'bg-view'); const img = el('img'); img.alt = 'Report preview'; img.src = canvas.toDataURL('image/png'); view.append(img); box.append(view);
    const opts = el('div', 'bg-opts');
    const mk = (id, label, checked, note) => { const l = el('label', 'bg-opt'); const i = el('input'); i.type = 'checkbox'; i.id = id; i.checked = checked; const t = el('span'); t.append(el('b', '', label), el('small', '', note)); l.append(i, t); return [l, i]; };
    const dlName = ref.replace(/[^A-Za-z0-9_-]+/g, '_') + (fileSuffix ? '_' + fileSuffix : '') + '.png';
    const [l1, c1] = mk('bg-folder', 'Save to the report picture folder', true, 'It becomes the picture of ' + ref + ' in the Lab Tracker (an older picture is kept as the next version).');
    const [l2, c2] = mk('bg-download', 'Download the PNG to this computer', false, dlName);
    opts.append(l1, l2); box.append(opts);
    const msg = el('p', 'bg-msg'); msg.setAttribute('role', 'status'); box.append(msg);
    const bar = el('div', 'bg-bar'); const cancel = el('button', 'dtr-btn dtr-btn-outline', 'Close'); cancel.type = 'button'; const save = el('button', 'dtr-btn dtr-btn-primary', 'Save'); save.type = 'button';
    bar.append(cancel, save); box.append(bar); ov.append(box); document.body.append(ov);
    const close = () => { ov.remove(); document.removeEventListener('keydown', key); };
    const key = (e) => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', key); cancel.addEventListener('click', close); ov.addEventListener('mousedown', (e) => { if (e.target === ov) close(); });
    save.focus();
    save.addEventListener('click', async () => {
      if (!c1.checked && !c2.checked) { msg.textContent = 'Choose where to save it.'; msg.style.color = '#b91c1c'; return; }
      save.disabled = true; msg.style.color = ''; const done = [];
      try {
        if (c1.checked) {
          msg.textContent = 'Saving to the report picture folder...';
          const j = await api('dtr/bonding-image-save', { lab_ref: ref, tab: 'bonding', image: canvas.toDataURL('image/png') });
          done.push(j.message || 'Saved to the report picture folder.');
          document.querySelectorAll('.bs-thumb').forEach((i) => { if (i.dataset.labRef === ref && (i.dataset.tab || 'bonding') === 'bonding') { i.parentNode.classList.remove('none'); i.src = `${BASE}dtr/bonding-image?thumb=1&tab=bonding&lab_ref=${encodeURIComponent(ref)}&t=${Date.now()}`; } });
        }
        if (c2.checked) {
          const a = document.createElement('a'); a.href = canvas.toDataURL('image/png'); a.download = dlName; document.body.append(a); a.click(); a.remove();
          done.push('Downloaded.');
        }
        msg.textContent = done.join(' '); msg.style.color = '#15803d'; toast(done.join(' '), 'success');
        setTimeout(close, 1400);
      } catch (e) { msg.textContent = 'Not saved: ' + e.message; msg.style.color = '#b91c1c'; save.disabled = false; }
    });
  };

  async function generateReport(typeKey, ref) {
    const label = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = 'Drawing the report...';
    try {
      if (typeKey === 'ft_adhesion') {
        const [d, l] = await Promise.all([
          api('dtr/bonding-graph-data?lab_ref=' + encodeURIComponent(ref)),
          api('dtr/bonding-graph-layout').catch(() => ({ layout: null }))
        ]);
        const layout = l.layout && l.layout.items ? l.layout : G.defaultLayout();
        if (!layout.series || !layout.series.length) layout.series = G.defaultLayout().series;
        const canvas = document.createElement('canvas');
        G.render(canvas, layout, d.data);
        openDialog(ref, canvas, 'FT Adhesion Graph', 'ft_adhesion');
      } else {
        const [d, l] = await Promise.all([
          api(`dtr/pull-graph-data?cat=${encodeURIComponent(typeKey)}&lab_ref=${encodeURIComponent(ref)}`),
          api(`dtr/pull-graph-layout?cat=${encodeURIComponent(typeKey)}`).catch(() => ({ layout: null }))
        ]);
        const layout = l.layout && l.layout.items ? l.layout : PG.defaultLayout(typeKey);
        const dd = d.data || {};
        if (!dd.Peak1 && !dd.Graph1 && !Number(dd.TestCount || 0)) { toast(`${ref} has no machine results yet (no peaks / curves loaded): load the pull test results first.`, 'error'); return; }
        const canvas = document.createElement('canvas');
        PG.render(canvas, layout, Object.assign({}, d.data, { cat: typeKey }));
        openDialog(ref, canvas, PG.catMeta(typeKey).label, typeKey);
      }
    } catch (e) {
      toast(e.message, 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = label;
    }
  }

  function openTypePicker(ref) {
    const ov = el('div', 'bg-ov'); ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true'); ov.setAttribute('aria-label', 'Choose report graph type');
    const box = el('div', 'bg-box bg-pick-box');
    box.append(el('h3', '', `Generate Report - ${ref}`));
    box.append(el('p', 'muted sm', isPull() ? 'Pull test Lab Ref: the report is drawn with the values of this pull test and its picture is saved to this Lab Ref. Select the layout:' : 'Select which graph report layout to generate for this Lab Ref:'));
    const list = el('div', 'bg-type-list');
    const close = () => { ov.remove(); document.removeEventListener('keydown', key); };
    const key = (e) => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', key);

    const pull = isPull();
    const sug = pull ? catOf(ref) : '';
    const types = (pull ? REPORT_TYPES.filter((t) => t.key !== 'ft_adhesion') : REPORT_TYPES).slice().sort((a, b) => (b.key === sug) - (a.key === sug));
    types.forEach((rt) => {
      const b = el('button', 'bg-type-btn' + (rt.key === sug ? ' is-suggested' : ''));
      b.type = 'button';
      b.append(el('b', '', rt.label + (rt.key === sug ? '  (matches this Lab Ref)' : '')), el('small', '', rt.desc));
      b.addEventListener('click', () => { close(); generateReport(rt.key, ref); });
      list.append(b);
    });
    box.append(list);
    const bar = el('div', 'bg-bar');
    const cancel = el('button', 'dtr-btn dtr-btn-outline', 'Cancel'); cancel.type = 'button';
    cancel.addEventListener('click', close);
    bar.append(cancel);
    box.append(bar);
    ov.append(box);
    ov.addEventListener('mousedown', (e) => { if (e.target === ov) close(); });
    document.body.append(ov);
    list.querySelector('button')?.focus();
  }

  btn.addEventListener('click', () => {
    const ref = labRef();
    if (!ref) { toast('Open a bonding or pull test report first: the Generate Report button draws the report of the open Lab Ref.', 'error'); return; }
    openTypePicker(ref);
  });
})();
