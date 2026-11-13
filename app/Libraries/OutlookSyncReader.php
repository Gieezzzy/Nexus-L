<?php

namespace App\Libraries;

/**
 * Reads & decrypts email records stored by OutlookAttachmentSync (AES-256-GCM "enc:v1:" and "OAS1" binary blobs)
 * directly from outlook_attachment_sync.email_records on the shared MySQL host.
 */
class OutlookSyncReader
{
    private const AAD = 'outlook-attachment-sync:v1';

    private ?string $keyRaw = null;

    public function __construct(?string $base64OrHexKey = null)
    {
        $raw = trim((string) $base64OrHexKey);
        if ($raw === '') {
            $raw = trim((string) SystemSettings::get('outlook_sync.secret_key', ''));
        }
        if ($raw === '') {
            foreach ([
                'D:\\desktop\\MaterialTest\\OutlookAttachmentSync\\data\\secret.key',
                'E:\\desktop\\MaterialTest\\OutlookAttachmentSync\\data\\secret.key',
            ] as $path) {
                if (is_file($path)) {
                    $raw = trim((string) file_get_contents($path));
                    if ($raw !== '') {
                        break;
                    }
                }
            }
        }
        if ($raw !== '') {
            $this->keyRaw = self::decodeKey($raw);
        }
    }

    public function hasKey(): bool
    {
        return $this->keyRaw !== null && strlen($this->keyRaw) === 32;
    }

    private static function fromUrlSafeBase64(string $text): string|false
    {
        $s = strtr(trim($text), '-_', '+/');
        $mod = strlen($s) % 4;
        if ($mod > 0) {
            $s .= str_repeat('=', 4 - $mod);
        }

        return base64_decode($s, true);
    }

    private static function decodeKey(string $value): ?string
    {
        $b = self::fromUrlSafeBase64($value);
        if ($b !== false && strlen($b) === 32) {
            return $b;
        }
        if (ctype_xdigit($value) && strlen($value) === 64) {
            $h = @hex2bin($value);
            if ($h !== false && strlen($h) === 32) {
                return $h;
            }
        }

        return null;
    }

    public function decryptText(string $token): string
    {
        if ($token === '' || ! str_starts_with($token, 'enc:v1:')) {
            return $token;
        }
        if (! $this->hasKey()) {
            throw new \RuntimeException('OutlookAttachmentSync secret key is not configured in NEXUS-L.');
        }
        $parts = explode(':', $token, 4);
        if (count($parts) !== 4) {
            throw new \RuntimeException('Malformed OutlookAttachmentSync encrypted payload.');
        }
        $packed = self::fromUrlSafeBase64($parts[3]);
        if ($packed === false || strlen($packed) < 28) {
            throw new \RuntimeException('Encrypted email payload is too short.');
        }
        $nonce = substr($packed, 0, 12);
        $tag   = substr($packed, -16);
        $ct    = substr($packed, 12, -16);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $this->keyRaw, OPENSSL_RAW_DATA, $nonce, $tag, self::AAD);
        if ($plain === false) {
            throw new \RuntimeException('Failed to decrypt email payload with current OutlookAttachmentSync secret key.');
        }

        return $plain;
    }

    public function decryptBytes(string $blob): string
    {
        if (strlen($blob) < 32 || ! str_starts_with($blob, 'OAS1')) {
            return $blob;
        }
        if (! $this->hasKey()) {
            throw new \RuntimeException('OutlookAttachmentSync secret key missing for binary attachment.');
        }
        $nonce = substr($blob, 4, 12);
        $tag   = substr($blob, -16);
        $ct    = substr($blob, 16, -16);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $this->keyRaw, OPENSSL_RAW_DATA, $nonce, $tag, self::AAD);
        if ($plain === false) {
            throw new \RuntimeException('Binary attachment decryption failed.');
        }

        return $plain;
    }

    /** Resolves an OutlookAttachmentSync stored attachment path (mapping UNC share onto local E:\ or D:\ when running on the file server). */
    public function readAttachmentBytes(array $attInfo): ?string
    {
        $rawPath = trim((string) ($attInfo['StoredPath'] ?? $attInfo['stored_path'] ?? ''));
        if ($rawPath === '') {
            return null;
        }
        $candidates = [$rawPath];
        if (preg_match('/^\\\\\\\\[^\\\\]+\\\\html\\\\(.+)$/i', $rawPath, $m)) {
            $candidates[] = 'E:\\html\\' . $m[1];
            $candidates[] = 'D:\\html\\' . $m[1];
        }
        foreach ($candidates as $path) {
            if (@is_file($path)) {
                $bytes = @file_get_contents($path);
                if ($bytes === false) {
                    continue;
                }
                if (! empty($attInfo['IsEncrypted']) || ! empty($attInfo['is_encrypted']) || str_starts_with($bytes, 'OAS1')) {
                    return $this->decryptBytes($bytes);
                }

                return $bytes;
            }
        }

        return null;
    }

    /**
     * Loads and decrypts recent email records from outlook_attachment_sync.email_records.
     *
     * @return list<array<string,mixed>>
     */
    public function fetchRecentEmails(int $limit = 120): array
    {
        $db = \Config\Database::connect();
        try {
            $rows = $db->query(
                'SELECT id, record_uid, internet_message_id, category_name, received_at, json_file_path, attachment_count, is_encrypted, encrypted_payload
                 FROM outlook_attachment_sync.email_records
                 ORDER BY received_at DESC, id DESC LIMIT ?',
                [max(1, min(500, $limit))]
            )->getResultArray();
        } catch (\Throwable $e) {
            log_message('warning', 'OutlookSyncReader fetchRecentEmails failed: ' . $e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            try {
                $rawJson = ! empty($r['is_encrypted'])
                    ? $this->decryptText((string) $r['encrypted_payload'])
                    : (string) $r['encrypted_payload'];
                $p = json_decode($rawJson, true);
                if (! is_array($p)) {
                    continue;
                }
                $sender   = $p['Sender'] ?? $p['sender'] ?? [];
                $body     = $p['Body'] ?? $p['body'] ?? [];
                $ids      = $p['Identifiers'] ?? $p['identifiers'] ?? [];
                $out[] = [
                    'db_id'               => (int) $r['id'],
                    'record_uid'          => (string) ($r['record_uid'] ?: ($p['RecordUid'] ?? $p['record_uid'] ?? '')),
                    'internet_message_id' => (string) ($r['internet_message_id'] ?: ($ids['InternetMessageId'] ?? $ids['internet_message_id'] ?? '')),
                    'category'            => (string) ($r['category_name'] ?: ($p['Category'] ?? $p['category'] ?? '')),
                    'received_at'         => (string) ($r['received_at'] ?: ($p['ReceivedAt'] ?? $p['received_at'] ?? '')),
                    'sender_name'         => (string) ($sender['Name'] ?? $sender['name'] ?? ''),
                    'sender_email'        => strtolower(trim((string) ($sender['Email'] ?? $sender['email'] ?? ''))),
                    'subject'             => (string) ($p['Subject'] ?? $p['subject'] ?? ''),
                    'body_text'           => (string) ($body['Text'] ?? $body['text'] ?? ''),
                    'body_html'           => (string) ($body['Html'] ?? $body['html'] ?? ''),
                    'attachments'         => (array) ($p['Attachments'] ?? $p['attachments'] ?? []),
                    'extracted'           => (array) ($ids['Extracted'] ?? $ids['extracted'] ?? []),
                ];
            } catch (\Throwable $e) {
                log_message('warning', 'OutlookSyncReader decrypt failed for ' . ($r['record_uid'] ?? '') . ': ' . $e->getMessage());
            }
        }

        return $out;
    }
}
