from __future__ import annotations

import operator
from typing import Annotated, Any, Literal, TypedDict

from pydantic import BaseModel, Field


class UiState(BaseModel):
    """What the sessionizer reduces the clickstream to: typed, bounded, read-only. This is all the agent ever sees of the page."""

    page: str = ""
    role: Literal["user", "admin"] = "user"
    last_actions: list[str] = Field(default_factory=list, max_length=5)     # e.g. ["click:send_interlink", "view:interlink"]
    last_error: str | None = None                                           # e.g. "service_unreachable"
    form_id: str | None = None


class Intent(BaseModel):
    name: str = "nlu_fallback"
    confidence: float = 0.0
    entities: dict[str, str] = Field(default_factory=dict)


class Citation(BaseModel):
    source_id: str
    quote: str


class AgentAnswer(BaseModel):
    """The answer travels with the evidence it was built from."""

    answer: str
    citations: list[Citation] = Field(default_factory=list)
    claims: list[str] = Field(default_factory=list, description="every factual statement of the answer, one per item")
    self_confidence: float = Field(default=0.0, ge=0, le=1)
    kb_id: int | None = None
    steps: list[str] = Field(default_factory=list)


class AgentState(TypedDict, total=False):
    session_id: str
    user_id: str
    question: str                 # as the user typed it (redacted)
    search_text: str              # what retrieval uses; widened by the self-correction loop
    prev_question: str
    ui: UiState
    intent: Intent
    route: Literal["rule", "agent", "abstain", "human"]
    memories: list[str]
    docs: list[dict[str, Any]]    # {key, kind: doc|correction, kb_id, title, text, score, dense, cover}
    draft: AgentAnswer | None
    grounded: float
    confidence: float
    threshold: float
    use_rerank: bool
    calib: dict[str, Any] | None
    feat: dict[str, float]
    attempts: int
    trace: Annotated[list[str], operator.add]
    payload: dict[str, Any]
