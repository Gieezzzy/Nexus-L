<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRecordedCyclesToWholeShoeTests extends Migration
{
    public function up()
    {
        // 1. Add recorded_cycles to tbl_wst_tests
        if ($this->db->tableExists('tbl_wst_tests') && ! $this->db->fieldExists('recorded_cycles', 'tbl_wst_tests')) {
            $this->forge->addColumn('tbl_wst_tests', [
                'recorded_cycles' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'current_cycles',
                    'comment'    => 'Concatenated cycles e.g. 5000;10000;15000',
                ],
            ]);
        }

        // 2. Add recorded_cycles to tbl_wst_test_results
        if ($this->db->tableExists('tbl_wst_test_results') && ! $this->db->fieldExists('recorded_cycles', 'tbl_wst_test_results')) {
            $this->forge->addColumn('tbl_wst_test_results', [
                'recorded_cycles' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'cycle',
                    'comment'    => 'Concatenated cycles for this specimen e.g. 5000;10000;15000',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('tbl_wst_tests') && $this->db->fieldExists('recorded_cycles', 'tbl_wst_tests')) {
            $this->forge->dropColumn('tbl_wst_tests', 'recorded_cycles');
        }

        if ($this->db->tableExists('tbl_wst_test_results') && $this->db->fieldExists('recorded_cycles', 'tbl_wst_test_results')) {
            $this->forge->dropColumn('tbl_wst_test_results', 'recorded_cycles');
        }
    }
}
