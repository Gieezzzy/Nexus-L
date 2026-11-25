<?php

namespace App\Services\Dtr;

/** Material Lab Ref numbering (DTP... base, code and child numbers). (moved out of DtrController; bodies unchanged). */
class DtrMaterialLabRef
{
    /** DTP + date + material code: a single word gives its first 3 letters (FAB, LEA), several words their initials (NW, LF) */
    public function nextMaterialLabRefBase(string $material, string $date): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', trim($material), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) === 1) {
            $code = strtoupper(substr($words[0], 0, 3));
        } else {
            $code = strtoupper(implode('', array_map(static fn ($w) => $w[0], array_slice($words, 0, 3))));
        }

        return 'DTP' . str_replace('-', '', $date) . ($code !== '' ? $code : 'MT');
    }

    /**
     * The Lab Reference code of a material (list of the lab): Fabric Upper FBU, Fabric Lining FBL, Leather Upper LEU / Lining LEL, Synthetic Upper SYU / Lining SYL,
     * Shoe Lace LC, Thread TH, Eyelet EY, Grommets GR, Latex Foam FML, PU Foam FM, Velcro Hook VH / Loop VL, Size Label SL, Woven Label WL, Nylon Webbing NW,
     * Inner Box IB, Outer Box OB, Rubber Outsole RB, Foxing F1, Heel Logo Rubber HLR, Heel Logo molded HL, Accessories AC. '' when the type is not in that list.
     */
    public function materialLabRefCode(string $type, string $name): string
    {
        $t = strtoupper(trim($type));
        $n = strtoupper($name);
        $lining = str_contains($t, 'LINING') || str_contains($n, 'LINING');
        $map = [
            ['FABRIC', $lining ? 'FBL' : 'FBU'], ['CANVAS', $lining ? 'FBL' : 'FBU'], ['OSNABURG', 'FBL'], ['LEATHER', $lining ? 'LEL' : 'LEU'],
            ['SYNTHETIC', $lining ? 'SYL' : 'SYU'], ['LACE', 'LC'], ['THREAD', 'TH'], ['GROMMET', str_contains($t, 'EYELET') ? 'EY' : 'GR'], ['EYELET', 'EY'],
            ['LATEX FOAM', 'FML'], ['FOAM', 'FM'], ['HOOK', str_contains($n, 'LOOP') && ! str_contains($n, 'HOOK') ? 'VL' : 'VH'], ['VELCRO', str_contains($n, 'LOOP') && ! str_contains($n, 'HOOK') ? 'VL' : 'VH'],
            ['SIZE LABEL', 'SL'], ['WOVEN LABEL', 'WL'], ['NYLON WEBBING', 'NW'], ['INNER BOX', 'IB'], ['OUTER BOX', 'OB'], ['HEEL LOGO RUBBER', 'HLR'],
            ['HEEL LOGO', 'HL'], ['OUTSOLE', 'RB'], ['RUBBER', 'RB'], ['FOXING', 'F1'], ['ACCESSOR', 'AC'],
        ];
        foreach ($map as [$k, $c]) {
            if (str_contains($t, $k)) {
                return $c;
            }
        }

        return '';
    }

    /**
     * DTP + date + material code - first sample no. [- last sample no.] , e.g. DTP20261104FBU-1-100 (samples 1 to 100); one sample is only DTP20261104FBU-1.
     * Samples go on from the last one of the same code and date (LC-1-7 is followed by LC-8-9).
     */
    public function nextMaterialLabRef($db, string $type, string $date, string $name = '', int $samples = 1): string
    {
        $code = $this->materialLabRefCode($type, $name);
        $base = $code !== '' ? 'DTP' . str_replace('-', '', $date) . $code : $this->nextMaterialLabRefBase($type, $date);
        $n    = 0;
        $sql  = 'SELECT CONVERT(LabReference USING utf8mb4) COLLATE utf8mb4_general_ci AS r FROM tbl_dtr_material_receipts WHERE LabReference LIKE ?'
            . ' UNION SELECT CONVERT(lab_ref_no USING utf8mb4) COLLATE utf8mb4_general_ci FROM tbl_dtr_unified_labrefs WHERE lab_ref_no LIKE ?'
            . ' UNION SELECT CONVERT(lab_ref_no USING utf8mb4) COLLATE utf8mb4_general_ci FROM tbl_dtr_material_bench_log WHERE lab_ref_no LIKE ?';
        try {
            $found = $db->query($sql, [$base . '-%', $base . '-%', $base . '-%'])->getResultArray();
        } catch (\Throwable $e) {
            $found = $db->query(str_replace(' UNION SELECT CONVERT(lab_ref_no USING utf8mb4) COLLATE utf8mb4_general_ci FROM tbl_dtr_material_bench_log WHERE lab_ref_no LIKE ?', '', $sql), [$base . '-%', $base . '-%'])->getResultArray();
        }
        foreach ($found as $x) {
            if (preg_match('/^' . preg_quote($base, '/') . '-(\d+)(?:-(\d+))?/', (string) $x['r'], $m)) {
                $n = max($n, (int) ($m[2] ?? $m[1]));
            }
        }
        $first = $n + 1;
        $last  = $first + max(1, $samples) - 1;

        return $base . '-' . $first . ($last > $first ? '-' . $last : '');
    }

    /** highest sample no. already used with a child Lab Ref base (DTP20261118LC-7 -> 7; an older range LC-1-8 counts as 8), across every table that holds Lab Refs */
    public function lastMaterialChildNo($db, string $base): int
    {
        $n   = 0;
        $col = 'COLLATE utf8mb4_general_ci';
        $src = [['tbl_dtr_MaterialTracker', 'LabRefNo'], ['tbl_dtr_MaterialTrackerTests', 'ChildLabRefNo'], ['tbl_dtr_material_receipts', 'LabReference'], ['tbl_dtr_unified_labrefs', 'lab_ref_no'], ['tbl_dtr_material_bench_log', 'lab_ref_no']];
        foreach ($src as [$t, $c]) {
            try {
                foreach ($db->query("SELECT CONVERT({$c} USING utf8mb4) {$col} AS r FROM {$t} WHERE {$c} LIKE ?", [$base . '-%'])->getResultArray() as $x) {
                    if (preg_match('/^' . preg_quote($base, '/') . '-(\d+)(?:-(\d+))?/', (string) $x['r'], $m)) {
                        $n = max($n, (int) ($m[2] ?? $m[1]));
                    }
                }
            } catch (\Throwable $e) {
                // table not there: skipped
            }
        }

        return $n;
    }
}
