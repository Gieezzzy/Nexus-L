---
type: "CodebaseIndex"
tags: [obsidian, graphify, icm, codebase-index, nexus-l, interlink, dtr, qc-lab]
updated: "2026-10-29"
---

# NEXUS-L - Codebase Index

[[ICM_CHANGELOG]] | Knowledge Graph: `D:\html\graphify.html` | Canvas: `_docs/obsidian/graph.canvas`

NEXUS-L is the laboratory intelligence and execution system built on CodeIgniter 4, featuring real-time messaging, Intertek InterLink 2.0 integration, and the QC Lab Digital Test Reports (DTR) test suite.

## Main Controllers & Components

| File | Responsibility |
| :--- | :--- |
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
| **Master Index** | `tbl_dtr_unified_labrefs` | 4,568 unified test reports indexed with JSON test item results for instant Pre-Sync Dialog ComboBox lookup. |
| **Masters & DSR** | `tbl_dtr_standards`, `tbl_dtr_materials_master`, `tbl_dtr_dsr`, `tbl_dtr_shoe_list`, `tbl_dtr_material_list` | Reference data for automatic material, shoe, and thickness-dependent standards lookups. |

## Machine Data Folders (`\\192.168.32.45\OUTDATA\`)
- `MATERIALS\TENSILE\`: Tensile strength & elongation curves.
- `MATERIALS\TROUSER TEAR\`: Trouser/wing tear data.
- `MATERIALS\STITCH TEAR\`: Stitch tear readings.
- `MATERIALS\LAMINAR\`: Laminar peel dry/wet data.
- `BONDING\`: Whole shoe bonding test files (`U60.xls` and CSV).
- *Strict Rule:* Network files are read-only; never modified or deleted.


## Status (2026-10-29)
- NEXUS-L is detached from Access: `dbnexus` is the only database; reports print from it (ICM Phase 196).
- Nothing is saved in InterLink without an approved, signed report (ICM Phases 194 and 197).
