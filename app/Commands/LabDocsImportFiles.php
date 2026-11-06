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
        foreach (scandir(self::ROOT) ?: [] as $d) {                          // the exact folder name first, then a name that starts with it
            if (strcasecmp($d, $arg) === 0 && is_dir(self::ROOT . '/' . $d)) {
                $folder = $d;
                break;
            }
        }
        foreach ($folder === null ? (scandir(self::ROOT) ?: []) : [] as $d) {
            if (str_starts_with(strtolower($d), strtolower($arg)) && is_dir(self::ROOT . '/' . $d)) {
                $folder = $d;
                break;
            }
        }
        if ($folder === null) {
            CLI::error("No folder starting with {$arg} on the share.");
            return;
        }
        $codeOpt = CLI::getOption('code');
        if (is_string($codeOpt) && $codeOpt !== '') {
            $code = $codeOpt;
        } elseif (preg_match('/^#?DTP-Lab-(\d+)/i', $folder, $cm)) {
            $code = 'DTP-Lab-' . $cm[1];
        } else {
            $code = ltrim($arg, '#');
        }
        $this->disk = CLI::getOption('disk') !== null;                      // keep the files as copies on the server's disk, not in the database (big folders)
        $grp        = CLI::getOption('group');
        $grp        = is_string($grp) && $grp !== '' ? $grp : 'lab';
        $tagOpt = CLI::getOption('tag');
        $tag    = is_string($tagOpt) && $tagOpt !== '' ? $tagOpt : (string) (int) preg_replace('/\D/', '', $code);
        $title = trim((string) preg_replace('/^#?DTP-Lab-\d+\s*/', '', $folder));
        $now   = date('Y-m-d H:i:s');
        $reg   = $db->table('tbl_labdoc_registers')->where('code', $code)->get()->getRowArray();
        $row   = ['code' => $code, 'view_type' => 'files', 'menu_label' => $code . ' ' . $title, 'title' => $title, 'folder_name' => $folder, 'file_path' => '', 'sheet_name' => '',
            'sort_order' => $grp === 'lab' ? (int) preg_replace('/\D/', '', $code) : 1000, 'is_active' => 1, 'updated_by' => 'import', 'updated_at' => $now, 'grp' => $grp];
        if ($grp !== 'lab') {
            $row['menu_label'] = $folder;
        }
        if ($reg) {
            $db->table('tbl_labdoc_registers')->where('id', $reg['id'])->update($row);
            $rid = (int) $reg['id'];
        } else {
            $db->table('tbl_labdoc_registers')->insert($row);
            $rid = (int) $db->insertID();
        }
        $this->diskDir = preg_replace('/[^A-Za-z0-9._-]+/', '-', $code);
        $thumbDir = 'E:/NexusAI/vfm/thumbs' . $tag;
        $thumbs   = is_file($thumbDir . '/index.json') ? (json_decode((string) file_get_contents($thumbDir . '/index.json'), true) ?: []) : [];
        $convDir  = 'E:/NexusAI/vfm/conv' . $tag;                          // Word documents as PDF (lab_docpdf.py) and the first page of those
        $conv     = is_file($convDir . '/index.json') ? (json_decode((string) file_get_contents($convDir . '/index.json'), true) ?: []) : [];
        $cthDir   = 'E:/NexusAI/vfm/thumbsconv' . $tag;
        $cth      = is_file($cthDir . '/index.json') ? (json_decode((string) file_get_contents($cthDir . '/index.json'), true) ?: []) : [];
        $root     = self::ROOT . '/' . $folder;
        $new      = $same = 0;
        $it       = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (! $f->isFile() || str_starts_with($f->getFilename(), '~$') || in_array(strtolower($f->getExtension()), ['db', 'ini', 'lnk', 'tmp'], true)) {
                continue;
            }
            $full = str_replace('\\', '/', $f->getPathname());
            $rel  = ltrim(substr($full, strlen(str_replace('\\', '/', $root))), '/');
            $dir  = str_contains($rel, '/') ? substr($rel, 0, (int) strrpos($rel, '/')) : '';
            $name = $f->getFilename();
            $mt   = date('Y-m-d H:i:s', $f->getMTime());
            $cur  = $db->table('tbl_labdoc_files')->select('id, file_size, file_mtime')->where(['register_id' => $rid, 'folder' => $dir, 'name' => $name])->get()->getRowArray();
            if ($cur && (int) $cur['file_size'] === $f->getSize() && $cur['file_mtime'] === $mt
                && ! (isset($conv[$rel]) && ! $db->table('tbl_labdoc_files')->where(['register_id' => $rid, 'src_key' => 'conv:' . $cur['id']])->countAllResults())) {
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
            if (isset($conv[$rel]) && str_ends_with($conv[$rel], '.xlsx') && is_file($convDir . '/' . $conv[$rel])) {      // an old .xls: opened as sheets from its .xlsx copy (kept next to the cache)
                $keep = WRITEPATH . 'labdocs/xls/' . $code . '/' . $fid . '.xlsx';
                @mkdir(dirname($keep), 0775, true);
                copy($convDir . '/' . $conv[$rel], $keep);
                $bk = 'f' . $fid;
                $db->table('tbl_labdoc_books')->where(['register_id' => $rid, 'book_key' => $bk])->delete();
                $this->book($db, $rid, $bk, ['name' => $name], $keep);
            } elseif (isset($conv[$rel]) && is_file($convDir . '/' . $conv[$rel])) {          // a Word document also gets its PDF, which is what the viewer shows
                $cd = ['register_id' => $rid, 'folder' => '', 'name' => $name . '.pdf', 'ext' => 'pdf', 'file_size' => filesize($convDir . '/' . $conv[$rel]), 'file_mtime' => $mt, 'src_key' => 'conv:' . $fid,
                    'thumb' => isset($cth[$conv[$rel]]) && is_file($cthDir . '/' . $cth[$conv[$rel]]) ? file_get_contents($cthDir . '/' . $cth[$conv[$rel]]) : null];
                $cc = $db->table('tbl_labdoc_files')->where(['register_id' => $rid, 'src_key' => 'conv:' . $fid])->get()->getRowArray();
                if ($cc) {
                    $db->table('tbl_labdoc_files')->where('id', $cc['id'])->update($cd);
                    $cid = (int) $cc['id'];
                } else {
                    $db->table('tbl_labdoc_files')->insert($cd);
                    $cid = (int) $db->insertID();
                }
                $this->store($db, $cid, $convDir . '/' . $conv[$rel]);
                if ($cd['thumb'] !== null) {
                    $db->table('tbl_labdoc_files')->where('id', $fid)->update(['thumb' => $cd['thumb']]);
                }
            }
            $new++;
        }
        // the Excel files open in the same editable sheet view as the workbooks of the other folders (book key f<file id>)
        $books = 0;
        foreach ($db->table('tbl_labdoc_files')->where('register_id', $rid)->whereIn('ext', ['xlsx', 'xlsm'])->get()->getResultArray() as $x) {
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

    private bool $disk = false;
    private string $diskDir = '';

    private function store($db, int $fid, string $path): void
    {
        if ($this->disk) {                                                    // a copy on the disk: writable/labdocs/other/<code>/<id>.<ext>
            $dst = 'other/' . $this->diskDir . '/' . $fid . '.' . strtolower(pathinfo($path, PATHINFO_EXTENSION));
            @mkdir(dirname(WRITEPATH . 'labdocs/' . $dst), 0775, true);
            if (@copy($path, WRITEPATH . 'labdocs/' . $dst)) {
                $db->table('tbl_labdoc_file_parts')->where('file_id', $fid)->delete();
                $db->table('tbl_labdoc_files')->where('id', $fid)->update(['disk_path' => $dst]);

                return;
            }
        }
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
        if (strlen((string) json_encode($m['styles'])) > 450000) {
            CLI::write($file['name'] . ': too many cell styles to show as a sheet; the file stays a download', 'yellow');
            return false;
        }
        foreach ($m['sheets'] as $sh) {                                       // one statement is limited to 1 MB by the database server
            if (strlen((string) json_encode($sh, JSON_UNESCAPED_UNICODE)) > 990000) {
                CLI::write($file['name'] . ': a sheet is too large to show as a sheet; the file stays a download', 'yellow');
                return false;
            }
        }
        $db->table('tbl_labdoc_books')->insert(['register_id' => $rid, 'book_key' => $key, 'label' => pathinfo($file['name'], PATHINFO_FILENAME), 'file_name' => $file['name'], 'file_path' => $path,
            'styles_json' => json_encode($m['styles']), 'sheet_count' => count($m['sheets']), 'file_size' => (int) filesize($path), 'file_mtime' => date('Y-m-d H:i:s', (int) filemtime($path)), 'sort_order' => 0]);
        $bid = (int) $db->insertID();
        foreach ($m['sheets'] as $i => $sh) {
            $db->table('tbl_labdoc_sheets')->insert(['book_id' => $bid, 'idx' => $i, 'name' => $sh['name'], 'model_json' => '']);
            $sid = (int) $db->insertID();
            foreach (str_split((string) json_encode($sh, JSON_UNESCAPED_UNICODE), 200000) as $piece) {      // in pieces: one statement is limited to 1 MB
                $db->query('UPDATE tbl_labdoc_sheets SET model_json = CONCAT(model_json, ?) WHERE id = ?', [$piece, $sid]);
            }
        }
        foreach (str_split((string) file_get_contents($path), 400000) as $pi => $piece) {
            $db->table('tbl_labdoc_book_parts')->insert(['book_id' => $bid, 'part' => $pi, 'data' => $piece]);
        }
        CLI::write($file['name'] . ': ' . count($m['sheets']) . ' sheets', 'green');

        return true;
    }
}
