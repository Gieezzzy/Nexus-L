<?php

namespace App\Controllers;

use App\Libraries\SynologyChat;
use App\Libraries\SystemSettings;

/**
 * Hands a report to the NEXUS-L InterLink background service (tools/interlink-service, a C# program with an invisible browser) and
 * takes its answer back. Nothing is opened on the user's computer: the user presses Send, NEXUS-L prepares the payload, the service
 * does Add / Edit in InterLink with its own (shared) account, and the user is told by a toast in NEXUS-L and a Synology message.
 *
 * Settings (.env):  interlinkService.url = 'http://127.0.0.1:5077'   interlinkService.key = '<long secret, same as the service's Service:ApiKey>'
 */
class InterlinkServiceController extends BaseController
{
    private function db()
    {
        return \Config\Database::connect();
    }

    private function baseUrl(): string
    {
        return rtrim((string) SystemSettings::get('interlink.service.url', ''), '/');
    }

    private function key(): string
    {
        return (string) SystemSettings::get('interlink.service.key', '');
    }

    private function enabled(): bool
    {
        return SystemSettings::bool('interlink.service.enabled', true) && $this->baseUrl() !== '' && strlen($this->key()) >= 16;
    }

    private function json(array $data, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($data + ['csrf' => csrf_hash()]);
    }

    private function keyOk(): bool
    {
        $got = (string) $this->request->getHeaderLine('X-Service-Key');

        return $this->enabled() && $got !== '' && hash_equals($this->key(), $got);
    }

    private function table(): void
    {
        $this->db()->query('CREATE TABLE IF NOT EXISTS tbl_interlink_service_jobs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_uid VARCHAR(40) NOT NULL,
            application_no VARCHAR(30) NOT NULL,
            lab_ref_no VARCHAR(60) NOT NULL DEFAULT "",
            report_no VARCHAR(60) NOT NULL DEFAULT "",
            mode VARCHAR(8) NOT NULL DEFAULT "edit",
            user_id INT UNSIGNED NOT NULL DEFAULT 0,
            user_name VARCHAR(80) NOT NULL DEFAULT "",
            status VARCHAR(20) NOT NULL DEFAULT "queued",
            message VARCHAR(500) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            UNIQUE KEY uq_job (job_uid),
            KEY ix_user (user_id, finished_at)
        ) DEFAULT CHARSET=utf8mb4');
        if (! $this->db()->fieldExists('started_at', 'tbl_interlink_service_jobs')) {
            $this->db()->query('ALTER TABLE tbl_interlink_service_jobs ADD COLUMN started_at DATETIME NULL AFTER created_at');
        }
    }

    /** the Synology Chat message goes to one person at a time (Synology rejects a list of several recipients) */
    private function synology(int $userId, string $text): void
    {
        $row = $this->db()->table('tbl_users')->select('SynologyID')->where('UserID', $userId)->get()->getRowArray();
        $id  = (int) ($row['SynologyID'] ?? 0);
        if ($id <= 0) {
            return;
        }
        try {
            (new SynologyChat())->send($id, $text);
        } catch (\Throwable $e) {
            log_message('error', 'InterLink service Synology notice failed: ' . $e->getMessage());
        }
    }

    /** GET interlink/service/status : is the service set up, reachable, signed in to InterLink? (signed-in users) */
    public function status()
    {
        if (! $this->enabled()) {
            return $this->json(['ok' => true, 'enabled' => false]);
        }
        try {
            $res = service('curlrequest')->get($this->baseUrl() . '/health', ['timeout' => 3, 'http_errors' => false]);
            $h   = json_decode((string) $res->getBody(), true) ?: [];

            return $this->json(['ok' => true, 'enabled' => true, 'online' => ! empty($h['ok']), 'signedIn' => ! empty($h['signedIn']), 'waiting' => (int) ($h['waiting'] ?? 0)]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => true, 'enabled' => true, 'online' => false]);
        }
    }

    /**
     * POST interlink/service/submit {application_no, lab_ref_no, fields?, mode: add|edit, pdf_name?}
     * Only a report that a Manager / Supervisor approved and signed goes out; the service asks NEXUS-L again right before it presses Save.
     */
    public function submit()
    {
        if (! $this->enabled()) {
            return $this->json(['ok' => false, 'code' => 'not_configured', 'error' => 'The InterLink background service is not set up on this server.'], 503);
        }
        $p      = $this->request->getJSON(true) ?? [];
        $appNo  = trim((string) ($p['application_no'] ?? ''));
        $ref    = trim((string) ($p['lab_ref_no'] ?? ''));
        $fields = is_array($p['fields'] ?? null) ? $p['fields'] : [];
        $mode   = ($p['mode'] ?? 'edit') === 'add' ? 'add' : 'edit';

        $r = (new InterlinkController())->fillData(['application_no' => $appNo, 'lab_ref_no' => $ref, 'fields' => $fields]);
        if ($r['code'] !== 200) {
            return $this->json($r['body'], $r['code']);
        }
        $data   = $r['body'];
        $labRef = (string) ($data['lab']['lab_ref_no'] ?? $ref);

        // the signed report of THIS application and Lab Reference
        $q = $this->db()->table('tbl_dtr_report_approvals')->where('lab_ref_no', $labRef)->where('status', 'approved');
        $q->groupStart()->where('application_no', $appNo)->orWhere('application_no', '')->orWhere('application_no IS NULL')->groupEnd();
        $a      = $q->orderBy('id', 'DESC')->get()->getRowArray();
        $signed = $a ? WRITEPATH . 'approvals/' . ($a['file_key'] ?: $a['token']) . '_signed.pdf' : '';
        if ($signed === '' || ! is_file($signed)) {
            return $this->json(['ok' => false, 'error' => "{$labRef} has no approved, signed report for {$appNo}. Generate it on the Upload Form and request approval (QR) first."], 409);
        }
        $pdfName  = preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', '_', trim((string) ($p['pdf_name'] ?? ''))) ?: $appNo . '.pdf';
        $reportNo = trim((string) ($fields['report_no'] ?? '')) ?: (string) ($this->db()->table('tbl_dtr_unified_labrefs')->select('tr_no')->where('lab_ref_no', $labRef)->get()->getRowArray()['tr_no'] ?? '');

        $me  = (array) service('auth')->user();
        $uid = bin2hex(random_bytes(12));
        $this->table();
        // one application is submitted once at a time: a second click, or a colleague, does not queue it again
        $busy = $this->db()->table('tbl_interlink_service_jobs')->where('application_no', $appNo)->whereIn('status', ['queued', 'running'])->where('created_at >', date('Y-m-d H:i:s', time() - 1800))->orderBy('id')->get()->getRowArray();
        if ($busy) {
            return $this->json(['ok' => false, 'code' => 'already_queued', 'error' => "{$appNo} is already " . ($busy['status'] === 'running' ? 'being submitted' : 'waiting in the queue') . ($busy['user_name'] !== '' ? " (sent by {$busy['user_name']})" : '') . '.'], 409);
        }
        $this->db()->table('tbl_interlink_service_jobs')->insert([
            'job_uid' => $uid, 'application_no' => $appNo, 'lab_ref_no' => $labRef, 'report_no' => $reportNo, 'mode' => $mode,
            'user_id' => (int) ($me['id'] ?? 0), 'user_name' => (string) ($me['name'] ?? $me['username'] ?? ''), 'status' => 'queued', 'created_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $res  = service('curlrequest')->post($this->baseUrl() . '/jobs', [
                'headers'     => ['X-Service-Key' => $this->key()],
                'json'        => [
                    'job_uid' => $uid, 'application_no' => $appNo, 'lab_ref_no' => $labRef, 'report_no' => $reportNo, 'mode' => $mode,
                    'user_name' => (string) ($me['name'] ?? ''),
                    'verify_url' => site_url('interlink/service/verify') . '?lab_ref=' . rawurlencode($labRef) . '&application_no=' . rawurlencode($appNo),
                    'callback_url' => site_url('interlink/service/callback'),
                    'job' => [
                        'application_no' => $appNo, 'target_url' => $data['target_url'], 'controls' => $data['controls'], 'tables' => $data['tables'],
                        'pdf' => ['name' => $pdfName, 'b64' => base64_encode((string) file_get_contents($signed))],
                    ],
                ],
                'timeout'     => 60,
                'http_errors' => false,
            ]);
            $body = json_decode((string) $res->getBody(), true) ?: [];
            if ($res->getStatusCode() === 409 && ($body['code'] ?? '') === 'already_queued') {
                $this->db()->table('tbl_interlink_service_jobs')->where('job_uid', $uid)->delete();

                return $this->json(['ok' => false, 'code' => 'already_queued', 'error' => (string) ($body['error'] ?? "{$appNo} is already in the queue.")], 409);
            }
            if ($res->getStatusCode() !== 200 || empty($body['ok'])) {
                throw new \RuntimeException((string) ($body['error'] ?? 'the service answered ' . $res->getStatusCode()));
            }
        } catch (\Throwable $e) {
            $this->db()->table('tbl_interlink_service_jobs')->where('job_uid', $uid)->update(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 480), 'finished_at' => date('Y-m-d H:i:s')]);

            return $this->json(['ok' => false, 'code' => 'service_unreachable', 'error' => 'The InterLink background service could not take the report: ' . $e->getMessage()], 502);
        }

        return $this->json(['ok' => true, 'job_uid' => $uid, 'ahead' => (int) ($body['ahead'] ?? 0), 'waiting' => (int) ($body['waiting'] ?? 0), 'lab_ref_no' => $labRef, 'report_no' => $reportNo]);
    }

    /** POST interlink/service/callback (service key): the result of a job. The user gets a Synology message; the web feed shows the toast. */
    public function callback()
    {
        if (! $this->keyOk()) {
            return $this->json(['ok' => false, 'error' => 'Wrong or missing service key.'], 401);
        }
        $p   = $this->request->getJSON(true) ?? [];
        $uid = (string) ($p['job_uid'] ?? '');
        $this->table();
        $job = $this->db()->table('tbl_interlink_service_jobs')->where('job_uid', $uid)->get()->getRowArray();
        if (! $job) {
            return $this->json(['ok' => false, 'error' => 'Unknown job.'], 404);
        }
        $state = in_array($p['state'] ?? '', ['running', 'done', 'failed', 'signin-required'], true) ? $p['state'] : 'failed';
        $msg   = mb_substr((string) ($p['message'] ?? ''), 0, 480);
        if ($state === 'running') {
            // the service took this report up: the user is told it has started (no Synology message for that)
            $this->db()->table('tbl_interlink_service_jobs')->where('id', $job['id'])->where('finished_at IS NULL')->update(['status' => 'running', 'started_at' => date('Y-m-d H:i:s')]);

            return $this->json(['ok' => true]);
        }
        $this->db()->table('tbl_interlink_service_jobs')->where('id', $job['id'])->update(['status' => $state, 'message' => $msg, 'finished_at' => date('Y-m-d H:i:s')]);

        $lab = $job['lab_ref_no'];
        $rep = $job['report_no'];
        if ($state === 'done') {
            $this->synology((int) $job['user_id'], "Labreference: {$lab}\nReportNO: {$rep}\nsubmitted to InterLink");
        } else {
            $this->synology((int) $job['user_id'], "Labreference: {$lab}\nReportNO: {$rep}\nwas NOT submitted to InterLink: {$msg}");
        }

        return $this->json(['ok' => true]);
    }

    /** GET interlink/service/verify (service key): is the report still approved and signed? Same answer as dtr/approval/verify. */
    public function verify()
    {
        if (! $this->keyOk()) {
            return $this->json(['ok' => false, 'error' => 'Wrong or missing service key.'], 401);
        }
        $lab = trim((string) $this->request->getGet('lab_ref'));
        $app = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $this->request->getGet('application_no'));
        $q   = $this->db()->table('tbl_dtr_report_approvals')->where('lab_ref_no', $lab)->where('status', 'approved');
        $q->groupStart()->where('application_no', $app)->orWhere('application_no', '')->orWhere('application_no IS NULL')->groupEnd();
        $a      = $q->orderBy('id', 'DESC')->get()->getRowArray();
        $signed = $a ? WRITEPATH . 'approvals/' . ($a['file_key'] ?: $a['token']) . '_signed.pdf' : '';

        return $this->json($signed !== '' && is_file($signed)
            ? ['ok' => true, 'approved' => true, 'sha256' => hash_file('sha256', $signed), 'by' => $a['decided_name'], 'at' => $a['decided_at']]
            : ['ok' => true, 'approved' => false]);
    }

    /** GET interlink/service/feed?after= : finished jobs of the signed-in user, for the toast shown wherever they are in NEXUS-L */
    public function feed()
    {
        $uid    = (int) ((array) service('auth')->user())['id'];
        $after  = (string) $this->request->getGet('after');
        $out    = ['ok' => true, 'events' => [], 'running' => 0, 'cursor' => date('Y-m-d H:i:s')];
        $this->table();
        $out['running'] = $this->db()->table('tbl_interlink_service_jobs')->where('user_id', $uid)->whereIn('status', ['queued', 'running'])->where('created_at >', date('Y-m-d H:i:s', time() - 900))->countAllResults();
        if ($after !== '') {
            foreach ($this->db()->table('tbl_interlink_service_jobs')->where('user_id', $uid)->where('started_at >', $after)->orderBy('id')->get(20)->getResultArray() as $j) {
                $out['events'][] = ['kind' => 'info', 'text' => "{$j['application_no']} ({$j['lab_ref_no']}) is now being submitted to InterLink...", 'application_no' => $j['application_no']];
            }
            foreach ($this->db()->table('tbl_interlink_service_jobs')->where('user_id', $uid)->where('finished_at >', $after)->orderBy('id')->get(20)->getResultArray() as $j) {
                $out['events'][] = $j['status'] === 'done'
                    ? ['kind' => 'success', 'text' => "Labreference: {$j['lab_ref_no']} · ReportNO: {$j['report_no']} · submitted to InterLink", 'application_no' => $j['application_no']]
                    : ['kind' => 'error', 'text' => "{$j['application_no']} ({$j['lab_ref_no']}) was NOT submitted to InterLink: {$j['message']}", 'application_no' => $j['application_no']];
            }
        }

        return $this->json($out);
    }
}
