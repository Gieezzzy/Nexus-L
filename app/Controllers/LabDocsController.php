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

    /** the share the registers live on (forward slashes work for UNC paths in PHP on Windows); the Logs folders are made inside the folder of each register */
    private const SHARE = '//192.168.32.130/qc lab files/#Lab Documents';

    /** the layout: a revision opened beside the original (in a frame) has no menus */
    private function layout(): string
    {
        return $this->request->getGet('embed') ? 'layouts/bare' : 'layouts/main';
    }

    /** the saved version asked for with ?rev= (only for this document), or null */
    private function revSnap(array $reg, string $kind, string $key): ?array
    {
        $id = (int) $this->request->getGet('rev');

        return $id > 0 ? ($this->db()->table('tbl_labdoc_revs')->where(['id' => $id, 'register_id' => $reg['id'], 'kind' => $kind, 'doc_key' => $key, 'log_deleted' => 0])->get()->getRowArray() ?: null) : null;
    }

    /** the list the "Revisions" panel shows (versions whose Logs file was deleted are not listed; their copy stays in the database) */
    private function revList(array $reg, string $kind, string $key, bool $ro): array
    {
        $items = [];
        if (! $ro) {
            foreach ($this->db()->table('tbl_labdoc_revs')->select('id, rev_no, saved_at, saved_by, category')->where(['register_id' => $reg['id'], 'kind' => $kind, 'doc_key' => $key, 'log_deleted' => 0])
                ->orderBy('rev_no', 'DESC')->get()->getResultArray() as $r) {
                $items[] = ['id' => (int) $r['id'], 'no' => (int) $r['rev_no'], 'at' => date('d M Y H:i', strtotime((string) $r['saved_at'])), 'by' => (string) $r['saved_by'], 'cat' => $r['category']];
            }
        }

        return ['items' => $items, 'ro' => $ro, 'canDelete' => $this->canEdit(), 'delUrl' => site_url('labdocs/' . rawurlencode($reg['code']) . '/rev/')];
    }

    /**
     * Keeps the version that is about to be replaced: in the database (for good) and in <folder>/Logs/<category>/Rev<N> on the share
     * (the previous Excel file itself, or the document as a json file). The share is not needed for the save to succeed.
     *
     * @param array|string $snapshot
     */
    private function archive(array $reg, string $kind, string $key, string $category, $snapshot, ?string $copyFrom, string $user): void
    {
        $db  = $this->db();
        $n   = 1 + (int) ($db->table('tbl_labdoc_revs')->selectMax('rev_no')->where(['register_id' => $reg['id'], 'kind' => $kind, 'doc_key' => $key])->get()->getRow()->rev_no ?? 0);
        $cat = trim((string) preg_replace('/[^A-Za-z0-9 ._#()+-]+/', '-', $category)) ?: $kind;
        $dir = self::SHARE . '/' . $reg['folder_name'] . '/Logs/' . $cat . '/Rev' . $n;
        $json = is_string($snapshot) ? $snapshot : json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        $ok  = false;
        try {
            if (is_dir($dir) || @mkdir($dir, 0775, true)) {
                $ok = @file_put_contents($dir . '/' . $cat . ' Rev' . $n . '.json', (string) $json) !== false;
                if ($copyFrom && is_file($copyFrom)) {
                    $ok = @copy($copyFrom, $dir . '/' . basename(str_replace('/', DIRECTORY_SEPARATOR, $copyFrom))) && $ok;
                }
            }
        } catch (\Throwable) {
            $ok = false;
        }
        $db->table('tbl_labdoc_revs')->insert(['register_id' => $reg['id'], 'kind' => $kind, 'doc_key' => $key, 'category' => mb_substr($category, 0, 250), 'rev_no' => $n, 'snapshot_json' => $json,
            'log_file' => $ok ? mb_substr($dir, 0, 690) : '', 'saved_by' => $user, 'saved_at' => date('Y-m-d H:i:s')]);
    }

    /** the trash of the Revisions panel: the files in Logs are deleted, the copy in the database stays */
    public function deleteRev(string $code, string $id): ResponseInterface
    {
        if (! $this->canEdit()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Only an administrator can delete a revision.', 'csrf' => csrf_hash()]);
        }
        $reg = $this->register(rawurldecode($code));
        $r   = $reg ? $this->db()->table('tbl_labdoc_revs')->where(['id' => (int) $id, 'register_id' => $reg['id'], 'log_deleted' => 0])->get()->getRowArray() : null;
        if (! $r) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Revision not found.', 'csrf' => csrf_hash()]);
        }
        $dir  = (string) $r['log_file'];
        $note = 'no file in Logs';
        if ($dir !== '' && str_starts_with($dir, self::SHARE . '/') && str_contains($dir, '/Logs/') && ! str_contains($dir, '..') && is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            $note = @rmdir($dir) ? 'Logs folder deleted' : 'Logs files deleted';
        }
        $user = (string) ($this->auth->user()['name'] ?? $this->auth->user()['username'] ?? 'user');
        $this->db()->table('tbl_labdoc_revs')->where('id', $r['id'])->update(['log_deleted' => 1, 'deleted_by' => $user, 'deleted_at' => date('Y-m-d H:i:s')]);
        $this->db()->table('tbl_labdoc_log')->insert(['register_id' => $reg['id'], 'action' => 'delete-rev', 'user_name' => $user, 'created_at' => date('Y-m-d H:i:s'),
            'detail' => mb_substr($r['category'] . ' Rev' . $r['rev_no'] . ': ' . $note . '; the copy in the database is kept', 0, 490)]);

        return $this->response->setJSON(['ok' => true, 'message' => $note, 'csrf' => csrf_hash()]);
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
        if (($reg['view_type'] ?? 'register') === 'books') {
            return $this->booksPage($reg);
        }
        if (($reg['view_type'] ?? 'register') === 'specs') {
            return $this->specsPage($reg);
        }
        if (($reg['view_type'] ?? 'register') === 'files') {
            return $this->filesPage($reg);
        }
        $cols = $this->db()->table('tbl_labdoc_columns')->where('register_id', $reg['id'])->orderBy('sort_order')->get()->getResultArray();
        $rows = $this->db()->table('tbl_labdoc_rows')->where('register_id', $reg['id'])->orderBy('sort_order')->get()->getResultArray();
        $log  = $this->db()->table('tbl_labdoc_log')->where('register_id', $reg['id'])->orderBy('id', 'DESC')->get(5)->getResultArray();
        $snap = $this->revSnap($reg, 'register', '');
        if ($snap) {
            $sn   = json_decode((string) $snap['snapshot_json'], true) ?: [];
            $cols = $sn['cols'] ?? $cols;
            $rows = $sn['rows'] ?? $rows;
        }
        $data = [
            'register' => [
                'code' => $reg['code'], 'title' => $reg['title'], 'companyCn' => $reg['company_cn'], 'companyEn' => $reg['company_en'], 'address' => $reg['address'],
                'listTitle' => $reg['list_title'], 'logoLeft' => $reg['logo_left'] ? base_url($reg['logo_left']) : null, 'logoRight' => $reg['logo_right'] ? base_url($reg['logo_right']) : null,
                'marginMm' => (float) $reg['margin_mm'],
            ],
            'columns' => array_map(static fn ($c) => ['key' => $c['col_key'], 'label' => $c['label'], 'width' => (float) $c['width_pct'], 'align' => $c['align'], 'visible' => (bool) $c['is_visible']], $cols),
            'rows'    => array_map(static fn ($r) => json_decode((string) $r['cells_json'], true) ?: [], $rows),
            'canEdit' => $snap ? false : $this->canEdit(),
            'saveUrl' => site_url('labdocs/' . rawurlencode($reg['code']) . '/save'),
        ];

        return $this->render('labdocs/register', [
            'labRevs' => $this->revList($reg, 'register', '', (bool) $snap),
            'title'   => $reg['code'] . ' ' . $reg['title'],
            'withCss' => 'labdocs.css',
            'withJs'  => ['labdocs-zoom.js', 'labdocs-search.js', 'labdocs-revs.js', 'labdocs.js'],
            'reg'     => $reg,
            'data'    => $data,
            'files'   => json_decode((string) $reg['source_files'], true) ?: [],
            'log'     => $log,
        ], $this->layout());
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
        $this->archive($reg, 'register', '', (string) $reg['code'], [
            'cols' => $db->table('tbl_labdoc_columns')->where('register_id', $reg['id'])->orderBy('sort_order')->get()->getResultArray(),
            'rows' => $db->table('tbl_labdoc_rows')->where('register_id', $reg['id'])->orderBy('sort_order')->get()->getResultArray(),
        ], (string) $reg['file_path'], $user);
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
        $tm   = $this->thumbMap($reg);
        $rows = $db->table('tbl_vfm_methods')->select('code, series, title, rev_label, rev_date, edited_at, edited_by, pages, revision_no, cache_file')
            ->where(['register_id' => $reg['id'], 'is_active' => 1])->orderBy('code')->get()->getResultArray();
        $base = site_url('labdocs/' . rawurlencode($reg['code']));
        $cards = array_map(static fn ($m) => [
            'code' => $m['code'], 'series' => $m['series'], 'title' => $m['title'], 'rev' => $m['rev_label'], 'revDate' => $m['rev_date'], 'pages' => (int) $m['pages'],
            'edited' => $m['edited_at'] ? date('d M Y', strtotime((string) $m['edited_at'])) : null, 'url' => $base . '/m/' . rawurlencode($m['code']),
            'ill' => $ill[$m['code']] ?? [], 'thumb' => $tm[$m['cache_file']] ?? null,
        ], $rows);
        $docs = array_map(static fn ($d) => ['key' => $d['doc_key'], 'label' => $d['label'], 'kind' => $d['kind'], 'url' => $base . '/doc/' . rawurlencode($d['doc_key'])],
            $db->table('tbl_vfm_docs')->where('register_id', $reg['id'])->orderBy('sort_order')->get()->getResultArray());
        $meta = json_decode((string) $reg['meta_json'], true) ?: [];
        $meta['sections'] = ($meta['sections'] ?? []) + ['H' => 'Sampling and Material Conditioning', 'I' => 'Chemical Analysis and Identification', 'J' => 'Electrical and Thermal Safety'];

        return $this->render('labdocs/methods', [
            'title' => $reg['code'] . ' ' . $reg['title'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-methods.js', 'labdocs-preview.js'],
            'reg' => $reg, 'cards' => $cards, 'docs' => $docs, 'sections' => $meta['sections'] ?? [],
        ]);
    }

    /** #DTP-Lab-103: one card per material specification sheet (name and year) and the Excel files as links */
    private function specsPage(array $reg): string
    {
        $db   = $this->db();
        $base = site_url('labdocs/' . rawurlencode($reg['code']));
        $tm   = $this->thumbMap($reg);
        $cards = array_map(static fn ($m) => [
            'code' => $m['code'], 'name' => $m['title'], 'year' => $m['rev_label'], 'pages' => (int) $m['pages'], 'group' => $m['series'],
            'edited' => $m['edited_at'] ? date('d M Y', strtotime((string) $m['edited_at'])) : null, 'url' => $base . '/m/' . rawurlencode($m['code']), 'thumb' => $tm[$m['cache_file']] ?? null,
        ], $db->table('tbl_vfm_methods')->select('code, series, title, rev_label, edited_at, pages, cache_file')->where(['register_id' => $reg['id'], 'is_active' => 1])->orderBy('title')->get()->getResultArray());
        $docs = array_map(static fn ($d) => ['key' => $d['doc_key'], 'label' => $d['label'], 'kind' => $d['kind'], 'url' => $base . '/doc/' . rawurlencode($d['doc_key'])],
            $db->table('tbl_vfm_docs')->where('register_id', $reg['id'])->orderBy('sort_order')->get()->getResultArray());

        return $this->render('labdocs/specs', ['title' => $reg['code'] . ' ' . $reg['title'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-methods.js', 'labdocs-preview.js'], 'reg' => $reg, 'cards' => $cards, 'docs' => $docs]);
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
        $snap = $this->revSnap($reg, 'method', $m['code']);
        $doc  = json_decode((string) ($snap['snapshot_json'] ?? $m['content_json']), true) ?: ['blocks' => [], 'base' => 11, 'margin' => 72];
        $data = [
            'method' => ['code' => $m['code'], 'title' => $m['title'], 'rev' => $m['rev_label'], 'pages' => (int) $m['pages'], 'revisionNo' => (int) $m['revision_no'],
                'editedBy' => $m['edited_by'], 'editedAt' => $m['edited_at'], 'spec' => ($reg['view_type'] ?? '') === 'specs', 'isRev' => (bool) $snap, 'header' => (string) ($doc['headerTitle'] ?? '')],
            'doc' => ['base' => (float) ($doc['base'] ?? 11), 'orient' => (string) ($doc['orient'] ?? 'portrait'), 'blocks' => $doc['blocks'] ?? []],
            'assets' => base_url('assets/vfm') . '/',
            'logo' => base_url('assets/vfm/_logo.png'),
            'canEdit' => $snap ? false : $this->canEdit(), 'saveUrl' => $base . '/m/' . rawurlencode($m['code']) . '/save', 'restoreUrl' => $base . '/m/' . rawurlencode($m['code']) . '/restore',
            'pdfUrl' => $base . '/pdf/' . rawurlencode($m['code']), 'backUrl' => $base, 'illustrated' => $ill,
        ];

        return $this->render('labdocs/method', [
            'title' => $m['code'] . ' ' . $m['title'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-zoom.js', 'labdocs-search.js', 'labdocs-revs.js', 'labdocs-method.js'], 'reg' => $reg, 'm' => $m, 'data' => $data, 'revs' => $revs,
            'labRevs' => $this->revList($reg, 'method', $m['code'], (bool) $snap),
        ], $this->layout());
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
        $json = json_encode(['base' => (float) ($old['base'] ?? 11), 'pages' => (int) ($old['pages'] ?? 0), 'margin' => (int) ($old['margin'] ?? 72), 'orient' => $old['orient'] ?? 'portrait',
            'headerTitle' => $old['headerTitle'] ?? '', 'blocks' => $blocks], JSON_UNESCAPED_UNICODE);
        $user = (string) ($this->auth->user()['name'] ?? $this->auth->user()['username'] ?? 'user');
        $now  = date('Y-m-d H:i:s');
        $rev  = (int) $m['revision_no'] + 1;
        $db   = $this->db();
        $this->archive($reg, 'method', $m['code'], (string) $m['title'] !== '' ? $m['code'] . ' ' . $m['title'] : $m['code'], (string) $m['content_json'], null, $user);
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
        $this->archive($reg, 'method', $m['code'], (string) $m['title'] !== '' ? $m['code'] . ' ' . $m['title'] : $m['code'], (string) $m['content_json'], null, $user);
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
            $f = $this->db()->table('tbl_labdoc_files')->select('id, ext')->where('src_key', $rel)->get()->getRowArray();
            if ($f) {
                return $this->sendDbFile((int) $f['id'], $name, $download);
            }

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


    // ------------------------------------------------------------------------------------------------ folders of files (#DTP-Lab-108)

    private const TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel', 'zip' => 'application/zip'];

    /** one level of the folder tree: its folders and files as cards; ?p= is the folder, ?q= a search over every folder */
    private function filesPage(array $reg): string
    {
        $db   = $this->db();
        $base = site_url('labdocs/' . rawurlencode($reg['code']));
        $p    = trim(str_replace('\\', '/', (string) $this->request->getGet('p')), '/');
        $q    = trim((string) $this->request->getGet('q'));
        if (str_contains($p, '..')) {
            $p = '';
        }
        $sel = 'id, folder, name, ext, file_size, file_mtime, (thumb IS NOT NULL) AS has_thumb';
        $folders = [];
        if ($q !== '') {
            $rows = $db->table('tbl_labdoc_files')->select($sel)->where('register_id', $reg['id'])->groupStart()->like('name', $q)->orLike('folder', $q)->groupEnd()->orderBy('folder')->orderBy('name')->get(300)->getResultArray();
        } else {
            $rows = $db->table('tbl_labdoc_files')->select($sel)->where(['register_id' => $reg['id'], 'folder' => $p])->orderBy('name')->get()->getResultArray();
            $like = $p === '' ? '' : $p . '/';
            $all  = $db->table('tbl_labdoc_files')->select('folder, COUNT(*) AS n')->where('register_id', $reg['id'])->groupBy('folder')->get()->getResultArray();
            foreach ($all as $a) {
                if ($a['folder'] === '' || ($like !== '' && ! str_starts_with($a['folder'], $like)) || $a['folder'] === $p) {
                    continue;
                }
                $name = explode('/', substr($a['folder'], strlen($like)))[0];
                $folders[$name] = ($folders[$name] ?? 0) + (int) $a['n'];
            }
            ksort($folders, SORT_NATURAL | SORT_FLAG_CASE);
        }
        usort($rows, static fn ($a, $b) => strnatcasecmp($a['folder'] . '/' . $a['name'], $b['folder'] . '/' . $b['name']));
        $bookKeys = array_column($db->table('tbl_labdoc_books')->select('book_key')->where('register_id', $reg['id'])->get()->getResultArray(), 'book_key');
        $files    = array_map(static function ($f) use ($base, $bookKeys) {
            $view = in_array($f['ext'], ['pdf', 'jpg', 'jpeg', 'png', 'gif'], true);
            $url  = $view ? $base . '/v/' . $f['id'] : (in_array('f' . $f['id'], $bookKeys, true) ? $base . '/b/f' . $f['id'] : $base . '/f/' . $f['id'] . '?dl=1');

            return ['name' => $f['name'], 'ext' => $f['ext'], 'folder' => $f['folder'], 'size' => (int) $f['file_size'], 'date' => $f['file_mtime'] ? date('d M Y', strtotime((string) $f['file_mtime'])) : '',
                'url' => $url, 'open' => $view || in_array('f' . $f['id'], $bookKeys, true), 'thumb' => $f['has_thumb'] ? $base . '/thumb/' . $f['id'] : null];
        }, $rows);
        $parent = $p === '' ? '' : (str_contains($p, '/') ? $base . '?p=' . rawurlencode(substr($p, 0, (int) strrpos($p, '/'))) : $base);
        $crumbs = [];
        $acc    = '';
        foreach ($p === '' ? [] : explode('/', $p) as $seg) {
            $acc     .= ($acc === '' ? '' : '/') . $seg;
            $crumbs[] = ['name' => $seg, 'url' => $base . '?p=' . rawurlencode($acc)];
        }
        $fl = [];
        foreach ($folders as $name => $n) {
            $fl[] = ['name' => $name, 'count' => $n, 'url' => $base . '?p=' . rawurlencode(($p === '' ? '' : $p . '/') . $name)];
        }

        return $this->render('labdocs/files', ['title' => $reg['code'] . ' ' . $reg['title'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-preview.js'],
            'reg' => $reg, 'folders' => $fl, 'files' => $files, 'crumbs' => $crumbs, 'q' => $q, 'base' => $base, 'parent' => $q !== '' ? $base : $parent]);
    }

    /** a PDF or picture of a folder register in the same page frame as the other documents: back to its folder, previous / next file, zoom, print, download */
    public function fileView(string $code, string $id): ResponseInterface|string
    {
        $reg = $this->register(rawurldecode($code));
        $db  = $this->db();
        $f   = $reg ? $db->table('tbl_labdoc_files')->select('id, folder, name, ext, file_size, file_mtime')->where(['id' => (int) $id, 'register_id' => $reg['id']])->get()->getRowArray() : null;
        if (! $f) {
            return redirect()->to(site_url('labdocs'))->with('error', 'That file is not in the database.');
        }
        $base = site_url('labdocs/' . rawurlencode($reg['code']));
        $sib  = $db->table('tbl_labdoc_files')->select('id, name')->where(['register_id' => $reg['id'], 'folder' => $f['folder']])->whereIn('ext', ['pdf', 'jpg', 'jpeg', 'png', 'gif'])->get()->getResultArray();
        usort($sib, static fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        $ids = array_column($sib, 'id');
        $at  = array_search((string) $f['id'], array_map('strval', $ids), true);
        $nav = static fn ($i) => isset($ids[$i]) ? $base . '/v/' . $ids[$i] : null;
        $data = [
            'file' => ['id' => (int) $f['id'], 'name' => $f['name'], 'ext' => $f['ext'], 'folder' => $f['folder'], 'size' => (int) $f['file_size'], 'at' => $at === false ? 0 : $at + 1, 'of' => count($ids),
                'date' => $f['file_mtime'] ? date('d M Y H:i', strtotime((string) $f['file_mtime'])) : ''],
            'pdf' => $f['ext'] === 'pdf', 'src' => $base . '/f/' . $f['id'], 'download' => $base . '/f/' . $f['id'] . '?dl=1',
            'prev' => $at === false ? null : $nav($at - 1), 'next' => $at === false ? null : $nav($at + 1),
            'backUrl' => $f['folder'] === '' ? $base : $base . '?p=' . rawurlencode($f['folder']),
        ];

        return $this->render('labdocs/fileview', ['title' => $f['name'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-zoom.js', 'labdocs-revs.js', 'labdocs-fileview.js'], 'reg' => $reg, 'data' => $data,
            'labRevs' => $this->revList($reg, 'file', (string) $f['id'], false)], $this->layout());
    }

    /** a file of a folder register, from the database copy */
    public function fileGet(string $code, string $id): ResponseInterface
    {
        $reg = $this->register(rawurldecode($code));
        $f   = $reg ? $this->db()->table('tbl_labdoc_files')->select('id, name, ext')->where(['id' => (int) $id, 'register_id' => $reg['id']])->get()->getRowArray() : null;

        return $f ? $this->sendDbFile((int) $f['id'], (string) $f['name'], $this->request->getGet('dl') !== null || ! in_array($f['ext'], ['pdf', 'jpg', 'jpeg', 'png', 'gif'], true)) : $this->response->setStatusCode(404)->setBody('Not found');
    }

    /** the preview picture of a file (first page of a PDF, small copy of a picture) */
    public function thumb(string $code, string $id): ResponseInterface
    {
        $reg = $this->register(rawurldecode($code));
        $f   = $reg ? $this->db()->table('tbl_labdoc_files')->select('thumb')->where(['id' => (int) $id, 'register_id' => $reg['id']])->get()->getRowArray() : null;
        if (! $f || $f['thumb'] === null) {
            return $this->response->setStatusCode(404)->setBody('No preview');
        }

        return $this->response->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'private, max-age=86400')->setHeader('X-Content-Type-Options', 'nosniff')->setBody($f['thumb']);
    }

    private function sendDbFile(int $id, string $name, bool $download): ResponseInterface
    {
        $f = $this->db()->table('tbl_labdoc_files')->select('ext')->where('id', $id)->get()->getRowArray();
        if (! $f) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        $body = '';
        foreach ($this->db()->table('tbl_labdoc_file_parts')->select('data')->where('file_id', $id)->orderBy('part')->get()->getResultArray() as $part) {
            $body .= $part['data'];
        }

        return $this->response->setHeader('Content-Type', self::TYPES[$f['ext']] ?? 'application/octet-stream')
            ->setHeader('Content-Disposition', ($download ? 'attachment' : 'inline') . "; filename*=UTF-8''" . rawurlencode($name))->setHeader('X-Content-Type-Options', 'nosniff')->setBody($body);
    }

    /** thumbnail addresses for the cards of 101 / 103: cache name => url */
    private function thumbMap(array $reg): array
    {
        $base = site_url('labdocs/' . rawurlencode($reg['code']));
        $out  = [];
        foreach ($this->db()->table('tbl_labdoc_files')->select('id, src_key')->where('register_id', $reg['id'])->where('src_key !=', '')->where('thumb IS NOT NULL', null, false)->get()->getResultArray() as $r) {
            $out[$r['src_key']] = $base . '/thumb/' . $r['id'];
        }

        return $out;
    }

    // ------------------------------------------------------------------------------------------------ workbooks (#DTP-Lab-102)

    private function booksPage(array $reg): ResponseInterface|string
    {
        $db    = $this->db();
        $base  = site_url('labdocs/' . rawurlencode($reg['code']));
        $one   = $db->table('tbl_labdoc_books')->select('book_key')->where('register_id', $reg['id'])->get()->getResultArray();
        if (count($one) === 1) {                                    // a folder with one file shows its content straight away
            return redirect()->to($base . '/b/' . rawurlencode($one[0]['book_key']));
        }
        $books = [];
        foreach ($db->table('tbl_labdoc_books')->where('register_id', $reg['id'])->orderBy('sort_order')->get()->getResultArray() as $b) {
            $sheets = $db->table('tbl_labdoc_sheets')->select('name, revision_no, edited_at, model_json')->where('book_id', $b['id'])->orderBy('idx')->get()->getResultArray();
            $rows   = 0;
            $edited = null;
            foreach ($sheets as $sh) {
                $m    = json_decode((string) $sh['model_json'], true) ?: [];
                $rows += count($m['grid'] ?? []);
                if ($sh['edited_at'] && ($edited === null || $sh['edited_at'] > $edited)) {
                    $edited = $sh['edited_at'];
                }
            }
            $books[] = ['key' => $b['book_key'], 'label' => $b['label'], 'file' => $b['file_name'], 'sheets' => array_column($sheets, 'name'), 'rows' => $rows,
                'modified' => $b['file_mtime'] ? date('d M Y', strtotime((string) $b['file_mtime'])) : null, 'edited' => $edited ? date('d M Y', strtotime((string) $edited)) : null,
                'status' => $b['file_status'], 'url' => $base . '/b/' . rawurlencode($b['book_key'])];
        }

        return $this->render('labdocs/books', ['title' => $reg['code'] . ' ' . $reg['title'], 'withCss' => 'labdocs.css', 'reg' => $reg, 'books' => $books]);
    }

    public function book(string $code, string $key): ResponseInterface|string
    {
        $reg = $this->register(rawurldecode($code));
        $b   = $reg ? $this->db()->table('tbl_labdoc_books')->where(['register_id' => $reg['id'], 'book_key' => rawurldecode($key)])->get()->getRowArray() : null;
        if (! $reg || ! $b) {
            return redirect()->to(site_url('labdocs'))->with('error', 'That workbook is not in the database.');
        }
        $sheets = [];
        $snap   = $this->revSnap($reg, 'book', $b['book_key']);
        if ($snap) {
            foreach (json_decode((string) $snap['snapshot_json'], true) ?: [] as $m) {
                $sheets[] = $m;
            }
        } else {
            foreach ($this->db()->table('tbl_labdoc_sheets')->where('book_id', $b['id'])->orderBy('idx')->get()->getResultArray() as $sh) {
                $m              = json_decode((string) $sh['model_json'], true) ?: [];
                $m['rev']       = (int) $sh['revision_no'];
                $sheets[]       = $m;
            }
        }
        $base = site_url('labdocs/' . rawurlencode($reg['code']));
        $data = [
            'book' => ['key' => $b['book_key'], 'label' => $b['label'], 'file' => $b['file_name'], 'status' => $b['file_status'], 'updatedAt' => $b['updated_at'], 'updatedBy' => $b['updated_by']],
            'styles' => json_decode((string) $b['styles_json'], true) ?: [], 'sheets' => $sheets, 'assets' => base_url('assets/labdocs/books/' . $b['book_key']) . '/',
            'canEdit' => $snap ? false : $this->canEdit(), 'saveUrl' => $base . '/b/' . rawurlencode($b['book_key']) . '/save',
            'backUrl' => $this->bookBack($reg, $b, $base),
        ];

        return $this->render('labdocs/book', ['title' => $b['label'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-zoom.js', 'labdocs-search.js', 'labdocs-revs.js', 'labdocs-book.js'], 'reg' => $reg, 'b' => $b, 'data' => $data,
            'labRevs' => $this->revList($reg, 'book', $b['book_key'], (bool) $snap)], $this->layout());
    }

    /** where "back" goes from a workbook: the folder it sits in (folder registers), the list of workbooks (several), or nowhere (one) */
    private function bookBack(array $reg, array $b, string $base): string
    {
        if (($reg['view_type'] ?? '') === 'files' && preg_match('/^f(\d+)$/', (string) $b['book_key'], $m)) {
            $f = $this->db()->table('tbl_labdoc_files')->select('folder')->where('id', (int) $m[1])->get()->getRowArray();

            return $f && $f['folder'] !== '' ? $base . '?p=' . rawurlencode($f['folder']) : $base;
        }

        return $this->db()->table('tbl_labdoc_books')->where('register_id', $reg['id'])->countAllResults() > 1 ? $base : '';
    }

    public function saveBook(string $code, string $key): ResponseInterface
    {
        $fail = fn (int $status, string $msg) => $this->response->setStatusCode($status)->setJSON(['ok' => false, 'message' => $msg, 'csrf' => csrf_hash()]);
        if (! $this->canEdit()) {
            return $fail(403, 'Only an administrator can change the workbooks.');
        }
        $reg = $this->register(rawurldecode($code));
        $db  = $this->db();
        $b   = $reg ? $db->table('tbl_labdoc_books')->where(['register_id' => $reg['id'], 'book_key' => rawurldecode($key)])->get()->getRowArray() : null;
        if (! $b) {
            return $fail(404, 'Workbook not found.');
        }
        $in    = $this->request->getJSON(true);
        $nStyl = count(json_decode((string) $b['styles_json'], true) ?: []);
        $sent  = is_array($in['sheets'] ?? null) ? $in['sheets'] : [];
        $clean = [];
        foreach ($sent as $idx => $m) {
            $sheet = $db->table('tbl_labdoc_sheets')->where(['book_id' => $b['id'], 'idx' => (int) $idx])->get()->getRowArray();
            $model = $sheet ? $this->cleanSheet($m, json_decode((string) $sheet['model_json'], true) ?: [], $nStyl) : null;
            if ($model === null) {
                return $fail(422, 'Sheet ' . ((int) $idx + 1) . ' is not valid (size, columns or merged cells); nothing was saved.');
            }
            $clean[(int) $idx] = $model;
        }
        if (! $clean) {
            return $fail(422, 'Nothing to save.');
        }
        $user = (string) ($this->auth->user()['name'] ?? $this->auth->user()['username'] ?? 'user');
        $now  = date('Y-m-d H:i:s');

        // the version that is replaced: every sheet of the workbook and the Excel file as it is now, into Logs
        $prev = [];
        foreach ($db->table('tbl_labdoc_sheets')->where('book_id', $b['id'])->orderBy('idx')->get()->getResultArray() as $sh) {
            $pm          = json_decode((string) $sh['model_json'], true) ?: [];
            $pm['rev']   = (int) $sh['revision_no'];
            $prev[]      = $pm;
        }
        $this->archive($reg, 'book', $b['book_key'], (string) $b['label'], $prev, (string) $b['file_path'], $user);

        // the file was deleted from the share: the copy kept in the database is put back first, then it is changed as usual
        if (! is_file((string) $b['file_path'])) {
            $blob = '';
            foreach ($db->table('tbl_labdoc_book_parts')->where('book_id', $b['id'])->orderBy('part')->get()->getResultArray() as $part) {
                $blob .= $part['data'];
            }
            if ($blob !== '') {
                @mkdir(dirname((string) $b['file_path']), 0775, true);
                @file_put_contents((string) $b['file_path'], $blob);
            }
        }

        // the workbook on the share first; the database keeps what the file now holds
        $file = \App\Libraries\XlsxSheets::write((string) $b['file_path'], $clean, WRITEPATH . 'labdocs-backups' . DIRECTORY_SEPARATOR . $reg['code'] . DIRECTORY_SEPARATOR . $b['book_key']);
        $back = [];
        $db->transStart();
        foreach ($clean as $idx => $model) {
            $sheet = $db->table('tbl_labdoc_sheets')->where(['book_id' => $b['id'], 'idx' => $idx])->get()->getRowArray();
            $db->table('tbl_labdoc_sheet_rev')->insert(['sheet_id' => $sheet['id'], 'rev_no' => (int) $sheet['revision_no'], 'model_json' => $sheet['model_json'], 'saved_by' => $user, 'saved_at' => $now]);
            $stored = $model;
            if (! empty($file['ok']) && isset($file['read']['sheets'][$idx])) {
                $stored = $file['read']['sheets'][$idx];                    // what the file holds now (positions refreshed)
            }
            $stored['images'] = $model['images'];                           // the pictures are not read back (they are not edited here)
            $json = json_encode($stored, JSON_UNESCAPED_UNICODE);
            $db->table('tbl_labdoc_sheets')->where('id', $sheet['id'])->update(['model_json' => $json, 'revision_no' => (int) $sheet['revision_no'] + 1, 'edited_by' => $user, 'edited_at' => $now, 'name' => $stored['name'] ?? $sheet['name']]);
            $stored['rev'] = (int) $sheet['revision_no'] + 1;
            $back[$idx]    = $stored;
        }
        $db->table('tbl_labdoc_books')->where('id', $b['id'])->update(['updated_by' => $user, 'updated_at' => $now, 'file_status' => $file['ok'] ? 'updated' : mb_substr($file['message'], 0, 250),
            'file_synced_at' => $file['ok'] ? $now : $b['file_synced_at']]);
        $db->table('tbl_labdoc_log')->insert(['register_id' => $reg['id'], 'action' => 'save-book', 'user_name' => $user, 'created_at' => $now,
            'detail' => mb_substr($b['file_name'] . ': sheets ' . implode(',', array_map(static fn ($i) => $i + 1, array_keys($clean))) . '; file: ' . ($file['ok'] ? 'updated' : $file['message']), 0, 490)]);
        $db->transComplete();
        if (! empty($file['ok']) && is_file((string) $b['file_path'])) {            // the copy of the Excel file in the database follows the file
            $db->table('tbl_labdoc_book_parts')->where('book_id', $b['id'])->delete();
            foreach (str_split((string) file_get_contents((string) $b['file_path']), 400000) as $pi => $piece) {
                $db->table('tbl_labdoc_book_parts')->insert(['book_id' => $b['id'], 'part' => $pi, 'data' => $piece]);
            }
            $db->table('tbl_labdoc_books')->where('id', $b['id'])->update(['file_size' => (int) filesize((string) $b['file_path'])]);
        }
        if (! $db->transStatus()) {
            return $fail(500, 'The database could not be updated.' . ($file['ok'] ? ' The file on the share WAS updated; save again to bring the database level.' : ''));
        }

        return $this->response->setJSON(['ok' => true, 'file' => ['ok' => $file['ok'], 'message' => $file['message']], 'sheets' => $back, 'savedAt' => $now, 'by' => $user, 'csrf' => csrf_hash()]);
    }

    /**
     * An edited sheet from the browser, checked and reduced to what a sheet may hold; null when it is not usable.
     *
     * @param mixed                $m
     * @param array<string,mixed>  $stored the sheet as it is in the database (the pictures are taken from there)
     */
    private function cleanSheet(mixed $m, array $stored, int $nStyles): ?array
    {
        if (! is_array($m) || ! is_array($m['grid'] ?? null) || ! is_array($m['cols'] ?? null) || ! is_array($m['rows'] ?? null)) {
            return null;
        }
        $nR = count($m['grid']);
        $nC = count($m['cols']);
        if ($nR < 1 || $nR > \App\Libraries\XlsxSheets::MAX_ROWS || $nC < 1 || $nC > \App\Libraries\XlsxSheets::MAX_COLS || count($m['rows']) !== $nR) {
            return null;
        }
        $grid = [];
        foreach ($m['grid'] as $line) {
            if (! is_array($line) || count($line) !== $nC) {
                return null;
            }
            $row = [];
            foreach ($line as $c) {
                $cell = ['v' => mb_substr(str_replace("\r", '', (string) ($c['v'] ?? '')), 0, 5000), 's' => max(0, min($nStyles - 1, (int) ($c['s'] ?? 0)))];
                if (isset($c['k']) && preg_match('/^\d{1,5}:\d{1,3}$/', (string) $c['k'])) {
                    $cell['k'] = (string) $c['k'];
                }
                $row[] = $cell;
            }
            $grid[] = $row;
        }
        $rows = array_map(static fn ($r) => ['h' => isset($r['h']) && (int) $r['h'] > 0 ? min(900, (int) $r['h']) : null, 'k' => isset($r['k']) ? (int) $r['k'] : null], array_values($m['rows']));
        $cols = array_map(static fn ($c) => ['w' => max(14, min(900, (int) ($c['w'] ?? 64))), 'k' => isset($c['k']) ? (int) $c['k'] : null], array_values($m['cols']));
        $merges = [];
        $taken  = [];
        foreach ($m['merges'] ?? [] as $mg) {
            if (! is_array($mg) || count($mg) !== 4) {
                return null;
            }
            [$r1, $c1, $r2, $c2] = array_map('intval', $mg);
            if ($r1 < 1 || $c1 < 1 || $r2 > $nR || $c2 > $nC || $r2 < $r1 || $c2 < $c1 || ($r1 === $r2 && $c1 === $c2)) {
                return null;
            }
            for ($r = $r1; $r <= $r2; $r++) {
                for ($c = $c1; $c <= $c2; $c++) {
                    if (isset($taken[$r . ':' . $c])) {
                        return null;                                                // overlapping merged cells
                    }
                    $taken[$r . ':' . $c] = true;
                }
            }
            $merges[] = [$r1, $c1, $r2, $c2];
        }

        return ['name' => (string) ($stored['name'] ?? ($m['name'] ?? 'Sheet')), 'cols' => $cols, 'rows' => $rows, 'grid' => $grid, 'merges' => $merges, 'images' => $stored['images'] ?? [],
            'defH' => (int) ($stored['defH'] ?? 20), 'print' => $stored['print'] ?? ['orient' => 'portrait', 'fit' => false]];
    }

    /** @return array<string,mixed>|null */
    private function register(string $code): ?array
    {
        return $this->db()->table('tbl_labdoc_registers')->where('code', $code)->where('is_active', 1)->get()->getRowArray() ?: null;
    }
}
