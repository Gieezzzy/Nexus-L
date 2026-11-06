<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * - tbl_users.Role is long enough for several roles ("Supervisor 1;Supervisor 2;Encoder")
 * - tbl_labdoc_presence  who has a document open right now and where their cursor is (updated every few seconds; old rows are ignored)
 * - tbl_labdoc_folders   folders made in NEXUS-L (a folder with no file yet still shows)
 * - tbl_labdoc_files.deleted_at / deleted_by  a deleted file is hidden, its copy stays in the database
 */
class LabDocRolesPresence extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE tbl_users MODIFY Role VARCHAR(120) NOT NULL DEFAULT \'user\'');
        $opts = ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4'];
        if (! $this->db->tableExists('tbl_labdoc_presence')) {
            $this->forge->addField([
                'doc_key' => ['type' => 'VARCHAR', 'constraint' => 190],
                'user_id' => ['type' => 'INT', 'unsigned' => true],
                'name'    => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
                'roles'   => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
                'pos'     => ['type' => 'VARCHAR', 'constraint' => 300, 'default' => ''],
                'editing' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'seen_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addPrimaryKey(['doc_key', 'user_id']);
            $this->forge->addKey('seen_at');
            $this->forge->createTable('tbl_labdoc_presence', true, $opts);
        }
        if (! $this->db->tableExists('tbl_labdoc_folders')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'register_id' => ['type' => 'INT', 'unsigned' => true],
                'path'        => ['type' => 'VARCHAR', 'constraint' => 500],
                'created_by'  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['register_id', 'path']);
            $this->forge->createTable('tbl_labdoc_folders', true, $opts);
        }
        if (! $this->db->fieldExists('deleted_at', 'tbl_labdoc_files')) {
            $this->forge->addColumn('tbl_labdoc_files', [
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_by' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('tbl_labdoc_presence', true);
        $this->forge->dropTable('tbl_labdoc_folders', true);
        $this->forge->dropColumn('tbl_labdoc_files', 'deleted_at');
        $this->forge->dropColumn('tbl_labdoc_files', 'deleted_by');
    }
}
