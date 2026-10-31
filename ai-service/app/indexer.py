from __future__ import annotations

import hashlib
import json
import logging
from typing import Any

from app import db, memory, store
from app.config import settings

log = logging.getLogger("nexus.indexer")


def _doc_text(r: dict[str, Any]) -> str:
    return f"{r['title']}. {r.get('keywords') or ''}".strip()


def load_knowledge() -> list[dict[str, Any]]:
    """knowledge.jsonl is written by `php spark ai:export` (approved answers only)."""
    f = settings.knowledge_file
    if not f.exists():
        return []
    return [json.loads(line) for line in f.read_text(encoding="utf-8").splitlines() if line.strip()]


def index_docs(force: bool = False) -> dict[str, Any]:
    rows = load_knowledge()
    digest = hashlib.sha1(json.dumps(rows, sort_keys=True, ensure_ascii=False).encode()).hexdigest()
    marker = settings.home / "knowledge.sha1"
    if not force and marker.exists() and marker.read_text() == digest and store.count("docs") == len(rows):
        return {"docs": len(rows), "changed": False}
    store.upsert("docs", [{"key": r["id"], "text": _doc_text(r), "payload": {
        "kb_id": r["id"], "title": r["title"], "keywords": r.get("keywords", ""), "body": r["body"], "steps": r.get("steps", []),
        "page_match": r.get("page_match"), "role_min": r.get("role_min", "user")}} for r in rows])
    removed = store.delete_missing("docs", {r["id"] for r in rows})
    marker.write_text(digest)
    return {"docs": len(rows), "changed": True, "removed": removed}


def index_corrections() -> int:
    """Approved administrator corrections: into Qdrant (retrieved first) and Mem0 (as organisation-wide overrides)."""
    rows = db.query("SELECT * FROM admin_corrections WHERE status = 'approved' AND indexed_at IS NULL")
    for r in rows:
        store.upsert("corrections", [{"key": r["correction_id"], "text": r["question"], "payload": {
            "correction_id": r["correction_id"], "title": r["question"], "keywords": "", "body": r["correct_answer"], "steps": [], "role_min": "user"}}])
        memory.remember("org", f"Administrator override: for the question '{r['question']}' the correct answer is: {r['correct_answer']}", trust="admin",
                        metadata={"correction_id": r["correction_id"]})
        db.run("UPDATE admin_corrections SET indexed_at = now() WHERE correction_id = %s", (r["correction_id"],))
    return len(rows)
