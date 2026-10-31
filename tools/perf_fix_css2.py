"""One-off, idempotent: `transition: all` makes the browser watch every property of an element (including ones that force a layout);
list the properties that really change. The horror-theme crawler keeps `all` (it moves `bottom`, one decorative element)."""
import re
from pathlib import Path

root = Path(__file__).resolve().parent.parent / "public" / "assets" / "css"
PROPS = "background-color, border-color, color, box-shadow, transform, opacity"


def fix(m: re.Match) -> str:
    dur = m.group(1)
    ease = m.group(2) or ""
    return "transition: " + ", ".join(f"{p.strip()} {dur}{ease}" for p in PROPS.split(","))


n = 0
for name in ("dash.css", "inbox.css", "interlink.css"):
    p = root / name
    s = p.read_text(encoding="utf-8")
    out = []
    for line in s.split("\n"):
        if "horror-crawler" in line:
            out.append(line)
            continue
        new, k = re.subn(r"transition:\s*all\s+([\d.]+m?s)((?:\s+(?:ease-out|ease-in-out|ease-in|ease|linear|cubic-bezier\([^)]*\)))?)", fix, line)
        n += k
        out.append(new)
    p.write_text("\n".join(out), encoding="utf-8")
print("transition: all replaced in", n, "places")

# touch devices: no 300 ms tap delay on controls, no grey flash on tap
p = root / "dash.css"
s = p.read_text(encoding="utf-8")
marker = "/* ---- smoothness hygiene ---- */"
if marker not in s:
    s += ("\n" + marker + "\n"
          "a, button, [role=button], input, select, textarea, label, summary { touch-action: manipulation; }\n"
          "html { -webkit-tap-highlight-color: transparent; }\n"
          "img, video { max-width: 100%; }\n")
    p.write_text(s, encoding="utf-8")
    print("dash.css: touch-action / tap highlight added")
