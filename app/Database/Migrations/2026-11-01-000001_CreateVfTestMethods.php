<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration for VF Test Methods Catalog (DTP-Lab-101 VF Test Methods Updated 2025).
 * Stores official standards, parameters, and illustrated guides.
 */
class CreateVfTestMethods extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tbl_vf_test_methods')) {
            return;
        }

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code'           => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'title'          => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'category'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'General'],
            'file_name'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_path'      => ['type' => 'VARCHAR', 'constraint' => 500],
            'url_path'       => ['type' => 'VARCHAR', 'constraint' => 500],
            'file_size'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'file_ext'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pdf'],
            'is_illustrated' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'revision_date'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('code');
        $this->forge->addKey('category');
        $this->forge->addKey('is_illustrated');
        $this->forge->createTable('tbl_vf_test_methods', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('tbl_vf_test_methods', true);
    }
}
