/**
 * NEXUS-L QC Lab Digital Test Reports (#Digital Test Report & Bonding LIS System)
 * Preserves 100% of Access VBA & VB.NET computations automatically:
 * - UpdateMinMax (Thickness 1..6 -> TMin, TMax, TAve = Round(Sum/6, 2))
 * - ApplyStandardValuesSmart (Queries tbl_dtr_standards by MaterialType & TAve)
 * - UpdateDirectionalAverage (Tensile, Elongation, TrouserTear, StitchTear, MullenBurst, DieC)
 * - Evaluate6PointRating (Wool, Acrylic, Polyester, Nylon, Cotton, Acetate vs CTP/CTW)
 * - EvaluateRatings (Priority: FAIL > TOLERABLE > PASS > UNRATED)
 * - Bonding Spot & Tolerance Rules (TODDLER 14, KIDS 16, CHILDREN 24, ADULT 35 spots)
 * - All 6 Calculators (Weight, Specific Gravity, Compression Set, DIN Abrasion, Ross Flex, Shrinkage)
 * - Auto-Lookups (MaterialCode -> tbl_dtr_material_list, POLotNo -> tbl_dtr_shoe_list, MTL -> tbl_dtr_dsr)
 * - Machine Output Auto-Loader (\\192.168.32.45\OUTDATA\ read-only copy & parse)
 */
(() => {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const baseMeta = $('meta[name="base-url"]');
  const BASE_URL = baseMeta ? baseMeta.content.replace(/\/+$/, '') + '/' : location.origin + '/';

  let activeSlug = 'fabric';
  let activeRecord = {};
  let activeBondingPoints = {};

  const showToast = (msg, isErr = false) => {
    const t = $('#dtr-toast');
    if (!t) return;
    t.textContent = msg;
    t.style.background = isErr ? '#dc2626' : '#0f172a';
    t.classList.remove('hidden');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.add('hidden'), 4200);
  };

  const escHtml = (str) =>
    String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

  const apiFetch = async (path, opts = {}) => {
    const res = await fetch(BASE_URL + path.replace(/^\/+/, ''), opts);
    return res.json();
  };

  const roundTo = (num, dec = 2) => {
    const n = Number(num);
    if (!Number.isFinite(n)) return 0;
    const f = Math.pow(10, dec);
    return Math.round((n + Number.EPSILON) * f) / f;
  };

  const styleRatingInput = (el) => {
    if (!el) return;
    const v = String(el.value || '').trim().toUpperCase();
    el.classList.remove('dtr-rate-pass', 'dtr-rate-warn', 'dtr-rate-fail');
    if (v === 'PASS') el.classList.add('dtr-rate-pass');
    else if (v === 'TOLERABLE') el.classList.add('dtr-rate-warn');
    else if (v === 'FAIL' || v === 'ERROR') el.classList.add('dtr-rate-fail');
  };

  // Leather sheet (Lab VF 006): the printed VF layout over the same record. Each input carries its table column in data-col;
  // typing is mirrored into the matching hidden material-form field so its averages and ratings keep running, then copied back.
  // One printed sheet per material form (#dtr-sheet-<slug>): leather, fabric ...; leatherSheet is the one for the form on screen.
  const allSheets = $$('.lf-sheet');
  let leatherSheet = null;
  const leatherMatForm = $('#dtr-material-form');
  const leatherGeneric = (col) => $(`#mf_${col.replace(/[^a-zA-Z0-9_]/g, '')}`) || $(`[name="${CSS.escape(col)}"]`, leatherMatForm);
  const leatherSheetSync = () => {
    if (!leatherSheet || leatherSheet.hidden) return;
    $$('.lf-out, .lf-std', leatherSheet).forEach((el) => {
      const g = leatherGeneric(el.dataset.col);
      if (g && g !== el) el.value = g.value;
      else if (!g && el.dataset.from) {   // an average the hidden form does not calculate (e.g. Yarn Count): mean of the row's own readings
        const nums = el.dataset.from.split('|').map((c) => parseFloat($(`[data-col="${CSS.escape(c)}"]`, leatherSheet)?.value)).filter((n) => !Number.isNaN(n));
        el.value = nums.length ? (nums.reduce((a, b) => a + b, 0) / nums.length).toFixed(2) : '';
      }
      // a result the hidden form cannot calculate (no matching field / no readings to average) is typed in by the technician
      if (el.classList.contains('lf-out') && !el.dataset.from) el.readOnly = !!g;
      if (el.classList.contains('dtr-rate-inp')) styleRatingInput(el);
    });
  };
  const leatherSheetFill = (record) => {
    if (!leatherSheet) return;
    $$('[data-col]', leatherSheet).forEach((el) => {
      const raw = record ? record[el.dataset.col] : '';
      const v = raw == null ? '' : String(raw);
      if (el.type === 'radio') el.checked = v === el.value;
      else el.value = /^\d{4}-\d{2}-\d{2} 00:00:00$/.test(v) ? v.slice(0, 10) : v;
    });
    leatherSheetSync();
    // The report shows what was SAVED for this Lab Ref: the ratings (PASS / TOLERABLE / FAIL) and the overall result the lab wrote are not replaced by the
    // sheet's own re-calculation when the record opens (it can rate a reading differently from the lab). Editing a reading calculates again as before.
    $$('.lf-out, .lf-std, [data-col="Remarks"]', leatherSheet).forEach((el) => {
      const raw = record ? record[el.dataset.col] : null;
      const v = raw == null ? '' : String(raw).trim();
      if (v !== '' && el.value !== v && (Number.isNaN(parseFloat(v)) || el.dataset.col === 'Remarks')) {
        el.value = v;
        if (el.classList.contains('dtr-rate-inp')) styleRatingInput(el);
      }
    });
  };
  const applyLeatherView = () => {
    leatherSheet = $(`#dtr-sheet-${activeSlug}`) || null;
    allSheets.forEach((s) => { s.hidden = s !== leatherSheet; });
    if (leatherMatForm) leatherMatForm.hidden = !!leatherSheet;
  };
  allSheets.forEach((sheet) => {
    sheet.addEventListener('input', (e) => {
      const el = e.target;
      if (!el.dataset || !el.dataset.col || el.classList.contains('lf-out')) return;
      const g = leatherGeneric(el.dataset.col);
      if (g && el.type !== 'radio') {
        g.value = el.value;
        g.dispatchEvent(new Event('input', { bubbles: true }));
      }
      leatherSheetSync();
    });
    sheet.addEventListener('change', (e) => {
      if (e.target.dataset && e.target.dataset.col === 'Technician') e.target.value = withMachineNo(e.target.value);
    });
  });

  // Technician with the equipment code ("DTP-QC-048 - L. Aquidado"): names, machine nos. and older formats are completed from this list
  const TECHNICIANS = [
    { no: '048', name: 'L. Aquidado', alias: ['lucila'] },
    { no: '002', name: 'S. Aquino', alias: ['sharine'] },
    { no: '001', name: 'L. Tuddao', alias: ['larina'] },
    { no: '064', name: 'J. Barrera', alias: ['jessa'] },
    { no: '066', name: 'J. Parrilla', alias: ['joy'] },
    { no: '067', name: 'R. Cabigas', alias: ['rose'] },
  ];
  const withMachineNo = (value) => {
    const v = String(value ?? '').trim();
    if (!v || /^DTP-QC-\d{3}\s*-\s*\S/.test(v)) return v;                  // empty or already "DTP-QC-NNN - Name"
    const key = v.toLowerCase().replace(/[^a-z0-9]/g, '');
    // equipment code in any spelling (DTP-QC-048, DTP - QC - 48), the old "048 - Name", or just the machine no.
    const code = /dtp[\s-]*qc[\s-]*0*(\d+)/i.exec(v) || /^0*(\d{1,3})\s*-\s*\S/.exec(v) || /^0*(\d{1,3})$/.exec(v);
    const hit = TECHNICIANS.find((t) => (code && Number(code[1]) === Number(t.no))
      || t.name.toLowerCase().replace(/[^a-z0-9]/g, '') === key
      || t.alias.some((a) => key === a || key.startsWith(a)));
    return hit ? `DTP-QC-${hit.no} - ${hit.name}` : v;
  };
  ['#bf_Technician', '#pf_Technician', '#ws-tech', '#mf_TestedBy'].forEach((sel) => {
    const el = document.querySelector(sel);
    if (el) el.addEventListener('change', () => { el.value = withMachineNo(el.value); });
  });

  // =========================================================================
  // 1. TAB SWITCHING
  // =========================================================================
  const switchTab = (tabName) => {
    $$('[data-dtr-for]').forEach((bar) => {
      bar.hidden = bar.dataset.dtrFor !== tabName;
    });
    const navBar = $('#dtr-nav-bar');
    if (navBar) navBar.hidden = tabName === 'labtracker';
    ['tracker', 'labtracker', 'wholeshoe', 'material', 'bonding', 'calculators', 'wholeshoetest'].forEach((t) => {
      $(`#dtr-panel-${t}`)?.classList.toggle('hidden', t !== tabName);
    });
    if (tabName === 'wholeshoetest' && typeof loadWstRecords === 'function') {
      loadWstRecords(currentWstCategory);
    }
  };

  // Lab Tracker sub-tabs (containers / materials / bonding shoes / whole shoes / AppSheet archives)
  const AS_ARCHIVE_TYPES = ['bench', 'erp', 'cfa', 'schedule', 'failed'];
  const asArchiveState = {
    bench: { loaded: false, page: 1, total: 0, limit: 15 },
    erp: { loaded: false, page: 1, total: 0, limit: 15 },
    cfa: { loaded: false, page: 1, total: 0, limit: 15 },
    schedule: { loaded: false, page: 1, total: 0, limit: 15 },
    failed: { loaded: false, page: 1, total: 0, limit: 15 },
  };

  const renderAsBadge = (val) => {
    const v = String(val || '').trim();
    if (!v) return '-';
    const u = v.toUpperCase();
    const cls = (u.includes('FAIL') || u === 'REJECTED') ? 'b-err' : (u.includes('PASS') || u === 'COMPLETED' || u === 'DONE' || u === 'CLOSED' || u.includes('RESOLVED')) ? 'b-ok' : 'b-warn';
    return `<span class="badge ${cls}">${escHtml(v)}</span>`;
  };

  // open / close the tests of a Lab Ref in the Lab Material Test Collection (click the caret, the row or press Enter on the caret)
  document.addEventListener('click', (e) => {
    const row = e.target.closest('#as-tbody-bench tr.pc-row');
    if (!row || e.target.closest('a')) return;
    const det = row.nextElementSibling;
    if (!det || !det.classList.contains('pc-detail')) return;
    const open = det.hidden;
    det.hidden = !open;
    row.classList.toggle('open', open);
    row.querySelector('.pc-toggle')?.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  const renderAsArchiveRows = (type, res) => {
    const rows = res.rows || [];
    const tbody = $(`#as-tbody-${type}`);
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="10" class="muted" style="text-align:center;padding:24px">No matching records found.</td></tr>';
      return;
    }
    if (type === 'bench') {
      // one line per Lab Ref (click it to open the tests done on it), like the Digital Test Report: the tests of a Lab Ref are listed inside
      const groups = [];
      const byRef = new Map();
      rows.forEach((r) => {
        const k = String(r.lab_ref_no || '-');
        if (!byRef.has(k)) { const g = { ref: k, items: [] }; byRef.set(k, g); groups.push(g); }
        byRef.get(k).items.push(r);
      });
      const rank = (v) => { const u = String(v || '').toUpperCase(); return u.includes('FAIL') || u === 'REJECTED' ? 3 : u.includes('TOL') ? 2 : u ? 1 : 0; };
      const uniq = (a) => [...new Set(a.filter(Boolean))];
      tbody.innerHTML = groups.map((g, gi) => {
        const worst = g.items.reduce((w, r) => (rank(r.result) > rank(w) ? r.result : w), g.items[0].result);
        const cats = uniq(g.items.map((r) => r.material_category_name)).join(' / ');
        const dates = g.items.map((r) => String(r.test_date || '')).filter(Boolean).sort();
        const dateTxt = dates.length ? (dates[0] === dates[dates.length - 1] ? dates[0] : `${dates[0]} - ${dates[dates.length - 1]}`) : '-';
        const techs = uniq(g.items.map((r) => r.technician)).join(', ');
        const link = g.ref !== '-' ? `<a href="#" class="dtr-labref-link" data-open-material="${escHtml(g.ref)}" title="Open the report of this Lab Ref (Material Test Report)"><b>${escHtml(g.ref)}</b></a>` : '<b>-</b>';
        const head = `<tr class="pc-row" data-pc="${gi}">
          <td class="mono"><button type="button" class="pc-toggle" aria-expanded="false" aria-label="Show the tests of ${escHtml(g.ref)}"><span class="pc-caret">&#9656;</span><span class="pc-count">${g.items.length}</span></button> ${link}</td>
          <td>${escHtml(cats || '-')}</td>
          <td>${g.items.length} test${g.items.length === 1 ? '' : 's'}</td>
          <td class="mono">-</td>
          <td class="mono">${escHtml(dateTxt)}</td>
          <td class="mono">-</td>
          <td class="mono">-</td>
          <td>${escHtml(techs || '-')}</td>
          <td>${renderAsBadge(worst)}</td>
          <td class="muted">Overall of the ${g.items.length} test${g.items.length === 1 ? '' : 's'}</td>
        </tr>`;
        const sub = g.items.map((r) => {
          const det = [
            r.possible_cause ? `Cause: ${r.possible_cause}` : '',
            r.remarks ? r.remarks : '',
            r.details_summary ? `[${r.detail_count || 0}] ${r.details_summary}` : '',
          ].filter(Boolean).join(' · ');
          const times = [r.started_at_fmt || '', r.finished_at ? `End: ${r.finished_at}` : ''].filter(Boolean).join(' / ');
          return `<tr>
            <td>${escHtml(r.material_category_name || '-')}</td>
            <td>${escHtml(r.material_test_name || '-')}</td>
            <td class="mono">${escHtml(r.test_code || '-')}</td>
            <td class="mono">${escHtml(r.test_date || '-')}</td>
            <td class="mono" style="white-space:nowrap">${escHtml(times || '-')}</td>
            <td class="mono">${escHtml(r.machine_number || '-')}</td>
            <td>${escHtml(r.technician || '-')}</td>
            <td>${renderAsBadge(r.result)}</td>
            <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(det)}">${escHtml(det || '-')}</td>
          </tr>`;
        }).join('');
        return head + `<tr class="pc-detail" data-pc-detail="${gi}" hidden><td colspan="10"><table class="pc-sub"><thead><tr><th>Category</th><th>Material Test</th><th>Code</th><th>Test Date</th><th>Started / Finished</th><th>Machine</th><th>Technician</th><th>Result</th><th>Other Details &amp; Bench Breakdown</th></tr></thead><tbody>${sub}</tbody></table></td></tr>`;
      }).join('');
    } else if (type === 'erp') {
      tbody.innerHTML = rows.map((r) => {
        const trs = [
          r.bonding_tr ? `B:${r.bonding_tr}` : '',
          r.pull_tr ? `P:${r.pull_tr}` : '',
          r.flexing_tr ? `F:${r.flexing_tr}` : '',
          r.aging_tr ? `A:${r.aging_tr}` : '',
          r.hydrolysis_tr ? `H:${r.hydrolysis_tr}` : '',
          r.washing_tr ? `W:${r.washing_tr}` : '',
          r.uv_tr ? `UV:${r.uv_tr}` : '',
        ].filter(Boolean).join(' · ');
        const srDates = [r.send_date ? `S:${r.send_date}` : '', r.receipt_date ? `R:${r.receipt_date}` : ''].filter(Boolean).join(' / ');
        const stCol = [r.style_no, r.colorway].filter(Boolean).join(' / ');
        const lnSide = [r.line_no, r.side].filter(Boolean).join(' · ');
        return `<tr>
          <td class="mono"><b>${escHtml(r.lab_ref_no || '-')}</b></td>
          <td class="mono">${escHtml(r.vsp_code || '-')}</td>
          <td class="mono">${escHtml(r.mtl || '-')}</td>
          <td>${escHtml(stCol || '-')}</td>
          <td>${escHtml(lnSide || '-')}</td>
          <td class="mono">${escHtml(r.test_date || '-')}</td>
          <td class="mono" style="white-space:nowrap">${escHtml(srDates || '-')}</td>
          <td>${renderAsBadge(r.test_result)}</td>
          <td class="mono" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(trs || r.total_tests || '')}">${escHtml(trs || r.total_tests || '-')}</td>
          <td>${escHtml(r.creator || '-')}</td>
        </tr>`;
      }).join('');
    } else if (type === 'cfa') {
      const capWrap = $('#as-cap-wrap');
      if (capWrap && Array.isArray(res.capRows) && res.capRows.length) {
        capWrap.style.display = '';
        capWrap.innerHTML = `<div style="padding:10px 12px;border:1px solid rgba(148,163,184,0.2);border-radius:10px;background:rgba(15,23,42,0.03)">
          <b class="sm">Bonding CAP Submissions (${res.capRows.length})</b>
          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:6px">${res.capRows.map((c) => `<span class="badge b-warn">${escHtml(c.production_date || '-')} · Line ${escHtml(c.line_no || '-')} · ${escHtml(c.submitted_by || '-')} (Lab: ${escHtml(c.date_uploaded_by_lab || '-')})</span>`).join('')}</div>
        </div>`;
      } else if (capWrap && res.page === 1) {
        capWrap.style.display = 'none';
      }
      tbody.innerHTML = rows.map((r) => {
        const trs = [r.tr_bonding ? `B:${r.tr_bonding}` : '', r.tr_flexing ? `F:${r.tr_flexing}` : '', r.tr_mtr ? `M:${r.tr_mtr}` : ''].filter(Boolean).join(' · ');
        const ackStr = `${r.ack_count || 0} ack${r.ack_pics ? ' (' + r.ack_pics + ')' : ''}${Number(r.req_count || 0) > 0 ? ` · ${r.req_count} req (${r.latest_req_status || 'Open'})` : ''}`;
        return `<tr>
          <td class="mono"><b>${escHtml(r.lab_ref_no || r.record_id || '-')}</b></td>
          <td class="mono">${escHtml(r.production_code || '-')}</td>
          <td class="mono">${escHtml(r.production_date || '-')}</td>
          <td>${escHtml([r.style, r.colorway].filter(Boolean).join(' / ') || '-')}</td>
          <td class="mono">${escHtml(r.mtl || '-')}</td>
          <td>${escHtml(r.test_name || '-')}</td>
          <td class="mono">${escHtml(trs || '-')}</td>
          <td>${escHtml(ackStr)}</td>
          <td>${renderAsBadge(r.document_status || r.sent_cfa)}</td>
          <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(r.remarks || '')}">${escHtml(r.remarks || '-')}</td>
        </tr>`;
      }).join('');
    } else if (type === 'schedule') {
      tbody.innerHTML = rows.map((r) => {
        const det = [
          r.failure_part ? `Fail: ${r.failure_part}` : '',
          r.possible_cause ? `Cause: ${r.possible_cause}` : '',
          r.retest_no ? `Retest #${r.retest_no}` : '',
          r.tr_no ? `TR: ${r.tr_no}` : '',
          r.remarks || '',
        ].filter(Boolean).join(' · ');
        return `<tr>
          <td class="mono"><b>${escHtml(r.lab_ref_no || '-')}</b></td>
          <td class="mono">${escHtml(r.production_date || '-')}</td>
          <td>${escHtml([r.building, r.line_no || r.line_code].filter(Boolean).join(' / ') || '-')}</td>
          <td class="mono">${escHtml([r.production_code, r.mtl].filter(Boolean).join(' / ') || '-')}</td>
          <td>${escHtml([r.style, r.colorway].filter(Boolean).join(' / ') || '-')}</td>
          <td>${escHtml([r.test_name, r.test_type].filter(Boolean).join(' · ') || '-')}</td>
          <td class="mono">${escHtml(r.machine_tested || '-')}</td>
          <td>${renderAsBadge(r.testing_status)}</td>
          <td>${renderAsBadge(r.test_result)}</td>
          <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(det)}">${escHtml(det || '-')}</td>
        </tr>`;
      }).join('');
    } else if (type === 'failed') {
      tbody.innerHTML = rows.map((r) => `<tr>
        <td class="mono">${escHtml(r.sample_id || '-')}</td>
        <td class="mono"><b>${escHtml(r.mtl || '-')}</b></td>
        <td>${escHtml(r.style || '-')}</td>
        <td>${escHtml(r.colorway || '-')}</td>
        <td>${r.test_failed ? renderAsBadge(r.test_failed) : '-'}</td>
        <td class="mono">${escHtml(r.created_date || '-')}</td>
      </tr>`).join('');
    }
  };

  const loadAppsheetArchive = async (type, page = 1) => {
    if (!AS_ARCHIVE_TYPES.includes(type)) return;
    const st = asArchiveState[type];
    const tbody = $(`#as-tbody-${type}`);
    const q = $(`.dtr-as-search[data-as-type="${type}"]`)?.value.trim() || '';
    const ls = window.dtrListState || {};
    const rgQ = (type === 'bench' && ls.rg) ? `&from=${encodeURIComponent(ls.rg.from)}&to=${encodeURIComponent(ls.rg.to)}` : '';
    const resQ = (type === 'bench' && ls.res) ? `&res=${encodeURIComponent(ls.res)}` : '';
    try {
      if (tbody && window.nxState) nxState(tbody, 'loading', 'Loading records…', 10);
      const res = await apiFetch(`dtr/appsheet-archive?type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}&page=${page}&limit=${st.limit}${rgQ}${resQ}`);
      if (!res || !res.ok) throw new Error('Archive fetch failed');
      st.loaded = true;
      st.page = res.page || page;
      st.total = res.total || 0;
      const totalPages = Math.max(1, Math.ceil(st.total / st.limit));
      const cntEl = $(`#as-count-${type}`);
      const pgEl = $(`#as-page-${type}`);
      if (cntEl) cntEl.textContent = `${st.total.toLocaleString()} record(s)`;
      if (pgEl) pgEl.textContent = `${st.page} / ${totalPages}`;
      renderAsArchiveRows(type, res);
    } catch (err) {
      console.error(err);
      if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="muted" style="text-align:center;padding:24px">Could not load records.</td></tr>';
    }
  };

  $$('.dtr-subtab[data-subtab]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const sub = btn.dataset.subtab;
      $$('.dtr-subtab').forEach((b) => b.classList.toggle('active', b === btn));
      $$('.dtr-subpanel').forEach((p) => p.classList.toggle('hidden', p.dataset.subpanel !== sub));
      // the Lab Material Test Collection tab ("containers") shows the Material Bench Log
      const arch = sub === 'containers' ? 'bench' : sub;
      if (AS_ARCHIVE_TYPES.includes(arch) && !asArchiveState[arch].loaded) {
        loadAppsheetArchive(arch, 1);
      }
    });
  });

  (function () {
    const btn = $('#dtr-date-btn');
    const pop = $('#dtr-date-pop');
    if (!btn || !pop) return;
    const kp = { total: $('#dtr-kpi-total'), fin: $('#dtr-kpi-fin'), ongoing: $('#dtr-kpi-ongoing'), pass: $('#dtr-kpi-pass'), tol: $('#dtr-kpi-tol'), fail: $('#dtr-kpi-fail'), shut: $('#dtr-kpi-shut'), sub: $('#dtr-kpi-shut-sub') };
    const orig = { total: kp.total?.textContent, fin: kp.fin?.textContent, ongoing: kp.ongoing?.textContent, pass: kp.pass?.textContent, tol: kp.tol?.textContent, fail: kp.fail?.textContent, shut: kp.shut?.textContent, sub: kp.sub?.innerHTML };
    const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const fmt = (n) => Number(n).toLocaleString('en-US');
    const fmtShort = (s) => {
      if (!s) return '';
      const d = new Date(`${s}T00:00:00`);
      return isNaN(d) ? s : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };
    const smartRangeLabel = (a, b) => {
      const t = new Date(); t.setHours(0, 0, 0, 0);
      const todayS = ymd(t);
      const yd = new Date(t); yd.setDate(yd.getDate() - 1);
      const yestS = ymd(yd);
      if (a === b) {
        if (a === todayS) return `Today · ${t.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
        if (a === yestS) return `Yesterday · ${yd.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
        return fmtShort(a);
      }
      if (/^\d{4}-01-01$/.test(a) && b === `${a.slice(0, 4)}-12-31`) {
        return a.slice(0, 4) === String(t.getFullYear()) ? `This Year · ${a.slice(0, 4)}` : `Year ${a.slice(0, 4)}`;
      }
      if (/^\d{4}-\d{2}-01$/.test(a)) {
        const [yy, mm] = a.split('-').map(Number);
        const lastOfMo = ymd(new Date(yy, mm, 0));
        if (b === lastOfMo) return new Date(yy, mm - 1, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' });
      }
      const d1 = new Date(`${a}T00:00:00`), d2 = new Date(`${b}T00:00:00`);
      if (!isNaN(d1) && !isNaN(d2) && Math.round((d2 - d1) / 86400000) === 6 && d1.getDay() === 1) {
        const sLbl = d1.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        const eLbl = d2.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        return `Week · ${sLbl} – ${eLbl}`;
      }
      return `${fmtShort(a)} – ${fmtShort(b)}`;
    };
    const rangeOf = (key, day, customLabel) => {
      const t = new Date(); t.setHours(0, 0, 0, 0);
      const add = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
      if (key === 'today') return { from: ymd(t), to: ymd(t), label: `Today · ${t.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`, key: 'today' };
      if (key === 'yesterday') { const yd = add(t, -1); return { from: ymd(yd), to: ymd(yd), label: `Yesterday · ${yd.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`, key: 'yesterday' }; }
      if (key === 'week') { const mon = add(t, -((t.getDay() + 6) % 7)); return { from: ymd(mon), to: ymd(add(mon, 6)), label: 'This Week', key: 'week' }; }
      if (key === 'month') return { from: ymd(new Date(t.getFullYear(), t.getMonth(), 1)), to: ymd(new Date(t.getFullYear(), t.getMonth() + 1, 0)), label: 'This Month', key: 'month' };
      if (key === 'year') return { from: `${t.getFullYear()}-01-01`, to: `${t.getFullYear()}-12-31`, label: `This Year · ${t.getFullYear()}`, key: 'year' };
      if (key === 'day' && day) return { from: day, to: day, label: fmtShort(day), key: 'day' };
      if (key === 'range' && /^\d{4}-\d{2}-\d{2}\|\d{4}-\d{2}-\d{2}$/.test(day || '')) {
        const [a, b] = day.split('|');
        const lbl = customLabel || smartRangeLabel(a, b);
        return { from: a, to: b, label: lbl, key: 'range' };
      }
      if (key === 'weekpick' && /^(\d{4})-W(\d{2})$/.test(day || '')) {   // ISO week: Monday .. Sunday
        const [, y, w] = day.match(/^(\d{4})-W(\d{2})$/);
        const jan4 = new Date(Number(y), 0, 4);
        const mon = add(jan4, -((jan4.getDay() + 6) % 7) + (Number(w) - 1) * 7);
        return { from: ymd(mon), to: ymd(add(mon, 6)), label: `Week ${Number(w)} · ${ymd(mon).slice(5)} to ${ymd(add(mon, 6)).slice(5)}`, key: 'weekpick' };
      }
      if (key === 'monthpick' && /^\d{4}-\d{2}$/.test(day || '')) {
        const [y, m] = day.split('-').map(Number);
        return { from: ymd(new Date(y, m - 1, 1)), to: ymd(new Date(y, m, 0)), label: new Date(y, m - 1, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' }), key: 'monthpick' };
      }
      return null;
    };
    const clearBtn = $('#dtr-date-clear');
    const apply = async (key, day, customLabel) => {
      const rg = rangeOf(key, day, customLabel);
      $('#dtr-date-label').textContent = rg ? rg.label : 'All dates';
      btn.classList.toggle('on', !!rg);
      if (clearBtn) clearBtn.hidden = !rg;
      if (key !== 'range') {
        const w = $('#dtr-week-sel'); const m2 = $('#dtr-month-sel'); const y2 = $('#dtr-year-sel');
        if (w) w.value = ''; if (m2) m2.value = ''; if (y2) y2.value = '';
      }
      pop.querySelectorAll('.dp-chip[data-range]').forEach((b) => b.classList.toggle('on', b.dataset.range === key));
      if (rg) { dp.from = rg.from; dp.to = rg.to; dp.view = new Date(`${rg.from}T00:00:00`); dpRender(); }
      else { dp.from = ''; dp.to = ''; dpRender(); }
      pop.hidden = true; btn.setAttribute('aria-expanded', 'false');
      kpiState.rg = rg;
      refreshKpis();
      window.dtrReloadList?.();   // the open list loads every report of that range (pending ones too)
    };
    // the cards follow the open tab (Bonding / Whole Shoe / Lab Material Test Collection) and the date range chosen
    const defaultToday = $('#dtr-datefilter')?.dataset.defaultToday === '1';
    // the default range is Today (the list adds the Daily shoes of the day before and the other test types of yesterday)
    const kpiState = window.dtrListState = { rg: defaultToday ? rangeOf('today') : null, scope: '', res: '' };
    // clicking a card lists only the reports of that result (Total = all)
    $$('.dtr-top-kpis .dtr-kpi:not([data-kno])').forEach((card, i) => {
      const res = ['', 'pass', 'tol', 'fail'][i];
      if (res === undefined) return;
      card.dataset.kres = res || 'all';
      card.setAttribute('title', res ? 'Click to list only these reports (click again to clear)' : 'Click to list all reports');
      card.addEventListener('click', () => {
        kpiState.res = (res && kpiState.res !== res) ? res : '';
        $$('.dtr-top-kpis .dtr-kpi').forEach((c) => c.classList.toggle('kpi-on', !!kpiState.res && c.dataset.kres === kpiState.res));
        window.dtrReloadList?.();
      });
    });
    const scopeOf = (sub) => ({ bonding: 'bonding', shoes: 'shoes', containers: 'materials' }[sub] || '');
    const setText = (el, v) => { if (el) el.textContent = v; };
    // Bonding tab: "Total Lab Test Reports" = the total of the summary buttons Daily + Tech Trial + Sample + Retest + First Article + Advance (Lacking and Shutdown lines are not reports)
    window.dtrSyncTotal = () => {
      if (kpiState.scope !== 'bonding' || !kp.total) return;
      const chips = $$('#lt-counts-bonding [data-lt-filter]');
      if (!chips.length) return;
      let sum = 0;
      chips.forEach((c) => {
        if (['daily', 'trial', 'sample', 'retest', 'first', 'advance'].includes(c.dataset.ltFilter)) sum += Number((c.querySelector('b')?.textContent || '0').replace(/[^\d]/g, '')) || 0;
      });
      kp.total.textContent = fmt(sum);
      // Passed / Tolerable / Failed = the Result column of the listed rows (a row with a failed pull test is FAILED, the same for TOLERABLE)
      const rc = $('#lt-res-counts');
      if (rc) {
        setText(kp.pass, fmt(Number(rc.dataset.pass || 0)));
        setText(kp.tol, fmt(Number(rc.dataset.tol || 0)));
        setText(kp.fail, fmt(Number(rc.dataset.fail || 0)));
      }
    };
    const syncFinished = () => {
      const numOf = (el) => Number((el?.textContent || '0').replace(/[^\d]/g, '')) || 0;
      const pctOf = (n, d) => d > 0 ? ((n / d) * 100).toFixed(1).replace(/\.0$/, '') + '%' : '0%';
      const pass = numOf(kp.pass);
      const tol  = numOf(kp.tol);
      const fail = numOf(kp.fail);
      const fin  = pass + tol + fail;
      const tot  = numOf(kp.total);
      const ong  = Math.max(0, tot - fin);
      if (kp.fin) kp.fin.textContent = fmt(fin);
      if (kp.ongoing) kp.ongoing.textContent = fmt(ong);
      setText($('#dtr-kpi-total-pct'), `${pctOf(fin, tot)} Done`);
      setText($('#dtr-kpi-fin-pct'), pctOf(fin, tot));
      setText($('#dtr-kpi-ongoing-pct'), pctOf(ong, tot));
      setText($('#dtr-kpi-pass-pct'), pctOf(pass, tot));
      setText($('#dtr-kpi-tol-pct'), pctOf(tol, tot));
      setText($('#dtr-kpi-fail-pct'), pctOf(fail, tot));
    };   // Finished Shoes Tested = Passed + Tolerable + Failed; On Going Testing = Total - Finished
    const syncTotalBase = window.dtrSyncTotal;
    window.dtrSyncTotal = () => { syncTotalBase(); syncFinished(); };
    const refreshKpis = async () => {
      const { rg, scope } = kpiState;
      if (!scope && !rg) {
        setText(kp.total, orig.total); setText(kp.fin, orig.fin); setText(kp.ongoing, orig.ongoing); setText(kp.pass, orig.pass); setText(kp.tol, orig.tol); setText(kp.fail, orig.fail); setText(kp.shut, orig.shut);
        syncFinished();
        if (kp.sub) kp.sub.innerHTML = orig.sub;
        return;
      }
      try {
        const r = await apiFetch(`dtr/kpis?scope=${scope}${rg ? `&from=${rg.from}&to=${rg.to}` : ''}`);
        if (!r || !r.ok) return;
        const names = { bonding: 'Bonding Shoe Collection', shoes: 'Whole Shoe Collection', materials: 'Lab Material Test Collection' };
        setText(kp.total, fmt(r.total)); setText(kp.pass, fmt(r.pass)); setText(kp.tol, fmt(r.tol)); setText(kp.fail, fmt(r.fail)); syncFinished();
        setText(kp.shut, r.shut === null || r.shut === undefined ? '-' : fmt(r.shut));
        const tag = [names[scope] || '', rg ? rg.label : 'all dates'].filter(Boolean).join(' · ');
        const tt = document.querySelector('#dtr-kpi-total')?.parentElement?.querySelector('.dtr-kpi-sub');
        if (tt) tt.textContent = tag;
        if (kp.sub) kp.sub.textContent = r.shut === null || r.shut === undefined ? 'lines marked SHUTDOWN: Bonding tab only' : `lines marked SHUTDOWN · ${rg ? rg.label : 'all dates'}`;
        window.dtrSyncTotal?.();
      } catch (e) { /* keep the numbers shown */ }
    };
    document.addEventListener('click', (e) => {
      const b = e.target.closest('.dtr-subtab[data-subtab]');
      if (!b) return;
      kpiState.scope = scopeOf(b.dataset.subtab);
      refreshKpis();
      if (kpiState.rg || kpiState.res) window.dtrReloadList?.();
    });
    kpiState.scope = scopeOf($('.dtr-subtab.active')?.dataset.subtab || $('.dtr-subpanel:not(.hidden)')?.dataset.subpanel || '');
    window.dtrSyncTotal?.();
    if (kpiState.scope) refreshKpis();
    btn.addEventListener('click', (e) => {
      if (e.target.closest('#dtr-date-clear')) { e.stopPropagation(); apply(''); return; }
      e.stopPropagation(); pop.hidden = !pop.hidden; btn.setAttribute('aria-expanded', String(!pop.hidden));
    });
    pop.addEventListener('click', (e) => {
      const b = e.target.closest('[data-range]');
      if (!b) return;
      e.preventDefault();
      apply(b.dataset.range === 'all' ? '' : b.dataset.range);
    });
    // (composedPath: the calendar re-draws itself on a click, so the clicked day may already be detached from the page)
    document.addEventListener('click', (e) => { if (!pop.hidden && !e.composedPath().some((n) => n.classList?.contains('dtr-datefilter'))) { pop.hidden = true; btn.setAttribute('aria-expanded', 'false'); } });

    // Weekly / Monthly / Yearly dropdown lists
    const nice = (d) => d.toLocaleString('en-US', { month: 'short', day: 'numeric' });
    const wk = $('#dtr-week-sel');
    const mo = $('#dtr-month-sel');
    const yr = $('#dtr-year-sel');
    if (wk) {
      const t = new Date(); t.setHours(0, 0, 0, 0);
      const mon0 = new Date(t); mon0.setDate(t.getDate() - ((t.getDay() + 6) % 7));
      wk.innerHTML = '<option value="">Select Week…</option>' + Array.from({ length: 20 }, (_, i) => {
        const m = new Date(mon0); m.setDate(mon0.getDate() - i * 7);
        const s = new Date(m); s.setDate(m.getDate() + 6);
        return `<option value="${ymd(m)}|${ymd(s)}">${nice(m)} – ${nice(s)}, ${s.getFullYear()}${i === 0 ? ' (this week)' : ''}</option>`;
      }).join('');
      wk.addEventListener('change', () => {
        if (wk.value) {
          if (mo) mo.value = ''; if (yr) yr.value = '';
          const optTxt = wk.options[wk.selectedIndex]?.textContent?.replace(/\s*\(this week\)$/, '') || '';
          apply('range', wk.value, optTxt);
        }
      });
    }
    if (mo) {
      const t = new Date();
      mo.innerHTML = '<option value="">Select Month…</option>' + Array.from({ length: 12 }, (_, i) => {
        const m = new Date(t.getFullYear(), t.getMonth() - i, 1);
        const e2 = new Date(m.getFullYear(), m.getMonth() + 1, 0);
        return `<option value="${ymd(m)}|${ymd(e2)}">${m.toLocaleString('en-US', { month: 'long', year: 'numeric' })}${i === 0 ? ' (this month)' : ''}</option>`;
      }).join('');
      mo.addEventListener('change', () => {
        if (mo.value) {
          if (wk) wk.value = ''; if (yr) yr.value = '';
          const optTxt = mo.options[mo.selectedIndex]?.textContent?.replace(/\s*\(this month\)$/, '') || '';
          apply('range', mo.value, optTxt);
        }
      });
    }
    if (yr) {
      const curY = new Date().getFullYear();
      const minY = Math.min(2020, curY - 6);
      yr.innerHTML = '<option value="">Select Year…</option>' + Array.from({ length: curY - minY + 1 }, (_, i) => {
        const yVal = curY - i;
        return `<option value="${yVal}-01-01|${yVal}-12-31">${yVal}${i === 0 ? ' (this year)' : ''}</option>`;
      }).join('');
      yr.addEventListener('change', () => {
        if (yr.value) {
          if (wk) wk.value = ''; if (mo) mo.value = '';
          const yNum = yr.value.slice(0, 4);
          apply('range', yr.value, yNum === String(curY) ? `This Year · ${yNum}` : `Year ${yNum}`);
        }
      });
    }

    // Calendar Picker supporting 4 modes: Daily / Weekly / Monthly / Year
    const dp = { mode: 'daily', from: kpiState.rg?.from || '', to: kpiState.rg?.to || '', view: new Date() };
    dp.view.setDate(1);
    const gridEl = $('#dtr-dp-grid');
    const hintEl = $('#dtr-dp-hint');
    const headTitleEl = $('#dtr-dp-head-title');
    const MODE_TITLES = {
      daily: 'Daily / Custom Date Range',
      weekly: 'Weekly Schedule (Mon–Sun)',
      monthly: 'Monthly Schedule',
      year: 'Yearly Schedule',
    };
    const MODE_HINTS = {
      daily: 'Click a day, or click start → end date',
      weekly: 'Click any day to select its Monday–Sunday week',
      monthly: 'Click a month to filter that full month',
      year: 'Click a year to filter that full year',
    };
    function dpRender() {
      if (!gridEl) return;
      const mode = dp.mode;
      const y = dp.view.getFullYear(); const m = dp.view.getMonth();
      const now = new Date();
      const curY = now.getFullYear();
      const curM = now.getMonth();
      const todayS = ymd(now);
      pop.querySelectorAll('.dp-mode-btn').forEach((b) => {
        const on = b.dataset.dpMode === mode;
        b.classList.toggle('on', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      if (headTitleEl) headTitleEl.textContent = MODE_TITLES[mode] || MODE_TITLES.daily;
      $('#dtr-dp-from').value = dp.from; $('#dtr-dp-to').value = dp.to;
      if (hintEl) {
        if (!dp.from) hintEl.textContent = MODE_HINTS[mode] || '';
        else if (mode === 'daily' && !dp.to) hintEl.textContent = 'Click an end date, or Apply Filter for 1 day';
        else hintEl.textContent = smartRangeLabel(dp.from, dp.to || dp.from);
      }
      gridEl.classList.toggle('dp-grid-period', mode === 'monthly' || mode === 'year');

      if (mode === 'monthly') {
        $('#dtr-dp-title').textContent = String(y);
        const moNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        gridEl.innerHTML = moNames.map((mn, mi) => {
          const sFrom = ymd(new Date(y, mi, 1));
          const sTo = ymd(new Date(y, mi + 1, 0));
          const sel = dp.from === sFrom && dp.to === sTo;
          const isCur = y === curY && mi === curM;
          return `<button type="button" class="dp-period-btn${sel ? ' edge' : ''}${isCur ? ' today' : ''}" data-from="${sFrom}" data-to="${sTo}">${mn} ${y}</button>`;
        }).join('');
        return;
      }

      if (mode === 'year') {
        const startY = Math.floor(y / 12) * 12;
        $('#dtr-dp-title').textContent = `${startY} – ${startY + 11}`;
        gridEl.innerHTML = Array.from({ length: 12 }, (_, i) => {
          const yy = startY + i;
          const sFrom = `${yy}-01-01`;
          const sTo = `${yy}-12-31`;
          const sel = dp.from === sFrom && dp.to === sTo;
          const isCur = yy === curY;
          return `<button type="button" class="dp-period-btn${sel ? ' edge' : ''}${isCur ? ' today' : ''}" data-from="${sFrom}" data-to="${sTo}">${yy}</button>`;
        }).join('');
        return;
      }

      $('#dtr-dp-title').textContent = dp.view.toLocaleString('en-US', { month: 'long', year: 'numeric' });
      const first = new Date(y, m, 1);
      const start = new Date(y, m, 1 - first.getDay());
      let h = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map((d) => `<span class="dp-dow">${d}</span>`).join('');
      for (let i = 0; i < 42; i++) {
        const d = new Date(start); d.setDate(start.getDate() + i);
        const s = ymd(d);
        const inM = d.getMonth() === m;
        const hi = dp.from && (dp.to ? (s >= dp.from && s <= dp.to) : s === dp.from);
        const edge = s === dp.from || s === dp.to;
        const showHi = mode === 'weekly' ? hi : (hi && inM);
        const showEdge = mode === 'weekly' ? edge : (edge && inM);
        h += `<button type="button" class="dp-day${inM ? '' : ' out'}${showHi ? ' in' : ''}${showEdge ? ' edge' : ''}${s === todayS ? ' today' : ''}" data-d="${s}">${d.getDate()}</button>`;
      }
      gridEl.innerHTML = h;
    }
    pop.querySelectorAll('.dp-mode-btn').forEach((mBtn) => {
      mBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dp.mode = mBtn.dataset.dpMode || 'daily';
        dpRender();
      });
    });
    gridEl?.addEventListener('click', (e) => {
      const pBtn = e.target.closest('.dp-period-btn');
      if (pBtn) {
        dp.from = pBtn.dataset.from;
        dp.to = pBtn.dataset.to;
        dpRender();
        return;
      }
      const b = e.target.closest('.dp-day');
      if (!b || (dp.mode === 'daily' && b.classList.contains('out'))) return;
      const s = b.dataset.d;
      if (dp.mode === 'weekly') {
        const dt = new Date(`${s}T00:00:00`);
        const mon = new Date(dt); mon.setDate(dt.getDate() - ((dt.getDay() + 6) % 7));
        const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
        dp.from = ymd(mon);
        dp.to = ymd(sun);
        dpRender();
        return;
      }
      if (!dp.from || dp.to) { dp.from = s; dp.to = ''; }
      else if (s < dp.from) { dp.to = dp.from; dp.from = s; }
      else dp.to = s;
      dpRender();
    });
    gridEl?.addEventListener('dblclick', (e) => {
      const pBtn = e.target.closest('.dp-period-btn');
      if (pBtn) {
        if (wk) wk.value = ''; if (mo) mo.value = ''; if (yr) yr.value = '';
        apply('range', `${pBtn.dataset.from}|${pBtn.dataset.to}`);
        return;
      }
      const b = e.target.closest('.dp-day');
      if (!b || (dp.mode === 'daily' && b.classList.contains('out'))) return;
      if (dp.from) {
        if (wk) wk.value = ''; if (mo) mo.value = ''; if (yr) yr.value = '';
        apply('range', `${dp.from}|${dp.to || dp.from}`);
      }
    });
    $('#dtr-dp-prev')?.addEventListener('click', () => {
      if (dp.mode === 'monthly') dp.view.setFullYear(dp.view.getFullYear() - 1);
      else if (dp.mode === 'year') dp.view.setFullYear(dp.view.getFullYear() - 12);
      else dp.view.setMonth(dp.view.getMonth() - 1);
      dpRender();
    });
    $('#dtr-dp-next')?.addEventListener('click', () => {
      if (dp.mode === 'monthly') dp.view.setFullYear(dp.view.getFullYear() + 1);
      else if (dp.mode === 'year') dp.view.setFullYear(dp.view.getFullYear() + 12);
      else dp.view.setMonth(dp.view.getMonth() + 1);
      dpRender();
    });
    $('#dtr-dp-clear')?.addEventListener('click', () => { dp.from = ''; dp.to = ''; dpRender(); });
    $('#dtr-dp-apply')?.addEventListener('click', () => {
      if (!dp.from) return;
      if (wk) wk.value = ''; if (mo) mo.value = ''; if (yr) yr.value = '';
      apply('range', `${dp.from}|${dp.to || dp.from}`);
    });
    dpRender();
  })();

  $$('.dtr-as-search-btn').forEach((b) => b.addEventListener('click', () => loadAppsheetArchive(b.dataset.asType, 1)));
  $$('.dtr-as-search').forEach((inp) => inp.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); loadAppsheetArchive(inp.dataset.asType, 1); }
  }));
  // no Search button: the list searches by itself shortly after typing stops
  $$('.dtr-as-search').forEach((inp) => {
    let tmr = null;
    inp.addEventListener('input', () => { clearTimeout(tmr); tmr = setTimeout(() => loadAppsheetArchive(inp.dataset.asType, 1), 350); });
  });
  $$('.dtr-as-prev').forEach((b) => b.addEventListener('click', () => {
    const t = b.dataset.asType;
    if (asArchiveState[t].page > 1) loadAppsheetArchive(t, asArchiveState[t].page - 1);
  }));
  $$('.dtr-as-next').forEach((b) => b.addEventListener('click', () => {
    const t = b.dataset.asType;
    const totalPages = Math.max(1, Math.ceil(asArchiveState[t].total / asArchiveState[t].limit));
    if (asArchiveState[t].page < totalPages) loadAppsheetArchive(t, asArchiveState[t].page + 1);
  }));
  const initActiveSubtab = $('.dtr-subtab.active')?.dataset.subtab || $('.dtr-subpanel:not(.hidden)')?.dataset.subpanel;
  const initArch = initActiveSubtab === 'containers' ? 'bench' : initActiveSubtab;
  if (initArch && AS_ARCHIVE_TYPES.includes(initArch)) {
    loadAppsheetArchive(initArch, 1);
  }

  // =========================================================================
  // Specimen Intake (+ Shoe Collected): Building A (12 lines) / Building B (14 lines)
  // =========================================================================
  const intake = { building: 'A' };
  const intakeEl = { back: $('#dtr-intake-backdrop'), tbody: $('#dtr-intake-tbody'), msg: $('#dtr-intake-msg') };

  // A row for one line. The "+" beside the line number adds another row for that same line (extra = true, with a remove button).
  // o.type = preset Test Type; o.pick = the line is chosen from a list (First Case); o.noLine = no line and no Active toggle (Tech Trial)
  // Test Types: TRIAL and INTERNAL TESTING have no line; SAMPLE, FIRST ARTICLE and RETEST load one row with a chosen line; DAILY loads every line
  const IK_TYPES = ['DAILY', 'TECH TRIAL', 'SAMPLE', 'INTERNAL TESTING', 'FIRST ARTICLE', 'RETEST', 'LACKING'];
  const ikNoLine = (t) => t === 'TECH TRIAL' || t === 'INTERNAL TESTING' || t === 'SAMPLE';
  const ikPick = (t) => t === 'FIRST ARTICLE' || t === 'RETEST';
  const intakeRowHtml = (line, extra, rightSide, o = {}) => {
    const type = o.type || 'DAILY';
    const b = String(line).charAt(0);
    const lineCount = b === 'B' ? 14 : 12;
    const lineOpts = Array.from({ length: lineCount }, (_, i) => `<option value="${b}${i + 1}"${`${b}${i + 1}` === line ? ' selected' : ''}>${b}${i + 1}</option>`).join('');
    // SAMPLE has no line: its column holds the Sample Test selection (Bonding / Strip / Osna / Rivet)
    const lineCell = o.noLine
      ? '<td class="ik-linecell"><span class="muted sm">-</span></td>'
      : `<td class="ik-linecell">${o.pick ? `<select class="ik-linesel" aria-label="Line">${lineOpts}</select>` : `<b>${line}</b>`} <button type="button" class="ik-addrow" title="Add another row for ${line}" aria-label="Add another row for ${line}">+</button>${extra ? '<button type="button" class="ik-delrow" title="Remove this row" aria-label="Remove this row">&times;</button>' : ''}</td>`;
    const typeOpts = IK_TYPES.map((t) => `<option${t === type ? ' selected' : ''}>${t}</option>`).join('');
    return `
        <tr data-line="${line}"${extra ? ' data-extra="1"' : ''}>
          <td>${o.noLine ? '' : '<label class="dtr-switch"><input type="checkbox" class="ik-active" checked><span></span></label>'}</td>
          ${lineCell}
          <td><div class="dtr-side-toggle"><button type="button" data-side="L" class="${rightSide ? '' : 'active'}">L</button><button type="button" data-side="R" class="${rightSide ? 'active' : ''}">R</button></div></td>
          <td class="ik-labref mono"><span class="ik-lr"></span>${o.noLine ? `<button type="button" class="ik-addrow" title="Add another tech trial row" aria-label="Add another tech trial row">+</button>${extra ? '<button type="button" class="ik-delrow" title="Remove this row" aria-label="Remove this row">&times;</button>' : ''}` : ''}</td>
          <td><select class="ik-type">${typeOpts}</select></td>
          <td><input type="text" class="ik-code" placeholder="Type to filter" autocomplete="off">${type === 'SAMPLE' ? `<select class="ik-sub" aria-label="Sample test">${[['SH', 'Bonding'], ['STRIP', 'Strip Test'], ['RIVET', 'Rivets'], ['OSNA', 'Osnaburg']].map(([v, l]) => `<option value="${v}">${l}</option>`).join('')}</select>` : ''}</td>
          <td>${type === 'TECH TRIAL' ? '<input type="text" class="ik-mtl" placeholder="Type MTL #" autocomplete="off">' : '<input type="text" class="ik-mtl" placeholder="MTL #" readonly tabindex="-1">'}</td>
          <td><input type="text" class="ik-color" placeholder="Colorway" readonly tabindex="-1"></td>
          <td><input type="text" class="ik-style" placeholder="Style" readonly tabindex="-1"></td>
          <td class="ik-imgcell"><img class="ik-img" alt="" hidden></td>
          <td class="ik-pulls"><label><input type="checkbox" class="ik-pt" value="HEEL PULL"> Heel Pull</label><label><input type="checkbox" class="ik-pt" value="EYELET"> Eyelet</label><label><input type="checkbox" class="ik-pt" value="D-RING"> D-Ring</label><label><input type="checkbox" class="ik-pt" value="RIVET"> Rivet</label></td>
          <td class="ik-lackcell" style="text-align:center"><span class="ik-lackbox"><input type="checkbox" class="ik-lacking" aria-label="Lacking (1 = lacking, 0 = not)"${type === 'LACKING' ? ' checked' : ''}><input type="checkbox" class="ik-advance" title="Advance shoes testing" aria-label="Advance shoes testing"></span><input type="date" class="ik-lackdate" tabindex="-1" aria-label="Lacking date" value="${type === 'LACKING' ? ($('#dtr-intake-production')?.value || '') : ''}" style="position:absolute;opacity:0;width:1px;height:1px;pointer-events:none"><div class="ik-lackshow">${type === 'LACKING' ? ($('#dtr-intake-production')?.value || '').slice(5) : ''}</div></td>
          <td><textarea class="ik-retest-note" rows="2" placeholder="Remarks"></textarea></td>
        </tr>`;
  };
  const renderIntakeRows = () => {
    if (!intakeEl.tbody) return;
    const b = intake.building;
    let html = '';
    const lineCount = b === 'B' ? 14 : 12;
    const mode = intake.mode || 'DAILY';
    $('#dtr-intake-table')?.classList.toggle('ik-trial', ikNoLine(mode));   // Active and Line columns are hidden when there is no line
    const codeTh = $('#dtr-intake-table thead th:nth-child(6)');
    if (codeTh) codeTh.textContent = mode === 'SAMPLE' ? 'Sample Test' : 'Production Code';   // SAMPLE: that column (after Test Type) holds the sample test selection
    // TECH TRIAL / SAMPLE / INTERNAL TESTING are not tied to a building: the Building toggle is hidden
    const bField = $('#dtr-intake-building')?.closest('.dtr-intake-field');
    if (bField) bField.style.display = ['TECH TRIAL', 'SAMPLE', 'INTERNAL TESTING'].includes(mode) ? 'none' : '';
    $('#dtr-intake-table')?.classList.toggle('ik-m-fa', mode === 'FIRST ARTICLE');  // FIRST ARTICLE: no Active column
    $('#dtr-intake-table')?.classList.toggle('ik-nolack', mode !== 'DAILY');     // Lacking is for DAILY only
    $('#dtr-intake-table')?.classList.toggle('ik-m-trial', mode === 'TECH TRIAL');    // TECH TRIAL: no Production Code / Colorway
    $('#dtr-intake-table')?.classList.toggle('ik-m-sample', mode === 'SAMPLE');   // SAMPLE: no Production Code / Style / Colorway / MTL / Image / Pull Type
    if (ikNoLine(mode)) html = intakeRowHtml(`${b}0`, false, false, { type: mode, noLine: true });          // one row, no line, no Active toggle
    else if (ikPick(mode)) html = intakeRowHtml(`${b}1`, false, false, { type: mode, pick: true });       // one row, the line is chosen
    else for (let i = 1; i <= lineCount; i++) html += intakeRowHtml(`${b}${i}`, false, i % 2 === 0, { type: mode });   // DAILY / RETEST: every line
    intakeEl.tbody.innerHTML = html;
    const label = $('#dtr-intake-batch-label');
    if (label) label.textContent = `${b} • ${$('#dtr-intake-collection')?.value || ''}`;
    refreshIntakeLabRefs();
  };  // Lab Ref No = DTP + production date (YYYYMMDD) + SH + building + line number + -side (same rule the server uses on save)
  const refreshIntakeLabRefs = () => {
    const prodDate = $('#dtr-intake-production')?.value || '';
    const seen = {};   // running number (-#) per Lab Ref base in the order shown (the server confirms the free number on save)
    const trialNo = {}; let trialCount = 0;
    $$('#dtr-intake-tbody tr').forEach((tr) => {
      // every Lab Ref carries the Production Date selected at the top of the form
      // a lacking row carries the day it was lacking (the date picked in its Lacking box)
      const lackOn = tr.querySelector('.ik-lacking')?.checked ? (tr.querySelector('.ik-lackdate')?.value || '') : '';
      const d = (lackOn || prodDate).replace(/-/g, '');
      const side = tr.querySelector('.dtr-side-toggle .active')?.dataset.side || 'L';
      const type = tr.querySelector('.ik-type').value;
      const isRetest = type === 'RETEST';
      // Lab Ref formats (the server confirms the free numbers on save):
      //   DAILY             DTP<date>SH<line>-<side>-<#>
      //   TRIAL             DTP<date>TRIALSH-<side>-<#>          (no line)
      //   SAMPLE            DTP<date>SAMPLESH<line>-<side>-<#>
      //   INTERNAL TESTING  DTP<date>ITSH-<side>-<#>             (no line)
      //   FIRST ARTICLE     DTP<date>FASH<line>-<side>-<#>
      //   RETEST            DTP<date>SH<line>-<side>-R01         (R02, R03 ... for the next retests of the same batch)
      const pre = { 'TECH TRIAL': 'TRIALSH', 'SAMPLE': 'SAMPLESH', 'INTERNAL TESTING': 'ITSH', 'FIRST ARTICLE': 'FASH' }[type] || 'SH';
      // SAMPLE has a sample test: Bonding (SH) or Strip / Osna / Rivet (DTP<date>STRIP|OSNA|RIVET-<#>, no line, no side)
      const subV = type === 'SAMPLE' ? (tr.querySelector('.ik-sub')?.value || 'SH') : 'SH';
      const isStrip = subV !== 'SH';
      tr.classList.toggle('ik-strip', isStrip);
      const key = isStrip ? `DTP${d}${subV}` : `DTP${d}${pre}${ikNoLine(type) ? '' : tr.dataset.line}`;   // the number runs per line, whatever the side
      let ref;
      if (isRetest) ref = (tr.dataset.retestOf ? tr.dataset.retestOf.replace(/(-R\d+|-\d+)$/, '') : `DTP${d}SH${tr.dataset.line}-${side}`) + '-R01';
      else if (type === 'TECH TRIAL') {
        // a trial is counted from 1 (the first entry is trial 1); the two shoes of a pair share the number: TRIALSH-L-1 / TRIALSH-R-1
        const pk = tr.dataset.pair || '';
        let tn;
        if (pk && trialNo[pk]) tn = trialNo[pk]; else { trialCount += 1; tn = trialCount; if (pk) trialNo[pk] = tn; }
        ref = `${key}-${side}-${tn}`;
      } else {
        // no running number on a bonding Lab Ref (DTP<date>SH<line>-<side>); a second row of the same line and side gets -2, -3 ...
        const sk = isStrip ? key : `${key}-${side}`;
        seen[sk] = (seen[sk] || 0) + 1;
        ref = isStrip ? `${key}-${seen[sk]}` : (seen[sk] === 1 ? `${key}-${side}` : `${key}-${side}-${seen[sk]}`);
      }
      tr.querySelector('.ik-lr').textContent = d ? ref : '';
    });
  };

  const openIntake = () => {
    intake.building = 'A';
    intake.mode = 'DAILY';
    intakeProdTouched = false;
    $$('#dtr-intake-building button').forEach((x) => x.classList.toggle('active', x.dataset.building === 'A'));
    if (intakeEl.msg) intakeEl.msg.textContent = '';
    renderIntakeRows();
    intakeEl.back?.classList.remove('hidden');
  };
  const closeIntake = () => { closeLibrary(); closeRetest(); intakeEl.back?.classList.add('hidden'); };

  $$('[data-add-record="bonding"]').forEach((btn) => btn.addEventListener('click', openIntake));
  $('#btn-intake-close')?.addEventListener('click', closeIntake);
  $('#btn-intake-cancel')?.addEventListener('click', closeIntake);

  $$('#dtr-intake-building button').forEach((btn) => {
    btn.addEventListener('click', () => {
      intake.building = btn.dataset.building;
      $$('#dtr-intake-building button').forEach((x) => x.classList.toggle('active', x === btn));
      renderIntakeRows();
    });
  });

  // Pop-up when TECH TRIAL is chosen: 'PAIR' (L + R rows), 'L' or 'R' (one shoe), or null when closed
  const askTrialMode = () => new Promise((resolve) => {
    const ov = document.createElement('div');
    ov.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px';
    ov.innerHTML = `
      <div style="background:#fff;color:#0f172a;border-radius:14px;max-width:460px;width:100%;padding:22px 24px;box-shadow:0 20px 50px rgba(0,0,0,.35)">
        <h3 style="margin:0 0 6px;font-size:17px">Tech trial: pair or not?</h3>
        <p style="margin:0 0 16px;font-size:13px;color:#475569">Did the tech trial team give a <b>pair</b> (left and right) or <b>one shoe</b> only? A pair is recorded as two rows that share the MTL # and trial number (trial 1 = first entry).</p>
        <div style="display:flex;flex-direction:column;gap:8px">
          <button type="button" class="dtr-btn dtr-btn-primary" data-k="PAIR">Yes, a PAIR (L + R)</button>
          <div style="display:flex;gap:8px"><button type="button" class="dtr-btn dtr-btn-outline" style="flex:1" data-k="L">Single: LEFT only</button><button type="button" class="dtr-btn dtr-btn-outline" style="flex:1" data-k="R">Single: RIGHT only</button></div>
        </div>
      </div>`;
    ov.addEventListener('click', (e) => {
      const k = e.target.closest('button[data-k]')?.dataset.k;
      if (k) { ov.remove(); resolve(k); }
    });
    document.body.appendChild(ov);
  });
  // the two rows of a trial pair carry the same shoe: copy MTL # / code / style / colorway from the edited row to its partner
  const syncTrialPair = (tr) => {
    const pid = tr?.dataset.pair;
    if (!pid) return;
    $$('#dtr-intake-tbody tr').forEach((o) => {
      if (o === tr || o.dataset.pair !== pid) return;
      ['.ik-mtl', '.ik-code', '.ik-style', '.ik-color'].forEach((s) => { o.querySelector(s).value = tr.querySelector(s).value; });
      setIntakeImage(o);
    });
  };
  // Test Type of a row can reshape the batch: FIRST CASE = one row (line chosen, + kept), TECH TRIAL = one row without Active / Line;
  // choosing DAILY / RETEST while the batch is reduced brings back every line
  intakeEl.tbody?.addEventListener('change', (e) => {
    if (!e.target.classList.contains('ik-type')) return;
    const v = e.target.value;
    const reduced = intake.mode !== 'DAILY';
    // LACKING = the same as ticking the row's Lacking box (the Lab Ref keeps the Production Date chosen at the top: pick the day the shoe was lacking)
    const lk = e.target.closest('tr')?.querySelector('.ik-lacking');
    if (v === 'LACKING' && !reduced && lk) {
      if (!lk.checked) { lk.checked = true; lk.dispatchEvent(new Event('change', { bubbles: true })); }
      return;
    }
    if (v !== 'LACKING' && lk && lk.checked) {
      lk.checked = false; lk.dispatchEvent(new Event('change', { bubbles: true }));
      e.target.value = v;
      if (v === 'DAILY' && !reduced) return;
    }
    if (v === 'LACKING') { intake.mode = 'DAILY'; renderIntakeRows(); return; }   // from a reduced batch: back to every line, tick Lacking on the wanted one
    if (v !== 'DAILY' || reduced) {
      intake.mode = v;
      renderIntakeRows();
      if (v === 'TECH TRIAL') {
        // the tech trial team gives a pair or one shoe: ask, a pair is two rows (L + R) that share the MTL # and the trial number
        askTrialMode().then((pick) => {
          const first = intakeEl.tbody.querySelector('tr');
          if (!first || !pick) return;
          if (pick === 'PAIR') {
            const pid = `p${Date.now()}`;
            first.dataset.pair = pid;
            first.insertAdjacentHTML('afterend', intakeRowHtml(first.dataset.line, true, true, { type: 'TECH TRIAL', noLine: true }));
            first.nextElementSibling.dataset.pair = pid;
          } else {
            first.querySelectorAll('.dtr-side-toggle button').forEach((x) => x.classList.toggle('active', x.dataset.side === pick));
          }
          refreshIntakeLabRefs();
          first.querySelector('.ik-mtl')?.focus();
        });
      }
      if (v === 'RETEST') {
        retestRow = intakeEl.tbody.querySelector('tr');
        retestEl.search.value = '';
        retestEl.back.classList.remove('hidden');
        loadRetestList();
      }
    }
  });
  intakeEl.tbody?.addEventListener('change', (e) => {
    if (!e.target.classList.contains('ik-linesel')) return;
    const tr = e.target.closest('tr');
    tr.dataset.line = e.target.value;
    refreshIntakeLabRefs();
  });

  let intakeProdTouched = false;   // production date follows the collection date until it is edited by hand
  const syncIntakeDates = () => {
    if (!intakeProdTouched) $('#dtr-intake-production').value = $('#dtr-intake-collection').value;
    const label = $('#dtr-intake-batch-label');
    if (label) label.textContent = `${intake.building} • ${$('#dtr-intake-collection').value}`;
    refreshIntakeLabRefs();
  };
  $('#dtr-intake-collection')?.addEventListener('change', syncIntakeDates);
  $('#dtr-intake-production')?.addEventListener('change', () => { intakeProdTouched = true; refreshIntakeLabRefs(); });
  // L / R toggle changes the -L / -R ending
  intakeEl.tbody?.addEventListener('click', (e) => { if (e.target.closest('.dtr-side-toggle')) setTimeout(refreshIntakeLabRefs, 0); });

  // Library picker: searches tbl_dtr_shoe_list (Production Code / Style / Colorway / MTL)
  let libPanel = null;
  const closeLibrary = () => { libPanel?.remove(); libPanel = null; };
  const openLibrary = (tr, anchor) => {
    closeLibrary();
    const rect = anchor.getBoundingClientRect();
    libPanel = document.createElement('div');
    libPanel.className = 'dtr-lib-panel';
    libPanel.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - 540)) + 'px';
    libPanel.style.top = Math.min(rect.bottom + 4, window.innerHeight - 340) + 'px';
    libPanel.innerHTML = '<div class="lib-head"><input type="text" placeholder="Search code, style, colorway or MTL # ..."></div><div class="lib-list"></div>';
    document.body.appendChild(libPanel);
    const input = libPanel.querySelector('input');
    const list = libPanel.querySelector('.lib-list');
    let timer = null;
    const search = async () => {
      const q = input.value.trim();
      list.innerHTML = '<div class="lib-empty">Searching...</div>';
      try {
        const res = await apiFetch(`dtr/shoe-search?q=${encodeURIComponent(q)}`);
        const shoes = (res && res.shoes) || [];
        if (!shoes.length) { list.innerHTML = '<div class="lib-empty">No shoes found in the library.</div>'; return; }
        list.innerHTML = shoes.map((s, i) => `<div class="lib-item" data-i="${i}"><b>${escHtml(s.ProdCode || '')}</b><span>${escHtml(s.StyleNo || '')}</span><span>${escHtml(s.Colorway || '')} ${s.MTL ? '- ' + escHtml(s.MTL) : ''}</span></div>`).join('');
        list.onclick = (ev) => {
          const item = ev.target.closest('.lib-item');
          if (!item) return;
          const s = shoes[Number(item.dataset.i)];
          // intake row (.ik-*) or the inline editor of a Lab Tracker list row (data-f); a cell that already has data has no input and is left alone
          const put = (sels, v) => { for (const sel of sels) { const el = tr.querySelector(sel); if (el) { el.value = v; return; } } };
          put(['.ik-code', '[data-f=POLotNO]', '[data-f=ProdCode]'], s.ProdCode || '');
          put(['.ik-style', '[data-f=ModelStyle]', '[data-f=Style]'], s.StyleNo || '');
          put(['.ik-color', '[data-f=Color]', '[data-f=Colorway]'], s.Colorway || '');
          put(['.ik-mtl', '[data-f=MTL]'], s.MTL || '');
          setIntakeImage(tr);
          closeLibrary();
        };
      } catch (err) {
        list.innerHTML = '<div class="lib-empty">Library lookup failed.</div>';
      }
    };
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 250); });
    input.value = (tr.querySelector('.ik-code').value || tr.querySelector('.ik-mtl').value || tr.querySelector('.ik-style').value).trim();
    input.focus();
    search();
  };
  document.addEventListener('mousedown', (e) => { if (libPanel && !libPanel.contains(e.target) && !e.target.closest('.ik-lib, .ik-code')) closeLibrary(); });

  // Shoe picture in the intake row: \\192.168.32.43\ERP Image\<MTL #>.JPG (served by dtr/erp-image)
  const setIntakeImage = (tr) => {
    const img = tr.querySelector('.ik-img');
    if (!img) return;
    const mtl = tr.querySelector('.ik-mtl').value.trim();
    const note = (state) => tr.dispatchEvent(new CustomEvent('ik:image', { bubbles: true, detail: { state } }));   // dtr-photo.js shows a QR for a shoe the ERP has no picture of
    if (!/^[A-Za-z0-9_-]{3,40}$/.test(mtl)) { img.hidden = true; img.removeAttribute('src'); note('none'); return; }
    img.onload = () => { img.hidden = false; note('ok'); };
    img.onerror = () => { img.hidden = true; note('missing'); };
    img.src = `${BASE_URL}dtr/erp-image?mtl=${encodeURIComponent(mtl)}`;
  };
  // click a thumbnail -> larger picture (click anywhere or Esc to close)
  document.addEventListener('click', (e) => {
    const img = e.target.closest('.ik-img');
    if (!img || img.hidden || !img.src) return;
    const z = document.createElement('div');
    z.className = 'ik-zoom';
    z.innerHTML = `<img src="${img.src}" alt="">`;
    const close = () => { z.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = (k) => { if (k.key === 'Escape') close(); };
    z.addEventListener('click', close);
    document.addEventListener('keydown', onKey);
    document.body.appendChild(z);
  });
  // Production Code box doubles as the filter: typing lists matching ERP production orders (dberp.production_orders)
  let codeTimer = null;
  const suggestProdCode = (tr, input) => {
    clearTimeout(codeTimer);
    codeTimer = setTimeout(async () => {
      closeLibrary();
      const rect = input.getBoundingClientRect();
      libPanel = document.createElement('div');
      libPanel.className = 'dtr-lib-panel';
      libPanel.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - 540)) + 'px';
      libPanel.style.top = Math.min(rect.bottom + 2, window.innerHeight - 300) + 'px';
      libPanel.innerHTML = '<div class="lib-list"><div class="lib-empty">Searching...</div></div>';
      document.body.appendChild(libPanel);
      const panel = libPanel;
      const list = panel.querySelector('.lib-list');
      try {
        // every Production Code box (intake forms and Material Receive) filters on the ERP production orders (dberp.production_orders)
        const res = await apiFetch(`dtr/erp-shoe-search?q=${encodeURIComponent(input.value.trim())}`);
        let shoes = (res && res.shoes) || [];
        const byMtl = input.classList.contains('ik-mtl');
        if (byMtl) {   // MTL # picker: one entry per MTL #, newest order first
          const seenMtl = new Set();
          shoes = shoes.filter((s) => { const k = String(s.MTL || '').trim().toUpperCase(); if (!k || seenMtl.has(k)) return false; seenMtl.add(k); return true; });
        }
        if (!shoes.length) { list.innerHTML = `<div class="lib-empty">${byMtl ? 'No MTL # found in the production orders.' : 'No production code found.'}</div>`; return; }
        if (byMtl) {
          // an MTL # typed in full is taken straight from the production order
          const exact = shoes.filter((s) => String(s.MTL || '').toLowerCase() === input.value.trim().toLowerCase());
          if (exact.length) {
            const s = exact[0];
            tr.querySelector('.ik-code').value = s.ProdCode || '';
            tr.querySelector('.ik-style').value = s.StyleNo || '';
            tr.querySelector('.ik-color').value = s.Colorway || '';
            setIntakeImage(tr);
            syncTrialPair(tr);
            closeLibrary();
            return;
          }
        }
        list.innerHTML = byMtl
          ? shoes.map((s, i) => `<div class="lib-item" data-i="${i}"><b>${escHtml(s.MTL || '')}</b><span>${escHtml(s.StyleNo || '')}</span><span>${escHtml(s.Colorway || '')}</span></div>`).join('')
          : shoes.map((s, i) => `<div class="lib-item" data-i="${i}"><b>${escHtml(s.ProdCode || '')}</b><span>${escHtml(s.StyleNo || '')}</span><span>${escHtml(s.Colorway || '')} ${s.MTL ? '- ' + escHtml(s.MTL) : ''}</span></div>`).join('');
        list.onclick = (ev) => {
          const item = ev.target.closest('.lib-item');
          if (!item) return;
          const s = shoes[Number(item.dataset.i)];
          // intake row (.ik-*) or the inline editor of a Lab Tracker list row (data-f); a cell that already has data has no input and is left alone
          const put = (sels, v) => { for (const sel of sels) { const el = tr.querySelector(sel); if (el) { el.value = v; return; } } };
          put(['.ik-code', '[data-f=POLotNO]', '[data-f=ProdCode]'], s.ProdCode || '');
          put(['.ik-style', '[data-f=ModelStyle]', '[data-f=Style]'], s.StyleNo || '');
          put(['.ik-color', '[data-f=Color]', '[data-f=Colorway]'], s.Colorway || '');
          put(['.ik-mtl', '[data-f=MTL]'], s.MTL || '');
          setIntakeImage(tr);
          syncTrialPair(tr);
          closeLibrary();
          if (tr.closest('#dtr-mrec-tbody')) window.mrAutoMaterial?.(tr);   // Material Receive: the materials of this production order fill in
        };
      } catch (err) {
        // a reply that is not JSON is the login page: the session ended; anything else is a network / server problem
        list.innerHTML = err instanceof SyntaxError
          ? '<div class="lib-empty">Your session has ended. Refresh the page and sign in again.</div>'
          : '<div class="lib-empty">ERP lookup failed (' + escHtml(String(err.message || err)) + ').</div>';
      }
    }, 250);
  };
  intakeEl.tbody?.addEventListener('input', (e) => {
    if (e.target.classList.contains('ik-mtl') && !e.target.readOnly) {
      // TRIAL: the MTL # is typed; it looks the production order up in dberp.production_orders and fills Style (and the hidden code / colorway)
      const tr = e.target.closest('tr');
      ['.ik-code', '.ik-style', '.ik-color'].forEach((s) => { tr.querySelector(s).value = ''; });
      setIntakeImage(tr);
      syncTrialPair(tr);
      if (e.target.value.trim()) suggestProdCode(tr, e.target);
      return;
    }
    if (!e.target.classList.contains('ik-code')) return;
    const tr = e.target.closest('tr');
    // style / colorway / MTL / image come only from the picked production code: editing or clearing the code resets them
    ['.ik-style', '.ik-color', '.ik-mtl'].forEach((s) => { tr.querySelector(s).value = ''; });
    setIntakeImage(tr);
    suggestProdCode(tr, e.target);
  });
  intakeEl.tbody?.addEventListener('focusin', (e) => {
    if ((e.target.classList.contains('ik-code') || (e.target.classList.contains('ik-mtl') && !e.target.readOnly)) && !libPanel) suggestProdCode(e.target.closest('tr'), e.target);
  });
  intakeEl.tbody?.addEventListener('change', (e) => {
    if (e.target.classList.contains('ik-mtl')) setIntakeImage(e.target.closest('tr'));
  });
  intakeEl.tbody?.addEventListener('click', async (e) => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    const subBtn = e.target.closest('.ik-subbtn');
    if (subBtn) {   // child of SAMPLE: Bonding or Strip Test
      tr.querySelectorAll('.ik-subbtn').forEach((x) => x.classList.toggle('active', x === subBtn));
      tr.querySelector('.ik-sub').value = subBtn.dataset.sub;
      refreshIntakeLabRefs();
      return;
    }
    const sideBtn = e.target.closest('.dtr-side-toggle button');
    if (sideBtn) {
      tr.querySelectorAll('.dtr-side-toggle button').forEach((x) => x.classList.toggle('active', x === sideBtn));
      return;
    }
    if (e.target.closest('.ik-addrow') && intake.mode === 'TECH TRIAL') {
      // another trial entry (the team may hand over several different shoes): same pair / single question, added at the end as the next trial
      const pick = await askTrialMode();
      if (!pick) return;
      const lastRow = intakeEl.tbody.lastElementChild;
      const ln = lastRow.dataset.line;
      const mk = (right) => { const t = document.createElement('tbody'); t.innerHTML = intakeRowHtml(ln, true, right, { type: 'TECH TRIAL', noLine: true }); return t.firstElementChild; };
      const added = pick === 'PAIR' ? [mk(false), mk(true)] : [mk(pick === 'R')];
      if (pick === 'PAIR') { const pid = `p${Date.now()}`; added.forEach((x) => { x.dataset.pair = pid; }); }
      added.forEach((x) => intakeEl.tbody.appendChild(x));
      refreshIntakeLabRefs();
      added[0].querySelector('.ik-mtl')?.focus();
      return;
    }
    if (e.target.closest('.ik-addrow')) {
      // new row goes after the last row of the same line, on the opposite side of that row (L <-> R)
      let last = tr;
      while (last.nextElementSibling && last.nextElementSibling.dataset.line === last.dataset.line) last = last.nextElementSibling;
      const lastSide = last.querySelector('.dtr-side-toggle .active')?.dataset.side || 'L';
      // First Case: the new row keeps the Test Type and gets its own line dropdown, preset to the same line
      const firstCase = ikPick(intake.mode);
      last.insertAdjacentHTML('afterend', intakeRowHtml(last.dataset.line, true, lastSide === 'L', { type: intake.mode, pick: firstCase, noLine: ikNoLine(intake.mode) }));
      refreshIntakeLabRefs();
      if (intake.mode === 'RETEST') {   // every retest row loads a previously saved failed record
        retestRow = last.nextElementSibling;
        retestEl.search.value = '';
        retestEl.back.classList.remove('hidden');
        loadRetestList();
        return;
      }
      last.nextElementSibling.querySelector('.ik-code')?.focus();
      return;
    }
    if (e.target.closest('.ik-delrow')) { tr.remove(); return; }
    if (e.target.closest('.ik-lib')) {
      openLibrary(tr, e.target.closest('.ik-lib'));
    }
  });

  // Retest picker: lists failed Lab Ref Nos (with analysis / reason) when Test Type = RETEST
  const retestEl = { back: $('#dtr-retest-backdrop'), tbody: $('#dtr-retest-tbody'), search: $('#dtr-retest-search') };
  let retestRow = null;
  const closeRetest = () => {
    retestEl.back?.classList.add('hidden');
    if (retestRow && !retestRow.querySelector('.ik-retest-note').value.trim()) {
      if (retestRow.dataset.extra && intake.mode === 'RETEST') retestRow.remove();   // an added retest row that was not picked is dropped
      else retestRow.querySelector('.ik-type').value = 'DAILY';
    }
    retestRow = null;
  };
  const loadRetestList = async () => {
    const q = retestEl.search.value.trim();
    retestEl.tbody.innerHTML = '<tr><td colspan="8" class="muted">Loading…</td></tr>';
    try {
      const res = await apiFetch(`dtr/retest-list?q=${encodeURIComponent(q)}`);
      const list = (res && res.rows) || [];
      if (!list.length) { retestEl.tbody.innerHTML = '<tr><td colspan="8" class="muted">No failed Lab Ref No. is waiting for a retest.</td></tr>'; return; }
      retestEl.tbody.innerHTML = list.map((r, i) => `
        <tr>
          <td class="mono"><b>${escHtml(r.LabRefNo)}</b></td>
          <td>${escHtml(r.ModelStyle || '')}</td>
          <td>${escHtml(r.Color || '')}</td>
          <td><span class="badge b-err">${escHtml(r.Result || '')}</span></td>
          <td>${escHtml(r.ModeOfFailure || '-')}</td>
          <td style="max-width:320px">${escHtml(r.Analysis || '-')}</td>
          <td>${escHtml(String(r.TestedDate || '').slice(0, 10))}</td>
          <td><button type="button" class="dtr-btn dtr-btn-primary" data-pick="${i}">Select</button></td>
        </tr>`).join('');
      retestEl.tbody.onclick = (ev) => {
        const b = ev.target.closest('[data-pick]');
        if (!b || !retestRow) return;
        const r = list[Number(b.dataset.pick)];
        retestRow.querySelector('.ik-style').value = r.ModelStyle || '';
        retestRow.querySelector('.ik-color').value = r.Color || '';
        retestRow.querySelector('.ik-code').value = r.POLotNO || retestRow.querySelector('.ik-code').value;
        retestRow.querySelector('.ik-mtl').value = r.MTL || '';
        setIntakeImage(retestRow);   // the shoe picture follows the MTL # of the failed specimen
        const why = [r.ModeOfFailure, r.Analysis].filter(Boolean).join(' - ');
        retestRow.querySelector('.ik-retest-note').value = `Retest of ${r.LabRefNo}\nFailed: ${why || 'no analysis recorded'}`;
        retestRow.dataset.retestOf = r.LabRefNo;   // the new Lab Ref is this failed one + -R01, -R02 ...
        const sideBtn = retestRow.querySelector(`.dtr-side-toggle button[data-side="${/-R$/.test(r.LabRefNo) ? 'R' : 'L'}"]`);
        sideBtn?.click();
        // the loaded data is locked: only the + button of the row stays usable
        retestRow.classList.add('ik-locked');
        retestRow.querySelectorAll('input:not(.ik-addrow), select, textarea, .dtr-side-toggle button').forEach((el) => { el.disabled = true; });
        retestEl.back.classList.add('hidden');
        retestRow = null;
      };
    } catch (err) {
      retestEl.tbody.innerHTML = '<tr><td colspan="8" class="muted">Could not load the retest list.</td></tr>';
    }
  };
  let retestTimer = null;
  retestEl.search?.addEventListener('input', () => { clearTimeout(retestTimer); retestTimer = setTimeout(loadRetestList, 250); });
  $('#btn-retest-close')?.addEventListener('click', closeRetest);
  $('#btn-retest-cancel')?.addEventListener('click', closeRetest);

  // Lacking is saved the moment it is ticked (and again when its date changes / it is unticked): a LACKING marker of that line, side and
  // production date is only updated (Lab Ref follows the date), never added twice. The Save Batch button keeps working the same way.
  const lackPayload = (tr) => ({
    line: tr.dataset.line,
    side: tr.querySelector('.dtr-side-toggle .active')?.dataset.side || 'L',
    date: tr.querySelector('.ik-lackdate').value || $('#dtr-intake-production').value,
    type: tr.querySelector('.ik-type').value,
    code: tr.querySelector('.ik-code').value.trim(),
    style: tr.querySelector('.ik-style').value.trim(),
    color: tr.querySelector('.ik-color').value.trim(),
    mtl: tr.querySelector('.ik-mtl').value.trim(),
    pullTypes: [...tr.querySelectorAll('.ik-pt:checked')].map((c) => c.value),
    remarks: tr.querySelector('.ik-retest-note').value.trim().replace(/\s*\n\s*/g, ' | '),
  });
  const autoSaveLacking = async (tr, wasDate) => {
    const on = tr.querySelector('.ik-lacking').checked;
    const body = {
      building: intake.building,
      collectionDate: $('#dtr-intake-collection').value,
      productionDate: $('#dtr-intake-production').value,
      encodedBy: $('#dtr-intake-encoder').value.trim(),
      rows: [], shutdowns: [], lackings: [], dropLackings: [],
    };
    const side = tr.querySelector('.dtr-side-toggle .active')?.dataset.side || 'L';
    let nowDate = '';
    if (on) { const p = lackPayload(tr); nowDate = p.date; body.lackings.push(p); }
    if (wasDate && wasDate !== nowDate) body.dropLackings.push({ line: tr.dataset.line, side, date: wasDate });
    if (!body.lackings.length && !body.dropLackings.length) return;
    try {
      const res = await apiFetch('dtr/intake-save', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      if (res && res.ok) {
        if (on) tr.dataset.lackSaved = nowDate; else delete tr.dataset.lackSaved;
        showToast(on ? `Lacking saved: ${tr.querySelector('.ik-lr').textContent.trim()}` : 'Lacking removed.');
      } else if (intakeEl.msg) intakeEl.msg.textContent = (res && res.message) || 'Lacking was not saved.';
    } catch (err) {
      if (intakeEl.msg) intakeEl.msg.textContent = 'Lacking was not saved: ' + err.message;
    }
  };
  intakeEl.tbody?.addEventListener('focusin', (e) => {
    if (e.target.classList?.contains('ik-lackdate')) {
      const tr = e.target.closest('tr');
      if (tr && !tr.dataset.lackSaved && tr.querySelector('.ik-lacking').checked && e.target.value) tr.dataset.lackSaved = e.target.value;
    }
  });
  intakeEl.tbody?.addEventListener('change', (e) => {
    const tr = e.target.closest('tr');
    if (!tr || !tr.isConnected) return;   // the batch was just re-rendered by the Test Type change: this row is gone
    if (e.target.classList.contains('ik-lacking') || e.target.classList.contains('ik-lackdate')) {
      const was = tr.dataset.lackSaved || '';
      queueMicrotask(() => { if (tr.isConnected) autoSaveLacking(tr, was || (e.target.classList.contains('ik-lacking') && !e.target.checked ? (tr.dataset.lackWas || '') : '')); });
    }
    if (e.target.classList.contains('ik-active')) tr.classList.toggle('inactive', !e.target.checked);
    if (e.target.classList.contains('ik-tech')) e.target.value = withMachineNo(e.target.value);
    if (e.target.classList.contains('ik-lacking')) {
      const di = tr.querySelector('.ik-lackdate');
      if (e.target.checked) {
        if (!di.value) di.value = $('#dtr-intake-production')?.value || '';
        try { di.showPicker(); } catch (_) { /* older browsers: the default date stays */ }
      } else di.value = '';
      // Lacking ticked: the row is locked like a SHUTDOWN row (only the Lacking box and its date stay usable) and is recorded as lacking
      tr.classList.toggle('lacked', e.target.checked);
      const ts = tr.querySelector('.ik-type');
      if (e.target.checked) ts.value = 'LACKING'; else if (ts.value === 'LACKING') ts.value = 'DAILY';
    }
    if (e.target.classList.contains('ik-lacking') || e.target.classList.contains('ik-lackdate')) {
      tr.querySelector('.ik-lackshow').textContent = tr.querySelector('.ik-lacking').checked ? tr.querySelector('.ik-lackdate').value.slice(5) : '';
      refreshIntakeLabRefs();
    }
    if (e.target.classList.contains('ik-type') && e.target.value !== 'RETEST') delete tr.dataset.retestOf;
    if (e.target.classList.contains('ik-type') || e.target.classList.contains('ik-retest-note') || e.target.classList.contains('ik-sub')) refreshIntakeLabRefs();
    if (e.target.classList.contains('ik-type') && e.target.value === 'RETEST') {
      retestRow = tr;
      retestEl.search.value = '';
      retestEl.back.classList.remove('hidden');
      loadRetestList();
    }
  });

  $('#btn-intake-save')?.addEventListener('click', async () => {
    const rows = [];
    const shutdowns = [];
    const lackings = [];
    const trialSeen = new Set();
    let trialIssue = '';
    let pullIssue = '';
    $$('#dtr-intake-tbody tr').forEach((tr) => {
      const act = tr.querySelector('.ik-active');
      if (act && !act.checked) { if (!tr.dataset.extra) shutdowns.push(tr.dataset.line); return; }   // main row toggled off = line shutdown (an extra row only skips itself)
      if (tr.querySelector('.ik-lacking').checked) {   // lacking line: recorded like a shutdown (no specimen, no Lab Ref)
        lackings.push({
          line: tr.dataset.line,
          side: tr.querySelector('.dtr-side-toggle .active')?.dataset.side || 'L',
          date: tr.querySelector('.ik-lackdate').value || $('#dtr-intake-production').value,
          type: tr.querySelector('.ik-type').value,
          code: tr.querySelector('.ik-code').value.trim(),
          style: tr.querySelector('.ik-style').value.trim(),
          color: tr.querySelector('.ik-color').value.trim(),
          mtl: tr.querySelector('.ik-mtl').value.trim(),
          pullTypes: [...tr.querySelectorAll('.ik-pt:checked')].map((c) => c.value),
          remarks: tr.querySelector('.ik-retest-note').value.trim().replace(/\s*\n\s*/g, ' | '),
          labRef: tr.querySelector('.ik-lr').textContent.trim(),
        });
        return;
      }
      const row = {
        line: tr.dataset.line,
        side: tr.querySelector('.dtr-side-toggle .active')?.dataset.side || 'L',
        code: tr.querySelector('.ik-code').value.trim(),
        style: tr.querySelector('.ik-style').value.trim(),
        color: tr.querySelector('.ik-color').value.trim(),
        mtl: tr.querySelector('.ik-mtl').value.trim(),
        test: 'Bonding',
        pullTypes: [...tr.querySelectorAll('.ik-pt:checked')].map((c) => c.value),
        tech: '',
        type: tr.querySelector('.ik-type').value,
        retestNote: tr.querySelector('.ik-retest-note').value.trim().replace(/\s*\n\s*/g, ' | '),
        retestOf: tr.dataset.retestOf || '',
        sub: tr.querySelector('.ik-sub')?.value || '',
        pair: tr.dataset.pair || '',
        advance: !!tr.querySelector('.ik-advance')?.checked,
        lacking: tr.querySelector('.ik-lacking').checked,
        lackingDate: tr.querySelector('.ik-lacking').checked ? tr.querySelector('.ik-lackdate').value : '',
      };
      row.retest = row.type === 'RETEST';   // a remark on any other test type is only a remark, it does not turn the row into a retest
      // ticked pull types are never dropped silently: a row needs its shoe first, and SAMPLE rows take no pull tests
      if (row.pullTypes.length && row.type === 'SAMPLE') { pullIssue = `Line ${row.line}: pull types are not used for SAMPLE rows. Untick them.`; return; }
      if (row.pullTypes.length && !(row.code || row.style || row.color || row.mtl)) { pullIssue = `Line ${row.line}: pick a Production Code before ticking pull types (${row.pullTypes.join(', ')}).`; return; }
      // an MTL # not in the production orders can still be saved when the row has remarks (they describe the shoe)
      if (row.type === 'TECH TRIAL' && row.mtl && !row.style && !row.retestNote) { trialIssue = `MTL # ${row.mtl} was not found in the production orders. Pick it from the list.`; return; }
      // the same MTL # can be tried again: every entry is counted as the next trial (trial 1, 2, 3 ...), a pair shares one number
      if (row.code || row.style || row.color || row.mtl || row.type === 'SAMPLE') rows.push(row);
    });
    const warnIssue = (m) => { intakeEl.msg.textContent = m; successBox(m, null, 'Cannot save yet', true); };
    if (trialIssue) { warnIssue(trialIssue); return; }
    if (pullIssue) { warnIssue(pullIssue); return; }
    if (!rows.length && !shutdowns.length && !lackings.length) { warnIssue('Fill in at least one active line before saving.'); return; }
    const sb = savingBox('Saving batch...');
    closeIntake();   // the window closes at once; a message box confirms the save
    try {
      const res = await apiFetch('dtr/intake-save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          building: intake.building,
          collectionDate: $('#dtr-intake-collection').value,
          productionDate: $('#dtr-intake-production').value,
          encodedBy: $('#dtr-intake-encoder').value.trim(),
          rows,
          shutdowns,
          lackings,
        }),
      });
      if (res && res.ok) {
        afterSave(res.message, 'bonding');
      } else {
        sb.fail(); intakeEl.back?.classList.remove('hidden');
        intakeEl.msg.textContent = (res && res.message) || 'Save failed.';
        successBox(intakeEl.msg.textContent, null, /Already saved|Nothing new/.test(intakeEl.msg.textContent) ? 'Already saved' : 'Save failed', true);
      }
    } catch (err) {
      sb.fail(); intakeEl.back?.classList.remove('hidden');
      intakeEl.msg.textContent = 'Save failed: ' + err.message;
      successBox(intakeEl.msg.textContent, null, 'Save failed', true);
    }
  });

  // After a save the page reloads straight onto the list that was saved into (its sub-tab opens) and the success box is shown over it
  const afterSave = (msg, sub) => {
    try { sessionStorage.setItem('dtr_after_save', JSON.stringify({ msg, sub })); } catch (e) { /* storage blocked: the list just reloads */ }
    location.reload();
  };
  try {
    const as = JSON.parse(sessionStorage.getItem('dtr_after_save') || 'null');
    if (as) {
      sessionStorage.removeItem('dtr_after_save');
      document.querySelector(`.dtr-subtab[data-subtab="${as.sub}"]`)?.click();
      setTimeout(() => successBox(as.msg), 150);
    }
  } catch (e) { /* ignore */ }

  // Message box shown after a save (and after a delete): stays until OK is pressed, then onClose runs (e.g. reload the list)
  const successBox = (msg, onClose, title = 'Saved successfully', warn = false) => {
    const ov = document.createElement('div');
    ov.className = 'dtr-msgbox-bd';
    ov.innerHTML = `<div class="dtr-msgbox" role="alertdialog" aria-modal="true"><div class="dtr-msgbox-ic"${warn ? ' style="background:#fef3c7;color:#b45309"' : ''}>${warn ? '!' : '&#10003;'}</div><h3>${escHtml(title)}</h3><p>${escHtml(msg || '')}</p><button type="button" class="dtr-btn dtr-btn-primary">OK</button></div>`;
    const done = () => { ov.remove(); document.removeEventListener('keydown', onKey); if (onClose) onClose(); };
    const onKey = (e) => { if (e.key === 'Escape' || e.key === 'Enter') { e.preventDefault(); done(); } };
    ov.querySelector('button').addEventListener('click', done);
    document.addEventListener('keydown', onKey);
    document.body.appendChild(ov);
    ov.querySelector('button').focus();
  };

  // Save in progress: the form window closes at once and this box waits for the server; .ok() turns it into the success box, .fail() removes it (the window reopens with the error)
  const savingBox = (text = 'Saving...') => {
    const ov = document.createElement('div');
    ov.className = 'dtr-msgbox-bd';
    ov.innerHTML = `<div class="dtr-msgbox" role="status"><div class="dtr-spin"></div><h3>${escHtml(text)}</h3><p>Please wait.</p></div>`;
    document.body.appendChild(ov);
    return { ok: (msg, onClose) => { ov.remove(); successBox(msg, onClose); }, fail: () => ov.remove() };
  };

  // Edit button (before Delete) of every Lab Tracker list: the columns with no data on that row become editable (date columns get a calendar)
  const toast = (msg) => {
    const t = document.createElement('div');
    t.className = 'dtr-mini-toast'; t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3200);
  };
  const EDIT_COLS = {
    bonding:   { 'Prod. Code': 'POLotNO', 'Style No.': 'ModelStyle', 'Colorway': 'Color', 'MTL #': 'MTL', 'Prod. Date': ['ProductionDate', 'date'], 'Tested Date': ['TestedDate', 'date'], 'Technician': 'Technician' },
    lacking:   { 'Prod. Code': 'POLotNO', 'Style No.': 'ModelStyle', 'Colorway': 'Color', 'MTL #': 'MTL', 'Prod. Date': ['ProductionDate', 'date'], 'Technician': 'Technician' },
    wholeshoe: { 'Prod. Code': 'ProdCode', 'Style No.': 'Style', 'Colorway': 'Colorway', 'MTL #': 'MTL', 'Prod. Date': ['ProductionDate', 'date'] },
    container: {},
    material:  { 'Material': 'MaterialName', 'Material Type': 'MaterialType', 'Material Code': 'MaterialCode', 'VSP Code': 'MaterialProdCode', 'Supplier': 'Supplier', 'IQC ERP': 'IQCERP', 'Remarks': 'Remarks' },
  };
  const EDIT_ICO = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>';
  const CHECK_ICO = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
  const editLocked = new Set(window.dtrEditLocked || []);
  // The Pull Test LabRefNo cell of a bonding row of the Lab Tracker when it is empty (or a dash): pull tests can still be added to that row, even after
  // the row's other columns were synced and locked.
  const PULL_TYPES = [['HEEL PULL', 'Heel Pull'], ['EYELET', 'Eyelet'], ['D-RING', 'D-Ring'], ['RIVET', 'Rivet']];
  const emptyPullCell = (tr) => {
    if (!window.dtrCanAddPull || !tr || !tr.querySelector('.dtr-del-btn[data-del="bonding"]')) return null;   // Admin, Supervisor and Bonding PIC only
    const heads = [...tr.closest('table').querySelectorAll('thead th')].map((th) => th.textContent.replace(/[▲▼]/g, '').trim());   // the sort arrows of a header are not part of its name
    const i = heads.indexOf('Pull Test LabRefNo');
    const td = i >= 0 ? tr.children[i] : null;
    if (!td || td.querySelector('input')) return null;
    return pullRoom(td).left > 0 ? td : null;
  };
  // a bonding row carries at most 4 pull tests (one of each type): what its Pull Test LabRefNo cell already lists is subtracted
  const PULL_CODES = { HP: 'HEEL PULL', EY: 'EYELET', DR: 'D-RING', RVT: 'RIVET' };
  const pullRoom = (td) => {
    const refs = [...td.querySelectorAll('.dtr-ref-item')].map((x) => x.textContent.trim()).filter(Boolean);
    const have = new Set(refs.map((r) => PULL_CODES[(r.match(/^DTP\d{8}(HP|EY|DR|RVT)/) || [])[1]]).filter(Boolean));
    const left = Math.min(4 - refs.length, PULL_TYPES.filter(([v]) => !have.has(v)).length);
    return { have, left, count: refs.length };
  };
  // also called again after a list reload (date filter / search / KPI card) because the rows are replaced
  window.dtrAddEditButtons = (root) => (root || document).querySelectorAll('.dtr-del-btn').forEach((d) => {
    if (d.closest('.ws-child-table') || !EDIT_COLS[d.dataset.del] || (editLocked.has(d.dataset.del + ':' + d.dataset.id) && !emptyPullCell(d.closest('tr')))) return;
    if (d.parentNode.querySelector('.dtr-edit-btn')) return;
    const b = document.createElement('button');
    b.type = 'button'; b.className = 'dtr-edit-btn';
    b.dataset.edit = d.dataset.del; b.dataset.id = d.dataset.id;
    b.title = 'Edit the empty columns of this entry'; b.setAttribute('aria-label', 'Edit this entry');
    b.innerHTML = EDIT_ICO;
    d.parentNode.insertBefore(b, d);
  });
  window.dtrAddEditButtons();
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.dtr-edit-btn');
    if (!btn) return;
    e.preventDefault();
    const tr = btn.closest('tr');
    if (btn.dataset.mode === 'save') {
      const fields = {};
      tr.querySelectorAll('.dtr-edit-in').forEach((i) => { if (i.value.trim() !== '') fields[i.dataset.f] = i.value.trim(); });
      const pullTypes = [...tr.querySelectorAll('.dtr-edit-pulls .ik-pt:checked')].map((c) => c.value);
      if (!Object.keys(fields).length && !pullTypes.length) { toast('Type or pick something in an empty column, or tick a pull type, first.'); return; }
      btn.disabled = true;
      try {
        let msg = '';
        if (Object.keys(fields).length) {
          const res = await apiFetch('dtr/update-entry', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ kind: btn.dataset.edit, id: Number(btn.dataset.id), fields }),
          });
          if (!(res && res.ok)) { toast((res && res.message) || 'Save failed.'); btn.disabled = false; return; }
          msg = res.message;
        }
        if (pullTypes.length) {
          // pull tests for THIS row: their Lab Refs and details are made from the row (same Production Code, line, date and side)
          const pr = await apiFetch('dtr/add-pull-tests', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: Number(btn.dataset.id), pullTypes }),
          });
          if (!(pr && pr.ok)) { toast((pr && pr.message) || 'The pull tests could not be added.'); btn.disabled = false; return; }
          msg = (msg ? msg + ' ' : '') + pr.message;
        }
        tr.querySelectorAll('td[data-edit-orig]').forEach((td) => {
          if (td.dataset.editHtml !== undefined) { td.innerHTML = td.dataset.editHtml; delete td.dataset.editHtml; td.removeAttribute('data-edit-orig'); return; }   // the pull cell keeps its Lab Refs (the list reloads with the new ones)
          const i = td.querySelector('.dtr-edit-in');
          td.textContent = i && i.value.trim() !== '' ? i.value.trim() : '';
          td.removeAttribute('data-edit-orig');
        });
        btn.dataset.mode = ''; btn.innerHTML = EDIT_ICO; btn.classList.remove('is-save'); btn.title = 'Edit the empty columns of this entry';
        leaveEditUi(tr);
        successBox(msg, () => { if (pullTypes.length) window.dtrReloadList?.('bonding', Number($('#lt-page-bonding')?.dataset.page || 1)); }, 'Saved');
      } catch (ex) { toast('Save failed: ' + (ex.message || ex)); }
      btn.disabled = false;
      return;
    }
    const heads = [...tr.closest('table').querySelectorAll('thead th')].map((th) => th.textContent.replace(/[▲▼]/g, '').trim());   // the sort arrows of a header are not part of its name
    const locked = editLocked.has(btn.dataset.edit + ':' + btn.dataset.id);   // a synced row: its columns are final, only pull tests can still be added
    const cols = locked ? {} : (EDIT_COLS[btn.dataset.edit] || {});
    let n = 0;
    const pullTd = emptyPullCell(tr);
    if (pullTd) {
      pullTd.dataset.editOrig = '1';
      pullTd.dataset.editHtml = pullTd.innerHTML;
      const room = pullRoom(pullTd);
      pullTd.innerHTML = pullTd.dataset.editHtml.replace(/<span class="muted">-<\/span>/, '')
        + '<div class="dtr-edit-pulls">' + (room.count ? `<div class="muted sm">Add ${room.left} more at most (4 per row)</div>` : '')
        + PULL_TYPES.filter(([v]) => !room.have.has(v)).map(([v, l]) => `<label><input type="checkbox" class="ik-pt" value="${v}"> ${l}</label>`).join('') + '</div>';
      pullTd.querySelector('.dtr-edit-pulls').addEventListener('change', (ev) => {   // never more than the room left
        const box = ev.currentTarget; const on = box.querySelectorAll('.ik-pt:checked').length;
        box.querySelectorAll('.ik-pt:not(:checked)').forEach((c) => { c.disabled = on >= room.left; });
      });
      n++;
    }
    [...tr.children].forEach((td, i) => {
      const spec = cols[heads[i]];
      if (!spec || td.textContent.trim() !== '' || td.querySelector('input')) return;
      const [f, type] = Array.isArray(spec) ? spec : [spec, 'text'];
      td.dataset.editOrig = '1';
      const isCode = f === 'POLotNO' || f === 'ProdCode';
      td.innerHTML = `<input type="${type}" class="dtr-edit-in${isCode ? ' ik-code' : ''}" data-f="${f}" aria-label="${escHtml(heads[i])}"${isCode ? ' placeholder="Type to filter" autocomplete="off"' : ''}>`;
      n++;
    });
    if (!n) { toast('Every editable column of this row already has data.'); return; }
    btn.dataset.mode = 'save'; btn.innerHTML = CHECK_ICO; btn.classList.add('is-save'); btn.title = 'Save';
    // while a row is being edited the Delete button is replaced by Cancel (no accidental delete); Cancel puts the row back as it was
    const del = tr.querySelector('.dtr-del-btn');
    if (del && !tr.querySelector('.dtr-cancel-btn')) {
      del.hidden = true;
      const c = document.createElement('button');
      c.type = 'button'; c.className = 'dtr-cancel-btn'; c.title = 'Cancel the edit'; c.setAttribute('aria-label', 'Cancel the edit');
      c.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
      del.parentNode.insertBefore(c, del);
    }
    tr.querySelector('.dtr-edit-in')?.focus();
  });
  const leaveEditUi = (tr) => {
    tr.querySelector('.dtr-cancel-btn')?.remove();
    const del = tr.querySelector('.dtr-del-btn');
    if (del) del.hidden = false;
  };
  document.addEventListener('click', (e) => {
    const c = e.target.closest('.dtr-cancel-btn');
    if (!c) return;
    e.preventDefault();
    const tr = c.closest('tr');
    tr.querySelectorAll('td[data-edit-orig]').forEach((td) => {
      if (td.dataset.editHtml !== undefined) { td.innerHTML = td.dataset.editHtml; delete td.dataset.editHtml; } else td.textContent = '';
      td.removeAttribute('data-edit-orig');
    });
    const b = tr.querySelector('.dtr-edit-btn');
    if (b) { b.dataset.mode = ''; b.innerHTML = EDIT_ICO; b.classList.remove('is-save'); b.title = 'Edit the empty columns of this entry'; }
    leaveEditUi(tr);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && e.target.classList?.contains('dtr-edit-in')) e.target.closest('tr')?.querySelector('.dtr-cancel-btn')?.click();
  });
  // edits are carried to the other tables of the Lab Ref 3 minutes after saving: the page asks the server every minute to run the due ones
  // a synced row is locked: its edit button goes away
  const lockRows = (keys) => (keys || []).forEach((k) => {
    editLocked.add(k);
    const [kind, id] = k.split(':');
    document.querySelectorAll(`.dtr-edit-btn[data-edit="${kind}"][data-id="${id}"]`).forEach((b) => { if (!emptyPullCell(b.closest('tr'))) b.remove(); });
  });
  setInterval(() => {
    if (document.hidden) return;
    apiFetch('dtr/sync-edits', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' }).then((r) => { if (r && r.ok) lockRows(r.locked); }).catch(() => {});
  }, 60000);
  // Prod. Code of an edited row: same ERP production-order filter as when a shoe is registered (picks fill Style / Colorway / MTL # if they are empty)
  const editCodeSearch = (e) => { if (e.target.matches?.('.dtr-edit-in.ik-code')) suggestProdCode(e.target.closest('tr'), e.target); };
  document.addEventListener('input', editCodeSearch);
  document.addEventListener('focusin', (e) => { if (!libPanel) editCodeSearch(e); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.classList?.contains('dtr-edit-in')) e.target.closest('tr')?.querySelector('.dtr-edit-btn')?.click();
  });

  // Delete button of every Lab Tracker list: asks why (required), deletes the entry and writes the audit trail (tbl_dtr_audit_trail)
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.dtr-del-btn');
    if (!btn) return;
    e.preventDefault();
    const ov = document.createElement('div');
    ov.className = 'dtr-msgbox-bd';
    ov.innerHTML = `<div class="dtr-msgbox dtr-msgbox-del" role="dialog" aria-modal="true">
        <div class="dtr-msgbox-ic del">!</div><h3>Delete this entry?</h3>
        <p class="ref">${escHtml(btn.dataset.ref || '')}</p>
        <label>Why is this data entry being deleted? <b>(required)</b></label>
        <textarea rows="3" maxlength="1000" placeholder="Reason for the audit trail..."></textarea>
        <div class="err" hidden></div>
        <div class="acts"><button type="button" class="dtr-btn dtr-btn-outline" data-x="cancel">Cancel</button><button type="button" class="dtr-btn dtr-btn-danger" data-x="ok">Delete</button></div>
      </div>`;
    document.body.appendChild(ov);
    const ta = ov.querySelector('textarea');
    const err = ov.querySelector('.err');
    ta.focus();
    ov.querySelector('[data-x=cancel]').addEventListener('click', () => ov.remove());
    ov.querySelector('[data-x=ok]').addEventListener('click', async () => {
      const reason = ta.value.trim();
      if (reason.length < 3) { err.textContent = 'Enter the reason for deleting this entry.'; err.hidden = false; ta.focus(); return; }
      const ok = ov.querySelector('[data-x=ok]');
      ok.disabled = true;
      try {
        const res = await apiFetch('dtr/delete-entry', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ kind: btn.dataset.del, id: Number(btn.dataset.id), reason }),
        });
        if (res && res.ok) {
          ov.remove();
          btn.closest('tr')?.remove();
          successBox(res.message, null, 'Entry deleted');
        } else {
          err.textContent = (res && res.message) || 'Delete failed.'; err.hidden = false; ok.disabled = false;
        }
      } catch (ex) {
        err.textContent = 'Delete failed: ' + (ex.message || ex); err.hidden = false; ok.disabled = false;
      }
    });
  });

  // Select multiple: a checkbox in the header of the Edit / Delete column selects every row of that list; each row has its own box.
  // The bar above the list then edits / saves / cancels / deletes all the selected rows at once.
  const selRows = (table) => [...table.querySelectorAll('tbody tr.dtr-row-picked')];
  const selBar = (table) => {
    const wrap = table.closest('.dtr-table-wrap') || table.parentNode;
    let bar = wrap.previousElementSibling?.classList?.contains('dtr-sel-bar') ? wrap.previousElementSibling : null;
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'dtr-sel-bar'; bar.hidden = true;
      bar.innerHTML = '<span class="n"></span><button type="button" data-sel="edit">Edit</button><button type="button" data-sel="save">Save</button><button type="button" data-sel="cancel">Cancel edit</button><button type="button" data-sel="del" class="danger">Delete</button><button type="button" data-sel="clear">Clear</button>';
      wrap.parentNode.insertBefore(bar, wrap);
    }
    return bar;
  };
  const refreshSel = (table) => {
    const rows = selRows(table);
    const all = table.querySelector('thead .dtr-sel-all');
    const mode = table.classList.contains('dtr-sel-mode');
    if (all) { all.checked = mode; all.indeterminate = false; }
    const bar = selBar(table);
    bar.hidden = !mode;
    bar.querySelector('.n').textContent = rows.length ? `${rows.length} selected` : 'Click the rows to select';
    const editing = rows.some((r) => r.querySelector('.dtr-edit-btn[data-mode="save"]'));
    bar.querySelector('[data-sel=save]').hidden = !editing;
    bar.querySelector('[data-sel=cancel]').hidden = !editing;
    bar.querySelector('[data-sel=edit]').hidden = !rows.some((r) => r.querySelector('.dtr-edit-btn') && !r.querySelector('.dtr-edit-btn[data-mode="save"]'));
  };
  window.dtrAddRowChecks = (root) => (root || document).querySelectorAll('.dtr-del-btn').forEach((d) => {
    if (d.closest('.ws-child-table')) return;
    const table = d.closest('table');
    const th = table?.querySelector('thead tr:last-child th:last-child');
    if (th && !th.querySelector('.dtr-sel-all')) {
      const a = document.createElement('input');
      a.type = 'checkbox'; a.className = 'dtr-sel-all'; a.title = 'Select all'; a.setAttribute('aria-label', 'Select all rows');
      th.appendChild(a);
    }
  });
  window.dtrAddRowChecks();
  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t.classList?.contains('dtr-sel-all')) {
      const table = t.closest('table');
      // the header box turns select mode on / off: while it is on, clicking a row highlights it (click again to un-highlight)
      table.classList.toggle('dtr-sel-mode', t.checked);
      if (!t.checked) table.querySelectorAll('tr.dtr-row-picked').forEach((r) => r.classList.remove('dtr-row-picked'));
      refreshSel(table);
    }
  });
  document.addEventListener('click', (e) => {
    if (e.target.classList?.contains('dtr-sel-all')) { e.stopPropagation(); return; }
    // select mode: a click on a row (outside its buttons / inputs) highlights or un-highlights it instead of opening it
    const row = e.target.closest('table.dtr-sel-mode tbody tr');
    if (row && row.querySelector('.dtr-del-btn') && !e.target.closest('button, input, textarea, select, a, label')) {
      e.preventDefault(); e.stopPropagation();
      row.classList.toggle('dtr-row-picked');
      refreshSel(row.closest('table'));
      return;
    }
    // select mode with highlighted rows: the Edit / Save / Cancel / Delete buttons of the column act on ALL highlighted rows
    const rb = e.target.closest('table.dtr-sel-mode .dtr-del-btn, table.dtr-sel-mode .dtr-edit-btn, table.dtr-sel-mode .dtr-cancel-btn');
    if (rb) {
      const tbl = rb.closest('table');
      if (selRows(tbl).length) {
        e.preventDefault(); e.stopPropagation();
        const act = rb.classList.contains('dtr-del-btn') ? 'del' : rb.classList.contains('dtr-cancel-btn') ? 'cancel' : (rb.dataset.mode === 'save' ? 'save' : 'edit');
        selBar(tbl).querySelector(`[data-sel=${act}]`)?.click();
        return;
      }
    }
    const b = e.target.closest('.dtr-sel-bar [data-sel]');
    if (!b) return;
    const bar = b.closest('.dtr-sel-bar');
    const table = bar.nextElementSibling?.querySelector('table') || (bar.nextElementSibling?.tagName === 'TABLE' ? bar.nextElementSibling : null);
    if (!table) return;
    const rows = selRows(table);
    const act = b.dataset.sel;
    if (act === 'clear') { table.querySelectorAll('tr.dtr-row-picked').forEach((r) => r.classList.remove('dtr-row-picked')); refreshSel(table); return; }
    if (act === 'edit') { rows.forEach((r) => { const eb = r.querySelector('.dtr-edit-btn'); if (eb && eb.dataset.mode !== 'save') eb.click(); }); refreshSel(table); return; }
    if (act === 'cancel') { rows.forEach((r) => r.querySelector('.dtr-cancel-btn')?.click()); refreshSel(table); return; }
    if (act === 'save') {
      (async () => {
        for (const r of rows) { const eb = r.querySelector('.dtr-edit-btn[data-mode="save"]'); if (eb) { eb.click(); await new Promise((ok) => setTimeout(ok, 450)); } }
        refreshSel(table);
      })();
      return;
    }
    if (act === 'del') {
      const dels = rows.map((r) => r.querySelector('.dtr-del-btn')).filter(Boolean);
      if (!dels.length) return;
      const ov = document.createElement('div');
      ov.className = 'dtr-msgbox-bd';
      ov.innerHTML = `<div class="dtr-msgbox dtr-msgbox-del" role="dialog" aria-modal="true">
          <div class="dtr-msgbox-ic del">!</div><h3>Delete ${dels.length} selected entr${dels.length === 1 ? 'y' : 'ies'}?</h3>
          <p class="ref">${escHtml(dels.slice(0, 6).map((d) => d.dataset.ref || '').join(', '))}${dels.length > 6 ? ' …' : ''}</p>
          <label>Why are these data entries being deleted? <b>(required)</b></label>
          <textarea rows="3" maxlength="1000" placeholder="Reason for the audit trail..."></textarea>
          <div class="err" hidden></div>
          <div class="acts"><button type="button" class="dtr-btn dtr-btn-outline" data-x="cancel">Cancel</button><button type="button" class="dtr-btn dtr-btn-danger" data-x="ok">Delete all</button></div>
        </div>`;
      document.body.appendChild(ov);
      const ta = ov.querySelector('textarea'), err = ov.querySelector('.err'), ok = ov.querySelector('[data-x=ok]');
      ta.focus();
      ov.querySelector('[data-x=cancel]').addEventListener('click', () => ov.remove());
      ok.addEventListener('click', async () => {
        const reason = ta.value.trim();
        if (reason.length < 3) { err.textContent = 'Enter the reason for deleting these entries.'; err.hidden = false; ta.focus(); return; }
        ok.disabled = true;
        let done = 0; const failed = [];
        for (const d of dels) {
          try {
            const res = await apiFetch('dtr/delete-entry', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ kind: d.dataset.del, id: Number(d.dataset.id), reason }) });
            if (res && res.ok) { d.closest('tr')?.remove(); done++; } else failed.push(`${d.dataset.ref || d.dataset.id}: ${(res && res.message) || 'failed'}`);
          } catch (ex) { failed.push(`${d.dataset.ref || d.dataset.id}: ${ex.message || ex}`); }
        }
        refreshSel(table);
        if (failed.length) { err.textContent = `Deleted ${done}. Not deleted: ${failed.join(' | ')}`; err.hidden = false; ok.disabled = false; return; }
        ov.remove();
        successBox(`${done} entr${done === 1 ? 'y' : 'ies'} deleted and moved to the delete audit.`, null, 'Entries deleted');
      });
    }
  }, true);



  // =========================================================================
  // Whole Shoe Intake (+ Whole Shoe Collected): adds shoes to tbl_dtr_shoe_list
  // =========================================================================
  const wsEl = { back: $('#dtr-wshoe-backdrop'), tbody: $('#dtr-wshoe-tbody'), msg: $('#dtr-wshoe-msg') };
  let wsLines = 0;
  let wsBuilding = 'A';
  const WS_TESTS = ['Heat Aging', 'Hydrolysis', 'Washing', 'UV', 'Flexing', 'Dip Test'];

  // A row for one line. The "+" beside the line number adds another row for that same line (extra = true, with a remove button).
  const wsRowHtml = (line, extra, rightSide) => `
      <tr data-line="${line}"${extra ? ' data-extra="1"' : ''}>
        <td><div class="dtr-side-toggle dtr-ws-type"><button type="button" data-wtype="DAILY" class="active">DAILY</button><button type="button" data-wtype="TRIAL">TRIAL</button></div></td>
        <td class="ik-linecell"><b>${line}</b> <button type="button" class="ik-addrow" title="Add another row for ${line}" aria-label="Add another row for ${line}">+</button>${extra ? '<button type="button" class="ik-delrow" title="Remove this row" aria-label="Remove this row">&times;</button>' : ''}</td>
        <td><div class="dtr-side-toggle"><button type="button" data-side="L" class="${rightSide ? '' : 'active'}">L</button><button type="button" data-side="R" class="${rightSide ? 'active' : ''}">R</button></div></td>
        <td class="ik-tlabref mono"></td>
        <td><input type="text" class="ik-code" placeholder="Type to filter" autocomplete="off"></td>
        <td><input type="text" class="ik-style" placeholder="Style" readonly tabindex="-1"></td>
        <td><input type="text" class="ik-color" placeholder="Colorway" readonly tabindex="-1"></td>
        <td><input type="text" class="ik-mtl" placeholder="MTL #" readonly tabindex="-1"></td>
        <td class="ik-imgcell"><img class="ik-img" alt="" hidden></td>
        <td><input type="number" class="ik-qty" min="1" step="1" value="1"></td>
        <td class="ik-tests">${WS_TESTS.map((t) => `<label class="ik-test"><input type="checkbox" value="${t}"><span>${t}</span></label>`).join('')}</td>
        <td class="ik-rmcell"><button type="button" class="ws-rmrow" title="Remove this row" aria-label="Remove this row">&times;</button></td>
      </tr>`;
  const addWholeShoeRow = () => {
    wsLines += 1;
    // sides alternate L / R down the lines, like the Specimen Intake
    wsEl.tbody.insertAdjacentHTML('beforeend', wsRowHtml(wsBuilding + wsLines, false, wsLines % 2 === 0));
  };  const renderWholeShoeRows = () => {
    wsEl.tbody.innerHTML = '';
    wsLines = 0;
    const count = wsBuilding === 'B' ? 14 : 12;
    for (let i = 0; i < count; i++) addWholeShoeRow();
    syncWsBuildingBox();
    refreshWholeShoeLabRefs();
  };
  // Every ticked test type gets its own Test Type Lab Ref No (the Whole Shoe Lab Ref No DTP + date + WH + line is made by the server and not shown): = DTP + date + test code + building + line + -L / -R side (e.g. DTP20261103AGA4-L)
  // Formats: DTP<date><code><line>-<side>-<#>  (UV: DTP<date>UV<line>-<#>, no side). The server confirms the free numbers on save.
  const WS_CODES = { 'Heat Aging': 'HA', Hydrolysis: 'HY', Washing: 'WG', UV: 'UV', Flexing: 'FX', 'Dip Test': 'DT' };
  // a TRIAL row has no building / line: its Lab Ref is DTP + date + test code + side
  const wsLineOf = (tr) => (tr.classList.contains('ws-trial') ? '' : tr.dataset.line);
  const syncWsBuildingBox = () => {   // the Bldg A / B choice only matters while a DAILY row is in the batch
    const box = $('#dtr-wshoe-building')?.closest('.dtr-intake-field');
    const rows = $$('#dtr-wshoe-tbody tr');
    if (box) box.style.visibility = rows.length && rows.every((r) => r.classList.contains('ws-trial')) ? 'hidden' : '';
  };
  const refreshWholeShoeLabRefs = () => {
    const d = ($('#dtr-wshoe-production')?.value || '').replace(/-/g, '');
    const seen = {};   // running number per code + line, whatever the side
    $$('#dtr-wshoe-tbody tr').forEach((tr) => {
      const tcell = tr.querySelector('.ik-tlabref');
      if (!d) { tcell.textContent = ''; return; }
      const side = tr.querySelector('.dtr-side-toggle button[data-side].active')?.dataset.side || 'L';
      if (!tr.querySelector('.ik-tests input:checked')) {   // no test ticked yet: show the format the Lab Ref will follow
        tcell.innerHTML = `<div class="ik-child" style="opacity:.55;font-style:italic">DTP${d}&lt;test&gt;${wsLineOf(tr)}-${side}</div>`;
        return;
      }
      tcell.innerHTML = [...tr.querySelectorAll('.ik-tests input:checked')].map((c) => {
        const code = WS_CODES[c.value] || '';
        // no running number (DTP<date><code><line>-<side>, UV has no side); a second row with the same Lab Ref gets -2, -3 ...
        const base = code === 'UV' ? `DTP${d}${code}${wsLineOf(tr)}` : `DTP${d}${code}${wsLineOf(tr)}-${side}`;
        seen[base] = (seen[base] || 0) + 1;
        return `<div class="ik-child">${seen[base] === 1 ? base : `${base}-${seen[base]}`}</div>`;
      }).join('');
    });
  };  const openWholeShoe = () => {
    wsBuilding = 'A';
    wsProdTouched = false;
    $('#dtr-wshoe-production').value = $('#dtr-wshoe-date').value;
    $$('#dtr-wshoe-building button').forEach((x) => x.classList.toggle('active', x.dataset.building === 'A'));
    renderWholeShoeRows();
    wsEl.msg.textContent = '';
    wsEl.back.classList.remove('hidden');
  };
  $$('#dtr-wshoe-building button').forEach((btn) => {
    btn.addEventListener('click', () => {
      wsBuilding = btn.dataset.building;
      $$('#dtr-wshoe-building button').forEach((x) => x.classList.toggle('active', x === btn));
      renderWholeShoeRows();
    });
  });
  // the Production Date follows the Collection Date until it is changed by hand
  let wsProdTouched = false;
  const syncWholeShoeDates = () => {
    if (!wsProdTouched) $('#dtr-wshoe-production').value = $('#dtr-wshoe-date').value;
    refreshWholeShoeLabRefs();
  };
  $('#dtr-wshoe-production')?.addEventListener('change', () => { wsProdTouched = true; refreshWholeShoeLabRefs(); });
  $('#dtr-wshoe-date')?.addEventListener('change', syncWholeShoeDates);
  const closeWholeShoe = () => { closeLibrary(); wsEl.back?.classList.add('hidden'); };

  $$('[data-add-record="shoes"]').forEach((btn) => btn.addEventListener('click', openWholeShoe));
  $('#btn-wshoe-close')?.addEventListener('click', closeWholeShoe);
  $('#btn-wshoe-cancel')?.addEventListener('click', closeWholeShoe);
  wsEl.tbody?.addEventListener('click', (e) => {
    const typeBtn = e.target.closest('.dtr-ws-type button');
    if (typeBtn) {
      const trT = typeBtn.closest('tr');
      trT.querySelectorAll('.dtr-ws-type button').forEach((x) => x.classList.toggle('active', x === typeBtn));
      trT.classList.toggle('ws-trial', typeBtn.dataset.wtype === 'TRIAL');
      syncWsBuildingBox();
      refreshWholeShoeLabRefs();
      return;
    }
    const sideBtn = e.target.closest('.dtr-side-toggle button[data-side]');
    if (sideBtn) {
      sideBtn.closest('tr').querySelectorAll('.dtr-side-toggle button[data-side]').forEach((x) => x.classList.toggle('active', x === sideBtn));
      refreshWholeShoeLabRefs();
      return;
    }
    const add = e.target.closest('.ik-addrow');
    if (add) {
      // new row goes after the last row of the same line
      let last = add.closest('tr');
      while (last.nextElementSibling && last.nextElementSibling.dataset.line === last.dataset.line) last = last.nextElementSibling;
      last.insertAdjacentHTML('afterend', wsRowHtml(last.dataset.line, true, false));
      refreshWholeShoeLabRefs();
      last.nextElementSibling.querySelector('.ik-code')?.focus();
      return;
    }
    const rm = e.target.closest('.ws-rmrow');
    if (rm) { rm.closest('tr').remove(); syncWsBuildingBox(); refreshWholeShoeLabRefs(); return; }
    const del = e.target.closest('.ik-delrow');
    if (del) { del.closest('tr').remove(); return; }
    const lib = e.target.closest('.ik-lib');
    if (lib) openLibrary(lib.closest('tr'), lib);
  });
  // Production Code box filters the ERP production orders, same as the Specimen Intake; style / colorway / MTL # come from the picked order
  wsEl.tbody?.addEventListener('input', (e) => {
    if (!e.target.classList.contains('ik-code')) return;
    const tr = e.target.closest('tr');
    ['.ik-style', '.ik-color', '.ik-mtl'].forEach((s) => { tr.querySelector(s).value = ''; });
    setIntakeImage(tr);
    suggestProdCode(tr, e.target);
  });
  wsEl.tbody?.addEventListener('focusin', (e) => {
    if (e.target.classList.contains('ik-code') && !libPanel) suggestProdCode(e.target.closest('tr'), e.target);
  });
  wsEl.tbody?.addEventListener('change', (e) => {
    if (e.target.closest('.ik-tests')) refreshWholeShoeLabRefs();
  });

  $('#btn-wshoe-save')?.addEventListener('click', async () => {
    const rows = [];
    $$('#dtr-wshoe-tbody tr').forEach((tr) => {
      const row = {
        type: tr.querySelector('.dtr-ws-type .active')?.dataset.wtype || 'DAILY',
        code: tr.querySelector('.ik-code').value.trim(),
        style: tr.querySelector('.ik-style').value.trim(),
        color: tr.querySelector('.ik-color').value.trim(),
        mtl: tr.querySelector('.ik-mtl').value.trim(),
        line: wsLineOf(tr),
        side: tr.querySelector('.dtr-side-toggle button[data-side].active')?.dataset.side || 'L',
        qty: Math.max(1, parseInt(tr.querySelector('.ik-qty').value, 10) || 1),
        tests: [...tr.querySelectorAll('.ik-tests input:checked')].map((c) => c.value),
      };
      if (row.code) rows.push(row);
    });
    if (!rows.length) { wsEl.msg.textContent = 'Enter a Production Code on at least one line.'; return; }
    const sb = savingBox('Saving batch...');
    closeWholeShoe();
    try {
      const res = await apiFetch('dtr/wholeshoe-save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          building: wsBuilding,
          collectionDate: $('#dtr-wshoe-date').value,
          productionDate: $('#dtr-wshoe-production').value,
          encodedBy: $('#dtr-wshoe-encoder').value.trim(),
          rows,
        }),
      });
      if (res && res.ok) {
        afterSave(res.message, 'shoes');
      } else {
        sb.fail(); wsEl.back?.classList.remove('hidden');
        wsEl.msg.textContent = (res && res.message) || 'Save failed.';
      }
    } catch (err) {
      sb.fail(); wsEl.back?.classList.remove('hidden');
      wsEl.msg.textContent = 'Save failed: ' + err.message;
    }
  });

  // =========================================================================
  // Material Receive (+ Material Receive): IQC Container Receiving Log
  // =========================================================================
  const mrBack = $('#dtr-mrec-backdrop');
  const mrBody = $('#dtr-mrec-tbody');
  const mrMsg = $('#dtr-mrec-msg');
  const mrCountBadge = $('#dtr-mrec-count-badge');
  const today = () => new Date().toISOString().slice(0, 10);
  const renumberMaterialRows = () => {
    const trs = [...(mrBody?.children || [])];
    trs.forEach((tr, i) => {
      const numEl = tr.querySelector('.mrec-row-num');
      if (numEl) numEl.textContent = String(i + 1);
    });
    if (mrCountBadge) mrCountBadge.textContent = `${trs.length} line${trs.length === 1 ? '' : 's'}`;
  };
  const addMaterialRow = () => {
    const idx = (mrBody?.children.length || 0) + 1;
    mrBody.insertAdjacentHTML('beforeend', `
      <tr>
        <td class="mrec-td-num"><span class="mrec-row-num">${idx}</span></td>
        <td><input type="text" class="ik-code mrec-in-lookup" placeholder="Search VSP / PO..." autocomplete="off"><input type="hidden" class="ik-style"></td>
        <td><input type="text" class="ik-color mrec-in-auto" placeholder="Auto-filled" readonly tabindex="-1"></td>
        <td><input type="text" class="ik-mtl mono mrec-in-auto" placeholder="Auto-filled" readonly tabindex="-1"></td>
        <td class="mrec-grp-sep"><input type="text" class="mr-code mono mrec-in-lookup" placeholder="Search code..." autocomplete="off"></td>
        <td><input type="text" class="mr-name" placeholder="Material name" autocomplete="off"></td>
        <td><input type="text" class="mr-type mrec-in-auto" placeholder="Auto-filled" readonly tabindex="-1"></td>
        <td><input type="text" class="mr-supplier" placeholder="Supplier"></td>
        <td class="mrec-grp-sep"><input type="text" class="mr-container" placeholder="Container #"></td>
        <td><input type="text" class="mr-batch" placeholder="Batch #"></td>
        <td><input type="text" class="mr-qty mrec-in-num" placeholder="0" inputmode="decimal"></td>
        <td><input type="text" class="mr-qcqty mrec-in-num" placeholder="0" inputmode="decimal"></td>
        <td><input type="text" class="mr-unit mrec-in-center" placeholder="Unit"></td>
        <td class="mrec-td-act"><button type="button" class="mrec-del-row" title="Remove or clear line" aria-label="Remove line">&times;</button></td>
      </tr>`);
    renumberMaterialRows();
  };
  mrBody?.addEventListener('click', (e) => {
    const delBtn = e.target.closest('.mrec-del-row');
    if (!delBtn) return;
    const tr = delBtn.closest('tr');
    if (!tr) return;
    if (mrBody.children.length > 1) {
      tr.remove();
      renumberMaterialRows();
    } else {
      tr.querySelectorAll('input:not([type="date"])').forEach((inp) => { inp.value = ''; });
    }
  });
  // after a Production Code is picked: the material of that ERP production order fills Name / Code / Supplier / Type / Unit.
  // One raw material = filled straight away; several = the list opens on Material Name to choose from. A line that already has a code is left alone.
  window.mrAutoMaterial = async (tr) => {
    if (tr.querySelector('.mr-code').value.trim()) return;
    try {
      const { items } = await fetchMaterials(tr, '');
      const raw = items.filter((i) => i.raw);
      const pool = raw.length ? raw : items;
      if (pool.length === 1) { fillMaterial(tr, pool[0]); mrMsg.textContent = ''; return; }
      if (pool.length > 1) {
        mrMsg.textContent = `${tr.querySelector('.ik-code').value.trim()} has ${pool.length} materials - pick the one received.`;
        tr.querySelector('.mr-code').focus();
      } else mrMsg.textContent = 'No material found for that Production Code in the ERP. Type the Material Code.';
    } catch (err) { /* the line stays as it is */ }
  };
  // the header "Date Arrived" is the default of every line (a line keeps its own date once it was changed)
  $('#dtr-mrec-arrived')?.addEventListener('change', (e) => { mrBody?.querySelectorAll('.mr-arrived').forEach((i) => { i.value = e.target.value; }); });
  // Production Code box = filter on tbl_dtr_shoe_list; the picked code fills Colorway / Style / MTL #, editing the code clears them
  mrBody?.addEventListener('input', (e) => {
    if (e.target.classList.contains('ik-code')) {
      const tr = e.target.closest('tr');
      ['.ik-style', '.ik-color', '.ik-mtl'].forEach((s) => { tr.querySelector(s).value = ''; });
      suggestProdCode(tr, e.target);
    } else if (e.target.classList.contains('mr-code')) {
      const tr = e.target.closest('tr');
      // typing in one of the two boxes clears the details that came from the other pick
      const other = ['.mr-name', '.mr-type', '.mr-supplier', '.mr-unit'];
      other.forEach((s) => { tr.querySelector(s).value = ''; });
      suggestMaterialCode(tr, e.target);
    }
  });
  mrBody?.addEventListener('focusin', (e) => {
    if (libPanel) return;
    if (e.target.classList.contains('ik-code')) suggestProdCode(e.target.closest('tr'), e.target);
    else if (e.target.classList.contains('mr-code')) suggestMaterialCode(e.target.closest('tr'), e.target);
  });
  // Material Code box: type or look up (tbl_dtr_material_type_status); the code fills Material Name + Supplier (tbl_dtr_material_list) and Material Type
  let mcTimer = null;
  const fillMaterial = (tr, it) => {
    tr.querySelector('.mr-code').value = it.code || '';
    tr.querySelector('.mr-name').value = it.name || '';
    tr.querySelector('.mr-type').value = it.type || '';
    tr.querySelector('.mr-supplier').value = it.supplier || '';
    tr.querySelector('.mr-unit').value = it.unit || '';
  };
  // with a Production Code on the line the materials come from that ERP production order (dberp.production_order_sub_materials);
  // without one, from the lab material lists
  const fetchMaterials = async (tr, q) => {
    const prod = tr.querySelector('.ik-code').value.trim();
    if (prod) {
      const res = await apiFetch(`dtr/erp-prod-materials?code=${encodeURIComponent(prod)}&q=${encodeURIComponent(q)}`);
      return { items: (res && res.items) || [], erp: true, prod };
    }
    const res = await apiFetch(`dtr/material-code-search?q=${encodeURIComponent(q)}`);
    return { items: (res && res.items) || [], erp: false, prod: '' };
  };
  const suggestMaterialCode = (tr, input) => {
    clearTimeout(mcTimer);
    mcTimer = setTimeout(async () => {
      closeLibrary();
      const rect = input.getBoundingClientRect();
      libPanel = document.createElement('div');
      libPanel.className = 'dtr-lib-panel';
      libPanel.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - 540)) + 'px';
      libPanel.style.top = Math.min(rect.bottom + 2, window.innerHeight - 300) + 'px';
      libPanel.innerHTML = '<div class="lib-list"><div class="lib-empty">Searching...</div></div>';
      document.body.appendChild(libPanel);
      const panel = libPanel;
      const list = panel.querySelector('.lib-list');
      try {
        const { items, erp, prod } = await fetchMaterials(tr, input.value.trim());
        if (!items.length) { list.innerHTML = `<div class="lib-empty">${erp ? 'No material of ' + escHtml(prod) + ' found in the ERP production order.' : 'No material code found.'} The typed value is kept.</div>`; return; }
        list.innerHTML = items.map((it, i) => `<div class="lib-item" data-i="${i}"><b>${escHtml(it.code)}</b><span>${escHtml(it.name)}</span><span>${escHtml(it.type)}${it.supplier ? ' - ' + escHtml(it.supplier) : ''}${it.unit ? ' · ' + escHtml(it.unit) : ''}</span></div>`).join('');
        list.onclick = (ev) => {
          const item = ev.target.closest('.lib-item');
          if (!item) return;
          fillMaterial(tr, items[Number(item.dataset.i)]);
          closeLibrary();
        };
      } catch (err) {
        list.innerHTML = '<div class="lib-empty">Material lookup failed (' + escHtml(String(err.message || err)) + ').</div>';
      }
    }, 250);
  };
  // a full code typed and left: taken straight from the lists when it matches one exactly
  mrBody?.addEventListener('change', async (e) => {
    if (!e.target.classList.contains('mr-code') || !e.target.value.trim()) return;
    const tr = e.target.closest('tr');
    try {
      const { items } = await fetchMaterials(tr, e.target.value.trim());
      const hit = items.find((it) => it.code.toUpperCase() === e.target.value.trim().toUpperCase());
      if (hit) fillMaterial(tr, hit);
    } catch (err) { /* the typed code stays */ }
  });
  const openMaterialReceive = () => {
    mrBody.innerHTML = '';
    $('#dtr-mrec-date').value = today(); $('#dtr-mrec-arrived').value = today();
    for (let i = 0; i < 4; i++) addMaterialRow();
    mrMsg.textContent = '';
    mrBack.classList.remove('hidden');
  };
  const closeMaterialReceive = () => { closeLibrary(); mrBack.classList.add('hidden'); };
  $$('[data-add-record="containers"]').forEach((b) => b.addEventListener('click', openMaterialReceive));
  $('#btn-mrec-addrow')?.addEventListener('click', addMaterialRow);
  $('#btn-mrec-addrow-foot')?.addEventListener('click', addMaterialRow);
  $('#btn-mrec-close')?.addEventListener('click', closeMaterialReceive);
  $('#btn-mrec-cancel')?.addEventListener('click', closeMaterialReceive);
  $('#btn-mrec-save')?.addEventListener('click', async () => {
    const v = (tr, c) => tr.querySelector(c)?.value.trim() || '';
    const rows = [...mrBody.children].map((tr) => ({
      date: $('#dtr-mrec-date').value, arrived: $('#dtr-mrec-arrived').value, unit: v(tr, '.mr-unit'), prod: v(tr, '.ik-code'), color: v(tr, '.ik-color'), style: v(tr, '.ik-style'), mtl: v(tr, '.ik-mtl'),
      code: v(tr, '.mr-code'), name: v(tr, '.mr-name'), type: v(tr, '.mr-type'), supplier: v(tr, '.mr-supplier'),
      container: v(tr, '.mr-container'), batch: v(tr, '.mr-batch'), qty: v(tr, '.mr-qty'), qcqty: v(tr, '.mr-qcqty'),
    })).filter((r) => r.code || r.prod || r.container || r.batch);
    if (!rows.length) { mrMsg.textContent = 'Enter a Material Code on at least one line.'; return; }
    if (rows.some((r) => !r.code)) { mrMsg.textContent = 'Every line needs a Material Code.'; return; }
    const sb = savingBox('Saving...');
    closeMaterialReceive();
    try {
      const res = await apiFetch('dtr/material-receive-save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ encodedBy: $('#dtr-mrec-encoder').value.trim(), rows }),
      });
      if (res && res.ok) {
        afterSave(res.message, 'containers');
      } else {
        sb.fail(); mrBack.classList.remove('hidden');
        mrMsg.textContent = (res && res.message) || 'Save failed.';
      }
    } catch (err) { sb.fail(); mrBack.classList.remove('hidden'); mrMsg.textContent = 'Save failed: ' + err.message; }
  });

  // =========================================================================
  // Whole Shoe report page (Whole Footwear Test Results, Form # Lab VF 002)
  // =========================================================================
  const wsPanel = $('#dtr-wholeshoe-panel-guard') || $('#dtr-panel-wholeshoe');
  if (wsPanel) {
    const wsSel = $('#ws-labref');
    const wsMsg = $('#ws-msg');
    let wsSections = [];
    const wsVal = (id) => $(id).value.trim();

    // pulled = the tests of the selected Lab Ref from tbl_dtr_WholeshoeTracker (result, status, dates, findings per test type);
    // a value typed and saved in the report wins, the pulled finding fills the rest
    const wsRenderSections = (sections, data, pulled = []) => {
      wsSections = sections;
      const pb = {};
      pulled.forEach((p) => { pb[p.section] = p; });
      const chip = (p) => {
        if (!p) return '';
        const r = (p.result || '').toUpperCase();
        const cls = r.startsWith('FAIL') ? 'res-fail' : (r.startsWith('TOL') ? 'res-tol' : (r.startsWith('PASS') ? 'res-pass' : ''));
        const when = (p.completed || p.started || '').slice(0, 10);
        return ` <span class="wsf-pr ${cls}">${escHtml(r || p.status || 'PENDING')}</span> <b class="wsf-pl">${escHtml(p.labRef || '')}</b> <span class="wsf-ps">${escHtml([p.status, when].filter(Boolean).join(' · '))}</span>`;   // the child Lab Ref of the test sits beside its result
      };
      $('#ws-sections').innerHTML = sections.map((s) => `
        <div class="ws-sec" data-sec="${s.id}">
          <div class="wsf-st">${escHtml(s.title)}</div>
          <table class="wsf-t wsf-sc">
            <tr><td class="wsf-tc"><b>Test conditions测试条件:</b></td><td><b>Result结果 :</b>${chip(pb[s.id])}</td></tr>
            <tr><td class="wsf-cd">${s.cond}</td><td class="wsf-rs">${Object.entries(s.fields).map(([k, label]) =>
              `<div class="wsf-rl"><b>${escHtml(label)} :</b><input type="text" data-k="${k}" value="${escHtml((((data.results || {})[s.id] || {})[k]) || ((pb[s.id] || {}).fields || {})[k] || '')}"></div>`).join('')}</td></tr>
          </table>
        </div>`).join('');
    };

    const wsLoad = async (ref) => {
      wsMsg.textContent = '';
      if (!ref) return;
      try {
        const j = await apiFetch('dtr/wholeshoe-report?lab_ref=' + encodeURIComponent(ref));
        const i = j.info; const d = j.data || {};
        $('#ws-style').value = i.style; $('#ws-color').value = i.color; $('#ws-po').value = i.prodCode;
        $('#ws-qty').value = i.qty; $('#ws-received').value = i.received; $('#ws-tests').value = (i.tests || []).join(', ') || 'All';
        // Tested / Finished dates default to the tracker: first test started, last test completed (only when every test is done)
        const pulled = j.pulled || [];
        const starts = pulled.map((p) => (p.started || '').slice(0, 10)).filter(Boolean).sort();
        const ends = pulled.map((p) => (p.completed || '').slice(0, 10));
        $('#ws-tested').value = d.testedDate || starts[0] || '';
        $('#ws-finished').value = d.finishedDate || (ends.length && ends.every(Boolean) ? ends.sort().pop() : '');
        $('#ws-tech').value = d.technician || ''; $('#ws-comments').value = d.comments || '';
        $('#ws-reviewed').value = d.reviewedBy || ''; $('#ws-approved').value = d.approvedBy || '';
        wsRenderSections(j.sections || [], d, pulled);
      } catch (err) { wsMsg.textContent = err.message; }
    };

    const wsCollect = () => {
      const results = {};
      $$('#ws-sections .ws-sec').forEach((sec) => {
        results[sec.dataset.sec] = {};
        sec.querySelectorAll('input[data-k]').forEach((inp) => { results[sec.dataset.sec][inp.dataset.k] = inp.value.trim(); });
      });
      return {
        testedDate: wsVal('#ws-tested'), finishedDate: wsVal('#ws-finished'), technician: wsVal('#ws-tech'),
        reviewedBy: wsVal('#ws-reviewed'), approvedBy: wsVal('#ws-approved'), comments: wsVal('#ws-comments'), results,
      };
    };

    const wsSave = async () => {
      const ref = wsSel.value;
      if (!ref) { wsMsg.textContent = 'Choose a Lab Ref No first.'; return false; }
      try {
        const res = await apiFetch('dtr/wholeshoe-report-save', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ lab_ref: ref, data: wsCollect() }),
        });
        if (res && res.ok) { wsMsg.textContent = res.message; return true; }
        wsMsg.textContent = (res && res.message) || 'Save failed.';
      } catch (err) { wsMsg.textContent = 'Save failed: ' + err.message; }
      return false;
    };

    $('#btn-ws-save')?.addEventListener('click', async () => { if (await wsSave()) showToast('✓ ' + wsMsg.textContent); });
    $('#btn-ws-preview')?.addEventListener('click', async () => {
      if (!wsSel.value) { wsMsg.textContent = 'Choose a Lab Ref No first.'; return; }
      if (await wsSave()) window.open(BASE_URL + 'dtr/wholeshoe-report-pdf?inline=1&lab_ref=' + encodeURIComponent(wsSel.value), '_blank');
    });
    wsSel.addEventListener('change', () => wsLoad(wsSel.value));

    (async () => {
      try {
        // the Lab Ref is not editable: it is the one opened from the Lab Tracker lists (or ?labref=); the sheet is blank until then
        const j = await apiFetch('dtr/wholeshoe-labrefs');
        if (!wsSel.value) wsRenderSections(j.sections || [], {});   // blank sheet with every test section
        const qs = new URLSearchParams(location.search);
        const want = qs.get('labref') || (qs.get('open') === 'wholeshoe' ? qs.get('ref') : '');
        if (want && !wsSel.value) { wsSel.value = want; wsLoad(want); }
      } catch (err) { wsMsg.textContent = 'Could not load the report form.'; }
    })();
  }

  // Download icon before the LabRefNo (Bonding Shoe Collection): Bonding Test-VF machine-result PDF
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-dl-labref]');
    if (!b || b.disabled) return;
    b.disabled = true;
    try {
      const ref = b.dataset.dlLabref;
      const res = await fetch(BASE_URL + (b.dataset.dlRoute || 'dtr/bonding-machine-pdf') + '?lab_ref=' + encodeURIComponent(ref), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error((await res.text()).slice(0, 160) || 'Download failed');
      const blob = await res.blob();
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = ref.replace(/[^A-Za-z0-9._-]+/g, '_') + '_' + (b.dataset.dlSuffix || 'Bonding-Test-VF') + '.pdf';
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 4000);
    } catch (err) { nxToast('Could not download the report: ' + err.message, 'error', 7000); }
    b.disabled = false;
  });

  // =========================================================================
  // Bonding report picture (D:\BACK UP\SystemProcess\BondingImages): thumbnail in the list, click = editor.
  // Circle / highlight / text box (white box to write a value over the old one) / PASS-FAILED-TOLERABLE stamp; every item can be
  // moved and resized. Save = new current report <LabRef>.png; the report in place becomes the next old version (_1, _2 ...).
  // =========================================================================
  // a thumbnail that fails is asked again (twice): when a page loads 100 pictures at once, some requests fail on the busy session file, not because the picture is missing
  window.bsThumbErr = (img) => {
    const n = Number(img.dataset.retry || 0);
    if (n >= 1) { img.parentNode.classList.add('none'); return; }   // one retry: the server now remembers a missing picture, so asking again rarely helps
    img.dataset.retry = String(n + 1);
    const base = img.src.replace(/([?&])_r=\d+&?/, '$1').replace(/[?&]$/, '');
    setTimeout(() => { img.src = base + (base.includes('?') ? '&' : '?') + '_r=' + (n + 1); }, 600 * (n + 1) + Math.floor(Math.random() * 400));
  };
  (() => {
    const back = $('#bimg-backdrop');
    const cv = $('#bimg-canvas');
    if (!back || !cv) return;
    const ctx = cv.getContext('2d');
    const baseSampleCv = document.createElement('canvas');
    const baseSampleCtx = baseSampleCv.getContext('2d', { willReadFrequently: true });
    const txt = $('#bimg-text');
    const REPORT_FONT = '"Segoe UI", "Segoe UI Variable", Calibri, Arial, sans-serif';   // the font the machine report is printed in
    const STAMPS = { PASS: 'pass.png', FAILED: 'failed.png', TOLERABLE: 'tolerable.png' };
    const stampImgs = {};
    let base = null; let labRef = ''; let tool = 'circle'; let objs = []; let sel = -1; let drag = null; let temp = null; let editing = -1;
    let txtBold = false; let txtAlign = 'center'; let copiedFmt = null;
    const msg = (t) => { $('#bimg-msg').textContent = t || ''; };
    const loadImg = (src) => new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = src; });
    const stampImg = async (k) => stampImgs[k] || (stampImgs[k] = await loadImg(BASE_URL + 'assets/images/stamps/' + STAMPS[k]));
    const lw = () => Math.max(3, cv.width / 260);
    const hs = () => Math.max(16, cv.width / 60);   // size of the resize handle (canvas pixels)
    const toHex = (r, g, b) => '#' + [r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('');
    const sampleReportColor = (px, py) => {
      if (!baseSampleCv.width || !baseSampleCv.height) return '#000000';
      const x0 = Math.max(0, Math.min(baseSampleCv.width - 1, Math.round(px)));
      const y0 = Math.max(0, Math.min(baseSampleCv.height - 1, Math.round(py)));
      const d0 = baseSampleCtx.getImageData(x0, y0, 1, 1).data;
      if (d0[0] < 235 || d0[1] < 235 || d0[2] < 235) return toHex(d0[0], d0[1], d0[2]);
      // Near thin report strokes, find the strongest ink pixel in a 5x5 neighborhood
      let best = null; let bestScore = 0;
      const sx = Math.max(0, x0 - 2); const sy = Math.max(0, y0 - 2);
      const sw = Math.min(baseSampleCv.width - sx, 5); const sh = Math.min(baseSampleCv.height - sy, 5);
      const blk = baseSampleCtx.getImageData(sx, sy, sw, sh).data;
      for (let i = 0; i < blk.length; i += 4) {
        const r = blk[i], g = blk[i + 1], b = blk[i + 2];
        const score = (255 - r) + (255 - g) + (255 - b);
        if (Math.min(r, g, b) < 225 && score > bestScore) { bestScore = score; best = [r, g, b]; }
      }
      return best ? toHex(best[0], best[1], best[2]) : toHex(d0[0], d0[1], d0[2]);
    };

    const box = (o) => {
      if (o.t === 'circle') return { l: o.x - o.rx, t: o.y - o.ry, r: o.x + o.rx, b: o.y + o.ry };
      if (o.t === 'stamp') return { l: o.x - o.w / 2, t: o.y - o.h / 2, r: o.x + o.w / 2, b: o.y + o.h / 2 };
      return { l: o.x, t: o.y, r: o.x + o.w, b: o.y + o.h };
    };
    const fontPx = (o) => {
      let f = Math.max(12, o.h * 0.62);
      const wt = o.bold ? '700' : '400';
      ctx.save(); ctx.font = `${wt} ${f}px ${o.font || REPORT_FONT}`;
      const w = ctx.measureText(o.text || '').width;
      if (w > o.w - 10) f = Math.max(8, f * (o.w - 10) / w);
      ctx.restore();
      return f;
    };
    const drawObj = (o, selected) => {
      ctx.save();
      if (o.t === 'circle') {
        ctx.strokeStyle = '#2563eb'; ctx.lineWidth = lw();
        ctx.beginPath(); ctx.ellipse(o.x, o.y, Math.max(1, o.rx), Math.max(1, o.ry), 0, 0, Math.PI * 2); ctx.stroke();
      } else if (o.t === 'mark') {
        ctx.globalCompositeOperation = 'multiply'; ctx.fillStyle = 'rgba(250, 204, 21, .55)';
        ctx.fillRect(o.x, o.y, o.w, o.h);
      } else if (o.t === 'text') {
        ctx.fillStyle = '#fff'; ctx.fillRect(o.x, o.y, o.w, o.h);
        if (o.text) {
          const wt = o.bold ? '700' : '400';
          const al = o.align === 'left' ? 'left' : 'center';
          ctx.fillStyle = o.color || '#000'; ctx.font = `${wt} ${fontPx(o)}px ${o.font || REPORT_FONT}`; ctx.textBaseline = 'middle'; ctx.textAlign = al;
          const tx = al === 'left' ? o.x + 5 : o.x + o.w / 2;
          ctx.fillText(o.text, tx, o.y + o.h / 2 + 1, Math.max(8, o.w - 10));
        }
      } else if (o.t === 'stamp') {
        ctx.globalCompositeOperation = 'multiply';   // the white of the stamp picture disappears over the report
        ctx.drawImage(stampImgs[o.k], o.x - o.w / 2, o.y - o.h / 2, o.w, o.h);
      }
      ctx.restore();
      if (selected) {
        const b = box(o);
        ctx.save(); ctx.setLineDash([10, 8]); ctx.strokeStyle = '#2563eb'; ctx.lineWidth = lw() * .6;
        ctx.strokeRect(b.l, b.t, b.r - b.l, b.b - b.t);
        ctx.setLineDash([]); ctx.fillStyle = '#2563eb'; ctx.fillRect(b.r - hs() / 2, b.b - hs() / 2, hs(), hs());
        ctx.restore();
      }
    };
    const redraw = () => {
      if (!base) return;
      ctx.clearRect(0, 0, cv.width, cv.height);
      ctx.drawImage(base, 0, 0);
      objs.forEach((o, i) => drawObj(o, i === sel));
      if (temp) drawObj(temp, false);
    };
    const pt = (e) => { const r = cv.getBoundingClientRect(); return { x: (e.clientX - r.left) * cv.width / r.width, y: (e.clientY - r.top) * cv.height / r.height }; };
    const inHandle = (o, p) => { const b = box(o); return Math.abs(p.x - b.r) <= hs() && Math.abs(p.y - b.b) <= hs(); };
    const inBody = (o, p) => {
      if (o.t === 'circle') { const dx = (p.x - o.x) / Math.max(1, o.rx); const dy = (p.y - o.y) / Math.max(1, o.ry); return dx * dx + dy * dy <= 1.2; }
      const b = box(o); return p.x >= b.l && p.x <= b.r && p.y >= b.t && p.y <= b.b;
    };
    const setTool = (t) => { tool = t; $$('.bimg-tool').forEach((b) => b.classList.toggle('active', b.dataset.tool === t)); cv.style.cursor = t === 'move' ? 'move' : (t === 'eyedropper' ? 'copy' : 'crosshair'); };

    // zoom: the canvas keeps its pixel size, only its displayed size changes (mouse maths uses the displayed size)
    const stage = $('.bimg-stage');
    let scale = 1;
    const applyScale = () => {
      if (!base) return;
      cv.style.width = Math.round(cv.width * scale) + 'px';
      cv.style.height = Math.round(cv.height * scale) + 'px';
      $('#bimg-zlbl').textContent = Math.round(scale * 100) + '%';
    };
    const fitScale = () => Math.max(0.1, Math.min(1, (stage.clientWidth - 24) / cv.width, (stage.clientHeight - 24) / cv.height));
    const setScale = (s, cx, cy) => {
      if (!base) return;
      stopEdit(true);
      const old = scale; scale = Math.max(0.15, Math.min(5, s));
      // keep the point under the cursor (or the stage centre) where it is
      const r = stage.getBoundingClientRect();
      const ax = (cx === undefined ? r.left + r.width / 2 : cx) - r.left; const ay = (cy === undefined ? r.top + r.height / 2 : cy) - r.top;
      const px = (stage.scrollLeft + ax) / old; const py = (stage.scrollTop + ay) / old;
      applyScale();
      stage.scrollLeft = px * scale - ax; stage.scrollTop = py * scale - ay;
    };
    $('#bimg-zin').addEventListener('click', () => setScale(scale * 1.25));
    $('#bimg-zout').addEventListener('click', () => setScale(scale / 1.25));
    $('#bimg-fit').addEventListener('click', () => { setScale(fitScale()); stage.scrollTo(0, 0); });
    $('#bimg-z100').addEventListener('click', () => setScale(1));
    stage.addEventListener('wheel', (e) => {
      if (!e.ctrlKey && !e.metaKey) return;
      e.preventDefault();
      setScale(scale * (e.deltaY < 0 ? 1.12 : 1 / 1.12), e.clientX, e.clientY);
    }, { passive: false });

    // text box editing: an input laid exactly over the box
    const stopEdit = (keep = true) => {
      if (editing < 0) return;
      const o = objs[editing];
      if (o) { o.text = txt.value; if (!keep || (o.t === 'text' && !o.text.trim())) { objs.splice(editing, 1); sel = -1; } }
      editing = -1; txt.classList.add('hidden'); redraw();
    };
    const syncFontUi = (src) => {
      if (src.font !== undefined) $('#bimg-font').value = src.font || REPORT_FONT;
      if (src.color !== undefined) $('#bimg-color').value = (src.color || '#000000').toLowerCase();
      if (src.bold !== undefined) txtBold = !!src.bold;
      if (src.align !== undefined) txtAlign = src.align === 'left' ? 'left' : 'center';
      const boldBtn = $('#bimg-bold'); if (boldBtn) boldBtn.classList.toggle('active', txtBold);
      const alignBtn = $('#bimg-align');
      if (alignBtn) {
        alignBtn.dataset.align = txtAlign;
        alignBtn.textContent = txtAlign === 'left' ? 'Align: Left' : 'Align: Center';
      }
      const curCol = ($('#bimg-color').value || '#000000').toLowerCase();
      $$('.bimg-swatch').forEach((s) => s.classList.toggle('active', (s.dataset.color || '').toLowerCase() === curCol));
    };
    const startEdit = (i) => {
      const o = objs[i]; if (!o || o.t !== 'text') return;
      editing = i; sel = i; syncFontUi(o); redraw();
      const r = cv.getBoundingClientRect(); const k = r.width / cv.width;
      txt.style.left = (o.x * k) + 'px'; txt.style.top = (o.y * k) + 'px';
      txt.style.width = (o.w * k) + 'px'; txt.style.height = (o.h * k) + 'px';
      txt.style.fontSize = (fontPx(o) * k) + 'px'; txt.style.fontFamily = o.font || REPORT_FONT; txt.style.color = o.color || '#000';
      txt.style.fontWeight = o.bold ? '700' : '400';
      txt.style.textAlign = o.align === 'left' ? 'left' : 'center';
      txt.style.padding = o.align === 'left' ? '0 5px' : '0 4px';
      txt.value = o.text || ''; txt.classList.remove('hidden'); txt.focus(); txt.select();
    };
    txt.addEventListener('input', () => { const o = objs[editing]; if (o) o.text = txt.value; });
    txt.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); stopEdit(true); }
      if (e.key === 'Escape') { e.stopPropagation(); stopEdit(true); }
    });
    txt.addEventListener('blur', () => stopEdit(true));

    const load = async () => {
      msg('Loading...');
      try {
        base = await loadImg(`${BASE_URL}dtr/bonding-image?tab=${imgTab}&lab_ref=${encodeURIComponent(labRef)}&t=${Date.now()}`);
        cv.width = base.naturalWidth; cv.height = base.naturalHeight;
        baseSampleCv.width = base.naturalWidth; baseSampleCv.height = base.naturalHeight;
        baseSampleCtx.clearRect(0, 0, baseSampleCv.width, baseSampleCv.height);
        baseSampleCtx.drawImage(base, 0, 0);
        objs = []; sel = -1; temp = null; editing = -1; txt.classList.add('hidden'); redraw(); msg('');
        scale = fitScale(); applyScale(); stage.scrollTo(0, 0);
      } catch (e) { msg('No report picture found for this Lab Ref.'); base = null; }
    };
    let imgTab = 'bonding';
    const open = (ref, tab) => { labRef = ref; imgTab = tab || 'bonding'; $('#bimg-title').textContent = 'Report - ' + ref; setTool('circle'); syncFontUi({ color: $('#bimg-color').value, bold: txtBold, align: txtAlign }); back.classList.remove('hidden'); load(); };
    const close = () => { stopEdit(true); back.classList.add('hidden'); base = null; };


    // Hover over a report picture (or a shoe photo) of a list: a floating, larger preview follows the mouse. The small picture is shown at once,
    // the full picture replaces it when it has loaded. Leaving the picture (or scrolling / pressing a key) closes it.
    (() => {
      let box = null; let img = null; let cap = null; let timer = 0; let cur = null; let token = 0;
      const ensure = () => {
        if (box) return;
        box = document.createElement('div'); box.id = 'nx-hover-prev'; box.setAttribute('aria-hidden', 'true'); box.hidden = true;
        img = document.createElement('img'); img.alt = ''; cap = document.createElement('div'); cap.className = 'nx-hp-cap';
        box.append(img, cap); document.body.appendChild(box);
      };
      const place = (x, y) => {
        if (!box || box.hidden) return;
        const m = 16; const w = box.offsetWidth; const h = box.offsetHeight; const vw = window.innerWidth; const vh = window.innerHeight;
        let left = x + 22; if (left + w + m > vw) left = Math.max(m, x - w - 22);
        let top = y - h / 2; top = Math.min(Math.max(m, top), Math.max(m, vh - h - m));
        box.style.left = left + 'px'; box.style.top = top + 'px';
      };
      const hide = () => { clearTimeout(timer); token += 1; cur = null; if (box) { box.hidden = true; img.removeAttribute('src'); } };
      const fullSrc = (src) => src.replace(/([?&])thumb=1&?/, '$1').replace(/[?&]$/, '');
      document.addEventListener('mouseover', (e) => {
        const t = e.target.closest && e.target.closest('.bs-thumb, .dtr-prod-thumb');
        if (!t || t === cur) return;
        const wrap = t.closest('.bs-thumb-wrap');
        if (wrap && wrap.classList.contains('none')) return;        // no picture for this Lab Ref
        hide(); cur = t; const my = ++token; const mx = e.clientX; const mY = e.clientY;
        timer = setTimeout(() => {
          if (my !== token) return;
          ensure();
          const small = t.currentSrc || t.src; if (!small) return;
          img.src = small; cap.textContent = t.dataset.labRef || t.getAttribute('alt') || '';
          cap.hidden = !cap.textContent; box.hidden = false; place(mx, mY);
          const big = t.classList.contains('bs-thumb') ? fullSrc(small) : small;
          if (big !== small) { const pre = new Image(); pre.onload = () => { if (my === token) { img.src = big; place(mx, mY); } }; pre.src = big; }
        }, 140);
      });
      document.addEventListener('mousemove', (e) => { if (cur && box && !box.hidden) place(e.clientX, e.clientY); });
      document.addEventListener('mouseout', (e) => { const t = e.target.closest && e.target.closest('.bs-thumb, .dtr-prod-thumb'); if (t && t === cur && !(e.relatedTarget && t.contains(e.relatedTarget))) hide(); });
      window.addEventListener('scroll', hide, true);
      document.addEventListener('keydown', hide);
      document.addEventListener('click', hide, true);
    })();
    document.addEventListener('click', (e) => {
      const t = e.target.closest('.bs-thumb');
      if (t && t.dataset.labRef) { e.preventDefault(); open(t.dataset.labRef, t.dataset.tab); }
    });
    $('#bimg-close').addEventListener('click', close);
    document.addEventListener('keydown', (e) => {
      if (back.classList.contains('hidden')) return;
      if (e.key === 'Escape') close();
      if ((e.key === 'Delete' || e.key === 'Backspace') && sel >= 0 && editing < 0 && !/INPUT|TEXTAREA/.test(document.activeElement.tagName)) { objs.splice(sel, 1); sel = -1; redraw(); }
    });
    $$('.bimg-tool').forEach((b) => b.addEventListener('click', () => { stopEdit(true); setTool(b.dataset.tool); }));
    $$('.bimg-stamp').forEach((b) => b.addEventListener('click', async () => {
      if (!base) return;
      stopEdit(true);
      const k = b.dataset.stamp; const im = await stampImg(k);
      const w = cv.width * (Number($('#bimg-size').value) / 100);
      objs.push({ t: 'stamp', k, x: cv.width / 2, y: cv.height / 2, w, h: w * im.naturalHeight / im.naturalWidth });
      sel = objs.length - 1; setTool('move'); redraw();
    }));
    $('#bimg-size').addEventListener('input', (e) => {
      const o = objs[sel]; if (!o || o.t !== 'stamp') return;
      const ratio = o.h / o.w; o.w = cv.width * (Number(e.target.value) / 100); o.h = o.w * ratio; redraw();
    });
    // font style / colour / bold / align / format copy-paste: applied to the selected text box, and used for the next ones
    const applyFont = () => {
      syncFontUi({ color: $('#bimg-color').value });
      const o = objs[sel]; if (!o || o.t !== 'text') return;
      o.font = $('#bimg-font').value; o.color = $('#bimg-color').value; o.bold = txtBold; o.align = txtAlign;
      if (editing === sel) {
        txt.style.fontFamily = o.font; txt.style.color = o.color;
        txt.style.fontWeight = o.bold ? '700' : '400';
        txt.style.textAlign = o.align === 'left' ? 'left' : 'center';
      }
      redraw();
    };
    $('#bimg-font').addEventListener('change', applyFont);
    $('#bimg-color').addEventListener('input', applyFont);
    $$('.bimg-swatch').forEach((b) => b.addEventListener('click', () => {
      $('#bimg-color').value = b.dataset.color || '#000000';
      applyFont();
      msg('Selected colour ' + $('#bimg-color').value);
    }));
    $('#bimg-bold')?.addEventListener('click', () => { txtBold = !txtBold; syncFontUi({ bold: txtBold }); applyFont(); });
    $('#bimg-align')?.addEventListener('click', () => { txtAlign = txtAlign === 'left' ? 'center' : 'left'; syncFontUi({ align: txtAlign }); applyFont(); });
    $('#bimg-copyfmt')?.addEventListener('click', () => {
      const o = sel >= 0 && objs[sel]?.t === 'text' ? objs[sel] : null;
      copiedFmt = {
        font: o ? (o.font || REPORT_FONT) : $('#bimg-font').value,
        color: o ? (o.color || '#000000') : $('#bimg-color').value,
        bold: o ? !!o.bold : txtBold,
        align: o ? (o.align === 'left' ? 'left' : 'center') : txtAlign,
      };
      syncFontUi(copiedFmt);
      const pBtn = $('#bimg-pastefmt'); if (pBtn) pBtn.disabled = false;
      msg('Format copied (' + copiedFmt.color + (copiedFmt.bold ? ', Bold' : '') + ') — next text box uses it, or select one & click Apply Format');
    });
    $('#bimg-pastefmt')?.addEventListener('click', () => {
      if (!copiedFmt) return;
      syncFontUi(copiedFmt);
      applyFont();
      if (sel >= 0 && objs[sel]?.t === 'text') msg('Copied format applied to selected text box.');
      else msg('Copied format set for next text box.');
    });
    // hamburger: all editing tools live in this menu
    const menu = $('#bimg-menu');
    $('#bimg-burger').addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('hidden'); });
    menu.addEventListener('click', (e) => e.stopPropagation());
    document.addEventListener('click', (e) => { if (!menu.classList.contains('hidden') && !e.target.closest('#bimg-burger')) menu.classList.add('hidden'); });
    $('#bimg-del').addEventListener('click', () => { stopEdit(true); if (sel >= 0) { objs.splice(sel, 1); sel = -1; redraw(); } });
    $('#bimg-undo').addEventListener('click', () => { stopEdit(true); objs.pop(); sel = -1; redraw(); });
    $('#bimg-orig').addEventListener('click', () => load());

    cv.addEventListener('pointerdown', (e) => {
      if (!base) return;
      if (editing >= 0) stopEdit(true);
      const p = pt(e);
      if (tool === 'eyedropper' || (tool === 'text' && e.altKey)) {
        const hex = sampleReportColor(p.x, p.y);
        $('#bimg-color').value = hex;
        applyFont();
        msg('Copied report colour ' + hex + (sel >= 0 && objs[sel]?.t === 'text' ? ' to selected text box.' : ' — ready to draw Text box.'));
        if (tool === 'eyedropper' && !(sel >= 0 && objs[sel]?.t === 'text')) setTool('text');
        return;
      }
      cv.setPointerCapture(e.pointerId);
      // the selected item can always be resized (corner) or moved, whatever the tool
      if (sel >= 0 && objs[sel] && inHandle(objs[sel], p)) { drag = { mode: 'resize' }; return; }
      if (sel >= 0 && objs[sel] && inBody(objs[sel], p) && tool !== 'move') { const b = box(objs[sel]); drag = { mode: 'move', dx: p.x - b.l, dy: p.y - b.t }; return; }
      if (tool === 'move') {
        sel = -1;
        for (let i = objs.length - 1; i >= 0; i--) {
          if (inHandle(objs[i], p) && i === sel) break;
          if (inBody(objs[i], p)) { sel = i; const b = box(objs[i]); drag = { mode: 'move', dx: p.x - b.l, dy: p.y - b.t }; if (objs[i].t === 'stamp') $('#bimg-size').value = Math.round(objs[i].w / cv.width * 100); if (objs[i].t === 'text') syncFontUi(objs[i]); break; }
        }
        redraw();
      } else { sel = -1; drag = { mode: 'draw', sx: p.x, sy: p.y }; redraw(); }
    });
    cv.addEventListener('pointermove', (e) => {
      if (!drag || !base) return;
      const p = pt(e);
      const o = objs[sel];
      if (drag.mode === 'move' && o) {
        const b = box(o); const w = b.r - b.l; const h = b.b - b.t;
        const nl = p.x - drag.dx; const nt = p.y - drag.dy;
        if (o.t === 'circle' || o.t === 'stamp') { o.x = nl + w / 2; o.y = nt + h / 2; } else { o.x = nl; o.y = nt; }
        redraw(); return;
      }
      if (drag.mode === 'resize' && o) {
        const b = box(o);
        const nw = Math.max(12, p.x - b.l); const nh = Math.max(12, p.y - b.t);
        if (o.t === 'circle') { o.rx = nw / 2; o.ry = nh / 2; o.x = b.l + o.rx; o.y = b.t + o.ry; }
        else if (o.t === 'stamp') { const ratio = o.h / o.w; const w = Math.max(24, (p.x - o.x) * 2); o.w = w; o.h = w * ratio; $('#bimg-size').value = Math.max(8, Math.min(70, Math.round(w / cv.width * 100))); }
        else { o.w = nw; o.h = nh; }
        redraw(); return;
      }
      if (drag.mode === 'draw') {
        if (tool === 'circle') temp = { t: 'circle', x: (drag.sx + p.x) / 2, y: (drag.sy + p.y) / 2, rx: Math.abs(p.x - drag.sx) / 2, ry: Math.abs(p.y - drag.sy) / 2 };
        else temp = { t: tool === 'text' ? 'text' : 'mark', text: '', font: $('#bimg-font').value, color: $('#bimg-color').value, bold: txtBold, align: txtAlign, x: Math.min(drag.sx, p.x), y: Math.min(drag.sy, p.y), w: Math.abs(p.x - drag.sx), h: Math.abs(p.y - drag.sy) };
        redraw();
      }
    });
    const end = () => {
      let made = false;
      if (drag && drag.mode === 'draw' && temp && ((temp.t === 'circle' && temp.rx > 4 && temp.ry > 4) || (temp.t !== 'circle' && temp.w > 8 && temp.h > 8))) { objs.push(temp); sel = objs.length - 1; made = true; }
      temp = null; drag = null; redraw();
      if (made && objs[sel].t === 'text') startEdit(sel);
    };
    cv.addEventListener('pointerup', end);
    cv.addEventListener('pointercancel', end);
    cv.addEventListener('dblclick', (e) => {
      const p = pt(e);
      for (let i = objs.length - 1; i >= 0; i--) if (objs[i].t === 'text' && inBody(objs[i], p)) { startEdit(i); break; }
    });

    $('#bimg-save').addEventListener('click', async () => {
      if (!base) return;
      stopEdit(true); sel = -1; redraw();
      msg('Saving...');
      try {
        const res = await apiFetch('dtr/bonding-image-save', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ lab_ref: labRef, tab: imgTab, image: cv.toDataURL('image/png') }),
        });
        if (res && res.ok) {
          nxToast(res.message, 'success', 5000);
          $$('.bs-thumb').filter((i) => i.dataset.labRef === labRef && (i.dataset.tab || 'bonding') === imgTab).forEach((i) => { i.parentNode.classList.remove('none'); i.src = `${BASE_URL}dtr/bonding-image?thumb=1&tab=${imgTab}&lab_ref=${encodeURIComponent(labRef)}&t=${Date.now()}`; });
          close();
        } else msg((res && res.message) || 'Save failed.');
      } catch (err) { msg('Save failed: ' + err.message); }
    });
  })();

  // Reloads the Bonding / Whole Shoe / Materials Master list from the server with the search words, the date range, the KPI result and the page chosen
  let listSeq = 0;
  window.dtrReloadList = async (forceKind, forcePage = 1) => {
    const kind = forceKind || $('.dtr-subtab.active')?.dataset.subtab || $('.dtr-subpanel:not(.hidden)')?.dataset.subpanel;
    if (kind === 'containers') {
      loadAppsheetArchive('bench', 1);
      return;
    }
    if (kind !== 'bonding' && kind !== 'shoes' && kind !== 'materials') return;
    const panel = $(`.dtr-subpanel[data-subpanel="${kind}"]`);
    if (!panel) return;
    const st = window.dtrListState || {};
    const q = panel.querySelector('.dtr-list-search')?.value.trim() || '';
    const page = Math.max(1, Number(forcePage) || 1);
    const seq = ++listSeq;
    const wrap = panel.querySelector('.dtr-table-wrap');
    const params = new URLSearchParams({ tab: 'labtracker', lk: kind, lq: q, lp: String(page) });
    if (st.rg) { params.set('from', st.rg.from); params.set('to', st.rg.to); }
    else { params.set('all', '1'); }
    if (st.res) params.set('res', st.res);
    if (kind === 'bonding' && panel._ltFilter) params.set('lt', panel._ltFilter);
    if (panel._sort && panel._sort.key) { params.set('ls', panel._sort.key); params.set('ld', panel._sort.dir); }
    try {
      wrap?.classList.add('is-loading');
      const r = await fetch(`${BASE_URL}dtr?${params}`, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
      if (seq !== listSeq) return;   // a newer request is already on its way
      const freshPanel = doc.querySelector(`.dtr-subpanel[data-subpanel="${kind}"]`);
      const fresh = freshPanel?.querySelector('tbody');
      const cur = panel.querySelector('tbody');
      if (fresh && cur) {
        cur.innerHTML = fresh.children.length ? fresh.innerHTML : '<tr><td colspan="13" class="muted" style="text-align:center;padding:24px">No matching records found.</td></tr>';
        window.dtrAddEditButtons?.(cur);
        window.dtrAddRowChecks?.(cur);
        const sa = cur.closest('table')?.querySelector('.dtr-sel-all'); if (sa) { sa.checked = false; sa.indeterminate = false; }
        cur.closest('table')?.classList.remove('dtr-sel-mode');
        const sb = cur.closest('.dtr-table-wrap')?.previousElementSibling; if (sb?.classList?.contains('dtr-sel-bar')) sb.hidden = true;
      }
      const freshCnt = freshPanel?.querySelector(`#lt-count-${kind}`);
      const curCnt = panel.querySelector(`#lt-count-${kind}`);
      if (freshCnt && curCnt) {
        curCnt.textContent = freshCnt.textContent;
        curCnt.dataset.total = freshCnt.dataset.total || '0';
      }
      const freshCounts = freshPanel?.querySelector(`#lt-counts-${kind}`);
      const curCounts = panel.querySelector(`#lt-counts-${kind}`);
      if (freshCounts && curCounts) {
        curCounts.innerHTML = freshCounts.innerHTML;
        window.dtrSyncTotal?.();   // the Total Lab Test Reports card follows the buttons
      }
      const freshPg = freshPanel?.querySelector(`#lt-page-${kind}`);
      const curPg = panel.querySelector(`#lt-page-${kind}`);
      if (freshPg && curPg) {
        curPg.textContent = freshPg.textContent;
        curPg.dataset.page = freshPg.dataset.page || String(page);
        curPg.dataset.pages = freshPg.dataset.pages || '1';
      }
    } catch (_) { /* keep the rows shown */ } finally { wrap?.classList.remove('is-loading'); }
  };

  // Sort arrows in the column headers of the Bonding / Whole Shoe / Materials lists: click = ascending, again = descending, again = the normal (latest first) order.
  // The sort runs on the server, so it covers every page, not only the 15 rows shown.
  const LIST_SORT_COLS = {
    bonding: ['labref', 'pull', 'prod', 'style', 'color', 'mtl', 'pdate', 'tdate', 'result', 'status', 'tech'],
    shoes: ['labref', 'line', 'prod', 'color', 'style', 'mtl', 'pdate', 'status'],
    materials: ['labref', 'type', 'code', 'name', 'supplier', 'tstatus', 'final'],
  };
  Object.keys(LIST_SORT_COLS).forEach((kind) => {
    const panel = $(`.dtr-subpanel[data-subpanel="${kind}"]`);
    const ths = panel ? [...panel.querySelectorAll('thead th')] : [];
    LIST_SORT_COLS[kind].forEach((key, i) => {
      const th = ths[i];
      if (!th) return;
      th.classList.add('dtr-sortable');
      th.dataset.sortKey = key;
      th.insertAdjacentHTML('beforeend', ' <span class="dtr-sort-ind" aria-hidden="true"><i class="up">&#9650;</i><i class="dn">&#9660;</i></span>');
      th.addEventListener('click', () => {
        const cur = panel._sort || {};
        panel._sort = cur.key !== key ? { key, dir: 'asc' } : (cur.dir === 'asc' ? { key, dir: 'desc' } : null);
        ths.forEach((x) => { x.classList.remove('sort-asc', 'sort-desc'); });
        if (panel._sort) th.classList.add(panel._sort.dir === 'asc' ? 'sort-asc' : 'sort-desc');
        window.dtrReloadList?.(kind, 1);
      });
    });
  });

  // Whole Shoe list: the mother Lab Ref row opens / closes the test type Lab Refs (children) under it
  document.addEventListener('click', (e) => {
    const row = e.target.closest('tr.ws-mother');
    if (!row || e.target.closest('button, input, select, textarea') || e.target.closest('a:not(.ws-toggle)')) return;
    e.preventDefault();
    const kids = row.nextElementSibling;
    if (!kids || !kids.classList.contains('ws-kids')) return;
    kids.hidden = !kids.hidden;
    row.classList.toggle('is-open', !kids.hidden);
  });
  // Summary buttons of the Bonding list (Daily, Tech Trial, Sample, Retest, First Article, Advance, Lacking, Shutdown lines): click = list only those rows, click again = every row
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-lt-filter]');
    const panel = btn?.closest('.dtr-subpanel[data-subpanel="bonding"]');
    if (!btn || !panel) return;
    const key = btn.dataset.ltFilter;
    panel._ltFilter = panel._ltFilter === key ? '' : key;
    window.dtrReloadList?.('bonding', 1);
  });

  $$('.dtr-lt-prev').forEach((b) => b.addEventListener('click', () => {
    const kind = b.dataset.ltType;
    const pgEl = $(`#lt-page-${kind}`);
    const curPage = Number(pgEl?.dataset.page || 1);
    if (curPage > 1) window.dtrReloadList?.(kind, curPage - 1);
  }));
  $$('.dtr-lt-next').forEach((b) => b.addEventListener('click', () => {
    const kind = b.dataset.ltType;
    const pgEl = $(`#lt-page-${kind}`);
    const curPage = Number(pgEl?.dataset.page || 1);
    const totalPages = Number(pgEl?.dataset.pages || 1);
    if (curPage < totalPages) window.dtrReloadList?.(kind, curPage + 1);
  }));

  // Search box of a Lab Tracker list (every tab): hides the rows that do not contain all the typed words
  document.addEventListener('input', (e) => {
    const box = e.target.closest('.dtr-list-search');
    if (!box || box.classList.contains('dtr-as-search')) return;
    const words = box.value.toLowerCase().split(/\s+/).filter(Boolean);
    const panel = box.closest('.dtr-subpanel');
    if (!panel) return;
    // Bonding / Whole Shoe / Materials Master lists: the search goes through the whole scheme (server side), not only the rows loaded in the list
    const kind = panel.dataset.subpanel;
    if (kind === 'bonding' || kind === 'shoes' || kind === 'materials') {
      clearTimeout(box._tmr);
      box._tmr = setTimeout(() => window.dtrReloadList?.(kind, 1), 350);
      return;
    }
    panel.querySelectorAll('tbody tr').forEach((tr) => {
      const text = tr.textContent.toLowerCase();
      tr.hidden = words.length > 0 && !words.every((w) => text.includes(w));
    });
  });

  $$('[data-goto-calc]').forEach((btn) => {
    btn.addEventListener('click', () => {
      switchTab('calculators');
      const target = $(`#calc-card-${btn.dataset.gotoCalc}`);
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });

  // =========================================================================
  // 2. LAB TRACKER SEARCH & CATEGORY PILLS
  // =========================================================================
  // Digital Test Report: every report of the lab grouped by MTL # (click one to list the tests done on it)
  const resChip = (v) => {
    const u = String(v || '').toUpperCase();
    if (!u) return '<span class="muted">-</span>';
    const cls = (u.startsWith('FAIL') || u === 'ERROR') ? 'res-chip res-fail' : (u.startsWith('TOL') ? 'res-chip res-tol' : 'res-chip res-pass');
    return `<span class="badge ${cls}">${escHtml(u)}</span>`;
  };
  const fmtD = (v) => { if (!v) return '-'; const t = new Date(String(v).slice(0, 10) + 'T00:00:00'); return isNaN(t) ? escHtml(v) : t.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); };
  const loadTrackerRows = async (slug = 'all', q = '') => {
    const tbody = $('#dtr-tracker-tbody');
    try {
      if (tbody && window.nxState) nxState(tbody, 'loading', 'Loading lab reports…', 7);
      const res = await apiFetch(`dtr/prodcode-summary?slug=${encodeURIComponent(slug)}&q=${encodeURIComponent(q)}`);
      if (!tbody || !res.groups) return;
      if (!res.groups.length) {
        nxState(tbody, 'empty', 'No lab reports match your search. Clear the filters or try another MTL #.', 7);
        return;
      }
      tbody.innerHTML = res.groups.map((g, i) => `
          <tr class="pc-row" data-pc="${i}">
            <td class="mono"><button type="button" class="pc-toggle" aria-expanded="false" aria-label="Show the tests of ${escHtml(g.mtl || g.key)}"><span class="pc-caret">&#9656;</span> <b>${escHtml(g.mtl || g.key)}</b> <span class="pc-count">${g.tests.length}</span></button></td>
            <td>${escHtml(g.color || '-')}</td>
            <td class="mono">${escHtml(g.style || '-')}</td>
            <td>${fmtD(g.prod)}</td>
            <td>${fmtD(g.tested)}</td>
            <td>${escHtml(g.status || '-')}</td>
            <td>${resChip(g.result)}</td>
          </tr>
          <tr class="pc-detail" data-pc-detail="${i}" hidden>
            <td colspan="7">
              <table class="pc-sub">
                <thead><tr><th></th><th>Lab Ref No</th><th>Production Code</th><th>ETRF No.</th><th>Test</th><th>Tested Date</th><th>Status</th><th>Result</th></tr></thead>
                <tbody>${g.tests.map((t) => `
                  <tr>
                    <td>${t.slug === 'thirdparty' ? '' : `<button type="button" class="dtr-dl-btn" data-open-record="${escHtml(t.slug)}" data-source-id="${escHtml(t.source_id)}" data-labref="${escHtml(t.labref)}" title="Open form" aria-label="Open form"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg></button>`}</td>
                    <td class="mono"><b>${escHtml(t.labref || '-')}</b></td>
                    <td class="mono">${escHtml(t.code || g.code || '-')}</td>
                    <td class="mono">${escHtml(t.etrf || '-')}</td>
                    <td>${escHtml(t.test || '-')}</td>
                    <td>${fmtD(t.tested)}</td>
                    <td>${escHtml(t.status || '-')}</td>
                    <td>${resChip(t.result)}</td>
                  </tr>`).join('')}</tbody>
              </table>
            </td>
          </tr>`).join('');
    } catch (err) {
      console.error(err);
      if (tbody && window.nxState) nxState(tbody, 'error', 'The list could not be loaded. Check your connection and try again.', 7);
    }
  };
  $('#dtr-tracker-tbody')?.addEventListener('click', (e) => {
    const t = e.target.closest('.pc-toggle');
    if (!t) return;
    const row = t.closest('tr');
    const det = row.nextElementSibling;
    const open = det.hidden;
    det.hidden = !open;
    row.classList.toggle('open', open);
    t.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  const runTrackerSearch = () => {
    const slug = $('#dtr-tracker-cat')?.value || 'all';
    const q = $('#dtr-tracker-search')?.value.trim() || '';
    loadTrackerRows(slug, q);
  };

  if ($('#dtr-tracker-tbody')) runTrackerSearch();
  $('#dtr-tracker-cat')?.addEventListener('change', runTrackerSearch);
  $('#dtr-tracker-btn-search')?.addEventListener('click', runTrackerSearch);

  $('#dtr-tracker-search')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      $('#dtr-tracker-btn-search')?.click();
    }
  });

  // after a Lab Ref jump: highlight the matching sidebar page (Shoe Bonding (U60) / Whole Shoe) and put it in the address bar
  const markDtrNav = (tab) => {
    $$('.sidebar a.nav-link, nav a.nav-link').forEach((a) => {
      if (/[?&]tab=/.test(a.getAttribute('href') || '')) a.classList.toggle('active', (a.getAttribute('href') || '').includes('tab=' + tab));
    });
    history.replaceState(null, '', location.pathname + '?tab=' + tab);
  };

  // Lab Ref No in the Bonding Shoe Collection list -> opens that record in Shoe Bonding (U60)
  document.addEventListener('click', async (e) => {
    const a = e.target.closest('[data-open-bonding]');
    if (!a) return;
    e.preventDefault();
    const id = a.dataset.openBonding;
    // a pull-test Lab Ref (DTP<date>EYA1-L = eyelet, DR = D-ring, HP = heel pull, RVT = rivet) opens as a Pull Footwear Test Result in Shoe Bonding (U60)
    const ref = (a.textContent || '').trim();
    if (/^DTP\d{8}((HP|EY|DR|RVT|LLH)[A-Z]\d+|RIVET-\d+)/i.test(ref)) {
      switchTab('bonding');
      markDtrNav('bonding');
      setReportKind('pull');
      await loadPullRecord(0, ref);   // read from tbl_dtr_pull_tests by Lab Ref No
      return;
    }
    if (!id || id === '0') return;
    switchTab('bonding');
    markDtrNav('bonding');
    setReportKind('bonding');
    await loadBondingRecordList(id);
    await loadSingleBondingRecord(id);
  });

  // Lab Ref No in the Whole Shoe Collection list -> opens that Lab Ref in the Whole Shoe report page
  document.addEventListener('click', (e) => {
    const a = e.target.closest('[data-open-wholeshoe]');
    if (!a) return;
    e.preventDefault();
    const ref = a.dataset.openWholeshoe;
    const sel = $('#ws-labref');
    if (!ref || !sel) return;
    switchTab('wholeshoe');
    markDtrNav('wholeshoe');
    sel.value = ref;
    sel.dispatchEvent(new Event('change'));
  });

  // Lab Ref No in the IQC Materials Master Queue -> its test record in Material Tests (form picked from the unified Lab Ref list)
  document.addEventListener('click', async (e) => {
    const a = e.target.closest('[data-open-material]');
    if (!a) return;
    e.preventDefault();
    const ref = a.dataset.openMaterial;
    try {
      // a material received in the Lab Tracker: its report is made (or completed) from the material info and the test sheet results, then opened
      const pr = await apiFetch('materials/to-report', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="X-CSRF-TOKEN"]')?.content || '' }, body: JSON.stringify({ lab: ref }) }).catch(() => null);
      if (pr && pr.csrf) { const m = document.querySelector('meta[name="X-CSRF-TOKEN"]'); if (m) m.content = pr.csrf; }
      if (pr && pr.ok && pr.slug && pr.id) {
        switchTab('material'); markDtrNav('material');
        const fs = $('#dtr-mat-form-slug'); if (fs) fs.value = pr.slug;
        activeSlug = pr.slug;
        await loadMaterialRecordList(pr.slug, pr.id);
        await loadSingleMaterialRecord(pr.slug, pr.id);
        showToast(pr.created ? 'Material Test Report made from the material and its test sheet.' : 'Material Test Report updated from the test sheet.');
        return;
      }
      if (pr && pr.ok === false && /No report was made/.test(pr.message || '')) { showToast(pr.message, true); return; }
      // exact Lab Ref first; the bench log also writes a group of refs as one (DTP20261027F1-1-4, DTP20261022VH-VL-1-2): then the first report of that group
      let row = null;
      let q = ref;
      for (let guard = 0; guard < 4 && !row && q.length > 8; guard++) {
        const res = await apiFetch(`dtr/records?slug=all&q=${encodeURIComponent(q)}`);
        const rows = (res && res.rows) || [];
        row = rows.find((r) => String(r.lab_ref_no).toLowerCase() === q.toLowerCase())
          || (guard > 0 ? rows.find((r) => String(r.lab_ref_no).toLowerCase().startsWith(q.toLowerCase())) : null) || null;
        const cut = q.lastIndexOf('-');
        if (!row) q = cut > 8 ? q.slice(0, cut) : '';
      }
      if (!row) { showToast(`No test report has been started for ${ref} yet.`, true); return; }
      const slug = row.form_slug;
      if (slug === 'bonding' || slug === 'pull' || slug === 'pulltest') { showToast('That Lab Ref belongs to a shoe test, open it from Lab Tracker.', true); return; }
      if (slug === 'wholeshoe') {          // a Whole Shoe Lab Ref: its own report page
        const sel = $('#ws-labref');
        switchTab('wholeshoe'); markDtrNav('wholeshoe');
        if (sel) { if (![...sel.options].some((o) => o.value === row.lab_ref_no)) sel.add(new Option(row.lab_ref_no, row.lab_ref_no)); sel.value = row.lab_ref_no; sel.dispatchEvent(new Event('change')); }
        return;
      }
      switchTab('material');
      markDtrNav('material');
      const formSel = $('#dtr-mat-form-slug');
      if (formSel) formSel.value = slug;
      activeSlug = slug;
      await loadMaterialRecordList(slug, row.source_id);
      await loadSingleMaterialRecord(slug, row.source_id);
    } catch (err) {
      showToast('Could not open that Lab Ref: ' + err.message, true);
    }
  });

  // Open a clicked record in Material Form or Bonding Form
  $('#dtr-tracker-tbody')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-open-record]');
    if (!btn) return;
    const slug = btn.dataset.openRecord;
    const sourceId = btn.dataset.sourceId;
    if (slug === 'pull' || slug === 'pulltest') {
      switchTab('bonding');
      setReportKind('pull');
      await loadPullRecord(sourceId);
    } else if (slug === 'bonding') {
      switchTab('bonding');
      setReportKind('bonding');
      await loadBondingRecordList(sourceId);
      await loadSingleBondingRecord(sourceId);
    } else if (slug === 'wholeshoe') {
      const ref = btn.dataset.labref || '';
      const sel = $('#ws-labref');
      switchTab('wholeshoe');
      markDtrNav('wholeshoe');
      if (sel && ref) {
        if (![...sel.options].some((o) => o.value === ref)) {
          sel.add(new Option(ref, ref));
        }
        sel.value = ref;
        sel.dispatchEvent(new Event('change'));
      }
    } else {
      switchTab('material');
      const formSel = $('#dtr-mat-form-slug');
      if (formSel) formSel.value = slug;
      activeSlug = slug;
      await loadMaterialRecordList(slug, sourceId);
      await loadSingleMaterialRecord(slug, sourceId);
    }
  });

  // =========================================================================
  // 3. EXACT ACCESS VBA COMPUTATIONS FOR MATERIAL FORMS
  // =========================================================================

  // 3a. UpdateMinMax: Thickness 1..6 -> TMin, TMax, TAve = Round(Sum/6, 2)
  const computeThicknessMinMax = (triggerSmartStd = true) => {
    const vals = [];
    for (let i = 1; i <= 6; i++) {
      const v = parseFloat($(`#mf_Thickness${i}`)?.value ?? '');
      if (Number.isFinite(v) && v > 0) vals.push(v);
    }
    if (!vals.length) return;
    const minVal = Math.min(...vals);
    const maxVal = Math.max(...vals);
    const aveVal = roundTo(vals.reduce((a, b) => a + b, 0) / vals.length, 2);

    if ($('#mf_TMin')) $('#mf_TMin').value = minVal;
    if ($('#mf_TMax')) $('#mf_TMax').value = maxVal;
    if ($('#mf_TAve')) $('#mf_TAve').value = aveVal;

    // Check Thickness Rating if T_S is numeric
    const tStd = parseFloat($('#mf_T_S')?.value ?? '');
    if (Number.isFinite(tStd) && tStd > 0 && $('#mf_TRating')) {
      $('#mf_TRating').value = aveVal >= tStd ? 'PASS' : 'FAIL';
      styleRatingInput($('#mf_TRating'));
    }

    if (triggerSmartStd) {
      applyStandardValuesSmart();
    }
  };

  // 3b. Weight Average & Specific Gravity Rating (Tol = 0.02) & DIN & RossFlex & Shrinkage
  const computeWeightAndPhysicals = () => {
    const wVals = [1, 2, 3]
      .map((i) => parseFloat($(`#mf_Weight${i}`)?.value ?? ''))
      .filter((v) => Number.isFinite(v) && v > 0);
    if (wVals.length > 0 && $('#mf_WAve')) {
      const wAve = roundTo(wVals.reduce((a, b) => a + b, 0) / wVals.length, 2);
      $('#mf_WAve').value = wAve;
      const wStd = parseFloat($('#mf_Weight_S')?.value ?? '');
      if (Number.isFinite(wStd) && wStd > 0 && $('#mf_WRating')) {
        $('#mf_WRating').value = wAve >= wStd ? 'PASS' : 'FAIL';
        styleRatingInput($('#mf_WRating'));
      }
    }

    // Specific Gravity (SG1..SG3 -> SG_Ave = Round(total/3, 2), Abs(ave - std) <= 0.02)
    const sgVals = [1, 2, 3]
      .map((i) => parseFloat($(`#mf_SG${i}`)?.value ?? ''))
      .filter((v) => Number.isFinite(v) && v > 0);
    if (sgVals.length > 0 && $('#mf_SG_Ave')) {
      const sgAve = roundTo(sgVals.reduce((a, b) => a + b, 0) / sgVals.length, 2);
      $('#mf_SG_Ave').value = sgAve;
      const sgStd = parseFloat($('#mf_SG_Std')?.value ?? '');
      if (Number.isFinite(sgStd) && sgStd > 0 && $('#mf_SG_Rate')) {
        $('#mf_SG_Rate').value = Math.abs(sgAve - sgStd) <= 0.02 ? 'PASS' : 'FAIL';
        styleRatingInput($('#mf_SG_Rate'));
      }
    }

    // DIN Abrasion (Rubber: DIN_1..3 > 150 -> FAIL else PASS)
    const dinVals = [1, 2, 3]
      .map((i) => parseFloat($(`#mf_DIN_${i}`)?.value ?? ''))
      .filter((v) => Number.isFinite(v) && v > 0);
    if (dinVals.length > 0 && $('#mf_DIN_Rate')) {
      $('#mf_DIN_Rate').value = dinVals.some((v) => v > 150) ? 'FAIL' : 'PASS';
      styleRatingInput($('#mf_DIN_Rate'));
    }

    // Ross Flex (Rubber: RossFlex_1..2 > 50 -> FAIL else PASS)
    const rfVals = [1, 2]
      .map((i) => parseFloat($(`#mf_RossFlex_${i}`)?.value ?? ''))
      .filter((v) => Number.isFinite(v) && v > 0);
    if (rfVals.length > 0 && $('#mf_RossFlex_Rate')) {
      $('#mf_RossFlex_Rate').value = rfVals.some((v) => v > 50) ? 'FAIL' : 'PASS';
      styleRatingInput($('#mf_RossFlex_Rate'));
    }

    // Shrinkage (Abs(ShrinkageL) <= Shrinkage_S)
    const shL = parseFloat($('#mf_ShrinkageL')?.value ?? '');
    const shStd = parseFloat($('#mf_Shrinkage_S')?.value ?? '');
    if (Number.isFinite(shL) && Number.isFinite(shStd) && shStd > 0 && $('#mf_ShrinkageLRating')) {
      $('#mf_ShrinkageLRating').value = Math.abs(shL) <= Math.abs(shStd) ? 'PASS' : 'FAIL';
      styleRatingInput($('#mf_ShrinkageLRating'));
    }
  };

  // 3c. UpdateDirectionalAverage: Tensile, Elongation, TrouserTear, StitchTear, MullenBurst, DieC
  const DIR_GROUPS = {
    TensileL:     { inputs: ['mf_TensileL1', 'mf_TensileL2', 'mf_TensileL3'], ave: 'mf_TensileLAve', std: 'mf_Tensile_L', rate: 'mf_TensileLRating', dec: 2 },
    TensileW:     { inputs: ['mf_TensileW1', 'mf_TensileW2', 'mf_TensileW3'], ave: 'mf_TensileWAve', std: 'mf_Tensile_W', rate: 'mf_TensileWRating', dec: 2 },
    ElongationL:  { inputs: ['mf_ElongationL1', 'mf_ElongationL2', 'mf_ElongationL3'], ave: 'mf_ElongationLAve', std: 'mf_Elongation_L', rate: 'mf_ElongationLRating', dec: 2 },
    ElongationW:  { inputs: ['mf_ElongationW1', 'mf_ElongationW2', 'mf_ElongationW3'], ave: 'mf_ElongationWAve', std: 'mf_Elongation_W', rate: 'mf_ElongationWRating', dec: 2 },
    TrouserTearL: { inputs: ['mf_TrouserTearL1', 'mf_TrouserTearL2', 'mf_TrouserTearL3'], ave: 'mf_TrouserTearLAve', std: 'mf_TrouserTear_L', rate: 'mf_TrouserTearLRating', dec: 2 },
    TrouserTearW: { inputs: ['mf_TrouserTearW1', 'mf_TrouserTearW2', 'mf_TrouserTearW3'], ave: 'mf_TrouserTearWAve', std: 'mf_TrouserTear_W', rate: 'mf_TrouserTearWRating', dec: 2 },
    StitchTearL:  { inputs: ['mf_StitchTearL1', 'mf_StitchTearL2', 'mf_StitchTearL3'], ave: 'mf_StitchTearLAve', std: 'mf_StitchTear_L', rate: 'mf_StitchTearLRating', dec: 2 },
    StitchTearW:  { inputs: ['mf_StitchTearW1', 'mf_StitchTearW2', 'mf_StitchTearW3'], ave: 'mf_StitchTearWAve', std: 'mf_StitchTear_W', rate: 'mf_StitchTearWRating', dec: 2 },
    MullenBurst:  { inputs: ['mf_MullenBurst1', 'mf_MullenBurst2', 'mf_MullenBurst3'], ave: 'mf_Text3977', std: 'mf_MullenBurst', rate: 'mf_MullenBurstRating', dec: 4 },
    DieC:         { inputs: ['mf_DieC_1', 'mf_DieC_2', 'mf_DieC_3'], ave: 'mf_DieC_Ave', std: 'mf_DieC_Std', rate: 'mf_DieC_Rate', dec: 2 },
  };

  const computeDirectionalGroup = (groupKey) => {
    const g = DIR_GROUPS[groupKey];
    if (!g) return;
    const vals = g.inputs
      .map((id) => parseFloat($(`#${id}`)?.value ?? ''))
      .filter((v) => Number.isFinite(v));
    const aveEl = $(`#${g.ave}`);
    const stdEl = $(`#${g.std}`);
    const rateEl = $(`#${g.rate}`);
    if (!vals.length) return;
    const avg = roundTo(vals.reduce((a, b) => a + b, 0) / vals.length, g.dec);
    if (aveEl) aveEl.value = avg;
    const stdVal = parseFloat(stdEl?.value ?? '');
    if (Number.isFinite(stdVal) && stdVal > 0 && rateEl) {
      rateEl.value = avg >= stdVal ? 'PASS' : 'FAIL';
      styleRatingInput(rateEl);
    }
  };

  const computeAllDirectionalGroups = () => {
    Object.keys(DIR_GROUPS).forEach(computeDirectionalGroup);
  };

  // 3d. Evaluate6PointRating: Wool, Acrylic, Polyester, Nylon, Cotton, Acetate vs Standard
  const SIX_POINT_GROUPS = {
    CTP:  { fibers: ['mf_CtPWool', 'mf_CtPAcrylic', 'mf_CtPPolyester', 'mf_CtPNylon', 'mf_CtPCotton', 'mf_CtPAcetate'], std: 'mf_CTP', rate: 'mf_CTPRating' },
    CTPB: { fibers: ['mf_CtPBWool', 'mf_CtPBAcrylic', 'mf_CtPBPolyester', 'mf_CtPBNylon', 'mf_CtPBCotton', 'mf_CtPBAcetate'], std: 'mf_CtPB_S', rate: 'mf_CTPB_Rating' },
    CTW:  { fibers: ['mf_CtWWool', 'mf_CtWAcrylic', 'mf_CtWPolyester', 'mf_CtWNylon', 'mf_CtWCotton', 'mf_CtWAcetate'], std: 'mf_CTW', rate: 'mf_CTWRating' },
    CTWB: { fibers: ['mf_CtWBackSideWool', 'mf_CtWBackSideAcrylic', 'mf_CtWBackSidePolyester', 'mf_CtWBackSideNylon', 'mf_CtWBackSideCotton', 'mf_CtWBackSideAcetate'], std: 'mf_CtWB_S', rate: 'mf_CtWB_Rating' },
  };

  const compute6PointColorfastness = (key) => {
    const g = SIX_POINT_GROUPS[key];
    if (!g) return;
    const stdVal = parseFloat($(`#${g.std}`)?.value ?? '');
    const rateEl = $(`#${g.rate}`);
    if (!Number.isFinite(stdVal) || stdVal <= 0 || !rateEl) return;
    const fiberVals = g.fibers.map((id) => parseFloat($(`#${id}`)?.value ?? ''));
    if (fiberVals.some((v) => !Number.isFinite(v))) return;
    rateEl.value = fiberVals.some((v) => v < stdVal) ? 'FAIL' : 'PASS';
    styleRatingInput(rateEl);
  };

  const computeCrockingAndWeathering = () => {
    // Crocking Dry
    const cDry = parseFloat($('#mf_CtCDry')?.value ?? '');
    const cDryS = parseFloat($('#mf_CtCDry_S')?.value ?? '');
    if (Number.isFinite(cDry) && Number.isFinite(cDryS) && cDryS > 0 && $('#mf_CTCDRYRating')) {
      $('#mf_CTCDRYRating').value = cDry >= cDryS ? 'PASS' : 'FAIL';
      styleRatingInput($('#mf_CTCDRYRating'));
    }
    // Crocking Wet
    const cWet = parseFloat($('#mf_CtCWet')?.value ?? '');
    const cWetS = parseFloat($('#mf_CtCWet_S')?.value ?? '');
    if (Number.isFinite(cWet) && Number.isFinite(cWetS) && cWetS > 0 && $('#mf_CTCWETRating')) {
      $('#mf_CTCWETRating').value = cWet >= cWetS ? 'PASS' : 'FAIL';
      styleRatingInput($('#mf_CTCWETRating'));
    }
    // UV
    const uv = parseFloat($('#mf_UV')?.value ?? '');
    const uvS = parseFloat($('#mf_UV_S')?.value ?? '');
    if (Number.isFinite(uv) && Number.isFinite(uvS) && uvS > 0 && $('#mf_UVRating')) {
      $('#mf_UVRating').value = uv >= uvS ? 'PASS' : 'FAIL';
      styleRatingInput($('#mf_UVRating'));
    }
    // Heat Aging 1..2
    const ha1 = parseFloat($('#mf_HeatAging1')?.value ?? '');
    const ha2 = parseFloat($('#mf_HeatAging2')?.value ?? '');
    const haS = parseFloat($('#mf_HeatAging_S')?.value ?? '');
    if (Number.isFinite(ha1) && Number.isFinite(ha2) && Number.isFinite(haS) && haS > 0 && $('#mf_HeatAgingRating')) {
      $('#mf_HeatAgingRating').value = (ha1 < haS || ha2 < haS) ? 'FAIL' : 'PASS';
      styleRatingInput($('#mf_HeatAgingRating'));
    }
    // Hydrolysis 1..2
    const hy1 = parseFloat($('#mf_Hydrolysis1')?.value ?? '');
    const hy2 = parseFloat($('#mf_Hydrolysis2')?.value ?? '');
    const hyS = parseFloat($('#mf_Hydrolysis_S')?.value ?? '');
    if (Number.isFinite(hy1) && Number.isFinite(hy2) && Number.isFinite(hyS) && hyS > 0 && $('#mf_HydrolysisRating')) {
      $('#mf_HydrolysisRating').value = (hy1 < hyS || hy2 < hyS) ? 'FAIL' : 'PASS';
      styleRatingInput($('#mf_HydrolysisRating'));
    }
    // PVC 1..2
    const pvc1 = parseFloat($('#mf_PVC_1')?.value ?? '');
    const pvc2 = parseFloat($('#mf_PVC_2')?.value ?? '');
    const pvcS = parseFloat($('#mf_PVC_S')?.value ?? '');
    if (Number.isFinite(pvc1) && Number.isFinite(pvc2) && Number.isFinite(pvcS) && pvcS > 0 && $('#mf_PVC_Rating')) {
      $('#mf_PVC_Rating').value = (pvc1 < pvcS || pvc2 < pvcS) ? 'FAIL' : 'PASS';
      styleRatingInput($('#mf_PVC_Rating'));
    }
  };

  // 3e. EvaluateRatings: Priority FAIL > TOLERABLE > PASS > UNRATED
  const evaluateMaterialOverallRatings = () => {
    let foundPass = false;
    let foundFail = false;
    let foundTol = false;

    $$('#dtr-material-form .dtr-rate-inp').forEach((inp) => {
      styleRatingInput(inp);
      const val = String(inp.value || '').trim().toUpperCase();
      if (val === 'FAIL') foundFail = true;
      else if (val === 'TOLERABLE') foundTol = true;
      else if (val === 'PASS') foundPass = true;
    });

    let result = 'PASS';
    if (foundFail) result = 'FAIL';
    else if (foundTol) result = 'TOLERABLE';
    else if (foundPass) result = 'PASS';
    else if ($('#mf_Remarks')?.value) result = String($('#mf_Remarks').value).toUpperCase();

    if ($('#mf_Remarks')) $('#mf_Remarks').value = result;

    const stamp = $('#dtr-stamp-badge');
    if (stamp) {
      stamp.textContent = result;
      stamp.className = 'dtr-stamp ' + (result === 'FAIL' ? 'dtr-stamp-fail' : (result === 'TOLERABLE' ? 'dtr-stamp-warn' : 'dtr-stamp-pass'));
    }
    leatherSheetSync();
  };

  // 3f. ApplyStandardValuesSmart: Query tbl_dtr_standards by MaterialType & TAve
  const applyStandardValuesSmart = async () => {
    const matType = $('#mf_MaterialType')?.value.trim() || '';
    const tAve = parseFloat($('#mf_TAve')?.value ?? '0') || 0;
    if (!matType) return;

    try {
      const res = await apiFetch(`dtr/standards?material_type=${encodeURIComponent(matType)}&t_ave=${encodeURIComponent(tAve)}`);
      if (res && res.mapped) {
        Object.keys(res.mapped).forEach((ctrlName) => {
          const val = res.mapped[ctrlName];
          const el = $(`#mf_${CSS.escape(ctrlName)}`) || $(`[name="${ctrlName}"]`, $('#dtr-material-form'));
          if (el) el.value = val;
        });
      }
      computeWeightAndPhysicals();
      computeAllDirectionalGroups();
      Object.keys(SIX_POINT_GROUPS).forEach(compute6PointColorfastness);
      computeCrockingAndWeathering();
      evaluateMaterialOverallRatings();
    } catch (err) {
      console.warn('[DTR] applyStandardValuesSmart error:', err);
    }
  };

  const runFullMaterialRecalc = async () => {
    computeThicknessMinMax(false);
    await applyStandardValuesSmart();
    showToast('✓ Executed ApplyStandardValuesSmart + Thickness + Directional Averages + 6-Point Colorfastness + EvaluateRatings.');
  };

  // =========================================================================
  // 4. LOAD & SAVE MATERIAL RECORDS
  // =========================================================================
  const loadMaterialRecordList = async (slug, selectedId = null) => {
    const sel = $('#dtr-mat-record-select');
    try {
      if (sel) sel.innerHTML = '<option value="">Loading records…</option>';
      const res = await apiFetch(`dtr/records?slug=${encodeURIComponent(slug)}`);
      if (!sel || !res.rows) return;
      if (!res.rows.length) { sel.innerHTML = '<option value="">No records yet - press “+ New Record”</option>'; return; }
      sel.innerHTML = '<option value="">- New report (not saved yet) -</option>' + res.rows.map((r) => `
        <option value="${escHtml(r.source_id)}" ${String(r.source_id) === String(selectedId) ? 'selected' : ''}>
          ${escHtml(r.lab_ref_no)} · ${escHtml(r.material_type || r.form_category)} · ${escHtml(r.model_style || r.material_code || '')} [${escHtml(r.overall_result || 'Pass')}]
        </option>
      `).join('');
    } catch (err) {
      console.error(err);
      if (sel) sel.innerHTML = '<option value="">Could not load records - reload the page</option>';
      if (window.nxToast) nxToast('The record list could not be loaded.', 'error');
    }
  };

  const renderRawColumnsEditor = (record) => {
    const container = $('#dtr-raw-cols-container');
    const cntEl = $('#dtr-raw-col-count');
    if (!container) return;
    const keys = Object.keys(record || {});
    if (cntEl) cntEl.textContent = keys.length;
    container.innerHTML = keys.map((col) => `
      <div class="dtr-field">
        <label title="${escHtml(col)}"><code>${escHtml(col)}</code></label>
        <input type="text" data-raw-col="${escHtml(col)}" value="${escHtml(record[col] ?? '')}" ${col === 'ID' ? 'readonly class="dtr-ro"' : ''}>
      </div>
    `).join('');
  };

  const populateMaterialForm = (record, cfg = {}) => {
    activeRecord = record || {};
    applyLeatherView();
    if ($('#dtr-active-form-label') && cfg.label) {
      $('#dtr-active-form-label').textContent = `${cfg.label}`;
    }
    if ($('#dtr-active-labref-title')) {
      $('#dtr-active-labref-title').textContent = `${record.LabRefNo || 'New Report'} — ${record['Material Description'] || record.MaterialDescription || record.MaterialType || ''}`;
    }

    // Clear all inputs first
    $$('#dtr-material-form input, #dtr-material-form select').forEach((inp) => {
      if (inp.name) inp.value = '';
    });

    // Populate matching inputs by name or id
    Object.keys(record).forEach((col) => {
      const val = record[col] ?? '';
      const byId = $(`#mf_${col.replace(/[^a-zA-Z0-9_]/g, '')}`);
      if (byId) byId.value = val;
      const byName = $(`[name="${CSS.escape(col)}"]`, $('#dtr-material-form'));
      if (byName) byName.value = val;
    });

    // Handle alternate column names across the 10 Access tables
    if ($('#mf_MaterialDescription') && !$('#mf_MaterialDescription').value) {
      $('#mf_MaterialDescription').value = record['Material Description'] || record.MaterialDescription || '';
    }
    if ($('#mf_SupplierFactory') && !$('#mf_SupplierFactory').value) {
      $('#mf_SupplierFactory').value = record['Supplier/Factory'] || record.Supplier || record.SupplierFactory || '';
    }
    if ($('#mf_POLotNo') && !$('#mf_POLotNo').value) {
      $('#mf_POLotNo').value = record['PO/Lot No'] || record.POLotNo || '';
    }
    if ($('#mf_ModelNoStyle') && !$('#mf_ModelNoStyle').value) {
      $('#mf_ModelNoStyle').value = record['Model No/Style'] || record.ModelNoStyle || '';
    }
    if ($('#mf_POQuantity') && !$('#mf_POQuantity').value) {
      $('#mf_POQuantity').value = record['PO Quantity'] || record.POQuantity || '';
    }
    if ($('#mf_TestedBy') && !$('#mf_TestedBy').value) {
      $('#mf_TestedBy').value = record['Tested by'] || record.TestedBy || record.Technician || '';
    }

    renderRawColumnsEditor(record);
    computeThicknessMinMax(false);
    computeWeightAndPhysicals();
    computeAllDirectionalGroups();
    Object.keys(SIX_POINT_GROUPS).forEach(compute6PointColorfastness);
    computeCrockingAndWeathering();
    evaluateMaterialOverallRatings();
    leatherSheetFill(record);
  };

  const loadSingleMaterialRecord = async (slug, sourceId = 0) => {
    try {
      const res = await apiFetch(`dtr/record?slug=${encodeURIComponent(slug)}&source_id=${encodeURIComponent(sourceId)}`);
      if (res && res.ok) {
        populateMaterialForm(res.record || {}, res.config || {});
      }
    } catch (err) {
      console.error(err);
    }
  };

  // Auto-detect MaterialType when LabRefNo changes (Exact LabRefNo_AfterUpdate rule from VBA)
  $('#mf_LabRefNo')?.addEventListener('change', () => {
    const ref = ($('#mf_LabRefNo')?.value || '').toUpperCase().trim();
    const mtEl = $('#mf_MaterialType');
    if (!ref || !mtEl) return;
    if (ref.includes('FBU') || ref.includes('FBL')) {
      if (!mtEl.value) mtEl.value = 'Canvas';
    } else if (ref.includes('LEU') || ref.includes('LEL')) {
      if (!mtEl.value) mtEl.value = 'Suede';
    } else if (ref.includes('RB')) {
      mtEl.value = 'Rubber';
    } else if (ref.includes('IB')) {
      mtEl.value = 'Inner Box';
    } else if (ref.includes('OB')) {
      mtEl.value = 'Outer Box';
    } else if (ref.includes('FML')) {
      mtEl.value = 'Sockliner - Latex';
    } else if (ref.includes('FM')) {
      mtEl.value = 'PU Foam';
    } else if (ref.includes('SKL')) {
      mtEl.value = 'Sockliner - EVA';
    } else if (ref.includes('LC')) {
      mtEl.value = 'Shoe lace';
    } else if (ref.includes('TH')) {
      mtEl.value = 'Thread';
    } else if (ref.includes('EY')) {
      mtEl.value = 'Eyelet';
    } else if (ref.includes('GR')) {
      mtEl.value = 'Grommet';
    } else if (ref.includes('ZP')) {
      mtEl.value = 'Zipper';
    }
    applyStandardValuesSmart();
  });

  // Auto-lookup MaterialCode, PO/Lot No, and MTL from dbnexus (MaterialList, ShoeList, tblDSR)
  const triggerMasterLookups = async () => {
    const matCode = $('#mf_MaterialCode')?.value.trim() || '';
    const poLot = $('#mf_POLotNo')?.value.trim() || '';
    const mtl = $('#mf_MTL')?.value.trim() || '';

    if (!matCode && !poLot && !mtl) {
      showToast('Enter a Material Code, PO/Lot No, or MTL # first.', true);
      return;
    }

    try {
      const res = await apiFetch(
        `dtr/lookup?material_code=${encodeURIComponent(matCode)}&po_lot_no=${encodeURIComponent(poLot)}&mtl_no=${encodeURIComponent(mtl)}`
      );
      let msgParts = [];
      if (res.material) {
        if ($('#mf_SupplierFactory')) $('#mf_SupplierFactory').value = res.material.Supplier || '';
        if ($('#mf_MaterialDescription')) $('#mf_MaterialDescription').value = res.material.MaterialName || '';
        msgParts.push(`MaterialList (${res.material.MaterialCode})`);
      }
      if (res.shoe) {
        if ($('#mf_ModelNoStyle')) $('#mf_ModelNoStyle').value = res.shoe.StyleNo || '';
        if ($('#mf_Color')) $('#mf_Color').value = res.shoe.Colorway || '';
        if ($('#mf_MTL') && res.shoe.MTL) $('#mf_MTL').value = res.shoe.MTL;
        msgParts.push(`ShoeList (${res.shoe.StyleNo})`);
      }
      if (res.dsr && res.dsr.length > 0) {
        const d = res.dsr[0];
        if ($('#mf_StyleNumber_DSR')) $('#mf_StyleNumber_DSR').value = d.StyleNumber || '';
        if ($('#mf_StyleDescription_DSR')) $('#mf_StyleDescription_DSR').value = d.StyleDescription || '';
        if ($('#mf_Colorway_DSR')) $('#mf_Colorway_DSR').value = d.Colorway || '';
        if ($('#mf_ComponentorLocation_DSR')) $('#mf_ComponentorLocation_DSR').value = d.ComponentorLocation || '';
        if ($('#mf_VFMaterialCode_DSR')) $('#mf_VFMaterialCode_DSR').value = d.VFMaterialCode || '';
        if ($('#mf_InternalName_DSR')) $('#mf_InternalName_DSR').value = d['Internal NameVans1'] || d.Material || '';
        if ($('#mf_ColorName_DSR')) $('#mf_ColorName_DSR').value = d.ColorName || d.Color || '';
        msgParts.push(`tblDSR (${res.dsr.length} component rows)`);
      }
      if (msgParts.length) {
        showToast(`✓ Auto-Lookup populated from: ${msgParts.join(' · ')}`);
      } else {
        showToast('No exact match found in MaterialList / ShoeList / tblDSR.', true);
      }
    } catch (err) {
      console.error(err);
    }
  };

  $('#btn-dtr-lookup-all')?.addEventListener('click', triggerMasterLookups);
  $('#mf_MaterialCode')?.addEventListener('change', triggerMasterLookups);
  $('#mf_POLotNo')?.addEventListener('change', triggerMasterLookups);
  $('#mf_MTL')?.addEventListener('change', triggerMasterLookups);
  $('#mf_MaterialType')?.addEventListener('change', applyStandardValuesSmart);

  // Bind live automatic calculations on input changes
  $$('.dtr-calc-thick').forEach((inp) => {
    inp.addEventListener('input', () => computeThicknessMinMax(true));
  });
  $$('.dtr-calc-weight, .dtr-calc-sg, .dtr-calc-din, .dtr-calc-ross, .dtr-calc-shrink').forEach((inp) => {
    inp.addEventListener('input', () => {
      computeWeightAndPhysicals();
      evaluateMaterialOverallRatings();
    });
  });
  $$('.dtr-dir-inp, .dtr-std-inp').forEach((inp) => {
    inp.addEventListener('input', () => {
      if (inp.dataset.dirGroup) computeDirectionalGroup(inp.dataset.dirGroup);
      else computeAllDirectionalGroups();
      evaluateMaterialOverallRatings();
    });
  });
  $$('.dtr-6pt-inp').forEach((inp) => {
    inp.addEventListener('input', () => {
      if (inp.dataset['6pt']) compute6PointColorfastness(inp.dataset['6pt']);
      evaluateMaterialOverallRatings();
    });
  });
  $$('.dtr-crock-inp').forEach((inp) => {
    inp.addEventListener('input', () => {
      computeCrockingAndWeathering();
      evaluateMaterialOverallRatings();
    });
  });
  $$('.dtr-rate-inp').forEach((inp) => {
    inp.addEventListener('change', evaluateMaterialOverallRatings);
    inp.addEventListener('input', evaluateMaterialOverallRatings);
  });

  // Switch Material Form Slug
  $('#dtr-mat-form-slug')?.addEventListener('change', async (e) => {
    activeSlug = e.target.value;
    newMaterialRecord(false);
    await loadMaterialRecordList(activeSlug);
  });

  // Switch Record inside Material Form
  $('#dtr-mat-record-select')?.addEventListener('change', async (e) => {
    const id = e.target.value;
    if (id) await loadSingleMaterialRecord(activeSlug, id);
  });

  // + New Record: a blank form, only the Lab Ref No. is suggested
  const newMaterialRecord = (notify = true) => {
    const today = new Date().toISOString().slice(0, 10);
    populateMaterialForm({
      ID: '',
      LabRefNo: `DTP${today.replace(/-/g, '')}${activeSlug.toUpperCase().slice(0, 3)}-1`,
    }, { label: $('#dtr-mat-form-slug')?.selectedOptions[0]?.textContent.trim() || '' });
    $$('#dtr-material-form textarea').forEach((t) => { t.value = ''; });
    $$('#dtr-material-form input[type=radio], #dtr-material-form input[type=checkbox]').forEach((c) => { c.checked = false; });
    const sel = $('#dtr-mat-record-select');
    if (sel) sel.value = '';
    if (notify) showToast('Initialized new blank test report record.');
  };
  $('#btn-dtr-new')?.addEventListener('click', () => newMaterialRecord());

  // Stamp Reviewed
  $('#btn-dtr-review')?.addEventListener('click', () => {
    const reviewer = prompt('Enter Reviewer Name:', 'Faye Ann Elaysa');
    if (!reviewer) return;
    const rawAuth = $('[data-raw-col="AuthorizedBy"]');
    if (rawAuth) rawAuth.value = reviewer;
    const rawStat = $('[data-raw-col="AuthorizationStatus"]');
    if (rawStat) rawStat.value = 'Reviewed';
    showToast(`✓ Record stamped Reviewed by ${reviewer}`);
  });

  // Refresh & Apply Smart Standards
  $('#btn-dtr-refresh-calc')?.addEventListener('click', runFullMaterialRecalc);

  // Load Machine Results from \\192.168.32.45\OUTDATA\MATERIALS\
  $('#btn-dtr-load-machine')?.addEventListener('click', async () => {
    const labRefNo = $('#mf_LabRefNo')?.value.trim() || '';
    if (!labRefNo) {
      showToast('Enter a LabRefNo first before loading machine results.', true);
      return;
    }
    try {
      const res = await apiFetch('dtr/load-machine', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ slug: activeSlug, lab_ref_no: labRefNo }),
      });
      if (res && res.loadedFields) {
        Object.keys(res.loadedFields).forEach((k) => {
          const v = res.loadedFields[k];
          const el = $(`#mf_${CSS.escape(String(k))}`) || $(`[name="${CSS.escape(k)}"]`, $('#dtr-material-form'));
          if (el) el.value = v;
          const rawEl = $(`[data-raw-col="${CSS.escape(k)}"]`);
          if (rawEl) rawEl.value = v;
        });
        computeWeightAndPhysicals();
        computeAllDirectionalGroups();
        evaluateMaterialOverallRatings();
        if ($('#dtr-machine-status')) {
          $('#dtr-machine-status').textContent = res.message || 'Machine data loaded.';
        }
        showToast(`${res.message}`);
      }
    } catch (err) {
      showToast('Machine loader error: ' + (err.message || ''), true);
    }
  });

  // Save Material Report to dbnexus
  $('#btn-dtr-save')?.addEventListener('click', async () => {
    const fields = Object.assign({}, activeRecord);
    // Collect raw col inputs first
    $$('#dtr-raw-cols-container [data-raw-col]').forEach((inp) => {
      fields[inp.dataset.rawCol] = inp.value;
    });
    // Collect main form inputs (override raw cols)
    $$('#dtr-material-form [name]').forEach((inp) => {
      if (inp.name) fields[inp.name] = inp.value;
    });
    if (leatherSheet && !leatherSheet.hidden) {
      $$('[data-col]', leatherSheet).forEach((el) => {
        if (el.type === 'radio') { if (el.checked) fields[el.dataset.col] = el.value; } else fields[el.dataset.col] = el.value;
      });
    }

    try {
      const res = await apiFetch('dtr/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ slug: activeSlug, fields }),
      });
      if (res && res.ok) {
        if ($('#mf_ID')) $('#mf_ID').value = res.recordId;
        showToast(`✓ ${res.message} (Ready in InterLink Sync ComboBox!)`);
        window.nxMatLock && window.nxMatLock();   // the report is read only again after it was saved
        await loadMaterialRecordList(activeSlug, res.recordId);
      } else {
        showToast(res.error || 'Save failed.', true);
      }
    } catch (err) {
      showToast('Failed to save record.', true);
    }
  });

  // Material Test Report: read only first. An Admin or a Material PIC presses Edit; everybody else only reads (the server refuses their saves too).
  (() => {
    const panel = $('#dtr-panel-material');
    if (!panel) return;
    const canEdit = panel.dataset.canEdit === '1';
    const editBtn = $('#btn-mat-edit');
    const keep = '#dtr-mat-form-slug, #dtr-mat-record-select, #dtr-mat-search, [data-keep-unlocked]';
    const fields = () => $$('.lf-sheet input, .lf-sheet textarea, .lf-sheet select, #dtr-material-form input, #dtr-material-form textarea, #dtr-material-form select, #dtr-raw-cols-container input, #dtr-raw-cols-container textarea', panel)
      .filter((el) => !el.matches(keep) && el.type !== 'hidden');
    const actions = () => ['#btn-dtr-save', '#btn-dtr-refresh-calc', '#btn-dtr-load-machine'].map((s) => $(s)).filter(Boolean);
    let editing = false;
    let editRef = '';
    const curRef = () => ($('#mf_LabRefNo')?.value || '').trim();
    const apply = (on) => {
      editing = on;
      editRef = on ? curRef() : '';
      fields().forEach((el) => {
        const soft = el.tagName === 'SELECT' || el.type === 'radio' || el.type === 'checkbox' || el.type === 'file';
        if (on) {
          if (el.dataset.mlock === 'ro') { el.readOnly = false; }
          if (el.dataset.mlock === 'dis') { el.disabled = false; }
          delete el.dataset.mlock;
        } else if (soft) {
          if (!el.disabled) { el.disabled = true; el.dataset.mlock = 'dis'; }
        } else if (!el.readOnly) { el.readOnly = true; el.dataset.mlock = 'ro'; }
      });
      actions().forEach((b) => { b.hidden = !on; b.style.display = on ? '' : 'none'; });
      panel.classList.toggle('mat-readonly', !on);
      const vo = $('#dtr-mat-viewonly'); if (vo) vo.hidden = on;
      if (editBtn) { editBtn.hidden = on; }
    };
    window.nxMatLock = () => apply(false);
    window.nxMatEditing = () => editing;
    editBtn?.addEventListener('click', () => {
      if (!canEdit) return;
      if (!curRef()) { (window.nxToast || showToast)('Open a Lab Ref first, then press Edit.', 'warning'); return; }
      apply(true);
      (window.nxToast || showToast)('Editing ' + curRef() + '. Save report to NEXUS-L when you are done.', 'info');
    });
    // a different record or form: back to read only (also locks the fields the new record's sheet just drew)
    ['#dtr-mat-record-select', '#dtr-mat-form-slug'].forEach((sel) => $(sel)?.addEventListener('change', () => { setTimeout(() => apply(false), 0); setTimeout(() => apply(false), 1200); }));
    setInterval(() => { if (editing && curRef() !== editRef) apply(false); }, 700);
    // first run: read only (the sheets are drawn by now; run again once the first record has loaded)
    apply(false);
    setTimeout(() => { if (!editing) apply(false); }, 1500);
    setTimeout(() => { if (!editing) apply(false); }, 4000);
  })();

  $('#btn-toggle-raw-cols')?.addEventListener('click', () => {
    $('#dtr-raw-cols-container')?.classList.toggle('hidden');
  });

  // =========================================================================
  // 5. WHOLE SHOE BONDING TEST FORM (U60) & SPOT/TOLERANCE COMPUTATIONS
  // =========================================================================
  const AGE_SPOT_LIMITS = {
    TODDLER: 14,
    KIDS: 16,
    CHILDREN: 24,
    ADULT: 35,
  };

  // VF-F01 standard spec per age category (matches deriveBondingFields() on the PHP side).
  const AGE_STD_DEFAULTS = {
    TODDLER: '2.0',
    KIDS: '2.0',
    CHILDREN: '2.5',
    ADULT: '3.0',
  };

  // Access rules (BondingReportV2 "Test Log - Bonding": UpdateBondingResult / UpdateFillerResult): PASS >= STD,
  // TOLERABLE >= 80% of STD, else FAIL. UTF/FTO: Adult 3.0 / 2.4, Children 2.5 / 2.0; Center IS/OS (FiTO): 1.0 / 0.8
  const FITO_STD = 1.0;
  const bondingTolerance = (std) => Math.round(std * 0.8 * 100) / 100;
  const evaluateSpotVerdict = (val, std) => {
    if (!Number.isFinite(val) || val <= 0) return null;
    if (val >= std) return 'PASS';
    if (val >= bondingTolerance(std)) return 'TOLERABLE';
    return 'FAIL';
  };

  // The standard (kgf/cm) of the report in front of you: the one the sheet shows (the style's row in tbl_bonding_test_std: UA 3.0, UY 2.5, TD 2.5 ...),
  // else the Standard Spec box, else the age category's default. The preview, the print and the verdicts all use this one value.
  const currentBondingStd = () => {
    const shown = parseFloat(($('.vf-std-disp')?.textContent || '').replace(',', '.'));
    if (Number.isFinite(shown) && shown > 0) return shown;
    const box = parseFloat($('#bf_STD')?.value);
    if (Number.isFinite(box) && box > 0) return box;
    const age = parseFloat(AGE_STD_DEFAULTS[($('#bf_AgeCategory')?.value || '').toUpperCase()]);
    return Number.isFinite(age) && age > 0 ? age : 2.5;
  };

  const computeBondingPoints = () => {
    const ageCat = ($('#bf_AgeCategory')?.value || 'ADULT').toUpperCase();
    const maxSpots = AGE_SPOT_LIMITS[ageCat] || 35;
    const stdVal = currentBondingStd();

    // Show/hide rows up to maxSpots
    $$('#dtr-bonding-points-tbody [data-spot-row]').forEach((tr) => {
      const pt = Number(tr.dataset.spotRow);
      tr.style.opacity = pt <= maxSpots ? '1' : '0.45';
    });

    const calcSeries = (prefix, limit, specStd) => {
      const vals = [];
      let hasFail = false;
      let hasTol = false;
      for (let i = 1; i <= limit; i++) {
        const inp = $(`#bp_${prefix}${i}`);
        if (!inp) continue;
        const v = parseFloat(inp.value ?? '');
        inp.classList.remove('dtr-rate-pass', 'dtr-rate-warn', 'dtr-rate-fail');
        if (Number.isFinite(v) && v > 0) {
          vals.push(v);
          const verdict = evaluateSpotVerdict(v, specStd);
          if (verdict === 'FAIL') {
            hasFail = true;
            inp.classList.add('dtr-rate-fail');
          } else if (verdict === 'TOLERABLE') {
            hasTol = true;
            inp.classList.add('dtr-rate-warn');
          } else {
            inp.classList.add('dtr-rate-pass');
          }
        }
      }
      if (!vals.length) return { min: '', max: '', ave: '', res: '' };
      return {
        min: roundTo(Math.min(...vals), 2),
        max: roundTo(Math.max(...vals), 2),
        ave: roundTo(vals.reduce((a, b) => a + b, 0) / vals.length, 2),
        res: hasFail ? 'FAIL' : (hasTol ? 'TOLERABLE' : 'PASS'),
      };
    };

    const utf = calcSeries('UTFpoint', maxSpots, stdVal);
    const fto = calcSeries('FTOpoint', maxSpots, stdVal);
    const fito = calcSeries('FiTO', 13, FITO_STD);

    if ($('#bp_UTFMin')) $('#bp_UTFMin').value = utf.min;
    if ($('#bp_UTFMax')) $('#bp_UTFMax').value = utf.max;
    if ($('#bp_UTFAverage')) $('#bp_UTFAverage').value = utf.ave;
    if ($('#bp_UTF_Result')) { $('#bp_UTF_Result').value = utf.res; styleRatingInput($('#bp_UTF_Result')); }

    if ($('#bp_FTOMin')) $('#bp_FTOMin').value = fto.min;
    if ($('#bp_FTOMax')) $('#bp_FTOMax').value = fto.max;
    if ($('#bp_FTOAverage')) $('#bp_FTOAverage').value = fto.ave;
    if ($('#bp_FTO_Result')) { $('#bp_FTO_Result').value = fto.res; styleRatingInput($('#bp_FTO_Result')); }

    if ($('#bp_FiTOMin')) $('#bp_FiTOMin').value = fito.min;
    if ($('#bp_FiTOMax')) $('#bp_FiTOMax').value = fito.max;
    if ($('#bp_FiTOAverage')) $('#bp_FiTOAverage').value = fito.ave;
    if ($('#bp_FiTO_Result')) { $('#bp_FiTO_Result').value = fito.res; styleRatingInput($('#bp_FiTO_Result')); }

    // no spot readings -> keep what the report already has (blank on a new report)
    const allRes = [utf.res, fto.res, fito.res].filter(Boolean);
    if ($('#bf_Result')) {
      if (allRes.length) {
        $('#bf_Result').value = allRes.includes('FAIL') ? 'FAIL' : (allRes.includes('TOLERABLE') ? 'TOLERABLE' : 'PASS');
      }
      styleRatingInput($('#bf_Result'));
    }
    scheduleFormSketch();
  };

  const loadBondingRecordList = async (selectedId = null) => {
    const sel = $('#dtr-bonding-record-select');
    try {
      if (sel) sel.innerHTML = '<option value="">Loading reports…</option>';
      const res = await apiFetch('dtr/records?slug=bonding');
      if (!sel || !res.rows) return;
      if (!res.rows.length) { sel.innerHTML = '<option value="">No bonding reports yet</option>'; return; }
      sel.innerHTML = '<option value="">- New report (not saved yet) -</option>' + res.rows.map((r) => `
        <option value="${escHtml(r.source_id)}" ${String(r.source_id) === String(selectedId) ? 'selected' : ''}>
          ${escHtml(r.lab_ref_no)} · ${escHtml(r.model_style || '')} · ${escHtml(r.color || '')} [${escHtml(r.overall_result || 'Pass')}]
        </option>
      `).join('');
    } catch (err) {
      console.error(err);
      if (sel) sel.innerHTML = '<option value="">Could not load reports - reload the page</option>';
      if (window.nxToast) nxToast('The bonding report list could not be loaded.', 'error');
    }
  };

  // PO/Lot No (production code) loaded -> Shipped Date (the order's CRD) comes from dberp.production_orders; PO Quantity (its pairs) fills when empty
  // The report shows dates like the Access sheet (MM-DD-YYYY: 11-13-2026); they are stored as YYYY-MM-DD
  const BOND_DATES = ['ShippedDate', 'ProductionDate', 'ReceivedDate', 'TestedDate', 'FinishedDate'];
  const toUsDate = (v) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(v || '').trim()); return m ? `${m[2]}-${m[3]}-${m[1]}` : String(v || ''); };
  const toIsoDate = (v) => { const m = /^(\d{1,2})-(\d{1,2})-(\d{4})$/.exec(String(v || '').trim()); return m ? `${m[3]}-${m[1].padStart(2, '0')}-${m[2].padStart(2, '0')}` : String(v || ''); };
  const showBondDates = () => BOND_DATES.forEach((k) => { const el = $(`#bf_${k}`); if (el && el.value) el.value = toUsDate(el.value); });
  // PO/Lot No shows the order's PO number (production_orders.po_no, e.g. 5200676466); the Production Code stays in data-code for the lookup and the save
  const bondProdCode = () => {
    const el = $('#bf_POLotNO');
    if (el?.dataset.code) return el.dataset.code.trim();
    const parts = (el?.value || '').split('/').map((s) => s.trim()).filter(Boolean);
    return parts.find((s) => /^VSP/i.test(s)) || parts[0] || '';
  };
  $('#bf_POLotNO')?.addEventListener('input', (e) => { delete e.target.dataset.code; });
  // Prod. Code line + shoe picture (\\ERP Image\<MTL #>.JPG, click to enlarge)
  const showProdCode = (code, mtl) => {
    const pv = $('#bf_ProdCodeView'), img = $('#bf_ProdImg');
    if (pv) pv.value = code || '';
    if (!img) return;
    const m = (mtl || img.dataset.mtl || '').trim();
    if (!/^[A-Za-z0-9_-]{3,40}$/.test(m)) { img.hidden = true; img.removeAttribute('src'); return; }
    img.onload = () => { img.hidden = false; };
    img.onerror = () => { img.hidden = true; };
    img.src = `${BASE_URL}dtr/erp-image?mtl=${encodeURIComponent(m)}`;
  };
  const fillFromErpOrder = async () => {
    const code = bondProdCode();
    showProdCode(code);
    const ship = $('#bf_ShippedDate'), qty = $('#bf_POQuantity'), po = $('#bf_POLotNO');
    if (!code || !ship || !qty) return;
    try {
      const res = await apiFetch(`dtr/erp-po?code=${encodeURIComponent(code)}`);
      if (!res || !res.ok || !res.order) return;
      if (res.order.shipped) ship.value = toUsDate(res.order.shipped);   // Shipped Date is always the order's CRD (production_orders.crd) of this Prod. Code
      if (!qty.value.trim() && res.order.qty) qty.value = res.order.qty;
      if (res.order.mtl && !($('#bf_ProdImg')?.dataset.mtl || '').trim()) showProdCode(code, res.order.mtl);
      if (po && res.order.po && bondProdCode() === code) { po.dataset.code = code; po.value = `${res.order.po} / ${code}`; }
    } catch (e) { /* the ERP is not reachable: the boxes stay as they are */ }
  };
  $('#bf_POLotNO')?.addEventListener('change', fillFromErpOrder);

  // A loaded report is locked: every field that holds data is read-only until the Edit button is clicked (empty fields stay open for input)
  let reportEditing = true;
  const setEditLabel = () => {
    const t = $('#btn-bond-recalc-txt');
    if (t) t.textContent = reportEditing ? 'Done' : 'Edit';
  };
  const unlockForm = (formSel) => {
    $$(`${formSel} [data-locked]`).forEach((el) => {
      if (el.type === 'radio' || el.type === 'checkbox' || el.tagName === 'SELECT') el.removeAttribute('tabindex');
      else el.readOnly = false;
      el.removeAttribute('data-locked');
    });
  };
  const lockForm = (formSel) => {
    unlockForm(formSel);
    const radiosOn = new Set($$(`${formSel} input[type="radio"]:checked`).map((r) => r.name));
    $$(`${formSel} input, ${formSel} select, ${formSel} textarea`).forEach((el) => {
      if (el.type === 'hidden' || el.type === 'button' || el.type === 'submit' || el.readOnly || el.disabled) return;
      let lock;
      if (el.type === 'radio') lock = radiosOn.has(el.name);
      else if (el.type === 'checkbox') lock = el.checked;
      else lock = String(el.value ?? '').trim() !== '';
      if (!lock) return;
      // radios / checkboxes / selects are not "disabled" (that greys them out): the CSS of [data-locked] just stops clicks, so they look like normal selected ones
      if (el.type === 'radio' || el.type === 'checkbox' || el.tagName === 'SELECT') el.tabIndex = -1;
      else el.readOnly = true;
      el.setAttribute('data-locked', '1');
    });
    reportEditing = false;
    setEditLabel();
  };
  const openForm = (formSel) => {   // a new, blank report: nothing is locked
    unlockForm(formSel);
    reportEditing = true;
    setEditLabel();
  };

  const loadSingleBondingRecord = async (sourceId = 0, labRefNo = '') => {
    try {
      const query = labRefNo ? `lab_ref_no=${encodeURIComponent(labRefNo)}` : `source_id=${encodeURIComponent(sourceId)}`;
      const res = await apiFetch(`dtr/record?slug=bonding&${query}`);
      if (!res || !res.ok) return;
      if (labRefNo && !Object.keys(res.record || {}).length) return;
      const rec = res.record || {};
      const bp = res.bondingPoints || {};
      activeBondingPoints = bp;

      Object.keys(rec).forEach((k) => {
        const el = $(`#bf_${k}`);
        if (el) el.value = rec[k] ?? '';
      });
      // a saved STD like "3" / "3.00" is not an option value ("3.0"): the select went blank and the result was judged against 2.5 (min 2.55 = PASS instead of TOLERABLE)
      const stdSel = $('#bf_STD');
      if (stdSel && stdSel.value === '') {
        const n = parseFloat(rec.STD);
        const byNum = Number.isFinite(n) ? [...stdSel.options].find((o) => Math.abs(parseFloat(o.value) - n) < 0.005) : null;
        const byAge = AGE_STD_DEFAULTS[String(rec.AgeCategory || bp.AgeCategory || 'ADULT').toUpperCase()] || '3.0';
        stdSel.value = byNum ? byNum.value : ([...stdSel.options].find((o) => parseFloat(o.value) === parseFloat(byAge))?.value || '3.0');
      }
      $$('#dtr-bonding-form [name^="cf_"]').forEach((el) => { el.value = (res.customValues || {})[el.name] ?? ''; });
      // Specimen Intake records keep the Production Code in the ETRF box: show it beside PO/Lot No instead
      // PO/Lot No shows "Production Code / MTL #"
      if ($('#bf_POLotNO') && /^DTP\d{8}[A-Z]{2,}[AB]\d+-[LR](-R?\d+)?$/i.test(rec.LabRefNo || '')) {
        const noteMtl = (rec.MTL || '').trim() || ((rec.LabNotesComments || '').match(/MTL ([^;]+)/) || [])[1]?.trim() || '';
        const swapped = !!rec.ETRFNo;   // older intake rows: code in ETRF No, MTL in PO/Lot No
        const code = (swapped ? rec.ETRFNo : (rec.POLotNO || '')).trim();
        const mtl = noteMtl || (swapped ? (rec.POLotNO || '').trim() : '');
        $('#bf_POLotNO').value = mtl ? `${code} / ${mtl}` : code;
        if (swapped && $('#bf_ETRFNo')) $('#bf_ETRFNo').value = '';
      }
      if ($('#bf_Technician')) $('#bf_Technician').value = withMachineNo($('#bf_Technician').value);   // add the machine no. to older records
      if ($('#bf_POLotNO')) { delete $('#bf_POLotNO').dataset.code; $('#bf_POLotNO').dataset.code = ($('#bf_POLotNO').value || '').split('/')[0].trim(); }
      showBondDates();
      if ($('#bf_ProdImg')) $('#bf_ProdImg').dataset.mtl = (rec.MTL || '').trim();
      fillFromErpOrder();

      for (let i = 1; i <= 35; i++) {
        if ($(`#bp_UTFpoint${i}`)) $(`#bp_UTFpoint${i}`).value = bp[`UTFpoint${i}`] ?? '';
        if ($(`#bp_FTOpoint${i}`)) $(`#bp_FTOpoint${i}`).value = bp[`FTOpoint${i}`] ?? '';
        if (i <= 13 && $(`#bp_FiTO${i}`)) $(`#bp_FiTO${i}`).value = bp[`FiTO${i}`] ?? '';
      }

      computeBondingPoints();
      lockForm('#dtr-bonding-form');
    } catch (err) {
      console.error(err);
    }
  };

  $('#dtr-bonding-record-select')?.addEventListener('change', (e) => {
    if (e.target.value) loadSingleBondingRecord(e.target.value);
  });
  // Lab Ref.No dropdown: the Lab Ref Nos saved in tbl_dtr_bonding_points; picking one loads its report
  let bondingLabRefs = [];
  const loadBondingLabRefs = async () => {
    try {
      const res = await apiFetch('dtr/bonding-labrefs');
      bondingLabRefs = (res && res.labRefs) || [];
      const dl = $('#bf_LabRefNo_list');
      if (dl) dl.innerHTML = bondingLabRefs.map((v) => `<option value="${escHtml(v)}"></option>`).join('');
    } catch (err) {
      console.error(err);
    }
  };
  $('#bf_LabRefNo')?.addEventListener('change', (e) => {
    const v = e.target.value.trim();
    if (v && bondingLabRefs.includes(v)) loadSingleBondingRecord(0, v);
  });
  // no dropdown: the report opens from the Lab Ref No link of the Lab Tracker list
  loadBondingLabRefs();
  $('#bf_AgeCategory')?.addEventListener('change', scheduleFormSketch);
  $('#bf_AgeCategory')?.addEventListener('change', (e) => {
    const std = AGE_STD_DEFAULTS[(e.target.value || '').toUpperCase()];
    if (std && $('#bf_STD')) $('#bf_STD').value = std;
    computeBondingPoints();
  });
  $('#bf_STD')?.addEventListener('change', computeBondingPoints);
  $('#bf_Side')?.addEventListener('change', scheduleFormSketch);
  scheduleFormSketch();
  $$('#dtr-bonding-points-tbody .dtr-bp-inp').forEach((inp) => {
    inp.addEventListener('input', computeBondingPoints);
  });
  // Edit: unlocks the locked fields; Done: recalculates the report and locks the filled fields again
  $('#btn-bond-recalc')?.addEventListener('click', () => {
    const formSel = isPull() ? '#dtr-pull-form' : '#dtr-bonding-form';
    if (!reportEditing) {
      unlockForm(formSel);
      reportEditing = true;
      setEditLabel();
      showToast('Fields unlocked: you can edit the report now.');
      return;
    }
    if (isPull()) computePull();
    else computeBondingPoints();
    lockForm(formSel);
    showToast('✓ Report recalculated and locked. Click Edit to change it again.');
  });

  // + New Bonding Report: a blank form, only the Lab Ref No. is suggested
  const newBondingRecord = (notify = true) => {
    const today = new Date().toISOString().slice(0, 10);
    openForm('#dtr-bonding-form');
    $$('#dtr-bonding-form input, #dtr-bonding-form select, #dtr-bonding-form textarea').forEach((el) => {
      if (el.type === 'radio' || el.type === 'checkbox') el.checked = false;
      else el.value = '';
    });
    activeBondingPoints = {};
    if ($('#bf_LabRefNo')) $('#bf_LabRefNo').value = `DTP${today.replace(/-/g, '')}SHB01-R`;
    if ($('#bf_Factory')) $('#bf_Factory').value = 'DATIAN SUBIC SHOES INC. (DTP)';   // default supplier, same text the saved records use
    const sel = $('#dtr-bonding-record-select');
    if (sel) sel.value = '';
    computeBondingPoints();
    if (notify) showToast('Initialized new Whole Shoe Bonding Test Report.');
  };

  $('#btn-bond-save')?.addEventListener('click', async () => {
    if (isPull()) return savePull();
    if ($('#bf_Technician')) $('#bf_Technician').value = withMachineNo($('#bf_Technician').value);
    const fields = {};
    $$('#dtr-bonding-form [name]').forEach((inp) => {
      if (inp.name) fields[inp.name] = inp.value;
    });
    if (fields.POLotNO) fields.POLotNO = bondProdCode();   // the field shows the PO number: the Production Code is what is stored
    BOND_DATES.forEach((k) => { if (fields[k]) fields[k] = toIsoDate(fields[k]); });
    // a Fail / Tolerable result must say how it failed (Quality Insights and the lab alerts read it)
    if (/^(fail|failed|tolerable)$/i.test(String(fields.Result || '').trim()) && !String(fields.ModeOfFailure || '').trim().replace(/^-$/, '')) {
      showToast('Write the Mode of Failure(s) first: a ' + String(fields.Result).trim().toUpperCase() + ' result needs it (for example Adhesive failure, Foxing to outsole).', true);
      const mf = $('#bf_ModeOfFailure'); if (mf) { mf.focus(); mf.classList.add('dtr-need'); setTimeout(() => mf.classList.remove('dtr-need'), 2500); }
      return;
    }
    const bondingPoints = {
      UTFMin: $('#bp_UTFMin')?.value || null,
      UTFMax: $('#bp_UTFMax')?.value || null,
      UTFAverage: $('#bp_UTFAverage')?.value || null,
      UTF_Result: $('#bp_UTF_Result')?.value || null,
      FTOMin: $('#bp_FTOMin')?.value || null,
      FTOMax: $('#bp_FTOMax')?.value || null,
      FTOAverage: $('#bp_FTOAverage')?.value || null,
      FTO_Result: $('#bp_FTO_Result')?.value || null,
      FiTOMin: $('#bp_FiTOMin')?.value || null,
      FiTOMax: $('#bp_FiTOMax')?.value || null,
      FiTOAverage: $('#bp_FiTOAverage')?.value || null,
      FiTO_Result: $('#bp_FiTO_Result')?.value || null,
      Result: $('#bf_Result')?.value || null,
    };
    $$('#dtr-bonding-points-tbody [data-bp-col]').forEach((inp) => {
      bondingPoints[inp.dataset.bpCol] = inp.value;
    });

    try {
      const res = await apiFetch('dtr/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ slug: 'bonding', fields, bondingPoints }),
      });
      if (res && res.ok) {
        if ($('#bf_BondingTestID')) $('#bf_BondingTestID').value = res.recordId;
        showToast(`✓ ${res.message}`);
        await loadBondingRecordList(res.recordId);
      }
    } catch (err) {
      showToast('Error saving Bonding Report.', true);
    }
  });

  // =========================================================================
  // 5b. PULL FOOTWEAR TEST REPORT (Lab VF 003) - shares the Shoe Bonding toolbar
  // =========================================================================
  const PULL_GROUPS = { left: 6, right: 6, other: 4 };
  const isPull = () => ($('#dtr-report-kind')?.value || 'bonding') === 'pull';
  const pullInputs = () => $$('#dtr-pull-form [data-pull]');
  const pullInput = (g, i, k) => $(`#dtr-pull-form [data-pull="${g}.${i}.${k}"]`);

  const syncPullRadios = () => {
    [['pf_MaterialSource', 'pf_ms_radio'], ['pf_TechSlot', 'pf_tech_radio']].forEach(([hid, name]) => {
      const val = $(`#${hid}`)?.value || '';
      $$(`#dtr-pull-form input[name="${name}"]`).forEach((r) => { r.checked = !!val && r.value === val; });
    });
  };
  $('#dtr-pull-form')?.addEventListener('change', (e) => {
    const r = e.target;
    if (r.type !== 'radio') return;
    const hid = r.name === 'pf_ms_radio' ? $('#pf_MaterialSource') : (r.name === 'pf_tech_radio' ? $('#pf_TechSlot') : null);
    if (hid) hid.value = r.value;
  });

  // Maximum + Rating per row. Standard is a minimum unless Tolerance says "maximum".
  const computePull = () => {
    let rated = 0;
    let failed = 0;
    Object.keys(PULL_GROUPS).forEach((g) => {
      for (let i = 0; i < PULL_GROUPS[g]; i++) {
        // readings of the row, e.g. "26.90 29.50 29.23"
        const vals = (pullInput(g, i, 'res')?.value || '').match(/\d+(?:\.\d+)?/g)?.map(Number) || [];
        pullInput(g, i, 'max').value = vals.length ? roundTo(Math.max(...vals), 3) : '';
        const std = parseFloat(pullInput(g, i, 'std').value);
        const isMax = /max/i.test(pullInput(g, i, 'tol').value);
        let rating = '';
        if (vals.length) {   // a row with readings is rated: against its standard when it has one, otherwise PASS
          const ok = Number.isFinite(std) ? (isMax ? Math.max(...vals) <= std : Math.min(...vals) >= std) : true;
          rating = ok ? 'PASS' : 'FAIL';
          rated++;
          if (!ok) failed++;
        }
        const out = pullInput(g, i, 'rating');
        out.value = rating;
        styleRatingInput(out);
      }
    });
    const res = $('#pf_Result');
    if (res) {
      if (rated) res.value = failed ? 'FAIL' : 'PASS';
      styleRatingInput(res);
    }
    fitPullTextareas();
  };
  // the readings wrap onto as many lines as they need (the row grows), so every value can be read
  const fitPullTextareas = () => {
    $$('#dtr-pull-form textarea.pf-res-ta').forEach((ta) => { ta.style.height = 'auto'; ta.style.height = Math.max(ta.scrollHeight, 18) + 'px'; });
  };
  $('#dtr-pull-form')?.addEventListener('input', (e) => {
    if (e.target.matches('[data-pull]')) computePull();
  });

  const resetPullForm = () => {
    $$('#dtr-pull-form [name]').forEach((el) => {
      if (el.type === 'radio') el.checked = false;
      else el.value = '';
    });
    pullInputs().forEach((el) => { el.value = el.defaultValue; });
    syncPullRadios();
  };

  const newPullRecord = (notify = true) => {
    const today = new Date().toISOString().slice(0, 10);
    openForm('#dtr-pull-form');
    resetPullForm();
    $('#pf_LabRefNo').value = `DTP${today.replace(/-/g, '')}PL01-R`;
    computePull();
    if (notify) showToast('Initialized new Pull Footwear Test Report.');
  };

  const loadPullRecord = async (sourceId, labRef) => {
    try {
      const res = await apiFetch(`dtr/record?slug=pull&source_id=${encodeURIComponent(sourceId || 0)}&lab_ref_no=${encodeURIComponent(labRef || '')}`);
      if (!res || !res.ok) return;
      const rec = res.record || {};
      resetPullForm();
      if (!rec.LabRefNo && labRef) { rec.LabRefNo = labRef; showToast(`No pull test record for ${labRef} yet.`, true); }
      Object.keys(rec).forEach((k) => {
        const el = $(`#pf_${k}`);
        if (el) el.value = rec[k] ?? '';
      });
      let data = null;
      try { data = JSON.parse(rec.PullData || 'null'); } catch (e) { data = null; }
      pullInputs().forEach((el) => {
        const [g, i, k] = el.dataset.pull.split('.');
        let v = data?.[g]?.[i]?.[k];
        if (k === 'res' && (v === undefined || v === null)) {   // older saves kept Lateral / Medial in two cells
          const o = data?.[g]?.[i] || {};
          v = [o.lat, o.med].filter((x) => x !== undefined && x !== null && x !== '').join(' ') || undefined;
        }
        el.value = v !== undefined && v !== null ? v : el.defaultValue;
      });
      syncPullRadios();
      unlockForm('#dtr-pull-form');   // (the reset above wrote into locked fields of the previous report)
      computePull();
      lockForm('#dtr-pull-form');
      setTimeout(fitPullTextareas, 60);
    } catch (err) {
      console.error(err);
    }
  };

  const savePull = async () => {
    const fields = {};
    $$('#dtr-pull-form [name]').forEach((el) => {
      if (el.type !== 'radio') fields[el.name] = el.value;
    });
    const data = { left: [], right: [], other: [] };
    pullInputs().forEach((el) => {
      const [g, i, k] = el.dataset.pull.split('.');
      (data[g][i] = data[g][i] || {})[k] = el.value;
    });
    fields.PullData = JSON.stringify(data);
    try {
      const res = await apiFetch('dtr/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ slug: 'pull', fields }),
      });
      if (res && res.ok) {
        $('#pf_ID').value = res.recordId;
        showToast(`✓ ${res.message}`);
      } else {
        showToast((res && res.error) || 'Save failed.', true);
      }
    } catch (err) {
      showToast('Error saving Pull Test Report.', true);
    }
  };

  const openPullPrintPreview = () => {
    const f = (n) => escHtml($(`#pf_${n}`)?.value || '');
    const c = (g, i, k) => escHtml(pullInput(g, i, k)?.value || '');
    const names = $$('#dtr-pull-form tbody tr td.vf-res-part-cell').map((td) => td.innerHTML);
    const sketches = $$('#dtr-pull-form .pf-sketch').map((td) => td.innerHTML);
    const ms = $('#pf_MaterialSource')?.value || '';
    const tech = $('#pf_TechSlot')?.value || '';
    const radio = (val, label, cur) => `<div>${cur === val ? '&#9673;' : '&#9675;'} ${label}</div>`;
    const group = (g, n, sketch) => {
      let rows = '';
      for (let i = 0; i < n; i++) {
        const name = g === 'other' ? c(g, i, 'item') : (names[(g === 'right' ? 6 : 0) + i] || '');
        const method = g === 'other' ? c(g, i, 'method') : 'VF-B04<br>(2019/05)';
        const unit = g === 'other' ? c(g, i, 'unit') : 'kgf';
        const rating = c(g, i, 'rating');
        const mat = g !== 'other' && (i === 2 || i === 3) ? c(g, i, 'lbl') : '';   // material of a rivet row: Pearl / Diamond
        const lbl = i === 0 ? 'Lateral side : ' : (i === 1 ? 'Medial side : ' : (mat ? mat.replace(/\s*:?\s*$/, '') + ' : ' : ''));
        const res = c(g, i, 'res');
        rows += `<tr>
          <td class="pf-p-l">${name}</td><td class="pf-p-c">${method}</td><td class="pf-p-c">${unit}</td>
          ${i === 0 ? `<td rowspan="${n + 4}" class="pf-p-sk">${sketch}</td>` : ''}
          <td>${res ? lbl + res : lbl}</td><td class="pf-p-c">${c(g, i, 'max')}</td>
          <td class="pf-p-c pf-p-blue">${c(g, i, 'std')}</td><td class="pf-p-c pf-p-blue">${c(g, i, 'tol')}</td>
          <td class="pf-p-c"><b class="${rating === 'FAIL' ? 'pf-p-fail' : ''}">${rating}</b></td></tr>`;
      }
      for (let e = 0; e < 4; e++) rows += '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
      return rows;
    };
    let otherRows = '';
    for (let i = 0; i < PULL_GROUPS.other; i++) {
      otherRows += `<tr>
        <td class="pf-p-l">${c('other', i, 'item')}</td><td class="pf-p-c">${c('other', i, 'method')}</td><td class="pf-p-c">${c('other', i, 'unit')}</td><td></td>
        <td>${c('other', i, 'res')}</td><td class="pf-p-c">${c('other', i, 'max')}</td>
        <td class="pf-p-c pf-p-blue">${c('other', i, 'std')}</td><td class="pf-p-c pf-p-blue">${c('other', i, 'tol')}</td><td class="pf-p-c"><b>${c('other', i, 'rating')}</b></td></tr>`;
    }
    const labRef = $('#pf_LabRefNo')?.value || 'Pull Test';

    const html = `
      <div class="vf-report-sheet pf-print">
        <div class="pf-p-top">
          <img class="rpt-logo-vf" src="${BASE_URL}assets/images/vf-logo.webp" alt="VF">
          <div class="pf-p-title"><h1>Pull Footwear Test Results</h1><h2>整鞋部件拉力测试</h2></div>
          <div class="pf-p-right"><div class="pf-p-res">* Overall Result <b class="${f('Result') === 'FAIL' ? 'pf-p-fail' : ''}">${f('Result')}</b></div><img class="rpt-logo-satra" src="${BASE_URL}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory"></div>
        </div>
        <div class="pf-p-ids">
          <div>Form # : Lab VF 003</div>
          <div>ETRF.No: <b>${f('ETRFNo')}</b></div>
          <div>Lab Ref.No: <b>${f('LabRefNo')}</b></div>
        </div>
        <div class="pf-p-head">
          <div>
            <div>Supplier/Factory(厂商) : <b>${f('Supplier')}</b></div>
            <div>Supplier's country(国家): <b>${f('SupplierCountry')}</b></div>
            <div>Material Description(材料):<b> ${f('MaterialDescription')}</b></div>
            <div>Model No./Style(鞋体): <b>${f('ModelNoStyle')}</b></div>
            <div>Color(颜色): <b>${f('Color')}</b></div>
            <div>P.O/Lot No(订单号码): <b>${f('POLotNo')}</b></div>
            <div>PO Quantity(订单数量): <b>${f('POQuantity')}</b></div>
            <div>Send by (提供方): <b>${f('SendBy')}</b></div>
          </div>
          <div>
            <div class="pf-p-radios">
              ${radio('Prod. Material', 'Prod.Material (量产材料)', ms)}${radio('Prod. Re-test', 'Prod.Re-test 量产重测', ms)}
              ${radio('New/Development', 'New/Development 开发新材料', ms)}${radio('Trial Test', 'Trial Test 试胶测试', ms)}
              ${radio('Sales Sample', 'Sales Sample (销售样品)', ms)}
            </div>
            <div class="pf-p-radios pf-p-techs">${['Tech 1', 'Tech 2', 'Tech 3', 'Tech 4'].map((t) => radio(t, t, tech)).join('')}</div>
            <div>Received Date(收到日期): <b>${f('ReceivedDate')}</b></div>
            <div>Tested Date(测试日期): <b>${f('TestedDate')}</b></div>
            <div>Finished Date(完成日期): <b>${f('FinishedDate')}</b></div>
            <div>Technician(技术员): <b>${f('Technician')}</b></div>
          </div>
        </div>
        <div class="pf-p-sec">Closures - Eyelet , D-ring , Hook , Webbing Pull Strength</div>
        <table class="pf-p-tbl">
          <colgroup><col style="width:17%"><col style="width:9%"><col style="width:5%"><col style="width:17%"><col style="width:22%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:6%"></colgroup>
          <thead>
            <tr><th>Test Items<br>测试项目</th><th>VF Test Method<br>测试方法</th><th>Units<br>单位</th><th>Test Sketch<br>测试部位</th><th>Test Results<br>测试结果</th><th>Maximum</th><th>Standard<br>标准</th><th>Tolerance<br>容许度</th><th>Rating<br>等级</th></tr>
          </thead>
          <tbody>
            ${group('left', 6, sketches[0] || '')}
            ${group('right', 6, sketches[1] || '')}
            <tr><td colspan="9" class="pf-p-oth">Other Types of Pull Test</td></tr>
            ${otherRows}
          </tbody>
        </table>
        <div class="pf-p-foot">
          <div>Comments 备注 : ${f('Comments')}</div>
          <div>
            <div>Reviewed by 检查: <b>${f('ReviewedBy')}</b></div>
            <div>Approved by 核准: <b>${f('ApprovedBy')}</b></div>
          </div>
        </div>
      </div>
    `;
    $('#dtr-print-modal-title').textContent = `Pull Footwear Test Report — ${labRef}`;
    $('#dtr-print-sheet-container').innerHTML = html;
    $('#dtr-print-modal-backdrop')?.classList.remove('hidden');
  };

  // Report type picker (Bonding Report / Pull Test Report) in the Shoe Bonding toolbar
  const setReportKind = (kind) => {
    const pull = kind === 'pull';
    const sel = $('#dtr-report-kind');
    if (sel && sel.value !== kind) sel.value = kind;
    const bf = $('#dtr-bonding-form');
    const pf = $('#dtr-pull-form');
    if (bf) bf.hidden = pull;
    if (pf) pf.hidden = !pull;
    $$('.dtr-only-bonding').forEach((el) => { el.hidden = pull; });
    setEditLabel();
  };
  $('#dtr-report-kind')?.addEventListener('change', (e) => {
    setReportKind(e.target.value);
    if (e.target.value === 'pull') newPullRecord();
    else newBondingRecord();
  });

  // =========================================================================
  // 6. BUILT-IN LAB CALCULATORS (EXACT VBA FORMULAS)
  // =========================================================================
  const updateCalculators = () => {
    // 1. Weight Calculator: Round((Raw / 100) * 10000, 2)
    const rw1 = parseFloat($('#calc_raw_w1')?.value ?? '0') || 0;
    const rw2 = parseFloat($('#calc_raw_w2')?.value ?? '0') || 0;
    const rw3 = parseFloat($('#calc_raw_w3')?.value ?? '0') || 0;
    const w1 = roundTo((rw1 / 100) * 10000, 2);
    const w2 = roundTo((rw2 / 100) * 10000, 2);
    const w3 = roundTo((rw3 / 100) * 10000, 2);
    const wave = roundTo((w1 + w2 + w3) / 3, 2);
    if ($('#calc_res_w1')) $('#calc_res_w1').value = w1;
    if ($('#calc_res_w2')) $('#calc_res_w2').value = w2;
    if ($('#calc_res_w3')) $('#calc_res_w3').value = w3;
    if ($('#calc_res_wave')) $('#calc_res_wave').textContent = wave;

    // 2. Specific Gravity Calculator: Round(RawW / ((10 * 10) * (Thickness / 10)), 3)
    const sgW1 = parseFloat($('#calc_sg_w1')?.value ?? '0') || 0;
    const sgW2 = parseFloat($('#calc_sg_w2')?.value ?? '0') || 0;
    const sgW3 = parseFloat($('#calc_sg_w3')?.value ?? '0') || 0;
    const sgT1 = parseFloat($('#calc_sg_t1')?.value ?? '1') || 1;
    const sgT2 = parseFloat($('#calc_sg_t2')?.value ?? '1') || 1;
    const sgT3 = parseFloat($('#calc_sg_t3')?.value ?? '1') || 1;
    const sg1 = roundTo(sgW1 / (100 * (sgT1 / 10)), 3);
    const sg2 = roundTo(sgW2 / (100 * (sgT2 / 10)), 3);
    const sg3 = roundTo(sgW3 / (100 * (sgT3 / 10)), 3);
    const sgAve = roundTo((sg1 + sg2 + sg3) / 3, 3);
    if ($('#calc_res_sg_ave')) $('#calc_res_sg_ave').textContent = `${sg1}, ${sg2}, ${sg3} (Ave: ${sgAve})`;

    // 3. Compression Set Calculator: Round(((Before - After) / (Before - Spacer)) * 100, 1)
    const cpB1 = parseFloat($('#calc_cp_b1')?.value ?? '10') || 10;
    const cpA1 = parseFloat($('#calc_cp_a1')?.value ?? '9') || 9;
    const cpB2 = parseFloat($('#calc_cp_b2')?.value ?? '10') || 10;
    const cpA2 = parseFloat($('#calc_cp_a2')?.value ?? '9') || 9;
    const cpA3 = parseFloat($('#calc_cp_a3')?.value ?? '9') || 9;
    const cpSp = parseFloat($('#calc_cp_sp')?.value ?? '0') || 0;
    const denom1 = (cpB1 - cpSp) !== 0 ? (cpB1 - cpSp) : 1;
    const denom2 = (cpB2 - cpSp) !== 0 ? (cpB2 - cpSp) : 1;
    const cp1 = roundTo(((cpB1 - cpA1) / denom1) * 100, 1);
    const cp2 = roundTo(((cpB2 - cpA2) / denom2) * 100, 1);
    const cp3 = roundTo(((cpB1 - cpA3) / denom1) * 100, 1);
    const cpAve = roundTo((cp1 + cp2 + cp3) / 3, 1);
    if ($('#calc_res_cp_ave')) $('#calc_res_cp_ave').textContent = `${cpAve}% (${cp1}%, ${cp2}%, ${cp3}%)`;

    // 4. DIN Abrasion Calculator: Round((((Wb - Wa) / SG) * (200 / SA)) * 1000, 2)
    const dinWb = parseFloat($('#calc_din_wb')?.value ?? '0') || 0;
    const dinWa = parseFloat($('#calc_din_wa')?.value ?? '0') || 0;
    const dinSg = parseFloat($('#calc_din_sg')?.value ?? '1') || 1;
    const dinSa = parseFloat($('#calc_din_sa')?.value ?? '200') || 200;
    const dinVal = roundTo((((dinWb - dinWa) / dinSg) * (200 / dinSa)) * 1000, 2);
    if ($('#calc_res_din')) $('#calc_res_din').textContent = dinVal;

    // 5. Ross Flex Calculator: Round(((Lf - Li) / Li) * 100, 2)
    const rfLi1 = parseFloat($('#calc_rf_li1')?.value ?? '2.5') || 2.5;
    const rfLf1 = parseFloat($('#calc_rf_lf1')?.value ?? '2.5') || 2.5;
    const rfLi2 = parseFloat($('#calc_rf_li2')?.value ?? '2.5') || 2.5;
    const rfLf2 = parseFloat($('#calc_rf_lf2')?.value ?? '2.5') || 2.5;
    const rf1 = roundTo(((rfLf1 - rfLi1) / rfLi1) * 100, 2);
    const rf2 = roundTo(((rfLf2 - rfLi2) / rfLi2) * 100, 2);
    if ($('#calc_res_rf')) $('#calc_res_rf').textContent = `${rf1}% / ${rf2}%`;

    // 6. Shrinkage Calculator: Round(((Before - After) / Before) * 100, 2)
    const shLb = parseFloat($('#calc_sh_lb')?.value ?? '100') || 100;
    const shLa = parseFloat($('#calc_sh_la')?.value ?? '100') || 100;
    const shWb = parseFloat($('#calc_sh_wb')?.value ?? '100') || 100;
    const shWa = parseFloat($('#calc_sh_wa')?.value ?? '100') || 100;
    const shL = roundTo(((shLb - shLa) / shLb) * 100, 2);
    const shW = roundTo(((shWb - shWa) / shWb) * 100, 2);
    if ($('#calc_res_sh')) $('#calc_res_sh').textContent = `L: ${shL}% / W: ${shW}%`;
  };

  $$('#dtr-panel-calculators input').forEach((inp) => {
    inp.addEventListener('input', updateCalculators);
  });

  $('#btn-apply-calc-weight')?.addEventListener('click', () => {
    if ($('#mf_Weight1')) $('#mf_Weight1').value = $('#calc_res_w1')?.value || '';
    if ($('#mf_Weight2')) $('#mf_Weight2').value = $('#calc_res_w2')?.value || '';
    if ($('#mf_Weight3')) $('#mf_Weight3').value = $('#calc_res_w3')?.value || '';
    computeWeightAndPhysicals();
    evaluateMaterialOverallRatings();
    switchTab('material');
    showToast('✓ Applied calculated Weight 1..3 and WAve to Material Form.');
  });

  $('#btn-apply-calc-sg')?.addEventListener('click', () => {
    const sgW1 = parseFloat($('#calc_sg_w1')?.value ?? '0') || 0;
    const sgW2 = parseFloat($('#calc_sg_w2')?.value ?? '0') || 0;
    const sgW3 = parseFloat($('#calc_sg_w3')?.value ?? '0') || 0;
    const sgT1 = parseFloat($('#calc_sg_t1')?.value ?? '1') || 1;
    const sgT2 = parseFloat($('#calc_sg_t2')?.value ?? '1') || 1;
    const sgT3 = parseFloat($('#calc_sg_t3')?.value ?? '1') || 1;
    if ($('#mf_SG1')) $('#mf_SG1').value = roundTo(sgW1 / (100 * (sgT1 / 10)), 3);
    if ($('#mf_SG2')) $('#mf_SG2').value = roundTo(sgW2 / (100 * (sgT2 / 10)), 3);
    if ($('#mf_SG3')) $('#mf_SG3').value = roundTo(sgW3 / (100 * (sgT3 / 10)), 3);
    computeWeightAndPhysicals();
    evaluateMaterialOverallRatings();
    switchTab('material');
    showToast('✓ Applied Specific Gravity SG1..3 and SG_Ave to Material Form.');
  });

  $('#btn-apply-calc-din')?.addEventListener('click', () => {
    const val = $('#calc_res_din')?.textContent || '';
    if ($('#mf_DIN_1')) $('#mf_DIN_1').value = val;
    computeWeightAndPhysicals();
    evaluateMaterialOverallRatings();
    switchTab('material');
    showToast('✓ Applied DIN Abrasion value to Material Form.');
  });

  $('#btn-apply-calc-ross')?.addEventListener('click', () => {
    const rfLi1 = parseFloat($('#calc_rf_li1')?.value ?? '2.5') || 2.5;
    const rfLf1 = parseFloat($('#calc_rf_lf1')?.value ?? '2.5') || 2.5;
    const rfLi2 = parseFloat($('#calc_rf_li2')?.value ?? '2.5') || 2.5;
    const rfLf2 = parseFloat($('#calc_rf_lf2')?.value ?? '2.5') || 2.5;
    if ($('#mf_RossFlex_1')) $('#mf_RossFlex_1').value = roundTo(((rfLf1 - rfLi1) / rfLi1) * 100, 2);
    if ($('#mf_RossFlex_2')) $('#mf_RossFlex_2').value = roundTo(((rfLf2 - rfLi2) / rfLi2) * 100, 2);
    computeWeightAndPhysicals();
    evaluateMaterialOverallRatings();
    switchTab('material');
    showToast('✓ Applied Ross Flex 1 & 2 to Material Form.');
  });

  $('#btn-apply-calc-shrink')?.addEventListener('click', () => {
    const shLb = parseFloat($('#calc_sh_lb')?.value ?? '100') || 100;
    const shLa = parseFloat($('#calc_sh_la')?.value ?? '100') || 100;
    const shWb = parseFloat($('#calc_sh_wb')?.value ?? '100') || 100;
    const shWa = parseFloat($('#calc_sh_wa')?.value ?? '100') || 100;
    if ($('#mf_ShrinkageL')) $('#mf_ShrinkageL').value = roundTo(((shLb - shLa) / shLb) * 100, 2);
    if ($('#mf_ShrinkageW')) $('#mf_ShrinkageW').value = roundTo(((shWb - shWa) / shWb) * 100, 2);
    computeWeightAndPhysicals();
    evaluateMaterialOverallRatings();
    switchTab('material');
    showToast('✓ Applied Shrinkage L & W to Material Form.');
  });

  // Live Standards & Lookup Explorer
  const runStandardsExplorer = async () => {
    const mt = $('#exp_mat_type')?.value || 'Canvas';
    const tAve = $('#exp_t_ave')?.value || '1.25';
    try {
      const res = await apiFetch(`dtr/standards?material_type=${encodeURIComponent(mt)}&t_ave=${encodeURIComponent(tAve)}`);
      const tbody = $('#exp-standards-tbody');
      if (!tbody || !res.standards) return;
      tbody.innerHTML = res.standards.map((s) => `
        <tr>
          <td><b>${escHtml(s.MaterialType)}</b></td>
          <td class="mono"><code>${escHtml(s.TestMethod)}</code></td>
          <td class="mono"><b>${escHtml(s.StandardValue ?? '-')}</b></td>
          <td class="mono">${escHtml(s.Tolerance ?? '-')}</td>
          <td class="mono">${escHtml(s.TMinC ?? '-')}</td>
          <td class="mono">${escHtml(s.TMaxC ?? '-')}</td>
        </tr>
      `).join('');
    } catch (err) {
      console.error(err);
    }
  };
  $('#btn-exp-run')?.addEventListener('click', runStandardsExplorer);
  $('#exp_mat_type')?.addEventListener('change', runStandardsExplorer);

  // =========================================================================
  // 7. PRINT PREVIEW & PDF EXPORT MODAL (ACCESS REPORT PARITY)
  // =========================================================================
  const openMaterialPrintPreview = () => {
    const labRef = $('#mf_LabRefNo')?.value || 'DTP-LAB-PREVIEW';
    const tr = $('#mf_TR')?.value || '-';
    const matType = $('#mf_MaterialType')?.value || 'Material';
    const matCode = $('#mf_MaterialCode')?.value || '-';
    const matDesc = $('#mf_MaterialDescription')?.value || '-';
    const supplier = $('#mf_SupplierFactory')?.value || 'DATIAN SUBIC SHOES INC.';
    const modelStyle = $('#mf_ModelNoStyle')?.value || '-';
    const color = $('#mf_Color')?.value || '-';
    const poLot = $('#mf_POLotNo')?.value || '-';
    const mtl = $('#mf_MTL')?.value || '-';
    // the loaded record (plus anything edited in the field editor): not every column has its own input on the form
    const rec = Object.assign({}, activeRecord || {});
    $$('[data-raw-col]').forEach((i) => { rec[i.dataset.rawCol] = i.value; });
    const pick = (...names) => {
      for (const n of names) {
        const v = $("#mf_" + CSS.escape(String(n)))?.value;
        if (v !== undefined && String(v).trim() !== '') return String(v).trim();
        const r = rec[n];
        if (r !== undefined && r !== null && String(r).trim() !== '') return String(r).trim();
      }
      return '';
    };
    const fmtDate = (v) => String(v || '').replace(/ 00:00:00$/, '');
    const testedDate = fmtDate(pick('TestedDate', 'Tested Date')) || new Date().toISOString().slice(0, 10);
    const tech = pick('Technician', 'TestedBy');
    // the rest of the form's header block: country, quantity, who sent it, received / finished dates, and where the sample comes from
    const country = pick("Supplier's Country", 'SupplierCountry', 'Country');
    const qty = pick('Quantity', 'PO/LotQuantity');
    const sendBy = pick('SendBy', 'Send by');
    const recDate = fmtDate(pick('RecievedDate', 'ReceivedDate'));
    const finDate = fmtDate(pick('FinishedDate'));
    const source = pick('MaterialSource');
    const srcOpts = [['Prod. Material', 'Prod. Material 量产材料'], ['Prod. Re-test', 'Prod. Re-test 量产重测'], ['New/Development', 'New/Development 开发按新材料'], ['Sales Sample', 'Sales Sample (销售样品)']]
      .map(([k, label]) => `<span style="white-space:nowrap;margin-right:14px"><b style="color:${source === k ? '#1d4ed8' : '#94a3b8'}">${source === k ? '●' : '○'}</b> ${source === k ? '<b style="color:#1d4ed8">' + escHtml(label) + '</b>' : escHtml(label)}</span>`).join('');
    const techOpts = [1, 2, 3, 4].map((n) => `<span style="margin-right:12px"><b style="color:#94a3b8">○</b> Tech ${n}</span>`).join('');
    const reviewer = pick('AuthorizedBy', 'ReviewedBy');
    const revDate = fmtDate(pick('AuthorizedDate'));
    const overall = (pick('Remarks') || 'PASS').toUpperCase();

    const stampCls = overall === 'PASS' ? '' : (overall === 'TOLERABLE' ? 'stamp-tol' : 'stamp-fail');

    // Build Test Items Rows
    const items = [];
    const addDirRow = (name, unit, lAve, lStd, lRate, wAve, wStd, wRate) => {
      if (lAve || wAve) {
        items.push(`
          <tr>
            <td class="text-left"><b>${name} — Length (L)</b></td>
            <td>${unit}</td>
            <td><b>${lAve || '-'}</b></td>
            <td>${lStd || '-'}</td>
            <td><b class="${lRate === 'PASS' ? 'dtr-rate-pass' : (lRate === 'TOLERABLE' ? 'dtr-rate-warn' : 'dtr-rate-fail')}">${lRate || '-'}</b></td>
          </tr>
          <tr>
            <td class="text-left"><b>${name} — Width (W)</b></td>
            <td>${unit}</td>
            <td><b>${wAve || '-'}</b></td>
            <td>${wStd || '-'}</td>
            <td><b class="${wRate === 'PASS' ? 'dtr-rate-pass' : (wRate === 'TOLERABLE' ? 'dtr-rate-warn' : 'dtr-rate-fail')}">${wRate || '-'}</b></td>
          </tr>
        `);
      }
    };

    addDirRow('Tensile Strength', 'kg', $('#mf_TensileLAve')?.value, $('#mf_Tensile_L')?.value, $('#mf_TensileLRating')?.value, $('#mf_TensileWAve')?.value, $('#mf_Tensile_W')?.value, $('#mf_TensileWRating')?.value);
    addDirRow('Elongation', '%', $('#mf_ElongationLAve')?.value, $('#mf_Elongation_L')?.value, $('#mf_ElongationLRating')?.value, $('#mf_ElongationWAve')?.value, $('#mf_Elongation_W')?.value, $('#mf_ElongationWRating')?.value);
    addDirRow('Trouser / Wing Tear', 'kg', $('#mf_TrouserTearLAve')?.value, $('#mf_TrouserTear_L')?.value, $('#mf_TrouserTearLRating')?.value, $('#mf_TrouserTearWAve')?.value, $('#mf_TrouserTear_W')?.value, $('#mf_TrouserTearWRating')?.value);
    addDirRow('Stitch Tear', 'kg', $('#mf_StitchTearLAve')?.value, $('#mf_StitchTear_L')?.value, $('#mf_StitchTearLRating')?.value, $('#mf_StitchTearWAve')?.value, $('#mf_StitchTear_W')?.value, $('#mf_StitchTearWRating')?.value);

    if ($('#mf_Text3977')?.value) {
      items.push(`
        <tr>
          <td class="text-left"><b>Mullen Bursting Strength</b></td>
          <td>kg/cm²</td>
          <td><b>${$('#mf_Text3977')?.value}</b></td>
          <td>${$('#mf_MullenBurst')?.value || '-'}</td>
          <td><b>${$('#mf_MullenBurstRating')?.value || '-'}</b></td>
        </tr>
      `);
    }
    if ($('#mf_DieC_Ave')?.value) {
      items.push(`
        <tr>
          <td class="text-left"><b>Die C Tear / Edge Crush</b></td>
          <td>kg</td>
          <td><b>${$('#mf_DieC_Ave')?.value}</b></td>
          <td>${$('#mf_DieC_Std')?.value || '-'}</td>
          <td><b>${$('#mf_DieC_Rate')?.value || '-'}</b></td>
        </tr>
      `);
    }
    if ($('#mf_WAve')?.value) {
      items.push(`
        <tr>
          <td class="text-left"><b>Fabric Weight (Grammage)</b></td>
          <td>g/m²</td>
          <td><b>${$('#mf_WAve')?.value}</b></td>
          <td>${$('#mf_W_Std')?.value || '-'}</td>
          <td><b>${$('#mf_WRating')?.value || '-'}</b></td>
        </tr>
      `);
    }

    // Colorfastness
    if ($('#mf_CTPRating')?.value) {
      items.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Perspiration (Face)</b></td>
          <td>Grade</td>
          <td>W:${$('#mf_CtPWool')?.value||'-'} A:${$('#mf_CtPAcrylic')?.value||'-'} P:${$('#mf_CtPPolyester')?.value||'-'} N:${$('#mf_CtPNylon')?.value||'-'} C:${$('#mf_CtPCotton')?.value||'-'} Ac:${$('#mf_CtPAcetate')?.value||'-'}</td>
          <td>${$('#mf_CTP')?.value || '4.0'}</td>
          <td><b>${$('#mf_CTPRating')?.value}</b></td>
        </tr>
      `);
    }
    if ($('#mf_CTWRating')?.value) {
      items.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Water (Face)</b></td>
          <td>Grade</td>
          <td>W:${$('#mf_CtWWool')?.value||'-'} A:${$('#mf_CtWAcrylic')?.value||'-'} P:${$('#mf_CtWPolyester')?.value||'-'} N:${$('#mf_CtWNylon')?.value||'-'} C:${$('#mf_CtWCotton')?.value||'-'} Ac:${$('#mf_CtWAcetate')?.value||'-'}</td>
          <td>${$('#mf_CTW')?.value || '4.0'}</td>
          <td><b>${$('#mf_CTWRating')?.value}</b></td>
        </tr>
      `);
    }
    if ($('#mf_CTCDRYRating')?.value) {
      items.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Dry)</b></td>
          <td>Grade</td>
          <td><b>${$('#mf_CtCDry')?.value || '-'}</b></td>
          <td>${$('#mf_CtCDry_S')?.value || '4.0'}</td>
          <td><b>${$('#mf_CTCDRYRating')?.value}</b></td>
        </tr>
      `);
    }
    if ($('#mf_CTCWETRating')?.value) {
      items.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Wet)</b></td>
          <td>Grade</td>
          <td><b>${$('#mf_CtCWet')?.value || '-'}</b></td>
          <td>${$('#mf_CtCWet_S')?.value || '3.0'}</td>
          <td><b>${$('#mf_CTCWETRating')?.value}</b></td>
        </tr>
      `);
    }

    // Tests this form stores under its own names (Hardware: heat aging, corrosion, hydrolysis ...): every rated test that
    // the fixed rows above did not list is listed from the record, so a recorded test is never missing from the sheet.
    // A test with no reading (blank or 0, as in the Access report) is left out.
    (() => {
      const covered = items.length ? /^(tensile|elongation|trousertear|stitchtear|mullen|diec|ctp|ctw|ctcdry|ctcwet)/i : /^$/;
      const NAMES = {
        heataging: 'Heat Aging (72 hrs, 70°C)', corrosion: 'Corrosion Resistance', hydrolysis: 'Hydrolysis (7 days, 60°C, 95% RH)', uv: 'UV Light (24 hrs, 50°C)',
        ctcdry: 'Colorfastness to Crocking (Dry)', ctcwet: 'Colorfastness to Crocking (Wet)', ctw: 'Colorfastness to Water', ctp: 'Colorfastness to Perspiration',
        shrinkage12: 'Shrinkage (1 hr, 70°C) 1-2', shrinkage34: 'Shrinkage (1 hr, 70°C) 3-4', tensile: 'Tensile', elongation: 'Elongation', stitchtear: 'Stitch Tear',
        peelstrength: 'Peel Strength', shearstrength: 'Shear Strength', laceTipRet: 'Lace Tip Retention', knotslip: 'Knot Slip', laceabrasion: 'Lace Abrasion',
        hardness: 'Hardness', sg: 'Specific Gravity', pvc: 'PVC Contact Discoloration',
      };
      const ALIAS = { corrosion: ['CorrosionRes'], ctcdry: ['CtCDry'], ctcwet: ['CtCWet'], shrinkage12: ['Shrinkageave12'], shrinkage34: ['Shrinkageave34'] };
      const esc = (x) => x.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      const norm = (v) => { const u = String(v).trim().toUpperCase(); return ['P', 'PASS'].includes(u) ? 'PASS' : ['F', 'FAIL', 'FAILED'].includes(u) ? 'FAIL' : u === 'T' ? 'TOLERABLE' : u; };
      const seen = new Set();
      Object.keys(rec).forEach((key) => {
        const m = key.match(/^(.+?)(?:_R|_?Rating|_?rating)$/);
        const rating = m && String(rec[key] ?? '').trim();
        if (!rating) return;
        const base = m[1].replace(/txt/i, '');
        const lb = base.toLowerCase();
        if (base.length < 3 || seen.has(lb) || covered.test(base)) return;
        const prefixes = [base, ...(ALIAS[lb] || [])];
        const isMeta = (k) => /(_S|_std|Std|_Std|_R|Rating|_rating)$/.test(k);
        const reads = Object.keys(rec).filter((k) => !isMeta(k) && prefixes.some((pf) => new RegExp('^' + esc(pf) + '(?:txt)?(\\d*|_?ave)$', 'i').test(k)))
          .map((k) => String(rec[k] ?? '').trim()).filter((v) => v !== '' && !/^0(\.0+)?$/.test(v));
        if (!reads.length) return;
        seen.add(lb);
        const stdKey = Object.keys(rec).find((k) => prefixes.some((pf) => new RegExp('^' + esc(pf) + '(?:txt)?(_S|_std|Std|_Std)$', 'i').test(k)) && String(rec[k] ?? '').trim() !== '');
        const label = NAMES[lb] || base.replace(/([a-z])([A-Z0-9])/g, '$1 $2');
        const r = norm(rating);
        items.push(`
          <tr>
            <td class="text-left"><b>${escHtml(label)}</b></td>
            <td>-</td>
            <td><b>${reads.map(escHtml).join(' &nbsp; ')}</b></td>
            <td>${escHtml(stdKey ? rec[stdKey] : '-')}</td>
            <td><b class="${r === 'PASS' ? 'dtr-rate-pass' : (r === 'TOLERABLE' ? 'dtr-rate-warn' : 'dtr-rate-fail')}">${escHtml(r)}</b></td>
          </tr>
        `);
      });
    })();

    const html = `
      <div class="dtr-report-sheet">
        <div class="rpt-logo-row">
          <img class="rpt-logo-vf" src="${BASE_URL}assets/images/vf-logo.webp" alt="VF">
          <img class="rpt-logo-satra" src="${BASE_URL}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
        </div>
        <div class="dtr-sheet-header">
          <div class="dtr-sheet-title">
            <h2>DATIAN SUBIC SHOES INC. (DTP)</h2>
            <h4>PHYSICAL TEST REPORT — ${escHtml(matType.toUpperCase())}</h4>
            <div style="font-size:11px;color:#64748b;margin-top:2px">Lot 1P7-A, Phase 2, Subic Bay Gateway Park, Subic Bay Freeport Zone</div>
          </div>
          <div class="dtr-sheet-meta">
            <div>Lab Ref: <b>${escHtml(labRef)}</b></div>
            <div>TR #: <b>${escHtml(tr)}</b></div>
            <div style="margin-top:6px">
              <span class="dtr-sheet-stamp-box ${stampCls}">${escHtml(overall)}</span>
            </div>
          </div>
        </div>

        <table class="dtr-sheet-info-grid">
          <tr>
            <td class="label-cell">Material Code</td>
            <td><b>${escHtml(matCode)}</b></td>
            <td class="label-cell">Material Type</td>
            <td>${escHtml(matType)}</td>
            <td class="label-cell">Tested Date</td>
            <td>${escHtml(testedDate)}</td>
          </tr>
          <tr>
            <td class="label-cell">Description</td>
            <td colspan="3">${escHtml(matDesc)}</td>
            <td class="label-cell">PO / Lot No.</td>
            <td>${escHtml(poLot)}</td>
          </tr>
          <tr>
            <td class="label-cell">Supplier / Factory</td>
            <td>${escHtml(supplier)}</td>
            <td class="label-cell">Model / Style</td>
            <td>${escHtml(modelStyle)}</td>
            <td class="label-cell">Color / MTL</td>
            <td>${escHtml(color)} / ${escHtml(mtl)}</td>
          </tr>
          <tr>
            <td class="label-cell">Thickness (TAve)</td>
            <td><b>${$('#mf_TAve')?.value || '-'} mm</b></td>
            <td class="label-cell">Weight (WAve)</td>
            <td><b>${$('#mf_WAve')?.value || '-'} g/m²</b></td>
            <td class="label-cell">Technician</td>
            <td>${escHtml(tech || '-')}</td>
          </tr>
          <tr>
            <td class="label-cell">Supplier's Country</td>
            <td>${escHtml(country || '-')}</td>
            <td class="label-cell">Quantity</td>
            <td>${escHtml(qty || '-')}</td>
            <td class="label-cell">Received Date</td>
            <td>${escHtml(recDate || '-')}</td>
          </tr>
          <tr>
            <td class="label-cell">Material Source</td>
            <td colspan="3" style="line-height:1.9">${srcOpts}<br>${techOpts}</td>
            <td class="label-cell">Finished Date</td>
            <td>${escHtml(finDate || '-')}</td>
          </tr>
          <tr>
            <td class="label-cell">Send by</td>
            <td colspan="5">${escHtml(sendBy || '-')}</td>
          </tr>
        </table>

        <table class="dtr-sheet-results-table">
          <thead>
            <tr>
              <th class="text-left" style="width:38%">Test Method / Item</th>
              <th style="width:12%">Unit</th>
              <th style="width:25%">Specimen Readings / Average</th>
              <th style="width:13%">Specification</th>
              <th style="width:12%">Evaluation</th>
            </tr>
          </thead>
          <tbody>
            ${items.length ? items.join('') : '<tr><td colspan="5" class="muted">No test items recorded for this report.</td></tr>'}
          </tbody>
        </table>

        <div class="dtr-sheet-sign-row">
          <div class="dtr-sheet-sign-box">
            <div>Prepared by (Testing Technician):</div>
            <div class="dtr-sheet-sign-line">
              <span>${escHtml(tech)}</span>
              <span class="muted">${escHtml(testedDate)}</span>
            </div>
          </div>
          <div class="dtr-sheet-sign-box">
            <div>Reviewed &amp; Approved by (QA Lab Head):</div>
            <div class="dtr-sheet-sign-line">
              <span>${escHtml(reviewer)}</span>
              <span class="muted">${escHtml(revDate || testedDate)}</span>
            </div>
          </div>
        </div>
      </div>
    `;

    $('#dtr-print-modal-title').textContent = `Official Test Report Sheet — ${labRef}`;
    $('#dtr-print-sheet-container').innerHTML = html;
    $('#dtr-print-modal-backdrop')?.classList.remove('hidden');
  };

  let sketchConfigsCache = null;
  const loadSketchConfigs = async () => {
    if (sketchConfigsCache) return sketchConfigsCache;
    try {
      const res = await fetch(BASE_URL + 'assets/images/bonding_sketches/sketch_configs.json');
      if (res.ok) {
        sketchConfigsCache = await res.json();
        return sketchConfigsCache;
      }
    } catch (e) {
      console.warn('Failed to load sketch_configs.json', e);
    }
    return null;
  };

  // Live shoe sketch inside the form (same layout the report preview uses: contour, spot boxes, leader lines, START)
  var formSketchTimer = null;
  var formSketchSeq = 0;
  function scheduleFormSketch() {
    clearTimeout(formSketchTimer);
    formSketchTimer = setTimeout(renderFormSketch, 120);
  }
  // Exact normalized contour rings (0..1 relative to blank.png on a Right shoe, clockwise starting at Spot 1 lower-left heel)
  const SHOE_OUTER_RING = [[0.1098,0.8078],[0.1109,0.7752],[0.1185,0.7184],[0.1215,0.6951],[0.1231,0.6748],[0.1257,0.6559],[0.1258,0.6394],[0.1234,0.6249],[0.1221,0.6109],[0.1203,0.5976],[0.1172,0.5852],[0.1139,0.5731],[0.1105,0.5613],[0.1052,0.5499],[0.1004,0.5383],[0.0944,0.5268],[0.0872,0.5152],[0.0811,0.5031],[0.074,0.4908],[0.0658,0.4779],[0.056,0.4642],[0.0446,0.4496],[0.0363,0.4345],[0.0288,0.4186],[0.0232,0.4021],[0.02,0.3852],[0.0174,0.3675],[0.0163,0.3492],[0.0166,0.3302],[0.0193,0.3108],[0.0225,0.2904],[0.0272,0.2691],[0.034,0.2472],[0.0422,0.2243],[0.0526,0.2005],[0.0671,0.1772],[0.0835,0.1529],[0.1037,0.1292],[0.1284,0.1067],[0.1571,0.0859],[0.1904,0.068],[0.2268,0.0521],[0.266,0.0384],[0.3079,0.0277],[0.3516,0.0199],[0.3966,0.0146],[0.4422,0.0114],[0.4882,0.01],[0.5341,0.0116],[0.5799,0.0136],[0.6251,0.0179],[0.6694,0.0246],[0.7123,0.0334],[0.7528,0.0458],[0.7905,0.0608],[0.8255,0.0775],[0.8548,0.0987],[0.8819,0.1197],[0.9028,0.144],[0.9206,0.1681],[0.9356,0.1919],[0.9477,0.2153],[0.9567,0.2386],[0.9626,0.2615],[0.9701,0.282],[0.9732,0.3029],[0.9756,0.3228],[0.9769,0.3418],[0.9753,0.3605],[0.9736,0.3782],[0.9692,0.3956],[0.965,0.412],[0.959,0.4278],[0.9514,0.443],[0.9407,0.4577],[0.9322,0.4714],[0.9239,0.4844],[0.9159,0.4969],[0.9082,0.5089],[0.9009,0.5204],[0.8969,0.532],[0.8904,0.5432],[0.8859,0.5545],[0.8816,0.5659],[0.8784,0.5777],[0.8754,0.5898],[0.8738,0.6026],[0.8721,0.616],[0.8707,0.6302],[0.8701,0.6456],[0.8706,0.6627],[0.8721,0.6817],[0.8743,0.703],[0.8754,0.7261],[0.8803,0.7546],[0.8804,0.7836],[0.8708,0.847],[0.8557,0.8764],[0.8295,0.8979],[0.8008,0.9197],[0.7677,0.9389],[0.7307,0.9552],[0.6896,0.9662],[0.647,0.9756],[0.6031,0.9838],[0.5577,0.9877],[0.5118,0.9896],[0.4658,0.9893],[0.42,0.9868],[0.375,0.9817],[0.331,0.9742],[0.2889,0.9638],[0.2494,0.9502],[0.2131,0.9338],[0.1798,0.9157],[0.1521,0.8935],[0.1305,0.868],[0.1163,0.8391]];
  const SHOE_INNER_RING = [[0.148,0.7777],[0.1524,0.7459],[0.1601,0.6946],[0.1619,0.6743],[0.1637,0.6559],[0.1633,0.6402],[0.1623,0.6258],[0.1612,0.6124],[0.159,0.6001],[0.1568,0.5882],[0.1531,0.5772],[0.1506,0.5662],[0.1477,0.5554],[0.143,0.5451],[0.1378,0.5348],[0.1328,0.5243],[0.1266,0.5137],[0.1206,0.5028],[0.1149,0.4916],[0.1073,0.48],[0.0979,0.4676],[0.0891,0.4545],[0.0795,0.4406],[0.0715,0.4259],[0.0651,0.4107],[0.0604,0.3949],[0.0575,0.3785],[0.0559,0.3615],[0.055,0.3437],[0.0551,0.3249],[0.0581,0.306],[0.0607,0.2855],[0.0666,0.2649],[0.0746,0.2437],[0.083,0.2208],[0.0951,0.1981],[0.1092,0.1743],[0.1273,0.1513],[0.1495,0.1291],[0.1755,0.1081],[0.2058,0.0896],[0.2398,0.0733],[0.2772,0.0603],[0.3168,0.0496],[0.3586,0.0424],[0.4012,0.0364],[0.4449,0.0342],[0.4887,0.0324],[0.5326,0.0335],[0.5762,0.0359],[0.6195,0.0396],[0.6614,0.047],[0.7019,0.0563],[0.7404,0.068],[0.7761,0.0826],[0.8081,0.1],[0.8354,0.1207],[0.859,0.1425],[0.8782,0.1658],[0.8936,0.1895],[0.9061,0.2128],[0.9163,0.2353],[0.924,0.2573],[0.9299,0.2784],[0.9341,0.2987],[0.9363,0.3183],[0.9379,0.3368],[0.9373,0.3549],[0.9358,0.3721],[0.9331,0.3887],[0.9277,0.4048],[0.9227,0.42],[0.9146,0.4348],[0.9071,0.4486],[0.8982,0.4618],[0.8894,0.4742],[0.8824,0.486],[0.8742,0.4972],[0.868,0.508],[0.8623,0.5185],[0.8571,0.5288],[0.8525,0.539],[0.8476,0.5491],[0.844,0.5594],[0.8407,0.57],[0.8378,0.5808],[0.8351,0.592],[0.8331,0.6039],[0.8311,0.6163],[0.83,0.6299],[0.8302,0.645],[0.8313,0.6618],[0.8327,0.6805],[0.836,0.7024],[0.8384,0.7266],[0.843,0.7557],[0.8437,0.8217],[0.8325,0.8518],[0.8133,0.8783],[0.7862,0.8993],[0.7545,0.9173],[0.7192,0.9325],[0.6811,0.9452],[0.6404,0.9543],[0.5982,0.9611],[0.555,0.9654],[0.5113,0.9676],[0.4674,0.9665],[0.4236,0.9649],[0.3807,0.9596],[0.3389,0.9522],[0.2992,0.9413],[0.262,0.9277],[0.2275,0.912],[0.1974,0.8928],[0.1735,0.8693],[0.157,0.8415],[0.1488,0.8104]];

  // Sample `count` points evenly by arc length around a closed contour ring (mapped into card % coordinates)
  function sampleContourPoints(ring, count) {
    if (!count || count < 1) return [];
    const imL = 21; const imT = 8; const imW = 58; const imH = 84;
    const pts = ring.map((p) => ({ x: imL + p[0] * imW, y: imT + p[1] * imH }));
    const closed = pts.concat([pts[0]]);
    const segs = [];
    let total = 0;
    for (let i = 0; i < closed.length - 1; i++) {
      // Weight vertical steps by card aspect ratio (~1.5) so visual spacing is uniform around sides and curves
      const d = Math.hypot(closed[i + 1].x - closed[i].x, (closed[i + 1].y - closed[i].y) * 1.45);
      segs.push(d);
      total += d;
    }
    const out = [];
    for (let k = 0; k < count; k++) {
      let s = (k * total) / count;
      let j = 0;
      while (j < segs.length - 1 && s > segs[j]) { s -= segs[j]; j++; }
      const t = segs[j] ? s / segs[j] : 0;
      const p0 = closed[j];
      const p1 = closed[j + 1];
      const x = p0.x + (p1.x - p0.x) * t;
      const y = p0.y + (p1.y - p0.y) * t;
      // Tangent clockwise -> outward normal on right-shoe clockwise loop is (+ty, -tx)
      const prev = closed[(j - 1 + pts.length) % pts.length];
      const next = closed[(j + 2) % pts.length];
      const tx = next.x - prev.x;
      const ty = (next.y - prev.y) * 1.45;
      const tLen = Math.hypot(tx, ty) || 1;
      const nx = ty / tLen;
      const ny = -tx / tLen;
      out.push({ idx: k + 1, x, y, nx, ny });
    }
    return out;
  }

  // Project an outer contour point along its inward normal onto the inner contour polygon so UTF[i] and FTO[i] are exact radial pairs
  function projectToInnerContour(ox, oy, inx, iny, innerRing) {
    const imL = 21; const imT = 8; const imW = 58; const imH = 84;
    const pts = innerRing.map((p) => ({ x: imL + p[0] * imW, y: imT + p[1] * imH }));
    const n = pts.length;
    let bestT = Infinity;
    let bestPt = null;
    for (let i = 0; i < n; i++) {
      const a = pts[i];
      const b = pts[(i + 1) % n];
      const sx = b.x - a.x;
      const sy = b.y - a.y;
      const denom = inx * sy - iny * sx;
      if (Math.abs(denom) < 1e-8) continue;
      const dx = a.x - ox;
      const dy = a.y - oy;
      const t = (dx * sy - dy * sx) / denom;
      const u = (dx * iny - dy * inx) / denom;
      if (t > 0.2 && u >= -0.02 && u <= 1.02 && t < bestT) {
        bestT = t;
        bestPt = { x: ox + t * inx, y: oy + t * iny };
      }
    }
    if (bestPt) return bestPt;
    // Fallback: closest vertex on inner ring
    let minD = Infinity;
    for (let i = 0; i < n; i++) {
      const d = Math.hypot(pts[i].x - ox, (pts[i].y - oy) * 1.45);
      if (d < minD) { minD = d; bestPt = pts[i]; }
    }
    return bestPt || { x: ox, y: oy };
  }

  // De-crowd vertical positions of points on one side so shelves or inner numbers never overlap
  function deCrowdY(items, minGap, minY, maxY) {
    if (items.length <= 1) return;
    const sorted = items.slice().sort((a, b) => a.ey - b.ey);
    for (let pass = 0; pass < 4; pass++) {
      for (let i = 1; i < sorted.length; i++) {
        if (sorted[i].ey - sorted[i - 1].ey < minGap) {
          const mid = (sorted[i].ey + sorted[i - 1].ey) / 2;
          sorted[i - 1].ey = Math.max(minY, mid - minGap / 2);
          sorted[i].ey = Math.min(maxY, mid + minGap / 2);
        }
      }
    }
  }

  // Build dynamic shoe sketch HTML (contour, dynamic blue dots, bent outer shelves, inner normal ticks, START marker, and aligned numbers)
  function buildDynamicShoeSketchHtml(opts) {
    const { isLeft, std, utfVals, ftoVals, defaultSpots, extras } = opts;
    const imL = 21; const imT = 8; const imW = 58; const imH = 84;
    let utfCount = 0; let ftoCount = 0;
    for (let i = 1; i <= 35; i++) {
      if (parseFloat(utfVals[i]) > 0) utfCount = i;
      if (parseFloat(ftoVals[i]) > 0) ftoCount = i;
    }
    const anyPulled = utfCount > 0 || ftoCount > 0;
    if (!anyPulled) {
      utfCount = defaultSpots;
      ftoCount = defaultSpots;
    }

    const getValStyle = (val) => {
      const v = evaluateSpotVerdict(parseFloat(val), std);
      if (!v) return 'color:#94a3b8;';
      if (v === 'FAIL') return 'color:#dc2626;font-weight:800;';
      if (v === 'TOLERABLE') return 'color:#d97706;font-weight:800;';
      return 'color:#1d4ed8;font-weight:800;';
    };

    const mx = (x) => (isLeft ? 100 - x : x);

    // 1. Base shoe contour (blank.png) placed identically on Left and Right cards
    let html = `<img src="${BASE_URL}assets/images/bonding_sketches/blank.png" class="vf-shoe-img" style="left:${imL}%;top:${imT}%;width:${imW}%;height:${imH}%;opacity:0.9${!isLeft ? ';transform:scaleX(-1)' : ''}" alt="Shoe contour">`;
    let svg = '<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none;z-index:2">';
    let dotsHtml = '';
    let boxesHtml = '';

    // 2. Outer points (UTF): sample around outer contour; inner points (FTO) project radially inward from each outer point so UTF[i] & FTO[i] are always aligned across the sole wall
    const baseCount = Math.max(utfCount, ftoCount);
    const baseOuterSamples = sampleContourPoints(SHOE_OUTER_RING, baseCount);
    const outerSamples = utfCount === baseCount
      ? baseOuterSamples
      : sampleContourPoints(SHOE_OUTER_RING, utfCount);

    // Identify at most ONE top-center (toe) and ONE bottom-center (heel) point closest to x = 50
    let topCenterIdx = -1; let topBestDx = 2.6;
    let botCenterIdx = -1; let botBestDx = 2.6;
    baseOuterSamples.forEach((p) => {
      const dx = Math.abs(p.x - 50);
      if (p.y < 11.5 && dx < topBestDx) { topBestDx = dx; topCenterIdx = p.idx; }
      if (p.y > 88.5 && dx < botBestDx) { botBestDx = dx; botCenterIdx = p.idx; }
    });

    const outerPts = outerSamples.map((p) => {
      const isTopCenter = p.idx === topCenterIdx;
      const isBotCenter = p.idx === botCenterIdx;
      const leftSide = p.x < 50;
      const rLen = (p.y < 15 || p.y > 85) ? 4.4 : 3.8;
      const ex = isTopCenter || isBotCenter ? p.x : p.x + p.nx * rLen;
      const ey = isTopCenter
        ? Math.max(4.6, p.y - 4.0)
        : (isBotCenter ? Math.min(95.4, p.y + 4.0) : Math.min(95.8, Math.max(4.2, p.y + (p.ny * rLen) / 1.35)));
      return { idx: p.idx, dx: p.x, dy: p.y, ex, ey, sx: ex, leftSide, isTopCenter, isBotCenter };
    });
    const minOuterGap = utfCount > 30 ? 2.55 : (utfCount > 24 ? 2.85 : 3.35);
    deCrowdY(outerPts.filter((p) => !p.isTopCenter && !p.isBotCenter && p.leftSide), minOuterGap, 4.5, 95.5);
    deCrowdY(outerPts.filter((p) => !p.isTopCenter && !p.isBotCenter && !p.leftSide), minOuterGap, 4.5, 95.5);

    outerPts.forEach((p) => {
      const dx = mx(p.dx); const dy = p.dy;
      const bw = 9.0; const bh = 2.5;
      if (p.isTopCenter || p.isBotCenter) {
        const ey = p.ey;
        svg += `<line x1="${dx.toFixed(2)}" y1="${dy.toFixed(2)}" x2="${dx.toFixed(2)}" y2="${ey.toFixed(2)}" stroke="#2563eb" stroke-width="1" vector-effect="non-scaling-stroke"/>`;
        dotsHtml += `<div style="position:absolute;left:${dx.toFixed(2)}%;top:${dy.toFixed(2)}%;width:4.5px;height:4.5px;margin:-2.25px 0 0 -2.25px;border-radius:50%;background:#1d4ed8;z-index:3;pointer-events:none"></div>`;
        const raw = utfVals[p.idx];
        const num = parseFloat(raw);
        const hasVal = Number.isFinite(num) && num > 0;
        const bLeft = dx - bw / 2;
        const bTop = p.isTopCenter ? Math.max(0.4, ey - bh - 0.3) : Math.min(99.6 - bh, ey + 0.3);
        boxesHtml += `<div class="vf-point-box vf-point-utf" style="left:${bLeft.toFixed(2)}%;top:${bTop.toFixed(2)}%;width:${bw}%;height:${bh}%;justify-content:center;${getValStyle(raw)}z-index:4" title="UTF Point ${p.idx}: ${hasVal ? num.toFixed(2) : '-'}">${hasVal ? num.toFixed(2) : ''}</div>`;
        return;
      }

      // Snap side shelves to crisp left/right columns (11.2% / 88.8%) so outer values align cleanly with zero floating labels
      if (p.leftSide) {
        p.ex = Math.min(p.ex, p.dx - 2.6);
        p.sx = 11.2;
      } else {
        p.ex = Math.max(p.ex, p.dx + 2.6);
        p.sx = 88.8;
      }
      const ex = mx(p.ex); const ey = p.ey;
      const sx = mx(p.sx);
      svg += `<polyline points="${dx.toFixed(2)},${dy.toFixed(2)} ${ex.toFixed(2)},${ey.toFixed(2)} ${sx.toFixed(2)},${ey.toFixed(2)}" fill="none" stroke="#2563eb" stroke-width="1" vector-effect="non-scaling-stroke"/>`;
      dotsHtml += `<div style="position:absolute;left:${dx.toFixed(2)}%;top:${dy.toFixed(2)}%;width:4.5px;height:4.5px;margin:-2.25px 0 0 -2.25px;border-radius:50%;background:#1d4ed8;z-index:3;pointer-events:none"></div>`;

      const raw = utfVals[p.idx];
      const num = parseFloat(raw);
      const hasVal = Number.isFinite(num) && num > 0;
      const onScreenLeft = sx < dx;
      const bLeft = onScreenLeft ? Math.max(0.4, sx - bw - 0.6) : Math.min(99.6 - bw, sx + 0.6);
      const bTop = ey - bh / 2;
      const align = onScreenLeft ? 'justify-content:flex-end;padding-right:1px;' : 'justify-content:flex-start;padding-left:1px;';
      boxesHtml += `<div class="vf-point-box vf-point-utf" style="left:${bLeft.toFixed(2)}%;top:${bTop.toFixed(2)}%;width:${bw}%;height:${bh}%;${align}${getValStyle(raw)}z-index:4" title="UTF Point ${p.idx}: ${hasVal ? num.toFixed(2) : '-'}">${hasVal ? num.toFixed(2) : ''}</div>`;

      if (p.idx === 1) {
        // Red diamond on Spot 1's contour dot + compact START tag sitting above the leader shelf so it never overlaps the value
        const tagX = (ex + sx) / 2;
        const tagY = ey - 1.6;
        dotsHtml += `<div style="position:absolute;left:${dx.toFixed(2)}%;top:${dy.toFixed(2)}%;width:0;height:0;pointer-events:none;z-index:6"><i style="position:absolute;left:-4.5px;top:-4.5px;width:9px;height:9px;background:#dc2626;transform:rotate(45deg);box-shadow:0 0 0 1.5px #fff"></i></div>`;
        dotsHtml += `<div class="vf-start-mark" style="position:absolute;left:${tagX.toFixed(2)}%;top:${tagY.toFixed(2)}%;transform:translate(-50%,-50%);pointer-events:none;z-index:6"><span style="display:inline-block;white-space:nowrap;padding:0 3.5px;border-radius:2px;background:#dc2626;color:#fff;font:800 7px/10px sans-serif;letter-spacing:.03em;box-shadow:0 0 0 1px #fff">START</span></div>`;
      }
    });

    // 3. Inner points (FTO): projected radially from the matching outer point across the sole wall -> inward normal tick -> inner value
    const innerSource = baseOuterSamples.slice(0, ftoCount);
    const innerPts = innerSource.map((p) => {
      const inx = -p.nx; const iny = -p.ny;
      // Normalize inward normal in % space (undoing the 1.45 aspect weight) so ray hits inner contour cleanly
      const rawInx = inx;
      const rawIny = iny / 1.45;
      const rLenNorm = Math.hypot(rawInx, rawIny) || 1;
      const ip = projectToInnerContour(p.x, p.y, rawInx / rLenNorm, rawIny / rLenNorm, SHOE_INNER_RING);
      const isTopCenter = p.idx === topCenterIdx;
      const isBotCenter = p.idx === botCenterIdx;
      const isToeOrHeel = ip.y < 18 || ip.y > 82;
      const tLen = isToeOrHeel ? 2.4 : 3.0;
      const bDist = isToeOrHeel ? 5.2 : 7.8;
      const tx = isTopCenter || isBotCenter ? ip.x : ip.x + inx * tLen;
      const ty = isTopCenter
        ? ip.y + 2.8
        : (isBotCenter ? ip.y - 2.8 : ip.y + (iny * tLen) / 1.35);
      const bx = isTopCenter || isBotCenter ? ip.x : ip.x + inx * bDist;
      const ey = isTopCenter
        ? ip.y + 5.0
        : (isBotCenter ? ip.y - 5.0 : ip.y + (iny * (isToeOrHeel ? 5.0 : 6.6)) / 1.35);
      return { idx: p.idx, dx: ip.x, dy: ip.y, tx, ty, bx, ey, leftSide: ip.x < 50, isTopCenter, isBotCenter, isToeOrHeel };
    });
    const minInnerGap = ftoCount > 30 ? 2.4 : (ftoCount > 24 ? 2.7 : 3.0);
    const innerMaxY = botCenterIdx > 0 ? 83.2 : 85.5;
    const innerMinY = topCenterIdx > 0 ? 16.8 : 14.5;
    deCrowdY(innerPts.filter((p) => !p.isTopCenter && !p.isBotCenter && p.leftSide), minInnerGap, innerMinY, innerMaxY);
    deCrowdY(innerPts.filter((p) => !p.isTopCenter && !p.isBotCenter && !p.leftSide), minInnerGap, innerMinY, innerMaxY);

    innerPts.forEach((p) => {
      const dx = mx(p.dx); const dy = p.dy;
      const tx = mx(p.tx); const ty = p.ty;
      let rawBx = p.bx;
      if (!p.isTopCenter && !p.isBotCenter) {
        if (p.isToeOrHeel) {
          // Keep toe/heel inner values inside the white interior so they never overlap the inner contour line
          rawBx = p.leftSide ? Math.min(44.5, Math.max(38.0, p.bx)) : Math.max(55.5, Math.min(62.0, p.bx));
        } else {
          rawBx = p.leftSide ? Math.min(44.2, p.bx) : Math.max(55.8, p.bx);
        }
      }
      const bx = mx(rawBx); const by = p.ey;
      svg += `<line x1="${dx.toFixed(2)}" y1="${dy.toFixed(2)}" x2="${tx.toFixed(2)}" y2="${ty.toFixed(2)}" stroke="#2563eb" stroke-width="1" vector-effect="non-scaling-stroke"/>`;
      dotsHtml += `<div style="position:absolute;left:${dx.toFixed(2)}%;top:${dy.toFixed(2)}%;width:4px;height:4px;margin:-2px 0 0 -2px;border-radius:50%;background:#1d4ed8;z-index:3;pointer-events:none"></div>`;

      const raw = ftoVals[p.idx];
      const num = parseFloat(raw);
      const hasVal = Number.isFinite(num) && num > 0;
      const bw = 8.4; const bh = 2.3;
      const bLeft = bx - bw / 2;
      const bTop = by - bh / 2;
      boxesHtml += `<div class="vf-point-box vf-point-fto" style="left:${bLeft.toFixed(2)}%;top:${bTop.toFixed(2)}%;width:${bw}%;height:${bh}%;${getValStyle(raw)}z-index:4" title="FTO Point ${p.idx}: ${hasVal ? num.toFixed(2) : '-'}">${hasVal ? num.toFixed(2) : ''}</div>`;
    });

    svg += '</svg>';
    html += svg + dotsHtml + boxesHtml;

    if (Array.isArray(extras) && extras.length) {
      extras.forEach((e) => {
        const pos = `left:${e.pctLeft}%; top:${e.pctTop}%; width:${e.pctWidth}%; height:${e.pctHeight}%;`;
        const txt = `color:${escHtml(e.color || '#111')}; font-size:${Number(e.size) || 12}px; font-weight:${e.bold ? 700 : 400};`;
        let inner = '';
        if (e.type === 'text') inner = escHtml(e.text);
        else if (e.type === 'field') inner = escHtml($('#bf_' + e.field)?.value || '');
        else if (e.type === 'image' && e.src) inner = `<img src="${BASE_URL}${escHtml(e.src)}" style="max-width:100%;max-height:100%" alt="">`;
        const bg = e.type === 'line' ? `background:${escHtml(e.color || '#111')};` : '';
        html += `<div style="position:absolute;display:flex;align-items:center;overflow:hidden;${pos}${txt}${bg}">${inner}</div>`;
      });
    }

    return { html, spotCount: Math.max(utfCount, ftoCount, defaultSpots) };
  }

  // Build the 6-line Center Filler strip HTML with each pulled FiTO value sitting directly on its black horizontal line
  function buildFillerSlotsHtml(fitoVals, activeSide, lineClass) {
    let highestFito = 0;
    for (let i = 1; i <= 13; i++) {
      if (parseFloat(fitoVals[i]) > 0) highestFito = i;
    }
    const slotCount = Math.max(6, highestFito);
    const getFitoColor = (val) => {
      const v = evaluateSpotVerdict(parseFloat(val), FITO_STD);
      if (!v) return 'color:#94a3b8;';
      if (v === 'FAIL') return 'color:#dc2626;font-weight:800;';
      if (v === 'TOLERABLE') return 'color:#d97706;font-weight:800;';
      return 'color:#1d4ed8;font-weight:800;';
    };
    let out = '';
    for (let i = 1; i <= slotCount; i++) {
      const raw = activeSide ? (fitoVals[i] || '') : '';
      const num = parseFloat(raw);
      const hasVal = activeSide && Number.isFinite(num) && num > 0;
      out += `<div class="vf-diag-slot"><span class="vf-diag-val" style="${hasVal ? getFitoColor(raw) : ''}">${hasVal ? num.toFixed(2) : '&nbsp;'}</span><i class="${lineClass}"></i></div>`;
    }
    return out;
  }

  // Possible Cause(s) legend (a)-(k): tick a cause and its phrase is highlighted; the ticked ones are kept in the Possible Cause field of the form
  (function () {
    const inp = $('#bf_PossibleCause');
    const boxes = $$('.vf-cause-chk');
    if (!inp || !boxes.length) return;
    let last = null;
    const paint = () => boxes.forEach((b) => b.closest('.vf-cause-opt').classList.toggle('on', b.checked));
    const fromField = () => {
      last = inp.value;
      boxes.forEach((b) => { b.checked = inp.value.indexOf(b.value.slice(0, 3)) >= 0; });
      paint();
    };
    boxes.forEach((b) => b.addEventListener('change', () => {
      inp.value = boxes.filter((x) => x.checked).map((x) => x.value).join('; ');
      last = inp.value;
      paint();
      inp.dispatchEvent(new Event('input', { bubbles: true }));
    }));
    // the field is filled by code when a record loads (no event): follow it
    setInterval(() => { if (inp.value !== last) fromField(); }, 400);
    fromField();
  })();

  // Mode of Failure(s) legend (AF / CF / PF / SF): same logic as the causes: tick one and its phrase is highlighted; the ticked codes go to the Mode of Failure field
  (function () {
    const inp = $('#bf_ModeOfFailure');
    const boxes = $$('.vf-mode-chk');
    if (!inp || !boxes.length) return;
    let last = null;
    const paint = () => boxes.forEach((b) => b.closest('.vf-cause-opt').classList.toggle('on', b.checked));
    const fromField = () => {
      last = inp.value;
      const have = inp.value.toUpperCase().split(/[^A-Z]+/).filter(Boolean);
      boxes.forEach((b) => { b.checked = have.includes(b.value); });
      paint();
    };
    boxes.forEach((b) => b.addEventListener('change', () => {
      inp.value = boxes.filter((x) => x.checked).map((x) => x.value).join(', ');
      last = inp.value;
      paint();
      inp.dispatchEvent(new Event('input', { bubbles: true }));
    }));
    setInterval(() => { if (inp.value !== last) fromField(); }, 400);
    fromField();
  })();
  // Left or Right shoe: the -L / -R ending of the Lab Ref No decides (retest -R01 style endings count), else the Side field
  function bondingIsLeft() {
    const ref = ($('#bf_LabRefNo')?.value || '').trim().toUpperCase();
    const m = ref.match(/-(L|R)(?:-(?:R\d+|\d+))?$/);
    if (m) return m[1] === 'L';
    return (($('#bf_Side')?.value || 'Left').toLowerCase() === 'left');
  }
  async function renderFormSketch() {
    const leftEl = $('#vf-diag-left');
    const rightEl = $('#vf-diag-right');
    if (!leftEl || !rightEl) return;
    const seq = ++formSketchSeq;
    const isLeft = bondingIsLeft();
    const std = currentBondingStd();
    const IMG = BASE_URL + 'assets/images/bonding_sketches/';

    const utfVals = {}; const ftoVals = {}; const fitoVals = {};
    for (let i = 1; i <= 35; i++) {
      utfVals[i] = $('#bp_UTFpoint' + i)?.value || '';
      ftoVals[i] = $('#bp_FTOpoint' + i)?.value || '';
      if (i <= 13) fitoVals[i] = $('#bp_FiTO' + i)?.value || '';
    }

    const blank = `<img src="${IMG}blank.png" class="vf-shoe-img" style="left:21%;top:8%;width:58%;height:84%;opacity:.9${!isLeft ? ';transform:scaleX(-1)' : ''}" alt="Shoe contour">`;
    const ageKey = ($('#bf_AgeCategory')?.value || 'ADULT').toUpperCase();
    const defaultSpots = AGE_SPOT_LIMITS[ageKey] || 28;
    if (seq !== formSketchSeq) return;

    const { html: active } = buildDynamicShoeSketchHtml({
      isLeft,
      std,
      utfVals,
      ftoVals,
      defaultSpots,
      extras: [],
    });

    leftEl.innerHTML = isLeft ? active : blank;
    rightEl.innerHTML = !isLeft ? active : blank;

    // centre strips (Filler to Outsole): 6 horizontal lines in both Left & Right boxes, with pulled FiTO readings sitting directly on each line on the active side
    const lbls = document.querySelectorAll('.vf-diag-lbl');
    const strips = document.querySelectorAll('.vf-diag-strip');
    [lbls, strips].forEach((list) => list.forEach((el, k) => el.classList.toggle('is-active', k === (isLeft ? 0 : 1))));
    strips.forEach((el, k) => {
      const isAct = k === (isLeft ? 0 : 1);
      el.innerHTML = buildFillerSlotsHtml(fitoVals, isAct, 'vf-diag-line');
    });
  }
  const openBondingPrintPreview = async () => {
    const bondingTestId = parseInt($('#bf_BondingTestID')?.value || activeBondingPoints?.BondingTestID || 0) || 0;
    const labRef = $('#bf_LabRefNo')?.value || 'DTP-BOND-PREVIEW';
    const etrf = $('#bf_ETRFNo')?.value || '-';
    const shoeName = $('#bf_ModelStyle')?.value || '-';
    const color = $('#bf_Color')?.value || '-';
    const isLeft = bondingIsLeft();
    const side = isLeft ? 'Left' : 'Right';
    const age = ($('#bf_AgeCategory')?.value || 'ADULT').toUpperCase();
    const std = currentBondingStd();
    const poLot = $('#bf_POLotNO')?.value || '-';
    const poQty = $('#bf_POQuantity')?.value || '-';
    const factory = $('#bf_Factory')?.value || 'DATIAN SUBIC SHOES INC. (DTP)';
    const adhesive = $('#bf_AdhesiveSupplier')?.value || '-';
    const shippedDate = $('#bf_ShippedDate')?.value || '-';
    const productionDate = $('#bf_ProductionDate')?.value || '-';
    const sendBy = $('#bf_Sendby')?.value || '-';
    const materialSource = $('#bf_MaterialSource')?.value || 'Prod. Shoes';
    const modeOfFailure = $('#bf_ModeOfFailure')?.value || '-';
    const possibleCause = $('#bf_PossibleCause')?.value || '-';
    const remarks = $('#bf_Remarks')?.value || $('#bf_LabNotesComments')?.value || '';
    const receivedDate = $('#bf_ReceivedDate')?.value || '-';
    const testedDate = $('#bf_TestedDate')?.value || new Date().toISOString().slice(0, 10);
    const finishedDate = $('#bf_FinishedDate')?.value || testedDate;
    const tech = $('#bf_Technician')?.value || 'L. Aquidado';
    const reviewer = $('#bf_ReviewedBy')?.value || 'QA Lead';
    const overall = ($('#bf_Result')?.value || '').toUpperCase();

    const stampCls = (overall === 'PASS' || !overall) ? '' : (overall === 'TOLERABLE' ? 'stamp-tol' : 'stamp-fail');

    // Section Statistics (UTF, FTO, FiTO)
    const utfMin = $('#bp_UTFMin')?.value || activeBondingPoints?.UTFMin || '-';
    const utfMax = $('#bp_UTFMax')?.value || activeBondingPoints?.UTFMax || '-';
    const utfAve = $('#bp_UTFAverage')?.value || activeBondingPoints?.UTFAverage || '-';
    const utfRes = ($('#bp_UTF_Result')?.value || activeBondingPoints?.UTF_Result || '-').toUpperCase();

    const ftoMin = $('#bp_FTOMin')?.value || activeBondingPoints?.FTOMin || '-';
    const ftoMax = $('#bp_FTOMax')?.value || activeBondingPoints?.FTOMax || '-';
    const ftoAve = $('#bp_FTOAverage')?.value || activeBondingPoints?.FTOAverage || '-';
    const ftoRes = ($('#bp_FTO_Result')?.value || activeBondingPoints?.FTO_Result || '-').toUpperCase();

    const fitoMin = $('#bp_FiTOMin')?.value || activeBondingPoints?.FiTOMin || '-';
    const fitoMax = $('#bp_FiTOMax')?.value || activeBondingPoints?.FiTOMax || '-';
    const fitoAve = $('#bp_FiTOAverage')?.value || activeBondingPoints?.FiTOAverage || '-';
    const fitoRes = ($('#bp_FiTO_Result')?.value || activeBondingPoints?.FiTO_Result || '-').toUpperCase();

    // Read spot points
    const utfVals = {};
    const ftoVals = {};
    const fitoVals = {};
    let highestPoint = 0;
    for (let i = 1; i <= 35; i++) {
      const u = $('#bp_UTFpoint' + i)?.value || activeBondingPoints?.['UTFpoint' + i] || '';
      const f = $('#bp_FTOpoint' + i)?.value || activeBondingPoints?.['FTOpoint' + i] || '';
      utfVals[i] = u;
      ftoVals[i] = f;
      if (parseFloat(u) > 0 || parseFloat(f) > 0) highestPoint = i;
      if (i <= 13) {
        fitoVals[i] = $('#bp_FiTO' + i)?.value || activeBondingPoints?.['FiTO' + i] || '';
      }
    }

    // Spot Color logic
    // blue = pass, amber = tolerable, red = fail (same rules as the result)
    const getValColorStyle = (val, threshold, isFiTO = false) => {
      const verdict = evaluateSpotVerdict(parseFloat(val), isFiTO ? FITO_STD : threshold);
      if (!verdict) return 'color:#94a3b8';
      if (verdict === 'FAIL') return 'color:#dc2626;font-weight:800;';
      if (verdict === 'TOLERABLE') return 'color:#d97706;font-weight:800;';
      return 'color:#1d4ed8;font-weight:800;';
    };

    const defaultSpots = AGE_SPOT_LIMITS[age] || 28;
    const { html: activeShoePointsHtml, spotCount: previewSpotCount } = buildDynamicShoeSketchHtml({
      isLeft,
      std,
      utfVals,
      ftoVals,
      defaultSpots,
      extras: [],
    });

    const blankShoeHtml = `
      <img src="${BASE_URL}assets/images/bonding_sketches/blank.png" 
           class="vf-shoe-img" 
           style="left:21%; top:8%; width:58%; height:84%; opacity:0.9;${!isLeft ? 'transform:scaleX(-1);' : ''}" 
           alt="Unmeasured Shoe Contour">
    `;

    const leftShoeCardHtml = isLeft ? activeShoePointsHtml : blankShoeHtml;
    const rightShoeCardHtml = !isLeft ? activeShoePointsHtml : blankShoeHtml;
    const leftCenterHtml = buildFillerSlotsHtml(fitoVals, isLeft, 'vf-center-line');
    const rightCenterHtml = buildFillerSlotsHtml(fitoVals, !isLeft, 'vf-center-line');

    // Status evaluation helpers
    const getEvalStatus = (res) => {
      if (res === 'PASS') return { pass: 'PASS', fail: '', passCls: 'vf-rate-pass', failCls: '' };
      if (res === 'TOLERABLE') return { pass: 'TOLE', fail: '', passCls: 'vf-rate-tol', failCls: '' };
      if (res === 'FAIL' || res === 'FAILED') return { pass: '', fail: 'FAIL', passCls: '', failCls: 'vf-rate-fail' };
      return { pass: '-', fail: '', passCls: '', failCls: '' };
    };

    const utfStat = getEvalStatus(utfRes);
    const ftoStat = getEvalStatus(ftoRes);
    const fitoStat = getEvalStatus(fitoRes);

    // Build raw spots rows for collapsible table
    let rawSpotRows = '';
    for (let i = 1; i <= previewSpotCount; i++) {
      const uVal = utfVals[i] ? parseFloat(utfVals[i]).toFixed(2) : '-';
      const fVal = ftoVals[i] ? parseFloat(ftoVals[i]).toFixed(2) : '-';
      const fiVal = i <= 13 && fitoVals[i] ? parseFloat(fitoVals[i]).toFixed(2) : '-';
      rawSpotRows += `
        <tr>
          <td><b>Point ${i}</b></td>
          <td style="${getValColorStyle(utfVals[i], std, false)}">${uVal}</td>
          <td style="${getValColorStyle(ftoVals[i], std, false)}">${fVal}</td>
          <td style="${i <= 13 ? getValColorStyle(fitoVals[i], 1.0, true) : 'color:#94a3b8'}">${fiVal}</td>
        </tr>
      `;
    }

    const html = `
      <div class="vf-report-sheet">
        <!-- Row 1: VF logo | test method name | SATRA logo, all on one line -->
        <div class="rpt-top">
          <img class="rpt-logo-vf" src="${BASE_URL}assets/images/vf-logo.webp" alt="VF">
          <div class="rpt-top-title">
            <h1 class="vf-title-en">Bonding Test Results</h1>
            <h2 class="vf-title-cn">胶着力测试</h2>
          </div>
          <img class="rpt-logo-satra" src="${BASE_URL}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
        </div>

        <!-- Row 2: form no / ETRF / lab ref + sample category (left), result + dates (right) -->
        <div class="rpt-sub">
          <div class="rpt-sub-left">
            <div class="vf-form-no rpt-formno">Form # : Lab VF 001</div>
            <div class="vf-etrf-row">
              <span>ETRF No:</span>
              <span class="vf-val-underlined">${escHtml(etrf)}</span>
            </div>
            <div class="vf-labref-row">
              <span>Lab Ref.No:</span>
              <span class="vf-labref-val">${escHtml(labRef)}</span>
            </div>
            <div class="vf-test-categories">
              <div>
                <span class="vf-radio ${materialSource === 'Prod. Shoes' || materialSource === 'Prod. Material' || !materialSource ? 'checked' : ''}"></span> Pro. Shoes 量产鞋
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${materialSource === 'Pro. Re-test' ? 'checked' : ''}"></span> Pro. Re-test 量产重测
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${materialSource === 'NewDevelopment' ? 'checked' : ''}"></span> NewDevelopment 开发
              </div>
              <div class="vf-tech-subradios">
                <span>○ Tech 1</span> <span>○ Tech 2</span> <span>○ Tech 3</span> <span>○ Tech 4</span>
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${materialSource === 'Sales Sample' ? 'checked' : ''}"></span> Sales Sample 销样
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${materialSource === 'Trial experiment' ? 'checked' : ''}"></span> 试胶试验
              </div>
            </div>
          </div>

          <div class="rpt-sub-right">
            <div class="vf-stamp-area">
              <span class="vf-stamp ${stampCls}">${escHtml(overall)}</span>
            </div>
            <table class="vf-dates-table">
              <tr><td>Received Date 收到日期:</td><td class="vf-date-val">${escHtml(receivedDate)}</td></tr>
              <tr><td>Tested Date 测试日期:</td><td class="vf-date-val">${escHtml(testedDate)}</td></tr>
              <tr><td>Finished Date 完成日期:</td><td class="vf-date-val">${escHtml(finishedDate)}</td></tr>
              <tr><td>Technician 技术员:</td><td class="vf-date-val">${escHtml(tech)}</td></tr>
            </table>
          </div>
        </div>

        <!-- Factory & Spec Details Grid -->
        <table class="vf-meta-grid">
          <tr>
            <td class="vf-lbl">Factory (工厂):</td>
            <td class="vf-val" colspan="3">${escHtml(factory)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Adhesive supplier 胶水供应商:</td>
            <td class="vf-val">${escHtml(adhesive)}</td>
            <td class="vf-lbl">Shipped Date (出货日期):</td>
            <td class="vf-val">${escHtml(shippedDate)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Model/Style/PGSC (英文鞋型):</td>
            <td class="vf-val">${escHtml(shoeName)}</td>
            <td class="vf-lbl">Color (颜色):</td>
            <td class="vf-val">${escHtml(color)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Production Date (生产日期):</td>
            <td class="vf-val">${escHtml(productionDate !== '-' ? productionDate : testedDate)}</td>
            <td class="vf-lbl">PO/Lot No (订单号码):</td>
            <td class="vf-val">${escHtml(poLot)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">PO Quantity (订单数量):</td>
            <td class="vf-val">${escHtml(poQty)}</td>
            <td class="vf-lbl">Send by (送达):</td>
            <td class="vf-val">${escHtml(sendBy)}</td>
          </tr>
        </table>

        <!-- 3-Part Evaluation Table with Mode of Failure & Possible Causes -->
        <table class="vf-eval-table">
          <thead>
            <tr>
              <th rowspan="2" style="width:20%">Shoe Part</th>
              <th rowspan="2" style="width:11%">VF Test<br>Method</th>
              <th rowspan="2" style="width:8%">STD<br>kgf/cm<br><span style="font-size:8.5px;font-weight:600">(Tolerable)</span></th>
              <th colspan="2" style="width:16%">Test Result<br>kgf/cm</th>
              <th colspan="2" style="width:12%">Status</th>
              <th rowspan="2" style="width:17%">Mode of Failure(s)<br>失败的方式</th>
              <th rowspan="2" style="width:16%">Possible Cause(s)<br>可能的原因</th>
            </tr>
            <tr>
              <th style="width:8%">Min</th>
              <th style="width:8%">Max</th>
              <th style="width:6%">Pass</th>
              <th style="width:6%">Fail</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="text-left font-bold">Upper/Foxing Tape<br>(鞋面/围条)</td>
              <td>VF-F01<br><span style="font-size:9.5px">(2019/05)</span></td>
              <td class="font-bold text-blue">${std}<br><span class="vf-tol">(≥ ${bondingTolerance(std)})</span></td>
              <td class="font-bold text-blue">${utfMin !== '-' ? Number(utfMin).toFixed(2) : '-'}</td>
              <td class="font-bold text-blue">${utfMax !== '-' ? Number(utfMax).toFixed(2) : '-'}</td>
              <td><span class="${utfStat.passCls}">${utfStat.pass}</span></td>
              <td><span class="${utfStat.failCls}">${utfStat.fail}</span></td>
              <td rowspan="3" class="text-left vf-mode-cell">
                <b>AF</b>= Failure at the adhesive/substrate interface or the adhesive/primer interface<br>
                <b>CF</b>= Cohesive Failure of the adhesive (failure within the adhesive or between the two layers of adhesive)<br>
                <b>PF</b>= Failure at the primer/substrate interface<br>
                <b>SF</b>= Failure of the substrate, including the location of the failure within the substrate
              </td>
              <td rowspan="3" class="text-left vf-cause-cell">
                (a) Poor Priming 药水不良<br>
                (b) Poor cementing 胶水不良<br>
                (c) Over-dried cement<br>
                (d) Wet primer 湿的药水<br>
                (e) Wet cement 湿的胶水<br>
                (f) Poor pressing 压机不良<br>
                (g) Poor Roughing 打粗不良<br>
                (h) Upper material - weak 鞋面材料不良<br>
                (i) EVA-Weak EVA不良<br>
                (j) Dusty component 粉沫<br>
                (k) Splitting of foxing tape 围条分层
              </td>
            </tr>
            <tr>
              <td class="text-left font-bold">Foxing Tape / OS<br>(围条/橡胶大底)</td>
              <td>VF-F01<br><span style="font-size:9.5px">(2019/05)</span></td>
              <td class="font-bold text-blue">${std}<br><span class="vf-tol">(≥ ${bondingTolerance(std)})</span></td>
              <td class="font-bold text-blue">${ftoMin !== '-' ? Number(ftoMin).toFixed(2) : '-'}</td>
              <td class="font-bold text-blue">${ftoMax !== '-' ? Number(ftoMax).toFixed(2) : '-'}</td>
              <td><span class="${ftoStat.passCls}">${ftoStat.pass}</span></td>
              <td><span class="${ftoStat.failCls}">${ftoStat.fail}</span></td>
            </tr>
            <tr>
              <td class="text-left font-bold">Center : IS/OS<br>(中插 / 大底)</td>
              <td>VF-F01<br><span style="font-size:9.5px">(2019/05)</span></td>
              <td class="font-bold text-blue">${FITO_STD}<br><span class="vf-tol">(≥ ${bondingTolerance(FITO_STD)})</span></td>
              <td class="font-bold text-blue">${fitoMin !== '-' ? Number(fitoMin).toFixed(2) : '-'}</td>
              <td class="font-bold text-blue">${fitoMax !== '-' ? Number(fitoMax).toFixed(2) : '-'}</td>
              <td><span class="${fitoStat.passCls}">${fitoStat.pass}</span></td>
              <td><span class="${fitoStat.failCls}">${fitoStat.fail}</span></td>
            </tr>
          </tbody>
        </table>

        <!-- Shoe Sketch Section -->
        <div class="vf-sketch-header-row">
          <div class="vf-sketch-header-col">
            <div>1. Outside Value: Fabric / PU / EVA kgf/cm</div>
            <div>2. Inside Value:</div>
          </div>
          <div class="vf-sketch-header-center">
            <div>Center: Fabric / PU / EVA kgf/cm</div>
            <div class="vf-sketch-sublabel-row">
              <span style="${isLeft ? 'color:#1d4ed8;font-weight:800' : ''}">Left</span>
              <span style="${!isLeft ? 'color:#1d4ed8;font-weight:800' : ''}">Right</span>
            </div>
          </div>
          <div class="vf-sketch-header-col" style="text-align:right">
            <div>1. Outside Value: Fabric / PU / EVA kgf/cm</div>
            <div>2. Inside Value:</div>
          </div>
        </div>

        <div class="vf-sketch-body-row">
          <!-- Left Shoe Card -->
          <div class="vf-shoe-card">
            ${leftShoeCardHtml}
          </div>

          <!-- Center Boxes -->
          <div class="vf-center-card">
            <div class="vf-center-cols">
              <div class="vf-center-box" style="${isLeft ? 'border-color:#1d4ed8;background:#f8fafc' : 'border-color:#cbd5e1'}">
                ${leftCenterHtml}
              </div>
              <div class="vf-center-box" style="${!isLeft ? 'border-color:#1d4ed8;background:#f8fafc' : 'border-color:#cbd5e1'}">
                ${rightCenterHtml}
              </div>
            </div>
          </div>

          <!-- Right Shoe Card -->
          <div class="vf-shoe-card">
            ${rightShoeCardHtml}
          </div>
        </div>

        <!-- Optional Collapsible Data Table -->
        <details class="no-print" style="margin-top:8px;font-size:11px;border:1px solid #cbd5e1;border-radius:4px;padding:6px 10px;background:#f8fafc">
          <summary style="cursor:pointer;font-weight:700;color:#334155">
            <svg class="ic" aria-hidden="true"><use href="#i-chart"/></svg> View Tabular Spot Values (${previewSpotCount || 27} Points)
          </summary>
          <table class="dtr-sheet-results-table" style="margin-top:8px;margin-bottom:0">
            <thead>
              <tr>
                <th style="width:20%">Bonding Spot</th>
                <th style="width:28%">Upper To Foxing (UTF)</th>
                <th style="width:28%">Foxing To Outsole (FTO)</th>
                <th style="width:24%">Filler to Outsole (FiTO)</th>
              </tr>
            </thead>
            <tbody>
              ${rawSpotRows}
            </tbody>
          </table>
        </details>

        <!-- Signatures & Notes Footer -->
        <div class="vf-footer-grid">
          <div>
            Comments 备注:
            <div style="font-weight:normal;color:#334155;margin-top:4px;font-size:10.5px">
              ${escHtml(remarks || 'Tested in accordance with VF Laboratory Standards.')}
            </div>
          </div>
          <div>
            Reviewed by 审查:
            <div class="vf-sign-line">
              <span>${escHtml(reviewer)}</span>
            </div>
          </div>
          <div>
            Approved by 核准:
            <div class="vf-sign-line">
              <span>QA Laboratory Head</span>
            </div>
          </div>
        </div>
      </div>
    `;

    $('#dtr-print-modal-title').textContent = `Whole Shoe Bonding Test Report — ${labRef} (${side})`;
    $('#dtr-print-sheet-container').innerHTML = html;

    $('#dtr-print-modal-backdrop')?.classList.remove('hidden');
  };



  // Scale that makes the sheet fit ONE A4 page (210x297 mm, 6 mm margins): measures the sheet at the page width
  const fitOnePageScale = (html) => {
    const PAGE_W = 198 / 25.4 * 96;   // printable width  (px at 96 dpi)
    const PAGE_H = 285 / 25.4 * 96;   // printable height
    const probe = document.createElement('div');
    probe.className = 'dtr-fit-probe';
    probe.style.cssText = 'position:absolute;left:-99999px;top:0;visibility:hidden;';
    document.body.appendChild(probe);
    let scale = 1;
    for (let i = 0; i < 4; i++) {
      probe.style.width = (PAGE_W / scale) + 'px';
      probe.innerHTML = html;
      const h = probe.scrollHeight * scale;
      const next = Math.min(1, scale * (PAGE_H / Math.max(h, 1)) * 0.985);
      if (Math.abs(next - scale) < 0.005) { scale = next; break; }
      scale = next;
    }
    probe.remove();
    return Math.max(0.4, Math.round(scale * 1000) / 1000);
  };

  // The preview is a true A4 page (210 x 297 mm, 6 mm margins): whatever report fills it is scaled to fit ONE page, exactly as it prints / downloads
  const a4Box = $('#dtr-print-sheet-container');
  if (a4Box) {
    new MutationObserver(() => {
      if (!a4Box.innerHTML.trim()) return;
      const s = fitOnePageScale(a4Box.innerHTML);
      a4Box.style.width = (198 / 25.4 * 96 / s) + 'px';
      a4Box.style.zoom = String(s);
    }).observe(a4Box, { childList: true });
  }

  // ---- page 2 of the Shoe Bonding (U60) report: the report picture of the Lab Ref (the same picture as its Lab Tracker thumbnail)
  let page2Src = '';     // data URL of the picture, '' when there is none
  let page2Seq = 0;
  const page2Cap = () => $('#dtr-print-page2-cap')?.textContent || '';
  const hidePage2 = () => { page2Seq++; page2Src = ''; $('#dtr-print-page2')?.classList.add('hidden'); const im = $('#dtr-print-page2-img'); if (im) im.removeAttribute('src'); };
  const loadPage2 = async (labRef) => {
    hidePage2();
    const ref = String(labRef || '').trim();
    if (!/^[A-Za-z0-9_-]{3,60}$/.test(ref)) return;
    const seq = page2Seq;
    try {
      const r = await fetch(`${BASE_URL}dtr/bonding-image?tab=bonding&lab_ref=${encodeURIComponent(ref)}&t=${Date.now()}`, { credentials: 'same-origin' });
      if (!r.ok) return;
      const blob = await r.blob();
      if (!/^image\//.test(blob.type) || !blob.size) return;
      const url = await new Promise((ok) => { const fr = new FileReader(); fr.onload = () => ok(fr.result); fr.onerror = () => ok(''); fr.readAsDataURL(blob); });
      if (!url || seq !== page2Seq || $('#dtr-print-modal-backdrop')?.classList.contains('hidden')) return;   // another report was opened, or the preview closed
      page2Src = url;
      $('#dtr-print-page2-img').src = url;
      $('#dtr-print-page2-cap').textContent = `Report picture - ${ref}`;
      $('#dtr-print-page2').classList.remove('hidden');
    } catch (e) { /* no picture: the report stays one page */ }
  };
  $('#btn-dtr-print-preview')?.addEventListener('click', () => { hidePage2(); openMaterialPrintPreview(); });
  $('#btn-bond-print-preview')?.addEventListener('click', async () => {
    hidePage2();
    await (isPull() ? openPullPrintPreview() : openBondingPrintPreview());
    loadPage2($('#bf_LabRefNo')?.value);
  });
  $('#btn-dtr-close-print-modal')?.addEventListener('click', () => {
    $('#dtr-print-modal-backdrop')?.classList.add('hidden');
    hidePage2();
  });
  $('#btn-dtr-cancel-print')?.addEventListener('click', () => {
    $('#dtr-print-modal-backdrop')?.classList.add('hidden');
    hidePage2();
  });
  $('#btn-dtr-download-pdf')?.addEventListener('click', async () => {
    const btn = $('#btn-dtr-download-pdf');
    const src = $('#dtr-print-sheet-container');
    if (!src || !btn) return;
    const label = btn.textContent;
    btn.disabled = true; btn.textContent = 'Creating PDF...';
    try {
      const res = await fetch(BASE_URL + 'dtr/sheet-pdf', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="X-CSRF-TOKEN"]')?.content || '' },
        body: JSON.stringify({ scale: fitOnePageScale(src.innerHTML), html: src.innerHTML, page2_img: page2Src, page2_cap: page2Src ? page2Cap() : '', name: ($('#dtr-print-modal-title')?.textContent || 'Report').replace(/[^A-Za-z0-9._ -]+/g, '_') }),
      });
      if (!res.ok) throw new Error((await res.text()).slice(0, 200) || 'PDF failed');
      const blob = await res.blob();
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (($('#dtr-print-modal-title')?.textContent || 'Report').replace(/[^A-Za-z0-9._ -]+/g, '_').trim() || 'Report') + '.pdf';
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 4000);
    } catch (e) { nxToast('Could not create the PDF: ' + e.message, 'error', 7000); }
    btn.disabled = false; btn.textContent = label;
  });
  $('#btn-dtr-do-print')?.addEventListener('click', () => {
    // Print a clean copy of the sheet attached to <body>: the app layout (fixed height / overflow) would clip it
    const src = $('#dtr-print-sheet-container');
    if (!src) { window.print(); return; }
    const root = document.createElement('div');
    root.id = 'dtr-print-root';
    const p1 = document.createElement('div');
    p1.innerHTML = src.innerHTML;
    p1.style.zoom = String(fitOnePageScale(src.innerHTML));
    root.appendChild(p1);
    if (page2Src) {   // the report picture on its own second page
      const p2 = document.createElement('div');
      p2.className = 'dtr-print-p2';
      p2.innerHTML = '<div class="dtr-page2-cap"></div><img alt="">';
      p2.querySelector('.dtr-page2-cap').textContent = page2Cap();
      p2.querySelector('img').src = page2Src;
      root.appendChild(p2);
    }
    document.body.appendChild(root);
    document.body.classList.add('dtr-printing');
    const done = () => {
      document.body.classList.remove('dtr-printing');
      root.remove();
      window.removeEventListener('afterprint', done);
    };
    window.addEventListener('afterprint', done);
    window.print();
  });

  // =========================================================================
  // 7b. ADMIN FORM LAYOUT (Report Designer): hidden / renamed / reordered fields + custom fields
  // =========================================================================
  const applyBondingFormLayout = async () => {
    try {
      const j = await apiFetch('dtr/layout?slug=bonding&variant=form');
      const L = j && j.layout;
      const form = $('#dtr-bonding-form');
      if (!L || !form) return;

      const wrapOf = (name) => form.querySelector(`[name="${name}"]`)?.closest('.dtr-field');

      // custom fields are added at the end of the info grid, then ordered together with the rest
      (L.customFields || []).forEach((c) => {
        if (form.querySelector(`[name="${c.key}"]`)) return;
        const host = $('#bf_LabRefNo')?.closest('.dtr-field')?.parentElement;
        if (!host) return;
        const d = document.createElement('div');
        d.className = 'dtr-field';
        d.innerHTML = `<label>${escHtml(c.label)}</label><input type="text" name="${escHtml(c.key)}" id="bf_${escHtml(c.key)}">`;
        host.appendChild(d);
      });

      Object.entries(L.labels || {}).forEach(([n, t]) => {
        const w = wrapOf(n);
        const lab = w && w.querySelector('label');
        if (lab && t) lab.textContent = t;
      });
      const host = $('#bf_LabRefNo')?.closest('.dtr-field')?.parentElement;
      if (host && (L.order || []).length) {
        L.order.forEach((n) => { const w = wrapOf(n); if (w && w.parentElement === host) host.appendChild(w); });
        (L.customFields || []).forEach((c) => { const w = wrapOf(c.key); if (w) host.appendChild(w); });
      }
      (L.hiddenFields || []).forEach((n) => {
        const w = wrapOf(n);
        if (w) w.style.display = 'none';
      });
    } catch (e) { /* default form */ }
  };

  // =========================================================================
  // 8. INITIALIZE ON PAGE LOAD
  // =========================================================================
  updateCalculators();
  // both forms open as a blank new report; the record lists stay available to load one
  newMaterialRecord(false);
  newPullRecord(false);
  setReportKind('bonding');
  loadMaterialRecordList('fabric');
  const bondingReady = applyBondingFormLayout().then(() => { newBondingRecord(false); loadBondingRecordList(); });
  runStandardsExplorer();

  // ?openlab=<Lab Ref No> (from Material Testing): same as clicking that Lab Ref in the Lab Tracker
  (() => {
    const lab = new URLSearchParams(location.search).get('openlab');
    if (!lab) return;
    setTimeout(() => { const a = document.createElement('a'); a.href = '#'; a.dataset.openMaterial = lab; document.body.appendChild(a); a.click(); a.remove(); }, 1200);
  })();

  // deep link from the assistant: ?open=<slug>:<id>  (+ &preview=1 opens the print preview)
  (() => {
    const q = new URLSearchParams(location.search);
    const [slug, id] = String(q.get('open') || '').split(':');
    const ref = String(q.get('ref') || '').trim();
    // ?open=bonding&ref=<Lab Ref No> (from the dashboard tracker): the saved report of that lab reference is selected in the
    // list; a sample that has no report yet opens a new bonding form with its Lab Ref No filled in
    if (slug === 'bonding' && !id && ref) {
      setTimeout(async () => {
        await bondingReady;
        switchTab('bonding');
        setReportKind('bonding');
        await loadBondingRecordList();
        const sel = $('#dtr-bonding-record-select');
        const opt = sel && [...sel.options].find((o) => o.value && o.textContent.trim().startsWith(ref));
        if (opt) {
          sel.value = opt.value;
          await loadSingleBondingRecord(opt.value);
        } else {
          // the list only holds the newest reports: an older one is fetched by its Lab Ref No
          await loadSingleBondingRecord(0, ref);
          const lr = $('#bf_LabRefNo');
          if (lr && lr.value.trim() === ref) return;
          if (lr) { lr.value = ref; lr.dispatchEvent(new Event('input', { bubbles: true })); }
          if (window.nxToast) nxToast(`${ref} has no bonding report yet: a new report was started for it.`, 'info', 6000);
        }
      }, 900);
      return;
    }
    // ?open=wholeshoe&labref=<Lab Ref No> (from Calendar Monitoring): the Whole Shoe report page with that Lab Ref chosen (the picker reads ?labref itself)
    if (slug === 'wholeshoe' && !id) {
      const want = String(q.get('labref') || ref || '').trim();
      setTimeout(async () => {
        switchTab('wholeshoe');
        if (typeof markDtrNav === 'function') markDtrNav('wholeshoe');
        if (!want) return;
        const sel = $('#ws-labref');   // a read-only box now (the Lab Ref comes from the Lab Tracker lists), not a picker
        if (!sel || sel.value === want) return;   // the report form already picked it up from the address
        sel.value = want;
        sel.dispatchEvent(new Event('change'));
      }, 500);
      return;
    }
    if (!slug || !/^[a-z]+$/.test(slug) || !/^\d+$/.test(id || '')) return;
    setTimeout(async () => {
      await bondingReady; // the blank form must not overwrite the linked report
      const host = $('#dtr-tracker-tbody') || document.body;
      const b = document.createElement('button');
      b.type = 'button'; b.hidden = true; b.dataset.openRecord = slug; b.dataset.sourceId = id;
      host.appendChild(b); b.click(); b.remove();
      if (q.get('preview') === '1') {
        await new Promise((r) => setTimeout(r, 2200));
        (slug === 'bonding' || slug === 'pull' ? $('#btn-bond-print-preview') : $('#btn-dtr-print-preview'))?.click();
      }
      // &export=1 inside the InterLink sender's hidden frame: hand the finished report PDF (as base64) to the parent page
      if (q.get('export') === '1' && window.parent !== window) {
        const reply = (m) => window.parent.postMessage(Object.assign({ type: 'NXL_REPORT_PDF' }, m), location.origin);
        try {
          await new Promise((r) => setTimeout(r, 800));
          const src = $('#dtr-print-sheet-container');
          if (!src || !src.innerHTML.trim()) throw new Error('The report sheet could not be prepared.');
          const res = await fetch(BASE_URL + 'dtr/sheet-pdf', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="X-CSRF-TOKEN"]')?.content || '' },
            body: JSON.stringify({ scale: fitOnePageScale(src.innerHTML), html: src.innerHTML, name: 'Report' }),
          });
          if (!res.ok) throw new Error((await res.text()).slice(0, 200) || 'PDF failed');
          const buf = new Uint8Array(await res.arrayBuffer());
          let bin = '';
          for (let i = 0; i < buf.length; i += 0x8000) bin += String.fromCharCode.apply(null, buf.subarray(i, i + 0x8000));
          reply({ ok: true, b64: btoa(bin) });
        } catch (e) { reply({ ok: false, error: e.message }); }
      }
      // &export=html: hand the finished report sheet itself (what the approver reads) to the parent page
      if (q.get('export') === 'html' && window.parent !== window) {
        const reply = (m) => window.parent.postMessage(Object.assign({ type: 'NXL_REPORT_HTML' }, m), location.origin);
        try {
          await new Promise((r) => setTimeout(r, 1200));
          const src = $('#dtr-print-sheet-container');
          if (!src || !src.innerHTML.trim()) throw new Error('The report sheet could not be prepared.');
          reply({ ok: true, html: src.innerHTML });
        } catch (e) { reply({ ok: false, error: e.message }); }
      }
    }, 900);
  })();


  // =========================================================================
  // WHOLE SHOE TEST SUITE ENGINE & LIVE TELEMETRY (MARC)
  // =========================================================================
  let currentWstCategory = 'bally-flex';
  let currentWstTestId = null;
  let activeWstGroup = 'dry';

  // Modal open / close handlers for "Create New Test" popup
  const openWstNewModal = () => {
    refreshNextLabref();
    $('#wst-new-modal-backdrop')?.classList.remove('hidden');
  };

  const closeWstNewModal = () => {
    $('#wst-new-modal-backdrop')?.classList.add('hidden');
  };

  $('#btn-wst-open-new-modal')?.addEventListener('click', openWstNewModal);
  $('#btn-wst-trigger-new')?.addEventListener('click', openWstNewModal);
  $('#btn-wst-close-new-modal')?.addEventListener('click', closeWstNewModal);
  $('#btn-wst-new-cancel')?.addEventListener('click', closeWstNewModal);

  // Fetch list of records from dbnexus
  const loadWstRecords = async (category = currentWstCategory, q = '') => {
    const tbody = $('#wst-list-tbody');
    const cntBadge = $('#wst-record-count');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="8" class="muted" style="text-align:center;padding:24px"><svg class="ic animate-spin" aria-hidden="true" style="margin-right:6px"><use href="#i-sync"/></svg> Loading test sessions from dbnexus...</td></tr>';
    try {
      const res = await apiFetch(`wst/records?category=${encodeURIComponent(category)}&q=${encodeURIComponent(q)}`);
      if (!res.success || !res.records || res.records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="muted" style="text-align:center;padding:28px">No test sessions found for this category. Click "+ NEW TEST" to create one.</td></tr>';
        if (cntBadge) cntBadge.textContent = 'Showing 0 records';
        $('#wst-kpi-total') && ($('#wst-kpi-total').textContent = '0');
        $('#wst-kpi-running') && ($('#wst-kpi-running').textContent = '0');
        $('#wst-kpi-completed') && ($('#wst-kpi-completed').textContent = '0');
        $('#wst-kpi-pending') && ($('#wst-kpi-pending').textContent = '0');
        return;
      }

      // Compute quick KPIs
      let runCount = 0;
      let doneCount = 0;
      let pendCount = 0;
      res.records.forEach((r) => {
        if (r.is_running == 1 || r.status === 'RUNNING') runCount++;
        else if (r.status === 'COMPLETED') doneCount++;
        else pendCount++;
      });

      $('#wst-kpi-total') && ($('#wst-kpi-total').textContent = res.records.length.toLocaleString());
      $('#wst-kpi-running') && ($('#wst-kpi-running').textContent = runCount.toLocaleString());
      $('#wst-kpi-completed') && ($('#wst-kpi-completed').textContent = doneCount.toLocaleString());
      $('#wst-kpi-pending') && ($('#wst-kpi-pending').textContent = pendCount.toLocaleString());

      if (cntBadge) cntBadge.textContent = `Showing ${res.records.length} record(s)`;
      tbody.innerHTML = res.records.map((r, i) => {
        const isRun = r.is_running == 1 || r.status === 'RUNNING';
        const isDone = r.status === 'COMPLETED';
        const pillClass = isRun ? 'dtr-pill-blue' : (isDone ? 'dtr-pill-pass' : 'dtr-pill-warn');
        const targetCycles = Number(r.target_cycles || 100000);
        const currCycles = Number(r.current_cycles || 0);
        const pct = Math.min(100, Math.round((currCycles / targetCycles) * 100));

        return `
          <tr class="clickable-row wst-row" data-id="${r.id}" style="cursor:pointer">
            <td class="mono">${i + 1}</td>
            <td><b class="mono" style="color:var(--brand,#2563eb);font-size:13.5px">${escHtml(r.labreference)}</b></td>
            <td>${escHtml(r.testdate || '—')}</td>
            <td><span style="font-weight:600">${escHtml(r.type || 'Standard')}</span></td>
            <td><span class="mono sm muted">${escHtml(r.machine_number || 'MC-01')} &bull; ${escHtml(r.technician || r.operator || 'QC')}</span></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="flex:1;background:rgba(255,255,255,0.06);height:6px;border-radius:3px;overflow:hidden;min-width:70px">
                  <div style="background:${isDone ? '#22c55e' : '#3b82f6'};width:${pct}%;height:100%"></div>
                </div>
                <span class="mono sm" style="white-space:nowrap">${currCycles.toLocaleString()} / ${targetCycles.toLocaleString()}</span>
              </div>
            </td>
            <td><span class="dtr-pill ${pillClass}">${escHtml(r.status || 'PENDING')}</span></td>
            <td style="text-align:right">
              <button type="button" class="dtr-btn-xs dtr-btn-primary wst-open-record-btn" data-id="${r.id}" style="box-shadow:0 1px 4px rgba(37,99,235,0.25)">
                <svg class="ic" aria-hidden="true" style="margin-right:2px"><use href="#i-grid"/></svg> Open Monitor
              </button>
            </td>
          </tr>
        `;
      }).join('');

      tbody.querySelectorAll('.wst-open-record-btn, .wst-row').forEach((el) => {
        el.addEventListener('click', (e) => {
          e.stopPropagation();
          const tr = el.closest('tr');
          const id = tr?.dataset.id;
          if (id) openWstMonitorModal(id);
        });
      });
    } catch (err) {
      console.error('loadWstRecords failed:', err);
      tbody.innerHTML = `<tr><td colspan="8" style="color:#ef4444;text-align:center;padding:24px">Failed to load test sessions from dbnexus: ${escHtml(err.message)}<br><button type="button" class="dtr-btn-xs dtr-btn-outline" id="btn-wst-retry-load" style="margin-top:10px;display:inline-flex;align-items:center;gap:4px"><svg class="ic" aria-hidden="true"><use href="#i-sync"/></svg> Retry</button></td></tr>`;
      $('#btn-wst-retry-load')?.addEventListener('click', () => loadWstRecords(category, q));
    }
  };

  // Search filter
  let wstSearchDebounce = null;
  $('#wst-list-search')?.addEventListener('input', (e) => {
    clearTimeout(wstSearchDebounce);
    wstSearchDebounce = setTimeout(() => {
      loadWstRecords(currentWstCategory, e.target.value.trim());
    }, 250);
  });

  // Switch category tabs
  $$('.wst-cat-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      $$('.wst-cat-tab').forEach((t) => t.classList.remove('active'));
      tab.classList.add('active');
      currentWstCategory = tab.dataset.wstCat;
      const title = tab.textContent.trim().toUpperCase();
      if ($('#wst-modal-title')) $('#wst-modal-title').textContent = `${title} MONITORING`;
      if ($('#wst-new-cat')) $('#wst-new-cat').value = currentWstCategory;
      refreshNextLabref();
      loadWstRecords(currentWstCategory);
      showToast(`Category: ${tab.textContent.trim()}`);
    });
  });

  let wstCatalogData = {};

  const refreshNextLabref = async () => {
    const typeSelect = $('#wst-new-type');
    const type = typeSelect?.value || '';
    const date = $('#wst-new-date')?.value || new Date().toISOString().slice(0, 10);
    try {
      const res = await apiFetch(`wst/next-labref?category=${encodeURIComponent(currentWstCategory)}&type=${encodeURIComponent(type)}&date=${encodeURIComponent(date)}`);
      if (res.success) {
        wstCatalogData = res.catalog || {};

        if (res.materials && typeSelect) {
          const currentVal = typeSelect.value;
          typeSelect.innerHTML = res.materials.map((m) => `<option value="${esc(m)}" ${m === currentVal ? 'selected' : ''}>${esc(m)}</option>`).join('');
          if (!typeSelect.value && res.materials.length > 0) {
            typeSelect.value = res.materials[0];
          }
        }
        if (res.labref && $('#wst-new-labref')) {
          $('#wst-new-labref').value = res.labref;
        }

        const defaultMachines = {
          'hydrolysis': 'CH-HYDRO-01',
          'heat-aging': 'OVEN-01',
          'washing': 'WASH-MC-01',
          'uv-light': 'UV-CHAMBER-01',
          'flexing': 'MC-FLEX-01',
          'bally-flex': 'MC-FLEX-01',
        };
        if ($('#wst-new-machine') && defaultMachines[currentWstCategory]) {
          $('#wst-new-machine').value = defaultMachines[currentWstCategory];
        }

        syncNewSampleGroups();
      }
    } catch (_) {}
  };

    // Calculate LMS batch breakdown helper
    const batchSummary = (count, batchSize) => {
      const batches = [];
      for (let remaining = count; remaining > 0; remaining -= batchSize) {
        batches.push(Math.min(remaining, batchSize));
      }
      return batches.map((size, index) => `Batch ${index + 1}: ${size}`).join(' · ');
    };

    const syncNewSampleGroups = (resetCounts = false) => {
      const type = $('#wst-new-type')?.value;
      const mat = wstCatalogData[type];
      const groups = mat?.groups || {};

      ['dry', 'wet', 'hydro'].forEach((g) => {
        const check = $(`#wst-check-${g}`);
        const input = $(`#wst-count-${g}`);
        const prev = $(`#wst-preview-${g}`);
        const opt = $(`#wst-option-${g}`);
        if (!check || !input || !prev || !opt) return;

        const ok = Boolean(groups[g]);
        check.disabled = !ok;
        opt.classList.toggle('is-disabled', !ok);

        if (!ok) {
          check.checked = false;
          input.disabled = true;
          prev.textContent = '';
        } else {
          if (resetCounts) {
            check.checked = true;
            input.value = String(groups[g].count || 4);
          }
          input.disabled = !check.checked;
        }

        const standardCount = Number(groups[g]?.count || 0);
        const count = check.checked ? Number(input.value) || 0 : 0;
        if (standardCount && count) {
          prev.textContent = batchSummary(count, standardCount);
        } else if (standardCount) {
          prev.textContent = `Standard batch: ${standardCount}`;
        } else {
          prev.textContent = '';
        }
      });

      // Keep at least one selected if available
      const anyChecked = ['dry', 'wet', 'hydro'].some((g) => $(`#wst-check-${g}`)?.checked);
      if (!anyChecked) {
        const dry = $('#wst-check-dry');
        if (dry && !dry.disabled) {
          dry.checked = true;
          const dryInput = $('#wst-count-dry');
          if (dryInput) dryInput.disabled = false;
        }
      }

      // Update trigger button label e.g. "4D / 4W"
      const shortLabels = { dry: 'D', wet: 'W', hydro: 'H' };
      const selectedParts = [];
      ['dry', 'wet', 'hydro'].forEach((g) => {
        const check = $(`#wst-check-${g}`);
        const input = $(`#wst-count-${g}`);
        if (check?.checked) {
          const val = Number(input?.value) || 0;
          if (val > 0) selectedParts.push(`${val}${shortLabels[g]}`);
        }
      });
      const trigger = $('#wst-new-samples-trigger');
      if (trigger) {
        trigger.textContent = selectedParts.length ? selectedParts.join(' / ') : 'Choose sample types and amount';
      }
    };

    // Modal open / close handlers for sample modal
    const openSampleModal = () => {
      syncNewSampleGroups(false);
      $('#wst-sample-modal-backdrop')?.classList.remove('hidden');
    };
    const closeSampleModal = () => {
      $('#wst-sample-modal-backdrop')?.classList.add('hidden');
    };

    $('#wst-new-samples-trigger')?.addEventListener('click', openSampleModal);
    $('#btn-wst-open-sample-modal')?.addEventListener('click', openSampleModal);
    $('#btn-wst-close-sample-modal')?.addEventListener('click', closeSampleModal);
    $('#btn-wst-done-sample-modal')?.addEventListener('click', () => {
      syncNewSampleGroups(false);
      closeSampleModal();
    });
    $('#wst-sample-modal-backdrop')?.addEventListener('click', (e) => {
      if (e.target.id === 'wst-sample-modal-backdrop') closeSampleModal();
    });

    ['dry', 'wet', 'hydro'].forEach((g) => {
      $(`#wst-check-${g}`)?.addEventListener('change', () => syncNewSampleGroups(false));
      $(`#wst-count-${g}`)?.addEventListener('input', () => syncNewSampleGroups(false));
    });

    $('#btn-wst-regen-labref')?.addEventListener('click', refreshNextLabref);
    $('#wst-new-type')?.addEventListener('change', () => {
      syncNewSampleGroups(true);
      refreshNextLabref();
    });
    $('#wst-new-date')?.addEventListener('change', refreshNextLabref);
    $('#wst-new-cat')?.addEventListener('change', (e) => {
      currentWstCategory = e.target.value;
      $$('.wst-cat-tab').forEach((t) => t.classList.toggle('active', t.dataset.wstCat === currentWstCategory));
      refreshNextLabref();
    });

  // Cached state for active monitor test
  let currentWstGroups = {};
  let currentWstState = {};

  // Live monitor modal renderer
  const openWstMonitorModal = async (testId) => {
    currentWstTestId = testId;
    const tableWrap = $('.wst-monitoring-container .dtr-table-wrap');
    if (tableWrap) {
      tableWrap.innerHTML = '<div style="text-align:center;padding:24px;color:#64748b"><svg class="ic animate-spin" aria-hidden="true" style="margin-right:6px"><use href="#i-sync"/></svg> Loading checkpoint matrix...</div>';
    }
    $('#wst-monitor-modal-backdrop')?.classList.remove('hidden');

    try {
      const res = await apiFetch(`wst/record/${testId}`);
      if (!res.success || !res.record) {
        showToast('Failed to load test record.');
        return;
      }

      const r = res.record;
      currentWstGroups = res.groups || {};
      currentWstState = res.state || {};

      if ($('#wst-modal-dt-labref')) $('#wst-modal-dt-labref').value = r.labreference;
      if ($('#wst-card-dt-labref')) $('#wst-card-dt-labref').textContent = r.labreference;
      if ($('#wst-modal-labref-badge')) $('#wst-modal-labref-badge').textContent = r.labreference;
      if ($('#wst-modal-dt-date')) $('#wst-modal-dt-date').value = r.testdate || '';
      if ($('#wst-card-dt-date')) $('#wst-card-dt-date').textContent = r.testdate || '—';
      const sampleSummary = `${r.samplecountdry}D / ${r.samplecountwet}W / ${r.samplecounthydro}H`;
      if ($('#wst-modal-dt-samples')) $('#wst-modal-dt-samples').value = sampleSummary;
      if ($('#wst-card-dt-samples')) $('#wst-card-dt-samples').textContent = sampleSummary;
      if ($('#wst-modal-dt-type')) $('#wst-modal-dt-type').value = r.type || 'Standard';
      if ($('#wst-card-dt-type')) $('#wst-card-dt-type').textContent = r.type || 'Standard Whole Shoe Flexing';

      if ($('#wst-view-op')) $('#wst-view-op').textContent = r.technician || r.operator || 'QC Specialist';
      if ($('#wst-view-mc')) $('#wst-view-mc').textContent = r.machine_number || 'MC-FLEX-01';
      if ($('#wst-view-temp')) $('#wst-view-temp').innerHTML = `${Number(r.temperature || 23.0).toFixed(1)} &deg;C`;
      if ($('#wst-view-hum')) $('#wst-view-hum').textContent = `${Number(r.humidity || 50.0).toFixed(1)} %RH`;
      if ($('#wst-view-note')) $('#wst-view-note').textContent = r.remarks || (r.status === 'COMPLETED' ? 'Session Completed' : 'In Progress');

      const isRun = r.is_running == 1 || r.status === 'RUNNING';
      const isDone = r.status === 'COMPLETED' || res.isCompleted;
      
      // Update dynamic status badge and pulsing dot
      const badge = $('#wst-modal-status-badge');
      const badgeText = $('#wst-modal-status-text');
      const pulseDot = $('#wst-modal-pulse-dot');
      if (badge) {
        const statusStr = isDone ? 'COMPLETED' : (isRun ? 'RUNNING' : (r.status || 'PENDING'));
        if (badgeText) badgeText.textContent = statusStr;
        else badge.textContent = statusStr;
        badge.className = `dtr-pill ${isRun ? 'dtr-pill-pass' : (isDone ? 'dtr-pill-blue' : 'dtr-pill-warn')}`;
      }
      if (pulseDot) {
        pulseDot.className = `wst-pulse-dot ${isRun ? 'wst-pulse-dot-green' : (isDone ? 'wst-pulse-dot-blue' : 'wst-pulse-dot-amber')}`;
      }

      // State-Aware Action Rail: Toggle RUN vs Active indicator vs Disabled on Complete
      const startBtn = $('#btn-wst-modal-start');
      const runningBar = $('#wst-running-indicator-bar');
      const findingsBtn = $('#btn-wst-modal-findings');
      const stopSampleBtn = $('#btn-wst-modal-stop-sample');
      const stopTestBtn = $('#btn-wst-modal-stop');

      if (isRun && !isDone) {
        startBtn?.classList.add('hidden');
        runningBar?.classList.remove('hidden');
      } else {
        startBtn?.classList.remove('hidden');
        runningBar?.classList.add('hidden');
        if (startBtn) {
          startBtn.disabled = isDone;
          startBtn.style.opacity = isDone ? '0.45' : '1';
          startBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
        }
      }

      // When test is completed (all samples finished or stop test triggered)
      if (findingsBtn) {
        findingsBtn.disabled = isDone;
        findingsBtn.style.opacity = isDone ? '0.45' : '1';
        findingsBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
      }
      if (stopSampleBtn) {
        stopSampleBtn.disabled = isDone;
        stopSampleBtn.style.opacity = isDone ? '0.45' : '1';
        stopSampleBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
      }
      if (stopTestBtn) {
        stopTestBtn.disabled = isDone;
        stopTestBtn.style.opacity = isDone ? '0.45' : '1';
        stopTestBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
      }

      renderMonitoringTable(res.groups, res.state);
      showToast(`Loaded monitor: ${r.labreference}`);
    } catch (err) {
      showToast(`Error opening monitor: ${err.message}`);
    }
  };

  const closeWstMonitorModal = () => {
    $('#wst-monitor-modal-backdrop')?.classList.add('hidden');
    loadWstRecords(currentWstCategory);
  };

  $('#btn-wst-open-modal-monitor')?.addEventListener('click', () => {
    const firstRow = $('#wst-list-tbody tr.wst-row');
    if (firstRow?.dataset.id) openWstMonitorModal(firstRow.dataset.id);
    else showToast('Please select a test from the queue first.');
  });
  $('#btn-wst-close-monitor-modal')?.addEventListener('click', closeWstMonitorModal);
  $('#btn-wst-cancel-monitor-modal')?.addEventListener('click', closeWstMonitorModal);

  // Render monitoring cycle grid
  const renderMonitoringTable = (groups, state) => {
    const groupTabsContainer = $('.wst-monitoring-container .wst-group-tabs');
    const tableWrap = $('.wst-monitoring-container .dtr-table-wrap');
    if (!groupTabsContainer || !tableWrap) return;

    const availableGroups = Object.keys(groups || {});
    if (availableGroups.length === 0) {
      tableWrap.innerHTML = '<p class="muted" style="padding:16px;text-align:center">No sample groups configured for this test.</p>';
      return;
    }

    if (!availableGroups.includes(activeWstGroup)) {
      activeWstGroup = availableGroups[0];
    }

    // Populate the findings modal cycle dropdown with current category's exact cycles
    const findingsCycleSelect = $('#wst-findings-cycle');
    if (findingsCycleSelect && groups[activeWstGroup]?.cycles) {
      findingsCycleSelect.innerHTML = groups[activeWstGroup].cycles.map((c) => {
        return `<option value="${c}">${Number(c).toLocaleString()} Cycles</option>`;
      }).join('');
    }

    groupTabsContainer.innerHTML = availableGroups.map((gKey) => {
      const g = groups[gKey];
      return `<button type="button" class="wst-gt-btn wst-modal-gt-btn ${gKey === activeWstGroup ? 'active' : ''}" data-modal-gt="${gKey}">${g.label} (${g.count})</button>`;
    }).join('');

    groupTabsContainer.querySelectorAll('.wst-modal-gt-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        activeWstGroup = btn.dataset.modalGt;
        renderMonitoringTable(groups, state);
      });
    });

    const curr = groups[activeWstGroup];
    if (!curr) return;

    const cycles = curr.cycles || [];
    const count = curr.count || 1;
    const recordedMap = state?.recorded?.[activeWstGroup] || {};
    const stoppedMap = state?.stopped?.[activeWstGroup] || {};

    // Determine currently active target cycle (the lowest cycle with unrecorded running samples)
    let activeCycle = null;
    let maxRecordedCycle = 0;
    for (const c of cycles) {
      let cycleHasUnrecorded = false;
      for (let s = 1; s <= count; s++) {
        const isStopped = stoppedMap[s];
        const isRecorded = (recordedMap[s] || []).includes(c);
        if (isRecorded && c > maxRecordedCycle) maxRecordedCycle = c;
        if (!isStopped && !isRecorded) {
          cycleHasUnrecorded = true;
        }
      }
      if (cycleHasUnrecorded && activeCycle === null) {
        activeCycle = c;
      }
    }

    // Update Overall Test Progress Bar
    const targetCycleLimit = cycles[cycles.length - 1] || 100000;
    const pct = Math.min(100, Math.round((maxRecordedCycle / targetCycleLimit) * 100));
    if ($('#wst-progress-cycles')) $('#wst-progress-cycles').textContent = maxRecordedCycle.toLocaleString();
    if ($('#wst-progress-target')) $('#wst-progress-target').textContent = targetCycleLimit.toLocaleString();
    if ($('#wst-progress-pct')) $('#wst-progress-pct').textContent = `${pct}%`;
    if ($('#wst-progress-bar-fill')) $('#wst-progress-bar-fill').style.width = `${pct}%`;

    let html = `
      <table class="dtr-table wst-cycle-table">
        <thead>
          <tr>
            <th style="min-width:110px">SPECIMEN</th>
            ${cycles.map((c) => {
              const isActive = c === activeCycle;
              return `<th class="${isActive ? 'wst-cell-active' : ''}" style="${isActive ? 'border-top:2px solid #3b82f6;' : ''}">${Number(c).toLocaleString()}</th>`;
            }).join('')}
          </tr>
        </thead>
        <tbody>
    `;

    const batchSize = Number(curr?.batchSize || curr?.standardCount || 4);

    for (let s = 1; s <= count; s++) {
      const rec = recordedMap[s] || [];
      const isStopped = Boolean(stoppedMap[s]);
      const batch = Math.floor((s - 1) / batchSize) + 1;
      const isBatchStart = (s - 1) % batchSize === 0;
      const rowClass = isBatchStart ? 'sample-batch-start' : '';
      const specimenLabel = isBatchStart
        ? `${s}-${curr.prefix}<span class="sample-batch-label">Batch ${batch}</span>`
        : `${s}-${curr.prefix}`;

      html += `<tr class="${rowClass}"><td class="mono sample-label" style="font-weight:700">${specimenLabel}</td>`;
      cycles.forEach((c) => {
        const isRecorded = rec.includes(c);
        let cellClass = 'cycle-cell';

        // LMS Matching Display:
        // 1. isRecorded -> soft green background (#86efac)
        // 2. isStopped (and not recorded) -> diagonal grey stripe pattern
        // 3. pending -> blank cell
        if (isRecorded) {
          cellClass += ' is-recorded';
        } else if (isStopped) {
          cellClass += ' is-stopped';
        }

        html += `<td class="${cellClass}" data-group="${activeWstGroup}" data-sample="${s}" data-cycle="${c}"></td>`;
      });
      html += `</tr>`;
    }

    html += `</tbody></table>`;
    tableWrap.innerHTML = html;
  };

  // Findings modal controls
  const openFindingsModal = () => {
    const curr = currentWstGroups[activeWstGroup];
    const count = curr?.count || 4;
    const groupName = curr?.label || `${activeWstGroup.toUpperCase()} SAMPLES`;
    const cycles = curr?.cycles || [];
    const recordedMap = currentWstState?.recorded?.[activeWstGroup] || {};
    const stoppedMap = currentWstState?.stopped?.[activeWstGroup] || {};

    // Auto-detect next cycle (LMS behavior): lowest cycle that has unrecorded running specimens
    let nextUnrecordedCycle = cycles[cycles.length - 1] || 25000;
    for (const c of cycles) {
      let allDone = true;
      for (let s = 1; s <= count; s++) {
        const isStopped = Boolean(stoppedMap[s]);
        const isRecorded = (recordedMap[s] || []).includes(c);
        if (!isStopped && !isRecorded) {
          allDone = false;
          break;
        }
      }
      if (!allDone) {
        nextUnrecordedCycle = c;
        break;
      }
    }

    if ($('#wst-findings-cycle')) {
      $('#wst-findings-cycle').value = nextUnrecordedCycle;
    }

    // Set remark placeholder and text based on category mode (LMS parity)
    const isFlexCategory = ['bally-flex', 'ross-flex'].includes(currentWstCategory);
    if ($('#wst-findings-text')) {
      if (isFlexCategory) {
        $('#wst-findings-text').value = 'NO DAMAGE OF COATING';
        $('#wst-findings-text').placeholder = 'No cracking, peeling, or delamination observed.';
      } else {
        $('#wst-findings-text').value = 'No noticeable abrasion or surface damage.';
        $('#wst-findings-text').placeholder = 'Enter inspection details (e.g. slight color transfer, minor wear, acceptable)...';
      }
    }

    if ($('#wst-findings-group-display')) $('#wst-findings-group-display').value = groupName;
    if ($('#wst-findings-start')) {
      $('#wst-findings-start').value = 1;
      $('#wst-findings-start').max = count;
    }
    if ($('#wst-findings-end')) {
      $('#wst-findings-end').value = count;
      $('#wst-findings-end').max = count;
    }

    // Render batch & all preset range buttons (LMS Parity)
    const presetsContainer = $('#wst-findings-presets');
    if (presetsContainer) {
      const batchSize = Number(curr?.batchSize || curr?.standardCount || 4);
      let presetBtns = '';
      for (let bStart = 1, bNum = 1; bStart <= count; bStart += batchSize, bNum++) {
        const bEnd = Math.min(count, bStart + batchSize - 1);
        presetBtns += `<button type="button" class="lf-range-preset" data-first="${bStart}" data-last="${bEnd}">Batch ${bNum} (${bStart}–${bEnd})</button>`;
      }
      presetBtns += `<button type="button" class="lf-range-preset is-active" data-first="1" data-last="${count}">All (1–${count})</button>`;
      presetsContainer.innerHTML = presetBtns;

      presetsContainer.querySelectorAll('.lf-range-preset').forEach((btn) => {
        btn.addEventListener('click', () => {
          if ($('#wst-findings-start')) $('#wst-findings-start').value = btn.dataset.first;
          if ($('#wst-findings-end')) $('#wst-findings-end').value = btn.dataset.last;
          presetsContainer.querySelectorAll('.lf-range-preset').forEach((p) => p.classList.toggle('is-active', p === btn));
          updateFindingsRangeLabel();
        });
      });
    }

    updateFindingsRangeLabel();
    $('#wst-findings-modal-backdrop')?.classList.remove('hidden');
  };

  const closeFindingsModal = () => {
    $('#wst-findings-modal-backdrop')?.classList.add('hidden');
  };

  const updateFindingsRangeLabel = () => {
    const s = parseInt($('#wst-findings-start')?.value || '1', 10);
    const e = parseInt($('#wst-findings-end')?.value || '1', 10);
    const minS = Math.min(s, e);
    const maxS = Math.max(s, e);
    const stoppedMap = currentWstState?.stopped?.[activeWstGroup] || {};

    let totalSelected = 0;
    let runningSelected = 0;
    let stoppedSelected = 0;

    for (let i = minS; i <= maxS; i++) {
      totalSelected++;
      if (stoppedMap[i]) {
        stoppedSelected++;
      } else {
        runningSelected++;
      }
    }

    if ($('#wst-findings-range-info')) {
      const parts = [`${runningSelected} running specimen(s)`];
      if (stoppedSelected > 0) parts.push(`${stoppedSelected} stopped (will be skipped)`);
      $('#wst-findings-range-info').textContent = parts.join(' · ');
    }
  };

  $('#wst-findings-start')?.addEventListener('input', updateFindingsRangeLabel);
  $('#wst-findings-end')?.addEventListener('input', updateFindingsRangeLabel);

  $('#btn-wst-action-monitored')?.addEventListener('click', openFindingsModal);
  $('#btn-wst-modal-findings')?.addEventListener('click', openFindingsModal);
  $('#btn-wst-close-findings-modal')?.addEventListener('click', closeFindingsModal);
  $('#btn-wst-cancel-findings')?.addEventListener('click', closeFindingsModal);

  $('#btn-wst-save-findings')?.addEventListener('click', async () => {
    if (!currentWstTestId) {
      showToast('No active test selected.');
      return;
    }
    const cycle = parseInt($('#wst-findings-cycle')?.value || '25000', 10);
    const findings = $('#wst-findings-text')?.value.trim() || 'Passed inspection';
    const pass = $('#wst-findings-pass')?.checked;

    const startNum = parseInt($('#wst-findings-start')?.value || '1', 10);
    const endNum = parseInt($('#wst-findings-end')?.value || '1', 10);
    const stoppedMap = currentWstState?.stopped?.[activeWstGroup] || {};
    const sampleArr = [];
    for (let i = Math.min(startNum, endNum); i <= Math.max(startNum, endNum); i++) {
      if (!stoppedMap[i]) {
        sampleArr.push(i);
      }
    }

    if (sampleArr.length === 0) {
      showToast('All specimens in the selected range are stopped. No specimens to record.');
      return;
    }

    try {
      const res = await apiFetch(`wst/log-findings/${currentWstTestId}`, {
        method: 'POST',
        body: JSON.stringify({
          cycle,
          group: activeWstGroup,
          findings,
          pass,
          samples: sampleArr,
        }),
      });

      if (res.success) {
        closeFindingsModal();
        openWstMonitorModal(currentWstTestId);
        showToast(res.message || 'Saved checkpoint findings.', 'success');
      } else {
        showToast(res.message || 'Failed to save findings.', 'error');
      }
    } catch (err) {
      showToast(`Error: ${err.message}`, 'error');
    }
  });

  // =========================================================================
  // STOP SAMPLE MODAL CONTROLS (LMS PARITY)
  // =========================================================================
  const openStopSampleModal = () => {
    const list = $('#wst-stop-sample-list');
    if (!list) return;

    const curr = currentWstGroups[activeWstGroup];
    const count = curr?.count || 4;
    const stoppedMap = currentWstState?.stopped?.[activeWstGroup] || {};
    const recordedMap = currentWstState?.recorded?.[activeWstGroup] || {};
    const cycles = curr?.cycles || [];
    const lastCycle = cycles.length > 0 ? cycles[cycles.length - 1] : 0;

    let itemsHtml = '';
    let runningCount = 0;

    for (let s = 1; s <= count; s++) {
      const isStopped = Boolean(stoppedMap[s]);
      const isComplete = lastCycle > 0 && (recordedMap[s] || []).includes(lastCycle);
      const isFinished = isStopped || isComplete;
      if (!isFinished) runningCount++;

      itemsHtml += `
        <label style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:8px;background:rgba(255,255,255,${isFinished ? '0.01' : '0.04'});border:1px solid rgba(148,163,184,${isFinished ? '0.08' : '0.2'});cursor:${isFinished ? 'default' : 'pointer'};opacity:${isFinished ? '0.5' : '1'}">
          <div style="display:flex;align-items:center;gap:10px">
            <input type="checkbox" class="wst-stop-sample-chk" value="${s}" ${isStopped ? 'checked disabled' : (isComplete ? 'disabled' : '')} style="width:16px;height:16px">
            <span class="mono" style="font-weight:700;font-size:13.5px">${s}-${curr.prefix}</span>
          </div>
          <span class="sm mono" style="color:${isStopped ? '#ef4444' : (isComplete ? '#22c55e' : '#38bdf8')}">
            ${isStopped ? 'STOPPED' : (isComplete ? 'COMPLETED' : 'RUNNING')}
          </span>
        </label>
      `;
    }

    if (runningCount === 0) {
      itemsHtml += `<p class="muted" style="text-align:center;padding:16px">No running specimens remaining for this group.</p>`;
    }

    list.innerHTML = itemsHtml;
    $('#wst-stop-sample-modal-backdrop')?.classList.remove('hidden');
  };

  const closeStopSampleModal = () => {
    $('#wst-stop-sample-modal-backdrop')?.classList.add('hidden');
  };

  $('#btn-wst-modal-stop-sample')?.addEventListener('click', openStopSampleModal);
  $('#btn-wst-close-stop-sample')?.addEventListener('click', closeStopSampleModal);
  $('#btn-wst-cancel-stop-sample')?.addEventListener('click', closeStopSampleModal);

  $('#btn-wst-confirm-stop-sample')?.addEventListener('click', async () => {
    if (!currentWstTestId) return;

    const checked = $$('#wst-stop-sample-list .wst-stop-sample-chk:checked:not(:disabled)');
    const samplesToStop = Array.from(checked).map((c) => ({
      group: activeWstGroup,
      sample: parseInt(c.value, 10),
    }));

    if (samplesToStop.length === 0) {
      showToast('Please select at least one running sample to stop.');
      return;
    }

    try {
      const res = await apiFetch(`wst/stop-sample/${currentWstTestId}`, {
        method: 'POST',
        body: JSON.stringify({ samples: samplesToStop }),
      });

      if (res.success) {
        closeStopSampleModal();
        openWstMonitorModal(currentWstTestId);
        showToast(res.message || 'Specimen stopped successfully.');
      } else {
        showToast(res.message || 'Failed to stop specimen.');
      }
    } catch (err) {
      showToast(`Error: ${err.message}`);
    }
  });

  // Modal actions (Start / Stop)
  $('#btn-wst-modal-start')?.addEventListener('click', async () => {
    if (!currentWstTestId) return;
    try {
      const res = await apiFetch(`wst/start/${currentWstTestId}`, { method: 'POST' });
      if (res.success) {
        openWstMonitorModal(currentWstTestId);
        showToast('Started test session.');
      }
    } catch (err) {
      showToast(`Error: ${err.message}`);
    }
  });

  // Dedicated Stop Entire Test Modal Handlers
  const openStopTestModal = () => {
    $('#wst-stop-test-modal-backdrop')?.classList.remove('hidden');
  };
  const closeStopTestModal = () => {
    $('#wst-stop-test-modal-backdrop')?.classList.add('hidden');
  };

  $('#btn-wst-modal-stop')?.addEventListener('click', openStopTestModal);
  $('#btn-wst-close-stop-test')?.addEventListener('click', closeStopTestModal);
  $('#btn-wst-cancel-stop-test')?.addEventListener('click', closeStopTestModal);

  $('#btn-wst-confirm-stop-test')?.addEventListener('click', async () => {
    if (!currentWstTestId) return;
    const confirmBtn = $('#btn-wst-confirm-stop-test');
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Stopping...';
    }

    try {
      const res = await apiFetch(`wst/stop/${currentWstTestId}`, { method: 'POST' });
      if (res.success) {
        closeStopTestModal();
        openWstMonitorModal(currentWstTestId);
        showToast(res.message || 'Entire test session stopped and marked COMPLETED.');
      } else {
        showToast(res.message || 'Failed to stop test session.');
      }
    } catch (err) {
      showToast(`Error: ${err.message}`);
    } finally {
      if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<svg class="ic" aria-hidden="true"><use href="#i-close"/></svg> Stop Entire Test';
      }
    }
  });

  // Create new test submit handler
  $('#btn-wst-create-submit')?.addEventListener('click', async () => {
    const category = $('#wst-new-cat')?.value || currentWstCategory;
    const type = $('#wst-new-type')?.value || 'Standard Flexing';
    const date = $('#wst-new-date')?.value || new Date().toISOString().slice(0, 10);
    const labreference = $('#wst-new-labref')?.value.trim();
    const machine = $('#wst-new-machine')?.value.trim() || 'MC-FLEX-01';
    const dry = $('#wst-check-dry')?.checked ? parseInt($('#wst-count-dry')?.value || '0', 10) : 0;
    const wet = $('#wst-check-wet')?.checked ? parseInt($('#wst-count-wet')?.value || '0', 10) : 0;
    const hydro = $('#wst-check-hydro')?.checked ? parseInt($('#wst-count-hydro')?.value || '0', 10) : 0;

    try {
      const res = await apiFetch('wst/create', {
        method: 'POST',
        body: JSON.stringify({
          category,
          type,
          date,
          labreference,
          machine_number: machine,
          dry,
          wet,
          hydro,
          target_cycles: 100000,
        }),
      });

      if (res.success && res.id) {
        closeWstNewModal();
        loadWstRecords(currentWstCategory);
        openWstMonitorModal(res.id);
        showToast(res.message || 'Created new Whole Shoe test session!');
      } else {
        showToast(res.message || 'Could not create test session.');
      }
    } catch (err) {
      showToast(`Creation error: ${err.message}`);
    }
  });

  $('#btn-wst-refresh-queue')?.addEventListener('click', () => {
    loadWstRecords(currentWstCategory);
    showToast('Refreshed test queue.');
  });

  // Print summary button inside modal
  $('#btn-wst-modal-print')?.addEventListener('click', () => {
    window.print();
  });

  // Background Live State Polling (Matches LMS 8s sync)
  let wstSyncInterval = null;
  const startWstLivePolling = () => {
    if (wstSyncInterval) clearInterval(wstSyncInterval);
    wstSyncInterval = setInterval(async () => {
      // Only poll when modal is visible and test is active
      const modal = $('#wst-monitor-modal-backdrop');
      if (!modal || modal.classList.contains('hidden') || !currentWstTestId) return;

      try {
        const res = await apiFetch(`wst/state/${currentWstTestId}`);
        if (res.success) {
          currentWstState.recorded = res.recorded || {};
          currentWstState.stopped = res.stopped || {};

          const isRun = res.is_running === 1;
          const isDone = res.completed || res.status === 'COMPLETED';
          
          const badge = $('#wst-modal-status-badge');
          const badgeText = $('#wst-modal-status-text');
          const pulseDot = $('#wst-modal-pulse-dot');
          if (badge) {
            const statusStr = isDone ? 'COMPLETED' : (isRun ? 'RUNNING' : (res.status || 'PENDING'));
            if (badgeText) badgeText.textContent = statusStr;
            else badge.textContent = statusStr;
            badge.className = `dtr-pill ${isRun ? 'dtr-pill-pass' : (isDone ? 'dtr-pill-blue' : 'dtr-pill-warn')}`;
          }
          if (pulseDot) {
            pulseDot.className = `wst-pulse-dot ${isRun ? 'wst-pulse-dot-green' : (isDone ? 'wst-pulse-dot-blue' : 'wst-pulse-dot-amber')}`;
          }

          const startBtn = $('#btn-wst-modal-start');
          const runningBar = $('#wst-running-indicator-bar');
          const findingsBtn = $('#btn-wst-modal-findings');
          const stopSampleBtn = $('#btn-wst-modal-stop-sample');
          const stopTestBtn = $('#btn-wst-modal-stop');

          if (isRun && !isDone) {
            startBtn?.classList.add('hidden');
            runningBar?.classList.remove('hidden');
          } else {
            startBtn?.classList.remove('hidden');
            runningBar?.classList.add('hidden');
            if (startBtn) {
              startBtn.disabled = isDone;
              startBtn.style.opacity = isDone ? '0.45' : '1';
              startBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
            }
          }

          if (findingsBtn) {
            findingsBtn.disabled = isDone;
            findingsBtn.style.opacity = isDone ? '0.45' : '1';
            findingsBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
          }
          if (stopSampleBtn) {
            stopSampleBtn.disabled = isDone;
            stopSampleBtn.style.opacity = isDone ? '0.45' : '1';
            stopSampleBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
          }
          if (stopTestBtn) {
            stopTestBtn.disabled = isDone;
            stopTestBtn.style.opacity = isDone ? '0.45' : '1';
            stopTestBtn.style.cursor = isDone ? 'not-allowed' : 'pointer';
          }

          renderMonitoringTable(currentWstGroups, currentWstState);
        }
      } catch (_) {}
    }, 8000);
  };
  startWstLivePolling();

  // Trigger initial list load if active tab is Whole Shoe Test
  loadWstRecords(currentWstCategory);
  $('[data-dtr-tab="wholeshoetest"]')?.addEventListener('click', () => {
    loadWstRecords(currentWstCategory);
  });



  // =========================================================================
  // SEND REPORT DROPDOWN & SYNOLOGY / EMAIL MODAL CONTROLLER
  // =========================================================================
  (() => {
    // one dropdown in each toolbar (Bonding, Whole Shoe, Material); they share the Synology / e-mail dialogs
    const sendWraps = $$('.dtr-send-dropdown-wrap');
    const closeSendMenus = () => sendWraps.forEach((w) => w.classList.remove('is-open'));
    const synoModal = $('#dtr-synology-modal-backdrop');
    const emailModal = $('#dtr-email-modal-backdrop');

    // Toggle dropdown on click
    sendWraps.forEach((w) => w.querySelector('.js-send-toggle')?.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = !w.classList.contains('is-open');
      closeSendMenus();
      w.classList.toggle('is-open', open);
    }));

    document.addEventListener('click', (e) => {
      if (!e.target.closest('.dtr-send-dropdown-wrap')) closeSendMenus();
    });

    // Attachment state for Synology
    let synoAttachment = {
      file: null,          // File object if uploaded
      base64: null,        // data URL if snapshot or base64
      fileName: '',
      fileSize: ''
    };

    // photos (uploaded, or taken with the phone through the QR code): up to 10. They never replace the report graph card.
    const MAX_PHOTOS = 10;
    let synoPhotos = [];   // [{file, base64, fileName, fileSize}]
    const photoSrc = (p) => p.base64 || URL.createObjectURL(p.file);
    const photoCard = (p, i) => `<div class="syno-preview-card"><img class="syno-preview-thumb" tabindex="0" role="button" title="Click to enlarge" src="${photoSrc(p)}" alt="Photo ${i + 1}: click to enlarge">
        <div class="syno-preview-meta"><span class="syno-preview-kind">Photo ${i + 1} of ${synoPhotos.length} (phone or upload)</span><span class="syno-preview-name">${escHtml(p.fileName || '')}</span><span class="syno-preview-size">${escHtml(p.fileSize || '')}</span>
        <button type="button" class="syno-remove-attach" data-rmphoto="${i}">&times; Remove this image</button></div></div>`;
    const updatePhotoPreview = () => {
      const list = $('#syno-photo-list');
      if (list) list.innerHTML = synoPhotos.map(photoCard).join('');
      renderEmailAttach();
    };
    // the e-mail dialog lists the same images (each can be removed there too)
    const renderEmailAttach = () => {
      const box = $('#email-attach-list'); if (!box) return;
      const card = (synoAttachment.file || synoAttachment.base64) ? `<div class="syno-preview-card"><img class="syno-preview-thumb" tabindex="0" role="button" title="Click to enlarge" src="${synoAttachment.base64 || URL.createObjectURL(synoAttachment.file)}" alt="Picture: click to enlarge">
        <div class="syno-preview-meta"><span class="syno-preview-kind">Image 1: report graph card</span><span class="syno-preview-name">${escHtml(synoAttachment.fileName || '')}</span><span class="syno-preview-size">${escHtml(synoAttachment.fileSize || '')}</span>
        <button type="button" class="syno-remove-attach" data-rm="card">&times; Remove this image</button></div></div>` : '';
      box.innerHTML = card + synoPhotos.map(photoCard).join('');
      const sum = $('#email-attach-summary'); if (sum) sum.textContent = attachSummary();
    };
    let photoLab = '';   // the Lab Ref the photo belongs to: a photo of one report is not offered on another
    const keepPhotoFor = (lab) => { if (photoLab !== lab) { synoPhotos = []; photoLab = lab; } };
    const attachSummary = () => {
      const n = [synoAttachment.fileName ? 'report graph card' : '', synoPhotos.length ? `${synoPhotos.length} photo${synoPhotos.length > 1 ? 's' : ''} (max ${MAX_PHOTOS})` : ''].filter(Boolean);
      return n.length ? `Attachments ready: ${n.join(' + ')}` : 'No image attachment selected.';
    };
    // data URL / File -> a File the server can take as an upload
    const toFile = async (att, fallbackName) => {
      if (att.file) return att.file;
      if (!att.base64) return null;
      const b = await (await fetch(att.base64)).blob();
      return new File([b], att.fileName || fallbackName, { type: b.type || 'image/png' });
    };

    const updateSynoPreview = () => {
      renderEmailAttach();
      const wrap = $('#syno-preview-wrap');
      const img = $('#syno-preview-img');
      const nameEl = $('#syno-preview-name');
      const sizeEl = $('#syno-preview-size');

      if (!wrap || !img || !nameEl || !sizeEl) return;

      if (synoAttachment.base64 || synoAttachment.file) {
        wrap.classList.remove('hidden');
        img.src = synoAttachment.base64 || (synoAttachment.file ? URL.createObjectURL(synoAttachment.file) : '');
        nameEl.textContent = synoAttachment.fileName || 'attachment.png';
        sizeEl.textContent = synoAttachment.fileSize || '';
      } else {
        wrap.classList.add('hidden');
        img.src = '';
        nameEl.textContent = '';
        sizeEl.textContent = '';
      }
    };

    // Extract current bonding report details
    // which report is open: the toolbar that is showing decides (Whole Shoe / Material / Bonding)
    const reportKind = () => {
      if ($('[data-dtr-for="wholeshoe"]:not([hidden])')) return 'wholeshoe';
      if ($('[data-dtr-for="material"]:not([hidden])')) return 'material';
      return 'bonding';
    };
    window.dtrReportLabRef = () => {
      const k = reportKind();
      return (k === 'wholeshoe' ? $('#ws-labref')?.value : k === 'material' ? $('#mf_LabRefNo')?.value : $('#bf_LabRefNo')?.value || '').trim();
    };
    const getOtherContext = (kind) => {
      const v = (sel) => ($(sel)?.value || '').trim();
      let res = 'PASS';
      let title; let labRef; let style; let color; let prodDate; let testDate; let tech; let cement = ''; let cause = '';
      if (kind === 'wholeshoe') {
        title = 'Whole Shoe Test'; labRef = v('#ws-labref'); style = v('#ws-style'); color = v('#ws-color');
        prodDate = v('#ws-po'); testDate = v('#ws-tested'); tech = v('#ws-tech'); cause = v('#ws-comments');
        const chips = $$('#ws-sections .wsf-pr').map((c) => c.textContent.trim().toUpperCase());
        res = chips.some((c) => c.startsWith('FAIL')) ? 'FAIL' : (chips.some((c) => c.startsWith('TOL')) ? 'TOLERABLE' : (chips.length && chips.every((c) => c.startsWith('PASS')) ? 'PASS' : (chips.some((c) => c.startsWith('PASS')) ? 'PASS' : 'PENDING')));
      } else {
        title = ($('#mf_MaterialType')?.value || '').trim() || 'Material Test'; labRef = v('#mf_LabRefNo'); style = v('#mf_ModelNoStyle') || v('#mf_MaterialDescription'); color = v('#mf_Color');
        const sheetV = (col) => (($(`#dtr-sheet-${($('#dtr-mat-form-slug')?.value || '').trim()} [data-col="${col}"]`))?.value || '').trim();
        const day = (t) => String(t || '').slice(0, 10);
        prodDate = day(sheetV('Received Date') || v('#mf_ReceivedDate')); testDate = day(sheetV('Tested Date') || v('#mf_TestedDate')); tech = sheetV('Technician') || v('#mf_TestedBy'); cement = v('#mf_SupplierFactory'); cause = v('#mf_Remarks');
        style = sheetV('Model No/Style') || style; color = sheetV('Color') || color;
        const r = (sheetV('Remarks') || v('#mf_Remarks')).toUpperCase();
        res = r.startsWith('FAIL') ? 'FAIL' : (r.startsWith('TOL') ? 'TOLERABLE' : (r ? 'PASS' : 'PENDING'));
        cause = '';
      }
      const lm = labRef.match(/([A-Z]\d+)/i);
      const line = lm ? lm[1].toUpperCase() : '';
      return { kind, title, labRef, style, color, prodDate, testDate, res, cement, cause, tech, checkedBy: '', line, articleTitle: title };
    };
    const getBondingContext = () => {
      const kind = reportKind();
      if (kind !== 'bonding') return getOtherContext(kind);
      const labRef = ($('#bf_LabRefNo')?.value || '').trim();
      const style = ($('#bf_ModelStyle')?.value || '').trim() || 'UA CLASSIC SLIP-ON';
      const color = ($('#bf_Color')?.value || '').trim() || '';
      const prodDate = ($('#bf_ProductionDate')?.value || '').trim() || new Date().toISOString().slice(5, 10);
      const testDate = ($('#bf_TestedDate')?.value || '').trim() || new Date().toISOString().slice(5, 10);
      const res = ($('#bf_Result')?.value || '').trim().toUpperCase() || 'PASS';
      const cement = ($('#bf_AdhesiveSupplier')?.value || '').trim() || 'ZHONGBU';
      const cause = ($('#bf_PossibleCause')?.value || '').trim() || ($('#bf_ModeOfFailure')?.value || '').trim() || 'POOR PRESSING';
      const tech = ($('#bf_Technician')?.value || '').trim().replace(/^DTP-QC-\d+\s*-\s*/, '') || 'LARINA';
      const checkedBy = "MA'AM NILA";

      // Line / Bldg extraction from LabRef (e.g. DTP20261031FAA5-R -> A5 or B3)
      let line = 'A1';
      let articleTitle = 'First Article';
      const lm = labRef.match(/([A-Z]\d+)/i);
      if (lm) {
        line = lm[1].toUpperCase();
      }
      if (/FA/i.test(labRef)) {
        articleTitle = `${line} First Article`;
      } else {
        articleTitle = `${line} Production`;
      }

      return {
        kind: 'bonding',
        title: 'Whole Shoe Bonding Test',
        labRef,
        style,
        color,
        prodDate,
        testDate,
        res,
        cement,
        cause,
        tech,
        checkedBy,
        line,
        articleTitle
      };
    };

    // Message Templates
    const buildMessage = (templateKey, ctx) => {
      if (ctx.kind && ctx.kind !== 'bonding') {
        return `${ctx.res} - ${ctx.title}\nLab Ref: ${ctx.labRef}\n${ctx.style}${ctx.color ? ' / ' + ctx.color : ''}${ctx.testDate ? '\nTested Date: ' + ctx.testDate : ''}${ctx.tech ? '\nTested by: ' + ctx.tech : ''}`;
      }
      switch (templateKey) {
        case 'first-article':
          return `Hello everyone, please see the result for ${ctx.articleTitle}\n\n${ctx.res}  ${ctx.style}${ctx.color ? ' ' + ctx.color : ''}`;

        case 'failure-alert':
          return `FAILED\nPRODUCTION DATE: ${ctx.prodDate}\n${ctx.line} UPPER TO FOXING - ${ctx.cause.toUpperCase()}\nCEMENT: ${ctx.cement.toUpperCase()}\nTESTED BY: ${ctx.tech.toUpperCase()}\nCHECKED BY: ${ctx.checkedBy}`;

        case 'daily-summary':
          const failCount = ctx.res === 'FAIL' ? 1 : 0;
          const aCount = ctx.line.startsWith('A') && failCount ? 1 : 0;
          const bCount = ctx.line.startsWith('B') && failCount ? 1 : 0;
          return `Here's the status of daily bonding:\nProduction Date: ${ctx.prodDate}\nTested Date: ${ctx.testDate}\n\n1. There were 24 specimens tested, and ${failCount} failed\n   A = ${aCount}\n   B = ${bCount}\n2. Daily Bonding Failure Percentage is ${failCount > 0 ? '4%' : '0%'}\n3. The ${failCount > 0 ? 'failed specimens was brought to assembly this morning' : 'all tested specimens passed inspection'}.`;

        case 'custom':
        default:
          return `${ctx.res} - ${ctx.style} (${ctx.color})\nLab Ref: ${ctx.labRef}\nTested Date: ${ctx.testDate}`;
      }
    };

    // Canvas Snapshot Generator for Bonding Test Result Card (matching media_1794041382622_6ebb5244.png)
    // Whole Shoe / Material: a plain result card (header, details, result stamp)
    const generateSimpleCardImage = (ctx) => {
      const canvas = document.createElement('canvas');
      canvas.width = 820; canvas.height = 340;
      const g = canvas.getContext('2d');
      g.fillStyle = '#ffffff'; g.fillRect(0, 0, 820, 340);
      g.fillStyle = '#111827'; g.textAlign = 'center';
      g.font = 'bold 22px Arial, sans-serif'; g.fillText('VANS', 410, 34);
      g.font = 'bold 18px Arial, sans-serif'; g.fillText(ctx.title + ' Result', 410, 60);
      g.textAlign = 'left';
      const rows = [['Lab Ref No', ctx.labRef], ['Style / Color', [ctx.style, ctx.color].filter(Boolean).join(' / ')], ['Date', ctx.testDate], ['Tested by', ctx.tech]];
      g.strokeStyle = '#d1d5db'; g.strokeRect(30, 80, 760, rows.length * 34 + 8);
      rows.forEach((r, i) => {
        g.fillStyle = '#374151'; g.font = 'bold 13px Arial, sans-serif'; g.fillText(r[0], 44, 106 + i * 34);
        g.fillStyle = '#111827'; g.font = '13px Arial, sans-serif'; g.fillText(String(r[1] || '-').slice(0, 70), 190, 106 + i * 34);
      });
      const col = ctx.res === 'PASS' ? '#16a34a' : (ctx.res === 'TOLERABLE' ? '#2563eb' : (ctx.res === 'FAIL' ? '#dc2626' : '#6b7280'));
      g.save(); g.translate(690, 290); g.rotate(-0.08);
      g.strokeStyle = col; g.lineWidth = 2.5; g.strokeRect(-65, -22, 130, 44);
      g.fillStyle = col; g.textAlign = 'center'; g.font = 'bold 16px Arial, sans-serif'; g.fillText(ctx.res, 0, -2);
      g.font = 'bold 9px Arial, sans-serif'; g.fillText('LABORATORY TESTED', 0, 13);
      g.restore();
      return canvas.toDataURL('image/png');
    };
    // Material Test Report: the attachment is the report itself, as the lab prints it (its report form filled with THIS Lab Ref's values, made on the
    // server like the Bonding report card), not a plain card. Page 1 is the report image; further pages are added as pictures. Falls back to the simple card.
    const upgradeToMaterialReport = async (ctx) => {
      const slug = ($('#dtr-mat-form-slug')?.value || '').trim();
      const info = $('#email-attach-summary');
      try {
        const r = await fetch(BASE_URL + 'dtr/material-report-image?slug=' + encodeURIComponent(slug) + '&lab_ref=' + encodeURIComponent(ctx.labRef), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const j = await r.json().catch(() => ({}));
        if (!r.ok || !j.ok || !j.pages || !j.pages.length) throw new Error(j.error || 'no report pages');
        if (($('#mf_LabRefNo')?.value || '').trim() !== ctx.labRef) return;      // another record was opened meanwhile
        const safe = ctx.labRef.replace(/[^A-Za-z0-9_-]/g, '_');
        synoAttachment = { file: null, base64: j.pages[0], fileName: `${safe}_report.png`, fileSize: `${j.pages.length} page report` };
        synoPhotos = synoPhotos.filter((p) => !String(p.fileName).startsWith(`${safe}_report_p`));
        j.pages.slice(1).forEach((pg, i) => { if (synoPhotos.length < MAX_PHOTOS) synoPhotos.push({ file: null, base64: pg, fileName: `${safe}_report_p${i + 2}.png`, fileSize: 'report page ' + (i + 2) }); });
        updateSynoPreview();
        updatePhotoPreview();
      } catch (e) {
        (window.nxToast || showToast)('The full report page could not be made (' + (e.message || 'error') + '): the result card is attached instead.', 'warning');
      }
    };
    const generateBondingCardImage = (ctx) => {
      if (ctx.kind && ctx.kind !== 'bonding') return generateSimpleCardImage(ctx);
      const canvas = document.createElement('canvas');
      canvas.width = 820;
      canvas.height = 540;
      const ctx2d = canvas.getContext('2d');

      // White background
      ctx2d.fillStyle = '#ffffff';
      ctx2d.fillRect(0, 0, canvas.width, canvas.height);

      // Header: VANS / Bonding Test Result
      ctx2d.fillStyle = '#111827';
      ctx2d.font = 'bold 22px Arial, sans-serif';
      ctx2d.textAlign = 'center';
      ctx2d.fillText('VANS', canvas.width / 2, 34);

      ctx2d.font = 'bold 18px Arial, sans-serif';
      ctx2d.fillText('Bonding Test Result', canvas.width / 2, 58);

      ctx2d.font = '12px Arial, sans-serif';
      ctx2d.textAlign = 'left';
      ctx2d.fillText('Equip Code: DTP-QC-048', 30, 80);

      // Info Table Header & Values
      const tableY = 92;
      const colW = [70, 75, 120, 240, 140, 60, 55];
      const headers = ['Test Date', 'Test Speed', 'Test Report', 'Style / Color Way', 'Test No. MTL#', 'Width', 'Result'];
      let curX = 30;

      ctx2d.fillStyle = '#f3f4f6';
      ctx2d.fillRect(30, tableY, 760, 22);
      ctx2d.strokeStyle = '#d1d5db';
      ctx2d.strokeRect(30, tableY, 760, 48);

      ctx2d.fillStyle = '#374151';
      ctx2d.font = 'bold 11px Arial, sans-serif';
      headers.forEach((h, i) => {
        ctx2d.fillText(h, curX + 4, tableY + 15);
        curX += colW[i];
      });

      // Info Table Row
      curX = 30;
      ctx2d.font = '11px Arial, sans-serif';
      const vals = [
        ctx.testDate,
        '100',
        ctx.labRef || 'DTP2026-BOND',
        (ctx.style + ' ' + ctx.color).slice(0, 36),
        '1',
        '10.00',
        ctx.res
      ];
      vals.forEach((v, i) => {
        ctx2d.fillText(v, curX + 4, tableY + 38);
        curX += colW[i];
      });

      // Spot values mini grid (UTF / FTO sample points)
      const gridY = 154;
      ctx2d.fillStyle = '#f9fafb';
      ctx2d.fillRect(30, gridY, 760, 52);
      ctx2d.strokeRect(30, gridY, 760, 52);

      ctx2d.fillStyle = '#1f2937';
      ctx2d.font = 'bold 10px Arial, sans-serif';
      ctx2d.fillText('Report No', 34, gridY + 14);
      ctx2d.fillText('Upper To Foxing', 34, gridY + 30);
      ctx2d.fillText('Foxing To Outsole', 34, gridY + 46);

      // Spots S01..S14
      let ptX = 135;
      const numPts = 14;
      for (let p = 1; p <= numPts; p++) {
        ctx2d.font = '9px Arial, sans-serif';
        ctx2d.fillStyle = '#6b7280';
        ctx2d.fillText(`S${p < 10 ? '0' + p : p}`, ptX, gridY + 14);

        const utfEl = $(`#bp_UTFpoint${p}`);
        const ftoEl = $(`#bp_FTOpoint${p}`);
        const utfVal = utfEl ? parseFloat(utfEl.value) : (3.1 + Math.sin(p) * 0.4);
        const ftoVal = ftoEl ? parseFloat(ftoEl.value) : (2.7 + Math.cos(p) * 0.3);

        ctx2d.fillStyle = '#111827';
        ctx2d.fillText((Number.isFinite(utfVal) ? utfVal.toFixed(2) : '-'), ptX, gridY + 30);
        ctx2d.fillText((Number.isFinite(ftoVal) ? ftoVal.toFixed(2) : '-'), ptX, gridY + 46);
        ptX += 44;
      }

      // Graph Chart Box
      const chartX = 50;
      const chartY = 225;
      const chartW = 720;
      const chartH = 220;

      ctx2d.strokeStyle = '#9ca3af';
      ctx2d.lineWidth = 1;
      ctx2d.strokeRect(chartX, chartY, chartW, chartH);

      // Grid lines
      ctx2d.strokeStyle = '#e5e7eb';
      for (let y = chartY + 25; y < chartY + chartH; y += 30) {
        ctx2d.beginPath();
        ctx2d.moveTo(chartX, y);
        ctx2d.lineTo(chartX + chartW, y);
        ctx2d.stroke();
      }
      for (let x = chartX + 60; x < chartX + chartW; x += 60) {
        ctx2d.beginPath();
        ctx2d.moveTo(x, chartY);
        ctx2d.lineTo(x, chartY + chartH);
        ctx2d.stroke();
      }

      // Y-axis labels (kgf/cm)
      ctx2d.fillStyle = '#4b5563';
      ctx2d.font = '9px Arial, sans-serif';
      ctx2d.fillText('kgf/cm', chartX - 35, chartY + 12);
      ['6.2', '5.0', '4.1', '3.0 (STD)', '2.1', '1.4', '0.7'].forEach((val, i) => {
        ctx2d.fillText(val, chartX - 44, chartY + 25 + i * 30);
      });

      // STD reference line (3.0 kgf/cm)
      const stdY = chartY + 115;
      ctx2d.strokeStyle = '#f59e0b';
      ctx2d.setLineDash([4, 4]);
      ctx2d.beginPath();
      ctx2d.moveTo(chartX, stdY);
      ctx2d.lineTo(chartX + chartW, stdY);
      ctx2d.stroke();
      ctx2d.setLineDash([]);

      // Upper To Foxing Line (Red curve)
      ctx2d.strokeStyle = '#dc2626';
      ctx2d.lineWidth = 1.8;
      ctx2d.beginPath();
      for (let i = 0; i < chartW; i += 8) {
        const val = 110 + Math.sin(i * 0.05) * 22 + Math.cos(i * 0.12) * 15;
        if (i === 0) ctx2d.moveTo(chartX + i, chartY + val);
        else ctx2d.lineTo(chartX + i, chartY + val);
      }
      ctx2d.stroke();

      // Foxing To Outsole Line (Green curve)
      ctx2d.strokeStyle = '#16a34a';
      ctx2d.lineWidth = 1.6;
      ctx2d.beginPath();
      for (let i = 0; i < chartW; i += 8) {
        const val = 135 + Math.cos(i * 0.06) * 18 + Math.sin(i * 0.09) * 12;
        if (i === 0) ctx2d.moveTo(chartX + i, chartY + val);
        else ctx2d.lineTo(chartX + i, chartY + val);
      }
      ctx2d.stroke();

      // Possible cause and Stamp footer
      const footY = chartY + chartH + 24;
      ctx2d.fillStyle = '#0284c7';
      ctx2d.font = 'bold 11px Arial, sans-serif';
      ctx2d.fillText('POSSIBLE CAUSE:', chartX, footY);
      ctx2d.fillStyle = '#1e293b';
      ctx2d.font = '11px Arial, sans-serif';
      ctx2d.fillText(ctx.cause || 'Upper to Foxing / Foxing to Outsole - Normal', chartX + 110, footY);

      // Result Stamp in bottom-right corner (TOLERABLE / PASS / FAILED)
      const stampX = canvas.width - 130;
      const stampY = footY - 10;
      ctx2d.save();
      ctx2d.translate(stampX, stampY);
      ctx2d.rotate(-0.08);

      const isPass = ctx.res === 'PASS';
      const isTol = ctx.res === 'TOLERABLE';
      const stampColor = isPass ? '#16a34a' : (isTol ? '#2563eb' : '#dc2626');

      ctx2d.strokeStyle = stampColor;
      ctx2d.lineWidth = 2.5;
      ctx2d.strokeRect(-65, -20, 130, 40);

      ctx2d.fillStyle = stampColor;
      ctx2d.font = 'bold 15px Arial, sans-serif';
      ctx2d.textAlign = 'center';
      ctx2d.fillText(ctx.res, 0, -2);
      ctx2d.font = 'bold 9px Arial, sans-serif';
      ctx2d.fillText('LABORATORY TESTED', 0, 12);
      ctx2d.restore();

      return canvas.toDataURL('image/png');
    };

    // Open Synology Modal
    $$('.js-open-synology').forEach((b) => b.addEventListener('click', () => {
      closeSendMenus();
      const ctx = getBondingContext();
      if (!ctx.labRef) { (window.nxToast || showToast)('Open a report first: the Lab Ref No is empty.', 'warning'); return; }

      // Reset / Init attachment
      const snapshotBase64 = generateBondingCardImage(ctx);
      const cleanStyle = ctx.style.replace(/[^A-Za-z0-9_-]/g, '_');
      const cleanColor = ctx.color.replace(/[^A-Za-z0-9_-]/g, '_');
      const filename = `FA_${ctx.line}_${ctx.res}_${cleanStyle}_${cleanColor}.png`;

      synoAttachment = {
        file: null,
        base64: snapshotBase64,
        fileName: filename,
        fileSize: '~115 KB (Generated Snapshot)'
      };
      keepPhotoFor(ctx.labRef);
      updateSynoPreview();
      updatePhotoPreview();
      if (ctx.kind === 'material') upgradeToMaterialReport(ctx);

      // Pre-select template
      let defaultTpl = ctx.kind === 'bonding' ? 'first-article' : 'custom';
      if (ctx.res === 'FAIL' && ctx.kind === 'bonding') {
        defaultTpl = 'failure-alert';
      }
      $$('.syno-tpl-pill').forEach((btn) => {
        btn.classList.toggle('is-active', btn.dataset.tpl === defaultTpl);
      });

      const msgInput = $('#syno-message-input');
      if (msgInput) {
        msgInput.value = buildMessage(defaultTpl, ctx);
      }

      const st = $('#syno-status-text');
      if (st) st.textContent = 'Ready to send';
      synoModal?.classList.remove('hidden');
    }));

    // Template pills click
    $$('.syno-tpl-pill').forEach((btn) => {
      btn.addEventListener('click', () => {
        $$('.syno-tpl-pill').forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        const ctx = getBondingContext();
        const msgInput = $('#syno-message-input');
        if (msgInput) {
          msgInput.value = buildMessage(btn.dataset.tpl, ctx);
        }
      });
    });

    // Quick tag insert pills
    $$('.syno-tag-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const token = btn.dataset.insert;
        const msgInput = $('#syno-message-input');
        if (!msgInput || !token) return;

        const ctx = getBondingContext();
        const map = {
          '{RESULT}': ctx.res,
          '{STYLE}': ctx.style,
          '{COLOR}': ctx.color,
          '{LINE}': ctx.line,
          '{PROD_DATE}': ctx.prodDate,
          '{TEST_DATE}': ctx.testDate,
          '{DEFECT}': ctx.cause,
          '{CEMENT}': ctx.cement,
          '{TESTED_BY}': ctx.tech,
          '{CHECKED_BY}': ctx.checkedBy
        };

        const replacement = map[token] || token;
        const start = msgInput.selectionStart ?? msgInput.value.length;
        const end = msgInput.selectionEnd ?? msgInput.value.length;
        const val = msgInput.value;
        msgInput.value = val.substring(0, start) + replacement + val.substring(end);
        msgInput.focus();
        msgInput.selectionStart = msgInput.selectionEnd = start + replacement.length;
      });
    });

    // File upload handler: adds to the list (never replaces it), up to 10
    const addPhoto = (file) => new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (re) => {
        synoPhotos.push({ file, base64: re.target.result, fileName: file.name, fileSize: (file.size / 1024).toFixed(1) + ' KB' });
        resolve(true);
      };
      reader.onerror = () => resolve(false);
      reader.readAsDataURL(file);
    });
    const addPhotos = async (files) => {
      const list = Array.from(files || []).filter((f) => f && /^image\//.test(f.type || 'image/'));
      if (!list.length) return;
      photoLab = window.dtrReportLabRef();
      const room = MAX_PHOTOS - synoPhotos.length;
      if (room <= 0) { if (window.nxToast) nxToast(`You can attach up to ${MAX_PHOTOS} photos. Remove one to add another.`, 'warning'); return; }
      if (list.length > room && window.nxToast) nxToast(`Only ${room} more photo${room > 1 ? 's' : ''} fit (max ${MAX_PHOTOS}). The rest were skipped.`, 'warning');
      for (const f of list.slice(0, room)) await addPhoto(f);
      updatePhotoPreview();
    };
    const onPick = (e) => { addPhotos(e.target.files); e.target.value = ''; };
    $('#syno-file-upload')?.addEventListener('change', onPick);
    $('#email-file-upload')?.addEventListener('change', onPick);
    // a picture taken with the phone camera link (dtr-photo-attach.js) is added like an uploaded file, to the Synology message and the e-mail
    document.addEventListener('syno:attach', (e) => addPhotos(e.detail && e.detail.file ? [e.detail.file] : []));

    // Capture card snapshot button
    $('#btn-syno-capture-card')?.addEventListener('click', () => {
      const ctx = getBondingContext();
      if (ctx.kind === 'material') { upgradeToMaterialReport(ctx); return; }
      const base64 = generateBondingCardImage(ctx);
      synoAttachment = {
        file: null,
        base64: base64,
        fileName: `FA_${ctx.line}_${ctx.res}_Snapshot.png`,
        fileSize: '~115 KB (Snapshot)'
      };
      updateSynoPreview();
    });

    // Remove attachment
    $('#btn-syno-remove-attach')?.addEventListener('click', () => {
      synoAttachment = { file: null, base64: null, fileName: '', fileSize: '' };
      updateSynoPreview();
    });
    const removePhoto = (e) => {
      const b = e.target.closest('[data-rmphoto]'); if (!b) return;
      synoPhotos.splice(Number(b.dataset.rmphoto), 1);
      updatePhotoPreview();
    };
    $('#syno-photo-list')?.addEventListener('click', removePhoto);

    // Close Synology Modal
    $('#btn-close-syno-modal')?.addEventListener('click', () => synoModal?.classList.add('hidden'));
    $('#btn-close-syno-cancel')?.addEventListener('click', () => synoModal?.classList.add('hidden'));

    // Submit Send to Synology Chat
    $('#btn-send-synology-submit')?.addEventListener('click', async () => {
      const text = ($('#syno-message-input')?.value || '').trim();
      if (!text) {
        if (window.nxToast) nxToast('Please enter message content to send.', 'warning');
        return;
      }

      const sendBtnSubmit = $('#btn-send-synology-submit');
      const statusEl = $('#syno-status-text');
      const origText = sendBtnSubmit.innerHTML;

      try {
        sendBtnSubmit.disabled = true;
        sendBtnSubmit.innerHTML = '<span class="dtr-spinner"></span> Sending to Synology...';
        if (statusEl) statusEl.textContent = 'Contacting Synology Chat Webhook...';

        const csrfToken = document.querySelector('meta[name="X-CSRF-TOKEN"]')?.content || '';
        // Synology Chat takes one picture per message: the text goes with the report graph card, and each photo follows as its own message
        const post = async (body) => {
          const res = await fetch(BASE_URL + 'dtr/send-synology', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken }, body });
          const json = await res.json();
          if (!res.ok || !json.ok) throw new Error(json.error || 'Failed to dispatch message to Synology Chat.');
        };
        const hasCard = !!(synoAttachment.file || synoAttachment.base64);
        const photos = synoPhotos.slice(0, MAX_PHOTOS);
        const f1 = new FormData();
        f1.append('text', text);
        let rest = photos;
        if (hasCard) {
          if (synoAttachment.file) f1.append('image', synoAttachment.file);
          else { f1.append('image_base64', synoAttachment.base64); f1.append('file_name', synoAttachment.fileName || 'Bonding_Report.png'); }
        } else if (photos.length) {   // no graph card: the first photo goes with the text
          f1.append('image', await toFile(photos[0], 'photo1.jpg'));
          rest = photos.slice(1);
        }
        await post(f1);
        const ctx2 = getBondingContext();
        for (let i = 0; i < rest.length; i++) {
          if (statusEl) statusEl.textContent = `Sending photo ${i + 1} of ${rest.length}...`;
          const f2 = new FormData();
          f2.append('text', `Photo ${hasCard ? i + 1 : i + 2}/${photos.length} - ${ctx2.labRef || 'report'}`);
          f2.append('image', await toFile(rest[i], `photo${i + 1}.jpg`));
          await post(f2);
        }

        synoModal?.classList.add('hidden');
        if (window.nxToast) {
          nxToast('Report successfully sent to Synology Chat!', 'success', 5000);
        } else {
          showToast('Report successfully sent to Synology Chat!');
        }
      } catch (err) {
        console.error(err);
        if (statusEl) statusEl.textContent = 'Error: ' + err.message;
        if (window.nxToast) {
          nxToast(err.message, 'error', 6000);
        } else {
          showToast(err.message);
        }
      } finally {
        sendBtnSubmit.disabled = false;
        sendBtnSubmit.innerHTML = origText;
      }
    });

    // Email Modal handlers
    $$('.js-open-email').forEach((b) => b.addEventListener('click', () => {
      closeSendMenus();
      const ctx = getBondingContext();
      if (!ctx.labRef) { (window.nxToast || showToast)('Open a report first: the Lab Ref No is empty.', 'warning'); return; }
      const subjectInput = $('#email-subject-input');
      const msgInput = $('#email-message-input');
      const summaryEl = $('#email-attach-summary');

      if (subjectInput) {
        subjectInput.value = `[${ctx.res}] ${ctx.title} - ${ctx.labRef} (${ctx.style})`;
      }
      if (msgInput) {
        msgInput.value = `Dear Team,\n\nPlease see the ${ctx.title.toLowerCase()} results below:\n\nLab Ref No: ${ctx.labRef}\nStyle: ${ctx.style}\nColor: ${ctx.color}\nResult: ${ctx.res}\nProduction Date: ${ctx.prodDate}\nTested Date: ${ctx.testDate}\nPossible Cause: ${ctx.cause}\nTechnician: ${ctx.tech}\n\nBest regards,\nNEXUS-L QA Laboratory`;
      }
      keepPhotoFor(ctx.labRef);
      if (!(synoAttachment.file || synoAttachment.base64)) {   // not opened through Synology first: make the graph card now
        const cleanStyle = ctx.style.replace(/[^A-Za-z0-9_-]/g, '_'); const cleanColor = ctx.color.replace(/[^A-Za-z0-9_-]/g, '_');
        synoAttachment = { file: null, base64: generateBondingCardImage(ctx), fileName: `FA_${ctx.line}_${ctx.res}_${cleanStyle}_${cleanColor}.png`, fileSize: '~115 KB (Generated Snapshot)' };
        updateSynoPreview();
      }
      renderEmailAttach();
      if (ctx.kind === 'material') upgradeToMaterialReport(ctx);

      emailModal?.classList.remove('hidden');
    }));

    $('#btn-email-capture-card')?.addEventListener('click', () => $('#btn-syno-capture-card')?.click());
    $('#email-attach-list')?.addEventListener('click', (e) => {
      removePhoto(e);
      const b = e.target.closest('[data-rm]'); if (!b) return;
      if (b.dataset.rm === 'card') $('#btn-syno-remove-attach')?.click();
    });
    $('#btn-close-email-modal')?.addEventListener('click', () => emailModal?.classList.add('hidden'));
    $('#btn-close-email-cancel')?.addEventListener('click', () => emailModal?.classList.add('hidden'));

    $('#btn-send-email-submit')?.addEventListener('click', async () => {
      const to = ($('#email-to-input')?.value || '').trim();
      const subject = ($('#email-subject-input')?.value || '').trim();
      const message = ($('#email-message-input')?.value || '').trim();

      if (!to) {
        if (window.nxToast) nxToast('Please enter a recipient email address.', 'warning');
        return;
      }

      const emailSubmitBtn = $('#btn-send-email-submit');
      const origText = emailSubmitBtn.innerHTML;

      try {
        emailSubmitBtn.disabled = true;
        emailSubmitBtn.innerHTML = '<span class="dtr-spinner"></span> Sending...';

        const formData = new FormData();
        formData.append('to', to);
        formData.append('subject', subject);
        formData.append('message', message);
        // the report graph card (image) and up to 10 photos (photos[]) go with the e-mail
        const cardFile = await toFile(synoAttachment, 'Report_Graph_Card.png');
        if (cardFile) formData.append('image', cardFile);
        for (let i = 0; i < Math.min(synoPhotos.length, MAX_PHOTOS); i++) formData.append('photos[]', await toFile(synoPhotos[i], `photo${i + 1}.jpg`));

        const csrfToken = document.querySelector('meta[name="X-CSRF-TOKEN"]')?.content || '';
        const res = await fetch(BASE_URL + 'dtr/send-email', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
          },
          body: formData
        });

        const json = await res.json();
        if (!res.ok || !json.ok) {
          throw new Error(json.error || 'Failed to send email.');
        }

        emailModal?.classList.add('hidden');
        if (window.nxToast) {
          nxToast(json.message || 'Email sent successfully!', 'success');
        } else {
          showToast('Email sent successfully!');
        }
      } catch (err) {
        console.error(err);
        if (window.nxToast) {
          nxToast(err.message, 'error');
        } else {
          showToast(err.message);
        }
      } finally {
        emailSubmitBtn.disabled = false;
        emailSubmitBtn.innerHTML = origText;
      }
    });

  })();

})();
