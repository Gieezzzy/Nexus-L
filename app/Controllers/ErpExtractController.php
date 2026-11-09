<?php

namespace App\Controllers;

use App\Libraries\SystemSettings;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ERP Extract: starts, stops and watches the ERP Extract Agent (ERP_Extract_Agent) on the PC where the ERP client is open.
 * The agent fills the central dberp database itself; this page only controls it. The page is under settings/ so only admins reach it.
 * Address, port and token: Settings > ERP agent ("Extract agent").
 */
class ErpExtractController extends BaseController
{
    private function call(string $method, string $path, ?array $body = null, int $timeout = 5): array
    {
        $host  = trim((string) SystemSettings::get('erp.extract_host', ''));
        $port  = (int) SystemSettings::get('erp.extract_port', '5058');
        $token = (string) SystemSettings::get('erp.extract_token', '');
        if ($host === '' || $port <= 0) {
            return ['ok' => false, 'error' => 'Set the extract agent address in Settings > ERP agent.'];
        }
        $ch = curl_init("http://{$host}:{$port}{$path}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER     => ['X-Agent-Token: ' . $token, 'Content-Type: application/json'],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['ok' => false, 'error' => "The extract agent at {$host}:{$port} cannot be reached ({$err})."];
        }
        if ($code === 401) {
            return ['ok' => false, 'error' => 'The extract agent refused the token. Use the token printed by the agent (agent_config.json).'];
        }
        $j = json_decode((string) $raw, true);

        return is_array($j) ? $j + ['ok' => $code < 300] : ['ok' => false, 'error' => "Unexpected answer (HTTP {$code})."];
    }

    private function json(array $d): ResponseInterface
    {
        return $this->response->setJSON($d);
    }

    public function index(): string
    {
        return $this->render('settings/erp_extract', ['title' => 'ERP Extract']);
    }

    public function health(): ResponseInterface
    {
        return $this->json($this->call('GET', '/api/health', null, 3));
    }

    public function status(): ResponseInterface
    {
        return $this->json($this->call('GET', '/api/status'));
    }

    public function log(): ResponseInterface
    {
        return $this->json($this->call('GET', '/api/log?since=' . (int) $this->request->getGet('since')));
    }

    public function start(): ResponseInterface
    {
        $in   = (array) $this->request->getJSON(true);
        $date = static fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : null;
        $comp = array_values(array_intersect((array) ($in['targets'] ?? []), ['CURRENT', 'B-DA TIAN', 'P-DTP SUBIC', 'T-D2P SUBIC']));
        $body = [
            'targets'         => $comp ?: ['CURRENT'],
            'date_from'       => $date($in['date_from'] ?? ''),
            'date_to'         => $date($in['date_to'] ?? ''),
            'date_type'       => ($in['date_type'] ?? '') === 'crd' ? 'crd' : 'created',
            'start_row'       => max(1, (int) ($in['start_row'] ?? 1)),
            'end_row'         => ! empty($in['end_row']) ? (int) $in['end_row'] : null,
            'max_rows'        => ! empty($in['max_rows']) ? (int) $in['max_rows'] : null,
            'start_prod_code' => preg_match('/^[A-Za-z0-9._-]{1,40}$/', (string) ($in['start_prod_code'] ?? '')) ? $in['start_prod_code'] : null,
            'mode'            => 'background',
        ];
        log_message('notice', 'ERP extract start by ' . ($this->auth->user()['username'] ?? '?') . ': ' . json_encode($body));

        return $this->json($this->call('POST', '/api/start', $body, 8));
    }

    public function stop(): ResponseInterface
    {
        return $this->json($this->call('POST', '/api/stop', []));
    }
}
