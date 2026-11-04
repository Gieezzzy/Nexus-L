"""Reads the #DTP-Lab-101 VF Test Methods folder and turns every test method PDF into an editable document (JSON blocks).

    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\vfm_convert.py

For every "VF - A01 Hardness Dec 2016.pdf":
  - the text is rebuilt as paragraphs (bold, italic, colour, size, list indent and centring are kept), tables as tables, pictures as
    image files under public/assets/vfm/<CODE>/ (so a method can be shown as a normal form that keeps its format, and be edited)
  - the PDF itself (and the documents of the folder and the Illustrated Test Methods) is copied to writable/labdocs/lab101/, so the
    web server does not depend on the share to open them
Output: E:\\NexusAI\\vfm\\index.json and one E:\\NexusAI\\vfm\\<CODE>.json per method; `php spark vfm:import` loads them into dbnexus.
Nothing is written to the share.
"""
from __future__ import annotations

import html
import json
import os
import re
import shutil
import sys
from pathlib import Path

import pymupdf

sys.stdout.reconfigure(encoding="utf-8")
SRC = "//192.168.32.130/qc lab files/#Lab Documents/#DTP-Lab-101 VF Test Methods Updated 2025"
ROOT = Path(__file__).resolve().parents[2]
OUT = Path(r"E:\NexusAI\vfm")
ASSETS = ROOT / "public" / "assets" / "vfm"
CACHE = ROOT / "writable" / "labdocs" / "lab101"
MONTHS = {m: i for i, m in enumerate(["jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec"], 1)}
MARKER = re.compile(r"^(?:\(?\d{1,2}[.)]|\(?[A-Za-z][.)]|[IVXivx]{1,4}\.|[\u2022\u00b7\u25aa\u2023\u25e6\u2013\u2014\-o]\s|[\u2022\u00b7\uf0b7\uf0a7])\s*")


def rev_of(name: str) -> tuple[str, str | None]:
    """("Dec 2016", "2016-12-01") from the file name."""
    m = re.search(r"(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s*(?:\d{1,2},\s*)?(\d{4})", name, re.I)
    if not m:
        return "", None
    mo = MONTHS[m.group(1)[:3].lower()]
    return f"{m.group(1).title()} {m.group(2)}", f"{m.group(2)}-{mo:02d}-01"


def span_html(sp: dict, base: float) -> str:
    t = html.escape(sp["text"].replace("\u00a0", " "))
    if not t.strip():
        return t
    f, name = sp["flags"], sp.get("font", "")
    bold = bool(f & 16) or "bold" in name.lower() or "black" in name.lower()
    ital = bool(f & 2) or "italic" in name.lower() or "oblique" in name.lower()
    style = []
    col = sp.get("color", 0)
    if col and col != 0:
        style.append("color:#%06x" % col)
    if abs(sp["size"] - base) >= 1.2:
        style.append("font-size:%.1fpt" % sp["size"])
    out = t
    if style:
        out = f'<span style="{";".join(style)}">{out}</span>'
    if ital:
        out = f"<i>{out}</i>"
    if bold:
        out = f"<b>{out}</b>"
    return out


def merge_html(parts: list[str]) -> str:
    s = "".join(parts)
    return re.sub(r"</b>(\s*)<b>", r"\1", re.sub(r"</i>(\s*)<i>", r"\1", s))


def convert(path: str, code: str) -> dict:
    doc = pymupdf.open(path)
    adir = ASSETS / code
    shutil.rmtree(adir, ignore_errors=True)
    sizes: dict[float, int] = {}
    for p in doc:
        for b in p.get_text("dict")["blocks"]:
            for ln in b.get("lines", []):
                for sp in ln["spans"]:
                    sizes[round(sp["size"], 1)] = sizes.get(round(sp["size"], 1), 0) + len(sp["text"])
    base = max(sizes, key=sizes.get) if sizes else 10.0
    margin = None
    blocks: list[dict] = []
    for pno, page in enumerate(doc):
        W, H = page.rect.width, page.rect.height
        d = page.get_text("dict")
        try:
            tabs = page.find_tables().tables
        except Exception:
            tabs = []
        trects = [pymupdf.Rect(t.bbox) for t in tabs]
        items = []                                                        # (y0, x0, kind, payload)
        for t, rect in zip(tabs, trects):
            rows = [[(c or "").strip() for c in r] for r in t.extract()]
            if rows and any(any(c for c in r) for r in rows):
                items.append((rect.y0, rect.x0, "table", {"rows": [[html.escape(c).replace(chr(10), "<br>") for c in r] for r in rows], "box": False}))
        k = 0
        for b in d["blocks"]:
            x0, y0, x1, y1 = b["bbox"]
            if b["type"] == 1:
                if y1 < 100 or (x1 - x0) < 18 or (y1 - y0) < 18 or (y0 > H - 60):
                    continue                                              # logo, rules, footer marks
                ext = b.get("ext") or "png"
                if ext not in ("png", "jpeg", "jpg", "gif"):
                    continue
                adir.mkdir(parents=True, exist_ok=True)
                k += 1
                fn = f"p{pno + 1}_{k}.{ext}"
                (adir / fn).write_bytes(b["image"])
                items.append((y0, x0, "img", {"src": f"{code}/{fn}", "w": round(x1 - x0), "h": round(y1 - y0)}))
                continue
            for ln in b.get("lines", []):
                lx0, ly0, lx1, ly1 = ln["bbox"]
                c = pymupdf.Point((lx0 + lx1) / 2, (ly0 + ly1) / 2)
                if any(r.contains(c) for r in trects):
                    continue
                text = "".join(s["text"] for s in ln["spans"]).strip()
                if not text:
                    continue
                if ly1 < 100 and pno > 0:
                    continue                                              # repeated page header
                if ly0 > H - 62 and (re.fullmatch(r"\d{1,3}", text) or "Performance Testing Manual" in text or "Laboratory Testing Manual" in text):
                    continue                                              # page footer
                if pno == 0 and ly1 < 100 and "Laboratory Testing Manual" in text:
                    continue
                items.append((ly0, lx0, "line", ln))
        items.sort(key=lambda it: (round(it[0] / 2), it[1]))
        if pno == 0:                                                      # the box at the top of the first page (title | method number, date, author)
            first = next((i for i, it in enumerate(items) if it[2] == "line" and re.match(r"(PURPOSE|SCOPE|OBJECTIVE|EQUIPMENT)", "".join(sp["text"] for sp in it[3]["spans"]).strip(), re.I)), None)
            if first:
                head = [it for it in items[:first] if it[2] == "line"]
                right = [it for it in head if it[1] > W * 0.45]
                left = [it for it in head if it[1] <= W * 0.45]
                if len(right) >= 2 and left:
                    def col(ls):
                        out = []
                        for it in sorted(ls, key=lambda i: i[0]):
                            plain = "".join(sp["text"] for sp in it[3]["spans"]).strip()
                            h = merge_html([span_html(sp, base) for sp in it[3]["spans"]]).strip()
                            if out and plain[:1].islower():
                                out[-1] += " " + h                          # a wrapped line of the same sentence
                            else:
                                out.append(h)
                        return "<br>".join(out)
                    box = (head[0][0], 0, "table", {"rows": [[col(left), col(right)]], "box": True})
                    items = [box] + [it for it in items[:first] if it[2] != "line"] + items[first:]
        for it in items:
            if it[2] == "line":
                margin = it[1] if margin is None else min(margin, it[1])
        prev = None                                                       # the open paragraph: dict + last line geometry
        for y0, x0, kind, payload in items:
            if kind in ("img", "table"):
                prev = None
                blocks.append({"t": kind, **({"src": payload["src"], "w": payload["w"], "h": payload["h"]} if kind == "img" else {"rows": payload["rows"], "box": payload["box"]})})
                continue
            ln = payload
            lx0, ly0, lx1, ly1 = ln["bbox"]
            plain = "".join(s["text"] for s in ln["spans"]).strip()
            htm = merge_html([span_html(s, base) for s in ln["spans"]]).strip()
            size = max(s["size"] for s in ln["spans"])
            is_marker = bool(MARKER.match(plain)) and len(plain) > 2
            gap_ok = prev is not None and -4 <= ly0 - prev["_y1"] < max(4.0, 0.75 * size)
            narrow = (lx1 - lx0) < 0.6 * (W - 2 * (margin or 72))
            centre = abs((lx0 + lx1) / 2 - W / 2) < 14 and lx0 > (margin or 72) + 24 and narrow and not gap_ok       # a centred title, not the last lines of a paragraph
            cont = (gap_ok and not is_marker and (lx0 >= prev["_x0"] - 3) and not centre and not prev.get("al") == "c")
            if cont:
                prev["html"] = prev["html"] + " " + htm
                prev["_y1"] = ly1
                continue
            blk = {"t": "p", "ind": max(0, round(lx0 - (margin or 72))), "list": is_marker, "al": "c" if centre else "l", "sz": round(size, 1), "html": htm,
                   "_y1": ly1, "_x0": lx0}
            blocks.append(blk)
            prev = blk
    for b in blocks:
        b.pop("_y1", None)
        b.pop("_x0", None)
    return {"base": base, "pages": len(doc), "blocks": blocks, "margin": round(margin or 72)}


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    CACHE.mkdir(parents=True, exist_ok=True)
    ASSETS.mkdir(parents=True, exist_ok=True)
    names = sorted(os.listdir(SRC))
    # method titles and sections come from "List of Test Methods.xlsx"
    import openpyxl
    wb = openpyxl.load_workbook(SRC + "/List of Test Methods.xlsx", data_only=True)
    ws = wb["DTP LAB 2025"]
    titles, sections, section = {}, {}, ""
    for r in ws.iter_rows(values_only=True):
        a = str(r[0] or "").strip()
        if a.upper().startswith("SECTION"):
            section = a
            sections[a[8:9]] = re.sub(r"\s+", " ", a.split(":", 1)[1]).strip() if ":" in a else a
        elif re.fullmatch(r"VF-[A-J]\d{2}", a):
            titles[a] = next((str(c).strip() for c in r[1:] if c), "")
    methods, illus = [], []
    for fn in names:
        m = re.match(r"^VF\s*-\s*([A-J])\s*(\d{2})\s+(.*)\.pdf$", fn, re.I)
        if not m:
            continue
        code = f"VF-{m.group(1).upper()}{m.group(2)}"
        label, date = rev_of(fn)
        print(code, fn[:60], flush=True)
        conv = convert(SRC + "/" + fn, code)
        shutil.copyfile(SRC + "/" + fn, CACHE / fn)
        (OUT / f"{code}.json").write_text(json.dumps(conv, ensure_ascii=False), encoding="utf-8")
        methods.append({"code": code, "series": m.group(1).upper(), "title": titles.get(code) or re.sub(r"\s*[-,]?\s*(?:[A-Za-z]+\.?\s*\d{0,2},?\s*\d{4}).*$", "", m.group(3)).strip(" -"),
                        "file_name": fn, "rev_label": label, "rev_date": date, "pages": conv["pages"], "mtime": int(os.path.getmtime(SRC + "/" + fn))})
    for fn in sorted(os.listdir(SRC + "/Illustrated Test Methods")):
        m = re.match(r"^Illustrated\s+(VF-?\s*[A-J]\s*\d{2})\s*(.*)\.pdf$", fn, re.I)
        if m:
            code = re.sub(r"[\s]", "", m.group(1)).upper().replace("VF-", "VF").replace("VF", "VF-")
            os.makedirs(CACHE / "illustrated", exist_ok=True)
            shutil.copyfile(SRC + "/Illustrated Test Methods/" + fn, CACHE / "illustrated" / fn)
            illus.append({"code": code, "label": m.group(2).strip() or "Illustrated", "file_name": fn, "size": os.path.getsize(SRC + "/Illustrated Test Methods/" + fn),
                          "mtime": int(os.path.getmtime(SRC + "/Illustrated Test Methods/" + fn))})
    docs = []
    wanted = [("manual", "Vans Footwear Performance Testing Manual", "VANS  Footwear Performance Testing Manual 2026.pdf"),
              ("machine", "Universal Machine Test Parameters", "UNIVERSAL MACHINE TEST PARAMETERS.xlsx"),
              ("temp", "Testing Parameters w Temp", "Testing Parameters w Temp.pdf"),
              ("list", "List of Test Methods", "List of Test Methods.xlsx"),
              ("guidelines", "Guidelines Phy Prop", "Guidelines for Phy Prop Testing.xlsx"),
              ("amend", "2026 Test Method Amendments", "2026 Test Method Ammendment Email Announcement.docx"),
              ("sticker", "Side Stickers", "vf test method side sticker.xlsx")]
    for key, label, fn in wanted:
        if fn in names:
            shutil.copyfile(SRC + "/" + fn, CACHE / fn)
            docs.append({"key": key, "label": label, "file_name": fn, "kind": fn.rsplit(".", 1)[1].lower(), "size": os.path.getsize(SRC + "/" + fn), "mtime": int(os.path.getmtime(SRC + "/" + fn))})
    index = {"folder": "#DTP-Lab-101 VF Test Methods Updated 2025", "sections": sections, "methods": methods, "illustrated": illus, "docs": docs}
    (OUT / "index.json").write_text(json.dumps(index, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"{len(methods)} methods, {len(illus)} illustrated, {len(docs)} documents")


if __name__ == "__main__":
    main()
