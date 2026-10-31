<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 000013: Unified Whole Shoe Physical & Monitoring Test Suite tables in dbnexus
 * Handles all categories (Bally Flex, Ross Flex, Martindale, Lace, Taber, Crocking)
 */
class CreateWholeShoeTestTables extends Migration
{
    public function up()
    {
        // 1. tbl_wst_tests - Main Test Sessions
        if (! $this->db->tableExists('tbl_wst_tests')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'category' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'bally-flex',
                    'comment'    => 'bally-flex, ross-flex, martindale-abrasion, lace-abrasion, taber-abrasion, crocking',
                ],
                'labreference' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'testdate' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 120,
                    'null'       => true,
                    'comment'    => 'Material or spec type',
                ],
                'machine_number' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                    'null'       => true,
                ],
                'samplecountdry' => [
                    'type'       => 'INT',
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'samplecountwet' => [
                    'type'       => 'INT',
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'samplecounthydro' => [
                    'type'       => 'INT',
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'target_cycles' => [
                    'type'       => 'INT',
                    'unsigned'   => true,
                    'default'    => 100000,
                ],
                'current_cycles' => [
                    'type'       => 'INT',
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'PENDING',
                    'comment'    => 'PENDING, RUNNING, COMPLETED, STOPPED',
                ],
                'is_running' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'operator' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'temperature' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'humidity' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'remarks' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('labreference');
            $this->forge->addKey('category');
            $this->forge->addKey('status');
            $this->forge->createTable('tbl_wst_tests', true, ['ENGINE' => 'InnoDB']);
        }

        // 2. tbl_wst_test_results - Checkpoint readings & cycle logs
        if (! $this->db->tableExists('tbl_wst_test_results')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'test_id' => [
                    'type'     => 'BIGINT',
                    'unsigned' => true,
                ],
                'labreference' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'group_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'dry',
                    'comment'    => 'dry, wet, hydro',
                ],
                'sample_num' => [
                    'type'       => 'INT',
                    'unsigned'   => true,
                    'default'    => 1,
                ],
                'cycle' => [
                    'type'       => 'INT',
                    'unsigned'   => true,
                ],
                'is_recorded' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'is_stopped' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'findings' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'result' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'PASS',
                    'comment'    => 'PASS, FAIL, TOLERABLE',
                ],
                'recorded_by' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'recorded_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['test_id', 'group_type', 'sample_num', 'cycle']);
            $this->forge->addKey('labreference');
            $this->forge->createTable('tbl_wst_test_results', true, ['ENGINE' => 'InnoDB']);
        }
    }

    public function down()
    {
        $this->forge->dropTable('tbl_wst_test_results', true);
        $this->forge->dropTable('tbl_wst_tests', true);
    }
}
