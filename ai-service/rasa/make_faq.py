"""Writes data/faq.yml: example questions for the 'faq' intent (everything that is NOT a rule), taken from the approved answers' titles.
Without it Rasa tends to force a how-to question into the nearest rule intent. Run before `rasa train nlu`."""
import json
import random
from pathlib import Path

rows = [json.loads(line) for line in open(r"C:\NexusAI\knowledge.jsonl", encoding="utf-8")]
random.Random(3).shuffle(rows)
titles = [r["title"].replace("\n", " ").replace(":", " -") for r in rows if len(r["title"]) < 110][:220]
out = ['version: "3.1"', "nlu:", "  - intent: faq", "    examples: |"]
out += ["      - " + t.replace("[", "(").replace("]", ")") for t in titles]
Path(__file__).parent.joinpath("data", "faq.yml").write_text("\n".join(out) + "\n", encoding="utf-8")
print(len(titles), "faq examples")
