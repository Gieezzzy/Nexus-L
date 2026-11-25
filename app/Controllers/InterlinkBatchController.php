<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Batch Approval area of the InterLink page: every Application No. we have next to the Lab Reference chosen for it and the state of its
 * report approval. The report itself is made in the browser (like the Upload Form's Generate Report) and sent for approval one by one with
 * dtr/approval/request; this controller only lists and reports the live status.
 */
class InterlinkBatchController extends BaseController
{
    private function approvalMap(array $apps): array
    {
        if (! $apps) {
            return [];
        }
        $out = [];
        $rows = \Config\Database::connect()->table('tbl_dtr_report_approvals')->whereIn('application_no', $apps)->where('status !=', 'superseded')->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($rows as $a) {
            $live = $a['status'] === 'pending' && ! empty($a['expires_at']) && strtotime((string) $a['expires_at']) > time();
            $out[(string) $a['application_no']] = [   // the newest one of an application wins
                'status' => $a['status'] === 'pending' && ! $live ? 'expired' : $a['status'], 'lab_ref_no' => $a['lab_ref_no'],
                'requested_name' => $a['requested_name'], 'requested_at' => $a['requested_at'],
                'decided_name' => $a['decided_name'], 'decided_at' => $a['decided_at'], 'remarks' => $a['remarks'],
                'file' => site_url('dtr/approval/file/' . ($a['file_key'] ?: $a['token'])),
            ];
        }

        return $out;
    }

    /** GET interlink/batch/data?q= : the applications (newest first) with their Lab Reference guess and approval state */
    public function data()
    {
        InterlinkMirrorController::tables();
        $db = \Config\Database::connect();
        $q  = trim((string) $this->request->getGet('q'));
        $b  = $db->table('tbl_interlink_rows')->select('application_no, report_no, style_no, vendor_name, test_packages, job_status, overall_result');
        if ($q !== '') {
            $b->groupStart()->like('application_no', $q)->orLike('report_no', $q)->orLike('style_no', $q)->orLike('vendor_name', $q)->groupEnd();
        }
        // type filter on the Test Package(s) text: Whole Shoe / Bonding / Pull Test; Material is everything else
        $type = (string) $this->request->getGet('type');
        $kw   = ['wholeshoe' => 'Whole Shoe', 'bonding' => 'Bonding', 'pull' => 'Pull Strength'];
        if (isset($kw[$type])) {
            $b->like('test_packages', $kw[$type]);
        } elseif ($type === 'material') {
            $b->notLike('test_packages', 'Whole Shoe')->notLike('test_packages', 'Bonding')->notLike('test_packages', 'Pull Strength');
        }
        $rows = $b->orderBy('application_no', 'DESC')->limit(500)->get()->getResultArray();
        $apps = array_column($rows, 'application_no');
        $appr = $this->approvalMap($apps);
        // the Lab Reference whose TR No. is the application, when no report was made for it yet
        $byTr = [];
        if ($apps) {
            foreach ($db->table('tbl_dtr_unified_labrefs')->select('lab_ref_no, tr_no')->whereIn('tr_no', $apps)->orderBy('id', 'ASC')->get()->getResultArray() as $l) {
                $byTr[(string) $l['tr_no']] = $l['lab_ref_no'];
            }
        }
        foreach ($rows as &$r) {
            $a = $appr[$r['application_no']] ?? null;
            $r['lab_ref_no'] = $a['lab_ref_no'] ?? ($byTr[$r['application_no']] ?? '');
            $r['approval']   = $a;
        }
        unset($r);

        return $this->response->setJSON(['ok' => true, 'rows' => $rows, 'csrf' => csrf_hash()]);
    }

    /** GET interlink/batch/status?apps=A1,A2 : the live approval state of these applications */
    public function status()
    {
        $apps = array_values(array_filter(array_map(static fn ($a) => preg_replace('/[^A-Za-z0-9_-]/', '', trim($a)), explode(',', (string) $this->request->getGet('apps')))));

        return $this->response->setJSON(['ok' => true, 'approvals' => (object) $this->approvalMap(array_slice($apps, 0, 500)), 'csrf' => csrf_hash()]);
    }
}
