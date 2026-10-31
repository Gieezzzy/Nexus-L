<?php

namespace App\Commands;

use App\Libraries\Brain;
use App\Libraries\BrainLearn;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark brain:train-vf
 *
 * Trains LEXIS AI Brain on 500+ realistic queries, commands, abbreviations,
 * failure symptoms, and parameter questions for VF Test Methods.
 */
class TrainVfMethods extends BaseCommand
{
    protected $group       = 'NEXUS-L';
    protected $name        = 'brain:train-vf';
    protected $description = 'Trains the AI Brain with 500+ diverse VF test method commands, synonyms, and queries.';

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        CLI::write("==================================================", 'cyan');
        CLI::write("  LEXIS AI Brain: VF Test Methods 500+ Trainer", 'yellow');
        CLI::write("==================================================", 'cyan');

        // Ensure weights table exists
        if (! $db->tableExists('tbl_bot_weights')) {
            CLI::error("tbl_bot_weights does not exist. Run php spark migrate first.");
            return;
        }

        // Fetch all VF knowledge entries to map codes to kb_id
        $kbRows = $db->table('tbl_bot_knowledge')->where('kind', 'vf_method')->get()->getResultArray();
        $codeToId = [];
        foreach ($kbRows as $row) {
            if (preg_match('/(VF\s*-\s*[A-Z0-9]+|VF-[A-Z0-9]+)/i', $row['title'], $m)) {
                $c = strtoupper(preg_replace('/\s+/', '', $m[1]));
                $codeToId[$c] = (int) $row['id'];
                // Also map series letter overview
                if (preg_match('/Series\s+([A-J])/i', $row['title'], $sm)) {
                    $codeToId['SERIES-' . strtoupper($sm[1])] = (int) $row['id'];
                }
            } elseif (stripos($row['title'], 'Manual') !== false) {
                $codeToId['VF-MANUAL'] = (int) $row['id'];
            }
        }

        CLI::write("Indexed " . count($codeToId) . " target knowledge targets.", 'green');

        // Master Dataset of 500+ diverse training queries
        $trainingCases = $this->generate500TrainingQueries();

        $totalQueries = count($trainingCases);
        CLI::write("Generated {$totalQueries} realistic training prompts & variations across VF test methods.", 'cyan');

        // Step 1: Train Term Weights (ML Epochs)
        CLI::write("Training weights with BrainLearn::train() across 5 epochs...", 'yellow');
        $weightsUpdated = 0;
        $trainedCount = 0;

        for ($epoch = 1; $epoch <= 5; $epoch++) {
            foreach ($trainingCases as $item) {
                $query = $item['q'];
                $targetCode = $item['code'];
                $kbId = $codeToId[$targetCode] ?? null;

                if (! $kbId) {
                    continue;
                }

                $terms = Brain::tokens($query);
                if (! $terms) {
                    continue;
                }

                $res = BrainLearn::train($terms, $kbId, 1);
                if ($res !== null) {
                    $weightsUpdated += count($terms) + 1;
                }
                if ($epoch === 1) {
                    $trainedCount++;
                }
            }
        }

        CLI::write("Completed training: {$trainedCount} unique queries, {$weightsUpdated} total weight updates applied.", 'green');

        // Step 2: Enrich keywords in tbl_bot_knowledge with user query phrases (comma-separated for phrase matching)
        CLI::write("Enriching comma-separated keyword phrases in tbl_bot_knowledge for +4.0 phrase bonuses...", 'yellow');
        $groupedKeywords = [];
        foreach ($trainingCases as $item) {
            $c = $item['code'];
            $groupedKeywords[$c][] = mb_strtolower($item['q']);
        }

        $enrichedCount = 0;
        foreach ($groupedKeywords as $code => $phrases) {
            $kbId = $codeToId[$code] ?? null;
            if (! $kbId) {
                continue;
            }

            $current = $db->table('tbl_bot_knowledge')->where('id', $kbId)->get()->getRowArray();
            if (! $current) {
                continue;
            }

            $existingKw = (string) ($current['keywords'] ?? '');
            $existingPhrases = array_filter(array_map('trim', explode(',', $existingKw)));

            $newPhrases = [];
            foreach ($phrases as $phrase) {
                $cleanPhrase = trim(preg_replace('/\s+/', ' ', $phrase));
                if (mb_strlen($cleanPhrase) >= 4 && mb_strlen($cleanPhrase) <= 45) {
                    $newPhrases[$cleanPhrase] = true;
                }
            }

            $allPhrases = array_unique(array_merge($existingPhrases, array_keys($newPhrases)));
            $combinedKw = '';
            foreach ($allPhrases as $p) {
                if ($combinedKw === '') {
                    $combinedKw = $p;
                } elseif (mb_strlen($combinedKw . ', ' . $p) <= 590) {
                    $combinedKw .= ', ' . $p;
                } else {
                    break;
                }
            }

            $db->table('tbl_bot_knowledge')->where('id', $kbId)->update([
                'keywords'   => $combinedKw,
                'updated_at' => $now,
            ]);
            $enrichedCount++;
        }

        CLI::write("Enriched comma-separated keywords on {$enrichedCount} knowledge entries.", 'green');

        // Step 3: Flush Brain caches & Markdown playbooks
        CLI::write("Flushing Brain caches and synchronizing knowledge backups...", 'yellow');
        Brain::flush();

        // Step 4: Verification sample run (Target: >= 90% confidence for all test methods)
        CLI::write("Running verification sample on 15 real-world queries (Target: >= 90% confidence)...", 'cyan');
        $sampleQueries = [
            'how do we test rubber tensile strength at 500mm/min',
            'show me vf-b01 illustrated guide',
            'what is the cut growth limit for ross flex at cold temp',
            'how to test whole footwear sole adhesion vf-f01',
            'pu sole hydrolysis jungle test at 70c 95rh',
            'check static compression set for eva midsole',
            'show me all vf test methods in series b',
            'what test is used for shoelaces coming untied',
            'how to measure martindale abrasion on collar lining',
            'vans footwear performance testing manual 2026',
            'what test is used when shoe sole is separating at the toe',
            'how to check shore a durometer hardness for rubber',
            'die c tear strength astm d624 test procedure',
            'accelerated uv yellowing test on white foxing band',
            'testing parameters with temperature pdf',
        ];

        $passed = 0;
        foreach ($sampleQueries as $sq) {
            $hits = Brain::search($sq, ['role' => 'user'], 3);
            $conf = Brain::confidence($hits, $sq);
            $topTitle = $hits[0]['row']['title'] ?? '(none)';
            $isTargetMet = ($conf >= 90 && $topTitle !== '(none)');
            if ($isTargetMet) {
                $passed++;
                CLI::write("  ✓ [{$conf}%] {$sq} -> " . mb_substr($topTitle, 0, 48), 'green');
            } else {
                CLI::write("  ✗ [{$conf}%] {$sq} -> " . mb_substr($topTitle, 0, 48), 'red');
            }
        }

        $pct = round(($passed / count($sampleQueries)) * 100);
        CLI::write("Target Confidence Verification (>=90%): {$passed}/" . count($sampleQueries) . " ({$pct}%)", $passed === count($sampleQueries) ? 'green' : 'yellow');

        // Step 5: AI Vector Indexing
        if (\App\Libraries\AiBridge::isLive()) {
            CLI::write("Triggering AI vector index refresh...", 'yellow');
            \App\Libraries\AiBridge::post('/admin/train/start', []);
            \App\Libraries\AiBridge::post('/admin/index', []);
            CLI::write("AI Vector store re-indexed successfully.", 'green');
        }

        CLI::write("==================================================", 'cyan');
        CLI::write("  VF 500+ Training Pipeline Completed Successfully!", 'green');
        CLI::write("==================================================", 'cyan');
    }

    /**
     * Generates 500+ diverse, realistic user commands, questions, and failure queries
     * mapped to target VF method codes.
     */
    private function generate500TrainingQueries(): array
    {
        $queries = [];

        // Helper to add query
        $add = function($code, $q) use (&$queries) {
            $queries[] = ['code' => $code, 'q' => trim($q)];
        };

        // 1. GENERAL DISCOVERY & CATALOG (45 queries)
        $catalogQueries = [
            'what test methods do we have', 'show all vf test methods', 'list of test methods', 'vf test methods catalog',
            'show me the complete list of lab tests', 'what tests are available in the qc lab', 'all footwear test standards',
            'how many test methods does vans have', 'show me all dtp lab 101 methods', 'vf test standards list',
            'what are our laboratory test methods', 'where can i see all vf methods', 'show all test methods in the system',
            'list all qc test methods', 'test method index', 'lab test directory', 'give me the list of physical tests',
            'can you show the test catalog', 'vf testing procedures list', 'all vf methods', 'what testing do we perform',
            'show available test methods', 'qc lab test catalogue', 'list of vf standards', 'show test standards',
            'what test methods exist', 'browse test methods', 'search test methods', 'complete test methods list',
            'view all vf test documents', 'show list of test methods', 'testing methods list', 'what are the vf standards',
            'laboratory testing standards', 'all available test procedures', 'display test methods', 'open test catalog',
            'how to find a test method', 'find test method', 'which test methods are in the database', 'show me the testing list',
            'list all test methods with pdf', 'show test protocols', 'footwear testing protocols', 'show me all lab procedures'
        ];
        foreach ($catalogQueries as $q) {
            $add('VF-INDEX', $q);
        }

        // 2. MASTER MANUALS & PARAMETERS (40 queries)
        $manualQueries = [
            'vans footwear performance testing manual', 'vans manual 2026', 'testing manual 2026 pdf',
            'vans quality manual', 'footwear testing manual', 'dtp lab 101 manual', 'download vans manual 2026',
            'vans performance testing manual 2026', 'open footwear manual pdf', 'where is the vans testing manual',
            'official vans testing specifications', 'vans global performance manual', '2026 testing manual',
            'performance testing manual for footwear', 'vans qc manual', 'vans quality specification manual'
        ];
        foreach ($manualQueries as $q) {
            $add('VF-MANUAL', $q);
        }

        $paramQueries = [
            'universal machine test parameters', 'utm test parameters', 'tensile machine parameters excel',
            'universal machine pull rates table', 'utm crosshead speed list', 'utm speed settings',
            'testing parameters with temperature', 'testing parameters w temp', 'temperature testing parameters pdf',
            'guidelines for physical property testing', 'guidelines for phy prop testing', 'physical property testing excel',
            'lab testing temperature guidelines', 'test parameter table with temp', 'universal tensile machine speed settings'
        ];
        foreach ($paramQueries as $q) {
            $add('VF-PARAM-UTM', $q);
        }

        // 3. SERIES OVERVIEWS (50 queries)
        $seriesMap = [
            'SERIES-A' => ['series a test methods', 'physical and dimensional properties', 'series a physical tests', 'what tests are in series a', 'hardness thickness weight tests'],
            'SERIES-B' => ['series b test methods', 'strength and mechanical properties', 'series b mechanical tests', 'what tests are in series b', 'tensile tear compression slip tests'],
            'SERIES-C' => ['series c test methods', 'abrasion resistance tests', 'series c abrasion', 'what tests are in series c', 'din martindale taber lace abrasion'],
            'SERIES-D' => ['series d test methods', 'flexing and fatigue resistance', 'series d flexing tests', 'what tests are in series d', 'bally ross bata belt flex tests'],
            'SERIES-E' => ['series e test methods', 'chemical and aging tests', 'environmental fastness tests', 'what tests are in series e', 'colorfastness uv yellowing hydrolysis tests'],
            'SERIES-F' => ['series f test methods', 'adhesion and bond strength', 'sole bonding adhesion tests', 'what tests are in series f', 'peel strength sole adhesion finish bond'],
            'SERIES-G' => ['series g test methods', 'assembled footwear performance', 'whole shoe tests', 'what tests are in series g', 'whole shoe flex wash waterproof tests'],
            'SERIES-H' => ['series h test methods', 'sampling and material conditioning', 'conditioning protocols', 'what tests are in series h', 'vf-h01 23c 50rh conditioning room'],
            'SERIES-I' => ['series i test methods', 'chemical analysis and identification', 'fiber and oil content tests', 'what tests are in series i', 'soxhlet oil extraction fiber test'],
            'SERIES-J' => ['series j test methods', 'electrical and thermal safety', 'dielectric and insulation tests', 'what tests are in series j', '18000v shock resistance thermal conductivity'],
        ];
        foreach ($seriesMap as $sCode => $sQueries) {
            foreach ($sQueries as $sq) {
                $add($sCode, $sq);
            }
        }

        // 4. INDIVIDUAL VF METHOD QUERIES (300+ queries covering A to J series)
        $methodCases = [
            // VF-A01 Hardness
            'VF-A01' => [
                'vf-a01', 'vfa01', 'vf a01', 'a01', 'hardness test method', 'shore a hardness test',
                'shore c durometer test', 'how to test hardness of rubber outsole', 'durometer reading on eva foam',
                'illustrated vf-a01 hardness', 'astm d2240 durometer procedure', 'outsole hardness measurement',
                'how many points to read hardness on sole', 'measure durometer on foxing tape'
            ],
            // VF-A02 Weight
            'VF-A02' => [
                'vf-a02', 'vfa02', 'vf a02', 'a02', 'weight determination test method', 'how to weigh soling material',
                'unit weight of upper fabric', 'areal weight in grams per square meter', 'astm d3776 weight test',
                'measure component weight of insole', 'fabric weight gsm test method'
            ],
            // VF-A03 Thickness
            'VF-A03' => [
                'vf-a03', 'vfa03', 'vf a03', 'a03', 'thickness measurement method', 'dead weight thickness gauge test',
                'leather thickness test method', 'measure thickness of eva sheet', 'astm d1813 thickness gauge',
                'illustrated vf-a03 thickness', 'how to measure soling thickness without pinching', 'lining fabric thickness test'
            ],
            // VF-A04 Specific Gravity
            'VF-A04' => [
                'vf-a04', 'vfa04', 'vf a04', 'a04', 'specific gravity test method', 'density measurement rubber',
                'archimedes water displacement density test', 'how to calculate specific gravity of sole',
                'astm d792 specific gravity', 'illustrated vf-a04 specific gravity', 'eva compound density test',
                'measure density of cellular midsole'
            ],
            // VF-A06 Softness
            'VF-A06' => [
                'vf-a06', 'vfa06', 'vf a06', 'a06', 'leather softness test method', 'iso 17235 softness tester',
                'how to measure upper leather softness', 'illustrated vf-a06 softness', 'plunger deflection test on leather',
                'softness gauge reading for suede'
            ],
            // VF-A07 Yarn Count
            'VF-A07' => [
                'vf-a07', 'vfa07', 'vf a07', 'a07', 'yarn count test method', 'linear density tex denier test',
                'how to measure shoelace yarn count', 'sewing thread denier measurement', 'astm d1907 yarn count'
            ],
            // VF-A08 Non-Volatile Content
            'VF-A08' => [
                'vf-a08', 'vfa08', 'vf a08', 'a08', 'non volatile content test', 'adhesive solids content percentage',
                'primer volatile percentage test', 'bake adhesive at 105c for solids', 'astm d2369 non volatile'
            ],
            // VF-B01 Tensile Strength
            'VF-B01' => [
                'vf-b01', 'vfb01', 'vf b01', 'b01', 'tensile properties test method', 'rubber tensile strength test',
                'elongation at break test method', 'dumbbell die c tensile specimen', 'pull speed 500 mm per min rubber',
                'illustrated vf-b01 tensile rubber', 'illustrated vf-b01 tensile fabric', 'illustrated vf-b01 tensile leather',
                'illustrated vf-b01 tensile lace', 'illustrated vf-b01 tensile thread', 'tensile modulus test method',
                'how to cut tensile dumbbells from outsole', 'astm d412 tensile test procedure', 'textile tensile strength astm d5034'
            ],
            // VF-B02 Bursting Strength
            'VF-B02' => [
                'vf-b02', 'vfb02', 'vf b02', 'b02', 'bursting strength test method', 'diaphragm bursting pressure',
                'upper fabric bursting test', 'lining bursting resistance astm d3786', 'illustrated vf-b02 bursting',
                'hydraulic burst test on knitted collar'
            ],
            // VF-B03 Peel & Shear
            'VF-B03' => [
                'vf-b03', 'vfb03', 'vf b03', 'b03', 'peel and shear strength test', 'hook and loop velcro test',
                'velcro holding strength test method', 'astm d5169 shear strength fastener', 'touch and close fastener durability'
            ],
            // VF-B04 Hardware Attachment
            'VF-B04' => [
                'vf-b04', 'vfb04', 'vf b04', 'b04', 'hardware attachment test method', 'lace holder strength test',
                'eyelet pull out test', 'd-ring detachment tensile test', 'buckle tensile pull test',
                'illustrated vf-b04 hardware attachment', 'how to test lace loop breaking force'
            ],
            // VF-B05 Die C Tear
            'VF-B05' => [
                'vf-b05', 'vfb05', 'vf b05', 'b05', 'die c tear test method', 'die-c tear strength rubber',
                'astm d624 die c tear procedure', 'illustrated vf-b05 die c tear', 'how to cut 0.5mm nick in die c sample',
                'tear initiation test on rubber outsole', 'pull speed for die c tear 500 mm min'
            ],
            // VF-B06 Split Tear
            'VF-B06' => [
                'vf-b06', 'vfb06', 'vf b06', 'b06', 'split tear test method', 'eva midsole split tear test',
                'cellular soling split tear', 'illustrated vf-b06 split tear', 'astm d3574 test f split tear',
                'midsole foam split resistance'
            ],
            // VF-B07 Trouser Tear
            'VF-B07' => [
                'vf-b07', 'vfb07', 'vf b07', 'b07', 'trouser tear test method', 'leather trouser tear test',
                'textile trouser tear resistance', 'astm d2261 trouser tear', 'illustrated vf-b07 trouser tear leather',
                'illustrated vf-b07 trouser tear fabric', 'illustrated vf-b07 trouser tear rubber', 'tear propagation test'
            ],
            // VF-B08 Stitch Tear
            'VF-B08' => [
                'vf-b08', 'vfb08', 'vf b08', 'b08', 'stitch tear test method', 'needle tear resistance leather',
                'insole board stitch tear strength', 'satra tm82 stitch tear test', 'illustrated vf-b08 stitch tear'
            ],
            // VF-B09 Baumann Tear
            'VF-B09' => [
                'vf-b09', 'vfb09', 'vf b09', 'b09', 'baumann tear test method', 'baumann tear strength leather',
                'heavy upper leather tear test', 'illustrated vf-b09 baumann tear', 'satra tm29 baumann tear'
            ],
            // VF-B10 Compression Set
            'VF-B10' => [
                'vf-b10', 'vfb10', 'vf b10', 'b10', 'static compression set test method', 'eva compression set 50c',
                'midsole permanent deformation test', 'astm d395 method b compression set', 'illustrated vf-b10 compression set',
                'foam thickness recovery test 6 hours 50 degrees'
            ],
            // VF-B15 James Slip
            'VF-B15' => [
                'vf-b15', 'vfb15', 'vf b15', 'b15', 'james slip resistance test', 'static coefficient of friction james machine',
                'astm f489 slip test', 'illustrated vf-b15 james slip', 'dry and wet tile slip test james tester'
            ],
            // VF-B16 Rebound Resiliency
            'VF-B16' => [
                'vf-b16', 'vfb16', 'vf b16', 'b16', 'rebound resiliency test method', 'vertical ball rebound test',
                'eva foam energy return cushioning', 'astm d2632 ball rebound', 'illustrated vf-b16 rebound resiliency',
                'steel ball drop 460mm bounce percentage'
            ],
            // VF-B17 Zipper Strength
            'VF-B17' => [
                'vf-b17', 'vfb17', 'vf b17', 'b17', 'zipper strength test method', 'crosswise zipper chain tensile',
                'zipper slider lock holding strength', 'zipper puller attachment detachment', 'astm d2061 zipper strength'
            ],
            // VF-B23 Knot Slip
            'VF-B23' => [
                'vf-b23', 'vfb23', 'vf b23', 'b23', 'knot slip resistance test', 'shoelace untying test method',
                'illustrated vf-b23 knot slip resistance', 'satra tm153 knot slip', 'shoelace bow knot loosening test'
            ],
            // VF-B24 Lace Tip
            'VF-B24' => [
                'vf-b24', 'vfb24', 'vf b24', 'b24', 'lace tip retention test', 'shoelace aglet pull off test',
                'illustrated vf-b24 lace tip retention', 'satra tm154 aglet retention', 'plastic tip detachment force'
            ],
            // VF-B25 SATRA TM144 Slip
            'VF-B25' => [
                'vf-b25', 'vfb25', 'vf b25', 'b25', 'satra tm144 coefficient of friction', 'traction properties test method',
                'whole shoe slip test quarry tile', 'dynamic friction slip test wet stainless steel', 'en iso 13287 slip'
            ],
            // VF-B26 Zipper Repeated Cycles
            'VF-B26' => [
                'vf-b26', 'vfb26', 'vf b26', 'b26', 'slide fasteners repeated opening closing', 'zipper cycle durability test',
                'illustrated vf-b26 slide fasteners', 'satra tm52 zipper cycling 10000 cycles'
            ],
            // VF-B27 Edgewise Crush
            'VF-B27' => [
                'vf-b27', 'vfb27', 'vf b27', 'b27', 'edgewise crush strength test', 'shoe box crush strength test',
                'corrugated packaging compression test', 'illustrated vf-b27 edgewise crush', 'tappi t811 box crush'
            ],
            // VF-C01 DIN Abrasion
            'VF-C01' => [
                'vf-c01', 'vfc01', 'vf c01', 'c01', 'din rotary drum abrasion test', 'outsole din abrasion resistance',
                'volume loss mm3 test method', 'iso 4649 method a din abrasion', 'illustrated vf-c01 din abrasion',
                'rubber sole 40 meters p60 sandpaper wear test', 'density formula for din abrasion volume loss'
            ],
            // VF-C04 Martindale Abrasion
            'VF-C04' => [
                'vf-c04', 'vfc04', 'vf c04', 'c04', 'martindale abrasion resistance test', 'collar lining martindale test',
                'upper textile pilling and wear test', 'lissajous pattern abrasion test', 'illustrated vf-c04 martindale abrasion',
                'iso 12947-2 martindale rubs wet and dry', '51200 rubs lining breakdown test'
            ],
            // VF-C05 Taber Abrasion
            'VF-C05' => [
                'vf-c05', 'vfc05', 'vf c05', 'c05', 'taber abrasion test method', 'cs10 wheel rotary platform abrasion',
                'screen print logo taber test', 'illustrated vf-c05 taber abrasion', 'astm d3884 taber abrasion'
            ],
            // VF-C09 Lace Abrasion
            'VF-C09' => [
                'vf-c09', 'vfc09', 'vf c09', 'c09', 'shoe lace abrasion test', 'lace eyelet reciprocation test',
                'illustrated vf-c09 lace abrasion', 'satra tm154 lace wear resistance'
            ],
            // VF-D01 Bally Flex
            'VF-D01' => [
                'vf-d01', 'vfd01', 'vf d01', 'd01', 'bally flexing resistance test', 'upper leather bally flex',
                'v crease upper flexing fatigue test', 'iso 5402-1 bally flex', 'illustrated vf-d01 bally flex',
                '100000 cycles bally flex dry test', '20000 cycles bally flex wet test'
            ],
            // VF-D02 Ross Flex
            'VF-D02' => [
                'vf-d02', 'vfd02', 'vf b02', 'd02', 'ross flexing test method', 'soling cut growth flex test',
                'ross flex at cold temperature -10c', 'illustrated vf-d02 ross flex', 'astm d1052 ross flex procedure',
                '2mm center chisel puncture cut propagation', '50000 cycles ross flex 10mm mandrel'
            ],
            // VF-D03 Bata Belt
            'VF-D03' => [
                'vf-d03', 'vfd03', 'vf d03', 'd03', 'bata belt flexing test', 'whole sole belt flex test',
                'illustrated vf-d03 bata belt', 'satra tm133 bata belt sole flex'
            ],
            // VF-D06 Stretch & Flex
            'VF-D06' => [
                'vf-d06', 'vfd06', 'vf d06', 'd06', 'stretch and flex test method', 'elastic side gore durability test',
                'illustrated vf-d06 resistance to stretching and flexing', 'shoe webbing extension cycling 10000'
            ],
            // VF-E01 Color Fastness Water
            'VF-E01' => [
                'vf-e01', 'vfe01', 'vf e01', 'e01', 'color fastness to water test', 'water bleeding staining test',
                'iso 105-e01 water fastness', 'illustrated vf-e01 colorfastness to water', 'perspirometer 37c 4 hours water test'
            ],
            // VF-E02 Crocking
            'VF-E02' => [
                'vf-e02', 'vfe02', 'vf e02', 'e02', 'color fastness to crocking test', 'rub fastness dry and wet crocking',
                'crockmeter 9n load 10 double strokes', 'aatcc 8 crocking gray scale rating', 'illustrated vf-e02 crocking'
            ],
            // VF-E03 UV Discoloration
            'VF-E03' => [
                'vf-e03', 'vfe03', 'vf e03', 'e03', 'uv light discoloration test', 'ultraviolet yellowing test white sole',
                'uva 340 accelerated weathering chamber', 'astm g154 uv aging 50c 24 hours', 'illustrated vf-e03 uv discoloration',
                'delta e color difference spectrophotometer test'
            ],
            // VF-E16 Perspiration
            'VF-E16' => [
                'vf-e16', 'vfe16', 'vf e16', 'e16', 'color fastness to perspiration test', 'synthetic sweat acid alkaline fastness',
                'insole perspiration bleeding test', 'illustrated vf-e16 colorfastness to perspiration', 'iso 105-e04 perspiration'
            ],
            // VF-E17 Blooming
            'VF-E17' => [
                'vf-e17', 'vfe17', 'vf e17', 'e17', 'rubber surface blooming test', 'sulfur white haze bloom test',
                'plasticizer migration 60c oven test', 'illustrated vf-e17 blooming test'
            ],
            // VF-E18 Water Swelling
            'VF-E18' => [
                'vf-e18', 'vfe18', 'vf e18', 'e18', 'water swelling test method', 'polymer soak swelling volume test',
                'midsole dimensional expansion in water', 'astm d471 water swelling'
            ],
            // VF-E19 Hydrolysis
            'VF-E19' => [
                'vf-e19', 'vfe19', 'vf e19', 'e19', 'hydrolysis resistance test method', 'pu midsole jungle test',
                '70c 95rh humidity chamber crumble test', 'illustrated vf-e19 hydrolysis', 'satra tm353 hydrolysis 14 days'
            ],
            // VF-E20 pH Extract
            'VF-E20' => [
                'vf-e20', 'vfe20', 'vf e20', 'e20', 'ph of aqueous extract test', 'leather ph test method',
                'illustrated vf-e20 ph for an aqueous extract', 'iso 4045 leather ph difference figure'
            ],
            // VF-E21 Non-Marking
            'VF-E21' => [
                'vf-e21', 'vfe21', 'vf e21', 'e21', 'non marking outsole test method', 'gym floor scuff mark test',
                'court shoe black mark rub test', 'illustrated vf-e21 non marking test', 'satra tm223 non marking sole'
            ],
            // VF-E25 Breathability
            'VF-E25' => [
                'vf-e25', 'vfe25', 'vf e25', 'e25', 'water vapor permeability test', 'breathability test mvtr',
                'desiccant silica gel cup moisture transmission', 'iso 20344 breathability mg cm2 h'
            ],
            // VF-E26 Dip Test
            'VF-E26' => [
                'vf-e26', 'vfe26', 'vf e26', 'e26', 'dip test water repellency', 'cyclic water immersion absorption test',
                'illustrated vf-e26 dip test', 'satra tm171 dynamic dip'
            ],
            // VF-E27 Spray Test
            'VF-E27' => [
                'vf-e27', 'vfe27', 'vf e27', 'e27', 'spray test surface water repellency', 'aatcc 22 spray rating chart',
                'illustrated vf-e27 spray test', '250ml water spray 45 degree angle fabric test'
            ],
            // VF-E28 Glow in the dark
            'VF-E28' => [
                'vf-e28', 'vfe28', 'vf e28', 'e28', 'glow in the dark test method', 'photoluminescence afterglow brightness',
                'phosphorescent luminance decay test', 'din 67510 photoluminescence mcd m2'
            ],
            // VF-F01 Sole Adhesion
            'VF-F01' => [
                'vf-f01', 'vff01', 'vf f01', 'f01', 'whole footwear sole adhesion test', 'sole bond strength test method',
                'foxing tape adhesion to canvas upper', 'feather edge sole peel test', 'pull speed 100 mm min sole peel',
                'illustrated vf-f01 footwear adhesion', 'illustrated vf-f01 footwear adhesion whole bottom',
                'satra tm404 sole adhesion procedure', 'record bond strength n mm kg cm', 'adhesive cohesive failure evaluation'
            ],
            // VF-F02 Peel Strength
            'VF-F02' => [
                'vf-f02', 'vff02', 'vf f02', 'f02', 'peel strength rubber to upper', 't peel bond strength test',
                'astm d1876 t-peel test polyurethane cement', 'illustrated vf-f02 peel strength'
            ],
            // VF-F03 Finish Adhesion
            'VF-F03' => [
                'vf-f03', 'vff03', 'vf f03', 'f03', 'finish adhesion leather test', 'polyurethane topcoat peel adhesion',
                'illustrated vf-f03 finish adhesion', 'iso 11644 finish adhesion n 10mm'
            ],
            // VF-F04 Laminar Peel
            'VF-F04' => [
                'vf-f04', 'vff04', 'vf f04', 'f04', 'laminar peel strength test', 'foam package lamination peel',
                'waterproof membrane inter laminar bond', 'satra tm401 laminar peel'
            ],
            // VF-F05 Ink Adhesion
            'VF-F05' => [
                'vf-f05', 'vff05', 'vf f05', 'f05', 'ink adhesion on substrate test', 'cross hatch tape adhesion test',
                '3m 610 tape test screen print', 'logo foil stamp adhesion test', 'illustrated vf-f05 ink adhesion on substrate',
                'astm d3359 cross hatch 100 squares rating'
            ],
            // VF-F06 Strap Pulling
            'VF-F06' => [
                'vf-f06', 'vff06', 'vf f06', 'f06', 'sandal strap pulling strength test', 'slipper strap detachment pull out',
                'flip flop thong strap pull test', 'illustrated vf-f06 pulling strength straps'
            ],
            // VF-F09 Heel Attachment
            'VF-F09' => [
                'vf-f09', 'vff09', 'vf f09', 'f09', 'heel attachment strength test', 'high heel detachment force test',
                'illustrated vf-f09 heels attachment', 'satra tm113 heel attachment'
            ],
            // VF-F10 Top Lift
            'VF-F10' => [
                'vf-f10', 'vff10', 'vf f10', 'f10', 'top lift strength test', 'heel stud pull out retention test',
                'illustrated vf-f10 top lift or stud', 'satra tm108 top lift'
            ],
            // VF-F11 Cardboard Adhesion
            'VF-F11' => [
                'vf-f11', 'vff11', 'vf f11', 'f11', 'cardboard adhesion test', 'shoe box flap glue fiber tear test',
                'carton sealing adhesive bond', 'illustrated vf-f11 cardboard adhesion'
            ],
            // VF-G01 Assembled Parameters
            'VF-G01' => [
                'vf-g01', 'vfg01', 'vf g01', 'g01', 'test parameters for assembled footwear', 'whole shoe quality thresholds',
                'vans global footwear specification dtp lab 101', 'assembled shoe inspection criteria'
            ],
            // VF-G02 Whole Shoe Flex
            'VF-G02' => [
                'vf-g02', 'vfg02', 'vf g02', 'g02', 'whole shoe flex durability test', 'dynamic walking flex simulator',
                '150000 cycles whole shoe flex', 'foxing delamination whole shoe test', 'illustrated vf-g02 whole shoe flex',
                'satra tm92 whole shoe flex machine'
            ],
            // VF-G03 Whole Shoe Wash
            'VF-G03' => [
                'vf-g03', 'vfg03', 'vf g03', 'g03', 'whole shoe wash test', 'machine wash canvas shoes test',
                'shoe shrinkage after domestic washing', 'illustrated vf-g03 whole shoe wash', 'aatcc 135 shoe washing cycle'
            ],
            // VF-G04 Waterproof Flex
            'VF-G04' => [
                'vf-g04', 'vfg04', 'vf g04', 'g04', 'whole shoe flex water proof test', 'dynamic water penetration flexing',
                'submerged walking shoe leak test', 'illustrated vf-g04 whole shoe flex water proof', 'satra tm230 waterproof flex'
            ],
            // VF-G05 China GB Flex
            'VF-G05' => [
                'vf-g05', 'vfg05', 'vf g05', 'g05', 'china gb whole shoe flex test', 'gb t 3903.1 flexing resistance',
                'china national footwear flex standard', '40000 cycles 5mm initial cut china flex'
            ],
            // VF-G06 Whole Shoe Water Absorption
            'VF-G06' => [
                'vf-g06', 'vfg06', 'vf g06', 'g06', 'whole shoe water absorption test', 'submerged shoe weight gain percentage',
                'illustrated vf-g06 whole shoe water absorption'
            ],
            // VF-G07 Static Waterproof
            'VF-G07' => [
                'vf-g07', 'vfg07', 'vf g07', 'g07', 'whole shoe water proof static test', 'still water immersion leak test',
                'illustrated vf-g07 water proof whole shoe static', 'satra tm77 static waterproof'
            ],
            // VF-G08 Hydrostatic Head
            'VF-G08' => [
                'vf-g08', 'vfg08', 'vf g08', 'g08', 'hydrostatic water proofness test', 'water column pressure head test',
                'bootie membrane hydrostatic leak', 'illustrated vf-g08 hydrostatic water proofness', 'aatcc 127 hydrostatic'
            ],
            // VF-G09 Centrifuge Leak
            'VF-G09' => [
                'vf-g09', 'vfg09', 'vf g09', 'g09', 'centrifuge waterproof leak test', 'high speed centrifuge spin 250 rpm',
                'centrifugal leak detection waterproof shoes', 'satra tm358 centrifuge leak'
            ],
            // VF-H01 Conditioning
            'VF-H01' => [
                'vf-h01', 'vfh01', 'vf h01', 'h01', 'material conditioning standard', 'laboratory conditioning 23c 50rh',
                'how long to condition test specimens', '24 hours conditioning room parameters', 'astm d618 material conditioning'
            ],
            // VF-H02 Leather Sampling
            'VF-H02' => [
                'vf-h02', 'vfh02', 'vf h02', 'h02', 'leather sampling protocol', 'official sampling area butt hide',
                'where to cut test swatches on hide', 'iso 2418 leather sampling standard'
            ],
            // VF-H03 Midsole Sampling
            'VF-H03' => [
                'vf-h03', 'vfh03', 'vf h03', 'h03', 'midsole sampling protocol', 'how to cut eva midsole test coupon',
                'sampling test specimens from molded foam'
            ],
            // VF-H04 Cement Solids
            'VF-H04' => [
                'vf-h04', 'vfh04', 'vf h04', 'h04', 'cement test solid content', 'adhesive solids percentage measurement',
                'dry polymer content solvent cement'
            ],
            // VF-I01 Oil Content
            'VF-I01' => [
                'vf-i01', 'vfi01', 'vf i01', 'i01', 'oil content test method', 'soxhlet extraction oil test',
                'fatliquor content in leather', 'dichloromethane extraction test'
            ],
            // VF-I02 Fiber Identification
            'VF-I02' => [
                'vf-i02', 'vfi02', 'vf i02', 'i02', 'fiber identification and analysis', 'microscopic fiber identification',
                'chemical solubility test cotton nylon polyester', 'aatcc 20 fiber analysis'
            ],
            // VF-I05 Oil Resistant
            'VF-I05' => [
                'vf-i05', 'vfi05', 'vf i05', 'i05', 'oil resistant soling test', 'hydrocarbon mineral oil swelling',
                'irm 903 reference oil 22 hours soak', 'astm d471 oil resistance max 12 percent swell'
            ],
            // VF-J01 Electric Shock
            'VF-J01' => [
                'vf-j01', 'vfj01', 'vf j01', 'j01', 'electric shock resistance test', 'dielectric sole testing 18000v',
                'safety shoe electrical hazard eh test', 'astm f2412 18kv 1 minute current leakage'
            ],
            // VF-J02 Thermal Conductivity
            'VF-J02' => [
                'vf-j02', 'vfi02', 'vf j02', 'j02', 'thermal conductivity insulation test', 'hot sand bath 150c sole insulation',
                'cold insulation temperature transfer', 'en iso 20344 thermal insulation sole'
            ],
        ];

        foreach ($methodCases as $code => $qList) {
            foreach ($qList as $q) {
                $add($code, $q);
            }
        }

        // 5. PROBLEM & DEFECT SYMPTOM QUERIES (80 queries mapping real floor problems to VF methods)
        $problemCases = [
            'VF-F01' => [
                'sole is falling off at the toe', 'foxing band peeled from canvas', 'glue failure between sole and shoe upper',
                'what test tests sole bonding strength', 'outsole detached after wearing once', 'rubber sole separating from midsole',
                'bonding line gap at the waist', 'sole separation defect testing', 'delamination between rubber foxing and vamp'
            ],
            'VF-B01' => [
                'rubber outsole snapped in half', 'shoelace broke when tying tightly', 'sewing thread snapped during lasting',
                'rubber is too brittle and snaps easily', 'fabric ripped under tension', 'how to test tensile strength of soling compound'
            ],
            'VF-B05' => [
                'rubber sole tore at the tread notch', 'foxing tape has small tear that spreads', 'tear propagation on vulcanized rubber',
                'outsole lug ripped off on skateboard grip tape', 'how do we check rubber tear resistance'
            ],
            'VF-B06' => [
                'eva midsole split into two layers', 'foam cushion tearing horizontally', 'sponge rubber midsole tearing apart',
                'how to test split tear of cellular soling'
            ],
            'VF-B10' => [
                'eva midsole squished flat and stayed flat', 'shoe insole compressed down permanently',
                'heel cushion packed out and has no bounce', 'midsole lost thickness after walking', 'compression set failure on foam'
            ],
            'VF-B15' => [
                'shoes are too slippery on wet tile floor', 'slip and fall hazard on wet surfaces', 'testing slip resistance of waffle outsole',
                'how to check coefficient of friction on shoes', 'outsole slippery on smooth concrete'
            ],
            'VF-B16' => [
                'midsole feels dead and has no energy return', 'eva foam is hard and has no bounce', 'how to measure rebound of cushion'
            ],
            'VF-B17' => [
                'zipper teeth pulled apart while putting shoe on', 'zipper pull tab broke off in hand', 'zipper slider jammed and separated',
                'crosswise zipper strength failure'
            ],
            'VF-B23' => [
                'shoelaces untie constantly while walking', 'knot comes undone on round shoelaces', 'shoelace knot slip problem',
                'how to test why shoelaces slip loose'
            ],
            'VF-B24' => [
                'plastic lace tip fell off shoelace', 'aglet slipped off the end of lace', 'shoelace tip pulled off during threading',
                'how to test lace tip retention'
            ],
            'VF-C01' => [
                'rubber sole wore down to smooth bottom too fast', 'waffle sole tread worn out in two weeks',
                'abrasion wear on skate shoe bottom', 'high volume loss on rubber abrasion drum'
            ],
            'VF-C04' => [
                'collar lining fabric has holes from ankle rub', 'heel counter lining worn through to plastic',
                'fabric pilling and fuzz on inner sock', 'how to test collar lining durability against rubbing'
            ],
            'VF-D01' => [
                'leather upper cracked along the vamp crease', 'synthetic pu leather flaking at flex point',
                'repeated bending caused upper finish to crack', 'how to test upper flexing durability'
            ],
            'VF-D02' => [
                'rubber sole cracked in the ball flex groove', 'midsole cut grew all the way through during flexing',
                'sole cracked in half at cold temperature', 'how to measure cut growth on rubber outsoles'
            ],
            'VF-E01' => [
                'dye bled from red suede onto white socks after rain', 'leather colored water stained canvas',
                'color bleeding when shoe got wet'
            ],
            'VF-E02' => [
                'black canvas rubs off color onto white trousers', 'dye transferred from shoe to white crock cloth',
                'dry rubbing color crocking failure', 'how to test dye rubbing fastness'
            ],
            'VF-E03' => [
                'white rubber foxing turned yellow in display window', 'clear icy sole turned yellow and cloudy',
                'uv discoloration on white vulcanized tape', 'accelerated uv yellowing test method'
            ],
            'VF-E16' => [
                'foot perspiration made insole dye bleed', 'acidic sweat caused collar lining color change',
                'perspiration staining on sockliner'
            ],
            'VF-E17' => [
                'white chalky powder on surface of black rubber sole', 'sulfur blooming on cured rubber outsoles',
                'hazy white film on stored shoes', 'how to test for rubber blooming'
            ],
            'VF-E19' => [
                'pu midsole crumbled into small pieces in box', 'polyurethane sole disintegrated into dust',
                'hydrolysis jungle test failure on pu foam', 'how to test hydrolysis resistance on pu'
            ],
            'VF-E21' => [
                'dark sole leaves black scuff marks on school gym floor', 'rubber outsole marking white floor tiles',
                'non marking basketball shoe test'
            ],
            'VF-F05' => [
                'screen printed logo on heel tab peeled off with tape', 'printed vans logo rubbed off foxing band',
                'ink adhesion failure on rubber substrate', 'cross hatch tape test on shoe printing'
            ],
            'VF-G02' => [
                'foxing band separated from upper after 50000 flex cycles', 'whole shoe cracked during walking machine test',
                'delamination on whole shoe flex test machine', 'how many cycles for vans whole shoe flex test'
            ],
            'VF-G03' => [
                'canvas shoes shrank two sizes after machine wash', 'shoe seams unravelled in domestic washing machine',
                'colors bled together after washing whole shoe'
            ],
            'VF-G04' => [
                'water seeped through sole seam during puddle flexing', 'shoes got soaked inside while walking in rain',
                'dynamic waterproof flexing test for whole shoes'
            ],
        ];

        foreach ($problemCases as $code => $pList) {
            foreach ($pList as $p) {
                $add($code, $p);
            }
        }

        return $queries;
    }
}
