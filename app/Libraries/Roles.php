<?php

namespace App\Libraries;

/**
 * Roles and who may open what. A user can hold several roles in the one Role field of tbl_users, separated by ";" (for example
 * "Materials;Encoding"); the user gets the highest access any of the roles gives. Admin sees and does everything, including Settings,
 * the Brain console, the Report Designer and the management of users and roles.
 *
 * The seven roles: Admin, Bonding, Encoding, Materials, Manager, Supervisor, Technician.
 * A user whose Role is still the old plain "user" (nobody has assigned a role yet) keeps the access that every signed-in user had before,
 * except the admin pages, so introducing roles locks nobody out; assign a role and the matrix below applies.
 *
 * Access level per module: 0 = none (menu entry hidden, page refused), 1 = view, 2 = edit. The defaults are in MATRIX; an Admin can change any cell
 * under Settings > Users & roles (saved as JSON in the setting roles.matrix; a cell not saved there keeps its default).
 */
class Roles
{
    public const ROLES = ['Admin', 'Bonding', 'Encoding', 'Materials', 'Manager', 'Supervisor', 'Technician'];

    /** module key => label shown to people */
    public const MODULES = [
        'dashboard'   => 'Dashboard',
        'inbox'       => 'Inbox (team chat)',
        'interlink'   => 'InterLink report submission',
        'etrf'        => 'VF eTRF requests',
        'dtr'         => 'QC Lab Reports (DTR)',
        'materials'   => 'Material testing',
        'wholeshoe'   => 'Whole shoe testing',
        'bonding'     => 'Shoe bonding',
        'calendar'    => 'Calendar Monitoring',
        'insights'    => 'Quality Insights',
        'erpfinish'   => 'ERP Finish Monitoring',
        'testsummary' => 'Test results summary',
        'analytics'   => 'Analytics',
        'labdocs'     => 'Lab documents',
        'thirdparty'  => 'Third party tests',
        'approvals'   => 'Report approval (sign off)',
        'help'        => 'Help',
        'designer'    => 'Report Designer',
        'brain'       => 'AI Brain console',
        'settings'    => 'Settings and user management',
    ];

    /** modules only an Admin may open, whatever the matrix says */
    public const ADMIN_ONLY = ['settings', 'brain', 'designer'];

    /** role => module => level (0 none, 1 view, 2 edit). Admin is not listed: it is always 2 everywhere. */
    private const MATRIX = [
        'Manager' => ['dashboard' => 1, 'inbox' => 2, 'interlink' => 1, 'etrf' => 1, 'dtr' => 1, 'materials' => 1, 'wholeshoe' => 1, 'bonding' => 1, 'testsummary' => 1, 'analytics' => 1, 'labdocs' => 2, 'thirdparty' => 1, 'approvals' => 2, 'help' => 2, 'calendar' => 1, 'erpfinish' => 1],
        'Supervisor' => ['dashboard' => 1, 'inbox' => 2, 'interlink' => 2, 'etrf' => 2, 'dtr' => 2, 'materials' => 2, 'wholeshoe' => 2, 'bonding' => 2, 'testsummary' => 1, 'analytics' => 1, 'labdocs' => 2, 'thirdparty' => 1, 'approvals' => 2, 'help' => 2, 'calendar' => 1, 'erpfinish' => 2],
        'Encoding' => ['dashboard' => 1, 'inbox' => 2, 'interlink' => 2, 'etrf' => 2, 'dtr' => 2, 'materials' => 1, 'wholeshoe' => 1, 'bonding' => 1, 'testsummary' => 1, 'analytics' => 1, 'labdocs' => 2, 'thirdparty' => 2, 'help' => 2, 'calendar' => 1, 'erpfinish' => 2],
        'Materials' => ['dashboard' => 1, 'inbox' => 2, 'interlink' => 1, 'dtr' => 1, 'materials' => 2, 'testsummary' => 1, 'analytics' => 1, 'labdocs' => 1, 'help' => 2, 'calendar' => 1, 'erpfinish' => 1],
        'Bonding' => ['dashboard' => 1, 'inbox' => 2, 'interlink' => 1, 'dtr' => 1, 'bonding' => 2, 'testsummary' => 1, 'analytics' => 1, 'labdocs' => 1, 'help' => 2, 'calendar' => 1, 'erpfinish' => 1],
        'Technician' => ['dashboard' => 1, 'inbox' => 2, 'dtr' => 1, 'materials' => 2, 'wholeshoe' => 2, 'bonding' => 2, 'testsummary' => 1, 'labdocs' => 1, 'help' => 2, 'calendar' => 1],
    ];

    /** the first URL segment of a page => its module (a segment that is not here is not restricted) */
    public const SEGMENTS = [
        'dashboard' => 'dashboard', 'inbox' => 'inbox', 'interlink' => 'interlink', 'lab-monitoring' => 'erpfinish', 'calendar-monitoring' => 'calendar', 'calendar' => 'calendar', 'quality-insights' => 'insights', 'dtr' => 'dtr', 'materials' => 'materials', 'material-testing' => 'materials',
        'wholeshoe' => 'wholeshoe', 'whole-shoe-test' => 'wholeshoe', 'bonding' => 'bonding', 'test-summary' => 'testsummary', 'tests-summary' => 'testsummary', 'test-results' => 'testsummary',
        'analytics' => 'analytics', 'labdocs' => 'labdocs', 'thirdparty' => 'thirdparty', 'approval' => 'approvals', 'help' => 'help', 'designer' => 'designer', 'brain' => 'brain', 'settings' => 'settings',
    ];

    /** @return list<string> the roles of a Role value, as the normal names (Encoder -> Encoding, Supervisor 1 / 2 -> Supervisor); unknown words are dropped */
    public static function parse(string $role): array
    {
        $out = [];
        foreach (preg_split('/[;,|]+/', $role) ?: [] as $r) {
            $n = self::normalize($r);
            if ($n !== null && ! in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out;
    }

    public static function normalize(string $r): ?string
    {
        $k = preg_replace('/[^a-z0-9]/', '', strtolower($r)) ?? '';

        return match (true) {
            $k === 'admin' || $k === 'administrator' => 'Admin',
            $k === 'bonding' => 'Bonding',
            in_array($k, ['encoding', 'encoder', 'encoders'], true) => 'Encoding',
            $k === 'materials' || $k === 'material' => 'Materials',
            $k === 'manager' => 'Manager',
            in_array($k, ['supervisor', 'supervisor1', 'supervisor2'], true) => 'Supervisor',
            $k === 'technician' || $k === 'tech' => 'Technician',
            default => null,
        };
    }

    /** true when the Role value has no assigned role yet (the old plain "user") */
    public static function isLegacy(string $role): bool
    {
        return self::parse($role) === [];
    }

    public static function isAdmin(string $role): bool
    {
        return in_array('Admin', self::parse($role), true);
    }

    /** the matrix in force: the defaults with the Admin's saved changes on top */
    public static function matrix(): array
    {
        $m = self::MATRIX;
        try {
            $saved = json_decode((string) SystemSettings::get('roles.matrix', ''), true);
        } catch (\Throwable $e) {
            $saved = null;
        }
        if (is_array($saved)) {
            foreach ($saved as $role => $mods) {
                if (isset($m[$role]) && is_array($mods)) {
                    foreach ($mods as $mod => $lvl) {
                        if (isset(self::MODULES[$mod]) && ! in_array($mod, self::ADMIN_ONLY, true) && in_array((int) $lvl, [0, 1, 2], true)) {
                            $m[$role][$mod] = (int) $lvl;
                        }
                    }
                }
            }
        }

        return $m;
    }

    /** the defaults only (for the "reset" button of the matrix editor) */
    public static function defaults(): array
    {
        return self::MATRIX;
    }

    /** the level (0 none, 1 view, 2 edit) a Role value has on a module */
    public static function level(string $role, string $module): int
    {
        if (self::isAdmin($role)) {
            return 2;
        }
        if (in_array($module, self::ADMIN_ONLY, true)) {
            return 0;
        }
        $roles = self::parse($role);
        if (! $roles) {
            return in_array($module, ['designer', 'insights'], true) ? 0 : 2;                  // not assigned yet: what every signed-in user had before roles existed
        }
        $m   = self::matrix();
        $max = 0;
        foreach ($roles as $r) {
            $max = max($max, (int) ($m[$r][$module] ?? 0));
        }

        return $max;
    }

    public static function can(string $role, string $module, int $need = 1): bool
    {
        return self::level($role, $module) >= $need;
    }

    /** the module of a request path ('' when the page is not restricted) */
    public static function moduleOfPath(string $path): string
    {
        $seg = explode('/', trim($path, '/'));
        if (($seg[0] ?? '') === 'interlink' && ($seg[1] ?? '') === 'etrf') {
            return 'etrf';
        }

        return self::SEGMENTS[$seg[0] ?? ''] ?? '';
    }

    /** @return array<string,int> every module with the level of the Role value (for the "my access" table) */
    public static function levels(string $role): array
    {
        $out = [];
        foreach (self::MODULES as $k => $_) {
            $out[$k] = self::level($role, $k);
        }

        return $out;
    }
}
