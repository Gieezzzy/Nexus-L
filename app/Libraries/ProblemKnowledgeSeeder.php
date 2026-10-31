<?php

namespace App\Libraries;

use Config\Database;

/**
 * Seeds and trains 200 real-world QC Lab & testing problem questions and step-by-step diagnostic solutions.
 * Covers:
 *  1. Whole Shoe Bonding & U60 Issues (35)
 *  2. Material Testing Forms & Computations (35)
 *  3. Approvals, Signatories & QR Workflows (30)
 *  4. InterLink 2.0 Integration & Extension (35)
 *  5. Machine Output Files & Network Shares (30)
 *  6. System, Security, Sessions & Data Management (35)
 */
class ProblemKnowledgeSeeder
{
    /**
     * Seeds all 200 problem questions into tbl_bot_knowledge, flushes to Brain backup,
     * exports to C:\NexusAI\knowledge.jsonl, and triggers AI service re-indexing.
     *
     * @return array{total: int, inserted: int, updated: int, exported: bool, indexed: bool}
     */
    public static function run(): array
    {
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $problems = self::problems();

        $inserted = 0;
        $updated  = 0;

        foreach ($problems as $p) {
            $row = $db->table('tbl_bot_knowledge')->where('title', $p['title'])->get()->getRowArray();
            $data = [
                'kind'        => 'problem',
                'title'       => $p['title'],
                'keywords'    => $p['keywords'],
                'body'        => $p['body'],
                'steps_json'  => json_encode($p['steps'] ?? [], JSON_UNESCAPED_UNICODE),
                'actions_json'=> json_encode($p['actions'] ?? [], JSON_UNESCAPED_UNICODE),
                'page_match'  => $p['page_match'] ?? null,
                'error_match' => $p['error_match'] ?? null,
                'diagnose'    => $p['diagnose'] ?? null,
                'role_min'    => $p['role_min'] ?? 'user',
                'status'      => 'approved',
                'source'      => 'seed',
                'updated_at'  => $now,
            ];

            if ($row) {
                $db->table('tbl_bot_knowledge')->where('id', $row['id'])->update($data);
                $updated++;
            } else {
                $db->table('tbl_bot_knowledge')->insert($data + ['created_at' => $now, 'hits' => 0, 'helpful' => 0]);
                $inserted++;
            }
        }

        // 1. Flush to Brain backups (E:\html\Brain\knowledge.back)
        try {
            Brain::flush();
        } catch (\Throwable $e) {
            // ignore backup disk stalls
        }

        // 2. Export to C:\NexusAI\knowledge.jsonl
        $exported = false;
        try {
            $exportPath = 'C:\\NexusAI\\knowledge.jsonl';
            $dir = dirname($exportPath);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $allApproved = $db->table('tbl_bot_knowledge')->where('status', 'approved')->orderBy('id')->get()->getResultArray();
            $tmp = $exportPath . '.tmp';
            $fh = fopen($tmp, 'wb');
            if ($fh) {
                foreach ($allApproved as $r) {
                    fwrite($fh, json_encode([
                        'id'         => (int) $r['id'],
                        'title'      => $r['title'],
                        'keywords'   => (string) $r['keywords'],
                        'body'       => (string) $r['body'],
                        'steps'      => json_decode((string) $r['steps_json'], true) ?: [],
                        'page_match' => $r['page_match'],
                        'role_min'   => $r['role_min'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                }
                fclose($fh);
                @rename($tmp, $exportPath);
                $exported = true;
            }
        } catch (\Throwable $e) {
            log_message('error', 'ProblemKnowledgeSeeder export failed: ' . $e->getMessage());
        }

        // 3. Post to AI Service re-index endpoint
        $indexed = false;
        try {
            $aiUrl = rtrim((string) SystemSettings::get('ai.url', 'http://127.0.0.1:8765'), '/');
            $aiKey = (string) SystemSettings::get('ai.key', '');
            $headers = ['Content-Type: application/json'];
            if ($aiKey !== '') {
                $headers[] = 'X-AI-Key: ' . $aiKey;
            }
            $ch = curl_init($aiUrl . '/admin/index');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => '{}',
            ]);
            $res = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $indexed = ($code === 200);
        } catch (\Throwable $e) {
            // Service may be offline or in shadow mode
        }

        return [
            'total'    => count($problems),
            'inserted' => $inserted,
            'updated'  => $updated,
            'exported' => $exported,
            'indexed'  => $indexed,
        ];
    }

    /**
     * Returns the 200 curated problem questions and solutions.
     *
     * @return list<array{title: string, keywords: string, body: string, steps: list<string>, actions?: list<array{type: string, label: string, url: string}>, page_match?: string, error_match?: string, diagnose?: string, role_min?: string}>
     */
    public static function problems(): array
    {
        $dtrTrackerAct = [['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => 'dtr?tab=tracker']];
        $dtrBondingAct = [['type' => 'open', 'label' => 'Open Shoe Bonding', 'url' => 'dtr?tab=bonding']];
        $dtrMatAct     = [['type' => 'open', 'label' => 'Open Material Tests', 'url' => 'dtr?tab=material']];
        $interlinkAct  = [['type' => 'open', 'label' => 'Open InterLink Sync', 'url' => 'interlink']];
        $settingsAct   = [['type' => 'open', 'label' => 'Open Settings', 'url' => 'settings']];

        $p = [];

        // =========================================================================
        // CATEGORY 1: Whole Shoe Bonding (U60) Problems (35)
        // =========================================================================
        $p[] = [
            'title'    => 'Why did my whole shoe bonding test fail on the Center FiTO spot?',
            'keywords' => 'bonding center fito fail, filler to outsole fail, fito spot failure, u60 center fail, bonding rating failed center',
            'body'     => 'Center FiTO (Filler to Outsole) has a standard of 1.0 kg/cm. Under official QC rules, a reading of 1.0 or higher is PASS, 0.80 to 0.99 is TOLERABLE, and anything below 0.80 kg/cm is an automatic FAIL. Because the overall report verdict follows the worst spot group, a single Center FiTO spot below 0.80 marks the whole report as FAIL.',
            'steps'    => ['Check if the reading is below 0.80 kg/cm.', 'Inspect the shoe construction to verify adhesive application at the center cavity.', 'If a spot reading was misaligned, reload the U60 machine output file.', 'Prepare a re-test specimen if required by supervisor.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr', 'diagnose' => 'machine_share',
        ];
        $p[] = [
            'title'    => 'How is the Adult 3.0 kg/cm vs Kids 2.5 kg/cm bonding standard selected?',
            'keywords' => 'adult 3.0, kids 2.5, bonding standard selection, age category standard, toddler standard bonding',
            'body'     => 'The bonding standard is automatically determined from the shoe age category: Adult styles enforce 3.0 kg/cm (tolerable 2.4 - 2.99), while Kids, Toddler, and Children styles enforce 2.5 kg/cm (tolerable 2.0 - 2.49). NEXUS-L reads the AgeCategory directly from the shoe model lookup or the U60 header.',
            'steps'    => ['Verify the Age Category in the bonding header.', 'Press "Refresh & Apply Smart Standards" to re-calculate spot ratings.', 'Confirm whether Adult or Children standard is shown in the STD column.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Spot values are showing outside the shoe diagram in the preview',
            'keywords' => 'spot values outside shoe, dots misaligned, shoe diagram offset, misplaced spot values, bonding layout broken',
            'body'     => 'If spot values or leader lines appear shifted outside the shoe outline, the point coordinates in the Report Designer need adjustment for that specific point count (e.g. 26, 27, 30, or 34 spots). Administrators can drag and save the exact coordinates in Report Designer > Shoe diagram.',
            'steps'    => ['Hard refresh your browser using Ctrl + F5 to reload cached SVG diagram coordinates.', 'If still misaligned, ask an administrator to open Report Designer > Shoe diagram.', 'Select the matching point count and adjust the box positions, then click "Save layout".'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'U60 machine output file has 35 spots but the shoe only has 24 spots',
            'keywords' => '35 spots 24 spots mismatch, too many spots, extra bonding points, spot count difference u60',
            'body'     => 'U60 testing machines record up to 35 channels by default. When testing Children or Toddler shoes that only have 14, 16, or 24 physical spots, the machine software leaves the unused spots blank or zeroes them out. NEXUS-L automatically detects the highest populated spot index and selects the correct 14/16/24/35 diagram.',
            'steps'    => ['Ensure unused channel columns in the machine file are empty rather than filled with phantom readings.', 'Click "Load U60 Machine File" to let the parser detect the true point boundary.', 'Review the resulting diagram on the VF print preview sheet.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Why are bonding spot readings showing 0.0 or blank after loading?',
            'keywords' => 'bonding spots 0.0, blank spot readings, zero readings bonding, machine file empty readings, u60 zero values',
            'body'     => 'Zero or blank spot values occur when: (1) the machine test was saved before all peel passes completed, (2) the Lab Reference No. was typed without the required -L (Left) or -R (Right) side indicator, or (3) the machine Excel sheet layout was modified by a machine operator.',
            'steps'    => ['Check that your Lab Ref No. has the -L or -R suffix (e.g. DTP20261012FAA8-L).', 'Open the machine file from \\\\192.168.32.45\\OUTDATA\\U60\\ to verify data was recorded.', 'Click "Load U60 Machine File" again.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr', 'diagnose' => 'machine_share',
        ];
        $p[] = [
            'title'    => 'A spot rating shows amber TOLERABLE but overall report shows FAIL',
            'keywords' => 'tolerable amber fail overall, why fail when tolerable, worst spot rule, bonding pass priority',
            'body'     => 'NEXUS-L strictly enforces the SATRA priority rule: FAIL > TOLERABLE > PASS. If even a single spot among all UTF, FTO, and FiTO locations drops below the tolerable threshold (<2.4 for Adult, <2.0 for Kids, <0.8 for Center), that single failure causes the entire report verdict to become FAIL, regardless of how many spots were Tolerable.',
            'steps'    => ['Inspect the spot table and look for any cell highlighted in red.', 'Review the minimum spot value in the statistics summary box.', 'If all red spots are resolved on a re-test, save the re-test to update the unified index.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Left shoe passed but right shoe failed: how to record a retest?',
            'keywords' => 'left passed right failed, retest right shoe, bonding retest left right, -L -R retest',
            'body'     => 'Whole shoe bonding treats Left (-L) and Right (-R) shoes as separate test records sharing the same base Lab Reference. If the Right shoe fails, create a new record for the right shoe with re-test designation (e.g. DTP20261012FAA8-R2 or re-run on -R), load the new machine file, and click Save Bonding Report.',
            'steps'    => ['Open Shoe Bonding (U60) and locate the failed -R record.', 'Click "+ New Bonding Report" and enter the retest Lab Reference.', 'Load the new machine file and verify readings.', 'Click "Save Bonding Report". Both records remain in the audit trail.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'U60 machine file name mismatch with -L and -R suffix',
            'keywords' => 'u60 file name mismatch, machine file suffix error, cannot find -L or -R file, outdata file naming',
            'body'     => 'The automated loader searches \\\\192.168.32.45\\OUTDATA\\ for filenames matching either the full Lab Ref (DTP20261012FAA8-L.xlsx) or the base reference containing the side letter in column B. If the machine operator saved the file without the suffix or with lowercase letters, the loader cannot match it.',
            'steps'    => ['Ensure the machine operator saved the file with uppercase uppercase -L or -R.', 'Verify the file exists in the MATERIALS\\U60 folder on the outdata share.', 'Re-enter the exact Lab Reference and click "Load U60 Machine File".'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr', 'diagnose' => 'machine_share',
        ];
        $p[] = [
            'title'    => 'Error loading bonding file: "Corrupt or unreadable U60 worksheet"',
            'keywords' => 'corrupt u60 worksheet, unreadable excel bonding, load results error u60, parse spreadsheet failure',
            'body'     => 'This error indicates that the Excel file on the machine share is currently open and locked by the testing machine PC, or the file was truncated during network saving. NEXUS-L copies the file to writable/cache/ before parsing, but if the source is 0 bytes or locked, loading fails.',
            'steps'    => ['Have the technician on the U60 machine close the test software or save the test run.', 'Wait 10 seconds for file sync across the local network share.', 'Click "Load U60 Machine File" again in NEXUS-L.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr', 'diagnose' => 'machine_share',
        ];
        $p[] = [
            'title'    => 'Toe-to-heel spot numbers are inverted or reversed on shoe diagram',
            'keywords' => 'spot numbers reversed, toe to heel inverted, spot direction backwards, bonding spot numbers wrong way',
            'body'     => 'U60 tests can be performed starting from the Toe going to the Heel, or Heel to Toe depending on sample mounting. If the numbers appear inverted on the shoe diagram, the machine operator tested from the opposite starting point. In Report Designer > Shoe diagram, an administrator can toggle "Invert spot numbering" or adjust the diagram mapping.',
            'steps'    => ['Verify on the physical shoe sample whether spot 1 is at the toe or heel.', 'Check the technician test notes in the bonding form.', 'If needed, notify an administrator to flip the diagram direction in Report Designer.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Toddler 14-spot test shows 26-point diagram: how to fix?',
            'keywords' => 'toddler 14 spot 26 point diagram wrong, diagram size mismatch toddler, toddler shoe wrong diagram',
            'body'     => 'NEXUS-L chooses the diagram based on the highest spot index containing a numerical value. If a Toddler test has leftover test values in channel columns 15 through 26 in the machine output file, the engine automatically selects the 26-point diagram instead of the Toddler 14-point diagram.',
            'steps'    => ['Inspect the spots table in the bonding report.', 'Clear any phantom readings beyond spot 14.', 'Click "Save Bonding Report" and reopen the preview. The correct 14-point Toddler diagram will render.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Foxing to outsole (FTO) failure at heel curve: standard tolerance rules',
            'keywords' => 'fto heel failure, foxing to outsole heel curve, fto tolerance rules, bonding heel failure',
            'body'     => 'The heel curve experiences maximum stress during walking. The standard for Adult FTO is 3.0 kg/cm with tolerable threshold at 2.40 kg/cm. Readings between 2.40 and 2.99 are Tolerable. Readings below 2.40 kg/cm at the heel curve trigger a FAIL and require checking primer application and pressing pressure on the production line.',
            'steps'    => ['Identify the exact heel spot numbers (typically spots 18-22 on adult styles).', 'Inspect the failure mode: adhesive failure (clean peel) vs material tear.', 'Log the failure in Lab Tracker and notify the assembly line supervisor.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Upper to foxing (UTF) peel test shows adhesive failure vs cohesive failure',
            'keywords' => 'adhesive failure cohesive failure, utf peel failure type, bonding failure mode, clean peel vs material tear',
            'body'     => 'Adhesive failure means the glue peeled cleanly off the upper or foxing tape (indicates inadequate primer, curing, or surface treatment). Cohesive failure means the material itself tore before the bond broke (indicates strong adhesion exceeding material strength). Record the failure mode in the Remarks field.',
            'steps'    => ['Inspect the separated surfaces under good lighting.', 'In the bonding form, enter failure mode details into the "Remarks" text area.', 'Click "Save Bonding Report".'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Filler to outsole (FiTO) minimum standard threshold calculation',
            'keywords' => 'fito threshold calculation, filler to outsole minimum, fito 1.0 0.8, center standard bonding',
            'body'     => 'The center filler bond has a lower standard than foxing: Standard = 1.0 kg/cm. Tolerable band = 0.80 kg/cm to 0.99 kg/cm. Failed = < 0.80 kg/cm. This rule applies to both adult and children categories alike.',
            'steps'    => ['Look at the Center FiTO rows in the bonding table.', 'Verify that no reading drops below 0.80 kg/cm.', 'Use "Refresh & Apply Smart Standards" to ensure the 1.0/0.8 rule is applied.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Bonding test date does not match the received date on ticket',
            'keywords' => 'bonding test date mismatch, received date tested date different, date ticket bonding',
            'body'     => 'The Received Date represents when the shoe arrived in the lab, while the Tested Date represents when the U60 machine peel test was performed. In NEXUS-L, both dates are maintained independently. If the tested date is missing or wrong, click the date picker to align it with the machine run date.',
            'steps'    => ['In Shoe Bonding (U60), check the "Received Date" and "Tested Date" fields.', 'Select the correct calendar date.', 'Click "Save Bonding Report".'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'How to manually override an erroneous machine reading on a bonding spot?',
            'keywords' => 'override machine reading bonding, manual spot edit, fix wrong spot value, change spot reading',
            'body'     => 'If a machine sensor slip caused a false reading (e.g. gripper slipped during initial clamping), technicians can edit the specific spot input box in the bonding table directly, provide a justification in the Remarks field, and click "Save Bonding Report". The audit trail logs the change.',
            'steps'    => ['Click directly into the spot input box you need to adjust.', 'Type the corrected reading (in kg/cm).', 'Add an explanatory note in the Remarks box explaining the clamp slip.', 'Click "Save Bonding Report".'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Why does the bonding diagram display "No spot data available"?',
            'keywords' => 'no spot data available, bonding diagram blank, empty spots diagram, no points rendered',
            'body'     => 'This occurs when a new bonding report header was created and saved before clicking "Load U60 Machine File". Without spot data loaded from the machine file, the shoe diagram has no values to plot.',
            'steps'    => ['Make sure the Lab Ref No. is typed correctly.', 'Click "Load U60 Machine File".', 'Confirm that the spot readings table fills with numbers.', 'Click "Save Bonding Report".'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr', 'diagnose' => 'machine_share',
        ];
        $p[] = [
            'title'    => 'Bonding report print preview crops the right-hand spot table',
            'keywords' => 'print preview crops spot table, bonding report cut off right, table clipped preview, print bonding scale',
            'body'     => 'NEXUS-L print sheets are formatted for standard ISO A4 paper (210 x 297 mm) with 6 mm margins. If your browser print zoom or Windows display scale is set to 125% or 150%, the print sheet preview may appear cropped on screen.',
            'steps'    => ['Press Ctrl + 0 in your browser to reset zoom to 100%.', 'In the print preview dialog, ensure Destination is set to "Save as PDF" or your printer, and Paper Size is "A4".', 'Set Scale to "Default" or "100%".'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Missing technician name on whole shoe bonding test report',
            'keywords' => 'missing technician name bonding, tech name empty, technician required bonding, who tested bonding',
            'body'     => 'The Technician field is required on the official report sheet. NEXUS-L attempts to autofill it from your signed-in user profile, but if your account has no full name set or if another technician performed the test, choose their name from the Technician dropdown before saving.',
            'steps'    => ['In the bonding form, locate the "Technician" dropdown.', 'Select the technician who operated the U60 machine.', 'Click "Save Bonding Report". The name will now appear on the printed sheet.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'U60 machine output folder \\\\192.168.32.45\\OUTDATA\\ access denied',
            'keywords' => 'outdata access denied, permission denied 192.168.32.45, network share locked outdata, cannot open u60 share',
            'body'     => 'The machine file share runs on local laboratory network IP 192.168.32.45. If access is denied, either the web server service account lacks read permissions on the share, or the network switch between the web server and the QC lab machines is unreachable.',
            'steps'    => ['Check Settings > AI & System Status to verify network reachability.', 'Ask IT to ensure user "Everyone" or the web server machine account has Read permissions on \\\\192.168.32.45\\OUTDATA.', 'In Report Designer > File locations, click "Test location" to confirm connection.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'diagnose' => 'machine_share', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to link multiple shoe styles to one bonding test?',
            'keywords' => 'multiple shoe styles bonding, link styles bonding, shared bonding test, several models bonding',
            'body'     => 'When a single whole shoe bonding test covers shared tooling or colorways, enter the primary style number in Model No / Style, and enter additional style numbers or POs in the Remarks or PO/Lot No field separated by commas. All styles will be searchable in the Lab Tracker.',
            'steps'    => ['Enter the main style code in "Model No / Style".', 'Add secondary styles into "Remarks" (e.g. "Also covers VN0A5JISBLK, VN000D3HY28").', 'Click "Save Bonding Report".'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Why did Smart Standards set bonding rating to UNRATED?',
            'keywords' => 'bonding unrated smart standards, rating unrated bonding, why unrated bonding',
            'body'     => 'A rating shows UNRATED when the Age Category is missing or unrecognized, or when no numerical readings exist in the spot columns. Without an age category, the system cannot decide whether to apply the 3.0 kg/cm or 2.5 kg/cm threshold.',
            'steps'    => ['Select the appropriate Age Category (Adult, Kids, Toddler, or Children).', 'Verify that spot values are populated.', 'Click "Refresh & Apply Smart Standards", then Save.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Machine reading shows 3.42 kg/cm but rating displays FAIL',
            'keywords' => '3.42 fail, high reading shows fail, machine value high but fail, bonding rating bug',
            'body'     => 'Even if an individual spot has a high value like 3.42 kg/cm, the overall test verdict is determined by the minimum spot across the entire shoe. If another spot on the same shoe scored below 2.40 kg/cm, the entire report displays FAIL.',
            'steps'    => ['Sort or scan the spot table to find the lowest value.', 'Confirm whether any spot is <2.40 kg/cm (Adult) or <2.0 kg/cm (Kids).', 'The overall stamp reflects the worst spot, not the average.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Bonding test report missing SATRA accreditation header mark',
            'keywords' => 'missing satra accreditation mark, satra logo missing bonding, accredited lab mark header',
            'body'     => 'The official VF report header includes the SATRA Accredited Laboratory No. 08A03 mark on the top right. If it is missing from your preview, your browser may be rendering a cached version of an older stylesheet.',
            'steps'    => ['Press Ctrl + F5 while viewing the preview to perform a hard stylesheet reload.', 'Ensure "Download PDF" is used rather than browser print to get vector-rendered logos.', 'If still missing, check that the SATRA header is enabled in Report Designer.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'How to print both Left and Right bonding sheets on one PDF?',
            'keywords' => 'print left right one pdf, combined bonding pdf, 2 page bonding report, merge left right bonding',
            'body'     => 'In Shoe Bonding (U60), open the Left shoe (-L) record and click "Print / Preview Bonding Report". The preview dialog includes a "Merge Right Shoe (-R)" button that automatically pulls the matching right shoe report and compiles a unified 2-page PDF.',
            'steps'    => ['Open the -L bonding report.', 'Click "Print / Preview Bonding Report".', 'In the preview toolbar, click "Include Right Shoe (-R)".', 'Click "Download Combined PDF".'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Why are spot leader lines overlapping on small toddler shoes?',
            'keywords' => 'leader lines overlapping toddler, lines crossing diagram, spot lines messy toddler, small shoe diagram overlap',
            'body'     => 'On Toddler shoes (sizes 4-9), the physical area of the sole is very compact, which can cause SVG leader lines to crowd if using adult coordinate offsets. Administrators can tune the Toddler 14-point diagram layout in Report Designer > Shoe diagram.',
            'steps'    => ['Open Report Designer > Shoe diagram as Admin.', 'Select point count "14 (Toddler)".', 'Drag the coordinate boxes slightly outward to create breathing room.', 'Click "Save layout".'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Bonding adhesive supplier code lookup fails in DSR list',
            'keywords' => 'adhesive supplier code lookup fail, dsr adhesive not found, glue supplier lookup, bonding adhesive code',
            'body'     => 'The adhesive supplier code (e.g. Nan Pao, Great Eastern) is checked against tbl_dtr_materials_master. If typing the supplier code does not populate the name, the supplier has not yet been added to the master list in dbnexus.',
            'steps'    => ['Type the adhesive supplier name manually into the Adhesive field.', 'Ask an administrator to add the supplier code into Master Data > Materials Master.', 'Click "Save Bonding Report".'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Retest bonding record overwrites original test: how to keep both?',
            'keywords' => 'retest overwrites original, keep both bonding tests, duplicate lab ref bonding, preserve original test',
            'body'     => 'NEXUS-L uses the Lab Reference Number as a unique key for the index. To keep both the original failed test and the re-test, append a re-test indicator to the Lab Ref (e.g. DTP20261012FAA8-L-RT or -L2). This guarantees both records remain in the database history.',
            'steps'    => ['When creating the retest record, add "-RT" or "R2" to the Lab Ref No.', 'Load the new machine file.', 'Click "Save Bonding Report". Both the original FAIL and retest PASS remain accessible.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'U60 machine curve graph image not rendering in report preview',
            'keywords' => 'curve graph image missing, u60 graph not showing, peel curve image blank, bonding graph missing',
            'body'     => 'If the peel force curve graph is missing from page 2 of the bonding report, the U60 testing machine did not export the companion image file (.bmp/.png) to \\\\192.168.32.45\\OUTDATA\\U60\\GRAPHS\\. The report sheet can still be printed with all numerical spot tables.',
            'steps'    => ['Check if the machine export setting "Export Chart Image" is enabled on the U60 PC.', 'Verify the image exists with the matching Lab Ref filename.', 'If unavailable, the numerical spot diagram on page 1 serves as the certified test record.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr', 'diagnose' => 'machine_share',
        ];
        $p[] = [
            'title'    => 'Whole shoe bonding test report status stuck on "In Progress"',
            'keywords' => 'bonding status stuck in progress, status not complete bonding, mark complete bonding',
            'body'     => 'A report remains "In Progress" until all required fields (Technician, Age Category, Overall Verdict) are populated and "Save Bonding Report" is pressed. Once saved with valid readings, the status automatically updates to "Complete".',
            'steps'    => ['Open the report and verify that Technician, Age Category, and Results are set.', 'Click "Refresh & Apply Smart Standards".', 'Click "Save Bonding Report". Status will switch to Complete.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Why is the VF logo missing on the bonding report sheet?',
            'keywords' => 'vf logo missing bonding, brand logo missing header, vf logo not showing print',
            'body'     => 'The VF logo is embedded in the report header as a vector asset. If it fails to display, the local cache in writable/cache/assets or the browser cache has an incomplete download.',
            'steps'    => ['Press Ctrl + F5 in the browser to clear cached images.', 'If exporting via "Download PDF", verify that node canvas rendered the header properly.', 'Contact an administrator if the asset path in App.php has changed.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Tolerance band calculation: why did 2.39 kg/cm fail when standard is 3.0?',
            'keywords' => '2.39 failed 3.0 standard, tolerance limit 2.4, why 2.39 failed, bonding tolerance calculation',
            'body'     => 'Under the 80% tolerance rule for Adult styles with a standard of 3.0 kg/cm: 80% of 3.0 = 2.40 kg/cm. Therefore, 2.40 to 2.99 is Tolerable. A reading of 2.39 kg/cm is strictly below 2.40 kg/cm, which classifies it mathematically as FAILED.',
            'steps'    => ['Verify the exact decimal reading (2.39 is below the 2.40 minimum tolerable cutoff).', 'Review with the QC supervisor to determine if a re-test is required.', 'Record the result in the official log.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Bonding test age category mismatch between shoe list and U60 file',
            'keywords' => 'age category mismatch shoe list u60, category conflict bonding, adult vs kids conflict',
            'body'     => 'If the shoe list master lists the model as Adult but the U60 operator selected Kids on the machine software, NEXUS-L flags a warning banner. The master shoe list takes precedence to prevent under-testing adult shoes under the lower 2.5 kg/cm standard.',
            'steps'    => ['Check the shoe style number against the production tech pack.', 'If the shoe is indeed an Adult model, change Age Category to Adult.', 'Click "Refresh & Apply Smart Standards" to recalculate under the 3.0 kg/cm standard.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Bonding points table scrolls off the screen on low resolution laptops',
            'keywords' => 'bonding table scrolls off screen, table too wide laptop, screen resolution bonding, table responsive',
            'body'     => 'The 35-column spot table has horizontal scrolling enabled for smaller laptop screens (1366x768). You can collapse the sidebar using the top-left hamburger menu to gain an extra 260px of screen width.',
            'steps'    => ['Click the three-line menu button at the top left to collapse the sidebar.', 'Use Shift + Mouse Wheel to scroll horizontally through the spot columns.', 'Or zoom out slightly in Chrome (Ctrl + Minus).'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'How to delete a duplicate bonding report created by mistake?',
            'keywords' => 'delete duplicate bonding report, remove wrong bonding record, delete test report admin',
            'body'     => 'To maintain strict lab audit integrity, only users with Role = Admin can permanently delete a report. Regular users can mark the report as "Superseded" or "Cancelled" in the Status dropdown, or ask an Admin to delete it from the Lab Tracker.',
            'steps'    => ['If you are an Admin, open Lab Tracker, find the record, and click "Delete".', 'If you are a regular user, set Status to "Cancelled" and add a note in Remarks.', 'Click "Save Bonding Report".'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'dtr',
        ];

        // =========================================================================
        // CATEGORY 2: Material Test Forms & Computations (35)
        // =========================================================================
        $p[] = [
            'title'    => 'Fabric tensile strength elongation average calculation mismatch',
            'keywords' => 'fabric tensile elongation mismatch, average calculation wrong tensile, directional average fabric',
            'body'     => 'Tensile strength and elongation are calculated separately for Warp (Length) and Weft (Width) directions using the 3-specimen directional average formula: Ave = (Specimen 1 + Specimen 2 + Specimen 3) / 3. If any specimen was untested (0), it must not be included in the denominator.',
            'steps'    => ['Press "Refresh & Apply Smart Standards" on the fabric report form.', 'Verify that specimens 1, 2, and 3 have non-zero numbers.', 'Check that Warp and Weft averages match the directional formula.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Trouser tear test readings in warp vs weft directions',
            'keywords' => 'trouser tear warp weft, wing tear direction, tear strength calculation, fabric tear test',
            'body'     => 'Trouser tear requires testing 3 specimens cut parallel to warp yarns and 3 specimens cut parallel to weft yarns. The standard specifies minimum tear force in Newtons (N) or kgf. NEXUS-L checks each direction against the material master standard independently.',
            'steps'    => ['Enter the 3 Length (Warp) readings and 3 Width (Weft) readings.', 'Click "Refresh & Apply Smart Standards".', 'Verify that both directional ratings show PASS.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Stitch tear strength test failure on leather materials',
            'keywords' => 'stitch tear failure leather, leather stitch tear weak, leu lel stitch tear standard',
            'body'     => 'Stitch tear tests evaluate the strength of leather when perforated by needle stitching. Standard requirement is typically >= 70 N. If a specimen tears below standard, inspect if leather thickness is below specification or if specimen was cut across the flank rather than the butt.',
            'steps'    => ['Check the leather thickness average in the sample info block.', 'Verify if the thickness-dependent standard applies (thinner leathers have adjusted standards).', 'Log the failure and notify the leather cutting department.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Mullen burst test pressure unit conversion (psi vs kPa vs kg/cm2)',
            'keywords' => 'mullen burst pressure conversion, psi kpa kg/cm2 burst, burst strength units',
            'body'     => 'NEXUS-L records burst strength in kPa by default, matching InterLink portal specifications. If your machine outputs in psi: 1 psi = 6.89476 kPa. If in kg/cm2: 1 kg/cm2 = 98.0665 kPa. The built-in converter automatically aligns units when loading machine files.',
            'steps'    => ['Check which unit your Mullen Burst machine is configured to output.', 'In Calculators & Standards, verify the active conversion factor.', 'Confirm the reading displayed on the form is in kPa.'],
            'actions'  => [['type' => 'open', 'label' => 'Open Calculators', 'url' => 'dtr?tab=calculators']], 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => '6-point colorfastness test: acetate staining rating shows FAIL',
            'keywords' => '6 point colorfastness acetate fail, multifiber staining failure, color fastness rating fail',
            'body'     => 'The 6-point multifiber strip evaluates staining on Wool, Acrylic, Polyester, Nylon, Cotton, and Acetate using the ISO / AATCC Grey Scale (ratings 1 to 5, where 5 is no staining). Under Vans/VF standards, minimum rating is typically 3.5 or 4.0. If Acetate staining is rated 2.5 or 3.0, the overall test is marked FAIL.',
            'steps'    => ['Compare the test strip against the certified Grey Scale for Staining in a viewing booth (D65 light).', 'Enter the numerical ratings 1.0 - 5.0 for all 6 fibers.', 'If Acetate failed, record shade bleeding remarks.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Crocking colorfastness wet vs dry rubbing difference',
            'keywords' => 'crocking wet dry difference, rubbing fastness crockmeter, crock test failure',
            'body'     => 'Crocking measures color transfer from surface rubbing. Dry crocking requirement is higher (typically >= Grade 4.0), while Wet crocking allows a slightly lower threshold (typically >= Grade 3.0 or 3.5) due to moisture-induced dye transfer. Both must meet standard.',
            'steps'    => ['Enter the Dry Crocking rating (1.0 to 5.0).', 'Enter the Wet Crocking rating.', 'Click "Refresh & Apply Smart Standards".'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Ross flex test cold temperature puncture growth limit',
            'keywords' => 'ross flex cold test, cut growth puncture ross flex, rubber flex failure cold',
            'body'     => 'Ross flex testing evaluates rubber and outsoles at -10°C or room temperature over 50,000 to 150,000 cycles. Standard specifies maximum cut growth (e.g. <= 4.0 mm). If cut growth exceeds standard or sole cracks through, the rating is FAIL.',
            'steps'    => ['Measure the initial puncture cut with calipers (typically 2.0 mm).', 'Measure final cut length after completed cycles.', 'Enter cut growth in mm into the Ross Flex calculator.'],
            'actions'  => [['type' => 'open', 'label' => 'Open Calculators', 'url' => 'dtr?tab=calculators']], 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'DIN abrasion volume loss calculation formula',
            'keywords' => 'din abrasion volume loss calculation, din abrasion formula, abrasion resistance rubber',
            'body'     => 'DIN abrasion volume loss in mm³ is computed as: Volume Loss = (Mass Loss in mg / Specific Gravity) * (Standard Reference Rubber Constant / Actual Reference Rubber Mass Loss). Standard requirement for vulcanized rubber is typically <= 150 mm³.',
            'steps'    => ['In Calculators & Standards, open the DIN Abrasion calculator.', 'Enter initial mass, final mass, specific gravity, and standard rubber loss.', 'Click "Apply to Material Form" to transfer the result.'],
            'actions'  => [['type' => 'open', 'label' => 'Open Calculators', 'url' => 'dtr?tab=calculators']], 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Compression set test recovery percentage computation',
            'keywords' => 'compression set recovery computation, foam compression set formula, sklfm compression set',
            'body'     => 'Compression set of EVA or PU foam after 22 hours at 50°C is: Set % = [(t0 - t1) / (t0 - ts)] * 100, where t0 = original thickness, t1 = recovered thickness after 30 min, and ts = spacer thickness. Lower percentage means better cushioning retention (standard is <= 40% or 50%).',
            'steps'    => ['Enter original thickness t0, spacer thickness ts, and final thickness t1.', 'Review the calculated Compression Set percentage.', 'Click "Apply to Form".'],
            'actions'  => [['type' => 'open', 'label' => 'Open Calculators', 'url' => 'dtr?tab=calculators']], 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Specific gravity test water displacement method calculation',
            'keywords' => 'specific gravity water displacement, density calculation rubber, sg ave calculation',
            'body'     => 'Specific gravity is: SG = Weight in Air / (Weight in Air - Weight in Water). Rubber outsoles typically require SG between 1.15 and 1.25 depending on compound. NEXUS-L averages up to 3 specimen SG readings automatically.',
            'steps'    => ['Weigh specimen in air (A).', 'Weigh specimen suspended in distilled water (B).', 'SG = A / (A - B). Enter into the Specific Gravity fields.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Fabric thickness 1-6 readings average not updating min/max',
            'keywords' => 'thickness average not updating, min max thickness not computing, fabric thickness ave',
            'body'     => 'The thickness fields calculate Min, Max, and Average automatically as you type or load values. If the summary fields stay blank, click "Refresh & Apply Smart Standards" or ensure JavaScript is active in your browser.',
            'steps'    => ['Enter readings into Thickness 1, 2, 3, 4, 5, 6.', 'Click "Refresh & Apply Smart Standards".', 'Confirm that Min, Max, and Ave fields populate.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Rubber outsole hardness Shore A readings out of tolerance',
            'keywords' => 'rubber hardness shore a out of tolerance, durometer reading fail, rubber hardness standard',
            'body'     => 'Durometer hardness (Shore A) is measured at 5 points on the outsole. Standard requirement is typically 58 - 65 Shore A. Readings below 58 are too soft (poor wear life); readings above 65 are too stiff (poor grip/comfort).',
            'steps'    => ['Verify the durometer calibration using certified test blocks.', 'Measure 5 points along heel, arch, and forefoot.', 'Enter the 5 readings into the Hardness table to compute the average.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Carton box edge crush test (ECT) calculation formula',
            'keywords' => 'carton box edge crush test ect, box ect formula, inner box outer box crush test',
            'body'     => 'Edge Crush Test (ECT) measures compressive strength in kN/m or lbf/in. For outer shipping cartons, requirement is typically >= 5.5 kN/m (32 ECT). The 3-specimen peak load average is computed automatically from the crush tester output file.',
            'steps'    => ['In Carton Box Form (IB/OB), enter the 3 peak load values.', 'The system computes the average ECT in kN/m.', 'Click "Refresh & Apply Smart Standards" to check compliance.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Insole board flex resistance failure at 100,000 cycles',
            'keywords' => 'insole board flex resistance failure, insole cracking cycles, sl insole flex test',
            'body'     => 'Insole boards must withstand 100,000 continuous bending cycles without cracking, delaminating, or flaking. If cracks exceed 5 mm before reaching 100,000 cycles, the rating is marked FAIL.',
            'steps'    => ['Inspect the flexed crease line of the insole board with a magnifying loupe.', 'Record the cycle count where failure occurred.', 'Click "Save Report to NEXUS-L".'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Heel logo pull strength test failure on PU coated material',
            'keywords' => 'heel logo pull strength failure, heel badge adhesion fail, fthl pull strength',
            'body'     => 'The heel logo badge must withstand a minimum perpendicular pull force of 200 N or peel strength of 2.5 kg/cm. If the badge separates from the foxing tape, check whether primer was applied to the PU backing.',
            'steps'    => ['Check the clamp grip alignment on the tensile tester.', 'Verify peak load reading in Newtons.', 'Record failure mode (badge separation vs tape tear) in Remarks.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Foxing tape adhesion test dry peel vs wet peel difference',
            'keywords' => 'foxing tape adhesion dry wet, fthl dry peel wet peel, foxing tape adhesion difference',
            'body'     => 'Dry adhesion evaluates bonding under standard atmospheric conditions (>= 2.5 kg/cm). Wet adhesion tests bonding after immersion in distilled water for 24 hours (allowed threshold >= 2.0 kg/cm). Both dry and wet passes are required for vulcanized tape.',
            'steps'    => ['Record Dry Peel readings for specimens 1, 2, 3.', 'Record Wet Peel readings after 24h water soak.', 'Verify that both directional averages meet their respective standards.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Webbing shoelace tensile break strength standard lookup',
            'keywords' => 'shoelace tensile break standard, webbing strength lookup, wst test standards',
            'body'     => 'Shoelaces and webbings must satisfy minimum tensile break strength: shoelaces typically >= 450 N, tubular webbing >= 800 N. The Smart Standards engine checks the material code in tbl_dtr_standards to determine the specific requirement.',
            'steps'    => ['Enter the Material Code in the WST form.', 'Click "Auto-Lookup Material / PO / DSR".', 'Click "Refresh & Apply Smart Standards" to display the threshold.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Woven size label perspiration staining rating failed',
            'keywords' => 'woven size label perspiration fail, label dye bleed sweat, label perspiration staining',
            'body'     => 'Labels placed against the foot or collar are subject to alkaline and acidic artificial perspiration testing. If color stains the lining fabric with a rating < 3.5, the label fails perspiration fastness.',
            'steps'    => ['Rate color change and multifiber staining under D65 lighting.', 'Enter ratings into the Woven Label test form.', 'If failed, recommend dye fixing treatment to the vendor.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Sockliner foam density calculation error from raw weight',
            'keywords' => 'sockliner foam density calculation, foam density formula, sklfm density error',
            'body'     => 'Foam density in g/cm³ is: Density = Weight (g) / Volume (cm³), where Volume = Length * Width * Thickness. Ensure dimensions are entered in centimeters, not millimeters, to avoid 1000x calculation errors.',
            'steps'    => ['Measure die-cut specimen dimensions in cm.', 'Weigh specimen on analytical balance to 0.001 g.', 'Enter into Sockliner & Foam form to compute density.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Chinese material descriptions showing as ??? on printed report',
            'keywords' => 'chinese descriptions showing as ???, question marks chinese text, cjk encoding error pdf',
            'body'     => 'In Phase 196, NEXUS-L implemented pure Node.js vector PDF rendering. Chinese characters require the Microsoft YaHei CJK font cache located at writable/cache/fonts/cjk.ttf. If this font file is missing, characters render as ???.',
            'steps'    => ['Ask an administrator to run `python tools/make-cjk-font.py` on the server.', 'Confirm writable/cache/fonts/cjk.ttf exists.', 'Hard refresh (Ctrl + F5) and generate the report PDF again.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Material test report preview shows blank table rows',
            'keywords' => 'material report preview blank rows, empty table print preview, no readings displayed preview',
            'body'     => 'Untested test items or rows with 0.0 values intentionally print blank in Phase 196+ layouts, matching official Access formatting where untested items are left unprinted rather than filled with zeroes.',
            'steps'    => ['Ensure machine results were loaded for the specific test item.', 'Click "Refresh & Apply Smart Standards" so ratings populate.', 'Click "Print / Preview Report" to inspect populated rows.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'How to apply Smart Standards when material thickness is between brackets?',
            'keywords' => 'thickness between brackets smart standards, thickness dependent lookup, standard lookup bracket',
            'body'     => 'Many standards in tbl_dtr_standards have thickness ranges (e.g. 1.0 - 1.4 mm: STD 120 N; 1.5 - 2.0 mm: STD 180 N). Smart Standards checks the average thickness and selects the standard matching the bracket automatically.',
            'steps'    => ['Enter all thickness readings to calculate the true average.', 'Click "Refresh & Apply Smart Standards".', 'NEXUS-L matches the average thickness against the range in the standards table.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Why did "Refresh & Apply Smart Standards" clear custom remarks?',
            'keywords' => 'smart standards cleared remarks, custom remarks overwritten, refresh remarks bug',
            'body'     => 'Smart Standards updates numerical ratings, averages, and pass/fail stamps, but preserves existing technician remarks. If remarks disappeared, the form was reloaded without saving.',
            'steps'    => ['Always enter your remarks before clicking "Save Report to NEXUS-L".', 'If updating standards, do so before writing custom notes.', 'Click Save to persist changes to dbnexus.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Hardware eyelet corrosion resistance salt spray test rating',
            'keywords' => 'eyelet corrosion salt spray rating, hardware rust rating, astm b117 hardware',
            'body'     => 'Eyelets and grommets undergo 24h or 48h neutral salt spray testing (ASTM B117). Any visible red rust on steel or white corrosion on zinc before the required exposure hours constitutes a FAIL.',
            'steps'    => ['Examine specimens under 10x magnification after rinsing and drying.', 'Record corrosion presence and hours to failure in Hardware form.', 'Save report.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Zipper crosswise strength test failure: slider separation',
            'keywords' => 'zipper crosswise strength failure, zipper slider separation, zipper pull test fail',
            'body'     => 'Zippers must withstand lateral pulling force across the chain (standard >= 400 N for #5 zippers). If teeth separate or slider dislodges below standard, report as FAIL.',
            'steps'    => ['Record peak tensile force before chain burst.', 'Note whether teeth broke, tape ripped, or slider came off.', 'Save Hardware report.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Elastic gore elongation recovery failure after fatigue cycles',
            'keywords' => 'elastic gore recovery failure, elastic fatigue test, gore tension loss',
            'body'     => 'Elastic gores are cycled to 50% elongation for 10,000 cycles. Tension loss and permanent set must not exceed 10%. Excess slack causes loose fit in slip-on shoes.',
            'steps'    => ['Measure original relaxed length L0.', 'Measure length L1 after 10,000 cycles and 30 min recovery.', 'Permanent Set % = [(L1 - L0) / L0] * 100.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Leather water spotting resistance rating evaluation',
            'keywords' => 'leather water spotting rating, water drop stain leather, leather water mark rating',
            'body'     => 'Distilled water drops are placed on leather for 30 minutes, then dried. Color change or surface swelling is rated against the Grey Scale. Minimum acceptable rating is 4.0.',
            'steps'    => ['Evaluate color change and physical ring marks.', 'Enter rating into Leather form.', 'Save report.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Suede dye bleeding test with neon lining material',
            'keywords' => 'suede dye bleed neon lining, suede bleeding migration, dye migration test',
            'body'     => 'Suede tested against light or neon linings under heat and pressure (50°C, 24h) evaluates dye migration. Color transfer must meet Grade 4.0 minimum on Grey Scale.',
            'steps'    => ['Inspect the multi-fiber or neon fabric contact patch.', 'Rate dye transfer severity.', 'Record in Leather report.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Die C tear strength average directional formula',
            'keywords' => 'die c tear formula, rubber die c tear average, tear strength calculation',
            'body'     => 'Die C tear measures tear resistance in kN/m or lbf/in: Tear Strength = Peak Force (N) / Thickness (mm). The average of 3 specimens is calculated automatically in NEXUS-L.',
            'steps'    => ['Enter thickness of each Die C specimen.', 'Enter peak load from tensile machine.', 'NEXUS-L calculates tear strength in kN/m automatically.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Material report print preview cuts off technician signature line',
            'keywords' => 'print preview cuts off signature line, signature line missing material report, footer clipped',
            'body'     => 'If the bottom signature block is cut off, verify your printer margin settings in Chrome/Edge print dialog: set Margins to "None" or "Default", and turn off "Headers and footers".',
            'steps'    => ['In the print preview dialog, expand "More settings".', 'Uncheck "Headers and footers".', 'Set Margins to "Default".'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Auto-lookup material code returns "Material not found in master list"',
            'keywords' => 'material not found in master list, auto lookup material failed, missing material code master',
            'body'     => 'This means the material code entered is not registered in tbl_dtr_materials_master. You can type the description and supplier manually, then ask an administrator to add the code.',
            'steps'    => ['Verify the material code on the lab receipt ticket.', 'Enter the material description and supplier manually.', 'Contact an admin to sync Master Data with ERP.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'PO/Lot number auto-lookup returns multiple shoe styles',
            'keywords' => 'po lot lookup multiple styles, duplicate po shoe list, po lot ambiguous',
            'body'     => 'When a single purchase order includes multiple colorways or styles, the lookup dialog displays a selection picker so you can choose the exact style for this test report.',
            'steps'    => ['Type the PO/Lot number and press Tab.', 'Select the matching style from the popup picker.', 'The model style, brand, and season will populate.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'MTL number lookup in DSR tracker does not populate vendor name',
            'keywords' => 'mtl lookup missing vendor, dsr tracker vendor blank, mtl dsr lookup',
            'body'     => 'If typing the MTL number fails to populate the vendor name, the DSR tracking record in tbl_dtr_dsr does not have a vendor linked. Enter the vendor manually.',
            'steps'    => ['Type the vendor name into the Vendor / Factory field.', 'Click Save Report to store the information in dbnexus.', 'The unified tracker index will record the vendor.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'How to re-order material test categories on the material form?',
            'keywords' => 'reorder test categories material form, change test order, form field order designer',
            'body'     => 'Administrators can change the display order of test sections and fields in Report Designer > Form fields using the Up and Down arrow buttons.',
            'steps'    => ['Open Report Designer > Form fields as Admin.', 'Select the target material form (e.g. Fabric FBU/FBL).', 'Use the arrows to rearrange fields and click "Save form fields".'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Material test overall stamp shows PASS but one specimen failed',
            'keywords' => 'stamp pass but specimen failed, overall result error material, why pass when failed specimen',
            'body'     => 'Some standards evaluate compliance on the mathematical 3-specimen average, not individual specimens. If the average satisfies the standard, the rating passes even if one specimen dipped slightly below.',
            'steps'    => ['Check if the standard is defined on Average or Individual Specimen.', 'Click "Refresh & Apply Smart Standards".', 'If individual compliance is strictly required, update the rating manually.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];

        // =========================================================================
        // CATEGORY 3: Approvals, Signatories & QR Workflows (30)
        // =========================================================================
        $p[] = [
            'title'    => 'QR approval code expired: how to get a new approval link?',
            'keywords' => 'qr approval code expired, approval link expired, token expired approval, request approval again qr',
            'body'     => 'Approval links expire automatically after the configured link window (default 60 minutes) to prevent unauthorized late signatures. To generate a fresh live link, open the report and click "Request approval (QR)" again, or tell the bot "Resend notification for approver".',
            'steps'    => ['Open the report in Report View or Upload Form.', 'Click "Request approval (QR)".', 'A new QR code and single-use link are generated immediately.', 'Or ask the bot: "Resend notification for approver".'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Error: "This single-use approval link has already been spent"',
            'keywords' => 'approval link already spent, single use token dead, approval link reused, token spent error',
            'body'     => 'Approval links are strictly single-use: once a manager or supervisor signs or rejects the report, the link token is immediately retired ("spent") and replaced with a non-reusable token to prevent replay attacks.',
            'steps'    => ['Check the approval status in Lab Tracker: if already signed, download the signed PDF.', 'If the report was rejected and needs a re-sign, generate a new approval link.', 'Do not share old bookmarked approval URLs.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Approval sheet says "Approved by Manager X" but Manager Y scanned',
            'keywords' => 'wrong manager scanned approval, required signatory mismatch, approved by name mismatch',
            'body'     => 'In Phase 194+, the name printed after "Approved by" on the report is the exact person who must sign it. If Manager Y attempts to approve a report designated for Manager X, the approval system refuses the signature with an authorization error.',
            'steps'    => ['Check the printed name on the report preview.', 'Ensure the matching manager signs in to approve.', 'If Manager Y is substituting, regenerate the report with Manager Y designated as approver.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Manager scanned QR code on phone but sign-in page keeps looping',
            'keywords' => 'qr code sign in loop mobile, phone approval login loop, session cookie mobile qr',
            'body'     => 'If scanning the QR code in WeChat, Synology Chat, or an in-app browser loops on login, the in-app browser is blocking cross-site session cookies. Tap the three dots (...) and select "Open in Chrome" or "Open in Safari".',
            'steps'    => ['In the mobile scanner, tap options (...) and choose "Open in browser".', 'Sign in with your NEXUS-L credentials.', 'The approval sheet will load and allow digital signing.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Signatory signature PNG image background is black instead of transparent',
            'keywords' => 'signature background black, transparent signature png, signature stamp black box',
            'body'     => 'If a signature image was saved with an inverted alpha channel, it may appear with a solid black box. In Report Designer > Signatories, use the "Remove background" tool or upload a clean PNG with transparent alpha background.',
            'steps'    => ['Open Report Designer > Signatories as Admin.', 'Upload a high-contrast signature on white paper.', 'The built-in background remover will make the background 100% transparent.', 'Click Save Signatory.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to change the approval link expiration time from 60 minutes?',
            'keywords' => 'change approval expiration time, approval link minutes settings, approval timeout config',
            'body'     => 'Administrators can adjust the approval link lifetime in Settings > Report Approval > "Approval link works for (minutes)". You can set it to 120 minutes or longer for managers who review reports in batches.',
            'steps'    => ['Sign in as Admin and open Settings (/settings).', 'Scroll to "Report Approval".', 'Change "Approval link works for (minutes)" to your desired time.', 'Click Save.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Approval notification not arriving in Synology Chat for supervisor',
            'keywords' => 'synology notification not arriving approval, supervisor synology missing, chat notification fail',
            'body'     => 'Synology Chat notifications require: (1) Synology Chat address and bot token configured in Settings, and (2) the supervisor user account in tbl_users must have a valid SynologyID mapped to their Synology Chat user ID.',
            'steps'    => ['Check Settings > Synology Chat to verify webhook connectivity.', 'In User Management, verify the supervisor account has their numeric SynologyID set.', 'Use the bot: "Resend notification for approver" to test.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Why does the approval page show "HTTP 404 - Approval request superseded"?',
            'keywords' => 'approval request superseded, 404 approval gone, report superseded qr',
            'body'     => 'When a laboratory technician regenerates or edits a report that had an active pending approval, the previous approval request is automatically marked "superseded" so an outdated report version cannot be signed.',
            'steps'    => ['Ask the technician for the updated approval link.', 'Technician can click "Request approval (QR)" to create a live link for the newest version.', 'Sign the newest version.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Report was edited after approval: why is approval status revoked?',
            'keywords' => 'edited after approval status revoked, approved report modified, re-approval required',
            'body'     => 'To prevent unauthorized data alteration after managerial sign-off, editing any reading or rating on an approved report automatically marks the approval as superseded. The modified report must be re-submitted for approval.',
            'steps'    => ['Review the edits made to the report.', 'Click "Request approval (QR)" to submit the updated sheet.', 'The manager receives a new notification to approve the changes.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'How to register a new signatory in Report Designer?',
            'keywords' => 'register new signatory designer, add manager signature, new approver account',
            'body'     => 'Administrators can register new managers in Report Designer > Signatories. Choose their NEXUS-L user account, enter their printed full name and job title, and either draw their signature on screen or upload a PNG image.',
            'steps'    => ['Open Report Designer > Signatories as Admin.', 'Click "+ New Signatory".', 'Select the user account, enter printed name and title.', 'Draw signature or upload image, then click Save Signatory.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Signatory uploaded signature is blurry or pixelated on printed PDF',
            'keywords' => 'signature blurry pixelated, low resolution signature, high res signature upload',
            'body'     => 'Upload a signature scan with a resolution of at least 600 x 200 pixels at 150+ DPI. NEXUS-L vector stamping automatically scales high-resolution signatures to maintain crisp lines in the final PDF.',
            'steps'    => ['Scan the signature on clean white paper at 300 DPI.', 'Crop tightly around the signature.', 'Upload in Report Designer > Signatories.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Signatory printed name must match the exact PDF sign-off field name',
            'keywords' => 'printed name exact match pdf, nameMatches rule, signatory name validation',
            'body'     => 'ReportPdf::nameMatches compares the name extracted from the PDF sign-off block with registered signatories (case-insensitive, ignoring middle initials). If the PDF says "John A. Doe", the registered signatory name must be "John Doe" or "John A. Doe".',
            'steps'    => ['Check the printed "Approved by" name on the report sheet.', 'Verify the Full Name in Report Designer > Signatories matches.', 'Update the signatory name if misspelled.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Approval request rejected by manager: how to view rejection remarks?',
            'keywords' => 'approval rejected remarks, view rejection reason, manager rejected report',
            'body'     => 'When a manager clicks "Reject" on the approval page, they enter a mandatory remark explaining the rejection (e.g. "Wrong PO number" or "Retest spot 12"). The remarks appear directly in the Lab Tracker and Upload Form status pill.',
            'steps'    => ['Open the report in Lab Tracker.', 'Hover over the red "Rejected" status tag to read the remarks.', 'Make the requested corrections and re-submit for approval.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'How to resubmit a rejected report for approval after correction?',
            'keywords' => 'resubmit rejected report, request approval after reject, fix rejected report',
            'body'     => 'Once you have updated the data on a rejected report and clicked "Save Report to NEXUS-L", open the report view and click "Request approval (QR)". A fresh approval request is dispatched to the manager.',
            'steps'    => ['Edit the values that were noted in the manager rejection remark.', 'Save the report.', 'Click "Request approval (QR)" to generate a new live review link.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Can two managers sign off on the same digital test report?',
            'keywords' => 'two managers sign off, dual signature approval, multiple approvers report',
            'body'     => 'NEXUS-L supports one primary "Approved by" managerial signature and one "Reviewed by" technical stamp per report sheet, adhering to SATRA accredited laboratory guidelines.',
            'steps'    => ['Technician checks readings and clicks "Stamp Reviewed".', 'Manager scans QR code to provide the certified "Approved by" digital signature.', 'Both credentials appear on the final PDF.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Approval link opened on mobile browser shows unstyled HTML sheet',
            'keywords' => 'approval mobile unstyled html, approval css broken phone, mobile preview unstyled',
            'body'     => 'NEXUS-L approval pages use self-contained responsive CSS with inline fallback styles. If the page appears unstyled on mobile, check your phone network connection to ensure the web server port is accessible.',
            'steps'    => ['Confirm your phone is connected to the company Wi-Fi network (192.168.32.*).', 'Refresh the page in Chrome or Safari.', 'The report sheet will format as an A4 document preview.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Digital signature stamp outline covers the printed approval date',
            'keywords' => 'signature stamp covers date, stamp outline overlapping date, stamp position designer',
            'body'     => 'In Phase 194, tools/build/stamp-pdf.js was updated to use outline-only stamping placed beside the printed lines, preventing ink coverage over the date text.',
            'steps'    => ['Hard refresh your browser to ensure the latest stamp-pdf script is active.', 'If older signed PDFs have overlapping stamps, ask an administrator to regenerate.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Why does the approval feed pill badge not decrease after sign-off?',
            'keywords' => 'approval pill badge not decreasing, feed badge stuck, pending approval count',
            'body'     => 'The top bar approval feed badge polls for updates every 45 seconds. Once an approval is completed or rejected, the badge refreshes automatically on the next poll or page navigation.',
            'steps'    => ['Wait up to 45 seconds or navigate to any page to trigger a fresh poll.', 'Or click the bell / feed icon to view the updated list immediately.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Error: "Cannot approve report: PDF file integrity checksum mismatch"',
            'keywords' => 'checksum mismatch approval, pdf integrity error, sha256 mismatch approval',
            'body'     => 'This security error prevents unauthorized swapping of PDF attachments. The SHA-256 hash of the file being signed must match the file stored when the approval was initiated.',
            'steps'    => ['If the report PDF was regenerated while the approval was open, close the page.', 'Request a fresh approval from the report view.', 'Sign the newest generated copy.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Where are approved signed PDF files permanently stored?',
            'keywords' => 'where are approved signed pdfs stored, signed pdf file path, writable approvals folder',
            'body'     => 'Signed report PDFs are stored on the server under writable/approvals/<file_key>_signed.pdf with permanent file keys. They are accessible through the verified URL dtr/approval/file/<file_key>.',
            'steps'    => ['Download the signed PDF directly from the Lab Tracker by clicking "PDF".', 'Or open the report in InterLink Upload Form Tab 2 to view the attached signed copy.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Manager forgot password while opening approval QR code on phone',
            'keywords' => 'manager forgot password mobile qr, password reset phone approval',
            'body'     => 'Managers can tap "Forgot password?" directly on the mobile sign-in page, enter their username or email, receive a verification code on Synology Chat or email, and choose a new password in 60 seconds.',
            'steps'    => ['On the mobile login screen, tap "Forgot password?".', 'Enter your username and tap "Send code".', 'Check your Synology Chat for the 6-digit code.', 'Enter the code and set a new password.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'login',
        ];
        $p[] = [
            'title'    => 'Can regular laboratory technicians sign off on their own reports?',
            'keywords' => 'technician sign off own report, can tech approve report, approval permissions',
            'body'     => 'No. Under accredited laboratory governance (SATRA & ISO 17025), testing technicians cannot approve their own reports. Only users registered in tbl_dtr_signatories with managerial authority can sign.',
            'steps'    => ['Technicians prepare the report and click "Request approval (QR)".', 'A registered Manager or Supervisor reviews and signs off.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'How to view the full audit history of who approved a test report?',
            'keywords' => 'view approval audit history, who approved report, approval log tracker',
            'body'     => 'In Lab Tracker, click on any approved report. The audit card displays: Requested By, Request Timestamp, Approver Name, Approver Title, Decision Timestamp, and SHA-256 PDF hash.',
            'steps'    => ['Open Lab Tracker (dtr?tab=tracker).', 'Locate the report and click the green "Approved" tag.', 'The audit modal displays complete signatory timestamps.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Approval request sent with wrong Application No: how to correct?',
            'keywords' => 'approval wrong application no, correct application number approval',
            'body'     => 'If the Application No. was mistyped when requesting approval, open the InterLink Upload Form, enter the correct Application No., and click "Request approval (QR)" again. It will supersede the old request.',
            'steps'    => ['Open InterLink Sync > Upload Form.', 'Type the correct Application No.', 'Click "Generate Report" then "Request approval (QR)".'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Synology Chat webhook error: "record is not valid" when sending approval',
            'keywords' => 'synology record is not valid, synology webhook error, chat webhook invalid record',
            'body'     => 'Synology Chat rejects batch requests containing multiple recipient IDs simultaneously. In Phase 194+, SynologyChat::send dispatches individual messages per recipient in a loop to eliminate this error.',
            'steps'    => ['Verify you have the latest SynologyChat.php library.', 'Check Settings > Synology Chat to confirm your incoming webhook URL.', 'Use the bot to test: "Resend notification for approver".'],
            'actions'  => $settingsAct, 'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Signatory title is displayed incorrectly on the approval stamp',
            'keywords' => 'signatory title incorrect stamp, wrong title manager, update signatory title',
            'body'     => 'Administrators can update a manager\'s printed title in Report Designer > Signatories. Edit the "Title" field (e.g. "QC Manager" or "Lab Supervisor") and click Save Signatory.',
            'steps'    => ['Open Report Designer > Signatories as Admin.', 'Click "Edit" on the manager\'s card.', 'Update the Title and click Save Signatory.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Approval QR code does not scan under low light or mobile camera glare',
            'keywords' => 'qr code does not scan, low light qr scan, mobile camera glare qr',
            'body'     => 'If mobile camera glare prevents scanning the on-screen QR code, click the "Copy approval link" button directly underneath the QR code and send the URL to the manager via Synology Chat.',
            'steps'    => ['Click "Copy link" below the QR code.', 'Paste the link into Synology Chat directly to the manager.', 'Or ask the bot: "Resend notification for approver".'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'approval',
        ];
        $p[] = [
            'title'    => 'Report approved but InterLink sender extension says "Report not approved"',
            'keywords' => 'sender extension says not approved, approval check failed extension, verify approved extension',
            'body'     => 'The extension calls GET dtr/approval/verify?lab_ref=<LabRef>. If it reports not approved: (1) verify you are signed in to NEXUS-L in the same browser, and (2) verify the Application No. matches the approved report.',
            'steps'    => ['Make sure NEXUS-L is open and signed in in another tab.', 'Check that the Lab Reference in the extension matches the approved report number.', 'Refresh the InterLink page.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'How to deactivate an inactive manager from the signatory list?',
            'keywords' => 'deactivate signatory, remove manager signatory, inactive approver',
            'body'     => 'In Report Designer > Signatories, toggle "Active" to Off on the manager\'s card, or click Delete. Inactive signatories are immediately excluded from future approval routings.',
            'steps'    => ['Open Report Designer > Signatories as Admin.', 'Toggle the Active switch to Off.', 'Click Save.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Can an approved report be unlocked by an administrator without re-testing?',
            'keywords' => 'unlock approved report admin, unapprove report, revert approved status',
            'body'     => 'Yes. An Administrator can open the report in Report Designer or Lab Tracker and click "Revoke Approval". The status reverts to Unapproved, allowing field editing before re-submitting.',
            'steps'    => ['As Admin, open the report in Lab Tracker.', 'Click "Admin Actions" > "Revoke Approval".', 'Edit the required fields and request a fresh approval.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'dtr', 'role_min' => 'admin',
        ];

        // =========================================================================
        // CATEGORY 4: InterLink 2.0 Integration & Extension (35)
        // =========================================================================
        $p[] = [
            'title'    => '1-Click Send button in InterLink Sync Center is disabled or greyed out',
            'keywords' => '1 click send disabled greyed out, cannot click send interlink, send button inactive',
            'body'     => 'The "1-Click Send" button requires: (1) a valid Lab Reference selected, (2) an Application No. entered, (3) an approved signed report PDF attached, and (4) the InterLink sender extension v1.4.0+ installed in Chrome.',
            'steps'    => ['Check that the report has an approved green status tag.', 'Verify an Application No. is typed.', 'Confirm the InterLink Sender extension is installed and shows active in Chrome toolbar.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Pre-Sync dialog ComboBox does not show my Lab Reference number',
            'keywords' => 'pre sync combobox missing lab ref, lab ref not in sync dropdown, cannot find lab ref interlink',
            'body'     => 'The Pre-Sync ComboBox queries tbl_dtr_unified_labrefs. If your report does not appear: (1) the report was never saved in NEXUS-L, or (2) the report has no Lab Ref No entered. Save the report first.',
            'steps'    => ['Open Lab Tracker and confirm your report appears there.', 'If missing, open Material Tests or Shoe Bonding and click Save Report.', 'Reopen the Pre-Sync Dialog.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'InterLink sender extension says "Please sign in to Intertek portal first"',
            'keywords' => 'extension please sign in intertek portal, interlink extension login required, connect interlink session',
            'body'     => 'The Chrome extension does not store portal passwords. It requires that you open the Intertek InterLink portal in a tab and sign in with your own Intertek account once.',
            'steps'    => ['Open a new tab and sign in to the Intertek InterLink 2.0 portal.', 'Once the portal dashboard loads, switch back to NEXUS-L.', 'Click 1-Click Send again.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Extension version mismatch: "Requires InterLink Sender v1.4.0+"',
            'keywords' => 'extension version mismatch, requires v1.4.0, update sender extension, old extension version',
            'body'     => 'Phase 197 implemented the approval gate which requires extension v1.4.0 or higher. Older versions (v1.3.x) cannot verify the cryptographic approval hash and are refused.',
            'steps'    => ['Download the latest extension from public/downloads/nexus-l-interlink-sender.zip.', 'In Chrome open chrome://extensions, enable Developer Mode.', 'Click "Load unpacked" and select the updated extension folder.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'InterLink background service error: "Connection refused on port 5077"',
            'keywords' => 'connection refused port 5077, background service down, interlink service not reachable',
            'body'     => 'The C# .NET 9 InterLink background service (tools/interlink-service/) listens on port 5077. If connection is refused, the service process is stopped on the server PC.',
            'steps'    => ['Open Settings > InterLink Service.', 'Click "Test the service" to inspect port reachability.', 'On the server, run tools\\interlink-service\\InterlinkService.exe.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Shared key mismatch between NEXUS-L Settings and background service',
            'keywords' => 'shared key mismatch interlink service, x-service-key refused, bad service key 5077',
            'body'     => 'NEXUS-L authenticates background service requests using an HTTP header (X-Service-Key). The key entered in Settings > InterLink service must match the SecretKey in the service appsettings.json.',
            'steps'    => ['Open Settings > InterLink service as Admin.', 'Copy the shared key.', 'Verify it matches the key configured in the background service executable.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Extension fails at step "Searching Application No in live grid"',
            'keywords' => 'searching application no in live grid failed, cannot find application interlink, grid search timeout',
            'body'     => 'The extension searches for the Application No. in the Intertek portal search bar. If not found: (1) the Application No. has not been registered in InterLink yet, or (2) the portal grid filter is set to an inactive status.',
            'steps'    => ['In the Intertek portal, confirm the application exists in your company search view.', 'Verify the application number has no leading/trailing spaces.', 'Re-try 1-Click Send.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Why does InterLink say "Duplicate Test Report Number already exists"?',
            'keywords' => 'duplicate test report number interlink, report already exists portal, interlink duplicate error',
            'body'     => 'This occurs when an earlier report was already saved with this Test Report Number in InterLink. Use "Edit Report" instead of "Add Report" in the InterLink Upload Form.',
            'steps'    => ['In the InterLink Upload Form, select "Edit Existing Report" instead of "Add New Report".', 'Enter the existing report ID.', 'Send updates.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'InterLink portal session expires during long report uploads',
            'keywords' => 'interlink session expired upload, portal timeout during send, relogin interlink portal',
            'body'     => 'InterLink portal sessions time out after 20 minutes of inactivity. When preparing multiple test packages, keep an active portal tab open or use the C# background service which maintains a persistent session.',
            'steps'    => ['Refresh your Intertek portal tab to ensure you are signed in.', 'Or switch to the C# background service in Settings > InterLink service.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Test package selection dropdown does not match Intertek portal test item',
            'keywords' => 'test package selection mismatch, package dropdown wrong, test item name interlink',
            'body'     => 'Intertek test packages (e.g. VANS Pull Strength, Colorfastness Suite) are defined in Settings > InterLink report defaults. If a new brand standard requires a different package name, update it in Settings.',
            'steps'    => ['Open Settings > InterLink report defaults as Admin.', 'Update "Test Package(s)" with the exact string from the Intertek portal.', 'Click Save.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Report attachment PDF upload fails in InterLink Upload Form Tab 2',
            'keywords' => 'report attachment pdf upload failed tab 2, cannot attach pdf interlink, upload form tab 2 pdf error',
            'body'     => 'InterLink accepts PDF attachments up to 10 MB. If your generated PDF exceeds 10 MB or is not in standard PDF format, the upload helper rejects it. Use "Generate Report" in NEXUS-L to produce a compressed vector PDF.',
            'steps'    => ['In Upload Form Tab 2, click "Generate Report" to create a fresh, compressed PDF.', 'Confirm the PDF size is under 5 MB.', 'Click 1-Click Send.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Background service headless Chromium crashes on server startup',
            'keywords' => 'headless chromium crash background service, playwright chromium error 5077, interlink service crash',
            'body'     => 'Playwright requires Chromium dependencies. If running on Windows Server without Desktop Experience, install the Visual C++ Redistributable 2015-2022 and run Playwright install.',
            'steps'    => ['Open PowerShell as Administrator in tools\\interlink-service\\.', 'Run `pwsh bin\\Debug\\net9.0\\playwright.ps1 install chromium`.', 'Restart the service.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'InterLink Pre-Sync field highlight color meaning (green vs yellow vs red)',
            'keywords' => 'pre sync highlight colors, green yellow red interlink, field highlight meaning',
            'body'     => 'Green = exact match between NEXUS-L and InterLink portal values. Yellow = default value applied (review recommended). Red = mandatory field missing or value format conflict (must be resolved before sending).',
            'steps'    => ['Review all red highlighted fields in the Pre-Sync Dialog.', 'Fill missing values.', 'Once all rows turn green or yellow, proceed with sync.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'How to update factory and vendor default values for InterLink reports?',
            'keywords' => 'update factory vendor defaults interlink, factory id vendor id settings, change default company interlink',
            'body'     => 'Factory Name, Factory ID (e.g. 0000507452), Vendor Name, and Company Name were moved to Settings > InterLink report defaults in Phase 199. Change them there without touching code.',
            'steps'    => ['Open Settings (/settings) as Admin.', 'Under "InterLink report defaults", update Vendor ID, Factory ID, Season, Brand, etc.', 'Click Save.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'InterLink Sender extension closes the portal tab unexpectedly',
            'keywords' => 'sender extension closes tab, portal tab closed unexpectedly, extension closed tab error',
            'body'     => 'In Phase 194+, the extension was hardened to never close the tab where a user signed in manually. It only closes temporary automation tabs that it spawned itself after confirming successful submission.',
            'steps'    => ['Update to extension v1.4.0+.', 'Keep your primary portal tab open and signed in.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Error: "Application No cannot be blank in InterLink Normal Form"',
            'keywords' => 'application no cannot be blank normal form, missing application no interlink, application number required',
            'body'     => 'The Intertek Normal Form uses the Application Number (TR No) as the master identifier. If your lab reference has no TR No linked, type the Application No. in the Upload Form before initiating send.',
            'steps'    => ['In the Upload Form, type the Application No (e.g. APP20261028-01).', 'Click "Save".', 'Click 1-Click Send.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Service required field in InterLink defaults: Regular vs Express',
            'keywords' => 'service required regular express, interlink service required setting, turnaround time intertek',
            'body'     => 'Service Required specifies the turnaround priority in the Intertek lab portal: "Regular" (standard production cycle) or "Express" (priority expedite). Configure the default in Settings > InterLink report defaults.',
            'steps'    => ['Open Settings as Admin.', 'Set "Service Required" to Regular or Express.', 'Click Save.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'InterLink test stage selection: Production vs Development vs Retest',
            'keywords' => 'test stage production development retest, interlink test stage, stage selection intertek',
            'body'     => 'Test Stage identifies the manufacturing phase: Production (bulk production samples), Development (pre-production protos), or Retest (post-failure re-examination). Default is set in Settings.',
            'steps'    => ['In the Upload Form header block, choose the Test Stage from the dropdown.', 'Or update the global default in Settings > InterLink report defaults.'],
            'actions'  => $settingsAct, 'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Extension popup shows "Waiting for NEXUS-L handshake response"',
            'keywords' => 'waiting for handshake response extension, extension handshake timeout, nxlsenderversion handshake',
            'body'     => 'This handshake occurs between the browser extension and the NEXUS-L page DOM. If it hangs, the page has not fully loaded or you navigated away before the handshake completed.',
            'steps'    => ['Refresh the NEXUS-L InterLink page.', 'Ensure the extension icon in the Chrome toolbar is enabled for this domain.', 'Click 1-Click Send again.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'InterLink background service queue shows "Job stuck in processing"',
            'keywords' => 'job stuck in processing background service, interlink service queue hang, clear queue job',
            'body'     => 'If a job shows processing for more than 5 minutes, Playwright encountered an unhandled modal dialog in the Intertek portal. In Settings > InterLink service, admins can test connectivity or reset the queue.',
            'steps'    => ['Open Settings > InterLink service.', 'Click "Test the service" to inspect the waiting count.', 'Restart the service process on the server to flush hanging jobs.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to cancel a queued InterLink background submission job?',
            'keywords' => 'cancel queued interlink job, remove job from queue, stop interlink submit',
            'body'     => 'In InterLink Sync > Queue tab, locate the pending job and click "Cancel". Jobs that have already commenced the live Save step in InterLink cannot be rolled back from NEXUS-L.',
            'steps'    => ['Open InterLink Sync (/interlink).', 'Click the "Queue" tab.', 'Click "Cancel" next to the pending submission.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Report attached in InterLink does not contain official SATRA stamp',
            'keywords' => 'attached report missing satra stamp, unsigned report attached interlink, satra mark missing upload',
            'body'     => 'Only the approved, digitally signed PDF from writable/approvals/ may be attached. If an unsigned draft was attached, the report approval was bypassed or not completed before sending.',
            'steps'    => ['Request and complete managerial QR approval in NEXUS-L.', 'In Upload Form Tab 2, click "Generate Report" to ensure the signed copy is bound.', 'Submit to InterLink.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'InterLink Save button clicked but portal returns "Mandatory fields missing"',
            'keywords' => 'mandatory fields missing interlink portal, save failed mandatory fields, intertek form error',
            'body'     => 'The Intertek portal requires: Sample Description, Country of Origin, Brand, Season, and at least one Test Item with valid readings. Verify all header fields in the Upload Form are filled.',
            'steps'    => ['Review the red error text on the InterLink portal window.', 'Fill the highlighted mandatory field in NEXUS-L Upload Form.', 'Click 1-Click Send again.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Synology notification "Report submitted to InterLink" not sending',
            'keywords' => 'submitted to interlink notification not sending, synology submit alert missing',
            'body'     => 'Upon successful submission, the background service triggers a Synology notification ("Labreference / ReportNO / submitted to InterLink"). If not received, verify the Synology Bot Token in Settings.',
            'steps'    => ['Check Settings > Synology Chat.', 'Confirm that the bot token and prefix are valid.', 'Submit a test report to verify alert delivery.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to download the latest InterLink sender extension zip package?',
            'keywords' => 'download sender extension zip, get latest extension, nexus-l-interlink-sender.zip',
            'body'     => 'The extension zip package is built automatically and served directly from public/downloads/nexus-l-interlink-sender.zip on your NEXUS-L server.',
            'steps'    => ['Navigate to http://192.168.32.43/NEXUS-L/downloads/nexus-l-interlink-sender.zip.', 'Extract the zip file to your computer.', 'Load unpacked in chrome://extensions.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Extension blocked by Chrome policy: "Cannot install extension from zip"',
            'keywords' => 'chrome policy blocked extension, cannot install unpacked extension, developer mode blocked',
            'body'     => 'Enterprise Chrome policies may restrict unpacked extensions. If so, contact your IT administrator to allow the extension ID or use the C# background service which runs headlessly on the server.',
            'steps'    => ['Ask IT to whitelist the extension in Chrome Enterprise policy.', 'Alternatively, enable the C# background service in Settings > InterLink service.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Why are tensile specimen readings grouped into one row in InterLink?',
            'keywords' => 'tensile readings grouped one row, tensile length width grouped, interlink test grouping',
            'body'     => 'In Phase 198, tensile tests were unified to match InterLink portal architecture: Tensile Length and Tensile Width are treated as sub-elements under a single test row, preventing duplicate test charges.',
            'steps'    => ['This is the standard behavior in InterLink 2.0.', 'Readings automatically map to the corresponding sub-elements in the portal grid.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'InterLink portal shows blank screen when opened by background service',
            'keywords' => 'portal shows blank screen background service, white screen interlink playwright',
            'body'     => 'If the portal returns a white screen in Playwright, the stored browser session cookie has expired and the portal redirected to an unhandled SSO page. Re-run `--signin` on the server.',
            'steps'    => ['On the server, run `tools\\interlink-service\\InterlinkService.exe --signin`.', 'Sign in once in the visible browser window.', 'The session cookie is refreshed and stored.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to switch from Background Service mode back to Extension mode?',
            'keywords' => 'switch to extension mode, disable background service, use extension instead of service',
            'body'     => 'In Settings > InterLink service, untick "Send reports through the background service" and click Save. NEXUS-L immediately switches back to the Chrome browser extension for all users.',
            'steps'    => ['Open Settings as Admin.', 'Under "InterLink service", untick "Send reports through the background service".', 'Click Save.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'InterLink report detail page readings do not match NEXUS-L values',
            'keywords' => 'interlink detail page mismatch, readings different portal vs nexus, values out of sync',
            'body'     => 'The Report Detail Page in NEXUS-L mirrors InterLink portal fields. If readings differ, the report was edited on the portal directly without syncing back to NEXUS-L.',
            'steps'    => ['In InterLink Sync, click "Pull from Portal" to refresh local records with portal changes.', 'Always make edits in NEXUS-L and sync outward to maintain audit parity.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Background service log file location for troubleshooting submission errors',
            'keywords' => 'background service log file location, where are interlink service logs, debug service logs',
            'body'     => 'The background service writes rotating log files to tools/interlink-service/logs/service-YYYY-MM-DD.log and reports errors directly to NEXUS-L via the callback endpoint.',
            'steps'    => ['Open tools\\interlink-service\\logs\\ on the server PC.', 'Review the most recent timestamped log entries for Playwright exceptions.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Why does InterLink report submission show "Status: Rejected by Intertek"?',
            'keywords' => 'status rejected by intertek, intertek rejected report, audit review failure portal',
            'body'     => 'If Intertek QA reviewers reject an uploaded report in the portal, they leave an audit comment in the InterLink portal review queue. Check the comment and update your report.',
            'steps'    => ['Sign in to InterLink portal and check the Review Queue remarks.', 'Correct the noted discrepancies in NEXUS-L.', 'Re-approve and re-send.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'InterLink Lab Location code for Philippines factory submissions',
            'keywords' => 'lab location code philippines, interlink lab location setting, factory location code',
            'body'     => 'The standard Lab Location code for Datian Subic Shoes Inc. is "PHILIPPINES" with Service Provider "DATIAN SUBIC SHOES INC.". These are configured in Settings > InterLink report defaults.',
            'steps'    => ['Open Settings > InterLink report defaults.', 'Verify Lab Location is set to PHILIPPINES.', 'Click Save.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to verify if a report was successfully committed in InterLink?',
            'keywords' => 'verify committed in interlink, report confirmed portal, check sync status',
            'body'     => 'When committed, the report row in NEXUS-L displays a green "Synced" tag with the Intertek Report Number and timestamp, and an automated Synology message is posted.',
            'steps'    => ['Open Lab Tracker or InterLink Sync.', 'Confirm the "Synced" badge is displayed.', 'Click the badge to view the submission audit receipt.'],
            'actions'  => $interlinkAct, 'page_match' => 'interlink',
        ];
        $p[] = [
            'title'    => 'Background service fails when server runs under LocalSystem account',
            'keywords' => 'background service fails localsystem, service account permission network, windows service localsystem',
            'body'     => 'The Windows LocalSystem account cannot access mapped network drives or credentials on 192.168.32.45. The background service must be run under a named Windows user account with network rights.',
            'steps'    => ['Open Windows Services (services.msc).', 'Right-click the service > Properties > Log On tab.', 'Select "This account" and enter a user account with network access.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];

        // =========================================================================
        // CATEGORY 5: Machine Output Files & Network Shares (30)
        // =========================================================================
        $p[] = [
            'title'    => 'Error: "Cannot connect to machine share \\\\192.168.32.45\\OUTDATA\\"',
            'keywords' => 'cannot connect machine share outdata, network share offline 192.168.32.45, outdata connection timeout',
            'body'     => 'This occurs when: (1) the machine PC hosting the share is powered down, (2) the local IP address changed, or (3) SMB file sharing is disabled. Check Settings > AI & System Status.',
            'steps'    => ['Verify the machine PC at 192.168.32.45 is powered on.', 'In Report Designer > File locations, click "Test location".', 'Update the IP address if the host machine was reassigned.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'diagnose' => 'machine_share', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Machine file found but contains 0 readings for the requested Lab Ref',
            'keywords' => 'machine file found 0 readings, empty readings machine file, file exists but no data',
            'body'     => 'This happens when the testing software generated the workbook template but the tensile or peel test cycle was aborted before readings were logged into the cells.',
            'steps'    => ['Check with the lab technician if the test completed.', 'Confirm that data rows are populated in the workbook on the machine PC.', 'Re-test if the run was aborted.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr', 'diagnose' => 'machine_share',
        ];
        $p[] = [
            'title'    => 'Excel reader preview shows wrong worksheet name in machine file',
            'keywords' => 'wrong worksheet name machine file, sheet name mismatch reader, sheet1 vs data',
            'body'     => 'Testing machine software sometimes changes the default sheet name from "Sheet1" to "Data" or "Results" after a software update. Update the sheet name in Report Designer > Excel readers.',
            'steps'    => ['Open Report Designer > Excel readers as Admin.', 'Select the test form and click "Preview file".', 'Note the exact sheet name tab at the bottom, update the Sheet field, and click Save readers.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to map a new machine file format in Report Designer?',
            'keywords' => 'map new machine file format designer, add excel reader rule, configure new test machine',
            'body'     => 'Report Designer > Excel readers allows mapping any Excel file layout: specify the folder, sheet name, starting row, and click cells in the visual grid to assign them to database columns.',
            'steps'    => ['Open Report Designer > Excel readers as Admin.', 'Select the report type and preview a sample machine file.', 'Click cells in the grid to assign them to form fields.', 'Click Test then Save readers.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Machine output file is locked by tensile test software on lab PC',
            'keywords' => 'machine file locked tensile software, file sharing violation excel, file in use outdata',
            'body'     => 'Some testing machines lock their output file while the test software is open. Ask the technician to close the test run or export to a finalized file before loading in NEXUS-L.',
            'steps'    => ['Close the test record in the machine software on the lab PC.', 'Wait 5 seconds and click "Load Machine Results" again in NEXUS-L.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'U60 machine output Excel file contains #VALUE! formula errors',
            'keywords' => 'u60 file contains #value errors, formula error machine file, broken excel formula u60',
            'body'     => 'If the testing machine writes Excel formulas that reference empty cells, the cell evaluates to #VALUE!. NEXUS-L parser treats formula error cells as null to prevent script crashes.',
            'steps'    => ['Ensure all required baseline inputs are filled on the machine software.', 'Inspect the raw Excel file on the machine share.', 'Save the file to recalculate formulas.'],
            'actions'  => $dtrBondingAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Machine readings loaded with comma decimals instead of dots',
            'keywords' => 'comma decimals machine readings, comma vs dot decimals, locale number format machine',
            'body'     => 'European testing machines often format numbers with commas (e.g. 3,45 instead of 3.45). NEXUS-L automatically normalizes comma decimals to standard floating point numbers during load.',
            'steps'    => ['NEXUS-L sanitizes comma decimals automatically upon loading.', 'If manual entry is needed, use standard dot decimals (3.45).'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Machine file path changed to a new server: how to update settings?',
            'keywords' => 'change machine file path new server, update outdata share location, move machine folders',
            'body'     => 'In Report Designer > File locations, type the new network path (e.g. \\\\192.168.32.50\\OUTDATA), click "Test location" to verify all subfolders, and click "Save location".',
            'steps'    => ['Open Report Designer > File locations as Admin.', 'Type the new root folder path.', 'Click "Test location". Confirm all test subfolders show green.', 'Click "Save location".'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Network share connection drops during batch machine result loading',
            'keywords' => 'network share drops batch loading, timeout outdata network, intermittent share disconnect',
            'body'     => 'NEXUS-L caches loaded machine files locally in writable/cache/outdata/. If the network drops intermittently, cached files ensure report editing is not interrupted.',
            'steps'    => ['Check lab switch ethernet cables connecting the testing machine PC.', 'NEXUS-L reads from local cache if the share momentarily drops.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Machine file has multiple rows with the same Lab Reference number',
            'keywords' => 'multiple rows same lab ref machine file, duplicate lab ref in excel, which row is loaded',
            'body'     => 'If a machine file contains multiple test runs under the same Lab Ref, NEXUS-L loads the lowest (most recent) row by default, matching standard laboratory re-run protocols.',
            'steps'    => ['Verify the row numbers in the Excel reader preview.', 'If an earlier row must be loaded, specify the row number in Report Designer > Excel readers.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Why does "Load Machine Results" load only specimen 1 and 2, missing 3?',
            'keywords' => 'load machine results missing specimen 3, only 2 specimens loaded, 3rd specimen empty',
            'body'     => 'Check the machine file to see if the 3rd specimen column was populated. Some testing runs are aborted before testing the 3rd replicate. A valid test requires all 3 specimens.',
            'steps'    => ['Open the machine file in Excel to confirm whether specimen 3 was tested.', 'If tested, check the column offset rule in Report Designer > Excel readers.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Machine file date format is DD/MM/YYYY but NEXUS-L expects YYYY-MM-DD',
            'keywords' => 'machine date format dd mm yyyy, date parsing machine file, date format error excel',
            'body'     => 'NEXUS-L uses PHP strtotime with intelligent locale detection, supporting ISO (YYYY-MM-DD), British (DD/MM/YYYY), and US (MM/DD/YYYY) formats automatically.',
            'steps'    => ['Dates are normalized automatically upon load.', 'Verify the parsed date on the form header before saving.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Excel file reader error: "Missing font or unreadable binary workbook"',
            'keywords' => 'unreadable binary workbook excel, xlsb not supported, convert xlsb to xlsx',
            'body'     => 'NEXUS-L supports standard XML workbooks (.xlsx) and comma-separated files (.csv). Proprietary binary formats (.xlsb) must be re-saved as standard .xlsx on the machine PC.',
            'steps'    => ['On the machine PC, save the output file as "Excel Workbook (*.xlsx)".', 'Click "Load Machine Results" in NEXUS-L.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Where are Trouser Tear machine output files stored?',
            'keywords' => 'trouser tear output folder location, where is trouser tear share, materials trouser tear',
            'body'     => 'Trouser tear machine files are stored on \\\\192.168.32.45\\OUTDATA\\MATERIALS\\TROUSER TEAR\\. The automated loader checks this folder when loading Fabric or Leather forms.',
            'steps'    => ['Confirm machine output is directed to MATERIALS\\TROUSER TEAR.', 'Verify filename matches the Lab Reference number.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Where are Stitch Tear machine output files stored?',
            'keywords' => 'stitch tear output folder location, where is stitch tear share, materials stitch tear',
            'body'     => 'Stitch tear machine output files are stored on \\\\192.168.32.45\\OUTDATA\\MATERIALS\\STITCH TEAR\\. The loader parses the 3 length and 3 width readings automatically.',
            'steps'    => ['Ensure the machine exports to MATERIALS\\STITCH TEAR.', 'Verify the Lab Reference in column B matches your ticket.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Where are Laminar Peel machine output files stored?',
            'keywords' => 'laminar peel output folder location, where is laminar peel share, materials laminar',
            'body'     => 'Laminar peel test output files are stored on \\\\192.168.32.45\\OUTDATA\\MATERIALS\\LAMINAR\\. Used for foxing tape and synthetic upper lamination peel tests.',
            'steps'    => ['Ensure files are saved in MATERIALS\\LAMINAR.', 'Click "Load Machine Results" in the Foxing Tape (FTHL) form.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Where are Tensile Elongation machine output files stored?',
            'keywords' => 'tensile elongation output folder location, where is tensile share, materials tensile',
            'body'     => 'Tensile strength and elongation curves are stored on \\\\192.168.32.45\\OUTDATA\\MATERIALS\\TENSILE\\. Loaded by Fabric, Leather, Webbing, and Insole forms.',
            'steps'    => ['Verify files are exported to MATERIALS\\TENSILE.', 'Click "Load Machine Results" on the material test form.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Where are Die C Tear machine output files stored?',
            'keywords' => 'die c tear output folder location, where is die c share, materials die c tear',
            'body'     => 'Die C tear machine files are stored on \\\\192.168.32.45\\OUTDATA\\MATERIALS\\DIE C TEAR\\. Evaluated primarily on rubber and polymer compound specimens.',
            'steps'    => ['Verify output is saved in MATERIALS\\DIE C TEAR.', 'Click "Load Machine Results" in the Rubber Outsole (RB) form.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Where are Edge Crush machine output files stored?',
            'keywords' => 'edge crush output folder location, where is edge crush share, materials edge crush',
            'body'     => 'Edge crush test files for corrugated packaging are stored on \\\\192.168.32.45\\OUTDATA\\MATERIALS\\EDGE CRUSH\\. Loaded by the Carton Box (IB/OB) form.',
            'steps'    => ['Confirm export to MATERIALS\\EDGE CRUSH.', 'Click "Load Machine Results" in the Carton Box form.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Machine file cache in writable/cache/outdata: how to clear stale cache?',
            'keywords' => 'clear outdata cache, stale machine cache, delete cached machine files, refresh outdata',
            'body'     => 'NEXUS-L caches machine files to speed up reloading. If a machine file was re-run on the machine PC but NEXUS-L loads the old values, delete the cached file in writable/cache/outdata/.',
            'steps'    => ['In Settings or Brain Console, click "Clear Machine File Cache".', 'Or an administrator can delete files in writable/cache/outdata/.', 'Click "Load Machine Results" again to pull the fresh file.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Tensile machine output file has no header row: how to configure reader?',
            'keywords' => 'machine file no header row, configure reader without header, headerless excel mapping',
            'body'     => 'In Report Designer > Excel readers, untick "File has header row" and specify the exact numerical row index (e.g. Row 1) where specimen data begins.',
            'steps'    => ['Open Report Designer > Excel readers as Admin.', 'Set "Starting Data Row" to 1.', 'Click Save readers.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Machine file auto-loader fails for Lab Ref with special characters',
            'keywords' => 'lab ref special characters machine file, slash in lab ref file not found, sanitized lab ref filename',
            'body'     => 'Windows filenames cannot contain slashes (/ or \\). If your Lab Ref contains slashes on the ticket (e.g. DTP/2026/10), the machine file must be saved with hyphens (DTP-2026-10).',
            'steps'    => ['Use hyphens (-) instead of slashes (/) in file names.', 'NEXUS-L sanitizes slashes to hyphens automatically during file matching.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Why does the Excel reader rule require a starting row index?',
            'keywords' => 'why starting row index excel reader, starting row rule designer, excel reader config',
            'body'     => 'Different testing software programs put company logos and machine metadata in rows 1 through 5 before the readings start. The starting row index tells the parser where valid numbers begin.',
            'steps'    => ['Preview the machine file in Report Designer > Excel readers.', 'Identify the first row containing specimen readings.', 'Set "Starting Row" to that number.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Machine file contains negative load values: how are they handled?',
            'keywords' => 'negative load values machine file, negative readings excel, load tare calibration',
            'body'     => 'Negative load values occur if the machine load cell was not tared before clamping the specimen. NEXUS-L parser flags negative values and sets them to 0.0 with a warning banner.',
            'steps'    => ['Ask the technician to tare the load cell and re-clamp.', 'Do not use negative values for official averages.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Permission error: "NT AUTHORITY\\\\SYSTEM cannot open network share"',
            'keywords' => 'nt authority system cannot open share, network share permission system, access sync network error',
            'body'     => 'The Windows SYSTEM account cannot authenticate across network shares without active directory credentials. Run background tasks as the signed-in Windows user account.',
            'steps'    => ['Configure scheduled tasks to run as the logged-in Administrator user account.', 'Verify access to \\\\192.168.32.45\\OUTDATA in Windows Explorer.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to test network share reachability from Settings page?',
            'keywords' => 'test network share settings, verify outdata connection, file location test settings',
            'body'     => 'In Report Designer > File locations, click the "Test location" button. It tests folder existence, SMB ping, and counts the files in each material test folder.',
            'steps'    => ['Open Report Designer > File locations as Admin.', 'Click "Test location".', 'Review the table showing green checkmarks and file counts for all 10 folders.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Machine file reader reads text string instead of numeric reading',
            'keywords' => 'machine reader reads text not number, string instead of float reading, typecast error excel',
            'body'     => 'If a cell contains text like "120 N" or "PASS", the numerical parser extracts the numeric portion (120) and ignores units automatically.',
            'steps'    => ['NEXUS-L strips alphabetical unit labels automatically.', 'Verify the numerical value in the form input.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Outdata machine file timestamp is older than sample received date',
            'keywords' => 'machine file older than received date, stale file loaded, wrong test date outdata',
            'body'     => 'If the file timestamp is older than the received date, you are likely loading an older test run from a previous batch with the same Lab Ref. Verify the suffix (-L2 or re-run).',
            'steps'    => ['Check the file modified date on \\\\192.168.32.45\\OUTDATA.', 'Ensure the machine saved the newest test run.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Machine file contains merged cells in results table: how to map?',
            'keywords' => 'merged cells machine file mapping, excel merged cells reader, map merged cell',
            'body'     => 'In Excel, merged cells store their value in the top-left cell of the merged block. When mapping in Report Designer > Excel readers, select the top-left cell coordinate.',
            'steps'    => ['In the visual grid preview, click the top-left cell of the merged block.', 'Assign it to the target form field and click Save readers.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to backup machine reading mapping rules from tbl_machine_maps?',
            'keywords' => 'backup machine maps rules, export tbl_machine_maps, save excel reader rules',
            'body'     => 'All reader mapping rules live in tbl_machine_maps in dbnexus. Use Settings > Database Transfer or mysqldump to back up tbl_machine_maps along with other system tables.',
            'steps'    => ['Open Settings > Transfer to another database to perform a live table backup.', 'Or run `php spark db:backup` from the terminal.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];

        // =========================================================================
        // CATEGORY 6: System, Security, Sessions & Data Management (35)
        // =========================================================================
        $p[] = [
            'title'    => 'Error 401: "Your session has expired, please sign in again"',
            'keywords' => 'error 401 session expired, signed out automatically, 30 minute timeout, relogin 401',
            'body'     => 'For data security, sessions expire after 30 minutes of inactivity. When you see this error, sign in again. All work saved prior to expiration is preserved in dbnexus.',
            'steps'    => ['Sign in with your username and password.', 'Tick "Remember me" on your dedicated workstation.', 'Reopen your report from the Lab Tracker.'],
            'actions'  => [['type' => 'open', 'label' => 'Sign In', 'url' => 'login']], 'page_match' => 'login',
        ];
        $p[] = [
            'title'    => 'Error 403: "Only an Administrator can access the Settings page"',
            'keywords' => 'error 403 settings admin only, access forbidden settings, role admin required',
            'body'     => 'The Settings page (/settings) and Report Designer are restricted strictly to users with Role = Admin. If you need system configuration changes, ask an administrator or use the bot.',
            'steps'    => ['Check your user role in the top-right profile header.', 'If administrative privileges are required, contact the lead system administrator.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Error 419: "CSRF token mismatch on form submission"',
            'keywords' => 'error 419 csrf token mismatch, page expired csrf, csrf token invalid',
            'body'     => 'CSRF tokens protect against cross-site request forgery. A mismatch occurs if you leave a form open overnight or submit from an outdated tab. Refresh the page to obtain a fresh token.',
            'steps'    => ['Press F5 to refresh the page.', 'NEXUS-L automatically refreshes the CSRF token in the meta tags.', 'Submit your form again.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Password reset verification code email or Synology message not received',
            'keywords' => 'password reset code not received, verification code missing, forgot password code not arriving',
            'body'     => 'Verification codes are sent to your registered Synology Chat ID or email. If not received within 2 minutes: (1) check your username spelling, or (2) ask an Admin to reset your password directly.',
            'steps'    => ['Confirm your username on the sign-in page.', 'Check Synology Chat notifications.', 'If not received, ask an Admin to set a temporary password in User Management.'],
            'actions'  => [['type' => 'open', 'label' => 'Forgot Password', 'url' => 'forgot-password']], 'page_match' => 'login',
        ];
        $p[] = [
            'title'    => 'Account lockout: "Maximum login attempts exceeded, try again in 15 min"',
            'keywords' => 'account lockout 15 min, max login attempts exceeded, account locked out, login blocked',
            'body'     => 'After 5 consecutive failed login attempts, accounts are locked for 15 minutes to block brute-force attacks. An administrator can unlock the account immediately in User Management.',
            'steps'    => ['Wait 15 minutes for the automated lockout window to expire.', 'Or ask an Admin to clear the lockout in tbl_users.', 'Use the eye icon to verify your password before typing.'],
            'actions'  => [['type' => 'open', 'label' => 'Sign In', 'url' => 'login']], 'page_match' => 'login',
        ];
        $p[] = [
            'title'    => 'Database error: "MySQL connection timeout on 192.168.32.43:3306"',
            'keywords' => 'mysql connection timeout 192.168.32.43, dbnexus connection error, database unreachable',
            'body'     => 'NEXUS-L connects to MySQL database dbnexus on host 192.168.32.43:3306. If connection fails, verify the MySQL service is running on the database server and port 3306 is not blocked by Windows Firewall.',
            'steps'    => ['Verify MySQL service (mysqld) is running on 192.168.32.43.', 'Check port 3306 using `Test-NetConnection 192.168.32.43 -Port 3306`.', 'Check .env database configuration.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to switch the active database from the Settings page?',
            'keywords' => 'switch active database settings, change mysql host settings, use this database button',
            'body'     => 'In Settings > Database in use, fill in the target MySQL server, port, database name, user, and password. Click "Test this connection". Once verified, type SWITCH and click "Use this database".',
            'steps'    => ['Open Settings as Admin.', 'Fill in the target database connection credentials.', 'Click "Test this connection".', 'Type SWITCH into the confirmation box and click "Use this database".'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How does background database transfer work in NEXUS-L Settings?',
            'keywords' => 'background database transfer settings, transfer to another database, copy all tables dbnexus',
            'body'     => 'The background transfer tool (php spark settings:transfer) copies every table structure and all data rows from the current database to a target server without taking the web offline.',
            'steps'    => ['Open Settings > Transfer to another database as Admin.', 'Enter the target server credentials.', 'Tick "Create database if it does not exist".', 'Click "Start the transfer" and monitor the real-time progress bar.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'What happens if a database transfer is interrupted or loses power?',
            'keywords' => 'database transfer interrupted, transfer power loss resume, transfer job recovery',
            'body'     => 'The transfer job writes state to writable/transfer/<job_id>.json after every table. If interrupted, the source database remains 100% untouched. You can restart the transfer safely.',
            'steps'    => ['Check writable/transfer/ for the latest job log.', 'Re-run the transfer with "Overwrite tables" checked to resume cleanly.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Where are .env database connection backups stored?',
            'keywords' => 'where are env backups stored, writable env-backups folder, restore old .env connection',
            'body'     => 'Whenever you switch databases from the Settings page, a timestamped copy of the previous .env file is automatically saved into writable/env-backups/.',
            'steps'    => ['Open writable/env-backups/ on the server.', 'Find the timestamped backup file (e.g. .env.20261030_142010).', 'Copy the desired configuration back if a rollback is needed.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Hard refresh shortcut (Ctrl + F5) does not clear cached assets',
            'keywords' => 'ctrl f5 not clearing cache, hard refresh browser asset, clear cache chrome edge',
            'body'     => 'In Chrome or Edge, open DevTools (press F12), right-click the browser Refresh button, and select "Empty Cache and Hard Reload". This forces immediate clearing of all cached JS and CSS.',
            'steps'    => ['Press F12 to open DevTools.', 'Right-click the circular Reload icon in the top toolbar.', 'Select "Empty Cache and Hard Reload".'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'How to configure Synology Chat bot webhook token in Settings?',
            'keywords' => 'configure synology chat webhook token settings, synology bot address token, synology settings',
            'body'     => 'In Settings > Synology Chat, enter the Synology Chat Base URL (e.g. http://192.168.32.43:5000/webapi/entry.cgi) and the Bot Token generated in Synology Chat Integration. Click Save.',
            'steps'    => ['Open Synology Chat > Integrations > Incoming Webhook to copy the bot token.', 'In NEXUS-L Settings, paste the token into "Bot token".', 'Click Save.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Why does Synology Chat bot return HTTP 400 Bad Request?',
            'keywords' => 'synology bot 400 bad request, synology webhook bad request, token urlencode synology',
            'body'     => 'Synology Chat requires the token parameter to be enclosed in double quotes (token="%22...%22"). SynologyChat::endpoint formats this automatically. Verify your token has no extra spaces.',
            'steps'    => ['In Settings > Synology Chat, re-paste the token cleanly.', 'Ensure no trailing spaces exist.', 'Click Save.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'AI service error: "Service unreachable at http://127.0.0.1:8765"',
            'keywords' => 'ai service unreachable 8765, python ai service stopped, restart ai bot service',
            'body'     => 'The Python LangGraph AI microservice is stopped on port 8765. Start the service by running run-ai.cmd from E:\\html\\NEXUS-L or execute `python -m uvicorn app.main:app --port 8765`.',
            'steps'    => ['Open PowerShell on the server PC.', 'Run `E:\\html\\NEXUS-L\\run-ai.cmd`.', 'In Settings > AI Assistant, click "Test AI Service" to verify status.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to calibrate the AI confidence threshold for the bot?',
            'keywords' => 'calibrate ai confidence threshold, change bot threshold settings, ai threshold tuning',
            'body'     => 'In Settings > AI Assistant & Bot Engine, adjust "Minimum Confidence Threshold" (default 0.85). Higher values enforce stricter accuracy; lower values increase answer coverage.',
            'steps'    => ['Open Settings as Admin.', 'Under "AI Assistant & Bot Engine", update the confidence threshold.', 'Click Save. The new threshold applies immediately.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'What is the difference between AI Shadow Mode and Live Mode?',
            'keywords' => 'difference shadow mode live mode, ai operating mode settings, shadow vs live ai',
            'body'     => 'In Shadow Mode, the Python AI service answers questions in the background and logs traces for evaluation without showing answers to users (users receive Brain answers). In Live Mode, users receive verified LangGraph answers directly.',
            'steps'    => ['Shadow mode is ideal for verifying accuracy before rollout.', 'Once tested, switch AI Operating Mode to "live" in Settings and click Save.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to review unanswered user bot questions in Brain Console?',
            'keywords' => 'review unanswered bot questions, brain console unanswered, teach bot missing answers',
            'body'     => 'In the sidebar, click Brain Console (admins only) > Unanswered. It lists all questions where the bot had low confidence or abstained, allowing admins to write and approve answers in 1 click.',
            'steps'    => ['Open Brain Console from the sidebar.', 'Click "Unanswered Questions".', 'Click "Answer" on any question to add the solution directly into the knowledge base.'],
            'actions'  => [['type' => 'open', 'label' => 'Open Brain Console', 'url' => 'brain']], 'page_match' => 'brain', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to teach the bot a new answer from an administrator reply?',
            'keywords' => 'teach bot new answer admin reply, learn from admin chat, bot learn draft',
            'body'     => 'When an administrator answers an escalated chat with a user, the bot automatically captures the conversation into "Learn from Chats". Administrators can click "Approve" to convert it into a permanent knowledge article.',
            'steps'    => ['Open Brain Console > Learn.', 'Review proposed Q&A pairs captured from live chats.', 'Click "Approve & Teach" to index the answer permanently.'],
            'actions'  => [['type' => 'open', 'label' => 'Open Brain Console', 'url' => 'brain']], 'page_match' => 'brain', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to restore tbl_bot_knowledge from Brain backup files?',
            'keywords' => 'restore tbl_bot_knowledge brain backup, rebuild knowledge table, restore knowledge.back',
            'body'     => 'If the database knowledge table is damaged or emptied, run `php spark brain:restore` or click "Restore Knowledge" in the Brain Console. It rebuilds the table from E:\\html\\Brain\\knowledge.back.',
            'steps'    => ['Open Brain Console > Health as Admin.', 'Click "Restore Knowledge from Backup".', 'All approved articles are restored with full text search index.'],
            'actions'  => [['type' => 'open', 'label' => 'Open Brain Console', 'url' => 'brain']], 'page_match' => 'brain', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Why does the sidebar collapse state reset after browser restart?',
            'keywords' => 'sidebar collapse state reset, remember sidebar collapsed, localstorage sidebar',
            'body'     => 'Sidebar state is stored in your browser\'s localStorage under key "nx.side". If your browser is set to "Clear cookies and site data on close", the preference is cleared upon restart.',
            'steps'    => ['In Chrome settings, ensure "Clear cookies and site data when you close all windows" is disabled for NEXUS-L domain.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'Where are system application error logs stored on the server?',
            'keywords' => 'where are system error logs stored, writable logs codeigniter, debug error log path',
            'body'     => 'Application logs are written daily to writable/logs/log-YYYY-MM-DD.log on the server. Administrators can view error traces and database query exceptions there.',
            'steps'    => ['Open writable\\logs\\ on Drive E:.', 'Open the latest date log file in a text editor to view detailed stack traces.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to check available disk space on Drive E: for NEXUS-L?',
            'keywords' => 'check available disk space drive e, disk space nexus-l, server storage check',
            'body'     => 'In Settings > Database in use, the table size and total storage footprint are summarized. On the server machine, check Drive E: properties in File Explorer to verify free disk space.',
            'steps'    => ['Open Settings > Database in use.', 'Review the MB storage statistics.', 'Ensure at least 10 GB of free space is available on Drive E: for report PDFs.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to change the idle session timeout from 30 minutes?',
            'keywords' => 'change idle session timeout 30 minutes, auth idletimeoutminutes, session duration config',
            'body'     => 'The session idle timeout is defined in .env under auth.idleTimeoutMinutes = 30. Administrators can update this value to 60 or 120 minutes if technicians require longer intervals.',
            'steps'    => ['Open E:\\html\\NEXUS-L\\.env in a text editor as Admin.', 'Update `auth.idleTimeoutMinutes = 60`.', 'Save the file. The new timeout applies to new sign-ins.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'User cannot see "Settings" or "Report Designer" in the sidebar',
            'keywords' => 'user cannot see settings designer sidebar, missing sidebar items, role permission sidebar',
            'body'     => 'Settings and Report Designer are restricted strictly to Role = Admin. Users with Role = Technician, Reviewer, or User only see their assigned operational modules.',
            'steps'    => ['If the user is an Administrator, update their Role to "Admin" in User Management.', 'The user must sign out and sign in again to refresh role permissions.'],
            'actions'  => $settingsAct, 'page_match' => 'settings',
        ];
        $p[] = [
            'title'    => 'Error: "Maximum upload file size exceeded (20MB limit)"',
            'keywords' => 'maximum upload file size exceeded, 20mb upload limit, upload file too large',
            'body'     => 'NEXUS-L restricts file uploads to 20 MB per file to prevent server memory exhaustion. If a scanned document is larger than 20 MB, compress the scan or lower scanner DPI to 150.',
            'steps'    => ['Re-scan the document at 150 DPI greyscale or color.', 'Ensure file size is under 20 MB before uploading.'],
            'actions'  => $dtrMatAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'How to export all laboratory test reports to an Excel summary?',
            'keywords' => 'export test reports excel summary, download lab tracker csv, export reports xlsx',
            'body'     => 'In QC Lab Reports > Lab Tracker, use the search and date filters to narrow down your records, then click "Export CSV / Excel" at the top right of the table.',
            'steps'    => ['Open Lab Tracker (dtr?tab=tracker).', 'Filter by date, brand, or report type.', 'Click "Export to CSV". An Excel-compatible file downloads immediately.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'What to do when the web server shows "503 Service Unavailable"?',
            'keywords' => '503 service unavailable, web server down 503, php version error 503',
            'body'     => 'A 503 error on NEXUS-L means either PHP is stopped, or the PHP version is lower than the required PHP 8.2 minimum (check public/index.php).',
            'steps'    => ['Verify the Apache or IIS web service is running on the server.', 'Check that PHP 8.2 or higher is configured.', 'Review Apache error.log for syntax errors.'],
            'actions'  => [['type' => 'open', 'label' => 'Dashboard', 'url' => 'dashboard']], 'page_match' => 'dashboard',
        ];
        $p[] = [
            'title'    => 'How to verify PHP version compatibility (PHP 8.2+ required)?',
            'keywords' => 'verify php version compatibility, php 8.2 required, check php version',
            'body'     => 'Open PowerShell on the server and run `php -v`. NEXUS-L requires PHP 8.2.0 or newer with extensions curl, mysqli, mbstring, and openssl enabled.',
            'steps'    => ['Open terminal on server.', 'Run `php -v`.', 'Ensure PHP 8.2+ is reported.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to restart the local AI assistant stack with run-ai.cmd?',
            'keywords' => 'restart local ai stack run-ai.cmd, restart ai bot, bounce ai service',
            'body'     => 'Run `E:\\html\\NEXUS-L\\run-ai.cmd` from a command prompt or PowerShell on the server PC. It restarts PostgreSQL (port 5440), Phoenix (port 6006), and FastAPI (port 8765).',
            'steps'    => ['Open command prompt on the server.', 'Run `E:\\html\\NEXUS-L\\run-ai.cmd`.', 'Open http://127.0.0.1:8765/health to verify service status.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'Why does the bot say "Checking system status..." instead of answering?',
            'keywords' => 'bot says checking system status, bot abstaining, safe abstain message, low confidence answer',
            'body'     => 'Under strict Phase 200 zero-hallucination rules, if the AI service confidence score falls below your calibrated threshold (e.g. 0.88), the bot safely abstains rather than inventing an incorrect answer.',
            'steps'    => ['Rephrase your question with specific keywords (e.g. mention the form name, Lab Ref, or error message).', 'Or click "Talk to an administrator" to escalate directly.'],
            'actions'  => $dtrTrackerAct, 'page_match' => 'dtr',
        ];
        $p[] = [
            'title'    => 'How to view real-time traces and latency in Arize Phoenix (port 6006)?',
            'keywords' => 'view traces latency arize phoenix, open phoenix 6006, ai observability traces',
            'body'     => 'Phoenix runs on http://127.0.0.1:6006. Open that URL on the server machine to view live execution spans, prompt tokens, self-correction loops, and groundedness evaluations.',
            'steps'    => ['Open browser on server PC.', 'Navigate to http://127.0.0.1:6006.', 'Click "Traces" to inspect step-by-step LangGraph node executions.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to verify Mem0 long-term memory retrieval for user sessions?',
            'keywords' => 'verify mem0 long term memory, mem0 user recall, user profile memory bot',
            'body'     => 'Mem0 stores cross-session user memories in Qdrant vector storage under C:\\NexusAI\\qdrant. When a user asks repeat questions, the memory node recalls previous interactions automatically.',
            'steps'    => ['Inspect the trace in Phoenix on port 6006.', 'Look for node "memory" > mem0 recall count.', 'Verify remembered context.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to unblock an account locked due to forgotten password?',
            'keywords' => 'unblock locked account admin, clear login attempts tbl_users, unlock user account',
            'body'     => 'An Administrator can unblock a locked account immediately in User Management, or by executing `UPDATE tbl_users SET LoginAttempts = 0, LockoutUntil = NULL WHERE username = ?` in dbnexus.',
            'steps'    => ['Sign in as Admin.', 'Open User Management.', 'Locate the locked user and click "Unlock Account".'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to update factory signatory list when a manager leaves?',
            'keywords' => 'update signatory list manager leaves, replace manager signatory, signatory turnover',
            'body'     => 'In Report Designer > Signatories, deactivate the former manager\'s card and click "+ New Signatory" to register the replacement manager with their name, title, and signature.',
            'steps'    => ['Open Report Designer > Signatories as Admin.', 'Toggle the former manager\'s card to Inactive.', 'Click "+ New Signatory" to add the new manager.'],
            'actions'  => $settingsAct, 'page_match' => 'designer', 'role_min' => 'admin',
        ];
        $p[] = [
            'title'    => 'How to verify daily automated backup tasks for dbnexus?',
            'keywords' => 'verify daily automated backup tasks, dbnexus backup check, database backup verify',
            'body'     => 'Database backups run nightly. Check writable/backups/ on Drive E: for timestamped .sql.gz archives. Ensure file size is non-zero and dated today.',
            'steps'    => ['Open E:\\html\\NEXUS-L\\writable\\backups\\.', 'Confirm today\'s backup file exists and is larger than 10 MB.', 'Store an off-site copy on Synology NAS.'],
            'actions'  => $settingsAct, 'page_match' => 'settings', 'role_min' => 'admin',
        ];

        if (class_exists(ProblemKnowledgeSeeder2::class)) {
            $p = array_merge($p, ProblemKnowledgeSeeder2::problems());
        }

        return $p;
    }
}
