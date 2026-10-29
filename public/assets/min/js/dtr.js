(()=>{"use strict";var Xe,Je,Ke,Ye,Ze,ta,ea,aa,sa,la,ra,oa,na,ia,ca,da,fa,pa,ua,ma,va,_a,ba,ha,ga,Ta,ya,Sa,Fa,La,$a,Ca,wa,Ra,Aa,Ea,xa,Ma;const t=(e,a=document)=>a.querySelector(e),w=(e,a=document)=>[...a.querySelectorAll(e)],Ve=t('meta[name="base-url"]'),X=Ve?Ve.content.replace(/\/+$/,"")+"/":location.origin+"/";let st="fabric",xe={},v={};const $=(e,a=!1)=>{const s=t("#dtr-toast");s&&(s.textContent=e,s.style.background=a?"#dc2626":"#0f172a",s.classList.remove("hidden"),clearTimeout(s._timer),s._timer=setTimeout(()=>s.classList.add("hidden"),4200))},i=e=>String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"),P=async(e,a={})=>(await fetch(X+e.replace(/^\/+/,""),a)).json(),T=(e,a=2)=>{const s=Number(e);if(!Number.isFinite(s))return 0;const r=Math.pow(10,a);return Math.round((s+Number.EPSILON)*r)/r},E=e=>{if(!e)return;const a=String(e.value||"").trim().toUpperCase();e.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),a==="PASS"?e.classList.add("dtr-rate-pass"):a==="TOLERABLE"?e.classList.add("dtr-rate-warn"):(a==="FAIL"||a==="ERROR")&&e.classList.add("dtr-rate-fail")},lt=e=>{w("[data-dtr-tab]").forEach(a=>{a.classList.toggle("active",a.dataset.dtrTab===e)}),["tracker","material","bonding","calculators"].forEach(a=>{var s;(s=t(`#dtr-panel-${a}`))==null||s.classList.toggle("hidden",a!==e)})};w("[data-dtr-tab]").forEach(e=>{e.addEventListener("click",()=>lt(e.dataset.dtrTab))}),w("[data-goto-calc]").forEach(e=>{e.addEventListener("click",()=>{lt("calculators");const a=t(`#calc-card-${e.dataset.gotoCalc}`);a&&a.scrollIntoView({behavior:"smooth",block:"center"})})});const Be=async(e="all",a="")=>{const s=t("#dtr-tracker-tbody");try{s&&window.nxState&&nxState(s,"loading","Loading lab reports…",16);const r=await P(`dtr/records?slug=${encodeURIComponent(e)}&q=${encodeURIComponent(a)}`);if(!s||!r.rows)return;if(!r.rows.length){nxState(s,"empty","No lab reports match your search. Clear the filters or try another Lab Ref No.",16);return}s.innerHTML=r.rows.map(l=>{const c=String(l.overall_result||"PASS").toUpperCase(),o=c==="FAIL"?"b-err":c==="TOLERABLE"?"b-warn":"b-ok";return`
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
        `}).join("")}catch(r){console.error(r),s&&window.nxState&&nxState(s,"error","The list could not be loaded. Check your connection and try again.",16)}};(Xe=t("#dtr-tracker-pills"))==null||Xe.addEventListener("click",e=>{var r;const a=e.target.closest("[data-slug]");if(!a)return;w("#dtr-tracker-pills .dtr-pill").forEach(l=>l.classList.remove("active")),a.classList.add("active");const s=((r=t("#dtr-tracker-search"))==null?void 0:r.value.trim())||"";Be(a.dataset.slug,s)}),(Je=t("#dtr-tracker-btn-search"))==null||Je.addEventListener("click",()=>{var r;const e=t("#dtr-tracker-pills .dtr-pill.active"),a=e?e.dataset.slug:"all",s=((r=t("#dtr-tracker-search"))==null?void 0:r.value.trim())||"";Be(a,s)}),(Ke=t("#dtr-tracker-search"))==null||Ke.addEventListener("keydown",e=>{var a;e.key==="Enter"&&(e.preventDefault(),(a=t("#dtr-tracker-btn-search"))==null||a.click())}),(Ye=t("#dtr-tracker-tbody"))==null||Ye.addEventListener("click",async e=>{const a=e.target.closest("[data-open-record]");if(!a)return;const s=a.dataset.openRecord,r=a.dataset.sourceId;if(s==="bonding")lt("bonding"),await Pe(r),await Ne(r);else{lt("material");const l=t("#dtr-mat-form-slug");l&&(l.value=s),st=s,await Le(s,r),await $e(s,r)}});const Me=(e=!0)=>{var o,d;const a=[];for(let n=1;n<=6;n++){const b=parseFloat(((o=t(`#mf_Thickness${n}`))==null?void 0:o.value)??"");Number.isFinite(b)&&b>0&&a.push(b)}if(!a.length)return;const s=Math.min(...a),r=Math.max(...a),l=T(a.reduce((n,b)=>n+b,0)/a.length,2);t("#mf_TMin")&&(t("#mf_TMin").value=s),t("#mf_TMax")&&(t("#mf_TMax").value=r),t("#mf_TAve")&&(t("#mf_TAve").value=l);const c=parseFloat(((d=t("#mf_T_S"))==null?void 0:d.value)??"");Number.isFinite(c)&&c>0&&t("#mf_TRating")&&(t("#mf_TRating").value=l>=c?"PASS":"FAIL",E(t("#mf_TRating"))),e&&Fe()},rt=()=>{var o,d,n,b;const e=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_Weight${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);if(e.length>0&&t("#mf_WAve")){const p=T(e.reduce((y,R)=>y+R,0)/e.length,2);t("#mf_WAve").value=p;const u=parseFloat(((o=t("#mf_Weight_S"))==null?void 0:o.value)??"");Number.isFinite(u)&&u>0&&t("#mf_WRating")&&(t("#mf_WRating").value=p>=u?"PASS":"FAIL",E(t("#mf_WRating")))}const a=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_SG${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);if(a.length>0&&t("#mf_SG_Ave")){const p=T(a.reduce((y,R)=>y+R,0)/a.length,2);t("#mf_SG_Ave").value=p;const u=parseFloat(((d=t("#mf_SG_Std"))==null?void 0:d.value)??"");Number.isFinite(u)&&u>0&&t("#mf_SG_Rate")&&(t("#mf_SG_Rate").value=Math.abs(p-u)<=.02?"PASS":"FAIL",E(t("#mf_SG_Rate")))}const s=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_DIN_${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);s.length>0&&t("#mf_DIN_Rate")&&(t("#mf_DIN_Rate").value=s.some(p=>p>150)?"FAIL":"PASS",E(t("#mf_DIN_Rate")));const r=[1,2].map(p=>{var u;return parseFloat(((u=t(`#mf_RossFlex_${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);r.length>0&&t("#mf_RossFlex_Rate")&&(t("#mf_RossFlex_Rate").value=r.some(p=>p>50)?"FAIL":"PASS",E(t("#mf_RossFlex_Rate")));const l=parseFloat(((n=t("#mf_ShrinkageL"))==null?void 0:n.value)??""),c=parseFloat(((b=t("#mf_Shrinkage_S"))==null?void 0:b.value)??"");Number.isFinite(l)&&Number.isFinite(c)&&c>0&&t("#mf_ShrinkageLRating")&&(t("#mf_ShrinkageLRating").value=Math.abs(l)<=Math.abs(c)?"PASS":"FAIL",E(t("#mf_ShrinkageLRating")))},je={TensileL:{inputs:["mf_TensileL1","mf_TensileL2","mf_TensileL3"],ave:"mf_TensileLAve",std:"mf_Tensile_L",rate:"mf_TensileLRating",dec:2},TensileW:{inputs:["mf_TensileW1","mf_TensileW2","mf_TensileW3"],ave:"mf_TensileWAve",std:"mf_Tensile_W",rate:"mf_TensileWRating",dec:2},ElongationL:{inputs:["mf_ElongationL1","mf_ElongationL2","mf_ElongationL3"],ave:"mf_ElongationLAve",std:"mf_Elongation_L",rate:"mf_ElongationLRating",dec:2},ElongationW:{inputs:["mf_ElongationW1","mf_ElongationW2","mf_ElongationW3"],ave:"mf_ElongationWAve",std:"mf_Elongation_W",rate:"mf_ElongationWRating",dec:2},TrouserTearL:{inputs:["mf_TrouserTearL1","mf_TrouserTearL2","mf_TrouserTearL3"],ave:"mf_TrouserTearLAve",std:"mf_TrouserTear_L",rate:"mf_TrouserTearLRating",dec:2},TrouserTearW:{inputs:["mf_TrouserTearW1","mf_TrouserTearW2","mf_TrouserTearW3"],ave:"mf_TrouserTearWAve",std:"mf_TrouserTear_W",rate:"mf_TrouserTearWRating",dec:2},StitchTearL:{inputs:["mf_StitchTearL1","mf_StitchTearL2","mf_StitchTearL3"],ave:"mf_StitchTearLAve",std:"mf_StitchTear_L",rate:"mf_StitchTearLRating",dec:2},StitchTearW:{inputs:["mf_StitchTearW1","mf_StitchTearW2","mf_StitchTearW3"],ave:"mf_StitchTearWAve",std:"mf_StitchTear_W",rate:"mf_StitchTearWRating",dec:2},MullenBurst:{inputs:["mf_MullenBurst1","mf_MullenBurst2","mf_MullenBurst3"],ave:"mf_Text3977",std:"mf_MullenBurst",rate:"mf_MullenBurstRating",dec:4},DieC:{inputs:["mf_DieC_1","mf_DieC_2","mf_DieC_3"],ave:"mf_DieC_Ave",std:"mf_DieC_Std",rate:"mf_DieC_Rate",dec:2}},Ge=e=>{const a=je[e];if(!a)return;const s=a.inputs.map(n=>{var b;return parseFloat(((b=t(`#${n}`))==null?void 0:b.value)??"")}).filter(n=>Number.isFinite(n)),r=t(`#${a.ave}`),l=t(`#${a.std}`),c=t(`#${a.rate}`);if(!s.length)return;const o=T(s.reduce((n,b)=>n+b,0)/s.length,a.dec);r&&(r.value=o);const d=parseFloat((l==null?void 0:l.value)??"");Number.isFinite(d)&&d>0&&c&&(c.value=o>=d?"PASS":"FAIL",E(c))},Se=()=>{Object.keys(je).forEach(Ge)},Oe={CTP:{fibers:["mf_CtPWool","mf_CtPAcrylic","mf_CtPPolyester","mf_CtPNylon","mf_CtPCotton","mf_CtPAcetate"],std:"mf_CTP",rate:"mf_CTPRating"},CTPB:{fibers:["mf_CtPBWool","mf_CtPBAcrylic","mf_CtPBPolyester","mf_CtPBNylon","mf_CtPBCotton","mf_CtPBAcetate"],std:"mf_CtPB_S",rate:"mf_CTPB_Rating"},CTW:{fibers:["mf_CtWWool","mf_CtWAcrylic","mf_CtWPolyester","mf_CtWNylon","mf_CtWCotton","mf_CtWAcetate"],std:"mf_CTW",rate:"mf_CTWRating"},CTWB:{fibers:["mf_CtWBackSideWool","mf_CtWBackSideAcrylic","mf_CtWBackSidePolyester","mf_CtWBackSideNylon","mf_CtWBackSideCotton","mf_CtWBackSideAcetate"],std:"mf_CtWB_S",rate:"mf_CtWB_Rating"}},ke=e=>{var c;const a=Oe[e];if(!a)return;const s=parseFloat(((c=t(`#${a.std}`))==null?void 0:c.value)??""),r=t(`#${a.rate}`);if(!Number.isFinite(s)||s<=0||!r)return;const l=a.fibers.map(o=>{var d;return parseFloat(((d=t(`#${o}`))==null?void 0:d.value)??"")});l.some(o=>!Number.isFinite(o))||(r.value=l.some(o=>o<s)?"FAIL":"PASS",E(r))},De=()=>{var S,k,I,U,x,D,H,J,ot,ft,V,nt,A,j,xt;const e=parseFloat(((S=t("#mf_CtCDry"))==null?void 0:S.value)??""),a=parseFloat(((k=t("#mf_CtCDry_S"))==null?void 0:k.value)??"");Number.isFinite(e)&&Number.isFinite(a)&&a>0&&t("#mf_CTCDRYRating")&&(t("#mf_CTCDRYRating").value=e>=a?"PASS":"FAIL",E(t("#mf_CTCDRYRating")));const s=parseFloat(((I=t("#mf_CtCWet"))==null?void 0:I.value)??""),r=parseFloat(((U=t("#mf_CtCWet_S"))==null?void 0:U.value)??"");Number.isFinite(s)&&Number.isFinite(r)&&r>0&&t("#mf_CTCWETRating")&&(t("#mf_CTCWETRating").value=s>=r?"PASS":"FAIL",E(t("#mf_CTCWETRating")));const l=parseFloat(((x=t("#mf_UV"))==null?void 0:x.value)??""),c=parseFloat(((D=t("#mf_UV_S"))==null?void 0:D.value)??"");Number.isFinite(l)&&Number.isFinite(c)&&c>0&&t("#mf_UVRating")&&(t("#mf_UVRating").value=l>=c?"PASS":"FAIL",E(t("#mf_UVRating")));const o=parseFloat(((H=t("#mf_HeatAging1"))==null?void 0:H.value)??""),d=parseFloat(((J=t("#mf_HeatAging2"))==null?void 0:J.value)??""),n=parseFloat(((ot=t("#mf_HeatAging_S"))==null?void 0:ot.value)??"");Number.isFinite(o)&&Number.isFinite(d)&&Number.isFinite(n)&&n>0&&t("#mf_HeatAgingRating")&&(t("#mf_HeatAgingRating").value=o<n||d<n?"FAIL":"PASS",E(t("#mf_HeatAgingRating")));const b=parseFloat(((ft=t("#mf_Hydrolysis1"))==null?void 0:ft.value)??""),p=parseFloat(((V=t("#mf_Hydrolysis2"))==null?void 0:V.value)??""),u=parseFloat(((nt=t("#mf_Hydrolysis_S"))==null?void 0:nt.value)??"");Number.isFinite(b)&&Number.isFinite(p)&&Number.isFinite(u)&&u>0&&t("#mf_HydrolysisRating")&&(t("#mf_HydrolysisRating").value=b<u||p<u?"FAIL":"PASS",E(t("#mf_HydrolysisRating")));const y=parseFloat(((A=t("#mf_PVC_1"))==null?void 0:A.value)??""),R=parseFloat(((j=t("#mf_PVC_2"))==null?void 0:j.value)??""),M=parseFloat(((xt=t("#mf_PVC_S"))==null?void 0:xt.value)??"");Number.isFinite(y)&&Number.isFinite(R)&&Number.isFinite(M)&&M>0&&t("#mf_PVC_Rating")&&(t("#mf_PVC_Rating").value=y<M||R<M?"FAIL":"PASS",E(t("#mf_PVC_Rating")))},N=()=>{var c;let e=!1,a=!1,s=!1;w("#dtr-material-form .dtr-rate-inp").forEach(o=>{E(o);const d=String(o.value||"").trim().toUpperCase();d==="FAIL"?a=!0:d==="TOLERABLE"?s=!0:d==="PASS"&&(e=!0)});let r="PASS";a?r="FAIL":s?r="TOLERABLE":e?r="PASS":(c=t("#mf_Remarks"))!=null&&c.value&&(r=String(t("#mf_Remarks").value).toUpperCase()),t("#mf_Remarks")&&(t("#mf_Remarks").value=r);const l=t("#dtr-stamp-badge");l&&(l.textContent=r,l.className="dtr-stamp "+(r==="FAIL"?"dtr-stamp-fail":r==="TOLERABLE"?"dtr-stamp-warn":"dtr-stamp-pass"))},Fe=async()=>{var s,r;const e=((s=t("#mf_MaterialType"))==null?void 0:s.value.trim())||"",a=parseFloat(((r=t("#mf_TAve"))==null?void 0:r.value)??"0")||0;if(e)try{const l=await P(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`);l&&l.mapped&&Object.keys(l.mapped).forEach(c=>{const o=l.mapped[c],d=t(`#mf_${c}`)||t(`[name="${c}"]`,t("#dtr-material-form"));d&&(d.value=o)}),rt(),Se(),Object.keys(Oe).forEach(ke),De(),N()}catch(l){console.warn("[DTR] applyStandardValuesSmart error:",l)}},Pa=async()=>{Me(!1),await Fe(),$("✓ Executed ApplyStandardValuesSmart + Thickness + Directional Averages + 6-Point Colorfastness + EvaluateRatings.")},Le=async(e,a=null)=>{const s=t("#dtr-mat-record-select");try{s&&(s.innerHTML='<option value="">Loading records…</option>');const r=await P(`dtr/records?slug=${encodeURIComponent(e)}`);if(!s||!r.rows)return;if(!r.rows.length){s.innerHTML='<option value="">No records yet - press “+ New Record”</option>';return}s.innerHTML=r.rows.map(l=>`
        <option value="${i(l.source_id)}" ${String(l.source_id)===String(a)?"selected":""}>
          ${i(l.lab_ref_no)} · ${i(l.material_type||l.form_category)} · ${i(l.model_style||l.material_code||"")} [${i(l.overall_result||"Pass")}]
        </option>
      `).join("")}catch(r){console.error(r),s&&(s.innerHTML='<option value="">Could not load records - reload the page</option>'),window.nxToast&&nxToast("The record list could not be loaded.","error")}},Na=e=>{const a=t("#dtr-raw-cols-container"),s=t("#dtr-raw-col-count");if(!a)return;const r=Object.keys(e||{});s&&(s.textContent=r.length),a.innerHTML=r.map(l=>`
      <div class="dtr-field">
        <label title="${i(l)}"><code>${i(l)}</code></label>
        <input type="text" data-raw-col="${i(l)}" value="${i(e[l]??"")}" ${l==="ID"?'readonly class="dtr-ro"':""}>
      </div>
    `).join("")},ze=(e,a={})=>{xe=e||{},t("#dtr-active-form-label")&&a.label&&(t("#dtr-active-form-label").textContent=`${a.label}`),t("#dtr-active-labref-title")&&(t("#dtr-active-labref-title").textContent=`${e.LabRefNo||"New Report"} — ${e["Material Description"]||e.MaterialDescription||e.MaterialType||""}`),w("#dtr-material-form input, #dtr-material-form select").forEach(s=>{s.name&&(s.value="")}),Object.keys(e).forEach(s=>{const r=e[s]??"",l=t(`#mf_${s.replace(/[^a-zA-Z0-9_]/g,"")}`);l&&(l.value=r);const c=t(`[name="${CSS.escape(s)}"]`,t("#dtr-material-form"));c&&(c.value=r)}),t("#mf_MaterialDescription")&&!t("#mf_MaterialDescription").value&&(t("#mf_MaterialDescription").value=e["Material Description"]||e.MaterialDescription||""),t("#mf_SupplierFactory")&&!t("#mf_SupplierFactory").value&&(t("#mf_SupplierFactory").value=e["Supplier/Factory"]||e.Supplier||e.SupplierFactory||""),t("#mf_POLotNo")&&!t("#mf_POLotNo").value&&(t("#mf_POLotNo").value=e["PO/Lot No"]||e.POLotNo||""),t("#mf_ModelNoStyle")&&!t("#mf_ModelNoStyle").value&&(t("#mf_ModelNoStyle").value=e["Model No/Style"]||e.ModelNoStyle||""),t("#mf_POQuantity")&&!t("#mf_POQuantity").value&&(t("#mf_POQuantity").value=e["PO Quantity"]||e.POQuantity||""),t("#mf_TestedBy")&&!t("#mf_TestedBy").value&&(t("#mf_TestedBy").value=e["Tested by"]||e.TestedBy||e.Technician||""),Na(e),Me(!1),rt(),Se(),Object.keys(Oe).forEach(ke),De(),N()},$e=async(e,a=0)=>{try{const s=await P(`dtr/record?slug=${encodeURIComponent(e)}&source_id=${encodeURIComponent(a)}`);s&&s.ok&&ze(s.record||{},s.config||{})}catch(s){console.error(s)}};(Ze=t("#mf_LabRefNo"))==null||Ze.addEventListener("change",()=>{var s;const e=(((s=t("#mf_LabRefNo"))==null?void 0:s.value)||"").toUpperCase().trim(),a=t("#mf_MaterialType");!e||!a||(e.includes("FBU")||e.includes("FBL")?a.value||(a.value="Canvas"):e.includes("LEU")||e.includes("LEL")?a.value||(a.value="Suede"):e.includes("RB")?a.value="Rubber":e.includes("IB")?a.value="Inner Box":e.includes("OB")?a.value="Outer Box":e.includes("FML")?a.value="Sockliner - Latex":e.includes("FM")?a.value="PU Foam":e.includes("SKL")?a.value="Sockliner - EVA":e.includes("LC")?a.value="Shoe lace":e.includes("TH")?a.value="Thread":e.includes("EY")?a.value="Eyelet":e.includes("GR")?a.value="Grommet":e.includes("ZP")&&(a.value="Zipper"),Fe())});const Ce=async()=>{var r,l,c;const e=((r=t("#mf_MaterialCode"))==null?void 0:r.value.trim())||"",a=((l=t("#mf_POLotNo"))==null?void 0:l.value.trim())||"",s=((c=t("#mf_MTL"))==null?void 0:c.value.trim())||"";if(!e&&!a&&!s){$("Enter a Material Code, PO/Lot No, or MTL # first.",!0);return}try{const o=await P(`dtr/lookup?material_code=${encodeURIComponent(e)}&po_lot_no=${encodeURIComponent(a)}&mtl_no=${encodeURIComponent(s)}`);let d=[];if(o.material&&(t("#mf_SupplierFactory")&&(t("#mf_SupplierFactory").value=o.material.Supplier||""),t("#mf_MaterialDescription")&&(t("#mf_MaterialDescription").value=o.material.MaterialName||""),d.push(`MaterialList (${o.material.MaterialCode})`)),o.shoe&&(t("#mf_ModelNoStyle")&&(t("#mf_ModelNoStyle").value=o.shoe.StyleNo||""),t("#mf_Color")&&(t("#mf_Color").value=o.shoe.Colorway||""),t("#mf_MTL")&&o.shoe.MTL&&(t("#mf_MTL").value=o.shoe.MTL),d.push(`ShoeList (${o.shoe.StyleNo})`)),o.dsr&&o.dsr.length>0){const n=o.dsr[0];t("#mf_StyleNumber_DSR")&&(t("#mf_StyleNumber_DSR").value=n.StyleNumber||""),t("#mf_StyleDescription_DSR")&&(t("#mf_StyleDescription_DSR").value=n.StyleDescription||""),t("#mf_Colorway_DSR")&&(t("#mf_Colorway_DSR").value=n.Colorway||""),t("#mf_ComponentorLocation_DSR")&&(t("#mf_ComponentorLocation_DSR").value=n.ComponentorLocation||""),t("#mf_VFMaterialCode_DSR")&&(t("#mf_VFMaterialCode_DSR").value=n.VFMaterialCode||""),t("#mf_InternalName_DSR")&&(t("#mf_InternalName_DSR").value=n["Internal NameVans1"]||n.Material||""),t("#mf_ColorName_DSR")&&(t("#mf_ColorName_DSR").value=n.ColorName||n.Color||""),d.push(`tblDSR (${o.dsr.length} component rows)`)}d.length?$(`✓ Auto-Lookup populated from: ${d.join(" · ")}`):$("No exact match found in MaterialList / ShoeList / tblDSR.",!0)}catch(o){console.error(o)}};(ta=t("#btn-dtr-lookup-all"))==null||ta.addEventListener("click",Ce),(ea=t("#mf_MaterialCode"))==null||ea.addEventListener("change",Ce),(aa=t("#mf_POLotNo"))==null||aa.addEventListener("change",Ce),(sa=t("#mf_MTL"))==null||sa.addEventListener("change",Ce),(la=t("#mf_MaterialType"))==null||la.addEventListener("change",Fe),w(".dtr-calc-thick").forEach(e=>{e.addEventListener("input",()=>Me(!0))}),w(".dtr-calc-weight, .dtr-calc-sg, .dtr-calc-din, .dtr-calc-ross, .dtr-calc-shrink").forEach(e=>{e.addEventListener("input",()=>{rt(),N()})}),w(".dtr-dir-inp, .dtr-std-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset.dirGroup?Ge(e.dataset.dirGroup):Se(),N()})}),w(".dtr-6pt-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset["6pt"]&&ke(e.dataset["6pt"]),N()})}),w(".dtr-crock-inp").forEach(e=>{e.addEventListener("input",()=>{De(),N()})}),w(".dtr-rate-inp").forEach(e=>{e.addEventListener("change",N),e.addEventListener("input",N)}),(ra=t("#dtr-mat-form-slug"))==null||ra.addEventListener("change",async e=>{st=e.target.value,await Le(st),await $e(st,0)}),(oa=t("#dtr-mat-record-select"))==null||oa.addEventListener("change",async e=>{const a=e.target.value;a&&await $e(st,a)}),(na=t("#btn-dtr-new"))==null||na.addEventListener("click",()=>{const e=new Date().toISOString().slice(0,10);ze({ID:"",LabRefNo:`DTP${e.replace(/-/g,"")}${st.toUpperCase().slice(0,3)}-1`,TR:`TR${e.replace(/-/g,"")}001`,ReceivedDate:e,TestedDate:e,FinishedDate:e,Status:"Complete",Remarks:"PASS"}),$("Initialized new blank test report record.")}),(ia=t("#btn-dtr-review"))==null||ia.addEventListener("click",()=>{const e=prompt("Enter Reviewer Name:","Faye Ann Elaysa");if(!e)return;const a=t('[data-raw-col="AuthorizedBy"]');a&&(a.value=e);const s=t('[data-raw-col="AuthorizationStatus"]');s&&(s.value="Reviewed"),$(`✓ Record stamped Reviewed by ${e}`)}),(ca=t("#btn-dtr-refresh-calc"))==null||ca.addEventListener("click",Pa),(da=t("#btn-dtr-load-machine"))==null||da.addEventListener("click",async()=>{var a;const e=((a=t("#mf_LabRefNo"))==null?void 0:a.value.trim())||"";if(!e){$("Enter a LabRefNo first before loading machine results.",!0);return}try{const s=await P("dtr/load-machine",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:st,lab_ref_no:e})});s&&s.loadedFields&&(Object.keys(s.loadedFields).forEach(r=>{const l=s.loadedFields[r],c=t(`#mf_${r}`)||t(`[name="${CSS.escape(r)}"]`,t("#dtr-material-form"));c&&(c.value=l);const o=t(`[data-raw-col="${CSS.escape(r)}"]`);o&&(o.value=l)}),rt(),Se(),N(),t("#dtr-machine-status")&&(t("#dtr-machine-status").textContent=s.message||"Machine data loaded."),$(`${s.message}`))}catch(s){$("Machine loader error: "+(s.message||""),!0)}}),(fa=t("#btn-dtr-save"))==null||fa.addEventListener("click",async()=>{const e=Object.assign({},xe);w("#dtr-raw-cols-container [data-raw-col]").forEach(a=>{e[a.dataset.rawCol]=a.value}),w("#dtr-material-form [name]").forEach(a=>{a.name&&(e[a.name]=a.value)});try{const a=await P("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:st,fields:e})});a&&a.ok?(t("#mf_ID")&&(t("#mf_ID").value=a.recordId),$(`✓ ${a.message} (Ready in InterLink Sync ComboBox!)`),await Le(st,a.recordId)):$(a.error||"Save failed.",!0)}catch{$("Failed to save record.",!0)}}),(pa=t("#btn-toggle-raw-cols"))==null||pa.addEventListener("click",()=>{var e;(e=t("#dtr-raw-cols-container"))==null||e.classList.toggle("hidden")});const Wa={TODDLER:14,KIDS:16,CHILDREN:24,ADULT:35},we=1,Re=e=>Math.round(e*.8*100)/100,qe=(e,a)=>!Number.isFinite(e)||e<=0?null:e>=a?"PASS":e>=Re(a)?"TOLERABLE":"FAIL",Et=()=>{var p,u;const e=(((p=t("#bf_AgeCategory"))==null?void 0:p.value)||"ADULT").toUpperCase(),a=Wa[e]||35,s=parseFloat(((u=t("#bf_STD"))==null?void 0:u.value)??"2.5")||2.5;w("#dtr-bonding-points-tbody [data-spot-row]").forEach(y=>{const R=Number(y.dataset.spotRow);y.style.opacity=R<=a?"1":"0.45"});const r=(y,R,M)=>{const S=[];let k=!1,I=!1;for(let U=1;U<=R;U++){const x=t(`#bp_${y}${U}`);if(!x)continue;const D=parseFloat(x.value??"");if(x.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),Number.isFinite(D)&&D>0){S.push(D);const H=qe(D,M);H==="FAIL"?(k=!0,x.classList.add("dtr-rate-fail")):H==="TOLERABLE"?(I=!0,x.classList.add("dtr-rate-warn")):x.classList.add("dtr-rate-pass")}}return S.length?{min:T(Math.min(...S),2),max:T(Math.max(...S),2),ave:T(S.reduce((U,x)=>U+x,0)/S.length,2),res:k?"FAIL":I?"TOLERABLE":"PASS"}:{min:"",max:"",ave:"",res:""}},l=r("UTFpoint",a,s),c=r("FTOpoint",a,s),o=r("FiTO",13,we);t("#bp_UTFMin")&&(t("#bp_UTFMin").value=l.min),t("#bp_UTFMax")&&(t("#bp_UTFMax").value=l.max),t("#bp_UTFAverage")&&(t("#bp_UTFAverage").value=l.ave),t("#bp_UTF_Result")&&(t("#bp_UTF_Result").value=l.res,E(t("#bp_UTF_Result"))),t("#bp_FTOMin")&&(t("#bp_FTOMin").value=c.min),t("#bp_FTOMax")&&(t("#bp_FTOMax").value=c.max),t("#bp_FTOAverage")&&(t("#bp_FTOAverage").value=c.ave),t("#bp_FTO_Result")&&(t("#bp_FTO_Result").value=c.res,E(t("#bp_FTO_Result"))),t("#bp_FiTOMin")&&(t("#bp_FiTOMin").value=o.min),t("#bp_FiTOMax")&&(t("#bp_FiTOMax").value=o.max),t("#bp_FiTOAverage")&&(t("#bp_FiTOAverage").value=o.ave),t("#bp_FiTO_Result")&&(t("#bp_FiTO_Result").value=o.res,E(t("#bp_FiTO_Result")));const d=[l.res,c.res,o.res].filter(Boolean);let n="PASS";d.includes("FAIL")?n="FAIL":d.includes("TOLERABLE")&&(n="TOLERABLE"),t("#bf_Result")&&(t("#bf_Result").value=n,E(t("#bf_Result")));const b=t("#bf-overall-badge");b&&(b.textContent=n,b.className="badge "+(n==="FAIL"?"b-err":n==="TOLERABLE"?"b-warn":"b-ok"))},Pe=async(e=null)=>{const a=t("#dtr-bonding-record-select");try{a&&(a.innerHTML='<option value="">Loading reports…</option>');const s=await P("dtr/records?slug=bonding");if(!a||!s.rows)return;if(!s.rows.length){a.innerHTML='<option value="">No bonding reports yet</option>';return}a.innerHTML=s.rows.map(r=>`
        <option value="${i(r.source_id)}" ${String(r.source_id)===String(e)?"selected":""}>
          ${i(r.lab_ref_no)} · ${i(r.model_style||"")} · ${i(r.color||"")} [${i(r.overall_result||"Pass")}]
        </option>
      `).join("")}catch(s){console.error(s),a&&(a.innerHTML='<option value="">Could not load reports - reload the page</option>'),window.nxToast&&nxToast("The bonding report list could not be loaded.","error")}},Ne=async(e=0)=>{try{const a=await P(`dtr/record?slug=bonding&source_id=${encodeURIComponent(e)}`);if(!a||!a.ok)return;const s=a.record||{},r=a.bondingPoints||{};v=r,Object.keys(s).forEach(l=>{const c=t(`#bf_${l}`);c&&(c.value=s[l]??"")}),w('#dtr-bonding-form [name^="cf_"]').forEach(l=>{l.value=(a.customValues||{})[l.name]??""});for(let l=1;l<=35;l++)t(`#bp_UTFpoint${l}`)&&(t(`#bp_UTFpoint${l}`).value=r[`UTFpoint${l}`]??""),t(`#bp_FTOpoint${l}`)&&(t(`#bp_FTOpoint${l}`).value=r[`FTOpoint${l}`]??""),l<=13&&t(`#bp_FiTO${l}`)&&(t(`#bp_FiTO${l}`).value=r[`FiTO${l}`]??"");Et()}catch(a){console.error(a)}};(ua=t("#dtr-bonding-record-select"))==null||ua.addEventListener("change",e=>{e.target.value&&Ne(e.target.value)}),(ma=t("#bf_AgeCategory"))==null||ma.addEventListener("change",Et),(va=t("#bf_STD"))==null||va.addEventListener("change",Et),w("#dtr-bonding-points-tbody .dtr-bp-inp").forEach(e=>{e.addEventListener("input",Et)}),(_a=t("#btn-bond-recalc"))==null||_a.addEventListener("click",()=>{Et(),$("✓ Recalculated UTF, FTO, and FiTO Min/Max/Ave & Spot Tolerances.")}),(ba=t("#btn-bond-new"))==null||ba.addEventListener("click",()=>{const e=new Date().toISOString().slice(0,10);w("#dtr-bonding-form input").forEach(a=>{a.value=""}),t("#bf_LabRefNo")&&(t("#bf_LabRefNo").value=`DTP${e.replace(/-/g,"")}SHB01-R`),t("#bf_AgeCategory")&&(t("#bf_AgeCategory").value="ADULT"),t("#bf_STD")&&(t("#bf_STD").value="2.5"),t("#bf_ReceivedDate")&&(t("#bf_ReceivedDate").value=e),t("#bf_TestedDate")&&(t("#bf_TestedDate").value=e),t("#bf_FinishedDate")&&(t("#bf_FinishedDate").value=e),Et(),$("Initialized new Whole Shoe Bonding Test Report.")}),(ha=t("#btn-bond-load-u60"))==null||ha.addEventListener("click",async()=>{var a;const e=((a=t("#bf_LabRefNo"))==null?void 0:a.value.trim())||"DTP20261024SHB11-R";try{const s=await P("dtr/load-machine",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"bonding",lab_ref_no:e})});s&&s.loadedFields&&(Object.keys(s.loadedFields).forEach(r=>{const l=t(`#bp_${r}`);l&&(l.value=s.loadedFields[r])}),Et(),$(`${s.message}`))}catch{$("Failed to load U60 machine file.",!0)}}),(ga=t("#btn-bond-save"))==null||ga.addEventListener("click",async()=>{var s,r,l,c,o,d,n,b,p,u,y,R,M;const e={};w("#dtr-bonding-form [name]").forEach(S=>{S.name&&(e[S.name]=S.value)});const a={UTFMin:((s=t("#bp_UTFMin"))==null?void 0:s.value)||null,UTFMax:((r=t("#bp_UTFMax"))==null?void 0:r.value)||null,UTFAverage:((l=t("#bp_UTFAverage"))==null?void 0:l.value)||null,UTF_Result:((c=t("#bp_UTF_Result"))==null?void 0:c.value)||null,FTOMin:((o=t("#bp_FTOMin"))==null?void 0:o.value)||null,FTOMax:((d=t("#bp_FTOMax"))==null?void 0:d.value)||null,FTOAverage:((n=t("#bp_FTOAverage"))==null?void 0:n.value)||null,FTO_Result:((b=t("#bp_FTO_Result"))==null?void 0:b.value)||null,FiTOMin:((p=t("#bp_FiTOMin"))==null?void 0:p.value)||null,FiTOMax:((u=t("#bp_FiTOMax"))==null?void 0:u.value)||null,FiTOAverage:((y=t("#bp_FiTOAverage"))==null?void 0:y.value)||null,FiTO_Result:((R=t("#bp_FiTO_Result"))==null?void 0:R.value)||null,Result:((M=t("#bf_Result"))==null?void 0:M.value)||"PASS"};w("#dtr-bonding-points-tbody [data-bp-col]").forEach(S=>{a[S.dataset.bpCol]=S.value});try{const S=await P("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"bonding",fields:e,bondingPoints:a})});S&&S.ok&&(t("#bf_BondingTestID")&&(t("#bf_BondingTestID").value=S.recordId),$(`✓ ${S.message}`),await Pe(S.recordId))}catch{$("Error saving Bonding Report.",!0)}});const Qe=()=>{var mt,z,q,W,vt,ct,Pt,Nt,Wt,It,dt,tt,et,at,_t,Ut,bt,ht,gt,Tt,yt,St,Ft,Lt,$t,Ct,wt;const e=parseFloat(((mt=t("#calc_raw_w1"))==null?void 0:mt.value)??"0")||0,a=parseFloat(((z=t("#calc_raw_w2"))==null?void 0:z.value)??"0")||0,s=parseFloat(((q=t("#calc_raw_w3"))==null?void 0:q.value)??"0")||0,r=T(e/100*1e4,2),l=T(a/100*1e4,2),c=T(s/100*1e4,2),o=T((r+l+c)/3,2);t("#calc_res_w1")&&(t("#calc_res_w1").value=r),t("#calc_res_w2")&&(t("#calc_res_w2").value=l),t("#calc_res_w3")&&(t("#calc_res_w3").value=c),t("#calc_res_wave")&&(t("#calc_res_wave").textContent=o);const d=parseFloat(((W=t("#calc_sg_w1"))==null?void 0:W.value)??"0")||0,n=parseFloat(((vt=t("#calc_sg_w2"))==null?void 0:vt.value)??"0")||0,b=parseFloat(((ct=t("#calc_sg_w3"))==null?void 0:ct.value)??"0")||0,p=parseFloat(((Pt=t("#calc_sg_t1"))==null?void 0:Pt.value)??"1")||1,u=parseFloat(((Nt=t("#calc_sg_t2"))==null?void 0:Nt.value)??"1")||1,y=parseFloat(((Wt=t("#calc_sg_t3"))==null?void 0:Wt.value)??"1")||1,R=T(d/(100*(p/10)),3),M=T(n/(100*(u/10)),3),S=T(b/(100*(y/10)),3),k=T((R+M+S)/3,3);t("#calc_res_sg_ave")&&(t("#calc_res_sg_ave").textContent=`${R}, ${M}, ${S} (Ave: ${k})`);const I=parseFloat(((It=t("#calc_cp_b1"))==null?void 0:It.value)??"10")||10,U=parseFloat(((dt=t("#calc_cp_a1"))==null?void 0:dt.value)??"9")||9,x=parseFloat(((tt=t("#calc_cp_b2"))==null?void 0:tt.value)??"10")||10,D=parseFloat(((et=t("#calc_cp_a2"))==null?void 0:et.value)??"9")||9,H=parseFloat(((at=t("#calc_cp_a3"))==null?void 0:at.value)??"9")||9,J=parseFloat(((_t=t("#calc_cp_sp"))==null?void 0:_t.value)??"0")||0,ot=I-J!==0?I-J:1,ft=x-J!==0?x-J:1,V=T((I-U)/ot*100,1),nt=T((x-D)/ft*100,1),A=T((I-H)/ot*100,1),j=T((V+nt+A)/3,1);t("#calc_res_cp_ave")&&(t("#calc_res_cp_ave").textContent=`${j}% (${V}%, ${nt}%, ${A}%)`);const xt=parseFloat(((Ut=t("#calc_din_wb"))==null?void 0:Ut.value)??"0")||0,Ht=parseFloat(((bt=t("#calc_din_wa"))==null?void 0:bt.value)??"0")||0,Mt=parseFloat(((ht=t("#calc_din_sg"))==null?void 0:ht.value)??"1")||1,Ot=parseFloat(((gt=t("#calc_din_sa"))==null?void 0:gt.value)??"200")||200,Bt=T((xt-Ht)/Mt*(200/Ot)*1e3,2);t("#calc_res_din")&&(t("#calc_res_din").textContent=Bt);const kt=parseFloat(((Tt=t("#calc_rf_li1"))==null?void 0:Tt.value)??"2.5")||2.5,Dt=parseFloat(((yt=t("#calc_rf_lf1"))==null?void 0:yt.value)??"2.5")||2.5,pt=parseFloat(((St=t("#calc_rf_li2"))==null?void 0:St.value)??"2.5")||2.5,jt=parseFloat(((Ft=t("#calc_rf_lf2"))==null?void 0:Ft.value)??"2.5")||2.5,Vt=T((Dt-kt)/kt*100,2),G=T((jt-pt)/pt*100,2);t("#calc_res_rf")&&(t("#calc_res_rf").textContent=`${Vt}% / ${G}%`);const K=parseFloat(((Lt=t("#calc_sh_lb"))==null?void 0:Lt.value)??"100")||100,Z=parseFloat((($t=t("#calc_sh_la"))==null?void 0:$t.value)??"100")||100,ut=parseFloat(((Ct=t("#calc_sh_wb"))==null?void 0:Ct.value)??"100")||100,it=parseFloat(((wt=t("#calc_sh_wa"))==null?void 0:wt.value)??"100")||100,Y=T((K-Z)/K*100,2),F=T((ut-it)/ut*100,2);t("#calc_res_sh")&&(t("#calc_res_sh").textContent=`L: ${Y}% / W: ${F}%`)};w("#dtr-panel-calculators input").forEach(e=>{e.addEventListener("input",Qe)}),(Ta=t("#btn-apply-calc-weight"))==null||Ta.addEventListener("click",()=>{var e,a,s;t("#mf_Weight1")&&(t("#mf_Weight1").value=((e=t("#calc_res_w1"))==null?void 0:e.value)||""),t("#mf_Weight2")&&(t("#mf_Weight2").value=((a=t("#calc_res_w2"))==null?void 0:a.value)||""),t("#mf_Weight3")&&(t("#mf_Weight3").value=((s=t("#calc_res_w3"))==null?void 0:s.value)||""),rt(),N(),lt("material"),$("✓ Applied calculated Weight 1..3 and WAve to Material Form.")}),(ya=t("#btn-apply-calc-sg"))==null||ya.addEventListener("click",()=>{var o,d,n,b,p,u;const e=parseFloat(((o=t("#calc_sg_w1"))==null?void 0:o.value)??"0")||0,a=parseFloat(((d=t("#calc_sg_w2"))==null?void 0:d.value)??"0")||0,s=parseFloat(((n=t("#calc_sg_w3"))==null?void 0:n.value)??"0")||0,r=parseFloat(((b=t("#calc_sg_t1"))==null?void 0:b.value)??"1")||1,l=parseFloat(((p=t("#calc_sg_t2"))==null?void 0:p.value)??"1")||1,c=parseFloat(((u=t("#calc_sg_t3"))==null?void 0:u.value)??"1")||1;t("#mf_SG1")&&(t("#mf_SG1").value=T(e/(100*(r/10)),3)),t("#mf_SG2")&&(t("#mf_SG2").value=T(a/(100*(l/10)),3)),t("#mf_SG3")&&(t("#mf_SG3").value=T(s/(100*(c/10)),3)),rt(),N(),lt("material"),$("✓ Applied Specific Gravity SG1..3 and SG_Ave to Material Form.")}),(Sa=t("#btn-apply-calc-din"))==null||Sa.addEventListener("click",()=>{var a;const e=((a=t("#calc_res_din"))==null?void 0:a.textContent)||"";t("#mf_DIN_1")&&(t("#mf_DIN_1").value=e),rt(),N(),lt("material"),$("✓ Applied DIN Abrasion value to Material Form.")}),(Fa=t("#btn-apply-calc-ross"))==null||Fa.addEventListener("click",()=>{var l,c,o,d;const e=parseFloat(((l=t("#calc_rf_li1"))==null?void 0:l.value)??"2.5")||2.5,a=parseFloat(((c=t("#calc_rf_lf1"))==null?void 0:c.value)??"2.5")||2.5,s=parseFloat(((o=t("#calc_rf_li2"))==null?void 0:o.value)??"2.5")||2.5,r=parseFloat(((d=t("#calc_rf_lf2"))==null?void 0:d.value)??"2.5")||2.5;t("#mf_RossFlex_1")&&(t("#mf_RossFlex_1").value=T((a-e)/e*100,2)),t("#mf_RossFlex_2")&&(t("#mf_RossFlex_2").value=T((r-s)/s*100,2)),rt(),N(),lt("material"),$("✓ Applied Ross Flex 1 & 2 to Material Form.")}),(La=t("#btn-apply-calc-shrink"))==null||La.addEventListener("click",()=>{var l,c,o,d;const e=parseFloat(((l=t("#calc_sh_lb"))==null?void 0:l.value)??"100")||100,a=parseFloat(((c=t("#calc_sh_la"))==null?void 0:c.value)??"100")||100,s=parseFloat(((o=t("#calc_sh_wb"))==null?void 0:o.value)??"100")||100,r=parseFloat(((d=t("#calc_sh_wa"))==null?void 0:d.value)??"100")||100;t("#mf_ShrinkageL")&&(t("#mf_ShrinkageL").value=T((e-a)/e*100,2)),t("#mf_ShrinkageW")&&(t("#mf_ShrinkageW").value=T((s-r)/s*100,2)),rt(),N(),lt("material"),$("✓ Applied Shrinkage L & W to Material Form.")});const We=async()=>{var s,r;const e=((s=t("#exp_mat_type"))==null?void 0:s.value)||"Canvas",a=((r=t("#exp_t_ave"))==null?void 0:r.value)||"1.25";try{const l=await P(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`),c=t("#exp-standards-tbody");if(!c||!l.standards)return;c.innerHTML=l.standards.map(o=>`
        <tr>
          <td><b>${i(o.MaterialType)}</b></td>
          <td class="mono"><code>${i(o.TestMethod)}</code></td>
          <td class="mono"><b>${i(o.StandardValue??"-")}</b></td>
          <td class="mono">${i(o.Tolerance??"-")}</td>
          <td class="mono">${i(o.TMinC??"-")}</td>
          <td class="mono">${i(o.TMaxC??"-")}</td>
        </tr>
      `).join("")}catch(l){console.error(l)}};($a=t("#btn-exp-run"))==null||$a.addEventListener("click",We),(Ca=t("#exp_mat_type"))==null||Ca.addEventListener("change",We);const Ia=()=>{var Ht,Mt,Ot,Bt,kt,Dt,pt,jt,Vt,G,K,Z,ut,it,Y,F,mt,z,q,W,vt,ct,Pt,Nt,Wt,It,dt,tt,et,at,_t,Ut,bt,ht,gt,Tt,yt,St,Ft,Lt,$t,Ct,wt,Gt,zt,qt,Qt,Xt,Jt,Kt,Yt,Zt,te,ee,ae,se,le,re,oe,ne,ie,ce,de,fe,pe,ue,me,ve,_e,be,he,ge,Te,ye,f;const e=((Ht=t("#mf_LabRefNo"))==null?void 0:Ht.value)||"DTP-LAB-PREVIEW",a=((Mt=t("#mf_TR"))==null?void 0:Mt.value)||"-",s=((Ot=t("#mf_MaterialType"))==null?void 0:Ot.value)||"Material",r=((Bt=t("#mf_MaterialCode"))==null?void 0:Bt.value)||"-",l=((kt=t("#mf_MaterialDescription"))==null?void 0:kt.value)||"-",c=((Dt=t("#mf_SupplierFactory"))==null?void 0:Dt.value)||"DATIAN SUBIC SHOES INC.",o=((pt=t("#mf_ModelNoStyle"))==null?void 0:pt.value)||"-",d=((jt=t("#mf_Color"))==null?void 0:jt.value)||"-",n=((Vt=t("#mf_POLotNo"))==null?void 0:Vt.value)||"-",b=((G=t("#mf_MTL"))==null?void 0:G.value)||"-",p=Object.assign({},xe||{});w("[data-raw-col]").forEach(m=>{p[m.dataset.rawCol]=m.value});const u=(...m)=>{var _;for(const g of m){const h=(_=t("#mf_"+g))==null?void 0:_.value;if(h!==void 0&&String(h).trim()!=="")return String(h).trim();const L=p[g];if(L!=null&&String(L).trim()!=="")return String(L).trim()}return""},y=m=>String(m||"").replace(/ 00:00:00$/,""),R=y(u("TestedDate","Tested Date"))||new Date().toISOString().slice(0,10),M=u("Technician","TestedBy"),S=u("Supplier's Country","SupplierCountry","Country"),k=u("Quantity","PO/LotQuantity"),I=u("SendBy","Send by"),U=y(u("RecievedDate","ReceivedDate")),x=y(u("FinishedDate")),D=u("MaterialSource"),H=[["Prod. Material","Prod. Material 量产材料"],["Prod. Re-test","Prod. Re-test 量产重测"],["New/Development","New/Development 开发按新材料"],["Sales Sample","Sales Sample (销售样品)"]].map(([m,_])=>`<span style="white-space:nowrap;margin-right:14px"><b style="color:${D===m?"#1d4ed8":"#94a3b8"}">${D===m?"●":"○"}</b> ${D===m?'<b style="color:#1d4ed8">'+i(_)+"</b>":i(_)}</span>`).join(""),J=[1,2,3,4].map(m=>`<span style="margin-right:12px"><b style="color:#94a3b8">○</b> Tech ${m}</span>`).join(""),ot=u("AuthorizedBy","ReviewedBy"),ft=y(u("AuthorizedDate")),V=(u("Remarks")||"PASS").toUpperCase(),nt=V==="PASS"?"":V==="TOLERABLE"?"stamp-tol":"stamp-fail",A=[],j=(m,_,g,h,L,O,B,C)=>{(g||O)&&A.push(`
          <tr>
            <td class="text-left"><b>${m} — Length (L)</b></td>
            <td>${_}</td>
            <td><b>${g||"-"}</b></td>
            <td>${h||"-"}</td>
            <td><b class="${L==="PASS"?"dtr-rate-pass":L==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${L||"-"}</b></td>
          </tr>
          <tr>
            <td class="text-left"><b>${m} — Width (W)</b></td>
            <td>${_}</td>
            <td><b>${O||"-"}</b></td>
            <td>${B||"-"}</td>
            <td><b class="${C==="PASS"?"dtr-rate-pass":C==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${C||"-"}</b></td>
          </tr>
        `)};j("Tensile Strength","kg",(K=t("#mf_TensileLAve"))==null?void 0:K.value,(Z=t("#mf_Tensile_L"))==null?void 0:Z.value,(ut=t("#mf_TensileLRating"))==null?void 0:ut.value,(it=t("#mf_TensileWAve"))==null?void 0:it.value,(Y=t("#mf_Tensile_W"))==null?void 0:Y.value,(F=t("#mf_TensileWRating"))==null?void 0:F.value),j("Elongation","%",(mt=t("#mf_ElongationLAve"))==null?void 0:mt.value,(z=t("#mf_Elongation_L"))==null?void 0:z.value,(q=t("#mf_ElongationLRating"))==null?void 0:q.value,(W=t("#mf_ElongationWAve"))==null?void 0:W.value,(vt=t("#mf_Elongation_W"))==null?void 0:vt.value,(ct=t("#mf_ElongationWRating"))==null?void 0:ct.value),j("Trouser / Wing Tear","kg",(Pt=t("#mf_TrouserTearLAve"))==null?void 0:Pt.value,(Nt=t("#mf_TrouserTear_L"))==null?void 0:Nt.value,(Wt=t("#mf_TrouserTearLRating"))==null?void 0:Wt.value,(It=t("#mf_TrouserTearWAve"))==null?void 0:It.value,(dt=t("#mf_TrouserTear_W"))==null?void 0:dt.value,(tt=t("#mf_TrouserTearWRating"))==null?void 0:tt.value),j("Stitch Tear","kg",(et=t("#mf_StitchTearLAve"))==null?void 0:et.value,(at=t("#mf_StitchTear_L"))==null?void 0:at.value,(_t=t("#mf_StitchTearLRating"))==null?void 0:_t.value,(Ut=t("#mf_StitchTearWAve"))==null?void 0:Ut.value,(bt=t("#mf_StitchTear_W"))==null?void 0:bt.value,(ht=t("#mf_StitchTearWRating"))==null?void 0:ht.value),(gt=t("#mf_Text3977"))!=null&&gt.value&&A.push(`
        <tr>
          <td class="text-left"><b>Mullen Bursting Strength</b></td>
          <td>kg/cm²</td>
          <td><b>${(Tt=t("#mf_Text3977"))==null?void 0:Tt.value}</b></td>
          <td>${((yt=t("#mf_MullenBurst"))==null?void 0:yt.value)||"-"}</td>
          <td><b>${((St=t("#mf_MullenBurstRating"))==null?void 0:St.value)||"-"}</b></td>
        </tr>
      `),(Ft=t("#mf_DieC_Ave"))!=null&&Ft.value&&A.push(`
        <tr>
          <td class="text-left"><b>Die C Tear / Edge Crush</b></td>
          <td>kg</td>
          <td><b>${(Lt=t("#mf_DieC_Ave"))==null?void 0:Lt.value}</b></td>
          <td>${(($t=t("#mf_DieC_Std"))==null?void 0:$t.value)||"-"}</td>
          <td><b>${((Ct=t("#mf_DieC_Rate"))==null?void 0:Ct.value)||"-"}</b></td>
        </tr>
      `),(wt=t("#mf_WAve"))!=null&&wt.value&&A.push(`
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
      `),(()=>{const m=A.length?/^(tensile|elongation|trousertear|stitchtear|mullen|diec|ctp|ctw|ctcdry|ctcwet)/i:/^$/,_={heataging:"Heat Aging (72 hrs, 70°C)",corrosion:"Corrosion Resistance",hydrolysis:"Hydrolysis (7 days, 60°C, 95% RH)",uv:"UV Light (24 hrs, 50°C)",ctcdry:"Colorfastness to Crocking (Dry)",ctcwet:"Colorfastness to Crocking (Wet)",ctw:"Colorfastness to Water",ctp:"Colorfastness to Perspiration",shrinkage12:"Shrinkage (1 hr, 70°C) 1-2",shrinkage34:"Shrinkage (1 hr, 70°C) 3-4",tensile:"Tensile",elongation:"Elongation",stitchtear:"Stitch Tear",peelstrength:"Peel Strength",shearstrength:"Shear Strength",laceTipRet:"Lace Tip Retention",knotslip:"Knot Slip",laceabrasion:"Lace Abrasion",hardness:"Hardness",sg:"Specific Gravity",pvc:"PVC Contact Discoloration"},g={corrosion:["CorrosionRes"],ctcdry:["CtCDry"],ctcwet:["CtCWet"],shrinkage12:["Shrinkageave12"],shrinkage34:["Shrinkageave34"]},h=B=>B.replace(/[.*+?^${}()|[\]\\]/g,"\\$&"),L=B=>{const C=String(B).trim().toUpperCase();return["P","PASS"].includes(C)?"PASS":["F","FAIL","FAILED"].includes(C)?"FAIL":C==="T"?"TOLERABLE":C},O=new Set;Object.keys(p).forEach(B=>{const C=B.match(/^(.+?)(?:_R|_?Rating|_?rating)$/),Rt=C&&String(p[B]??"").trim();if(!Rt)return;const At=C[1].replace(/txt/i,""),Ee=At.toLowerCase();if(At.length<3||O.has(Ee)||m.test(At))return;const Oa=[At,...g[Ee]||[]],Ba=Q=>/(_S|_std|Std|_Std|_R|Rating|_rating)$/.test(Q),ka=Object.keys(p).filter(Q=>!Ba(Q)&&Oa.some(He=>new RegExp("^"+h(He)+"(?:txt)?(\\d*|_?ave)$","i").test(Q))).map(Q=>String(p[Q]??"").trim()).filter(Q=>Q!==""&&!/^0(\.0+)?$/.test(Q));if(!ka.length)return;O.add(Ee);const Da=Object.keys(p).find(Q=>Oa.some(He=>new RegExp("^"+h(He)+"(?:txt)?(_S|_std|Std|_Std)$","i").test(Q))&&String(p[Q]??"").trim()!==""),ja=_[Ee]||At.replace(/([a-z])([A-Z0-9])/g,"$1 $2"),Ue=L(Rt);A.push(`
          <tr>
            <td class="text-left"><b>${i(ja)}</b></td>
            <td>-</td>
            <td><b>${ka.map(i).join(" &nbsp; ")}</b></td>
            <td>${i(Da?p[Da]:"-")}</td>
            <td><b class="${Ue==="PASS"?"dtr-rate-pass":Ue==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${i(Ue)}</b></td>
          </tr>
        `)})})();const xt=`
      <div class="dtr-report-sheet">
        <div class="rpt-logo-row">
          <img class="rpt-logo-vf" src="${X}assets/images/vf-logo.webp" alt="VF">
          <img class="rpt-logo-satra" src="${X}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
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
              <span class="dtr-sheet-stamp-box ${nt}">${i(V)}</span>
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
            <td>${i(R)}</td>
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
            <td>${i(d)} / ${i(b)}</td>
          </tr>
          <tr>
            <td class="label-cell">Thickness (TAve)</td>
            <td><b>${((Te=t("#mf_TAve"))==null?void 0:Te.value)||"-"} mm</b></td>
            <td class="label-cell">Weight (WAve)</td>
            <td><b>${((ye=t("#mf_WAve"))==null?void 0:ye.value)||"-"} g/m²</b></td>
            <td class="label-cell">Technician</td>
            <td>${i(M||"-")}</td>
          </tr>
          <tr>
            <td class="label-cell">Supplier's Country</td>
            <td>${i(S||"-")}</td>
            <td class="label-cell">Quantity</td>
            <td>${i(k||"-")}</td>
            <td class="label-cell">Received Date</td>
            <td>${i(U||"-")}</td>
          </tr>
          <tr>
            <td class="label-cell">Material Source</td>
            <td colspan="3" style="line-height:1.9">${H}<br>${J}</td>
            <td class="label-cell">Finished Date</td>
            <td>${i(x||"-")}</td>
          </tr>
          <tr>
            <td class="label-cell">Send by</td>
            <td colspan="5">${i(I||"-")}</td>
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
              <span>${i(M)}</span>
              <span class="muted">${i(R)}</span>
            </div>
          </div>
          <div class="dtr-sheet-sign-box">
            <div>Reviewed &amp; Approved by (QA Lab Head):</div>
            <div class="dtr-sheet-sign-line">
              <span>${i(ot)}</span>
              <span class="muted">${i(ft||R)}</span>
            </div>
          </div>
        </div>
      </div>
    `;t("#dtr-print-modal-title").textContent=`Official Test Report Sheet — ${e}`,t("#dtr-print-sheet-container").innerHTML=xt,(f=t("#dtr-print-modal-backdrop"))==null||f.classList.remove("hidden")};let Ae=null;const Ua=async()=>{if(Ae)return Ae;try{const e=await fetch(X+"assets/images/bonding_sketches/sketch_configs.json");if(e.ok)return Ae=await e.json(),Ae}catch(e){console.warn("Failed to load sketch_configs.json",e)}return null},Ha=async()=>{var bt,ht,gt,Tt,yt,St,Ft,Lt,$t,Ct,wt,Gt,zt,qt,Qt,Xt,Jt,Kt,Yt,Zt,te,ee,ae,se,le,re,oe,ne,ie,ce,de,fe,pe,ue,me,ve,_e,be,he,ge,Te,ye;const e=parseInt(((bt=t("#bf_BondingTestID"))==null?void 0:bt.value)||(v==null?void 0:v.BondingTestID)||0)||0,a=((ht=t("#bf_LabRefNo"))==null?void 0:ht.value)||"DTP-BOND-PREVIEW",s=((gt=t("#bf_ETRFNo"))==null?void 0:gt.value)||"-",r=((Tt=t("#bf_ModelStyle"))==null?void 0:Tt.value)||"-",l=((yt=t("#bf_Color"))==null?void 0:yt.value)||"-",c=((St=t("#bf_Side"))==null?void 0:St.value)||"Left",o=c.toLowerCase()==="left",d=(((Ft=t("#bf_AgeCategory"))==null?void 0:Ft.value)||"ADULT").toUpperCase(),n=parseFloat(((Lt=t("#bf_STD"))==null?void 0:Lt.value)||"2.5")||2.5,b=(($t=t("#bf_POLotNO"))==null?void 0:$t.value)||"-",p=((Ct=t("#bf_POQuantity"))==null?void 0:Ct.value)||"-",u=((wt=t("#bf_Factory"))==null?void 0:wt.value)||"DATIAN SUBIC SHOES INC. (DTP)",y=((Gt=t("#bf_AdhesiveSupplier"))==null?void 0:Gt.value)||"-",R=((zt=t("#bf_ShippedDate"))==null?void 0:zt.value)||"-",M=((qt=t("#bf_ProductionDate"))==null?void 0:qt.value)||"-",S=((Qt=t("#bf_Sendby"))==null?void 0:Qt.value)||"-",k=((Xt=t("#bf_MaterialSource"))==null?void 0:Xt.value)||"Prod. Shoes",I=((Jt=t("#bf_ModeOfFailure"))==null?void 0:Jt.value)||"-",U=((Kt=t("#bf_PossibleCause"))==null?void 0:Kt.value)||"-",x=((Yt=t("#bf_Remarks"))==null?void 0:Yt.value)||((Zt=t("#bf_LabNotesComments"))==null?void 0:Zt.value)||"",D=((te=t("#bf_ReceivedDate"))==null?void 0:te.value)||"-",H=((ee=t("#bf_TestedDate"))==null?void 0:ee.value)||new Date().toISOString().slice(0,10),J=((ae=t("#bf_FinishedDate"))==null?void 0:ae.value)||H,ot=((se=t("#bf_Technician"))==null?void 0:se.value)||"L. Aquidado",ft=((le=t("#bf_ReviewedBy"))==null?void 0:le.value)||"QA Lead",V=(((re=t("#bf_Result"))==null?void 0:re.value)||"PASS").toUpperCase(),nt=V==="PASS"?"":V==="TOLERABLE"?"stamp-tol":"stamp-fail",A=((oe=t("#bp_UTFMin"))==null?void 0:oe.value)||(v==null?void 0:v.UTFMin)||"-",j=((ne=t("#bp_UTFMax"))==null?void 0:ne.value)||(v==null?void 0:v.UTFMax)||"-",xt=((ie=t("#bp_UTFAverage"))==null?void 0:ie.value)||(v==null?void 0:v.UTFAverage)||"-",Ht=(((ce=t("#bp_UTF_Result"))==null?void 0:ce.value)||(v==null?void 0:v.UTF_Result)||"-").toUpperCase(),Mt=((de=t("#bp_FTOMin"))==null?void 0:de.value)||(v==null?void 0:v.FTOMin)||"-",Ot=((fe=t("#bp_FTOMax"))==null?void 0:fe.value)||(v==null?void 0:v.FTOMax)||"-",Bt=((pe=t("#bp_FTOAverage"))==null?void 0:pe.value)||(v==null?void 0:v.FTOAverage)||"-",kt=(((ue=t("#bp_FTO_Result"))==null?void 0:ue.value)||(v==null?void 0:v.FTO_Result)||"-").toUpperCase(),Dt=((me=t("#bp_FiTOMin"))==null?void 0:me.value)||(v==null?void 0:v.FiTOMin)||"-",pt=((ve=t("#bp_FiTOMax"))==null?void 0:ve.value)||(v==null?void 0:v.FiTOMax)||"-",jt=((_e=t("#bp_FiTOAverage"))==null?void 0:_e.value)||(v==null?void 0:v.FiTOAverage)||"-",Vt=(((be=t("#bp_FiTO_Result"))==null?void 0:be.value)||(v==null?void 0:v.FiTO_Result)||"-").toUpperCase(),G={},K={},Z={};let ut=0;for(let f=1;f<=35;f++){const m=((he=t("#bp_UTFpoint"+f))==null?void 0:he.value)||(v==null?void 0:v["UTFpoint"+f])||"",_=((ge=t("#bp_FTOpoint"+f))==null?void 0:ge.value)||(v==null?void 0:v["FTOpoint"+f])||"";G[f]=m,K[f]=_,(parseFloat(m)>0||parseFloat(_)>0)&&(ut=f),f<=13&&(Z[f]=((Te=t("#bp_FiTO"+f))==null?void 0:Te.value)||(v==null?void 0:v["FiTO"+f])||"")}const it=await Ua();let Y=parseInt((v==null?void 0:v.MaxPoints)||ut||27);(!it||!it[Y])&&(Y=[26,27,28,29,30,31,32,33,34].reduce((m,_)=>Math.abs(_-Y)<Math.abs(m-Y)?_:m,27));let F=it?it[Y]:null,mt=[],z=null;if(F)try{const f=await P("dtr/layout?slug=bonding&variant="+encodeURIComponent(Y)),m=f&&f.layout;m&&(F=JSON.parse(JSON.stringify(F)),m.image&&Object.assign(F.image,m.image),["utf","fto"].forEach(_=>{Object.keys(m[_]||{}).forEach(g=>{m[_][g].hidden?delete F[_][g]:F[_][g]=m[_][g]})}),mt=m.extras||[],(m.vector||m.start)&&(z=m),m.vector&&(F.image.file=m.imageFile||"blank.png"))}catch{}const q=(f,m,_=!1)=>{const g=qe(parseFloat(f),_?we:m);return g?g==="FAIL"?"color:#dc2626;font-weight:800;":g==="TOLERABLE"?"color:#d97706;font-weight:800;":"color:#1d4ed8;font-weight:800;":"color:#94a3b8"};let W="";if(F){W+=`
        <img src="${X}assets/images/bonding_sketches/${F.image.file}" 
             class="vf-shoe-img" 
             style="left:${F.image.pctLeft}%; top:${F.image.pctTop}%; width:${F.image.pctWidth}%; height:${F.image.pctHeight}%;" 
             alt="${c} Shoe Sketch">
      `;for(let f=1;f<=F.pointsCount;f++){const m=F.utf[f];if(m){const g=G[f],h=parseFloat(g),L=Number.isFinite(h)&&h>0,O=q(g,n,!1);W+=`
            <div class="vf-point-box vf-point-utf" 
                 style="left:${m.pctLeft}%; top:${m.pctTop}%; width:${m.pctWidth}%; height:${m.pctHeight}%; ${O}" 
                 title="UTF Point ${f}: ${L?h.toFixed(2):"-"}">
              ${L?h.toFixed(2):""}
            </div>
          `}const _=F.fto[f];if(_){const g=K[f],h=parseFloat(g),L=Number.isFinite(h)&&h>0,O=q(g,n,!1);W+=`
            <div class="vf-point-box vf-point-fto" 
                 style="left:${_.pctLeft}%; top:${_.pctTop}%; width:${_.pctWidth}%; height:${_.pctHeight}%; ${O}" 
                 title="FTO Point ${f}: ${L?h.toFixed(2):"-"}">
              ${L?h.toFixed(2):""}
            </div>
          `}}if(z){let f='<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none">',m="";if(z.vector&&["utf","fto"].forEach(_=>Object.keys(F[_]).forEach(g=>{const h=F[_][g],L=((z.dots||{})[_]||{})[g];if(!L)return;const O=h.pctLeft+h.pctWidth,B=h.pctTop+h.pctHeight/2,C=L.x<h.pctLeft?h.pctLeft:L.x>O?O:h.pctLeft+h.pctWidth/2,Rt=C===h.pctLeft||C===O?B:L.y<h.pctTop?h.pctTop:h.pctTop+h.pctHeight,At=_==="utf"?"#2563eb":"#7c3aed";f+=`<line x1="${C}" y1="${Rt}" x2="${L.x}" y2="${L.y}" stroke="${At}" stroke-width="1.2" vector-effect="non-scaling-stroke"/>`,m+=`<div style="position:absolute;left:${L.x}%;top:${L.y}%;width:7px;height:7px;margin:-3.5px 0 0 -3.5px;border-radius:50%;background:${At}"></div>`})),z.start){const _=z.start;m+=`<div style="position:absolute;left:${_.x}%;top:${_.y}%;transform:translate(-50%,-50%);display:flex;align-items:center;gap:3px;white-space:nowrap;color:#b91c1c;font:800 9px sans-serif"><i style="display:block;width:9px;height:9px;background:#dc2626;transform:rotate(45deg)"></i>${i(_.label||"START")}</div>`}W+=f+"</svg>"+m}mt.forEach(f=>{var L;const m=`left:${f.pctLeft}%; top:${f.pctTop}%; width:${f.pctWidth}%; height:${f.pctHeight}%;`,_=`color:${i(f.color||"#111")}; font-size:${Number(f.size)||12}px; font-weight:${f.bold?700:400};`;let g="";if(f.type==="text")g=i(f.text);else if(f.type==="field")g=i(((L=t("#bf_"+f.field))==null?void 0:L.value)||"");else if(f.type==="image"&&f.src)g=`<img src="${X}${i(f.src)}" style="max-width:100%;max-height:100%" alt="">`;else if(f.type==="chart"){let O=0;for(let C=1;C<=F.pointsCount;C++)O=Math.max(O,parseFloat(G[C])||0);let B="";for(let C=1;C<=F.pointsCount;C++){const Rt=parseFloat(G[C])||0;B+=`<i style="flex:1;display:block;min-height:1px;height:${O?Rt/O*100:0}%;background:${Rt<2?"#dc2626":Rt<3?"#d97706":"#1d4ed8"}"></i>`}g=`<div style="display:flex;align-items:flex-end;gap:1px;width:100%;height:100%">${B}</div>`}const h=f.type==="line"?`background:${i(f.color||"#111")};`:"";W+=`<div style="position:absolute;display:flex;align-items:center;overflow:hidden;${m}${_}${h}">${g}</div>`})}else W='<div style="padding:20px;text-align:center;color:#64748b">Shoe sketch unavailable</div>';const vt=`
      <img src="${X}assets/images/bonding_sketches/blank.png" 
           class="vf-shoe-img" 
           style="left:12%; top:8%; width:76%; height:84%; opacity:0.85;" 
           alt="Unmeasured Shoe Contour">
    `;let ct="";for(let f=1;f<=13;f++){const m=Z[f],_=parseFloat(m),g=Number.isFinite(_)&&_>0,h=q(m,1,!0);ct+=`
        <div class="vf-center-item" style="${h}">
          ${g?_.toFixed(2):"&nbsp;"}
        </div>
      `}const Pt=o?W:vt,Nt=o?vt:W,Wt=o?ct:"",It=o?"":ct,dt=f=>f==="PASS"?{pass:"PASS",fail:"",passCls:"vf-rate-pass",failCls:""}:f==="TOLERABLE"?{pass:"TOLE",fail:"",passCls:"vf-rate-tol",failCls:""}:f==="FAIL"||f==="FAILED"?{pass:"",fail:"FAIL",passCls:"",failCls:"vf-rate-fail"}:{pass:"-",fail:"",passCls:"",failCls:""},tt=dt(Ht),et=dt(kt),at=dt(Vt);let _t="";for(let f=1;f<=(F?F.pointsCount:27);f++){const m=G[f]?parseFloat(G[f]).toFixed(2):"-",_=K[f]?parseFloat(K[f]).toFixed(2):"-",g=f<=13&&Z[f]?parseFloat(Z[f]).toFixed(2):"-";_t+=`
        <tr>
          <td><b>Point ${f}</b></td>
          <td style="${q(G[f],n,!1)}">${m}</td>
          <td style="${q(K[f],n,!1)}">${_}</td>
          <td style="${f<=13?q(Z[f],1,!0):"color:#94a3b8"}">${g}</td>
        </tr>
      `}const Ut=`
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
              <span class="vf-val-underlined">${i(s)}</span>
            </div>
            <div class="vf-labref-row">
              <span>Lab Ref.No:</span>
              <span class="vf-labref-val">${i(a)}</span>
            </div>
            <div class="vf-test-categories">
              <div>
                <span class="vf-radio ${k==="Prod. Shoes"||!k?"checked":""}"></span> Pro. Shoes 量产鞋
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${k==="Pro. Re-test"?"checked":""}"></span> Pro. Re-test 量产重测
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${k==="NewDevelopment"?"checked":""}"></span> NewDevelopment 开发
              </div>
              <div class="vf-tech-subradios">
                <span>○ Tech 1</span> <span>○ Tech 2</span> <span>○ Tech 3</span> <span>○ Tech 4</span>
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${k==="Sales Sample"?"checked":""}"></span> Sales Sample 销样
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${k==="Trial experiment"?"checked":""}"></span> 试胶试验
              </div>
            </div>
          </div>

          <div class="rpt-sub-right">
            <div class="vf-stamp-area">
              <span class="vf-stamp ${nt}">${i(V)}</span>
            </div>
            <table class="vf-dates-table">
              <tr><td>Received Date 收到日期:</td><td class="vf-date-val">${i(D)}</td></tr>
              <tr><td>Tested Date 测试日期:</td><td class="vf-date-val">${i(H)}</td></tr>
              <tr><td>Finished Date 完成日期:</td><td class="vf-date-val">${i(J)}</td></tr>
              <tr><td>Technician 技术员:</td><td class="vf-date-val">${i(ot)}</td></tr>
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
            <td class="vf-val">${i(y)}</td>
            <td class="vf-lbl">Shipped Date (出货日期):</td>
            <td class="vf-val">${i(R)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Model/Style/PGSC (英文鞋型):</td>
            <td class="vf-val">${i(r)}</td>
            <td class="vf-lbl">Color (颜色):</td>
            <td class="vf-val">${i(l)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Production Date (生产日期):</td>
            <td class="vf-val">${i(M!=="-"?M:H)}</td>
            <td class="vf-lbl">PO/Lot No (订单号码):</td>
            <td class="vf-val">${i(b)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">PO Quantity (订单数量):</td>
            <td class="vf-val">${i(p)}</td>
            <td class="vf-lbl">Send by (送达):</td>
            <td class="vf-val">${i(S)}</td>
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
              <td class="font-bold text-blue">${A!=="-"?Number(A).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${j!=="-"?Number(j).toFixed(2):"-"}</td>
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
              <td class="font-bold text-blue">${n}<br><span class="vf-tol">(≥ ${Re(n)})</span></td>
              <td class="font-bold text-blue">${Mt!=="-"?Number(Mt).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${Ot!=="-"?Number(Ot).toFixed(2):"-"}</td>
              <td><span class="${et.passCls}">${et.pass}</span></td>
              <td><span class="${et.failCls}">${et.fail}</span></td>
            </tr>
            <tr>
              <td class="text-left font-bold">Center : IS/OS<br>(中插 / 大底)</td>
              <td>VF-F01<br><span style="font-size:9.5px">(2019/05)</span></td>
              <td class="font-bold text-blue">${we}<br><span class="vf-tol">(≥ ${Re(we)})</span></td>
              <td class="font-bold text-blue">${Dt!=="-"?Number(Dt).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${pt!=="-"?Number(pt).toFixed(2):"-"}</td>
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
            ${Pt}
          </div>

          <!-- Center Boxes -->
          <div class="vf-center-card">
            <div class="vf-center-cols">
              <div class="vf-center-box" style="${o?"border-color:#1d4ed8;background:#f8fafc":"border-color:#cbd5e1"}">
                ${Wt}
              </div>
              <div class="vf-center-box" style="${o?"border-color:#cbd5e1":"border-color:#1d4ed8;background:#f8fafc"}">
                ${It}
              </div>
            </div>
          </div>

          <!-- Right Shoe Card -->
          <div class="vf-shoe-card">
            ${Nt}
          </div>
        </div>

        <!-- Optional Collapsible Data Table -->
        <details class="no-print" style="margin-top:8px;font-size:11px;border:1px solid #cbd5e1;border-radius:4px;padding:6px 10px;background:#f8fafc">
          <summary style="cursor:pointer;font-weight:700;color:#334155">
            <svg class="ic" aria-hidden="true"><use href="#i-chart"/></svg> View Tabular Spot Values (${F?F.pointsCount:27} Points)
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
              ${i(x||"Tested in accordance with VF Laboratory Standards.")}
            </div>
          </div>
          <div>
            Reviewed by 审查:
            <div class="vf-sign-line">
              <span>${i(ft)}</span>
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
    `;t("#dtr-print-modal-title").textContent=`Whole Shoe Bonding Test Report — ${a} (${c})`,t("#dtr-print-sheet-container").innerHTML=Ut,(ye=t("#dtr-print-modal-backdrop"))==null||ye.classList.remove("hidden")},Ie=e=>{const a=748.3464566929134,s=285/25.4*96,r=document.createElement("div");r.className="dtr-fit-probe",r.style.cssText="position:absolute;left:-99999px;top:0;visibility:hidden;",document.body.appendChild(r);let l=1;for(let c=0;c<4;c++){r.style.width=a/l+"px",r.innerHTML=e;const o=r.scrollHeight*l,d=Math.min(1,l*(s/Math.max(o,1))*.985);if(Math.abs(d-l)<.005){l=d;break}l=d}return r.remove(),Math.max(.4,Math.round(l*1e3)/1e3)};(wa=t("#btn-dtr-print-preview"))==null||wa.addEventListener("click",Ia),(Ra=t("#btn-bond-print-preview"))==null||Ra.addEventListener("click",Ha),(Aa=t("#btn-dtr-close-print-modal"))==null||Aa.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(Ea=t("#btn-dtr-cancel-print"))==null||Ea.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(xa=t("#btn-dtr-download-pdf"))==null||xa.addEventListener("click",async()=>{var r,l,c;const e=t("#btn-dtr-download-pdf"),a=t("#dtr-print-sheet-container");if(!a||!e)return;const s=e.textContent;e.disabled=!0,e.textContent="Creating PDF...";try{const o=await fetch(X+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((r=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:r.content)||""},body:JSON.stringify({scale:Ie(a.innerHTML),html:a.innerHTML,name:(((l=t("#dtr-print-modal-title"))==null?void 0:l.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_")})});if(!o.ok)throw new Error((await o.text()).slice(0,200)||"PDF failed");const d=await o.blob(),n=document.createElement("a");n.href=URL.createObjectURL(d),n.download=((((c=t("#dtr-print-modal-title"))==null?void 0:c.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_").trim()||"Report")+".pdf",document.body.appendChild(n),n.click(),n.remove(),setTimeout(()=>URL.revokeObjectURL(n.href),4e3)}catch(o){nxToast("Could not create the PDF: "+o.message,"error",7e3)}e.disabled=!1,e.textContent=s}),(Ma=t("#btn-dtr-do-print"))==null||Ma.addEventListener("click",()=>{const e=t("#dtr-print-sheet-container");if(!e){window.print();return}const a=document.createElement("div");a.id="dtr-print-root",a.innerHTML=e.innerHTML,a.style.zoom=String(Ie(e.innerHTML)),document.body.appendChild(a),document.body.classList.add("dtr-printing");const s=()=>{document.body.classList.remove("dtr-printing"),a.remove(),window.removeEventListener("afterprint",s)};window.addEventListener("afterprint",s),window.print()});const Va=async()=>{var e,a;try{const s=await P("dtr/layout?slug=bonding&variant=form"),r=s&&s.layout,l=t("#dtr-bonding-form");if(!r||!l)return;const c=d=>{var n;return(n=l.querySelector(`[name="${d}"]`))==null?void 0:n.closest(".dtr-field")};(r.customFields||[]).forEach(d=>{var p,u;if(l.querySelector(`[name="${d.key}"]`))return;const n=(u=(p=t("#bf_LabRefNo"))==null?void 0:p.closest(".dtr-field"))==null?void 0:u.parentElement;if(!n)return;const b=document.createElement("div");b.className="dtr-field",b.innerHTML=`<label>${i(d.label)}</label><input type="text" name="${i(d.key)}" id="bf_${i(d.key)}">`,n.appendChild(b)}),Object.entries(r.labels||{}).forEach(([d,n])=>{const b=c(d),p=b&&b.querySelector("label");p&&n&&(p.textContent=n)});const o=(a=(e=t("#bf_LabRefNo"))==null?void 0:e.closest(".dtr-field"))==null?void 0:a.parentElement;o&&(r.order||[]).length&&(r.order.forEach(d=>{const n=c(d);n&&n.parentElement===o&&o.appendChild(n)}),(r.customFields||[]).forEach(d=>{const n=c(d.key);n&&o.appendChild(n)})),(r.hiddenFields||[]).forEach(d=>{const n=c(d);n&&(n.style.display="none")})}catch{}};Qe(),Le("fabric"),$e("fabric",0),Va().then(()=>{Pe(),Ne(0)}),We(),(()=>{const e=new URLSearchParams(location.search),[a,s]=String(e.get("open")||"").split(":");!a||!/^[a-z]+$/.test(a)||!/^\d+$/.test(s||"")||setTimeout(async()=>{var c,o;const r=t("#dtr-tracker-tbody")||document.body,l=document.createElement("button");if(l.type="button",l.hidden=!0,l.dataset.openRecord=a,l.dataset.sourceId=s,r.appendChild(l),l.click(),l.remove(),e.get("preview")==="1"&&(await new Promise(d=>setTimeout(d,2200)),(c=t(a==="bonding"?"#btn-bond-print-preview":"#btn-dtr-print-preview"))==null||c.click()),e.get("export")==="1"&&window.parent!==window){const d=n=>window.parent.postMessage(Object.assign({type:"NXL_REPORT_PDF"},n),location.origin);try{await new Promise(y=>setTimeout(y,800));const n=t("#dtr-print-sheet-container");if(!n||!n.innerHTML.trim())throw new Error("The report sheet could not be prepared.");const b=await fetch(X+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((o=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:o.content)||""},body:JSON.stringify({scale:Ie(n.innerHTML),html:n.innerHTML,name:"Report"})});if(!b.ok)throw new Error((await b.text()).slice(0,200)||"PDF failed");const p=new Uint8Array(await b.arrayBuffer());let u="";for(let y=0;y<p.length;y+=32768)u+=String.fromCharCode.apply(null,p.subarray(y,y+32768));d({ok:!0,b64:btoa(u)})}catch(n){d({ok:!1,error:n.message})}}if(e.get("export")==="html"&&window.parent!==window){const d=n=>window.parent.postMessage(Object.assign({type:"NXL_REPORT_HTML"},n),location.origin);try{await new Promise(b=>setTimeout(b,1200));const n=t("#dtr-print-sheet-container");if(!n||!n.innerHTML.trim())throw new Error("The report sheet could not be prepared.");d({ok:!0,html:n.innerHTML})}catch(n){d({ok:!1,error:n.message})}}},900)})()})();
