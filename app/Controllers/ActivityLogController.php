<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;

/**
 * GET activity-log (Admin): every recorded action, newest first, filtered by person, module, action, text and date.
 * GET activity-log?export=1 : the same rows as a CSV file.
 */
class ActivityLogController extends BaseController
{
    public function index()
    {
        if (! \App\Libraries\Roles::isAdmin((string) ($this->auth->user()['role'] ?? ''))) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Only an Admin can open the activity log.');
        }
        ActivityLog::ensureTable();
        $db = \Config\Database::connect();
        $f  = [
            'user'   => trim((string) $this->request->getGet('user')),
            'module' => trim((string) $this->request->getGet('module')),
            'action' => trim((string) $this->request->getGet('action')),
            'q'      => trim((string) $this->request->getGet('q')),
            'from'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->request->getGet('from')) ? (string) $this->request->getGet('from') : date('Y-m-d', strtotime('-7 days')),
            'to'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->request->getGet('to')) ? (string) $this->request->getGet('to') : date('Y-m-d'),
            'all'    => (bool) $this->request->getGet('all'),
        ];
        $qb = $db->table('tbl_activity_log')->where('created_at >=', $f['from'] . ' 00:00:00')->where('created_at <=', $f['to'] . ' 23:59:59');
        if (! $f['all']) {
            $qb->where('action !=', 'request');   // the plain request lines only when asked for
        }
        foreach (['user' => 'user_name', 'module' => 'module', 'action' => 'action'] as $k => $col) {
            if ($f[$k] !== '') {
                $qb->where($col, $f[$k]);
            }
        }
        if ($f['q'] !== '') {
            $qb->groupStart()->like('target', $f['q'])->orLike('detail', $f['q'])->orLike('path', $f['q'])->groupEnd();
        }
        $rows = $qb->orderBy('id', 'DESC')->get($this->request->getGet('export') ? 20000 : 1000)->getResultArray();

        if ($this->request->getGet('export')) {
            $out = fopen('php://temp', 'w+');
            fputcsv($out, ['When', 'Who', 'Role', 'Module', 'Action', 'Target', 'Detail', 'Method', 'Path', 'Status', 'IP']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['created_at'], $r['user_name'], $r['user_role'], $r['module'], $r['action'], $r['target'], $r['detail'], $r['method'], $r['path'], $r['status'], $r['ip']]);
            }
            rewind($out);

            return $this->response->setHeader('Content-Type', 'text/csv; charset=utf-8')
                ->setHeader('Content-Disposition', 'attachment; filename="NEXUS-L activity ' . $f['from'] . ' to ' . $f['to'] . '.csv"')
                ->setBody("\xEF\xBB\xBF" . stream_get_contents($out));
        }

        $opts = static fn (string $col) => array_column($db->query("SELECT DISTINCT {$col} AS v FROM tbl_activity_log WHERE {$col} <> '' ORDER BY v LIMIT 300")->getResultArray(), 'v');

        return $this->render('activity/index', [
            'title' => 'Activity log', 'rows' => $rows, 'f' => $f,
            'users' => $opts('user_name'), 'modules' => $opts('module'), 'actions' => $opts('action'),
        ]);
    }
}
