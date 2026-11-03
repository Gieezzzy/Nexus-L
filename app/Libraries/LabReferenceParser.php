<?php

namespace App\Libraries;

/**
 * LabReferenceParser
 *
 * Deconstructs and explains standardized NEXUS-L Lab Reference codes:
 *   Example: DTP20261027FAA7 (or with foot suffix DTP20261027FAA7-L / DTP20261027FAA7-R)
 *
 * Anatomy:
 *   - DTP       : Facility Code (DaTian Subic Shoes Inc. Philippines QC Lab)
 *   - 20261027  : Test Date (YYYYMMDD -> 27 Oct 2026)
 *   - FA        : Production Stage (FA = First Article, PP = Pre-Production, PR = Mass Production)
 *   - A7        : Production Line (Line A7 in production assembly)
 *   - -L / -R   : Foot side (Left / Right foot specimen)
 */
class LabReferenceParser
{
    /**
     * @return array{
     *   raw: string,
     *   is_valid: bool,
     *   facility_code: string,
     *   facility_name: string,
     *   date_raw: string,
     *   date_formatted: string,
     *   stage_code: string,
     *   stage_name: string,
     *   line_code: string,
     *   line_name: string,
     *   foot_side: string,
     *   summary: string
     * }
     */
    public static function parse(string $ref): array
    {
        $clean = strtoupper(trim($ref));
        $res = [
            'raw'            => $clean,
            'is_valid'       => false,
            'facility_code'  => 'DTP',
            'facility_name'  => 'DaTian Subic Shoes Inc. (Philippines QC Lab)',
            'date_raw'       => '',
            'date_formatted' => '',
            'stage_code'     => '',
            'stage_name'     => '',
            'line_code'      => '',
            'line_name'      => '',
            'foot_side'      => 'Pair / Whole Shoe',
            'summary'        => '',
        ];

        // 1. Standard pattern: DTP + 8 digits + 2-letter stage + 2-4 char line + optional -L/-R
        if (preg_match('/^(DTP)(\d{4})(\d{2})(\d{2})([A-Z]{2})([A-Z0-9]+)(?:-([LR]))?$/i', $clean, $m)) {
            $res['is_valid']       = true;
            $res['facility_code']  = 'DTP';
            $res['date_raw']       = "{$m[2]}-{$m[3]}-{$m[4]}";
            $res['date_formatted'] = date('d M Y', strtotime($res['date_raw']));
            $res['stage_code']     = strtoupper($m[5]);
            $res['stage_name']     = self::stageName($res['stage_code']);
            $res['line_code']      = strtoupper($m[6]);
            $res['line_name']      = 'Production Line ' . $res['line_code'];
            if (! empty($m[7])) {
                $res['foot_side'] = strtoupper($m[7]) === 'L' ? 'Left Foot' : 'Right Foot';
            }
            $res['summary'] = "Facility: {$res['facility_name']} | Date: {$res['date_formatted']} | Stage: {$res['stage_name']} ({$res['stage_code']}) | Line: {$res['line_name']} ({$res['line_code']}) | Foot: {$res['foot_side']}";
            return $res;
        }

        // 2. Fallback pattern: DTP + 8 digits + alphanumeric tail
        if (preg_match('/^(DTP)(\d{4})(\d{2})(\d{2})([A-Z0-9]+)$/i', $clean, $m)) {
            $res['is_valid']       = true;
            $res['date_raw']       = "{$m[2]}-{$m[3]}-{$m[4]}";
            $res['date_formatted'] = date('d M Y', strtotime($res['date_raw']));
            $tail = strtoupper($m[5]);
            if (strlen($tail) >= 4) {
                $res['stage_code'] = substr($tail, 0, 2);
                $res['stage_name'] = self::stageName($res['stage_code']);
                $res['line_code']  = substr($tail, 2);
                $res['line_name']  = 'Production Line ' . $res['line_code'];
            } else {
                $res['line_code'] = $tail;
                $res['line_name'] = 'Production Line ' . $tail;
            }
            $res['summary'] = "Facility: {$res['facility_name']} | Date: {$res['date_formatted']} | Stage: {$res['stage_name']} | Line: {$res['line_name']}";
            return $res;
        }

        return $res;
    }

    public static function stageName(string $code): string
    {
        return match (strtoupper($code)) {
            'FA'    => 'First Article (FA)',
            'PP'    => 'Pre-Production (PP)',
            'PR'    => 'Mass Production (PR)',
            'CS'    => 'Confirmation Sample (CS)',
            'SM'    => 'Salesman Sample (SM)',
            'DV'    => 'Development (DV)',
            'RT'    => 'Retest Batch (RT)',
            'QC'    => 'Quality Audit Check',
            default => 'Production Stage (' . strtoupper($code) . ')',
        };
    }
}
