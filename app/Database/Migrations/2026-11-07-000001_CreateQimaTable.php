<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * QIMA monthly audit/inspection booking list (source: QIMA.xlsx).
 * PhotoFile = image file name saved in D:\BACK UP\OUTDATA\QIMA (named after the MTL#).
 */
class CreateQimaTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tbl_QIMA')) {
            return;
        }
        $this->forge->addField([
            'ID'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'RowNo'          => ['type' => 'INT', 'null' => true, 'comment' => 'No. in the sheet'],
            'ReportMonth'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'comment' => 'e.g. NOVEMBER 2026'],
            'PhotoFile'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'comment' => 'image file name (MTL#.ext)'],
            'ProductionCode' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'ProductionLine' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'PONo'           => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'Style'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'Colorway'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'MTL'            => ['type' => 'VARCHAR', 'constraint' => 30],
            'CRD'            => ['type' => 'DATE', 'null' => true],
            'Pairs'          => ['type' => 'INT', 'null' => true],
            'Cnts'           => ['type' => 'INT', 'null' => true],
            'Destination'    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'RiskLevel'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'SamplingPlan'   => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'SampleSize'     => ['type' => 'INT', 'null' => true],
            'Mold'           => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'BookingDate'    => ['type' => 'DATE', 'null' => true],
            'QimaAuditDate'  => ['type' => 'DATE', 'null' => true],
            'Inspector'      => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'QimaReference'  => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'Remarks'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('ID');
        $this->forge->addUniqueKey(['MTL', 'ProductionCode']);
        $this->forge->addKey('QimaAuditDate');
        $this->forge->createTable('tbl_QIMA', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('tbl_QIMA', true);
    }
}
