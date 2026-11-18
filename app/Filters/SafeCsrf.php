<?php

namespace App\Filters;

use CodeIgniter\Filters\CSRF;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

/**
 * The CSRF filter of the framework throws a SecurityException (a CRITICAL log entry with a stack trace) every time a page that was left open sends an old
 * token - 18,000 times in one day. This one answers the same request with a short "the page's token is old" message that carries the CURRENT token, so
 * the page (app.js) takes it and repeats the request once; a form post gets a plain message. Nothing is written to the log.
 */
class SafeCsrf extends CSRF
{
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            return parent::before($request, $arguments);
        } catch (SecurityException $e) {
            $fresh = csrf_hash();
            $ajax  = $request->isAJAX() || str_contains((string) $request->getHeaderLine('Accept'), 'json') || str_contains((string) $request->getHeaderLine('Content-Type'), 'json');
            $res   = service('response')->setStatusCode(419, 'Page expired');
            if ($ajax) {
                return $res->setJSON(['ok' => false, 'csrf_expired' => true, 'csrf' => $fresh,
                    'message' => 'This page was open for too long. Please try again (the page was refreshed in the background).']);
            }

            return $res->setContentType('text/html')->setBody('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Page expired</title>'
                . '<body style="font-family:system-ui,sans-serif;max-width:480px;margin:12vh auto;padding:0 16px;color:#0f172a"><h2>This page was open for too long</h2>'
                . '<p>Nothing was saved. Go back, reload the page and send it again.</p><p><a href="javascript:history.back()">Go back</a></p></body>');
        }
    }
}
