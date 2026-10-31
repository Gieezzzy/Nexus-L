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
| Rasa 3.6 | intent gateway for hard rules (open report, talk to admin, greet, thanks, not helpful; everything else = `faq`) | installed in `E:\NexusAI\rasa-venv` (Python 3.10), model trained from `rasa/` (`python rasa/make_faq.py` then `rasa train nlu`). **Opt-in at run time**: `.\run-ai.ps1 -WithRasa`. It needs ~1.6 GB of committed memory and this PC has no page file, so with Rasa running other programs can fail with "bad allocation". If Rasa is not running the service falls back to the built-in rule router (`app/nlu.py`) automatically |
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

## Two brains, the reranker, calibration, training and the Universe

* **Primary brain = this service. Secondary brain = the classic PHP Brain.** With `ai.mode = live` (Settings), every question that is not a built-in skill
  (chart, file, report lookup, calculator) goes to the AI brain first. If it is offline, unsure or abstains, the PHP brain answers; if both are unsure the user
  gets the safe message plus help options. `shadow` = the PHP brain answers and the AI brain only watches; `off` = PHP only.
* **Second stage:** a cross-encoder reranker (`Xenova/ms-marco-MiniLM-L-12-v2`, ~120 MB, CPU) reads the question together with each of the top candidates.
* **Calibrated confidence:** a small logistic regression (numpy) turns hybrid score, reranker score, word coverage and the margin to the runner-up into
  P(correct). The threshold is the lowest one that keeps precision >= 98% on questions the model never saw.
* **Train AI tab (Brain console, admins):** *Train now* re-indexes, extends the test set, fits the calibration on the train split and compares old vs new on the
  holdout split; the new version is activated only if it does at least as well. *Your training task* shows real questions with the three best candidates: pick the
  right one, "none fits" (optionally type the right answer: it goes live), or "not about NEXUS-L". Each decision becomes a labelled case.
* **Universe tab:** every answer is a star in a topic cluster; questions fly to the brain that answered; new knowledge appears live (polls `brain/universe/feed`,
  stored in `tbl_bot_brain_events`).
* `python`-side endpoints: `POST /admin/train/start`, `GET /admin/train/status`, `POST /admin/review/seed`, `GET /admin/review/next`, `POST /admin/review/{id}/label`.
* Rasa has a circuit breaker: when it is not running it is skipped for 60 s (a refused local connection used to cost 2 seconds per message).
