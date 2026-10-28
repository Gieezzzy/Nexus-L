(()=>{"use strict";var He,Be,je,Ge,ze,qe,Qe,Xe,Je,Ye,Ke,Ze,ta,ea,aa,sa,la,oa,ra,ia,na,ca,da,fa,ua,pa,ma,va,_a,ba,ha,ga,Ta,ya,Fa,Sa,La,$a;const t=(e,a=document)=>a.querySelector(e),w=(e,a=document)=>[...a.querySelectorAll(e)],Ne=t('meta[name="base-url"]'),I=Ne?Ne.content.replace(/\/+$/,"")+"/":location.origin+"/";let lt="fabric",We={},v={};const C=(e,a=!1)=>{const s=t("#dtr-toast");s&&(s.textContent=e,s.style.background=a?"#dc2626":"#0f172a",s.classList.remove("hidden"),clearTimeout(s._timer),s._timer=setTimeout(()=>s.classList.add("hidden"),4200))},n=e=>String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"),W=async(e,a={})=>(await fetch(I+e.replace(/^\/+/,""),a)).json(),g=(e,a=2)=>{const s=Number(e);if(!Number.isFinite(s))return 0;const o=Math.pow(10,a);return Math.round((s+Number.EPSILON)*o)/o},A=e=>{if(!e)return;const a=String(e.value||"").trim().toUpperCase();e.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),a==="PASS"?e.classList.add("dtr-rate-pass"):a==="TOLERABLE"?e.classList.add("dtr-rate-warn"):(a==="FAIL"||a==="ERROR")&&e.classList.add("dtr-rate-fail")},ot=e=>{w("[data-dtr-tab]").forEach(a=>{a.classList.toggle("active",a.dataset.dtrTab===e)}),["tracker","material","bonding","calculators"].forEach(a=>{var s;(s=t(`#dtr-panel-${a}`))==null||s.classList.toggle("hidden",a!==e)})};w("[data-dtr-tab]").forEach(e=>{e.addEventListener("click",()=>ot(e.dataset.dtrTab))}),w("[data-goto-calc]").forEach(e=>{e.addEventListener("click",()=>{ot("calculators");const a=t(`#calc-card-${e.dataset.gotoCalc}`);a&&a.scrollIntoView({behavior:"smooth",block:"center"})})});const Pe=async(e="all",a="")=>{const s=t("#dtr-tracker-tbody");try{s&&window.nxState&&nxState(s,"loading","Loading lab reports…",16);const o=await W(`dtr/records?slug=${encodeURIComponent(e)}&q=${encodeURIComponent(a)}`);if(!s||!o.rows)return;if(!o.rows.length){nxState(s,"empty","No lab reports match your search. Clear the filters or try another Lab Ref No.",16);return}s.innerHTML=o.rows.map(l=>{const i=String(l.overall_result||"PASS").toUpperCase(),r=i==="FAIL"?"b-err":i==="TOLERABLE"?"b-warn":"b-ok";return`
          <tr>
            <td style="white-space:nowrap">
              <button type="button" class="dtr-btn-xs dtr-btn-primary" data-open-record="${n(l.form_slug)}" data-source-id="${n(l.source_id)}" data-labref="${n(l.lab_ref_no)}">
                Open Form
              </button>
            </td>
            <td class="mono"><b>${n(l.lab_ref_no)}</b></td>
            <td class="mono">${n(l.tr_no||"-")}</td>
            <td><span class="dtr-tag">${n(l.form_category)}</span></td>
            <td>${n(l.material_type||"-")}</td>
            <td class="mono">${n(l.material_code||"-")}</td>
            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${n(l.material_description||"")}">${n(l.material_description||"-")}</td>
            <td>${n(l.supplier||"-")}</td>
            <td class="mono">${n(l.model_style||"-")}</td>
            <td>${n(l.color||"-")}</td>
            <td class="mono">${n(l.po_lot_no||"-")}</td>
            <td class="mono">${n(l.mtl_no||"-")}</td>
            <td>${n(l.tested_date||l.received_date||"-")}</td>
            <td>${n(l.technician||"-")}</td>
            <td>${n(l.status||"Complete")}</td>
            <td><span class="badge ${r}">${n(i)}</span></td>
          </tr>
        `}).join("")}catch(o){console.error(o),s&&window.nxState&&nxState(s,"error","The list could not be loaded. Check your connection and try again.",16)}};(He=t("#dtr-tracker-pills"))==null||He.addEventListener("click",e=>{var o;const a=e.target.closest("[data-slug]");if(!a)return;w("#dtr-tracker-pills .dtr-pill").forEach(l=>l.classList.remove("active")),a.classList.add("active");const s=((o=t("#dtr-tracker-search"))==null?void 0:o.value.trim())||"";Pe(a.dataset.slug,s)}),(Be=t("#dtr-tracker-btn-search"))==null||Be.addEventListener("click",()=>{var o;const e=t("#dtr-tracker-pills .dtr-pill.active"),a=e?e.dataset.slug:"all",s=((o=t("#dtr-tracker-search"))==null?void 0:o.value.trim())||"";Pe(a,s)}),(je=t("#dtr-tracker-search"))==null||je.addEventListener("keydown",e=>{var a;e.key==="Enter"&&(e.preventDefault(),(a=t("#dtr-tracker-btn-search"))==null||a.click())}),(Ge=t("#dtr-tracker-tbody"))==null||Ge.addEventListener("click",async e=>{const a=e.target.closest("[data-open-record]");if(!a)return;const s=a.dataset.openRecord,o=a.dataset.sourceId;if(s==="bonding")ot("bonding"),await xe(o),await Me(o);else{ot("material");const l=t("#dtr-mat-form-slug");l&&(l.value=s),lt=s,await ye(s,o),await Fe(s,o)}});const we=(e=!0)=>{var r,d;const a=[];for(let c=1;c<=6;c++){const p=parseFloat(((r=t(`#mf_Thickness${c}`))==null?void 0:r.value)??"");Number.isFinite(p)&&p>0&&a.push(p)}if(!a.length)return;const s=Math.min(...a),o=Math.max(...a),l=g(a.reduce((c,p)=>c+p,0)/a.length,2);t("#mf_TMin")&&(t("#mf_TMin").value=s),t("#mf_TMax")&&(t("#mf_TMax").value=o),t("#mf_TAve")&&(t("#mf_TAve").value=l);const i=parseFloat(((d=t("#mf_T_S"))==null?void 0:d.value)??"");Number.isFinite(i)&&i>0&&t("#mf_TRating")&&(t("#mf_TRating").value=l>=i?"PASS":"FAIL",A(t("#mf_TRating"))),e&&Te()},rt=()=>{var r,d,c,p;const e=[1,2,3].map(u=>{var m;return parseFloat(((m=t(`#mf_Weight${u}`))==null?void 0:m.value)??"")}).filter(u=>Number.isFinite(u)&&u>0);if(e.length>0&&t("#mf_WAve")){const u=g(e.reduce((R,L)=>R+L,0)/e.length,2);t("#mf_WAve").value=u;const m=parseFloat(((r=t("#mf_Weight_S"))==null?void 0:r.value)??"");Number.isFinite(m)&&m>0&&t("#mf_WRating")&&(t("#mf_WRating").value=u>=m?"PASS":"FAIL",A(t("#mf_WRating")))}const a=[1,2,3].map(u=>{var m;return parseFloat(((m=t(`#mf_SG${u}`))==null?void 0:m.value)??"")}).filter(u=>Number.isFinite(u)&&u>0);if(a.length>0&&t("#mf_SG_Ave")){const u=g(a.reduce((R,L)=>R+L,0)/a.length,2);t("#mf_SG_Ave").value=u;const m=parseFloat(((d=t("#mf_SG_Std"))==null?void 0:d.value)??"");Number.isFinite(m)&&m>0&&t("#mf_SG_Rate")&&(t("#mf_SG_Rate").value=Math.abs(u-m)<=.02?"PASS":"FAIL",A(t("#mf_SG_Rate")))}const s=[1,2,3].map(u=>{var m;return parseFloat(((m=t(`#mf_DIN_${u}`))==null?void 0:m.value)??"")}).filter(u=>Number.isFinite(u)&&u>0);s.length>0&&t("#mf_DIN_Rate")&&(t("#mf_DIN_Rate").value=s.some(u=>u>150)?"FAIL":"PASS",A(t("#mf_DIN_Rate")));const o=[1,2].map(u=>{var m;return parseFloat(((m=t(`#mf_RossFlex_${u}`))==null?void 0:m.value)??"")}).filter(u=>Number.isFinite(u)&&u>0);o.length>0&&t("#mf_RossFlex_Rate")&&(t("#mf_RossFlex_Rate").value=o.some(u=>u>50)?"FAIL":"PASS",A(t("#mf_RossFlex_Rate")));const l=parseFloat(((c=t("#mf_ShrinkageL"))==null?void 0:c.value)??""),i=parseFloat(((p=t("#mf_Shrinkage_S"))==null?void 0:p.value)??"");Number.isFinite(l)&&Number.isFinite(i)&&i>0&&t("#mf_ShrinkageLRating")&&(t("#mf_ShrinkageLRating").value=Math.abs(l)<=Math.abs(i)?"PASS":"FAIL",A(t("#mf_ShrinkageLRating")))},De={TensileL:{inputs:["mf_TensileL1","mf_TensileL2","mf_TensileL3"],ave:"mf_TensileLAve",std:"mf_Tensile_L",rate:"mf_TensileLRating",dec:2},TensileW:{inputs:["mf_TensileW1","mf_TensileW2","mf_TensileW3"],ave:"mf_TensileWAve",std:"mf_Tensile_W",rate:"mf_TensileWRating",dec:2},ElongationL:{inputs:["mf_ElongationL1","mf_ElongationL2","mf_ElongationL3"],ave:"mf_ElongationLAve",std:"mf_Elongation_L",rate:"mf_ElongationLRating",dec:2},ElongationW:{inputs:["mf_ElongationW1","mf_ElongationW2","mf_ElongationW3"],ave:"mf_ElongationWAve",std:"mf_Elongation_W",rate:"mf_ElongationWRating",dec:2},TrouserTearL:{inputs:["mf_TrouserTearL1","mf_TrouserTearL2","mf_TrouserTearL3"],ave:"mf_TrouserTearLAve",std:"mf_TrouserTear_L",rate:"mf_TrouserTearLRating",dec:2},TrouserTearW:{inputs:["mf_TrouserTearW1","mf_TrouserTearW2","mf_TrouserTearW3"],ave:"mf_TrouserTearWAve",std:"mf_TrouserTear_W",rate:"mf_TrouserTearWRating",dec:2},StitchTearL:{inputs:["mf_StitchTearL1","mf_StitchTearL2","mf_StitchTearL3"],ave:"mf_StitchTearLAve",std:"mf_StitchTear_L",rate:"mf_StitchTearLRating",dec:2},StitchTearW:{inputs:["mf_StitchTearW1","mf_StitchTearW2","mf_StitchTearW3"],ave:"mf_StitchTearWAve",std:"mf_StitchTear_W",rate:"mf_StitchTearWRating",dec:2},MullenBurst:{inputs:["mf_MullenBurst1","mf_MullenBurst2","mf_MullenBurst3"],ave:"mf_Text3977",std:"mf_MullenBurst",rate:"mf_MullenBurstRating",dec:4},DieC:{inputs:["mf_DieC_1","mf_DieC_2","mf_DieC_3"],ave:"mf_DieC_Ave",std:"mf_DieC_Std",rate:"mf_DieC_Rate",dec:2}},Ie=e=>{const a=De[e];if(!a)return;const s=a.inputs.map(c=>{var p;return parseFloat(((p=t(`#${c}`))==null?void 0:p.value)??"")}).filter(c=>Number.isFinite(c)),o=t(`#${a.ave}`),l=t(`#${a.std}`),i=t(`#${a.rate}`);if(!s.length)return;const r=g(s.reduce((c,p)=>c+p,0)/s.length,a.dec);o&&(o.value=r);const d=parseFloat((l==null?void 0:l.value)??"");Number.isFinite(d)&&d>0&&i&&(i.value=r>=d?"PASS":"FAIL",A(i))},ge=()=>{Object.keys(De).forEach(Ie)},Re={CTP:{fibers:["mf_CtPWool","mf_CtPAcrylic","mf_CtPPolyester","mf_CtPNylon","mf_CtPCotton","mf_CtPAcetate"],std:"mf_CTP",rate:"mf_CTPRating"},CTPB:{fibers:["mf_CtPBWool","mf_CtPBAcrylic","mf_CtPBPolyester","mf_CtPBNylon","mf_CtPBCotton","mf_CtPBAcetate"],std:"mf_CtPB_S",rate:"mf_CTPB_Rating"},CTW:{fibers:["mf_CtWWool","mf_CtWAcrylic","mf_CtWPolyester","mf_CtWNylon","mf_CtWCotton","mf_CtWAcetate"],std:"mf_CTW",rate:"mf_CTWRating"},CTWB:{fibers:["mf_CtWBackSideWool","mf_CtWBackSideAcrylic","mf_CtWBackSidePolyester","mf_CtWBackSideNylon","mf_CtWBackSideCotton","mf_CtWBackSideAcetate"],std:"mf_CtWB_S",rate:"mf_CtWB_Rating"}},Ae=e=>{var i;const a=Re[e];if(!a)return;const s=parseFloat(((i=t(`#${a.std}`))==null?void 0:i.value)??""),o=t(`#${a.rate}`);if(!Number.isFinite(s)||s<=0||!o)return;const l=a.fibers.map(r=>{var d;return parseFloat(((d=t(`#${r}`))==null?void 0:d.value)??"")});l.some(r=>!Number.isFinite(r))||(o.value=l.some(r=>r<s)?"FAIL":"PASS",A(o))},Ee=()=>{var $,T,k,X,x,E,M,N,J,it,U,Y,G,K,ft;const e=parseFloat((($=t("#mf_CtCDry"))==null?void 0:$.value)??""),a=parseFloat(((T=t("#mf_CtCDry_S"))==null?void 0:T.value)??"");Number.isFinite(e)&&Number.isFinite(a)&&a>0&&t("#mf_CTCDRYRating")&&(t("#mf_CTCDRYRating").value=e>=a?"PASS":"FAIL",A(t("#mf_CTCDRYRating")));const s=parseFloat(((k=t("#mf_CtCWet"))==null?void 0:k.value)??""),o=parseFloat(((X=t("#mf_CtCWet_S"))==null?void 0:X.value)??"");Number.isFinite(s)&&Number.isFinite(o)&&o>0&&t("#mf_CTCWETRating")&&(t("#mf_CTCWETRating").value=s>=o?"PASS":"FAIL",A(t("#mf_CTCWETRating")));const l=parseFloat(((x=t("#mf_UV"))==null?void 0:x.value)??""),i=parseFloat(((E=t("#mf_UV_S"))==null?void 0:E.value)??"");Number.isFinite(l)&&Number.isFinite(i)&&i>0&&t("#mf_UVRating")&&(t("#mf_UVRating").value=l>=i?"PASS":"FAIL",A(t("#mf_UVRating")));const r=parseFloat(((M=t("#mf_HeatAging1"))==null?void 0:M.value)??""),d=parseFloat(((N=t("#mf_HeatAging2"))==null?void 0:N.value)??""),c=parseFloat(((J=t("#mf_HeatAging_S"))==null?void 0:J.value)??"");Number.isFinite(r)&&Number.isFinite(d)&&Number.isFinite(c)&&c>0&&t("#mf_HeatAgingRating")&&(t("#mf_HeatAgingRating").value=r<c||d<c?"FAIL":"PASS",A(t("#mf_HeatAgingRating")));const p=parseFloat(((it=t("#mf_Hydrolysis1"))==null?void 0:it.value)??""),u=parseFloat(((U=t("#mf_Hydrolysis2"))==null?void 0:U.value)??""),m=parseFloat(((Y=t("#mf_Hydrolysis_S"))==null?void 0:Y.value)??"");Number.isFinite(p)&&Number.isFinite(u)&&Number.isFinite(m)&&m>0&&t("#mf_HydrolysisRating")&&(t("#mf_HydrolysisRating").value=p<m||u<m?"FAIL":"PASS",A(t("#mf_HydrolysisRating")));const R=parseFloat(((G=t("#mf_PVC_1"))==null?void 0:G.value)??""),L=parseFloat(((K=t("#mf_PVC_2"))==null?void 0:K.value)??""),F=parseFloat(((ft=t("#mf_PVC_S"))==null?void 0:ft.value)??"");Number.isFinite(R)&&Number.isFinite(L)&&Number.isFinite(F)&&F>0&&t("#mf_PVC_Rating")&&(t("#mf_PVC_Rating").value=R<F||L<F?"FAIL":"PASS",A(t("#mf_PVC_Rating")))},P=()=>{var i;let e=!1,a=!1,s=!1;w("#dtr-material-form .dtr-rate-inp").forEach(r=>{A(r);const d=String(r.value||"").trim().toUpperCase();d==="FAIL"?a=!0:d==="TOLERABLE"?s=!0:d==="PASS"&&(e=!0)});let o="PASS";a?o="FAIL":s?o="TOLERABLE":e?o="PASS":(i=t("#mf_Remarks"))!=null&&i.value&&(o=String(t("#mf_Remarks").value).toUpperCase()),t("#mf_Remarks")&&(t("#mf_Remarks").value=o);const l=t("#dtr-stamp-badge");l&&(l.textContent=o,l.className="dtr-stamp "+(o==="FAIL"?"dtr-stamp-fail":o==="TOLERABLE"?"dtr-stamp-warn":"dtr-stamp-pass"))},Te=async()=>{var s,o;const e=((s=t("#mf_MaterialType"))==null?void 0:s.value.trim())||"",a=parseFloat(((o=t("#mf_TAve"))==null?void 0:o.value)??"0")||0;if(e)try{const l=await W(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`);l&&l.mapped&&Object.keys(l.mapped).forEach(i=>{const r=l.mapped[i],d=t(`#mf_${i}`)||t(`[name="${i}"]`,t("#dtr-material-form"));d&&(d.value=r)}),rt(),ge(),Object.keys(Re).forEach(Ae),Ee(),P()}catch(l){console.warn("[DTR] applyStandardValuesSmart error:",l)}},wa=async()=>{we(!1),await Te(),C("✓ Executed ApplyStandardValuesSmart + Thickness + Directional Averages + 6-Point Colorfastness + EvaluateRatings.")},ye=async(e,a=null)=>{const s=t("#dtr-mat-record-select");try{s&&(s.innerHTML='<option value="">Loading records…</option>');const o=await W(`dtr/records?slug=${encodeURIComponent(e)}`);if(!s||!o.rows)return;if(!o.rows.length){s.innerHTML='<option value="">No records yet - press “+ New Record”</option>';return}s.innerHTML=o.rows.map(l=>`
        <option value="${n(l.source_id)}" ${String(l.source_id)===String(a)?"selected":""}>
          ${n(l.lab_ref_no)} · ${n(l.material_type||l.form_category)} · ${n(l.model_style||l.material_code||"")} [${n(l.overall_result||"Pass")}]
        </option>
      `).join("")}catch(o){console.error(o),s&&(s.innerHTML='<option value="">Could not load records - reload the page</option>'),window.nxToast&&nxToast("The record list could not be loaded.","error")}},Ra=e=>{const a=t("#dtr-raw-cols-container"),s=t("#dtr-raw-col-count");if(!a)return;const o=Object.keys(e||{});s&&(s.textContent=o.length),a.innerHTML=o.map(l=>`
      <div class="dtr-field">
        <label title="${n(l)}"><code>${n(l)}</code></label>
        <input type="text" data-raw-col="${n(l)}" value="${n(e[l]??"")}" ${l==="ID"?'readonly class="dtr-ro"':""}>
      </div>
    `).join("")},Ue=(e,a={})=>{We=e||{},t("#dtr-active-form-label")&&a.label&&(t("#dtr-active-form-label").textContent=`${a.label}`),t("#dtr-active-labref-title")&&(t("#dtr-active-labref-title").textContent=`${e.LabRefNo||"New Report"} — ${e["Material Description"]||e.MaterialDescription||e.MaterialType||""}`),w("#dtr-material-form input, #dtr-material-form select").forEach(s=>{s.name&&(s.value="")}),Object.keys(e).forEach(s=>{const o=e[s]??"",l=t(`#mf_${s.replace(/[^a-zA-Z0-9_]/g,"")}`);l&&(l.value=o);const i=t(`[name="${CSS.escape(s)}"]`,t("#dtr-material-form"));i&&(i.value=o)}),t("#mf_MaterialDescription")&&!t("#mf_MaterialDescription").value&&(t("#mf_MaterialDescription").value=e["Material Description"]||e.MaterialDescription||""),t("#mf_SupplierFactory")&&!t("#mf_SupplierFactory").value&&(t("#mf_SupplierFactory").value=e["Supplier/Factory"]||e.Supplier||e.SupplierFactory||""),t("#mf_POLotNo")&&!t("#mf_POLotNo").value&&(t("#mf_POLotNo").value=e["PO/Lot No"]||e.POLotNo||""),t("#mf_ModelNoStyle")&&!t("#mf_ModelNoStyle").value&&(t("#mf_ModelNoStyle").value=e["Model No/Style"]||e.ModelNoStyle||""),t("#mf_POQuantity")&&!t("#mf_POQuantity").value&&(t("#mf_POQuantity").value=e["PO Quantity"]||e.POQuantity||""),t("#mf_TestedBy")&&!t("#mf_TestedBy").value&&(t("#mf_TestedBy").value=e["Tested by"]||e.TestedBy||e.Technician||""),Ra(e),we(!1),rt(),ge(),Object.keys(Re).forEach(Ae),Ee(),P()},Fe=async(e,a=0)=>{try{const s=await W(`dtr/record?slug=${encodeURIComponent(e)}&source_id=${encodeURIComponent(a)}`);s&&s.ok&&Ue(s.record||{},s.config||{})}catch(s){console.error(s)}};(ze=t("#mf_LabRefNo"))==null||ze.addEventListener("change",()=>{var s;const e=(((s=t("#mf_LabRefNo"))==null?void 0:s.value)||"").toUpperCase().trim(),a=t("#mf_MaterialType");!e||!a||(e.includes("FBU")||e.includes("FBL")?a.value||(a.value="Canvas"):e.includes("LEU")||e.includes("LEL")?a.value||(a.value="Suede"):e.includes("RB")?a.value="Rubber":e.includes("IB")?a.value="Inner Box":e.includes("OB")?a.value="Outer Box":e.includes("FML")?a.value="Sockliner - Latex":e.includes("FM")?a.value="PU Foam":e.includes("SKL")?a.value="Sockliner - EVA":e.includes("LC")?a.value="Shoe lace":e.includes("TH")?a.value="Thread":e.includes("EY")?a.value="Eyelet":e.includes("GR")?a.value="Grommet":e.includes("ZP")&&(a.value="Zipper"),Te())});const Se=async()=>{var o,l,i;const e=((o=t("#mf_MaterialCode"))==null?void 0:o.value.trim())||"",a=((l=t("#mf_POLotNo"))==null?void 0:l.value.trim())||"",s=((i=t("#mf_MTL"))==null?void 0:i.value.trim())||"";if(!e&&!a&&!s){C("Enter a Material Code, PO/Lot No, or MTL # first.",!0);return}try{const r=await W(`dtr/lookup?material_code=${encodeURIComponent(e)}&po_lot_no=${encodeURIComponent(a)}&mtl_no=${encodeURIComponent(s)}`);let d=[];if(r.material&&(t("#mf_SupplierFactory")&&(t("#mf_SupplierFactory").value=r.material.Supplier||""),t("#mf_MaterialDescription")&&(t("#mf_MaterialDescription").value=r.material.MaterialName||""),d.push(`MaterialList (${r.material.MaterialCode})`)),r.shoe&&(t("#mf_ModelNoStyle")&&(t("#mf_ModelNoStyle").value=r.shoe.StyleNo||""),t("#mf_Color")&&(t("#mf_Color").value=r.shoe.Colorway||""),t("#mf_MTL")&&r.shoe.MTL&&(t("#mf_MTL").value=r.shoe.MTL),d.push(`ShoeList (${r.shoe.StyleNo})`)),r.dsr&&r.dsr.length>0){const c=r.dsr[0];t("#mf_StyleNumber_DSR")&&(t("#mf_StyleNumber_DSR").value=c.StyleNumber||""),t("#mf_StyleDescription_DSR")&&(t("#mf_StyleDescription_DSR").value=c.StyleDescription||""),t("#mf_Colorway_DSR")&&(t("#mf_Colorway_DSR").value=c.Colorway||""),t("#mf_ComponentorLocation_DSR")&&(t("#mf_ComponentorLocation_DSR").value=c.ComponentorLocation||""),t("#mf_VFMaterialCode_DSR")&&(t("#mf_VFMaterialCode_DSR").value=c.VFMaterialCode||""),t("#mf_InternalName_DSR")&&(t("#mf_InternalName_DSR").value=c["Internal NameVans1"]||c.Material||""),t("#mf_ColorName_DSR")&&(t("#mf_ColorName_DSR").value=c.ColorName||c.Color||""),d.push(`tblDSR (${r.dsr.length} component rows)`)}d.length?C(`✓ Auto-Lookup populated from: ${d.join(" · ")}`):C("No exact match found in MaterialList / ShoeList / tblDSR.",!0)}catch(r){console.error(r)}};(qe=t("#btn-dtr-lookup-all"))==null||qe.addEventListener("click",Se),(Qe=t("#mf_MaterialCode"))==null||Qe.addEventListener("change",Se),(Xe=t("#mf_POLotNo"))==null||Xe.addEventListener("change",Se),(Je=t("#mf_MTL"))==null||Je.addEventListener("change",Se),(Ye=t("#mf_MaterialType"))==null||Ye.addEventListener("change",Te),w(".dtr-calc-thick").forEach(e=>{e.addEventListener("input",()=>we(!0))}),w(".dtr-calc-weight, .dtr-calc-sg, .dtr-calc-din, .dtr-calc-ross, .dtr-calc-shrink").forEach(e=>{e.addEventListener("input",()=>{rt(),P()})}),w(".dtr-dir-inp, .dtr-std-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset.dirGroup?Ie(e.dataset.dirGroup):ge(),P()})}),w(".dtr-6pt-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset["6pt"]&&Ae(e.dataset["6pt"]),P()})}),w(".dtr-crock-inp").forEach(e=>{e.addEventListener("input",()=>{Ee(),P()})}),w(".dtr-rate-inp").forEach(e=>{e.addEventListener("change",P),e.addEventListener("input",P)}),(Ke=t("#dtr-mat-form-slug"))==null||Ke.addEventListener("change",async e=>{lt=e.target.value,await ye(lt),await Fe(lt,0)}),(Ze=t("#dtr-mat-record-select"))==null||Ze.addEventListener("change",async e=>{const a=e.target.value;a&&await Fe(lt,a)}),(ta=t("#btn-dtr-new"))==null||ta.addEventListener("click",()=>{const e=new Date().toISOString().slice(0,10);Ue({ID:"",LabRefNo:`DTP${e.replace(/-/g,"")}${lt.toUpperCase().slice(0,3)}-1`,TR:`TR${e.replace(/-/g,"")}001`,ReceivedDate:e,TestedDate:e,FinishedDate:e,Status:"Complete",Remarks:"PASS"}),C("Initialized new blank test report record.")}),(ea=t("#btn-dtr-review"))==null||ea.addEventListener("click",()=>{const e=prompt("Enter Reviewer Name:","Faye Ann Elaysa");if(!e)return;const a=t('[data-raw-col="AuthorizedBy"]');a&&(a.value=e);const s=t('[data-raw-col="AuthorizationStatus"]');s&&(s.value="Reviewed"),C(`✓ Record stamped Reviewed by ${e}`)}),(aa=t("#btn-dtr-refresh-calc"))==null||aa.addEventListener("click",wa),(sa=t("#btn-dtr-load-machine"))==null||sa.addEventListener("click",async()=>{var a;const e=((a=t("#mf_LabRefNo"))==null?void 0:a.value.trim())||"";if(!e){C("Enter a LabRefNo first before loading machine results.",!0);return}try{const s=await W("dtr/load-machine",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:lt,lab_ref_no:e})});s&&s.loadedFields&&(Object.keys(s.loadedFields).forEach(o=>{const l=s.loadedFields[o],i=t(`#mf_${o}`)||t(`[name="${CSS.escape(o)}"]`,t("#dtr-material-form"));i&&(i.value=l);const r=t(`[data-raw-col="${CSS.escape(o)}"]`);r&&(r.value=l)}),rt(),ge(),P(),t("#dtr-machine-status")&&(t("#dtr-machine-status").textContent=s.message||"Machine data loaded."),C(`${s.message}`))}catch(s){C("Machine loader error: "+(s.message||""),!0)}}),(la=t("#btn-dtr-save"))==null||la.addEventListener("click",async()=>{const e=Object.assign({},We);w("#dtr-raw-cols-container [data-raw-col]").forEach(a=>{e[a.dataset.rawCol]=a.value}),w("#dtr-material-form [name]").forEach(a=>{a.name&&(e[a.name]=a.value)});try{const a=await W("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:lt,fields:e})});a&&a.ok?(t("#mf_ID")&&(t("#mf_ID").value=a.recordId),C(`✓ ${a.message} (Ready in InterLink Sync ComboBox!)`),await ye(lt,a.recordId)):C(a.error||"Save failed.",!0)}catch{C("Failed to save record.",!0)}}),(oa=t("#btn-toggle-raw-cols"))==null||oa.addEventListener("click",()=>{var e;(e=t("#dtr-raw-cols-container"))==null||e.classList.toggle("hidden")});const Aa={TODDLER:14,KIDS:16,CHILDREN:24,ADULT:35},Ea=(e,a)=>!Number.isFinite(e)||e<=0?null:a>=2.5?e>=2.5?"PASS":e>=2?"TOLERABLE":"FAIL":e>=2?"PASS":e>=1.8?"TOLERABLE":"FAIL",wt=()=>{var m,R;const e=(((m=t("#bf_AgeCategory"))==null?void 0:m.value)||"ADULT").toUpperCase(),a=Aa[e]||35,s=parseFloat(((R=t("#bf_STD"))==null?void 0:R.value)??"2.5")||2.5,o=1.9;w("#dtr-bonding-points-tbody [data-spot-row]").forEach(L=>{const F=Number(L.dataset.spotRow);L.style.opacity=F<=a?"1":"0.45"});const l=(L,F,$)=>{const T=[];let k=!1,X=!1;for(let x=1;x<=F;x++){const E=t(`#bp_${L}${x}`);if(!E)continue;const M=parseFloat(E.value??"");if(E.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),Number.isFinite(M)&&M>0){T.push(M);const N=L==="FiTO"?M>=o?"PASS":"FAIL":Ea(M,$);N==="FAIL"?(k=!0,E.classList.add("dtr-rate-fail")):N==="TOLERABLE"?(X=!0,E.classList.add("dtr-rate-warn")):E.classList.add("dtr-rate-pass")}}return T.length?{min:g(Math.min(...T),2),max:g(Math.max(...T),2),ave:g(T.reduce((x,E)=>x+E,0)/T.length,2),res:k?"FAIL":X?"TOLERABLE":"PASS"}:{min:"",max:"",ave:"",res:""}},i=l("UTFpoint",a,s),r=l("FTOpoint",a,s),d=l("FiTO",13,o);t("#bp_UTFMin")&&(t("#bp_UTFMin").value=i.min),t("#bp_UTFMax")&&(t("#bp_UTFMax").value=i.max),t("#bp_UTFAverage")&&(t("#bp_UTFAverage").value=i.ave),t("#bp_UTF_Result")&&(t("#bp_UTF_Result").value=i.res,A(t("#bp_UTF_Result"))),t("#bp_FTOMin")&&(t("#bp_FTOMin").value=r.min),t("#bp_FTOMax")&&(t("#bp_FTOMax").value=r.max),t("#bp_FTOAverage")&&(t("#bp_FTOAverage").value=r.ave),t("#bp_FTO_Result")&&(t("#bp_FTO_Result").value=r.res,A(t("#bp_FTO_Result"))),t("#bp_FiTOMin")&&(t("#bp_FiTOMin").value=d.min),t("#bp_FiTOMax")&&(t("#bp_FiTOMax").value=d.max),t("#bp_FiTOAverage")&&(t("#bp_FiTOAverage").value=d.ave),t("#bp_FiTO_Result")&&(t("#bp_FiTO_Result").value=d.res,A(t("#bp_FiTO_Result")));const c=[i.res,r.res,d.res].filter(Boolean);let p="PASS";c.includes("FAIL")?p="FAIL":c.includes("TOLERABLE")&&(p="TOLERABLE"),t("#bf_Result")&&(t("#bf_Result").value=p,A(t("#bf_Result")));const u=t("#bf-overall-badge");u&&(u.textContent=p,u.className="badge "+(p==="FAIL"?"b-err":p==="TOLERABLE"?"b-warn":"b-ok"))},xe=async(e=null)=>{const a=t("#dtr-bonding-record-select");try{a&&(a.innerHTML='<option value="">Loading reports…</option>');const s=await W("dtr/records?slug=bonding");if(!a||!s.rows)return;if(!s.rows.length){a.innerHTML='<option value="">No bonding reports yet</option>';return}a.innerHTML=s.rows.map(o=>`
        <option value="${n(o.source_id)}" ${String(o.source_id)===String(e)?"selected":""}>
          ${n(o.lab_ref_no)} · ${n(o.model_style||"")} · ${n(o.color||"")} [${n(o.overall_result||"Pass")}]
        </option>
      `).join("")}catch(s){console.error(s),a&&(a.innerHTML='<option value="">Could not load reports - reload the page</option>'),window.nxToast&&nxToast("The bonding report list could not be loaded.","error")}},Me=async(e=0)=>{try{const a=await W(`dtr/record?slug=bonding&source_id=${encodeURIComponent(e)}`);if(!a||!a.ok)return;const s=a.record||{},o=a.bondingPoints||{};v=o,Object.keys(s).forEach(l=>{const i=t(`#bf_${l}`);i&&(i.value=s[l]??"")}),w('#dtr-bonding-form [name^="cf_"]').forEach(l=>{l.value=(a.customValues||{})[l.name]??""});for(let l=1;l<=35;l++)t(`#bp_UTFpoint${l}`)&&(t(`#bp_UTFpoint${l}`).value=o[`UTFpoint${l}`]??""),t(`#bp_FTOpoint${l}`)&&(t(`#bp_FTOpoint${l}`).value=o[`FTOpoint${l}`]??""),l<=13&&t(`#bp_FiTO${l}`)&&(t(`#bp_FiTO${l}`).value=o[`FiTO${l}`]??"");wt()}catch(a){console.error(a)}};(ra=t("#dtr-bonding-record-select"))==null||ra.addEventListener("change",e=>{e.target.value&&Me(e.target.value)}),(ia=t("#bf_AgeCategory"))==null||ia.addEventListener("change",wt),(na=t("#bf_STD"))==null||na.addEventListener("change",wt),w("#dtr-bonding-points-tbody .dtr-bp-inp").forEach(e=>{e.addEventListener("input",wt)}),(ca=t("#btn-bond-recalc"))==null||ca.addEventListener("click",()=>{wt(),C("✓ Recalculated UTF, FTO, and FiTO Min/Max/Ave & Spot Tolerances.")}),(da=t("#btn-bond-new"))==null||da.addEventListener("click",()=>{const e=new Date().toISOString().slice(0,10);w("#dtr-bonding-form input").forEach(a=>{a.value=""}),t("#bf_LabRefNo")&&(t("#bf_LabRefNo").value=`DTP${e.replace(/-/g,"")}SHB01-R`),t("#bf_AgeCategory")&&(t("#bf_AgeCategory").value="ADULT"),t("#bf_STD")&&(t("#bf_STD").value="2.5"),t("#bf_ReceivedDate")&&(t("#bf_ReceivedDate").value=e),t("#bf_TestedDate")&&(t("#bf_TestedDate").value=e),t("#bf_FinishedDate")&&(t("#bf_FinishedDate").value=e),wt(),C("Initialized new Whole Shoe Bonding Test Report.")}),(fa=t("#btn-bond-load-u60"))==null||fa.addEventListener("click",async()=>{var a;const e=((a=t("#bf_LabRefNo"))==null?void 0:a.value.trim())||"DTP20261024SHB11-R";try{const s=await W("dtr/load-machine",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"bonding",lab_ref_no:e})});s&&s.loadedFields&&(Object.keys(s.loadedFields).forEach(o=>{const l=t(`#bp_${o}`);l&&(l.value=s.loadedFields[o])}),wt(),C(`${s.message}`))}catch{C("Failed to load U60 machine file.",!0)}}),(ua=t("#btn-bond-save"))==null||ua.addEventListener("click",async()=>{var s,o,l,i,r,d,c,p,u,m,R,L,F;const e={};w("#dtr-bonding-form [name]").forEach($=>{$.name&&(e[$.name]=$.value)});const a={UTFMin:((s=t("#bp_UTFMin"))==null?void 0:s.value)||null,UTFMax:((o=t("#bp_UTFMax"))==null?void 0:o.value)||null,UTFAverage:((l=t("#bp_UTFAverage"))==null?void 0:l.value)||null,UTF_Result:((i=t("#bp_UTF_Result"))==null?void 0:i.value)||null,FTOMin:((r=t("#bp_FTOMin"))==null?void 0:r.value)||null,FTOMax:((d=t("#bp_FTOMax"))==null?void 0:d.value)||null,FTOAverage:((c=t("#bp_FTOAverage"))==null?void 0:c.value)||null,FTO_Result:((p=t("#bp_FTO_Result"))==null?void 0:p.value)||null,FiTOMin:((u=t("#bp_FiTOMin"))==null?void 0:u.value)||null,FiTOMax:((m=t("#bp_FiTOMax"))==null?void 0:m.value)||null,FiTOAverage:((R=t("#bp_FiTOAverage"))==null?void 0:R.value)||null,FiTO_Result:((L=t("#bp_FiTO_Result"))==null?void 0:L.value)||null,Result:((F=t("#bf_Result"))==null?void 0:F.value)||"PASS"};w("#dtr-bonding-points-tbody [data-bp-col]").forEach($=>{a[$.dataset.bpCol]=$.value});try{const $=await W("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"bonding",fields:e,bondingPoints:a})});$&&$.ok&&(t("#bf_BondingTestID")&&(t("#bf_BondingTestID").value=$.recordId),C(`✓ ${$.message}`),await xe($.recordId))}catch{C("Error saving Bonding Report.",!0)}});const Ve=()=>{var mt,H,B,D,vt,ct,Mt,Ot,kt,Nt,dt,tt,et,at,_t,Wt,st,bt,ht,gt,Tt,yt,Ft,St,Lt,$t,Ct;const e=parseFloat(((mt=t("#calc_raw_w1"))==null?void 0:mt.value)??"0")||0,a=parseFloat(((H=t("#calc_raw_w2"))==null?void 0:H.value)??"0")||0,s=parseFloat(((B=t("#calc_raw_w3"))==null?void 0:B.value)??"0")||0,o=g(e/100*1e4,2),l=g(a/100*1e4,2),i=g(s/100*1e4,2),r=g((o+l+i)/3,2);t("#calc_res_w1")&&(t("#calc_res_w1").value=o),t("#calc_res_w2")&&(t("#calc_res_w2").value=l),t("#calc_res_w3")&&(t("#calc_res_w3").value=i),t("#calc_res_wave")&&(t("#calc_res_wave").textContent=r);const d=parseFloat(((D=t("#calc_sg_w1"))==null?void 0:D.value)??"0")||0,c=parseFloat(((vt=t("#calc_sg_w2"))==null?void 0:vt.value)??"0")||0,p=parseFloat(((ct=t("#calc_sg_w3"))==null?void 0:ct.value)??"0")||0,u=parseFloat(((Mt=t("#calc_sg_t1"))==null?void 0:Mt.value)??"1")||1,m=parseFloat(((Ot=t("#calc_sg_t2"))==null?void 0:Ot.value)??"1")||1,R=parseFloat(((kt=t("#calc_sg_t3"))==null?void 0:kt.value)??"1")||1,L=g(d/(100*(u/10)),3),F=g(c/(100*(m/10)),3),$=g(p/(100*(R/10)),3),T=g((L+F+$)/3,3);t("#calc_res_sg_ave")&&(t("#calc_res_sg_ave").textContent=`${L}, ${F}, ${$} (Ave: ${T})`);const k=parseFloat(((Nt=t("#calc_cp_b1"))==null?void 0:Nt.value)??"10")||10,X=parseFloat(((dt=t("#calc_cp_a1"))==null?void 0:dt.value)??"9")||9,x=parseFloat(((tt=t("#calc_cp_b2"))==null?void 0:tt.value)??"10")||10,E=parseFloat(((et=t("#calc_cp_a2"))==null?void 0:et.value)??"9")||9,M=parseFloat(((at=t("#calc_cp_a3"))==null?void 0:at.value)??"9")||9,N=parseFloat(((_t=t("#calc_cp_sp"))==null?void 0:_t.value)??"0")||0,J=k-N!==0?k-N:1,it=x-N!==0?x-N:1,U=g((k-X)/J*100,1),Y=g((x-E)/it*100,1),G=g((k-M)/J*100,1),K=g((U+Y+G)/3,1);t("#calc_res_cp_ave")&&(t("#calc_res_cp_ave").textContent=`${K}% (${U}%, ${Y}%, ${G}%)`);const ft=parseFloat(((Wt=t("#calc_din_wb"))==null?void 0:Wt.value)??"0")||0,Pt=parseFloat(((st=t("#calc_din_wa"))==null?void 0:st.value)??"0")||0,Rt=parseFloat(((bt=t("#calc_din_sg"))==null?void 0:bt.value)??"1")||1,At=parseFloat(((ht=t("#calc_din_sa"))==null?void 0:ht.value)??"200")||200,Ut=g((ft-Pt)/Rt*(200/At)*1e3,2);t("#calc_res_din")&&(t("#calc_res_din").textContent=Ut);const Et=parseFloat(((gt=t("#calc_rf_li1"))==null?void 0:gt.value)??"2.5")||2.5,xt=parseFloat(((Tt=t("#calc_rf_lf1"))==null?void 0:Tt.value)??"2.5")||2.5,ut=parseFloat(((yt=t("#calc_rf_li2"))==null?void 0:yt.value)??"2.5")||2.5,Vt=parseFloat(((Ft=t("#calc_rf_lf2"))==null?void 0:Ft.value)??"2.5")||2.5,Dt=g((xt-Et)/Et*100,2),V=g((Vt-ut)/ut*100,2);t("#calc_res_rf")&&(t("#calc_res_rf").textContent=`${Dt}% / ${V}%`);const z=parseFloat(((St=t("#calc_sh_lb"))==null?void 0:St.value)??"100")||100,Z=parseFloat(((Lt=t("#calc_sh_la"))==null?void 0:Lt.value)??"100")||100,pt=parseFloat((($t=t("#calc_sh_wb"))==null?void 0:$t.value)??"100")||100,nt=parseFloat(((Ct=t("#calc_sh_wa"))==null?void 0:Ct.value)??"100")||100,q=g((z-Z)/z*100,2),y=g((pt-nt)/pt*100,2);t("#calc_res_sh")&&(t("#calc_res_sh").textContent=`L: ${q}% / W: ${y}%`)};w("#dtr-panel-calculators input").forEach(e=>{e.addEventListener("input",Ve)}),(pa=t("#btn-apply-calc-weight"))==null||pa.addEventListener("click",()=>{var e,a,s;t("#mf_Weight1")&&(t("#mf_Weight1").value=((e=t("#calc_res_w1"))==null?void 0:e.value)||""),t("#mf_Weight2")&&(t("#mf_Weight2").value=((a=t("#calc_res_w2"))==null?void 0:a.value)||""),t("#mf_Weight3")&&(t("#mf_Weight3").value=((s=t("#calc_res_w3"))==null?void 0:s.value)||""),rt(),P(),ot("material"),C("✓ Applied calculated Weight 1..3 and WAve to Material Form.")}),(ma=t("#btn-apply-calc-sg"))==null||ma.addEventListener("click",()=>{var r,d,c,p,u,m;const e=parseFloat(((r=t("#calc_sg_w1"))==null?void 0:r.value)??"0")||0,a=parseFloat(((d=t("#calc_sg_w2"))==null?void 0:d.value)??"0")||0,s=parseFloat(((c=t("#calc_sg_w3"))==null?void 0:c.value)??"0")||0,o=parseFloat(((p=t("#calc_sg_t1"))==null?void 0:p.value)??"1")||1,l=parseFloat(((u=t("#calc_sg_t2"))==null?void 0:u.value)??"1")||1,i=parseFloat(((m=t("#calc_sg_t3"))==null?void 0:m.value)??"1")||1;t("#mf_SG1")&&(t("#mf_SG1").value=g(e/(100*(o/10)),3)),t("#mf_SG2")&&(t("#mf_SG2").value=g(a/(100*(l/10)),3)),t("#mf_SG3")&&(t("#mf_SG3").value=g(s/(100*(i/10)),3)),rt(),P(),ot("material"),C("✓ Applied Specific Gravity SG1..3 and SG_Ave to Material Form.")}),(va=t("#btn-apply-calc-din"))==null||va.addEventListener("click",()=>{var a;const e=((a=t("#calc_res_din"))==null?void 0:a.textContent)||"";t("#mf_DIN_1")&&(t("#mf_DIN_1").value=e),rt(),P(),ot("material"),C("✓ Applied DIN Abrasion value to Material Form.")}),(_a=t("#btn-apply-calc-ross"))==null||_a.addEventListener("click",()=>{var l,i,r,d;const e=parseFloat(((l=t("#calc_rf_li1"))==null?void 0:l.value)??"2.5")||2.5,a=parseFloat(((i=t("#calc_rf_lf1"))==null?void 0:i.value)??"2.5")||2.5,s=parseFloat(((r=t("#calc_rf_li2"))==null?void 0:r.value)??"2.5")||2.5,o=parseFloat(((d=t("#calc_rf_lf2"))==null?void 0:d.value)??"2.5")||2.5;t("#mf_RossFlex_1")&&(t("#mf_RossFlex_1").value=g((a-e)/e*100,2)),t("#mf_RossFlex_2")&&(t("#mf_RossFlex_2").value=g((o-s)/s*100,2)),rt(),P(),ot("material"),C("✓ Applied Ross Flex 1 & 2 to Material Form.")}),(ba=t("#btn-apply-calc-shrink"))==null||ba.addEventListener("click",()=>{var l,i,r,d;const e=parseFloat(((l=t("#calc_sh_lb"))==null?void 0:l.value)??"100")||100,a=parseFloat(((i=t("#calc_sh_la"))==null?void 0:i.value)??"100")||100,s=parseFloat(((r=t("#calc_sh_wb"))==null?void 0:r.value)??"100")||100,o=parseFloat(((d=t("#calc_sh_wa"))==null?void 0:d.value)??"100")||100;t("#mf_ShrinkageL")&&(t("#mf_ShrinkageL").value=g((e-a)/e*100,2)),t("#mf_ShrinkageW")&&(t("#mf_ShrinkageW").value=g((s-o)/s*100,2)),rt(),P(),ot("material"),C("✓ Applied Shrinkage L & W to Material Form.")});const Oe=async()=>{var s,o;const e=((s=t("#exp_mat_type"))==null?void 0:s.value)||"Canvas",a=((o=t("#exp_t_ave"))==null?void 0:o.value)||"1.25";try{const l=await W(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`),i=t("#exp-standards-tbody");if(!i||!l.standards)return;i.innerHTML=l.standards.map(r=>`
        <tr>
          <td><b>${n(r.MaterialType)}</b></td>
          <td class="mono"><code>${n(r.TestMethod)}</code></td>
          <td class="mono"><b>${n(r.StandardValue??"-")}</b></td>
          <td class="mono">${n(r.Tolerance??"-")}</td>
          <td class="mono">${n(r.TMinC??"-")}</td>
          <td class="mono">${n(r.TMaxC??"-")}</td>
        </tr>
      `).join("")}catch(l){console.error(l)}};(ha=t("#btn-exp-run"))==null||ha.addEventListener("click",Oe),(ga=t("#exp_mat_type"))==null||ga.addEventListener("change",Oe);const xa=()=>{var x,E,M,N,J,it,U,Y,G,K,ft,Pt,Rt,At,Ut,Et,xt,ut,Vt,Dt,V,z,Z,pt,nt,q,y,mt,H,B,D,vt,ct,Mt,Ot,kt,Nt,dt,tt,et,at,_t,Wt,st,bt,ht,gt,Tt,yt,Ft,St,Lt,$t,Ct,Ht,Bt,jt,Gt,zt,qt,Qt,Xt,Jt,Yt,Kt,Zt,te,ee,ae,se,le,oe,re,ie,ne,ce,de,fe,ue,pe;const e=((x=t("#mf_LabRefNo"))==null?void 0:x.value)||"DTP-LAB-PREVIEW",a=((E=t("#mf_TR"))==null?void 0:E.value)||"-",s=((M=t("#mf_MaterialType"))==null?void 0:M.value)||"Material",o=((N=t("#mf_MaterialCode"))==null?void 0:N.value)||"-",l=((J=t("#mf_MaterialDescription"))==null?void 0:J.value)||"-",i=((it=t("#mf_SupplierFactory"))==null?void 0:it.value)||"DATIAN SUBIC SHOES INC.",r=((U=t("#mf_ModelNoStyle"))==null?void 0:U.value)||"-",d=((Y=t("#mf_Color"))==null?void 0:Y.value)||"-",c=((G=t("#mf_POLotNo"))==null?void 0:G.value)||"-",p=((K=t("#mf_MTL"))==null?void 0:K.value)||"-",u=((ft=t("#mf_TestedDate"))==null?void 0:ft.value)||new Date().toISOString().slice(0,10),m=((Pt=t("#mf_Technician"))==null?void 0:Pt.value)||"Lab Technician",R=((Rt=t("#mf_AuthorizedBy"))==null?void 0:Rt.value)||"QA Manager",L=((At=t("#mf_AuthorizedDate"))==null?void 0:At.value)||"",F=(((Ut=t("#mf_Remarks"))==null?void 0:Ut.value)||"PASS").toUpperCase(),$=F==="PASS"?"":F==="TOLERABLE"?"stamp-tol":"stamp-fail",T=[],k=(me,ve,_e,$e,It,be,f,_)=>{(_e||be)&&T.push(`
          <tr>
            <td class="text-left"><b>${me} — Length (L)</b></td>
            <td>${ve}</td>
            <td><b>${_e||"-"}</b></td>
            <td>${$e||"-"}</td>
            <td><b class="${It==="PASS"?"dtr-rate-pass":It==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${It||"-"}</b></td>
          </tr>
          <tr>
            <td class="text-left"><b>${me} — Width (W)</b></td>
            <td>${ve}</td>
            <td><b>${be||"-"}</b></td>
            <td>${f||"-"}</td>
            <td><b class="${_==="PASS"?"dtr-rate-pass":_==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${_||"-"}</b></td>
          </tr>
        `)};k("Tensile Strength","kg",(Et=t("#mf_TensileLAve"))==null?void 0:Et.value,(xt=t("#mf_Tensile_L"))==null?void 0:xt.value,(ut=t("#mf_TensileLRating"))==null?void 0:ut.value,(Vt=t("#mf_TensileWAve"))==null?void 0:Vt.value,(Dt=t("#mf_Tensile_W"))==null?void 0:Dt.value,(V=t("#mf_TensileWRating"))==null?void 0:V.value),k("Elongation","%",(z=t("#mf_ElongationLAve"))==null?void 0:z.value,(Z=t("#mf_Elongation_L"))==null?void 0:Z.value,(pt=t("#mf_ElongationLRating"))==null?void 0:pt.value,(nt=t("#mf_ElongationWAve"))==null?void 0:nt.value,(q=t("#mf_Elongation_W"))==null?void 0:q.value,(y=t("#mf_ElongationWRating"))==null?void 0:y.value),k("Trouser / Wing Tear","kg",(mt=t("#mf_TrouserTearLAve"))==null?void 0:mt.value,(H=t("#mf_TrouserTear_L"))==null?void 0:H.value,(B=t("#mf_TrouserTearLRating"))==null?void 0:B.value,(D=t("#mf_TrouserTearWAve"))==null?void 0:D.value,(vt=t("#mf_TrouserTear_W"))==null?void 0:vt.value,(ct=t("#mf_TrouserTearWRating"))==null?void 0:ct.value),k("Stitch Tear","kg",(Mt=t("#mf_StitchTearLAve"))==null?void 0:Mt.value,(Ot=t("#mf_StitchTear_L"))==null?void 0:Ot.value,(kt=t("#mf_StitchTearLRating"))==null?void 0:kt.value,(Nt=t("#mf_StitchTearWAve"))==null?void 0:Nt.value,(dt=t("#mf_StitchTear_W"))==null?void 0:dt.value,(tt=t("#mf_StitchTearWRating"))==null?void 0:tt.value),(et=t("#mf_Text3977"))!=null&&et.value&&T.push(`
        <tr>
          <td class="text-left"><b>Mullen Bursting Strength</b></td>
          <td>kg/cm²</td>
          <td><b>${(at=t("#mf_Text3977"))==null?void 0:at.value}</b></td>
          <td>${((_t=t("#mf_MullenBurst"))==null?void 0:_t.value)||"-"}</td>
          <td><b>${((Wt=t("#mf_MullenBurstRating"))==null?void 0:Wt.value)||"-"}</b></td>
        </tr>
      `),(st=t("#mf_DieC_Ave"))!=null&&st.value&&T.push(`
        <tr>
          <td class="text-left"><b>Die C Tear / Edge Crush</b></td>
          <td>kg</td>
          <td><b>${(bt=t("#mf_DieC_Ave"))==null?void 0:bt.value}</b></td>
          <td>${((ht=t("#mf_DieC_Std"))==null?void 0:ht.value)||"-"}</td>
          <td><b>${((gt=t("#mf_DieC_Rate"))==null?void 0:gt.value)||"-"}</b></td>
        </tr>
      `),(Tt=t("#mf_WAve"))!=null&&Tt.value&&T.push(`
        <tr>
          <td class="text-left"><b>Fabric Weight (Grammage)</b></td>
          <td>g/m²</td>
          <td><b>${(yt=t("#mf_WAve"))==null?void 0:yt.value}</b></td>
          <td>${((Ft=t("#mf_W_Std"))==null?void 0:Ft.value)||"-"}</td>
          <td><b>${((St=t("#mf_WRating"))==null?void 0:St.value)||"-"}</b></td>
        </tr>
      `),(Lt=t("#mf_CTPRating"))!=null&&Lt.value&&T.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Perspiration (Face)</b></td>
          <td>Grade</td>
          <td>W:${(($t=t("#mf_CtPWool"))==null?void 0:$t.value)||"-"} A:${((Ct=t("#mf_CtPAcrylic"))==null?void 0:Ct.value)||"-"} P:${((Ht=t("#mf_CtPPolyester"))==null?void 0:Ht.value)||"-"} N:${((Bt=t("#mf_CtPNylon"))==null?void 0:Bt.value)||"-"} C:${((jt=t("#mf_CtPCotton"))==null?void 0:jt.value)||"-"} Ac:${((Gt=t("#mf_CtPAcetate"))==null?void 0:Gt.value)||"-"}</td>
          <td>${((zt=t("#mf_CTP"))==null?void 0:zt.value)||"4.0"}</td>
          <td><b>${(qt=t("#mf_CTPRating"))==null?void 0:qt.value}</b></td>
        </tr>
      `),(Qt=t("#mf_CTWRating"))!=null&&Qt.value&&T.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Water (Face)</b></td>
          <td>Grade</td>
          <td>W:${((Xt=t("#mf_CtWWool"))==null?void 0:Xt.value)||"-"} A:${((Jt=t("#mf_CtWAcrylic"))==null?void 0:Jt.value)||"-"} P:${((Yt=t("#mf_CtWPolyester"))==null?void 0:Yt.value)||"-"} N:${((Kt=t("#mf_CtWNylon"))==null?void 0:Kt.value)||"-"} C:${((Zt=t("#mf_CtWCotton"))==null?void 0:Zt.value)||"-"} Ac:${((te=t("#mf_CtWAcetate"))==null?void 0:te.value)||"-"}</td>
          <td>${((ee=t("#mf_CTW"))==null?void 0:ee.value)||"4.0"}</td>
          <td><b>${(ae=t("#mf_CTWRating"))==null?void 0:ae.value}</b></td>
        </tr>
      `),(se=t("#mf_CTCDRYRating"))!=null&&se.value&&T.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Dry)</b></td>
          <td>Grade</td>
          <td><b>${((le=t("#mf_CtCDry"))==null?void 0:le.value)||"-"}</b></td>
          <td>${((oe=t("#mf_CtCDry_S"))==null?void 0:oe.value)||"4.0"}</td>
          <td><b>${(re=t("#mf_CTCDRYRating"))==null?void 0:re.value}</b></td>
        </tr>
      `),(ie=t("#mf_CTCWETRating"))!=null&&ie.value&&T.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Wet)</b></td>
          <td>Grade</td>
          <td><b>${((ne=t("#mf_CtCWet"))==null?void 0:ne.value)||"-"}</b></td>
          <td>${((ce=t("#mf_CtCWet_S"))==null?void 0:ce.value)||"3.0"}</td>
          <td><b>${(de=t("#mf_CTCWETRating"))==null?void 0:de.value}</b></td>
        </tr>
      `);const X=`
      <div class="dtr-report-sheet">
        <div class="rpt-logo-row">
          <img class="rpt-logo-vf" src="${I}assets/images/vf-logo.webp" alt="VF">
          <img class="rpt-logo-satra" src="${I}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
        </div>
        <div class="dtr-sheet-header">
          <div class="dtr-sheet-title">
            <h2>DATIAN SUBIC SHOES INC. (DTP)</h2>
            <h4>PHYSICAL TEST REPORT — ${n(s.toUpperCase())}</h4>
            <div style="font-size:11px;color:#64748b;margin-top:2px">Lot 1P7-A, Phase 2, Subic Bay Gateway Park, Subic Bay Freeport Zone</div>
          </div>
          <div class="dtr-sheet-meta">
            <div>Lab Ref: <b>${n(e)}</b></div>
            <div>TR #: <b>${n(a)}</b></div>
            <div style="margin-top:6px">
              <span class="dtr-sheet-stamp-box ${$}">${n(F)}</span>
            </div>
          </div>
        </div>

        <table class="dtr-sheet-info-grid">
          <tr>
            <td class="label-cell">Material Code</td>
            <td><b>${n(o)}</b></td>
            <td class="label-cell">Material Type</td>
            <td>${n(s)}</td>
            <td class="label-cell">Tested Date</td>
            <td>${n(u)}</td>
          </tr>
          <tr>
            <td class="label-cell">Description</td>
            <td colspan="3">${n(l)}</td>
            <td class="label-cell">PO / Lot No.</td>
            <td>${n(c)}</td>
          </tr>
          <tr>
            <td class="label-cell">Supplier / Factory</td>
            <td>${n(i)}</td>
            <td class="label-cell">Model / Style</td>
            <td>${n(r)}</td>
            <td class="label-cell">Color / MTL</td>
            <td>${n(d)} / ${n(p)}</td>
          </tr>
          <tr>
            <td class="label-cell">Thickness (TAve)</td>
            <td><b>${((fe=t("#mf_TAve"))==null?void 0:fe.value)||"-"} mm</b></td>
            <td class="label-cell">Weight (WAve)</td>
            <td><b>${((ue=t("#mf_WAve"))==null?void 0:ue.value)||"-"} g/m²</b></td>
            <td class="label-cell">Technician</td>
            <td>${n(m)}</td>
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
              <span>${n(m)}</span>
              <span class="muted">${n(u)}</span>
            </div>
          </div>
          <div class="dtr-sheet-sign-box">
            <div>Reviewed &amp; Approved by (QA Lab Head):</div>
            <div class="dtr-sheet-sign-line">
              <span>${n(R)}</span>
              <span class="muted">${n(L||u)}</span>
            </div>
          </div>
        </div>
      </div>
    `;t("#dtr-print-modal-title").textContent=`Official Test Report Sheet — ${e}`,t("#dtr-print-sheet-container").innerHTML=X,(pe=t("#dtr-print-modal-backdrop"))==null||pe.classList.remove("hidden")};let Le=null;const Ma=async()=>{if(Le)return Le;try{const e=await fetch(I+"assets/images/bonding_sketches/sketch_configs.json");if(e.ok)return Le=await e.json(),Le}catch(e){console.warn("Failed to load sketch_configs.json",e)}return null},Oa=async()=>{var bt,ht,gt,Tt,yt,Ft,St,Lt,$t,Ct,Ht,Bt,jt,Gt,zt,qt,Qt,Xt,Jt,Yt,Kt,Zt,te,ee,ae,se,le,oe,re,ie,ne,ce,de,fe,ue,pe,me,ve,_e,$e,It,be;const e=parseInt(((bt=t("#bf_BondingTestID"))==null?void 0:bt.value)||(v==null?void 0:v.BondingTestID)||0)||0,a=((ht=t("#bf_LabRefNo"))==null?void 0:ht.value)||"DTP-BOND-PREVIEW",s=((gt=t("#bf_ETRFNo"))==null?void 0:gt.value)||"-",o=((Tt=t("#bf_ModelStyle"))==null?void 0:Tt.value)||"-",l=((yt=t("#bf_Color"))==null?void 0:yt.value)||"-",i=((Ft=t("#bf_Side"))==null?void 0:Ft.value)||"Left",r=i.toLowerCase()==="left",d=(((St=t("#bf_AgeCategory"))==null?void 0:St.value)||"ADULT").toUpperCase(),c=parseFloat(((Lt=t("#bf_STD"))==null?void 0:Lt.value)||"2.5")||2.5,p=(($t=t("#bf_POLotNO"))==null?void 0:$t.value)||"-",u=((Ct=t("#bf_POQuantity"))==null?void 0:Ct.value)||"-",m=((Ht=t("#bf_Factory"))==null?void 0:Ht.value)||"DATIAN SUBIC SHOES INC. (DTP)",R=((Bt=t("#bf_AdhesiveSupplier"))==null?void 0:Bt.value)||"-",L=((jt=t("#bf_ShippedDate"))==null?void 0:jt.value)||"-",F=((Gt=t("#bf_ProductionDate"))==null?void 0:Gt.value)||"-",$=((zt=t("#bf_Sendby"))==null?void 0:zt.value)||"-",T=((qt=t("#bf_MaterialSource"))==null?void 0:qt.value)||"Prod. Shoes",k=((Qt=t("#bf_ModeOfFailure"))==null?void 0:Qt.value)||"-",X=((Xt=t("#bf_PossibleCause"))==null?void 0:Xt.value)||"-",x=((Jt=t("#bf_Remarks"))==null?void 0:Jt.value)||((Yt=t("#bf_LabNotesComments"))==null?void 0:Yt.value)||"",E=((Kt=t("#bf_ReceivedDate"))==null?void 0:Kt.value)||"-",M=((Zt=t("#bf_TestedDate"))==null?void 0:Zt.value)||new Date().toISOString().slice(0,10),N=((te=t("#bf_FinishedDate"))==null?void 0:te.value)||M,J=((ee=t("#bf_Technician"))==null?void 0:ee.value)||"L. Aquidado",it=((ae=t("#bf_ReviewedBy"))==null?void 0:ae.value)||"QA Lead",U=(((se=t("#bf_Result"))==null?void 0:se.value)||"PASS").toUpperCase(),Y=U==="PASS"?"":U==="TOLERABLE"?"stamp-tol":"stamp-fail",G=((le=t("#bp_UTFMin"))==null?void 0:le.value)||(v==null?void 0:v.UTFMin)||"-",K=((oe=t("#bp_UTFMax"))==null?void 0:oe.value)||(v==null?void 0:v.UTFMax)||"-",ft=((re=t("#bp_UTFAverage"))==null?void 0:re.value)||(v==null?void 0:v.UTFAverage)||"-",Pt=(((ie=t("#bp_UTF_Result"))==null?void 0:ie.value)||(v==null?void 0:v.UTF_Result)||"-").toUpperCase(),Rt=((ne=t("#bp_FTOMin"))==null?void 0:ne.value)||(v==null?void 0:v.FTOMin)||"-",At=((ce=t("#bp_FTOMax"))==null?void 0:ce.value)||(v==null?void 0:v.FTOMax)||"-",Ut=((de=t("#bp_FTOAverage"))==null?void 0:de.value)||(v==null?void 0:v.FTOAverage)||"-",Et=(((fe=t("#bp_FTO_Result"))==null?void 0:fe.value)||(v==null?void 0:v.FTO_Result)||"-").toUpperCase(),xt=((ue=t("#bp_FiTOMin"))==null?void 0:ue.value)||(v==null?void 0:v.FiTOMin)||"-",ut=((pe=t("#bp_FiTOMax"))==null?void 0:pe.value)||(v==null?void 0:v.FiTOMax)||"-",Vt=((me=t("#bp_FiTOAverage"))==null?void 0:me.value)||(v==null?void 0:v.FiTOAverage)||"-",Dt=(((ve=t("#bp_FiTO_Result"))==null?void 0:ve.value)||(v==null?void 0:v.FiTO_Result)||"-").toUpperCase(),V={},z={},Z={};let pt=0;for(let f=1;f<=35;f++){const _=((_e=t("#bp_UTFpoint"+f))==null?void 0:_e.value)||(v==null?void 0:v["UTFpoint"+f])||"",b=(($e=t("#bp_FTOpoint"+f))==null?void 0:$e.value)||(v==null?void 0:v["FTOpoint"+f])||"";V[f]=_,z[f]=b,(parseFloat(_)>0||parseFloat(b)>0)&&(pt=f),f<=13&&(Z[f]=((It=t("#bp_FiTO"+f))==null?void 0:It.value)||(v==null?void 0:v["FiTO"+f])||"")}const nt=await Ma();let q=parseInt((v==null?void 0:v.MaxPoints)||pt||27);(!nt||!nt[q])&&(q=[26,27,28,29,30,31,32,33,34].reduce((_,b)=>Math.abs(b-q)<Math.abs(_-q)?b:_,27));let y=nt?nt[q]:null,mt=[],H=null;if(y)try{const f=await W("dtr/layout?slug=bonding&variant="+encodeURIComponent(q)),_=f&&f.layout;_&&(y=JSON.parse(JSON.stringify(y)),_.image&&Object.assign(y.image,_.image),["utf","fto"].forEach(b=>{Object.keys(_[b]||{}).forEach(h=>{_[b][h].hidden?delete y[b][h]:y[b][h]=_[b][h]})}),mt=_.extras||[],(_.vector||_.start)&&(H=_),_.vector&&(y.image.file=_.imageFile||"blank.png"))}catch{}const B=(f,_,b=!1)=>{const h=parseFloat(f);return!Number.isFinite(h)||h<=0?"color:#94a3b8":b?h<1?"color:#dc2626;font-weight:800;":h<1.2?"color:#d97706;font-weight:800;":"color:#1d4ed8;font-weight:800;":_>=3?h<2?"color:#dc2626;font-weight:800;":h<3?"color:#d97706;font-weight:800;":"color:#1d4ed8;font-weight:800;":_>=2.5?h<1.8?"color:#dc2626;font-weight:800;":h<2.5?"color:#d97706;font-weight:800;":"color:#1d4ed8;font-weight:800;":h<1.5?"color:#dc2626;font-weight:800;":h<2?"color:#d97706;font-weight:800;":"color:#1d4ed8;font-weight:800;"};let D="";if(y){D+=`
        <img src="${I}assets/images/bonding_sketches/${y.image.file}" 
             class="vf-shoe-img" 
             style="left:${y.image.pctLeft}%; top:${y.image.pctTop}%; width:${y.image.pctWidth}%; height:${y.image.pctHeight}%;" 
             alt="${i} Shoe Sketch">
      `;for(let f=1;f<=y.pointsCount;f++){const _=y.utf[f];if(_){const h=V[f],S=parseFloat(h),O=Number.isFinite(S)&&S>0,j=B(h,c,!1);D+=`
            <div class="vf-point-box vf-point-utf" 
                 style="left:${_.pctLeft}%; top:${_.pctTop}%; width:${_.pctWidth}%; height:${_.pctHeight}%; ${j}" 
                 title="UTF Point ${f}: ${O?S.toFixed(2):"-"}">
              ${O?S.toFixed(2):""}
            </div>
          `}const b=y.fto[f];if(b){const h=z[f],S=parseFloat(h),O=Number.isFinite(S)&&S>0,j=B(h,c,!1);D+=`
            <div class="vf-point-box vf-point-fto" 
                 style="left:${b.pctLeft}%; top:${b.pctTop}%; width:${b.pctWidth}%; height:${b.pctHeight}%; ${j}" 
                 title="FTO Point ${f}: ${O?S.toFixed(2):"-"}">
              ${O?S.toFixed(2):""}
            </div>
          `}}if(H){let f='<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none">',_="";if(H.vector&&["utf","fto"].forEach(b=>Object.keys(y[b]).forEach(h=>{const S=y[b][h],O=((H.dots||{})[b]||{})[h];if(!O)return;const j=S.pctLeft+S.pctWidth,Ce=S.pctTop+S.pctHeight/2,Q=O.x<S.pctLeft?S.pctLeft:O.x>j?j:S.pctLeft+S.pctWidth/2,he=Q===S.pctLeft||Q===j?Ce:O.y<S.pctTop?S.pctTop:S.pctTop+S.pctHeight,Ca=b==="utf"?"#2563eb":"#7c3aed";f+=`<line x1="${Q}" y1="${he}" x2="${O.x}" y2="${O.y}" stroke="${Ca}" stroke-width="1.2" vector-effect="non-scaling-stroke"/>`,_+=`<div style="position:absolute;left:${O.x}%;top:${O.y}%;width:7px;height:7px;margin:-3.5px 0 0 -3.5px;border-radius:50%;background:${Ca}"></div>`})),H.start){const b=H.start;_+=`<div style="position:absolute;left:${b.x}%;top:${b.y}%;transform:translate(-50%,-50%);display:flex;align-items:center;gap:3px;white-space:nowrap;color:#b91c1c;font:800 9px sans-serif"><i style="display:block;width:9px;height:9px;background:#dc2626;transform:rotate(45deg)"></i>${n(b.label||"START")}</div>`}D+=f+"</svg>"+_}mt.forEach(f=>{var O;const _=`left:${f.pctLeft}%; top:${f.pctTop}%; width:${f.pctWidth}%; height:${f.pctHeight}%;`,b=`color:${n(f.color||"#111")}; font-size:${Number(f.size)||12}px; font-weight:${f.bold?700:400};`;let h="";if(f.type==="text")h=n(f.text);else if(f.type==="field")h=n(((O=t("#bf_"+f.field))==null?void 0:O.value)||"");else if(f.type==="image"&&f.src)h=`<img src="${I}${n(f.src)}" style="max-width:100%;max-height:100%" alt="">`;else if(f.type==="chart"){let j=0;for(let Q=1;Q<=y.pointsCount;Q++)j=Math.max(j,parseFloat(V[Q])||0);let Ce="";for(let Q=1;Q<=y.pointsCount;Q++){const he=parseFloat(V[Q])||0;Ce+=`<i style="flex:1;display:block;min-height:1px;height:${j?he/j*100:0}%;background:${he<2?"#dc2626":he<3?"#d97706":"#1d4ed8"}"></i>`}h=`<div style="display:flex;align-items:flex-end;gap:1px;width:100%;height:100%">${Ce}</div>`}const S=f.type==="line"?`background:${n(f.color||"#111")};`:"";D+=`<div style="position:absolute;display:flex;align-items:center;overflow:hidden;${_}${b}${S}">${h}</div>`})}else D='<div style="padding:20px;text-align:center;color:#64748b">Shoe sketch unavailable</div>';const vt=`
      <img src="${I}assets/images/bonding_sketches/blank.png" 
           class="vf-shoe-img" 
           style="left:12%; top:8%; width:76%; height:84%; opacity:0.85;" 
           alt="Unmeasured Shoe Contour">
    `;let ct="";for(let f=1;f<=13;f++){const _=Z[f],b=parseFloat(_),h=Number.isFinite(b)&&b>0,S=B(_,1,!0);ct+=`
        <div class="vf-center-item" style="${S}">
          ${h?b.toFixed(2):"&nbsp;"}
        </div>
      `}const Mt=r?D:vt,Ot=r?vt:D,kt=r?ct:"",Nt=r?"":ct,dt=f=>f==="PASS"?{pass:"PASS",fail:"",passCls:"vf-rate-pass",failCls:""}:f==="TOLERABLE"?{pass:"TOLE",fail:"",passCls:"vf-rate-tol",failCls:""}:f==="FAILED"?{pass:"",fail:"FAIL",passCls:"",failCls:"vf-rate-fail"}:{pass:"-",fail:"",passCls:"",failCls:""},tt=dt(Pt),et=dt(Et),at=dt(Dt);let _t="";for(let f=1;f<=(y?y.pointsCount:27);f++){const _=V[f]?parseFloat(V[f]).toFixed(2):"-",b=z[f]?parseFloat(z[f]).toFixed(2):"-",h=f<=13&&Z[f]?parseFloat(Z[f]).toFixed(2):"-";_t+=`
        <tr>
          <td><b>Point ${f}</b></td>
          <td style="${B(V[f],c,!1)}">${_}</td>
          <td style="${B(z[f],c,!1)}">${b}</td>
          <td style="${f<=13?B(Z[f],1,!0):"color:#94a3b8"}">${h}</td>
        </tr>
      `}const Wt=`
      <div class="vf-report-sheet">
        <!-- Row 1: VF logo | test method name | SATRA logo, all on one line -->
        <div class="rpt-top">
          <img class="rpt-logo-vf" src="${I}assets/images/vf-logo.webp" alt="VF">
          <div class="rpt-top-title">
            <h1 class="vf-title-en">Bonding Test Results</h1>
            <h2 class="vf-title-cn">胶着力测试</h2>
          </div>
          <img class="rpt-logo-satra" src="${I}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
        </div>

        <!-- Row 2: form no / ETRF / lab ref + sample category (left), result + dates (right) -->
        <div class="rpt-sub">
          <div class="rpt-sub-left">
            <div class="vf-form-no rpt-formno">Form # : Lab VF 001</div>
            <div class="vf-etrf-row">
              <span>ETRF No:</span>
              <span class="vf-val-underlined">${n(s)}</span>
            </div>
            <div class="vf-labref-row">
              <span>Lab Ref.No:</span>
              <span class="vf-labref-val">${n(a)}</span>
            </div>
            <div class="vf-test-categories">
              <div>
                <span class="vf-radio ${T==="Prod. Shoes"||!T?"checked":""}"></span> Pro. Shoes 量产鞋
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${T==="Pro. Re-test"?"checked":""}"></span> Pro. Re-test 量产重测
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${T==="NewDevelopment"?"checked":""}"></span> NewDevelopment 开发
              </div>
              <div class="vf-tech-subradios">
                <span>○ Tech 1</span> <span>○ Tech 2</span> <span>○ Tech 3</span> <span>○ Tech 4</span>
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${T==="Sales Sample"?"checked":""}"></span> Sales Sample 销样
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${T==="Trial experiment"?"checked":""}"></span> 试胶试验
              </div>
            </div>
          </div>

          <div class="rpt-sub-right">
            <div class="vf-stamp-area">
              <span class="vf-stamp ${Y}">${n(U)}</span>
            </div>
            <table class="vf-dates-table">
              <tr><td>Received Date 收到日期:</td><td class="vf-date-val">${n(E)}</td></tr>
              <tr><td>Tested Date 测试日期:</td><td class="vf-date-val">${n(M)}</td></tr>
              <tr><td>Finished Date 完成日期:</td><td class="vf-date-val">${n(N)}</td></tr>
              <tr><td>Technician 技术员:</td><td class="vf-date-val">${n(J)}</td></tr>
            </table>
          </div>
        </div>

        <!-- Factory & Spec Details Grid -->
        <table class="vf-meta-grid">
          <tr>
            <td class="vf-lbl">Factory (工厂):</td>
            <td class="vf-val" colspan="3">${n(m)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Adhesive supplier 胶水供应商:</td>
            <td class="vf-val">${n(R)}</td>
            <td class="vf-lbl">Shipped Date (出货日期):</td>
            <td class="vf-val">${n(L)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Model/Style/PGSC (英文鞋型):</td>
            <td class="vf-val">${n(o)}</td>
            <td class="vf-lbl">Color (颜色):</td>
            <td class="vf-val">${n(l)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Production Date (生产日期):</td>
            <td class="vf-val">${n(F!=="-"?F:M)}</td>
            <td class="vf-lbl">PO/Lot No (订单号码):</td>
            <td class="vf-val">${n(p)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">PO Quantity (订单数量):</td>
            <td class="vf-val">${n(u)}</td>
            <td class="vf-lbl">Send by (送达):</td>
            <td class="vf-val">${n($)}</td>
          </tr>
        </table>

        <!-- 3-Part Evaluation Table with Mode of Failure & Possible Causes -->
        <table class="vf-eval-table">
          <thead>
            <tr>
              <th rowspan="2" style="width:20%">Shoe Part</th>
              <th rowspan="2" style="width:11%">VF Test<br>Method</th>
              <th rowspan="2" style="width:8%">STD<br>kgf/cm</th>
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
              <td class="font-bold text-blue">${c}</td>
              <td class="font-bold text-blue">${G!=="-"?Number(G).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${K!=="-"?Number(K).toFixed(2):"-"}</td>
              <td><span class="${tt.passCls}">${tt.pass}</span></td>
              <td><span class="${tt.failCls}">${tt.fail}</span></td>
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
              <td class="font-bold text-blue">${c}</td>
              <td class="font-bold text-blue">${Rt!=="-"?Number(Rt).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${At!=="-"?Number(At).toFixed(2):"-"}</td>
              <td><span class="${et.passCls}">${et.pass}</span></td>
              <td><span class="${et.failCls}">${et.fail}</span></td>
            </tr>
            <tr>
              <td class="text-left font-bold">Center : IS/OS<br>(中插 / 大底)</td>
              <td>VF-F01<br><span style="font-size:9.5px">(2019/05)</span></td>
              <td class="font-bold text-blue">1</td>
              <td class="font-bold text-blue">${xt!=="-"?Number(xt).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${ut!=="-"?Number(ut).toFixed(2):"-"}</td>
              <td><span class="${at.passCls}">${at.pass}</span></td>
              <td><span class="${at.failCls}">${at.fail}</span></td>
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
              <span style="${r?"color:#1d4ed8;font-weight:800":""}">Left</span>
              <span style="${r?"":"color:#1d4ed8;font-weight:800"}">Right</span>
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
            ${Mt}
          </div>

          <!-- Center Boxes -->
          <div class="vf-center-card">
            <div class="vf-center-cols">
              <div class="vf-center-box" style="${r?"border-color:#1d4ed8;background:#f8fafc":"border-color:#cbd5e1"}">
                ${kt}
              </div>
              <div class="vf-center-box" style="${r?"border-color:#cbd5e1":"border-color:#1d4ed8;background:#f8fafc"}">
                ${Nt}
              </div>
            </div>
          </div>

          <!-- Right Shoe Card -->
          <div class="vf-shoe-card">
            ${Ot}
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
              ${_t}
            </tbody>
          </table>
        </details>

        <!-- Signatures & Notes Footer -->
        <div class="vf-footer-grid">
          <div>
            Comments 备注:
            <div style="font-weight:normal;color:#334155;margin-top:4px;font-size:10.5px">
              ${n(x||"Tested in accordance with VF Laboratory Standards.")}
            </div>
          </div>
          <div>
            Reviewed by 审查:
            <div class="vf-sign-line">
              <span>${n(it)}</span>
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
    `;t("#dtr-print-modal-title").textContent=`Whole Shoe Bonding Test Report — ${a} (${i})`,t("#dtr-print-sheet-container").innerHTML=Wt;const st=t("#btn-dtr-download-access-pdf");st&&(e>0?(st.href=`${I}dtr/bonding-access-pdf?id=${e}&side=${encodeURIComponent(i)}`,st.classList.remove("hidden")):st.classList.add("hidden")),(be=t("#dtr-print-modal-backdrop"))==null||be.classList.remove("hidden")},ke=e=>{const a=748.3464566929134,s=285/25.4*96,o=document.createElement("div");o.className="dtr-fit-probe",o.style.cssText="position:absolute;left:-99999px;top:0;visibility:hidden;",document.body.appendChild(o);let l=1;for(let i=0;i<4;i++){o.style.width=a/l+"px",o.innerHTML=e;const r=o.scrollHeight*l,d=Math.min(1,l*(s/Math.max(r,1))*.985);if(Math.abs(d-l)<.005){l=d;break}l=d}return o.remove(),Math.max(.4,Math.round(l*1e3)/1e3)};(Ta=t("#btn-dtr-print-preview"))==null||Ta.addEventListener("click",xa),(ya=t("#btn-bond-print-preview"))==null||ya.addEventListener("click",Oa),(Fa=t("#btn-dtr-close-print-modal"))==null||Fa.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(Sa=t("#btn-dtr-cancel-print"))==null||Sa.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(La=t("#btn-dtr-download-pdf"))==null||La.addEventListener("click",async()=>{var o,l,i,r,d,c;const e=t("#btn-dtr-download-pdf"),a=t("#dtr-print-sheet-container");if(!a||!e)return;const s=e.textContent;e.disabled=!0,e.textContent="Creating PDF...";try{const p=await fetch(I+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((o=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:o.content)||""},body:JSON.stringify({access_id:parseInt(((i=(((l=t("#btn-dtr-download-access-pdf"))==null?void 0:l.href)||"").match(/[?&]id=(\d+)/))==null?void 0:i[1])||0,10)&&!t("#btn-dtr-download-access-pdf").classList.contains("hidden")?parseInt(t("#btn-dtr-download-access-pdf").href.match(/[?&]id=(\d+)/)[1],10):0,side:((((r=t("#btn-dtr-download-access-pdf"))==null?void 0:r.href)||"").match(/side=(\w+)/)||[])[1]||"Left",scale:ke(a.innerHTML),html:a.innerHTML,name:(((d=t("#dtr-print-modal-title"))==null?void 0:d.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_")})});if(!p.ok)throw new Error((await p.text()).slice(0,200)||"PDF failed");const u=await p.blob(),m=document.createElement("a");m.href=URL.createObjectURL(u),m.download=((((c=t("#dtr-print-modal-title"))==null?void 0:c.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_").trim()||"Report")+".pdf",document.body.appendChild(m),m.click(),m.remove(),setTimeout(()=>URL.revokeObjectURL(m.href),4e3)}catch(p){nxToast("Could not create the PDF: "+p.message,"error",7e3)}e.disabled=!1,e.textContent=s}),($a=t("#btn-dtr-do-print"))==null||$a.addEventListener("click",()=>{const e=t("#dtr-print-sheet-container");if(!e){window.print();return}const a=document.createElement("div");a.id="dtr-print-root",a.innerHTML=e.innerHTML,a.style.zoom=String(ke(e.innerHTML)),document.body.appendChild(a),document.body.classList.add("dtr-printing");const s=()=>{document.body.classList.remove("dtr-printing"),a.remove(),window.removeEventListener("afterprint",s)};window.addEventListener("afterprint",s),window.print()});const ka=async()=>{var e,a;try{const s=await W("dtr/layout?slug=bonding&variant=form"),o=s&&s.layout,l=t("#dtr-bonding-form");if(!o||!l)return;const i=d=>{var c;return(c=l.querySelector(`[name="${d}"]`))==null?void 0:c.closest(".dtr-field")};(o.customFields||[]).forEach(d=>{var u,m;if(l.querySelector(`[name="${d.key}"]`))return;const c=(m=(u=t("#bf_LabRefNo"))==null?void 0:u.closest(".dtr-field"))==null?void 0:m.parentElement;if(!c)return;const p=document.createElement("div");p.className="dtr-field",p.innerHTML=`<label>${n(d.label)}</label><input type="text" name="${n(d.key)}" id="bf_${n(d.key)}">`,c.appendChild(p)}),Object.entries(o.labels||{}).forEach(([d,c])=>{const p=i(d),u=p&&p.querySelector("label");u&&c&&(u.textContent=c)});const r=(a=(e=t("#bf_LabRefNo"))==null?void 0:e.closest(".dtr-field"))==null?void 0:a.parentElement;r&&(o.order||[]).length&&(o.order.forEach(d=>{const c=i(d);c&&c.parentElement===r&&r.appendChild(c)}),(o.customFields||[]).forEach(d=>{const c=i(d.key);c&&r.appendChild(c)})),(o.hiddenFields||[]).forEach(d=>{const c=i(d);c&&(c.style.display="none")})}catch{}};Ve(),ye("fabric"),Fe("fabric",0),ka().then(()=>{xe(),Me(0)}),Oe(),(()=>{const e=new URLSearchParams(location.search),[a,s]=String(e.get("open")||"").split(":");!a||!/^[a-z]+$/.test(a)||!/^\d+$/.test(s||"")||setTimeout(async()=>{var i,r;const o=t("#dtr-tracker-tbody")||document.body,l=document.createElement("button");if(l.type="button",l.hidden=!0,l.dataset.openRecord=a,l.dataset.sourceId=s,o.appendChild(l),l.click(),l.remove(),e.get("preview")==="1"&&(await new Promise(d=>setTimeout(d,2200)),(i=t(a==="bonding"?"#btn-bond-print-preview":"#btn-dtr-print-preview"))==null||i.click()),e.get("export")==="1"&&window.parent!==window){const d=c=>window.parent.postMessage(Object.assign({type:"NXL_REPORT_PDF"},c),location.origin);try{await new Promise(F=>setTimeout(F,800));const c=t("#dtr-print-sheet-container");if(!c||!c.innerHTML.trim())throw new Error("The report sheet could not be prepared.");const p=t("#btn-dtr-download-access-pdf"),u=p&&!p.classList.contains("hidden")?parseInt(((p.href||"").match(/[?&]id=(\d+)/)||[])[1]||0,10):0,m=await fetch(I+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((r=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:r.content)||""},body:JSON.stringify({access_id:u,side:(((p==null?void 0:p.href)||"").match(/side=(\w+)/)||[])[1]||"Left",scale:ke(c.innerHTML),html:c.innerHTML,name:"Report"})});if(!m.ok)throw new Error((await m.text()).slice(0,200)||"PDF failed");const R=new Uint8Array(await m.arrayBuffer());let L="";for(let F=0;F<R.length;F+=32768)L+=String.fromCharCode.apply(null,R.subarray(F,F+32768));d({ok:!0,b64:btoa(L)})}catch(c){d({ok:!1,error:c.message})}}},900)})()})();
