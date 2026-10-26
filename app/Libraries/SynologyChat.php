<?php

namespace App\Libraries;

use Config\Synology;

/**
 * Sends direct messages through the Synology Chat incoming-webhook bot.
 * Rewritten for NEXUS-L (based on the payload format used in send-reply.php).
 */
class SynologyChat
{
    protected Synology $config;
    protected ?string $lastError = null;

    public function __construct(?Synology $config = null)
    {
        $this->config = $config ?? config(Synology::class);
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    protected function endpoint(): string
    {
        // Synology expects the token wrapped in double quotes ("%22").
        return $this->config->baseUrl
            . '?api=SYNO.Chat.External&method=chatbot&version=2&token=%22'
            . rawurlencode($this->config->botToken) . '%22';
    }

    /**
     * Send a text message to one or more Synology Chat user ids.
     *
     * @param int|list<int> $userIds
     */
    public function send(int|array $userIds, string $text, bool $withPrefix = false): bool
    {
        $this->lastError = null;
        $userIds         = array_values(array_filter(array_map('intval', (array) $userIds)));

        if ($userIds === [] || $this->config->botToken === '') {
            $this->lastError = 'Missing Synology user id or bot token.';

            return false;
        }

        if (! function_exists('curl_init')) {
            $this->lastError = 'PHP cURL extension is not loaded.';
            log_message('error', '[SynologyChat] ' . $this->lastError);

            return false;
        }

        $formattedText = ($withPrefix && !empty($this->config->botPrefix))
            ? trim($this->config->botPrefix) . ' ' . $text
            : $text;

        $payload = json_encode([
            'text'     => $formattedText,
            'user_ids' => $userIds,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->endpoint());
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['payload' => $payload]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->config->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => $this->config->verifySSL,
            CURLOPT_SSL_VERIFYHOST => $this->config->verifySSL ? 2 : 0,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($raw === false) {
            $this->lastError = 'cURL: ' . $err;
            log_message('error', '[SynologyChat] ' . $this->lastError);

            return false;
        }

        $json = json_decode((string) $raw, true);
        if ($code !== 200 || empty($json['success'])) {
            $this->lastError = 'API: HTTP ' . $code . ' ' . $raw;
            log_message('error', '[SynologyChat] ' . $this->lastError);

            return false;
        }

        return true;
    }
}
