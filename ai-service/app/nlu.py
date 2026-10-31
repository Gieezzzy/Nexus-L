from __future__ import annotations

import re
from typing import Any

import httpx

from app.config import settings
from app.state import AgentState, Intent
from app.textutil import norm

# Intents that are executed by deterministic code and never by a model. Keep the list short and reviewed.
RULE_INTENTS: dict[str, str] = {
    "open_report": "Open a report by Lab Reference",
    "talk_to_admin": "Hand over to an administrator",
    "greet": "Greeting",
    "thanks": "Thanks",
    "feedback_negative": "The user pressed or typed 'not helpful'",
}

_LABREF = re.compile(r"\b(DTP\d{8}[A-Z0-9]{2,}(?:-[LR])?)\b", re.I)
_OPEN = re.compile(r"\b(open|show|find|view|edit|preview|print|pdf|download|machine|file|reading|load|check|where)\b", re.I)
_FOLLOW = re.compile(r"^(why|why not|and then|then what|what next|how come|really|what do you mean|explain|more|details?)\??$", re.I)


def local_parse(text: str) -> Intent:
    """The built-in router, used when Rasa is not running. Same contract as Rasa's /model/parse."""
    t = norm(text)
    m = _LABREF.search(text)
    if m and _OPEN.search(text):
        return Intent(name="open_report", confidence=0.97, entities={"lab_ref": m.group(1).upper()})
    if re.search(r"\b(talk|speak|chat|connect)\b.*\b(admin|administrator|human|person|someone)\b", t) or t in {"i need an admin", "admin please", "human"}:
        return Intent(name="talk_to_admin", confidence=0.95)
    if re.fullmatch(r"(hi|hello|hey|good (morning|afternoon|evening)|yo)( there)?", t):
        return Intent(name="greet", confidence=0.98)
    if re.fullmatch(r"(thanks|thank you|ty|ok thanks|great|got it|thank you very much)", t):
        return Intent(name="thanks", confidence=0.98)
    if t in {"not helpful", "no", "wrong", "that is wrong", "not what i asked", "useless"}:
        return Intent(name="feedback_negative", confidence=0.95)
    if _FOLLOW.match(text.strip()):
        return Intent(name="followup", confidence=0.9)
    return Intent(name="nlu_fallback", confidence=0.0)


_rasa_down_until = 0.0


async def rasa_parse(text: str) -> Intent:
    """Rasa when it answers; the built-in router when it does not. A failed call switches Rasa off for a minute (circuit breaker), because
    on Windows a refused connection to a local port can take seconds and every user message would pay for it."""
    global _rasa_down_until
    import time

    if time.time() < _rasa_down_until:
        return local_parse(text)
    try:
        async with httpx.AsyncClient(timeout=httpx.Timeout(2.0, connect=0.4)) as c:
            r = await c.post(f"{settings.rasa_url}/model/parse", json={"text": text})
            r.raise_for_status()
            j: dict[str, Any] = r.json()
        ents = {e["entity"]: str(e["value"]) for e in j.get("entities", []) if "value" in e}
        return Intent(name=j["intent"]["name"], confidence=float(j["intent"]["confidence"]), entities=ents)
    except (httpx.HTTPError, KeyError, ValueError):
        _rasa_down_until = time.time() + 60
        return local_parse(text)                       # NLU down or malformed: never fail the user


def nlu_node(state: AgentState) -> AgentState:
    import asyncio

    intent = asyncio.run(rasa_parse(state["question"])) if settings.use_rasa else local_parse(state["question"])
    is_rule = intent.name in RULE_INTENTS and intent.confidence >= settings.rasa_rule_threshold
    out: AgentState = {"intent": intent, "route": "rule" if is_rule else "agent", "attempts": 0, "search_text": state["question"],
                       "trace": [f"nlu:{intent.name}@{intent.confidence:.2f}->{'rule' if is_rule else 'agent'}"]}
    if intent.name == "followup" and state.get("prev_question"):
        out["search_text"] = state["prev_question"] + " " + state["question"]      # "why" is about the previous question
        out["route"] = "agent"
    return out


def rule_node(state: AgentState) -> AgentState:
    """Deterministic handlers: they return text and buttons only. The assistant never performs the action."""
    name, ent = state["intent"].name, state["intent"].entities
    p: dict[str, Any]
    if name == "open_report":
        ref = ent.get("lab_ref", "")
        p = {"text": f"Opening {ref}.", "actions": [{"type": "open", "label": f"Open {ref}", "url": f"dtr?open={ref}"}], "intent": name, "entities": ent}
    elif name == "talk_to_admin":
        p = {"text": "Connecting you to an administrator.", "escalate": True}
    elif name == "thanks":
        p = {"text": "You are welcome! Ask me anything else about NEXUS-L."}
    elif name == "feedback_negative":
        p = {"text": "Sorry about that. I will note it. You can rephrase the question, or I can connect you to an administrator.", "suggest_admin": True, "log_gap": True}
    else:
        p = {"text": "Hello! I am the NEXUS-L assistant. Ask me how to do something in this system."}
    return {"route": "rule", "confidence": 1.0, "payload": {**p, "route": "rule", "confidence": 1.0}, "trace": [f"rule:{name}"]}
