<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pull Footwear Test Results (Form # Lab VF 003): closures (eyelet, D-ring, hook, webbing) pull strength.
 * Header fields are columns; the Left / Right / Other test rows are kept as JSON in PullData.
 */
class CreatePullTestResults extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('tbl_dtr_pull_results')) {
            return;
        }

        $fields = ['ID' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true]];
        $text   = [
            'LabRefNo' => 80, 'ETRFNo' => 80, 'Supplier' => 160, 'SupplierCountry' => 80, 'MaterialDescription' => 255,
            'ModelNoStyle' => 160, 'Color' => 120, 'POLotNo' => 120, 'POQuantity' => 40, 'SendBy' => 120,
            'MaterialSource' => 40, 'TechSlot' => 20, 'ReceivedDate' => 20, 'TestedDate' => 20, 'FinishedDate' => 20,
            'Technician' => 120, 'Result' => 12, 'ReviewedBy' => 120, 'ApprovedBy' => 120,
        ];
        foreach ($text as $name => $len) {
            $fields[$name] = ['type' => 'VARCHAR', 'constraint' => $len, 'null' => true];
        }
        $fields['Comments'] = ['type' => 'TEXT', 'null' => true];
        $fields['PullData'] = ['type' => 'MEDIUMTEXT', 'null' => true, 'comment' => 'JSON: test rows of the Left / Right / Other tables'];

        $this->forge->addField($fields);
        $this->forge->addPrimaryKey('ID');
        $this->forge->addKey('LabRefNo');
        $this->forge->createTable('tbl_dtr_pull_results', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('tbl_dtr_pull_results', true);
    }
}
