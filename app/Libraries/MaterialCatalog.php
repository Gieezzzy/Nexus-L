<?php

namespace App\Libraries;

/**
 * The material categories of the lab (Main Menu of the Access material test system: Fabric, Leather, Sockliner / Foam, Foxing Tape / Heel Logo,
 * Rubber Outsole, Shoelace, Thread, Webbing, Elastic Gore, Insole, Velcro, Label, Box, Eyelet / Grommet), the material types of each one and the TESTS
 * each material type has (app/Libraries/material_catalog.json, read from the Access file by tools/build_material_catalog.py).
 *
 * A received material (Lab Tracker > Material Received) is saved with its ERP material type (FABRIC UPPER, LACE, HEEL LOGO ...): resolve() finds the Access
 * material type and its tests; every test becomes a child of the material's Lab Ref in tbl_dtr_MaterialTrackerTests (Lab Ref "<material Lab Ref>-<test code>").
 */
class MaterialCatalog
{
    /** ERP material type (upper case) -> Access material type name; an entry ending in * means every Access type that starts so (their tests together) */
    private const ERP = [
        'FABRIC UPPER' => 'Fabric Upper', 'FABRIC LINING' => 'Fabric Lining', 'FABRIC INNER LINING' => 'Fabric Lining', 'OSNABURG' => 'Fabric Lining', 'PROCESS FABRIC' => 'Fabric Lining',
        'FABRIC PRINTED' => 'Ink & Print', 'PRINTED CANVAS' => 'Ink & Print', 'FABRIC SYNTHETIC' => 'Synthetic Upper', 'SYNTHETIC' => 'Synthetic Upper', 'LEATHER' => 'Leather Upper',
        'LACE' => 'Shoe lace*', 'THREAD' => 'Thread*', 'NYLON WEBBING' => 'Webbing - light', 'WEBBING' => 'Webbing - light', 'GORE' => 'Elastic Gore - light',
        'HOOK AND LOOP' => 'Velcro - light', 'VELCRO' => 'Velcro - light', 'SIZE LABEL' => 'Size Label - LIGHT', 'WOVEN LABEL' => 'Woven Label - LIGHT w/ Perspiration',
        'TRANSFER LABEL' => 'Transfer Label - LIGHT', 'HEEL LOGO' => 'Heel Logo', 'HEEL LOGO CHINA' => 'Heel Logo', 'HEEL LOGO RUBBER' => 'Heel Logo', 'FOXING TAPE' => 'FoxingTape',
        'SOCKLINER' => 'Sockliner - PU', 'DROP IN SOCKLINER' => 'Sockliner - PU', 'LATEX FOAM' => 'Sockliner - Latex', 'FOAM' => 'PU Foam - Low',
        'INNER BOX' => 'Inner Box', 'OUTER BOX' => 'Outer Box', 'OUTSOLE' => 'Rubber', 'RUBBER' => 'Rubber', 'INSOLE' => 'Insole',
        'EYELET AND GROMMET' => 'Eyelet', 'EYELET AND GROMMETS' => 'Eyelet', 'EYELET' => 'Eyelet', 'GROMMET' => 'Grommet', 'ZIPPER' => 'Zipper', 'BUCKLE' => 'Eyelet', 'ACCESSORIES' => 'Eyelet',
    ];

    private static ?array $data = null;

    public static function data(): array
    {
        if (self::$data === null) {
            $f = __DIR__ . '/material_catalog.json';
            self::$data = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        }

        return self::$data;
    }

    /** @return list<array{slug:string,name:string}> the categories in the order of the Main Menu */
    public static function categories(): array
    {
        return array_map(static fn ($c) => ['slug' => $c['slug'], 'name' => $c['name']], self::data()['categories'] ?? []);
    }

    /**
     * @return array{category:string,category_name:string,type:string,tests:list<string>}|null  null = this material has no test list (Processing Material, CANCEL ...)
     */
    public static function resolve(string $materialType): ?array
    {
        $u    = strtoupper(trim($materialType));
        $want = [];
        if ($u === '') {
            return null;
        }
        $cats = self::data()['categories'] ?? [];
        $name = self::ERP[$u] ?? null;
        foreach ($cats as $c) {                 // an Access type name given as it is
            foreach ($c['types'] as $t) {
                if (strtoupper($t['name']) === $u) {
                    $name = $t['name'];
                }
            }
        }
        if ($name === null) {
            return null;
        }
        $prefix = str_ends_with($name, '*') ? strtoupper(rtrim($name, '*')) : null;
        foreach ($cats as $c) {
            $hit = [];
            foreach ($c['types'] as $t) {
                if ($prefix !== null ? str_starts_with(strtoupper($t['name']), $prefix) : strtoupper($t['name']) === strtoupper($name)) {
                    $hit[] = $t;
                }
            }
            if ($hit) {
                $codes = [];
                foreach ($hit as $t) {
                    $codes = array_merge($codes, $t['tests']);
                }
                $codes = array_values(array_intersect($c['all_tests'], array_unique($codes)));      // the order of the form

                return ['category' => $c['slug'], 'category_name' => $c['name'], 'type' => $prefix !== null ? rtrim($name, '*') : $hit[0]['name'], 'tests' => $codes];
            }
        }

        return null;
    }

    /** @return array{name:string,method:string,unit:string} */
    public static function test(string $code): array
    {
        return self::data()['tests'][$code] ?? ['name' => $code, 'method' => '', 'unit' => ''];
    }

    /** the category slug of a received material (by its ERP / Access material type), '' when it has none */
    public static function categoryOf(string $materialType): string
    {
        return self::resolve($materialType)['category'] ?? '';
    }

    /** the table names are case-insensitive on this server but fieldExists() is not */
    private static function hasCol(string $table, string $col): bool
    {
        return (bool) \Config\Database::connect()->query('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND LOWER(table_name) = ? AND LOWER(column_name) = ? LIMIT 1', [strtolower($table), strtolower($col)])->getRowArray();
    }

    public static function ensureTables(): void
    {
        $db = \Config\Database::connect();
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_material_type_tests (ID INT AUTO_INCREMENT PRIMARY KEY, MaterialType VARCHAR(100) NOT NULL, TestCode VARCHAR(20) NOT NULL,
            TestName VARCHAR(191) NOT NULL, SortNo INT NOT NULL DEFAULT 0, UNIQUE KEY uq_type_test (MaterialType, TestCode)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->query('CREATE TABLE IF NOT EXISTS tbl_dtr_MaterialTrackerTests (TestID INT AUTO_INCREMENT PRIMARY KEY, TrackerID INT NULL, MotherLabRefNo VARCHAR(191) NOT NULL,
            ChildLabRefNo VARCHAR(191) NOT NULL, MaterialCode VARCHAR(191) NULL, MaterialType VARCHAR(191) NULL, TestType VARCHAR(191) NULL, TestCode VARCHAR(20) NULL, Status VARCHAR(50) NULL,
            CreatedAt DATETIME NULL, UNIQUE KEY uq_child (ChildLabRefNo), INDEX (MotherLabRefNo), INDEX (TrackerID)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        foreach (['TestMethod' => 'VARCHAR(80)', 'Unit' => 'VARCHAR(40)'] as $col => $type) {
            if (! self::hasCol('tbl_dtr_material_type_tests', $col)) {
                $db->query("ALTER TABLE tbl_dtr_material_type_tests ADD COLUMN {$col} {$type} NULL");
            }
        }
        foreach (['TestMethod' => 'VARCHAR(80)', 'Unit' => 'VARCHAR(40)', 'Result' => 'VARCHAR(20)', 'TestedDate' => 'DATE', 'Technician' => 'VARCHAR(120)', 'Remarks' => 'VARCHAR(500)',
            'UpdatedBy' => 'VARCHAR(120)', 'UpdatedAt' => 'DATETIME', 'SortNo' => 'INT'] as $col => $type) {
            if (! self::hasCol('tbl_dtr_MaterialTrackerTests', $col)) {
                $db->query("ALTER TABLE tbl_dtr_MaterialTrackerTests ADD COLUMN {$col} {$type} NULL");
            }
        }
        // the tests of every Access material type (inserted once; a row that exists - the older RUBBER list - is kept as it is)
        $have = (int) $db->query("SELECT COUNT(*) n FROM tbl_dtr_material_type_tests WHERE TestMethod IS NOT NULL")->getRow('n');
        if ($have === 0) {
            foreach (self::data()['categories'] ?? [] as $c) {
                foreach ($c['types'] as $t) {
                    foreach ($t['tests'] as $i => $code) {
                        $d = self::test($code);
                        $db->query('INSERT IGNORE INTO tbl_dtr_material_type_tests (MaterialType, TestCode, TestName, SortNo, TestMethod, Unit) VALUES (?, ?, ?, ?, ?, ?)',
                            [strtoupper($t['name']), $code, $d['name'], $i + 1, $d['method'], $d['unit']]);
                    }
                }
            }
            $db->query("UPDATE tbl_dtr_material_type_tests SET TestMethod = '' WHERE TestMethod IS NULL");
        }
    }

    /**
     * Creates the child tests of one received material (its Lab Ref is the mother) from the tests of its material type. Returns how many were added.
     * @param array{TrackerID:int|string,LabRefNo:string,MaterialCode?:string,MaterialType?:string} $row
     */
    public static function addChildren(array $row, string $specificType = ''): int
    {
        $db  = \Config\Database::connect();
        $res = self::resolve($specificType !== '' ? $specificType : (string) ($row['MaterialType'] ?? '')) ?? self::resolve((string) ($row['MaterialType'] ?? ''));
        if (! $res) {
            return 0;
        }
        $n = 0;
        foreach ($res['tests'] as $i => $code) {
            $d = self::test($code);
            $db->query('INSERT IGNORE INTO tbl_dtr_MaterialTrackerTests (TrackerID, MotherLabRefNo, ChildLabRefNo, MaterialCode, MaterialType, TestType, TestCode, Status, CreatedAt, TestMethod, Unit, SortNo)
                VALUES (?, ?, ?, ?, ?, ?, ?, "Pending", NOW(), ?, ?, ?)',
                [(int) $row['TrackerID'], $row['LabRefNo'], $row['LabRefNo'] . '-' . $code, (string) ($row['MaterialCode'] ?? ''), $res['type'], $d['name'], $code, $d['method'], $d['unit'], $i + 1]);
            $n += $db->affectedRows() > 0 ? 1 : 0;
        }

        return $n;
    }

    /** gives the received materials that have no tests yet their tests (also after the catalog was loaded); returns the number of tests added */
    public static function backfill(): int
    {
        self::ensureTables();
        $db = \Config\Database::connect();
        $n  = 0;
        foreach ($db->query('SELECT t.* FROM tbl_dtr_MaterialTracker t WHERE NOT EXISTS (SELECT 1 FROM tbl_dtr_MaterialTrackerTests c WHERE c.MotherLabRefNo = t.LabRefNo COLLATE utf8mb4_unicode_ci)')->getResultArray() as $r) {
            $spec = (string) ($db->query('SELECT MaterialType FROM tbl_dtr_material_type_status WHERE MaterialCode = ? LIMIT 1', [$r['MaterialCode']])->getRow('MaterialType') ?? '');
            $n += self::addChildren($r, $spec);
        }
        // tests created before the method / sort columns existed
        $db->query('UPDATE tbl_dtr_MaterialTrackerTests c JOIN tbl_dtr_material_type_tests y ON y.TestCode = c.TestCode AND y.MaterialType = UPPER(c.MaterialType) SET c.TestMethod = y.TestMethod, c.Unit = y.Unit, c.SortNo = y.SortNo WHERE c.TestMethod IS NULL');

        return $n;
    }
}
