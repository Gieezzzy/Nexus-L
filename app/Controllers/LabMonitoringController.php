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
    private const DAY_SQL = "COALESCE(t.ProductionDate, STR_TO_DATE(SUBSTRING(u.lab_ref_no, 4, 8), '%Y%m%d'))";

    private function base()
    {
        ErpMonitor::ensureColumns();

        return "FROM tbl_dtr_unified_labrefs u
            LEFT JOIN (SELECT LabRefNo, MAX(TrackerID) mid FROM tbl_dtr_bondingtracker GROUP BY LabRefNo) tm ON tm.LabRefNo COLLATE utf8mb4_general_ci = u.lab_ref_no COLLATE utf8mb4_general_ci
            LEFT JOIN tbl_dtr_bondingtracker t ON t.TrackerID = tm.mid
            LEFT JOIN (SELECT LabRefNo, MAX(BondingTestID) bid FROM tbl_dtr_bonding_info GROUP BY LabRefNo) bm ON bm.LabRefNo COLLATE utf8mb4_general_ci = u.lab_ref_no COLLATE utf8mb4_general_ci
            LEFT JOIN tbl_dtr_bonding_info b ON b.BondingTestID = bm.bid
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
                b.Result b_result, b.LabNotesComments b_notes, b.PossibleCause b_cause, COALESCE(b.TestedDate, u.tested_date) b_tested, b.POLotNO b_po, DATE_FORMAT({$day}, '%Y-%m-%d') pday
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
            if (in_array('bad', $st, true)) {
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
                'tested' => (string) ($r['b_tested'] ?? ''), 'day' => (string) $r['pday'],
                'bonding' => trim((string) $r['etrf_bonding_app']), 'bonding_hist' => trim((string) $r['etrf_bonding_hist']),
                'pull' => trim((string) $r['etrf_pull_app']), 'pull_hist' => trim((string) $r['etrf_pull_hist']),
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
                'pull_app'        => trim((string) $r['etrf_pull_app']),
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
        $this->db()->table('tbl_dtr_unified_labrefs')->where('id', $id)->update(['erp_status' => $s]);

        return $this->json(['ok' => true]);
    }
}
