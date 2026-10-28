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

  // =========================================================================
  // 1. TAB SWITCHING
  // =========================================================================
  const switchTab = (tabName) => {
    $$('[data-dtr-tab]').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.dtrTab === tabName);
    });
    ['tracker', 'material', 'bonding', 'calculators'].forEach((t) => {
      $(`#dtr-panel-${t}`)?.classList.toggle('hidden', t !== tabName);
    });
  };

  $$('[data-dtr-tab]').forEach((btn) => {
    btn.addEventListener('click', () => switchTab(btn.dataset.dtrTab));
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
  const loadTrackerRows = async (slug = 'all', q = '') => {
    const tbody = $('#dtr-tracker-tbody');
    try {
      if (tbody && window.nxState) nxState(tbody, 'loading', 'Loading lab reports…', 16);
      const res = await apiFetch(`dtr/records?slug=${encodeURIComponent(slug)}&q=${encodeURIComponent(q)}`);
      if (!tbody || !res.rows) return;
      if (!res.rows.length) {
        nxState(tbody, 'empty', 'No lab reports match your search. Clear the filters or try another Lab Ref No.', 16);
        return;
      }
      tbody.innerHTML = res.rows.map((r) => {
        const resUpper = String(r.overall_result || 'PASS').toUpperCase();
        const badgeCls = resUpper === 'FAIL' ? 'b-err' : (resUpper === 'TOLERABLE' ? 'b-warn' : 'b-ok');
        return `
          <tr>
            <td style="white-space:nowrap">
              <button type="button" class="dtr-btn-xs dtr-btn-primary" data-open-record="${escHtml(r.form_slug)}" data-source-id="${escHtml(r.source_id)}" data-labref="${escHtml(r.lab_ref_no)}">
                Open Form
              </button>
            </td>
            <td class="mono"><b>${escHtml(r.lab_ref_no)}</b></td>
            <td class="mono">${escHtml(r.tr_no || '-')}</td>
            <td><span class="dtr-tag">${escHtml(r.form_category)}</span></td>
            <td>${escHtml(r.material_type || '-')}</td>
            <td class="mono">${escHtml(r.material_code || '-')}</td>
            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(r.material_description || '')}">${escHtml(r.material_description || '-')}</td>
            <td>${escHtml(r.supplier || '-')}</td>
            <td class="mono">${escHtml(r.model_style || '-')}</td>
            <td>${escHtml(r.color || '-')}</td>
            <td class="mono">${escHtml(r.po_lot_no || '-')}</td>
            <td class="mono">${escHtml(r.mtl_no || '-')}</td>
            <td>${escHtml(r.tested_date || r.received_date || '-')}</td>
            <td>${escHtml(r.technician || '-')}</td>
            <td>${escHtml(r.status || 'Complete')}</td>
            <td><span class="badge ${badgeCls}">${escHtml(resUpper)}</span></td>
          </tr>
        `;
      }).join('');
    } catch (err) {
      console.error(err);
      if (tbody && window.nxState) nxState(tbody, 'error', 'The list could not be loaded. Check your connection and try again.', 16);
    }
  };

  $('#dtr-tracker-pills')?.addEventListener('click', (e) => {
    const pill = e.target.closest('[data-slug]');
    if (!pill) return;
    $$('#dtr-tracker-pills .dtr-pill').forEach((p) => p.classList.remove('active'));
    pill.classList.add('active');
    const q = $('#dtr-tracker-search')?.value.trim() || '';
    loadTrackerRows(pill.dataset.slug, q);
  });

  $('#dtr-tracker-btn-search')?.addEventListener('click', () => {
    const activePill = $('#dtr-tracker-pills .dtr-pill.active');
    const slug = activePill ? activePill.dataset.slug : 'all';
    const q = $('#dtr-tracker-search')?.value.trim() || '';
    loadTrackerRows(slug, q);
  });

  $('#dtr-tracker-search')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      $('#dtr-tracker-btn-search')?.click();
    }
  });

  // Open a clicked record in Material Form or Bonding Form
  $('#dtr-tracker-tbody')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-open-record]');
    if (!btn) return;
    const slug = btn.dataset.openRecord;
    const sourceId = btn.dataset.sourceId;
    if (slug === 'bonding') {
      switchTab('bonding');
      await loadBondingRecordList(sourceId);
      await loadSingleBondingRecord(sourceId);
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
          const el = $(`#mf_${ctrlName}`) || $(`[name="${ctrlName}"]`, $('#dtr-material-form'));
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
      sel.innerHTML = res.rows.map((r) => `
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
    await loadMaterialRecordList(activeSlug);
    await loadSingleMaterialRecord(activeSlug, 0);
  });

  // Switch Record inside Material Form
  $('#dtr-mat-record-select')?.addEventListener('change', async (e) => {
    const id = e.target.value;
    if (id) await loadSingleMaterialRecord(activeSlug, id);
  });

  // + New Record
  $('#btn-dtr-new')?.addEventListener('click', () => {
    const today = new Date().toISOString().slice(0, 10);
    populateMaterialForm({
      ID: '',
      LabRefNo: `DTP${today.replace(/-/g, '')}${activeSlug.toUpperCase().slice(0, 3)}-1`,
      TR: `TR${today.replace(/-/g, '')}001`,
      ReceivedDate: today,
      TestedDate: today,
      FinishedDate: today,
      Status: 'Complete',
      Remarks: 'PASS',
    });
    showToast('Initialized new blank test report record.');
  });

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
          const el = $(`#mf_${k}`) || $(`[name="${CSS.escape(k)}"]`, $('#dtr-material-form'));
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

    try {
      const res = await apiFetch('dtr/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ slug: activeSlug, fields }),
      });
      if (res && res.ok) {
        if ($('#mf_ID')) $('#mf_ID').value = res.recordId;
        showToast(`✓ ${res.message} (Ready in InterLink Sync ComboBox!)`);
        await loadMaterialRecordList(activeSlug, res.recordId);
      } else {
        showToast(res.error || 'Save failed.', true);
      }
    } catch (err) {
      showToast('Failed to save record.', true);
    }
  });

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

  const computeBondingPoints = () => {
    const ageCat = ($('#bf_AgeCategory')?.value || 'ADULT').toUpperCase();
    const maxSpots = AGE_SPOT_LIMITS[ageCat] || 35;
    const stdVal = parseFloat($('#bf_STD')?.value ?? '2.5') || 2.5;

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

    const allRes = [utf.res, fto.res, fito.res].filter(Boolean);
    let overall = 'PASS';
    if (allRes.includes('FAIL')) overall = 'FAIL';
    else if (allRes.includes('TOLERABLE')) overall = 'TOLERABLE';

    if ($('#bf_Result')) {
      $('#bf_Result').value = overall;
      styleRatingInput($('#bf_Result'));
    }
    const badge = $('#bf-overall-badge');
    if (badge) {
      badge.textContent = overall;
      badge.className = 'badge ' + (overall === 'FAIL' ? 'b-err' : (overall === 'TOLERABLE' ? 'b-warn' : 'b-ok'));
    }
  };

  const loadBondingRecordList = async (selectedId = null) => {
    const sel = $('#dtr-bonding-record-select');
    try {
      if (sel) sel.innerHTML = '<option value="">Loading reports…</option>';
      const res = await apiFetch('dtr/records?slug=bonding');
      if (!sel || !res.rows) return;
      if (!res.rows.length) { sel.innerHTML = '<option value="">No bonding reports yet</option>'; return; }
      sel.innerHTML = res.rows.map((r) => `
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

  const loadSingleBondingRecord = async (sourceId = 0) => {
    try {
      const res = await apiFetch(`dtr/record?slug=bonding&source_id=${encodeURIComponent(sourceId)}`);
      if (!res || !res.ok) return;
      const rec = res.record || {};
      const bp = res.bondingPoints || {};
      activeBondingPoints = bp;

      Object.keys(rec).forEach((k) => {
        const el = $(`#bf_${k}`);
        if (el) el.value = rec[k] ?? '';
      });
      $$('#dtr-bonding-form [name^="cf_"]').forEach((el) => { el.value = (res.customValues || {})[el.name] ?? ''; });

      for (let i = 1; i <= 35; i++) {
        if ($(`#bp_UTFpoint${i}`)) $(`#bp_UTFpoint${i}`).value = bp[`UTFpoint${i}`] ?? '';
        if ($(`#bp_FTOpoint${i}`)) $(`#bp_FTOpoint${i}`).value = bp[`FTOpoint${i}`] ?? '';
        if (i <= 13 && $(`#bp_FiTO${i}`)) $(`#bp_FiTO${i}`).value = bp[`FiTO${i}`] ?? '';
      }

      computeBondingPoints();
    } catch (err) {
      console.error(err);
    }
  };

  $('#dtr-bonding-record-select')?.addEventListener('change', (e) => {
    if (e.target.value) loadSingleBondingRecord(e.target.value);
  });
  $('#bf_AgeCategory')?.addEventListener('change', computeBondingPoints);
  $('#bf_STD')?.addEventListener('change', computeBondingPoints);
  $$('#dtr-bonding-points-tbody .dtr-bp-inp').forEach((inp) => {
    inp.addEventListener('input', computeBondingPoints);
  });
  $('#btn-bond-recalc')?.addEventListener('click', () => {
    computeBondingPoints();
    showToast('✓ Recalculated UTF, FTO, and FiTO Min/Max/Ave & Spot Tolerances.');
  });

  $('#btn-bond-new')?.addEventListener('click', () => {
    const today = new Date().toISOString().slice(0, 10);
    $$('#dtr-bonding-form input').forEach((i) => { i.value = ''; });
    if ($('#bf_LabRefNo')) $('#bf_LabRefNo').value = `DTP${today.replace(/-/g, '')}SHB01-R`;
    if ($('#bf_AgeCategory')) $('#bf_AgeCategory').value = 'ADULT';
    if ($('#bf_STD')) $('#bf_STD').value = '2.5';
    if ($('#bf_ReceivedDate')) $('#bf_ReceivedDate').value = today;
    if ($('#bf_TestedDate')) $('#bf_TestedDate').value = today;
    if ($('#bf_FinishedDate')) $('#bf_FinishedDate').value = today;
    computeBondingPoints();
    showToast('Initialized new Whole Shoe Bonding Test Report.');
  });

  $('#btn-bond-load-u60')?.addEventListener('click', async () => {
    const labRefNo = $('#bf_LabRefNo')?.value.trim() || 'DTP20261024SHB11-R';
    try {
      const res = await apiFetch('dtr/load-machine', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ slug: 'bonding', lab_ref_no: labRefNo }),
      });
      if (res && res.loadedFields) {
        Object.keys(res.loadedFields).forEach((k) => {
          const el = $(`#bp_${k}`);
          if (el) el.value = res.loadedFields[k];
        });
        computeBondingPoints();
        showToast(`${res.message}`);
      }
    } catch (err) {
      showToast('Failed to load U60 machine file.', true);
    }
  });

  $('#btn-bond-save')?.addEventListener('click', async () => {
    const fields = {};
    $$('#dtr-bonding-form [name]').forEach((inp) => {
      if (inp.name) fields[inp.name] = inp.value;
    });
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
      Result: $('#bf_Result')?.value || 'PASS',
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
        const v = $('#mf_' + n)?.value;
        if (v !== undefined && String(v).trim() !== '') return String(v).trim();
        const r = rec[n];
        if (r !== undefined && r !== null && String(r).trim() !== '') return String(r).trim();
      }
      return '';
    };
    const fmtDate = (v) => String(v || '').replace(/ 00:00:00$/, '');
    const testedDate = fmtDate(pick('TestedDate', 'Tested Date')) || new Date().toISOString().slice(0, 10);
    const tech = pick('Technician', 'TestedBy');
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

  const openBondingPrintPreview = async () => {
    const bondingTestId = parseInt($('#bf_BondingTestID')?.value || activeBondingPoints?.BondingTestID || 0) || 0;
    const labRef = $('#bf_LabRefNo')?.value || 'DTP-BOND-PREVIEW';
    const etrf = $('#bf_ETRFNo')?.value || '-';
    const shoeName = $('#bf_ModelStyle')?.value || '-';
    const color = $('#bf_Color')?.value || '-';
    const side = $('#bf_Side')?.value || 'Left';
    const isLeft = side.toLowerCase() === 'left';
    const age = ($('#bf_AgeCategory')?.value || 'ADULT').toUpperCase();
    const std = parseFloat($('#bf_STD')?.value || '2.5') || 2.5;
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
    const overall = ($('#bf_Result')?.value || 'PASS').toUpperCase();

    const stampCls = overall === 'PASS' ? '' : (overall === 'TOLERABLE' ? 'stamp-tol' : 'stamp-fail');

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

    // Determine sketch config
    const configs = await loadSketchConfigs();
    let numPts = parseInt(activeBondingPoints?.MaxPoints || highestPoint || 27);
    if (!configs || !configs[numPts]) {
      const available = [26, 27, 28, 29, 30, 31, 32, 33, 34];
      numPts = available.reduce((prev, curr) => Math.abs(curr - numPts) < Math.abs(prev - numPts) ? curr : prev, 27);
    }
    let cfg = configs ? configs[numPts] : null;

    // Admin-designed layout (Report Designer): moved / hidden points and extra items
    let layoutExtras = [];
    let layoutVec = null;
    if (cfg) {
      try {
        const lj = await apiFetch('dtr/layout?slug=bonding&variant=' + encodeURIComponent(numPts));
        const L = lj && lj.layout;
        if (L) {
          cfg = JSON.parse(JSON.stringify(cfg));
          if (L.image) Object.assign(cfg.image, L.image);
          ['utf', 'fto'].forEach((k) => {
            Object.keys(L[k] || {}).forEach((i) => {
              if (L[k][i].hidden) delete cfg[k][i];
              else cfg[k][i] = L[k][i];
            });
          });
          layoutExtras = L.extras || [];
          if (L.vector || L.start) layoutVec = L;
          if (L.vector) cfg.image.file = L.imageFile || 'blank.png';
        }
      } catch (e) { /* default layout */ }
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

    // Active shoe overlay
    let activeShoePointsHtml = '';
    if (cfg) {
      activeShoePointsHtml += `
        <img src="${BASE_URL}assets/images/bonding_sketches/${cfg.image.file}" 
             class="vf-shoe-img" 
             style="left:${cfg.image.pctLeft}%; top:${cfg.image.pctTop}%; width:${cfg.image.pctWidth}%; height:${cfg.image.pctHeight}%;" 
             alt="${side} Shoe Sketch">
      `;
      for (let i = 1; i <= cfg.pointsCount; i++) {
        const uCoord = cfg.utf[i];
        if (uCoord) {
          const val = utfVals[i];
          const num = parseFloat(val);
          const hasVal = Number.isFinite(num) && num > 0;
          const colStyle = getValColorStyle(val, std, false);
          activeShoePointsHtml += `
            <div class="vf-point-box vf-point-utf" 
                 style="left:${uCoord.pctLeft}%; top:${uCoord.pctTop}%; width:${uCoord.pctWidth}%; height:${uCoord.pctHeight}%; ${colStyle}" 
                 title="UTF Point ${i}: ${hasVal ? num.toFixed(2) : '-'}">
              ${hasVal ? num.toFixed(2) : ''}
            </div>
          `;
        }
        const fCoord = cfg.fto[i];
        if (fCoord) {
          const val = ftoVals[i];
          const num = parseFloat(val);
          const hasVal = Number.isFinite(num) && num > 0;
          const colStyle = getValColorStyle(val, std, false);
          activeShoePointsHtml += `
            <div class="vf-point-box vf-point-fto" 
                 style="left:${fCoord.pctLeft}%; top:${fCoord.pctTop}%; width:${fCoord.pctWidth}%; height:${fCoord.pctHeight}%; ${colStyle}" 
                 title="FTO Point ${i}: ${hasVal ? num.toFixed(2) : '-'}">
              ${hasVal ? num.toFixed(2) : ''}
            </div>
          `;
        }
      }
      if (layoutVec) {
        let svg = '<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none">';
        let dots = '';
        if (layoutVec.vector) {
          ['utf', 'fto'].forEach((k) => Object.keys(cfg[k]).forEach((i) => {
            const b = cfg[k][i]; const d = ((layoutVec.dots || {})[k] || {})[i];
            if (!d) return;
            const r = b.pctLeft + b.pctWidth; const cy = b.pctTop + b.pctHeight / 2;
            const x = d.x < b.pctLeft ? b.pctLeft : d.x > r ? r : b.pctLeft + b.pctWidth / 2;
            const y = (x === b.pctLeft || x === r) ? cy : (d.y < b.pctTop ? b.pctTop : b.pctTop + b.pctHeight);
            const col = k === 'utf' ? '#2563eb' : '#7c3aed';
            svg += `<line x1="${x}" y1="${y}" x2="${d.x}" y2="${d.y}" stroke="${col}" stroke-width="1.2" vector-effect="non-scaling-stroke"/>`;
            dots += `<div style="position:absolute;left:${d.x}%;top:${d.y}%;width:7px;height:7px;margin:-3.5px 0 0 -3.5px;border-radius:50%;background:${col}"></div>`;
          }));
        }
        if (layoutVec.start) {
          const st = layoutVec.start;
          dots += `<div style="position:absolute;left:${st.x}%;top:${st.y}%;transform:translate(-50%,-50%);display:flex;align-items:center;gap:3px;white-space:nowrap;color:#b91c1c;font:800 9px sans-serif"><i style="display:block;width:9px;height:9px;background:#dc2626;transform:rotate(45deg)"></i>${escHtml(st.label || 'START')}</div>`;
        }
        activeShoePointsHtml += svg + '</svg>' + dots;
      }
      layoutExtras.forEach((e) => {
        const pos = `left:${e.pctLeft}%; top:${e.pctTop}%; width:${e.pctWidth}%; height:${e.pctHeight}%;`;
        const txt = `color:${escHtml(e.color || '#111')}; font-size:${Number(e.size) || 12}px; font-weight:${e.bold ? 700 : 400};`;
        let inner = '';
        if (e.type === 'text') inner = escHtml(e.text);
        else if (e.type === 'field') inner = escHtml($('#bf_' + e.field)?.value || '');
        else if (e.type === 'image' && e.src) inner = `<img src="${BASE_URL}${escHtml(e.src)}" style="max-width:100%;max-height:100%" alt="">`;
        else if (e.type === 'chart') {
          let max = 0;
          for (let i = 1; i <= cfg.pointsCount; i++) max = Math.max(max, parseFloat(utfVals[i]) || 0);
          let bars = '';
          for (let i = 1; i <= cfg.pointsCount; i++) {
            const v = parseFloat(utfVals[i]) || 0;
            bars += `<i style="flex:1;display:block;min-height:1px;height:${max ? (v / max) * 100 : 0}%;background:${v < 2 ? '#dc2626' : v < 3 ? '#d97706' : '#1d4ed8'}"></i>`;
          }
          inner = `<div style="display:flex;align-items:flex-end;gap:1px;width:100%;height:100%">${bars}</div>`;
        }
        const bg = e.type === 'line' ? `background:${escHtml(e.color || '#111')};` : '';
        activeShoePointsHtml += `<div style="position:absolute;display:flex;align-items:center;overflow:hidden;${pos}${txt}${bg}">${inner}</div>`;
      });
    } else {
      activeShoePointsHtml = `<div style="padding:20px;text-align:center;color:#64748b">Shoe sketch unavailable</div>`;
    }

    const blankShoeHtml = `
      <img src="${BASE_URL}assets/images/bonding_sketches/blank.png" 
           class="vf-shoe-img" 
           style="left:12%; top:8%; width:76%; height:84%; opacity:0.85;" 
           alt="Unmeasured Shoe Contour">
    `;

    // Center FiTO column items
    let fitoItemsHtml = '';
    for (let i = 1; i <= 13; i++) {
      const val = fitoVals[i];
      const num = parseFloat(val);
      const hasVal = Number.isFinite(num) && num > 0;
      const colStyle = getValColorStyle(val, 1.0, true);
      fitoItemsHtml += `
        <div class="vf-center-item" style="${colStyle}">
          ${hasVal ? num.toFixed(2) : '&nbsp;'}
        </div>
      `;
    }

    const leftShoeCardHtml = isLeft ? activeShoePointsHtml : blankShoeHtml;
    const rightShoeCardHtml = !isLeft ? activeShoePointsHtml : blankShoeHtml;
    const leftCenterHtml = isLeft ? fitoItemsHtml : '';
    const rightCenterHtml = !isLeft ? fitoItemsHtml : '';

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
    for (let i = 1; i <= (cfg ? cfg.pointsCount : 27); i++) {
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
                <span class="vf-radio ${materialSource === 'Prod. Shoes' || !materialSource ? 'checked' : ''}"></span> Pro. Shoes 量产鞋
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
            <svg class="ic" aria-hidden="true"><use href="#i-chart"/></svg> View Tabular Spot Values (${cfg ? cfg.pointsCount : 27} Points)
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

    // Show/Update Access PDF download button
    const pdfBtn = $('#btn-dtr-download-access-pdf');
    if (pdfBtn) {
      if (bondingTestId > 0) {
        pdfBtn.href = `${BASE_URL}dtr/bonding-access-pdf?id=${bondingTestId}&side=${encodeURIComponent(side)}`;
        pdfBtn.classList.remove('hidden');
      } else {
        pdfBtn.classList.add('hidden');
      }
    }

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

  $('#btn-dtr-print-preview')?.addEventListener('click', openMaterialPrintPreview);
  $('#btn-bond-print-preview')?.addEventListener('click', openBondingPrintPreview);
  $('#btn-dtr-close-print-modal')?.addEventListener('click', () => {
    $('#dtr-print-modal-backdrop')?.classList.add('hidden');
  });
  $('#btn-dtr-cancel-print')?.addEventListener('click', () => {
    $('#dtr-print-modal-backdrop')?.classList.add('hidden');
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
        body: JSON.stringify({ access_id: parseInt(($('#btn-dtr-download-access-pdf')?.href || '').match(/[?&]id=(\d+)/)?.[1] || 0, 10) && !$('#btn-dtr-download-access-pdf').classList.contains('hidden') ? parseInt($('#btn-dtr-download-access-pdf').href.match(/[?&]id=(\d+)/)[1], 10) : 0, side: (($('#btn-dtr-download-access-pdf')?.href || '').match(/side=(\w+)/) || [])[1] || 'Left', scale: fitOnePageScale(src.innerHTML), html: src.innerHTML, name: ($('#dtr-print-modal-title')?.textContent || 'Report').replace(/[^A-Za-z0-9._ -]+/g, '_') }),
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
    root.innerHTML = src.innerHTML;
    root.style.zoom = String(fitOnePageScale(src.innerHTML));
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
  loadMaterialRecordList('fabric');
  loadSingleMaterialRecord('fabric', 0);
  applyBondingFormLayout().then(() => { loadBondingRecordList(); loadSingleBondingRecord(0); });
  runStandardsExplorer();

  // deep link from the assistant: ?open=<slug>:<id>  (+ &preview=1 opens the print preview)
  (() => {
    const q = new URLSearchParams(location.search);
    const [slug, id] = String(q.get('open') || '').split(':');
    if (!slug || !/^[a-z]+$/.test(slug) || !/^\d+$/.test(id || '')) return;
    setTimeout(async () => {
      const host = $('#dtr-tracker-tbody') || document.body;
      const b = document.createElement('button');
      b.type = 'button'; b.hidden = true; b.dataset.openRecord = slug; b.dataset.sourceId = id;
      host.appendChild(b); b.click(); b.remove();
      if (q.get('preview') === '1') {
        await new Promise((r) => setTimeout(r, 2200));
        (slug === 'bonding' ? $('#btn-bond-print-preview') : $('#btn-dtr-print-preview'))?.click();
      }
      // &export=1 inside the InterLink sender's hidden frame: hand the finished report PDF (as base64) to the parent page
      if (q.get('export') === '1' && window.parent !== window) {
        const reply = (m) => window.parent.postMessage(Object.assign({ type: 'NXL_REPORT_PDF' }, m), location.origin);
        try {
          await new Promise((r) => setTimeout(r, 800));
          const src = $('#dtr-print-sheet-container');
          if (!src || !src.innerHTML.trim()) throw new Error('The report sheet could not be prepared.');
          const acc = $('#btn-dtr-download-access-pdf');
          const accId = acc && !acc.classList.contains('hidden') ? parseInt(((acc.href || '').match(/[?&]id=(\d+)/) || [])[1] || 0, 10) : 0;
          const res = await fetch(BASE_URL + 'dtr/sheet-pdf', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="X-CSRF-TOKEN"]')?.content || '' },
            body: JSON.stringify({ access_id: accId, side: ((acc?.href || '').match(/side=(\w+)/) || [])[1] || 'Left', scale: fitOnePageScale(src.innerHTML), html: src.innerHTML, name: 'Report' }),
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
})();
