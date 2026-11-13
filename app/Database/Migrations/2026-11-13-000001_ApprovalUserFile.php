<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** An approved report may have its attached file changed by the user: the chosen file is kept in NEXUS-L and is the one sent (see App\Libraries\ApprovalFiles). */
class ApprovalUserFile extends Migration
{
    public function up()
    {
        $have = $this->db->getFieldNames('tbl_dtr_report_approvals');
        $add  = [];
        foreach (['user_file' => 90, 'user_file_name' => 160, 'user_file_by' => 120] as $col => $len) {
            if (! in_array($col, $have, true)) {
                $add[$col] = ['type' => 'VARCHAR', 'constraint' => $len, 'null' => true];
            }
        }
        if (! in_array('user_file_at', $have, true)) {
            $add['user_file_at'] = ['type' => 'DATETIME', 'null' => true];
        }
        if ($add) {
            $this->forge->addColumn('tbl_dtr_report_approvals', $add);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('tbl_dtr_report_approvals', ['user_file', 'user_file_name', 'user_file_by', 'user_file_at']);
    }
}
