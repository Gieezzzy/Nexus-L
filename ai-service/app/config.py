from __future__ import annotations

from pathlib import Path
from typing import Literal

from pydantic_settings import BaseSettings, SettingsConfigDict

ROOT = Path(__file__).resolve().parent.parent


class Settings(BaseSettings):
    """All settings come from ai-service/.env (prefix AI_) or the environment. Nothing here leaves the machine."""

    model_config = SettingsConfigDict(env_file=ROOT / ".env", env_prefix="AI_", extra="ignore")

    # runtime data (models, vector stores, Postgres) lives on C:, away from the web root
    home: Path = Path(r"C:\NexusAI")
    pg_dsn: str = "postgresql://bot@127.0.0.1:5440/botdb"
    service_key: str = ""                       # shared secret with NEXUS-L (header X-AI-Key); empty = no check (local tests only)

    embed_model: str = "BAAI/bge-small-en-v1.5"
    knowledge_file: Path = Path(r"C:\NexusAI\knowledge.jsonl")

    # NLU: Rasa when it runs, the built-in rule router otherwise
    use_rasa: bool = False
    rasa_url: str = "http://127.0.0.1:5005"
    rasa_rule_threshold: float = 0.85

    # generation: "extractive" answers with the approved text itself (nothing can be invented); "ollama" lets a local model
    # compose the answer, which is then verified against the same text
    llm_mode: Literal["extractive", "ollama"] = "extractive"
    ollama_url: str = "http://127.0.0.1:11434"
    ollama_model: str = "qwen2.5:3b-instruct"

    threshold: float = 0.80                     # starting point; the nightly gate replaces it with a calibrated value
    max_retries: int = 2
    top_k: int = 6

    phoenix_endpoint: str = "http://127.0.0.1:6006/v1/traces"
    phoenix_enabled: bool = True


settings = Settings()
settings.home.mkdir(parents=True, exist_ok=True)
