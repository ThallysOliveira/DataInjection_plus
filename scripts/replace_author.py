#!/usr/bin/env python3
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

OLD_COPY = 'Copyright (C) 2007-2023 by DataInjection plugin team.'
NEW_COPY = 'Copyright (C) 2007-2026 by Thallys Fernandes.'

AUTHOR_NAMES = [
    'Walid Nouh', 'Dévi Balpe', 'Remi Collet', 'Nelly Mahu-Lasson', 'Xavier Caillaud'
]

def replace_in_file(path: Path):
    try:
        s = path.read_text(encoding='utf-8')
    except Exception:
        return False
    orig = s
    if OLD_COPY in s:
        s = s.replace(OLD_COPY, NEW_COPY)
    for a in AUTHOR_NAMES:
        if a in s:
            s = s.replace(a, 'Thallys Fernandes')
    # datainjection.xml authors block
    if '<authors>' in s and any(a in s for a in AUTHOR_NAMES):
        # naive replace: collapse authors to single author
        import re
        s = re.sub(r'<authors>[\s\S]*?<\/authors>', '<authors>\n      <author>Thallys Fernandes</author>\n   </authors>', s)

    if s != orig:
        bak = path.with_suffix(path.suffix + '.bak')
        bak.write_text(orig, encoding='utf-8')
        path.write_text(s, encoding='utf-8')
        return True
    return False

def main():
    changed = []
    for p in ROOT.rglob('*'):
        if p.is_file() and p.suffix.lower() in ['.php', '.js', '.css', '.xml', '.md']:
            if replace_in_file(p):
                changed.append(str(p.relative_to(ROOT)))
    print('Modified {} files'.format(len(changed)))
    for c in changed:
        print('-', c)

if __name__ == '__main__':
    main()
