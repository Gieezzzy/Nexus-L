<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRecordedRemarksToWholeShoeTests extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tbl_wst_test_results') && ! $this->db->fieldExists('recorded_remarks', 'tbl_wst_test_results')) {
            $this->forge->addColumn('tbl_wst_test_results', [
                'recorded_remarks' => [
                    'type'    => 'TEXT',
                    'null'    => true,
                    'after'   => 'findings',
                    'comment' => 'Concatenated remarks e.g. No defects;No defects;Minor crease',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('tbl_wst_test_results') && $this->db->fieldExists('recorded_remarks', 'tbl_wst_test_results')) {
            $this->forge->dropColumn('tbl_wst_test_results', 'recorded_remarks');
        }
    }
}
