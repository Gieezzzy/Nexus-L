"""Renders the pages of a PDF to PNG files:  python pdf_pages.py <in.pdf> <out_prefix> [zoom] [max_pages]
Prints the written file names, one per line. Used to show a report exactly as it is printed (no PDF viewer needed)."""
import os
import sys
import warnings

# The web server runs as another Windows user: PyMuPDF lives in the lab user's own site-packages, so add that folder to the search path.
for _p in (os.path.join(os.environ.get('APPDATA', ''), 'Python', 'Python314', 'site-packages'),
           'C:/Users/AUDIT STAFF/AppData/Roaming/Python/Python314/site-packages'):
    if os.path.isdir(_p) and _p not in sys.path:
        sys.path.append(_p)

warnings.filterwarnings('ignore')
import fitz  # noqa: E402

src, prefix = sys.argv[1], sys.argv[2]
zoom = float(sys.argv[3]) if len(sys.argv) > 3 else 1.6
limit = int(sys.argv[4]) if len(sys.argv) > 4 else 3
doc = fitz.open(src)
for i in range(min(len(doc), limit)):
    out = f'{prefix}_{i + 1}.png'
    doc[i].get_pixmap(matrix=fitz.Matrix(zoom, zoom), alpha=False).save(out)
    print(out)
