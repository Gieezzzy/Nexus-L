<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 000008: admin-designed report layouts (point positions on the shoe sketch, hidden/added fields, extra
 * elements) and the values of admin-added custom fields.
 */
class CreateReportLayouts extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('tbl_report_layouts')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'slug'        => ['type' => 'VARCHAR', 'constraint' => 40],
                'variant'     => ['type' => 'VARCHAR', 'constraint' => 40],
                'layout_json' => ['type' => 'LONGTEXT'],
                'updated_by'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['slug', 'variant']);
            $this->forge->createTable('tbl_report_layouts', true, ['ENGINE' => 'InnoDB']);
        }

        if (! $this->db->tableExists('tbl_dtr_custom_values')) {
            $this->forge->addField([
                'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'slug'       => ['type' => 'VARCHAR', 'constraint' => 40],
                'record_id'  => ['type' => 'BIGINT', 'unsigned' => true],
                'field_key'  => ['type' => 'VARCHAR', 'constraint' => 60],
                'field_value' => ['type' => 'TEXT', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['slug', 'record_id', 'field_key']);
            $this->forge->createTable('tbl_dtr_custom_values', true, ['ENGINE' => 'InnoDB']);
        }
    }

    public function down()
    {
        $this->forge->dropTable('tbl_dtr_custom_values', true);
        $this->forge->dropTable('tbl_report_layouts', true);
    }
}
