---
type: "CodebaseIndex"
tags: [obsidian, graphify, icm, codebase-index, nexus-l, interlink, dtr, qc-lab]
updated: "2026-11-10"
---

# NEXUS-L - Codebase Index

[[ICM_CHANGELOG]] | Knowledge Graph: `E:\html\graphify.html` | Canvas: `_docs/obsidian/graph.canvas`

NEXUS-L is the laboratory intelligence and execution system built on CodeIgniter 4, featuring real-time messaging, Intertek InterLink 2.0 integration, and the QC Lab Digital Test Reports (DTR) test suite.

## Main Controllers & Components

| File | Responsibility |
| :--- | :--- |
| `app/Controllers/SettingsController.php` | Settings page (Admin only): InterLink background service settings & connectivity test, database in use / testing / switching, table-by-table background migration, and AI engine controls. |
| `app/Controllers/BotController.php` | LEXIS AI (Laboratory Execution Intelligence System): live read-only DB querying (`dbSearchIntent`), Synology approval reminders (`approvalReminderIntent`), interactive test computation & pass/fail rating (`computeIntent`), and knowledge retrieval. |
| `app/Libraries/ProblemKnowledgeSeeder.php` | 200 real-world QC Lab & testing problem scenarios with step-by-step diagnostic solutions, automated export to `C:\NexusAI\knowledge.jsonl`, and FAISS vector re-indexing. |
| `app/Controllers/MailRelayController.php` | API of the Outlook Email Relay Windows app (`mail-relay/*`, token `X-Relay-Token`): ping/config/heartbeat, atomic FIFO queue claim, retry back-off, enqueue, stats. See [[ICM_CHANGELOG]] Phase 207. |
| `E:\desktop\MaterialTest\NexusEmailDesigner` | Email Designer (WinForms): drag-and-drop builder of the five NEXUS-L e-mails, exports Outlook-safe HTML. Designs are uploaded in Settings > E-mail > E-mail designs (`MailOutbox::render`). See [[ICM_CHANGELOG]] Phase 208. |
| `app/Libraries/MailOutbox.php` | The one way to queue an e-mail into `tbl_email_outbox`; relay config and online state. Used by Dtr, Approval, MailRelay and Settings. |
| `app/Libraries/SystemSettings.php` | Persistent admin configuration in `tbl_system_settings` with `.env` fallback for AI mode, InterLink defaults, approval expiry, and Synology chat. |
| `app/Libraries/AiBridge.php` | AI assistant microservice bridge: routes questions and user clickstream telemetry to the LangGraph/Rasa agent, handles shadow inference and live zero-hallucination gating. |
| `app/Controllers/InterlinkController.php` | InterLink 2.0 proxy, Pre-Sync ComboBox data provider (`/interlink/labrefs`), report persistence, and 1-click live postback to Intertek portal. |
| `app/Controllers/DtrController.php` | Digital Test Report (DTR) module: 10 Material Test Forms, Whole Shoe Bonding (U60), Smart Standards lookups, machine auto-loader, and print report preview. |
| `app/Controllers/InboxController.php` | Messaging and inbox suite with group approval gating and theme reactions. |
| `app/Controllers/AuthController.php` | User authentication, session handling, and role permissions. |
| `app/Models/InterlinkReportModel.php` | InterLink reports model, sample records, test items, specimen readings, and sync audit logs. |
| `app/Views/interlink/index.php` | InterLink portal interface matching SearchForm, NormalForm, and UploadForm with Tab 1 & Tab 2 sub-grids and Pre-Sync Dialog. |
| `app/Views/dtr/index.php` | QC Lab suite: Lab Tracker & IQC log, 10 material forms, whole shoe bonding, calculators, and print preview modal. |
| `public/assets/js/interlink.js` | Pre-Sync Dialog logic, dynamic LabRef ComboBox loading, field highlight syncing, 1-click submission, and the Upload Form **Generate Report** button (`window.nxReportPdf`: Lab Ref → DTR print sheet → `dtr/sheet-pdf` PDF attachment). |
| `app/Libraries/ReportPdf.php` | Material report PDFs without Access (blank form + dbnexus values via `tools/build/render-report.js`), sign-off line finder, signature background removal, stamping. Forms in `app/Libraries/report_templates/`. |
| `app/Controllers/ApprovalController.php` | QR approval of reports by registered Managers / Supervisors: request, single-use links, sign, signed PDF, verify (`dtr/approval/verify`), signatories admin, per-user PDF name format. |
| `app/Controllers/LabMonitoringController.php`, `app/Views/labmonitor/erp.php`, `public/assets/js/labmon.js` | Lab Monitoring > ERP Finish Monitoring: month view of bonding lab refs, ERP / InterLink status, row dialog (read-only U60 report), single and bulk **Submit to ERP**, ERP log, ERP PC badge. |
| `app/Libraries/ErpMonitor.php`, `app/Libraries/ErpAgent.php`, `app/Controllers/ErpAgentController.php`, `app/Commands/ErpImport.php` | ERP payload / import from the daily tracker workbook; client for the ERP Agent (PC .43 / .45); result callback; `php spark erp:import`. |
| `E:\desktop\MaterialTest\NexusErpAgent\` | **NEXUS-L ERP Agent** (C# tray app): HTTP API, FIFO queue, ERP driver on a hidden desktop, log view, Inno Setup installer. |
| `app/Libraries/EtrfAppNos.php` | One Application No. column + history per eTRF type on `tbl_dtr_unified_labrefs`. |
| `app/Controllers/PhotoLinkController.php`, `public/assets/js/dtr-photo.js`, `dtr-photo-attach.js`, `app/Views/photo/capture.php` | Phone camera QR links (shoe picture, Send Report photo): token, lock, live update. |
| `app/Libraries/QrPng.php` | QR code PNG maker (Synology message, e-mail, photo links). |
| `public/assets/css/polish.css`, `labmon.css` | Shared page polish (InterLink, eTRF, Help, Designer, Brain, documents) and the ERP Finish Monitoring styles. |
| `app/Libraries/Brain.php` | Bot knowledge search: per-word, rarity-weighted matching and ranking (cached corpus), confidence. |
| `tools/interlink-sender/` | Chrome extension (v1.4.0): fills and saves InterLink forms, reads the live grid, and refuses to save anything not approved. |
| `tools/interlink-service/` | C# .NET 9 background service (Playwright, headless Chromium): submits approved reports to InterLink without a visible browser; see its README. NEXUS-L side: `InterlinkServiceController`. |
| `tools/build/render-report.js`, `tools/access-report-*.ps1`, `tools/pdf_signoff.py`, `tools/build/stamp-pdf.js` | Report rendering, one-time Access form/layout extraction, sign-off finder, signature + stamp. |
| `public/assets/js/interlink_center.js` | InterLink Sender hand-off (`nxFillHelper`): prepares the form values and attaches the generated/browsed report PDF. |
| `public/assets/js/dtr.js` | 100% VBA computation port: `ApplyStandardValuesSmart`, directional averages, 6-point colorfastness, bonding spot rules, calculators, machine data parsing, and print preview rendering. |
| `public/assets/css/interlink.css` | InterLink 2.0 theme styling, DevExpress pager, and status tags. |
| `public/assets/css/dtr.css` | Laboratory cards, measurement inputs, rating stamps, and `@media print` A4 report sheet layouts. |

## Database Architecture (`dbnexus` on `192.168.32.43`)

| Table Group | Tables | Details |
| :--- | :--- | :--- |
| **InterLink 2.0** | `tbl_interlink_reports`, `tbl_interlink_sync_logs` | Ported Intertek form columns, live sync state, and audit history. |
| **Material Results** | `tbl_dtr_fabric_results`, `tbl_dtr_leather_results`, `tbl_dtr_wst_results`, `tbl_dtr_fthl_results`, `tbl_dtr_hardware_results`, `tbl_dtr_label_results`, `tbl_dtr_sklfm_results`, `tbl_dtr_rubber_results`, `tbl_dtr_box_results`, `tbl_dtr_insole_results` | Complete test readings across all 10 material categories migrated from Access. |
| **Bonding U60** | `tbl_dtr_bonding_info`, `tbl_dtr_bonding_points`, `tbl_dtr_bonding_spot_points` | Whole shoe bonding headers, 132-column point readings, and spot data. |
| **ERP & photos** | `tbl_erp_submissions`, `tbl_photo_links` | ERP send log with payload and status; one-off phone camera links with lock. |
| **Master Index** | `tbl_dtr_unified_labrefs` | 4,670 unified test reports (bonding incl. uncollected samples, Whole Shoe) indexed with JSON test item results for instant Pre-Sync Dialog ComboBox lookup. |
| **Masters & DSR** | `tbl_dtr_standards`, `tbl_dtr_materials_master`, `tbl_dtr_dsr`, `tbl_dtr_shoe_list`, `tbl_dtr_material_list` | Reference data for automatic material, shoe, and thickness-dependent standards lookups. |

## Machine Data Folders (`\\192.168.32.45\OUTDATA\`)
- `MATERIALS\TENSILE\`: Tensile strength & elongation curves.
- `MATERIALS\TROUSER TEAR\`: Trouser/wing tear data.
- `MATERIALS\STITCH TEAR\`: Stitch tear readings.
- `MATERIALS\LAMINAR\`: Laminar peel dry/wet data.
- `BONDING\`: Whole shoe bonding test files (`U60.xls` and CSV).
- *Strict Rule:* Network files are read-only; never modified or deleted.


## Status (2026-11-08)
- ERP Finish Monitoring sends reports to the ERP Agent (ICM Phase 206); the agent enters them in the ERP client in the background (hidden desktop) one at a time.
- Per-eTRF Application Nos., phone camera links and report approval messages (Synology + e-mail with QR) are live (ICM Phase 206).
- NEXUS-L is detached from Access: `dbnexus` is the only database; reports print from it (ICM Phase 196).
- Nothing is saved in InterLink without an approved, signed report (ICM Phases 194 and 197).
- AI Assistant & Help Bot fully operational (ICM Phase 200 & 201): live read-only DB querying, Synology Chat approval reminders, 200 problem knowledge corpus, and interactive test computation engine with follow-up validation.
