# NEXUS-L assistant service (local, system-only)

Python service next to NEXUS-L. It answers **only from NEXUS-L's own approved answers and administrator corrections**: no web search,
no outside knowledge, nothing leaves this computer (everything listens on 127.0.0.1; the embedding model is on disk).
Design: [docs/ai-bot-architecture.md](../docs/ai-bot-architecture.md).

```
NEXUS-L (PHP) --AiBridge--> /ask, /ingest/*  ->  Rasa or the built-in router  ->  LangGraph agent
                                                  (hard rules)                    memory (Mem0) -> retrieve (Qdrant) -> generate -> verify
                                                                                  confidence >= threshold ? answer : retry : "Checking system status..." / admin
 every node is a span  ->  Phoenix (6006)          audit tables  ->  PostgreSQL (5440)
```

| Library | Role here | State |
|---|---|---|
| LangGraph / LangChain | the loop: retrieve, generate, verify, rewrite, abstain / human; Postgres checkpointer | running |
| Mem0 | long-term memory per user and shared `org` memory for administrator overrides (local Qdrant + fastembed) | running |
| Phoenix (Arize) | traces of every node, latency, tokens, evals | running at http://127.0.0.1:6006 |
| Rasa | intent gateway for hard rules | **optional**: `AI_USE_RASA=true` once Rasa 3.6 runs on :5005 (needs Python 3.10, see `rasa/`). Until then the built-in rule router (`app/nlu.py`) is used behind the same interface |
| Local LLM | `AI_LLM_MODE=ollama` lets a local Ollama model compose answers from the retrieved text; verified the same way | optional, not installed |

**Default generation is extractive**: the answer is the approved text itself, so nothing can be invented. A model is not needed.

## Run

```powershell
php spark ai:export          # writes the approved answers to C:\NexusAI\knowledge.jsonl (from the NEXUS-L root)
.\ai-service\run-ai.ps1      # PostgreSQL, Phoenix and the service
```

Runtime data lives in `C:\NexusAI` (venv, models, vector stores, Postgres), not on E:. NEXUS-L `.env`: `ai.mode = shadow`, `ai.url`, `ai.key`
(same value as `AI_SERVICE_KEY` in `ai-service/.env`). In **shadow** mode the service sees every question, click, admin reply and thumbs vote and
answers on the side; the user still gets the Brain's answer.

## Confidence and the threshold

`confidence = relevance x ambiguity x groundedness x word-match`, computed by code (not self-reported). Below the active threshold the agent
retries with the page context, then abstains ("Checking system status...") or, when the user has a visible error, hands over to an administrator.
The threshold is **calibrated nightly** (`POST /admin/pipeline/run`): the lowest value that keeps precision >= 98% on the holdout set and refuses
the off-topic questions. `settings_versions` keeps every version and which one is active.

## Endpoints (header `X-AI-Key`)

`GET /health` | `POST /ask` | `POST /ingest/events|admin|feedback` | `GET /admin/corrections?status=` | `POST /admin/corrections/{id}/decide` |
`POST /admin/index` | `POST /admin/pipeline/run` | `GET /admin/eval?split=holdout` | `GET /admin/report`

## Admin corrections

An administrator's reply in a live chat becomes a **pending** correction (never live by itself). Approving it makes it retrievable first and writes an
`org` memory; rejecting a live one retires it (removed from retrieval and memory).

## Known limits

* The evaluation set is built from the answers' own titles/keywords plus typos, dropped words and off-topic questions: a regression and
  calibration set, **not** a measure of real users. Real accuracy comes from `/admin/report` once real conversations exist.
* Short keyword-only questions that fit several answers are the main source of wrong answers.
* LangGraph logs a msgpack warning about `UiState` / `AgentAnswer` in checkpoints (harmless today).
