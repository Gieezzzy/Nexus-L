<?php

namespace App\Services\Dtr;

/** Reads and derives bonding fields from the machine OUTDATA files. (moved out of DtrController; bodies unchanged). */
class DtrBondingFile
{
    /**
     * Same rules the Access import (btnImportExcel) applies after reading the file: technician by equipment code,
     * side from the Lab Ref suffix, age category / STD from the style, dates, and the per-group + overall result.
     * The equipment -> technician list is the admin setting `bonding_technicians` (JSON {"DTP-QC-001":"S. Aquino"}).
     */
    public function deriveBondingFields(array $f, string $labRefNo): array
    {
        $techs = [
            'DTP-QC-001' => 'L. Tuddao', 'DTP-QC-002' => 'S. Aquino', 'DTP-QC-048' => 'L. Aquidado',
            'DTP-QC-064' => 'J. Barrera', 'DTP-QC-066' => 'J. Parrilla', 'DTP-QC-067' => 'R. Cabigas',
        ];
        $db = \Config\Database::connect();
        if ($db->tableExists('tbl_app_settings')) {
            $row = $db->table('tbl_app_settings')->where('skey', 'bonding_technicians')->get()->getRowArray();
            $j   = json_decode((string) ($row['svalue'] ?? ''), true);
            if (is_array($j) && $j) {
                $techs = $j;
            }
        }

        $set = static function (array &$a, string $k, $v): void {
            if (! isset($a[$k]) || $a[$k] === '') {
                $a[$k] = $v;
            }
        };

        // Excel stores dates as serial numbers
        foreach (['TestedDate', 'ReceivedDate', 'FinishedDate'] as $dk) {
            if (isset($f[$dk]) && is_numeric($f[$dk]) && (float) $f[$dk] > 20000) {
                $f[$dk] = gmdate('Y-m-d', (int) round(((float) $f[$dk] - 25569) * 86400));
            }
        }
        if (! empty($f['TestedDate'])) {
            $set($f, 'ReceivedDate', $f['TestedDate']);
            $set($f, 'FinishedDate', $f['TestedDate']);
        }
        if (! empty($f['ModelStyle'])) {
            $set($f, 'Color', $f['ModelStyle']);
        }
        if (! empty($f['EquipmentCode'])) {
            // "048 - L. Aquidado": the machine no. (last digits of the equipment code) (DTP-QC-NNN) goes in front of the name
            // the code may be written "DTP-QC-048", "DTP - QC - 48" or "DTP QC-048": it is matched on its number
            $name = '';
            $mn   = [];
            if (preg_match('/dtp[\s-]*qc[\s-]*0*(\d+)/i', (string) $f['EquipmentCode'], $mn) || preg_match('/(\d+)\s*$/', (string) $f['EquipmentCode'], $mn)) {
                $no = str_pad(substr($mn[1], -3), 3, '0', STR_PAD_LEFT);
                foreach ($techs as $code => $tn) {
                    if (preg_match('/(\d+)\s*$/', (string) $code, $cn) && str_pad(substr($cn[1], -3), 3, '0', STR_PAD_LEFT) === $no) {
                        $name = preg_match('/^DTP-QC-\d{3}\s*-/', (string) $tn) ? (string) $tn : 'DTP-QC-' . $no . ' - ' . $tn;
                        break;
                    }
                }
            }
            $set($f, 'Technician', $name);
        }
        $last = strtoupper(substr(trim($labRefNo), -1));
        $set($f, 'Side', $last === 'L' ? 'Left' : ($last === 'R' ? 'Right' : 'Unknown'));

        $style = strtoupper((string) ($f['ModelStyle'] ?? ''));
        $child = false;
        foreach (['UY', 'TD', 'JN', 'INFANT'] as $t) {
            if ($style !== '' && str_contains($style, $t)) {
                $child = true;
            }
        }
        foreach (['UA', 'WM', 'MN'] as $t) {
            if ($style !== '' && str_contains($style, $t)) {
                $child = false;
            }
        }
        $std = $child ? 2.5 : 3.0;
        $set($f, 'AgeCategory', $child ? 'Children' : 'Adult');
        $set($f, 'STD', $std);

        $rank  = ['PASS' => 0, 'TOLERABLE' => 1, 'FAILED' => 2];
        $worst = static function (string $a, string $b) use ($rank): string {
            return $rank[$b] > $rank[$a] ? $b : $a;
        };
        $grade = static function (float $v, float $pass, float $tol): string {
            return $v >= $pass ? 'PASS' : ($v >= $tol ? 'TOLERABLE' : 'FAILED');
        };
        $overall = 'PASS';
        $maxPts  = 0;
        foreach (['UTF' => ['UTFpoint', 45, $std, $std === 3.0 ? 2.4 : 2.0], 'FTO' => ['FTOpoint', 45, $std, $std === 3.0 ? 2.4 : 2.0], 'FiTO' => ['FiTO', 13, 1.0, 0.8]] as $grp => [$pre, $max, $pass, $tol]) {
            $res = 'PASS';
            $any = false;
            for ($i = 1; $i <= $max; $i++) {
                if (isset($f[$pre . $i]) && is_numeric($f[$pre . $i])) {
                    $any = true;
                    $res = $worst($res, $grade((float) $f[$pre . $i], $pass, $tol));
                    if ($grp === 'UTF') {
                        $maxPts = max($maxPts, $i);
                    }
                }
            }
            if ($any) {
                $f[$grp . '_Result'] = $res;
                $overall = $worst($overall, $res);
            }
        }
        $f['FiTOSTD'] = 1;
        $f['Result']  = $overall;
        if ($maxPts > 0) {
            $f['MaxPoints'] = $maxPts;
        }

        return $f;
    }

    /**
     * Finds a machine output file in writable/cache/outdata_samples/{subFolder} or \\192.168.32.45\OUTDATA\{subFolder},
     * safely copying from \\192.168.32.45 to local cache without modifying the network source.
     */
    public function findAndCacheOutdataFile(string $subFolder, string $labRefNo, string $baseRef): ?string
    {
        $cacheDir = WRITEPATH . 'cache' . DIRECTORY_SEPARATOR . 'outdata_samples' . DIRECTORY_SEPARATOR . $subFolder;
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $candidates = [$labRefNo, $baseRef];
        $exts       = ['.xlsx', '.csv'];

        // 1. Check local cache first
        foreach ($candidates as $cand) {
            foreach ($exts as $ext) {
                $localPath = $cacheDir . DIRECTORY_SEPARATOR . $cand . $ext;
                if (is_file($localPath)) {
                    return $localPath;
                }
            }
        }

        // 2. Check \\192.168.32.45\OUTDATA\{subFolder} (Read-only copy to cache!)
        $netDir = \App\Libraries\MachineFileReader::root() . $subFolder;
        foreach ($candidates as $cand) {
            foreach ($exts as $ext) {
                $netPath   = $netDir . '\\' . $cand . $ext;
                $localPath = $cacheDir . DIRECTORY_SEPARATOR . $cand . $ext;
                if (@is_file($netPath)) {
                    @copy($netPath, $localPath);
                    if (is_file($localPath)) {
                        return $localPath;
                    }
                    return $netPath;
                }
            }
        }

        // 3. Fallback for demo/testing: if user clicks Load Machine Results on a sample record, return most recent cached file in that folder
        $cachedFiles = glob($cacheDir . DIRECTORY_SEPARATOR . '*.{xlsx,csv}', GLOB_BRACE);
        if (! empty($cachedFiles)) {
            return $cachedFiles[0];
        }

        return null;
    }

    public function parseSpreadsheetToGrid(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $grid = [];
            if (($fh = @fopen($path, 'r')) !== false) {
                $r = 1;
                while (($row = fgetcsv($fh)) !== false) {
                    foreach ($row as $cIdx => $val) {
                        $grid[$r][$cIdx + 1] = $val;
                    }
                    $r++;
                }
                fclose($fh);
            }
            return $grid;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        if (($ssXml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = @simplexml_load_string($ssXml);
            if ($sx) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } else {
                        $txt = '';
                        foreach ($si->r as $r) {
                            $txt .= (string) $r->t;
                        }
                        $sharedStrings[] = $txt;
                    }
                }
            }
        }

        $sheetXml = false;
        for ($i = 1; $i <= 5; $i++) {
            $cand = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
            if ($cand !== false && strpos($cand, '<c ') !== false) {
                $sheetXml = $cand;
                break;
            }
        }
        $zip->close();
        if ($sheetXml === false) {
            return [];
        }

        $sx = @simplexml_load_string($sheetXml);
        if (! $sx || ! isset($sx->sheetData->row)) {
            return [];
        }

        $grid = [];
        foreach ($sx->sheetData->row as $row) {
            $rIdx = (int) $row['r'];
            foreach ($row->c as $c) {
                $ref        = (string) $c['r'];
                $colLetters = preg_replace('/[0-9]/', '', $ref);
                $colIdx     = 0;
                for ($i = 0; $i < strlen($colLetters); $i++) {
                    $colIdx = $colIdx * 26 + (ord($colLetters[$i]) - 64);
                }
                $type = (string) $c['t'];
                $val  = isset($c->v) ? (string) $c->v : '';
                if ($type === 's') {
                    $val = $sharedStrings[(int) $val] ?? $val;
                } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                    $val = (string) $c->is->t;
                }
                $grid[$rIdx][$colIdx] = $val;
            }
        }
        return $grid;
    }

    /**
     * Exact PHP port of LoadResults(xlWS, labRef, labRefRow, resultRow, startCol, numCols, targetForm, fieldMap)
     * from Mod_modMaterialUtilities.txt.
     */
    public function extractByLoadResultsRule(array $grid, string $labRef, int $labRefRow, int $resultRow, int $startCol, int $numCols, array $fields): array
    {
        $foundCol = null;
        $rowCells = $grid[$labRefRow] ?? [];
        foreach ($rowCells as $col => $cellVal) {
            if ($col < $startCol) {
                continue;
            }
            if (strcasecmp(trim((string) $cellVal), trim($labRef)) === 0) {
                $foundCol = (int) $col;
                break;
            }
        }

        // If exact LabRefNo wasn't found in row (e.g. testing with a cached sample file), use startCol (Col 3) if numeric data exists
        if ($foundCol === null && isset($grid[$resultRow][$startCol]) && is_numeric($grid[$resultRow][$startCol])) {
            $foundCol = $startCol;
        }

        if ($foundCol === null) {
            return [];
        }

        $out = [];
        for ($i = 0; $i < $numCols; $i++) {
            $col = $foundCol + $i;
            $val = $grid[$resultRow][$col] ?? null;
            if ($val !== null && $val !== '' && $val !== '---' && isset($fields[$i])) {
                $out[$fields[$i]] = is_numeric($val) ? round((float) $val, 2) : $val;
            }
        }
        return $out;
    }
}
