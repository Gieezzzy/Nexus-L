# Report forms (no Access needed at run time)

Each material report is two files with the same base name:

- `<Report>.pdf`  the lab's BLANK form, printed once from the Access report by `tools/access-report-templates.ps1`
- `<Report>.json` the Access report design (every text box: position, font, bound field / expression), made by `tools/access-report-layout.ps1`

`ReportPdf::material()` (app/Libraries/ReportPdf.php) calls `tools/build/render-report.js`, which writes the record's values from dbnexus
onto the blank form at those positions. Chinese text needs `writable/cache/fonts/cjk.ttf` (`python tools/make-cjk-font.py`).

Changing a report form: edit it in Access, then re-run the two tools above for that report and replace its two files. dbnexus is the only
database NEXUS-L reads or writes; the Access files are not touched after the last sync (2026-10-28).
