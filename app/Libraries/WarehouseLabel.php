<?php

namespace App\Libraries;

/** Warehouse boxes: the Box Code and the Date Range are made from the Box Label (the same rules are in warehouse-contents.js for the live form). */
class WarehouseLabel
{
    private const MONTHS = ['JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12];

    /** the Box Code: the label in capitals, words joined with "-" (BONDING SPECIMENS NOV 11-12 2026 -> BONDING-SPECIMENS-NOV-11-12-2026) */
    public static function code(string $label): string
    {
        $c = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $label) ?? ''));
        $c = trim($c, '-');

        return substr($c, 0, 60);
    }

    /**
     * The date written in the label, or null when there is none. A month needs a day (or range of days) or a year next to it: "NOV 11-12 2026",
     * "November 13-14, 2026", "MARCH 2026 (3)". Without a year the current year is used.
     * @return array{range:string,month_year:string}|null
     */
    public static function date(string $label): ?array
    {
        if (! preg_match('/\b(JAN(?:UARY)?|FEB(?:RUARY)?|MAR(?:CH)?|APR(?:IL)?|MAY|JUNE?|JULY?|AUG(?:UST)?|SEP(?:T(?:EMBER)?)?|OCT(?:OBER)?|NOV(?:EMBER)?|DEC(?:EMBER)?)\.?(?:\s*(\d{1,2})(?!\d)(?:\s*[-\x{2013}]\s*(\d{1,2})(?!\d))?)?(?:\s*,?\s*(20\d{2}))?/iu', $label, $m)) {
            return null;
        }
        $d1   = $m[2] ?? '';
        $d2   = $m[3] ?? '';
        $year = $m[4] ?? '';
        if ($d1 === '' && $year === '') {
            return null;
        }
        $mon   = strtoupper(substr($m[1], 0, 3));
        $n     = self::MONTHS[$mon] ?? 0;
        $y     = $year !== '' ? $year : date('Y');
        $range = $mon . ($d1 !== '' ? ' ' . $d1 . ($d2 !== '' ? '-' . $d2 : '') : '') . ' ' . $y;

        return ['range' => $range, 'month_year' => sprintf('%04d-%02d', (int) $y, $n)];
    }
}
