<?php

namespace App\Controllers;

use App\Libraries\ErpMonitor;

/** Lab Monitoring: ERP Finish Monitoring (bonding lab refs of a production day, their InterLink numbers and ERP status) */
class LabMonitoringController extends BaseController
{
    private function db()
    {
        return \Config\Database::connect();
    }

    private function json(array $data, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($data + ['csrf' => csrf_hash()]);
    }

    /** GET lab-monitoring, lab-monitoring/erp-finish */
    public function erp(): string
    {
        $me = (array) service('auth')->user();

        return $this->render('labmonitor/erp', [
            'title'   => 'ERP Finish Monitoring',
            'withCss' => 'labmon.css',
            'withJs'  => ['labmon.js'],
            'meId'    => (int) ($me['id'] ?? 0),
        ]);
    }

    /** the production day of a lab ref: the tracker's date, else the date inside the lab ref (DTP20261103SHA1-R) */
    private const MANUAL_IL = ['MOK' => 'ok', 'MPENDING' => 'pending', 'MNO' => 'bad'];
    private const DAY_SQL = "COALESCE(t.ProductionDate, STR_TO_DATE(SUBSTRING(u.lab_ref_no, 4, 8), '%Y%m%d'))";

    private function base()
    {
        ErpMonitor::ensureColumns();

        // Index lookups per lab ref (the old GROUP BY sub-tables with COLLATE on both sides read both tables in full: ~6 s a query).
        // The tracker has one row per lab ref (unique LabRefNo); the bonding info takes its newest row through its LabRefNo index.
        return "FROM tbl_dtr_unified_labrefs u
            LEFT JOIN tbl_dtr_bondingtracker t ON t.LabRefNo = (u.lab_ref_no COLLATE utf8mb4_general_ci)
            LEFT JOIN tbl_dtr_bonding_info b ON b.BondingTestID = (SELECT MAX(b2.BondingTestID) FROM tbl_dtr_bonding_info b2 WHERE b2.LabRefNo = u.lab_ref_no)
            WHERE u.form_slug = 'bonding' AND u.lab_ref_no REGEXP '^DTP[0-9]{8}'";
    }

    /** one request state => ok | pending | bad */
    private function reqState(string $status): string
    {
        $s = strtolower($status);
        if (in_array($s, ['submitted', 'done', 'success', 'completed'], true)) {
            return 'ok';
        }
        if (str_contains($s, 'fail') || str_contains($s, 'error') || str_contains($s, 'cancel') || $s === 'rehearsed') {
            return 'bad';
        }

        return 'pending';
    }

    /** GET lab-monitoring/data?date=YYYY-MM-DD&q=&side=&status= */
    public function data()
    {
        if ((string) $this->request->getGet('kind') === 'wholeshoe') {
            return $this->dataWholeShoe();
        }
        $db   = $this->db();
        $base = $this->base();
        $day  = self::DAY_SQL;
        $days = $db->query("SELECT DATE_FORMAT(d, '%Y-%m') d, COUNT(*) c FROM (SELECT {$day} d {$base}) x WHERE d IS NOT NULL GROUP BY 1 ORDER BY 1 DESC LIMIT 36")->getResultArray();   // the months that have lab refs
        $date = (string) $this->request->getGet('month');
        if (! preg_match('/^\d{4}-\d{2}$/', $date)) {
            $date = (string) ($days[0]['d'] ?? date('Y-m'));
        }
        $rows = $db->query("SELECT u.id, u.lab_ref_no, u.etrf_bonding_app, u.etrf_bonding_hist, u.etrf_pull_app, u.etrf_pull_hist, u.erp_status, u.interlink_flag, u.po_lot_no, u.model_style, u.overall_result,
                t.LineNo t_line, t.Side t_side, t.ProductionCode t_vsp, t.Shutdown t_shut, t.ProductionDate t_pd,
                b.Result b_result, b.LabNotesComments b_notes, b.PossibleCause b_cause, COALESCE(NULLIF(b.TestedDate, ''), NULLIF(u.tested_date, '')) b_tested, b.POLotNO b_po, DATE_FORMAT({$day}, '%Y-%m-%d') pday
            {$base} AND DATE_FORMAT({$day}, '%Y-%m') = ? ORDER BY {$day}, u.lab_ref_no", [$date])->getResultArray();

        // the latest bonding / pull request per lab ref, to tell OK from pending from not submitted
        $labs = array_column($rows, 'lab_ref_no');
        $reqs = [];
        if ($labs) {
            foreach ($db->table('tbl_interlink_etrf_requests')->select('lab_ref_no, etrf_type, status')->whereIn('lab_ref_no', $labs)->orderBy('id')->get()->getResultArray() as $q) {
                $k = \App\Libraries\EtrfAppNos::keyFor((string) $q['etrf_type']);
                if (in_array($k, ['bonding', 'pull'], true)) {
                    $reqs[$q['lab_ref_no']][$k] = $this->reqState((string) $q['status']);   // the later row wins
                }
            }
        }

        $pulls = $this->pullTrs($labs);
        $out = [];
        $sum = ['total' => 0, 'il_ok' => 0, 'il_pending' => 0, 'il_bad' => 0, 'erp_ok' => 0, 'erp_pending' => 0, 'erp_bad' => 0];
        $q    = strtolower(trim((string) $this->request->getGet('q')));
        $side = strtoupper(trim((string) $this->request->getGet('side')));
        $stat = trim((string) $this->request->getGet('status'));
        foreach ($rows as $r) {
            $lab = (string) $r['lab_ref_no'];
            preg_match('/(?:SH|FA)([A-Z]\d+)-([RL])\b/i', $lab, $m);
            $line = trim((string) $r['t_line']) !== '' ? (string) $r['t_line'] : ($m[1] ?? '');
            $sd   = trim((string) $r['t_side']) !== '' ? strtoupper((string) $r['t_side']) : strtoupper($m[2] ?? '');
            $vsp  = trim((string) $r['t_vsp']) !== '' ? (string) $r['t_vsp'] : trim((string) ($r['b_po'] ?: $r['po_lot_no']));

            $st = array_values($reqs[$lab] ?? []);
            if (isset(self::MANUAL_IL[strtoupper((string) $r['interlink_flag'])])) {
                $il = self::MANUAL_IL[strtoupper((string) $r['interlink_flag'])];   // typed by hand with the Edit button
            } elseif (in_array('bad', $st, true)) {
                $il = 'bad';
            } elseif (in_array('pending', $st, true)) {
                $il = 'pending';
            } elseif (in_array('ok', $st, true) || trim((string) $r['etrf_bonding_app']) !== '' || $r['interlink_flag'] === 'OK') {
                $il = 'ok';
            } else {
                $il = 'bad';
            }
            $erpRaw = trim((string) $r['erp_status']);
            $erp    = in_array(strtolower($erpRaw), ['ok', 'submitted'], true) ? 'ok' : (in_array(strtolower($erpRaw), ['ongoing', 'pending'], true) ? 'pending' : 'bad');
            $res    = strtoupper(trim((string) ($r['b_result'] ?: $r['overall_result'])));
            $level  = str_contains($res, 'FAIL') ? 'fail' : (str_contains($res, 'TOLER') ? 'tol' : ($res !== '' ? 'pass' : ''));

            $row = [
                'id' => (int) $r['id'], 'lab_ref' => $lab, 'line' => $line, 'side' => $sd, 'vsp' => $vsp, 'shutdown' => (int) $r['t_shut'] === 1,
                'tested' => ($tt = strtotime((string) ($r['b_tested'] ?? ''))) ? date('Y-m-d', $tt) : '', 'day' => (string) $r['pday'],
                'bonding' => trim((string) $r['etrf_bonding_app']), 'bonding_hist' => trim((string) $r['etrf_bonding_hist']),
                'pull' => trim((string) $r['etrf_pull_app']) !== '' ? trim((string) $r['etrf_pull_app']) : ($pulls[$lab]['app'] ?? ''),
                'pull_hist' => trim((string) $r['etrf_pull_app']) !== '' ? trim((string) $r['etrf_pull_hist']) : ($pulls[$lab]['hist'] ?? ''),
                'il' => $il, 'erp' => $erp, 'erp_raw' => $erpRaw,
                'result' => $res, 'level' => $level, 'notes' => trim((string) $r['b_notes']), 'cause' => trim((string) $r['b_cause']),
            ];
            $sum['total']++;
            $sum['il_' . $il]++;
            $sum['erp_' . $erp]++;
            if ($side !== '' && $sd !== $side) {
                continue;
            }
            if ($stat !== '' && $stat !== $il && $stat !== 'erp_' . $erp && $stat !== 'il_' . $il) {
                continue;
            }
            if ($q !== '' && ! str_contains(strtolower(implode(' ', [$lab, $line, $vsp, $row['bonding'], $row['pull'], $row['bonding_hist'], $row['pull_hist'], $row['result'], $row['notes']])), $q)) {
                continue;
            }
            $out[] = $row;
        }
        $ln = static fn ($r) => [$r['day'], $r['line'] !== '' ? $r['line'][0] : '', (int) preg_replace('/\D/', '', $r['line'])];
        usort($out, static fn ($a, $b) => $ln($a) <=> $ln($b));   // production day, then building letter, then line number

        return $this->json(['ok' => true, 'month' => $date, 'days' => $days, 'rows' => $out, 'summary' => $sum]);
    }

    /**
     * The Pull test TR of a shoe line: the pull tests (EY / DR / HP / RV ... Lab Refs, form pulltest) of the same day, line and side as the bonding Lab Ref
     * (DTP20261112SHA1-R <-> DTP20261112EYA1-R) carry their own Application No. The newest is the current one, the others go to "earlier".
     * @param list<string> $labs bonding Lab Refs
     * @return array<string, array{app:string, hist:string}>
     */
    private function pullTrs(array $labs): array
    {
        $want = [];
        foreach ($labs as $lab) {
            if (preg_match('/^DTP(\d{8})(?:SH|FA)([A-Z]\d+)-([RL])\b/i', (string) $lab, $m)) {
                $want[(string) $lab] = strtoupper($m[1] . '|' . $m[2] . '|' . $m[3]);
            }
        }
        if (! $want) {
            return [];
        }
        $dates = array_values(array_unique(array_map(static fn ($k) => explode('|', $k)[0], $want)));
        $by    = [];
        foreach ($this->db()->query("SELECT id, lab_ref_no, etrf_pull_app, etrf_pull_hist FROM tbl_dtr_unified_labrefs WHERE form_slug = 'pulltest' AND etrf_pull_app <> ''
                AND lab_ref_no REGEXP ?", ['^DTP(' . implode('|', $dates) . ')[A-Z]{2}[A-Z][0-9]+-[LR]'])->getResultArray() as $u) {
            if (preg_match('/^DTP(\d{8})[A-Z]{2}([A-Z]\d+)-([RL])\b/i', (string) $u['lab_ref_no'], $m)) {
                $by[strtoupper($m[1] . '|' . $m[2] . '|' . $m[3])][] = $u;
            }
        }
        $out = [];
        foreach ($want as $lab => $key) {
            $list = $by[$key] ?? [];
            if (! $list) {
                continue;
            }
            usort($list, static fn ($a, $b) => (int) $b['id'] <=> (int) $a['id']);
            $cur  = trim((string) $list[0]['etrf_pull_app']);
            $hist = [];
            foreach ($list as $i => $u) {
                foreach (array_merge($i === 0 ? [] : [(string) $u['etrf_pull_app']], explode(';', (string) $u['etrf_pull_hist'])) as $h) {
                    $h = trim($h);
                    if ($h !== '' && $h !== $cur && ! in_array($h, $hist, true)) {
                        $hist[] = $h;
                    }
                }
            }
            $out[$lab] = ['app' => $cur, 'hist' => implode(';', $hist)];
        }

        return $out;
    }

    /**
     * The Whole Shoe tab: the same view as the Bonding one for the whole shoe lab references (tbl_dtr_unified_labrefs, form_slug 'wholeshoe'), one line per lab ref,
     * with the TR numbers of the whole shoe tests kept in the same table (the ERP's own boxes): Flexing, Aging, Hydrolysis, Washing and UV Light (etrf_<key>_app, the earlier ones in _hist).
     */
    private function dataWholeShoe()
    {
        $db   = $this->db();
        ErpMonitor::ensureColumns();
        $day  = "STR_TO_DATE(SUBSTRING(u.lab_ref_no, 4, 8), '%Y%m%d')";
        $from = "FROM tbl_dtr_unified_labrefs u WHERE u.form_slug = 'wholeshoe' AND u.lab_ref_no REGEXP '^DTP[0-9]{8}'";
        $days = $db->query("SELECT DATE_FORMAT(d, '%Y-%m') d, COUNT(DISTINCT lab) c FROM (SELECT {$day} d, u.lab_ref_no lab {$from}) x WHERE d IS NOT NULL GROUP BY 1 ORDER BY 1 DESC LIMIT 36")->getResultArray();
        $date = (string) $this->request->getGet('month');
        if (! preg_match('/^\d{4}-\d{2}$/', $date)) {
            $date = (string) ($days[0]['d'] ?? date('Y-m'));
        }
        // one line per lab ref (the intake keeps one row per shoe, so a lab ref can appear on several rows): the first row's id, the TR numbers of whichever row holds them
        $rows = $db->query("SELECT MIN(u.id) id, u.lab_ref_no,
                MAX(u.etrf_flexing_app) flexing_app, MAX(u.etrf_flexing_hist) flexing_hist, MAX(u.etrf_aging_app) aging_app, MAX(u.etrf_aging_hist) aging_hist,
                MAX(u.etrf_hydrolysis_app) hydrolysis_app, MAX(u.etrf_hydrolysis_hist) hydrolysis_hist, MAX(u.etrf_washing_app) washing_app, MAX(u.etrf_washing_hist) washing_hist,
                MAX(u.etrf_uv_app) uv_app, MAX(u.etrf_uv_hist) uv_hist,
                MAX(NULLIF(u.erp_status, '')) erp_status, MAX(NULLIF(u.interlink_flag, '')) interlink_flag, MAX(NULLIF(u.po_lot_no, '')) po_lot_no, MAX(NULLIF(u.model_style, '')) model_style,
                MAX(NULLIF(u.overall_result, '')) overall_result, MAX(NULLIF(u.tested_date, '')) tested_date, MAX(NULLIF(u.received_date, '')) received_date,
                DATE_FORMAT({$day}, '%Y-%m-%d') pday
            {$from} AND DATE_FORMAT({$day}, '%Y-%m') = ? GROUP BY u.lab_ref_no ORDER BY pday, u.lab_ref_no", [$date])->getResultArray();

        // the eTRF requests of the TESTS of each whole shoe (their own lab refs, linked by WholeshoeLabRefNo): ok / pending / bad per whole shoe and test
        $labs = array_column($rows, 'lab_ref_no');
        $reqs = [];
        $testedBy = [];   // tested date fallback: the last test finished (else started) in the tracker
        if ($labs) {
            $tests = [];
            foreach ($db->table('tbl_dtr_wholeshoetracker')->select('LabRefNo, WholeshoeLabRefNo, TimeStarted, TimeCompleted')->whereIn('WholeshoeLabRefNo', $labs)->get()->getResultArray() as $t) {
                $tests[$t['LabRefNo']] = $t['WholeshoeLabRefNo'];
                $d = substr((string) (($t['TimeCompleted'] ?? '') ?: ($t['TimeStarted'] ?? '')), 0, 10);
                if ($d !== '' && $d > ($testedBy[$t['WholeshoeLabRefNo']] ?? '')) {
                    $testedBy[$t['WholeshoeLabRefNo']] = $d;
                }
            }
            $names = array_merge($labs, array_keys($tests));
            foreach ($db->table('tbl_interlink_etrf_requests')->select('lab_ref_no, status')->whereIn('lab_ref_no', $names)->orderBy('id')->get()->getResultArray() as $q) {
                $wh = $tests[$q['lab_ref_no']] ?? $q['lab_ref_no'];
                $reqs[$wh][$q['lab_ref_no']] = $this->reqState((string) $q['status']);   // the later request of a test wins
            }
        }
        $ymd = static function (string $v): string {
            $v = trim($v);
            if ($v === '') {
                return '';
            }
            $t = strtotime($v);

            return $t ? date('Y-m-d', $t) : '';
        };
        $out  = [];
        $sum  = ['total' => 0, 'il_ok' => 0, 'il_pending' => 0, 'il_bad' => 0, 'erp_ok' => 0, 'erp_pending' => 0, 'erp_bad' => 0];
        $q    = strtolower(trim((string) $this->request->getGet('q')));
        $side = strtoupper(trim((string) $this->request->getGet('side')));
        $stat = trim((string) $this->request->getGet('status'));
        foreach ($rows as $r) {
            $lab = (string) $r['lab_ref_no'];
            preg_match('/(?:WH|SH|FA)([A-Z]\d+)-([RL])\b/i', $lab, $m);
            $line = $m[1] ?? '';
            $sd   = strtoupper($m[2] ?? '');
            $st   = array_values($reqs[$lab] ?? []);
            $hasTr = trim((string) $r['flexing_app'] . $r['aging_app'] . $r['hydrolysis_app'] . $r['washing_app'] . $r['uv_app']) !== '';
            if (isset(self::MANUAL_IL[strtoupper((string) $r['interlink_flag'])])) {
                $il = self::MANUAL_IL[strtoupper((string) $r['interlink_flag'])];   // typed by hand with the Edit button
            } elseif (in_array('bad', $st, true)) {
                $il = 'bad';
            } elseif (in_array('pending', $st, true)) {
                $il = 'pending';
            } elseif (in_array('ok', $st, true) || $hasTr || strtoupper((string) $r['interlink_flag']) === 'OK') {
                $il = 'ok';
            } else {
                $il = 'bad';
            }
            $erpRaw = trim((string) $r['erp_status']);
            $erp    = in_array(strtolower($erpRaw), ['ok', 'submitted'], true) ? 'ok' : (in_array(strtolower($erpRaw), ['ongoing', 'pending'], true) ? 'pending' : 'bad');
            $res    = strtoupper(trim((string) $r['overall_result']));
            $level  = str_contains($res, 'FAIL') ? 'fail' : (str_contains($res, 'TOLER') ? 'tol' : ($res !== '' ? 'pass' : ''));
            $row = [
                'id' => (int) $r['id'], 'lab_ref' => $lab, 'line' => $line, 'side' => $sd, 'vsp' => trim((string) $r['po_lot_no']), 'shutdown' => false, 'style' => trim((string) $r['model_style']),
                'tested' => $ymd((string) $r['tested_date']) ?: ($testedBy[$lab] ?? ''), 'day' => (string) $r['pday'],
                'flexing' => trim((string) $r['flexing_app']), 'flexing_hist' => trim((string) $r['flexing_hist']), 'aging' => trim((string) $r['aging_app']), 'aging_hist' => trim((string) $r['aging_hist']),
                'hydrolysis' => trim((string) $r['hydrolysis_app']), 'hydrolysis_hist' => trim((string) $r['hydrolysis_hist']), 'washing' => trim((string) $r['washing_app']), 'washing_hist' => trim((string) $r['washing_hist']),
                'uv' => trim((string) $r['uv_app']), 'uv_hist' => trim((string) $r['uv_hist']),
                'il' => $il, 'erp' => $erp, 'erp_raw' => $erpRaw, 'result' => $res, 'level' => $level, 'notes' => '', 'cause' => '',
            ];
            $sum['total']++;
            $sum['il_' . $il]++;
            $sum['erp_' . $erp]++;
            if ($side !== '' && $sd !== $side) {
                continue;
            }
            if ($stat !== '' && $stat !== $il && $stat !== 'erp_' . $erp && $stat !== 'il_' . $il) {
                continue;
            }
            if ($q !== '' && ! str_contains(strtolower(implode(' ', [$lab, $line, $row['vsp'], $row['style'], $row['flexing'], $row['aging'], $row['hydrolysis'], $row['washing'], $row['uv'], $row['result']])), $q)) {
                continue;
            }
            $out[] = $row;
        }
        $ln = static fn ($r) => [$r['day'], $r['line'] !== '' ? $r['line'][0] : '', (int) preg_replace('/\D/', '', $r['line'])];
        usort($out, static fn ($a, $b) => $ln($a) <=> $ln($b));

        return $this->json(['ok' => true, 'kind' => 'wholeshoe', 'month' => $date, 'days' => $days, 'rows' => $out, 'summary' => $sum]);
    }

    /** GET lab-monitoring/erp-log : every report sent to ERP, one by one or in bulk (newest first), polled to follow the status */
    public function erpLog()
    {
        ErpMonitor::ensureLogTable();
        $this->redispatch();
        $rows = $this->db()->table('tbl_erp_submissions')
            ->select('id, lab_ref_no, production_date, mode, batch_id, status, message, requested_by_id, requested_by_name, created_at, submitted_at')
            ->orderBy('id', 'DESC')->limit(60)->get()->getResultArray();
        foreach ($rows as &$r) {
            $r['state'] = $this->reqState((string) $r['status']);
        }

        return $this->json(['ok' => true, 'rows' => $rows]);
    }

    /**
     * POST lab-monitoring/erp-submit {ids: [labref row ids], mode: single|bulk}
     * Puts each report in the ERP log as "queued" (a bulk send shares one batch id) and marks it Pending. A report that is already
     * ERP OK / Submitted, or already queued / sending, is skipped. The sender that talks to ERP picks up the queued rows and sets them
     * to submitted / failed, which the page shows and tells the submitter about.
     */
    public function erpSubmit()
    {
        if (! \App\Libraries\Roles::can((string) (service('auth')->user()['role'] ?? ''), 'erpfinish', 2)) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'Your role may view ERP Finish Monitoring but not send or change reports (Settings > Users & roles).']);
        }
        $p   = $this->request->getJSON(true) ?? [];
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($p['ids'] ?? [])))));
        if (! $ids || count($ids) > 500) {
            return $this->json(['ok' => false, 'error' => 'Pick one to 500 reports.'], 422);
        }
        ErpMonitor::ensureColumns();
        ErpMonitor::ensureLogTable();
        if ((string) ($p['kind'] ?? '') === 'wholeshoe') {
            return $this->erpSubmitWholeShoe($ids, ! empty($p['dry']));
        }
        $db   = $this->db();
        $me   = (array) service('auth')->user();
        $mode = count($ids) > 1 ? 'bulk' : 'single';
        $batch = $mode === 'bulk' ? 'B' . date('ymdHis') . substr(bin2hex(random_bytes(2)), 0, 3) : '';
        $now  = date('Y-m-d H:i:s');
        $queued = $skipped = 0;
        $invalid = [];
        $dry     = ! empty($p['dry']);   // dry = only show the ERP data of each report (nothing is queued, no status changes)
        $preview = [];
        $toSend  = [];
        $dayExpr = self::DAY_SQL;
        $in      = implode(',', array_map('intval', $ids));
        $found   = $db->query("SELECT u.id, u.lab_ref_no, u.erp_status, u.etrf_bonding_app, u.etrf_pull_app, u.po_lot_no, u.overall_result, u.tested_date,
                t.LineNo t_line, t.ProductionCode t_vsp, b.Result b_result, b.TestedDate b_tested, b.POLotNO b_po, DATE_FORMAT({$dayExpr}, '%Y-%m-%d') pday
            " . $this->base() . " AND u.id IN ({$in}) ORDER BY u.id")->getResultArray();   // FIFO: the order they were collected
        $pullMap = $this->pullTrs(array_column($found, 'lab_ref_no'));
        foreach ($found as $r) {
            $busy = $db->table('tbl_erp_submissions')->where('lab_ref_no', $r['lab_ref_no'])->whereIn('status', ['queued', 'sending'])->countAllResults() > 0;
            if (in_array(strtolower((string) $r['erp_status']), ['ok', 'submitted'], true) || $busy) {
                $skipped++;
                continue;
            }
            // what the ERP needs for this report (Finished Product Test Report): the sender copies these into ERP in order
            preg_match('/(?:SH|FA)([A-Z]\d+)-([RL])$/i', (string) $r['lab_ref_no'], $m);
            $payload = [
                'lab_ref_no'      => (string) $r['lab_ref_no'],
                'vsp_code'        => trim((string) $r['t_vsp']) !== '' ? trim((string) $r['t_vsp']) : trim((string) ($r['b_po'] ?: $r['po_lot_no'])),
                'bonding_app'     => trim((string) $r['etrf_bonding_app']),
                'pull_app'        => trim((string) $r['etrf_pull_app']) !== '' ? trim((string) $r['etrf_pull_app']) : (string) ($pullMap[(string) $r['lab_ref_no']]['app'] ?? ''),
                'line'            => trim((string) $r['t_line']) !== '' ? (string) $r['t_line'] : (string) ($m[1] ?? ''),
                'side'            => strtoupper((string) ($m[2] ?? '')),   // the last letter of the lab reference: L or R
                'production_date' => (string) $r['pday'],
                'tested_date'     => ErpMonitor::ymd($r['b_tested'] ?: $r['tested_date']),
                'result'          => ErpMonitor::erpResult((string) ($r['b_result'] ?: $r['overall_result'])),
                'mtl'             => $this->mtlOf($db, trim((string) $r['t_vsp']) !== '' ? trim((string) $r['t_vsp']) : trim((string) ($r['b_po'] ?: $r['po_lot_no']))),
            ];
            $missing = [];
            foreach (['vsp_code' => 'VSP code', 'mtl' => 'MTL # of the VSP code (shoe list)', 'bonding_app' => 'Bonding TR no.', 'side' => 'L/R', 'production_date' => 'production date', 'tested_date' => 'tested date', 'result' => 'result (PASS, TOLERABLE or FAILED)'] as $k => $label) {
                if ($payload[$k] === '') {
                    $missing[] = $label;
                }
            }
            if ($missing) {
                $invalid[] = ['lab_ref_no' => $r['lab_ref_no'], 'missing' => $missing];
                continue;
            }
            if ($dry) {
                $preview[] = $payload;
                continue;
            }
            $day = $payload['production_date'] ?: null;
            $db->table('tbl_erp_submissions')->insert([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'labref_id' => (int) $r['id'], 'lab_ref_no' => $r['lab_ref_no'], 'production_date' => $day, 'mode' => $mode, 'batch_id' => $batch, 'status' => 'queued',
                'message' => 'Waiting to be sent', 'requested_by_id' => (int) ($me['id'] ?? 0), 'requested_by_name' => (string) ($me['name'] ?? $me['username'] ?? ''),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $subId = (int) $db->insertID();
            $db->table('tbl_dtr_unified_labrefs')->where('id', $r['id'])->update(['erp_status' => 'Pending']);
            $toSend[] = ['id' => $subId, 'lab_ref_no' => (string) $r['lab_ref_no'], 'by' => (string) ($me['name'] ?? $me['username'] ?? ''), 'payload' => $payload];
            $queued++;
        }
        $dispatch = $toSend ? $this->dispatch($toSend) : null;

        return $this->json(['ok' => true, 'queued' => $queued, 'skipped' => $skipped, 'invalid' => $invalid, 'batch' => $batch, 'dispatch' => $dispatch] + ($dry ? ['preview' => $preview] : []));
    }

    /**
     * Whole Shoe lines to the ERP agent: one report per whole shoe lab ref (DTP20261116WHA3-L) with the TR numbers of its tests (Flexing, Aging, Hydrolysis, Washing, UV Light);
     * the agent fills those boxes of the Finished Product Test Report (no Bonding / Pull TR). A line needs at least one test TR.
     */
    private function erpSubmitWholeShoe(array $ids, bool $dry)
    {
        $db   = $this->db();
        $me   = (array) service('auth')->user();
        $mode = count($ids) > 1 ? 'bulk' : 'single';
        $batch = $mode === 'bulk' ? 'B' . date('ymdHis') . substr(bin2hex(random_bytes(2)), 0, 3) : '';
        $now  = date('Y-m-d H:i:s');
        $in   = implode(',', array_map('intval', $ids));
        $labs = array_column($db->query("SELECT DISTINCT lab_ref_no FROM tbl_dtr_unified_labrefs WHERE form_slug = 'wholeshoe' AND id IN ({$in})")->getResultArray(), 'lab_ref_no');
        $queued = $skipped = 0;
        $invalid = $preview = $toSend = [];
        foreach ($labs as $lab) {
            $rows = $db->query("SELECT MIN(u.id) id, MAX(NULLIF(u.erp_status, '')) erp_status, MAX(NULLIF(u.po_lot_no, '')) po_lot_no, MAX(NULLIF(u.overall_result, '')) overall_result, MAX(NULLIF(u.tested_date, '')) tested_date,
                    MAX(u.etrf_flexing_app) flexing, MAX(u.etrf_aging_app) aging, MAX(u.etrf_hydrolysis_app) hydrolysis, MAX(u.etrf_washing_app) washing, MAX(u.etrf_uv_app) uv
                FROM tbl_dtr_unified_labrefs u WHERE u.form_slug = 'wholeshoe' AND u.lab_ref_no = ?", [$lab])->getRowArray();
            $busy = $db->table('tbl_erp_submissions')->where('lab_ref_no', $lab)->whereIn('status', ['queued', 'sending'])->countAllResults() > 0;
            if (in_array(strtolower((string) $rows['erp_status']), ['ok', 'submitted'], true) || $busy) {
                $skipped++;
                continue;
            }
            preg_match('/^DTP(\d{4})(\d{2})(\d{2})(?:WS|WH|SH|FA)([A-Z]\d+)-([RL])\b/i', $lab, $m);
            $done = $db->table('tbl_dtr_wholeshoetracker')->selectMax('TimeCompleted')->where('WholeshoeLabRefNo', $lab)->get()->getRowArray();
            $vsp  = trim((string) $rows['po_lot_no']);
            $payload = [
                'kind'            => 'wholeshoe',
                'lab_ref_no'      => $lab,
                'vsp_code'        => $vsp,
                'mtl'             => $this->mtlOf($db, $vsp),
                'flexing_app'     => trim((string) $rows['flexing']), 'aging_app' => trim((string) $rows['aging']), 'hydrolysis_app' => trim((string) $rows['hydrolysis']),
                'washing_app'     => trim((string) $rows['washing']), 'uv_app' => trim((string) $rows['uv']),
                'bonding_app'     => '', 'pull_app' => '',
                'line'            => (string) ($m[4] ?? ''),
                'side'            => strtoupper((string) ($m[5] ?? '')),
                'production_date' => isset($m[1]) ? $m[1] . '-' . $m[2] . '-' . $m[3] : '',
                'tested_date'     => ErpMonitor::ymd($rows['tested_date'] ?: ($done['TimeCompleted'] ?? '')),
                'result'          => ErpMonitor::erpResult((string) $rows['overall_result']),
            ];
            $missing = [];
            foreach (['vsp_code' => 'VSP code', 'mtl' => 'MTL # of the VSP code (shoe list)', 'side' => 'L/R', 'production_date' => 'production date', 'tested_date' => 'tested date', 'result' => 'result (PASS / TOLERABLE / FAILED)'] as $k => $label) {
                if ($payload[$k] === '') {
                    $missing[] = $label;
                }
            }
            if ($payload['flexing_app'] . $payload['aging_app'] . $payload['hydrolysis_app'] . $payload['washing_app'] . $payload['uv_app'] === '') {
                $missing[] = 'at least one test TR no. (Flexing, Aging, Hydrolysis, Washing or UV Light)';
            }
            if ($missing) {
                $invalid[] = ['lab_ref_no' => $lab, 'missing' => $missing];
                continue;
            }
            if ($dry) {
                $preview[] = $payload;
                continue;
            }
            $db->table('tbl_erp_submissions')->insert([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'labref_id' => (int) $rows['id'], 'lab_ref_no' => $lab, 'production_date' => $payload['production_date'] ?: null,
                'mode' => $mode, 'batch_id' => $batch, 'status' => 'queued', 'message' => 'Waiting to be sent', 'requested_by_id' => (int) ($me['id'] ?? 0),
                'requested_by_name' => (string) ($me['name'] ?? $me['username'] ?? ''), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $subId = (int) $db->insertID();
            $db->table('tbl_dtr_unified_labrefs')->where('form_slug', 'wholeshoe')->where('lab_ref_no', $lab)->update(['erp_status' => 'Pending']);
            $toSend[] = ['id' => $subId, 'lab_ref_no' => $lab, 'by' => (string) ($me['name'] ?? $me['username'] ?? ''), 'payload' => $payload];
            $queued++;
        }
        $dispatch = $toSend ? $this->dispatch($toSend) : null;

        return $this->json(['ok' => true, 'queued' => $queued, 'skipped' => $skipped, 'invalid' => $invalid, 'batch' => $batch, 'dispatch' => $dispatch] + ($dry ? ['preview' => $preview] : []));
    }

    /** MTL # of a VSP / production code from the shoe list ('VSP26080269-T' falls back to 'VSP26080269') */
    private function mtlOf($db, string $vsp): string
    {
        if ($vsp === '') {
            return '';
        }
        foreach (array_unique([$vsp, preg_replace('/-.*$/', '', $vsp)]) as $code) {
            $r = $db->table('tbl_dtr_shoe_list')->select('MTL')->where('ProdCode', $code)->where('MTL <>', '')->get(1)->getRowArray();
            if ($r) {
                return trim((string) $r['MTL']);
            }
        }

        return '';
    }

    /** sends reports to the ERP agent; marks the ones it took. Returns what the page tells the user. */
    private function dispatch(array $tasks): array
    {
        $r = \App\Libraries\ErpAgent::submit($tasks);
        if (empty($r['ok'])) {
            $this->db()->table('tbl_erp_submissions')->whereIn('id', array_column($tasks, 'id'))->update(['message' => mb_substr('Waiting for the ERP PC: ' . ($r['error'] ?? 'no answer'), 0, 480)]);

            return ['ok' => false, 'error' => (string) ($r['error'] ?? 'The ERP PC did not answer.')];
        }
        $now = date('Y-m-d H:i:s');
        foreach ($tasks as $t) {
            $a = $r['accepted'][$t['id']] ?? null;
            if ($a) {
                $this->db()->table('tbl_erp_submissions')->where('id', $t['id'])->update(['dispatched_at' => $now, 'message' => 'Sent to the ERP PC (place ' . (int) ($a['position'] ?? 0) . ' in its queue)']);
            }
        }

        return ['ok' => true, 'queued' => (int) ($r['queued'] ?? 0)];
    }

    /** every few seconds: reports the agent has not taken yet (it was off, or unreachable) are offered again, oldest first */
    private function redispatch(): void
    {
        $cache = cache();
        if ($cache->get('erp_redispatch')) {
            return;
        }
        $cache->save('erp_redispatch', 1, 8);
        $db   = $this->db();
        $rows = $db->table('tbl_erp_submissions')->where('status', 'queued')->where('dispatched_at IS NULL', null, false)->orderBy('id')->limit(50)->get()->getResultArray();
        if (! $rows) {
            return;
        }
        $tasks = [];
        foreach ($rows as $r) {
            $pl = json_decode((string) $r['payload'], true);
            if (is_array($pl)) {
                $tasks[] = ['id' => (int) $r['id'], 'lab_ref_no' => (string) $r['lab_ref_no'], 'by' => (string) $r['requested_by_name'], 'payload' => $pl];
            }
        }
        if ($tasks) {
            $this->dispatch($tasks);
        }
    }

    /** GET lab-monitoring/agent-status : is the ERP PC reachable (shown as a badge on the page) */
    public function agentStatus()
    {
        $h = \App\Libraries\ErpAgent::health();
        $c = \App\Libraries\ErpAgent::config();

        return $this->json(['ok' => ! empty($h['ok']), 'online' => ! empty($h['ok']), 'where' => '.' . $c['target'] . ' (' . $c['host'] . ':' . $c['port'] . ')', 'target' => $c['target'], 'queued' => (int) ($h['queued'] ?? 0), 'working' => (string) ($h['working'] ?? ''),
            'paused' => ! empty($h['paused']), 'erp' => (string) ($h['erp'] ?? ''), 'save_in_erp' => $h['save_in_erp'] ?? null, 'error' => (string) ($h['error'] ?? '')]);
    }

    /** POST lab-monitoring/erp-status {id, status: OK|Submitted|Ongoing|Pending|''} */
    public function setErp()
    {
        if (! \App\Libraries\Roles::can((string) (service('auth')->user()['role'] ?? ''), 'erpfinish', 2)) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'Your role may view ERP Finish Monitoring but not send or change reports (Settings > Users & roles).']);
        }
        $p  = $this->request->getJSON(true) ?? [];
        $id = (int) ($p['id'] ?? 0);
        $s  = (string) ($p['status'] ?? '');
        if ($id <= 0 || ! in_array($s, ['', 'OK', 'Submitted', 'Ongoing', 'Pending'], true)) {
            return $this->json(['ok' => false, 'error' => 'Bad request.'], 422);
        }
        ErpMonitor::ensureColumns();
        $row = $this->db()->table('tbl_dtr_unified_labrefs')->select('lab_ref_no, form_slug')->where('id', $id)->get()->getRowArray();
        if ($row && $row['form_slug'] === 'wholeshoe') {
            $this->db()->table('tbl_dtr_unified_labrefs')->where('form_slug', 'wholeshoe')->where('lab_ref_no', $row['lab_ref_no'])->update(['erp_status' => $s]);
        } else {
            $this->db()->table('tbl_dtr_unified_labrefs')->where('id', $id)->update(['erp_status' => $s]);
        }

        return $this->json(['ok' => true]);
    }

    /**
     * POST lab-monitoring/row-save {id, kind, fields}: the Edit button of a row. Everything but the Line and the Lab Reference can be typed:
     * tested / production date, VSP code, the TR (Application No) numbers, InterLink status, result and ERP status. Only the fields sent are changed.
     */
    public function rowSave()
    {
        if (! \App\Libraries\Roles::can((string) (service('auth')->user()['role'] ?? ''), 'erpfinish', 2)) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'Your role may view ERP Finish Monitoring but not change it (Settings > Users & roles).']);
        }
        $p  = $this->request->getJSON(true) ?? [];
        $f  = is_array($p['fields'] ?? null) ? $p['fields'] : [];
        $id = (int) ($p['id'] ?? 0);
        $ws = (string) ($p['kind'] ?? '') === 'wholeshoe';
        $db = $this->db();
        ErpMonitor::ensureColumns();
        \App\Libraries\EtrfAppNos::ensureColumns();
        $u = $id > 0 ? $db->table('tbl_dtr_unified_labrefs')->where('id', $id)->get()->getRowArray() : null;
        if (! $u || ! in_array((string) $u['form_slug'], ['bonding', 'wholeshoe'], true)) {
            return $this->json(['ok' => false, 'error' => 'That line was not found.'], 404);
        }
        $lab   = (string) $u['lab_ref_no'];
        $slug  = (string) $u['form_slug'];
        $set   = [];
        $errs  = [];
        $done  = [];
        $isApp = static fn (string $v): bool => (bool) preg_match('/^A\d{6,}(-REV\d+)?$/', $v);
        $date  = static function (string $v): ?string {
            $v = trim($v);
            if ($v === '') {
                return '';
            }

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
        };
        // a TR column: the new number is the current one, the one it replaces goes to the earlier ones
        $setApp = function (string $col, string $new) use ($db, $slug, $lab): void {
            foreach ($db->table('tbl_dtr_unified_labrefs')->select("id, etrf_{$col}_app AS cur, etrf_{$col}_hist AS hist")->where('form_slug', $slug)->where('lab_ref_no', $lab)->get()->getResultArray() as $r) {
                $cur = trim((string) $r['cur']);
                if ($cur === $new) {
                    continue;
                }
                $hist = array_values(array_filter(array_map('trim', explode(';', (string) $r['hist'])), 'strlen'));
                if ($cur !== '' && ! in_array($cur, $hist, true)) {
                    $hist[] = $cur;
                }
                $db->table('tbl_dtr_unified_labrefs')->where('id', $r['id'])->update(["etrf_{$col}_app" => $new, "etrf_{$col}_hist" => implode(';', array_values(array_diff($hist, [$new])))]);
            }
        };

        if (array_key_exists('tested', $f)) {
            $d = $date((string) $f['tested']);
            if ($d === null) {
                $errs[] = 'Tested date: use a valid date.';
            } else {
                $set['tested_date'] = $d;
                if (! $ws && $db->tableExists('tbl_dtr_bonding_info')) {
                    $db->table('tbl_dtr_bonding_info')->where('LabRefNo', $lab)->update(['TestedDate' => $d]);
                }
                $done[] = 'tested date';
            }
        }
        if (array_key_exists('day', $f) && ! $ws) {
            $d = $date((string) $f['day']);
            if ($d === null || $d === '') {
                $errs[] = 'Production date: use a valid date.';
            } elseif ($db->table('tbl_dtr_bondingtracker')->where('LabRefNo', $lab)->countAllResults() > 0) {
                $db->table('tbl_dtr_bondingtracker')->where('LabRefNo', $lab)->update(['ProductionDate' => $d]);
                $done[] = 'production date';
            } else {
                $errs[] = 'Production date: this lab reference has no tracker line, so its date is the one in the lab reference.';
            }
        }
        if (array_key_exists('vsp', $f)) {
            $v = mb_substr(trim((string) $f['vsp']), 0, 191);
            $set['po_lot_no'] = $v;
            if (! $ws) {
                $db->table('tbl_dtr_bondingtracker')->where('LabRefNo', $lab)->update(['ProductionCode' => $v]);
                if ($db->tableExists('tbl_dtr_bonding_info')) {
                    $db->table('tbl_dtr_bonding_info')->where('LabRefNo', $lab)->update(['POLotNO' => $v]);
                }
            }
            $done[] = 'VSP code';
        }
        $cols = $ws ? ['flexing' => 'flexing', 'aging' => 'aging', 'hydrolysis' => 'hydrolysis', 'washing' => 'washing', 'uv' => 'uv'] : ['bonding' => 'bonding', 'pull' => 'pull'];
        foreach ($cols as $key => $col) {
            if (! array_key_exists($key, $f)) {
                continue;
            }
            $v = strtoupper(trim((string) $f[$key]));
            if ($v !== '' && ! $isApp($v)) {
                $errs[] = ucfirst($key) . ' TR no.: "' . $v . '" is not an Application No (like A006041469).';
                continue;
            }
            if ($v !== '' && $key === 'bonding') {
                \App\Libraries\EtrfAppNos::linkToLabRef($lab, 'VF eTRF-bonding', $v);   // also the TR of the bonding report
            } else {
                $setApp($col, $v);
            }
            $done[] = $key . ' TR';
        }
        if (array_key_exists('il', $f)) {
            $set['interlink_flag'] = ['ok' => 'MOK', 'pending' => 'MPENDING', 'bad' => 'MNO'][(string) $f['il']] ?? '';
            $done[] = 'InterLink status';
        }
        if (array_key_exists('result', $f)) {
            $r = ucfirst(strtolower(trim((string) $f['result'])));
            if (! in_array($r, ['', 'Pass', 'Fail', 'Tolerable'], true)) {
                $errs[] = 'Result: Pass, Tolerable or Fail.';
            } else {
                $set['overall_result'] = $r;
                if (! $ws && $db->tableExists('tbl_dtr_bonding_info')) {
                    $bid = $db->table('tbl_dtr_bonding_info')->selectMax('BondingTestID')->where('LabRefNo', $lab)->get()->getRow('BondingTestID');
                    if ($bid) {
                        $db->table('tbl_dtr_bonding_info')->where('BondingTestID', $bid)->update(['Result' => $r]);
                    }
                }
                $done[] = 'result';
            }
        }
        if (array_key_exists('erp', $f)) {
            $e = (string) $f['erp'];
            if (! in_array($e, ['', 'OK', 'Submitted', 'Ongoing', 'Pending'], true)) {
                $errs[] = 'ERP status: not a known status.';
            } else {
                $set['erp_status'] = $e;
                $done[] = 'ERP status';
            }
        }
        if ($set) {
            $db->table('tbl_dtr_unified_labrefs')->where('form_slug', $slug)->where('lab_ref_no', $lab)->update($set);   // a lab ref can have several index rows
        }
        if ($done) {
            \App\Libraries\ActivityLog::record('erpfinish', 'row-edit', $lab, 'edited by hand: ' . implode(', ', $done));
        }

        return $this->json(['ok' => $errs === [], 'error' => implode(' ', $errs), 'saved' => $done]);
    }
}
