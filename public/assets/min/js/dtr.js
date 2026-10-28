(()=>{"use strict";var Ge,ze,qe,Qe,Xe,Je,Ye,Ke,Ze,ta,ea,aa,sa,la,oa,ra,na,ia,ca,da,fa,pa,ua,ma,va,_a,ba,ha,ga,Ta,ya,Fa,Sa,La,$a,Ca,wa,Ra;const t=(e,a=document)=>a.querySelector(e),R=(e,a=document)=>[...a.querySelectorAll(e)],De=t('meta[name="base-url"]'),I=De?De.content.replace(/\/+$/,"")+"/":location.origin+"/";let lt="fabric",Pe={},m={};const $=(e,a=!1)=>{const s=t("#dtr-toast");s&&(s.textContent=e,s.style.background=a?"#dc2626":"#0f172a",s.classList.remove("hidden"),clearTimeout(s._timer),s._timer=setTimeout(()=>s.classList.add("hidden"),4200))},c=e=>String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"),O=async(e,a={})=>(await fetch(I+e.replace(/^\/+/,""),a)).json(),h=(e,a=2)=>{const s=Number(e);if(!Number.isFinite(s))return 0;const o=Math.pow(10,a);return Math.round((s+Number.EPSILON)*o)/o},x=e=>{if(!e)return;const a=String(e.value||"").trim().toUpperCase();e.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),a==="PASS"?e.classList.add("dtr-rate-pass"):a==="TOLERABLE"?e.classList.add("dtr-rate-warn"):(a==="FAIL"||a==="ERROR")&&e.classList.add("dtr-rate-fail")},ot=e=>{R("[data-dtr-tab]").forEach(a=>{a.classList.toggle("active",a.dataset.dtrTab===e)}),["tracker","material","bonding","calculators"].forEach(a=>{var s;(s=t(`#dtr-panel-${a}`))==null||s.classList.toggle("hidden",a!==e)})};R("[data-dtr-tab]").forEach(e=>{e.addEventListener("click",()=>ot(e.dataset.dtrTab))}),R("[data-goto-calc]").forEach(e=>{e.addEventListener("click",()=>{ot("calculators");const a=t(`#calc-card-${e.dataset.gotoCalc}`);a&&a.scrollIntoView({behavior:"smooth",block:"center"})})});const Ie=async(e="all",a="")=>{const s=t("#dtr-tracker-tbody");try{s&&window.nxState&&nxState(s,"loading","Loading lab reports…",16);const o=await O(`dtr/records?slug=${encodeURIComponent(e)}&q=${encodeURIComponent(a)}`);if(!s||!o.rows)return;if(!o.rows.length){nxState(s,"empty","No lab reports match your search. Clear the filters or try another Lab Ref No.",16);return}s.innerHTML=o.rows.map(l=>{const i=String(l.overall_result||"PASS").toUpperCase(),r=i==="FAIL"?"b-err":i==="TOLERABLE"?"b-warn":"b-ok";return`
          <tr>
            <td style="white-space:nowrap">
              <button type="button" class="dtr-btn-xs dtr-btn-primary" data-open-record="${c(l.form_slug)}" data-source-id="${c(l.source_id)}" data-labref="${c(l.lab_ref_no)}">
                Open Form
              </button>
            </td>
            <td class="mono"><b>${c(l.lab_ref_no)}</b></td>
            <td class="mono">${c(l.tr_no||"-")}</td>
            <td><span class="dtr-tag">${c(l.form_category)}</span></td>
            <td>${c(l.material_type||"-")}</td>
            <td class="mono">${c(l.material_code||"-")}</td>
            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${c(l.material_description||"")}">${c(l.material_description||"-")}</td>
            <td>${c(l.supplier||"-")}</td>
            <td class="mono">${c(l.model_style||"-")}</td>
            <td>${c(l.color||"-")}</td>
            <td class="mono">${c(l.po_lot_no||"-")}</td>
            <td class="mono">${c(l.mtl_no||"-")}</td>
            <td>${c(l.tested_date||l.received_date||"-")}</td>
            <td>${c(l.technician||"-")}</td>
            <td>${c(l.status||"Complete")}</td>
            <td><span class="badge ${r}">${c(i)}</span></td>
          </tr>
        `}).join("")}catch(o){console.error(o),s&&window.nxState&&nxState(s,"error","The list could not be loaded. Check your connection and try again.",16)}};(Ge=t("#dtr-tracker-pills"))==null||Ge.addEventListener("click",e=>{var o;const a=e.target.closest("[data-slug]");if(!a)return;R("#dtr-tracker-pills .dtr-pill").forEach(l=>l.classList.remove("active")),a.classList.add("active");const s=((o=t("#dtr-tracker-search"))==null?void 0:o.value.trim())||"";Ie(a.dataset.slug,s)}),(ze=t("#dtr-tracker-btn-search"))==null||ze.addEventListener("click",()=>{var o;const e=t("#dtr-tracker-pills .dtr-pill.active"),a=e?e.dataset.slug:"all",s=((o=t("#dtr-tracker-search"))==null?void 0:o.value.trim())||"";Ie(a,s)}),(qe=t("#dtr-tracker-search"))==null||qe.addEventListener("keydown",e=>{var a;e.key==="Enter"&&(e.preventDefault(),(a=t("#dtr-tracker-btn-search"))==null||a.click())}),(Qe=t("#dtr-tracker-tbody"))==null||Qe.addEventListener("click",async e=>{const a=e.target.closest("[data-open-record]");if(!a)return;const s=a.dataset.openRecord,o=a.dataset.sourceId;if(s==="bonding")ot("bonding"),await Oe(o),await ke(o);else{ot("material");const l=t("#dtr-mat-form-slug");l&&(l.value=s),lt=s,await ye(s,o),await Fe(s,o)}});const Ae=(e=!0)=>{var r,f;const a=[];for(let n=1;n<=6;n++){const v=parseFloat(((r=t(`#mf_Thickness${n}`))==null?void 0:r.value)??"");Number.isFinite(v)&&v>0&&a.push(v)}if(!a.length)return;const s=Math.min(...a),o=Math.max(...a),l=h(a.reduce((n,v)=>n+v,0)/a.length,2);t("#mf_TMin")&&(t("#mf_TMin").value=s),t("#mf_TMax")&&(t("#mf_TMax").value=o),t("#mf_TAve")&&(t("#mf_TAve").value=l);const i=parseFloat(((f=t("#mf_T_S"))==null?void 0:f.value)??"");Number.isFinite(i)&&i>0&&t("#mf_TRating")&&(t("#mf_TRating").value=l>=i?"PASS":"FAIL",x(t("#mf_TRating"))),e&&Te()},rt=()=>{var r,f,n,v;const e=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_Weight${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);if(e.length>0&&t("#mf_WAve")){const p=h(e.reduce((C,w)=>C+w,0)/e.length,2);t("#mf_WAve").value=p;const u=parseFloat(((r=t("#mf_Weight_S"))==null?void 0:r.value)??"");Number.isFinite(u)&&u>0&&t("#mf_WRating")&&(t("#mf_WRating").value=p>=u?"PASS":"FAIL",x(t("#mf_WRating")))}const a=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_SG${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);if(a.length>0&&t("#mf_SG_Ave")){const p=h(a.reduce((C,w)=>C+w,0)/a.length,2);t("#mf_SG_Ave").value=p;const u=parseFloat(((f=t("#mf_SG_Std"))==null?void 0:f.value)??"");Number.isFinite(u)&&u>0&&t("#mf_SG_Rate")&&(t("#mf_SG_Rate").value=Math.abs(p-u)<=.02?"PASS":"FAIL",x(t("#mf_SG_Rate")))}const s=[1,2,3].map(p=>{var u;return parseFloat(((u=t(`#mf_DIN_${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);s.length>0&&t("#mf_DIN_Rate")&&(t("#mf_DIN_Rate").value=s.some(p=>p>150)?"FAIL":"PASS",x(t("#mf_DIN_Rate")));const o=[1,2].map(p=>{var u;return parseFloat(((u=t(`#mf_RossFlex_${p}`))==null?void 0:u.value)??"")}).filter(p=>Number.isFinite(p)&&p>0);o.length>0&&t("#mf_RossFlex_Rate")&&(t("#mf_RossFlex_Rate").value=o.some(p=>p>50)?"FAIL":"PASS",x(t("#mf_RossFlex_Rate")));const l=parseFloat(((n=t("#mf_ShrinkageL"))==null?void 0:n.value)??""),i=parseFloat(((v=t("#mf_Shrinkage_S"))==null?void 0:v.value)??"");Number.isFinite(l)&&Number.isFinite(i)&&i>0&&t("#mf_ShrinkageLRating")&&(t("#mf_ShrinkageLRating").value=Math.abs(l)<=Math.abs(i)?"PASS":"FAIL",x(t("#mf_ShrinkageLRating")))},Ue={TensileL:{inputs:["mf_TensileL1","mf_TensileL2","mf_TensileL3"],ave:"mf_TensileLAve",std:"mf_Tensile_L",rate:"mf_TensileLRating",dec:2},TensileW:{inputs:["mf_TensileW1","mf_TensileW2","mf_TensileW3"],ave:"mf_TensileWAve",std:"mf_Tensile_W",rate:"mf_TensileWRating",dec:2},ElongationL:{inputs:["mf_ElongationL1","mf_ElongationL2","mf_ElongationL3"],ave:"mf_ElongationLAve",std:"mf_Elongation_L",rate:"mf_ElongationLRating",dec:2},ElongationW:{inputs:["mf_ElongationW1","mf_ElongationW2","mf_ElongationW3"],ave:"mf_ElongationWAve",std:"mf_Elongation_W",rate:"mf_ElongationWRating",dec:2},TrouserTearL:{inputs:["mf_TrouserTearL1","mf_TrouserTearL2","mf_TrouserTearL3"],ave:"mf_TrouserTearLAve",std:"mf_TrouserTear_L",rate:"mf_TrouserTearLRating",dec:2},TrouserTearW:{inputs:["mf_TrouserTearW1","mf_TrouserTearW2","mf_TrouserTearW3"],ave:"mf_TrouserTearWAve",std:"mf_TrouserTear_W",rate:"mf_TrouserTearWRating",dec:2},StitchTearL:{inputs:["mf_StitchTearL1","mf_StitchTearL2","mf_StitchTearL3"],ave:"mf_StitchTearLAve",std:"mf_StitchTear_L",rate:"mf_StitchTearLRating",dec:2},StitchTearW:{inputs:["mf_StitchTearW1","mf_StitchTearW2","mf_StitchTearW3"],ave:"mf_StitchTearWAve",std:"mf_StitchTear_W",rate:"mf_StitchTearWRating",dec:2},MullenBurst:{inputs:["mf_MullenBurst1","mf_MullenBurst2","mf_MullenBurst3"],ave:"mf_Text3977",std:"mf_MullenBurst",rate:"mf_MullenBurstRating",dec:4},DieC:{inputs:["mf_DieC_1","mf_DieC_2","mf_DieC_3"],ave:"mf_DieC_Ave",std:"mf_DieC_Std",rate:"mf_DieC_Rate",dec:2}},Ve=e=>{const a=Ue[e];if(!a)return;const s=a.inputs.map(n=>{var v;return parseFloat(((v=t(`#${n}`))==null?void 0:v.value)??"")}).filter(n=>Number.isFinite(n)),o=t(`#${a.ave}`),l=t(`#${a.std}`),i=t(`#${a.rate}`);if(!s.length)return;const r=h(s.reduce((n,v)=>n+v,0)/s.length,a.dec);o&&(o.value=r);const f=parseFloat((l==null?void 0:l.value)??"");Number.isFinite(f)&&f>0&&i&&(i.value=r>=f?"PASS":"FAIL",x(i))},ge=()=>{Object.keys(Ue).forEach(Ve)},xe={CTP:{fibers:["mf_CtPWool","mf_CtPAcrylic","mf_CtPPolyester","mf_CtPNylon","mf_CtPCotton","mf_CtPAcetate"],std:"mf_CTP",rate:"mf_CTPRating"},CTPB:{fibers:["mf_CtPBWool","mf_CtPBAcrylic","mf_CtPBPolyester","mf_CtPBNylon","mf_CtPBCotton","mf_CtPBAcetate"],std:"mf_CtPB_S",rate:"mf_CTPB_Rating"},CTW:{fibers:["mf_CtWWool","mf_CtWAcrylic","mf_CtWPolyester","mf_CtWNylon","mf_CtWCotton","mf_CtWAcetate"],std:"mf_CTW",rate:"mf_CTWRating"},CTWB:{fibers:["mf_CtWBackSideWool","mf_CtWBackSideAcrylic","mf_CtWBackSidePolyester","mf_CtWBackSideNylon","mf_CtWBackSideCotton","mf_CtWBackSideAcetate"],std:"mf_CtWB_S",rate:"mf_CtWB_Rating"}},Ee=e=>{var i;const a=xe[e];if(!a)return;const s=parseFloat(((i=t(`#${a.std}`))==null?void 0:i.value)??""),o=t(`#${a.rate}`);if(!Number.isFinite(s)||s<=0||!o)return;const l=a.fibers.map(r=>{var f;return parseFloat(((f=t(`#${r}`))==null?void 0:f.value)??"")});l.some(r=>!Number.isFinite(r))||(o.value=l.some(r=>r<s)?"FAIL":"PASS",x(o))},Me=()=>{var g,L,M,P,A,N,W,U,J,nt,V,Y,z,K,ft;const e=parseFloat(((g=t("#mf_CtCDry"))==null?void 0:g.value)??""),a=parseFloat(((L=t("#mf_CtCDry_S"))==null?void 0:L.value)??"");Number.isFinite(e)&&Number.isFinite(a)&&a>0&&t("#mf_CTCDRYRating")&&(t("#mf_CTCDRYRating").value=e>=a?"PASS":"FAIL",x(t("#mf_CTCDRYRating")));const s=parseFloat(((M=t("#mf_CtCWet"))==null?void 0:M.value)??""),o=parseFloat(((P=t("#mf_CtCWet_S"))==null?void 0:P.value)??"");Number.isFinite(s)&&Number.isFinite(o)&&o>0&&t("#mf_CTCWETRating")&&(t("#mf_CTCWETRating").value=s>=o?"PASS":"FAIL",x(t("#mf_CTCWETRating")));const l=parseFloat(((A=t("#mf_UV"))==null?void 0:A.value)??""),i=parseFloat(((N=t("#mf_UV_S"))==null?void 0:N.value)??"");Number.isFinite(l)&&Number.isFinite(i)&&i>0&&t("#mf_UVRating")&&(t("#mf_UVRating").value=l>=i?"PASS":"FAIL",x(t("#mf_UVRating")));const r=parseFloat(((W=t("#mf_HeatAging1"))==null?void 0:W.value)??""),f=parseFloat(((U=t("#mf_HeatAging2"))==null?void 0:U.value)??""),n=parseFloat(((J=t("#mf_HeatAging_S"))==null?void 0:J.value)??"");Number.isFinite(r)&&Number.isFinite(f)&&Number.isFinite(n)&&n>0&&t("#mf_HeatAgingRating")&&(t("#mf_HeatAgingRating").value=r<n||f<n?"FAIL":"PASS",x(t("#mf_HeatAgingRating")));const v=parseFloat(((nt=t("#mf_Hydrolysis1"))==null?void 0:nt.value)??""),p=parseFloat(((V=t("#mf_Hydrolysis2"))==null?void 0:V.value)??""),u=parseFloat(((Y=t("#mf_Hydrolysis_S"))==null?void 0:Y.value)??"");Number.isFinite(v)&&Number.isFinite(p)&&Number.isFinite(u)&&u>0&&t("#mf_HydrolysisRating")&&(t("#mf_HydrolysisRating").value=v<u||p<u?"FAIL":"PASS",x(t("#mf_HydrolysisRating")));const C=parseFloat(((z=t("#mf_PVC_1"))==null?void 0:z.value)??""),w=parseFloat(((K=t("#mf_PVC_2"))==null?void 0:K.value)??""),S=parseFloat(((ft=t("#mf_PVC_S"))==null?void 0:ft.value)??"");Number.isFinite(C)&&Number.isFinite(w)&&Number.isFinite(S)&&S>0&&t("#mf_PVC_Rating")&&(t("#mf_PVC_Rating").value=C<S||w<S?"FAIL":"PASS",x(t("#mf_PVC_Rating")))},k=()=>{var i;let e=!1,a=!1,s=!1;R("#dtr-material-form .dtr-rate-inp").forEach(r=>{x(r);const f=String(r.value||"").trim().toUpperCase();f==="FAIL"?a=!0:f==="TOLERABLE"?s=!0:f==="PASS"&&(e=!0)});let o="PASS";a?o="FAIL":s?o="TOLERABLE":e?o="PASS":(i=t("#mf_Remarks"))!=null&&i.value&&(o=String(t("#mf_Remarks").value).toUpperCase()),t("#mf_Remarks")&&(t("#mf_Remarks").value=o);const l=t("#dtr-stamp-badge");l&&(l.textContent=o,l.className="dtr-stamp "+(o==="FAIL"?"dtr-stamp-fail":o==="TOLERABLE"?"dtr-stamp-warn":"dtr-stamp-pass"))},Te=async()=>{var s,o;const e=((s=t("#mf_MaterialType"))==null?void 0:s.value.trim())||"",a=parseFloat(((o=t("#mf_TAve"))==null?void 0:o.value)??"0")||0;if(e)try{const l=await O(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`);l&&l.mapped&&Object.keys(l.mapped).forEach(i=>{const r=l.mapped[i],f=t(`#mf_${i}`)||t(`[name="${i}"]`,t("#dtr-material-form"));f&&(f.value=r)}),rt(),ge(),Object.keys(xe).forEach(Ee),Me(),k()}catch(l){console.warn("[DTR] applyStandardValuesSmart error:",l)}},xa=async()=>{Ae(!1),await Te(),$("✓ Executed ApplyStandardValuesSmart + Thickness + Directional Averages + 6-Point Colorfastness + EvaluateRatings.")},ye=async(e,a=null)=>{const s=t("#dtr-mat-record-select");try{s&&(s.innerHTML='<option value="">Loading records…</option>');const o=await O(`dtr/records?slug=${encodeURIComponent(e)}`);if(!s||!o.rows)return;if(!o.rows.length){s.innerHTML='<option value="">No records yet - press “+ New Record”</option>';return}s.innerHTML=o.rows.map(l=>`
        <option value="${c(l.source_id)}" ${String(l.source_id)===String(a)?"selected":""}>
          ${c(l.lab_ref_no)} · ${c(l.material_type||l.form_category)} · ${c(l.model_style||l.material_code||"")} [${c(l.overall_result||"Pass")}]
        </option>
      `).join("")}catch(o){console.error(o),s&&(s.innerHTML='<option value="">Could not load records - reload the page</option>'),window.nxToast&&nxToast("The record list could not be loaded.","error")}},Ea=e=>{const a=t("#dtr-raw-cols-container"),s=t("#dtr-raw-col-count");if(!a)return;const o=Object.keys(e||{});s&&(s.textContent=o.length),a.innerHTML=o.map(l=>`
      <div class="dtr-field">
        <label title="${c(l)}"><code>${c(l)}</code></label>
        <input type="text" data-raw-col="${c(l)}" value="${c(e[l]??"")}" ${l==="ID"?'readonly class="dtr-ro"':""}>
      </div>
    `).join("")},He=(e,a={})=>{Pe=e||{},t("#dtr-active-form-label")&&a.label&&(t("#dtr-active-form-label").textContent=`${a.label}`),t("#dtr-active-labref-title")&&(t("#dtr-active-labref-title").textContent=`${e.LabRefNo||"New Report"} — ${e["Material Description"]||e.MaterialDescription||e.MaterialType||""}`),R("#dtr-material-form input, #dtr-material-form select").forEach(s=>{s.name&&(s.value="")}),Object.keys(e).forEach(s=>{const o=e[s]??"",l=t(`#mf_${s.replace(/[^a-zA-Z0-9_]/g,"")}`);l&&(l.value=o);const i=t(`[name="${CSS.escape(s)}"]`,t("#dtr-material-form"));i&&(i.value=o)}),t("#mf_MaterialDescription")&&!t("#mf_MaterialDescription").value&&(t("#mf_MaterialDescription").value=e["Material Description"]||e.MaterialDescription||""),t("#mf_SupplierFactory")&&!t("#mf_SupplierFactory").value&&(t("#mf_SupplierFactory").value=e["Supplier/Factory"]||e.Supplier||e.SupplierFactory||""),t("#mf_POLotNo")&&!t("#mf_POLotNo").value&&(t("#mf_POLotNo").value=e["PO/Lot No"]||e.POLotNo||""),t("#mf_ModelNoStyle")&&!t("#mf_ModelNoStyle").value&&(t("#mf_ModelNoStyle").value=e["Model No/Style"]||e.ModelNoStyle||""),t("#mf_POQuantity")&&!t("#mf_POQuantity").value&&(t("#mf_POQuantity").value=e["PO Quantity"]||e.POQuantity||""),t("#mf_TestedBy")&&!t("#mf_TestedBy").value&&(t("#mf_TestedBy").value=e["Tested by"]||e.TestedBy||e.Technician||""),Ea(e),Ae(!1),rt(),ge(),Object.keys(xe).forEach(Ee),Me(),k()},Fe=async(e,a=0)=>{try{const s=await O(`dtr/record?slug=${encodeURIComponent(e)}&source_id=${encodeURIComponent(a)}`);s&&s.ok&&He(s.record||{},s.config||{})}catch(s){console.error(s)}};(Xe=t("#mf_LabRefNo"))==null||Xe.addEventListener("change",()=>{var s;const e=(((s=t("#mf_LabRefNo"))==null?void 0:s.value)||"").toUpperCase().trim(),a=t("#mf_MaterialType");!e||!a||(e.includes("FBU")||e.includes("FBL")?a.value||(a.value="Canvas"):e.includes("LEU")||e.includes("LEL")?a.value||(a.value="Suede"):e.includes("RB")?a.value="Rubber":e.includes("IB")?a.value="Inner Box":e.includes("OB")?a.value="Outer Box":e.includes("FML")?a.value="Sockliner - Latex":e.includes("FM")?a.value="PU Foam":e.includes("SKL")?a.value="Sockliner - EVA":e.includes("LC")?a.value="Shoe lace":e.includes("TH")?a.value="Thread":e.includes("EY")?a.value="Eyelet":e.includes("GR")?a.value="Grommet":e.includes("ZP")&&(a.value="Zipper"),Te())});const Se=async()=>{var o,l,i;const e=((o=t("#mf_MaterialCode"))==null?void 0:o.value.trim())||"",a=((l=t("#mf_POLotNo"))==null?void 0:l.value.trim())||"",s=((i=t("#mf_MTL"))==null?void 0:i.value.trim())||"";if(!e&&!a&&!s){$("Enter a Material Code, PO/Lot No, or MTL # first.",!0);return}try{const r=await O(`dtr/lookup?material_code=${encodeURIComponent(e)}&po_lot_no=${encodeURIComponent(a)}&mtl_no=${encodeURIComponent(s)}`);let f=[];if(r.material&&(t("#mf_SupplierFactory")&&(t("#mf_SupplierFactory").value=r.material.Supplier||""),t("#mf_MaterialDescription")&&(t("#mf_MaterialDescription").value=r.material.MaterialName||""),f.push(`MaterialList (${r.material.MaterialCode})`)),r.shoe&&(t("#mf_ModelNoStyle")&&(t("#mf_ModelNoStyle").value=r.shoe.StyleNo||""),t("#mf_Color")&&(t("#mf_Color").value=r.shoe.Colorway||""),t("#mf_MTL")&&r.shoe.MTL&&(t("#mf_MTL").value=r.shoe.MTL),f.push(`ShoeList (${r.shoe.StyleNo})`)),r.dsr&&r.dsr.length>0){const n=r.dsr[0];t("#mf_StyleNumber_DSR")&&(t("#mf_StyleNumber_DSR").value=n.StyleNumber||""),t("#mf_StyleDescription_DSR")&&(t("#mf_StyleDescription_DSR").value=n.StyleDescription||""),t("#mf_Colorway_DSR")&&(t("#mf_Colorway_DSR").value=n.Colorway||""),t("#mf_ComponentorLocation_DSR")&&(t("#mf_ComponentorLocation_DSR").value=n.ComponentorLocation||""),t("#mf_VFMaterialCode_DSR")&&(t("#mf_VFMaterialCode_DSR").value=n.VFMaterialCode||""),t("#mf_InternalName_DSR")&&(t("#mf_InternalName_DSR").value=n["Internal NameVans1"]||n.Material||""),t("#mf_ColorName_DSR")&&(t("#mf_ColorName_DSR").value=n.ColorName||n.Color||""),f.push(`tblDSR (${r.dsr.length} component rows)`)}f.length?$(`✓ Auto-Lookup populated from: ${f.join(" · ")}`):$("No exact match found in MaterialList / ShoeList / tblDSR.",!0)}catch(r){console.error(r)}};(Je=t("#btn-dtr-lookup-all"))==null||Je.addEventListener("click",Se),(Ye=t("#mf_MaterialCode"))==null||Ye.addEventListener("change",Se),(Ke=t("#mf_POLotNo"))==null||Ke.addEventListener("change",Se),(Ze=t("#mf_MTL"))==null||Ze.addEventListener("change",Se),(ta=t("#mf_MaterialType"))==null||ta.addEventListener("change",Te),R(".dtr-calc-thick").forEach(e=>{e.addEventListener("input",()=>Ae(!0))}),R(".dtr-calc-weight, .dtr-calc-sg, .dtr-calc-din, .dtr-calc-ross, .dtr-calc-shrink").forEach(e=>{e.addEventListener("input",()=>{rt(),k()})}),R(".dtr-dir-inp, .dtr-std-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset.dirGroup?Ve(e.dataset.dirGroup):ge(),k()})}),R(".dtr-6pt-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset["6pt"]&&Ee(e.dataset["6pt"]),k()})}),R(".dtr-crock-inp").forEach(e=>{e.addEventListener("input",()=>{Me(),k()})}),R(".dtr-rate-inp").forEach(e=>{e.addEventListener("change",k),e.addEventListener("input",k)}),(ea=t("#dtr-mat-form-slug"))==null||ea.addEventListener("change",async e=>{lt=e.target.value,await ye(lt),await Fe(lt,0)}),(aa=t("#dtr-mat-record-select"))==null||aa.addEventListener("change",async e=>{const a=e.target.value;a&&await Fe(lt,a)}),(sa=t("#btn-dtr-new"))==null||sa.addEventListener("click",()=>{const e=new Date().toISOString().slice(0,10);He({ID:"",LabRefNo:`DTP${e.replace(/-/g,"")}${lt.toUpperCase().slice(0,3)}-1`,TR:`TR${e.replace(/-/g,"")}001`,ReceivedDate:e,TestedDate:e,FinishedDate:e,Status:"Complete",Remarks:"PASS"}),$("Initialized new blank test report record.")}),(la=t("#btn-dtr-review"))==null||la.addEventListener("click",()=>{const e=prompt("Enter Reviewer Name:","Faye Ann Elaysa");if(!e)return;const a=t('[data-raw-col="AuthorizedBy"]');a&&(a.value=e);const s=t('[data-raw-col="AuthorizationStatus"]');s&&(s.value="Reviewed"),$(`✓ Record stamped Reviewed by ${e}`)}),(oa=t("#btn-dtr-refresh-calc"))==null||oa.addEventListener("click",xa),(ra=t("#btn-dtr-load-machine"))==null||ra.addEventListener("click",async()=>{var a;const e=((a=t("#mf_LabRefNo"))==null?void 0:a.value.trim())||"";if(!e){$("Enter a LabRefNo first before loading machine results.",!0);return}try{const s=await O("dtr/load-machine",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:lt,lab_ref_no:e})});s&&s.loadedFields&&(Object.keys(s.loadedFields).forEach(o=>{const l=s.loadedFields[o],i=t(`#mf_${o}`)||t(`[name="${CSS.escape(o)}"]`,t("#dtr-material-form"));i&&(i.value=l);const r=t(`[data-raw-col="${CSS.escape(o)}"]`);r&&(r.value=l)}),rt(),ge(),k(),t("#dtr-machine-status")&&(t("#dtr-machine-status").textContent=s.message||"Machine data loaded."),$(`${s.message}`))}catch(s){$("Machine loader error: "+(s.message||""),!0)}}),(na=t("#btn-dtr-save"))==null||na.addEventListener("click",async()=>{const e=Object.assign({},Pe);R("#dtr-raw-cols-container [data-raw-col]").forEach(a=>{e[a.dataset.rawCol]=a.value}),R("#dtr-material-form [name]").forEach(a=>{a.name&&(e[a.name]=a.value)});try{const a=await O("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:lt,fields:e})});a&&a.ok?(t("#mf_ID")&&(t("#mf_ID").value=a.recordId),$(`✓ ${a.message} (Ready in InterLink Sync ComboBox!)`),await ye(lt,a.recordId)):$(a.error||"Save failed.",!0)}catch{$("Failed to save record.",!0)}}),(ia=t("#btn-toggle-raw-cols"))==null||ia.addEventListener("click",()=>{var e;(e=t("#dtr-raw-cols-container"))==null||e.classList.toggle("hidden")});const Ma={TODDLER:14,KIDS:16,CHILDREN:24,ADULT:35},Le=1,$e=e=>Math.round(e*.8*100)/100,Be=(e,a)=>!Number.isFinite(e)||e<=0?null:e>=a?"PASS":e>=$e(a)?"TOLERABLE":"FAIL",wt=()=>{var p,u;const e=(((p=t("#bf_AgeCategory"))==null?void 0:p.value)||"ADULT").toUpperCase(),a=Ma[e]||35,s=parseFloat(((u=t("#bf_STD"))==null?void 0:u.value)??"2.5")||2.5;R("#dtr-bonding-points-tbody [data-spot-row]").forEach(C=>{const w=Number(C.dataset.spotRow);C.style.opacity=w<=a?"1":"0.45"});const o=(C,w,S)=>{const g=[];let L=!1,M=!1;for(let P=1;P<=w;P++){const A=t(`#bp_${C}${P}`);if(!A)continue;const N=parseFloat(A.value??"");if(A.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),Number.isFinite(N)&&N>0){g.push(N);const W=Be(N,S);W==="FAIL"?(L=!0,A.classList.add("dtr-rate-fail")):W==="TOLERABLE"?(M=!0,A.classList.add("dtr-rate-warn")):A.classList.add("dtr-rate-pass")}}return g.length?{min:h(Math.min(...g),2),max:h(Math.max(...g),2),ave:h(g.reduce((P,A)=>P+A,0)/g.length,2),res:L?"FAIL":M?"TOLERABLE":"PASS"}:{min:"",max:"",ave:"",res:""}},l=o("UTFpoint",a,s),i=o("FTOpoint",a,s),r=o("FiTO",13,Le);t("#bp_UTFMin")&&(t("#bp_UTFMin").value=l.min),t("#bp_UTFMax")&&(t("#bp_UTFMax").value=l.max),t("#bp_UTFAverage")&&(t("#bp_UTFAverage").value=l.ave),t("#bp_UTF_Result")&&(t("#bp_UTF_Result").value=l.res,x(t("#bp_UTF_Result"))),t("#bp_FTOMin")&&(t("#bp_FTOMin").value=i.min),t("#bp_FTOMax")&&(t("#bp_FTOMax").value=i.max),t("#bp_FTOAverage")&&(t("#bp_FTOAverage").value=i.ave),t("#bp_FTO_Result")&&(t("#bp_FTO_Result").value=i.res,x(t("#bp_FTO_Result"))),t("#bp_FiTOMin")&&(t("#bp_FiTOMin").value=r.min),t("#bp_FiTOMax")&&(t("#bp_FiTOMax").value=r.max),t("#bp_FiTOAverage")&&(t("#bp_FiTOAverage").value=r.ave),t("#bp_FiTO_Result")&&(t("#bp_FiTO_Result").value=r.res,x(t("#bp_FiTO_Result")));const f=[l.res,i.res,r.res].filter(Boolean);let n="PASS";f.includes("FAIL")?n="FAIL":f.includes("TOLERABLE")&&(n="TOLERABLE"),t("#bf_Result")&&(t("#bf_Result").value=n,x(t("#bf_Result")));const v=t("#bf-overall-badge");v&&(v.textContent=n,v.className="badge "+(n==="FAIL"?"b-err":n==="TOLERABLE"?"b-warn":"b-ok"))},Oe=async(e=null)=>{const a=t("#dtr-bonding-record-select");try{a&&(a.innerHTML='<option value="">Loading reports…</option>');const s=await O("dtr/records?slug=bonding");if(!a||!s.rows)return;if(!s.rows.length){a.innerHTML='<option value="">No bonding reports yet</option>';return}a.innerHTML=s.rows.map(o=>`
        <option value="${c(o.source_id)}" ${String(o.source_id)===String(e)?"selected":""}>
          ${c(o.lab_ref_no)} · ${c(o.model_style||"")} · ${c(o.color||"")} [${c(o.overall_result||"Pass")}]
        </option>
      `).join("")}catch(s){console.error(s),a&&(a.innerHTML='<option value="">Could not load reports - reload the page</option>'),window.nxToast&&nxToast("The bonding report list could not be loaded.","error")}},ke=async(e=0)=>{try{const a=await O(`dtr/record?slug=bonding&source_id=${encodeURIComponent(e)}`);if(!a||!a.ok)return;const s=a.record||{},o=a.bondingPoints||{};m=o,Object.keys(s).forEach(l=>{const i=t(`#bf_${l}`);i&&(i.value=s[l]??"")}),R('#dtr-bonding-form [name^="cf_"]').forEach(l=>{l.value=(a.customValues||{})[l.name]??""});for(let l=1;l<=35;l++)t(`#bp_UTFpoint${l}`)&&(t(`#bp_UTFpoint${l}`).value=o[`UTFpoint${l}`]??""),t(`#bp_FTOpoint${l}`)&&(t(`#bp_FTOpoint${l}`).value=o[`FTOpoint${l}`]??""),l<=13&&t(`#bp_FiTO${l}`)&&(t(`#bp_FiTO${l}`).value=o[`FiTO${l}`]??"");wt()}catch(a){console.error(a)}};(ca=t("#dtr-bonding-record-select"))==null||ca.addEventListener("change",e=>{e.target.value&&ke(e.target.value)}),(da=t("#bf_AgeCategory"))==null||da.addEventListener("change",wt),(fa=t("#bf_STD"))==null||fa.addEventListener("change",wt),R("#dtr-bonding-points-tbody .dtr-bp-inp").forEach(e=>{e.addEventListener("input",wt)}),(pa=t("#btn-bond-recalc"))==null||pa.addEventListener("click",()=>{wt(),$("✓ Recalculated UTF, FTO, and FiTO Min/Max/Ave & Spot Tolerances.")}),(ua=t("#btn-bond-new"))==null||ua.addEventListener("click",()=>{const e=new Date().toISOString().slice(0,10);R("#dtr-bonding-form input").forEach(a=>{a.value=""}),t("#bf_LabRefNo")&&(t("#bf_LabRefNo").value=`DTP${e.replace(/-/g,"")}SHB01-R`),t("#bf_AgeCategory")&&(t("#bf_AgeCategory").value="ADULT"),t("#bf_STD")&&(t("#bf_STD").value="2.5"),t("#bf_ReceivedDate")&&(t("#bf_ReceivedDate").value=e),t("#bf_TestedDate")&&(t("#bf_TestedDate").value=e),t("#bf_FinishedDate")&&(t("#bf_FinishedDate").value=e),wt(),$("Initialized new Whole Shoe Bonding Test Report.")}),(ma=t("#btn-bond-load-u60"))==null||ma.addEventListener("click",async()=>{var a;const e=((a=t("#bf_LabRefNo"))==null?void 0:a.value.trim())||"DTP20261024SHB11-R";try{const s=await O("dtr/load-machine",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"bonding",lab_ref_no:e})});s&&s.loadedFields&&(Object.keys(s.loadedFields).forEach(o=>{const l=t(`#bp_${o}`);l&&(l.value=s.loadedFields[o])}),wt(),$(`${s.message}`))}catch{$("Failed to load U60 machine file.",!0)}}),(va=t("#btn-bond-save"))==null||va.addEventListener("click",async()=>{var s,o,l,i,r,f,n,v,p,u,C,w,S;const e={};R("#dtr-bonding-form [name]").forEach(g=>{g.name&&(e[g.name]=g.value)});const a={UTFMin:((s=t("#bp_UTFMin"))==null?void 0:s.value)||null,UTFMax:((o=t("#bp_UTFMax"))==null?void 0:o.value)||null,UTFAverage:((l=t("#bp_UTFAverage"))==null?void 0:l.value)||null,UTF_Result:((i=t("#bp_UTF_Result"))==null?void 0:i.value)||null,FTOMin:((r=t("#bp_FTOMin"))==null?void 0:r.value)||null,FTOMax:((f=t("#bp_FTOMax"))==null?void 0:f.value)||null,FTOAverage:((n=t("#bp_FTOAverage"))==null?void 0:n.value)||null,FTO_Result:((v=t("#bp_FTO_Result"))==null?void 0:v.value)||null,FiTOMin:((p=t("#bp_FiTOMin"))==null?void 0:p.value)||null,FiTOMax:((u=t("#bp_FiTOMax"))==null?void 0:u.value)||null,FiTOAverage:((C=t("#bp_FiTOAverage"))==null?void 0:C.value)||null,FiTO_Result:((w=t("#bp_FiTO_Result"))==null?void 0:w.value)||null,Result:((S=t("#bf_Result"))==null?void 0:S.value)||"PASS"};R("#dtr-bonding-points-tbody [data-bp-col]").forEach(g=>{a[g.dataset.bpCol]=g.value});try{const g=await O("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"bonding",fields:e,bondingPoints:a})});g&&g.ok&&(t("#bf_BondingTestID")&&(t("#bf_BondingTestID").value=g.recordId),$(`✓ ${g.message}`),await Oe(g.recordId))}catch{$("Error saving Bonding Report.",!0)}});const je=()=>{var mt,B,j,D,vt,ct,Mt,Ot,kt,Nt,dt,tt,et,at,_t,Wt,st,bt,ht,gt,Tt,yt,Ft,St,Lt,$t,Ct;const e=parseFloat(((mt=t("#calc_raw_w1"))==null?void 0:mt.value)??"0")||0,a=parseFloat(((B=t("#calc_raw_w2"))==null?void 0:B.value)??"0")||0,s=parseFloat(((j=t("#calc_raw_w3"))==null?void 0:j.value)??"0")||0,o=h(e/100*1e4,2),l=h(a/100*1e4,2),i=h(s/100*1e4,2),r=h((o+l+i)/3,2);t("#calc_res_w1")&&(t("#calc_res_w1").value=o),t("#calc_res_w2")&&(t("#calc_res_w2").value=l),t("#calc_res_w3")&&(t("#calc_res_w3").value=i),t("#calc_res_wave")&&(t("#calc_res_wave").textContent=r);const f=parseFloat(((D=t("#calc_sg_w1"))==null?void 0:D.value)??"0")||0,n=parseFloat(((vt=t("#calc_sg_w2"))==null?void 0:vt.value)??"0")||0,v=parseFloat(((ct=t("#calc_sg_w3"))==null?void 0:ct.value)??"0")||0,p=parseFloat(((Mt=t("#calc_sg_t1"))==null?void 0:Mt.value)??"1")||1,u=parseFloat(((Ot=t("#calc_sg_t2"))==null?void 0:Ot.value)??"1")||1,C=parseFloat(((kt=t("#calc_sg_t3"))==null?void 0:kt.value)??"1")||1,w=h(f/(100*(p/10)),3),S=h(n/(100*(u/10)),3),g=h(v/(100*(C/10)),3),L=h((w+S+g)/3,3);t("#calc_res_sg_ave")&&(t("#calc_res_sg_ave").textContent=`${w}, ${S}, ${g} (Ave: ${L})`);const M=parseFloat(((Nt=t("#calc_cp_b1"))==null?void 0:Nt.value)??"10")||10,P=parseFloat(((dt=t("#calc_cp_a1"))==null?void 0:dt.value)??"9")||9,A=parseFloat(((tt=t("#calc_cp_b2"))==null?void 0:tt.value)??"10")||10,N=parseFloat(((et=t("#calc_cp_a2"))==null?void 0:et.value)??"9")||9,W=parseFloat(((at=t("#calc_cp_a3"))==null?void 0:at.value)??"9")||9,U=parseFloat(((_t=t("#calc_cp_sp"))==null?void 0:_t.value)??"0")||0,J=M-U!==0?M-U:1,nt=A-U!==0?A-U:1,V=h((M-P)/J*100,1),Y=h((A-N)/nt*100,1),z=h((M-W)/J*100,1),K=h((V+Y+z)/3,1);t("#calc_res_cp_ave")&&(t("#calc_res_cp_ave").textContent=`${K}% (${V}%, ${Y}%, ${z}%)`);const ft=parseFloat(((Wt=t("#calc_din_wb"))==null?void 0:Wt.value)??"0")||0,Dt=parseFloat(((st=t("#calc_din_wa"))==null?void 0:st.value)??"0")||0,Rt=parseFloat(((bt=t("#calc_din_sg"))==null?void 0:bt.value)??"1")||1,At=parseFloat(((ht=t("#calc_din_sa"))==null?void 0:ht.value)??"200")||200,Ut=h((ft-Dt)/Rt*(200/At)*1e3,2);t("#calc_res_din")&&(t("#calc_res_din").textContent=Ut);const xt=parseFloat(((gt=t("#calc_rf_li1"))==null?void 0:gt.value)??"2.5")||2.5,Et=parseFloat(((Tt=t("#calc_rf_lf1"))==null?void 0:Tt.value)??"2.5")||2.5,pt=parseFloat(((yt=t("#calc_rf_li2"))==null?void 0:yt.value)??"2.5")||2.5,Vt=parseFloat(((Ft=t("#calc_rf_lf2"))==null?void 0:Ft.value)??"2.5")||2.5,Pt=h((Et-xt)/xt*100,2),H=h((Vt-pt)/pt*100,2);t("#calc_res_rf")&&(t("#calc_res_rf").textContent=`${Pt}% / ${H}%`);const q=parseFloat(((St=t("#calc_sh_lb"))==null?void 0:St.value)??"100")||100,Z=parseFloat(((Lt=t("#calc_sh_la"))==null?void 0:Lt.value)??"100")||100,ut=parseFloat((($t=t("#calc_sh_wb"))==null?void 0:$t.value)??"100")||100,it=parseFloat(((Ct=t("#calc_sh_wa"))==null?void 0:Ct.value)??"100")||100,Q=h((q-Z)/q*100,2),T=h((ut-it)/ut*100,2);t("#calc_res_sh")&&(t("#calc_res_sh").textContent=`L: ${Q}% / W: ${T}%`)};R("#dtr-panel-calculators input").forEach(e=>{e.addEventListener("input",je)}),(_a=t("#btn-apply-calc-weight"))==null||_a.addEventListener("click",()=>{var e,a,s;t("#mf_Weight1")&&(t("#mf_Weight1").value=((e=t("#calc_res_w1"))==null?void 0:e.value)||""),t("#mf_Weight2")&&(t("#mf_Weight2").value=((a=t("#calc_res_w2"))==null?void 0:a.value)||""),t("#mf_Weight3")&&(t("#mf_Weight3").value=((s=t("#calc_res_w3"))==null?void 0:s.value)||""),rt(),k(),ot("material"),$("✓ Applied calculated Weight 1..3 and WAve to Material Form.")}),(ba=t("#btn-apply-calc-sg"))==null||ba.addEventListener("click",()=>{var r,f,n,v,p,u;const e=parseFloat(((r=t("#calc_sg_w1"))==null?void 0:r.value)??"0")||0,a=parseFloat(((f=t("#calc_sg_w2"))==null?void 0:f.value)??"0")||0,s=parseFloat(((n=t("#calc_sg_w3"))==null?void 0:n.value)??"0")||0,o=parseFloat(((v=t("#calc_sg_t1"))==null?void 0:v.value)??"1")||1,l=parseFloat(((p=t("#calc_sg_t2"))==null?void 0:p.value)??"1")||1,i=parseFloat(((u=t("#calc_sg_t3"))==null?void 0:u.value)??"1")||1;t("#mf_SG1")&&(t("#mf_SG1").value=h(e/(100*(o/10)),3)),t("#mf_SG2")&&(t("#mf_SG2").value=h(a/(100*(l/10)),3)),t("#mf_SG3")&&(t("#mf_SG3").value=h(s/(100*(i/10)),3)),rt(),k(),ot("material"),$("✓ Applied Specific Gravity SG1..3 and SG_Ave to Material Form.")}),(ha=t("#btn-apply-calc-din"))==null||ha.addEventListener("click",()=>{var a;const e=((a=t("#calc_res_din"))==null?void 0:a.textContent)||"";t("#mf_DIN_1")&&(t("#mf_DIN_1").value=e),rt(),k(),ot("material"),$("✓ Applied DIN Abrasion value to Material Form.")}),(ga=t("#btn-apply-calc-ross"))==null||ga.addEventListener("click",()=>{var l,i,r,f;const e=parseFloat(((l=t("#calc_rf_li1"))==null?void 0:l.value)??"2.5")||2.5,a=parseFloat(((i=t("#calc_rf_lf1"))==null?void 0:i.value)??"2.5")||2.5,s=parseFloat(((r=t("#calc_rf_li2"))==null?void 0:r.value)??"2.5")||2.5,o=parseFloat(((f=t("#calc_rf_lf2"))==null?void 0:f.value)??"2.5")||2.5;t("#mf_RossFlex_1")&&(t("#mf_RossFlex_1").value=h((a-e)/e*100,2)),t("#mf_RossFlex_2")&&(t("#mf_RossFlex_2").value=h((o-s)/s*100,2)),rt(),k(),ot("material"),$("✓ Applied Ross Flex 1 & 2 to Material Form.")}),(Ta=t("#btn-apply-calc-shrink"))==null||Ta.addEventListener("click",()=>{var l,i,r,f;const e=parseFloat(((l=t("#calc_sh_lb"))==null?void 0:l.value)??"100")||100,a=parseFloat(((i=t("#calc_sh_la"))==null?void 0:i.value)??"100")||100,s=parseFloat(((r=t("#calc_sh_wb"))==null?void 0:r.value)??"100")||100,o=parseFloat(((f=t("#calc_sh_wa"))==null?void 0:f.value)??"100")||100;t("#mf_ShrinkageL")&&(t("#mf_ShrinkageL").value=h((e-a)/e*100,2)),t("#mf_ShrinkageW")&&(t("#mf_ShrinkageW").value=h((s-o)/s*100,2)),rt(),k(),ot("material"),$("✓ Applied Shrinkage L & W to Material Form.")});const Ne=async()=>{var s,o;const e=((s=t("#exp_mat_type"))==null?void 0:s.value)||"Canvas",a=((o=t("#exp_t_ave"))==null?void 0:o.value)||"1.25";try{const l=await O(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`),i=t("#exp-standards-tbody");if(!i||!l.standards)return;i.innerHTML=l.standards.map(r=>`
        <tr>
          <td><b>${c(r.MaterialType)}</b></td>
          <td class="mono"><code>${c(r.TestMethod)}</code></td>
          <td class="mono"><b>${c(r.StandardValue??"-")}</b></td>
          <td class="mono">${c(r.Tolerance??"-")}</td>
          <td class="mono">${c(r.TMinC??"-")}</td>
          <td class="mono">${c(r.TMaxC??"-")}</td>
        </tr>
      `).join("")}catch(l){console.error(l)}};(ya=t("#btn-exp-run"))==null||ya.addEventListener("click",Ne),(Fa=t("#exp_mat_type"))==null||Fa.addEventListener("change",Ne);const Oa=()=>{var A,N,W,U,J,nt,V,Y,z,K,ft,Dt,Rt,At,Ut,xt,Et,pt,Vt,Pt,H,q,Z,ut,it,Q,T,mt,B,j,D,vt,ct,Mt,Ot,kt,Nt,dt,tt,et,at,_t,Wt,st,bt,ht,gt,Tt,yt,Ft,St,Lt,$t,Ct,Ht,Bt,jt,Gt,zt,qt,Qt,Xt,Jt,Yt,Kt,Zt,te,ee,ae,se,le,oe,re,ne,ie,ce,de,fe,pe,ue;const e=((A=t("#mf_LabRefNo"))==null?void 0:A.value)||"DTP-LAB-PREVIEW",a=((N=t("#mf_TR"))==null?void 0:N.value)||"-",s=((W=t("#mf_MaterialType"))==null?void 0:W.value)||"Material",o=((U=t("#mf_MaterialCode"))==null?void 0:U.value)||"-",l=((J=t("#mf_MaterialDescription"))==null?void 0:J.value)||"-",i=((nt=t("#mf_SupplierFactory"))==null?void 0:nt.value)||"DATIAN SUBIC SHOES INC.",r=((V=t("#mf_ModelNoStyle"))==null?void 0:V.value)||"-",f=((Y=t("#mf_Color"))==null?void 0:Y.value)||"-",n=((z=t("#mf_POLotNo"))==null?void 0:z.value)||"-",v=((K=t("#mf_MTL"))==null?void 0:K.value)||"-",p=((ft=t("#mf_TestedDate"))==null?void 0:ft.value)||new Date().toISOString().slice(0,10),u=((Dt=t("#mf_Technician"))==null?void 0:Dt.value)||"Lab Technician",C=((Rt=t("#mf_AuthorizedBy"))==null?void 0:Rt.value)||"QA Manager",w=((At=t("#mf_AuthorizedDate"))==null?void 0:At.value)||"",S=(((Ut=t("#mf_Remarks"))==null?void 0:Ut.value)||"PASS").toUpperCase(),g=S==="PASS"?"":S==="TOLERABLE"?"stamp-tol":"stamp-fail",L=[],M=(me,ve,_e,we,It,be,d,_)=>{(_e||be)&&L.push(`
          <tr>
            <td class="text-left"><b>${me} — Length (L)</b></td>
            <td>${ve}</td>
            <td><b>${_e||"-"}</b></td>
            <td>${we||"-"}</td>
            <td><b class="${It==="PASS"?"dtr-rate-pass":It==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${It||"-"}</b></td>
          </tr>
          <tr>
            <td class="text-left"><b>${me} — Width (W)</b></td>
            <td>${ve}</td>
            <td><b>${be||"-"}</b></td>
            <td>${d||"-"}</td>
            <td><b class="${_==="PASS"?"dtr-rate-pass":_==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${_||"-"}</b></td>
          </tr>
        `)};M("Tensile Strength","kg",(xt=t("#mf_TensileLAve"))==null?void 0:xt.value,(Et=t("#mf_Tensile_L"))==null?void 0:Et.value,(pt=t("#mf_TensileLRating"))==null?void 0:pt.value,(Vt=t("#mf_TensileWAve"))==null?void 0:Vt.value,(Pt=t("#mf_Tensile_W"))==null?void 0:Pt.value,(H=t("#mf_TensileWRating"))==null?void 0:H.value),M("Elongation","%",(q=t("#mf_ElongationLAve"))==null?void 0:q.value,(Z=t("#mf_Elongation_L"))==null?void 0:Z.value,(ut=t("#mf_ElongationLRating"))==null?void 0:ut.value,(it=t("#mf_ElongationWAve"))==null?void 0:it.value,(Q=t("#mf_Elongation_W"))==null?void 0:Q.value,(T=t("#mf_ElongationWRating"))==null?void 0:T.value),M("Trouser / Wing Tear","kg",(mt=t("#mf_TrouserTearLAve"))==null?void 0:mt.value,(B=t("#mf_TrouserTear_L"))==null?void 0:B.value,(j=t("#mf_TrouserTearLRating"))==null?void 0:j.value,(D=t("#mf_TrouserTearWAve"))==null?void 0:D.value,(vt=t("#mf_TrouserTear_W"))==null?void 0:vt.value,(ct=t("#mf_TrouserTearWRating"))==null?void 0:ct.value),M("Stitch Tear","kg",(Mt=t("#mf_StitchTearLAve"))==null?void 0:Mt.value,(Ot=t("#mf_StitchTear_L"))==null?void 0:Ot.value,(kt=t("#mf_StitchTearLRating"))==null?void 0:kt.value,(Nt=t("#mf_StitchTearWAve"))==null?void 0:Nt.value,(dt=t("#mf_StitchTear_W"))==null?void 0:dt.value,(tt=t("#mf_StitchTearWRating"))==null?void 0:tt.value),(et=t("#mf_Text3977"))!=null&&et.value&&L.push(`
        <tr>
          <td class="text-left"><b>Mullen Bursting Strength</b></td>
          <td>kg/cm²</td>
          <td><b>${(at=t("#mf_Text3977"))==null?void 0:at.value}</b></td>
          <td>${((_t=t("#mf_MullenBurst"))==null?void 0:_t.value)||"-"}</td>
          <td><b>${((Wt=t("#mf_MullenBurstRating"))==null?void 0:Wt.value)||"-"}</b></td>
        </tr>
      `),(st=t("#mf_DieC_Ave"))!=null&&st.value&&L.push(`
        <tr>
          <td class="text-left"><b>Die C Tear / Edge Crush</b></td>
          <td>kg</td>
          <td><b>${(bt=t("#mf_DieC_Ave"))==null?void 0:bt.value}</b></td>
          <td>${((ht=t("#mf_DieC_Std"))==null?void 0:ht.value)||"-"}</td>
          <td><b>${((gt=t("#mf_DieC_Rate"))==null?void 0:gt.value)||"-"}</b></td>
        </tr>
      `),(Tt=t("#mf_WAve"))!=null&&Tt.value&&L.push(`
        <tr>
          <td class="text-left"><b>Fabric Weight (Grammage)</b></td>
          <td>g/m²</td>
          <td><b>${(yt=t("#mf_WAve"))==null?void 0:yt.value}</b></td>
          <td>${((Ft=t("#mf_W_Std"))==null?void 0:Ft.value)||"-"}</td>
          <td><b>${((St=t("#mf_WRating"))==null?void 0:St.value)||"-"}</b></td>
        </tr>
      `),(Lt=t("#mf_CTPRating"))!=null&&Lt.value&&L.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Perspiration (Face)</b></td>
          <td>Grade</td>
          <td>W:${(($t=t("#mf_CtPWool"))==null?void 0:$t.value)||"-"} A:${((Ct=t("#mf_CtPAcrylic"))==null?void 0:Ct.value)||"-"} P:${((Ht=t("#mf_CtPPolyester"))==null?void 0:Ht.value)||"-"} N:${((Bt=t("#mf_CtPNylon"))==null?void 0:Bt.value)||"-"} C:${((jt=t("#mf_CtPCotton"))==null?void 0:jt.value)||"-"} Ac:${((Gt=t("#mf_CtPAcetate"))==null?void 0:Gt.value)||"-"}</td>
          <td>${((zt=t("#mf_CTP"))==null?void 0:zt.value)||"4.0"}</td>
          <td><b>${(qt=t("#mf_CTPRating"))==null?void 0:qt.value}</b></td>
        </tr>
      `),(Qt=t("#mf_CTWRating"))!=null&&Qt.value&&L.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Water (Face)</b></td>
          <td>Grade</td>
          <td>W:${((Xt=t("#mf_CtWWool"))==null?void 0:Xt.value)||"-"} A:${((Jt=t("#mf_CtWAcrylic"))==null?void 0:Jt.value)||"-"} P:${((Yt=t("#mf_CtWPolyester"))==null?void 0:Yt.value)||"-"} N:${((Kt=t("#mf_CtWNylon"))==null?void 0:Kt.value)||"-"} C:${((Zt=t("#mf_CtWCotton"))==null?void 0:Zt.value)||"-"} Ac:${((te=t("#mf_CtWAcetate"))==null?void 0:te.value)||"-"}</td>
          <td>${((ee=t("#mf_CTW"))==null?void 0:ee.value)||"4.0"}</td>
          <td><b>${(ae=t("#mf_CTWRating"))==null?void 0:ae.value}</b></td>
        </tr>
      `),(se=t("#mf_CTCDRYRating"))!=null&&se.value&&L.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Dry)</b></td>
          <td>Grade</td>
          <td><b>${((le=t("#mf_CtCDry"))==null?void 0:le.value)||"-"}</b></td>
          <td>${((oe=t("#mf_CtCDry_S"))==null?void 0:oe.value)||"4.0"}</td>
          <td><b>${(re=t("#mf_CTCDRYRating"))==null?void 0:re.value}</b></td>
        </tr>
      `),(ne=t("#mf_CTCWETRating"))!=null&&ne.value&&L.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Wet)</b></td>
          <td>Grade</td>
          <td><b>${((ie=t("#mf_CtCWet"))==null?void 0:ie.value)||"-"}</b></td>
          <td>${((ce=t("#mf_CtCWet_S"))==null?void 0:ce.value)||"3.0"}</td>
          <td><b>${(de=t("#mf_CTCWETRating"))==null?void 0:de.value}</b></td>
        </tr>
      `);const P=`
      <div class="dtr-report-sheet">
        <div class="rpt-logo-row">
          <img class="rpt-logo-vf" src="${I}assets/images/vf-logo.webp" alt="VF">
          <img class="rpt-logo-satra" src="${I}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
        </div>
        <div class="dtr-sheet-header">
          <div class="dtr-sheet-title">
            <h2>DATIAN SUBIC SHOES INC. (DTP)</h2>
            <h4>PHYSICAL TEST REPORT — ${c(s.toUpperCase())}</h4>
            <div style="font-size:11px;color:#64748b;margin-top:2px">Lot 1P7-A, Phase 2, Subic Bay Gateway Park, Subic Bay Freeport Zone</div>
          </div>
          <div class="dtr-sheet-meta">
            <div>Lab Ref: <b>${c(e)}</b></div>
            <div>TR #: <b>${c(a)}</b></div>
            <div style="margin-top:6px">
              <span class="dtr-sheet-stamp-box ${g}">${c(S)}</span>
            </div>
          </div>
        </div>

        <table class="dtr-sheet-info-grid">
          <tr>
            <td class="label-cell">Material Code</td>
            <td><b>${c(o)}</b></td>
            <td class="label-cell">Material Type</td>
            <td>${c(s)}</td>
            <td class="label-cell">Tested Date</td>
            <td>${c(p)}</td>
          </tr>
          <tr>
            <td class="label-cell">Description</td>
            <td colspan="3">${c(l)}</td>
            <td class="label-cell">PO / Lot No.</td>
            <td>${c(n)}</td>
          </tr>
          <tr>
            <td class="label-cell">Supplier / Factory</td>
            <td>${c(i)}</td>
            <td class="label-cell">Model / Style</td>
            <td>${c(r)}</td>
            <td class="label-cell">Color / MTL</td>
            <td>${c(f)} / ${c(v)}</td>
          </tr>
          <tr>
            <td class="label-cell">Thickness (TAve)</td>
            <td><b>${((fe=t("#mf_TAve"))==null?void 0:fe.value)||"-"} mm</b></td>
            <td class="label-cell">Weight (WAve)</td>
            <td><b>${((pe=t("#mf_WAve"))==null?void 0:pe.value)||"-"} g/m²</b></td>
            <td class="label-cell">Technician</td>
            <td>${c(u)}</td>
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
            ${L.length?L.join(""):'<tr><td colspan="5" class="muted">No test items recorded for this report.</td></tr>'}
          </tbody>
        </table>

        <div class="dtr-sheet-sign-row">
          <div class="dtr-sheet-sign-box">
            <div>Prepared by (Testing Technician):</div>
            <div class="dtr-sheet-sign-line">
              <span>${c(u)}</span>
              <span class="muted">${c(p)}</span>
            </div>
          </div>
          <div class="dtr-sheet-sign-box">
            <div>Reviewed &amp; Approved by (QA Lab Head):</div>
            <div class="dtr-sheet-sign-line">
              <span>${c(C)}</span>
              <span class="muted">${c(w||p)}</span>
            </div>
          </div>
        </div>
      </div>
    `;t("#dtr-print-modal-title").textContent=`Official Test Report Sheet — ${e}`,t("#dtr-print-sheet-container").innerHTML=P,(ue=t("#dtr-print-modal-backdrop"))==null||ue.classList.remove("hidden")};let Ce=null;const ka=async()=>{if(Ce)return Ce;try{const e=await fetch(I+"assets/images/bonding_sketches/sketch_configs.json");if(e.ok)return Ce=await e.json(),Ce}catch(e){console.warn("Failed to load sketch_configs.json",e)}return null},Na=async()=>{var bt,ht,gt,Tt,yt,Ft,St,Lt,$t,Ct,Ht,Bt,jt,Gt,zt,qt,Qt,Xt,Jt,Yt,Kt,Zt,te,ee,ae,se,le,oe,re,ne,ie,ce,de,fe,pe,ue,me,ve,_e,we,It,be;const e=parseInt(((bt=t("#bf_BondingTestID"))==null?void 0:bt.value)||(m==null?void 0:m.BondingTestID)||0)||0,a=((ht=t("#bf_LabRefNo"))==null?void 0:ht.value)||"DTP-BOND-PREVIEW",s=((gt=t("#bf_ETRFNo"))==null?void 0:gt.value)||"-",o=((Tt=t("#bf_ModelStyle"))==null?void 0:Tt.value)||"-",l=((yt=t("#bf_Color"))==null?void 0:yt.value)||"-",i=((Ft=t("#bf_Side"))==null?void 0:Ft.value)||"Left",r=i.toLowerCase()==="left",f=(((St=t("#bf_AgeCategory"))==null?void 0:St.value)||"ADULT").toUpperCase(),n=parseFloat(((Lt=t("#bf_STD"))==null?void 0:Lt.value)||"2.5")||2.5,v=(($t=t("#bf_POLotNO"))==null?void 0:$t.value)||"-",p=((Ct=t("#bf_POQuantity"))==null?void 0:Ct.value)||"-",u=((Ht=t("#bf_Factory"))==null?void 0:Ht.value)||"DATIAN SUBIC SHOES INC. (DTP)",C=((Bt=t("#bf_AdhesiveSupplier"))==null?void 0:Bt.value)||"-",w=((jt=t("#bf_ShippedDate"))==null?void 0:jt.value)||"-",S=((Gt=t("#bf_ProductionDate"))==null?void 0:Gt.value)||"-",g=((zt=t("#bf_Sendby"))==null?void 0:zt.value)||"-",L=((qt=t("#bf_MaterialSource"))==null?void 0:qt.value)||"Prod. Shoes",M=((Qt=t("#bf_ModeOfFailure"))==null?void 0:Qt.value)||"-",P=((Xt=t("#bf_PossibleCause"))==null?void 0:Xt.value)||"-",A=((Jt=t("#bf_Remarks"))==null?void 0:Jt.value)||((Yt=t("#bf_LabNotesComments"))==null?void 0:Yt.value)||"",N=((Kt=t("#bf_ReceivedDate"))==null?void 0:Kt.value)||"-",W=((Zt=t("#bf_TestedDate"))==null?void 0:Zt.value)||new Date().toISOString().slice(0,10),U=((te=t("#bf_FinishedDate"))==null?void 0:te.value)||W,J=((ee=t("#bf_Technician"))==null?void 0:ee.value)||"L. Aquidado",nt=((ae=t("#bf_ReviewedBy"))==null?void 0:ae.value)||"QA Lead",V=(((se=t("#bf_Result"))==null?void 0:se.value)||"PASS").toUpperCase(),Y=V==="PASS"?"":V==="TOLERABLE"?"stamp-tol":"stamp-fail",z=((le=t("#bp_UTFMin"))==null?void 0:le.value)||(m==null?void 0:m.UTFMin)||"-",K=((oe=t("#bp_UTFMax"))==null?void 0:oe.value)||(m==null?void 0:m.UTFMax)||"-",ft=((re=t("#bp_UTFAverage"))==null?void 0:re.value)||(m==null?void 0:m.UTFAverage)||"-",Dt=(((ne=t("#bp_UTF_Result"))==null?void 0:ne.value)||(m==null?void 0:m.UTF_Result)||"-").toUpperCase(),Rt=((ie=t("#bp_FTOMin"))==null?void 0:ie.value)||(m==null?void 0:m.FTOMin)||"-",At=((ce=t("#bp_FTOMax"))==null?void 0:ce.value)||(m==null?void 0:m.FTOMax)||"-",Ut=((de=t("#bp_FTOAverage"))==null?void 0:de.value)||(m==null?void 0:m.FTOAverage)||"-",xt=(((fe=t("#bp_FTO_Result"))==null?void 0:fe.value)||(m==null?void 0:m.FTO_Result)||"-").toUpperCase(),Et=((pe=t("#bp_FiTOMin"))==null?void 0:pe.value)||(m==null?void 0:m.FiTOMin)||"-",pt=((ue=t("#bp_FiTOMax"))==null?void 0:ue.value)||(m==null?void 0:m.FiTOMax)||"-",Vt=((me=t("#bp_FiTOAverage"))==null?void 0:me.value)||(m==null?void 0:m.FiTOAverage)||"-",Pt=(((ve=t("#bp_FiTO_Result"))==null?void 0:ve.value)||(m==null?void 0:m.FiTO_Result)||"-").toUpperCase(),H={},q={},Z={};let ut=0;for(let d=1;d<=35;d++){const _=((_e=t("#bp_UTFpoint"+d))==null?void 0:_e.value)||(m==null?void 0:m["UTFpoint"+d])||"",b=((we=t("#bp_FTOpoint"+d))==null?void 0:we.value)||(m==null?void 0:m["FTOpoint"+d])||"";H[d]=_,q[d]=b,(parseFloat(_)>0||parseFloat(b)>0)&&(ut=d),d<=13&&(Z[d]=((It=t("#bp_FiTO"+d))==null?void 0:It.value)||(m==null?void 0:m["FiTO"+d])||"")}const it=await ka();let Q=parseInt((m==null?void 0:m.MaxPoints)||ut||27);(!it||!it[Q])&&(Q=[26,27,28,29,30,31,32,33,34].reduce((_,b)=>Math.abs(b-Q)<Math.abs(_-Q)?b:_,27));let T=it?it[Q]:null,mt=[],B=null;if(T)try{const d=await O("dtr/layout?slug=bonding&variant="+encodeURIComponent(Q)),_=d&&d.layout;_&&(T=JSON.parse(JSON.stringify(T)),_.image&&Object.assign(T.image,_.image),["utf","fto"].forEach(b=>{Object.keys(_[b]||{}).forEach(y=>{_[b][y].hidden?delete T[b][y]:T[b][y]=_[b][y]})}),mt=_.extras||[],(_.vector||_.start)&&(B=_),_.vector&&(T.image.file=_.imageFile||"blank.png"))}catch{}const j=(d,_,b=!1)=>{const y=Be(parseFloat(d),b?Le:_);return y?y==="FAIL"?"color:#dc2626;font-weight:800;":y==="TOLERABLE"?"color:#d97706;font-weight:800;":"color:#1d4ed8;font-weight:800;":"color:#94a3b8"};let D="";if(T){D+=`
        <img src="${I}assets/images/bonding_sketches/${T.image.file}" 
             class="vf-shoe-img" 
             style="left:${T.image.pctLeft}%; top:${T.image.pctTop}%; width:${T.image.pctWidth}%; height:${T.image.pctHeight}%;" 
             alt="${i} Shoe Sketch">
      `;for(let d=1;d<=T.pointsCount;d++){const _=T.utf[d];if(_){const y=H[d],F=parseFloat(y),E=Number.isFinite(F)&&F>0,G=j(y,n,!1);D+=`
            <div class="vf-point-box vf-point-utf" 
                 style="left:${_.pctLeft}%; top:${_.pctTop}%; width:${_.pctWidth}%; height:${_.pctHeight}%; ${G}" 
                 title="UTF Point ${d}: ${E?F.toFixed(2):"-"}">
              ${E?F.toFixed(2):""}
            </div>
          `}const b=T.fto[d];if(b){const y=q[d],F=parseFloat(y),E=Number.isFinite(F)&&F>0,G=j(y,n,!1);D+=`
            <div class="vf-point-box vf-point-fto" 
                 style="left:${b.pctLeft}%; top:${b.pctTop}%; width:${b.pctWidth}%; height:${b.pctHeight}%; ${G}" 
                 title="FTO Point ${d}: ${E?F.toFixed(2):"-"}">
              ${E?F.toFixed(2):""}
            </div>
          `}}if(B){let d='<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none">',_="";if(B.vector&&["utf","fto"].forEach(b=>Object.keys(T[b]).forEach(y=>{const F=T[b][y],E=((B.dots||{})[b]||{})[y];if(!E)return;const G=F.pctLeft+F.pctWidth,Re=F.pctTop+F.pctHeight/2,X=E.x<F.pctLeft?F.pctLeft:E.x>G?G:F.pctLeft+F.pctWidth/2,he=X===F.pctLeft||X===G?Re:E.y<F.pctTop?F.pctTop:F.pctTop+F.pctHeight,Aa=b==="utf"?"#2563eb":"#7c3aed";d+=`<line x1="${X}" y1="${he}" x2="${E.x}" y2="${E.y}" stroke="${Aa}" stroke-width="1.2" vector-effect="non-scaling-stroke"/>`,_+=`<div style="position:absolute;left:${E.x}%;top:${E.y}%;width:7px;height:7px;margin:-3.5px 0 0 -3.5px;border-radius:50%;background:${Aa}"></div>`})),B.start){const b=B.start;_+=`<div style="position:absolute;left:${b.x}%;top:${b.y}%;transform:translate(-50%,-50%);display:flex;align-items:center;gap:3px;white-space:nowrap;color:#b91c1c;font:800 9px sans-serif"><i style="display:block;width:9px;height:9px;background:#dc2626;transform:rotate(45deg)"></i>${c(b.label||"START")}</div>`}D+=d+"</svg>"+_}mt.forEach(d=>{var E;const _=`left:${d.pctLeft}%; top:${d.pctTop}%; width:${d.pctWidth}%; height:${d.pctHeight}%;`,b=`color:${c(d.color||"#111")}; font-size:${Number(d.size)||12}px; font-weight:${d.bold?700:400};`;let y="";if(d.type==="text")y=c(d.text);else if(d.type==="field")y=c(((E=t("#bf_"+d.field))==null?void 0:E.value)||"");else if(d.type==="image"&&d.src)y=`<img src="${I}${c(d.src)}" style="max-width:100%;max-height:100%" alt="">`;else if(d.type==="chart"){let G=0;for(let X=1;X<=T.pointsCount;X++)G=Math.max(G,parseFloat(H[X])||0);let Re="";for(let X=1;X<=T.pointsCount;X++){const he=parseFloat(H[X])||0;Re+=`<i style="flex:1;display:block;min-height:1px;height:${G?he/G*100:0}%;background:${he<2?"#dc2626":he<3?"#d97706":"#1d4ed8"}"></i>`}y=`<div style="display:flex;align-items:flex-end;gap:1px;width:100%;height:100%">${Re}</div>`}const F=d.type==="line"?`background:${c(d.color||"#111")};`:"";D+=`<div style="position:absolute;display:flex;align-items:center;overflow:hidden;${_}${b}${F}">${y}</div>`})}else D='<div style="padding:20px;text-align:center;color:#64748b">Shoe sketch unavailable</div>';const vt=`
      <img src="${I}assets/images/bonding_sketches/blank.png" 
           class="vf-shoe-img" 
           style="left:12%; top:8%; width:76%; height:84%; opacity:0.85;" 
           alt="Unmeasured Shoe Contour">
    `;let ct="";for(let d=1;d<=13;d++){const _=Z[d],b=parseFloat(_),y=Number.isFinite(b)&&b>0,F=j(_,1,!0);ct+=`
        <div class="vf-center-item" style="${F}">
          ${y?b.toFixed(2):"&nbsp;"}
        </div>
      `}const Mt=r?D:vt,Ot=r?vt:D,kt=r?ct:"",Nt=r?"":ct,dt=d=>d==="PASS"?{pass:"PASS",fail:"",passCls:"vf-rate-pass",failCls:""}:d==="TOLERABLE"?{pass:"TOLE",fail:"",passCls:"vf-rate-tol",failCls:""}:d==="FAIL"||d==="FAILED"?{pass:"",fail:"FAIL",passCls:"",failCls:"vf-rate-fail"}:{pass:"-",fail:"",passCls:"",failCls:""},tt=dt(Dt),et=dt(xt),at=dt(Pt);let _t="";for(let d=1;d<=(T?T.pointsCount:27);d++){const _=H[d]?parseFloat(H[d]).toFixed(2):"-",b=q[d]?parseFloat(q[d]).toFixed(2):"-",y=d<=13&&Z[d]?parseFloat(Z[d]).toFixed(2):"-";_t+=`
        <tr>
          <td><b>Point ${d}</b></td>
          <td style="${j(H[d],n,!1)}">${_}</td>
          <td style="${j(q[d],n,!1)}">${b}</td>
          <td style="${d<=13?j(Z[d],1,!0):"color:#94a3b8"}">${y}</td>
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
              <span class="vf-val-underlined">${c(s)}</span>
            </div>
            <div class="vf-labref-row">
              <span>Lab Ref.No:</span>
              <span class="vf-labref-val">${c(a)}</span>
            </div>
            <div class="vf-test-categories">
              <div>
                <span class="vf-radio ${L==="Prod. Shoes"||!L?"checked":""}"></span> Pro. Shoes 量产鞋
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${L==="Pro. Re-test"?"checked":""}"></span> Pro. Re-test 量产重测
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${L==="NewDevelopment"?"checked":""}"></span> NewDevelopment 开发
              </div>
              <div class="vf-tech-subradios">
                <span>○ Tech 1</span> <span>○ Tech 2</span> <span>○ Tech 3</span> <span>○ Tech 4</span>
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${L==="Sales Sample"?"checked":""}"></span> Sales Sample 销样
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${L==="Trial experiment"?"checked":""}"></span> 试胶试验
              </div>
            </div>
          </div>

          <div class="rpt-sub-right">
            <div class="vf-stamp-area">
              <span class="vf-stamp ${Y}">${c(V)}</span>
            </div>
            <table class="vf-dates-table">
              <tr><td>Received Date 收到日期:</td><td class="vf-date-val">${c(N)}</td></tr>
              <tr><td>Tested Date 测试日期:</td><td class="vf-date-val">${c(W)}</td></tr>
              <tr><td>Finished Date 完成日期:</td><td class="vf-date-val">${c(U)}</td></tr>
              <tr><td>Technician 技术员:</td><td class="vf-date-val">${c(J)}</td></tr>
            </table>
          </div>
        </div>

        <!-- Factory & Spec Details Grid -->
        <table class="vf-meta-grid">
          <tr>
            <td class="vf-lbl">Factory (工厂):</td>
            <td class="vf-val" colspan="3">${c(u)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Adhesive supplier 胶水供应商:</td>
            <td class="vf-val">${c(C)}</td>
            <td class="vf-lbl">Shipped Date (出货日期):</td>
            <td class="vf-val">${c(w)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Model/Style/PGSC (英文鞋型):</td>
            <td class="vf-val">${c(o)}</td>
            <td class="vf-lbl">Color (颜色):</td>
            <td class="vf-val">${c(l)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Production Date (生产日期):</td>
            <td class="vf-val">${c(S!=="-"?S:W)}</td>
            <td class="vf-lbl">PO/Lot No (订单号码):</td>
            <td class="vf-val">${c(v)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">PO Quantity (订单数量):</td>
            <td class="vf-val">${c(p)}</td>
            <td class="vf-lbl">Send by (送达):</td>
            <td class="vf-val">${c(g)}</td>
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
              <td class="font-bold text-blue">${n}<br><span class="vf-tol">(≥ ${$e(n)})</span></td>
              <td class="font-bold text-blue">${z!=="-"?Number(z).toFixed(2):"-"}</td>
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
              <td class="font-bold text-blue">${n}<br><span class="vf-tol">(≥ ${$e(n)})</span></td>
              <td class="font-bold text-blue">${Rt!=="-"?Number(Rt).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${At!=="-"?Number(At).toFixed(2):"-"}</td>
              <td><span class="${et.passCls}">${et.pass}</span></td>
              <td><span class="${et.failCls}">${et.fail}</span></td>
            </tr>
            <tr>
              <td class="text-left font-bold">Center : IS/OS<br>(中插 / 大底)</td>
              <td>VF-F01<br><span style="font-size:9.5px">(2019/05)</span></td>
              <td class="font-bold text-blue">${Le}<br><span class="vf-tol">(≥ ${$e(Le)})</span></td>
              <td class="font-bold text-blue">${Et!=="-"?Number(Et).toFixed(2):"-"}</td>
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
            <svg class="ic" aria-hidden="true"><use href="#i-chart"/></svg> View Tabular Spot Values (${T?T.pointsCount:27} Points)
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
              ${c(A||"Tested in accordance with VF Laboratory Standards.")}
            </div>
          </div>
          <div>
            Reviewed by 审查:
            <div class="vf-sign-line">
              <span>${c(nt)}</span>
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
    `;t("#dtr-print-modal-title").textContent=`Whole Shoe Bonding Test Report — ${a} (${i})`,t("#dtr-print-sheet-container").innerHTML=Wt;const st=t("#btn-dtr-download-access-pdf");st&&(e>0?(st.href=`${I}dtr/bonding-access-pdf?id=${e}&side=${encodeURIComponent(i)}`,st.classList.remove("hidden")):st.classList.add("hidden")),(be=t("#dtr-print-modal-backdrop"))==null||be.classList.remove("hidden")},We=e=>{const a=748.3464566929134,s=285/25.4*96,o=document.createElement("div");o.className="dtr-fit-probe",o.style.cssText="position:absolute;left:-99999px;top:0;visibility:hidden;",document.body.appendChild(o);let l=1;for(let i=0;i<4;i++){o.style.width=a/l+"px",o.innerHTML=e;const r=o.scrollHeight*l,f=Math.min(1,l*(s/Math.max(r,1))*.985);if(Math.abs(f-l)<.005){l=f;break}l=f}return o.remove(),Math.max(.4,Math.round(l*1e3)/1e3)};(Sa=t("#btn-dtr-print-preview"))==null||Sa.addEventListener("click",Oa),(La=t("#btn-bond-print-preview"))==null||La.addEventListener("click",Na),($a=t("#btn-dtr-close-print-modal"))==null||$a.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(Ca=t("#btn-dtr-cancel-print"))==null||Ca.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(wa=t("#btn-dtr-download-pdf"))==null||wa.addEventListener("click",async()=>{var o,l,i,r,f,n;const e=t("#btn-dtr-download-pdf"),a=t("#dtr-print-sheet-container");if(!a||!e)return;const s=e.textContent;e.disabled=!0,e.textContent="Creating PDF...";try{const v=await fetch(I+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((o=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:o.content)||""},body:JSON.stringify({access_id:parseInt(((i=(((l=t("#btn-dtr-download-access-pdf"))==null?void 0:l.href)||"").match(/[?&]id=(\d+)/))==null?void 0:i[1])||0,10)&&!t("#btn-dtr-download-access-pdf").classList.contains("hidden")?parseInt(t("#btn-dtr-download-access-pdf").href.match(/[?&]id=(\d+)/)[1],10):0,side:((((r=t("#btn-dtr-download-access-pdf"))==null?void 0:r.href)||"").match(/side=(\w+)/)||[])[1]||"Left",scale:We(a.innerHTML),html:a.innerHTML,name:(((f=t("#dtr-print-modal-title"))==null?void 0:f.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_")})});if(!v.ok)throw new Error((await v.text()).slice(0,200)||"PDF failed");const p=await v.blob(),u=document.createElement("a");u.href=URL.createObjectURL(p),u.download=((((n=t("#dtr-print-modal-title"))==null?void 0:n.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_").trim()||"Report")+".pdf",document.body.appendChild(u),u.click(),u.remove(),setTimeout(()=>URL.revokeObjectURL(u.href),4e3)}catch(v){nxToast("Could not create the PDF: "+v.message,"error",7e3)}e.disabled=!1,e.textContent=s}),(Ra=t("#btn-dtr-do-print"))==null||Ra.addEventListener("click",()=>{const e=t("#dtr-print-sheet-container");if(!e){window.print();return}const a=document.createElement("div");a.id="dtr-print-root",a.innerHTML=e.innerHTML,a.style.zoom=String(We(e.innerHTML)),document.body.appendChild(a),document.body.classList.add("dtr-printing");const s=()=>{document.body.classList.remove("dtr-printing"),a.remove(),window.removeEventListener("afterprint",s)};window.addEventListener("afterprint",s),window.print()});const Wa=async()=>{var e,a;try{const s=await O("dtr/layout?slug=bonding&variant=form"),o=s&&s.layout,l=t("#dtr-bonding-form");if(!o||!l)return;const i=f=>{var n;return(n=l.querySelector(`[name="${f}"]`))==null?void 0:n.closest(".dtr-field")};(o.customFields||[]).forEach(f=>{var p,u;if(l.querySelector(`[name="${f.key}"]`))return;const n=(u=(p=t("#bf_LabRefNo"))==null?void 0:p.closest(".dtr-field"))==null?void 0:u.parentElement;if(!n)return;const v=document.createElement("div");v.className="dtr-field",v.innerHTML=`<label>${c(f.label)}</label><input type="text" name="${c(f.key)}" id="bf_${c(f.key)}">`,n.appendChild(v)}),Object.entries(o.labels||{}).forEach(([f,n])=>{const v=i(f),p=v&&v.querySelector("label");p&&n&&(p.textContent=n)});const r=(a=(e=t("#bf_LabRefNo"))==null?void 0:e.closest(".dtr-field"))==null?void 0:a.parentElement;r&&(o.order||[]).length&&(o.order.forEach(f=>{const n=i(f);n&&n.parentElement===r&&r.appendChild(n)}),(o.customFields||[]).forEach(f=>{const n=i(f.key);n&&r.appendChild(n)})),(o.hiddenFields||[]).forEach(f=>{const n=i(f);n&&(n.style.display="none")})}catch{}};je(),ye("fabric"),Fe("fabric",0),Wa().then(()=>{Oe(),ke(0)}),Ne(),(()=>{const e=new URLSearchParams(location.search),[a,s]=String(e.get("open")||"").split(":");!a||!/^[a-z]+$/.test(a)||!/^\d+$/.test(s||"")||setTimeout(async()=>{var i,r;const o=t("#dtr-tracker-tbody")||document.body,l=document.createElement("button");if(l.type="button",l.hidden=!0,l.dataset.openRecord=a,l.dataset.sourceId=s,o.appendChild(l),l.click(),l.remove(),e.get("preview")==="1"&&(await new Promise(f=>setTimeout(f,2200)),(i=t(a==="bonding"?"#btn-bond-print-preview":"#btn-dtr-print-preview"))==null||i.click()),e.get("export")==="1"&&window.parent!==window){const f=n=>window.parent.postMessage(Object.assign({type:"NXL_REPORT_PDF"},n),location.origin);try{await new Promise(S=>setTimeout(S,800));const n=t("#dtr-print-sheet-container");if(!n||!n.innerHTML.trim())throw new Error("The report sheet could not be prepared.");const v=t("#btn-dtr-download-access-pdf"),p=v&&!v.classList.contains("hidden")?parseInt(((v.href||"").match(/[?&]id=(\d+)/)||[])[1]||0,10):0,u=await fetch(I+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((r=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:r.content)||""},body:JSON.stringify({access_id:p,side:(((v==null?void 0:v.href)||"").match(/side=(\w+)/)||[])[1]||"Left",scale:We(n.innerHTML),html:n.innerHTML,name:"Report"})});if(!u.ok)throw new Error((await u.text()).slice(0,200)||"PDF failed");const C=new Uint8Array(await u.arrayBuffer());let w="";for(let S=0;S<C.length;S+=32768)w+=String.fromCharCode.apply(null,C.subarray(S,S+32768));f({ok:!0,b64:btoa(w)})}catch(n){f({ok:!1,error:n.message})}}},900)})()})();
