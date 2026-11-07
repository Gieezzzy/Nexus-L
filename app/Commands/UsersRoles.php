<?php

namespace App\Commands;

use App\Libraries\LabDocRoles;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark users:roles                         lists every user with the roles
 * php spark users:roles <username> "Materials;Encoder"   gives the user those roles (several are separated by ;)
 *
 * Roles for the Laboratory Documents: Admin, Manager, Supervisor 1, Supervisor 2, Materials, Technician, Encoder.
 * Admin, Manager, Supervisors and Encoders may edit, upload, make folders and delete; the others view. The user signs in again to pick up the change.
 */
class UsersRoles extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'users:roles';
    protected $description = 'Lists or sets the roles (several allowed, separated by ;) of a user.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        if (! isset($params[0])) {
            foreach ($db->table('tbl_users')->select('Username, FullName, Role, IsActive')->orderBy('FullName')->get()->getResultArray() as $u) {
                CLI::write(str_pad($u['Username'], 18) . str_pad($u['FullName'], 28) . str_pad($u['Role'], 34) . (LabDocRoles::canEdit($u['Role']) ? 'can edit' : 'view only') . ($u['IsActive'] ? '' : '  (inactive)'));
            }
            CLI::write('Roles that can be given: ' . implode(', ', LabDocRoles::ROLES));

            return;
        }
        $user = $db->table('tbl_users')->where('Username', $params[0])->get()->getRowArray();
        if (! $user) {
            CLI::error('No user with the username ' . $params[0]);

            return;
        }
        $roles = LabDocRoles::parse($params[1] ?? '');
        if (! $roles) {
            CLI::error('Give the roles, for example "Materials;Encoder".');

            return;
        }
        foreach ($roles as $r) {
            if (\App\Libraries\Roles::normalize($r) === null && strtolower($r) !== 'user') {
                CLI::error('Unknown role "' . $r . '". Roles: ' . implode(', ', LabDocRoles::ROLES));

                return;
            }
        }
        $value = implode(';', $roles);
        if (strlen($value) > 120) {
            CLI::error('Too many roles.');

            return;
        }
        $db->table('tbl_users')->where('UserID', $user['UserID'])->update(['Role' => $value]);
        CLI::write($user['FullName'] . ' now has: ' . $value . ' (' . (LabDocRoles::canEdit($value) ? 'can edit' : 'view only') . ')', 'green');
    }
}
