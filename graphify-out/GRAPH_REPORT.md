# Graph Report - NEXUS-L  (2026-10-27)

## Corpus Check
- 136 files · ~146,988 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 27 file(s) not represented in the graph (top: (none) 16, .css 8, .canvas 1)

## Summary
- 720 nodes · 1174 edges · 86 communities (15 shown, 71 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 46 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `6602319e`
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
- ChatStore.php
- debug.js
- interlink-bot.js
- Kint.php
- Routes.php
- Database
- Config/Validation.php
- Images.php
- Logger.php
- Mimes
- Autoload.php
- ForeignCharacters.php
- Modules.php
- Publisher.php
- Routing.php
- DocTypes.php
- Hostnames.php
- Optimize.php
- WorkerMode.php
- Format.php
- ICM Record: NEXUS-L

## God Nodes (most connected - your core abstractions)
1. `ChatStore` - 75 edges
2. `InboxController` - 43 edges
3. `NexusStore` - 28 edges
4. `RmsStore` - 27 edges
5. `InterlinkController` - 22 edges
6. `Auth` - 19 edges
7. `UserModel` - 19 edges
8. `Session` - 15 edges
9. `PasswordResetController` - 15 edges
10. `BaseController` - 12 edges

## Surprising Connections (you probably didn't know these)
- `ConfigReader` --inherits--> `App`  [EXTRACTED]
  tests/_support/Libraries/ConfigReader.php → app/Config/App.php
- `DashboardController` --inherits--> `BaseController`  [EXTRACTED]
  app/Controllers/DashboardController.php → app/Controllers/BaseController.php
- `DtrController` --inherits--> `BaseController`  [EXTRACTED]
  app/Controllers/DtrController.php → app/Controllers/BaseController.php
- `InboxController` --inherits--> `BaseController`  [EXTRACTED]
  app/Controllers/InboxController.php → app/Controllers/BaseController.php
- `InterlinkController` --inherits--> `BaseController`  [EXTRACTED]
  app/Controllers/InterlinkController.php → app/Controllers/BaseController.php

## Import Cycles
- None detected.

## Communities (86 total, 71 thin omitted)

### Community 0 - "InboxController"
Cohesion: 0.07
Nodes (24): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+16 more)

### Community 1 - "Auth"
Cohesion: 0.06
Nodes (10): Auth, Session, DashboardController, PasswordResetController, Auth, AuthLogModel, InterlinkReportModel, PasswordResetModel (+2 more)

### Community 3 - "RmsStore"
Cohesion: 0.07
Nodes (4): Rms, RmsStore, FcmService, RmsDb

### Community 4 - "BaseController"
Cohesion: 0.09
Nodes (6): View, AuthController, BaseController, Home, AuthFilter, GuestFilter

### Community 5 - "CodeIgniter\Database\Migration"
Cohesion: 0.08
Nodes (8): CreateAuthTables, CreateMessagesTable, CreateNexusChatTables, CreateNexusPushTokensAndThemesTables, CreateInterlinkTables, CreateCompleteInterlinkSchemaInDbNexus, AddFullLiveInterlinkFieldsToDbNexus, ExampleMigration

### Community 6 - "composer.json"
Cohesion: 0.06
Nodes (30): autoload, autoload-dev, psr-4, exclude-from-classmap, psr-4, config, optimize-autoloader, preferred-install (+22 more)

### Community 7 - "CodeIgniter\Config\BaseConfig"
Cohesion: 0.11
Nodes (13): ContentSecurityPolicy, Cookie, Cors, CURLRequest, Email, Encryption, Feature, Generators (+5 more)

### Community 9 - "App"
Cohesion: 0.12
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

### Community 22 - "ChatStore.php"
Cohesion: 0.18
Nodes (3): {closure#3}(), {closure#4}(), RmsCrypto

### Community 23 - "debug.js"
Cohesion: 0.53
Nodes (4): getFirstChildWithTagName(), getHash(), init(), showTab()

### Community 137 - "ICM Record: NEXUS-L"
Cohesion: 0.15
Nodes (11): 1. Intertek InterLink 2.0 Full Integration & Live Parity, 2. Complete Database Migration (#Digital Test Report &rarr; MySQL `dbnexus`), 3. QC Lab Digital Test Reports Web Suite (`/dtr`), ICM Record: NEXUS-L, Phase 180 - FCM Web Token Isolation (2026-10-26), Phase 186 - Synology Chat Notification Standard (2026-10-26), Phase 190 - InterLink 2.0 Integration & Digital Test Report (QC Lab) Suite Migration (2026-10-27), Database Architecture (`dbnexus` on `192.168.32.43`) (+3 more)

## Knowledge Gaps
- **2 isolated node(s):** `@tailwindcss/cli`, `tailwindcss`
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 271 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **71 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `ChatStore` connect `ChatStore` to `InboxController`, `NexusStore`, `RmsStore`, `ChatStore.php`?**
  _High betweenness centrality (0.126) - this node is a cross-community bridge._
- **What connects `@tailwindcss/cli`, `tailwindcss` to the rest of the system?**
  _2 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `InboxController` be split into smaller, more focused modules?**
  _Cohesion score 0.06659056316590563 - nodes in this community are weakly interconnected._
- **Why does `BaseController` connect `BaseController` to `InboxController`, `Auth`, `DtrController`, `InterlinkController`?**
  _High betweenness centrality (0.105) - this node is a cross-community bridge._
- **Should `Auth` be split into smaller, more focused modules?**
  _Cohesion score 0.05755693581780538 - nodes in this community are weakly interconnected._
- **Why does `InboxController` connect `InboxController` to `RmsStore`, `BaseController`?**
  _High betweenness centrality (0.100) - this node is a cross-community bridge._
- **Should `ChatStore` be split into smaller, more focused modules?**
  _Cohesion score 0.09995477159656264 - nodes in this community are weakly interconnected._