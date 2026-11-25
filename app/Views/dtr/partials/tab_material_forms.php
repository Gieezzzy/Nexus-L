<?php /* DTR partial: Material Test Report forms (all Access forms + auto compute). Included by dtr/index.php, shares its variables. */ ?>
<!-- =======================================================================
     TAB 2: MATERIAL TEST REPORT FORMS (ALL 10 ACCESS FORMS + AUTO COMPUTE)
     ======================================================================= -->
<section id="dtr-panel-material" class="dtr-panel <?= $activeTab === 'material' ? '' : 'hidden' ?>" data-can-edit="<?= ! empty($canEditMaterial) ? '1' : '0' ?>">
    <!-- Form Selector & Toolbar -->
    <div class="card glass dtr-section-card">
        <select id="dtr-mat-record-select" hidden style="display:none"></select>

        <!-- Overall Rating Stamp Banner -->
        <div class="dtr-stamp-banner" id="dtr-mat-banner" hidden style="display:none">
            <div>
                <span class="dtr-eyebrow" id="dtr-active-form-label">Fabric Test Report (FBU / FBL)</span>
                <h3 id="dtr-active-labref-title">Loading record...</h3>
                <span class="muted sm" id="dtr-machine-status">Ready · Automatic calculations active</span>
            </div>
            <div class="dtr-stamp-box">
                <div class="dtr-stamp dtr-stamp-pass" id="dtr-stamp-badge">PASS</div>
            </div>
        </div>

        <?php
        // ===== Leather Test Results (Form # Lab VF 006): the printed VF layout, one row per VF test method. data-col = column of tbl_dtr_leather_results.
        $lfAttr = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
        $lfIn   = static fn (string $col, string $cls = ''): string => '<input type="text" inputmode="decimal" autocomplete="off" class="vf-res-inp lf-in ' . $cls . '" data-col="' . htmlspecialchars($col, ENT_QUOTES) . '">';
        $lfOut  = static fn (string $col, string $cls = ''): string => '<input type="text" readonly tabindex="-1" class="vf-res-inp bold lf-out ' . $cls . '" data-col="' . htmlspecialchars($col, ENT_QUOTES) . '">';
        $lfRate = static fn (string $col): string => '<input type="text" readonly tabindex="-1" class="vf-res-inp bold lf-out dtr-rate-inp" data-col="' . htmlspecialchars($col, ENT_QUOTES) . '">';
        $lfStd  = static fn (string $col): string => '<input type="text" inputmode="decimal" autocomplete="off" class="vf-res-inp lf-in lf-std" data-col="' . htmlspecialchars($col, ENT_QUOTES) . '">';
        $lfFib  = ['Wool' => '羊毛', 'Acrylic' => '腈纶', 'Polyester' => '涤纶', 'Nylon' => '锦纶', 'Cotton' => '棉', 'Acetate' => '醋酸纤维'];
        // line: ['lab' => label, 'in' => [cols], 'fib' => prefix of the six fiber columns, 'mm' => [max, min cols], 'ave' => col, 'std' => col, 'rate' => col]; no keys = a printed row left blank
        $lfRowsLeather = [
            ['Gauge', '规格', 'VF-A03<br>(2014/04)', 'mm', true, [['in' => ['Thickness1', 'Thickness2', 'Thickness3', 'Thickness4', 'Thickness5', 'Thickness6'], 'mm' => ['TMax', 'TMin'], 'ave' => 'TAve', 'std' => 'T_S', 'rate' => 'TRating']]],
            ['Weight', '重量', 'VF-A02<br>(2018/11)', 'g/m²', true, [['in' => ['Weight1', 'Weight2', 'Weight3'], 'ave' => 'WAve', 'std' => 'Weight_S', 'rate' => 'WRating']]],
            ['Softness', '柔软度', 'VF-A06<br>(2014/08)', 'mm', false, [[]]],
            ['Tensile', '抗拉', 'VF-B01<br>(2022/11)', 'kgf/cm', true, [
                ['lab' => 'L=', 'in' => ['TensileL1', 'TensileL2', 'TensileL3'], 'ave' => 'TensileLAve', 'std' => 'Tensile_L', 'rate' => 'TensileLRating'],
                ['lab' => 'W=', 'in' => ['TensileW1', 'TensileW2', 'TensileW3'], 'ave' => 'TensileWAve', 'std' => 'Tensile_W', 'rate' => 'TensileWRating']]],
            ['Elongation', '延伸', 'VF-B01<br>(2022/11)', '%', true, [
                ['lab' => 'L=', 'in' => ['ElongationL1', 'ElongationL2', 'ElongationL3'], 'ave' => 'ElongationLAve', 'std' => 'Elongation_L', 'rate' => 'ElongationLRating'],
                ['lab' => 'W=', 'in' => ['ElongationW1', 'ElongationW2', 'ElongationW3'], 'ave' => 'ElongationWAve', 'std' => 'Elongation_W', 'rate' => 'ElongationWRating']]],
            ['Trouser Tear', '裤形撕裂', 'VF-B07<br>(2022/11)', 'kgf', true, [
                ['lab' => 'L=', 'in' => ['Trouser TearL1', 'Trouser TearL2', 'Trouser TearL3'], 'ave' => 'TrouserTearLAve', 'std' => 'TrouserTear_L', 'rate' => 'TrouserTearLRating'],
                ['lab' => 'W=', 'in' => ['Trouser TearW1', 'Trouser TearW2', 'Trouser TearW3'], 'ave' => 'TrouserTearWAve', 'std' => 'TrouserTear_W', 'rate' => 'TrouserTearWRating']]],
            ['Baumann Tear', 'Baumann 撕裂', 'VF-B09<br>(2019/05)', 'kgf', true, [
                ['lab' => 'L=', 'in' => ['BaumannTearL1', 'BaumannTearL2', 'BaumannTearL3'], 'ave' => 'BaumannTearLAve', 'std' => 'BaumannTearL_S', 'rate' => 'BaumannTearL_Rating'],
                ['lab' => 'W=', 'in' => ['BaumannTearW1', 'BaumannTearW2', 'BaumannTearW3'], 'ave' => 'BaumannTearWAve', 'std' => 'BaumannTearW_S', 'rate' => 'BaumannTearW_Rating']]],
            ['Bally Flex', '耐挠', 'VF-D01<br>(2019/06)', 'cycles', true, [
                ['lab' => 'Dry 干: L1 / L2', 'in' => ['BallyDryL1', 'BallyDryL2'], 'std' => 'BallyDryL_S', 'rate' => 'BallyDryL_R'],
                ['lab' => 'Dry 干: W1 / W2', 'in' => ['BallyDryW1', 'BallyDryW2'], 'std' => 'BallyDryW_S', 'rate' => 'BallyDryW_R'],
                ['lab' => 'Wet 湿: L1 / L2', 'in' => ['BallyWetL1', 'BallyWetL2'], 'std' => 'BallyWetL_S', 'rate' => 'BallyWetL_R'],
                ['lab' => 'Wet 湿: W1 / W2', 'in' => ['BallyWetW1', 'BallyWetW2'], 'std' => 'BallyWetW_S', 'rate' => 'BallyWetW_R'],
                ['lab' => 'After hydrolysis: L1 / L2', 'in' => ['BallyAHL1', 'BallyAHL2'], 'std' => 'BallyAHL_S', 'rate' => 'BallyAHL_R'],
                ['lab' => 'After hydrolysis: W1 / W2', 'in' => ['BallyAHW1', 'BallyAHW2'], 'std' => 'BallyAHW_S', 'rate' => 'BallyAHW_R'],
                ['lab' => 'Freezing L1 / L2'], ['lab' => 'Freezing W1 / W2']]],
            ['Martindale Abrasion', '耐磨', 'VF-C04<br>(2024/12)', 'cycles', true, [
                ['lab' => 'Dry 干=', 'in' => ['MartindaleD1', 'MartindaleD2'], 'std' => 'MartindaleD1_S', 'rate' => 'MartindaleD1Rating'],
                ['lab' => 'Wet 湿=', 'in' => ['MartindaleW1', 'MartindaleW2'], 'std' => 'MartindaleW1_S', 'rate' => 'MartindaleW1Rating']]],
            ['Taber Abrasion H-22/1000g', '', 'VF-C05<br>(2024/12)', 'cycles', true, [['in' => ['TaberAbrasion1', 'TaberAbrasion2'], 'std' => 'Taber_S', 'rate' => 'TaberRating']]],
            ['Mullen Burst', '爆破', 'VF-B02<br>(2019/05)', 'kgf/cm²', true, [['in' => ['MullenBurst1', 'MullenBurst2', 'MullenBurst3'], 'ave' => 'MullenBurstAve', 'std' => 'MullenBurst', 'rate' => 'MullenBurstRating']]],
            ['Colorfastness to Perspiration<br>(Top Side/Finished Surface Only)', '耐汗渍测试(正面)', 'VF-E16<br>(2018/11)', 'AATCC', true, [['fib' => 'CtP', 'std' => 'CTP', 'rate' => 'CTPRating']]],
            ['Colorfastness to Perspiration<br>Back side', '耐汗渍测试(反面)', 'VF-E16<br>(2018/11)', 'AATCC', true, [['fib' => 'CtPB', 'std' => 'CtPB_S', 'rate' => 'CtPB_Rating']]],
            ['Colorfastness to Water<br>(Top Side/Finished Surface Only)', '耐水测试(正面)', 'VF-E01<br>(2008/05)', 'AATCC', true, [['fib' => 'CtW', 'std' => 'CTW', 'rate' => 'CTWRating']]],
            ['Colorfastness to Water<br>Back side', '耐水测试(反面)', 'VF-E01<br>(2008/05)', 'AATCC', true, [['fib' => 'CtWBackSide', 'std' => 'CtWB_S', 'rate' => 'CtWB_Rating']]],
            ['UV light (24hrs, 50°C)', '紫外光', 'VF-E03<br>(2019/05)', 'AATCC', true, [['in' => ['UV1', 'UV2'], 'std' => 'UV_S', 'rate' => 'UVRating']]],
            ['Colorfastness to Crocking', '摩擦褪色', 'VF-E02<br>(2024/08)', 'AATCC', true, [
                ['lab' => 'Dry 干=', 'in' => ['CtCDry'], 'std' => 'CtCDry_S', 'rate' => 'CTCDRYRating'],
                ['lab' => 'Wet 湿=', 'in' => ['CtCWet'], 'std' => 'CtCWet_S', 'rate' => 'CTCWETRating']]],
            ['Abrasion Crocking', '摩擦', 'VF-E02<br>(2024/08)', 'AATCC', true, [
                ['lab' => 'Dry 干=', 'in' => ['CADry'], 'rate' => 'AbrasionCrockingDRY_Rating'],
                ['lab' => 'Wet 湿=', 'in' => ['CAWet'], 'rate' => 'AbrasionCrockingWET_Rating']]],
            ['Ink adhesion on Substrate<br>3M Scotch tape #600', '', 'VF-F05<br>(2014/07)', '', true, [
                ['lab' => 'Dry 干=', 'in' => ['IAoSDry'], 'rate' => 'InkAdhesionOSDRY_Rating'],
                ['lab' => 'Wet 湿=', 'in' => ['IAoSWet'], 'rate' => 'InkAdhesionOSWET_Rating']]],
            ['PVC Migration', '', 'VF-E06<br>(2014/07)', 'AATCC', false, [['in' => ['PVC_1', 'PVC_2'], 'std' => 'PVC_S', 'rate' => 'PVC_Rating']]],
            ['Reaction with solvents', '溶剂反应', 'VF-I03<br>(2012/08)', '', false, [['lab' => 'Acetone 丙酮:'], ['lab' => 'Ethyl Acetate 乙酸乙酯:'], ['lab' => 'MEK 丁酮:']]],
            ['Wicking', '吸湿', 'VF-E14<br>(2014/07)', 'mm', false, [[]]],
            ['Maeser Test', 'Maeser 防水测试', 'VF-E11<br>(2018/11)', 'cycles', false, [[]]],
            ['Peeling Strength', '剥离强度', 'VF-F03<br>(2024/12)', 'kgf/cm', false, [[]]],
            ['Glow in the dark', '', 'VF-E28<br>(2024/08)', '', false, [['lab' => 'D65 / CWF'], ['lab' => 'After Washing D65 / CWF']]],
            ['Heat Aging (72hrs, 70°C)', '热老化', 'VF-E04<br>(2022/09)', '', true, [['in' => ['HeatAging1', 'HeatAging2'], 'std' => 'HeatAging_S', 'rate' => 'HeatAgingRating']]],
            ['Hydrolysis (7Days, 60°C, 95%RH)', '水解', 'VF-E19<br>(2024/12)', '', true, [['in' => ['Hydrolysis1', 'Hydrolysis2'], 'std' => 'Hydrolysis_S', 'rate' => 'HydrolysisRating']]],
        ];
        // ===== Fabric Test Results (Form # Lab VF 005): same printed layout, columns of tbl_dtr_fabric_results (no Bally columns exist, so those printed lines stay blank)
        $lfRowsFabric = [
            ['Gauge', '规格', 'VF-A01<br>(2016/12)', 'mm', true, [['in' => ['Thickness1', 'Thickness2', 'Thickness3', 'Thickness4', 'Thickness5', 'Thickness6'], 'mm' => ['TMax', 'TMin'], 'ave' => 'TAve', 'std' => 'T_S', 'rate' => 'TRating']]],
            ['Weight', '重量', 'VF-A02<br>(2018/11)', 'g/m²', true, [['in' => ['Weight1', 'Weight2', 'Weight3'], 'ave' => 'WAve', 'std' => 'Weight_S', 'rate' => 'WRating']]],
            ['Yarn Count', '经纬密度', 'VF-A07<br>(2008/05)', '# of yarns', true, [
                ['lab' => 'Warp :', 'in' => ['YCWarp1', 'YCWarp2'], 'ave' => 'YCWaAve', 'rate' => 'YCWaRating'],
                ['lab' => 'Welf :', 'in' => ['YCWelf1', 'YCWelf2'], 'ave' => 'YCWeAve', 'rate' => 'YCWeRating']]],
            ['Tensile', '抗拉', 'VF-B01<br>(2022/11)', 'kgf/cm', true, [
                ['lab' => 'L=', 'in' => ['TensileL1', 'TensileL2', 'TensileL3'], 'ave' => 'TensileLAve', 'std' => 'Tensile_L', 'rate' => 'TensileLRating'],
                ['lab' => 'W=', 'in' => ['TensileW1', 'TensileW2', 'TensileW3'], 'ave' => 'TensileWAve', 'std' => 'Tensile_W', 'rate' => 'TensileWRating']]],
            ['Elongation', '延伸', 'VF-B01<br>(2022/11)', '%', true, [
                ['lab' => 'L=', 'in' => ['ElongationL1', 'ElongationL2', 'ElongationL3'], 'ave' => 'ElongationLAve', 'std' => 'Elongation_L', 'rate' => 'ElongationLRating'],
                ['lab' => 'W=', 'in' => ['ElongationW1', 'ElongationW2', 'ElongationW3'], 'ave' => 'ElongationWAve', 'std' => 'Elongation_W', 'rate' => 'ElongationWRating']]],
            ['Trouser Tear', '裤形撕裂', 'VF-B07<br>(2022/11)', 'kgf', true, [
                ['lab' => 'L=', 'in' => ['Trouser TearL1', 'Trouser TearL2', 'Trouser TearL3'], 'ave' => 'TrouserTearLAve', 'std' => 'TrouserTear_L', 'rate' => 'TrouserTearLRating'],
                ['lab' => 'W=', 'in' => ['Trouser TearW1', 'Trouser TearW2', 'Trouser TearW3'], 'ave' => 'TrouserTearWAve', 'std' => 'TrouserTear_W', 'rate' => 'TrouserTearWRating']]],
            ['Stitch Tear', '车缝撕裂', 'VF-B08<br>(2019/05)', 'kgf/cm', true, [
                ['lab' => 'L=', 'in' => ['StitchTearL1', 'StitchTearL2', 'StitchTearL3'], 'ave' => 'StitchTearLAve', 'std' => 'StitchTear_L', 'rate' => 'StitchTearLRating'],
                ['lab' => 'W=', 'in' => ['StitchTearW1', 'StitchTearW2', 'StitchTearW3'], 'ave' => 'StitchTearWAve', 'std' => 'StitchTear_W', 'rate' => 'StitchTearWRating']]],
            ['Bally Flex', '耐挠', 'VF-D01<br>(2019/06)', 'cycles', false, [
                ['lab' => 'Dry 干: L1 / L2'], ['lab' => 'Dry 干: W1 / W2'], ['lab' => 'Wet 湿: L1 / L2'], ['lab' => 'Wet 湿: W1 / W2'],
                ['lab' => 'After hydrolysis 水解: L1 / L2'], ['lab' => 'After hydrolysis 水解: W1 / W2']]],
            ['Martindale Abrasion', '耐磨', 'VF-C04<br>(2024/06)', 'cycles', true, [
                ['lab' => 'Dry 干=Fabric:', 'in' => ['MartindaleD1'], 'std' => 'MartindaleD1_S', 'rate' => 'MartindaleD1Rating'],
                ['lab' => 'Dry 干=Ink:', 'in' => ['MartindaleD2'], 'std' => 'MartindaleD2_S', 'rate' => 'MartindaleD2Rating'],
                ['lab' => 'Wet 湿=Fabric:', 'in' => ['MartindaleW1'], 'std' => 'MartindaleW1_S', 'rate' => 'MartindaleW1Rating'],
                ['lab' => 'Wet 湿=Ink:', 'in' => ['MartindaleW2'], 'std' => 'MartindaleW2_S', 'rate' => 'MartindaleW2Rating']]],
            ['Taber Abrasion CS-17, 500g', '', 'VF-C05<br>(2024/12)', 'cycles', true, [['in' => ['TaberAbrasion1', 'TaberAbrasion2'], 'std' => 'Taber_S', 'rate' => 'TaberRating']]],
            ['Mullen Burst', '爆破', 'VF-B02<br>(2019/05)', 'kgf/cm²', true, [['in' => ['MullenBurst1', 'MullenBurst2', 'MullenBurst3'], 'ave' => 'MullenBurstAve', 'std' => 'MullenBurst', 'rate' => 'MullenBurstRating']]],
            ['Colorfastness to Perspiration<br>(Top Side/Finished Surface Only)', '耐汗渍测试(正面)', 'VF-E16<br>(2018/11)', 'AATCC', true, [['fib' => 'CtP', 'std' => 'CTP', 'rate' => 'CTPRating']]],
            ['Colorfastness to Perspiration<br>Back side', '耐汗渍测试(反面)', 'VF-E16<br>(2018/11)', 'AATCC', true, [['fib' => 'CtPB', 'std' => 'CtPB_S', 'rate' => 'CtPB_Rating']]],
            ['Colorfastness to Water<br>(Top Side/Finished Surface Only)', '耐水测试(正面)', 'VF-E01<br>(2008/05)', 'AATCC', true, [['fib' => 'CtW', 'std' => 'CTW', 'rate' => 'CTWRating']]],
            ['Colorfastness to Water<br>Back side', '耐水测试(反面)', 'VF-E01<br>(2008/05)', 'AATCC', true, [['fib' => 'CtWBackSide', 'std' => 'CtWB_S', 'rate' => 'CtWB_Rating']]],
            ['UV light (24hrs, 50°C)', '紫外光', 'VF-E03<br>(2019/05)', 'AATCC', true, [['in' => ['UV1', 'UV2'], 'std' => 'UV_S', 'rate' => 'UVRating']]],
            ['Colorfastness to Crocking', '摩擦褪色', 'VF-E02<br>(2024/08)', 'AATCC', true, [
                ['lab' => 'Dry 干=', 'in' => ['CtCDry'], 'std' => 'CtCDry_S', 'rate' => 'CTCDRYRating'],
                ['lab' => 'Wet 湿=', 'in' => ['CtCWet'], 'std' => 'CtCWet_S', 'rate' => 'CTCWETRating']]],
            ['Abrasion Crocking', '摩擦', 'VF-E02<br>(2024/08)', 'AATCC', true, [
                ['lab' => 'Dry 干=', 'in' => ['CADry'], 'rate' => 'AbrasionCrockingDRY_Rating'],
                ['lab' => 'Wet 湿=', 'in' => ['CAWet'], 'rate' => 'AbrasionCrockingWET_Rating']]],
            ['Ink adhesion on Substrate<br>3M Scotch tape #600', '', 'VF-F05<br>(2014/07)', '', true, [
                ['lab' => 'Dry 干=', 'in' => ['IAoSDry'], 'rate' => 'InkAdhesionOSDRY_Rating'],
                ['lab' => 'Wet 湿=', 'in' => ['IAoSWet'], 'rate' => 'InkAdhesionOSWET_Rating']]],
            ['Reaction with solvents', '溶剂反应', 'VF-I03<br>(2012/08)', '', false, [['lab' => 'Acetone 丙酮:'], ['lab' => 'Ethyl Acetate 乙酸乙酯:'], ['lab' => 'MEK 丁酮:']]],
            ['Peeling Strength', '剥离强度', 'VF-F03<br>(2024/12)', 'kgf/cm', false, [[]]],
            ['Wicking', '吸湿', 'VF-E14<br>(2014/07)', 'mm', false, [[]]],
            ['Maeser Test', 'Maeser 防水测试', 'VF-E11<br>(2018/11)', 'cycles', false, [[]]],
            ['Glow in the dark', '', 'VF-E28<br>(2024/08)', '', false, [['lab' => 'D65 / CWF'], ['lab' => 'After Washing D65 / CWF']]],
            ['Heat Aging (72hrs, 70°C)', '热老化', 'VF-E04<br>(2022/09)', '', true, [['in' => ['HeatAging1', 'HeatAging2'], 'std' => 'HeatAging_S', 'rate' => 'HeatAgingRating']]],
            ['Hydrolysis (7Days, 60°C, 95%RH)', '水解', 'VF-E19<br>(2024/12)', '', true, [['in' => ['Hydrolysis1', 'Hydrolysis2'], 'std' => 'Hydrolysis_S', 'rate' => 'HydrolysisRating']]],
        ];
        $lfSheets = [
            'leather' => ['Leather Test Results', '皮料测试结果', 'Lab VF 006', $lfRowsLeather],
            'fabric'  => ['Fabric Test Results', '布料测试结果', 'Lab VF 005', $lfRowsFabric],
        ] + (include APPPATH . 'Views/dtr/lf_sheets_more.php');   // webbing, foxing/heel, hardware, label, sockliner/foam, rubber, box, insole
        ?>
        <?php
        // description cells of the Access forms (a selection of what the lab wrote: "No damage or No abrasion of ink" ...): input column => [text column, list]
        $lfOpts   = include APPPATH . 'Views/dtr/lf_options.php';
        $lfTxtMap = [
            'fabric'  => ['MartindaleD1' => ['txtMartindaleD1', 'martindale'], 'MartindaleD2' => ['txtMartindaleD2', 'martindale'], 'MartindaleW1' => ['txtMartindaleW1', 'martindale'], 'MartindaleW2' => ['txtMartindaleW2', 'martindale'],
                'Hydrolysis1' => ['HydrolysisTxt1', 'hydro'], 'HeatAging1' => ['HeatAgingTxt1', 'heat']],
            'leather' => ['MartindaleD1' => ['txtMartindaleD1', 'martindale'], 'MartindaleD2' => ['txtMartindaleD2', 'martindale'], 'MartindaleW1' => ['txtMartindaleW1', 'martindale'], 'MartindaleW2' => ['txtMartindaleW2', 'martindale'],
                'TaberAbrasion1' => ['TaberAbrasiontxt1', 'taber'], 'TaberAbrasion2' => ['TaberAbrasiontxt2', 'taber'], 'Hydrolysis1' => ['HydrolysisTxt1', 'hydro'], 'HeatAging1' => ['HeatAgingTxt1', 'heat']],
            'label'   => ['Hydrolysis1' => ['Hydrolysistxt', 'hydro'], 'HeatAging1' => ['HeatAgingTxt1', 'heat']],
            'rubber'  => ['Hydrolysis1' => ['Hydrolysistxt', 'hydro'], 'Hydrolysis' => ['Hydrolysistxt', 'hydro']],
        ];
        // machine ID of a test (Access "MC..." fields): first reading column of the row => column
        $lfMcMap = [
            'fabric'   => ['TensileL1' => 'MCTensile', 'MullenBurst1' => 'MCBursting', 'HeatAging1' => 'MCOven', 'Hydrolysis1' => 'MCHydrolysis'],
            'leather'  => ['TensileL1' => 'MCTensile', 'MullenBurst1' => 'MCBursting', 'HeatAging1' => 'MCOven', 'Hydrolysis1' => 'MCHydrolysis'],
            'hardware' => ['HeatAging1' => 'MCOven', 'Hydrolysis' => 'MCHydro'],
            'rubber'   => ['Tensile1' => 'MCTensile', 'HeatAging1' => 'MCOven', 'Hydrolysis1' => 'MCHydro', 'Hydrolysis' => 'MCHydro'],
            'insole'   => ['Tensile1' => 'MCTensile', 'HeatAging1' => 'MCOven', 'Hydrolysis1' => 'MCHydrolysis'],
            'label'    => ['Tensile1' => 'MCTensile', 'HeatAging1' => 'MCOven', 'Hydrolysis1' => 'MCHydrolysis'],
            'sklfm'    => ['Tensile1' => 'MCTensile', 'HeatAging1' => 'MCOven', 'Hydrolysis1' => 'MCHydrolysis'],
            'wst'      => ['Tensile1' => 'MCTensile', 'HeatAging1' => 'MCOven', 'Hydrolysis1' => 'MCHydrolysis'],
            'box'      => ['Tensile1' => 'MCTensile', 'MullenBurst_In1' => 'MCBursting'],
        ];
        foreach ($lfOpts as $lfG => $lfList): ?>
            <datalist id="lf-opt-<?= $lfG ?>"><?php foreach ($lfList as $lfV): ?><option value="<?= $lfAttr($lfV) ?>"><?php endforeach ?></datalist>
        <?php endforeach ?>
        <?php foreach ($lfSheets as $lfSlug => $lfSheet):
            [$lfTitleEn, $lfTitleCn, $lfFormNo, $lfRows] = $lfSheet;
            $lfOpt  = $lfSheet[4] ?? [];
            $lfH    = static fn (string $col): string => $lfOpt['hdr'][$col] ?? $col;   // header column names differ in some tables
            $lfFibs = $lfOpt['fibers'] ?? array_map(static fn ($cn) => $cn, $lfFib);
            foreach ($lfFibs as $fk => $fv) { $lfFibs[$fk] = is_array($fv) ? $fv : [$fk, $fv]; }
        ?>
        <div id="dtr-sheet-<?= $lfSlug ?>" class="vf-sheet lf-sheet" hidden>
            <div class="vf-tpl-head">
                <img class="vf-tpl-logo" src="<?= base_url('assets/images/vf-logo.webp') ?>" alt="VF">
                <div class="vf-tpl-title">
                    <div class="vf-tpl-title-en"><?= $lfTitleEn ?></div>
                    <div class="vf-tpl-title-cn"><?= $lfTitleCn ?></div>
                </div>
                <img class="vf-tpl-satra" src="<?= base_url('assets/images/satra-accredited.png') ?>" alt="SATRA Accredited Laboratory">
            </div>

            <div class="vf-tpl-ids">
                <div class="vf-tpl-box vf-tpl-formno">Form # : <?= $lfFormNo ?></div>
                <div class="vf-tpl-box"><label>ETRF.No:</label></div>
                <div class="vf-tpl-box"><label>Lab Ref.No:</label><input type="text" class="mono bold lf-in" data-col="LabRefNo"></div>
            </div>

            <div class="vf-tpl-body">
                <div class="vf-tpl-col">
                    <div class="vf-tpl-line"><label>Supplier/Factory (厂商):</label><input type="text" class="lf-in" data-col="Supplier/Factory"></div>
                    <div class="vf-tpl-line"><label>Supplier's country (国家):</label><input type="text" class="lf-in" data-col="Supplier's Country"></div>
                    <div class="vf-tpl-line"><label>Material Description (材料):</label><input type="text" class="lf-in" data-col="Material Description"></div>
                    <div class="vf-tpl-line"><label>Color (颜色):</label><input type="text" class="lf-in" data-col="Color"></div>
                    <div class="vf-tpl-line"><label>Model No./Style (型体):</label><input type="text" class="lf-in" data-col="Model No/Style"></div>
                    <div class="vf-tpl-line"><label>P.O/Lot No (订单号):</label><input type="text" class="mono lf-in" data-col="<?= $lfH('PO/Lot No') ?>"></div>
                    <div class="vf-tpl-line"><label>Quantity (数量):</label><input type="text" class="lf-in" data-col="Quantity"></div>
                    <div class="vf-tpl-line"><label>Send by (提供方):</label><input type="text" class="lf-in" data-col="<?= $lfH('Send by') ?>"></div>
                </div>
                <div class="vf-tpl-col">
                    <div class="vf-tpl-radios" role="radiogroup" aria-label="Test Type">
                        <?php foreach (['Prod. Material' => 'Prod. Material 量产材料', 'Prod. Re-test' => 'Prod. Re-test 量产重测', 'New/Development' => 'New/Development 开发段新材料', 'Sales Sample' => 'Sales Sample 销售样品'] as $v => $t): ?>
                            <label><input type="radio" name="lf_ms_radio_<?= $lfSlug ?>" class="lf-in" data-col="MaterialSource" value="<?= $lfAttr($v) ?>"> <?= $lfAttr($t) ?></label>
                        <?php endforeach ?>
                    </div>
                    <div class="vf-tpl-line"><label>Received Date 收到日期:</label><input type="text" class="lf-in" data-col="<?= $lfH('Received Date') ?>"></div>
                    <div class="vf-tpl-line"><label>Tested Date 测试时间:</label><input type="text" class="lf-in" data-col="<?= $lfH('Tested Date') ?>"></div>
                    <div class="vf-tpl-line"><label>Finished Date 完成时间:</label><input type="text" class="lf-in" data-col="<?= $lfH('Finished Date') ?>"></div>
                    <div class="vf-tpl-line"><label>Technician 技术人员:</label><input type="text" class="lf-in" data-col="Technician" list="dtr-tech-list" autocomplete="off"></div>
                    <div class="vf-tpl-line pf-result"><label>* Overall Result:</label><input type="text" readonly tabindex="-1" class="bold mono dtr-rate-inp lf-out" data-col="Remarks"></div>
                </div>
            </div>

            <div class="pf-wrap">
            <table class="vf-res lf-res">
                <thead>
                    <tr>
                        <th class="lf-c-item">Test Items<br><span class="cn">测试项目</span></th>
                        <th class="lf-c-m">VF Test<br>Method<br><span class="cn">测试方法</span></th>
                        <th class="lf-c-u">Units<br><span class="cn">单位</span></th>
                        <th class="lf-c-r">Test Results<br><span class="cn">测试结果</span></th>
                        <th class="lf-c-n">Max/Min<br><span class="cn">最大/小值</span></th>
                        <th class="lf-c-n">Average<br><span class="cn">平均值</span></th>
                        <th class="lf-c-n">Standard<br><span class="cn">标准</span></th>
                        <th class="lf-c-n">Tolerance<br><span class="cn">容许度</span></th>
                        <th class="lf-c-n">Rating<br><span class="cn">等级</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lfRows as [$item, $cn, $method, $unit, $hl, $lines]): ?>
                    <?php foreach ($lines as $li => $ln): ?>
                        <tr>
                            <?php if ($li === 0): $rs = count($lines); ?>
                                <td rowspan="<?= $rs ?>" class="vf-res-part-cell lf-item<?= $hl ? ' lf-hl' : '' ?>"><?= $item ?><?= $cn !== '' ? '<br><span class="cn">' . $cn . '</span>' : '' ?>
                                    <?php $lfMcDone = false; foreach ($lines as $lfL2) { foreach ($lfL2['in'] ?? [] as $lfC2) { if (! $lfMcDone && isset($lfMcMap[$lfSlug][$lfC2])) { $lfMcDone = true; ?>
                                        <input type="text" autocomplete="off" class="vf-res-inp lf-in lf-mc" data-col="<?= $lfAttr($lfMcMap[$lfSlug][$lfC2]) ?>" placeholder="Machine ID" title="Machine ID used for this test" aria-label="Machine ID of <?= $lfAttr(strip_tags($item)) ?>">
                                    <?php } } } ?></td>
                                <td rowspan="<?= $rs ?>" class="vf-res-c"><?= $method ?></td>
                                <td rowspan="<?= $rs ?>" class="vf-res-c"><?= $unit ?></td>
                            <?php endif ?>
                            <td class="lf-cell">
                                <?php if (! empty($ln['fib'])): ?>
                                    <div class="lf-fibers">
                                        <?php foreach ($lfFibs as $fn => [$fen, $fcn]): ?>
                                            <label class="lf-fib"><span><?= $fen ?><br><?= $fcn ?></span><?= $lfIn($ln['fib'] . $fn) ?></label>
                                        <?php endforeach ?>
                                    </div>
                                <?php else: ?>
                                    <?php if (! empty($ln['lab'])): ?><span class="lf-lab"><?= $ln['lab'] ?></span><?php endif ?>
                                    <?php foreach ($ln['in'] ?? [] as $col): ?><?= $lfIn($col) ?><?php endforeach ?>
                                <?php endif ?>
                                <?php if (! empty($ln['txt'])): [$tcol0, $tgrp0] = $ln['txt']; ?>
                                    <input type="text" autocomplete="off" class="vf-res-inp lf-in lf-txt" data-col="<?= $lfAttr($tcol0) ?>" list="lf-opt-<?= $tgrp0 ?>" placeholder="Observation / result of the test" aria-label="<?= $lfAttr(strip_tags($item) . ' ' . $tcol0) ?>">
                                <?php endif ?>
                                <?php foreach ($ln['in'] ?? [] as $col): if (isset($lfTxtMap[$lfSlug][$col])): [$tcol, $tgrp] = $lfTxtMap[$lfSlug][$col]; ?>
                                    <input type="text" autocomplete="off" class="vf-res-inp lf-in lf-txt" data-col="<?= $lfAttr($tcol) ?>" list="lf-opt-<?= $tgrp ?>" placeholder="Observation / result of the test" aria-label="<?= $lfAttr($item . ' ' . $tcol) ?>">
                                <?php endif; endforeach ?>
                            </td>
                            <td class="lf-mm"><?php foreach ($ln['mm'] ?? [] as $col): ?><?= $lfOut($col) ?><?php endforeach ?></td>
                            <td><?= isset($ln['ave']) ? str_replace('<input ', '<input data-from="' . $lfAttr(implode('|', $ln['in'] ?? [])) . '" ', $lfOut($ln['ave'])) : '' ?></td>
                            <td><?= isset($ln['std']) ? $lfStd($ln['std']) : '' ?></td>
                            <td></td>
                            <td><?= isset($ln['rate']) ? $lfRate($ln['rate']) : '' ?></td>
                        </tr>
                    <?php endforeach ?>
                <?php endforeach ?>
                </tbody>
            </table>
            </div>

            <div class="pf-foot">
                <div class="pf-comments"><label>Comments 备注 :</label><textarea rows="2" class="lf-in" data-col="Comments"></textarea></div>
                <div class="lf-lab-notes">
                    <label>ERP <input type="text" class="lf-in" data-col="ERP" list="lf-opt-erp" autocomplete="off"></label>
                    <label>SARGE <input type="text" class="lf-in" data-col="SARGE" list="lf-opt-sarge" autocomplete="off"></label>
                    <label>Tracker <input type="text" class="lf-in" data-col="Tracker" list="lf-opt-tracker" autocomplete="off"></label>
                    <label class="lf-ln-wide">Lab comments <input type="text" class="lf-in" data-col="LabComments" autocomplete="off"></label>
                </div>
                <div class="pf-sign">
                    <div class="vf-tpl-line"><label>Reviewed by 检查:</label><input type="text" class="lf-in" data-col="AuthorizedBy"></div>
                    <div class="vf-tpl-line"><label>Approved by 核准:</label><input type="text" class="lf-in" data-col="Acknowledged by"></div>
                </div>
            </div>
        </div>
        <?php endforeach ?>

        <form id="dtr-material-form" onsubmit="return false;">
            <input type="hidden" name="ID" id="mf_ID" value="">

            <!-- SECTION A: HEADER, SAMPLE METADATA & AUTO-LOOKUPS -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>A. Sample Identification &amp; Automatic Master Lookups<?= anTip('Lab Ref No = the unique number of this lab test. When you type a material or supplier code, the matching details are filled in automatically from the master list.') ?></h4>
                    <div class="dtr-subcard-btns">
                        <button type="button" id="btn-dtr-lookup-all" class="dtr-btn-xs dtr-btn-primary"><svg class="ic" aria-hidden="true"><use href="#i-search"/></svg> Auto-Lookup Material / PO / DSR</button>
                        <button type="button" id="btn-dtr-review" class="dtr-btn-xs dtr-btn-outline"><svg class="ic" aria-hidden="true"><use href="#i-check"/></svg> Stamp Reviewed</button>
                    </div>
                </div>
                <div class="dtr-grid-6">
                    <div class="dtr-field">
                        <label>* Lab Reference No.</label>
                        <input type="text" name="LabRefNo" id="mf_LabRefNo" class="mono bold" placeholder="e.g. DTP20261009FBU-1">
                    </div>
                    <div class="dtr-field">
                        <label>Test Report No.</label>
                        <input type="text" name="TR" id="mf_TR" class="mono bold" placeholder="e.g. TR20261009001">
                    </div>
                    <div class="dtr-field">
                        <label>* Material Type</label>
                        <input type="text" name="MaterialType" id="mf_MaterialType" list="dtr-std-mattypes" placeholder="e.g. Canvas, Suede, Rubber">
                        <datalist id="dtr-std-mattypes">
                            <?php foreach ($standardMatTypes as $smt): ?>
                                <option value="<?= esc($smt) ?>"></option>
                            <?php endforeach ?>
                        </datalist>
                    </div>
                    <div class="dtr-field">
                        <label>Material Code</label>
                        <input type="text" name="MaterialCode" id="mf_MaterialCode" class="mono" placeholder="Type the code, then Tab">
                    </div>
                    <div class="dtr-field" style="grid-column:span 2">
                        <label>Material Description</label>
                        <input type="text" name="Material Description" id="mf_MaterialDescription" placeholder="Auto-filled from the material master">
                    </div>

                    <div class="dtr-field">
                        <label>Supplier / Factory</label>
                        <input type="text" name="Supplier/Factory" id="mf_SupplierFactory" placeholder="Auto-filled from the material master">
                    </div>
                    <div class="dtr-field">
                        <label>PO / Lot No.</label>
                        <input type="text" name="PO/Lot No" id="mf_POLotNo" class="mono" placeholder="Type the production code, then Tab">
                    </div>
                    <div class="dtr-field">
                        <label>Model No. / Style</label>
                        <input type="text" name="Model No/Style" id="mf_ModelNoStyle" placeholder="Auto-filled from the style master">
                    </div>
                    <div class="dtr-field">
                        <label>Color / Colorway</label>
                        <input type="text" name="Color" id="mf_Color" placeholder="Auto-filled from the style master">
                    </div>
                    <div class="dtr-field">
                        <label>PO Quantity</label>
                        <input type="text" name="PO Quantity" id="mf_POQuantity" placeholder="Quantity">
                    </div>
                    <div class="dtr-field">
                        <label>MTL #</label>
                        <input type="text" name="MTL" id="mf_MTL" class="mono" placeholder="e.g. MTL-12345">
                    </div>

                    <!-- DSR Auto-Filled Row -->
                    <div class="dtr-field">
                        <label>DSR Style Number</label>
                        <input type="text" name="StyleNumber_DSR" id="mf_StyleNumber_DSR" placeholder="From DSR">
                    </div>
                    <div class="dtr-field">
                        <label>DSR Style Description</label>
                        <input type="text" name="StyleDescription_DSR" id="mf_StyleDescription_DSR" placeholder="From DSR">
                    </div>
                    <div class="dtr-field">
                        <label>DSR Component / Location</label>
                        <input type="text" name="ComponentorLocation_DSR" id="mf_ComponentorLocation_DSR" placeholder="e.g. Vamp / Quarter">
                    </div>
                    <div class="dtr-field">
                        <label>DSR VF Material Code</label>
                        <input type="text" name="VFMaterialCode_DSR" id="mf_VFMaterialCode_DSR" class="mono">
                    </div>
                    <div class="dtr-field">
                        <label>DSR Internal Material Name</label>
                        <input type="text" name="InternalName_DSR" id="mf_InternalName_DSR">
                    </div>
                    <div class="dtr-field">
                        <label>DSR Color Name</label>
                        <input type="text" name="ColorName_DSR" id="mf_ColorName_DSR">
                    </div>

                    <!-- Dates, Technician, Status & Overall Result -->
                    <div class="dtr-field">
                        <label>Received Date</label>
                        <input type="text" name="ReceivedDate" id="mf_ReceivedDate" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="dtr-field">
                        <label>Tested Date</label>
                        <input type="text" name="TestedDate" id="mf_TestedDate" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="dtr-field">
                        <label>Finished Date</label>
                        <input type="text" name="FinishedDate" id="mf_FinishedDate" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="dtr-field">
                        <label>Tested By / Technician</label>
                        <input type="text" name="Tested by" id="mf_TestedBy" list="dtr-tech-list" autocomplete="off" placeholder="Technician">
                    </div>
                    <div class="dtr-field">
                        <label>Status (Complete sets the rating)</label>
                        <select name="Status" id="mf_Status">
                            <option value="Complete">Complete</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Hold">Hold</option>
                        </select>
                    </div>
                    <div class="dtr-field">
                        <label>* Overall Rating</label>
                        <input type="text" name="Remarks" id="mf_Remarks" class="bold mono" readonly style="background:#f8fafc">
                    </div>
                </div>
            </div>

            <!-- SECTION B: THICKNESS / HARDNESS (6-POINT AUTO MIN/MAX/AVE + SMART STANDARDS) -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>B. Thickness (mm) &amp; Durometer Hardness (Shore A) — 6-Point Auto Min / Max / Ave<?= anTip('Durometer measures how hard or soft a material is. Enter the 6 readings; the lowest, highest and average are calculated for you.') ?></h4>
                    <span class="muted sm">Changing Thickness 1..6 automatically computes Min, Max, Average and updates thickness-dependent Standards!</span>
                </div>
                <div class="dtr-grid-6">
                    <div class="dtr-field"><label>Thickness 1</label><input type="number" step="any" name="Thickness1" id="mf_Thickness1" class="dtr-calc-thick"></div>
                    <div class="dtr-field"><label>Thickness 2</label><input type="number" step="any" name="Thickness2" id="mf_Thickness2" class="dtr-calc-thick"></div>
                    <div class="dtr-field"><label>Thickness 3</label><input type="number" step="any" name="Thickness3" id="mf_Thickness3" class="dtr-calc-thick"></div>
                    <div class="dtr-field"><label>Thickness 4</label><input type="number" step="any" name="Thickness4" id="mf_Thickness4" class="dtr-calc-thick"></div>
                    <div class="dtr-field"><label>Thickness 5</label><input type="number" step="any" name="Thickness5" id="mf_Thickness5" class="dtr-calc-thick"></div>
                    <div class="dtr-field"><label>Thickness 6</label><input type="number" step="any" name="Thickness6" id="mf_Thickness6" class="dtr-calc-thick"></div>

                    <div class="dtr-field"><label>Thickness Min</label><input type="text" name="TMin" id="mf_TMin" readonly class="dtr-ro"></div>
                    <div class="dtr-field"><label>Thickness Max</label><input type="text" name="TMax" id="mf_TMax" readonly class="dtr-ro"></div>
                    <div class="dtr-field"><label><b>Thickness Ave</b></label><input type="text" name="TAve" id="mf_TAve" readonly class="dtr-ro bold"></div>
                    <div class="dtr-field"><label>Thickness Std</label><input type="text" name="T_S" id="mf_T_S" class="dtr-std-inp"></div>
                    <div class="dtr-field"><label>Thickness Rating</label><input type="text" name="TRating" id="mf_TRating" class="dtr-rate-inp"></div>
                    <div class="dtr-field"><label>Hardness Ave / Rate (Rubber/FTHL)</label>
                        <div style="display:flex;gap:4px">
                            <input type="text" name="Hardness_Ave" id="mf_Hardness_Ave" placeholder="Hard Ave">
                            <input type="text" name="Hardness_Rate" id="mf_Hardness_Rate" placeholder="Rating" class="dtr-rate-inp">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION C: WEIGHT, SPECIFIC GRAVITY, COMPRESSION, DIN ABRASION, ROSS FLEX & SHRINKAGE -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>C. Weight, Specific Gravity, Compression Set, DIN Abrasion, Ross Flex &amp; Shrinkage<?= anTip('Specific gravity = density. Compression set = how well a foam springs back after being squeezed. DIN abrasion = wear resistance. Ross flex = how well rubber resists cracking when bent. Shrinkage = how much it shrinks.') ?></h4>
                    <div class="dtr-subcard-btns">
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="weight"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> Weight Calc</button>
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="sg"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> SG Calc</button>
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="comp"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> Compression Calc</button>
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="din"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> DIN Calc</button>
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="ross"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> Ross Flex Calc</button>
                        <button type="button" class="dtr-btn-xs dtr-btn-outline" data-goto-calc="shrink"><svg class="ic" aria-hidden="true"><use href="#i-calc"/></svg> Shrinkage Calc</button>
                    </div>
                </div>
                <div class="dtr-grid-6">
                    <!-- Weight -->
                    <div class="dtr-field"><label>Weight 1</label><input type="number" step="any" name="Weight1" id="mf_Weight1" class="dtr-calc-weight"></div>
                    <div class="dtr-field"><label>Weight 2</label><input type="number" step="any" name="Weight2" id="mf_Weight2" class="dtr-calc-weight"></div>
                    <div class="dtr-field"><label>Weight 3</label><input type="number" step="any" name="Weight3" id="mf_Weight3" class="dtr-calc-weight"></div>
                    <div class="dtr-field"><label><b>Weight Ave</b></label><input type="text" name="WAve" id="mf_WAve" readonly class="dtr-ro bold"></div>
                    <div class="dtr-field"><label>Weight Std</label><input type="text" name="Weight_S" id="mf_Weight_S" class="dtr-std-inp"></div>
                    <div class="dtr-field"><label>Weight Rating</label><input type="text" name="WRating" id="mf_WRating" class="dtr-rate-inp"></div>

                    <!-- Specific Gravity -->
                    <div class="dtr-field"><label>Spec. Gravity 1</label><input type="number" step="any" name="SG1" id="mf_SG1" class="dtr-calc-sg"></div>
                    <div class="dtr-field"><label>Spec. Gravity 2</label><input type="number" step="any" name="SG2" id="mf_SG2" class="dtr-calc-sg"></div>
                    <div class="dtr-field"><label>Spec. Gravity 3</label><input type="number" step="any" name="SG3" id="mf_SG3" class="dtr-calc-sg"></div>
                    <div class="dtr-field"><label><b>SG Ave</b></label><input type="text" name="SG_Ave" id="mf_SG_Ave" readonly class="dtr-ro bold"></div>
                    <div class="dtr-field"><label>SG Std (±0.02 Tol)</label><input type="text" name="SG_Std" id="mf_SG_Std" class="dtr-std-inp"></div>
                    <div class="dtr-field"><label>SG Rating</label><input type="text" name="SG_Rate" id="mf_SG_Rate" class="dtr-rate-inp"></div>

                    <!-- DIN Abrasion, Ross Flex, Shrinkage -->
                    <div class="dtr-field"><label>DIN Abrasion 1..3 (≤150)</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="any" name="DIN_1" id="mf_DIN_1" class="dtr-calc-din" placeholder="1">
                            <input type="number" step="any" name="DIN_2" id="mf_DIN_2" class="dtr-calc-din" placeholder="2">
                            <input type="number" step="any" name="DIN_3" id="mf_DIN_3" class="dtr-calc-din" placeholder="3">
                        </div>
                    </div>
                    <div class="dtr-field"><label>DIN Rating</label><input type="text" name="DIN_Rate" id="mf_DIN_Rate" class="dtr-rate-inp"></div>
                    <div class="dtr-field"><label>Ross Flex 1..2 (≤50%)</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="any" name="RossFlex_1" id="mf_RossFlex_1" class="dtr-calc-ross" placeholder="1">
                            <input type="number" step="any" name="RossFlex_2" id="mf_RossFlex_2" class="dtr-calc-ross" placeholder="2">
                        </div>
                    </div>
                    <div class="dtr-field"><label>Ross Flex Rate</label><input type="text" name="RossFlex_Rate" id="mf_RossFlex_Rate" class="dtr-rate-inp"></div>
                    <div class="dtr-field"><label>Shrinkage L / W (%)</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="any" name="ShrinkageL" id="mf_ShrinkageL" class="dtr-calc-shrink" placeholder="L%">
                            <input type="number" step="any" name="ShrinkageW" id="mf_ShrinkageW" class="dtr-calc-shrink" placeholder="W%">
                        </div>
                    </div>
                    <div class="dtr-field"><label>Shrinkage Std &amp; Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="text" name="Shrinkage_S" id="mf_Shrinkage_S" placeholder="Std" class="dtr-std-inp">
                            <input type="text" name="ShrinkageLRating" id="mf_ShrinkageLRating" placeholder="L Rate" class="dtr-rate-inp">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION D: PHYSICAL & MECHANICAL DIRECTIONAL TESTS (AUTO L/W AVERAGES & RATINGS) -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>D. Physical &amp; Mechanical Tests — Directional Specimen Readings, Auto-Averages &amp; Pass/Fail</h4>
                    <span class="muted sm">Formula: <code>Ave = Round((Spec1 + Spec2 + Spec3) / 3, 2)</code> · Rating = <code>PASS</code> if <code>Ave >= Standard</code> else <code>FAIL</code></span>
                </div>
                <div class="dtr-table-wrap">
                    <table class="dtr-table dtr-mech-table">
                        <thead>
                            <tr>
                                <th style="width:210px">Test Property &amp; Direction</th>
                                <th>Spec 1</th>
                                <th>Spec 2</th>
                                <th>Spec 3</th>
                                <th>Computed Average</th>
                                <th>VF Standard Spec</th>
                                <th>Auto Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Tensile L & W -->
                            <tr>
                                <td><b>Tensile Strength — Length (L)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="TensileL1" id="mf_TensileL1" class="dtr-dir-inp" data-dir-group="TensileL"></td>
                                <td><input type="number" step="any" name="TensileL2" id="mf_TensileL2" class="dtr-dir-inp" data-dir-group="TensileL"></td>
                                <td><input type="number" step="any" name="TensileL3" id="mf_TensileL3" class="dtr-dir-inp" data-dir-group="TensileL"></td>
                                <td><input type="text" name="TensileLAve" id="mf_TensileLAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="Tensile_L" id="mf_Tensile_L" class="dtr-std-inp" data-dir-group="TensileL"></td>
                                <td><input type="text" name="TensileLRating" id="mf_TensileLRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>Tensile Strength — Width (W)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="TensileW1" id="mf_TensileW1" class="dtr-dir-inp" data-dir-group="TensileW"></td>
                                <td><input type="number" step="any" name="TensileW2" id="mf_TensileW2" class="dtr-dir-inp" data-dir-group="TensileW"></td>
                                <td><input type="number" step="any" name="TensileW3" id="mf_TensileW3" class="dtr-dir-inp" data-dir-group="TensileW"></td>
                                <td><input type="text" name="TensileWAve" id="mf_TensileWAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="Tensile_W" id="mf_Tensile_W" class="dtr-std-inp" data-dir-group="TensileW"></td>
                                <td><input type="text" name="TensileWRating" id="mf_TensileWRating" class="dtr-rate-inp"></td>
                            </tr>
                            <!-- Elongation L & W -->
                            <tr>
                                <td><b>Elongation — Length (L)</b> <small class="muted">(%)</small></td>
                                <td><input type="number" step="any" name="ElongationL1" id="mf_ElongationL1" class="dtr-dir-inp" data-dir-group="ElongationL"></td>
                                <td><input type="number" step="any" name="ElongationL2" id="mf_ElongationL2" class="dtr-dir-inp" data-dir-group="ElongationL"></td>
                                <td><input type="number" step="any" name="ElongationL3" id="mf_ElongationL3" class="dtr-dir-inp" data-dir-group="ElongationL"></td>
                                <td><input type="text" name="ElongationLAve" id="mf_ElongationLAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="Elongation_L" id="mf_Elongation_L" class="dtr-std-inp" data-dir-group="ElongationL"></td>
                                <td><input type="text" name="ElongationLRating" id="mf_ElongationLRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>Elongation — Width (W)</b> <small class="muted">(%)</small></td>
                                <td><input type="number" step="any" name="ElongationW1" id="mf_ElongationW1" class="dtr-dir-inp" data-dir-group="ElongationW"></td>
                                <td><input type="number" step="any" name="ElongationW2" id="mf_ElongationW2" class="dtr-dir-inp" data-dir-group="ElongationW"></td>
                                <td><input type="number" step="any" name="ElongationW3" id="mf_ElongationW3" class="dtr-dir-inp" data-dir-group="ElongationW"></td>
                                <td><input type="text" name="ElongationWAve" id="mf_ElongationWAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="Elongation_W" id="mf_Elongation_W" class="dtr-std-inp" data-dir-group="ElongationW"></td>
                                <td><input type="text" name="ElongationWRating" id="mf_ElongationWRating" class="dtr-rate-inp"></td>
                            </tr>
                            <!-- Trouser Tear L & W -->
                            <tr>
                                <td><b>Trouser / Wing Tear — Length (L)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="TrouserTearL1" id="mf_TrouserTearL1" class="dtr-dir-inp" data-dir-group="TrouserTearL"></td>
                                <td><input type="number" step="any" name="TrouserTearL2" id="mf_TrouserTearL2" class="dtr-dir-inp" data-dir-group="TrouserTearL"></td>
                                <td><input type="number" step="any" name="TrouserTearL3" id="mf_TrouserTearL3" class="dtr-dir-inp" data-dir-group="TrouserTearL"></td>
                                <td><input type="text" name="TrouserTearLAve" id="mf_TrouserTearLAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="TrouserTear_L" id="mf_TrouserTear_L" class="dtr-std-inp" data-dir-group="TrouserTearL"></td>
                                <td><input type="text" name="TrouserTearLRating" id="mf_TrouserTearLRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>Trouser / Wing Tear — Width (W)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="TrouserTearW1" id="mf_TrouserTearW1" class="dtr-dir-inp" data-dir-group="TrouserTearW"></td>
                                <td><input type="number" step="any" name="TrouserTearW2" id="mf_TrouserTearW2" class="dtr-dir-inp" data-dir-group="TrouserTearW"></td>
                                <td><input type="number" step="any" name="TrouserTearW3" id="mf_TrouserTearW3" class="dtr-dir-inp" data-dir-group="TrouserTearW"></td>
                                <td><input type="text" name="TrouserTearWAve" id="mf_TrouserTearWAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="TrouserTear_W" id="mf_TrouserTear_W" class="dtr-std-inp" data-dir-group="TrouserTearW"></td>
                                <td><input type="text" name="TrouserTearWRating" id="mf_TrouserTearWRating" class="dtr-rate-inp"></td>
                            </tr>
                            <!-- Stitch Tear L & W -->
                            <tr>
                                <td><b>Stitch Tear — Length (L)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="StitchTearL1" id="mf_StitchTearL1" class="dtr-dir-inp" data-dir-group="StitchTearL"></td>
                                <td><input type="number" step="any" name="StitchTearL2" id="mf_StitchTearL2" class="dtr-dir-inp" data-dir-group="StitchTearL"></td>
                                <td><input type="number" step="any" name="StitchTearL3" id="mf_StitchTearL3" class="dtr-dir-inp" data-dir-group="StitchTearL"></td>
                                <td><input type="text" name="StitchTearLAve" id="mf_StitchTearLAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="StitchTear_L" id="mf_StitchTear_L" class="dtr-std-inp" data-dir-group="StitchTearL"></td>
                                <td><input type="text" name="StitchTearLRating" id="mf_StitchTearLRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>Stitch Tear — Width (W)</b> <small class="muted">(kg)</small></td>
                                <td><input type="number" step="any" name="StitchTearW1" id="mf_StitchTearW1" class="dtr-dir-inp" data-dir-group="StitchTearW"></td>
                                <td><input type="number" step="any" name="StitchTearW2" id="mf_StitchTearW2" class="dtr-dir-inp" data-dir-group="StitchTearW"></td>
                                <td><input type="number" step="any" name="StitchTearW3" id="mf_StitchTearW3" class="dtr-dir-inp" data-dir-group="StitchTearW"></td>
                                <td><input type="text" name="StitchTearWAve" id="mf_StitchTearWAve" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="StitchTear_W" id="mf_StitchTear_W" class="dtr-std-inp" data-dir-group="StitchTearW"></td>
                                <td><input type="text" name="StitchTearWRating" id="mf_StitchTearWRating" class="dtr-rate-inp"></td>
                            </tr>
                            <!-- Mullen Burst & Edge Crush -->
                            <tr>
                                <td><b>Mullen Bursting Strength</b> <small class="muted">(kg/cm²)</small></td>
                                <td><input type="number" step="any" name="MullenBurst1" id="mf_MullenBurst1" class="dtr-dir-inp" data-dir-group="MullenBurst"></td>
                                <td><input type="number" step="any" name="MullenBurst2" id="mf_MullenBurst2" class="dtr-dir-inp" data-dir-group="MullenBurst"></td>
                                <td><input type="number" step="any" name="MullenBurst3" id="mf_MullenBurst3" class="dtr-dir-inp" data-dir-group="MullenBurst"></td>
                                <td><input type="text" name="Text3977" id="mf_Text3977" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="MullenBurst" id="mf_MullenBurst" class="dtr-std-inp" data-dir-group="MullenBurst"></td>
                                <td><input type="text" name="MullenBurstRating" id="mf_MullenBurstRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>Die C Tear / Edge Crush (Box/Rubber)</b></td>
                                <td><input type="number" step="any" name="DieC_1" id="mf_DieC_1" class="dtr-dir-inp" data-dir-group="DieC"></td>
                                <td><input type="number" step="any" name="DieC_2" id="mf_DieC_2" class="dtr-dir-inp" data-dir-group="DieC"></td>
                                <td><input type="number" step="any" name="DieC_3" id="mf_DieC_3" class="dtr-dir-inp" data-dir-group="DieC"></td>
                                <td><input type="text" name="DieC_Ave" id="mf_DieC_Ave" readonly class="dtr-ro bold"></td>
                                <td><input type="text" name="DieC_Std" id="mf_DieC_Std" class="dtr-std-inp" data-dir-group="DieC"></td>
                                <td><input type="text" name="DieC_Rate" id="mf_DieC_Rate" class="dtr-rate-inp"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION E: COLORFASTNESS (6-POINT MULTI-FIBER STRIP + CROCKING + WEATHERING) -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>E. Colorfastness (6-Fiber Multi-Fiber Evaluation, Crocking, UV, Heat Aging &amp; Hydrolysis)<?= anTip('Colorfastness = whether colour stays put. Crocking = colour rubbing off. UV = sunlight exposure. Heat aging and hydrolysis = how the material holds up after heat or humidity.') ?></h4>
                    <span class="muted sm">6-Fiber Rule: Any fiber grade &lt; Standard = <code>FAIL</code>; all 6 fibers &ge; Standard = <code>PASS</code>.</span>
                </div>
                <div class="dtr-table-wrap">
                    <table class="dtr-table dtr-mech-table">
                        <thead>
                            <tr>
                                <th style="width:210px">6-Fiber Colorfastness Test</th>
                                <th>Wool</th>
                                <th>Acrylic</th>
                                <th>Polyester</th>
                                <th>Nylon</th>
                                <th>Cotton</th>
                                <th>Acetate</th>
                                <th>Standard</th>
                                <th>Auto Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><b>CF to Perspiration (Top)</b></td>
                                <td><input type="number" step="0.5" name="CtPWool" id="mf_CtPWool" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CtPAcrylic" id="mf_CtPAcrylic" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CtPPolyester" id="mf_CtPPolyester" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CtPNylon" id="mf_CtPNylon" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CtPCotton" id="mf_CtPCotton" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CtPAcetate" id="mf_CtPAcetate" class="dtr-6pt-inp" data-6pt="CTP"></td>
                                <td><input type="number" step="0.5" name="CTP" id="mf_CTP" class="dtr-std-inp" data-6pt="CTP"></td>
                                <td><input type="text" name="CTPRating" id="mf_CTPRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>CF to Perspiration (Back)</b></td>
                                <td><input type="number" step="0.5" name="CtPBWool" id="mf_CtPBWool" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPBAcrylic" id="mf_CtPBAcrylic" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPBPolyester" id="mf_CtPBPolyester" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPBNylon" id="mf_CtPBNylon" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPBCotton" id="mf_CtPBCotton" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPBAcetate" id="mf_CtPBAcetate" class="dtr-6pt-inp" data-6pt="CTPB"></td>
                                <td><input type="number" step="0.5" name="CtPB_S" id="mf_CtPB_S" class="dtr-std-inp" data-6pt="CTPB"></td>
                                <td><input type="text" name="CTPB_Rating" id="mf_CTPB_Rating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>CF to Water (Top)</b></td>
                                <td><input type="number" step="0.5" name="CtWWool" id="mf_CtWWool" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CtWAcrylic" id="mf_CtWAcrylic" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CtWPolyester" id="mf_CtWPolyester" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CtWNylon" id="mf_CtWNylon" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CtWCotton" id="mf_CtWCotton" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CtWAcetate" id="mf_CtWAcetate" class="dtr-6pt-inp" data-6pt="CTW"></td>
                                <td><input type="number" step="0.5" name="CTW" id="mf_CTW" class="dtr-std-inp" data-6pt="CTW"></td>
                                <td><input type="text" name="CTWRating" id="mf_CTWRating" class="dtr-rate-inp"></td>
                            </tr>
                            <tr>
                                <td><b>CF to Water (Back)</b></td>
                                <td><input type="number" step="0.5" name="CtWBackSideWool" id="mf_CtWBackSideWool" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWBackSideAcrylic" id="mf_CtWBackSideAcrylic" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWBackSidePolyester" id="mf_CtWBackSidePolyester" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWBackSideNylon" id="mf_CtWBackSideNylon" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWBackSideCotton" id="mf_CtWBackSideCotton" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWBackSideAcetate" id="mf_CtWBackSideAcetate" class="dtr-6pt-inp" data-6pt="CTWB"></td>
                                <td><input type="number" step="0.5" name="CtWB_S" id="mf_CtWB_S" class="dtr-std-inp" data-6pt="CTWB"></td>
                                <td><input type="text" name="CtWB_Rating" id="mf_CtWB_Rating" class="dtr-rate-inp"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Crocking, UV, Heat Aging, Hydrolysis -->
                <div class="dtr-grid-6" style="margin-top:10px">
                    <div class="dtr-field">
                        <label>CF Crocking Dry / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="CtCDry" id="mf_CtCDry" class="dtr-crock-inp" placeholder="Val">
                            <input type="number" step="0.5" name="CtCDry_S" id="mf_CtCDry_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="CTCDRYRating" id="mf_CTCDRYRating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                    <div class="dtr-field">
                        <label>CF Crocking Wet / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="CtCWet" id="mf_CtCWet" class="dtr-crock-inp" placeholder="Val">
                            <input type="number" step="0.5" name="CtCWet_S" id="mf_CtCWet_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="CTCWETRating" id="mf_CTCWETRating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                    <div class="dtr-field">
                        <label>UV Light / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="UV" id="mf_UV" class="dtr-crock-inp" placeholder="UV">
                            <input type="number" step="0.5" name="UV_S" id="mf_UV_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="UVRating" id="mf_UVRating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                    <div class="dtr-field">
                        <label>Heat Aging 1..2 / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="HeatAging1" id="mf_HeatAging1" class="dtr-crock-inp" placeholder="1">
                            <input type="number" step="0.5" name="HeatAging2" id="mf_HeatAging2" class="dtr-crock-inp" placeholder="2">
                            <input type="number" step="0.5" name="HeatAging_S" id="mf_HeatAging_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="HeatAgingRating" id="mf_HeatAgingRating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                    <div class="dtr-field">
                        <label>Hydrolysis 1..2 / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="Hydrolysis1" id="mf_Hydrolysis1" class="dtr-crock-inp" placeholder="1">
                            <input type="number" step="0.5" name="Hydrolysis2" id="mf_Hydrolysis2" class="dtr-crock-inp" placeholder="2">
                            <input type="number" step="0.5" name="Hydrolysis_S" id="mf_Hydrolysis_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="HydrolysisRating" id="mf_HydrolysisRating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                    <div class="dtr-field">
                        <label>PVC Migration 1..2 / Std / Rate</label>
                        <div style="display:flex;gap:3px">
                            <input type="number" step="0.5" name="PVC_1" id="mf_PVC_1" class="dtr-crock-inp" placeholder="1">
                            <input type="number" step="0.5" name="PVC_2" id="mf_PVC_2" class="dtr-crock-inp" placeholder="2">
                            <input type="number" step="0.5" name="PVC_S" id="mf_PVC_S" class="dtr-std-inp" placeholder="Std">
                            <input type="text" name="PVC_Rating" id="mf_PVC_Rating" class="dtr-rate-inp" placeholder="Rate">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION F: COMPLETE DATABASE COLUMNS INSPECTOR (ALL 68..250 COLUMNS) -->
            <div class="dtr-subcard">
                <div class="dtr-subcard-head">
                    <h4>F. Complete Field Editor (<span id="dtr-raw-col-count">250</span> Fields)<?= anTip('Advanced: every stored field of this report in one list. Most people do not need this; sections A to E cover the normal work.') ?></h4>
                    <button type="button" id="btn-toggle-raw-cols" class="dtr-btn-xs dtr-btn-outline">Show / Hide All Fields</button>
                </div>
                <div id="dtr-raw-cols-container" class="dtr-grid-6" style="margin-top:8px;max-height:360px;overflow-y:auto;padding-right:6px">
                    <!-- Dynamically populated with every column of the selected table -->
                </div>
            </div>
        </form>
    </div>
</section>
