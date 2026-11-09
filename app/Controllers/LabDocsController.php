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
    private function archive(array $reg, string $kind, string $key, string $category, $snapshot, ?string $copyFrom, string $user, ?string $copyName = null): void
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
                    $ok = @copy($copyFrom, $dir . '/' . ($copyName ?? basename(str_replace('/', DIRECTORY_SEPARATOR, $copyFrom)))) && $ok;
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

    /** a colour for each person, the same on every screen (cursor, ring of the avatar) */
    private const COLORS = ['#e5533d', '#2f80ed', '#16a34a', '#a855f7', '#f59e0b', '#0ea5a5', '#db2777', '#65a30d', '#7c3aed', '#0369a1'];

    /** what the page needs to show who else has the document open and to warn about newer saves */
    private function collabInfo(array $reg, string $kind, string $key, bool $ro = false): array
    {
        $u = $this->auth->user() ?? [];

        return ['doc' => $kind . '|' . $reg['code'] . '|' . $key, 'token' => $this->docToken($kind, $reg, $key), 'ro' => $ro, 'url' => site_url('labdocs/presence'),
            'me' => ['id' => (int) ($u['id'] ?? 0), 'name' => (string) ($u['name'] ?? $u['username'] ?? 'User'), 'color' => self::COLORS[((int) ($u['id'] ?? 0)) % count(self::COLORS)]],
            'canEdit' => $this->canEdit()];
    }

    /** a value that changes whenever the document is saved (the page keeps the one it loaded and compares) */
    private function docToken(string $kind, array $reg, string $key): string
    {
        $db = $this->db();
        if ($kind === 'method') {
            $m = $db->table('tbl_vfm_methods')->select('revision_no')->where(['register_id' => $reg['id'], 'code' => $key])->get()->getRowArray();

            return (string) ($m['revision_no'] ?? '0');
        }
        if ($kind === 'book') {
            $revs = $db->query('SELECT s.revision_no FROM tbl_labdoc_sheets s JOIN tbl_labdoc_books b ON b.id = s.book_id WHERE b.register_id = ? AND b.book_key = ? ORDER BY s.idx', [$reg['id'], $key])->getResultArray();

            return implode(',', array_column($revs, 'revision_no'));
        }
        if ($kind === 'register') {
            $r = $db->table('tbl_labdoc_registers')->select('updated_at')->where('id', $reg['id'])->get()->getRowArray();

            return (string) ($r['updated_at'] ?? '');
        }

        return '';
    }

    /** who has a document open (every few seconds each open page reports where its cursor is and gets everybody else back) */
    public function presence(): ResponseInterface
    {
        $in  = $this->request->getJSON(true) ?: [];
        $doc = (string) ($in['doc'] ?? '');
        $u   = $this->auth->user();
        if (! $u || ! preg_match('/^(register|method|book|file)\|[A-Za-z0-9_.-]{1,60}\|[A-Za-z0-9_.-]{0,100}$/', $doc)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false]);
        }
        $db  = $this->db();
        $now = date('Y-m-d H:i:s');
        $uid = (int) $u['id'];
        $name = (string) ($u['name'] ?? $u['username'] ?? 'User');
        $db->query('INSERT INTO tbl_labdoc_presence (doc_key, user_id, name, roles, pos, editing, seen_at) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name = VALUES(name), roles = VALUES(roles), pos = VALUES(pos), editing = VALUES(editing), seen_at = VALUES(seen_at)',
            [$doc, $uid, mb_substr($name, 0, 120), mb_substr((string) ($u['role'] ?? ''), 0, 120), mb_substr((string) ($in['pos'] ?? ''), 0, 300), ! empty($in['editing']) ? 1 : 0, $now]);
        if (random_int(1, 20) === 1) {
            $db->query('DELETE FROM tbl_labdoc_presence WHERE seen_at < ?', [date('Y-m-d H:i:s', time() - 300)]);
        }
        $users = [];
        foreach ($db->query('SELECT user_id, name, roles, pos, editing FROM tbl_labdoc_presence WHERE doc_key = ? AND seen_at >= ? ORDER BY name', [$doc, date('Y-m-d H:i:s', time() - 12)])->getResultArray() as $r) {
            $users[] = ['id' => (int) $r['user_id'], 'name' => $r['name'], 'roles' => \App\Libraries\LabDocRoles::label((string) $r['roles']), 'pos' => $r['pos'], 'editing' => (bool) $r['editing'],
                'color' => self::COLORS[((int) $r['user_id']) % count(self::COLORS)], 'me' => (int) $r['user_id'] === $uid];
        }
        [$kind, $code, $key] = explode('|', $doc);
        $reg   = $this->register($code);
        $token = $reg ? $this->docToken($kind, $reg, $key) : '';
        $by    = '';
        if ($reg) {
            $row = $db->table('tbl_labdoc_log')->select('user_name, created_at')->where('register_id', $reg['id'])->like('action', 'save', 'after')->orderBy('id', 'DESC')->get(1)->getRowArray();
            $by  = $row ? $row['user_name'] : '';
        }

        return $this->response->setJSON(['ok' => true, 'users' => $users, 'token' => $token, 'by' => $by]);
    }

    private function conflict(string $by, ?string $at): ResponseInterface
    {
        return $this->response->setStatusCode(409)->setJSON(['ok' => false, 'conflict' => true, 'by' => $by, 'at' => $at ? date('d M Y H:i', strtotime($at)) : '',
            'message' => 'Somebody saved a newer version of this document after you opened it.', 'csrf' => csrf_hash()]);
    }

    private function canEdit(): bool
    {
        return \App\Libraries\LabDocRoles::canEdit((string) ($this->auth->user()['role'] ?? ''));
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
            'base'    => (string) $reg['updated_at'],
            'saveUrl' => site_url('labdocs/' . rawurlencode($reg['code']) . '/save'),
        ];

        return $this->render('labdocs/register', [
            'labRevs' => $this->revList($reg, 'register', '', (bool) $snap),
            'labCollab' => $this->collabInfo($reg, 'register', '', (bool) $snap),
            'title'   => $reg['code'] . ' ' . $reg['title'],
            'withCss' => 'labdocs.css',
            'withJs'  => ['labdocs-collab.js', 'labdocs-zoom.js', 'labdocs-search.js', 'labdocs-revs.js', 'labdocs.js'],
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
        if (empty($in['force']) && isset($in['base']) && (string) $in['base'] !== (string) $reg['updated_at']) {
            $last = $this->db()->table('tbl_labdoc_log')->select('user_name')->where('register_id', $reg['id'])->like('action', 'save', 'after')->orderBy('id', 'DESC')->get(1)->getRowArray();

            return $this->conflict((string) ($last['user_name'] ?? ''), (string) $reg['updated_at']);
        }
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
            'title' => $m['code'] . ' ' . $m['title'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-collab.js', 'labdocs-zoom.js', 'labdocs-search.js', 'labdocs-revs.js', 'labdocs-method.js'], 'reg' => $reg, 'm' => $m, 'data' => $data, 'revs' => $revs,
            'labRevs' => $this->revList($reg, 'method', $m['code'], (bool) $snap),
            'labCollab' => $this->collabInfo($reg, 'method', $m['code'], (bool) $snap),
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
        if (empty($in['force']) && isset($in['base']) && (int) $in['base'] !== (int) $m['revision_no']) {
            return $this->conflict((string) ($m['edited_by'] ?? ''), $m['edited_at'] ?? null);
        }
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
        $kind = strtolower((string) ($d['kind'] ?? ''));
        if ($kind === 'xlsx') {
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

        if (in_array($kind, ['docx', 'doc'], true)) {
            $docxHtml = null;
            if ($kind === 'docx') {
                $docxHtml = \App\Libraries\LabDocOffice::docxToHtml($path);
            }
            $data = [
                'file' => ['id' => (int) $d['id'], 'name' => $d['file_name'], 'ext' => $kind, 'folder' => '', 'size' => (int) filesize($path), 'at' => 1, 'of' => 1, 'date' => date('d M Y H:i', (int) filemtime($path))],
                'pdf' => false, 'text' => null, 'docx' => ['html' => $docxHtml ?: '<p>Document preview.</p>'], 'pptx' => null, 'canEdit' => false,
                'src' => site_url('labdocs/' . rawurlencode($reg['code']) . '/file/' . rawurlencode($d['doc_key'])),
                'convSrc' => null,
                'download' => site_url('labdocs/' . rawurlencode($reg['code']) . '/file/' . rawurlencode($d['doc_key']) . '?dl=1'),
                'saveDocxUrl' => '',
                'savePptUrl'  => '',
                'savePdfUrl'  => '',
                'backUrl' => site_url('labdocs/' . rawurlencode($reg['code'])),
                'prev' => null, 'next' => null,
            ];

            return $this->render('labdocs/fileview', [
                'title' => $d['label'],
                'withCss' => 'labdocs.css',
                'withJs' => ['labdocs-collab.js', 'labdocs-zoom.js', 'labdocs-search.js', 'labdocs-revs.js', 'labdocs-fileview.js'],
                'reg' => $reg,
                'data' => $data,
            ]);
        }

        if (in_array($kind, ['pptx', 'ppt'], true)) {
            $slides = null;
            if ($kind === 'pptx') {
                $slides = \App\Libraries\LabDocOffice::pptxToSlides($path);
            }
            $data = [
                'file' => ['id' => (int) $d['id'], 'name' => $d['file_name'], 'ext' => $kind, 'folder' => '', 'size' => (int) filesize($path), 'at' => 1, 'of' => 1, 'date' => date('d M Y H:i', (int) filemtime($path))],
                'pdf' => false, 'text' => null, 'docx' => null, 'pptx' => ['slides' => $slides ?: [['id' => 1, 'title' => $d['file_name'], 'content' => ['Presentation file.'], 'notes' => '']]], 'canEdit' => false,
                'src' => site_url('labdocs/' . rawurlencode($reg['code']) . '/file/' . rawurlencode($d['doc_key'])),
                'convSrc' => null,
                'download' => site_url('labdocs/' . rawurlencode($reg['code']) . '/file/' . rawurlencode($d['doc_key']) . '?dl=1'),
                'saveDocxUrl' => '',
                'savePptUrl'  => '',
                'savePdfUrl'  => '',
                'backUrl' => site_url('labdocs/' . rawurlencode($reg['code'])),
                'prev' => null, 'next' => null,
            ];

            return $this->render('labdocs/fileview', [
                'title' => $d['label'],
                'withCss' => 'labdocs.css',
                'withJs' => ['labdocs-collab.js', 'labdocs-zoom.js', 'labdocs-search.js', 'labdocs-revs.js', 'labdocs-fileview.js'],
                'reg' => $reg,
                'data' => $data,
            ]);
        }

        return $this->serve((string) $d['cache_file'], (string) $d['file_name'], $this->request->getGet('dl') !== null);
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
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $this->streamOut($ext, $name, $download, (int) filesize($path), md5($path . '|' . filesize($path) . '|' . filemtime($path)), static function (int $from, int $to) use ($path): void {
            $h = fopen($path, 'rb');
            fseek($h, $from);
            for ($left = $to - $from + 1; $left > 0 && ! feof($h);) {
                $chunk = fread($h, min(262144, $left));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                $left -= strlen($chunk);
                // flush() inside an output buffer makes the web server send the headers NOW, before the file type is set (the PDF then arrived as text/html)
                if (ob_get_level() === 0) {
                    flush();
                }
            }
            fclose($h);
        });
    }

    /** @return array<string,mixed>|null */
    private function vfm(array $reg, string $code): ?array
    {
        return $this->db()->table('tbl_vfm_methods')->where(['register_id' => $reg['id'], 'code' => $code, 'is_active' => 1])->get()->getRowArray() ?: null;
    }


    // ------------------------------------------------------------------------------------------------ folders of files (#DTP-Lab-108)

    private const TYPES = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'bmp'  => 'image/bmp',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
        'xls'  => 'application/vnd.ms-excel',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc'  => 'application/msword',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'csv'  => 'text/csv; charset=UTF-8',
        'txt'  => 'text/plain; charset=UTF-8',
        'htm'  => 'text/html; charset=UTF-8',
        'html' => 'text/html; charset=UTF-8',
        'zip'  => 'application/zip',
        'rar'  => 'application/x-rar-compressed',
    ];

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
            $rows = $db->table('tbl_labdoc_files')->select($sel)->where(['register_id' => $reg['id'], 'src_key' => '', 'deleted_at' => null])->groupStart()->like('name', $q)->orLike('folder', $q)->groupEnd()->orderBy('folder')->orderBy('name')->get(300)->getResultArray();
        } else {
            $rows = $db->table('tbl_labdoc_files')->select($sel)->where(['register_id' => $reg['id'], 'folder' => $p, 'src_key' => '', 'deleted_at' => null])->orderBy('name')->get()->getResultArray();
            $like = $p === '' ? '' : $p . '/';
            $all  = $db->table('tbl_labdoc_files')->select('folder, COUNT(*) AS n')->where(['register_id' => $reg['id'], 'src_key' => '', 'deleted_at' => null])->groupBy('folder')->get()->getResultArray();
            foreach ($all as $a) {
                if ($a['folder'] === '' || ($like !== '' && ! str_starts_with($a['folder'], $like)) || $a['folder'] === $p) {
                    continue;
                }
                $name = explode('/', substr($a['folder'], strlen($like)))[0];
                $folders[$name] = ($folders[$name] ?? 0) + (int) $a['n'];
            }
            foreach ($db->table('tbl_labdoc_folders')->select('path')->where('register_id', $reg['id'])->get()->getResultArray() as $fo) {      // folders made here (maybe still empty)
                if (($like === '' || str_starts_with($fo['path'], $like)) && $fo['path'] !== $p) {
                    $name = explode('/', substr($fo['path'], strlen($like)))[0];
                    $folders[$name] = $folders[$name] ?? 0;
                }
            }
            ksort($folders, SORT_NATURAL | SORT_FLAG_CASE);
        }
        usort($rows, static fn ($a, $b) => strnatcasecmp($a['folder'] . '/' . $a['name'], $b['folder'] . '/' . $b['name']));
        $conv     = [];
        foreach ($db->table('tbl_labdoc_files')->select('src_key')->where(['register_id' => $reg['id']])->like('src_key', 'conv:', 'after')->get()->getResultArray() as $c) {
            $conv[(int) substr($c['src_key'], 5)] = true;
        }
        $bookKeys = array_column($db->table('tbl_labdoc_books')->select('book_key')->where('register_id', $reg['id'])->get()->getResultArray(), 'book_key');
        $files    = array_map(static function ($f) use ($base, $bookKeys, $conv) {
            $ext = strtolower(trim((string) $f['ext']));
            $isSheet = in_array($ext, ['xlsx', 'xls', 'xlsm'], true);
            $url = $isSheet
                ? $base . '/b/f' . $f['id']
                : $base . '/v/' . $f['id'];

            return [
                'name' => $f['name'],
                'ext' => $f['ext'],
                'folder' => $f['folder'],
                'size' => (int) $f['file_size'],
                'date' => $f['file_mtime'] ? date('d M Y', strtotime((string) $f['file_mtime'])) : '',
                'url' => $url,
                'id' => (int) $f['id'],
                'open' => true,
                'thumb' => $f['has_thumb'] ? $base . '/thumb/' . $f['id'] : null,
            ];
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

        return $this->render('labdocs/files', ['title' => $reg['code'] . ' ' . $reg['title'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-collab.js', 'labdocs-preview.js', 'labdocs-files.js'],
            'reg' => $reg, 'folders' => $fl, 'files' => $files, 'crumbs' => $crumbs, 'q' => $q, 'base' => $base, 'canEdit' => $this->canEdit(), 'curPath' => $p,
            'labCollab' => null, 'parent' => $q !== '' ? $base : $parent]);
    }

    /** a PDF or picture of a folder register in the same page frame as the other documents: back to its folder, previous / next file, zoom, print, download */
    public function fileView(string $code, string $id): ResponseInterface|string
    {
        $reg = $this->register(rawurldecode($code));
        $db  = $this->db();
        $f   = $reg ? $db->table('tbl_labdoc_files')->select('id, folder, name, ext, file_size, file_mtime, disk_path')->where(['id' => (int) $id, 'register_id' => $reg['id']])->get()->getRowArray() : null;
        if (! $f) {
            return redirect()->to(site_url('labdocs'))->with('error', 'That file is not in the database.');
        }
        $base = site_url('labdocs/' . rawurlencode($reg['code']));
        $ext  = strtolower(trim((string) $f['ext']));

        // If it's an Excel spreadsheet, ensure book exists and redirect to interactive spreadsheet viewer
        if (in_array($ext, ['xlsx', 'xls', 'xlsm'], true)) {
            $bKey = $this->ensureBookForFile($reg, $f);
            if ($bKey) {
                return redirect()->to($base . '/b/' . $bKey);
            }
        }

        $sib  = $db->table('tbl_labdoc_files')->select('id, name')->where(['register_id' => $reg['id'], 'folder' => $f['folder'], 'src_key' => '', 'deleted_at' => null])->get()->getResultArray();
        usort($sib, static fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        $ids = array_column($sib, 'id');
        $at  = array_search((string) $f['id'], array_map('strval', $ids), true);
        $nav = static fn ($i) => isset($ids[$i]) ? $base . '/v/' . $ids[$i] : null;
        $showId = (int) $f['id'];                                   // the file whose bytes the viewer shows: the PDF made from a Word document, or the file itself
        $isPdf  = $ext === 'pdf';
        $text   = null;
        $docx   = null;
        $pptx   = null;
        $html   = null;

        $cv = $db->table('tbl_labdoc_files')->select('id')->where(['register_id' => $reg['id'], 'src_key' => 'conv:' . $f['id']])->get()->getRowArray();
        $convSrc = $cv ? $base . '/f/' . $cv['id'] : null;

        if (in_array($ext, ['doc', 'docx'], true)) {
            $tmp = tempnam(sys_get_temp_dir(), 'docx_');
            $this->writeDbFileToPath((int) $f['id'], $tmp);
            if ($ext === 'docx') {
                $docxHtml = \App\Libraries\LabDocOffice::docxToHtml($tmp);
                $docx = ['html' => $docxHtml];
            } else {
                $cv = $db->table('tbl_labdoc_files')->select('id')->where(['register_id' => $reg['id'], 'src_key' => 'conv:' . $f['id']])->get()->getRowArray();
                if ($cv) {
                    $showId = (int) $cv['id'];
                    $isPdf  = true;
                } else {
                    $docx = ['html' => '<p>Legacy Word .doc document. You can edit content below or save modern updates.</p>'];
                }
            }
            @unlink($tmp);
        } elseif (in_array($ext, ['ppt', 'pptx'], true)) {
            $tmp = tempnam(sys_get_temp_dir(), 'pptx_');
            $this->writeDbFileToPath((int) $f['id'], $tmp);
            if ($ext === 'pptx') {
                $slides = \App\Libraries\LabDocOffice::pptxToSlides($tmp);
                $pptx = ['slides' => $slides];
            } else {
                $cv = $db->table('tbl_labdoc_files')->select('id')->where(['register_id' => $reg['id'], 'src_key' => 'conv:' . $f['id']])->get()->getRowArray();
                if ($cv) {
                    $showId = (int) $cv['id'];
                    $isPdf  = true;
                } else {
                    $pptx = ['slides' => [['id' => 1, 'title' => $f['name'], 'content' => ['Legacy .ppt presentation file.'], 'notes' => '']]];
                }
            }
            @unlink($tmp);
        } elseif (in_array($ext, ['csv', 'txt'], true)) {
            $raw = '';
            foreach ($db->table('tbl_labdoc_file_parts')->select('data')->where('file_id', $f['id'])->orderBy('part')->get(3)->getResultArray() as $part) {
                $raw .= $part['data'];
            }
            $raw  = mb_convert_encoding(substr($raw, 0, 1200000), 'UTF-8', 'UTF-8, Windows-1252');
            $text = ['kind' => $ext, 'rows' => []];
            if ($ext === 'csv') {
                foreach (array_slice(preg_split('/\r\n|\n|\r/', $raw) ?: [], 0, 2000) as $ln) {
                    if ($ln !== '') {
                        $text['rows'][] = array_map(static fn ($c) => (string) $c, str_getcsv($ln, strpos($ln, ';') !== false && substr_count($ln, ';') > substr_count($ln, ',') ? ';' : ',', '"', ''));
                    }
                }
            } else {
                $text['raw'] = mb_substr($raw, 0, 200000);
            }
        } elseif (in_array($ext, ['htm', 'html'], true)) {
            $tmp = tempnam(sys_get_temp_dir(), 'html_');
            $this->writeDbFileToPath((int) $f['id'], $tmp);
            if (is_file($tmp)) {
                $html = (string) file_get_contents($tmp);
                @unlink($tmp);
            }
        }
        $data = [
            'file' => ['id' => (int) $f['id'], 'name' => $f['name'], 'ext' => $f['ext'], 'folder' => $f['folder'], 'size' => (int) $f['file_size'], 'at' => $at === false ? 0 : $at + 1, 'of' => count($ids),
                'date' => $f['file_mtime'] ? date('d M Y H:i', strtotime((string) $f['file_mtime'])) : ''],
            'pdf' => $isPdf, 'text' => $text, 'docx' => $docx, 'pptx' => $pptx, 'html' => $html, 'canEdit' => $this->canEdit(),
            'src' => $base . '/f/' . $showId, 'convSrc' => $convSrc, 'download' => $base . '/f/' . $f['id'] . '?dl=1',
            'saveDocxUrl' => $base . '/f/' . $f['id'] . '/save-docx',
            'savePptUrl'  => $base . '/f/' . $f['id'] . '/save-ppt',
            'savePdfUrl'  => $base . '/f/' . $f['id'] . '/save-pdf',
            'prev' => $at === false ? null : $nav($at - 1), 'next' => $at === false ? null : $nav($at + 1),
            'backUrl' => $f['folder'] === '' ? $base : $base . '?p=' . rawurlencode($f['folder']),
        ];

        return $this->render('labdocs/fileview', ['title' => $f['name'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-collab.js', 'labdocs-zoom.js', 'labdocs-search.js', 'labdocs-revs.js', 'labdocs-fileview.js'], 'reg' => $reg, 'data' => $data,
            'labRevs' => $this->revList($reg, 'file', (string) $f['id'], false), 'labCollab' => $this->collabInfo($reg, 'file', (string) $f['id'], false)], $this->layout());
    }

    public function saveDocxFile(string $code, string $id): ResponseInterface
    {
        if (! $this->canEdit()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Only authorized users can modify documents.']);
        }
        $reg = $this->register(rawurldecode($code));
        $db  = $this->db();
        $f   = $reg ? $db->table('tbl_labdoc_files')->where(['id' => (int) $id, 'register_id' => $reg['id']])->get()->getRowArray() : null;
        if (! $f) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Document not found.']);
        }
        $in   = $this->request->getJSON(true);
        $html = (string) ($in['html'] ?? '');

        $tmp = tempnam(sys_get_temp_dir(), 'docx_');
        $this->writeDbFileToPath((int) $f['id'], $tmp);

        \App\Libraries\LabDocOffice::htmlToDocx($tmp, $html);

        $user = $this->userName();
        $this->updateFileFromTemp($reg, $f, $tmp, $user, 'Saved document edits');
        @unlink($tmp);

        return $this->response->setJSON(['ok' => true, 'message' => 'Document saved successfully.', 'csrf' => csrf_hash()]);
    }

    public function savePptFile(string $code, string $id): ResponseInterface
    {
        if (! $this->canEdit()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Only authorized users can modify presentations.']);
        }
        $reg = $this->register(rawurldecode($code));
        $db  = $this->db();
        $f   = $reg ? $db->table('tbl_labdoc_files')->where(['id' => (int) $id, 'register_id' => $reg['id']])->get()->getRowArray() : null;
        if (! $f) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Presentation not found.']);
        }
        $in     = $this->request->getJSON(true);
        $slides = is_array($in['slides'] ?? null) ? $in['slides'] : [];

        $tmp = tempnam(sys_get_temp_dir(), 'pptx_');
        $this->writeDbFileToPath((int) $f['id'], $tmp);

        \App\Libraries\LabDocOffice::slidesToPptx($tmp, $slides);

        $user = $this->userName();
        $this->updateFileFromTemp($reg, $f, $tmp, $user, 'Saved presentation edits');
        @unlink($tmp);

        return $this->response->setJSON(['ok' => true, 'message' => 'Presentation saved successfully.', 'csrf' => csrf_hash()]);
    }

    public function savePdfFile(string $code, string $id): ResponseInterface
    {
        if (! $this->canEdit()) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Only authorized users can modify PDF documents.']);
        }
        $reg = $this->register(rawurldecode($code));
        $db  = $this->db();
        $f   = $reg ? $db->table('tbl_labdoc_files')->where(['id' => (int) $id, 'register_id' => $reg['id']])->get()->getRowArray() : null;
        if (! $f) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'PDF document not found.']);
        }
        $in   = $this->request->getJSON(true);
        $user = $this->userName();

        $pdfBase64 = (string) ($in['pdf_base64'] ?? '');
        if ($pdfBase64 !== '') {
            $bin = base64_decode(preg_replace('#^data:application/pdf;base64,#', '', $pdfBase64));
            if ($bin !== false && strlen($bin) > 100) {
                $tmp = tempnam(sys_get_temp_dir(), 'pdf_');
                file_put_contents($tmp, $bin);
                $this->updateFileFromTemp($reg, $f, $tmp, $user, 'Saved PDF annotations');
                @unlink($tmp);
                return $this->response->setJSON(['ok' => true, 'message' => 'PDF updated successfully.', 'csrf' => csrf_hash()]);
            }
        }

        $annotations = $in['annotations'] ?? null;
        if ($annotations) {
            $this->archive($reg, 'file_ann', (string) $f['id'], ($f['folder'] === '' ? '' : $f['folder'] . '/') . $f['name'],
                ['annotations' => $annotations, 'file_id' => (int) $f['id']], null, $user, null);
            return $this->response->setJSON(['ok' => true, 'message' => 'Annotations saved as revision.', 'csrf' => csrf_hash()]);
        }

        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'message' => 'No valid data provided.']);
    }

    private function writeDbFileToPath(int $id, string $targetPath): bool
    {
        $db = $this->db();
        $f  = $db->table('tbl_labdoc_files')->select('disk_path')->where('id', $id)->get()->getRowArray();
        if ($f && ! empty($f['disk_path']) && $this->cachePath((string) $f['disk_path']) !== null) {
            $p = (string) $this->cachePath((string) $f['disk_path']);
            if (is_file($p)) {
                return @copy($p, $targetPath);
            }
        }
        $fh = fopen($targetPath, 'wb');
        if (! $fh) {
            return false;
        }
        foreach ($db->table('tbl_labdoc_file_parts')->select('data')->where('file_id', $id)->orderBy('part')->get()->getResultArray() as $pt) {
            fwrite($fh, $pt['data']);
        }
        fclose($fh);
        return true;
    }

    private function ensureBookForFile(array $reg, array $f): ?string
    {
        $db  = $this->db();
        $key = 'f' . $f['id'];
        $cur = $db->table('tbl_labdoc_books')->where(['register_id' => $reg['id'], 'book_key' => $key])->get()->getRowArray();
        if ($cur) {
            return $key;
        }

        $ext = strtolower((string) ($f['ext'] ?? 'xlsx'));
        $tmp = tempnam(sys_get_temp_dir(), 'bk_') . '.' . $ext;
        if (! $this->writeDbFileToPath((int) $f['id'], $tmp)) {
            @unlink($tmp);
            return null;
        }

        $m = null;
        try {
            $m = \App\Libraries\XlsxSheets::read($tmp, FCPATH . 'assets/labdocs/books/' . $key);
        } catch (\Throwable) {
            $m = null;
        }

        if (empty($m['sheets'])) {
            @unlink($tmp);
            return null;
        }

        $db->table('tbl_labdoc_books')->insert([
            'register_id' => $reg['id'],
            'book_key'    => $key,
            'label'       => pathinfo((string) $f['name'], PATHINFO_FILENAME),
            'file_name'   => $f['name'],
            'file_path'   => $f['disk_path'] ?? '',
            'styles_json' => json_encode($m['styles'] ?? []),
            'sheet_count' => count($m['sheets']),
            'file_size'   => (int) $f['file_size'],
            'file_mtime'  => $f['file_mtime'] ?: date('Y-m-d H:i:s'),
            'sort_order'  => 0,
        ]);
        $bid = (int) $db->insertID();

        foreach ($m['sheets'] as $i => $sh) {
            $db->table('tbl_labdoc_sheets')->insert([
                'book_id'    => $bid,
                'idx'        => $i,
                'name'       => $sh['name'],
                'model_json' => '',
            ]);
            $sid    = (int) $db->insertID();
            $shJson = json_encode($sh, JSON_UNESCAPED_UNICODE);
            foreach (str_split($shJson, 200000) as $piece) {
                $db->query('UPDATE tbl_labdoc_sheets SET model_json = CONCAT(model_json, ?) WHERE id = ?', [$piece, $sid]);
            }
        }

        @unlink($tmp);
        return $key;
    }

    private function updateFileFromTemp(array $reg, array $cur, string $tmpPath, string $user, string $reason): void
    {
        $db  = $this->db();
        $now = date('Y-m-d H:i:s');
        $fid = (int) $cur['id'];
        $dir = (string) ($cur['folder'] ?? '');
        $name = (string) $cur['name'];
        $ext = (string) $cur['ext'];

        // Archive previous revision
        $old = tempnam(sys_get_temp_dir(), 'lab_old');
        $this->writeDbFileToPath($fid, $old);
        $this->archive($reg, 'file', (string) $fid, ($dir === '' ? '' : $dir . '/') . $name,
            ['name' => $name, 'size' => (int) $cur['file_size'], 'saved' => $cur['file_mtime'], 'reason' => $reason],
            $old, $user, basename($name));
        @unlink($old);

        // Update main file record
        $size = (int) @filesize($tmpPath);
        $db->table('tbl_labdoc_files')->where('id', $fid)->update([
            'file_size' => $size,
            'file_mtime' => $now,
        ]);

        // Update parts or disk
        $db->table('tbl_labdoc_file_parts')->where('file_id', $fid)->delete();
        $disk = is_dir(WRITEPATH . 'labdocs');
        if ($disk) {
            $rel = 'other/' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $reg['code']) . '/' . $fid . ($ext !== '' ? '.' . $ext : '');
            @mkdir(dirname(WRITEPATH . 'labdocs/' . $rel), 0775, true);
            copy($tmpPath, WRITEPATH . 'labdocs/' . $rel);
            $db->table('tbl_labdoc_files')->where('id', $fid)->update(['disk_path' => $rel]);
        } else {
            $h = fopen($tmpPath, 'rb');
            for ($i = 0; $h && ! feof($h); $i++) {
                $piece = fread($h, 400000);
                if ($piece === false || $piece === '') {
                    break;
                }
                $db->table('tbl_labdoc_file_parts')->insert(['file_id' => $fid, 'part' => $i, 'data' => $piece]);
            }
            if ($h) {
                fclose($h);
            }
        }
    }

    // ------------------------------------------------------------------------------------------------ editing a folder of files (Encoder, Manager, Supervisor, Admin)

    private const BLOCKED = ['exe', 'bat', 'cmd', 'com', 'msi', 'dll', 'ps1', 'vbs', 'js', 'jse', 'php', 'phtml', 'scr', 'sh', 'jar', 'hta', 'lnk'];
    private const MAX_UPLOAD = 62914560;                       // 60 MB

    private function folderGuard(string $code): array
    {
        $reg = $this->register(rawurldecode($code));
        if (! $this->canEdit()) {
            return [null, $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Your role can view the documents but not change them.', 'csrf' => csrf_hash()])];
        }
        if (! $reg || ($reg['view_type'] ?? '') !== 'files') {
            return [null, $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Folder register not found.', 'csrf' => csrf_hash()])];
        }

        return [$reg, null];
    }

    private function cleanPath(string $p): ?string
    {
        $p = trim(str_replace('\\', '/', $p), '/');
        if ($p === '') {
            return '';
        }
        foreach (explode('/', $p) as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..' || preg_match('/[<>:"|?*\x00-\x1f]/', $seg) || mb_strlen($seg) > 120) {
                return null;
            }
        }

        return mb_strlen($p) > 480 ? null : $p;
    }

    private function userName(): string
    {
        return (string) ($this->auth->user()['name'] ?? $this->auth->user()['username'] ?? 'user');
    }

    public function newFolder(string $code): ResponseInterface
    {
        [$reg, $err] = $this->folderGuard($code);
        if ($err) {
            return $err;
        }
        $in   = $this->request->getJSON(true) ?: [];
        $base = $this->cleanPath((string) ($in['p'] ?? ''));
        $name = trim((string) ($in['name'] ?? ''));
        if ($base === null || $name === '' || $this->cleanPath($name) === null || str_contains($name, '/') || str_contains($name, '\\')) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'Give the folder a name without \ / : * ? " < > |', 'csrf' => csrf_hash()]);
        }
        $path = ($base === '' ? '' : $base . '/') . $name;
        $db   = $this->db();
        if ($db->table('tbl_labdoc_folders')->where(['register_id' => $reg['id'], 'path' => $path])->countAllResults() === 0) {
            $db->table('tbl_labdoc_folders')->insert(['register_id' => $reg['id'], 'path' => $path, 'created_by' => $this->userName(), 'created_at' => date('Y-m-d H:i:s')]);
        }
        $made = @mkdir(self::SHARE . '/' . $reg['folder_name'] . '/' . $path, 0775, true) || is_dir(self::SHARE . '/' . $reg['folder_name'] . '/' . $path);
        $db->table('tbl_labdoc_log')->insert(['register_id' => $reg['id'], 'action' => 'new-folder', 'user_name' => $this->userName(), 'created_at' => date('Y-m-d H:i:s'), 'detail' => mb_substr($path . ($made ? '' : ' (not created on the share)'), 0, 490)]);

        return $this->response->setJSON(['ok' => true, 'onShare' => $made, 'csrf' => csrf_hash()]);
    }

    public function upload(string $code): ResponseInterface
    {
        [$reg, $err] = $this->folderGuard($code);
        if ($err) {
            return $err;
        }
        $db  = $this->db();
        $dir = $this->cleanPath((string) $this->request->getPost('p'));
        if ($dir === null) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => 'That folder is not valid.', 'csrf' => csrf_hash()]);
        }
        $user = $this->userName();
        $now  = date('Y-m-d H:i:s');
        $done = [];
        $bad  = [];
        $disk = ($reg['grp'] ?? 'lab') === 'other';
        foreach ($this->request->getFileMultiple('files') ?: [] as $up) {
            $name = trim(str_replace(['\\', '/'], '-', (string) $up->getClientName()));
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! $up->isValid() || $name === '' || preg_match('/[<>:"|?*\x00-\x1f]/', $name)) {
                $bad[] = $name . ' (not accepted)';
                continue;
            }
            if (in_array($ext, self::BLOCKED, true)) {
                $bad[] = $name . ' (this kind of file is not allowed)';
                continue;
            }
            if ($up->getSize() > self::MAX_UPLOAD) {
                $bad[] = $name . ' (over 60 MB)';
                continue;
            }
            $tmp = $up->getTempName();
            $cur = $db->table('tbl_labdoc_files')->where(['register_id' => $reg['id'], 'folder' => $dir, 'name' => $name, 'src_key' => ''])->get()->getRowArray();
            if ($cur) {                                            // the same name: the old file is kept as a revision (database + Logs folder)
                $old = tempnam(sys_get_temp_dir(), 'lab');
                $fh  = fopen($old, 'wb');
                foreach ($db->table('tbl_labdoc_file_parts')->select('data')->where('file_id', $cur['id'])->orderBy('part')->get()->getResultArray() as $pt) {
                    fwrite($fh, $pt['data']);
                }
                fclose($fh);
                $oldCopy = $old;
                if ($cur['disk_path'] && $this->cachePath((string) $cur['disk_path']) !== null) {
                    $oldCopy = (string) $this->cachePath((string) $cur['disk_path']);
                }
                $this->archive($reg, 'file', (string) $cur['id'], ($dir === '' ? '' : $dir . '/') . $name, ['name' => $name, 'size' => (int) $cur['file_size'], 'saved' => $cur['file_mtime']], $oldCopy, $user, basename($name));
                @unlink($old);
            }
            $data = ['register_id' => $reg['id'], 'folder' => $dir, 'name' => mb_substr($name, 0, 250), 'ext' => $ext, 'file_size' => $up->getSize(), 'file_mtime' => $now, 'deleted_at' => null, 'deleted_by' => null, 'thumb' => null, 'src_key' => ''];
            if ($cur) {
                $db->table('tbl_labdoc_files')->where('id', $cur['id'])->update($data);
                $fid = (int) $cur['id'];
            } else {
                $db->table('tbl_labdoc_files')->insert($data);
                $fid = (int) $db->insertID();
            }
            $db->table('tbl_labdoc_file_parts')->where('file_id', $fid)->delete();
            if ($disk) {
                $rel = 'other/' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $reg['code']) . '/' . $fid . ($ext !== '' ? '.' . $ext : '');
                @mkdir(dirname(WRITEPATH . 'labdocs/' . $rel), 0775, true);
                copy($tmp, WRITEPATH . 'labdocs/' . $rel);
                $db->table('tbl_labdoc_files')->where('id', $fid)->update(['disk_path' => $rel]);
            } else {
                $h = fopen($tmp, 'rb');
                for ($i = 0; $h && ! feof($h); $i++) {
                    $piece = fread($h, 400000);
                    if ($piece === false || $piece === '') {
                        break;
                    }
                    $db->table('tbl_labdoc_file_parts')->insert(['file_id' => $fid, 'part' => $i, 'data' => $piece]);
                }
                if ($h) {
                    fclose($h);
                }
            }
            $thumb = $this->makeThumb($tmp, $ext);
            if ($thumb !== null) {
                $db->table('tbl_labdoc_files')->where('id', $fid)->update(['thumb' => $thumb]);
            }
            $target = self::SHARE . '/' . $reg['folder_name'] . '/' . ($dir === '' ? '' : $dir . '/');       // also onto the share, when it can be written
            if ((is_dir($target) || @mkdir($target, 0775, true)) && @copy($tmp, $target . $name)) {
                $done[] = $name;
            } else {
                $done[] = $name . ' (kept in NEXUS-L; not copied to the share)';
            }
            $db->table('tbl_labdoc_log')->insert(['register_id' => $reg['id'], 'action' => $cur ? 'replace-file' : 'upload', 'user_name' => $user, 'created_at' => $now, 'detail' => mb_substr(($dir === '' ? '' : $dir . '/') . $name, 0, 490)]);
        }

        return $this->response->setJSON(['ok' => $done !== [], 'done' => $done, 'bad' => $bad, 'message' => $done === [] ? 'Nothing was uploaded: ' . implode(', ', $bad) : '', 'csrf' => csrf_hash()]);
    }

    /** a small preview of an uploaded picture (PDF and Office previews are made by the import tools) */
    private function makeThumb(string $path, string $ext): ?string
    {
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true) || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        $im = @imagecreatefromstring((string) @file_get_contents($path));
        if (! $im) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $k = min(1, 360 / max($w, 1));
        $t = imagecreatetruecolor((int) max(1, $w * $k), (int) max(1, $h * $k));
        imagecopyresampled($t, $im, 0, 0, 0, 0, imagesx($t), imagesy($t), $w, $h);
        ob_start();
        imagejpeg($t, null, 72);

        return (string) ob_get_clean();
    }

    public function deleteFile(string $code, string $id): ResponseInterface
    {
        [$reg, $err] = $this->folderGuard($code);
        if ($err) {
            return $err;
        }
        $f = $this->db()->table('tbl_labdoc_files')->select('id, name, folder')->where(['id' => (int) $id, 'register_id' => $reg['id'], 'deleted_at' => null])->get()->getRowArray();
        if (! $f) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'File not found.', 'csrf' => csrf_hash()]);
        }
        $user = $this->userName();
        $this->db()->table('tbl_labdoc_files')->where('id', $f['id'])->update(['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $user]);
        $this->db()->table('tbl_labdoc_log')->insert(['register_id' => $reg['id'], 'action' => 'delete-file', 'user_name' => $user, 'created_at' => date('Y-m-d H:i:s'), 'detail' => mb_substr(($f['folder'] === '' ? '' : $f['folder'] . '/') . $f['name'] . ' (the copy in the database is kept)', 0, 490)]);

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    /** looks in the folder on the share for files that are new or changed and takes them in (no previews: those come from the import tools) */
    public function refresh(string $code): ResponseInterface
    {
        [$reg, $err] = $this->folderGuard($code);
        if ($err) {
            return $err;
        }
        @set_time_limit(180);
        $root = self::SHARE . '/' . $reg['folder_name'];
        if (! is_dir($root)) {
            return $this->response->setStatusCode(502)->setJSON(['ok' => false, 'message' => 'The folder on the share cannot be reached from the server right now.', 'csrf' => csrf_hash()]);
        }
        $db   = $this->db();
        $disk = ($reg['grp'] ?? 'lab') === 'other';
        $new  = $changed = $skipped = 0;
        $it   = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $name = $f->getFilename();
            $ext  = strtolower($f->getExtension());
            if (! $f->isFile() || str_starts_with($name, '~$') || in_array($ext, ['db', 'ini', 'lnk', 'tmp'], true) || in_array($ext, self::BLOCKED, true)) {
                continue;
            }
            $full = str_replace('\\', '/', $f->getPathname());
            $rel  = ltrim(substr($full, strlen(str_replace('\\', '/', $root))), '/');
            $dir  = str_contains($rel, '/') ? substr($rel, 0, (int) strrpos($rel, '/')) : '';
            $mt   = date('Y-m-d H:i:s', $f->getMTime());
            $cur  = $db->table('tbl_labdoc_files')->select('id, file_size, file_mtime, deleted_at')->where(['register_id' => $reg['id'], 'folder' => $dir, 'name' => $name, 'src_key' => ''])->get()->getRowArray();
            if ($cur && ($cur['deleted_at'] !== null || ((int) $cur['file_size'] === $f->getSize() && $cur['file_mtime'] === $mt))) {
                continue;                                          // already here (or deleted in NEXUS-L: it stays deleted)
            }
            if ($f->getSize() > self::MAX_UPLOAD * 2) {
                $skipped++;
                continue;
            }
            $data = ['register_id' => $reg['id'], 'folder' => $dir, 'name' => $name, 'ext' => $ext, 'file_size' => $f->getSize(), 'file_mtime' => $mt, 'src_key' => ''];
            if ($cur) {
                $db->table('tbl_labdoc_files')->where('id', $cur['id'])->update($data);
                $fid = (int) $cur['id'];
                $changed++;
            } else {
                $db->table('tbl_labdoc_files')->insert($data);
                $fid = (int) $db->insertID();
                $new++;
            }
            $db->table('tbl_labdoc_file_parts')->where('file_id', $fid)->delete();
            if ($disk) {
                $dst = 'other/' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $reg['code']) . '/' . $fid . ($ext !== '' ? '.' . $ext : '');
                @mkdir(dirname(WRITEPATH . 'labdocs/' . $dst), 0775, true);
                if (@copy($f->getPathname(), WRITEPATH . 'labdocs/' . $dst)) {
                    $db->table('tbl_labdoc_files')->where('id', $fid)->update(['disk_path' => $dst]);
                }
            } else {
                $h = fopen($f->getPathname(), 'rb');
                for ($i = 0; $h && ! feof($h); $i++) {
                    $piece = fread($h, 400000);
                    if ($piece === false || $piece === '') {
                        break;
                    }
                    $db->table('tbl_labdoc_file_parts')->insert(['file_id' => $fid, 'part' => $i, 'data' => $piece]);
                }
                if ($h) {
                    fclose($h);
                }
            }
            $thumb = $this->makeThumb($f->getPathname(), $ext);
            if ($thumb !== null) {
                $db->table('tbl_labdoc_files')->where('id', $fid)->update(['thumb' => $thumb]);
            }
        }
        $db->table('tbl_labdoc_log')->insert(['register_id' => $reg['id'], 'action' => 'refresh', 'user_name' => $this->userName(), 'created_at' => date('Y-m-d H:i:s'), 'detail' => "{$new} new, {$changed} changed, {$skipped} too big"]);

        return $this->response->setJSON(['ok' => true, 'new' => $new, 'changed' => $changed, 'skipped' => $skipped, 'csrf' => csrf_hash()]);
    }

    /** a file of a folder register, from the database copy */
    public function fileGet(string $code, string $id): ResponseInterface
    {
        $reg = $this->register(rawurldecode($code));
        $f   = $reg ? $this->db()->table('tbl_labdoc_files')->select('id, name, ext')->where(['id' => (int) $id, 'register_id' => $reg['id']])->get()->getRowArray() : null;

        return $f ? $this->sendDbFile((int) $f['id'], (string) $f['name'], $this->request->getGet('dl') !== null) : $this->response->setStatusCode(404)->setBody('Not found');
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
        $db = $this->db();
        $f  = $db->table('tbl_labdoc_files')->select('ext, disk_path, file_size, file_mtime')->where('id', $id)->get()->getRowArray();
        if (! $f) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        $etag = md5($id . '|' . $f['file_size'] . '|' . $f['file_mtime']);
        if (! empty($f['disk_path']) && $this->cachePath((string) $f['disk_path']) !== null) {            // a copy on the server's disk
            $path = (string) $this->cachePath((string) $f['disk_path']);

            return $this->streamOut((string) $f['ext'], $name, $download, (int) filesize($path), $etag, static function (int $from, int $to) use ($path): void {
                $h = fopen($path, 'rb');
                fseek($h, $from);
                for ($left = $to - $from + 1; $left > 0 && ! feof($h);) {
                    $chunk = fread($h, min(262144, $left));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $left -= strlen($chunk);
                    // flush() inside an output buffer makes the web server send the headers NOW, before the file type is set (the PDF then arrived as text/html)
                    if (ob_get_level() === 0) {
                        flush();
                    }
                }
                fclose($h);
            });
        }
        $size = (int) ($db->query('SELECT SUM(LENGTH(data)) AS n FROM tbl_labdoc_file_parts WHERE file_id = ?', [$id])->getRow()->n ?? 0);

        return $this->streamOut((string) $f['ext'], $name, $download, $size, $etag, static function (int $from, int $to) use ($db, $id): void {
            $ps = 400000;                                                                               // every piece is 400 KB except the last
            foreach ($db->query('SELECT part, data FROM tbl_labdoc_file_parts WHERE file_id = ? AND part BETWEEN ? AND ? ORDER BY part', [$id, intdiv($from, $ps), intdiv($to, $ps)])->getResultArray() as $r) {
                $start = (int) $r['part'] * $ps;
                $a     = max($from, $start) - $start;
                $b     = min($to, $start + strlen($r['data']) - 1) - $start;
                if ($b >= $a) {
                    echo substr($r['data'], $a, $b - $a + 1);
                    // flush() inside an output buffer makes the web server send the headers NOW, before the file type is set (the PDF then arrived as text/html)
                    if (ob_get_level() === 0) {
                        flush();
                    }
                }
            }
        });
    }

    /**
     * Sends a file: small ones the normal way, big ones (or a Range request, which the browser's PDF viewer makes) piece by piece, so the server never
     * holds the whole file in memory. A file that did not change is answered with 304 (the browser keeps its copy).
     */
    private function streamOut(string $ext, string $name, bool $download, int $size, string $etag, callable $emit): ResponseInterface
    {
        $res = $this->response->setHeader('Content-Type', self::TYPES[$ext] ?? 'application/octet-stream')
            ->setHeader('Content-Disposition', ($download ? 'attachment' : 'inline') . "; filename*=UTF-8''" . rawurlencode($name))
            ->setHeader('X-Content-Type-Options', 'nosniff')->setHeader('Accept-Ranges', 'bytes')->setHeader('ETag', '"' . $etag . '"')->setHeader('Cache-Control', 'private, max-age=0, must-revalidate');
        if (trim($this->request->getHeaderLine('If-None-Match')) === '"' . $etag . '"') {
            return $res->setStatusCode(304);
        }
        $from   = 0;
        $to     = max(0, $size - 1);
        $status = 200;
        $range  = $this->request->getHeaderLine('Range');
        if ($range !== '' && $size > 0 && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            if ($m[1] !== '') {
                $from = (int) $m[1];
                $to   = $m[2] !== '' ? min((int) $m[2], $size - 1) : $size - 1;
            } elseif ($m[2] !== '') {
                $from = max(0, $size - (int) $m[2]);
            }
            if ($from > $to || $from >= $size) {
                return $res->setStatusCode(416)->setHeader('Content-Range', 'bytes */' . $size);
            }
            $status = 206;
            $res->setHeader('Content-Range', "bytes {$from}-{$to}/{$size}");
        }
        if ($status === 200 && $size <= 8 * 1048576) {                                                  // small: the normal way
            ob_start();
            $emit(0, max(0, $size - 1));

            return $res->setBody((string) ob_get_clean());
        }
        $res->setStatusCode($status)->setHeader('Content-Length', (string) ($to - $from + 1));
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $res->sendHeaders();
        $emit($from, $to);
        exit;
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
        $reg        = $this->register(rawurldecode($code));
        $decodedKey = rawurldecode($key);
        $db         = $this->db();
        $b          = $reg ? $db->table('tbl_labdoc_books')->where(['register_id' => $reg['id'], 'book_key' => $decodedKey])->get()->getRowArray() : null;

        if (! $b && $reg && preg_match('/^f(\d+)$/', $decodedKey, $fm)) {
            $fRow = $db->table('tbl_labdoc_files')->where(['id' => (int) $fm[1], 'register_id' => $reg['id']])->get()->getRowArray();
            if ($fRow) {
                $this->ensureBookForFile($reg, $fRow);
                $b = $db->table('tbl_labdoc_books')->where(['register_id' => $reg['id'], 'book_key' => $decodedKey])->get()->getRowArray();
            }
        }

        if (! $reg || ! $b) {
            if ($reg && preg_match('/^f(\d+)$/', $decodedKey, $fm)) {
                return redirect()->to(site_url('labdocs/' . rawurlencode($reg['code']) . '/v/' . $fm[1]));
            }

            return redirect()->to(site_url('labdocs'))->with('error', 'That workbook is not in the database.');
        }
        $sheets = [];
        $snap   = $this->revSnap($reg, 'book', $b['book_key']);
        if ($snap) {
            foreach (json_decode((string) $snap['snapshot_json'], true) ?: [] as $m) {
                $sheets[] = $m;
            }
        } else {
            foreach ($this->db()->table('tbl_labdoc_sheets')->select('idx, name, revision_no')->where('book_id', $b['id'])->orderBy('idx')->get()->getResultArray() as $k => $sh) {
                if ($k === 0) {                                      // the first sheet comes with the page, the others are fetched when their tab is opened
                    $m = json_decode((string) $this->db()->table('tbl_labdoc_sheets')->select('model_json')->where(['book_id' => $b['id'], 'idx' => $sh['idx']])->get()->getRowArray()['model_json'], true) ?: [];
                    $m['rev'] = (int) $sh['revision_no'];
                    $sheets[] = $m;
                } else {
                    $sheets[] = ['name' => $sh['name'], 'rev' => (int) $sh['revision_no'], 'lazy' => true];
                }
            }
        }
        $base = site_url('labdocs/' . rawurlencode($reg['code']));
        $data = [
            'book' => ['key' => $b['book_key'], 'label' => $b['label'], 'file' => $b['file_name'], 'status' => $b['file_status'], 'updatedAt' => $b['updated_at'], 'updatedBy' => $b['updated_by']],
            'styles' => json_decode((string) $b['styles_json'], true) ?: [], 'sheets' => $sheets, 'assets' => base_url('assets/labdocs/books/' . $b['book_key']) . '/',
            'canEdit' => $snap ? false : $this->canEdit(), 'saveUrl' => $base . '/b/' . rawurlencode($b['book_key']) . '/save', 'sheetUrl' => $base . '/b/' . rawurlencode($b['book_key']) . '/sheet/', 'downloadUrl' => $base . '/b/' . rawurlencode($b['book_key']) . '/download',
            'backUrl' => $this->bookBack($reg, $b, $base),
        ];

        return $this->render('labdocs/book', ['title' => $b['label'], 'withCss' => 'labdocs.css', 'withJs' => ['labdocs-collab.js', 'labdocs-zoom.js', 'labdocs-search.js', 'labdocs-revs.js', 'labdocs-book.js'], 'reg' => $reg, 'b' => $b, 'data' => $data,
            'labRevs' => $this->revList($reg, 'book', $b['book_key'], (bool) $snap), 'labCollab' => $this->collabInfo($reg, 'book', $b['book_key'], (bool) $snap)], $this->layout());
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

    /** one sheet of a workbook (the page asks for a sheet when its tab is opened) */
    public function sheet(string $code, string $key, string $idx): ResponseInterface
    {
        $reg = $this->register(rawurldecode($code));
        $db  = $this->db();
        $b   = $reg ? $db->table('tbl_labdoc_books')->select('id')->where(['register_id' => $reg['id'], 'book_key' => rawurldecode($key)])->get()->getRowArray() : null;
        $sh  = $b ? $db->table('tbl_labdoc_sheets')->where(['book_id' => $b['id'], 'idx' => (int) $idx])->get()->getRowArray() : null;
        if (! $sh) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Sheet not found.']);
        }
        $m        = json_decode((string) $sh['model_json'], true) ?: [];
        $m['rev'] = (int) $sh['revision_no'];

        return $this->response->setHeader('Cache-Control', 'private, no-cache')->setJSON(['ok' => true, 'sheet' => $m]);
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
        if (empty($in['force']) && is_array($in['base'] ?? null)) {
            foreach (array_keys($clean) as $idx) {
                $cur = $db->table('tbl_labdoc_sheets')->select('revision_no, edited_by, edited_at')->where(['book_id' => $b['id'], 'idx' => $idx])->get()->getRowArray();
                if ($cur && isset($in['base'][$idx]) && (int) $in['base'][$idx] !== (int) $cur['revision_no']) {
                    return $this->conflict((string) ($cur['edited_by'] ?? ''), $cur['edited_at'] ?? null);
                }
            }
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
                if (! empty($c['f'])) {
                    $cell['f'] = mb_substr(str_replace("\r", '', (string) $c['f']), 0, 2000);
                }
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
