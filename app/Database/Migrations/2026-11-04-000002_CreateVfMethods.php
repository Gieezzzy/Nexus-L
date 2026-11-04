<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * #DTP-Lab-101 VF Test Methods: the registers get a view type ("register" = a list on A4, "methods" = cards of test methods), and the
 * test methods, their illustrated PDFs, the documents of the folder and the edit history get their own tables.
 *
 *   tbl_vfm_methods      one row per test method (code, name, series, revision date) with its document as JSON blocks (current + original)
 *   tbl_vfm_illustrated  the Illustrated Test Methods PDFs, linked to a method by its code
 *   tbl_vfm_docs         the documents of the folder shown as links at the top (manual, parameters, list of methods, amendments ...)
 *   tbl_vfm_revisions    every saved edit of a method document
 */
class CreateVfMethods extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tbl_labdoc_registers')) {
            $f = $this->db->getFieldNames('tbl_labdoc_registers');
            if (! in_array('view_type', $f, true)) {
                $this->db->query("ALTER TABLE tbl_labdoc_registers ADD COLUMN view_type VARCHAR(20) NOT NULL DEFAULT 'register' AFTER code");
            }
            if (! in_array('meta_json', $f, true)) {
                $this->db->query('ALTER TABLE tbl_labdoc_registers ADD COLUMN meta_json TEXT NULL');
            }
        }
        $opts = ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4'];
        if (! $this->db->tableExists('tbl_vfm_methods')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'register_id'    => ['type' => 'INT', 'unsigned' => true],
                'code'           => ['type' => 'VARCHAR', 'constraint' => 20],
                'series'         => ['type' => 'CHAR', 'constraint' => 1, 'default' => ''],
                'title'          => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'rev_label'      => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => ''],
                'rev_date'       => ['type' => 'DATE', 'null' => true],
                'pages'          => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'file_name'      => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'cache_file'     => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'content_json'   => ['type' => 'MEDIUMTEXT', 'null' => true],
                'original_json'  => ['type' => 'MEDIUMTEXT', 'null' => true],
                'revision_no'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'edited_by'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'edited_at'      => ['type' => 'DATETIME', 'null' => true],
                'sort_order'     => ['type' => 'INT', 'default' => 0],
                'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['register_id', 'code']);
            $this->forge->createTable('tbl_vfm_methods', true, $opts);
        }
        if (! $this->db->tableExists('tbl_vfm_illustrated')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'register_id' => ['type' => 'INT', 'unsigned' => true],
                'code'        => ['type' => 'VARCHAR', 'constraint' => 20],
                'label'       => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'file_name'   => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'cache_file'  => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'file_size'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'file_mtime'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['register_id', 'code']);
            $this->forge->createTable('tbl_vfm_illustrated', true, $opts);
        }
        if (! $this->db->tableExists('tbl_vfm_docs')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'register_id' => ['type' => 'INT', 'unsigned' => true],
                'doc_key'     => ['type' => 'VARCHAR', 'constraint' => 30],
                'label'       => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'kind'        => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'pdf'],
                'file_name'   => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'cache_file'  => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'file_size'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'file_mtime'  => ['type' => 'DATETIME', 'null' => true],
                'sort_order'  => ['type' => 'INT', 'default' => 0],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['register_id', 'doc_key']);
            $this->forge->createTable('tbl_vfm_docs', true, $opts);
        }
        if (! $this->db->tableExists('tbl_vfm_revisions')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'method_id'  => ['type' => 'INT', 'unsigned' => true],
                'rev_no'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'content_json' => ['type' => 'MEDIUMTEXT', 'null' => true],
                'note'       => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'saved_by'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'saved_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('method_id');
            $this->forge->createTable('tbl_vfm_revisions', true, $opts);
        }
    }

    public function down()
    {
        foreach (['tbl_vfm_revisions', 'tbl_vfm_docs', 'tbl_vfm_illustrated', 'tbl_vfm_methods'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
