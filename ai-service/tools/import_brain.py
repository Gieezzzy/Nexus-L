"""Installs an exported LEXUS brain (nexus_ai_brain_vNN.model + nexus_ai_brain_tensorflow.tf) into this NEXUS-L.

    cd ai-service
    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\import_brain.py --model E:\\nexus_ai_brain_v43.model --tf E:\\nexus_ai_brain_tensorflow.tf            # dry run: shows what would change
    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\import_brain.py --model ... --tf ... --apply                                                     # does it

What it installs (each part is skipped when it is already identical, and anything it replaces is copied to C:\\NexusAI\\import-backup first):
  * knowledge_base.jsonl   -> C:\\NexusAI\\knowledge.jsonl           (the approved answers; the service re-indexes on the next start / POST /admin/index)
  * eval_cases.jsonl       -> table eval_cases                      (cases that are not there yet; the holdout split is kept as exported)
  * active_version_info    -> table settings_versions               (threshold + fitted calibration + metrics, new version, activated)
  * embeddings_onnx/, reranker_cross_encoder/ -> C:\\NexusAI\\models (only when the folder is missing)
  * the TensorFlow DIET model (a Rasa NLU model) -> ai-service\\brain\\ and E:\\NexusAI\\rasa-models (newest model wins when Rasa starts)
Nothing is downloaded and nothing leaves this computer.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import shutil
import sys
import tarfile
import time
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from app import db  # noqa: E402
from app.config import settings  # noqa: E402

RASA_MODELS = Path(r"E:\NexusAI\rasa-models")


def sha(b: bytes) -> str:
    return hashlib.sha1(b).hexdigest()


def say(ok: bool, msg: str) -> None:
    print(("  [ok]   " if ok else "  [todo] ") + msg)


def backup(path: Path, apply: bool) -> None:
    if apply and path.exists():
        dst = settings.home / "import-backup" / time.strftime("%Y%m%d-%H%M%S")
        dst.mkdir(parents=True, exist_ok=True)
        shutil.copy2(path, dst / path.name)


def do_knowledge(z: zipfile.ZipFile, apply: bool) -> None:
    new = z.read("knowledge_base.jsonl")
    cur = settings.knowledge_file
    same = cur.exists() and [json.loads(x) for x in cur.read_bytes().decode("utf-8").splitlines() if x.strip()] == [json.loads(x) for x in new.decode("utf-8").splitlines() if x.strip()]
    n = len([x for x in new.decode("utf-8").splitlines() if x.strip()])
    say(same, f"knowledge: {n} approved answers" + (" (already installed)" if same else f" -> {cur}"))
    if not same and apply:
        backup(cur, apply)
        cur.write_bytes(new if new.endswith(b"\n") else new + b"\n")
        (cur.parent / "knowledge.sha1").write_text(sha(cur.read_bytes()))


def do_eval(z: zipfile.ZipFile, apply: bool) -> None:
    rows = [json.loads(x) for x in z.read("eval_cases.jsonl").decode("utf-8").splitlines() if x.strip()]
    have = {r["question"] for r in db.query("SELECT question FROM eval_cases")}
    missing = [r for r in rows if r["question"] not in have]
    say(not missing, f"eval cases: {len(rows)} in the package, {len(missing)} not in the database yet")
    if missing and apply:
        for r in missing:
            db.run("INSERT INTO eval_cases (question, expected_kb, expect_abstain, origin, split) VALUES (%s,%s,%s,%s,%s) ON CONFLICT (question) DO NOTHING",
                   (r["question"], r.get("expected_kb"), bool(r.get("expect_abstain")), r.get("origin", "title"), r.get("split", "train")))


def do_version(z: zipfile.ZipFile, apply: bool) -> None:
    v = json.loads(z.read("manifest.json"))["active_version_info"]
    cur = db.one("SELECT version, threshold, calib FROM settings_versions WHERE active") or {}
    same = bool(cur) and abs(float(cur.get("threshold") or 0) - float(v["threshold"])) < 1e-6 and (cur.get("calib") or {}).get("w") == (v.get("calib") or {}).get("w")
    say(same, f"calibration: package v{v['version']} threshold {v['threshold']} precision {v['holdout_precision']:.4f} recall {v['holdout_recall']:.4f}"
              + (" (already active)" if same else f" (active now: v{cur.get('version')} threshold {cur.get('threshold')})"))
    if not same and apply:
        with db.conn() as c:
            c.execute("UPDATE settings_versions SET active = false WHERE active")
            c.execute("INSERT INTO settings_versions (threshold, holdout_cases, holdout_precision, holdout_abstain, holdout_recall, note, active, calib, rerank, metrics) "
                      "VALUES (%s,%s,%s,%s,%s,%s,true,%s::jsonb,%s,%s::jsonb)",
                      (v["threshold"], v["holdout_cases"], v["holdout_precision"], v["holdout_abstain"], v["holdout_recall"],
                       f"imported from LEXUS brain export v{v['version']}", db.j(v.get("calib")), bool(v.get("rerank")), db.j(v.get("metrics"))))
        db.drop_settings_cache()


def do_models(z: zipfile.ZipFile, apply: bool) -> None:
    for folder, target in (("embeddings_onnx", "models--Qdrant--bge-small-en-v1.5-onnx-Q"), ("reranker_cross_encoder", "models--Xenova--ms-marco-MiniLM-L-6-v2")):
        dst = settings.home / "models" / target
        names = [n for n in z.namelist() if n.startswith(folder + "/") and not n.endswith("/")]
        say(dst.exists(), f"{folder}: {'present in ' + str(dst) if dst.exists() else 'missing, will be extracted to ' + str(dst)}")
        if not dst.exists() and apply:
            for n in names:
                out = dst / n[len(folder) + 1:]
                out.parent.mkdir(parents=True, exist_ok=True)
                out.write_bytes(z.read(n))


def do_tf(tf: Path, apply: bool) -> None:
    with tarfile.open(tf, "r:gz") as t:
        meta = json.loads(t.extractfile("metadata.json").read())          # type: ignore[union-attr]
        names = t.getnames()
    ok = any(n.endswith("DIETClassifier.tf_model.index") for n in names) and "rasa_open_source_version" in meta
    say(ok, f"TensorFlow model: Rasa {meta.get('rasa_open_source_version')} NLU, trained {meta.get('trained_at')}, {len(names)} files")
    if not ok:
        raise SystemExit("the .tf file is not a Rasa NLU model")
    keep = ROOT / "brain" / "nexus_ai_brain_tensorflow.tf"
    sink = RASA_MODELS / "nlu-lexus-v43.tar.gz"
    say(sink.exists() and sink.stat().st_size == tf.stat().st_size, f"Rasa model folder: {sink}")
    if apply:
        keep.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(tf, keep)
        if RASA_MODELS.exists():
            shutil.copy2(tf, sink)            # a Rasa model is a .tar.gz; run-ai.ps1 starts the newest one


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--model", required=True, type=Path)
    ap.add_argument("--tf", required=True, type=Path)
    ap.add_argument("--apply", action="store_true")
    a = ap.parse_args()
    print(("APPLYING" if a.apply else "DRY RUN (add --apply to install)") + f" - {a.model.name}, {a.tf.name}")
    db.migrate()
    with zipfile.ZipFile(a.model) as z:
        man = json.loads(z.read("manifest.json"))
        print(f"{man['model_name']} {man['version']}")
        do_knowledge(z, a.apply)
        do_eval(z, a.apply)
        do_version(z, a.apply)
        do_models(z, a.apply)
    do_tf(a.tf, a.apply)
    if a.apply:
        print("\nDone. Restart the service (or POST /admin/index?force=true) so the answers are re-embedded.")


if __name__ == "__main__":
    main()
