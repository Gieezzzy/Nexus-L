<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 000009: admin-editable Excel/CSV reading rules per report type (tbl_machine_maps).
 * Seeds the rules that used to be hard-coded in DtrController::loadMachine so behaviour stays the same
 * until an admin changes them in the Report Designer ("Excel readers").
 */
class CreateMachineMaps extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('tbl_machine_maps')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'slug'       => ['type' => 'VARCHAR', 'constraint' => 40],
                'label'      => ['type' => 'VARCHAR', 'constraint' => 120],
                'folder'     => ['type' => 'VARCHAR', 'constraint' => 120],
                'sheet'      => ['type' => 'VARCHAR', 'constraint' => 60, 'default' => ''],
                'rules_json' => ['type' => 'LONGTEXT'],
                'enabled'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'sort_order' => ['type' => 'INT', 'default' => 0],
                'updated_by' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('slug');
            $this->forge->createTable('tbl_machine_maps', true, ['ENGINE' => 'InnoDB']);
        }

        $t = $this->db->table('tbl_machine_maps');
        if ($t->countAllResults() > 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $add = function (string $slug, string $label, string $folder, array $rules, int $order) use ($t, $now): void {
            $t->insert([
                'slug' => $slug, 'label' => $label, 'folder' => $folder, 'sheet' => '',
                'rules_json' => json_encode($rules, JSON_UNESCAPED_SLASHES), 'enabled' => 1, 'sort_order' => $order,
                'updated_by' => 'seed', 'updated_at' => $now,
            ]);
        };

        // Whole shoe bonding (U60): spot readings from the BONDING folder
        $add('bonding', 'U60 bonding spots', 'BONDING', [
            ['type' => 'column_range', 'col' => 3, 'rowFrom' => 12, 'rowTo' => 46, 'pattern' => 'UTFpoint{n}', 'stats' => 'UTF'],
            ['type' => 'column_range', 'col' => 4, 'rowFrom' => 12, 'rowTo' => 46, 'pattern' => 'FTOpoint{n}', 'stats' => 'FTO'],
            ['type' => 'column_range', 'col' => 5, 'rowFrom' => 12, 'rowTo' => 24, 'pattern' => 'FiTO{n}', 'stats' => 'FiTO'],
        ], 1);

        // Material tests (same specs as the old hard-coded list, per form)
        foreach (['fabric', 'leather', 'wst', 'fthl', 'hardware', 'label', 'sklfm', 'rubber', 'box', 'insole'] as $slug) {
            $thick = in_array($slug, ['rubber', 'fthl'], true);
            $one   = in_array($slug, ['rubber', 'fthl', 'wst'], true);
            $row   = static fn (int $lab, int $res, array $f) => ['type' => 'row_by_labref', 'labRow' => $lab, 'resultRow' => $res, 'startCol' => 3, 'fields' => $f, 'fallbackFirstCol' => true];

            $add($slug, 'Tensile - strength', 'MATERIALS\\TENSILE', [
                $row(7, $thick ? 11 : 9, $one ? ['Tensile1', 'Tensile2', 'Tensile3'] : ['TensileL1', 'TensileL2', 'TensileL3', 'TensileW1', 'TensileW2', 'TensileW3']),
                $row(7, $thick ? 12 : 10, $thick ? ['Elongation1', 'Elongation2', 'Elongation3']
                    : ($slug === 'wst' ? ['ElongationL1', 'ElongationL2', 'ElongationL3'] : ['ElongationL1', 'ElongationL2', 'ElongationL3', 'ElongationW1', 'ElongationW2', 'ElongationW3'])),
            ], 1);
            $add($slug, 'Trouser tear', 'MATERIALS\\TROUSER TEAR', [
                $row(7, 10, ['TrouserTearL1', 'TrouserTearL2', 'TrouserTearL3', 'TrouserTearW1', 'TrouserTearW2', 'TrouserTearW3']),
            ], 2);
            $add($slug, 'Stitch tear', 'MATERIALS\\STITCH TEAR', [
                $row(7, 11, $slug === 'wst' ? ['StitchTear1', 'StitchTear2', 'StitchTear3']
                    : ($slug === 'label' ? ['StitchTear_1', 'StitchTear_2', 'StitchTear_3'] : ['StitchTearL1', 'StitchTearL2', 'StitchTearL3', 'StitchTearW1', 'StitchTearW2', 'StitchTearW3'])),
            ], 3);
            $add($slug, 'Die C tear', 'MATERIALS\\DIE C TEAR', [$row(6, 10, ['DieC_1', 'DieC_2', 'DieC_3'])], 4);
            $add($slug, 'Edge crush', 'MATERIALS\\EDGE CRUSH', [$row(6, 10, ['EdgeCrushTest1', 'EdgeCrushTest2', 'EdgeCrushTest3'])], 5);
        }
    }

    public function down()
    {
        $this->forge->dropTable('tbl_machine_maps', true);
    }
}
