<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** The details of the Account Profile page that tbl_users did not have yet: phone, employee no., job title and the personal preferences (JSON). Safe to run twice. */
class AddUserProfileColumns extends Migration
{
    public function up()
    {
        $add = [
            'Phone'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'EmployeeNo' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'JobTitle'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'Prefs'      => ['type' => 'TEXT', 'null' => true],
        ];
        foreach ($add as $name => $def) {
            if (! $this->db->fieldExists($name, 'tbl_users')) {
                $this->forge->addColumn('tbl_users', [$name => $def]);
            }
        }
    }

    public function down()
    {
        foreach (['Phone', 'EmployeeNo', 'JobTitle', 'Prefs'] as $c) {
            if ($this->db->fieldExists($c, 'tbl_users')) {
                $this->forge->dropColumn('tbl_users', $c);
            }
        }
    }
}
