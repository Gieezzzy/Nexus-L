# Builds writable/cache/fonts/cjk.ttf: the Windows Microsoft YaHei font as a single TrueType file, for tools/build/render-report.js
# (Chinese characters in the lab report forms). The font is not stored in git - its licence does not allow redistribution -
# so run once on each server:  pip install fonttools  &&  python tools/make-cjk-font.py
import os
from fontTools.ttLib import TTCollection

root = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'writable', 'cache', 'fonts')
os.makedirs(root, exist_ok=True)
TTCollection(os.path.join(os.environ.get('WINDIR', 'C:/Windows'), 'Fonts', 'msyh.ttc')).fonts[0].save(os.path.join(root, 'cjk.ttf'))
print('ok')
