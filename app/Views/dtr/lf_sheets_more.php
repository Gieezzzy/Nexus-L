<?php

/**
 * Printed VF test-result sheets for the material forms beyond Leather / Fabric (layouts taken from _docs/access_forms).
 * Returns slug => [title EN, title CN, form no., rows, options].
 *
 * A row is [item, CN, VF method, unit, highlight?, lines]; a line is
 *   ['lab' => label, 'in' => [readings], 'fib' => column prefix of the six fiber columns, 'mm' => [max, min], 'ave' => col, 'std' => col, 'rate' => col]
 * and a line with no column keys is a printed row left blank (the form's table has no column for it).
 * Options: 'hdr' => header column names that differ in that table, 'fibers' => suffix => [english, chinese] fiber headings.
 */

$m = static fn (string $p, int $n): array => array_map(static fn ($i) => $p . $i, range(1, $n));
$blank = static fn (string $item, string $cn, string $method, string $unit, array $lines = [[]]): array => [$item, $cn, $method, $unit, false, $lines];
$dryWet = static fn (): array => [['lab' => 'Dry 干='], ['lab' => 'Wet 湿=']];
$solvLab = [['lab' => 'D65 / CWF'], ['lab' => 'After Washing D65 / CWF']];
$cof = [['lab' => 'PVC Vinyl tiles Dry 干='], ['lab' => 'PVC Vinyl tiles Wet 湿='], ['lab' => 'Mason tiles Dry 干='], ['lab' => 'Mason tiles Wet 湿='], ['lab' => 'Wood Varnish tiles Dry 干='], ['lab' => 'Wood Varnish tiles Wet 湿=']];

$sheets = [];

// ---------- Webbing / Shoe Lace / Elastic Cord (Lab VF 014) -> tbl_dtr_wst_results ----------
$sheets['wst'] = ['Webbing/Shoe Lace/Elastic Cord Test Results', '织带/ 鞋带/松紧带测试报告', 'Lab VF 014', [
    ['Gauge', '规格', 'VF-A03<br>(2014/04)', 'mm', true, [['in' => $m('Thickness', 6), 'mm' => ['TMax', 'TMin'], 'ave' => 'TAve', 'std' => 'TStandard', 'rate' => 'TRating']]],
    ['Weight', '重量', 'VF-A02<br>(2018/11)', 'g/linear meter', true, [['in' => $m('Weight', 3), 'ave' => 'WAve', 'rate' => 'WRating']]],
    ['Tensile', '抗拉', 'VF-B01<br>(2022/11)', 'kgf', true, [['in' => $m('Tensile', 3), 'ave' => 'TensileAve', 'std' => 'Tensile_S', 'rate' => 'Tensile_Rating']]],
    ['Elongation', '延伸', 'VF-B01<br>(2022/11)', '%', true, [['in' => $m('ElongationL', 3), 'ave' => 'ElongationLAve', 'std' => 'Elongation_L', 'rate' => 'ElongationLRating']]],
    ['Tensile After Abrasion', '耐磨后抗拉强度', 'VF-B01<br>(2022/11)', '%', true, [['in' => $m('TensileAA', 3), 'ave' => 'TensileAAAve', 'std' => 'TensileAA_S', 'rate' => 'TensileAA_Rating']]],
    $blank('Load at 50% extension<br>伸长50%抗拉', '', 'VF-B01<br>(2022/11)', 'kgf'),
    $blank('Retained elongation after 50% extension &amp; hold for 60 seconds', '', 'VF-B01<br>(2022/11)', '%'),
    ['Stitch tear', '车缝撕裂', 'VF-B08<br>(2019/05)', 'kgf/cm', true, [['in' => $m('StitchTear', 3), 'ave' => 'StitchTearAve', 'std' => 'StitchTear_S', 'rate' => 'StitchTear_Rating']]],
    ['Shoelace tip Retention', '鞋带头拉脱', 'VF-B24<br>(2019/05)', 'kgf', true, [['in' => $m('LaceTipRet', 6), 'ave' => 'LaceTipRetAve', 'std' => 'LaceTipRet_S', 'rate' => 'LaceTipRet_Rating']]],
    ['Knot Slip Resistance', '鞋带打结', 'VF-B23<br>(2019/05)', 'kgf', true, [['in' => $m('KnotSlip', 6), 'ave' => 'KnotSlipAve', 'std' => 'KnotSlip_S', 'rate' => 'KnotSlip_Rating']]],
    ['Lace Abrasion', '鞋带耐磨', 'VF-C09<br>(2022/09)', 'cycles', true, [
        ['lab' => '1.', 'in' => ['LaceAbrasion1'], 'std' => 'LaceAbrasion_S', 'rate' => 'LaceAbrasiontxt_Rating'],
        ['lab' => '2.', 'in' => ['LaceAbrasion2']], ['lab' => '3.', 'in' => ['LaceAbrasion3']],
        ['lab' => '4.', 'in' => ['LaceAbrasion4']], ['lab' => '5.', 'in' => ['LaceAbrasion5']], ['lab' => '6.', 'in' => ['LaceAbrasion6']]]],
    ['Colorfastness to Perspiration<br>(Top Side/Finished Surface Only)', '耐汗渍测试(正面)', 'VF-E16<br>(2018/11)', 'AATCC', true, [['fib' => 'CtP', 'std' => 'CTP_S', 'rate' => 'CTPRating']]],
    ['Colorfastness to Perspiration<br>Back side', '耐汗渍测试(反面)', 'VF-E16<br>(2018/11)', 'AATCC', true, [['fib' => 'CtPB', 'std' => 'CtPB_S', 'rate' => 'CtPB_Rating']]],
    ['Colorfastness to Water<br>(Top Side/Finished Surface Only)', '耐水测试(正面)', 'VF-E01<br>(2008/05)', 'AATCC', true, [['fib' => 'CtW', 'std' => 'CTW_S', 'rate' => 'CTWRating']]],
    ['Colorfastness to Water<br>Back side', '耐水测试(反面)', 'VF-E01<br>(2008/05)', 'AATCC', true, [['fib' => 'CtWBackSide', 'std' => 'CtWB_S', 'rate' => 'CtWB_Rating']]],
    ['Colorfastness to Crocking', '摩擦褪色', 'VF-E02<br>(2024/08)', 'AATCC', true, [
        ['lab' => 'Dry 干=', 'in' => ['CtCDry'], 'std' => 'CtCDry_S', 'rate' => 'CTCDRYRating'],
        ['lab' => 'Wet 湿=', 'in' => ['CtCWet'], 'std' => 'CtCWet_S', 'rate' => 'CTCWETRating']]],
    ['UV light (24hrs, 50°C)', '紫外线', 'VF-E03<br>(2019/05)', 'AATCC', true, [['in' => ['UV1', 'UV2'], 'std' => 'UV_S', 'rate' => 'UVRating']]],
    ['Wicking', '虹吸', 'VF-E14<br>(2014/07)', 'mm', false, [['in' => ['Wicking']]]],
    ['Heat Aging (72hrs, 70°C)', '热老化', 'VF-E04<br>(2022/09)', 'AATCC', true, [['in' => ['HeatAging1', 'HeatAging2'], 'std' => 'HeatAging_S', 'rate' => 'HeatAgingRating']]],
    ['Hydrolysis (7Days, 60°C 95%RH)', '水解', 'VF-E19<br>(2024/12)', '', true, [['in' => ['Hydrolysis1', 'Hydrolysis2'], 'std' => 'Hydrolysis_S', 'rate' => 'HydrolysisRating']]],
]];

// ---------- EVA & Rubber Sponge & Foam (Lab VF 009) -> tbl_dtr_fthl_results ----------
$sheets['fthl'] = ['EVA &amp; Rubber Sponge &amp; Foam Test Results', 'EVA &amp; 橡胶发泡 &amp; 泡棉测试结果', 'Lab VF 009', [
    ['Hardness', '硬度', 'VF-A01<br>(2016/12)', 'C', true, [['in' => $m('Hardness', 6), 'mm' => ['Hardness_Max', 'Hardness_Min'], 'ave' => 'Hardness_Ave', 'std' => 'Hardness_Std', 'rate' => 'Hardness_Rate']]],
    ['Specific Gravity', '比重', 'VF-A04<br>(2018/11)', 'g/cm³', true, [['in' => $m('SG', 3), 'ave' => 'SG_Ave', 'std' => 'SG_Std', 'rate' => 'SG_Rate']]],
    ['Tensile', '抗拉', 'VF-B01<br>(2022/11)', 'kgf/cm²', true, [['in' => $m('Tensile', 3), 'ave' => 'Tensile_Ave', 'std' => 'Tensile_Std', 'rate' => 'Tensile_Rate']]],
    ['Elongation', '延伸', 'VF-B01<br>(2022/11)', '%', true, [['in' => $m('Elongation', 3), 'ave' => 'Elongation_Ave', 'std' => 'Elongation_Std', 'rate' => 'Elongation_Rate']]],
    $blank('300% Modulus', '300% 抗拉', 'VF-B01<br>(2022/11)', 'kgf/cm²'),
    ['Die-C tear', 'C型撕裂', 'VF-B05<br>(2022/11)', 'kgf/cm', true, [['in' => $m('DieC_', 3), 'ave' => 'DieC_Ave', 'std' => 'DieC_Std', 'rate' => 'DieC_Rate']]],
    $blank('Trouser Tear', '裤形撕裂', 'VF-B07<br>(2022/11)', 'kgf/cm'),
    $blank('Split Tear', '分层撕裂', 'VF-B06<br>(2022/11)', 'kgf/cm'),
    $blank('DIN Abrasion', 'DIN 耐磨', 'VF-C01<br>(2017/07)', 'mm³'),
    ['Shrinkage (1 hr, 60°C)', '收缩率', 'VF-E13<br>(2016/03)', '%', true, [
        ['lab' => 'L=', 'in' => ['ShrinkageL1', 'ShrinkageL2'], 'ave' => 'ShrinkageL', 'std' => 'Shrinkage_std', 'rate' => 'ShrinkageL_rate'],
        ['lab' => 'W=', 'in' => ['ShrinkageW1', 'ShrinkageW2'], 'ave' => 'ShrinkageW', 'rate' => 'ShrinkageW_rate']]],
    $blank('Compression (6 hrs, 45°C)', '压缩率', 'VF-B10<br>(2017/05)', '%'),
    $blank('Compression (22 hrs, 23°C)', '压缩率', 'VF-B10<br>(2017/05)', '%'),
    $blank('Flexing Ross (23±2°C)', 'Ross曲折', 'VF-D02<br>(2019/06)', '% growth'),
    $blank('Flexing Ross (-15°C)', 'Ross曲折', 'VF-D02<br>(2019/06)', '% growth'),
    $blank('Coefficient of Friction - TM144', '止滑', 'VF-B25<br>(2014/07)', 'COF', $cof),
    $blank('Laminar Peel Strength', '剥离强度', 'VF-F04<br>(2019/05)', 'kgf/cm', $dryWet()),
    ['Colorfastness to Crocking', '摩擦褪色', 'VF-E02<br>(2024/08)', 'AATCC', true, [
        ['lab' => 'Dry 干=', 'in' => ['CTC_DRY'], 'std' => 'CTC_DRY_std', 'rate' => 'CTC_DRY_Rating'],
        ['lab' => 'Wet 湿=', 'in' => ['CTC_WET'], 'std' => 'CTC_WET_std', 'rate' => 'CTC_WET_Rating']]],
    ['UV light (24hrs, 50°C)', '紫外光', 'VF-E03<br>(2019/05)', 'AATCC', true, [['in' => ['UV1', 'UV2'], 'std' => 'UV_std', 'rate' => 'UV_rate']]],
    ['Heat Aging (72hrs, 70°C)', '热老化', 'VF-E04<br>(2022/09)', 'AATCC', true, [['in' => ['HeatAging1', 'HeatAging2'], 'std' => 'HeatAging_Std', 'rate' => 'HeatAging_Rate']]],
    ['Blooming (7Days, 60°C 95%RH)', '吐霜', 'VF-E17<br>(2016/03)', '', true, [['in' => ['Blooming'], 'rate' => 'Blooming_Rate']]],
    ['Hydrolysis (7Days, 60°C 95%RH)', '水解', 'VF-E19<br>(2024/12)', '', true, [['in' => ['Hydrolysis1', 'Hydrolysis2'], 'std' => 'Hydrolysis_std', 'rate' => 'Hydrolysis_Rate']]],
    $blank('Glow in the dark', '', 'VF-E28<br>(2024/08)', '', $solvLab),
    $blank('Non Marking (PVC Tiles)', '无痕迹', 'VF-E21<br>(2014/07)', ''),
    $blank('Resiliency', '回弹', 'VF-B16<br>(2011/04)', '%'),
]];

// ---------- Hardware (Lab VF 011) -> tbl_dtr_hardware_results (its header columns are spelled differently) ----------
$sheets['hardware'] = ['Hardware Test Results', '金属制品测试报告', 'Lab VF 011', [
    $blank('Gauge', '规格', 'VF-A03<br>(2014/04)', 'mm'),
    $blank('Weight', '重量', 'VF-A02<br>(2018/11)', 'g/m²'),
    $blank('Tensile', '拉力', 'VF-B01<br>(2022/11)', 'kgf'),
    $blank('Tensile (After Aging)', '拉力', 'VF-B01<br>(2022/11)', 'kgf'),
    $blank('Tensile (After Hydrolysis)', '拉力', 'VF-B01<br>(2022/11)', 'kgf'),
    $blank('Elongation', '延伸', 'VF-B01<br>(2022/11)', '%'),
    ['Colorfastness to Crocking', '摩擦退色', 'VF-E02<br>(2024/08)', 'AATCC', true, [
        ['lab' => 'Dry 干=', 'in' => ['CtCDry'], 'std' => 'CtCDry_S', 'rate' => 'CTCDRY_R'],
        ['lab' => 'Wet 湿=', 'in' => ['CtCWet'], 'std' => 'CtCWet_S', 'rate' => 'CTCWET_R']]],
    ['UV Light (24hrs, 50°C)', '紫外线', 'VF-E03<br>(2019/05)', 'AATCC', true, [['in' => ['UV1', 'UV2'], 'std' => 'UV_S', 'rate' => 'UV_R']]],
    ['Heat Aging (72hrs, 70°C)', '热老化', 'VF-E04<br>(2019/05)', '', true, [['in' => ['HeatAging1', 'HeatAging2'], 'std' => 'HeatAging_S', 'rate' => 'HeatAging_R']]],
    ['Corrosion Resistance', '腐蚀', 'VF-E09<br>(2014/12)', '', true, [['in' => ['CorrosionRes1', 'CorrosionRes2'], 'rate' => 'Corrosion_R']]],
    ['Hydrolysis (7Days, 60°C 95%RH)', '水解', 'VF-E19<br>(2024/12)', '', true, [['in' => ['Hydrolysis'], 'rate' => 'Hydrolysis_R']]],
    $blank('Ink adhesion on Substrate<br>3M Scotch tape #600', '', 'VF-F05<br>(2014/07)', '', $dryWet()),
    ['Colorfastness to Water<br>(Top Side/Finished Surface Only)', '耐水测试(正面)', 'VF-E01<br>(2008/05)', 'AATCC', true, [['fib' => 'CtW', 'std' => 'CTW_S', 'rate' => 'CTWRating']]],
    $blank('PVC Contact Discoloration PVC', '移色', 'VF-E06<br>(2014/07)', 'AATCC'),
], ['hdr' => ['PO/Lot No' => 'PO/LotNo', 'Send by' => 'SendBy', 'Received Date' => 'RecievedDate', 'Tested Date' => 'TestedDate', 'Finished Date' => 'FinishedDate']]];

// ---------- Woven / Size / Transfer Label (printed "Eyelet & Label & Brand & Packaging", Lab VF 011) -> tbl_dtr_label_results ----------
$sheets['label'] = ['Eyelet &amp; Lable &amp; Brand &amp; Packaging Test Results', '眼扣 &amp; 布标 &amp; 商标 &amp; 包装袋测试报告', 'Lab VF 011', [
    $blank('Gauge', '规格', 'VF-A03', 'mm'),
    $blank('Weight', '重量', 'VF-A02', 'g/m²'),
    $blank('Tensile', '抗拉', 'VF-B01', 'kg'),
    $blank('Elongation', '延伸', 'VF-B01', '%'),
    $blank('Die-C tear', 'C型撕裂', 'VF-B05', 'kg'),
    $blank('Trouser Tear', '裤形撕裂', 'VF-B07', 'kg'),
    ['Stitch Tear', '车缝撕裂', 'VF-B08', 'kgf', true, [['in' => $m('StitchTear_', 3), 'ave' => 'StitchTear_ave', 'std' => 'StitchTear_std', 'rate' => 'StitchTear_Rating']]],
    $blank('10 kg Load Elongation', '伸长率', 'VF-B01', '%'),
    $blank('Load at 50% extension', '伸长50%抗拉', 'VF-B01', 'kg'),
    ['Heat Aging (72hrs, 70°C)', '热老化', 'VF-E04', 'ASTM', true, [['in' => ['HeatAging1', 'HeatAging2'], 'std' => 'HeatAging_std', 'rate' => 'HeatAgingRating']]],
    $blank('Corrosion Resistance', '腐蚀', 'VF-E09', 'ASTM'),
    ['Hydrolysis (7Days, 70°C, 95%RH)', '水解', 'VF-E17', '', true, [['in' => ['Hydrolysis1', 'Hydrolysis2'], 'std' => 'Hydrolysis_std', 'rate' => 'HydrolysisRating']]],
    $blank('Martindale Abrasion', '耐磨', 'VF-C04', 'cycles', $dryWet()),
    $blank('Mullen Burst', '爆破', 'VF-B02', 'kg/cm²'),
    ['Colorfastness to Perspiration<br>(Top Side/Finished Surface Only)', '耐汗渍测试(正面)', 'VF-E16', 'AATCC', true, [['fib' => 'CtP', 'std' => 'CTP_std', 'rate' => 'CTPRating']]],
    ['Colorfastness to Perspiration<br>Back side', '耐汗渍测试(反面)', 'VF-E16', 'AATCC', true, [['fib' => 'CtPB', 'std' => 'CtPB_std', 'rate' => 'CtPB_Rating']]],
    ['Colorfastness to Water<br>(Top Side/Finished Surface Only)', '耐水测试(正面)', 'VF-E01', 'AATCC', true, [['fib' => 'CtW', 'std' => 'CTW_std', 'rate' => 'CTWRating']]],
    ['Colorfastness to Water<br>Back side', '耐水测试(反面)', 'VF-E01', 'AATCC', true, [['fib' => 'CtWBackSide', 'std' => 'CtWB_std', 'rate' => 'CtWB_Rating']]],
    ['Colorfastness to Crocking', '摩擦褪色', 'VF-E02', 'AATCC', true, [
        ['lab' => 'Dry 干=', 'in' => ['CtCDry'], 'std' => 'CtCDry_std', 'rate' => 'CTCDRYRating'],
        ['lab' => 'Wet 湿=', 'in' => ['CtCWet'], 'std' => 'CtCWet_std', 'rate' => 'CTCWETRating']]],
    ['UV light (24hrs, 50°C)', '紫外线', 'VF-E03', 'AATCC', true, [['in' => ['UV1', 'UV2'], 'std' => 'UV_std', 'rate' => 'UVRating']]],
    $blank('Phenolic Yellowing', '酚黄', '', ''),
    $blank('Peeling Strength', '剥离强度', 'VF-F02', 'kg/cm'),
    $blank('PH Value', 'PH值', 'VF-A05', 'AATCC'),
    $blank('PVC Contact Discoloration', 'PVC 移色', 'VF-E06', 'AATCC'),
], ['fibers' => ['Wool' => ['Wool', '毛料'], 'Acrylic' => ['Orlon', '奥龙'], 'Polyester' => ['Dacron', '涤纶'], 'Nylon' => ['Nylon', '尼龙'], 'Cotton' => ['Cotton', '棉'], 'Acetate' => ['Acetate', '合成纤']]]];

// ---------- Sockliner / Foam (Lab VF 010) -> tbl_dtr_sklfm_results ----------
$cmp = static fn (string $p): array => [$p . 'AB1', $p . 'CD1', $p . 'AB2', $p . 'CD2', $p . 'AB3', $p . 'CD3', $p . 'AB4', $p . 'CD4'];
$sheets['sklfm'] = ['Sockliner Test Results', '鞋垫测试结果', 'Lab VF 010', [
    ['Hardness &nbsp;Forefoot', '硬度', 'VF-A01<br>(2016/12)', 'C', true, [['in' => $m('Hardness', 6), 'mm' => ['Hardness_Max', 'Hardness_Min'], 'ave' => 'Hardness_Ave', 'std' => 'Hardness_Std', 'rate' => 'Hardness_Rate']]],
    $blank('Hardness &nbsp;Heel', '硬度', 'VF-A01<br>(2016/12)', 'C'),
    $blank('Weight &nbsp;Left', '重量', 'VF-A02<br>(2018/11)', 'g'),
    $blank('Weight &nbsp;Right', '重量', 'VF-A02<br>(2018/11)', 'g'),
    $blank('Thickness (Forefoot)', '厚度', 'VF-A03<br>(2014/04)', 'mm'),
    $blank('Thickness (Heel)', '厚度', 'VF-A03<br>(2014/04)', 'mm'),
    ['Specific Gravity (Forefoot)', '比重', 'VF-A04<br>(2018/11)', 'g/cm³', true, [['in' => $m('SG', 3), 'ave' => 'SG_Ave', 'std' => 'SG_Std', 'rate' => 'SG_Rate']]],
    $blank('Specific Gravity (Heel)', '比重', 'VF-A04<br>(2018/11)', 'g/cm³'),
    ['Tensile', '抗拉', 'VF-B01<br>(2022/11)', 'kgf/cm²', true, [['in' => $m('Tensile', 3), 'ave' => 'Tensile_Ave', 'std' => 'Tensile_Std', 'rate' => 'Tensile_Rate']]],
    ['Elongation', '延伸', 'VF-B01<br>(2022/11)', '%', true, [['in' => $m('Elongation', 3), 'ave' => 'Elongation_Ave', 'std' => 'Elongation_Std', 'rate' => 'Elongation_Rate']]],
    ['Die-C tear', 'C型撕裂', 'VF-B05<br>(2022/11)', 'kgf/cm', true, [['in' => $m('DieC_', 3), 'ave' => 'DieC_Ave', 'std' => 'DieC_Std', 'rate' => 'DieC_Rate']]],
    ['Split Tear', '分层撕裂', 'VF-B06<br>(2022/11)', 'kgf/cm', true, [['in' => $m('SplitTear', 3), 'ave' => 'SplitTear_Ave', 'std' => 'SplitTear_Std', 'rate' => 'SplitTear_Rate']]],
    ['Compression (6 hrs, 45°C) (Forefoot)', '压缩率', 'VF-B10<br>(2017/05)', '%', true, [['in' => $cmp('Compression'), 'std' => 'Compression_Std', 'rate' => 'Compression_Rate']]],
    ['Compression (6 hrs, 45°C) (Heel)', '压缩率', 'VF-B10<br>(2017/05)', '%', true, [['in' => $cmp('CompressionF'), 'std' => 'CompressionF_Std', 'rate' => 'CompressionF_Rate']]],
    ['Martindale Abrasion', '耐磨', 'VF-C04<br>(2024/12)', 'cycles', true, [
        ['lab' => 'Dry 干=', 'in' => ['MartindaleDry_cycles'], 'std' => 'MartindaleDry_std', 'rate' => 'MartindaleDry_rate'],
        ['lab' => 'Wet 湿=', 'in' => ['MartindaleWet_cycles'], 'std' => 'MartindaleWet_std', 'rate' => 'MartindaleWet_rate'],
        ['lab' => 'Perspiration 汗液=']]],
    ['Colorfastness to Perspiration<br>(Top Side/Finished Surface Only)', '耐汗渍测试(正面)', 'VF-E16<br>(2018/11)', 'AATCC', true, [['fib' => 'CtP', 'std' => 'CTP_std', 'rate' => 'CTPRating']]],
    $blank('After aging Shrinkage (72 hr, 70°C)', '收缩率', 'VF-E13<br>(2016/03)', '%', [['lab' => 'L='], ['lab' => 'W=']]),
    ['Shrinkage (1 hr, 60°C)', '收缩率', 'VF-E13<br>(2016/03)', '%', true, [
        ['lab' => 'L=', 'in' => ['ShrinkageL1', 'ShrinkageL2'], 'ave' => 'ShrinkageL', 'std' => 'Shrinkage_std', 'rate' => 'ShrinkageL_rate'],
        ['lab' => 'W=', 'in' => ['ShrinkageW1', 'ShrinkageW2'], 'ave' => 'ShrinkageW', 'rate' => 'ShrinkageW_rate']]],
    $blank('After Hydrolysis Shrinkage (7Days, 60°C, 95%RH)', '收缩率', 'VF-E13<br>(2016/03)', '%', [['lab' => 'L='], ['lab' => 'W=']]),
    ['Colorfastness to Crocking', '摩擦褪色', 'VF-E02<br>(2024/08)', 'AATCC', true, [
        ['lab' => 'Dry 干=', 'in' => ['CrockingDry_aatcc'], 'std' => 'CrockingDry_std', 'rate' => 'CrockingDry_Rate'],
        ['lab' => 'Wet 湿=', 'in' => ['CrockingWet_aatcc'], 'std' => 'CrockingWet_std', 'rate' => 'CrockingWet_Rate']]],
    ['Abrasion Crocking Equipment', '摩擦褪色', 'VF-E02<br>(2024/08)', 'AATCC', true, [
        ['lab' => 'Dry 干=', 'in' => ['ACDry_text'], 'rate' => 'ACDry_rate'],
        ['lab' => 'Wet 湿=', 'in' => ['ACWet_text'], 'rate' => 'ACWet_rate'],
        ['lab' => 'Perspiration 汗液=']]],
    ['Laminar Peel Strength', '剥离强度', 'VF-F04<br>(2019/05)', 'kgf/cm', true, [
        ['lab' => 'Dry 干=', 'in' => $m('LaminarPeelDry', 5), 'mm' => ['LaminarPeelDryMax', 'LaminarPeelDryMin'], 'std' => 'LaminarPeelDry_Std', 'rate' => 'LaminarPeelDry_Rate'],
        ['lab' => 'Wet 湿=', 'in' => $m('LaminarPeelWet', 5), 'mm' => ['LaminarPeelWetMax', 'LaminarPeelWetMin'], 'std' => 'LaminarPeelWet_Std', 'rate' => 'LaminarPeelWet_Rate']]],
    ['Ink adhesion on Substrate<br>3M Scotch tape #600', '', 'VF-F05<br>(2014/07)', '', true, [
        ['lab' => 'Dry 干=', 'in' => ['InkAdhesion_Dry'], 'rate' => 'InkAdhesion_Dry_Rate'],
        ['lab' => 'Wet 湿=', 'in' => ['InkAdhesion_Wet'], 'rate' => 'InkAdhesion_Wet_Rate'],
        ['lab' => 'Perspiration 汗液=', 'in' => ['InkAdhesion_ASW'], 'rate' => 'InkAdhesion_ASW_Rate']]],
    ['UV Light (24hrs, 50°C)', '紫外线', 'VF-E03<br>(2019/05)', 'AATCC', true, [['in' => ['UV1', 'UV2'], 'std' => 'UV_std', 'rate' => 'UV_rate']]],
    $blank('Resiliency (Forefoot)', '回弹', 'VF-B16<br>(2011/04)', '%'),
    ['Heat Aging (72hrs, 70°C)', '热老化', 'VF-E04<br>(2022/09)', '', true, [['in' => ['HeatAging1', 'HeatAging2'], 'std' => 'HeatAging_Std', 'rate' => 'HeatAging_Rate']]],
    ['Hydrolysis (7Days, 60°C, 95%RH)', '水解', 'VF-E19<br>(2024/12)', '', true, [['in' => ['Hydrolysis1', 'Hydrolysis2'], 'std' => 'Hydrolysis_std', 'rate' => 'Hydrolysis_Rate']]],
]];

// ---------- Rubber & Plastic (Lab VF 008) -> tbl_dtr_rubber_results ----------
$sheets['rubber'] = ['Rubber &amp; Plastic Test Results', '橡胶 &amp; 塑胶测试结果', 'Lab VF 008', [
    ['Hardness', '硬度', 'VF-A01<br>(2016/12)', 'Asker A', true, [['in' => $m('Hardness', 6), 'mm' => ['Hardness_Max', 'Hardness_Min'], 'ave' => 'Hardness_Ave', 'std' => 'Hardness_Std', 'rate' => 'Hardness_Rate']]],
    ['Specific Gravity', '比重', 'VF-A04<br>(2018/11)', 'g/cm³', true, [['in' => $m('SG', 3), 'ave' => 'SG_Ave', 'std' => 'SG_Std', 'rate' => 'SG_Rate']]],
    ['Tensile', '抗拉', 'VF-B01<br>(2022/11)', 'kgf/cm²', true, [['in' => $m('Tensile', 3), 'ave' => 'Tensile_Ave', 'std' => 'Tensile_Std', 'rate' => 'Tensile_Rate']]],
    ['Elongation', '延伸', 'VF-B01<br>(2022/11)', '%', true, [['in' => $m('Elongation', 3), 'ave' => 'Elongation_Ave', 'std' => 'Elongation_Std', 'rate' => 'Elongation_Rate']]],
    $blank('300% Modulus', '300% 抗拉', 'VF-B01<br>(2022/11)', 'kgf/cm²'),
    ['Die-C tear', 'C型撕裂', 'VF-B05<br>(2022/11)', 'kgf/cm', true, [['in' => $m('DieC_', 3), 'ave' => 'DieC_Ave', 'std' => 'DieC_Std', 'rate' => 'DieC_Rate']]],
    $blank('Trouser Tear', '裤形撕裂', 'VF-B07<br>(2022/11)', 'kgf/cm'),
    ['DIN Abrasion', 'DIN 耐磨', 'VF-C01<br>(2017/07)', 'mm³', true, [['in' => $m('DIN_', 3), 'std' => 'DIN_Std', 'rate' => 'DIN_Rate']]],
    ['Flexing Ross (23±2°C)', 'Ross曲折', 'VF-D02<br>(2019/06)', '% growth', true, [['in' => ['RossFlex_1', 'RossFlex_2'], 'rate' => 'RossFlex_Rate']]],
    $blank('Flexing Ross (-15°C)', 'Ross曲折', 'VF-D02<br>(2019/06)', '% growth'),
    ['Shrinkage (1 hr, 70°C)', '收缩率', 'VF-E13<br>(2016/03)', '%', true, [
        ['lab' => 'L=', 'in' => ['ShrinkageL_before', 'ShrinkageL_after'], 'ave' => 'ShrinkageL', 'std' => 'Shrinkage_Std', 'rate' => 'ShrinkageL_Rate'],
        ['lab' => 'W=', 'in' => ['ShrinkageW_before', 'ShrinkageW_after'], 'ave' => 'ShrinkageW', 'rate' => 'ShrinkageW_Rate']]],
    ['Shrinkage after aging (72 hr, 70°C)', '老化后收缩率', 'VF-E13<br>(2016/03)', '%', true, [
        ['lab' => 'L=', 'in' => ['ShrinkageAAL_before', 'ShrinkageAAL_after'], 'ave' => 'ShrinkageAAL', 'std' => 'ShrinkageAA_Std', 'rate' => 'ShrinkageAAL_Rate'],
        ['lab' => 'W=', 'in' => ['ShrinkageAAW_before', 'ShrinkageAAW_after'], 'ave' => 'ShrinkageAAW', 'rate' => 'ShrinkageAAW_Rate']]],
    ['Shrinkage After Hydrolysis (168 hr, 60°C)', '水解后收缩率', 'VF-E13<br>(2016/03)', '%', true, [
        ['lab' => 'L=', 'in' => ['ShrinkageAHL_before', 'ShrinkageAHL_after'], 'ave' => 'ShrinkageAHL', 'std' => 'ShrinkageAH_Std', 'rate' => 'ShrinkageAHL_Rate'],
        ['lab' => 'W=', 'in' => ['ShrinkageAHW_before', 'ShrinkageAHW_after'], 'ave' => 'ShrinkageAHW', 'rate' => 'ShrinkageAHW_Rate']]],
    $blank('Coefficient of Friction - TM144', '止滑', 'VF-B25<br>(2014/07)', 'COF', $cof),
    $blank('Laminar Peel Strength', '剥离强度', 'VF-F04<br>(2019/05)', 'kgf/cm', $dryWet()),
    $blank('Colorfastness to Crocking', '摩擦褪色', 'VF-E02<br>(2024/08)', 'AATCC', $dryWet()),
    ['UV light (24hrs, 50°C)', '紫外光', 'VF-E03<br>(2019/05)', 'AATCC', true, [['in' => ['UV1', 'UV2'], 'std' => 'UV_Std', 'rate' => 'UV_Rate']]],
    ['Heat Aging (72hrs, 70°C)', '热老化', 'VF-E04<br>(2022/09)', 'AATCC', true, [['in' => ['HeatAging1', 'HeatAging2'], 'std' => 'HeatAging_Std', 'rate' => 'HeatAging_Rate']]],
    ['Hydrolysis (7Days, 60°C 95%RH)', '水解', 'VF-E17<br>(2016/03)', '', true, [['in' => ['Hydrolysis'], 'rate' => 'Hydrolysis_Rate']]],
    ['Blooming (7Days, 60°C 95%RH)', '吐霜', 'VF-E17<br>(2016/03)', '', true, [['in' => ['Blooming'], 'rate' => 'Blooming_Rate']]],
    $blank('Non Marking (PVC Tiles)', '无痕迹', 'VF-E21<br>(2014/07)', 'AATCC'),
]];

// ---------- Carton Box & Inner Box (Lab VF 012) -> tbl_dtr_box_results ----------
$sheets['box'] = ['Carton Box &amp; Inner Box Test Results', '外箱 &amp; 内盒 测试结果', 'Lab VF 012', [
    ['Gauge', '规格', 'VF-A03<br>(2014/04)', 'mm', true, [['in' => ['Gauge']]]],
    ['Weight', '重量', 'VF-A02<br>(2008/05)', 'g/m²', true, [['in' => ['Weight']]]],
    ['Tensile', '拉力', 'VF-B01<br>(2022/11)', 'kgf/cm', true, [['lab' => 'L=', 'in' => ['Tensile_L']], ['lab' => 'W=', 'in' => ['Tensile_W']]]],
    ['Mullen Burst', '爆破', 'VF-B02<br>(2019/05)', 'kgf/cm²', true, [
        ['lab' => 'Inner box', 'in' => $m('MullenBurst_In', 3), 'ave' => 'MullenBurstIn_Ave', 'std' => 'MullenBurstIn_std', 'rate' => 'MullenBurstIn_Rating'],
        ['lab' => 'Outer box', 'in' => $m('MullenBurst_O', 3), 'ave' => 'MullenBurstO_Ave', 'std' => 'MullenBurstO_std', 'rate' => 'MullenBurstO_Rating']]],
    ['Trouser Tear', '裤形撕裂', 'VF-B07<br>(2022/11)', 'kgf', true, [['lab' => 'Inner box', 'in' => ['TrouserTear_I']], ['lab' => 'Outer box', 'in' => ['TrouserTear_O']]]],
    ['Edge Crush Test', '边压', 'VF-B27<br>(2019/05)', 'kgf/m', true, [['in' => $m('EdgeCrushTest', 6), 'ave' => 'EdgeCrushTest_Ave', 'std' => 'EdgeCrushTest_Std', 'rate' => 'EdgeCrushTest_Rating']]],
    ['Cardboard Adhesion - Dry', '剥离强度', 'VF-F11<br>(2019/05)', 'kgf/cm', true, [
        ['lab' => 'L=', 'in' => $m('CardboardAdhesionDryL', 3), 'ave' => 'CardboardAdhesionDryL_Ave', 'std' => 'CardboardAdhesionDryL_Std', 'rate' => 'CardboardAdhesionDryL_Rating'],
        ['lab' => 'W=', 'in' => $m('CardboardAdhesionDryW', 3), 'ave' => 'CardboardAdhesionDryW_Ave', 'std' => 'CardboardAdhesionDryW_Std', 'rate' => 'CardboardAdhesionDryW_Rating']]],
    ['Cardboard Adhesion - Wet', '剥离强度', 'VF-F11<br>(2019/05)', '', true, [
        ['lab' => 'L=', 'in' => ['CardboardAdhesionWetL'], 'rate' => 'CardboardAdhesionWetL_Rating'],
        ['lab' => 'W=', 'in' => ['CardboardAdhesionWetW'], 'rate' => 'CardboardAdhesionWetW_Rating']]],
    ['Colorfastness to Crocking', '摩擦褪色', 'VF-E02<br>(2024/08)', 'AATCC', true, [
        ['lab' => 'Dry 干=', 'in' => ['CtCDry'], 'std' => 'CtCDry_std', 'rate' => 'CtCDry_Rating'],
        ['lab' => 'Wet 湿=', 'in' => ['CtCWet'], 'std' => 'CtCWet_std', 'rate' => 'CtCWet_Rating']]],
    $blank('Rubbing Fastness (GB)', 'GB摩擦', 'VF-E24<br>(2014/05)', 'AATCC'),
    $blank('Contact Discoloration', '接触溢色', 'VF-E04<br>(2008/05)', 'AATCC'),
]];

// ---------- Insole / Carbon Fiber Board (Lab VF 018) -> tbl_dtr_insole_results ----------
$sheets['insole'] = ['Insole / Carbon Fiber Board Test Results', '中底 / 碳纤片测试结果', 'Lab VF 018', [
    ['Gauge', '规格', 'VF-A03<br>(2014/04)', 'mm', true, [['in' => $m('Thickness', 6), 'mm' => ['TMax', 'TMin'], 'ave' => 'TAve', 'rate' => 'TRating']]],
    ['Weight', '重量', 'VF-A02<br>(2018/11)', 'g/m²', true, [['in' => $m('Weight', 3), 'ave' => 'WAve', 'rate' => 'WRating']]],
    ['Tensile', '拉力', 'VF-B01<br>(2022/11)', 'kgf/cm', true, [
        ['lab' => 'L=', 'in' => $m('TensileL', 3), 'ave' => 'TensileLAve', 'std' => 'Tensile_L', 'rate' => 'TensileLRating'],
        ['lab' => 'W=', 'in' => $m('TensileW', 3), 'ave' => 'TensileWAve', 'std' => 'Tensile_W', 'rate' => 'TensileWRating']]],
    ['Elongation', '延伸', 'VF-B01<br>(2022/11)', '%', true, [
        ['lab' => 'L=', 'in' => $m('ElongationL', 3), 'ave' => 'ElongationLAve', 'std' => 'Elongation_L', 'rate' => 'ElongationLRating'],
        ['lab' => 'W=', 'in' => $m('ElongationW', 3), 'ave' => 'ElongationWAve', 'std' => 'Elongation_W', 'rate' => 'ElongationWRating']]],
    ['Mullen Burst', '爆破', 'VF-B02<br>(2019/05)', 'kgf/cm²', true, [['in' => $m('MullenBurst', 3), 'ave' => 'MullenBurstAve', 'std' => 'MullenBurst', 'rate' => 'MullenBurstRating']]],
    ['Trouser Tear', '裤形撕裂', 'VF-B07<br>(2022/11)', 'kgf', true, [
        ['lab' => 'L=', 'in' => ['Trouser TearL1', 'Trouser TearL2', 'Trouser TearL3'], 'ave' => 'TrouserTearLAve', 'std' => 'TrouserTear_L', 'rate' => 'TrouserTearLRating'],
        ['lab' => 'W=', 'in' => ['Trouser TearW1', 'Trouser TearW2', 'Trouser TearW3'], 'ave' => 'TrouserTearWAve', 'std' => 'TrouserTear_W', 'rate' => 'TrouserTearWRating']]],
    $blank('Water Swelling', '膨胀性', 'VF-E18<br>(2008/05)', '%'),
    $blank('Water Absorption', '吸水率', 'VF-E10<br>(2014/07)', '%'),
    $blank('Moisture Content 100°C 16 Hrs', '', 'VF-E12<br>(2008/05)', '%'),
    ['Shrinkage (1 hr, 60°C)', '收缩率', 'VF-E13<br>(2016/03)', '%', true, [
        ['lab' => 'L=', 'in' => ['ShrinkageL1', 'ShrinkageL2'], 'ave' => 'ShrinkageL', 'std' => 'Shrinkage_std', 'rate' => 'ShrinkageL_rate'],
        ['lab' => 'W=', 'in' => ['ShrinkageW1', 'ShrinkageW2'], 'ave' => 'ShrinkageW', 'rate' => 'ShrinkageW_rate']]],
    $blank('Laminar Peel Strength', '剥离强度', 'VF-F04<br>(2019/05)', 'kgf/cm', $dryWet()),
    ['Martindale Abrasion', '耐磨', 'VF-C04<br>(2024/12)', 'cycles', true, [
        ['lab' => 'Dry 干=', 'in' => ['MartindaleD1'], 'std' => 'MartindaleD1_S', 'rate' => 'MartindaleD1Rating'],
        ['lab' => 'Wet 湿=', 'in' => ['MartindaleW1'], 'std' => 'MartindaleW1_S', 'rate' => 'MartindaleW1Rating']]],
    $blank('Flexing Ross', 'Ross曲折', 'VF-D02<br>(2019/05)', 'Rating'),
    ['Colorfastness to Perspiration -<br>(Grain side)', '耐汗渍测试(正面)', 'VF-E16<br>(2018/11)', 'AATCC', true, [['fib' => 'CtP', 'std' => 'CTP', 'rate' => 'CTPRating']]],
    ['Colorfastness to Perspiration -<br>Back side', '耐汗渍测试(反面)', 'VF-E16<br>(2018/11)', 'AATCC', true, [['fib' => 'CtPB', 'std' => 'CtPB_S', 'rate' => 'CtPB_Rating']]],
    ['Colorfastness to Water<br>(Grain side)', '耐水测试(正面)', 'VF-E01<br>(2008/05)', 'AATCC', true, [['fib' => 'CtW', 'std' => 'CTW', 'rate' => 'CTWRating']]],
    ['Colorfastness to Water<br>Back side', '耐水测试(反面)', 'VF-E01<br>(2008/05)', 'AATCC', true, [['fib' => 'CtWBackSide', 'std' => 'CtWB_S', 'rate' => 'CtWB_Rating']]],
    ['Colorfastness to Crocking', '摩擦褪色', 'VF-E02<br>(2024/08)', 'AATCC', true, [
        ['lab' => 'Dry 干=', 'in' => ['CtCDry'], 'std' => 'CtCDry_S', 'rate' => 'CTCDRYRating'],
        ['lab' => 'Wet 湿=', 'in' => ['CtCWet'], 'std' => 'CtCWet_S', 'rate' => 'CTCWETRating']]],
    ['UV Light (24hrs, 50°C)', '紫外线', 'VF-E03<br>(2019/05)', 'AATCC', true, [['in' => ['UV1', 'UV2'], 'std' => 'UV_S', 'rate' => 'UVRating']]],
    ['Heat Aging (72hrs, 70°C)', '热老化', 'VF-E04<br>(2022/09)', '', true, [['in' => ['HeatAging1', 'HeatAging2'], 'std' => 'HeatAging_S', 'rate' => 'HeatAgingRating']]],
    ['Hydrolysis (7Days, 60°C, 95%RH)', '水解', 'VF-E19<br>(2024/12)', '', true, [['in' => ['Hydrolysis1', 'Hydrolysis2'], 'std' => 'Hydrolysis_S', 'rate' => 'HydrolysisRating']]],
    $blank('Wicking', '虹吸', 'VF-E14<br>(2014/07)', 'mm'),
]];

return $sheets;
