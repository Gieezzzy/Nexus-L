<?php

namespace App\Controllers;

use App\Libraries\LabDocXlsx;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Laboratory Documents: the registers of the "#" folders of \\192.168.32.130\qc lab files\#Lab Documents (first one: #DTP-Lab-100).
 *
 *   GET  labdocs                 opens the first register
 *   GET  labdocs/(code)          the register as an A4 sheet: view, edit (add / delete rows and columns, change cells), print
 *   POST labdocs/(code)/save     saves the edited register in dbnexus and puts it back into the workbook on the share
 *
 * Everyone who is signed in can view and print; changing a register is for administrators.
 */
class LabDocsController extends BaseController
{
    /** Excel character units the visible columns share (the width of the printed list: 32 + 72) */
    private const SHEET_CHARS = 104.0;

    private function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    private function canEdit(): bool
    {
        return strcasecmp((string) ($this->auth->user()['role'] ?? ''), 'admin') === 0;
    }

    public function index(): ResponseInterface|string
    {
        $row = $this->db()->table('tbl_labdoc_registers')->where('is_active', 1)->orderBy('sort_order')->get(1)->getRowArray();
        if (! $row) {
            return $this->render('labdocs/empty', ['title' => 'Laboratory Documents', 'withCss' => 'labdocs.css']);
        }

        return redirect()->to(site_url('labdocs/' . rawurlencode($row['code'])));
    }

    public function show(string $code): ResponseInterface|string
    {
        $reg = $this->register(rawurldecode($code));
        if (! $reg) {
            return redirect()->to(site_url('labdocs'))->with('error', 'That register is not in the database.');
        }
        if (($reg['view_type'] ?? 'register') === 'methods') {
            return $this->methodsPage($reg);
        }
        $cols = $this->db()->table('tbl_labdoc_columns')->where('register_id', $reg['id'])->orderBy('sort_order')->get()->getResultArray();
        $rows = $this->db()->table('tbl_labdoc_rows')->where('register_id', $reg['id'])->orderBy('sort_order')->get()->getResultArray();
        $log  = $this->db()->table('tbl_labdoc_log')->where('register_id', $reg['id'])->orderBy('id', 'DESC')->get(5)->getResultArray();
        $data = [
            'register' => [
                'code' => $reg['code'], 'title' => $reg['title'], 'companyCn' => $reg['company_cn'], 'companyEn' => $reg['company_en'], 'address' => $reg['address'],
                'listTitle' => $reg['list_title'], 'logoLeft' => $reg['logo_left'] ? base_url($reg['logo_left']) : null, 'logoRight' => $reg['logo_right'] ? base_url($reg['logo_right']) : null,
                'marginMm' => (float) $reg['margin_mm'],
            ],
            'columns' => array_map(static fn ($c) => ['key' => $c['col_key'], 'label' => $c['label'], 'width' => (float) $c['width_pct'], 'align' => $c['align'], 'visible' => (bool) $c['is_visible']], $cols),
            'rows'    => array_map(static fn ($r) => json_decode((string) $r['cells_json'], true) ?: [], $rows),
            'canEdit' => $this->canEdit(),
            'saveUrl' => site_url('labdocs/' . rawurlencode($reg['code']) . '/save'),
        ];

        return $this->render('labdocs/register', [
            'title'   => $reg['code'] . ' ' . $reg['title'],
            'withCss' => 'labdocs.css',
            'withJs'  => ['labdocs.js'],
            'reg'     => $reg,
            'data'    => $data,
            'files'   => json_decode((string) $reg['source_files'], true) ?: [],
            'log'     => $log,
        ]);
    }

    public function save(string $code): ResponseInterface
    {
        if (! $this->canEdit()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Only an administrator can change the registers.', 'csrf' => csrf_hash()]);
        }
        $reg = $this->register(rawurldecode($code));
        if (! $reg) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Register not found.', 'csrf' => csrf_hash()]);
        }
        $in   = $this->request->getJSON(true);
        $cols = is_array($in['columns'] ?? null) ? $in['columns'] : [];
        $rows = is_array($in['rows'] ?? null) ? $in['rows'] : [];
        if ($cols === [] || count($cols) > 30 || count($rows) > 1500) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'A register needs 1 to 30 columns and at most 1,500 rows.', 'csrf' => csrf_hash()]);
        }
        $keys = [];
        $clean = [];
        foreach ($cols as $i => $c) {
            $key = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($c['key'] ?? '')) ?: ('c' . ($i + 1));
            if (isset($keys[$key])) {
                $key .= '_' . ($i + 1);
            }
            $keys[$key] = true;
            $label = trim((string) preg_replace('/\s+/', ' ', mb_substr((string) ($c['label'] ?? ''), 0, 255)));
            $clean[] = ['key' => $key, 'label' => $label !== '' ? $label : 'Column ' . ($i + 1), 'width' => max(4.0, min(80.0, (float) ($c['width'] ?? 20))),
                'align' => in_array($c['align'] ?? '', ['left', 'center', 'right'], true) ? $c['align'] : 'left', 'visible' => ! empty($c['visible'])];
        }
        if (! array_filter($clean, static fn ($c) => $c['visible'])) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'At least one column has to be shown.', 'csrf' => csrf_hash()]);
        }
        $out = [];
        foreach ($rows as $r) {
            $cells = [];
            foreach ($clean as $c) {
                $cells[$c['key']] = mb_substr(str_replace("\r", '', (string) ($r[$c['key']] ?? '')), 0, 2000);
            }
            if (implode('', $cells) !== '') {
                $out[] = $cells;                                       // a row with nothing in it is dropped
            }
        }
        $user = (string) ($this->auth->user()['name'] ?? $this->auth->user()['username'] ?? $this->auth->user()['email'] ?? 'user');
        $now  = date('Y-m-d H:i:s');
        $db   = $this->db();
        $db->transStart();
        $db->table('tbl_labdoc_columns')->where('register_id', $reg['id'])->delete();
        $db->table('tbl_labdoc_rows')->where('register_id', $reg['id'])->delete();
        foreach ($clean as $i => $c) {
            $db->table('tbl_labdoc_columns')->insert(['register_id' => $reg['id'], 'col_key' => $c['key'], 'label' => $c['label'], 'width_pct' => $c['width'], 'align' => $c['align'],
                'is_visible' => $c['visible'] ? 1 : 0, 'sort_order' => $i]);
        }
        foreach ($out as $n => $cells) {
            $db->table('tbl_labdoc_rows')->insert(['register_id' => $reg['id'], 'sort_order' => $n, 'cells_json' => json_encode($cells, JSON_UNESCAPED_UNICODE), 'updated_by' => $user, 'updated_at' => $now]);
        }
        $db->table('tbl_labdoc_registers')->where('id', $reg['id'])->update(['updated_by' => $user, 'updated_at' => $now]);
        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'message' => 'The database could not be updated; nothing was saved.', 'csrf' => csrf_hash()]);
        }

        // the workbook on the share: the visible columns only, widths in proportion
        $visible = array_values(array_filter($clean, static fn ($c) => $c['visible']));
        $sum     = array_sum(array_column($visible, 'width')) ?: 1.0;
        $file    = LabDocXlsx::write(
            (string) $reg['file_path'],
            (string) $reg['sheet_name'],
            array_map(static fn ($c) => ['label' => $c['label'], 'width' => $c['width'] / $sum * self::SHEET_CHARS], $visible),
            array_map(static fn ($cells) => array_map(static fn ($c) => (string) $cells[$c['key']], $visible), $out),
            WRITEPATH . 'labdocs-backups' . DIRECTORY_SEPARATOR . $reg['code'],
        );
        $db->table('tbl_labdoc_registers')->where('id', $reg['id'])->update([
            'file_status' => $file['ok'] ? 'updated' : mb_substr($file['message'], 0, 250), 'file_synced_at' => $file['ok'] ? $now : $reg['file_synced_at'],
        ]);
        $db->table('tbl_labdoc_log')->insert(['register_id' => $reg['id'], 'action' => 'save', 'user_name' => $user, 'created_at' => $now,
            'detail' => mb_substr(count($out) . ' rows, ' . count($clean) . ' columns; file: ' . ($file['ok'] ? 'updated' : $file['message']), 0, 490)]);

        return $this->response->setJSON(['ok' => true, 'rows' => count($out), 'columns' => count($clean), 'file' => $file, 'savedAt' => $now, 'csrf' => csrf_hash()]);
    }


    // ------------------------------------------------------------------------------------------------ test methods (#DTP-Lab-101)

    private function methodsPage(array $reg): string
    {
        $db   = $this->db();
        $ill  = [];
        foreach ($db->table('tbl_vfm_illustrated')->where('register_id', $reg['id'])->orderBy('label')->get()->getResultArray() as $r) {
            $ill[$r['code']][] = ['id' => (int) $r['id'], 'label' => $r['label'], 'url' => site_url('labdocs/' . rawurlencode($reg['code']) . '/ill/' . $r['id'])];
        }
        $rows = $db->table('tbl_vfm_methods')->select('code, series, title, rev_label, rev_date, edited_at, edited_by, pages, revision_no')
            ->where(['register_id' => $reg['id'], 'is_active' => 1])->orderBy('code')->get()->getResultArray();
        $base = site_url('labdocs/' . rawurlencode($reg['code']));
        $cards = array_map(static fn ($m) => [
            'code' => $m['code'], 'series' => $m['series'], 'title' => $m['title'], 'rev' => $m['rev_label'], 'revDate' => $m['rev_date'], 'pages' => (int) $m['pages'],
            'edited' => $m['edited_at'] ? date('d M Y', strtotime((string) $m['edited_at'])) : null, 'url' => $base . '/m/' . rawurlencode($m['code']),
            'ill' => $ill[$m['code']] ?? [],
        ], $rows);
        $docs = array_map(static fn ($d) => ['key' => $d['doc_key'], 'label' => $d['label'], 'kind' => $d['kind'], 'url' => $base . '/doc/' . rawurlencode($d['doc_key'])],
            $db->table('tbl_vfm_docs')->where('register_id', $reg['id'])->orderBy('sort_order')->get()->getResultArray());
        $meta = json_decode((string) $reg['meta_json'], true) ?: [];
        $meta['sections'] = ($meta['sections'] ?? []) + ['H' => 'Sampling and Material Conditioning', 'I' => 'Chemical Analysis and Identification', 'J' => 'Electrical and Thermal Safety'];

        return $this->render('labdocs/methods', [
            'title' => $reg['code'] . ' ' . $reg['title'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-methods.js'],
            'reg' => $reg, 'cards' => $cards, 'docs' => $docs, 'sections' => $meta['sections'] ?? [],
        ]);
    }

    public function method(string $code, string $vf): ResponseInterface|string
    {
        $reg = $this->register(rawurldecode($code));
        $m   = $reg ? $this->vfm($reg, rawurldecode($vf)) : null;
        if (! $reg || ! $m) {
            return redirect()->to(site_url('labdocs'))->with('error', 'That test method is not in the database.');
        }
        $base = site_url('labdocs/' . rawurlencode($reg['code']));
        $ill  = array_map(static fn ($r) => ['label' => $r['label'], 'url' => $base . '/ill/' . $r['id']],
            $this->db()->table('tbl_vfm_illustrated')->where(['register_id' => $reg['id'], 'code' => $m['code']])->orderBy('label')->get()->getResultArray());
        $revs = $this->db()->table('tbl_vfm_revisions')->select('rev_no, note, saved_by, saved_at')->where('method_id', $m['id'])->orderBy('id', 'DESC')->get(8)->getResultArray();
        $doc  = json_decode((string) $m['content_json'], true) ?: ['blocks' => [], 'base' => 11, 'margin' => 72];
        $data = [
            'method' => ['code' => $m['code'], 'title' => $m['title'], 'rev' => $m['rev_label'], 'pages' => (int) $m['pages'], 'revisionNo' => (int) $m['revision_no'],
                'editedBy' => $m['edited_by'], 'editedAt' => $m['edited_at']],
            'doc' => ['base' => (float) ($doc['base'] ?? 11), 'blocks' => $doc['blocks'] ?? []],
            'assets' => base_url('assets/vfm') . '/',
            'logo' => base_url('assets/vfm/_logo.png'),
            'canEdit' => $this->canEdit(), 'saveUrl' => $base . '/m/' . rawurlencode($m['code']) . '/save', 'restoreUrl' => $base . '/m/' . rawurlencode($m['code']) . '/restore',
            'pdfUrl' => $base . '/pdf/' . rawurlencode($m['code']), 'backUrl' => $base, 'illustrated' => $ill,
        ];

        return $this->render('labdocs/method', [
            'title' => $m['code'] . ' ' . $m['title'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-method.js'], 'reg' => $reg, 'm' => $m, 'data' => $data, 'revs' => $revs,
        ]);
    }

    public function saveMethod(string $code, string $vf): ResponseInterface
    {
        if (! $this->canEdit()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Only an administrator can edit a test method.', 'csrf' => csrf_hash()]);
        }
        $reg = $this->register(rawurldecode($code));
        $m   = $reg ? $this->vfm($reg, rawurldecode($vf)) : null;
        if (! $m) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Test method not found.', 'csrf' => csrf_hash()]);
        }
        $in     = $this->request->getJSON(true);
        $blocks = \App\Libraries\VfmHtml::blocks($in['blocks'] ?? null);
        if ($blocks === null || $blocks === []) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'The document is empty or not valid; nothing was saved.', 'csrf' => csrf_hash()]);
        }
        $old  = json_decode((string) $m['content_json'], true) ?: [];
        $json = json_encode(['base' => (float) ($old['base'] ?? 11), 'pages' => (int) ($old['pages'] ?? 0), 'margin' => (int) ($old['margin'] ?? 72), 'blocks' => $blocks], JSON_UNESCAPED_UNICODE);
        $user = (string) ($this->auth->user()['name'] ?? $this->auth->user()['username'] ?? 'user');
        $now  = date('Y-m-d H:i:s');
        $rev  = (int) $m['revision_no'] + 1;
        $db   = $this->db();
        $db->transStart();
        $db->table('tbl_vfm_revisions')->insert(['method_id' => $m['id'], 'rev_no' => $rev, 'content_json' => $json, 'note' => mb_substr((string) ($in['note'] ?? ''), 0, 250), 'saved_by' => $user, 'saved_at' => $now]);
        $db->table('tbl_vfm_methods')->where('id', $m['id'])->update(['content_json' => $json, 'revision_no' => $rev, 'edited_by' => $user, 'edited_at' => $now]);
        $db->table('tbl_labdoc_log')->insert(['register_id' => $reg['id'], 'action' => 'save-method', 'user_name' => $user, 'created_at' => $now, 'detail' => $m['code'] . ' revision ' . $rev . ', ' . count($blocks) . ' blocks']);
        $db->transComplete();
        if (! $db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'message' => 'The database could not be updated; nothing was saved.', 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON(['ok' => true, 'revision' => $rev, 'savedAt' => $now, 'by' => $user, 'csrf' => csrf_hash()]);
    }

    /** puts the document converted from the original PDF back (as a new revision, so nothing is lost) */
    public function restoreMethod(string $code, string $vf): ResponseInterface
    {
        if (! $this->canEdit()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Only an administrator can do that.', 'csrf' => csrf_hash()]);
        }
        $reg = $this->register(rawurldecode($code));
        $m   = $reg ? $this->vfm($reg, rawurldecode($vf)) : null;
        if (! $m || ! $m['original_json']) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'The original is not available.', 'csrf' => csrf_hash()]);
        }
        $user = (string) ($this->auth->user()['name'] ?? 'user');
        $now  = date('Y-m-d H:i:s');
        $rev  = (int) $m['revision_no'] + 1;
        $db   = $this->db();
        $db->table('tbl_vfm_revisions')->insert(['method_id' => $m['id'], 'rev_no' => $rev, 'content_json' => $m['original_json'], 'note' => 'Restored the original (converted from the PDF)', 'saved_by' => $user, 'saved_at' => $now]);
        $db->table('tbl_vfm_methods')->where('id', $m['id'])->update(['content_json' => $m['original_json'], 'revision_no' => $rev, 'edited_by' => $user, 'edited_at' => $now]);

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    public function pdf(string $code, string $vf): ResponseInterface
    {
        $reg = $this->register(rawurldecode($code));
        $m   = $reg ? $this->vfm($reg, rawurldecode($vf)) : null;

        return $m ? $this->serve((string) $m['cache_file'], (string) $m['file_name']) : $this->response->setStatusCode(404)->setBody('Not found');
    }

    public function illustrated(string $code, string $id): ResponseInterface
    {
        $reg = $this->register(rawurldecode($code));
        $r   = $reg ? $this->db()->table('tbl_vfm_illustrated')->where(['register_id' => $reg['id'], 'id' => (int) $id])->get()->getRowArray() : null;

        return $r ? $this->serve((string) $r['cache_file'], (string) $r['file_name']) : $this->response->setStatusCode(404)->setBody('Not found');
    }

    public function doc(string $code, string $key): ResponseInterface|string
    {
        $reg = $this->register(rawurldecode($code));
        $d   = $reg ? $this->db()->table('tbl_vfm_docs')->where(['register_id' => $reg['id'], 'doc_key' => rawurldecode($key)])->get()->getRowArray() : null;
        if (! $d) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        $path = $this->cachePath((string) $d['cache_file']);
        if ($path === null) {
            return $this->response->setStatusCode(404)->setBody('The file is not in the cache; run php spark vfm:import again.');
        }
        if ($d['kind'] === 'xlsx') {
            $sheets = [];
            foreach (array_slice(LabDocXlsx::sheetNames($path), 0, 8) as $i => $name) {
                try {
                    $s = LabDocXlsx::read($path, $i);
                } catch (\Throwable) {
                    continue;
                }
                $sheets[] = ['name' => $name, 'rows' => array_slice($s['rows'], 0, 400, true), 'cols' => $s['max_col']];
            }

            return $this->render('labdocs/docview', ['title' => $d['label'], 'withCss' => 'labdocs.css', 'reg' => $reg, 'd' => $d, 'sheets' => $sheets,
                'fileUrl' => site_url('labdocs/' . rawurlencode($reg['code']) . '/file/' . rawurlencode($d['doc_key']))]);
        }

        return $this->serve((string) $d['cache_file'], (string) $d['file_name'], $d['kind'] !== 'pdf');
    }

    public function file(string $code, string $key): ResponseInterface
    {
        $reg = $this->register(rawurldecode($code));
        $d   = $reg ? $this->db()->table('tbl_vfm_docs')->where(['register_id' => $reg['id'], 'doc_key' => rawurldecode($key)])->get()->getRowArray() : null;

        return $d ? $this->serve((string) $d['cache_file'], (string) $d['file_name'], true) : $this->response->setStatusCode(404)->setBody('Not found');
    }

    /** a file of the cache (writable/labdocs) by its relative name; never anything outside that folder */
    private function cachePath(string $rel): ?string
    {
        $base = realpath(WRITEPATH . 'labdocs');
        $full = $base ? realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel)) : false;

        return ($full && str_starts_with($full, $base . DIRECTORY_SEPARATOR) && is_file($full)) ? $full : null;
    }

    private function serve(string $rel, string $name, bool $download = false): ResponseInterface
    {
        $path = $this->cachePath($rel);
        if ($path === null) {
            return $this->response->setStatusCode(404)->setBody('The file is not in the cache; run php spark vfm:import again.');
        }
        $types = ['pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $this->response->setHeader('Content-Type', $types[$ext] ?? 'application/octet-stream')
            ->setHeader('Content-Disposition', ($download ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', $name) . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')->setBody((string) file_get_contents($path));
    }

    /** @return array<string,mixed>|null */
    private function vfm(array $reg, string $code): ?array
    {
        return $this->db()->table('tbl_vfm_methods')->where(['register_id' => $reg['id'], 'code' => $code, 'is_active' => 1])->get()->getRowArray() ?: null;
    }

    /** @return array<string,mixed>|null */
    private function register(string $code): ?array
    {
        return $this->db()->table('tbl_labdoc_registers')->where('code', $code)->where('is_active', 1)->get()->getRowArray() ?: null;
    }
}
