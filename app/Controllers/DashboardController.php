<?php

namespace App\Controllers;

use App\Models\AuthLogModel;
use App\Models\UserModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $logs = model(AuthLogModel::class);
        $uid  = $this->auth->id();

        $recent = $logs->where('UserID', $uid)->orderBy('LogID', 'DESC')->findAll(8);

        // Sign-in activity for the last 7 days (successful vs failed logins).
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $days[date('Y-m-d', strtotime("-{$i} days"))] = ['ok' => 0, 'bad' => 0];
        }
        $rows = $logs->select('DATE(CreatedAt) AS d, Success, COUNT(*) AS n')
            ->where('UserID', $uid)
            ->whereIn('Event', ['login', 'login_remember'])
            ->where('CreatedAt >=', date('Y-m-d 00:00:00', strtotime('-6 days')))
            ->groupBy('DATE(CreatedAt), Success')
            ->findAll();
        foreach ($rows as $r) {
            if (isset($days[$r['d']])) {
                $days[$r['d']][(int) $r['Success'] === 1 ? 'ok' : 'bad'] = (int) $r['n'];
            }
        }

        $user = model(UserModel::class)->find($uid);

        return $this->render('dashboard/index', [
            'title'     => 'Dashboard',
            'recent'    => $recent,
            'days'      => $days,
            'okTotal'   => array_sum(array_column($days, 'ok')),
            'badTotal'  => array_sum(array_column($days, 'bad')),
            'lastLogin' => $user['LastLoginAt'] ?? null,
            'lastIp'    => $user['LastLoginIP'] ?? null,
            'dept'      => $user['Department'] ?? null,
        ]);
    }
}
