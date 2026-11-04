"""Lexus keeps learning while nobody is using it: every cycle it re-reads the # folders of the Lab Documents share (new and
changed files, scans and photos with OCR), indexes the new passages, retrains the ranker on fresh generated questions when the
documents changed, and runs the real-question test. Everything is written to E:\\NexusAI\\overnight.log.

    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\lexus_overnight.py --until 07:00 --every 60

The ranker is only replaced when the new one is at least as good (train_lexus.py decides), and the service picks it up without a
restart (POST /admin/labdocs/reload). Stop it any time with Ctrl+C or by closing its window; nothing is left half done.
"""
from __future__ import annotations

import datetime as dt
import json
import re
import subprocess
import sys
import time
from pathlib import Path

import httpx

HERE = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(HERE))
from lab_eval import KEY, URL                                       # noqa: E402

PY = sys.executable
LOG = Path(r"E:\NexusAI\overnight.log")
PASSAGES = Path(r"E:\NexusAI\labdocs\passages.jsonl")
H = {"X-AI-Key": KEY}


def log(msg: str) -> None:
    line = f"{dt.datetime.now():%Y-%m-%d %H:%M:%S}  {msg}"
    print(line, flush=True)
    with LOG.open("a", encoding="utf-8") as f:
        f.write(line + "\n")


def arg(name: str, default: str) -> str:
    return sys.argv[sys.argv.index(name) + 1] if name in sys.argv else default


def step(args: list[str], timeout: int) -> str:
    r = subprocess.run([PY, *args], cwd=HERE, capture_output=True, timeout=timeout, env={**__import__("os").environ, "PYTHONIOENCODING": "utf-8"})
    return (r.stdout.decode("utf-8", errors="ignore") + r.stderr.decode("utf-8", errors="ignore")).strip()


def healthy() -> bool:
    try:
        return httpx.get(f"{URL}/health", timeout=5).status_code == 200
    except httpx.HTTPError:
        return False


def restart_service() -> bool:
    """A fresh service after indexing: the embedded vector store answers several times slower after a large update until restarted.
    The service is stopped and started again with run-ai.ps1 (which starts only what is not running)."""
    ps = "Get-CimInstance Win32_Process -Filter \"Name='python.exe'\" | Where-Object { $_.CommandLine -like '*app.main:app*' } | "          "ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"
    subprocess.run(["powershell", "-NoProfile", "-Command", ps], capture_output=True, timeout=60)
    time.sleep(5)
    subprocess.run(["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-File", str(HERE / "run-ai.ps1")], capture_output=True, timeout=120)
    for _ in range(60):
        time.sleep(5)
        if healthy():
            return True
    return False


def wait_index() -> dict:
    for _ in range(360):
        try:
            st = httpx.get(f"{URL}/admin/labdocs/status", headers=H, timeout=10).json()
            if st.get("state") != "indexing":
                return st
        except httpx.HTTPError:
            pass
        time.sleep(10)
    return {"state": "timeout"}


def cycle(n: int, force_train: bool) -> None:
    before = PASSAGES.stat().st_mtime if PASSAGES.exists() else 0
    out = step(["labdocs_extract.py"], 6 * 3600)
    log(f"cycle {n}: re-read the # folders -> {out.splitlines()[-1] if out else 'no output'}")
    changed = PASSAGES.exists() and PASSAGES.stat().st_mtime != before
    if not healthy():
        log("  the AI service is not answering: skipping indexing, training and the test this cycle")
        return
    if changed:
        httpx.post(f"{URL}/admin/labdocs/index", headers=H, timeout=30)
        st = wait_index()
        log(f"  indexed: {json.dumps({k: st.get(k) for k in ('state', 'passages', 'added', 'removed', 'indexed')})}")
        if st.get("added") or st.get("removed"):
            log(f"  service restarted: {'ok' if restart_service() else 'NOT answering'}")
    weights = PASSAGES.parent / "weights.json"
    backup = weights.read_bytes() if weights.exists() else None
    new_weights = False
    if changed or force_train or n % 2 == 0:
        # every other cycle (and whenever the documents changed) a new batch of generated questions joins the training pool
        out = step(["tools/train_lexus.py", "--save", "--more", "--epochs", "45", "--trials", "40"], 4 * 3600)
        keep = [ln for ln in out.splitlines() if ln.startswith(("generated", "questions", "current", "best", "blend", "chosen", "train", "val", "test", "real", "saved", "not saved"))]
        for ln in keep:
            log("  " + ln.strip())
        if any(ln.startswith("saved") for ln in keep):
            httpx.post(f"{URL}/admin/labdocs/reload", headers=H, timeout=30)
            new_weights = True
            log("  the service now ranks with the new weights")
    passed = evaluate()
    if new_weights and backup is not None and passed < BEST["passed"]:
        weights.write_bytes(backup)                                  # the live test got worse: the earlier ranking comes back
        httpx.post(f"{URL}/admin/labdocs/reload", headers=H, timeout=30)
        log(f"  new weights put back: the live test fell from {BEST['passed']} to {passed}")
    else:
        BEST["passed"] = max(BEST["passed"], passed)


BEST = {"passed": 0}


def evaluate() -> int:
    out = step(["lab_eval.py"], 3600)
    log(f"  real-question test: {out.splitlines()[-1] if out else 'no output'}")
    for ln in out.splitlines():
        if ln.startswith("FAIL"):
            log("    " + ln[:150])
    m = re.search(r"^(\d+)/\d+ passed", out, re.M)
    return int(m.group(1)) if m else 0


def main() -> None:
    until = dt.datetime.combine(dt.date.today(), dt.time.fromisoformat(arg("--until", "07:00")))
    if until <= dt.datetime.now():
        until += dt.timedelta(days=1)
    every = int(arg("--every", "60"))
    log(f"overnight learning started, until {until:%Y-%m-%d %H:%M}, a cycle every {every} min")
    n = 0
    if healthy():
        BEST["passed"] = evaluate()                                  # the score any new ranking has to keep
    while dt.datetime.now() < until:
        n += 1
        t0 = time.time()
        try:
            cycle(n, force_train=(n == 1 and "--train-first" in sys.argv))
        except Exception as e:                                        # one bad cycle must not end the night
            log(f"cycle {n} failed: {type(e).__name__}: {e}")
        log(f"cycle {n} took {(time.time() - t0) / 60:.0f} min")
        nxt = t0 + every * 60
        while time.time() < nxt and dt.datetime.now() < until:
            time.sleep(30)
    log("overnight learning finished")


if __name__ == "__main__":
    main()
