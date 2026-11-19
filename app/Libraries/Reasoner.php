<?php

namespace App\Libraries;

/**
 * Logical reasoning over the laboratory's own data, with a trace you can read. Nothing here is guessed or learned: every conclusion is derived from
 * facts read from the database by explicit rules, and every step is shown ("Fact: ...", "Rule: ...", "So: ..."), so the owner can check the thinking.
 *
 *  - classify(): Pass / Tolerable / Fail of a measured value against a standard (the bonding rule of this system: Pass at or above the standard, Tolerable from 80% of it, Fail below);
 *  - explainLabRef(): what the state of a Lab Reference is, why, whether the records agree with each other, and what the next step is.
 */
class Reasoner
{
    /**
     * @param string $dir 'min' = the value must reach the standard (strength); 'max' = the value must stay under it (shrinkage, wear)
     * @return array{result:string,trace:list<string>}
     */
    public static function classify(float $value, float $std, string $dir = 'min', float $tol = 0.2): array
    {
        $t = [];
        $t[] = "Fact: measured value = {$value}, standard = {$std} (" . ($dir === 'min' ? 'the value must reach the standard' : 'the value must stay at or under the standard') . ').';
        if ($dir === 'min') {
            $low = round($std * (1 - $tol), 4);
            $t[] = "Rule: Pass if value >= standard; Tolerable if value >= {$low} (standard less " . round($tol * 100) . "%); otherwise Fail.";
            if ($value >= $std) {
                $r = 'PASS';
                $t[] = "Check: {$value} >= {$std} is true.";
            } elseif ($value >= $low) {
                $r = 'TOLERABLE';
                $t[] = "Check: {$value} >= {$std} is false, but {$value} >= {$low} is true.";
            } else {
                $r = 'FAIL';
                $t[] = "Check: {$value} >= {$std} is false and {$value} >= {$low} is false.";
            }
        } else {
            $hi = round($std * (1 + $tol), 4);
            $t[] = "Rule: Pass if value <= standard; Tolerable if value <= {$hi} (standard plus " . round($tol * 100) . "%); otherwise Fail.";
            if ($value <= $std) {
                $r = 'PASS';
                $t[] = "Check: {$value} <= {$std} is true.";
            } elseif ($value <= $hi) {
                $r = 'TOLERABLE';
                $t[] = "Check: {$value} <= {$std} is false, but {$value} <= {$hi} is true.";
            } else {
                $r = 'FAIL';
                $t[] = "Check: {$value} <= {$std} is false and {$value} <= {$hi} is false.";
            }
        }
        $t[] = "So: {$r}.";

        return ['result' => $r, 'trace' => $t];
    }

    /** reads "is 2.1 a pass when the standard is 2.5" style questions; null when it is not one */
    public static function parseClassify(string $text): ?array
    {
        $t = mb_strtolower($text);
        if (! preg_match('/\b(pass|tolerable|fail|ok|acceptable|within|meet|meets|reach|reaches)\b/u', $t) || ! preg_match_all('/(?<![\w.])\d+(?:\.\d+)?(?![\w])/', $t, $m) || count($m[0]) < 2) {
            return null;
        }
        $nums = array_map('floatval', $m[0]);
        $std  = null;
        if (preg_match('/\b(?:standard|std|spec|specification|requirement|minimum|min|maximum|max|limit|target)\b\D{0,12}(\d+(?:\.\d+)?)/', $t, $s)) {
            $std = (float) $s[1];
        } elseif (preg_match('/(\d+(?:\.\d+)?)\D{0,12}\b(?:standard|std|spec|requirement)\b/', $t, $s)) {
            $std = (float) $s[1];
        }
        if ($std === null) {
            $std = $nums[1];
        }
        $value = null;
        foreach ($nums as $n) {
            if ($n !== $std) {
                $value = $n;
                break;
            }
        }
        $value ??= $nums[0];
        $dir = preg_match('/\b(max|maximum|at most|under|below|not more than|shrinkage|wear|abrasion)\b/', $t) ? 'max' : 'min';

        return ['value' => $value, 'std' => $std, 'dir' => $dir];
    }

    /**
     * Reasons about one Lab Reference from every record that knows it.
     * @return array{found:bool,summary:string,trace:list<string>,next:string,confidence:int}
     */
    public static function explainLabRef(string $ref): array
    {
        $db    = \Config\Database::connect();
        $ref   = strtoupper(trim($ref));
        $trace = [];
        $u     = $db->query('SELECT form_slug, form_category, status, overall_result, tested_date, technician, source_table, source_id FROM tbl_dtr_unified_labrefs WHERE lab_ref_no = ? ORDER BY id DESC LIMIT 1', [$ref])->getRowArray();
        $b     = $db->query('SELECT BondingTestID, Result, TestedDate, ReceivedDate, TestType, Technician FROM tbl_dtr_bonding_info WHERE LabRefNo = ? ORDER BY BondingTestID DESC LIMIT 1', [$ref])->getRowArray();
        $k     = $db->query('SELECT TrackerID, Lacking, LackingDate, CollectionDate, TestType, PullTest, PullTestType FROM tbl_dtr_BondingTracker WHERE LabRefNo = ? ORDER BY TrackerID DESC LIMIT 1', [$ref])->getRowArray();
        $ws    = $db->query('SELECT TestType, Status, FinalResult, TimeStarted, TimeCompleted FROM tbl_dtr_wholeshoetracker WHERE WholeshoeLabRefNo = ? OR LabRefNo = ? ORDER BY TrackerID', [$ref, $ref])->getResultArray();
        $pt    = $db->query('SELECT TestType, Result, TestedDate FROM tbl_dtr_pull_tests WHERE LabRefNo = ? ORDER BY PullInfoID DESC LIMIT 1', [$ref])->getRowArray();
        if (! $u && ! $b && ! $k && ! $ws && ! $pt) {
            return ['found' => false, 'summary' => "I cannot find {$ref} in any record.", 'trace' => ["Fact: no row with Lab Reference {$ref} in the unified list, bonding records, the bonding tracker, whole shoe tests or pull tests."],
                'next' => 'Check the spelling. A Lab Reference looks like DTP + production date + test + line + side, for example DTP20261118SHB13-L.', 'confidence' => 60];
        }
        $summary = '';
        $next    = '';
        $isPull = (bool) preg_match('/^DTP\d{8}(HP|EY|DR|RVT|LLH|RIVET)/', $ref);   // a pull test Lab Reference is answered by the pull test record first
        if (($b || $k) && ! ($isPull && $pt)) {
            $res = $b ? trim((string) ($b['Result'] ?? '')) : '';
            $trace[] = 'Fact: bonding record ' . ($b ? 'exists (type ' . ($b['TestType'] ?: 'n/a') . ', received ' . ($b['ReceivedDate'] ?: 'n/a') . ', tested ' . ($b['TestedDate'] ?: 'not yet') . ', result ' . ($res !== '' ? $res : 'empty') . ')' : 'does not exist yet') . '.';
            if ($k) {
                $trace[] = 'Fact: the bonding tracker row says ' . ((int) $k['Lacking'] === 1 ? 'Lacking (due ' . ($k['LackingDate'] ?: 'no date') . ')' : 'not lacking') . ', collected ' . ($k['CollectionDate'] ?: 'n/a') . ', type ' . ($k['TestType'] ?: 'n/a') . ($k['PullTestType'] ? ', pull tests: ' . $k['PullTestType'] : '') . '.';
            }
            if ($res !== '') {
                $summary = "{$ref} is complete: the bonding result is " . strtoupper($res) . ($b['TestedDate'] ? ', tested ' . substr((string) $b['TestedDate'], 0, 10) : '') . '.';
                $trace[] = 'Rule: a bonding record with a result is a finished test.';
                $next    = 'Nothing to do on the test; send the report for approval if it is not approved yet.';
            } elseif ($k && ((int) $k['Lacking'] === 1 || strtoupper((string) ($b['TestType'] ?? '')) === 'LACKING')) {
                $summary = "{$ref} is waiting for its specimen: it is marked Lacking" . ($k['LackingDate'] ? ' (due ' . $k['LackingDate'] . ')' : '') . ' and has no result.';
                $trace[] = 'Rule: Lacking means production still owes the specimen, so no test can be done yet.';
                $next    = 'When the shoe arrives, encode it in Lab Tracker > Bonding Shoe Collected; the row then takes over this Lab Reference.';
            } else {
                $summary = "{$ref} is pending: the specimen is registered but no result has been entered.";
                $trace[] = 'Rule: a bonding record with no result and not Lacking is waiting for the test result.';
                $next    = 'Test the specimen and enter the result (Lab Tracker > open the Lab Reference).';
            }
            if ($u) {
                $uRes = trim((string) $u['overall_result']);
                $trace[] = 'Fact: the Lab Tracker list shows status ' . ($u['status'] ?: 'n/a') . ($uRes !== '' ? ', overall ' . $uRes : ', no overall result') . '.';
                if ($res === '' && $uRes !== '') {
                    $trace[] = 'Check: the list shows a result (' . $uRes . ') but the bonding record has none, so the two records disagree.';
                    $next   .= ' The records disagree: open the Lab Reference and re-save it so the list follows the record.';
                }
                if ($res !== '' && $uRes === '') {
                    $trace[] = 'Check: the bonding record has a result but the list shows none, so the list is behind the report.';
                    $next   .= ' The list is out of date: run the index sync (php spark dtr:sync-index) or save the record again.';
                }
            }
        }
        if ($ws) {
            $done = count(array_filter($ws, static fn ($r) => stripos((string) $r['Status'], 'COMPLETE') === 0));
            $fail = array_values(array_filter($ws, static fn ($r) => stripos((string) $r['FinalResult'], 'F') === 0));
            $run  = count(array_filter($ws, static fn ($r) => in_array(strtoupper((string) $r['Status']), ['RUNNING', 'PAUSED'], true)));
            $trace[] = 'Fact: whole shoe tests under this Lab Reference: ' . count($ws) . ' (' . implode(', ', array_map(static fn ($r) => $r['TestType'] . ' ' . (strtolower((string) $r['Status']) ?: 'pending') . ($r['FinalResult'] ? ' ' . strtoupper($r['FinalResult']) : ''), $ws)) . ').';
            if ($fail) {
                $summary = "{$ref} has failed: " . implode(', ', array_column($fail, 'TestType')) . '.';
                $trace[] = 'Rule: one failed test makes the whole shoe result Fail.';
                $next    = 'Review the failed test findings and decide on a retest.';
            } elseif ($done === count($ws)) {
                $summary = "{$ref} is complete: all " . count($ws) . ' whole shoe tests are done and none failed.';
                $trace[] = 'Rule: every test completed with no failure means the shoe passed.';
                $next    = 'Send the report for approval.';
            } else {
                $summary = "{$ref} is in progress: {$done} of " . count($ws) . ' whole shoe tests are done' . ($run ? ", {$run} running now" : '') . '.';
                $trace[] = 'Rule: a Lab Reference is complete only when all its tests are completed.';
                $next    = 'Wait for the running tests, or start the pending ones in Whole Shoe Test.';
            }
        }
        if ($pt && ($summary === '' || ($isPull && ! $ws))) {
            $pr      = trim((string) $pt['Result']);
            $summary = $pr !== '' ? "{$ref} is a pull test with result " . strtoupper($pr) . '.' : "{$ref} is a pull test still waiting for its result.";
            $trace[] = 'Fact: pull test ' . ($pt['TestType'] ?: '') . ', result ' . ($pr !== '' ? $pr : 'empty') . ', tested ' . ($pt['TestedDate'] ?: 'not yet') . '.';
            $next    = $pr !== '' ? 'Nothing to do.' : 'Run the pull test and import its result.';
        }
        if ($summary === '' && $u) {
            $summary = "{$ref} is listed as {$u['form_category']} with status " . ($u['status'] ?: 'unknown') . ($u['overall_result'] ? ', overall ' . $u['overall_result'] : '') . '.';
            $trace[] = 'Fact: only the Lab Tracker list knows this Lab Reference; its source record is ' . ($u['source_table'] ?: 'unknown') . '.';
            $next    = 'Open the Lab Reference in Lab Tracker for the detail.';
        }

        return ['found' => true, 'summary' => $summary, 'trace' => $trace, 'next' => trim($next), 'confidence' => 90];
    }
}
