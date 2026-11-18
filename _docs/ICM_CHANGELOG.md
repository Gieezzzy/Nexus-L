# ICM Record: NEXUS-L

[[OBSIDIAN_CODEBASE_INDEX]] | Knowledge Graph: `E:\html\graphify.html` | Canvas: `_docs/obsidian/graph.canvas`

---

## Phase 211 - VF Test Monitoring: tree of e-mail batches, tick rows, reply e-mail with the eTRF PDFs (2026-11-12)
- **VF Test Request page:** Incoming Requested Items grouped by e-mail (tree), every request shown with its processing status (waiting / sending / submitted / already in InterLink / history / completed) and a Show filter; Copy as New dialog got a past-Application picker (`GET interlink/vf-test-request/past-apps`). The duplicate check ignores `HISTORY` rows.
- **VF Test Monitoring (`labmonitor/vf_requests`):** "Email Request Batches & Outgoing PDF Notifications" is a tree (batch = parent, requests = children) with a check box per request and a select-all in the header row. **Send Batch PDF Email** opens a dialog (To / CC / Subject, reply tick box, attachment list, message preview). `POST lab-monitoring/vf-requests/email-preview` and `send-email` take `request_ids`.
- **Mail:** `VfTestRequestService::buildBatchEmail()` builds the reply (layout of the CPSIA reply: greeting, "eleven (11) new CPSIA items", signature block, file names `Application No - MTL# - CPSIA TEST.PDF`). Recipients per Test Package come from Settings > VF "Who the eTRF PDF e-mail is sent to"; signature, greeting and logo are settings too. `tbl_email_outbox` got `reply_to_message_id` / `reply_to_subject`.
- **NexusOutlookRelay 1.1.0:** when `reply_to_message_id` is set, it finds the original mail (Internet Message-ID, Inbox tree + Sent Items of every mailbox) and answers it with `Reply()` (same thread, original quoted); not found = new mail with RE: subject and the in-reply-to id set. The Server HTML Handler (SMTP) does not thread.
- Verified: PHP lint, a spark test of the preview with a temporary batch (removed), Relay build. Not verified: a real reply through Outlook, the new pages on screen.

---
## Phase 210 - VF Test Request: InterLink service async reads, saved list copy, configurable e-mail sources (2026-11-12)
- **NexusInterlinkService 1.3.6 -> 1.3.13:** Copy as New works end to end (popup form ids without PH1_, certify check box + Continue, success only on the THANK YOU page, PDF taken from the printed eTRF; only real %PDF files > 5 KB are accepted). `JobQueue`: loading/filtering modes (`vf_grid`, `grid_html`, `lov`) run in parallel (max 3, start gap 1.5 s, 60 s pause after 3 failures, identical requests shared, 60 s result cache, one load per user); submissions stay FIFO one at a time.
- **Saved list copy:** `tbl_vf_grid_cache` + `App\Libraries\VfGridCache`, `GET interlink/vf-test-request/grid`. One refresh per (package, search, page) key at a time; only the job claimed for a key fills it; a result for another page is not saved.
- **Page layout:** submitted requests leave "Incoming Requested Items" and appear in "Requests sent from NEXUS-L" below the InterLink list (status follows the e-mail: waiting / sent / in transit / received / completed).
- **Configurable e-mail sources:** `tbl_vf_email_sources` (senders, subject keywords, default package, column-name map, skip pattern) managed from the VF Test Request page (E-mail sources). `VfTestRequestService::findSourceFor()`, per-source column map, all table columns kept in `tbl_vf_test_requests.extra_json`, `source_id` on batches and requests. djc_lab is only the seeded first source.
- **Outlook Attachment Sync 1.1.0:** client chooses PC 45 or PC 43 with automatic failover; admin app runs on both (Internet Message-ID lock against double saves, secret.key check at start). NEXUS-L marks the Outlook sync stale after 6 h.
- Verified: PHP lint, a spark test with a temporary second sender (wildcard match, custom columns, forced package, extra columns stored, then removed), SQL claim test. Not verified live with real mail or several users.

---
## Phase 209 - Warehouse: real AppSheet box ledger imported and placed in the 3D Tracker (2026-11-10)
- **Source found:** MySQL already had the full legacy AppSheet data in database `dbappsheet` (`sample_boxes_bonding` 2059 rows, `sample_boxes_flexing_uv` 320, `sample_boxes_aging` 107, `sample_boxes_washing` 191, `sample_boxes_hydrolysis` 217) - no re-export from Google Sheets needed.
- **`dbnexus.tbl_warehouse_box_items`** (new): the content ledger - 2,843 rows (box_name, test_type, lab_reference, tested_date, production_date, po_or_tr, style, mtl, colorway, box_code). Import script: `E:\New folder (2)\scratch\import_warehouse_boxes.php` (idempotent upsert).
- **Placed in the 3D Warehouse Tracker:** 60 real physical boxes created in `tbl_warehouse_boxes` (box_code `WH-BOND-*` / `WH-WS-*`) and linked back to their items. Whole-shoe box names are shared across flexing/UV, aging, washing and hydrolysis (same physical box, several test types) - 43 distinct Bonding boxes + 17 distinct Whole-Shoe boxes = 60, matching the 60 distinct box names found.
- **4 new racks** added to `WarehouseTrackerController::racks()` (`RACK-ARCH-B1/B2/B3` on the open west wall for Bonding, `RACK-ARCH-WS` on the south wall away from the door for Whole Shoe), each 5 tiers x 4 slots = 20, matching the admin form's existing tier/slot clamp. Added to both rack `<select>` dropdowns and the `$rackNames` map in `app/Views/warehouse/index.php`. Placement script: `E:\New folder (2)\scratch\place_warehouse_boxes.php` (idempotent - only places a box_name once).
- Verified by direct SQL and PHP syntax check only; not checked with a live authenticated screenshot (no session available in this session). Ask the user to open Warehouse 3D Tracker and confirm the 4 new archive racks look right (west wall, south wall) with no overlap.

---
## Phase 208 - Email Designer app, e-mail designs in NEXUS-L, Relay repository (2026-11-10)
- **NEXUS-L Email Designer (`E:\desktop\MaterialTest\NexusEmailDesigner`, `NexusEmailDesigner.exe`, C# .NET Framework 4.8 WinForms, Newtonsoft.Json):** drag-and-drop e-mail builder. Toolbox of 15 elements (header band, heading, text, picture, picture-from-NEXUS-L slot, button, facts table, highlight box, bullet list, columns 2/3 with nested drop, divider, space, link row, footer, raw HTML); owner-drawn `DesignCanvas` (drag to move, insertion line, auto-scroll, selection, double-click to edit text, Ctrl+Z/Y/D/Up/Down); property panel filtered per element (`FilteredView`); undo/redo; autosave to `%AppData%\NexusEmailDesigner\project.json`; `.nxemails` project file.
  - **Five formats (slots):** `report_sent`, `approval_request`, `test_alert`, `daily_summary`, `general_notice`, each with a starter design and its variables (`{{title}}`, `{{message}}`, `{{lab_ref}}`, `{{images_html}}`, `{{qr_img}}` ...).
  - **Export HTML** (`HtmlExporter`): Outlook-safe (table layout, inline styles, MSO conditionals, bulletproof button, pictures embedded as `data:` which the Relay / Handler turn into inline CID). Export one / all 5 / **Install to Relay** (`%LocalAppData%\NexusOutlookRelay\templates`). `--selftest <folder>` and `--shot <png>` check a build headlessly.
- **NEXUS-L:** `MailOutbox::TEMPLATES|render|fill|subjectOf` (designs stored in `writable/email-templates/<slot>.html`; `_html` / `_img` variables are HTML, all others escaped with line breaks kept); Settings > E-mail > **E-mail designs** (list, upload `.html` with script check, preview in a sandboxed iframe, remove) via `SettingsController::mailTemplates|mailTemplateUpload|mailTemplateDelete|mailTemplatePreview`; used by Send Report (`report_sent`), the signatory approval e-mail (`approval_request`) and the test e-mail (`general_notice`); `mail-relay/enqueue` accepts `{template, vars}`. With no design uploaded the built-in layout is used as before.
- **Outlook Relay:** `TemplateStore` renders a design by name for the local `/api/enqueue` (`{"template":"general_notice","vars":{...}}`). The Relay is now in its own repository: **https://github.com/Gieezzzy/NexusOutlookRelay** (private).

- **Designer update (2026-11-10):** new variables synced in NEXUS-L (`MailOutbox::TEMPLATES`, Send Report / approval / Settings preview): `report_sent` + `result`, `link`; `approval_request` + `date`; `test_alert` + `action_required`.

---
## Phase 207 - Outlook Email Relay: web settings, safe queue, Handler fallback (2026-11-10)
- **Outlook Relay (`E:\desktop\MaterialTest\NexusOutlookRelay`, `NexusOutlookRelay.exe`, C# .NET Framework 4.8, WinForms tray app v1.1.0):** sends the e-mails of `tbl_email_outbox` through the desktop Outlook (COM), FIFO, with a local HTTP listener (127.0.0.1:5088).
  - **New in 1.1.0:** follows the server settings (poll / pause / test mode / blind copy / sender) pulled from `mail-relay/ping|queue|heartbeat`; heartbeat every 15 s (account, version, counts) so NEXUS-L shows if it is online; picks the Outlook account whose SMTP equals the sender (`SendUsingAccount`, else SentOnBehalfOf, else the default account); `data:image` pictures in the HTML become inline CID attachments; one reconnect-and-retry when Outlook was closed; local `/api/enqueue` now **needs the relay token** and has **no CORS** (a web page could send mail through it before); body limit 40 MB.
- **NEXUS-L side:**
  - `App\Libraries\MailOutbox` (new): the one way to queue an e-mail (`enqueue`, address validation, `ensureTable` with all columns incl. `claimed_at/claimed_by`, `relayConfig`, `relayOnline`, `touchRelay`). `DtrController` (Send Report) and `ApprovalController` (signatory e-mail) use it; the DtrController **no longer pushes the same e-mail to `127.0.0.1:5088`** (it was sent twice: once from the push, once from the queue).
  - `MailRelayController`: `ping` (+config), `config`, `heartbeat`, `queue` (rows are **claimed one by one** with `UPDATE ... WHERE status='queued'`; stale `sending` rows older than 10 min return to the queue; daily limit; allowed recipient domains; hands out `bcc_address` and `dry_run`), `update-status` (a failed send goes back to the queue with a growing wait until `mail.relay_max_attempts`, then `failed`), `enqueue` (the old `is_html` always-1 bug fixed), `stats`.
  - Settings > **E-mail (Outlook Relay)** card (`SettingsController::mailStatus|mailRetry|mailTest`, `settings.js`): live Relay status, counts, the latest 25 e-mails with **Retry now**, **Send test e-mail**, and the settings `mail.transport` (auto / relay / handler), `mail.fallback_after`, `mail.relay_max_attempts`, `mail.relay_retry_minutes`, `mail.relay_poll_seconds`, `mail.relay_throttle_seconds`, `mail.relay_daily_limit`, `mail.relay_dry_run`, `mail.relay_bcc`, `mail.relay_allowed_domains`, sender name / e-mail, Relay token. Internal: `mail.relay_seen_at`, `mail.relay_info`.
- **Server HTML Handler (`D:\desktop\MaterialTest\Server HTML Handler`, `Services/EmailOutboxService.cs`):** both programs read the same outbox, so the Handler now asks `ShouldSend()` first: `relay` = never, `handler` = always, `auto` = only when the Relay has not been seen for `mail.fallback_after` seconds. It creates the full table (and adds missing columns), claims with `claimed_at/claimed_by`, and at start only returns `sending` rows older than 10 minutes (it used to reset all of them, which could double-send a mail the Relay was sending). **Rebuild and restart the Handler to apply.**
- Tested: API (auth, enqueue, FIFO claim, retry back-off, heartbeat, stats), local listener token (401 / 401 / 200), full loop in test mode through the built Relay code. A real Outlook send was not run.

---
## Phase 206 - ERP Finish Monitoring, ERP Agent, phone camera links, report approval messages (2026-11-08)
- **Lab Monitoring > ERP Finish Monitoring (`LabMonitoringController`, `labmonitor/erp.php`, `labmon.js/css`):**
  - Bonding lab references per month, by production day, grouped Right / Left shoes: tested / production date, line, VSP code, Bonding and Pull-test TR numbers, InterLink status (green OK / orange pending / red not submitted), result with notes, ERP status (editable). Click a row for the read-only Shoe Bonding (U60) report in a dialog; **Submit** shows checkboxes for bulk send.
  - `tbl_erp_submissions` is the ERP log (single / bulk, batch id, status queued > sending > submitted / failed, message, payload JSON). The page follows it live and tells the submitter with a toast when a report is completely sent.
  - `php spark erp:import "<2026 Daily Tracker.xlsx>"` fills Application / TR numbers, ERP status and the InterLink flag from the daily tracker workbook (`ErpMonitor::importWorkbook`).
- **Per-eTRF Application Nos. (`EtrfAppNos`):** `tbl_dtr_unified_labrefs.etrf_<mat|prod|pull|bonding|whole|adult>_app` holds the current Application No. of each eTRF type for a Lab Ref and `_hist` the earlier ones (`A1;A2`); filled when an eTRF is submitted and shown in the eTRF Lab Reference lookup.
- **Lab Reference index completed (`DtrLabrefIndex`):** 85 bonding lab refs with no index row were added; **Whole Shoe** is now a category (`form_slug = wholeshoe`, from `tbl_dtr_wholeshoe_intake`, status / result from its tests); a bonding sample with no result is no longer indexed as Pass. `tbl_dtr_unified_labrefs` = 4,670 rows.
- **ERP Agent (`E:\desktop\MaterialTest\NexusErpAgent`, `NexusErpAgent.exe`, C# .NET Framework 4.8, tray app):** accepts requests over HTTP (IP, port, token), keeps them in a FIFO queue and enters one report at a time in the ERP client (Orders > Finished Product Test Report: style lookup by MTL#, test / send / receipt dates, L/R, Bonding / Pull TR, result, serial, remark = line) by sending Win32 messages only; Tasks / Log / Settings window. The ERP runs on a **hidden Windows desktop** (own sign-in; started and signed in by the agent from the saved user, password stored with DPAPI) or the visible ERP. After Save every box is read back; the ERP's own Update Error window is read, the list is refreshed and one retry made on a duplicate key. NEXUS-L side: `ErpAgent` (Settings > ERP agent: choose PC .43 or .45, each with IP / port / token), `ErpAgentController` (`erp-agent/callback`), `lab-monitoring/erp-submit` builds the payload (`ErpMonitor`), `agent-status` badge. Installer: `NexusErpAgent-Setup-1.0.0.exe` (Inno Setup, `installer.iss`), logo made in Canva.
- **Phone camera links (`PhotoLinkController`, `dtr-photo.js`, `dtr-photo-attach.js`, `tbl_photo_links`):** a QR with the site + Lab Ref + test type + one-off token (15 min) for the shoe picture in *+ Bonding Shoe Collected* and for the Shoe Bonding Send Report dialogs. One live QR per shoe / report, one phone at a time (heartbeat lock, released when the picture is sent or the page is closed), the picture replaces the QR live. Send Report (Synology and e-mail) carries two images: the report graph card and the photo; both dialogs share them, and a click enlarges any attached image.
- **Report approval messages (`ApprovalController`, `QrPng`, `SynologyChat`):** every required signatory gets their own Synology message with the QR code picture and the single-use link, and an e-mail (QR inline) through the Server HTML Handler outbox (`tbl_email_outbox`; the Handler's `MailService` turns `data:` images into inline pictures). The approval page was redesigned and has a **Next report** button.
- **InterLink:** Report Submission panel redesigned (two steps), Pre-Sync dialog and shared page polish (`polish.css`) on InterLink, eTRF, Help, Report Designer, Brain and documents; **1-Click Send Report** works only after Sync NEXUS-L Data; the repeated banner / bottom buttons were removed.

---
## Phase 205 - Complete VF Test Methods Integration & AI Scanner/Viewer (2026-11-01)
- **VF Test Methods Network Share Ingestion (`\\192.168.32.130`):**
  - Copied all 161 laboratory files (78.26 MB) from network share `\\192.168.32.130\qc lab files\#Lab Documents\#DTP-Lab-101 VF Test Methods Updated 2025` directly into `public/uploads/vf_test_methods/`.
  - Includes all official standard operating procedures (PDFs), universal machine parameter sheets (XLSX), test guidelines, and the full `Illustrated Test Methods` subcatalog (35 visual guide PDFs).
- **Dedicated Database Catalog (`tbl_vf_test_methods`):**
  - Created migration `2026-11-01-000001_CreateVfTestMethods.php` establishing `tbl_vf_test_methods` indexed by method code (`VF-A01`, `VF-B01`, `VF-G02`, etc.), category, file type, and illustrated flag.
  - Built CLI cataloger and seeder (`App\Commands\VfMethodsSeed` / `php spark vf:seed`):
    - Automatically scanned and parsed 162 total files into structured records with clean titles, revision dates, and procedural summaries.
    - Categorized across 10 distinct VF test series:
      - **Series A:** Physical & Dimensional Properties (Hardness, Weight, Thickness, Specific Gravity, Yarn Count, Non-Volatiles)
      - **Series B:** Strength & Mechanical Properties (Tensile, Bursting, Hardware Attachment, Die C Tear, Split Tear, Trouser Tear, Stitch Tear, Baumann Tear, Compression Set, James Slip, TM144 Traction, Rebound, Zipper, Knot Slip, Lace Tip)
      - **Series C:** Abrasion Resistance (DIN Rotary Drum Abrasion, Martindale, Taber, Lace Abrasion)
      - **Series D:** Flexing & Fatigue Resistance (Bally Upper Flex, Ross Soling Flex, Bata Belt Flex, Stretch & Flex)
      - **Series E:** Chemical, Aging & Environmental Fastness (Water Fastness, Crocking, UV Discoloration, Perspiration, Blooming, Water Swelling, Hydrolysis Jungle Test, pH Extract, Non-Marking, Breathability, Dip/Spray Tests, Photoluminescence)
      - **Series F:** Adhesion & Bond Strength (Footwear Adhesion, Peel Strength, Finish Adhesion, Laminar Peel, Ink Adhesion Tape Test, Strap Pulling, Heel/Toplift Attachment, Cardboard Adhesion)
      - **Series G:** Assembled Footwear Performance (Assembled Parameters, Whole Shoe Flex, Wash Test, Dynamic Waterproof Flex, China GB Flex, Water Absorption, Static Waterproof, Hydrostatic Head, Centrifuge Leak)
      - **Series H:** Sampling & Conditioning Protocols (Material Conditioning VF-H01: 23±2°C / 50±5% RH, Leather/Midsole Sampling, Cement Solids)
      - **Series I:** Chemical Analysis & Identification (Oil Content, Fiber ID, Dye Solvent Reaction, Oil Resistant Swelling)
      - **Series J:** Electrical & Thermal Safety (Dielectric 18kV Shock Resistance, Thermal Insulation)
      - **Manuals & Standards:** VANS Footwear Performance Testing Manual 2026, UTM Parameters, and Testing Parameters with Temperature.
- **AI Brain Seeding & Vector Retraining:**
  - Seeded **112 new comprehensive knowledge entries** into `tbl_bot_knowledge` with governing standards (ASTM, ISO, SATRA, GB), test scopes, operating procedures, and action buttons.
  - Re-trained LEXIS AI Brain (`php spark brain:train`), expanding total approved answers to **1,610 knowledge entries** and syncing offline vector embeddings.
- **Dedicated Bot Intent (`vfMethodIntent`):**
  - Added native intent in `App\Controllers\BotController` recognizing specific VF codes (`VF-B01`, `VF-F01`), method names ("show tensile test method", "martindale abrasion", "ross flex"), and catalog requests ("list of vf test methods").
  - Returns formatted cards with category, revision date, procedural summary, direct action buttons (`[Open Official PDF]`, `[Illustrated Guide]`, `[Create Test in DTR]`), and attached file chips (`r.files`).
- **New-Tab Document Viewer (`public/assets/js/bot.js`):**
  - Updated bot action and file handlers so PDF, XLSX, and DOCX documents open in fresh browser tabs without interrupting user chat sessions.

---
## Phase 204 - LEXIS AI Brain Full Training Pipeline (`spark brain:train`) (2026-10-31)
- **Unified Training Pipeline (`App\Commands\BrainTrain`):**
  - Added one-command CLI pipeline `php spark brain:train` that executes the full training, indexing, and backup workflow across all AI and Brain layers.
  - **Playbook Knowledge:** Refreshed and validated 1,014 built-in playbooks and Q&A procedures across 8 knowledge seeders (`BrainSeed1` through `BrainSeed8`).
  - **510+ QC Problem Scenarios:** Created `ProblemKnowledgeSeeder2.php` expanding the problem corpus from 200 to **510 comprehensive laboratory failure and diagnostic cases** covering SATRA/ISO physical test deviations, rubber/EVA/PU polymer defects, upper leather/mesh failures, RSL toxicological chemicals (Cr VI, Formaldehyde, Phthalates, DMFu), cementing/priming skips, and ISO 17025 instrument calibration drifts.
  - **Expanded Knowledge Base:** Total approved answers in `tbl_bot_knowledge` expanded to **1,498 answers** (1,014 core playbooks + 510 QC problems), mirrored to 1,498 Markdown playbooks and re-indexed into the vector store.
  - **Learning & Weight Optimization:** Ran `BrainLearn::refresh()` to recalculate question term weights and update learning suggestions based on user interaction signals.
  - **Backup & Markdown Sync:** Flushed updated knowledge to `knowledge.back` and exported 1,188 human-readable Markdown playbooks for Obsidian integration.
  - **Dataset & Vector Store Re-indexing:** Exported 1,188 approved answers and 25 review questions to `C:\NexusAI\knowledge.jsonl` and successfully triggered vector store re-indexing (`/admin/train/start` and `/admin/index`).

---

## Phase 203 - Facebook-Style Skeleton Shimmer & Slow Connection Resiliency (2026-10-31)
- **Zero-JS GPU Skeleton Shimmer (`.nx-shimmer`):**
  - Implemented pure CSS linear gradient shimmer (`#f1f5f9` → `#e2e8f0` → `#f1f5f9`) with 0% JavaScript CPU consumption and zero memory overhead.
  - Upgraded `nxState(tbody, 'loading')` to instantly generate 5 realistic skeleton table rows with varied cell widths matching actual test report tables, eliminating layout shifts (CLS = 0) while waiting for network responses.
  - Lists and card containers display skeleton avatar and dual-line wireframe placeholders during data fetch.
- **LEXIS AI Shimmer Thinking State (`public/assets/js/bot.js`):**
  - Replaced plain "Thinking..." text with modern 2-line conversational shimmer skeleton bubble while waiting for database aggregations, formula calculations, or LangGraph queries.
- **Top-Bar Slim Network Progress (`window.nxProgress`):**
  - Added ultra-lightweight YouTube/GitHub-style 2.5px gradient indicator at the top of the viewport (`#nx-progress`) during slow navigations and page transitions.
- **Network Connectivity Sentinel:**
  - Added automatic online/offline state listeners alerting users immediately if their network cable or Wi-Fi drops before form submissions.

---

## Phase 201 - Bot Read-Only Database Queries, Synology Signatory Reminders & 200 QC Problem Knowledge (2026-10-31)
- **UI/UX Performance & Animation Policy (QA Verified):**
  - Completely stripped out third-party animation libraries (Anime.js) and full-page cascading transitions after QA testing showed excessive visual fatigue and browser memory overhead.
  - Standardized on pure instant enterprise responsiveness: zero page-load flashes, zero wobbly hover translations on navigation menus, and zero background interval timers.
  - Retained clean, lightweight branding (LEXIS AI light logo and crisp icon badge) with native, instant browser performance.
- **Visual Identity & Light Canva Logo Integration:**
  - Designed and exported official light-themed LEXIS AI logo (`DAHZi0cxBt0`) via Canva with electric cyan and deep royal/navy blue 3D neural-molecular network iconography on a pure white background.
  - Integrated high-resolution assets into `public/assets/images/`: `lexis-logo.png`, `lexis-logo-transparent.png`, `lexis-icon.png`, and `lexis-icon-transparent.png`.
  - Floating Action Button (FAB `#nxbot-fab`) upgraded from generic `?` icon to a polished circular white launcher featuring the crisp 3D neural node mark, cyan hover illumination, and smooth micro-interactions.
  - Bot chat panel header (`#nxbot-panel header`) now displays the branded logo badge alongside `LEXIS AI` and `Laboratory Execution Intelligence System`.
  - Initial chat open showcases a branded welcome card with the full light logo and friendly onboarding copy.
  - Bot messages styled with cyan accent indicator (`border-left: 3px solid #0284c7`).
  - Admin Brain console (`/brain`) updated with the new LEXIS AI logo badge and system description.
- **Brand Identity & AI Renaming:** Officially named and rebranded the assistant to **LEXIS AI (Laboratory Execution Intelligence System)** across widget headers, floating triggers, conversational greetings, database sender identities, LangGraph prompts, and service metadata, with customizable configuration via `ai.name` in `SystemSettings`.
- **Read-Only Database Query Engine (`BotController::dbSearchIntent`):**
  - Bot understands natural queries like `"how many fail and pass do we have"`, `"what is the total failed for bonding"`, `"how many reports failed this month"`, and `"find failed reports for Vans"`.
  - Runs parameterized, read-only aggregation queries against `tbl_dtr_unified_labrefs` across all 11 test categories (`bonding`, `fabric`, `leather`, `rubber`, `wst`, `fthl`, `hardware`, `label`, `sklfm`, `box`, `insole`).
  - Summarizes Total Reports, Passed count/percentage, Failed count/percentage, Tolerable count/percentage, and lists recent failed reports with direct navigation and view buttons.
  - Zero risk: strictly read-only SELECT operations with try/catch safety boundaries.
- **Synology Approval Reminders via Bot (`BotController::approvalReminderIntent`):**
  - Listens for phrases like `"resend notification for approver"`, `"remind manager for approval"`, `"send notification for pending approval"`.
  - Detects pending approvals in `tbl_dtr_report_approvals`, validates single-use tokens, automatically refreshes expired tokens (with expiration reset), and checks active signatories in `tbl_dtr_signatories` / `tbl_users.SynologyID`.
  - Dispatches Synology Chat webhook notifications directly to managers and supervisors with single-use approval links.
  - Returns a detailed chat response listing notified managers, token countdown, and direct buttons (`[Open Approval Sheet]`, `[View Report]`).
- **200 QC Problem Questions Corpus & Auto-Seeding (`ProblemKnowledgeSeeder`):**
  - Added 200 real-world QC Lab & testing problem scenarios with step-by-step diagnostic solutions into `tbl_bot_knowledge` across 6 categories (Bonding, Materials, Signatories/QR, InterLink, Machine output shares, and System security).
  - Wired auto-seed check into `SystemSettings::ensureTable()` and added an admin trigger button **Seed 200 Problem Answers** (`#st-ai-seed`) in the Settings page (`POST settings/ai/seed`).
  - Automatically exports to `C:\NexusAI\knowledge.jsonl` and invokes Python AI re-indexing (`POST /admin/index`).
- **Interactive Test Computation & Pass/Fail Rating Engine (`BotController::computeIntent`):**
  - Directly calculates specimen averages, physical test formulas, and evaluates Pass/Tolerable/Fail verdicts when users provide readings and standards.
  - Supports Whole Shoe Bonding (Adult STD 3.0, Children STD 2.5, Center FiTO STD 1.0, 80% tolerable threshold), Directional tests (Tensile, Elongation, Tear, Mullen Burst, Die C), Specific Gravity ($SG = \frac{W_1}{W_1 - W_2}$), Compression Set ($CS = \frac{t_0 - t_1}{t_0 - t_s} \times 100$), and Shrinkage.
  - Guided conversational follow-up: if incomplete data is provided, the bot computes the intermediate math and prompts the user for the specific missing parameter (e.g. shoe age category, standard requirement, or spacer thickness) across conversational thread turns.
- **Related Pills Interactive Chat Trigger (`public/assets/js/bot.js`):**
  - Fixed related suggestion pill buttons (`data-q="${esc(title)}"`); clicking any suggested pill immediately sends the query into the chat.

---

## Phase 200 - AI Assistant & Bot Engine Web Settings & Live Switching (2026-10-31)
- **Settings Page Integration (`/settings`):** Added a dedicated **AI Assistant & Bot Engine** control panel (`#st-ai`) accessible to Admins. All settings live in `tbl_system_settings` via `App\Libraries\SystemSettings` with seamless fallback to `.env`:
  - `ai.mode`: `off` | `shadow` (background LangGraph inference & telemetry logging) | `live` (direct LangGraph response delivery).
  - `ai.url`: Service endpoint URL (default `http://127.0.0.1:8765`).
  - `ai.key`: Shared security key (`X-AI-Key`).
  - `ai.confidence_threshold`: Minimum confidence score required to render AI answers (default `0.85`).
  - `ai.fallback_message`: Rigid zero-hallucination safe message when confidence falls below threshold.
- **Diagnostic Health Check (`settings/ai/test`):** `SettingsController::testAi` and frontend handler `#st-ai-test` in `public/assets/js/settings.js` test the Python FastAPI service reachability (`GET /health`), returning service identity, status, and ping latency in milliseconds.
- **Dynamic AI Bridge & Live Dispatch:** `App\Libraries\AiBridge` refactored to read configuration in real-time from `SystemSettings`. `BotController::ask` checks `AiBridge::isLive()` and routes user queries with UI telemetry context to LangGraph; responses meeting the confidence threshold are served directly, while low-confidence queries cleanly fall back without speculation.
- **Drive E: Migration:** Validated all filesystem assets and database configurations on `E:\html\NEXUS-L`, `E:\#Digital Test Report`, and updated path references across CodeIgniter configurations.

---

## Phase 199 - Settings Page (Admin only) (2026-10-30)
- New sidebar item **Settings**, visible and reachable only for Role = Admin (`SettingsController`, routes in the `auth:admin` group, checked again in every method). Values live in the new table `tbl_system_settings` (`App\Libraries\SystemSettings`; unsaved settings fall back to their old defaults / .env values, so nothing changes until saved).
- **InterLink service:** address (IP + port that receives the payload), shared key, on/off, with a Test. `InterlinkServiceController` reads them from the settings.
- **Database:** shows the connection in use, tests another one, and **Use this database** writes `database.default.*` into `.env` (the place the code reads; a copy of the old file goes to `writable/env-backups`; the password is never sent back to the browser).
- **Transfer to another database:** `php spark settings:transfer <job>` (started from the page, runs in the background, progress polled): copies every table (structure + rows) into another database; tables that already hold rows are skipped unless overwrite is chosen; the target password is removed from the job file at the end.
- More settings: the values a new InterLink report starts with (service provider, lab location, company, vendor, factory, brand, season, ...: they were fixed in `saveReport`), the approval link time (was a constant), Synology Chat address / token / prefix (override `.env`).

## Phase 198 - InterLink Background Service (C#, invisible browser), Sender 1.7.0, Detail Page, Save (2026-10-30)
- **`tools/interlink-service/`** (C# .NET 9 + Playwright, headless Chromium): NEXUS-L hands an approved report to it (`InterlinkServiceController`: `interlink/service/submit|status|feed`, key-protected `callback` / `verify`, table `tbl_interlink_service_jobs`); it does Add / Edit in InterLink in the background with a shared service account (session saved once with `--signin`, no password stored), asks NEXUS-L again before Save, and reports back. NEXUS-L shows a toast wherever the user is and sends the Synology message `Labreference / ReportNO / submitted to InterLink`. The browser extension stays as the fallback. The page logic is shared: `tools/build/build-service-scripts.js` cuts it out of the extension's `background.js`.
- Extension 1.5.x-1.7.0: tests grouped as InterLink has them (Tensile Length + Width = one test), readings go to the rows named like them; no values are typed for any test (only Sample ID, Test Item Name, Test Method, Test Elements); the send tells InterLink's Add / Edit menu which one to use.
- Upload Form: the arrow up of a sent report is a read-only view, U > Add / Edit open it in that mode; Save stores the boxes and the three grids per application and Lab Ref; Generate asks in page dialogs; the Report Detail Page shows Test Package, Sample, Test Item Review and the paged readings like InterLink's; the page behind an open dialog does not scroll.

## Phase 197 - No Save in InterLink Without Approval; Full Header Block on the Report View (2026-10-29)

### 1. Approval gate in the sender extension (v1.4.0)
- `ApprovalController::verify()` (`GET dtr/approval/verify?lab_ref=`, auth-protected): answers `approved` plus the SHA-256 of the signed PDF.
- `tools/interlink-sender/background.js` `approvalProblem(job)`: asked once before InterLink is opened and again right before **Save** is pressed. It refuses when the report is not approved, the approval was replaced, NEXUS-L cannot be reached / is not signed in, the page did not send the check (`job.verify`), or the hash of the file to attach differs from the approved signed PDF.
- `interlink_center.js` sends `job.verify.url`; the minimum extension for 1-Click Send is now **1.4.0** (older copies could bypass the check). Zip rebuilt: `public/downloads/nexus-l-interlink-sender.zip`.
- Manual entry in InterLink itself cannot be blocked by NEXUS-L.

### 2. Report view header block
- `public/assets/js/dtr.js` (`openMaterialPrintPreview`): the sheet now shows Supplier's Country, Quantity, Received Date, Finished Date, Send by, Material Source (Prod. Material / Prod. Re-test / New/Development / Sales Sample, chosen one marked) and Tech 1-4, as on the lab form.

---

## Phase 196 - Detached from Access: Reports Print from dbnexus (2026-10-28)

### 1. Final sync, then the sync is retired
- One last `dtr:sync-access` run: all 29 tables identical to Access (hardware 503, DSR 154,426, bonding tests 1,092, ...). Not copied (by design): Access `tblUsers` passwords, empty `MainMenu` / `tblLoginTracker`, scratch `Paste Errors`, old `BondingTestPoints - Copy`.
- Removed: `DtrSyncAccess.php`, `export_access_tables.ps1`, `export_access_report.ps1`, `export_bonding_pdf.ps1`, `tools/dtr-sync-access.ps1`; Windows task `NEXUS-L Access Sync` unregistered; `DeployCheck` no longer looks for it. dbnexus is the only database NEXUS-L reads or writes.

### 2. Material reports without Access
- `app/Libraries/report_templates/`: per report (16 forms) a **blank form PDF** (printed once from Access with `tools/access-report-templates.ps1`, which swaps each results table for a one-row empty query in a private copy) and a **layout JSON** (every text box, position, font, bound field / expression; `tools/access-report-layout.ps1`).
- `tools/build/render-report.js` (pdf-lib + `@pdf-lib/fontkit`): evaluates the Access expressions (`IIf`, `Switch`, `Nz`, `&`, `Len`, `Trim`) and writes the record's values at the Access positions. Single-line boxes shrink instead of wrapping; the big PASS/FAIL word is coloured; untested rows (0 / ratings written by *Refresh & Apply Smart Standards*) print blank.
- `ReportPdf::material()` now calls the renderer. Chinese text needs `writable/cache/fonts/cjk.ttf` (`python tools/make-cjk-font.py`, from Microsoft YaHei; not stored in git).
- Verified: 48 renders over all 16 report variants, and the Hardware form against the Access print.
- Bonding: no Access print any more; the "Access PDF" button is hidden and the VF sheet is the report.
- Re-making a form after a change in Access: see `app/Libraries/report_templates/README.md`.

---

## Phase 195 - Bot Retrieval: Per-Word Matching, Weighted by Rarity (2026-10-28)

- `app/Libraries/Brain.php` `search()` now looks at the question **one word at a time** over the whole approved knowledge base (cached tokenised copy `writable/cache/brain_corpus.ser`, rebuilt when entries change): exact, same stem/prefix, or a 1-2 letter typo, each word weighted by IDF (rare words count more).
- Entries are ranked by the share of the question's weight they explain (keywords 3.0 > title 2.5 > body 0.8), plus keyword-phrase (all words present, any order), adjacent-word and title-coverage bonuses; page / error / helpful factors kept.
- Measured on 623 generated questions: title + filler words 94.5 → 98.1 % top-1, typos 89.4 → 97.1 %, keyword phrase 89.9 → 91.7 %, first sentence of an answer top-3 85.8 → 90.2 %, half of a title top-3 77.3 → 81.4 %.

---

## Phase 194 - Report Sign-off (QR Approval) and the InterLink Sender Extension (2026-10-28)

### 1. Approval before InterLink
- Tables `tbl_dtr_signatories`, `tbl_dtr_report_approvals` (+ `file_key`, `expires_at`, `used_at`, `required_name`); `ApprovalController`, views `approval/page|gone`, `approval.js/css`, Report Designer → **Signatories** tab (account, printed name, title, signature drawn or uploaded PNG).
- Flow: *Generate Report* → report view → **Request approval (QR)** → the signatory scans, signs in, checks the report sheet and signs → the signed PDF becomes the attachment. Links are single-use (40-hex token, 60 min, rotated per request, replaced by a spent token on a decision); the PDF keeps a permanent `file_key`. Synology messages go per recipient (requester told at once, other signatories told it was decided); an in-app feed pill shows waiting reports.
- **The name printed after "Approved by" on the report is the one who must sign** (`tools/pdf_signoff.py` reads it from the PDF; `ReportPdf::nameMatches`; unregistered names are refused with a message). Signature (background removed) goes beside the printed lines and an outline-only stamp under them (`tools/build/stamp-pdf.js`).
- An already approved Lab Ref can be **regenerated**: the approved version stays valid until the new one is approved, then it is marked `superseded`.
- Per-user PDF file-name format (`{app}` ...), report grids filled from the Lab Reference, report view instead of a PDF viewer on the approval page.

### 2. InterLink sender extension 1.3.x
- Save commits (no Submit button); the Application No. is searched in the live grid; Home → grid navigation through the portal's own link; live reading of any grid page (`pullGrid`, `pullGridHtml`) with ACK / progress / bounded waits; version handshake (`dataset.nxlSenderVersion`); sign-in hardening (never a second sign-in, never closes a tab the user signs in on).
- NEXUS-L never stores an InterLink login: only the user's own browser reads and writes InterLink.

---

## Phase 193 - Access Rules Parity for Bonding & Full Access → dbnexus Sync (2026-10-28)

### 1. Bonding PASS / TOLERABLE / FAIL now matches Access exactly
- Source of truth: `BondingReportV2.accdb` form "Test Log - Bonding", `UpdateBondingResult` / `UpdateFillerResult`:

  | Section | PASS | TOLERABLE | FAILED |
  | --- | --- | --- | --- |
  | UTF / FTO, Adult (STD 3.0) | ≥ 3.0 | 2.4 – 2.99 | < 2.4 |
  | UTF / FTO, Children (STD 2.5) | ≥ 2.5 | 2.0 – 2.49 | < 2.0 |
  | Center IS/OS (FiTO, STD 1.0) | ≥ 1.0 | 0.8 – 0.99 | < 0.8 |

  i.e. PASS ≥ STD, TOLERABLE ≥ 80 % of STD.
- `public/assets/js/dtr.js`: one rule (`evaluateSpotVerdict`, `bondingTolerance`, `FITO_STD = 1.0`) now drives the form calculation, the spot colours and the print sheet. Before, FiTO needed ≥ 1.9 (so e.g. `DTP20260612SHB14-L`, Center 1.20–1.38, printed **FAIL** while Access says **PASS**), adult tolerable started at 2.0 instead of 2.4, and the Status column showed "-" for a failed section (`FAIL` vs `FAILED`).
- Report: the STD column shows the tolerable limit under each standard, e.g. `2.5 (≥ 2)`, `1 (≥ 0.8)` (`.vf-tol` in `dtr.css`).

### 2. `php spark dtr:sync-access` - everything in Access into dbnexus  _(retired in Phase 196: the sync, its task and scripts were removed after a final run)_
- `app/Commands/DtrSyncAccess.php` + `app/Libraries/export_access_tables.ps1`. Reads the live Access back-ends (through the front-ends `V20261006.accdb` and `BondingReportV2.accdb`, linked to `\192.168.32.130\qc lab files\#Digital Test Report\...`) with .NET OleDb, because PHP ODBC turns Chinese into `???`.
- 29 tables: Standards (with Tolerance / TMinC / TMaxC), field map, form numbers, permissions, settings, filters, MaterialList, ShoeList, DSR, containers, materials master, material type status, import raw, audit trail, all 12 material result tables, Bally, Laminar, BondingTestInfo, BondingTestPoints. Not copied: `tblUsers` (Access passwords), empty `MainMenu` / `tblLoginTracker`, scratch `Paste Errors`.
- Reference tables mirror Access; result tables add missing rows, fill blank / `???` values, and overwrite only when Access has a newer `LastModified` (web edits kept). Same ID with a different Lab Ref is reported as a conflict and never overwritten. Missing tables / columns are created. Options: `--dry`, `--show`, `--table=Name`.
- First run repaired the Chinese text lost by the original migration (MaterialList, ShoeList, DSR, result descriptions) and brought in sign-offs, TR / SARGE / ERP status, ratings and audit-trail rows recorded in Access since then.
- Backup taken first: `writable/backups/dbnexus_tbl_dtr_before_access_sync_20261028_0920.sql` (now gitignored).
- Reports that arrive from Access also get their `tbl_dtr_unified_labrefs` row (DTR tracker, InterLink Lab Ref list, Generate Report). The row builder moved to `app/Libraries/DtrLabrefIndex.php`, shared with `DtrController::save()`; it now reads a bonding report's `Result` (not its `Remarks` comment) and maps Access `FAILED` to Fail (it used to become Pass). First run added `DTP20261019HLR-1..4`.
- **Runs every 15 minutes**: Windows task `NEXUS-L Access Sync` (registered by `tools/dtr-sync-access.ps1`) runs `php spark dtr:sync-access --log` through `conhost --headless` (no window), as the signed-in Windows user, because SYSTEM cannot open `\\192.168.32.130\qc lab files`. One run at a time, 14-minute limit. Each run appends a summary to `writable/logs/dtr-sync-access.log` (rotates at 5 MB). `php spark deploy:check` reports whether the task exists.
- Access repeats some IDs (MaterialList ID 41371 is used by 4 different materials); such rows are synced as a group, so they no longer overwrite each other each run. After the first scheduled run a full dry run reports 0 changes.
- Data fix: `DTP20260605SHB4-L` (BondingTestID 9) had been re-saved from the web as FAIL under the old FiTO ≥ 1.9 rule; Access and the corrected rule say PASS (Center min 1.19). Set back to PASS in `tbl_dtr_bonding_info`, `tbl_dtr_bonding_points` and `tbl_dtr_unified_labrefs`. The 8 web-only bonding records already follow the Access rules.

---

## Phase 192 - InterLink Upload Form: Generate Report PDF from the Lab Reference (2026-10-28)

### 1. "Generate Report" button beside Browse (Tab 2 · Report Attachment, `JOB_DUMMY_004`)
- **View** (`app/Views/interlink/index.php`): the Report Attachment (PDF) row now has **Generate Report**, a real **Browse...** file picker (`#uf-file-pdf`, PDF only) and a meta line with **View** / **Download** for the attached PDF.
- **How the PDF is made** (`public/assets/js/interlink.js`): no new renderer. It reuses the Digital Test Report pipeline that mirrors the Access forms in `E:\#Digital Test Report` (Lab Test Report / `BondingReportV2.accdb`):
  1. The Lab Reference is resolved from the Pre-Sync dialog (`#dlg-lr-labref`), the saved report (`lab_ref_no`), the Lab Ref whose TR No. is the Application No., or the `<LabRef>_TestReport.pdf` attachment name (asked for when none is linked).
  2. `GET interlink/labref-detail?lab_ref_no=` returns `form_slug` + `source_id` from `tbl_dtr_unified_labrefs`.
  3. `window.nxReportPdf({slug, id})` opens `dtr?open=<slug>:<source_id>&preview=1&export=1` in a hidden frame; `dtr.js` builds the VF print sheet and `POST dtr/sheet-pdf` renders it with headless Chrome/Edge (bonding: Access machine-result page merged as page 2).
  4. The PDF is kept as `window.nxAttachedPdf` and named `<LabRef>_TestReport.pdf` (e.g. `DTP20260929VH-12_TestReport.pdf`).
- **1-Click Send** (`public/assets/js/interlink_center.js`): `nxFillHelper` sends the generated or browsed PDF for the same application as-is, and only builds one when none is attached. The hidden-frame helper now lives once in `interlink.js` (`window.nxReportPdf`).

### 2. Fix: wrong DTR record opened for the report PDF
- `InterlinkController::centerFill` returned the unified row `id` as `lab.id`, but the DTR deep link opens records by their form `source_id` (e.g. `DTP20260612SHB14-L`: id 4563 vs source_id 14). It now returns `source_id` (falls back to `id`).

### 3. Build
- Minified assets regenerated (`tools/build/build.js` → `public/assets/min/`).

---

## Phase 191 - Whole Shoe Bonding Test Report Shoe Sketch Diagram & Vector Parity (2026-10-27)

### 1. Vector/Raster Shoe Contour Sketch Diagram with Spot Readings & Leader Lines
- **Extracted High-Resolution Sketch Assets from MS Access (`MSysResources`):**
  - Extracted all official shoe sole contours directly from `BondingReportV2.accdb` shared image resources: `26_points.png`, `27_points.png`, `28_points.png`, `29_points.png`, `30_point.png`, `31_point.png`, `32_point.png`, `33_points.png`, `34_points.png`, `L_shoe.png`, `R_shoe.png`, and `blank.png`.
  - Parsed exact twip coordinates $(x, y, w, h)$ from MS Access subreports (`SaveAsText`) across 26 to 34 points into normalized percentage offsets in `public/assets/images/bonding_sketches/sketch_configs.json`.
- **Dynamic Active Shoe & Leader Lines Overlay:**
  - In `public/assets/js/dtr.js` (`openBondingPrintPreview`), dynamically identifies shoe side (`Left` vs `Right`) and spot capacity (14, 16, 24, 27, 34 spots).
  - Positions outside values (`UTFpoint1..N`) exactly at the outer ends of the blue leader lines radiating from the shoe perimeter.
  - Positions inside values (`FTOpoint1..N`) adjacent to the inner ticks along the interior sole contour.
  - Applies official VF laboratory rating color codes:
    - **Blue (`#1d4ed8`):** Values meeting standard threshold ($\ge \text{STD}$)
    - **Amber / Orange (`#d97706`):** Values in tolerable range ($2.0 \dots 2.99$ kgf/cm)
    - **Red (`#dc2626`):** Values below tolerance ($< 2.0$ kgf/cm)
  - Displays the unmeasured opposite shoe using `blank.png` for 100% parity with Access `Report - LEFT` / `Report - RIGHT`.
- **Center IS/OS FiTO Box:**
  - Displays two vertical column boxes labeled `Left` and `Right`.
  - Inside the active column, renders FiTO 1..13 values centered vertically with $\ge 1.0$ kgf/cm standard coloring.

### 2. Official VF Laboratory Report Header & 3-Part Evaluation Table
- **Header Architecture:**
  - Stylized inline vector VF emblem with circular blue container and white italicized "VF" logo.
  - `Form # : Lab VF 001`, `Bonding Test Results / 胶着力测试`, underlined `ETRF No:`.
  - Large status stamp box (`PASS`, `TOLERABLE`, `FAILED`), `Lab Ref.No:`, test category indicator dots, and dates table.
- **Evaluation Table:**
  - 3-part breakdown: `Upper/Foxing Tape (鞋面/围条)`, `Foxing Tape / OS (围条/橡胶大底)`, and `Center : IS/OS (中插 / 大底)`.
  - Official failure codes `AF, CF, PF, SF` and root causes `(a)` through `(k)` formatted into the report grid.

### 3. Server-Side Access COM PDF Direct Export Endpoint
- **Library & Endpoint:**
  - Created `app/Libraries/export_bonding_pdf.ps1`: Invokes `Access.Application` COM object in background mode, applies SQL filter `BondingTestID = <ID>`, and outputs native pixel-perfect PDF via `DoCmd.OutputTo`.
  - Registered route `GET /dtr/bonding-access-pdf` in `app/Config/Routes.php` mapped to `DtrController::bondingAccessPdf`.
  - Added **"📥 Download Official Access PDF"** button inside the print preview modal for 1-click downloads.

---

## Phase 190 - InterLink 2.0 Integration & Digital Test Report (QC Lab) Suite Migration (2026-10-27)

### 1. Intertek InterLink 2.0 Full Integration & Live Parity
- **Controller & Models:**
  - Added `app/Controllers/InterlinkController.php`: Handles reverse-proxy (`/interlink/proxy`), report saving (`/interlink/report/save`), Pre-Sync ComboBox data provider (`/interlink/labrefs`, `/interlink/labref-detail`), and 1-Click live postback (`/interlink/sync`, `/interlink/submit`).
  - Added `app/Models/InterlinkReportModel.php`: Manages `tbl_interlink_reports`, sample definitions, test item results, specimen readings, and audit trails.
- **Pre-Sync Dialog Workflow:**
  - Displays selected **Application No.** as a styled **readonly** input (`#dlg-readonly-appno`).
  - Searchable ComboBox (`#dlg-labref-combo`) with category filters (*Fabric, Leather, WST, FTHL, Hardware, Label, SKLFM, Rubber, Box, Insole, Whole Shoe Bonding*).
  - Automatically loads and populates all 32+ fields across Tab 1 (Header/Specs) and Tab 2 (Upload Result Sub-Grids) upon selecting any `LabRefNo`.
- **Forms Parity:**
  - `SearchFormWithHeader.aspx` `[10102]`: Multi-column data grid with DevExpress pager (`[1] 2 3 ... 517`), sorting, and column filters.
  - `NormalForm.aspx` `[10000]`: Test Request Form (TRF) workflow.
  - `UploadForm.aspx` `[10072]` Finished Product & `[10076]` Material: Tab 1 form inputs and Tab 2 sub-grids for Sample Information, Test Items, and Specimen Readings.

### 2. Complete Database Migration (#Digital Test Report &rarr; MySQL `dbnexus`)
- **Migrated 367,333 records across 30 tables** from MS Access (`#Digital Test Report` and `BACKUP/V20260504/`):
  - `tbl_dtr_fabric_results` (844 rows, 250 columns)
  - `tbl_dtr_leather_results` (336 rows, 249 columns)
  - `tbl_dtr_wst_results` (615 rows, 224 columns)
  - `tbl_dtr_fthl_results` (384 rows, 136 columns)
  - `tbl_dtr_hardware_results` (503 rows, 68 columns)
  - `tbl_dtr_label_results` (301 rows, 119 columns)
  - `tbl_dtr_sklfm_results` (264 rows, 215 columns)
  - `tbl_dtr_rubber_results` (120 rows, 138 columns)
  - `tbl_dtr_box_results` (88 rows, 114 columns)
  - `tbl_dtr_insole_results` (30 rows, 241 columns)
  - `tbl_dtr_bonding_info` (1,091 rows) & `tbl_dtr_bonding_points` (957 rows, 132 columns) & `tbl_dtr_bonding_spot_points` (236 rows)
  - `tbl_dtr_standards` (1,086 rows)
  - `tbl_dtr_materials_master` (778 rows)
  - `tbl_dtr_dsr` (154,426 rows)
  - `tbl_dtr_shoe_list` (74,741 rows)
  - `tbl_dtr_material_list` (41,374 rows)
- **Master Search Index (`tbl_dtr_unified_labrefs`):**
  - Consolidated index of 4,568 reports pre-populated with `test_items_json` for instantaneous combobox lookup in the Pre-Sync Dialog.

### 3. QC Lab Digital Test Reports Web Suite (`/dtr`)
- **Controller & Views:**
  - Added `app/Controllers/DtrController.php`, `app/Views/dtr/index.php`, `public/assets/css/dtr.css`, and `public/assets/js/dtr.js`.
  - Sidebar integration in `app/Views/partials/sidebar.php` linking to `/dtr`.
- **Computation Integrity (Strict 0% Difference):**
  - `UpdateMinMax`: Thickness 1..6 &rarr; $T_{\text{Min}}, T_{\text{Max}}, T_{\text{Ave}} = \text{Round}(\sum / 6, 2)$.
  - `ApplyStandardValuesSmart`: Thickness-based standards matching from `tbl_dtr_standards` where $T_{\text{Ave}} \in [T_{\text{MinC}}, T_{\text{MaxC}}]$.
  - Directional Averages: Length/Warp ($L$) and Width/Weft ($W$) averages and ratings.
  - 6-Fiber Colorfastness: Wool, Acrylic, Polyester, Nylon, Cotton, Acetate vs standard thresholds.
  - Evaluation priority: $\text{FAIL} > \text{TOLERABLE} > \text{PASS}$.
  - Whole Shoe Bonding spot rules: `TODDLER` (14 spots), `KIDS` (16 spots), `CHILDREN` (24 spots), `ADULT` (35 spots) with 2.5/2.0 kg/cm and 1.0 kg/cm FiTO limits.
  - All 6 standalone calculators: Weight ($g/m^2$), Specific Gravity, Compression Set, DIN Abrasion ($mm^3$), Ross Flex, and Shrinkage (%).
- **Print Preview & Official Report Generation (Access Parity):**
  - Added Print Preview modal for both Material Physical Reports (`Fabric Test Report Form - (w/ ASW)`, `Leather`, etc.) and Whole Shoe Bonding (`Report - LEFT` / `Report - RIGHT`).
  - Supports high-resolution printing and PDF generation via `window.print()` with official DATIAN SUBIC SHOES INC. QA stamp and signature blocks.
- **Machine Auto-Loader:**
  - Safely reads/copies `.xlsx`/`.csv` machine files from `\\192.168.32.45\OUTDATA\MATERIALS\` and `\BONDING\` without modifying network source files.

---

## Phase 186 - Synology Chat Notification Standard (2026-10-26)
- Standardized NEXUS-L message notifications in Synology Chat to discrete portal login alert.

## Phase 180 - FCM Web Token Isolation (2026-10-26)
- Prefixed mirrored web FCM tokens with `NEXUS_WEB:`. Added defense-in-depth filtering in service worker to reject RMS manufacturing alerts.

## Phase 212 - VF Test Monitoring live refresh + Inbox notice on sent e-mail
- vf_requests.js polls the data endpoint every 6 s (tab visible, no dialog open); ticks are kept; toasts on mail sent/failed, PDF arrived, Application No. arrived.
- tbl_vf_email_batches.notify_user_id (sender); VfTestRequestService::notifyMailSent posts to Inbox thread interlink_etrf when the Hub reports "sent"; MailRelayController status callback calls reconcileOutbox immediately for source "NEXUS-L VF-Request".

## Phase 213 - eTRF creation (bonding) + Application No. hardening (service 1.3.22 - 1.3.31)
- Grids: Report Submission (U form, data and documents) and the eTRF Application No. lookup use "Inhouse & 3rd party Lab Upload"; Copy as New, the printed PDF and the VF list sync use "Applicant". Both grids list a new Application; the eTRF lookup compares the grid before/after (new row for the SKU), never the newest row blindly.
- Print & Submit: InterLink validates on the SERVER, reloads the form and shows a short toast ("... is missing!"); every page now records such toasts in sessionStorage (init script) and the job fails with "InterLink said: ..." instead of waiting. Wait is 150 s (was ~35 s); a submit that never reaches THANK YOU and is not in the grid FAILS (never "done" without a number).
- Fixes found by running the service scripts on the live form: Factory ID filled via the Vendor lookup (lovPick) ; table Season drop-down chosen by option TEXT (7 = S27); Style No. is the style CODE (VN00114V = SKU minus colour code), derived in InterlinkController; Lab Location chosen from its list at the end of the fill (typing it = "Invalid"). Generic lovPick for all pick-lists was tried and reverted (it blanked other boxes).
- Link: EtrfAppNos::linkToLabRef() writes tbl_dtr_unified_labrefs.tr_no + etrf_<key>_app (+hist) + DTR form TR/ETRFNo; used by the service callback. DTP20261111SHB4-L linked to A006036440 (created in the browser pane).
- Notifications: toast/Inbox/Synology show only a real A-number ("not read" otherwise), never the lab reference. VF Test Monitoring polls every 6 s; Inbox message when the Hub reports the reply e-mail sent.
- Outlook Attachment Sync 1.2.5 / 1.1.5: one retry on COM "RPC unavailable". Extension 1.8.0: Export InterLink session; service 1.3.21+: Import session.

## Phase 214 - Report Submission: change the file of an APPROVED report
- Cause: the page re-attached the signed copy over a browsed file (setAttachedPdf -> checkApproval -> attachSigned), and the service submit always read <file_key>_signed.pdf from the server, so a browsed file never went to InterLink.
- ApprovalController::replaceFile (POST dtr/approval/replace-file): for a report with status approved, stores the chosen PDF as writable/approvals/<file_key>_user_<time>.pdf (older copies kept), records user_file / user_file_name / user_file_by / user_file_at on tbl_dtr_report_approvals (migration 2026-11-13-000001 + lazy ensure); {clear:1} returns to the signed report. The approval (who signed, when) is untouched.
- App\Libraries\ApprovalFiles::chosen(): user's file if present, else the signed copy. Used by InterlinkServiceController::submit (the file sent + its name), service verify and ApprovalController::verify (sha256 of the file that is sent). GET dtr/approval/file/<key>?v=user serves it.
- interlink.js: Browse on an approved report uploads the copy and attaches it (flag replaced); attachSigned/restoreApproval restore it after a reload; "Use the signed report again" button (#uf-apv-use-signed); nxPdfName keeps the user's file name. interlink_center.js extension path fetches ?v=user when set.

## Phase 215 - VF Test Monitoring: Transit Monitoring + Request Material Monitoring (tabs)
- Tabs on lab-monitoring/vf-requests: "Email Submission" (the old page), "Transit Monitoring", "Request Material Monitoring" (public/assets/js/vf_monitor_tabs.js).
- App\Libraries\VfTransitService: tbl_vf_shipments / tbl_vf_shipment_items. An e-mail with an SF tracking number + a request table (MTL# / TR#) makes ONE shipment from the NEWEST message of a forwarded chain only (first SF number + first table after it); rows link to requests by Application No. (generated or tr_no, also Excel HISTORY rows) else Prod Code + MTL#; the request gets container_no = tracking no., stage IN_TRANSIT. Mails older than 7 days are imported closed (no alarms). More than 40 rows / 3 numbers = a chain read wrongly: skipped (event TRANSIT_EMAIL_SKIPPED).
- Check every 3 h from the e-mail time (grid anchor+3h*k). SF's waybill page shows a CAPTCHA (never solved/bypassed): a person presses "Check on SF" (number copied, page opens) and "Record check"; a changed status / delivered raises the alarm (UPDATE chip, badge, toast, beep) until "Seen" or the e-mail is queued.
- "Send to e-mail": the lab e-mail (sample shoes sent today + tracking no. + table of the ticked requests + signature) via MailOutbox; settings vf.transit_greeting / vf.transit_to / vf.transit_cc.
- Request Material Monitoring status: SENDING / IN_TRANSIT / TESTING / DONE / FOLLOW_UP. Lab-received parser: only the FIRST report table of a mail; a non-date text in Expected Due Date = a remark -> stage FOLLOW_UP, expected_due_remark, fail_reason; certificate e-mail body kept (cert_email_*), PDF = certificate.
- LESSON (data incident, repaired): the first version read every table/number of a forwarded chain and wrote 51 requests + 9 shipments; repaired by SQL (the untouched requests never had a container_no). Always dry-run a mail parser on the real e-mails first.

### Phase 215b - Transit check page in Settings + Material Monitoring regrouped
- Settings group "vf": `vf.transit_url` (supports `{tracking}`), `vf.transit_carrier`, `vf.transit_pattern`, `vf.transit_hours`, greeting/to/cc. `VfTransitService` helpers read them; the "Check on ..." button, help text and tracking regex follow the settings.
- Request Material Monitoring: one sub-tab per status (Sending / In transit / Testing / Done / For follow-up), rows grouped by the request e-mail (batch; "Imported history" for Excel), lazy children, roomy table padding.

### Phase 216 - KC Test request e-mails (CPSIA locked)
- Outlook Attachment Sync admin 1.2.9 / client 1.1.8: new setting "Keep large inline pictures for categories" (default `KC TEST`, pictures >= 25 KB). Only that category uploads a table pasted as a picture; CPSIA and every other category behave as before.
- NEXUS-L: `VfImageTable` reads such a picture with Tesseract (English), takes Prod Code from the picture and MTL#/style/CRD from the ERP production order (OCR mixes 0/O), then feeds the rows to the normal request ingest as a table. Runs only for KC uploads (record uid `KC...`) without a table in the text. Setting `vf.tesseract_path` (empty = auto-find).
- Request table header mapping also accepts simplified `颜色` (KC format has no State / TR No columns; State defaults to "Need to test").

### Phase 217 - KC Test workflow (KOTITI): sheets, Proceed, update e-mail, Lab received
- `VfKcService` (new): Invoice + Packing List built from the two sample workbooks in `app/Libraries/templates/kc/` (editable per batch in table `tbl_vf_kc_docs`; Amount/TOTAL cells stay formulas, `fullCalcOnLoad`, calcChain dropped). `buildBatchEmail` for a KC package uses the KC message and attaches the eTRF PDFs + both sheets.
- Email Submission > KC tab: buttons "Invoice / Packing List" (editor) and "Proceed -> Transit" (creates a shipment, carrier KC, requests IN_TRANSIT, lab KOTITI; tracking number optional).
- Transit Monitoring: KC shipments get "Send KC update e-mail" (tracking no. field, automatic attachments + any number of added proof files, "KC TEST UPDATE" body, To/CC from Settings vf.kc_transit_to/cc) and a "Lab received" trigger (requests -> LAB_RECEIVED, shipment closed).
- Routes `lab-monitoring/vf-requests/kc/*`. CPSIA code paths unchanged. Not yet: KOTITI tracker xlsx parsing for completion.
- KOTITI tracker xlsx (KOTITI Number / Pass-Fail / KC Number / Model Name = MTL#) now completes KC requests (newest number per MTL); KOTITI PDFs 82261011108759_1_1_0.pdf match the number 8226-1011-108759, the _1 file stays the certificate.
- Transit Monitoring divided into CPSIA / KC Test tabs (KC = carrier KC or KC package items).

### Phase 218 - Browser push (works with NEXUS-L closed)
- Audit: only in-page pop-ups existed; the Firebase web token path never subscribed browsers (only Android/RMS got push). Added real Web Push (VAPID, RFC 8291 encryption) with no extra package: `App\Libraries\WebPush` (keys in `writable/vapid_private.pem`, tables `tbl_push_subscriptions`, `tbl_push_log`), `PushController` (`push/key|subscribe|unsubscribe|test`), service worker `public/nexus-push-sw.js`, subscribe code in `app.js` (`window.nxEnablePush`; `inbox.js` calls it). HTTPS only (https://192.168.32.43:9443/NEXUS-L/).
- Events pushed: VF sync (new request, in transit, lab received, report received), transit "update" and "check due" (dedupe per event), InterLink job done/failed/sign-in, report approval requests/decisions, Inbox chat messages, Notifier app "push" channel (Lab Alerts). VF alerts are limited to roles that can open ERP Finish/Lab Monitoring.
- `php spark push:test` lists subscribed devices and sends a test.

- Shoe Bonding U60 Send report: up to 10 photos for Synology (graph card first, each photo its own message) and e-mail (`photos[]`). Upload accepts several files, the QR code stays usable for more photos, and the list is never reset by a scan or upload (only by opening a different Lab Ref). Files: dtr.js, dtr-photo-attach.js, DtrController::sendEmail, dtr/index.php.
- Send report (Synology + e-mail): image 1 defaults to the Lab Tracker thumbnail picture of that Lab Ref (generated snapshot only when there is none); Lab line parsed from DTP########SHA9-R.
- Send to Synology: the message text goes with the LAST image (earlier images get a short 'Image n/N - LabRef' caption); a single image carries the text.
- VF Test Monitoring: 'Export Summary (Excel)' button -> CPSIA / KC TEST / RSL TARGETED TEST / GB TEST RESULT workbook like the lab's CPSIA,RSL & KC TEST SUMMARY file (VfSummaryExport.php, route lab-monitoring/vf-requests/export-summary).
- Export Summary now uses the lab's own workbook as template (app/Libraries/templates/vf_summary/summary.xlsx): every original row/style/merge/date-row/year-title kept; later results fill blank cells; new requests appended in the same style (CPSIA under a date row, KC/RSL/GB under the year). The lab file has no formulas.
- Fast sidebar clicks: GET requests release the PHP session lock after auth (AuthFilter), so a left slow page no longer blocks the next; ux.js cancels the page's GET fetches + intervals when leaving (POST saves never cancelled), hides their error toasts.
- Lab Tracker thumbnails: dtr/bonding-image?thumb=1 = 320px JPEG cached 10 min in writable/cache/thumbs (missing = 5 min), picture index 5 min + one builder at a time, folder lookup cached 5 min, one client retry. Shoe Bonding (U60) Print/Preview Report: page 2 = the Lab Ref's report picture (preview, print and Download PDF).

## Inbox overhaul (Messenger-style) - 2026-11-14
- Security: groups are members-only on every route (thread, send, upload, file, react, edit, pin, search, media, details, settings, disappearing, triggered); conversation list no longer matches user 1 inside ';12;'; 1:1 queries exclude group rows ({PAIR}/{NG} placeholders in ChatStore); markTriggered limited to the chat; only real active accounts (no bots) can be group members; a disabled account cannot chat; uploads validated before any file is stored.
- Groups: per-member read position (tbl_group_members.last_read_message_id) - opening a group no longer marks it read for everyone; "Seen by"; add people, remove (group admin), leave (admin passes on), rename, mute; system lines ([SYS]); browser push to members (not muted, not sender) with click-to-open; react/edit/pin/bump/forward/search/media work in groups.
- Messenger features: Unsend (NEXUS-L chats), typing indicator, Active now, older messages on scroll-up (thread?before=), clickable links, chime/notification for new messages in other chats, push click opens the chat, forward to groups and with attachments.
- Fixes: tbl_messages.IsTriggered column was missing (theme effects replayed, markTriggered failed); disappearing messages now really delete after 24 h (NEXUS-L); failed send restores the typed text; no double send on fast Enter; edit keeps a reply's quote; self-chat no longer shows unread.

## SATRA + Warehouse PIC roles, file manager, share sync, activity log - 2026-11-14
- Roles: SATRA (full control of Laboratory / Other Documents), Warehouse PIC (full control of the Warehouse 3D Tracker). New module 'warehouse' (was under 'dtr'; every role that saw it keeps view). Roles::canManage(), LabDocRoles::canManage().
- Lab/Other Documents (files registers): POST labdocs/<code>/files/op - rename file/folder, cut/copy/paste files and folders, delete files and folders, new Word/Excel/text file. Right-click menu, Ctrl/Shift multi-select, Ctrl+C/X/V, F2, Delete. Editors keep New folder / Upload / Delete file.
- .130 share sync: Apache (SYSTEM) cannot open the share, so every change goes to tbl_share_ops and the share worker (php spark share:worker, runs as the Windows user via Startup "NEXUS-L Share Worker.vbs", restarts itself) applies it within ~2 s, strictly in order, with retries. Uploads and new folders now really reach the share (before: "not copied to the share").
- Logging: tbl_activity_log (ActivityLog::record) for every action + RequestLog after-filter for every POST; deletions also in tbl_dtr_delete_audit. Admin page: activity-log (filters, CSV).

## Report approval: one approver at a time (live) - 2026-11-14
- approve/<token>: the first approver holds the report (tbl_apv_presence, heartbeat every 2 s via POST approve/presence/<id>); others see "X is checking this report", controls locked, place in line. The holder sees who waits and can "Let in" exactly one (holder then waits). A holder silent for 12 s has left: the first in line gets it.
- decide() refuses anyone but the holder (409) and a second simultaneous decision (status='pending' in the UPDATE). After a decision, the pages still open show "already approved/returned by X at hh:mm, closes in 20 s" and go to the dashboard. The approvals pill refreshes every 8 s (was 20 s). Decisions and hand-overs are in the activity log.
- Late click on a decided approval link: the page says who approved / returned it and closes after the 20 s (used_token column keeps the spent link). The approvals pill refreshes every 3 s while it is showing.
- Shoe Bonding (U60) Generate Report: draws the Bonding Test-VF graph report (readings tables + curve from tbl_dtr_bonding_points UTF/FTO/FiTO point, *Graph, *Spots columns) as an A4 PNG (bond-graph.js), dialog saves it to the report picture folder (= Lab Tracker picture) and/or downloads. Report Designer > Graph report (PNG): draggable page + toolbox of columns + series (readings/curve/spot columns, colours); layout in tbl_report_layouts slug bonding-graph; endpoints dtr/bonding-graph-data, dtr/bonding-graph-layout, designer/graph-layout/save.

## Bonding PIC role - 2026-11-17
- Roles.php / LabDocRoles.php: new role "Bonding PIC" (aliases bondingpic, bondingincharge). Edit on bonding + dtr, view on Quality Insights, Analytics, Calendar, ERP Finish, Lab docs, Warehouse; nothing on Materials / Whole shoe / Approvals. Roles::canManage(role, "bonding") is true for it. Admin can tune cells under Settings > Users & roles.

## Garbled icons fix - 2026-11-17
- inbox.js, approval.js, settings.js, vf_monitor_tabs.js, warehouse-multi.js, BotController.php had UTF-8 text saved as cp1252 mojibake (e.g. "âœ“" for a tick): repaired, assets rebuilt. InterLink service 1.3.35: openEtrfFromHome waits up to 20 s for the Submit a Request list to fill before reporting a form missing.
- DtrController: the Whole Shoe tracker -L/-R rename no longer crashes the DTR page when two rows share one Lab Ref (second one becomes -L-2).
- Removed the "+ New Test" button from Material Testing and Whole Shoe Test pages.

## Speed pass - 2026-11-17
- Apache restarted: gzip now served (pages and js/css about 5-10x smaller).
- SQL: Dashboard ongoing-bonding list 3.8 s -> 1 ms (limit first, index-friendly collation); double COLLATE joins that blocked indexes fixed in Dashboard, Analytics, Dtr, Notifier, BotApprovals; indexes idx_mt_cat_date, idx_bt_coll_shut, idx_wt_coll (migration 2026-11-17-000001).
- WstController::syncFromTracker runs at most every 20 s; InterlinkController::ensureEtrfTables seeds at most every 5 min (cache).
- ux.js: timers of 3 s+ run 1 in 5 while the tab is hidden and 1 in 2 on slow / data-saver connections.
- Not done: DTR hidden-tab HTML (Material forms 470 KB) still ships in the page; gzip makes it small on the wire.
- Session: timeToUpdate 300 -> 0 (periodic id renewal made parallel background calls fail with session file errors = random 500s); login still renews the id.

## Warehouse: auto box code / date, Add Contents, box contents list - 2026-11-17
- Box Label drives Box Code (WarehouseLabel::code) and Date Range + FIFO month (WarehouseLabel::date; same rules in warehouse-contents.js). No date in the label: the date range is typed.
- WarehouseContentsController + routes warehouse-tracker/labrefs, box-items (+/add, /save), item-cols (/add, /hide). Pick Lab Refs from tbl_dtr_unified_labrefs by tested date + test group (Material / Bonding / Whole Shoe); stored in tbl_warehouse_box_items (new columns flags JSON, item_status, item_note, added_by/at, updated_by/at). Check columns live in tbl_warehouse_item_cols (QIMA, CFA/Loading seeded); an Admin adds / hides columns, no DB structure change.
- Box Code is a link (3D inspector and table): everyone can open the list; only Admin, Manager, Supervisor, Warehouse PIC, Bonding PIC, Encoding can add / tick / save. All saves go to the activity log.
- Warehouse box list: Status Transferred asks for the destination box (rack / layer / slot shown); the source row stays locked as "Transferred to ..." and the new box gets the row marked "Transferred from ..."; Remove button takes a wrongly picked Lab Ref out (not allowed for a transferred row); new columns moved_to/from_code/label, moved_at/by (schema key wh_items_schema_ok2); route box-items/remove.
- Analytics speed: the materials monthly query (EXISTS on tbl_interlink_reports with COLLATE on both sides) 0.9-1.5 s -> 18 ms, identical result (md5 checked); workload / encoding data cached 60 s. Analytics default page 1.27 s -> about 50 ms warm, 220-360 ms cold.

## Material Test Report - 2026-11-17
- Sidebar Lab Reports: "Material Tests" -> "Material Test Report".
- Access V20261002.accdb (linked tables = same 10 material result tables, identical columns) vs dbnexus: 78 missing reports inserted (fabric 17, leather 5, hardware 4, rubber 5, label 6, fthl 14, sklfm 1, wst 26) and 15 reports (fabric 12, box 3) where Access was newer were updated, old rows saved in tbl_dtr_import_backup (batch access-20261117). Index: php spark dtr:sync-index --materials [--refresh]. Fabric sheet: Gauge VF-A01 (2016/12), Martindale VF-C04 (2024/06) as in the Access form.
- Material sheets: description cells added (fabric / leather Martindale, Hydrolysis, Heat Aging, Taber; label and rubber Hydrolysis / Heat Aging) with the lab suggestions from Access (app/Views/dtr/lf_options.php). Fill-blanks merge from Access: 100 reports / 2009 cells filled (backup rows batch access-fill-20261117 in tbl_dtr_import_backup). Rubber DTP20261104RB-2 ref repaired. Known source duplicate: wst DTP20261030EG-3 appears twice in Access too.
- Material sheets, remaining Access fields: Machine ID cell under the test name (MCTensile / MCBursting / MCOven / MCHydrolysis / MCHydro), ERP / SARGE / Tracker / Lab comments row under Comments (all 10 sheets), Foxing/Heel Abrasion Crocking and Ink adhesion description + rating cells, Webbing Lace Abrasion description cells (txt1-6); suggestion lists in app/Views/dtr/lf_options.php. Not added: hardware Shrinkage12_R / Shrinkage34_R (12 rows in Access).

## Material Test Report: Material PIC, read-only + Edit, accurate view, Send report - 2026-11-17
- New role "Material PIC" (Roles.php, LabDocRoles.php; canManage materials). Only Admin and Material PIC can edit the material report sheets: the sheet opens read only (View only tag), the Edit button unlocks it (Save / Refresh appear), a different record or a save locks it again; DtrController::save refuses material saves from anyone else (403).
- The sheet shows the SAVED ratings and overall result of the Lab Ref (it used to re-calculate them on open: DTP20261030FBU-5 Elongation showed FAIL, the lab saved TOLERABLE).
- Send report (Synology / e-mail) for a material Lab Ref attaches the lab report form filled with that Lab Ref (ReportPdf::material -> page image, GET dtr/material-report-image), further pages become pictures, up to 10 photos as in Bonding; the message uses the saved overall result and the date without time.
- tools/pdf_pages.py adds the lab user site-packages to its path: PyMuPDF was invisible to the web server (this also fixes the report page images of the approval screens).
- Lab Tracker > Lab Material Test Collection (bench log): the Lab Ref No is a link; click opens the Material Test Report with the form chosen from the classification of that Lab Ref (Fabric / Leather / Foxing Tape & Heel Logo ...) and its record loaded; a Whole Shoe ref opens the Whole Shoe report; a grouped ref of the bench log (DTP20261027F1-1-4) opens the first report of the group.
- Report Designer > Pull Test Graphs tab: designer.js now shows / hides #dsg-pane-pull with the other tabs (the pane stayed hidden, the tab looked empty). Verified: 7 report options in Shoe Bonding > Generate Report, 6 pull-test designers (up to 3 tables, drag and drop columns), data endpoint dtr/pull-graph-data returns Peak/SampleNo/Graph 1-30 of the Lab Ref.
- Shoe Bonding > Generate Report for a PULL test (Rivet, Eyelet, D-ring ...): uses the pull test Lab Ref (#pf_LabRefNo, e.g. DTP20261116EYA3-L), not the bonding Lab Ref; the layout that matches the letters of the ref (EY/DR eyelet, RVT/RIVET rivet, LLH lace holder, HP/HPT heel pull, TP tongue pull, VL velcro) is listed first; the picture is saved to that pull Lab Ref (same report picture folder as its Lab Tracker thumbnail); a pull test with no machine results gives a clear message.
- Hover over a report picture (Lab Tracker lists) or a shoe photo: a floating larger preview follows the mouse (full picture, Lab Ref caption), closes on leave / scroll / click.
- Restored after another editor overwrote Routes.php and DtrController.php: routes warehouse-tracker/labrefs, box-items/*, item-cols/*, dtr/material-report-image, approve/presence, activity-log, labdocs files/op; DtrController canEditMaterial + save guard + materialReportImage + index-friendly collation joins. Box Code click in the Warehouse 404 was the missing box-items route.
- Lab Tracker > Lab Material Test Collection: the tests are grouped by Lab Ref in collapsible lines like the Digital Test Report (caret, test count, overall result; the tests inside).
- Lab Tracker > Bonding Shoe Collection: the Edit button of a bonding row stays (even on a synced / locked row) while its Pull Test LabRefNo is empty or a dash; Edit puts the Pull Type tick boxes (Heel Pull, Eyelet, D-Ring, Rivet, the same as Add Bonding Shoe Collected) in that cell; Save calls POST dtr/add-pull-tests, which builds the pull Lab Ref from the row (DTP<date><HP|EY|DR|RVT><bld><line>-<side>) and copies Production Code, Style, Colorway, MTL, production / collection date, technician and test type from the row (pull_tests + tracker + unified index), skipping a pull type the shoe already has. The Edit column matching ignores the header sort arrows.
- Adding pull tests to a bonding row (Edit > Pull Type) is limited to Admin, Supervisor and Bonding PIC: DtrController::canAddPullTests (server 403 for others), button flag window.dtrCanAddPull.
- ERP Finish Monitoring: two tabs, Bonding (the original view, unchanged) and Whole Shoe (same view for form_slug wholeshoe lab refs, one line per lab ref, with the Whole Shoe / Product / Product (Adult) / Material TR numbers from tbl_dtr_unified_labrefs etrf_<key>_app). lab-monitoring/data?kind=wholeshoe; Submit to ERP stays on the Bonding tab (the ERP PC enters bonding reports); the ERP status dropdown of a whole shoe line updates every row of that lab ref.
