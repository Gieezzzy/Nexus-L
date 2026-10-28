<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Report sign-off before InterLink:
 * - tbl_dtr_signatories: managers / supervisors registered by an admin as report signatories, with their signature.
 * - tbl_dtr_report_approvals: one approval request per report (Lab Ref). The QR code on the Upload Form links to
 *   approve/<token>; a signatory signs in there, checks the report and approves (signature + date stamp) or rejects.
 */
class CreateReportApprovals extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('tbl_dtr_signatories')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'       => ['type' => 'INT', 'unsigned' => true, 'comment' => 'tbl_users.UserID (signs in with this account)'],
                'full_name'     => ['type' => 'VARCHAR', 'constraint' => 120],
                'title'         => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'QA Laboratory Supervisor'],
                'signature_png' => ['type' => 'MEDIUMTEXT', 'null' => true, 'comment' => 'data:image/png;base64,...'],
                'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'    => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('user_id');
            $this->forge->createTable('tbl_dtr_signatories', true, ['ENGINE' => 'InnoDB']);
        }

        if (! $this->db->tableExists('tbl_dtr_report_approvals')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'token'            => ['type' => 'CHAR', 'constraint' => 40],
                'lab_ref_no'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'application_no'   => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'form_slug'        => ['type' => 'VARCHAR', 'constraint' => 30],
                'source_id'        => ['type' => 'INT', 'unsigned' => true],
                'status'           => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'pending', 'comment' => 'pending / approved / rejected'],
                'requested_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'requested_name'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'requested_at'     => ['type' => 'DATETIME', 'null' => true],
                'signatory_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'decided_name'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'decided_title'    => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'decided_at'       => ['type' => 'DATETIME', 'null' => true],
                'signature_png'    => ['type' => 'MEDIUMTEXT', 'null' => true, 'comment' => 'the signature as signed'],
                'remarks'          => ['type' => 'TEXT', 'null' => true],
                'seen_by_requester' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('token');
            $this->forge->addKey('lab_ref_no');
            $this->forge->addKey(['status', 'updated_at']);
            $this->forge->createTable('tbl_dtr_report_approvals', true, ['ENGINE' => 'InnoDB']);
        }
    }

    public function down()
    {
        $this->forge->dropTable('tbl_dtr_report_approvals', true);
        $this->forge->dropTable('tbl_dtr_signatories', true);
    }
}
