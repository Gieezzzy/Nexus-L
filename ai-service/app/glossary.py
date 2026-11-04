"""Direct definitions of the words technicians ask about ("what is a tolerable result", "what does for approval mean", "what is a Lab
Reference"): one short, fixed answer each. They say the same as the approved answers of the PHP brain and invent no limit or number.
"""
from __future__ import annotations

import re
from typing import Any

_ASK = re.compile(r"\b(what('s| is| does| do)|what are|meaning of|mean|define|definition|explain|ano (ang|yung|po ang)|ibig sabihin|difference (between|of)|tell me about)\b", re.I)

TERMS: list[tuple[str, str, str]] = [
    ("difference", r"(pass.*tolerable|tolerable.*pass|pass.*fail|fail.*pass|tolerable.*fail)",
     "Pass: the reading meets the standard. Tolerable: it does not quite meet the standard but is inside the allowed tolerance of the specification. "
     "Fail: it is outside the tolerance. The overall result of a report is the worst result of its tests."),
    ("tolerable", r"\btolerable\b",
     "Tolerable means the reading does not quite meet the standard but is inside the allowed tolerance of the specification (it is not a Fail). "
     "The overall result of a report is the worst of its tests."),
    ("overall", r"\boverall result\b",
     "The overall result of a report is the worst result of any of its tests: one Fail makes the whole report Fail, one Tolerable (and no Fail) makes it Tolerable."),
    ("fail", r"\b(a |the )?fail(ed|ure)?\b.*\b(result|mean)|\bwhat (is|does) (a )?fail\b|\bmeaning of fail\b",
     "Fail means the reading is outside the tolerance of the specification. One failing test makes the whole report Fail; open the report to see which test, then decide on a retest."),
    ("pass", r"\bwhat (is|does) (a )?pass(ed)?\b|\bmeaning of pass\b|\bpass(ed)? (result|mean)",
     "Pass means the reading meets the standard of its specification."),
    ("approval", r"\b(for approval|pending approval|pending signature|awaiting approval|waiting for approval)\b",
     "A report is \"for approval\" when approval was requested and no signatory has signed it yet. The signatories got a Synology Chat message with a sign link; "
     "the report stays pending until one signs or the link is resent. Ask \"what are my for approval reports\" to see them."),
    ("labref", r"\blab(oratory)? ?ref(erence)?( no| number)?\b|\bwhat does dtp mean\b",
     "The Lab Reference (for example DTP20261019F1-3) is the number of one report: DTP, the date it was opened, the report type code and a running number. "
     "It is how you find, open and approve a report."),
    ("method", r"\btest methods?\b|\bvf (test )?methods?\b|\bwhat is vf\b(?![\s-]*[a-j][\s-]*\d)",
     "A test method (VF-A01, VF-B04 ...) is the written procedure for one test: what it measures, the equipment, the steps and the conditions. "
     "Ask \"what is VF-B04\" or \"test for hydrolysis\" to see one."),
    ("signatory", r"\bsignator(y|ies)\b",
     "A signatory is a registered manager or supervisor with signing authority. Only signatories can approve a report; technicians cannot approve their own."),
    ("retest", r"\bretest\b",
     "A retest is a new test of the same material after a Fail or a doubtful result. Open the failed report and start the retest from the Lab Tracker; the new result is kept next to the first."),
    ("tracker", r"\blab tracker\b",
     "The Lab Tracker lists every report with its status and result, so you can find, open and follow a report."),
]

# questions that are not "what is X" but have one fixed answer
DIRECT: list[tuple[str, str, str]] = [
    ("who", r"\bwho (signs|approves|can sign|can approve|gives (the )?final approval|is allowed to sign)\b|\bsino ang (pipirma|mag-?aapruba|nagsa?sign)\b|\bsino (ang )?pipirma\b",
     "Only registered signatories (managers or supervisors with signing authority) can sign a report. Technicians cannot approve their own reports. "
     "Ask \"who has not signed yet\" to see what is waiting."),
    ("start", r"\bwhere (do i|to|should i|can i) (start|begin)\b|\bhow (do i|to|should i) (start|begin)\b.*\b(sample|test|report)\b|\bnew sample\b|\bbagong sample\b",
     "Receive the sample first: log the material (code, supplier, date received) so it gets a Lab Reference, then open the report for its type in QC Lab Reports and enter or import the readings."),
    ("status", r"\b(how do i know|how can i tell|how to know|paano malalaman).*\b(report|it).*\b(done|finished|complete|completed|ready)\b|\bis my report (done|finished|complete|ready)\b|\bstatus of my report\b",
     "Look at the Status of the report in the Lab Tracker: In Progress means readings are still being entered, Pending Review means it waits for checking, "
     "Complete means it is finished. For signing, ask \"what are my for approval reports\"."),
]


def answer(question: str) -> dict[str, Any] | None:
    q = question.lower()
    if len(q.split()) <= 14:
        for name, pat, text in DIRECT:
            if re.search(pat, q):
                return {"text": text, "kind": name}
    if not _ASK.search(q) or len(q.split()) > 14:
        return None
    if re.search(r"\b(how (do|to|can)|paano|where|who has|sino|list|show|how many|count|resend|remind|which reports|my )\b", q) and not re.search(r"difference", q):
        return None
    for name, pat, text in TERMS:
        if re.search(pat, q):
            return {"text": text, "kind": name}
    return None
