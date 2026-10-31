from __future__ import annotations

import logging
import os
from typing import Any

from app.config import settings
from app.textutil import redact

log = logging.getLogger("nexus.memory")
os.environ.setdefault("FASTEMBED_CACHE_PATH", str(settings.home / "models"))
os.environ.setdefault("MEM0_TELEMETRY", "False")        # nothing is reported anywhere

_mem = None


def _config() -> dict[str, Any]:
    cfg: dict[str, Any] = {
        "embedder": {"provider": "fastembed", "config": {"model": settings.embed_model, "embedding_dims": 384}},
        "vector_store": {"provider": "qdrant", "config": {"path": str(settings.home / "mem0_qdrant"), "collection_name": "mem0", "embedding_model_dims": 384, "on_disk": True}},
        "history_db_path": str(settings.home / "mem0_history.db"),
    }
    # Mem0 only needs a model to EXTRACT facts from raw conversations (infer=True). We store already-structured memories
    # with infer=False, so no model is called; a local Ollama model is configured only so extraction can be switched on later.
    cfg["llm"] = {"provider": "ollama", "config": {"model": settings.ollama_model, "ollama_base_url": settings.ollama_url, "temperature": 0}}
    return cfg


def mem():
    global _mem
    if _mem is None:
        from mem0 import Memory

        _mem = Memory.from_config(_config())
    return _mem


def recall(user_id: str, query: str, limit: int = 5) -> list[str]:
    """What is known about this user and the organisation (admin overrides are stored under the user id 'org')."""
    out: list[str] = []
    for uid in (user_id, "org"):
        try:
            res = mem().search(query, filters={"user_id": uid}, limit=limit) if _supports_filters() else mem().search(query, user_id=uid, limit=limit)
        except Exception as e:                           # memory must never break an answer
            log.warning("mem0 search failed: %s", e)
            continue
        items = res.get("results", res) if isinstance(res, dict) else res
        out += [i["memory"] for i in items if isinstance(i, dict) and i.get("memory")]
    return out[: limit * 2]


def _supports_filters() -> bool:
    import inspect

    return "filters" in inspect.signature(mem().search).parameters


def forget_correction(correction_id: int) -> int:
    """Removes the organisation memory written for a correction that an administrator retired."""
    n = 0
    try:
        res = mem().get_all(filters={"user_id": "org"}, limit=1000) if _supports_filters_getall() else mem().get_all(user_id="org", limit=1000)
        items = res.get("results", res) if isinstance(res, dict) else res
        for i in items:
            if (i.get("metadata") or {}).get("correction_id") == correction_id:
                mem().delete(i["id"])
                n += 1
    except Exception as e:
        log.warning("mem0 forget failed: %s", e)
    return n


def _supports_filters_getall() -> bool:
    import inspect

    return "filters" in inspect.signature(mem().get_all).parameters


def remember(user_id: str, text: str, *, trust: str = "verified_turn", metadata: dict[str, Any] | None = None) -> None:
    """Stores one already-structured fact. Admin overrides use trust='admin' and the shared user id 'org'."""
    try:
        mem().add(redact(text), user_id=user_id, metadata={"trust": trust, **(metadata or {})}, infer=False)
    except Exception as e:
        log.warning("mem0 add failed: %s", e)
