<?php

namespace App\Libraries;

use Config\Database;

/**
 * SystemWorkflowSeeder
 * 
 * Seeds 18 comprehensive, actionable playbooks for all website processes and active tasks
 * outside raw test methods:
 * - Generating and downloading PDF reports & certificates
 * - Material testing: creating sessions, starting timers, stopping samples
 * - Logging intermediate defect findings & cycle milestones
 * - Whole shoe testing suite & technician tracking
 * - Test results summary suite & multi-parameter filtering
 * - Exporting summary data and DTR logs to Excel/CSV
 * - DTR sample intake, receiving & supplier batching
 * - Retest management for failed production lots
 * - Shoe lookup, style search & PO tracking
 * - Raw machine Excel file upload & U60 parsing
 * - Manager approvals, QR verification & Synology Chat webhooks
 * - InterLink 2.0 cloud auto-fill & grid synchronization
 * - Laboratory analytics, throughput & defect Pareto charts
 * - Report designer visual customization & grid mapping
 * - Team inbox real-time chat, file sharing & groups
 * - System administration, live DB switching & git sync
 * - Brain AI console, live chat takeover & question review
 */
class SystemWorkflowSeeder
{
    public static function run(): array
    {
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $tasks = self::getTaskPlaybooks();

        foreach ($tasks as $task) {
            $existing = $db->table('tbl_bot_knowledge')->where('title', $task['title'])->get()->getRowArray();
            $data = [
                'kind' => 'playbook',
                'title' => mb_substr($task['title'], 0, 200),
                'keywords' => mb_substr($task['keywords'], 0, 600),
                'body' => $task['body'],
                'steps_json' => json_encode($task['steps'], JSON_UNESCAPED_UNICODE),
                'actions_json' => json_encode($task['actions'], JSON_UNESCAPED_SLASHES),
                'page_match' => $task['page_match'],
                'source' => 'system_workflow',
                'status' => 'approved',
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

    public static function getTaskPlaybooks(): array
    {
        return [
            // 1. GENERATING PDF REPORTS
            [
                'title' => 'How to Generate, Download & Print Official PDF Reports',
                'keywords' => 'generate pdf report, download pdf report, print test report, create pdf certificate, export report to pdf, printout on a4, pdf download button, generate test report dtr',
                'body' => "### Generating & Downloading Laboratory PDF Reports\n\n"
                        . "NEXUS-L provides vector-accurate PDF generation for material tests, whole shoe physical tests, and sole bonding certificates.\n\n"
                        . "#### Step-by-Step Instructions:\n"
                        . "1. **Open DTR / Reports:** Go to the **Digital Test Reports (DTR)** module.\n"
                        . "2. **Select Report:** Open the test record from the table (or click the test ID link).\n"
                        . "3. **Generate PDF:** Click the **Generate PDF** button (or **Sheet PDF** for material tests).\n"
                        . "4. **Preview & Verify:** The system renders a 100% vector preview including VF and SATRA official logos, specimen photo attachments, parameter tables, and signature blocks.\n"
                        . "5. **Print or Save:** Use the toolbar icons to download the PDF file or send directly to the printer (select **A4 paper**, 100% scale, no margins).",
                'steps' => [
                    'Navigate to DTR (Digital Test Reports) module.',
                    'Select the target test record from the database table.',
                    'Click "Generate PDF" or "Preview Report".',
                    'Verify header logos, readings table, and QR code verification block.',
                    'Download or print directly on standard A4 paper at 100% scale.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open DTR Reports', 'url' => site_url('dtr')],
                    ['type' => 'open', 'label' => 'Generate Whole Shoe PDF', 'url' => site_url('dtr/wholeshoe-report-pdf'), 'target' => '_blank'],
                    ['type' => 'open', 'label' => 'View Report Approvals', 'url' => site_url('dtr/approval/feed')],
                ],
                'page_match' => 'dtr',
            ],

            // 2. MATERIAL TESTING: STARTING RUNS & TIMERS
            [
                'title' => 'How to Create, Start & Monitor Material Tests (Live Timers)',
                'keywords' => 'start material test, create material test session, material testing timer, start test stopwatch, run material test, pause test timer, launch physical test, wst create',
                'body' => "### Material Testing Suite: Starting & Running Tests\n\n"
                        . "The Material Testing suite tracks active laboratory machine runs, stopwatches, and cyclic milestones in real-time.\n\n"
                        . "#### How to Start a Material Test:\n"
                        . "1. **Open Material Testing:** Navigate to `/materials` (or click **Material Testing** in the navigation sidebar).\n"
                        . "2. **Create Test Session:** Click the **+ New Material Test** button.\n"
                        . "3. **Select Method:** Choose the target test standard (e.g. VF-B01 Tensile, VF-B05 Tear, VF-C01 Abrasion, VF-D01 Bally Flex, VF-D02 Ross Flex, VF-E02 Crocking).\n"
                        . "4. **Assign Sample & Technician:** Select the intake batch or lab reference number and designate the operating technician.\n"
                        . "5. **Start Timer:** Click **Start Test** (`wst/start/(:num)`). The card displays a running green stopwatch and cycle counter.\n"
                        . "6. **Pausing or Stopping:** Click **Pause** to hold the session or **Stop Test** upon reaching target cycles.",
                'steps' => [
                    'Open the Material Testing module from the sidebar navigation.',
                    'Click "+ New Material Test" to initialize a test session.',
                    'Select the physical test standard and target specimen.',
                    'Click "Start Test" to begin the live timer and cycle counter.',
                    'Monitor progress and click "Stop Test" upon reaching test duration.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Material Testing Suite', 'url' => site_url('materials')],
                    ['type' => 'open', 'label' => 'View Running Material Tests', 'url' => site_url('materials?status=running')],
                    ['type' => 'open', 'label' => 'Open Test Results Summary', 'url' => site_url('test-summary')],
                ],
                'page_match' => 'materials',
            ],

            // 3. LOGGING FINDINGS & CYCLE CHECKPOINTS
            [
                'title' => 'How to Log Intermediate Findings & Cycle Checkpoints in Material Testing',
                'keywords' => 'log finding material test, intermediate findings, log defect during test, cycle checkpoint, record crack bally flex, inspection milestone, wst log findings, specimen observations',
                'body' => "### Logging Intermediate Defect Findings During Testing\n\n"
                        . "For cyclic fatigue tests (Bally Flex, Ross Flex, Martindale, Whole Shoe Flex), technicians must record intermediate inspection findings at specific cycle milestones.\n\n"
                        . "#### Procedure for Logging Findings:\n"
                        . "1. **Locate Running Test Card:** Find the active test on the **Material Testing** board.\n"
                        . "2. **Click Log Findings:** Press the **Log Findings** button on the test card (`wst/log-findings/(:num)`).\n"
                        . "3. **Enter Milestone Cycles:** Enter the current cycle count (e.g. 10,000, 25,000, 50,000, 100,000 cycles).\n"
                        . "4. **Select Finding Type:** Choose defect classification: *Surface micro-crack, Finish delamination, Color bleeding, Stitch rupture, Yarn severance, or Normal/No Defect*.\n"
                        . "5. **Attach Inspection Notes & Photos:** Type qualitative remarks and upload high-resolution photos under magnification.\n"
                        . "6. **Save Record:** Click **Save Finding**. The inspection checkpoint is permanently timestamped on the official test log.",
                'steps' => [
                    'Locate the active test session card on the Material Testing board.',
                    'Click the "Log Findings" button.',
                    'Input the observed cycle count milestone.',
                    'Select defect classification from the standardized dropdown.',
                    'Add technician notes and photo attachments, then click Save.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Material Testing Board', 'url' => site_url('materials')],
                    ['type' => 'open', 'label' => 'View Test Results Summary', 'url' => site_url('test-summary')],
                ],
                'page_match' => 'materials',
            ],

            // 4. WHOLE SHOE TESTING SUITE
            [
                'title' => 'How to Run Whole Shoe Physical & Monitoring Tests (Walking Flex & Waterproof)',
                'keywords' => 'whole shoe testing, run whole shoe test, walking flex test run, dynamic waterproof shoe test, wholeshoe module, satra tm92 whole shoe, wst run, technician whole shoe test',
                'body' => "### Whole Shoe Physical Testing Suite Management\n\n"
                        . "The Whole Shoe Testing module (`/wholeshoe`) manages full footwear performance evaluations including walking flex simulation (SATRA TM92) and dynamic waterproof flexing (SATRA TM230).\n\n"
                        . "#### Operational Guide:\n"
                        . "1. **Access Whole Shoe Suite:** Click **Whole Shoe Test** in the main navigation.\n"
                        . "2. **Create New Session:** Click **+ New Whole Shoe Test**.\n"
                        . "3. **Assign Parameters:** Enter Style Name, Factory Mold Code, Shoe Size (e.g. US 9.0), Test Standard (VF-G02 Walking Flex or VF-G04 Waterproof Flex), and Target Cycles (150,000 flexes).\n"
                        . "4. **Assign Technician:** Designate the operator responsible for machine setup.\n"
                        . "5. **Start Run:** Click **Start Test**. The system initializes the session with cycle counters and periodic inspection alarms.\n"
                        . "6. **Stop Individual Sample:** If a sample develops catastrophic sole separation or water leaks, click **Stop Sample** (`wst/stop-sample/(:num)`) to halt the damaged shoe while other pairs continue.",
                'steps' => [
                    'Navigate to the Whole Shoe Testing suite (/wholeshoe).',
                    'Click "+ New Whole Shoe Test" to set up a new test run.',
                    'Input shoe style, size, mold code, and target cycle count.',
                    'Assign the technician and click "Start Test".',
                    'Use "Stop Sample" if an individual shoe fails before full cycle target.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Whole Shoe Test Suite', 'url' => site_url('wholeshoe')],
                    ['type' => 'open', 'label' => 'View Whole Shoe Reports', 'url' => site_url('dtr/wholeshoe-report')],
                    ['type' => 'open', 'label' => 'Open Test Results Summary', 'url' => site_url('test-summary')],
                ],
                'page_match' => 'wholeshoe',
            ],

            // 5. TEST RESULTS SUMMARY & FILTERING
            [
                'title' => 'How to Filter & View Unified Test Results Summary (Materials & Whole Shoe)',
                'keywords' => 'where can i see which shoes passed or failed today, see which shoes passed or failed today, which shoes passed or failed today, passed or failed today shoes summary, test results summary, test summary table, filter test results, view all test records, filter by season brand, search test summary',
                'body' => "### Unified Test Results Summary Suite\n\n"
                        . "The **Test Results Summary** (`/test-summary`) combines all Material Testing and Whole Shoe Testing records into a single interactive monitoring dashboard.\n\n"
                        . "#### Summary Capabilities:\n"
                        . "1. **Multi-Parameter Filtering:** Filter records by:\n"
                        . "   - **Brand:** Vans, The North Face, Timberland, Altra.\n"
                        . "   - **Season:** SS25, FW25, Core / Carryover.\n"
                        . "   - **Status:** Passed, Tolerable, Failed, In Progress, Stopped.\n"
                        . "   - **Date Range:** Filter by test start or completion date.\n"
                        . "2. **Instant Search:** Search across Lab Ref #, Style Name, Factory Code, or Technician name.\n"
                        . "3. **Visual Status Badges:** Green (Pass), Yellow (Tolerable), Red (Fail), Blue (Running).\n"
                        . "4. **Quick Actions:** Click any row to view complete findings, technician remarks, or jump to the official PDF.",
                'steps' => [
                    'Navigate to Test Results Summary (/test-summary).',
                    'Select Brand and Season filters from the top filter bar.',
                    'Choose desired status (e.g. Failed, In Progress, Passed).',
                    'Use the search box for instant lookup by style, PO, or lab ref.',
                    'Click any row to open the full test details card.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Test Results Summary', 'url' => site_url('test-summary')],
                    ['type' => 'open', 'label' => 'Filter Failed Tests', 'url' => site_url('test-summary?status=fail')],
                    ['type' => 'open', 'label' => 'Export Summary to Excel', 'url' => site_url('wst/export-summary')],
                ],
                'page_match' => 'test-summary',
            ],

            // 6. EXPORTING SUMMARY TO EXCEL / CSV
            [
                'title' => 'How to Export Laboratory Summary & DTR Logs to Excel / CSV',
                'keywords' => 'export to excel, export test summary, download excel report, export csv dtr, export lab results spreadsheet, wst export summary, excel export test data, download xlsx',
                'body' => "### Exporting Laboratory Records to Excel & CSV\n\n"
                        . "Technicians and quality managers can export laboratory testing data to Excel (`.xlsx`) or CSV spreadsheets for reporting, statistical analysis, and supplier quality audits.\n\n"
                        . "#### How to Export Data:\n"
                        . "1. **Filter Records First:** In **Test Results Summary** (`/test-summary`), apply any desired filters (e.g. Season FW25, Brand Vans, Status Failed).\n"
                        . "2. **Click Export Excel:** Click the green **Export Excel** button on the summary toolbar (`wst/export-summary`).\n"
                        . "3. **Automated Spreadsheet Generation:** The system compiles all filtered records including:\n"
                        . "   - Lab Reference Number & Test Standard Code\n"
                        . "   - Style Name, Mold Code, Size & Colorway\n"
                        . "   - Individual Specimen Readings & Peak Values\n"
                        . "   - Calculated Averages & Acceptance Thresholds\n"
                        . "   - Overall Result Status (Pass / Tolerable / Fail)\n"
                        . "   - Technician Name & Test Timestamp\n"
                        . "4. **Instant Download:** The browser downloads the spreadsheet immediately.",
                'steps' => [
                    'Open the Test Results Summary suite (/test-summary).',
                    'Apply filters for brand, season, status, or date range.',
                    'Click the green "Export Excel" button.',
                    'Open the downloaded spreadsheet in Microsoft Excel or Google Sheets.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Export Current Summary (Excel)', 'url' => site_url('wst/export-summary')],
                    ['type' => 'open', 'label' => 'Open Test Results Summary', 'url' => site_url('test-summary')],
                ],
                'page_match' => 'test-summary',
            ],

            // 7. DTR SAMPLE INTAKE & RECEIVING
            [
                'title' => 'How to Receive Samples, Log Supplier Batches & Intake Footwear in DTR',
                'keywords' => 'where do i scan or check new delivery batch, scan or check new delivery batch, scan new delivery batch, check delivery batch intake, sample intake dtr, receive material, log sample batch, intake footwear, material receive save, sample log in, dtr intake save, supplier batch logging',
                'body' => "### Sample Intake & Material Receiving in DTR\n\n"
                        . "Before testing begins, every specimen and shoe pair must be formally logged into DTR to generate a unique Lab Reference ID and track supplier batch traceability.\n\n"
                        . "#### Intake Procedure:\n"
                        . "1. **Open DTR Module:** Go to `/dtr` and click the **Sample Intake** tab.\n"
                        . "2. **Enter Sample Metadata:**\n"
                        . "   - **Material / Component:** Outsole Rubber, Foxing Tape, Upper Leather, Canvas, Midsole EVA.\n"
                        . "   - **Supplier / Compound ID:** Enter supplier batch code and compound identification.\n"
                        . "   - **Colorway & Style:** Style name and production color code.\n"
                        . "   - **Quantity Received:** Number of swatches, cut parts, or whole pairs.\n"
                        . "3. **Conditioning Timer Start:** Record the date and time placed into the standard conditioning room (23°C 50% RH).\n"
                        . "4. **Save Intake:** Click **Save Intake Record** (`dtr/intake-save`). The system issues the official sequential **Lab Ref #** (e.g. `LR-2026-0842`).",
                'steps' => [
                    'Navigate to DTR and switch to the "Sample Intake" tab.',
                    'Fill in component material, supplier compound code, and style metadata.',
                    'Record quantity received and timestamp conditioning start.',
                    'Click "Save Intake Record" to generate the unique Lab Reference ID.',
                    'Attach the generated barcode / lab ID tag to the sample container.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Sample Intake (DTR)', 'url' => site_url('dtr?tab=intake')],
                    ['type' => 'open', 'label' => 'View Material Receiving Records', 'url' => site_url('dtr?tab=material-receive')],
                    ['type' => 'open', 'label' => 'Open DTR Dashboard', 'url' => site_url('dtr')],
                ],
                'page_match' => 'dtr',
            ],

            // 8. RETEST MANAGEMENT FOR FAILED LOTS
            [
                'title' => 'How to Manage Retests & Quarantine Protocols for Failed Production Lots',
                'keywords' => 'retest failed lot, create retest dtr, retest list, retest management, quarantine failed production, retesting protocol, dtr retest list, failed test retest, capa retest',
                'body' => "### Retest Management for Failed Laboratory Tests\n\n"
                        . "When a physical or bonding test fails to meet quality standards, NEXUS-L enforces an automated quarantine and retest protocol.\n\n"
                        . "#### Standard Retest Protocol:\n"
                        . "1. **Automatic Flagging:** Failed tests appear on the **DTR Retest List** (`dtr/retest-list`) highlighted in red.\n"
                        . "2. **Production Lot Quarantine:** Technicians must flag the production lot number in the system to quarantine the suspect batch on the factory floor.\n"
                        . "3. **Pull Additional Specimens:** Standard protocol requires pulling **3 additional pairs / specimens** from the quarantined production lot (beginning, middle, and end of production shift).\n"
                        . "4. **Create Retest Record:** Click **Create Retest** linked to the original failed Lab Ref.\n"
                        . "5. **Log Corrective Actions (CAPA):** Document root-cause remediation (e.g. primer temperature increased, autoclave vulcanization dwell extended).\n"
                        . "6. **Retest Evaluation:** All 3 retest specimens must achieve **100% PASS** to release the quarantined lot.",
                'steps' => [
                    'Open the DTR Retest List (/dtr/retest-list).',
                    'Review the failed parameter and original test readings.',
                    'Pull 3 additional specimens from the suspect production lot.',
                    'Click "Create Retest" and log the CAPA root-cause notes.',
                    'Execute testing and record retest results in the linked DTR report.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open DTR Retest List', 'url' => site_url('dtr?tab=retests')],
                    ['type' => 'open', 'label' => 'Open Test Results Summary (Failed)', 'url' => site_url('test-summary?status=fail')],
                    ['type' => 'open', 'label' => 'View Failure Remediation Guide', 'url' => site_url('dtr')],
                ],
                'page_match' => 'dtr',
            ],

            // 9. SHOE LOOKUP & STYLE SEARCH
            [
                'title' => 'How to Use Shoe Lookup, Style Search & Purchase Order Production Tracking',
                'keywords' => 'shoe lookup dtr, style search, search shoe test history, look up style po, shoe search dtr, historical shoe test records, find shoe bonding history, search footwear style',
                'body' => "### Shoe Lookup & Footwear Test History Search\n\n"
                        . "The **Shoe Lookup** tool allows technicians, factory managers, and auditors to trace the full historical testing log of any footwear model.\n\n"
                        . "#### How to Look Up a Shoe Model:\n"
                        . "1. **Open Shoe Lookup:** Go to `/dtr` and click the **Shoe Lookup** tab (`dtr/shoe-lookup`).\n"
                        . "2. **Search Criteria:** Enter:\n"
                        . "   - **Style Name:** e.g. *Old Skool, Sk8-Hi, Authentic, Era, Slip-On, Half Cab*.\n"
                        . "   - **Style / PO Number:** e.g. *VN000D3HY28* or factory purchase order.\n"
                        . "   - **Factory Mold Code:** Outsole mold identification number.\n"
                        . "3. **View Performance History:** The system retrieves:\n"
                        . "   - Historical bonding averages across Toe, Ball, Waist, and Heel.\n"
                        . "   - Failure frequency by component.\n"
                        . "   - Linked material test certificates for upper leather, foxing tape, and canvas.\n"
                        . "4. **One-Click Pre-Fill:** Click **Use for New Test** to pre-fill standard parameters into a new DTR record.",
                'steps' => [
                    'Navigate to DTR and select the "Shoe Lookup" tab.',
                    'Search by style name, PO number, or factory mold code.',
                    'Review historical bonding and physical property trends.',
                    'Click "Use for New Test" to clone metadata into a new test record.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Shoe Lookup Tool', 'url' => site_url('dtr?tab=shoe-lookup')],
                    ['type' => 'open', 'label' => 'Search Shoe Test Database', 'url' => site_url('dtr/shoe-search')],
                    ['type' => 'open', 'label' => 'Open DTR Dashboard', 'url' => site_url('dtr')],
                ],
                'page_match' => 'dtr',
            ],

            // 10. UPLOADING MACHINE EXCEL FILES (U60 & UTM)
            [
                'title' => 'How to Upload & Import Raw Machine Data Files (U60, UTM, Instron)',
                'keywords' => 'upload machine file, import u60 excel, load machine dtr, raw tensile data import, utm excel outdata, upload machine test data, drag and drop machine file, parse machine spreadsheet',
                'body' => "### Uploading & Importing Raw Machine Test Files\n\n"
                        . "NEXUS-L can parse raw output spreadsheets directly from Gotech U60 universal tensile machines, Instron testers, and digital tensile software without manual typing.\n\n"
                        . "#### How to Upload Machine Files:\n"
                        . "1. **Open Material Testing in DTR:** Go to `/dtr` and open the material test form.\n"
                        . "2. **Locate Import Box:** Drag and drop the `.xlsx` or `.csv` machine file into the **Upload Machine File** dropzone (`dtr/load-machine`).\n"
                        . "3. **Automated Column Parsing:** The parser maps standard columns:\n"
                        . "   - `Peak Load / Max Force` -> Mapped to Max Load (N / kgf)\n"
                        . "   - `Displacement / Elongation` -> Mapped to Gauge Elongation (%)\n"
                        . "   - `Width & Thickness` -> Mapped to Specimen Dimensions (mm)\n"
                        . "4. **Formula Calculation:** Tensile strength ($\sigma$) and % elongation at break are calculated automatically.\n"
                        . "5. **Troubleshooting:** If the upload fails, ensure the Excel sheet is closed in Microsoft Excel before dragging into the browser.",
                'steps' => [
                    'Navigate to DTR -> Material Test entry form.',
                    'Drag and drop the U60/UTM Excel spreadsheet into the upload zone.',
                    'Verify mapped values for peak load, thickness, and elongation.',
                    'Confirm calculated strength and click Save Record.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Material Test Form', 'url' => site_url('dtr?tab=material')],
                    ['type' => 'open', 'label' => 'Upload Machine File (DTR)', 'url' => site_url('dtr/load-machine')],
                    ['type' => 'open', 'label' => 'View DTR Records', 'url' => site_url('dtr')],
                ],
                'page_match' => 'dtr',
            ],

            // 11. MANAGER APPROVALS & SYNOLOGY NOTIFICATIONS
            [
                'title' => 'How to Send Reports & Synology Approval Notifications',
                'keywords' => 'send report to manager, synology chat notification, request report approval, notify approver, approval link synology, submit report for sign off, dtr approval request, approval feed',
                'body' => "### Submitting Reports for Manager Approval via Synology Chat\n\n"
                        . "Finished laboratory reports require sign-off by a registered Laboratory Supervisor or QC Manager before official release.\n\n"
                        . "#### How to Dispatch Approvals:\n"
                        . "1. **Complete Test Entry:** Ensure all test readings and specimen photos are saved in DTR.\n"
                        . "2. **Click Submit for Approval:** Click the **Submit for Approval** button (`dtr/approval/request`).\n"
                        . "3. **Select Signatory:** Pick the authorized technical signatory from the dropdown.\n"
                        . "4. **Automated Synology Alert:** NEXUS-L dispatches an instant webhook notification to the manager's **Synology Chat** channel with:\n"
                        . "   - Lab Reference Number & Shoe Style\n"
                        . "   - Pass / Fail Result Overview\n"
                        . "   - Secure, one-click mobile-ready approval preview link.\n"
                        . "5. **Resending Notifications:** If the manager has not signed within 24 hours, click **Resend Notification** on the Approval Feed.",
                'steps' => [
                    'Ensure all test parameters and readings are completed in DTR.',
                    'Click "Submit for Approval" on the test record card.',
                    'Select the designated Lab Manager signatory.',
                    'Verify that the Synology Chat notification was transmitted.',
                    'Track approval status on the DTR Approval Feed.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'View Approval Feed', 'url' => site_url('dtr/approval/feed')],
                    ['type' => 'open', 'label' => 'Check Approval Status', 'url' => site_url('dtr/approval/status')],
                    ['type' => 'open', 'label' => 'Manage Signatories', 'url' => site_url('designer/signatories')],
                ],
                'page_match' => 'dtr',
            ],

            // 12. QR CODE VERIFICATION & DIGITAL SIGNATURES
            [
                'title' => 'How to Verify Reports with QR Codes & Sign Off Digitally',
                'keywords' => 'qr code report verification, verify test certificate qr, digital signature report, approve report mobile, sign off report qr, designer signatories, approval preview, decide report',
                'body' => "### QR Code Verification & Digital Signatures\n\n"
                        . "Every official PDF test certificate generated by NEXUS-L features a cryptographically secured dynamic QR code.\n\n"
                        . "#### How to Verify & Sign Reports:\n"
                        . "1. **Scan QR Code:** Scan the QR code printed on the bottom right of the PDF report using any smartphone camera or tablet.\n"
                        . "2. **Live Verification Page:** Opens `/approve/(:token)` displaying:\n"
                        . "   - Report Authenticity Seal (Verified Authentic in NEXUS-L LIS Database)\n"
                        . "   - Complete raw test data, technician name, and timestamps\n"
                        . "   - High-resolution specimen photo attachments.\n"
                        . "3. **Manager Decision:** Authorized logged-in managers see **Approve** and **Reject** buttons:\n"
                        . "   - **Approve:** Applies digital signature stamp and technical certificate ID.\n"
                        . "   - **Reject:** Prompts for mandatory rejection reason and routes report back to technician for correction.\n"
                        . "4. **Security Timeout:** Unsigned approval links expire after 72 hours for security.",
                'steps' => [
                    'Scan the QR code on the PDF certificate with a smartphone camera.',
                    'Review the live verification page confirming database authenticity.',
                    'If authorized, click "Approve" to stamp with your digital signature.',
                    'If data is incorrect, click "Reject" and input rejection feedback.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Manage Technical Signatories', 'url' => site_url('designer/signatories')],
                    ['type' => 'open', 'label' => 'Verify Report Token', 'url' => site_url('dtr/approval/verify')],
                    ['type' => 'open', 'label' => 'Open DTR Approval Feed', 'url' => site_url('dtr/approval/feed')],
                ],
                'page_match' => 'dtr',
            ],

            // 13. INTERLINK 2.0 CLOUD AUTO-FILL & SUBMISSION
            [
                'title' => 'How to Auto-Fill & Submit Reports to Intertek InterLink 2.0 Cloud',
                'keywords' => 'interlink sync, submit to interlink, interlink auto fill, intertek cloud sync, interlink center status, interlink live grid, interlink service submit, interlink report automation',
                'body' => "### InterLink 2.0 Cloud Automation & Live Grid Synchronization\n\n"
                        . "NEXUS-L integrates seamlessly with Intertek's cloud reporting system to automate test entry and synchronize approved certificates.\n\n"
                        . "#### How to Use InterLink Center:\n"
                        . "1. **Navigate to InterLink:** Go to `/interlink` in the sidebar navigation.\n"
                        . "2. **Select Approved Report:** Choose a report that has received full Manager Sign-Off.\n"
                        . "3. **Fetch Live Form / Grid:** Click **Fetch Live Page** (`interlink/fetch-live-page`) to connect to Intertek's web form.\n"
                        . "4. **Automated Grid Fill:** Click **Auto-Fill Grid** (`interlink/center/fill`). NEXUS-L maps local DTR readings into the InterLink input table.\n"
                        . "5. **Submit to InterLink:** Click **Submit to InterLink** (`interlink/submit`).\n"
                        . "6. **Status Monitoring:** The background daemon queues the payload and confirms receipt via the Service Feed (`interlink/service/feed`).",
                'steps' => [
                    'Open the InterLink Center (/interlink).',
                    'Select an approved test report from the pending sync list.',
                    'Click "Fetch Live Page" to establish connection with Intertek portal.',
                    'Click "Auto-Fill Grid" to populate all test parameter fields.',
                    'Review mapped data and click "Submit to InterLink".'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open InterLink Center', 'url' => site_url('interlink')],
                    ['type' => 'open', 'label' => 'Check InterLink Service Status', 'url' => site_url('interlink/service/status')],
                    ['type' => 'open', 'label' => 'View Synced Reports', 'url' => site_url('interlink/reports')],
                ],
                'page_match' => 'interlink',
            ],

            // 14. LABORATORY ANALYTICS DASHBOARD
            [
                'title' => 'How to Monitor Laboratory Analytics, Throughput & Defect Trends',
                'keywords' => 'laboratory analytics, quality analytics dashboard, defect trends chart, lab throughput metrics, pass fail rate analytics, technician productivity, analytics dashboard, qc lab statistics',
                'body' => "### Laboratory Analytics & Quality Intelligence Dashboard\n\n"
                        . "The **Analytics** module (`/analytics`) provides comprehensive quality intelligence and performance KPIs across factory testing operations.\n\n"
                        . "#### Key Analytics Metrics:\n"
                        . "1. **First-Time Pass Rate (FTPR):** Percentage of tests that pass on initial submission vs. requiring retesting.\n"
                        . "2. **Defect Pareto Breakdown:** Identifies the top failure modes (e.g. 42% Toe Sole Adhesion, 28% Ross Flex Cut Growth, 15% DIN Abrasion Wear).\n"
                        . "3. **Testing Throughput:** Daily, weekly, and monthly volume of material and whole shoe tests executed.\n"
                        . "4. **Technician Output:** Number of completed test records per laboratory technician.\n"
                        . "5. **Supplier / Compound Quality Heatmap:** Compares failure rates across different rubber and EVA compound suppliers.",
                'steps' => [
                    'Navigate to Analytics (/analytics) from the sidebar navigation.',
                    'Select the desired date range (This Month, Quarter, Year-to-Date).',
                    'Review First-Time Pass Rate and defect distribution charts.',
                    'Identify recurring failure modes and export chart summaries.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Analytics Dashboard', 'url' => site_url('analytics')],
                    ['type' => 'open', 'label' => 'Open Main Dashboard', 'url' => site_url('dashboard')],
                    ['type' => 'open', 'label' => 'View Test Results Summary', 'url' => site_url('test-summary')],
                ],
                'page_match' => 'analytics',
            ],

            // 15. REPORT DESIGNER & VECTOR CUSTOMIZATION
            [
                'title' => 'How to Customize PDF Report Layouts & Element Positioning in Designer',
                'keywords' => 'report designer, customize pdf layout, designer grid mapping, edit report layout, move logo on report, designer index, drag and drop designer, designer save layout',
                'body' => "### Report Designer: Customizing PDF Vector Layouts\n\n"
                        . "Admins can customize laboratory report formats, move visual elements, and configure field mappings using the visual **Report Designer** (`/designer`).\n\n"
                        . "#### Designer Workflow:\n"
                        . "1. **Access Designer:** Log in as Administrator and click **Designer** in the sidebar (`/designer`).\n"
                        . "2. **Select Report Template:** Choose Material Test, Whole Shoe Flex, or Sole Bonding certificate.\n"
                        . "3. **Drag & Drop Elements:** Click and drag elements on the canvas:\n"
                        . "   - Header block & Company / Brand Logos\n"
                        . "   - Specimen information & supplier details grid\n"
                        . "   - Test parameter & readings table\n"
                        . "   - Specimen photo attachment boxes\n"
                        . "   - Signature blocks & QR verification badge.\n"
                        . "4. **Grid Mapping:** Go to **Designer Maps** (`designer/maps`) to map database columns to PDF cells.\n"
                        . "5. **Test Layout:** Click **Test Map** to verify rendering, then click **Save Layout**.",
                'steps' => [
                    'Log in as an Administrator and navigate to /designer.',
                    'Select the report template to customize.',
                    'Drag and reposition headers, tables, and photo blocks.',
                    'Configure field coordinates or reset to factory defaults.',
                    'Click "Save Layout" to publish changes across all generated PDFs.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Report Designer', 'url' => site_url('designer')],
                    ['type' => 'open', 'label' => 'Edit Designer Maps', 'url' => site_url('designer/maps')],
                    ['type' => 'open', 'label' => 'Manage Signatories', 'url' => site_url('designer/signatories')],
                ],
                'page_match' => 'designer',
            ],

            // 16. TEAM INBOX & REAL-TIME COLLABORATION
            [
                'title' => 'How to Use Team Inbox for Real-Time Chat, File Sharing & Group Channels',
                'keywords' => 'inbox chat, team messaging, send file in chat, create group chat, inbox thread, send message inbox, share test photo chat, disappearing messages inbox',
                'body' => "### Team Inbox: Laboratory Communication & Collaboration\n\n"
                        . "The **Inbox** (`/inbox`) provides instant real-time messaging, file sharing, and group discussion channels for laboratory staff, supervisors, and QC inspectors.\n\n"
                        . "#### How to Use the Inbox:\n"
                        . "1. **Open Inbox:** Click **Inbox** in the top navigation bar or sidebar.\n"
                        . "2. **Start Conversation:** Select a team member from the user list or click **+ New Group** to create a department channel (e.g. *Vulcanized QC Team*).\n"
                        . "3. **Send Messages & Files:** Type messages in real-time. Use the paperclip icon to attach raw machine Excel spreadsheets, failure photos, or PDF drafts.\n"
                        . "4. **Message Features:** Hover over messages to add emoji reactions, pin important announcements to the channel header, or forward to other colleagues.\n"
                        . "5. **Disappearing Messages:** Enable disappearing messages for sensitive technical audit files.",
                'steps' => [
                    'Open the Team Inbox (/inbox).',
                    'Select a colleague or group channel from the conversation list.',
                    'Type your message and attach testing spreadsheets or photos.',
                    'Pin critical announcements to keep them visible at the top.',
                    'Receive desktop notifications for incoming messages.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Team Inbox', 'url' => site_url('inbox')],
                    ['type' => 'open', 'label' => 'Create Group Channel', 'url' => site_url('inbox?action=new-group')],
                ],
                'page_match' => 'inbox',
            ],

            // 17. SYSTEM ADMINISTRATION & DATABASE SWITCHING
            [
                'title' => 'How to Test Connections, Switch Databases & Sync Git in Settings',
                'keywords' => 'system settings, switch database, database transfer, test database connection, settings db switch, git sync settings, test service settings, admin settings',
                'body' => "### System Administration: Database Switching & Maintenance Tools\n\n"
                        . "Administrators can manage database connections, transfer records, verify microservices, and sync Git repositories via **Settings** (`/settings`).\n\n"
                        . "#### Administrative Capabilities:\n"
                        . "1. **Database Test & Switch:** Test connection to alternate databases (`settings/db/test`) and switch active connection dynamically without downtime (`settings/db/switch`).\n"
                        . "2. **Database Data Transfer:** Transfer records from staging/offline databases to production server (`settings/transfer/start`).\n"
                        . "3. **InterLink Microservice Ping:** Test connectivity and API authentication keys with the InterLink bridge service (`settings/service/test`).\n"
                        . "4. **Git Repository Sync:** Pull the latest updates directly from the master Git repository (`settings/git/sync`).\n"
                        . "5. **AI Knowledge Seeding:** Trigger immediate re-seeding and synchronization of AI playbooks (`settings/ai/seed`).",
                'steps' => [
                    'Log in as Administrator and open /settings.',
                    'Use "Test Connection" to verify alternate database credentials.',
                    'Switch active database or start background data transfer.',
                    'Test InterLink microservice connectivity.',
                    'Click "Git Sync" to pull official system updates.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open System Settings', 'url' => site_url('settings')],
                    ['type' => 'open', 'label' => 'Brain Console', 'url' => site_url('brain')],
                ],
                'page_match' => 'settings',
            ],

            // 18. BRAIN AI CONSOLE & LIVE SUPERVISOR TAKEOVER
            [
                'title' => 'How to Manage AI Brain Knowledge, Supervise Chats & Review Questions',
                'keywords' => 'brain console, ai knowledge base, supervise ai bot, live chat takeover, unanswered questions, brain threads, train ai brain, brain flush cache, brain overview',
                'body' => "### Brain AI Console: Managing Bot Knowledge & Live Takeover\n\n"
                        . "Administrators and QC Supervisors can monitor LEXIS AI bot interactions, review questions the bot couldn't answer, and take over live chat sessions in the **Brain Console** (`/brain`).\n\n"
                        . "#### Brain Console Tools:\n"
                        . "1. **Overview & Health:** Displays total knowledge playbooks, query confidence distribution, and AI service status (`brain/overview`, `brain/health`).\n"
                        . "2. **Live Chat Takeover:** Monitor real-time user questions. If a technician needs human assistance, click **Take Over Chat** (`brain/reply`) to answer directly as supervisor.\n"
                        . "3. **Unanswered Question Review:** Review queries with confidence $< 70\%$ (`brain/unanswered`) and draft approved answers in one click.\n"
                        . "4. **Knowledge Editor:** Create, edit, or pin playbooks in `tbl_bot_knowledge` (`brain/knowledge`).\n"
                        . "5. **AI Training & Cache Flush:** Trigger ML term weight updates (`brain/ai/train`) and flush cache (`brain/flush`).",
                'steps' => [
                    'Open the Brain Console (/brain) from the admin navigation.',
                    'Review recent user conversations and bot confidence scores.',
                    'Take over any live chat requiring supervisor intervention.',
                    'Open "Unanswered Questions" and convert technician queries into playbooks.',
                    'Trigger "Train AI" to synchronize updated neural term weights.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Brain Console', 'url' => site_url('brain')],
                    ['type' => 'open', 'label' => 'View Unanswered Questions', 'url' => site_url('brain/unanswered')],
                    ['type' => 'open', 'label' => 'Knowledge Base Manager', 'url' => site_url('brain/knowledge')],
                ],
                'page_match' => 'brain',
            ],
        
            // 19. LEXUS MULTI-FORMAT REPORT GENERATION (PPTX, XLSX, PDF)
            [
                'title' => 'How to Generate PowerPoint, Excel & PDF Reports with LEXUS AI',
                'keywords' => 'generate powerpoint report, export excel report, generate ppt report, export pdf report, monthly quality audit report, powerpoint report of pass fail, span date audit report, lexus report engine, download pptx report, download xlsx spreadsheet, generate powerpoint report for dtp20261027faa7, give me excel report of dtp20261027faa7, export pdf report for dtp20261027faa7',
                'body' => "### LEXUS AI Multi-Format Reporting Engine\n\n"
                        . "LEXUS AI generates executive Microsoft PowerPoint presentations (.pptx), granular Excel workbooks (.xlsx), and high-resolution vector PDFs (.pdf) natively within NEXUS-L without any third-party cloud services.\n\n"
                        . "#### Supported Reporting Scopes:\n"
                        . "1. **Single Lab Reference Process Report:** Complete peel strength curves, pass/fail status, and production parameters for a specific reference (e.g. `DTP20261027FAA7`).\n"
                        . "2. **Monthly & Span Date Quality Audits:** Summary dashboards with total Pass, Tolerable, and Failed counts, percentage pass rates, and stage breakdowns (First Article FA, Pre-Production PP, Mass Production PR).\n\n"
                        . "#### Example Commands to Prompt LEXUS:\n"
                        . "- *\"Generate PowerPoint report for DTP20261027FAA7\"*\n"
                        . "- *\"Give me excel report of DTP20261027FAA7\"*\n"
                        . "- *\"Export monthly powerpoint report of pass fail and tolerable\"*\n"
                        . "- *\"Export monthly audit summary to excel\"*",
                'steps' => [
                    'Type your request to LEXUS specifying the Lab Reference or date span.',
                    'Specify desired format: PowerPoint (.pptx), Excel (.xlsx), or PDF (.pdf).',
                    'LEXUS compiles the test data and returns a direct download card with live action buttons.',
                    'Click the download button to open the formatted slide deck or spreadsheet.'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open LEXUS AI Assistant', 'url' => site_url('brain')],
                    ['type' => 'open', 'label' => 'View Test Results Summary', 'url' => site_url('test-summary')],
                ],
                'page_match' => 'brain',
            ],

            // 20. LAB REFERENCE ANATOMY & STAGE DECONSTRUCTION
            [
                'title' => 'Lab Reference Anatomy: Deconstructing Facility, Date, Stage (FA/PP/PR) & Assembly Line',
                'keywords' => 'what does lab reference mean, lab reference anatomy, dtp20261027faa7, dtp lab reference breakdown, fa first article meaning, production line a7, understand lab reference code, bonding reference structure, explain lab reference, what does lab reference dtp20261027faa7 mean',
                'body' => "### Understanding NEXUS-L Standardized Lab Reference Codes\n\n"
                        . "Every footwear specimen tested in DaTian Subic Shoes Inc. is assigned a standardized reference ID containing full production traceability:\n\n"
                        . "#### Anatomy Example: `DTP20261027FAA7`\n"
                        . "- **DTP (Facility):** DaTian Subic Shoes Inc. Philippines QC Lab.\n"
                        . "- **20261027 (Test Date):** 27 October 2026 (Format: YYYYMMDD).\n"
                        . "- **FA (Production Stage):** **First Article** (Initial production tooling qualification). Other stages include `PP` (Pre-Production), `PR` (Mass Production), `CS` (Confirmation Sample), `SM` (Salesman Sample), `DV` (Development).\n"
                        . "- **A7 (Production Line):** Assembly Conveyor Line A7 in the manufacturing building.\n"
                        . "- **Suffix -L / -R (Specimen Foot):** Left foot or Right foot specimen.\n\n"
                        . "LEXUS AI automatically parses this anatomy and can generate specialized reports filtered by stage and production line.",
                'steps' => [
                    'Observe the 15-character reference code (e.g. DTP20261027FAA7).',
                    'First 3 characters identify the manufacturing facility (DTP).',
                    'Next 8 digits represent the testing calendar date (YYYYMMDD).',
                    'Next 2 characters signify the production phase (FA = First Article).',
                    'Trailing characters designate the specific assembly line (A7).'
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Open Digital Test Reports', 'url' => site_url('dtr')],
                    ['type' => 'open', 'label' => 'Open LEXUS AI Console', 'url' => site_url('brain')],
                ],
                'page_match' => 'dtr',
            ],
        ];
    }
}
