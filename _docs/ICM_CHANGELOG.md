# ICM Record: NEXUS-L

[[OBSIDIAN_CODEBASE_INDEX]] | Knowledge Graph: `E:\html\graphify.html` | Canvas: `_docs/obsidian/graph.canvas`

---

## Phase 202 - Anime.js Ultra-Lightweight Micro-Interaction & Animation Engine (2026-10-31)
- **High-Performance Motion Engine (`anime.min.js` + `window.nxMotion`):**
  - Integrated Anime.js (17 KB ultra-lightweight library) to power buttery, native-feeling 60 FPS transitions without heavy CSS clutter or memory overhead on resource-constrained lab PCs.
  - Strict hardware-accelerated GPU execution: strictly animates composited `transform` and `opacity` properties (avoiding layout thrashing, CPU spikes, or reflows).
  - Automatically respects OS accessibility settings (`prefers-reduced-motion: reduce`) with instant fallbacks and pauses during background tabs (`document.hidden`).
- **LEXIS AI Assistant Micro-Interactions (`public/assets/js/bot.js`):**
  - **Panel Open / Close Spring:** Replaced abrupt toggling with an elastic spring-up from the bottom-right launcher (`easeOutBack`, 260ms) and snappy fade-down collapse (`easeInQuad`, 180ms).
  - **FAB Launcher Micro-Motion:** Added spring-press reaction on click (`scale: 0.91 -> 1`), subtle hover elevation, and an ambient idle breath cycle (gentle 1.06x scale pulse every 24s) to remind users the assistant is active.
  - **Staggered Question Chips:** Suggestion chips enter sequentially with a 28ms staggered pop (`anime.stagger(28)`), providing polished interactive cues.
  - **Smooth Message Bubbles:** Incoming conversational replies slide up gracefully (`translateY: [10, 0]`, 220ms).
  - **Rhythmic Typing Dots:** Added lightweight 3-dot waving indicator while LEXIS AI performs queries or calculations.
- **Site-Wide Navigation & Element Cascading (`public/assets/js/ux.js` & `dash.css`):**
  - **Staggered Card Entrance:** Main dashboard cards, statistics tiles, and hero containers smoothly cascade into view on navigation (`nxMotion.staggerIn`).
  - **Tab Transitions:** Smooth cross-fade and slide when switching tabs across Brain, DTR test categories, and Settings panels (`nxMotion.switchTab`).
  - **Toast & Modal Springs:** In-app notifications (`nxToast`) and confirmation dialogs (`nxConfirm`) animate in with spring physics and dismiss cleanly.
  - **Interactive Button Reactions:** Applied micro active-scale compression (`transform: scale(0.97)`) on buttons, tabs, and navigation links.

---

## Phase 201 - Bot Read-Only Database Queries, Synology Signatory Reminders & 200 QC Problem Knowledge (2026-10-31)
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
