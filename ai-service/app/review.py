"""The review task: an administrator looks at a REAL question, sees the three best candidate answers, and says which one is right, that none
is, or that the question is not about NEXUS-L. Each decision becomes a labelled case (eval_cases) that the next training run learns from
(calibration) and is measured against (holdout). Typing the right answer when none fits creates an approved administrator correction at once.
"""
from __future__ import annotations

import hashlib
import json
from typing import Any

from app import db, indexer
from app.config import settings
from app.pipeline import _split
from app.state import UiState
from app.textutil import redact


def seed(extra: list[str] | None = None) -> dict[str, int]:
    """Real questions to review: user messages exported by NEXUS-L (questions.jsonl), questions the bot logged, and any passed in."""
    f = settings.home / "questions.jsonl"
    qs: list[tuple[str, str]] = []
    if f.exists():
        for line in f.read_text(encoding="utf-8").splitlines():
            try:
                j = json.loads(line)
            except ValueError:
                continue
            if j.get("q"):
                qs.append((str(j["q"]), str(j.get("source", "chat"))))
    qs += [(q, "shadow") for q in (extra or [])]
    for r in db.query("SELECT question FROM answer_log WHERE route IN ('abstain','human') OR confidence < threshold ORDER BY answer_id DESC LIMIT 500"):
        qs.append((r["question"], "shadow"))
    added = 0
    seen = {r["question"] for r in db.query("SELECT question FROM eval_cases")}
    for q, src in qs:
        q = redact(q).strip()
        if not (6 <= len(q) <= 300) or q in seen:
            continue
        row = db.one("INSERT INTO review_items (question, source) VALUES (%s,%s) ON CONFLICT (question) DO NOTHING RETURNING item_id", (q, src))
        added += 1 if row else 0
    return {"added": added, "pending": counts()["pending"]}


def counts() -> dict[str, int]:
    r = db.one("SELECT count(*) FILTER (WHERE status = 'pending') pending, count(*) FILTER (WHERE status = 'done') done, count(*) FILTER (WHERE status = 'skipped') skipped FROM review_items") or {}
    lab = db.one("SELECT count(*) n FROM eval_cases WHERE origin = 'review'") or {}
    return {"pending": int(r.get("pending") or 0), "done": int(r.get("done") or 0), "skipped": int(r.get("skipped") or 0), "labelled_cases": int(lab.get("n") or 0)}


def next_item(graph: Any) -> dict[str, Any] | None:
    """The next question with the three best candidates (found by the same retrieval the assistant uses)."""
    item = db.one("SELECT item_id, question, source FROM review_items WHERE status = 'pending' ORDER BY item_id LIMIT 1")
    if not item:
        return None
    act = db.active_settings()
    from app import agent

    st: dict[str, Any] = {"question": item["question"], "search_text": item["question"], "ui": UiState(role="admin"), "use_rerank": act["rerank"], "calib": act["calib"], "user_id": "eval", "session_id": "review"}
    docs = agent.retrieve_node(st)["docs"][:3]  # type: ignore[arg-type]
    return {"item_id": item["item_id"], "question": item["question"], "source": item["source"],
            "candidates": [{"kb_id": d.get("kb_id"), "title": d["title"], "body": d["text"][:420], "score": d["score"], "kind": d["kind"]} for d in docs], **counts()}


def label(item_id: int, choice: str, kb_id: int | None, admin: str, answer: str = "") -> dict[str, Any]:
    it = db.one("SELECT question FROM review_items WHERE item_id = %s AND status = 'pending'", (item_id,))
    if not it:
        return {"ok": False, "error": "That item was already reviewed."}
    q = it["question"]
    if choice == "skip":
        db.run("UPDATE review_items SET status = 'skipped', labeled_by = %s, labeled_at = now() WHERE item_id = %s", (admin[:80], item_id))
        return {"ok": True, **counts()}
    if choice == "kb" and kb_id:
        db.run("INSERT INTO eval_cases (question, expected_kb, expect_abstain, origin, split) VALUES (%s,%s,false,'review',%s) ON CONFLICT (question) DO UPDATE SET expected_kb = EXCLUDED.expected_kb, expect_abstain = false, origin = 'review'", (q, kb_id, _split(q)))
    else:                                                    # none of the candidates, or not about NEXUS-L: the assistant must NOT answer it
        db.run("INSERT INTO eval_cases (question, expected_kb, expect_abstain, origin, split) VALUES (%s,NULL,true,'review',%s) ON CONFLICT (question) DO UPDATE SET expected_kb = NULL, expect_abstain = true, origin = 'review'", (q, _split(q)))
        if choice == "none" and len(answer.strip()) >= 20:   # the administrator knows the right answer: it goes live now
            row = db.one("INSERT INTO admin_corrections (question, correct_answer, error_type, status, approved_by, approved_at) VALUES (%s,%s,'missing','approved',%s,now()) ON CONFLICT DO NOTHING RETURNING correction_id", (q, redact(answer), admin[:80]))
            if row:
                indexer.index_corrections()
    db.run("UPDATE review_items SET status = 'done', choice = %s, kb_id = %s, labeled_by = %s, labeled_at = now() WHERE item_id = %s", (choice, kb_id, admin[:80], item_id))
    return {"ok": True, **counts()}


def who(admin_id: str) -> str:
    return hashlib.sha256(f"nexus-l:{admin_id}".encode()).hexdigest()[:24]
