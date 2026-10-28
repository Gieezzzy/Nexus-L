<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** The name printed after "Approved by" on the report: only the signatory with that name can approve it. */
class ApprovalRequiredSigner extends Migration
{
    public function up()
    {
        if (! in_array('required_name', $this->db->getFieldNames('tbl_dtr_report_approvals'), true)) {
            $this->forge->addColumn('tbl_dtr_report_approvals', ['required_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true]]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('tbl_dtr_report_approvals', 'required_name');
    }
}
