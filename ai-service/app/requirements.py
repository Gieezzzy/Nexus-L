"""Standards and requirements: "what is the standard for footwear adhesion", "eyelet requirement", "minimum tear strength of
lining leather". The answer is the matching row of a requirement table, not the whole test method:

    Strength of Hardware Attachment (Eyelet) | VF-B04, SATRA TM 149 | kgf | 23 ±2 °C
    Toddler 15.0 · Children & Jr 15.0 · Adult Price Value 21.0 · Adult Casual 21.0 · Adult Athletic 27.0 · Adult Performance 27.0

The rows are read from the requirement tables in the lab documents (VF Material Specifications, the Footwear Performance Testing
Manual): a property, its test method, unit and condition, then one minimum per specification level. The passing values NEXUS-L
itself rates results with (tbl_dtr_standards) are added when the question names a material type that has them. A short reply
after an answer ("for adult casual?", "toddler") gives only that level.
"""
from __future__ import annotations

import json
import re
import time
from typing import Any

from app import labdocs
from app.textutil import coverage, words

_REQ = re.compile(r"\b(standards?|requirements?|required|requires|spec|specs|specification|minimum|maximum|min|max|passing|pass(?:ing)? value|"
                  r"limit|tolerance|criteria|pamantayan|dapat)\b", re.I)
# "standard" in these is not a required value
_NOT_REQ = re.compile(r"standard (operating|reference|laboratory|lab|atmosphere|condition|rubber|solution|weight|fabric|wool|abradant|felt)|"
                      r"\bsop\b|how (do|to|can|should)|procedure|steps?|calibrat", re.I)
VALUE = re.compile(r"^(?:[≤≥<>]=?\s*)?\d+(?:\.\d+)?\s*(?:%|kgf(?:/cm2?)?|n|mm|cycles?)?$|^n/?a$|^-$|^no (?:change|crack\w*|delamination)", re.I)
METHOD = re.compile(r"^(VF[- ]?[A-J]\s?\d{2}|SATRA\b|ASTM\b|ISO\b|DIN\b|BS\b|EN\b|AATCC\b|JIS\b|CAN/|N/A$|PRO-$|CE/EN)", re.I)
UNIT = re.compile(r"^(kgf(/cm2?)?|kg(/cm)?|n(/mm|/cm)?|%|rating|mm|cm|cycles?|a|c|d|shore a|g/cm3|g/m2|gsm|hrs?|seconds?|grey ?scale|aatcc scale|scale)$", re.I)
CONDITION = re.compile(r"°c|\b\d+\s*(hrs?|hours?|days?|cycles)\b|% ?rh|\bdry\b|\bwet\b", re.I)
SKIPCELL = re.compile(r"^(toddler|childre|children ?&?|n & jr|children ?(&|and)? ?jr|& jr|jr|adult|price|value|casual|athletic( /)?|/ classic|classic|boots|"
                      r"adult (price|casual|athletic|performance)( value)?|"
                      r"outdoor|performance|adult performance|material specification levels|minimum requirement|property|test|method|"
                      r"units?|condition|international|test method|vf fw|specification|internal use|requirement)$|page \d+ of|©|^version|"
                      r"^material component|^description|^date issued|^created by|^reason for revision|^revision", re.I)
LEVELS = {6: ["Toddler", "Children & Jr", "Adult Price Value", "Adult Casual", "Adult Athletic / Classic", "Adult Performance"],
          7: ["Toddler", "Children & Jr", "Adult Price Value", "Adult Casual", "Adult Athletic / Classic", "Boots / Outdoor", "Adult Performance"],
          4: ["Adult Price Value", "Adult Casual", "Adult Athletic", "Adult Performance"]}
SYN = {"adhesion": ["bond", "bonding", "peel"], "shoelace": ["lace", "shoe lace", "laces"], "shoelaces": ["lace", "laces"], "laces": ["lace"], "bond": ["adhesion"], "bonding": ["bond", "adhesion"], "eyelets": ["eyelet"], "glue": ["bond"],
       "tear": ["tear"], "lace": ["shoe lace", "lace"], "hardware": ["hardware"]}
FILLER = frozenset("standard standards requirement requirements required spec specs specification minimum maximum min max passing pass value values "
                   "limit tolerance criteria what which the for of is are a an ng ang sa po yung pamantayan dapat test vf".split())

_rows: dict[str, Any] = {"mtime": None, "rows": []}


def _page_texts() -> dict[tuple[str, str], dict[str, Any]]:
    rows, _ = labdocs._kw_index()
    pages: dict[tuple[str, str], dict[str, Any]] = {}
    for r in rows:
        if "(OCR)" in r["loc"] or labdocs.SUPERSEDED.search(r["file"]):
            continue
        if not re.search(r"toddler|minimum requirement|specification levels", r["text"], re.I) and (r["file"], r["loc"]) not in pages:
            continue
        p = pages.setdefault((r["file"], r["loc"]), {"folder": r["folder"], "file": r["file"], "path": r["path"], "loc": r["loc"], "title": r["title"], "text": ""})
        p["text"] = labdocs._join(p["text"], r["text"]) if p["text"] else r["text"]
    return pages


def _method_titles() -> dict[str, str]:
    rows, _ = labdocs._kw_index()
    out = {}
    for r in rows:
        m = re.match(r"VF\s*-\s*([A-J])\s?(\d{2})\s+(.+?)(?:\s*-\s*\w+\s+\d{4})?\.pdf$", r["file"], re.I)
        if m:
            out[f"VF-{m.group(1).upper()}{m.group(2)}"] = m.group(3).strip()
    return out


SECTION = re.compile(r"^\d+\.\d+\s+([A-Za-z].{3,80})$")


def _parse(page: dict[str, Any], titles: dict[str, str], section: str = "") -> tuple[list[dict[str, Any]], str]:
    """The rows of one page, and the section heading it ends in (the next page's rows belong to it until a new one starts)."""
    cells = [c.strip() for c in page["text"].split("\n") if c.strip()]
    comp = re.search(r"Description\s*:\s*([^\n]+)", page["text"])
    fixed = comp.group(1).strip() if comp else ""
    has_levels = bool(re.search(r"toddler", page["text"], re.I))
    out, i, start = [], 0, 0
    carry = {"method": [], "unit": "", "cond": ""}
    while i < len(cells):
        j = i
        while j < len(cells) and VALUE.match(cells[j]):
            j += 1
        if j - i >= 4:
            lab, meth, unit, cond = [], [], "", ""
            for c in cells[start:i]:
                sec = SECTION.match(c)
                if sec:
                    section, lab = sec.group(1).strip(), []
                    continue
                if re.fullmatch(r"[A-H](\s*,\s*[A-H])*", c) and not re.search(r"hardness", " ".join(lab), re.I):
                    continue                                          # a footnote letter, not the Shore A unit
                if SKIPCELL.search(c):
                    continue
                if METHOD.match(c):
                    meth.append(c)
                elif UNIT.match(c):
                    unit = c
                elif CONDITION.search(c) and len(c) < 40:
                    cond = c
                else:
                    lab.append(c)
            if meth or unit or cond:
                carry = {"method": meth or carry["method"], "unit": unit or carry["unit"], "cond": cond or carry["cond"]}
            label = re.sub(r"\s+", " ", " ".join(lab[-4:])).strip()
            label = re.sub(r"^(?:(?:toddler|children|&|jr|adult|price|value|casual|athletic|performance)\s*)+(?=[A-Z])", "", label, flags=re.I)
            label = re.sub(r"\s+(?:[A-H](?:\s*,\s*[A-H])*)$|(?<=[a-z\)])[A-H]$", "", label).strip(" ,")
            vals = cells[i:j]
            if label and len(label) <= 120:
                codes = [re.sub(r"\s", "", m).upper().replace("VF", "VF-").replace("VF--", "VF-") for m in carry["method"] if m.upper().startswith("VF")]
                out.append({"label": label, "method": [m for m in carry["method"] if m.upper() not in ("N/A", "PRO-", "CE/EN")], "unit": carry["unit"],
                            "cond": carry["cond"], "values": vals, "levels": LEVELS.get(len(vals)) if has_levels else None, "component": fixed or section,
                            "method_title": " ".join(titles.get(c, "") for c in codes), **{k: page[k] for k in ("folder", "file", "path", "loc", "title")}})
            start = i = j
        else:
            sec = SECTION.match(cells[i])
            if sec:
                section = sec.group(1).strip()
            i += 1
    return out, section


def table() -> list[dict[str, Any]]:
    """Every requirement row of the lab documents, read again when passages.jsonl changes."""
    labdocs._kw_index()
    if _rows["mtime"] != labdocs._kw["mtime"]:
        titles = _method_titles()
        rows: list[dict[str, Any]] = []
        section: dict[str, str] = {}
        for page in _page_texts().values():
            got, section[page["file"]] = _parse(page, titles, section.get(page["file"], ""))
            rows += got
        _rows.update(mtime=labdocs._kw["mtime"], rows=rows)
    return _rows["rows"]


def _doc_rank(r: dict[str, Any]) -> float:
    f = r["file"].lower()
    return (0.3 if "2026" in f else 0.15 if "2025" in f or "2024" in f else 0.0) + (0.1 if "material specification" in f else 0.0) - \
        (0.5 if re.search(r"draft|version 2", f) else 0.0)


def is_requirement(question: str) -> bool:
    return bool(_REQ.search(question)) and not _NOT_REQ.search(question)


def find(question: str) -> list[dict[str, Any]]:
    """The rows that answer the question, best first; [] when none clearly matches."""
    q = labdocs.clean_question(question)
    _, idx = labdocs._kw_index()
    qw = [w for w in words(q) if w not in FILLER and (len(w) >= 4 or w in idx)]     # "wha", "tis": typo fragments, not content
    if not qw:
        return []
    scored = []
    for r in table():
        head = f"{r['label']} {' '.join(r['method'])} {r['method_title']}"
        ctx = f"{r['component']} {r['title']}"
        def has(w: str, text: str) -> bool:
            return coverage([w], text) > 0 or any(coverage([s], text) > 0 for s in SYN.get(w, []))
        in_head = [w for w in qw if has(w, head)]
        in_ctx = [w for w in qw if w not in in_head and has(w, ctx)]          # the material is often only in the section heading
        score = (len(in_head) + 0.8 * len(in_ctx)) / len(qw)
        if score >= 0.6 and (in_head or len(in_ctx) == len(qw)):
            scored.append((round(score, 3), _doc_rank(r), r))
    if not scored:
        return []
    scored.sort(key=lambda x: (x[0], x[1]), reverse=True)
    best_score, best_rank, best = scored[0]
    # the rows of the best document that fit as well (the eyelet rows, the bond strength rows), at most eight, without repeats
    out, seen = [], set()
    for s, rank, r in scored:
        if s < best_score - 1e-9 or r["file"] != best["file"]:
            continue
        key = (r["label"].lower(), tuple(r["values"]))
        if key not in seen:
            seen.add(key)
            out.append(r)
    # the section with the most matching rows is the table asked about (footwear adhesion -> the bonding table, not the one
    # bond-strength row of the foxing tape table); its rows come first
    per = {c: sum(x["component"] == c for x in out) for c in {x["component"] for x in out}}
    out.sort(key=lambda x: -per[x["component"]])
    return out[:8]


def _fmt(r: dict[str, Any], level: str | None = None) -> str:
    head = " | ".join(x for x in (r["label"], ", ".join(r["method"]), r["unit"], r["cond"]) if x)
    if r["levels"]:
        pairs = list(zip(r["levels"], r["values"]))
        if level:
            pairs = [p for p in pairs if level.lower() in p[0].lower()] or pairs
        vals = " · ".join(f"{lv} {v}" for lv, v in pairs)
    else:
        vals = " · ".join(r["values"])
    return f"{head}\n   {vals}"


_last: dict[str, dict[str, Any]] = {}
_LEVEL_Q = re.compile(r"\b(toddler|children|jr|junior|kids?|price value|casual|athletic|classic|boots?|outdoor|performance|pro)\b", re.I)


def _nexus_values(question: str) -> str:
    """The passing values NEXUS-L rates results with, when the question names one of its material types."""
    from app import dbskill
    if not dbskill.available():
        return ""
    try:
        types = [r["MaterialType"] for r in dbskill.run("SELECT DISTINCT MaterialType FROM tbl_dtr_standards")]
    except Exception:
        return ""
    qw = [w for w in words(labdocs.clean_question(question)) if w not in FILLER and len(w) >= 4]
    # only a material type the question names exactly ("eyelet", "rubber", "zipper"); a family of variants ("Shoe lace - neon",
    # "Shoe lace - Cotton Light", ...) would be a list, not an answer
    hit = [t for t in types if " - " not in t and any(coverage([w], t) > 0 and len(words(t)) == 1 for w in qw)]
    if len(hit) != 1:
        return ""
    rows = dbskill.run(f"SELECT TestMethod, StandardValue, Tolerance FROM tbl_dtr_standards WHERE MaterialType = {dbskill.q(hit[0])} AND StandardValue <> '' LIMIT 25")
    asked = [x for x in rows if any(coverage([w], re.sub(r"(?<=[a-z])(?=[A-Z])|_", " ", x["TestMethod"])) > 0 for w in qw)]
    rows = asked or rows
    if not rows:
        return ""
    vals = ", ".join(f"{re.sub(r'_(Std|std|S)$', '', x['TestMethod'])} {x['StandardValue']}" + (f" ±{x['Tolerance']}" if x["Tolerance"] else "") for x in rows)
    return f"\n\nPassing values set in NEXUS-L for {hit[0]} (used to rate results): {vals}"


def answer(question: str, session_id: str = "") -> dict[str, Any] | None:
    """{text, source, confidence} with just the requirement rows asked for, or None."""
    last = _last.get(session_id or "")
    if last and time.time() - last["t"] < 900 and len(words(question)) <= 4 and _LEVEL_Q.search(question) and not is_requirement(question):
        lv = _LEVEL_Q.search(question).group(1).lower()
        lv = {"kids": "children", "kid": "children", "junior": "jr", "boot": "boots", "pro": "performance"}.get(lv, lv)
        rows = last["rows"]
        text = "\n".join(_fmt(r, lv) for r in rows)
        return _out(rows, f"Minimum requirement for {lv.title()}:\n\n{text}", question)
    if not is_requirement(question):
        return None
    rows = find(question)
    if not rows:
        return None
    if session_id:
        _last[session_id] = {"rows": rows, "t": time.time()}
    title = rows[0]["component"] or rows[0]["title"]
    text = f"Standard (minimum requirement), {title}:\n\n" + "\n\n".join(_fmt(r) for r in rows)
    text += "\n\nAsk for one level (for example \"adult casual?\") to see only its value." if rows[0]["levels"] else ""
    return _out(rows, text + _nexus_values(question), question)


def _out(rows: list[dict[str, Any]], text: str, question: str) -> dict[str, Any]:
    r = rows[0]
    src = {"folder": r["folder"], "file": r["file"], "path": r["path"], "loc": r["loc"]}
    return {"text": f"{text}\n\nSource: {r['folder']} > {r['file']}, {r['loc']}", "source": src, "confidence": 0.97,
            "excerpt": json.dumps([x["label"] for x in rows])[:300]}
