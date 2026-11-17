<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Indexes for the list / count queries that were the slowest in the MySQL slow log (material tests list, tracker counts). */
class AddSpeedIndexes extends Migration
{
    public function up()
    {
        foreach ([
            'ALTER TABLE tbl_material_tests ADD INDEX IF NOT EXISTS idx_mt_cat_date (category, testdate)',
            'ALTER TABLE tbl_dtr_bondingtracker ADD INDEX IF NOT EXISTS idx_bt_coll_shut (CollectionDate, Shutdown)',
            'ALTER TABLE tbl_dtr_wholeshoetracker ADD INDEX IF NOT EXISTS idx_wt_coll (CollectionDate)',
        ] as $q) {
            try {
                $this->db->query($q);
            } catch (\Throwable $e) {
                // table not there on this install
            }
        }
    }

    public function down()
    {
    }
}
