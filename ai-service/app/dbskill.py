"""Live NEXUS-L data: Lexus reads the laboratory database (MariaDB dbnexus on this PC) to answer questions about reports.

    "DTP20261019F1-3"                                   the whole report: header, every filled-in reading and rating
    "how many failed this month", "bonding pass and fail in 2026", "ilan ang fail na fabric ngayong buwan"
    "list the failed leather reports", "latest reports of supplier Datian", "reports tested by Kian"
    "material code VF123", "style UA LX Old Skool", "interlink report 12345", "what tables does NEXUS-L have"

Read only, by construction: every statement is a SELECT built here from fixed templates (the user's words only ever become quoted
values), the session is set READ ONLY before it runs, and only the report tables are reachable. It runs through the MariaDB
client installed with XAMPP, with the credentials of NEXUS-L's own .env; nothing leaves the PC.
"""
from __future__ import annotations

import datetime as dt
import logging
import os
import re
import subprocess
from pathlib import Path
from typing import Any

log = logging.getLogger("nexus.dbskill")
MYSQL = Path(os.environ.get("AI_MYSQL_EXE", r"C:\xampp\mysql\bin\mysql.exe"))
ENV = Path(__file__).resolve().parents[2] / ".env"
U = "tbl_dtr_unified_labrefs"
_cfg: dict[str, str] | None = None


def _conf() -> dict[str, str]:
    global _cfg
    if _cfg is None:
        _cfg = {}
        for line in ENV.read_text(encoding="utf-8", errors="ignore").splitlines():
            m = re.match(r"\s*database\.default\.(\w+)\s*=\s*'?([^'#]*?)'?\s*$", line)
            if m:
                _cfg[m.group(1)] = m.group(2).strip()
    return _cfg


def available() -> bool:
    return MYSQL.exists() and ENV.exists() and bool(_conf().get("database"))


def q(v: Any) -> str:
    """A value as a quoted SQL string literal."""
    s = str(v).replace("\\", "\\\\").replace("'", "\\'").replace("\x00", "")
    return f"'{s}'"


def like(v: str) -> str:
    return q("%" + re.sub(r"([%_])", r"\\\1", v) + "%")


def run(sql: str, timeout: float = 8.0) -> list[dict[str, str]]:
    """Rows of one SELECT as dicts (all values text, "" for NULL)."""
    if not re.match(r"^\s*(SELECT|SHOW)\b", sql, re.I) or ";" in sql:
        raise ValueError("only one SELECT is allowed")
    c = _conf()
    env = dict(os.environ, MYSQL_PWD=c.get("password", ""))
    args = [str(MYSQL), "-h", c.get("hostname", "127.0.0.1"), "-P", c.get("port", "3306"), "-u", c.get("username", ""), c["database"],
            "--batch", "--default-character-set=utf8mb4", "-e", f"SET SESSION TRANSACTION READ ONLY; {sql}"]
    r = subprocess.run(args, capture_output=True, timeout=timeout, env=env, creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0))
    if r.returncode != 0:
        raise RuntimeError(r.stderr.decode("utf-8", errors="ignore")[:300])
    lines = r.stdout.decode("utf-8", errors="ignore").replace("\r\n", "\n").split("\n")
    if not lines or not lines[0]:
        return []
    head = lines[0].split("\t")

    def val(x: str) -> str:
        return "" if x == "NULL" else x.replace("\\n", "\n").replace("\\t", "\t").replace("\\\\", "\\")
    return [dict(zip(head, map(val, ln.split("\t")))) for ln in lines[1:] if ln]


# ---------------------------------------------------------------------------------------------------- understanding the question

LABREF = re.compile(r"\b(DTP\d{6,8}[A-Z0-9]*(?:-[A-Z0-9]{1,4})*)\b", re.I)
CATEGORY = [  # word in the question -> form_category prefix
    (r"fabric|textile|mesh|canvas|lining fabric", "Fabric"), (r"leather|suede|nubuck", "Leather"),
    (r"bonding|whole ?shoe|u60|peel|sole adhesion", "Whole Shoe"), (r"hardware|eyelet|grommet|zip(?:per)?|rivet", "Hardware"),
    (r"rubber|outsole", "Rubber"), (r"foxing|heel logo", "Foxing"), (r"label|woven|transfer", "Woven"),
    (r"webbing|shoe ?lace|laces|thread", "Webbing"), (r"sockliner|sock liner|foam", "Sockliner"), (r"insole", "Insole"),
    (r"carton|box", "Carton")]
RESULT = [(r"\bfail(?:s|ed|ing|ure|ures)?\b|\bbagsak\b|\breject", "Fail"), (r"\bpass(?:es|ed|ing)?\b|\bpasado\b", "Pass"),
          (r"\btolerable\b", "Tolerable")]
MONTHS = {m.lower(): i for i, m in enumerate(["January", "February", "March", "April", "May", "June", "July", "August", "September",
                                                "October", "November", "December"], 1)}
MONTHS.update({k[:3]: v for k, v in list(MONTHS.items())})
_COUNT = re.compile(r"\b(how many|how much|count|total|number of|ilan|ilang|summary|statistics|stats|tally|breakdown|rate|percent(?:age)?)\b", re.I)
_LIST = re.compile(r"\b(list|show|find|search|give me|display|latest|recent|newest|last \d+|pakita|ipakita|hanapin)\b", re.I)
_REPORTISH = re.compile(r"\b(reports?|lab ?refs?|lab references?|tests?|samples?|results?|fail\w*|pass\w*|tolerable|records?|submissions?|materials? tested|nexus|database|system)\b", re.I)
# a question about a test procedure or specification ("how many readings", "how many hours"), which the documents answer
_DOCISH = re.compile(r"\b(hours?|minutes?|seconds?|readings?|specimens?|cycles?|mm|cm|kg|newtons?|speed|temperature|humidity|times|layers?|"
                     r"steps?|procedure|method|requirement|standard value|conditioning|jaws?|weights? of|days? before)\b", re.I)
_SCHEMA = re.compile(r"\b(what|which|list)\b.*\b(tables?|database|columns?|fields?)\b.*\b(nexus|system|database|have|stored|are there)\b"
                     r"|\bwhere (is|are) .+ (stored|saved|kept)\b", re.I)


def _period(t: str) -> tuple[dt.date, dt.date, str] | None:
    today = dt.date.today()
    if re.search(r"\btoday|ngayong araw|ngayon araw\b", t):
        return today, today, "today"
    if re.search(r"\byesterday|kahapon\b", t):
        d = today - dt.timedelta(days=1)
        return d, d, "yesterday"
    if re.search(r"\bthis week|ngayong linggo\b", t):
        s = today - dt.timedelta(days=today.weekday())
        return s, today, "this week"
    if re.search(r"\blast week\b", t):
        s = today - dt.timedelta(days=today.weekday() + 7)
        return s, s + dt.timedelta(days=6), "last week"
    if re.search(r"\bthis month|ngayong buwan|this mon\b", t):
        return today.replace(day=1), today, f"this month ({today:%B %Y})"
    if re.search(r"\blast month|nakaraang buwan\b", t):
        e = today.replace(day=1) - dt.timedelta(days=1)
        return e.replace(day=1), e, f"last month ({e:%B %Y})"
    if re.search(r"\bthis year|ngayong taon\b", t):
        return dt.date(today.year, 1, 1), today, "this year"
    if re.search(r"\blast year\b", t):
        return dt.date(today.year - 1, 1, 1), dt.date(today.year - 1, 12, 31), "last year"
    m = re.search(r"\b(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\b\.?\s*(\d{4})?", t)
    if m and not (m.group(1) == "may" and not m.group(2)):          # "may" alone is the verb
        mo = MONTHS[m.group(1)[:3]]
        yr = int(m.group(2)) if m.group(2) else (today.year if mo <= today.month else today.year - 1)
        s = dt.date(yr, mo, 1)
        e = (s.replace(day=28) + dt.timedelta(days=4)).replace(day=1) - dt.timedelta(days=1)
        return s, e, s.strftime("%B %Y")
    y = re.search(r"\b(20[12]\d)\b", t)
    if y:
        yr = int(y.group(1))
        return dt.date(yr, 1, 1), dt.date(yr, 12, 31), str(yr)
    return None


_STOPNAME = (r"(?=\s+(?:this|last|in|for|with|that|which|and|or|from|since|na|ngayong|noong|reports?|tests?|results?|did|do|does|has|have|"
             r"had|finish\w*|test\w*|made|make|encod\w*|submit\w*|fail\w*|pass\w*|so far|until|between)\b|[?.!,;]|$)")


def _name_after(t: str, keys: str) -> str:
    m = re.search(rf"\b(?:{keys})\s+(?:is\s+|=\s*|:\s*)?([a-z0-9][a-z0-9&.'\-/ ]{{1,40}}?){_STOPNAME}", t, re.I)
    return m.group(1).strip() if m else ""


def parse(question: str) -> dict[str, Any] | None:
    """What the question asks of the database, or None when it is not a data question."""
    t = question.lower()
    ref = LABREF.search(question)
    if ref:
        return {"kind": "labref", "ref": ref.group(1).upper()}
    if _SCHEMA.search(t):
        return {"kind": "schema"}
    if _FLOW.search(t) and _FLOW_OF.search(t) and not _DOCISH.search(t):
        return {"kind": "flow"}
    m = re.search(r"\b(?:material code|mat(?:erial)? ?code|item code)\s*(?:is|:|=|of|for)?\s*([A-Z0-9][A-Z0-9\-./]{2,30})", question, re.I)
    if m:
        return {"kind": "material", "code": m.group(1)}
    m = re.search(r"\binterlink\b.*?\b(?:application|app|report|trf)\s*(?:no\.?|number|#)?\s*([A-Z0-9\-]{4,30})\b", question, re.I)
    if m and re.search(r"\d", m.group(1)):
        return {"kind": "interlink", "no": m.group(1)}
    f: dict[str, Any] = {}
    for pat, cat in CATEGORY:
        if re.search(rf"\b(?:{pat})\b", t):
            f["category"] = cat
            break
    for pat, res in RESULT:
        if re.search(pat, t):
            f["result"] = res
            break
    p = _period(t)
    if p:
        f["period"] = p
    sup = _name_after(t, r"supplier|vendor|factory|galing sa|from supplier")
    if sup:
        f["supplier"] = sup
    tech = _name_after(t, r"technician|tech|tested by|done by|ni")
    if tech:
        f["technician"] = tech
    sty = _name_after(t, r"style|model|model style|article")
    if sty:
        f["style"] = sty
    if f.get("result") and re.search(r"\b(rate|percent(?:age)?|ratio)\b", t):
        f.pop("result")                                                  # "pass rate" asks for the share, which needs every result
    reportish = bool(_REPORTISH.search(t))
    if re.search(r"\binterlink\b", t) and (_COUNT.search(t) or re.search(r"\b(synced|unsynced|not synced|pending|status)\b", t)):
        return {"kind": "interlink_stats", **f}
    if _DOCISH.search(t) and not ({"supplier", "technician", "style", "period"} & set(f)):
        return None                                                      # a procedure or specification question: the documents answer it
    if _COUNT.search(t) and (reportish or f):
        return {"kind": "count", **f}
    if _LIST.search(t) and reportish and (f or re.search(r"\b(latest|recent|newest|last \d+)\b", t)):
        return {"kind": "list", **f}
    if re.search(r"\b(database|db|sa system|in the system|in nexus)\b", t) and f:
        return {"kind": "list", **f}
    return None


# ---------------------------------------------------------------------------------------------------- queries

_DATE = ("COALESCE(STR_TO_DATE(NULLIF(finished_date,''),'%d %b %Y'), STR_TO_DATE(NULLIF(tested_date,''),'%d %b %Y'), "
         "STR_TO_DATE(NULLIF(received_date,''),'%d %b %Y'))")
_RES = ("CASE WHEN overall_result IN ('Pass','P','PASS','Passed') THEN 'Pass' WHEN overall_result IN ('Fail','F','FAIL','Failed') THEN 'Fail' "
        "WHEN overall_result LIKE 'Tolerable%' THEN 'Tolerable' WHEN overall_result IS NULL OR overall_result = '' THEN 'No result yet' ELSE 'Other' END")


def _where(f: dict[str, Any]) -> tuple[str, list[str]]:
    w, said = ["lab_ref_no LIKE 'DT%'"], []                        # a few rows hold a reading in the reference column ("point18"): not reports
    if f.get("category"):
        w.append(f"form_category LIKE {q(f['category'] + '%')}")
        said.append(f["category"].lower() + (" bonding" if f["category"] == "Whole Shoe" else ""))
    if f.get("result"):
        w.append(f"{_RES} = {q(f['result'])}")
        said.append(f["result"].lower())
    if f.get("period"):
        s, e, label = f["period"]
        w.append(f"{_DATE} BETWEEN {q(s.isoformat())} AND {q(e.isoformat())}")
        said.append(label)
    for key, col, word in (("supplier", "supplier", "supplier"), ("technician", "technician", "technician"), ("style", "model_style", "style")):
        if f.get(key):
            w.append(f"{col} LIKE {like(f[key])}")
            said.append(f"{word} '{f[key]}'")
    return " AND ".join(w), said


def labref(ref: str, full: bool = True) -> dict[str, Any]:
    rows = run(f"SELECT * FROM {U} WHERE lab_ref_no = {q(ref)} LIMIT 5") or run(f"SELECT * FROM {U} WHERE lab_ref_no LIKE {q(ref + '%')} ORDER BY id DESC LIMIT 8")
    if not rows:
        return {"text": f"I could not find Lab Reference {ref} in NEXUS-L.", "found": 0}
    if len(rows) > 1 and rows[0]["lab_ref_no"].upper() != ref:
        lines = "\n".join(f"- {r['lab_ref_no']}: {r['form_category']}, {r['material_description'] or r['model_style']}, {r['overall_result'] or r['status']}" for r in rows)
        return {"text": f"{ref} matches {len(rows)} reports:\n{lines}\n\nAsk for one of them to see all its results.", "found": len(rows)}
    r = rows[0]
    head = [("Lab Reference", r["lab_ref_no"]), ("TR No.", r["tr_no"]), ("Report", r["form_category"]), ("Material type", r["material_type"]),
            ("Material code", r["material_code"]), ("Material", r["material_description"]), ("Supplier", r["supplier"]), ("Model / style", r["model_style"]),
            ("Color", r["color"]), ("PO / lot", r["po_lot_no"]), ("MTL", r["mtl_no"]), ("Received", r["received_date"]), ("Tested", r["tested_date"]),
            ("Finished", r["finished_date"]), ("Technician", r["technician"]), ("Authorized by", r["authorized_by"]), ("Status", r["status"]),
            ("Overall result", r["overall_result"])]
    text = [f"{k}: {v}" for k, v in head if v]
    detail = []
    if full and re.fullmatch(r"tbl_dtr_\w+", r.get("source_table") or "") and r.get("source_id"):
        try:
            detail = _source_fields(r["source_table"], r["source_id"])
        except Exception as e:                                       # the summary alone is still a correct answer
            log.warning("source row of %s: %s", ref, e)
    if detail:
        text.append("\nTest results:")
        text += [f"{k}: {v}" for k, v in detail]
    return {"text": "From the NEXUS-L database:\n\n" + "\n".join(text), "found": 1, "lab_ref": r["lab_ref_no"]}


# columns that are bookkeeping, not results
_SKIP = re.compile(r"^(ID|id|txtSearch|txtIsLocked|ToggleField|Tracker|Attachments|Slot\d|CreatedOn|LastModified|ModifiedBy|FormNo|.*_DSR|"
                   r"LabRefNo|Status|Supplier/Factory|Material Description|Color|Model No/Style|PO/Lot No|Received Date|Tested Date|"
                   r"Finished Date|Technician|MaterialType|MTL|MaterialCode|signature.*|.*json.*|.*ID)$")


def _source_fields(table: str, sid: str) -> list[tuple[str, str]]:
    keys = run(f"SHOW KEYS FROM `{table}` WHERE Key_name = 'PRIMARY'")
    pk = keys[0]["Column_name"] if keys else "ID"
    rows = run(f"SELECT * FROM `{table}` WHERE `{pk}` = {q(sid)} LIMIT 1")
    if not rows:
        return []
    out = []
    for k, v in rows[0].items():
        v = (v or "").strip()
        if not v or v in ("0", "0.00", "0000-00-00", "N/A", "-") or _SKIP.match(k) or len(v) > 300:
            continue
        out.append((re.sub(r"(?<=[a-z])(?=[A-Z])|_", " ", k).strip(), v))
    return out[:160]


def _latest() -> str:
    r = run(f"SELECT DATE_FORMAT(MAX({_DATE}), '%d %b %Y') AS d FROM {U} WHERE lab_ref_no LIKE 'DT%'")
    return r[0]["d"] if r and r[0]["d"] else ""


def count(f: dict[str, Any]) -> dict[str, Any]:
    want = f.get("result")
    w, said = _where({k: v for k, v in f.items() if k != "result"})   # the result asked for is shown out of every report in the same scope
    by_res = run(f"SELECT {_RES} AS result, COUNT(*) AS n FROM {U} WHERE {w} GROUP BY 1 ORDER BY 2 DESC")
    total = sum(int(r["n"]) for r in by_res)
    scope = ", ".join(said) or "all reports"
    if not total:
        last = _latest()
        return {"text": f"From the NEXUS-L database: no reports match ({scope})." + (f" The latest report was finished on {last}." if last else ""), "rows": 0}
    n_of = {r["result"]: int(r["n"]) for r in by_res}
    lines = [f"{r['result']}: {int(r['n']):,} ({int(r['n']) / total:.1%})" for r in by_res]
    head = (f"{want}: {n_of.get(want, 0):,} of {total:,} reports ({n_of.get(want, 0) / total:.1%})\n\nAll results:\n" if want
            else f"Total: {total:,} reports\n")
    text = f"From the NEXUS-L database ({scope}):\n\n{head}" + "\n".join(lines)
    if not f.get("category"):
        by_cat = run(f"SELECT SUBSTRING_INDEX(form_category,' (',1) AS cat, COUNT(*) AS n, SUM({_RES} = 'Fail') AS fails FROM {U} WHERE {w} "
                     f"GROUP BY 1 ORDER BY 2 DESC LIMIT 14")
        text += "\n\nBy report type:\n" + "\n".join(f"{r['cat']}: {int(r['n']):,} ({r['fails'] or 0} fail)" for r in by_cat)
    return {"text": text, "rows": total}


def listing(f: dict[str, Any], limit: int = 15) -> dict[str, Any]:
    w, said = _where(f)
    rows = run(f"SELECT lab_ref_no, SUBSTRING_INDEX(form_category,' (',1) AS cat, material_description, supplier, model_style, overall_result, "
               f"finished_date, technician FROM {U} WHERE {w} ORDER BY {_DATE} DESC, id DESC LIMIT {int(limit)}")
    n = run(f"SELECT COUNT(*) AS n FROM {U} WHERE {w}")[0]["n"]
    scope = ", ".join(said) or "latest reports"
    if not rows:
        last = _latest()
        return {"text": f"From the NEXUS-L database: no reports match ({scope})." + (f" The latest report was finished on {last}." if last else ""), "rows": 0}
    lines = [f"- {r['lab_ref_no']} | {r['cat']} | {(r['material_description'] or r['model_style'] or '-')[:45]} | {r['supplier'][:30] or '-'} | "
             f"{r['overall_result'] or '-'} | {r['finished_date'] or '-'}" + (f" | {r['technician']}" if r['technician'] else "") for r in rows]
    more = f"\n\nShowing the latest {len(rows)} of {int(n):,}. Ask for a Lab Reference to see all its results." if int(n) > len(rows) else ""
    return {"text": f"From the NEXUS-L database ({scope}):\n\nLab Ref | Report | Material | Supplier | Result | Finished\n" + "\n".join(lines) + more, "rows": int(n)}


def material(code: str) -> dict[str, Any]:
    m = run(f"SELECT MaterialCode, MaterialName, MaterialNameChinese, Supplier, `Material Type` AS type FROM tbl_dtr_material_list "
            f"WHERE MaterialCode = {q(code)} OR MaterialCode LIKE {q(code + '%')} LIMIT 5")
    reps = run(f"SELECT lab_ref_no, SUBSTRING_INDEX(form_category,' (',1) AS cat, overall_result, finished_date FROM {U} "
               f"WHERE material_code LIKE {q(code + '%')} ORDER BY id DESC LIMIT 10")
    if not m and not reps:
        return {"text": f"Material code {code} is not in the NEXUS-L material list or reports.", "rows": 0}
    text = ["From the NEXUS-L database:"]
    for r in m:
        text.append(f"\n{r['MaterialCode']}: {r['MaterialName']}" + (f" ({r['MaterialNameChinese']})" if r['MaterialNameChinese'] else "") +
                    f"\nSupplier: {r['Supplier'] or '-'}  Type: {r['type'] or '-'}")
    if reps:
        text.append("\nReports with this material:\n" + "\n".join(f"- {r['lab_ref_no']} | {r['cat']} | {r['overall_result'] or '-'} | {r['finished_date'] or '-'}" for r in reps))
    return {"text": "\n".join(text), "rows": len(m) + len(reps)}


def interlink(no: str) -> dict[str, Any]:
    rows = run(f"SELECT application_no, report_no, form_code, style_no, material_name, job_status, overall_result, review_status, "
               f"synced_to_interlink, synced_at, last_sync_error, report_issue_date FROM tbl_interlink_reports "
               f"WHERE application_no = {q(no)} OR report_no = {q(no)} OR nexus_work_order_no = {q(no)} ORDER BY id DESC LIMIT 3")
    if not rows:
        return {"text": f"No InterLink report with number {no} is in NEXUS-L.", "rows": 0}
    out = []
    for r in rows:
        out.append("\n".join(f"{k.replace('_', ' ').capitalize()}: {v}" for k, v in r.items() if v))
    return {"text": "From the NEXUS-L database (InterLink reports):\n\n" + "\n\n".join(out), "rows": len(rows)}


def interlink_stats(f: dict[str, Any]) -> dict[str, Any]:
    rows = run("SELECT CASE WHEN synced_to_interlink = 1 THEN 'Sent to InterLink' ELSE 'Not sent yet' END AS s, COALESCE(NULLIF(job_status,''),'-') AS job, "
               "COUNT(*) AS n FROM tbl_interlink_reports GROUP BY 1, 2 ORDER BY 3 DESC LIMIT 12")
    total = sum(int(r["n"]) for r in rows)
    return {"text": f"From the NEXUS-L database (InterLink reports): {total:,} in all\n\n" + "\n".join(f"{r['s']}, job status {r['job']}: {int(r['n']):,}" for r in rows), "rows": total}


TABLES = {
    "tbl_dtr_unified_labrefs": "every lab reference (report) with its type, material, supplier, style, dates, technician, status and overall result",
    "tbl_dtr_fabric_results / leather / rubber / hardware / label / sklfm / fthl / wst / box / insole / laminar results": "the readings and ratings of each test, per report type",
    "tbl_dtr_bonding_info, tbl_dtr_bonding_points, tbl_dtr_bonding_spot_points": "whole shoe bonding (U60) reports and their peel points",
    "tbl_dtr_material_list, tbl_dtr_materials_master": "material codes, names (also in Chinese), suppliers and incoming material status",
    "tbl_dtr_shoe_list, tbl_dtr_dsr": "styles, colorways and the DSR (bill of materials) lines",
    "tbl_dtr_standards": "the standard value and tolerance of each test per material type",
    "tbl_interlink_reports": "reports prepared for InterLink, their job status and whether they were sent",
    "tbl_dtr_report_approvals": "approval requests and signatures of reports",
    "tbl_vf_test_methods": "the 162 VF test methods and their files",
    "tbl_machine_maps": "which machine export folder and sheet feed which test",
    "tbl_dtr_audit_trail": "who changed what in a report, and when",
}


_FLOW = re.compile(r"\b(flow|workflow|process|lifecycle|life cycle|stages?|daloy|proseso)\b", re.I)
_FLOW_OF = re.compile(r"\b(reports?|lab ?refs?|lab references?|samples?|materials? testing|nexus(?:-l)?|the system|sa system|dtr)\b", re.I)


def flow() -> dict[str, Any]:
    """How a sample moves through NEXUS-L, with how many are at each step now."""
    mat = run("SELECT COALESCE(NULLIF(FinalStatus,''),'not set') AS s, COUNT(*) AS n FROM tbl_dtr_materials_master GROUP BY 1 ORDER BY 2 DESC LIMIT 6")
    st = run(f"SELECT status AS s, COUNT(*) AS n FROM {U} WHERE lab_ref_no LIKE 'DT%' GROUP BY 1 ORDER BY 2 DESC LIMIT 6")
    res = run(f"SELECT {_RES} AS s, COUNT(*) AS n FROM {U} WHERE lab_ref_no LIKE 'DT%' GROUP BY 1 ORDER BY 2 DESC")
    apr = run("SELECT status AS s, COUNT(*) AS n FROM tbl_dtr_report_approvals GROUP BY 1 ORDER BY 2 DESC")
    il = run("SELECT CASE WHEN synced_to_interlink = 1 THEN 'sent' ELSE 'not sent yet' END AS s, COUNT(*) AS n FROM tbl_interlink_reports GROUP BY 1")

    def fmt(rows):
        return ", ".join(f"{r['s']} {int(r['n']):,}" for r in rows) or "none"
    steps = [
        f"1. Material arrives: it is logged in Materials (code, supplier, container, date received in the lab). Now: {fmt(mat)}.",
        "2. A Lab Reference (DTP...) is opened in the Digital Test Report (DTR) for its report type: fabric, leather, rubber, hardware, "
        "foxing, label, webbing, sockliner, insole, carton box or whole shoe bonding (U60).",
        "3. The technician enters or imports the readings (machine exports are mapped to the right test); each test is rated against "
        "the standard value and tolerance of its material type, and the report gets its overall result.",
        f"4. The report moves from In Progress to Complete. Now: {fmt(st)}. Results: {fmt(res)}.",
        f"5. The report is sent to the signatory for approval and signed. Approval requests: {fmt(apr)}.",
        f"6. The InterLink page prepares the report for InterLink and the PIC sends it with their own account. InterLink reports: {fmt(il)}.",
        "7. Dashboard, Test Summary and Analytics show the totals, pass rates and fails; Lexus can read them too (ask \"how many failed this month\").",
    ]
    return {"text": "How a sample goes through NEXUS-L (with the numbers in the database now):\n\n" + "\n".join(steps), "rows": len(steps)}


def schema() -> dict[str, Any]:
    return {"text": "NEXUS-L keeps its data in the database dbnexus. The main tables:\n\n" + "\n".join(f"- {k}: {v}" for k, v in TABLES.items()) +
            "\n\nYou can ask me, for example: \"DTP20261019F1-3\", \"how many failed this month\", \"list the failed fabric reports\", "
            "\"reports of supplier Datian in 2026\", \"material code ...\".", "rows": len(TABLES)}


def answer(question: str) -> dict[str, Any] | None:
    """{text, kind, rows} from the database, or None when the question is not a data question (or the database cannot be read)."""
    if not available():
        return None
    p = parse(question)
    if not p:
        return None
    try:
        kind = p["kind"]
        if kind == "labref":
            out = labref(p["ref"])
        elif kind == "count":
            out = count(p)
        elif kind == "list":
            out = listing(p)
        elif kind == "material":
            out = material(p["code"])
        elif kind == "interlink":
            out = interlink(p["no"])
        elif kind == "interlink_stats":
            out = interlink_stats(p)
        elif kind == "flow":
            out = flow()
        else:
            out = schema()
    except Exception as e:
        log.warning("database question failed: %s", e)
        return None
    out["kind"] = p["kind"]
    return out
