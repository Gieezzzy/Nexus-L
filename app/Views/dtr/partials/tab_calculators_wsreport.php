<?php /* DTR partial: Lab calculators / standards explorer + Whole Shoe report (Lab VF 002). Included by dtr/index.php, shares its variables. */ ?>
<!-- =======================================================================
     TAB 4: BUILT-IN LAB CALCULATORS & STANDARDS / DSR LOOKUP EXPLORER
     ======================================================================= -->
<!-- =======================================================================
     WHOLE SHOE REPORT (Whole Footwear Test Results, Form # Lab VF 002)
     ======================================================================= -->
<section id="dtr-panel-wholeshoe" class="dtr-panel <?= $activeTab === 'wholeshoe' ? '' : 'hidden' ?>">
    <div class="card glass dtr-section-card">
        <div class="wsf wsf-readonly" id="wsf-sheet">
            <div class="wsf-head">
                <img src="<?= base_url('assets/images/vf-logo.webp') ?>" class="wsf-vf" alt="VF">
                <div class="wsf-title"><h2>Whole Footwear Test Results</h2><h3>整鞋测试报告</h3></div>
                <img src="<?= base_url('assets/images/satra-accredited.png') ?>" class="wsf-satra" alt="SATRA Accredited Laboratory">
            </div>
            <table class="wsf-t wsf-ref"><tr><td class="wsf-frm">Form # : Lab VF 002</td><td class="wsf-rf"><div class="wsf-lr">ETRF. No : <input type="text" id="ws-etrf" placeholder="-" readonly aria-label="ETRF No"></div><div class="wsf-lr">Lab Ref. No : <input type="text" id="ws-labref" readonly tabindex="-1" aria-label="Lab Ref No" placeholder="Open a Lab Ref from the Lab Tracker"></div></td></tr></table>
            <table class="wsf-t wsf-info">
                <tr><td>Supplier/Factory(厂商) :</td><td><input type="text" id="ws-supplier" class="wsf-bold" value="DATIAN SUBIC SHOES INC." placeholder="-" readonly aria-label="Supplier / Factory"></td><td>O Prod.Material (量产材料)</td><td>O Prod.Re-test量产重测</td></tr>
                <tr><td>Supplier's country(国家):</td><td><input type="text" id="ws-country" class="wsf-bold" value="Philippines" placeholder="-" readonly aria-label="Supplier country"></td><td>O New/Development 开发段新材料</td><td>O Trial Test 试测测试</td></tr>
                <tr><td>Material Description(材料):</td><td><input type="text" id="ws-matdesc" placeholder="-" readonly aria-label="Material description"></td><td colspan="2">o Tech 1 &nbsp;&nbsp;&nbsp; Tech 2 &nbsp;&nbsp;&nbsp; Tech 3 &nbsp;&nbsp;&nbsp; Tech 4</td></tr>
                <tr><td>Model No./Style(型体):</td><td><input type="text" id="ws-style" placeholder="-" readonly></td><td colspan="2">O Sales Sample (销售样品)</td></tr>
                <tr><td>Color (颜色):</td><td><input type="text" id="ws-color" placeholder="-" readonly></td><td>Received Date(收到日) :</td><td><span class="wsf-date-ro" data-date-for="ws-received">-</span><input type="date" id="ws-received" class="wsf-date-inp" disabled></td></tr>
                <tr><td>P.O/Lot No(编号):</td><td><input type="text" id="ws-po" placeholder="-" readonly></td><td>Tested Date(测试时间) :</td><td><span class="wsf-date-ro" data-date-for="ws-tested">-</span><input type="date" id="ws-tested" class="wsf-date-inp" disabled></td></tr>
                <tr><td>Quantity(数量):</td><td><input type="text" id="ws-qty" placeholder="-" readonly></td><td>Finished Date(完成时间) :</td><td><span class="wsf-date-ro" data-date-for="ws-finished">-</span><input type="date" id="ws-finished" class="wsf-date-inp" disabled></td></tr>
                <tr><td>Send by (提供方):</td><td><input type="hidden" id="ws-tests"><input type="text" id="ws-sendby" placeholder="-" readonly aria-label="Send by"></td><td>Technician(技术人员) :</td><td><input type="text" id="ws-tech" list="dtr-tech-list" autocomplete="off" placeholder="-" readonly></td></tr>
            </table>

            <div id="ws-sections"></div>

            <table class="wsf-t wsf-foot"><tr>
                <td class="wsf-cm">Comments备注 :<textarea id="ws-comments" rows="3" placeholder="-" readonly></textarea></td>
                <td class="wsf-sg">
                    <div>Reviewed by 检查: <input type="text" id="ws-reviewed" placeholder="-" readonly></div>
                    <div>Approved by核准: <input type="text" id="ws-approved" placeholder="-" readonly></div>
                </td>
            </tr></table>
        </div>
        <p id="ws-msg" class="muted sm" style="margin:10px 0 0"></p>
    </div>
</section>
