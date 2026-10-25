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

        if (! empty($arguments) && ! in_array($auth->user()['role'], $arguments, true)) {
            return redirect()->route('dashboard')->with('error', 'You are not authorized to access that page.');
        }

        return null;
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
