"""Copy one APEXSLOTS feature game's client (free games, held-win protocol) into apps/web/play/<id> (ADR 0024).

Usage: python3 vendor_feature.py <id> <opening grid JSON> <engine files to keep, comma separated>
The wallet becomes play/shared/apex.ts (their protocol over our API); the browser demo that dealt rounds is
removed; amounts are whole Fishkas; the bets are the server's. Every patch is asserted.
"""
import glob, json, os, re, shutil, sys

Z = os.environ['APEX_SRC']  # a checkout of https://github.com/Z1000k/open-source-slots
W = '/Users/hrak/Projects/game-platform/apps/web'
gid, grid, keep = sys.argv[1], json.loads(sys.argv[2]), sys.argv[3].split(',')
src, dst = os.path.join(Z, gid), os.path.join(W, 'play', gid)

shutil.rmtree(dst, ignore_errors=True)
shutil.copytree(os.path.join(src, 'src', 'game'), os.path.join(dst, 'src', 'game'))
for f in keep:
    os.makedirs(os.path.dirname(os.path.join(dst, 'src', 'engine', f)), exist_ok=True)
    shutil.copy(os.path.join(src, 'src', 'engine', f), os.path.join(dst, 'src', 'engine', f))
shutil.copy(os.path.join(src, 'index.html'), dst)
pub = os.path.join(W, 'public', 'play', gid)
shutil.rmtree(pub, ignore_errors=True)
shutil.copytree(os.path.join(src, 'public', 'assets'), os.path.join(pub, 'assets'))
shutil.copy(os.path.join(Z, 'tiles', gid + '.webp'), os.path.join(pub, 'tile.webp'))

open(os.path.join(dst, 'src', 'engine', 'index.ts'), 'w').write(
    "// Only the game's types and the data its screens show: the rules run on the server (ADR 0024).\n"
    + ''.join(f"export * from './{f[:-3]}';\n" for f in keep))

open(os.path.join(dst, 'src', 'game', 'wallet.ts'), 'w').write(f"""/**
 * Where the Fishkas live: on our server (ADR 0024). Replaces the APEXSLOTS original, which also had a demo that
 * dealt in the browser: the same protocol now runs over our API (../../shared/apex), which credits every win
 * with its spin, so no round is ever held back or pending.
 */
import type {{ RoundResult }} from '../engine';
import {{
  apexWallet,
  ApexWalletError,
  type ApexPendingRound,
  type ApexServerRound,
}} from '../../../shared/apex';

const wallet = apexWallet<RoundResult>('{gid}');

/** Always true here: there is no browser-dealt demo. */
export const REAL = true;

export type {{ PlaySpec }} from '../../../shared/apex';
export {{ ApexWalletError as WalletError }};
export type PendingRound = ApexPendingRound<RoundResult>;
export type ServerRound = ApexServerRound<RoundResult>;

export const tellLowBalance = wallet.tellLowBalance;
export const fetchSession = wallet.fetchSession;
/** Wins are credited with the spin: delivering only reads the balance. */
export const deliverRound = (_roundId: string) => wallet.deliverRound();
export const serverSpin = wallet.serverSpin;
/** The bets the server offers, known once the session is open. */
export const offeredBets = wallet.bets;
""")

def patch(path, pairs):
    p = os.path.join(dst, path)
    s = open(p).read()
    for old, new in pairs:
        if isinstance(old, re.Pattern):
            s, n = old.subn(new, s)
            if n == 0:
                sys.exit(f'{path}: pattern not found: {old.pattern[:70]!r}')
        else:
            if old not in s:
                sys.exit(f'{path}: not found: {old[:70]!r}')
            s = s.replace(old, new)
    open(p, 'w').write(s)

rows = ',\n'.join('  [' + ', '.join(f"'{c}'" for c in col) + ']' for col in grid)
patch('src/game/main.ts', [
    (re.compile(r"import \{ mulberry32, playRound, randomSeed, type RoundResult \} from '\.\./engine';"),
     "import type { Grid, RoundResult } from '../engine';\nimport { setBets } from './hud';"),
    ("  fetchSession,\n", "  fetchSession,\n  offeredBets,\n"),
    ("const rng = mulberry32(randomSeed());\n", f"/** The frame's opening picture before the first spin (the browser never deals: ADR 0024). */\nconst OPENING: Grid = [\n{rows},\n];\n"),
    ("board.land(playRound(mulberry32(7)).baseSpin.grid);", "board.land(OPENING);"),
    ("  let balance = 1000;", "  let balance = 0;"),
    ("      unfinished = session.pending;\n", "      unfinished = session.pending;\n      setBets(offeredBets());\n"),
    (re.compile(r"      \} else \{\n        hud\.setBalance\(hud\.balance - bet\);\n        const round = playRound\(rng\);\n.*?\n      \}\n", re.S), "      }\n"),
    ("if (!tellLowBalance()) hud.message(REAL ? 'Balance exhausted.' : 'Balance exhausted, refresh to reset the demo.');", "if (!tellLowBalance()) hud.message('Not enough Fishkas for this bet.');"),
    ("// The live CSP on apexslots.eu has no 'unsafe-eval': this import MUST come\n// first or Pixi hangs on boot when served from the site.", "// A Content Security Policy without 'unsafe-eval' needs this import first, or Pixi hangs on boot."),
])
main = open(os.path.join(dst, 'src', 'game', 'main.ts')).read()
for word in ['playRound', 'mulberry32', 'randomSeed', ' rng']:
    if word in main:
        sys.exit(f'main.ts still uses {word}')

patch('src/game/hud.ts', [
    (re.compile(r"import \{ REAL \} from './wallet';\n\nconst DEMO = REAL \? '' : 'DEMO ';\n\nconst ALL_BETS = \[[^\]]*\];\n/\*\*[^\n]*\*/\nconst BETS = REAL \? ALL_BETS.filter\(\(b\) => b <= 5\) : ALL_BETS;\n"),
     """/** The bets in Fishkas, as the server offers them for this game (set before the HUD is built). */
let BETS: number[] = [10];

export function setBets(bets: number[]): void {
  if (bets.length > 0) BETS = bets;
}
"""),
    ("private betIndex = BETS.indexOf(1);", "private betIndex = 0;"),
    ("${DEMO}BALANCE", "FISHKAS"),
    ("    return BETS[this.betIndex];", "    return BETS[Math.min(this.betIndex, BETS.length - 1)];"),
])

# Whole Fishkas everywhere a client printed money with two decimals.
for path in glob.glob(os.path.join(dst, 'src', 'game', '*.ts')):
    s = open(path).read()
    if 'toFixed(2)' not in s and '0.00' not in s:
        continue
    s = re.sub(r"\(\(([^()]*)\) \* bet\)\.toFixed\(2\)", r"fishkas((\1) * bet)", s)
    s = re.sub(r"\(([^()]+?)\)\.toFixed\(2\)(?!\}?%)", r"fishkas(\1)", s)
    s = re.sub(r"([A-Za-z_][\w.]*(?:\[[^\]]+\])?)\.toFixed\(2\)(?!\}?%)", r"fishkas(\1)", s)
    s = s.replace('>0.00<', '>0<')
    if re.search(r"toFixed\(2\)(?!\}?%)", s):
        sys.exit(f'{path}: toFixed(2) left')
    if 'fishkas(' in s and "from '../../../shared/house'" not in s:
        anchor = re.search(r"^import 'pixi.js/unsafe-eval';\n", s, re.M)
        first_import = anchor.end() if anchor else re.search(r'^import ', s, re.M).start()
        s = s[:first_import] + "import { fishkas } from '../../../shared/house';\n" + s[first_import:]
    open(path, 'w').write(s)

# A screen that prints the dealt set's RTP gets the figure itself (the reel sets stay on the server).
gi = os.path.join(dst, 'src', 'game', 'gameinfo.ts')
g = open(gi).read()
if 'ACTIVE_REELS' in g:
    rtp = os.environ['RTP']
    g = re.sub(r"\n\s*ACTIVE_REELS,", "", g).replace('ACTIVE_REELS.rtp', rtp)
    open(gi, 'w').write(g)

patch('src/game/assets.ts', [("import.meta.env.BASE_URL + path", f"'/play/{gid}/' + path")])
p = os.path.join(dst, 'src', 'game', 'style.css')
s = re.sub(r"url\((['\"]?)/assets/", lambda m: f"url({m.group(1)}/play/{gid}/assets/", open(p).read())
open(p, 'w').write(s)
p = os.path.join(dst, 'index.html')
s = open(p).read().replace('href="/src/game/style.css"', 'href="./src/game/style.css"').replace('src="/src/game/main.ts"', 'src="./src/game/main.ts"').replace('"/assets/art/', f'"/play/{gid}/assets/art/')
s = re.sub(r"<title>(.*?) - Demo</title>", r"<title>\1</title>", s)
open(p, 'w').write(s)
print('ok', gid)
