from __future__ import annotations

import re
import time
from typing import Any, Literal

from langgraph.graph import END, StateGraph
from opentelemetry import trace

from app import db, memory, store
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

def memory_node(state: AgentState) -> AgentState:
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
            cur = hits.get(kb)
            if cur is None or score > cur["score"]:
                hits[kb] = {"key": kb, "kind": kind, "kb_id": kb if kb > 0 else p.get("kb_id"), "title": p.get("title", ""), "text": body, "steps": p.get("steps", []),
                            "score": round(min(1.0, score), 4), "dense": round(h["score"], 4), "cover": round(cover, 3), "source_id": f"{kind}:{kb}"}
    ranked = sorted(hits.values(), key=lambda d: d["score"], reverse=True)[: settings.top_k]
    best = f" (best {ranked[0]['score']:.2f}: {ranked[0]['title'][:50]})" if ranked else ""
    return {"docs": ranked, "trace": [f"retrieve:{len(ranked)}{best}"]}


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
    margin = 0.92 if len(docs) > 1 and top - docs[1]["score"] < 0.03 and docs[1].get("kb_id") != docs[0].get("kb_id") else 1.0
    unmatched = min(1.0, docs[0].get("cover", 0.0) / 0.6)         # a question whose words the answer mostly does not contain is not answered
    conf = round(min(d.self_confidence, top) * margin * grounded * unmatched * (1.0 if cited_ok else 0.0), 3)
    span.set_attribute("nexus.confidence", conf)
    span.set_attribute("nexus.grounded", grounded)
    span.set_attribute("nexus.threshold", state["threshold"])
    return {"grounded": round(grounded, 3), "confidence": conf, "trace": [f"verify:grounded={grounded:.2f} cited_ok={cited_ok} margin={margin} conf={conf:.2f}"]}


def decide(state: AgentState) -> Literal["finish", "retry", "abstain", "human"]:
    if state["confidence"] >= state["threshold"]:
        return "finish"
    if state.get("attempts", 0) <= settings.max_retries:
        return "retry"
    ui = state.get("ui")
    return "human" if ui and ui.last_error else "abstain"      # a failing user with a visible error is handed to a person


def rewrite_node(state: AgentState) -> AgentState:
    """Self-correction: widen the search with what the page says the user is doing, then retrieve again."""
    ui = state.get("ui")
    extra = " ".join(x for x in ((ui.page if ui else ""), (ui.last_error if ui and ui.last_error else ""), *(a.split(":")[-1] for a in (ui.last_actions if ui else []))) if x)
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


def build_graph(checkpointer: Any | None = None):
    g = StateGraph(AgentState)
    for name, fn in (("nlu", nlu_node), ("rule", rule_node), ("memory", memory_node), ("retrieve", retrieve_node), ("generate", generate_node),
                     ("verify", verify_node), ("rewrite", rewrite_node), ("finish", finish_node), ("abstain", abstain_node), ("human", human_node)):
        g.add_node(name, fn)
    g.set_entry_point("nlu")
    g.add_conditional_edges("nlu", lambda s: s["route"], {"rule": "rule", "agent": "memory"})
    g.add_edge("rule", END)
    g.add_edge("memory", "retrieve")
    g.add_edge("retrieve", "generate")
    g.add_edge("generate", "verify")
    g.add_conditional_edges("verify", decide, {"finish": "finish", "retry": "rewrite", "abstain": "abstain", "human": "human"})
    g.add_edge("rewrite", "retrieve")
    for n in ("finish", "abstain", "human"):
        g.add_edge(n, END)
    return g.compile(checkpointer=checkpointer)
