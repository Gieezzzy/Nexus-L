<?php

namespace App\Controllers;

use App\Libraries\SystemSettings;

/**
 * The InterLink pick lists ("search" buttons: Supplier Name, Season, Country, Fiber Content, End Use, Brand, Test Item ...) of the
 * five eTRF templates, read once by the background service (job mode "lov") and kept here so NEXUS-L can offer exactly the values
 * InterLink accepts.
 *
 *  POST interlink/lov-sync          (login)        queue one read job per template
 *  POST interlink/service/lov       (service key)  the service sends one list (or a field -> already-sent list mapping)
 *  GET  interlink/lov               (login)        values of one list; GET interlink/lov-status  what is stored
 */
class InterlinkLovController extends BaseController
{
    private function db()
    {
        return \Config\Database::connect();
    }

    private function tables(): void
    {
        $db = $this->db();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_interlink_lov_lists (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            list_id VARCHAR(30) NOT NULL,
            parent_path VARCHAR(300) NOT NULL DEFAULT "",
            field_code VARCHAR(80) NOT NULL DEFAULT "",
            label VARCHAR(120) NOT NULL DEFAULT "",
            headers TEXT NULL,
            `rows` MEDIUMTEXT NULL,
            row_count INT UNSIGNED NOT NULL DEFAULT 0,
            sync_id VARCHAR(40) NOT NULL DEFAULT "",
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_list (list_id, parent_path(190))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->query('CREATE TABLE IF NOT EXISTS tbl_interlink_lov_fields (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            template VARCHAR(80) NOT NULL,
            field_code VARCHAR(80) NOT NULL,
            label VARCHAR(120) NOT NULL DEFAULT "",
            list_id VARCHAR(30) NOT NULL,
            parent_field VARCHAR(80) NOT NULL DEFAULT "",
            columns_set VARCHAR(255) NOT NULL DEFAULT "",
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_field (template, field_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    private function json(array $data, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($data + ['csrf' => csrf_hash()]);
    }

    private function key(): string
    {
        return (string) SystemSettings::get('interlink.service.key', '');
    }

    /** POST interlink/lov-sync : one "lov" job per template (the service reads them one after the other; this takes a while) */
    public function sync()
    {
        $base = SystemSettings::serviceUrl();
        if ($base === '' || strlen($this->key()) < 16) {
            return $this->json(['ok' => false, 'error' => 'The InterLink background service is not set up on this server.'], 503);
        }
        $syncId = bin2hex(random_bytes(8));
        $queued = [];
        $me     = (array) service('auth')->user();
        foreach (InterlinkController::ETRF_TEMPLATES as $t) {
            if (str_contains($t['label'], '(Adult)')) {
                continue;                                   // an alias of the Product form
            }
            $uid = bin2hex(random_bytes(12));
            try {
                $res  = service('curlrequest')->post($base . '/jobs', [
                    'headers' => ['X-Service-Key' => $this->key()],
                    'json'    => [
                        'job_uid'        => $uid,
                        'application_no' => 'LOV:' . substr($t['code'], 0, 24),
                        'report_no'      => $t['code'],
                        'mode'           => 'lov',
                        'account'        => $t['account'],
                        'user_name'      => (string) ($me['name'] ?? ''),
                        'callback_url'   => '',
                        'job'            => [
                            'lov_url' => site_url('interlink/service/lov'),
                            'sync_id' => $syncId,
                            'etrf'    => ['etrf_type' => $t['code'], 'template_label' => $t['label'], 'account_email' => $t['account'], 'menu_targets' => $t['menu_targets']],
                        ],
                    ],
                    'timeout'     => 30,
                    'http_errors' => false,
                ]);
                $body = json_decode((string) $res->getBody(), true) ?: [];
                $queued[] = ['template' => $t['label'], 'ok' => $res->getStatusCode() === 200 && ! empty($body['ok']), 'error' => $body['error'] ?? ''];
            } catch (\Throwable $e) {
                $queued[] = ['template' => $t['label'], 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return $this->json(['ok' => (bool) array_filter($queued, static fn ($q) => $q['ok']), 'sync_id' => $syncId, 'queued' => $queued]);
    }

    /** POST interlink/service/lov (service key) */
    public function receive()
    {
        $got = (string) $this->request->getHeaderLine('X-Service-Key');
        if ($this->key() === '' || $got === '' || ! hash_equals($this->key(), $got)) {
            return $this->json(['ok' => false, 'error' => 'Wrong or missing service key.'], 401);
        }
        $p = $this->request->getJSON(true) ?? [];
        $tpl = trim((string) ($p['template'] ?? ''));
        $l   = (array) ($p['list'] ?? $p['alias'] ?? []);
        if ($tpl === '' || empty($l['field']) || empty($l['list_id'])) {
            return $this->json(['ok' => false, 'error' => 'template, field and list_id are required.'], 400);
        }
        $this->tables();
        $db  = $this->db();
        $now = date('Y-m-d H:i:s');
        $db->query('INSERT INTO tbl_interlink_lov_fields (template, field_code, label, list_id, parent_field, columns_set, updated_at) VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE label = VALUES(label), list_id = VALUES(list_id), parent_field = VALUES(parent_field), columns_set = VALUES(columns_set), updated_at = VALUES(updated_at)',
            [$tpl, (string) $l['field'], mb_substr((string) ($l['label'] ?? ''), 0, 120), (string) $l['list_id'], (string) ($l['parent_field'] ?? ''), mb_substr((string) ($l['columns'] ?? ''), 0, 255), $now]);
        if (isset($p['list']) && is_array($l['rows'] ?? null)) {
            $rows = $l['rows'];
            if (! empty($l['empty']) && ! $rows) {
                return $this->json(['ok' => true, 'stored' => 0, 'note' => 'empty until its parent field is set']);
            }
            $db->query('INSERT INTO tbl_interlink_lov_lists (list_id, parent_path, field_code, label, headers, `rows`, row_count, sync_id, updated_at) VALUES (?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE field_code = VALUES(field_code), label = VALUES(label), headers = VALUES(headers), `rows` = VALUES(`rows`), row_count = VALUES(row_count), sync_id = VALUES(sync_id), updated_at = VALUES(updated_at)',
                [(string) $l['list_id'], mb_substr((string) ($l['parent_path'] ?? ''), 0, 300), (string) $l['field'], mb_substr((string) ($l['label'] ?? ''), 0, 120),
                    json_encode($l['headers'] ?? [], JSON_UNESCAPED_UNICODE), json_encode($rows, JSON_UNESCAPED_UNICODE), count($rows), mb_substr((string) ($p['sync_id'] ?? ''), 0, 40), $now]);
        }

        return $this->json(['ok' => true, 'stored' => isset($l['rows']) ? count($l['rows']) : 0]);
    }

    /** GET interlink/lov?template=VF eTRF - Material&field=MSTA_FIELD_6&parent=TRPT_PRD_CAT=Footwear&q=stahl&limit=50 */
    public function values()
    {
        $this->tables();
        $tpl    = trim((string) $this->request->getGet('template'));
        $field  = trim((string) $this->request->getGet('field'));
        $parent = trim((string) $this->request->getGet('parent'));
        $q      = mb_strtolower(trim((string) $this->request->getGet('q')));
        $limit  = max(1, min(500, (int) ($this->request->getGet('limit') ?: 100)));
        $f = $this->db()->table('tbl_interlink_lov_fields')->where('template', $tpl)->where('field_code', $field)->get()->getRowArray();
        if (! $f) {
            return $this->json(['ok' => false, 'error' => "No pick list stored for {$field} on {$tpl}. Run the pick list read first."], 404);
        }
        $row = $this->db()->table('tbl_interlink_lov_lists')->where('list_id', $f['list_id'])->where('parent_path', $parent)->get()->getRowArray();
        if (! $row) {
            return $this->json(['ok' => false, 'error' => 'This list depends on another field (' . $f['parent_field'] . '); choose that first.', 'parent_field' => $f['parent_field']], 404);
        }
        $rows = json_decode((string) $row['rows'], true) ?: [];
        if ($q !== '') {
            $rows = array_values(array_filter($rows, static fn ($r) => str_contains(mb_strtolower(implode(' ', $r)), $q)));
        }

        return $this->json(['ok' => true, 'label' => $f['label'], 'headers' => json_decode((string) $row['headers'], true) ?: [], 'total' => count($rows), 'rows' => array_slice($rows, 0, $limit), 'updated_at' => $row['updated_at']]);
    }

    /** GET interlink/lov-status : what has been read, per template */
    public function status()
    {
        $this->tables();
        $fields = $this->db()->query('SELECT f.template, f.field_code, f.label, f.parent_field, f.list_id, COUNT(l.id) AS variants, COALESCE(SUM(l.row_count),0) AS total_rows, MAX(l.updated_at) AS updated_at
            FROM tbl_interlink_lov_fields f LEFT JOIN tbl_interlink_lov_lists l ON l.list_id = f.list_id GROUP BY f.id ORDER BY f.template, f.field_code')->getResultArray();

        return $this->json(['ok' => true, 'fields' => $fields]);
    }
}
