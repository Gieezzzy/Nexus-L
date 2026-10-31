"""One-off: removes the backdrop-filter (live blur of everything behind an element) from surfaces that are repeated or that scroll:
every .glass card / sidebar in dash.css, and the chat bubbles of every inbox theme. A blur that re-renders on each scroll frame is the
usual cause of scroll lag, most of all on integrated graphics. Pop-ups, modals and the loading overlay keep their blur (they are one
element, shown briefly). Idempotent."""
import re
from pathlib import Path

root = Path(__file__).resolve().parent.parent / "public" / "assets" / "css"

# --- dash.css: .glass keeps its look with a nearly opaque gradient instead of the blur
p = root / "dash.css"
s = p.read_text(encoding="utf-8")
old = (".glass{background:linear-gradient(135deg,rgba(226,232,242,.55),rgba(226,232,242,.22));\n"
       "    -webkit-backdrop-filter:blur(14px) saturate(1.5);backdrop-filter:blur(14px) saturate(1.5);\n")
new = (".glass{background:linear-gradient(135deg,rgba(236,240,247,.97),rgba(228,234,244,.93));\n"
       "    /* no backdrop-filter here: a live blur on every card and on the fixed sidebar is re-drawn on each scroll frame */\n")
if old in s:
    s = s.replace(old, new)
    p.write_text(s, encoding="utf-8")
    print("dash.css: .glass blur removed")
else:
    print("dash.css: already done or pattern changed")

# --- inbox.css: chat bubbles (.m.me / .m.them) of the themes
p = root / "inbox.css"
s = p.read_text(encoding="utf-8")
n = 0


def strip(m: re.Match) -> str:
    global n
    rule = m.group(0)
    if re.search(r"\.m\.(me|them)", m.group(1)):
        new_rule, k = re.subn(r"-?(?:webkit-)?backdrop-filter\s*:[^;}]+;?", "", rule)
        # the pattern above also matches the leading '-' of '-webkit-': normalise anything that was left behind
        new_rule = re.sub(r"(?<![\w-])-(?=\s*[;}])", "", new_rule)
        n += k
        return new_rule
    return rule


s = re.sub(r"([^{}]+)\{[^{}]*\}", strip, s)
p.write_text(s, encoding="utf-8")
print("inbox.css: removed", n, "backdrop-filter declarations from chat bubbles")
