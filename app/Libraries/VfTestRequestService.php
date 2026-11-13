<?php

namespace App\Libraries;

/**
 * End-to-end VF Test Request workflow service:
 *   1. Parses Outlook emails (djc_lab@dtshoes.com test request table, transit container updates,
 *      SGS/KOTITI Lab Received Expect Due Date + Report No., and final PASS Certificate PDFs)
 *   2. Bidirectional lookup against dberp.production_orders (MTL# <-> Prod Code)
 *   3. Pre-fills Copy-as-New InterLink eTRF form payloads & snapshots submitted production orders
 *   4. Queues grouped PDF reply emails via MailOutbox matching exact naming:
 *      "{Application No} - {MTL#} - {Test TYPE}.pdf"
 */
class VfTestRequestService
{
    public const PACKAGES = [
        'KC Test Package'               => ['short' => 'KC Test',    'aliases' => ['kc package', 'kc test package', 'kc test', 'kotiti']],
        'GB - Full Package'             => ['short' => 'GB Test',    'aliases' => ['gb - full package', 'gb full package', 'gb test']],
        'CPSIA Product Testing Package' => ['short' => 'CPSIA test', 'aliases' => ['cpsia product testing package', 'cpsia test', 'cpsia']],
        'RSL'                           => ['short' => 'RSL',        'aliases' => ['rsl', 'rsl package', 'rsl targeted']],
    ];

    private static bool $schemaReady = false;

    /**
     * The entries of the Test Package search dropdown on VF - Test Request (Settings > VF): shown name => words typed into InterLink's
     * Test Package(s) filter. Written one per line: "Name" or "Name | filter words". Empty / unreadable = the 4 built-in packages.
     *
     * @return array<string,string>
     */
    public static function searchItems(): array
    {
        $raw   = (string) SystemSettings::get('vf.search_items', '');
        $items = [];
        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            [$label, $filter] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $label = mb_substr($label, 0, 80);
            if ($label !== '' && ! isset($items[$label])) {
                $items[$label] = mb_substr($filter !== '' ? $filter : $label, 0, 60);
            }
        }
        if (! $items) {
            foreach (array_keys(self::PACKAGES) as $p) {
                $items[$p] = $p;
            }
        }

        return $items;
    }

    private function db()
    {
        return \Config\Database::connect();
    }

    public function ensureTables(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $db = $this->db();

        $db->query('CREATE TABLE IF NOT EXISTS tbl_vf_email_batches (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            outlook_record_uid VARCHAR(100) NULL,
            internet_message_id VARCHAR(255) NOT NULL DEFAULT "",
            source_channel VARCHAR(30) NOT NULL DEFAULT "outlook_sync",
            sender_name VARCHAR(191) NOT NULL DEFAULT "",
            sender_email VARCHAR(191) NOT NULL DEFAULT "",
            to_list TEXT NULL,
            cc_list TEXT NULL,
            subject VARCHAR(500) NOT NULL DEFAULT "",
            test_package VARCHAR(120) NOT NULL DEFAULT "CPSIA Product Testing Package",
            test_type_short VARCHAR(50) NOT NULL DEFAULT "CPSIA test",
            item_count INT UNSIGNED NOT NULL DEFAULT 0,
            submitted_count INT UNSIGNED NOT NULL DEFAULT 0,
            auto_send_email TINYINT(1) NOT NULL DEFAULT 1,
            package_needs_review TINYINT(1) NOT NULL DEFAULT 0,
            email_sent TINYINT(1) NOT NULL DEFAULT 0,
            outbox_id BIGINT NULL,
            email_sent_at DATETIME NULL,
            received_at DATETIME NULL,
            raw_html MEDIUMTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_outlook_uid (outlook_record_uid),
            KEY ix_pkg (test_package),
            KEY ix_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $db->query('CREATE TABLE IF NOT EXISTS tbl_vf_test_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_id BIGINT UNSIGNED NULL,
            production_order_id INT UNSIGNED NULL,
            erp_matched TINYINT(1) NOT NULL DEFAULT 0,
            erp_ambiguous TINYINT(1) NOT NULL DEFAULT 0,
            factory VARCHAR(50) NOT NULL DEFAULT "DTP",
            prod_code VARCHAR(80) NOT NULL DEFAULT "",
            po_no VARCHAR(80) NOT NULL DEFAULT "",
            style_no VARCHAR(150) NOT NULL DEFAULT "",
            mtl_no VARCHAR(100) NOT NULL DEFAULT "",
            colorway VARCHAR(255) NOT NULL DEFAULT "",
            colorway_c VARCHAR(255) NOT NULL DEFAULT "",
            season VARCHAR(30) NOT NULL DEFAULT "",
            size_no VARCHAR(120) NOT NULL DEFAULT "",
            country VARCHAR(100) NOT NULL DEFAULT "",
            buyer VARCHAR(150) NOT NULL DEFAULT "",
            crd DATE NULL,
            email_state VARCHAR(60) NOT NULL DEFAULT "Need to test",
            tr_no VARCHAR(80) NOT NULL DEFAULT "",
            test_package VARCHAR(120) NOT NULL DEFAULT "CPSIA Product Testing Package",
            test_type_short VARCHAR(50) NOT NULL DEFAULT "CPSIA test",
            source_application_no VARCHAR(60) NOT NULL DEFAULT "",
            generated_application_no VARCHAR(60) NOT NULL DEFAULT "",
            interlink_job_uid VARCHAR(60) NOT NULL DEFAULT "",
            interlink_status VARCHAR(40) NOT NULL DEFAULT "Pending",
            interlink_message VARCHAR(500) NOT NULL DEFAULT "",
            etrf_pdf_name VARCHAR(255) NOT NULL DEFAULT "",
            etrf_pdf_path VARCHAR(500) NOT NULL DEFAULT "",
            pdf_capture_warning VARCHAR(255) NOT NULL DEFAULT "",
            etrf_emailed_at DATETIME NULL,
            container_no VARCHAR(100) NOT NULL DEFAULT "",
            transit_status VARCHAR(60) NOT NULL DEFAULT "",
            transit_updated_at DATETIME NULL,
            lab_name VARCHAR(120) NOT NULL DEFAULT "",
            lab_report_no VARCHAR(120) NOT NULL DEFAULT "",
            expected_due_date DATE NULL,
            lab_received_at DATETIME NULL,
            test_result VARCHAR(40) NOT NULL DEFAULT "",
            issue_date DATE NULL,
            validity_date DATE NULL,
            fail_reason TEXT NULL,
            retest_app_no VARCHAR(80) NOT NULL DEFAULT "",
            retest_report_no VARCHAR(120) NOT NULL DEFAULT "",
            retest_result VARCHAR(40) NOT NULL DEFAULT "",
            certificate_pdf_name VARCHAR(255) NOT NULL DEFAULT "",
            certificate_pdf_path VARCHAR(500) NOT NULL DEFAULT "",
            stage VARCHAR(40) NOT NULL DEFAULT "REQUESTED",
            submitted_by_id INT UNSIGNED NOT NULL DEFAULT 0,
            submitted_by_name VARCHAR(120) NOT NULL DEFAULT "",
            submitted_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY ix_batch (batch_id),
            KEY ix_mtl (mtl_no),
            KEY ix_prod_code (prod_code),
            KEY ix_gen_app (generated_application_no),
            KEY ix_lab_rep (lab_report_no),
            KEY ix_stage (stage),
            KEY ix_pkg (test_package)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $db->query('CREATE TABLE IF NOT EXISTS tbl_vf_production_snapshots (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id BIGINT UNSIGNED NULL,
            generated_application_no VARCHAR(60) NOT NULL DEFAULT "",
            test_package VARCHAR(120) NOT NULL DEFAULT "",
            erp_order_id INT UNSIGNED NULL,
            comp_no VARCHAR(50) NULL,
            company VARCHAR(100) NULL,
            prod_code VARCHAR(50) NOT NULL DEFAULT "",
            brand_no VARCHAR(50) NULL,
            brand VARCHAR(100) NULL,
            buyer VARCHAR(150) NULL,
            country_c VARCHAR(100) NULL,
            country VARCHAR(100) NULL,
            po_no VARCHAR(100) NULL,
            so_no VARCHAR(100) NULL,
            style_no VARCHAR(150) NULL,
            mtl_no VARCHAR(100) NULL,
            colorway_c TEXT NULL,
            colorway TEXT NULL,
            cutting_die_no VARCHAR(100) NULL,
            last_no VARCHAR(50) NULL,
            outsole_no VARCHAR(50) NULL,
            acc_date DATE NULL,
            crd DATE NULL,
            pairs INT NULL DEFAULT 0,
            cartons INT NULL DEFAULT 0,
            specific_finish VARCHAR(255) NULL,
            rubber_desc VARCHAR(255) NULL,
            size_no VARCHAR(100) NULL,
            size_name_c VARCHAR(255) NULL,
            size_name_e VARCHAR(255) NULL,
            batch_no VARCHAR(255) NULL,
            produce_batch VARCHAR(100) NULL,
            packing_batch VARCHAR(100) NULL,
            remark TEXT NULL,
            image_file VARCHAR(255) NULL,
            image_path VARCHAR(255) NULL,
            picture_name VARCHAR(255) NULL,
            creator VARCHAR(50) NULL,
            created_date DATETIME NULL,
            modifier VARCHAR(50) NULL,
            modified_date DATETIME NULL,
            erp_status VARCHAR(50) NULL,
            snapshot_at DATETIME NOT NULL,
            KEY ix_snap_app (generated_application_no),
            KEY ix_snap_req (request_id),
            KEY ix_snap_prod (prod_code),
            KEY ix_snap_mtl (mtl_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $db->query('CREATE TABLE IF NOT EXISTS tbl_vf_test_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id BIGINT UNSIGNED NULL,
            batch_id BIGINT UNSIGNED NULL,
            event_type VARCHAR(50) NOT NULL,
            outlook_record_uid VARCHAR(100) NOT NULL DEFAULT "",
            subject VARCHAR(500) NOT NULL DEFAULT "",
            details_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            KEY ix_ev_uid (outlook_record_uid),
            KEY ix_ev_req (request_id),
            KEY ix_ev_batch (batch_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // E-mail sources: WHICH senders' mail is read as test requests, and HOW its table is read (nothing is fixed in code)
        $db->query('CREATE TABLE IF NOT EXISTS tbl_vf_email_sources (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            sender_patterns TEXT NULL,
            subject_keywords TEXT NULL,
            default_package VARCHAR(120) NOT NULL DEFAULT "",
            column_map_json MEDIUMTEXT NULL,
            skip_state_regex VARCHAR(255) NOT NULL DEFAULT "",
            priority INT NOT NULL DEFAULT 100,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        if ((int) $db->table('tbl_vf_email_sources')->countAllResults() === 0) {
            $nowSeed = date('Y-m-d H:i:s');
            $db->table('tbl_vf_email_sources')->insert([
                'name' => 'DJC Lab test requests', 'sender_patterns' => 'djc_lab@dtshoes.com', 'subject_keywords' => '', 'default_package' => '',
                'column_map_json' => '{}', 'skip_state_regex' => '', 'priority' => 100, 'enabled' => 1, 'created_at' => $nowSeed, 'updated_at' => $nowSeed,
            ]);
        }

        // Idempotent column migrations for tables already created prior to hardening
        $alters = [
            ['tbl_vf_email_batches', 'source_id', 'ADD COLUMN source_id INT UNSIGNED NULL'],
            ['tbl_vf_email_batches', 'notify_user_id', 'ADD COLUMN notify_user_id INT UNSIGNED NULL'],
            ['tbl_vf_test_requests', 'etrf_outbox_id', 'ADD COLUMN etrf_outbox_id BIGINT NULL'],
            ['tbl_vf_test_requests', 'source_id', 'ADD COLUMN source_id INT UNSIGNED NULL'],
            ['tbl_vf_test_requests', 'extra_json', 'ADD COLUMN extra_json MEDIUMTEXT NULL'],
            ['tbl_vf_email_batches', 'package_needs_review', 'ADD COLUMN package_needs_review TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_send_email'],
            ['tbl_vf_test_requests', 'erp_matched', 'ADD COLUMN erp_matched TINYINT(1) NOT NULL DEFAULT 0 AFTER production_order_id'],
            ['tbl_vf_test_requests', 'erp_ambiguous', 'ADD COLUMN erp_ambiguous TINYINT(1) NOT NULL DEFAULT 0 AFTER erp_matched'],
            ['tbl_vf_test_requests', 'pdf_capture_warning', 'ADD COLUMN pdf_capture_warning VARCHAR(255) NOT NULL DEFAULT "" AFTER etrf_pdf_path'],
            ['tbl_vf_test_requests', 'etrf_emailed_at', 'ADD COLUMN etrf_emailed_at DATETIME NULL AFTER pdf_capture_warning'],
            ['tbl_vf_test_requests', 'lab_order_no', 'ADD COLUMN lab_order_no VARCHAR(120) NOT NULL DEFAULT "" AFTER lab_report_no'],
            ['tbl_vf_test_requests', 'sample_send_date', 'ADD COLUMN sample_send_date DATE NULL AFTER lab_order_no'],
            ['tbl_vf_test_requests', 'notification_date', 'ADD COLUMN notification_date DATE NULL AFTER sample_send_date'],
        ];
        foreach ($alters as [$tbl, $col, $ddl]) {
            $exists = $db->query("SHOW COLUMNS FROM `{$tbl}` LIKE ?", [$col])->getRowArray();
            if (! $exists) {
                $db->query("ALTER TABLE `{$tbl}` {$ddl}");
            }
        }

        self::$schemaReady = true;
    }

    /** Matches an explicit VF Test Package name using ASCII/non-alphanumeric boundaries (safe for CJK e.g. "CPSIA测试"). */
    public static function detectExplicitPackage(?string $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $low = strtolower($s);
        foreach (self::PACKAGES as $canon => $_) {
            if (strtolower($canon) === $low) {
                return $canon;
            }
        }
        // Check exact multi-word / explicit package strings first, then ASCII-bounded short codes
        if (preg_match('/(?<![A-Z0-9])KC(?:[\s_-]*(?:TEST|PACKAGE))?(?![A-Z0-9])|(?<![A-Z0-9])KOTITI(?![A-Z0-9])/iu', $s)) {
            return 'KC Test Package';
        }
        if (preg_match('/(?<![A-Z0-9])GB(?:[\s_-]*(?:FULL|TEST|PACKAGE))?(?![A-Z0-9])/iu', $s)) {
            return 'GB - Full Package';
        }
        if (preg_match('/(?<![A-Z0-9])CPSIA(?![A-Z0-9])/iu', $s)) {
            return 'CPSIA Product Testing Package';
        }
        if (preg_match('/(?<![A-Z0-9])RSL(?![A-Z0-9])/iu', $s)) {
            return 'RSL';
        }

        return null;
    }

    public static function normalizePackage(?string $raw): string
    {
        return self::detectExplicitPackage($raw) ?? 'CPSIA Product Testing Package';
    }

    /** Detects package from email Subject first, then unquoted top intro before <table>; never from quoted history/signatures. */
    public static function detectPackageFromEmail(string $subject, string $htmlOrText): array
    {
        $pkg = self::detectExplicitPackage($subject);
        if ($pkg !== null) {
            return ['package' => $pkg, 'needs_review' => false];
        }
        // Extract only top text above first <table> or Outlook reply separator
        $top = preg_split('/<(?:table|blockquote|hr)\b|id=["\']divRplyFwdMsg|-----Original\s+Message-----|^\s*From:\s+/im', $htmlOrText, 2)[0] ?? '';
        $intro = trim(strip_tags($top));
        if ($intro !== '') {
            $pkg = self::detectExplicitPackage(mb_substr($intro, 0, 500));
            if ($pkg !== null) {
                return ['package' => $pkg, 'needs_review' => false];
            }
        }

        return ['package' => 'CPSIA Product Testing Package', 'needs_review' => true];
    }

    /** Strips quoted reply/forward sections so Stage A/B parsers only inspect the latest message body. */
    private static function stripQuotedEmailHtml(string $html): string
    {
        $parts = preg_split('/<div[^>]+id=["\']?divRplyFwdMsg["\']?|<blockquote\b|<div[^>]+style=["\'][^"\']*border-top:\s*solid\s+#[A-F0-9]{6}/i', $html, 2);

        return (string) ($parts[0] ?? $html);
    }

    private static function stripQuotedEmailText(string $text): string
    {
        $parts = preg_split('/^\s*-----Original\s+Message-----|^\s*From:\s+.+?\r?\n\s*Sent:/ims', $text, 2);

        return (string) ($parts[0] ?? $text);
    }

    public static function shortTypeForPackage(string $pkg): string
    {
        $canon = self::normalizePackage($pkg);

        return self::PACKAGES[$canon]['short'] ?? 'CPSIA test';
    }

    /** Query dberp.production_orders bidirectionally by MTL#, Prod Code, or free search. */
    public function lookupErp(string $mtl = '', string $prodCode = '', string $q = ''): array
    {
        $db   = $this->db();
        $mtl  = strtoupper(trim($mtl));
        $code = strtoupper(trim($prodCode));
        $q    = strtoupper(trim($q));

        $mtls = [];
        $prodCodes = [];
        $ordersByMtl = [];
        $ordersByProd = [];
        $exactProdMatch = false;

        if ($mtl !== '') {
            $ordersByMtl = $db->query(
                'SELECT id, company, prod_code, po_no, so_no, style_no, mtl_no, colorway, colorway_c, size_no, crd, country, buyer, pairs, status
                 FROM dberp.production_orders WHERE mtl_no = ? ORDER BY id DESC LIMIT 100',
                [$mtl]
            )->getResultArray();
            if (! $ordersByMtl) {
                $ordersByMtl = $db->query(
                    'SELECT id, company, prod_code, po_no, so_no, style_no, mtl_no, colorway, colorway_c, size_no, crd, country, buyer, pairs, status
                     FROM dberp.production_orders WHERE mtl_no LIKE ? ORDER BY id DESC LIMIT 60',
                    [$mtl . '%']
                )->getResultArray();
            }
            foreach ($ordersByMtl as $o) {
                if (! empty($o['prod_code'])) {
                    $prodCodes[$o['prod_code']] = $o;
                }
            }
        }

        if ($code !== '') {
            $ordersByProd = $db->query(
                'SELECT id, company, prod_code, po_no, so_no, style_no, mtl_no, colorway, colorway_c, size_no, crd, country, buyer, pairs, status
                 FROM dberp.production_orders WHERE prod_code = ? ORDER BY id DESC LIMIT 20',
                [$code]
            )->getResultArray();
            $exactProdMatch = count($ordersByProd) > 0;
            if (! $ordersByProd) {
                $ordersByProd = $db->query(
                    'SELECT id, company, prod_code, po_no, so_no, style_no, mtl_no, colorway, colorway_c, size_no, crd, country, buyer, pairs, status
                     FROM dberp.production_orders WHERE prod_code LIKE ? ORDER BY id DESC LIMIT 40',
                    [$code . '%']
                )->getResultArray();
            }
            foreach ($ordersByProd as $o) {
                if (! empty($o['mtl_no'])) {
                    $mtls[$o['mtl_no']] = ($mtls[$o['mtl_no']] ?? 0) + 1;
                }
            }
        }

        if ($q !== '' || ($mtl === '' && $code === '')) {
            $like = ($q !== '' ? $q : 'VN') . '%';
            $mRows = $db->query(
                'SELECT mtl_no, MAX(style_no) AS style_no, MAX(colorway) AS colorway, COUNT(*) AS cnt
                 FROM dberp.production_orders
                 WHERE mtl_no IS NOT NULL AND mtl_no != "" AND (mtl_no LIKE ? OR prod_code LIKE ? OR style_no LIKE ?)
                 GROUP BY mtl_no ORDER BY MAX(id) DESC LIMIT 40',
                [$like, $like, '%' . ($q ?: '') . '%']
            )->getResultArray();
            $pRows = $db->query(
                'SELECT prod_code, mtl_no, po_no, style_no, colorway, crd
                 FROM dberp.production_orders
                 WHERE prod_code LIKE ? OR mtl_no LIKE ?
                 ORDER BY id DESC LIMIT 40',
                [$like, $like]
            )->getResultArray();

            return [
                'ok'            => true,
                'mtl_options'   => $mRows,
                'prod_options'  => $pRows,
                'orders_by_mtl' => $ordersByMtl,
                'orders_by_prod'=> $ordersByProd,
            ];
        }

        return [
            'ok'                   => true,
            'mtl'                  => $mtl,
            'prod_code'            => $code,
            'exact_prod_match'     => $exactProdMatch,
            'orders_by_mtl'        => $ordersByMtl,
            'orders_by_prod'       => $ordersByProd,
            'prod_options_for_mtl' => array_values($prodCodes),
            'mtl_options_for_prod' => array_keys($mtls),
            'multiple_prod_codes'  => count($prodCodes) > 1,
        ];
    }

    /** Clean Product Name from ERP style_no (e.g. "TD SLIP-ON V" -> "Slip-On V" or keep clean title case). */
    public static function cleanProductName(string $styleNo): string
    {
        $s = trim($styleNo);
        if ($s === '') {
            return '';
        }
        $s = preg_replace('/^(TD|UY|JN|UA|IN|YT|MN|WN)\s+/i', '', $s) ?? $s;
        $words = preg_split('/\s+/', strtolower($s)) ?: [];
        $out = [];
        foreach ($words as $w) {
            if ($w === 'v' || $w === 'ls') {
                $out[] = strtoupper($w);
            } elseif (str_contains($w, '-')) {
                $out[] = implode('-', array_map(static fn ($p) => strlen($p) <= 2 ? strtoupper($p) : ucfirst($p), explode('-', $w)));
            } else {
                $out[] = ucfirst($w);
            }
        }

        return implode(' ', $out);
    }

    /** Build pre-filled Copy-as-New payload combining source Application No. record + dberp.production_orders row. */
    public function prepareCopyForm(array $p): array
    {
        $this->ensureTables();
        $db        = $this->db();
        $srcAppNo  = strtoupper(trim((string) ($p['source_application_no'] ?? '')));
        $mtl       = strtoupper(trim((string) ($p['mtl_no'] ?? '')));
        $prodCode  = strtoupper(trim((string) ($p['prod_code'] ?? '')));
        $requestId = (int) ($p['request_id'] ?? 0);
        $pkgRaw    = trim((string) ($p['test_package'] ?? ''));

        $srcReport = $srcAppNo !== ''
            ? $db->table('tbl_interlink_reports')->where('application_no', $srcAppNo)->orderBy('id', 'DESC')->get(1)->getRowArray()
            : null;
        if ($pkgRaw === '' && $srcReport && ! empty($srcReport['test_packages'])) {
            $pkgRaw = (string) $srcReport['test_packages'];
        }

        $reqRow = $requestId > 0
            ? $db->table('tbl_vf_test_requests')->where('id', $requestId)->get(1)->getRowArray()
            : null;
        if ($reqRow) {
            if ($mtl === '' && ! empty($reqRow['mtl_no'])) {
                $mtl = strtoupper(trim((string) $reqRow['mtl_no']));
            }
            if ($prodCode === '' && ! empty($reqRow['prod_code'])) {
                $prodCode = strtoupper(trim((string) $reqRow['prod_code']));
            }
            if ($pkgRaw === '' && ! empty($reqRow['test_package'])) {
                $pkgRaw = (string) $reqRow['test_package'];
            }
        }

        $erpOrder     = null;
        $erpMatched   = false;
        $erpAmbiguous = false;
        $mtlCount     = 0;
        if ($prodCode !== '') {
            $erpOrder   = $db->query('SELECT * FROM dberp.production_orders WHERE prod_code = ? LIMIT 1', [$prodCode])->getRowArray();
            $erpMatched = ! empty($erpOrder);
        }
        if (! $erpOrder && $mtl !== '') {
            $mtlRows  = $db->query('SELECT * FROM dberp.production_orders WHERE mtl_no = ? ORDER BY id DESC LIMIT 20', [$mtl])->getResultArray();
            $mtlCount = count($mtlRows);
            if ($mtlCount === 1 && $prodCode === '') {
                $erpOrder   = $mtlRows[0];
                $erpMatched = true;
            } elseif ($mtlCount > 1) {
                // Never guess one PO/CRD silently when 1 MTL# spans multiple Prod Codes
                $erpAmbiguous = true;
            }
        }
        if ($erpOrder && $mtl === '' && ! empty($erpOrder['mtl_no'])) {
            $mtl = strtoupper(trim((string) $erpOrder['mtl_no']));
        }
        if ($erpOrder && $prodCode === '' && ! empty($erpOrder['prod_code'])) {
            $prodCode = strtoupper(trim((string) $erpOrder['prod_code']));
        }

        $testPkg = self::normalizePackage($pkgRaw);
        $styleNo = preg_match('/^([A-Z0-9]{8})[A-Z0-9]{3}$/', $mtl, $mm) ? $mm[1] : $mtl;
        $colorCode = preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})$/', $mtl, $cm) ? $cm[1] : '';

        $rawStyle  = (string) (! empty($erpOrder['style_no']) ? $erpOrder['style_no'] : (! empty($reqRow['style_no']) ? $reqRow['style_no'] : ($srcReport['product_name'] ?? '')));
        $prodName  = self::cleanProductName($rawStyle) ?: $rawStyle;
        $colorDesc = (string) (! empty($erpOrder['colorway']) ? $erpOrder['colorway'] : (! empty($reqRow['colorway']) ? $reqRow['colorway'] : ($srcReport['colorway_name'] ?? '')));
        $erpCrd    = trim((string) ($erpOrder['crd'] ?? ''));
        $emailCrd  = trim((string) ($reqRow['crd'] ?? ''));
        $crdRaw    = $erpCrd !== '' ? $erpCrd : $emailCrd;
        $crdTs     = $crdRaw !== '' ? strtotime($crdRaw) : false;
        $cargoDate = $crdTs ? date('d M Y', $crdTs) : (string) ($srcReport['cargo_receive_date'] ?? date('d M Y', strtotime('+' . max(1, (int) SystemSettings::get('vf.cargo_days', '30')) . ' days')));
        $crdDiffers = ($erpCrd !== '' && $emailCrd !== '' && $erpCrd !== $emailCrd);

        $season = (string) ($reqRow['season'] ?? $srcReport['season'] ?? '');
        if ($season === '' && preg_match('/^VSP(\d{2})/i', $prodCode, $sm)) {
            $season = 'S' . ((int) $sm[1] + 1);
        }
        if ($season === '') {
            $season = (string) (SystemSettings::get('interlink.season', 'S27') ?: 'S27');
        }

        $sizeCat = strtoupper((string) ($srcReport['size_category'] ?? 'UNISEX'));
        if (! empty($erpOrder['size_no'])) {
            $szU = strtoupper((string) $erpOrder['size_no']);
            if (str_contains($szU, 'UNISEX')) {
                $sizeCat = 'UNISEX';
            } elseif (str_contains($szU, 'WOMEN')) {
                $sizeCat = 'WOMENS';
            } elseif (str_contains($szU, 'MEN')) {
                $sizeCat = 'MENS';
            }
        }

        $ageGroup = (string) ($srcReport['age_group'] ?? SystemSettings::get('vf.default_age_group', 'Children (3-12 years)'));
        if (preg_match('/^(TD|IN)\b/i', $rawStyle)) {
            $ageGroup = 'Infants (0-36 months)';
        } elseif (preg_match('/^(UY|JN|YT)\b/i', $rawStyle)) {
            $ageGroup = 'Children (3-12 years)';
        } elseif (preg_match('/^(UA|MN|WN)\b/i', $rawStyle)) {
            $ageGroup = 'Adult';
        }

        return [
            'ok' => true,
            'form' => [
                'request_id'             => $requestId,
                'source_application_no'  => $srcAppNo,
                'test_package'           => $testPkg,
                'test_type_short'        => self::shortTypeForPackage($testPkg),
                'erp_order_id'           => (int) ($erpOrder['id'] ?? 0),
                'erp_matched'            => $erpMatched,
                'erp_ambiguous'          => $erpAmbiguous,
                'erp_prod_choices_count' => $mtlCount,
                'mtl_no'                 => $mtl,
                'prod_code'              => $prodCode,
                'po_no'                  => (string) ($erpOrder['po_no'] ?? $reqRow['po_no'] ?? ''),
                'style_no'               => $styleNo,
                'product_name'           => $prodName,
                'style_color_no'         => $mtl,
                'color_description'      => $colorDesc,
                'color_code'             => $colorCode,
                'season'                 => $season,
                'size_category'          => $sizeCat,
                'age_group'              => $ageGroup,
                'cargo_receive_date'     => $cargoDate,
                'erp_crd'                => $erpCrd,
                'email_crd'              => $emailCrd,
                'crd_differs'            => $crdDiffers,
                'country'                => (string) ($erpOrder['country'] ?? $reqRow['country'] ?? ''),
                'buyer'                  => (string) ($erpOrder['buyer'] ?? ''),
                'service_provider'       => (string) ($srcReport['service_provider'] ?? ($testPkg === 'KC Test Package' ? 'KOTITI Testing & Research Institute' : '')),
                'service_test_type'      => (string) ($srcReport['service_test_type'] ?? self::shortTypeForPackage($testPkg)),
                'remarks'                => trim("VF Test Request | {$prodCode} | {$mtl}" . (! empty($erpOrder['po_no']) ? " | PO: {$erpOrder['po_no']}" : '')),
            ],
            'erp_order'  => $erpOrder,
            'src_report' => $srcReport,
        ];
    }

    /** Inserts full dberp.production_orders snapshot + generated application_no into tbl_vf_production_snapshots. */
    public function snapshotProductionOrder(int $requestId, string $appNo, string $testPkg, ?array $erp = null): void
    {
        $this->ensureTables();
        $db = $this->db();

        $req = $requestId > 0 ? $db->table('tbl_vf_test_requests')->where('id', $requestId)->get(1)->getRowArray() : null;
        if (! $erp) {
            $erpId = (int) ($req['production_order_id'] ?? 0);
            if ($erpId > 0) {
                $erp = $db->query('SELECT * FROM dberp.production_orders WHERE id = ? LIMIT 1', [$erpId])->getRowArray();
            } elseif (! empty($req['prod_code'])) {
                $erp = $db->query('SELECT * FROM dberp.production_orders WHERE prod_code = ? LIMIT 1', [$req['prod_code']])->getRowArray();
            } elseif (! empty($req['mtl_no'])) {
                $erp = $db->query('SELECT * FROM dberp.production_orders WHERE mtl_no = ? ORDER BY id DESC LIMIT 1', [$req['mtl_no']])->getRowArray();
            }
        }

        $exists = $db->table('tbl_vf_production_snapshots')
            ->where('generated_application_no', $appNo)
            ->where('request_id', $requestId)
            ->countAllResults();
        if ($exists > 0) {
            return;
        }

        $db->table('tbl_vf_production_snapshots')->insert([
            'request_id'               => $requestId ?: null,
            'generated_application_no' => $appNo,
            'test_package'             => $testPkg,
            'erp_order_id'             => (int) ($erp['id'] ?? 0) ?: null,
            'comp_no'                  => $erp['comp_no'] ?? null,
            'company'                  => $erp['company'] ?? ($req['factory'] ?? 'DTP'),
            'prod_code'                => (string) ($erp['prod_code'] ?? $req['prod_code'] ?? ''),
            'brand_no'                 => $erp['brand_no'] ?? '',
            'brand'                    => $erp['brand'] ?? 'Vans',
            'buyer'                    => $erp['buyer'] ?? ($req['buyer'] ?? null),
            'country_c'                => $erp['country_c'] ?? '',
            'country'                  => $erp['country'] ?? ($req['country'] ?? null),
            'po_no'                    => $erp['po_no'] ?? ($req['po_no'] ?? null),
            'so_no'                    => $erp['so_no'] ?? null,
            'style_no'                 => $erp['style_no'] ?? ($req['style_no'] ?? null),
            'mtl_no'                   => $erp['mtl_no'] ?? ($req['mtl_no'] ?? null),
            'colorway_c'               => $erp['colorway_c'] ?? ($req['colorway_c'] ?? null),
            'colorway'                 => $erp['colorway'] ?? ($req['colorway'] ?? null),
            'cutting_die_no'           => $erp['cutting_die_no'] ?? null,
            'last_no'                  => $erp['last_no'] ?? null,
            'outsole_no'               => $erp['outsole_no'] ?? null,
            'acc_date'                 => $erp['acc_date'] ?? null,
            'crd'                      => $erp['crd'] ?? ($req['crd'] ?? null),
            'pairs'                    => (int) ($erp['pairs'] ?? 0),
            'cartons'                  => (int) ($erp['cartons'] ?? 0),
            'specific_finish'          => $erp['specific_finish'] ?? null,
            'rubber_desc'              => $erp['rubber_desc'] ?? null,
            'size_no'                  => $erp['size_no'] ?? ($req['size_no'] ?? null),
            'size_name_c'              => $erp['size_name_c'] ?? null,
            'size_name_e'              => $erp['size_name_e'] ?? null,
            'batch_no'                 => $erp['batch_no'] ?? '',
            'produce_batch'            => $erp['produce_batch'] ?? null,
            'packing_batch'            => $erp['packing_batch'] ?? null,
            'remark'                   => $erp['remark'] ?? null,
            'image_file'               => $erp['image_file'] ?? null,
            'image_path'               => $erp['image_path'] ?? null,
            'picture_name'             => $erp['picture_name'] ?? '',
            'creator'                  => $erp['creator'] ?? null,
            'created_date'             => $erp['created_date'] ?? null,
            'modifier'                 => $erp['modifier'] ?? null,
            'modified_date'            => $erp['modified_date'] ?? null,
            'erp_status'               => $erp['status'] ?? null,
            'snapshot_at'              => date('Y-m-d H:i:s'),
        ]);
    }

    /** Exact filename: "{Application No} - {MTL#} - {Test TYPE}.pdf" */
    public static function formatPdfFilename(string $appNo, string $mtlNo, string $testPkgOrShort): string
    {
        $short = strtoupper(self::shortTypeForPackage($testPkgOrShort));
        $raw   = trim("{$appNo} - {$mtlNo} - {$short}") . '.PDF';

        return preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', '_', $raw) ?: ($appNo . '.PDF');
    }

    /** Handles the callback of a "vf_pdf" job: the printed eTRF PDF of an existing Application is saved on the request that holds that Application No. */
    public function handlePdfCallback(array $job, array $p): void
    {
        $this->ensureTables();
        $db  = $this->db();
        $app = strtoupper(trim((string) ($job['application_no'] ?? '')));
        $req = $db->table('tbl_vf_test_requests')->where('generated_application_no', $app)->orderBy('id', 'DESC')->get(1)->getRowArray();
        if (! $req) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        if (($p['state'] ?? '') !== 'done') {
            if (($p['state'] ?? '') !== 'running') {
                $db->table('tbl_vf_test_requests')->where('id', $req['id'])->update(['pdf_capture_warning' => mb_substr('PDF from InterLink failed: ' . (string) ($p['message'] ?? ''), 0, 250), 'updated_at' => $now]);
            }

            return;
        }
        $bytes = base64_decode((string) ($p['pdf_b64'] ?? ''), true);
        if ($bytes === false || strlen($bytes) < 500 || ! str_starts_with($bytes, '%PDF')) {
            $db->table('tbl_vf_test_requests')->where('id', $req['id'])->update(['pdf_capture_warning' => 'InterLink returned no real PDF.', 'updated_at' => $now]);

            return;
        }
        $name = self::formatPdfFilename($app, (string) $req['mtl_no'], (string) $req['test_package']);
        $dir  = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'vf_etrf_pdfs' . DIRECTORY_SEPARATOR . ($req['batch_id'] ?: 'single') . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (@file_put_contents($dir . $name, $bytes) !== false) {
            $db->table('tbl_vf_test_requests')->where('id', $req['id'])->update(['etrf_pdf_name' => $name, 'etrf_pdf_path' => $dir . $name, 'pdf_capture_warning' => '', 'updated_at' => $now]);
        }
    }

    /**
     * Asks the InterLink service to print the eTRF PDF of every submitted request that has an Application No. but no PDF yet.
     *
     * @param list<int> $requestIds empty = every such request of the batch
     *
     * @return array{ok:bool,queued:int,error?:string}
     */
    public function queuePdfFetch(int $batchId, array $requestIds = []): array
    {
        $this->ensureTables();
        $db      = $this->db();
        $baseUrl = SystemSettings::serviceUrl();
        $key     = (string) SystemSettings::get('interlink.service.key', '');
        if (! SystemSettings::bool('interlink.service.enabled', true) || $baseUrl === '' || strlen($key) < 16) {
            return ['ok' => false, 'queued' => 0, 'error' => 'InterLink background service is not configured.'];
        }
        $q = $db->table('tbl_vf_test_requests')->where('batch_id', $batchId)->where('generated_application_no !=', '');
        if ($requestIds) {
            $q->whereIn('id', $requestIds);
        }
        $n = 0;
        foreach ($q->orderBy('id', 'ASC')->get()->getResultArray() as $r) {
            $app = (string) $r['generated_application_no'];
            if (! preg_match('/^A\d{8,12}(-REV\d+)?$/', $app) || (($r['etrf_pdf_path'] ?? '') !== '' && is_file((string) $r['etrf_pdf_path']))) {
                continue;
            }
            $uid = bin2hex(random_bytes(12));
            $db->table('tbl_interlink_service_jobs')->insert(['job_uid' => $uid, 'application_no' => $app, 'lab_ref_no' => '', 'report_no' => 'eTRF PDF', 'mode' => 'vf_pdf', 'user_id' => 0, 'user_name' => 'pdf-' . substr($uid, 0, 6), 'status' => 'queued', 'created_at' => date('Y-m-d H:i:s')]);
            try {
                $res  = service('curlrequest')->post($baseUrl . '/jobs', ['headers' => ['X-Service-Key' => $key], 'json' => [
                    'job_uid' => $uid, 'application_no' => $app, 'report_no' => 'eTRF PDF', 'mode' => 'vf_pdf', 'account' => 'dtp_qcasst@dtshoes.com',
                    'user_name' => 'pdf-' . substr($uid, 0, 6), 'callback_url' => site_url('interlink/service/callback'), 'job' => ['application_no' => $app],
                ], 'timeout' => 15, 'http_errors' => false]);
                $body = json_decode((string) $res->getBody(), true) ?: [];
                if ($res->getStatusCode() === 200 && ! empty($body['ok'])) {
                    $n++;
                } elseif ($res->getStatusCode() === 409) {
                    $db->table('tbl_interlink_service_jobs')->where('job_uid', $uid)->delete();   // already waiting in the service
                } else {
                    throw new \RuntimeException((string) ($body['error'] ?? 'Service HTTP ' . $res->getStatusCode()));
                }
            } catch (\Throwable $e) {
                $db->table('tbl_interlink_service_jobs')->where('job_uid', $uid)->update(['status' => 'failed', 'message' => $e->getMessage(), 'finished_at' => date('Y-m-d H:i:s')]);

                return ['ok' => false, 'queued' => $n, 'error' => 'Could not reach NexusInterlinkService: ' . $e->getMessage()];
            }
        }

        return ['ok' => true, 'queued' => $n];
    }

    /** Handles completion callback from NexusInterlinkService for mode="vf_copy". */
    public function handleCopyCallback(array $job, array $p): void
    {
        $this->ensureTables();
        $db     = $this->db();
        $uid    = (string) ($job['job_uid'] ?? '');
        $state  = (string) ($p['state'] ?? 'failed');
        $genApp = strtoupper(trim((string) ($p['application_no'] ?? '')));
        // only a real InterLink Application No. counts: before one is generated the service reports the job name ("VF:VSP26070081"), which must never be stored as a result
        if (! preg_match('/^A\d{8,12}(-REV\d+)?$/', $genApp)) {
            $genApp = '';
        }
        $msg    = mb_substr((string) ($p['message'] ?? ''), 0, 480);

        $req = $db->table('tbl_vf_test_requests')->where('interlink_job_uid', $uid)->get(1)->getRowArray();
        if (! $req) {
            return;
        }
        $reqId = (int) $req['id'];
        $now   = date('Y-m-d H:i:s');

        if ($state === 'running') {
            $runUpd = [
                'interlink_status'  => 'Running',
                'interlink_message' => $msg ?: 'Submitting via Copy as New in InterLink...',
                'stage'             => 'SUBMITTING',
                'updated_at'        => $now,
            ];
            if ($genApp !== '') {
                // Save Application No. & ERP snapshot as soon as InterLink creates it (before Print PDF completes)
                $runUpd['generated_application_no'] = $genApp;
                $runUpd['tr_no']                    = $req['tr_no'] ?: $genApp;
                $this->snapshotProductionOrder($reqId, $genApp, (string) $req['test_package']);
            }
            $db->table('tbl_vf_test_requests')->where('id', $reqId)->update($runUpd);

            return;
        }

        if ($state !== 'done' && $genApp === '') {
            $db->table('tbl_vf_test_requests')->where('id', $reqId)->update([
                'interlink_status'  => 'Failed',
                'interlink_message' => $msg ?: 'InterLink submission failed',
                'stage'             => 'FAILED',
                'updated_at'        => $now,
            ]);
            $db->table('tbl_vf_test_events')->insert([
                'request_id'   => $reqId,
                'batch_id'     => $req['batch_id'] ?: null,
                'event_type'   => 'INTERLINK_FAILED',
                'subject'      => (string) ($req['prod_code'] . ' ' . $req['mtl_no']),
                'details_json' => json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE),
                'created_at'   => $now,
            ]);

            return;
        }

        if ($genApp === '') {
            // InterLink finished, but no Application No. could be read: do not pretend it is a result, and do not leave it as "sending"
            $db->table('tbl_vf_test_requests')->where('id', $reqId)->update([
                'interlink_status'  => 'Check InterLink',
                'interlink_message' => mb_substr(($msg ?: 'The job ended') . ' - its Application No. was not read; look for it in InterLink before submitting again.', 0, 480),
                'stage'             => 'FAILED',
                'updated_at'        => $now,
            ]);

            return;
        }

        $pdfName = self::formatPdfFilename($genApp, (string) $req['mtl_no'], (string) $req['test_package']);
        $pdfPath = '';
        $pdfB64  = (string) ($p['pdf_b64'] ?? '');
        if ($pdfB64 !== '') {
            $bytes = base64_decode($pdfB64, true);
            if ($bytes !== false && strlen($bytes) > 100) {
                $subDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'vf_etrf_pdfs' . DIRECTORY_SEPARATOR . ($req['batch_id'] ?: 'single') . DIRECTORY_SEPARATOR;
                if (! is_dir($subDir)) {
                    @mkdir($subDir, 0775, true);
                }
                if (@file_put_contents($subDir . $pdfName, $bytes) !== false) {
                    $pdfPath = $subDir . $pdfName;
                }
            }
        }
        $pdfWarn = $pdfPath === ''
            ? ($state !== 'done' ? mb_substr("Assigned {$genApp}, post-submit warning: {$msg}", 0, 250) : 'Application No. assigned, but Print PDF did not capture automatically.')
            : '';

        $db->table('tbl_vf_test_requests')->where('id', $reqId)->update([
            'generated_application_no' => $genApp,
            'tr_no'                    => $req['tr_no'] ?: $genApp,
            'interlink_status'         => 'Submitted',
            'interlink_message'        => $msg ?: "Submitted in InterLink as {$genApp}",
            'etrf_pdf_name'            => $pdfName,
            'etrf_pdf_path'            => $pdfPath,
            'pdf_capture_warning'      => $pdfWarn,
            'stage'                    => 'SUBMITTED_INTERLINK',
            'submitted_at'             => $now,
            'updated_at'               => $now,
        ]);

        // Snapshot dberp.production_orders + generated_application_no
        $this->snapshotProductionOrder($reqId, $genApp, (string) $req['test_package']);

        // Also register in tbl_interlink_reports so the VF - Test Request list shows it right away
        if ($db->tableExists('tbl_interlink_reports')) {
            $ex = $db->table('tbl_interlink_reports')->where('application_no', $genApp)->get(1)->getRowArray();
            $suffix = substr(preg_replace('/\D/', '', $genApp), -5) ?: '00001';
            $styleNo = preg_match('/^([A-Z0-9]{8})[A-Z0-9]{3}$/i', (string) $req['mtl_no'], $m) ? strtoupper($m[1]) : (string) $req['mtl_no'];
            $cCode   = preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})$/i', (string) $req['mtl_no'], $cm) ? strtoupper($cm[1]) : '';
            $repData = [
                'application_no'      => $genApp,
                'trf_submission_date' => date('d M Y'),
                'job_status'          => 'Submitted',
                'company_name'        => 'KEY TRADE CO., LTD',
                'vendor_id'           => '0000507450',
                'vendor_name'         => 'KEY TRADE CO., LTD',
                'factory_id'          => '0000507452',
                'factory_name'        => 'DATIAN SUBIC SHOES INC.',
                'buyer_brand'         => 'Vans',
                'test_stage'          => 'Production',
                'service_test_type'   => self::shortTypeForPackage((string) $req['test_package']),
                'style_no'            => $styleNo,
                'style_color_no'      => (string) $req['mtl_no'],
                'product_name'        => self::cleanProductName((string) $req['style_no']),
                'color_code'          => $cCode,
                'colorway_name'       => (string) $req['colorway'],
                'season'              => (string) $req['season'],
                'test_packages'       => (string) $req['test_package'],
                'cargo_receive_date'  => $req['crd'] ? date('d M Y', strtotime((string) $req['crd'])) : '',
                'updated_at'          => $now,
            ];
            if ($ex) {
                $db->table('tbl_interlink_reports')->where('id', $ex['id'])->update($repData);
            } else {
                $repData['nexus_sample_id']     = 'NXL-' . date('Y') . '-' . $suffix;
                $repData['nexus_work_order_no'] = 'WO-' . date('Ymd') . '-' . $suffix;
                $repData['created_at']          = $now;
                $db->table('tbl_interlink_reports')->insert($repData);
            }
        }

        $db->table('tbl_vf_test_events')->insert([
            'request_id'   => $reqId,
            'batch_id'     => $req['batch_id'] ?: null,
            'event_type'   => 'INTERLINK_SUBMITTED',
            'subject'      => "{$genApp} / {$req['mtl_no']}",
            'details_json' => json_encode(['application_no' => $genApp, 'pdf_name' => $pdfName, 'has_pdf' => $pdfPath !== ''], JSON_UNESCAPED_UNICODE),
            'created_at'   => $now,
        ]);

        // Refresh batch submitted count & auto-send grouped email when all items in the batch are submitted
        $batchId = (int) ($req['batch_id'] ?? 0);
        if ($batchId > 0) {
            $batch  = $db->table('tbl_vf_email_batches')->where('id', $batchId)->get(1)->getRowArray();
            $total  = (int) $db->table('tbl_vf_test_requests')->where('batch_id', $batchId)->countAllResults();
            $done   = (int) $db->table('tbl_vf_test_requests')->where('batch_id', $batchId)->where('generated_application_no !=', '')->countAllResults();
            $unsent = (int) $db->table('tbl_vf_test_requests')->where('batch_id', $batchId)->where('generated_application_no !=', '')->where('etrf_emailed_at IS NULL', null, false)->countAllResults();
            $db->table('tbl_vf_email_batches')->where('id', $batchId)->update([
                'item_count'      => $total,
                'submitted_count' => $done,
                'updated_at'      => $now,
            ]);
            if ($batch && ! empty($batch['auto_send_email']) && $total > 0 && $done >= $total && $unsent > 0) {
                $this->sendBatchEmail($batchId);
            }
        }
    }

    /** Integer to lowercase English word for email phrasing ("eleven (11)"). */
    private static function countPhrase(int $n): string
    {
        $words = [1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty'];

        return isset($words[$n]) ? "{$words[$n]} ({$n})" : "{$n}";
    }

    /**
     * Requests whose test already exists in InterLink (same MTL# and Test Package, e.g. submitted by hand earlier) take that Application No., so
     * VF Test Monitoring shows it and its eTRF PDF can be e-mailed. The printed PDF is taken from the Outlook e-mail it was sent in
     * (file name "A006022419 - VN000CYA6MZ CPSIA TEST.PDF"), when that e-mail is among the synced ones.
     *
     * @return array{linked:int,pdfs:int}
     */
    public function linkExistingApplications(?int $batchId = null): array
    {
        $this->ensureTables();
        $db = $this->db();
        if (! $db->tableExists('tbl_interlink_reports')) {
            return ['linked' => 0, 'pdfs' => 0];
        }
        $q = $db->table('tbl_vf_test_requests r')->select('r.*')->join('tbl_vf_email_batches b', 'b.id = r.batch_id', 'left')
            ->where('r.stage', 'REQUESTED')->where('r.generated_application_no', '')->where('r.mtl_no !=', '')
            ->groupStart()->where('b.source_channel IS NULL', null, false)->orWhere('b.source_channel !=', 'excel_import')->groupEnd();
        if ($batchId) {
            $q->where('r.batch_id', $batchId);
        }
        $reqs = $q->limit(400)->get()->getResultArray();
        if (! $reqs) {
            return ['linked' => 0, 'pdfs' => 0];
        }
        $mtls = array_values(array_unique(array_map(static fn ($r) => strtoupper(trim((string) $r['mtl_no'])), $reqs)));
        $byMtl = [];
        foreach ($db->table('tbl_interlink_reports')->select('application_no, style_color_no, test_packages, job_status, trf_submission_date')
            ->whereIn('style_color_no', $mtls)->like('application_no', 'A', 'after')->orderBy('application_no', 'DESC')->limit(3000)->get()->getResultArray() as $r) {
            $byMtl[strtoupper((string) $r['style_color_no'])][] = $r;
        }
        $used = [];
        foreach ($db->table('tbl_vf_test_requests')->select('generated_application_no')->where('generated_application_no !=', '')->get()->getResultArray() as $u) {
            $used[strtoupper((string) $u['generated_application_no'])] = true;
        }
        $now    = date('Y-m-d H:i:s');
        $linked = [];
        foreach ($reqs as $r) {
            $pkg   = (string) $r['test_package'];
            $terms = array_map('strtolower', array_merge(self::PACKAGES[$pkg]['aliases'] ?? [], [$pkg]));
            foreach ($byMtl[strtoupper(trim((string) $r['mtl_no']))] ?? [] as $cand) {
                $app = strtoupper((string) $cand['application_no']);
                if (isset($used[$app]) || preg_match('/cancel/i', (string) $cand['job_status']) || ! preg_match('/^A\d{8,12}(-REV\d+)?$/', $app)) {
                    continue;
                }
                $tp = strtolower((string) $cand['test_packages']);
                $ok = false;
                foreach ($terms as $t) {
                    if ($t !== '' && str_contains($tp, $t)) {
                        $ok = true;
                        break;
                    }
                }
                if (! $ok) {
                    continue;
                }
                $used[$app] = true;
                $sub = ! empty($cand['trf_submission_date']) ? date('Y-m-d H:i:s', strtotime((string) $cand['trf_submission_date']) ?: time()) : $now;
                $db->table('tbl_vf_test_requests')->where('id', $r['id'])->update([
                    'generated_application_no' => $app,
                    'tr_no'                    => $r['tr_no'] ?: $app,
                    'interlink_status'         => 'Submitted',
                    'interlink_message'        => "Linked to the existing InterLink Application {$app} (same MTL# and Test Package).",
                    'stage'                    => 'SUBMITTED_INTERLINK',
                    'submitted_at'             => $sub,
                    'updated_at'               => $now,
                ]);
                $db->table('tbl_vf_test_events')->insert(['request_id' => (int) $r['id'], 'batch_id' => $r['batch_id'] ?: null, 'event_type' => 'LINKED_EXISTING', 'subject' => $app, 'details_json' => json_encode(['mtl' => $r['mtl_no'], 'package' => $pkg], JSON_UNESCAPED_UNICODE), 'created_at' => $now]);
                $linked[(int) $r['id']] = $app;
                break;
            }
        }
        $pdfs = 0;
        if ($linked) {
            // the printed eTRF PDFs that were e-mailed earlier (file name begins with the Application No.)
            try {
                $reader = new OutlookSyncReader();
                if ($reader->hasKey()) {
                    $index = [];
                    foreach ($reader->fetchRecentEmails(200) as $em) {
                        foreach ((array) $em['attachments'] as $att) {
                            $fn = (string) ($att['OriginalFileName'] ?? $att['FileName'] ?? $att['file_name'] ?? '');
                            if (preg_match('/^(A\d{8,12}(?:-REV\d+)?)\b.*\.pdf$/i', $fn, $m) && ! isset($index[strtoupper($m[1])])) {
                                $index[strtoupper($m[1])] = $att;
                            }
                        }
                    }
                    foreach ($linked as $rid => $app) {
                        if (! isset($index[$app])) {
                            continue;
                        }
                        $bytes = $reader->readAttachmentBytes($index[$app]);
                        if ($bytes === null || strlen($bytes) < 500 || ! str_starts_with($bytes, '%PDF')) {
                            continue;
                        }
                        $row   = $db->table('tbl_vf_test_requests')->where('id', $rid)->get(1)->getRowArray();
                        $name  = self::formatPdfFilename($app, (string) $row['mtl_no'], (string) $row['test_package']);
                        $dir   = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'vf_etrf_pdfs' . DIRECTORY_SEPARATOR . ($row['batch_id'] ?: 'single') . DIRECTORY_SEPARATOR;
                        if (! is_dir($dir)) {
                            @mkdir($dir, 0775, true);
                        }
                        if (@file_put_contents($dir . $name, $bytes) !== false) {
                            $db->table('tbl_vf_test_requests')->where('id', $rid)->update(['etrf_pdf_name' => $name, 'etrf_pdf_path' => $dir . $name, 'pdf_capture_warning' => '']);
                            $pdfs++;
                        }
                    }
                }
            } catch (\Throwable $e) {
                log_message('warning', 'linkExistingApplications PDF import: ' . $e->getMessage());
            }
        }

        return ['linked' => count($linked), 'pdfs' => $pdfs];
    }

    /** @return array{to:string,cc:string} the To / CC set for a Test Package (Settings > VF > "Who the eTRF PDF e-mail is sent to") */
    public static function recipientsFor(string $testPackage): array
    {
        $first = null;
        foreach (preg_split('/\r?\n/', (string) SystemSettings::get('vf.recipients', '')) ?: [] as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2 || $parts[0] === '') {
                continue;
            }
            $row = ['to' => $parts[1] ?? '', 'cc' => $parts[2] ?? ''];
            $first ??= $row;
            if (strcasecmp($parts[0], $testPackage) === 0) {
                return $row;
            }
        }

        return $first ?? ['to' => 'djc_lab@dtshoes.com', 'cc' => (string) SystemSettings::get('vf_request.default_cc', '')];
    }

    /**
     * Builds the e-mail that sends the printed eTRF PDFs back (a REPLY to the e-mail that asked for them). Used by the dialog preview and by the send.
     *
     * @param array{request_ids?:list<int>,to?:string,cc?:string,subject?:string,reply?:bool,resend_all?:bool} $opts
     */
    public function buildBatchEmail(int $batchId, array $opts = []): array
    {
        $this->ensureTables();
        $db    = $this->db();
        $batch = $db->table('tbl_vf_email_batches')->where('id', $batchId)->get(1)->getRowArray();
        if (! $batch) {
            return ['ok' => false, 'error' => 'Batch not found.'];
        }
        $ids = array_values(array_filter(array_map('intval', (array) ($opts['request_ids'] ?? []))));
        $iq  = $db->table('tbl_vf_test_requests')->where('batch_id', $batchId)->where('generated_application_no !=', '');
        if ($ids) {
            $iq->whereIn('id', $ids);   // exactly what was ticked
        } elseif (empty($opts['resend_all'])) {
            $iq->where('etrf_emailed_at IS NULL', null, false);
        }
        $items = $iq->orderBy('id', 'ASC')->get()->getResultArray();
        if (! $items) {
            $all = (int) $db->table('tbl_vf_test_requests')->where('batch_id', $batchId)->where('generated_application_no !=', '')->countAllResults();

            return ['ok' => false, 'error' => $all > 0 && ! $ids ? 'All submitted items in this batch were already e-mailed. Tick the rows to send again.' : 'No submitted Application No. items to send yet (tick rows that have an Application No.).'];
        }

        $attachments = [];
        $files       = [];
        $warnings    = [];
        $sentIds     = [];
        $pkg         = (string) ($items[0]['test_package'] ?: $batch['test_package']);
        foreach ($items as $it) {
            $sentIds[] = (int) $it['id'];
            $appNo = (string) $it['generated_application_no'];
            $short = (string) ($it['test_type_short'] ?: self::shortTypeForPackage((string) $it['test_package']));
            $fName = self::formatPdfFilename($appNo, (string) $it['mtl_no'], $short);   // Application No - MTL# - CPSIA TEST.PDF
            $fPath = (string) ($it['etrf_pdf_path'] ?? '');
            if ($fPath !== '' && is_file($fPath)) {
                $bytes = file_get_contents($fPath);
                if ($bytes !== false) {
                    $attachments[] = ['filename' => $fName, 'data_base64' => base64_encode($bytes)];
                    $files[]       = ['request_id' => (int) $it['id'], 'filename' => $fName, 'bytes' => strlen($bytes), 'ok' => true];
                    continue;
                }
            }
            $files[]    = ['request_id' => (int) $it['id'], 'filename' => $fName, 'bytes' => 0, 'ok' => false];
            $warnings[] = "No PDF saved for {$appNo} ({$it['mtl_no']}); it will be left out of the attachments.";
        }

        $shortLabel = strtoupper((string) (self::shortTypeForPackage($pkg) ?: ($batch['test_type_short'] ?: 'CPSIA')));
        $shortLabel = trim(preg_replace('/\s*test$/i', '', $shortLabel)) ?: $shortLabel;   // "CPSIA test" -> "CPSIA"
        $n          = count($items);
        $cntPhrase  = self::countPhrase($n);

        $def  = self::recipientsFor($pkg);
        $to   = trim((string) ($opts['to'] ?? '')) ?: ($def['to'] ?: (string) ($batch['sender_email'] ?: 'djc_lab@dtshoes.com'));
        $cc   = array_key_exists('cc', $opts) ? trim((string) $opts['cc']) : ($def['cc'] ?: (string) $batch['cc_list']);
        $orig = trim((string) ($batch['subject'] ?? ''));
        $subject = trim((string) ($opts['subject'] ?? '')) ?: ($orig !== '' ? (stripos($orig, 're:') === 0 ? $orig : 'RE: ' . $orig) : "RE: DTP {$shortLabel} Test Request");

        $sig  = static fn (string $k, string $d = '') => (string) SystemSettings::get($k, $d);
        $logo = '';
        $logoPath = trim($sig('vf.mail_logo'));
        if ($logoPath !== '' && is_file($logoPath)) {
            $ext  = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');
            $logo = '<p><img alt="" src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($logoPath)) . '" style="height:90px"></p>';
        }
        $body = '<div style="font-family:\'Times New Roman\',serif;font-size:13.5px;color:#000;line-height:1.5">'
            . '<p>' . esc($sig('vf.mail_greeting', 'Hello DJC TEAM,')) . '</p>'
            . '<p>This email is to provide the ETRF application number for the ' . esc($cntPhrase) . ' new ' . esc($shortLabel) . ' ' . ($n === 1 ? 'item' : 'items') . ' that ' . ($n === 1 ? 'requires' : 'require') . ' testing.</p>'
            . '<p>Please refer to the attached files for your reference. Should you have any questions or require further clarification, please feel free to contact me.</p>'
            . '<p style="font-family:\'Monotype Corsiva\',\'Segoe Script\',cursive;font-size:17px;font-weight:bold;color:#1f3864"><i>Thank you and have a blessed day!</i></p>'
            . '<p style="font-family:Arial,sans-serif;font-size:11px;font-weight:bold;color:#1f3864;margin-bottom:2px">Best Regards,</p>'
            . '<p style="margin-top:2px"><b><u style="color:#ff0000;font-size:20px">' . esc($sig('vf.sign_name', 'QC Laboratory Team')) . '</u></b><br>'
            . '<b style="font-family:Arial,sans-serif;font-size:13px;color:#2e75b6">' . esc($sig('vf.sign_title')) . '</b><br>'
            . '<b style="font-family:Arial,sans-serif;font-size:13px;color:#000"><i>' . esc($sig('vf.sign_company', 'DA TIAN SUBIC SHOES INC.')) . '</i></b><br>'
            . '<i style="font-family:Arial,sans-serif;font-size:12px;color:#2e75b6">Telephone No.: ' . esc($sig('vf.sign_phone')) . '</i><br>'
            . '<i style="font-family:Arial,sans-serif;font-size:12px;color:#2e75b6">' . esc($sig('vf.sign_address')) . '</i></p>'
            . $logo
            . '</div>';

        $reply = array_key_exists('reply', $opts) ? ! empty($opts['reply']) : true;

        return [
            'ok' => true, 'batch_id' => $batchId, 'to' => $to, 'cc' => $cc, 'subject' => $subject, 'body' => $body, 'attachments' => $attachments, 'files' => $files,
            'warnings' => $warnings, 'request_ids' => $sentIds, 'count' => $n, 'package' => $pkg,
            'reply' => $reply, 'reply_to_message_id' => trim((string) ($batch['internet_message_id'] ?? '')), 'reply_to_subject' => $orig,
            'can_reply' => trim((string) ($batch['internet_message_id'] ?? '')) !== '',
        ];
    }

    /** The sender (Outlook Relay / Hub) reports its result into tbl_email_outbox: a request is "e-mailed" only when its mail was really sent. */
    public function reconcileOutbox(): void
    {
        $db = $this->db();
        MailOutbox::ensureTable();
        $rows = $db->query("SELECT r.id, r.batch_id, r.stage, o.sent_at FROM tbl_vf_test_requests r JOIN tbl_email_outbox o ON o.id = r.etrf_outbox_id WHERE r.etrf_emailed_at IS NULL AND o.status = 'sent'")->getResultArray();
        if (! $rows) {
            return;
        }
        $batches = [];
        foreach ($rows as $r) {
            $when = $r['sent_at'] ?: date('Y-m-d H:i:s');
            $upd  = ['etrf_emailed_at' => $when, 'updated_at' => date('Y-m-d H:i:s')];
            if ($r['stage'] === 'SUBMITTED_INTERLINK') {
                $upd['stage'] = 'EMAIL_SENT';
            }
            $db->table('tbl_vf_test_requests')->where('id', $r['id'])->update($upd);
            $batches[(int) $r['batch_id']] = $when;
        }
        foreach ($batches as $bid => $when) {
            $db->table('tbl_vf_email_batches')->where('id', $bid)->update(['email_sent' => 1, 'email_sent_at' => $when]);
            $this->notifyMailSent((int) $bid, $when, count(array_filter($rows, static fn ($r) => (int) $r['batch_id'] === (int) $bid)));
        }
    }

    /** Puts "the e-mail was sent" into the Inbox ("InterLink 2.0 - Application No." thread) of the person who sent it. Runs once per send (rows are marked e-mailed first). */
    private function notifyMailSent(int $batchId, string $when, int $items): void
    {
        try {
            $db = $this->db();
            $b  = $db->table('tbl_vf_email_batches')->where('id', $batchId)->get()->getRowArray();
            $uid = (int) ($b['notify_user_id'] ?? 0);
            if (! $b || $uid <= 0) {
                return;
            }
            $o = $db->table('tbl_email_outbox')->select('to_address, cc_address, subject')->where('id', (int) ($b['outbox_id'] ?? 0))->get()->getRowArray() ?: [];
            $lines = [
                '✅ VF Test Request e-mail SENT',
                '📨 Subject: ' . ($o['subject'] ?? $b['subject'] ?? ''),
                '👥 To: ' . ($o['to_address'] ?? '-') . (! empty($o['cc_address']) ? '  |  CC: ' . $o['cc_address'] : ''),
                "📦 {$items} request(s) with their eTRF PDF(s)",
                '🕒 Sent: ' . $when,
            ];
            (new \App\Libraries\Chat\NexusStore())->send('interlink_etrf', (string) $uid, implode("\n", $lines));
        } catch (\Throwable $e) {
            log_message('error', '[VfTestRequestService] inbox notice failed: ' . $e->getMessage());
        }
    }

    /** What the e-mail dialog shows (no PDF data): recipients, subject, the message, and the files. */
    public function previewBatchEmail(int $batchId, array $opts = []): array
    {
        $b = $this->buildBatchEmail($batchId, $opts);
        if (empty($b['ok'])) {
            return $b;
        }
        unset($b['attachments']);

        return $b;
    }

    /** Queues one e-mail for the chosen items of a batch (a reply to the request e-mail) via MailOutbox. */
    public function sendBatchEmail(int $batchId, ?array $opts = []): array
    {
        $db = $this->db();
        $b  = $this->buildBatchEmail($batchId, $opts ?? []);
        if (empty($b['ok'])) {
            return $b;
        }
        $replyId = ! empty($b['reply']) && ! empty($b['reply_to_message_id']) ? $b['reply_to_message_id'] : '';
        [$outboxId, $err] = MailOutbox::enqueue($b['to'], $b['subject'], $b['body'], [
            'cc'                  => $b['cc'],
            'attachments'         => $b['attachments'],
            'is_html'             => true,
            'source'              => 'NEXUS-L VF-Request',
            'sender_name'         => 'DTP QC Lab',
            'reply_to_message_id' => $replyId,
            'reply_to_subject'    => $replyId !== '' ? $b['reply_to_subject'] : '',
        ]);
        if ($outboxId <= 0) {
            return ['ok' => false, 'error' => $err ?: 'Failed to queue email.'];
        }

        $now = date('Y-m-d H:i:s');
        $this->ensureTables();
        $db->table('tbl_vf_email_batches')->where('id', $batchId)->update(['outbox_id' => $outboxId, 'notify_user_id' => (int) ($opts['notify_user_id'] ?? 0) ?: null, 'updated_at' => $now]);
        $sentIds = $b['request_ids'];
        if ($sentIds) {
            // queued, not sent yet: the rows follow the real state of the mail (see reconcileOutbox)
            $db->table('tbl_vf_test_requests')->whereIn('id', $sentIds)->update(['etrf_outbox_id' => $outboxId, 'etrf_emailed_at' => null, 'updated_at' => $now]);
        }
        $db->table('tbl_vf_test_events')->insert([
            'batch_id'     => $batchId,
            'event_type'   => 'OUTBOX_QUEUED',
            'subject'      => $b['subject'],
            'details_json' => json_encode(['outbox_id' => $outboxId, 'to' => $b['to'], 'cc' => $b['cc'], 'reply' => $replyId !== '', 'attachments' => count($b['attachments']), 'item_count' => count($sentIds)], JSON_UNESCAPED_UNICODE),
            'created_at'   => $now,
        ]);

        return ['ok' => true, 'outbox_id' => $outboxId, 'attached_pdfs' => count($b['attachments']), 'to' => $b['to'], 'reply' => $replyId !== '', 'warnings' => $b['warnings']];
    }

    // ---------------------------------------------------------------- e-mail sources (configurable, nothing fixed in code)

    /** the fields a source's column map may fill; the first eight are the original ones */
    public const REQUEST_FIELDS = [
        'factory' => 'Factory', 'prod_code' => 'Prod Code', 'style_no' => 'Style', 'mtl_no' => 'MTL#', 'colorway_c' => 'Color', 'state' => 'State',
        'tr_no' => 'TR No', 'crd' => 'CRD', 'po_no' => 'PO#', 'buyer' => 'Buyer', 'country' => 'Country', 'size_no' => 'Size', 'colorway' => 'Color (English)',
    ];

    /** @return list<array<string,mixed>> the sources with column_map decoded */
    public function sources(bool $onlyEnabled = false): array
    {
        $this->ensureTables();
        $qb = $this->db()->table('tbl_vf_email_sources');
        if ($onlyEnabled) {
            $qb->where('enabled', 1);
        }
        $rows = $qb->orderBy('priority', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($rows as &$r) {
            $m = json_decode((string) ($r['column_map_json'] ?? ''), true);
            $r['column_map'] = is_array($m) ? $m : [];
        }
        unset($r);

        return $rows;
    }

    /** @return list<string> */
    public static function splitList(string $s): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\n\r]+/u', $s) ?: []), static fn ($x) => $x !== ''));
    }

    /** exact address, *@domain / any * wildcard, or a bare domain / word */
    public static function senderMatches(string $patterns, string $sender): bool
    {
        $sender = strtolower(trim($sender));
        foreach (self::splitList($patterns) as $p) {
            $p = strtolower($p);
            if (str_contains($p, '*')) {
                if (fnmatch($p, $sender)) {
                    return true;
                }
            } elseif (str_contains($p, '@')) {
                if ($p === $sender) {
                    return true;
                }
            } elseif ($sender !== '' && (str_ends_with($sender, '@' . $p) || str_contains($sender, $p))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gives the senders of every enabled source to the Outlook Attachment Sync database (outlook_attachment_sync.accepted_senders). Every Control
     * Center (PC 43 / PC 45) takes that list within a minute, so a sender added here is accepted without touching the Outlook apps.
     *
     * @return array{ok:bool,count:int,error?:string}
     */
    public function shareSendersWithOutlook(): array
    {
        try {
            $db = $this->db();
            $db->query('CREATE TABLE IF NOT EXISTS outlook_attachment_sync.accepted_senders (
                pattern VARCHAR(255) NOT NULL PRIMARY KEY,
                added_by VARCHAR(150) DEFAULT "",
                note VARCHAR(255) DEFAULT "",
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                added_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            if (! $db->query("SHOW COLUMNS FROM outlook_attachment_sync.accepted_senders LIKE 'is_active'")->getRowArray()) {
                $db->query('ALTER TABLE outlook_attachment_sync.accepted_senders ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1');
            }
            $n = 0;
            foreach ($this->sources(true) as $src) {
                foreach (self::splitList((string) $src['sender_patterns']) as $p) {
                    $p = strtolower(trim($p));
                    $p = preg_replace('/^\*(?=@)/', '', $p);   // *@domain.com -> @domain.com (the Outlook apps match a domain with a leading @)
                    if ($p === '' || str_contains($p, '*')) {
                        continue;
                    }
                    $db->query('INSERT INTO outlook_attachment_sync.accepted_senders (pattern, added_by, note, is_active, added_at) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE is_active = 1', [$p, 'NEXUS-L', mb_substr('E-mail source: ' . $src['name'], 0, 250)]);
                    $n++;
                }
            }

            return ['ok' => true, 'count' => $n];
        } catch (\Throwable $e) {
            return ['ok' => false, 'count' => 0, 'error' => $e->getMessage()];
        }
    }

    /** the first enabled source (by priority) whose sender and subject rules fit this mail, or null */
    public function findSourceFor(string $sender, string $subject): ?array
    {
        foreach ($this->sources(true) as $src) {
            $hasSender = self::splitList((string) $src['sender_patterns']) !== [];
            $kw        = self::splitList((string) ($src['subject_keywords'] ?? ''));
            if ($hasSender && ! self::senderMatches((string) $src['sender_patterns'], $sender)) {
                continue;
            }
            if (! $hasSender && ! $kw) {
                continue;   // a source with no rule at all would take every mail
            }
            if ($kw) {
                $hit = false;
                foreach ($kw as $k) {
                    if (stripos($subject, $k) !== false) {
                        $hit = true;
                        break;
                    }
                }
                if (! $hit) {
                    continue;
                }
            }

            return $src;
        }

        return null;
    }

    /** Syncs incoming Outlook emails (Stage D Certs -> Stage C Lab Received -> Stage B Transit Container -> Stage A Requests). */
    public function syncFromOutlook(): array
    {
        $this->ensureTables();
        $reader = new OutlookSyncReader();
        if (! $reader->hasKey()) {
            return ['ok' => false, 'error' => 'OutlookAttachmentSync secret key not found.'];
        }

        $emails = $reader->fetchRecentEmails(120);
        // Process oldest -> newest so Stages A -> B -> C -> D apply in chronological order!
        $emails = array_reverse($emails);
        $db     = $this->db();

        $stats = ['scanned' => count($emails), 'batches_added' => 0, 'items_added' => 0, 'items_skipped' => 0, 'transit_updates' => 0, 'lab_received' => 0, 'certificates_saved' => 0];

        foreach ($emails as $em) {
            $uid = (string) $em['record_uid'];
            if ($uid === '') {
                continue;
            }
            $already = (int) $db->table('tbl_vf_test_events')->where('outlook_record_uid', $uid)->countAllResults();
            if ($already > 0) {
                continue;
            }

            $sender  = strtolower($em['sender_email']);
            $subject = (string) $em['subject'];
            $html    = (string) $em['body_html'];
            $text    = (string) $em['body_text'];
            $atts    = (array) $em['attachments'];

            // 1. Check Stage D: Final PDF certificate attachment (SGS / KOTITI report)
            $pdfAtts = array_values(array_filter($atts, static fn ($a) => str_ends_with(strtolower((string) ($a['OriginalFileName'] ?? $a['FileName'] ?? $a['file_name'] ?? '')), '.pdf')));
            if ($pdfAtts && (str_contains($sender, 'sgs.com') || str_contains($sender, 'kotiti') || preg_match('/\b(SL\d{10,}FW|822\d-\d+-\d+|(?:SGS|KOTITI|Test)\s+Report)\b/i', $subject . ' ' . $text))) {
                $saved = $this->applyCertificateEmail($em, $pdfAtts, $reader);
                $stats['certificates_saved'] += $saved;
                if ($saved > 0) {
                    continue;
                }
            }

            // 2. Check Stage C: Lab Received (Expect Due Date + Report No.)
            if (preg_match('/(Expect\s*Due\s*Date|预计出单时间|have received your sample|Customer Reference No)/i', $text . ' ' . $html)) {
                $upd = $this->applyLabReceivedEmail($em);
                $stats['lab_received'] += $upd;
                if ($upd > 0) {
                    continue;
                }
            }

            // 3. Check Stage B BEFORE Stage A using ONLY the unquoted top message (so quoted Need-to-test tables don't hijack transit replies!)
            $topText = trim(self::stripQuotedEmailText($text !== '' ? $text : strip_tags(self::stripQuotedEmailHtml($html))));
            if (preg_match('/(transit|container|柜号|貨櫃|vessel|shipped|\b[A-Z]{4}\d{7}\b)/i', $subject . "\n" . $topText)) {
                $upd = $this->applyTransitEmail($em);
                $stats['transit_updates'] += $upd;
                if ($upd > 0) {
                    continue;
                }
            }

            // 4. Check Stage A: Incoming Test Request HTML Table from djc_lab@dtshoes.com (or containing request table columns)
            $src = $this->findSourceFor($sender, $subject);
            if ($src || preg_match('/指令編號|Need\s+to\s+test|DTP交期|TR\s*No/i', $html . ' ' . $text)) {
                $res = $this->ingestRequestEmail($em['subject'], $html !== '' ? $html : $text, $em['sender_email'], $em['sender_name'], '', $uid, (string) $em['internet_message_id'], (string) $em['received_at'], $src);
                $stats['items_skipped'] += (int) ($res['skipped'] ?? 0);
                if ($res['added'] > 0) {
                    $stats['batches_added'] += $res['batch_created'] ? 1 : 0;
                    $stats['items_added']   += $res['added'];
                    continue;
                }
            }

            // Record scanned non-matching email UID so chatter/moving-notices aren't re-parsed on every sync
            $db->table('tbl_vf_test_events')->insert([
                'event_type'         => 'OUTLOOK_IGNORED',
                'outlook_record_uid' => $uid,
                'subject'            => mb_substr($subject, 0, 500),
                'details_json'       => json_encode(['sender' => $sender], JSON_UNESCAPED_UNICODE),
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
        }

        return ['ok' => true] + $stats;
    }

    /** Parses HTML table or tab-delimited text into request items and persists batch + request rows. */
    public function ingestRequestEmail(
        string $subject,
        string $htmlOrText,
        string $senderEmail = 'djc_lab@dtshoes.com',
        string $senderName = '',
        string $packageOverride = '',
        ?string $outlookUid = null,
        string $inetMsgId = '',
        ?string $receivedAt = null,
        ?array $source = null
    ): array {
        $this->ensureTables();
        $db     = $this->db();
        $source ??= $this->findSourceFor($senderEmail, $subject);
        $rows   = $this->parseHtmlRequestTable($htmlOrText, $source['column_map'] ?? null);
        if (! $rows) {
            return ['ok' => false, 'added' => 0, 'skipped' => 0, 'batch_created' => false, 'error' => 'No test request table rows detected (expected columns: Factory, Prod Code / 指令編號, Style, MTL#, Color, State, TR No, CRD / DTP交期).'];
        }

        $srcPkg = trim((string) ($source['default_package'] ?? ''));
        if ($packageOverride === '' && $srcPkg !== '' && isset(self::PACKAGES[$srcPkg])) {
            $packageOverride = $srcPkg;   // this source always means that package
        }
        if ($packageOverride !== '') {
            $pkg         = self::normalizePackage($packageOverride);
            $needsReview = false;
        } else {
            $det         = self::detectPackageFromEmail($subject, $htmlOrText);
            $pkg         = $det['package'];
            $needsReview = $det['needs_review'];
        }
        $short = self::shortTypeForPackage($pkg);
        $now   = date('Y-m-d H:i:s');
        $recv  = $receivedAt ? date('Y-m-d H:i:s', strtotime($receivedAt)) : $now;

        $batchId = 0;
        $batchCreated = false;
        if ($outlookUid !== null && $outlookUid !== '') {
            $exB = $db->table('tbl_vf_email_batches')->where('outlook_record_uid', $outlookUid)->get(1)->getRowArray();
            if ($exB) {
                $batchId = (int) $exB['id'];
            }
        }
        if ($batchId === 0) {
            $db->table('tbl_vf_email_batches')->insert([
                'outlook_record_uid'   => $outlookUid ?: null,
                'source_id'            => ! empty($source['id']) ? (int) $source['id'] : null,
                'internet_message_id'  => $inetMsgId,
                'source_channel'       => $outlookUid ? 'outlook_sync' : 'manual_paste',
                'sender_name'          => mb_substr($senderName, 0, 191),
                'sender_email'         => mb_substr($senderEmail ?: 'djc_lab@dtshoes.com', 0, 191),
                'to_list'              => mb_substr($senderEmail ?: 'djc_lab@dtshoes.com', 0, 500),
                'subject'              => mb_substr($subject ?: "DTP {$short} Test Request", 0, 500),
                'test_package'         => $pkg,
                'test_type_short'      => $short,
                'package_needs_review' => $needsReview ? 1 : 0,
                'auto_send_email'      => SystemSettings::bool('vf.auto_send_email', true) ? 1 : 0,
                'item_count'           => count($rows),
                'received_at'          => $recv,
                'raw_html'             => mb_substr($htmlOrText, 0, 500000),
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
            $batchId      = (int) $db->insertID();
            $batchCreated = true;
        }

        $added   = 0;
        $skipped = 0;
        foreach ($rows as $r) {
            $prodCode = strtoupper(trim((string) ($r['prod_code'] ?? '')));
            $mtlNo    = strtoupper(trim((string) ($r['mtl_no'] ?? '')));
            if ($prodCode === '' && $mtlNo === '') {
                continue;
            }
            $rowState = trim((string) ($r['state'] ?? ''));
            $rowTrNo  = strtoupper(trim((string) ($r['tr_no'] ?? '')));
            // Skip cancelled/held/completed rows or rows that already carry an InterLink Application No.
            $skipRe = '/\b(cancel(?:led)?|hold|skip|done|completed|no\s*need)\b/i';
            $custom = trim((string) ($source['skip_state_regex'] ?? ''));
            if ($custom !== '' && @preg_match('/' . str_replace('/', '\/', $custom) . '/iu', '') !== false) {
                $skipRe = '/' . str_replace('/', '\/', $custom) . '/iu';
            }
            if (($rowState !== '' && preg_match($skipRe, $rowState))
                || preg_match('/\bA\d{8,12}\b/i', $rowTrNo)) {
                $skipped++;
                continue;
            }

            // Global deduplication across batches so RE: threads re-sending prior tables don't duplicate active requests
            $dup = $db->table('tbl_vf_test_requests')
                ->where('prod_code', $prodCode)
                ->where('mtl_no', $mtlNo)
                ->where('test_package', $pkg)
                ->whereNotIn('stage', ['FAILED', 'HISTORY'])   // Excel history rows are records only: they must not hide a new request
                ->countAllResults();
            if ($dup > 0) {
                $skipped++;
                continue;
            }

            $erp          = null;
            $erpMatched   = 0;
            $erpAmbiguous = 0;
            if ($prodCode !== '') {
                $erp = $db->query('SELECT * FROM dberp.production_orders WHERE prod_code = ? LIMIT 1', [$prodCode])->getRowArray();
                if ($erp) {
                    $erpMatched = 1;
                }
            }
            if (! $erp && $mtlNo !== '') {
                $mtlMatches = $db->query('SELECT * FROM dberp.production_orders WHERE mtl_no = ? ORDER BY id DESC LIMIT 20', [$mtlNo])->getResultArray();
                if (count($mtlMatches) === 1 && $prodCode === '') {
                    $erp        = $mtlMatches[0];
                    $erpMatched = 1;
                } elseif (count($mtlMatches) > 1) {
                    // Do not silently pick a random PO/CRD when 1 MTL# spans multiple Prod Codes
                    $erpAmbiguous = 1;
                }
            }

            // Prefer authoritative ERP CRD when matched cleanly, else fallback to email DTP交期
            $rawCrd = trim((string) (! empty($erp['crd']) ? $erp['crd'] : ($r['crd'] ?? '')));
            $crdVal = self::parseSafeDate($rawCrd);

            $db->table('tbl_vf_test_requests')->insert([
                'batch_id'            => $batchId,
                'production_order_id' => ! empty($erp['id']) ? (int) $erp['id'] : null,
                'erp_matched'         => $erpMatched,
                'erp_ambiguous'       => $erpAmbiguous,
                'factory'             => (string) ($r['factory'] ?: 'DTP'),
                'prod_code'           => $prodCode,
                'source_id'           => ! empty($source['id']) ? (int) $source['id'] : null,
                'extra_json'          => ! empty($r['_extra']) ? json_encode($r['_extra'], JSON_UNESCAPED_UNICODE) : null,
                'po_no'               => (string) (($r['po_no'] ?? '') ?: ($erp['po_no'] ?? '')),
                'style_no'            => (string) ($r['style_no'] ?: ($erp['style_no'] ?? '')),
                'mtl_no'              => $mtlNo,
                'colorway'            => (string) (($r['colorway'] ?? '') ?: ($erp['colorway'] ?? '')),
                'colorway_c'          => (string) ($r['colorway_c'] ?: ($erp['colorway_c'] ?? '')),
                'size_no'             => (string) (($r['size_no'] ?? '') ?: ($erp['size_no'] ?? '')),
                'country'             => (string) (($r['country'] ?? '') ?: ($erp['country'] ?? '')),
                'buyer'               => (string) (($r['buyer'] ?? '') ?: ($erp['buyer'] ?? '')),
                'crd'                 => $crdVal,
                'email_state'         => $rowState !== '' ? $rowState : 'Need to test',
                'tr_no'               => $rowTrNo,
                'test_package'        => $pkg,
                'test_type_short'     => $short,
                'stage'               => 'REQUESTED',
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
            $added++;
        }

        // every row was a duplicate / cancelled / already submitted: do not leave an empty batch in the monitor
        if ($batchCreated && $added === 0) {
            $db->table('tbl_vf_email_batches')->where('id', $batchId)->delete();
            if ($outlookUid) {
                $db->table('tbl_vf_test_events')->insert(['event_type' => 'OUTLOOK_IGNORED', 'outlook_record_uid' => $outlookUid, 'subject' => mb_substr($subject, 0, 500), 'details_json' => json_encode(['skipped' => $skipped, 'reason' => 'no new rows'], JSON_UNESCAPED_UNICODE), 'created_at' => $now]);
            }

            return ['ok' => true, 'batch_id' => 0, 'batch_created' => false, 'added' => 0, 'skipped' => $skipped, 'package' => $pkg, 'package_needs_review' => $needsReview];
        }

        // Sync actual inserted count back onto batch item_count when skipped/duplicate rows were filtered out
        if ($batchCreated && $added !== count($rows)) {
            $db->table('tbl_vf_email_batches')->where('id', $batchId)->update(['item_count' => $added, 'updated_at' => $now]);
        }

        if ($outlookUid) {
            $db->table('tbl_vf_test_events')->insert([
                'batch_id'           => $batchId,
                'event_type'         => 'EMAIL_REQUEST',
                'outlook_record_uid' => $outlookUid,
                'subject'            => $subject,
                'details_json'       => json_encode(['added' => $added, 'skipped' => $skipped, 'package' => $pkg, 'needs_review' => $needsReview], JSON_UNESCAPED_UNICODE),
                'created_at'         => $now,
            ]);
        }

        return ['ok' => true, 'batch_id' => $batchId, 'batch_created' => $batchCreated, 'added' => $added, 'skipped' => $skipped, 'package' => $pkg, 'package_needs_review' => $needsReview];
    }

    /** Extracts rows from the top unquoted HTML table (handling banner rows above headers) or tab-delimited text. */
    public function parseHtmlRequestTable(string $input, ?array $custom = null): array
    {
        $unquoted = self::stripQuotedEmailHtml($input);
        $rows     = $this->extractHtmlTables($unquoted, $custom);
        if (! $rows && $unquoted !== $input) {
            $rows = $this->extractHtmlTables($input, $custom);
        }
        if ($rows) {
            return $rows;
        }

        // Plain-text / TSV paste fallback (e.g. copy-paste directly from Outlook table!)
        $lines = preg_split('/\r?\n/', trim($input)) ?: [];
        if (count($lines) >= 2) {
            $firstCols = preg_split('/\t+/', trim($lines[0])) ?: [];
            $colIdx    = $this->mapHeaderIndexes($firstCols, $custom);
            $start     = (isset($colIdx['mtl_no']) || isset($colIdx['prod_code'])) ? 1 : 0;
            for ($i = $start; $i < count($lines); $i++) {
                $parts = preg_split('/\t+/', trim($lines[$i])) ?: [];
                if (count($parts) < 2) {
                    continue;
                }
                if ($start === 1) {
                    $rows[] = self::buildRequestRow($parts, $colIdx, $firstCols);
                } else {
                    $joined = implode(' ', $parts);
                    preg_match('/\b(VSP[A-Z0-9-]+)\b/i', $joined, $pm);
                    preg_match('/\b(VN[A-Z0-9]{9})\b/i', $joined, $mm);
                    if ($pm || $mm) {
                        $rows[] = [
                            'factory' => 'DTP', 'prod_code' => $pm[1] ?? '', 'style_no' => '',
                            'mtl_no'  => $mm[1] ?? '', 'colorway_c' => '', 'state' => 'Need to test', 'tr_no' => '', 'crd' => '',
                        ];
                    }
                }
            }
        }

        return $rows;
    }

    /** one request row from a table line: every mapped field, plus ALL columns (header => value) kept as extra data */
    private static function buildRequestRow(array $vals, array $colIdx, array $hdr): array
    {
        $row = [];
        foreach (array_keys(self::REQUEST_FIELDS) as $f) {
            $row[$f] = isset($colIdx[$f]) ? trim((string) ($vals[$colIdx[$f]] ?? '')) : '';
        }
        $row['factory'] = $row['factory'] !== '' ? $row['factory'] : 'DTP';
        $row['state']   = $row['state'] !== '' ? $row['state'] : 'Need to test';
        $extra = [];
        foreach ($hdr as $i => $h) {
            $h = trim((string) $h);
            $v = trim((string) ($vals[$i] ?? ''));
            if ($h === '' || $v === '') {
                continue;
            }
            $key = $h;
            for ($n = 2; isset($extra[$key]); $n++) {
                $key = $h . ' (' . $n . ')';
            }
            $extra[$key] = mb_substr($v, 0, 500);
        }
        $row['_extra'] = $extra;

        return $row;
    }

    /** header names, the mapped columns and a few sample rows of the first request table in a pasted / received mail (for the source editor) */
    public function previewRequestTable(string $input, ?array $custom): array
    {
        $unquoted = self::stripQuotedEmailHtml($input);
        foreach ([$unquoted, $input] as $html) {
            if (stripos($html, '<tr') === false) {
                continue;
            }
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($dom->getElementsByTagName('table') as $tbl) {
                $trs = $tbl->getElementsByTagName('tr');
                for ($r = 0; $r < min(4, $trs->length); $r++) {
                    $hdr = [];
                    foreach ($trs->item($r)->childNodes as $cell) {
                        if ($cell->nodeType === XML_ELEMENT_NODE) {
                            $hdr[] = trim(preg_replace('/\s+/', ' ', $cell->textContent));
                        }
                    }
                    $idx = $this->mapHeaderIndexes($hdr, $custom);
                    if (isset($idx['mtl_no']) || isset($idx['prod_code'])) {
                        $mapped = [];
                        foreach ($idx as $f => $i) {
                            $mapped[$f] = $hdr[$i] ?? '';
                        }

                        return ['ok' => true, 'headers' => $hdr, 'mapped' => $mapped, 'rows' => array_slice($this->parseHtmlRequestTable($html, $custom), 0, 5)];
                    }
                    if ($r === 0 || $r === 1) {
                        $firstHdr ??= $hdr;
                    }
                }
            }
        }

        return ['ok' => false, 'headers' => $firstHdr ?? [], 'mapped' => [], 'rows' => [], 'error' => 'No table with a Prod Code or MTL# column was found. Map those columns below.'];
    }

    private function extractHtmlTables(string $html, ?array $custom = null): array
    {
        if (stripos($html, '<tr') === false) {
            return [];
        }
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ($dom->getElementsByTagName('table') as $tbl) {
            $trs = $tbl->getElementsByTagName('tr');
            if ($trs->length < 2) {
                continue;
            }
            // Scan rows 0..3 so banner/title rows above column headers do not break parsing
            $hdrRow = -1;
            $colIdx = [];
            for ($r = 0; $r < min(4, $trs->length - 1); $r++) {
                $hdr = [];
                foreach ($trs->item($r)->childNodes as $cell) {
                    if ($cell->nodeType === XML_ELEMENT_NODE) {
                        $hdr[] = trim(preg_replace('/\s+/', ' ', $cell->textContent));
                    }
                }
                $idx = $this->mapHeaderIndexes($hdr, $custom);
                if (isset($idx['mtl_no']) || isset($idx['prod_code'])) {
                    $hdrRow = $r;
                    $colIdx = $idx;
                    $hdrNames = $hdr;
                    break;
                }
            }
            if ($hdrRow < 0) {
                continue;
            }
            $rows = [];
            for ($i = $hdrRow + 1; $i < $trs->length; $i++) {
                $vals = [];
                foreach ($trs->item($i)->childNodes as $cell) {
                    if ($cell->nodeType === XML_ELEMENT_NODE) {
                        $vals[] = trim(preg_replace('/\s+/', ' ', $cell->textContent));
                    }
                }
                if (! $vals) {
                    continue;
                }
                $rows[] = self::buildRequestRow($vals, $colIdx, $hdrNames ?? []);
            }
            if ($rows) {
                return $rows;
            }
        }

        return [];
    }

    private function mapHeaderIndexes(array $hdr, ?array $custom = null): array
    {
        $map = [];
        // 1) the source's own column names (field => [header text, ...]); "=Text" must match the whole header, otherwise a part of it is enough
        foreach (($custom ?: []) as $field => $aliases) {
            if (! isset(self::REQUEST_FIELDS[$field])) {
                continue;
            }
            foreach ((array) $aliases as $al) {
                $al = trim((string) $al);
                if ($al === '') {
                    continue;
                }
                $exact = $al[0] === '=';
                $needle = mb_strtolower(ltrim($al, '='));
                foreach ($hdr as $i => $h) {
                    $hl = mb_strtolower(trim((string) $h));
                    if (! in_array($i, $map, true) && ($exact ? $hl === $needle : str_contains($hl, $needle))) {
                        $map[$field] = $i;
                        break 2;
                    }
                }
            }
        }
        // 2) the built-in names for whatever the source did not name
        $taken = array_values($map);
        $named = array_keys($map);   // fields the source named stay as the source set them
        foreach ($hdr as $i => $h) {
            if (in_array($i, $taken, true)) {
                continue;
            }
            $l = strtolower(trim($h));
            $f = null;
            if ($l === 'factory') {
                $f = 'factory';
            } elseif (str_contains($h, '指令') || str_contains($l, 'prod')) {
                $f = 'prod_code';
            } elseif (str_contains($h, '型體') || str_contains($l, 'style')) {
                $f = 'style_no';
            } elseif (str_contains($l, 'mtl')) {
                $f = 'mtl_no';
            } elseif (str_contains($h, '顏色') || str_contains($l, 'color')) {
                $f = 'colorway_c';
            } elseif ($l === 'state' || $l === 'status') {
                $f = 'state';
            } elseif (str_contains($l, 'tr no') || $l === 'tr#') {
                $f = 'tr_no';
            } elseif (str_contains($h, '交期') || str_contains($l, 'crd')) {
                $f = 'crd';
            }
            if ($f !== null && ! in_array($f, $named, true)) {
                $map[$f] = $i;   // as before: with two matching headers the later one wins
            }
        }

        return $map;
    }

    private static function parseSafeDate(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        // Prefer unambiguous ISO-first YYYY-M-D / YYYY/M/D formatting
        if (preg_match('/\b(20\d{2})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/', $s, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        $ts = strtotime(str_replace('/', '-', $s));

        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function applyTransitEmail(array $em): int
    {
        $db      = $this->db();
        $rawBody = (string) ($em['body_text'] !== '' ? $em['body_text'] : strip_tags(self::stripQuotedEmailHtml((string) $em['body_html'])));
        $topBody = self::stripQuotedEmailText($rawBody);
        $text    = $em['subject'] . "\n" . $topBody;

        $container = '';
        if (preg_match('/(?:container(?:\s*no\.?)?|柜号|貨櫃號?)\s*[:：#\-\s]*([A-Z]{4}\d{7}|(?=[A-Z-]*\d)[A-Z0-9-]{5,22})\b/i', $text, $cm)) {
            $container = strtoupper(trim($cm[1]));
        } elseif (preg_match('/\b([A-Z]{4}\d{7})\b/', $text, $cm2)) {
            $container = $cm2[1];
        }
        if ($container === '') {
            return 0;
        }

        preg_match_all('/\b(VN[A-Z0-9]{9})\b/i', $text, $mtls);
        preg_match_all('/\b(VSP[A-Z0-9-]+)\b/i', $text, $prods);
        preg_match_all('/\b(A\d{8,12})\b/i', $text, $apps);

        $pkgHint = self::detectExplicitPackage((string) $em['subject']);
        $targets = $this->findMatchingRequests($mtls[1] ?? [], $prods[1] ?? [], $apps[1] ?? [], ['SUBMITTED_INTERLINK', 'EMAIL_SENT', 'IN_TRANSIT', 'REQUESTED'], $pkgHint);
        $now = date('Y-m-d H:i:s');
        $cnt = 0;
        foreach ($targets as $t) {
            $upd = ['container_no' => $container, 'transit_status' => 'IN_TRANSIT', 'transit_updated_at' => $now, 'updated_at' => $now];
            if (! in_array($t['stage'], ['LAB_RECEIVED', 'COMPLETED'], true)) {
                $upd['stage'] = 'IN_TRANSIT';
            }
            $db->table('tbl_vf_test_requests')->where('id', $t['id'])->update($upd);
            $cnt++;
        }
        if ($cnt > 0) {
            $db->table('tbl_vf_test_events')->insert([
                'event_type'         => 'EMAIL_IN_TRANSIT',
                'outlook_record_uid' => (string) $em['record_uid'],
                'subject'            => (string) $em['subject'],
                'details_json'       => json_encode(['container_no' => $container, 'updated' => $cnt], JSON_UNESCAPED_UNICODE),
                'created_at'         => $now,
            ]);
        }

        return $cnt;
    }

    private function applyLabReceivedEmail(array $em): int
    {
        $db   = $this->db();
        $html = (string) $em['body_html'];
        $text = (string) $em['body_text'];
        $lab  = str_contains(strtolower((string) $em['sender_email']), 'kotiti') || stripos($text . ' ' . $em['subject'], 'kotiti') !== false ? 'KOTITI' : 'SGS';
        $pairs = [];

        if (stripos($html, '<tr') !== false) {
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($dom->getElementsByTagName('table') as $tbl) {
                $trs = $tbl->getElementsByTagName('tr');
                if ($trs->length < 2) {
                    continue;
                }
                $hdr = [];
                foreach ($trs->item(0)->childNodes as $c) {
                    if ($c->nodeType === XML_ELEMENT_NODE) {
                        $hdr[] = strtolower(trim(preg_replace('/\s+/', ' ', $c->textContent)));
                    }
                }
                $iRep = $iRef = $iDue = -1;
                foreach ($hdr as $i => $h) {
                    if (str_contains($h, 'report no') || str_contains($h, '报告号')) $iRep = $i;
                    elseif (str_contains($h, 'customer reference') || str_contains($h, '客户参考') || str_contains($h, 'mtl')) $iRef = $i;
                    elseif (str_contains($h, 'due date') || str_contains($h, '预计')) $iDue = $i;
                }
                if ($iRep < 0) {
                    continue;
                }
                for ($r = 1; $r < $trs->length; $r++) {
                    $vals = [];
                    foreach ($trs->item($r)->childNodes as $c) {
                        if ($c->nodeType === XML_ELEMENT_NODE) {
                            $vals[] = trim(preg_replace('/\s+/', ' ', $c->textContent));
                        }
                    }
                    $rep = trim((string) ($vals[$iRep] ?? ''));
                    if ($rep === '') {
                        continue;
                    }
                    $pairs[] = [
                        'report_no' => $rep,
                        'ref'       => trim((string) ($vals[$iRef] ?? '')),
                        'due_date'  => trim((string) ($vals[$iDue] ?? '')),
                    ];
                }
            }
        }

        $pkgHint = self::detectExplicitPackage((string) $em['subject']);
        $now = date('Y-m-d H:i:s');
        $cnt = 0;
        foreach ($pairs as $pr) {
            preg_match_all('/\b(VN[A-Z0-9]{9})\b/i', $pr['ref'] . ' ' . $em['subject'], $mtls);
            preg_match_all('/\b(A\d{8,12})\b/i', $pr['ref'] . ' ' . $em['subject'], $apps);
            $targets = $this->findMatchingRequests($mtls[1] ?? [], [], $apps[1] ?? [], ['SUBMITTED_INTERLINK', 'EMAIL_SENT', 'IN_TRANSIT', 'LAB_RECEIVED'], $pkgHint);
            $dueDate = self::parseSafeDate($pr['due_date']);
            foreach ($targets as $t) {
                $upd = [
                    'lab_name'          => $lab,
                    'lab_report_no'     => $pr['report_no'],
                    'lab_order_no'      => $pr['report_no'],
                    'expected_due_date' => $dueDate,
                    'lab_received_at'   => $now,
                    'updated_at'        => $now,
                ];
                if ($t['stage'] !== 'COMPLETED') {
                    $upd['stage'] = 'LAB_RECEIVED';
                }
                $db->table('tbl_vf_test_requests')->where('id', $t['id'])->update($upd);
                $cnt++;
            }
        }
        if ($cnt > 0) {
            $db->table('tbl_vf_test_events')->insert([
                'event_type'         => 'EMAIL_LAB_RECEIVED',
                'outlook_record_uid' => (string) $em['record_uid'],
                'subject'            => (string) $em['subject'],
                'details_json'       => json_encode(['lab' => $lab, 'pairs' => $pairs, 'updated' => $cnt], JSON_UNESCAPED_UNICODE),
                'created_at'         => $now,
            ]);
        }

        return $cnt;
    }

    /** Extracts readable text from a stored PDF (PyMuPDF when present, plus ASCII/zlib stream fallback). */
    /** Final lab report number printed on a certificate: SGS GZT19-002946-01, HTK HKT23-045842-01 / HKGT05409230, KOTITI 8221-123-45, VF 20251VF01789. */
    public static function extractFinalReportNo(string $text): string
    {
        $pats = ['/\b(GZT\d{2}-\d{5,7}-\d{2})\b/i', '/\b(HKT\d{2}-\d{5,7}-\d{2})\b/i', '/\b(HKGT\d{6,10})\b/i', '/\b(822\d-\d+-\d+)\b/', '/\b(\d{4,5}VF\d{4,6})\b/i'];
        foreach ($pats as $p) {
            if (preg_match($p, $text, $m)) {
                return strtoupper($m[1]);
            }
        }

        return '';
    }

    public static function extractPdfText(string $pdfPath): string
    {
        if (! is_file($pdfPath)) {
            return '';
        }
        $cmd = 'python -c "import sys,pymupdf;doc=pymupdf.open(sys.argv[1]);print(\'\n\'.join(p.get_text() for p in doc[:6]))" ' . escapeshellarg($pdfPath) . ' 2>NUL';
        $out = @shell_exec($cmd);
        if (is_string($out) && trim($out) !== '') {
            return trim($out);
        }

        // Pure-PHP fallback: inspect uncompressed streams / zlib deflate objects
        $raw = @file_get_contents($pdfPath);
        if (! is_string($raw) || $raw === '') {
            return '';
        }
        $buf = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $ms)) {
            foreach (array_slice($ms[1], 0, 12) as $st) {
                $dec = @gzuncompress($st);
                if ($dec === false) {
                    $dec = @gzinflate($st);
                }
                $src = is_string($dec) ? $dec : $st;
                if (preg_match_all('/\(([^()]{2,200})\)/', $src, $ts)) {
                    $buf .= ' ' . implode(' ', $ts[1]);
                }
            }
        }

        return trim($buf);
    }

    /** Determines certificate conclusion from actual PDF text (never assumes PASS from an empty email body). */
    public static function evaluatePdfResult(string $pdfText, string $emailText = ''): string
    {
        $pdfText = trim($pdfText);
        if ($pdfText !== '') {
            // Look for explicit FAIL summary/conclusion inside the report PDF first
            if (preg_match('/\b(?:OVERALL\s+(?:RESULT|RATING|CONCLUSION)\s*[:\-]?\s*FAIL(?:ED)?|TEST\s+RESULT\s*[:\-]?\s*FAIL(?:ED)?|UNSATISFACTORY|REJECTED)\b/i', $pdfText)) {
                return 'FAILED';
            }
            $hasFail = preg_match('/\bFAIL(?:ED)?\b/i', $pdfText) === 1;
            $hasPass = preg_match('/\bPASS(?:ED)?\b/i', $pdfText) === 1;
            if ($hasFail && ! $hasPass) {
                return 'FAILED';
            }
            if ($hasPass && ! $hasFail) {
                return 'PASS';
            }
            if ($hasFail && $hasPass) {
                // Report contains both words (e.g. a Pass/Fail legend table or a failing sub-test) -> require explicit Overall Pass or flag review
                if (preg_match('/\b(?:OVERALL\s+(?:RESULT|RATING|CONCLUSION)|CONCLUSION)\s*[:\-]?\s*PASS(?:ED)?\b/i', $pdfText)) {
                    return 'PASS';
                }

                return 'PENDING_REVIEW';
            }
        }
        // Without readable PDF PASS/FAIL proof, check subject-only explicit FAIL/PASS, otherwise require manual review
        if (preg_match('/\bFAIL(?:ED)?\b/i', $emailText)) {
            return 'FAILED';
        }

        return 'PENDING_REVIEW';
    }

    private function applyCertificateEmail(array $em, array $pdfAtts, OutlookSyncReader $reader): int
    {
        $db   = $this->db();
        $text = $em['subject'] . "\n" . ($em['body_text'] ?: strip_tags((string) $em['body_html']));
        $pkgHint = self::detectExplicitPackage((string) $em['subject']);
        $now  = date('Y-m-d H:i:s');
        $saved = 0;

        foreach ($pdfAtts as $att) {
            $origName = (string) ($att['OriginalFileName'] ?? $att['FileName'] ?? $att['file_name'] ?? 'certificate.pdf');
            $bytes    = $reader->readAttachmentBytes($att);
            if ($bytes === null || $bytes === '') {
                continue;
            }
            $tmpPdf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vf_cert_' . bin2hex(random_bytes(6)) . '.pdf';
            @file_put_contents($tmpPdf, $bytes);
            $pdfText = self::extractPdfText($tmpPdf);
            @unlink($tmpPdf);

            $haystack = $origName . ' ' . $text . "\n" . $pdfText;
            // two different numbers: the SGS order no (SL...FW, from the lab-received e-mail) and the final report no printed on the certificate
            $orderNo = preg_match('/\b(SL\d{10,}FW)\b/i', $haystack, $om) ? strtoupper($om[1]) : '';
            $finalNo = self::extractFinalReportNo($haystack);
            $repNo   = $finalNo !== '' ? $finalNo : $orderNo;
            preg_match_all('/\b(VN[A-Z0-9]{9})\b/i', $haystack, $mtls);
            preg_match_all('/\b(A\d{8,12})\b/i', $haystack, $apps);

            $targets = [];
            $keys = array_values(array_filter([$orderNo, $finalNo]));
            if ($keys) {
                $targets = $db->table('tbl_vf_test_requests')->groupStart()->whereIn('lab_report_no', $keys)->orWhereIn('lab_order_no', $keys)->groupEnd()->where('stage !=', 'HISTORY')->orderBy('id', 'DESC')->get()->getResultArray();
            }
            if (! $targets) {
                $targets = $this->findMatchingRequests($mtls[1] ?? [], [], $apps[1] ?? [], ['SUBMITTED_INTERLINK', 'EMAIL_SENT', 'IN_TRANSIT', 'LAB_RECEIVED'], $pkgHint);
            }
            if (! $targets) {
                continue;
            }

            $issue  = ! empty($em['received_at']) && ($ts = strtotime((string) $em['received_at'])) ? date('Y-m-d', $ts) : date('Y-m-d');
            $result = self::evaluatePdfResult($pdfText, (string) $em['subject']);

            foreach ($targets as $t) {
                $folderKey = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($t['test_type_short'] ?: 'VF'));
                $dir       = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'vf_certificates' . DIRECTORY_SEPARATOR . $folderKey . DIRECTORY_SEPARATOR;
                if (! is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $safeFile = preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', '_', $origName);
                @file_put_contents($dir . $safeFile, $bytes);

                $db->table('tbl_vf_test_requests')->where('id', $t['id'])->update([
                    'lab_report_no'        => $finalNo !== '' ? $finalNo : ($t['lab_report_no'] ?: $repNo),
                    'lab_order_no'         => $t['lab_order_no'] ?: $orderNo,
                    'test_result'          => $result,
                    'issue_date'           => $issue,
                    'validity_date'        => date('Y-m-d', strtotime($issue . ' +' . max(1, (int) SystemSettings::get('vf.validity_months', '12')) . ' months')),
                    'certificate_pdf_name' => $safeFile,
                    'certificate_pdf_path' => $dir . $safeFile,
                    'completed_at'         => $now,
                    'stage'                => 'COMPLETED',
                    'updated_at'           => $now,
                ]);
                $saved++;
            }
        }
        if ($saved > 0) {
            $db->table('tbl_vf_test_events')->insert([
                'event_type'         => 'EMAIL_COMPLETED',
                'outlook_record_uid' => (string) $em['record_uid'],
                'subject'            => (string) $em['subject'],
                'details_json'       => json_encode(['certificates_saved' => $saved], JSON_UNESCAPED_UNICODE),
                'created_at'         => $now,
            ]);
        }

        return $saved;
    }

    private function findMatchingRequests(array $mtls, array $prods, array $apps, array $allowedStages = [], ?string $packageHint = null): array
    {
        $db    = $this->db();
        $mtls  = array_values(array_unique(array_map('strtoupper', array_filter($mtls))));
        $prods = array_values(array_unique(array_map('strtoupper', array_filter($prods))));
        $apps  = array_values(array_unique(array_map('strtoupper', array_filter($apps))));
        if (! $mtls && ! $prods && ! $apps) {
            return [];
        }
        $qb = $db->table('tbl_vf_test_requests')->groupStart();
        $first = true;
        foreach (['generated_application_no' => $apps, 'prod_code' => $prods, 'mtl_no' => $mtls] as $col => $vals) {
            if ($vals) {
                $first ? $qb->whereIn($col, $vals) : $qb->orWhereIn($col, $vals);
                $first = false;
            }
        }
        $qb->groupEnd();
        if ($allowedStages) {
            $qb->whereIn('stage', $allowedStages);
        }
        if ($packageHint !== null && $packageHint !== '') {
            $qb->where('test_package', $packageHint);
        }

        return $qb->orderBy('id', 'DESC')->get(50)->getResultArray();
    }
}
