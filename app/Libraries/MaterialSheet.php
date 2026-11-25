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
    private static bool $ready = false;

    public static function ensureColumns(): void
    {
        if (self::$ready) {
            return;
        }
        $db = \Config\Database::connect();
        $has = $db->query("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND LOWER(table_name) = 'tbl_dtr_materialtrackertests' AND LOWER(column_name) = 'valuesjson' LIMIT 1")->getRowArray();
        if (! $has) {
            $db->query('ALTER TABLE tbl_dtr_MaterialTrackerTests ADD COLUMN ValuesJson MEDIUMTEXT NULL');
        }
        self::$ready = true;
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

    // ------------------------------------------------------------------------------------------------------------------ option lists (word classifications)
    /** @return list<array{0:string,1:string}> [label, class]: P = PASS, T = TOLERABLE, F = FAIL */
    private static function words(string $set): array
    {
        $three = static fn (string $a, string $b, string $c) => [[$a, 'P'], [$b, 'T'], [$c, 'F']];
        switch ($set) {
            case 'abr':        // abrasion of ink (Martindale of sockliner, crocking abrasion)
                return $three('No abrasion of ink', 'Slight abrasion of ink', 'Severe abrasion of ink');
            case 'del':        // delamination of ink (ink adhesion)
                return $three('No delamination of ink', 'Slight delamination of ink', 'Severe delamination of ink');
            case 'mart':       // Martindale of fabric / leather
                return $three('No damage or No abrasion of ink', 'Slight damage or Slight abrasion of ink', 'Severe damage or Severe abrasion of ink');
            case 'taber':
                return $three('No damage of coating', 'Slight damage of coating', 'Severe damage of coating');
            case 'dmg_ink':    // heat aging / hydrolysis of webbing, label, fabric
                return $three('No damage or No delamination of ink', 'Slight damage or Slight delamination of ink', 'Severe damage or Severe delamination of ink');
            case 'dmg_coat':   // leather
                return array_merge($three('No damage or No delamination of coating', 'Slight damage or Slight delamination of coating', 'Severe damage or Severe delamination of coating'),
                    $three('No damage or No delamination of ink', 'Slight damage or Slight delamination of ink', 'Severe damage or Severe delamination of ink'));
            case 'dmg_plain':  // aglet
                return $three('No damage or No delamination', 'Slight damage or Slight delamination', 'Severe damage or Severe delamination');
            case 'pu':         // sockliner / fabric hydrolysis
                return [["Physical appearance of PU didn't hydrolyze", 'P'], ['Physical appearance of PU hydrolyzed', 'F']];
            case 'pu2':        // rubber / foxing
                return [["Physical appearance of PU didn't hydrolyze", 'P'], ['No change on physical appearance', 'P'], ['Physical appearance of PU hydrolyzed', 'F'], ['Change on physical appearance', 'F']];
            case 'hw_hydro':
                return [['No peeling off coating and no deformation of material', 'P'], ['Peeling off coating and deformation of material observed', 'F']];
            case 'label_hydro':
                return [['No delamination', 'P'], ['No peeling observed', 'P'], ['No ink bleeding', 'P'], ['Partial peeling observed', 'T'], ['Slight delamination', 'T'], ['Partial ink bleeding observed', 'T'],
                    ['Severe peeling', 'F'], ['Severe delamination', 'F'], ['Severe ink bleeding observed', 'F']];
            case 'bloom':
                return [['No Blooming', 'P'], ['Blooming observed', 'F']];
            case 'corr':
                return $three('No Corrosion', 'Slight Corrosion', 'Severe Corrosion');
            case 'card':
                return [['Lamination tore (not peeled off.)', 'P'], ['Lamination partially peeled.', 'T'], ['Lamination peeled off.', 'F'], ['Lamination delaminated.', 'F']];
            case 'lace':
                return [['No Visible Wear', 'P'], ['Slight dulling or matting of top surface with no pilling, or fraying', 'T'], ['Up to 50% wear through top surface with visible pilling, or fraying', 'T'],
                    ['50% to 100% wear through top surface', 'F'], ['Lace broken in two', 'F']];
        }

        return [];
    }

    /** the words of heat aging / hydrolysis in each category's Access form */
    private static function agingWords(string $kind, string $cat): array
    {
        if ($kind === 'HYD') {
            return match ($cat) {
                'leather' => self::words('dmg_coat'), 'shoelace', 'thread', 'webbing', 'elastic-gore', 'velcro' => self::words('dmg_ink'), 'label' => self::words('label_hydro'),
                'eyelet-grommet' => self::words('hw_hydro'), 'foxing-heel-logo', 'rubber-outsole' => self::words('pu2'), default => self::words('pu'),
            };
        }

        return match ($cat) {
            'leather' => self::words('dmg_coat'), 'shoelace', 'thread', 'webbing', 'elastic-gore', 'velcro', 'label' => self::words('dmg_ink'), 'eyelet-grommet', 'rubber-outsole', 'insole' => [],
            default => self::words('del'),
        };
    }

    // ------------------------------------------------------------------------------------------------------------------ the rows of a test
    /**
     * @return list<array<string,mixed>> the rows of the test (as on the Access form): k key, label, mode (avg | each | fibre | text | mart | numtext | calc | plain), n samples,
     * unit, std keys, dir (min: reach the standard, max: stay under it), texts (word classifications), manual (rating chosen by the technician)
     */
    /** the most samples a test item can have after "+" on the sheet */
    public const MAX_SAMPLES = 10;

    /** what the person changed on the sheet of ONE test: ['hide' => [row keys], 'n' => [row key => number of samples]]; null / empty = the default layout */
    public static function cleanOverride($o): array
    {
        $out = ['hide' => [], 'n' => []];
        if (! is_array($o)) {
            return $out;
        }
        foreach ((array) ($o['hide'] ?? []) as $k) {
            if (is_string($k) && preg_match('/^[A-Za-z0-9_]{1,30}$/', $k)) {
                $out['hide'][] = $k;
            }
        }
        foreach ((array) ($o['n'] ?? []) as $k => $n) {
            if (is_string($k) && preg_match('/^[A-Za-z0-9_]{1,30}$/', $k) && (int) $n >= 1) {
                $out['n'][$k] = min(self::MAX_SAMPLES, (int) $n);
            }
        }
        $out['hide'] = array_values(array_unique($out['hide']));

        return $out;
    }

    /**
     * The rows of a test as set up for this Lab Ref: the default rows (rows() below, from the Access forms) less the items that were removed (a test item that does not
     * apply, e.g. only Dry and not Wet) and with the number of samples the person chose (the default is 3, it can be 2 or 5). Averages and ratings follow this layout.
     */
    public static function rows(string $code, string $type, string $cat, ?array $override = null): array
    {
        $rows = self::baseRows($code, $type, $cat);
        $o    = self::cleanOverride($override);
        if (! $o['hide'] && ! $o['n']) {
            return $rows;
        }
        $hide = array_flip($o['hide']);
        $out  = [];
        foreach ($rows as $r) {
            if (isset($hide[$r['k']]) && count($rows) > 1) {
                continue;
            }
            $n = $o['n'][$r['k']] ?? null;
            if ($n) {
                if (in_array($r['mode'], ['avg', 'each', 'plain'], true)) {
                    $r['n'] = $n;
                } elseif ($r['mode'] === 'calc' && ! empty($r['specs'])) {
                    $specs = $r['specs'];
                    while (count($specs) < $n) {
                        $specs[] = $specs[0];
                    }
                    $r['specs'] = array_slice($specs, 0, $n);
                }
            }
            $out[] = $r;
        }

        return $out ?: $rows;
    }

    /** the removed rows of a test: [{k, label}] */
    public static function removedRows(string $code, string $type, string $cat, ?array $override): array
    {
        $o = self::cleanOverride($override);
        if (! $o['hide']) {
            return [];
        }
        $out = [];
        foreach (self::baseRows($code, $type, $cat) as $r) {
            if (in_array($r['k'], $o['hide'], true)) {
                $out[] = ['k' => $r['k'], 'label' => $r['label'] !== '' ? $r['label'] : $r['k']];
            }
        }

        return $out;
    }

    private static function baseRows(string $code, string $type, string $cat): array
    {
        $thickType = in_array(strtoupper($type), self::THICK_TYPES, true);
        $thick     = $thickType && in_array($code, self::THICK_CODES, true);
        $dirs      = self::hasKey($type, '/(_L|L_S)$/i');   // a type that has length / width standards
        $linear    = in_array($cat, ['shoelace', 'thread', 'webbing', 'elastic-gore', 'velcro'], true);
        $avg = static fn (string $k, string $label, int $n, array $std, string $unit, array $o = []) => array_merge(
            ['k' => $k, 'label' => $label, 'mode' => 'avg', 'n' => $n, 'std' => $std, 'thick' => false, 'dir' => 'min', 'dec' => 2, 'unit' => $unit], $o);
        $each = static fn (string $k, string $label, int $n, array $std, string $unit, array $o = []) => array_merge(
            ['k' => $k, 'label' => $label, 'mode' => 'each', 'n' => $n, 'std' => $std, 'thick' => false, 'dir' => 'min', 'dec' => 1, 'unit' => $unit], $o);
        $text = static fn (string $k, string $label, array $texts, array $o = []) => array_merge(['k' => $k, 'label' => $label, 'mode' => 'text', 'std' => [], 'texts' => $texts, 'unit' => 'words'], $o);
        // L / W or a single direction
        $dirRows = static function (string $base, array $stdKeys, string $unit, bool $th, int $n = 3, int $dec = 2) use ($avg, $dirs): array {
            if ($dirs) {
                return [$avg('L', 'Length (L)', $n, [$base . '_L', $base . 'L_S', $base . '_S'], $unit, ['thick' => $th, 'dec' => $dec]), $avg('W', 'Width (W)', $n, [$base . '_W', $base . 'W_S', $base . '_S'], $unit, ['thick' => $th, 'dec' => $dec])];
            }

            return [$avg('V', '', $n, $stdKeys, $unit, ['thick' => $th, 'dec' => $dec])];
        };
        $tensileUnit = in_array($cat, ['fabric', 'sockliner-foam', 'rubber-outsole'], true) ? 'kgf/cm2' : 'kgf/cm';
        switch ($code) {
            case 'GAU':
                return [$avg('T', '', 6, [], 'mm', ['minmax' => true, 'manual' => true])];
            case 'WGT':
                // the Weight calculator tools of Access: fabric / leather / insole = weight of a 10 x 10 cm piece x 100 (g/m2); webbing / lace / thread / gore / velcro = weight / length x 100
                if (! $linear) {
                    return [['k' => 'V', 'label' => '', 'mode' => 'calc', 'formula' => 'wgt', 'specs' => self::specs(3, [['r', 'Weight of 10 x 10 cm (g)']]), 'out' => 'g/m2', 'unit' => 'g/m2', 'dec' => 2, 'std' => [], 'manual' => true]];
                }

                return [['k' => 'V', 'label' => '', 'mode' => 'calc', 'formula' => 'wgtlen', 'specs' => self::specs(3, [['r', 'Weight (g)'], ['l', 'Length (cm)']]), 'out' => 'g per 100 cm', 'unit' => 'g/100 cm', 'dec' => 4, 'std' => [], 'manual' => true]];
            case 'YRN':
                return [$avg('WARP', 'WARP', 2, [], '# of yarns', ['autopass' => true]), $avg('WELF', 'WELF', 2, [], '# of yarns', ['autopass' => true])];
            case 'TEN':
                return $dirRows('Tensile', ['Tensile_Std', 'Tensile_S', 'Tensile'], $tensileUnit, $thick);
            case 'ELO':
                return $dirRows('Elongation', ['Elongation_Std', 'Elongation_S', 'Elongation'], '%', $thick);
            case 'TRT':
                return $dirRows('TrouserTear', ['TrouserTear_Std', 'TrouserTear_S'], $cat === 'fabric' ? 'kgf' : 'kgf/cm', $thick);
            case 'STT':
                return $dirRows('StitchTear', ['StitchTear_Std', 'StitchTear_S', 'StitchTear'], 'kgf/cm', $thick);
            case 'BAU':
                return $dirRows('BaumannTear', ['BaumannTear_S'], 'kgf', false);
            case 'TAA':
                return [$avg('V', '', 3, ['TensileAA_S', 'TensileAA'], 'kgf/cm')];
            case 'MUL':
                return [$avg('V', '', 3, ['MullenBurst'], 'kgf/cm2', ['thick' => $thick, 'dec' => 4])];
            case 'MBI':
                return [$avg('V', 'Inside', 3, ['MullenBurstIn_Std'], 'kgf/cm2')];
            case 'MBO':
                return [$avg('V', 'Outside', 3, ['MullenBurstO_Std'], 'kgf/cm2')];
            case 'EDG':
                return [$avg('V', '', 3, ['EdgeCrushTest_Std'], 'kgf/cm2')];
            case 'HRD':
                return [$avg('V', '', 6, ['Hardness_Std'], 'Shore C', ['dec' => 1, 'minmax' => true, 'manual' => true])];
            case 'SPG':
                return [['k' => 'V', 'label' => '', 'mode' => 'calc', 'formula' => 'sg', 'specs' => self::specs(3, [['w', 'Weight (g)'], ['t', 'Thickness (mm)']]), 'out' => 'g/cm3', 'unit' => 'g/cm3', 'dec' => 3, 'std' => ['SG_Std'], 'manual' => true]];
            case 'DIC':
                return [$avg('V', '', 3, ['DieC_Std'], $cat === 'foxing-heel-logo' ? 'kgf/cm' : ($cat === 'sockliner-foam' ? '%' : 'kgf/cm'))];
            case 'SPT':
                return [$avg('V', '', 3, ['SplitTear_Std'], 'kgf/cm')];
            case 'CMP':
                // Compression Calculator: % = (thickness before - after) / (thickness before - spacer) x 100, four specimens at each condition
                $sp = self::specs(4, [['b', 'Before (mm)'], ['a', 'After (mm)']]);

                return [['k' => 'SP', 'label' => 'Spacer thickness', 'mode' => 'plain', 'n' => 1, 'unit' => 'mm'],
                    ['k' => 'AB', 'label' => '6 hrs, 45 C', 'mode' => 'calc', 'formula' => 'comp', 'specs' => $sp, 'out' => '%', 'unit' => '%', 'dec' => 1, 'std' => ['Compression_Std', 'CompressionF_Std'], 'dir' => 'max'],
                    ['k' => 'CD', 'label' => '22 hrs, 23 C', 'mode' => 'calc', 'formula' => 'comp', 'specs' => $sp, 'out' => '%', 'unit' => '%', 'dec' => 1, 'std' => ['Compression_Std', 'CompressionF_Std'], 'dir' => 'max']];
            case 'SHR': case 'SHA': case 'SHH':
                $std = ['Shrinkage_Std', 'Shrinkage_S'];
                $def = $cat === 'rubber-outsole' ? 2.0 : null;
                $sh  = ['mode' => 'calc', 'formula' => 'shrink', 'specs' => self::specs(1, [['b', 'Length before'], ['a', 'Length after']]), 'out' => '%', 'unit' => '%', 'dec' => 2, 'std' => $std, 'dir' => 'max', 'def' => $def];

                return [array_merge(['k' => 'L', 'label' => 'Length (L)'], $sh), array_merge(['k' => 'W', 'label' => 'Width (W)'], $sh)];
            case 'RFX':
                return [['k' => 'V', 'label' => '', 'mode' => 'calc', 'formula' => 'ross', 'specs' => self::specs(2, [['i', 'Initial length'], ['f', 'Final length']]), 'out' => '% growth', 'unit' => '% growth', 'dec' => 2, 'std' => ['RossFlex_Std'], 'dir' => 'max', 'def' => 50.0]];
            case 'DIN':
                return [['k' => 'SG', 'label' => 'Specific gravity', 'mode' => 'plain', 'n' => 1, 'unit' => 'g/cm3'], ['k' => 'SA', 'label' => 'Abrasion distance (SA)', 'mode' => 'plain', 'n' => 1, 'unit' => ''],
                    ['k' => 'V', 'label' => '', 'mode' => 'calc', 'formula' => 'din', 'specs' => self::specs(3, [['b', 'Before (g)'], ['a', 'After (g)']]), 'out' => 'mm3', 'unit' => 'mm3', 'dec' => 2, 'std' => ['DIN_Std'], 'dir' => 'max', 'def' => 150.0]];
            case 'KNT':
                return [$avg('V', '', 6, ['KnotSlip_S'], 'kgf')];
            case 'TIP':
                return [$avg('V', '', 6, ['LaceTipRet_S'], 'kgf')];
            case 'PLS':
                return [$avg('V', '', 4, ['PeelStrength_S'], 'kgf/cm')];
            case 'SHS':
                return [$avg('V', '', 4, ['ShearStrength_S'], 'kgf/cm2')];
            case 'LAB':
                // six lace samples: cycles reached + what the lace looks like (the Access rating: below the standard / broken or worn through = FAIL, dulling or up to 50 % wear = TOLERABLE)
                $o = [];
                for ($i = 1; $i <= 6; $i++) {
                    $o[] = ['k' => 'S' . $i, 'label' => 'Sample ' . $i, 'mode' => 'mart', 'std' => ['LaceAbrasion_S'], 'texts' => self::words('lace'), 'unit' => 'cycles + words', 'optional' => $i > 4];   // the Access rating reads samples 1 to 4; 5 and 6 count when they are filled
                }

                return $o;
            case 'LAM':
                return [$avg('DRY', 'Dry', 5, ['LaminarPeelDry_Std', 'LaminarPeelFabric_Std'], 'kgf/cm', ['minmax' => true, 'manual' => true]), $avg('WET', 'Wet', 5, ['LaminarPeelWet_Std'], 'kgf/cm', ['minmax' => true, 'manual' => true])];
            case 'CAD':
                return [$avg('L', 'Length (L)', 3, ['CardboardAdhesionDryL_Std'], 'kgf/cm2', ['manual' => true]), $avg('W', 'Width (W)', 3, ['CardboardAdhesionDryW_Std'], 'kgf/cm2', ['manual' => true])];
            case 'CAW':
                return [$text('L', 'Length (L)', self::words('card')), $text('W', 'Width (W)', self::words('card'))];
            case 'MAR':
                $mw = in_array($cat, ['fabric', 'leather'], true) ? self::words('mart') : self::words('abr');
                if (self::hasKey($type, '/^MARTINDALE[DW][12]_S$/')) {
                    return [['k' => 'D1', 'label' => 'Dry: sample 1', 'mode' => 'mart', 'std' => ['MartindaleD1_S'], 'texts' => $mw, 'unit' => 'cycles + words'], ['k' => 'D2', 'label' => 'Dry: sample 2', 'mode' => 'mart', 'std' => ['MartindaleD2_S'], 'texts' => $mw, 'unit' => 'cycles + words'],
                        ['k' => 'W1', 'label' => 'Wet: sample 1', 'mode' => 'mart', 'std' => ['MartindaleW1_S'], 'texts' => $mw, 'unit' => 'cycles + words'], ['k' => 'W2', 'label' => 'Wet: sample 2', 'mode' => 'mart', 'std' => ['MartindaleW2_S'], 'texts' => $mw, 'unit' => 'cycles + words']];
                }

                return [['k' => 'D', 'label' => 'Dry', 'mode' => 'mart', 'std' => ['Martindale_S', 'MartindaleDry_Std'], 'texts' => $mw, 'unit' => 'cycles + words'], ['k' => 'W', 'label' => 'Wet', 'mode' => 'mart', 'std' => ['Martindale_S', 'MartindaleWet_Std'], 'texts' => $mw, 'unit' => 'cycles + words']];
            case 'TAB':
                return [['k' => 'T1', 'label' => 'Sample 1', 'mode' => 'mart', 'std' => ['Taber_S'], 'texts' => self::words('taber'), 'unit' => 'cycles + words'], ['k' => 'T2', 'label' => 'Sample 2', 'mode' => 'mart', 'std' => ['Taber_S'], 'texts' => self::words('taber'), 'unit' => 'cycles + words']];
            case 'BAL':
                $o = [];
                foreach (['DryL' => 'Dry · Length (L)', 'DryW' => 'Dry · Width (W)', 'WetL' => 'Wet · Length (L)', 'WetW' => 'Wet · Width (W)', 'AHL' => 'After hydrolysis · Length (L)', 'AHW' => 'After hydrolysis · Width (W)'] as $k => $l) {
                    $o[] = $each($k, $l, 2, ['Bally' . $k . '_S'], 'cycles', ['dec' => 0]);
                }

                return $o;
            case 'PVC':
                return [$each('V', '', 2, ['PVC_S', 'PVCMigration'], 'AATCC rating')];
            case 'CTP':
                return [['k' => 'TOP', 'label' => 'Top side', 'mode' => 'fibre', 'std' => ['CTP', 'CTP_S', 'CTP_Std', 'Perspiration_Std'], 'unit' => 'AATCC rating'], ['k' => 'BACK', 'label' => 'Back side', 'mode' => 'fibre', 'std' => ['CtPB_S', 'CTP', 'CTP_S', 'CTP_Std', 'Perspiration_Std'], 'unit' => 'AATCC rating']];
            case 'PER':
                return [['k' => 'V', 'label' => '', 'mode' => 'fibre', 'std' => ['Perspiration_Std', 'CTP_Std'], 'unit' => 'AATCC rating']];
            case 'CTW':
                if ($cat === 'eyelet-grommet') {
                    return [['k' => 'TOP', 'label' => 'Top side', 'mode' => 'fibre', 'std' => ['CTW_S', 'CTW', 'Water_S'], 'unit' => 'AATCC rating']];
                }

                return [['k' => 'TOP', 'label' => 'Top side', 'mode' => 'fibre', 'std' => ['CTW', 'CTW_S', 'CTW_Std', 'Water_S'], 'unit' => 'AATCC rating'], ['k' => 'BACK', 'label' => 'Back side', 'mode' => 'fibre', 'std' => ['CtWB_S', 'CTW', 'CTW_S', 'CTW_Std', 'Water_S'], 'unit' => 'AATCC rating']];
            case 'UVA':
                return [$each('V', '', 2, ['UV_S', 'UV_Std', 'UV'], 'AATCC rating')];
            case 'AGU':
                return [$each('V', '', 2, ['UV_std_AGLET'], 'AATCC rating')];
            case 'HAG': case 'AGH':
                // grey-scale rating OR what the sample looks like: either one gives the rating of the sample
                $w = $code === 'AGH' ? self::words('dmg_plain') : self::agingWords('HAG', $cat);
                $s = $code === 'AGH' ? ['HeatAging_std_AGLET'] : ['HeatAging_S', 'HeatAging_Std', 'HeatAging'];

                return [['k' => 'S1', 'label' => 'Sample 1', 'mode' => 'numtext', 'std' => $s, 'texts' => $w, 'unit' => 'AATCC rating or words'], ['k' => 'S2', 'label' => 'Sample 2', 'mode' => 'numtext', 'std' => $s, 'texts' => $w, 'unit' => 'AATCC rating or words']];
            case 'HYD': case 'AGY':
                $w = $code === 'AGY' ? self::words('dmg_plain') : self::agingWords('HYD', $cat);
                $s = $code === 'AGY' ? ['Hydro_std_AGLET'] : ['Hydrolysis_S', 'Hydrolysis_Std', 'Hydrolysistxt_S', 'Hydrolysis'];

                return [['k' => 'S1', 'label' => 'Sample 1', 'mode' => 'numtext', 'std' => $s, 'texts' => $w, 'unit' => 'rating or words'], ['k' => 'S2', 'label' => 'Sample 2', 'mode' => 'numtext', 'std' => $s, 'texts' => $w, 'unit' => 'rating or words']];
            case 'CRK':
                return [$each('DRY', 'Dry', 1, ['CtCDry_S', 'CrockingDry_Std', 'CrockingDry', 'CTC_Dry_std', 'CTCDry_std'], 'AATCC rating'), $each('WET', 'Wet', 1, ['CtCWet_S', 'CrockingWet_Std', 'CrockingWet', 'CTC_Wet_std'], 'AATCC rating')];
            case 'ACK':
                $r = [$text('DRY', 'Dry', self::words('abr')), $text('WET', 'Wet', self::words('abr'))];
                if (in_array($cat, ['fabric', 'leather', 'foxing-heel-logo'], true)) {
                    $r[] = $text('ASW', 'Artificial sea water', self::words('abr'));
                }

                return $r;
            case 'INK':
                return [$text('DRY', 'Dry', self::words('del')), $text('WET', 'Wet', self::words('del')), $text('ASW', 'Artificial sea water', self::words('del'))];
            case 'BLM':
                return [$text('V', '', self::words('bloom'))];
            case 'COR':
                return [$text('S1', 'Sample 1', self::words('corr')), $text('S2', 'Sample 2', self::words('corr'))];
            default:
                return [$avg('V', '', 3, [], '', ['manual' => true])];
        }
    }

    /** n specimens, each with the same fields: [[field, label], ...] -> [['b' => 'Before', 'a' => 'After'], ...] */
    private static function specs(int $n, array $fields): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = array_column($fields, 1, 0);      // field => label
        }

        return $out;
    }

    /** the Access calculator tools: one specimen -> its result, null while a box is empty or a divisor is zero */
    private static function formula(string $kind, array $f, array $all, int $dec): ?float
    {
        $v = array_values($f);
        $has = static fn (...$x) => count(array_filter($x, static fn ($y) => $y !== null)) === count($x);
        switch ($kind) {
            case 'shrink':
                return $has($f['b'] ?? null, $f['a'] ?? null) && $f['b'] != 0 ? round(($f['b'] - $f['a']) / $f['b'] * 100, $dec) : null;
            case 'comp':
                $s = self::num($all['SP1'] ?? null);

                return $has($f['b'] ?? null, $f['a'] ?? null, $s) && $s != 0 && ($f['b'] - $s) != 0 ? round(($f['b'] - $f['a']) / ($f['b'] - $s) * 100, $dec) : null;
            case 'din':
                $sg = self::num($all['SG1'] ?? null);
                $sa = self::num($all['SA1'] ?? null);

                return $has($f['b'] ?? null, $f['a'] ?? null, $sg, $sa) && $sg != 0 && $sa != 0 ? round((($f['b'] - $f['a']) / $sg) * (200 / $sa) * 1000, $dec) : null;
            case 'ross':
                return $has($f['i'] ?? null, $f['f'] ?? null) && $f['i'] != 0 && $f['f'] != 0 ? round(($f['f'] - $f['i']) / $f['i'] * 100, $dec) : null;
            case 'sg':
                return $has($f['w'] ?? null, $f['t'] ?? null) && $f['t'] != 0 ? round($f['w'] / ((10 * 10) * ($f['t'] / 10)), $dec) : null;
            case 'wgt':
                return $has($f['r'] ?? null) ? round($f['r'] / 100 * 10000, $dec) : null;
            case 'wgtlen':
                return $has($f['r'] ?? null, $f['l'] ?? null) && $f['l'] != 0 ? round($f['r'] / $f['l'] * 100, $dec) : null;
        }

        return null;
    }

    // ------------------------------------------------------------------------------------------------------------------ compute
    /** PASS / TOLERABLE / FAIL of the chosen words ('' when nothing or something unknown is chosen) */
    private static function wordClass(array $texts, string $t): string
    {
        foreach ($texts as [$label, $pf]) {
            if ($t !== '' && strcasecmp($label, $t) === 0) {
                return $pf === 'P' ? 'PASS' : ($pf === 'T' ? 'TOLERABLE' : 'FAIL');
            }
        }

        return '';
    }

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
        foreach (self::rows($code, $type, $cat, is_array($values['_layout'] ?? null) ? $values['_layout'] : null) as $r) {
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
                case 'plain':
                    $has = false;
                    for ($i = 1; $i <= $r['n']; $i++) {
                        $has = $has || self::num($values[$k . $i] ?? null) !== null;
                    }
                    $any = $any || $has;
                    $row['norate'] = true;
                    break;
                case 'calc':
                    $vals = [];
                    $full = true;
                    foreach ($r['specs'] as $i => $sp) {
                        $f = [];
                        foreach (array_keys($sp) as $fn) {
                            $f[$fn] = self::num($values[$k . '_' . $fn . ($i + 1)] ?? null);
                            $any = $any || $f[$fn] !== null;
                        }
                        $v = self::formula($r['formula'], $f, $values, (int) $r['dec']);
                        $vals[] = $v;
                        $full = $full && $v !== null;
                    }
                    $row['vals'] = $vals;
                    $got = array_values(array_filter($vals, static fn ($x) => $x !== null));
                    if ($got) {
                        $row['ave'] = round(array_sum($got) / count($got), (int) $r['dec']);
                        $row['min'] = min($got);
                        $row['max'] = max($got);
                    }
                    if ($std === null && isset($r['def'])) {
                        $std = $r['def'];
                        $row['std'] = $std;
                    }
                    if ($std !== null) {
                        $hi = ($r['dir'] ?? 'min') === 'max';
                        if ($got && $hi && max($got) > $std) {
                            $rating = 'FAIL';
                        } elseif ($full && $got) {
                            $rating = $hi ? 'PASS' : (min($got) < $std ? 'FAIL' : 'PASS');
                        }
                    } elseif (! empty($r['manual'])) {
                        $row['manual'] = true;
                        $rating = in_array($manual, ['PASS', 'TOLERABLE', 'FAIL'], true) && $full ? $manual : '';
                    }
                    break;
                case 'text':
                    $t = trim((string) ($values[$k] ?? ''));
                    $any = $any || $t !== '';
                    $rating = self::wordClass($r['texts'], $t);
                    break;
                case 'mart':
                    // cycles reached + what the sample looks like: below the standard or a severe word = FAIL; "Slight" = TOLERABLE; "No ..." = PASS; both boxes are needed
                    $c = self::num($values[$k . 'c'] ?? null);
                    $t = trim((string) ($values[$k . 't'] ?? ''));
                    $any = $any || $c !== null || $t !== '';
                    $wc = self::wordClass($r['texts'], $t);
                    $row['empty'] = $c === null && $t === '';
                    if ($c === null && $t === '') {
                        $rating = '';
                    } elseif (($c !== null && $std !== null && $c < $std) || $wc === 'FAIL') {
                        $rating = 'FAIL';
                    } elseif ($c === null || $t === '') {
                        $rating = '';
                    } else {
                        $rating = $wc === 'TOLERABLE' ? 'TOLERABLE' : 'PASS';
                    }
                    break;
                case 'numtext':
                    // a rating number OR words: either one rates the sample; with both the worse one counts
                    $c = self::num($values[$k . 'c'] ?? null);
                    $t = trim((string) ($values[$k . 't'] ?? ''));
                    $any = $any || $c !== null || $t !== '';
                    $cls = [];
                    if ($c !== null && $std !== null) {
                        $cls[] = $c < $std ? 'FAIL' : 'PASS';
                    }
                    if ($t !== '') {
                        $cls[] = self::wordClass($r['texts'], $t);
                    }
                    $cls = array_filter($cls);
                    $rating = in_array('FAIL', $cls, true) ? 'FAIL' : (in_array('TOLERABLE', $cls, true) ? 'TOLERABLE' : ($cls ? 'PASS' : ''));
                    break;
            }
            $row['rating'] = $rating;
            $out[$k] = $row;
            if (! empty($row['norate']) || (! empty($r['optional']) && ! empty($row['empty']))) {
                continue;      // a box the other rows use (spacer, specific gravity ...), not rated itself
            }
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
    public static function layout(string $code, string $type, string $cat, ?array $override = null): array
    {
        $rows = self::rows($code, $type, $cat, $override);
        foreach ($rows as &$r) {
            if ($r['mode'] === 'fibre') {
                $r['fibres'] = self::FIBRES;
            }
            $dir = ($r['dir'] ?? 'min') === 'max' ? 'must stay at or under' : 'must reach';
            switch ($r['mode']) {
                case 'avg':
                    $r['hint'] = ! empty($r['autopass']) ? $r['n'] . ' samples: enter the number of yarns' : (! empty($r['manual']) && empty($r['std'])
                        ? $r['n'] . ' samples (numbers); no standard: choose the rating' : $r['n'] . ' samples (numbers): the average ' . $dir . ' the standard');
                    break;
                case 'each':
                    $r['hint'] = $r['n'] . ($r['n'] === 1 ? ' sample' : ' samples') . ' (' . ($r['unit'] === 'cycles' ? 'cycles' : 'rating number') . '): ' . ($r['n'] === 1 ? 'it' : 'each one') . ' ' . $dir . ' the standard';
                    break;
                case 'fibre':
                    $r['hint'] = '6 fibres, rating 1 to 5 each: all must reach the standard';
                    break;
                case 'text':
                    $r['hint'] = 'words: choose what is seen. No ... = PASS · Slight / Partial = TOLERABLE · Severe = FAIL';
                    break;
                case 'mart':
                    $r['hint'] = 'cycles AND words: below the standard cycles or Severe = FAIL · Slight = TOLERABLE · No ... = PASS';
                    break;
                case 'numtext':
                    $r['hint'] = 'a rating number OR words (either rates the sample): No ... = PASS · Slight = TOLERABLE · Severe = FAIL';
                    break;
                case 'calc':
                    $r['hint'] = count($r['specs']) . ' sample' . (count($r['specs']) === 1 ? '' : 's') . ': enter the measurements, the result is worked out';
                    break;
                default:
                    $r['hint'] = '';
            }
        }
        unset($r);

        return $rows;
    }
}
