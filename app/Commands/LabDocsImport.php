<?php

namespace App\Commands;

use App\Libraries\LabDocXlsx;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark labdocs:import [#DTP-Lab-100] [--force]
 *
 * Reads a register folder of \\192.168.32.130\qc lab files\#Lab Documents into dbnexus (tbl_labdoc_*). Only "#" folders are read.
 * #DTP-Lab-100: the register workbook has two sheets (the long working list and the printed "DOCX" list); the printed list (the one in
 * the Word file, too) is the register, its logos and header are kept; the extra columns of the long list and the Blue Folder titles
 * are added as hidden columns, matched by Document ID and order. Nothing is written to the share.
 */
class LabDocsImport extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'labdocs:import';
    protected $description = 'Imports a "#" register folder of the Lab Documents into the database.';
    protected $usage       = 'labdocs:import [#folder-prefix] [--force]';

    private const ROOT = '\\\\192.168.32.130\\qc lab files\\#Lab Documents';

    public function run(array $params)
    {
        $prefix = $params[0] ?? '#DTP-Lab-100';
        $force  = CLI::getOption('force') !== null || in_array('--force', $params, true);
        if (! str_starts_with($prefix, '#')) {
            CLI::error('Only folders whose name starts with "#" are read.');
            return;
        }
        $folder = null;
        foreach (scandir(self::ROOT) ?: [] as $d) {
            if (str_starts_with($d, $prefix) && is_dir(self::ROOT . '\\' . $d)) {
                $folder = $d;
                break;
            }
        }
        if ($folder === null) {
            CLI::error("No folder starting with {$prefix} on the share.");
            return;
        }
        $dir   = self::ROOT . '\\' . $folder;
        $code  = preg_match('/^#(DTP-Lab-\d+)/i', $folder, $m) ? $m[1] : trim($folder, '#');
        $files = [];
        foreach (scandir($dir) ?: [] as $f) {
            if ($f !== '.' && $f !== '..' && is_file($dir . '\\' . $f) && ! str_starts_with($f, '~$')) {
                $files[] = ['name' => $f, 'size' => filesize($dir . '\\' . $f), 'modified' => date('Y-m-d H:i', filemtime($dir . '\\' . $f))];
            }
        }
        $main = null;
        $blue = null;
        foreach ($files as $f) {
            if (preg_match('/^' . preg_quote($code, '/') . '.*\.xlsx$/i', $f['name'])) {
                $main = $dir . '\\' . $f['name'];
            }
            if (stripos($f['name'], 'blue folder') !== false && str_ends_with(strtolower($f['name']), '.xlsx')) {
                $blue = $dir . '\\' . $f['name'];
            }
        }
        if ($main === null) {
            CLI::error('No register workbook (' . $code . '*.xlsx) in the folder.');
            return;
        }
        $db = \Config\Database::connect();
        if (! $db->tableExists('tbl_labdoc_registers')) {
            CLI::error('Run php spark migrate first.');
            return;
        }
        $exists = $db->table('tbl_labdoc_registers')->where('code', $code)->get()->getRowArray();
        if ($exists && ! $force) {
            CLI::write("{$code} is already in the database (use --force to read it again; the rows are replaced).", 'yellow');
            return;
        }

        // the printed list: the sheet whose Excel table has two columns
        $sheets = [];
        for ($i = 0; $i < 4; $i++) {
            try {
                $sheets[$i] = LabDocXlsx::read($main, $i);
            } catch (\Throwable) {
                break;
            }
        }
        $printed = null;
        $working = null;
        foreach ($sheets as $i => $s) {
            if (! preg_match('/^[A-Z]+(\d+):([A-Z]+)(\d+)$/', (string) $s['table_ref'], $mm)) {
                continue;
            }
            $w = LabDocXlsx::colIndex($mm[2]);
            if ($w === 2) {
                $printed = $i;
            } else {
                $working ??= $i;
            }
        }
        if ($printed === null) {
            CLI::error('The printed two-column list was not found in the workbook.');
            return;
        }
        $S = $sheets[$printed];
        preg_match('/^[A-Z]+(\d+):([A-Z]+)(\d+)$/', (string) $S['table_ref'], $mm);
        [$h0, $h1] = [(int) $mm[1], (int) $mm[3]];
        $head = [1 => trim((string) ($S['rows'][$h0][1] ?? 'DOC ID')), 2 => trim((string) ($S['rows'][$h0][2] ?? 'DESCRIPTION'))];
        $rows = [];
        for ($r = $h0 + 1; $r <= $h1; $r++) {
            $rows[] = ['c1' => trim((string) ($S['rows'][$r][1] ?? '')), 'c2' => trim((string) ($S['rows'][$r][2] ?? ''))];
        }
        // header block
        $block   = preg_split('/\R/u', trim((string) ($S['rows'][1][1] ?? ''))) ?: [];
        $title   = trim((string) ($S['rows'][2][1] ?? 'DTP LABORATORY DOCUMENTATION & RECORDS LIST'));
        $company = array_values(array_filter(array_map('trim', $block), 'strlen'));

        // extras, matched by Document ID and its order ("the 2nd DTP-Lab-101 of the long list" -> "the 2nd of the printed list")
        $extraCols = [];
        $extra     = [];
        $take = static function (array $sheet, int $headRow, array $cols, string $prefix) use (&$extra, &$extraCols, $rows): void {
            $byId = [];
            for ($r = $headRow + 1; $r <= max(array_keys($sheet['rows'])); $r++) {
                $id = trim((string) ($sheet['rows'][$r][1] ?? ''));
                if ($id !== '') {
                    $byId[strtolower($id)][] = $r;
                }
            }
            $seen = [];
            foreach ($rows as $n => $row) {
                $k = strtolower($row['c1']);
                $i = $seen[$k] = ($seen[$k] ?? -1) + 1;
                $r = $byId[$k][$i] ?? null;
                if ($r === null) {
                    continue;
                }
                foreach ($cols as $col => $key) {
                    $extra[$n][$key] = trim((string) ($sheet['rows'][$r][$col] ?? ''));
                }
            }
        };
        if ($working !== null && preg_match('/^[A-Z]+(\d+):/', (string) $sheets[$working]['table_ref'], $wm)) {
            $W  = $sheets[$working];
            $wh = (int) $wm[1];
            $map = [];
            foreach ($W['rows'][$wh] ?? [] as $c => $label) {
                $label = trim((string) $label);
                if ($c > 2 && $label !== '' && ! in_array(strtoupper($label), ['DOC ID', 'DESCRIPTION'], true)) {
                    $key            = 'x' . $c;
                    $map[$c]        = $key;
                    $extraCols[$key] = $label;
                }
            }
            $take($W, $wh, $map, 'x');
        }
        if ($blue !== null) {
            try {
                $B = LabDocXlsx::read($blue, 0);
                $extraCols['xblue'] = 'BLUE FOLDER TITLE';
                $take(['rows' => $B['rows']], 0, [2 => 'xblue'], 'b');
            } catch (\Throwable $e) {
                CLI::write('Blue Folder Titles not read: ' . $e->getMessage(), 'yellow');
            }
        }

        // logos
        $assets = FCPATH . 'assets' . DIRECTORY_SEPARATOR . 'labdocs';
        @mkdir($assets, 0775, true);
        $logoL = $logoR = null;
        foreach ($S['logos'] as $i => $lg) {
            $name = strtolower($code) . ($i === 0 ? '-left.' : '-right.') . $lg['ext'];
            if (LabDocXlsx::extract($main, $lg['part'], $assets . DIRECTORY_SEPARATOR . $name)) {
                if ($i === 0) {
                    $logoL = 'assets/labdocs/' . $name;
                } else {
                    $logoR = 'assets/labdocs/' . $name;
                }
            }
        }

        $now  = date('Y-m-d H:i:s');
        $data = [
            'code' => $code, 'menu_label' => $code . ' Register', 'title' => 'Register Of Approved Documents, Records, And Templates',
            'company_cn' => $company[0] ?? '', 'company_en' => $company[1] ?? '', 'address' => $company[2] ?? '', 'list_title' => $title,
            'logo_left' => $logoL, 'logo_right' => $logoR, 'folder_name' => $folder, 'file_path' => $main,
            'sheet_name' => $this->sheetName($main, $printed), 'source_files' => json_encode($files, JSON_UNESCAPED_UNICODE),
            'margin_mm' => 6.4, 'sort_order' => (int) preg_replace('/\D/', '', $code), 'is_active' => 1, 'updated_by' => 'import', 'updated_at' => $now,
        ];
        $db->transStart();
        if ($exists) {
            $rid = (int) $exists['id'];
            $db->table('tbl_labdoc_registers')->where('id', $rid)->update($data);
            $db->table('tbl_labdoc_columns')->where('register_id', $rid)->delete();
            $db->table('tbl_labdoc_rows')->where('register_id', $rid)->delete();
        } else {
            $db->table('tbl_labdoc_registers')->insert($data);
            $rid = (int) $db->insertID();
        }
        $cols = [['c1', $head[1], 31.0, 'center', 1], ['c2', $head[2], 69.0, 'left', 1]];
        foreach ($extraCols as $k => $l) {
            $cols[] = [$k, $l, 16.0, 'left', 0];
        }
        foreach ($cols as $i => [$k, $l, $w, $al, $vis]) {
            $db->table('tbl_labdoc_columns')->insert(['register_id' => $rid, 'col_key' => $k, 'label' => $l, 'width_pct' => $w, 'align' => $al, 'is_visible' => $vis, 'sort_order' => $i]);
        }
        foreach ($rows as $n => $row) {
            $db->table('tbl_labdoc_rows')->insert(['register_id' => $rid, 'sort_order' => $n, 'cells_json' => json_encode($row + ($extra[$n] ?? []), JSON_UNESCAPED_UNICODE),
                'updated_by' => 'import', 'updated_at' => $now]);
        }
        $db->table('tbl_labdoc_log')->insert(['register_id' => $rid, 'action' => 'import', 'detail' => count($rows) . ' rows, ' . count($cols) . ' columns from ' . basename($main), 'user_name' => 'spark', 'created_at' => $now]);
        $db->transComplete();
        CLI::write(($db->transStatus() ? 'Imported ' : 'FAILED ') . "{$code}: " . count($rows) . ' rows, ' . count($cols) . ' columns (' . count($extraCols) . ' hidden), logos: ' . ($logoL ? 'left ' : '') . ($logoR ? 'right' : ''), $db->transStatus() ? 'green' : 'red');
    }

    private function sheetName(string $file, int $index): string
    {
        $z = new \ZipArchive();
        $z->open($file);
        $wb = (string) $z->getFromName('xl/workbook.xml');
        $z->close();
        preg_match_all('/<sheet name="([^"]*)"/', $wb, $m);
        return html_entity_decode($m[1][$index] ?? '', ENT_QUOTES | ENT_XML1);
    }
}
