<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Excel workbooks of a "#" folder that are shown as cards and edited sheet by sheet (#DTP-Lab-102: Test Method Amendment 2026,
 * Test Method Accredited 2026, the Amendment Record Sheet).
 *
 *   tbl_labdoc_books      one row per workbook: label, the file on the share, the cell styles of the workbook as css
 *   tbl_labdoc_sheets     one row per sheet: the whole sheet (cells, styles, merged cells, widths, pictures) as JSON; what the page shows and edits
 *   tbl_labdoc_sheet_rev  the sheet as it was before each save
 */
class CreateLabDocBooks extends Migration
{
    public function up()
    {
        $opts = ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4'];
        if (! $this->db->tableExists('tbl_labdoc_books')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'register_id'    => ['type' => 'INT', 'unsigned' => true],
                'book_key'       => ['type' => 'VARCHAR', 'constraint' => 60],
                'label'          => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'file_name'      => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'file_path'      => ['type' => 'VARCHAR', 'constraint' => 700, 'default' => ''],
                'styles_json'    => ['type' => 'MEDIUMTEXT', 'null' => true],
                'sheet_count'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'file_mtime'     => ['type' => 'DATETIME', 'null' => true],
                'file_status'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'file_synced_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_by'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'updated_at'     => ['type' => 'DATETIME', 'null' => true],
                'sort_order'     => ['type' => 'INT', 'default' => 0],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['register_id', 'book_key']);
            $this->forge->createTable('tbl_labdoc_books', true, $opts);
        }
        if (! $this->db->tableExists('tbl_labdoc_sheets')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'book_id'     => ['type' => 'INT', 'unsigned' => true],
                'idx'         => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'name'        => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
                'model_json'  => ['type' => 'MEDIUMTEXT', 'null' => true],
                'revision_no' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'edited_by'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'edited_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['book_id', 'idx']);
            $this->forge->createTable('tbl_labdoc_sheets', true, $opts);
        }
        if (! $this->db->tableExists('tbl_labdoc_sheet_rev')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'sheet_id'   => ['type' => 'INT', 'unsigned' => true],
                'rev_no'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'model_json' => ['type' => 'MEDIUMTEXT', 'null' => true],
                'saved_by'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'saved_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('sheet_id');
            $this->forge->createTable('tbl_labdoc_sheet_rev', true, $opts);
        }
    }

    public function down()
    {
        foreach (['tbl_labdoc_sheet_rev', 'tbl_labdoc_sheets', 'tbl_labdoc_books'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
