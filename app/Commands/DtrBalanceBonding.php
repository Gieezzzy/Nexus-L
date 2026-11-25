<?php

namespace App\Commands;

use App\Libraries\BondingMirror;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark dtr:balance-bonding           shows how many Lab Refs are missing (nothing is changed)
 * php spark dtr:balance-bonding --apply   adds them: tracker rows without a tbl_dtr_bonding_info record, and bonding_info records without a tbl_dtr_unified_labrefs row
 */
class DtrBalanceBonding extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'dtr:balance-bonding';
    protected $description = 'Makes tbl_dtr_BondingTracker, tbl_dtr_bonding_info and tbl_dtr_unified_labrefs hold the same Lab Refs.';

    public function run(array $params)
    {
        $db  = \Config\Database::connect();
        $col = 'COLLATE utf8mb4_general_ci';
        $noInfo = (int) $db->query("SELECT COUNT(*) c FROM tbl_dtr_BondingTracker t WHERE t.LabRefNo IS NOT NULL AND t.LabRefNo <> '' AND t.Shutdown = 0 AND NOT EXISTS (SELECT 1 FROM tbl_dtr_bonding_info b WHERE b.LabRefNo {$col} = t.LabRefNo {$col})")->getRow()->c;
        $noUni  = (int) $db->query("SELECT COUNT(*) c FROM tbl_dtr_bonding_info b WHERE b.LabRefNo IS NOT NULL AND b.LabRefNo <> '' AND NOT EXISTS (SELECT 1 FROM tbl_dtr_unified_labrefs u WHERE u.lab_ref_no {$col} = b.LabRefNo {$col})")->getRow()->c;
        CLI::write("Tracker Lab Refs missing in tbl_dtr_bonding_info: {$noInfo}");
        CLI::write("bonding_info records missing in tbl_dtr_unified_labrefs: {$noUni}");
        if (CLI::getOption('apply') === null && ! in_array('--apply', $params, true)) {
            CLI::write('Run with --apply to add them.', 'yellow');

            return;
        }
        $r = BondingMirror::backfill($db);
        CLI::write("Added {$r['info_added']} bonding_info record(s) and {$r['unified_added']} unified Lab Ref row(s).", 'green');
    }
}
