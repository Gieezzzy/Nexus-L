<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * tbl_labdoc_revs: the version of a Laboratory Document as it was BEFORE each save (registers, test methods / specification sheets, workbooks).
 * snapshot_json is kept for good (a secure backup); log_file is the copy in the Logs folder of the share, which can be deleted from the page (log_deleted = 1).
 */
class CreateLabDocRevs extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tbl_labdoc_revs')) {
            return;
        }
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'register_id'   => ['type' => 'INT', 'unsigned' => true],
            'kind'          => ['type' => 'VARCHAR', 'constraint' => 12],
            'doc_key'       => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => ''],
            'category'      => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'rev_no'        => ['type' => 'INT', 'unsigned' => true],
            'snapshot_json' => ['type' => 'LONGTEXT', 'null' => true],
            'log_file'      => ['type' => 'VARCHAR', 'constraint' => 700, 'default' => ''],
            'log_deleted'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'deleted_by'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
            'saved_by'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'saved_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['register_id', 'kind', 'doc_key']);
        $this->forge->createTable('tbl_labdoc_revs', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4']);
    }

    public function down()
    {
        $this->forge->dropTable('tbl_labdoc_revs', true);
    }
}
