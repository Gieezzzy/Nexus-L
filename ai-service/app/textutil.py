from __future__ import annotations

import re
import unicodedata

_EMAIL = re.compile(r"[\w.+-]+@[\w-]+\.[\w.-]+")
_TOKEN = re.compile(r"\b[A-Za-z0-9_\-]{32,}\b")
_PHONE = re.compile(r"\b\+?\d[\d\s\-]{8,}\d\b")

_STOP = frozenset(
    "a an the is are was were be been am do does did how what why when where which who whom i me my we our you your it its "
    "to of in on at for from with by and or but not no yes can could should would will shall may might this that these those "
    "there here please help need want like tell show about into than then so if as get got have has had just also any some "
    "more most very".split()
)


def redact(text: str) -> str:
    """E-mail addresses, long tokens and phone numbers never reach a store, a memory or a model (same idea as Brain::redact)."""
    return _PHONE.sub("[number]", _TOKEN.sub("[token]", _EMAIL.sub("[email]", text)))


def norm(text: str) -> str:
    text = unicodedata.normalize("NFKC", text).lower()
    return re.sub(r"\s+", " ", re.sub(r"[^\w\s]+", " ", text)).strip()


def stem(w: str) -> str:
    for suf in ("ing", "ed", "es", "s"):
        if len(w) > len(suf) + 3 and w.endswith(suf):
            return w[: -len(suf)]
    return w


def words(text: str) -> list[str]:
    """Content words, lower case, lightly stemmed, without filler words."""
    seen: dict[str, None] = {}
    for w in norm(text).split():
        if w in _STOP or len(w) < 2:
            continue
        seen.setdefault(stem(w), None)
    return list(seen)


def coverage(question_words: list[str], text: str) -> float:
    """Share of the question's content words found in the text (exact stem, shared prefix, or one typo)."""
    if not question_words:
        return 0.0
    tw = set(words(text))
    hit = 0
    for q in question_words:
        if q in tw or any(
            (len(q) >= 4 and len(t) >= 4 and (t.startswith(q) or q.startswith(t)))
            or (len(q) >= 5 and abs(len(q) - len(t)) <= 1 and _lev1(q, t))
            for t in tw
        ):
            hit += 1
    return hit / len(question_words)


def _lev1(a: str, b: str) -> bool:
    """True when the two words differ by at most one insertion, deletion or substitution."""
    if a == b:
        return True
    if abs(len(a) - len(b)) > 1:
        return False
    if len(a) == len(b):                                    # two neighbouring letters swapped counts as one typo
        diff = [i for i in range(len(a)) if a[i] != b[i]]
        if len(diff) == 2 and diff[1] == diff[0] + 1 and a[diff[0]] == b[diff[1]] and a[diff[1]] == b[diff[0]]:
            return True
    i = j = d = 0
    while i < len(a) and j < len(b):
        if a[i] == b[j]:
            i += 1
            j += 1
            continue
        d += 1
        if d > 1:
            return False
        if len(a) > len(b):
            i += 1
        elif len(b) > len(a):
            j += 1
        else:
            i += 1
            j += 1
    return d + (len(a) - i) + (len(b) - j) <= 1


def sentences(text: str) -> list[str]:
    parts = re.split(r"(?<=[.!?])\s+|\n+", text.strip())
    return [p.strip() for p in parts if p and p.strip()]
