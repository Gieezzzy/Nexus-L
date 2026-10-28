# Graph Report - NEXUS-L  (2026-10-28)

## Corpus Check
- 184 files · ~358,040 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 38 file(s) not represented in the graph (top: .css 18, (none) 16, .canvas 1)

## Summary
- 992 nodes · 1652 edges · 129 communities (18 shown, 111 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 69 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `22857181`
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
- DtrController
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
- CodeIgniter\CLI\BaseCommand
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
- DesignerController
- manifest.json
- assets/js/brain.js
- 2026-10-27-000009_CreateMachineMaps.php
- CreateMessagesTable
- CreateNexusChatTables
- CreateNexusPushTokensAndThemesTables
- CreateInterlinkTables
- CreateCompleteInterlinkSchemaInDbNexus
- AddFullLiveInterlinkFieldsToDbNexus
- CreateReportLayouts
- CreateAppSettingsAndBondingRules
- CreateInterlinkSyncCenter
- CreateBrain
- ExampleMigration
- ICM Record: NEXUS-L

## God Nodes (most connected - your core abstractions)
1. `ChatStore` - 75 edges
2. `InboxController` - 43 edges
3. `BotController` - 41 edges
4. `InterlinkController` - 36 edges
5. `NexusStore` - 28 edges
6. `RmsStore` - 27 edges
7. `MachineFileReader` - 21 edges
8. `DesignerController` - 20 edges
9. `Auth` - 19 edges
10. `UserModel` - 19 edges

## Surprising Connections (you probably didn't know these)
- `ConfigReader` --inherits--> `App`  [EXTRACTED]
  tests/_support/Libraries/ConfigReader.php → app/Config/App.php
- `BotController` --inherits--> `BaseController`  [EXTRACTED]
  app/Controllers/BotController.php → app/Controllers/BaseController.php
- `DashboardController` --inherits--> `BaseController`  [EXTRACTED]
  app/Controllers/DashboardController.php → app/Controllers/BaseController.php
- `DesignerController` --inherits--> `BaseController`  [EXTRACTED]
  app/Controllers/DesignerController.php → app/Controllers/BaseController.php
- `DtrController` --inherits--> `BaseController`  [EXTRACTED]
  app/Controllers/DtrController.php → app/Controllers/BaseController.php

## Import Cycles
- None detected.

## Communities (129 total, 111 thin omitted)

### Community 0 - "InboxController"
Cohesion: 0.06
Nodes (24): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+16 more)

### Community 1 - "Auth"
Cohesion: 0.06
Nodes (10): Auth, Session, DashboardController, PasswordResetController, Auth, AuthLogModel, InterlinkReportModel, PasswordResetModel (+2 more)

### Community 2 - "ChatStore"
Cohesion: 0.08
Nodes (4): ChatStore, {closure#3}(), {closure#4}(), RmsCrypto

### Community 3 - "RmsStore"
Cohesion: 0.08
Nodes (4): Rms, RmsStore, FcmService, RmsDb

### Community 4 - "BaseController"
Cohesion: 0.08
Nodes (7): View, AuthController, BaseController, HelpController, Home, AuthFilter, GuestFilter

### Community 6 - "composer.json"
Cohesion: 0.06
Nodes (30): autoload, autoload-dev, psr-4, exclude-from-classmap, psr-4, config, optimize-autoloader, preferred-install (+22 more)

### Community 7 - "CodeIgniter\Config\BaseConfig"
Cohesion: 0.11
Nodes (13): ContentSecurityPolicy, Cookie, Cors, CURLRequest, Email, Encryption, Feature, Generators (+5 more)

### Community 9 - "App"
Cohesion: 0.13
Nodes (7): App, ExampleDatabaseTest, ExampleSessionTest, ExampleSeeder, ConfigReader, ExampleModel, HealthTest

### Community 11 - "package.json"
Cohesion: 0.11
Nodes (18): author, description, devDependencies, tailwindcss, @tailwindcss/cli, directories, test, keywords (+10 more)

### Community 12 - "SynologyChat"
Cohesion: 0.21
Nodes (3): Services, Synology, SynologyChat

### Community 16 - "Running Application Tests"
Cohesion: 0.18
Nodes (10): Creating Tests, Database Tests, Generating Code Coverage, PHPUnit XML Configuration, Requirements, Resources, Running Application Tests, Running the tests (+2 more)

### Community 19 - "CodeIgniter 4 Application Starter"
Cohesion: 0.25
Nodes (7): CodeIgniter 4 Application Starter, Important Change with index.php, Installation & updates, Repository Management, Server Requirements, Setup, What is CodeIgniter?

### Community 23 - "debug.js"
Cohesion: 0.53
Nodes (4): getFirstChildWithTagName(), getHash(), init(), showTab()

### Community 26 - "Routes.php"
Cohesion: 0.53
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 32 - "CodeIgniter\CLI\BaseCommand"
Cohesion: 0.06
Nodes (10): BrainAsk, BrainMaintain, BrainSeed, DeployCheck, InterlinkWork, BrainSeed2, BrainSeed3, BrainSeed4 (+2 more)

### Community 79 - "background.js"
Cohesion: 0.33
Nodes (10): checkSubmitted(), endJob(), fillAndAttach(), getJob(), inPage(), key(), pressSubmit(), setJob() (+2 more)

### Community 86 - "manifest.json"
Cohesion: 0.20
Nodes (9): background, service_worker, content_scripts, description, host_permissions, manifest_version, name, permissions (+1 more)

### Community 137 - "ICM Record: NEXUS-L"
Cohesion: 0.10
Nodes (19): 1. "Generate Report" button beside Browse (Tab 2 · Report Attachment, `JOB_DUMMY_004`), 1. Intertek InterLink 2.0 Full Integration & Live Parity, 1. Vector/Raster Shoe Contour Sketch Diagram with Spot Readings & Leader Lines, 2. Complete Database Migration (#Digital Test Report &rarr; MySQL `dbnexus`), 2. Fix: wrong DTR record opened for the report PDF, 2. Official VF Laboratory Report Header & 3-Part Evaluation Table, 3. Build, 3. QC Lab Digital Test Reports Web Suite (`/dtr`) (+11 more)

## Knowledge Gaps
- **2 isolated node(s):** `@tailwindcss/cli`, `tailwindcss`
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 374 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **111 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `BaseController` connect `BaseController` to `InboxController`, `Auth`, `InterlinkController`, `DtrController`, `DesignerController`, `BotController`?**
  _High betweenness centrality (0.185) - this node is a cross-community bridge._
- **What connects `@tailwindcss/cli`, `tailwindcss` to the rest of the system?**
  _2 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `InboxController` be split into smaller, more focused modules?**
  _Cohesion score 0.06315789473684211 - nodes in this community are weakly interconnected._
- **Why does `InboxController` connect `InboxController` to `BaseController`?**
  _High betweenness centrality (0.121) - this node is a cross-community bridge._
- **Should `Auth` be split into smaller, more focused modules?**
  _Cohesion score 0.056338028169014086 - nodes in this community are weakly interconnected._
- **Why does `ChatStore` connect `ChatStore` to `InboxController`, `NexusStore`, `RmsStore`?**
  _High betweenness centrality (0.097) - this node is a cross-community bridge._
- **Should `ChatStore` be split into smaller, more focused modules?**
  _Cohesion score 0.07626582278481013 - nodes in this community are weakly interconnected._