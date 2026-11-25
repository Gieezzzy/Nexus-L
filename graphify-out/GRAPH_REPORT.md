# Graph Report - NEXUS-L  (2026-10-29)

## Corpus Check
- 220 files · ~405,255 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 40 file(s) not represented in the graph (top: .css 20, (none) 16, .canvas 1)

## Summary
- 1155 nodes · 2045 edges · 146 communities (20 shown, 126 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 148 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `61887aa4`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- InboxController
- Auth
- ChatStore
- RmsStore
- BaseController
- CodeIgniter\Database\Migration
- composer.json
- CodeIgniter\Config\BaseConfig
- InterlinkController
- App
- NexusStore
- package.json
- SynologyChat
- Filters.php
- DesignerController
- Paths
- Running Application Tests
- Cache.php
- Toolbar.php
- CodeIgniter 4 Application Starter
- Exceptions.php
- BotController
- debug.js
- interlink-bot.js
- Kint.php
- Routes.php
- Database
- Config/Validation.php
- Images.php
- Logger.php
- Mimes
- Commands/BrainSeed.php
- Autoload.php
- ForeignCharacters.php
- Modules.php
- Publisher.php
- Routing.php
- DocTypes.php
- Hostnames.php
- Optimize.php
- WorkerMode.php
- background.js
- Format.php
- MachineFileReader
- DtrController
- manifest.json
- assets/js/brain.js
- ApprovalController
- 2026-10-27-000009_CreateMachineMaps.php
- CreateMessagesTable
- CreateNexusChatTables
- CreateNexusPushTokensAndThemesTables
- CreateInterlinkTables
- CreateCompleteInterlinkSchemaInDbNexus
- ReportTests
- CreateReportLayouts
- CreateAppSettingsAndBondingRules
- CreateInterlinkSyncCenter
- CreateBrain
- ExampleMigration
- .mod
- CreateReportApprovals
- ApprovalLinksExpire
- ApprovalRequiredSigner
- K
- access-report-layout.ps1
- report_templates/README.md
- Session.php
- ICM Record: NEXUS-L
- CreateBotSessions
- Cookie.php

## God Nodes (most connected - your core abstractions)
1. `ChatStore` - 75 edges
2. `BotController` - 44 edges
3. `InboxController` - 43 edges
4. `InterlinkController` - 37 edges
5. `ApprovalController` - 30 edges
6. `NexusStore` - 28 edges
7. `RmsStore` - 27 edges
8. `MachineFileReader` - 21 edges
9. `DesignerController` - 20 edges
10. `Auth` - 20 edges

## Surprising Connections (you probably didn't know these)
- `1. Final sync, then the sync is retired` --references--> `DeployCheck`  [INFERRED]
  _docs/ICM_CHANGELOG.md → app/Commands/DeployCheck.php
- `1. Approval before InterLink` --references--> `ApprovalController`  [INFERRED]
  _docs/ICM_CHANGELOG.md → app/Controllers/ApprovalController.php
- `2. InterLink sender extension 1.3.x` --references--> `pullGrid()`  [INFERRED]
  _docs/ICM_CHANGELOG.md → tools/interlink-sender/background.js
- `ConfigReader` --inherits--> `App`  [EXTRACTED]
  tests/_support/Libraries/ConfigReader.php → app/Config/App.php
- `ApprovalController` --inherits--> `BaseController`  [EXTRACTED]
  app/Controllers/ApprovalController.php → app/Controllers/BaseController.php

## Import Cycles
- None detected.

## Communities (146 total, 126 thin omitted)

### Community 0 - "InboxController"
Cohesion: 0.06
Nodes (24): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+16 more)

### Community 1 - "Auth"
Cohesion: 0.05
Nodes (9): Auth, AuthController, PasswordResetController, Auth, AuthLogModel, InterlinkReportModel, PasswordResetModel, RememberTokenModel (+1 more)

### Community 2 - "ChatStore"
Cohesion: 0.08
Nodes (4): ChatStore, {closure#3}(), {closure#4}(), RmsCrypto

### Community 3 - "RmsStore"
Cohesion: 0.08
Nodes (4): Rms, RmsStore, FcmService, RmsDb

### Community 4 - "BaseController"
Cohesion: 0.09
Nodes (7): View, BaseController, DashboardController, HelpController, Home, AuthFilter, GuestFilter

### Community 6 - "composer.json"
Cohesion: 0.06
Nodes (30): autoload, autoload-dev, psr-4, exclude-from-classmap, psr-4, config, optimize-autoloader, preferred-install (+22 more)

### Community 7 - "CodeIgniter\Config\BaseConfig"
Cohesion: 0.12
Nodes (12): ContentSecurityPolicy, Cors, CURLRequest, Email, Encryption, Feature, Generators, Honeypot (+4 more)

### Community 9 - "App"
Cohesion: 0.12
Nodes (7): App, ExampleDatabaseTest, ExampleSessionTest, ExampleSeeder, ConfigReader, ExampleModel, HealthTest

### Community 11 - "package.json"
Cohesion: 0.11
Nodes (18): author, description, devDependencies, tailwindcss, @tailwindcss/cli, directories, test, keywords (+10 more)

### Community 12 - "SynologyChat"
Cohesion: 0.19
Nodes (3): Services, Synology, SynologyChat

### Community 16 - "Running Application Tests"
Cohesion: 0.18
Nodes (10): Creating Tests, Database Tests, Generating Code Coverage, PHPUnit XML Configuration, Requirements, Resources, Running Application Tests, Running the tests (+2 more)

### Community 19 - "CodeIgniter 4 Application Starter"
Cohesion: 0.25
Nodes (7): CodeIgniter 4 Application Starter, Important Change with index.php, Installation & updates, Repository Management, Server Requirements, Setup, What is CodeIgniter?

### Community 22 - "BotController"
Cohesion: 0.07
Nodes (3): BotController, BotSessions, Brain

### Community 23 - "debug.js"
Cohesion: 0.53
Nodes (4): getFirstChildWithTagName(), getHash(), init(), showTab()

### Community 26 - "Routes.php"
Cohesion: 0.53
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 32 - "Commands/BrainSeed.php"
Cohesion: 0.05
Nodes (11): BrainAsk, BrainMaintain, BrainSeed, DeployCheck, InterlinkWork, BrainSeed2, BrainSeed3, BrainSeed4 (+3 more)

### Community 79 - "background.js"
Cohesion: 0.17
Nodes (21): approvalProblem(), bounded(), checkSubmitted(), endJob(), fillAndAttach(), getJob(), goToGridFromHome(), gotoPageAndRead() (+13 more)

### Community 86 - "manifest.json"
Cohesion: 0.20
Nodes (9): background, service_worker, content_scripts, description, host_permissions, manifest_version, name, permissions (+1 more)

### Community 88 - "ApprovalController"
Cohesion: 0.12
Nodes (4): ApprovalController, {closure#1}(), {closure#2}(), ReportPdf

### Community 137 - "ICM Record: NEXUS-L"
Cohesion: 0.06
Nodes (33): 1. Approval before InterLink, 1. Approval gate in the sender extension (v1.4.0), 1. Bonding PASS / TOLERABLE / FAIL now matches Access exactly, 1. Final sync, then the sync is retired, 1. "Generate Report" button beside Browse (Tab 2 · Report Attachment, `JOB_DUMMY_004`), 1. Intertek InterLink 2.0 Full Integration & Live Parity, 1. Vector/Raster Shoe Contour Sketch Diagram with Spot Readings & Leader Lines, 2. Complete Database Migration (#Digital Test Report &rarr; MySQL `dbnexus`) (+25 more)

## Knowledge Gaps
- **2 isolated node(s):** `@tailwindcss/cli`, `tailwindcss`
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 410 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **126 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `BaseController` connect `BaseController` to `InboxController`, `Auth`, `InterlinkController`, `DesignerController`, `DtrController`, `BotController`, `ApprovalController`?**
  _High betweenness centrality (0.131) - this node is a cross-community bridge._
- **What connects `@tailwindcss/cli`, `tailwindcss` to the rest of the system?**
  _2 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `InboxController` be split into smaller, more focused modules?**
  _Cohesion score 0.06315789473684211 - nodes in this community are weakly interconnected._
- **Why does `ChatStore` connect `ChatStore` to `InboxController`, `NexusStore`, `RmsStore`?**
  _High betweenness centrality (0.099) - this node is a cross-community bridge._
- **Should `Auth` be split into smaller, more focused modules?**
  _Cohesion score 0.05189189189189189 - nodes in this community are weakly interconnected._
- **Why does `InboxController` connect `InboxController` to `BaseController`?**
  _High betweenness centrality (0.091) - this node is a cross-community bridge._
- **Should `ChatStore` be split into smaller, more focused modules?**
  _Cohesion score 0.07626582278481013 - nodes in this community are weakly interconnected._