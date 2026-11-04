<?php

namespace App\Commands;

use App\Libraries\XlsxSheets;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark labdocs:import-books [#DTP-Lab-102] [--force]
 *
 * Reads the Excel workbooks that a "#" folder shows as cards (see BOOKS) from \\192.168.32.130\qc lab files\#Lab Documents into dbnexus.
 * Every sheet becomes one row (cells, styles, merged cells, widths, pictures). Nothing is written to the share. A workbook that was
 * edited in NEXUS-L is not read again unless --force is given.
 */
class LabDocsImportBooks extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'labdocs:import-books';
    protected $description = 'Imports the Excel workbooks of a "#" folder (cards, editable sheets) into the database.';

    private const ROOT = '\\\\192.168.32.130\\qc lab files\\#Lab Documents';

    /** folder prefix => [code, register title, menu label, [key => [card label, file name]]] */
    private const BOOKS = [
        '#DTP-Lab-102' => ['DTP-Lab-102', 'Test Method Amendment Record Sheet', 'DTP-Lab-102 Test Method Amendments', [
            'amendment-2026'  => ['Test Method Amendment 2026', 'Test Method Ammendment 2026.xlsx'],
            'accredited-2026' => ['Test Method Accredited 2026', 'TEST METHOD ACCREDITED 2026.xlsx'],
            'record-sheet'    => ['Amendment Record Sheet (Updated)', 'DTP-Lab-102 Test Method Amendment Record Sheet UPDATED.xlsx'],
        ]],
    ];

    public function run(array $params)
    {
        $prefix = $params[0] ?? '#DTP-Lab-102';
        $force  = CLI::getOption('force') !== null || in_array('--force', $params, true);
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
        if (isset(self::BOOKS[$prefix])) {
            [$code, $title, $menu, $books] = self::BOOKS[$prefix];
        } else {                                                    // any other "#" folder: every Excel file in it is a workbook
            $code  = ltrim($prefix, '#');
            $title = trim((string) preg_replace('/^#?DTP-Lab-\\d+\\s*/', '', $folder));
            $menu  = $code . ' ' . $title;
            $books = [];
            foreach (scandir(self::ROOT . '\\' . $folder) ?: [] as $f) {
                if (preg_match('/\\.xlsx$/i', $f) && ! str_starts_with($f, '~$')) {
                    $key = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', pathinfo($f, PATHINFO_FILENAME)), '-'));
                    $books[substr($key, 0, 58)] = [pathinfo($f, PATHINFO_FILENAME), $f];
                }
            }
        }
        $db = \Config\Database::connect();
        if (! $db->tableExists('tbl_labdoc_books')) {
            CLI::error('Run php spark migrate first.');
            return;
        }
        $now = date('Y-m-d H:i:s');
        $reg = $db->table('tbl_labdoc_registers')->where('code', $code)->get()->getRowArray();
        $row = ['code' => $code, 'view_type' => 'books', 'menu_label' => $menu, 'title' => $title, 'folder_name' => $folder, 'file_path' => '', 'sheet_name' => '',
            'sort_order' => (int) preg_replace('/\D/', '', $code), 'is_active' => 1, 'updated_by' => 'import', 'updated_at' => $now];
        if ($reg) {
            $db->table('tbl_labdoc_registers')->where('id', $reg['id'])->update($row);
            $rid = (int) $reg['id'];
        } else {
            $db->table('tbl_labdoc_registers')->insert($row);
            $rid = (int) $db->insertID();
        }
        $n = 0;
        foreach (array_values($books) as $order => [$label, $file]) {
            $key  = array_keys($books)[$order];
            $path = self::ROOT . '\\' . $folder . '\\' . $file;
            if (! is_file($path)) {
                CLI::write("missing: {$file}", 'red');
                continue;
            }
            $cur = $db->table('tbl_labdoc_books')->where(['register_id' => $rid, 'book_key' => $key])->get()->getRowArray();
            if ($cur && ! $force && $db->table('tbl_labdoc_sheets')->where('book_id', $cur['id'])->where('revision_no >', 0)->countAllResults() > 0) {
                CLI::write("{$key}: edited in NEXUS-L, kept (use --force to read it again)", 'yellow');
                continue;
            }
            $dir = FCPATH . 'assets' . DIRECTORY_SEPARATOR . 'labdocs' . DIRECTORY_SEPARATOR . 'books' . DIRECTORY_SEPARATOR . $key;
            $m   = XlsxSheets::read($path, $dir);
            if (! $m['sheets']) {
                CLI::write("{$file}: no sheet could be read (the Excel file is damaged); skipped", 'red');
                continue;
            }
            $data = ['register_id' => $rid, 'book_key' => $key, 'label' => $label, 'file_name' => $file, 'file_path' => $path, 'styles_json' => json_encode($m['styles']),
                'sheet_count' => count($m['sheets']), 'file_size' => (int) filesize($path), 'file_mtime' => date('Y-m-d H:i:s', (int) filemtime($path)), 'sort_order' => $order, 'file_status' => null];
            $db->transStart();
            if ($cur) {
                $bid = (int) $cur['id'];
                $db->table('tbl_labdoc_books')->where('id', $bid)->update($data);
                $db->table('tbl_labdoc_sheets')->where('book_id', $bid)->delete();
            } else {
                $db->table('tbl_labdoc_books')->insert($data);
                $bid = (int) $db->insertID();
            }
            foreach ($m['sheets'] as $i => $sh) {
                $db->table('tbl_labdoc_sheets')->insert(['book_id' => $bid, 'idx' => $i, 'name' => $sh['name'], 'model_json' => '']);
                $this->putBig($db, 'tbl_labdoc_sheets', (int) $db->insertID(), 'model_json', json_encode($sh, JSON_UNESCAPED_UNICODE));
            }
            $db->table('tbl_labdoc_book_parts')->where('book_id', $bid)->delete();                  // the Excel file itself, kept in the database in pieces
            foreach (str_split((string) file_get_contents($path), 400000) as $pi => $piece) {
                $db->table('tbl_labdoc_book_parts')->insert(['book_id' => $bid, 'part' => $pi, 'data' => $piece]);
            }
            $db->table('tbl_labdoc_log')->insert(['register_id' => $rid, 'action' => 'import', 'user_name' => 'spark', 'created_at' => $now, 'detail' => $file . ': ' . count($m['sheets']) . ' sheets']);
            $db->transComplete();
            CLI::write(($db->transStatus() ? 'ok  ' : 'FAIL ') . "{$key}: {$file} (" . count($m['sheets']) . ' sheets)', $db->transStatus() ? 'green' : 'red');
            $n++;
        }
        CLI::write("{$code}: {$n} workbooks imported");
    }

    /** a big value goes in pieces (the database server limits the size of one statement) */
    private function putBig($db, string $table, int $id, string $col, string $data): void
    {
        $db->query("UPDATE {$table} SET {$col} = '' WHERE id = ?", [$id]);
        foreach (str_split($data, 262144) ?: [] as $piece) {
            $db->query("UPDATE {$table} SET {$col} = CONCAT({$col}, ?) WHERE id = ?", [$piece, $id]);
        }
    }
}
