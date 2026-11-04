<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** The Excel file of a workbook kept in the database in pieces of 400 KB (one statement is limited to 1 MB by the database server). */
class CreateLabDocBookParts extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tbl_labdoc_book_parts')) {
            return;
        }
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'book_id' => ['type' => 'INT', 'unsigned' => true],
            'part'    => ['type' => 'INT', 'unsigned' => true],
            'data'    => ['type' => 'MEDIUMBLOB'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['book_id', 'part']);
        $this->forge->createTable('tbl_labdoc_book_parts', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4']);
    }

    public function down()
    {
        $this->forge->dropTable('tbl_labdoc_book_parts', true);
    }
}
