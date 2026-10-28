<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * - tbl_app_settings: admin-editable key/value settings (OUTDATA root folder, ...).
 * - Bonding machine-file rules now follow the Access import (btnImportExcel): sheet "U60.xls", rows labelled M1..M45,
 *   UTF = column C, FTO = column D, FiTO = column E (max 13), equipment code in A3, tested date in C4, style in C7 (D7 for
 *   DTP-QC-002 / DTP-QC-048); CSV files are read by their "pointN" labels.
 */
class CreateAppSettingsAndBondingRules extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('tbl_app_settings')) {
            $this->forge->addField([
                'skey'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'svalue'     => ['type' => 'TEXT', 'null' => true],
                'updated_by' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('skey');
            $this->forge->createTable('tbl_app_settings', true, ['ENGINE' => 'InnoDB']);
        }

        $st = $this->db->table('tbl_app_settings');
        if (! $st->where('skey', 'outdata_root')->countAllResults()) {
            $st->insert(['skey' => 'outdata_root', 'svalue' => '\\\\192.168.32.45\\OUTDATA\\', 'updated_by' => 'seed', 'updated_at' => date('Y-m-d H:i:s')]);
        }

        if ($this->db->tableExists('tbl_machine_maps')) {
            $rules = [
                ['type' => 'cell', 'row' => 3, 'col' => 1, 'field' => 'EquipmentCode', 'afterColon' => true],
                ['type' => 'cell', 'row' => 4, 'col' => 3, 'field' => 'TestedDate'],
                ['type' => 'cell', 'row' => 7, 'col' => 3, 'field' => 'ModelStyle', 'altCol' => 4, 'altWhen' => ['field' => 'EquipmentCode', 'in' => ['DTP-QC-002', 'DTP-QC-048']]],
                ['type' => 'label_rows', 'labelCol' => 1, 'labelRegex' => '/^M(\\d+)$/i', 'cols' => [
                    ['col' => 3, 'pattern' => 'UTFpoint{n}', 'maxN' => 45, 'stats' => 'UTF'],
                    ['col' => 4, 'pattern' => 'FTOpoint{n}', 'maxN' => 45, 'stats' => 'FTO'],
                    ['col' => 5, 'pattern' => 'FiTO{n}', 'maxN' => 13, 'stats' => 'FiTO'],
                ]],
                ['type' => 'point_grid', 'reads' => [
                    ['rowOffset' => 2, 'pattern' => 'UTFpoint{n}', 'maxN' => 45, 'stats' => 'UTF'],
                    ['rowOffset' => 3, 'pattern' => 'FTOpoint{n}', 'maxN' => 45, 'stats' => 'FTO'],
                    ['rowOffset' => 4, 'pattern' => 'FiTO{n}', 'maxN' => 13, 'stats' => 'FiTO'],
                ]],
                ['type' => 'header_value', 'header' => 'Test date', 'field' => 'TestedDate'],
            ];
            $this->db->table('tbl_machine_maps')
                ->where(['slug' => 'bonding', 'updated_by' => 'seed'])
                ->update(['sheet' => 'U60.xls', 'rules_json' => json_encode($rules, JSON_UNESCAPED_SLASHES), 'updated_by' => 'seed2', 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('tbl_app_settings', true);
    }
}
