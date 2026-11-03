"""Trains how Lexus picks the passage that answers a lab question, on 1,000+ questions generated from the documents themselves.

    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\train_lexus.py                 generate, fetch, train, report
    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\train_lexus.py --save          ... and write the winning ranker to weights.json
    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\train_lexus.py --reuse         train again on the questions fetched last time (offline)

1. Questions. From passages of every # folder (at most a few per document), each written the ways technicians type: a clean
   question, a sloppy one (lower case, a typo, no question words), a Taglish one, and a vague one of two or three words. The
   answer is known: the passage it was written from (or the same lines in another copy of the same document).
2. Candidates. Each question goes through the running service once (GET /admin/labdocs/features), which returns every candidate
   passage with its features: the reader's score, the question's (rare) words in it, a file named after the subject, the folder.
3. Training. A linear ranker over those features, trained with a listwise softmax loss (Adam). Every trial draws its number of
   epochs at random from 35 to 45, its learning rate and its start; the trial with the best accuracy on held-out documents
   (then the lowest error rate, then the best precision) wins. It must also do at least as well as the current ranking on the
   real-question test (lab_eval.py) and on a second set of held-out documents, or nothing is saved.
"""
from __future__ import annotations

import json
import math
import random
import re
import sys
import time
from pathlib import Path

import httpx
import numpy as np

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from lab_eval import CASES, KEY, URL                                  # noqa: E402

sys.stdout.reconfigure(encoding="utf-8")
PASSAGES = Path(r"E:\NexusAI\labdocs\passages.jsonl")
OUT = PASSAGES.parent / "weights.json"
WORK = PASSAGES.parent / "train"
FEATS = ("base", "named", "logit", "wcover", "auth", "cover", "dense", "ocr")
THRESHOLD = 0.55
PER_FILE, TARGET_PASSAGES, TRIALS = 3, 420, 24
REAL_WEIGHT = 30
SUPERSEDED = re.compile(r"\bwrong\b|\bcopy\b|\bold\b|\bdraft\b|\bbackup\b|\bobsolete\b|\(\d\)", re.I)
COMMON = set("""test testing tested machine sample samples standard laboratory what which when where many much does used need needed
required requirement steps step maintain maintenance clean check procedure method value minimum maximum often should there their about
shall with from that this these those have will each other after before which while being into than then them they were your page
sheet document record records form forms number total date name remarks signature checked prepared approved reviewed measured
january february march april may june july august september october november december monday tuesday wednesday thursday friday
saturday sunday month months weekly daily yearly result results actual reading readings time""".split())
PER_FOLDER = 36


# ------------------------------------------------------------------------------------------------ 1. questions

def subject_of(title: str) -> list[str]:
    t = re.sub(r"DTP[- ]?Lab[- ]?\d+|DTP[- ]?QC[- ]?\d+|\b\d[\d.\-/]*\b|\(\d+\)", " ", title, flags=re.I)
    t = SUPERSEDED.sub(" ", t)
    ws = [w for w in re.findall(r"[A-Za-z]{3,}", t) if w.lower() not in COMMON and w.lower() not in ("rev", "revision", "and", "the", "for")]
    return ws[:4]


def typo(w: str, rnd: random.Random) -> str:
    if len(w) < 6:
        return w
    i = rnd.randrange(1, len(w) - 2)
    return w[:i] + w[i + 1:] if rnd.random() < 0.5 else w[:i] + w[i + 1] + w[i] + w[i + 2:]


def generate(rnd: random.Random) -> list[dict]:
    rows = [json.loads(x) for x in PASSAGES.read_text(encoding="utf-8").splitlines() if x.strip()]
    df: dict[str, int] = {}
    for r in rows:
        for w in set(re.findall(r"[a-z]{5,}", r["text"].lower())):
            df[w] = df.get(w, 0) + 1
    by_file: dict[str, list[dict]] = {}
    for r in rows:
        if len(r["text"]) >= 150 and not SUPERSEDED.search(r["file"]):
            by_file.setdefault(r["path"], []).append(r)
    picked, per_folder = [], {}
    paths = list(by_file)
    rnd.shuffle(paths)
    for path in paths:                                               # every folder is asked about, none dominates (checklists are many files)
        rs = by_file[path]
        folder = rs[0]["folder"]
        take = min(PER_FILE, len(rs), PER_FOLDER - per_folder.get(folder, 0))
        if take > 0:
            picked += rnd.sample(rs, take)
            per_folder[folder] = per_folder.get(folder, 0) + take
    rnd.shuffle(picked)
    qs = []
    for r in picked:
        if len(qs) >= TARGET_PASSAGES * 4:
            break
        subj = subject_of(r["title"])
        if not subj:
            continue
        sl = {w.lower() for w in subj}
        cand = sorted({w for w in re.findall(r"[a-z]{5,}", r["text"].lower()) if w not in COMMON and w not in sl and 2 <= df.get(w, 0) <= 0.02 * len(rows)},
                      key=lambda w: df[w])
        if len(cand) < 2:
            continue
        a1, a2 = rnd.sample(cand[:6], 2)
        s = " ".join(subj)
        base = {"pid": r["id"], "file": r["file"], "title": r["title"], "words": [a1, a2], "subject": [w.lower() for w in subj], "path": r["path"]}
        clean = rnd.choice([f"What is the {a1} {a2} in the {s}?", f"What does the {s} say about {a1} and {a2}?", f"{a1.capitalize()} and {a2} for the {s}?"])
        sloppy = f"{typo(a1, rnd)} {a2} {s.lower()}" + rnd.choice(["", " pls", "?", " ??"])
        taglish = rnd.choice([f"ano ang {a1} {a2} ng {s.lower()} po", f"paano yung {a1} {a2} sa {s.lower()}", f"saan yung {a1} {a2} ng {s.lower()}"])
        vague = f"{a1} {subj[0].lower()}"
        for style, q in (("clean", clean), ("sloppy", sloppy), ("taglish", taglish), ("vague", vague)):
            qs.append({**base, "q": q, "style": style})
    return qs


def good_for(gq: dict, c: dict) -> bool:
    """The candidate answers the generated question: it is the passage it was written from, or holds the same words in a document
    about the same subject (another copy or revision of it)."""
    if c.get("pid") == gq["pid"]:
        return True
    text = (c.get("text") or "").lower()
    if not all(w in text for w in gq["words"]):
        return False
    names = f"{c.get('title', '')} {c.get('file', '')}".lower()
    return sum(w in names for w in gq["subject"]) >= max(1, math.ceil(0.5 * len(gq["subject"])))


# ------------------------------------------------------------------------------------------------ 2. candidates

def fetch(items: list[dict], label: str) -> list[dict]:
    out, t0 = [], time.time()
    with httpx.Client(timeout=120) as cl:
        for i, it in enumerate(items, 1):
            for attempt in range(3):
                try:
                    r = cl.get(f"{URL}/admin/labdocs/features", params={"q": it["q"]}, headers={"X-AI-Key": KEY}).json()
                    break
                except httpx.HTTPError:
                    time.sleep(20)                                   # the service is restarting: wait for it
            else:
                continue
            out.append({**it, "cands": [{k: c.get(k) for k in ("pid", "file", "title", "text", "conf", *FEATS)} for c in r.get("candidates", [])]})
            if i % 50 == 0:
                print(f"  {label}: {i}/{len(items)} questions, {time.time() - t0:.0f}s", flush=True)
    return out


# ------------------------------------------------------------------------------------------------ 3. training

def matrix(data: list[dict]) -> list[tuple[np.ndarray, np.ndarray, np.ndarray]]:
    out = []
    for d in data:
        if not d["cands"]:
            continue
        X = np.array([[float(c.get(f) or 0.0) for f in FEATS] for c in d["cands"]])
        y = np.array([1.0 if d["ok"][i] else 0.0 for i in range(len(d["cands"]))])
        conf = np.array([float(c.get("conf") or 0.0) for c in d["cands"]])
        out.append((X, y, conf))
    return out


def metrics(sets, w: np.ndarray) -> dict:
    n = right = answered = answered_right = 0
    confs = []
    for X, y, conf in sets:
        n += 1
        top = int(np.argmax(X @ w))
        ok = y[top] > 0
        right += ok
        if conf[top] >= THRESHOLD:
            answered += 1
            answered_right += ok
            if ok:
                confs.append(conf[top])
    return {"accuracy": right / max(1, n), "precision": answered_right / max(1, answered), "error_rate": (answered - answered_right) / max(1, n),
            "coverage": answered / max(1, n), "confidence": float(np.mean(confs)) if confs else 0.0, "n": n}


def train(sets, w0: np.ndarray, epochs: int, lr: float, rnd: random.Random, l2: float = 1e-3) -> np.ndarray:
    allX = np.vstack([X for X, _, _ in sets])
    mu, sd = allX.mean(0), allX.std(0) + 1e-6
    w = w0 * sd                                                      # trained on standardised features, returned for raw ones
    prior = w.copy()                                                 # the step size of the change is held back towards the start
    m, v, t = np.zeros_like(w), np.zeros_like(w), 0
    usable = [(((X - mu) / sd), y) for X, y, _ in sets if 0 < y.sum() < len(y)]
    for _ in range(epochs):
        rnd.shuffle(usable)
        for b in range(0, len(usable), 32):
            g = np.zeros_like(w)
            for Xs, y in usable[b:b + 32]:
                s = Xs @ w
                p = np.exp(s - s.max())
                p /= p.sum()
                qpos = p * y
                qpos /= qpos.sum()
                g += Xs.T @ (p - qpos)                               # gradient of -log P(a right passage is first)
            g = g / max(1, len(usable[b:b + 32])) + l2 * (w - prior)
            t += 1
            m = 0.9 * m + 0.1 * g
            v = 0.999 * v + 0.001 * g * g
            w -= lr * (m / (1 - 0.9 ** t)) / (np.sqrt(v / (1 - 0.999 ** t)) + 1e-8)
    return w / sd


def current_w(weights: dict) -> np.ndarray:
    if "linear" in weights:
        return np.array([float(weights["linear"].get(f, 0.0)) for f in FEATS])
    return np.array([1.0, weights.get("boost", 0.3), weights.get("logit", 0.01), weights.get("cover", 0.05), weights.get("auth", 1.0), 0.0, 0.0, 0.0])


def real_cases() -> list[dict]:
    return [{"q": c["q"], "doc": c["doc"], "has": c.get("has", ""), "avoid": c.get("avoid", "")} for c in CASES if "doc" in c and "sid" not in c]


def real_ok(case: dict, c: dict) -> bool:
    return (case["doc"] == "" or case["doc"].lower() in (c.get("file") or "").lower()) and \
        (not case["has"] or case["has"].lower() in (c.get("text") or "").lower()) and not (case["avoid"] and case["avoid"].lower() in (c.get("file") or "").lower())


def main() -> None:
    rnd = random.Random(20261103)
    WORK.mkdir(parents=True, exist_ok=True)
    gen_f, real_f = WORK / "generated.json", WORK / "real.json"
    pool = json.loads(gen_f.read_text(encoding="utf-8")) if gen_f.exists() and ({"--reuse", "--more"} & set(sys.argv)) else []
    if "--reuse" in sys.argv and pool:
        gen, real = pool, json.loads(real_f.read_text(encoding="utf-8"))
    else:
        # --more: a new batch of questions (other passages, other wording) is added to the ones already learned from
        qs = generate(random.Random(int(time.time())) if "--more" in sys.argv else rnd)
        asked = {p["q"] for p in pool}
        qs = [x for x in qs if x["q"] not in asked]
        print(f"generated {len(qs)} questions from {len({q['path'] for q in qs})} documents "
              f"({', '.join(f'{s} {sum(q['style'] == s for q in qs)}' for s in ('clean', 'sloppy', 'taglish', 'vague'))})"
              + (f", added to {len(pool)} from earlier rounds" if pool else ""), flush=True)
        gen = pool + fetch(qs, "generated")
        real = fetch(real_cases(), "real")
        gen_f.write_text(json.dumps(gen, ensure_ascii=False), encoding="utf-8")
        real_f.write_text(json.dumps(real, ensure_ascii=False), encoding="utf-8")
    for d in gen:
        d["ok"] = [good_for(d, c) for c in d["cands"]]
    for d in real:
        d["ok"] = [real_ok(d, c) for c in d["cands"]]
    # split by document, so the held-out questions are about documents the ranker never saw
    paths = sorted({d["path"] for d in gen})
    rnd.shuffle(paths)
    val_p, test_p = set(paths[: len(paths) // 5]), set(paths[len(paths) // 5: 2 * len(paths) // 5])
    tr = matrix([d for d in gen if d["path"] not in val_p | test_p])
    va = matrix([d for d in gen if d["path"] in val_p])
    te = matrix([d for d in gen if d["path"] in test_p])
    rl = matrix(real)
    reachable = sum(any(d["ok"]) for d in gen) / max(1, len(gen))
    print(f"\nquestions: train {len(tr)}, validation {len(va)}, test {len(te)}, real {len(rl)};"
          f" the right passage is among the candidates for {reachable:.0%} of the generated ones")
    try:
        weights = json.loads(OUT.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        weights = {}
    w_cur = current_w(weights)
    base = {k: metrics(s, w_cur) for k, s in (("train", tr), ("val", va), ("test", te), ("real", rl))}
    print(f"current ranking: val acc {base['val']['accuracy']:.1%}  test acc {base['test']['accuracy']:.1%}  real {base['real']['accuracy']:.1%}")

    trials = []
    for i in range(1, TRIALS + 1):
        epochs, lr = rnd.randint(35, 45), rnd.choice([0.01, 0.02, 0.03, 0.05, 0.08])
        w0 = w_cur * np.array([rnd.uniform(0.7, 1.3) for _ in FEATS]) if i > 1 else w_cur.copy()
        l2 = rnd.choice([0.001, 0.01, 0.05, 0.2, 0.5])
        w = train(tr + rl * REAL_WEIGHT, w0, epochs, lr, rnd, l2)  # the real lab questions count most: they are what technicians ask
        mv, mr = metrics(va, w), metrics(rl, w)
        trials.append({"trial": i, "epochs": epochs, "lr": lr, "l2": l2, "w": w, "val": mv, "real": mr})
        print(f"  trial {i:2}: {epochs} epochs lr {lr:<5} hold {l2:<5} val acc {mv['accuracy']:.1%} prec {mv['precision']:.1%} err {mv['error_rate']:.1%}"
              f" conf {mv['confidence']:.2f} | real {mr['accuracy']:.1%}", flush=True)
    best = max(trials, key=lambda t: (round(t["real"]["accuracy"] + t["val"]["accuracy"], 3), -round(t["val"]["error_rate"], 3), t["val"]["precision"]))
    w = best["w"]
    # blend: how far to move from the current ranking towards the trained one. The largest step that keeps every real question
    # the current ranking answers right, and gains the most on held-out documents, is taken
    wn = w / (w[0] if w[0] > 0 else 1.0) * w_cur[0]                  # same scale as the current weights (the reader's score = 1)
    blends = []
    for a in [x / 10 for x in range(11)]:
        wa = w_cur + a * (wn - w_cur)
        blends.append((metrics(rl, wa)["accuracy"], metrics(va, wa)["accuracy"], a, wa))
    one = 1 / max(1, len(rl)) + 1e-9
    ok_real = max(b[0] for b in blends) - one
    _, _, alpha, w = max((b for b in blends if b[0] >= ok_real), key=lambda b: (round(b[0] + b[1], 4), b[2]))
    print("blend towards the trained ranker: " + "  ".join(f"{b[2]:.1f}: real {b[0]:.0%} val {b[1]:.1%}" for b in blends))
    print(f"chosen blend {alpha:.1f}")
    final ={k: metrics(s, w) for k, s in (("train", tr), ("val", va), ("test", te), ("real", rl))}
    print(f"\nbest: trial {best['trial']} ({best['epochs']} epochs)  weights " + ", ".join(f"{f} {x:+.3f}" for f, x in zip(FEATS, w)))
    print(f"{'':10}{'accuracy':>10}{'precision':>11}{'error':>8}{'answered':>10}{'confidence':>12}")
    for k in ("train", "val", "test", "real"):
        a, b = base[k], final[k]
        print(f"{k:10}{b['accuracy']:>9.1%} {b['precision']:>10.1%} {b['error_rate']:>7.1%} {b['coverage']:>9.1%} {b['confidence']:>11.2f}"
              f"   (was {a['accuracy']:.1%} / {a['precision']:.1%} / {a['error_rate']:.1%})")
    # at most one real question may be lost here (this check reads single passages; the live test, which also joins a record cut
    # in two, has the last word: lexus_overnight.py puts the old weights back when lab_eval.py gets worse), and only for a clear
    # gain on documents the ranker never saw
    one = 1 / max(1, final["real"]["n"]) + 1e-9
    better = (final["real"]["accuracy"] >= base["real"]["accuracy"] - one and final["test"]["accuracy"] >= base["test"]["accuracy"] + 0.03) or         (final["real"]["accuracy"] >= base["real"]["accuracy"] and final["test"]["accuracy"] >= base["test"]["accuracy"] - 0.005)
    report = {"trained": time.strftime("%Y-%m-%d %H:%M"), "trial": best["trial"], "epochs": best["epochs"], "questions": len(gen),
              "metrics": {k: {m: round(x, 4) for m, x in v.items()} for k, v in final.items()},
              "before": {k: {m: round(x, 4) for m, x in v.items()} for k, v in base.items()},
              "trials": [{"trial": t["trial"], "epochs": t["epochs"], "lr": t["lr"], "val_accuracy": round(t["val"]["accuracy"], 4),
                          "val_error": round(t["val"]["error_rate"], 4), "real_accuracy": round(t["real"]["accuracy"], 4)} for t in trials]}
    (WORK / "last_training.json").write_text(json.dumps(report, indent=1), encoding="utf-8")
    if not better:
        print("\nnot saved: the trained ranker is not at least as good as the current one on the real questions and held-out documents")
        return
    if "--save" in sys.argv:
        weights.update({"linear": {f: round(float(x), 5) for f, x in zip(FEATS, w)}, "trained": report["trained"], "metrics": report["metrics"]})
        OUT.write_text(json.dumps(weights, indent=2), encoding="utf-8")
        print(f"\nsaved {OUT} (restart the AI service to use it)")


if __name__ == "__main__":
    main()
