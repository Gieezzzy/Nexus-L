<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class VfStats extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'vf:stats';
    protected $description = 'Shows exact counts and series breakdown of VF test methods.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        $total = $db->table('tbl_vf_test_methods')->countAll();
        $illustrated = $db->table('tbl_vf_test_methods')->where('is_illustrated', 1)->countAllResults();
        $official = $db->table('tbl_vf_test_methods')->where('is_illustrated', 0)->countAllResults();

        $distinctCodes = $db->query("SELECT COUNT(DISTINCT code) as c FROM tbl_vf_test_methods WHERE code NOT IN ('MANUAL', 'DOC')")->getRowArray()['c'];

        CLI::write("Total Documents in Catalog: {$total}", 'cyan');
        CLI::write("Official Standard Operating Procedures & Manuals: {$official}", 'green');
        CLI::write("Illustrated Pictorial Guides: {$illustrated}", 'yellow');
        CLI::write("Distinct Test Method Codes: {$distinctCodes}", 'cyan');
        CLI::write("--------------------------------------------------", 'white');

        $sql = "
            SELECT 
                CASE 
                    WHEN code LIKE 'VF-A%' THEN 'Series A: Physical & Dimensional Properties'
                    WHEN code LIKE 'VF-B%' THEN 'Series B: Strength & Mechanical Properties'
                    WHEN code LIKE 'VF-C%' THEN 'Series C: Abrasion Resistance'
                    WHEN code LIKE 'VF-D%' THEN 'Series D: Flexing & Fatigue Resistance'
                    WHEN code LIKE 'VF-E%' THEN 'Series E: Chemical, Aging & Environmental Fastness'
                    WHEN code LIKE 'VF-F%' THEN 'Series F: Adhesion & Bond Strength'
                    WHEN code LIKE 'VF-G%' THEN 'Series G: Assembled Footwear Performance'
                    WHEN code LIKE 'VF-H%' THEN 'Series H: Sampling & Material Conditioning'
                    WHEN code LIKE 'VF-I%' THEN 'Series I: Chemical Analysis & Identification'
                    WHEN code LIKE 'VF-J%' THEN 'Series J: Electrical & Thermal Safety'
                    ELSE 'Master Manuals, Guidelines & UTM Parameters'
                END as series_group,
                COUNT(DISTINCT code) as distinct_methods,
                COUNT(*) as total_documents
            FROM tbl_vf_test_methods
            GROUP BY series_group
            ORDER BY series_group ASC
        ";

        $rows = $db->query($sql)->getResultArray();
        foreach ($rows as $r) {
            CLI::write(sprintf("%-52s | %2d methods | %2d files", $r['series_group'], $r['distinct_methods'], $r['total_documents']));
        }
    }
}
