<?php

namespace App\Libraries;

/**
 * The ERP agent: a small Windows program (NexusErpAgent.exe) on the PC where the ERP client is open. NEXUS-L sends it the reports to enter
 * in the ERP; the agent keeps them in a first-in first-out queue and tells NEXUS-L the result (erp-agent/callback).
 * Address, port and token are in Settings > ERP agent.
 */
class ErpAgent
{
    public static function config(): array
    {
        $t = SystemSettings::get('erp.agent_target', '43') === '45' ? '45' : '43';   // the PC chosen in Settings

        return [
            'enabled' => SystemSettings::get('erp.agent_enabled', '1') !== '0',
            'target'  => $t,
            'host'    => trim((string) SystemSettings::get("erp.agent{$t}_host", $t === '45' ? '192.168.32.45' : '192.168.32.43')),
            'port'    => (int) SystemSettings::get("erp.agent{$t}_port", '5057'),
            'token'   => (string) SystemSettings::get("erp.agent{$t}_token", ''),
        ];
    }

    /** the tokens of both ERP PCs: either agent may report a result */
    public static function tokens(): array
    {
        return array_values(array_filter([(string) SystemSettings::get('erp.agent43_token', ''), (string) SystemSettings::get('erp.agent45_token', '')], static fn ($t) => $t !== ''));
    }

    private static function call(string $method, string $path, ?array $body = null, int $timeout = 4): array
    {
        $c = self::config();
        if ($c['host'] === '' || $c['port'] <= 0) {
            return ['ok' => false, 'error' => 'The ERP agent address is not set (Settings > ERP agent).'];
        }
        $ch = curl_init("http://{$c['host']}:{$c['port']}{$path}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['X-Agent-Token: ' . $c['token'], 'Content-Type: application/json'],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['ok' => false, 'error' => "The ERP PC {$c['host']}:{$c['port']} cannot be reached ({$err})."];
        }
        $j = json_decode((string) $raw, true);
        if ($code === 401) {
            return ['ok' => false, 'error' => 'The ERP agent refused the token. Use the same token in Settings > ERP agent and in the agent.'];
        }

        return is_array($j) ? $j + ['ok' => $code < 300] : ['ok' => false, 'error' => "Unexpected answer from the ERP agent (HTTP {$code})."];
    }

    /** is the agent up: queue length, what it is working on, whether the ERP client is open there */
    public static function health(): array
    {
        $r = self::call('GET', '/api/health', null, 3);

        return $r;
    }

    /**
     * Hands reports to the agent. $tasks: [['id' => submission id, 'lab_ref_no' => ..., 'by' => name, 'payload' => [...]]].
     * Returns ['ok' => bool, 'accepted' => [id => ['position' => n, 'status' => ..]], 'error' => ..]
     */
    public static function submit(array $tasks): array
    {
        if (! self::config()['enabled']) {
            return ['ok' => false, 'error' => 'Sending to the ERP agent is switched off in Settings.'];
        }
        $r = self::call('POST', '/api/submit', ['tasks' => $tasks], 6);
        if (! empty($r['ok'])) {
            $map = [];
            foreach ((array) ($r['accepted'] ?? []) as $a) {
                $map[(int) $a['id']] = $a;
            }
            $r['accepted'] = $map;
        }

        return $r;
    }
}
