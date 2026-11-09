<?php

namespace App\Controllers;

/** Help & quick guide: what each page does, and the answers to the questions people ask most. */
class HelpController extends BaseController
{
    public function index(): string
    {
        $user = $this->auth->user();

        return $this->render('help/index', [
            'title'   => 'Help',
            'isAdmin' => strcasecmp((string) ($user['role'] ?? ''), 'admin') === 0,
        ]);
    }
}
