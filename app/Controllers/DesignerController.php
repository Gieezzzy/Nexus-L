<?php

namespace App\Controllers;

/**
 * DesignerController - admin-only report designer.
 * Stores where each measuring point sits on the shoe sketch, which form fields are hidden / renamed / reordered,
 * the admin-added custom fields and extra report elements (text, bound field, bar chart, image, line).
 * Everyone who is signed in can READ a layout (the report views apply it); only admins can change it.
 */
class DesignerController extends BaseController
{
    private const SLUGS = ['bonding'];

    public function index(): string
    {
        return view('layouts/main', [
            'title'   => 'Report Designer',
            'withCss' => 'designer.css',
            'withJs'  => ['bond-graph.js', 'pull-graph.js', 'designer.js', 'designer-graph.js', 'designer-pull.js'],
            'content' => view('designer/index', ['formFields' => $this->bondingFormFields(), 'graphColumns' => $this->graphColumns()]),
        ]);
    }

    /**
     * The fields of the Bonding form, read from the form itself so the designer always matches it.
     *
     * @return list<array{name:string,label:string}>
     */
    private function bondingFormFields(): array
    {
        // the DTR page is split into partials (dtr/partials/*.php): read them together with the page itself
        $html = (string) @file_get_contents(APPPATH . 'Views/dtr/index.php');
        foreach (glob(APPPATH . 'Views/dtr/partials/*.php') ?: [] as $part) {
            $html .= "\n" . (string) @file_get_contents($part);
        }
        $from = strpos($html, 'id="dtr-bonding-form"');
        $to   = strpos($html, 'dtr-bonding-points-tbody', (int) $from);
        if ($from === false) {
            return [];
        }
        $html = substr($html, $from, ($to ?: strlen($html)) - $from);
        $out  = [];
        if (preg_match_all('#<div class="dtr-field[^"]*">\s*<label>(.*?)</label>\s*<(?:input|select)[^>]*\bname="([A-Za-z0-9_]+)"#s', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $out[] = ['name' => $x[2], 'label' => trim(html_entity_decode(strip_tags($x[1])), " *	
")];
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------- Bonding graph report (PNG)

    private const GRAPH_SLUG = 'bonding-graph';

    /** GET dtr/bonding-graph-layout : the saved layout of the graph report (any signed-in user; null = the built-in default) */
    public function graphLayout()
    {
        $row = \Config\Database::connect()->table('tbl_report_layouts')->where(['slug' => self::GRAPH_SLUG, 'variant' => 'a4'])->get()->getRowArray();

        return $this->response->setJSON(['ok' => true, 'layout' => $row ? json_decode($row['layout_json'], true) : null, 'csrf' => csrf_hash()]);
    }

    /** POST designer/graph-layout/save {layout} (admin) | {reset: true} */
    public function graphLayoutSave()
    {
        $p     = $this->request->getJSON(true) ?? [];
        $db    = \Config\Database::connect();
        $where = ['slug' => self::GRAPH_SLUG, 'variant' => 'a4'];
        if (! empty($p['reset'])) {
            $db->table('tbl_report_layouts')->where($where)->delete();
            \App\Libraries\ActivityLog::record('designer', 'graph-layout-reset', 'Bonding graph report');

            return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
        }
        $lay = $p['layout'] ?? null;
        if (! is_array($lay) || ! isset($lay['items']) || ! is_array($lay['items']) || count($lay['items']) > 200) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Invalid layout.', 'csrf' => csrf_hash()]);
        }
        // only known item types and plain values: numbers, short text, simple lists
        $types = ['text', 'field', 'tests', 'points', 'graph', 'line'];
        $items = [];
        foreach ($lay['items'] as $it) {
            if (! is_array($it) || ! in_array($it['type'] ?? '', $types, true)) {
                continue;
            }
            $o = [];
            foreach ($it as $k => $v) {
                if (! is_string($k) || ! preg_match('/^[A-Za-z0-9_]{1,24}$/', $k)) {
                    continue;
                }
                if (is_array($v)) {
                    $o[$k] = array_values(array_filter(array_map(static fn ($x) => is_scalar($x) ? mb_substr((string) $x, 0, 40) : null, $v), static fn ($x) => $x !== null));
                } elseif (is_bool($v) || is_int($v) || is_float($v)) {
                    $o[$k] = $v;
                } else {
                    $o[$k] = mb_substr((string) $v, 0, 300);
                }
            }
            $items[] = $o;
        }
        $series = [];
        foreach ((array) ($lay['series'] ?? []) as $sr) {
            if (! is_array($sr)) {
                continue;
            }
            $one = [];
            foreach (['key', 'label', 'points', 'count', 'graph', 'spots', 'color'] as $k) {
                $one[$k] = mb_substr((string) ($sr[$k] ?? ''), 0, 60);
            }
            $one['on'] = ! isset($sr['on']) || (bool) $sr['on'];
            if (preg_match('/^[A-Za-z0-9_]{1,20}$/', $one['key'])) {
                $series[] = $one;
            }
        }
        $clean = ['v' => 1, 'w' => max(600, min(4000, (int) ($lay['w'] ?? 1240))), 'h' => max(800, min(6000, (int) ($lay['h'] ?? 1754))), 'series' => $series, 'items' => $items];
        $data  = ['layout_json' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'updated_by' => (string) ($this->auth->user()['username'] ?? ''), 'updated_at' => date('Y-m-d H:i:s')];
        if ($db->table('tbl_report_layouts')->where($where)->countAllResults() > 0) {
            $db->table('tbl_report_layouts')->where($where)->update($data);
        } else {
            $db->table('tbl_report_layouts')->insert($where + $data);
        }
        \App\Libraries\ActivityLog::record('designer', 'graph-layout-save', 'Bonding graph report', count($items) . ' items');

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    /** GET designer/graph-records : recent Lab Refs that have a curve, for the preview list */
    public function graphRecords()
    {
        $rows = \Config\Database::connect()->query("SELECT LabRefNo, ShoeName, TestDate FROM tbl_dtr_bonding_points WHERE UTFGraph IS NOT NULL AND UTFGraph <> '' ORDER BY BondingPointstID DESC LIMIT 80")->getResultArray();

        return $this->response->setJSON(['ok' => true, 'records' => $rows, 'csrf' => csrf_hash()]);
    }

    // ---------------------------------------------------------------- Pull Test Graph Reports (Rivet, Velcro, Eyelet, Lace Holder, Heel/Tongue Pull Tab)

    private const PULL_CATS = ['rivet', 'velcro', 'eyelet', 'lace_holder', 'heel_pull_tab', 'tongue_pull_tab'];

    private function pullSlug(string $cat): ?string
    {
        return in_array($cat, self::PULL_CATS, true) ? ('pull-graph-' . $cat) : null;
    }

    /** @return list<string> */
    private function allDbTables(\CodeIgniter\Database\BaseConnection $db): array
    {
        $out = [];
        foreach ($db->query('SHOW TABLES')->getResultArray() as $r) {
            $t = (string) reset($r);
            if ($t !== '' && ! str_starts_with($t, 'tbl_bot_')) {
                $out[] = $t;
            }
        }
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    /** @return list<string> */
    private function tableColumns(\CodeIgniter\Database\BaseConnection $db, string $table): array
    {
        if (! in_array($table, $this->allDbTables($db), true)) {
            return [];
        }
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->getResultArray() as $c) {
            $cols[] = (string) $c['Field'];
        }

        return $cols;
    }

    /** GET dtr/pull-graph-layout?cat=eyelet */
    public function pullGraphLayout()
    {
        $cat  = trim((string) ($this->request->getGet('cat') ?? 'eyelet'));
        $slug = $this->pullSlug($cat);
        if (! $slug) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Invalid report category.', 'csrf' => csrf_hash()]);
        }
        $row = \Config\Database::connect()->table('tbl_report_layouts')->where(['slug' => $slug, 'variant' => 'a4'])->get()->getRowArray();

        return $this->response->setJSON(['ok' => true, 'cat' => $cat, 'layout' => $row ? json_decode($row['layout_json'], true) : null, 'csrf' => csrf_hash()]);
    }

    /** GET designer/db-tables */
    public function dbTables()
    {
        $db = \Config\Database::connect();

        return $this->response->setJSON(['ok' => true, 'tables' => $this->allDbTables($db), 'csrf' => csrf_hash()]);
    }

    /** GET designer/db-columns?tables=t1,t2,t3 */
    public function dbColumns()
    {
        $db     = \Config\Database::connect();
        $valid  = $this->allDbTables($db);
        $raw    = array_filter(array_map('trim', explode(',', (string) ($this->request->getGet('tables') ?? ''))));
        $tables = array_values(array_slice(array_unique(array_intersect($raw, $valid)), 0, 3));
        $out    = [];
        foreach ($tables as $t) {
            $out[$t] = $this->tableColumns($db, $t);
        }

        return $this->response->setJSON([
            'ok'      => true,
            'columns' => $out,
            'special' => ['MaxPeak', 'MinPeak', 'AvgPeak', 'TestCountUsed', 'now'],
            'csrf'    => csrf_hash(),
        ]);
    }

    /** GET designer/pull-records?table=tbl_dtr_pull_tests&cat=eyelet */
    public function pullRecords()
    {
        $db    = \Config\Database::connect();
        $table = trim((string) ($this->request->getGet('table') ?? 'tbl_dtr_pull_tests'));
        $cat   = trim((string) ($this->request->getGet('cat') ?? ''));
        $cols  = $this->tableColumns($db, $table);
        if (! $cols) {
            return $this->response->setJSON(['ok' => true, 'records' => [], 'csrf' => csrf_hash()]);
        }
        $pick = [];
        foreach (['LabRefNo', 'TestType', 'ModelStyle', 'TestedDate', 'Result'] as $c) {
            if (in_array($c, $cols, true)) {
                $pick[] = '`' . $c . '`';
            }
        }
        $pk = $cols[0];
        if (! in_array('`' . $pk . '`', $pick, true)) {
            array_unshift($pick, '`' . $pk . '`');
        }
        $where = '';
        $binds = [];
        if ($table === 'tbl_dtr_pull_tests' && $cat !== '') {
            $map = [
                'rivet'           => 'RIVET',
                'velcro'          => 'VELCRO',
                'eyelet'          => 'EYELET',
                'lace_holder'     => 'LACE',
                'heel_pull_tab'   => 'HEEL',
                'tongue_pull_tab' => 'TONGUE',
            ];
            if (isset($map[$cat])) {
                $where = ' WHERE `TestType` LIKE ?';
                $binds[] = '%' . $map[$cat] . '%';
            }
        }
        $sql  = 'SELECT ' . implode(', ', $pick) . ' FROM `' . $table . '`' . $where . ' ORDER BY `' . $pk . '` DESC LIMIT 80';
        $rows = $db->query($sql, $binds)->getResultArray();
        if ($where !== '' && ! $rows) {
            $rows = $db->query('SELECT ' . implode(', ', $pick) . ' FROM `' . $table . '` ORDER BY `' . $pk . '` DESC LIMIT 80')->getResultArray();
        }

        return $this->response->setJSON(['ok' => true, 'pk' => $pk, 'records' => $rows, 'csrf' => csrf_hash()]);
    }

    /** POST designer/pull-layout/save {cat, layout} | {cat, reset: true} */
    public function pullLayoutSave()
    {
        $p    = $this->request->getJSON(true) ?? [];
        $cat  = trim((string) ($p['cat'] ?? ''));
        $slug = $this->pullSlug($cat);
        if (! $slug) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Invalid report category.', 'csrf' => csrf_hash()]);
        }
        $db    = \Config\Database::connect();
        $where = ['slug' => $slug, 'variant' => 'a4'];
        if (! empty($p['reset'])) {
            $db->table('tbl_report_layouts')->where($where)->delete();
            \App\Libraries\ActivityLog::record('designer', 'pull-layout-reset', $slug);

            return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
        }
        $lay = $p['layout'] ?? null;
        if (! is_array($lay) || ! isset($lay['items']) || ! is_array($lay['items']) || count($lay['items']) > 200) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Invalid layout.', 'csrf' => csrf_hash()]);
        }
        $validTables = $this->allDbTables($db);
        $rawSrc      = (array) ($lay['sources'] ?? []);
        $tables      = ['', '', ''];
        for ($i = 0; $i < 3; $i++) {
            $t = trim((string) (($rawSrc['tables'] ?? [])[$i] ?? ''));
            if ($t !== '' && in_array($t, $validTables, true)) {
                $tables[$i] = $t;
            }
        }
        $joins = [null, null, null];
        for ($i = 1; $i < 3; $i++) {
            $j = ($rawSrc['joins'] ?? [])[$i] ?? null;
            if (is_array($j) && $tables[$i] !== '') {
                $lt = (int) ($j['leftTable'] ?? 0);
                if ($lt < 0 || $lt >= $i || $tables[$lt] === '') {
                    $lt = 0;
                }
                $lc = trim((string) ($j['leftCol'] ?? ''));
                $rc = trim((string) ($j['rightCol'] ?? ''));
                $lCols = $tables[$lt] !== '' ? $this->tableColumns($db, $tables[$lt]) : [];
                $rCols = $this->tableColumns($db, $tables[$i]);
                $joins[$i] = [
                    'leftTable' => $lt,
                    'leftCol'   => in_array($lc, $lCols, true) ? $lc : '',
                    'rightCol'  => in_array($rc, $rCols, true) ? $rc : '',
                ];
            } else {
                $joins[$i] = ['leftTable' => 0, 'leftCol' => '', 'rightCol' => ''];
            }
        }

        $types = ['text', 'field', 'infogrid', 'pulltable', 'pullgraph', 'line'];
        $items = [];
        foreach ($lay['items'] as $it) {
            if (! is_array($it) || ! in_array($it['type'] ?? '', $types, true)) {
                continue;
            }
            $items[] = $this->sanitizeLayoutNode($it, 0);
        }

        $clean = [
            'v'       => 1,
            'w'       => max(600, min(4000, (int) ($lay['w'] ?? 1240))),
            'h'       => max(800, min(6000, (int) ($lay['h'] ?? 1754))),
            'cat'     => $cat,
            'sources' => ['tables' => $tables, 'joins' => $joins],
            'items'   => $items,
        ];
        $data = [
            'layout_json' => json_encode($clean, JSON_UNESCAPED_UNICODE),
            'updated_by'  => (string) ($this->auth->user()['username'] ?? ''),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        if ($db->table('tbl_report_layouts')->where($where)->countAllResults() > 0) {
            $db->table('tbl_report_layouts')->where($where)->update($data);
        } else {
            $db->table('tbl_report_layouts')->insert($where + $data);
        }
        \App\Libraries\ActivityLog::record('designer', 'pull-layout-save', $slug, count($items) . ' items');

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    private function sanitizeLayoutNode($val, int $depth)
    {
        if ($depth > 4) {
            return null;
        }
        if (is_bool($val) || is_int($val) || is_float($val)) {
            return $val;
        }
        if (is_string($val)) {
            return mb_substr($val, 0, 300);
        }
        if (! is_array($val)) {
            return null;
        }
        $isList = array_is_list($val);
        $out    = [];
        $count  = 0;
        foreach ($val as $k => $v) {
            if ($count++ >= 60) {
                break;
            }
            $sv = $this->sanitizeLayoutNode($v, $depth + 1);
            if ($sv === null && $v !== null) {
                continue;
            }
            if ($isList) {
                $out[] = $sv;
            } elseif (is_string($k) && preg_match('/^[A-Za-z0-9_]{1,24}$/', $k)) {
                $out[$k] = $sv;
            }
        }

        return $out;
    }

    /** the columns the toolbox offers: [{name, group, src}] */
    private function graphColumns(): array
    {
        $db  = \Config\Database::connect();
        $out = [];
        foreach ($db->query('SHOW COLUMNS FROM tbl_dtr_bonding_points')->getResultArray() as $c) {
            $n = $c['Field'];
            if (preg_match('/^(UTF|FTO)point\d+$|^FiTO\d+$/', $n)) {
                continue;   // the 45 + 45 + 13 point columns are used through a Points table item
            }
            $g = preg_match('/Graph$|Spots$|SpotEdits$/', $n) ? 'Curves and spots' : 'Report';
            $out[] = ['name' => $n, 'group' => $g, 'src' => 'points'];
        }
        $have = array_column($out, 'name');
        foreach (['Technician', 'Result', 'Side', 'Remarks', 'ModeOfFailure', 'POLotNO', 'MachineNumber', 'Report'] as $n) {
            if (! in_array($n, $have, true)) {
                $out[] = ['name' => $n, 'group' => 'From the bonding report', 'src' => 'info'];
            }
        }
        $out[] = ['name' => 'now', 'group' => 'Special', 'src' => 'special'];

        return $out;
    }

    /** GET dtr/layout?slug=&variant= (any signed-in user) */
    public function layout()
    {
        [$slug, $variant] = $this->keys();
        $row = \Config\Database::connect()->table('tbl_report_layouts')->where(['slug' => $slug, 'variant' => $variant])->get()->getRowArray();

        return $this->response->setJSON([
            'ok'     => true,
            'layout' => $row ? json_decode($row['layout_json'], true) : null,
            'csrf'   => csrf_hash(),
        ]);
    }

    /** POST designer/save {slug, variant, layout} */
    public function save()
    {
        $p      = $this->request->getJSON(true) ?? $this->request->getPost();
        $slug   = (string) ($p['slug'] ?? '');
        $var    = (string) ($p['variant'] ?? '');
        $layout = $p['layout'] ?? null;

        if (! in_array($slug, self::SLUGS, true) || ! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $var) || ! is_array($layout)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Invalid layout.', 'csrf' => csrf_hash()]);
        }

        $json = json_encode($this->clean($layout), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) > 2000000) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Layout too large.', 'csrf' => csrf_hash()]);
        }

        $db    = \Config\Database::connect();
        $by    = (string) ($this->auth->user()['username'] ?? '');
        $where = ['slug' => $slug, 'variant' => $var];
        $data  = ['layout_json' => $json, 'updated_by' => $by, 'updated_at' => date('Y-m-d H:i:s')];

        if ($db->table('tbl_report_layouts')->where($where)->countAllResults() > 0) {
            $db->table('tbl_report_layouts')->where($where)->update($data);
        } else {
            $db->table('tbl_report_layouts')->insert($where + $data);
        }

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    /** POST designer/reset {slug, variant}: back to the built-in default */
    public function reset()
    {
        $p    = $this->request->getJSON(true) ?? $this->request->getPost();
        $slug = (string) ($p['slug'] ?? '');
        $var  = (string) ($p['variant'] ?? '');
        if (in_array($slug, self::SLUGS, true) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $var)) {
            \Config\Database::connect()->table('tbl_report_layouts')->where(['slug' => $slug, 'variant' => $var])->delete();
        }

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    // ---------------------------------------------------------------- Excel readers

    private const MAP_SLUGS = ['bonding', 'fabric', 'leather', 'wst', 'fthl', 'hardware', 'label', 'sklfm', 'rubber', 'box', 'insole'];

    /** GET designer/maps?slug= */
    public function maps()
    {
        $slug = (string) $this->request->getGet('slug');
        if (! in_array($slug, self::MAP_SLUGS, true)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Unknown report type.', 'csrf' => csrf_hash()]);
        }
        $rows = \Config\Database::connect()->table('tbl_machine_maps')->where('slug', $slug)->orderBy('sort_order')->orderBy('id')->get()->getResultArray();
        foreach ($rows as &$r) {
            $r['rules']   = json_decode((string) $r['rules_json'], true) ?: [];
            $r['enabled'] = (int) $r['enabled'] === 1;
            unset($r['rules_json']);
        }

        return $this->response->setJSON(['ok' => true, 'maps' => $rows, 'slugs' => self::MAP_SLUGS, 'csrf' => csrf_hash()]);
    }

    /** POST designer/maps/save {slug, maps:[{label, folder, sheet, enabled, rules:[...]}]} replaces all maps of the type */
    public function saveMaps()
    {
        $p    = $this->request->getJSON(true) ?? $this->request->getPost();
        $slug = (string) ($p['slug'] ?? '');
        if (! in_array($slug, self::MAP_SLUGS, true) || ! is_array($p['maps'] ?? null)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Invalid data.', 'csrf' => csrf_hash()]);
        }

        $rows = [];
        foreach ($p['maps'] as $i => $m) {
            $folder = \App\Libraries\MachineFileReader::safeFolder((string) ($m['folder'] ?? ''));
            if ($folder === null) {
                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'The folder of "' . strip_tags((string) ($m['label'] ?? '')) . '" is not valid (letters, digits, space, _ - . and backslash only).', 'csrf' => csrf_hash()]);
            }
            $rows[] = [
                'slug'       => $slug,
                'label'      => mb_substr(strip_tags((string) ($m['label'] ?? 'Reader')), 0, 120),
                'folder'     => $folder,
                'sheet'      => mb_substr(strip_tags((string) ($m['sheet'] ?? '')), 0, 60),
                'rules_json' => json_encode($this->cleanRules((array) ($m['rules'] ?? [])), JSON_UNESCAPED_SLASHES),
                'enabled'    => ! empty($m['enabled']) ? 1 : 0,
                'sort_order' => $i + 1,
                'updated_by' => (string) ($this->auth->user()['username'] ?? ''),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        $db = \Config\Database::connect();
        $db->transStart();
        $db->table('tbl_machine_maps')->where('slug', $slug)->delete();
        if ($rows) {
            $db->table('tbl_machine_maps')->insertBatch($rows);
        }
        $db->transComplete();

        return $this->response->setJSON(['ok' => $db->transStatus(), 'csrf' => csrf_hash()]);
    }

    /** GET designer/grid?folder=&lab_ref_no=&sheet= : the first rows/columns of the machine file, to pick cells from */
    public function grid()
    {
        $reader = new \App\Libraries\MachineFileReader();
        $file   = $reader->findFile((string) $this->request->getGet('folder'), (string) $this->request->getGet('lab_ref_no'));
        if (! $file) {
            return $this->response->setJSON(['ok' => false, 'error' => 'No file found for that Lab Ref in this folder (local cache or the OUTDATA share).', 'csrf' => csrf_hash()]);
        }
        $grid = $reader->grid($file, (string) $this->request->getGet('sheet'));
        $rows = [];
        foreach ($grid as $r => $cells) {
            if ($r > 90) {
                break;
            }
            foreach ($cells as $c => $v) {
                if ($c <= 30 && trim((string) $v) !== '') {
                    $rows[$r][$c] = mb_substr((string) $v, 0, 40);
                }
            }
        }

        return $this->response->setJSON(['ok' => true, 'file' => basename($file), 'sheets' => $reader->sheets($file), 'grid' => $rows, 'csrf' => csrf_hash()]);
    }

    /** POST designer/maps/test {map:{folder,sheet,rules}, lab_ref_no} : dry run, nothing is saved */
    public function testMap()
    {
        $p      = $this->request->getJSON(true) ?? $this->request->getPost();
        $m      = (array) ($p['map'] ?? []);
        $labRef = (string) ($p['lab_ref_no'] ?? '');
        $reader = new \App\Libraries\MachineFileReader();
        $file   = $reader->findFile((string) ($m['folder'] ?? ''), $labRef);
        if (! $file) {
            return $this->response->setJSON(['ok' => false, 'error' => 'No file found for that Lab Ref in this folder.', 'csrf' => csrf_hash()]);
        }
        $fields = $reader->apply($reader->grid($file, (string) ($m['sheet'] ?? '')), $this->cleanRules((array) ($m['rules'] ?? [])), $labRef);

        return $this->response->setJSON(['ok' => true, 'file' => basename($file), 'fields' => $fields, 'csrf' => csrf_hash()]);
    }

    /** @return list<array<string,mixed>> */
    private function cleanRules(array $rules): array
    {
        $key = static fn ($v) => preg_match('/^[A-Za-z0-9_]{1,60}$/', (string) $v) === 1;
        $int = static fn ($v, int $lo = 0, int $hi = 2000) => max($lo, min($hi, (int) $v));
        $out = [];
        foreach (array_slice($rules, 0, 60) as $r) {
            switch ($r['type'] ?? '') {
                case 'row_by_labref':
                    $f = is_array($r['fields'] ?? null) ? $r['fields'] : explode(',', (string) ($r['fields'] ?? ''));
                    $out[] = [
                        'type' => 'row_by_labref', 'labRow' => $int($r['labRow'] ?? 0), 'resultRow' => $int($r['resultRow'] ?? 0),
                        'startCol' => $int($r['startCol'] ?? 3, 1, 200), 'fields' => array_values(array_filter(array_map('trim', $f), $key)),
                        'fallbackFirstCol' => ! empty($r['fallbackFirstCol']),
                    ];
                    break;

                case 'column_range':
                    $pat = (string) ($r['pattern'] ?? '');
                    if (str_contains($pat, '{n}') && $key(str_replace('{n}', '0', $pat))) {
                        $out[] = [
                            'type' => 'column_range', 'col' => $int($r['col'] ?? 0, 0, 200), 'rowFrom' => $int($r['rowFrom'] ?? 0), 'rowTo' => $int($r['rowTo'] ?? 0),
                            'pattern' => $pat, 'maxN' => $int($r['maxN'] ?? 0, 0, 200), 'stats' => preg_replace('/[^A-Za-z0-9_]/', '', (string) ($r['stats'] ?? '')),
                        ];
                    }
                    break;

                case 'cell':
                    if ($key($r['field'] ?? '')) {
                        $c = ['type' => 'cell', 'row' => $int($r['row'] ?? 0), 'col' => $int($r['col'] ?? 0, 0, 200), 'field' => (string) $r['field']];
                        if (! empty($r['afterColon'])) {
                            $c['afterColon'] = true;
                        }
                        if (! empty($r['altCol']) && is_array($r['altWhen'] ?? null) && $key($r['altWhen']['field'] ?? '')) {
                            $c['altCol']  = $int($r['altCol'], 0, 200);
                            $c['altWhen'] = [
                                'field' => (string) $r['altWhen']['field'],
                                'in'    => array_values(array_filter(array_map(static fn ($v) => mb_substr(trim(strip_tags((string) $v)), 0, 40), (array) ($r['altWhen']['in'] ?? [])), 'strlen')),
                            ];
                        }
                        $out[] = $c;
                    }
                    break;

                case 'label_rows':
                    $regex = (string) ($r['labelRegex'] ?? '/^M(\\d+)$/i');
                    if (@preg_match($regex, '') === false || strlen($regex) > 80) {
                        $regex = '/^M(\\d+)$/i';
                    }
                    $cols = [];
                    foreach (array_slice((array) ($r['cols'] ?? []), 0, 12) as $c) {
                        $pat = (string) ($c['pattern'] ?? '');
                        if (is_array($c) && str_contains($pat, '{n}') && $key(str_replace('{n}', '0', $pat))) {
                            $cols[] = ['col' => $int($c['col'] ?? 0, 1, 200), 'pattern' => $pat, 'maxN' => $int($c['maxN'] ?? 45, 1, 200), 'stats' => preg_replace('/[^A-Za-z0-9_]/', '', (string) ($c['stats'] ?? ''))];
                        }
                    }
                    if ($cols) {
                        $out[] = ['type' => 'label_rows', 'labelCol' => $int($r['labelCol'] ?? 1, 1, 200), 'labelRegex' => $regex, 'cols' => $cols];
                    }
                    break;

                case 'point_grid':
                    $reads = [];
                    foreach (array_slice((array) ($r['reads'] ?? []), 0, 12) as $c) {
                        $pat = (string) ($c['pattern'] ?? '');
                        if (is_array($c) && str_contains($pat, '{n}') && $key(str_replace('{n}', '0', $pat))) {
                            $reads[] = ['rowOffset' => $int($c['rowOffset'] ?? 2, 0, 50), 'pattern' => $pat, 'maxN' => $int($c['maxN'] ?? 45, 1, 200), 'stats' => preg_replace('/[^A-Za-z0-9_]/', '', (string) ($c['stats'] ?? ''))];
                        }
                    }
                    if ($reads) {
                        $out[] = ['type' => 'point_grid', 'reads' => $reads];
                    }
                    break;

                case 'header_value':
                    if ($key($r['field'] ?? '') && trim((string) ($r['header'] ?? '')) !== '') {
                        $out[] = ['type' => 'header_value', 'header' => mb_substr(strip_tags((string) $r['header']), 0, 80), 'field' => (string) $r['field'], 'onlyIfMissing' => ! empty($r['onlyIfMissing'])];
                    }
                    break;

                case 'anchor_cell':
                    if ($key($r['field'] ?? '') && trim((string) ($r['anchor'] ?? '')) !== '') {
                        $out[] = [
                            'type' => 'anchor_cell', 'anchor' => mb_substr(strip_tags((string) $r['anchor']), 0, 80),
                            'rowOffset' => $int($r['rowOffset'] ?? 0, -200, 200), 'colOffset' => $int($r['colOffset'] ?? 1, -200, 200), 'field' => (string) $r['field'],
                        ];
                    }
                    break;
            }
        }

        return $out;
    }

    /** @return array{0:string,1:string} */
    private function keys(): array
    {
        $slug = (string) $this->request->getGet('slug');
        $var  = (string) $this->request->getGet('variant');
        if (! in_array($slug, self::SLUGS, true)) {
            $slug = 'bonding';
        }
        if (! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $var)) {
            $var = 'form';
        }

        return [$slug, $var];
    }

    /** Keeps only expected keys/types so stored layouts can never inject markup. */
    private function clean(array $l): array
    {
        $num = static fn ($v) => is_numeric($v) ? round((float) $v, 3) : 0;
        $box = static fn ($b) => is_array($b) ? [
            'pctLeft'   => $num($b['pctLeft'] ?? 0),
            'pctTop'    => $num($b['pctTop'] ?? 0),
            'pctWidth'  => $num($b['pctWidth'] ?? 0),
            'pctHeight' => $num($b['pctHeight'] ?? 0),
        ] : null;
        $key = static fn ($v) => preg_match('/^[A-Za-z0-9_]{1,60}$/', (string) $v) === 1;
        $out = [];

        if (isset($l['image']) && is_array($l['image'])) {
            $out['image'] = $box($l['image']);
        }
        if (isset($l['fito']) && is_array($l['fito'])) {
            $out['fito'] = $box($l['fito']);
        }
        // movable measuring dots + leader lines (drawn on a clean outline instead of the baked-in picture)
        if (! empty($l['vector'])) {
            $out['vector'] = true;
            foreach (['utf', 'fto'] as $k) {
                foreach ((array) ($l['dots'][$k] ?? []) as $n => $d) {
                    if (ctype_digit((string) $n) && (int) $n >= 1 && (int) $n <= 60 && is_array($d)) {
                        $out['dots'][$k][(int) $n] = ['x' => $num($d['x'] ?? 0), 'y' => $num($d['y'] ?? 0)];
                    }
                }
            }
        }
        if (isset($l['start']) && is_array($l['start'])) {
            $out['start'] = ['x' => $num($l['start']['x'] ?? 0), 'y' => $num($l['start']['y'] ?? 0), 'label' => mb_substr(strip_tags((string) ($l['start']['label'] ?? 'START')), 0, 30)];
        }
        if (isset($l['imageFile']) && preg_match('/^[A-Za-z0-9_]{1,40}\.png$/', (string) $l['imageFile'])) {
            $out['imageFile'] = (string) $l['imageFile'];
        }
        foreach (['utf', 'fto'] as $k) {
            foreach ((array) ($l[$k] ?? []) as $n => $b) {
                if (ctype_digit((string) $n) && (int) $n >= 1 && (int) $n <= 60 && ($bx = $box($b))) {
                    $bx['hidden']      = ! empty($b['hidden']);
                    $out[$k][(int) $n] = $bx;
                }
            }
        }

        // form configuration: hidden / renamed / reordered fields and admin-added custom fields
        foreach ((array) ($l['hiddenFields'] ?? []) as $f) {
            if ($key($f)) {
                $out['hiddenFields'][] = (string) $f;
            }
        }
        foreach ((array) ($l['labels'] ?? []) as $f => $t) {
            if ($key($f)) {
                $out['labels'][$f] = mb_substr(strip_tags((string) $t), 0, 80);
            }
        }
        foreach ((array) ($l['order'] ?? []) as $f) {
            if ($key($f)) {
                $out['order'][] = (string) $f;
            }
        }
        foreach ((array) ($l['customFields'] ?? []) as $c) {
            if (is_array($c) && $key($c['key'] ?? '')) {
                $out['customFields'][] = ['key' => (string) $c['key'], 'label' => mb_substr(strip_tags((string) ($c['label'] ?? $c['key'])), 0, 80)];
            }
        }

        // extra report elements
        foreach ((array) ($l['extras'] ?? []) as $e) {
            if (! is_array($e) || ! in_array($e['type'] ?? '', ['text', 'field', 'chart', 'image', 'line'], true)) {
                continue;
            }
            $out['extras'][] = $box($e) + [
                'id'    => preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($e['id'] ?? uniqid('x'))),
                'type'  => $e['type'],
                'text'  => mb_substr(strip_tags((string) ($e['text'] ?? '')), 0, 300),
                'field' => $key($e['field'] ?? '') ? (string) $e['field'] : '',
                'size'  => max(6, min(60, (int) ($e['size'] ?? 12))),
                'bold'  => ! empty($e['bold']),
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#111111',
                'src'   => preg_match('#^assets/[A-Za-z0-9_/.-]{1,200}$#', (string) ($e['src'] ?? '')) ? $e['src'] : '',
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------ file locations (admin settings)

    private const SETTING_KEYS = ['outdata_root', 'bonding_technicians'];

    private function setting(string $key): string
    {
        $row = \Config\Database::connect()->table('tbl_app_settings')->where('skey', $key)->get()->getRowArray();

        return (string) ($row['svalue'] ?? '');
    }

    private function putSetting(string $key, string $value): void
    {
        $t    = \Config\Database::connect()->table('tbl_app_settings');
        $data = ['svalue' => $value, 'updated_by' => (string) ($this->auth->user()['username'] ?? ''), 'updated_at' => date('Y-m-d H:i:s')];
        if ($t->where('skey', $key)->countAllResults() > 0) {
            $t->where('skey', $key)->update($data);
        } else {
            $t->insert($data + ['skey' => $key]);
        }
    }

    /** A UNC share (\\server\share[\folder]) or a drive path (D:\folder): nothing else. */
    private function cleanRoot(string $root): ?string
    {
        $root = rtrim(trim(str_replace('/', '\\', $root)), '\\');
        if ($root === '' || str_contains($root, '..') || strlen($root) > 200) {
            return null;
        }
        if (! preg_match('/^(\\\\\\\\[A-Za-z0-9._-]+\\\\[A-Za-z0-9 ._$#-]+(\\\\[A-Za-z0-9 ._#-]+)*|[A-Za-z]:(\\\\[A-Za-z0-9 ._#-]+)*)$/', $root)) {
            return null;
        }

        return $root . '\\';
    }

    /** GET designer/settings */
    public function settings()
    {
        $maps = \Config\Database::connect()->table('tbl_machine_maps')->select('slug, folder')->distinct()->orderBy('slug')->get()->getResultArray();

        return $this->response->setJSON([
            'ok'          => true,
            'root'        => \App\Libraries\MachineFileReader::root(),
            'default'     => \App\Libraries\MachineFileReader::NET_ROOT,
            'technicians' => json_decode($this->setting('bonding_technicians'), true) ?: [
                'DTP-QC-001' => 'S. Aquino', 'DTP-QC-002' => 'L. Tuddao', 'DTP-QC-048' => 'L. Aquidado',
                'DTP-QC-064' => 'J. Barrera', 'DTP-QC-066' => 'D. Parrilla', 'DTP-QC-067' => 'T. Villalobos',
            ],
            'folders'     => $maps,
            'csrf'        => csrf_hash(),
        ]);
    }

    /** POST designer/settings/save {root, technicians:{code:name}} */
    public function saveSettings()
    {
        $p    = $this->request->getJSON(true) ?? $this->request->getPost();
        $root = $this->cleanRoot((string) ($p['root'] ?? ''));
        if ($root === null) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'The location must look like \\\\server\\share\\folder or D:\\folder.', 'csrf' => csrf_hash()]);
        }
        $this->putSetting('outdata_root', $root);

        if (isset($p['technicians']) && is_array($p['technicians'])) {
            $t = [];
            foreach ($p['technicians'] as $code => $name) {
                if (preg_match('/^[A-Za-z0-9 _-]{1,30}$/', (string) $code) && trim((string) $name) !== '') {
                    $t[(string) $code] = mb_substr(strip_tags((string) $name), 0, 60);
                }
            }
            $this->putSetting('bonding_technicians', json_encode($t, JSON_UNESCAPED_UNICODE));
        }

        return $this->response->setJSON(['ok' => true, 'root' => $root, 'csrf' => csrf_hash()]);
    }

    /** POST designer/settings/test {root}: read-only check of the location and of every configured report folder. */
    public function testSettings()
    {
        $p    = $this->request->getJSON(true) ?? $this->request->getPost();
        $root = $this->cleanRoot((string) ($p['root'] ?? ''));
        if ($root === null) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Invalid location format.', 'csrf' => csrf_hash()]);
        }
        $reachable = @is_dir($root);
        $rows      = [];
        $seen      = [];
        $maps      = \Config\Database::connect()->table('tbl_machine_maps')->select('folder')->get()->getResultArray();
        foreach ($maps as $m) {
            $f = (string) $m['folder'];
            if (isset($seen[$f])) {
                continue;
            }
            $seen[$f] = true;
            $dir      = $root . $f;
            $ok       = $reachable && @is_dir($dir);
            $count    = 0;
            $latest   = 0;
            if ($ok && ($list = @scandir($dir))) {
                foreach ($list as $n) {
                    if (preg_match('/\.(xlsx|csv)$/i', $n)) {
                        $count++;
                        $latest = max($latest, (int) @filemtime($dir . '\\' . $n));
                    }
                }
            }
            $rows[] = ['folder' => $f, 'found' => $ok, 'files' => $count, 'latest' => $latest ? date('Y-m-d H:i', $latest) : ''];
        }

        return $this->response->setJSON(['ok' => true, 'reachable' => $reachable, 'folders' => $rows, 'csrf' => csrf_hash()]);
    }
}
