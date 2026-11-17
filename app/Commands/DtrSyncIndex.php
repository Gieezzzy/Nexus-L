<?php

namespace App\Commands;

use App\Controllers\DtrController;
use App\Libraries\DtrLabrefIndex;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark dtr:sync-index          adds every report (any material form, bonding ...) that has no row yet in tbl_dtr_unified_labrefs
 * php spark dtr:sync-index --dry    only counts them
 * php spark dtr:sync-index --materials  only the material report tables (fabric, leather, hardware ...): adds the ones with no index row yet
 * php spark dtr:sync-index --refresh [--slugs=fabric,box]  also re-reads the index row of every material report (after rows were loaded or changed outside the web save)
 */
class DtrSyncIndex extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'dtr:sync-index';
    protected $description = 'Brings tbl_dtr_unified_labrefs up to date with the test report tables.';

    public function run(array $params)
    {
        $db  = \Config\Database::connect();
        $dry = CLI::getOption('dry') !== null;
        if (CLI::getOption('materials') !== null) {
            $n = 0;
            foreach (DtrController::FORM_CONFIG as $slug => $cfg) {
                if ($slug === 'bonding' || ! $db->tableExists($cfg['table'])) {
                    continue;
                }
                $rows = $db->query('SELECT r.* FROM `' . $cfg['table'] . '` r WHERE r.`LabRefNo` IS NOT NULL AND r.`LabRefNo` <> "" AND NOT EXISTS '
                    . '(SELECT 1 FROM tbl_dtr_unified_labrefs u WHERE u.source_table = ? AND u.source_id = r.`' . $cfg['pk'] . '`)', [$cfg['table']])->getResultArray();
                foreach ($rows as $r) {
                    $dry || DtrLabrefIndex::upsert($db, $slug, (int) $r[$cfg['pk']], $r);
                    $n++;
                }
            }
            CLI::write(($dry ? 'Would add ' : 'Added ') . $n . ' material index row(s).');
        } else {
            $n = DtrLabrefIndex::addMissing($db, $dry);
            CLI::write(($dry ? 'Would add ' : 'Added ') . $n . ' index row(s).');
        }
        if (! $dry && CLI::getOption('refresh') !== null) {
            $m    = 0;
            $only = CLI::getOption('slugs') ? array_map('trim', explode(',', (string) CLI::getOption('slugs'))) : [];   // --slugs=fabric,box
            foreach (DtrController::FORM_CONFIG as $slug => $cfg) {
                if ($slug === 'bonding' || ! $db->tableExists($cfg['table']) || ($only && ! in_array($slug, $only, true))) {
                    continue;
                }
                foreach ($db->query('SELECT * FROM `' . $cfg['table'] . '` WHERE LabRefNo IS NOT NULL AND LabRefNo <> \'\'')->getResultArray() as $r) {
                    DtrLabrefIndex::upsert($db, $slug, (int) $r[$cfg['pk']], $r);
                    $m++;
                }
            }
            CLI::write('Refreshed ' . $m . ' material report row(s).');
        }
    }
}
