#!/usr/bin/env python3
"""
Converts Slotopol's AGT-style game maths (MIT, https://github.com/slotopol/server, kept in this repository's branch
`backup/before-slotopol-restore-2026-11-18` under services/slot-engine/game/slot) into the JSON our house slot engine
reads (apps/api/app/Platform/Slots/House/Agt/data/<slug>.json). Only numbers are taken: symbols, pay tables, lines,
reel strips at one RTP. No art, names of art or code is copied.

    python3 apps/web/play/tools/slotopol_agt.py sevenhot seven-hot "Seven Hot"
"""
import json
import re
import subprocess
import sys
from pathlib import Path

BRANCH = 'backup/before-slotopol-restore-2026-11-18'
BASE = 'services/slot-engine/game/slot'
OUT = Path(__file__).resolve().parents[4] / 'apps/api/app/Platform/Slots/House/Agt/data'
TARGET_RTP = 96.0


def show(path: str) -> str:
    return subprocess.run(['git', 'show', f'{BRANCH}:{path}'], capture_output=True, text=True, check=True).stdout


def lines_table(name: str) -> list[list[int]]:
    src = show(f'{BASE}/lines.go')
    m = re.search(rf'var {name} = \[\.\.\.\]Linex\{{(.*?)\n\}}', src, re.S)
    if not m:
        raise SystemExit(f'lines table {name} not found')
    return [[int(n) - 1 for n in row.split(',')] for row in re.findall(r'\{([\d, ]+)\}', m.group(1))]


def main(game: str, slug: str, title: str, lnum: int | None = None) -> None:
    rule = show(f'{BASE}/agt/{game}/{game}_rule.go')
    link = show(f'{BASE}/agt/{game}/{game}_link.go')
    data = show(f'{BASE}/agt/{game}/{game}_data.yaml')

    consts = dict(re.findall(r'(\w+)\s*=\s*(\d+)', rule.split('var ReelsMap')[0]))
    m = re.search(r'(\w+), (\w+) += (\d+), (\d+)', rule)
    if m:
        consts[m.group(1)] = m.group(3)
        consts[m.group(2)] = m.group(4)

    pay_block = re.search(r'var LinePay = \[sn\]\[\d\]float64\{(.*?)\n\}', rule, re.S).group(1)
    symbols, line_pay = [], []
    for row, name in re.findall(r'\{([^}]*)\},\s*//\s*\d+\s+([^\n(]+)', pay_block):
        symbols.append(name.strip())
        nums = [float(x) for x in row.split(',') if x.strip()]
        line_pay.append([int(x) if x == int(x) else x for x in nums] + [0] * (5 - len(nums)) if nums else [])
    scat = re.search(r'var ScatPay = \[\d\]float64\{([^}]*)\}', rule)
    scat_pay = [float(x) for x in scat.group(1).split(',')] if scat else []

    if 'sx != syml && sx != wild' in rule:
        wild_mode = 'line_wild'  # the first symbol sets the line; a wild on the line counts as it and never pays itself
    elif 'reelwild' in rule:
        wild_mode = 'expanding'  # a wild anywhere on reels 2–4 turns the whole reel wild; the wild itself pays nothing
    elif 'numw' in rule:
        wild_mode = 'substitute'  # stands in for line symbols, and pays its own run when that pays more
    else:
        wild_mode = 'none'

    # Fill multiplier: whole reels of one symbol multiply every line win. Cherry Hot counts the full reels from the
    # left (×3…×5 for 3…5 reels); Valentine's Day counts them around the middle reel (×2…×4 for 3…5 reels).
    fill = None
    fm = re.search(r'func \(g \*Game\) FillMult\(\).*?var sym = g\.Grid\[(\d)\]\[(\d)\].*?sym < (\d+) \|\| sym > (\d+)', rule, re.S)
    if fm:
        fill = {
            'from': 'left' if fm.group(1) == '0' else 'center',
            'symbols': list(range(int(fm.group(3)) - 1, int(fm.group(4)))),
        }

    lines_name = re.search(r'slot\.(BetLines\w+)', rule).group(1)
    sx = int(re.search(r'SX: +(\d+)', link).group(1))
    sy = int(re.search(r'SY: +(\d+)', link).group(1))
    default_lnum = int(re.search(r'LNum: (\d+)', link).group(1))
    lnum = lnum or default_lnum

    # Reel sets keyed by their exact RTP; take the one closest to 96 % without going over 96.5 %.
    sets = {}
    for key, body in re.findall(r'\n([\d.]+):\n((?:  - \[[^\]]*\][^\n]*\n?)+)', data):  # a reel may end with '# length'
        sets[float(key)] = [[int(n) - 1 for n in r.split(',')] for r in re.findall(r'\[([^\]]*)\]', body)]
    rtp = min((k for k in sets if k <= 96.5), key=lambda k: abs(k - TARGET_RTP))

    out = {
        'source': f'Slotopol (MIT) game/slot/agt/{game}, reels at {rtp}% RTP',
        'slug': slug,
        'title': title,
        'cols': sx,
        'rows': sy,
        'lines': lines_table(lines_name)[:lnum],
        # The whole table: Slotopol's published RTP is computed over it (the tests check the port against that).
        'lines_all': lines_table(lines_name),
        'symbols': symbols,
        'line_pay': line_pay,
        'scatter': int(consts['scat']) - 1 if 'scat' in consts else None,
        'scatter_pay': scat_pay,
        'wild': int(consts['wild']) - 1 if 'wild' in consts else None,
        'wild_mode': wild_mode,
        'fill': fill,
        'rtp': rtp,
        'reels': sets[rtp],
    }
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / f'{slug}.json').write_text(json.dumps(out, indent=1) + '\n')
    print(f'{slug}: {sx}x{sy}, {lnum} lines, {len(symbols)} symbols, wild {wild_mode}, RTP {rtp}')


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2], sys.argv[3], int(sys.argv[4]) if len(sys.argv) > 4 else None)
