(()=>{"use strict";var ua,va,ma,ha,ba,_a,ga,Ta,ya,Sa,$a,Fa,La,Ca,wa,Ra,xa,Aa,Ea,Ma,Oa,ka,Pa,Na,Da,Wa,Ia,Ua,Ha,Va,Ba,ja,Ga,za,qa,Qa,Ja,Ka,Xa,Ya,Za,ts,es,as;const t=(e,a=document)=>a.querySelector(e),k=(e,a=document)=>[...a.querySelectorAll(e)],Ye=t('meta[name="base-url"]'),st=Ye?Ye.content.replace(/\/+$/,"")+"/":location.origin+"/";let $t="fabric",Pe={},h={};const I=(e,a=!1)=>{const s=t("#dtr-toast");s&&(s.textContent=e,s.style.background=a?"#dc2626":"#0f172a",s.classList.remove("hidden"),clearTimeout(s._timer),s._timer=setTimeout(()=>s.classList.add("hidden"),4200))},c=e=>String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"),J=async(e,a={})=>(await fetch(st+e.replace(/^\/+/,""),a)).json(),R=(e,a=2)=>{const s=Number(e);if(!Number.isFinite(s))return 0;const l=Math.pow(10,a);return Math.round((s+Number.EPSILON)*l)/l},j=e=>{if(!e)return;const a=String(e.value||"").trim().toUpperCase();e.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),a==="PASS"?e.classList.add("dtr-rate-pass"):a==="TOLERABLE"?e.classList.add("dtr-rate-warn"):(a==="FAIL"||a==="ERROR")&&e.classList.add("dtr-rate-fail")},gt=e=>{k("[data-dtr-for]").forEach(a=>{a.hidden=a.dataset.dtrFor!==e}),["tracker","material","bonding","calculators"].forEach(a=>{var s;(s=t(`#dtr-panel-${a}`))==null||s.classList.toggle("hidden",a!==e)})};k("[data-goto-calc]").forEach(e=>{e.addEventListener("click",()=>{gt("calculators");const a=t(`#calc-card-${e.dataset.gotoCalc}`);a&&a.scrollIntoView({behavior:"smooth",block:"center"})})});const ls=async(e="all",a="")=>{const s=t("#dtr-tracker-tbody");try{s&&window.nxState&&nxState(s,"loading","Loading lab reports…",16);const l=await J(`dtr/records?slug=${encodeURIComponent(e)}&q=${encodeURIComponent(a)}`);if(!s||!l.rows)return;if(!l.rows.length){nxState(s,"empty","No lab reports match your search. Clear the filters or try another Lab Ref No.",16);return}s.innerHTML=l.rows.map(o=>{const n=String(o.overall_result||"PASS").toUpperCase(),r=n==="FAIL"?"b-err":n==="TOLERABLE"?"b-warn":"b-ok";return`
          <tr>
            <td style="white-space:nowrap">
              <button type="button" class="dtr-btn-xs dtr-btn-primary" data-open-record="${c(o.form_slug)}" data-source-id="${c(o.source_id)}" data-labref="${c(o.lab_ref_no)}">
                Open Form
              </button>
            </td>
            <td class="mono"><b>${c(o.lab_ref_no)}</b></td>
            <td class="mono">${c(o.tr_no||"-")}</td>
            <td><span class="dtr-tag">${c(o.form_category)}</span></td>
            <td>${c(o.material_type||"-")}</td>
            <td class="mono">${c(o.material_code||"-")}</td>
            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${c(o.material_description||"")}">${c(o.material_description||"-")}</td>
            <td>${c(o.supplier||"-")}</td>
            <td class="mono">${c(o.model_style||"-")}</td>
            <td>${c(o.color||"-")}</td>
            <td class="mono">${c(o.po_lot_no||"-")}</td>
            <td class="mono">${c(o.mtl_no||"-")}</td>
            <td>${c(o.tested_date||o.received_date||"-")}</td>
            <td>${c(o.technician||"-")}</td>
            <td>${c(o.status||"Complete")}</td>
            <td><span class="badge ${r}">${c(n)}</span></td>
          </tr>
        `}).join("")}catch(l){console.error(l),s&&window.nxState&&nxState(s,"error","The list could not be loaded. Check your connection and try again.",16)}},Ze=()=>{var s,l;const e=((s=t("#dtr-tracker-cat"))==null?void 0:s.value)||"all",a=((l=t("#dtr-tracker-search"))==null?void 0:l.value.trim())||"";ls(e,a)};(ua=t("#dtr-tracker-cat"))==null||ua.addEventListener("change",Ze),(va=t("#dtr-tracker-btn-search"))==null||va.addEventListener("click",Ze),(ma=t("#dtr-tracker-search"))==null||ma.addEventListener("keydown",e=>{var a;e.key==="Enter"&&(e.preventDefault(),(a=t("#dtr-tracker-btn-search"))==null||a.click())}),(ha=t("#dtr-tracker-tbody"))==null||ha.addEventListener("click",async e=>{const a=e.target.closest("[data-open-record]");if(!a)return;const s=a.dataset.openRecord,l=a.dataset.sourceId;if(s==="pull")gt("bonding"),Me("pull"),await ds(l);else if(s==="bonding")gt("bonding"),Me("bonding"),await Ve(l),await Be(l);else{gt("material");const o=t("#dtr-mat-form-slug");o&&(o.value=s),$t=s,await we(s,l),await sa(s,l)}});const Ne=(e=!0)=>{var r,d;const a=[];for(let i=1;i<=6;i++){const m=parseFloat(((r=t(`#mf_Thickness${i}`))==null?void 0:r.value)??"");Number.isFinite(m)&&m>0&&a.push(m)}if(!a.length)return;const s=Math.min(...a),l=Math.max(...a),o=R(a.reduce((i,m)=>i+m,0)/a.length,2);t("#mf_TMin")&&(t("#mf_TMin").value=s),t("#mf_TMax")&&(t("#mf_TMax").value=l),t("#mf_TAve")&&(t("#mf_TAve").value=o);const n=parseFloat(((d=t("#mf_T_S"))==null?void 0:d.value)??"");Number.isFinite(n)&&n>0&&t("#mf_TRating")&&(t("#mf_TRating").value=o>=n?"PASS":"FAIL",j(t("#mf_TRating"))),e&&Ce()},Tt=()=>{var r,d,i,m;const e=[1,2,3].map(u=>{var v;return parseFloat(((v=t(`#mf_Weight${u}`))==null?void 0:v.value)??"")}).filter(u=>Number.isFinite(u)&&u>0);if(e.length>0&&t("#mf_WAve")){const u=R(e.reduce((x,L)=>x+L,0)/e.length,2);t("#mf_WAve").value=u;const v=parseFloat(((r=t("#mf_Weight_S"))==null?void 0:r.value)??"");Number.isFinite(v)&&v>0&&t("#mf_WRating")&&(t("#mf_WRating").value=u>=v?"PASS":"FAIL",j(t("#mf_WRating")))}const a=[1,2,3].map(u=>{var v;return parseFloat(((v=t(`#mf_SG${u}`))==null?void 0:v.value)??"")}).filter(u=>Number.isFinite(u)&&u>0);if(a.length>0&&t("#mf_SG_Ave")){const u=R(a.reduce((x,L)=>x+L,0)/a.length,2);t("#mf_SG_Ave").value=u;const v=parseFloat(((d=t("#mf_SG_Std"))==null?void 0:d.value)??"");Number.isFinite(v)&&v>0&&t("#mf_SG_Rate")&&(t("#mf_SG_Rate").value=Math.abs(u-v)<=.02?"PASS":"FAIL",j(t("#mf_SG_Rate")))}const s=[1,2,3].map(u=>{var v;return parseFloat(((v=t(`#mf_DIN_${u}`))==null?void 0:v.value)??"")}).filter(u=>Number.isFinite(u)&&u>0);s.length>0&&t("#mf_DIN_Rate")&&(t("#mf_DIN_Rate").value=s.some(u=>u>150)?"FAIL":"PASS",j(t("#mf_DIN_Rate")));const l=[1,2].map(u=>{var v;return parseFloat(((v=t(`#mf_RossFlex_${u}`))==null?void 0:v.value)??"")}).filter(u=>Number.isFinite(u)&&u>0);l.length>0&&t("#mf_RossFlex_Rate")&&(t("#mf_RossFlex_Rate").value=l.some(u=>u>50)?"FAIL":"PASS",j(t("#mf_RossFlex_Rate")));const o=parseFloat(((i=t("#mf_ShrinkageL"))==null?void 0:i.value)??""),n=parseFloat(((m=t("#mf_Shrinkage_S"))==null?void 0:m.value)??"");Number.isFinite(o)&&Number.isFinite(n)&&n>0&&t("#mf_ShrinkageLRating")&&(t("#mf_ShrinkageLRating").value=Math.abs(o)<=Math.abs(n)?"PASS":"FAIL",j(t("#mf_ShrinkageLRating")))},ta={TensileL:{inputs:["mf_TensileL1","mf_TensileL2","mf_TensileL3"],ave:"mf_TensileLAve",std:"mf_Tensile_L",rate:"mf_TensileLRating",dec:2},TensileW:{inputs:["mf_TensileW1","mf_TensileW2","mf_TensileW3"],ave:"mf_TensileWAve",std:"mf_Tensile_W",rate:"mf_TensileWRating",dec:2},ElongationL:{inputs:["mf_ElongationL1","mf_ElongationL2","mf_ElongationL3"],ave:"mf_ElongationLAve",std:"mf_Elongation_L",rate:"mf_ElongationLRating",dec:2},ElongationW:{inputs:["mf_ElongationW1","mf_ElongationW2","mf_ElongationW3"],ave:"mf_ElongationWAve",std:"mf_Elongation_W",rate:"mf_ElongationWRating",dec:2},TrouserTearL:{inputs:["mf_TrouserTearL1","mf_TrouserTearL2","mf_TrouserTearL3"],ave:"mf_TrouserTearLAve",std:"mf_TrouserTear_L",rate:"mf_TrouserTearLRating",dec:2},TrouserTearW:{inputs:["mf_TrouserTearW1","mf_TrouserTearW2","mf_TrouserTearW3"],ave:"mf_TrouserTearWAve",std:"mf_TrouserTear_W",rate:"mf_TrouserTearWRating",dec:2},StitchTearL:{inputs:["mf_StitchTearL1","mf_StitchTearL2","mf_StitchTearL3"],ave:"mf_StitchTearLAve",std:"mf_StitchTear_L",rate:"mf_StitchTearLRating",dec:2},StitchTearW:{inputs:["mf_StitchTearW1","mf_StitchTearW2","mf_StitchTearW3"],ave:"mf_StitchTearWAve",std:"mf_StitchTear_W",rate:"mf_StitchTearWRating",dec:2},MullenBurst:{inputs:["mf_MullenBurst1","mf_MullenBurst2","mf_MullenBurst3"],ave:"mf_Text3977",std:"mf_MullenBurst",rate:"mf_MullenBurstRating",dec:4},DieC:{inputs:["mf_DieC_1","mf_DieC_2","mf_DieC_3"],ave:"mf_DieC_Ave",std:"mf_DieC_Std",rate:"mf_DieC_Rate",dec:2}},ea=e=>{const a=ta[e];if(!a)return;const s=a.inputs.map(i=>{var m;return parseFloat(((m=t(`#${i}`))==null?void 0:m.value)??"")}).filter(i=>Number.isFinite(i)),l=t(`#${a.ave}`),o=t(`#${a.std}`),n=t(`#${a.rate}`);if(!s.length)return;const r=R(s.reduce((i,m)=>i+m,0)/s.length,a.dec);l&&(l.value=r);const d=parseFloat((o==null?void 0:o.value)??"");Number.isFinite(d)&&d>0&&n&&(n.value=r>=d?"PASS":"FAIL",j(n))},Le=()=>{Object.keys(ta).forEach(ea)},De={CTP:{fibers:["mf_CtPWool","mf_CtPAcrylic","mf_CtPPolyester","mf_CtPNylon","mf_CtPCotton","mf_CtPAcetate"],std:"mf_CTP",rate:"mf_CTPRating"},CTPB:{fibers:["mf_CtPBWool","mf_CtPBAcrylic","mf_CtPBPolyester","mf_CtPBNylon","mf_CtPBCotton","mf_CtPBAcetate"],std:"mf_CtPB_S",rate:"mf_CTPB_Rating"},CTW:{fibers:["mf_CtWWool","mf_CtWAcrylic","mf_CtWPolyester","mf_CtWNylon","mf_CtWCotton","mf_CtWAcetate"],std:"mf_CTW",rate:"mf_CTWRating"},CTWB:{fibers:["mf_CtWBackSideWool","mf_CtWBackSideAcrylic","mf_CtWBackSidePolyester","mf_CtWBackSideNylon","mf_CtWBackSideCotton","mf_CtWBackSideAcetate"],std:"mf_CtWB_S",rate:"mf_CtWB_Rating"}},We=e=>{var n;const a=De[e];if(!a)return;const s=parseFloat(((n=t(`#${a.std}`))==null?void 0:n.value)??""),l=t(`#${a.rate}`);if(!Number.isFinite(s)||s<=0||!l)return;const o=a.fibers.map(r=>{var d;return parseFloat(((d=t(`#${r}`))==null?void 0:d.value)??"")});o.some(r=>!Number.isFinite(r))||(l.value=o.some(r=>r<s)?"FAIL":"PASS",j(l))},Ie=()=>{var f,g,N,G,E,et,at,Y,y,C,T,M,F,D,U;const e=parseFloat(((f=t("#mf_CtCDry"))==null?void 0:f.value)??""),a=parseFloat(((g=t("#mf_CtCDry_S"))==null?void 0:g.value)??"");Number.isFinite(e)&&Number.isFinite(a)&&a>0&&t("#mf_CTCDRYRating")&&(t("#mf_CTCDRYRating").value=e>=a?"PASS":"FAIL",j(t("#mf_CTCDRYRating")));const s=parseFloat(((N=t("#mf_CtCWet"))==null?void 0:N.value)??""),l=parseFloat(((G=t("#mf_CtCWet_S"))==null?void 0:G.value)??"");Number.isFinite(s)&&Number.isFinite(l)&&l>0&&t("#mf_CTCWETRating")&&(t("#mf_CTCWETRating").value=s>=l?"PASS":"FAIL",j(t("#mf_CTCWETRating")));const o=parseFloat(((E=t("#mf_UV"))==null?void 0:E.value)??""),n=parseFloat(((et=t("#mf_UV_S"))==null?void 0:et.value)??"");Number.isFinite(o)&&Number.isFinite(n)&&n>0&&t("#mf_UVRating")&&(t("#mf_UVRating").value=o>=n?"PASS":"FAIL",j(t("#mf_UVRating")));const r=parseFloat(((at=t("#mf_HeatAging1"))==null?void 0:at.value)??""),d=parseFloat(((Y=t("#mf_HeatAging2"))==null?void 0:Y.value)??""),i=parseFloat(((y=t("#mf_HeatAging_S"))==null?void 0:y.value)??"");Number.isFinite(r)&&Number.isFinite(d)&&Number.isFinite(i)&&i>0&&t("#mf_HeatAgingRating")&&(t("#mf_HeatAgingRating").value=r<i||d<i?"FAIL":"PASS",j(t("#mf_HeatAgingRating")));const m=parseFloat(((C=t("#mf_Hydrolysis1"))==null?void 0:C.value)??""),u=parseFloat(((T=t("#mf_Hydrolysis2"))==null?void 0:T.value)??""),v=parseFloat(((M=t("#mf_Hydrolysis_S"))==null?void 0:M.value)??"");Number.isFinite(m)&&Number.isFinite(u)&&Number.isFinite(v)&&v>0&&t("#mf_HydrolysisRating")&&(t("#mf_HydrolysisRating").value=m<v||u<v?"FAIL":"PASS",j(t("#mf_HydrolysisRating")));const x=parseFloat(((F=t("#mf_PVC_1"))==null?void 0:F.value)??""),L=parseFloat(((D=t("#mf_PVC_2"))==null?void 0:D.value)??""),P=parseFloat(((U=t("#mf_PVC_S"))==null?void 0:U.value)??"");Number.isFinite(x)&&Number.isFinite(L)&&Number.isFinite(P)&&P>0&&t("#mf_PVC_Rating")&&(t("#mf_PVC_Rating").value=x<P||L<P?"FAIL":"PASS",j(t("#mf_PVC_Rating")))},ot=()=>{var n;let e=!1,a=!1,s=!1;k("#dtr-material-form .dtr-rate-inp").forEach(r=>{j(r);const d=String(r.value||"").trim().toUpperCase();d==="FAIL"?a=!0:d==="TOLERABLE"?s=!0:d==="PASS"&&(e=!0)});let l="PASS";a?l="FAIL":s?l="TOLERABLE":e?l="PASS":(n=t("#mf_Remarks"))!=null&&n.value&&(l=String(t("#mf_Remarks").value).toUpperCase()),t("#mf_Remarks")&&(t("#mf_Remarks").value=l);const o=t("#dtr-stamp-badge");o&&(o.textContent=l,o.className="dtr-stamp "+(l==="FAIL"?"dtr-stamp-fail":l==="TOLERABLE"?"dtr-stamp-warn":"dtr-stamp-pass"))},Ce=async()=>{var s,l;const e=((s=t("#mf_MaterialType"))==null?void 0:s.value.trim())||"",a=parseFloat(((l=t("#mf_TAve"))==null?void 0:l.value)??"0")||0;if(e)try{const o=await J(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`);o&&o.mapped&&Object.keys(o.mapped).forEach(n=>{const r=o.mapped[n],d=t(`#mf_${n}`)||t(`[name="${n}"]`,t("#dtr-material-form"));d&&(d.value=r)}),Tt(),Le(),Object.keys(De).forEach(We),Ie(),ot()}catch(o){console.warn("[DTR] applyStandardValuesSmart error:",o)}},rs=async()=>{Ne(!1),await Ce(),I("✓ Executed ApplyStandardValuesSmart + Thickness + Directional Averages + 6-Point Colorfastness + EvaluateRatings.")},we=async(e,a=null)=>{const s=t("#dtr-mat-record-select");try{s&&(s.innerHTML='<option value="">Loading records…</option>');const l=await J(`dtr/records?slug=${encodeURIComponent(e)}`);if(!s||!l.rows)return;if(!l.rows.length){s.innerHTML='<option value="">No records yet - press “+ New Record”</option>';return}s.innerHTML='<option value="">- New report (not saved yet) -</option>'+l.rows.map(o=>`
        <option value="${c(o.source_id)}" ${String(o.source_id)===String(a)?"selected":""}>
          ${c(o.lab_ref_no)} · ${c(o.material_type||o.form_category)} · ${c(o.model_style||o.material_code||"")} [${c(o.overall_result||"Pass")}]
        </option>
      `).join("")}catch(l){console.error(l),s&&(s.innerHTML='<option value="">Could not load records - reload the page</option>'),window.nxToast&&nxToast("The record list could not be loaded.","error")}},ns=e=>{const a=t("#dtr-raw-cols-container"),s=t("#dtr-raw-col-count");if(!a)return;const l=Object.keys(e||{});s&&(s.textContent=l.length),a.innerHTML=l.map(o=>`
      <div class="dtr-field">
        <label title="${c(o)}"><code>${c(o)}</code></label>
        <input type="text" data-raw-col="${c(o)}" value="${c(e[o]??"")}" ${o==="ID"?'readonly class="dtr-ro"':""}>
      </div>
    `).join("")},aa=(e,a={})=>{Pe=e||{},t("#dtr-active-form-label")&&a.label&&(t("#dtr-active-form-label").textContent=`${a.label}`),t("#dtr-active-labref-title")&&(t("#dtr-active-labref-title").textContent=`${e.LabRefNo||"New Report"} — ${e["Material Description"]||e.MaterialDescription||e.MaterialType||""}`),k("#dtr-material-form input, #dtr-material-form select").forEach(s=>{s.name&&(s.value="")}),Object.keys(e).forEach(s=>{const l=e[s]??"",o=t(`#mf_${s.replace(/[^a-zA-Z0-9_]/g,"")}`);o&&(o.value=l);const n=t(`[name="${CSS.escape(s)}"]`,t("#dtr-material-form"));n&&(n.value=l)}),t("#mf_MaterialDescription")&&!t("#mf_MaterialDescription").value&&(t("#mf_MaterialDescription").value=e["Material Description"]||e.MaterialDescription||""),t("#mf_SupplierFactory")&&!t("#mf_SupplierFactory").value&&(t("#mf_SupplierFactory").value=e["Supplier/Factory"]||e.Supplier||e.SupplierFactory||""),t("#mf_POLotNo")&&!t("#mf_POLotNo").value&&(t("#mf_POLotNo").value=e["PO/Lot No"]||e.POLotNo||""),t("#mf_ModelNoStyle")&&!t("#mf_ModelNoStyle").value&&(t("#mf_ModelNoStyle").value=e["Model No/Style"]||e.ModelNoStyle||""),t("#mf_POQuantity")&&!t("#mf_POQuantity").value&&(t("#mf_POQuantity").value=e["PO Quantity"]||e.POQuantity||""),t("#mf_TestedBy")&&!t("#mf_TestedBy").value&&(t("#mf_TestedBy").value=e["Tested by"]||e.TestedBy||e.Technician||""),ns(e),Ne(!1),Tt(),Le(),Object.keys(De).forEach(We),Ie(),ot()},sa=async(e,a=0)=>{try{const s=await J(`dtr/record?slug=${encodeURIComponent(e)}&source_id=${encodeURIComponent(a)}`);s&&s.ok&&aa(s.record||{},s.config||{})}catch(s){console.error(s)}};(ba=t("#mf_LabRefNo"))==null||ba.addEventListener("change",()=>{var s;const e=(((s=t("#mf_LabRefNo"))==null?void 0:s.value)||"").toUpperCase().trim(),a=t("#mf_MaterialType");!e||!a||(e.includes("FBU")||e.includes("FBL")?a.value||(a.value="Canvas"):e.includes("LEU")||e.includes("LEL")?a.value||(a.value="Suede"):e.includes("RB")?a.value="Rubber":e.includes("IB")?a.value="Inner Box":e.includes("OB")?a.value="Outer Box":e.includes("FML")?a.value="Sockliner - Latex":e.includes("FM")?a.value="PU Foam":e.includes("SKL")?a.value="Sockliner - EVA":e.includes("LC")?a.value="Shoe lace":e.includes("TH")?a.value="Thread":e.includes("EY")?a.value="Eyelet":e.includes("GR")?a.value="Grommet":e.includes("ZP")&&(a.value="Zipper"),Ce())});const Re=async()=>{var l,o,n;const e=((l=t("#mf_MaterialCode"))==null?void 0:l.value.trim())||"",a=((o=t("#mf_POLotNo"))==null?void 0:o.value.trim())||"",s=((n=t("#mf_MTL"))==null?void 0:n.value.trim())||"";if(!e&&!a&&!s){I("Enter a Material Code, PO/Lot No, or MTL # first.",!0);return}try{const r=await J(`dtr/lookup?material_code=${encodeURIComponent(e)}&po_lot_no=${encodeURIComponent(a)}&mtl_no=${encodeURIComponent(s)}`);let d=[];if(r.material&&(t("#mf_SupplierFactory")&&(t("#mf_SupplierFactory").value=r.material.Supplier||""),t("#mf_MaterialDescription")&&(t("#mf_MaterialDescription").value=r.material.MaterialName||""),d.push(`MaterialList (${r.material.MaterialCode})`)),r.shoe&&(t("#mf_ModelNoStyle")&&(t("#mf_ModelNoStyle").value=r.shoe.StyleNo||""),t("#mf_Color")&&(t("#mf_Color").value=r.shoe.Colorway||""),t("#mf_MTL")&&r.shoe.MTL&&(t("#mf_MTL").value=r.shoe.MTL),d.push(`ShoeList (${r.shoe.StyleNo})`)),r.dsr&&r.dsr.length>0){const i=r.dsr[0];t("#mf_StyleNumber_DSR")&&(t("#mf_StyleNumber_DSR").value=i.StyleNumber||""),t("#mf_StyleDescription_DSR")&&(t("#mf_StyleDescription_DSR").value=i.StyleDescription||""),t("#mf_Colorway_DSR")&&(t("#mf_Colorway_DSR").value=i.Colorway||""),t("#mf_ComponentorLocation_DSR")&&(t("#mf_ComponentorLocation_DSR").value=i.ComponentorLocation||""),t("#mf_VFMaterialCode_DSR")&&(t("#mf_VFMaterialCode_DSR").value=i.VFMaterialCode||""),t("#mf_InternalName_DSR")&&(t("#mf_InternalName_DSR").value=i["Internal NameVans1"]||i.Material||""),t("#mf_ColorName_DSR")&&(t("#mf_ColorName_DSR").value=i.ColorName||i.Color||""),d.push(`tblDSR (${r.dsr.length} component rows)`)}d.length?I(`✓ Auto-Lookup populated from: ${d.join(" · ")}`):I("No exact match found in MaterialList / ShoeList / tblDSR.",!0)}catch(r){console.error(r)}};(_a=t("#btn-dtr-lookup-all"))==null||_a.addEventListener("click",Re),(ga=t("#mf_MaterialCode"))==null||ga.addEventListener("change",Re),(Ta=t("#mf_POLotNo"))==null||Ta.addEventListener("change",Re),(ya=t("#mf_MTL"))==null||ya.addEventListener("change",Re),(Sa=t("#mf_MaterialType"))==null||Sa.addEventListener("change",Ce),k(".dtr-calc-thick").forEach(e=>{e.addEventListener("input",()=>Ne(!0))}),k(".dtr-calc-weight, .dtr-calc-sg, .dtr-calc-din, .dtr-calc-ross, .dtr-calc-shrink").forEach(e=>{e.addEventListener("input",()=>{Tt(),ot()})}),k(".dtr-dir-inp, .dtr-std-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset.dirGroup?ea(e.dataset.dirGroup):Le(),ot()})}),k(".dtr-6pt-inp").forEach(e=>{e.addEventListener("input",()=>{e.dataset["6pt"]&&We(e.dataset["6pt"]),ot()})}),k(".dtr-crock-inp").forEach(e=>{e.addEventListener("input",()=>{Ie(),ot()})}),k(".dtr-rate-inp").forEach(e=>{e.addEventListener("change",ot),e.addEventListener("input",ot)}),($a=t("#dtr-mat-form-slug"))==null||$a.addEventListener("change",async e=>{$t=e.target.value,Ue(!1),await we($t)}),(Fa=t("#dtr-mat-record-select"))==null||Fa.addEventListener("change",async e=>{const a=e.target.value;a&&await sa($t,a)});const Ue=(e=!0)=>{var l,o;const a=new Date().toISOString().slice(0,10);aa({ID:"",LabRefNo:`DTP${a.replace(/-/g,"")}${$t.toUpperCase().slice(0,3)}-1`},{label:((o=(l=t("#dtr-mat-form-slug"))==null?void 0:l.selectedOptions[0])==null?void 0:o.textContent.trim())||""}),k("#dtr-material-form textarea").forEach(n=>{n.value=""}),k("#dtr-material-form input[type=radio], #dtr-material-form input[type=checkbox]").forEach(n=>{n.checked=!1});const s=t("#dtr-mat-record-select");s&&(s.value=""),e&&I("Initialized new blank test report record.")};(La=t("#btn-dtr-new"))==null||La.addEventListener("click",()=>Ue()),(Ca=t("#btn-dtr-review"))==null||Ca.addEventListener("click",()=>{const e=prompt("Enter Reviewer Name:","Faye Ann Elaysa");if(!e)return;const a=t('[data-raw-col="AuthorizedBy"]');a&&(a.value=e);const s=t('[data-raw-col="AuthorizationStatus"]');s&&(s.value="Reviewed"),I(`✓ Record stamped Reviewed by ${e}`)}),(wa=t("#btn-dtr-refresh-calc"))==null||wa.addEventListener("click",rs),(Ra=t("#btn-dtr-load-machine"))==null||Ra.addEventListener("click",async()=>{var a;const e=((a=t("#mf_LabRefNo"))==null?void 0:a.value.trim())||"";if(!e){I("Enter a LabRefNo first before loading machine results.",!0);return}try{const s=await J("dtr/load-machine",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:$t,lab_ref_no:e})});s&&s.loadedFields&&(Object.keys(s.loadedFields).forEach(l=>{const o=s.loadedFields[l],n=t(`#mf_${l}`)||t(`[name="${CSS.escape(l)}"]`,t("#dtr-material-form"));n&&(n.value=o);const r=t(`[data-raw-col="${CSS.escape(l)}"]`);r&&(r.value=o)}),Tt(),Le(),ot(),t("#dtr-machine-status")&&(t("#dtr-machine-status").textContent=s.message||"Machine data loaded."),I(`${s.message}`))}catch(s){I("Machine loader error: "+(s.message||""),!0)}}),(xa=t("#btn-dtr-save"))==null||xa.addEventListener("click",async()=>{const e=Object.assign({},Pe);k("#dtr-raw-cols-container [data-raw-col]").forEach(a=>{e[a.dataset.rawCol]=a.value}),k("#dtr-material-form [name]").forEach(a=>{a.name&&(e[a.name]=a.value)});try{const a=await J("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:$t,fields:e})});a&&a.ok?(t("#mf_ID")&&(t("#mf_ID").value=a.recordId),I(`✓ ${a.message} (Ready in InterLink Sync ComboBox!)`),await we($t,a.recordId)):I(a.error||"Save failed.",!0)}catch{I("Failed to save record.",!0)}}),(Aa=t("#btn-toggle-raw-cols"))==null||Aa.addEventListener("click",()=>{var e;(e=t("#dtr-raw-cols-container"))==null||e.classList.toggle("hidden")});const oa={TODDLER:14,KIDS:16,CHILDREN:24,ADULT:35},is={TODDLER:"2.0",KIDS:"2.0",CHILDREN:"2.5",ADULT:"3.0"},xe=1,Ae=e=>Math.round(e*.8*100)/100,He=(e,a)=>!Number.isFinite(e)||e<=0?null:e>=a?"PASS":e>=Ae(a)?"TOLERABLE":"FAIL",Gt=()=>{var i,m;const e=(((i=t("#bf_AgeCategory"))==null?void 0:i.value)||"ADULT").toUpperCase(),a=oa[e]||35,s=parseFloat(((m=t("#bf_STD"))==null?void 0:m.value)??"2.5")||2.5;k("#dtr-bonding-points-tbody [data-spot-row]").forEach(u=>{const v=Number(u.dataset.spotRow);u.style.opacity=v<=a?"1":"0.45"});const l=(u,v,x)=>{const L=[];let P=!1,f=!1;for(let g=1;g<=v;g++){const N=t(`#bp_${u}${g}`);if(!N)continue;const G=parseFloat(N.value??"");if(N.classList.remove("dtr-rate-pass","dtr-rate-warn","dtr-rate-fail"),Number.isFinite(G)&&G>0){L.push(G);const E=He(G,x);E==="FAIL"?(P=!0,N.classList.add("dtr-rate-fail")):E==="TOLERABLE"?(f=!0,N.classList.add("dtr-rate-warn")):N.classList.add("dtr-rate-pass")}}return L.length?{min:R(Math.min(...L),2),max:R(Math.max(...L),2),ave:R(L.reduce((g,N)=>g+N,0)/L.length,2),res:P?"FAIL":f?"TOLERABLE":"PASS"}:{min:"",max:"",ave:"",res:""}},o=l("UTFpoint",a,s),n=l("FTOpoint",a,s),r=l("FiTO",13,xe);t("#bp_UTFMin")&&(t("#bp_UTFMin").value=o.min),t("#bp_UTFMax")&&(t("#bp_UTFMax").value=o.max),t("#bp_UTFAverage")&&(t("#bp_UTFAverage").value=o.ave),t("#bp_UTF_Result")&&(t("#bp_UTF_Result").value=o.res,j(t("#bp_UTF_Result"))),t("#bp_FTOMin")&&(t("#bp_FTOMin").value=n.min),t("#bp_FTOMax")&&(t("#bp_FTOMax").value=n.max),t("#bp_FTOAverage")&&(t("#bp_FTOAverage").value=n.ave),t("#bp_FTO_Result")&&(t("#bp_FTO_Result").value=n.res,j(t("#bp_FTO_Result"))),t("#bp_FiTOMin")&&(t("#bp_FiTOMin").value=r.min),t("#bp_FiTOMax")&&(t("#bp_FiTOMax").value=r.max),t("#bp_FiTOAverage")&&(t("#bp_FiTOAverage").value=r.ave),t("#bp_FiTO_Result")&&(t("#bp_FiTO_Result").value=r.res,j(t("#bp_FiTO_Result")));const d=[o.res,n.res,r.res].filter(Boolean);t("#bf_Result")&&(d.length&&(t("#bf_Result").value=d.includes("FAIL")?"FAIL":d.includes("TOLERABLE")?"TOLERABLE":"PASS"),j(t("#bf_Result"))),ke()},Ve=async(e=null)=>{const a=t("#dtr-bonding-record-select");try{a&&(a.innerHTML='<option value="">Loading reports…</option>');const s=await J("dtr/records?slug=bonding");if(!a||!s.rows)return;if(!s.rows.length){a.innerHTML='<option value="">No bonding reports yet</option>';return}a.innerHTML='<option value="">- New report (not saved yet) -</option>'+s.rows.map(l=>`
        <option value="${c(l.source_id)}" ${String(l.source_id)===String(e)?"selected":""}>
          ${c(l.lab_ref_no)} · ${c(l.model_style||"")} · ${c(l.color||"")} [${c(l.overall_result||"Pass")}]
        </option>
      `).join("")}catch(s){console.error(s),a&&(a.innerHTML='<option value="">Could not load reports - reload the page</option>'),window.nxToast&&nxToast("The bonding report list could not be loaded.","error")}},Be=async(e=0,a="")=>{try{const s=a?`lab_ref_no=${encodeURIComponent(a)}`:`source_id=${encodeURIComponent(e)}`,l=await J(`dtr/record?slug=bonding&${s}`);if(!l||!l.ok||a&&!Object.keys(l.record||{}).length)return;const o=l.record||{},n=l.bondingPoints||{};h=n,Object.keys(o).forEach(r=>{const d=t(`#bf_${r}`);d&&(d.value=o[r]??"")}),k('#dtr-bonding-form [name^="cf_"]').forEach(r=>{r.value=(l.customValues||{})[r.name]??""});for(let r=1;r<=35;r++)t(`#bp_UTFpoint${r}`)&&(t(`#bp_UTFpoint${r}`).value=n[`UTFpoint${r}`]??""),t(`#bp_FTOpoint${r}`)&&(t(`#bp_FTOpoint${r}`).value=n[`FTOpoint${r}`]??""),r<=13&&t(`#bp_FiTO${r}`)&&(t(`#bp_FiTO${r}`).value=n[`FiTO${r}`]??"");Gt()}catch(s){console.error(s)}};(Ea=t("#dtr-bonding-record-select"))==null||Ea.addEventListener("change",e=>{e.target.value&&Be(e.target.value)});let je=[];const cs=async()=>{try{const e=await J("dtr/bonding-labrefs");je=e&&e.labRefs||[];const a=t("#bf_LabRefNo_list");a&&(a.innerHTML=je.map(s=>`<option value="${c(s)}"></option>`).join(""))}catch(e){console.error(e)}};(Ma=t("#bf_LabRefNo"))==null||Ma.addEventListener("change",e=>{const a=e.target.value.trim();a&&je.includes(a)&&Be(0,a)}),(Oa=t("#bf_LabRefNo"))==null||Oa.addEventListener("focus",e=>{e.target.dataset.keep=e.target.value,e.target.value=""}),(ka=t("#bf_LabRefNo"))==null||ka.addEventListener("blur",e=>{e.target.value||(e.target.value=e.target.dataset.keep||"")}),cs(),(Pa=t("#bf_AgeCategory"))==null||Pa.addEventListener("change",ke),(Na=t("#bf_AgeCategory"))==null||Na.addEventListener("change",e=>{const a=is[(e.target.value||"").toUpperCase()];a&&t("#bf_STD")&&(t("#bf_STD").value=a),Gt()}),(Da=t("#bf_STD"))==null||Da.addEventListener("change",Gt),(Wa=t("#bf_Side"))==null||Wa.addEventListener("change",ke),ke(),k("#dtr-bonding-points-tbody .dtr-bp-inp").forEach(e=>{e.addEventListener("input",Gt)}),(Ia=t("#btn-bond-recalc"))==null||Ia.addEventListener("click",()=>{if(ze()){Ee(),I("✓ Recalculated Maximum and Rating of every pull test row.");return}Gt(),I("✓ Recalculated UTF, FTO, and FiTO Min/Max/Ave & Spot Tolerances.")});const la=(e=!0)=>{const a=new Date().toISOString().slice(0,10);k("#dtr-bonding-form input, #dtr-bonding-form select, #dtr-bonding-form textarea").forEach(l=>{l.type==="radio"||l.type==="checkbox"?l.checked=!1:l.value=""}),h={},t("#bf_LabRefNo")&&(t("#bf_LabRefNo").value=`DTP${a.replace(/-/g,"")}SHB01-R`);const s=t("#dtr-bonding-record-select");s&&(s.value=""),Gt(),e&&I("Initialized new Whole Shoe Bonding Test Report.")};(Ua=t("#btn-bond-save"))==null||Ua.addEventListener("click",async()=>{var s,l,o,n,r,d,i,m,u,v,x,L,P;if(ze())return fs();const e={};k("#dtr-bonding-form [name]").forEach(f=>{f.name&&(e[f.name]=f.value)});const a={UTFMin:((s=t("#bp_UTFMin"))==null?void 0:s.value)||null,UTFMax:((l=t("#bp_UTFMax"))==null?void 0:l.value)||null,UTFAverage:((o=t("#bp_UTFAverage"))==null?void 0:o.value)||null,UTF_Result:((n=t("#bp_UTF_Result"))==null?void 0:n.value)||null,FTOMin:((r=t("#bp_FTOMin"))==null?void 0:r.value)||null,FTOMax:((d=t("#bp_FTOMax"))==null?void 0:d.value)||null,FTOAverage:((i=t("#bp_FTOAverage"))==null?void 0:i.value)||null,FTO_Result:((m=t("#bp_FTO_Result"))==null?void 0:m.value)||null,FiTOMin:((u=t("#bp_FiTOMin"))==null?void 0:u.value)||null,FiTOMax:((v=t("#bp_FiTOMax"))==null?void 0:v.value)||null,FiTOAverage:((x=t("#bp_FiTOAverage"))==null?void 0:x.value)||null,FiTO_Result:((L=t("#bp_FiTO_Result"))==null?void 0:L.value)||null,Result:((P=t("#bf_Result"))==null?void 0:P.value)||null};k("#dtr-bonding-points-tbody [data-bp-col]").forEach(f=>{a[f.dataset.bpCol]=f.value});try{const f=await J("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"bonding",fields:e,bondingPoints:a})});f&&f.ok&&(t("#bf_BondingTestID")&&(t("#bf_BondingTestID").value=f.recordId),I(`✓ ${f.message}`),await Ve(f.recordId))}catch{I("Error saving Bonding Report.",!0)}});const Ge={left:6,right:6,other:4},ze=()=>{var e;return(((e=t("#dtr-report-kind"))==null?void 0:e.value)||"bonding")==="pull"},qe=()=>k("#dtr-pull-form [data-pull]"),Wt=(e,a,s)=>t(`#dtr-pull-form [data-pull="${e}.${a}.${s}"]`),ra=()=>{[["pf_MaterialSource","pf_ms_radio"],["pf_TechSlot","pf_tech_radio"]].forEach(([e,a])=>{var l;const s=((l=t(`#${e}`))==null?void 0:l.value)||"";k(`#dtr-pull-form input[name="${a}"]`).forEach(o=>{o.checked=!!s&&o.value===s})})};(Ha=t("#dtr-pull-form"))==null||Ha.addEventListener("change",e=>{const a=e.target;if(a.type!=="radio")return;const s=a.name==="pf_ms_radio"?t("#pf_MaterialSource"):a.name==="pf_tech_radio"?t("#pf_TechSlot"):null;s&&(s.value=a.value)});const Ee=()=>{let e=0,a=0;Object.keys(Ge).forEach(l=>{for(let o=0;o<Ge[l];o++){const n=[Wt(l,o,"lat"),Wt(l,o,"med")].map(u=>parseFloat((u==null?void 0:u.value)??"")).filter(Number.isFinite);Wt(l,o,"max").value=n.length?R(Math.max(...n),2):"";const r=parseFloat(Wt(l,o,"std").value),d=/max/i.test(Wt(l,o,"tol").value);let i="";if(n.length&&Number.isFinite(r)){const u=d?Math.max(...n)<=r:Math.min(...n)>=r;i=u?"PASS":"FAIL",e++,u||a++}const m=Wt(l,o,"rating");m.value=i,j(m)}});const s=t("#pf_Result");s&&(e&&(s.value=a?"FAIL":"PASS"),j(s))};(Va=t("#dtr-pull-form"))==null||Va.addEventListener("input",e=>{e.target.matches("[data-pull]")&&Ee()});const na=()=>{k("#dtr-pull-form [name]").forEach(e=>{e.type==="radio"?e.checked=!1:e.value=""}),qe().forEach(e=>{e.value=e.defaultValue}),ra()},ia=(e=!0)=>{const a=new Date().toISOString().slice(0,10);na(),t("#pf_LabRefNo").value=`DTP${a.replace(/-/g,"")}PL01-R`,Ee(),e&&I("Initialized new Pull Footwear Test Report.")},ds=async e=>{try{const a=await J(`dtr/record?slug=pull&source_id=${encodeURIComponent(e)}`);if(!a||!a.ok)return;const s=a.record||{};na(),Object.keys(s).forEach(o=>{const n=t(`#pf_${o}`);n&&(n.value=s[o]??"")});let l=null;try{l=JSON.parse(s.PullData||"null")}catch{l=null}qe().forEach(o=>{var m,u;const[n,r,d]=o.dataset.pull.split("."),i=(u=(m=l==null?void 0:l[n])==null?void 0:m[r])==null?void 0:u[d];o.value=i??o.defaultValue}),ra(),Ee()}catch(a){console.error(a)}},fs=async()=>{const e={};k("#dtr-pull-form [name]").forEach(s=>{s.type!=="radio"&&(e[s.name]=s.value)});const a={left:[],right:[],other:[]};qe().forEach(s=>{const[l,o,n]=s.dataset.pull.split(".");(a[l][o]=a[l][o]||{})[n]=s.value}),e.PullData=JSON.stringify(a);try{const s=await J("dtr/save",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({slug:"pull",fields:e})});s&&s.ok?(t("#pf_ID").value=s.recordId,I(`✓ ${s.message}`)):I(s&&s.error||"Save failed.",!0)}catch{I("Error saving Pull Test Report.",!0)}},ps=()=>{var v,x,L,P;const e=f=>{var g;return c(((g=t(`#pf_${f}`))==null?void 0:g.value)||"")},a=(f,g,N)=>{var G;return c(((G=Wt(f,g,N))==null?void 0:G.value)||"")},s=k("#dtr-pull-form tbody tr td.vf-res-part-cell").map(f=>f.innerHTML),l=k("#dtr-pull-form .pf-sketch").map(f=>f.innerHTML),o=((v=t("#pf_MaterialSource"))==null?void 0:v.value)||"",n=((x=t("#pf_TechSlot"))==null?void 0:x.value)||"",r=(f,g,N)=>`<div>${N===f?"&#9673;":"&#9675;"} ${g}</div>`,d=(f,g,N)=>{let G="";for(let E=0;E<g;E++){const et=f==="other"?a(f,E,"item"):s[(f==="right"?6:0)+E]||"",at=f==="other"?a(f,E,"method"):"VF-B04<br>(2019/05)",Y=f==="other"?a(f,E,"unit"):"kgf",y=a(f,E,"rating");G+=`<tr>
          <td class="pf-p-l">${et}</td><td class="pf-p-c">${at}</td><td class="pf-p-c">${Y}</td>
          ${E===0?`<td rowspan="${g}" class="pf-p-sk">${N}</td>`:""}
          <td class="pf-p-c">${a(f,E,"lat")}</td><td class="pf-p-c">${a(f,E,"med")}</td><td class="pf-p-c">${a(f,E,"max")}</td>
          <td class="pf-p-c pf-p-blue">${a(f,E,"std")}</td><td class="pf-p-c pf-p-blue">${a(f,E,"tol")}</td>
          <td class="pf-p-c"><b class="${y==="FAIL"?"pf-p-fail":""}">${y}</b></td></tr>`}return G};let i="";for(let f=0;f<Ge.other;f++)i+=`<tr>
        <td class="pf-p-l">${a("other",f,"item")}</td><td class="pf-p-c">${a("other",f,"method")}</td><td class="pf-p-c">${a("other",f,"unit")}</td><td></td>
        <td class="pf-p-c">${a("other",f,"lat")}</td><td class="pf-p-c">${a("other",f,"med")}</td><td class="pf-p-c">${a("other",f,"max")}</td>
        <td class="pf-p-c pf-p-blue">${a("other",f,"std")}</td><td class="pf-p-c pf-p-blue">${a("other",f,"tol")}</td><td class="pf-p-c"><b>${a("other",f,"rating")}</b></td></tr>`;const m=((L=t("#pf_LabRefNo"))==null?void 0:L.value)||"Pull Test",u=`
      <div class="vf-report-sheet pf-print">
        <div class="pf-p-top">
          <img class="rpt-logo-vf" src="${st}assets/images/vf-logo.webp" alt="VF">
          <div class="pf-p-title"><h1>Pull Footwear Test Results</h1><h2>整鞋部件拉力测试</h2></div>
          <img class="rpt-logo-satra" src="${st}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory">
        </div>
        <div class="pf-p-ids">
          <div>Form # : Lab VF 003</div>
          <div>ETRF.No: <b>${e("ETRFNo")}</b></div>
          <div>Lab Ref.No: <b>${e("LabRefNo")}</b></div>
        </div>
        <div class="pf-p-head">
          <div>
            <div>Supplier/Factory(厂商) : <b>${e("Supplier")}</b></div>
            <div>Supplier's country(国家): <b>${e("SupplierCountry")}</b></div>
            <div>Material Description(材料):<b> ${e("MaterialDescription")}</b></div>
            <div>Model No./Style(鞋体): <b>${e("ModelNoStyle")}</b></div>
            <div>Color(颜色): <b>${e("Color")}</b></div>
            <div>P.O/Lot No(订单号码): <b>${e("POLotNo")}</b></div>
            <div>PO Quantity(订单数量): <b>${e("POQuantity")}</b></div>
            <div>Send by (提供方): <b>${e("SendBy")}</b></div>
          </div>
          <div>
            <div class="pf-p-radios">
              ${r("Prod. Material","Prod.Material (量产材料)",o)}${r("Prod. Re-test","Prod.Re-test 量产重测",o)}
              ${r("New/Development","New/Development 开发新材料",o)}${r("Trial Test","Trial Test 试胶测试",o)}
              ${r("Sales Sample","Sales Sample (销售样品)",o)}
            </div>
            <div class="pf-p-radios pf-p-techs">${["Tech 1","Tech 2","Tech 3","Tech 4"].map(f=>r(f,f,n)).join("")}</div>
            <div>Received Date(收到日期): <b>${e("ReceivedDate")}</b></div>
            <div>Tested Date(测试日期): <b>${e("TestedDate")}</b></div>
            <div>Finished Date(完成日期): <b>${e("FinishedDate")}</b></div>
            <div>Technician(技术员): <b>${e("Technician")}</b></div>
          </div>
        </div>
        <div class="pf-p-sec">Closures - Eyelet , D-ring , Hook , Webbing Pull Strength</div>
        <table class="pf-p-tbl">
          <thead>
            <tr><th rowspan="2">Test Items<br>测试项目</th><th rowspan="2">VF Test Method<br>测试方法</th><th rowspan="2">Units<br>单位</th><th rowspan="2">Test Sketch<br>测试部位</th><th colspan="2">Test Results<br>测试结果</th><th rowspan="2">Maximum</th><th rowspan="2">Standard<br>标准</th><th rowspan="2">Tolerance<br>容许度</th><th rowspan="2">Rating<br>等级</th></tr>
            <tr><th>Lateral side:</th><th>Medial side:</th></tr>
          </thead>
          <tbody>
            ${d("left",6,l[0]||"")}
            ${d("right",6,l[1]||"")}
            <tr><td colspan="10" class="pf-p-oth">Other Types of Pull Test</td></tr>
            ${i}
          </tbody>
        </table>
        <div class="pf-p-foot">
          <div>Comments 备注 : ${e("Comments")}</div>
          <div>
            <div>Reviewed by 检查: <b>${e("ReviewedBy")}</b></div>
            <div>Approved by 核准: <b>${e("ApprovedBy")}</b></div>
            <div>Overall Result: <b>${e("Result")}</b></div>
          </div>
        </div>
      </div>
    `;t("#dtr-print-modal-title").textContent=`Pull Footwear Test Report — ${m}`,t("#dtr-print-sheet-container").innerHTML=u,(P=t("#dtr-print-modal-backdrop"))==null||P.classList.remove("hidden")},Me=e=>{const a=e==="pull",s=t("#dtr-report-kind");s&&s.value!==e&&(s.value=e);const l=t("#dtr-bonding-form"),o=t("#dtr-pull-form");l&&(l.hidden=a),o&&(o.hidden=!a),k(".dtr-only-bonding").forEach(r=>{r.hidden=a});const n=t("#btn-bond-recalc-txt");n&&(n.textContent=a?"Recalculate Results":"Recalculate Spots & Result")};(Ba=t("#dtr-report-kind"))==null||Ba.addEventListener("change",e=>{Me(e.target.value),e.target.value==="pull"?ia():la()});const ca=()=>{var vt,nt,it,ct,Lt,yt,Ct,wt,It,Ut,Ht,Vt,St,mt,ht,bt,Rt,Bt,xt,At,Et,Mt,Ot,kt,Pt,Nt,Dt;const e=parseFloat(((vt=t("#calc_raw_w1"))==null?void 0:vt.value)??"0")||0,a=parseFloat(((nt=t("#calc_raw_w2"))==null?void 0:nt.value)??"0")||0,s=parseFloat(((it=t("#calc_raw_w3"))==null?void 0:it.value)??"0")||0,l=R(e/100*1e4,2),o=R(a/100*1e4,2),n=R(s/100*1e4,2),r=R((l+o+n)/3,2);t("#calc_res_w1")&&(t("#calc_res_w1").value=l),t("#calc_res_w2")&&(t("#calc_res_w2").value=o),t("#calc_res_w3")&&(t("#calc_res_w3").value=n),t("#calc_res_wave")&&(t("#calc_res_wave").textContent=r);const d=parseFloat(((ct=t("#calc_sg_w1"))==null?void 0:ct.value)??"0")||0,i=parseFloat(((Lt=t("#calc_sg_w2"))==null?void 0:Lt.value)??"0")||0,m=parseFloat(((yt=t("#calc_sg_w3"))==null?void 0:yt.value)??"0")||0,u=parseFloat(((Ct=t("#calc_sg_t1"))==null?void 0:Ct.value)??"1")||1,v=parseFloat(((wt=t("#calc_sg_t2"))==null?void 0:wt.value)??"1")||1,x=parseFloat(((It=t("#calc_sg_t3"))==null?void 0:It.value)??"1")||1,L=R(d/(100*(u/10)),3),P=R(i/(100*(v/10)),3),f=R(m/(100*(x/10)),3),g=R((L+P+f)/3,3);t("#calc_res_sg_ave")&&(t("#calc_res_sg_ave").textContent=`${L}, ${P}, ${f} (Ave: ${g})`);const N=parseFloat(((Ut=t("#calc_cp_b1"))==null?void 0:Ut.value)??"10")||10,G=parseFloat(((Ht=t("#calc_cp_a1"))==null?void 0:Ht.value)??"9")||9,E=parseFloat(((Vt=t("#calc_cp_b2"))==null?void 0:Vt.value)??"10")||10,et=parseFloat(((St=t("#calc_cp_a2"))==null?void 0:St.value)??"9")||9,at=parseFloat(((mt=t("#calc_cp_a3"))==null?void 0:mt.value)??"9")||9,Y=parseFloat(((ht=t("#calc_cp_sp"))==null?void 0:ht.value)??"0")||0,y=N-Y!==0?N-Y:1,C=E-Y!==0?E-Y:1,T=R((N-G)/y*100,1),M=R((E-et)/C*100,1),F=R((N-at)/y*100,1),D=R((T+M+F)/3,1);t("#calc_res_cp_ave")&&(t("#calc_res_cp_ave").textContent=`${D}% (${T}%, ${M}%, ${F}%)`);const U=parseFloat(((bt=t("#calc_din_wb"))==null?void 0:bt.value)??"0")||0,B=parseFloat(((Rt=t("#calc_din_wa"))==null?void 0:Rt.value)??"0")||0,Q=parseFloat(((Bt=t("#calc_din_sg"))==null?void 0:Bt.value)??"1")||1,Z=parseFloat(((xt=t("#calc_din_sa"))==null?void 0:xt.value)??"200")||200,z=R((U-B)/Q*(200/Z)*1e3,2);t("#calc_res_din")&&(t("#calc_res_din").textContent=z);const tt=parseFloat(((At=t("#calc_rf_li1"))==null?void 0:At.value)??"2.5")||2.5,dt=parseFloat(((Et=t("#calc_rf_lf1"))==null?void 0:Et.value)??"2.5")||2.5,ut=parseFloat(((Mt=t("#calc_rf_li2"))==null?void 0:Mt.value)??"2.5")||2.5,Ft=parseFloat(((Ot=t("#calc_rf_lf2"))==null?void 0:Ot.value)??"2.5")||2.5,$=R((dt-tt)/tt*100,2),O=R((Ft-ut)/ut*100,2);t("#calc_res_rf")&&(t("#calc_res_rf").textContent=`${$}% / ${O}%`);const W=parseFloat(((kt=t("#calc_sh_lb"))==null?void 0:kt.value)??"100")||100,K=parseFloat(((Pt=t("#calc_sh_la"))==null?void 0:Pt.value)??"100")||100,lt=parseFloat(((Nt=t("#calc_sh_wb"))==null?void 0:Nt.value)??"100")||100,ft=parseFloat(((Dt=t("#calc_sh_wa"))==null?void 0:Dt.value)??"100")||100,rt=R((W-K)/W*100,2),A=R((lt-ft)/lt*100,2);t("#calc_res_sh")&&(t("#calc_res_sh").textContent=`L: ${rt}% / W: ${A}%`)};k("#dtr-panel-calculators input").forEach(e=>{e.addEventListener("input",ca)}),(ja=t("#btn-apply-calc-weight"))==null||ja.addEventListener("click",()=>{var e,a,s;t("#mf_Weight1")&&(t("#mf_Weight1").value=((e=t("#calc_res_w1"))==null?void 0:e.value)||""),t("#mf_Weight2")&&(t("#mf_Weight2").value=((a=t("#calc_res_w2"))==null?void 0:a.value)||""),t("#mf_Weight3")&&(t("#mf_Weight3").value=((s=t("#calc_res_w3"))==null?void 0:s.value)||""),Tt(),ot(),gt("material"),I("✓ Applied calculated Weight 1..3 and WAve to Material Form.")}),(Ga=t("#btn-apply-calc-sg"))==null||Ga.addEventListener("click",()=>{var r,d,i,m,u,v;const e=parseFloat(((r=t("#calc_sg_w1"))==null?void 0:r.value)??"0")||0,a=parseFloat(((d=t("#calc_sg_w2"))==null?void 0:d.value)??"0")||0,s=parseFloat(((i=t("#calc_sg_w3"))==null?void 0:i.value)??"0")||0,l=parseFloat(((m=t("#calc_sg_t1"))==null?void 0:m.value)??"1")||1,o=parseFloat(((u=t("#calc_sg_t2"))==null?void 0:u.value)??"1")||1,n=parseFloat(((v=t("#calc_sg_t3"))==null?void 0:v.value)??"1")||1;t("#mf_SG1")&&(t("#mf_SG1").value=R(e/(100*(l/10)),3)),t("#mf_SG2")&&(t("#mf_SG2").value=R(a/(100*(o/10)),3)),t("#mf_SG3")&&(t("#mf_SG3").value=R(s/(100*(n/10)),3)),Tt(),ot(),gt("material"),I("✓ Applied Specific Gravity SG1..3 and SG_Ave to Material Form.")}),(za=t("#btn-apply-calc-din"))==null||za.addEventListener("click",()=>{var a;const e=((a=t("#calc_res_din"))==null?void 0:a.textContent)||"";t("#mf_DIN_1")&&(t("#mf_DIN_1").value=e),Tt(),ot(),gt("material"),I("✓ Applied DIN Abrasion value to Material Form.")}),(qa=t("#btn-apply-calc-ross"))==null||qa.addEventListener("click",()=>{var o,n,r,d;const e=parseFloat(((o=t("#calc_rf_li1"))==null?void 0:o.value)??"2.5")||2.5,a=parseFloat(((n=t("#calc_rf_lf1"))==null?void 0:n.value)??"2.5")||2.5,s=parseFloat(((r=t("#calc_rf_li2"))==null?void 0:r.value)??"2.5")||2.5,l=parseFloat(((d=t("#calc_rf_lf2"))==null?void 0:d.value)??"2.5")||2.5;t("#mf_RossFlex_1")&&(t("#mf_RossFlex_1").value=R((a-e)/e*100,2)),t("#mf_RossFlex_2")&&(t("#mf_RossFlex_2").value=R((l-s)/s*100,2)),Tt(),ot(),gt("material"),I("✓ Applied Ross Flex 1 & 2 to Material Form.")}),(Qa=t("#btn-apply-calc-shrink"))==null||Qa.addEventListener("click",()=>{var o,n,r,d;const e=parseFloat(((o=t("#calc_sh_lb"))==null?void 0:o.value)??"100")||100,a=parseFloat(((n=t("#calc_sh_la"))==null?void 0:n.value)??"100")||100,s=parseFloat(((r=t("#calc_sh_wb"))==null?void 0:r.value)??"100")||100,l=parseFloat(((d=t("#calc_sh_wa"))==null?void 0:d.value)??"100")||100;t("#mf_ShrinkageL")&&(t("#mf_ShrinkageL").value=R((e-a)/e*100,2)),t("#mf_ShrinkageW")&&(t("#mf_ShrinkageW").value=R((s-l)/s*100,2)),Tt(),ot(),gt("material"),I("✓ Applied Shrinkage L & W to Material Form.")});const Qe=async()=>{var s,l;const e=((s=t("#exp_mat_type"))==null?void 0:s.value)||"Canvas",a=((l=t("#exp_t_ave"))==null?void 0:l.value)||"1.25";try{const o=await J(`dtr/standards?material_type=${encodeURIComponent(e)}&t_ave=${encodeURIComponent(a)}`),n=t("#exp-standards-tbody");if(!n||!o.standards)return;n.innerHTML=o.standards.map(r=>`
        <tr>
          <td><b>${c(r.MaterialType)}</b></td>
          <td class="mono"><code>${c(r.TestMethod)}</code></td>
          <td class="mono"><b>${c(r.StandardValue??"-")}</b></td>
          <td class="mono">${c(r.Tolerance??"-")}</td>
          <td class="mono">${c(r.TMinC??"-")}</td>
          <td class="mono">${c(r.TMaxC??"-")}</td>
        </tr>
      `).join("")}catch(o){console.error(o)}};(Ja=t("#btn-exp-run"))==null||Ja.addEventListener("click",Qe),(Ka=t("#exp_mat_type"))==null||Ka.addEventListener("change",Qe);const us=()=>{var B,Q,Z,z,tt,dt,ut,Ft,$,O,W,K,lt,ft,rt,A,vt,nt,it,ct,Lt,yt,Ct,wt,It,Ut,Ht,Vt,St,mt,ht,bt,Rt,Bt,xt,At,Et,Mt,Ot,kt,Pt,Nt,Dt,zt,qt,Qt,Jt,Kt,Xt,Yt,Zt,te,ee,ae,se,oe,le,re,ne,ie,ce,de,fe,pe,ue,ve,me,he,be,_e,ge,Te,ye,Se,$e;const e=((B=t("#mf_LabRefNo"))==null?void 0:B.value)||"DTP-LAB-PREVIEW",a=((Q=t("#mf_TR"))==null?void 0:Q.value)||"-",s=((Z=t("#mf_MaterialType"))==null?void 0:Z.value)||"Material",l=((z=t("#mf_MaterialCode"))==null?void 0:z.value)||"-",o=((tt=t("#mf_MaterialDescription"))==null?void 0:tt.value)||"-",n=((dt=t("#mf_SupplierFactory"))==null?void 0:dt.value)||"DATIAN SUBIC SHOES INC.",r=((ut=t("#mf_ModelNoStyle"))==null?void 0:ut.value)||"-",d=((Ft=t("#mf_Color"))==null?void 0:Ft.value)||"-",i=(($=t("#mf_POLotNo"))==null?void 0:$.value)||"-",m=((O=t("#mf_MTL"))==null?void 0:O.value)||"-",u=Object.assign({},Pe||{});k("[data-raw-col]").forEach(q=>{u[q.dataset.rawCol]=q.value});const v=(...q)=>{var p;for(const b of q){const _=(p=t("#mf_"+b))==null?void 0:p.value;if(_!==void 0&&String(_).trim()!=="")return String(_).trim();const S=u[b];if(S!=null&&String(S).trim()!=="")return String(S).trim()}return""},x=q=>String(q||"").replace(/ 00:00:00$/,""),L=x(v("TestedDate","Tested Date"))||new Date().toISOString().slice(0,10),P=v("Technician","TestedBy"),f=v("Supplier's Country","SupplierCountry","Country"),g=v("Quantity","PO/LotQuantity"),N=v("SendBy","Send by"),G=x(v("RecievedDate","ReceivedDate")),E=x(v("FinishedDate")),et=v("MaterialSource"),at=[["Prod. Material","Prod. Material 量产材料"],["Prod. Re-test","Prod. Re-test 量产重测"],["New/Development","New/Development 开发按新材料"],["Sales Sample","Sales Sample (销售样品)"]].map(([q,p])=>`<span style="white-space:nowrap;margin-right:14px"><b style="color:${et===q?"#1d4ed8":"#94a3b8"}">${et===q?"●":"○"}</b> ${et===q?'<b style="color:#1d4ed8">'+c(p)+"</b>":c(p)}</span>`).join(""),Y=[1,2,3,4].map(q=>`<span style="margin-right:12px"><b style="color:#94a3b8">○</b> Tech ${q}</span>`).join(""),y=v("AuthorizedBy","ReviewedBy"),C=x(v("AuthorizedDate")),T=(v("Remarks")||"PASS").toUpperCase(),M=T==="PASS"?"":T==="TOLERABLE"?"stamp-tol":"stamp-fail",F=[],D=(q,p,b,_,S,w,H,V)=>{(b||w)&&F.push(`
          <tr>
            <td class="text-left"><b>${q} — Length (L)</b></td>
            <td>${p}</td>
            <td><b>${b||"-"}</b></td>
            <td>${_||"-"}</td>
            <td><b class="${S==="PASS"?"dtr-rate-pass":S==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${S||"-"}</b></td>
          </tr>
          <tr>
            <td class="text-left"><b>${q} — Width (W)</b></td>
            <td>${p}</td>
            <td><b>${w||"-"}</b></td>
            <td>${H||"-"}</td>
            <td><b class="${V==="PASS"?"dtr-rate-pass":V==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${V||"-"}</b></td>
          </tr>
        `)};D("Tensile Strength","kg",(W=t("#mf_TensileLAve"))==null?void 0:W.value,(K=t("#mf_Tensile_L"))==null?void 0:K.value,(lt=t("#mf_TensileLRating"))==null?void 0:lt.value,(ft=t("#mf_TensileWAve"))==null?void 0:ft.value,(rt=t("#mf_Tensile_W"))==null?void 0:rt.value,(A=t("#mf_TensileWRating"))==null?void 0:A.value),D("Elongation","%",(vt=t("#mf_ElongationLAve"))==null?void 0:vt.value,(nt=t("#mf_Elongation_L"))==null?void 0:nt.value,(it=t("#mf_ElongationLRating"))==null?void 0:it.value,(ct=t("#mf_ElongationWAve"))==null?void 0:ct.value,(Lt=t("#mf_Elongation_W"))==null?void 0:Lt.value,(yt=t("#mf_ElongationWRating"))==null?void 0:yt.value),D("Trouser / Wing Tear","kg",(Ct=t("#mf_TrouserTearLAve"))==null?void 0:Ct.value,(wt=t("#mf_TrouserTear_L"))==null?void 0:wt.value,(It=t("#mf_TrouserTearLRating"))==null?void 0:It.value,(Ut=t("#mf_TrouserTearWAve"))==null?void 0:Ut.value,(Ht=t("#mf_TrouserTear_W"))==null?void 0:Ht.value,(Vt=t("#mf_TrouserTearWRating"))==null?void 0:Vt.value),D("Stitch Tear","kg",(St=t("#mf_StitchTearLAve"))==null?void 0:St.value,(mt=t("#mf_StitchTear_L"))==null?void 0:mt.value,(ht=t("#mf_StitchTearLRating"))==null?void 0:ht.value,(bt=t("#mf_StitchTearWAve"))==null?void 0:bt.value,(Rt=t("#mf_StitchTear_W"))==null?void 0:Rt.value,(Bt=t("#mf_StitchTearWRating"))==null?void 0:Bt.value),(xt=t("#mf_Text3977"))!=null&&xt.value&&F.push(`
        <tr>
          <td class="text-left"><b>Mullen Bursting Strength</b></td>
          <td>kg/cm²</td>
          <td><b>${(At=t("#mf_Text3977"))==null?void 0:At.value}</b></td>
          <td>${((Et=t("#mf_MullenBurst"))==null?void 0:Et.value)||"-"}</td>
          <td><b>${((Mt=t("#mf_MullenBurstRating"))==null?void 0:Mt.value)||"-"}</b></td>
        </tr>
      `),(Ot=t("#mf_DieC_Ave"))!=null&&Ot.value&&F.push(`
        <tr>
          <td class="text-left"><b>Die C Tear / Edge Crush</b></td>
          <td>kg</td>
          <td><b>${(kt=t("#mf_DieC_Ave"))==null?void 0:kt.value}</b></td>
          <td>${((Pt=t("#mf_DieC_Std"))==null?void 0:Pt.value)||"-"}</td>
          <td><b>${((Nt=t("#mf_DieC_Rate"))==null?void 0:Nt.value)||"-"}</b></td>
        </tr>
      `),(Dt=t("#mf_WAve"))!=null&&Dt.value&&F.push(`
        <tr>
          <td class="text-left"><b>Fabric Weight (Grammage)</b></td>
          <td>g/m²</td>
          <td><b>${(zt=t("#mf_WAve"))==null?void 0:zt.value}</b></td>
          <td>${((qt=t("#mf_W_Std"))==null?void 0:qt.value)||"-"}</td>
          <td><b>${((Qt=t("#mf_WRating"))==null?void 0:Qt.value)||"-"}</b></td>
        </tr>
      `),(Jt=t("#mf_CTPRating"))!=null&&Jt.value&&F.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Perspiration (Face)</b></td>
          <td>Grade</td>
          <td>W:${((Kt=t("#mf_CtPWool"))==null?void 0:Kt.value)||"-"} A:${((Xt=t("#mf_CtPAcrylic"))==null?void 0:Xt.value)||"-"} P:${((Yt=t("#mf_CtPPolyester"))==null?void 0:Yt.value)||"-"} N:${((Zt=t("#mf_CtPNylon"))==null?void 0:Zt.value)||"-"} C:${((te=t("#mf_CtPCotton"))==null?void 0:te.value)||"-"} Ac:${((ee=t("#mf_CtPAcetate"))==null?void 0:ee.value)||"-"}</td>
          <td>${((ae=t("#mf_CTP"))==null?void 0:ae.value)||"4.0"}</td>
          <td><b>${(se=t("#mf_CTPRating"))==null?void 0:se.value}</b></td>
        </tr>
      `),(oe=t("#mf_CTWRating"))!=null&&oe.value&&F.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Water (Face)</b></td>
          <td>Grade</td>
          <td>W:${((le=t("#mf_CtWWool"))==null?void 0:le.value)||"-"} A:${((re=t("#mf_CtWAcrylic"))==null?void 0:re.value)||"-"} P:${((ne=t("#mf_CtWPolyester"))==null?void 0:ne.value)||"-"} N:${((ie=t("#mf_CtWNylon"))==null?void 0:ie.value)||"-"} C:${((ce=t("#mf_CtWCotton"))==null?void 0:ce.value)||"-"} Ac:${((de=t("#mf_CtWAcetate"))==null?void 0:de.value)||"-"}</td>
          <td>${((fe=t("#mf_CTW"))==null?void 0:fe.value)||"4.0"}</td>
          <td><b>${(pe=t("#mf_CTWRating"))==null?void 0:pe.value}</b></td>
        </tr>
      `),(ue=t("#mf_CTCDRYRating"))!=null&&ue.value&&F.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Dry)</b></td>
          <td>Grade</td>
          <td><b>${((ve=t("#mf_CtCDry"))==null?void 0:ve.value)||"-"}</b></td>
          <td>${((me=t("#mf_CtCDry_S"))==null?void 0:me.value)||"4.0"}</td>
          <td><b>${(he=t("#mf_CTCDRYRating"))==null?void 0:he.value}</b></td>
        </tr>
      `),(be=t("#mf_CTCWETRating"))!=null&&be.value&&F.push(`
        <tr>
          <td class="text-left"><b>Colorfastness to Crocking (Wet)</b></td>
          <td>Grade</td>
          <td><b>${((_e=t("#mf_CtCWet"))==null?void 0:_e.value)||"-"}</b></td>
          <td>${((ge=t("#mf_CtCWet_S"))==null?void 0:ge.value)||"3.0"}</td>
          <td><b>${(Te=t("#mf_CTCWETRating"))==null?void 0:Te.value}</b></td>
        </tr>
      `),(()=>{const q=F.length?/^(tensile|elongation|trousertear|stitchtear|mullen|diec|ctp|ctw|ctcdry|ctcwet)/i:/^$/,p={heataging:"Heat Aging (72 hrs, 70°C)",corrosion:"Corrosion Resistance",hydrolysis:"Hydrolysis (7 days, 60°C, 95% RH)",uv:"UV Light (24 hrs, 50°C)",ctcdry:"Colorfastness to Crocking (Dry)",ctcwet:"Colorfastness to Crocking (Wet)",ctw:"Colorfastness to Water",ctp:"Colorfastness to Perspiration",shrinkage12:"Shrinkage (1 hr, 70°C) 1-2",shrinkage34:"Shrinkage (1 hr, 70°C) 3-4",tensile:"Tensile",elongation:"Elongation",stitchtear:"Stitch Tear",peelstrength:"Peel Strength",shearstrength:"Shear Strength",laceTipRet:"Lace Tip Retention",knotslip:"Knot Slip",laceabrasion:"Lace Abrasion",hardness:"Hardness",sg:"Specific Gravity",pvc:"PVC Contact Discoloration"},b={corrosion:["CorrosionRes"],ctcdry:["CtCDry"],ctcwet:["CtCWet"],shrinkage12:["Shrinkageave12"],shrinkage34:["Shrinkageave34"]},_=H=>H.replace(/[.*+?^${}()|[\]\\]/g,"\\$&"),S=H=>{const V=String(H).trim().toUpperCase();return["P","PASS"].includes(V)?"PASS":["F","FAIL","FAILED"].includes(V)?"FAIL":V==="T"?"TOLERABLE":V},w=new Set;Object.keys(u).forEach(H=>{const V=H.match(/^(.+?)(?:_R|_?Rating|_?rating)$/),jt=V&&String(u[H]??"").trim();if(!jt)return;const X=V[1].replace(/txt/i,""),_t=X.toLowerCase();if(X.length<3||w.has(_t)||q.test(X))return;const Fe=[X,...b[_t]||[]],_s=pt=>/(_S|_std|Std|_Std|_R|Rating|_rating)$/.test(pt),ss=Object.keys(u).filter(pt=>!_s(pt)&&Fe.some(Xe=>new RegExp("^"+_(Xe)+"(?:txt)?(\\d*|_?ave)$","i").test(pt))).map(pt=>String(u[pt]??"").trim()).filter(pt=>pt!==""&&!/^0(\.0+)?$/.test(pt));if(!ss.length)return;w.add(_t);const os=Object.keys(u).find(pt=>Fe.some(Xe=>new RegExp("^"+_(Xe)+"(?:txt)?(_S|_std|Std|_Std)$","i").test(pt))&&String(u[pt]??"").trim()!==""),gs=p[_t]||X.replace(/([a-z])([A-Z0-9])/g,"$1 $2"),Ke=S(jt);F.push(`
          <tr>
            <td class="text-left"><b>${c(gs)}</b></td>
            <td>-</td>
            <td><b>${ss.map(c).join(" &nbsp; ")}</b></td>
            <td>${c(os?u[os]:"-")}</td>
            <td><b class="${Ke==="PASS"?"dtr-rate-pass":Ke==="TOLERABLE"?"dtr-rate-warn":"dtr-rate-fail"}">${c(Ke)}</b></td>
          </tr>
        `)})})();const U=`
      <div class="dtr-report-sheet">
        <div class="rpt-logo-row">
          <img class="rpt-logo-vf" src="${st}assets/images/vf-logo.webp" alt="VF">
          <img class="rpt-logo-satra" src="${st}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
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
              <span class="dtr-sheet-stamp-box ${M}">${c(T)}</span>
            </div>
          </div>
        </div>

        <table class="dtr-sheet-info-grid">
          <tr>
            <td class="label-cell">Material Code</td>
            <td><b>${c(l)}</b></td>
            <td class="label-cell">Material Type</td>
            <td>${c(s)}</td>
            <td class="label-cell">Tested Date</td>
            <td>${c(L)}</td>
          </tr>
          <tr>
            <td class="label-cell">Description</td>
            <td colspan="3">${c(o)}</td>
            <td class="label-cell">PO / Lot No.</td>
            <td>${c(i)}</td>
          </tr>
          <tr>
            <td class="label-cell">Supplier / Factory</td>
            <td>${c(n)}</td>
            <td class="label-cell">Model / Style</td>
            <td>${c(r)}</td>
            <td class="label-cell">Color / MTL</td>
            <td>${c(d)} / ${c(m)}</td>
          </tr>
          <tr>
            <td class="label-cell">Thickness (TAve)</td>
            <td><b>${((ye=t("#mf_TAve"))==null?void 0:ye.value)||"-"} mm</b></td>
            <td class="label-cell">Weight (WAve)</td>
            <td><b>${((Se=t("#mf_WAve"))==null?void 0:Se.value)||"-"} g/m²</b></td>
            <td class="label-cell">Technician</td>
            <td>${c(P||"-")}</td>
          </tr>
          <tr>
            <td class="label-cell">Supplier's Country</td>
            <td>${c(f||"-")}</td>
            <td class="label-cell">Quantity</td>
            <td>${c(g||"-")}</td>
            <td class="label-cell">Received Date</td>
            <td>${c(G||"-")}</td>
          </tr>
          <tr>
            <td class="label-cell">Material Source</td>
            <td colspan="3" style="line-height:1.9">${at}<br>${Y}</td>
            <td class="label-cell">Finished Date</td>
            <td>${c(E||"-")}</td>
          </tr>
          <tr>
            <td class="label-cell">Send by</td>
            <td colspan="5">${c(N||"-")}</td>
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
            ${F.length?F.join(""):'<tr><td colspan="5" class="muted">No test items recorded for this report.</td></tr>'}
          </tbody>
        </table>

        <div class="dtr-sheet-sign-row">
          <div class="dtr-sheet-sign-box">
            <div>Prepared by (Testing Technician):</div>
            <div class="dtr-sheet-sign-line">
              <span>${c(P)}</span>
              <span class="muted">${c(L)}</span>
            </div>
          </div>
          <div class="dtr-sheet-sign-box">
            <div>Reviewed &amp; Approved by (QA Lab Head):</div>
            <div class="dtr-sheet-sign-line">
              <span>${c(y)}</span>
              <span class="muted">${c(C||L)}</span>
            </div>
          </div>
        </div>
      </div>
    `;t("#dtr-print-modal-title").textContent=`Official Test Report Sheet — ${e}`,t("#dtr-print-sheet-container").innerHTML=U,($e=t("#dtr-print-modal-backdrop"))==null||$e.classList.remove("hidden")};let Oe=null;const da=async()=>{if(Oe)return Oe;try{const e=await fetch(st+"assets/images/bonding_sketches/sketch_configs.json");if(e.ok)return Oe=await e.json(),Oe}catch(e){console.warn("Failed to load sketch_configs.json",e)}return null};var fa=null,pa=0;function ke(){clearTimeout(fa),fa=setTimeout(vs,120)}async function vs(){var G,E,et,at,Y;const e=t("#vf-diag-left"),a=t("#vf-diag-right");if(!e||!a)return;const s=++pa,l=(((G=t("#bf_Side"))==null?void 0:G.value)||"Left").toLowerCase()==="left",o=parseFloat(((E=t("#bf_STD"))==null?void 0:E.value)||"2.5")||2.5,n=st+"assets/images/bonding_sketches/",r={},d={};let i=0;for(let y=1;y<=35;y++)r[y]=((et=t("#bp_UTFpoint"+y))==null?void 0:et.value)||"",d[y]=((at=t("#bp_FTOpoint"+y))==null?void 0:at.value)||"",(parseFloat(r[y])>0||parseFloat(d[y])>0)&&(i=y);const m=`<img src="${n}blank.png" class="vf-shoe-img" style="left:12%;top:8%;width:76%;height:84%;opacity:.85" alt="Shoe contour">`;let u="";const v=await da(),x=(((Y=t("#bf_AgeCategory"))==null?void 0:Y.value)||"ADULT").toUpperCase(),L=oa[x]||35;let P=L;v&&!v[P]&&(P=[26,27,28,29,30,31,32,33,34].reduce((C,T)=>Math.abs(T-P)<Math.abs(C-P)?T:C,27));let f=v?v[P]:null,g=null;if(f)try{const y=await J("dtr/layout?slug=bonding&variant="+encodeURIComponent(P)),C=y&&y.layout;C&&(f=JSON.parse(JSON.stringify(f)),C.image&&Object.assign(f.image,C.image),["utf","fto"].forEach(T=>{Object.keys(C[T]||{}).forEach(M=>{C[T][M].hidden?delete f[T][M]:f[T][M]=C[T][M]})}),(C.vector||C.start)&&(g=C),C.vector&&(f.image.file=C.imageFile||"blank.png"))}catch{}if(s!==pa)return;if(f&&g&&g.vector&&L!==f.pointsCount){const y=M=>{const F=Object.keys(g.dots[M]||{}).map(Number).filter($=>f[M][$]).sort(($,O)=>$-O);if(F.length<3)return null;const D=F.map($=>({x:g.dots[M][$].x,y:g.dots[M][$].y,i:$})),U=D.reduce(($,O)=>$+O.x,0)/D.length,B=D.reduce(($,O)=>$+O.y,0)/D.length;let Q=0;D.forEach($=>{const O=f[M][$.i],W=$.x-U,K=$.y-B,lt=Math.hypot(W,K)||1;Q+=((O.pctLeft+O.pctWidth/2-$.x)*W+(O.pctTop+O.pctHeight/2-$.y)*K)/lt}),Q/=D.length;const Z=f[M][D[0].i],z=D.concat([D[0]]),tt=[];let dt=0;for(let $=0;$<z.length-1;$++){const O=Math.hypot(z[$+1].x-z[$].x,z[$+1].y-z[$].y);tt.push(O),dt+=O}const ut={},Ft={};for(let $=0;$<L;$++){let O=$*dt/L,W=0;for(;W<tt.length-1&&O>tt[W];)O-=tt[W],W++;const K=tt[W]?O/tt[W]:0,lt=z[W].x+(z[W+1].x-z[W].x)*K,ft=z[W].y+(z[W+1].y-z[W].y)*K,rt=lt-U,A=ft-B,vt=Math.hypot(rt,A)||1,nt=lt+rt/vt*Q,it=ft+A/vt*Q;ut[$+1]={pctLeft:Math.min(100-Z.pctWidth,Math.max(0,nt-Z.pctWidth/2)),pctTop:Math.min(100-Z.pctHeight,Math.max(0,it-Z.pctHeight/2)),pctWidth:Z.pctWidth,pctHeight:Z.pctHeight},Ft[$+1]={x:lt,y:ft}}return{boxes:ut,dots:Ft}},C=y("utf"),T=y("fto");C&&T&&(f.utf=C.boxes,f.fto=T.boxes,g.dots={utf:C.dots,fto:T.dots},f.pointsCount=L)}const N=y=>{const C=He(parseFloat(y),o);return C?C==="FAIL"?"color:#dc2626;font-weight:800;":C==="TOLERABLE"?"color:#d97706;font-weight:800;":"color:#1d4ed8;font-weight:800;":"color:#94a3b8"};if(f){const y=f.image;u+=`<img src="${n}${y.file}" class="vf-shoe-img" style="left:${y.pctLeft}%;top:${y.pctTop}%;width:${y.pctWidth}%;height:${y.pctHeight}%" alt="Shoe sketch">`;const C=(T,M,F,D,U)=>{const B=parseFloat(F),Q=Number.isFinite(B)&&B>0;return`<div class="vf-point-box ${T}" style="left:${M.pctLeft}%;top:${M.pctTop}%;width:${M.pctWidth}%;height:${M.pctHeight}%;${N(F)}" title="${D} Point ${U}: ${Q?B.toFixed(2):"-"}">${Q?B.toFixed(2):""}</div>`};for(let T=1;T<=f.pointsCount;T++)f.utf[T]&&(u+=C("vf-point-utf",f.utf[T],r[T],"UTF",T)),f.fto[T]&&(u+=C("vf-point-fto",f.fto[T],d[T],"FTO",T));if(g){let T='<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none">',M="";if(g.vector&&["utf","fto"].forEach(F=>Object.keys(f[F]).forEach(D=>{const U=f[F][D],B=((g.dots||{})[F]||{})[D];if(!B)return;const Q=U.pctLeft+U.pctWidth,Z=U.pctTop+U.pctHeight/2,z=B.x<U.pctLeft?U.pctLeft:B.x>Q?Q:U.pctLeft+U.pctWidth/2,tt=z===U.pctLeft||z===Q?Z:B.y<U.pctTop?U.pctTop:U.pctTop+U.pctHeight,dt=F==="utf"?"#2563eb":"#7c3aed";T+=`<line x1="${z}" y1="${tt}" x2="${B.x}" y2="${B.y}" stroke="${dt}" stroke-width="1.2" vector-effect="non-scaling-stroke"/>`,M+=`<div style="position:absolute;left:${B.x}%;top:${B.y}%;width:7px;height:7px;margin:-3.5px 0 0 -3.5px;border-radius:50%;background:${dt}"></div>`})),g.start){const F=g.start;M+=`<div style="position:absolute;left:${F.x}%;top:${F.y}%;transform:translate(-50%,-50%);display:flex;align-items:center;gap:3px;white-space:nowrap;color:#b91c1c;font:800 9px sans-serif"><i style="display:block;width:9px;height:9px;background:#dc2626;transform:rotate(45deg)"></i>${c(F.label||"START")}</div>`}u+=T+"</svg>"+M}}else u='<div style="padding:20px;text-align:center;color:#64748b">Shoe sketch unavailable</div>';e.innerHTML=l?u:m,a.innerHTML=l?m:u}const ms=async()=>{var xt,At,Et,Mt,Ot,kt,Pt,Nt,Dt,zt,qt,Qt,Jt,Kt,Xt,Yt,Zt,te,ee,ae,se,oe,le,re,ne,ie,ce,de,fe,pe,ue,ve,me,he,be,_e,ge,Te,ye,Se,$e,q;const e=parseInt(((xt=t("#bf_BondingTestID"))==null?void 0:xt.value)||(h==null?void 0:h.BondingTestID)||0)||0,a=((At=t("#bf_LabRefNo"))==null?void 0:At.value)||"DTP-BOND-PREVIEW",s=((Et=t("#bf_ETRFNo"))==null?void 0:Et.value)||"-",l=((Mt=t("#bf_ModelStyle"))==null?void 0:Mt.value)||"-",o=((Ot=t("#bf_Color"))==null?void 0:Ot.value)||"-",n=((kt=t("#bf_Side"))==null?void 0:kt.value)||"Left",r=n.toLowerCase()==="left",d=(((Pt=t("#bf_AgeCategory"))==null?void 0:Pt.value)||"ADULT").toUpperCase(),i=parseFloat(((Nt=t("#bf_STD"))==null?void 0:Nt.value)||"2.5")||2.5,m=((Dt=t("#bf_POLotNO"))==null?void 0:Dt.value)||"-",u=((zt=t("#bf_POQuantity"))==null?void 0:zt.value)||"-",v=((qt=t("#bf_Factory"))==null?void 0:qt.value)||"DATIAN SUBIC SHOES INC. (DTP)",x=((Qt=t("#bf_AdhesiveSupplier"))==null?void 0:Qt.value)||"-",L=((Jt=t("#bf_ShippedDate"))==null?void 0:Jt.value)||"-",P=((Kt=t("#bf_ProductionDate"))==null?void 0:Kt.value)||"-",f=((Xt=t("#bf_Sendby"))==null?void 0:Xt.value)||"-",g=((Yt=t("#bf_MaterialSource"))==null?void 0:Yt.value)||"Prod. Shoes",N=((Zt=t("#bf_ModeOfFailure"))==null?void 0:Zt.value)||"-",G=((te=t("#bf_PossibleCause"))==null?void 0:te.value)||"-",E=((ee=t("#bf_Remarks"))==null?void 0:ee.value)||((ae=t("#bf_LabNotesComments"))==null?void 0:ae.value)||"",et=((se=t("#bf_ReceivedDate"))==null?void 0:se.value)||"-",at=((oe=t("#bf_TestedDate"))==null?void 0:oe.value)||new Date().toISOString().slice(0,10),Y=((le=t("#bf_FinishedDate"))==null?void 0:le.value)||at,y=((re=t("#bf_Technician"))==null?void 0:re.value)||"L. Aquidado",C=((ne=t("#bf_ReviewedBy"))==null?void 0:ne.value)||"QA Lead",T=(((ie=t("#bf_Result"))==null?void 0:ie.value)||"").toUpperCase(),M=T==="PASS"||!T?"":T==="TOLERABLE"?"stamp-tol":"stamp-fail",F=((ce=t("#bp_UTFMin"))==null?void 0:ce.value)||(h==null?void 0:h.UTFMin)||"-",D=((de=t("#bp_UTFMax"))==null?void 0:de.value)||(h==null?void 0:h.UTFMax)||"-",U=((fe=t("#bp_UTFAverage"))==null?void 0:fe.value)||(h==null?void 0:h.UTFAverage)||"-",B=(((pe=t("#bp_UTF_Result"))==null?void 0:pe.value)||(h==null?void 0:h.UTF_Result)||"-").toUpperCase(),Q=((ue=t("#bp_FTOMin"))==null?void 0:ue.value)||(h==null?void 0:h.FTOMin)||"-",Z=((ve=t("#bp_FTOMax"))==null?void 0:ve.value)||(h==null?void 0:h.FTOMax)||"-",z=((me=t("#bp_FTOAverage"))==null?void 0:me.value)||(h==null?void 0:h.FTOAverage)||"-",tt=(((he=t("#bp_FTO_Result"))==null?void 0:he.value)||(h==null?void 0:h.FTO_Result)||"-").toUpperCase(),dt=((be=t("#bp_FiTOMin"))==null?void 0:be.value)||(h==null?void 0:h.FiTOMin)||"-",ut=((_e=t("#bp_FiTOMax"))==null?void 0:_e.value)||(h==null?void 0:h.FiTOMax)||"-",Ft=((ge=t("#bp_FiTOAverage"))==null?void 0:ge.value)||(h==null?void 0:h.FiTOAverage)||"-",$=(((Te=t("#bp_FiTO_Result"))==null?void 0:Te.value)||(h==null?void 0:h.FiTO_Result)||"-").toUpperCase(),O={},W={},K={};let lt=0;for(let p=1;p<=35;p++){const b=((ye=t("#bp_UTFpoint"+p))==null?void 0:ye.value)||(h==null?void 0:h["UTFpoint"+p])||"",_=((Se=t("#bp_FTOpoint"+p))==null?void 0:Se.value)||(h==null?void 0:h["FTOpoint"+p])||"";O[p]=b,W[p]=_,(parseFloat(b)>0||parseFloat(_)>0)&&(lt=p),p<=13&&(K[p]=(($e=t("#bp_FiTO"+p))==null?void 0:$e.value)||(h==null?void 0:h["FiTO"+p])||"")}const ft=await da();let rt=parseInt((h==null?void 0:h.MaxPoints)||lt||27);(!ft||!ft[rt])&&(rt=[26,27,28,29,30,31,32,33,34].reduce((b,_)=>Math.abs(_-rt)<Math.abs(b-rt)?_:b,27));let A=ft?ft[rt]:null,vt=[],nt=null;if(A)try{const p=await J("dtr/layout?slug=bonding&variant="+encodeURIComponent(rt)),b=p&&p.layout;b&&(A=JSON.parse(JSON.stringify(A)),b.image&&Object.assign(A.image,b.image),["utf","fto"].forEach(_=>{Object.keys(b[_]||{}).forEach(S=>{b[_][S].hidden?delete A[_][S]:A[_][S]=b[_][S]})}),vt=b.extras||[],(b.vector||b.start)&&(nt=b),b.vector&&(A.image.file=b.imageFile||"blank.png"))}catch{}const it=(p,b,_=!1)=>{const S=He(parseFloat(p),_?xe:b);return S?S==="FAIL"?"color:#dc2626;font-weight:800;":S==="TOLERABLE"?"color:#d97706;font-weight:800;":"color:#1d4ed8;font-weight:800;":"color:#94a3b8"};let ct="";if(A){ct+=`
        <img src="${st}assets/images/bonding_sketches/${A.image.file}" 
             class="vf-shoe-img" 
             style="left:${A.image.pctLeft}%; top:${A.image.pctTop}%; width:${A.image.pctWidth}%; height:${A.image.pctHeight}%;" 
             alt="${n} Shoe Sketch">
      `;for(let p=1;p<=A.pointsCount;p++){const b=A.utf[p];if(b){const S=O[p],w=parseFloat(S),H=Number.isFinite(w)&&w>0,V=it(S,i,!1);ct+=`
            <div class="vf-point-box vf-point-utf" 
                 style="left:${b.pctLeft}%; top:${b.pctTop}%; width:${b.pctWidth}%; height:${b.pctHeight}%; ${V}" 
                 title="UTF Point ${p}: ${H?w.toFixed(2):"-"}">
              ${H?w.toFixed(2):""}
            </div>
          `}const _=A.fto[p];if(_){const S=W[p],w=parseFloat(S),H=Number.isFinite(w)&&w>0,V=it(S,i,!1);ct+=`
            <div class="vf-point-box vf-point-fto" 
                 style="left:${_.pctLeft}%; top:${_.pctTop}%; width:${_.pctWidth}%; height:${_.pctHeight}%; ${V}" 
                 title="FTO Point ${p}: ${H?w.toFixed(2):"-"}">
              ${H?w.toFixed(2):""}
            </div>
          `}}if(nt){let p='<svg viewBox="0 0 100 100" preserveAspectRatio="none" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none">',b="";if(nt.vector&&["utf","fto"].forEach(_=>Object.keys(A[_]).forEach(S=>{const w=A[_][S],H=((nt.dots||{})[_]||{})[S];if(!H)return;const V=w.pctLeft+w.pctWidth,jt=w.pctTop+w.pctHeight/2,X=H.x<w.pctLeft?w.pctLeft:H.x>V?V:w.pctLeft+w.pctWidth/2,_t=X===w.pctLeft||X===V?jt:H.y<w.pctTop?w.pctTop:w.pctTop+w.pctHeight,Fe=_==="utf"?"#2563eb":"#7c3aed";p+=`<line x1="${X}" y1="${_t}" x2="${H.x}" y2="${H.y}" stroke="${Fe}" stroke-width="1.2" vector-effect="non-scaling-stroke"/>`,b+=`<div style="position:absolute;left:${H.x}%;top:${H.y}%;width:7px;height:7px;margin:-3.5px 0 0 -3.5px;border-radius:50%;background:${Fe}"></div>`})),nt.start){const _=nt.start;b+=`<div style="position:absolute;left:${_.x}%;top:${_.y}%;transform:translate(-50%,-50%);display:flex;align-items:center;gap:3px;white-space:nowrap;color:#b91c1c;font:800 9px sans-serif"><i style="display:block;width:9px;height:9px;background:#dc2626;transform:rotate(45deg)"></i>${c(_.label||"START")}</div>`}ct+=p+"</svg>"+b}vt.forEach(p=>{var H;const b=`left:${p.pctLeft}%; top:${p.pctTop}%; width:${p.pctWidth}%; height:${p.pctHeight}%;`,_=`color:${c(p.color||"#111")}; font-size:${Number(p.size)||12}px; font-weight:${p.bold?700:400};`;let S="";if(p.type==="text")S=c(p.text);else if(p.type==="field")S=c(((H=t("#bf_"+p.field))==null?void 0:H.value)||"");else if(p.type==="image"&&p.src)S=`<img src="${st}${c(p.src)}" style="max-width:100%;max-height:100%" alt="">`;else if(p.type==="chart"){let V=0;for(let X=1;X<=A.pointsCount;X++)V=Math.max(V,parseFloat(O[X])||0);let jt="";for(let X=1;X<=A.pointsCount;X++){const _t=parseFloat(O[X])||0;jt+=`<i style="flex:1;display:block;min-height:1px;height:${V?_t/V*100:0}%;background:${_t<2?"#dc2626":_t<3?"#d97706":"#1d4ed8"}"></i>`}S=`<div style="display:flex;align-items:flex-end;gap:1px;width:100%;height:100%">${jt}</div>`}const w=p.type==="line"?`background:${c(p.color||"#111")};`:"";ct+=`<div style="position:absolute;display:flex;align-items:center;overflow:hidden;${b}${_}${w}">${S}</div>`})}else ct='<div style="padding:20px;text-align:center;color:#64748b">Shoe sketch unavailable</div>';const Lt=`
      <img src="${st}assets/images/bonding_sketches/blank.png" 
           class="vf-shoe-img" 
           style="left:12%; top:8%; width:76%; height:84%; opacity:0.85;" 
           alt="Unmeasured Shoe Contour">
    `;let yt="";for(let p=1;p<=13;p++){const b=K[p],_=parseFloat(b),S=Number.isFinite(_)&&_>0,w=it(b,1,!0);yt+=`
        <div class="vf-center-item" style="${w}">
          ${S?_.toFixed(2):"&nbsp;"}
        </div>
      `}const Ct='<div class="vf-center-line"></div>'.repeat(6),wt=Array.from({length:13},(p,b)=>parseFloat(K[b+1])).some(p=>Number.isFinite(p)&&p>0),It=r?ct:Lt,Ut=r?Lt:ct,Ht=r&&wt?yt:Ct,Vt=!r&&wt?yt:Ct,St=p=>p==="PASS"?{pass:"PASS",fail:"",passCls:"vf-rate-pass",failCls:""}:p==="TOLERABLE"?{pass:"TOLE",fail:"",passCls:"vf-rate-tol",failCls:""}:p==="FAIL"||p==="FAILED"?{pass:"",fail:"FAIL",passCls:"",failCls:"vf-rate-fail"}:{pass:"-",fail:"",passCls:"",failCls:""},mt=St(B),ht=St(tt),bt=St($);let Rt="";for(let p=1;p<=(A?A.pointsCount:27);p++){const b=O[p]?parseFloat(O[p]).toFixed(2):"-",_=W[p]?parseFloat(W[p]).toFixed(2):"-",S=p<=13&&K[p]?parseFloat(K[p]).toFixed(2):"-";Rt+=`
        <tr>
          <td><b>Point ${p}</b></td>
          <td style="${it(O[p],i,!1)}">${b}</td>
          <td style="${it(W[p],i,!1)}">${_}</td>
          <td style="${p<=13?it(K[p],1,!0):"color:#94a3b8"}">${S}</td>
        </tr>
      `}const Bt=`
      <div class="vf-report-sheet">
        <!-- Row 1: VF logo | test method name | SATRA logo, all on one line -->
        <div class="rpt-top">
          <img class="rpt-logo-vf" src="${st}assets/images/vf-logo.webp" alt="VF">
          <div class="rpt-top-title">
            <h1 class="vf-title-en">Bonding Test Results</h1>
            <h2 class="vf-title-cn">胶着力测试</h2>
          </div>
          <img class="rpt-logo-satra" src="${st}assets/images/satra-accredited.png" alt="SATRA Accredited Laboratory No. 08A03">
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
                <span class="vf-radio ${g==="Prod. Shoes"||g==="Prod. Material"||!g?"checked":""}"></span> Pro. Shoes 量产鞋
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${g==="Pro. Re-test"?"checked":""}"></span> Pro. Re-test 量产重测
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${g==="NewDevelopment"?"checked":""}"></span> NewDevelopment 开发
              </div>
              <div class="vf-tech-subradios">
                <span>○ Tech 1</span> <span>○ Tech 2</span> <span>○ Tech 3</span> <span>○ Tech 4</span>
              </div>
              <div style="margin-top:2px">
                <span class="vf-radio ${g==="Sales Sample"?"checked":""}"></span> Sales Sample 销样
                <span style="display:inline-block;width:8px"></span>
                <span class="vf-radio ${g==="Trial experiment"?"checked":""}"></span> 试胶试验
              </div>
            </div>
          </div>

          <div class="rpt-sub-right">
            <div class="vf-stamp-area">
              <span class="vf-stamp ${M}">${c(T)}</span>
            </div>
            <table class="vf-dates-table">
              <tr><td>Received Date 收到日期:</td><td class="vf-date-val">${c(et)}</td></tr>
              <tr><td>Tested Date 测试日期:</td><td class="vf-date-val">${c(at)}</td></tr>
              <tr><td>Finished Date 完成日期:</td><td class="vf-date-val">${c(Y)}</td></tr>
              <tr><td>Technician 技术员:</td><td class="vf-date-val">${c(y)}</td></tr>
            </table>
          </div>
        </div>

        <!-- Factory & Spec Details Grid -->
        <table class="vf-meta-grid">
          <tr>
            <td class="vf-lbl">Factory (工厂):</td>
            <td class="vf-val" colspan="3">${c(v)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Adhesive supplier 胶水供应商:</td>
            <td class="vf-val">${c(x)}</td>
            <td class="vf-lbl">Shipped Date (出货日期):</td>
            <td class="vf-val">${c(L)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Model/Style/PGSC (英文鞋型):</td>
            <td class="vf-val">${c(l)}</td>
            <td class="vf-lbl">Color (颜色):</td>
            <td class="vf-val">${c(o)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">Production Date (生产日期):</td>
            <td class="vf-val">${c(P!=="-"?P:at)}</td>
            <td class="vf-lbl">PO/Lot No (订单号码):</td>
            <td class="vf-val">${c(m)}</td>
          </tr>
          <tr>
            <td class="vf-lbl">PO Quantity (订单数量):</td>
            <td class="vf-val">${c(u)}</td>
            <td class="vf-lbl">Send by (送达):</td>
            <td class="vf-val">${c(f)}</td>
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
              <td class="font-bold text-blue">${F!=="-"?Number(F).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${D!=="-"?Number(D).toFixed(2):"-"}</td>
              <td><span class="${mt.passCls}">${mt.pass}</span></td>
              <td><span class="${mt.failCls}">${mt.fail}</span></td>
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
              <td class="font-bold text-blue">${Q!=="-"?Number(Q).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${Z!=="-"?Number(Z).toFixed(2):"-"}</td>
              <td><span class="${ht.passCls}">${ht.pass}</span></td>
              <td><span class="${ht.failCls}">${ht.fail}</span></td>
            </tr>
            <tr>
              <td class="text-left font-bold">Center : IS/OS<br>(中插 / 大底)</td>
              <td>VF-F01<br><span style="font-size:9.5px">(2019/05)</span></td>
              <td class="font-bold text-blue">${xe}<br><span class="vf-tol">(≥ ${Ae(xe)})</span></td>
              <td class="font-bold text-blue">${dt!=="-"?Number(dt).toFixed(2):"-"}</td>
              <td class="font-bold text-blue">${ut!=="-"?Number(ut).toFixed(2):"-"}</td>
              <td><span class="${bt.passCls}">${bt.pass}</span></td>
              <td><span class="${bt.failCls}">${bt.fail}</span></td>
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
            ${It}
          </div>

          <!-- Center Boxes -->
          <div class="vf-center-card">
            <div class="vf-center-cols">
              <div class="vf-center-box" style="${r?"border-color:#1d4ed8;background:#f8fafc":"border-color:#cbd5e1"}">
                ${Ht}
              </div>
              <div class="vf-center-box" style="${r?"border-color:#cbd5e1":"border-color:#1d4ed8;background:#f8fafc"}">
                ${Vt}
              </div>
            </div>
          </div>

          <!-- Right Shoe Card -->
          <div class="vf-shoe-card">
            ${Ut}
          </div>
        </div>

        <!-- Optional Collapsible Data Table -->
        <details class="no-print" style="margin-top:8px;font-size:11px;border:1px solid #cbd5e1;border-radius:4px;padding:6px 10px;background:#f8fafc">
          <summary style="cursor:pointer;font-weight:700;color:#334155">
            <svg class="ic" aria-hidden="true"><use href="#i-chart"/></svg> View Tabular Spot Values (${A?A.pointsCount:27} Points)
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
              ${Rt}
            </tbody>
          </table>
        </details>

        <!-- Signatures & Notes Footer -->
        <div class="vf-footer-grid">
          <div>
            Comments 备注:
            <div style="font-weight:normal;color:#334155;margin-top:4px;font-size:10.5px">
              ${c(E||"Tested in accordance with VF Laboratory Standards.")}
            </div>
          </div>
          <div>
            Reviewed by 审查:
            <div class="vf-sign-line">
              <span>${c(C)}</span>
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
    `;t("#dtr-print-modal-title").textContent=`Whole Shoe Bonding Test Report — ${a} (${n})`,t("#dtr-print-sheet-container").innerHTML=Bt,(q=t("#dtr-print-modal-backdrop"))==null||q.classList.remove("hidden")},Je=e=>{const a=748.3464566929134,s=285/25.4*96,l=document.createElement("div");l.className="dtr-fit-probe",l.style.cssText="position:absolute;left:-99999px;top:0;visibility:hidden;",document.body.appendChild(l);let o=1;for(let n=0;n<4;n++){l.style.width=a/o+"px",l.innerHTML=e;const r=l.scrollHeight*o,d=Math.min(1,o*(s/Math.max(r,1))*.985);if(Math.abs(d-o)<.005){o=d;break}o=d}return l.remove(),Math.max(.4,Math.round(o*1e3)/1e3)};(Xa=t("#btn-dtr-print-preview"))==null||Xa.addEventListener("click",us),(Ya=t("#btn-bond-print-preview"))==null||Ya.addEventListener("click",()=>ze()?ps():ms()),(Za=t("#btn-dtr-close-print-modal"))==null||Za.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(ts=t("#btn-dtr-cancel-print"))==null||ts.addEventListener("click",()=>{var e;(e=t("#dtr-print-modal-backdrop"))==null||e.classList.add("hidden")}),(es=t("#btn-dtr-download-pdf"))==null||es.addEventListener("click",async()=>{var l,o,n;const e=t("#btn-dtr-download-pdf"),a=t("#dtr-print-sheet-container");if(!a||!e)return;const s=e.textContent;e.disabled=!0,e.textContent="Creating PDF...";try{const r=await fetch(st+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((l=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:l.content)||""},body:JSON.stringify({scale:Je(a.innerHTML),html:a.innerHTML,name:(((o=t("#dtr-print-modal-title"))==null?void 0:o.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_")})});if(!r.ok)throw new Error((await r.text()).slice(0,200)||"PDF failed");const d=await r.blob(),i=document.createElement("a");i.href=URL.createObjectURL(d),i.download=((((n=t("#dtr-print-modal-title"))==null?void 0:n.textContent)||"Report").replace(/[^A-Za-z0-9._ -]+/g,"_").trim()||"Report")+".pdf",document.body.appendChild(i),i.click(),i.remove(),setTimeout(()=>URL.revokeObjectURL(i.href),4e3)}catch(r){nxToast("Could not create the PDF: "+r.message,"error",7e3)}e.disabled=!1,e.textContent=s}),(as=t("#btn-dtr-do-print"))==null||as.addEventListener("click",()=>{const e=t("#dtr-print-sheet-container");if(!e){window.print();return}const a=document.createElement("div");a.id="dtr-print-root",a.innerHTML=e.innerHTML,a.style.zoom=String(Je(e.innerHTML)),document.body.appendChild(a),document.body.classList.add("dtr-printing");const s=()=>{document.body.classList.remove("dtr-printing"),a.remove(),window.removeEventListener("afterprint",s)};window.addEventListener("afterprint",s),window.print()});const hs=async()=>{var e,a;try{const s=await J("dtr/layout?slug=bonding&variant=form"),l=s&&s.layout,o=t("#dtr-bonding-form");if(!l||!o)return;const n=d=>{var i;return(i=o.querySelector(`[name="${d}"]`))==null?void 0:i.closest(".dtr-field")};(l.customFields||[]).forEach(d=>{var u,v;if(o.querySelector(`[name="${d.key}"]`))return;const i=(v=(u=t("#bf_LabRefNo"))==null?void 0:u.closest(".dtr-field"))==null?void 0:v.parentElement;if(!i)return;const m=document.createElement("div");m.className="dtr-field",m.innerHTML=`<label>${c(d.label)}</label><input type="text" name="${c(d.key)}" id="bf_${c(d.key)}">`,i.appendChild(m)}),Object.entries(l.labels||{}).forEach(([d,i])=>{const m=n(d),u=m&&m.querySelector("label");u&&i&&(u.textContent=i)});const r=(a=(e=t("#bf_LabRefNo"))==null?void 0:e.closest(".dtr-field"))==null?void 0:a.parentElement;r&&(l.order||[]).length&&(l.order.forEach(d=>{const i=n(d);i&&i.parentElement===r&&r.appendChild(i)}),(l.customFields||[]).forEach(d=>{const i=n(d.key);i&&r.appendChild(i)})),(l.hiddenFields||[]).forEach(d=>{const i=n(d);i&&(i.style.display="none")})}catch{}};ca(),Ue(!1),ia(!1),Me("bonding"),we("fabric");const bs=hs().then(()=>{la(!1),Ve()});Qe(),(()=>{const e=new URLSearchParams(location.search),[a,s]=String(e.get("open")||"").split(":");!a||!/^[a-z]+$/.test(a)||!/^\d+$/.test(s||"")||setTimeout(async()=>{var n,r;await bs;const l=t("#dtr-tracker-tbody")||document.body,o=document.createElement("button");if(o.type="button",o.hidden=!0,o.dataset.openRecord=a,o.dataset.sourceId=s,l.appendChild(o),o.click(),o.remove(),e.get("preview")==="1"&&(await new Promise(d=>setTimeout(d,2200)),(n=t(a==="bonding"||a==="pull"?"#btn-bond-print-preview":"#btn-dtr-print-preview"))==null||n.click()),e.get("export")==="1"&&window.parent!==window){const d=i=>window.parent.postMessage(Object.assign({type:"NXL_REPORT_PDF"},i),location.origin);try{await new Promise(x=>setTimeout(x,800));const i=t("#dtr-print-sheet-container");if(!i||!i.innerHTML.trim())throw new Error("The report sheet could not be prepared.");const m=await fetch(st+"dtr/sheet-pdf",{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":((r=document.querySelector('meta[name="X-CSRF-TOKEN"]'))==null?void 0:r.content)||""},body:JSON.stringify({scale:Je(i.innerHTML),html:i.innerHTML,name:"Report"})});if(!m.ok)throw new Error((await m.text()).slice(0,200)||"PDF failed");const u=new Uint8Array(await m.arrayBuffer());let v="";for(let x=0;x<u.length;x+=32768)v+=String.fromCharCode.apply(null,u.subarray(x,x+32768));d({ok:!0,b64:btoa(v)})}catch(i){d({ok:!1,error:i.message})}}if(e.get("export")==="html"&&window.parent!==window){const d=i=>window.parent.postMessage(Object.assign({type:"NXL_REPORT_HTML"},i),location.origin);try{await new Promise(m=>setTimeout(m,1200));const i=t("#dtr-print-sheet-container");if(!i||!i.innerHTML.trim())throw new Error("The report sheet could not be prepared.");d({ok:!0,html:i.innerHTML})}catch(i){d({ok:!1,error:i.message})}}},900)})()})();
