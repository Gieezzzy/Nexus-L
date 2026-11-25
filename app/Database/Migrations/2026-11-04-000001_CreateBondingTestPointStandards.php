<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bonding test points standard for shoes (Laminar Peel Strength lab reference card).
 *  - tbl_bonding_test_std:    pass / tolerance / "points only" / fail limits, one row per style code.
 *  - tbl_bonding_test_points: spot size (14-35) with the three LINES values, per style group.
 */
class CreateBondingTestPointStandards extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('tbl_bonding_test_std')) {
            $this->forge->addField([
                'ID'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'StyleGroup'      => ['type' => 'VARCHAR', 'constraint' => 20, 'comment' => 'TODDLER | CHILDREN | ADULT (joins tbl_bonding_test_points.StyleGroup)'],
                'StyleCode'       => ['type' => 'VARCHAR', 'constraint' => 20, 'comment' => 'one style code per row'],
                'StyleLabel'      => ['type' => 'VARCHAR', 'constraint' => 80],
                'PassMin'         => ['type' => 'DECIMAL', 'constraint' => '4,2', 'comment' => 'value >= this = PASS'],
                'ToleMin'         => ['type' => 'DECIMAL', 'constraint' => '4,2', 'comment' => 'lower bound of TOLE (upper = PassMin - 0.01); = PassMin when no tolerance'],
                'PointsOnlyMin'   => ['type' => 'DECIMAL', 'constraint' => '4,2', 'null' => true, 'comment' => 'lower bound of the "points only" band'],
                'PointsOnlyMax'   => ['type' => 'DECIMAL', 'constraint' => '4,2', 'null' => true, 'comment' => 'upper bound of the "points only" band'],
                'MaxPointsAllowed'=> ['type' => 'TINYINT', 'unsigned' => true, 'default' => 3, 'comment' => 'more points than this in the band = FAILED'],
                'FailMax'         => ['type' => 'DECIMAL', 'constraint' => '4,2', 'comment' => 'value <= this = FAILED'],
                'Notes'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ]);
            $this->forge->addPrimaryKey('ID');
            $this->forge->addUniqueKey('StyleCode');
            $this->forge->addKey('StyleGroup');
            $this->forge->createTable('tbl_bonding_test_std', true, ['ENGINE' => 'InnoDB']);

            $young = ['PassMin' => 2.50, 'ToleMin' => 2.25, 'PointsOnlyMin' => 2.00, 'PointsOnlyMax' => 2.24, 'MaxPointsAllowed' => 3, 'FailMax' => 1.99, 'Notes' => null];
            $adult = ['PassMin' => 3.00, 'ToleMin' => 2.70, 'PointsOnlyMin' => 2.40, 'PointsOnlyMax' => 2.69, 'MaxPointsAllowed' => 3, 'FailMax' => 2.39, 'Notes' => null];
            $osna  = ['PassMin' => 2.50, 'ToleMin' => 2.50, 'PointsOnlyMin' => null, 'PointsOnlyMax' => null, 'MaxPointsAllowed' => 0, 'FailMax' => 2.49, 'Notes' => 'OSNABURG - 2.5 no tolerance'];

            $batch = [['StyleGroup' => 'TODDLER', 'StyleCode' => 'TODDLER', 'StyleLabel' => 'TODDLER (INFANT)'] + $young];
            foreach (['UY', 'JN', 'YT'] as $c) {
                $batch[] = ['StyleGroup' => 'CHILDREN', 'StyleCode' => $c, 'StyleLabel' => 'CHILDREN'] + $young;
            }
            foreach (['UA', 'MN', 'WM'] as $c) {
                $batch[] = ['StyleGroup' => 'ADULT', 'StyleCode' => $c, 'StyleLabel' => 'ADULT'] + $adult;
            }
            $batch[] = ['StyleGroup' => 'ADULT', 'StyleCode' => 'OSNABURG', 'StyleLabel' => 'ADULT'] + $osna;
            $this->db->table('tbl_bonding_test_std')->insertBatch($batch);
        }

        if (! $this->db->tableExists('tbl_bonding_test_points')) {
            $this->forge->addField([
                'ID'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'StyleGroup' => ['type' => 'VARCHAR', 'constraint' => 20],
                'Spot'       => ['type' => 'TINYINT', 'unsigned' => true],
                'Lines1'     => ['type' => 'TINYINT', 'unsigned' => true],
                'Lines2'     => ['type' => 'TINYINT', 'unsigned' => true],
                'Lines3'     => ['type' => 'TINYINT', 'unsigned' => true],
            ]);
            $this->forge->addPrimaryKey('ID');
            $this->forge->addUniqueKey(['StyleGroup', 'Spot']);
            $this->forge->createTable('tbl_bonding_test_points', true, ['ENGINE' => 'InnoDB']);

            $rows = [
                'TODDLER'  => [14 => [3, 7, 12], 15 => [3, 8, 12], 16 => [3, 8, 12], 17 => [3, 9, 13], 18 => [4, 9, 14], 19 => [4, 10, 15], 20 => [4, 11, 16]],
                'CHILDREN' => [21 => [4, 12, 17], 22 => [4, 13, 17], 23 => [5, 12, 17], 24 => [5, 12, 18], 25 => [5, 13, 19], 26 => [5, 14, 20]],
                'ADULT'    => [27 => [5, 14, 21], 28 => [6, 15, 22], 29 => [6, 16, 23], 30 => [6, 16, 24], 31 => [7, 17, 25],
                               32 => [7, 17, 26], 33 => [8, 18, 27], 34 => [8, 18, 27], 35 => [9, 19, 28]],
            ];
            $batch = [];
            foreach ($rows as $group => $spots) {
                foreach ($spots as $spot => $l) {
                    $batch[] = ['StyleGroup' => $group, 'Spot' => $spot, 'Lines1' => $l[0], 'Lines2' => $l[1], 'Lines3' => $l[2]];
                }
            }
            $this->db->table('tbl_bonding_test_points')->insertBatch($batch);
        }
    }

    public function down()
    {
        $this->forge->dropTable('tbl_bonding_test_points', true);
        $this->forge->dropTable('tbl_bonding_test_std', true);
    }
}
