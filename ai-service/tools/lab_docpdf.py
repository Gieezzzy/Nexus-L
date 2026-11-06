"""
Office files of a Laboratory Documents folder -> something NEXUS-L can show, using Microsoft Office (its own hidden copy, never one that is already open):

    Word (.docx .doc)        -> PDF
    PowerPoint (.pptx .ppt)  -> PDF
    old Excel (.xls)         -> .xlsx (so it opens in the sheet view)

    python lab_docpdf.py <root folder> <out folder>

Writes <out>/<n>.pdf or <out>/<n>.xlsx and <out>/index.json = {"relative/path": "<n>.pdf"}.
Every document is opened read-only from a local copy and never saved back. A parent process watches a worker: when a document
makes Office stop answering for 120 s, that Office (only that one) is stopped, the document is skipped and the work goes on.
"""
import json
import os
import shutil
import subprocess
import sys
import tempfile
import time

WORD = (".docx", ".doc")
PPT = (".pptx", ".ppt")
XLS = (".xls",)


def pids(image):
    out = subprocess.run(["tasklist", "/fo", "csv", "/nh", "/fi", "imagename eq " + image], capture_output=True, text=True).stdout
    return {int(l.split(",")[1].strip('"')) for l in out.splitlines() if l.startswith('"' + image.split(".")[0].upper()) or l.upper().startswith('"' + image.upper())}


def collect(root):
    docs = []
    for dp, _d, files in os.walk(root):
        for f in sorted(files):
            if f.startswith("~$"):
                continue
            if f.lower().endswith(WORD + PPT + XLS):
                docs.append(os.path.join(dp, f))
    return docs


def worker(root, out, listfile, progress):
    """converts the documents named in listfile (one per line: n<TAB>path); appends 'n<TAB>ok|fail<TAB>name' to progress"""
    import pythoncom
    import win32com.client
    pythoncom.CoInitialize()
    apps = {}
    tmp = tempfile.mkdtemp(prefix="labdoc_")

    def app(kind):
        if kind not in apps:
            name = {"w": "Word.Application", "p": "PowerPoint.Application", "x": "Excel.Application"}[kind]
            a = win32com.client.DispatchEx(name)
            if kind != "p":
                a.Visible = False
                a.DisplayAlerts = 0 if kind == "w" else False
            apps[kind] = a
        return apps[kind]

    for line in open(listfile, encoding="utf-8").read().splitlines():
        n, path = line.split("\t", 1)
        ext = os.path.splitext(path)[1].lower()
        local = os.path.join(tmp, "d%s%s" % (n, ext))
        try:
            shutil.copy2(path, local)
            if ext in WORD:
                dst = os.path.abspath(os.path.join(out, n + ".pdf"))
                d = app("w").Documents.Open(local, False, True, False)
                d.SaveAs2(dst, 17)
                d.Close(False)
            elif ext in PPT:
                dst = os.path.abspath(os.path.join(out, n + ".pdf"))
                d = app("p").Presentations.Open(local, True, False, False)       # read only, untitled, no window
                d.SaveAs(dst, 32)
                d.Close()
            else:
                dst = os.path.abspath(os.path.join(out, n + ".xlsx"))
                d = app("x").Workbooks.Open(local, 0, True)
                d.SaveAs(dst, 51)
                d.Close(False)
            res = "ok" if os.path.isfile(dst) else "fail"
        except Exception as e:
            res = "fail"
            print("no output:", os.path.basename(path), str(e)[:70], flush=True)
        open(progress, "a", encoding="utf-8").write("%s\t%s\t%s\n" % (n, res, os.path.basename(path)))
    for kind, a in apps.items():
        try:
            a.Quit()
        except Exception:
            pass
    shutil.rmtree(tmp, ignore_errors=True)


def main():
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    if len(sys.argv) >= 6 and sys.argv[1] == "--worker":
        worker(sys.argv[2], sys.argv[3], sys.argv[4], sys.argv[5])
        return
    root, out = sys.argv[1], sys.argv[2]
    os.makedirs(out, exist_ok=True)
    docs = collect(root)
    idx_file = os.path.join(out, "index.json")
    idx = json.load(open(idx_file, encoding="utf-8")) if os.path.isfile(idx_file) else {}
    todo = []
    for n, path in enumerate(docs, 1):
        rel = os.path.relpath(path, root).replace("\\", "/")
        ext = ".xlsx" if path.lower().endswith(XLS) else ".pdf"
        if rel in idx and os.path.isfile(os.path.join(out, idx[rel])):
            continue                                          # converted by an earlier run
        todo.append((n, path, rel, ext))
    rels = {str(n): (rel, ext) for n, _p, rel, ext in todo}
    done_ok = 0
    progress = os.path.join(out, "_progress.txt")
    while todo:
        open(progress, "w").close()
        lst = os.path.join(out, "_list.txt")
        with open(lst, "w", encoding="utf-8") as fh:
            for n, path, _r, _e in todo:
                fh.write("%d\t%s\n" % (n, path))
        before = {i: pids(i) for i in ("WINWORD.EXE", "POWERPNT.EXE", "EXCEL.EXE")}
        p = subprocess.Popen([sys.executable, "-W", "ignore", os.path.abspath(__file__), "--worker", root, out, lst, progress], stdout=sys.stdout, stderr=sys.stderr)
        last, seen = time.time(), 0
        while p.poll() is None:
            time.sleep(2)
            n_lines = len(open(progress, encoding="utf-8").read().splitlines())
            if n_lines != seen:
                seen, last = n_lines, time.time()
            elif time.time() - last > 120:                    # Office stopped answering: stop only what this run started
                print("stalled, skipping one document", flush=True)
                p.kill()
                for img, b in before.items():
                    for pid in pids(img) - b:
                        subprocess.run(["taskkill", "/pid", str(pid), "/f"], capture_output=True)
                break
        for img, b in before.items():                         # leftovers of this run
            for pid in pids(img) - b:
                subprocess.run(["taskkill", "/pid", str(pid), "/f"], capture_output=True)
        lines = [l.split("\t") for l in open(progress, encoding="utf-8").read().splitlines() if l.strip()]
        handled = {l[0] for l in lines}
        for l in lines:
            if l[1] == "ok" and l[0] in rels:
                rel, ext = rels[l[0]]
                idx[rel] = l[0] + ext
                done_ok += 1
        json.dump(idx, open(idx_file, "w", encoding="utf-8"), ensure_ascii=False)
        remaining = [t for t in todo if str(t[0]) not in handled]
        if len(remaining) == len(todo):                       # nothing moved: the first document is the stuck one
            remaining = remaining[1:]
        todo = remaining
    json.dump(idx, open(idx_file, "w", encoding="utf-8"), ensure_ascii=False)
    for f in ("_list.txt", "_progress.txt"):
        try:
            os.remove(os.path.join(out, f))
        except OSError:
            pass
    print("%d converted (%d documents in the folder)" % (len(idx), len(docs)))


if __name__ == "__main__":
    main()
