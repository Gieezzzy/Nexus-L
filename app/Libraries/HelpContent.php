<?php

namespace App\Libraries;

/**
 * The text of the Help page, kept apart from the page so it can be updated without touching the layout.
 * Each topic: id, title, module (the access-grid row a person needs to open that part; null = everybody), admin (only Admins),
 * lead (one sentence on what it is for), url (where it lives), articles [question / task => answer in HTML].
 * Plain words, the order people do things in. When a feature changes, change its article here.
 */
class HelpContent
{
    /** what changed lately, newest first: [date, title, one line, url, module] */
    public static function news(): array
    {
        return [
            ['Nov 2026', 'Quality Insights', 'A line map, the failure modes and a supplier scorecard, with the same Month / Week / Day control as the calendar.', 'quality-insights', 'insights'],
            ['Nov 2026', 'Calendar: Day view', 'The calendar now has Month, Week and Day. The Day view splits the day into Bonding, Material and Whole Shoe, problems first.', 'calendar-monitoring', 'calendar'],
            ['Nov 2026', 'Lab Alerts', 'Failed and tolerable results, shoes collected but not tested, the end-of-shift summary and failed ERP sends now arrive in your Inbox thread "Lab Alerts — NEXUS-L".', 'inbox', 'inbox'],
            ['Nov 2026', 'Mode of Failure is required', 'A bonding report rated Fail or Tolerable cannot be saved until the Mode of Failure(s) is written.', 'dtr?tab=bonding', 'bonding'],
            ['Nov 2026', 'ERP Finish Monitoring', 'Bonding lab references per month with their InterLink and ERP status; send one or many to the ERP from here.', 'lab-monitoring/erp-finish', 'erpfinish'],
            ['Nov 2026', 'Photo from your phone', 'A QR code on the bonding screens opens the phone camera and puts the picture straight into the report or message.', 'dtr?tab=bonding', 'bonding'],
            ['Nov 2026', 'Access for every new page', 'Calendar Monitoring, Quality Insights and ERP Finish Monitoring each have their own row in Settings > Users & roles.', 'settings', null],
        ];
    }

    public static function topics(): array
    {
        return [
            [
                'id' => 'start', 'title' => 'Getting around', 'module' => null, 'admin' => false, 'url' => 'dashboard',
                'lead' => 'The menu on the left follows the way the lab works. What you see in it depends on your role.',
                'articles' => [
                    ['How is the menu arranged?', '<p>Top to bottom:</p><ol><li><b>Dashboard, Inbox, Calendar Monitoring, Quality Insights, Analytics, InterLink</b>: where you start the day and see the lab\'s picture.</li><li><b>Tests, Lab Monitoring, Lab Reports, the document folders</b>: the groups you open when you work on a test or a report.</li><li><b>Help, Report Designer, Brain, Settings, Account Profile</b>: the tools, at the bottom. Report Designer, Brain and Settings are for Admins.</li></ol>'],
                    ['A page is missing from my menu', '<p>Each role sees only the pages it has access to. An Admin sets this under <b>Settings &rarr; Users &amp; roles</b>, in the access grid: <b>None</b> hides the page, <b>View</b> lets you open it, <b>Edit</b> lets you change things on it. If you need a page, ask an Admin to raise your role there.</p>'],
                    ['What does my role allow?', '<p>Open <b>Account Profile</b>: it lists every part of the system with your access (None, View or Edit). You can hold more than one role; you get the highest access any of them gives.</p>'],
                    ['Searching this guide', '<p>Type in the search box at the top of this page, or press <kbd>/</kbd> from anywhere on it. Press <kbd>Esc</kbd> to clear.</p>'],
                ],
            ],
            [
                'id' => 'dashboard', 'title' => 'Dashboard', 'module' => 'dashboard', 'admin' => false, 'url' => 'dashboard',
                'lead' => 'The laboratory overview: how many tests finished, the pass rate, what failed and what is still ongoing.',
                'articles' => [
                    ['Reading the overview', '<ul><li>Choose the period with <b>Today, 7 days, 30 days, 90 days</b>.</li><li><b>Tests finished</b> counts final results in that period; <b>Pass rate</b> counts pass and tolerable together.</li><li><b>Ongoing</b> is collected or in testing with no final result yet. <b>For report</b> is finished but waiting for an approved report.</li><li>The tabs (Bonding, Materials, Whole shoe, People and suppliers, Reports) show the same numbers for one part of the lab.</li></ul>'],
                    ['The numbers differ from another page', '<p>Pages that list individual tests (Calendar Monitoring, Quality Insights) count the same lab references. A small difference usually means a different period or that trial records are left out; test records such as "TRIAL" are never counted.</p>'],
                ],
            ],
            [
                'id' => 'inbox', 'title' => 'Inbox and Lab Alerts', 'module' => 'inbox', 'admin' => false, 'url' => 'inbox',
                'lead' => 'Team chat, plus two automatic senders that tell you what needs attention.',
                'articles' => [
                    ['Lab Alerts — NEXUS-L', '<p>An automatic thread that only appears if you are on an alert rule. It brings:</p><ul><li><b>Failed or tolerable result</b>: the moment a bonding result is saved, with the lab reference, style, line, mode of failure and the day\'s totals so far.</li><li><b>Collected but not tested</b>: at the cutoff time, the shoes collected from the lines that still have no result.</li><li><b>End-of-shift summary</b>: tested, passed, tolerable, failed, the failure rate and the worst lines.</li><li><b>ERP send failed</b>: when a report could not be entered in the ERP.</li></ul><p>Alerts are sent only in working hours. The people who receive them, the times and the wording are set by an Admin in the Notifier app.</p>'],
                    ['"Seen it? Tap to acknowledge"', '<p>Some alerts end with an acknowledge link. Open it once when you have seen the alert: it stops the reminders for <b>everybody</b>. Without it the alert is repeated after a few minutes, up to three times.</p>'],
                    ['InterLink 2.0 — Application No.', '<p>When a report or eTRF is submitted to InterLink, the Application No. (or the reason it failed) is posted in this thread, and also to Synology Chat.</p>'],
                    ['I get no alerts', '<p>You are probably not on a rule. Ask an Admin to add your role or your name to the rule. Also check that your account has its Synology ID filled in if you want the Synology message too.</p>'],
                ],
            ],
            [
                'id' => 'calendar', 'title' => 'Calendar Monitoring', 'module' => 'calendar', 'admin' => false, 'url' => 'calendar-monitoring',
                'lead' => 'Every test of the lab on a calendar: how many, which kind, and where they stand.',
                'articles' => [
                    ['Month, Week and Day', '<ul><li><b>Month</b>: each day shows how many tests it holds, how many are ongoing, and the first few lab references.</li><li><b>Week</b>: one column per day (Monday to Sunday) with a card for each test.</li><li><b>Day</b>: one day split into <b>Bonding, Material and Whole Shoe</b>, failed and tolerable first.</li></ul><p>Use the arrows and <b>Today</b> to move. The numbers above (total, ongoing, pending, completed, quality rate) always match the period you are looking at.</p>'],
                    ['Filtering', '<p>Pick a <b>Category</b> (Bonding, Material, Whole Shoe) and a <b>Status</b> (Ongoing, Pending, Completed), or type in the search box (lab reference, style, technician). The same filters apply in Month, Week and Day.</p>'],
                    ['Opening a day', '<p>Click a day (or a card). The side panel lists every test of that day: status, result, lab reference, test date, production date, technician and remarks. The tiles at the top of the panel are buttons: <b>Total</b> clears the filter, <b>Bonding / Material / Whole Shoe</b> pick a kind, <b>Ongoing / Pending / Completed</b> pick a status.</p>'],
                    ['View Full Sheet', '<p>Opens the real report of that lab reference in QC Lab Reports with the record already loaded: the bonding report, the material form, or the Whole Shoe page. You do not need to search for it.</p>'],
                    ['What do the colours and words mean?', '<ul><li><b>Ongoing</b>: being tested now. <b>Pending / Waiting</b>: collected or logged, not tested yet. <b>Completed</b>: has a final result.</li><li><b>Pass, Tolerable, Fail</b> are the lab\'s own ratings.</li></ul>'],
                ],
            ],
            [
                'id' => 'insights', 'title' => 'Quality Insights', 'module' => 'insights', 'admin' => false, 'url' => 'quality-insights',
                'lead' => 'Where the problems are: which line, which failure mode, which supplier.',
                'articles' => [
                    ['Choosing the period', '<p>The control at the top (arrows, Today and <b>Month / Week / Day</b>) is the same as in Calendar Monitoring and applies to all three tabs.</p>'],
                    ['Line map', '<p>One card per assembly line, grouped by building, adding up every bonding test of the period.</p><ul><li><b>ALL PASSED</b> (green): everything tested passed.</li><li><b>WATCH</b> (amber): no failure, but a tolerable result or shoes collected and still waiting to be tested.</li><li><b>FAILED</b> (red): at least one test failed.</li></ul><p>The bar on each card shows the mix: green passed, amber tolerable, red failed, grey waiting. The red banner at the top names every line with a failure. Click a card to see its tests. Tick <b>Show lines with no tests</b> to see the quiet lines too.</p><p>The line comes from the lab reference: <b>DTP20261103SHA9-R</b> is line <b>A9</b>.</p>'],
                    ['Failure modes', '<p>The failed and tolerable results of the period, grouped by <b>line, cement supplier, style or failure mode</b>. The right side counts each failure mode. "Not recorded" means the report was saved as Fail or Tolerable with no mode; the bonding form now asks for it, so this fills in over time. Click a row to see the tests behind it.</p>'],
                    ['Suppliers', '<p>Pass rate per supplier and material for the period, or for the last 90 or 180 days (a week is usually too small to judge a supplier). A supplier with fewer tests than the "low sample" number shows a grey tag: do not judge it yet. Click a row to see its failed and tolerable tests.</p><p>Same supplier spelled two ways? An Admin can merge them in <b>Settings &rarr; Supplier spelling merges</b> (for example <code>NNAPAO=NANPAO</code>).</p>'],
                    ['Who can open this page?', '<p>It is switched on per role in Settings &rarr; Users &amp; roles (row <b>Quality Insights</b>). At the start only Admins have it.</p>'],
                ],
            ],
            [
                'id' => 'interlink', 'title' => 'InterLink', 'module' => 'interlink', 'admin' => false, 'url' => 'interlink',
                'lead' => 'Send the lab\'s reports and eTRF requests to InterLink 2.0, and keep NEXUS-L in step with it.',
                'articles' => [
                    ['Sending a report (Report Submission)', '<ol><li>Open <b>InterLink &rarr; Report Submission</b>.</li><li>Press <b>Sync NEXUS-L Data</b> first: it fills the form from the lab\'s records. The send buttons stay locked until you have synced.</li><li>Check the filled fields, the report PDF and the test items.</li><li>Press <b>1-Click Send Report</b>, or <b>Print &amp; Submit</b>.</li></ol><p>The Application No. comes back in your Inbox and on Synology. Do not type the field codes: they are not shown any more and the form fills them for you.</p>'],
                    ['VF eTRF requests', '<p>Under <b>InterLink &rarr; VF eTRF</b> pick the template (Material, Product, Pull test, Bonding, Whole Shoe), a lab reference or typed values, then submit. The Application No. of each eTRF type is kept per lab reference and shown in the lookup.</p>'],
                    ['InterLink says the session ended', '<p>InterLink signs people out after a while. Press <b>Sign in to InterLink</b> and sign in on the window that opens. What you queued is kept and continues by itself.</p>'],
                    ['Where does the service run?', '<p>A background program sends the work to InterLink. An Admin chooses the PC (.43 or .45) in Settings &rarr; InterLink service. If sending stops, check that program is running on that PC.</p>'],
                ],
            ],
            [
                'id' => 'reports', 'title' => 'QC Lab Reports', 'module' => 'dtr', 'admin' => false, 'url' => 'dtr',
                'lead' => 'The digital test reports: Lab Tracker, Material tests, Shoe Bonding (U60), Whole Shoe and the calculators.',
                'articles' => [
                    ['Lab Tracker', '<p>Every lab report in one list. Use the category buttons or the search box (Lab Ref No, TR No, material, style, supplier) and press <b>Open Form</b> to view or edit the report.</p>'],
                    ['Material tests', '<p>Choose the form (fabric, leather, labels, rubber and the others), then the Lab Ref No or <b>+ New Record</b>. <b>Load Machine Results</b> fills the readings from the machine file; <b>Save Report</b> stores it; <b>Print / Preview</b> makes the official sheet.</p>'],
                    ['Shoe Bonding (U60)', '<ol><li>Choose the Lab Ref No or start a new report.</li><li><b>Load U60 Machine File</b> reads every spot value; the report shows each value at its point on the shoe, with the left and right sheets.</li><li>The Overall Result is worked out for you.</li><li>If the result is <b>Fail</b> or <b>Tolerable</b>, write the <b>Mode of Failure(s)</b> (for example Adhesive failure, Foxing to outsole). The report cannot be saved without it.</li><li>Press <b>Save Report</b>.</li></ol>'],
                    ['A photo from the phone (QR)', '<p>In <b>+ Bonding Shoe Collected</b> and in the Send Report window a QR code appears. Scan it with the phone: the camera opens, take the picture, and it appears in the report on the PC. The code works for about 15 minutes, for one phone at a time; a new code appears after the picture is sent.</p>'],
                    ['Send Report (Synology and e-mail)', '<p>From a finished bonding report, <b>Send Report</b> posts the report card and the shoe photo (two images) to Synology Chat or to an e-mail. Click any attached image to enlarge it.</p>'],
                    ['Report approval and QR signing', '<p>A report is approved by its signatories. Each one receives a Synology message and an e-mail with a QR code and a one-time link; signing from the phone is enough. Once every required signature is in, the report is final and can be sent to InterLink.</p>'],
                    ['Whole Shoe and Calculators &amp; Standards', '<p><b>Whole Shoe</b> opens the whole-shoe report for a lab reference. <b>Calculators &amp; Standards</b> covers weight, shrinkage, specific gravity, abrasion and the standards lookup.</p>'],
                ],
            ],
            [
                'id' => 'erp', 'title' => 'ERP Finish Monitoring', 'module' => 'erpfinish', 'admin' => false, 'url' => 'lab-monitoring/erp-finish',
                'lead' => 'The bonding lab references of a month with their InterLink and ERP status, and the way to send them to the ERP.',
                'articles' => [
                    ['Reading the list', '<p>Reports are grouped by production day and by <b>Right / Left</b> shoes. Each row shows the tested and production date, the line, the VSP code, the bonding and pull-test TR numbers, the InterLink status (green OK, orange pending, red not submitted), the result and the ERP status.</p>'],
                    ['Sending to the ERP', '<p>Click a row to read the report, then <b>Submit</b> (one) or tick several for a bulk send. The ERP Agent program enters them one at a time in the ERP and reports back; the page updates by itself and a message tells you when a report is completely sent. You need <b>Edit</b> access on this page to send; <b>View</b> only lets you read.</p>'],
                    ['The ERP says the send failed', '<p>The reason is shown on the row and in your Lab Alerts. Common ones: the ERP\'s style lookup window did not open, or the record already exists. An Admin can check the ERP PC under <b>Settings &rarr; ERP agent</b> (Check the ERP PC). Do not send the same report twice before the first result is known.</p>'],
                ],
            ],
            [
                'id' => 'docs', 'title' => 'Lab documents', 'module' => 'labdocs', 'admin' => false, 'url' => 'labdocs',
                'lead' => 'The laboratory\'s controlled documents and registers, in the folders of the sidebar.',
                'articles' => [
                    ['Finding a document', '<p>Open <b>Laboratory Documents</b> or <b>Other Documents</b> in the sidebar and pick a register; use the search box above them to find a document by name. Documents open in the viewer; people with edit access can change the registers.</p>'],
                ],
            ],
            [
                'id' => 'admin', 'title' => 'For Admins', 'module' => null, 'admin' => true, 'url' => 'settings',
                'lead' => 'Setting up people, access, alerts and the helper programs.',
                'articles' => [
                    ['Users &amp; roles and the access grid', '<p>Settings &rarr; <b>Users &amp; roles</b>: create people, give them one or more roles (Admin, Bonding, Encoding, Materials, Manager, Supervisor, Technician, Guest) and edit the grid. Each row is a page or part of the system: <b>None</b>, <b>View</b> or <b>Edit</b> per role. Rows for newer pages: <b>Calendar Monitoring</b>, <b>Quality Insights</b>, <b>ERP Finish Monitoring</b>. Settings, Brain and Report Designer are always Admin only. One-click login settings are under <b>Settings &rarr; Guest access</b>.</p>'],
                    ['Notifier app (alerts)', '<p>A Windows program (<b>NEXUS-L Notifier</b>) runs in the tray, watches the lab and delivers the alerts. In it you choose, for each alert: who gets it (roles or named people), by Inbox, Synology or phone push, the working days and hours, the times, a limit on how often, filters (kind of test, test type such as FIRST CASE, lines), reminders when nobody acknowledges, and the wording. <b>Dry run</b> shows what would be sent without sending. Its token is in Settings &rarr; AI Assistant &rarr; <b>Notifier app token</b>.</p>'],
                    ['Brain and the Brain Trainer app', '<p>The <b>Brain</b> console shows the assistant\'s knowledge and conversations. The <b>Brain Trainer</b> Windows app trains the bot on the server: choose the knowledge groups, epochs and how long, read the estimated finish time, Start and Stop. It can resume after a restart. Its token is in Settings &rarr; AI Assistant &rarr; <b>Brain Trainer app token</b>.</p>'],
                    ['ERP Agent', '<p>A Windows program on the ERP PC enters reports into the ERP in the background, one at a time. Settings &rarr; ERP agent chooses which PC (.43 or .45) receives the work and holds each one\'s address, port and token.</p>'],
                    ['Report Designer', '<p>Move every shoe point and dot, add a START marker, hide or rename form fields, set the machine-file location and the Excel reading rules.</p>'],
                    ['Settings you may need', '<ul><li><b>AI Assistant</b>: operating mode, service address and the two app tokens above.</li><li><b>Supplier spelling merges</b>: one <code>WRONG=RIGHT</code> per line for Quality Insights.</li><li><b>Report approval</b>: e-mail and Synology messages to signatories.</li></ul>'],
                ],
            ],
            [
                'id' => 'faq', 'title' => 'Common questions', 'module' => null, 'admin' => false, 'url' => null,
                'lead' => 'Quick answers to what people ask most.',
                'articles' => [
                    ['Download PDF or Print does nothing, or the report is blank', '<p>Refresh with <kbd>Ctrl</kbd> + <kbd>F5</kbd> and open the preview again. <b>Download PDF</b> needs Chrome or Edge on the server; if it still fails, use <b>Print Report / Save as PDF</b>. Both fit the report on one A4 page.</p>'],
                    ['"No matching machine output file found"', '<p>The machine has not saved the file yet, or the Lab Ref No is typed differently. Check it and try again. An Admin can check where the files are in <b>Report Designer &rarr; File locations</b>.</p>'],
                    ['Some values look wrong after loading machine results', '<p>Press <b>Refresh &amp; Apply Smart Standards</b> to recalculate averages and pass/fail. If a machine file layout changed, tell an Admin so the reading rules can be updated.</p>'],
                    ['It says I must write the Mode of Failure', '<p>A bonding report rated Fail or Tolerable needs it so Quality Insights and the alerts can say how it failed. Write it in the field marked "needed for Fail / Tolerable" and save again.</p>'],
                    ['The QR code on my screen stopped working', '<p>The phone link lasts about 15 minutes and works for one phone at a time. Close the phone page, then press the QR button again for a new code.</p>'],
                    ['The calendar or Quality Insights shows nothing', '<p>Check the period at the top (you may be on a month with no tests) and the filters. Press <b>Refresh</b> to reload from the lab data. Trial records are never shown.</p>'],
                    ['I cannot see Report Designer, Brain or Settings', '<p>They are only for Admins. Ask an Admin if you need a change made.</p>'],
                ],
            ],
            [
                'id' => 'words', 'title' => 'Words used in the lab', 'module' => null, 'admin' => false, 'url' => null,
                'lead' => 'What the short names mean.',
                'articles' => [
                    ['Glossary', '<ul><li><b>Lab Ref No</b>: the lab reference of a sample, for example DTP20261103SHA9-R. The date, the line (A9) and the side (R / L) are inside it.</li><li><b>TR No</b>: the test report number. <b>MTL</b>: the material number. <b>VSP</b>: the production code.</li><li><b>U60</b>: the whole-shoe bonding test. <b>eTRF</b>: the electronic test request form sent to InterLink.</li><li><b>Pass / Tolerable / Fail</b>: the lab\'s ratings. Tolerable is within the allowed band but not a clean pass.</li><li><b>FIRST CASE</b> (first article): the first shoes of a run; <b>DAILY</b>: the routine daily test.</li><li><b>Application No.</b>: the number InterLink gives a submitted report or eTRF.</li><li><b>Dry run</b>: the Notifier writes what it would send, but sends nothing.</li></ul>'],
                ],
            ],
        ];
    }

    /** the VF test methods of tbl_vf_test_methods as a Help topic: one answer per family, each method with its official and illustrated PDF */
    public static function methodsTopic(): ?array
    {
        try {
            $db = \Config\Database::connect();
            if (! $db->tableExists('tbl_vf_test_methods')) {
                return null;
            }
            $rows = $db->table('tbl_vf_test_methods')->select('code, title, category, file_name, url_path, is_illustrated')->orderBy('code')->get()->getResultArray();
        } catch (\Throwable $e) {
            return null;
        }
        $url = static function (string $path): string {
            return site_url(implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $path)))));
        };
        $name = static function (string $t): string {
            $t = preg_replace('/\s*[-–]?\s*(?:(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*\.?\s*,?\s*\d{0,2},?\s*\d{4}|Revise.*|-?REV)\s*$/i', '', trim($t));

            return trim(preg_replace('/\s+/', ' ', $t));
        };
        $docs   = [];
        $groups = [];
        foreach ($rows as $r) {
            if (str_starts_with((string) $r['file_name'], '~$')) {
                continue;
            }
            if (! preg_match('/^VF-[A-Z]\d+$/', (string) $r['code'])) {   // manuals, parameter sheets, the index
                $docs[] = $r;
                continue;
            }
            $g = &$groups[$r['category']][$r['code']];
            $g ??= ['title' => '', 'official' => null, 'illus' => []];
            $clean = $name((string) $r['title']);
            if ((int) $r['is_illustrated'] === 1) {
                $g['illus'][] = ['label' => trim(preg_replace('/^' . preg_quote((string) $r['code'], '/') . '\s*/', '', $clean)), 'url' => $url((string) $r['url_path'])];
            } elseif ($g['official'] === null) {
                $g['official'] = $url((string) $r['url_path']);
            }
            if ($g['title'] === '' || mb_strlen($clean) < mb_strlen($g['title'])) {
                $g['title'] = $clean;
            }
            unset($g);
        }
        if (! $groups) {
            return null;
        }

        $order = ['Physical & Dimensional Properties', 'Strength & Mechanical Properties', 'Abrasion Resistance', 'Flexing & Fatigue Resistance', 'Adhesion & Bond Strength',
            'Chemical, Aging & Environmental Fastness', 'Chemical Analysis & Identification', 'Assembled Footwear Performance', 'Electrical & Thermal Safety', 'Sampling & Material Conditioning'];
        $rank = static fn ($c) => array_search($c, $order, true) === false ? 99 : array_search($c, $order, true);
        uksort($groups, static fn ($a, $b) => $rank($a) <=> $rank($b));

        $n        = 0;
        $articles = [
            ['What the VF test methods are', '<p>The lab tests materials and shoes to the <b>VF test methods</b>: written procedures with a code such as <b>VF-D02</b> (the letter is the family, the number the method). NEXUS-L keeps every method as a PDF so the procedure is one click away. The groups below follow the family of the test.</p>'],
            ['Opening a method', '<ol><li>Find it by code or name: type in the search box at the top of this page (for example <i>ross flex</i>, <i>VF-E19</i>, <i>tear</i>).</li><li>Press <b>Official</b> for the full written procedure, or <b>Illustrated</b> for the same method with pictures. It opens in a new tab.</li><li>Some methods have several illustrated sheets (for example Tensile for fabric, lace, leather, rubber and thread): each has its own button.</li></ol>'],
        ];
        $ref   = '';
        $label = static fn (array $d): string => $d['code'] === 'VF-MANUAL' ? 'VANS Footwear Performance Testing Manual 2026' : (str_contains((string) $d['title'], 'List of Test Methods') ? 'List of Test Methods (index)' : (string) $d['title']);
        foreach ($docs as $d) {
            $ref .= '<li><a href="' . esc($url((string) $d['url_path']), 'attr') . '" target="_blank" rel="noopener">' . esc($label($d)) . '</a> <span class="hp-ext">' . esc(strtoupper(pathinfo((string) $d['file_name'], PATHINFO_EXTENSION))) . '</span></li>';
        }
        if ($ref !== '') {
            $articles[] = ['Reference documents', '<p>The manual, the index of all methods, the machine parameter sheets and the latest amendment notice:</p><ul class="hp-docs">' . $ref . '</ul>'];
        }
        foreach ($groups as $cat => $methods) {
            $li = '';
            foreach ($methods as $code => $m) {
                $n++;
                $links = '';
                if ($m['official']) {
                    $links .= '<a href="' . esc($m['official'], 'attr') . '" target="_blank" rel="noopener">Official</a>';
                }
                foreach ($m['illus'] as $i) {
                    $links .= '<a href="' . esc($i['url'], 'attr') . '" target="_blank" rel="noopener">Illustrated' . (count($m['illus']) > 1 && $i['label'] !== '' ? ': ' . esc($i['label']) : '') . '</a>';
                }
                $li .= '<li><b>' . esc($code) . '</b><span>' . esc($m['title']) . '</span><i>' . $links . '</i></li>';
            }
            $articles[] = [esc($cat) . ' <small>(' . count($methods) . ')</small>', '<ul class="hp-methods">' . $li . '</ul>'];
        }
        $articles[] = ['Ask the assistant about a method', '<p>The assistant (the round button in the corner) knows these methods. Ask it things like "how do I run VF-D02", "what is the Hydrolysis test" or "which method is Martindale"; it answers and gives you the PDF to open.</p>'];

        return ['id' => 'methods', 'title' => 'Test methods', 'module' => null, 'admin' => false, 'url' => null,
            'lead' => $n . ' VF test methods in ' . count($groups) . ' families, each with its official procedure and, where there is one, an illustrated version.', 'articles' => $articles];
    }
}
