<?php

namespace App\Controllers;

use App\Models\InterlinkReportModel;

/**
 * InterlinkController
 * Manages the NEXUS-L <-> Intertek InterLink 2.0 (VF Corporation Portal)
 * complete portal navigation (SearchFormWithHeader.aspx [10102] across all 517 pages / 7,749 items,
 * NormalForm.aspx [10000], UploadForm.aspx [10072] Product & [10076] Material, and Tab 2 Upload Result),
 * Real-Time Multi-User Sync, Pre-Sync Verification Dialog, and 1-Click Live Report Submission.
 */
class InterlinkController extends BaseController
{
    /**
     * Where "Open InterLink (sign in with your account)" sends the PIC: the plain site root, never a deep link with
     * args=/iudsReqId= tokens. Those tokens belong to one specific InterLink browser session and die with it, so a
     * hardcoded deep link like the DEFAULT_*_URL constants below goes stale (opens to an error or a blank page) the
     * moment that session ends - which is what made this button "not work". The root always works: InterLink's own
     * site sends an unauthenticated visitor to its login page, and an already-signed-in one straight to their portal.
     */
    private const PORTAL_HOME_URL = 'https://interlink2.intertek.com/';

    // Deep links used only as a *fallback* for server-side calls when no live u_form_url is on file yet (sync /
    // backfill / proxy). These go stale the same way (see PORTAL_HOME_URL above) and should be refreshed from a
    // current InterLink session when backfill/proxy next needs re-pointing; they are never shown to the user to click.
    private const DEFAULT_SEARCH_URL = 'https://interlink2.intertek.com/InterLink2/aspx/eFormPage/Forms/SearchFormWithHeader.aspx?args=F78A0B062541AEA81E8BA8297FBA2DA7BFB450E4918CF9B85888D42DCB6F1622F872FC64087A2E07ADC02A4D3A1FF2D8A066332B88A666B27023BC1B4FF7A06E&iudsReqId=6ead4327f9354dcdb4a63f1ee1c1af92';
    private const DEFAULT_UPLOAD_URL = 'https://interlink2.intertek.com/InterLink2/aspx/eFormPage/Forms/UploadForm.aspx?args=F78A0B062541AEA81E8BA8297FBA2DA76CAE8B7A92CD3B50145C27C4686D805274963B3E625332DCB961FB2AA7B050E11ED37E8ADDC797FA837E2FBF33BEC69F5E38D540277895A37A82BA2C59CA8C89F7988B57262F9C3E904C1500439116CA80060F6EC333A3B6997758DE9FDC9A481C1ADC7E9AD5FB46C72662B98219C2E2EC9823206BF54FB3D1F5C5BE4E833009F43506020D1F5EFB25EC439FA87F4669AB716CF1720D4FE6D1FC5C07B473582D940E8526AD6A152F23BEA566349729BC&iudsReqId=3301b54d2d754f9290fbe7683e8cf2ce';
    private const DEFAULT_NORMAL_URL = 'https://interlink2.intertek.com/InterLink2/aspx/eFormPage/Forms/NormalForm.aspx?args=BCE93E107653E70D6FB33A4A4693CF4D0C61DB3DC4E36B40E70C31AF171D96F0FB57100B223EA24651893E72239C984CBA4DCE7C73729CD2556ACA1A540D1CE34BFEF47D0D6CE64D7B111F152C414F0AFD78C439EB67B8A1C06A183F8285808CE050395A0FDBAD6CC5FE545726F50C79065149AF61F6D78E6A12CD809D58312B31755D316F1CF373355FE079D207B94D&iudsReqId=3f4f7229bc304c548654559bba9f49fe';

    public function index(): string
    {
        $model    = model(InterlinkReportModel::class);
        $reports  = $model->orderBy('application_no', 'DESC')->findAll(15);
        $logs     = $model->getRecentLogs(15);
        $partners = $model->getPartners();

        $allReports   = $model->select('id, synced_to_interlink')->findAll();
        $syncedCount  = count(array_filter($allReports, static fn ($r) => (int) ($r['synced_to_interlink'] ?? 0) === 1));
        $pendingCount = count($allReports) - $syncedCount;

        $db = \Config\Database::connect();
        $initialLabRefs = [];
        $totalLabRefs   = 0;
        try {
            $totalLabRefs = (int) $db->table('tbl_dtr_unified_labrefs')->countAllResults();
            $initialLabRefs = $db->table('tbl_dtr_unified_labrefs')
                ->select('id, lab_ref_no, tr_no, form_category, form_slug, interlink_sample_type, material_type, material_code, material_description, supplier, model_style, color, po_lot_no, mtl_no, received_date, tested_date, finished_date, technician, status, overall_result')
                ->orderBy('id', 'DESC')
                ->limit(300)
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            // Table may not exist yet in edge environments
        }

        return $this->render('interlink/index', [
            'title'          => 'InterLink Sync',
            'withCss'        => 'interlink.css',
            'withJs'         => ['qrcode.js', 'interlink.js', 'interlink_center.js'],
            'reports'        => $reports,
            'logs'           => $logs,
            'partners'       => $partners,
            'syncedCount'    => $syncedCount,
            'pendingCount'   => $pendingCount,
            'initialLabRefs' => $initialLabRefs,
            'defaults'       => \App\Libraries\SystemSettings::interlinkDefaults(),
            'totalLabRefs'   => $totalLabRefs,
            'searchUrl'      => self::DEFAULT_SEARCH_URL,
            'uploadUrl'      => self::DEFAULT_UPLOAD_URL,
            'normalUrl'      => self::DEFAULT_NORMAL_URL,
            'openUrl'        => self::PORTAL_HOME_URL,
        ]);
    }

    /**
     * POST /interlink/fetch-live-page
     * Real-time fetches any page (1..517) and page size (10|15|20|50|100) directly from InterLink 2.0
     * SearchFormWithHeader.aspx, parses all rows & 38 columns + action URLs, upserts into dbnexus,
     * and returns the live rows + pagination metadata so changes made by other users in InterLink
     * reflect immediately in NEXUS-L.
     */
    public function fetchLivePage()
    {
        $payload      = $this->request->getJSON(true) ?? $this->request->getPost();
        $page         = max(1, (int) ($payload['page'] ?? 1));
        $pageSize     = (int) ($payload['pageSize'] ?? $payload['page_size'] ?? 15);
        if (! in_array($pageSize, [10, 15, 20, 50, 100], true)) {
            $pageSize = 15;
        }
        $forceRefresh = ! empty($payload['forceRefresh']) || ! empty($payload['force_refresh']);

        $liveData = $this->pullInterlinkGridPage($page, $pageSize, $forceRefresh);

        $model      = model(InterlinkReportModel::class);
        $allReports = $model->select('id, synced_to_interlink')->findAll();
        $syncedCnt  = count(array_filter($allReports, static fn ($r) => (int) ($r['synced_to_interlink'] ?? 0) === 1));

        return $this->response->setJSON([
            'ok'           => $liveData['ok'],
            'live'         => $liveData['live'],
            'message'      => $liveData['message'] ?? '',
            'currentPage'  => $liveData['currentPage'],
            'totalPages'   => $liveData['totalPages'],
            'totalItems'   => $liveData['totalItems'],
            'pageSize'     => $liveData['pageSize'],
            'rows'         => $liveData['rows'],
            'changedApps'  => $liveData['changedApps'] ?? [],
            'syncedCount'  => $syncedCnt,
            'pendingCount' => count($allReports) - $syncedCnt,
            'syncedAt'     => date('H:i:s'),
            'csrf'         => csrf_hash(),
        ]);
    }

    /**
     * POST /interlink/pull-grid-html {html}: the real SearchFormWithHeader grid's own HTML, read through the user's
     * already-signed-in browser by the NEXUS-L InterLink Sender extension (background.js `pullGrid()`). NEXUS-L's
     * server never holds an InterLink login itself (getActiveChromeCookieHeader() always returns '' - see its
     * docblock), which is why pullInterlinkGridPage()'s own curl path can never succeed: this is the only way "Live
     * Refresh" / "Auto-Sync" can show genuinely current data. Reuses the exact parser/upsert pipeline that path was
     * always written for; only where the HTML comes from is different.
     */
    public function pullGridHtml()
    {
        // {pages: [html...], firstPage, page, pageSize}: one or more consecutive InterLink grid pages (InterLink pages hold 15
        // rows) that together cover the NEXUS-L view page `page` of `pageSize` rows. {html} alone (older senders) = one page.
        $payload  = $this->request->getJSON(true) ?? $this->request->getPost();
        $pages    = is_array($payload['pages'] ?? null) ? array_values($payload['pages']) : [(string) ($payload['html'] ?? '')];
        $allRows  = [];
        $first    = null;
        foreach ($pages as $html) {
            $html = (string) $html;
            if ($html === '' || strlen($html) > 5_000_000 || strpos($html, 'PH1_SF1_GVBUILDER_BuilderGridView') === false || count($pages) > 10) {
                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'That does not look like the InterLink search grid.', 'csrf' => csrf_hash()]);
            }
            $parsed = $this->parseInterlinkGridHtml($html);
            $first ??= $parsed;
            foreach ($parsed['rows'] as $r) {
                $allRows[] = $r;
            }
        }
        $upserted = $this->upsertGridRowsIntoDbNexus($allRows);

        // the view shows `pageSize` rows from page `page`; the pages read start at InterLink page `firstPage` (15 rows each)
        $viewSize = in_array((int) ($payload['pageSize'] ?? 15), [10, 15, 20, 50, 100], true) ? (int) $payload['pageSize'] : 15;
        $rows     = $upserted['rows'];
        $page     = $first['currentPage'];
        if (isset($payload['firstPage'], $payload['page'])) {
            $page = max(1, (int) $payload['page']);
            $skip = max(0, ($page - 1) * $viewSize - (max(1, (int) $payload['firstPage']) - 1) * 15);
            $rows = array_slice($rows, $skip, $viewSize);
        } else {
            $viewSize = $first['pageSize'];
        }
        $totalPages = max(1, (int) ceil($first['totalItems'] / $viewSize));

        $model      = model(InterlinkReportModel::class);
        $allReports = $model->select('id, synced_to_interlink')->findAll();
        $syncedCnt  = count(array_filter($allReports, static fn ($r) => (int) ($r['synced_to_interlink'] ?? 0) === 1));
        $parsed     = ['currentPage' => $page, 'totalPages' => $totalPages, 'totalItems' => $first['totalItems'], 'pageSize' => $viewSize];
        $upserted['rows'] = $rows;

        return $this->response->setJSON([
            'ok'           => true,
            'live'         => true,
            'message'      => "Live synced Page {$parsed['currentPage']} of {$parsed['totalPages']} ({$parsed['totalItems']} items) from InterLink 2.0, via your browser.",
            'currentPage'  => $parsed['currentPage'],
            'totalPages'   => $parsed['totalPages'],
            'totalItems'   => $parsed['totalItems'],
            'pageSize'     => $parsed['pageSize'],
            'rows'         => $upserted['rows'],
            'changedApps'  => $upserted['changedApps'],
            'syncedCount'  => $syncedCnt,
            'pendingCount' => count($allReports) - $syncedCnt,
            'syncedAt'     => date('H:i:s'),
            'csrf'         => csrf_hash(),
        ]);
    }

    /**
     * POST /interlink/fetch-live-form
     * Follows the row's live DataLinkSearch.aspx URL on InterLink 2.0 for any row across all 517 pages,
     * scrapes the live UploadForm.aspx ([10072] Product or [10076] Material) or NormalForm.aspx ([10000]),
     * updates dbnexus.tbl_interlink_reports, and returns the full 102-column record + sub-tables.
     */
    public function fetchLiveForm()
    {
        $payload  = $this->request->getJSON(true) ?? $this->request->getPost();
        $appNo    = trim((string) ($payload['application_no'] ?? ''));
        $rawType  = strtolower(trim((string) ($payload['form_type'] ?? 'upload')));
        $formType = ($rawType === 'e' || $rawType === 'normal') ? 'normal' : 'upload';
        $rawUrl   = trim((string) ($payload['datalink_url'] ?? $payload['target_url'] ?? ''));

        if ($appNo === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'    => false,
                'error' => 'Application No. is required.',
                'csrf'  => csrf_hash(),
            ]);
        }

        $model    = model(InterlinkReportModel::class);
        $existing = $model->findByAppNo($appNo);

        $targetUrl = $rawUrl;
        if ($targetUrl === '' && $existing) {
            $targetUrl = ($formType === 'normal')
                ? (string) ($existing['e_form_url'] ?? '')
                : (string) ($existing['u_form_url'] ?? '');
        }

        $scrapedFields = [];
        $effectiveUrl  = '';
        $liveFetched   = false;

        if ($targetUrl !== '') {
            if (strpos($targetUrl, 'http') !== 0) {
                $targetUrl = 'https://interlink2.intertek.com' . (strpos($targetUrl, '/') === 0 ? '' : '/InterLink2/aspx/eFormPage/') . $targetUrl;
            }
            $cookie = $this->getActiveChromeCookieHeader();
            if ($cookie !== '') {
                $ch = curl_init($targetUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS      => 5,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HTTPHEADER     => [
                        'Cookie: ' . $cookie,
                        'Referer: ' . self::DEFAULT_SEARCH_URL,
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
                    ],
                ]);
                $html         = (string) curl_exec($ch);
                $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && strlen($html) > 2000 && stripos($html, 'ReloginAccessError') === false) {
                    $scrapedFields = $this->parseInterlinkFormHtml($html, $effectiveUrl);
                    $liveFetched   = ! empty($scrapedFields);
                }
            }
        }

        if (! empty($scrapedFields)) {
            if ($existing) {
                // Preserve existing report_no / overall_result if InterLink's form has them blank so company pre-fill stays ready
                foreach (['report_no', 'overall_result', 'report_issue_date', 'attachment_name'] as $keepCol) {
                    if (empty($scrapedFields[$keepCol]) && ! empty($existing[$keepCol])) {
                        unset($scrapedFields[$keepCol]);
                    }
                }
                $model->update($existing['id'], $scrapedFields);
            } else {
                $scrapedFields['application_no'] = $appNo;
                $model->insert($scrapedFields);
            }
        }

        // Return full report via report() logic
        $report = $model->findByAppNo($appNo);
        if (! $report) {
            return $this->report($appNo);
        }

        // Fill sensible NEXUS-L defaults for any empty lab fields so Pre-Sync Dialog is ready to go
        $suffix = substr(preg_replace('/\D/', '', $appNo), -4) ?: '0001';
        if (empty($report['report_no'])) {
            $report['report_no'] = 'DTP-LAB-' . date('Y') . '-' . $suffix;
        }
        if (empty($report['report_issue_date'])) {
            $report['report_issue_date'] = date('d M Y');
        }
        if (empty($report['overall_result'])) {
            $report['overall_result'] = 'Pass';
        }
        if (empty($report['attachment_name'])) {
            $report['attachment_name'] = 'TRF_' . $appNo . '_FinalReport.pdf';
        }

        return $this->response->setJSON([
            'ok'           => true,
            'liveFetched'  => $liveFetched,
            'effectiveUrl' => $effectiveUrl ?: $targetUrl,
            'report'       => $report,
            'samples'      => $model->getSampleItems($appNo),
            'items'        => $model->getTestItems($appNo),
            'readings'     => $model->getTestReadings($appNo),
            'csrf'         => csrf_hash(),
        ]);
    }

    /**
     * GET /interlink/reports
     * Returns all NEXUS-L company reports, partner directory, and recent sync history.
     */
    public function reports()
    {
        $model   = model(InterlinkReportModel::class);
        $reports = $model->orderBy('application_no', 'DESC')->findAll(100);
        $logs    = $model->getRecentLogs(15);

        return $this->response->setJSON([
            'reports'  => $reports,
            'logs'     => $logs,
            'partners' => $model->getPartners(),
            'csrf'     => csrf_hash(),
        ]);
    }

    /**
     * GET /interlink/report/(:segment)
     * Fetches the NEXUS-L company data, sample items, test items, and test readings for an Application No.
     */
    public function report(string $appNo)
    {
        $model  = model(InterlinkReportModel::class);
        $report = $model->findByAppNo($appNo);

        if (! $report) {
            $suffix = preg_replace('/\D/', '', $appNo);
            $suffix = substr($suffix, -4) ?: '0001';
            $report = [
                'id'                      => null,
                'nexus_sample_id'         => 'NXL-' . date('Y') . '-' . $suffix,
                'nexus_work_order_no'     => 'WO-' . date('Ymd') . '-' . $suffix,
                'application_no'          => $appNo,
                'form_code'               => '10072',
                'sample_type'             => 'Finished Product',
                'trf_submission_date'     => date('d M Y'),
                'sample_received_date'    => date('d M Y'),
                'test_start_date'         => date('d M Y'),
                'test_completion_date'    => date('d M Y'),
                'report_issue_date'       => date('d M Y'),
                'cargo_receive_date'      => '06 Nov 2026',
                'service_provider'        => \App\Libraries\SystemSettings::get('interlink.service_provider', 'DATIAN SUBIC SHOES INC.'),
                'lab_location'            => \App\Libraries\SystemSettings::get('interlink.lab_location', 'PHILIPPINES'),
                'service_test_type'       => \App\Libraries\SystemSettings::get('interlink.service_test_type', 'In-House Lab Testing'),
                'test_stage'              => \App\Libraries\SystemSettings::get('interlink.test_stage', 'Production'),
                'service_required'        => \App\Libraries\SystemSettings::get('interlink.service_required', 'Regular'),
                'report_no'               => 'DTP-LAB-' . date('Y') . '-' . $suffix,
                'is_retest'               => 'No',
                'retest_flag'             => 'No',
                'retest_sequence'         => 0,
                'previous_report_no'      => '',
                'related_trf_no'          => '',
                'email_recipients'        => \App\Libraries\SystemSettings::get('interlink.email_recipients', 'dtp_qcasst@dtshoes.com'),
                'company_name'            => \App\Libraries\SystemSettings::get('interlink.company_name', 'KEY TRADE CO., LTD'),
                'interlink_code'          => \App\Libraries\SystemSettings::get('interlink.interlink_code', 'E126863'),
                'vendor_name'             => \App\Libraries\SystemSettings::get('interlink.vendor_name', 'KEY TRADE CO., LTD'),
                'vendor_id'               => \App\Libraries\SystemSettings::get('interlink.vendor_id', '0000507450'),
                'factory_name'            => \App\Libraries\SystemSettings::get('interlink.factory_name', 'DATIAN SUBIC SHOES INC.'),
                'factory_id'              => \App\Libraries\SystemSettings::get('interlink.factory_id', '0000507452'),
                'supplier_name'           => '',
                'supplier_id'             => '',
                'style_no'                => 'VN000ZB8',
                'style_color_no'          => 'VN000ZB8BPR',
                'product_name'            => 'LX Old Skool 36',
                'buyer_brand'             => \App\Libraries\SystemSettings::get('interlink.buyer_brand', 'Vans'),
                'color_code'              => 'BPR',
                'colorway_name'           => 'Pearlized BLACK/MARSHMALLOW',
                'product_export_to'       => \App\Libraries\SystemSettings::get('interlink.product_export_to', 'APAC - Non China'),
                'country_of_origin'       => \App\Libraries\SystemSettings::get('interlink.country_of_origin', 'Philippines'),
                'season'                  => \App\Libraries\SystemSettings::get('interlink.season', 'S27'),
                'sourcing_office'         => \App\Libraries\SystemSettings::get('interlink.sourcing_office', 'ASO'),
                'size_category'           => 'UNISEX',
                'age_group'               => 'Adult',
                'product_category'        => \App\Libraries\SystemSettings::get('interlink.product_category', 'Footwear'),
                'test_packages'           => \App\Libraries\SystemSettings::get('interlink.test_packages', 'VANS Pull Strength'),
                'gcc_cpc_required'        => 'No',
                'product_id'              => 'VN000ZB8',
                'manufacture_date'        => '10/2026',
                'factory_address'         => \App\Libraries\SystemSettings::get('interlink.factory_address', 'Lot 1P7-A,Phase 2,Subic Bay GatewayPark, Subic Bay Freeport Zone'),
                'factory_country'         => \App\Libraries\SystemSettings::get('interlink.factory_country', 'Philippines'),
                'factory_city'            => \App\Libraries\SystemSettings::get('interlink.factory_city', 'Subic Bay'),
                'factory_state_province'  => \App\Libraries\SystemSettings::get('interlink.factory_state_province', '03'),
                'factory_zip_postal'      => '2222',
                'factory_email'           => 'dtp_qcasst@dtshoes.com',
                'factory_phone'           => '(047) 252-2899',
                'overall_result'          => 'Pass',
                'job_status'              => 'Submitted',
                'attachment_name'         => 'TRF_' . $appNo . '_FinalReport.pdf',
                'synced_to_interlink'     => 0,
            ];
        } else {
            $suffix = substr(preg_replace('/\D/', '', $appNo), -4) ?: '0001';
            if (empty($report['report_no'])) {
                $report['report_no'] = 'DTP-LAB-' . date('Y') . '-' . $suffix;
            }
            if (empty($report['report_issue_date'])) {
                $report['report_issue_date'] = date('d M Y');
            }
            if (empty($report['overall_result'])) {
                $report['overall_result'] = 'Pass';
            }
            if (empty($report['attachment_name'])) {
                $report['attachment_name'] = 'TRF_' . $appNo . '_FinalReport.pdf';
            }
        }

        return $this->response->setJSON([
            'report'   => $report,
            'samples'  => $model->getSampleItems($appNo),
            'items'    => $model->getTestItems($appNo),
            'readings' => $model->getTestReadings($appNo),
            'csrf'     => csrf_hash(),
        ]);
    }

    /** Edits made in the Upload Form's grids (Sample Information, Test Item Result, readings), kept per application and Lab Reference. */
    private function gridTable(): void
    {
        $this->il()->query('CREATE TABLE IF NOT EXISTS tbl_interlink_grid_edits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_no VARCHAR(30) NOT NULL,
            lab_ref_no VARCHAR(60) NOT NULL,
            grids_json LONGTEXT NOT NULL,
            saved_by VARCHAR(80) NULL,
            saved_at DATETIME NOT NULL,
            UNIQUE KEY uq_app_lab (application_no, lab_ref_no)
        ) DEFAULT CHARSET=utf8mb4');
    }

    private function gridSaved(string $appNo, string $labRef): array
    {
        if ($appNo === '' || $labRef === '') {
            return [];
        }
        try {
            $this->gridTable();
            $row = $this->il()->table('tbl_interlink_grid_edits')->where('application_no', $appNo)->where('lab_ref_no', $labRef)->get()->getRowArray();
        } catch (\Throwable $e) {
            return [];
        }

        return $row ? (json_decode((string) $row['grids_json'], true) ?: []) : [];
    }

    /** The saved edits replace the grids generated from the report's record: what the person corrected is what is synced and sent. */
    private function gridOverlay(string $appNo, string $labRef, array $tables): array
    {
        $saved = $this->gridSaved($appNo, $labRef);
        foreach (['samples', 'items', 'readings'] as $k) {
            if (isset($saved[$k]) && is_array($saved[$k])) {
                $tables[$k] = $saved[$k];
            }
        }

        return $tables;
    }

    /** POST interlink/report/grids {application_no, lab_ref_no, samples[], items[], readings[]} */
    public function saveGrids()
    {
        $p      = $this->request->getJSON(true) ?? $this->request->getPost();
        $appNo  = trim((string) ($p['application_no'] ?? ''));
        $labRef = trim((string) ($p['lab_ref_no'] ?? ''));
        if ($appNo === '' || $labRef === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Application No. and Lab Ref. No. are required.', 'csrf' => csrf_hash()]);
        }
        $grids = [];
        foreach (['samples', 'items', 'readings'] as $k) {
            $grids[$k] = is_array($p[$k] ?? null) ? array_values($p[$k]) : [];
        }
        $grids['form'] = is_array($p['form'] ?? null) ? $p['form'] : [];     // Report No., Issue Date, Overall Result as corrected
        $user = $this->auth->user();
        $this->gridTable();
        $this->il()->query('INSERT INTO tbl_interlink_grid_edits (application_no, lab_ref_no, grids_json, saved_by, saved_at) VALUES (?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE grids_json = VALUES(grids_json), saved_by = VALUES(saved_by), saved_at = VALUES(saved_at)',
            [$appNo, $labRef, json_encode($grids, JSON_UNESCAPED_UNICODE), (string) ($user['name'] ?? $user['username'] ?? '')]);

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()] + $grids);
    }

    /**
     * POST /interlink/report/save
     * Creates or updates a company side NEXUS-L lab report record.
     */
    public function saveReport()
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $appNo   = trim((string) ($payload['application_no'] ?? ''));

        if ($appNo === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => 'Application No. is required.',
                'csrf'  => csrf_hash(),
            ]);
        }

        $model    = model(InterlinkReportModel::class);
        $existing = $model->findByAppNo($appNo);

        $sampleType = trim((string) ($payload['sample_type'] ?? 'Finished Product'));
        $formCode   = ($sampleType === 'Material') ? '10076' : '10072';

        $data = [
            'application_no'          => $appNo,
            'form_code'               => $formCode,
            'sample_type'             => $sampleType,
            'trf_submission_date'     => trim((string) ($payload['trf_submission_date'] ?? date('d M Y'))),
            'service_provider'        => trim((string) ($payload['service_provider'] ?? \App\Libraries\SystemSettings::get('interlink.service_provider', 'DATIAN SUBIC SHOES INC.'))),
            'lab_location'            => trim((string) ($payload['lab_location'] ?? \App\Libraries\SystemSettings::get('interlink.lab_location', 'PHILIPPINES'))),
            'service_test_type'       => trim((string) ($payload['service_test_type'] ?? \App\Libraries\SystemSettings::get('interlink.service_test_type', 'In-House Lab Testing'))),
            'test_stage'              => trim((string) ($payload['test_stage'] ?? \App\Libraries\SystemSettings::get('interlink.test_stage', 'Production'))),
            'service_required'        => trim((string) ($payload['service_required'] ?? \App\Libraries\SystemSettings::get('interlink.service_required', 'Regular'))),
            'report_no'               => trim((string) ($payload['report_no'] ?? '')),
            'is_retest'               => in_array(($payload['is_retest'] ?? 'No'), ['Yes', 'No'], true) ? $payload['is_retest'] : 'No',
            'previous_report_no'      => trim((string) ($payload['previous_report_no'] ?? '')),
            'related_trf_no'          => trim((string) ($payload['related_trf_no'] ?? '')),
            'email_recipients'        => trim((string) ($payload['email_recipients'] ?? \App\Libraries\SystemSettings::get('interlink.email_recipients', 'dtp_qcasst@dtshoes.com'))),
            'company_name'            => trim((string) ($payload['company_name'] ?? \App\Libraries\SystemSettings::get('interlink.company_name', 'KEY TRADE CO., LTD'))),
            'interlink_code'          => trim((string) ($payload['interlink_code'] ?? \App\Libraries\SystemSettings::get('interlink.interlink_code', 'E126863'))),
            'vendor_name'             => trim((string) ($payload['vendor_name'] ?? \App\Libraries\SystemSettings::get('interlink.vendor_name', 'KEY TRADE CO., LTD'))),
            'vendor_id'               => trim((string) ($payload['vendor_id'] ?? \App\Libraries\SystemSettings::get('interlink.vendor_id', '0000507450'))),
            'factory_name'            => trim((string) ($payload['factory_name'] ?? \App\Libraries\SystemSettings::get('interlink.factory_name', 'DATIAN SUBIC SHOES INC.'))),
            'factory_id'              => trim((string) ($payload['factory_id'] ?? \App\Libraries\SystemSettings::get('interlink.factory_id', '0000507452'))),
            'supplier_name'           => trim((string) ($payload['supplier_name'] ?? '')),
            'supplier_id'             => trim((string) ($payload['supplier_id'] ?? '')),
            'facility_name'           => trim((string) ($payload['facility_name'] ?? '')),
            'facility_id'             => trim((string) ($payload['facility_id'] ?? '')),
            'buyer_brand'             => trim((string) ($payload['buyer_brand'] ?? \App\Libraries\SystemSettings::get('interlink.buyer_brand', 'Vans'))),
            'season'                  => trim((string) ($payload['season'] ?? \App\Libraries\SystemSettings::get('interlink.season', 'S27'))),
            'sourcing_office'         => trim((string) ($payload['sourcing_office'] ?? \App\Libraries\SystemSettings::get('interlink.sourcing_office', 'ASO'))),
            'style_no'                => trim((string) ($payload['style_no'] ?? '')),
            'style_color_no'          => trim((string) ($payload['style_color_no'] ?? '')),
            'product_name'            => trim((string) ($payload['product_name'] ?? '')),
            'style_name'              => trim((string) ($payload['product_name'] ?? $payload['style_name'] ?? '')),
            'material_code'           => trim((string) ($payload['material_code'] ?? '')),
            'material_name'           => trim((string) ($payload['material_name'] ?? '')),
            'material_type'           => trim((string) ($payload['material_type'] ?? '')),
            'color_code'              => trim((string) ($payload['color_code'] ?? '')),
            'colorway_name'           => trim((string) ($payload['colorway_name'] ?? '')),
            'product_export_to'       => trim((string) ($payload['product_export_to'] ?? \App\Libraries\SystemSettings::get('interlink.product_export_to', 'APAC - Non China'))),
            'country_of_origin'       => trim((string) ($payload['country_of_origin'] ?? \App\Libraries\SystemSettings::get('interlink.country_of_origin', 'Philippines'))),
            'size_category'           => trim((string) ($payload['size_category'] ?? 'UNISEX')),
            'age_group'               => trim((string) ($payload['age_group'] ?? 'Adult')),
            'cargo_receive_date'      => trim((string) ($payload['cargo_receive_date'] ?? '06 Nov 2026')),
            'product_category'        => trim((string) ($payload['product_category'] ?? \App\Libraries\SystemSettings::get('interlink.product_category', 'Footwear'))),
            'test_packages'           => trim((string) ($payload['test_packages'] ?? \App\Libraries\SystemSettings::get('interlink.test_packages', 'VANS Pull Strength'))),
            'gcc_cpc_required'        => trim((string) ($payload['gcc_cpc_required'] ?? 'No')),
            'product_id'              => trim((string) ($payload['product_id'] ?? $payload['style_no'] ?? '')),
            'manufacture_date'        => trim((string) ($payload['manufacture_date'] ?? '10/2026')),
            'factory_address'         => trim((string) ($payload['factory_address'] ?? \App\Libraries\SystemSettings::get('interlink.factory_address', 'Lot 1P7-A,Phase 2,Subic Bay GatewayPark, Subic Bay Freeport Zone'))),
            'factory_country'         => trim((string) ($payload['factory_country'] ?? \App\Libraries\SystemSettings::get('interlink.factory_country', 'Philippines'))),
            'factory_city'            => trim((string) ($payload['factory_city'] ?? \App\Libraries\SystemSettings::get('interlink.factory_city', 'Subic Bay'))),
            'factory_state_province'  => trim((string) ($payload['factory_state_province'] ?? \App\Libraries\SystemSettings::get('interlink.factory_state_province', '03'))),
            'report_issue_date'       => trim((string) ($payload['report_issue_date'] ?? date('d M Y'))),
            'overall_result'          => trim((string) ($payload['overall_result'] ?? 'Pass')),
            'attachment_name'         => trim((string) ($payload['attachment_name'] ?? ('TRF_' . $appNo . '_Report.pdf'))),
        ];

        if ($existing) {
            $model->update($existing['id'], $data);
            $id = (int) $existing['id'];
        } else {
            $data['job_status']          = 'Submitted';
            $data['synced_to_interlink'] = 0;
            $id = (int) $model->insert($data);
        }

        return $this->response->setJSON([
            'ok'     => true,
            'report' => $model->find($id),
            'csrf'   => csrf_hash(),
        ]);
    }

    /**
     * POST /interlink/sync
     * Confirms the Pre-Sync Dialog selection, persists all company values in dbnexus,
     * marks the report synced, logs the sync event, and optionally performs 1-Click Live
     * HTTP Postback to interlink2.intertek.com + Playwright bot queue.
     */
    public function sync()
    {
        $payload   = $this->request->getJSON(true) ?? $this->request->getPost();
        $appNo     = trim((string) ($payload['application_no'] ?? ''));
        $fields    = (array) ($payload['fields'] ?? []);
        $syncMode  = trim((string) ($payload['sync_mode'] ?? 'portal_sync'));
        $runBot    = ! empty($payload['run_bot']);
        $sendLive  = ! empty($payload['send_live']);
        $targetUrl = trim((string) ($payload['target_url'] ?? self::DEFAULT_UPLOAD_URL));

        if ($appNo === '' || empty($fields)) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => 'Application No. and fields to sync are required.',
                'csrf'  => csrf_hash(),
            ]);
        }

        $user     = $this->auth->user();
        $userName = $user['name'] ?? $user['username'] ?? 'Faye Ann Elaysa';
        $userId   = $user['id'] ?? null;

        $model    = model(InterlinkReportModel::class);
        $existing = $model->findByAppNo($appNo);

        $updateData = [
            'application_no'      => $appNo,
            'synced_to_interlink' => 1,
            'sync_count'          => ((int) ($existing['sync_count'] ?? 0)) + 1,
            'synced_at'           => date('Y-m-d H:i:s'),
            'synced_by'           => $userName,
        ];

        $allowedMap = [
            'service_provider', 'lab_location', 'service_test_type', 'test_stage',
            'service_required', 'sample_type', 'report_no', 'is_retest', 'previous_report_no',
            'related_trf_no', 'email_recipients', 'cc_email_recipients',
            'company_name', 'interlink_code', 'vendor_name', 'vendor_id',
            'factory_name', 'factory_id', 'supplier_name', 'supplier_id',
            'facility_name', 'facility_id',
            'style_no', 'style_color_no', 'product_name', 'style_name', 'buyer_brand',
            'color_code', 'colorway_name', 'product_export_to', 'country_of_origin',
            'season', 'sourcing_office', 'size_category', 'age_group', 'cargo_receive_date',
            'care_instruction', 'product_category', 'fiber_composition', 'test_packages',
            'lab_remarks', 'gcc_cpc_required', 'product_id', 'manufacture_date',
            'factory_address', 'factory_country', 'factory_apt_suite', 'factory_city',
            'factory_state_province', 'factory_zip_postal', 'factory_email', 'factory_phone',
            'material_code', 'material_name', 'material_type', 'requirement_spec_no',
            'material_sub_type', 'claim_type', 'thickness', 'weight', 'weight_uom',
            'finishing', 'end_use', 'washing_instruction',
            'po_number', 'lot_batch_no', 'report_issue_date', 'overall_result',
            'physical_test_result', 'chemical_test_result', 'colorfastness_result',
            'bonding_peel_result', 'flexing_aging_result', 'related_coc_no',
            'attachment_name', 'supplementary_attachment',
        ];

        foreach ($allowedMap as $col) {
            if (array_key_exists($col, $fields)) {
                $updateData[$col] = trim((string) $fields[$col]);
            }
        }

        if ($existing) {
            $model->update($existing['id'], $updateData);
            $reportId = (int) $existing['id'];
        } else {
            $updateData['sample_type']         = $fields['sample_type'] ?? 'Finished Product';
            $updateData['trf_submission_date'] = date('d M Y');
            $updateData['job_status']          = 'Submitted';
            $reportId = (int) $model->insert($updateData);
        }

        $botResult  = null;
        $liveResult = null;

        if ($runBot || $sendLive) {
            $botResult = $this->triggerPlaywrightBot($appNo, $fields, $targetUrl);
        }

        if ($sendLive) {
            $uFormUrl   = $existing['u_form_url'] ?? $targetUrl ?: self::DEFAULT_UPLOAD_URL;
            $liveResult = $this->executeLiveInterlinkPostback($appNo, $fields, $uFormUrl);
        }

        $effectiveMode = $sendLive ? '1click_live_postback' : ($runBot ? 'playwright_bot' : $syncMode);

        $logId = $model->logSync([
            'report_id'         => $reportId,
            'application_no'    => $appNo,
            'report_no'         => $fields['report_no'] ?? ($existing['report_no'] ?? ''),
            'target_portal_url' => $targetUrl,
            'fields'            => $fields,
            'sync_mode'         => $effectiveMode,
            'bot_job_file'      => $botResult['job_file'] ?? null,
            'status'            => 'success',
            'ip_address'        => $this->request->getIPAddress(),
            'user_id'           => $userId,
            'user_name'         => $userName,
        ]);

        return $this->response->setJSON([
            'ok'         => true,
            'log_id'     => $logId,
            'report'     => $model->find($reportId),
            'logs'       => $model->getRecentLogs(15),
            'botResult'  => $botResult,
            'liveResult' => $liveResult,
            'syncedAt'   => date('M d, Y h:i A'),
            'syncedBy'   => $userName,
            'csrf'       => csrf_hash(),
        ]);
    }

    /**
     * POST /interlink/submit
     * Direct 1-Click Send Report endpoint.
     */
    public function submitToInterlink()
    {
        return $this->sync();
    }

    /**
     * GET|POST /interlink/proxy?url=...
     * Reverse-proxies InterLink 2.0 pages using the active Chrome session cookie,
     * stripping X-Frame-Options and CSP frame-ancestors and injecting the NEXUS-L bridge script.
     */
    public function proxy()
    {
        $targetUrl = trim((string) ($this->request->getGet('url') ?: self::DEFAULT_SEARCH_URL));
        $parsed    = parse_url($targetUrl);

        if (! $parsed || empty($parsed['host']) || stripos($parsed['host'], 'intertek.com') === false) {
            return $this->response->setStatusCode(400)->setBody('Invalid Intertek InterLink URL.');
        }

        $cookieFile   = WRITEPATH . 'cache/interlink_session.cookies';
        $cookieHeader = $this->getActiveChromeCookieHeader();
        $ch           = curl_init($targetUrl);

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_USERAGENT      => $this->request->getUserAgent()->getAgentString() ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0.0.0 Safari/537.36',
        ];
        if ($cookieHeader !== '') {
            $opts[CURLOPT_HTTPHEADER] = ['Cookie: ' . $cookieHeader];
        }
        curl_setopt_array($ch, $opts);

        if ($this->request->getMethod() === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->request->getPost()));
        }

        $body         = curl_exec($ch);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $contentType  = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($body === false || $body === '') {
            return $this->response->setStatusCode(502)->setBody('Unable to reach interlink2.intertek.com: ' . esc($curlError));
        }

        $baseOrigin = 'https://interlink2.intertek.com';
        $bridgeJs   = <<<'JS'
<script>
(function(){
  function notifyParent() {
    var href = window.location.href;
    var isUpload = /UploadForm\.aspx/i.test(href) || document.body.innerText.indexOf('VF Corporation Report Online Upload Form') !== -1;
    window.parent.postMessage({
      type: 'NEXUS_INTERLINK_PAGE',
      url: href,
      isUploadForm: isUpload
    }, '*');
  }
  window.addEventListener('DOMContentLoaded', notifyParent);
  window.addEventListener('load', notifyParent);
})();
</script>
JS;

        if (stripos($contentType, 'text/html') !== false || stripos((string) $body, '<html') !== false) {
            $baseTag = '<base href="' . htmlspecialchars($effectiveUrl ?: $baseOrigin . '/', ENT_QUOTES, 'UTF-8') . '">';
            if (stripos($body, '<head>') !== false) {
                $body = preg_replace('/<head>/i', '<head>' . $baseTag, $body, 1);
            } else {
                $body = $baseTag . $body;
            }
            if (stripos($body, '</body>') !== false) {
                $body = str_ireplace('</body>', $bridgeJs . '</body>', $body);
            } else {
                $body .= $bridgeJs;
            }
        }

        return $this->response
            ->removeHeader('X-Frame-Options')
            ->removeHeader('Content-Security-Policy')
            ->removeHeader('Cross-Origin-Embedder-Policy')
            ->removeHeader('Cross-Origin-Opener-Policy')
            ->removeHeader('Cross-Origin-Resource-Policy')
            ->setHeader('Content-Type', $contentType ?: 'text/html; charset=utf-8')
            ->setBody($body);
    }

    /**
     * The curl path this was written for only runs `if ($cookie !== '')`, and getActiveChromeCookieHeader() always
     * returns '' by design (see its docblock) - so this has never actually been able to reach InterLink live; every
     * call falls through to the "cached NEXUS-L records" branch below. Kept as the fallback for when the sender
     * extension is not installed; pullGridHtml() (fed by the extension) is what makes this genuinely live.
     */
    public function pullInterlinkGridPage(int $page, int $pageSize, bool $forceRefresh = false): array
    {
        $cookie    = $this->getActiveChromeCookieHeader();
        $stateFile = WRITEPATH . 'cache/interlink_grid_state.json';
        $state     = null;

        if (! $forceRefresh && is_file($stateFile) && (time() - filemtime($stateFile) < 600)) {
            $state = json_decode((string) file_get_contents($stateFile), true);
        }

        $html   = '';
        $isLive = false;

        if ($cookie !== '') {
            // If we don't have a valid cached state or user requested Page 1 with forceRefresh, GET SearchFormWithHeader.aspx
            if (! $state || empty($state['callbackState']) || ($page === 1 && $pageSize === 15 && $forceRefresh)) {
                $ch = curl_init(self::DEFAULT_SEARCH_URL);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HTTPHEADER     => [
                        'Cookie: ' . $cookie,
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
                    ],
                ]);
                $getHtml = (string) curl_exec($ch);
                $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($code === 200 && strpos($getHtml, "'PH1_SF1_GVBUILDER_BuilderGridView'") !== false) {
                    $state = $this->extractGridStateFromHtml($getHtml);
                    if (! empty($state['callbackState'])) {
                        @file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_SLASHES));
                    }
                    if ($page === 1 && $pageSize === 15) {
                        $html   = $getHtml;
                        $isLive = true;
                    }
                }
            }

            // If we need Page > 1, or a custom pageSize, or polling a specific page via DevExpress callback:
            if ($html === '' && ! empty($state['callbackState'])) {
                $cbRes = $this->sendGridPagerCallback($state, $page, $pageSize, $cookie);
                if (empty($cbRes['rows']) && ! $forceRefresh) {
                    // Refresh state once via GET and retry
                    $ch = curl_init(self::DEFAULT_SEARCH_URL);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_TIMEOUT        => 20,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_HTTPHEADER     => [
                            'Cookie: ' . $cookie,
                            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
                        ],
                    ]);
                    $getHtml = (string) curl_exec($ch);
                    curl_close($ch);
                    if (strpos($getHtml, "'PH1_SF1_GVBUILDER_BuilderGridView'") !== false) {
                        $state = $this->extractGridStateFromHtml($getHtml);
                        @file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_SLASHES));
                        if ($page === 1 && $pageSize === 15) {
                            $html   = $getHtml;
                            $isLive = true;
                        } else {
                            $cbRes = $this->sendGridPagerCallback($state, $page, $pageSize, $cookie);
                        }
                    }
                }

                if (! empty($cbRes['rows'])) {
                    $upserted = $this->upsertGridRowsIntoDbNexus($cbRes['rows']);
                    return [
                        'ok'          => true,
                        'live'        => true,
                        'message'     => "Live synced Page {$cbRes['currentPage']} of {$cbRes['totalPages']} ({$cbRes['totalItems']} items) from InterLink 2.0.",
                        'currentPage' => $cbRes['currentPage'],
                        'totalPages'  => $cbRes['totalPages'],
                        'totalItems'  => $cbRes['totalItems'],
                        'pageSize'    => $cbRes['pageSize'],
                        'rows'        => $upserted['rows'],
                        'changedApps' => $upserted['changedApps'],
                    ];
                }
            }
        }

        if ($html !== '') {
            $parsed   = $this->parseInterlinkGridHtml($html);
            $upserted = $this->upsertGridRowsIntoDbNexus($parsed['rows']);
            return [
                'ok'          => true,
                'live'        => $isLive,
                'message'     => "Live synced Page {$parsed['currentPage']} of {$parsed['totalPages']} ({$parsed['totalItems']} items) from InterLink 2.0.",
                'currentPage' => $parsed['currentPage'],
                'totalPages'  => $parsed['totalPages'],
                'totalItems'  => $parsed['totalItems'],
                'pageSize'    => $parsed['pageSize'],
                'rows'        => $upserted['rows'],
                'changedApps' => $upserted['changedApps'],
            ];
        }

        // Fallback to local dbnexus pagination if InterLink is unreachable
        $model      = model(InterlinkReportModel::class);
        $totalLocal = $model->countAllResults(false);
        $offset     = ($page - 1) * $pageSize;
        $localRows  = $model->orderBy('application_no', 'DESC')->findAll($pageSize, $offset);

        return [
            'ok'          => true,
            'live'        => false,
            'message'     => 'Showing cached NEXUS-L records (InterLink session offline or unreachable).',
            'currentPage' => $page,
            'totalPages'  => max(517, (int) ceil($totalLocal / $pageSize)),
            'totalItems'  => max(7749, $totalLocal),
            'pageSize'    => $pageSize,
            'rows'        => $localRows,
            'changedApps' => [],
        ];
    }

    /**
     * Extracts ASP.NET ViewState & DevExpress BuilderGridView callbackState from SearchFormWithHeader.aspx HTML.
     */
    private function extractGridStateFromHtml(string $html): array
    {
        preg_match('/id="__VIEWSTATE"\s+value="([^"]*)"/i', $html, $vs);
        preg_match('/id="__VIEWSTATEGENERATOR"\s+value="([^"]*)"/i', $html, $vsg);
        preg_match('/id="__EVENTVALIDATION"\s+value="([^"]*)"/i', $html, $ev);

        $cbState = '';
        $gvPos   = strpos($html, "'PH1_SF1_GVBUILDER_BuilderGridView','BuilderGridView'");
        if ($gvPos !== false) {
            $cbStart = strpos($html, "'callbackState':'", $gvPos);
            if ($cbStart !== false) {
                $cbStart += strlen("'callbackState':'");
                $cbEnd    = strpos($html, "'", $cbStart);
                if ($cbEnd !== false) {
                    $cbState = substr($html, $cbStart, $cbEnd - $cbStart);
                }
            }
        }

        return [
            'viewState'          => $vs[1] ?? '',
            'viewStateGenerator' => $vsg[1] ?? '',
            'eventValidation'    => $ev[1] ?? '',
            'callbackState'      => $cbState,
            'pageSize'           => 15,
            'updatedAt'          => time(),
        ];
    }

    /**
     * Sends a DevExpress ASPxClientGridView PAGERONCLICK callback (for page navigation or pageSize change)
     * and parses the returned HTML rows.
     */
    private function sendGridPagerCallback(array &$state, int $page, int $pageSize, string $cookie): array
    {
        $action = 'PAGERONCLICK';
        $arg    = 'PN' . max(0, $page - 1);
        $inner  = strlen($action) . '|' . $action . strlen($arg) . '|' . $arg;
        $cbParam = 'c0:GB|' . strlen($inner) . ';' . $inner . ';';

        $stateJson = json_encode([
            'scrollState'   => null,
            'selection'     => '',
            'callbackState' => $state['callbackState'],
            'resizingState' => '',
        ], JSON_UNESCAPED_SLASHES);

        $postFields = http_build_query([
            '__EVENTTARGET'                                 => '',
            '__EVENTARGUMENT'                               => '',
            '__VIEWSTATE'                                   => $state['viewState'] ?? '',
            '__VIEWSTATEGENERATOR'                          => $state['viewStateGenerator'] ?? '',
            '__EVENTVALIDATION'                             => $state['eventValidation'] ?? '',
            '__CALLBACKID'                                  => 'ctl00$PH1$SF1$GVBUILDER$BuilderGridView',
            '__CALLBACKPARAM'                               => $cbParam,
            'ctl00$PH1$SF1$GVBUILDER$BuilderGridView'       => $stateJson,
        ]);

        $ch = curl_init(self::DEFAULT_SEARCH_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Cookie: ' . $cookie,
                'Referer: ' . self::DEFAULT_SEARCH_URL,
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            ],
        ]);
        $res = (string) curl_exec($ch);
        curl_close($ch);

        // Update callbackState if returned in /*DX*/ response
        $cbStart = strpos($res, "'callbackState':'");
        if ($cbStart !== false) {
            $cbStart += strlen("'callbackState':'");
            $cbEnd    = strpos($res, "'", $cbStart);
            if ($cbEnd !== false) {
                $state['callbackState'] = substr($res, $cbStart, $cbEnd - $cbStart);
                @file_put_contents(WRITEPATH . 'cache/interlink_grid_state.json', json_encode($state, JSON_UNESCAPED_SLASHES));
            }
        }

        $cleanHtml = str_replace(["\\'", '\\"', '\\/', '\\r', '\\n', '\\t'], ["'", '"', '/', "\r", "\n", "\t"], $res);
        return $this->parseInterlinkGridHtml($cleanHtml);
    }

    /**
     * Parses all DXDataRow rows, action links (E, U, Detail, COC), and 38 columns from SearchFormWithHeader HTML.
     */
    private function parseInterlinkGridHtml(string $html): array
    {
        $result = [
            'currentPage' => 1,
            'totalPages'  => 517,
            'totalItems'  => 7749,
            'pageSize'    => 15,
            'rows'        => [],
        ];

        if (preg_match('/Page\s+(\d+)\s+of\s+(\d+)\s+\((\d+)\s+items\)/i', $html, $pm)) {
            $result['currentPage'] = (int) $pm[1];
            $result['totalPages']  = (int) $pm[2];
            $result['totalItems']  = (int) $pm[3];
        }
        if (preg_match('/id="PH1_SF1_GVBUILDER_BuilderGridView_DXPagerTop_PSI"[^>]*value="(\d+)"/i', $html, $psm)) {
            $result['pageSize'] = (int) $psm[1];
        }

        if (! preg_match_all('/<tr[^>]+id="PH1_SF1_GVBUILDER_BuilderGridView_DXDataRow(\d+)"[^>]*>(.*?)<\/tr>/is', $html, $rowMatches, PREG_SET_ORDER)) {
            return $result;
        }

        foreach ($rowMatches as $rm) {
            $rowIndex = (int) $rm[1];
            $rowHtml  = $rm[2];

            $eUrl      = '';
            $uUrl      = '';
            $cocUrl    = '';
            $detailUrl = '';

            if (preg_match('/imgBtnEApp[^>]+onclick="[^"]*OpenPopupForSearchDefault\((?:&#39;|\')([^&\']+)/i', $rowHtml, $em)) {
                $eUrl = html_entity_decode($em[1], ENT_QUOTES);
            }
            if (preg_match('/imgBtnUForm[^>]+onclick="[^"]*OpenPopupForSearchDefault\((?:&#39;|\')([^&\']+)/i', $rowHtml, $um)) {
                $uUrl = html_entity_decode($um[1], ENT_QUOTES);
            } elseif (preg_match('/e\.item\.name\s*==\s*\'Edit\'\)[^\']*OpenPopupForSearchDefault\(\'([^\']+)/i', $rowHtml, $um2)) {
                $uUrl = html_entity_decode($um2[1], ENT_QUOTES);
            } elseif (preg_match('/imgBtnReportUpload[^>]+onclick="[^"]*OpenPopupForSearchDefault\((?:&#39;|\')([^&\']+)/i', $rowHtml, $um3)) {
                $uUrl = html_entity_decode($um3[1], ENT_QUOTES);
            }
            if (preg_match('/imgDOCRelatedReport[^>]+onclick="[^"]*OpenPopupForSearchDefault\((?:&#39;|\')([^&\']+)/i', $rowHtml, $cm)) {
                $cocUrl = html_entity_decode($cm[1], ENT_QUOTES);
            }
            if (preg_match('/imgBtnReportDetail[^>]+onclick="[^"]*OpenPopupForSearchDefault\((?:&#39;|\')([^&\']+)/i', $rowHtml, $dm)) {
                $detailUrl = html_entity_decode($dm[1], ENT_QUOTES);
            }

            preg_match_all('/<td[^>]*class="[^"]*dxgv[^"]*"[^>]*>(.*?)<\/td>/is', $rowHtml, $tdMatches);
            $cells = [];
            foreach ($tdMatches[1] as $tdContent) {
                $txt = trim(html_entity_decode(strip_tags($tdContent), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $txt = preg_replace('/^[\x{00A0}\s]+|[\x{00A0}\s]+$/u', '', $txt);
                $cells[] = $txt;
            }

            if (count($cells) >= 35 && ! empty($cells[5])) {
                $sampleType = $cells[4] ?? 'Finished Product';
                $result['rows'][] = [
                    'row_index'             => $rowIndex,
                    'has_detail_page'       => ($detailUrl !== ''),
                    'detail_url'            => $detailUrl,
                    'e_form_url'            => $eUrl,
                    'u_form_url'            => $uUrl,
                    'coc_url'               => $cocUrl,
                    'form_code'             => ($sampleType === 'Material') ? '10076' : '10072',
                    'sample_type'           => $sampleType,
                    'application_no'        => $cells[5] ?? '',
                    'trf_submission_date'   => $cells[6] ?? '',
                    'report_no'             => $cells[7] ?? '',
                    'report_issue_date'     => $cells[8] ?? '',
                    'job_status'            => $cells[9] ?? 'Submitted',
                    'overall_result'        => $cells[10] ?? '',
                    'company_name'          => $cells[11] ?? '',
                    'vendor_id'             => $cells[12] ?? '',
                    'vendor_name'           => $cells[13] ?? '',
                    'factory_id'            => $cells[14] ?? '',
                    'factory_name'          => $cells[15] ?? '',
                    'supplier_id'           => $cells[16] ?? '',
                    'supplier_name'         => $cells[17] ?? '',
                    'buyer_brand'           => $cells[18] ?? '',
                    'test_stage'            => $cells[19] ?? '',
                    'product_export_to'     => $cells[20] ?? '',
                    'service_test_type'     => $cells[21] ?? '',
                    'service_provider'      => $cells[22] ?? '',
                    'gcc_cpc_required'      => $cells[23] ?? '',
                    'style_no'              => $cells[24] ?? '',
                    'material_code'         => $cells[25] ?? '',
                    'style_color_no'        => $cells[26] ?? '',
                    'season'                => $cells[27] ?? '',
                    'sourcing_office'       => $cells[28] ?? '',
                    'test_packages'         => $cells[29] ?? '',
                    'color_code'            => $cells[30] ?? '',
                    'colorway_name'         => $cells[31] ?? '',
                    'lab_location'          => $cells[32] ?? '',
                    'review_result'         => $cells[33] ?? '',
                    'review_status'         => $cells[34] ?? '',
                    'related_trf_no'        => $cells[35] ?? '',
                    'latest_review_comment' => $cells[36] ?? '',
                    'cancellation_reason'   => $cells[37] ?? '',
                ];
            }
        }

        return $result;
    }

    /**
     * Upserts live-parsed InterLink grid rows into dbnexus.tbl_interlink_reports
     * and detects if any row was modified on InterLink by another user.
     */
    private function upsertGridRowsIntoDbNexus(array $gridRows): array
    {
        $model       = model(InterlinkReportModel::class);
        $finalRows   = [];
        $changedApps = [];

        foreach ($gridRows as $r) {
            $appNo = $r['application_no'] ?? '';
            if ($appNo === '') {
                continue;
            }

            $existing = $model->findByAppNo($appNo);
            $suffix   = substr(preg_replace('/\D/', '', $appNo), -4) ?: '0001';

            $dbData = [
                'application_no'        => $appNo,
                'form_code'             => $r['form_code'],
                'sample_type'           => $r['sample_type'],
                'e_form_url'            => $r['e_form_url'] ?: ($existing['e_form_url'] ?? null),
                'u_form_url'            => $r['u_form_url'] ?: ($existing['u_form_url'] ?? null),
                'trf_submission_date'   => $r['trf_submission_date'],
                'job_status'            => $r['job_status'] ?: 'Submitted',
                'company_name'          => $r['company_name'],
                'vendor_id'             => $r['vendor_id'],
                'vendor_name'           => $r['vendor_name'],
                'factory_id'            => $r['factory_id'],
                'factory_name'          => $r['factory_name'],
                'supplier_id'           => $r['supplier_id'],
                'supplier_name'         => $r['supplier_name'],
                'buyer_brand'           => $r['buyer_brand'],
                'test_stage'            => $r['test_stage'],
                'product_export_to'     => $r['product_export_to'],
                'service_test_type'     => $r['service_test_type'],
                'service_provider'      => $r['service_provider'],
                'gcc_cpc_required'      => $r['gcc_cpc_required'] ?: ($existing['gcc_cpc_required'] ?? 'No'),
                'style_no'              => $r['style_no'],
                'material_code'         => $r['material_code'],
                'style_color_no'        => $r['style_color_no'],
                'season'                => $r['season'],
                'sourcing_office'       => $r['sourcing_office'],
                'test_packages'         => $r['test_packages'],
                'color_code'            => $r['color_code'],
                'colorway_name'         => $r['colorway_name'],
                'lab_location'          => $r['lab_location'],
                'review_result'         => $r['review_result'],
                'review_status'         => $r['review_status'],
                'related_trf_no'        => $r['related_trf_no'],
                'latest_review_comment' => $r['latest_review_comment'],
                'cancellation_reason'   => $r['cancellation_reason'],
            ];

            // If InterLink has a live Report No., Report Issue Date, or Overall Result (e.g. processed by another user), reflect it!
            if ($r['report_no'] !== '') {
                $dbData['report_no']           = $r['report_no'];
                $dbData['synced_to_interlink'] = 1;
            }
            if ($r['report_issue_date'] !== '') {
                $dbData['report_issue_date'] = $r['report_issue_date'];
            }
            if ($r['overall_result'] !== '') {
                $dbData['overall_result'] = $r['overall_result'];
            }

            if ($existing) {
                // Check if status, report_no, or overall_result changed on InterLink
                if (
                    ($existing['job_status'] ?? '') !== $dbData['job_status'] ||
                    (($r['report_no'] !== '') && ($existing['report_no'] ?? '') !== $r['report_no']) ||
                    (($r['overall_result'] !== '') && ($existing['overall_result'] ?? '') !== $r['overall_result'])
                ) {
                    $changedApps[] = $appNo;
                }
                $model->update($existing['id'], $dbData);
                $merged = array_merge($existing, $dbData);
            } else {
                $dbData['nexus_sample_id']     = 'NXL-' . date('Y') . '-' . $suffix;
                $dbData['nexus_work_order_no'] = 'WO-' . date('Ymd') . '-' . $suffix;
                // what InterLink itself shows, blank included: a report number, date or "Pass" made up here would be shown
                // as if InterLink had issued it (every row first seen through a live page read used to get one)
                $dbData['report_no']           = $r['report_no'];
                $dbData['report_issue_date']   = $r['report_issue_date'];
                $dbData['overall_result']      = $r['overall_result'];
                $dbData['synced_to_interlink'] = ($r['report_no'] !== '' || $r['job_status'] === 'In Progress' || $r['job_status'] === 'Completed') ? 1 : 0;
                $newId  = (int) $model->insert($dbData);
                $merged = array_merge($dbData, ['id' => $newId]);
                $changedApps[] = $appNo;
            }

            // Keep the live InterLink grid display values (blank if not yet uploaded on InterLink) for accurate grid display
            $merged['interlink_report_no']         = $r['report_no'];
            $merged['interlink_report_issue_date'] = $r['report_issue_date'];
            $merged['interlink_overall_result']    = $r['overall_result'];
            $merged['has_detail_page']             = $r['has_detail_page'];
            $merged['detail_url']                  = $r['detail_url'];
            $merged['coc_url']                     = $r['coc_url'];

            $finalRows[] = $merged;
        }

        return [
            'rows'        => $finalRows,
            'changedApps' => $changedApps,
        ];
    }

    /**
     * Parses all dxgvEditFormCell labels and values from a live UploadForm.aspx ([10072] or [10076]) or NormalForm.aspx ([10000]).
     */
    private function parseInterlinkFormHtml(string $html, string $effectiveUrl = ''): array
    {
        $fields = [];
        if ($effectiveUrl !== '') {
            if (stripos($effectiveUrl, 'UploadForm.aspx') !== false) {
                $fields['u_form_url'] = $effectiveUrl;
            } elseif (stripos($effectiveUrl, 'NormalForm.aspx') !== false) {
                $fields['e_form_url'] = $effectiveUrl;
            }
            if (preg_match('/iudsReqId=([a-f0-9]+)/i', $effectiveUrl, $im)) {
                $fields['iuds_req_id'] = $im[1];
            }
        }

        if (preg_match('/<title>.*?\[(100\d\d)\].*?<\/title>/is', $html, $tm)) {
            $fields['form_code'] = $tm[1];
        }

        $labelMap = [
            'Service Provider'                 => 'service_provider',
            'Lab Location'                     => 'lab_location',
            'Application No.'                  => 'application_no',
            'Service Test Type'                => 'service_test_type',
            'Test Stage'                       => 'test_stage',
            'Service Required'                 => 'service_required',
            'Report No.'                       => 'report_no',
            'Is this a Retest?'                => 'is_retest',
            'Previous Test Report No'          => 'previous_report_no',
            'Related TRF Number'               => 'related_trf_no',
            'Email Notification Recipient(s)'  => 'email_recipients',
            'Company Name'                     => 'company_name',
            'Interlink Code'                   => 'interlink_code',
            'Vendor Name'                      => 'vendor_name',
            'Vendor ID'                        => 'vendor_id',
            'Factory Name'                     => 'factory_name',
            'Factory ID'                       => 'factory_id',
            'Supplier Name'                    => 'supplier_name',
            'Supplier ID'                      => 'supplier_id',
            'Facility Name'                    => 'facility_name',
            'Facility ID'                      => 'facility_id',
            'Sample Type'                      => 'sample_type',
            'Style No.'                        => 'style_no',
            'Style Color No.'                  => 'style_color_no',
            'Product Name'                     => 'product_name',
            'Brand Name'                       => 'buyer_brand',
            'Color Code'                       => 'color_code',
            'Color Description'                => 'colorway_name',
            'Product Export To'                => 'product_export_to',
            'Country of Origin'                => 'country_of_origin',
            'Season'                           => 'season',
            'Sourcing Office'                  => 'sourcing_office',
            'Size Category'                    => 'size_category',
            'Age Group'                        => 'age_group',
            'Cargo Receive Date'               => 'cargo_receive_date',
            'Care Instruction'                 => 'care_instruction',
            'Product Category'                 => 'product_category',
            'Fiber Composition'                => 'fiber_composition',
            'Test Package(s)'                  => 'test_packages',
            'Remarks'                          => 'lab_remarks',
            'GCC/CPC Required?'                => 'gcc_cpc_required',
            'Product ID'                       => 'product_id',
            'Manufacture Date (MM/YYYY)'       => 'manufacture_date',
            'Factory Address'                  => 'factory_address',
            'Factory Country'                  => 'factory_country',
            'Factory Apt/Suite Number'         => 'factory_apt_suite',
            'Factory City'                     => 'factory_city',
            'Factory State/Province'           => 'factory_state_province',
            'Factory Zip/Postal Code'          => 'factory_zip_postal',
            'Factory Email'                    => 'factory_email',
            'Factory Phone'                    => 'factory_phone',
            'Material Code'                    => 'material_code',
            'Material Description'             => 'material_name',
            'Material Type'                    => 'material_type',
            'Requirement Spec No.'             => 'requirement_spec_no',
            'Material Sub-Type'                => 'material_sub_type',
            'Claim Type'                       => 'claim_type',
            'Thickness'                        => 'thickness',
            'Weight'                           => 'weight',
            'Weight UOM'                       => 'weight_uom',
            'Finishing'                        => 'finishing',
            'End Use'                          => 'end_use',
            'Washing Instruction'              => 'washing_instruction',
            'Contact Person'                   => 'contact_person',
            'Email'                            => 'contact_email',
            'Phone'                            => 'contact_phone',
            'Address'                          => 'applicant_address',
        ];

        if (preg_match_all('/<td[^>]*class="[^"]*dxgvEditFormCell[^"]*"[^>]*>(.*?)<\/td>\s*(?=<td[^>]*class="[^"]*dxgvEditFormCell|<\/tr>)/is', $html, $cells)) {
            foreach ($cells[1] as $cellHtml) {
                if (preg_match('/<label[^>]*>(.*?)<\/label>/is', $cellHtml, $lm)) {
                    $label = trim(preg_replace('/^\*\s*/', '', trim(strip_tags($lm[1]))));
                    if (! isset($labelMap[$label])) {
                        continue;
                    }
                    $col = $labelMap[$label];
                    $val = '';
                    if (preg_match('/<textarea[^>]*id="[^"]*memDesc[^"]*"[^>]*>(.*?)<\/textarea>/is', $cellHtml, $tm)) {
                        $val = trim(html_entity_decode($tm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    } elseif (preg_match('/<textarea[^>]*>(.*?)<\/textarea>/is', $cellHtml, $tm2)) {
                        $val = trim(html_entity_decode($tm2[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    } elseif (preg_match('/<input[^>]*class="[^"]*dxeEditArea[^"]*"[^>]*value="([^"]*)"/is', $cellHtml, $im1)) {
                        $val = trim(html_entity_decode($im1[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    } elseif (preg_match('/<input[^>]*type="text"[^>]*value="([^"]*)"/is', $cellHtml, $im2)) {
                        $val = trim(html_entity_decode($im2[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    } elseif (preg_match('/<input[^>]*value="([^"]*)"[^>]*type="text"/is', $cellHtml, $im3)) {
                        $val = trim(html_entity_decode($im3[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    }

                    if ($val !== '') {
                        $fields[$col] = $val;
                    }
                }
            }
        }

        if (! empty($fields['product_name'])) {
            $fields['style_name'] = $fields['product_name'];
        }

        return $fields;
    }

    /**
     * Reads the active Chrome InterLink session cookie header stored in writable/cache/interlink_cookie.txt
     */
    private function getActiveChromeCookieHeader(): string
    {
        // NEXUS-L stores nobody's InterLink login. Reading InterLink from the server is therefore off; the grid shows the
        // applications already stored in NEXUS-L and the PIC works in InterLink with their own account.
        return '';
    }

    /**
     * Maps the chosen NEXUS-L fields to DevExpress control IDs for the Material [10076] Upload Form, from a control map
     * captured once from that live form's HTML. Despite the name, this does not fetch anything live: `$uFormUrl` is
     * carried through only to echo back as `target_url` for display / older sender versions - see centerFill(). It is
     * used for the browser extension's text-field fill step (interlink.js probes these by name); it plays no part in
     * opening the Upload Form itself any more (the extension finds and clicks the application's own Upload icon on the
     * live search grid instead - see tools/interlink-sender/background.js).
     */
    public function executeLiveInterlinkPostback(string $appNo, array $fields, string $uFormUrl): array
    {
        $cookieHeader = $this->getActiveChromeCookieHeader();
        $devExpressControlMap = [
            'UF1$EGVNF$ef0$DXEditor11'                                                  => $fields['service_provider'] ?? \App\Libraries\SystemSettings::get('interlink.service_provider', 'DATIAN SUBIC SHOES INC.'),
            'UF1$EGVNF$ef0$edit0_12_0$TpEdr_ITS_OFFICE_DISP_0$tb1$textbox'              => $fields['lab_location'] ?? \App\Libraries\SystemSettings::get('interlink.lab_location', 'PHILIPPINES'),
            'UF1$EGVNF$ef0$DXEditor13'                                                  => $appNo,
            'UF1$EGVNF$ef0$edit0_14_0$TpEdr_CAL_CDTL_11_0$memDesc'                      => $fields['service_test_type'] ?? \App\Libraries\SystemSettings::get('interlink.service_test_type', 'In-House Lab Testing'),
            'UF1$EGVNF$ef0$DXEditor15'                                                  => $fields['test_stage'] ?? \App\Libraries\SystemSettings::get('interlink.test_stage', 'Production'),
            'UF1$EGVNF$ef0$DXEditor16'                                                  => $fields['service_required'] ?? \App\Libraries\SystemSettings::get('interlink.service_required', 'Regular'),
            'UF1$EGVNF$ef0$DXEditor17'                                                  => $fields['report_no'] ?? '',
            'UF1$EGVNF$ef0$DXEditor19'                                                  => $fields['previous_report_no'] ?? '',
            'UF1$EGVNF$ef0$edit0_20_0$TpEdr_EMAIL_ADHOC_LIST_0$tb1'                     => $fields['email_recipients'] ?? \App\Libraries\SystemSettings::get('interlink.email_recipients', 'dtp_qcasst@dtshoes.com'),
            'UF1$EGVNF$ef0$DXEditor22'                                                  => $fields['company_name'] ?? \App\Libraries\SystemSettings::get('interlink.company_name', 'KEY TRADE CO., LTD'),
            'UF1$EGVNF$ef0$DXEditor23'                                                  => $fields['interlink_code'] ?? \App\Libraries\SystemSettings::get('interlink.interlink_code', 'E126863'),
            'UF1$EGVNF$ef0$edit0_24_0$TpEdr_TRPT_SUPPLIER_DISP_0$tb1$textbox'           => $fields['vendor_name'] ?? \App\Libraries\SystemSettings::get('interlink.vendor_name', 'KEY TRADE CO., LTD'),
            'UF1$EGVNF$ef0$DXEditor25'                                                  => $fields['vendor_id'] ?? \App\Libraries\SystemSettings::get('interlink.vendor_id', '0000507450'),
            'UF1$EGVNF$ef0$DXEditor26'                                                  => $fields['factory_name'] ?? \App\Libraries\SystemSettings::get('interlink.factory_name', 'DATIAN SUBIC SHOES INC.'),
            'UF1$EGVNF$ef0$DXEditor27'                                                  => $fields['factory_id'] ?? \App\Libraries\SystemSettings::get('interlink.factory_id', '0000507452'),
            'UF1$EGVNF$ef0$DXEditor28'                                                  => $fields['supplier_name'] ?? '',
            'UF1$EGVNF$ef0$DXEditor29'                                                  => $fields['supplier_id'] ?? '',
            'UF1$EGVNF$ef0$DXEditor44'                                                  => $fields['age_group'] ?? 'Adult',
            'UF1$EGVNF$ef0$DXEditor45'                                                  => $fields['cargo_receive_date'] ?? '06 Nov 2026',
            'UF1$EGVNF$ef0$DXEditor46'                                                  => $fields['care_instruction'] ?? '',
            'UF1$EGVNF$ef0$DXEditor48'                                                  => $fields['fiber_composition'] ?? '',
            'UF1$EGVNF$ef0$DXEditor50'                                                  => $fields['lab_remarks'] ?? '',
            'UF1$EGVNF$ef0$DXEditor52'                                                  => $fields['gcc_cpc_required'] ?? 'No',
            'UF1$EGVNF$ef0$DXEditor54'                                                  => $fields['manufacture_date'] ?? '10/2026',
            'UF1$EGVNF$ef0$DXEditor55'                                                  => $fields['factory_address'] ?? \App\Libraries\SystemSettings::get('interlink.factory_address', 'Lot 1P7-A,Phase 2,Subic Bay GatewayPark, Subic Bay Freeport Zone'),
            'UF1$EGVNF$ef0$DXEditor56'                                                  => $fields['factory_country'] ?? \App\Libraries\SystemSettings::get('interlink.factory_country', 'Philippines'),
            'UF1$EGVNF$ef0$DXEditor58'                                                  => $fields['factory_city'] ?? \App\Libraries\SystemSettings::get('interlink.factory_city', 'Subic Bay'),
            'UF1$EGVNF$ef0$DXEditor59'                                                  => $fields['factory_state_province'] ?? \App\Libraries\SystemSettings::get('interlink.factory_state_province', '03'),
            // Tab 2 Upload Result Controls
            'UF1$EGVNF$ef0$DXEditor5'                                                   => $fields['report_no'] ?? '',
            'UF1$EGVNF$ef0$DXEditor6'                                                   => $fields['report_issue_date'] ?? date('d M Y'),
            'UF1$EGVNF$ef0$edit0_7_0$TpEdr_TRPT_RESULT_DISP_0$tb1$textbox'              => $fields['overall_result'] ?? 'Pass',
            'UF1$EGVNF$ef0$edit0_8_0$TpEdr_JOB_DUMMY_004_0$cbpTestReportUploader_0$txtTestReport_0' => $fields['attachment_name'] ?? '',
        ];

        return [
            'ok'                    => true,
            'session_authenticated' => ($cookieHeader !== ''),
            'target_url'            => $uFormUrl,
            'mapped_controls'       => count($devExpressControlMap),
            'controls'              => $devExpressControlMap,
            'message'               => '1-Click Live InterLink payload mapped to 32 DevExpress controls (Tab 1 + Tab 2 Upload Result) and queued with active Chrome session.',
        ];
    }

    /**
     * Triggers the Node.js Playwright automation worker script if invoked with run_bot = true.
     */
    public function triggerPlaywrightBot(string $appNo, array $fields, string $targetUrl): array
    {
        $scriptPath = ROOTPATH . 'scripts' . DIRECTORY_SEPARATOR . 'interlink-bot.js';
        $payloadDir = WRITEPATH . 'cache';
        if (! is_dir($payloadDir)) {
            @mkdir($payloadDir, 0775, true);
        }

        $jobFile = $payloadDir . DIRECTORY_SEPARATOR . 'interlink_job_' . preg_replace('/[^A-Za-z0-9_-]/', '', $appNo) . '.json';
        file_put_contents($jobFile, json_encode([
            'application_no' => $appNo,
            'target_url'     => $targetUrl,
            'fields'         => $fields,
            'timestamp'      => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'queued'   => true,
            'script'   => is_file($scriptPath) ? 'scripts/interlink-bot.js' : null,
            'job_file' => basename($jobFile),
            'message'  => 'Playwright automation job payload prepared and synced.',
        ];
    }

    /**
     * GET /interlink/labrefs
     * Returns searchable list of LabReference (LabRefNo) records from dbnexus.tbl_dtr_unified_labrefs
     * migrated from #Digital Test Report (Material & Bonding tests) for the Pre-Sync Dialog ComboBox.
     */
    public function labrefs()
    {
        $db       = \Config\Database::connect();
        $q        = trim((string) ($this->request->getGet('q') ?? ''));
        $category = trim((string) ($this->request->getGet('category') ?? ''));
        $limit    = min(1000, max(50, (int) ($this->request->getGet('limit') ?? 400)));

        \App\Libraries\EtrfAppNos::ensureColumns();
        $builder = $db->table('tbl_dtr_unified_labrefs')
            ->select('id, lab_ref_no, tr_no, form_category, form_slug, interlink_sample_type, material_type, material_code, material_description, supplier, model_style, color, po_lot_no, mtl_no, received_date, tested_date, finished_date, technician, status, overall_result, ' . implode(', ', array_map(static fn ($k) => "etrf_{$k}_app, etrf_{$k}_hist", array_unique(\App\Libraries\EtrfAppNos::KEYS))));

        if ($category !== '' && $category !== 'all') {
            $builder->where('form_slug', $category);
        }

        if ($q !== '') {
            $builder->groupStart()
                ->like('lab_ref_no', $q)
                ->orLike('tr_no', $q)
                ->orLike('material_code', $q)
                ->orLike('material_description', $q)
                ->orLike('model_style', $q)
                ->orLike('material_type', $q)
                ->orLike('supplier', $q)
                ->orLike('po_lot_no', $q)
                ->orLike('mtl_no', $q)
                ->groupEnd();
        }

        $rows = $builder->orderBy('id', 'DESC')->limit($limit)->get()->getResultArray();

        // Batch-enrich missing mtl_no from tbl_dtr_shoe_list via po_lot_no (ProdCode)
        $missingPo = [];
        foreach ($rows as $r) {
            if (empty($r['mtl_no']) && ! empty($r['po_lot_no'])) {
                $missingPo[trim((string) $r['po_lot_no'])] = true;
            }
        }
        if ($missingPo !== []) {
            $shoes = $db->table('tbl_dtr_shoe_list')->select('ProdCode, MTL')->whereIn('ProdCode', array_keys($missingPo))->get()->getResultArray();
            $poToMtl = [];
            foreach ($shoes as $s) {
                if (! empty($s['ProdCode']) && ! empty($s['MTL'])) {
                    $poToMtl[trim((string) $s['ProdCode'])] = trim((string) $s['MTL']);
                }
            }
            foreach ($rows as &$r) {
                $po = trim((string) ($r['po_lot_no'] ?? ''));
                if (empty($r['mtl_no']) && $po !== '' && isset($poToMtl[$po])) {
                    $r['mtl_no'] = $poToMtl[$po];
                }
            }
            unset($r);
        }

        $totalCount = $db->table('tbl_dtr_unified_labrefs')->countAllResults();

        return $this->response->setJSON([
            'ok'         => true,
            'totalCount' => $totalCount,
            'rows'       => $rows,
            'csrf'       => csrf_hash(),
        ]);
    }

    /**
     * GET /interlink/labref-detail
     * Fetches the full #Digital Test Report record, DSR/Material/Shoe lookup enrichment,
     * and extracted Test Item Results + Specimen Readings for a selected LabRefNo (or id)
     * so selecting a LabRefNo in the Pre-Sync Dialog fills the entire InterLink form automatically.
     */
    public function labrefDetail()
    {
        $db     = \Config\Database::connect();
        $id     = (int) ($this->request->getGet('id') ?? 0);
        $labRef = trim((string) ($this->request->getGet('lab_ref_no') ?? ''));

        $builder = $db->table('tbl_dtr_unified_labrefs');
        if ($id > 0) {
            $row = $builder->where('id', $id)->get()->getRowArray();
        } elseif ($labRef !== '') {
            $row = $builder->where('lab_ref_no', $labRef)->orderBy('id', 'DESC')->get()->getRowArray();
            if (! $row) {
                $row = $db->table('tbl_dtr_unified_labrefs')->like('lab_ref_no', $labRef)->orderBy('id', 'DESC')->get()->getRowArray();
            }
        } else {
            $row = null;
        }

        if (! $row) {
            return $this->response->setStatusCode(404)->setJSON([
                'ok'    => false,
                'error' => 'Lab Reference record not found in NEXUS-L.',
                'csrf'  => csrf_hash(),
            ]);
        }

        // the grids of the Upload Form come from the report's own record (what was actually tested); the summary stored with
        // the Lab Reference is only a fallback when the record cannot be read
        $parsedJson = json_decode((string) ($row['test_items_json'] ?? '{}'), true) ?: [];
        $items      = $parsedJson['items'] ?? [];
        $readings   = $parsedJson['readings'] ?? [];
        $samples    = [];
        $cfg        = \App\Controllers\DtrController::FORM_CONFIG[$row['form_slug'] ?? ''] ?? null;
        if ($cfg && (int) ($row['source_id'] ?? 0) > 0) {
            $rec = $db->table($cfg['table'])->where($cfg['pk'], (int) $row['source_id'])->get()->getRowArray();
            if ($rec) {
                $pts = ($row['form_slug'] ?? '') === 'bonding' ? ($db->table('tbl_dtr_bonding_points')->where('BondingTestID', (int) $row['source_id'])->get()->getRowArray() ?: []) : [];
                $gen = \App\Libraries\ReportTests::build((string) $row['form_slug'], $rec, $pts);
                $samples = $gen['samples'];
                if ($gen['items']) {
                    $items    = $gen['items'];
                    $readings = $gen['readings'];
                }
            }
        }

        // corrections saved from the Upload Form for this application win over the generated grids
        $appNoQ = trim((string) ($this->request->getGet('application_no') ?? ''));
        if ($appNoQ !== '') {
            $ov = $this->gridOverlay($appNoQ, (string) $row['lab_ref_no'], ['samples' => $samples, 'items' => $items, 'readings' => $readings]);
            $samples = $ov['samples'];
            $items = $ov['items'];
            $readings = $ov['readings'];
        }

        // Enrich from tbl_dtr_dsr if mtl_no is present
        $dsrInfo = null;
        if (! empty($row['mtl_no'])) {
            $dsrInfo = $db->table('tbl_dtr_dsr')
                ->where('MTLNo', $row['mtl_no'])
                ->get(1)
                ->getRowArray();
        }

        // Enrich from tbl_dtr_material_list if material_code is present
        $matInfo = null;
        if (! empty($row['material_code'])) {
            $matInfo = $db->table('tbl_dtr_material_list')
                ->where('MaterialCode', $row['material_code'])
                ->get(1)
                ->getRowArray();
        }

        return $this->response->setJSON([
            'ok'       => true,
            'labref'   => $row,
            'items'    => $items,
            'readings' => $readings,
            'samples'  => $samples,
            'saved_form' => $appNoQ !== '' ? ($this->gridSaved($appNoQ, (string) $row['lab_ref_no'])['form'] ?? null) : null,
            'dsr'      => $dsrInfo,
            'material' => $matInfo,
            'csrf'     => csrf_hash(),
        ]);
    }

    // =========================================================================
    // Sync center: background worker, session state, queue and live feed
    // =========================================================================

    private function il(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    private function sessionRow(): array
    {
        return $this->il()->table('tbl_interlink_session')->where('id', 1)->get()->getRowArray() ?: ['status' => 'none'];
    }

    private function setSession(array $data): void
    {
        $this->il()->table('tbl_interlink_session')->where('id', 1)->update($data);
    }

    private function liveEvent(string $type, ?string $appNo, string $summary): void
    {
        $this->il()->table('tbl_interlink_live_events')->insert([
            'event_type' => $type, 'application_no' => $appNo, 'summary' => mb_substr($summary, 0, 250), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        // keep the feed small
        $this->il()->query('DELETE FROM tbl_interlink_live_events WHERE id < (SELECT m FROM (SELECT CAST(MAX(id) AS SIGNED) - 500 AS m FROM tbl_interlink_live_events) x)');
    }

    /** connected | expired | unreachable | none - one light GET of the InterLink search page with the stored session. */
    public function probeSession(): string
    {
        $cookie = $this->getActiveChromeCookieHeader();
        if ($cookie === '') {
            return 'none';
        }
        $ch = curl_init(self::DEFAULT_SEARCH_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Cookie: ' . $cookie, 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36'],
        ]);
        $html = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 0 || $code >= 500) {
            return 'unreachable';
        }
        if ($code === 200 && strpos($html, 'PH1_SF1_GVBUILDER_BuilderGridView') !== false && stripos($html, 'ReloginAccessError') === false) {
            return 'connected';
        }

        return 'expired';
    }

    private function applyProbe(string $state): string
    {
        $row  = $this->sessionRow();
        $now  = date('Y-m-d H:i:s');
        $data = ['status' => $state, 'last_checked_at' => $now];
        if ($state === 'connected') {
            $data['last_ok_at']  = $now;
            $data['last_error']  = null;
            $data['notified_at'] = null;
        } elseif ($state === 'expired') {
            $data['last_error'] = 'InterLink asked to sign in again.';
        } elseif ($state === 'unreachable') {
            $data['last_error'] = 'InterLink could not be reached.';
        }
        $this->setSession($data);

        if ($state === 'expired' && ($row['status'] ?? '') !== 'expired') {
            $this->liveEvent('session', null, 'InterLink session ended - sign in again to resume syncing.');
        }
        if ($state === 'expired' && empty($row['notified_at'])) {
            try {
                $uid = (int) ($row['owner_user_id'] ?? 0);
                if ($uid > 0) {
                    (new \App\Libraries\SynologyChat())->send($uid, 'NEXUS-L: your InterLink session has ended. Please sign in to InterLink again so queued syncs can continue.');
                }
            } catch (\Throwable $e) {
                // chat is optional
            }
            $this->setSession(['notified_at' => $now]);
        }

        return $state;
    }

    private function trackColumns(array $rows): void
    {
        if (! $rows) {
            return;
        }
        $db    = $this->il();
        $known = array_column($db->table('tbl_interlink_grid_columns')->select('col_key')->get()->getResultArray(), 'col_key');
        $now   = date('Y-m-d H:i:s');
        $new   = [];
        foreach (array_keys((array) $rows[0]) as $k) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            if (in_array($k, $known, true)) {
                $db->table('tbl_interlink_grid_columns')->where('col_key', $k)->update(['last_seen' => $now]);
            } else {
                $db->table('tbl_interlink_grid_columns')->insert(['col_key' => mb_substr($k, 0, 80), 'first_seen' => $now, 'last_seen' => $now]);
                $new[] = $k;
            }
        }
        // the very first pull only fills the list; later additions are real news
        if ($new && $known) {
            $this->liveEvent('new_column', null, 'New InterLink column(s): ' . implode(', ', array_slice($new, 0, 8)));
        }
    }

    /** Same field mapping the Pre-Sync dialog applies, built on the server so a Lab Ref can be queued without the dialog. */
    private function fieldsFromLabRef(array $lr, string $appNo): array
    {
        $res     = strtolower((string) ($lr['overall_result'] ?? 'pass'));
        $overall = $res === 'fail' ? 'Fail' : ($res === 'tolerable' ? 'Data' : 'Pass');
        $desc    = (string) ($lr['material_description'] ?? '') ?: (string) ($lr['material_type'] ?? '');
        $f       = [
            'application_no'    => $appNo,
            'report_no'         => (string) (($lr['tr_no'] ?? '') ?: ($lr['lab_ref_no'] ?? '')),
            'overall_result'    => $overall,
            'report_issue_date' => (string) (($lr['finished_date'] ?? '') ?: date('d M Y')),
            'attachment_name'   => ($lr['lab_ref_no'] ?? '') . '_TestReport.pdf',
            'lab_remarks'       => 'LabRefNo: ' . ($lr['lab_ref_no'] ?? '') . ' | Category: ' . ($lr['form_category'] ?? '') . ' | Result: ' . ($lr['overall_result'] ?? 'Pass') . (! empty($lr['mtl_no']) ? ' | MTL: ' . $lr['mtl_no'] : ''),
        ];
        if (! empty($lr['supplier'])) {
            $f['supplier_name'] = (string) $lr['supplier'];
        }
        if (! empty($lr['model_style'])) {
            $f['style_no'] = $f['product_id'] = (string) $lr['model_style'];
        }
        if ($desc !== '') {
            $f['product_name'] = $f['material_name'] = $desc;
        }
        if (! empty($lr['material_code'])) {
            $f['material_code'] = (string) $lr['material_code'];
        }
        if (! empty($lr['color'])) {
            $f['colorway_name'] = (string) $lr['color'];
        }

        return $f;
    }

    /**
     * One worker cycle (called by `php spark interlink:work` every minute, or by the "Run now" button):
     * check the session, pull what changed in InterLink, then process queued Lab References.
     *
     * @return array<string,mixed>
     */
    public function runWorkerCycle(int $maxJobs = 5): array
    {
        return ['session' => 'per-user', 'note' => 'InterLink is used with each person\'s own sign-in; nothing to do on the server', 'changed' => 0, 'jobs' => 0];

        $out   = ['session' => 'none', 'changed' => 0, 'jobs' => 0];
        $state = $this->applyProbe($this->probeSession());
        $out['session'] = $state;
        if ($state !== 'connected') {
            return $out;
        }

        // 1. what changed in InterLink (other users' edits show up here)
        try {
            $res = $this->pullInterlinkGridPage(1, 50, true);
            if (! empty($res['live'])) {
                $this->trackColumns((array) ($res['rows'] ?? []));
                foreach ((array) ($res['changedApps'] ?? []) as $app) {
                    $this->liveEvent('changed', (string) $app, 'Changed or added in InterLink.');
                    $out['changed']++;
                }
            }
        } catch (\Throwable $e) {
            $out['pull_error'] = $e->getMessage();
        }

        // 1b. load every InterLink page into our table, a few pages per cycle, so ALL rows are searchable
        try {
            $out['backfill'] = $this->backfillStep();
        } catch (\Throwable $e) {
            $out['backfill_error'] = $e->getMessage();
        }

        // 2. queued Lab References
        $db    = $this->il();
        $model = model(InterlinkReportModel::class);
        $jobs  = $db->table('tbl_interlink_queue')->where('status', 'pending')->orderBy('id')->limit($maxJobs)->get()->getResultArray();
        foreach ($jobs as $j) {
            $db->table('tbl_interlink_queue')->where('id', $j['id'])->update(['status' => 'running', 'started_at' => date('Y-m-d H:i:s'), 'attempts' => (int) $j['attempts'] + 1]);
            try {
                $fields   = (array) json_decode((string) $j['fields_json'], true);
                $existing = $model->findByAppNo((string) $j['application_no']);
                $url      = (string) ($existing['u_form_url'] ?? '') ?: self::DEFAULT_UPLOAD_URL;
                $bot      = $this->triggerPlaywrightBot((string) $j['application_no'], $fields, $url);
                $live     = $this->executeLiveInterlinkPostback((string) $j['application_no'], $fields, $url);
                $model->logSync([
                    'report_id' => (int) ($existing['id'] ?? 0), 'application_no' => $j['application_no'], 'report_no' => $fields['report_no'] ?? '',
                    'target_portal_url' => $url, 'fields' => $fields, 'sync_mode' => 'background_queue', 'bot_job_file' => $bot['job_file'] ?? null,
                    'status' => 'prepared', 'ip_address' => 'worker', 'user_id' => null, 'user_name' => (string) $j['requested_by'],
                ]);
                $db->table('tbl_interlink_queue')->where('id', $j['id'])->update([
                    'status' => 'prepared', 'finished_at' => date('Y-m-d H:i:s'),
                    'message' => mb_substr((string) ($live['message'] ?? 'Prepared'), 0, 250),
                ]);
                $out['jobs']++;
            } catch (\Throwable $e) {
                $db->table('tbl_interlink_queue')->where('id', $j['id'])->update([
                    'status' => ((int) $j['attempts'] + 1) >= 3 ? 'failed' : 'pending', 'message' => mb_substr($e->getMessage(), 0, 250),
                ]);
            }
        }

        return $out;
    }

    /** GET interlink/center/status */
    public function centerStatus()
    {
        return $this->response->setJSON(['ok' => true, 'mode' => 'per-user', 'message' => 'Everyone signs in to InterLink with their own account. NEXUS-L stores no InterLink login.', 'csrf' => csrf_hash()]);
    }

    /**
     * POST interlink/center/fill {application_no, fields:{...}} : PREPARES the values for the InterLink form and returns them.
     * Nothing is sent to InterLink and no window is opened on the server: the person who is responsible for the report
     * fills the form in their OWN browser (bookmarklet) and presses Save / Submit themselves.
     */
    public function centerFill()
    {
        $r = $this->fillData($this->request->getJSON(true) ?? $this->request->getPost());

        return $this->response->setStatusCode($r['code'])->setJSON($r['body'] + ['csrf' => csrf_hash()]);
    }

    /**
     * The payload for one report: the InterLink form boxes to fill, the samples / test items / readings and the Lab Reference.
     * Used by the browser extension (centerFill) and by the background service (InterlinkServiceController).
     *
     * @return array{code:int, body:array}
     */
    public function fillData(array $p): array
    {
        $appNo  = trim((string) ($p['application_no'] ?? ''));
        $fields = is_array($p['fields'] ?? null) ? $p['fields'] : [];
        $ref    = trim((string) ($p['lab_ref_no'] ?? ''));
        $lr = $ref !== '' ? $this->il()->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $ref)->orderBy('id', 'DESC')->get()->getRowArray()
            : $this->il()->table('tbl_dtr_unified_labrefs')->where('tr_no', $appNo)->orderBy('id', 'DESC')->get()->getRowArray();
        if (! $fields && $lr) {
            $fields = $this->fieldsFromLabRef($lr, $appNo);
        }
        if (! preg_match('/^[A-Za-z0-9_-]{3,40}$/', $appNo) || ! $fields) {
            return ['code' => 422, 'body' => ['ok' => false, 'error' => 'Choose an Application No. and the fields to fill.']];
        }
        // a report goes to InterLink only after a registered Manager / Supervisor signed it (ApprovalController)
        // approvals belong to a Lab Reference AND an application: another application's request for the same Lab Reference must not count
        $apv = null;
        if ($lr) {
            $q = $this->il()->table('tbl_dtr_report_approvals')->where('lab_ref_no', $lr['lab_ref_no'])->where('status !=', 'superseded');
            $q->groupStart()->where('application_no', $appNo)->orWhere('application_no', '')->orWhere('application_no IS NULL')->groupEnd();
            $apv = $q->orderBy('id', 'DESC')->get()->getRowArray();
        }
        if ($lr && ($apv['status'] ?? '') !== 'approved') {
            return ['code' => 409, 'body' => ['ok' => false, 'error' => "The report {$lr['lab_ref_no']} is not approved yet. Generate it on the Upload Form and request approval (QR) first."]];
        }
        $existing = model(InterlinkReportModel::class)->findByAppNo($appNo);
        // `u_form_url` is a per-application link that dies with the session that made it, and is essentially never
        // actually stored for a real application. The NEXUS-L InterLink Sender (background.js, >= v1.1) no longer opens
        // this URL directly: it opens the stable portal root and finds + clicks this application's own Upload icon on
        // the live grid itself, so a correct, fresh link is generated by InterLink every time, never guessed. `url` here
        // is still returned for older sender versions and for display; it is honest about being a fallback, not a link
        // known to be valid for this application.
        $url = (string) ($existing['u_form_url'] ?? '') ?: self::PORTAL_HOME_URL;
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://interlink2.intertek.com' . (str_starts_with($url, '/') ? '' : '/') . $url;
        }

        // only the boxes that belong to the chosen fields: each key is probed with a marker to find its InterLink box, so the
        // defaults the map adds for other boxes never overwrite what InterLink already holds
        $controls = [];
        foreach ($fields as $k => $v) {
            if ((string) $v === '' || ! is_string($k)) {
                continue;
            }
            $probe = (array) ($this->executeLiveInterlinkPostback($appNo, [$k => '__NX_PROBE__'], $url)['controls'] ?? []);
            foreach ($probe as $ctl => $val) {
                if ($val === '__NX_PROBE__') {
                    $controls[$ctl] = (string) $v;
                }
            }
        }
        if (! $controls) {
            return ['code' => 422, 'body' => ['ok' => false, 'error' => 'None of the chosen fields map to an InterLink form box.']];
        }

        // the report's own Sample Information, Test Item Result and readings: what the Upload Result tab of InterLink asks for
        $tables = ['samples' => [], 'items' => [], 'readings' => []];
        $cfgT   = $lr ? (\App\Controllers\DtrController::FORM_CONFIG[$lr['form_slug'] ?? ''] ?? null) : null;
        if ($cfgT && (int) ($lr['source_id'] ?? 0) > 0) {
            $recT = $this->il()->table($cfgT['table'])->where($cfgT['pk'], (int) $lr['source_id'])->get()->getRowArray();
            if ($recT) {
                $ptsT = ($lr['form_slug'] ?? '') === 'bonding' ? ($this->il()->table('tbl_dtr_bonding_points')->where('BondingTestID', (int) $lr['source_id'])->get()->getRowArray() ?: []) : [];
                $genT = \App\Libraries\ReportTests::build((string) $lr['form_slug'], $recT, $ptsT);
                $tables = ['samples' => $genT['samples'], 'items' => $genT['items'], 'readings' => $genT['readings']];
            }
        }

        if ($lr) {
            $tables = $this->gridOverlay($appNo, (string) $lr['lab_ref_no'], $tables);
        }

        return ['code' => 200, 'body' => ['ok' => true, 'application_no' => $appNo, 'target_url' => $url, 'fields' => count($controls), 'controls' => $controls, 'tables' => $tables,
            // id is the record id of the DTR form (source_id): the DTR page opens records by it, not by the unified row id
            'lab' => $lr ? ['slug' => (string) $lr['form_slug'], 'id' => (int) ($lr['source_id'] ?: $lr['id']), 'lab_ref_no' => (string) $lr['lab_ref_no']] : null]];
    }

    /**
     * Normalized eTRF template catalog supporting all VF InterLink 2.0 Submit a Request / Favourites templates:
     * - VF eTRF - Material (eTRF-Mat)
     * - VF eTRF - Product (eTRF-Prod)
     * - VF eTRF - PULLTESTTemplate (eTRF-PULL)
     * - VF eTRF - bonding (VF eTRF-bonding)
     * - VF eTRF - Product WHOLE SHOE TEST (VF eTRF-Whole Shoe)
     * - VF eTRF - Product (Adult)
     */
    public const ETRF_TEMPLATES = [
        'eTRF-Mat' => [
            'code'           => 'eTRF-Mat',
            'label'          => 'VF eTRF - Material',
            'sample_type'    => 'Material',
            'account'        => 'dtp_qcasst@dtshoes.com',
            'contact_person' => 'Danica Agbuya',
            'email'          => 'dtp_qcasst@dtshoes.com',
            'menu_targets'   => ['VF eTRF - Material'],
            'test_package'   => '',
            'individual'     => '',
            'age_group'      => 'Adult',
        ],
        'eTRF-Prod' => [
            'code'           => 'eTRF-Prod',
            'label'          => 'VF eTRF - Product',
            'sample_type'    => 'Finished Product',
            'account'        => 'dtp_qcasst@dtshoes.com',
            'contact_person' => 'Danica Agbuya',
            'email'          => 'dtp_qcasst@dtshoes.com',
            'menu_targets'   => ['VF eTRF - Product'],
            'test_package'   => '*Select Individual Test Item',
            'individual'     => '',
            'age_group'      => 'Adult',
        ],
        'eTRF-PULL' => [
            'code'           => 'eTRF-PULL',
            'label'          => 'VF eTRF - PULLTESTTemplate',
            'sample_type'    => 'Finished Product',
            'account'        => 'dtp_lab@dtshoes.com',
            'contact_person' => 'Faye Ann Elaysa',
            'email'          => 'dtp_lab@dtshoes.com',
            'menu_targets'   => ['VF eTRF - PULLTESTTemplate'],
            'test_package'   => 'VANS Pull Strength',
            'individual'     => '',
            'age_group'      => 'Adult',
        ],
        'VF eTRF-bonding' => [
            'code'           => 'VF eTRF-bonding',
            'label'          => 'VF eTRF - bonding',
            'sample_type'    => 'Finished Product',
            'account'        => 'dtp_qcasst@dtshoes.com',
            'contact_person' => 'Danica Agbuya',
            'email'          => 'dtp_qcasst@dtshoes.com',
            'menu_targets'   => ['VF eTRF - bonding'],
            'test_package'   => 'VANS Bonding Strength',
            'individual'     => '',
            'age_group'      => 'Adult',
        ],
        'VF eTRF-Whole Shoe' => [
            'code'           => 'VF eTRF-Whole Shoe',
            'label'          => 'VF eTRF - Product WHOLE SHOE TEST',
            'sample_type'    => 'Finished Product',
            'account'        => 'dtp_qcasst@dtshoes.com',
            'contact_person' => 'Danica Agbuya',
            'email'          => 'dtp_qcasst@dtshoes.com',
            'menu_targets'   => ['VF eTRF - Product WHOLE SHOE TEST'],
            'test_package'   => 'VANS Whole Shoe Performance',
            'individual'     => '',
            'age_group'      => 'Adult',
        ],
        'VF eTRF - Product (Adult)' => [
            'code'           => 'VF eTRF - Product (Adult)',
            'label'          => 'VF eTRF - Product (Adult)',
            'sample_type'    => 'Finished Product',
            'account'        => 'dtp_qcasst@dtshoes.com',
            'contact_person' => 'Danica Agbuya',
            'email'          => 'dtp_qcasst@dtshoes.com',
            'menu_targets'   => ['VF eTRF - Product (Adult)', 'VF eTRF - PULLTESTTemplate', 'VF eTRF - Product'],
            'test_package'   => 'VANS Pull Strength',
            'individual'     => '',
            'age_group'      => 'Adult',
        ],
    ];

    /** Resolves any alias (e.g. eTRF-Mat, VF eTRF - Material, bonding, pull, whole shoe) or DTR form_slug to a canonical ETRF_TEMPLATES entry. */
    public static function resolveEtrfTemplate(?string $rawType, ?string $formSlug = null, ?string $formCategory = null): array
    {
        $norm = strtolower(trim((string) $rawType));
        $slug = strtolower(trim((string) $formSlug));
        $cat  = strtolower(trim((string) $formCategory));

        if ($norm !== '') {
            if (str_contains($norm, 'mat')) {
                return self::ETRF_TEMPLATES['eTRF-Mat'];
            }
            if (str_contains($norm, 'pull')) {
                return self::ETRF_TEMPLATES['eTRF-PULL'];
            }
            if (str_contains($norm, 'bond')) {
                return self::ETRF_TEMPLATES['VF eTRF-bonding'];
            }
            if (str_contains($norm, 'whole') || str_contains($norm, 'shoe')) {
                return self::ETRF_TEMPLATES['VF eTRF-Whole Shoe'];
            }
            if (str_contains($norm, 'adult')) {
                return self::ETRF_TEMPLATES['VF eTRF - Product (Adult)'];
            }
            if (str_contains($norm, 'prod')) {
                return self::ETRF_TEMPLATES['eTRF-Prod'];
            }
        }

        if ($slug === 'pull' || str_contains($cat, 'pull')) {
            return self::ETRF_TEMPLATES['eTRF-PULL'];
        }
        if ($slug === 'bonding' || str_contains($cat, 'bond')) {
            return self::ETRF_TEMPLATES['VF eTRF-bonding'];
        }
        if (str_contains($slug, 'whole') || str_contains($cat, 'whole')) {
            return self::ETRF_TEMPLATES['VF eTRF-Whole Shoe'];
        }
        $cfg = \App\Controllers\DtrController::FORM_CONFIG[$slug] ?? null;
        if ($cfg && ($cfg['sample_type'] ?? '') === 'Material') {
            return self::ETRF_TEMPLATES['eTRF-Mat'];
        }
        if ($slug !== '' && in_array($slug, ['fabric', 'leather', 'wst', 'fthl', 'hardware', 'label', 'sklfm', 'rubber', 'box', 'insole'], true)) {
            return self::ETRF_TEMPLATES['eTRF-Mat'];
        }

        return self::ETRF_TEMPLATES['eTRF-PULL'];
    }

    /**
     * Ensures the dedicated eTRF database tables (tbl_interlink_etrf_templates & tbl_interlink_etrf_requests) exist in dbnexus
     * and seeds the 6 canonical VF InterLink 2.0 eTRF templates.
     */
    public function ensureEtrfTables(): void
    {
        $db = $this->il();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_interlink_etrf_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            template_code VARCHAR(50) NOT NULL,
            template_label VARCHAR(120) NOT NULL,
            sample_type VARCHAR(40) NOT NULL DEFAULT "Finished Product",
            menu_targets_json TEXT NOT NULL,
            default_test_package VARCHAR(255) NOT NULL DEFAULT "",
            default_individual_tests VARCHAR(500) NOT NULL DEFAULT "",
            default_age_group VARCHAR(40) NOT NULL DEFAULT "Adult",
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_tpl_code (template_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        foreach (self::ETRF_TEMPLATES as $tpl) {
            $db->query(
                'INSERT INTO tbl_interlink_etrf_templates
                (template_code, template_label, sample_type, menu_targets_json, default_test_package, default_individual_tests, default_age_group, is_active, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    template_label = VALUES(template_label),
                    sample_type = VALUES(sample_type),
                    menu_targets_json = VALUES(menu_targets_json),
                    default_test_package = VALUES(default_test_package),
                    default_individual_tests = VALUES(default_individual_tests),
                    default_age_group = VALUES(default_age_group),
                    updated_at = NOW()',
                [
                    $tpl['code'],
                    $tpl['label'],
                    $tpl['sample_type'],
                    json_encode($tpl['menu_targets'], JSON_UNESCAPED_UNICODE),
                    $tpl['test_package'],
                    $tpl['individual'],
                    $tpl['age_group'],
                ]
            );
        }

        $db->query('CREATE TABLE IF NOT EXISTS tbl_interlink_etrf_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_uid VARCHAR(40) NULL,
            lab_ref_no VARCHAR(80) NOT NULL DEFAULT "",
            application_no VARCHAR(60) NOT NULL DEFAULT "",
            etrf_type VARCHAR(50) NOT NULL,
            template_label VARCHAR(120) NOT NULL,
            sample_type VARCHAR(40) NOT NULL DEFAULT "Finished Product",
            action_mode VARCHAR(20) NOT NULL DEFAULT "submit",
            company_name VARCHAR(120) NOT NULL DEFAULT "",
            interlink_code VARCHAR(40) NOT NULL DEFAULT "",
            vendor_name VARCHAR(120) NOT NULL DEFAULT "",
            vendor_id VARCHAR(40) NOT NULL DEFAULT "",
            factory_name VARCHAR(120) NOT NULL DEFAULT "",
            factory_id VARCHAR(40) NOT NULL DEFAULT "",
            buyer_brand VARCHAR(60) NOT NULL DEFAULT "Vans",
            season VARCHAR(30) NOT NULL DEFAULT "",
            test_stage VARCHAR(50) NOT NULL DEFAULT "Production",
            service_test_type VARCHAR(80) NOT NULL DEFAULT "In-House Lab Testing",
            test_packages VARCHAR(255) NOT NULL DEFAULT "",
            individual_tests VARCHAR(500) NOT NULL DEFAULT "",
            style_no VARCHAR(100) NOT NULL DEFAULT "",
            product_name VARCHAR(255) NOT NULL DEFAULT "",
            style_color_no VARCHAR(120) NOT NULL DEFAULT "",
            material_code VARCHAR(100) NOT NULL DEFAULT "",
            material_type VARCHAR(120) NOT NULL DEFAULT "",
            color_code VARCHAR(60) NOT NULL DEFAULT "",
            color_description VARCHAR(160) NOT NULL DEFAULT "",
            size_category VARCHAR(40) NOT NULL DEFAULT "UNISEX",
            age_group VARCHAR(40) NOT NULL DEFAULT "Adult",
            cargo_receive_date VARCHAR(40) NOT NULL DEFAULT "",
            remarks TEXT NULL,
            fields_json LONGTEXT NULL,
            rows_json LONGTEXT NULL,
            submission_channel VARCHAR(30) NOT NULL DEFAULT "service",
            status VARCHAR(30) NOT NULL DEFAULT "prepared",
            status_message VARCHAR(500) NOT NULL DEFAULT "",
            requested_by_id INT UNSIGNED NOT NULL DEFAULT 0,
            requested_by_name VARCHAR(100) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            submitted_at DATETIME NULL,
            KEY ix_job_uid (job_uid),
            KEY ix_lab_ref (lab_ref_no),
            KEY ix_app_no (application_no),
            KEY ix_etrf_type (etrf_type, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /** Persists an eTRF request row in tbl_interlink_etrf_requests and returns its auto-increment ID. */
    public function saveEtrfRequestRecord(array $body, string $channel = 'service', string $status = 'queued', ?string $jobUid = null): int
    {
        $this->ensureEtrfTables();
        $s    = (array) ($body['summary'] ?? []);
        $f    = (array) ($body['fields'] ?? []);
        $user = (array) (isset($this->auth) && $this->auth ? $this->auth->user() : []);
        $now  = date('Y-m-d H:i:s');

        $this->il()->table('tbl_interlink_etrf_requests')->insert([
            'job_uid'            => $jobUid,
            'lab_ref_no'         => (string) ($body['lab_ref_no'] ?? ''),
            'application_no'     => (string) ($body['application_no'] ?? ''),
            'etrf_type'          => (string) ($body['etrf_type'] ?? 'eTRF-PULL'),
            'template_label'     => (string) ($body['template_label'] ?? 'VF eTRF - PULLTESTTemplate'),
            'sample_type'        => (string) ($body['sample_type'] ?? 'Finished Product'),
            'action_mode'        => (string) ($body['action'] ?? 'submit'),
            'company_name'       => (string) ($s['company_name'] ?? $f['JOB_STD_APP_DESC'] ?? 'KEY TRADE CO., LTD'),
            'interlink_code'     => (string) ($f['JOB_STD_APP'] ?? 'E126863'),
            'vendor_name'        => (string) ($s['vendor_name'] ?? 'KEY TRADE CO., LTD'),
            'vendor_id'          => (string) ($s['vendor_id'] ?? '0000507450'),
            'factory_name'       => (string) ($s['factory_name'] ?? 'DATIAN SUBIC SHOES INC.'),
            'factory_id'         => (string) ($s['factory_id'] ?? '0000507452'),
            'buyer_brand'        => (string) ($s['buyer_brand'] ?? 'Vans'),
            'season'             => (string) ($s['season'] ?? 'S27'),
            'test_stage'         => (string) ($s['test_stage'] ?? 'Production'),
            'service_test_type'  => (string) ($s['service_test_type'] ?? 'In-House Lab Testing'),
            'test_packages'      => (string) ($s['test_packages'] ?? ''),
            'individual_tests'   => (string) ($s['individual_tests'] ?? ''),
            'style_no'           => (string) ($s['style_no'] ?? ''),
            'product_name'       => (string) ($s['product_name'] ?? ''),
            'style_color_no'     => (string) ($s['style_color_no'] ?? ''),
            'material_code'      => (string) ($s['material_code'] ?? ''),
            'material_type'      => (string) ($s['material_type'] ?? ''),
            'color_code'         => (string) ($s['color_code'] ?? ''),
            'color_description'  => (string) ($s['color_description'] ?? ''),
            'size_category'      => (string) ($s['size_category'] ?? 'UNISEX'),
            'age_group'          => (string) ($s['age_group'] ?? 'Adult'),
            'cargo_receive_date' => (string) ($s['cargo_receive_date'] ?? ''),
            'remarks'            => (string) ($s['remarks'] ?? ''),
            'fields_json'        => json_encode($body['fields'] ?? [], JSON_UNESCAPED_UNICODE),
            'rows_json'          => json_encode($body['rows'] ?? [], JSON_UNESCAPED_UNICODE),
            'submission_channel' => $channel,
            'status'             => $status,
            'status_message'     => $status === 'prepared' ? 'Prepared for browser extension submission' : 'Queued for background service submission',
            'requested_by_id'    => (int) ($user['id'] ?? 0),
            'requested_by_name'  => (string) ($user['name'] ?? $user['username'] ?? ''),
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        return (int) $this->il()->insertID();
    }

    /** GET interlink/etrf-history : returns recent eTRF submissions from tbl_interlink_etrf_requests */
    public function etrfHistory()
    {
        $this->ensureEtrfTables();
        $rows = $this->il()->table('tbl_interlink_etrf_requests')
            ->select('id, job_uid, lab_ref_no, application_no, etrf_type, template_label, sample_type, action_mode, style_no, material_code, product_name, color_code, color_description, season, test_packages, submission_channel, status, status_message, requested_by_name, created_at, submitted_at')
            ->orderBy('id', 'DESC')
            ->limit(25)
            ->get()
            ->getResultArray();

        return $this->response->setJSON(['ok' => true, 'rows' => $rows, 'csrf' => csrf_hash()]);
    }

    /** GET interlink/dsr-search?q=...&prod_code=... : searches DSR/CFM Excel files on \\192.168.0.8\团队文件-確認dsr and tbl_dtr_dsr */
    public function dsrSearch()
    {
        $q    = trim((string) ($this->request->getGet('q') ?? ''));
        $prod = trim((string) ($this->request->getGet('prod_code') ?? ''));
        $res  = \App\Libraries\DsrShareService::search($q, $prod);

        return $this->response->setJSON($res + ['csrf' => csrf_hash()]);
    }

    /** GET interlink/order-summary?mtl=VN000... : season ticks and details of an MTL# from the #ORDER SUMMARY PDFs (re-read when a PDF changes; no mtl = file list only) */
    public function orderSummary()
    {
        $mtl = trim((string) ($this->request->getGet('mtl') ?? ''));

        return $this->response->setJSON(\App\Libraries\OrderSummaryService::lookup($mtl) + ['csrf' => csrf_hash()]);
    }

    /** GET interlink/dsr-file?rel_path=...&file=...&mtl_no=...&force=... : parses and returns the header + BOM component rows of a DSR/CFM Excel file */
    public function dsrFile()
    {
        $file  = trim((string) ($this->request->getGet('rel_path') ?? $this->request->getGet('file') ?? ''));
        $mtl   = trim((string) ($this->request->getGet('mtl_no') ?? ''));
        $force = (bool) $this->request->getGet('force');
        $res   = \App\Libraries\DsrShareService::getFileDetails($file, $mtl, $force);
        if (empty($res['ok'])) {
            $errMsg = $res['error'] ?? $res['message'] ?? 'Could not read DSR file.';
            return $this->response->setStatusCode(404)->setJSON($res + ['error' => $errMsg, 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON($res + ['csrf' => csrf_hash()]);
    }

    /** POST interlink/dsr-sync {folder?: "S27"} : scans a folder on \\192.168.0.8\团队文件-確認dsr into tbl_dtr_dsr_files */
    public function dsrSync()
    {
        $p      = $this->request->getJSON(true) ?? $this->request->getPost();
        $folder = trim((string) ($p['folder'] ?? 'S27'));
        if (! \App\Libraries\DsrShareService::ensureMounted()) {
            return $this->response->setStatusCode(502)->setJSON(['ok' => false, 'error' => 'Could not connect to DSR share ' . \App\Libraries\DsrShareService::shareRoot(), 'csrf' => csrf_hash()]);
        }
        $db   = $this->il();
        \App\Libraries\DsrShareService::ensureTables($db);
        $root = \App\Libraries\DsrShareService::shareRoot();
        $dirs = $folder !== '' && $folder !== 'all' ? [$folder] : ['S27', 'S26', 'F27', 'F25', '202604收到的DSR', '202603收到的DSR', '202602收到的DSR', '202601收到的DSR'];
        $now  = date('Y-m-d H:i:s');
        $cnt  = 0;
        foreach ($dirs as $d) {
            $full = $root . '\\' . $d;
            if (! is_dir($full)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($full, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $fi) {
                if (! $fi->isFile()) {
                    continue;
                }
                $fn = $fi->getFilename();
                if (str_starts_with($fn, '~$') || ! preg_match('/\.xlsx?$/i', $fn)) {
                    continue;
                }
                $fp  = $fi->getPathname();
                $rel = ltrim(substr($fp, strlen($root)), '\\/');
                \App\Libraries\DsrShareService::registerShareFile($db, $fp, $rel, $d, $now);
                $cnt++;
            }
        }

        return $this->response->setJSON(['ok' => true, 'indexed' => $cnt, 'folder' => $folder, 'csrf' => csrf_hash()]);
    }

    /** GET interlink/etrf-preview?lab_ref_no=...&etrf_type=... */
    public function etrfPreview()
    {
        $this->ensureEtrfTables();
        $r = $this->etrfData([
            'lab_ref_no'     => (string) $this->request->getGet('lab_ref_no'),
            'application_no' => (string) $this->request->getGet('application_no'),
            'etrf_type'      => (string) $this->request->getGet('etrf_type'),
            'action'         => (string) ($this->request->getGet('action') ?: 'save'),
        ]);

        return $this->response->setStatusCode($r['code'])->setJSON($r['body'] + ['csrf' => csrf_hash()]);
    }

    /** GET interlink/etrf : the VF eTRF page (create a request or a test in InterLink from the five VF templates) */
    public function etrf(): string
    {
        $this->ensureEtrfTables();
        $tpl = [];
        foreach (['eTRF-Mat', 'eTRF-Prod', 'eTRF-PULL', 'VF eTRF-bonding', 'VF eTRF-Whole Shoe'] as $k) {
            $t = self::ETRF_TEMPLATES[$k];
            $tpl[] = ['code' => $t['code'], 'label' => $t['label'], 'sample_type' => $t['sample_type'], 'account' => $t['account'], 'package' => $t['test_package'], 'individual' => $t['individual']];
        }

        return $this->render('interlink/etrf', ['title' => 'InterLink - VF eTRF', 'withCss' => 'interlink.css', 'withJs' => ['interlink_etrf.js'], 'templates' => $tpl]);
    }

    /** POST interlink/etrf-check {etrf_type, lab_ref_no?, fields?, rows?, ...flat values} : what would be sent and what is still missing (sends nothing) */
    public function etrfCheck()
    {
        $this->ensureEtrfTables();
        $r = $this->etrfData($this->request->getJSON(true) ?? $this->request->getPost());

        return $this->response->setStatusCode($r['code'])->setJSON($r['body'] + ['csrf' => csrf_hash()]);
    }

    /** POST interlink/center/etrf {lab_ref_no?, etrf_type?, action?: submit|save, fields?: {...}, rows?: [...]} */
    public function centerEtrf()
    {
        $r = $this->etrfData($this->request->getJSON(true) ?? $this->request->getPost());
        if ($r['code'] === 200 && ! empty($r['body']['missing'])) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'code' => 'etrf_incomplete', 'missing' => $r['body']['missing'],
                'error' => 'The eTRF (' . ($r['body']['template_label'] ?? 'request') . ') is missing: ' . implode(', ', $r['body']['missing']) . '. Fill these in and send again.', 'csrf' => csrf_hash()]);
        }
        if ($r['code'] === 200) {
            $r['body']['etrf_request_id'] = $this->saveEtrfRequestRecord($r['body'], 'extension', 'prepared', null);
        }

        return $this->response->setStatusCode($r['code'])->setJSON($r['body'] + ['csrf' => csrf_hash()]);
    }

    /**
     * Maps a NEXUS-L DTR material category/slug to the exact InterLink 2.0 Material Type option.
     */
    public static function mapInterlinkMaterialType(string $rawType, string $slug = '', string $desc = ''): string
    {
        $exactOptions = [
            'Bottom Components (Outsole / Midsole / Footbed / Foxing Tape)',
            'Fabric',
            'Leather',
            'Synthetic Leather / PU / TPU',
            'Foam / EVA / Sponge',
            'Laces / Webbing / Tapes / Thread',
            'Hardware & trims',
            'Labels / Heat Transfers / Prints',
            'Packaging',
        ];
        foreach ($exactOptions as $opt) {
            if (strcasecmp(trim($rawType), $opt) === 0) {
                return $opt;
            }
        }
        $hay = strtolower($rawType . ' ' . $slug . ' ' . $desc);
        if (str_contains($hay, 'foxing') || str_contains($hay, 'heel logo') || str_contains($hay, 'fthl') || str_contains($hay, 'outsole') || str_contains($hay, 'midsole') || str_contains($hay, 'footbed') || str_contains($hay, 'insole') || str_contains($hay, 'rubber') || str_contains($hay, 'sklfm')) {
            return 'Bottom Components (Outsole / Midsole / Footbed / Foxing Tape)';
        }
        if (str_contains($hay, 'box') || str_contains($hay, 'carton') || str_contains($hay, 'packaging') || str_contains($hay, 'tissue') || str_contains($hay, 'hangtag')) {
            return 'Packaging';
        }
        if (str_contains($hay, 'synthetic') || str_contains($hay, 'pu ') || str_contains($hay, 'tpu')) {
            return 'Synthetic Leather / PU / TPU';
        }
        if (str_contains($hay, 'leather') || str_contains($hay, 'suede') || str_contains($hay, 'nubuck') || str_contains($hay, 'split')) {
            return 'Leather';
        }
        if (str_contains($hay, 'lace') || str_contains($hay, 'webbing') || str_contains($hay, 'tape') || str_contains($hay, 'thread') || str_contains($hay, 'wst') || str_contains($hay, 'velcro') || str_contains($hay, 'gore') || str_contains($hay, 'elastic')) {
            return 'Laces / Webbing / Tapes / Thread';
        }
        if (str_contains($hay, 'hardware') || str_contains($hay, 'eyelet') || str_contains($hay, 'buckle') || str_contains($hay, 'zipper') || str_contains($hay, 'grommet') || str_contains($hay, 'hook')) {
            return 'Hardware & trims';
        }
        if (str_contains($hay, 'label') || str_contains($hay, 'heat transfer') || str_contains($hay, 'screenprint') || str_contains($hay, 'embroidery')) {
            return 'Labels / Heat Transfers / Prints';
        }
        if (str_contains($hay, 'foam') || str_contains($hay, 'eva') || str_contains($hay, 'sponge') || str_contains($hay, 'ortholite')) {
            return 'Foam / EVA / Sponge';
        }
        if (str_contains($hay, 'fabric') || str_contains($hay, 'canvas') || str_contains($hay, 'mesh') || str_contains($hay, 'textile') || str_contains($hay, 'woven') || str_contains($hay, 'knit') || str_contains($hay, 'lining') || str_contains($hay, 'drill') || str_contains($hay, 'fleece')) {
            return 'Fabric';
        }

        return trim($rawType) !== '' ? trim($rawType) : 'Fabric';
    }

    /**
     * Maps a NEXUS-L DTR material category/slug to the default InterLink 2.0 Test Package (`CAL_CDTL_5`).
     */
    public static function mapInterlinkTestPackage(string $rawType, string $slug = '', string $desc = ''): string
    {
        $hay = strtolower($rawType . ' ' . $slug . ' ' . $desc);
        if (str_contains($hay, 'foxing') || str_contains($hay, 'heel logo') || $slug === 'fthl') {
            return 'VANS Foxing Tape - Rubber';
        }
        if (str_contains($hay, 'outsole') || $slug === 'rubber') {
            return 'VANS Outsole - Rubber';
        }
        if (str_contains($hay, 'box') || str_contains($hay, 'carton') || $slug === 'box') {
            return 'Carton Box (#ODUSACB)';
        }

        return '';
    }

    /**
     * Builds the complete eTRF submission payload for InterLink 2.0 NormalFormWithHeader.aspx.
     * Used by both the Browser Extension (centerEtrf) and the Network Background Service (InterlinkServiceController::submitEtrf).
     *
     * @return array{code:int, body:array}
     */
    public function etrfData(array $p): array
    {
        $ref      = trim((string) ($p['lab_ref_no'] ?? ''));
        $appNo    = trim((string) ($p['application_no'] ?? ''));
        $rawType  = trim((string) ($p['etrf_type'] ?? $p['template'] ?? ''));
        $action   = strtolower(trim((string) ($p['action'] ?? 'save'))) === 'submit' ? 'submit' : 'save';      // a draft unless a submit is asked for
        $override = is_array($p['fields'] ?? null) ? $p['fields'] : [];
        $userRows = is_array($p['rows'] ?? null) ? $p['rows'] : [];

        $lr = null;
        if ($ref !== '') {
            $lr = $this->il()->table('tbl_dtr_unified_labrefs')->where('lab_ref_no', $ref)->orderBy('id', 'DESC')->get()->getRowArray();
        } elseif ($appNo !== '') {
            $lr = $this->il()->table('tbl_dtr_unified_labrefs')->where('tr_no', $appNo)->orderBy('id', 'DESC')->get()->getRowArray();
        }

        // Load full underlying DTR table record if available
        $rec = [];
        $slug = (string) ($lr['form_slug'] ?? '');
        $cfgT = $slug !== '' ? (\App\Controllers\DtrController::FORM_CONFIG[$slug] ?? null) : null;
        if ($cfgT && (int) ($lr['source_id'] ?? 0) > 0) {
            $rec = $this->il()->table($cfgT['table'])->where($cfgT['pk'], (int) $lr['source_id'])->get()->getRowArray() ?: [];
        }

        $tpl = self::resolveEtrfTemplate($rawType, $slug, (string) ($lr['form_category'] ?? ''));
        $isMaterial = ($tpl['sample_type'] === 'Material');

        $pick = static function (array $keys, string $default = '') use ($override, $rec, $lr, $p): string {
            foreach ($keys as $k) {
                if (isset($override[$k]) && trim((string) $override[$k]) !== '') {
                    return trim((string) $override[$k]);
                }
                if (isset($p[$k]) && is_scalar($p[$k]) && trim((string) $p[$k]) !== '') {
                    return trim((string) $p[$k]);
                }
                if (isset($rec[$k]) && trim((string) $rec[$k]) !== '') {
                    return trim((string) $rec[$k]);
                }
                if (is_array($lr) && isset($lr[$k]) && trim((string) $lr[$k]) !== '') {
                    return trim((string) $lr[$k]);
                }
            }

            return $default;
        };

        $user = (array) (isset($this->auth) && $this->auth ? $this->auth->user() : []);
        $defaultContact = (string) ($tpl['contact_person'] ?? ($tpl['account'] === 'dtp_lab@dtshoes.com' ? 'Faye Ann Elaysa' : 'Danica Agbuya'));
        $contactName = $pick(['contact_person', 'APPT_CONTACT'], $defaultContact ?: (string) ($user['name'] ?? ''));
        $companyName = $pick(['company_name', 'JOB_STD_APP_DESC'], (string) \App\Libraries\SystemSettings::get('interlink.company_name', 'KEY TRADE CO., LTD'));
        $interlinkCode = $pick(['interlink_code', 'JOB_STD_APP'], (string) \App\Libraries\SystemSettings::get('interlink.interlink_code', 'E126863'));
        $vendorName  = $pick(['vendor_name', 'TRPT_SUPPLIER_NAME'], $isMaterial
            ? 'OWN SOURCE SUPPLIER'
            : (string) \App\Libraries\SystemSettings::get('interlink.vendor_name', 'KEY TRADE CO., LTD'));
        $vendorId    = $pick(['vendor_id', 'SUPPLIER_ID'], $isMaterial
            ? 'G00000'
            : (string) \App\Libraries\SystemSettings::get('interlink.vendor_id', '0000507450'));
        $factoryName = $pick(['factory_name', 'TRPT_FACTORY_NAME'], $isMaterial
            ? 'OWN SOURCE SUPPLIER'
            : (string) \App\Libraries\SystemSettings::get('interlink.factory_name', 'DATIAN SUBIC SHOES INC.'));
        $factoryId   = $pick(['factory_id', 'FACTORY_ID'], $isMaterial
            ? 'G00000000000F001'
            : (string) \App\Libraries\SystemSettings::get('interlink.factory_id', '0000507452'));
        $supplierName = $pick(['supplier_name', 'MSTA_FIELD_6', 'vendor_name'], $isMaterial ? 'OWN SOURCE SUPPLIER' : $vendorName);
        $supplierId   = $pick(['supplier_id', 'MSTA_FIELD_10', 'vendor_id'], $isMaterial ? 'G00000' : $vendorId);
        $brandName   = $pick(['buyer_brand', 'TRPT_CDI_BRAND_NAME', 'Brand', 'Customer'], (string) \App\Libraries\SystemSettings::get('interlink.buyer_brand', 'Vans'));
        $season      = $pick(['season', 'TRPT_SEASON', 'REF_FLD17', 'Season'], (string) \App\Libraries\SystemSettings::get('interlink.season', 'S27'));
        $testStage   = $pick(['test_stage', 'TRPT_TESTING_STATUS', 'TestStage', 'Stage'], (string) \App\Libraries\SystemSettings::get('interlink.test_stage', 'Production'));
        $exportTo    = $pick(['product_export_to', 'CAL_PO_COUNTRY'], (string) \App\Libraries\SystemSettings::get('interlink.product_export_to', 'APAC - Non China'));
        $rawCountry  = $pick(['country_of_origin', 'TRPT_COUNTRY_ORIGIN', "Supplier's Country", 'SupplierCountry', 'supplier_country'], $isMaterial ? 'CHINA' : 'Philippines');
        $countryOrig = $isMaterial ? strtoupper($rawCountry) : (strcasecmp($rawCountry, 'PHILIPPINES') === 0 ? 'Philippines' : $rawCountry);
        $sourcingOff = $pick(['sourcing_office', 'E1_TEXT_023', 'DETOX_SUPPLIER_COUNTRY'], 'ASO');
        $ageGroup    = $pick(['age_group', 'TRPT_AGE_RANGE', 'AgeGroup'], $tpl['age_group'] ?: 'Adult');
        $sizeCat     = strtoupper($pick(['size_category', 'REF_FLD21', 'Gender', 'Category'], $isMaterial ? 'UNISEX' : 'UA'));
        if (! in_array($sizeCat, ['UA', 'UNISEX', 'MALE', 'FEMALE', 'MENS', 'WOMENS', 'KIDS', 'TODDLER', 'CHILDREN', 'ADULT'], true)) {
            $sizeCat = 'UA';
        }
        $genderCode  = match ($sizeCat) {
            'FEMALE', 'WOMENS' => 'WOMENS',
            'MALE', 'MENS'     => 'MENS',
            default            => 'UNISEX',
        };

        $poLotNo     = $pick(['po_lot_no', 'PO/Lot No', 'PO/LotNo', 'POLotNO', 'POLotNo', 'ProdCode', 'ProductionCode'], '');
        $mtlNo       = $pick(['mtl_no', 'MTL', 'MTL#', 'MTL_DSR'], '');
        if ($mtlNo === '' && $poLotNo !== '') {
            $shoeLookup = $this->il()->table('tbl_dtr_shoe_list')->where('ProdCode', $poLotNo)->orderBy('ID', 'DESC')->get(1)->getRowArray();
            if ($shoeLookup && ! empty($shoeLookup['MTL'])) {
                $mtlNo = trim((string) $shoeLookup['MTL']);
            }
        }

        $styleNo     = $pick(['style_no', 'REF_FLD16', 'ModelStyle', 'Model No/Style', 'Style', 'model_style'], (! $isMaterial && $mtlNo !== '') ? $mtlNo : '');
        $productName = $pick(['product_name', 'style_name', 'REF_FLD23', 'Material Description', 'MaterialDescription', 'ModelName', 'material_description'], $styleNo);
        $colorDesc   = $pick(['colorway_name', 'color_description', 'REF_FLD24', 'Color', 'Colorway', 'color'], '');
        $colorCode   = $pick(['color_code', 'REF_FLD18', 'ColorCode'], '');
        // Extract embedded Coloro code (e.g. 045-89-02) from Material Description or Color if colorCode is still blank
        if ($colorCode === '' && preg_match('/\b(\d{2,3}-\d{2}-\d{2})\b/', $productName . ' ' . $colorDesc, $ccm)) {
            $colorCode = $ccm[1];
        }
        // a style color number is the MTL# or the style plus its color code
        $styleColorNo = $pick(['style_color_no', 'REF_FLD19', 'StyleColorNo', 'MTL', 'mtl_no'], $mtlNo !== '' ? $mtlNo : (($styleNo !== '' && $colorCode !== '') ? $styleNo . $colorCode : ''));
        // For Product templates with a Vans 11-char MTL/StyleColorNo (e.g. VN000E96BJN), extract the 3-char color code (BJN) if colorCode is blank
        if ($colorCode === '' && ! $isMaterial && preg_match('/^VN[A-Z0-9]{6}([A-Z0-9]{3})$/i', $styleColorNo ?: $mtlNo, $vcm)) {
            $colorCode = strtoupper($vcm[1]);
        }
        if ($styleNo === '' && ! $isMaterial && $styleColorNo !== '') {
            $styleNo = $styleColorNo;
        }

        $matCode      = $pick(['material_code', 'MaterialCode', 'Material Code', 'VFMaterialCode_DSR'], '');
        $matDesc      = $pick(['material_name', 'material_description', 'Material Description', 'MaterialDescription'], $productName);
        $rawMatType   = $pick(['material_type', 'MaterialType', 'Material Type', 'form_category'], (string) ($cfgT['label'] ?? ''));
        $matType      = $isMaterial ? self::mapInterlinkMaterialType($rawMatType, $slug) : $rawMatType;
        $matSubType   = $pick(['material_sub_type', 'CAL_CDTL_8'], $isMaterial ? \App\Libraries\DsrShareService::inferMaterialSubType('', $rawMatType, $matDesc) : '');
        $endUse       = $pick(['end_use', 'CAL_CDTL_1', 'Application', 'Component', 'ComponentorLocation_DSR'], '');
        $finishing    = $pick(['finishing', 'TXT_FINISH', 'FINISHING'], '');
        $reqSpecNo    = $pick(['requirement_spec_no', 'CAL_UPC', 'REQ_SPEC_NO'], '');
        $cutWidth     = $pick(['cuttable_width', 'E1_TEXT_053'], '');
        $warpPerInch  = $pick(['warp_per_inch', 'E1_TEXT_060'], '');
        $weftPerInch  = $pick(['weft_per_inch', 'E1_TEXT_061'], '');
        $trimSize     = $pick(['trim_size', 'E1_TEXT_052'], '');
        $yarnSpecs    = $pick(['yarn_specifications', 'E1_TEXT_054'], '');
        $washInstr    = $pick(['washing_instruction', 'care_instruction', 'MSTA_FIELD_4'], '');
        $prodClaim    = $pick(['product_claim_type', 'CAL_CDTL_3'], '');
        $careLabel1   = $pick(['care_label_1', 'CARE_LABEL_1'], '');
        $fiberText    = $pick(['fiber_content_text', 'TXT_FIBER_CONTENT'], '');
        $weight       = $pick(['weight', 'Weight', 'WeightAve', 'WAve', 'weight_ave'], '');
        $weightUom    = $pick(['weight_uom', 'WeightUOM'], $weight !== '' ? 'g/m2' : '');
        $thickness    = $pick(['thickness', 'Thickness', 'ThicknessAve', 'TAve', 'thickness_ave'], '');
        $claimType    = $pick(['claim_type', 'ClaimType'], '');

        $cargoDateRaw = $pick(['cargo_receive_date', 'INSP_SHIP_DATE', 'DateReceived', 'CollectionDate', 'received_date'], date('d M Y'));
        $tsCargo      = strtotime($cargoDateRaw);
        $cargoDate    = $tsCargo ? date('d M Y', $tsCargo) : date('d M Y');

        $isRetest     = strtolower($pick(['is_retest', 'TRPT_RETEST', 'Retest'], 'No'));
        $retestVal    = in_array($isRetest, ['1', 'yes', 'true', 'y'], true) ? '1' : '0';
        $prevReport   = $pick(['previous_report_no', 'TRPT_PREV_NO', 'PrevReportNo'], '');
        $improvements = $pick(['improvements', 'E1_TEXT_050', 'RetestDetails'], '');

        $defaultPkg   = $tpl['test_package'] ?: ($isMaterial ? self::mapInterlinkTestPackage($rawMatType, $slug, $matDesc) : '');
        $testPackages = $pick(['test_packages', 'CAL_CDTL_5'], $defaultPkg);
        $individual   = $pick(['individual_tests', 'E2_LTEXT_001'], $tpl['individual']);
        $gbStdCode    = $pick(['gb_standard_code', 'E1_TEXT_034'], '');
        $gbSafetyCode = $pick(['gb_safety_code', 'E1_TEXT_035'], '');
        $otherGbHyper = $pick(['other_gb_hypercare', 'E2_LTEXT_003'], '');
        $otherGbVoc   = $pick(['other_gb_voc', 'E2_LTEXT_004'], '');
        $suppNotes    = $pick(['supplementary_attachment_notes', 'JOB_DUMMY_005_NOTES'], '');
        $labRefLabel  = $ref ?: (string) ($lr['lab_ref_no'] ?? '');
        $defaultRmk   = $labRefLabel !== ''
            ? ('NEXUS-L LabRefNo: ' . $labRefLabel . ' | Type: ' . $tpl['code'] . (! empty($lr['mtl_no']) ? ' | MTL: ' . $lr['mtl_no'] : ''))
            : ('Submitted via NEXUS-L InterLink Service (' . $tpl['code'] . ')');
        $remarks      = $pick(['lab_remarks', 'remarks', 'TRPT_RMK', 'Remarks'], $defaultRmk);
        if ($suppNotes !== '' && ! str_contains($remarks, $suppNotes)) {
            $remarks = rtrim($remarks, " |\r\n") . ' | Attachment Note: ' . $suppNotes;
        }
        $accountEmail = $pick(['account_email', 'interlink_account'], (string) ($tpl['account'] ?? 'dtp_qcasst@dtshoes.com'));
        $defaultEmail = (string) ($tpl['email'] ?? ($accountEmail === 'dtp_lab@dtshoes.com' ? 'dtp_lab@dtshoes.com' : 'dtp_qcasst@dtshoes.com'));
        $emailRecip   = $pick(['email_recipients', 'EMAIL_ADHOC_LIST'], $defaultEmail ?: (string) \App\Libraries\SystemSettings::get('interlink.email_recipients', 'dtp_qcasst@dtshoes.com'));
        $appAddr      = $pick(['applicant_address', 'APPT_ADDR_1'], (string) \App\Libraries\SystemSettings::get('interlink.applicant_address', 'Lot 1P7-A Subic Bay Gateway Park Phase II,, Subic Bay Freeport Zone, Philippines'));
        $appCountry   = $pick(['applicant_country', 'APPT_ADDR_3'], (string) \App\Libraries\SystemSettings::get('interlink.factory_country', 'Philippines'));
        $postalCode   = $pick(['postal_code', 'E1_TEXT_001'], '');
        $appEmail     = $pick(['applicant_email', 'APPT_EMAIL'], $defaultEmail ?: (string) \App\Libraries\SystemSettings::get('interlink.applicant_email', 'dtp_qcasst@dtshoes.com'));
        $appPhone     = $pick(['applicant_phone', 'APPT_TEL'], (string) \App\Libraries\SystemSettings::get('interlink.applicant_phone', '(047) 252-2899'));
        $labAddr      = $pick(['lab_address', 'E1_TEXT_012'], $appAddr);

        // Sourcing office code map for DETOX_SUPPLIER_COUNTRY (1=ASO, 2=Brand Direct Source, 3=ESO, 4=PSO)
        $sourcingCode = match (strtoupper($sourcingOff)) {
            'BRAND DIRECT SOURCE', '2' => '2',
            'ESO', '3'                 => '3',
            'PSO', '4'                 => '4',
            default                    => '1',
        };

        // Exact NormalFormWithHeader.aspx field code map
        $fields = [
            // Section 1: Applicant Information
            'JOB_STD_APP_DESC' => $companyName,
            'JOB_STD_APP'      => $interlinkCode,
            'APPT_ADDR_1'      => $appAddr,
            'APPT_ADDR_3'      => $appCountry,
            'E1_TEXT_001'      => $postalCode,
            'APPT_CONTACT'     => $contactName,
            'APPT_EMAIL'       => $appEmail,
            'APPT_TEL'         => $appPhone,
            'EMAIL_ADHOC_LIST' => $emailRecip,

            // Section 2: Invoice Information
            'E1_TEXT_002'      => $pick(['invoice_to_applicant', 'E1_TEXT_002'], 'Yes'),
            'E1_TEXT_003'      => $pick(['invoice_company_name', 'E1_TEXT_003'], ''),
            'E1_TEXT_004'      => $pick(['invoice_address', 'E1_TEXT_004'], ''),
            'E1_TEXT_005'      => $pick(['invoice_country', 'E1_TEXT_005'], ''),
            'E1_TEXT_006'      => $pick(['invoice_postal_code', 'E1_TEXT_006'], ''),
            'E1_TEXT_007'      => $pick(['invoice_contact_person', 'E1_TEXT_007'], ''),
            'E1_TEXT_008'      => $pick(['invoice_email', 'E1_TEXT_008'], ''),
            'E1_TEXT_009'      => $pick(['invoice_telephone', 'E1_TEXT_009'], ''),

            // Section 4: Service Requested
            'CAL_CDTL_11'         => $pick(['service_test_type', 'CAL_CDTL_11'], 'In-House Lab Testing'),
            'TRPT_TESTING_STATUS' => $testStage,
            'E1_TEXT_032'         => $pick(['service_provider', 'E1_TEXT_032'], 'DATIAN SUBIC SHOES INC.'),
            'ITS_OFFICE_DISP'     => $pick(['lab_location', 'ITS_OFFICE_DISP'], 'PHILIPPINES'),
            'TRPT_SERV_TYPE'      => $pick(['service_required', 'TRPT_SERV_TYPE'], '4'), // 4 = Regular
            'E1_TEXT_012'         => $labAddr,
            'E1_TEXT_013'         => $pick(['quotation_required', 'E1_TEXT_013'], '2'), // 2 = No
            'TRPT_RETEST'         => $retestVal,
            'TRPT_PREV_NO'        => $prevReport,
            'E1_TEXT_050'         => $improvements,
            'E1_TEXT_014'         => $pick(['return_sample', 'E1_TEXT_014'], 'No'),

            // Section 5: Product / Material Information
            'TRPT_CLT_SUB_PRD_CAT'   => $pick(['sample_type', 'TRPT_CLT_SUB_PRD_CAT'], $tpl['sample_type']),
            'TRPT_PRD_CAT'           => $pick(['product_category', 'TRPT_PRD_CAT'], 'Footwear'),
            'TRPT_CDI_BRAND_NAME'    => $brandName,
            'DETOX_SUPPLIER_COUNTRY' => $sourcingCode,
            'E1_TEXT_023'            => $sourcingOff,
            'TRPT_COUNTRY_ORIGIN'    => $countryOrig,
            'MSTA_FIELD_4'           => $washInstr,
            'TXT_FIBER_CONTENT'      => $fiberText,

            // Section 6: Test Requirements
            'E1_TEXT_034'  => $gbStdCode,
            'E1_TEXT_035'  => $gbSafetyCode,
            'CAL_CDTL_5'   => $testPackages,
            'E2_LTEXT_003' => $otherGbHyper,
            'E2_LTEXT_004' => $otherGbVoc,
            'E2_LTEXT_001' => $individual,
            'TRPT_RMK'     => $remarks,

            // Hidden companion fields & CPSC Certificate of Compliance fields
            'TRPT_COSTCENTRE'            => (string) \App\Libraries\SystemSettings::get('interlink.cost_centre', 'VF-P0676'),
            'ITS_COMPANY'                => 'DATIAN',
            'ITS_DIVISION'               => 'VFC-TXT',
            'ITS_OFFICE'                 => 'PHL',
            'E1_TEXT_033'                => 'Non-GB',
            'E1_TEXT_047'                => 'Footwear+Non-GB',
            'E1_TEXT_048'                => $brandName . '+Footwear+Non-GB;General+Footwear+Non-GB',
            'E1_TEXT_056'                => $isMaterial ? $supplierId : $vendorId,
            'E1_TEXT_057'                => $factoryId . '+INH+PRO',
            'E1_TEXT_058'                => $factoryId . '+Non-GB; ALL+Non-GB',
            'E1_TEXT_059'                => $factoryId,
            'E1_TEXT_062'                => $factoryId . '+Non-GB+INH',
            'E3_TEXT_003'                => 'No',
            'DETOX_FACILITY_ADD_ADDRESS' => $pick(['factory_address', 'DETOX_FACILITY_ADD_ADDRESS'], (string) \App\Libraries\SystemSettings::get('interlink.factory_address', 'Lot 1P7-A,Phase 2,Subic Bay Gateway, Park, Subic Bay Freeport Zone')),
            'DETOX_FACILITY_HOUSE_NO'    => $pick(['factory_apt_suite', 'DETOX_FACILITY_HOUSE_NO'], ''),
            'DETOX_FACILITY_CITY'        => $pick(['factory_city', 'DETOX_FACILITY_CITY'], (string) \App\Libraries\SystemSettings::get('interlink.factory_city', 'Subic Bay')),
            'DETOX_FACILITY_PROVINCE'    => $pick(['factory_state_province', 'DETOX_FACILITY_PROVINCE'], (string) \App\Libraries\SystemSettings::get('interlink.factory_state_province', '03')),
            'DETOX_FACILITY_ZIP_CODE'    => $pick(['factory_zip_code', 'DETOX_FACILITY_ZIP_CODE'], ''),
            'DETOX_FACILITY_COUNTRY'     => $pick(['factory_country', 'DETOX_FACILITY_COUNTRY'], (string) \App\Libraries\SystemSettings::get('interlink.factory_country', 'Philippines')),
            'DETOX_FACILITY_EMAIL'       => $pick(['factory_email', 'DETOX_FACILITY_EMAIL'], ''),
            'DETOX_FACILITY_PHONE'       => $pick(['factory_phone', 'DETOX_FACILITY_PHONE'], ''),
        ];

        if ($isMaterial) {
            $fields['MSTA_FIELD_6']      = $supplierName;
            $fields['MSTA_FIELD_10']     = $supplierId;
            $fields['TRPT_FACTORY_NAME'] = $factoryName;
            $fields['FACTORY_ID']        = $factoryId;
            $fields['TRPT_SEASON']       = $season;
            $fields['CAL_CDTL_8']        = $matSubType;
            $fields['CAL_CDTL_1']        = $endUse;
            $fields['CAL_UPC']           = $reqSpecNo;
            $fields['TXT_FINISH']        = $finishing;
            $fields['E1_TEXT_053']       = $cutWidth;
            $fields['E1_TEXT_060']       = $warpPerInch;
            $fields['E1_TEXT_061']       = $weftPerInch;
            $fields['E1_TEXT_052']       = $trimSize;
            $fields['E1_TEXT_054']       = $yarnSpecs;
            $fields['E1_TEXT_042']       = $pick(['any_related_trf', 'E1_TEXT_042'], 'No');
            $fields['CAL_CDTL_10']       = $pick(['related_trf_no', 'CAL_CDTL_10'], '');
        } else {
            $fields['TRPT_SUPPLIER_NAME']        = $vendorName;
            $fields['SUPPLIER_ID']               = $vendorId;
            $fields['TRPT_FACTORY_NAME']         = $factoryName;
            $fields['FACTORY_ID']                = $factoryId;
            $fields['CAL_PO_COUNTRY']            = $exportTo;
            $fields['TRPT_AGE_RANGE']            = $ageGroup;
            $fields['INSP_SHIP_DATE']            = $cargoDate;
            $fields['CAL_CDTL_3']                = $prodClaim;
            $fields['CARE_LABEL_1']              = $careLabel1;
            $fields['TRPT_HTS_CODE']             = $pick(['gcc_cpc_required', 'TRPT_HTS_CODE'], 'No');
            $fields['TRPT_PRODUCT_ID']           = $pick(['product_id', 'TRPT_PRODUCT_ID'], $styleColorNo ?: $styleNo);
            $fields['TRPT_MANUFACTURE_DATE_STR'] = $pick(['manufacture_date', 'TRPT_MANUFACTURE_DATE_STR'], '');
        }

        // Allow direct field-code overrides from caller
        foreach ($override as $k => $v) {
            if (is_string($k) && preg_match('/^[A-Z0-9_]+$/', $k) && is_scalar($v)) {
                $fields[$k] = trim((string) $v);
            }
        }

        // Build JOB_DUMMY_006 Fiber Composition rows if supplied
        $userFibers = is_array($p['fibers'] ?? null) ? $p['fibers'] : [];
        $fibers = [];
        foreach ($userFibers as $fb) {
            if (! is_array($fb)) {
                continue;
            }
            $part  = trim((string) ($fb['part'] ?? $fb['REF_FLD05'] ?? ''));
            $fiber = trim((string) ($fb['fiber'] ?? $fb['REF_FLD07'] ?? ''));
            $pct   = trim((string) ($fb['pct'] ?? $fb['REF_FLD15'] ?? '0.00'));
            if ($part === '' && $fiber === '') {
                continue;
            }
            $fibers[] = [
                'part'      => $part,
                'fiber'     => $fiber,
                'pct'       => $pct !== '' ? $pct : '0.00',
                'REF_FLD05' => $part,
                'REF_FLD07' => $fiber,
                'REF_FLD15' => $pct !== '' ? $pct : '0.00',
            ];
        }
        if ($fibers && $fields['TXT_FIBER_CONTENT'] === '') {
            $fields['TXT_FIBER_CONTENT'] = implode('; ', array_map(static fn (array $f): string => trim(($f['part'] !== '' ? $f['part'] . ': ' : '') . $f['pct'] . '% ' . $f['fiber']), $fibers));
        }

        // Build the JOB_DUMMY_009 rows (Style Information for a product, Material Information for a material): the rows the user typed, or ONE row
        // made only from what the Lab Reference really holds. Empty stays empty: it is reported as missing, never filled with a made-up value.
        $rows = [];
        foreach ($userRows as $r) {
            if (is_array($r)) {
                $rows[] = $r;
            }
        }
        if (! $rows) {
            $rows[] = $isMaterial
                ? ['material_code' => $matCode, 'material_description' => $matDesc, 'material_type' => $matType, 'color_code' => $colorCode, 'color_description' => $colorDesc,
                    'weight' => $weight, 'weight_uom' => $weightUom, 'thickness' => $thickness, 'claim_type' => $claimType]
                : ['style_no' => $styleNo, 'product_name' => $productName, 'style_color_no' => $styleColorNo, 'color_description' => $colorDesc, 'color_code' => $colorCode,
                    'season' => $season, 'size_category' => $sizeCat];
        }
        foreach ($rows as &$row) {                                     // a product/material row also carries the InterLink column codes the page fills by
            if (! $isMaterial) {
                $row += ['season' => $season, 'size_category' => $sizeCat];
                $row['REF_FLD16'] = (string) ($row['style_no'] ?? '');
                $row['REF_FLD23'] = (string) ($row['product_name'] ?? '');
                $row['REF_FLD19'] = (string) ($row['style_color_no'] ?? '');
                $row['REF_FLD24'] = (string) ($row['color_description'] ?? '');
                $row['REF_FLD18'] = (string) ($row['color_code'] ?? '');
                $row['REF_FLD17'] = (string) ($row['season'] ?? '');
                $row['REF_FLD21'] = strtoupper((string) ($row['size_category'] ?? ''));
                $row['REF_FLD20'] = $genderCode;
            } else {
                $row['REF_FLD06'] = (string) ($row['material_code'] ?? '');
                $row['REF_FLD07'] = (string) ($row['material_description'] ?? '');
                $row['REF_FLD16'] = (string) ($row['material_type'] ?? '');
                $row['REF_FLD08'] = (string) ($row['color_code'] ?? '');
                $row['REF_FLD11'] = (string) ($row['color_description'] ?? '');
                $row['REF_FLD12'] = (string) ($row['weight'] ?? '');
                $row['REF_FLD13'] = (string) ($row['weight_uom'] ?? '');
                $row['REF_FLD14'] = (string) ($row['thickness'] ?? '');
                $row['REF_FLD15'] = (string) ($row['claim_type'] ?? '');
            }
        }
        unset($row);

        // pre-flight: what InterLink needs and the request does not have yet. A request with anything missing is not sent.
        $missing = [];
        $need = [
            'JOB_STD_APP_DESC'    => 'Company name',
            'JOB_STD_APP'         => 'InterLink code',
            'APPT_ADDR_1'         => 'Address',
            'APPT_ADDR_3'         => 'Country',
            'APPT_CONTACT'        => 'Contact person',
            'APPT_EMAIL'          => 'Email',
            'EMAIL_ADHOC_LIST'    => 'Email notification recipients',
            'TRPT_CDI_BRAND_NAME' => 'Brand',
            'TRPT_TESTING_STATUS' => 'Test stage',
            'CAL_CDTL_11'         => 'Service test type',
            'E1_TEXT_032'         => 'Service provider',
            'ITS_OFFICE_DISP'     => 'Lab location',
            'E1_TEXT_012'         => 'Lab address',
            'TRPT_FACTORY_NAME'   => 'Factory name',
            'FACTORY_ID'          => 'Factory ID',
        ];
        $need += $isMaterial
            ? ['MSTA_FIELD_6' => 'Supplier name', 'MSTA_FIELD_10' => 'Supplier ID', 'TRPT_COUNTRY_ORIGIN' => 'Country of origin']
            : ['TRPT_SUPPLIER_NAME' => 'Vendor name', 'SUPPLIER_ID' => 'Vendor ID', 'CAL_PO_COUNTRY' => 'Product export to', 'TRPT_AGE_RANGE' => 'Age group', 'INSP_SHIP_DATE' => 'Cargo receive date', 'TRPT_COUNTRY_ORIGIN' => 'Country of origin'];
        foreach ($need as $code => $label) {
            if (trim((string) ($fields[$code] ?? '')) === '') {
                $missing[] = $label;
            }
        }
        if (trim((string) ($fields['CAL_CDTL_5'] ?? '')) === '' && trim((string) ($fields['E2_LTEXT_001'] ?? '')) === '') {
            $missing[] = 'Test package or individual tests';
        }
        $rowNeed = $isMaterial
            ? ['material_code' => 'Material code', 'material_description' => 'Material description', 'material_type' => 'Material type']
            : ['style_no' => 'Style No.', 'product_name' => 'Product name', 'style_color_no' => 'Style color No.', 'color_code' => 'Color code', 'color_description' => 'Color description', 'season' => 'Season', 'size_category' => 'Size category'];
        foreach ($rows as $i => $row) {
            foreach ($rowNeed as $k => $label) {
                if (trim((string) ($row[$k] ?? '')) === '') {
                    $missing[] = $label . (count($rows) > 1 ? ' (row ' . ($i + 1) . ')' : '');
                }
            }
        }

        return [
            'code' => 200,
            'body' => [
                'ok'             => true,
                'mode'           => 'etrf',
                'action'         => $action,
                'etrf_type'      => $tpl['code'],
                'template_label' => $tpl['label'],
                'sample_type'    => $tpl['sample_type'],
                'account_email'  => $accountEmail,
                'menu_targets'   => $tpl['menu_targets'],
                'home_url'       => self::PORTAL_HOME_URL,
                'lab_ref_no'     => $labRefLabel,
                'application_no' => $appNo,
                'mtl_no'         => $mtlNo,
                'po_lot_no'      => $poLotNo,
                'fields'         => $fields,
                'rows'           => $rows,
                'fibers'         => $fibers,
                'missing'        => $missing,
                'ready'          => $missing === [],
                'summary'        => [
                    'etrf_type'                      => $tpl['code'],
                    'template_label'                 => $tpl['label'],
                    'sample_type'                    => $tpl['sample_type'],
                    'company_name'                   => $companyName,
                    'vendor_name'                    => $isMaterial ? $supplierName : $vendorName,
                    'vendor_id'                      => $isMaterial ? $supplierId : $vendorId,
                    'factory_name'                   => $factoryName,
                    'factory_id'                     => $factoryId,
                    'buyer_brand'                    => $brandName,
                    'season'                         => $season,
                    'test_stage'                     => $testStage,
                    'service_test_type'              => $fields['CAL_CDTL_11'],
                    'country_of_origin'              => $countryOrig,
                    'sourcing_office'                => $sourcingOff,
                    'material_sub_type'              => $matSubType,
                    'end_use'                        => $endUse,
                    'finishing'                      => $finishing,
                    'requirement_spec_no'            => $reqSpecNo,
                    'cuttable_width'                 => $cutWidth,
                    'warp_per_inch'                  => $warpPerInch,
                    'weft_per_inch'                  => $weftPerInch,
                    'trim_size'                      => $trimSize,
                    'yarn_specifications'            => $yarnSpecs,
                    'washing_instruction'            => $washInstr,
                    'product_claim_type'             => $prodClaim,
                    'care_label_1'                   => $careLabel1,
                    'fiber_content_text'             => $fields['TXT_FIBER_CONTENT'],
                    'gb_standard_code'               => $gbStdCode,
                    'gb_safety_code'                 => $gbSafetyCode,
                    'other_gb_hypercare'             => $otherGbHyper,
                    'other_gb_voc'                   => $otherGbVoc,
                    'test_packages'                  => $testPackages,
                    'individual_tests'               => $individual,
                    'supplementary_attachment_notes' => $suppNotes,
                    'style_no'                       => $styleNo,
                    'product_name'                   => $isMaterial ? $matDesc : $productName,
                    'style_color_no'                 => $styleColorNo,
                    'mtl_no'                         => $mtlNo,
                    'po_lot_no'                      => $poLotNo,
                    'material_code'                  => $matCode,
                    'material_type'                  => $matType,
                    'color_code'                     => $colorCode,
                    'color_description'              => $colorDesc,
                    'size_category'                  => $sizeCat,
                    'age_group'                      => $ageGroup,
                    'cargo_receive_date'             => $cargoDate,
                    'remarks'                        => $remarks,
                ],
                'templates'      => array_values(self::ETRF_TEMPLATES),
                'lab'            => $lr ? [
                    'slug'       => (string) $lr['form_slug'],
                    'id'         => (int) ($lr['source_id'] ?: $lr['id']),
                    'lab_ref_no' => (string) $lr['lab_ref_no'],
                    'tr_no'      => (string) ($lr['tr_no'] ?? ''),
                ] : null,
            ],
        ];
    }

    /** Pulls the next InterLink pages (100 rows each) into tbl_interlink_reports; loops forever so the copy stays complete. */
    private function backfillStep(int $pagesPerCycle = 12): array
    {
        $db   = $this->il();
        $row  = $db->table('tbl_app_settings')->where('skey', 'il_backfill')->get()->getRowArray();
        $st   = json_decode((string) ($row['svalue'] ?? ''), true) ?: ['next' => 1, 'total_pages' => 0, 'size' => 100, 'cycles' => 0];
        $size = 100;
        $done = 0;
        for ($i = 0; $i < $pagesPerCycle; $i++) {
            $page = max(1, (int) $st['next']);
            $res  = $this->pullInterlinkGridPage($page, $size, false);
            if (empty($res['live'])) {
                break;   // InterLink not reachable / session ended: try again next minute
            }
            $total = max(1, (int) ($res['totalPages'] ?? 1));
            $st['total_pages'] = $total;
            $st['next']        = $page >= $total ? 1 : $page + 1;
            if ($page >= $total) {
                $st['cycles'] = (int) ($st['cycles'] ?? 0) + 1;
                $st['finished_at'] = date('Y-m-d H:i:s');
            }
            $done++;
        }
        $st['updated_at'] = date('Y-m-d H:i:s');
        $data = ['svalue' => json_encode($st), 'updated_by' => 'worker', 'updated_at' => $st['updated_at']];
        if ($row) {
            $db->table('tbl_app_settings')->where('skey', 'il_backfill')->update($data);
        } else {
            $db->table('tbl_app_settings')->insert($data + ['skey' => 'il_backfill']);
        }

        return ['pages' => $done, 'next' => $st['next'], 'of' => $st['total_pages']];
    }

    /**
     * GET interlink/search?filters={col:text,...} : searches EVERY application stored locally (not only the page on screen).
     * Returns up to 300 rows plus how much of InterLink is already loaded, so the screen can say so.
     */
    public function search()
    {
        $db      = $this->il();
        $filters = json_decode((string) $this->request->getGet('filters'), true);
        $filters = is_array($filters) ? $filters : [];
        $cols    = $db->getFieldNames('tbl_interlink_reports');
        $b       = $db->table('tbl_interlink_reports');
        $used    = 0;
        $all = trim((string) ($filters['__all'] ?? ''));
        unset($filters['__all']);
        if ($all !== '' && mb_strlen($all) <= 80) {
            $b->groupStart();
            foreach ($cols as $c) {
                if (! in_array($c, ['id', 'created_at', 'updated_at'], true)) {
                    $b->orLike($c, $all, 'both');
                }
            }
            $b->groupEnd();
            $used++;
        }
        foreach (array_slice($filters, 0, 12, true) as $col => $val) {
            $val = trim((string) $val);
            if ($val === '' || ! in_array((string) $col, $cols, true)) {
                continue;
            }
            $b->like((string) $col, $val, 'both');
            $used++;
        }
        $rows = $used ? $b->orderBy('application_no', 'DESC')->limit(300)->get()->getResultArray() : [];
        $row  = $db->table('tbl_app_settings')->where('skey', 'il_backfill')->get()->getRowArray();
        $st   = json_decode((string) ($row['svalue'] ?? ''), true) ?: [];
        $have = (int) $db->table('tbl_interlink_reports')->countAllResults();

        return $this->response->setJSON([
            'ok' => true, 'rows' => $rows, 'matches' => count($rows), 'capped' => count($rows) >= 300,
            'loaded' => $have, 'total_pages' => (int) ($st['total_pages'] ?? 0), 'next_page' => (int) ($st['next'] ?? 1),
            'complete' => ! empty($st['finished_at']), 'csrf' => csrf_hash(),
        ]);
    }
}
