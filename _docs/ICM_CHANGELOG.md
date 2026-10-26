# ICM Record: NEXUS-L

[[OBSIDIAN_CODEBASE_INDEX]] | Knowledge Graph: `D:\html\graphify.html` | Canvas: `_docs/obsidian/graph.canvas`

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
