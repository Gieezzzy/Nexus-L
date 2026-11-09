<?php

namespace App\Controllers;

use App\Libraries\HelpContent;
use App\Libraries\Roles;

/** Help: the guide to every part of the system, showing the parts the signed-in role can open. The text is in App\Libraries\HelpContent. */
class HelpController extends BaseController
{
    public function index(): string
    {
        $user    = $this->auth->user();
        $role    = (string) ($user['role'] ?? '');
        $isAdmin = Roles::isAdmin($role);
        $open    = static fn (?string $module): bool => $module === null || Roles::can($role, $module, 1);

        $topics = [];
        $hidden = 0;
        $all    = HelpContent::topics();
        if ($methods = HelpContent::methodsTopic()) {       // the test methods, built from their table, placed before "For Admins"
            array_splice($all, (int) array_search('admin', array_column($all, 'id'), true), 0, [$methods]);
        }
        foreach ($all as $t) {
            if (($t['admin'] && ! $isAdmin) || ! $open($t['module'])) {
                $hidden++;
                continue;
            }
            $topics[] = $t;
        }
        $news = array_values(array_filter(HelpContent::news(), static fn ($n) => $open($n[4])));

        return $this->render('help/index', [
            'title'   => 'Help',
            'withCss' => ['help.css'],
            'withJs'  => ['help.js'],
            'isAdmin' => $isAdmin,
            'topics'  => $topics,
            'news'    => $news,
            'hidden'  => $hidden,
        ]);
    }
}
