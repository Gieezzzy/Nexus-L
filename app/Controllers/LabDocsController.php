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

    /** @return array<string,mixed>|null */
    private function register(string $code): ?array
    {
        return $this->db()->table('tbl_labdoc_registers')->where('code', $code)->where('is_active', 1)->get()->getRowArray() ?: null;
    }
}
