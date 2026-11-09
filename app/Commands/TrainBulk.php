<?php

namespace App\Commands;

use App\Libraries\Brain;
use App\Libraries\BrainSeed10;
use App\Libraries\BrainSeed11;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Bulk training of the AI Brain.
 *   1. 1,000 new knowledge entries built only from real tables of the system: the acceptance standards (tbl_dtr_standards, one entry per
 *      material type and test), per-test and per-material summaries of them, the bonding limits, the lab form numbers, the test form
 *      controls and the VF test method catalog.
 *   2. 5,000 technical questions on those entries, 5,000 everyday how-to questions on the system's own how-to playbooks and 2,500
 *      report-generation questions, learned over 45 epochs.
 * The learning step is the logistic step of BrainLearn::train(), done in memory and written back in batches (BrainLearn::train() costs
 * about 11 database calls per question, so 560,000 steps would take hours). Two differences keep the new entries from pushing out the
 * existing ones: the bias weight "#" of an entry is never changed (a raised bias lifted every trained entry above the others), and the
 * standards entries also learn from questions that are NOT theirs (VF test method and how-to questions) as negative examples.
 * Run `php spark brain:train` afterwards to rebuild the index of the AI service.
 */
class TrainBulk extends BaseCommand
{
    protected $group       = 'Brain';
    protected $name        = 'brain:train-bulk';
    protected $description = 'Builds 1,000 entries from the system tables and trains 5,000 technical + 5,000 non-technical + 2,500 report-generation questions (45 epochs).';
    protected $usage       = 'brain:train-bulk [epochs] [system|vf|labdocs|labfiles]';

    private const ETA  = 0.5;     // the same step and limit as BrainLearn
    private const WMAX = 2.0;

    /** @var array<string,string> words for the test-method codes of tbl_dtr_standards (only what the code itself says) */
    private const ALIAS = [
        'Tensile' => 'tensile strength', 'Elongation' => 'elongation', 'Hardness' => 'hardness', 'Crocking' => 'crocking color transfer rub',
        'Martindale' => 'martindale abrasion', 'LaceAbrasion' => 'lace abrasion', 'HeatAging' => 'heat aging', 'Hydrolysis' => 'hydrolysis',
        'StitchTear' => 'stitch tear', 'BaumannTear' => 'baumann tear', 'KnotSlip' => 'knot slip', 'UV' => 'uv discoloration', 'DieC' => 'die c tear',
        'Compression' => 'compression', 'LaminarPeel' => 'laminar peel', 'CardboardAdhesion' => 'cardboard adhesion', 'EdgeCrushTest' => 'edge crush',
    ];

    private $db;
    private string $now = '';

    public function run(array $params)
    {
        $epochs    = max(1, min(100, (int) ($params[0] ?? 45)));
        $set       = in_array($params[1] ?? 'system', ['system', 'vf', 'labdocs', 'labfiles'], true) ? ($params[1] ?? 'system') : 'system';
        $setNo     = ['system' => 0, 'vf' => 1, 'labdocs' => 2, 'labfiles' => 3][$set];
        $this->db  = \Config\Database::connect();
        $this->now = date('Y-m-d H:i:s');
        CLI::write("=== Bulk training [{$set}]: 1,000 entries + 12,500 questions x {$epochs} epochs ===", 'cyan');

        // a copy of the weights first: the set can be taken back with  RENAME / INSERT ... SELECT  from this table if the benchmark drops
        $bak = 'tbl_bot_weights_bak_' . $set;
        $this->db->query("DROP TABLE IF EXISTS `{$bak}`");
        $this->db->query("CREATE TABLE `{$bak}` AS SELECT * FROM tbl_bot_weights");
        CLI::write("Weights saved in {$bak} (" . $this->db->query("SELECT COUNT(*) c FROM `{$bak}`")->getRow()->c . ' rows).', 'light_gray');

        $entries = $set === 'vf' ? $this->buildVfEntries() : ($set === 'labdocs' ? $this->buildLabEntries() : ($set === 'labfiles' ? $this->buildLabFileEntries() : $this->buildEntries()));
        CLI::write('Entries built from the system tables: ' . count($entries) . ' (standards ' . count(array_filter($entries, static fn ($e) => $e['kind'] === 'std')) . ').', 'green');
        $technical = $this->technical($entries);
        $general   = $this->general($setNo);
        $reports   = $this->reports($setNo);
        CLI::write('Questions: technical ' . count($technical) . ', non-technical ' . count($general) . ', report generation ' . count($reports), 'green');
        if (count($technical) < 5000 || count($general) < 5000 || count($reports) < 2500 || count($entries) < 1000) {
            CLI::write('One of the groups is short of the requested size.', 'yellow');
        }

        // ------------------------------------------------------------------ learn in memory (no bias term)
        $term = static function (string $q): array {
            return array_slice(array_values(array_unique(array_filter(Brain::tokens($q), static fn ($t) => $t !== '' && $t !== '#'))), 0, 12);
        };
        $pos = [];
        foreach (array_merge($technical, $general, $reports) as $q) {
            $t = $term($q['q']);
            if ($t) {
                $pos[] = [$q['id'], $t];
            }
        }
        // negatives for the standards entries: VF test method and everyday questions that are not about a standard
        $neg = [];
        foreach ($set === 'system' ? $this->vfQuestions() : [] as $q) {      // the VF questions are the right ones for the VF set, so only the system set pushes against them
            $t = $term($q);
            if ($t) {
                $neg[] = $t;
            }
        }
        foreach ($set === 'vf' ? array_slice($this->vfQuestions(), 0, 0) : [] as $q) {
            $neg[] = $term($q);
        }
        foreach (array_slice($general, 0, 700) as $q) {
            $t = $term($q['q']);
            if ($t) {
                $neg[] = $t;
            }
        }
        $stdIds = [];
        foreach ($entries as $e) {
            $stdIds[$e['id']] = true;
        }

        $ids = array_values(array_unique(array_column($pos, 0)));
        $w   = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            foreach ($this->db->table('tbl_bot_weights')->whereIn('kb_id', $chunk)->where('term !=', '#')->get()->getResultArray() as $r) {
                $w[(int) $r['kb_id']][$r['term']] = (float) $r['w'];
            }
        }
        $touched = [];
        $steps   = 0;
        $negs    = 0;
        $index   = [];                                  // term => [kb ids of standards entries that have learned it]
        for ($ep = 1; $ep <= $epochs; $ep++) {
            foreach ($pos as [$kb, $keys]) {
                $z = 0.0;
                foreach ($keys as $k) {
                    $z += $w[$kb][$k] ?? 0.0;
                }
                $e = 1 / (1 + exp(-$z)) - 1;            // y = 1
                foreach ($keys as $k) {
                    $new = max(-self::WMAX, min(self::WMAX, ($w[$kb][$k] ?? 0.0) - self::ETA * $e));
                    if (isset($stdIds[$kb]) && ! isset($w[$kb][$k])) {
                        $index[$k][$kb] = true;
                    }
                    $w[$kb][$k] = $new;
                    $touched[$kb][$k] = ($touched[$kb][$k] ?? 0) + 1;
                }
                $steps++;
            }
            // negatives (y = 0): a standards entry that has learned words of a question that is not its own is pushed down on them
            foreach ($neg as $keys) {
                $cand = [];
                foreach ($keys as $k) {
                    foreach ($index[$k] ?? [] as $kb => $_) {
                        $cand[$kb] = true;
                    }
                }
                foreach (array_slice(array_keys($cand), 0, 250) as $kb) {
                    $z = 0.0;
                    foreach ($keys as $k) {
                        $z += $w[$kb][$k] ?? 0.0;
                    }
                    $e = 1 / (1 + exp(-$z));
                    foreach ($keys as $k) {
                        if (isset($w[$kb][$k])) {
                            $w[$kb][$k] = max(-self::WMAX, min(self::WMAX, $w[$kb][$k] - self::ETA * $e));
                            $touched[$kb][$k] = ($touched[$kb][$k] ?? 0) + 1;
                        }
                    }
                    $negs++;
                }
            }
            if ($ep % 5 === 0 || $ep === $epochs) {
                CLI::write("   epoch {$ep}/{$epochs}", 'light_gray');
            }
        }

        // ------------------------------------------------------------------ write back, in batches
        $rows = [];
        foreach ($touched as $kb => $terms) {
            foreach ($terms as $t => $n) {
                $rows[] = [$kb, $t, $w[$kb][$t], $n];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            $ph  = implode(',', array_fill(0, count($chunk), '(?, ?, ?, ?, ?)'));
            $arg = [];
            foreach ($chunk as [$kb, $t, $wt, $n]) {
                array_push($arg, $kb, $t, $wt, $n, $this->now);
            }
            $this->db->query("INSERT INTO tbl_bot_weights (kb_id, term, w, updates, updated_at) VALUES {$ph} ON DUPLICATE KEY UPDATE w = VALUES(w), updates = updates + VALUES(updates), updated_at = VALUES(updated_at)", $arg);
        }
        CLI::write(count($pos) . " questions x {$epochs} epochs = {$steps} learning steps, {$negs} negative steps; " . count($rows) . ' weights written for ' . count($ids) . ' knowledge entries.', 'green');

        return 0;
    }

    // ====================================================================== entries

    /** @return list<array{id:int,kind:string,title:string,a:string,b:string,w:string}> exactly 1,000 entries (or fewer if the tables hold fewer facts) */
    private function buildEntries(): array
    {
        $db  = $this->db;
        $out = [];
        $add = function (string $kind, string $title, string $body, array $kw, array $meta = []) use (&$out) {
            $title = mb_substr($title, 0, 200);
            if (isset($out[$title]) || count($out) >= 1000) {
                return;
            }
            $kw   = implode(', ', array_slice(array_values(array_unique(array_filter($kw))), 0, 14));
            $data = ['kind' => 'playbook', 'title' => $title, 'keywords' => mb_substr($kw, 0, 600), 'body' => $body, 'steps_json' => '[]', 'actions_json' => '[]',
                'page_match' => null, 'error_match' => null, 'diagnose' => null, 'role_min' => 'user', 'status' => 'approved', 'source' => 'standards_db', 'updated_at' => $this->now];
            $row = $this->db->table('tbl_bot_knowledge')->where('title', $title)->get()->getRowArray();
            if (! $row) {
                $this->db->table('tbl_bot_knowledge')->insert($data + ['created_at' => $this->now]);
                $id = (int) $this->db->insertID();
            } else {
                $id = (int) $row['id'];
                if ($row['source'] === 'standards_db') {
                    $this->db->table('tbl_bot_knowledge')->where('id', $id)->update($data);
                } else {
                    return;                               // never overwrite an entry somebody else owns
                }
            }
            $out[$title] = ['id' => $id, 'kind' => $kind, 'title' => $title, 'a' => (string) ($meta['a'] ?? ''), 'b' => (string) ($meta['b'] ?? ''), 'w' => (string) ($meta['w'] ?? '')];
        };
        $num = static fn ($v) => rtrim(rtrim((string) $v, '0'), '.');

        // a. one entry per material type and test (582)
        $combos = $db->query('SELECT MaterialType, TestMethod, COUNT(*) n FROM tbl_dtr_standards GROUP BY MaterialType, TestMethod ORDER BY n DESC, MaterialType, TestMethod')->getResultArray();
        foreach ($combos as $c) {
            $rows = $db->query('SELECT StandardValue, Tolerance, TMinC, TMaxC FROM tbl_dtr_standards WHERE MaterialType = ? AND TestMethod = ? ORDER BY CAST(TMinC AS DECIMAL(10,2))', [$c['MaterialType'], $c['TestMethod']])->getResultArray();
            $words = $this->methodWords($c['TestMethod']);
            $lines = [];
            foreach ($rows as $r) {
                $all = $r['TMinC'] === null || $r['TMinC'] === '' || ((float) $r['TMinC'] <= 0.01 && (float) $r['TMaxC'] >= 999);
                $lines[] = '- ' . ($all ? 'for every thickness' : 'when the average thickness (TAve) is between ' . $num($r['TMinC']) . ' and ' . $num($r['TMaxC'])) . ': standard value ' . $num($r['StandardValue'])
                    . ($r['Tolerance'] !== null && $r['Tolerance'] !== '' ? ' (tolerance ' . $r['Tolerance'] . ')' : '');
            }
            $add('std', 'Standard: ' . $words . ' - ' . $c['MaterialType'] . ' (' . $c['TestMethod'] . ')',
                "Acceptance standard of NEXUS-L (table tbl_dtr_standards) for the test \"{$c['TestMethod']}\" ({$words}) on the material type \"{$c['MaterialType']}\". The measured result of a report is compared with this standard to rate Pass / Fail / Tolerable:\n" . implode("\n", $lines),
                [$words . ' ' . $c['MaterialType'], $c['TestMethod'] . ' ' . $c['MaterialType'], 'standard for ' . $words . ' of ' . $c['MaterialType'], $c['MaterialType'] . ' ' . $words . ' requirement', 'limit ' . $words . ' ' . $c['MaterialType'], $words . ' pass criteria ' . $c['MaterialType']],
                ['a' => $c['MaterialType'], 'b' => $c['TestMethod'], 'w' => $words]);
        }

        // b. per test: which materials have a standard
        foreach ($db->query('SELECT TestMethod, GROUP_CONCAT(DISTINCT MaterialType ORDER BY MaterialType SEPARATOR "; ") m, COUNT(DISTINCT MaterialType) n FROM tbl_dtr_standards GROUP BY TestMethod ORDER BY TestMethod')->getResultArray() as $r) {
            $words = $this->methodWords($r['TestMethod']);
            $add('test', 'Standards: materials that have a ' . $words . ' standard (' . $r['TestMethod'] . ')', "The test \"{$r['TestMethod']}\" ({$words}) has an acceptance standard in tbl_dtr_standards for {$r['n']} material type(s): " . mb_substr($r['m'], 0, 1500) . '.',
                [$words . ' materials with a standard', 'which materials have a ' . $words . ' standard', $r['TestMethod'] . ' materials', 'materials tested for ' . $words], ['a' => '', 'b' => $r['TestMethod'], 'w' => $words]);
        }
        // c. per material: which tests have a standard
        foreach ($db->query('SELECT MaterialType, GROUP_CONCAT(DISTINCT TestMethod ORDER BY TestMethod SEPARATOR "; ") t, COUNT(DISTINCT TestMethod) n FROM tbl_dtr_standards GROUP BY MaterialType ORDER BY MaterialType')->getResultArray() as $r) {
            $add('mat', 'Standards: tests that have a standard for ' . $r['MaterialType'], "The material type \"{$r['MaterialType']}\" has acceptance standards in tbl_dtr_standards for {$r['n']} test(s): " . mb_substr($r['t'], 0, 1500) . '.',
                ['tests for ' . $r['MaterialType'], 'which tests apply to ' . $r['MaterialType'], $r['MaterialType'] . ' test list', $r['MaterialType'] . ' standards'], ['a' => $r['MaterialType'], 'b' => '', 'w' => '']);
        }
        // d. bonding limits per style group
        foreach ($db->query('SELECT * FROM tbl_bonding_test_std ORDER BY ID')->getResultArray() as $r) {
            $add('bond', 'Bonding limits: ' . $r['StyleLabel'] . ' (' . $r['StyleCode'] . ')', "Shoe bonding limits for {$r['StyleLabel']} ({$r['StyleGroup']}, style code {$r['StyleCode']}): Pass from {$r['PassMin']}; Tolerable from {$r['ToleMin']}; the points-only band is {$r['PointsOnlyMin']} to {$r['PointsOnlyMax']} with at most {$r['MaxPointsAllowed']} such point(s) allowed; Fail at {$r['FailMax']} or below.",
                ['bonding limit ' . $r['StyleLabel'], 'bonding pass minimum ' . $r['StyleLabel'], $r['StyleLabel'] . ' bonding standard', 'bonding ' . $r['StyleCode'], 'bonding tolerable ' . $r['StyleLabel']], ['a' => $r['StyleLabel'], 'b' => $r['StyleCode'], 'w' => '']);
        }
        // e. lab form numbers
        foreach ($db->query('SELECT DISTINCT FormNo, TestForm FROM tbl_dtr_form_no ORDER BY FormNo') ->getResultArray() as $r) {
            $f = trim((string) $r['TestForm']);
            $add('form', 'Lab form number: ' . trim($r['FormNo']) . ' = ' . $f, "The laboratory form number {$r['FormNo']} is the test form for: {$f}.", [trim($r['FormNo']), $f . ' form number', 'which form is used for ' . $f, 'form no ' . $f], ['a' => $f, 'b' => trim($r['FormNo']), 'w' => '']);
        }
        // f. test form controls
        foreach ($db->query('SELECT TestItem, GROUP_CONCAT(DISTINCT CtrlKey SEPARATOR ", ") k, GROUP_CONCAT(DISTINCT CtrlRating SEPARATOR ", ") r FROM tbl_dtr_field_map GROUP BY TestItem ORDER BY TestItem')->getResultArray() as $r) {
            $add('field', 'Test form fields: ' . $r['TestItem'], "On the test form, the test item \"{$r['TestItem']}\" uses the input field(s) {$r['k']}" . ($r['r'] ? " and the rating field {$r['r']}" : '') . '.',
                [$r['TestItem'] . ' fields', $r['TestItem'] . ' input', $r['TestItem'] . ' rating field', 'where do i enter ' . $r['TestItem']], ['a' => $r['TestItem'], 'b' => '', 'w' => '']);
        }
        // g. VF test method catalog
        foreach ($db->query('SELECT code, title, category, revision_date, file_ext FROM tbl_vf_test_methods ORDER BY code, id')->getResultArray() as $r) {
            $add('vfc', 'VF method catalog: ' . $r['code'] . ' - ' . $r['title'], "The VF test method catalog lists {$r['code']} \"{$r['title']}\" in the category \"{$r['category']}\"" . ($r['revision_date'] ? ", revision date {$r['revision_date']}" : '') . ", as a {$r['file_ext']} file.",
                ['catalog ' . $r['code'] . ' ' . $r['title'], $r['title'] . ' revision date', $r['title'] . ' category', $r['code'] . ' file type'], ['a' => $r['title'], 'b' => $r['code'], 'w' => (string) $r['category']]);
        }
        // h. material types of the material list
        foreach ($db->query('SELECT MaterialType, COUNT(*) n FROM tbl_dtr_material_type_status GROUP BY MaterialType ORDER BY MaterialType')->getResultArray() as $r) {
            $add('mlist', 'Material list: type ' . $r['MaterialType'], "The material list (tbl_dtr_material_type_status) holds {$r['n']} material code(s) of the type \"{$r['MaterialType']}\".", ['material type ' . $r['MaterialType'], 'how many ' . $r['MaterialType'] . ' materials', $r['MaterialType'] . ' material codes'], ['a' => $r['MaterialType'], 'b' => '', 'w' => '']);
        }
        // i. per category of the VF catalog
        foreach ($db->query('SELECT category, COUNT(*) n FROM tbl_vf_test_methods GROUP BY category ORDER BY category')->getResultArray() as $r) {
            $add('vfcat', 'VF method catalog: category ' . $r['category'], "The VF test method catalog has {$r['n']} file(s) in the category \"{$r['category']}\".", ['vf category ' . $r['category'], 'how many methods in ' . $r['category'], $r['category'] . ' test methods'], ['a' => $r['category'], 'b' => '', 'w' => '']);
        }

        return array_values($out);
    }

    /** plain text of the content blocks of a VF method (tables and paragraphs stored as html) */
    private function blockText($v): string
    {
        if (is_string($v)) {
            $t = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>', '</tr>', '</td>'], "\n", $v)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n\s*\n+/", "\n", $t)));
        }
        if (! is_array($v)) {
            return '';                                  // numbers and flags carry no text
        }
        $out = [];
        foreach ($v as $k => $x) {
            if (in_array($k, ['t', 'box', 'base', 'pages', 'margin', 'w', 'h'], true)) {
                continue;
            }
            $s = $this->blockText($x);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return implode("\n", $out);
    }

    /** entries from the text of the VF test methods: each method cut into parts of about 700 characters */
    private function buildVfEntries(): array
    {
        $out = [];
        $sec = ['scope', 'purpose', 'apparatus', 'equipment', 'specimen', 'sample', 'procedure', 'calculation', 'report', 'safety', 'conditioning', 'definition', 'reference', 'machine', 'speed', 'result', 'criteria', 'preparation'];
        foreach ($this->db->query("SELECT code, title, rev_label, series, content_json FROM tbl_vfm_methods WHERE content_json IS NOT NULL AND content_json <> '' ORDER BY code, id")->getResultArray() as $m) {
            $j = json_decode($m['content_json'], true);
            if (! is_array($j)) {
                continue;
            }
            $text  = $this->blockText($j['blocks'] ?? []);
            $paras = array_values(array_filter(array_map('trim', preg_split("/\n+/", $text)), static fn ($l) => mb_strlen($l) >= 3));
            $chunk = '';
            $parts = [];
            foreach ($paras as $l) {
                if (mb_strlen($chunk) + mb_strlen($l) > 380 && $chunk !== '') {
                    $parts[] = $chunk;
                    $chunk   = '';
                }
                $chunk .= ($chunk === '' ? '' : "\n") . $l;
            }
            if ($chunk !== '') {
                $parts[] = $chunk;
            }
            foreach (array_slice($parts, 0, 14) as $i => $c) {
                $w = 'part ' . ($i + 1);
                foreach ($sec as $sw) {
                    if (stripos($c, $sw) !== false) {
                        $w = $sw . ' part ' . ($i + 1);          // the part number keeps every question of the method different
                        break;
                    }
                }
                $title = mb_substr('VF method text: ' . $m['code'] . ' ' . $m['title'] . ' (' . $w . ')', 0, 200);
                $this->putEntry($out, 'vft', $title, "From the VF test method {$m['code']} \"{$m['title']}\"" . ($m['rev_label'] ? " ({$m['rev_label']})" : '') . ", part " . ($i + 1) . ":\n" . $c,
                    [$m['code'] . ' ' . $m['title'] . ' ' . $w, $m['title'] . ' ' . $w, $m['code'] . ' ' . $w, 'vf ' . $m['code'] . ' part ' . ($i + 1)], ['a' => $m['title'], 'b' => $m['code'], 'w' => $w]);
                if (count($out) >= 1000) {
                    return array_values($out);
                }
            }
        }

        return array_values($out);
    }

    /** entries from the lab document registers: one per document book, with its register, file and sheet names */
    private function buildLabEntries(): array
    {
        $out = [];
        $q   = $this->db->query('SELECT b.id, b.label, b.file_name, b.sheet_count, r.code, r.menu_label FROM tbl_labdoc_books b JOIN tbl_labdoc_registers r ON r.id = b.register_id ORDER BY r.sort_order, b.id');
        foreach ($q->getResultArray() as $b) {
            $names = array_column($this->db->query('SELECT name FROM tbl_labdoc_sheets WHERE book_id = ? ORDER BY idx LIMIT 12', [$b['id']])->getResultArray(), 'name');
            $label = trim((string) ($b['label'] ?: $b['file_name']));
            $title = mb_substr('Lab document: ' . $label . ' (' . $b['menu_label'] . ')', 0, 200);
            $this->putEntry($out, 'lab', $title, "The lab document \"{$label}\" is in the register \"{$b['menu_label']}\" ({$b['code']}) of the Lab Documents menu, as the file {$b['file_name']} with {$b['sheet_count']} sheet(s)" . ($names ? ': ' . implode(', ', $names) . '.' : '.'),
                [$label, $label . ' ' . $b['menu_label'], $b['file_name'], 'lab document ' . $label, $b['menu_label'] . ' ' . $label], ['a' => $label, 'b' => $b['code'], 'w' => '']);
            if (count($out) >= 1000) {
                break;
            }
        }

        return array_values($out);
    }

    /** one entry per lab document file (every k-th of the files on record, so the registers and folders are all represented) */
    private function buildLabFileEntries(): array
    {
        $out  = [];
        $rows = $this->db->query('SELECT f.name, f.ext, f.folder, f.file_size, r.menu_label FROM tbl_labdoc_files f JOIN tbl_labdoc_registers r ON r.id = f.register_id WHERE f.deleted_at IS NULL AND f.name <> "" ORDER BY f.register_id, f.folder, f.name')->getResultArray();
        $step = max(1, (int) floor(count($rows) / 1000));
        foreach ($rows as $i => $f) {
            if ($i % $step !== 0) {
                continue;
            }
            $file  = trim($f['name']) . (str_contains($f['name'], '.') || $f['ext'] === '' ? '' : '.' . $f['ext']);
            $where = trim($f['menu_label'] . ($f['folder'] !== '' ? ' / ' . $f['folder'] : ''));
            $title = mb_substr('Lab file: ' . $file . ' (' . $where . ')', 0, 200);
            $this->putEntry($out, 'file', $title, "The file \"{$file}\" is in the Lab Documents register \"{$f['menu_label']}\"" . ($f['folder'] !== '' ? ", folder \"{$f['folder']}\"" : '') . ($f['file_size'] ? ', size ' . round($f['file_size'] / 1024) . ' KB' : '') . '.',
                [$file, pathinfo($file, PATHINFO_FILENAME), $f['menu_label'] . ' ' . pathinfo($file, PATHINFO_FILENAME), 'file ' . $file], ['a' => pathinfo($file, PATHINFO_FILENAME), 'b' => $file, 'w' => '']);
            if (count($out) >= 1000) {
                break;
            }
        }

        return array_values($out);
    }

    /** writes one entry of the standards_db source and records it in $out (never replaces an entry another source owns) */
    private function putEntry(array &$out, string $kind, string $title, string $body, array $kw, array $meta): void
    {
        if (isset($out[$title])) {
            return;
        }
        $kw   = implode(', ', array_slice(array_values(array_unique(array_filter($kw))), 0, 14));
        $data = ['kind' => 'playbook', 'title' => $title, 'keywords' => mb_substr($kw, 0, 600), 'body' => $body, 'steps_json' => '[]', 'actions_json' => '[]',
            'page_match' => null, 'error_match' => null, 'diagnose' => null, 'role_min' => 'user', 'status' => 'approved', 'source' => 'standards_db', 'updated_at' => $this->now];
        $row = $this->db->table('tbl_bot_knowledge')->where('title', $title)->get()->getRowArray();
        if (! $row) {
            $this->db->table('tbl_bot_knowledge')->insert($data + ['created_at' => $this->now]);
            $id = (int) $this->db->insertID();
        } elseif ($row['source'] === 'standards_db') {
            $id = (int) $row['id'];
            $this->db->table('tbl_bot_knowledge')->where('id', $id)->update($data);
        } else {
            return;
        }
        $out[$title] = ['id' => $id, 'kind' => $kind, 'title' => $title, 'a' => (string) ($meta['a'] ?? ''), 'b' => (string) ($meta['b'] ?? ''), 'w' => (string) ($meta['w'] ?? '')];
    }

    /** readable words for a code like BallyDryL_S, Tensile_L, CrockingDry_Std */
    private function methodWords(string $code): string
    {
        $base = preg_replace('/_(S|Std|std|STD)$/', '', $code);
        $dir  = '';
        if (preg_match('/_(L|W)$/', $base, $m)) {
            $dir  = $m[1] === 'L' ? ' lengthwise' : ' widthwise';
            $base = substr($base, 0, -2);
        }
        foreach (self::ALIAS as $k => $v) {
            if (stripos($base, $k) === 0) {
                $rest = trim(preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', substr($base, strlen($k))));

                return trim(mb_strtolower($v . ' ' . $rest . $dir));
            }
        }

        return trim(mb_strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', str_replace('_', ' ', $base)) . $dir));
    }

    // ====================================================================== questions

    /** 5,000 unique technical questions over the 1,000 entries: 5 per entry, each from the question styles of its own kind */
    private function technical(array $entries): array
    {
        $t = [
            'std'  => ['what is the standard for {w} of {a}', 'what is the requirement for {w} on {a}', '{a} {w} standard', '{w} limit for {a}', 'pass criteria {w} {a}', 'acceptance value {w} {a}', 'what value must {a} reach in {w}',
                'how much is the {w} standard for {a}', 'what is the {b} standard for {a}', '{b} requirement {a}', 'check the {w} requirement of {a}', 'what does {a} need for {w}', 'what is the passing value of {w} for {a}',
                'what is the specification of {w} on {a}', '{w} spec {a}', 'standard value {b} {a}', 'what is the tolerance of {w} for {a}', 'which standard applies to {w} of {a}', 'give me the {w} limit of {a}',
                'what is the minimum for {w} of {a}', 'how is {w} judged for {a}', 'dtr standard {w} {a}', 'what is the cutoff for {w} on {a}', 'when does {a} pass {w}', 'what is the threshold of {w} for {a}',
                'what is the expected {w} of {a}', 'qc limit {w} {a}', 'show the {w} standard for {a}', 'what is the benchmark for {w} {a}', 'rating criteria {w} {a}',
                'what {w} value does {a} need', 'what value does {a} need for {w}', 'what {w} does {a} need', 'how much {w} must {a} have', 'what {w} should {a} reach', 'minimum {w} value for {a}', '{a} needs what {w}'],
            'test' => ['which materials have a {w} standard', 'what materials are tested for {w}', 'list the materials with a {b} standard', 'which material types need {w}', 'where is {w} required', 'materials covered by the {w} standard',
                'what can be rated with {b}', 'for which materials does {w} apply', '{w} applies to which materials', 'which materials does the {w} limit cover'],
            'mat'  => ['which tests have a standard for {a}', 'what tests apply to {a}', 'list the tests for {a}', 'what is {a} tested for', 'what standards exist for {a}', '{a} test list', 'which standards does {a} have',
                'what do we test on {a}', 'what must {a} pass', 'which test limits exist for {a}'],
            'bond' => ['what is the bonding pass minimum for {a}', 'bonding limits for {a}', 'when does {a} bonding fail', 'what is the tolerable bonding value for {a}', 'how many bonding points are allowed for {a}', '{a} bonding standard',
                'what is the points only band for {a}', 'bonding {b} pass value', 'what bonding value is a fail for {a}', 'bonding criteria {a}'],
            'form' => ['which form number is used for {a}', 'what is the lab form for {a}', 'what is the form no of {a}', '{b} is which test form', 'what is form {b}', 'which lab form do i use for {a}', 'form number {a}',
                'what test is {b} for', 'find the form for {a} testing', 'what does {b} cover'],
            'field' => ['where do i enter {a}', 'which fields does {a} use on the form', 'what is the rating field for {a}', '{a} input fields', 'how is {a} recorded on the test form', 'what controls does {a} have', '{a} form fields',
                'where is {a} rated', 'which input is {a}', 'fields used for {a}'],
            'vfc'  => ['what is the revision date of {a}', 'which category is {a} in', 'what file type is {b}', 'when was {a} revised', '{a} revision date', '{b} catalog entry', 'what category does {a} belong to',
                'what kind of file is {a}', 'catalog entry for {a}', 'which vf series is {a}'],
            'mlist' => ['how many {a} materials are in the material list', 'how many material codes are {a}', 'material list {a} count', 'how many materials of type {a}', '{a} materials in the list'],
            'vft'  => ['what does {b} say about {w}', '{b} {a} {w}', '{a} test method {w}', 'vf method {b} {w}', 'what is in the {w} of {a}', '{a} {w} requirements', 'tell me the {w} of {b}',
                'how does {b} describe the {w}', 'method {b} section {w}', '{w} for the {a} test', 'what do i need to know about {w} in {b}', 'vf {b} {w}', '{a} procedure {w}', 'read me the {w} of {a}', '{b} instructions {w}'],
            'file' => ['where is the file {b}', 'which folder has {b}', 'what register holds {b}', 'find the file {b}', 'open {b}', 'what type of file is {b}', '{b} file location', 'is {b} in lab documents',
                'download {b}', 'show me the file {a}', 'where is {a}', 'which register has the file {a}'],
            'lab'  => ['where is the document {a}', 'which register has {a}', 'how many sheets does {a} have', 'what file is {a}', 'find {a} in lab documents', 'open {a}', 'which sheets are in {a}', '{a} lab document',
                'where do i find {a}', 'what is {a} in the lab documents', 'which lab document folder holds {a}', '{a} excel file', 'is there a lab document called {a}', '{a} register', 'show me {a}'],
            'vfcat' => ['how many methods are in {a}', '{a} how many files', 'how many vf files in the category {a}', 'size of the {a} category', '{a} category count'],
        ];
        $out = [];
        foreach ($entries as $i => $e) {
            $tpls = $t[$e['kind']] ?? $t['std'];
            for ($j = 0, $n = 0; $n < 5 && $j < count($tpls) * 2; $j++) {
                $tpl = $tpls[($i * 3 + $j * 7) % count($tpls)];
                $q   = str_replace(['{w}', '{a}', '{b}'], [$e['w'] !== '' ? $e['w'] : $e['a'], mb_strtolower($e['a']), $e['b']], $tpl);
                if (isset($out[mb_strtolower($q)])) {
                    continue;
                }
                $out[mb_strtolower($q)] = ['q' => $q, 'id' => $e['id']];
                $n++;
            }
        }

        return array_slice(array_values($out), 0, 5000);
    }

    /** the how-to playbooks of the system itself: the system tasks, the lab workflows and the newest feature entries (not the 1,000 standalone seeds) */
    private function canonicalIds(): array
    {
        $titles = [];
        foreach (array_merge(BrainSeed10::all(), BrainSeed11::all()) as $e) {
            $titles[mb_substr($e['title'], 0, 200)] = true;
        }
        $ids = [];
        foreach ($this->db->query("SELECT id, title, keywords, source FROM tbl_bot_knowledge WHERE status = 'approved' AND role_min = 'user' AND keywords <> ''")->getResultArray() as $r) {
            $repo = $r['source'] === 'workflow_seed' && preg_match('#\([\w.-]+/[\w.-]+\)#', $r['title']);   // the github-repo notes are not how-to questions
            if (! $repo && (in_array($r['source'], ['system_workflow', 'workflow_seed'], true) || isset($titles[$r['title']]))) {
                $ids[(int) $r['id']] = $r['keywords'];
            }
        }

        return $ids;
    }

    /** 5,000 unique everyday questions, spread evenly over the how-to playbooks */
    private function general(int $setNo = 0): array
    {
        $pre = ['', 'how do i ', 'where can i find ', 'please help me ', 'i want to ', 'can you show me ', 'how to ', 'where is ', 'i need help with ', 'what is the way to ', 'hi, ', 'how can i ', 'i cannot find ',
            'quick question about ', 'teach me ', 'steps for ', 'what is ', 'is it possible to ', 'what should i do about ', 'show me ', 'can i ', 'help, ', 'nexus, ', 'new here: ',
            'i forgot how to ', 'my supervisor asked me to ', 'urgent: ', 'where do i start with ', 'guide me to ', 'any tips on ', 'explain how to ', 'looking for ', 'do you know how to ', 'is there a page to '];
        $suf = ['', ' in nexus', ' in the system', ' please', ' step by step', ' for beginners', ' on the website', ' quickly', ' today', ' again', ' thanks', ' i am new', ' on my phone', ' for the lab'];
        $phr = [];
        foreach ($this->canonicalIds() as $id => $kw) {
            foreach (array_filter(array_map('trim', explode(',', $kw))) as $p) {
                if (mb_strlen($p) >= 4 && mb_strlen($p) <= 60) {
                    $phr[] = [$id, $p];
                }
            }
        }
        $out = [];
        $n   = count($phr);
        $off = $setNo * 7000;                          // each set walks a different part of the phrase / opening / ending combinations
        for ($i = $off; $n > 0 && $i < $off + $n * 30 && count($out) < 5000; $i++) {
            [$id, $p] = $phr[($i * 7919) % $n];
            $q = trim($pre[$i % count($pre)] . $p . $suf[intdiv($i, count($pre)) % count($suf)]);
            $out[mb_strtolower($q)] = ['q' => $q, 'id' => $id];
        }

        return array_values($out);
    }

    /** questions about VF test methods (what the standards entries must NOT take over) */
    private function vfQuestions(): array
    {
        $q = [];
        foreach ($this->db->query("SELECT keywords FROM tbl_bot_knowledge WHERE kind IN ('vf_method','vf_illustrated') AND status = 'approved'")->getResultArray() as $r) {
            foreach (array_filter(array_map('trim', explode(',', (string) $r['keywords']))) as $i => $p) {
                if ($i < 5 && mb_strlen($p) >= 6) {
                    $q[] = $p;
                    $q[] = 'how to run ' . $p;
                }
            }
        }

        return array_slice($q, 0, 1500);
    }

    /** 2,500 unique "generate / print / download / export a report" questions mapped to the matching report playbook */
    private function reports(int $setNo = 0): array
    {
        $db   = $this->db;
        $find = static function ($db, string $like): int {
            $r = $db->query("SELECT id FROM tbl_bot_knowledge WHERE status = 'approved' AND title LIKE ? ORDER BY id LIMIT 1", [$like])->getRowArray();

            return $r ? (int) $r['id'] : 0;
        };
        $pdf    = $find($db, 'How to Generate, Download & Print Official PDF Reports%');
        $pdf2   = $find($db, 'How to Generate, Preview & Download Laboratory PDF Reports%');
        $lexus  = $find($db, 'How to Generate PowerPoint, Excel & PDF Reports with LEXUS AI%');
        $export = $find($db, 'How to Export Laboratory Summary%');
        $design = $find($db, 'How to Customize PDF Report Layouts%');
        $send   = $find($db, 'How to Send Reports & Synology Approval Notifications%');
        $inter  = $find($db, 'How to Auto-Fill & Submit Reports to Intertek InterLink%');
        $verbs  = ['generate', 'create', 'make', 'print', 'download', 'export', 'preview', 'produce', 'get', 'open', 'prepare', 'issue', 'build', 'save'];
        $objs   = ['bonding report', 'whole shoe report', 'fabric report', 'leather report', 'synthetic leather report', 'rubber report', 'pull strength report', 'material test report', 'insole report',
            'hardware report', 'label report', 'zipper report', 'thread report', 'velcro report', 'webbing report', 'box report', 'lab report', 'test report', 'dtr report', 'summary report',
            'official report', 'pdf report', 'laboratory report', 'vf report'];
        $quals  = ['', ' for a lab reference', ' as pdf', ' for today', ' with signatures', ' with the qr code', ' for a style', ' in nexus'];
        $out    = [];
        foreach (['', 'how do i ', 'please ', 'can you '] as $pi => $pfx) {
        foreach ($objs as $oi => $o) {
            foreach ($verbs as $vi => $v) {
                foreach ($quals as $qi => $ql) {
                    $q  = trim($pfx . $v . ' ' . $o . $ql);
                    $id = $pdf;
                    if (str_contains($ql, 'qr') || str_contains($ql, 'signatures')) {
                        $id = $send ?: $pdf;
                    } elseif ($v === 'export') {
                        $id = $export ?: $pdf;
                    } elseif ($v === 'preview') {
                        $id = $pdf2 ?: $pdf;
                    } elseif (($oi + $vi + $qi) % 5 === 0) {
                        $id = $pdf2 ?: $pdf;
                    }
                    if ($id) {
                        $out[mb_strtolower($q)] = ['q' => $q, 'id' => $id];
                    }
                }
            }
        }
        }
        $extra = [['powerpoint report', $lexus], ['excel report', $lexus], ['slides of the test results', $lexus], ['chart of the results', $lexus], ['change the report layout', $design], ['move the elements on the report', $design],
            ['report designer', $design], ['send the report to interlink', $inter], ['send the report for approval', $send], ['export the lab summary to excel', $export]];
        foreach ($extra as [$ph, $id]) {
            foreach (['', 'how do i ', 'how to ', 'please ', 'can you ', 'i want to ', 'help me ', 'where do i '] as $p) {
                foreach (['', ' in nexus', ' step by step', ' now'] as $s) {
                    if ($id) {
                        $q = trim($p . $ph . $s);
                        $out[mb_strtolower($q)] = ['q' => $q, 'id' => $id];
                    }
                }
            }
        }

        $all = array_values($out);
        mt_srand(42);
        shuffle($all);                                  // a fixed shuffle, so every set gets its own mixed 2,500

        return array_slice($all, $setNo * 2500, 2500);
    }
}
