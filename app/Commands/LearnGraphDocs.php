<?php

namespace App\Commands;

use App\Libraries\Brain;
use App\Libraries\BrainLearn;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Teaches the AI Brain how the system fits together, from the three places that already describe it:
 *   1. the Graphify knowledge graph (graphify-out/graph.json: which class calls, extends or is documented with which),
 *   2. the Obsidian notes (_docs/ICM_CHANGELOG.md = the history of every phase, _docs/OBSIDIAN_CODEBASE_INDEX.md = what each file is for),
 *   3. a few written "flow" entries (report -> approval -> InterLink, eTRF -> service -> callback, the AI service stack).
 * Everything lands in tbl_bot_knowledge as source 'graph_obsidian' (admin level: it describes the code, not the lab work), so running it
 * again refreshes the same entries. Lines that look like a password, key or token are never copied. Then every entry is trained on
 * question variants, like brain:train-new. Run `php spark brain:train` afterwards to rebuild the index of the AI service.
 */
class LearnGraphDocs extends BaseCommand
{
    protected $group       = 'Brain';
    protected $name        = 'brain:learn-graph';
    protected $description = 'Teaches the AI Brain the system flow from the Graphify graph and the Obsidian notes (ICM changelog and codebase index).';
    protected $usage       = 'brain:learn-graph [epochs]';

    private const SECRET = '/(pass(word|wd)?|secret|api[_ -]?key|token|shared key)\s*[:=]/i';

    public function run(array $params)
    {
        $epochs = max(1, min(30, (int) ($params[0] ?? 10)));
        $db     = \Config\Database::connect();
        $now    = date('Y-m-d H:i:s');
        CLI::write('=== Learning the system flow from Graphify and Obsidian ===', 'cyan');

        $entries = array_merge($this->flowEntries(), $this->indexEntries(), $this->changelogEntries(), $this->graphEntries());
        $add = $upd = 0;
        $ids = [];
        foreach ($entries as $e) {
            $data = [
                'kind' => 'playbook', 'title' => mb_substr($e['title'], 0, 200), 'keywords' => mb_substr($e['keywords'], 0, 600), 'body' => $e['body'],
                'steps_json' => json_encode($e['steps'] ?? [], JSON_UNESCAPED_UNICODE), 'actions_json' => '[]', 'page_match' => null, 'error_match' => null, 'diagnose' => null,
                'role_min' => 'admin', 'status' => 'approved', 'source' => 'graph_obsidian', 'updated_at' => $now,
            ];
            $row = $db->table('tbl_bot_knowledge')->where('title', $data['title'])->get()->getRowArray();
            if (! $row) {
                $db->table('tbl_bot_knowledge')->insert($data + ['created_at' => $now]);
                $ids[$data['title']] = (int) $db->insertID();
                $add++;
            } elseif ($row['source'] === 'graph_obsidian') {
                $db->table('tbl_bot_knowledge')->where('id', $row['id'])->update($data);
                $ids[$data['title']] = (int) $row['id'];
                $upd++;
            }
        }
        CLI::write("Knowledge: {$add} added, {$upd} refreshed (" . count($entries) . ' entries: flow, file index, history, code map).', 'green');

        // question variants of every keyword phrase, learned over several epochs
        $prefixes = ['', 'how does ', 'what is ', 'explain ', 'where is ', 'how is ', 'what does ', 'tell me about '];
        $suffixes = ['', ' work', ' do', ' connect'];
        $dataset  = [];
        foreach ($entries as $e) {
            $id = $ids[mb_substr($e['title'], 0, 200)] ?? 0;
            if (! $id) {
                continue;
            }
            $phrases = array_values(array_filter(array_map('trim', explode(',', $e['keywords']))));
            foreach ($phrases as $i => $ph) {
                for ($j = 0; $j < 4; $j++) {
                    $n = ($i * 3 + $j * 5 + $id) % (count($prefixes) * count($suffixes));
                    $dataset[mb_strtolower(trim($prefixes[intdiv($n, count($suffixes))] . $ph . $suffixes[$n % count($suffixes)]))] = ['q' => trim($prefixes[intdiv($n, count($suffixes))] . $ph . $suffixes[$n % count($suffixes)]), 'id' => $id];
                }
            }
        }
        $dataset = array_values($dataset);
        CLI::write('Prepared ' . count($dataset) . ' question variants.', 'green');
        $updates = 0;
        for ($ep = 1; $ep <= $epochs; $ep++) {
            foreach ($dataset as $d) {
                $terms = Brain::tokens($d['q']);
                if ($terms && BrainLearn::train($terms, $d['id'], 1) !== null) {
                    $updates += count($terms) + 1;
                }
            }
        }
        CLI::write('Trained ' . count($dataset) . " questions x {$epochs} epochs ({$updates} weight updates).", 'green');
    }

    /** removes lines that look like credentials and markdown noise */
    private function clean(string $t, int $max): string
    {
        $out = [];
        foreach (preg_split('/\R/', $t) as $l) {
            if (preg_match(self::SECRET, $l)) {
                continue;
            }
            $out[] = rtrim(preg_replace('/\*\*|`/', '', $l));
        }
        $s = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)));

        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max)) . ' ...' : $s;
    }

    private function words(string $label): string
    {
        $w = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $label);

        return mb_strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', ' ', $w)));
    }

    /** @return list<array<string,mixed>> */
    private function flowEntries(): array
    {
        return [
            ['title' => 'System flow: how a lab report goes from the machine to InterLink',
                'keywords' => 'system flow, report flow, from machine to interlink, how a report moves, report lifecycle, test to report to interlink, flow of a test report, end to end flow, report approval to interlink',
                'body' => "1. The machine file is uploaded and imported (Upload & Import Raw Machine) and becomes a Digital Test Report record in the DTR module.\n2. The report is generated and checked in the DTR report view.\n3. It is sent for approval: the Manager or Supervisor gets a Synology message with a QR link that expires, reviews and signs.\n4. Only a report that is approved and signed can be sent to InterLink. NEXUS-L prepares the values of the Lab Reference and the signed PDF and hands them to the NEXUS-L InterLink background service (a C# tray program with an invisible browser), which asks NEXUS-L once more to confirm the approval right before it presses Save.\n5. The service does Add or Edit in InterLink, then reports back to NEXUS-L, which shows a toast and sends a Synology message with the Application No. / Report No.\n6. The Report Submission grid (InterLink Sync) shows the application and its status.",
                'steps' => ['Import machine data', 'Generate the report', 'Send for approval', 'Send to InterLink', 'Check the status in Report Submission']],
            ['title' => 'System flow: how an eTRF request is created in InterLink',
                'keywords' => 'etrf flow, how etrf is created, etrf to interlink flow, etrf service flow, new test request flow, etrf callback, etrf lifecycle',
                'body' => "On InterLink > VF eTRF the user picks one of the templates, a Lab Reference or typed values, and optionally looks up the season (Order Summary), the supplier (InterLink pick lists) and the BOM (DSR / CFM). NEXUS-L checks the request (nothing is invented; missing data is listed), saves it in tbl_interlink_etrf_requests and queues a job at the background service chosen in Settings > Service (.43, .45 or custom). The service signs in with the right account (dtp_qcasst for Material / Product / bonding / Whole Shoe, dtp_lab for PULLTEST), opens Home > Submit a Request > the exact template, fills the form, reads it back, then Saves a draft or presses Print & Submit and reads the Application No. It reports the result to NEXUS-L, which updates the request table and tells the user.",
                'steps' => ['Pick template and Lab Ref', 'Check season, supplier, BOM', 'Save draft or Submit', 'Service fills and sends in InterLink', 'Result and Application No. come back']],
            ['title' => 'System flow: how the AI Brain answers a question',
                'keywords' => 'ai brain flow, how the bot answers, brain flow, how the ai works, ai service stack, lexis flow, bot answer flow, two brains',
                'body' => "A question goes to BotController. The primary brain is the Python AI service (port 8765: LangGraph, retrieval over the approved knowledge and the lab documents, optional Rasa routing, Phoenix traces, PostgreSQL audit store); when it does not answer, the PHP Brain (tbl_bot_knowledge with term weights and TF-IDF) answers. Knowledge is added by the seeds (php spark brain:seed), trained with brain:train, brain:train-system, brain:train-vf, brain:train-new and brain:learn-graph, then re-indexed into the AI service. The Server HTML Handler keeps the AI service running, and the AI Brain switch under the page header turns the assistant on or off.",
                'steps' => ['Question reaches BotController', 'AI service answers, or the PHP Brain does', 'Admin reviews unanswered questions in the Brain console']],
            ['title' => 'System flow: how the system keeps itself running',
                'keywords' => 'server html handler, what the handler does, apache watchdog, ai service watchdog, auto start apache, handler tray app, what starts on boot, system services',
                'body' => "The Server HTML Handler is a tray program on the server PC. It keeps the XAMPP Apache serving NEXUS-L (and restarts it when its configuration changes), keeps the AI service stack running (PostgreSQL 5440, Phoenix 6006, the assistant service 8765), and runs the other background services (e-mail outbox, notifications, call signalling, share control). The NEXUS-L InterLink background service is a separate tray program, installed on the computer chosen in Settings > Service.",
                'steps' => []],
        ];
    }

    /** one entry per row of the Obsidian codebase index table: what a file is for */
    private function indexEntries(): array
    {
        $p = ROOTPATH . '_docs/OBSIDIAN_CODEBASE_INDEX.md';
        if (! is_file($p)) {
            return [];
        }
        $out = [];
        foreach (preg_split('/\R/', (string) file_get_contents($p)) as $l) {
            if (! preg_match('/^\|\s*`([^`]+)`\s*\|\s*(.+?)\s*\|\s*$/', $l, $m)) {
                continue;
            }
            $file = $m[1];
            $base = basename($file);
            $name = pathinfo($base, PATHINFO_FILENAME);
            $out[] = ['title' => 'What does ' . $base . ' do?',
                'keywords' => $this->words($name) . ', ' . $base . ', ' . $file . ', what does ' . $name . ' do, purpose of ' . $name . ', ' . $name . ' responsibility',
                'body' => $base . ' (' . $file . '): ' . $this->clean($m[2], 900)];
        }

        return $out;
    }

    /** one entry per "## Phase N - title" of the ICM changelog: what changed and why, in order */
    private function changelogEntries(): array
    {
        $p = ROOTPATH . '_docs/ICM_CHANGELOG.md';
        if (! is_file($p)) {
            return [];
        }
        $out = [];
        $parts = preg_split('/^## /m', (string) file_get_contents($p));
        array_shift($parts);
        foreach ($parts as $sec) {
            $nl    = strpos($sec, "\n");
            $head  = trim($nl === false ? $sec : substr($sec, 0, $nl));
            $body  = $nl === false ? '' : substr($sec, $nl + 1);
            $title = 'System history: ' . trim(preg_replace('/\s+/', ' ', preg_replace('/[`*]/', '', $head)));
            preg_match('/Phase\s+(\d+)/i', $head, $pm);
            $kw = [];
            if ($pm) {
                $kw[] = 'phase ' . $pm[1];
            }
            $kw[] = mb_strtolower(trim(preg_replace('/\(\d{4}-\d{2}-\d{2}\)|Phase\s+\d+\s*-?/i', '', preg_replace('/[`*]/', '', $head))));
            foreach (preg_split('/\R/', $body) as $l) {      // the bold lead of each bullet names a feature
                if (preg_match('/^\s*[-*]\s+\*\*([^*:]{6,70})/', $l, $bm)) {
                    $kw[] = mb_strtolower(trim($bm[1]));
                }
            }
            $out[] = ['title' => $title, 'keywords' => implode(', ', array_slice(array_unique(array_filter($kw)), 0, 12)), 'body' => $this->clean($body, 3000)];
        }

        return $out;
    }

    /** one "system map" entry for each of the most connected classes / files of the Graphify graph */
    private function graphEntries(): array
    {
        $p = ROOTPATH . 'graphify-out/graph.json';
        if (! is_file($p)) {
            return [];
        }
        $g = json_decode((string) file_get_contents($p), true);
        if (! is_array($g) || empty($g['nodes'])) {
            return [];
        }
        $node = [];
        foreach ($g['nodes'] as $n) {
            $node[$n['id']] = $n;
        }
        $deg = [];
        $adj = [];
        foreach ($g['links'] ?? [] as $e) {
            $a = $e['source'] ?? null;
            $b = $e['target'] ?? null;
            if (! isset($node[$a], $node[$b]) || $a === $b) {
                continue;
            }
            $rel = (string) ($e['relation'] ?? 'related');
            if ($rel === 'method') {
                continue;                                    // a method belongs to its class: not a connection worth telling
            }
            $adj[$a][$rel . '>'][$node[$b]['label']] = true;
            $adj[$b][$rel . '<'][$node[$a]['label']] = true;
            $deg[$a] = ($deg[$a] ?? 0) + 1;
            $deg[$b] = ($deg[$b] ?? 0) + 1;
        }
        arsort($deg);
        $words = ['calls>' => 'it calls', 'calls<' => 'it is called by', 'inherits>' => 'it extends', 'inherits<' => 'it is extended by', 'references>' => 'it mentions', 'references<' => 'it is mentioned in', 'imports>' => 'it imports', 'imports<' => 'it is imported by', 'uses>' => 'it uses', 'uses<' => 'it is used by', 'contains>' => 'it contains', 'contains<' => 'it is part of'];
        $out = [];
        foreach ($deg as $id => $d) {
            $n    = $node[$id];
            $file = (string) ($n['source_file'] ?? '');
            $lab  = (string) $n['label'];
            if ($d < 6 || ! preg_match('#^(app|tools|public/assets/js|_docs)/#', $file) || str_contains($lab, '{closure') || str_starts_with($lab, '.') || str_contains($lab, '(')) {
                continue;
            }
            $lines = [];
            foreach ($adj[$id] ?? [] as $rel => $set) {
                $names = array_keys($set);
                sort($names);
                $lines[] = ($words[$rel] ?? ('it is related (' . $rel . ') to')) . ': ' . implode(', ', array_slice($names, 0, 14)) . (count($names) > 14 ? ' and ' . (count($names) - 14) . ' more' : '') . '.';
            }
            $out[] = ['title' => 'System map: ' . $lab . ' (' . $file . ')',
                'keywords' => $this->words($lab) . ', ' . $lab . ', ' . basename($file) . ', what does ' . $lab . ' connect to, how does ' . $lab . ' work, ' . $lab . ' dependencies',
                'body' => $lab . ' is defined in ' . $file . ($n['community_name'] ?? '' ? ' and belongs to the code group "' . $n['community_name'] . '"' : '') . ". It has {$d} connections in the code graph.\n" . implode("\n", $lines)];
            if (count($out) >= 160) {
                break;
            }
        }

        return $out;
    }
}
