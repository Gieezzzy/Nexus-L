"""The daily refinement pipeline (also runnable on demand: POST /admin/pipeline/run).

 1  index     reload the approved NEXUS-L answers; put newly approved administrator corrections into Qdrant and Mem0
 2  dataset   build / extend eval_cases (answers' own titles and keywords, typos, dropped words, off-topic questions that must be
           refused, approved corrections, thumbs-down cases) with a split fixed by a hash of the question
 3  boosts    bounded per-answer ranking boosts from thumbs-up / thumbs-down
 4  gate      replay the HOLDOUT cases, measure precision of shown answers, abstain rate and recall, pick the lowest threshold that
           keeps precision >= 0.98, and activate it only if it is not worse than the active one
 5  clean     drop telemetry older than 90 days
"""
from __future__ import annotations

import hashlib
import random
import re
from typing import Any

from app import db, indexer, store
from app.agent import build_graph
from app.config import settings
from app.state import UiState
from app.textutil import words

PRECISION_TARGET = 0.98
OFF_TOPIC = [
    "what is the weather today", "who won the basketball game", "give me a recipe for adobo", "what is the capital of France", "tell me a joke",
    "how do I reset my facebook password", "what is the price of bitcoin", "translate good morning to japanese", "write me a poem",
    "how tall is mount everest", "what movies are showing tonight", "who is the president of the united states", "how do I lose weight fast",
    "what is the best phone to buy", "how do I install windows 11", "explain quantum physics", "play some music", "what time is it in tokyo",
    "how do I bake a chocolate cake", "recommend a good restaurant", "what is the meaning of life", "how many calories are in rice",
    "book me a flight to cebu", "who is the richest man in the world", "how do I fix my car engine", "what is the score of the match",
    "can you search the web for me", "open youtube", "how do I hack a wifi password", "what is the stock price of apple",
]
_TYPO_KEYS = "qwertyuiopasdfghjklzxcvbnm"


def _split(question: str) -> str:
    return "holdout" if int(hashlib.sha1(question.encode()).hexdigest(), 16) % 5 == 0 else "train"


def _typo(w: str, rnd: random.Random) -> str:
    if len(w) < 5:
        return w
    i = rnd.randrange(1, len(w) - 1)
    return w[:i] + w[i + 1] + w[i] + w[i + 2:] if rnd.random() < 0.5 else w[:i] + rnd.choice(_TYPO_KEYS) + w[i + 1:]


def build_eval_set() -> int:
    """Questions are derived from the answers themselves, so this is a regression and calibration set, not a measure of real users."""
    rnd = random.Random(7)
    added = 0
    rows: list[tuple[str, int | None, bool, str]] = []
    for k in indexer.load_knowledge():
        if k.get("role_min") == "admin":
            continue
        rows.append((k["title"], k["id"], False, "title"))
        kws = [x.strip() for x in (k.get("keywords") or "").split(",") if x.strip()]
        if len(kws) >= 2:
            rows.append((kws[1], k["id"], False, "keywords"))
        tw = k["title"].split()
        if len(tw) >= 5:
            rows.append((" ".join(w for i, w in enumerate(tw) if i != rnd.randrange(len(tw))), k["id"], False, "dropped"))
            rows.append((" ".join(_typo(w, rnd) if i % 3 == 1 else w for i, w in enumerate(tw)), k["id"], False, "typo"))
    rows += [(q, None, True, "negative") for q in OFF_TOPIC]
    for c in db.query("SELECT question FROM admin_corrections WHERE status = 'approved'"):
        rows.append((c["question"], None, False, "correction"))
    for r in db.query("SELECT m.body q, p.kb_id FROM messages p JOIN LATERAL (SELECT body FROM messages u WHERE u.session_id = p.session_id AND u.sender = 'user' AND u.message_id < p.message_id ORDER BY u.message_id DESC LIMIT 1) m ON true WHERE p.feedback = -1"):
        rows.append((r["q"], None, True, "thumbs_down"))
    for q, kb, ab, origin in rows:
        q = q.strip()
        if not q:
            continue
        cur = db.one("INSERT INTO eval_cases (question, expected_kb, expect_abstain, origin, split) VALUES (%s,%s,%s,%s,%s) ON CONFLICT (question) DO NOTHING RETURNING case_id", (q, kb, ab, origin, _split(q)))
        added += 1 if cur else 0
    return added


def update_boosts() -> int:
    """thumbs-up +0.01, thumbs-down -0.01 per vote on an answer, bounded to +-0.1 (the table enforces it)."""
    rows = db.query("SELECT kb_id, SUM(feedback) s, COUNT(*) n FROM messages WHERE sender = 'bot' AND kb_id IS NOT NULL AND feedback <> 0 GROUP BY kb_id")
    for r in rows:
        b = max(-0.1, min(0.1, 0.01 * int(r["s"])))
        db.run("INSERT INTO doc_boosts (kb_id, boost, votes) VALUES (%s,%s,%s) ON CONFLICT (kb_id) DO UPDATE SET boost = EXCLUDED.boost, votes = EXCLUDED.votes, updated_at = now()", (r["kb_id"], b, r["n"]))
    return len(rows)


def replay(cases: list[dict[str, Any]], graph: Any) -> list[dict[str, Any]]:
    """Runs each case through the real graph with a threshold of 0, so the raw confidence of the best answer is recorded."""
    out = []
    for c in cases:
        s = graph.invoke({"session_id": "eval", "user_id": "eval", "question": c["question"], "ui": UiState(), "threshold": 0.0, "prev_question": ""})
        p = s.get("payload", {})
        out.append({**c, "got_kb": p.get("kb_id"), "conf": float(s.get("confidence", 0.0)), "route": s.get("route")})
    return out


def evaluate(results: list[dict[str, Any]], tau: float) -> dict[str, float]:
    shown = [r for r in results if r["conf"] >= tau and r["got_kb"] is not None]
    correct = [r for r in shown if not r["expect_abstain"] and r["expected_kb"] is not None and r["got_kb"] == r["expected_kb"]]
    must_answer = [r for r in results if not r["expect_abstain"] and r["expected_kb"] is not None]
    must_refuse = [r for r in results if r["expect_abstain"]]
    return {
        "cases": len(results), "shown": len(shown),
        "precision": len(correct) / len(shown) if shown else 1.0,
        "recall": len(correct) / len(must_answer) if must_answer else 1.0,
        "abstain": 1 - len(shown) / len(results) if results else 0.0,
        "refusal_ok": (sum(1 for r in must_refuse if r not in shown) / len(must_refuse)) if must_refuse else 1.0,
    }


def gate(graph: Any) -> dict[str, Any]:
    cases = db.query("SELECT question, expected_kb, expect_abstain FROM eval_cases WHERE split = 'holdout'")
    if len(cases) < 30:
        return {"skipped": f"only {len(cases)} holdout cases"}
    res = replay(cases, graph)
    best_tau, best = None, None
    for tau in [x / 100 for x in range(40, 96, 1)]:
        m = evaluate(res, tau)
        if m["precision"] >= PRECISION_TARGET and m["refusal_ok"] >= 0.95:
            best_tau, best = tau, m                    # the LOWEST such tau keeps the most answers
            break
    if best_tau is None:
        return {"cases": len(cases), "note": "no threshold reaches the precision target; keeping the active one", "at_default": evaluate(res, db.active_threshold())}
    active = db.one("SELECT * FROM settings_versions WHERE active")
    worse = bool(active and active["holdout_precision"] is not None and best["precision"] < float(active["holdout_precision"]) - 0.005)
    note = "kept the previous version: precision fell" if worse else "activated"
    if not worse:
        db.run("UPDATE settings_versions SET active = false WHERE active")
    db.run("INSERT INTO settings_versions (threshold, holdout_cases, holdout_precision, holdout_abstain, holdout_recall, note, active) VALUES (%s,%s,%s,%s,%s,%s,%s)",
           (best_tau, best["cases"], best["precision"], best["abstain"], best["recall"], note, not worse))
    return {"threshold": best_tau, "metrics": best, "note": note}


def breakdown(graph: Any, tau: float | None = None, split: str = "holdout") -> dict[str, Any]:
    """Per-origin precision / recall at the active threshold, plus examples of what was wrong or missed."""
    tau = db.active_threshold() if tau is None else tau
    cases = db.query("SELECT question, expected_kb, expect_abstain, origin FROM eval_cases WHERE split = %s", (split,))
    res = replay(cases, graph)
    out: dict[str, Any] = {"tau": tau, "overall": evaluate(res, tau), "by_origin": {}, "wrong": [], "missed": []}
    for origin in sorted({r["origin"] for r in res}):
        sub = [r for r in res if r["origin"] == origin]
        out["by_origin"][origin] = {k: round(v, 3) for k, v in evaluate(sub, tau).items()}
    for r in res:
        shown = r["conf"] >= tau and r["got_kb"] is not None
        if shown and (r["expect_abstain"] or (r["expected_kb"] is not None and r["got_kb"] != r["expected_kb"])) and len(out["wrong"]) < 12:
            out["wrong"].append({"q": r["question"][:80], "origin": r["origin"], "got": r["got_kb"], "want": r["expected_kb"], "conf": round(r["conf"], 2)})
        if not shown and not r["expect_abstain"] and r["expected_kb"] is not None and len(out["missed"]) < 12:
            out["missed"].append({"q": r["question"][:80], "origin": r["origin"], "got": r["got_kb"], "want": r["expected_kb"], "conf": round(r["conf"], 2)})
    return out


def run(graph: Any) -> dict[str, Any]:
    summary: dict[str, Any] = {}
    run_id = db.one("INSERT INTO pipeline_runs DEFAULT VALUES RETURNING run_id")["run_id"]
    summary["docs"] = indexer.index_docs()
    summary["corrections_indexed"] = indexer.index_corrections()
    summary["eval_cases_added"] = build_eval_set()
    summary["boosts"] = update_boosts()
    summary["gate"] = gate(build_graph(None))              # replays run without the conversation checkpointer
    db.run("DELETE FROM telemetry_events WHERE occurred_at < now() - interval '90 days'")
    db.run("UPDATE pipeline_runs SET finished_at = now(), summary = %s::jsonb WHERE run_id = %s", (db.j(summary), run_id))
    return summary
