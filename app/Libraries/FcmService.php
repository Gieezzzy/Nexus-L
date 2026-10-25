<?php

namespace App\Libraries;

use Config\Rms;

/**
 * NEXUS-L's own Firebase Cloud Messaging (HTTP v1) sender for the RMS app.
 * Payload shape matches what the Android MyFirebaseMessagingService expects.
 */
class FcmService
{
    private Rms $cfg;
    private ?array $account = null;

    public function __construct(?Rms $cfg = null)
    {
        $this->cfg = $cfg ?? config(Rms::class);
    }

    public function isConfigured(): bool
    {
        return $this->account() !== null;
    }

    private function account(): ?array
    {
        if ($this->account === null) {
            $raw  = is_file($this->cfg->fcmKeyFile) ? file_get_contents($this->cfg->fcmKeyFile) : false;
            $data = $raw !== false ? json_decode($raw, true) : null;
            $this->account = (is_array($data) && ! empty($data['private_key']) && ! empty($data['client_email']) && ! empty($data['project_id'])) ? $data : [];
        }

        return $this->account === [] ? null : $this->account;
    }

    /** OAuth2 access token for the service account, cached (token lives ~1 hour). */
    private function accessToken(): ?string
    {
        $cache = cache();
        if (is_string($tok = $cache->get('rms_fcm_access_token'))) {
            return $tok;
        }
        $sa = $this->account();
        if ($sa === null) {
            return null;
        }

        $b64 = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode([
            'iss' => $sa['client_email'], 'sub' => $sa['client_email'],
            'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        ]));
        $sig = '';
        if (! openssl_sign($unsigned, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            log_message('error', '[FCM] Could not sign JWT with the service-account key.');

            return null;
        }

        $resp = $this->post('https://oauth2.googleapis.com/token', http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $unsigned . '.' . $b64($sig),
        ]), ['Content-Type: application/x-www-form-urlencoded']);

        $json = is_string($resp) ? json_decode($resp, true) : null;
        if (empty($json['access_token'])) {
            log_message('error', '[FCM] Token request failed.');

            return null;
        }
        $cache->save('rms_fcm_access_token', $json['access_token'], max(60, (int) ($json['expires_in'] ?? 3600) - 120));

        return $json['access_token'];
    }

    /** @return string|false response body, false on transport error */
    private function post(string $url, string $body, array $headers)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $this->cfg->fcmTimeout, CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => $this->cfg->fcmVerifySSL, CURLOPT_SSL_VERIFYHOST => $this->cfg->fcmVerifySSL ? 2 : 0,
        ]);
        $out = curl_exec($ch);
        if ($out === false) {
            log_message('error', '[FCM] cURL: ' . curl_error($ch));
        }
        curl_close($ch);

        return $out;
    }

    /** Device tokens stored for an RMS user (users.fcm_token is ";"-separated; DEVICE_* are placeholders). */
    public function tokensFor(string $userKey): array
    {
        $rows = RmsDb::rows(
            "SELECT TOP 1 fcm_token FROM dbo.users WHERE (synologyID = ? OR username = ? OR CAST(id AS VARCHAR(20)) = ?) AND fcm_token IS NOT NULL AND fcm_token <> ''",
            [$userKey, $userKey, $userKey],
        );
        $out = [];
        foreach (explode(';', (string) ($rows[0]['fcm_token'] ?? '')) as $t) {
            $t = trim($t);
            if ($t !== '' && ! str_starts_with($t, 'DEVICE_') && ! in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    private function pruneToken(string $dead): void
    {
        try {
            foreach (RmsDb::rows('SELECT id, fcm_token FROM dbo.users WHERE fcm_token LIKE ?', ['%' . $dead . '%']) as $r) {
                $keep = array_filter(array_map('trim', explode(';', (string) $r['fcm_token'])), static fn ($t) => $t !== '' && $t !== $dead);
                RmsDb::exec('UPDATE dbo.users SET fcm_token = ? WHERE id = ?', [implode(';', $keep), $r['id']]);
            }
        } catch (\Throwable $e) {
            log_message('error', '[FCM] prune failed: ' . $e->getMessage());
        }
    }

    /** Push one chat message to one device. Returns true when FCM accepted it. */
    public function sendChat(string $deviceToken, string $title, string $body, array $data): bool
    {
        $access = $this->accessToken();
        $sa     = $this->account();
        if ($access === null || $sa === null) {
            return false;
        }

        $message = [
            'token' => $deviceToken,
            'data'  => array_map('strval', $data + ['TARGET_FRAGMENT' => 'MessageFragment']),
            'notification' => ['title' => $title, 'body' => $body],
            'android' => [
                'priority' => 'HIGH', 'direct_boot_ok' => true,
                'notification' => [
                    'channel_id' => $this->cfg->fcmChannel, 'icon' => 'ic_launcher', 'sound' => 'default',
                    'default_sound' => true, 'default_vibrate_timings' => true, 'default_light_settings' => true,
                    'notification_priority' => 'PRIORITY_MAX', 'visibility' => 'PUBLIC',
                ],
            ],
        ];

        $resp = $this->post(
            "https://fcm.googleapis.com/v1/projects/{$sa['project_id']}/messages:send",
            json_encode(['message' => $message]),
            ['Authorization: Bearer ' . $access, 'Content-Type: application/json'],
        );
        $json = is_string($resp) ? json_decode($resp, true) : null;
        if (isset($json['error'])) {
            if (($json['error']['status'] ?? '') === 'NOT_FOUND' || str_contains((string) $resp, 'UNREGISTERED')) {
                $this->pruneToken($deviceToken);
            }
            log_message('error', '[FCM] ' . ($json['error']['status'] ?? 'error'));

            return false;
        }

        return $resp !== false;
    }
}
