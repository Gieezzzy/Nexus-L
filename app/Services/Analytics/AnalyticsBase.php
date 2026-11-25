<?php

namespace App\Services\Analytics;

/**
 * Shared state (date range + bucket) and SQL helpers for the Analytics area services.
 * The controller sets the range through setRange() before each call.
 */
abstract class AnalyticsBase
{
    protected string $group = 'month';
    protected string $from  = '';
    protected string $to    = '';

    public function setRange(string $from, string $to, string $group): static
    {
        [$this->from, $this->to, $this->group] = [$from, $to, $group];

        return $this;
    }

    /** Production dates before this one are read from dbexcelbackup.tbl_weekly_bonding_result (the Nexus records are complete from this date on). */
    public const BACKUP_UNTIL = '2026-11-14';
    public const SHUTDOWN_ROW ="(COALESCE(LabRefNo, '') LIKE '%SHUTDOWN%' OR COALESCE(POLotNO, '') = 'SHUTDOWN' OR COALESCE(ModelStyle, '') = 'SHUTDOWN')";
    public const BOND_DEDUP   = "(LabRefNo IS NULL OR LabRefNo = '' OR BondingTestID IN (SELECT keep_id FROM (SELECT COALESCE(MAX(CASE WHEN Result IS NOT NULL AND Result <> '' THEN BondingTestID END), MAX(BondingTestID)) AS keep_id FROM tbl_dtr_bonding_info WHERE LabRefNo IS NOT NULL AND LabRefNo <> '' GROUP BY LabRefNo) k))";
    public const BOND_DATE    = "COALESCE((CASE WHEN ProductionDate REGEXP '^[0-9]{2}-[0-9]{2}-20[0-9]{2}' THEN CONCAT(SUBSTR(ProductionDate, 7, 4), '-', SUBSTR(ProductionDate, 1, 2), '-', SUBSTR(ProductionDate, 4, 2)) WHEN ProductionDate REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(ProductionDate, 10) WHEN LabRefNo REGEXP '^DTP20[0-9]{6}' THEN CONCAT(SUBSTR(LabRefNo, 4, 4), '-', SUBSTR(LabRefNo, 8, 2), '-', SUBSTR(LabRefNo, 10, 2)) END), LEFT(NULLIF(TestedDate, ''), 10), LEFT(NULLIF(ReceivedDate, ''), 10))";

    public const UNIFIED_DATE = "COALESCE(
            CASE WHEN tested_date REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(tested_date, 10) END,
            DATE_FORMAT(STR_TO_DATE(tested_date, '%d %b %Y'), '%Y-%m-%d'),
            CASE WHEN received_date REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}' THEN LEFT(received_date, 10) END,
            DATE_FORMAT(STR_TO_DATE(received_date, '%d %b %Y'), '%Y-%m-%d'),
            CASE WHEN lab_ref_no REGEXP '^DTP20[0-9]{6}' THEN CONCAT(SUBSTR(lab_ref_no, 4, 4), '-', SUBSTR(lab_ref_no, 8, 2), '-', SUBSTR(lab_ref_no, 10, 2)) END
    )";

    public function bondingBaseWhere(string $dateCol = self::BOND_DATE): string
    {
        $w = 'NOT (' . self::SHUTDOWN_ROW . ") AND COALESCE(TestType, '') <> 'LACKING' AND " . self::BOND_DEDUP;
        // Shoe Bonding counts only the reports that the unified Lab Ref index (tbl_dtr_unified_labrefs) lists as a bonding test (form_slug = 'bonding');
        // a pull test or any other test of the index is not a bonding test, so it never shows here
        $w .= " AND (LabRefNo IS NULL OR LabRefNo = '' OR EXISTS (SELECT 1 FROM tbl_dtr_unified_labrefs ul WHERE ul.form_slug = 'bonding' AND ul.lab_ref_no = LabRefNo) OR NOT EXISTS (SELECT 1 FROM tbl_dtr_unified_labrefs ux WHERE ux.lab_ref_no = LabRefNo))";   // a Lab Ref the index does not list at all is still a bonding report (refs the index gives to another test stay out)
        if ($this->from !== '' || $this->to !== '') {
            $w .= ' AND ' . $this->dateWhere($dateCol);
        }

        return $w;
    }

    /** a failure cause as the monitoring sheet names it (OVER-DRIED CEMENT -> OVERDRIED CEMENT, POOR PRIMMER -> POOR PRIMER ...) */
    public function failCauseName(string $c): string
    {
        $c = strtoupper(trim((string) preg_replace('/^\([A-Z]\)\s*/', '', trim($c))));
        $c = trim((string) preg_replace('/\s+/', ' ', str_replace(['OVER-DRIED', 'OVER DRIED', 'PRIMMER', 'MATERAIL', 'CEMENTNG'], ['OVERDRIED', 'OVERDRIED', 'PRIMER', 'MATERIAL', 'CEMENTING'], $c)));

        return $c === 'OVERDRIED' ? 'OVERDRIED CEMENT' : $c;
    }

    /** a failed bonding row -> [portion letter (U upper-foxing, F foxing-outsole, I filler-outsole, '' unknown) => [cause names]] */
    public function failParse(array $r): array
    {
        $portionOf = static function (string $t): string {
            $u = strtoupper($t);

            return str_contains($u, 'UPPER') ? 'U' : (str_contains($u, 'FOXING TO') ? 'F' : (str_contains($u, 'FILLER') ? 'I' : ''));
        };
        $splitC = static fn (string $s): array => array_values(array_filter(array_map('trim', preg_split('/\s*[\/;,]+\s*/', $s) ?: []), static fn ($x) => $x !== '' && $x !== '-'));
        $src = trim((string) $r['ProbableCause']);
        if ($src === '' && trim((string) $r['FailPart']) !== '') {
            $src = trim((string) $r['FailPart']) . ' - ' . trim((string) $r['Cause']);
        }
        if ($src === '') {
            $src = trim((string) ($r['PossibleCause'] ?: $r['ModeOfFailure']));
        }
        $out = [];
        foreach (preg_split('/\s*\|\s*/', $src) as $seg) {
            if (str_contains($seg, ':')) {
                [$pt, $cs] = array_map('trim', explode(':', $seg, 2));
                $ps = [$portionOf($pt)];
            } elseif (str_contains($seg, ' - ')) {
                [$pt, $cs] = array_map('trim', explode(' - ', $seg, 2));
                $ps = array_map($portionOf, preg_split('/\s*[;,]\s*/', $pt));
            } else {
                $cs = $seg;
                $ps = [''];
            }
            foreach (array_unique($ps) as $p) {
                foreach ($splitC((string) $cs) as $c) {
                    $cn = $this->failCauseName($c);
                    if ($cn !== '' && ! in_array($cn, ['UPPER TO FOXING', 'FOXING TO OUTSOLE', 'FILLER TO OUTSOLE'], true)) {
                        $out[$p][$cn] = true;
                    }
                }
            }
        }

        return array_map('array_keys', $out);
    }

    public function pct(int $n, int $d): float
    {
        return $d > 0 ? round($n / $d * 100, 1) : 0.0;
    }

    /** SQL condition: the column holds a real date and falls inside the chosen range. */
    public function dateWhere(string $col): string
    {
        $w = "{$col} REGEXP '^20[0-9]{2}-[0-9]{2}-[0-9]{2}'";
        if ($this->from !== '') {
            $w .= " AND LEFT({$col}, 10) >= '{$this->from}'";
        }
        if ($this->to !== '') {
            $w .= " AND LEFT({$col}, 10) <= '{$this->to}'";
        }

        return $w;
    }

    /** SQL expression of the chart bucket (day / week / month / year) of a date column. */
    public function bucket(string $col): string
    {
        return match ($this->group) {
            'day'   => "LEFT({$col}, 10)",
            'week'  => "DATE_FORMAT(STR_TO_DATE(LEFT({$col}, 10), '%Y-%m-%d'), '%x-W%v')",
            'year'  => "LEFT({$col}, 4)",
            default => "LEFT({$col}, 7)",
        };
    }

    /** Buckets kept: the latest N for the chosen period, more when a custom range is set. */
    public function bucketLimit(): int
    {
        $custom = $this->from !== '' || $this->to !== '';

        return match ($this->group) {
            'day'   => $custom ? 92 : 31,
            'week'  => $custom ? 53 : 12,
            'year'  => 10,
            default => $custom ? 36 : 24,
        };
    }

    public function periodWord(): string
    {
        return ['day' => 'day', 'week' => 'week', 'month' => 'month', 'year' => 'year'][$this->group];
    }
}
