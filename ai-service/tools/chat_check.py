"""Plays scripted conversations against the running service and shows whether each follow-up stayed on its topic.

    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\chat_check.py
The history is carried exactly as NEXUS-L carries it (last turns with the answer's kb_id), so this checks the real path.
"""
from __future__ import annotations

import sys
import time
from pathlib import Path

import httpx

ROOT = Path(__file__).resolve().parent.parent
KEY = next((l.split("=", 1)[1].strip() for l in (ROOT / ".env").read_text().splitlines() if l.startswith("AI_SERVICE_KEY=")), "")
URL = "http://127.0.0.1:8765"

# (conversation, [(message, expected: 'answer' | 'same' = same answer as the first turn | 'abstain')])
TALKS = [
    ("casual -> technical", [("my shoe sole is peeling off, how do I test that?", "answer"), ("what is the pass limit?", "same"), ("and the steps?", "same")]),
    ("technical, then why", [("how do I run the VF-A01 hardness test?", "answer"), ("why?", "same"), ("how many readings?", "same")]),
    ("workflow", [("how do I send a report to InterLink with 1 click?", "answer"), ("and then?", "same"), ("what if it fails?", "same")]),
    ("topic switch", [("how do I verify a report with the QR code?", "answer"), ("how do I import the Gotech machine excel?", "answer"), ("where?", "same")]),
    ("off topic", [("what is the capital of France?", "abstain")]),
]


def main() -> int:
    bad = 0
    with httpx.Client(timeout=30, headers={"X-AI-Key": KEY}) as c:
        for name, turns in TALKS:
            print(f"\n== {name}")
            hist: list[dict] = []
            first_kb = None
            sid = f"check-{int(time.time())}-{abs(hash(name)) % 9999}"
            for msg, want in turns:
                r = c.post(f"{URL}/ask", json={"session_id": sid, "user_id": "chat-check", "message": msg, "prev_question": next((h["text"] for h in reversed(hist) if h["sender"] == "user"), ""),
                                              "history": hist[-8:], "ui": {"page": "dashboard", "role": "user"}}).json()
                kb, route, conf = r.get("kb_id"), r.get("route"), r.get("confidence")
                if want == "answer" and first_kb is None and route == "agent":
                    first_kb = kb
                ok = (want == "abstain" and route in ("abstain", "human")) or (want == "answer" and route == "agent") or (want == "same" and route == "agent" and (first_kb is None or kb in (first_kb, None)))
                bad += 0 if ok else 1
                print(f"  {'OK ' if ok else 'BAD'} [{route} kb={kb} conf={conf}] {msg!r} -> {(r.get('text') or '')[:70]!r}   ({'; '.join(x for x in r.get('trail', []) if x.startswith('context'))})")
                hist += [{"sender": "user", "text": msg, "kb_id": None}, {"sender": "bot", "text": r.get("text", ""), "kb_id": kb}]
    print(f"\n{bad} turn(s) need attention")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main())
