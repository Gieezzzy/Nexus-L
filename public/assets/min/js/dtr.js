(()=>{"use strict";var ze,qe,Qe,Xe,Je,Ke,Ye,Ze,ta,ea,aa,sa,la,ra,oa,na,ia,ca,da,fa,pa,ua,ma,va,_a,ba,ha,ga,Ta,Sa,ya,Fa,La,$a,Ca,wa,Ra,Aa;const t=(e,a=document)=>a.querySelector(e),R=(e,a=document)=>[...a.querySelectorAll(e)],Ie=t('meta[name="base-url"]'),J=Ie?Ie.content.replace(/\/+$/,"")+"/":location.origin+"/";let ft="fabric",Ee={},v={};const w=(e,a=!1)=>{const s=t("#dtr-toast");s&&(s.textContent=e,s.style.background=a?"#dc2626":"#0f172a",s.classList.remove("hidden"),clearTimeout(s._timer),s._timer=setTimeout(()=>s.classList.add("hidden"),4200))},i=e=>String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"),N=async(e,a={})=>(await fetch(J+e.replace(/^\/+/,""),a)).json(),h=(e,a=2)=>{const s=Number(e);if(!Number.isFinite(s))return 0;const r=Math.pow(10,a);return Math.round((s+Number.EPSILON)*r)/r},E=e=>{if(!e)return;const a=String(e.value||"").trim().toUpperCase();e.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),a==="PASS"?e.classList.add("dtr-rate-pass"):a==="TOLERABLE"?e.classList.add("dtr-rate-warn"):(a==="FAIL"||a==="ERROR")&&e.classList.add("dtr-rate-fail")},pt=e=>{R("[data-dtr-tab]").forEach(a=>{a.classList.toggle("active",a.dataset.dtrTab===e)}),["tracker","material","bonding","calculators"].forEach(a=>{var s;(s=t(`#dtr-panel-${a}`))==null||s.classList.toggle("hidden",a!==e)})};R("[data-dtr-tab]").forEach(e=>{e.addEventListener("click",()=>pt(e.dataset.dtrTab))}),R("[data-goto-calc]").forEach(e=>{e.addEventListener("click",()=>{pt("calculators");const a=t(`#calc-card-${e.dataset.gotoCalc}`);a&&a.scrollIntoView({behavior:"smooth",block:"center"})})});const Ue=async(e="all",a="")=>{const s=t("#dtr-tracker-tbody");try{s&&window.nxState&&nxState(s,"loading","Loading lab reports…",16);const r=await N(`dtr/records?slug=${encodeURIComponent(e)}&q=${encodeURIComponent(a)}`);if(!s||!r.rows)return;if(!r.rows.length){nxState(s,"empty","No lab reports match your search. Clear the filters or try another Lab Ref No.",16);return}s.innerHTML=r.rows.map(l=>{const c=String(l.overall_result||"PASS").toUpperCase(),o=c==="FAIL"?"b-err":c==="TOLERABLE"?"b-warn":"b-ok";return`
          <tr>
            <td style="white-space:nowrap">
              <button type="button" class="dtr-btn-xs dtr-btn-primary" data-open-record="${i(l.form_slug)}" data-source-id="${i(l.source_id)}" data-labref="${i(l.lab_ref_no)}">
                Open Form
              </button>
            </td>
            <td class="mono"><b>${i(l.lab_ref_no)}</b></td>
            <td class="mono">${i(l.tr_no||"-")}</td>
            <td><span class="dtr-tag">${i(l.form_category)}</span></td>
            <td>${i(l.material_type||"-")}</td>
            <td class="mono">${i(l.material_code||"-")}</td>
            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${i(l.material_description||"")}">${i(l.material_description||"-")}</td>
            <td>${i(l.supplier||"-")}</td>
            <td class="mono">${i(l.model_style||"-")}</td>
            <td>${i(l.color||"-")}</td>
            <td class="mono">${i(l.po_lot_no||"-")}</td>
            <td class="mono">${i(l.mtl_no||"-")}</td>
            <td>${i(l.tested_date||l.received_date||"-")}</td>
            <td>${i(l.technician||"-")}</td>
            <td>${i(l.status||"Complete")}</td>
            <td><span class="badge ${o}">${i(c)}</span></td>
          </tr>
        `}).join("")}catch(r){console.error(r),s&&window.nxState&&nxState(s,"error","The list could not be loaded. Check your connection and try again.",16)}};(ze=t("#dtr-tracker-pills"))==null||ze.addEventListener("click",e=>{var r;const a=e.target.closest("[data-slug]");if(!a)return;R("#dtr-tracker-pills .dtr-pill").forEach(l=>l.classList.remove("active")),a.classList.add("active");const s=((r=t("#dtr-tracker-search"))==null?void 0:r.value.trim())||"";Ue(a.dataset.slug,s)}),(qe=t("#dtr-tracker-btn-search"))==null||qe.addEventListener("click",()=>{var r;const e=t("#dtr-tracker-pills .dtr-pill.active"),a=e?e.dataset.slug:"all",s=((r=t("#dtr-tracker-search"))==null?void 0:r.value.trim())||"";Ue(a,s)}),(Qe=t("#dtr-tracker-search"))==null||Qe.addEventListener("keydown",e=>{var a;e.key==="Enter"&&(e.preventDefault(),(a=t("#dtr-tracker-btn-search"))==null||a.click())}),(Xe=t("#dtr-tracker-tbody"))==null||Xe.addEventListener("click",async e=>{const a=e.target.closest("[data-open-record]");if(!a)return;const s=a.dataset.openRecord,r=a.dataset.sourceId;if(s==="bonding")pt("bonding"),await Pe(r),await De(r);else{pt("material");const l=t("#dtr-mat-form-slug");l&&(l.value=s),ft=s,await Le(s,r),await $e(s,r)}});const xe=(e=!0)=>{var o,d;const a=[];for(let n=1;n<=6;n++){const m=parseFloat(((o=t(`#mf_Thickness${n}`))==null?void 0:o.value)??"");Number.isFinite(m)&&m>0&&a.push(m)}if(!a.length)return;const s=Math.min(...a),r=Math.max(...a),l=h(a.reduce((n,m)=>n+m,0)/a.length,2);t("#mf_TMin")&&(t("#mf_TMin").value=s),t("#mf_TMax")&&(t("#mf_TMax").value=r),t("#mf_TAve")&&(t("#mf_TAve").value=l);const c=parseFloat(((d=t("#mf_T_S"))==null?void 0:d.value)??"");Number.isFinite(c)&&c>0&&t("#mf_TRating")&&(t("#mf_TRating").value=l>=c?"PASS":"FAIL",E(t("#mf_TRating"))),e&&Fe()},ut=()=>{var o,d,n,m;const e=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_Weight${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);if(e.length>0&&t("#mf_WAve")){const p=h(e.reduce((C,L)=>C+L,0)/e.length,2);t("#mf_WAve").value=p;const u=parseFloat(((o=t("#mf_Weight_S"))==null?void 0:o.value)??"");Number.isFinite(u)&&u>0&&t("#mf_WRating")&&(t("#mf_WRating").value=p>=u?"PASS":"FAIL",E(t("#mf_WRating")))}const a=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_SG${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);if(a.length>0&&t("#mf_SG_Ave")){const p=h(a.reduce((C,L)=>C+L,0)/a.length,2);t("#mf_SG_Ave").value=p;const u=parseFloat(((d=t("#mf_SG_Std"))==null?void 0:d.value)??"");Number.isFinite(u)&&u>0&&t("#mf_SG_Rate")&&(t("#mf_SG_Rate").value=Math.abs(p-u)<=.02?"PASS":"FAIL",E(t("#mf_SG_Rate")))}const s=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_DIN_${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);s.length>0&&t("#mf_DIN_Rate")&&(t("#mf_DIN_Rate").value=s.some(p=>p>150)?"FAIL":"PASS",E(t("#mf_DIN_Rate")));const r=[1,2].map(p=>{var u;return parseFloat(((u=t(`#mf_RossFlex_${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);r.length>0&&t("#mf_RossFlex_Rate")&&(t("#mf_RossFlex_Rate").value=r.some(p=>p>50)?"FAIL":"PASS",E(t("#mf_RossFlex_Rate")));const l=parseFloat(((n=t("#mf_ShrinkageL"))==null?void 0:n.value)??""),c=parseFloat(((m=t("#mf_Shrinkage_S"))==null?void 0:m.value)??"");Number.isFinite(l)&&Number.isFinite(c)&&c>0&&t("#mf_ShrinkageLRating")&&(t("#mf_ShrinkageLRating").value=Math.abs(l)<=Math.abs(c)?"PASS":"FAIL",E(t("#mf_ShrinkageLRating")))},He={TensileL:{inputs:["mf_TensileL1","mf_TensileL2","mf_TensileL3"],ave:"mf_TensileLAve",std:"mf_Tensile_L",rate:"mf_TensileLRating",dec:2},TensileW:{inputs:["mf_TensileW1","mf_TensileW2","mf_TensileW3"],ave:"mf_TensileWAve",std:"mf_Tensile_W",rate:"mf_TensileWRating",dec:2},ElongationL:{inputs:["mf_ElongationL1","mf_ElongationL2","mf_ElongationL3"],ave:"mf_ElongationLAve",std:"mf_Elongation_L",rate:"mf_ElongationLRating",dec:2},ElongationW:{inputs:["mf_ElongationW1","mf_ElongationW2","mf_ElongationW3"],ave:"mf_ElongationWAve",std:"mf_Elongation_W",rate:"mf_ElongationWRating",dec:2},TrouserTearL:{inputs:["mf_TrouserTearL1","mf_TrouserTearL2","mf_TrouserTearL3"],ave:"mf_TrouserTearLAve",std:"mf_TrouserTear_L",rate:"mf_TrouserTearLRating",dec:2},TrouserTearW:{inputs:["mf_TrouserTearW1","mf_TrouserTearW2","mf_TrouserTearW3"],ave:"mf_TrouserTearWAve",std:"mf_TrouserTear_W",rate:"mf_TrouserTearWRating",dec:2},StitchTearL:{inputs:["mf_StitchTearL1","mf_StitchTearL2","mf_StitchTearL3"],ave:"mf_StitchTearLAve",std:"mf_StitchTear_L",rate:"mf_StitchTearLRating",dec:2},StitchTearW:{inputs:["mf_StitchTearW1","mf_StitchTearW2","mf_StitchTearW3"],ave:"mf_StitchTearWAve",std:"mf_StitchTear_W",rate:"mf_StitchTearWRating",dec:2},MullenBurst:{inputs:["mf_MullenBurst1","mf_MullenBurst2","mf_MullenBurst3"],ave:"mf_Text3977",std:"mf_MullenBurst",rate:"mf_MullenBurstRating",dec:4},DieC:{inputs:["mf_DieC_1","mf_DieC_2","mf_DieC_3"],ave:"mf_DieC_Ave",std:"mf_DieC_Std",rate:"mf_DieC_Rate",dec:2}},Ve=e=>{const a=He[e];if(!a)return;const s=a.inputs.map(n=>{var m;return parseFloat(((m=t(`#${n}`))==null?void 0:m.value)??"")}).filter(n=>Number.isFinite(n)),r=t(`#${a.ave}`),l=t(`#${a.std}`),c=t(`#${a.rate}`);if(!s.length)return;const o=h(s.reduce((n,m)=>n+m,0)/s.length,a.dec);r&&(r.value=o);const d=parseFloat((l==null?void 0:l.value)??"");Number.isFinite(d)&&d>0&&c&&(c.value=o>=d?"PASS":"FAIL",E(c))},ye=()=>{Object.keys(He).forEach(Ve)},Me={CTP:{fibers:["mf_CtPWool","mf_CtPAcrylic","mf_CtPPolyester","mf_CtPNylon","mf_CtPCotton","mf_CtPAcetate"],std:"mf_CTP",rate:"mf_CTPRating"},CTPB:{fibers:["mf_CtPBWool","mf_CtPBAcrylic","mf_CtPBPolyester","mf_CtPBNylon","mf_CtPBCotton","mf_CtPBAcetate"],std:"mf_CtPB_S",rate:"mf_CTPB_Rating"},CTW:{fibers:["mf_CtWWool","mf_CtWAcrylic","mf_CtWPolyester","mf_CtWNylon","mf_CtWCotton","mf_CtWAcetate"],std:"mf_CTW",rate:"mf_CTWRating"},CTWB:{fibers:["mf_CtWBackSideWool","mf_CtWBackSideAcrylic","mf_CtWBackSidePolyester","mf_CtWBackSideNylon","mf_CtWBackSideCotton","mf_CtWBackSideAcetate"],std:"mf_CtWB_S",rate:"mf_CtWB_Rating"}},Oe=e=>{var c;const a=Me[e];if(!a)return;const s=parseFloat(((c=t(`#${a.std}`))==null?void 0:c.value)??""),r=t(`#${a.rate}`);if(!Number.isFinite(s)||s<=0||!r)return;const l=a.fibers.map(o=>{var d;return parseFloat(((d=t(`#${o}`))==null?void 0:d.value)??"")});l.some(o=>!Number.isFinite(o))||(r.value=l.some(o=>o<s)?"FAIL":"PASS",E(r))},ke=()=>{var g,M,k,V,T,O,B,G,at,mt,z,st,K,lt,ht;const e=parseFloat(((g=t("#mf_CtCDry"))==null?void 0:g.value)??""),a=parseFloat(((M=t("#mf_CtCDry_S"))==null?void 0:M.value)??"");Number.isFinite(e)&&Number.isFinite(a)&&a>0&&t("#mf_CTCDRYRating")&&(t("#mf_CTCDRYRating").value=e>=a?"PASS":"FAIL",E(t("#mf_CTCDRYRating")));const s=parseFloat(((k=t("#mf_CtCWet"))==null?void 0:k.value)??""),r=parseFloat(((V=t("#mf_CtCWet_S"))==null?void 0:V.value)??"");Number.isFinite(s)&&Number.isFinite(r)&&r>0&&t("#mf_CTCWETRating")&&(t("#mf_CTCWETRating").value=s>=r?"PASS":"FAIL",E(t("#mf_CTCWETRating")));const l=parseFloat(((T=t("#mf_UV"))==null?void 0:T.value)??""),c=parseFloat(((O=t("#mf_UV_S"))==null?void 0:O.value)??"");Number.isFinite(l)&&Number.isFinite(c)&&c>0&&t("#mf_UVRating")&&(t("#mf_UVRating").value=l>=c?"PASS":"FAIL",E(t("#mf_UVRating")));const o=parseFloat(((B=t("#mf_HeatAging1"))==null?void 0:B.value)??""),d=parseFloat(((G=t("#mf_HeatAging2"))==null?void 0:G.value)??""),n=parseFloat(((at=t("#mf_HeatAging_S"))==null?void 0:at.value)??"");Number.isFinite(o)&&Number.isFinite(d)&&Number.isFinite(n)&&n>0&&t("#mf_HeatAgingRating")&&(t("#mf_HeatAgingRating").value=o<n||d<n?"FAIL":"PASS",E(t("#mf_HeatAgingRating")));const m=parseFloat(((mt=t("#mf_Hydrolysis1"))==null?void 0:mt.value)??""),p=parseFloat(((z=t("#mf_Hydrolysis2"))==null?void 0:z.value)??""),u=parseFloat(((st=t("#mf_Hydrolysis_S"))==null?void 0:st.value)??"");Number.isFinite(m)&&Number.isFinite(p)&&Number.isFinite(u)&&u>0&&t("#mf_HydrolysisRating")&&(t("#mf_HydrolysisRating").value=m<u||p<u?"FAIL":"PASS",E(t("#mf_HydrolysisRating")));const C=parseFloat(((K=t("#mf_PVC_1"))==null?void 0:K.value)??""),L=parseFloat(((lt=t("#mf_PVC_2"))==null?void 0:lt.value)??""),$=parseFloat(((ht=t("#mf_PVC_S"))==null?void 0:ht.value)??"");Number.isFinite(C)&&Number.isFinite(L)&&Number.isFinite($)&&$>0&&t("#mf_PVC_Rating")&&(t("#mf_PVC_Rating").value=C<$||L<$?"FAIL":"PASS",E(t("#mf_PVC_Rating")))},W=()=>{var c;let e=!1,a=!1,s=!1;R("#dtr-material-form .dtr-rate-inp").forEach(o=>{E(o);const d=String(o.value||"").trim().toUpperCase();d==="FAIL"?a=!0:d==="TOLERABLE"?s=!0:d==="PASS"&&(e=!0)});let r="PASS";a?r="FAIL":s?r="TOLERABLE":e?r="PASS":(c=t("#mf_Remarks"))!=null&&c.value&&(r=String(t("#mf_Remarks").value).toUpperCase()),t("#mf_Remarks")&&(t("#mf_Remarks").value=r);const l=t("#dtr-stamp-badge");l&&(l.textContent=r,l.className="dtr-stamp "+(r==="FAIL"?"dtr-stamp-fail":r==="TOLERABLE"?"dtr-stamp-warn":"dtr-stamp-pass"))},Fe=async()=>{var s,r;const e=((s=t("#mf_MaterialType"))==null?void 0:s.value.trim())||"",a=parseFloat(((r=t("#mf_TAve"))==null?void 0:r.value)??"0")||0;if(e)try{const l=await N(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`);l&&l.mapped&&Object.keys(l.mapped).forEach(c=>{const o=l.mapped[c],d=t(`#mf_${c}`)||t(`[name="${c}"]`,t("#dtr-material-form"));d&&(d.value=o)}),ut(),ye(),Object.keys(Me).forEach(Oe),ke(),W()}catch(l){console.warn("[DTR] applyStandardValuesSmart error:",l)}},Ea=async()=>{xe(!1),await Fe(),w("✓ Executed ApplyStandardValuesSmart + Thickness + Directional Averages + 6-Point Colorfastness + EvaluateRatings.")},Le=async(e,a=null)=>{const s=t("#dtr-mat-record-select");try{s&&(s.innerHTML='<option value="">Loading records…</option>');const r=await N(`dtr/records?slug=${encodeURIComponent(e)}`);if(!s||!r.rows)return;if(!r.rows.length){s.innerHTML='<option value="">No records yet - press “+ New Record”</option>';return}s.innerHTML=r.rows.map(l=>`
        <option value="${i(l.source_id)}" ${String(l.source_id)===String(a)?"selected":""}>
          ${i(l.lab_ref_no)} · ${i(l.material_type||l.form_category)} · ${i(l.model_style||l.material_code||"")} [${i(l.overall_result||"Pass")}]
        </option>
      `).join("")}catch(r){console.error(r),s&&(s.innerHTML='<option value="">Could not load records - reload the page</option>'),window.nxToast&&nxToast("The record list could not be loaded.","error")}},xa=e=>{const a=t("#dtr-raw-cols-container"),s=t("#dtr-raw-col-count");if(!a)return;const r=Object.keys(e||{});s&&(s.textContent=r.length),a.innerHTML=r.map(l=>`
      <div class="dtr-field">
        <label title="${i(l)}"><code>${i(l)}</code></label>
        <input type="text" data-raw-col="${i(l)}" value="${i(e[l]??"")}" ${l==="ID"?'readonly class="dtr-ro"':""}>
      </div>
    `).join("")},Be=(e,a={})=>{Ee=e||{},t("#dtr-active-form-label")&&a.label&&(t("#dtr-active-form-label").textContent=`${a.label}`),t("#dtr-active-labref-title")&&(t("#dtr-active-labref-title").textContent=`${e.LabRefNo||"New Report"} — ${e["Material Description"]||e.MaterialDescription||e.MaterialType||""}`),R("#dtr-material-form input, #dtr-material-form select").forEach(s=>{s.name&&(s.value="")}),Object.keys(e).forEach(s=>{const r=e[s]??"",l=t(`#mf_${s.replace(/[^a-zA-Z0-9_]/g,"")}`);l&&(l.value=r);const c=t(`[name="${CSS.escape(s)}"]`,t("#dtr-material-form"));c&&(c.value=r)}),t("#mf_MaterialDescription")&&!t("#mf_MaterialDescription").value&&(t("#mf_MaterialDescription").value=e["Material Description"]||e.MaterialDescription||""),t("#mf_SupplierFactory")&&!t("#mf_SupplierFactory").value&&(t("#mf_SupplierFactory").value=e["Supplier/Factory"]||e.Supplier||e.SupplierFactory||""),t("#mf_POLotNo")&&!t("#mf_POLotNo").value&&(t("#mf_POLotNo").value=e["PO/Lot No"]||e.POLotNo||""),t("#mf_ModelNoStyle")&&!t("#mf_ModelNoStyle").value&&(t("#mf_ModelNoStyle").value=e["Model No/Style"]||e.ModelNoStyle||""),t("#mf_POQuantity")&&!t("#mf_POQuantity").value&&(t("#mf_POQuantity").value=e["PO Quantity"]||e.POQuantity||""),t("#mf_TestedBy")&&!t("#mf_TestedBy").value&&(t("#mf_TestedBy").value=e["Tested by"]||e.TestedBy||e.Technician||""),xa(e),xe(!1),ut(),ye(),Object.keys(Me).forEach(Oe),ke(),W()},$e=async(e,a=0)=>{try{const s=await N(`dtr/record?slug=${encodeURIComponent(e)}&source_id=${encodeURIComponent(a)}`);s&&s.ok&&Be(s.record||{},s.config||{})}catch(s){console.error(s)}};(Je=t("#mf_LabRefNo"))==null||Je.addEventListener("change",()=>{var s;const e=(((s=t("#mf_LabRefNo"))==null?void 0:s.value)||"").toUpperCase().trim(),a=t("#mf_MaterialType");!e||!a||(e.includes("FBU")||e.includes("FBL")?a.value||(a.value="Canvas"):e.includes("LEU")||e.includes("LEL")?a.value||(a.value="Suede"):e.includes("RB")?a.value="Rubber":e.includes("IB")?a.value="Inner Box":e.includes("OB")?a.value="Outer Box":e.includes("FML")?a.value="Sockliner - Latex":e.includes("FM")?a.value="PU Foam":e.includes("SKL")?a.value="Sockliner - EVA":e.includes("LC")?a.value="Shoe lace":e.includes("TH")?a.value="Thread":e.includes("EY")?a.value="Eyelet":e.includes("GR")?a.value="Grommet":e.includes("ZP")&&(a.value="Zipper"),Fe())});const Ce=async()=>{var r,l,c;const e=((r=t("#mf_MaterialCode"))==null?void 0:r.value.trim())||"",a=((l=t("#mf_POLotNo"))==null?void 0:l.value.trim())||"",s=((c=t("#mf_MTL"))==null?void 0:c.value.trim())||"";if(!e&&!a&&!s){w("Enter a Material Code, PO/Lot No, or MTL # first.",!0);return}try{const o=await N(`dtr/lookup?material_code=${encodeURIComponent(e)}&po_lot_no=${encodeURIComponent(a)}&mtl_no=${encodeURIComponent(s)}`);let d=[];if(o.material&&(t("#mf_SupplierFactory")&&(t("#mf_SupplierFactory").value=o.material.Supplier||""),t("#mf_MaterialDescription")&&(t("#mf_MaterialDescription").value=o.material.MaterialName||""),d.push(`MaterialList (${o.material.MaterialCode})`)),o.shoe&&(t("#mf_ModelNoStyle")&&(t("#mf_ModelNoStyle").value=o.shoe.StyleNo||""),t("#mf_Color")&&(t("#mf_Color").value=o.shoe.Colorway||""),t("#mf_MTL")&&o.shoe.MTL&&(t("#mf_MTL").value=o.shoe.MTL),d.push(`ShoeList (${o.shoe.StyleNo})`)),o.dsr&&o.dsr.length>0){const n=o.dsr[0];t("#mf_StyleNumber_DSR")&&(t("#mf_StyleNumber_DSR").value=n.StyleNumber||""),t("#mf_StyleDescription_DSR")&&(t("#mf_StyleDescription_DSR").value=n.StyleDescription||""),t("#mf_Colorway_DSR")&&(t("#mf_Colorway_DSR").value=n.Colorway||""),t("#mf_ComponentorLocation_DSR")&&(t("#mf_ComponentorLocation_DSR").value=n.ComponentorLocation||""),t("#mf_VFMaterialCode_DSR")&&(t("#mf_VFMaterialCode_DSR").value=n.VFMaterialCode||""),t("#mf_InternalName_DSR")&&(t("#mf_InternalName_DSR").value=n["Internal NameVans1"]||n.Material||""),t("#mf_ColorName_DSR")&&(t("#mf_ColorName_DSR").value=n.ColorName||n.Color||""),d.push(`tblDSR (${o.dsr.length} component rows)`)}d.length?w(`✓ Auto-Lookup populated from: ${d.join(" · ")}`):w("No exact match found in MaterialList / ShoeList / tblDSR.",!0)}catch(o){console.error(o)}};(Ke=t("#btn-dtr-lookup-all"))==null||Ke.addEventListener("click",Ce),(Ye=t("#mf_MaterialCode"))==null||Ye.addEventListener("change",Ce),(Ze=t("#mf_POLotNo"))==null||Ze.addEventListener("change",Ce),(ta=t("#mf_MTL"))==null||ta.addEventListener("change",Ce),(ea=t("#mf_MaterialType"))==null||ea.addEventListener("change",Fe),R(".dtr-calc-thick").forEach(e=>{e.addEventListener("input",()=>xe(!0))}),R(".dtr-calc-weight, .dtr-calc-sg, .dtr-calc-din, .dtr-calc-ross, .dtr-calc-shrink").forEach(e=>{e.addEventListener("input",()=>{ut(),W()})}),R(".dtr-dir-inp, .dtr-std-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset.dirGroup?Ve(e.dataset.dirGroup):ye(),W()})}),R(".dtr-6pt-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset["6pt"]&&Oe(e.dataset["6pt"]),W()})}),R(".dtr-crock-inp").forEach(e=>{e.addEventListener("input",()=>{ke(),W()})}),R(".dtr-rate-inp").forEach(e=>{e.addEventListener("change",W),e.addEventListener("input",W)}),(aa=t("#dtr-mat-form-slug"))==null||aa.addEventListener("change",async e=>{ft=e.target.value,await Le(ft),await $e(ft,0)}),(sa=t("#dtr-mat-record-select"))==null||sa.addEventListener("change",async e=>{const a=e.target.value;a&&await $e(ft,a)}),(la=t("#btn-dtr-new"))==null||la.addEventListener("click",()=>{const e=new Date().toISOString().slice(0,10);Be({ID:"",LabRefNo:`DTP${e.replace(/-/g,"")}${ft.toUpperCase().slice(0,3)}-1`,TR:`TR${e.replace(/-/g,"")}001`,ReceivedDate:e,TestedDate:e,FinishedDate:e,Status:"Complete",Remarks:"PASS"}),w("Initialized new blank test report record.")}),(ra=t("#btn-dtr-review"))==null||ra.addEventListener("click",()=>{const e=prompt("Enter Reviewer Name:","Faye Ann Elaysa");if(!e)return;const a=t('[data-raw-col="AuthorizedBy"]');a&&(a.value=e);const s=t('[data-raw-col="AuthorizationStatus"]');s&&(s.value="Reviewed"),w(`✓ Record stamped Reviewed by ${e}`)}),(oa=t("#btn-dtr-refresh-calc"))==null||oa.addEventListener("click",Ea),(na=t("#btn-dtr-load-machine"))==null||na.addEventListener("click",async()=>{var a;const e=((a=t("#mf_LabRefNo"))==null?void 0:a.value.trim())||"";if(!e){w("Enter a LabRefNo first before loading machine results.",!0);return}try{const s=await N("dtr/load-machine",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:ft,lab_ref_no:e})});s&&s.loadedFields&&(Object.keys(s.loadedFields).forEach(r=>{const l=s.loadedFields[r],c=t(`#mf_${r}`)||t(`[name="${CSS.escape(r)}"]`,t("#dtr-material-form"));c&&(c.value=l);const o=t(`[data-raw-col="${CSS.escape(r)}"]`);o&&(o.value=l)}),ut(),ye(),W(),t("#dtr-machine-status")&&(t("#dtr-machine-status").textContent=s.message||"Machine data loaded."),w(`${s.message}`))}catch(s){w("Machine loader error: "+(s.message||""),!0)}}),(ia=t("#btn-dtr-save"))==null||ia.addEventListener("click",async()=>{const e=Object.assign({},Ee);R("#dtr-raw-cols-container [data-raw-col]").forEach(a=>{e[a.dataset.rawCol]=a.value}),R("#dtr-material-form [name]").forEach(a=>{a.name&&(e[a.name]=a.value)});try{const a=await N("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:ft,fields:e})});a&&a.ok?(t("#mf_ID")&&(t("#mf_ID").value=a.recordId),w(`✓ ${a.message} (Ready in InterLink Sync ComboBox!)`),await Le(ft,a.recordId)):w(a.error||"Save failed.",!0)}catch{w("Failed to save record.",!0)}}),(ca=t("#btn-toggle-raw-cols"))==null||ca.addEventListener("click",()=>{var e;(e=t("#dtr-raw-cols-container"))==null||e.classList.toggle("hidden")});const Ma={TODDLER:14,KIDS:16,CHILDREN:24,ADULT:35},we=1,Re=e=>Math.round(e*.8*100)/100,je=(e,a)=>!Number.isFinite(e)||e<=0?null:e>=a?"PASS":e>=Re(a)?"TOLERABLE":"FAIL",Dt=()=>{var p,u;const e=(((p=t("#bf_AgeCategory"))==null?void 0:p.value)||"ADULT").toUpperCase(),a=Ma[e]||35,s=parseFloat(((u=t("#bf_STD"))==null?void 0:u.value)??"2.5")||2.5;R("#dtr-bonding-points-tbody [data-spot-row]").forEach(C=>{const L=Number(C.dataset.spotRow);C.style.opacity=L<=a?"1":"0.45"});const r=(C,L,$)=>{const g=[];let M=!1,k=!1;for(let V=1;V<=L;V++){const T=t(`#bp_${C}${V}`);if(!T)continue;const O=parseFloat(T.value??"");if(T.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),Number.isFinite(O)&&O>0){g.push(O);const B=je(O,$);B==="FAIL"?(M=!0,T.classList.add("dtr-rate-fail")):B==="TOLERABLE"?(k=!0,T.classList.add("dtr-rate-warn")):T.classList.add("dtr-rate-pass")}}return g.length?{min:h(Math.min(...g),2),max:h(Math.max(...g),2),ave:h(g.reduce((V,T)=>V+T,0)/g.length,2),res:M?"FAIL":k?"TOLERABLE":"PASS"}:{min:"",max:"",ave:"",res:""}},l=r("UTFpoint",a,s),c=r("FTOpoint",a,s),o=r("FiTO",13,we);t("#bp_UTFMin")&&(t("#bp_UTFMin").value=l.min),t("#bp_UTFMax")&&(t("#bp_UTFMax").value=l.max),t("#bp_UTFAverage")&&(t("#bp_UTFAverage").value=l.ave),t("#bp_UTF_Result")&&(t("#bp_UTF_Result").value=l.res,E(t("#bp_UTF_Result"))),t("#bp_FTOMin")&&(t("#bp_FTOMin").value=c.min),t("#bp_FTOMax")&&(t("#bp_FTOMax").value=c.max),t("#bp_FTOAverage")&&(t("#bp_FTOAverage").value=c.ave),t("#bp_FTO_Result")&&(t("#bp_FTO_Result").value=c.res,E(t("#bp_FTO_Result"))),t("#bp_FiTOMin")&&(t("#bp_FiTOMin").value=o.min),t("#bp_FiTOMax")&&(t("#bp_FiTOMax").value=o.max),t("#bp_FiTOAverage")&&(t("#bp_FiTOAverage").value=o.ave),t("#bp_FiTO_Result")&&(t("#bp_FiTO_Result").value=o.res,E(t("#bp_FiTO_Result")));const d=[l.res,c.res,o.res].filter(Boolean);let n="PASS";d.includes("FAIL")?n="FAIL":d.includes("TOLERABLE")&&(n="TOLERABLE"),t("#bf_Result")&&(t("#bf_Result").value=n,E(t("#bf_Result")));const m=t("#bf-overall-badge");m&&(m.textContent=n,m.className="badge "+(n==="FAIL"?"b-err":n==="TOLERABLE"?"b-warn":"b-ok"))},Pe=async(e=null)=>{const a=t("#dtr-bonding-record-select");try{a&&(a.innerHTML='<option value="">Loading reports…</option>');const s=await N("dtr/records?slug=bonding");if(!a||!s.rows)return;if(!s.rows.length){a.innerHTML='<option value="">No bonding reports yet</option>';return}a.innerHTML=s.rows.map(r=>`
        <option value="${i(r.source_id)}" ${String(r.source_id)===String(e)?"selected":""}>
          ${i(r.lab_ref_no)} · ${i(r.model_style||"")} · ${i(r.color||"")} [${i(r.overall_result||"Pass")}]
        </option>
      `).join("")}catch(s){console.error(s),a&&(a.innerHTML='<option value="">Could not load reports - reload the page</option>'),window.nxToast&&nxToast("The bonding report list could not be loaded.","error")}},De=async(e=0)=>{try{const a=await N(`dtr/record?slug=bonding&source_id=${encodeURIComponent(e)}`);if(!a||!a.ok)return;const s=a.record||{},r=a.bondingPoints||{};v=r,Object.keys(s).forEach(l=>{const c=t(`#bf_${l}`);c&&(c.value=s[l]??"")}),R('#dtr-bonding-form [name^="cf_"]').forEach(l=>{l.value=(a.customValues||{})[l.name]??""});for(let l=1;l<=35;l++)t(`#bp_UTFpoint${l}`)&&(t(`#bp_UTFpoint${l}`).value=r[`UTFpoint${l}`]??""),t(`#bp_FTOpoint${l}`)&&(t(`#bp_FTOpoint${l}`).value=r[`FTOpoint${l}`]??""),l<=13&&t(`#bp_FiTO${l}`)&&(t(`#bp_FiTO${l}`).value=r[`FiTO${l}`]??"");Dt()}catch(a){console.error(a)}};(da=t("#dtr-bonding-record-select"))==null||da.addEventListener("change",e=>{e.target.value&&De(e.target.value)}),(fa=t("#bf_AgeCategory"))==null||fa.addEventListener("change",Dt),(pa=t("#bf_STD"))==null||pa.addEventListener("change",Dt),R("#dtr-bonding-points-tbody .dtr-bp-inp").forEach(e=>{e.addEventListener("input",Dt)}),(ua=t("#btn-bond-recalc"))==null||ua.addEventListener("click",()=>{Dt(),w("✓ Recalculated UTF, FTO, and FiTO Min/Max/Ave & Spot Tolerances.")}),(ma=t("#btn-bond-new"))==null||ma.addEventListener("click",()=>{const e=new Date().toISOString().slice(0,10);R("#dtr-bonding-form input").forEach(a=>{a.value=""}),t("#bf_LabRefNo")&&(t("#bf_LabRefNo").value=`DTP${e.replace(/-/g,"")}SHB01-R`),t("#bf_AgeCategory")&&(t("#bf_AgeCategory").value="ADULT"),t("#bf_STD")&&(t("#bf_STD").value="2.5"),t("#bf_ReceivedDate")&&(t("#bf_ReceivedDate").value=e),t("#bf_TestedDate")&&(t("#bf_TestedDate").value=e),t("#bf_FinishedDate")&&(t("#bf_FinishedDate").value=e),Dt(),w("Initialized new Whole Shoe Bonding Test Report.")}),(va=t("#btn-bond-load-u60"))==null||va.addEventListener("click",async()=>{var a;const e=((a=t("#bf_LabRefNo"))==null?void 0:a.value.trim())||"DTP20261024SHB11-R";try{const s=await N("dtr/load-machine",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"bonding",lab_ref_no:e})});s&&s.loadedFields&&(Object.keys(s.loadedFields).forEach(r=>{const l=t(`#bp_${r}`);l&&(l.value=s.loadedFields[r])}),Dt(),w(`${s.message}`))}catch{w("Failed to load U60 machine file.",!0)}}),(_a=t("#btn-bond-save"))==null||_a.addEventListener("click",async()=>{var s,r,l,c,o,d,n,m,p,u,C,L,$;const e={};R("#dtr-bonding-form [name]").forEach(g=>{g.name&&(e[g.name]=g.value)});const a={UTFMin:((s=t("#bp_UTFMin"))==null?void 0:s.value)||null,UTFMax:((r=t("#bp_UTFMax"))==null?void 0:r.value)||null,UTFAverage:((l=t("#bp_UTFAverage"))==null?void 0:l.value)||null,UTF_Result:((c=t("#bp_UTF_Result"))==null?void 0:c.value)||null,FTOMin:((o=t("#bp_FTOMin"))==null?void 0:o.value)||null,FTOMax:((d=t("#bp_FTOMax"))==null?void 0:d.value)||null,FTOAverage:((n=t("#bp_FTOAverage"))==null?void 0:n.value)||null,FTO_Result:((m=t("#bp_FTO_Result"))==null?void 0:m.value)||null,FiTOMin:((p=t("#bp_FiTOMin"))==null?void 0:p.value)||null,FiTOMax:((u=t("#bp_FiTOMax"))==null?void 0:u.value)||null,FiTOAverage:((C=t("#bp_FiTOAverage"))==null?void 0:C.value)||null,FiTO_Result:((L=t("#bp_FiTO_Result"))==null?void 0:L.value)||null,Result:(($=t("#bf_Result"))==null?void 0:$.value)||"PASS"};R("#dtr-bonding-points-tbody [data-bp-col]").forEach(g=>{a[g.dataset.bpCol]=g.value});try{const g=await N("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"bonding",fields:e,bondingPoints:a})});g&&g.ok&&(t("#bf_BondingTestID")&&(t("#bf_BondingTestID").value=g.recordId),w(`✓ ${g.message}`),await Pe(g.recordId))}catch{w("Error saving Bonding Report.",!0)}});const Ge=()=>{var St,Q,X,I,yt,_t,Ht,Vt,Bt,jt,bt,ot,nt,it,Ft,Gt,Lt,$t,Ct,wt,Rt,At,Et,xt,Mt,Ot,kt;const e=parseFloat(((St=t("#calc_raw_w1"))==null?void 0:St.value)??"0")||0,a=parseFloat(((Q=t("#calc_raw_w2"))==null?void 0:Q.value)??"0")||0,s=parseFloat(((X=t("#calc_raw_w3"))==null?void 0:X.value)??"0")||0,r=h(e/100*1e4,2),l=h(a/100*1e4,2),c=h(s/100*1e4,2),o=h((r+l+c)/3,2);t("#calc_res_w1")&&(t("#calc_res_w1").value=r),t("#calc_res_w2")&&(t("#calc_res_w2").value=l),t("#calc_res_w3")&&(t("#calc_res_w3").value=c),t("#calc_res_wave")&&(t("#calc_res_wave").textContent=o);const d=parseFloat(((I=t("#calc_sg_w1"))==null?void 0:I.value)??"0")||0,n=parseFloat(((yt=t("#calc_sg_w2"))==null?void 0:yt.value)??"0")||0,m=parseFloat(((_t=t("#calc_sg_w3"))==null?void 0:_t.value)??"0")||0,p=parseFloat(((Ht=t("#calc_sg_t1"))==null?void 0:Ht.value)??"1")||1,u=parseFloat(((Vt=t("#calc_sg_t2"))==null?void 0:Vt.value)??"1")||1,C=parseFloat(((Bt=t("#calc_sg_t3"))==null?void 0:Bt.value)??"1")||1,L=h(d/(100*(p/10)),3),$=h(n/(100*(u/10)),3),g=h(m/(100*(C/10)),3),M=h((L+$+g)/3,3);t("#calc_res_sg_ave")&&(t("#calc_res_sg_ave").textContent=`${L}, ${$}, ${g} (Ave: ${M})`);const k=parseFloat(((jt=t("#calc_cp_b1"))==null?void 0:jt.value)??"10")||10,V=parseFloat(((bt=t("#calc_cp_a1"))==null?void 0:bt.value)??"9")||9,T=parseFloat(((ot=t("#calc_cp_b2"))==null?void 0:ot.value)??"10")||10,O=parseFloat(((nt=t("#calc_cp_a2"))==null?void 0:nt.value)??"9")||9,B=parseFloat(((it=t("#calc_cp_a3"))==null?void 0:it.value)??"9")||9,G=parseFloat(((Ft=t("#calc_cp_sp"))==null?void 0:Ft.value)??"0")||0,at=k-G!==0?k-G:1,mt=T-G!==0?T-G:1,z=h((k-V)/at*100,1),st=h((T-O)/mt*100,1),K=h((k-B)/at*100,1),lt=h((z+st+K)/3,1);t("#calc_res_cp_ave")&&(t("#calc_res_cp_ave").textContent=`${lt}% (${z}%, ${st}%, ${K}%)`);const ht=parseFloat(((Gt=t("#calc_din_wb"))==null?void 0:Gt.value)??"0")||0,zt=parseFloat(((Lt=t("#calc_din_wa"))==null?void 0:Lt.value)??"0")||0,Nt=parseFloat((($t=t("#calc_din_sg"))==null?void 0:$t.value)??"1")||1,Wt=parseFloat(((Ct=t("#calc_din_sa"))==null?void 0:Ct.value)??"200")||200,Jt=h((ht-zt)/Nt*(200/Wt)*1e3,2);t("#calc_res_din")&&(t("#calc_res_din").textContent=Jt);const It=parseFloat(((wt=t("#calc_rf_li1"))==null?void 0:wt.value)??"2.5")||2.5,Ut=parseFloat(((Rt=t("#calc_rf_lf1"))==null?void 0:Rt.value)??"2.5")||2.5,gt=parseFloat(((At=t("#calc_rf_li2"))==null?void 0:At.value)??"2.5")||2.5,Kt=parseFloat(((Et=t("#calc_rf_lf2"))==null?void 0:Et.value)??"2.5")||2.5,qt=h((Ut-It)/It*100,2),q=h((Kt-gt)/gt*100,2);t("#calc_res_rf")&&(t("#calc_res_rf").textContent=`${qt}% / ${q}%`);const Y=parseFloat(((xt=t("#calc_sh_lb"))==null?void 0:xt.value)??"100")||100,rt=parseFloat(((Mt=t("#calc_sh_la"))==null?void 0:Mt.value)??"100")||100,Tt=parseFloat(((Ot=t("#calc_sh_wb"))==null?void 0:Ot.value)??"100")||100,vt=parseFloat(((kt=t("#calc_sh_wa"))==null?void 0:kt.value)??"100")||100,Z=h((Y-rt)/Y*100,2),y=h((Tt-vt)/Tt*100,2);t("#calc_res_sh")&&(t("#calc_res_sh").textContent=`L: ${Z}% / W: ${y}%`)};R("#dtr-panel-calculators input").forEach(e=>{e.addEventListener("input",Ge)}),(ba=t("#btn-apply-calc-weight"))==null||ba.addEventListener("click",()=>{var e,a,s;t("#mf_Weight1")&&(t("#mf_Weight1").value=((e=t("#calc_res_w1"))==null?void 0:e.value)||""),t("#mf_Weight2")&&(t("#mf_Weight2").value=((a=t("#calc_res_w2"))==null?void 0:a.value)||""),t("#mf_Weight3")&&(t("#mf_Weight3").value=((s=t("#calc_res_w3"))==null?void 0:s.value)||""),ut(),W(),pt("material"),w("✓ Applied calculated Weight 1..3 and WAve to Material Form.")}),(ha=t("#btn-apply-calc-sg"))==null||ha.addEventListener("click",()=>{var o,d,n,m,p,u;const e=parseFloat(((o=t("#calc_sg_w1"))==null?void 0:o.value)??"0")||0,a=parseFloat(((d=t("#calc_sg_w2"))==null?void 0:d.value)??"0")||0,s=parseFloat(((n=t("#calc_sg_w3"))==null?void 0:n.value)??"0")||0,r=parseFloat(((m=t("#calc_sg_t1"))==null?void 0:m.value)??"1")||1,l=parseFloat(((p=t("#calc_sg_t2"))==null?void 0:p.value)??"1")||1,c=parseFloat(((u=t("#calc_sg_t3"))==null?void 0:u.value)??"1")||1;t("#mf_SG1")&&(t("#mf_SG1").value=h(e/(100*(r/10)),3)),t("#mf_SG2")&&(t("#mf_SG2").value=h(a/(100*(l/10)),3)),t("#mf_SG3")&&(t("#mf_SG3").value=h(s/(100*(c/10)),3)),ut(),W(),pt("material"),w("✓ Applied Specific Gravity SG1..3 and SG_Ave to Material Form.")}),(ga=t("#btn-apply-calc-din"))==null||ga.addEventListener("click",()=>{var a;const e=((a=t("#calc_res_din"))==null?void 0:a.textContent)||"";t("#mf_DIN_1")&&(t("#mf_DIN_1").value=e),ut(),W(),pt("material"),w("✓ Applied DIN Abrasion value to Material Form.")}),(Ta=t("#btn-apply-calc-ross"))==null||Ta.addEventListener("click",()=>{var l,c,o,d;const e=parseFloat(((l=t("#calc_rf_li1"))==null?void 0:l.value)??"2.5")||2.5,a=parseFloat(((c=t("#calc_rf_lf1"))==null?void 0:c.value)??"2.5")||2.5,s=parseFloat(((o=t("#calc_rf_li2"))==null?void 0:o.value)??"2.5")||2.5,r=parseFloat(((d=t("#calc_rf_lf2"))==null?void 0:d.value)??"2.5")||2.5;t("#mf_RossFlex_1")&&(t("#mf_RossFlex_1").value=h((a-e)/e*100,2)),t("#mf_RossFlex_2")&&(t("#mf_RossFlex_2").value=h((r-s)/s*100,2)),ut(),W(),pt("material"),w("✓ Applied Ross Flex 1 & 2 to Material Form.")}),(Sa=t("#btn-apply-calc-shrink"))==null||Sa.addEventListener("click",()=>{var l,c,o,d;const e=parseFloat(((l=t("#calc_sh_lb"))==null?void 0:l.value)??"100")||100,a=parseFloat(((c=t("#calc_sh_la"))==null?void 0:c.value)??"100")||100,s=parseFloat(((o=t("#calc_sh_wb"))==null?void 0:o.value)??"100")||100,r=parseFloat(((d=t("#calc_sh_wa"))==null?void 0:d.value)??"100")||100;t("#mf_ShrinkageL")&&(t("#mf_ShrinkageL").value=h((e-a)/e*100,2)),t("#mf_ShrinkageW")&&(t("#mf_ShrinkageW").value=h((s-r)/s*100,2)),ut(),W(),pt("material"),w("✓ Applied Shrinkage L & W to Material Form.")});const Ne=async()=>{var s,r;const e=((s=t("#exp_mat_type"))==null?void 0:s.value)||"Canvas",a=((r=t("#exp_t_ave"))==null?void 0:r.value)||"1.25";try{const l=await N(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`),c=t("#exp-standards-tbody");if(!c||!l.standards)return;c.innerHTML=l.standards.map(o=>`
        <tr>
          <td><b>${i(o.MaterialType)}</b></td>
          <td class="mono"><code>${i(o.TestMethod)}</code></td>
          <td class="mono"><b>${i(o.StandardValue??"-")}</b></td>
          <td class="mono">${i(o.Tolerance??"-")}</td>
          <td class="mono">${i(o.TMinC??"-")}</td>
          <td class="mono">${i(o.TMaxC??"-")}</td>
        </tr>
      `).join("")}catch(l){console.error(l)}};(ya=t("#btn-exp-run"))==null||ya.addEventListener("click",Ne),(Fa=t("#exp_mat_type"))==null||Fa.addEventListener("change",Ne);const Oa=()=>{var G,at,mt,z,st,K,lt,ht,zt,Nt,Wt,Jt,It,Ut,gt,Kt,qt,q,Y,rt,Tt,vt,Z,y,St,Q,X,I,yt,_t,Ht,Vt,Bt,jt,bt,ot,nt,it,Ft,Gt,Lt,$t,Ct,wt,Rt,At,Et,xt,Mt,Ot,kt,Yt,Zt,te,ee,ae,se,le,re,oe,ne,ie,ce,de,fe,pe,ue,me,ve,_e,be,he,ge,Te,Se;const e=((G=t("#mf_LabRefNo"))==null?void 0:G.value)||"DTP-LAB-PREVIEW",a=((at=t("#mf_TR"))==null?void 0:at.value)||"-",s=((mt=t("#mf_MaterialType"))==null?void 0:mt.value)||"Material",r=((z=t("#mf_MaterialCode"))==null?void 0:z.value)||"-",l=((st=t("#mf_MaterialDescription"))==null?void 0:st.value)||"-",c=((K=t("#mf_SupplierFactory"))==null?void 0:K.value)||"DATIAN SUBIC SHOES INC.",o=((lt=t("#mf_ModelNoStyle"))==null?void 0:lt.value)||"-",d=((ht=t("#mf_Color"))==null?void 0:ht.value)||"-",n=((zt=t("#mf_POLotNo"))==null?void 0:zt.value)||"-",m=((Nt=t("#mf_MTL"))==null?void 0:Nt.value)||"-",p=Object.assign({},Ee||{});R("[data-raw-col]").forEach(U=>{p[U.dataset.rawCol]=U.value});const u=(...U)=>{var ct;for(const dt of U){const tt=(ct=t("#mf_"+dt))==null?void 0:ct.value;if(tt!==void 0&&String(tt).trim()!=="")return String(tt).trim();const j=p[dt];if(j!=null&&String(j).trim()!=="")return String(j).trim()}return""},C=U=>String(U||"").replace(/ 00:00:00$/,""),L=C(u("TestedDate","Tested Date"))||new Date().toISOString().slice(0,10),$=u("Technician","TestedBy"),g=u("AuthorizedBy","ReviewedBy"),M=C(u("AuthorizedDate")),k=(u("Remarks")||"PASS").toUpperCase(),V=k==="PASS"?"":k==="TOLERABLE"?"stamp-tol":"stamp-fail",T=[],O=(U,ct,dt,tt,j,Pt,et,H)=>{(dt||Pt)&&T.push(`
          <tr>
            <td class="text-left"><b>${U} — Length (L)</b></td>
            <td>${ct}</td>
            <td><b>${dt||"-"}</b></td>
            <td>${tt||"-"}</td>
            <td><b class="${j==="PASS"?"dtr-rate-pass":j==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${j||"-"}</b></td>
          </tr>
          <tr>
            <td class="text-left"><b>${U} — Width (W)</b></td>
            <td>${ct}</td>
            <td><b>${Pt||"-"}</b></td>
            <td>${et||"-"}</td>
            <td><b class="${H==="PASS"?"dtr-rate-pass":H==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${H||"-"}</b></td>
          </tr>
        `)};O("Tensile Strength","kg",(Wt=t("#mf_TensileLAve"))==null?void 0:Wt.value,(Jt=t("#mf_Tensile_L"))==null?void 0:Jt.value,(It=t("#mf_TensileLRating"))==null?void 0:It.value,(Ut=t("#mf_TensileWAve"))==null?void 0:Ut.value,(gt=t("#mf_Tensile_W"))==null?void 0:gt.value,(Kt=t("#mf_TensileWRating"))==null?void 0:Kt.value),O("Elongation","%",(qt=t("#mf_ElongationLAve"))==null?void 0:qt.value,(q=t("#mf_Elongation_L"))==null?void 0:q.value,(Y=t("#mf_ElongationLRating"))==null?void 0:Y.value,(rt=t("#mf_ElongationWAve"))==null?void 0:rt.value,(Tt=t("#mf_Elongation_W"))==null?void 0:Tt.value,(vt=t("#mf_ElongationWRating"))==null?void 0:vt.value),O("Trouser / Wing Tear","kg",(Z=t("#mf_TrouserTearLAve"))==null?void 0:Z.value,(y=t("#mf_TrouserTear_L"))==null?void 0:y.value,(St=t("#mf_TrouserTearLRating"))==null?void 0:St.value,(Q=t("#mf_TrouserTearWAve"))==null?void 0:Q.value,(X=t("#mf_TrouserTear_W"))==null?void 0:X.value,(I=t("#mf_TrouserTearWRating"))==null?void 0:I.value),O("Stitch Tear","kg",(yt=t("#mf_StitchTearLAve"))==null?void 0:yt.value,(_t=t("#mf_StitchTear_L"))==null?void 0:_t.value,(Ht=t("#mf_StitchTearLRating"))==null?void 0:Ht.value,(Vt=t("#mf_StitchTearWAve"))==null?void 0:Vt.value,(Bt=t("#mf_StitchTear_W"))==null?void 0:Bt.value,(jt=t("#mf_StitchTearWRating"))==null?void 0:jt.value),(bt=t("#mf_Text3977"))!=null&&bt.value&&T.push(`
        <tr>
          <td class="text-left"><b>Mullen Bursting Strength</b></td>
          <td>kg/cm²</td>
          <td><b>${(ot=t("#mf_Text3977"))==null?void 0:ot.value}</b></td>
          <td>${((nt=t("#mf_MullenBurst"))==null?void 0:nt.value)||"-"}</td>
          <td><b>${((it=t("#mf_MullenBurstRating"))==null?void 0:it.value)||"-"}</b></td>
        </tr>
      `),(Ft=t("#mf_DieC_Ave"))!=null&&Ft.value&&T.push(`
        <tr>
          <td class="text-left"><b>Die C Tear / Edge Crush</b></td>
          <td>kg</td>
          <td><b>${(Gt=t("#mf_DieC_Ave"))==null?void 0:Gt.value}</b></td>
          <td>${((Lt=t("#mf_DieC_Std"))==null?void 0:Lt.value)||"-"}</td>
          <td><b>${(($t=t("#mf_DieC_Rate"))==null?void 0:$t.value)||"-"}</b></td>
        </tr>
      `),(Ct=t("#mf_WAve"))!=null&&Ct.value&&T.push(`
        <tr>
          <td class="text-left"><b>Fabric Weight (Grammage)</b></td>
          <td>g/m²</td>
          <td><b>${(wt=t("#mf_WAve"))==null?void 0:wt.value}</b></td>
          <td>${((Rt=t("#mf_W_Std"))==null?void 0:Rt.value)||"-"}</td>
          <td><b>${((At=t("#mf_WRating"))==null?void 0:At.value)||"-"}</b></td>
        </tr>
      `),(Et=t("#mf_CTPRating"))!=null&&Et.value&&T.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Perspiration (Face)</b></td>
          <td>Grade</td>
          <td>W:${((xt=t("#mf_CtPWool"))==null?void 0:xt.value)||"-"} A:${((Mt=t("#mf_CtPAcrylic"))==null?void 0:Mt.value)||"-"} P:${((Ot=t("#mf_CtPPolyester"))==null?void 0:Ot.value)||"-"} N:${((kt=t("#mf_CtPNylon"))==null?void 0:kt.value)||"-"} C:${((Yt=t("#mf_CtPCotton"))==null?void 0:Yt.value)||"-"} Ac:${((Zt=t("#mf_CtPAcetate"))==null?void 0:Zt.value)||"-"}</td>
          <td>${((te=t("#mf_CTP"))==null?void 0:te.value)||"4.0"}</td>
          <td><b>${(ee=t("#mf_CTPRating"))==null?void 0:ee.value}</b></td>
        </tr>
      `),(ae=t("#mf_CTWRating"))!=null&&ae.value&&T.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Water (Face)</b></td>
          <td>Grade</td>
          <td>W:${((se=t("#mf_CtWWool"))==null?void 0:se.value)||"-"} A:${((le=t("#mf_CtWAcrylic"))==null?void 0:le.value)||"-"} P:${((re=t("#mf_CtWPolyester"))==null?void 0:re.value)||"-"} N:${((oe=t("#mf_CtWNylon"))==null?void 0:oe.value)||"-"} C:${((ne=t("#mf_CtWCotton"))==null?void 0:ne.value)||"-"} Ac:${((ie=t("#mf_CtWAcetate"))==null?void 0:ie.value)||"-"}</td>
          <td>${((ce=t("#mf_CTW"))==null?void 0:ce.value)||"4.0"}</td>
          <td><b>${(de=t("#mf_CTWRating"))==null?void 0:de.value}</b></td>
        </tr>
      `),(fe=t("#mf_CTCDRYRating"))!=null&&fe.value&&T.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Dry)</b></td>
          <td>Grade</td>
          <td><b>${((pe=t("#mf_CtCDry"))==null?void 0:pe.value)||"-"}</b></td>
          <td>${((ue=t("#mf_CtCDry_S"))==null?void 0:ue.value)||"4.0"}</td>
          <td><b>${(me=t("#mf_CTCDRYRating"))==null?void 0:me.value}</b></td>
        </tr>
      `),(ve=t("#mf_CTCWETRating"))!=null&&ve.value&&T.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Wet)</b></td>
          <td>Grade</td>
          <td><b>${((_e=t("#mf_CtCWet"))==null?void 0:_e.value)||"-"}</b></td>
          <td>${((be=t("#mf_CtCWet_S"))==null?void 0:be.value)||"3.0"}</td>
          <td><b>${(he=t("#mf_CTCWETRating"))==null?void 0:he.value}</b></td>
        </tr>
      `),(()=>{const U=T.length?/^(tensile|elongation|trousertear|stitchtear|mullen|diec|ctp|ctw|ctcdry|ctcwet)/i:/^$/,ct={heataging:"Heat Aging (72 hrs, 70°C)",corrosion:"Corrosion Resistance",hydrolysis:"Hydrolysis (7 days, 60°C, 95% RH)",uv:"UV Light (24 hrs, 50°C)",ctcdry:"Colorfastness to Crocking (Dry)",ctcwet:"Colorfastness to Crocking (Wet)",ctw:"Colorfastness to Water",ctp:"Colorfastness to Perspiration",shrinkage12:"Shrinkage (1 hr, 70°C) 1-2",shrinkage34:"Shrinkage (1 hr, 70°C) 3-4",tensile:"Tensile",elongation:"Elongation",stitchtear:"Stitch Tear",peelstrength:"Peel Strength",shearstrength:"Shear Strength",laceTipRet:"Lace Tip Retention",knotslip:"Knot Slip",laceabrasion:"Lace Abrasion",hardness:"Hardness",sg:"Specific Gravity",pvc:"PVC Contact Discoloration"},dt={corrosion:["CorrosionRes"],ctcdry:["CtCDry"],ctcwet:["CtCWet"],shrinkage12:["Shrinkageave12"],shrinkage34:["Shrinkageave34"]},tt=et=>et.replace(/[.*+?^${}()|[\]\\]/g,"\\$&"),j=et=>{const H=String(et).trim().toUpperCase();return["P","PASS"].includes(H)?"PASS":["F","FAIL","FAILED"].includes(H)?"FAIL":H==="T"?"TOLERABLE":H},Pt=new Set;Object.keys(p).forEach(et=>{const H=et.match(/^(.+?)(?:_R|_?Rating|_?rating)$/),f=H&&String(p[et]??"").trim();if(!f)return;const _=H[1].replace(/txt/i,""),b=_.toLowerCase();if(_.length<3||Pt.has(b)||U.test(_))return;const S=[_,...dt[b]||[]],F=x=>/(_S|_std|Std|_Std|_R|Rating|_rating)$/.test(x),A=Object.keys(p).filter(x=>!F(x)&&S.some(Xt=>new RegExp("^"+tt(Xt)+"(?:txt)?(\\d*|_?ave)$","i").test(x))).map(x=>String(p[x]??"").trim()).filter(x=>x!==""&&!/^0(\.0+)?$/.test(x));if(!A.length)return;Pt.add(b);const P=Object.keys(p).find(x=>S.some(Xt=>new RegExp("^"+tt(Xt)+"(?:txt)?(_S|_std|Std|_Std)$","i").test(x))&&String(p[x]??"").trim()!==""),Qt=ct[b]||_.replace(/([a-z])([A-Z0-9])/g,"$1 $2"),D=j(f);T.push(`
          <tr>
            <td class="text-left"><b>${i(Qt)}</b></td>
            <td>-</td>
            <td><b>${A.map(i).join(" &nbsp; ")}</b></td>
            <td>${i(P?p[P]:"-")}</td>
            <td><b class="${D==="PASS"?"dtr-rate-pass":D==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${i(D)}</b></td>
          </tr>
        `)})})();const B=`
      <div class="dtr-report-sheet">
        <div class="rpt-logo-row">
          <img class="rpt-logo-vf" src="${J}assets/images/vf-logo.webp" alt="VF">
          <img class="rpt-logo-satra" src="${J}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
        </div>
        <div class="dtr-sheet-header">
          <div class="dtr-sheet-title">
            <h2>DATIAN SUBIC SHOES INC. (DTP)</h2>
            <h4>PHYSICAL TEST REPORT — ${i(s.toUpperCase())}</h4>
            <div style="font-size:11px;color:#64748b;margin-top:2px">Lot 1P7-A, Phase 2, Subic Bay Gateway Park, Subic Bay Freeport Zone</div>
          </div>
          <div class="dtr-sheet-meta">
            <div>Lab Ref: <b>${i(e)}</b></div>
            <div>TR #: <b>${i(a)}</b></div>
            <div style="margin-top:6px">
              <span class="dtr-sheet-stamp-box ${V}">${i(k)}</span>
            </div>
          </div>
        </div>

        <table class="dtr-sheet-info-grid">
          <tr>
            <td class="label-cell">Material Code</td>
            <td><b>${i(r)}</b></td>
            <td class="label-cell">Material Type</td>
            <td>${i(s)}</td>
            <td class="label-cell">Tested Date</td>
            <td>${i(L)}</td>
          </tr>
          <tr>
            <td class="label-cell">Description</td>
            <td colspan="3">${i(l)}</td>
            <td class="label-cell">PO / Lot No.</td>
            <td>${i(n)}</td>
          </tr>
          <tr>
            <td class="label-cell">Supplier / Factory</td>
            <td>${i(c)}</td>
            <td class="label-cell">Model / Style</td>
            <td>${i(o)}</td>
            <td class="label-cell">Color / MTL</td>
            <td>${i(d)} / ${i(m)}</td>
          </tr>
          <tr>
            <td class="label-cell">Thickness (TAve)</td>
            <td><b>${((ge=t("#mf_TAve"))==null?void 0:ge.value)||"-"} mm</b></td>
            <td class="label-cell">Weight (WAve)</td>
            <td><b>${((Te=t("#mf_WAve"))==null?void 0:Te.value)||"-"} g/m²</b></td>
            <td class="label-cell">Technician</td>
            <td>${i($||"-")}</td>
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
            ${T.length?T.join(""):'<tr><td colspan="5" class="muted">No test items recorded for this report.</td></tr>'}
          </tbody>
        </table>

        <div class="dtr-sheet-sign-row">
          <div class="dtr-sheet-sign-box">
            <div>Prepared by (Testing Technician):</div>
            <div class="dtr-sheet-sign-line">
              <span>${i($)}</span>
              <span class="muted">${i(L)}</span>
            </div>
          </div>
          <div class="dtr-sheet-sign-box">
            <div>Reviewed &amp; Approved by (QA Lab Head):</div>
            <div class="dtr-sheet-sign-line">
              <span>${i(g)}</span>
              <span class="muted">${i(M||L)}</span>
            </div>
          </div>
        </div>
      </div>
    `;t("#dtr-print-modal-title").textContent=`Official Test Report Sheet — ${e}`,t("#dtr-print-sheet-container").innerHTML=B,(Se=t("#dtr-print-modal-backdrop"))==null||Se.classList.remove("hidden")};let Ae=null;const ka=async()=>{if(Ae)return Ae;try{const e=await fetch(J+"assets/images/bonding_sketches/sketch_configs.json");if(e.ok)return Ae=await e.json(),Ae}catch(e){console.warn("Failed to load sketch_configs.json",e)}return null},Pa=async()=>{var $t,Ct,wt,Rt,At,Et,xt,Mt,Ot,kt,Yt,Zt,te,ee,ae,se,le,re,oe,ne,ie,ce,de,fe,pe,ue,me,ve,_e,be,he,ge,Te,Se,U,ct,dt,tt,j,Pt,et,H;const e=parseInt((($t=t("#bf_BondingTestID"))==null?void 0:$t.value)||(v==null?void 0:v.BondingTestID)||0)||0,a=((Ct=t("#bf_LabRefNo"))==null?void 0:Ct.value)||"DTP-BOND-PREVIEW",s=((wt=t("#bf_ETRFNo"))==null?void 0:wt.value)||"-",r=((Rt=t("#bf_ModelStyle"))==null?void 0:Rt.value)||"-",l=((At=t("#bf_Color"))==null?void 0:At.value)||"-",c=((Et=t("#bf_Side"))==null?void 0:Et.value)||"Left",o=c.toLowerCase()==="left",d=(((xt=t("#bf_AgeCategory"))==null?void 0:xt.value)||"ADULT").toUpperCase(),n=parseFloat(((Mt=t("#bf_STD"))==null?void 0:Mt.value)||"2.5")||2.5,m=((Ot=t("#bf_POLotNO"))==null?void 0:Ot.value)||"-",p=((kt=t("#bf_POQuantity"))==null?void 0:kt.value)||"-",u=((Yt=t("#bf_Factory"))==null?void 0:Yt.value)||"DATIAN SUBIC SHOES INC. (DTP)",C=((Zt=t("#bf_AdhesiveSupplier"))==null?void 0:Zt.value)||"-",L=((te=t("#bf_ShippedDate"))==null?void 0:te.value)||"-",$=((ee=t("#bf_ProductionDate"))==null?void 0:ee.value)||"-",g=((ae=t("#bf_Sendby"))==null?void 0:ae.value)||"-",M=((se=t("#bf_MaterialSource"))==null?void 0:se.value)||"Prod. Shoes",k=((le=t("#bf_ModeOfFailure"))==null?void 0:le.value)||"-",V=((re=t("#bf_PossibleCause"))==null?void 0:re.value)||"-",T=((oe=t("#bf_Remarks"))==null?void 0:oe.value)||((ne=t("#bf_LabNotesComments"))==null?void 0:ne.value)||"",O=((ie=t("#bf_ReceivedDate"))==null?void 0:ie.value)||"-",B=((ce=t("#bf_TestedDate"))==null?void 0:ce.value)||new Date().toISOString().slice(0,10),G=((de=t("#bf_FinishedDate"))==null?void 0:de.value)||B,at=((fe=t("#bf_Technician"))==null?void 0:fe.value)||"L. Aquidado",mt=((pe=t("#bf_ReviewedBy"))==null?void 0:pe.value)||"QA Lead",z=(((ue=t("#bf_Result"))==null?void 0:ue.value)||"PASS").toUpperCase(),st=z==="PASS"?"":z==="TOLERABLE"?"stamp-tol":"stamp-fail",K=((me=t("#bp_UTFMin"))==null?void 0:me.value)||(v==null?void 0:v.UTFMin)||"-",lt=((ve=t("#bp_UTFMax"))==null?void 0:ve.value)||(v==null?void 0:v.UTFMax)||"-",ht=((_e=t("#bp_UTFAverage"))==null?void 0:_e.value)||(v==null?void 0:v.UTFAverage)||"-",zt=(((be=t("#bp_UTF_Result"))==null?void 0:be.value)||(v==null?void 0:v.UTF_Result)||"-").toUpperCase(),Nt=((he=t("#bp_FTOMin"))==null?void 0:he.value)||(v==null?void 0:v.FTOMin)||"-",Wt=((ge=t("#bp_FTOMax"))==null?void 0:ge.value)||(v==null?void 0:v.FTOMax)||"-",Jt=((Te=t("#bp_FTOAverage"))==null?void 0:Te.value)||(v==null?void 0:v.FTOAverage)||"-",It=(((Se=t("#bp_FTO_Result"))==null?void 0:Se.value)||(v==null?void 0:v.FTO_Result)||"-").toUpperCase(),Ut=((U=t("#bp_FiTOMin"))==null?void 0:U.value)||(v==null?void 0:v.FiTOMin)||"-",gt=((ct=t("#bp_FiTOMax"))==null?void 0:ct.value)||(v==null?void 0:v.FiTOMax)||"-",Kt=((dt=t("#bp_FiTOAverage"))==null?void 0:dt.value)||(v==null?void 0:v.FiTOAverage)||"-",qt=(((tt=t("#bp_FiTO_Result"))==null?void 0:tt.value)||(v==null?void 0:v.FiTO_Result)||"-").toUpperCase(),q={},Y={},rt={};let Tt=0;for(let f=1;f<=35;f++){const _=((j=t("#bp_UTFpoint"+f))==null?void 0:j.value)||(v==null?void 0:v["UTFpoint"+f])||"",b=((Pt=t("#bp_FTOpoint"+f))==null?void 0:Pt.value)||(v==null?void 0:v["FTOpoint"+f])||"";q[f]=_,Y[f]=b,(parseFloat(_)>0||parseFloat(b)>0)&&(Tt=f),f<=13&&(rt[f]=((et=t("#bp_FiTO"+f))==null?void 0:et.value)||(v==null?void 0:v["FiTO"+f])||"")}const vt=await ka();let Z=parseInt((v==null?void 0:v.MaxPoints)||Tt||27);(!vt||!vt[Z])&&(Z=[26,27,28,29,30,31,32,33,34].reduce((_,b)=>Math.abs(b-Z)<Math.abs(_-Z)?b:_,27));let y=vt?vt[Z]:null,St=[],Q=null;if(y)try{const f=await N("dtr/layout?slug=bonding&variant="+encodeURIComponent(Z)),_=f&&f.layout;_&&(y=JSON.parse(JSON.stringify(y)),_.image&&Object.assign(y.image,_.image),["utf","fto"].forEach(b=>{Object.keys(_[b]||{}).forEach(S=>{_[b][S].hidden?delete y[b][S]:y[b][S]=_[b][S]})}),St=_.extras||[],(_.vector||_.start)&&(Q=_),_.vector&&(y.image.file=_.imageFile||"blank.png"))}catch{}const X=(f,_,b=!1)=>{const S=je(parseFloat(f),b?we:_);return S?S==="FAIL"?"color:#dc2626;font-weight:800;":S==="TOLERABLE"?"color:#d97706;font-weight:800;":"color:#1d4ed8;font-weight:800;":"color:#94a3b8"};let I="";if(y){I+=`
        <img src="${J}assets/images/bonding_sketches/${y.image.file}" 
             class="vf-shoe-img" 
             style="left:${y.image.pctLeft}%; top:${y.image.pctTop}%; width:${y.image.pctWidth}%; height:${y.image.pctHeight}%;" 
             alt="${c} Shoe Sketch">
      `;for(let f=1;f<=y.pointsCount;f++){const _=y.utf[f];if(_){const S=q[f],F=parseFloat(S),A=Number.isFinite(F)&&F>0,P=X(S,n,!1);I+=`
            <div class="vf-point-box vf-point-utf" 
                 style="left:${_.pctLeft}%; top:${_.pctTop}%; width:${_.pctWidth}%; height:${_.pctHeight}%; ${P}" 
                 title="UTF Point ${f}: ${A?F.toFixed(2):"-"}">
              ${A?F.toFixed(2):""}
            </div>
          `}const b=y.fto[f];if(b){const S=Y[f],F=parseFloat(S),A=Number.isFinite(F)&&F>0,P=X(S,n,!1);I+=`
            <div class="vf-point-box vf-point-fto" 
                 style="left:${b.pctLeft}%; top:${b.pctTop}%; width:${b.pctWidth}%; height:${b.pctHeight}%; ${P}" 
                 title="FTO Point ${f}: ${A?F.toFixed(2):"-"}">
              ${A?F.toFixed(2):""}
            </div>
          `}}if(Q){let f='<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none">',_="";if(Q.vector&&["utf","fto"].forEach(b=>Object.keys(y[b]).forEach(S=>{const F=y[b][S],A=((Q.dots||{})[b]||{})[S];if(!A)return;const P=F.pctLeft+F.pctWidth,Qt=F.pctTop+F.pctHeight/2,D=A.x<F.pctLeft?F.pctLeft:A.x>P?P:F.pctLeft+F.pctWidth/2,x=D===F.pctLeft||D===P?Qt:A.y<F.pctTop?F.pctTop:F.pctTop+F.pctHeight,Xt=b==="utf"?"#2563eb":"#7c3aed";f+=`<line x1="${D}" y1="${x}" x2="${A.x}" y2="${A.y}" stroke="${Xt}" stroke-width="1.2" vector-effect="non-scaling-stroke"/>`,_+=`<div style="position:absolute;left:${A.x}%;top:${A.y}%;width:7px;height:7px;margin:-3.5px 0 0 -3.5px;border-radius:50%;background:${Xt}"></div>`})),Q.start){const b=Q.start;_+=`<div style="position:absolute;left:${b.x}%;top:${b.y}%;transform:translate(-50%,-50%);display:flex;align-items:center;gap:3px;white-space:nowrap;color:#b91c1c;font:800 9px sans-serif"><i style="display:block;width:9px;height:9px;background:#dc2626;transform:rotate(45deg)"></i>${i(b.label||"START")}</div>`}I+=f+"</svg>"+_}St.forEach(f=>{var A;const _=`left:${f.pctLeft}%; top:${f.pctTop}%; width:${f.pctWidth}%; height:${f.pctHeight}%;`,b=`color:${i(f.color||"#111")}; font-size:${Number(f.size)||12}px; font-weight:${f.bold?700:400};`;let S="";if(f.type==="text")S=i(f.text);else if(f.type==="field")S=i(((A=t("#bf_"+f.field))==null?void 0:A.value)||"");else if(f.type==="image"&&f.src)S=`<img src="${J}${i(f.src)}" style="max-width:100%;max-height:100%" alt="">`;else if(f.type==="chart"){let P=0;for(let D=1;D<=y.pointsCount;D++)P=Math.max(P,parseFloat(q[D])||0);let Qt="";for(let D=1;D<=y.pointsCount;D++){const x=parseFloat(q[D])||0;Qt+=`<i style="flex:1;display:block;min-height:1px;height:${P?x/P*100:0}%;background:${x<2?"#dc2626":x<3?"#d97706":"#1d4ed8"}"></i>`}S=`<div style="display:flex;align-items:flex-end;gap:1px;width:100%;height:100%">${Qt}</div>`}const F=f.type==="line"?`background:${i(f.color||"#111")};`:"";I+=`<div style="position:absolute;display:flex;align-items:center;overflow:hidden;${_}${b}${F}">${S}</div>`})}else I='<div style="padding:20px;text-align:center;color:#64748b">Shoe sketch unavailable</div>';const yt=`
      <img src="${J}assets/images/bonding_sketches/blank.png" 
           class="vf-shoe-img" 
           style="left:12%; top:8%; width:76%; height:84%; opacity:0.85;" 
           alt="Unmeasured Shoe Contour">
    `;let _t="";for(let f=1;f<=13;f++){const _=rt[f],b=parseFloat(_),S=Number.isFinite(b)&&b>0,F=X(_,1,!0);_t+=`
        <div class="vf-center-item" style="${F}">
          ${S?b.toFixed(2):"&nbsp;"}
        </div>
      `}const Ht=o?I:yt,Vt=o?yt:I,Bt=o?_t:"",jt=o?"":_t,bt=f=>f==="PASS"?{pass:"PASS",fail:"",passCls:"vf-rate-pass",failCls:""}:f==="TOLERABLE"?{pass:"TOLE",fail:"",passCls:"vf-rate-tol",failCls:""}:f==="FAIL"||f==="FAILED"?{pass:"",fail:"FAIL",passCls:"",failCls:"vf-rate-fail"}:{pass:"-",fail:"",passCls:"",failCls:""},ot=bt(zt),nt=bt(It),it=bt(qt);let Ft="";for(let f=1;f<=(y?y.pointsCount:27);f++){const _=q[f]?parseFloat(q[f]).toFixed(2):"-",b=Y[f]?parseFloat(Y[f]).toFixed(2):"-",S=f<=13&&rt[f]?parseFloat(rt[f]).toFixed(2):"-";Ft+=`
        <tr>
          <td><b>Point ${f}</b></td>
          <td style="${X(q[f],n,!1)}">${_}</td>
          <td style="${X(Y[f],n,!1)}">${b}</td>
          <td style="${f<=13?X(rt[f],1,!0):"color:#94a3b8"}">${S}</td>
        </tr>
      `}const Gt=`
      <div class="vf-report-sheet">
        <!-- Row 1: VF logo | test method name | SATRA logo, all on one line -->
        <div class="rpt-top">
          <img class="rpt-logo-vf" src="${J}assets/images/vf-logo.webp" alt="VF">
          <div class="rpt-top-title">
            <h1 class="vf-title-en">Bonding Test Results</h1>
            <h2 class="vf-title-cn">胶着力测试</h2>
          </div>
          <img class="rpt-logo-satra" src="${J}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
        </div>

        <!-- Row 2: form no / ETRF / lab ref + sample category (left), result + dates (right) -->
        <div class="rpt-sub">
          <div class="rpt-sub-left">
            <div class="vf-form-no rpt-formno">Form # : Lab VF 001</div>
            <div class="vf-etrf-row">
              <span>ETRF No:</span>
              <span class="vf-val-underlined">${i(s)}</span>
            </div>
            <div class="vf-labref-row">
              <span>Lab Ref.No:</span>
              <span class="vf-labref-val">${i(a)}</span>
            </div>
            <div class="vf-test-categories">
              <div>
                <span class="vf-radio ${M==="Prod. Shoes"||!M?"checked":""}"></span> Pro. Shoes 量产鞋
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${M==="Pro. Re-test"?"checked":""}"></span> Pro. Re-test 量产重测
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${M==="NewDevelopment"?"checked":""}"></span> NewDevelopment 开发
              </div>
              <div class="vf-tech-subradios">
                <span>○ Tech 1</span> <span>○ Tech 2</span> <span>○ Tech 3</span> <span>○ Tech 4</span>
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${M==="Sales Sample"?"checked":""}"></span> Sales Sample 销样
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${M==="Trial experiment"?"checked":""}"></span> 试胶试验
              </div>
            </div>
          </div>

          <div class="rpt-sub-right">
            <div class="vf-stamp-area">
              <span class="vf-stamp ${st}">${i(z)}</span>
            </div>
            <table class="vf-dates-table">
              <tr><td>Received Date 收到日期:</td><td class="vf-date-val">${i(O)}</td></tr>
              <tr><td>Tested Date 测试日期:</td><td class="vf-date-val">${i(B)}</td></tr>
              <tr><td>Finished Date 完成日期:</td><td class="vf-date-val">${i(G)}</td></tr>
              <tr><td>Technician 技术员:</td><td class="vf-date-val">${i(at)}</td></tr>
            </table>
          </div>
        </div>

        <!-- Factory & Spec Details Grid -->
        <table class="vf-meta-grid">
          <tr>
            <td class="vf-lbl">Factory (工厂):</td>
            <td class="vf-val" colspan="3">${i(u)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Adhesive supplier 胶水供应商:</td>
            <td class="vf-val">${i(C)}</td>
            <td class="vf-lbl">Shipped Date (出货日期):</td>
            <td class="vf-val">${i(L)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Model/Style/PGSC (英文鞋型):</td>
            <td class="vf-val">${i(r)}</td>
            <td class="vf-lbl">Color (颜色):</td>
            <td class="vf-val">${i(l)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Production Date (生产日期):</td>
            <td class="vf-val">${i($!=="-"?$:B)}</td>
            <td class="vf-lbl">PO/Lot No (订单号码):</td>
            <td class="vf-val">${i(m)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">PO Quantity (订单数量):</td>
            <td class="vf-val">${i(p)}</td>
            <td class="vf-lbl">Send by (送达):</td>
            <td class="vf-val">${i(g)}</td>
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
              <td class="font-bold text-blue">${n}<br><span class="vf-tol">(≥ ${Re(n)})</span></td>
              <td class="font-bold text-blue">${K!=="-"?Number(K).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${lt!=="-"?Number(lt).toFixed(2):"-"}</td>
              <td><span class="${ot.passCls}">${ot.pass}</span></td>
              <td><span class="${ot.failCls}">${ot.fail}</span></td>
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
              <td class="font-bold text-blue">${n}<br><span class="vf-tol">(≥ ${Re(n)})</span></td>
              <td class="font-bold text-blue">${Nt!=="-"?Number(Nt).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${Wt!=="-"?Number(Wt).toFixed(2):"-"}</td>
              <td><span class="${nt.passCls}">${nt.pass}</span></td>
              <td><span class="${nt.failCls}">${nt.fail}</span></td>
            </tr>
            <tr>
              <td class="text-left font-bold">Center : IS/OS<br>(中插 / 大底)</td>
              <td>VF-F01<br><span style="font-size:9.5px">(2019/05)</span></td>
              <td class="font-bold text-blue">${we}<br><span class="vf-tol">(≥ ${Re(we)})</span></td>
              <td class="font-bold text-blue">${Ut!=="-"?Number(Ut).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${gt!=="-"?Number(gt).toFixed(2):"-"}</td>
              <td><span class="${it.passCls}">${it.pass}</span></td>
              <td><span class="${it.failCls}">${it.fail}</span></td>
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
              <span style="${o?"color:#1d4ed8;font-weight:800":""}">Left</span>
              <span style="${o?"":"color:#1d4ed8;font-weight:800"}">Right</span>
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
            ${Ht}
          </div>

          <!-- Center Boxes -->
          <div class="vf-center-card">
            <div class="vf-center-cols">
              <div class="vf-center-box" style="${o?"border-color:#1d4ed8;background:#f8fafc":"border-color:#cbd5e1"}">
                ${Bt}
              </div>
              <div class="vf-center-box" style="${o?"border-color:#cbd5e1":"border-color:#1d4ed8;background:#f8fafc"}">
                ${jt}
              </div>
            </div>
          </div>

          <!-- Right Shoe Card -->
          <div class="vf-shoe-card">
            ${Vt}
          </div>
        </div>

        <!-- Optional Collapsible Data Table -->
        <details class="no-print" style="margin-top:8px;font-size:11px;border:1px solid #cbd5e1;border-radius:4px;padding:6px 10px;background:#f8fafc">
          <summary style="cursor:pointer;font-weight:700;color:#334155">
            <svg class="ic" aria-hidden="true"><use href="#i-chart"/></svg> View Tabular Spot Values (${y?y.pointsCount:27} Points)
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
              ${Ft}
            </tbody>
          </table>
        </details>

        <!-- Signatures & Notes Footer -->
        <div class="vf-footer-grid">
          <div>
            Comments 备注:
            <div style="font-weight:normal;color:#334155;margin-top:4px;font-size:10.5px">
              ${i(T||"Tested in accordance with VF Laboratory Standards.")}
            </div>
          </div>
          <div>
            Reviewed by 审查:
            <div class="vf-sign-line">
              <span>${i(mt)}</span>
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
    `;t("#dtr-print-modal-title").textContent=`Whole Shoe Bonding Test Report — ${a} (${c})`,t("#dtr-print-sheet-container").innerHTML=Gt;const Lt=t("#btn-dtr-download-access-pdf");Lt&&Lt.classList.add("hidden"),(H=t("#dtr-print-modal-backdrop"))==null||H.classList.remove("hidden")},We=e=>{const a=748.3464566929134,s=285/25.4*96,r=document.createElement("div");r.className="dtr-fit-probe",r.style.cssText="position:absolute;left:-99999px;top:0;visibility:hidden;",document.body.appendChild(r);let l=1;for(let c=0;c<4;c++){r.style.width=a/l+"px",r.innerHTML=e;const o=r.scrollHeight*l,d=Math.min(1,l*(s/Math.max(o,1))*.985);if(Math.abs(d-l)<.005){l=d;break}l=d}return r.remove(),Math.max(.4,Math.round(l*1e3)/1e3)};(La=t("#btn-dtr-print-preview"))==null||La.addEventListener("click",Oa),($a=t("#btn-bond-print-preview"))==null||$a.addEventListener("click",Pa),(Ca=t("#btn-dtr-close-print-modal"))==null||Ca.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(wa=t("#btn-dtr-cancel-print"))==null||wa.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(Ra=t("#btn-dtr-download-pdf"))==null||Ra.addEventListener("click",async()=>{var r,l,c,o,d,n;const e=t("#btn-dtr-download-pdf"),a=t("#dtr-print-sheet-container");if(!a||!e)return;const s=e.textContent;e.disabled=!0,e.textContent="Creating PDF...";try{const m=await fetch(J+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((r=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:r.content)||""},body:JSON.stringify({access_id:parseInt(((c=(((l=t("#btn-dtr-download-access-pdf"))==null?void 0:l.href)||"").match(/[?&]id=(\d+)/))==null?void 0:c[1])||0,10)&&!t("#btn-dtr-download-access-pdf").classList.contains("hidden")?parseInt(t("#btn-dtr-download-access-pdf").href.match(/[?&]id=(\d+)/)[1],10):0,side:((((o=t("#btn-dtr-download-access-pdf"))==null?void 0:o.href)||"").match(/side=(\w+)/)||[])[1]||"Left",scale:We(a.innerHTML),html:a.innerHTML,name:(((d=t("#dtr-print-modal-title"))==null?void 0:d.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_")})});if(!m.ok)throw new Error((await m.text()).slice(0,200)||"PDF failed");const p=await m.blob(),u=document.createElement("a");u.href=URL.createObjectURL(p),u.download=((((n=t("#dtr-print-modal-title"))==null?void 0:n.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_").trim()||"Report")+".pdf",document.body.appendChild(u),u.click(),u.remove(),setTimeout(()=>URL.revokeObjectURL(u.href),4e3)}catch(m){nxToast("Could not create the PDF: "+m.message,"error",7e3)}e.disabled=!1,e.textContent=s}),(Aa=t("#btn-dtr-do-print"))==null||Aa.addEventListener("click",()=>{const e=t("#dtr-print-sheet-container");if(!e){window.print();return}const a=document.createElement("div");a.id="dtr-print-root",a.innerHTML=e.innerHTML,a.style.zoom=String(We(e.innerHTML)),document.body.appendChild(a),document.body.classList.add("dtr-printing");const s=()=>{document.body.classList.remove("dtr-printing"),a.remove(),window.removeEventListener("afterprint",s)};window.addEventListener("afterprint",s),window.print()});const Da=async()=>{var e,a;try{const s=await N("dtr/layout?slug=bonding&variant=form"),r=s&&s.layout,l=t("#dtr-bonding-form");if(!r||!l)return;const c=d=>{var n;return(n=l.querySelector(`[name="${d}"]`))==null?void 0:n.closest(".dtr-field")};(r.customFields||[]).forEach(d=>{var p,u;if(l.querySelector(`[name="${d.key}"]`))return;const n=(u=(p=t("#bf_LabRefNo"))==null?void 0:p.closest(".dtr-field"))==null?void 0:u.parentElement;if(!n)return;const m=document.createElement("div");m.className="dtr-field",m.innerHTML=`<label>${i(d.label)}</label><input type="text" name="${i(d.key)}" id="bf_${i(d.key)}">`,n.appendChild(m)}),Object.entries(r.labels||{}).forEach(([d,n])=>{const m=c(d),p=m&&m.querySelector("label");p&&n&&(p.textContent=n)});const o=(a=(e=t("#bf_LabRefNo"))==null?void 0:e.closest(".dtr-field"))==null?void 0:a.parentElement;o&&(r.order||[]).length&&(r.order.forEach(d=>{const n=c(d);n&&n.parentElement===o&&o.appendChild(n)}),(r.customFields||[]).forEach(d=>{const n=c(d.key);n&&o.appendChild(n)})),(r.hiddenFields||[]).forEach(d=>{const n=c(d);n&&(n.style.display="none")})}catch{}};Ge(),Le("fabric"),$e("fabric",0),Da().then(()=>{Pe(),De(0)}),Ne(),(()=>{const e=new URLSearchParams(location.search),[a,s]=String(e.get("open")||"").split(":");!a||!/^[a-z]+$/.test(a)||!/^\d+$/.test(s||"")||setTimeout(async()=>{var c,o;const r=t("#dtr-tracker-tbody")||document.body,l=document.createElement("button");if(l.type="button",l.hidden=!0,l.dataset.openRecord=a,l.dataset.sourceId=s,r.appendChild(l),l.click(),l.remove(),e.get("preview")==="1"&&(await new Promise(d=>setTimeout(d,2200)),(c=t(a==="bonding"?"#btn-bond-print-preview":"#btn-dtr-print-preview"))==null||c.click()),e.get("export")==="1"&&window.parent!==window){const d=n=>window.parent.postMessage(Object.assign({type:"NXL_REPORT_PDF"},n),location.origin);try{await new Promise($=>setTimeout($,800));const n=t("#dtr-print-sheet-container");if(!n||!n.innerHTML.trim())throw new Error("The report sheet could not be prepared.");const m=t("#btn-dtr-download-access-pdf"),p=m&&!m.classList.contains("hidden")?parseInt(((m.href||"").match(/[?&]id=(\d+)/)||[])[1]||0,10):0,u=await fetch(J+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((o=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:o.content)||""},body:JSON.stringify({access_id:p,side:(((m==null?void 0:m.href)||"").match(/side=(\w+)/)||[])[1]||"Left",scale:We(n.innerHTML),html:n.innerHTML,name:"Report"})});if(!u.ok)throw new Error((await u.text()).slice(0,200)||"PDF failed");const C=new Uint8Array(await u.arrayBuffer());let L="";for(let $=0;$<C.length;$+=32768)L+=String.fromCharCode.apply(null,C.subarray($,$+32768));d({ok:!0,b64:btoa(L)})}catch(n){d({ok:!1,error:n.message})}}if(e.get("export")==="html"&&window.parent!==window){const d=n=>window.parent.postMessage(Object.assign({type:"NXL_REPORT_HTML"},n),location.origin);try{await new Promise(m=>setTimeout(m,1200));const n=t("#dtr-print-sheet-container");if(!n||!n.innerHTML.trim())throw new Error("The report sheet could not be prepared.");d({ok:!0,html:n.innerHTML})}catch(n){d({ok:!1,error:n.message})}}},900)})()})();
