<?php

namespace App\Libraries;

use App\Controllers\DashboardController;
use App\Libraries\Chat\NexusStore;

/**
 * Pending Approval Bottleneck Monitor: the finished lab reports that still have no approved sign-off, how long each one has been waiting
 * (days since the day it was tested), and a reminder to the people who can sign (registered signatories + Supervisors / Managers).
 * The same figures feed the Notifier rule type "approval_waiting" and the Dashboard card.
 */
class SignoffWaiting
{
    /** who may see the monitor and send the reminder */
    public const VIEW_ROLES = ['Admin', 'Supervisor', 'Manager'];

    /** Setting keys: days a finished report may wait before it counts as late; minutes between two reminders */
    public const LATE_DAYS_KEY = 'approval.late_days';
    public const COOLDOWN_KEY  = 'approval.remind_cooldown_min';
    public const LAST_KEY      = 'approval.remind_last';
    public const START_KEY     = 'approval.start_date';

    /** Reports tested before this day are not waiting for anything: the sign-off started with the first approval request (Settings key approval.start_date overrides) */
    public static function startDate(): string
    {
        $v = trim((string) SystemSettings::get(self::START_KEY, ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return $v;
        }
        $first = \Config\Database::connect()->query('SELECT MIN(requested_at) m FROM tbl_dtr_report_approvals')->getRow('m');

        return $first ? substr((string) $first, 0, 10) : date('Y-m-d');
    }

    public static function canView(): bool
    {
        return (bool) array_intersect(self::VIEW_ROLES, Roles::parse((string) (service('auth')->user()['role'] ?? '')));
    }

    public static function lateDays(): int
    {
        return max(1, (int) SystemSettings::get(self::LATE_DAYS_KEY, 2));
    }

    /**
     * @return array{total:int,late:int,late_days:int,oldest:int,buckets:array<int,array{label:string,n:int}>,categories:array<int,array{name:string,n:int,late:int}>,rows:array<int,array<string,mixed>>}
     * Finished (status Complete) bonding / material reports tested since the sign-off started that have a result and no approved sign-off, oldest first. Trial refs are left out.
     */
    public static function waiting(int $limit = 200): array
    {
        $db = \Config\Database::connect();
        // the lab references that were approved (a few dozen rows): read once and compared here (the two tables use different collations)
        $approved = array_flip(array_map('strtoupper', array_column($db->query("SELECT DISTINCT lab_ref_no FROM tbl_dtr_report_approvals WHERE status = 'approved'")->getResultArray(), 'lab_ref_no')));
        $d  = DashboardController::LR_DATE;
        $rows = $db->query('SELECT lab_ref_no, form_slug, overall_result, DATEDIFF(CURDATE(), ' . $d . ') AS age, ' . $d . ' AS tested FROM ' . DashboardController::LR
            . " WHERE status LIKE 'Complete%' AND COALESCE(overall_result, '') <> '' AND form_slug NOT IN ('wholeshoe', 'pulltest') AND lab_ref_no NOT LIKE '%TRIAL%'
               AND " . $d . ' >= ? ORDER BY tested ASC, lab_ref_no', [self::startDate()])->getResultArray();
        $names   = ['bonding' => 'Whole Shoe Bonding', 'fabric' => 'Fabric', 'wst' => 'Webbing / Lace / Thread', 'hardware' => 'Hardware', 'fthl' => 'Foxing Tape & Heel Logo',
            'leather' => 'Leather', 'label' => 'Labels', 'sklfm' => 'Sockliner & Foam', 'rubber' => 'Rubber Outsole', 'box' => 'Carton Box', 'insole' => 'Insole Board'];
        $late    = self::lateDays();
        $buckets = [['label' => 'Today', 'n' => 0], ['label' => '1-2 days', 'n' => 0], ['label' => '3-6 days', 'n' => 0], ['label' => '7+ days', 'n' => 0]];
        $cats    = [];
        $out     = [];
        $nLate   = 0;
        $oldest  = 0;
        $total   = 0;
        foreach ($rows as $r) {
            if (isset($approved[strtoupper((string) $r['lab_ref_no'])])) {
                continue;
            }
            $age = max(0, (int) $r['age']);
            $total++;
            $oldest = max($oldest, $age);
            $buckets[$age === 0 ? 0 : ($age <= 2 ? 1 : ($age <= 6 ? 2 : 3))]['n']++;
            $cn = $names[$r['form_slug']] ?? ucfirst((string) $r['form_slug']);
            $cats[$cn] ??= ['name' => $cn, 'n' => 0, 'late' => 0];
            $cats[$cn]['n']++;
            if ($age >= $late) {
                $nLate++;
                $cats[$cn]['late']++;
            }
            if (count($out) < $limit) {
                $out[] = ['ref' => (string) $r['lab_ref_no'], 'category' => $cn, 'slug' => (string) $r['form_slug'], 'result' => strtoupper((string) $r['overall_result']), 'tested' => (string) $r['tested'], 'age' => $age];
            }
        }
        usort($cats, static fn ($a, $b) => $b['late'] <=> $a['late'] ?: $b['n'] <=> $a['n']);

        return ['total' => $total, 'late' => $nLate, 'late_days' => $late, 'since' => self::startDate(), 'oldest' => $oldest, 'buckets' => $buckets, 'categories' => array_values($cats), 'rows' => $out];
    }

    /** the people who can sign: users behind the active signatories, plus every Supervisor / Manager */
    public static function approvers(): array
    {
        $db  = \Config\Database::connect();
        $ids = [];
        foreach ($db->query('SELECT user_id FROM tbl_dtr_signatories WHERE is_active = 1 AND user_id IS NOT NULL AND user_id > 0')->getResultArray() as $r) {
            $ids[(int) $r['user_id']] = true;
        }
        foreach ($db->query('SELECT UserID, Role FROM tbl_users WHERE IsActive = 1')->getResultArray() as $u) {
            if (array_intersect(['Supervisor', 'Manager'], Roles::parse((string) $u['Role']))) {
                $ids[(int) $u['UserID']] = true;
            }
        }

        return array_keys($ids);
    }

    /** the reminder text: how many are waiting, how many late, the oldest ones */
    public static function message(array $w): string
    {
        $lines = ['📝 Reports waiting for sign-off', $w['total'] . ' finished report' . ($w['total'] === 1 ? '' : 's') . ' without an approval; ' . $w['late'] . ' waiting ' . $w['late_days'] . '+ days (oldest: ' . $w['oldest'] . ' days).'];
        $late = array_values(array_filter($w['rows'], static fn ($r) => $r['age'] >= $w['late_days']));
        foreach (array_slice($late, 0, 8) as $r) {
            $lines[] = '• ' . $r['ref'] . ' (' . $r['category'] . ', ' . $r['result'] . ', ' . $r['age'] . ' d)';
        }
        if (count($late) > 8) {
            $lines[] = '… and ' . (count($late) - 8) . ' more';
        }
        $lines[] = 'Open the report and show its QR to a signatory (Dashboard > Reports).';

        return implode("\n", $lines);
    }

    /**
     * Sends the reminder to the approvers (Inbox thread "Lab Alerts" + Synology DM). One reminder per cooldown (default 60 minutes), however many people press the button.
     * @return array{ok:bool,sent:int,message:string}
     */
    public static function remind(string $by): array
    {
        $cool = max(1, (int) SystemSettings::get(self::COOLDOWN_KEY, 60));
        $last = (int) SystemSettings::get(self::LAST_KEY, 0);
        if ($last > 0 && time() - $last < $cool * 60) {
            return ['ok' => false, 'sent' => 0, 'message' => 'A reminder was sent ' . max(1, (int) round((time() - $last) / 60)) . ' minute(s) ago; the next one can go out after ' . $cool . ' minutes.'];
        }
        $w = self::waiting();
        if ($w['late'] === 0) {
            return ['ok' => false, 'sent' => 0, 'message' => 'Nothing has waited ' . $w['late_days'] . '+ days.'];
        }
        $ids = self::approvers();
        if (! $ids) {
            return ['ok' => false, 'sent' => 0, 'message' => 'No signatory or Supervisor / Manager is registered.'];
        }
        $text = self::message($w) . "\n(reminder sent by " . $by . ')';
        $sent = LabAlerts::send($ids, 'Sign-off reminder', $text, 'Sign-off reminder (manual)');
        SystemSettings::set(self::LAST_KEY, (string) time());

        return ['ok' => true, 'sent' => $sent, 'message' => 'Reminder sent to ' . $sent . ' ' . ($sent === 1 ? 'person' : 'people') . '.'];
    }
}
