<?php

namespace App\Libraries;

/**
 * Who may do what in the Laboratory Documents. A user can hold several roles in the one Role field, separated by ";"
 * (for example "Materials;Encoder"): the user may do what any of the roles may.
 *
 *   Admin, Manager, Supervisor 1, Supervisor 2, Encoder   view + edit, upload, new folder, delete
 *   Materials, Technician, user (and anything else)       view only
 *
 * Everything in the Laboratory Documents asks this class, so a change of rule is a change here.
 */
class LabDocRoles
{
    /** the roles that can be given (the names shown to people) */
    public const ROLES = ['Admin', 'Bonding', 'Encoding', 'Materials', 'Manager', 'Supervisor', 'Technician'];   // the same seven as Roles::ROLES (the old Encoder / Supervisor 1 / 2 still count)

    private const EDIT = ['admin', 'manager', 'supervisor', 'supervisor1', 'supervisor2', 'encoder', 'encoders', 'encoding'];

    /** @return list<string> the roles of a Role value, as normal names where they are known */
    public static function parse(string $role): array
    {
        $out = [];
        foreach (preg_split('/[;,|]+/', $role) ?: [] as $r) {
            $r = trim($r);
            if ($r !== '') {
                $out[] = $r;
            }
        }

        return $out;
    }

    private static function key(string $r): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($r)) ?? '';
    }

    /** may edit, upload, make folders and delete */
    public static function canEdit(string $role): bool
    {
        foreach (self::parse($role) as $r) {
            if (in_array(self::key($r), self::EDIT, true)) {
                return true;
            }
        }

        return false;
    }

    public static function isAdmin(string $role): bool
    {
        foreach (self::parse($role) as $r) {
            if (self::key($r) === 'admin') {
                return true;
            }
        }

        return false;
    }

    /** a short label for the people dialog: the roles as stored */
    public static function label(string $role): string
    {
        return implode(' · ', self::parse($role)) ?: 'User';
    }
}
