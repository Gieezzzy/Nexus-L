<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** tbl_labdoc_books keeps a copy of the Excel file itself, so it is not lost when the file on the share is deleted. */
class AddLabDocBookBlob extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('file_blob', 'tbl_labdoc_books')) {
            $this->forge->addColumn('tbl_labdoc_books', [
                'file_blob' => ['type' => 'LONGBLOB', 'null' => true],
                'file_size' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('tbl_labdoc_books', 'file_blob');
        $this->forge->dropColumn('tbl_labdoc_books', 'file_size');
    }
}
