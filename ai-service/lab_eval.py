"""Real-question test for Lexus: lab questions whose answer is in a known document, NEXUS-L how-to questions, and questions
that are not about the lab (which must be refused). Run against the running service:

    C:\\NexusAI\\venv\\Scripts\\python.exe lab_eval.py

A case passes when the answer comes from the expected place (document file name contains `doc`, or an approved answer for the
how-to questions) and, when `has` is given, the answer text contains that value. Off-topic cases pass when Lexus refuses.
"""
from __future__ import annotations

import json
import sys
import time

import httpx

URL, KEY = "http://127.0.0.1:8765", "uqirpkfl7m34yz65s1wghed0t2xnvb9o8jca"
RUN = int(time.time())                                              # fresh conversations on every run

CASES = [
    # ---- test methods (#DTP-Lab-101) ----
    {"q": "What crosshead speed is used for the fabric tensile test?", "doc": "Tensile Fabric", "has": "300"},
    {"q": "What is the jaw gap for tensile testing of fabric?", "doc": "Tensile Fabric", "has": "13.0"},
    {"q": "How is elongation computed in the fabric tensile test?", "doc": "", "has": "elongation"},
    {"q": "How do I test the strength of an eyelet with the shoe lace?", "doc": "B04", "has": "100"},
    {"q": "What is the sample size for laminar peel strength?", "doc": "F04", "has": "2.5"},
    {"q": "How do you test zipper pull off slider resistance?", "doc": "B17"},
    {"q": "What conditioning is needed before the Bata belt flex test?", "doc": "D03", "has": "16"},
    {"q": "What impact energy is used for rebound resiliency of foam?", "doc": "", "has": "0.2"},
    {"q": "What thickness should rubber tensile samples be?", "doc": "Tensile Rubber", "has": "3.0"},
    # ---- material specifications (#DTP-Lab-103) ----
    {"q": "What is the tensile requirement for foxing super grip rubber?", "doc": "Foxing Super Grip", "has": "120"},
    {"q": "What is the die C tear minimum for foxing super grip?", "doc": "Foxing Super Grip", "has": "30"},
    {"q": "What is the hydrolysis requirement for upper synthetic leather?", "doc": "", "has": "Hydrolysis"},
    # ---- quality and control manuals (#DTP-Lab-118, #DTP-Lab-125) ----
    {"q": "Who carries out maintenance and repairs on test equipment?", "doc": "Quality Assurance Manual", "has": "Technician"},
    {"q": "Where are adjustments and repairs done in the laboratory recorded?", "doc": "Quality Assurance Manual", "has": "121"},
    {"q": "How are the laboratory balances checked every day?", "doc": "Control Manual", "has": "100"},
    {"q": "What must a calibration certificate contain?", "doc": "Manual", "has": "calibration"},
    # ---- equipment and maintenance (#DTP-Lab-105, #DTP-Lab-107) ----
    {"q": "What machine is DTP-QC-001?", "doc": "", "has": "DTP-QC-001"},
    {"q": "What is the serial number of the universal tensile testing machine DTP-QC-001?", "doc": "", "has": "2016-01487"},
    {"q": "When was the Ross flex tester calibrated?", "doc": "", "has": "calibrat"},
    {"q": "How do I maintain the cut machine?", "doc": "Maintenance Procedure", "has": "antirust"},
    {"q": "How do I clean the dehumidifier in the bonding room?", "doc": "Maintenance Procedure", "has": "filter"},
    {"q": "How do I maintain the bursting strength machine?", "doc": "Maintenance Procedure", "has": "glycerin"},
    # ---- performance manual (#DTP-Lab-119) ----
    {"q": "Where must pH value testing be done?", "doc": "Performance Testing Manual", "has": "3rd party"},
    # ---- everyday wording ----
    {"q": "speed for fabric tensile?", "doc": "Tensile Fabric", "has": "300"},
    {"q": "how many hours do we condition the samples before bata belt", "doc": "D03", "has": "hours"},
    {"q": "load cell daily check of UTM 1, what is the standard weight?", "doc": "", "has": "5000"},
    {"q": "steps to maintain the rheometer", "doc": "Maintenance Procedure", "has": "lubricat"},
    {"q": "who reviews the maintenance findings?", "doc": "Manual", "has": "Supervisor"},
    # ---- maintenance schedules, cutting dies, reference materials (#DTP-Lab-106, 110, 112) ----
    {"q": "How often is the DIN abrasion tester maintained?", "doc": "Maintenance Schedule", "has": "3 months"},
    {"q": "What is the equipment code of the DIN abrasion tester?", "doc": "", "has": "DTP-QC-004"},
    {"q": "What size is the Martindale abrasion cutting die?", "doc": "", "has": "44"},
    {"q": "What is the cutting die size for Taber abrasion?", "doc": "", "has": "103"},
    {"q": "What is the Bally flex sample size?", "doc": "", "has": "70"},
    {"q": "What density should the Martindale foam be?", "doc": "", "has": "28"},
    # ---- calibration records and checklists (#DTP-Lab-108, 109, 113) ----
    {"q": "What is the serial number of the hydrolysis tester?", "doc": "", "has": "2016-02683"},
    {"q": "What temperature and humidity must the bonding testing room have?", "doc": "", "has": "23"},
    {"q": "What is the caliper accuracy requirement from 0 to 70 mm?", "doc": "", "has": "0.02"},
    # ---- amendment records, environment failures, SOPs, lecture (#DTP-Lab-102, 104, 111, 114, 120) ----
    {"q": "When was the hydrolysis temperature for hardware lowered to 60 C?", "doc": "Amendment", "has": "60"},
    {"q": "What happened when the bonding testing room temperature failed in April 2025?", "doc": "Conditioned Environment Failure", "has": "25.7"},
    {"q": "What reducing ring is used for shoe uppers in the softness test?", "doc": "Softness", "has": "35"},
    {"q": "How long must lace samples be for the tensile test?", "doc": "", "has": "25"},
    {"q": "How far should the rubber specimen protrude in DIN abrasion?", "doc": "", "has": "2 mm"},
    {"q": "What is DTP-Lab-129?", "doc": "", "has": "Incoming Raw Material"},
    {"q": "Which document holds the training records?", "doc": "", "has": "117", "avoid": "WRONG"},
    # ---- typos, Taglish, short follow-ups in one conversation ----
    {"q": "crosshed sped for fabric tensil", "doc": "Tensile Fabric", "has": "300"},
    {"q": "ano ang crosshead speed ng fabric tensile?", "doc": "", "has": "300"},
    {"q": "What crosshead speed is used for the fabric tensile test?", "sid": "fu1", "doc": "", "has": "300"},
    {"q": "and the jaw gap?", "sid": "fu1", "doc": "", "has": "13"},
    # ---- loosely typed: typos, chat words, Taglish ----
    {"q": "fabrik tensle crosshed speed", "doc": "Tensile Fabric", "has": "300"},
    {"q": "pano i maintain ang rheometer", "doc": "Maintenance Procedure", "has": "lubricat"},
    {"q": "wat is the jaw gap 4 fabric tensile pls", "doc": "Tensile Fabric", "has": "13"},
    {"q": "serial no of hydrolysis tester", "doc": "", "has": "2016-02683"},
    # ---- standards: the requirement row, not the whole method; value questions lead with the answering line ----
    {"q": "wha tis the standard for footwear adhesion", "doc": "", "has": "Upper to Midsole"},
    {"q": "what is the standard for eyelets", "sid": "std1", "doc": "", "has": "Eyelet) | VF-B04"},
    {"q": "for adult casual?", "sid": "std1", "doc": "", "has": "Adult Casual 21.0"},
    {"q": "rubber outsole tensile standard", "doc": "", "has": "90"},
    {"q": "What crosshead speed is used for the fabric tensile test? ", "doc": "Tensile Fabric", "has": "► Crosshead speed"},
    # ---- vague questions, then the reply to "which one do you mean?" (a number, or a detail) ----
    {"q": "cutting die size", "sid": "cl1", "doc": ""},
    {"q": "the martindale one", "sid": "cl1", "doc": "", "has": "44"},
    {"q": "abrasion", "sid": "cl2", "clarify": True},
    {"q": "2", "sid": "cl2", "doc": ""},
    # ---- live NEXUS-L data (read from the database) ----
    {"q": "DTP20261019F1-3 results", "db": True, "has": "Hardness"},
    {"q": "how many bonding fails in 2026", "db": True, "has": "Fail:"},
    {"q": "ilan ang pass na hardware ngayong taon", "db": True, "has": "Pass"},
    {"q": "list the failed leather reports", "db": True, "has": "DTP"},
    {"q": "what is the flow of a report in nexus-l", "db": True, "has": "InterLink"},
    # ---- NEXUS-L how-to: approved answers ----
    {"q": "what does tolerable mean", "kb": True},
    {"q": "how do I sign in to InterLink", "kb": True},
    {"q": "how do I print the report in NEXUS-L", "notdoc": True},
    # ---- not about the lab: must refuse ----
    {"q": "who won the world cup in 2022", "refuse": True},
    {"q": "write me a poem about the sea", "refuse": True},
    {"q": "what is the price of bitcoin today", "refuse": True},
    {"q": "how do I cook chicken adobo", "refuse": True},
    {"q": "what will the weather be tomorrow", "refuse": True},
    {"q": "who is the president of the united states", "refuse": True},
]


def main() -> None:
    sys.stdout.reconfigure(encoding="utf-8")
    ok = 0
    rows = []
    with httpx.Client(timeout=60) as c:
        for i, case in enumerate(CASES):
            t0 = time.time()
            sid = f"eval-lab-{case.get('sid', i)}-{RUN}"            # cases sharing a sid are one conversation
            r = c.post(f"{URL}/ask", headers={"X-AI-Key": KEY}, json={"session_id": sid, "user_id": "eval", "message": case["q"]}).json()
            ms = int((time.time() - t0) * 1000)
            text = r.get("text", "")
            doc = (r.get("document") or {}).get("file", "")
            refused = r.get("route") in ("abstain", "human")
            if case.get("refuse"):
                good = refused
            elif case.get("kb"):
                good = not refused and r.get("source") != "documents"
            elif case.get("db"):                                     # answered from the NEXUS-L database
                good = r.get("source") == "database" and case["has"].lower() in text.lower()
            elif case.get("clarify"):                                # Lexus asks which document is meant
                good = bool(r.get("clarify")) or (r.get("source") == "documents" and bool(r.get("options")))
            elif case.get("notdoc"):                                 # answered by an approved answer or left to the PHP brain
                good = r.get("source") != "documents"
            else:
                good = not refused and (case["doc"] == "" or case["doc"].lower() in doc.lower()) and (not case.get("has") or case["has"].lower() in text.lower())                     and not (case.get("avoid") and case["avoid"].lower() in doc.lower())
            ok += good
            src = "database" if r.get("source") == "database" else (doc or (f"kb {r.get('kb_id')}" if r.get("kb_id") else "-"))
            rows.append((good, case["q"], r.get("route"), r.get("confidence"), src, ms))
    for good, q, route, conf, src, ms in rows:
        print(f"{'PASS' if good else 'FAIL'}  {q[:62]:62} {route:8} conf={conf}  {src[:48]}  {ms}ms")
    print(f"\n{ok}/{len(CASES)} passed ({ok / len(CASES) * 100:.0f}%)")
    if "--json" in sys.argv:
        print(json.dumps(rows))


if __name__ == "__main__":
    main()
