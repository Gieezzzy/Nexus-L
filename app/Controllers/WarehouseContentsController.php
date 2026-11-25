<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Warehouse boxes: what is inside each box (Lab References).
 *  GET  warehouse-tracker/labrefs      Lab References to pick from (date range, test group, search)
 *  POST warehouse-tracker/box-items/add   put the picked Lab References into a box
 *  GET  warehouse-tracker/box-items    the list of a box with its check columns (QIMA, CFA/Loading, ...)
 *  POST warehouse-tracker/box-items/save  save the checks / status of the rows
 *  POST warehouse-tracker/item-cols/add   (Admin) a new check column; columns are rows of tbl_warehouse_item_cols and the checks of a row are JSON in
 *                                         tbl_warehouse_box_items.flags, so a new column never changes the database structure or breaks the page
 * Everybody who may open the warehouse can LOOK at a box list; only Admin, Manager, Supervisor, Warehouse PIC, Bonding PIC and Encoding can change it.
 */
class WarehouseContentsController extends BaseController
{
    private const GROUPS = [
        'bonding'   => ['bonding', 'pulltest'],
        'wholeshoe' => ['wholeshoe', 'wst'],
        'material'  => ['fabric', 'leather', 'hardware', 'fthl', 'label', 'sklfm', 'rubber', 'box', 'insole'],
    ];
    private const EDIT_ROLES = ['Admin', 'Manager', 'Supervisor', 'Warehouse PIC', 'Bonding PIC', 'Encoding'];
    private const STATUSES   = ['IN_BOX', 'TRANSFERRED', 'REMOVED'];

    private function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    private function role(): string
    {
        return (string) (service('auth')->user()['role'] ?? '');
    }

    private function canEdit(): bool
    {
        return (bool) array_intersect(self::EDIT_ROLES, \App\Libraries\Roles::parse($this->role()));
    }

    private function isAdmin(): bool
    {
        return \App\Libraries\Roles::isAdmin($this->role());
    }

    private function fail(string $m, int $c = 422): ResponseInterface
    {
        return $this->response->setStatusCode($c)->setJSON(['ok' => false, 'error' => $m, 'csrf' => csrf_hash()]);
    }

    /** the extra columns / the check column list, made once (cached) */
    private function ensure(): void
    {
        try {
            if (cache()->get('wh_items_schema_ok2') !== null) {
                return;
            }
        } catch (\Throwable $e) {
            // no cache
        }
        $db = $this->db();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_warehouse_item_cols (
            id INT AUTO_INCREMENT PRIMARY KEY,
            col_key VARCHAR(40) NOT NULL,
            label VARCHAR(60) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(120) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_col_key (col_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->query("INSERT IGNORE INTO tbl_warehouse_item_cols (col_key, label, sort_order, created_by) VALUES ('qima', 'QIMA', 1, 'system'), ('cfa_loading', 'CFA/Loading', 2, 'system')");
        foreach ([
            'flags'       => 'TEXT NULL',
            'item_status' => "VARCHAR(20) NOT NULL DEFAULT 'IN_BOX'",
            'item_note'   => 'VARCHAR(255) NULL',
            'added_by'    => 'VARCHAR(120) NULL',
            'added_at'    => 'DATETIME NULL',
            'updated_by'  => 'VARCHAR(120) NULL',
            'updated_at'  => 'DATETIME NULL',
            'moved_to_code'    => 'VARCHAR(64) NULL',
            'moved_to_label'   => 'VARCHAR(255) NULL',
            'moved_from_code'  => 'VARCHAR(64) NULL',
            'moved_from_label' => 'VARCHAR(255) NULL',
            'moved_at'         => 'DATETIME NULL',
            'moved_by'         => 'VARCHAR(120) NULL',
        ] as $col => $def) {
            try {
                $db->query("ALTER TABLE tbl_warehouse_box_items ADD COLUMN IF NOT EXISTS {$col} {$def}");
            } catch (\Throwable $e) {
                // already there
            }
        }
        try {
            cache()->save('wh_items_schema_ok2', 1, 3600);
        } catch (\Throwable $e) {
            // not cached
        }
    }

    private function box(string $code): ?array
    {
        $b = $this->db()->table('tbl_warehouse_boxes')->where('box_code', $code)->get()->getRowArray();

        return $b ?: null;
    }

    /** "31 Oct 2026" or "2026-11-17" (the tested date is saved both ways) as a date */
    private const DATE_SQL = "COALESCE(STR_TO_DATE(NULLIF(u.tested_date, ''), '%d %b %Y'), STR_TO_DATE(LEFT(NULLIF(u.tested_date, ''), 10), '%Y-%m-%d'),
        STR_TO_DATE(NULLIF(u.finished_date, ''), '%d %b %Y'), STR_TO_DATE(LEFT(NULLIF(u.finished_date, ''), 10), '%Y-%m-%d'),
        STR_TO_DATE(NULLIF(u.received_date, ''), '%d %b %Y'), STR_TO_DATE(LEFT(NULLIF(u.received_date, ''), 10), '%Y-%m-%d'))";
    // Whole Shoe rows (tbl_dtr_wholeshoe_intake) have no tested date until the report is made, only the received date; and the same Lab Ref can sit on several rows
    // (one Whole Shoe ref per shoe of a pair / several intake rows, repeated bonding rows). The picker shows ONE card per Lab Ref.

    /** GET warehouse-tracker/labrefs?box=&from=&to=&group=&q= */
    public function labrefs(): ResponseInterface
    {
        $this->ensure();
        $g    = (string) $this->request->getGet('group');
        $from = (string) $this->request->getGet('from');
        $to   = (string) $this->request->getGet('to');
        $q    = trim((string) $this->request->getGet('q'));
        $box  = trim((string) $this->request->getGet('box'));
        $slugs = self::GROUPS[$g] ?? array_merge(...array_values(self::GROUPS));
        $ph    = implode(',', array_fill(0, count($slugs), '?'));
        $args  = $slugs;
        $where = "u.form_slug IN ({$ph}) AND u.lab_ref_no <> ''";
        $d     = self::DATE_SQL;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where .= " AND {$d} >= ?";
            $args[] = $from;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where .= " AND {$d} <= ?";
            $args[] = $to;
        }
        if ($q !== '') {
            $like = '%' . $this->db()->escapeLikeString($q) . '%';
            $where .= ' AND (u.lab_ref_no LIKE ? OR u.model_style LIKE ? OR u.color LIKE ? OR u.mtl_no LIKE ? OR u.po_lot_no LIKE ?)';
            array_push($args, $like, $like, $like, $like, $like);
        }
        $rows = $this->db()->query("SELECT u.lab_ref_no, MIN(u.form_slug) AS form_slug, MAX({$d}) AS tdate, GROUP_CONCAT(DISTINCT NULLIF(u.model_style, '') SEPARATOR ' | ') AS model_style,
                GROUP_CONCAT(DISTINCT NULLIF(u.color, '') SEPARATOR ' | ') AS color, MAX(u.po_lot_no) AS po_lot_no, MAX(u.mtl_no) AS mtl_no, MAX(u.material_description) AS material_description, COUNT(*) AS n_rows,
                (SELECT GROUP_CONCAT(DISTINCT bi.box_name SEPARATOR ' | ') FROM tbl_warehouse_box_items bi WHERE bi.lab_reference = u.lab_ref_no COLLATE utf8mb4_general_ci AND bi.item_status = 'IN_BOX') AS in_box
            FROM tbl_dtr_unified_labrefs u WHERE {$where} GROUP BY u.lab_ref_no ORDER BY tdate DESC, u.lab_ref_no ASC LIMIT 401", $args)->getResultArray();
        $more = count($rows) > 400;
        $rows = array_slice($rows, 0, 400);
        $mine = [];
        if ($box !== '' && ($b = $this->box($box))) {
            foreach ($this->db()->table('tbl_warehouse_box_items')->select('lab_reference')->groupStart()->where('box_code', $box)->orWhere('box_name', $b['label'])->groupEnd()->get()->getResultArray() as $r) {
                $mine[strtoupper((string) $r['lab_reference'])] = true;
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $grp = 'material';
            foreach (['bonding', 'wholeshoe'] as $k) {
                if (in_array($r['form_slug'], self::GROUPS[$k], true)) {
                    $grp = $k;
                }
            }
            $out[] = [
                'lab_ref' => $r['lab_ref_no'], 'group' => $grp, 'slug' => $r['form_slug'], 'date' => (string) ($r['tdate'] ?? ''),
                'style' => trim((string) ($r['model_style'] ?: $r['material_description'])), 'color' => (string) $r['color'], 'po' => (string) $r['po_lot_no'], 'mtl' => (string) $r['mtl_no'],
                'in_this_box' => isset($mine[strtoupper($r['lab_ref_no'])]),
                // a Bonding Lab Reference is in ONE box only (the box it is in blocks it); every other type may be in several boxes (the other boxes are only shown)
                'in_box' => $grp === 'bonding' ? (string) ($r['in_box'] ?? '') : '', 'also_in' => $grp === 'bonding' ? '' : (string) ($r['in_box'] ?? ''), 'rows' => (int) $r['n_rows'],
            ];
        }

        return $this->response->setJSON(['ok' => true, 'items' => $out, 'more' => $more]);
    }

    /** POST warehouse-tracker/box-items/add {box_code, labs:[lab ref, ...]} */
    public function add(): ResponseInterface
    {
        if (! $this->canEdit()) {
            return $this->fail('Only Admin, Manager, Supervisor, Warehouse PIC, Bonding PIC and Encoding can put contents in a box.', 403);
        }
        $this->ensure();
        $p    = $this->request->getJSON(true) ?? [];
        $code = trim((string) ($p['box_code'] ?? ''));
        $labs = array_values(array_unique(array_filter(array_map(static fn ($x) => trim((string) $x), (array) ($p['labs'] ?? [])))));
        $box  = $this->box($code);
        if (! $box) {
            return $this->fail('That box was not found.', 404);
        }
        if (! $labs) {
            return $this->fail('Pick at least one Lab Reference.');
        }
        $labs = array_slice($labs, 0, 400);
        $db   = $this->db();
        $me   = (string) (service('auth')->user()['name'] ?? '');
        $ph   = implode(',', array_fill(0, count($labs), '?'));
        $src  = $db->query("SELECT u.lab_ref_no, MIN(u.form_slug) AS form_slug, GROUP_CONCAT(DISTINCT NULLIF(u.model_style, '') SEPARATOR ' | ') AS model_style, GROUP_CONCAT(DISTINCT NULLIF(u.color, '') SEPARATOR ' | ') AS color,
                MAX(u.po_lot_no) AS po_lot_no, MAX(u.mtl_no) AS mtl_no, MAX(" . self::DATE_SQL . ") AS tdate
            FROM tbl_dtr_unified_labrefs u WHERE u.lab_ref_no IN ({$ph}) GROUP BY u.lab_ref_no", $labs)->getResultArray();
        $added = 0;
        $skip  = 0;
        $elsewhere = [];
        foreach ($src as $r) {
            $type = in_array($r['form_slug'], self::GROUPS['bonding'], true) ? 'bonding' : (in_array($r['form_slug'], self::GROUPS['wholeshoe'], true) ? 'wholeshoe' : 'material');
            $dup  = $db->table('tbl_warehouse_box_items')->where('lab_reference', $r['lab_ref_no'])->groupStart()->where('box_code', $code)->orWhere('box_name', $box['label'])->groupEnd()->countAllResults();
            if ($dup) {
                $skip++;
                continue;
            }
            $other = $type === 'bonding' ? $db->table('tbl_warehouse_box_items')->select('box_name')->where('lab_reference', $r['lab_ref_no'])->where('item_status', 'IN_BOX')->get()->getRowArray() : null;
            if ($other) {          // a Bonding Lab Ref still in another box: it must be Transferred or Removed there first (Bonding: one box only; every other type may be in several boxes)
                $skip++;
                $elsewhere[] = $r['lab_ref_no'] . ' (in ' . $other['box_name'] . ')';
                continue;
            }
            $db->table('tbl_warehouse_box_items')->insert([
                'box_name' => $box['label'], 'test_type' => $type, 'lab_reference' => $r['lab_ref_no'], 'tested_date' => $r['tdate'] ?: null,
                'po_or_tr' => (string) $r['po_lot_no'], 'style' => (string) $r['model_style'], 'mtl' => (string) $r['mtl_no'], 'colorway' => (string) $r['color'],
                'box_code' => $code, 'source' => 'nexus', 'flags' => '{}', 'item_status' => 'IN_BOX', 'added_by' => $me, 'added_at' => date('Y-m-d H:i:s'),
            ]);
            $added++;
        }
        \App\Libraries\ActivityLog::record('warehouse', 'add-box-contents', $code . ' ' . $box['label'], $added . ' Lab Reference(s) put in the box' . ($skip ? ', ' . $skip . ' already there' : ''));

        return $this->response->setJSON(['ok' => true, 'added' => $added, 'skipped' => $skip, 'elsewhere' => array_slice($elsewhere, 0, 10), 'csrf' => csrf_hash()]);
    }

    /** GET warehouse-tracker/box-items?box=<box_code> */
    public function items(): ResponseInterface
    {
        $this->ensure();
        $code = trim((string) $this->request->getGet('box'));
        $box  = $this->box($code);
        if (! $box) {
            return $this->fail('That box was not found.', 404);
        }
        $db   = $this->db();
        $rows = $db->table('tbl_warehouse_box_items')->groupStart()->where('box_code', $code)->orWhere('box_name', $box['label'])->groupEnd()
            ->orderBy('test_type')->orderBy('lab_reference')->get()->getResultArray();
        $items = [];
        foreach ($rows as $r) {
            $f = json_decode((string) ($r['flags'] ?? ''), true);
            $items[] = [
                'id' => (int) $r['id'], 'lab_ref' => $r['lab_reference'], 'type' => $r['test_type'], 'date' => (string) ($r['tested_date'] ?? ''), 'style' => (string) ($r['style'] ?? ''),
                'color' => (string) ($r['colorway'] ?? ''), 'flags' => is_array($f) ? $f : [], 'status' => in_array($r['item_status'] ?? '', self::STATUSES, true) ? $r['item_status'] : 'IN_BOX',
                'note' => (string) ($r['item_note'] ?? ''), 'by' => (string) ($r['updated_by'] ?? ''), 'at' => (string) ($r['updated_at'] ?? ''),
                'to' => (string) ($r['moved_to_label'] ?? ''), 'to_code' => (string) ($r['moved_to_code'] ?? ''), 'from' => (string) ($r['moved_from_label'] ?? ''), 'from_code' => (string) ($r['moved_from_code'] ?? ''),
                'moved_at' => (string) ($r['moved_at'] ?? ''), 'moved_by' => (string) ($r['moved_by'] ?? ''),
            ];
        }
        $cols = $db->table('tbl_warehouse_item_cols')->where('is_active', 1)->orderBy('sort_order')->orderBy('id')->get()->getResultArray();

        return $this->response->setJSON([
            'ok' => true, 'box' => ['code' => $box['box_code'], 'label' => $box['label'], 'range' => (string) $box['date_range']], 'items' => $items,
            'cols' => array_map(static fn ($c) => ['key' => $c['col_key'], 'label' => $c['label']], $cols),
            'can_edit' => $this->canEdit(), 'is_admin' => $this->isAdmin(), 'csrf' => csrf_hash(),
        ]);
    }

    /** POST warehouse-tracker/box-items/save {box_code, rows:[{id, flags:{key:bool}, status, note}]} */
    public function save(): ResponseInterface
    {
        if (! $this->canEdit()) {
            return $this->fail('You can look at this list, but only Admin, Manager, Supervisor, Warehouse PIC, Bonding PIC and Encoding can save changes.', 403);
        }
        $this->ensure();
        $p    = $this->request->getJSON(true) ?? [];
        $code = trim((string) ($p['box_code'] ?? ''));
        $box  = $this->box($code);
        if (! $box) {
            return $this->fail('That box was not found.', 404);
        }
        $db   = $this->db();
        $keys = array_column($db->table('tbl_warehouse_item_cols')->select('col_key')->get()->getResultArray(), 'col_key');
        $me   = (string) (service('auth')->user()['name'] ?? '');
        $n    = 0;
        $log  = [];
        $errors = [];
        foreach ((array) ($p['rows'] ?? []) as $r) {
            $id  = (int) ($r['id'] ?? 0);
            $row = $id > 0 ? $db->table('tbl_warehouse_box_items')->where('id', $id)->groupStart()->where('box_code', $code)->orWhere('box_name', $box['label'])->groupEnd()->get()->getRowArray() : null;
            if (! $row) {
                continue;
            }
            $flags = [];
            foreach ((array) ($r['flags'] ?? []) as $k => $v) {
                if (in_array((string) $k, $keys, true)) {
                    $flags[(string) $k] = $v ? 1 : 0;
                }
            }
            $old = json_decode((string) ($row['flags'] ?? ''), true);
            $old = is_array($old) ? $old : [];
            $flags = array_merge($old, $flags);       // a column that is hidden now keeps what was saved
            $prev = $row['item_status'] ?? 'IN_BOX';
            $st   = in_array(($r['status'] ?? ''), self::STATUSES, true) ? $r['status'] : 'IN_BOX';
            if ($prev === 'TRANSFERRED') {
                $st = 'TRANSFERRED';        // a transferred row stays as the record of the move (to undo it, change the row in the new box)
            }
            $note  = mb_substr(trim((string) ($r['note'] ?? '')), 0, 255);
            $extra = [];
            if ($st === 'TRANSFERRED' && $prev !== 'TRANSFERRED') {
                $dest = $this->box(trim((string) ($r['dest'] ?? '')));
                if (! $dest || $dest['box_code'] === $code) {
                    $errors[] = $row['lab_reference'] . ': choose the box it moves to.';
                    continue;
                }
                $now = date('Y-m-d H:i:s');
                $ex  = $db->table('tbl_warehouse_box_items')->where('lab_reference', $row['lab_reference'])->groupStart()->where('box_code', $dest['box_code'])->orWhere('box_name', $dest['label'])->groupEnd()->get()->getRowArray();
                $mv  = ['item_status' => 'IN_BOX', 'moved_from_code' => $code, 'moved_from_label' => $box['label'], 'moved_at' => $now, 'moved_by' => $me, 'updated_by' => $me, 'updated_at' => $now,
                    'item_note' => mb_substr('Transferred from ' . $box['label'] . ' (' . $code . ') on ' . date('M j, Y') . ' by ' . $me, 0, 255)];
                if ($ex) {
                    $db->table('tbl_warehouse_box_items')->where('id', $ex['id'])->update($mv);
                } else {
                    $db->table('tbl_warehouse_box_items')->insert(array_merge($mv, [
                        'box_name' => $dest['label'], 'box_code' => $dest['box_code'], 'test_type' => $row['test_type'], 'lab_reference' => $row['lab_reference'], 'tested_date' => $row['tested_date'],
                        'production_date' => $row['production_date'], 'po_or_tr' => $row['po_or_tr'], 'style' => $row['style'], 'mtl' => $row['mtl'], 'colorway' => $row['colorway'],
                        'source' => 'nexus', 'flags' => $row['flags'] ?: '{}', 'added_by' => $me, 'added_at' => $now,
                    ]));
                }
                $extra = ['moved_to_code' => $dest['box_code'], 'moved_to_label' => $dest['label'], 'moved_at' => $now, 'moved_by' => $me];
                $note  = mb_substr('Transferred to ' . $dest['label'] . ' (' . $dest['box_code'] . ') on ' . date('M j, Y') . ' by ' . $me . ($note !== '' && ! str_starts_with($note, 'Transferred') ? ' - ' . $note : ''), 0, 255);
            } elseif (json_encode($flags) === json_encode($old) && $st === $prev && $note === (string) ($row['item_note'] ?? '')) {
                continue;
            }
            $db->table('tbl_warehouse_box_items')->where('id', $id)->update(array_merge(['flags' => json_encode($flags), 'item_status' => $st, 'item_note' => $note ?: null, 'updated_by' => $me, 'updated_at' => date('Y-m-d H:i:s')], $extra));
            $n++;
            if ($st !== $prev) {
                $log[] = $row['lab_reference'] . ' -> ' . $st . ($extra ? ' (' . $extra['moved_to_code'] . ')' : '');
            }
        }
        \App\Libraries\ActivityLog::record('warehouse', 'save-box-contents', $code . ' ' . $box['label'], $n . ' row(s) saved' . ($log ? ': ' . implode('; ', array_slice($log, 0, 8)) : ''));

        return $this->response->setJSON(['ok' => true, 'saved' => $n, 'errors' => $errors, 'csrf' => csrf_hash()]);
    }

    /** POST warehouse-tracker/box-items/remove {box_code, id}: takes a wrongly picked Lab Reference out of the box list (a transferred row is the record of a move and stays) */
    public function remove(): ResponseInterface
    {
        if (! $this->canEdit()) {
            return $this->fail('Only Admin, Manager, Supervisor, Warehouse PIC, Bonding PIC and Encoding can remove a Lab Reference from a box.', 403);
        }
        $this->ensure();
        $p    = $this->request->getJSON(true) ?? [];
        $code = trim((string) ($p['box_code'] ?? ''));
        $box  = $this->box($code);
        $id   = (int) ($p['id'] ?? 0);
        $db   = $this->db();
        $row  = $box && $id > 0 ? $db->table('tbl_warehouse_box_items')->where('id', $id)->groupStart()->where('box_code', $code)->orWhere('box_name', $box['label'])->groupEnd()->get()->getRowArray() : null;
        if (! $row) {
            return $this->fail('That row is not in this box.', 404);
        }
        if (($row['item_status'] ?? '') === 'TRANSFERRED') {
            return $this->fail($row['lab_reference'] . ' was transferred to ' . ($row['moved_to_label'] ?: 'another box') . '. That row is the record of the move and stays; if the move was a mistake, fix it in the new box.');
        }
        $db->table('tbl_warehouse_box_items')->where('id', $id)->delete();
        \App\Libraries\ActivityLog::record('warehouse', 'remove-box-content', $code . ' ' . $box['label'], $row['lab_reference'] . ' taken out of the list (picked by mistake)' . (! empty($row['moved_from_code']) ? ', it had come from ' . $row['moved_from_code'] : ''));

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    /** POST warehouse-tracker/item-cols/add {label} (Admin) */
    public function addCol(): ResponseInterface
    {
        if (! $this->isAdmin()) {
            return $this->fail('Only an Admin can add a column.', 403);
        }
        $this->ensure();
        $p     = $this->request->getJSON(true) ?? [];
        $label = mb_substr(trim((string) ($p['label'] ?? '')), 0, 40);
        if (mb_strlen($label) < 2) {
            return $this->fail('Give the column a name (at least 2 letters).');
        }
        $key = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $label), '_'));
        $key = substr($key !== '' ? $key : 'col', 0, 36);
        $db  = $this->db();
        $row = $db->table('tbl_warehouse_item_cols')->where('col_key', $key)->get()->getRowArray();
        if ($row) {
            if ((int) $row['is_active'] === 1) {
                return $this->fail('There is already a column called "' . $row['label'] . '".');
            }
            $db->table('tbl_warehouse_item_cols')->where('id', $row['id'])->update(['is_active' => 1, 'label' => $label]);   // bring a hidden one back
        } else {
            $next = (int) ($db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM tbl_warehouse_item_cols')->getRow()->n ?? 1);
            $db->table('tbl_warehouse_item_cols')->insert(['col_key' => $key, 'label' => $label, 'sort_order' => $next, 'created_by' => (string) (service('auth')->user()['name'] ?? '')]);
        }
        \App\Libraries\ActivityLog::record('warehouse', 'add-contents-column', $label, 'New check column in the box contents lists');

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }

    /** POST warehouse-tracker/item-cols/hide {key} (Admin): the saved checks stay in the rows, the column is just not shown */
    public function hideCol(): ResponseInterface
    {
        if (! $this->isAdmin()) {
            return $this->fail('Only an Admin can remove a column.', 403);
        }
        $p = $this->request->getJSON(true) ?? [];
        $this->db()->table('tbl_warehouse_item_cols')->where('col_key', (string) ($p['key'] ?? ''))->update(['is_active' => 0]);
        \App\Libraries\ActivityLog::record('warehouse', 'hide-contents-column', (string) ($p['key'] ?? ''), 'Column hidden');

        return $this->response->setJSON(['ok' => true, 'csrf' => csrf_hash()]);
    }
}
