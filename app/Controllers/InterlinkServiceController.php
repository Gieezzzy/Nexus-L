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
        return SystemSettings::serviceUrl();
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
        if (! $this->db()->fieldExists('result_json', 'tbl_interlink_service_jobs')) {
            $this->db()->query('ALTER TABLE tbl_interlink_service_jobs ADD COLUMN result_json MEDIUMTEXT NULL');
        }
        $this->db()->query('ALTER TABLE tbl_interlink_service_jobs MODIFY mode VARCHAR(20) NOT NULL DEFAULT "edit"');
        if (! $this->db()->fieldExists('started_at', 'tbl_interlink_service_jobs')) {
            $this->db()->query('ALTER TABLE tbl_interlink_service_jobs ADD COLUMN started_at DATETIME NULL AFTER created_at');
        }
        if (! $this->db()->fieldExists('etrf_request_id', 'tbl_interlink_service_jobs')) {
            $this->db()->query('ALTER TABLE tbl_interlink_service_jobs MODIFY application_no VARCHAR(60) NOT NULL, MODIFY mode VARCHAR(20) NOT NULL DEFAULT "edit", ADD COLUMN etrf_request_id INT UNSIGNED NULL AFTER mode, ADD COLUMN etrf_type VARCHAR(50) NOT NULL DEFAULT "" AFTER etrf_request_id');
        }
        (new InterlinkController())->ensureEtrfTables();
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

            return $this->json([
                'ok'       => true,
                'enabled'  => true,
                'online'   => ! empty($h['ok']),
                'signedIn' => ! empty($h['signedIn']),
                'waiting'  => (int) ($h['waiting'] ?? 0),
                'accounts' => $h['accounts'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => true, 'enabled' => true, 'online' => false]);
        }
    }

    /**
     * POST interlink/service/signin {account: "dtp_qcasst@dtshoes.com" | "dtp_lab@dtshoes.com"}
     * Opens the InterLink sign-in browser window on the NexusInterlinkService host for the specified account.
     */
    public function signin()
    {
        if (! $this->enabled()) {
            return $this->json(['ok' => false, 'code' => 'not_configured', 'error' => 'The InterLink background service is not set up on this server.'], 503);
        }
        $p       = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $account = strtolower(trim((string) ($p['account'] ?? 'dtp_qcasst@dtshoes.com')));
        if ($account !== 'dtp_lab@dtshoes.com') {
            $account = 'dtp_qcasst@dtshoes.com';
        }
        try {
            $res = service('curlrequest')->post($this->baseUrl() . '/signin', [
                'headers'     => ['X-Service-Key' => $this->key()],
                'json'        => ['account' => $account],
                'timeout'     => 10,
                'http_errors' => false,
            ]);
            $body = json_decode((string) $res->getBody(), true) ?: [];
            if ($res->getStatusCode() !== 200 || empty($body['ok'])) {
                return $this->json(['ok' => false, 'error' => (string) ($body['error'] ?? 'Could not open sign-in browser on the service host.')], 502);
            }

            return $this->json(['ok' => true, 'account' => $account, 'message' => (string) ($body['message'] ?? "Sign-in browser opened for {$account}.")]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => 'Could not reach NexusInterlinkService: ' . $e->getMessage()], 502);
        }
    }

    /**
     * POST interlink/service/submit {application_no, lab_ref_no, fields?, mode: add|edit|etrf, etrf_type?, pdf_name?}
     * Only a report that a Manager / Supervisor approved and signed goes out; the service asks NEXUS-L again right before it presses Save.
     * When mode === 'etrf' or etrf_type is present, routes to submitEtrf() to submit a new eTRF request on Home.aspx -> Submit a Request.
     */
    public function submit()
    {
        $p = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        if (($p['mode'] ?? '') === 'etrf' || ! empty($p['etrf_type'])) {
            return $this->submitEtrf();
        }
        if (! $this->enabled()) {
            return $this->json(['ok' => false, 'code' => 'not_configured', 'error' => 'The InterLink background service is not set up on this server.'], 503);
        }
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
        // the signed report - or, when the user changed the attached file of this approved report, the copy of THEIR file kept in NEXUS-L
        \App\Libraries\ApprovalFiles::ensureColumns();
        $pick   = $a ? \App\Libraries\ApprovalFiles::chosen($a) : ['path' => '', 'name' => '', 'replaced' => false];
        $signed = $pick['path'];
        if ($signed === '' || ! is_file($signed)) {
            return $this->json(['ok' => false, 'error' => "{$labRef} has no approved, signed report for {$appNo}. Generate it on the Upload Form and request approval (QR) first."], 409);
        }
        $pdfName  = preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', '_', trim((string) ($p['pdf_name'] ?? ''))) ?: ($pick['replaced'] && $pick['name'] !== '' ? $pick['name'] : $appNo . '.pdf');
        $reportNo = trim((string) ($fields['report_no'] ?? '')) ?: (string) ($this->db()->table('tbl_dtr_unified_labrefs')->select('tr_no')->where('lab_ref_no', $labRef)->get()->getRowArray()['tr_no'] ?? '');

        // InterLink does not accept one Report No. twice ("A report with the entered report number has already exists!"): say so before queueing
        if ($reportNo !== '') {
            $this->table();
            $used = $this->db()->table('tbl_interlink_reports')->select('application_no')->where('report_no', $reportNo)->where('application_no !=', $appNo)->get()->getRowArray();
            $used = $used['application_no'] ?? ($this->db()->table('tbl_interlink_service_jobs')->select('application_no')->where('report_no', $reportNo)->where('status', 'done')->where('application_no !=', $appNo)->get()->getRowArray()['application_no'] ?? '');
            if ($used !== '') {
                return $this->json(['ok' => false, 'code' => 'report_no_taken', 'error' => "Report No. {$reportNo} is already used in InterLink by {$used}, and InterLink does not accept the same Report No. twice. Change the Report No. on the Upload Form (then Save) before sending {$appNo}."], 409);
            }
        }

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

    /**
     * POST interlink/service/etrf
     * Submits a new eTRF request on InterLink 2.0 (Home.aspx -> Submit a Request -> VF eTRF - Material / Product / PULLTESTTemplate / bonding / Whole Shoe).
     */
    public function submitEtrf()
    {
        if (! $this->enabled()) {
            return $this->json(['ok' => false, 'code' => 'not_configured', 'error' => 'The InterLink background service is not set up on this server.'], 503);
        }
        $p = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $r = (new InterlinkController())->etrfData($p);
        if ($r['code'] !== 200) {
            return $this->json($r['body'], $r['code']);
        }
        $data      = $r['body'];
        if (! empty($data['missing'])) {                       // pre-flight: nothing incomplete is queued, and nothing is filled with a made-up value
            return $this->json(['ok' => false, 'code' => 'etrf_incomplete', 'missing' => $data['missing'],
                'error' => 'The eTRF (' . ($data['template_label'] ?? 'request') . ') is missing: ' . implode(', ', $data['missing']) . '. Fill these in and send again.'], 422);
        }
        $etrfType  = (string) ($data['etrf_type'] ?? 'eTRF-PULL');
        $labRef    = (string) ($data['lab_ref_no'] ?? '');
        $action    = (string) ($data['action'] ?? 'submit');
        $jobTag    = $labRef !== '' ? ('ETRF:' . $labRef) : ('ETRF:' . $etrfType . ':' . substr(bin2hex(random_bytes(3)), 0, 6));

        $me  = (array) service('auth')->user();
        $uid = bin2hex(random_bytes(12));
        $this->table();

        if ($labRef !== '') {
            $busy = $this->db()->table('tbl_interlink_service_jobs')
                ->where('lab_ref_no', $labRef)
                ->where('mode', 'etrf')
                ->whereIn('status', ['queued', 'running'])
                ->where('created_at >', date('Y-m-d H:i:s', time() - 900))
                ->orderBy('id')
                ->get()
                ->getRowArray();
            if ($busy) {
                return $this->json(['ok' => false, 'code' => 'already_queued', 'error' => "eTRF for {$labRef} is already " . ($busy['status'] === 'running' ? 'being submitted' : 'waiting in the queue') . '.'], 409);
            }
        }

        $etrfReqId = (new InterlinkController())->saveEtrfRequestRecord($data, 'service', 'queued', $uid);
        $this->db()->table('tbl_interlink_service_jobs')->insert([
            'job_uid'         => $uid,
            'application_no'  => mb_substr($jobTag, 0, 60),
            'lab_ref_no'      => $labRef,
            'report_no'       => $etrfType,
            'mode'            => 'etrf',
            'etrf_request_id' => $etrfReqId,
            'etrf_type'       => $etrfType,
            'user_id'         => (int) ($me['id'] ?? 0),
            'user_name'       => (string) ($me['name'] ?? $me['username'] ?? ''),
            'status'          => 'queued',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        try {
            $accountEmail = (string) ($data['account_email'] ?? ($etrfType === 'eTRF-PULL' ? 'dtp_lab@dtshoes.com' : 'dtp_qcasst@dtshoes.com'));
            $res = service('curlrequest')->post($this->baseUrl() . '/jobs', [
                'headers'     => ['X-Service-Key' => $this->key()],
                'json'        => [
                    'job_uid'        => $uid,
                    'application_no' => mb_substr($jobTag, 0, 30),
                    'lab_ref_no'     => $labRef,
                    'report_no'      => $etrfType,
                    'mode'           => 'etrf',
                    'account'        => $accountEmail,
                    'user_name'      => (string) ($me['name'] ?? ''),
                    'verify_url'     => '',
                    'callback_url'   => site_url('interlink/service/callback'),
                    'job'            => [
                        'application_no' => mb_substr($jobTag, 0, 30),
                        'target_url'     => $data['home_url'],
                        'controls'       => $data['fields'],
                        'tables'         => ['samples' => [], 'items' => [], 'readings' => []],
                        'etrf'           => [
                            'etrf_type'      => $data['etrf_type'],
                            'template_label' => $data['template_label'],
                            'sample_type'    => $data['sample_type'],
                            'account_email'  => $accountEmail,
                            'menu_targets'   => $data['menu_targets'],
                            'action'         => $action,
                            'fields'         => $data['fields'],
                            'rows'           => $data['rows'],
                            'summary'        => $data['summary'],
                        ],
                    ],
                ],
                'timeout'     => 60,
                'http_errors' => false,
            ]);
            $body = json_decode((string) $res->getBody(), true) ?: [];
            if ($res->getStatusCode() === 409 && ($body['code'] ?? '') === 'already_queued') {
                $this->db()->table('tbl_interlink_service_jobs')->where('job_uid', $uid)->delete();

                return $this->json(['ok' => false, 'code' => 'already_queued', 'error' => (string) ($body['error'] ?? "eTRF {$jobTag} is already in the queue.")], 409);
            }
            if ($res->getStatusCode() !== 200 || empty($body['ok'])) {
                throw new \RuntimeException((string) ($body['error'] ?? 'the service answered ' . $res->getStatusCode()));
            }
        } catch (\Throwable $e) {
            $this->db()->table('tbl_interlink_service_jobs')->where('job_uid', $uid)->update([
                'status'      => 'failed',
                'message'     => mb_substr($e->getMessage(), 0, 480),
                'finished_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db()->table('tbl_interlink_etrf_requests')->where('job_uid', $uid)->update([
                'status'         => 'failed',
                'status_message' => mb_substr($e->getMessage(), 0, 480),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

            return $this->json(['ok' => false, 'code' => 'service_unreachable', 'error' => 'The InterLink background service could not take the eTRF request: ' . $e->getMessage()], 502);
        }

        return $this->json([
            'ok'             => true,
            'job_uid'        => $uid,
            'mode'           => 'etrf',
            'etrf_type'      => $etrfType,
            'template_label' => $data['template_label'],
            'lab_ref_no'     => $labRef,
            'ahead'          => (int) ($body['ahead'] ?? 0),
            'waiting'        => (int) ($body['waiting'] ?? 0),
        ]);
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
        $state  = in_array($p['state'] ?? '', ['running', 'done', 'failed', 'signin-required'], true) ? $p['state'] : 'failed';
        $msg    = mb_substr((string) ($p['message'] ?? ''), 0, 480);
        $newApp = trim((string) ($p['application_no'] ?? ''));
        if (! preg_match('/^A\d{6,}$/i', $newApp) && preg_match('/\b(A\d{7,12})\b/i', $newApp . ' ' . $msg, $am)) {
            $newApp = strtoupper($am[1]);
        }
        if ($state === 'running') {
            // the service took this report up: the user is told it has started (no Synology message for that)
            $this->db()->table('tbl_interlink_service_jobs')->where('id', $job['id'])->where('finished_at IS NULL')->update(['status' => 'running', 'started_at' => date('Y-m-d H:i:s')]);
            $this->db()->table('tbl_interlink_etrf_requests')->where('job_uid', $uid)->update(['status' => 'running', 'status_message' => $msg ?: 'Submitting eTRF in InterLink...', 'updated_at' => date('Y-m-d H:i:s')]);
            if (($job['mode'] ?? '') === 'vf_copy') {
                (new \App\Libraries\VfTestRequestService())->handleCopyCallback($job, $p);
            }

            return $this->json(['ok' => true]);
        }
        $upd = ['status' => $state, 'message' => $msg, 'finished_at' => date('Y-m-d H:i:s')];
        if ($newApp !== '' && preg_match('/^A\d{6,}$/i', $newApp)) {
            $upd['application_no'] = strtoupper($newApp);
        }
        if (in_array(($job['mode'] ?? ''), ['grid_html', 'vf_grid'], true) && $state === 'done' && ! empty($p['result_json'])) {
            $upd['result_json'] = (string) $p['result_json'];
        }
        $this->db()->table('tbl_interlink_service_jobs')->where('id', $job['id'])->update($upd);
        if (($job['mode'] ?? '') === 'vf_grid') {
            // the saved copy of this list page (VF - Test Request) is filled only by the job that was claimed for it
            try {
                \App\Libraries\VfGridCache::ensure($this->db());
                $decoded = $state === 'done' && ! empty($p['result_json']) ? json_decode((string) $p['result_json'], true) : null;
                \App\Libraries\VfGridCache::finish($this->db(), (string) $uid, (string) $state, (string) $msg, is_array($decoded) ? $decoded : null);
            } catch (\Throwable $e) {
                log_message('error', 'VfGridCache finish failed: ' . $e->getMessage());
            }
        }

        $etrfRow = $this->db()->table('tbl_interlink_etrf_requests')->where('job_uid', $uid)->get()->getRowArray();
        if ($etrfRow) {
            $etrfUpd = [
                'status'         => $state === 'done' ? (($etrfRow['action_mode'] ?? 'submit') === 'save' ? 'saved_draft' : 'submitted') : 'failed',
                'status_message' => $msg ?: ($state === 'done' ? 'Submitted to InterLink' : 'Failed'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ];
            if ($state === 'done') {
                $etrfUpd['submitted_at'] = date('Y-m-d H:i:s');
            }
            if (! empty($upd['application_no'])) {
                $etrfUpd['application_no'] = $upd['application_no'];
            }
            $this->db()->table('tbl_interlink_etrf_requests')->where('id', $etrfRow['id'])->update($etrfUpd);
        }

        $lab    = (string) ($job['lab_ref_no'] ?? '');
        $rep    = (string) ($job['report_no'] ?? '');
        $mode   = (string) ($job['mode'] ?? '');
        $isEtrf = in_array($mode, ['etrf', 'vf_copy'], true);
        $genApp = (string) ($upd['application_no'] ?? '');

        if ($mode === 'vf_pdf') {
            (new \App\Libraries\VfTestRequestService())->handlePdfCallback($job, $p);
        } elseif ($mode === 'vf_copy') {
            (new \App\Libraries\VfTestRequestService())->handleCopyCallback($job, $p);
        } elseif ($mode === 'vf_grid' && $state === 'done' && ! empty($p['result_json'])) {
            $resData = json_decode((string) $p['result_json'], true);
            $gRows   = is_array($resData['rows'] ?? null) ? $resData['rows'] : [];
            if ($gRows && $this->db()->tableExists('tbl_interlink_reports')) {
                $now = date('Y-m-d H:i:s');
                foreach ($gRows as $gr) {
                    $gApp = strtoupper(trim((string) ($gr['Application No.'] ?? '')));
                    if ($gApp === '') {
                        continue;
                    }
                    $pkg = trim((string) ($gr['Test Package(s)'] ?? ($gr['_filter_package'] ?? '')));
                    $ex  = $this->db()->table('tbl_interlink_reports')->where('application_no', $gApp)->get(1)->getRowArray();
                    $suffix = substr(preg_replace('/\D/', '', $gApp), -5) ?: '00001';
                    $rowMap = [
                        'application_no'      => $gApp,
                        'sample_type'         => (string) ($gr['Sample Type'] ?? 'Finished Product'),
                        'trf_submission_date' => (string) ($gr['Online TRF Submission Date'] ?? ''),
                        'report_no'           => (string) ($gr['Report No.'] ?? ''),
                        'report_issue_date'   => (string) ($gr['Report Issue Date'] ?? ''),
                        'job_status'          => (string) ($gr['Job Status'] ?? ''),
                        'overall_result'      => (string) ($gr['Overall Result'] ?? ''),
                        'company_name'        => (string) ($gr['Company Name'] ?? 'KEY TRADE CO., LTD'),
                        'vendor_id'           => (string) ($gr['Vendor ID'] ?? ''),
                        'vendor_name'         => (string) ($gr['Vendor Name'] ?? ''),
                        'factory_id'          => (string) ($gr['Factory ID'] ?? ''),
                        'factory_name'        => (string) ($gr['Factory Name'] ?? ''),
                        'buyer_brand'         => (string) ($gr['Brand Name'] ?? 'Vans'),
                        'test_stage'          => (string) ($gr['Test Stage'] ?? 'Production'),
                        'product_export_to'   => (string) ($gr['Product Export To'] ?? ''),
                        'service_test_type'   => (string) ($gr['Service Test Type'] ?? ''),
                        'service_provider'    => (string) ($gr['Service Provider'] ?? ''),
                        'style_no'            => (string) ($gr['Style No.'] ?? ''),
                        'material_code'       => (string) ($gr['Material Code'] ?? ''),
                        'style_color_no'      => (string) ($gr['SKU No.'] ?: $gr['Product ID'] ?? ''),
                        'season'              => (string) ($gr['Season'] ?? ''),
                        'sourcing_office'     => (string) ($gr['Sourcing Office'] ?? ''),
                        'test_packages'       => $pkg,
                        'color_code'          => (string) ($gr['Color Code'] ?? ''),
                        'colorway_name'       => (string) ($gr['Color Description'] ?? ''),
                        'product_category'    => (string) ($gr['Product Category'] ?? 'Footwear'),
                        'product_id'          => (string) ($gr['Product ID'] ?? ''),
                        'updated_at'          => $now,
                    ];
                    if ($ex) {
                        $this->db()->table('tbl_interlink_reports')->where('id', $ex['id'])->update($rowMap);
                    } else {
                        $rowMap['nexus_sample_id']     = 'NXL-' . date('Y') . '-' . $suffix;
                        $rowMap['nexus_work_order_no'] = 'WO-' . date('Ymd') . '-' . $suffix;
                        $rowMap['created_at']          = $now;
                        $this->db()->table('tbl_interlink_reports')->insert($rowMap);
                    }
                }
            }
        }

        // When an eTRF finishes and InterLink generated an Application No. (A006...), write it back to DTR & InterLink tables
        if ($state === 'done' && $genApp !== '' && preg_match('/^A\d{6,}$/i', $genApp)) {
            if ($lab !== '') {
                // the selected Lab Reference is linked to the Application No. through tbl_dtr_unified_labrefs (and the DTR form it came from);
                // each eTRF type keeps its own Application No. for this Lab Ref (the earlier ones go to its history)
                \App\Libraries\EtrfAppNos::linkToLabRef($lab, (string) ($etrfRow['etrf_type'] ?? ''), $genApp);
            }
            if ($etrfRow && $this->db()->tableExists('tbl_interlink_reports')) {
                $exRep = $this->db()->table('tbl_interlink_reports')->where('application_no', $genApp)->get()->getRowArray();
                $suffix = substr(preg_replace('/\D/', '', $genApp), -5) ?: '00001';
                $repData = [
                    'application_no'      => $genApp,
                    'trf_submission_date' => date('d M Y'),
                    'job_status'          => ($etrfRow['action_mode'] ?? 'submit') === 'save' ? 'Saved' : 'Submitted',
                    'company_name'        => (string) ($etrfRow['company_name'] ?? 'KEY TRADE CO., LTD'),
                    'vendor_id'           => (string) ($etrfRow['vendor_id'] ?? ''),
                    'vendor_name'         => (string) ($etrfRow['vendor_name'] ?? ''),
                    'factory_id'          => (string) ($etrfRow['factory_id'] ?? ''),
                    'factory_name'        => (string) ($etrfRow['factory_name'] ?? ''),
                    'supplier_id'         => (string) ($etrfRow['vendor_id'] ?? ''),
                    'supplier_name'       => (string) ($etrfRow['vendor_name'] ?? ''),
                    'buyer_brand'         => (string) ($etrfRow['buyer_brand'] ?? 'Vans'),
                    'test_stage'          => (string) ($etrfRow['test_stage'] ?? 'Production'),
                    'service_test_type'   => (string) ($etrfRow['service_test_type'] ?? 'In-House Lab Testing'),
                    'style_no'            => (string) ($etrfRow['style_no'] ?? ''),
                    'material_code'       => (string) ($etrfRow['material_code'] ?? ''),
                    'style_color_no'      => (string) ($etrfRow['style_color_no'] ?? ''),
                    'season'              => (string) ($etrfRow['season'] ?? ''),
                    'test_packages'       => (string) ($etrfRow['test_packages'] ?? ''),
                    'color_code'          => (string) ($etrfRow['color_code'] ?? ''),
                    'colorway_name'       => (string) ($etrfRow['color_description'] ?? ''),
                    'updated_at'          => date('Y-m-d H:i:s'),
                ];
                if ($exRep) {
                    $this->db()->table('tbl_interlink_reports')->where('id', $exRep['id'])->update($repData);
                } else {
                    $repData['nexus_sample_id']     = 'NXL-' . date('Y') . '-' . $suffix;
                    $repData['nexus_work_order_no'] = 'WO-' . date('Ymd') . '-' . $suffix;
                    $repData['created_at']          = date('Y-m-d H:i:s');
                    $this->db()->table('tbl_interlink_reports')->insert($repData);
                }
            }
        }

        if (in_array($mode, ['grid_html', 'vf_grid'], true)) {
            return $this->json(['ok' => true]);      // reading the grid is not a submission: no Synology message, no toast
        }

        if ($state === 'done') {
            // for an eTRF only the Application No. InterLink generated counts; the job's own tag holds the lab reference and must never be shown as one
            $appLabel = $isEtrf ? $genApp : ($upd['application_no'] ?? $job['application_no']);
            if ($isEtrf) {
                $this->notifyInterlinkChat((int) $job['user_id'], $rep, $lab, (string) $appLabel, $msg, $etrfRow);
                $this->synology((int) $job['user_id'], "THANK YOU! For your online job submission\nApplication No: " . ($appLabel !== '' ? $appLabel : 'not read - check InterLink (Lab Ref ' . $lab . ')') . "\nTemplate: {$rep}" . ($lab !== '' ? "\nLabRef: {$lab}" : '') . ($msg !== '' ? "\n{$msg}" : ''));
            } else {
                $this->synology((int) $job['user_id'], "Labreference: {$lab}\nReportNO: {$rep}\nsubmitted to InterLink");
            }
        } else {
            if ($isEtrf) {
                $this->notifyInterlinkChat((int) $job['user_id'], $rep, $lab, '', "FAILED: {$msg}", $etrfRow);
                $this->synology((int) $job['user_id'], "eTRF ({$rep}" . ($lab !== '' ? " / {$lab}" : '') . ") was NOT submitted to InterLink: {$msg}");
            } else {
                $this->synology((int) $job['user_id'], "Labreference: {$lab}\nReportNO: {$rep}\nwas NOT submitted to InterLink: {$msg}");
            }
        }

        return $this->json(['ok' => true, 'application_no' => $genApp]);
    }

    /**
     * Posts a unique notification into the user's dedicated "InterLink 2.0 — Application No." NEXUS-L Inbox thread (n:interlink_etrf)
     * as well as the floating LEXUS AI assistant thread so the generated Application No. is immediately visible in Chat.
     */
    private function notifyInterlinkChat(int $userId, string $templateLabel, string $labRef, string $appNo, string $statusMsg, ?array $etrfRow = null): void
    {
        if ($userId <= 0) {
            return;
        }
        $styleOrMat = trim((string) (($etrfRow['style_color_no'] ?? '') ?: ($etrfRow['style_no'] ?? '') ?: ($etrfRow['material_code'] ?? '')));
        $prodName   = trim((string) ($etrfRow['product_name'] ?? ''));
        $pkg        = trim((string) ($etrfRow['test_packages'] ?? ''));
        $isSuccess  = $appNo !== '' && ! str_starts_with($statusMsg, 'FAILED:');
        $notRead    = $appNo === '' && ! str_starts_with($statusMsg, 'FAILED:');

        $lines = [];
        if ($notRead) {
            $lines[] = '⚠️ Submitted, but the InterLink Application No. could not be read';
            $lines[] = '📌 Application No: not read - open InterLink and look it up';
        } elseif ($isSuccess) {
            $lines[] = '🎉 THANK YOU! For your online job submission';
            $lines[] = "📌 Application No: {$appNo}";
        } else {
            $lines[] = '⚠️ InterLink eTRF Submission Notice';
            if ($appNo !== '') {
                $lines[] = "📌 Application No: {$appNo}";
            }
        }
        $lines[] = "📋 Template: {$templateLabel}";
        if ($labRef !== '') {
            $lines[] = "🧪 Lab Reference: {$labRef}";
        }
        if ($styleOrMat !== '' || $prodName !== '') {
            $lines[] = '🏷️ Style / Material: ' . trim($styleOrMat . ($prodName !== '' ? " ({$prodName})" : ''));
        }
        if ($pkg !== '') {
            $lines[] = "📦 Test Package: {$pkg}";
        }
        if ($statusMsg !== '') {
            $lines[] = "ℹ️ Status: {$statusMsg}";
        }
        $lines[] = '🕒 Time: ' . date('d M Y H:i:s');
        $chatBody = implode("\n", $lines);

        // 1. Dedicated NEXUS-L Inbox conversation: "InterLink 2.0 — Application No." (key: n:interlink_etrf)
        try {
            $store = new \App\Libraries\Chat\NexusStore();
            $store->send('interlink_etrf', (string) $userId, $chatBody);
        } catch (\Throwable $e) {
            log_message('error', '[InterlinkServiceController] NexusStore chat send failed: ' . $e->getMessage());
        }

        // 2. Floating LEXUS AI Assistant thread (tbl_bot_threads / tbl_bot_chats) so the floating chat dot lights up too
        try {
            $db = $this->db();
            if ($db->tableExists('tbl_bot_threads') && $db->tableExists('tbl_bot_chats')) {
                $uRow = $db->table('tbl_users')->select('Username')->where('UserID', $userId)->get()->getRowArray();
                $uname = (string) ($uRow['Username'] ?? 'user');
                $tRow = $db->table('tbl_bot_threads')
                    ->where('user_id', $userId)
                    ->where('status !=', 'resolved')
                    ->orderBy('id', 'DESC')
                    ->get(1)
                    ->getRowArray();
                $now = date('Y-m-d H:i:s');
                if (! $tRow) {
                    $db->table('tbl_bot_threads')->insert([
                        'user_id'    => $userId,
                        'username'   => $uname,
                        'status'     => 'bot',
                        'topic'      => $appNo !== '' ? "InterLink Application No: {$appNo}" : "InterLink eTRF: {$templateLabel}",
                        'page'       => 'interlink/etrf',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $tid = (int) $db->insertID();
                } else {
                    $tid = (int) $tRow['id'];
                    $db->table('tbl_bot_threads')->where('id', $tid)->update(['updated_at' => $now]);
                }
                if ($tid > 0) {
                    $db->table('tbl_bot_chats')->insert([
                        'thread_id'   => $tid,
                        'sender'      => 'bot',
                        'sender_name' => 'InterLink 2.0 Bot',
                        'message'     => $chatBody,
                        'created_at'  => $now,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[InterlinkServiceController] Bot chat insert failed: ' . $e->getMessage());
        }
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
        \App\Libraries\ApprovalFiles::ensureColumns();
        $signed = $a ? \App\Libraries\ApprovalFiles::chosen($a)['path'] : '';

        return $this->json($signed !== '' && is_file($signed)
            ? ['ok' => true, 'approved' => true, 'sha256' => hash_file('sha256', $signed), 'by' => $a['decided_name'], 'at' => $a['decided_at']]
            : ['ok' => true, 'approved' => false]);
    }

    /**
     * GET interlink/service/report-jobs : the reports (Add / Edit of an Application) sent to InterLink through the service, newest first, with their
     * status and - when it failed - the reason. Shown under the main list of the InterLink page, like the eTRF list.
     */
    public function reportJobs()
    {
        $this->table();
        $rows = $this->db()->table('tbl_interlink_service_jobs')
            ->select('id, application_no, lab_ref_no, report_no, mode, status, message, user_name, created_at, started_at, finished_at')
            ->whereIn('mode', ['add', 'edit'])
            ->orderBy('id', 'DESC')
            ->get(25)->getResultArray();
        $stale = time() - 1800;                       // a job that never finished (the service stopped) is not "running" for ever
        foreach ($rows as &$r) {
            $r['live'] = in_array($r['status'], ['queued', 'running'], true) && strtotime((string) $r['created_at']) > $stale;
            if (in_array($r['status'], ['queued', 'running'], true) && ! $r['live']) {
                $r['status']  = 'failed';
                $r['message'] = 'No answer from the InterLink service for 30 minutes: check NexusInterlinkService, then send it again.';
            }
        }
        unset($r);
        $ahead = (int) $this->db()->table('tbl_interlink_service_jobs')->whereIn('status', ['queued', 'running'])->where('created_at >', date('Y-m-d H:i:s', $stale))->countAllResults();

        return $this->json(['ok' => true, 'rows' => $rows, 'active' => $ahead]);
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
            foreach ($this->db()->table('tbl_interlink_service_jobs')->where('user_id', $uid)->whereNotIn('mode', ['grid_html', 'vf_grid'])->where('started_at >', $after)->orderBy('id')->get(20)->getResultArray() as $j) {
                $isEtrf = ($j['mode'] ?? '') === 'etrf';
                $label  = $isEtrf ? ("eTRF ({$j['report_no']})" . ($j['lab_ref_no'] !== '' ? " [{$j['lab_ref_no']}]" : '')) : "{$j['application_no']} ({$j['lab_ref_no']})";
                $out['events'][] = [
                    'kind'           => 'info',
                    'type'           => $isEtrf ? 'etrf_running' : 'report_running',
                    'text'           => "{$label} is now being submitted to InterLink...",
                    'application_no' => $j['application_no'],
                    'lab_ref_no'     => $j['lab_ref_no'],
                    'template'       => $j['report_no'],
                ];
            }
            foreach ($this->db()->table('tbl_interlink_service_jobs')->where('user_id', $uid)->whereNotIn('mode', ['grid_html', 'vf_grid'])->where('finished_at >', $after)->orderBy('id')->get(20)->getResultArray() as $j) {
                $isEtrf = in_array($j['mode'] ?? '', ['etrf', 'vf_copy'], true);
                if ($j['status'] === 'done') {
                    $realApp = preg_match('/^A\d{6,}/i', (string) $j['application_no']) ? (string) $j['application_no'] : '';   // never the lab reference / job tag
                    if ($isEtrf) {
                        $j['application_no'] = $realApp;
                    }
                    $txt = $isEtrf
                        ? ("THANK YOU! For your online job submission — Application No: " . ($realApp !== '' ? $realApp : 'not read, check InterLink') . " · Template: {$j['report_no']}" . ($j['lab_ref_no'] !== '' ? " · LabRef: {$j['lab_ref_no']}" : ''))
                        : "Labreference: {$j['lab_ref_no']} · ReportNO: {$j['report_no']} · submitted to InterLink";
                    $out['events'][] = [
                        'kind'           => 'success',
                        'type'           => $isEtrf ? 'etrf_submitted' : 'report_submitted',
                        'text'           => $txt,
                        'application_no' => $j['application_no'],
                        'lab_ref_no'     => $j['lab_ref_no'],
                        'template'       => $j['report_no'],
                        'chat_key'       => 'n:interlink_etrf',
                        'message'        => $j['message'],
                    ];
                } else {
                    $out['events'][] = [
                        'kind'           => 'error',
                        'type'           => $isEtrf ? 'etrf_failed' : 'report_failed',
                        'text'           => ($isEtrf ? "eTRF ({$j['report_no']})" : "{$j['application_no']} ({$j['lab_ref_no']})") . " was NOT submitted to InterLink: {$j['message']}",
                        'application_no' => $j['application_no'],
                        'lab_ref_no'     => $j['lab_ref_no'],
                        'template'       => $j['report_no'],
                        'chat_key'       => 'n:interlink_etrf',
                    ];
                }
            }
        }

        return $this->json($out);
    }
}
