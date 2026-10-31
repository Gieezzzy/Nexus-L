<?php

namespace App\Libraries;

/**
 * Bridge to the local AI service (ai-service/: Rasa router, LangGraph agent, Mem0 memory, Phoenix traces).
 *
 * `ai.mode` in .env:  off (default) | shadow
 *   shadow  the service gets a copy of every question, click event, admin reply and thumbs vote and answers on the side; its
 *           answer is stored for comparison and NEVER shown to the user. The production answer stays the Brain's.
 *
 * It never throws and never waits long: when the service is down the assistant works exactly as before.
 * Everything stays on this network (the service listens on 127.0.0.1).
 */
class AiBridge
{
    public static function mode(): string
    {
        $m = (string) SystemSettings::get('ai.mode', (string) env('ai.mode', 'shadow'));

        return in_array($m, ['shadow', 'live'], true) ? $m : 'off';
    }

    public static function isLive(): bool
    {
        return self::mode() === 'live';
    }

    public static function enabled(): bool
    {
        return self::mode() !== 'off';
    }

    public static function url(): string
    {
        return rtrim((string) SystemSettings::get('ai.url', (string) env('ai.url', 'http://127.0.0.1:8765')), '/');
    }

    public static function key(): string
    {
        return (string) SystemSettings::get('ai.key', (string) env('ai.key', ''));
    }

    public static function threshold(): float
    {
        return (float) SystemSettings::get('ai.confidence_threshold', 0.85);
    }

    public static function fallbackMessage(): string
    {
        return (string) SystemSettings::get(
            'ai.fallback_message',
            'Checking system status... I cannot verify this action against current system documentation with full confidence. Escalating directly to an administrator.'
        );
    }

    /** One session id per user and day: links clicks, questions and admin replies of that user. */
    public static function session(int $userId, ?string $day = null): string
    {
        return 'u' . $userId . '-' . ($day ?? date('Ymd'));
    }

    /** @return array<string,mixed>|null */
    public static function post(string $path, array $payload, int $timeoutMs = 1500): ?array
    {
        if (! self::enabled() || ! function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init(self::url() . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => $timeoutMs, CURLOPT_CONNECTTIMEOUT_MS => 400,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-AI-Key: ' . self::key()],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $out  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($out === false || $code >= 300) {
            return null;
        }
        $j = json_decode((string) $out, true);

        return is_array($j) ? $j : null;
    }

    /** Shadow answer for a question: the service logs it next to the production answer. */
    public static function shadowAsk(int $userId, string $message, string $page, string $role, ?int $liveKb, ?int $liveConf, string $prev = ''): void
    {
        self::post('/ask', [
            'session_id' => self::session($userId), 'user_id' => (string) $userId, 'message' => $message, 'prev_question' => $prev,
            'ui' => ['page' => $page, 'role' => $role === 'Admin' || $role === 'admin' ? 'admin' : 'user'],
            'live_kb_id' => $liveKb, 'live_conf' => $liveConf !== null ? $liveConf / 100 : null,
        ], 4000);
    }

    /** @param list<array<string,mixed>> $events */
    public static function events(int $userId, string $role, array $events): void
    {
        if (! $events) {
            return;
        }
        $isAdmin = strcasecmp($role, 'admin') === 0 ? 'admin' : 'user';
        self::post('/ingest/events', array_map(static fn ($e) => [
            'session_id' => self::session($userId), 'user_id' => (string) $userId, 'role' => $isAdmin, 'event_type' => (string) $e['event_type'],
            'page' => (string) $e['page'], 'target' => (string) $e['target'], 'detail' => (string) $e['detail'],
        ], $events));
    }

    public static function adminReply(int $userId, int $adminId, string $question, string $message): void
    {
        self::post('/ingest/admin', ['session_id' => self::session($userId), 'admin_id' => (string) $adminId, 'kind' => 'reply', 'message' => $message, 'question' => $question, 'trigger' => 'user_escalated']);
    }

    public static function feedback(int $userId, ?int $kbId, int $value, string $question): void
    {
        self::post('/ingest/feedback', ['session_id' => self::session($userId), 'kb_id' => $kbId, 'value' => $value > 0 ? 1 : -1, 'question' => $question]);
    }
}
