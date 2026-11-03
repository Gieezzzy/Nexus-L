"""Reads the laboratory documents Lexus is allowed to learn from and writes them as searchable passages.

Only the top-level folders of the Lab Documents share whose name starts with "#" are read (the controlled documents); nothing
is written to the share. Every passage keeps where it came from (folder, file, page or sheet), so an answer can cite it.

    C:\\NexusAI\\venv\\Scripts\\python.exe labdocs_extract.py             changed and new files only
    C:\\NexusAI\\venv\\Scripts\\python.exe labdocs_extract.py --full      read everything again

Output (E:, away from the nearly full C: drive):
    E:\\NexusAI\\labdocs\\passages.jsonl     one passage per line: {id, folder, file, path, loc, title, text, mtime}
    E:\\NexusAI\\labdocs\\manifest.json      per file: size, mtime, passages, status (ok / no-text / unsupported / error)
The AI service indexes passages.jsonl with POST /admin/labdocs/index.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import sys
import time
import zipfile
from pathlib import Path
from xml.etree import ElementTree as ET

ROOT = Path(os.environ.get("LAB_DOCS_ROOT", r"\\192.168.32.130\qc lab files\#Lab Documents"))
OUT = Path(os.environ.get("LAB_DOCS_OUT", r"E:\NexusAI\labdocs"))
CHUNK, OVERLAP = 900, 150            # characters per passage and the overlap that keeps a sentence split across two passages findable
MAX_ROWS = 600                       # rows read per worksheet (registers and records can be long)
IMAGE_EXT = {".jpg", ".jpeg", ".png", ".tif", ".tiff", ".bmp"}
TEXT_EXT = {".pdf", ".docx", ".doc", ".xlsx", ".xlsm", ".xls", ".pptx", ".txt", ".csv"} | IMAGE_EXT
# scanned pages, photographed records and certificates are read with the Tesseract OCR installed on this PC (nothing is sent anywhere)
TESSERACT = Path(os.environ.get("TESSERACT", r"C:\Users\AUDIT STAFF\AppData\Local\Programs\Tesseract-OCR\tesseract.exe"))
OCR_VERSION = 1                      # files that gave no text with this OCR are not read again until they change
TMP = OUT / "ocr-tmp"
W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
A = "{http://schemas.openxmlformats.org/drawingml/2006/main}"


def clean(s: str) -> str:
    s = s.replace("\u00a0", " ").replace("\r", "\n")
    s = re.sub(r"[ \t]+", " ", s)
    s = re.sub(r"\n\s*\n+", "\n", s)
    return s.strip()


def chunks(text: str) -> list[str]:
    text = clean(text)
    if len(text) <= CHUNK:
        return [text] if len(text) >= 40 else []
    out, i = [], 0
    while i < len(text):
        end = min(len(text), i + CHUNK)
        if end < len(text):                                   # end on a sentence or line break when one is close
            cut = max(text.rfind(". ", i + CHUNK // 2, end), text.rfind("\n", i + CHUNK // 2, end))
            if cut > 0:
                end = cut + 1
        part = text[i:end].strip()
        if len(part) >= 40:
            out.append(part)
        if end >= len(text):
            break
        i = max(end - OVERLAP, i + 1)
    return out


# ---- OCR ------------------------------------------------------------------------------------------------------------

_vocab: set[str] = set()


def _real_words(text: str) -> int:
    """How many of the OCR words are words the typed documents use (OCR noise like "ajqeyins" is not)."""
    return sum(t in _vocab for t in re.findall(r"[a-z]{3,}", text.lower()))


def _readable(text: str) -> bool:
    toks = re.findall(r"[A-Za-z]{3,}", text)
    return len(toks) >= 8 and _real_words(text) >= max(8, 0.5 * len(toks))


def ocr_png(png: Path) -> str:
    import subprocess
    if not TESSERACT.exists():
        return ""
    r = subprocess.run([str(TESSERACT), str(png), "stdout", "-l", "eng", "--psm", "3"], capture_output=True, timeout=120,
                       creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0))
    return r.stdout.decode("utf-8", errors="ignore")


def ocr_image_bytes(data: bytes, name: str) -> str:
    """Any image: made grey, scaled to at most 2600 px and read; a page scanned sideways or upside down is turned until it reads.
    A photo of a machine or of people gives letter noise, and nothing is kept."""
    import io
    from PIL import Image, ImageOps
    TMP.mkdir(parents=True, exist_ok=True)
    out = TMP / (re.sub(r"[^A-Za-z0-9]+", "_", name)[-60:] + ".png")
    with Image.open(io.BytesIO(data)) as im:
        im = ImageOps.exif_transpose(im).convert("L")
        if max(im.size) < 300:                                   # icons and logos
            return ""
        if max(im.size) > 2600:
            im.thumbnail((2600, 2600))
        try:
            for angle in (0, 180, 90, 270):
                (im if angle == 0 else im.rotate(angle, expand=True)).save(out)
                text = ocr_png(out)
                if _readable(text):
                    return text
                if angle == 0 and len(re.findall(r"[A-Za-z]{3,}", text)) < 4:
                    break                                         # hardly any letters at all: a photo, not a turned page
            return ""
        finally:
            out.unlink(missing_ok=True)


def read_image(p: Path):
    yield "image (OCR)", ocr_image_bytes(p.read_bytes(), p.name)


def _ocr_media(p: Path, prefix: str):
    """The pictures inside a Word or PowerPoint file that has no typed text (a scanned form pasted into a document)."""
    with zipfile.ZipFile(p) as z:
        media = [n for n in z.namelist() if re.match(prefix + r"media/.+\.(png|jpe?g|tiff?|bmp)$", n, re.I)]
        for n in media[:40]:
            yield f"picture {n.rsplit('/', 1)[-1]} (OCR)", ocr_image_bytes(z.read(n), p.stem + n)


# ---- readers: each yields (location, text)------------------------------------------------------------------------

def read_pdf(p: Path):
    import pymupdf
    with pymupdf.open(p) as doc:
        for n, page in enumerate(doc, 1):
            text = page.get_text("text")
            if len(text.strip()) >= 40:
                yield f"page {n}", text
            elif n <= 30:                                        # a scanned page has no text layer: read the picture of it
                yield f"page {n} (OCR)", ocr_image_bytes(page.get_pixmap(dpi=220).tobytes("png"), f"{p.stem}_{n}")


def read_docx(p: Path):
    with zipfile.ZipFile(p) as z:
        root = ET.fromstring(z.read("word/document.xml"))
    paras = []
    for para in root.iter(W + "p"):
        t = "".join(x.text or "" for x in para.iter(W + "t"))
        if t.strip():
            paras.append(t)
    yield "document", "\n".join(paras)


def read_pptx(p: Path):
    with zipfile.ZipFile(p) as z:
        slides = sorted((n for n in z.namelist() if re.match(r"ppt/slides/slide\d+\.xml$", n)), key=lambda n: int(re.findall(r"\d+", n)[-1]))
        for n in slides:
            root = ET.fromstring(z.read(n))
            yield f"slide {re.findall(r'\d+', n)[-1]}", "\n".join(t.text or "" for t in root.iter(A + "t"))


def _rows_to_text(rows) -> str:
    lines = []
    for r in rows:
        cells = [str(c).strip() for c in r if c is not None and str(c).strip() not in ("", "None")]
        if cells:
            lines.append(" | ".join(cells))
    return "\n".join(lines)


def read_xlsx(p: Path):
    import openpyxl
    wb = openpyxl.load_workbook(p, read_only=True, data_only=True)
    try:
        for ws in wb.worksheets:
            rows = []
            for i, r in enumerate(ws.iter_rows(values_only=True)):
                if i >= MAX_ROWS:
                    break
                rows.append(r)
            yield f"sheet {ws.title}", _rows_to_text(rows)
    finally:
        wb.close()


def read_xls(p: Path):
    import xlrd
    wb = xlrd.open_workbook(p, on_demand=True)
    for ws in wb.sheets():
        yield f"sheet {ws.name}", _rows_to_text(ws.row_values(i) for i in range(min(ws.nrows, MAX_ROWS)))


_word = None


def read_doc(p: Path):
    """Old binary Word files: read through the installed Microsoft Word (read-only, no window)."""
    global _word
    import win32com.client
    if _word is None:
        _word = win32com.client.DispatchEx("Word.Application")
        _word.Visible = False
        _word.DisplayAlerts = 0
    d = _word.Documents.Open(str(p), ReadOnly=True, AddToRecentFiles=False, ConfirmConversions=False)
    try:
        yield "document", d.Content.Text
    finally:
        d.Close(False)


def read_txt(p: Path):
    yield "text", p.read_text(encoding="utf-8", errors="ignore")


READERS = {".pdf": read_pdf, ".docx": read_docx, ".pptx": read_pptx, ".xlsx": read_xlsx, ".xlsm": read_xlsx, ".xls": read_xls,
           ".doc": read_doc, ".txt": read_txt, ".csv": read_txt, **{e: read_image for e in IMAGE_EXT}}


def title_of(p: Path) -> str:
    return re.sub(r"[_]+", " ", p.stem).strip()


def main() -> None:
    full = "--full" in sys.argv
    OUT.mkdir(parents=True, exist_ok=True)
    man_f, pas_f = OUT / "manifest.json", OUT / "passages.jsonl"
    manifest = {} if full or not man_f.exists() else json.loads(man_f.read_text(encoding="utf-8"))
    old: dict[str, list[dict]] = {}
    if pas_f.exists():
        for line in pas_f.read_text(encoding="utf-8").splitlines():
            if line.strip():
                r = json.loads(line)
                if "(OCR)" not in r["loc"]:
                    _vocab.update(re.findall(r"[a-z]{3,}", r["text"].lower()))
                if not full:
                    old.setdefault(r["path"], []).append(r)

    folders = sorted(d for d in ROOT.iterdir() if d.is_dir() and d.name.startswith("#"))
    seen, passages, stats = set(), [], {"read": 0, "kept": 0, "no_text": 0, "unsupported": 0, "error": 0}
    t0 = time.time()
    for folder in folders:
        for dirpath, _dirs, files in os.walk(folder):
            for name in files:
                p = Path(dirpath) / name
                ext = p.suffix.lower()
                rel = str(p.relative_to(ROOT))
                seen.add(rel)
                if name.startswith("~$") or ext not in TEXT_EXT:
                    stats["unsupported"] += ext not in TEXT_EXT
                    if ext not in TEXT_EXT:
                        manifest[rel] = {"status": "unsupported"}
                    continue
                try:
                    st = p.stat()
                except OSError:
                    continue
                prev = manifest.get(rel)
                if prev and prev.get("size") == st.st_size and prev.get("mtime") == int(st.st_mtime) and \
                        (rel in old or (prev.get("status") == "no-text" and prev.get("ocr") == OCR_VERSION)):
                    passages += old.get(rel, [])               # unchanged: keep what was read last time
                    stats["kept"] += 1
                    continue
                items, status = [], "ok"
                try:
                    for loc, text in READERS[ext](p):
                        for k, part in enumerate(chunks(text)):
                            pid = hashlib.sha1(f"{rel}|{loc}|{k}".encode()).hexdigest()[:16]
                            items.append({"id": pid, "folder": folder.name, "file": p.name, "path": rel, "loc": loc, "title": title_of(p),
                                          "text": part, "mtime": int(st.st_mtime)})
                    if not items and ext in (".docx", ".pptx"):
                        for loc, text in _ocr_media(p, "word/" if ext == ".docx" else "ppt/"):
                            for k, part in enumerate(chunks(text)):
                                pid = hashlib.sha1(f"{rel}|{loc}|{k}".encode()).hexdigest()[:16]
                                items.append({"id": pid, "folder": folder.name, "file": p.name, "path": rel, "loc": loc, "title": title_of(p),
                                              "text": part, "mtime": int(st.st_mtime)})
                    if not items:
                        status = "no-text"                     # no typed text, and nothing readable in its pictures (photos)
                        stats["no_text"] += 1
                except Exception as e:                         # a damaged or locked file must not stop the others
                    status = f"error: {type(e).__name__}: {str(e)[:120]}"
                    stats["error"] += 1
                passages += items
                manifest[rel] = {"size": st.st_size, "mtime": int(st.st_mtime), "passages": len(items), "status": status, "ocr": OCR_VERSION}
                stats["read"] += 1
                if stats["read"] % 25 == 0:
                    print(f"  {stats['read']} files read, {len(passages)} passages, {time.time() - t0:.0f}s", flush=True)
    for gone in set(manifest) - seen:
        manifest.pop(gone, None)
    tmp = pas_f.with_suffix(".tmp")
    with tmp.open("w", encoding="utf-8") as f:
        for r in passages:
            f.write(json.dumps(r, ensure_ascii=False) + "\n")
    tmp.replace(pas_f)
    man_f.write_text(json.dumps(manifest, ensure_ascii=False, indent=1), encoding="utf-8")
    if _word is not None:
        _word.Quit()
    stats.update(folders=len(folders), passages=len(passages), seconds=round(time.time() - t0))
    print(json.dumps(stats))


if __name__ == "__main__":
    main()
