<?php

namespace App\Libraries;

/**
 * From a received material (Lab Tracker > Lab Material Test Collection / Material Testing) to its Material Test Report (Lab Reports > Material Test Report):
 * the report record of the right form (Fabric, Leather, Webbing / Shoelace / Thread, ... = tbl_dtr_<form>_results, columns named as in the Access forms) is
 * created or completed from the material (Lab Ref, material type, name, supplier, style, colour, MTL, quantity, dates, technician) and from the values of the
 * tests entered on the Material Testing sheet (specimens, averages, standards, ratings), so the report opens with the material type selected and the results in.
 */
class MaterialReport
{
    /** category of the catalog -> form of the Material Test Report */
    public const SLUG = ['fabric' => 'fabric', 'leather' => 'leather', 'sockliner-foam' => 'sklfm', 'foxing-heel-logo' => 'fthl', 'rubber-outsole' => 'rubber', 'shoelace' => 'wst', 'thread' => 'wst',
        'webbing' => 'wst', 'elastic-gore' => 'wst', 'insole' => 'insole', 'velcro' => 'wst', 'label' => 'label', 'box' => 'box', 'eyelet-grommet' => 'hardware'];

    private const FIBRES = ['Wool', 'Acrylic', 'Polyester', 'Nylon', 'Cotton', 'Acetate'];

    /** the report table of a form slug */
    public static function table(string $slug): string
    {
        return (string) (\App\Controllers\DtrController::FORM_CONFIG[$slug]['table'] ?? '');
    }

    /** @return array{slug:string,table:string,type:string,fields:array<string,mixed>,unmapped:list<string>,mother:array<string,mixed>}|null */
    public static function build(string $labRef): ?array
    {
        $db     = \Config\Database::connect();
        $mother = $db->table('tbl_dtr_MaterialTracker')->where('LabRefNo', $labRef)->get()->getRowArray();
        if (! $mother) {
            return null;
        }
        $spec = (string) ($db->query('SELECT MaterialType FROM tbl_dtr_material_type_status WHERE MaterialCode = ? LIMIT 1', [$mother['MaterialCode']])->getRow('MaterialType') ?? '');
        $res  = MaterialCatalog::resolve($spec !== '' ? $spec : (string) $mother['MaterialType']) ?? MaterialCatalog::resolve((string) $mother['MaterialType']);
        if (! $res || ! isset(self::SLUG[$res['category']])) {
            return null;
        }
        $slug  = self::SLUG[$res['category']];
        $table = self::table($slug);
        $cols  = array_flip($db->getFieldNames($table));
        $type  = implode('|', $res['types']);
        $f     = [];
        $unm   = [];
        // $put: the first of the column names the form really has
        $put = static function (array $names, $value) use (&$f, $cols, &$unm): void {
            if ($value === null || $value === '') {
                return;
            }
            foreach ($names as $n) {
                if (isset($cols[$n])) {
                    $f[$n] = $value;

                    return;
                }
            }
            if ($names) {
                $unm[] = $names[0];
            }
        };
        $tpl = static function (array $templates, int $i): array {
            return array_map(static fn ($t) => str_replace('{i}', (string) $i, $t), $templates);
        };

        // ---- the material
        $put(['LabRefNo'], $labRef);
        $put(['MaterialType', 'FabricType'], $res['type']);
        $put(['Material Description'], (string) $mother['MaterialName']);
        $put(['Supplier/Factory'], (string) $mother['Supplier']);
        $put(['MaterialCode'], (string) $mother['MaterialCode']);
        $put(['Model No/Style'], (string) $mother['Style']);
        $put(['Color'], (string) $mother['Colorway']);
        $put(['PO/Lot No', 'PO/LotNo'], (string) $mother['ProductionCode']);
        $put(['MTL'], (string) $mother['MTL']);
        $put(['Quantity'], trim((string) $mother['Qty']) !== '' ? trim((string) $mother['Qty'] . ' ' . (string) ($mother['Unit'] ?? '')) : null);
        $put(['Received Date', 'RecievedDate'], substr((string) $mother['ReceivedDate'], 0, 10));
        $put(['MaterialSource'], 'Prod. Material');
        $put(['ContainerNo'], (string) $mother['ContainerNo']);

        // ---- the tests
        $tests = $db->query('SELECT * FROM tbl_dtr_MaterialTrackerTests WHERE MotherLabRefNo = ? ORDER BY SortNo, TestID', [$labRef])->getResultArray();
        $gauge = MaterialSheet::gaugeOf($labRef);
        $dates = [];
        $techs = [];
        $all   = true;
        $any   = false;
        $worst = '';
        foreach ($tests as $t) {
            $code = (string) $t['TestCode'];
            $vals = json_decode((string) ($t['ValuesJson'] ?? ''), true) ?: [];
            if ($t['TestedDate']) {
                $dates[] = substr((string) $t['TestedDate'], 0, 10);
            }
            if (trim((string) $t['Technician']) !== '') {
                $techs[] = trim((string) $t['Technician']);
            }
            $all = $all && ($t['Status'] ?? '') === 'Completed';
            if (! $vals) {
                continue;
            }
            $any = true;
            $c   = MaterialSheet::compute($code, $type, $res['category'], $vals, $gauge);
            $R   = $c['rows'];
            if (strtoupper((string) $t['Result']) === 'FAIL') {
                $worst = 'FAIL';
            } elseif (strtoupper((string) $t['Result']) === 'TOLERABLE' && $worst !== 'FAIL') {
                $worst = 'TOLERABLE';
            }
            $val  = static fn (string $k) => $vals[$k] ?? null;
            $rate = static fn (string $k) => ($R[$k]['rating'] ?? '') ?: null;
            // numbers of a row: <k>1..<n> into the columns of templates, then Ave / min / max / rating
            $series = function (string $k, int $n, array $in, array $ave, array $rating, array $mx = [], array $mn = []) use ($put, $tpl, $val, $R, $rate): void {
                for ($i = 1; $i <= $n; $i++) {
                    $put($tpl($in, $i), $val($k . $i));
                }
                if (isset($R[$k]['ave'])) {
                    $put($ave, $R[$k]['ave']);
                }
                if ($mx && isset($R[$k]['max'])) {
                    $put($mx, $R[$k]['max']);
                }
                if ($mn && isset($R[$k]['min'])) {
                    $put($mn, $R[$k]['min']);
                }
                $put($rating, $rate($k));
            };
            switch ($code) {
                case 'GAU':
                    $series('T', 6, ['Thickness{i}'], ['TAve'], ['TRating'], ['TMax'], ['TMin']);
                    break;
                case 'WGT':
                    foreach ([1, 2, 3] as $i) {
                        $r = $vals['V_r' . $i] ?? null;
                        $l = $vals['V_l' . $i] ?? null;
                        $put($tpl(['RawWeight{i}'], $i), $r);
                        $put($tpl(['Length{i}'], $i), $l);
                        $put($tpl(['Weight{i}'], $i), $R['V']['vals'][$i - 1] ?? null);
                    }
                    $put(['WAve'], $R['V']['ave'] ?? null);
                    $put(['WRating'], $rate('V'));
                    break;
                case 'YRN':
                    $series('WARP', 2, ['YCWarp{i}'], ['YCWaAve'], ['YCWaRating']);
                    $series('WELF', 2, ['YCWelf{i}'], ['YCWeAve'], ['YCWeRating']);
                    break;
                case 'TEN': case 'ELO': case 'TRT': case 'STT': case 'BAU':
                    $b = ['TEN' => 'Tensile', 'ELO' => 'Elongation', 'TRT' => 'TrouserTear', 'STT' => 'StitchTear', 'BAU' => 'BaumannTear'][$code];
                    foreach (['L', 'W'] as $d) {
                        if (isset($R[$d])) {
                            $series($d, 3, [$b . $d . '{i}', preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $b) . $d . '{i}'], [$b . $d . 'Ave'], [$b . $d . 'Rating', $b . $d . '_Rating', $b . $d . '_R']);
                        }
                    }
                    if (isset($R['V'])) {
                        $series('V', 3, [$b . '{i}', $b . '_{i}', $b . 'L{i}'], [$b . '_Ave', $b . 'Ave', $b . '_ave', $b . 'LAve'], [$b . '_Rate', $b . '_Rating', $b . 'Rating', $b . '_rate', $b . 'LRating']);
                    }
                    break;
                case 'TAA':
                    $series('V', 3, ['TensileAA{i}'], ['TensileAAAve'], ['TensileAA_Rating']);
                    break;
                case 'MUL':
                    $series('V', 3, ['MullenBurst{i}'], ['MullenBurstAve', 'Text3977'], ['MullenBurstRating']);
                    break;
                case 'MBI':
                    $series('V', 3, ['MullenBurst_In{i}'], ['MullenBurstIn_Ave'], ['MullenBurstIn_Rating']);
                    break;
                case 'MBO':
                    $series('V', 3, ['MullenBurst_O{i}'], ['MullenBurstO_Ave'], ['MullenBurstO_Rating']);
                    break;
                case 'EDG':
                    $series('V', 3, ['EdgeCrushTest{i}'], ['EdgeCrushTest_Ave'], ['EdgeCrushTest_Rating']);
                    break;
                case 'CAD':
                    foreach (['L', 'W'] as $d) {
                        $series($d, 3, ['CardboardAdhesionDry' . $d . '{i}'], ['CardboardAdhesionDry' . $d . '_Ave'], ['CardboardAdhesionDry' . $d . '_Rating']);
                    }
                    break;
                case 'CAW':
                    foreach (['L', 'W'] as $d) {
                        $put(['CardboardAdhesionWet' . $d], $val($d));
                        $put(['CardboardAdhesionWet' . $d . '_Rating'], $rate($d));
                    }
                    break;
                case 'HRD':
                    $series('V', 6, ['Hardness{i}'], ['Hardness_Ave'], ['Hardness_Rate', 'Hardness_RateC'], ['Hardness_Max'], ['Hardness_Min']);
                    break;
                case 'SPG':
                    foreach ([1, 2, 3] as $i) {
                        $put($tpl(['SG_W{i}'], $i), $vals['V_w' . $i] ?? null);
                        $put($tpl(['SG_T{i}'], $i), $vals['V_t' . $i] ?? null);
                        $put($tpl(['SG{i}'], $i), $R['V']['vals'][$i - 1] ?? null);
                    }
                    $put(['SG_Ave'], $R['V']['ave'] ?? null);
                    $put(['SG_Rate'], $rate('V'));
                    break;
                case 'DIC':
                    $series('V', 3, ['DieC_{i}', 'DieC{i}'], ['DieC_Ave'], ['DieC_Rate']);
                    break;
                case 'SPT':
                    $series('V', 3, ['SplitTear{i}'], ['SplitTear_Ave'], ['SplitTear_Rate']);
                    break;
                case 'KNT':
                    $series('V', 6, ['KnotSlip{i}'], ['KnotSlipAve'], ['KnotSlip_Rating']);
                    break;
                case 'TIP':
                    $series('V', 6, ['LaceTipRet{i}'], ['LaceTipRetAve'], ['LaceTipRet_Rating']);
                    break;
                case 'PLS':
                    $series('V', 4, ['PeelStrength{i}'], ['PeelStrength_Ave'], ['PeelStrength_Rating']);
                    break;
                case 'SHS':
                    $series('V', 4, ['ShearStrength{i}'], ['ShearStrength_Ave'], ['ShearStrength_Rating']);
                    break;
                case 'LAB':
                    for ($i = 1; $i <= 6; $i++) {
                        $put(['LaceAbrasion' . $i], $val('S' . $i . 'c'));
                        $put(['LaceAbrasiontxt' . $i], $val('S' . $i . 't'));
                    }
                    $put(['LaceAbrasiontxt_Rating'], $c['result'] ?: null);
                    break;
                case 'LAM':
                    foreach (['DRY' => 'Dry', 'WET' => 'Wet'] as $k => $n) {
                        $series($k, 5, ['LaminarPeel' . $n . '{i}'], [], ['LaminarPeel' . $n . '_Rate'], ['LaminarPeel' . $n . 'Max'], ['LaminarPeel' . $n . 'Min']);
                    }
                    break;
                case 'MAR':
                    foreach (['D1', 'D2', 'W1', 'W2'] as $k) {
                        if (isset($R[$k])) {
                            $put(['Martindale' . $k], $val($k . 'c'));
                            $put(['txtMartindale' . $k], $val($k . 't'));
                            $put(['Martindale' . $k . 'Rating'], $rate($k));
                        }
                    }
                    foreach (['D' => 'Dry', 'W' => 'Wet'] as $k => $n) {
                        if (isset($R[$k])) {
                            $put(['Martindale' . $n . '_cycles'], $val($k . 'c'));
                            $put(['Martindale' . $n . '_txt'], $val($k . 't'));
                            $put(['Martindale' . $n . '_rate'], $rate($k));
                        }
                    }
                    break;
                case 'TAB':
                    foreach ([1, 2] as $i) {
                        $put(['TaberAbrasion' . $i], $val('T' . $i . 'c'));
                        $put(['TaberAbrasiontxt' . $i], $val('T' . $i . 't'));
                    }
                    $put(['TaberRating'], $c['result'] ?: null);
                    break;
                case 'BAL':
                    foreach (['DryL', 'DryW', 'WetL', 'WetW', 'AHL', 'AHW'] as $k) {
                        $put(['Bally' . $k . '1'], $val($k . '1'));
                        $put(['Bally' . $k . '2'], $val($k . '2'));
                        $put(['Bally' . $k . '_R', 'Bally' . $k . '_Rating'], $rate($k));
                    }
                    break;
                case 'PVC':
                    $put(['PVC_1'], $val('V1'));
                    $put(['PVC_2'], $val('V2'));
                    $put(['PVC_Rating'], $rate('V'));
                    break;
                case 'CTP': case 'CTW': case 'PER':
                    foreach (['TOP', 'BACK', 'V'] as $k) {
                        if (! isset($R[$k])) {
                            continue;
                        }
                        foreach (self::FIBRES as $fb) {
                            $v = $val($k . '_' . strtolower($fb));
                            if ($code === 'CTW') {
                                $put($k === 'BACK' ? ['CtWBackSide' . $fb] : ['CtW' . $fb], $v);
                            } else {
                                $put($k === 'BACK' ? ['CtPB' . $fb] : ['CtP' . $fb], $v);
                            }
                        }
                        $put($code === 'CTW' ? ($k === 'BACK' ? ['CtWB_Rating'] : ['CTWRating']) : ($k === 'BACK' ? ['CtPB_Rating'] : ['CTPRating']), $rate($k));
                    }
                    break;
                case 'UVA':
                    $put(['UV1'], $val('V1'));
                    $put(['UV2'], $val('V2'));
                    $put(['UVRating', 'UV_rate', 'UV_Rate', 'UV_R'], $rate('V'));
                    break;
                case 'AGU':
                    $put(['UV1_AGLET'], $val('V1'));
                    $put(['UV2_AGLET'], $val('V2'));
                    $put(['UV_rating_AGLET'], $rate('V'));
                    break;
                case 'HAG': case 'AGH':
                    $ag = $code === 'AGH';
                    foreach ([1, 2] as $i) {
                        $put($ag ? ['HeatAging' . $i . '_AGLET'] : ['HeatAging' . $i], $val('S' . $i . 'c'));
                        $put($ag ? ['txtHeatAging' . $i . '_AGLET'] : ['HeatAgingTxt' . $i, 'HeatAgingtxt', 'HeatAgingTxt1'], $val('S' . $i . 't'));
                    }
                    $put($ag ? ['HeatAging_rating_AGLET'] : ['HeatAgingRating', 'HeatAging_Rate', 'HeatAging_R'], $c['result'] ?: null);
                    break;
                case 'HYD': case 'AGY':
                    $ag = $code === 'AGY';
                    foreach ([1, 2] as $i) {
                        $put($ag ? ['Hydro' . $i . '_AGLET'] : ['Hydrolysis' . $i], $val('S' . $i . 'c'));
                        $put($ag ? ['txtHydro' . $i . '_AGLET'] : ['HydrolysisTxt' . $i, 'Hydrolysistxt', 'HydrolysisTxt1', 'Hydrolysis'], $val('S' . $i . 't'));
                    }
                    $put($ag ? ['Hydro_rating_AGLET'] : ['HydrolysisRating', 'Hydrolysis_Rate', 'Hydrolysis_R', 'Hydrolysis_RateC'], $c['result'] ?: null);
                    break;
                case 'CRK':
                    $put(['CtCDry', 'CrockingDry_aatcc', 'CTC_DRY'], $val('DRY1'));
                    $put(['CTCDRYRating', 'CrockingDry_Rate', 'CTC_DRY_Rating', 'CTCDRY_R', 'CtCDry_Rating'], $rate('DRY'));
                    $put(['CtCWet', 'CrockingWet_aatcc', 'CTC_WET'], $val('WET1'));
                    $put(['CTCWETRating', 'CrockingWet_Rate', 'CTC_WET_Rating', 'CTCWET_R', 'CtCWet_Rating'], $rate('WET'));
                    break;
                case 'ACK':
                    $put(['CADry', 'ACDry_text', 'AbrasionCrockingDRYTXT'], $val('DRY'));
                    $put(['AbrasionCrockingDRY_Rating', 'ACDry_rate', 'AbrasionCrockingDRYTXT_Rating'], $rate('DRY'));
                    $put(['CAWet', 'ACWet_text'], $val('WET'));
                    $put(['AbrasionCrockingWET_Rating', 'ACWet_rate'], $rate('WET'));
                    $put(['CASEAWATER'], $val('ASW'));
                    $put(['AbrasionCrockingASW_Rating'], $rate('ASW'));
                    break;
                case 'INK':
                    $put(['IAoSDry', 'InkAdhesion_Dry', 'IAOSDRYTXT'], $val('DRY'));
                    $put(['InkAdhesionOSDRY_Rating', 'InkAdhesion_Dry_Rate', 'IAOSDRYTXT_Rating'], $rate('DRY'));
                    $put(['IAoSWet', 'InkAdhesion_Wet', 'IAOSWETTXT'], $val('WET'));
                    $put(['InkAdhesionOSWET_Rating', 'InkAdhesion_Wet_Rate', 'IAOSWETTXT_Rating'], $rate('WET'));
                    $put(['IAoSSEAWATER', 'InkAdhesion_ASW', 'IAOSASWTXT'], $val('ASW'));
                    $put(['InkAdhesionOSASW_Rating', 'InkAdhesion_ASW_Rate', 'IAOSASWTXT_Rating'], $rate('ASW'));
                    break;
                case 'BLM':
                    $put(['Blooming'], $val('V'));
                    $put(['Blooming_Rate', 'Blooming_RateC'], $rate('V'));
                    break;
                case 'COR':
                    $put(['CorrosionRes1'], $val('S1'));
                    $put(['CorrosionRes2'], $val('S2'));
                    $put(['Corrosion_R'], $c['result'] ?: null);
                    break;
                case 'SHR': case 'SHA': case 'SHH':
                    $p = ['SHR' => '', 'SHA' => 'AA', 'SHH' => 'AH'][$code];
                    foreach (['L', 'W'] as $d) {
                        $put(['Shrinkage' . $p . $d . '_before', 'Shrinkage' . $d . '1'], $val($d . '_b1'));
                        $put(['Shrinkage' . $p . $d . '_after', 'Shrinkage' . $d . '2'], $val($d . '_a1'));
                        $put(['Shrinkage' . $p . $d], $R[$d]['ave'] ?? null);
                        $put(['Shrinkage' . $p . $d . '_Rate', 'Shrinkage' . $p . $d . '_rate', 'Shrinkage' . $d . 'Rating', 'Shrinkage' . $d . '_rate'], $rate($d));
                    }
                    break;
                case 'CMP':
                    $put(['Compression_Spacer'], $val('SP1'));
                    foreach (['AB', 'CD'] as $g) {
                        foreach ([1, 2, 3, 4] as $i) {
                            $put(['Compression_BEFORE_' . $g . '_T' . $i], $val($g . '_b' . $i));
                            $put(['Compression_AFTER_' . $g . '_T' . $i], $val($g . '_a' . $i));
                            $put(['Compression' . $g . $i], $R[$g]['vals'][$i - 1] ?? null);
                        }
                    }
                    $put(['Compression_Rate', 'Compression_RateC'], $c['result'] ?: null);
                    break;
                case 'RFX':
                    foreach ([1, 2] as $i) {
                        $put(['Li' . $i], $val('V_i' . $i));
                        $put(['Lf' . $i], $val('V_f' . $i));
                        $put(['RossFlex_' . $i], $R['V']['vals'][$i - 1] ?? null);
                    }
                    $put(['RossFlex_Rate', 'RossFlex_RateC'], $rate('V'));
                    break;
                case 'DIN':
                    $put(['DIN_SG'], $val('SG1'));
                    $put(['DIN_SA'], $val('SA1'));
                    foreach ([1 => '1A', 2 => '2B', 3 => '3C'] as $i => $n) {
                        $put(['DIN_W' . $n . '_before'], $val('V_b' . $i));
                        $put(['DIN_W' . $n . '_after'], $val('V_a' . $i));
                        $put(['DIN_' . $i], $R['V']['vals'][$i - 1] ?? null);
                    }
                    $put(['DIN_Rate', 'DIN_RateC'], $rate('V'));
                    break;
            }
        }
        // ---- the report as a whole
        if ($dates) {
            sort($dates);
            $put(['Tested Date', 'TestedDate'], $dates[0]);
            $put(['Finished Date', 'FinishedDate'], $all ? end($dates) : null);
        }
        if ($techs) {
            $put(['Technician'], implode('/', array_values(array_unique($techs))));
        }
        $put(['Status'], $all && $tests ? 'Complete' : ($any ? 'In Progress' : 'Draft'));

        return ['slug' => $slug, 'table' => $table, 'type' => $res['type'], 'fields' => $f, 'unmapped' => array_values(array_unique($unm)), 'mother' => $mother];
    }

    /**
     * Creates the report of the material (or fills the one that exists: only the boxes the sheet has values for are written, nothing else is touched).
     * @return array{ok:bool,message:string,slug?:string,id?:int,created?:bool,unmapped?:list<string>}
     */
    public static function push(string $labRef, string $by = ''): array
    {
        $b = self::build($labRef);
        if (! $b) {
            return ['ok' => false, 'message' => 'This material type has no Material Test Report form.'];
        }
        $db    = \Config\Database::connect();
        $table = $b['table'];
        $slug  = $b['slug'];
        $q     = static fn (string $n): string => '`' . str_replace('`', '``', $n) . '`';
        $row   = $db->table($table)->select('ID')->where('LabRefNo', $labRef)->orderBy('ID', 'DESC')->get(1)->getRowArray();
        $f     = $b['fields'];
        $now   = date('Y-m-d H:i:s');
        $cols  = array_flip($db->getFieldNames($table));
        foreach (['LastModified' => $now, 'ModifiedBy' => $by] as $c => $v) {
            if (isset($cols[$c])) {
                $f[$c] = $v;
            }
        }
        if ($row) {
            $id = (int) $row['ID'];
            unset($f['LabRefNo']);
            if ($f) {
                $db->query('UPDATE ' . $q($table) . ' SET ' . implode(', ', array_map(static fn ($c) => $q((string) $c) . ' = ?', array_keys($f))) . ' WHERE ID = ?', array_merge(array_values($f), [$id]));
            }
            $created = false;
        } else {
            if (isset($cols['CreatedOn'])) {
                $f['CreatedOn'] = $now;
            }
            $db->query('INSERT INTO ' . $q($table) . ' (' . implode(', ', array_map(static fn ($c) => $q((string) $c), array_keys($f))) . ') VALUES (' . implode(', ', array_fill(0, count($f), '?')) . ')', array_values($f));
            $id      = (int) $db->insertID();
            $created = true;
        }
        $stored = $db->table($table)->where('ID', $id)->get()->getRowArray() ?: [];
        DtrLabrefIndex::upsert($db, $slug, $id, $stored);
        DataCache::bump('material');

        return ['ok' => true, 'message' => ($created ? 'Report created' : 'Report updated') . ' from the Material Testing sheet.', 'slug' => $slug, 'id' => $id, 'created' => $created, 'unmapped' => $b['unmapped']];
    }
}
