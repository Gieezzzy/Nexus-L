<?php

namespace App\Libraries;

/**
 * Wire-compatible with the Android CryptoUtils / PHP helpers:
 * AES-256-GCM, key = PBKDF2-SHA256(conversationId, fixed salt, 1000 iters), payload "[ENC]b64(iv):b64(ciphertext||tag)".
 */
class RmsCrypto
{
    private const PREFIX = '[ENC]';
    private const SALT   = 'RMS_E2EE_SHARED_SALT_2026';
    public const PROFILE_KEY = 'RMS_USER_PROFILE_IMAGE_PATH_KEY';

    private static function key(string $conversationId): string
    {
        if (trim($conversationId) === '') {
            $conversationId = 'DEFAULT_RMS_CHAT_KEY';
        }

        return hash_pbkdf2('sha256', $conversationId, self::SALT, 1000, 32, true);
    }

    public static function encrypt(string $plain, string $conversationId): string
    {
        if ($plain === '') {
            return '';
        }
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plain, 'aes-256-gcm', self::key($conversationId), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ct === false) {
            throw new \RuntimeException('Message encryption failed.');
        }

        return self::PREFIX . base64_encode($iv) . ':' . base64_encode($ct . $tag);
    }

    /** Returns plaintext, or the original text if it is not encrypted / cannot be decrypted. */
    public static function decrypt(?string $text, string $conversationId): ?string
    {
        if ($text === null || ! str_starts_with($text, self::PREFIX)) {
            return $text;
        }
        $parts = explode(':', substr($text, strlen(self::PREFIX)), 2);
        if (count($parts) < 2) {
            return $text;
        }
        $iv  = base64_decode($parts[0], true);
        $raw = base64_decode($parts[1], true);
        if ($iv === false || $raw === false || strlen($iv) < 12 || strlen($raw) < 16) {
            return $text;
        }
        $plain = openssl_decrypt(substr($raw, 0, -16), 'aes-256-gcm', self::key($conversationId), OPENSSL_RAW_DATA, $iv, substr($raw, -16));

        return $plain === false ? $text : $plain;
    }

    /** users.ImagePath is an encrypted \\host\html\UserImage\... path; return it as a web URL (or null). */
    public static function profileUrl(?string $stored): ?string
    {
        if ($stored === null || trim($stored) === '') {
            return null;
        }
        $path = (string) self::decrypt(trim($stored), self::PROFILE_KEY);
        if (preg_match('~[\\\\/]html[\\\\/](.+)$~i', $path, $m)) {
            return rtrim(config(\Config\Rms::class)->mediaBaseUrl, '/') . '/' . str_replace('\\', '/', $m[1]);
        }

        return null;
    }
}
