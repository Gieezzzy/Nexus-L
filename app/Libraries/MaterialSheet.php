<?php

namespace App\Libraries;

/**
 * The test sheet of a received material: what the lab fills in on the forms of the Access material test system ("Form - Fabric" ...), test by test:
 * the specimen boxes, the Max / Min / Average, the Standard of the material type (table Standards = tbl_dtr_standards; for fabric and leather the standard of
 * a tensile / tear / burst test depends on the thickness average of the Gauge test) and the Rating (PASS / FAIL / TOLERABLE) by the rules of the Access VBA:
 *  - average tests: the average of the specimens is compared with the standard (>= standard PASS, else FAIL; shrinkage, compression, DIN abrasion and Ross flex are "<=");
 *  - each tests (UV, Heat Aging, Bally, PVC, Taber, colorfastness): any specimen below the standard = FAIL, all present = PASS, incomplete = no rating;
 *  - fibre tests (perspiration / water): the six fibres (Wool, Acrylic, Polyester, Nylon, Cotton, Acetate) must all reach the standard;
 *  - text tests (abrasion / delamination of ink, hydrolysis): "No ..." and "Slight ..." = PASS, "Severe ..." / "hydrolyzed" = FAIL;
 *  - Martindale: cycles below the standard or severe damage = FAIL;
 *  - Gauge, Weight, Hardness ... have no standard ("Refer to specific requirements"): the rating is chosen by the technician.
 * Values are stored as JSON on the child test (tbl_dtr_MaterialTrackerTests.ValuesJson); averages and ratings are always recomputed here.
 */
class MaterialSheet
{
    private const FIBRES = ['wool' => 'Wool', 'acrylic' => 'Acrylic', 'polyester' => 'Polyester', 'nylon' => 'Nylon', 'cotton' => 'Cotton', 'acetate' => 'Acetate'];

    /** tests whose standard depends on the thickness (Gauge average) of the fabric / leather material types */
    private const THICK_TYPES = ['FABRIC LINING', 'FABRIC LINING - LIGHT', 'FABRIC LINING - NEON', 'FABRIC UPPER', 'FABRIC UPPER - LIGHT', 'LEATHER LINING', 'LEATHER UPPER'];
    private const THICK_CODES = ['TEN', 'ELO', 'TRT', 'STT', 'MUL'];

    private static array $stdCache = [];

    public static function ensureColumns(): void
    {
        $db = \Config\Database::connect();
        $has = $db->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND LOWER(table_name) = 'tbl_dtr_materialtrackertests' AND LOWER(column_name) = 'valuesjson' LIMIT 1")->getRowArray();
        if (! $has) {
            $db->query('ALTER TABLE tbl_dtr_MaterialTrackerTests ADD COLUMN ValuesJson MEDIUMTEXT NULL');
        }
    }

    // ------------------------------------------------------------------------------------------------------------------ standards
    /** @return array<string,list<array{v:?float,min:?float,max:?float}>> upper-case key -> rows of the material type */
    private static function standards(string $type): array
    {
        $k = strtoupper($type);
        if (! isset(self::$stdCache[$k])) {
            $out   = [];
            $owner = [];
            foreach (explode('|', $k) as $one) {      // a combined type (LACE = every shoe lace type): a key comes from the first type that has it
                foreach (\Config\Database::connect()->query('SELECT TestMethod, StandardValue, TMinC, TMaxC FROM tbl_dtr_standards WHERE UPPER(MaterialType) = ? ORDER BY ID', [$one])->getResultArray() as $r) {
                    $key = strtoupper(trim((string) $r['TestMethod']));
                    if (isset($owner[$key]) && $owner[$key] !== $one) {
                        continue;
                    }
                    $owner[$key] = $one;
                    $out[$key][] = ['v' => is_numeric($r['StandardValue']) ? (float) $r['StandardValue'] : null,
                        'min' => is_numeric($r['TMinC']) ? (float) $r['TMinC'] : null, 'max' => is_numeric($r['TMaxC']) ? (float) $r['TMaxC'] : null];
                }
            }
            self::$stdCache[$k] = $out;
        }

        return self::$stdCache[$k];
    }

    private static function hasKey(string $type, string $regex): bool
    {
        foreach (array_keys(self::standards($type)) as $key) {
            if (preg_match($regex, $key)) {
                return true;
            }
        }

        return false;
    }

    /** the standard value of the first key of $keys the type has; $thick: choose the row whose thickness band holds the Gauge average */
    private static function std(string $type, array $keys, bool $thick, ?float $gauge): ?float
    {
        $all = self::standards($type);
        foreach ($keys as $key) {
            $rows = $all[strtoupper($key)] ?? null;
            if (! $rows) {
                continue;
            }
            if ($thick) {
                if ($gauge === null) {
                    return null;
                }
                foreach ($rows as $r) {
                    if ($r['min'] !== null && $r['max'] !== null && $gauge >= $r['min'] && $gauge <= $r['max'] && $r['v'] !== null) {
                        return $r['v'];
                    }
                }

                return null;
            }
            foreach ($rows as $r) {
                if ($r['v'] !== null) {
                    return $r['v'];
                }
            }
        }

        return null;
    }

    // ------------------------------------------------------------------------------------------------------------------ the rows of a test
    /** @return list<array<string,mixed>> the rows of the test: k key, label, mode (avg|each|fibre|text|mart|manual), n, std keys, thick, dir, dec, opts */
    public static function rows(string $code, string $type, string $cat): array
    {
        $thickType = in_array(strtoupper($type), self::THICK_TYPES, true);
        $thick     = $thickType && in_array($code, self::THICK_CODES, true);
        $ld        = static fn (string $base, array $alt = []) => array_merge([$base . '_L', $base . '_S', $base . '_Std', $base], $alt);
        $dirs      = self::hasKey($type, '/(_L|L_S)$/i');   // a type that has length / width standards
        $both      = static function (string $name, int $n, array $stdL, array $stdW, bool $th, int $dec = 2): array {
            return [
                ['k' => 'L', 'label' => $name === '' ? 'L' : 'L =', 'mode' => 'avg', 'n' => $n, 'std' => $stdL, 'thick' => $th, 'dir' => 'min', 'dec' => $dec],
                ['k' => 'W', 'label' => $name === '' ? 'W' : 'W =', 'mode' => 'avg', 'n' => $n, 'std' => $stdW, 'thick' => $th, 'dir' => 'min', 'dec' => $dec],
            ];
        };
        $one = static fn (int $n, array $std, bool $th = false, string $dir = 'min', int $dec = 2) => [['k' => 'V', 'label' => '', 'mode' => 'avg', 'n' => $n, 'std' => $std, 'thick' => $th, 'dir' => $dir, 'dec' => $dec]];
        $mulTx = static fn (string $w) => [['No ' . $w, 'P'], ['Slight ' . $w, 'P'], ['Severe ' . $w, 'F']];
        switch ($code) {
            case 'GAU':
                return [['k' => 'T', 'label' => '', 'mode' => 'avg', 'n' => 6, 'std' => [], 'thick' => false, 'dir' => 'min', 'dec' => 2, 'minmax' => true, 'manual' => true]];
            case 'WGT':
                return [['k' => 'V', 'label' => '', 'mode' => 'avg', 'n' => 3, 'std' => [], 'thick' => false, 'dir' => 'min', 'dec' => 2, 'manual' => true]];
            case 'YRN':
                return [['k' => 'WARP', 'label' => 'WARP:', 'mode' => 'avg', 'n' => 2, 'std' => [], 'thick' => false, 'dir' => 'min', 'dec' => 2, 'autopass' => true], ['k' => 'WELF', 'label' => 'WELF:', 'mode' => 'avg', 'n' => 2, 'std' => [], 'thick' => false, 'dir' => 'min', 'dec' => 2, 'autopass' => true]];
            case 'TEN': case 'ELO': case 'TRT': case 'STT': case 'BAU':
                $base = ['TEN' => 'Tensile', 'ELO' => 'Elongation', 'TRT' => 'TrouserTear', 'STT' => 'StitchTear', 'BAU' => 'BaumannTear'][$code];
                if ($dirs) {
                    return $both('L/W', 3, [$base . '_L', $base . 'L_S', $base . '_S'], [$base . '_W', $base . 'W_S', $base . '_S'], $thick);
                }

                return $one(3, [$base . '_Std', $base . '_S', $base], $thick);
            case 'TAA':
                return $one(3, ['TensileAA_S', 'TensileAA']);
            case 'MUL':
                return $one(3, ['MullenBurst'], $thick, 'min', 4);
            case 'MBI':
                return $one(3, ['MullenBurstIn_Std']);
            case 'MBO':
                return $one(3, ['MullenBurstO_Std']);
            case 'EDG':
                return $one(3, ['EdgeCrushTest_Std']);
            case 'HRD':
                return [['k' => 'V', 'label' => '', 'mode' => 'avg', 'n' => 6, 'std' => ['Hardness_Std'], 'thick' => false, 'dir' => 'min', 'dec' => 1, 'minmax' => true, 'manual' => true]];
            case 'SPG':
                return [['k' => 'V', 'label' => '', 'mode' => 'avg', 'n' => 3, 'std' => ['SG_Std'], 'thick' => false, 'dir' => 'min', 'dec' => 3, 'manual' => true]];
            case 'DIC':
                return $one(3, ['DieC_Std']);
            case 'SPT':
                return $one(3, ['SplitTear_Std']);
            case 'CMP':
                return $one(4, ['Compression_Std'], false, 'max');
            case 'SHR': case 'SHA': case 'SHH':
                return [['k' => 'L', 'label' => 'L (%)', 'mode' => 'avg', 'n' => 1, 'std' => ['Shrinkage_Std', 'Shrinkage_S'], 'thick' => false, 'dir' => 'max', 'dec' => 2], ['k' => 'W', 'label' => 'W (%)', 'mode' => 'avg', 'n' => 1, 'std' => ['Shrinkage_Std', 'Shrinkage_S'], 'thick' => false, 'dir' => 'max', 'dec' => 2]];
            case 'RFX':
                return $one(3, ['RossFlex_Std'], false, 'max');
            case 'DIN':
                return $one(3, ['DIN_Std'], false, 'max');
            case 'KNT':
                return $one(3, ['KnotSlip_S']);
            case 'TIP':
                return $one(3, ['LaceTipRet_S']);
            case 'LAB':
                return $one(3, ['LaceAbrasion_S']);
            case 'PLS':
                return $one(3, ['PeelStrength_S']);
            case 'SHS':
                return $one(3, ['ShearStrength_S']);
            case 'LAM':
                return [['k' => 'DRY', 'label' => 'Dry', 'mode' => 'avg', 'n' => 5, 'std' => ['LaminarPeelDry_Std', 'LaminarPeelFabric_Std'], 'thick' => false, 'dir' => 'min', 'dec' => 2, 'minmax' => true, 'manual' => true],
                    ['k' => 'WET', 'label' => 'Wet', 'mode' => 'avg', 'n' => 5, 'std' => ['LaminarPeelWet_Std'], 'thick' => false, 'dir' => 'min', 'dec' => 2, 'minmax' => true, 'manual' => true]];
            case 'CAD':
                return [['k' => 'L', 'label' => 'L=', 'mode' => 'avg', 'n' => 3, 'std' => ['CardboardAdhesionDryL_Std'], 'thick' => false, 'dir' => 'min', 'dec' => 2, 'manual' => true], ['k' => 'W', 'label' => 'W=', 'mode' => 'avg', 'n' => 3, 'std' => ['CardboardAdhesionDryW_Std'], 'thick' => false, 'dir' => 'min', 'dec' => 2, 'manual' => true]];
            case 'CAW':
                return [['k' => 'L', 'label' => 'L=', 'mode' => 'avg', 'n' => 3, 'std' => ['CardboardAdhesionWetL_Std'], 'thick' => false, 'dir' => 'min', 'dec' => 2, 'manual' => true], ['k' => 'W', 'label' => 'W=', 'mode' => 'avg', 'n' => 3, 'std' => ['CardboardAdhesionWetW_Std'], 'thick' => false, 'dir' => 'min', 'dec' => 2, 'manual' => true]];
            case 'MAR':
                if (self::hasKey($type, '/^MARTINDALE[DW][12]_S$/')) {
                    return [['k' => 'D1', 'label' => 'Dry: 1.', 'mode' => 'mart', 'std' => ['MartindaleD1_S']], ['k' => 'D2', 'label' => 'Dry: 2.', 'mode' => 'mart', 'std' => ['MartindaleD2_S']],
                        ['k' => 'W1', 'label' => 'Wet: 1.', 'mode' => 'mart', 'std' => ['MartindaleW1_S']], ['k' => 'W2', 'label' => 'Wet: 2.', 'mode' => 'mart', 'std' => ['MartindaleW2_S']]];
                }

                return [['k' => 'D', 'label' => 'Dry', 'mode' => 'mart', 'std' => ['Martindale_S', 'MartindaleDry_Std']], ['k' => 'W', 'label' => 'Wet', 'mode' => 'mart', 'std' => ['Martindale_S', 'MartindaleWet_Std']]];
            case 'TAB':
                return [['k' => 'T1', 'label' => '1.', 'mode' => 'mart', 'std' => ['Taber_S'], 'texts' => $mulTx('damage of coating')], ['k' => 'T2', 'label' => '2.', 'mode' => 'mart', 'std' => ['Taber_S'], 'texts' => $mulTx('damage of coating')]];
            case 'BAL':
                $o = [];
                foreach (['DryL' => 'Dry: L', 'DryW' => 'Dry: W', 'WetL' => 'Wet: L', 'WetW' => 'Wet: W', 'AHL' => 'After Hydrolysis: L', 'AHW' => 'After Hydrolysis: W'] as $k => $l) {
                    $o[] = ['k' => $k, 'label' => $l, 'mode' => 'each', 'n' => 2, 'std' => ['Bally' . $k . '_S'], 'thick' => false, 'dir' => 'min', 'dec' => 0];
                }

                return $o;
            case 'PVC':
                return [['k' => 'V', 'label' => '', 'mode' => 'each', 'n' => 2, 'std' => ['PVC_S', 'PVCMigration'], 'thick' => false, 'dir' => 'min', 'dec' => 1]];
            case 'CTP':
                return [['k' => 'TOP', 'label' => 'Top Side', 'mode' => 'fibre', 'std' => ['CTP', 'CTP_S', 'CTP_Std', 'Perspiration_Std'], 'thick' => false], ['k' => 'BACK', 'label' => 'Back Side', 'mode' => 'fibre', 'std' => ['CtPB_S', 'CTP', 'CTP_S', 'CTP_Std', 'Perspiration_Std'], 'thick' => false]];
            case 'PER':
                return [['k' => 'V', 'label' => '', 'mode' => 'fibre', 'std' => ['Perspiration_Std', 'CTP_Std'], 'thick' => false]];
            case 'CTW':
                return [['k' => 'TOP', 'label' => 'Top Side', 'mode' => 'fibre', 'std' => ['CTW', 'CTW_S', 'CTW_Std', 'Water_S'], 'thick' => false], ['k' => 'BACK', 'label' => 'Back Side', 'mode' => 'fibre', 'std' => ['CtWB_S', 'CTW', 'CTW_S', 'CTW_Std', 'Water_S'], 'thick' => false]];
            case 'UVA':
                return [['k' => 'V', 'label' => '', 'mode' => 'each', 'n' => 2, 'std' => ['UV_S', 'UV_Std', 'UV'], 'thick' => false, 'dir' => 'min', 'dec' => 1]];
            case 'AGU':
                return [['k' => 'V', 'label' => '', 'mode' => 'each', 'n' => 2, 'std' => ['UV_std_AGLET'], 'thick' => false, 'dir' => 'min', 'dec' => 1]];
            case 'HAG':
                $r = [['k' => 'V', 'label' => '', 'mode' => 'each', 'n' => 2, 'std' => ['HeatAging_S', 'HeatAging_Std', 'HeatAging'], 'thick' => false, 'dir' => 'min', 'dec' => 1]];
                if (in_array($cat, ['fabric', 'leather', 'sockliner-foam'], true) && self::hasKey($type, '/^HEATAGINGTXT/')) {
                    $r[] = ['k' => 'TXT', 'label' => 'Appearance', 'mode' => 'text', 'std' => ['HeatAgingtxt_S'], 'texts' => $mulTx('delamination of ink')];
                }

                return $r;
            case 'AGH':
                return [['k' => 'V', 'label' => '', 'mode' => 'each', 'n' => 2, 'std' => ['HeatAging_std_AGLET'], 'thick' => false, 'dir' => 'min', 'dec' => 1]];
            case 'HYD': case 'AGY':
                $s = $code === 'AGY' ? ['Hydro_std_AGLET'] : ['Hydrolysis_S', 'Hydrolysis_Std', 'Hydrolysistxt_S', 'Hydrolysis'];

                return [['k' => 'TXT', 'label' => 'Appearance', 'mode' => 'text', 'std' => $s, 'texts' => [["Physical appearance of PU didn't hydrolyze", 'P'], ['Physical appearance of PU hydrolyzed', 'F']]]];
            case 'CRK':
                return [['k' => 'DRY', 'label' => 'Dry=', 'mode' => 'each', 'n' => 1, 'std' => ['CtCDry_S', 'CrockingDry_Std', 'CrockingDry', 'CTC_Dry_std', 'CTCDry_std'], 'thick' => false, 'dir' => 'min', 'dec' => 1],
                    ['k' => 'WET', 'label' => 'Wet=', 'mode' => 'each', 'n' => 1, 'std' => ['CtCWet_S', 'CrockingWet_Std', 'CrockingWet', 'CTC_Wet_std'], 'thick' => false, 'dir' => 'min', 'dec' => 1]];
            case 'ACK':
                $r = [['k' => 'DRY', 'label' => 'Dry=', 'mode' => 'text', 'std' => [], 'texts' => $mulTx('abrasion of ink')], ['k' => 'WET', 'label' => 'Wet=', 'mode' => 'text', 'std' => [], 'texts' => $mulTx('abrasion of ink')]];
                if (in_array($cat, ['fabric', 'leather', 'foxing-heel-logo'], true)) {
                    $r[] = ['k' => 'ASW', 'label' => 'Sea Water=', 'mode' => 'text', 'std' => [], 'texts' => $mulTx('abrasion of ink')];
                }

                return $r;
            case 'INK':
                $r = [['k' => 'DRY', 'label' => 'Dry=', 'mode' => 'text', 'std' => [], 'texts' => $mulTx('delamination of ink')], ['k' => 'WET', 'label' => 'Wet=', 'mode' => 'text', 'std' => [], 'texts' => $mulTx('delamination of ink')]];
                $r[] = ['k' => 'ASW', 'label' => 'Sea Water=', 'mode' => 'text', 'std' => [], 'texts' => $mulTx('delamination of ink')];

                return $r;
            case 'BLM':
                return [['k' => 'TXT', 'label' => 'Appearance', 'mode' => 'text', 'std' => [], 'texts' => [['No Blooming', 'P'], ['Blooming', 'F']]]];
            case 'COR':
                return [['k' => 'TXT', 'label' => 'Appearance', 'mode' => 'text', 'std' => [], 'texts' => [['No Corrosion', 'P'], ['Corrosion', 'F']]]];
            default:
                return [['k' => 'V', 'label' => '', 'mode' => 'avg', 'n' => 3, 'std' => [], 'thick' => false, 'dir' => 'min', 'dec' => 2, 'manual' => true]];
        }
    }

    // ------------------------------------------------------------------------------------------------------------------ compute
    private static function num($v): ?float
    {
        return is_numeric($v) && trim((string) $v) !== '' ? (float) $v : null;
    }

    /**
     * Averages, standards and ratings of one test from its input values.
     * @param array<string,mixed> $values inputs by field: <k>1..<n> (numbers), <k> (text), <k>c / <k>t (Martindale / Taber), <k>_<fibre>, <k>R (manual rating)
     * @return array{rows:array<string,array<string,mixed>>,result:string,complete:bool,any:bool}
     */
    public static function compute(string $code, string $type, string $cat, array $values, ?float $gauge): array
    {
        $out = [];
        $any = false;
        $allRated = true;
        $anyFail = false;
        $anyTol = false;
        $nRows = 0;
        foreach (self::rows($code, $type, $cat) as $r) {
            $k   = $r['k'];
            $std = self::std($type, $r['std'] ?? [], (bool) ($r['thick'] ?? false), $gauge);
            $row = ['std' => $std, 'ave' => null, 'min' => null, 'max' => null, 'rating' => '', 'manual' => false];
            $rating = '';
            $manual = trim((string) ($values[$k . 'R'] ?? ''));
            switch ($r['mode']) {
                case 'avg':
                    $vals = [];
                    for ($i = 1; $i <= $r['n']; $i++) {
                        $v = self::num($values[$k . $i] ?? null);
                        if ($v !== null) {
                            $vals[] = $v;
                        }
                    }
                    $any = $any || $vals;
                    if ($vals) {
                        $row['ave'] = round(array_sum($vals) / count($vals), (int) $r['dec']);
                        $row['min'] = min($vals);
                        $row['max'] = max($vals);
                    }
                    $full = count($vals) === (int) $r['n'];
                    if (! empty($r['autopass'])) {
                        $rating = $row['ave'] !== null ? 'PASS' : '';
                    } elseif ($std !== null) {
                        $rating = $full ? (($r['dir'] === 'max' ? $row['ave'] <= $std : $row['ave'] >= $std) ? 'PASS' : 'FAIL') : '';
                    } elseif (! empty($r['manual'])) {
                        $row['manual'] = true;
                        $rating = in_array($manual, ['PASS', 'TOLERABLE', 'FAIL'], true) ? $manual : '';
                    }
                    break;
                case 'each':
                case 'fibre':
                    $names = $r['mode'] === 'fibre' ? array_keys(self::FIBRES) : range(1, (int) $r['n']);
                    $vals = [];
                    $miss = false;
                    foreach ($names as $nm) {
                        $v = self::num($values[$r['mode'] === 'fibre' ? $k . '_' . $nm : $k . $nm] ?? null);
                        if ($v === null || $v == 0.0) {
                            $miss = true;
                        } else {
                            $vals[] = $v;
                        }
                    }
                    $any = $any || $vals;
                    if ($vals) {
                        $row['min'] = min($vals);
                        $row['max'] = max($vals);
                    }
                    if ($std !== null && ! $miss) {
                        $rating = min($vals) < $std ? 'FAIL' : 'PASS';
                    } elseif ($std !== null && $vals && min($vals) < $std) {
                        $rating = 'FAIL';
                    }
                    break;
                case 'text':
                    $t = trim((string) ($values[$k] ?? ''));
                    $any = $any || $t !== '';
                    foreach ($r['texts'] as [$label, $pf]) {
                        if (strcasecmp($label, $t) === 0) {
                            $rating = $pf === 'P' ? 'PASS' : 'FAIL';
                        }
                    }
                    break;
                case 'mart':
                    $c = self::num($values[$k . 'c'] ?? null);
                    $t = trim((string) ($values[$k . 't'] ?? ''));
                    $any = $any || $c !== null || $t !== '';
                    $isTaber = isset($r['texts']);
                    $severe = $t !== '' && stripos($t, 'severe') !== false;
                    if ($c === null && $t === '') {
                        $rating = '';
                    } elseif (($c !== null && $std !== null && $c < $std) || $severe) {
                        $rating = 'FAIL';
                    } elseif ($c === null || $t === '') {
                        $rating = '';
                    } else {
                        $rating = 'PASS';
                    }
                    break;
            }
            $row['rating'] = $rating;
            $out[$k] = $row;
            $nRows++;
            if ($rating === '') {
                $allRated = false;
            }
            $anyFail = $anyFail || $rating === 'FAIL';
            $anyTol = $anyTol || $rating === 'TOLERABLE';
        }
        $result = $anyFail ? 'FAIL' : ($allRated && $nRows ? ($anyTol ? 'TOLERABLE' : 'PASS') : '');

        return ['rows' => $out, 'result' => $result, 'complete' => $allRated && $nRows > 0, 'any' => $any];
    }

    /** the Gauge (thickness) average of a material: from the saved Gauge test of its Lab Ref */
    public static function gaugeOf(string $motherLabRef): ?float
    {
        $row = \Config\Database::connect()->query("SELECT ValuesJson FROM tbl_dtr_MaterialTrackerTests WHERE MotherLabRefNo = ? AND TestCode = 'GAU' LIMIT 1", [$motherLabRef])->getRowArray();
        if (! $row || ! $row['ValuesJson']) {
            return null;
        }
        $v = json_decode((string) $row['ValuesJson'], true) ?: [];
        $vals = [];
        for ($i = 1; $i <= 6; $i++) {
            if (self::num($v['T' . $i] ?? null) !== null) {
                $vals[] = (float) $v['T' . $i];
            }
        }

        return $vals ? round(array_sum($vals) / count($vals), 2) : null;
    }

    /** the field layout of a test for the sheet (what the screen draws) */
    public static function layout(string $code, string $type, string $cat): array
    {
        $rows = self::rows($code, $type, $cat);
        foreach ($rows as &$r) {
            if ($r['mode'] === 'fibre') {
                $r['fibres'] = self::FIBRES;
            }
            if ($r['mode'] === 'mart' && ! isset($r['texts'])) {
                $r['texts'] = [['No damage or No abrasion of ink', 'P'], ['Slight damage or Slight abrasion of ink', 'P'], ['Severe damage or Severe abrasion of ink', 'F']];
            }
            unset($r['std']['_']);
        }
        unset($r);

        return $rows;
    }
}
