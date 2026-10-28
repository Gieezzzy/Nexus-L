<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Approval links are temporary and single-use: `token` is the link a signatory opens (valid until `expires_at` and only
 * while the request is pending; every new request / renewal replaces it, and a decision kills it). `file_key` is the
 * permanent, unguessable key of the stored PDF, so the signed report stays reachable after the link is dead.
 */
class ApprovalLinksExpire extends Migration
{
    public function up()
    {
        $have = $this->db->getFieldNames('tbl_dtr_report_approvals');
        $add  = [];
        if (! in_array('file_key', $have, true)) {
            $add['file_key'] = ['type' => 'CHAR', 'constraint' => 40, 'null' => true, 'after' => 'token'];
        }
        if (! in_array('expires_at', $have, true)) {
            $add['expires_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'requested_at'];
        }
        if (! in_array('used_at', $have, true)) {
            $add['used_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'expires_at'];
        }
        if ($add) {
            $this->forge->addColumn('tbl_dtr_report_approvals', $add);
        }
        // rows made before this migration keep working: their token doubles as the file key
        $this->db->query("UPDATE tbl_dtr_report_approvals SET file_key = token WHERE file_key IS NULL");
    }

    public function down()
    {
        $this->forge->dropColumn('tbl_dtr_report_approvals', ['file_key', 'expires_at', 'used_at']);
    }
}
