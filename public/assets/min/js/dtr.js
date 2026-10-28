(()=>{"use strict";var Xe,Je,Ke,Ye,Ze,ta,ea,aa,sa,la,ra,oa,na,ia,ca,da,fa,pa,ua,ma,va,_a,ba,ha,ga,Ta,ya,Sa,Fa,La,$a,Ca,wa,Ra,Aa,Ea,xa,Ma;const t=(e,a=document)=>a.querySelector(e),R=(e,a=document)=>[...a.querySelectorAll(e)],Ve=t('meta[name="base-url"]'),X=Ve?Ve.content.replace(/\/+$/,"")+"/":location.origin+"/";let lt="fabric",xe={},v={};const C=(e,a=!1)=>{const s=t("#dtr-toast");s&&(s.textContent=e,s.style.background=a?"#dc2626":"#0f172a",s.classList.remove("hidden"),clearTimeout(s._timer),s._timer=setTimeout(()=>s.classList.add("hidden"),4200))},o=e=>String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"),P=async(e,a={})=>(await fetch(X+e.replace(/^\/+/,""),a)).json(),T=(e,a=2)=>{const s=Number(e);if(!Number.isFinite(s))return 0;const r=Math.pow(10,a);return Math.round((s+Number.EPSILON)*r)/r},x=e=>{if(!e)return;const a=String(e.value||"").trim().toUpperCase();e.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),a==="PASS"?e.classList.add("dtr-rate-pass"):a==="TOLERABLE"?e.classList.add("dtr-rate-warn"):(a==="FAIL"||a==="ERROR")&&e.classList.add("dtr-rate-fail")},rt=e=>{R("[data-dtr-tab]").forEach(a=>{a.classList.toggle("active",a.dataset.dtrTab===e)}),["tracker","material","bonding","calculators"].forEach(a=>{var s;(s=t(`#dtr-panel-${a}`))==null||s.classList.toggle("hidden",a!==e)})};R("[data-dtr-tab]").forEach(e=>{e.addEventListener("click",()=>rt(e.dataset.dtrTab))}),R("[data-goto-calc]").forEach(e=>{e.addEventListener("click",()=>{rt("calculators");const a=t(`#calc-card-${e.dataset.gotoCalc}`);a&&a.scrollIntoView({behavior:"smooth",block:"center"})})});const Be=async(e="all",a="")=>{const s=t("#dtr-tracker-tbody");try{s&&window.nxState&&nxState(s,"loading","Loading lab reports…",16);const r=await P(`dtr/records?slug=${encodeURIComponent(e)}&q=${encodeURIComponent(a)}`);if(!s||!r.rows)return;if(!r.rows.length){nxState(s,"empty","No lab reports match your search. Clear the filters or try another Lab Ref No.",16);return}s.innerHTML=r.rows.map(l=>{const d=String(l.overall_result||"PASS").toUpperCase(),n=d==="FAIL"?"b-err":d==="TOLERABLE"?"b-warn":"b-ok";return`
          <tr>
            <td style="white-space:nowrap">
              <button type="button" class="dtr-btn-xs dtr-btn-primary" data-open-record="${o(l.form_slug)}" data-source-id="${o(l.source_id)}" data-labref="${o(l.lab_ref_no)}">
                Open Form
              </button>
            </td>
            <td class="mono"><b>${o(l.lab_ref_no)}</b></td>
            <td class="mono">${o(l.tr_no||"-")}</td>
            <td><span class="dtr-tag">${o(l.form_category)}</span></td>
            <td>${o(l.material_type||"-")}</td>
            <td class="mono">${o(l.material_code||"-")}</td>
            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${o(l.material_description||"")}">${o(l.material_description||"-")}</td>
            <td>${o(l.supplier||"-")}</td>
            <td class="mono">${o(l.model_style||"-")}</td>
            <td>${o(l.color||"-")}</td>
            <td class="mono">${o(l.po_lot_no||"-")}</td>
            <td class="mono">${o(l.mtl_no||"-")}</td>
            <td>${o(l.tested_date||l.received_date||"-")}</td>
            <td>${o(l.technician||"-")}</td>
            <td>${o(l.status||"Complete")}</td>
            <td><span class="badge ${n}">${o(d)}</span></td>
          </tr>
        `}).join("")}catch(r){console.error(r),s&&window.nxState&&nxState(s,"error","The list could not be loaded. Check your connection and try again.",16)}};(Xe=t("#dtr-tracker-pills"))==null||Xe.addEventListener("click",e=>{var r;const a=e.target.closest("[data-slug]");if(!a)return;R("#dtr-tracker-pills .dtr-pill").forEach(l=>l.classList.remove("active")),a.classList.add("active");const s=((r=t("#dtr-tracker-search"))==null?void 0:r.value.trim())||"";Be(a.dataset.slug,s)}),(Je=t("#dtr-tracker-btn-search"))==null||Je.addEventListener("click",()=>{var r;const e=t("#dtr-tracker-pills .dtr-pill.active"),a=e?e.dataset.slug:"all",s=((r=t("#dtr-tracker-search"))==null?void 0:r.value.trim())||"";Be(a,s)}),(Ke=t("#dtr-tracker-search"))==null||Ke.addEventListener("keydown",e=>{var a;e.key==="Enter"&&(e.preventDefault(),(a=t("#dtr-tracker-btn-search"))==null||a.click())}),(Ye=t("#dtr-tracker-tbody"))==null||Ye.addEventListener("click",async e=>{const a=e.target.closest("[data-open-record]");if(!a)return;const s=a.dataset.openRecord,r=a.dataset.sourceId;if(s==="bonding")rt("bonding"),await Pe(r),await Ne(r);else{rt("material");const l=t("#dtr-mat-form-slug");l&&(l.value=s),lt=s,await $e(s,r),await Ce(s,r)}});const Me=(e=!0)=>{var n,f;const a=[];for(let i=1;i<=6;i++){const m=parseFloat(((n=t(`#mf_Thickness${i}`))==null?void 0:n.value)??"");Number.isFinite(m)&&m>0&&a.push(m)}if(!a.length)return;const s=Math.min(...a),r=Math.max(...a),l=T(a.reduce((i,m)=>i+m,0)/a.length,2);t("#mf_TMin")&&(t("#mf_TMin").value=s),t("#mf_TMax")&&(t("#mf_TMax").value=r),t("#mf_TAve")&&(t("#mf_TAve").value=l);const d=parseFloat(((f=t("#mf_T_S"))==null?void 0:f.value)??"");Number.isFinite(d)&&d>0&&t("#mf_TRating")&&(t("#mf_TRating").value=l>=d?"PASS":"FAIL",x(t("#mf_TRating"))),e&&Le()},ot=()=>{var n,f,i,m;const e=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_Weight${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);if(e.length>0&&t("#mf_WAve")){const p=T(e.reduce((F,L)=>F+L,0)/e.length,2);t("#mf_WAve").value=p;const u=parseFloat(((n=t("#mf_Weight_S"))==null?void 0:n.value)??"");Number.isFinite(u)&&u>0&&t("#mf_WRating")&&(t("#mf_WRating").value=p>=u?"PASS":"FAIL",x(t("#mf_WRating")))}const a=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_SG${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);if(a.length>0&&t("#mf_SG_Ave")){const p=T(a.reduce((F,L)=>F+L,0)/a.length,2);t("#mf_SG_Ave").value=p;const u=parseFloat(((f=t("#mf_SG_Std"))==null?void 0:f.value)??"");Number.isFinite(u)&&u>0&&t("#mf_SG_Rate")&&(t("#mf_SG_Rate").value=Math.abs(p-u)<=.02?"PASS":"FAIL",x(t("#mf_SG_Rate")))}const s=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_DIN_${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);s.length>0&&t("#mf_DIN_Rate")&&(t("#mf_DIN_Rate").value=s.some(p=>p>150)?"FAIL":"PASS",x(t("#mf_DIN_Rate")));const r=[1,2].map(p=>{var u;return parseFloat(((u=t(`#mf_RossFlex_${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);r.length>0&&t("#mf_RossFlex_Rate")&&(t("#mf_RossFlex_Rate").value=r.some(p=>p>50)?"FAIL":"PASS",x(t("#mf_RossFlex_Rate")));const l=parseFloat(((i=t("#mf_ShrinkageL"))==null?void 0:i.value)??""),d=parseFloat(((m=t("#mf_Shrinkage_S"))==null?void 0:m.value)??"");Number.isFinite(l)&&Number.isFinite(d)&&d>0&&t("#mf_ShrinkageLRating")&&(t("#mf_ShrinkageLRating").value=Math.abs(l)<=Math.abs(d)?"PASS":"FAIL",x(t("#mf_ShrinkageLRating")))},je={TensileL:{inputs:["mf_TensileL1","mf_TensileL2","mf_TensileL3"],ave:"mf_TensileLAve",std:"mf_Tensile_L",rate:"mf_TensileLRating",dec:2},TensileW:{inputs:["mf_TensileW1","mf_TensileW2","mf_TensileW3"],ave:"mf_TensileWAve",std:"mf_Tensile_W",rate:"mf_TensileWRating",dec:2},ElongationL:{inputs:["mf_ElongationL1","mf_ElongationL2","mf_ElongationL3"],ave:"mf_ElongationLAve",std:"mf_Elongation_L",rate:"mf_ElongationLRating",dec:2},ElongationW:{inputs:["mf_ElongationW1","mf_ElongationW2","mf_ElongationW3"],ave:"mf_ElongationWAve",std:"mf_Elongation_W",rate:"mf_ElongationWRating",dec:2},TrouserTearL:{inputs:["mf_TrouserTearL1","mf_TrouserTearL2","mf_TrouserTearL3"],ave:"mf_TrouserTearLAve",std:"mf_TrouserTear_L",rate:"mf_TrouserTearLRating",dec:2},TrouserTearW:{inputs:["mf_TrouserTearW1","mf_TrouserTearW2","mf_TrouserTearW3"],ave:"mf_TrouserTearWAve",std:"mf_TrouserTear_W",rate:"mf_TrouserTearWRating",dec:2},StitchTearL:{inputs:["mf_StitchTearL1","mf_StitchTearL2","mf_StitchTearL3"],ave:"mf_StitchTearLAve",std:"mf_StitchTear_L",rate:"mf_StitchTearLRating",dec:2},StitchTearW:{inputs:["mf_StitchTearW1","mf_StitchTearW2","mf_StitchTearW3"],ave:"mf_StitchTearWAve",std:"mf_StitchTear_W",rate:"mf_StitchTearWRating",dec:2},MullenBurst:{inputs:["mf_MullenBurst1","mf_MullenBurst2","mf_MullenBurst3"],ave:"mf_Text3977",std:"mf_MullenBurst",rate:"mf_MullenBurstRating",dec:4},DieC:{inputs:["mf_DieC_1","mf_DieC_2","mf_DieC_3"],ave:"mf_DieC_Ave",std:"mf_DieC_Std",rate:"mf_DieC_Rate",dec:2}},Ge=e=>{const a=je[e];if(!a)return;const s=a.inputs.map(i=>{var m;return parseFloat(((m=t(`#${i}`))==null?void 0:m.value)??"")}).filter(i=>Number.isFinite(i)),r=t(`#${a.ave}`),l=t(`#${a.std}`),d=t(`#${a.rate}`);if(!s.length)return;const n=T(s.reduce((i,m)=>i+m,0)/s.length,a.dec);r&&(r.value=n);const f=parseFloat((l==null?void 0:l.value)??"");Number.isFinite(f)&&f>0&&d&&(d.value=n>=f?"PASS":"FAIL",x(d))},Fe=()=>{Object.keys(je).forEach(Ge)},Oe={CTP:{fibers:["mf_CtPWool","mf_CtPAcrylic","mf_CtPPolyester","mf_CtPNylon","mf_CtPCotton","mf_CtPAcetate"],std:"mf_CTP",rate:"mf_CTPRating"},CTPB:{fibers:["mf_CtPBWool","mf_CtPBAcrylic","mf_CtPBPolyester","mf_CtPBNylon","mf_CtPBCotton","mf_CtPBAcetate"],std:"mf_CtPB_S",rate:"mf_CTPB_Rating"},CTW:{fibers:["mf_CtWWool","mf_CtWAcrylic","mf_CtWPolyester","mf_CtWNylon","mf_CtWCotton","mf_CtWAcetate"],std:"mf_CTW",rate:"mf_CTWRating"},CTWB:{fibers:["mf_CtWBackSideWool","mf_CtWBackSideAcrylic","mf_CtWBackSidePolyester","mf_CtWBackSideNylon","mf_CtWBackSideCotton","mf_CtWBackSideAcetate"],std:"mf_CtWB_S",rate:"mf_CtWB_Rating"}},ke=e=>{var d;const a=Oe[e];if(!a)return;const s=parseFloat(((d=t(`#${a.std}`))==null?void 0:d.value)??""),r=t(`#${a.rate}`);if(!Number.isFinite(s)||s<=0||!r)return;const l=a.fibers.map(n=>{var f;return parseFloat(((f=t(`#${n}`))==null?void 0:f.value)??"")});l.some(n=>!Number.isFinite(n))||(r.value=l.some(n=>n<s)?"FAIL":"PASS",x(r))},De=()=>{var y,O,U,H,M,D,V,J,nt,pt,B,it,A,j,Et;const e=parseFloat(((y=t("#mf_CtCDry"))==null?void 0:y.value)??""),a=parseFloat(((O=t("#mf_CtCDry_S"))==null?void 0:O.value)??"");Number.isFinite(e)&&Number.isFinite(a)&&a>0&&t("#mf_CTCDRYRating")&&(t("#mf_CTCDRYRating").value=e>=a?"PASS":"FAIL",x(t("#mf_CTCDRYRating")));const s=parseFloat(((U=t("#mf_CtCWet"))==null?void 0:U.value)??""),r=parseFloat(((H=t("#mf_CtCWet_S"))==null?void 0:H.value)??"");Number.isFinite(s)&&Number.isFinite(r)&&r>0&&t("#mf_CTCWETRating")&&(t("#mf_CTCWETRating").value=s>=r?"PASS":"FAIL",x(t("#mf_CTCWETRating")));const l=parseFloat(((M=t("#mf_UV"))==null?void 0:M.value)??""),d=parseFloat(((D=t("#mf_UV_S"))==null?void 0:D.value)??"");Number.isFinite(l)&&Number.isFinite(d)&&d>0&&t("#mf_UVRating")&&(t("#mf_UVRating").value=l>=d?"PASS":"FAIL",x(t("#mf_UVRating")));const n=parseFloat(((V=t("#mf_HeatAging1"))==null?void 0:V.value)??""),f=parseFloat(((J=t("#mf_HeatAging2"))==null?void 0:J.value)??""),i=parseFloat(((nt=t("#mf_HeatAging_S"))==null?void 0:nt.value)??"");Number.isFinite(n)&&Number.isFinite(f)&&Number.isFinite(i)&&i>0&&t("#mf_HeatAgingRating")&&(t("#mf_HeatAgingRating").value=n<i||f<i?"FAIL":"PASS",x(t("#mf_HeatAgingRating")));const m=parseFloat(((pt=t("#mf_Hydrolysis1"))==null?void 0:pt.value)??""),p=parseFloat(((B=t("#mf_Hydrolysis2"))==null?void 0:B.value)??""),u=parseFloat(((it=t("#mf_Hydrolysis_S"))==null?void 0:it.value)??"");Number.isFinite(m)&&Number.isFinite(p)&&Number.isFinite(u)&&u>0&&t("#mf_HydrolysisRating")&&(t("#mf_HydrolysisRating").value=m<u||p<u?"FAIL":"PASS",x(t("#mf_HydrolysisRating")));const F=parseFloat(((A=t("#mf_PVC_1"))==null?void 0:A.value)??""),L=parseFloat(((j=t("#mf_PVC_2"))==null?void 0:j.value)??""),$=parseFloat(((Et=t("#mf_PVC_S"))==null?void 0:Et.value)??"");Number.isFinite(F)&&Number.isFinite(L)&&Number.isFinite($)&&$>0&&t("#mf_PVC_Rating")&&(t("#mf_PVC_Rating").value=F<$||L<$?"FAIL":"PASS",x(t("#mf_PVC_Rating")))},N=()=>{var d;let e=!1,a=!1,s=!1;R("#dtr-material-form .dtr-rate-inp").forEach(n=>{x(n);const f=String(n.value||"").trim().toUpperCase();f==="FAIL"?a=!0:f==="TOLERABLE"?s=!0:f==="PASS"&&(e=!0)});let r="PASS";a?r="FAIL":s?r="TOLERABLE":e?r="PASS":(d=t("#mf_Remarks"))!=null&&d.value&&(r=String(t("#mf_Remarks").value).toUpperCase()),t("#mf_Remarks")&&(t("#mf_Remarks").value=r);const l=t("#dtr-stamp-badge");l&&(l.textContent=r,l.className="dtr-stamp "+(r==="FAIL"?"dtr-stamp-fail":r==="TOLERABLE"?"dtr-stamp-warn":"dtr-stamp-pass"))},Le=async()=>{var s,r;const e=((s=t("#mf_MaterialType"))==null?void 0:s.value.trim())||"",a=parseFloat(((r=t("#mf_TAve"))==null?void 0:r.value)??"0")||0;if(e)try{const l=await P(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`);l&&l.mapped&&Object.keys(l.mapped).forEach(d=>{const n=l.mapped[d],f=t(`#mf_${d}`)||t(`[name="${d}"]`,t("#dtr-material-form"));f&&(f.value=n)}),ot(),Fe(),Object.keys(Oe).forEach(ke),De(),N()}catch(l){console.warn("[DTR] applyStandardValuesSmart error:",l)}},Pa=async()=>{Me(!1),await Le(),C("✓ Executed ApplyStandardValuesSmart + Thickness + Directional Averages + 6-Point Colorfastness + EvaluateRatings.")},$e=async(e,a=null)=>{const s=t("#dtr-mat-record-select");try{s&&(s.innerHTML='<option value="">Loading records…</option>');const r=await P(`dtr/records?slug=${encodeURIComponent(e)}`);if(!s||!r.rows)return;if(!r.rows.length){s.innerHTML='<option value="">No records yet - press “+ New Record”</option>';return}s.innerHTML=r.rows.map(l=>`
        <option value="${o(l.source_id)}" ${String(l.source_id)===String(a)?"selected":""}>
          ${o(l.lab_ref_no)} · ${o(l.material_type||l.form_category)} · ${o(l.model_style||l.material_code||"")} [${o(l.overall_result||"Pass")}]
        </option>
      `).join("")}catch(r){console.error(r),s&&(s.innerHTML='<option value="">Could not load records - reload the page</option>'),window.nxToast&&nxToast("The record list could not be loaded.","error")}},Na=e=>{const a=t("#dtr-raw-cols-container"),s=t("#dtr-raw-col-count");if(!a)return;const r=Object.keys(e||{});s&&(s.textContent=r.length),a.innerHTML=r.map(l=>`
      <div class="dtr-field">
        <label title="${o(l)}"><code>${o(l)}</code></label>
        <input type="text" data-raw-col="${o(l)}" value="${o(e[l]??"")}" ${l==="ID"?'readonly class="dtr-ro"':""}>
      </div>
    `).join("")},ze=(e,a={})=>{xe=e||{},t("#dtr-active-form-label")&&a.label&&(t("#dtr-active-form-label").textContent=`${a.label}`),t("#dtr-active-labref-title")&&(t("#dtr-active-labref-title").textContent=`${e.LabRefNo||"New Report"} — ${e["Material Description"]||e.MaterialDescription||e.MaterialType||""}`),R("#dtr-material-form input, #dtr-material-form select").forEach(s=>{s.name&&(s.value="")}),Object.keys(e).forEach(s=>{const r=e[s]??"",l=t(`#mf_${s.replace(/[^a-zA-Z0-9_]/g,"")}`);l&&(l.value=r);const d=t(`[name="${CSS.escape(s)}"]`,t("#dtr-material-form"));d&&(d.value=r)}),t("#mf_MaterialDescription")&&!t("#mf_MaterialDescription").value&&(t("#mf_MaterialDescription").value=e["Material Description"]||e.MaterialDescription||""),t("#mf_SupplierFactory")&&!t("#mf_SupplierFactory").value&&(t("#mf_SupplierFactory").value=e["Supplier/Factory"]||e.Supplier||e.SupplierFactory||""),t("#mf_POLotNo")&&!t("#mf_POLotNo").value&&(t("#mf_POLotNo").value=e["PO/Lot No"]||e.POLotNo||""),t("#mf_ModelNoStyle")&&!t("#mf_ModelNoStyle").value&&(t("#mf_ModelNoStyle").value=e["Model No/Style"]||e.ModelNoStyle||""),t("#mf_POQuantity")&&!t("#mf_POQuantity").value&&(t("#mf_POQuantity").value=e["PO Quantity"]||e.POQuantity||""),t("#mf_TestedBy")&&!t("#mf_TestedBy").value&&(t("#mf_TestedBy").value=e["Tested by"]||e.TestedBy||e.Technician||""),Na(e),Me(!1),ot(),Fe(),Object.keys(Oe).forEach(ke),De(),N()},Ce=async(e,a=0)=>{try{const s=await P(`dtr/record?slug=${encodeURIComponent(e)}&source_id=${encodeURIComponent(a)}`);s&&s.ok&&ze(s.record||{},s.config||{})}catch(s){console.error(s)}};(Ze=t("#mf_LabRefNo"))==null||Ze.addEventListener("change",()=>{var s;const e=(((s=t("#mf_LabRefNo"))==null?void 0:s.value)||"").toUpperCase().trim(),a=t("#mf_MaterialType");!e||!a||(e.includes("FBU")||e.includes("FBL")?a.value||(a.value="Canvas"):e.includes("LEU")||e.includes("LEL")?a.value||(a.value="Suede"):e.includes("RB")?a.value="Rubber":e.includes("IB")?a.value="Inner Box":e.includes("OB")?a.value="Outer Box":e.includes("FML")?a.value="Sockliner - Latex":e.includes("FM")?a.value="PU Foam":e.includes("SKL")?a.value="Sockliner - EVA":e.includes("LC")?a.value="Shoe lace":e.includes("TH")?a.value="Thread":e.includes("EY")?a.value="Eyelet":e.includes("GR")?a.value="Grommet":e.includes("ZP")&&(a.value="Zipper"),Le())});const we=async()=>{var r,l,d;const e=((r=t("#mf_MaterialCode"))==null?void 0:r.value.trim())||"",a=((l=t("#mf_POLotNo"))==null?void 0:l.value.trim())||"",s=((d=t("#mf_MTL"))==null?void 0:d.value.trim())||"";if(!e&&!a&&!s){C("Enter a Material Code, PO/Lot No, or MTL # first.",!0);return}try{const n=await P(`dtr/lookup?material_code=${encodeURIComponent(e)}&po_lot_no=${encodeURIComponent(a)}&mtl_no=${encodeURIComponent(s)}`);let f=[];if(n.material&&(t("#mf_SupplierFactory")&&(t("#mf_SupplierFactory").value=n.material.Supplier||""),t("#mf_MaterialDescription")&&(t("#mf_MaterialDescription").value=n.material.MaterialName||""),f.push(`MaterialList (${n.material.MaterialCode})`)),n.shoe&&(t("#mf_ModelNoStyle")&&(t("#mf_ModelNoStyle").value=n.shoe.StyleNo||""),t("#mf_Color")&&(t("#mf_Color").value=n.shoe.Colorway||""),t("#mf_MTL")&&n.shoe.MTL&&(t("#mf_MTL").value=n.shoe.MTL),f.push(`ShoeList (${n.shoe.StyleNo})`)),n.dsr&&n.dsr.length>0){const i=n.dsr[0];t("#mf_StyleNumber_DSR")&&(t("#mf_StyleNumber_DSR").value=i.StyleNumber||""),t("#mf_StyleDescription_DSR")&&(t("#mf_StyleDescription_DSR").value=i.StyleDescription||""),t("#mf_Colorway_DSR")&&(t("#mf_Colorway_DSR").value=i.Colorway||""),t("#mf_ComponentorLocation_DSR")&&(t("#mf_ComponentorLocation_DSR").value=i.ComponentorLocation||""),t("#mf_VFMaterialCode_DSR")&&(t("#mf_VFMaterialCode_DSR").value=i.VFMaterialCode||""),t("#mf_InternalName_DSR")&&(t("#mf_InternalName_DSR").value=i["Internal NameVans1"]||i.Material||""),t("#mf_ColorName_DSR")&&(t("#mf_ColorName_DSR").value=i.ColorName||i.Color||""),f.push(`tblDSR (${n.dsr.length} component rows)`)}f.length?C(`✓ Auto-Lookup populated from: ${f.join(" · ")}`):C("No exact match found in MaterialList / ShoeList / tblDSR.",!0)}catch(n){console.error(n)}};(ta=t("#btn-dtr-lookup-all"))==null||ta.addEventListener("click",we),(ea=t("#mf_MaterialCode"))==null||ea.addEventListener("change",we),(aa=t("#mf_POLotNo"))==null||aa.addEventListener("change",we),(sa=t("#mf_MTL"))==null||sa.addEventListener("change",we),(la=t("#mf_MaterialType"))==null||la.addEventListener("change",Le),R(".dtr-calc-thick").forEach(e=>{e.addEventListener("input",()=>Me(!0))}),R(".dtr-calc-weight, .dtr-calc-sg, .dtr-calc-din, .dtr-calc-ross, .dtr-calc-shrink").forEach(e=>{e.addEventListener("input",()=>{ot(),N()})}),R(".dtr-dir-inp, .dtr-std-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset.dirGroup?Ge(e.dataset.dirGroup):Fe(),N()})}),R(".dtr-6pt-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset["6pt"]&&ke(e.dataset["6pt"]),N()})}),R(".dtr-crock-inp").forEach(e=>{e.addEventListener("input",()=>{De(),N()})}),R(".dtr-rate-inp").forEach(e=>{e.addEventListener("change",N),e.addEventListener("input",N)}),(ra=t("#dtr-mat-form-slug"))==null||ra.addEventListener("change",async e=>{lt=e.target.value,await $e(lt),await Ce(lt,0)}),(oa=t("#dtr-mat-record-select"))==null||oa.addEventListener("change",async e=>{const a=e.target.value;a&&await Ce(lt,a)}),(na=t("#btn-dtr-new"))==null||na.addEventListener("click",()=>{const e=new Date().toISOString().slice(0,10);ze({ID:"",LabRefNo:`DTP${e.replace(/-/g,"")}${lt.toUpperCase().slice(0,3)}-1`,TR:`TR${e.replace(/-/g,"")}001`,ReceivedDate:e,TestedDate:e,FinishedDate:e,Status:"Complete",Remarks:"PASS"}),C("Initialized new blank test report record.")}),(ia=t("#btn-dtr-review"))==null||ia.addEventListener("click",()=>{const e=prompt("Enter Reviewer Name:","Faye Ann Elaysa");if(!e)return;const a=t('[data-raw-col="AuthorizedBy"]');a&&(a.value=e);const s=t('[data-raw-col="AuthorizationStatus"]');s&&(s.value="Reviewed"),C(`✓ Record stamped Reviewed by ${e}`)}),(ca=t("#btn-dtr-refresh-calc"))==null||ca.addEventListener("click",Pa),(da=t("#btn-dtr-load-machine"))==null||da.addEventListener("click",async()=>{var a;const e=((a=t("#mf_LabRefNo"))==null?void 0:a.value.trim())||"";if(!e){C("Enter a LabRefNo first before loading machine results.",!0);return}try{const s=await P("dtr/load-machine",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:lt,lab_ref_no:e})});s&&s.loadedFields&&(Object.keys(s.loadedFields).forEach(r=>{const l=s.loadedFields[r],d=t(`#mf_${r}`)||t(`[name="${CSS.escape(r)}"]`,t("#dtr-material-form"));d&&(d.value=l);const n=t(`[data-raw-col="${CSS.escape(r)}"]`);n&&(n.value=l)}),ot(),Fe(),N(),t("#dtr-machine-status")&&(t("#dtr-machine-status").textContent=s.message||"Machine data loaded."),C(`${s.message}`))}catch(s){C("Machine loader error: "+(s.message||""),!0)}}),(fa=t("#btn-dtr-save"))==null||fa.addEventListener("click",async()=>{const e=Object.assign({},xe);R("#dtr-raw-cols-container [data-raw-col]").forEach(a=>{e[a.dataset.rawCol]=a.value}),R("#dtr-material-form [name]").forEach(a=>{a.name&&(e[a.name]=a.value)});try{const a=await P("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:lt,fields:e})});a&&a.ok?(t("#mf_ID")&&(t("#mf_ID").value=a.recordId),C(`✓ ${a.message} (Ready in InterLink Sync ComboBox!)`),await $e(lt,a.recordId)):C(a.error||"Save failed.",!0)}catch{C("Failed to save record.",!0)}}),(pa=t("#btn-toggle-raw-cols"))==null||pa.addEventListener("click",()=>{var e;(e=t("#dtr-raw-cols-container"))==null||e.classList.toggle("hidden")});const Wa={TODDLER:14,KIDS:16,CHILDREN:24,ADULT:35},Re=1,Ae=e=>Math.round(e*.8*100)/100,qe=(e,a)=>!Number.isFinite(e)||e<=0?null:e>=a?"PASS":e>=Ae(a)?"TOLERABLE":"FAIL",At=()=>{var p,u;const e=(((p=t("#bf_AgeCategory"))==null?void 0:p.value)||"ADULT").toUpperCase(),a=Wa[e]||35,s=parseFloat(((u=t("#bf_STD"))==null?void 0:u.value)??"2.5")||2.5;R("#dtr-bonding-points-tbody [data-spot-row]").forEach(F=>{const L=Number(F.dataset.spotRow);F.style.opacity=L<=a?"1":"0.45"});const r=(F,L,$)=>{const y=[];let O=!1,U=!1;for(let H=1;H<=L;H++){const M=t(`#bp_${F}${H}`);if(!M)continue;const D=parseFloat(M.value??"");if(M.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),Number.isFinite(D)&&D>0){y.push(D);const V=qe(D,$);V==="FAIL"?(O=!0,M.classList.add("dtr-rate-fail")):V==="TOLERABLE"?(U=!0,M.classList.add("dtr-rate-warn")):M.classList.add("dtr-rate-pass")}}return y.length?{min:T(Math.min(...y),2),max:T(Math.max(...y),2),ave:T(y.reduce((H,M)=>H+M,0)/y.length,2),res:O?"FAIL":U?"TOLERABLE":"PASS"}:{min:"",max:"",ave:"",res:""}},l=r("UTFpoint",a,s),d=r("FTOpoint",a,s),n=r("FiTO",13,Re);t("#bp_UTFMin")&&(t("#bp_UTFMin").value=l.min),t("#bp_UTFMax")&&(t("#bp_UTFMax").value=l.max),t("#bp_UTFAverage")&&(t("#bp_UTFAverage").value=l.ave),t("#bp_UTF_Result")&&(t("#bp_UTF_Result").value=l.res,x(t("#bp_UTF_Result"))),t("#bp_FTOMin")&&(t("#bp_FTOMin").value=d.min),t("#bp_FTOMax")&&(t("#bp_FTOMax").value=d.max),t("#bp_FTOAverage")&&(t("#bp_FTOAverage").value=d.ave),t("#bp_FTO_Result")&&(t("#bp_FTO_Result").value=d.res,x(t("#bp_FTO_Result"))),t("#bp_FiTOMin")&&(t("#bp_FiTOMin").value=n.min),t("#bp_FiTOMax")&&(t("#bp_FiTOMax").value=n.max),t("#bp_FiTOAverage")&&(t("#bp_FiTOAverage").value=n.ave),t("#bp_FiTO_Result")&&(t("#bp_FiTO_Result").value=n.res,x(t("#bp_FiTO_Result")));const f=[l.res,d.res,n.res].filter(Boolean);let i="PASS";f.includes("FAIL")?i="FAIL":f.includes("TOLERABLE")&&(i="TOLERABLE"),t("#bf_Result")&&(t("#bf_Result").value=i,x(t("#bf_Result")));const m=t("#bf-overall-badge");m&&(m.textContent=i,m.className="badge "+(i==="FAIL"?"b-err":i==="TOLERABLE"?"b-warn":"b-ok"))},Pe=async(e=null)=>{const a=t("#dtr-bonding-record-select");try{a&&(a.innerHTML='<option value="">Loading reports…</option>');const s=await P("dtr/records?slug=bonding");if(!a||!s.rows)return;if(!s.rows.length){a.innerHTML='<option value="">No bonding reports yet</option>';return}a.innerHTML=s.rows.map(r=>`
        <option value="${o(r.source_id)}" ${String(r.source_id)===String(e)?"selected":""}>
          ${o(r.lab_ref_no)} · ${o(r.model_style||"")} · ${o(r.color||"")} [${o(r.overall_result||"Pass")}]
        </option>
      `).join("")}catch(s){console.error(s),a&&(a.innerHTML='<option value="">Could not load reports - reload the page</option>'),window.nxToast&&nxToast("The bonding report list could not be loaded.","error")}},Ne=async(e=0)=>{try{const a=await P(`dtr/record?slug=bonding&source_id=${encodeURIComponent(e)}`);if(!a||!a.ok)return;const s=a.record||{},r=a.bondingPoints||{};v=r,Object.keys(s).forEach(l=>{const d=t(`#bf_${l}`);d&&(d.value=s[l]??"")}),R('#dtr-bonding-form [name^="cf_"]').forEach(l=>{l.value=(a.customValues||{})[l.name]??""});for(let l=1;l<=35;l++)t(`#bp_UTFpoint${l}`)&&(t(`#bp_UTFpoint${l}`).value=r[`UTFpoint${l}`]??""),t(`#bp_FTOpoint${l}`)&&(t(`#bp_FTOpoint${l}`).value=r[`FTOpoint${l}`]??""),l<=13&&t(`#bp_FiTO${l}`)&&(t(`#bp_FiTO${l}`).value=r[`FiTO${l}`]??"");At()}catch(a){console.error(a)}};(ua=t("#dtr-bonding-record-select"))==null||ua.addEventListener("change",e=>{e.target.value&&Ne(e.target.value)}),(ma=t("#bf_AgeCategory"))==null||ma.addEventListener("change",At),(va=t("#bf_STD"))==null||va.addEventListener("change",At),R("#dtr-bonding-points-tbody .dtr-bp-inp").forEach(e=>{e.addEventListener("input",At)}),(_a=t("#btn-bond-recalc"))==null||_a.addEventListener("click",()=>{At(),C("✓ Recalculated UTF, FTO, and FiTO Min/Max/Ave & Spot Tolerances.")}),(ba=t("#btn-bond-new"))==null||ba.addEventListener("click",()=>{const e=new Date().toISOString().slice(0,10);R("#dtr-bonding-form input").forEach(a=>{a.value=""}),t("#bf_LabRefNo")&&(t("#bf_LabRefNo").value=`DTP${e.replace(/-/g,"")}SHB01-R`),t("#bf_AgeCategory")&&(t("#bf_AgeCategory").value="ADULT"),t("#bf_STD")&&(t("#bf_STD").value="2.5"),t("#bf_ReceivedDate")&&(t("#bf_ReceivedDate").value=e),t("#bf_TestedDate")&&(t("#bf_TestedDate").value=e),t("#bf_FinishedDate")&&(t("#bf_FinishedDate").value=e),At(),C("Initialized new Whole Shoe Bonding Test Report.")}),(ha=t("#btn-bond-load-u60"))==null||ha.addEventListener("click",async()=>{var a;const e=((a=t("#bf_LabRefNo"))==null?void 0:a.value.trim())||"DTP20261024SHB11-R";try{const s=await P("dtr/load-machine",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"bonding",lab_ref_no:e})});s&&s.loadedFields&&(Object.keys(s.loadedFields).forEach(r=>{const l=t(`#bp_${r}`);l&&(l.value=s.loadedFields[r])}),At(),C(`${s.message}`))}catch{C("Failed to load U60 machine file.",!0)}}),(ga=t("#btn-bond-save"))==null||ga.addEventListener("click",async()=>{var s,r,l,d,n,f,i,m,p,u,F,L,$;const e={};R("#dtr-bonding-form [name]").forEach(y=>{y.name&&(e[y.name]=y.value)});const a={UTFMin:((s=t("#bp_UTFMin"))==null?void 0:s.value)||null,UTFMax:((r=t("#bp_UTFMax"))==null?void 0:r.value)||null,UTFAverage:((l=t("#bp_UTFAverage"))==null?void 0:l.value)||null,UTF_Result:((d=t("#bp_UTF_Result"))==null?void 0:d.value)||null,FTOMin:((n=t("#bp_FTOMin"))==null?void 0:n.value)||null,FTOMax:((f=t("#bp_FTOMax"))==null?void 0:f.value)||null,FTOAverage:((i=t("#bp_FTOAverage"))==null?void 0:i.value)||null,FTO_Result:((m=t("#bp_FTO_Result"))==null?void 0:m.value)||null,FiTOMin:((p=t("#bp_FiTOMin"))==null?void 0:p.value)||null,FiTOMax:((u=t("#bp_FiTOMax"))==null?void 0:u.value)||null,FiTOAverage:((F=t("#bp_FiTOAverage"))==null?void 0:F.value)||null,FiTO_Result:((L=t("#bp_FiTO_Result"))==null?void 0:L.value)||null,Result:(($=t("#bf_Result"))==null?void 0:$.value)||"PASS"};R("#dtr-bonding-points-tbody [data-bp-col]").forEach(y=>{a[y.dataset.bpCol]=y.value});try{const y=await P("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"bonding",fields:e,bondingPoints:a})});y&&y.ok&&(t("#bf_BondingTestID")&&(t("#bf_BondingTestID").value=y.recordId),C(`✓ ${y.message}`),await Pe(y.recordId))}catch{C("Error saving Bonding Report.",!0)}});const Qe=()=>{var vt,z,q,W,_t,dt,Dt,Pt,Nt,Wt,ft,et,at,st,bt,It,ht,gt,Tt,yt,St,Ft,Lt,$t,Ct,wt,Rt;const e=parseFloat(((vt=t("#calc_raw_w1"))==null?void 0:vt.value)??"0")||0,a=parseFloat(((z=t("#calc_raw_w2"))==null?void 0:z.value)??"0")||0,s=parseFloat(((q=t("#calc_raw_w3"))==null?void 0:q.value)??"0")||0,r=T(e/100*1e4,2),l=T(a/100*1e4,2),d=T(s/100*1e4,2),n=T((r+l+d)/3,2);t("#calc_res_w1")&&(t("#calc_res_w1").value=r),t("#calc_res_w2")&&(t("#calc_res_w2").value=l),t("#calc_res_w3")&&(t("#calc_res_w3").value=d),t("#calc_res_wave")&&(t("#calc_res_wave").textContent=n);const f=parseFloat(((W=t("#calc_sg_w1"))==null?void 0:W.value)??"0")||0,i=parseFloat(((_t=t("#calc_sg_w2"))==null?void 0:_t.value)??"0")||0,m=parseFloat(((dt=t("#calc_sg_w3"))==null?void 0:dt.value)??"0")||0,p=parseFloat(((Dt=t("#calc_sg_t1"))==null?void 0:Dt.value)??"1")||1,u=parseFloat(((Pt=t("#calc_sg_t2"))==null?void 0:Pt.value)??"1")||1,F=parseFloat(((Nt=t("#calc_sg_t3"))==null?void 0:Nt.value)??"1")||1,L=T(f/(100*(p/10)),3),$=T(i/(100*(u/10)),3),y=T(m/(100*(F/10)),3),O=T((L+$+y)/3,3);t("#calc_res_sg_ave")&&(t("#calc_res_sg_ave").textContent=`${L}, ${$}, ${y} (Ave: ${O})`);const U=parseFloat(((Wt=t("#calc_cp_b1"))==null?void 0:Wt.value)??"10")||10,H=parseFloat(((ft=t("#calc_cp_a1"))==null?void 0:ft.value)??"9")||9,M=parseFloat(((et=t("#calc_cp_b2"))==null?void 0:et.value)??"10")||10,D=parseFloat(((at=t("#calc_cp_a2"))==null?void 0:at.value)??"9")||9,V=parseFloat(((st=t("#calc_cp_a3"))==null?void 0:st.value)??"9")||9,J=parseFloat(((bt=t("#calc_cp_sp"))==null?void 0:bt.value)??"0")||0,nt=U-J!==0?U-J:1,pt=M-J!==0?M-J:1,B=T((U-H)/nt*100,1),it=T((M-D)/pt*100,1),A=T((U-V)/nt*100,1),j=T((B+it+A)/3,1);t("#calc_res_cp_ave")&&(t("#calc_res_cp_ave").textContent=`${j}% (${B}%, ${it}%, ${A}%)`);const Et=parseFloat(((It=t("#calc_din_wb"))==null?void 0:It.value)??"0")||0,Ht=parseFloat(((ht=t("#calc_din_wa"))==null?void 0:ht.value)??"0")||0,xt=parseFloat(((gt=t("#calc_din_sg"))==null?void 0:gt.value)??"1")||1,Mt=parseFloat(((Tt=t("#calc_din_sa"))==null?void 0:Tt.value)??"200")||200,Bt=T((Et-Ht)/xt*(200/Mt)*1e3,2);t("#calc_res_din")&&(t("#calc_res_din").textContent=Bt);const Ot=parseFloat(((yt=t("#calc_rf_li1"))==null?void 0:yt.value)??"2.5")||2.5,kt=parseFloat(((St=t("#calc_rf_lf1"))==null?void 0:St.value)??"2.5")||2.5,ut=parseFloat(((Ft=t("#calc_rf_li2"))==null?void 0:Ft.value)??"2.5")||2.5,jt=parseFloat(((Lt=t("#calc_rf_lf2"))==null?void 0:Lt.value)??"2.5")||2.5,Vt=T((kt-Ot)/Ot*100,2),G=T((jt-ut)/ut*100,2);t("#calc_res_rf")&&(t("#calc_res_rf").textContent=`${Vt}% / ${G}%`);const K=parseFloat((($t=t("#calc_sh_lb"))==null?void 0:$t.value)??"100")||100,tt=parseFloat(((Ct=t("#calc_sh_la"))==null?void 0:Ct.value)??"100")||100,mt=parseFloat(((wt=t("#calc_sh_wb"))==null?void 0:wt.value)??"100")||100,ct=parseFloat(((Rt=t("#calc_sh_wa"))==null?void 0:Rt.value)??"100")||100,Y=T((K-tt)/K*100,2),S=T((mt-ct)/mt*100,2);t("#calc_res_sh")&&(t("#calc_res_sh").textContent=`L: ${Y}% / W: ${S}%`)};R("#dtr-panel-calculators input").forEach(e=>{e.addEventListener("input",Qe)}),(Ta=t("#btn-apply-calc-weight"))==null||Ta.addEventListener("click",()=>{var e,a,s;t("#mf_Weight1")&&(t("#mf_Weight1").value=((e=t("#calc_res_w1"))==null?void 0:e.value)||""),t("#mf_Weight2")&&(t("#mf_Weight2").value=((a=t("#calc_res_w2"))==null?void 0:a.value)||""),t("#mf_Weight3")&&(t("#mf_Weight3").value=((s=t("#calc_res_w3"))==null?void 0:s.value)||""),ot(),N(),rt("material"),C("✓ Applied calculated Weight 1..3 and WAve to Material Form.")}),(ya=t("#btn-apply-calc-sg"))==null||ya.addEventListener("click",()=>{var n,f,i,m,p,u;const e=parseFloat(((n=t("#calc_sg_w1"))==null?void 0:n.value)??"0")||0,a=parseFloat(((f=t("#calc_sg_w2"))==null?void 0:f.value)??"0")||0,s=parseFloat(((i=t("#calc_sg_w3"))==null?void 0:i.value)??"0")||0,r=parseFloat(((m=t("#calc_sg_t1"))==null?void 0:m.value)??"1")||1,l=parseFloat(((p=t("#calc_sg_t2"))==null?void 0:p.value)??"1")||1,d=parseFloat(((u=t("#calc_sg_t3"))==null?void 0:u.value)??"1")||1;t("#mf_SG1")&&(t("#mf_SG1").value=T(e/(100*(r/10)),3)),t("#mf_SG2")&&(t("#mf_SG2").value=T(a/(100*(l/10)),3)),t("#mf_SG3")&&(t("#mf_SG3").value=T(s/(100*(d/10)),3)),ot(),N(),rt("material"),C("✓ Applied Specific Gravity SG1..3 and SG_Ave to Material Form.")}),(Sa=t("#btn-apply-calc-din"))==null||Sa.addEventListener("click",()=>{var a;const e=((a=t("#calc_res_din"))==null?void 0:a.textContent)||"";t("#mf_DIN_1")&&(t("#mf_DIN_1").value=e),ot(),N(),rt("material"),C("✓ Applied DIN Abrasion value to Material Form.")}),(Fa=t("#btn-apply-calc-ross"))==null||Fa.addEventListener("click",()=>{var l,d,n,f;const e=parseFloat(((l=t("#calc_rf_li1"))==null?void 0:l.value)??"2.5")||2.5,a=parseFloat(((d=t("#calc_rf_lf1"))==null?void 0:d.value)??"2.5")||2.5,s=parseFloat(((n=t("#calc_rf_li2"))==null?void 0:n.value)??"2.5")||2.5,r=parseFloat(((f=t("#calc_rf_lf2"))==null?void 0:f.value)??"2.5")||2.5;t("#mf_RossFlex_1")&&(t("#mf_RossFlex_1").value=T((a-e)/e*100,2)),t("#mf_RossFlex_2")&&(t("#mf_RossFlex_2").value=T((r-s)/s*100,2)),ot(),N(),rt("material"),C("✓ Applied Ross Flex 1 & 2 to Material Form.")}),(La=t("#btn-apply-calc-shrink"))==null||La.addEventListener("click",()=>{var l,d,n,f;const e=parseFloat(((l=t("#calc_sh_lb"))==null?void 0:l.value)??"100")||100,a=parseFloat(((d=t("#calc_sh_la"))==null?void 0:d.value)??"100")||100,s=parseFloat(((n=t("#calc_sh_wb"))==null?void 0:n.value)??"100")||100,r=parseFloat(((f=t("#calc_sh_wa"))==null?void 0:f.value)??"100")||100;t("#mf_ShrinkageL")&&(t("#mf_ShrinkageL").value=T((e-a)/e*100,2)),t("#mf_ShrinkageW")&&(t("#mf_ShrinkageW").value=T((s-r)/s*100,2)),ot(),N(),rt("material"),C("✓ Applied Shrinkage L & W to Material Form.")});const We=async()=>{var s,r;const e=((s=t("#exp_mat_type"))==null?void 0:s.value)||"Canvas",a=((r=t("#exp_t_ave"))==null?void 0:r.value)||"1.25";try{const l=await P(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`),d=t("#exp-standards-tbody");if(!d||!l.standards)return;d.innerHTML=l.standards.map(n=>`
        <tr>
          <td><b>${o(n.MaterialType)}</b></td>
          <td class="mono"><code>${o(n.TestMethod)}</code></td>
          <td class="mono"><b>${o(n.StandardValue??"-")}</b></td>
          <td class="mono">${o(n.Tolerance??"-")}</td>
          <td class="mono">${o(n.TMinC??"-")}</td>
          <td class="mono">${o(n.TMaxC??"-")}</td>
        </tr>
      `).join("")}catch(l){console.error(l)}};($a=t("#btn-exp-run"))==null||$a.addEventListener("click",We),(Ca=t("#exp_mat_type"))==null||Ca.addEventListener("change",We);const Ia=()=>{var Ht,xt,Mt,Bt,Ot,kt,ut,jt,Vt,G,K,tt,mt,ct,Y,S,vt,z,q,W,_t,dt,Dt,Pt,Nt,Wt,ft,et,at,st,bt,It,ht,gt,Tt,yt,St,Ft,Lt,$t,Ct,wt,Rt,Gt,zt,qt,Qt,Xt,Jt,Kt,Yt,Zt,te,ee,ae,se,le,re,oe,ne,ie,ce,de,fe,pe,ue,me,ve,_e,be,he,ge,Te,ye,Se;const e=((Ht=t("#mf_LabRefNo"))==null?void 0:Ht.value)||"DTP-LAB-PREVIEW",a=((xt=t("#mf_TR"))==null?void 0:xt.value)||"-",s=((Mt=t("#mf_MaterialType"))==null?void 0:Mt.value)||"Material",r=((Bt=t("#mf_MaterialCode"))==null?void 0:Bt.value)||"-",l=((Ot=t("#mf_MaterialDescription"))==null?void 0:Ot.value)||"-",d=((kt=t("#mf_SupplierFactory"))==null?void 0:kt.value)||"DATIAN SUBIC SHOES INC.",n=((ut=t("#mf_ModelNoStyle"))==null?void 0:ut.value)||"-",f=((jt=t("#mf_Color"))==null?void 0:jt.value)||"-",i=((Vt=t("#mf_POLotNo"))==null?void 0:Vt.value)||"-",m=((G=t("#mf_MTL"))==null?void 0:G.value)||"-",p=Object.assign({},xe||{});R("[data-raw-col]").forEach(c=>{p[c.dataset.rawCol]=c.value});const u=(...c)=>{var _;for(const b of c){const g=(_=t("#mf_"+b))==null?void 0:_.value;if(g!==void 0&&String(g).trim()!=="")return String(g).trim();const h=p[b];if(h!=null&&String(h).trim()!=="")return String(h).trim()}return""},F=c=>String(c||"").replace(/ 00:00:00$/,""),L=F(u("TestedDate","Tested Date"))||new Date().toISOString().slice(0,10),$=u("Technician","TestedBy"),y=u("Supplier's Country","SupplierCountry","Country"),O=u("Quantity","PO/LotQuantity"),U=u("SendBy","Send by"),H=F(u("RecievedDate","ReceivedDate")),M=F(u("FinishedDate")),D=u("MaterialSource"),V=[["Prod. Material","Prod. Material 量产材料"],["Prod. Re-test","Prod. Re-test 量产重测"],["New/Development","New/Development 开发按新材料"],["Sales Sample","Sales Sample (销售样品)"]].map(([c,_])=>`<span style="white-space:nowrap;margin-right:14px"><b style="color:${D===c?"#1d4ed8":"#94a3b8"}">${D===c?"●":"○"}</b> ${D===c?'<b style="color:#1d4ed8">'+o(_)+"</b>":o(_)}</span>`).join(""),J=[1,2,3,4].map(c=>`<span style="margin-right:12px"><b style="color:#94a3b8">○</b> Tech ${c}</span>`).join(""),nt=u("AuthorizedBy","ReviewedBy"),pt=F(u("AuthorizedDate")),B=(u("Remarks")||"PASS").toUpperCase(),it=B==="PASS"?"":B==="TOLERABLE"?"stamp-tol":"stamp-fail",A=[],j=(c,_,b,g,h,w,E,k)=>{(b||w)&&A.push(`
          <tr>
            <td class="text-left"><b>${c} — Length (L)</b></td>
            <td>${_}</td>
            <td><b>${b||"-"}</b></td>
            <td>${g||"-"}</td>
            <td><b class="${h==="PASS"?"dtr-rate-pass":h==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${h||"-"}</b></td>
          </tr>
          <tr>
            <td class="text-left"><b>${c} — Width (W)</b></td>
            <td>${_}</td>
            <td><b>${w||"-"}</b></td>
            <td>${E||"-"}</td>
            <td><b class="${k==="PASS"?"dtr-rate-pass":k==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${k||"-"}</b></td>
          </tr>
        `)};j("Tensile Strength","kg",(K=t("#mf_TensileLAve"))==null?void 0:K.value,(tt=t("#mf_Tensile_L"))==null?void 0:tt.value,(mt=t("#mf_TensileLRating"))==null?void 0:mt.value,(ct=t("#mf_TensileWAve"))==null?void 0:ct.value,(Y=t("#mf_Tensile_W"))==null?void 0:Y.value,(S=t("#mf_TensileWRating"))==null?void 0:S.value),j("Elongation","%",(vt=t("#mf_ElongationLAve"))==null?void 0:vt.value,(z=t("#mf_Elongation_L"))==null?void 0:z.value,(q=t("#mf_ElongationLRating"))==null?void 0:q.value,(W=t("#mf_ElongationWAve"))==null?void 0:W.value,(_t=t("#mf_Elongation_W"))==null?void 0:_t.value,(dt=t("#mf_ElongationWRating"))==null?void 0:dt.value),j("Trouser / Wing Tear","kg",(Dt=t("#mf_TrouserTearLAve"))==null?void 0:Dt.value,(Pt=t("#mf_TrouserTear_L"))==null?void 0:Pt.value,(Nt=t("#mf_TrouserTearLRating"))==null?void 0:Nt.value,(Wt=t("#mf_TrouserTearWAve"))==null?void 0:Wt.value,(ft=t("#mf_TrouserTear_W"))==null?void 0:ft.value,(et=t("#mf_TrouserTearWRating"))==null?void 0:et.value),j("Stitch Tear","kg",(at=t("#mf_StitchTearLAve"))==null?void 0:at.value,(st=t("#mf_StitchTear_L"))==null?void 0:st.value,(bt=t("#mf_StitchTearLRating"))==null?void 0:bt.value,(It=t("#mf_StitchTearWAve"))==null?void 0:It.value,(ht=t("#mf_StitchTear_W"))==null?void 0:ht.value,(gt=t("#mf_StitchTearWRating"))==null?void 0:gt.value),(Tt=t("#mf_Text3977"))!=null&&Tt.value&&A.push(`
        <tr>
          <td class="text-left"><b>Mullen Bursting Strength</b></td>
          <td>kg/cm²</td>
          <td><b>${(yt=t("#mf_Text3977"))==null?void 0:yt.value}</b></td>
          <td>${((St=t("#mf_MullenBurst"))==null?void 0:St.value)||"-"}</td>
          <td><b>${((Ft=t("#mf_MullenBurstRating"))==null?void 0:Ft.value)||"-"}</b></td>
        </tr>
      `),(Lt=t("#mf_DieC_Ave"))!=null&&Lt.value&&A.push(`
        <tr>
          <td class="text-left"><b>Die C Tear / Edge Crush</b></td>
          <td>kg</td>
          <td><b>${($t=t("#mf_DieC_Ave"))==null?void 0:$t.value}</b></td>
          <td>${((Ct=t("#mf_DieC_Std"))==null?void 0:Ct.value)||"-"}</td>
          <td><b>${((wt=t("#mf_DieC_Rate"))==null?void 0:wt.value)||"-"}</b></td>
        </tr>
      `),(Rt=t("#mf_WAve"))!=null&&Rt.value&&A.push(`
        <tr>
          <td class="text-left"><b>Fabric Weight (Grammage)</b></td>
          <td>g/m²</td>
          <td><b>${(Gt=t("#mf_WAve"))==null?void 0:Gt.value}</b></td>
          <td>${((zt=t("#mf_W_Std"))==null?void 0:zt.value)||"-"}</td>
          <td><b>${((qt=t("#mf_WRating"))==null?void 0:qt.value)||"-"}</b></td>
        </tr>
      `),(Qt=t("#mf_CTPRating"))!=null&&Qt.value&&A.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Perspiration (Face)</b></td>
          <td>Grade</td>
          <td>W:${((Xt=t("#mf_CtPWool"))==null?void 0:Xt.value)||"-"} A:${((Jt=t("#mf_CtPAcrylic"))==null?void 0:Jt.value)||"-"} P:${((Kt=t("#mf_CtPPolyester"))==null?void 0:Kt.value)||"-"} N:${((Yt=t("#mf_CtPNylon"))==null?void 0:Yt.value)||"-"} C:${((Zt=t("#mf_CtPCotton"))==null?void 0:Zt.value)||"-"} Ac:${((te=t("#mf_CtPAcetate"))==null?void 0:te.value)||"-"}</td>
          <td>${((ee=t("#mf_CTP"))==null?void 0:ee.value)||"4.0"}</td>
          <td><b>${(ae=t("#mf_CTPRating"))==null?void 0:ae.value}</b></td>
        </tr>
      `),(se=t("#mf_CTWRating"))!=null&&se.value&&A.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Water (Face)</b></td>
          <td>Grade</td>
          <td>W:${((le=t("#mf_CtWWool"))==null?void 0:le.value)||"-"} A:${((re=t("#mf_CtWAcrylic"))==null?void 0:re.value)||"-"} P:${((oe=t("#mf_CtWPolyester"))==null?void 0:oe.value)||"-"} N:${((ne=t("#mf_CtWNylon"))==null?void 0:ne.value)||"-"} C:${((ie=t("#mf_CtWCotton"))==null?void 0:ie.value)||"-"} Ac:${((ce=t("#mf_CtWAcetate"))==null?void 0:ce.value)||"-"}</td>
          <td>${((de=t("#mf_CTW"))==null?void 0:de.value)||"4.0"}</td>
          <td><b>${(fe=t("#mf_CTWRating"))==null?void 0:fe.value}</b></td>
        </tr>
      `),(pe=t("#mf_CTCDRYRating"))!=null&&pe.value&&A.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Dry)</b></td>
          <td>Grade</td>
          <td><b>${((ue=t("#mf_CtCDry"))==null?void 0:ue.value)||"-"}</b></td>
          <td>${((me=t("#mf_CtCDry_S"))==null?void 0:me.value)||"4.0"}</td>
          <td><b>${(ve=t("#mf_CTCDRYRating"))==null?void 0:ve.value}</b></td>
        </tr>
      `),(_e=t("#mf_CTCWETRating"))!=null&&_e.value&&A.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Wet)</b></td>
          <td>Grade</td>
          <td><b>${((be=t("#mf_CtCWet"))==null?void 0:be.value)||"-"}</b></td>
          <td>${((he=t("#mf_CtCWet_S"))==null?void 0:he.value)||"3.0"}</td>
          <td><b>${(ge=t("#mf_CTCWETRating"))==null?void 0:ge.value}</b></td>
        </tr>
      `),(()=>{const c=A.length?/^(tensile|elongation|trousertear|stitchtear|mullen|diec|ctp|ctw|ctcdry|ctcwet)/i:/^$/,_={heataging:"Heat Aging (72 hrs, 70°C)",corrosion:"Corrosion Resistance",hydrolysis:"Hydrolysis (7 days, 60°C, 95% RH)",uv:"UV Light (24 hrs, 50°C)",ctcdry:"Colorfastness to Crocking (Dry)",ctcwet:"Colorfastness to Crocking (Wet)",ctw:"Colorfastness to Water",ctp:"Colorfastness to Perspiration",shrinkage12:"Shrinkage (1 hr, 70°C) 1-2",shrinkage34:"Shrinkage (1 hr, 70°C) 3-4",tensile:"Tensile",elongation:"Elongation",stitchtear:"Stitch Tear",peelstrength:"Peel Strength",shearstrength:"Shear Strength",laceTipRet:"Lace Tip Retention",knotslip:"Knot Slip",laceabrasion:"Lace Abrasion",hardness:"Hardness",sg:"Specific Gravity",pvc:"PVC Contact Discoloration"},b={corrosion:["CorrosionRes"],ctcdry:["CtCDry"],ctcwet:["CtCWet"],shrinkage12:["Shrinkageave12"],shrinkage34:["Shrinkageave34"]},g=E=>E.replace(/[.*+?^${}()|[\]\\]/g,"\\$&"),h=E=>{const k=String(E).trim().toUpperCase();return["P","PASS"].includes(k)?"PASS":["F","FAIL","FAILED"].includes(k)?"FAIL":k==="T"?"TOLERABLE":k},w=new Set;Object.keys(p).forEach(E=>{const k=E.match(/^(.+?)(?:_R|_?Rating|_?rating)$/),I=k&&String(p[E]??"").trim();if(!I)return;const Z=k[1].replace(/txt/i,""),Ut=Z.toLowerCase();if(Z.length<3||w.has(Ut)||c.test(Z))return;const Oa=[Z,...b[Ut]||[]],Ba=Q=>/(_S|_std|Std|_Std|_R|Rating|_rating)$/.test(Q),ka=Object.keys(p).filter(Q=>!Ba(Q)&&Oa.some(He=>new RegExp("^"+g(He)+"(?:txt)?(\\d*|_?ave)$","i").test(Q))).map(Q=>String(p[Q]??"").trim()).filter(Q=>Q!==""&&!/^0(\.0+)?$/.test(Q));if(!ka.length)return;w.add(Ut);const Da=Object.keys(p).find(Q=>Oa.some(He=>new RegExp("^"+g(He)+"(?:txt)?(_S|_std|Std|_Std)$","i").test(Q))&&String(p[Q]??"").trim()!==""),ja=_[Ut]||Z.replace(/([a-z])([A-Z0-9])/g,"$1 $2"),Ue=h(I);A.push(`
          <tr>
            <td class="text-left"><b>${o(ja)}</b></td>
            <td>-</td>
            <td><b>${ka.map(o).join(" &nbsp; ")}</b></td>
            <td>${o(Da?p[Da]:"-")}</td>
            <td><b class="${Ue==="PASS"?"dtr-rate-pass":Ue==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${o(Ue)}</b></td>
          </tr>
        `)})})();const Et=`
      <div class="dtr-report-sheet">
        <div class="rpt-logo-row">
          <img class="rpt-logo-vf" src="${X}assets/images/vf-logo.webp" alt="VF">
          <img class="rpt-logo-satra" src="${X}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
        </div>
        <div class="dtr-sheet-header">
          <div class="dtr-sheet-title">
            <h2>DATIAN SUBIC SHOES INC. (DTP)</h2>
            <h4>PHYSICAL TEST REPORT — ${o(s.toUpperCase())}</h4>
            <div style="font-size:11px;color:#64748b;margin-top:2px">Lot 1P7-A, Phase 2, Subic Bay Gateway Park, Subic Bay Freeport Zone</div>
          </div>
          <div class="dtr-sheet-meta">
            <div>Lab Ref: <b>${o(e)}</b></div>
            <div>TR #: <b>${o(a)}</b></div>
            <div style="margin-top:6px">
              <span class="dtr-sheet-stamp-box ${it}">${o(B)}</span>
            </div>
          </div>
        </div>

        <table class="dtr-sheet-info-grid">
          <tr>
            <td class="label-cell">Material Code</td>
            <td><b>${o(r)}</b></td>
            <td class="label-cell">Material Type</td>
            <td>${o(s)}</td>
            <td class="label-cell">Tested Date</td>
            <td>${o(L)}</td>
          </tr>
          <tr>
            <td class="label-cell">Description</td>
            <td colspan="3">${o(l)}</td>
            <td class="label-cell">PO / Lot No.</td>
            <td>${o(i)}</td>
          </tr>
          <tr>
            <td class="label-cell">Supplier / Factory</td>
            <td>${o(d)}</td>
            <td class="label-cell">Model / Style</td>
            <td>${o(n)}</td>
            <td class="label-cell">Color / MTL</td>
            <td>${o(f)} / ${o(m)}</td>
          </tr>
          <tr>
            <td class="label-cell">Thickness (TAve)</td>
            <td><b>${((Te=t("#mf_TAve"))==null?void 0:Te.value)||"-"} mm</b></td>
            <td class="label-cell">Weight (WAve)</td>
            <td><b>${((ye=t("#mf_WAve"))==null?void 0:ye.value)||"-"} g/m²</b></td>
            <td class="label-cell">Technician</td>
            <td>${o($||"-")}</td>
          </tr>
          <tr>
            <td class="label-cell">Supplier's Country</td>
            <td>${o(y||"-")}</td>
            <td class="label-cell">Quantity</td>
            <td>${o(O||"-")}</td>
            <td class="label-cell">Received Date</td>
            <td>${o(H||"-")}</td>
          </tr>
          <tr>
            <td class="label-cell">Material Source</td>
            <td colspan="3" style="line-height:1.9">${V}<br>${J}</td>
            <td class="label-cell">Finished Date</td>
            <td>${o(M||"-")}</td>
          </tr>
          <tr>
            <td class="label-cell">Send by</td>
            <td colspan="5">${o(U||"-")}</td>
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
            ${A.length?A.join(""):'<tr><td colspan="5" class="muted">No test items recorded for this report.</td></tr>'}
          </tbody>
        </table>

        <div class="dtr-sheet-sign-row">
          <div class="dtr-sheet-sign-box">
            <div>Prepared by (Testing Technician):</div>
            <div class="dtr-sheet-sign-line">
              <span>${o($)}</span>
              <span class="muted">${o(L)}</span>
            </div>
          </div>
          <div class="dtr-sheet-sign-box">
            <div>Reviewed &amp; Approved by (QA Lab Head):</div>
            <div class="dtr-sheet-sign-line">
              <span>${o(nt)}</span>
              <span class="muted">${o(pt||L)}</span>
            </div>
          </div>
        </div>
      </div>
    `;t("#dtr-print-modal-title").textContent=`Official Test Report Sheet — ${e}`,t("#dtr-print-sheet-container").innerHTML=Et,(Se=t("#dtr-print-modal-backdrop"))==null||Se.classList.remove("hidden")};let Ee=null;const Ua=async()=>{if(Ee)return Ee;try{const e=await fetch(X+"assets/images/bonding_sketches/sketch_configs.json");if(e.ok)return Ee=await e.json(),Ee}catch(e){console.warn("Failed to load sketch_configs.json",e)}return null},Ha=async()=>{var gt,Tt,yt,St,Ft,Lt,$t,Ct,wt,Rt,Gt,zt,qt,Qt,Xt,Jt,Kt,Yt,Zt,te,ee,ae,se,le,re,oe,ne,ie,ce,de,fe,pe,ue,me,ve,_e,be,he,ge,Te,ye,Se;const e=parseInt(((gt=t("#bf_BondingTestID"))==null?void 0:gt.value)||(v==null?void 0:v.BondingTestID)||0)||0,a=((Tt=t("#bf_LabRefNo"))==null?void 0:Tt.value)||"DTP-BOND-PREVIEW",s=((yt=t("#bf_ETRFNo"))==null?void 0:yt.value)||"-",r=((St=t("#bf_ModelStyle"))==null?void 0:St.value)||"-",l=((Ft=t("#bf_Color"))==null?void 0:Ft.value)||"-",d=((Lt=t("#bf_Side"))==null?void 0:Lt.value)||"Left",n=d.toLowerCase()==="left",f=((($t=t("#bf_AgeCategory"))==null?void 0:$t.value)||"ADULT").toUpperCase(),i=parseFloat(((Ct=t("#bf_STD"))==null?void 0:Ct.value)||"2.5")||2.5,m=((wt=t("#bf_POLotNO"))==null?void 0:wt.value)||"-",p=((Rt=t("#bf_POQuantity"))==null?void 0:Rt.value)||"-",u=((Gt=t("#bf_Factory"))==null?void 0:Gt.value)||"DATIAN SUBIC SHOES INC. (DTP)",F=((zt=t("#bf_AdhesiveSupplier"))==null?void 0:zt.value)||"-",L=((qt=t("#bf_ShippedDate"))==null?void 0:qt.value)||"-",$=((Qt=t("#bf_ProductionDate"))==null?void 0:Qt.value)||"-",y=((Xt=t("#bf_Sendby"))==null?void 0:Xt.value)||"-",O=((Jt=t("#bf_MaterialSource"))==null?void 0:Jt.value)||"Prod. Shoes",U=((Kt=t("#bf_ModeOfFailure"))==null?void 0:Kt.value)||"-",H=((Yt=t("#bf_PossibleCause"))==null?void 0:Yt.value)||"-",M=((Zt=t("#bf_Remarks"))==null?void 0:Zt.value)||((te=t("#bf_LabNotesComments"))==null?void 0:te.value)||"",D=((ee=t("#bf_ReceivedDate"))==null?void 0:ee.value)||"-",V=((ae=t("#bf_TestedDate"))==null?void 0:ae.value)||new Date().toISOString().slice(0,10),J=((se=t("#bf_FinishedDate"))==null?void 0:se.value)||V,nt=((le=t("#bf_Technician"))==null?void 0:le.value)||"L. Aquidado",pt=((re=t("#bf_ReviewedBy"))==null?void 0:re.value)||"QA Lead",B=(((oe=t("#bf_Result"))==null?void 0:oe.value)||"PASS").toUpperCase(),it=B==="PASS"?"":B==="TOLERABLE"?"stamp-tol":"stamp-fail",A=((ne=t("#bp_UTFMin"))==null?void 0:ne.value)||(v==null?void 0:v.UTFMin)||"-",j=((ie=t("#bp_UTFMax"))==null?void 0:ie.value)||(v==null?void 0:v.UTFMax)||"-",Et=((ce=t("#bp_UTFAverage"))==null?void 0:ce.value)||(v==null?void 0:v.UTFAverage)||"-",Ht=(((de=t("#bp_UTF_Result"))==null?void 0:de.value)||(v==null?void 0:v.UTF_Result)||"-").toUpperCase(),xt=((fe=t("#bp_FTOMin"))==null?void 0:fe.value)||(v==null?void 0:v.FTOMin)||"-",Mt=((pe=t("#bp_FTOMax"))==null?void 0:pe.value)||(v==null?void 0:v.FTOMax)||"-",Bt=((ue=t("#bp_FTOAverage"))==null?void 0:ue.value)||(v==null?void 0:v.FTOAverage)||"-",Ot=(((me=t("#bp_FTO_Result"))==null?void 0:me.value)||(v==null?void 0:v.FTO_Result)||"-").toUpperCase(),kt=((ve=t("#bp_FiTOMin"))==null?void 0:ve.value)||(v==null?void 0:v.FiTOMin)||"-",ut=((_e=t("#bp_FiTOMax"))==null?void 0:_e.value)||(v==null?void 0:v.FiTOMax)||"-",jt=((be=t("#bp_FiTOAverage"))==null?void 0:be.value)||(v==null?void 0:v.FiTOAverage)||"-",Vt=(((he=t("#bp_FiTO_Result"))==null?void 0:he.value)||(v==null?void 0:v.FiTO_Result)||"-").toUpperCase(),G={},K={},tt={};let mt=0;for(let c=1;c<=35;c++){const _=((ge=t("#bp_UTFpoint"+c))==null?void 0:ge.value)||(v==null?void 0:v["UTFpoint"+c])||"",b=((Te=t("#bp_FTOpoint"+c))==null?void 0:Te.value)||(v==null?void 0:v["FTOpoint"+c])||"";G[c]=_,K[c]=b,(parseFloat(_)>0||parseFloat(b)>0)&&(mt=c),c<=13&&(tt[c]=((ye=t("#bp_FiTO"+c))==null?void 0:ye.value)||(v==null?void 0:v["FiTO"+c])||"")}const ct=await Ua();let Y=parseInt((v==null?void 0:v.MaxPoints)||mt||27);(!ct||!ct[Y])&&(Y=[26,27,28,29,30,31,32,33,34].reduce((_,b)=>Math.abs(b-Y)<Math.abs(_-Y)?b:_,27));let S=ct?ct[Y]:null,vt=[],z=null;if(S)try{const c=await P("dtr/layout?slug=bonding&variant="+encodeURIComponent(Y)),_=c&&c.layout;_&&(S=JSON.parse(JSON.stringify(S)),_.image&&Object.assign(S.image,_.image),["utf","fto"].forEach(b=>{Object.keys(_[b]||{}).forEach(g=>{_[b][g].hidden?delete S[b][g]:S[b][g]=_[b][g]})}),vt=_.extras||[],(_.vector||_.start)&&(z=_),_.vector&&(S.image.file=_.imageFile||"blank.png"))}catch{}const q=(c,_,b=!1)=>{const g=qe(parseFloat(c),b?Re:_);return g?g==="FAIL"?"color:#dc2626;font-weight:800;":g==="TOLERABLE"?"color:#d97706;font-weight:800;":"color:#1d4ed8;font-weight:800;":"color:#94a3b8"};let W="";if(S){W+=`
        <img src="${X}assets/images/bonding_sketches/${S.image.file}" 
             class="vf-shoe-img" 
             style="left:${S.image.pctLeft}%; top:${S.image.pctTop}%; width:${S.image.pctWidth}%; height:${S.image.pctHeight}%;" 
             alt="${d} Shoe Sketch">
      `;for(let c=1;c<=S.pointsCount;c++){const _=S.utf[c];if(_){const g=G[c],h=parseFloat(g),w=Number.isFinite(h)&&h>0,E=q(g,i,!1);W+=`
            <div class="vf-point-box vf-point-utf" 
                 style="left:${_.pctLeft}%; top:${_.pctTop}%; width:${_.pctWidth}%; height:${_.pctHeight}%; ${E}" 
                 title="UTF Point ${c}: ${w?h.toFixed(2):"-"}">
              ${w?h.toFixed(2):""}
            </div>
          `}const b=S.fto[c];if(b){const g=K[c],h=parseFloat(g),w=Number.isFinite(h)&&h>0,E=q(g,i,!1);W+=`
            <div class="vf-point-box vf-point-fto" 
                 style="left:${b.pctLeft}%; top:${b.pctTop}%; width:${b.pctWidth}%; height:${b.pctHeight}%; ${E}" 
                 title="FTO Point ${c}: ${w?h.toFixed(2):"-"}">
              ${w?h.toFixed(2):""}
            </div>
          `}}if(z){let c='<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none">',_="";if(z.vector&&["utf","fto"].forEach(b=>Object.keys(S[b]).forEach(g=>{const h=S[b][g],w=((z.dots||{})[b]||{})[g];if(!w)return;const E=h.pctLeft+h.pctWidth,k=h.pctTop+h.pctHeight/2,I=w.x<h.pctLeft?h.pctLeft:w.x>E?E:h.pctLeft+h.pctWidth/2,Z=I===h.pctLeft||I===E?k:w.y<h.pctTop?h.pctTop:h.pctTop+h.pctHeight,Ut=b==="utf"?"#2563eb":"#7c3aed";c+=`<line x1="${I}" y1="${Z}" x2="${w.x}" y2="${w.y}" stroke="${Ut}" stroke-width="1.2" vector-effect="non-scaling-stroke"/>`,_+=`<div style="position:absolute;left:${w.x}%;top:${w.y}%;width:7px;height:7px;margin:-3.5px 0 0 -3.5px;border-radius:50%;background:${Ut}"></div>`})),z.start){const b=z.start;_+=`<div style="position:absolute;left:${b.x}%;top:${b.y}%;transform:translate(-50%,-50%);display:flex;align-items:center;gap:3px;white-space:nowrap;color:#b91c1c;font:800 9px sans-serif"><i style="display:block;width:9px;height:9px;background:#dc2626;transform:rotate(45deg)"></i>${o(b.label||"START")}</div>`}W+=c+"</svg>"+_}vt.forEach(c=>{var w;const _=`left:${c.pctLeft}%; top:${c.pctTop}%; width:${c.pctWidth}%; height:${c.pctHeight}%;`,b=`color:${o(c.color||"#111")}; font-size:${Number(c.size)||12}px; font-weight:${c.bold?700:400};`;let g="";if(c.type==="text")g=o(c.text);else if(c.type==="field")g=o(((w=t("#bf_"+c.field))==null?void 0:w.value)||"");else if(c.type==="image"&&c.src)g=`<img src="${X}${o(c.src)}" style="max-width:100%;max-height:100%" alt="">`;else if(c.type==="chart"){let E=0;for(let I=1;I<=S.pointsCount;I++)E=Math.max(E,parseFloat(G[I])||0);let k="";for(let I=1;I<=S.pointsCount;I++){const Z=parseFloat(G[I])||0;k+=`<i style="flex:1;display:block;min-height:1px;height:${E?Z/E*100:0}%;background:${Z<2?"#dc2626":Z<3?"#d97706":"#1d4ed8"}"></i>`}g=`<div style="display:flex;align-items:flex-end;gap:1px;width:100%;height:100%">${k}</div>`}const h=c.type==="line"?`background:${o(c.color||"#111")};`:"";W+=`<div style="position:absolute;display:flex;align-items:center;overflow:hidden;${_}${b}${h}">${g}</div>`})}else W='<div style="padding:20px;text-align:center;color:#64748b">Shoe sketch unavailable</div>';const _t=`
      <img src="${X}assets/images/bonding_sketches/blank.png" 
           class="vf-shoe-img" 
           style="left:12%; top:8%; width:76%; height:84%; opacity:0.85;" 
           alt="Unmeasured Shoe Contour">
    `;let dt="";for(let c=1;c<=13;c++){const _=tt[c],b=parseFloat(_),g=Number.isFinite(b)&&b>0,h=q(_,1,!0);dt+=`
        <div class="vf-center-item" style="${h}">
          ${g?b.toFixed(2):"&nbsp;"}
        </div>
      `}const Dt=n?W:_t,Pt=n?_t:W,Nt=n?dt:"",Wt=n?"":dt,ft=c=>c==="PASS"?{pass:"PASS",fail:"",passCls:"vf-rate-pass",failCls:""}:c==="TOLERABLE"?{pass:"TOLE",fail:"",passCls:"vf-rate-tol",failCls:""}:c==="FAIL"||c==="FAILED"?{pass:"",fail:"FAIL",passCls:"",failCls:"vf-rate-fail"}:{pass:"-",fail:"",passCls:"",failCls:""},et=ft(Ht),at=ft(Ot),st=ft(Vt);let bt="";for(let c=1;c<=(S?S.pointsCount:27);c++){const _=G[c]?parseFloat(G[c]).toFixed(2):"-",b=K[c]?parseFloat(K[c]).toFixed(2):"-",g=c<=13&&tt[c]?parseFloat(tt[c]).toFixed(2):"-";bt+=`
        <tr>
          <td><b>Point ${c}</b></td>
          <td style="${q(G[c],i,!1)}">${_}</td>
          <td style="${q(K[c],i,!1)}">${b}</td>
          <td style="${c<=13?q(tt[c],1,!0):"color:#94a3b8"}">${g}</td>
        </tr>
      `}const It=`
      <div class="vf-report-sheet">
        <!-- Row 1: VF logo | test method name | SATRA logo, all on one line -->
        <div class="rpt-top">
          <img class="rpt-logo-vf" src="${X}assets/images/vf-logo.webp" alt="VF">
          <div class="rpt-top-title">
            <h1 class="vf-title-en">Bonding Test Results</h1>
            <h2 class="vf-title-cn">胶着力测试</h2>
          </div>
          <img class="rpt-logo-satra" src="${X}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
        </div>

        <!-- Row 2: form no / ETRF / lab ref + sample category (left), result + dates (right) -->
        <div class="rpt-sub">
          <div class="rpt-sub-left">
            <div class="vf-form-no rpt-formno">Form # : Lab VF 001</div>
            <div class="vf-etrf-row">
              <span>ETRF No:</span>
              <span class="vf-val-underlined">${o(s)}</span>
            </div>
            <div class="vf-labref-row">
              <span>Lab Ref.No:</span>
              <span class="vf-labref-val">${o(a)}</span>
            </div>
            <div class="vf-test-categories">
              <div>
                <span class="vf-radio ${O==="Prod. Shoes"||!O?"checked":""}"></span> Pro. Shoes 量产鞋
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${O==="Pro. Re-test"?"checked":""}"></span> Pro. Re-test 量产重测
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${O==="NewDevelopment"?"checked":""}"></span> NewDevelopment 开发
              </div>
              <div class="vf-tech-subradios">
                <span>○ Tech 1</span> <span>○ Tech 2</span> <span>○ Tech 3</span> <span>○ Tech 4</span>
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${O==="Sales Sample"?"checked":""}"></span> Sales Sample 销样
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${O==="Trial experiment"?"checked":""}"></span> 试胶试验
              </div>
            </div>
          </div>

          <div class="rpt-sub-right">
            <div class="vf-stamp-area">
              <span class="vf-stamp ${it}">${o(B)}</span>
            </div>
            <table class="vf-dates-table">
              <tr><td>Received Date 收到日期:</td><td class="vf-date-val">${o(D)}</td></tr>
              <tr><td>Tested Date 测试日期:</td><td class="vf-date-val">${o(V)}</td></tr>
              <tr><td>Finished Date 完成日期:</td><td class="vf-date-val">${o(J)}</td></tr>
              <tr><td>Technician 技术员:</td><td class="vf-date-val">${o(nt)}</td></tr>
            </table>
          </div>
        </div>

        <!-- Factory & Spec Details Grid -->
        <table class="vf-meta-grid">
          <tr>
            <td class="vf-lbl">Factory (工厂):</td>
            <td class="vf-val" colspan="3">${o(u)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Adhesive supplier 胶水供应商:</td>
            <td class="vf-val">${o(F)}</td>
            <td class="vf-lbl">Shipped Date (出货日期):</td>
            <td class="vf-val">${o(L)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Model/Style/PGSC (英文鞋型):</td>
            <td class="vf-val">${o(r)}</td>
            <td class="vf-lbl">Color (颜色):</td>
            <td class="vf-val">${o(l)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Production Date (生产日期):</td>
            <td class="vf-val">${o($!=="-"?$:V)}</td>
            <td class="vf-lbl">PO/Lot No (订单号码):</td>
            <td class="vf-val">${o(m)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">PO Quantity (订单数量):</td>
            <td class="vf-val">${o(p)}</td>
            <td class="vf-lbl">Send by (送达):</td>
            <td class="vf-val">${o(y)}</td>
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
              <td class="font-bold text-blue">${i}<br><span class="vf-tol">(≥ ${Ae(i)})</span></td>
              <td class="font-bold text-blue">${A!=="-"?Number(A).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${j!=="-"?Number(j).toFixed(2):"-"}</td>
              <td><span class="${et.passCls}">${et.pass}</span></td>
              <td><span class="${et.failCls}">${et.fail}</span></td>
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
              <td class="font-bold text-blue">${i}<br><span class="vf-tol">(≥ ${Ae(i)})</span></td>
              <td class="font-bold text-blue">${xt!=="-"?Number(xt).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${Mt!=="-"?Number(Mt).toFixed(2):"-"}</td>
              <td><span class="${at.passCls}">${at.pass}</span></td>
              <td><span class="${at.failCls}">${at.fail}</span></td>
            </tr>
            <tr>
              <td class="text-left font-bold">Center : IS/OS<br>(中插 / 大底)</td>
              <td>VF-F01<br><span style="font-size:9.5px">(2019/05)</span></td>
              <td class="font-bold text-blue">${Re}<br><span class="vf-tol">(≥ ${Ae(Re)})</span></td>
              <td class="font-bold text-blue">${kt!=="-"?Number(kt).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${ut!=="-"?Number(ut).toFixed(2):"-"}</td>
              <td><span class="${st.passCls}">${st.pass}</span></td>
              <td><span class="${st.failCls}">${st.fail}</span></td>
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
              <span style="${n?"color:#1d4ed8;font-weight:800":""}">Left</span>
              <span style="${n?"":"color:#1d4ed8;font-weight:800"}">Right</span>
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
            ${Dt}
          </div>

          <!-- Center Boxes -->
          <div class="vf-center-card">
            <div class="vf-center-cols">
              <div class="vf-center-box" style="${n?"border-color:#1d4ed8;background:#f8fafc":"border-color:#cbd5e1"}">
                ${Nt}
              </div>
              <div class="vf-center-box" style="${n?"border-color:#cbd5e1":"border-color:#1d4ed8;background:#f8fafc"}">
                ${Wt}
              </div>
            </div>
          </div>

          <!-- Right Shoe Card -->
          <div class="vf-shoe-card">
            ${Pt}
          </div>
        </div>

        <!-- Optional Collapsible Data Table -->
        <details class="no-print" style="margin-top:8px;font-size:11px;border:1px solid #cbd5e1;border-radius:4px;padding:6px 10px;background:#f8fafc">
          <summary style="cursor:pointer;font-weight:700;color:#334155">
            <svg class="ic" aria-hidden="true"><use href="#i-chart"/></svg> View Tabular Spot Values (${S?S.pointsCount:27} Points)
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
              ${bt}
            </tbody>
          </table>
        </details>

        <!-- Signatures & Notes Footer -->
        <div class="vf-footer-grid">
          <div>
            Comments 备注:
            <div style="font-weight:normal;color:#334155;margin-top:4px;font-size:10.5px">
              ${o(M||"Tested in accordance with VF Laboratory Standards.")}
            </div>
          </div>
          <div>
            Reviewed by 审查:
            <div class="vf-sign-line">
              <span>${o(pt)}</span>
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
    `;t("#dtr-print-modal-title").textContent=`Whole Shoe Bonding Test Report — ${a} (${d})`,t("#dtr-print-sheet-container").innerHTML=It;const ht=t("#btn-dtr-download-access-pdf");ht&&ht.classList.add("hidden"),(Se=t("#dtr-print-modal-backdrop"))==null||Se.classList.remove("hidden")},Ie=e=>{const a=748.3464566929134,s=285/25.4*96,r=document.createElement("div");r.className="dtr-fit-probe",r.style.cssText="position:absolute;left:-99999px;top:0;visibility:hidden;",document.body.appendChild(r);let l=1;for(let d=0;d<4;d++){r.style.width=a/l+"px",r.innerHTML=e;const n=r.scrollHeight*l,f=Math.min(1,l*(s/Math.max(n,1))*.985);if(Math.abs(f-l)<.005){l=f;break}l=f}return r.remove(),Math.max(.4,Math.round(l*1e3)/1e3)};(wa=t("#btn-dtr-print-preview"))==null||wa.addEventListener("click",Ia),(Ra=t("#btn-bond-print-preview"))==null||Ra.addEventListener("click",Ha),(Aa=t("#btn-dtr-close-print-modal"))==null||Aa.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(Ea=t("#btn-dtr-cancel-print"))==null||Ea.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(xa=t("#btn-dtr-download-pdf"))==null||xa.addEventListener("click",async()=>{var r,l,d,n,f,i;const e=t("#btn-dtr-download-pdf"),a=t("#dtr-print-sheet-container");if(!a||!e)return;const s=e.textContent;e.disabled=!0,e.textContent="Creating PDF...";try{const m=await fetch(X+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((r=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:r.content)||""},body:JSON.stringify({access_id:parseInt(((d=(((l=t("#btn-dtr-download-access-pdf"))==null?void 0:l.href)||"").match(/[?&]id=(\d+)/))==null?void 0:d[1])||0,10)&&!t("#btn-dtr-download-access-pdf").classList.contains("hidden")?parseInt(t("#btn-dtr-download-access-pdf").href.match(/[?&]id=(\d+)/)[1],10):0,side:((((n=t("#btn-dtr-download-access-pdf"))==null?void 0:n.href)||"").match(/side=(\w+)/)||[])[1]||"Left",scale:Ie(a.innerHTML),html:a.innerHTML,name:(((f=t("#dtr-print-modal-title"))==null?void 0:f.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_")})});if(!m.ok)throw new Error((await m.text()).slice(0,200)||"PDF failed");const p=await m.blob(),u=document.createElement("a");u.href=URL.createObjectURL(p),u.download=((((i=t("#dtr-print-modal-title"))==null?void 0:i.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_").trim()||"Report")+".pdf",document.body.appendChild(u),u.click(),u.remove(),setTimeout(()=>URL.revokeObjectURL(u.href),4e3)}catch(m){nxToast("Could not create the PDF: "+m.message,"error",7e3)}e.disabled=!1,e.textContent=s}),(Ma=t("#btn-dtr-do-print"))==null||Ma.addEventListener("click",()=>{const e=t("#dtr-print-sheet-container");if(!e){window.print();return}const a=document.createElement("div");a.id="dtr-print-root",a.innerHTML=e.innerHTML,a.style.zoom=String(Ie(e.innerHTML)),document.body.appendChild(a),document.body.classList.add("dtr-printing");const s=()=>{document.body.classList.remove("dtr-printing"),a.remove(),window.removeEventListener("afterprint",s)};window.addEventListener("afterprint",s),window.print()});const Va=async()=>{var e,a;try{const s=await P("dtr/layout?slug=bonding&variant=form"),r=s&&s.layout,l=t("#dtr-bonding-form");if(!r||!l)return;const d=f=>{var i;return(i=l.querySelector(`[name="${f}"]`))==null?void 0:i.closest(".dtr-field")};(r.customFields||[]).forEach(f=>{var p,u;if(l.querySelector(`[name="${f.key}"]`))return;const i=(u=(p=t("#bf_LabRefNo"))==null?void 0:p.closest(".dtr-field"))==null?void 0:u.parentElement;if(!i)return;const m=document.createElement("div");m.className="dtr-field",m.innerHTML=`<label>${o(f.label)}</label><input type="text" name="${o(f.key)}" id="bf_${o(f.key)}">`,i.appendChild(m)}),Object.entries(r.labels||{}).forEach(([f,i])=>{const m=d(f),p=m&&m.querySelector("label");p&&i&&(p.textContent=i)});const n=(a=(e=t("#bf_LabRefNo"))==null?void 0:e.closest(".dtr-field"))==null?void 0:a.parentElement;n&&(r.order||[]).length&&(r.order.forEach(f=>{const i=d(f);i&&i.parentElement===n&&n.appendChild(i)}),(r.customFields||[]).forEach(f=>{const i=d(f.key);i&&n.appendChild(i)})),(r.hiddenFields||[]).forEach(f=>{const i=d(f);i&&(i.style.display="none")})}catch{}};Qe(),$e("fabric"),Ce("fabric",0),Va().then(()=>{Pe(),Ne(0)}),We(),(()=>{const e=new URLSearchParams(location.search),[a,s]=String(e.get("open")||"").split(":");!a||!/^[a-z]+$/.test(a)||!/^\d+$/.test(s||"")||setTimeout(async()=>{var d,n;const r=t("#dtr-tracker-tbody")||document.body,l=document.createElement("button");if(l.type="button",l.hidden=!0,l.dataset.openRecord=a,l.dataset.sourceId=s,r.appendChild(l),l.click(),l.remove(),e.get("preview")==="1"&&(await new Promise(f=>setTimeout(f,2200)),(d=t(a==="bonding"?"#btn-bond-print-preview":"#btn-dtr-print-preview"))==null||d.click()),e.get("export")==="1"&&window.parent!==window){const f=i=>window.parent.postMessage(Object.assign({type:"NXL_REPORT_PDF"},i),location.origin);try{await new Promise($=>setTimeout($,800));const i=t("#dtr-print-sheet-container");if(!i||!i.innerHTML.trim())throw new Error("The report sheet could not be prepared.");const m=t("#btn-dtr-download-access-pdf"),p=m&&!m.classList.contains("hidden")?parseInt(((m.href||"").match(/[?&]id=(\d+)/)||[])[1]||0,10):0,u=await fetch(X+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((n=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:n.content)||""},body:JSON.stringify({access_id:p,side:(((m==null?void 0:m.href)||"").match(/side=(\w+)/)||[])[1]||"Left",scale:Ie(i.innerHTML),html:i.innerHTML,name:"Report"})});if(!u.ok)throw new Error((await u.text()).slice(0,200)||"PDF failed");const F=new Uint8Array(await u.arrayBuffer());let L="";for(let $=0;$<F.length;$+=32768)L+=String.fromCharCode.apply(null,F.subarray($,$+32768));f({ok:!0,b64:btoa(L)})}catch(i){f({ok:!1,error:i.message})}}if(e.get("export")==="html"&&window.parent!==window){const f=i=>window.parent.postMessage(Object.assign({type:"NXL_REPORT_HTML"},i),location.origin);try{await new Promise(m=>setTimeout(m,1200));const i=t("#dtr-print-sheet-container");if(!i||!i.innerHTML.trim())throw new Error("The report sheet could not be prepared.");f({ok:!0,html:i.innerHTML})}catch(i){f({ok:!1,error:i.message})}}},900)})()})();
