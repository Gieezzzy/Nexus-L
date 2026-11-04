<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Laboratory Documents: one register per "#" folder of \\192.168.32.130\qc lab files\#Lab Documents (first one: #DTP-Lab-100).
 *
 *   tbl_labdoc_registers  one row per register: its header (company, address, title), where its file lives, print settings
 *   tbl_labdoc_columns    the columns of a register (label, width, shown or hidden, order), so a column can be added or renamed
 *   tbl_labdoc_rows       the rows of a register; the cells are stored as JSON keyed by the column key
 *   tbl_labdoc_log        who saved what and when, and whether the file on the share was updated
 */
class CreateLabDocs extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('tbl_labdoc_registers')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'code'         => ['type' => 'VARCHAR', 'constraint' => 60],
                'menu_label'   => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
                'title'        => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'company_cn'   => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'company_en'   => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'address'      => ['type' => 'VARCHAR', 'constraint' => 500, 'default' => ''],
                'list_title'   => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'logo_left'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'logo_right'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'folder_name'  => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'file_path'    => ['type' => 'VARCHAR', 'constraint' => 700, 'default' => ''],
                'sheet_name'   => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => ''],
                'source_files' => ['type' => 'TEXT', 'null' => true],
                'margin_mm'    => ['type' => 'DECIMAL', 'constraint' => '4,1', 'default' => 6.4],
                'sort_order'   => ['type' => 'INT', 'default' => 0],
                'is_active'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'updated_by'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
                'file_status'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'file_synced_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('code');
            $this->forge->createTable('tbl_labdoc_registers', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4']);
        }
        if (! $this->db->tableExists('tbl_labdoc_columns')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'register_id' => ['type' => 'INT', 'unsigned' => true],
                'col_key'     => ['type' => 'VARCHAR', 'constraint' => 40],
                'label'       => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'width_pct'   => ['type' => 'DECIMAL', 'constraint' => '5,1', 'default' => 0],
                'align'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'left'],
                'is_visible'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'sort_order'  => ['type' => 'INT', 'default' => 0],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('register_id');
            $this->forge->createTable('tbl_labdoc_columns', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4']);
        }
        if (! $this->db->tableExists('tbl_labdoc_rows')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'register_id' => ['type' => 'INT', 'unsigned' => true],
                'sort_order'  => ['type' => 'INT', 'default' => 0],
                'cells_json'  => ['type' => 'MEDIUMTEXT', 'null' => true],
                'updated_by'  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['register_id', 'sort_order']);
            $this->forge->createTable('tbl_labdoc_rows', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4']);
        }
        if (! $this->db->tableExists('tbl_labdoc_log')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'register_id' => ['type' => 'INT', 'unsigned' => true],
                'action'      => ['type' => 'VARCHAR', 'constraint' => 40],
                'detail'      => ['type' => 'VARCHAR', 'constraint' => 500, 'default' => ''],
                'user_name'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('register_id');
            $this->forge->createTable('tbl_labdoc_log', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4']);
        }
    }

    public function down()
    {
        foreach (['tbl_labdoc_log', 'tbl_labdoc_rows', 'tbl_labdoc_columns', 'tbl_labdoc_registers'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
