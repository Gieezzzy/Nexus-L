from __future__ import annotations

import logging
import math
import threading
from typing import Any

from app.config import settings

log = logging.getLogger("nexus.rerank")
_enc: Any = None
_lock = threading.Lock()


def _encoder() -> Any:
    global _enc
    with _lock:
        if _enc is None:
            from fastembed.rerank.cross_encoder import TextCrossEncoder

            _enc = TextCrossEncoder(settings.rerank_model, cache_dir=str(settings.home / "models"))
        return _enc


def ready() -> bool:
    return settings.rerank_enabled


def doc_text(title: str, keywords: str, body: str) -> str:
    return f"{title}. {keywords}. {body[:400]}".strip(". ")


def scores(query: str, docs: list[str]) -> list[float]:
    """Relevance of each document to the query as a 0..1 number (a sigmoid of the cross-encoder's logit). [] when it cannot run."""
    if not docs or not settings.rerank_enabled:
        return []
    try:
        logits = list(_encoder().rerank(query, docs))
        return [1.0 / (1.0 + math.exp(-max(-30.0, min(30.0, float(x))))) for x in logits]
    except Exception as e:                                   # the assistant must keep answering without it
        log.warning("reranker unavailable: %s", e)
        return []
