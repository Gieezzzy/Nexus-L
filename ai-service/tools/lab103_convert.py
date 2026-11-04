"""Reads #DTP-Lab-103 Material Specification Sheet: every specification PDF becomes an editable document (JSON blocks, tables keep their
merged cells, widths and colours), the PDFs and the two Excel files are copied to writable/labdocs/lab103/.

    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\lab103_convert.py

Output: E:\\NexusAI\\vfm\\lab103\\index.json and one <slug>.json per PDF; `php spark labdocs:import-specs` loads them into dbnexus.
Pictures go to public/assets/vfm/<slug>/. Nothing is written to the share.
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

sys.path.insert(0, str(Path(__file__).resolve().parent))
import vfm_convert as V                                              # noqa: E402

sys.stdout.reconfigure(encoding="utf-8")
SRC = "//192.168.32.130/qc lab files/#Lab Documents/#DTP-Lab-103 Material Specification Sheet"
OUT = Path(r"E:\NexusAI\vfm\lab103")
CACHE = V.ROOT / "writable" / "labdocs" / "lab103"


def snap(vals: list[float], tol: float = 2.0) -> list[float]:
    out: list[float] = []
    for v in sorted(vals):
        if not out or v - out[-1] > tol:
            out.append(v)
    return out


def idx(lines: list[float], v: float) -> int:
    return min(range(len(lines)), key=lambda i: abs(lines[i] - v))


def hexcol(c) -> str | None:
    if not c or len(c) < 3:
        return None
    if min(c[:3]) > 0.96:
        return None
    return "#%02x%02x%02x" % tuple(int(round(x * 255)) for x in c[:3])


def cell_html(lines: list[dict], base: float) -> tuple[str, bool]:
    """The lines inside one cell as html (a new paragraph = <br>), and whether they are centred."""
    parts, prev = [], None
    for ln in sorted(lines, key=lambda l: (round(l["bbox"][1]), l["bbox"][0])):
        h = V.merge_html([V.span_html(s, base) for s in ln["spans"]]).strip()
        if not h:
            continue
        size = max(s["size"] for s in ln["spans"])
        if prev is not None and ln["bbox"][1] - prev["bbox"][3] < max(2.0, 0.55 * size) and ln["bbox"][1] >= prev["bbox"][1]:
            parts[-1] += " " + h
        else:
            parts.append(h)
        prev = ln
    return "<br>".join(parts), False


def build_table(t, textlines: list[dict], fills: list, base: float) -> dict | None:
    cells = [pymupdf.Rect(c) for c in t.cells if c]
    if len(cells) < 2:
        return None
    xs = snap([c.x0 for c in cells] + [c.x1 for c in cells])
    ys = snap([c.y0 for c in cells] + [c.y1 for c in cells])
    nR = len(ys) - 1
    if nR < 1 or len(xs) < 2:
        return None
    rows: list[list[dict]] = [[] for _ in range(nR)]
    for c in sorted(cells, key=lambda c: (c.y0, c.x0)):
        r0, r1, c0, c1 = idx(ys, c.y0), idx(ys, c.y1), idx(xs, c.x0), idx(xs, c.x1)
        if r1 <= r0 or c1 <= c0:
            continue
        inner = pymupdf.Rect(c.x0 + 0.8, c.y0 + 0.8, c.x1 - 0.8, c.y1 - 0.8)
        ls = [l for l in textlines if inner.contains(pymupdf.Point((l["bbox"][0] + l["bbox"][2]) / 2, (l["bbox"][1] + l["bbox"][3]) / 2))]
        h, _ = cell_html(ls, base)
        centre = bool(ls) and all(abs((l["bbox"][0] + l["bbox"][2]) / 2 - (c.x0 + c.x1) / 2) < 6 for l in ls) and (c.x1 - c.x0) > 28
        bg = None
        best = None
        for fr, col in fills:
            if fr.contains(pymupdf.Point((c.x0 + c.x1) / 2, (c.y0 + c.y1) / 2)) and 0.45 * c.get_area() <= fr.get_area() <= 1.35 * c.get_area():
                if best is None or fr.get_area() < best:
                    best, bg = fr.get_area(), hexcol(col)
        cell = {"h": h, "cs": c1 - c0, "rs": r1 - r0, "c": c0}
        if bg:
            cell["bg"] = bg
        if centre:
            cell["al"] = "c"
        rows[r0].append(cell)
    for r in rows:
        r.sort(key=lambda x: x["c"])
        for x in r:
            x.pop("c", None)
    total = xs[-1] - xs[0]
    return {"t": "table", "spec": True, "box": False, "plain": False, "cw": [round(100 * (xs[i + 1] - xs[i]) / total, 2) for i in range(len(xs) - 1)],
            "rh": [round(ys[i + 1] - ys[i], 1) for i in range(nR)], "rows": rows, "span": any(x["cs"] > 1 or x["rs"] > 1 for r in rows for x in r)}


def convert_spec(path: str, slug: str) -> dict:
    doc = pymupdf.open(path)
    adir = V.ASSETS / slug
    shutil.rmtree(adir, ignore_errors=True)
    sizes: dict[float, int] = {}
    for p in doc:
        for b in p.get_text("dict")["blocks"]:
            for ln in b.get("lines", []):
                for sp in ln["spans"]:
                    sizes[round(sp["size"], 1)] = sizes.get(round(sp["size"], 1), 0) + len(sp["text"])
    base = max(sizes, key=sizes.get) if sizes else 7.0
    W0, H0 = doc[0].rect.width, doc[0].rect.height
    blocks: list[dict] = []
    header_title = ""
    margin = None
    for pno, page in enumerate(doc):
        W, H = page.rect.width, page.rect.height
        d = page.get_text("dict")
        try:
            tabs = page.find_tables().tables
        except Exception:
            tabs = []
        fills = []
        for dr in page.get_drawings():
            if dr.get("fill") and hexcol(dr["fill"]) and dr.get("rect") is not None:
                r = pymupdf.Rect(dr["rect"])
                if r.get_area() > 20:
                    fills.append((r, dr["fill"]))
        textlines = [ln for b in d["blocks"] if b["type"] == 0 for ln in b.get("lines", []) if "".join(s["text"] for s in ln["spans"]).strip()]
        trects = [pymupdf.Rect(t.bbox) for t in tabs]
        items = []
        for t in tabs:
            tb = build_table(t, textlines, fills, base)
            if tb:
                items.append((t.bbox[1], t.bbox[0], "table", tb))
        k = 0
        for b in d["blocks"]:
            x0, y0, x1, y1 = b["bbox"]
            if b["type"] == 1:
                if y1 < 100 or (x1 - x0) < 18 or (y1 - y0) < 18 or y0 > H - 40:
                    continue
                ext = b.get("ext") or "png"
                if ext not in ("png", "jpeg", "jpg", "gif"):
                    continue
                adir.mkdir(parents=True, exist_ok=True)
                k += 1
                fn = f"p{pno + 1}_{k}.{ext}"
                (adir / fn).write_bytes(b["image"])
                items.append((y0, x0, "img", {"src": f"{slug}/{fn}", "w": round(x1 - x0), "h": round(y1 - y0)}))
                continue
            for ln in b.get("lines", []):
                lx0, ly0, lx1, ly1 = ln["bbox"]
                text = "".join(s["text"] for s in ln["spans"]).strip()
                if not text:
                    continue
                c = pymupdf.Point((lx0 + lx1) / 2, (ly0 + ly1) / 2)
                if any(r.contains(c) for r in trects):
                    continue
                if ly1 < 92:                                              # the title next to the logo
                    if pno == 0:
                        size = max(s["size"] for s in ln["spans"])
                        if size >= 10 and len(text) > len(header_title):
                            header_title = text
                    continue
                if ly0 > H - 36 and re.fullmatch(r"\d{1,3}|Page \d+( of \d+)?", text):
                    continue
                items.append((ly0, lx0, "line", ln))
        items.sort(key=lambda it: (round(it[0] / 2), it[1]))
        if pno == 0:                                                      # the header lines above the first table: left | right, as a table without borders
            ft = next((i for i, it in enumerate(items) if it[2] == "table"), None)
            if ft:
                head = [it for it in items[:ft] if it[2] == "line"]
                right = [it for it in head if it[1] > W * 0.5]
                left = [it for it in head if it[1] <= W * 0.5]
                if head and (right or left):
                    def col(ls):
                        out = []
                        for it in sorted(ls, key=lambda i: (round(i[0] / 2), i[1])):
                            h = V.merge_html([V.span_html(sp, base) for sp in it[3]["spans"]]).strip()
                            if out and it[0] - prev_y < 3.5:
                                out[-1] += " " + h
                            else:
                                out.append(h)
                            prev_y = it[3]["bbox"][3]
                        return "<br>".join(out)
                    prev_y = -99.0
                    lh, rh = col(left), col(right)
                    box = (head[0][0], 0, "table", {"t": "table", "spec": True, "box": False, "plain": True, "cw": [50, 50], "rh": [], "span": False,
                                                    "rows": [[{"h": lh, "cs": 1, "rs": 1}, {"h": rh, "cs": 1, "rs": 1}]]})
                    items = [box] + [it for it in items[:ft] if it[2] != "line"] + items[ft:]
        for it in items:
            if it[2] == "line":
                margin = it[1] if margin is None else min(margin, it[1])
        prev = None
        for y0, x0, kind, payload in items:
            if kind == "table":
                prev = None
                blocks.append(payload)
                continue
            if kind == "img":
                prev = None
                blocks.append({"t": "img", **payload})
                continue
            ln = payload
            lx0, ly0, lx1, ly1 = ln["bbox"]
            plain = "".join(s["text"] for s in ln["spans"]).strip()
            htm = V.merge_html([V.span_html(s, base) for s in ln["spans"]]).strip()
            size = max(s["size"] for s in ln["spans"])
            gap_ok = prev is not None and -4 <= ly0 - prev["_y1"] < max(4.0, 0.75 * size)
            cont = gap_ok and not V.MARKER.match(plain) and lx0 >= prev["_x0"] - 3
            if cont:
                prev["html"] += " " + htm
                prev["_y1"] = ly1
                continue
            blk = {"t": "p", "ind": max(0, round(lx0 - (margin or 30))), "list": bool(V.MARKER.match(plain)) and len(plain) > 2, "al": "l", "sz": round(size, 1), "html": htm, "_y1": ly1, "_x0": lx0}
            blocks.append(blk)
            prev = blk
    for b in blocks:
        b.pop("_y1", None)
        b.pop("_x0", None)
    return {"base": base, "pages": len(doc), "margin": round(margin or 30), "orient": "landscape" if W0 > H0 else "portrait", "headerTitle": header_title, "blocks": blocks}


def name_year(fn: str) -> tuple[str, str]:
    stem = re.sub(r"\.pdf$", "", fn, flags=re.I)
    year = ""
    m = re.search(r"(?:20\d{2}|19\d{2})", stem)
    if m:
        year = m.group(0)
    name = re.sub(r"^VF Material Specification\s*", "", stem)
    name = re.sub(r"[\s-]*(?:Jun(?:e)?\s*)?(?:20\d{2}|19\d{2})(?:\.\d{2}\.\d{2})?[\s-]*$", "", name).strip(" -")
    name = re.sub(r"\s+", " ", name)
    return name or stem, year


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    CACHE.mkdir(parents=True, exist_ok=True)
    cards, docs = [], []
    for fn in sorted(os.listdir(SRC)):
        p = SRC + "/" + fn
        low = fn.lower()
        if low.endswith(".pdf"):
            name, year = name_year(fn)
            slug = re.sub(r"[^A-Za-z0-9]+", "-", name).strip("-").upper()[:60] or "DOC"
            n = 2
            while any(c["slug"] == slug for c in cards):
                slug = f"{slug[:56]}-{n}"
                n += 1
            print(slug, "|", name, "|", year, flush=True)
            conv = convert_spec(p, slug)
            shutil.copyfile(p, CACHE / fn)
            (OUT / f"{slug}.json").write_text(json.dumps(conv, ensure_ascii=False), encoding="utf-8")
            cards.append({"slug": slug, "name": name, "year": year, "file_name": fn, "pages": conv["pages"], "mtime": int(os.path.getmtime(p))})
        elif low.endswith(".xlsx") and not fn.startswith("~$"):
            shutil.copyfile(p, CACHE / fn)
            key = "template" if "template" in low else "record"
            docs.append({"key": key, "label": "Amendment Record Sheet Template" if key == "template" else "Material Specification Amendment Record Sheet", "file_name": fn,
                         "kind": "xlsx", "size": os.path.getsize(p), "mtime": int(os.path.getmtime(p))})
    (OUT / "index.json").write_text(json.dumps({"folder": "#DTP-Lab-103 Material Specification Sheet", "cards": cards, "docs": docs}, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"{len(cards)} specifications, {len(docs)} Excel documents")


if __name__ == "__main__":
    main()
