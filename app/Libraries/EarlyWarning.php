<?php

namespace App\Libraries;

/**
 * Repeat-Failure / Line Shutdown Early Warning: a production LINE, a STYLE or a CEMENT (adhesive supplier) that keeps failing the bonding test is reported
 * before it becomes a full line shutdown. Two signals, both from the saved bonding reports of tbl_dtr_bonding_info (trial, LACKING and shutdown rows left out):
 *  - streak: the latest results of the group end in FAIL on N or more different Lab Refs in a row (default 2; the same Lab Ref tested again and again counts once);
 *  - rate:   in the last 7 days the group failed X % or more of at least M rated Lab Refs, each by its latest result (default 20 % of 10).
 * Settings (Settings table keys): warn.streak, warn.rate_pct, warn.rate_min, warn.days (how far back a streak may start, default 3).
 */
class EarlyWarning
{
    public static function config(): array
    {
        return [
            'streak'   => max(2, (int) SystemSettings::get('warn.streak', 2)),
            'rate_pct' => max(1, (int) SystemSettings::get('warn.rate_pct', 20)),
            'rate_min' => max(3, (int) SystemSettings::get('warn.rate_min', 10)),
            'days'     => max(1, (int) SystemSettings::get('warn.days', 3)),
        ];
    }

    /**
     * @return list<array{key:string,kind:string,group:string,name:string,severity:string,streak:int,fails:int,rated:int,fail_rate:float,last_ref:string,refs:string,since:string,text:string}>
     */
    public static function detect(): array
    {
        $c  = self::config();
        $db = \Config\Database::connect();
        $rows = $db->query("SELECT BondingTestID id, LabRefNo ref, Result, ModelStyle style, AdhesiveSupplier cement, LEFT(TestedDate, 10) td
            FROM tbl_dtr_bonding_info
            WHERE TestedDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND COALESCE(Result, '') <> ''
              AND LabRefNo NOT LIKE '%TRIAL%' AND LabRefNo NOT LIKE '%SHUTDOWN%' AND COALESCE(TestType, '') NOT LIKE '%LACKING%' AND COALESCE(ModelStyle, '') <> 'SHUTDOWN'
            ORDER BY BondingTestID")->getResultArray();
        $groups = [];
        foreach ($rows as $r) {
            $res = strtoupper(substr(trim((string) $r['Result']), 0, 1));
            if (! in_array($res, ['P', 'T', 'F'], true)) {
                continue;
            }
            $line = \App\Controllers\QualityInsightsController::lineOf((string) $r['ref']);
            $keys = [];
            if ($line !== '') {
                $keys[] = ['Line', $line];
            }
            if (trim((string) $r['style']) !== '') {
                $keys[] = ['Style', trim((string) $r['style'])];
            }
            if (trim((string) $r['cement']) !== '') {
                $keys[] = ['Cement', trim((string) $r['cement'])];
            }
            foreach ($keys as [$g, $n]) {
                $groups[$g . '|' . $n][] = ['res' => $res, 'ref' => (string) $r['ref'], 'td' => (string) $r['td']];
            }
        }
        $out    = [];
        $today  = date('Y-m-d');
        $cutoff = date('Y-m-d', strtotime('-' . ($c['days'] - 1) . ' days'));
        foreach ($groups as $k => $seq) {
            [$g, $n] = explode('|', $k, 2);
            // streak: walk back from the newest report while it is a FAIL; count the different Lab Refs
            $refs = [];
            $first = '';
            for ($i = count($seq) - 1; $i >= 0 && $seq[$i]['res'] === 'F'; $i--) {
                $refs[$seq[$i]['ref']] = true;
                $first = $seq[$i]['td'];
            }
            $last = end($seq);
            $byRef = [];      // the rate counts every Lab Ref once, by its latest result (a ref re-tested ten times is one shoe, not ten)
            foreach ($seq as $x) {
                $byRef[$x['ref']] = $x['res'];
            }
            $fails = count(array_filter($byRef, static fn ($x) => $x === 'F'));
            $rated = count($byRef);
            $rate  = $rated ? round($fails * 100 / $rated, 1) : 0.0;
            if (count($refs) >= $c['streak'] && $last['td'] >= $cutoff) {
                $sev = count($refs) >= $c['streak'] + 2 ? 'high' : 'medium';
                $out[] = ['key' => "ew|streak|{$k}|" . $today, 'kind' => 'streak', 'group' => $g, 'name' => $n, 'severity' => $sev, 'streak' => count($refs), 'fails' => $fails, 'rated' => $rated, 'fail_rate' => $rate,
                    'last_ref' => $last['ref'], 'refs' => implode(', ', array_slice(array_keys($refs), 0, 5)), 'since' => $first,
                    'text' => "{$g} {$n}: " . count($refs) . ' different Lab Refs failed in a row (latest ' . $last['ref'] . ').'];
            }
            if ($rated >= $c['rate_min'] && $rate >= $c['rate_pct']) {
                $out[] = ['key' => "ew|rate|{$k}|" . $today, 'kind' => 'rate', 'group' => $g, 'name' => $n, 'severity' => $rate >= $c['rate_pct'] * 2 ? 'high' : 'medium', 'streak' => count($refs), 'fails' => $fails, 'rated' => $rated, 'fail_rate' => $rate,
                    'last_ref' => $last['ref'], 'refs' => '', 'since' => date('Y-m-d', strtotime('-6 days')),
                    'text' => "{$g} {$n}: {$fails} of {$rated} Lab Refs failed in the last 7 days ({$rate} %)."];
            }
        }
        usort($out, static fn ($a, $b) => [$a['severity'] === 'high' ? 0 : 1, -$a['fail_rate']] <=> [$b['severity'] === 'high' ? 0 : 1, -$b['fail_rate']]);

        return $out;
    }
}
