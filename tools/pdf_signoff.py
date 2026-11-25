"""Finds the "Reviewed by" / "Approved by" lines on page 1 of a report PDF: the printed name and where it sits.
   python pdf_signoff.py <in.pdf>  ->  {"w":..,"h":..,"approved":{"name":..,"label":[x0,y0,x1,y1],"name_rect":[..]},"reviewed":{..}}
   Rects are PDF points with the origin at the top-left (PyMuPDF)."""
import json
import sys
import warnings

warnings.filterwarnings('ignore')
try:
    import fitz  # noqa: E402
except ImportError:   # Apache runs as LocalSystem: PyMuPDF installed for a user lives in that user's site-packages
    import glob
    for d in glob.glob(r'C:\Users\*\AppData\Roaming\Python\Python3*\site-packages'):
        sys.path.append(d)
    import fitz  # noqa: E402

out = {}
try:
    doc = fitz.open(sys.argv[1])
    # the sign-off lines are on the LAST page that has them (multi-page reports): look from the end
    page, pno = doc[0], 0
    for n in range(len(doc) - 1, -1, -1):
        ws = doc[n].get_text('words')
        if any(w[4].lower() in ('reviewed', 'approved', 'authorized', 'authorised') and i + 1 < len(ws) and ws[i + 1][4].lower().startswith('by') for i, w in enumerate(ws)):
            page, pno = doc[n], n
            break
    out['page'] = pno
    out['w'], out['h'] = page.rect.width, page.rect.height
    words = page.get_text('words')
    for i, w in enumerate(words):
        key = {'reviewed': 'reviewed', 'approved': 'approved', 'authorized': 'approved', 'authorised': 'approved'}.get(w[4].lower())
        if not key or key in out or i + 1 >= len(words) or not words[i + 1][4].lower().startswith('by'):
            continue
        y0, y1 = w[1], w[3]
        row = [x for x in words if abs((x[1] + x[3]) / 2 - (y0 + y1) / 2) < 4 and x[0] > words[i + 1][2] + 1]
        row.sort(key=lambda x: x[0])
        name = [x for x in row if x[4].isascii() and not x[4].endswith(':') and any(c.isalpha() for c in x[4])]
        out[key] = {
            'name': ' '.join(x[4] for x in name).strip(),
            'label': [w[0], y0, words[i + 1][2], y1],
            'name_rect': [name[0][0], min(x[1] for x in name), name[-1][2], max(x[3] for x in name)] if name else None,
            'row_end': max([x[2] for x in row] or [words[i + 1][2]]),
        }
except Exception as e:  # unreadable PDF: no names, the caller falls back
    out['error'] = str(e)
print(json.dumps(out))
