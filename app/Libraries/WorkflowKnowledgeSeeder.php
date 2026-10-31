<?php

namespace App\Libraries;

use Config\Database;

/**
 * WorkflowKnowledgeSeeder
 *
 * Seeds comprehensive laboratory workflow, report generation, machine file upload,
 * approval dispatch, and result interpretation knowledge into tbl_bot_knowledge.
 */
class WorkflowKnowledgeSeeder
{
    public static function run(): array
    {
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $items = [
            [
                'kind' => 'playbook',
                'title' => 'How to Upload & Import Raw Machine Files (U60, UTM, Universal Machine Outdata)',
                'keywords' => 'upload machine file, import excel outdata, u60 machine file, raw machine readings, load file error, tensile machine upload, bonding upload',
                'body' => "### How to Upload & Import Raw Machine Files in NEXUS-L\n\n"
                        . "NEXUS-L imports raw data directly from laboratory testing machines (Gotech U60, Universal Tensile Machines, SATRA apparatus):\n\n"
                        . "1. **Navigate to the Test Form:** Go to **QC Lab Reports** > **Material Tests** (for tensile, tear, abrasion) or **Shoe Bonding** (for sole adhesion).\n"
                        . "2. **Select Material / Form Type:** Choose the test form corresponding to your machine run.\n"
                        . "3. **Click 'Load Machine File':** In the top actions bar, click **Load Machine File** (or drag & drop your `.xlsx` / `.csv` file into the upload zone).\n"
                        . "4. **Automatic Column Mapping:** The system automatically extracts peak force, displacement, test date, and specimen numbers from the standardized machine outdata.\n"
                        . "5. **Verify Readings:** Check that the extracted points (Toe, Ball, Waist, Heel or Dumbbell specimens) populate the data grid correctly.\n\n"
                        . "> [!IMPORTANT]\n"
                        . "> Ensure the machine file is saved in `.xlsx` or `.csv` format from the U60 software. Do not alter column headers in Excel before uploading.",
                'steps' => [
                    'Open the target test form under QC Lab Reports (Material Tests or Shoe Bonding).',
                    'Click "Load Machine File" in the top action toolbar.',
                    'Select the exported machine file (.xlsx/.csv) from the networked machine share or local drive.',
                    'Verify that specimen readings and graph curves match the machine console output.',
                    'Save the test report to assign a unique DTP Lab Reference number.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Material Tests (DTR)', 'url' => site_url('dtr?tab=material')],
                    ['type' => 'open', 'label' => 'Open Shoe Bonding (DTR)', 'url' => site_url('dtr?tab=bonding')],
                    ['type' => 'open', 'label' => 'Open Lab Test Tracker', 'url' => site_url('dtr?tab=tracker')],
                ],
                'page_match' => 'dtr',
            ],
            [
                'kind' => 'playbook',
                'title' => 'How to Generate, Preview & Download Laboratory PDF Reports',
                'keywords' => 'generate report, download pdf report, print report, export pdf, preview pdf, satra logo printout, vf logo missing, make report',
                'body' => "### How to Generate, Preview & Download PDF Reports in NEXUS-L\n\n"
                        . "To generate and print official laboratory test reports with VF and SATRA accredited headers:\n\n"
                        . "1. **Find or Create the Report:** Open the saved test in **Material Tests**, **Shoe Bonding**, or the **Lab Tracker**.\n"
                        . "2. **Click 'Print / PDF Preview':** Click the **Print Preview** button in the form header or tracker table.\n"
                        . "3. **Review Report Layout:** The preview renders the official standard layout including style name, colorway, season, lab reference number, date, machine graph, individual readings, and digital signature boxes.\n"
                        . "4. **Download PDF:** Click **Download PDF** to export the high-resolution vector PDF to your computer.\n"
                        . "5. **Direct Print:** Click **Print** to send directly to your network printer formatted for standard A4 paper with zero scaling shifts.",
                'steps' => [
                    'Navigate to QC Lab Reports > Lab Tracker or open an active test record.',
                    'Click "Print / Preview" to view the generated document layout.',
                    'Verify all test parameters, required specifications, and overall pass/fail results are correct.',
                    'Click "Download PDF" to save locally or "Print" to send to the lab printer.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')],
                    ['type' => 'open', 'label' => 'Open Material Tests', 'url' => site_url('dtr?tab=material')],
                ],
                'page_match' => 'dtr',
            ],
            [
                'kind' => 'playbook',
                'title' => 'How to Send Reports & Synology Approval Notifications to Managers',
                'keywords' => 'send report, notify approver, send notification for approval, synology chat approval, remind manager, send approval link, resend notification',
                'body' => "### How to Send Reports for Manager Approval via Synology Chat\n\n"
                        . "When a test report is completed and saved, it must be submitted for authorized managerial sign-off:\n\n"
                        . "1. **Save Test Report:** Ensure all test fields and overall results are marked.\n"
                        . "2. **Click 'Send for Approval':** Click the **Send for Approval** button in the DTR action toolbar.\n"
                        . "3. **Select Designated Signatory:** Select the designated Lab Supervisor, Quality Manager, or Technical Signatory from the dropdown list.\n"
                        . "4. **Automatic Synology Notification:** The system automatically dispatches an encrypted 72-hour approval link directly to the manager's Synology Chat inbox with test summary, style number, and result.\n"
                        . "5. **Resending Reminders:** If a report remains pending, ask the bot *\"resend notification for approver\"* or click **Resend Approval** in the Lab Tracker.",
                'steps' => [
                    'Open the completed test report and ensure all required readings are saved.',
                    'Click "Send for Approval" and verify the designated manager signatory.',
                    'The system sends a direct Synology Chat notification with a one-click digital signing link.',
                    'Track approval progress in the Lab Tracker under the "Approval Status" column.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')],
                    ['type' => 'open', 'label' => 'View Pending Approvals', 'url' => site_url('dtr?tab=tracker&filter=pending')],
                ],
                'page_match' => 'dtr',
            ],
            [
                'kind' => 'playbook',
                'title' => 'Understanding Overall Results: Pass, Fail & Tolerable Criteria in NEXUS-L',
                'keywords' => 'overall result, pass or fail, tolerable result, what is pass or fail, result calculation, why did test fail, tolerance threshold',
                'body' => "### Understanding Overall Test Results in NEXUS-L\n\n"
                        . "Every laboratory report evaluates specimen measurements against global VF footwear quality specifications:\n\n"
                        . "• **PASS (Green):** All tested specimens and critical measurement points meet or exceed the minimum threshold specified in the VANS Footwear Performance Testing Manual.\n"
                        . "• **TOLERABLE (Yellow):** The sample average meets the minimum specification, but individual readings fall within an accepted minor tolerance band (e.g., individual sole bond points between 2.5 kg/cm and 3.0 kg/cm where whole bottom average exceeds 3.0 kg/cm).\n"
                        . "• **FAIL (Red):** The sample average or individual critical points fall below the minimum acceptable threshold. Requires physical quarantine, root cause analysis, and production line notification.",
                'steps' => [
                    'Check the specification column next to your test readings.',
                    'Verify individual specimen values against the threshold (e.g. Tensile >= 10 MPa, Sole Bond >= 3.0 kg/cm).',
                    'Review the automatically computed average and tolerance ratings in the Summary card.',
                    'If result is FAIL, initiate the QC Problem Remediation workflow.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')],
                    ['type' => 'open', 'label' => 'Open VANS Performance Manual 2026', 'url' => site_url('uploads/vf_test_methods/VANS  Footwear Performance Testing Manual 2026.pdf'), 'target' => '_blank'],
                ],
                'page_match' => 'dtr',
            ],
            [
                'kind' => 'playbook',
                'title' => 'Shoe Bonding Calculation Guide: Individual Points vs Whole Bottom Average',
                'keywords' => 'bonding calculation, toe ball waist heel average, shoe bonding formula, bonding tolerance, sole adhesion calculation, kg cm formula',
                'body' => "### Shoe Bonding Test Calculation Formula (VF-F01)\n\n"
                        . "In Shoe Bonding (VF-F01), bond strength is measured across 4 critical anatomical zones:\n\n"
                        . "1. **Toe Area:** Minimum individual point threshold: **2.5 kg/cm (24.5 N/mm)**\n"
                        . "2. **Ball Area (Medial & Lateral):** Minimum threshold: **3.0 kg/cm (29.4 N/mm)**\n"
                        . "3. **Waist / Shank Area:** Minimum threshold: **3.0 kg/cm (29.4 N/mm)**\n"
                        . "4. **Heel Area:** Minimum threshold: **3.0 kg/cm (29.4 N/mm)**\n\n"
                        . "**Whole Bottom Average Formula:**  \n"
                        . "`Average = (Toe + Ball_Medial + Ball_Lateral + Waist + Heel) / Total_Points`  \n"
                        . "Whole bottom average must be **>= 3.0 kg/cm**. If any single point is below 2.5 kg/cm, the overall test is marked **FAIL** regardless of the average.",
                'steps' => [
                    'Measure peel force at Toe, Ball (Inside/Outside), Waist, and Heel test strips.',
                    'Record readings in kg/cm or N/mm in the Shoe Bonding data table.',
                    'Check failure mode (AF = Adhesive Failure, CF = Cohesive Foam Failure, MF = Material Tear).',
                    'NEXUS-L automatically calculates point averages and overall compliance.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Shoe Bonding Form', 'url' => site_url('dtr?tab=bonding')],
                    ['type' => 'open', 'label' => 'Open VF-F01 Method Standard', 'url' => site_url('uploads/vf_test_methods/VF%20-%20F01%20Footwear%20Adhesion%20-%20May%202019.pdf'), 'target' => '_blank'],
                ],
                'page_match' => 'dtr',
            ],
            [
                'kind' => 'playbook',
                'title' => 'DIN Abrasion Volume Loss Formula & Specific Gravity Integration',
                'keywords' => 'din abrasion formula, volume loss calculation, specific gravity abrasion, mm3 formula, mass loss abrasion, iso 4649 formula',
                'body' => "### DIN Rotary Drum Abrasion Volume Loss Calculation (VF-C01)\n\n"
                        . "DIN abrasion resistance (VF-C01 / ISO 4649) is expressed as volume loss in cubic millimeters (mm³):\n\n"
                        . "**Formula:**  \n"
                        . "`Volume Loss (mm³) = (Mass Loss (mg) * Reference Constant (200 mg)) / (Density (g/cm³) * Standard Rubber Mass Loss (mg))`\n\n"
                        . "1. Measure specimen specific gravity / density using **VF-A04** Archimedes displacement.\n"
                        . "2. Weigh specimen before test (\$M_1) and after 40-meter drum cycle (\$M_2).\n"
                        . "3. Mass Loss \$\Delta M = \$M_1 - \$M_2.\n"
                        . "4. NEXUS-L calculates volume loss automatically. Lower volume loss indicates superior abrasion wear resistance (Standard requirement: **<= 150 mm³** for vulcanized waffle outsoles).",
                'steps' => [
                    'Measure specimen specific gravity first using VF-A04.',
                    'Perform 40-meter abrasion run on P60 sandpaper rotating drum.',
                    'Enter initial mass, final mass, and reference rubber loss into the DIN Abrasion calculator.',
                    'Verify calculated mm3 volume loss against the maximum allowed threshold.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Material Tests (DIN Abrasion)', 'url' => site_url('dtr?tab=material')],
                    ['type' => 'open', 'label' => 'Open VF-C01 Standard PDF', 'url' => site_url('uploads/vf_test_methods/VF%20-%20C01%20DIN%20Abrasion%20Nov%202022.pdf'), 'target' => '_blank'],
                ],
                'page_match' => 'dtr',
            ],
            [
                'kind' => 'playbook',
                'title' => 'Report Approvals: Handling Expired Links & Changing Designated Signatories',
                'keywords' => 'approval link expired, change approver, resend approval, digital signature expired, signatory link timeout, manager cannot sign',
                'body' => "### Handling Expired Approval Links & Changing Signatories\n\n"
                        . "For laboratory security, all manager approval links dispatched via Synology Chat expire after **72 hours**:\n\n"
                        . "• **If Link Has Expired:** The manager will see an *\"Approval Link Expired\"* notice. Any authorized technician or supervisor can click **Resend Approval** in the Lab Tracker to generate a fresh 72-hour link.\n"
                        . "• **If Signatory Is On Leave:** Open the report in the Lab Tracker, click **Reassign Signatory**, choose the acting manager, and click **Send Notification**.\n"
                        . "• **Digital Audit Trail:** Every approval action records the signer's identity, IP address, timestamp, and optional approval remarks.",
                'steps' => [
                    'Go to QC Lab Reports > Lab Tracker.',
                    'Find the pending report with the expired link indicator.',
                    'Click "Resend Approval" to deliver a new notification to the manager\'s Synology Chat.',
                    'Alternatively, select a new authorized signatory and confirm the transfer.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')],
                    ['type' => 'open', 'label' => 'View Pending Approvals', 'url' => site_url('dtr?tab=tracker&filter=pending')],
                ],
                'page_match' => 'dtr',
            ],
            [
                'kind' => 'playbook',
                'title' => 'InterLink Cloud Sync: Queue Monitoring & Troubleshooting Transmission Failures',
                'keywords' => 'interlink sync queue, interlink failed upload, sync report to interlink, cloud transmission stuck, retry interlink sync, portal upload error',
                'body' => "### InterLink Cloud Synchronization & Queue Management\n\n"
                        . "NEXUS-L synchronizes completed test records with the central InterLink enterprise cloud:\n\n"
                        . "1. **Sync Trigger:** Once a report receives final managerial approval, it is queued for cloud transmission.\n"
                        . "2. **InterLink Sync Center:** Go to **InterLink** > **Sync Center** to monitor real-time queue health, pending payloads, and confirmed deliveries.\n"
                        . "3. **Troubleshooting Failures:** If a sync fails due to network downtime or portal session timeout, the report is flagged with a red **Retry** button.\n"
                        . "4. **Manual Sync Retry:** Click **Retry Failed** to immediately re-transmit payloads with zero data loss.",
                'steps' => [
                    'Open the InterLink Sync Center from the top navigation bar.',
                    'Inspect the queue cards for "Pending", "Transmitted", or "Failed" statuses.',
                    'If network dropped during sync, click "Retry Transmission" on the failed payload.',
                    'Confirm sync receipt with the green InterLink confirmation timestamp.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open InterLink Sync Center', 'url' => site_url('interlink/center')],
                    ['type' => 'open', 'label' => 'View Sync Queue', 'url' => site_url('interlink')],
                ],
                'page_match' => 'interlink',
            ],
            [
                'kind' => 'playbook',
                'title' => 'Comprehensive Failure Remediation Guide: What to Do When a VF Test Fails',
                'keywords' => 'test failed what to do, remediation plan, failed test procedure, quarantine failed shoes, root cause analysis, qc failure action plan, why did test fail, why did sole adhesion fail, how to fix sole separation, how to fix test failure, remediation guide for failed test',
                'body' => "### Laboratory Test Failure Protocol & Action Plan\n\n"
                        . "When a test result is marked **FAIL** in NEXUS-L, follow the mandatory VF quality escalation protocol:\n\n"
                        . "1. **Verification Test:** Retest 3 additional specimens from the same production lot to verify if the failure is an isolated defect or a systematic batch issue.\n"
                        . "2. **Production Lot Quarantine:** Place affected production pairs on immediate physical and digital hold in the factory tracking system.\n"
                        . "3. **Root Cause Analysis (RCA):**\n"
                        . "   - *Sole Separation (VF-F01):* Check primer activation temperature (55-65°C), cement open time, and press pressure.\n"
                        . "   - *Upper Flex Cracking (VF-D01):* Inspect leather fatliquor content and coating thickness.\n"
                        . "   - *Ross Flex Sole Crack (VF-D02):* Check vulcanization cure time and sulfur compound dispersion.\n"
                        . "   - *UV Yellowing (VF-E03):* Inspect anti-yellowing antioxidant additive levels in white rubber.\n"
                        . "4. **Log Problem in Brain:** Document the issue in the NEXUS-L problem registry to track recurring supplier trends.",
                'steps' => [
                    'Record the failed readings in NEXUS-L and notify the lab supervisor.',
                    'Quarantine the corresponding production lot immediately.',
                    'Conduct verification re-testing on 3 additional samples.',
                    'Document root cause and preventive corrective action (CAPA).'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')],
                    ['type' => 'open', 'label' => 'Open VANS Performance Manual 2026', 'url' => site_url('uploads/vf_test_methods/VANS  Footwear Performance Testing Manual 2026.pdf'), 'target' => '_blank'],
                ],
                'page_match' => 'dtr',
            ],
            [
                'kind' => 'playbook',
                'title' => 'How to Check Laboratory Statistics, Pass Rates & Historical DTR Records',
                'keywords' => 'check lab statistics, pass fail rate, search old reports, monthly pass rate, supplier performance summary, lab analytics, how many reports passed',
                'body' => "### Checking Laboratory Analytics & Historical Test Records\n\n"
                        . "NEXUS-L maintains complete historical data and real-time pass/fail analytics:\n\n"
                        . "• **Ask LEXIS AI:** Simply ask the bot *\"how many reports failed this month\"*, *\"what is the bonding pass rate\"*, or *\"find failed reports for Vans\"* to receive instant live statistics.\n"
                        . "• **Lab Tracker Search:** Navigate to **QC Lab Reports** > **Lab Tracker**. Filter by Date Range, Model Style, Supplier, Form Category, or Overall Result (Pass/Fail/Tolerable).\n"
                        . "• **One-Click Reopen:** Click any Lab Reference number (`DTP...`) in search results to open, preview, or print the original test report.",
                'steps' => [
                    'Open QC Lab Reports > Lab Tracker.',
                    'Use the search filter bar to filter by date, supplier, style, or result.',
                    'Or ask LEXIS AI natural language database questions (e.g., "bonding test summary").',
                    'Click any report reference to view detailed graphs and readings.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Lab Tracker', 'url' => site_url('dtr?tab=tracker')],
                    ['type' => 'open', 'label' => 'Open Lab Ref Index', 'url' => site_url('dtr?tab=tracker&filter=all')],
                ],
                'page_match' => 'dtr',
            ],
        ];

        foreach ($items as $item) {
            $existing = $db->table('tbl_bot_knowledge')->where('title', $item['title'])->get()->getRowArray();
            $data = [
                'kind' => $item['kind'],
                'title' => mb_substr($item['title'], 0, 200),
                'keywords' => mb_substr($item['keywords'], 0, 600),
                'body' => $item['body'],
                'steps_json' => json_encode($item['steps'], JSON_UNESCAPED_UNICODE),
                'actions_json' => json_encode($item['actions'], JSON_UNESCAPED_SLASHES),
                'page_match' => $item['page_match'],
                'error_match' => null,
                'diagnose' => null,
                'role_min' => 'user',
                'status' => 'approved',
                'source' => 'workflow_seed',
                'updated_at' => $now,
            ];

            if ($existing) {
                $db->table('tbl_bot_knowledge')->where('id', $existing['id'])->update($data);
                $updated++;
            } else {
                $db->table('tbl_bot_knowledge')->insert($data + ['created_at' => $now]);
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }
}
