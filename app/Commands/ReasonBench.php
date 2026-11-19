<?php

namespace App\Commands;

use App\Libraries\Reasoner;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark brain:reason-bench   measures the logical reasoning of the assistant:
 *  1) classification of a value against a standard (the rule is explicit, so every case has one right answer);
 *  2) reading a question ("is 2.1 a pass when the standard is 2.5") into value / standard / direction;
 *  3) consistency with the real records: for sampled Lab References the conclusion must name the true state stored in the database.
 */
class ReasonBench extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'brain:reason-bench';
    protected $description = 'Measures logical reasoning: classification, reading a question, agreement with the real records.';

    /** [value, standard, direction, expected] */
    public const CLASSIFY = [
        [3.0, 2.5, 'min', 'PASS'], [2.5, 2.5, 'min', 'PASS'], [2.4, 2.5, 'min', 'TOLERABLE'], [2.0, 2.5, 'min', 'TOLERABLE'], [1.99, 2.5, 'min', 'FAIL'], [0.0, 2.5, 'min', 'FAIL'],
        [1.0, 1.0, 'min', 'PASS'], [0.85, 1.0, 'min', 'TOLERABLE'], [0.79, 1.0, 'min', 'FAIL'], [100.0, 90.0, 'min', 'PASS'], [71.0, 90.0, 'min', 'FAIL'], [72.0, 90.0, 'min', 'TOLERABLE'],
        [1.8, 2.0, 'max', 'PASS'], [2.0, 2.0, 'max', 'PASS'], [2.3, 2.0, 'max', 'TOLERABLE'], [2.4, 2.0, 'max', 'TOLERABLE'], [2.41, 2.0, 'max', 'FAIL'], [5.0, 2.0, 'max', 'FAIL'],
    ];

    /** [question, value, standard, direction] or null when it is not a classification question */
    public const PARSE = [
        ['is 2.1 a pass when the standard is 2.5', 2.1, 2.5, 'min'], ['does 3.0 meet the standard of 2.5', 3.0, 2.5, 'min'], ['shrinkage 1.8 pass if max is 2', 1.8, 2.0, 'max'],
        ['the tensile is 85 and the std is 90 is it a fail', 85.0, 90.0, 'min'], ['value 1.2 with requirement 1.0 pass or fail', 1.2, 1.0, 'min'], ['abrasion 3.5 within the limit of 4', 3.5, 4.0, 'max'],
        ['what is the capital of france', null, null, null], ['how do i add a pull test', null, null, null],
    ];

    public static function accuracy(): array
    {
        $ok = 0; $n = 0; $miss = [];
        foreach (self::CLASSIFY as [$v, $s, $d, $e]) {
            $n++;
            $r = Reasoner::classify($v, $s, $d)['result'];
            $r === $e ? $ok++ : $miss[] = "classify {$v} vs {$s} {$d}: want {$e} got {$r}";
        }
        foreach (self::PARSE as [$q, $v, $s, $d]) {
            $n++;
            $p = Reasoner::parseClassify($q);
            $good = $v === null ? $p === null : ($p !== null && abs($p['value'] - $v) < 1e-9 && abs($p['std'] - $s) < 1e-9 && $p['dir'] === $d);
            $good ? $ok++ : $miss[] = "parse '{$q}': got " . json_encode($p);
        }
        // agreement with the real records
        $db = \Config\Database::connect();
        $rows = $db->query("SELECT LabRefNo, Result FROM tbl_dtr_bonding_info WHERE LabRefNo REGEXP '^DTP[0-9]{8}SH' AND COALESCE(Result,'') <> '' ORDER BY RAND() LIMIT 25")->getResultArray();
        foreach ($rows as $r) {
            $n++;
            $x = Reasoner::explainLabRef($r['LabRefNo']);
            stripos($x['summary'], substr($r['Result'], 0, 4)) !== false || stripos($x['summary'], 'complete') !== false ? $ok++ : $miss[] = "record {$r['LabRefNo']} result {$r['Result']}: {$x['summary']}";
        }
        $rows = $db->query("SELECT LabRefNo FROM tbl_dtr_BondingTracker WHERE Lacking = 1 AND Shutdown = 0 AND LabRefNo REGEXP '^DTP[0-9]{8}SH' AND NOT EXISTS (SELECT 1 FROM tbl_dtr_bonding_info b WHERE b.LabRefNo = tbl_dtr_BondingTracker.LabRefNo COLLATE utf8mb4_unicode_ci AND COALESCE(b.Result,'') <> '') LIMIT 10")->getResultArray();
        foreach ($rows as $r) {
            $n++;
            $x = Reasoner::explainLabRef($r['LabRefNo']);
            stripos($x['summary'], 'lacking') !== false ? $ok++ : $miss[] = "lacking {$r['LabRefNo']}: {$x['summary']}";
        }
        $rows = $db->query("SELECT LabRefNo, Result FROM tbl_dtr_pull_tests WHERE COALESCE(Result,'') <> '' ORDER BY RAND() LIMIT 10")->getResultArray();
        foreach ($rows as $r) {
            $n++;
            $x = Reasoner::explainLabRef($r['LabRefNo']);
            stripos($x['summary'], substr($r['Result'], 0, 4)) !== false || stripos($x['summary'], 'complete') !== false ? $ok++ : $miss[] = "pull {$r['LabRefNo']} {$r['Result']}: {$x['summary']}";
        }

        return ['accuracy' => round($ok * 100 / max(1, $n), 1), 'n' => $n, 'ok' => $ok, 'miss' => $miss];
    }

    public function run(array $params)
    {
        $r = self::accuracy();
        foreach ($r['miss'] as $m) {
            CLI::write('  MISS ' . $m);
        }
        CLI::write(sprintf('OVERALL REASONING | %.1f%% (%d of %d)', $r['accuracy'], $r['ok'], $r['n']));
    }
}
