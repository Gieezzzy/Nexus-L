<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Browser push that arrives while NEXUS-L is closed (Web Push, RFC 8030 / 8291 / 8292), with no extra package: VAPID keys in writable/,
 * payload encryption with OpenSSL, delivery over HTTPS to the browser's push service (Google / Mozilla / Microsoft).
 * Browsers only allow it on https:// (or localhost), so people subscribe from https://<server>:9443/NEXUS-L/.
 *
 * WebPush::notify('Title', 'Text', 'lab-monitoring/vf-requests', ['module' => 'erpfinish', 'dedupe' => 'key']);
 */
class WebPush
{
    private static function db()
    {
        return \Config\Database::connect();
    }

    public static function ensureTables(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $db = self::db();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_push_subscriptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL DEFAULT "",
            role VARCHAR(120) NOT NULL DEFAULT "",
            endpoint VARCHAR(700) NOT NULL,
            p256dh VARCHAR(200) NOT NULL,
            auth VARCHAR(60) NOT NULL,
            user_agent VARCHAR(255) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL,
            last_ok_at DATETIME NULL,
            last_error VARCHAR(255) NOT NULL DEFAULT "",
            UNIQUE KEY uq_endpoint (endpoint(255)),
            KEY ix_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $db->query('CREATE TABLE IF NOT EXISTS tbl_push_log (
            dedupe_key VARCHAR(190) NOT NULL PRIMARY KEY,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    // ------------------------------------------------------------------------------------------------ keys

    public static function b64u(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function unb64u(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    /** OpenSSL on Windows needs to be told where its openssl.cnf is */
    private static function cfg(array $o): array
    {
        foreach ([getenv('OPENSSL_CONF') ?: '', 'C:/xampp/apache/conf/openssl.cnf', 'C:/xampp/php/extras/openssl/openssl.cnf'] as $c) {
            if ($c !== '' && is_file($c)) {
                $o['config'] = $c;
                break;
            }
        }

        return $o;
    }

    private static function keyFile(): string
    {
        return WRITEPATH . 'vapid_private.pem';
    }

    /** @return array{0:string,1:string} private PEM, public key (65 bytes, raw) */
    private static function vapid(): array
    {
        $f = self::keyFile();
        if (! is_file($f)) {
            $k = openssl_pkey_new(self::cfg(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]));
            if ($k === false) {
                throw new \RuntimeException('OpenSSL cannot create the VAPID key: ' . openssl_error_string());
            }
            openssl_pkey_export($k, $pem, null, self::cfg([]));
            file_put_contents($f, $pem);
        }
        $pem = (string) file_get_contents($f);
        $d   = openssl_pkey_get_details(openssl_pkey_get_private($pem));

        return [$pem, "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT)];
    }

    /** the public key the browser needs to subscribe (base64url) */
    public static function publicKey(): string
    {
        return self::b64u(self::vapid()[1]);
    }

    // ------------------------------------------------------------------------------------------------ sending

    /** DER signature of openssl_sign -> raw R||S (64 bytes) */
    private static function derToRaw(string $der): string
    {
        $o = 4;
        $rl = ord($der[3]);
        $r = substr($der, $o, $rl);
        $o += $rl + 2;
        $sl = ord($der[$o - 1]);
        $s = substr($der, $o, $sl);

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    private static function jwt(string $endpoint): string
    {
        [$pem, ] = self::vapid();
        $p   = parse_url($endpoint);
        $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        $in  = self::b64u((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.' . self::b64u((string) json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . (string) SystemSettings::get('notify.vapid_contact', 'admin@dtshoes.com')]));
        openssl_sign($in, $sig, $pem, OPENSSL_ALGO_SHA256);

        return $in . '.' . self::b64u(self::derToRaw($sig));
    }

    /** RFC 8291 aes128gcm body */
    private static function encrypt(string $payload, string $uaPublic, string $authSecret): string
    {
        $eph = openssl_pkey_new(self::cfg(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]));
        $d   = openssl_pkey_get_details($eph);
        $asPublic = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
        // the subscriber's public key as a PEM (SPKI wrapper of the raw point)
        $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $uaPublic;
        $peer = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . '-----END PUBLIC KEY-----');
        $secret = openssl_pkey_derive($peer, $eph, 32);
        if ($secret === false) {
            throw new \RuntimeException('ECDH failed');
        }
        $salt = random_bytes(16);
        $ikm  = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
        $cek  = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
    }

    /** @return array{0:int,1:string} HTTP status, body */
    private static function post(string $url, string $body, array $headers): array
    {
        $hdr = [];
        foreach ($headers as $k => $v) {
            $hdr[] = $k . ': ' . $v;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $hdr, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8]);
            $out  = (string) curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            return [$code, $code === 0 ? $err : $out];
        }
        $ssl = ['verify_peer' => true];
        foreach (['C:/xampp/apache/bin/curl-ca-bundle.crt', 'C:/xampp/php/extras/ssl/cacert.pem', 'C:/xampp/perl/vendor/lib/Mozilla/CA/cacert.pem'] as $ca) {
            if (is_file($ca)) {
                $ssl['cafile'] = $ca;
                break;
            }
        }
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $hdr), 'content' => $body, 'timeout' => 15, 'ignore_errors' => true], 'ssl' => $ssl]);
        $out = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $code = (int) $m[1];
            }
        }

        return [$code, $code === 0 ? 'no answer (internet / certificate?)' : (string) $out];
    }

    /** one push to one subscription; a gone subscription (404 / 410) is deleted. @return bool delivered */
    private static function sendOne(array $sub, string $payload): bool
    {
        try {
            $body = self::encrypt($payload, self::unb64u($sub['p256dh']), self::unb64u($sub['auth']));
            [$code, $resp] = self::post($sub['endpoint'], $body, [
                'Authorization' => 'vapid t=' . self::jwt($sub['endpoint']) . ', k=' . self::publicKey(),
                'Content-Encoding' => 'aes128gcm', 'Content-Type' => 'application/octet-stream', 'TTL' => '86400', 'Urgency' => 'high',
            ]);
        } catch (\Throwable $e) {
            $code = 0;
            $resp = $e->getMessage();
        }
        $db = self::db();
        if ($code >= 200 && $code < 300) {
            $db->table('tbl_push_subscriptions')->where('id', $sub['id'])->update(['last_ok_at' => date('Y-m-d H:i:s'), 'last_error' => '']);

            return true;
        }
        if ($code === 404 || $code === 410) {
            $db->table('tbl_push_subscriptions')->where('id', $sub['id'])->delete();
        } else {
            $db->table('tbl_push_subscriptions')->where('id', $sub['id'])->update(['last_error' => mb_substr($code . ' ' . $resp, 0, 250)]);
            log_message('error', '[WebPush] ' . $code . ' ' . mb_substr($resp, 0, 200));
        }

        return false;
    }

    /**
     * @param array{module?:string,user_ids?:list<string|int>,tag?:string,dedupe?:string,require_interaction?:bool} $opt
     *   module   only people whose role may open this module (Roles module key, e.g. erpfinish, interlink, dtr)
     *   user_ids only these users
     *   dedupe   a key; the same key is notified once only
     *
     * @return int how many devices were reached
     */
    public static function notify(string $title, string $body, string $url = '', array $opt = []): int
    {
        try {
            self::ensureTables();
            $db = self::db();
            if (! empty($opt['dedupe'])) {
                $k = mb_substr((string) $opt['dedupe'], 0, 190);
                if ($db->table('tbl_push_log')->where('dedupe_key', $k)->countAllResults() > 0) {
                    return 0;
                }
                $db->table('tbl_push_log')->insert(['dedupe_key' => $k, 'created_at' => date('Y-m-d H:i:s')]);
            }
            $qb = $db->table('tbl_push_subscriptions');
            if (! empty($opt['user_ids'])) {
                $qb->whereIn('user_id', array_map('strval', $opt['user_ids']));
            }
            $subs = $qb->get()->getResultArray();
            if (! empty($opt['module'])) {
                $subs = array_values(array_filter($subs, static fn ($s) => Roles::can((string) $s['role'], (string) $opt['module'], 1)));
            }
            if (! $subs) {
                return 0;
            }
            $payload = (string) json_encode(['title' => mb_substr($title, 0, 80), 'body' => mb_substr($body, 0, 240), 'url' => ltrim($url, '/'), 'tag' => (string) ($opt['tag'] ?? ''), 'at' => time()], JSON_UNESCAPED_UNICODE);
            $n = 0;
            foreach ($subs as $s) {
                $n += self::sendOne($s, $payload) ? 1 : 0;
            }

            return $n;
        } catch (\Throwable $e) {
            log_message('error', '[WebPush] ' . $e->getMessage());

            return 0;
        }
    }
}
