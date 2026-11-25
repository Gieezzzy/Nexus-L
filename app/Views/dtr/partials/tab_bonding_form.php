<?php /* DTR partial: Whole Shoe Bonding test form (U60) + Pull Footwear Test Results. Included by dtr/index.php, shares its variables. */ ?>
<!-- =======================================================================
     TAB 3: WHOLE SHOE BONDING TEST FORM (U60 — BondingTestInfo + BondingTestPoints)
     ======================================================================= -->
<section id="dtr-panel-bonding" class="dtr-panel <?= $activeTab === 'bonding' ? '' : 'hidden' ?>">
    <div class="card glass dtr-section-card">
        <div class="dtr-form-pickers" hidden>
            <div class="dtr-field">
                <label>Select Whole Shoe Bonding Record (1,091 Reports)</label>
                <select id="dtr-bonding-record-select" class="dtr-select-lg"></select>
            </div>
        </div>

        <form id="dtr-bonding-form" onsubmit="return false;">
            <input type="hidden" name="BondingTestID" id="bf_BondingTestID" value="">

            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>Bonding Test Info &amp; Age Category Spot Rules (<code>TODDLER</code>: 14 spots · <code>KIDS</code>: 16 spots · <code>CHILDREN</code>: 24 spots · <code>ADULT</code>: 35 spots)</h4>                </div>

                <!-- VF-F01 "paper sheet" layout — mirrors the official PRODUCTION bonding template.xlsx -->
                <div class="vf-sheet">
                    <div class="vf-tpl-head">
                        <img class="vf-tpl-logo" src="<?= base_url('assets/images/vf-logo.webp') ?>" alt="VF">
                        <div class="vf-tpl-title">
                            <div class="vf-tpl-title-en">Bonding Test Results</div>
                            <div class="vf-tpl-title-cn">胶着力测试</div>
                        </div>
                        <div class="vf-tpl-right">
                            <div class="vf-tpl-result"><label for="bf_Result">* Overall Result</label><input type="text" name="Result" id="bf_Result" class="bold mono dtr-rate-inp" readonly></div>
                            <img class="vf-tpl-satra" src="<?= base_url('assets/images/satra-accredited.png') ?>" alt="SATRA Accredited Laboratory">
                        </div>
                    </div>

                    <div class="vf-tpl-ids">
                        <div class="vf-tpl-box vf-tpl-formno">Form # : Lab VF 001</div>
                        <div class="vf-tpl-box"><label for="bf_ETRFNo">ETRF No:</label><input type="text" name="ETRFNo" id="bf_ETRFNo" class="mono"></div>
                        <div class="vf-tpl-box"><label for="bf_LabRefNo">* Lab Ref.No:</label><input type="text" name="LabRefNo" id="bf_LabRefNo" class="mono bold" autocomplete="off"></div>
                    </div>

                    <div class="vf-tpl-body">
                        <div class="vf-tpl-col">
                            <div class="vf-tpl-line"><label for="bf_Factory">Factory (工厂):</label><input type="text" name="Factory" id="bf_Factory"></div>
                            <div class="vf-tpl-line"><label for="bf_AdhesiveSupplier">Adhesive supplier 胶水供应商:</label><input type="text" name="AdhesiveSupplier" id="bf_AdhesiveSupplier"></div>
                            <div class="vf-tpl-line"><label for="bf_ShippedDate">Shipped Date (出货日期):</label><input type="text" name="ShippedDate" id="bf_ShippedDate"></div>
                            <div class="vf-tpl-line"><label for="bf_ModelStyle">Model/Style/PGSC (英文鞋型):</label><input type="text" name="ModelStyle" id="bf_ModelStyle"></div>
                            <div class="vf-tpl-line"><label for="bf_Color">Color (颜色):</label><input type="text" name="Color" id="bf_Color"></div>
                            <div class="vf-tpl-line"><label for="bf_ProductionDate">Production Date (生产日期):</label><input type="text" name="ProductionDate" id="bf_ProductionDate"></div>
                            <div class="vf-tpl-line">
                                <label for="bf_POLotNO">PO/Lot No (订单号码):</label>
                                <input type="text" name="POLotNO" id="bf_POLotNO" class="mono">                            </div>
                            <div class="vf-tpl-line"><label for="bf_POQuantity">PO Quantity (订单数量):</label><input type="text" name="POQuantity" id="bf_POQuantity"></div>
                            <div class="vf-tpl-line"><label for="bf_Sendby">Send by (送达):</label><input type="text" name="Sendby" id="bf_Sendby"></div>
                        </div>

                        <div class="vf-tpl-col">
                            <select name="MaterialSource" id="bf_MaterialSource" class="vf-tpl-hidden" tabindex="-1" aria-hidden="true">
                                <option value="Pro. Shoes">Pro. Shoes (量产鞋)</option>
                                <option value="Pro. Re-test">Pro. Re-test (量产重测)</option>
                                <option value="NewDevelopment">New / Development (开发)</option>
                                <option value="Sales Sample">Sales Sample (销样)</option>
                                <option value="Trial experiment">Trial experiment (试胶试验)</option>
                            </select>
                            <div class="vf-tpl-radios" id="bf_MaterialSource_radios" role="radiogroup" aria-label="Test Type">
                                <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:center">
                                    <label><input type="radio" name="bf_ms_radio" value="Pro. Shoes"> Pro. Shoes 量产鞋</label>
                                    <label><input type="radio" name="bf_ms_radio" value="Pro. Re-test"> Pro. Re-test 量产重测</label>
                                </div>
                                <label><input type="radio" name="bf_ms_radio" value="NewDevelopment"> New/Development 开发</label>
                                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding-left:20px" role="radiogroup" aria-label="Technician slot">
                                    <?php for ($t = 1; $t <= 4; $t++): ?>
                                        <label><input type="radio" name="bf_tech_radio" value="Tech <?= $t ?>"> Tech <?= $t ?></label>
                                    <?php endfor ?>
                                </div>
                                <label><input type="radio" name="bf_ms_radio" value="Sales Sample"> Sales Sample 销样</label>
                                <label><input type="radio" name="bf_ms_radio" value="Trial experiment"> Trial experiment 试胶试验</label>
                            </div>
                            <div class="vf-tpl-line"><label for="bf_ReceivedDate">Received Date 收到日期:</label><input type="text" name="ReceivedDate" id="bf_ReceivedDate"></div>
                            <div class="vf-tpl-line"><label for="bf_TestedDate">Tested Date 测试日期:</label><input type="text" name="TestedDate" id="bf_TestedDate"></div>
                            <div class="vf-tpl-line"><label for="bf_FinishedDate">Finished Date 完成日期:</label><input type="text" name="FinishedDate" id="bf_FinishedDate"></div>
                            <div class="vf-tpl-line"><label for="bf_Technician">Technician 技术员:</label><input type="text" name="Technician" id="bf_Technician" list="dtr-tech-list" autocomplete="off"></div>
                        </div>
                    </div>

                    <div class="vf-sheet-grid vf-sheet-grid--extra">
                        <div class="vf-sheet-cell vf-cell-off"><label>* Age Category <span class="cn">年龄分类</span></label>
                            <select name="AgeCategory" id="bf_AgeCategory">
                                <option value="ADULT">ADULT (35 Spots)</option>
                                <option value="CHILDREN">CHILDREN (24 Spots)</option>
                                <option value="KIDS">KIDS (16 Spots)</option>
                                <option value="TODDLER">TODDLER (14 Spots)</option>
                            </select>
                        </div>
                        <div class="vf-sheet-cell vf-cell-off"><label>* Standard Spec (kgf/cm)</label>
                            <select name="STD" id="bf_STD">
                                <option value="3.0">3.0 kgf/cm (Adult / VF-F01)</option>
                                <option value="2.5">2.5 kgf/cm (Children)</option>
                                <option value="2.0">2.0 kgf/cm (Kids/Toddler)</option>
                            </select>
                        </div>
                        <div class="vf-sheet-cell vf-cell-off"><label>Shoe Side</label>
                            <select name="Side" id="bf_Side">
                                <option value="Left">Left</option>
                                <option value="Right">Right</option>
                            </select>
                        </div>
                        <div class="vf-sheet-cell vf-cell-off"><label>Mode of Failure(s) <span class="cn">失败的方式</span> <small style="font-weight:400;color:#b45309" title="A Fail or Tolerable result cannot be saved without it">(needed for Fail / Tolerable)</small></label><input type="text" name="ModeOfFailure" id="bf_ModeOfFailure"></div>
                        <div class="vf-sheet-cell vf-cell-off"><label>Possible Cause(s) <span class="cn">可能的原因</span></label><input type="text" name="PossibleCause" id="bf_PossibleCause"></div>
                    </div>
                    <script>
                    (function () {
                        var sel = document.getElementById('bf_MaterialSource');
                        var wrap = document.getElementById('bf_MaterialSource_radios');
                        if (!sel || !wrap) return;
                        var radios = wrap.querySelectorAll('input[type=radio]');
                        function sync() { radios.forEach(function (r) { r.checked = (r.value === sel.value); }); }
                        var d = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
                        Object.defineProperty(sel, 'value', {
                            configurable: true,
                            get: function () { return d.get.call(this); },
                            set: function (v) { d.set.call(this, v); sync(); }
                        });
                        radios.forEach(function (r) {
                            r.addEventListener('change', function () {
                                if (!r.checked) return;
                                d.set.call(sel, r.value);
                                sel.dispatchEvent(new Event('change', { bubbles: true }));
                            });
                        });
                        sync();
                    })();
                    </script>
                </div>

            <!-- Results table / shoe diagram / sign-off — mirrors the VF-F01 template body (same box as the info sheet above, no gap) -->
            <div class="vf-sheet vf-body" id="vf-body">
                <table class="vf-res">
                    <thead>
                        <tr>
                            <th rowspan="2" class="vf-res-part">Shoe Part</th>
                            <th rowspan="2" class="vf-res-w1">VF Test<br>Method</th>
                            <th rowspan="2" class="vf-res-w2">STD<br>kgf/cm</th>
                            <th colspan="3">Test Result<br>kgf/cm</th>
                            <th colspan="2">Status</th>
                            <th rowspan="2" class="vf-res-mode">Mode of Failure(s)<br><span class="cn">失败的方式</span></th>
                            <th rowspan="2" class="vf-res-cause">Possible Cause(s)<br><span class="cn">可能的原因</span></th>
                        </tr>
                        <tr>
                            <th class="vf-res-n">Min</th><th class="vf-res-n">Max</th><th class="vf-res-n">Avg</th>
                            <th class="vf-res-s">Pass</th><th class="vf-res-s">Fail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="vf-res-part-cell">UPPER TO FOXING</td>
                            <td class="vf-res-c">VF-F01<br>(2019/05)</td>
                            <td class="vf-res-c vf-res-std"><b class="vf-std-disp">3.00</b></td>
                            <td><input type="text" id="bp_UTFMin" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_UTFMax" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_UTFAverage" readonly class="vf-res-inp bold"></td>
                            <td class="vf-res-mark" data-for="bp_UTF_Result" data-side="pass"></td>
                            <td class="vf-res-mark" data-for="bp_UTF_Result" data-side="fail"></td>
                            <td rowspan="8" class="vf-res-legend">
                                <p><label class="vf-cause-opt"><input type="checkbox" class="vf-mode-chk" value="AF"> <span><b>AF=</b> Failure at the adhesive/substrate interface or the adhesive/primer interface</span></label></p>
                                <p><label class="vf-cause-opt"><input type="checkbox" class="vf-mode-chk" value="CF"> <span><b>CF=</b> Cohesive Failure of the adhesive (failure within the adhesive or between the two layers of adhesive).</span></label></p>
                                <p><label class="vf-cause-opt"><input type="checkbox" class="vf-mode-chk" value="PF"> <span><b>PF=</b> Failure at the primer/substrate interface</span></label></p>
                                <p><label class="vf-cause-opt"><input type="checkbox" class="vf-mode-chk" value="SF"> <span><b>SF=</b> Failure of the substrate, including the location of the failure within the substrate</span></label></p>
                            </td>
                            <td rowspan="8" class="vf-res-legend vf-res-causes">
                                <?php foreach ([['a', 'Poor Priming', '药水不良'], ['b', 'Poor cementing', '胶水不良'], ['c', 'Over-dried cement', '积胶'], ['d', 'Wet primer', '湿的药水'], ['e', 'Wet cement', '湿的胶水'], ['f', 'Poor pressing', '压机不良'], ['g', 'Poor Roughing', '打粗不良'], ['h', 'Upper material - weak', '鞋面材料不良'], ['i', 'EVA-Weak', 'EVA 不良'], ['j', 'Dusty component', '粉沫'], ['k', 'Splitting of foxing tape', '围条分层']] as [$cl, $ce, $cc]): ?>
                                <p><label class="vf-cause-opt"><input type="checkbox" class="vf-cause-chk" value="(<?= $cl ?>) <?= $ce ?>"> <span>(<?= $cl ?>) <?= $ce ?> <span class="cn"><?= $cc ?></span></span></label></p>
                                <?php endforeach ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="vf-res-part-cell">FOXING TO OUTSOLE</td>
                            <td class="vf-res-c">VF-F01<br>(2019/05)</td>
                            <td class="vf-res-c vf-res-std"><b class="vf-std-disp">3.00</b></td>
                            <td><input type="text" id="bp_FTOMin" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_FTOMax" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_FTOAverage" readonly class="vf-res-inp bold"></td>
                            <td class="vf-res-mark" data-for="bp_FTO_Result" data-side="pass"></td>
                            <td class="vf-res-mark" data-for="bp_FTO_Result" data-side="fail"></td>
                        </tr>
                        <tr>
                            <td class="vf-res-part-cell">FILLER TO OUTSOLE <span class="vf-res-sub">(1..13)</span></td>
                            <td class="vf-res-c">VF-F01<br>(2019/05)</td>
                            <td class="vf-res-c vf-res-std"><b>1.00</b></td>
                            <td><input type="text" id="bp_FiTOMin" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_FiTOMax" readonly class="vf-res-inp"></td>
                            <td><input type="text" id="bp_FiTOAverage" readonly class="vf-res-inp bold"></td>
                            <td class="vf-res-mark" data-for="bp_FiTO_Result" data-side="pass"></td>
                            <td class="vf-res-mark" data-for="bp_FiTO_Result" data-side="fail"></td>
                        </tr>
                        <tr class="vf-res-blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr class="vf-res-blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr class="vf-res-blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr class="vf-res-blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr class="vf-res-blank"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                    </tbody>
                </table>

                <!-- hidden originals kept so dtr.js can read / write the per-part result -->
                <div class="vf-tpl-hidden" aria-hidden="true">
                    <input type="text" id="bp_UTF_Result" readonly class="dtr-rate-inp" tabindex="-1">
                    <input type="text" id="bp_FTO_Result" readonly class="dtr-rate-inp" tabindex="-1">
                    <input type="text" id="bp_FiTO_Result" readonly class="dtr-rate-inp" tabindex="-1">
                </div>

                <div class="vf-diag">
                    <div class="vf-diag-notes">
                        <div class="vf-diag-note-l">1.Outside Value: Fabric / PU / EVA (Left)kgf/cm<br>2.Inside Value:</div>
                        <div class="vf-diag-note-c">Center: Fabric / PU / EVA kgf/cm</div>
                        <div class="vf-diag-note-r">1.Outside Value:Fabric / PU / EVA kgf/cm<br>2.Inside Value:</div>
                    </div>
                    <div class="vf-diag-row">
                        <div id="vf-diag-left" class="vf-shoe-card vf-diag-card"><img src="<?= base_url('assets/images/bonding_sketches/blank.png') ?>" class="vf-shoe-img" style="left:21%;top:8%;width:58%;height:84%" alt="Left shoe"></div>
                        <div class="vf-diag-strips">
                            <div><span class="vf-diag-lbl is-active">Left</span><div class="vf-diag-strip is-active"><?= str_repeat('<div class="vf-diag-slot"><span class="vf-diag-val">&nbsp;</span><i class="vf-diag-line"></i></div>', 6) ?></div></div>
                            <div><span class="vf-diag-lbl">Right</span><div class="vf-diag-strip"><?= str_repeat('<div class="vf-diag-slot"><span class="vf-diag-val">&nbsp;</span><i class="vf-diag-line"></i></div>', 6) ?></div></div>
                        </div>
                        <div id="vf-diag-right" class="vf-shoe-card vf-diag-card"><img src="<?= base_url('assets/images/bonding_sketches/blank.png') ?>" class="vf-shoe-img" style="left:21%;top:8%;width:58%;height:84%;transform:scaleX(-1)" alt="Right shoe"></div>
                    </div>
                </div>

                <div class="vf-foot">
                    <div class="vf-foot-comments"><label for="bf_Remarks">Comments <span class="cn">备注:</span></label><input type="text" name="Remarks" id="bf_Remarks"></div>
                    <div class="vf-foot-signs">
                        <div class="vf-foot-sign"><label for="bf_ReviewedBy">Reviewed by <span class="cn">审查:</span></label><input type="text" name="ReviewedBy" id="bf_ReviewedBy"></div>
                        <div class="vf-foot-sign"><label for="vf_ApprovedBy">Approved by <span class="cn">核准:</span></label><input type="text" id="vf_ApprovedBy" value="QA Laboratory Head" readonly tabindex="-1" title="Set by the approval signatory (Report Designer → Signatories); not saved with the report"></div>
                    </div>
                </div>

                <script src="<?= base_url('assets/js/views/dtr-index-1.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/views/dtr-index-1.js') ?>"></script>
            </div>
            </div>

            <!-- 35-Spot Interactive Grid -->
            <div class="dtr-subcard" id="dtr-bonding-spots-card" hidden>
                <div class="dtr-subcard-head">
                    <h4>Bonding Spot Readings (Points 1 to 35)</h4>
                    <span class="muted sm">Tolerance Rule (80% of Std, per VF-F01): Std 3.0 (Adult) &rarr; TOLERABLE 2.4..2.99, FAIL &lt;2.4. Std 2.5 (Children) &rarr; TOLERABLE 2.0..2.49, FAIL &lt;2.0. Std 2.0 (Kids/Toddler) &rarr; TOLERABLE 1.6..1.99, FAIL &lt;1.6.</span>
                </div>
                <div class="dtr-table-wrap" style="max-height:420px">
                    <table class="dtr-table dtr-mech-table">
                        <thead>
                            <tr>
                                <th style="width:90px">Spot #</th>
                                <th>Upper to Foxing (kg/cm)</th>
                                <th>Foxing to Outsole (kg/cm)</th>
                                <th>Filler to Outsole (1..13 kg/cm)</th>
                            </tr>
                        </thead>
                        <tbody id="dtr-bonding-points-tbody">
                            <?php for ($pt = 1; $pt <= 35; $pt++): ?>
                                <tr data-spot-row="<?= $pt ?>">
                                    <td class="mono"><b>Spot <?= $pt ?></b></td>
                                    <td><input type="number" step="any" id="bp_UTFpoint<?= $pt ?>" data-bp-col="UTFpoint<?= $pt ?>" class="dtr-bp-inp"></td>
                                    <td><input type="number" step="any" id="bp_FTOpoint<?= $pt ?>" data-bp-col="FTOpoint<?= $pt ?>" class="dtr-bp-inp"></td>
                                    <td>
                                        <?php if ($pt <= 13): ?>
                                            <input type="number" step="any" id="bp_FiTO<?= $pt ?>" data-bp-col="FiTO<?= $pt ?>" class="dtr-bp-inp">
                                        <?php else: ?>
                                            <span class="muted sm">—</span>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endfor ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        <!-- ===== Pull Footwear Test Results (Form # Lab VF 003), shown when "Pull Test Report" is picked ===== -->
        <?php
            $pullItems = [
                ['Eyelet claw strength', ''],
                ['Hook&amp;eyelet attachment metal', ''],
                ['Hook&amp;eyelet attachment carted metal', ''],
                ['Hook&amp;eyelet attachment - plastic', ''],
                ['Webbing loop holder', ''],
                ['Webbing Heel Pull Tab', '35'],
            ];
            $pullSketch = static function (string $side): string {
                $eyelets = '';
                foreach ([[30, 70], [30, 100], [30, 133], [30, 163], [30, 196]] as $p) {
                    $eyelets .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="4.2"/><circle cx="' . (168 - $p[0]) . '" cy="' . $p[1] . '" r="4.2"/>';
                }

                // upper outline with a thick band (outer + inner edge), eyelets in the band, flared heel sides in blue
                return '<svg viewBox="0 0 168 250" class="pf-shoe" role="img" aria-label="' . $side . ' shoe"><text x="84" y="13" text-anchor="middle" class="pf-shoe-t">' . $side . '</text>'
                    . '<path d="M20 214 L20 78 C20 36 44 24 84 24 C124 24 148 36 148 78 L148 214" fill="#fff" stroke="#111" stroke-width="1.6"/>'
                    . '<path d="M40 214 L40 84 C40 54 56 42 84 42 C112 42 128 54 128 84 L128 214" fill="none" stroke="#111" stroke-width="1.6"/>'
                    . '<path d="M20 214 L2 240 M148 214 L166 240" fill="none" stroke="#2563eb" stroke-width="1.2"/>'
                    . '<path d="M40 214 L36 230 M128 214 L132 230" fill="none" stroke="#111" stroke-width="1.2"/>'
                    . '<g fill="#fff" stroke="#111" stroke-width="1.2">' . $eyelets . '</g></svg>';
            };
        ?>
        <form id="dtr-pull-form" onsubmit="return false;" hidden>
            <input type="hidden" name="ID" id="pf_ID" value="">
            <div class="vf-sheet pf-sheet">
                <div class="vf-tpl-head">
                    <img class="vf-tpl-logo" src="<?= base_url('assets/images/vf-logo.webp') ?>" alt="VF">
                    <div class="vf-tpl-title">
                        <div class="vf-tpl-title-en">Pull Footwear Test Results</div>
                        <div class="vf-tpl-title-cn">整鞋部件拉力测试</div>
                    </div>
                    <div class="vf-tpl-right">
                        <div class="vf-tpl-result"><label for="pf_Result">* Overall Result</label><input type="text" name="Result" id="pf_Result" class="bold mono dtr-rate-inp" readonly></div>
                        <img class="vf-tpl-satra" src="<?= base_url('assets/images/satra-accredited.png') ?>" alt="SATRA Accredited Laboratory">
                    </div>
                </div>

                <div class="vf-tpl-ids">
                    <div class="vf-tpl-box vf-tpl-formno">Form # : Lab VF 003</div>
                    <div class="vf-tpl-box"><label for="pf_ETRFNo">ETRF No:</label><input type="text" name="ETRFNo" id="pf_ETRFNo" class="mono"></div>
                    <div class="vf-tpl-box"><label for="pf_LabRefNo">* Lab Ref.No:</label><input type="text" name="LabRefNo" id="pf_LabRefNo" class="mono bold"></div>
                </div>

                <div class="vf-tpl-body">
                    <div class="vf-tpl-col">
                        <div class="vf-tpl-line"><label for="pf_Supplier">Supplier/Factory (厂商):</label><input type="text" name="Supplier" id="pf_Supplier"></div>
                        <div class="vf-tpl-line"><label for="pf_SupplierCountry">Supplier's country (国家):</label><input type="text" name="SupplierCountry" id="pf_SupplierCountry"></div>
                        <div class="vf-tpl-line"><label for="pf_MaterialDescription">Material Description (材料):</label><input type="text" name="MaterialDescription" id="pf_MaterialDescription"></div>
                        <div class="vf-tpl-line"><label for="pf_ModelNoStyle">Model No./Style (鞋体):</label><input type="text" name="ModelNoStyle" id="pf_ModelNoStyle"></div>
                        <div class="vf-tpl-line"><label for="pf_Color">Color (颜色):</label><input type="text" name="Color" id="pf_Color"></div>
                        <div class="vf-tpl-line"><label for="pf_POLotNo">P.O/Lot No (订单号码):</label><input type="text" name="POLotNo" id="pf_POLotNo" class="mono"></div>
                        <div class="vf-tpl-line"><label for="pf_POQuantity">PO Quantity (订单数量):</label><input type="text" name="POQuantity" id="pf_POQuantity"></div>
                        <div class="vf-tpl-line"><label for="pf_SendBy">Send by (送达方):</label><input type="text" name="SendBy" id="pf_SendBy"></div>
                    </div>
                    <div class="vf-tpl-col">
                        <input type="hidden" name="MaterialSource" id="pf_MaterialSource">
                        <div class="vf-tpl-radios" id="pf_MaterialSource_radios" role="radiogroup" aria-label="Test Type">
                            <label><input type="radio" name="pf_ms_radio" value="Prod. Material"> Prod. Material 量产材料</label>
                            <label><input type="radio" name="pf_ms_radio" value="Prod. Re-test"> Prod. Re-test 量产重测</label>
                            <label><input type="radio" name="pf_ms_radio" value="New/Development"> New/Development 开发新材料</label>
                            <label><input type="radio" name="pf_ms_radio" value="Trial Test"> Trial Test 试胶测试</label>
                            <label><input type="radio" name="pf_ms_radio" value="Sales Sample"> Sales Sample 销售样品</label>
                        </div>
                        <input type="hidden" name="TechSlot" id="pf_TechSlot">
                        <div class="vf-tpl-radios pf-techs" id="pf_TechSlot_radios" role="radiogroup" aria-label="Technician slot">
                            <?php for ($t = 1; $t <= 4; $t++): ?>
                                <label><input type="radio" name="pf_tech_radio" value="Tech <?= $t ?>"> Tech <?= $t ?></label>
                            <?php endfor ?>
                        </div>
                        <div class="vf-tpl-line"><label for="pf_ReceivedDate">Received Date 收到日期:</label><input type="text" name="ReceivedDate" id="pf_ReceivedDate"></div>
                        <div class="vf-tpl-line"><label for="pf_TestedDate">Tested Date 测试日期:</label><input type="text" name="TestedDate" id="pf_TestedDate"></div>
                        <div class="vf-tpl-line"><label for="pf_FinishedDate">Finished Date 完成日期:</label><input type="text" name="FinishedDate" id="pf_FinishedDate"></div>
                        <div class="vf-tpl-line"><label for="pf_Technician">Technician 技术员:</label><input type="text" name="Technician" id="pf_Technician" list="dtr-tech-list" autocomplete="off"></div>                    </div>
                </div>

                <div class="pf-section">Closures - Eyelet , D-ring , Hook , Webbing Pull Strength</div>
                <div class="pf-wrap">
                <table class="vf-res pf-res">
                    <thead>
                        <tr>
                            <th rowspan="2" class="pf-c-item">Test Items<br><span class="cn">测试项目</span></th>
                            <th rowspan="2" class="pf-c-m">VF Test Method<br><span class="cn">测试方法</span></th>
                            <th rowspan="2" class="pf-c-u">Units<br><span class="cn">单位</span></th>
                            <th rowspan="2" class="pf-c-sk">Test Sketch<br><span class="cn">测试部位</span></th>
                            <th rowspan="2" class="pf-c-res">Test Results<br><span class="cn">测试结果</span></th>
                            <th rowspan="2" class="pf-c-n">Maximum</th>
                            <th rowspan="2" class="pf-c-n">Standard<br><span class="cn">标准</span></th>
                            <th rowspan="2" class="pf-c-n">Tolerance<br><span class="cn">容许度</span></th>
                            <th rowspan="2" class="pf-c-n">Rating<br><span class="cn">等级</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (['left' => 'Left', 'right' => 'Right'] as $sk => $sideLabel): ?>
                            <?php foreach ($pullItems as $i => $it): ?>
                                <tr>
                                    <td class="vf-res-part-cell"><?= $it[0] ?></td>
                                    <td class="vf-res-c">VF-B04<br>(2019/05)</td>
                                    <td class="vf-res-c">kgf</td>
                                    <?php if ($i === 0): ?><td rowspan="<?= count($pullItems) + 4 ?>" class="pf-sketch"><?= $pullSketch($sideLabel) ?></td><?php endif ?>
                                    <td class="pf-res-cell"><?= $i === 0 ? '<span class="pf-res-lbl">Lateral side :</span>' : ($i === 1 ? '<span class="pf-res-lbl">Medial side :</span>' : ($i === 2 || $i === 3 ? '<input type="text" class="pf-res-mat" data-pull="' . $sk . '.' . $i . '.lbl" placeholder="Material" aria-label="Material (Pearl / Diamond)" spellcheck="false">' : '')) ?><textarea rows="1" class="vf-res-inp pf-res-ta" data-pull="<?= $sk ?>.<?= $i ?>.res" spellcheck="false"></textarea></td>
                                    <td><input type="text" readonly class="vf-res-inp bold" data-pull="<?= $sk ?>.<?= $i ?>.max"></td>
                                    <td><input type="text" class="vf-res-inp pf-std" data-pull="<?= $sk ?>.<?= $i ?>.std" value="<?= $it[1] ?>"></td>
                                    <td><input type="text" class="vf-res-inp pf-tol" data-pull="<?= $sk ?>.<?= $i ?>.tol" value="<?= $it[1] !== '' ? 'minimum' : '' ?>"></td>
                                    <td><input type="text" readonly class="vf-res-inp bold dtr-rate-inp" data-pull="<?= $sk ?>.<?= $i ?>.rating"></td>
                                </tr>
                            <?php endforeach ?>
                            <?php for ($e = 0; $e < 4; $e++): ?>
                                <tr class="pf-blank-row"><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                            <?php endfor ?>
                        <?php endforeach ?>
                        <tr><td colspan="9" class="pf-other-head">Other Types of Pull Test</td></tr>
                        <?php for ($o = 0; $o < 4; $o++): ?>
                            <tr>
                                <td><input type="text" class="vf-res-inp" data-pull="other.<?= $o ?>.item"></td>
                                <td><input type="text" class="vf-res-inp" data-pull="other.<?= $o ?>.method"></td>
                                <td><input type="text" class="vf-res-inp" data-pull="other.<?= $o ?>.unit"></td>
                                <td></td>
                                <td><textarea rows="1" class="vf-res-inp pf-res-ta" data-pull="other.<?= $o ?>.res" spellcheck="false"></textarea></td>
                                <td><input type="text" readonly class="vf-res-inp bold" data-pull="other.<?= $o ?>.max"></td>
                                <td><input type="text" class="vf-res-inp pf-std" data-pull="other.<?= $o ?>.std"></td>
                                <td><input type="text" class="vf-res-inp pf-tol" data-pull="other.<?= $o ?>.tol"></td>
                                <td><input type="text" readonly class="vf-res-inp bold dtr-rate-inp" data-pull="other.<?= $o ?>.rating"></td>
                            </tr>
                        <?php endfor ?>
                    </tbody>
                </table>
                </div>

                <div class="pf-foot">
                    <div class="pf-comments"><label for="pf_Comments">Comments 备注 :</label><textarea name="Comments" id="pf_Comments" rows="2"></textarea></div>
                    <div class="pf-sign">
                        <div class="vf-tpl-line"><label for="pf_ReviewedBy">Reviewed by 检查:</label><input type="text" name="ReviewedBy" id="pf_ReviewedBy"></div>
                        <div class="vf-tpl-line"><label for="pf_ApprovedBy">Approved by 核准:</label><input type="text" name="ApprovedBy" id="pf_ApprovedBy"></div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
