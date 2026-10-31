from __future__ import annotations

import logging
import os

from app.config import settings

log = logging.getLogger("nexus.tracing")


def init_tracing() -> None:
    """Every LangGraph node, model call and retriever call becomes a span in Phoenix (self-hosted, on this machine)."""
    if not settings.phoenix_enabled:
        return
    os.environ.setdefault("PHOENIX_WORKING_DIR", str(settings.home / "phoenix"))
    try:
        from openinference.instrumentation.langchain import LangChainInstrumentor
        from phoenix.otel import register

        provider = register(project_name="nexus-l-assistant", endpoint=settings.phoenix_endpoint, batch=True, auto_instrument=False)
        LangChainInstrumentor().instrument(tracer_provider=provider)
    except Exception as e:                                  # tracing must never stop the assistant from answering
        log.warning("Phoenix tracing is off: %s", e)
