"""The VF test methods, one by one: "what is VF-B04", "test for hydrolysis", "which methods are for adhesion", "how many test methods".

The answer is short and direct: the code, its name and group, what it measures (the method's own scope sentence), the minimum
requirements that name the method (rows of the requirement tables), and where the full method is. The list of methods is the
NEXUS-L table tbl_vf_test_methods (read only); the scope and the requirements come from the lab documents.
"""
from __future__ import annotations

import re
from typing import Any

from app import dbskill, labdocs, requirements
from app.textutil import coverage, words

CODE = re.compile(r"\bVF[\s-]*([A-J])[\s-]*(\d{2})\b", re.I)
_LISTISH = re.compile(r"\b(list|which|what|show|all|ano|anong|mga)\b.*\b(tests?|methods?|vf|procedures?)\b|\b(how many|ilan)\b.*\b(methods?|tests? methods?)\b", re.I)
_ABOUT = re.compile(r"\b(what is|what's|describe|explain|tell me about|details? (of|for|about)|info(rmation)? (on|about|for)|ano ang|ano yung|meaning of)\b", re.I)
_FILLER = frozenset("test tests method methods vf procedure procedures testing which what for the of is are a an to do does how many list show all "
                    "mga ano ang anong yung ng sa please give me".split())
_cache: dict[str, Any] = {"rows": None}


def _methods() -> list[dict[str, str]]:
    if _cache["rows"] is None:
        if not dbskill.available():
            return []
        best: dict[str, dict[str, str]] = {}
        for r in dbskill.run("SELECT code, title, category, revision_date FROM tbl_vf_test_methods ORDER BY code"):
            if CODE.fullmatch(r["code"].strip()):
                r["title"] = re.sub(r"\s*-\s*(?:\w+\s+\d{4}|\d{4}.*)$", "", r["title"]).strip()          # "DIN Abrasion - July 2017" -> "DIN Abrasion"
                k = norm(r["code"])
                if k not in best or len(r["title"]) < len(best[k]["title"]):
                    best[k] = r
        _cache["rows"] = [best[k] for k in sorted(best)]
    return _cache["rows"]


def norm(code: str) -> str:
    m = CODE.search(code)
    return f"VF-{m.group(1).upper()}{m.group(2)}" if m else code.upper()


def _scope(code: str) -> tuple[str, dict[str, Any] | None]:
    """The method's own scope sentence and the first passage of its (non-illustrated) document."""
    rows, _ = labdocs._kw_index()
    key = re.compile(rf"VF\s*-?\s*{code[3]}\s*{code[4:]}\b", re.I)
    best = None
    for r in rows:
        if key.search(r["file"]) and "illustrated" not in r["file"].lower() and not labdocs.SUPERSEDED.search(r["file"]) and "(OCR)" not in r["loc"]:
            if best is None or len(r["text"]) > len(best["text"]) and r["loc"] == best["loc"]:
                best = r if best is None else best
    if best is None:
        return "", None
    t = re.sub(r"\s+", " ", best["text"])
    m = re.search(r"PURPOSE AND SCOPE:?\s*(.{40,420}?)(?:\.\s|REFERENCE|EQUIPMENT|$)", t, re.I)
    return (re.sub(r"\s+\d+$", "", m.group(1).strip().rstrip(".")) + "." if m else ""), best


def _rows_for(code: str) -> list[dict[str, Any]]:
    out, seen = [], set()
    for r in requirements.table():
        if any(norm(m) == code for m in r["method"] if CODE.search(m)):
            k = (r["label"].lower(), tuple(r["values"]))
            if k not in seen and r["levels"]:
                seen.add(k)
                out.append(r)
    out.sort(key=lambda r: (-requirements._doc_rank(r), r["file"]))
    return out[:4]


def describe(code: str) -> dict[str, Any] | None:
    m = next((x for x in _methods() if norm(x["code"]) == code), None)
    if not m:
        return None
    scope, passage = _scope(code)
    lines = [f"{code} - {m['title']}", f"Group: {m['category']}" + (f" (revision {m['revision_date']})" if m.get("revision_date") else "")]
    if scope:
        lines.append(f"What it measures: {scope}")
    rr = _rows_for(code)
    if rr:
        lines.append("\nMinimum requirement (specification level: value):")
        for r in rr[:3]:
            lines.append(f"- {r['label'][:80]} [{r['unit']}]: " + " · ".join(f"{lv} {v}" for lv, v in zip(r["levels"], r["values"])))
    src = passage or (rr[0] if rr else None)
    return {"text": "\n".join(lines) + (f"\n\nSource: {src['folder']} > {src['file']}, {src['loc']}" if src else ""), "kind": "method", "code": code,
            "source": ({"folder": src["folder"], "file": src["file"], "path": src["path"], "loc": src["loc"]} if src else None)}


def _by_words(question: str) -> list[dict[str, str]]:
    q = re.sub(r"colou?r\s+fastness", "colorfastness", labdocs.clean_question(question), flags=re.I)
    qw = [w for w in words(q) if w not in _FILLER and len(w) >= 3]
    if not qw:
        return []
    ms = _methods()
    docs = [f"{m['title']} {m['category']}".lower().replace("colourfastness", "colorfastness") for m in ms]
    syn = {"light": ["ultraviolet", "uv"], "uv": ["ultraviolet"], "glue": ["adhesion", "bond"], "peel": ["adhesion", "bond"]}
    known = {w: sum(coverage([w], d) > 0 or any(coverage([x], d) > 0 for x in syn.get(w, [])) for d in docs) for w in qw}
    qw = [w for w in qw if known[w]]                                   # "describe", "sole": words no method title holds say nothing
    if not qw:
        return []
    wt = {w: 1.0 / known[w] for w in qw}
    total = sum(wt.values())
    scored = []
    for m, d in zip(ms, docs):
        got = sum(wt[w] for w in qw if coverage([w], d) > 0 or any(coverage([x], d) > 0 for x in syn.get(w, [])))
        if got:
            scored.append((got / total, m))
    if not scored:
        return []
    scored.sort(key=lambda x: (-x[0], x[1]["code"]))
    best = scored[0][0]
    return [m for s, m in scored if s >= max(0.5, best - 1e-9)][:8]


def answer(question: str) -> dict[str, Any] | None:
    """{text, kind, ...} or None when the question is not about a test method."""
    if not dbskill.available():
        return None
    ms = _methods()
    if not ms:
        return None
    codes = [norm(m.group(0)) for m in CODE.finditer(question)]
    low = question.lower()
    if not codes and (dbskill._FLOW_Q.search(low) or re.search(r"\b(after|before) the test\b|\b(serial|machine|equipment|calibrat\w*|maintain\w*|certificate|schedule|technician|signed|approv\w*)\b", low)):
        return None                                                    # a flow, equipment or approval question that happens to hold a method word
    if re.search(r"\b(how many|ilan)\b.*\b(test )?methods?\b", low) and not codes:
        cats: dict[str, int] = {}
        for m in ms:
            cats[m["category"]] = cats.get(m["category"], 0) + 1
        return {"text": f"NEXUS-L has {len(ms)} VF test methods:\n" + "\n".join(f"- {c}: {n}" for c, n in sorted(cats.items(), key=lambda x: -x[1])), "kind": "methods", "source": None}
    if codes:
        out = describe(codes[0])
        if out:
            return out
        return {"text": f"{codes[0]} is not in the NEXUS-L list of VF test methods.", "kind": "method", "source": None}
    # asking WHICH method, or for a method by name; a question for a value inside a method (speed, size, gap, conditioning ...) is the documents' to answer
    values = re.search(r"\b(speed|gap|size|thickness|temperature|humidity|time|hours?|minutes?|weight|load|length|width|diameter|distance|conditioning|requirement|minimum|maximum|"
                       r"ring|die|sample|specimens?|serial|how (much|many|long|do|to|can)|jaws?|rate|cycles?|force|standard)\b", low)
    asks_which = re.search(r"\bwhich (test|method|procedure)s?\b|\bwhat (test|method|procedure)s?\b|\b(test|method|procedure)s? (for|to (measure|check))\b|"
                           r"\b(list|show) (all |the )?(vf )?(test )?methods?\b|\b(describe|explain|tell me about) (the )?[\w\s]+? test\b|\bwhat is (the )?[\w\s-]+? test\b", low)
    if asks_which and not values:
        hits = _by_words(question)
        if not hits:
            return None
        if len(hits) == 1 or (len(hits) > 1 and _ABOUT.search(question) and words(question) and coverage(words(hits[0]["title"]), question) >= 0.99):
            return describe(norm(hits[0]["code"]))
        return {"text": "Test methods that fit:\n" + "\n".join(f"- {norm(m['code'])} - {m['title']} ({m['category']})" for m in hits)
                + "\n\nAsk for one (for example \"what is " + norm(hits[0]["code"]) + "\") to see what it measures and its requirement.", "kind": "methods", "source": None}
    return None
