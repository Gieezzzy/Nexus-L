<?php

namespace App\Controllers;

use App\Libraries\QrPng;

/**
 * Phone camera link for a shoe picture (Lab Reports > Lab Tracker > + Bonding Shoe Collected).
 * When the ERP has no picture for a shoe (MTL #), the intake row shows a QR code. The QR holds this site + the Lab Reference + the
 * Test Type + a one-off token (valid 15 minutes). The phone opens it, takes a photo, and the picture is saved for that MTL #
 * (writable/uploads/shoe_photos/<MTL>.jpg, which dtr/erp-image serves first), and the intake row swaps the QR for the picture at once.
 * The token is the only credential on the phone side: the page and the upload work without a NEXUS-L sign-in.
 */
class PhotoLinkController extends BaseController
{
    private const MINUTES = 15;
    private const LOCK_SECONDS = 20;   // a phone that has not pinged for this long has been closed: its hold on the shoe is free

    private function db()
    {
        return \Config\Database::connect();
    }

    public static function photoDir(): string
    {
        $d = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'shoe_photos' . DIRECTORY_SEPARATOR;
        if (! is_dir($d)) {
            @mkdir($d, 0755, true);
        }

        return $d;
    }

    private function ensureTable(): void
    {
        $this->db()->query("CREATE TABLE IF NOT EXISTS tbl_photo_links (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token CHAR(40) NOT NULL,
            lab_ref VARCHAR(80) NOT NULL DEFAULT '',
            test_type VARCHAR(40) NOT NULL DEFAULT '',
            mtl VARCHAR(40) NOT NULL DEFAULT '',
            prod_code VARCHAR(60) NOT NULL DEFAULT '',
            status VARCHAR(12) NOT NULL DEFAULT 'pending',
            captured_at DATETIME NULL,
            captures INT UNSIGNED NOT NULL DEFAULT 0,
            created_by VARCHAR(100) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            UNIQUE KEY ux_token (token),
            KEY ix_mtl (mtl)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        static $checked = false;
        if (! $checked) {
            $checked = true;
            $have = array_column($this->db()->query('SHOW COLUMNS FROM tbl_photo_links')->getResultArray(), 'Field');
            $add  = [];
            foreach (['scan_dev' => "VARCHAR(40) NOT NULL DEFAULT ''", 'last_dev' => "VARCHAR(40) NOT NULL DEFAULT ''", 'scan_at' => 'DATETIME NULL'] as $c => $def) {
                if (! in_array($c, $have, true)) {
                    $add[] = "ADD COLUMN `{$c}` {$def}";
                }
            }
            if ($add) {
                $this->db()->query('ALTER TABLE tbl_photo_links ' . implode(', ', $add));
            }
        }
    }

    /** a phone (device id) is holding this shoe right now: it pinged within the last LOCK_SECONDS */
    private function held(array $r): bool
    {
        return (string) $r['scan_dev'] !== '' && $r['scan_at'] !== null && strtotime((string) $r['scan_at']) > time() - self::LOCK_SECONDS;
    }

    private function dev(): string
    {
        $d = (string) ($this->request->getGet('d') ?? '');

        return preg_match('/^[A-Za-z0-9]{8,40}$/', $d) ? $d : '';
    }

    /** the link row when the token is well formed, and (when given) the Lab Ref and Test Type match; null otherwise */
    private function row(string $token, ?string $lab = null, ?string $type = null): ?array
    {
        if (! preg_match('/^[a-f0-9]{40}$/', $token)) {
            return null;
        }
        $this->ensureTable();
        $r = $this->db()->table('tbl_photo_links')->where('token', $token)->get()->getRowArray();
        if (! $r || ($lab !== null && $lab !== $r['lab_ref']) || ($type !== null && $type !== $r['test_type'])) {
            return null;
        }

        return $r;
    }

    private function live(?array $r): bool
    {
        return $r !== null && strtotime((string) $r['expires_at']) > time();
    }

    private function json(array $d, int $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($d + ['csrf' => csrf_hash()]);
    }

    /** POST dtr/photo-link {lab_ref, test_type, mtl, code} (signed in) : a new 15-minute link; returns its QR picture and page address */
    public function create()
    {
        $p    = $this->request->getJSON(true) ?? [];
        $mtl  = trim((string) ($p['mtl'] ?? ''));
        $lab  = mb_substr(trim((string) ($p['lab_ref'] ?? '')), 0, 80);
        $type = mb_substr(trim((string) ($p['test_type'] ?? '')), 0, 40);
        $attach = ($p['kind'] ?? '') === 'attach';   // a picture to attach to a report message (Shoe Bonding U60): not tied to a shoe, no MTL #
        if ($lab === '' || (! $attach && ! preg_match('/^[A-Za-z0-9_\-]{3,40}$/', $mtl))) {
            return $this->json(['ok' => false, 'error' => $attach ? 'Open a bonding report first.' : 'Pick the Production Code first.'], 422);
        }
        $mtl = $attach ? '' : $mtl;
        $this->ensureTable();
        // one live QR per shoe (MTL #) / per report (Lab Ref): a second user opening the same shoe gets the QR that is already there,
        // so nobody can start a second capture of the same thing
        $live = $this->db()->table('tbl_photo_links')->where('mtl', $mtl)->where('status', 'pending')->where('expires_at >', date('Y-m-d H:i:s'));
        $live = $attach ? $live->where('lab_ref', $lab)->where('test_type', $type) : $live;
        $ex   = $live->orderBy('id', 'DESC')->get()->getRowArray();
        if ($ex) {
            return $this->json(['ok' => true, 'reused' => true, 'by' => (string) $ex['created_by'], 'token' => $ex['token'], 'expires_at' => $ex['expires_at'], 'minutes' => self::MINUTES,
                'lab_ref' => $ex['lab_ref'], 'scanning' => $this->held($ex),
                'qr' => site_url('photo/' . $ex['token'] . '/qr.png') . '?l=' . rawurlencode($ex['lab_ref']) . '&t=' . rawurlencode($ex['test_type'])]);
        }
        $me    = (array) service('auth')->user();
        $token = bin2hex(random_bytes(20));
        $now   = date('Y-m-d H:i:s');
        $exp   = date('Y-m-d H:i:s', time() + self::MINUTES * 60);
        $this->db()->table('tbl_photo_links')->insert([
            'token' => $token, 'lab_ref' => $lab, 'test_type' => $type, 'mtl' => $mtl, 'prod_code' => mb_substr(trim((string) ($p['code'] ?? '')), 0, 60),
            'created_by' => (string) ($me['name'] ?? $me['username'] ?? ''), 'created_at' => $now, 'expires_at' => $exp,
        ]);

        return $this->json(['ok' => true, 'token' => $token, 'expires_at' => $exp, 'minutes' => self::MINUTES, 'qr' => site_url('photo/' . $token . '/qr.png') . '?l=' . rawurlencode($lab) . '&t=' . rawurlencode($type)]);
    }

    private function pageUrl(array $r): string
    {
        return site_url('photo/' . $r['token']) . '?l=' . rawurlencode($r['lab_ref']) . '&t=' . rawurlencode($r['test_type']);
    }

    /** GET photo/<token>/qr.png?l=&t= : the QR picture (web + lab ref + test type + token) for a live link */
    public function qr(string $token)
    {
        $r = $this->row($token, (string) $this->request->getGet('l'), (string) $this->request->getGet('t'));
        if (! $this->live($r)) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->setHeader('Content-Type', 'image/png')->setHeader('Cache-Control', 'no-store')->setBody(QrPng::make($this->pageUrl($r), 8));
    }

    /** GET photo/<token>?l=&t= : the page the QR opens on the phone */
    public function page(string $token)
    {
        $lab  = (string) $this->request->getGet('l');
        $type = (string) $this->request->getGet('t');
        $r    = $this->row($token, $lab, $type);
        if (! $this->live($r)) {
            return $this->response->setStatusCode(410)->setBody(view('photo/capture', ['ok' => false]));
        }

        return view('photo/capture', ['ok' => true, 'r' => $r, 'upload' => site_url('photo/' . $token . '/upload') . '?l=' . rawurlencode($lab) . '&t=' . rawurlencode($type),
            'left' => max(0, strtotime((string) $r['expires_at']) - time())]);
    }

    /**
     * POST photo/<token>/claim?l=&t=&d=<device id> : the phone asks to capture (and then pings every few seconds to keep its hold).
     * Free, or already this device's -> ok. Another phone holds it -> busy (it must wait). Photo already taken by another device -> done.
     */
    public function claim(string $token)
    {
        $r = $this->row($token, (string) $this->request->getGet('l'), (string) $this->request->getGet('t'));
        $d = $this->dev();
        if (! $this->live($r) || $d === '') {
            return $this->json(['ok' => false, 'state' => 'expired'], 410);
        }
        if ($r['status'] === 'captured' && $r['last_dev'] !== $d) {
            return $this->json(['ok' => false, 'state' => 'done'], 409);
        }
        if ($this->held($r) && $r['scan_dev'] !== $d) {
            return $this->json(['ok' => false, 'state' => 'busy', 'wait' => max(1, strtotime((string) $r['scan_at']) + self::LOCK_SECONDS - time())], 409);
        }
        $this->db()->table('tbl_photo_links')->where('id', $r['id'])->update(['scan_dev' => $d, 'scan_at' => date('Y-m-d H:i:s')]);

        return $this->json(['ok' => true, 'state' => 'you']);
    }

    /** POST photo/<token>/release?l=&t=&d= : the phone was closed or left: its hold is freed at once (also sent by sendBeacon) */
    public function release(string $token)
    {
        $r = $this->row($token, (string) $this->request->getGet('l'), (string) $this->request->getGet('t'));
        $d = $this->dev();
        if ($r && $d !== '' && $r['scan_dev'] === $d) {
            $this->db()->table('tbl_photo_links')->where('id', $r['id'])->update(['scan_dev' => '', 'scan_at' => null]);
        }

        return $this->json(['ok' => true]);
    }

    /** POST photo/<token>/upload?l=&t=&d= (multipart field "photo") : saves the picture for the shoe (MTL #) and marks the link captured */
    public function upload(string $token)
    {
        $r = $this->row($token, (string) $this->request->getGet('l'), (string) $this->request->getGet('t'));
        if (! $this->live($r)) {
            return $this->json(['ok' => false, 'error' => 'This link has expired. Ask for a new QR code on the computer.'], 410);
        }
        $d = $this->dev();
        $mine = $d !== '' && (($r['scan_dev'] === $d) || ($r['status'] === 'captured' && $r['last_dev'] === $d));
        if ($this->held($r) && $r['scan_dev'] !== $d) {
            return $this->json(['ok' => false, 'error' => 'Another user is capturing this shoe. Wait for them to finish.'], 409);
        }
        if ($r['status'] === 'captured' && ! $mine) {
            return $this->json(['ok' => false, 'error' => 'A photo was already taken for this.'], 409);
        }
        $f = $this->request->getFile('photo');
        if (! $f || ! $f->isValid() || $f->getSize() > 12 * 1024 * 1024) {
            return $this->json(['ok' => false, 'error' => 'Take or choose a photo (up to 12 MB).'], 422);
        }
        $src = @imagecreatefromstring((string) file_get_contents($f->getTempName()));
        if (! $src) {
            return $this->json(['ok' => false, 'error' => 'That file is not a picture.'], 422);
        }
        // a phone photo is often stored sideways with an EXIF orientation flag: turn it upright
        if (function_exists('exif_read_data') && in_array(strtolower($f->getClientExtension()), ['jpg', 'jpeg'], true)) {
            $ori = (int) (@exif_read_data($f->getTempName())['Orientation'] ?? 1);
            $deg = [3 => 180, 6 => -90, 8 => 90][$ori] ?? 0;
            if ($deg !== 0 && ($rot = imagerotate($src, $deg, 0))) {
                $src = $rot;
            }
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $k = min(1, 1600 / max($w, $h));
        if ($k < 1) {
            $dst = imagecreatetruecolor((int) round($w * $k), (int) round($h * $k));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
            $src = $dst;
        }
        $file = $r['mtl'] === '' ? self::photoDir() . 'attach_' . $r['token'] . '.jpg' : self::photoDir() . $r['mtl'] . '.jpg';
        if (! imagejpeg($src, $file, 85)) {
            return $this->json(['ok' => false, 'error' => 'The picture could not be saved.'], 500);
        }
        $this->db()->table('tbl_photo_links')->where('id', $r['id'])->update(['status' => 'captured', 'captured_at' => date('Y-m-d H:i:s'), 'captures' => (int) $r['captures'] + 1, 'last_dev' => $d, 'scan_dev' => '', 'scan_at' => null]);

        return $this->json(['ok' => true]);
    }

    /** GET dtr/photo-status?token= (signed in) : pending | captured | expired, with a version that changes on each new capture */
    public function status()
    {
        $r = $this->row((string) $this->request->getGet('token'));
        if (! $r) {
            return $this->json(['ok' => false, 'status' => 'expired'], 404);
        }
        $state = $r['status'] === 'captured' ? 'captured' : ($this->live($r) ? 'pending' : 'expired');

        return $this->json(['ok' => true, 'status' => $state, 'v' => (int) $r['captures'], 'left' => max(0, strtotime((string) $r['expires_at']) - time()), 'mtl' => $r['mtl'], 'scanning' => $state === 'pending' && $this->held($r)]);
    }

    /** GET dtr/photo-image?token= (signed in) : the picture a phone sent for an attach link */
    public function image()
    {
        $r = $this->row((string) $this->request->getGet('token'));
        $f = $r && $r['mtl'] === '' ? self::photoDir() . 'attach_' . $r['token'] . '.jpg' : '';
        if ($f === '' || $r['status'] !== 'captured' || ! is_file($f)) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->setHeader('Content-Type', 'image/jpeg')->setHeader('Cache-Control', 'no-store')->setBody((string) file_get_contents($f));
    }
}
