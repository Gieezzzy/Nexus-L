<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark settings:transfer <job id>
 * Started by the Settings page (Admin): copies every table of the database NEXUS-L uses now - structure and rows - into another database.
 * The job file in writable/transfer/<id>.json holds the target and receives the progress; the target's password is removed from it at the end.
 * Nothing in the source database is changed. A table that already holds rows in the target is skipped unless "overwrite" was chosen.
 */
class SettingsTransfer extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'settings:transfer';
    protected $description = 'Copies the database (structure and rows) into another database; started from the Settings page.';
    protected $usage       = 'settings:transfer <job id>';

    private string $file = '';
    private array $job = [];

    private function save(): void
    {
        file_put_contents($this->file, json_encode($this->job));
    }

    private function note(string $line): void
    {
        $this->job['log'][] = date('H:i:s') . ' ' . $line;
        if (count($this->job['log']) > 120) {
            array_shift($this->job['log']);
        }
    }

    public function run(array $params)
    {
        $id = preg_replace('/[^a-f0-9]/', '', (string) ($params[0] ?? ''));
        $this->file = WRITEPATH . 'transfer/' . $id . '.json';
        if ($id === '' || ! is_file($this->file)) {
            CLI::error('Unknown transfer job.');

            return;
        }
        $this->job = json_decode((string) file_get_contents($this->file), true) ?: [];
        @set_time_limit(0);
        ini_set('memory_limit', '1024M');
        mysqli_report(MYSQLI_REPORT_OFF);

        $this->job['status'] = 'running';
        $this->save();

        try {
            $this->copy();
            $this->job['status'] = $this->job['errors'] ? 'finished-with-errors' : 'done';
        } catch (\Throwable $e) {
            $this->job['status'] = 'failed';
            $this->job['errors'][] = $e->getMessage();
        }
        $this->job['finished_at'] = date('Y-m-d H:i:s');
        $this->job['current']     = '';
        unset($this->job['target']['password']);
        $this->note('Transfer ' . $this->job['status']);
        $this->save();
    }

    private function copy(): void
    {
        $c   = config(\Config\Database::class)->default;
        $src = new \mysqli((string) $c['hostname'], (string) $c['username'], (string) $c['password'], (string) $c['database'], (int) ($c['port'] ?? 3306));
        if ($src->connect_errno) {
            throw new \RuntimeException('The source database could not be opened: ' . $src->connect_error);
        }
        $src->set_charset('utf8mb4');
        $t   = $this->job['target'];
        $dst = new \mysqli($t['hostname'], $t['username'], $t['password'], $t['database'], (int) $t['port']);
        if ($dst->connect_errno) {
            throw new \RuntimeException('The target database could not be opened: ' . $dst->connect_error);
        }
        $dst->set_charset('utf8mb4');
        $dst->query('SET FOREIGN_KEY_CHECKS=0');
        $dst->query("SET SESSION sql_mode=''");

        $tables = [];
        $res    = $src->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        while ($r = $res->fetch_row()) {
            $tables[] = $r[0];
        }
        if (! empty($this->job['only']) && is_array($this->job['only'])) {
            $tables = array_values(array_intersect($tables, $this->job['only']));      // a chosen few (used for tests)
        }
        $this->job['tables_total'] = count($tables);
        $this->note(count($tables) . ' tables to copy');
        $this->save();

        foreach ($tables as $table) {
            $this->job['current'] = $table;
            $q = '`' . str_replace('`', '``', $table) . '`';
            $this->save();

            // structure
            $exists = $dst->query('SHOW TABLES LIKE \'' . $dst->real_escape_string($table) . '\'')->num_rows > 0;
            if (! $exists) {
                $create = $src->query('SHOW CREATE TABLE ' . $q)->fetch_row()[1] ?? '';
                $create = preg_replace('/^CREATE TABLE/', 'CREATE TABLE IF NOT EXISTS', $create);
                if (! $dst->query($create)) {
                    $this->job['errors'][] = "{$table}: could not create it - " . $dst->error;
                    $this->job['tables_done']++;
                    continue;
                }
            }
            // rows
            $have = (int) ($dst->query('SELECT COUNT(*) FROM ' . $q)->fetch_row()[0] ?? 0);
            if ($have > 0 && empty($this->job['overwrite'])) {
                $this->note("{$table}: skipped, the target already holds {$have} rows");
                $this->job['tables_done']++;
                continue;
            }
            if ($have > 0) {
                $dst->query('TRUNCATE TABLE ' . $q);
            }
            $copied = 0;
            $stmt   = $src->query('SELECT * FROM ' . $q, MYSQLI_USE_RESULT);
            if ($stmt === false) {
                $this->job['errors'][] = "{$table}: could not be read - " . $src->error;
                $this->job['tables_done']++;
                continue;
            }
            $fields = array_map(static fn ($f) => '`' . str_replace('`', '``', $f->name) . '`', $stmt->fetch_fields());
            $head   = 'INSERT INTO ' . $q . ' (' . implode(',', $fields) . ') VALUES ';
            $batch  = [];
            $size   = 0;
            $flush  = function () use (&$batch, &$size, $dst, $head, $table, &$copied) {
                if (! $batch) {
                    return;
                }
                if (! $dst->query($head . implode(',', $batch))) {
                    $this->job['errors'][] = "{$table}: " . $dst->error;
                } else {
                    $copied += count($batch);
                }
                $batch = [];
                $size  = 0;
            };
            while ($row = $stmt->fetch_row()) {
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = $v === null ? 'NULL' : "'" . $dst->real_escape_string((string) $v) . "'";
                }
                $line    = '(' . implode(',', $vals) . ')';
                $batch[] = $line;
                $size   += strlen($line);
                if (count($batch) >= 400 || $size > 2_000_000) {
                    $flush();
                    $this->job['rows_copied'] += 0;
                    $this->job['current'] = "{$table} ({$copied} rows)";
                    $this->save();
                }
            }
            $stmt->free();
            $flush();
            $this->job['rows_copied'] += $copied;
            $this->job['tables_done']++;
            $this->note("{$table}: {$copied} rows");
            $this->save();
        }
        $dst->query('SET FOREIGN_KEY_CHECKS=1');
        $src->close();
        $dst->close();
    }
}
