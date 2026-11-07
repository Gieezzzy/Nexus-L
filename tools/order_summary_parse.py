"""Read one #ORDER SUMMARY PDF (MTL running-style lists) and print JSON.

usage: python order_summary_parse.py <file.pdf>

Each page is a table with its own header and ruled columns, and the layout
differs between pages (some have Season/Material columns, some only the
F26/H26/S27 tick columns). So per page: find the header line (the word
starting with "MTL"), cut columns at the vertical rules, name them from the
header words, and anchor every row on its MTL code. Nothing is guessed: a
cell that is empty stays empty.
"""
import json
import re
import sys

try:
    import pymupdf
except ImportError:  # older installs expose it as fitz
    import fitz as pymupdf

MTL_RE = re.compile(r"^VN0[0-9A-Z]{7,}(?:-[A-Z]+)?$")
SEASON_COL_RE = re.compile(r"^[FHSW]\d{2}$")  # F26 / H26 / S27 tick columns


def vrules(page):
    xs = set()
    for d in page.get_drawings():
        r = d["rect"]
        if r.width < 2 and r.height > 20:
            xs.add(round(r.x0))
    out = []
    for x in sorted(xs):  # merge rules closer than 3pt
        if not out or x - out[-1] > 3:
            out.append(x)
    return out


def parse_page(page, prev_title):
    words = page.get_text("words")
    ws = [(w[0], w[1], w[2], w[3], w[4], (w[1] + w[3]) / 2) for w in words if w[3] < page.rect.height - 28]  # skip page footer
    mtl_hdr = [w for w in ws if w[4].upper().startswith("MTL") and not MTL_RE.match(w[4])]
    if not mtl_hdr:
        return prev_title, []
    hy = min(w[5] for w in mtl_hdr)
    # section title = text line above the header
    above = sorted([w for w in ws if w[5] < hy - 6 and w[5] < 40], key=lambda w: (round(w[5]), w[0]))
    title = " ".join(w[4] for w in above).strip().split(" Legend")[0] or prev_title
    rules = vrules(page)
    if len(rules) < 3:
        return title, []
    cols = list(zip(rules[:-1], rules[1:]))

    def col_of(xc):
        for i, (a, b) in enumerate(cols):
            if a - 1 <= xc < b + 1:
                return i
        return -1

    # header cells: header band is the words within 12pt below/around hy
    names = {}
    for w in sorted(ws, key=lambda w: (round(w[5]), w[0])):
        if hy - 8 <= w[5] <= hy + 7:
            i = col_of((w[0] + w[2]) / 2)
            if i >= 0:
                names.setdefault(i, []).append(w[4])
    heads = {i: " ".join(v).replace("#", "").strip() for i, v in names.items()}
    anchors = sorted(
        [w for w in ws if MTL_RE.match(w[4]) and w[5] > hy], key=lambda w: w[5]
    )
    if not anchors:
        return title, []
    mids = [(anchors[i][5] + anchors[i + 1][5]) / 2 for i in range(len(anchors) - 1)]
    rows = []
    for n, a in enumerate(anchors):
        top = mids[n - 1] if n else hy + 8
        bot = mids[n] if n < len(mids) else page.rect.height
        cell = {}
        for w in sorted(ws, key=lambda w: (round(w[5]), w[0])):
            if top <= w[5] < bot and w[5] > hy + 8:
                i = col_of((w[0] + w[2]) / 2)
                if i >= 0:
                    cell.setdefault(i, []).append(w[4])
        rec = {}
        ticks = []
        for i, toks in cell.items():
            h = heads.get(i, "")
            txt = " ".join(toks).strip()
            if not h:
                continue
            if SEASON_COL_RE.match(h):
                if txt.upper().startswith("V"):
                    ticks.append(h)
            else:
                rec[h] = txt
        mtl = a[4]
        out = {"mtl": mtl, "page": page.number + 1, "section": title, "fields": rec, "ticks": ticks}
        rows.append(out)
    return title, rows


def main():
    path = sys.argv[1]
    doc = pymupdf.open(path)
    rows = []
    title = ""
    for page in doc:
        title, r = parse_page(page, title)
        rows.extend(r)
    json.dump({"file": path, "rows": rows}, sys.stdout, ensure_ascii=False)


if __name__ == "__main__":
    main()
