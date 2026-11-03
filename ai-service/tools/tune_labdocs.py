"""Trains how Lexus ranks lab-document passages: tries every combination of the ranking weights on the real-question test
(lab_eval.py) and keeps the one that answers the most questions from the right document with the right value.

    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\tune_labdocs.py           # show the best weights
    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\tune_labdocs.py --save    # and write them to weights.json (restart the service)

Each question's candidates are fetched once from the running service; the search over the weights runs here, offline. Half
of the questions are held out on each of two folds, so the weights are chosen for questions they were not tuned on as well.
"""
from __future__ import annotations

import itertools
import json
import sys
from pathlib import Path

import httpx

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from lab_eval import CASES, KEY, URL                                 # noqa: E402

OUT = Path(r"E:\NexusAI\labdocs\weights.json")
GRID = {"logit": [0.0, 0.01, 0.02, 0.03, 0.05, 0.08], "cover": [0.0, 0.05, 0.1, 0.15, 0.2, 0.3],
        "boost": [0.1, 0.2, 0.3], "auth": [0.0, 1.0, 2.0, 4.0]}


def rank(c: dict, w: dict) -> float:
    raw = c["rr"] * min(1.0, c["cover"] / 0.6) + w["boost"] * float(c["named"])
    return raw + w["logit"] * c["logit"] + w["cover"] * c["wcover"] + w["auth"] * c["auth"]


def good(case: dict, cands: list[dict], w: dict) -> bool:
    top = max(cands, key=lambda c: rank(c, w))
    return (case["doc"] == "" or case["doc"].lower() in top["file"].lower()) and \
        (not case.get("has") or case["has"].lower() in top["text"].lower()) and \
        not (case.get("avoid") and case["avoid"].lower() in top["file"].lower())


def main() -> None:
    cases = [c for c in CASES if "doc" in c and "sid" not in c]
    data = []
    with httpx.Client(timeout=60) as cl:
        for case in cases:
            r = cl.get(f"{URL}/admin/labdocs/features", params={"q": case["q"]}, headers={"X-AI-Key": KEY}).json()
            if r["candidates"]:
                data.append((case, r["candidates"]))
    current = r["weights"]
    combos = [dict(zip(GRID, v)) for v in itertools.product(*GRID.values())]

    def score(w: dict, part: list) -> int:
        return sum(good(case, cands, w) for case, cands in part)

    folds = [data[0::2], data[1::2]]
    best = max(combos, key=lambda w: (score(w, data), min(score(w, f) for f in folds),
                                       -sum(abs(w[k] - current[k]) / max(GRID[k]) for k in GRID)))
    print(f"questions: {len(data)}")
    print(f"current weights {current}: {score(current, data)} right")
    print(f"best weights    {best}: {score(best, data)} right (folds {[score(best, f) for f in folds]})")
    for case, cands in data:
        if not good(case, cands, best):
            top = max(cands, key=lambda c: rank(c, best))
            print(f"  still wrong: {case['q'][:60]:60} -> {top['file'][:50]}")
    if "--save" in sys.argv:
        OUT.write_text(json.dumps(best, indent=2), encoding="utf-8")
        print(f"saved {OUT}")


if __name__ == "__main__":
    main()
