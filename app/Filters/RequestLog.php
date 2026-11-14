<?php

namespace App\Filters;

use App\Libraries\ActivityLog;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Every change a signed-in person makes (POST / PUT / PATCH / DELETE) gets one line in tbl_activity_log: who, which page / action, the
 * answer (status) and when. Frequent background calls (presence, typing, live updates, machine agents) are left out so the log stays readable.
 * Actions that write their own detailed line (file operations, warehouse boxes) are recorded twice on purpose: the detail and the request.
 */
class RequestLog implements FilterInterface
{
    private const SKIP = [
        'labdocs/presence', 'approve/presence/', 'warehouse-tracker/rt', 'warehouse-tracker/avatar', 'inbox/typing', 'inbox/triggered', 'inbox/token', 'push/', 'bot/', 'brain/',
        'brain-trainer/', 'erp-agent/', 'notifier/', 'mail-relay/', 'photo/', 'interlink/service/', 'interlink/fetch-live', 'interlink/pull-grid', 'dtr/sheet-pdf',
        'dtr/approval/pdf-pages', 'login', 'logout', 'guest-login', 'forgot-password',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        try {
            $method = strtoupper($request->getMethod());
            if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                return null;
            }
            $path = trim((string) $request->getUri()->getPath(), '/');
            $path = (string) preg_replace('~^.*?index\.php/~', '', $path);
            $base = trim((string) parse_url((string) config('App')->baseURL, PHP_URL_PATH), '/');
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = ltrim(substr($path, strlen($base)), '/');
            }
            foreach (self::SKIP as $s) {
                if ($path === rtrim($s, '/') || str_starts_with($path, $s)) {
                    return null;
                }
            }
            $user = service('auth')->user();
            if (! $user) {
                return null;
            }
            $module = \App\Libraries\Roles::moduleOfPath($path) ?: explode('/', $path)[0];
            ActivityLog::record($module, 'request', $path, '', ['method' => $method, 'path' => $path, 'status' => $response->getStatusCode()]);
        } catch (\Throwable $e) {
            log_message('error', '[RequestLog] ' . $e->getMessage());
        }

        return null;
    }
}
