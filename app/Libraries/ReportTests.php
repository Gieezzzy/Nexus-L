<?php

namespace App\Libraries;

/**
 * Turns a Digital Test Report record into what the InterLink Upload Form (Tab 2) needs: the Sample, the Test Item Result
 * rows and the Test Item Result For Reading rows. Everything comes from the record of the selected Lab Reference; nothing
 * is made up. Method codes (VF-E04 ...) are the ones printed on the official Access reports.
 *
 * InterLink accepts Pass / Fail / Data for a result: Access FAIL(ED) -> Fail, TOLERABLE -> Data (as for the overall result),
 * everything else rated -> Pass.
 */
class ReportTests
{
    /** lower-case test key (prefix match) => [label, VF method, unit] */
    private const KNOWN = [
        'heataging'      => ['Heat Aging (72 hrs, 70°C)', 'VF-E04 (2022/09)', ''],
        'corrosion'      => ['Corrosion Resistance', 'VF-E09 (2014/12)', ''],
        'hydrolysis'     => ['Hydrolysis (7 days, 60°C, 95% RH)', 'VF-E19 (2024/12)', ''],
        'uv'             => ['UV Light (24 hrs, 50°C)', 'VF-E03 (2019/05)', 'Grade'],
        'ctcdry'         => ['Colorfastness to Crocking (Dry)', 'VF-E02 (2024/08)', 'Grade'],
        'ctcwet'         => ['Colorfastness to Crocking (Wet)', 'VF-E02 (2024/08)', 'Grade'],
        'ctpb'           => ['Colorfastness to Perspiration (Back Side)', 'VF-E16 (2018/11)', 'Grade'],
        'ctp'            => ['Colorfastness to Perspiration (Top Side)', 'VF-E16 (2018/11)', 'Grade'],
        'ctwb'           => ['Colorfastness to Water (Back Side)', 'VF-E01 (2008/05)', 'Grade'],
        'ctw'            => ['Colorfastness to Water (Top Side)', 'VF-E01 (2008/05)', 'Grade'],
        'shrinkage'      => ['Shrinkage (1 hr, 70°C)', 'VF-E13 (2016/03)', '%'],
        'tensileaa'      => ['Tensile (After Aging)', 'VF-B01 (2022/11)', 'kgf'],
        'tensile'        => ['Tensile', 'VF-B01 (2022/11)', 'kgf'],
        'elongation'     => ['Elongation', 'VF-B01 (2022/11)', '%'],
        'trousertear'    => ['Trouser Tear', 'VF-B07 (2022/11)', 'kgf'],
        'stitchtear'     => ['Stitch Tear', 'VF-B08 (2019/05)', 'kgf/cm'],
        'peelstrength'   => ['Peel Strength', 'VF-B03 (2019/05)', 'kgf/cm'],
        'shearstrength'  => ['Shear Strength', 'VF-B03 (2019/05)', 'kgf/cm2'],
        'mullen'         => ['Mullen Burst', 'VF-B02 (2019/05)', 'kgf/cm2'],
        'laceTipRet'     => ['Lace Tip Retention', '', 'kgf'],
        'knotslip'       => ['Knot Slip', '', 'kgf'],
        'laceabrasion'   => ['Lace Abrasion', '', 'cycles'],
        'pvc'            => ['PVC Contact Discoloration', 'VF-E06 (2014/07)', 'Grade'],
        'iaosdry'        => ['Ink Adhesion on Substrate (Dry)', 'VF-F05 (2014/07)', ''],
        'iaoswet'        => ['Ink Adhesion on Substrate (Wet)', 'VF-F05 (2014/07)', ''],
        'abrasioncrocking' => ['Abrasion Crocking (Dry)', '', ''],
    ];

    /** keys of a test that hold something other than readings */
    private const ALIAS = ['corrosion' => ['CorrosionRes'], 'ctcdry' => ['CtCDry'], 'ctcwet' => ['CtCWet'], 'shrinkage12' => ['Shrinkageave12'], 'shrinkage34' => ['Shrinkageave34']];

    public static function result(string $rating): string
    {
        $u = strtoupper(trim($rating));

        return match (true) {
            in_array($u, ['F', 'FAIL', 'FAILED'], true) => 'Fail',
            in_array($u, ['T', 'TOLERABLE'], true)      => 'Data',
            default                                     => 'Pass',
        };
    }

    private static function known(string $lb): array
    {
        $best = null;
        foreach (self::KNOWN as $k => $v) {
            $k = strtolower($k);
            if (str_starts_with($lb, $k) && ($best === null || strlen($k) > strlen($best[0]))) {
                $best = [$k, $v];
            }
        }

        return $best ? $best[1] : ['', '', ''];
    }

    private static function label(string $base): string
    {
        $lb = strtolower($base);
        [$name] = self::known($lb);
        if ($name !== '' && preg_match('/^(tensile|tensileaa|elongation|trousertear|stitchtear)([lw])$/i', $base, $m)) {
            return $name . ' - ' . (strtolower($m[2]) === 'l' ? 'Length (L)' : 'Width (W)');
        }
        if ($name !== '') {
            return preg_match('/^shrinkage(12|34)$/i', $base, $m) ? $name . ' ' . $m[1][0] . '-' . $m[1][1] : $name;
        }

        return trim(preg_replace('/([a-z])([A-Z0-9])/', '$1 $2', $base));
    }

    /**
     * @param array $rec    the record of the report (its own table row)
     * @param array $points bonding only: the row of tbl_dtr_bonding_points
     *
     * @return array{items: list<array>, readings: list<array>, samples: list<array>}
     */
    public static function build(string $slug, array $rec, array $points = []): array
    {
        $items = [];
        $reads = [];
        $g     = static fn (array $k) => (function () use ($rec, $k) {
            foreach ($k as $n) {
                if (isset($rec[$n]) && trim((string) $rec[$n]) !== '') {
                    return trim((string) $rec[$n]);
                }
            }

            return '';
        })();
        $sample = [[
            'sample_id_code'     => 'S001',
            'sample_description' => $g(['Material Description', 'MaterialDescription', 'ModelStyle', 'Model No/Style']),
            'sample_color'       => $g(['Color']),
        ]];

        if ($slug === 'bonding') {
            $std = (float) ($points['STD'] ?? $rec['STD'] ?? 2.5);
            foreach ([['UTF', 'Upper / Foxing Tape - Bond Strength (UTF)', $std, 35], ['FTO', 'Foxing Tape / Outsole - Bond Strength (FTO)', $std, 35], ['FiTO', 'Center IS/OS - Bond Strength (FiTO)', 1.0, 13]] as [$p, $name, $s, $max]) {
                $min = $points[$p . 'Min'] ?? '';
                $mx  = $points[$p . 'Max'] ?? '';
                $res = (string) ($points[$p . '_Result'] ?? '');
                if ($min === '' && $mx === '') {
                    continue;
                }
                $items[] = self::item('VF-F01 (2019/05)', $name, 'VF-F01 (2019/05)', "STD {$s} kgf/cm (tolerable from " . round($s * 0.8, 2) . ')', "Min {$min} / Max {$mx} kgf/cm", $res, $res);
                for ($i = 1; $i <= $max; $i++) {
                    $v = $points[($p === 'FiTO' ? 'FiTO' : $p . 'point') . $i] ?? '';
                    if ($v === '' || $v === null || (float) $v <= 0) {
                        continue;
                    }
                    $r       = (float) $v >= $s ? 'PASS' : ((float) $v >= round($s * 0.8, 2) ? 'TOLERABLE' : 'FAIL');
                    $reads[] = self::reading($name, 'VF-F01 (2019/05)', '', "Spot {$i}", (string) round((float) $v, 2), 'kgf/cm', 'STD ' . $s, $r);
                }
            }

            return ['items' => $items, 'readings' => $reads, 'samples' => $sample];
        }

        $seen = [];
        foreach (array_keys($rec) as $key) {
            if (! preg_match('/^(.+?)(?:_R|_?Rating|_?rating)$/', $key, $m)) {
                continue;
            }
            $rating = trim((string) ($rec[$key] ?? ''));
            $base   = str_ireplace('txt', '', $m[1]);
            $lb     = strtolower($base);
            if ($rating === '' || strlen($base) < 3 || isset($seen[$lb])) {
                continue;
            }
            $prefixes = array_merge([$base], self::ALIAS[$lb] ?? []);
            $isMeta   = static fn (string $k) => (bool) preg_match('/(_S|_std|Std|_Std|_R|Rating|_rating)$/', $k);
            $found    = [];   // key => value
            foreach (array_keys($rec) as $k) {
                if ($isMeta($k)) {
                    continue;
                }
                foreach ($prefixes as $pf) {
                    if (preg_match('/^' . preg_quote($pf, '/') . '(?:txt)?(\d*|_?ave)$/i', $k, $mm)) {
                        $v = trim((string) ($rec[$k] ?? ''));
                        if ($v !== '' && ! preg_match('/^0(\.0+)?$/', $v)) {
                            $found[$k] = [$v, strtolower($mm[1])];
                        }
                        break;
                    }
                }
            }
            if (! $found) {
                continue;
            }
            $seen[$lb] = true;
            $std = '';
            foreach (array_keys($rec) as $k) {
                foreach ($prefixes as $pf) {
                    if (preg_match('/^' . preg_quote($pf, '/') . '(?:txt)?(_S|_std|Std|_Std)$/i', $k) && trim((string) $rec[$k]) !== '') {
                        $std = trim((string) $rec[$k]);
                        break 2;
                    }
                }
            }
            [, $method, $unit] = self::known($lb);
            $name   = self::label($base);
            $values = array_filter($found, static fn ($f) => ! str_contains($f[1], 'ave'));
            $ave    = '';
            foreach ($found as $f) {
                if (str_contains($f[1], 'ave')) {
                    $ave = $f[0];
                }
            }
            $actual = $ave !== '' ? 'Average ' . $ave . ($unit !== '' ? ' ' . $unit : '') : implode(', ', array_map(static fn ($f) => $f[0], $values)) . ($unit !== '' && $values ? ' ' . $unit : '');
            $items[] = self::item($method !== '' ? strtok($method, ' ') : strtoupper($base), $name, $method, $std !== '' ? 'Std ' . $std . ($unit !== '' ? ' ' . $unit : '') : '', mb_substr($actual, 0, 200), $rating, $rating);
            $n = 0;
            foreach ($values as $f) {
                $n++;
                $reads[] = self::reading($name, $method, '', 'Specimen ' . ($f[1] !== '' ? $f[1] : $n), $f[0], $unit, $std, $rating);
            }
            if ($ave !== '') {
                $reads[] = self::reading($name, $method, '', 'Average', $ave, $unit, $std, $rating);
            }
        }

        return ['items' => $items, 'readings' => $reads, 'samples' => $sample];
    }

    private static function item(string $code, string $name, string $method, string $std, string $actual, string $rating, string $raw): array
    {
        $res = self::result($rating);

        return [
            'sample_id_code' => 'S001', 'test_code' => $code, 'test_name' => $name, 'test_method' => $method, 'requirement_spec' => $std,
            'actual_reading' => $actual, 'result_status' => $res, 'raw_rating' => $raw,
            // names the Upload Form grids use
            'test_method_standard' => trim($method . ($method !== '' && $std !== '' ? ' | ' : '') . $std), 'actual_result_summary' => $actual, 'test_result' => $res,
        ];
    }

    private static function reading(string $name, string $method, string $component, string $element, string $value, string $unit, string $std, string $rating): array
    {
        return [
            'sample_id_code' => 'S001', 'test_item_name' => $name, 'test_method' => $method, 'sample_component' => $component, 'test_element' => $element,
            'test_value_indicator' => '=', 'reading_value_raw' => $value, 'unit' => $unit, 'target_spec' => $std, 'reading_status' => self::result($rating),
        ];
    }
}
