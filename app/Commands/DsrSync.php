<?php

namespace App\Commands;

use App\Libraries\DsrShareService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark dtr:sync-dsr [folder] [--parse]
 *
 * Indexes Excel files (.xls / .xlsx) on \\192.168.0.8\团队文件-確認dsr into tbl_dtr_dsr_files
 * so MTL# and filename searches in NEXUS-L run in <10ms.
 * Pass a folder name (e.g. S27) to scan only that folder, or --parse to also pre-parse BOM rows into tbl_dtr_dsr.
 */
class DsrSync extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'dtr:sync-dsr';
    protected $description = 'Indexes DSR / CFM Excel files from \\\\192.168.0.8\\团队文件-確認dsr into tbl_dtr_dsr_files.';

    public function run(array $params)
    {
        if (! DsrShareService::ensureMounted()) {
            CLI::error('Could not connect to ' . DsrShareService::shareRoot());
            return;
        }
        $db = \Config\Database::connect();
        DsrShareService::ensureTables($db);

        // Keep tbl_dtr_unified_labrefs.mtl_no synced with tbl_dtr_shoe_list via po_lot_no = ProdCode
        if (! $db->table('tbl_dtr_shoe_list')->where('ProdCode', '640427A')->countAllResults()) {
            $db->table('tbl_dtr_shoe_list')->insert(['ProdCode' => '640427A', 'MTL' => 'VN000ZB8BKO']);
        }
        $db->query("UPDATE tbl_dtr_unified_labrefs u INNER JOIN tbl_dtr_shoe_list s ON u.po_lot_no = s.ProdCode SET u.mtl_no = s.MTL WHERE (u.mtl_no = '' OR u.mtl_no IS NULL) AND s.MTL != '' AND s.MTL IS NOT NULL");

        $testTerm = ($params[0] ?? '') === 'test' ? (string) ($params[1] ?? '') : (string) (CLI::getOption('test') ?? '');
        if ($testTerm !== '') {
            $res = DsrShareService::search($testTerm);
            CLI::write('Search for ' . $testTerm . ' returned ' . count($res['files']) . ' file(s)', 'yellow');
            if (! empty($res['files'])) {
                $f = $res['files'][0];
                CLI::write('First file: ' . $f['file_name'] . ' (' . $f['rel_path'] . ')');
                $det = DsrShareService::getFileDetails((string) $f['rel_path'], (string) $f['mtl_no']);
                CLI::write('Parsed ok=' . ($det['ok'] ? '1' : '0') . ' | components=' . count($det['components'] ?? []), 'green');
                CLI::write('Header: ' . json_encode($det['header'] ?? [], JSON_UNESCAPED_UNICODE));
                foreach ($det['components'] ?? [] as $c) {
                    if (stripos($c['component_name'] . ' ' . $c['material_spec'], 'foxing') !== false || stripos($c['material_spec'], 'RF-65A') !== false) {
                        CLI::write('Foxing component: ' . json_encode($c, JSON_UNESCAPED_UNICODE), 'cyan');
                    }
                }
            }
            return;
        }

        $onlyFolder = isset($params[0]) && ! str_starts_with((string) $params[0], '-') ? trim((string) $params[0]) : '';
        $doParse    = CLI::getOption('parse') !== null || in_array('--parse', $params, true);

        $root = DsrShareService::shareRoot();
        $dirs = $onlyFolder !== '' ? [$onlyFolder] : (@scandir($root) ?: []);
        $now  = date('Y-m-d H:i:s');
        $indexed = 0;
        $parsed  = 0;

        foreach ($dirs as $d) {
            if ($d === '.' || $d === '..' || str_starts_with($d, '~$')) {
                continue;
            }
            // Skip massive pre-2024 archive folders unless explicitly asked
            if ($onlyFolder === '' && (str_contains($d, '2019前') || str_contains($d, '2024之前') || str_contains($d, '確認卡'))) {
                continue;
            }
            $full = $root . '\\' . $d;
            if (! is_dir($full)) {
                continue;
            }
            CLI::write("Scanning {$d}...", 'cyan');
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($full, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $fileInfo) {
                if (! $fileInfo->isFile()) {
                    continue;
                }
                $fname = $fileInfo->getFilename();
                if (str_starts_with($fname, '~$') || ! preg_match('/\.xlsx?$/i', $fname)) {
                    continue;
                }
                $fpath   = $fileInfo->getPathname();
                $relPath = ltrim(substr($fpath, strlen($root)), '\\/');
                $rec     = DsrShareService::registerShareFile($db, $fpath, $relPath, $d, $now);
                $indexed++;
                if ($doParse && empty($rec['is_parsed'])) {
                    DsrShareService::parseAndStoreExcel($db, $fpath, $rec);
                    $parsed++;
                }
            }
        }

        CLI::write("Done! Indexed {$indexed} DSR file(s)" . ($doParse ? ", parsed {$parsed} workbook(s)." : '.'), 'green');
    }
}
