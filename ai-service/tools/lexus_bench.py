"""Large prompt benchmark for both brains: ~1,000+ generated prompts with answers that can be checked.

    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\lexus_bench.py            generate, run on the AI service, run the PHP checks, report
    C:\\NexusAI\\venv\\Scripts\\python.exe tools\\lexus_bench.py --n 300    only the first 300 prompts of each group (a quick run)

Groups (every prompt is written four ways: clean, sloppy with a typo, Taglish, short):
  methods     every VF test method: "what is VF-B04", "describe the <title> test"            -> the answer names the code
  database    pass / tolerable / fail x report type x period, as lists and counts           -> the answer holds the true count from the database
  standards   "what is the standard for <requirement row>"                                  -> the answer holds the row's first value
  flow        the system flow, what happens next, who signs, plain words                    -> the answer holds the expected words, no refusal
  approvals   waiting-for-approval lists and follow-ups                                     -> read from the database, never claims to have sent
  offtopic    questions that are not about the lab                                          -> refused
PHP brain: the approval conversation (bot:intents) and the knowledge search (brain:ask) on the same kind of prompts.
Truth for the database group is computed here with its own SQL, not with the skill under test.
"""
from __future__ import annotations

import json
import random
import re
import subprocess
import sys
import time
from pathlib import Path

import httpx

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from app import dbskill, methods, requirements                    # noqa: E402
from lab_eval import KEY, URL                                      # noqa: E402

sys.stdout.reconfigure(encoding="utf-8")
PHP = r"C:\xampp\php\php.exe"
ROOT = Path(__file__).resolve().parents[2]
WORK = Path(r"E:\NexusAI\labdocs\train")
RUN = int(time.time())
rnd = random.Random(20261104)


def typo(s: str) -> str:
    ws = s.split()
    cand = [i for i, w in enumerate(ws) if len(w) >= 6 and w.isalpha()]
    if not cand:
        return s
    i = rnd.choice(cand)
    w = ws[i]
    j = rnd.randrange(1, len(w) - 2)
    ws[i] = w[:j] + w[j + 1] + w[j] + w[j + 2:]
    return " ".join(ws)


def taglish(s: str) -> str:
    return rnd.choice(["pakisabi po ", "ano po ", "paano po ", "pa-check po ", "sir ", ""]) + s


def forms(clean: str) -> list[tuple[str, str]]:
    return [("clean", clean), ("sloppy", typo(clean.lower())), ("taglish", taglish(clean.lower())), ("short", " ".join(clean.split()[-4:]))]


# ------------------------------------------------------------------------------------------------ the groups

def g_methods() -> list[dict]:
    out = []
    for m in methods._methods():
        code = methods.norm(m["code"])
        for style, q in forms(f"what is {code}")[:3]:
            out.append({"g": "methods", "style": style, "q": q, "must": [code]})
        out.append({"g": "methods", "style": "title", "q": f"describe the {m['title']} test", "must_any": [code, m["title"]]})
    return out


def g_database() -> list[dict]:
    out = []
    cats = [("fabric", "Fabric%"), ("leather", "Leather%"), ("bonding", "Whole Shoe%"), ("hardware", "Hardware%"), ("rubber", "Rubber%"), ("webbing", "Webbing%"), ("foxing tape", "Foxing%")]
    res = {"pass": "('Pass','P','PASS','Passed')", "tolerable": "overall_result LIKE 'Tolerable%'", "fail": "('Fail','F','FAIL','Failed')"}
    periods = [("", None), ("this year", ("2026-01-01", "2026-12-31")), ("in october 2026", ("2026-10-01", "2026-10-31")), ("in 2026", ("2026-01-01", "2026-12-31"))]
    date = ("COALESCE(STR_TO_DATE(NULLIF(finished_date,''),'%d %b %Y'), STR_TO_DATE(NULLIF(tested_date,''),'%d %b %Y'), STR_TO_DATE(NULLIF(received_date,''),'%d %b %Y'))")
    for rname, rsql in res.items():
        for cname, csql in cats + [("", None)]:
            for pname, prange in periods:
                where = ["lab_ref_no LIKE 'DT%'"]
                where.append(rsql if rsql.startswith("overall") else f"overall_result IN {rsql}")
                if csql:
                    where.append(f"form_category LIKE '{csql}'")
                if prange:
                    where.append(f"{date} BETWEEN '{prange[0]}' AND '{prange[1]}'")
                n = int(dbskill.run(f"SELECT COUNT(*) AS n FROM tbl_dtr_unified_labrefs WHERE {' AND '.join(where)}")[0]["n"])
                scope = " ".join(x for x in (cname, pname) if x)
                verb = {"pass": "passed", "tolerable": "tolerable", "fail": "failed"}[rname]
                for style, q in (("count", f"how many {verb} {scope} reports".replace("  ", " ")), ("list", f"show all {verb} {scope} reports".replace("  ", " ")),
                                 ("sloppy", typo(f"give me all {verb} {scope}".replace("  ", " ")))):
                    out.append({"g": "database", "style": style, "q": q, "n": n, "count": style == "count"})
    return out


def g_standards() -> list[dict]:
    out, seen = [], set()
    for r in requirements.table():
        if not r["levels"] or "Manual 2026" not in r["file"] or not r["method"]:
            continue
        label = re.sub(r"\s+", " ", r["label"])
        words = [w for w in re.findall(r"[A-Za-z]{4,}", label) if w.lower() not in ("strength", "material", "test", "requirement", "all", "parts", "thickness", "adult", "price", "value", "casual")]
        if len(words) < 2 or label.lower() in seen or len(label) > 60:
            continue
        seen.add(label.lower())
        out.append({"g": "standards", "style": "clean", "q": f"what is the standard for {' '.join(words[:3])}", "must_any": [v for v in r["values"][:6]], "row": label})
        if len(out) >= 120:
            break
    return out


FLOW = [
    ("how does a sample go through the lab", ["Lab Reference", "approval"]), ("what is the flow of a report in nexus-l", ["InterLink", "approval"]),
    ("what happens after the test is finished", ["approval"]), ("where do i start with a new sample", ["Lab Reference", "log"]),
    ("how do i know if my report is done", ["Complete"]), ("what does tolerable mean", ["tolerance"]), ("what is a tolerable result", ["tolerance"]),
    ("why is my report fail when most tests passed", ["worst"]), ("what does for approval mean", ["signed"]), ("who signs the report", ["signator"]),
    ("who has not signed yet", ["approval"]), ("how do i resend the approval for a report", ["resend", "Synology"]), ("how do i remind the manager to sign", ["Synology"]),
    ("can i send a message to synology chat", ["Synology"]), ("can you email a report", ["does not send e-mail", "does not send email"]), ("can i email the report to the supplier", ["does not send e-mail", "does not send email"]),
    ("my approval link expired", ["resend", "fresh link"]), ("is my report approved yet", ["approved"]), ("how do i generate a report", ["Generate Report", "PDF"]),
    ("how do i send the report to the customer", ["InterLink"]), ("how do i get a list of all passed reports", ["all", "list"]), ("how many reports failed this month", ["Fail"]),
    ("what is a lab reference", ["DTP"]), ("what is a test method", ["VF"]), ("which test do i use for a peeling sole", ["VF-F"]), ("how do i look up one report", ["DTP"]),
    ("ano ang flow ng report sa system", ["InterLink"]), ("paano dumadaan ang sample sa lab", ["Lab Reference", "approval"]), ("sino ang pipirma sa report", ["signator"]),
    ("how do i see the reports waiting for approval", ["for approval", "pending"]), ("what is the difference between pass and tolerable", ["tolerance"]),
    ("who sends the report to interlink", ["PIC"]), ("what do i do when a report fails", ["retest", "Fail"]), ("how do i export a report to pdf", ["PDF"]),
]
FLOW_KB = {"how does a sample go through the lab": "How does a sample go through", "what happens after the test is finished": "What happens after the test",
           "where do i start with a new sample": "Where do I start with a new sample", "how do i know if my report is done": "How do I know if my report is done",
           "what does tolerable mean": "Pass, Tolerable and Fail", "what is a tolerable result": "Pass, Tolerable and Fail", "what is the difference between pass and tolerable": "Pass, Tolerable and Fail",
           "why is my report fail when most tests passed": "Why is my report Fail", "what does for approval mean": "for approval", "who signs the report": "Who signs the report",
           "how do i resend the approval for a report": "How do I resend the approval", "how do i remind the manager to sign": "How do I remind the manager",
           "can i send a message to synology chat": "message to Synology", "can you email a report": "mail", "can i email the report to the supplier": "mail",
           "my approval link expired": "approval link expired", "is my report approved yet": "Is my report approved yet", "how do i generate a report": "How do I generate a report",
           "how do i send the report to the customer": "send the report to the customer", "how do i get a list of all passed reports": "list of all passed",
           "what is a lab reference": "What is a Lab Reference", "what is a test method": "What is a test method", "which test do i use for a peeling sole": "Which test do I use",
           "how do i look up one report": "look up one report", "how do i see the reports waiting for approval": "reports waiting for approval"}
OFF = ["who won the world cup in 2022", "write me a poem about the sea", "what is the price of bitcoin today", "how do i cook chicken adobo", "what will the weather be tomorrow",
       "who is the president of the united states", "tell me a joke", "translate hello to french", "what is the capital of japan", "recommend a movie"]
APPR = ["what are my for approval reports", "which reports are pending approval", "how many reports are waiting for signature", "who has not signed yet", "show approved reports",
        "list unsigned reports", "reports waiting for my approval", "how many pending approvals are there", "show reports with expired approval links", "what is waiting for signature"]


def g_text() -> list[dict]:
    out = []
    for q, must in FLOW:
        for style, v in forms(q):
            out.append({"g": "flow", "style": style, "q": v, "must_any": must, "kb": FLOW_KB.get(q)})
    for q in OFF:
        for style, v in forms(q)[:3]:
            out.append({"g": "offtopic", "style": style, "q": v})
    for q in APPR:
        for style, v in forms(q)[:3]:
            out.append({"g": "approvals", "style": style, "q": v})
    return out


# ------------------------------------------------------------------------------------------------ run and check

def check(c: dict, r: dict) -> bool:
    text = r.get("text", "")
    refused = r.get("route") in ("abstain", "human")
    g = c["g"]
    if g == "offtopic":
        return refused
    if refused:
        return False
    if g == "approvals":
        return r.get("source") == "database" and re.search(r"approval|signature|approved|rejected|signator", text, re.I) is not None
    if g == "database":
        if r.get("source") != "database":
            return False
        n = c["n"]
        if c["count"]:
            return f"{n:,}" in text or (n == 0 and "no reports match" in text.lower())
        return (n == 0 and "no reports match" in text.lower()) or f"of {n:,}" in text or (0 < n <= 40 and len(re.findall(r"^\d+\. DTP", text, re.M)) == n)
    if "must" in c:
        return all(m.lower() in text.lower() for m in c["must"])
    if "must_any" in c:
        return any(str(m).lower() in text.lower() for m in c["must_any"])
    return True


def run_ai(cases: list[dict]) -> list[dict]:
    t0 = time.time()
    with httpx.Client(timeout=120) as cl:
        for i, c in enumerate(cases, 1):
            for _ in range(3):
                try:
                    r = cl.post(f"{URL}/ask", headers={"X-AI-Key": KEY}, json={"session_id": f"bench-{RUN}-{i}", "user_id": "eval", "message": c["q"]}).json()
                    break
                except httpx.HTTPError:
                    time.sleep(20)
            else:
                r = {}
            c["ok"], c["ms"], c["route"], c["src"] = check(c, r), int(r.get("latency_ms") or 0), r.get("route"), r.get("source")
            c["text"] = (r.get("text") or "")[:160]
            if i % 100 == 0:
                print(f"  {i}/{len(cases)} prompts, {time.time() - t0:.0f}s, running accuracy {sum(x.get('ok', False) for x in cases[:i]) / i:.1%}", flush=True)
    return cases


def run_php(cases: list[dict]) -> dict:
    """The PHP brain: knowledge search (brain:ask) for methods and flow, and the approval conversation (bot:intents)."""
    out = {}
    kb = [f"{c['q']}\t{c['must'][0]}" for c in cases if c["g"] == "methods" and "must" in c]
    p = WORK / "php_kb.tsv"
    p.write_text("\n".join(kb), encoding="utf-8")
    r = subprocess.run([PHP, "spark", "brain:ask", str(p)], cwd=ROOT, capture_output=True, timeout=1800)
    m = re.search(r"Accuracy: (\d+)/(\d+)", r.stdout.decode("utf-8", errors="ignore"))
    out["knowledge search: methods"] = (int(m.group(1)), int(m.group(2))) if m else (0, 0)
    fl = [c for c in cases if c["g"] == "flow" and c.get("kb") and c["style"] != "short"]
    p2 = WORK / "php_flow.tsv"
    p2.write_text(chr(10).join(f"{c['q']}{chr(9)}{c['kb']}" for c in fl), encoding="utf-8")
    r = subprocess.run([PHP, "spark", "brain:ask", str(p2)], cwd=ROOT, capture_output=True, timeout=1800)
    txt = re.sub(chr(27) + r"\[[0-9;]*m", "", r.stdout.decode("utf-8", errors="ignore"))
    good = {m.group(2).strip() for m in re.finditer(r"^(OK|MISS)\s+\d+%\s+(.*?)\s+->", txt, re.M) if m.group(1) == "OK"}
    for c in fl:
        c["php_ok"] = c["q"] in good
    m = re.search(r"Accuracy: (\d+)/(\d+)", txt)
    out["knowledge search: flow prompts"] = (int(m.group(1)), int(m.group(2))) if m else (0, 0)
    out["BOTH brains together: flow prompts"] = (sum(c["ok"] or c.get("php_ok", False) for c in fl), len(fl))
    lines = []
    L2 = r"1. DTP20261019F1-3 - A\n2. DTP20261019F1-4 - B"
    for t, want in [("resend the verification for this report", "ASK"), ("re send the verification for this report", "ASK"), ("resend #2", "LAB:DTP20261019F1-4"),
                    ("resend approval for the first one", "LAB:DTP20261019F1-3"), ("remind them about the second one", "LAB:DTP20261019F1-4"),
                    ("resend approval for DTP20261027FAA7-R", "LAB:DTP20261027FAA7-R"), ("send the approval again for the last report", "LAB:DTP20261019F1-4"),
                    ("follow up the approval for number 1", "LAB:DTP20261019F1-3"), ("ipaalala mo yung una", "LAB:DTP20261019F1-3")]:
        lines.append(f"resolve\t{t}\t{L2}\t{want}")
    for t, want in [("resend the verification for this report", "LAB:DTP20261019F1-3"), ("remind the manager about it", "LAB:DTP20261019F1-3")]:
        lines.append(f"resolve\t{t}\t1. DTP20261019F1-3 - A\t{want}")
    for t in ["how do i send this report to the manager", "how to resend the approval", "paano mag resend ng approval", "what is the procedure to remind the approver", "can i resend an approval link"]:
        lines.append(f"howto\t{t}\t\tyes")
    for t in ["resend approval for this report", "re send the verification for this report", "remind the manager to sign", "send the approval again"]:
        lines.append(f"howto\t{t}\t\tno")
    for t in ["can you email this report to the supplier", "i want to send an email of the report", "email the approval follow up", "send the pdf by gmail to the customer"]:
        lines.append(f"email\t{t}\t\tyes")
    for t in ["how many reports failed", "show all tolerable reports", "resend approval for this report"]:
        lines.append(f"email\t{t}\t\tno")
    for t, want in [("what are my for approval reports", "approval"), ("which reports are pending approval", "approval"), ("how many reports are waiting for signature", "report"),
                    ("who has not signed yet", "approval"), ("show approved reports", "approved"), ("list unsigned reports", "approval"), ("reports waiting for my approval", "approval"),
                    ("show all failed reports", "none"), ("how do i send this report to the manager", "none"), ("resend approval for this report", "none"), ("how many reports failed this month", "none")]:
        lines.append(f"list\t{t}\t\t{want}")
    q = WORK / "php_intents.tsv"
    q.write_text("\n".join(lines), encoding="utf-8")
    r = subprocess.run([PHP, "spark", "bot:intents", str(q)], cwd=ROOT, capture_output=True, timeout=600)
    txt = r.stdout.decode("utf-8", errors="ignore")
    for kind in ("list", "resolve", "howto", "email"):
        m = re.search(rf"^{kind}\s+(\d+) / (\d+)", txt, re.M)
        out[f"approval conversation: {kind}"] = (int(m.group(1)), int(m.group(2))) if m else (0, 0)
    out["_misses"] = re.findall(r"^MISS .*$", re.sub(r"\x1b\[[0-9;]*m", "", txt), re.M)
    return out


def main() -> None:
    WORK.mkdir(parents=True, exist_ok=True)
    cases = g_methods() + g_database() + g_standards() + g_text()
    if "--n" in sys.argv:
        k = int(sys.argv[sys.argv.index("--n") + 1])
        by: dict[str, list] = {}
        for c in cases:
            by.setdefault(c["g"], []).append(c)
        cases = [c for v in by.values() for c in v[:k]]
    rnd.shuffle(cases)
    print(f"{len(cases)} prompts: " + ", ".join(f"{g} {sum(c['g'] == g for c in cases)}" for g in sorted({c['g'] for c in cases})), flush=True)
    run_ai(cases)
    php = run_php(cases)
    (WORK / "bench_last.json").write_text(json.dumps({"cases": cases, "php": php}, ensure_ascii=False, indent=1), encoding="utf-8")
    print("\nAI brain (accuracy per group, median ms):")
    for g in sorted({c["g"] for c in cases}):
        cs = [c for c in cases if c["g"] == g]
        ms = sorted(c["ms"] for c in cs)
        print(f"  {g:10} {sum(c['ok'] for c in cs):4}/{len(cs):<4} = {sum(c['ok'] for c in cs) / len(cs):6.1%}   median {ms[len(ms) // 2]} ms")
    print(f"  {'ALL':10} {sum(c['ok'] for c in cases):4}/{len(cases):<4} = {sum(c['ok'] for c in cases) / len(cases):6.1%}")
    print("\nAI brain by wording:")
    for st in ("clean", "count", "list", "sloppy", "taglish", "short", "title"):
        cs = [c for c in cases if c["style"] == st]
        if cs:
            print(f"  {st:8} {sum(c['ok'] for c in cs):4}/{len(cs):<4} = {sum(c['ok'] for c in cs) / len(cs):6.1%}")
    print("\nPHP brain:")
    for k, v in php.items():
        if not k.startswith("_"):
            print(f"  {k:34} {v[0]:4}/{v[1]:<4} = {v[0] / max(1, v[1]):6.1%}")
    for m in php.get("_misses", [])[:12]:
        print("   ", m)
    print("\nAI misses (first 25):")
    for c in [c for c in cases if not c["ok"]][:25]:
        print(f"  [{c['g']}/{c['style']}] {c['q'][:70]}  -> {c['route']} {c['src']} | {c['text'][:70].replace(chr(10), ' ')}")


if __name__ == "__main__":
    main()
