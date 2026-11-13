<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class WarehouseTrackerController extends BaseController
{
    private function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }

    /** Main 3D Warehouse Tracker View */
    public function index(): string
    {
        $user = service('auth')->user();
        $isAdmin = \App\Libraries\Roles::isAdmin((string) ($user['role'] ?? ''));

        return $this->render('warehouse/index', [
            'title'       => 'Warehouse 3D Tracker',
            'withCss'     => 'warehouse.css',
            'withJs'      => [
                'libs/three.min.js',
                'libs/OrbitControls.js',
                'coolguy-data.js',
                'avatar-factory.js',
                'asylum-world.js',
                'asylum-minigame.js',
                'warehouse-3d.js',
                'warehouse-multi.js',
            ],
            'myLook'      => $this->savedLook((int) ($user['id'] ?? 0)),
            'isAdmin'     => $isAdmin,
            'currentUser' => $user,
            'today'       => date('Y-m-d'),
        ]);
    }

    /** API: Get all boxes and storage statistics */
    public function boxes(): ResponseInterface
    {
        $db = $this->db();
        $rows = $db->table('tbl_warehouse_boxes')
            ->orderBy('rack_id', 'ASC')
            ->orderBy('tier_level', 'DESC')
            ->orderBy('slot_index', 'ASC')
            ->get()->getResultArray();

        $stats = [
            'total'        => count($rows),
            'active'       => 0,
            'for_disposal' => 0,
            'archived'     => 0,
            'categories'   => [],
            'racks'        => [],
        ];

        foreach ($rows as $r) {
            $st = strtoupper((string) ($r['status'] ?? 'ACTIVE'));
            if ($st === 'FOR_DISPOSAL') {
                $stats['for_disposal']++;
            } elseif ($st === 'ARCHIVED') {
                $stats['archived']++;
            } else {
                $stats['active']++;
            }

            $cat = (string) ($r['category'] ?: 'Other');
            $stats['categories'][$cat] = ($stats['categories'][$cat] ?? 0) + 1;

            $rk = (string) ($r['rack_id'] ?: 'UNKNOWN');
            $stats['racks'][$rk] = ($stats['racks'][$rk] ?? 0) + 1;
        }

        return $this->response->setJSON([
            'ok'    => true,
            'boxes' => $rows,
            'stats' => $stats,
        ]);
    }

    /** API: Official DTP QC Laboratory Rack Locator & FIFO Color Configuration */
    public function racks(): ResponseInterface
    {
        // Official Racks matching CAD Floor Plan in 45x45 Warehouse space
        $racks = [
            [
                'id'          => 'RACK-1',
                'name'        => 'Rack 1 (Blue) - Main Long Rack',
                'theme_color' => '#1d4ed8', // Official Blue tag
                'aisle_near'  => 'Main Aisle 01 (Entrance to North)',
                'tiers'       => 5,
                'slots'       => 4,
                'shelf_types' => [
                    'Daily Bonding',
                    'Daily Failed Bonding',
                    'Tech Trial Bonding',
                ],
                'pos'         => ['x' => 11.3, 'y' => 0, 'z' => 1.17],
                'size'        => ['w' => 1.6, 'h' => 4.2, 'l' => 30.6],
                'waypoint'    => ['x' => 13.1, 'z' => 1.17],
                'labels'      => ['RACK 1', 'BONDING & TECH TRIAL'],
            ],
            [
                'id'          => 'RACK-2',
                'name'        => 'Rack 2 (Brown) - Mid-Left Rack',
                'theme_color' => '#c25e3d', // Official Brown tag
                'aisle_near'  => 'Aisle 02 (Center Walkway)',
                'tiers'       => 5,
                'slots'       => 3,
                'shelf_types' => [
                    'Whole Shoe (Flexing / UV / Washing / Hydrolysis / Heat Aging)',
                ],
                'pos'         => ['x' => -5.56, 'y' => 0, 'z' => 6.04],
                'size'        => ['w' => 1.6, 'h' => 4.2, 'l' => 20.9],
                'waypoint'    => ['x' => -3.76, 'z' => 6.04],
                'labels'      => ['RACK 2', 'WHOLE SHOE SPECIMENS'],
            ],
            [
                'id'          => 'RACK-3',
                'name'        => 'Rack 3 (Green) - Far-Left Rack',
                'theme_color' => '#15803d', // Official Green tag
                'aisle_near'  => 'Aisle 03 (West Wall)',
                'tiers'       => 5,
                'slots'       => 3,
                'shelf_types' => [
                    'Old Test Reports',
                    'First Article Shoe Specimens',
                    'Sample Shoes',
                ],
                'pos'         => ['x' => -15.4, 'y' => 0, 'z' => 6.04],
                'size'        => ['w' => 1.6, 'h' => 4.2, 'l' => 20.9],
                'waypoint'    => ['x' => -13.6, 'z' => 6.04],
                'labels'      => ['RACK 3', 'REPORTS & SAMPLE SHOES'],
            ],
            [
                'id'          => 'RACK-4',
                'name'        => 'Rack 4 (Purple) - North Wall Rack',
                'theme_color' => '#6366f1', // Official Purple tag
                'aisle_near'  => 'Aisle 04 (North Corridor)',
                'tiers'       => 5,
                'slots'       => 4,
                'shelf_types' => [
                    'Material Scrap',
                    'Material Specimens',
                ],
                'pos'         => ['x' => 8.8, 'y' => 0, 'z' => -20.6],
                'size'        => ['w' => 16.1, 'h' => 4.2, 'l' => 1.6], // Horizontal along north wall
                'waypoint'    => ['x' => 8.8, 'z' => -18.8],
                'labels'      => ['RACK 4', 'MATERIAL SPECIMENS & SCRAP'],
            ],
            [
                'id'          => 'RACK-PART',
                'name'        => 'Partition Wall Rack',
                'theme_color' => '#4338ca', // Indigo tag
                'aisle_near'  => 'Aisle 04 (Storage Wall)',
                'tiers'       => 5,
                'slots'       => 2,
                'shelf_types' => [
                    'Material Scrap',
                    'Material Specimens',
                ],
                'pos'         => ['x' => 2.75, 'y' => 0, 'z' => -13.0],
                'size'        => ['w' => 1.6, 'h' => 4.2, 'l' => 11.25],
                'waypoint'    => ['x' => 4.55, 'z' => -13.0],
                'labels'      => ['PARTITION RACK', 'MATERIAL STORAGE'],
            ],
            [
                'id'          => 'RACK-WINDOW',
                'name'        => 'East Window Rack',
                'theme_color' => '#0284c7', // Cyan/Sky tag
                'aisle_near'  => 'Main Aisle (Window Side)',
                'tiers'       => 5,
                'slots'       => 4,
                'shelf_types' => [
                    'Specimen Trays & Ziplocks',
                    'Window Retain Storage',
                ],
                'pos'         => ['x' => 20.75, 'y' => 0, 'z' => -2.1],
                'size'        => ['w' => 1.5, 'h' => 3.8, 'l' => 28.1],
                'waypoint'    => ['x' => 19.0, 'z' => -2.1],
                'labels'      => ['WINDOW SHELF', 'SPECIMEN TRAYS'],
            ],
        ];

        // Official FIFO Monthly Label Color Reference
        $fifoColors = [
            1  => ['month' => 'January',   'zh' => '一月',   'hex' => '#3e2723', 'name' => 'Dark Brown'],
            2  => ['month' => 'February',  'zh' => '二月',   'hex' => '#ba68c8', 'name' => 'Lavender'],
            3  => ['month' => 'March',     'zh' => '三月',   'hex' => '#880e4f', 'name' => 'Maroon'],
            4  => ['month' => 'April',     'zh' => '四月',   'hex' => '#e65100', 'name' => 'Orange (For Disposal)'],
            5  => ['month' => 'May',       'zh' => '五月',   'hex' => '#0288d1', 'name' => 'Sky Blue'],
            6  => ['month' => 'June',      'zh' => '六月',   'hex' => '#f57f17', 'name' => 'Golden Yellow'],
            7  => ['month' => 'July',      'zh' => '七月',   'hex' => '#c2185b', 'name' => 'Magenta Pink'],
            8  => ['month' => 'August',    'zh' => '八月',   'hex' => '#2e7d32', 'name' => 'Bright Green'],
            9  => ['month' => 'September', 'zh' => '九月',   'hex' => '#c62828', 'name' => 'Red'],
            10 => ['month' => 'October',   'zh' => '十月',   'hex' => '#7986cb', 'name' => 'Periwinkle Blue'],
            11 => ['month' => 'November',  'zh' => '十一月', 'hex' => '#00838f', 'name' => 'Teal Cyan'],
            12 => ['month' => 'December',  'zh' => '十二月', 'hex' => '#212121', 'name' => 'Black'],
        ];

        return $this->response->setJSON([
            'ok'          => true,
            'room'        => [
                'area_m2'        => 2025,
                'width'          => 45.0,
                'length'         => 45.0,
                'height'         => 5.2,
                'grid_size'      => '45x45',
                'warehouse_size' => [45, 45],
                'door'           => ['x' => 7.0, 'y' => 0, 'z' => 22.3],
            ],
            'racks'       => $racks,
            'fifo_colors' => $fifoColors,
        ]);
    }

    /** API: Save (Insert or Update) a Box record (Admin Only) */
    public function saveBox(): ResponseInterface
    {
        $user = service('auth')->user();
        if (! \App\Libraries\Roles::isAdmin((string) ($user['role'] ?? ''))) {
            return $this->response->setStatusCode(403)->setJSON([
                'ok'    => false,
                'error' => 'Permission denied. Only Administrators can add or edit warehouse boxes.',
            ]);
        }

        $req = $this->request;
        $id = (int) ($req->getPost('id') ?? 0);
        $boxCode = trim((string) $req->getPost('box_code'));
        $label = trim((string) $req->getPost('label'));
        $category = trim((string) ($req->getPost('category') ?: 'Daily Bonding'));
        $rackId = trim((string) $req->getPost('rack_id'));
        $tier = (int) ($req->getPost('tier_level') ?: 1);
        $slot = (int) ($req->getPost('slot_index') ?: 1);
        $dateRange = trim((string) $req->getPost('date_range'));
        $monthYear = trim((string) $req->getPost('month_year'));
        $contents = trim((string) $req->getPost('contents_desc'));
        $status = strtoupper(trim((string) ($req->getPost('status') ?: 'ACTIVE')));

        if ($label === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'ok'    => false,
                'error' => 'Box Label or Description is required.',
            ]);
        }

        if ($boxCode === '') {
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category) ?: 'BX', 0, 4));
            $boxCode = 'BX-' . $prefix . '-' . date('Ymd-His');
        }

        $rackNames = [
            'RACK-1' => 'Rack 1 (Blue)',
            'RACK-2' => 'Rack 2 (Brown)',
            'RACK-3' => 'Rack 3 (Green)',
            'RACK-4' => 'Rack 4 (Purple)',
        ];
        $rackName = $rackNames[$rackId] ?? ($rackId ?: 'Rack 1 (Blue)');

        $data = [
            'box_code'      => $boxCode,
            'label'         => $label,
            'category'      => $category,
            'rack_id'       => $rackId ?: 'RACK-1',
            'rack_name'     => $rackName,
            'tier_level'    => max(1, min(5, $tier)),
            'slot_index'    => max(1, min(4, $slot)),
            'date_range'    => $dateRange ?: null,
            'month_year'    => $monthYear ?: date('Y-m'),
            'contents_desc' => $contents ?: null,
            'status'        => in_array($status, ['ACTIVE', 'FOR_DISPOSAL', 'ARCHIVED'], true) ? $status : 'ACTIVE',
            'created_by'    => (string) ($user['name'] ?? 'Admin'),
        ];

        $db = $this->db();
        if ($id > 0) {
            $db->table('tbl_warehouse_boxes')->where('id', $id)->update($data);
            $msg = 'Box updated successfully.';
        } else {
            $exists = $db->table('tbl_warehouse_boxes')->where('box_code', $boxCode)->countAllResults();
            if ($exists > 0) {
                $boxCode .= '-' . substr(uniqid(), -4);
                $data['box_code'] = $boxCode;
            }
            $db->table('tbl_warehouse_boxes')->insert($data);
            $id = $db->insertID();
            $msg = 'New box added to warehouse successfully.';
        }

        $saved = $db->table('tbl_warehouse_boxes')->where('id', $id)->get()->getRowArray();

        return $this->response->setJSON([
            'ok'      => true,
            'message' => $msg,
            'box'     => $saved,
        ]);
    }

    /** API: Delete or Dispose Box (Admin Only) */
    public function deleteBox(): ResponseInterface
    {
        $user = service('auth')->user();
        if (! \App\Libraries\Roles::isAdmin((string) ($user['role'] ?? ''))) {
            return $this->response->setStatusCode(403)->setJSON([
                'ok'    => false,
                'error' => 'Permission denied. Only Administrators can delete boxes.',
            ]);
        }

        $id = (int) ($this->request->getPost('id') ?? 0);
        $hard = (bool) ($this->request->getPost('hard_delete') ?? false);

        $db = $this->db();
        if ($hard) {
            $db->table('tbl_warehouse_boxes')->where('id', $id)->delete();
            $msg = 'Box permanently removed.';
        } else {
            $db->table('tbl_warehouse_boxes')->where('id', $id)->update([
                'status' => 'FOR_DISPOSAL',
            ]);
            $msg = 'Box marked as For Disposal.';
        }

        return $this->response->setJSON([
            'ok'      => true,
            'message' => $msg,
        ]);
    }

    /**
     * GET warehouse-tracker/voice?t=<text>: Lexus's voice as a WAV (a natural male voice made on this server by tools/tts/tts_server.py).
     * Every sentence is made once and kept in writable/tts/cache, so repeated lines play at once.
     */
    public function voice()
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $this->request->getGet('t'))));
        $text = mb_substr($text, 0, 400);
        if (mb_strlen($text) < 2) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false]);
        }
        $dir = WRITEPATH . 'tts/cache';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . sha1('ryan-medium|' . $text) . '.wav';
        if (! is_file($file)) {
            $wav = $this->synthesize($text);
            if ($wav === null) {
                return $this->response->setStatusCode(503)->setJSON(['ok' => false, 'error' => 'The voice service is not available.']);
            }
            file_put_contents($file, $wav);
        }

        return $this->response->setHeader('Content-Type', 'audio/wav')->setHeader('Cache-Control', 'private, max-age=86400')->setBody((string) file_get_contents($file));
    }

    /** asks the local voice server; starts it when it is not running */
    private function synthesize(string $text): ?string
    {
        $url = 'http://127.0.0.1:5061/tts?text=' . rawurlencode($text);
        for ($try = 0; $try < 2; $try++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 2]);
            $raw  = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($raw !== false && $code === 200 && str_starts_with((string) $raw, 'RIFF')) {
                return (string) $raw;
            }
            if ($try === 0 && $raw === false) {   // not running: start it and wait for the voice to load
                $py     = is_file('C:\Python314\pythonw.exe') ? 'C:\Python314\pythonw.exe' : 'pythonw';
                $script = ROOTPATH . 'tools' . DIRECTORY_SEPARATOR . 'tts' . DIRECTORY_SEPARATOR . 'tts_server.py';
                pclose(popen('start "" /B "' . $py . '" "' . $script . '"', 'r'));
                for ($w = 0; $w < 20; $w++) {
                    sleep(1);
                    $h = @file_get_contents('http://127.0.0.1:5061/health', false, stream_context_create(['http' => ['timeout' => 1]]));
                    if ($h !== false) {
                        break;
                    }
                }
            }
        }

        return null;
    }

    // ======================================================================================================================
    // SEVERAL USERS IN THE WAREHOUSE: positions and chat are passed along through a temporary file (nothing goes to the database);
    // only the look of each avatar is saved, with the account.
    // ======================================================================================================================
    private const LOOK_ENUMS = [
        'kind'   => ['human', 'dog', 'cat', 'panda', 'snake'],
        'gender' => ['male', 'female'],
        'build'  => ['thin', 'normal', 'fat'],
        'hair'   => ['none', 'short', 'long', 'spiky', 'bun', 'ponytail', 'afro'],
        'outfit' => ['gown', 'casual', 'suit', 'dress'],
    ];

    /** a look from outside, reduced to what is allowed (the same rules as the avatar factory in the browser) */
    private function cleanLook($in): array
    {
        $in  = is_array($in) ? $in : [];
        $out = [];
        foreach (self::LOOK_ENUMS as $k => $allowed) {
            $v       = $in[$k] ?? null;
            $out[$k] = in_array($v, $allowed, true) ? $v : $allowed[$k === 'build' ? 1 : 0];
        }
        $colors = ['skin' => '#c68a5c', 'hairColor' => '#18110b', 'outfitColor' => '#f8fafc', 'pants' => '#334155', 'eyes' => '#0f172a', 'fur' => '#b9783d', 'accent' => '#3a2412'];
        foreach ($colors as $k => $d) {
            $v       = $in[$k] ?? '';
            $out[$k] = (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? strtolower($v) : $d;
        }
        $h              = (float) ($in['height'] ?? 1.0);
        $out['height']  = max(0.8, min(1.2, $h > 0 ? $h : 1.0));
        $out['glasses'] = ! empty($in['glasses']);
        $out['cap']     = ! empty($in['cap']);

        return $out;
    }

    /** the saved look of an account (or the default look) */
    private function savedLook(int $uid): array
    {
        try {
            $row = \Config\Database::connect()->table('tbl_system_settings')->where('skey', 'wh.avatar.' . $uid)->get()->getRowArray();
            if ($row && ($j = json_decode((string) $row['svalue'], true))) {
                return $this->cleanLook($j);
            }
        } catch (\Throwable $e) {
            // no table or no row: the default look
        }

        return $this->cleanLook([]);
    }

    /** POST warehouse-tracker/avatar {look}: saves the look of the signed-in user */
    public function avatarSave()
    {
        $user = $this->auth->user();
        $uid  = (int) ($user['id'] ?? 0);
        if ($uid <= 0) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false]);
        }
        $in   = $this->request->getJSON(true) ?? [];
        $look = $this->cleanLook($in['look'] ?? []);
        try {
            $db   = \Config\Database::connect();
            $key  = 'wh.avatar.' . $uid;
            $data = ['svalue' => json_encode($look), 'category' => 'warehouse', 'updated_by' => (string) ($user['username'] ?? ''), 'updated_at' => date('Y-m-d H:i:s')];
            $row  = $db->table('tbl_system_settings')->where('skey', $key)->get()->getRowArray();
            if ($row) {
                $db->table('tbl_system_settings')->where('id', $row['id'])->update($data);
            } else {
                $db->table('tbl_system_settings')->insert($data + ['skey' => $key]);
            }
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false]);
        }

        return $this->response->setJSON(['ok' => true, 'look' => $look]);
    }

    /**
     * POST warehouse-tracker/rt: one round of the live poll. The page sends where its avatar is (and its look when it changed, and a chat
     * message when there is one); the answer holds the other users that are here and the chat messages since the last poll.
     * State lives in writable/wh-live/state.json for a few seconds (users) or minutes (messages) only.
     */
    public function rt()
    {
        $user = $this->auth->user();
        $isGuest = ! empty($user['is_guest']);
        $uid  = $isGuest ? ('guest_' . substr(md5((string) session_id()), 0, 8)) : (string) ((int) ($user['id'] ?? 0));
        if ($uid === '0') {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false]);
        }
        $in  = $this->request->getJSON(true) ?? [];
        $dir = WRITEPATH . 'wh-live';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fh = @fopen($dir . '/state.json', 'c+');
        if (! $fh) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false]);
        }
        flock($fh, LOCK_EX);
        $st = json_decode((string) stream_get_contents($fh), true);
        if (! is_array($st) || ! isset($st['users'], $st['chat'], $st['seq'])) {
            $st = ['users' => [], 'chat' => [], 'seq' => 0];
        }
        $now = microtime(true);
        foreach ($st['users'] as $k => $u) {
            if ($now - (float) ($u['t'] ?? 0) > 8) {                // gone: no poll for 8 seconds
                unset($st['users'][$k]);
            }
        }
        $st['chat'] = array_values(array_filter($st['chat'], static fn ($m) => $now - (float) $m['t'] < 180));   // messages live 3 minutes
        $st['chat'] = array_slice($st['chat'], -60);

        $name = trim((string) ($user['name'] ?? '')) ?: (string) ($user['username'] ?? 'Guest');
        $me   = $st['users'][$uid] ?? ['id' => $uid, 'look' => null, 'x' => 0, 'z' => 0, 'yaw' => 0, 'mv' => 0, 'zone' => 'warehouse', 'lastChat' => 0];
        $me['name'] = mb_substr(strip_tags($name), 0, 40);
        foreach (['x', 'z'] as $k) {
            if (isset($in[$k]) && is_numeric($in[$k])) {
                $me[$k] = max(-40, min(40, (float) $in[$k]));
            }
        }
        foreach (['yaw', 'mv'] as $k) {
            if (isset($in[$k]) && is_numeric($in[$k])) {
                $me[$k] = max(-10, min(10, (float) $in[$k]));
            }
        }
        $me['zone'] = (($in['zone'] ?? '') === 'asylum') ? 'asylum' : 'warehouse';
        $me['floor'] = isset($in['floor']) && is_numeric($in['floor']) ? max(0, min(4, (int) $in['floor'])) : 0;
        $me['y']     = isset($in['y']) && is_numeric($in['y']) ? max(-4, min(4, (float) $in['y'])) : 0;
        if (isset($in['look']) && is_array($in['look'])) {
            $me['look'] = $this->cleanLook($in['look']);
        }
        if ($me['look'] === null) {
            $me['look'] = $this->savedLook((int) $uid);
        }
        $me['t'] = $now;
        if (isset($in['chat']) && is_string($in['chat']) && $now - (float) ($me['lastChat'] ?? 0) > 0.4) {
            $text = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($in['chat'])));
            if ($text !== '') {
                $st['seq']++;
                $st['chat'][]   = ['id' => $st['seq'], 'uid' => $uid, 'name' => $me['name'], 'text' => mb_substr($text, 0, 240), 't' => $now];
                $me['lastChat'] = $now;
            }
        }
        $st['users'][$uid] = $me;

        $since  = ! empty($in['join']) ? (int) $st['seq'] : (int) ($in['since'] ?? 0);
        $msgs   = array_values(array_filter($st['chat'], static fn ($m) => (int) $m['id'] > $since));
        $others = [];
        foreach ($st['users'] as $k => $u) {
            if ((string) $k === $uid) {
                continue;
            }
            $others[] = ['id' => $u['id'], 'name' => $u['name'], 'x' => round($u['x'], 2), 'z' => round($u['z'], 2), 'yaw' => round($u['yaw'], 2), 'mv' => $u['mv'], 'zone' => $u['zone'], 'floor' => $u['floor'] ?? 0, 'y' => round($u['y'] ?? 0, 2), 'look' => $u['look']];
        }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($st));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);

        return $this->response->setJSON(['ok' => true, 'me' => $uid, 'users' => $others, 'chat' => $msgs, 'seq' => (int) $st['seq']]);
    }
}
