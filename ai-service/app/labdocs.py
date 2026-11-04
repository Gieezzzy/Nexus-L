"""The laboratory documents (the "#" folders of the Lab Documents share) as a second source of answers.

labdocs_extract.py turns the documents into passages (E:\\NexusAI\\labdocs\\passages.jsonl); index() embeds them into the "labdocs"
collection. answer() is used when no approved answer is confident enough: it finds the passages about the question, lets the
cross-encoder read the question with each of them, and answers with the lines of the best passage that hold the question's
words, always naming the document, folder and page. Nothing is composed: every word of the answer is in the document.
"""
from __future__ import annotations

import json
import logging
import os
import re
import threading
import time
from pathlib import Path
from typing import Any

from app import rerank, store
from app.textutil import coverage, words

log = logging.getLogger("nexus.labdocs")
PASSAGES = Path(os.environ.get("AI_LABDOCS_FILE", r"E:\NexusAI\labdocs\passages.jsonl"))
COLLECTION = "labdocs"
THRESHOLD = float(os.environ.get("AI_DOC_THRESHOLD", "0.55"))
_status: dict[str, Any] = {"state": "idle"}
_lock = threading.Lock()


def _ensure() -> None:
    from qdrant_client.http import models as qm

    c = store.client()
    if not c.collection_exists(COLLECTION):
        c.create_collection(COLLECTION, vectors_config=qm.VectorParams(size=384, distance=qm.Distance.COSINE))


def _embed_text(r: dict[str, Any]) -> str:
    return f"{r['title']} ({r['loc']}). {r['text']}"


def index(full: bool = False) -> dict[str, Any]:
    """Embeds new and changed passages and removes the ones whose document changed or disappeared."""
    with _lock:
        _ensure()
        if not PASSAGES.exists():
            return {"state": "no passages file", "file": str(PASSAGES)}
        rows = [json.loads(x) for x in PASSAGES.read_text(encoding="utf-8").splitlines() if x.strip()]
        known: set[str] = set()
        if not full:
            offset = None
            while True:
                pts, offset = store.client().scroll(COLLECTION, limit=1000, offset=offset, with_payload=["pid"])
                known |= {str((p.payload or {}).get("pid")) for p in pts}
                if offset is None:
                    break
        todo = [r for r in rows if r["id"] not in known]
        t0 = time.time()
        _status.update(state="indexing", total=len(todo), done=0, started=t0)
        for i in range(0, len(todo), 64):
            batch = todo[i:i + 64]
            store.upsert(COLLECTION, [{"key": r["id"], "text": _embed_text(r), "payload": {
                "pid": r["id"], "folder": r["folder"], "file": r["file"], "path": r["path"], "loc": r["loc"], "title": r["title"], "text": r["text"]}}
                for r in batch])
            _status["done"] = i + len(batch)
        removed = store.delete_missing(COLLECTION, {r["id"] for r in rows})
        res = {"state": "ready", "passages": len(rows), "added": len(todo), "removed": removed, "seconds": round(time.time() - t0)}
        _status.clear()
        _status.update(res)
        return res


def index_in_background(full: bool = False) -> dict[str, Any]:
    if _status.get("state") == "indexing":
        return dict(_status)
    threading.Thread(target=lambda: _safe(index, full), daemon=True).start()
    return {"state": "indexing", "started": True}


def _safe(fn, *a):
    try:
        fn(*a)
    except Exception as e:                                         # the service keeps answering from the approved answers
        log.exception("labdocs indexing failed")
        _status.clear()
        _status.update(state="error", error=str(e)[:300])


def status() -> dict[str, Any]:
    out = dict(_status)
    try:
        out["indexed"] = store.count(COLLECTION) if store.client().collection_exists(COLLECTION) else 0
    except Exception:
        pass
    return out


def _lines(text: str) -> list[str]:
    parts = re.split(r"(?<=[.;:])\s+|\n+", text)
    return [p.strip() for p in parts if len(p.strip()) > 2]


def _excerpt(question_words: list[str], text: str, max_chars: int = 1000) -> str:
    """The passage itself (passages are at most ~900 characters, so the whole procedure or table row stays together); a longer
    one is cut to the lines that carry the question's words, with the lines around them."""
    if len(text) <= max_chars:
        return text.strip()
    lines = _lines(text)
    if not lines:
        return text[:max_chars]
    scores = [coverage(question_words, ln) for ln in lines]
    best = max(range(len(lines)), key=lambda i: (scores[i], -i))
    pick = [best]
    lo, hi = best, best
    while True:                                                   # grow around the best line while it stays short
        grown = False
        for j in (hi + 1, lo - 1):
            if 0 <= j < len(lines) and sum(len(lines[k]) for k in pick) + len(lines[j]) <= max_chars:
                pick.append(j)
                lo, hi = min(lo, j), max(hi, j)
                grown = True
        if not grown or len(pick) >= 6:
            break
    return "\n".join(lines[k] for k in sorted(pick))


# when two passages fit about equally well, the controlled documents win over lectures, minutes and records
AUTHORITY = (("Lab-101", 0.012), ("Lab-103", 0.012), ("Lab-119", 0.012), ("Lab-118", 0.012), ("Lab-125", 0.012), ("Lab-107", 0.01),
             ("Lab-111", 0.01), ("Lab-105", 0.008), ("Lab-120", 0.0), ("Lab-106", -0.005), ("Lab-109", -0.01), ("Lab-115", -0.01),
             ("Lab-116", -0.01), ("Lab-114", -0.02), ("Lab-108", -0.008), ("Lab-121", -0.01))   # 108/121: calibration and CAP photos read by OCR


# a file its own name marks as not the current version ("... - WRONG.docx", "Copy of ...", "(DRAFT 1)", "... (2).pdf")
SUPERSEDED = re.compile(r"\bwrong\b|\bcopy\b|\bold\b|\bdraft\b|\bbackup\b|\bobsolete\b|\bsuperseded\b|\(\d\)", re.I)


def _authority(folder: str, file: str = "") -> float:
    f = folder.lower()
    return next((w for key, w in AUTHORITY if key.lower() in f), 0.0) - (0.03 if SUPERSEDED.search(file) else 0.0)


_COMMON = frozenset("test testing tested machine sample samples standard laboratory lab what which when where how many much does used use "
                    "need needed required requirement steps step maintain maintenance clean check procedure method value minimum maximum "
                    "often tester testers maintained should there their about".split())

# how the ranking weighs the reader's score, the question's rare words, a file named after the thing asked about and the folder;
# trained by tools/tune_labdocs.py on the real-question test and kept in weights.json next to the passages
WEIGHTS: dict[str, Any] = {}


def load_weights() -> dict[str, Any]:
    """(Re)reads weights.json; a ranker trained with tools/train_lexus.py ("linear") replaces the hand-set formula."""
    w: dict[str, Any] = {"logit": 0.03, "cover": 0.15, "boost": 0.3, "auth": 1.0}
    try:
        w.update(json.loads((PASSAGES.parent / "weights.json").read_text(encoding="utf-8")))
    except (OSError, ValueError):
        pass
    WEIGHTS.clear()
    WEIGHTS.update(w)
    return WEIGHTS


load_weights()


def _keywords(question: str) -> list[str]:
    """The question's distinctive words: not filler, and the rarest in the documents first ("cutting" says more than "abrasion"
    in "the Martindale abrasion cutting die"). At most three; a word no document holds (a typo) is left out."""
    _, idx = _kw_index()
    ws = dict.fromkeys(w.lower() for w in re.findall(r"[A-Za-z][A-Za-z\-]{4,}", question) if w.lower() not in _COMMON)
    found = [w for w in ws if idx.get(w)]
    return sorted(found, key=lambda w: len(idx[w]))[:3]


# file and sheet names written in Chinese, with the English the technicians ask in ("卡尺" certificates answer "caliper" questions)
GLOSSARY = {"剛直尺": "steel ruler", "卡尺": "caliper", "合格證": "certificate of conformity", "羊毛布": "wool felt cloth",
            "产地证明": "certificate of origin", "鋁箔片": "aluminium foil", "切刀": "cutting die", "六色九級卡": "multifibre grey scale card",
            "六色布": "multifibre fabric", "感濕棉布": "moisture sensitive cotton cloth", "染污灰色標": "grey scale for staining",
            "羊毛": "wool", "貼紙": "sticker", "氯化钠": "sodium chloride", "燈管": "lamp tube", "標準": "standard",
            "太陽燈泡": "sun lamp bulb", "砂輪": "abrasive wheel", "磷酸氢二钠": "disodium hydrogen phosphate", "无水": "anhydrous",
            "砂纸用双面胶": "double sided tape for sandpaper", "氢氧化钠标准溶液": "sodium hydroxide standard solution", "甘油": "glycerin",
            "燈泡": "bulb", "紫外線燈管": "UV lamp tube", "马丁代尔羊毛垫布": "Martindale wool felt", "胶纸": "adhesive tape",
            "摩擦布": "crocking rubbing cloth", "油氈": "felt", "吸墨纸": "blotting paper", "檢驗証書": "calibration certificate"}


def _en(name: str) -> str:
    """The name with the English of its Chinese words added."""
    extra = [en for cn, en in GLOSSARY.items() if cn in name]
    return f"{name} ({', '.join(extra)})" if extra else name


_kw: dict[str, Any] = {"mtime": None, "rows": [], "index": {}, "pos": {}}


def _kw_index() -> tuple[list[dict[str, Any]], dict[str, set[int]]]:
    """Word -> passages, built once from passages.jsonl (and again when the file changes). Looking a word up is instant, unlike a
    text filter in the vector store, which reads every passage."""
    try:
        m = PASSAGES.stat().st_mtime
    except OSError:
        return [], {}
    if _kw["mtime"] != m:
        rows, idx, pos = [], {}, {}
        for line in PASSAGES.read_text(encoding="utf-8").splitlines():
            if not line.strip():
                continue
            r = json.loads(line)
            r["title"], r["loc"] = _en(r["title"]), _en(r["loc"])
            i = len(rows)
            rows.append(r)
            pos[r["id"]] = i
            for w in set(re.findall(r"[a-z][a-z\-]{3,}", f"{_en(r['file'])} {r['title']} {r['loc']} {r['text']}".lower())):
                idx.setdefault(w, set()).add(i)
        from app.textutil import stem
        sdf: dict[str, int] = {}                                  # passages per stem, the form the question's words come in
        for w, ids in idx.items():
            s = stem(w)
            sdf[s] = max(sdf.get(s, 0), len(ids))
        _kw.update(mtime=m, rows=rows, index=idx, pos=pos, sdf=sdf)
    return _kw["rows"], _kw["index"]


def _next_of(pid: str) -> dict[str, Any] | None:
    """The passage that follows this one in the same document part (passages overlap by ~150 characters)."""
    rows, _ = _kw_index()
    i = _kw["pos"].get(pid)
    if i is None or i + 1 >= len(rows):
        return None
    a, b = rows[i], rows[i + 1]
    return b if (a["file"], a["loc"]) == (b["file"], b["loc"]) else None


def _prev_of(pid: str) -> dict[str, Any] | None:
    """The passage before this one in the same document part."""
    rows, _ = _kw_index()
    i = _kw["pos"].get(pid)
    if not i:
        return None
    a, b = rows[i - 1], rows[i]
    return a if (a["file"], a["loc"]) == (b["file"], b["loc"]) else None


def _join(a: str, b: str) -> str:
    """Two consecutive passages as one text, the overlap written once."""
    for n in range(min(len(a), len(b), 300), 20, -1):
        if a.endswith(b[:n]):
            return a + b[n:]
    return a.rstrip() + "\n" + b.lstrip()


def _with_word(question: str, word: str, k: int) -> list[dict[str, Any]]:
    """Passages that contain the word, those that also name it in their file or sheet and hold more of the question's words first."""
    rows, idx = _kw_index()
    ids = idx.get(word, set())
    if not ids:
        return []
    qw = words(question)

    def score(i: int) -> tuple[int, float]:
        r = rows[i]
        named = int(word in f"{_en(r['file'])} {r['loc']} {r['title']}".lower())
        return named, coverage(qw, f"{r['title']} {r['loc']} {r['text'][:400]}")

    best = sorted(ids, key=score, reverse=True)[:k]
    return [{"score": 0.0, "payload": {"pid": rows[i]["id"], **{f: rows[i][f] for f in ("folder", "file", "path", "loc", "title", "text")}}} for i in best]


# a technician's word and the document's word for the same thing ("maintain" the machine -> "Maintenance Procedure")
LAB_SYN = {"maintain": ["maintenance"], "step": ["procedure", "sop"], "calibrate": ["calibration", "calibrated"], "condition": ["conditioning", "conditioned"],
           "clean": ["cleaning", "maintenance"], "how": ["procedure"], "repair": ["repairs", "adjustment"], "speed": ["crosshead"],
           "often": ["frequency"], "frequently": ["frequency"], "day": ["daily"], "everyday": ["daily"], "week": ["weekly"],
           "month": ["monthly"], "year": ["yearly", "annual"]}


# ---- loosely typed questions: Taglish, chat words and typos --------------------------------------------------------

TAGLISH = {"paano": "how", "pano": "how", "ano": "what", "anong": "what", "ilan": "how many", "ilang": "how many", "kailan": "when",
           "saan": "where", "sino": "who", "bakit": "why", "gaano": "how", "katagal": "long", "tagal": "long", "kada": "every",
           "araw": "day", "linggo": "week", "buwan": "month", "taon": "year", "sukat": "size", "bilis": "speed", "haba": "length",
           "kapal": "thickness", "bigat": "weight", "linisin": "clean", "ayusin": "repair", "gamit": "used", "gagamitin": "used",
           "wat": "what", "wats": "what is", "whats": "what is", "hw": "how", "hwo": "how", "u": "you", "ur": "your", "abt": "about",
           "wid": "with", "pls": "", "plz": "", "pakisabi": "", "paki": "", "thx": "", "ty": ""}
FILLER = frozenset("ang ng sa ba po yung ung mga na para kung naman lang lng pa ko mo natin nga kasi daw raw din rin yan iyan ito yun nung "
                   "si ni kay ay ka kami tayo niyo nila eh oh ah uy pre bro sir maam mam".split())
_TAGALOG_HINT = frozenset(set(TAGLISH) - {"wat", "wats", "whats", "hw", "hwo", "u", "ur", "abt", "wid", "pls", "plz", "thx", "ty"}) | {"ang", "ng", "po", "yung", "mga", "ba"}
_eng: set[str] | None = None
_buckets: dict[str, Any] = {"mtime": None, "by": {}}


def _english() -> set[str]:
    """Ordinary English words (the embedding model's own word list): a real word is never "corrected" ("weather" is not "leather")."""
    global _eng
    if _eng is None:
        _eng = set()
        try:
            from glob import glob
            from app.config import settings
            f = glob(str(Path(settings.home) / "models" / "models--Qdrant--bge-small*" / "snapshots" / "*" / "tokenizer.json"))
            _eng = {w for w in json.loads(Path(f[0]).read_text(encoding="utf-8"))["model"]["vocab"] if w.isalpha() and len(w) >= 3}
        except Exception:
            log.warning("English word list not found; typo correction works on document words only")
    return _eng


def _lev(a: str, b: str, cap: int) -> int:
    """Edit distance where two swapped neighbouring letters count once ("psased" -> "passed"), giving up (cap + 1) as soon as it exceeds cap."""
    prev2: list[int] | None = None
    prev = list(range(len(b) + 1))
    for i, ca in enumerate(a, 1):
        cur = [i] + [0] * len(b)
        for j, cb in enumerate(b, 1):
            cur[j] = min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (ca != cb))
            if prev2 is not None and i > 1 and j > 1 and ca == b[j - 2] and a[i - 2] == cb:
                cur[j] = min(cur[j], prev2[j - 2] + 1)
        if min(cur) > cap:
            return cap + 1
        prev2, prev = prev, cur
    return prev[-1]


def _fix_word(w: str) -> str:
    """A word no document holds and that is not English ("crosshed", "tensil", "abrassion") -> the closest document word."""
    rows, idx = _kw_index()
    if len(w) < 4 or not w.isalpha() or w in idx or w in _english():
        return w
    if _buckets["mtime"] != _kw["mtime"]:
        by: dict[str, list[str]] = {}
        for v, ids in idx.items():
            if len(v) >= 4 and len(ids) >= 2 and v.isalpha():
                by.setdefault(v[0], []).append(v)
        _buckets.update(mtime=_kw["mtime"], by=by)
    cap = 1 if len(w) <= 6 else 2
    best: tuple[tuple[int, int], str] | None = None
    for v in _buckets["by"].get(w[0], []):
        if abs(len(v) - len(w)) <= cap:
            d = _lev(w, v, cap)
            if d <= cap and (best is None or (d, -len(idx[v])) < best[0]):
                best = ((d, -len(idx[v])), v)
    return best[1] if best else w


def clean_question(question: str) -> str:
    """The question as the documents would word it: Taglish and chat words in English, filler dropped, typos corrected.
    Codes, numbers and words the documents use are kept exactly as typed."""
    toks = question.split()
    low = [re.sub(r"^\W+|\W+$", "", t).lower() for t in toks]
    tagalog = sum(t in _TAGALOG_HINT for t in low) >= 1
    out = []
    for raw, t in zip(toks, low):
        if t in TAGLISH and (tagalog or t in ("wat", "wats", "whats", "hw", "hwo", "abt", "wid", "pls", "plz", "thx")):
            if TAGLISH[t]:
                out.append(TAGLISH[t])
        elif tagalog and t in FILLER:
            continue
        elif t.isalpha():
            f = _fix_word(t)
            out.append(raw if f == t else f)
        else:
            out.append(raw)
    return " ".join(out) or question


def _cover(qw: list[str], text: str) -> float:
    """Share of the question's words in the text, a word also counting when its lab synonym is there."""
    if not qw:
        return 0.0
    from app.textutil import words as _w
    tw = " ".join(_w(text))
    hit = 0
    for q in qw:
        if coverage([q], text) > 0 or any(alt in tw for alt in LAB_SYN.get(q, [])):
            hit += 1
    return hit / len(qw)


def _weighted_cover(qw: list[str], text: str) -> float:
    """Like _cover, but a rare word ("balances", "rheometer") counts for more than one every document has ("checked", "day")."""
    import math
    rows, _ = _kw_index()
    n, sdf = max(1, len(rows)), _kw.get("sdf", {})
    tw = " ".join(words(text))
    total = got = 0.0
    for q in qw:
        df = sdf.get(q, 0)
        w = math.log((n + 1) / (df + 1)) if df else (0.0 if len(q) >= 4 else 3.0)   # a word no passage holds tells nothing
        total += w
        if coverage([q], text) > 0 or any(alt in tw for alt in LAB_SYN.get(q, [])):
            got += w
    return got / total if total else 0.0


def search(question: str, k: int = 20) -> list[dict[str, Any]]:
    try:
        if not store.client().collection_exists(COLLECTION):
            return []
    except Exception:
        return []
    question = clean_question(question)
    qw = words(question)
    hits = store.search(COLLECTION, question, k)
    # keyword pass: the meaning search can miss a sheet whose text is mostly another language or a table; every passage that literally
    # contains one of the question's distinctive words (a machine, a test, a material) is a candidate too
    seen = {h["payload"].get("pid") for h in hits}
    kws = _keywords(question)
    for kw in kws:
        for h in _with_word(question, kw, 12):
            if h["payload"].get("pid") not in seen:
                seen.add(h["payload"].get("pid"))
                hits.append(h)
    out = []
    for h in hits:
        p = dict(h["payload"])
        p["title"], p["loc"] = _en(p.get("title", "")), _en(p.get("loc", ""))
        names = f"{p['title']} {_en(p.get('file', ''))} {p['loc']} {p.get('folder', '')}"
        out.append({**p, "dense": h["score"], "names": names.lower(), "cover": _cover(qw, f"{names} {p.get('text', '')}")})
    if out and rerank.ready():
        rr = rerank.scores(question, [f"{c['title']} ({c['loc']}). {c['text'][:700]}" for c in out])
        if len(rr) == len(out):
            for c, r in zip(out, rr):
                c["rr"] = r
    rows, idx = _kw_index()
    named_kws = [kw for kw in kws if len(idx.get(kw, ())) < 0.07 * max(1, len(rows))]   # "equipment" names too many files to tell
    import math
    for c in out:
        rr = c.get("rr", 0.0)
        # a document whose own file or sheet name is the thing asked about ("Rheometer Testing Machine" for "maintain the rheometer")
        # by how many of the question's distinctive words the name holds: "Upper Synthetic Leather" over "Lining Synthetic Leather"
        c["named"] = (sum(kw in c["names"] for kw in named_kws) / len(named_kws)) if named_kws and c["cover"] >= 0.6 else 0.0
        raw = rr * min(1.0, c["cover"] / 0.6) + WEIGHTS["boost"] * c["named"]
        c["conf"] = round(min(1.0, raw), 4)
        c["wcover"] = _weighted_cover(qw, f"{c['names']} {c.get('text', '')}")
        c["logit"] = math.log(max(rr, 1e-9) / max(1 - rr, 1e-9))     # the reader's own score, which still separates two passages both near 1.0
        c["auth"] = _authority(c.get("folder", ""), c.get("file", ""))
        c["base"] = rr * min(1.0, c["cover"] / 0.6)
        c["ocr"] = 1.0 if "(OCR)" in c.get("loc", "") else 0.0      # read from a scan or photo: the text can hold OCR mistakes
        if "linear" in WEIGHTS:                                     # the ranker trained on generated and real questions (tools/train_lexus.py)
            c["rank"] = sum(float(w) * float(c.get(f, 0.0)) for f, w in WEIGHTS["linear"].items())
        else:
            c["rank"] = raw + WEIGHTS["logit"] * c["logit"] + WEIGHTS["cover"] * c["wcover"] + WEIGHTS["auth"] * c["auth"]
    out.sort(key=lambda c: c["rank"], reverse=True)
    return out


_ASKS_VALUE = re.compile(r"\b(what|how (much|many|long|often|far|thick|big)|which|when|size|speed|gap|temperature|humidity|time|weight|load|"
                         r"length|width|thickness|diameter|distance|number|serial|code|ilan|gaano|ano)\b", re.I)


def _key_lines(question: str, text: str, names: str = "") -> str:
    """The one or two lines of the passage that answer the question ("Set the crosshead speed to 300 mm/min"), when it asks for a
    value and such a line exists; "" otherwise, and the passage is shown as it is. The line must hold what is asked about (the
    jaw gap, the speed), not only the subject every line of the method shares (fabric, tensile), and a number."""
    if not _ASKS_VALUE.search(question):
        return ""
    import math
    rows, _ = _kw_index()
    n = max(1, len(rows))
    from app.textutil import stem
    common = _COMMON | {stem(c) for c in _COMMON}
    qw = [w for w in words(question) if w not in common and len(w) >= 3]
    attr = [w for w in qw if _cover([w], names) == 0] if names else qw
    if not attr:
        return ""

    def weight(w: str) -> float:
        df = _kw.get("sdf", {}).get(w, 0)
        return math.log((n + 1) / (df + 1)) if df else 0.5

    def number(s: str) -> bool:
        return bool(re.search(r"\d", re.sub(r"^\s*\d+\s*[.)]\s*", "", s)))     # "1. Tensile machine": a list number, not a value
    total = sum(weight(w) for w in attr)
    lines = [x for x in _lines(text) if 4 <= len(x) <= 260]
    scored = []
    for i, ln in enumerate(lines):
        got = sum(weight(w) for w in attr if _cover([w], ln) > 0)
        if got and not number(ln) and i + 1 < len(lines) and number(lines[i + 1]):
            ln = f"{ln} {lines[i + 1]}"                              # "Set the crosshead speed to" | "100mm/minute ..." (a wrapped line)
        if got and number(ln):
            scored.append((got / total, i, ln))
    if not scored:
        return ""
    scored.sort(key=lambda x: (-x[0], x[1]))
    best = scored[0][0]
    if best < 0.34:
        return ""
    pick = sorted((x for x in scored[:2] if x[0] >= best - 0.15), key=lambda x: x[1])
    return "\n".join(f"► {ln}" for _, _, ln in pick)


# ---- clarification: "which one do you mean?" and the reply to it ---------------------------------------------------

_pending: dict[str, dict[str, Any]] = {}          # session -> {"q": question, "options": [candidate, ...], "t": time}
PENDING_SECONDS = 900
_CHOICE = re.compile(r"^\W*(?:(?:no|number|num|option|choice|yung|ung|the|si|sa)\.?\s*)?#?\s*([1-5])(?:st|nd|rd|th)?\s*(?:one|po|lang|pls|please)?\W*$"
                     r"|^\W*(?:the\s+|yung\s+|ung\s+)?(first|second|third|fourth|fifth|una|pangalawa|ikalawa|pangatlo|ikatlo)\b", re.I)
_ORD = {"first": 1, "una": 1, "second": 2, "pangalawa": 2, "ikalawa": 2, "third": 3, "pangatlo": 3, "ikatlo": 3, "fourth": 4, "fifth": 5}
# a reply that narrows the last question ("the rubber one", "for leather", "i mean the Martindale") rather than asking a new one
_NARROW = re.compile(r"^\W*(i mean|no,? i mean|i meant|not that|the|for|in|on|with|about|yung|ung|sa|para sa|ung sa|yung sa)\b", re.I)


def _subject(c: dict[str, Any]) -> str:
    return re.sub(r"\s+", " ", SUPERSEDED.sub(" ", re.sub(r"\d{4}|\(\d+\)|[-_.]", " ", c["title"].lower()))).strip()


def _options(found: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Up to three different documents that could hold the answer, best first (copies and old versions of one document count once)."""
    opts, seen = [], set()
    top = found[0]["conf"]
    for c in found[:12]:
        s = _subject(c)
        if s in seen or (opts and (c["conf"] < max(0.25, top - 0.3) or SUPERSEDED.search(c["file"]))):
            continue
        seen.add(s)
        opts.append({k: c.get(k) for k in ("pid", "folder", "file", "path", "loc", "title", "text", "conf", "rr", "cover", "wcover")})
        if len(opts) == 3:
            break
    return opts


def _remember_options(session_id: str, question: str, opts: list[dict[str, Any]]) -> None:
    if not session_id:
        return
    if len(_pending) > 500:
        for s in sorted(_pending, key=lambda s: _pending[s]["t"])[:250]:
            _pending.pop(s, None)
    _pending[session_id] = {"q": question, "options": opts, "t": time.time()}


def choose(session_id: str, reply: str) -> dict[str, Any] | None:
    """The answer to a reply to "which one do you mean?": a number ("2", "the second one") opens that document; a short narrowing
    ("the rubber one", "for leather") searches the earlier question again with it. None when the reply is a new question."""
    p = _pending.get(session_id or "")
    if not p or time.time() - p["t"] > PENDING_SECONDS:
        return None
    m = _CHOICE.match(reply.strip())
    if m:
        n = int(m.group(1)) if m.group(1) else _ORD[m.group(2).lower()]
        if 1 <= n <= len(p["options"]):
            c = dict(p["options"][n - 1])
            _pending.pop(session_id, None)
            out = _reply(p["q"], c, [c])
            out["confidence"] = round(max(out["confidence"], THRESHOLD), 3)      # the user picked it
            out["chosen"] = n
            return out
        return None
    if len(words(reply)) <= 4 and (_NARROW.match(reply) or len(words(reply)) <= 2):
        _pending.pop(session_id, None)
        out = answer(f"{p['q']} {reply}", session_id)
        if out:
            out["narrowed"] = p["q"]
        return out
    return None


def answer(question: str, session_id: str = "") -> dict[str, Any] | None:
    """The best passage as an answer, or None when no passage is clearly about the question. When other documents fit too, they
    are offered by number; when no passage is clear enough but some are close, the answer is the question "which one do you mean?"."""
    question = clean_question(question)
    found = search(question)
    if not found:
        return None
    out = _reply(question, found[0], found)
    opts = _options(found)
    top = found[0]
    if out["confidence"] < THRESHOLD:
        # close but not clear, and about the lab (the reader finds it relevant and the documents hold the question's rare words):
        # ask instead of giving up. A question that is not about the lab scores far below this and is still refused.
        if session_id and len(opts) >= 2 and top.get("rr", 0.0) >= 0.2 and top.get("wcover", 0.0) >= 0.5:
            _remember_options(session_id, question, opts)
            lines = "\n".join(f"{i}) {o['file']}, {o['loc']}" for i, o in enumerate(opts, 1))
            out["clarify"] = True
            out["text"] = (f"I am not sure which document you mean. These may hold it:\n\n{lines}\n\n"
                           "Reply with the number, or add a detail (the machine, test or material).")
        return out
    if len(opts) >= 2:
        _remember_options(session_id, question, opts)
        more = "\n".join(f"{i}) {o['file']}, {o['loc']}" for i, o in enumerate(opts[1:], 2))
        out["text"] += f"\n\nNot the one you meant? Reply with the number:\n{more}"
        out["options"] = [{"n": i, "file": o["file"], "loc": o["loc"]} for i, o in enumerate(opts, 1)]
    return out


def _reply(question: str, top: dict[str, Any], found: list[dict[str, Any]]) -> dict[str, Any]:
    """The answer built from one passage (joined with its neighbour when a record is cut in two), naming where it comes from."""
    second = found[1]["conf"] if len(found) > 1 else 0.0
    conf = top["conf"]
    if second > 0 and conf - second < 0.02 and found[1]["file"] != top["file"]:
        conf *= 0.95                                               # two different documents fit about equally well
    text = top["text"]
    # a record or procedure cut across two passages ("April 3, 2025 ... Bonding Testing Room" | "... root cause, action taken"):
    # when the reader finds the passage before or after this one about as relevant, the answer shows both
    near = {c.get("pid"): c for c in found[1:6] if c["conf"] >= max(THRESHOLD, top["conf"] - 0.05)}
    after = _next_of(top.get("pid", ""))
    before = next((c for pid, c in near.items() if (_next_of(pid) or {}).get("id") == top.get("pid")), None)
    prev = _prev_of(top.get("pid", ""))
    if not before and prev and top["text"][:1].islower() and _cover(words(question), prev["text"]) >= 0.6:
        before = prev                                             # the passage starts mid-record; the record's start is just before
    missing = [w for w in words(question) if _cover([w], f"{top['title']} {top['loc']} {text}") == 0]
    if before:
        text = _join(before["text"], text)
    elif after and after["id"] in near:
        text = _join(text, after["text"])
    elif missing and after and _cover(missing, after["text"]) > 0:
        text = _join(text, after["text"])                         # what was asked ("the speed") is in the next lines of the same part
    elif missing and prev and _cover(missing, prev["text"]) > 0:
        text = _join(prev["text"], text)
    excerpt = _excerpt(words(question), text, max_chars=1000 if text is top["text"] else 1800)
    source = f"{top['folder']} > {top['file']}, {top['loc']}"
    key = _key_lines(question, text, f"{top['title']} {_en(top['file'])} {top['loc']}")
    body = f"{key}\n\nIn the document:\n{excerpt}" if key else excerpt
    return {
        "confidence": round(conf, 3),
        "text": f"From the lab documents:\n\n{body}\n\nSource: {source}",
        "source": {"folder": top["folder"], "file": top["file"], "path": top["path"], "loc": top["loc"]},
        "excerpt": excerpt,
        "candidates": [{"pid": str(c.get("pid", ""))[:10], "file": c["file"], "loc": c["loc"], "conf": c["conf"], "rr": round(c.get("rr", 0.0), 3),
                        "cover": round(c["cover"], 2), "wcover": round(c.get("wcover", 0.0), 2)} for c in found[:8]],
    }
