<?php

namespace App\Controllers;

use App\Libraries\SystemSettings;
use App\Libraries\VfTestRequestService;

/**
 * Handles:
 *  - InterLink > VF - Test Request (Outlook djc_lab table, 4 Test Packages filter, Copy as New + dberp.production_orders, Fill Form, Submit)
 *  - Lab Monitoring > VF Test Monitoring (Submitted requests, auto PDF emails, In-Transit container #, Lab Received Expect Due Date/Report #, Final PASS PDF Certificates)
 */
class VfTestRequestController extends BaseController
{
    private function db()
    {
        return \Config\Database::connect();
    }

    private function json(array $data, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($data + ['csrf' => csrf_hash()]);
    }

    private function svc(): VfTestRequestService
    {
        return new VfTestRequestService();
    }

    /** GET interlink/vf-test-request */
    public function index(): string
    {
        $svc = $this->svc();
        $svc->ensureTables();

        return $this->render('interlink/vf_test_request', [
            'title'    => 'VF - Test Request',
            'withCss'  => 'interlink.css',
            'withJs'   => ['vf_test_request.js'],
            'packages' => array_keys(VfTestRequestService::PACKAGES),
            'searchItems' => array_keys(VfTestRequestService::searchItems()),
        ]);
    }

    /** GET lab-monitoring/vf-requests */
    public function monitoring(): string
    {
        $svc = $this->svc();
        $svc->ensureTables();

        return $this->render('labmonitor/vf_requests', [
            'title'    => 'VF Test Monitoring',
            'withCss'  => 'labmon.css',
            'withJs'   => ['vf_requests.js'],
            'packages' => array_keys(VfTestRequestService::PACKAGES),
        ]);
    }

    /** GET interlink/vf-test-request/data?package=&q=&stage= */
    public function data()
    {
        $svc = $this->svc();
        $svc->ensureTables();
        $svc->linkExistingApplications();
        $db = $this->db();

        $pkgFilter = trim((string) ($this->request->getGet('package') ?? ''));
        $q         = trim((string) ($this->request->getGet('q') ?? ''));

        // 1. InterLink Test Request list filtered strictly to the 4 VF Test Packages
        $allowedPkgs = array_keys(VfTestRequestService::PACKAGES);
        $searchItems = VfTestRequestService::searchItems();
        $interlinkRows = [];
        if ($db->tableExists('tbl_interlink_reports')) {
            $qb = $db->table('tbl_interlink_reports')
                ->select('id, application_no, sample_type, trf_submission_date, report_no, report_issue_date, job_status, overall_result, company_name, vendor_name, factory_name, buyer_brand, test_stage, product_export_to, service_test_type, service_provider, style_no, style_color_no, product_name, material_code, season, sourcing_office, test_packages, color_code, colorway_name, cargo_receive_date')
                ->groupStart()
                ->whereIn('test_packages', $allowedPkgs)
                ->orWhere('test_packages LIKE', '%KC %')
                ->orWhere('test_packages LIKE', '%KC Package%')
                ->orWhere('test_packages LIKE', '%GB - Full%')
                ->orWhere('test_packages LIKE', '%CPSIA%')
                ->orWhere('test_packages LIKE', '%RSL%')
                ->groupEnd();
            if ($pkgFilter !== '' && in_array($pkgFilter, $allowedPkgs, true)) {
                $aliases = VfTestRequestService::PACKAGES[$pkgFilter]['aliases'] ?? [$pkgFilter];
                $qb->groupStart()->where('test_packages', $pkgFilter);
                foreach ($aliases as $al) {
                    $qb->orWhere('test_packages LIKE', '%' . $al . '%');
                }
                $qb->groupEnd();
            } elseif ($pkgFilter !== '' && isset($searchItems[$pkgFilter])) {
                $qb->like('test_packages', $searchItems[$pkgFilter]);   // a search item added in Settings
            }
            if ($q !== '') {
                $qb->groupStart()
                    ->like('application_no', $q)
                    ->orLike('style_no', $q)
                    ->orLike('style_color_no', $q)
                    ->orLike('product_name', $q)
                    ->orLike('colorway_name', $q)
                    ->orLike('test_packages', $q)
                    ->groupEnd();
            }
            $interlinkRows = $qb->orderBy('application_no', 'DESC')->limit(250)->get()->getResultArray();
        }

        // 2. Incoming email batches & requested items from djc_lab@dtshoes.com
        $batches = $db->table('tbl_vf_email_batches')->orderBy('id', 'DESC')->limit(30)->get()->getResultArray();
        $reqQb   = $db->table('tbl_vf_test_requests r')
            ->select('r.*, COALESCE(b.package_needs_review, 0) AS package_needs_review, b.email_sent AS batch_email_sent, b.email_sent_at AS batch_email_sent_at')
            ->join('tbl_vf_email_batches b', 'b.id = r.batch_id', 'left');
        // Excel history rows are records only: never offer them as new requests to copy
        $reqQb->groupStart()->where('b.source_channel IS NULL', null, false)->orWhere('b.source_channel !=', 'excel_import')->groupEnd();
        if ($pkgFilter !== '' && in_array($pkgFilter, $allowedPkgs, true)) {
            $reqQb->where('r.test_package', $pkgFilter);
        } elseif ($pkgFilter !== '' && isset($searchItems[$pkgFilter])) {
            $reqQb->like('r.test_package', $searchItems[$pkgFilter]);
        }
        if ($q !== '') {
            $reqQb->groupStart()
                ->like('r.prod_code', $q)
                ->orLike('r.mtl_no', $q)
                ->orLike('r.style_no', $q)
                ->orLike('r.generated_application_no', $q)
                ->orLike('r.po_no', $q)
                ->groupEnd();
        }
        $requests = $reqQb->orderBy('r.id', 'DESC')->limit(250)->get()->getResultArray();
        $this->attachProcessing($requests);

        return $this->json([
            'ok'             => true,
            'packages'       => $allowedPkgs,
            'interlink_rows' => $interlinkRows,
            'batches'        => $batches,
            'requests'       => $requests,
        ]);
    }

    /**
     * Adds to every request what is already known about it: the InterLink Application with the same MTL# and Test Package (il_app, il_status,
     * il_result, il_report) and whether the Excel history already holds it (in_history). So the list shows what was asked AND what is finished.
     */
    private function attachProcessing(array &$requests): void
    {
        $db   = $this->db();
        $mtls = array_values(array_unique(array_filter(array_map(static fn ($r) => strtoupper(trim((string) $r['mtl_no'])), $requests))));
        if (! $mtls) {
            return;
        }
        $il = [];
        if ($db->tableExists('tbl_interlink_reports')) {
            $rows = $db->table('tbl_interlink_reports')->select('application_no, style_color_no, test_packages, job_status, overall_result, report_no')
                ->whereIn('style_color_no', $mtls)->like('application_no', 'A', 'after')->orderBy('application_no', 'DESC')->limit(2000)->get()->getResultArray();
            foreach ($rows as $r) {
                $il[strtoupper((string) $r['style_color_no'])][] = $r;
            }
        }
        $hist = [];
        foreach ($db->table('tbl_vf_test_requests')->select('mtl_no, prod_code, test_package')->where('stage', 'HISTORY')->whereIn('mtl_no', $mtls)->get()->getResultArray() as $h) {
            $hist[strtoupper((string) $h['mtl_no']) . '|' . strtoupper((string) $h['prod_code']) . '|' . $h['test_package']] = true;
        }
        foreach ($requests as &$r) {
            $mtl   = strtoupper(trim((string) $r['mtl_no']));
            $pkg   = (string) $r['test_package'];
            $terms = array_map('strtolower', array_merge(VfTestRequestService::PACKAGES[$pkg]['aliases'] ?? [], [$pkg]));
            $r['il_app'] = $r['il_status'] = $r['il_result'] = $r['il_report'] = '';
            foreach ($il[$mtl] ?? [] as $cand) {
                $tp = strtolower((string) $cand['test_packages']);
                foreach ($terms as $t) {
                    if ($t !== '' && str_contains($tp, $t)) {
                        $r['il_app'] = $cand['application_no'];
                        $r['il_status'] = (string) $cand['job_status'];
                        $r['il_result'] = (string) $cand['overall_result'];
                        $r['il_report'] = (string) $cand['report_no'];
                        break 2;
                    }
                }
            }
            $r['in_history'] = isset($hist[$mtl . '|' . strtoupper((string) $r['prod_code']) . '|' . $pkg]) ? 1 : 0;
        }
        unset($r);
    }

    /** GET interlink/vf-test-request/detail/(:segment) */
    public function detail(string $appNo)
    {
        $this->svc()->ensureTables();
        $appNo = strtoupper(trim($appNo));
        $row   = $this->db()->table('tbl_interlink_reports')->where('application_no', $appNo)->orderBy('id', 'DESC')->get(1)->getRowArray();
        if (! $row) {
            return $this->json(['ok' => false, 'error' => "Application {$appNo} was not found locally. Run Sync Applicant Grid first."], 404);
        }

        return $this->json(['ok' => true, 'report' => $row]);
    }

    /** GET interlink/vf-test-request/erp-lookup?mtl=&prod_code=&q= */
    public function erpLookup()
    {
        $mtl      = (string) ($this->request->getGet('mtl') ?? '');
        $prodCode = (string) ($this->request->getGet('prod_code') ?? '');
        $q        = (string) ($this->request->getGet('q') ?? '');

        return $this->json($this->svc()->lookupErp($mtl, $prodCode, $q));
    }

    /** POST interlink/vf-test-request/fill-form */
    public function fillForm()
    {
        $p = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];

        return $this->json($this->svc()->prepareCopyForm($p));
    }

    /** POST interlink/vf-test-request/submit : queues Copy-as-New + Print&Submit + Print PDF job in NexusInterlinkService */
    public function submit()
    {
        $svc = $this->svc();
        $svc->ensureTables();
        $db = $this->db();

        $baseUrl = SystemSettings::serviceUrl();
        $key     = (string) SystemSettings::get('interlink.service.key', '');
        $enabled = SystemSettings::bool('interlink.service.enabled', true) && $baseUrl !== '' && strlen($key) >= 16;
        if (! $enabled) {
            return $this->json(['ok' => false, 'error' => 'InterLink background service is not configured on this server.'], 503);
        }

        $p            = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $requestId    = (int) ($p['request_id'] ?? 0);
        $srcAppNo     = strtoupper(trim((string) ($p['source_application_no'] ?? '')));
        $mtlNo        = strtoupper(trim((string) ($p['mtl_no'] ?? '')));
        $prodCode     = strtoupper(trim((string) ($p['prod_code'] ?? '')));
        if ($srcAppNo !== '' && ! preg_match('/^A\d{8,12}(-REV\d+)?$/', $srcAppNo)) {
            return $this->json(['ok' => false, 'error' => "Source Application No. '{$srcAppNo}' is not an InterLink Application No. (it looks like A006022419). Pick a past Application from the list or type a real one."], 422);
        }
        $testPkg      = VfTestRequestService::normalizePackage((string) ($p['test_package'] ?? ''));
        $shortType    = VfTestRequestService::shortTypeForPackage($testPkg);
        $allowUnmatch = ! empty($p['allow_unmatched_erp']);
        $allowRetest  = ! empty($p['allow_retest']);

        if ($mtlNo === '' && $prodCode === '') {
            return $this->json(['ok' => false, 'error' => 'Select an MTL# or Prod Code before submitting.'], 422);
        }

        // Strict ERP validation: never guess when 1 MTL# spans multiple Prod Codes unless Prod Code is chosen
        $erp = null;
        if ($prodCode !== '') {
            $erp = $db->query('SELECT * FROM dberp.production_orders WHERE prod_code = ? LIMIT 1', [$prodCode])->getRowArray();
            if (! $erp && ! $allowUnmatch) {
                return $this->json(['ok' => false, 'code' => 'ERP_NOT_FOUND', 'error' => "Prod Code {$prodCode} was not found in dberp.production_orders. Pick a valid ERP Prod Code or check 'Allow Unmatched ERP'."], 422);
            }
        } elseif ($mtlNo !== '') {
            $mtlRows = $db->query('SELECT * FROM dberp.production_orders WHERE mtl_no = ? ORDER BY id DESC LIMIT 20', [$mtlNo])->getResultArray();
            if (count($mtlRows) > 1) {
                return $this->json(['ok' => false, 'code' => 'ERP_AMBIGUOUS', 'error' => "MTL# {$mtlNo} matches " . count($mtlRows) . " Prod Codes in dberp.production_orders. Choose the exact Prod Code first."], 422);
            }
            if (count($mtlRows) === 1) {
                $erp = $mtlRows[0];
            } elseif (! $allowUnmatch) {
                return $this->json(['ok' => false, 'code' => 'ERP_NOT_FOUND', 'error' => "MTL# {$mtlNo} was not found in dberp.production_orders."], 422);
            }
        }
        if ($erp && $mtlNo === '') {
            $mtlNo = strtoupper(trim((string) $erp['mtl_no']));
        }
        if ($erp && $prodCode === '') {
            $prodCode = strtoupper(trim((string) $erp['prod_code']));
        }

        // Prevent duplicate InterLink submission when row or active matching request already holds an Application No.
        if (! $allowRetest) {
            if ($requestId > 0) {
                $curReq = $db->table('tbl_vf_test_requests')->where('id', $requestId)->get(1)->getRowArray();
                if ($curReq && ! empty($curReq['generated_application_no'])) {
                    return $this->json(['ok' => false, 'code' => 'ALREADY_SUBMITTED', 'error' => "Request #{$requestId} already has Application No. {$curReq['generated_application_no']}. Check 'Allow Retest' to submit a new application."], 409);
                }
            } elseif ($prodCode !== '' && $mtlNo !== '') {
                $dupApp = $db->table('tbl_vf_test_requests')
                    ->where('prod_code', $prodCode)
                    ->where('mtl_no', $mtlNo)
                    ->where('test_package', $testPkg)
                    ->where('generated_application_no !=', '')
                    ->where('stage !=', 'FAILED')
                    ->orderBy('id', 'DESC')->get(1)->getRowArray();
                if ($dupApp) {
                    return $this->json(['ok' => false, 'code' => 'ALREADY_SUBMITTED', 'error' => "{$prodCode} / {$mtlNo} ({$testPkg}) already has active Application No. {$dupApp['generated_application_no']}. Check 'Allow Retest' if intentional."], 409);
                }
            }
        }

        $me  = (array) service('auth')->user();
        $uid = bin2hex(random_bytes(12));
        $now = date('Y-m-d H:i:s');

        $styleNo   = trim((string) ($p['style_no'] ?? (preg_match('/^([A-Z0-9]{8})[A-Z0-9]{3}$/', $mtlNo, $m) ? $m[1] : $mtlNo)));
        $prodName  = trim((string) ($p['product_name'] ?? VfTestRequestService::cleanProductName((string) ($erp['style_no'] ?? '')) ?: $styleNo));
        $colorDesc = trim((string) ($p['color_description'] ?? ($erp['colorway'] ?? '')));
        $colorCode = trim((string) ($p['color_code'] ?? (preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})$/', $mtlNo, $cm) ? $cm[1] : '')));
        $season    = trim((string) ($p['season'] ?? 'S27'));
        $sizeCat   = strtoupper(trim((string) ($p['size_category'] ?? 'UNISEX')));
        $ageGroup  = trim((string) ($p['age_group'] ?? SystemSettings::get('vf.default_age_group', 'Children (3-12 years)')));
        $cargoDate = trim((string) ($p['cargo_receive_date'] ?? (! empty($erp['crd']) ? date('d M Y', strtotime((string) $erp['crd'])) : date('d M Y', strtotime('+' . max(1, (int) SystemSettings::get('vf.cargo_days', '30')) . ' days')))));
        $remarks   = trim((string) ($p['remarks'] ?? "VF Test Request | {$prodCode} | {$mtlNo}"));

        // Create or update tbl_vf_test_requests row
        if ($requestId <= 0 && $prodCode !== '' && $mtlNo !== '') {
            $ex = $db->table('tbl_vf_test_requests')
                ->where('prod_code', $prodCode)
                ->where('mtl_no', $mtlNo)
                ->where('test_package', $testPkg)
                ->where('generated_application_no', '')
                ->orderBy('id', 'DESC')->get(1)->getRowArray();
            if ($ex) {
                $requestId = (int) $ex['id'];
            }
        }
        if ($requestId > 0) {
            $db->table('tbl_vf_test_requests')->where('id', $requestId)->update([
                'production_order_id'   => ! empty($erp['id']) ? (int) $erp['id'] : null,
                'erp_matched'           => $erp ? 1 : 0,
                'erp_ambiguous'         => 0,
                'prod_code'             => $prodCode,
                'mtl_no'                => $mtlNo,
                'po_no'                 => (string) ($erp['po_no'] ?? ''),
                'style_no'              => (string) ($erp['style_no'] ?? $prodName),
                'colorway'              => $colorDesc,
                'season'                => $season,
                'crd'                   => ! empty($erp['crd']) ? (string) $erp['crd'] : null,
                'test_package'          => $testPkg,
                'test_type_short'       => $shortType,
                'source_application_no' => $srcAppNo,
                'interlink_job_uid'     => $uid,
                'interlink_status'      => 'Queued',
                'interlink_message'     => 'Queued for Copy as New + Submit in InterLink...',
                'stage'                 => 'SUBMITTING',
                'submitted_by_id'       => (int) ($me['id'] ?? 0),
                'submitted_by_name'     => (string) ($me['name'] ?? $me['username'] ?? ''),
                'updated_at'            => $now,
            ]);
        } else {
            $db->table('tbl_vf_test_requests')->insert([
                'production_order_id'   => ! empty($erp['id']) ? (int) $erp['id'] : null,
                'erp_matched'           => $erp ? 1 : 0,
                'erp_ambiguous'         => 0,
                'factory'               => 'DTP',
                'prod_code'             => $prodCode,
                'po_no'                 => (string) ($erp['po_no'] ?? ''),
                'style_no'              => (string) ($erp['style_no'] ?? $prodName),
                'mtl_no'                => $mtlNo,
                'colorway'              => $colorDesc,
                'colorway_c'            => (string) ($erp['colorway_c'] ?? ''),
                'season'                => $season,
                'size_no'               => (string) ($erp['size_no'] ?? ''),
                'country'               => (string) ($erp['country'] ?? ''),
                'buyer'                 => (string) ($erp['buyer'] ?? ''),
                'crd'                   => ! empty($erp['crd']) ? (string) $erp['crd'] : null,
                'test_package'          => $testPkg,
                'test_type_short'       => $shortType,
                'source_application_no' => $srcAppNo,
                'interlink_job_uid'     => $uid,
                'interlink_status'      => 'Queued',
                'interlink_message'     => 'Queued for Copy as New + Submit in InterLink...',
                'stage'                 => 'SUBMITTING',
                'submitted_by_id'       => (int) ($me['id'] ?? 0),
                'submitted_by_name'     => (string) ($me['name'] ?? $me['username'] ?? ''),
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);
            $requestId = (int) $db->insertID();
        }

        // Register service job row (used by InterlinkServiceController::callback + feed toast)
        $jobTag = 'VF:' . ($prodCode ?: $mtlNo);
        (new InterlinkServiceController()); // ensures class autoload
        $db->table('tbl_interlink_service_jobs')->insert([
            'job_uid'         => $uid,
            'application_no'  => mb_substr($jobTag, 0, 60),
            'lab_ref_no'      => $mtlNo,
            'report_no'       => $testPkg,
            'mode'            => 'vf_copy',
            'etrf_request_id' => $requestId,
            'etrf_type'       => $shortType,
            'user_id'         => (int) ($me['id'] ?? 0),
            'user_name'       => (string) ($me['name'] ?? $me['username'] ?? ''),
            'status'          => 'queued',
            'created_at'      => $now,
        ]);

        $etrfPayload = [
            'source_application_no' => $srcAppNo,
            'test_package'          => $testPkg,
            'action'                => 'submit',
            'fields'                => [
                'TRPT_CDI_BRAND_NAME' => 'Vans',
                'CAL_CDTL_5'          => $testPkg,
                'TRPT_AGE_RANGE'      => $ageGroup,
                'INSP_SHIP_DATE'      => $cargoDate,
                'TRPT_PRODUCT_ID'     => $styleNo,
                'TRPT_RMK'            => $remarks,
            ],
            'rows' => [[
                'style_no'          => $styleNo,
                'product_name'      => $prodName,
                'style_color_no'    => $mtlNo,
                'color_description' => $colorDesc,
                'color_code'        => $colorCode,
                'season'            => $season,
                'size_category'     => $sizeCat,
            ]],
            'summary' => [
                'test_packages'  => $testPkg,
                'style_no'       => $styleNo,
                'style_color_no' => $mtlNo,
                'product_name'   => $prodName,
            ],
        ];

        try {
            $res = service('curlrequest')->post($baseUrl . '/jobs', [
                'headers' => ['X-Service-Key' => $key],
                'json'    => [
                    'job_uid'        => $uid,
                    'application_no' => mb_substr($jobTag, 0, 30),
                    'lab_ref_no'     => $mtlNo,
                    'report_no'      => $testPkg,
                    'mode'           => 'vf_copy',
                    'account'        => 'dtp_qcasst@dtshoes.com',
                    'user_name'      => (string) ($me['name'] ?? ''),
                    'verify_url'     => '',
                    'callback_url'   => site_url('interlink/service/callback'),
                    'job'            => [
                        'source_application_no' => $srcAppNo,
                        'etrf'                  => $etrfPayload,
                    ],
                ],
                'timeout'     => 45,
                'http_errors' => false,
            ]);
            $body = json_decode((string) $res->getBody(), true) ?: [];
            if ($res->getStatusCode() !== 200 || empty($body['ok'])) {
                throw new \RuntimeException((string) ($body['error'] ?? 'Service HTTP ' . $res->getStatusCode()));
            }
        } catch (\Throwable $e) {
            $db->table('tbl_interlink_service_jobs')->where('job_uid', $uid)->update(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 480), 'finished_at' => date('Y-m-d H:i:s')]);
            $db->table('tbl_vf_test_requests')->where('id', $requestId)->update(['interlink_status' => 'Failed', 'interlink_message' => mb_substr($e->getMessage(), 0, 480), 'stage' => 'FAILED', 'updated_at' => date('Y-m-d H:i:s')]);

            return $this->json(['ok' => false, 'error' => 'Could not queue Copy as New job in InterLink Service: ' . $e->getMessage()], 502);
        }

        return $this->json([
            'ok'         => true,
            'request_id' => $requestId,
            'job_uid'    => $uid,
            'ahead'      => (int) ($body['ahead'] ?? 0),
            'message'    => "Queued Copy as New for {$mtlNo} ({$prodCode}) under {$testPkg}. You will get a toast once InterLink assigns the Application No. and captures the PDF.",
        ]);
    }

    /** POST interlink/vf-test-request/sync-emails */
    public function syncEmails()
    {
        return $this->json($this->svc()->syncFromOutlook());
    }

    /** POST interlink/vf-test-request/import-html */
    public function importHtml()
    {
        $p = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $subject = trim((string) ($p['subject'] ?? 'RE: DTP Test Request'));
        $html    = (string) ($p['html'] ?? '');
        $sender  = trim((string) ($p['sender'] ?? ''));
        $pkg     = trim((string) ($p['test_package'] ?? ''));
        $source  = null;
        $srcId   = (int) ($p['source_id'] ?? 0);
        if ($srcId > 0) {
            foreach ($this->svc()->sources() as $cand) {
                if ((int) $cand['id'] === $srcId) {
                    $source = $cand;
                    break;
                }
            }
        }
        if ($sender === '') {
            $sender = $source ? (VfTestRequestService::splitList((string) $source['sender_patterns'])[0] ?? '') : 'djc_lab@dtshoes.com';
        }

        $res = $this->svc()->ingestRequestEmail($subject, $html, $sender, '', $pkg, null, '', null, $source);

        return $this->json($res, ! empty($res['ok']) ? 200 : 422);
    }

    /** POST interlink/vf-test-request/sync-grid : queues vf_grid scrape on NexusInterlinkService */
    public function syncGrid()
    {
        $baseUrl = SystemSettings::serviceUrl();
        $key     = (string) SystemSettings::get('interlink.service.key', '');
        if (! SystemSettings::bool('interlink.service.enabled', true) || $baseUrl === '' || strlen($key) < 16) {
            return $this->json(['ok' => false, 'error' => 'InterLink background service is not configured.'], 503);
        }
        $in        = $this->request->getJSON(true) ?? [];
        $pkgWanted = trim((string) ($in['package'] ?? ''));
        $items     = VfTestRequestService::searchItems();
        $pkgWanted = isset($items[$pkgWanted]) ? $items[$pkgWanted] : '';   // the words typed into InterLink's filter
        $pageWanted = max(1, min(5000, (int) ($in['page'] ?? 1)));
        $qWanted = mb_substr(trim((string) ($in['q'] ?? '')), 0, 60);
        $me  = (array) service('auth')->user();
        $uid = bin2hex(random_bytes(12));
        $now = date('Y-m-d H:i:s');
        $db  = $this->db();
        $db->table('tbl_interlink_service_jobs')->insert([
            'job_uid'        => $uid,
            'application_no' => 'VF-GRID-SYNC',
            'lab_ref_no'     => '',
            'report_no'      => 'Applicant Grid',
            'mode'           => 'vf_grid',
            'user_id'        => (int) ($me['id'] ?? 0),
            'user_name'      => (string) ($me['name'] ?? $me['username'] ?? ''),
            'status'         => 'queued',
            'created_at'     => $now,
        ]);

        try {
            $res = service('curlrequest')->post($baseUrl . '/jobs', [
                'headers' => ['X-Service-Key' => $key],
                'json'    => [
                    'job_uid'        => $uid,
                    'application_no' => 'VF-GRID-SYNC',
                    'report_no'      => 'Applicant Grid',
                    'mode'           => 'vf_grid',
                    'account'        => 'dtp_qcasst@dtshoes.com',
                    'user_name'      => (string) ($me['name'] ?? ''),
                    'callback_url'   => site_url('interlink/service/callback'),
                    // page 1 only (never every InterLink page); a chosen package / Application No. is filtered by InterLink itself
                    'job'            => ['packages' => $pkgWanted !== '' ? [$pkgWanted] : array_values($items), 'package' => $pkgWanted, 'q' => $qWanted, 'page_only' => true, 'page' => $pageWanted, 'fresh' => ! empty($in['fresh'])],
                ],
                'timeout'     => 25,
                'http_errors' => false,
            ]);
            $body = json_decode((string) $res->getBody(), true) ?: [];
            if ($res->getStatusCode() === 409 && ($body['code'] ?? '') === 'already_queued' && ! empty($body['job_uid'])) {
                // a sync is already running/waiting in the service: follow that one instead of failing
                $db->table('tbl_interlink_service_jobs')->where('job_uid', $uid)->delete();
                $state = (string) ($body['state'] ?? 'running');

                return $this->json(['ok' => true, 'job_uid' => (string) $body['job_uid'], 'message' => 'An InterLink sync is already ' . ($state === 'running' ? 'running' : 'waiting') . ' in the service - showing its result when it finishes.']);
            }
            if ($res->getStatusCode() !== 200 || empty($body['ok'])) {
                throw new \RuntimeException((string) ($body['error'] ?? 'Service HTTP ' . $res->getStatusCode()));
            }
        } catch (\Throwable $e) {
            $db->table('tbl_interlink_service_jobs')->where('job_uid', $uid)->update(['status' => 'failed', 'message' => $e->getMessage(), 'finished_at' => date('Y-m-d H:i:s')]);

            return $this->json(['ok' => false, 'error' => 'Could not reach NexusInterlinkService: ' . $e->getMessage()], 502);
        }

        return $this->json(['ok' => true, 'job_uid' => $uid, 'message' => 'Queued the InterLink sync (Inhouse & 3rd party search, newest first) for KC Test Package, GB - Full Package, CPSIA Product Testing Package & RSL. It runs in the InterLink service own browser; no tab opens here.']);
    }

    /**
     * GET interlink/vf-test-request/past-apps?package=&mtl=&style=
     * Past InterLink Application Nos. of the same Test Package, to pick the one to Copy as New from. The ones with the same style / MTL#
     * come first. Also lists what NEXUS-L itself submitted for that package (proven sources).
     */
    public function pastApps()
    {
        $db    = $this->db();
        $pkg   = trim((string) ($this->request->getGet('package') ?? ''));
        $mtl   = strtoupper(trim((string) ($this->request->getGet('mtl') ?? '')));
        $style = strtoupper(trim((string) ($this->request->getGet('style') ?? '')));
        $terms = [];
        if ($pkg !== '') {
            $terms = VfTestRequestService::PACKAGES[$pkg]['aliases'] ?? [];
            $terms[] = $pkg;
            $items = VfTestRequestService::searchItems();
            if (isset($items[$pkg])) {
                $terms[] = $items[$pkg];
            }
        }
        $terms = array_values(array_unique(array_filter(array_map('trim', $terms))));
        $apps  = [];
        if ($db->tableExists('tbl_interlink_reports')) {
            $qb = $db->table('tbl_interlink_reports')
                ->select('application_no, style_no, style_color_no, product_name, colorway_name, trf_submission_date, job_status, test_packages')
                ->like('application_no', 'A', 'after');
            if ($terms) {
                $qb->groupStart();
                foreach ($terms as $t) {
                    $qb->orLike('test_packages', $t);
                }
                $qb->groupEnd();
            }
            $rows = $qb->orderBy('application_no', 'DESC')->limit(400)->get()->getResultArray();
            foreach ($rows as $r) {
                $app = strtoupper(trim((string) $r['application_no']));
                if (! preg_match('/^A\d{8,12}(-REV\d+)?$/', $app)) {
                    continue;
                }
                $same = ($mtl !== '' && strtoupper((string) $r['style_color_no']) === $mtl) || ($style !== '' && strtoupper((string) $r['style_no']) === $style);
                $apps[$app] = $r + ['same' => $same ? 1 : 0, 'own' => 0];
            }
        }
        // what NEXUS-L submitted itself for this package
        $own = $db->table('tbl_vf_test_requests')->select('generated_application_no, style_no, mtl_no, test_package, submitted_at')->where('generated_application_no !=', '');
        if ($pkg !== '' && isset(VfTestRequestService::PACKAGES[$pkg])) {
            $own->where('test_package', $pkg);
        }
        foreach ($own->orderBy('id', 'DESC')->limit(100)->get()->getResultArray() as $o) {
            $app = strtoupper(trim((string) $o['generated_application_no']));
            if (! preg_match('/^A\d{8,12}(-REV\d+)?$/', $app)) {
                continue;   // only real InterLink Application Nos.
            }
            $same = ($mtl !== '' && strtoupper((string) $o['mtl_no']) === $mtl) || ($style !== '' && strtoupper((string) $o['style_no']) === $style);
            if (isset($apps[$app])) {
                $apps[$app]['own'] = 1;
                $apps[$app]['same'] = $apps[$app]['same'] || $same ? 1 : 0;
            } else {
                $apps[$app] = ['application_no' => $app, 'style_no' => $o['style_no'], 'style_color_no' => $o['mtl_no'], 'product_name' => '', 'colorway_name' => '', 'trf_submission_date' => substr((string) $o['submitted_at'], 0, 10), 'job_status' => 'Submitted', 'test_packages' => $pkg, 'same' => $same ? 1 : 0, 'own' => 1];
            }
        }
        $list = array_values($apps);
        usort($list, static fn ($a, $b) => [$b['same'], $b['application_no']] <=> [$a['same'], $a['application_no']]);

        return $this->json(['ok' => true, 'apps' => array_slice($list, 0, 80)]);
    }

    /** GET interlink/vf-test-request/sources : the configured e-mail sources + the fields a column map may fill */
    public function sources()
    {
        return $this->json(['ok' => true, 'sources' => $this->svc()->sources(), 'fields' => VfTestRequestService::REQUEST_FIELDS, 'packages' => array_keys(VfTestRequestService::PACKAGES)]);
    }

    /** POST interlink/vf-test-request/sources/save {id?, name, sender_patterns, subject_keywords, default_package, column_map {field: "alias, alias"}, skip_state_regex, priority, enabled} */
    public function saveSource()
    {
        $p    = $this->request->getJSON(true) ?? [];
        $name = mb_substr(trim((string) ($p['name'] ?? '')), 0, 120);
        $send = trim((string) ($p['sender_patterns'] ?? ''));
        $subj = trim((string) ($p['subject_keywords'] ?? ''));
        if ($name === '') {
            return $this->json(['ok' => false, 'error' => 'Give the source a name.'], 422);
        }
        if ($send === '' && $subj === '') {
            return $this->json(['ok' => false, 'error' => 'Enter at least one sender (e-mail, *@domain or domain) or one subject keyword; a source with no rule would take every mail.'], 422);
        }
        $map = [];
        foreach ((array) ($p['column_map'] ?? []) as $field => $aliases) {
            if (! isset(VfTestRequestService::REQUEST_FIELDS[$field])) {
                continue;
            }
            $list = is_array($aliases) ? array_map('strval', $aliases) : VfTestRequestService::splitList((string) $aliases);
            $list = array_values(array_filter(array_map('trim', $list), static fn ($x) => $x !== ''));
            if ($list) {
                $map[$field] = $list;
            }
        }
        $pkg = trim((string) ($p['default_package'] ?? ''));
        if ($pkg !== '' && ! isset(VfTestRequestService::PACKAGES[$pkg])) {
            $pkg = '';
        }
        $re = mb_substr(trim((string) ($p['skip_state_regex'] ?? '')), 0, 255);
        if ($re !== '' && @preg_match('/' . str_replace('/', '\\/', $re) . '/iu', '') === false) {
            return $this->json(['ok' => false, 'error' => 'The "skip rows whose State matches" pattern is not a valid expression.'], 422);
        }
        $this->svc()->ensureTables();
        $now = date('Y-m-d H:i:s');
        $row = [
            'name' => $name, 'sender_patterns' => $send, 'subject_keywords' => $subj, 'default_package' => $pkg,
            'column_map_json' => json_encode($map, JSON_UNESCAPED_UNICODE), 'skip_state_regex' => $re,
            'priority' => max(1, min(9999, (int) ($p['priority'] ?? 100))), 'enabled' => ! empty($p['enabled']) ? 1 : 0, 'updated_at' => $now,
        ];
        $id = (int) ($p['id'] ?? 0);
        if ($id > 0) {
            $this->db()->table('tbl_vf_email_sources')->where('id', $id)->update($row);
        } else {
            $row['created_at'] = $now;
            $this->db()->table('tbl_vf_email_sources')->insert($row);
            $id = (int) $this->db()->insertID();
        }

        $shared = $this->svc()->shareSendersWithOutlook();

        return $this->json(['ok' => true, 'id' => $id, 'sources' => $this->svc()->sources(), 'shared' => $shared]);
    }

    /** POST interlink/vf-test-request/sources/share : give every enabled source's senders to the Outlook Attachment Sync database again */
    public function shareSources()
    {
        return $this->json(['ok' => true, 'shared' => $this->svc()->shareSendersWithOutlook()]);
    }

    /** POST interlink/vf-test-request/sources/delete {id} */
    public function deleteSource()
    {
        $p  = $this->request->getJSON(true) ?? [];
        $id = (int) ($p['id'] ?? 0);
        if ($id > 0) {
            $this->svc()->ensureTables();
            $this->db()->table('tbl_vf_email_sources')->where('id', $id)->delete();
        }

        return $this->json(['ok' => true, 'sources' => $this->svc()->sources()]);
    }

    /** POST interlink/vf-test-request/sources/preview {html, column_map?} : what the table of a pasted mail looks like with this mapping */
    public function previewSource()
    {
        $p    = $this->request->getJSON(true) ?? [];
        $map  = [];
        foreach ((array) ($p['column_map'] ?? []) as $field => $aliases) {
            if (isset(VfTestRequestService::REQUEST_FIELDS[$field])) {
                $list = is_array($aliases) ? $aliases : VfTestRequestService::splitList((string) $aliases);
                $list = array_values(array_filter(array_map('trim', array_map('strval', $list)), static fn ($x) => $x !== ''));
                if ($list) {
                    $map[$field] = $list;
                }
            }
        }
        $html = (string) ($p['html'] ?? '');
        if (trim($html) === '') {
            return $this->json(['ok' => false, 'error' => 'Paste an e-mail table first.'], 422);
        }
        $res = $this->svc()->previewRequestTable($html, $map);
        // keep the preview small: the extra data of every row is enough on the first row only
        foreach ($res['rows'] as $i => &$r) {
            if ($i > 0) {
                unset($r['_extra']);
            }
        }
        unset($r);

        return $this->json($res);
    }

    /**
     * GET interlink/vf-test-request/grid?package=&q=&page=&fresh=
     * The saved copy of one InterLink list page. It answers at once from the copy; when the copy is older than its time limit (or fresh=1)
     * ONE refresh per key is asked from the InterLink service, however many users are looking. The page asks again until refreshing = false.
     */
    public function grid()
    {
        $db   = $this->db();
        \App\Libraries\VfGridCache::ensure($db);
        $pkg   = trim((string) ($this->request->getGet('package') ?? ''));
        $items = VfTestRequestService::searchItems();
        $pkg   = isset($items[$pkg]) ? $items[$pkg] : '';   // the words typed into InterLink's Test Package(s) filter (the cache key uses them too)
        $q     = mb_substr(trim((string) ($this->request->getGet('q') ?? '')), 0, 60);
        $page  = max(1, min(5000, (int) ($this->request->getGet('page') ?? 1)));
        $fresh = (string) ($this->request->getGet('fresh') ?? '') === '1';
        $key   = \App\Libraries\VfGridCache::key($pkg, $q, $page);

        $row   = \App\Libraries\VfGridCache::get($db, $key);
        $age   = $row && ! empty($row['fetched_at']) ? max(0, time() - strtotime((string) $row['fetched_at'])) : null;
        $stale = $age === null || $age > \App\Libraries\VfGridCache::ttl($q, $page) || $fresh;
        $error = '';
        if ($stale) {
            $uid = bin2hex(random_bytes(12));
            if (\App\Libraries\VfGridCache::claim($db, $key, $pkg, $q, $page, $uid)) {
                [$ok, $err] = $this->queueGridJob($uid, $pkg, $q, $page, $fresh);
                if (! $ok) {
                    \App\Libraries\VfGridCache::fail($db, $uid, $err);
                    $error = $err;
                }
            }
            $row = \App\Libraries\VfGridCache::get($db, $key);
        }
        $refreshing = $row && ! empty($row['refreshing_job']);
        if (! $refreshing && $error === '' && $row) {
            $error = (string) ($row['last_error'] ?? '');
        }
        $hit = $row && ! empty($row['fetched_at']);

        return $this->json([
            'ok'         => true,
            'key'        => $key,
            'req_page'   => $page,
            'hit'        => (bool) $hit,
            'rows'       => $hit ? (json_decode((string) $row['rows_json'], true) ?: []) : [],
            'page'       => $hit ? $page : 0,
            'pages'      => $hit ? (int) $row['pages'] : 0,
            'items'      => $hit ? (int) $row['items'] : 0,
            'fetched_at' => $hit ? $row['fetched_at'] : null,
            'age'        => $age,
            'refreshing' => (bool) $refreshing,
            'error'      => $error,
        ]);
    }

    /** queues one vf_grid job (a refresh of the saved copy) on the service; the user name is unique per job so refreshes never replace each other */
    private function queueGridJob(string $uid, string $pkg, string $q, int $page, bool $fresh): array
    {
        $baseUrl = SystemSettings::serviceUrl();
        $key     = (string) SystemSettings::get('interlink.service.key', '');
        if (! SystemSettings::bool('interlink.service.enabled', true) || $baseUrl === '' || strlen($key) < 16) {
            return [false, 'InterLink background service is not configured.'];
        }
        $db = $this->db();
        $db->table('tbl_interlink_service_jobs')->insert([
            'job_uid' => $uid, 'application_no' => 'VF-GRID-SYNC', 'lab_ref_no' => '', 'report_no' => 'Applicant Grid', 'mode' => 'vf_grid',
            'user_id' => 0, 'user_name' => 'cache-' . substr($uid, 0, 6), 'status' => 'queued', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        try {
            $res = service('curlrequest')->post($baseUrl . '/jobs', [
                'headers' => ['X-Service-Key' => $key],
                'json'    => [
                    'job_uid' => $uid, 'application_no' => 'VF-GRID-SYNC', 'report_no' => 'Applicant Grid', 'mode' => 'vf_grid',
                    'account' => 'dtp_qcasst@dtshoes.com', 'user_name' => 'cache-' . substr($uid, 0, 6),
                    'callback_url' => site_url('interlink/service/callback'),
                    'job' => ['packages' => $pkg !== '' ? [$pkg] : array_values(VfTestRequestService::searchItems()), 'package' => $pkg, 'q' => $q, 'page_only' => true, 'page' => $page, 'fresh' => $fresh],
                ],
                'timeout' => 15, 'http_errors' => false,
            ]);
            $body = json_decode((string) $res->getBody(), true) ?: [];
            if ($res->getStatusCode() !== 200 || empty($body['ok'])) {
                throw new \RuntimeException((string) ($body['error'] ?? 'Service HTTP ' . $res->getStatusCode()));
            }
        } catch (\Throwable $e) {
            $db->table('tbl_interlink_service_jobs')->where('job_uid', $uid)->update(['status' => 'failed', 'message' => $e->getMessage(), 'finished_at' => date('Y-m-d H:i:s')]);

            return [false, 'Could not reach NexusInterlinkService: ' . $e->getMessage()];
        }

        return [true, ''];
    }

    /** GET interlink/vf-test-request/job/{uid} : state of a grid sync job, so the page can reload the list when it ends */
    public function gridJob(string $uid)
    {
        $row = $this->db()->table('tbl_interlink_service_jobs')->select('status, message, result_json')->where('job_uid', $uid)->get(1)->getRowArray();
        $res = $row && ($row['status'] ?? '') === 'done' ? json_decode((string) ($row['result_json'] ?? ''), true) : null;

        return $this->json(['ok' => (bool) $row, 'status' => $row['status'] ?? 'unknown', 'message' => $row['message'] ?? '', 'result' => is_array($res) ? $res : null]);
    }

    /** When did the Outlook sync app last deliver an e-mail, from which station? A silent sync is the #1 reason no request shows up. */
    private function syncHealth(): array
    {
        try {
            $db  = $this->db();
            $row = $db->query('SELECT MAX(received_at) AS last_mail, MAX(created_at) AS last_sync, COUNT(*) AS total FROM outlook_attachment_sync.email_records')->getRowArray() ?: [];
            $st  = $db->query('SELECT station_id FROM outlook_attachment_sync.email_records ORDER BY id DESC LIMIT 1')->getRowArray();
            $age = ! empty($row['last_mail']) ? (time() - strtotime((string) $row['last_mail'])) / 3600 : null;

            return ['ok' => true, 'last_mail' => $row['last_mail'] ?? null, 'last_sync' => $row['last_sync'] ?? null, 'total' => (int) ($row['total'] ?? 0), 'station' => $st['station_id'] ?? '', 'stale' => $age === null || $age > 6, 'age_hours' => $age === null ? null : round($age, 1)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'stale' => true];
        }
    }

    /** GET lab-monitoring/vf-requests/data */
    public function monitoringData()
    {
        $this->svc()->ensureTables();
        $link = $this->svc()->linkExistingApplications();   // tests that already exist in InterLink take their Application No. (and the PDF that was e-mailed earlier)
        $db    = $this->db();
        $pkg   = trim((string) ($this->request->getGet('package') ?? ''));
        $stage = trim((string) ($this->request->getGet('stage') ?? ''));
        $q     = trim((string) ($this->request->getGet('q') ?? ''));

        $this->svc()->reconcileOutbox();
        \App\Libraries\MailOutbox::ensureTable();
        $qb = $db->table('tbl_vf_test_requests r')
            ->select('o.status AS outbox_status, o.last_error AS outbox_error, o.attempts AS outbox_attempts, r.*, b.source_channel AS source_channel, b.subject AS batch_subject, b.sender_email AS batch_sender, b.item_count AS batch_item_count, b.submitted_count AS batch_submitted_count, b.email_sent AS batch_email_sent, b.email_sent_at AS batch_email_sent_at, b.auto_send_email AS batch_auto_send')
            ->join('tbl_vf_email_batches b', 'b.id = r.batch_id', 'left')
            ->join('tbl_email_outbox o', 'o.id = r.etrf_outbox_id', 'left');
        if ($pkg !== '') {
            $qb->where('r.test_package', $pkg);
        }
        if ($stage !== '') {
            $qb->where('r.stage', $stage);
        }
        if ($q !== '') {
            $qb->groupStart()
                ->like('r.prod_code', $q)
                ->orLike('r.mtl_no', $q)
                ->orLike('r.style_no', $q)
                ->orLike('r.generated_application_no', $q)
                ->orLike('r.container_no', $q)
                ->orLike('r.lab_report_no', $q)
                ->groupEnd();
        }
        $rows = $qb->orderBy('r.id', 'DESC')->limit(1500)->get()->getResultArray();
        $batches = $db->table('tbl_vf_email_batches')->where('source_channel !=', 'excel_import')->orderBy('id', 'DESC')->limit(50)->get()->getResultArray();

        return $this->json(['ok' => true, 'rows' => $rows, 'batches' => $batches, 'sync_health' => $this->syncHealth(), 'linked' => $link]);
    }

    /** POST lab-monitoring/vf-requests/send-email */
    public function sendBatchEmail()
    {
        $p       = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $batchId = (int) ($p['batch_id'] ?? 0);
        if ($batchId <= 0) {
            return $this->json(['ok' => false, 'error' => 'Select a batch to send.'], 422);
        }
        $opts = $this->mailOpts($p);
        $opts['notify_user_id'] = (int) (((array) service('auth')->user())['id'] ?? 0);
        $res = $this->svc()->sendBatchEmail($batchId, $opts);

        return $this->json($res, ! empty($res['ok']) ? 200 : 422);
    }

    /** POST lab-monitoring/vf-requests/fetch-pdfs {batch_id, request_ids?}: the service prints the eTRF PDFs that are missing */
    public function fetchPdfs()
    {
        $p   = $this->request->getJSON(true) ?? [];
        $res = $this->svc()->queuePdfFetch((int) ($p['batch_id'] ?? 0), array_map('intval', (array) ($p['request_ids'] ?? [])));

        return $this->json($res, ! empty($res['ok']) ? 200 : 502);
    }

    /** POST lab-monitoring/vf-requests/email-preview {batch_id, request_ids[]}: what would be sent, for the dialog */
    public function emailPreview()
    {
        $p       = $this->request->getJSON(true) ?? [];
        $batchId = (int) ($p['batch_id'] ?? 0);
        if ($batchId <= 0) {
            return $this->json(['ok' => false, 'error' => 'Select a batch.'], 422);
        }
        $res = $this->svc()->previewBatchEmail($batchId, $this->mailOpts($p));

        return $this->json($res, ! empty($res['ok']) ? 200 : 422);
    }

    /** the options of the dialog: ticked rows, To / CC / Subject, reply or not */
    private function mailOpts(array $p): array
    {
        $o = [];
        if (! empty($p['request_ids']) && is_array($p['request_ids'])) {
            $o['request_ids'] = array_map('intval', $p['request_ids']);
        }
        foreach (['to', 'cc', 'subject'] as $k) {
            if (array_key_exists($k, $p)) {
                $o[$k] = trim((string) $p[$k]);
            }
        }
        if (array_key_exists('reply', $p)) {
            $o['reply'] = ! empty($p['reply']);
        }
        if (! empty($p['resend_all'])) {
            $o['resend_all'] = true;
        }

        return $o;
    }

    /** POST lab-monitoring/vf-requests/toggle-auto */
    public function toggleAutoEmail()
    {
        $p       = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $batchId = (int) ($p['batch_id'] ?? 0);
        $auto    = ! empty($p['auto_send_email']) ? 1 : 0;
        if ($batchId <= 0) {
            return $this->json(['ok' => false, 'error' => 'Invalid batch_id.'], 422);
        }
        $this->svc()->ensureTables();
        $this->db()->table('tbl_vf_email_batches')->where('id', $batchId)->update(['auto_send_email' => $auto, 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->json(['ok' => true, 'auto_send_email' => $auto]);
    }

    /** POST lab-monitoring/vf-requests/update-row : update container, lab report #, expected due date, result */
    public function updateRow()
    {
        $this->svc()->ensureTables();
        $p  = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $id = (int) ($p['id'] ?? 0);
        if ($id <= 0) {
            return $this->json(['ok' => false, 'error' => 'Missing request id.'], 422);
        }
        $upd = ['updated_at' => date('Y-m-d H:i:s')];
        foreach (['container_no', 'lab_name', 'lab_report_no', 'test_result', 'stage', 'generated_application_no'] as $k) {
            if (array_key_exists($k, $p)) {
                $upd[$k] = trim((string) $p[$k]);
            }
        }
        foreach (['expected_due_date', 'issue_date', 'validity_date'] as $dk) {
            if (array_key_exists($dk, $p)) {
                $v = trim((string) $p[$dk]);
                $upd[$dk] = $v !== '' && ($ts = strtotime($v)) ? date('Y-m-d', $ts) : null;
            }
        }
        $this->db()->table('tbl_vf_test_requests')->where('id', $id)->update($upd);

        return $this->json(['ok' => true, 'message' => 'Updated monitoring row.']);
    }

    /** GET lab-monitoring/vf-requests/pdf/(:segment)/(:num) (kind = etrf | cert) */
    public function pdf(string $kind, int $id)
    {
        $this->svc()->ensureTables();
        $row = $this->db()->table('tbl_vf_test_requests')->where('id', $id)->get(1)->getRowArray();
        if (! $row) {
            return $this->response->setStatusCode(404)->setBody('Request not found.');
        }
        $path = $kind === 'cert' ? (string) ($row['certificate_pdf_path'] ?? '') : (string) ($row['etrf_pdf_path'] ?? '');
        $name = $kind === 'cert' ? (string) ($row['certificate_pdf_name'] ?? 'certificate.pdf') : (string) ($row['etrf_pdf_name'] ?? 'etrf.pdf');
        if ($path === '' || ! is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('PDF file has not been stored yet.');
        }

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . str_replace('"', '', $name) . '"')
            ->setBody((string) file_get_contents($path));
    }
}
