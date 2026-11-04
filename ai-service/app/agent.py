from __future__ import annotations

import re
import time
from typing import Any, Literal

from langgraph.graph import END, StateGraph
from opentelemetry import trace

from app import calibration, context, db, dbskill, glossary, labdocs, memory, methods, requirements, rerank, store
from app import nlu as nlu_mod
from app.config import settings
from app.nlu import nlu_node, rule_node
from app.prompts import ABSTAIN_TEXT, HUMAN_TEXT, SYSTEM_PROMPT
from app.state import AgentAnswer, AgentState, Citation
from app.textutil import coverage, norm, redact, sentences, words

tracer = trace.get_tracer("nexus-l.agent")

_boost_cache: tuple[float, dict[int, float]] = (0.0, {})


def boosts() -> dict[int, float]:
    global _boost_cache
    if time.time() - _boost_cache[0] > 60:
        _boost_cache = (time.time(), {int(r["kb_id"]): float(r["boost"]) for r in db.query("SELECT kb_id, boost FROM doc_boosts")})
    return _boost_cache[1]


def _dense_norm(cos: float) -> float:
    """bge-small: unrelated text scores about 0.45, a clear paraphrase 0.80 and above."""
    return max(0.0, min(1.0, (cos - 0.45) / 0.40))


# ------------------------------------------------------------------------------------------------------ nodes

def context_node(state: AgentState) -> AgentState:
    """Conversation continuity: a follow-up is searched together with the topic it belongs to, so a talk does not drop mid-way."""
    hist = state.get("history") or []
    r = context.resolve(state["question"], hist, state.get("prev_question", ""))
    label = f"followup of '{r['topic'][:40]}' kb={r['topic_kb']}" if r["followup"] and r["topic"] else ("followup (no topic)" if r["followup"] else "new topic")
    text, casual = context.casual_expand(r["search_text"])                 # "sole is peeling" -> the manual's "adhesion peel strength"
    return {"search_text": text, "casual": casual, "topic": r["topic"], "topic_kb": r["topic_kb"], "followup": r["followup"], "trace": [f"context:{label}"]}


def memory_node(state: AgentState) -> AgentState:
    if state.get("user_id") == "eval":                        # evaluation replays have no memories
        return {"memories": [], "trace": ["mem0:skipped (eval)"]}
    mems = memory.recall(state["user_id"], state["question"])
    return {"memories": mems, "trace": [f"mem0:{len(mems)} memories"]}


def retrieve_node(state: AgentState) -> AgentState:
    q = state.get("search_text") or state["question"]
    qw = words(q)
    ui = state.get("ui")
    is_admin = bool(ui and ui.role == "admin")
    page = (ui.page if ui else "").lower()
    hits: dict[int, dict[str, Any]] = {}
    for kind, coll in (("correction", "corrections"), ("doc", "docs")):
        for h in store.search(coll, q, settings.top_k):
            p = h["payload"]
            if p.get("role_min") == "admin" and not is_admin:
                continue
            kb = int(p.get("kb_id") or 0) or -int(p.get("correction_id", 0))
            body = p.get("body", "")
            # the title and keywords say what an answer IS about; the body only mentions things, so it counts for less
            cover = 0.7 * coverage(qw, f"{p.get('title', '')} {p.get('keywords', '')}") + 0.3 * coverage(qw, f"{p.get('title', '')} {p.get('keywords', '')} {body}")
            score = 0.55 * _dense_norm(h["score"]) + 0.45 * cover + boosts().get(kb, 0.0)
            if p.get("page_match") and page and str(p["page_match"]).lower() in page:
                score += 0.03
            if kind == "correction":
                score += 0.05                                   # administrator-approved answers rank first
            if state.get("followup") and state.get("topic_kb") and kb == state["topic_kb"]:
                score += 0.08                                   # a follow-up stays on the answer it follows
            cur = hits.get(kb)
            if cur is None or score > cur["score"]:
                hits[kb] = {"key": kb, "kind": kind, "kb_id": kb if kb > 0 else p.get("kb_id"), "title": p.get("title", ""), "text": body, "steps": p.get("steps", []),
                            "score": round(min(1.0, score), 4), "dense": round(h["score"], 4), "cover": round(cover, 3), "source_id": f"{kind}:{kb}"}
    ranked = sorted(hits.values(), key=lambda d: d["score"], reverse=True)[: settings.top_k]
    feat: dict[str, float] = {}
    if ranked:
        feat = {"hybrid": ranked[0]["score"], "cover": ranked[0]["cover"], "rr": 0.0, "margin": 0.0, "rr_margin": 0.0, "qlen": min(1.0, len(qw) / 10)}
    # second stage: a cross-encoder reads the question with each candidate answer and re-orders them
    # (a curated everyday-word match is not re-read: the cross-encoder does not know "peeling" means adhesion; the hybrid score decides)
    if state.get("use_rerank") and not state.get("casual") and len(ranked) > 1 and rerank.ready():
        cand = ranked[: settings.rerank_candidates]
        rr = rerank.scores(q, [rerank.doc_text(c["title"], "", c["text"]) for c in cand])
        if len(rr) == len(cand):
            for c, r in zip(cand, rr):
                c["rr"] = round(r, 4)
                c["blend"] = 0.5 * c["score"] + 0.5 * r
            cand.sort(key=lambda d: d["blend"], reverse=True)
            ranked = cand + ranked[len(cand):]
            second = ranked[1] if len(ranked) > 1 else None
            feat = {"hybrid": ranked[0]["score"], "cover": ranked[0]["cover"], "rr": ranked[0]["rr"], "qlen": min(1.0, len(qw) / 10),
                    "margin": ranked[0]["score"] - (second["score"] if second else 0.0), "rr_margin": ranked[0]["rr"] - (second.get("rr", 0.0) if second else 0.0)}
            p = calibration.predict(state.get("calib"), feat)
            if p is not None:
                ranked[0]["score"] = round(p, 4)                  # the confidence IS the calibrated probability
                ranked[0]["calibrated"] = True
            else:
                ranked[0]["score"] = round(ranked[0]["blend"], 4)
    best = f" (best {ranked[0]['score']:.2f}: {ranked[0]['title'][:50]})" if ranked else ""
    return {"docs": ranked, "feat": feat, "trace": [f"retrieve:{len(ranked)}{best}"]}


def _extractive(state: AgentState) -> AgentAnswer:
    docs = state.get("docs", [])
    if not docs:
        return AgentAnswer(answer="", self_confidence=0.0)
    top = docs[0]
    sents = sentences(top["text"])
    return AgentAnswer(
        answer=top["text"], steps=list(top.get("steps") or []), kb_id=top.get("kb_id"),
        citations=[Citation(source_id=top["source_id"], quote=(sents[0] if sents else top["text"])[:300])],
        claims=sents, self_confidence=top["score"],
    )


def _ollama(state: AgentState) -> AgentAnswer:
    """A local model composes the answer from the retrieved text only. Verified afterwards by verify_node, like any other draft."""
    from langchain_core.prompts import ChatPromptTemplate
    from langchain_ollama import ChatOllama

    docs = state.get("docs", [])
    ctx = "\n\n".join(f"[{d['source_id']}|{d['kind']}] {d['text']}" for d in docs) or "(no documents were found)"
    llm = ChatOllama(model=settings.ollama_model, base_url=settings.ollama_url, temperature=0).with_structured_output(AgentAnswer)
    prompt = ChatPromptTemplate.from_messages([("system", SYSTEM_PROMPT), ("human", "{question}")])
    ans: AgentAnswer = (prompt | llm).invoke({
        "question": redact(state["question"]), "context": ctx, "threshold": state["threshold"],
        "memories": "\n".join(f"- {m}" for m in state.get("memories", [])) or "(none)",
        "ui_state": state["ui"].model_dump_json() if state.get("ui") else "{}",
    })
    if docs:
        ans.kb_id = docs[0].get("kb_id")
        ans.self_confidence = min(ans.self_confidence, docs[0]["score"])
    return ans


def generate_node(state: AgentState) -> AgentState:
    draft = _ollama(state) if settings.llm_mode == "ollama" else _extractive(state)
    return {"draft": draft, "attempts": state.get("attempts", 0) + 1, "trace": [f"generate[{settings.llm_mode}]:{len(draft.claims)} claims self={draft.self_confidence:.2f}"]}


def _supported(claim: str, context_norm: str) -> bool:
    """A claim is supported only if the context contains it, or (for a reworded claim) covers its words AND every number in it."""
    c = norm(claim)
    if not c:
        return True
    if c in context_norm:
        return True
    nums = set(re.findall(r"\d+(?:\.\d+)?", c))
    return coverage(words(claim), context_norm) >= 0.9 and all(n in context_norm for n in nums)


def verify_node(state: AgentState) -> AgentState:
    """Confidence is computed, never self-reported: relevance of the evidence x ambiguity x groundedness of the answer.

    relevance    hybrid score of the best retrieved entry (meaning + word coverage + learned boost)
    ambiguity    x0.92 when the runner-up is almost as good (two different answers fit)
    groundedness share of the answer's claims that the retrieved text itself supports; 0 if a citation is not an exact quote
    """
    d, docs = state.get("draft"), state.get("docs", [])
    span = trace.get_current_span()
    if not d or not d.answer or not docs:
        span.set_attribute("nexus.confidence", 0.0)
        return {"grounded": 0.0, "confidence": 0.0, "trace": ["verify:nothing to check"]}
    ctx = norm(" ".join(x["text"] for x in docs))
    grounded = sum(_supported(c, ctx) for c in d.claims) / max(1, len(d.claims))
    cited_ok = bool(d.citations) and all(norm(c.quote) in ctx for c in d.citations)
    top = docs[0]["score"]
    if docs[0].get("calibrated"):
        # the calibrated probability already accounts for coverage and the gap to the runner-up
        # the reranker can be fooled by a shared word ("today", "report"): the answer must also contain the question's own words
        # and the reader itself must find the answer relevant: on questions that are not about NEXUS-L it scores below 0.05, on real ones above 0.9
        margin, unmatched = 1.0, min(1.0, docs[0].get("cover", 0.0) / 0.5) * min(1.0, docs[0].get("rr", 0.0) / 0.5)
        conf = round(top * grounded * unmatched * (1.0 if cited_ok else 0.0), 3)
    else:
        margin = 0.92 if len(docs) > 1 and top - docs[1]["score"] < 0.03 and docs[1].get("kb_id") != docs[0].get("kb_id") else 1.0
        unmatched = min(1.0, docs[0].get("cover", 0.0) / 0.6)     # a question whose words the answer mostly does not contain is not answered
        conf = round(min(d.self_confidence, top) * margin * grounded * unmatched * (1.0 if cited_ok else 0.0), 3)
    span.set_attribute("nexus.confidence", conf)
    span.set_attribute("nexus.grounded", grounded)
    span.set_attribute("nexus.threshold", state["threshold"])
    return {"grounded": round(grounded, 3), "confidence": conf, "trace": [f"verify:grounded={grounded:.2f} cited_ok={cited_ok} margin={margin} conf={conf:.2f}"]}


def decide(state: AgentState) -> Literal["finish", "retry", "documents"]:
    if state["confidence"] >= state["threshold"]:
        return "finish"
    docs = state.get("docs") or []
    near_miss = bool(docs) and (docs[0].get("rr", 0.0) >= 0.3 or docs[0]["score"] >= 0.5)    # a question that is clearly not about NEXUS-L is never widened until something matches
    if state.get("attempts", 0) <= settings.max_retries and near_miss:
        return "retry"
    return "documents"                                         # no approved answer fits: look in the lab documents before giving up


# using NEXUS-L itself ("how do I add a bonding test", "print the report") is not in the lab documents: those questions are left to the
# approved answers and the PHP brain, never answered with a passage that merely shares the words
_SYSTEM_Q = re.compile(
    r"(^|\b)(how (do|can|should) i|where (do|can) i|how to|can i|i want to|i need to)\s+(add|create|make|enter|encode|record|register|open|edit|"
    r"delete|remove|print|export|download|upload|import|send|save|submit|approve|sign|find|see|view|change|update|sync|attach|generate|log)\b"
    r"|\b(nexus-?l|button|tab|screen|click|dashboard|inbox|interlink|designer|settings|password|account|login|log in|sign in|qr code|pdf)\b", re.I)


def documents_node(state: AgentState) -> AgentState:
    """Second source: the controlled lab documents. Answers only when a passage is clearly about the question, and names it."""
    # an approved NEXUS-L answer that only just missed its threshold beats a document (how to use the system is not in the lab documents)
    if state.get("draft") and state.get("confidence", 0.0) >= state["threshold"] - 0.03 and state.get("grounded", 0.0) >= 0.99:
        out = finish_node(state)
        out["trace"] = [f"documents:skipped (approved answer near threshold {state['confidence']:.2f})"]
        return out
    if _SYSTEM_Q.search(state["question"]):
        ui = state.get("ui")
        nxt = human_node(state) if ui and ui.last_error else abstain_node(state)
        nxt["trace"] = ["documents:skipped (question about using NEXUS-L)"]
        return nxt
    ans = labdocs.answer(state.get("search_text") or state["question"], state.get("session_id", ""))
    if ans and ans["confidence"] >= labdocs.THRESHOLD:
        if state["user_id"] != "eval":
            memory.remember(state["user_id"], f"Asked: {state['question']} -> answered from {ans['source']['file']} ({ans['source']['loc']})")
        return {"route": "agent", "confidence": ans["confidence"], "trace": [f"documents:{ans['source']['file']} conf={ans['confidence']:.2f}"], "payload": _doc_payload(ans)}
    if ans and ans.get("clarify"):                                 # close to a few documents, none clearly: ask which one
        return {"route": "agent", "confidence": ans["confidence"], "trace": [f"documents:clarify ({len(labdocs._pending.get(state.get('session_id', ''), {}).get('options', []))} options)"],
                "payload": _doc_payload(ans)}
    best = f"{ans['source']['file']} conf={ans['confidence']:.2f}" if ans else "none"
    ui = state.get("ui")
    nxt = human_node(state) if ui and ui.last_error else abstain_node(state)   # a failing user with a visible error is handed to a person
    nxt["trace"] = [f"documents:no fit ({best})"]
    return nxt


def rewrite_node(state: AgentState) -> AgentState:
    """Self-correction: widen the search with what the page says the user is doing, then retrieve again."""
    ui = state.get("ui")
    extra = " ".join(x for x in ((ui.page if ui else ""), (ui.last_error if ui and ui.last_error else ""), *(a.split(":")[-1] for a in (ui.last_actions if ui else []))) if x)
    if state.get("followup") and state.get("topic") and state.get("attempts", 0) <= 1:
        base = context.casual_expand(state["question"])[0]                           # the topic did not help: it may be a new subject, so try the message alone
    else:
        base = state.get("search_text") or state["question"]
    nxt = f"{base} {extra}".strip() if extra and extra not in base else base
    # never add the candidate's own words to the query: that would make any answer look like a perfect match
    return {"search_text": nxt, "trace": [f"rewrite:+'{extra[:40]}'"]}


def finish_node(state: AgentState) -> AgentState:
    d = state["draft"]
    assert d is not None
    if state["user_id"] != "eval" and d.citations:             # evaluation replays must not write memories
        memory.remember(state["user_id"], f"Asked: {state['question']} -> answered with: {d.citations[0].source_id}")
    return {"route": "agent", "payload": {"text": d.answer, "steps": d.steps, "kb_id": d.kb_id, "citations": [c.model_dump() for c in d.citations], "confidence": state["confidence"], "route": "agent"}}


def abstain_node(state: AgentState) -> AgentState:
    return {"route": "abstain", "payload": {"text": ABSTAIN_TEXT, "confidence": state["confidence"], "route": "abstain", "log_gap": True, "suggest_admin": True,
                                           "related": [{"kb_id": x.get("kb_id"), "title": x["title"]} for x in state.get("docs", [])[:3]]}}


def human_node(state: AgentState) -> AgentState:
    return {"route": "human", "payload": {"text": HUMAN_TEXT, "escalate": True, "confidence": state["confidence"], "route": "human", "log_gap": True}}


def _doc_payload(ans: dict[str, Any]) -> dict[str, Any]:
    return {"text": ans["text"], "steps": [], "kb_id": None, "confidence": ans["confidence"], "route": "agent", "source": "documents",
            "document": ans["source"], "clarify": bool(ans.get("clarify")), "options": ans.get("options", []),
            "citations": [{"source_id": f"labdoc:{ans['source']['path']}#{ans['source']['loc']}", "quote": ans["excerpt"][:300]}]}


def first_node(state: AgentState) -> AgentState:
    """Before anything else: the reply to "which one do you mean?" (a number, or a detail that narrows the last question), and questions
    about the laboratory's own data ("how many failed this month", a Lab Reference), which the NEXUS-L database answers exactly."""
    q, sid = state["question"], state.get("session_id", "")
    # politeness and Taglish openers carry no meaning ("paano po", "pa-check po", "sir"); a typo-corrected copy is the second try of every skill
    q = re.sub(r"\b(po|opo|pa-?check|pakisabi|paki|sir|maam|ma'am|pls|please)\b", " ", q, flags=re.I)
    q = re.sub(r"^\s*(paano|ano|saan)\s+(?=(what|who|how|can|does|is|are|which|show|list|give|where)\b)", "", q.strip(), flags=re.I).strip() or state["question"]
    qc = labdocs.clean_question(q)
    both = lambda fn, *a: (fn(qc, *a) if qc.lower() != q.lower() else None) or fn(q, *a)       # noqa: E731
    picked = labdocs.choose(sid, q)
    if picked and picked["confidence"] >= labdocs.THRESHOLD or (picked and picked.get("clarify")):
        how = f"option {picked['chosen']}" if picked.get("chosen") else f"narrowed '{picked.get('narrowed', '')[:40]}'"
        return {"route": "agent", "confidence": picked["confidence"], "payload": _doc_payload(picked), "trace": [f"clarified:{how} -> {picked['source']['file']}"]}
    # "the standard for eyelets": the requirement row itself, not the whole test method (and "adult casual?" right after it)
    gl = both(glossary.answer)
    if gl:
        return {"route": "agent", "confidence": 0.97, "trace": [f"glossary:{gl['kind']}"], "payload": {"text": gl["text"], "steps": [], "kb_id": None, "confidence": 0.97, "route": "agent", "source": "glossary"}}
    req = both(requirements.answer, sid)
    if req:
        return {"route": "agent", "confidence": req["confidence"], "trace": [f"requirements:{req['source']['file']} {req['source']['loc']}"],
                "payload": {"text": req["text"], "steps": [], "kb_id": None, "confidence": req["confidence"], "route": "agent", "source": "documents",
                            "document": req["source"], "citations": [{"source_id": f"labdoc:{req['source']['path']}#{req['source']['loc']}", "quote": req["excerpt"]}]}}
    # a test method by code or by what it tests ("what is VF-B04", "test for hydrolysis"): name, group, what it measures, its requirement
    meth = both(methods.answer)
    if meth:
        src = meth.get("source")
        return {"route": "agent", "confidence": 0.97, "trace": [f"methods:{meth.get('code', meth['kind'])}"],
                "payload": {"text": meth["text"], "steps": [], "kb_id": None, "confidence": 0.97, "route": "agent", "source": "documents" if src else "database",
                            **({"document": src, "citations": [{"source_id": f"labdoc:{src['path']}#{src['loc']}", "quote": meth["text"][:200]}]} if src else {})}}
    # "open DTP..." stays the button that opens the report (rule route); a Lab Reference in any other question is looked up
    data = None if (nlu_mod.local_parse(q).name == "open_report" and not re.search(r"\b(result|results|reading|readings|status|detail|details|complete|clearly|all)\b", q, re.I)) else both(dbskill.answer)
    if data:
        return {"route": "agent", "confidence": 0.99, "trace": [f"database:{data['kind']} rows={data.get('rows', data.get('found', 0))}"],
                "payload": {"text": data["text"], "steps": [], "kb_id": None, "confidence": 0.99, "route": "agent", "source": "database", "kind": data["kind"],
                            **({"actions": [{"type": "open", "label": f"Open {data['lab_ref']}", "url": f"dtr?open={data['lab_ref']}"}]} if data.get("lab_ref") else {})}}
    return {"route": "next", "trace": ["first:none"]}


def build_graph(checkpointer: Any | None = None):
    g = StateGraph(AgentState)
    for name, fn in (("first", first_node), ("nlu", nlu_node), ("rule", rule_node), ("context", context_node), ("memory", memory_node), ("retrieve", retrieve_node),
                     ("generate", generate_node), ("verify", verify_node), ("rewrite", rewrite_node), ("documents", documents_node), ("finish", finish_node),
                     ("abstain", abstain_node), ("human", human_node)):
        g.add_node(name, fn)
    g.set_entry_point("first")
    g.add_conditional_edges("first", lambda s: "nlu" if s["route"] == "next" else "end", {"nlu": "nlu", "end": END})
    g.add_conditional_edges("nlu", lambda s: s["route"], {"rule": "rule", "agent": "context"})
    g.add_edge("rule", END)
    g.add_edge("context", "memory")
    g.add_edge("memory", "retrieve")
    g.add_edge("retrieve", "generate")
    g.add_edge("generate", "verify")
    g.add_conditional_edges("verify", decide, {"finish": "finish", "retry": "rewrite", "documents": "documents"})
    g.add_edge("rewrite", "retrieve")
    for n in ("finish", "abstain", "human", "documents"):
        g.add_edge(n, END)
    return g.compile(checkpointer=checkpointer)
