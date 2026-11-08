<?php

namespace App\Controllers;

use App\Libraries\ErpMonitor;
use App\Libraries\SystemSettings;

/** Where the ERP agent (on the ERP PC) reports what it did with each report. No sign-in: the shared token is the key. */
class ErpAgentController extends BaseController
{
    /** POST erp-agent/callback {id, status: sending|submitted|failed|cancelled|rehearsed, message, lab_ref_no} with header X-Agent-Token */
    public function callback()
    {
        $sent = (string) $this->request->getHeaderLine('X-Agent-Token');
        $ok   = false;
        foreach (\App\Libraries\ErpAgent::tokens() as $t) {   // the token of either ERP PC
            $ok = $ok || hash_equals($t, $sent);
        }
        if (! $ok) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'error' => 'Wrong token']);
        }
        $p      = $this->request->getJSON(true) ?? [];
        $id     = (int) ($p['id'] ?? 0);
        $status = strtolower((string) ($p['status'] ?? ''));
        $msg    = mb_substr((string) ($p['message'] ?? ''), 0, 480);
        if ($id <= 0 || ! in_array($status, ['sending', 'submitted', 'failed', 'cancelled', 'rehearsed'], true)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Bad request']);
        }
        ErpMonitor::ensureColumns();
        ErpMonitor::ensureLogTable();
        $db  = \Config\Database::connect();
        $row = $db->table('tbl_erp_submissions')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Unknown submission']);
        }
        $now  = date('Y-m-d H:i:s');
        $upd  = ['message' => $msg, 'updated_at' => $now, 'status' => $status === 'cancelled' ? 'failed' : $status];
        if ($status === 'submitted') {
            $upd['submitted_at'] = $now;
        }
        $db->table('tbl_erp_submissions')->where('id', $id)->update($upd);
        // the ERP status of the report in the monitoring view
        if ($row['labref_id']) {
            $erp = $status === 'submitted' ? 'Submitted' : ($status === 'sending' ? 'Ongoing' : '');
            $db->table('tbl_dtr_unified_labrefs')->where('id', (int) $row['labref_id'])->update(['erp_status' => $erp]);
        }

        return $this->response->setJSON(['ok' => true]);
    }
}
