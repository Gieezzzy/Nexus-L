<?php

namespace App\Controllers;

/** Third Party Test: reports from outside laboratories / compliance documents, one page per type (listed in the sidebar). */
class ThirdPartyController extends BaseController
{
    public const TYPES = [
        'qima'   => ['QIMA', 'QIMA inspection and test reports'],
        'cfa'    => ['CFA', 'CFA reports'],
        'rsl'    => ['RSL', 'Restricted Substances List test reports'],
        'kc'     => ['KC Test', 'KC (Korea Certification) test reports'],
        'cpsia'  => ['CPSIA', 'CPSIA (US consumer product safety) test reports'],
        'gb'     => ['GB', 'GB (China national standard) test reports'],
        'coc'    => ['COC', 'Certificates of Conformity'],
        'bom'    => ['BOM', 'Bill of Materials documents'],
    ];

    private const IMAGE_DIR   = 'D:\\BACK UP\\OUTDATA\\QIMA image\\';
    private const REPORTS_DIR = '\\\\192.168.32.130\\qc lab files\\#Laboratory Monitoring\\#Encoding\\Monitoring\\CFA and QIMA Trackers\\#QIMA REPORTS\\';

    /** GET thirdparty/qima-image?mtl= : the QIMA photo kept as <MTL #>.png in D:\BACK UP\OUTDATA\QIMA image */
    public function qimaImage()
    {
        $mtl = trim((string) ($this->request->getGet('mtl') ?? ''));
        if (! preg_match('/^[A-Za-z0-9_\-]{3,40}$/', $mtl)) {
            return $this->response->setStatusCode(404);
        }
        // the QIMA image folder first, then the order's own folder on the lab share (<MONTH YEAR>\<MTL #>\)
        $dirs = [self::IMAGE_DIR];
        try {
            $month = (string) (\Config\Database::connect()->table('tbl_qima')->select('ReportMonth')->where('MTL', $mtl)->orderBy('ID', 'DESC')->get(1)->getRow('ReportMonth') ?? '');
            $month = trim((string) preg_replace('/[^A-Za-z0-9 ]/', '', $month));
            if ($month !== '') {
                $dirs[] = self::REPORTS_DIR . $month . '\\' . $mtl . '\\';
            }
        } catch (\Throwable $e) {
        }
        foreach ($dirs as $dir) {
            foreach (['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'] as $ext => $mime) {
                $file = $dir . $mtl . '.' . $ext;
                if (is_file($file)) {
                    return $this->response->setHeader('Content-Type', $mime)->setHeader('Cache-Control', 'private, max-age=86400')->setBody((string) file_get_contents($file));
                }
            }
        }

        return $this->response->setStatusCode(404);
    }

    /**
     * POST thirdparty/qima-import (multipart "file"): a QIMA schedule workbook. Every row is saved in tbl_qima (an order already there,
     * same MTL # and Production Code, is updated), and the pictures inside the workbook are saved as <MTL #>.<ext> in the QIMA image folder.
     */
    public function qimaImport()
    {
        $fail = fn (string $msg, int $code = 422) => $this->response->setStatusCode($code)->setJSON(['ok' => false, 'message' => $msg, 'csrf' => csrf_hash()]);

        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return $fail('Choose an Excel file (.xlsx) to upload.');
        }
        if (strtolower($file->getClientExtension()) !== 'xlsx' || $file->getSize() > 30 * 1024 * 1024) {
            return $fail('Upload the QIMA schedule as an .xlsx file of up to 30 MB.');
        }

        $read = (new \App\Libraries\QimaImporter())->read($file->getTempName());
        if (! empty($read['error'])) {
            return $fail($read['error']);
        }

        $db      = \Config\Database::connect();
        $columns = $db->getFieldNames('tbl_qima');
        $added   = 0;
        $updated = 0;
        foreach ($read['rows'] as $row) {
            $data = array_filter(array_intersect_key($row, array_flip($columns)), static fn ($v) => $v !== null && $v !== '');
            $q    = $db->table('tbl_qima')->select('ID')->where('MTL', $row['MTL']);
            if (! empty($row['ProductionCode'])) {
                $q->where('ProductionCode', $row['ProductionCode']);
            }
            $existing = $q->get()->getRowArray();
            if ($existing) {
                $db->table('tbl_qima')->where('ID', (int) $existing['ID'])->update($data);
                $updated++;
            } else {
                $db->table('tbl_qima')->insert($data);
                $added++;
            }
        }

        // pictures: written straight to <share>\<MONTH YEAR>\<MTL #>\<MTL #>.<ext>; only when the share cannot be written
        // are they kept in the local QIMA image folder (thumbnails still work from there)
        $dir = self::IMAGE_DIR;
        $pictures = 0;
        $moved    = 0;
        $folders  = 0;
        $problems = [];
        if ($err = $this->connectShare()) {
            $problems['share'] = $err;
        }
        foreach ($read['rows'] as $row) {
            $mtl = $row['MTL'];
            // <share>\<MONTH YEAR>\<MTL #>\ : the month folder is named after the title of the workbook, one sub-folder per MTL #
            $month  = trim((string) preg_replace('/[^A-Za-z0-9 ]/', '', (string) ($row['ReportMonth'] ?? '')));
            $target = self::REPORTS_DIR . $month . '\\' . $mtl;
            if ($month !== '' && ! is_dir($target)) {
                if (@mkdir($target, 0775, true)) {
                    $folders++;
                } else {
                    $problems[$month] = 'The folder ' . self::REPORTS_DIR . $month . ' could not be created (check the web server\'s access to the share).';
                }
            }
            $img = $read['images'][$mtl] ?? null;
            if (! $img) {
                continue;
            }
            if ($month !== '' && is_dir($target)) {
                $dest = $target . '\\' . $mtl . '.' . $img['ext'];
                if (@file_put_contents($dest, $img['data']) !== false) {
                    $pictures++;
                    $moved++;
                    continue;
                }
            }
            // share not reachable: keep a local copy so the thumbnail still shows
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (@file_put_contents($dir . $mtl . '.' . $img['ext'], $img['data']) !== false) {
                $pictures++;
                $problems['move' . $mtl] = "Picture(s) could not be saved to the share; kept in the local QIMA image folder.";
            }
        }

        $msg = "QIMA schedule imported: {$added} new, {$updated} updated, {$pictures} picture(s) saved";
        $msg .= $folders ? ", {$folders} new MTL folder(s) created, {$moved} picture(s) moved into them." : '.';
        $noPic = array_values(array_filter(array_map(static fn ($r) => isset($read['images'][$r['MTL']]) ? null : $r['MTL'], $read['rows'])));
        if ($noPic) {
            $msg .= ' No picture for: ' . implode(', ', array_slice($noPic, 0, 8)) . ' (the file holds ' . ($read['picturesInFile'] ?? 0) . ' picture(s), ' . ($read['unmatchedPictures'] ?? 0) . ' not matched to a row).';
        }
        if ($problems) {
            $msg .= ' Note: ' . implode(' ', array_slice(array_values($problems), 0, 3));
        }

        return $this->response->setJSON(['ok' => true, 'message' => $msg, 'csrf' => csrf_hash()]);
    }

    /**
     * Signs in to the lab share with the account kept in .env (qima.share.user / qima.share.password), because the web server's own
     * Windows account has no rights there. Returns an error text, or '' when the share is usable.
     */
    private function connectShare(): string
    {
        $share = '\\\\192.168.32.130\\qc lab files';
        $user  = (string) env('qima.share.user', '');
        $pass  = (string) env('qima.share.password', '');
        if ($user === '') {
            return is_dir($share) ? '' : 'The lab share needs a login: set qima.share.user and qima.share.password in .env.';
        }
        $p = @proc_open(['net', 'use', $share, '/user:' . $user, $pass, '/persistent:no'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($p)) {
            return 'Could not sign in to the lab share.';
        }
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $rc  = proc_close($p);
        // 1219 = already connected with other credentials: fine if the share is reachable
        if ($rc !== 0 && ! is_dir($share)) {
            return 'Sign-in to the lab share failed: ' . trim(preg_replace('/\s+/', ' ', $out));
        }

        return '';
    }

    public function index(string $type = 'qima'): string
    {
        $type = strtolower($type);
        if (! isset(self::TYPES[$type])) {
            $type = 'qima';
        }

        // QIMA: the booked / audited orders kept in tbl_qima (MTL #, Production Code, PO, Style, Colorway, CRD, Pairs)
        $orders = [];
        if ($type === 'qima') {
            try {
                $db     = \Config\Database::connect();
                $orders = $db->table('tbl_qima')
                    ->select('ID AS id, MTL AS mtl_no, ProductionCode AS prod_code, PONo AS po_no, Style AS style_no, Colorway AS colorway, CRD AS crd, Pairs AS pairs')
                    ->orderBy('QimaAuditDate', 'DESC')->orderBy('ID', 'DESC')->limit(3000)->get()->getResultArray();

                // Result / Status come from the lab's own tests of the same MTL # (the unified Lab Ref list of every material test):
                // worst result wins (FAIL > TOLERABLE > PASS); a test with no result yet = Ongoing Testing; no test at all = Pending
                $mtls = array_values(array_unique(array_filter(array_column($orders, 'mtl_no'))));
                $byMtl = [];
                if ($mtls && $db->tableExists('tbl_dtr_unified_labrefs')) {
                    foreach ($db->table('tbl_dtr_unified_labrefs')->select('mtl_no, overall_result')->whereIn('mtl_no', $mtls)->get()->getResultArray() as $t) {
                        $k = strtoupper(trim((string) $t['mtl_no']));
                        $r = strtoupper(trim((string) ($t['overall_result'] ?? '')));
                        $r = in_array($r, ['FAILED', 'FAIL', 'ERROR'], true) ? 'FAIL' : $r;
                        $byMtl[$k]['tests'] = ($byMtl[$k]['tests'] ?? 0) + 1;
                        if (in_array($r, ['FAIL', 'TOLERABLE', 'PASS'], true)) {
                            $rank = ['PASS' => 1, 'TOLERABLE' => 2, 'FAIL' => 3];
                            if (($rank[$r]) > ($rank[$byMtl[$k]['result'] ?? ''] ?? 0)) {
                                $byMtl[$k]['result'] = $r;
                            }
                        }
                    }
                }
                foreach ($orders as &$o) {
                    $k           = strtoupper(trim((string) $o['mtl_no']));
                    $o['result'] = $byMtl[$k]['result'] ?? '';
                    $o['tests']  = $byMtl[$k]['tests'] ?? 0;
                }
                unset($o);
            } catch (\Throwable $e) {
                $orders = [];
            }
        }

        return $this->render('thirdparty/index', [
            'title'  => 'Third Party Test - ' . self::TYPES[$type][0],
            'type'   => $type,
            'types'  => self::TYPES,
            'orders' => $orders,
        ]);
    }
}
