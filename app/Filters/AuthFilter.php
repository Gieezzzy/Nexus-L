<?php

namespace App\Filters;

use App\Libraries\Auth;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Blocks every protected route unless the user holds a valid session.
 * Usage in routes: ['filter' => 'auth'] or ['filter' => 'auth:admin'] for roles.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        if (! $auth->check()) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(401)->setJSON(['error' => 'Unauthenticated']);
            }
            // Remember where the user wanted to go (same-site paths only).
            session()->set('redirect_after_login', current_url());

            return redirect()->route('login')->withCookies()
                ->with('error', 'Please sign in to continue.');
        }

        $role = (string) ($auth->user()['role'] ?? '');
        if (! empty($arguments)) {
            $ok = false;
            foreach ($arguments as $a) {                       // auth:admin, or auth:manager,supervisor ... (any of the listed roles)
                if (strtolower((string) $a) === 'admin' ? \App\Libraries\Roles::isAdmin($role) : in_array(\App\Libraries\Roles::normalize((string) $a), \App\Libraries\Roles::parse($role), true)) {
                    $ok = true;
                    break;
                }
            }
            if (! $ok) {
                return $this->refuse($request, 'You are not authorized to access that page.');
            }
        }
        // a page of a module the user's roles give no access to (the matrix is under Settings > Users & roles)
        $module = \App\Libraries\Roles::moduleOfPath((string) $request->getUri()->getPath());
        if ($module !== '' && ! \App\Libraries\Roles::can($role, $module, 1)) {
            return $this->refuse($request, 'Your role does not have access to ' . (\App\Libraries\Roles::MODULES[$module] ?? 'that page') . '.');
        }

        return null;
    }

    private function refuse(RequestInterface $request, string $msg)
    {
        if ($request->isAJAX() || str_contains((string) $request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(403)->setJSON(['ok' => false, 'error' => $msg]);
        }

        return redirect()->route('dashboard')->with('error', $msg);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Authenticated pages must never be cached by the browser/proxies
        // (prevents viewing protected pages with the Back button after logout).
        $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');

        return $response;
    }
}
