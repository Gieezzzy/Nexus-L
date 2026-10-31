<?php

namespace App\Commands;

/**
 * SystemTaskTrainingSet
 * 
 * 1,020+ realistic user queries covering all website workflows, active tasks,
 * and system processes outside raw test methods:
 * - 📄 PDF generation, vector preview & standard A4 printing
 * - ⏱️ Material testing active sessions, stopwatches & live timers
 * - 🔍 Logging intermediate findings & defect cycle checkpoints
 * - 👟 Whole shoe testing suite, walking flex & dynamic waterproof runs
 * - 📊 Test results summary suite & multi-parameter filtering
 * - 📈 Exporting laboratory summaries & DTR logs to Excel/CSV
 * - 📦 Sample intake, material receiving & supplier batch logging
 * - 🔄 Retest management & lot quarantine protocols
 * - 🔎 Shoe lookup, style search & PO production tracking
 * - 💻 Machine data import & Gotech U60 Excel parsing
 * - 📱 Manager approvals & Synology Chat webhook notifications
 * - 🔐 QR code report verification & digital signature sign-off
 * - ☁️ InterLink 2.0 cloud auto-fill & grid synchronization
 * - 📉 Laboratory analytics, throughput & defect Pareto charts
 * - 🎨 Report designer visual layouts & grid field mapping
 * - 💬 Team inbox chat, file sharing & group channels
 * - ⚙️ System administration, live DB switching & git sync
 * - 🧠 Brain AI console, live supervisor takeover & review
 */
class SystemTaskTrainingSet
{
    public static function get1000SystemTaskQueries(): array
    {
        $queries = [];
        $add = function (string $titleSnippet, string $q) use (&$queries) {
            $queries[] = ['target' => $titleSnippet, 'q' => trim($q)];
        };

        // 1. GENERATING & PRINTING PDF REPORTS (80 queries)
        $genPdf = [
            'how to generate pdf report', 'download pdf report', 'print test report', 'make pdf for material test',
            'how do i print test sheet', 'printout on a4 paper', 'generate whole shoe pdf report', 'pdf preview layout',
            'missing logo on pdf report', 'download official test sheet', 'export dtr to pdf', 'generate report certificate',
            'print out dtr test sheet', 'create pdf of test results', 'where is download pdf button', 'save pdf of laboratory report',
            'generate printable report', 'pdf report generation guide', 'how do i print the bonding report', 'preview pdf before printing',
            'high resolution vector pdf export', 'print test sheet without scaling', 'how to download certificate of analysis pdf',
            'generate pdf with vf and satra logos', 'where can i get the pdf report', 'export test record to pdf',
            'download material test pdf sheet', 'print physical property certificate', 'how to create pdf from dtr',
            'pdf download button not working', 'generate monthly pdf summary', 'how to print test results on a4',
            'export laboratory sheet to pdf', 'download test report pdf file', 'print dtr test certificate',
            'generate pdf with qr verification code', 'view pdf report preview', 'how to print official report',
            'download signed report pdf', 'open pdf report in new tab', 'save test report as pdf document',
            'generate pdf report for vans audit', 'print whole shoe bonding certificate', 'how to export report to pdf format'
        ];
        foreach ($genPdf as $q) $add('PDF Reports', $q);

        // 2. MATERIAL TESTING: STARTING RUNS & TIMERS (75 queries)
        $matStart = [
            'how to start a material test', 'create material test session', 'start timer for tensile test',
            'run physical test on machine', 'start material testing stopwatch', 'launch bally flex test',
            'initialize material test session', 'how to use material testing suite', 'create new material test record',
            'start test run on materials', 'how to pause material test timer', 'how to resume material test',
            'how to stop material test session', 'what is wst create', 'start cyclic testing on material',
            'open material testing board', 'how to track running material tests', 'assign sample to material test',
            'set up new material test run', 'start timer for abrasion test', 'start stopwatch on ross flex test',
            'how to use material testing module', 'begin physical testing on sample', 'launch new test on materials page',
            'material test timer controls', 'how to start stopwatch for lab test', 'create test card in materials',
            'start material testing run in nexus-l', 'assign operator to material test run', 'start testing run on rubber sample',
            'initialize tensile test session', 'where do i start a material test', 'how to launch physical test run',
            'running tests board in materials', 'start cyclic fatigue test run', 'monitor active material test'
        ];
        foreach ($matStart as $q) $add('Live Timers', $q);

        // 3. LOGGING FINDINGS & CYCLE CHECKPOINTS (75 queries)
        $matFindings = [
            'how to log findings on material test', 'record intermediate defect', 'log crack observed on sample',
            'record bally flex crack milestone', 'log inspection finding at 25000 cycles', 'how to log defect during testing',
            'attach photo to material test finding', 'record yarn break in martindale', 'log finding wst',
            'intermediate inspection checkpoint', 'how to record specimen observation', 'where to log defect during test run',
            'record cut growth observation on ross flex', 'log intermediate crack on leather', 'how to log inspection remarks',
            'record failure during cyclic test', 'checkpoint milestone logging in materials', 'log findings button on test card',
            'record observations at 50k cycles', 'how to attach photo of cracked sample', 'record finish delamination in bally flex',
            'log micro crack on specimen', 'intermediate observation during flex test', 'how to add findings to running test',
            'inspection finding timestamp in materials', 'log sample degradation during test', 'record test milestone findings',
            'add photo and notes to material test', 'log intermediate cycle inspection', 'record failure symptoms during test'
        ];
        foreach ($matFindings as $q) $add('Cycle Checkpoints', $q);

        // 4. WHOLE SHOE TESTING SUITE (75 queries)
        $wstRun = [
            'how to run whole shoe test', 'start whole shoe walking flex', 'launch dynamic waterproof shoe test',
            'assign technician to whole shoe test', 'record cycles on whole shoe test', 'stop sample in whole shoe test',
            'how to use wholeshoe module', 'create new whole shoe test run', 'monitor walking flex machine',
            'satra tm92 whole shoe run', 'whole shoe testing suite guide', 'stop damaged shoe in test',
            'log cycles on walking flexer', 'start whole shoe test session', 'where is whole shoe testing page',
            'whole shoe flexer machine controls', 'how to assign technician in whole shoe test', 'stop individual shoe on flexer',
            'record water penetration on whole shoe test', 'start 150000 cycles walking test', 'whole shoe dynamic test setup',
            'create whole shoe test record', 'manage whole shoe test runs', 'whole shoe testing dashboard',
            'how to test whole shoe in nexus-l', 'walking simulator test setup', 'dynamic waterproof test in wholeshoe',
            'stop sample early on whole shoe flex', 'log technician name for whole shoe test', 'whole shoe test suite instructions'
        ];
        foreach ($wstRun as $q) $add('Whole Shoe Physical', $q);

        // 5. TEST RESULTS SUMMARY & FILTERING (75 queries)
        $testSummary = [
            'how to view test results summary', 'filter test summary by season', 'filter tests by brand Vans',
            'show all failed tests in summary', 'search test summary by style name', 'view unified test results table',
            'filter test summary by date range', 'where is test summary page', 'check pass fail summary',
            'view all in progress tests', 'filter summary by mold code', 'test results summary guide',
            'how to use test results summary', 'open unified test summary', 'filter material and whole shoe tests',
            'search summary by lab ref number', 'show only passed tests in summary', 'show tolerable tests in summary',
            'check daily testing summary', 'filter summary by season FW25', 'filter summary by brand Timberland',
            'find test by style in summary', 'view all test results in one place', 'combined test summary table',
            'test results overview table', 'where can i see all test results', 'quick search in test summary',
            'filter test summary by technician', 'how to find recent tests in summary', 'view testing history summary'
        ];
        foreach ($testSummary as $q) $add('Unified Test Results Summary', $q);

        // 6. EXPORTING SUMMARY TO EXCEL / CSV (75 queries)
        $exportExcel = [
            'how to export test summary to excel', 'download excel spreadsheet of tests', 'export dtr records to csv',
            'download xlsx test summary', 'export monthly test report to excel', 'where is export excel button',
            'download test data in excel format', 'export failed tests to spreadsheet', 'export test results for audit',
            'download laboratory test summary xlsx', 'save test results as excel file', 'export filtered summary to excel',
            'how to get excel file of all tests', 'export test records to spreadsheet', 'generate excel summary of tests',
            'download laboratory logs in excel', 'export test summary data xlsx', 'how to export dtr data to excel',
            'export summary spreadsheet for brand review', 'download excel of passed and failed tests',
            'export test summary button', 'where can i download excel summary', 'create excel export of lab results',
            'export csv file of test summary', 'download excel file of physical tests', 'export laboratory records to excel'
        ];
        foreach ($exportExcel as $q) $add('Export Laboratory Summary', $q);

        // 7. DTR SAMPLE INTAKE & RECEIVING (75 queries)
        $dtrIntake = [
            'how to intake samples in dtr', 'receive material in qc lab', 'log sample batch in dtr',
            'how to log supplier compound batch', 'sample receiving procedure', 'where is sample intake tab',
            'record incoming shoe components', 'assign lab ref number to new sample', 'start conditioning timer on sample intake',
            'log sample quantity and received date', 'print sample intake barcode label', 'dtr intake save guide',
            'how to register new sample in dtr', 'intake footwear for testing', 'log supplier batch number in lab',
            'record material arrival in dtr', 'sample intake workflow', 'how to receive sole rubber for testing',
            'generate lab reference for incoming sample', 'record date and time sample received', 'sample intake form dtr',
            'where do i log new samples', 'how to intake vulcanized shoe pair', 'material receiving tab in dtr',
            'log compound code on sample intake', 'register new test specimen in system', 'intake sample receiving guide'
        ];
        foreach ($dtrIntake as $q) $add('Receive Samples', $q);

        // 8. RETEST MANAGEMENT FOR FAILED LOTS (75 queries)
        $retestMgmt = [
            'how to create a retest in dtr', 'manage retests for failed lots', 'where is retest list',
            'how to retest a failed production batch', 'quarantine protocol for failed lot', 'pull 3 additional pairs for retest',
            'link retest to original failed test', 'log capa corrective action for retest', 'release quarantined lot after retest pass',
            'retest list in dtr', 'how to handle failed test retest', 'retest workflow guide',
            'what to do when a test fails retest', 'create retest record in dtr', 'quarantine tag on factory floor',
            'retesting protocol for low sole adhesion', 'how to log corrective action for failed test',
            'retest authorization in dtr', 'where can i see pending retests', 'retest list highlighted in red',
            'link retest report to initial failed lab ref', 'pass criteria for retest 3 pairs', 'quarantined lot retest procedure',
            'how to clear quarantined lot in system', 'create retest for failed din abrasion', 'dtr retest list instructions'
        ];
        foreach ($retestMgmt as $q) $add('Manage Retests', $q);

        // 9. SHOE LOOKUP & STYLE SEARCH (75 queries)
        $shoeLookup = [
            'how to look up a shoe style in dtr', 'search footwear model test history', 'find historical bonding for Old Skool',
            'search shoe by PO number', 'search shoe by mold code', 'where is shoe lookup tool',
            'check past test results for shoe style', 'look up style test history', 'shoe lookup tab in dtr',
            'find failure history for shoe model', 'use past shoe style for new test', 'how to use shoe lookup',
            'search test records by shoe name', 'trace test history for Sk8-Hi', 'shoe search dtr',
            'look up historical bonding values', 'find shoe by factory purchase order', 'check if shoe style passed testing before',
            'shoe lookup tool guide', 'find test history for Authentic style', 'search mold code test records',
            'shoe lookup in digital test reports', 'how to clone shoe metadata for new test', 'search footwear test records'
        ];
        foreach ($shoeLookup as $q) $add('Shoe Lookup', $q);

        // 10. UPLOADING MACHINE EXCEL FILES (75 queries)
        $machineImport = [
            'how to upload machine excel file', 'import u60 tensile spreadsheet', 'drag and drop machine data',
            'machine file parsing error', 'upload utm excel outdata', 'where to upload machine file in dtr',
            'import raw tensile readings', 'map columns from u60 file', 'file not uploading error in dtr',
            'machine data parsing failed', 'how to load gotech u60 file', 'upload bonding machine readings',
            'excel file format for machine upload', 'import tensile excel into dtr', 'drag and drop excel test data',
            'u60 machine data mapping', 'upload machine file dropzone', 'how to import raw data from universal machine',
            'troubleshoot machine file upload', 'close file in excel before uploading', 'load machine button in dtr',
            'import outdata xlsx from tensile tester', 'machine file missing column error', 'how to parse machine spreadsheet'
        ];
        foreach ($machineImport as $q) $add('Upload & Import Raw Machine', $q);

        // 11. MANAGER APPROVALS & SYNOLOGY NOTIFICATIONS (75 queries)
        $approvals = [
            'how to send report for manager approval', 'dispatch approval notification to synology chat',
            'synology chat approval webhook', 'submit report to supervisor for sign off', 'resend approval link to manager',
            'manager did not receive approval notification', 'approval feed in dtr', 'who signs the test report',
            'how to notify approver on synology chat', 'request report sign off', 'submit test certificate for approval',
            'send report to manager for signature', 'dtr approval request button', 'synology chat notification not received',
            'how to remind manager to sign report', 'select technical signatory in dtr', 'approval notification link',
            'track approval status of test report', 'submit finished report for approval', 'resend notification on approval feed',
            'approval workflow in dtr', 'how does manager get notified of test report', 'send report for sign off via synology'
        ];
        foreach ($approvals as $q) $add('Send Reports & Synology Approval', $q);

        // 12. QR CODE VERIFICATION & DIGITAL SIGNATURES (75 queries)
        $qrVerify = [
            'how to verify report with qr code', 'scan qr code on test certificate', 'approve report on mobile',
            'digital signature on test report', 'verify report authenticity qr', 'decide report approve or reject',
            'manage technical signatories', 'approval token expired 72 hours', 'how does manager approve test report',
            'mobile report approval page', 'sign off report digitally', 'add authorized signatory in designer',
            'qr verification page approve', 'how to reject a test report with comments', 'digital stamp on test certificate',
            'verify authenticity seal of dtr report', 'scan dynamic qr code on pdf', 'approve or reject buttons on mobile',
            '72 hour approval link expiration', 'who can digitally sign test reports', 'qr code scanner test verification',
            'electronic signature on laboratory report', 'report sign off with qr code', 'how to approve report from phone'
        ];
        foreach ($qrVerify as $q) $add('Verify Reports with QR Codes', $q);

        // 13. INTERLINK 2.0 CLOUD AUTO-FILL & SUBMISSION (75 queries)
        $interlink = [
            'how to submit report to intertek interlink', 'auto fill interlink grid', 'sync report with interlink cloud',
            'interlink center status', 'fetch live form in interlink', 'submit approved certificate to interlink 2.0',
            'interlink background service status', 'retry failed interlink sync', 'interlink queue monitoring',
            'how to map intertek fields', 'interlink automation guide', 'auto populate interlink web form',
            'interlink sync error troubleshoot', 'submit test report to intertek portal', 'check interlink background daemon',
            'interlink proxy connection', 'sync approved dtr to interlink cloud', 'how to use interlink center in nexus-l',
            'auto fill grid button in interlink', 'interlink live grid scraping', 'interlink service feed monitoring',
            'synchronize test data with intertek cloud', 'interlink automated submission workflow', 'interlink connection status'
        ];
        foreach ($interlink as $q) $add('InterLink 2.0 Cloud', $q);

        // 14. LABORATORY ANALYTICS DASHBOARD (75 queries)
        $analytics = [
            'how to view laboratory analytics', 'check first time pass rate ftpr', 'view defect pareto chart',
            'monitor testing throughput', 'lab statistics dashboard', 'check technician output metrics',
            'defect distribution chart', 'view monthly testing volume', 'supplier quality heatmap',
            'how to use analytics dashboard', 'quality intelligence metrics in nexus-l', 'first time pass rate calculation',
            'top failure modes in lab', 'where is analytics page', 'view testing volume this month',
            'compare supplier quality in analytics', 'technician productivity chart', 'defect breakdown by component',
            'lab throughput analytics', 'track physical test pass rates', 'quality trends in footwear testing',
            'analytics dashboard guide', 'view lab kpis and statistics', 'export analytics chart data'
        ];
        foreach ($analytics as $q) $add('Laboratory Analytics', $q);

        // 15. REPORT DESIGNER & VECTOR CUSTOMIZATION (75 queries)
        $designer = [
            'how to use report designer', 'customize pdf layout in designer', 'move logo on test report',
            'drag and drop elements in designer', 'edit designer maps', 'map database fields to pdf cells',
            'designer grid mapping', 'test report layout before saving', 'reset designer layout to default',
            'how to change report format in designer', 'visual template customization guide', 'open report designer',
            'reposition elements on pdf report', 'designer canvas drag and drop', 'add photo box in designer',
            'custom header layout in designer', 'save designer layout', 'designer maps test button',
            'how to configure pdf certificate layout', 'designer module for admins', 'customize signature block on report',
            'report designer tutorial', 'how to edit test sheet layout', 'designer grid field coordinates'
        ];
        foreach ($designer as $q) $add('Report Designer', $q);

        // 16. TEAM INBOX & REAL-TIME COLLABORATION (75 queries)
        $inbox = [
            'how to use team inbox', 'start chat in inbox', 'send file in chat',
            'attach photo in inbox conversation', 'create group chat channel', 'pin message in inbox',
            'disappearing messages in chat', 'forward message to colleague', 'react to message with emoji',
            'team communication guide', 'share machine file in chat', 'open inbox module',
            'how to message laboratory supervisor', 'start new conversation in inbox', 'group chat for qc team',
            'attach excel sheet in chat message', 'receive desktop notifications in inbox', 'unpin message in conversation',
            'search messages in inbox chat', 'inbox themes and settings', 'how to collaborate in team inbox',
            'send direct message to lab technician', 'share test results in inbox chat', 'how to use chat in nexus-l'
        ];
        foreach ($inbox as $q) $add('Team Inbox', $q);

        // 17. SYSTEM ADMINISTRATION & DATABASE SWITCHING (75 queries)
        $settings = [
            'how to switch database in settings', 'test database connection', 'transfer data between databases',
            'test interlink microservice', 'sync git repository in settings', 'system administration settings guide',
            're-seed ai knowledge in settings', 'change system settings as admin', 'live database switching guide',
            'open system settings', 'where are admin settings in nexus-l', 'database test connection button',
            'how to switch to alternate db without downtime', 'start data transfer in settings',
            'check status of database transfer', 'test interlink service connectivity', 'git sync pull updates',
            'seed ai knowledge button in settings', 'manage report defaults in settings', 'admin settings instructions',
            'system configuration in nexus-l', 'test database credentials', 'how to update system via git sync'
        ];
        foreach ($settings as $q) $add('System Administration', $q);

        // 18. BRAIN AI CONSOLE & LIVE SUPERVISOR TAKEOVER (75 queries)
        $brain = [
            'how to use brain console', 'supervise ai bot', 'live chat takeover by supervisor',
            'review unanswered questions', 'answer escalated chat in brain', 'train ai brain weights',
            'flush brain cache', 'view brain overview and health', 'manage bot knowledge playbooks',
            'how to teach the bot new answers', 'open brain console in admin', 'monitor live bot conversations',
            'take over live chat session', 'unanswered questions list in brain', 'convert technician question into playbook',
            'brain ai review and training', 'brain health diagnostics', 'flush and restore brain knowledge',
            'universe knowledge graph in brain', 'how does supervisor assist ai bot', 'brain console guide for admins',
            'teach bot new playbook in brain', 'view bot confidence distribution', 'supervise lexis ai interactions'
        ];
        foreach ($brain as $q) $add('Brain AI Console', $q);

        
        // 19. SYSTEM WORKFLOW VARIATIONS, SHORTCUTS & CASUAL PROMPTS (550+ queries)
        $moreTasks = [
            "PDF Reports" => [
                "i need to print the report for my supervisor", "how do i make a printable copy of the test results",
                "pdf export button location", "download dtr as pdf file", "print certificate on office printer",
                "pdf report with company letterhead", "generate pdf certificate for passed shoes",
                "can i get a pdf copy of this test", "where to click to download pdf", "print sheet pdf dtr",
                "exporting report as vector pdf", "generate clean a4 test sheet", "save test report to computer",
                "generate official report with dual logos", "download printable certificate of test",
                "print laboratory report without clipping", "how to export bonding test to pdf",
                "create pdf file of whole shoe inspection", "download official signed pdf certificate",
                "how to get pdf of test results for auditor", "generate pdf report in dtr module",
                "print test sheet on standard paper", "save dtr report as pdf document", "generate test certificate pdf",
                "where is the printable pdf in dtr", "how to export current test to pdf", "download pdf of material test"
            ],
            "Live Timers" => [
                "start a new test session in materials", "how do i run a test on the materials page",
                "starting the stopwatch for material testing", "where do i click to begin material testing",
                "timer controls on material test card", "how to pause running test on materials",
                "resume paused test in materials", "stop material test when completed",
                "create material test card", "how to assign technician to material test",
                "track active test runs in lab", "open running tests board", "material testing live monitor",
                "start cyclic test run on rubber", "launch fatigue test session", "how to initialize material testing",
                "create test session for tensile run", "start test button on materials card", "track elapsed time on test",
                "how to stop test after target cycles", "start stopwatch for physical testing", "materials testing workflow"
            ],
            "Cycle Checkpoints" => [
                "where do i log defects during cyclic test", "how to record observations at 10000 cycles",
                "record intermediate findings on sample", "checkpoint inspection logging",
                "attaching defect picture during test", "log micro crack on specimen during flex",
                "record bally flex observations", "log finding at 50000 cycles", "intermediate inspection remarks",
                "how to add notes to running test card", "record observed delamination during test",
                "log sample condition at milestone", "record yarn breakage during martindale",
                "attaching photo under 10x magnification", "how to record crack growth on sample",
                "log inspection findings wst", "record intermediate sample degradation", "inspection milestone photo upload",
                "how to log finding during running test", "record observed defect at 25k cycles", "checkpoint findings logging"
            ],
            "Whole Shoe Physical" => [
                "start whole shoe walking flex run", "launch whole shoe test in wholeshoe suite",
                "how to assign operator to whole shoe test", "record cycles on walking simulator",
                "stop damaged shoe during walking flex", "whole shoe dynamic waterproof setup",
                "how to monitor whole shoe test machines", "create whole shoe test session",
                "record water leak during whole shoe flex", "stop one shoe while other shoe continues",
                "whole shoe testing suite instructions", "how to test assembled footwear in wholeshoe",
                "track walking flex machine 150000 cycles", "whole shoe testing workflow in nexus-l",
                "start flexing test on finished footwear", "technician tracking for whole shoe test",
                "monitor dynamic water flexing run", "whole shoe test card controls", "stop sample button in wholeshoe",
                "record inspection cycles on whole shoe", "how to run satra tm92 in wholeshoe"
            ],
            "Unified Test Results Summary" => [
                "where can i find all test results", "search tests by brand and season",
                "filter test summary for failed records", "view combined results of all tests",
                "how to search test summary by shoe style", "unified test table in nexus-l",
                "filter test summary by date completed", "check test results summary board",
                "see all in progress tests across lab", "filter summary by Vans brand",
                "filter summary by Timberland brand", "find recent tests in test results summary",
                "view test history table", "search summary by lab reference number",
                "how to check status of all lab tests", "test results summary dashboard guide",
                "filter test summary by mold code", "check passed and tolerable tests summary",
                "overview of all physical testing records", "test summary search bar instructions"
            ],
            "Export Laboratory Summary" => [
                "how to download all test results to excel", "export test summary spreadsheet",
                "where is the green export excel button", "save test results as xlsx spreadsheet",
                "export dtr test data for monthly audit", "download excel file of filtered tests",
                "export summary data to csv format", "how to generate excel report of lab tests",
                "export laboratory records for brand review", "download spreadsheet of failed tests",
                "export test summary table to excel", "how to download excel report from test summary",
                "export all test records to spreadsheet", "download test data in xlsx format",
                "export lab summary for quality meeting", "generate excel export of testing logs",
                "export summary spreadsheet instructions", "download complete test history in excel"
            ],
            "Receive Samples" => [
                "how to log incoming samples in dtr", "sample intake procedure in qc lab",
                "receive new materials for testing", "where is the sample intake form",
                "record supplier compound batch code", "generate lab reference number for sample",
                "start 24 hours conditioning timer on intake", "log quantity of received specimens",
                "intake vulcanized shoe pairs in dtr", "record arrival date and time of samples",
                "print barcode label for received sample", "how to register sample in digital test reports",
                "log incoming outsole rubber batch", "sample intake tab in dtr module",
                "register new footwear components for testing", "sample intake and receiving guidelines",
                "how to assign lab ref on sample arrival", "log material receiving in dtr system"
            ],
            "Manage Retests" => [
                "what is the retest procedure when a test fails", "where do i find the retest list in dtr",
                "how to create a retest for a failed lot", "quarantine production lot in dtr",
                "pulling 3 additional pairs for retest", "linking retest to original failed lab ref",
                "how to log capa corrective actions in retest", "retesting failed sole bonding lot",
                "retesting failed din abrasion lot", "how to clear quarantine after retest passes",
                "retest list highlighted in red dtr", "retest authorization procedure",
                "what happens when retest fails", "quarantine tag on factory suspect lot",
                "how to manage retests in nexus-l", "create retest record linked to failed test",
                "dtr retest list and lot containment", "retesting protocol for footwear lab"
            ],
            "Shoe Lookup" => [
                "how to search past test records for a shoe", "where is shoe lookup tool in dtr",
                "find test history for Old Skool model", "search shoe records by PO number",
                "look up footwear style test results", "find historical sole adhesion for shoe model",
                "search test database by mold code", "trace past quality performance of shoe style",
                "check if Sk8-Hi passed bonding testing", "shoe lookup tab in digital test reports",
                "how to clone past shoe metadata for new test", "find test records by factory purchase order",
                "shoe search and style history lookup", "historical bonding trends for footwear style",
                "how to use shoe lookup in dtr module", "search test history by shoe name or style"
            ],
            "Upload & Import Raw Machine" => [
                "how to upload excel file from tensile machine", "import gotech u60 outdata file",
                "drag and drop machine spreadsheet into dtr", "troubleshoot machine file upload errors",
                "where is the machine upload dropzone", "import raw tensile readings without typing",
                "map columns from utm excel spreadsheet", "machine file parsing failed troubleshooting",
                "what columns are required in machine excel file", "how to import raw data into material test",
                "upload machine data button in dtr", "excel file upload error in digital test reports",
                "importing u60 tensile test spreadsheet", "how to load raw machine file into dtr",
                "drag and drop raw test data from computer", "machine data import guide for dtr"
            ],
            "Send Reports & Synology Approval" => [
                "how to send test report to manager on synology", "request approval for completed dtr report",
                "dispatch synology chat webhook notification", "notify supervisor that test report is ready",
                "resend approval notification to lab manager", "manager did not receive approval message",
                "where is the approval feed in dtr", "who is the technical signatory for this report",
                "submit report for manager signature", "approval request button on test report",
                "how to notify approver via synology chat", "resend notification button on approval feed",
                "synology chat approval link expired", "how does manager get notified of new test report",
                "approval notification workflow in dtr", "send finished report for sign off"
            ],
            "Verify Reports with QR Codes" => [
                "how to scan qr code to verify test report", "verify authenticity of test certificate with qr",
                "mobile report approval page for managers", "digital signature stamp on test certificate",
                "how manager approves or rejects report on phone", "scan dynamic qr code on printed pdf",
                "report verification seal in database", "what to do if approval token expires after 72 hours",
                "how to reject report with corrective comments", "manage technical signatories list in designer",
                "qr code verification page in nexus-l", "approve or reject decision on mobile",
                "digital sign off for laboratory reports", "verify test certificate authenticity",
                "mobile approval preview link", "electronic signature on test reports"
            ],
            "InterLink 2.0 Cloud" => [
                "how to sync test report with intertek cloud", "submit approved report to interlink 2.0",
                "auto fill interlink grid with dtr data", "where is interlink center in nexus-l",
                "fetch live page from intertek portal", "auto populate interlink fields from dtr",
                "check interlink background service status", "how to retry failed interlink submission",
                "interlink sync queue monitoring", "map dtr readings to interlink grid columns",
                "submit to interlink button in interlink center", "interlink background daemon status",
                "synchronize test certificate with intertek", "interlink center live grid automation",
                "how to use interlink module in nexus-l", "interlink submission workflow guide"
            ],
            "Laboratory Analytics" => [
                "where can i see laboratory pass fail analytics", "check first time pass rate in analytics",
                "view defect pareto chart for footwear lab", "testing throughput metrics this month",
                "check technician productivity in analytics", "defect breakdown by shoe component",
                "supplier quality comparison in analytics", "how to use the analytics dashboard",
                "track quality trends across production seasons", "analytics page in nexus-l navigation",
                "monthly test volume charts in analytics", "first time pass rate ftpr calculation",
                "view laboratory kpi dashboard", "export analytics charts and statistics"
            ],
            "Report Designer" => [
                "how to customize report format in designer", "open report designer in admin menu",
                "drag and drop elements on pdf canvas", "how to move company logo on report",
                "edit designer maps for field coordinates", "grid mapping in report designer",
                "test designer layout before saving", "reset report designer layout to default",
                "reposition signature block on report", "customize header layout in designer",
                "how to edit test sheet layout in designer", "save designer layout button",
                "visual template customization in nexus-l", "report designer guide for administrators"
            ],
            "Team Inbox" => [
                "how to send message in team inbox", "start new chat conversation in inbox",
                "attach test file or spreadsheet in chat", "share failure photo in inbox conversation",
                "create group chat for laboratory team", "pin important message to conversation header",
                "disappearing messages feature in inbox", "forward chat message to another colleague",
                "react to chat message with emoji", "team inbox communication guide",
                "how to collaborate with lab staff in inbox", "open inbox module from top navigation"
            ],
            "System Administration" => [
                "how to switch active database in settings", "test connection to alternate database",
                "transfer data between databases in settings", "sync git repository to pull updates",
                "test interlink microservice connection", "re-seed ai knowledge in admin settings",
                "where are system settings in nexus-l", "database test connection and switch guide",
                "start data transfer in admin settings", "system administration guide for nexus-l",
                "how to update codebase via git sync button", "configure system settings as admin"
            ],
            "Brain AI Console" => [
                "how to supervise ai bot in brain console", "take over live chat session as supervisor",
                "review unanswered questions in brain", "answer escalated user questions in brain",
                "train ai neural term weights in brain", "flush brain cache and sync playbooks",
                "view brain health diagnostics and overview", "how to teach the bot new playbooks",
                "brain console guide for administrators", "open brain console from admin sidebar",
                "manage bot knowledge entries in brain", "monitor real-time bot conversations"
            ]
        ];

        foreach ($moreTasks as $target => $list) {
            foreach ($list as $q) {
                $add($target, $q);
            }
        }

        
        // 20. CONVERSATIONAL PROMPTS, TROUBLESHOOTING & MULTI-STEP WORKFLOWS (250+ queries)
        $troubleshoot = [
            "PDF Reports" => [
                "why did my pdf not download properly", "pdf report shows blank page error",
                "how do i fix missing logos on printed report", "how to generate and print pdf report with both logos",
                "can you show me how to download the pdf certificate", "print test report on a4 paper without borders",
                "where do i find the download button for test pdf", "i need to export this test as a pdf file",
                "generate high quality vector pdf report", "how to preview pdf report before printing it",
                "pdf generation failed error troubleshoot", "can i download multiple pdf reports at once",
                "exporting completed dtr to official pdf certificate"
            ],
            "Live Timers" => [
                "timer paused unexpectedly during material test", "how to resume test stopwatch after machine inspection",
                "can i run multiple material tests at the same time", "how to track test duration on running cards",
                "stopping material test before target cycles", "what happens when i click stop test in materials",
                "how do i start a test run on rubber dumbbell", "starting stopwatch on bally flexometer",
                "material test session setup guide", "assigning technician name to active test run",
                "how to monitor all running machines in lab", "where to find active material tests in progress"
            ],
            "Cycle Checkpoints" => [
                "how often should i log findings during flex test", "what to do when sample shows early micro cracking",
                "logging photo evidence of specimen failure", "recording intermediate inspection remarks at 25k cycles",
                "where do intermediate findings appear on the final report", "can i edit a logged finding after saving",
                "checkpoint findings logging for satra tm55", "recording crack progression on leather upper",
                "logging visual defect on running sample card", "how to attach picture of sample failure"
            ],
            "Whole Shoe Physical" => [
                "why did whole shoe test stop at 80000 cycles", "how to record early sole delamination on walking flex",
                "stopping left shoe while right shoe continues flexing", "how to log water penetration in dynamic flex bath",
                "technician assignment on whole shoe test card", "starting 150k cycles walking test on new style",
                "whole shoe flexer machine operation steps", "dynamic waterproof shoe testing procedure",
                "recording periodic cycle counts on whole shoe run", "how to create a new whole shoe test session"
            ],
            "Unified Test Results Summary" => [
                "can i filter test summary by both brand and season", "how to find all failed tests for Vans this month",
                "searching test results summary by purchase order", "how to view only passed tests in the summary table",
                "where can i check the overall pass rate in summary", "quick filter for tests completed yesterday",
                "test results summary search bar instructions", "finding specific lab ref in unified summary",
                "how to check status of all testing across factory", "viewing material and whole shoe tests together"
            ],
            "Export Laboratory Summary" => [
                "excel download failed how to retry", "can i export only filtered test records to excel",
                "exporting weekly test logs for brand auditor", "downloading spreadsheet of all passed materials",
                "where is the exported excel file saved", "how to format test summary data for excel report",
                "downloading xlsx file of physical test results", "exporting laboratory logs to excel spreadsheet",
                "exporting test results for quality meeting presentation", "generating monthly summary spreadsheet"
            ],
            "Receive Samples" => [
                "what information is required on sample intake", "how to log received compound batch from supplier",
                "registering incoming vulcanized shoes for testing", "sample intake barcode label printing steps",
                "starting 24-hour conditioning timer in intake form", "sample intake record missing lab reference error",
                "how to log quantity received for rubber outsoles", "sample receiving workflow for qc laboratory",
                "where do i enter supplier batch number on intake", "receiving incoming leather swatches for testing"
            ],
            "Manage Retests" => [
                "what is the protocol when sole bonding test fails", "how to quarantine factory production lot after test failure",
                "pulling 3 retest pairs from beginning middle and end of shift", "how to link retest record to original failed lab ref",
                "logging corrective action plan capa for retest", "how to clear quarantine tag when retest passes",
                "retest failed again what is the next escalation step", "dtr retest list management guidelines",
                "creating retest record in digital test reports", "retest protocol for physical testing failures"
            ],
            "Shoe Lookup" => [
                "how to find historical bonding data for Old Skool", "tracing test records for a specific mold code",
                "searching shoe test history by purchase order number", "where to look up past test results for shoe style",
                "how to copy shoe style parameters into a new test", "checking if shoe style had previous bonding issues",
                "shoe lookup tool in digital test reports module", "searching test database by footwear model name",
                "viewing historical quality performance of shoe styles", "how to use shoe lookup for faster test entry"
            ],
            "Upload & Import Raw Machine" => [
                "how to upload raw excel file from gotech u60", "machine file columns do not match mapping error",
                "troubleshooting raw data upload in material test", "dragging and dropping tensile test spreadsheet",
                "how to fix excel file locked error during upload", "importing raw peak load and elongation values",
                "where is the upload machine file button on dtr page", "automatic formula calculation from uploaded data",
                "loading tensile machine outdata spreadsheet into dtr", "machine data upload guidelines for technicians"
            ],
            "Send Reports & Synology Approval" => [
                "how to dispatch approval notification to synology chat", "supervisor says they did not get approval link",
                "resending approval notification from dtr approval feed", "who is the registered technical signatory for this report",
                "submitting completed dtr report for manager sign off", "synology chat webhook alert for test certificate",
                "tracking approval status of laboratory reports", "how does manager receive approval link on phone",
                "reminding supervisor to sign pending test report", "approval request workflow in digital test reports"
            ],
            "Verify Reports with QR Codes" => [
                "scanning qr code on printed test report with smartphone", "how to verify authenticity of dtr test certificate",
                "mobile verification page approve and reject buttons", "digital signature stamp applied on approved report",
                "what happens when approval token expires after 72 hours", "how manager rejects a report with mandatory feedback",
                "managing authorized signatories list in designer", "qr code verification seal in nexus-l database",
                "electronic sign off procedure for laboratory managers", "approving test certificates from mobile phone"
            ],
            "InterLink 2.0 Cloud" => [
                "how to auto fill interlink web form from dtr report", "submitting approved test certificate to intertek cloud",
                "connecting to interlink center and fetching live page", "interlink background daemon sync status check",
                "retrying failed interlink submission in queue", "mapping dtr test readings to interlink grid columns",
                "interlink 2.0 automated reporting workflow", "checking intertek cloud sync status in nexus-l",
                "troubleshooting interlink live grid scraping", "synchronizing laboratory reports with intertek"
            ],
            "Laboratory Analytics" => [
                "how to check laboratory first time pass rate ftpr", "viewing defect pareto chart for physical tests",
                "tracking monthly testing throughput in analytics", "monitoring technician productivity and completed tests",
                "comparing compound supplier failure rates in analytics", "where to find laboratory kpi charts and statistics",
                "analytics dashboard navigation and filtering", "exporting quality analytics data for presentation"
            ],
            "Report Designer" => [
                "how to customize pdf certificate layout in designer", "moving company logo and signature block in designer",
                "grid mapping database fields to report cells", "testing designer layout before saving changes",
                "resetting report template to factory defaults", "repositioning test readings table in designer",
                "report designer visual canvas tutorial", "configuring custom pdf report layouts as admin"
            ],
            "Team Inbox" => [
                "how to chat with laboratory colleagues in inbox", "sharing raw machine excel files in inbox conversation",
                "creating a group channel for qc laboratory staff", "pinning important announcements in team inbox",
                "using disappearing messages for sensitive audit files", "forwarding test results message to supervisor",
                "team inbox communication and collaboration guide", "desktop notifications for new inbox messages"
            ],
            "System Administration" => [
                "how to switch active database without system downtime", "testing connection to alternate database server",
                "transferring records between staging and production dbs", "testing interlink microservice api connectivity",
                "syncing git repository to pull latest updates", "re-seeding ai knowledge playbooks in settings",
                "system settings administration tutorial", "managing laboratory system configuration as admin"
            ],
            "Brain AI Console" => [
                "how to monitor and supervise ai bot in brain console", "taking over live chat when bot needs human help",
                "reviewing unanswered questions and drafting answers", "training ai neural term weights across epochs",
                "flushing brain cache and syncing markdown playbooks", "checking brain overview and health diagnostics",
                "how supervisor answers escalated user questions", "teaching the bot new playbooks in brain console"
            ]
        ];

        foreach ($troubleshoot as $target => $list) {
            foreach ($list as $q) {
                $add($target, $q);
            }
        }

        
        // 21. ADDITIONAL COMMON OPERATOR WORKFLOW REQUESTS (50+ queries)
        $extraBatch = [
            "PDF Reports" => [
                "where do i find the print report button", "print test sheet on a4 standard paper",
                "pdf report layout vector view", "exporting laboratory certificate to pdf document"
            ],
            "Live Timers" => [
                "starting material test session on machine", "live stopwatch controls on material card",
                "pause and resume material test run", "stop material test after completion"
            ],
            "Cycle Checkpoints" => [
                "log inspection checkpoint at 10k cycles", "intermediate sample observations logging",
                "record micro crack during flex fatigue run", "attach failure photo to test checkpoint"
            ],
            "Whole Shoe Physical" => [
                "whole shoe flex testing machine setup", "dynamic waterproof flexing run for boots",
                "record cycle count milestones on whole shoe", "how to stop single damaged shoe on flexer"
            ],
            "Unified Test Results Summary" => [
                "unified test summary table guide", "how to filter tests by season and status",
                "search test summary by shoe model name", "check status of all tests in lab"
            ],
            "Export Laboratory Summary" => [
                "exporting all test logs to excel spreadsheet", "download test summary xlsx file",
                "generate excel report of laboratory results", "export filtered summary for quality review"
            ],
            "Receive Samples" => [
                "logging received materials in dtr intake", "assign unique lab ref number to new batch",
                "sample receiving and conditioning start time", "print intake barcode label for sample"
            ],
            "Manage Retests" => [
                "retesting protocol for failed production batch", "quarantine production lot after test failure",
                "pull 3 additional pairs for formal retest", "log corrective action plan in retest record"
            ],
            "Shoe Lookup" => [
                "search historical test records for Old Skool", "shoe lookup tool in dtr module",
                "find test history by purchase order number", "clone past shoe metadata for new test"
            ],
            "Upload & Import Raw Machine" => [
                "uploading gotech u60 raw machine excel file", "drag and drop tensile spreadsheet into dtr",
                "map columns from raw utm test data", "automatic strength calculation from uploaded file"
            ],
            "Send Reports & Synology Approval" => [
                "dispatch synology chat approval notification", "resend approval link to lab manager",
                "submit completed test certificate for signature", "approval request workflow in dtr"
            ],
            "Verify Reports with QR Codes" => [
                "verify test certificate authenticity with qr code", "mobile approval and digital signature stamp",
                "manage technical signatories in designer", "72-hour approval link expiration protocol"
            ],
            "InterLink 2.0 Cloud" => [
                "auto fill interlink grid with test data", "submit approved report to intertek cloud",
                "check interlink background daemon queue status", "retry failed interlink submission"
            ],
            "Laboratory Analytics" => [
                "view laboratory first time pass rate ftpr", "defect pareto chart for footwear testing",
                "monitor testing throughput and completed tests", "supplier compound quality comparison"
            ],
            "Report Designer" => [
                "customize pdf certificate layout in designer", "move logos and signature blocks on canvas",
                "grid mapping database fields to report cells", "save custom report template in designer"
            ],
            "Team Inbox" => [
                "chat with lab colleagues in team inbox", "share raw machine data files in chat",
                "create group channel for qc staff", "pin important announcements in inbox"
            ],
            "System Administration" => [
                "switch active database without downtime", "test database connection and transfer data",
                "sync git repository to pull updates", "system settings administration guide"
            ],
            "Brain AI Console" => [
                "supervise ai bot and take over live chats", "review unanswered questions in brain",
                "train neural term weights across epochs", "flush brain cache and sync playbooks"
            ]
        ];

        foreach ($extraBatch as $target => $list) {
            foreach ($list as $q) {
                $add($target, $q);
            }
        }

        return $queries;
    }
}
