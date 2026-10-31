-- NEXUS-L assistant: audit store. Vectors live in Qdrant (local, on disk), everything that must be auditable lives here.
-- Idempotent: safe to run on every start.

CREATE TABLE IF NOT EXISTS sessions (
  session_id   text PRIMARY KEY,
  user_id      text NOT NULL,                       -- a hash of the NEXUS-L user id, never a name
  role         text NOT NULL DEFAULT 'user' CHECK (role IN ('user','admin')),
  started_at   timestamptz NOT NULL DEFAULT now(),
  ended_at     timestamptz
);

-- clickstream / UI state: the biggest table, partitioned by month (rows older than 90 days are dropped by the nightly job)
CREATE TABLE IF NOT EXISTS telemetry_events (
  event_id     bigint GENERATED ALWAYS AS IDENTITY,
  session_id   text NOT NULL,
  occurred_at  timestamptz NOT NULL DEFAULT now(),
  event_type   text NOT NULL,                       -- view | click | error | http_error | slow | action
  page         text,
  target       text,
  detail       text,                                -- redacted before insert
  PRIMARY KEY (event_id, occurred_at)
) PARTITION BY RANGE (occurred_at);
CREATE TABLE IF NOT EXISTS telemetry_events_default PARTITION OF telemetry_events DEFAULT;
CREATE INDEX IF NOT EXISTS telemetry_session_idx ON telemetry_events (session_id, occurred_at DESC);

CREATE TABLE IF NOT EXISTS messages (
  message_id   bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  session_id   text NOT NULL,
  sender       text NOT NULL CHECK (sender IN ('user','bot','admin','system')),
  body         text NOT NULL,                       -- redacted
  trace_id     text,                                -- the Phoenix trace of this answer
  route        text,                                -- rule | agent | abstain | human
  confidence   real,
  kb_id        integer,                             -- the NEXUS-L knowledge entry answered with
  feedback     smallint NOT NULL DEFAULT 0,         -- -1 / 0 / 1
  created_at   timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS messages_session_idx ON messages (session_id, created_at);

-- every answer the orchestrator produced, shown or not (shadow mode keeps the live answer next to it)
CREATE TABLE IF NOT EXISTS answer_log (
  answer_id    bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  session_id   text NOT NULL,
  question     text NOT NULL,
  route        text NOT NULL,
  confidence   real NOT NULL,
  threshold    real NOT NULL,
  kb_id        integer,
  attempts     smallint NOT NULL DEFAULT 1,
  trail        jsonb,                               -- the audit trail of the loop (nlu, memory, retrieve, verify ...)
  live_kb_id   integer,                             -- shadow mode: what the production bot answered
  live_conf    real,
  trace_id     text,
  latency_ms   integer,
  created_at   timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS admin_interventions (
  intervention_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  session_id      text NOT NULL,
  admin_id        text NOT NULL,
  trigger         text NOT NULL DEFAULT 'admin_joined' CHECK (trigger IN ('user_escalated','low_confidence','abstain','admin_joined')),
  opened_at       timestamptz NOT NULL DEFAULT now(),
  closed_at       timestamptz,
  resolution      text
);

-- the gold dataset: what the bot said versus what an administrator says is right
CREATE TABLE IF NOT EXISTS admin_corrections (
  correction_id   bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  intervention_id bigint REFERENCES admin_interventions,
  source_message  bigint REFERENCES messages(message_id),
  question        text NOT NULL,
  bot_answer      text,
  correct_answer  text NOT NULL,
  ui_context      jsonb,
  error_type      text NOT NULL DEFAULT 'missing' CHECK (error_type IN ('wrong','incomplete','unsafe','missing','tone')),
  status          text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
  approved_by     text,
  approved_at     timestamptz,
  indexed_at      timestamptz,                      -- set by the nightly pipeline when it is in Qdrant and Mem0
  created_at      timestamptz NOT NULL DEFAULT now(),
  UNIQUE (question, correct_answer)
);

CREATE TABLE IF NOT EXISTS eval_cases (
  case_id        bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  question       text NOT NULL UNIQUE,
  expected_kb    integer,                           -- NULL together with expect_abstain = true: the bot must NOT answer
  expect_abstain boolean NOT NULL DEFAULT false,
  origin         text NOT NULL,                     -- title | keywords | typo | dropped | negative | correction | thumbs_down
  split          text NOT NULL CHECK (split IN ('train','holdout')),   -- fixed by a hash of the question; tuning never sees 'holdout'
  created_at     timestamptz NOT NULL DEFAULT now()
);

-- what the nightly gate decided: the threshold (and prompt, in LLM mode) that is active
CREATE TABLE IF NOT EXISTS settings_versions (
  version            integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  threshold          real NOT NULL,
  system_prompt      text,
  holdout_cases      integer NOT NULL DEFAULT 0,
  holdout_precision  real,
  holdout_abstain    real,
  holdout_recall     real,
  note               text,
  active             boolean NOT NULL DEFAULT false,
  created_at         timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS settings_one_active ON settings_versions (active) WHERE active;

-- bounded per-answer ranking boosts learned from thumbs-up / thumbs-down (same idea as NEXUS-L's tbl_bot_weights)
CREATE TABLE IF NOT EXISTS doc_boosts (
  kb_id      integer PRIMARY KEY,
  boost      real NOT NULL DEFAULT 0 CHECK (boost BETWEEN -0.1 AND 0.1),
  votes      integer NOT NULL DEFAULT 0,
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS pipeline_runs (
  run_id     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  started_at timestamptz NOT NULL DEFAULT now(),
  finished_at timestamptz,
  summary    jsonb
);
