"""Conversation continuity: a follow-up ("why?", "and the steps?", "what about the second one") is searched together with the topic it belongs to.

The topic is the last real (non follow-up) question of the conversation and the answer the assistant gave to it. Both come from the
history NEXUS-L sends with the question, or from the messages table when it sends none. Nothing is guessed: if there is no topic the
question is searched as typed, and the usual confidence gate decides.
"""
from __future__ import annotations

import re
from typing import Any

from app import db
from app.textutil import norm, redact, words

HISTORY_TURNS = 8

# connecting words only: "how", "where", "can you" start ordinary new questions too, so they do not count here
_CUE = re.compile(
    r"^(why|why not|and|and then|then|then what|what about|what next|what if|so|ok and|okay and|also|more|details?|explain|"
    r"steps?|the steps|go on|continue|really|what do you mean|how come)\b", re.I)
_ANAPHORA = re.compile(r"\b(it|its|that|this|those|these|them|they|the same|same one|above|previous|earlier|again|the first|the second|the last|that one|this one)\b", re.I)
# a short question fragment ("how many readings?", "what speed?") leans on the topic; a short request of its own ("tell me a joke") does not
_FRAGMENT = re.compile(r"^(what|how|which|when|where|who|is|are|does|do|can|any)\b", re.I)
_SELF_CONTAINED = re.compile(r"\b(DTP\d{8}[A-Z0-9]{2,}|VF-?[A-J]\d{2})\b", re.I)

# everyday wording -> the manual's own words. Each rule needs ALL its stems in the question (any order), so "sole is peeling off" matches
# ("sole", "peel"). Only phrases that cannot mean anything else are listed; a single generic word ("heavy", "breaks") never is.
CASUAL: list[tuple[tuple[str, ...], str]] = [
    (("sole", "peel"), "sole adhesion peel strength bonding"),
    (("sole", "separat"), "sole adhesion peel strength bonding"),
    (("sole", "com", "off"), "sole adhesion peel strength bonding"),
    (("glue", "fail"), "adhesion bonding peel strength"),
    (("yellow",), "discoloration yellowing UV light"),
    (("colo", "fad"), "colour fastness UV light crockmeter"),
    (("colo", "rub"), "crockmeter rubbing colour fastness"),
    (("sole", "wear"), "abrasion resistance"),
    (("wear", "out"), "abrasion resistance"),
    (("too", "soft"), "hardness shore"),
    (("too", "hard"), "hardness shore"),
    (("shoe", "crack"), "flexing fatigue bally ross"),
    (("shoe", "leak"), "water penetration whole shoe flexing"),
    (("electric", "shock"), "electric shock leakage resistance"),
]


def casual_expand(text: str) -> tuple[str, bool]:
    """(search text, changed). Replaces an everyday phrase by the manual's words and keeps the rest of the question's content words."""
    ws = words(text)
    for stems, tech in CASUAL:
        if all(any(w.startswith(st) for w in ws) for st in stems):
            rest = [w for w in ws if not any(w.startswith(st) for st in stems)]
            return f"{tech} {' '.join(rest)}".strip(), True
    return text, False


def is_followup(question: str) -> bool:
    """Short, starts with a connecting word, or points back with a pronoun, and does not carry its own lab reference or VF code."""
    q = question.strip()
    if not q or _SELF_CONTAINED.search(q):
        return False
    n = len(words(q))                       # content words: "how", "is", "the" ... do not count
    if n <= 2:                              # "why?", "and then?", "how many readings?" carry no topic of their own
        return bool(_CUE.match(q) or _ANAPHORA.search(q) or _FRAGMENT.match(q) or q.endswith("?"))
    return n <= 4 and bool(_CUE.match(q) or _ANAPHORA.search(q))


def load_history(session_id: str, limit: int = HISTORY_TURNS) -> list[dict[str, Any]]:
    """The last turns of this session from the audit store, oldest first. [] when the store cannot be read."""
    try:
        rows = db.query("SELECT sender, body, kb_id FROM messages WHERE session_id = %s AND sender IN ('user','bot') ORDER BY message_id DESC LIMIT %s", (session_id, limit))
    except Exception:
        return []
    return [{"sender": r["sender"], "text": r["body"] or "", "kb_id": r["kb_id"]} for r in reversed(rows)]


def clean_history(raw: list[dict[str, Any]] | None) -> list[dict[str, Any]]:
    out: list[dict[str, Any]] = []
    for h in (raw or [])[-HISTORY_TURNS:]:
        sender = "bot" if str(h.get("sender", "")).lower() in ("bot", "assistant") else "user"
        text = redact(str(h.get("text", "")))[:600].strip()
        if text:
            out.append({"sender": sender, "text": text, "kb_id": h.get("kb_id")})
    return out


def topic_of(history: list[dict[str, Any]], prev_question: str) -> tuple[str, int | None]:
    """(text of the last real user question, kb_id of the answer given to it). The current message is not in the history yet."""
    topic, kb = "", None
    for i in range(len(history) - 1, -1, -1):
        h = history[i]
        if h["sender"] == "bot" and kb is None and h.get("kb_id"):
            kb = int(h["kb_id"])
        if h["sender"] == "user" and not is_followup(h["text"]):
            topic = h["text"]
            break
    if not topic and prev_question and not is_followup(prev_question):
        topic = prev_question
    return topic, kb


def resolve(question: str, history: list[dict[str, Any]], prev_question: str = "") -> dict[str, Any]:
    """{search_text, topic, topic_kb, followup}. search_text is the question alone unless it is a follow-up with a known topic."""
    if not is_followup(question):
        return {"search_text": question, "topic": "", "topic_kb": None, "followup": False}
    topic, kb = topic_of(history, prev_question)
    if not topic:
        return {"search_text": question, "topic": "", "topic_kb": kb, "followup": True}
    return {"search_text": f"{topic} {question}".strip()[:400], "topic": topic, "topic_kb": kb, "followup": True}
