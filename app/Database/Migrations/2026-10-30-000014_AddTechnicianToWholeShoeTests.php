<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTechnicianToWholeShoeTests extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tbl_wst_tests') && ! $this->db->fieldExists('technician', 'tbl_wst_tests')) {
            $this->forge->addColumn('tbl_wst_tests', [
                'technician' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'operator',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('tbl_wst_tests') && $this->db->fieldExists('technician', 'tbl_wst_tests')) {
            $this->forge->dropColumn('tbl_wst_tests', 'technician');
        }
    }
}
