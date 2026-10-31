from __future__ import annotations

import hashlib
import hmac
import logging
import time
from contextlib import asynccontextmanager
from typing import Any, Literal

from fastapi import Depends, FastAPI, Header, HTTPException
from opentelemetry import trace
from pydantic import BaseModel, Field

from app import db, indexer, memory, pipeline, review, store
from app.agent import build_graph
from app.config import settings
from app.state import UiState
from app.textutil import redact
from app.tracing import init_tracing

log = logging.getLogger("nexus.main")


@asynccontextmanager
async def lifespan(app: FastAPI):
    init_tracing()
    db.migrate()
    store.client()
    app.state.docs = indexer.index_docs()
    log.info("indexed: %s", app.state.docs)
    saver = None
    try:
        from langgraph.checkpoint.postgres import PostgresSaver

        cm = PostgresSaver.from_conn_string(settings.pg_dsn)
        saver = cm.__enter__()
        saver.setup()
        app.state.saver_cm = cm
    except Exception as e:                                  # a missing checkpointer must not stop answers
        log.warning("no LangGraph checkpointer: %s", e)
    app.state.graph = build_graph(saver)
    yield
    if getattr(app.state, "saver_cm", None):
        app.state.saver_cm.__exit__(None, None, None)


app = FastAPI(title="LEXIS AI - Laboratory Execution Intelligence System", lifespan=lifespan)


def auth(x_ai_key: str = Header(default="")) -> None:
    if settings.service_key and not hmac.compare_digest(x_ai_key, settings.service_key):
        raise HTTPException(401, "bad key")


def sid(raw: str) -> str:
    return raw[:80]


def uid(raw: str) -> str:
    """The assistant never stores a user name: NEXUS-L's id is hashed."""
    return hashlib.sha256(f"nexus-l:{raw}".encode()).hexdigest()[:24]


# --------------------------------------------------------------------------------------------------------- models
class AskIn(BaseModel):
    session_id: str
    user_id: str
    message: str = Field(min_length=1, max_length=1000)
    prev_question: str = ""
    ui: UiState | None = None
    live_kb_id: int | None = None          # shadow mode: what the production bot answered
    live_conf: float | None = None


class EventIn(BaseModel):
    session_id: str
    user_id: str = ""
    role: Literal["user", "admin"] = "user"
    event_type: str
    page: str = ""
    target: str = ""
    detail: str = ""
    occurred_at: float | None = None


class AdminIn(BaseModel):
    session_id: str
    admin_id: str
    kind: Literal["open", "reply", "close"]
    message: str = ""
    question: str = ""                     # the user question this reply answers (NEXUS-L knows it)
    trigger: Literal["user_escalated", "low_confidence", "abstain", "admin_joined"] = "admin_joined"


class FeedbackIn(BaseModel):
    session_id: str
    kb_id: int | None = None
    value: Literal[-1, 1]
    question: str = ""


class DecideIn(BaseModel):
    action: Literal["approve", "reject"]
    admin_id: str = ""
    correct_answer: str | None = None


# ------------------------------------------------------------------------------------------------------- helpers
def derive_ui(session_id: str, role: str = "user") -> UiState:
    """The sessionizer: the last few clickstream events of a session reduced to a typed UiState."""
    rows = db.query("SELECT event_type, page, target, detail FROM telemetry_events WHERE session_id = %s ORDER BY occurred_at DESC LIMIT 12", (session_id,))
    ui = UiState(role=role)  # type: ignore[arg-type]
    for r in rows:
        if not ui.page and r["page"]:
            ui.page = r["page"]
        if r["event_type"] in ("error", "http_error") and not ui.last_error:
            ui.last_error = (r["detail"] or r["target"] or r["event_type"])[:80]
        if len(ui.last_actions) < 5 and r["event_type"] in ("click", "view", "action"):
            ui.last_actions.append(f"{r['event_type']}:{(r['target'] or r['page'] or '')[:40]}")
    return ui


# -------------------------------------------------------------------------------------------------------- routes
@app.get("/health")
def health() -> dict[str, Any]:
    ok_db = True
    try:
        db.one("SELECT 1 AS x")
    except Exception:
        ok_db = False
    return {"ok": ok_db, "docs": store.count("docs"), "corrections": store.count("corrections"), "threshold": db.active_threshold() if ok_db else None,
            "mode": settings.llm_mode, "rasa": settings.use_rasa}


@app.post("/ask", dependencies=[Depends(auth)])
def ask(body: AskIn) -> dict[str, Any]:
    t0 = time.time()
    s, u = sid(body.session_id), uid(body.user_id)
    q = redact(body.message)
    ui = body.ui or derive_ui(s)
    act = db.active_settings()
    tau = float(act["threshold"])
    db.run("INSERT INTO sessions (session_id, user_id) VALUES (%s,%s) ON CONFLICT DO NOTHING", (s, u))
    db.run("INSERT INTO messages (session_id, sender, body) VALUES (%s,'user',%s)", (s, q))
    with trace.get_tracer("nexus-l").start_as_current_span("ask") as span:
        span.set_attribute("session.id", s)
        out = app.state.graph.invoke(
            {"session_id": s, "user_id": u, "question": q, "prev_question": redact(body.prev_question), "ui": ui, "threshold": tau, "use_rerank": act["rerank"], "calib": act["calib"]},
            config={"configurable": {"thread_id": s}},
        )
        trace_id = format(span.get_span_context().trace_id, "032x")
    p: dict[str, Any] = dict(out["payload"])
    p["trace_id"], p["trail"], p["threshold"] = trace_id, out.get("trace", []), tau
    ms = int((time.time() - t0) * 1000)
    mid = db.one("INSERT INTO messages (session_id, sender, body, trace_id, route, confidence, kb_id) VALUES (%s,'bot',%s,%s,%s,%s,%s) RETURNING message_id",
                 (s, p["text"], trace_id, p.get("route"), p.get("confidence"), p.get("kb_id")))
    db.run("INSERT INTO answer_log (session_id, question, route, confidence, threshold, kb_id, attempts, trail, live_kb_id, live_conf, trace_id, latency_ms) VALUES (%s,%s,%s,%s,%s,%s,%s,%s::jsonb,%s,%s,%s,%s)",
           (s, q, p.get("route", "agent"), float(p.get("confidence", 0)), tau, p.get("kb_id"), int(out.get("attempts", 0)), db.j(p["trail"]), body.live_kb_id, body.live_conf, trace_id, ms))
    p["message_id"], p["latency_ms"] = mid["message_id"] if mid else None, ms
    return p


@app.post("/ingest/events", dependencies=[Depends(auth)])
def ingest_events(events: list[EventIn]) -> dict[str, int]:
    n = 0
    for e in events[:500]:
        db.run("INSERT INTO sessions (session_id, user_id, role) VALUES (%s,%s,%s) ON CONFLICT DO NOTHING", (sid(e.session_id), uid(e.user_id or "?"), e.role))
        db.run("INSERT INTO telemetry_events (session_id, occurred_at, event_type, page, target, detail) VALUES (%s, to_timestamp(%s), %s,%s,%s,%s)",
               (sid(e.session_id), e.occurred_at or time.time(), e.event_type[:30], e.page[:160], e.target[:160], redact(e.detail)[:300]))
        n += 1
    return {"stored": n}


@app.post("/ingest/admin", dependencies=[Depends(auth)])
def ingest_admin(a: AdminIn) -> dict[str, Any]:
    s = sid(a.session_id)
    db.run("INSERT INTO sessions (session_id, user_id, role) VALUES (%s,'?', 'user') ON CONFLICT DO NOTHING", (s,))
    open_row = db.one("SELECT intervention_id FROM admin_interventions WHERE session_id = %s AND closed_at IS NULL ORDER BY intervention_id DESC LIMIT 1", (s,))
    if a.kind == "open" or not open_row:
        open_row = db.one("INSERT INTO admin_interventions (session_id, admin_id, trigger) VALUES (%s,%s,%s) RETURNING intervention_id", (s, uid(a.admin_id), a.trigger))
    iid = open_row["intervention_id"] if open_row else None
    out: dict[str, Any] = {"intervention_id": iid}
    if a.kind == "reply" and a.message.strip():
        text = redact(a.message)
        db.run("INSERT INTO messages (session_id, sender, body) VALUES (%s,'admin',%s)", (s, text))
        question = redact(a.question) or ((db.one("SELECT body FROM messages WHERE session_id = %s AND sender = 'user' ORDER BY message_id DESC LIMIT 1", (s,)) or {}).get("body", ""))
        if question and len(text) >= 20:
            bot = db.one("SELECT message_id, body FROM messages WHERE session_id = %s AND sender = 'bot' ORDER BY message_id DESC LIMIT 1", (s,))
            cid = db.one("INSERT INTO admin_corrections (intervention_id, source_message, question, bot_answer, correct_answer, ui_context, error_type) VALUES (%s,%s,%s,%s,%s,%s::jsonb,%s) ON CONFLICT DO NOTHING RETURNING correction_id",
                         (iid, bot["message_id"] if bot else None, question, bot["body"] if bot else None, text, db.j(derive_ui(s).model_dump()), "wrong" if bot and bot["body"] and not bot["body"].startswith("Checking") else "missing"))
            out["correction_id"] = cid["correction_id"] if cid else None
    if a.kind == "close":
        db.run("UPDATE admin_interventions SET closed_at = now(), resolution = %s WHERE intervention_id = %s", (redact(a.message)[:1000], iid))
    return out


@app.post("/ingest/feedback", dependencies=[Depends(auth)])
def ingest_feedback(f: FeedbackIn) -> dict[str, bool]:
    db.run("UPDATE messages SET feedback = %s WHERE message_id = (SELECT message_id FROM messages WHERE session_id = %s AND sender = 'bot' AND (%s::int IS NULL OR kb_id = %s) ORDER BY message_id DESC LIMIT 1)",
           (f.value, sid(f.session_id), f.kb_id, f.kb_id))
    return {"ok": True}


@app.get("/admin/corrections", dependencies=[Depends(auth)])
def corrections(status: str = "pending") -> list[dict[str, Any]]:
    return db.query("SELECT correction_id, question, bot_answer, correct_answer, error_type, status, created_at FROM admin_corrections WHERE status = %s ORDER BY correction_id DESC LIMIT 200", (status,))


@app.post("/admin/corrections/{cid}/decide", dependencies=[Depends(auth)])
def decide(cid: int, d: DecideIn) -> dict[str, Any]:
    if d.action == "approve":
        if d.correct_answer:
            db.run("UPDATE admin_corrections SET correct_answer = %s WHERE correction_id = %s", (redact(d.correct_answer), cid))
        db.run("UPDATE admin_corrections SET status = 'approved', approved_by = %s, approved_at = now() WHERE correction_id = %s AND status = 'pending'", (uid(d.admin_id), cid))
        return {"ok": True, "indexed": indexer.index_corrections()}                 # live at once; the nightly run re-checks the gate
    was = db.one("SELECT indexed_at FROM admin_corrections WHERE correction_id = %s", (cid,))
    db.run("UPDATE admin_corrections SET status = 'rejected', approved_by = %s, approved_at = now() WHERE correction_id = %s", (uid(d.admin_id), cid))
    if was and was["indexed_at"]:                                           # retiring a correction that is already live removes it from retrieval
        from qdrant_client.http import models as qm

        store.client().delete("corrections", points_selector=qm.PointIdsList(points=[store.point_id("corrections", cid)]))
        memory.forget_correction(cid)
    return {"ok": True}


@app.post("/admin/index", dependencies=[Depends(auth)])
def reindex(force: bool = False) -> dict[str, Any]:
    return indexer.index_docs(force)


@app.post("/admin/pipeline/run", dependencies=[Depends(auth)])
def run_pipeline() -> dict[str, Any]:
    return pipeline.run(app.state.graph)


@app.post("/admin/train/start", dependencies=[Depends(auth)])
def train_start() -> dict[str, Any]:
    return pipeline.start_train(build_graph(None))


@app.get("/admin/train/status", dependencies=[Depends(auth)])
def train_status() -> dict[str, Any]:
    act = db.active_settings()
    ver = db.one("SELECT version, threshold, rerank, holdout_precision, holdout_recall, holdout_abstain, holdout_cases, note, metrics, created_at FROM settings_versions WHERE active") or {}
    cases = db.one("SELECT count(*) total, count(*) FILTER (WHERE origin = 'review') reviewed, count(*) FILTER (WHERE split = 'holdout') holdout FROM eval_cases") or {}
    return {"job": pipeline.JOB, "active": ver, "reranker": bool(act["rerank"]), "calibrated": bool(act["calib"]), "cases": cases, "review": review.counts(),
            "docs": store.count("docs"), "corrections": store.count("corrections")}


@app.post("/admin/review/seed", dependencies=[Depends(auth)])
def review_seed() -> dict[str, int]:
    return review.seed()


@app.get("/admin/review/next", dependencies=[Depends(auth)])
def review_next() -> dict[str, Any]:
    return review.next_item(app.state.graph) or {"item_id": None, **review.counts()}


class LabelIn(BaseModel):
    choice: Literal["kb", "none", "offtopic", "skip"]
    kb_id: int | None = None
    admin_id: str = ""
    answer: str = ""


@app.post("/admin/review/{item_id}/label", dependencies=[Depends(auth)])
def review_label(item_id: int, b: LabelIn) -> dict[str, Any]:
    return review.label(item_id, b.choice, b.kb_id, review.who(b.admin_id), b.answer)


@app.get("/admin/explain", dependencies=[Depends(auth)])
def explain(q: str) -> dict[str, Any]:
    """Why the assistant would answer (or refuse) a question: the best candidates with every signal behind the confidence."""
    from app import agent

    act = db.active_settings()
    st: dict[str, Any] = {"question": redact(q)[:300], "search_text": redact(q)[:300], "ui": UiState(role="admin"), "use_rerank": act["rerank"], "calib": act["calib"], "user_id": "eval", "session_id": "explain"}
    r = agent.retrieve_node(st)  # type: ignore[arg-type]
    return {"threshold": act["threshold"], "feat": r["feat"], "top": [{"title": d["title"], "score": d["score"], "dense": d.get("dense"), "cover": d.get("cover"), "rr": d.get("rr"), "kind": d["kind"]} for d in r["docs"][:3]]}


@app.get("/admin/eval", dependencies=[Depends(auth)])
def eval_breakdown(split: str = "holdout", tau: float | None = None) -> dict[str, Any]:
    return pipeline.breakdown(build_graph(None), tau, split)


@app.get("/admin/report", dependencies=[Depends(auth)])
def report(days: int = 7) -> dict[str, Any]:
    """The numbers that matter, from the audit store (the Phoenix dashboard shows the same per trace)."""
    rows = db.query("SELECT route, count(*) n, avg(confidence) c, avg(latency_ms) ms FROM answer_log WHERE created_at > now() - make_interval(days => %s) GROUP BY route", (days,))
    fb = db.one("SELECT count(*) FILTER (WHERE feedback = 1) up, count(*) FILTER (WHERE feedback = -1) down FROM messages WHERE sender = 'bot' AND created_at > now() - make_interval(days => %s)", (days,))
    shadow = db.one("SELECT count(*) n, count(*) FILTER (WHERE kb_id IS NOT NULL AND kb_id = live_kb_id) agree, count(*) FILTER (WHERE route IN ('abstain','human')) refused FROM answer_log WHERE live_kb_id IS NOT NULL OR live_conf IS NOT NULL")
    return {"routes": rows, "feedback": fb, "shadow": shadow, "active": db.one("SELECT * FROM settings_versions WHERE active")}
