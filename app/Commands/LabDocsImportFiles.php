<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark labdocs:import-files "#DTP-Lab-108"        a folder of files (sub folders too): shown as folders and file cards; the files go into the database
 * php spark labdocs:import-files --pdfs DTP-Lab-101    the PDFs / documents of the test methods already imported (101, 103): a copy in the database + a preview
 *
 * Run ai-service/tools/lab_thumbs.py first (E:\NexusAI\vfm\thumbs108 / thumbs101 / thumbs103). Files that did not change are not read again.
 */
class LabDocsImportFiles extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'labdocs:import-files';
    protected $description = 'Stores the files of a "#" folder (and the PDFs of the test methods) in the database.';

    private const ROOT = '//192.168.32.130/qc lab files/#Lab Documents';

    public function run(array $params)
    {
        $opt  = CLI::getOption('pdfs');
        $pdfs = $opt !== null || in_array('--pdfs', $params, true);
        $args = array_values(array_filter($params, static fn ($p) => $p !== '--pdfs' && $p !== '--force'));
        $db   = \Config\Database::connect();
        if (! $db->tableExists('tbl_labdoc_files')) {
            CLI::error('Run php spark migrate first.');
            return;
        }
        $arg = is_string($opt) && $opt !== '' ? $opt : ($args[0] ?? '#DTP-Lab-108');
        if ($pdfs) {
            $this->pdfs($db, ltrim($arg, '#'));
            return;
        }
        $folder = null;
        foreach (scandir(self::ROOT) ?: [] as $d) {
            if (str_starts_with($d, $arg) && is_dir(self::ROOT . '/' . $d)) {
                $folder = $d;
                break;
            }
        }
        if ($folder === null) {
            CLI::error("No folder starting with {$arg} on the share.");
            return;
        }
        $code  = ltrim($arg, '#');
        $title = trim((string) preg_replace('/^#?DTP-Lab-\d+\s*/', '', $folder));
        $now   = date('Y-m-d H:i:s');
        $reg   = $db->table('tbl_labdoc_registers')->where('code', $code)->get()->getRowArray();
        $row   = ['code' => $code, 'view_type' => 'files', 'menu_label' => $code . ' ' . $title, 'title' => $title, 'folder_name' => $folder, 'file_path' => '', 'sheet_name' => '',
            'sort_order' => (int) preg_replace('/\D/', '', $code), 'is_active' => 1, 'updated_by' => 'import', 'updated_at' => $now];
        if ($reg) {
            $db->table('tbl_labdoc_registers')->where('id', $reg['id'])->update($row);
            $rid = (int) $reg['id'];
        } else {
            $db->table('tbl_labdoc_registers')->insert($row);
            $rid = (int) $db->insertID();
        }
        $thumbDir = 'E:/NexusAI/vfm/thumbs' . (int) preg_replace('/\D/', '', $code);
        $thumbs   = is_file($thumbDir . '/index.json') ? (json_decode((string) file_get_contents($thumbDir . '/index.json'), true) ?: []) : [];
        $root     = self::ROOT . '/' . $folder;
        $new      = $same = 0;
        $it       = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (! $f->isFile() || str_starts_with($f->getFilename(), '~$') || strcasecmp($f->getFilename(), 'Thumbs.db') === 0) {
                continue;
            }
            $full = str_replace('\\', '/', $f->getPathname());
            $rel  = ltrim(substr($full, strlen(str_replace('\\', '/', $root))), '/');
            $dir  = str_contains($rel, '/') ? substr($rel, 0, (int) strrpos($rel, '/')) : '';
            $name = $f->getFilename();
            $mt   = date('Y-m-d H:i:s', $f->getMTime());
            $cur  = $db->table('tbl_labdoc_files')->select('id, file_size, file_mtime')->where(['register_id' => $rid, 'folder' => $dir, 'name' => $name])->get()->getRowArray();
            if ($cur && (int) $cur['file_size'] === $f->getSize() && $cur['file_mtime'] === $mt) {
                $same++;
                continue;
            }
            $data = [
                'register_id' => $rid, 'folder' => $dir, 'name' => $name, 'ext' => strtolower($f->getExtension()), 'file_size' => $f->getSize(), 'file_mtime' => $mt,
                'thumb' => isset($thumbs[$rel]) && is_file($thumbDir . '/' . $thumbs[$rel]) ? file_get_contents($thumbDir . '/' . $thumbs[$rel]) : null,
            ];
            if ($cur) {
                $db->table('tbl_labdoc_files')->where('id', $cur['id'])->update($data);
                $fid = (int) $cur['id'];
            } else {
                $db->table('tbl_labdoc_files')->insert($data);
                $fid = (int) $db->insertID();
            }
            $this->store($db, $fid, $f->getPathname());
            $new++;
        }
        // the Excel files open in the same editable sheet view as the workbooks of the other folders (book key f<file id>)
        $books = 0;
        foreach ($db->table('tbl_labdoc_files')->where(['register_id' => $rid, 'ext' => 'xlsx'])->get()->getResultArray() as $x) {
            $key = 'f' . $x['id'];
            if ($db->table('tbl_labdoc_books')->where(['register_id' => $rid, 'book_key' => $key])->countAllResults() > 0) {
                continue;
            }
            $books += $this->book($db, $rid, $key, $x, $root . '/' . ($x['folder'] === '' ? '' : $x['folder'] . '/') . $x['name']) ? 1 : 0;
        }
        $db->table('tbl_labdoc_log')->insert(['register_id' => $rid, 'action' => 'import', 'user_name' => 'spark', 'created_at' => $now, 'detail' => "{$new} files stored, {$same} unchanged"]);
        CLI::write("{$code}: {$new} files stored in the database, {$same} unchanged", 'green');
    }

    /** the PDFs and documents that belong to the test methods of 101 / 103: kept in the database under their cache name */
    private function pdfs($db, string $code): void
    {
        $reg = $db->table('tbl_labdoc_registers')->where('code', $code)->get()->getRowArray();
        if (! $reg) {
            CLI::error("{$code} is not imported.");
            return;
        }
        $n    = (int) preg_replace('/\D/', '', $code);
        $dir  = 'E:/NexusAI/vfm/thumbs' . $n;
        $idx  = is_file($dir . '/index.json') ? (json_decode((string) file_get_contents($dir . '/index.json'), true) ?: []) : [];
        $keys = [];
        foreach (['tbl_vfm_methods', 'tbl_vfm_illustrated', 'tbl_vfm_docs'] as $t) {
            foreach ($db->table($t)->select('cache_file')->where('register_id', $reg['id'])->get()->getResultArray() as $r) {
                $keys[] = $r['cache_file'];
            }
        }
        $new = $same = $miss = 0;
        foreach (array_unique($keys) as $key) {
            $path = WRITEPATH . 'labdocs/' . $key;
            if (! is_file($path)) {
                $miss++;
                continue;
            }
            $rel = preg_replace('~^lab' . $n . '/~', '', $key);
            $cur = $db->table('tbl_labdoc_files')->where(['register_id' => $reg['id'], 'src_key' => $key])->get()->getRowArray();
            if ($cur && (int) $cur['file_size'] === filesize($path)) {
                $same++;
                continue;
            }
            $data = ['register_id' => $reg['id'], 'folder' => '', 'name' => basename($key), 'ext' => strtolower(pathinfo($key, PATHINFO_EXTENSION)), 'file_size' => filesize($path),
                'file_mtime' => date('Y-m-d H:i:s', filemtime($path)), 'src_key' => $key,
                'thumb' => isset($idx[$rel]) && is_file($dir . '/' . $idx[$rel]) ? file_get_contents($dir . '/' . $idx[$rel]) : null];
            if ($cur) {
                $db->table('tbl_labdoc_files')->where('id', $cur['id'])->update($data);
                $fid = (int) $cur['id'];
            } else {
                $db->table('tbl_labdoc_files')->insert($data);
                $fid = (int) $db->insertID();
            }
            $this->store($db, $fid, $path);
            $new++;
        }
        CLI::write("{$code}: {$new} files stored in the database, {$same} unchanged, {$miss} not in the cache", 'green');
    }

    private function store($db, int $fid, string $path): void
    {
        $db->table('tbl_labdoc_file_parts')->where('file_id', $fid)->delete();
        $h = fopen($path, 'rb');
        $i = 0;
        while ($h && ! feof($h)) {
            $piece = fread($h, 400000);
            if ($piece === false || $piece === '') {
                break;
            }
            $db->table('tbl_labdoc_file_parts')->insert(['file_id' => $fid, 'part' => $i++, 'data' => $piece]);
        }
        if ($h) {
            fclose($h);
        }
    }

    private function book($db, int $rid, string $key, array $file, string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }
        $m = \App\Libraries\XlsxSheets::read($path, FCPATH . 'assets/labdocs/books/' . $key);
        if (! $m['sheets']) {
            CLI::write($file['name'] . ': no sheet could be read (damaged Excel file); it stays a download', 'red');
            return false;
        }
        $db->table('tbl_labdoc_books')->insert(['register_id' => $rid, 'book_key' => $key, 'label' => pathinfo($file['name'], PATHINFO_FILENAME), 'file_name' => $file['name'], 'file_path' => $path,
            'styles_json' => json_encode($m['styles']), 'sheet_count' => count($m['sheets']), 'file_size' => (int) filesize($path), 'file_mtime' => date('Y-m-d H:i:s', (int) filemtime($path)), 'sort_order' => 0]);
        $bid = (int) $db->insertID();
        foreach ($m['sheets'] as $i => $sh) {
            $db->table('tbl_labdoc_sheets')->insert(['book_id' => $bid, 'idx' => $i, 'name' => $sh['name'], 'model_json' => json_encode($sh, JSON_UNESCAPED_UNICODE)]);
        }
        foreach (str_split((string) file_get_contents($path), 400000) as $pi => $piece) {
            $db->table('tbl_labdoc_book_parts')->insert(['book_id' => $bid, 'part' => $pi, 'data' => $piece]);
        }
        CLI::write($file['name'] . ': ' . count($m['sheets']) . ' sheets', 'green');

        return true;
    }
}
