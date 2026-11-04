<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * tbl_labdoc_files       every file of a "#" folder (PDF, pictures, Word, Excel, zip ...) with its folder, size, date and a preview picture
 * tbl_labdoc_file_parts  the file itself, in pieces of 400 KB, so a file deleted from the share is still in the database
 * src_key is set for the PDFs / Excel files that belong to a test method or document page (their name in writable/labdocs).
 */
class CreateLabDocFiles extends Migration
{
    public function up()
    {
        $opts = ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4'];
        if (! $this->db->tableExists('tbl_labdoc_files')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'register_id' => ['type' => 'INT', 'unsigned' => true],
                'folder'      => ['type' => 'VARCHAR', 'constraint' => 500, 'default' => ''],
                'name'        => ['type' => 'VARCHAR', 'constraint' => 255],
                'ext'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => ''],
                'file_size'   => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
                'file_mtime'  => ['type' => 'DATETIME', 'null' => true],
                'thumb'       => ['type' => 'MEDIUMBLOB', 'null' => true],
                'src_key'     => ['type' => 'VARCHAR', 'constraint' => 300, 'default' => ''],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['register_id', 'folder']);
            $this->forge->addKey(['register_id', 'src_key']);
            $this->forge->createTable('tbl_labdoc_files', true, $opts);
        }
        if (! $this->db->tableExists('tbl_labdoc_file_parts')) {
            $this->forge->addField([
                'id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'file_id' => ['type' => 'INT', 'unsigned' => true],
                'part'    => ['type' => 'INT', 'unsigned' => true],
                'data'    => ['type' => 'MEDIUMBLOB'],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['file_id', 'part']);
            $this->forge->createTable('tbl_labdoc_file_parts', true, $opts);
        }
    }

    public function down()
    {
        $this->forge->dropTable('tbl_labdoc_file_parts', true);
        $this->forge->dropTable('tbl_labdoc_files', true);
    }
}
