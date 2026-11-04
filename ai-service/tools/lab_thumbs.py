"""
Preview pictures for the Laboratory Documents: the first page of every PDF and a small copy of every picture under a folder.

    python lab_thumbs.py <root folder> <out folder>

Writes <out>/<n>.jpg (about 360 px wide) and <out>/index.json = {relative/path: "<n>.jpg"}; the PHP importer (labdocs:import-files,
labdocs:import-pdfs) puts them in the database next to the files. Nothing is changed in the root folder.
"""
import json
import os
import sys

import fitz  # PyMuPDF
from PIL import Image

W = 360


def thumb_pdf(path, out):
    d = fitz.open(path)
    if d.page_count < 1:
        return False
    p = d[0]
    z = W / max(p.rect.width, 1)
    pix = p.get_pixmap(matrix=fitz.Matrix(z, z), alpha=False)
    open(out, "wb").write(pix.tobytes("jpeg", jpg_quality=72))
    d.close()
    return True


def thumb_img(path, out):
    im = Image.open(path)
    im = im.convert("RGB")
    im.thumbnail((W, W * 2))
    im.save(out, "JPEG", quality=72)
    return True


def main():
    root, out = sys.argv[1], sys.argv[2]
    os.makedirs(out, exist_ok=True)
    idx, n, bad = {}, 0, 0
    for dp, _dn, files in os.walk(root):
        for f in sorted(files):
            ext = f.lower().rsplit(".", 1)[-1] if "." in f else ""
            if ext not in ("pdf", "jpg", "jpeg", "png") or f.startswith("~$"):
                continue
            full = os.path.join(dp, f)
            rel = os.path.relpath(full, root).replace("\\", "/")
            n += 1
            name = "%d.jpg" % n
            try:
                ok = thumb_pdf(full, os.path.join(out, name)) if ext == "pdf" else thumb_img(full, os.path.join(out, name))
            except Exception as e:  # a damaged file simply has no preview
                ok = False
                bad += 1
                print("no preview:", rel, str(e)[:60])
            if ok:
                idx[rel] = name
    json.dump(idx, open(os.path.join(out, "index.json"), "w", encoding="utf-8"), ensure_ascii=False)
    print("%d previews, %d without" % (len(idx), bad))


if __name__ == "__main__":
    main()
