"""Copy one APEXSLOTS game's client into apps/web/play/<id> with our standard changes (ADR 0024).

Usage: python3 vendor_slot.py <id> <opening grid as JSON> [engine files to keep, comma separated]
Only handles the simple main.ts (no held wins); asserts every replacement applied.
"""
import json, os, re, shutil, sys

# The upstream checkout (git clone https://github.com/Z1000k/open-source-slots), first argument's sibling env APEX_SRC.
Z = os.environ['APEX_SRC']
W = '/Users/hrak/Projects/game-platform/apps/web'
gid, grid = sys.argv[1], json.loads(sys.argv[2])
keep = sys.argv[3].split(',') if len(sys.argv) > 3 else ['types.ts', 'config/paylines.ts', 'config/paytable.ts']
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

def edit(path, pairs):
    p = os.path.join(dst, path)
    s = open(p).read()
    for old, new in pairs:
        if old not in s:
            sys.exit(f'{path}: not found: {old[:70]!r}')
        s = s.replace(old, new)
    open(p, 'w').write(s)

exports = ''.join(f"export * from './{f[:-3]}';\n" for f in keep)
open(os.path.join(dst, 'src', 'engine', 'index.ts'), 'w').write(
    "// Only the game's types and its paytable/lines for the info screen: the rules run on the server (ADR 0024).\n" + exports)

name = gid.upper().replace('-', ' ')
open(os.path.join(dst, 'src', 'game', 'wallet.ts'), 'w').write(f"""/**
 * Where the Fishkas live: on our server (ADR 0024). Changed from the APEXSLOTS original, which also had a demo
 * that dealt in the browser: here every round is dealt by the server through ../../shared/house, and this module
 * only keeps the names the rest of the game uses. {name} is stateless, so nothing is ever held back.
 */
import type {{ RoundResult }} from '../engine';
import {{ HouseError, openGame, spin, tellLowBalance }} from '../../../shared/house';

export const GAME = '{gid}';

/** Always true here: there is no browser-dealt demo. */
export const REAL = true;

export {{ tellLowBalance }};
export {{ HouseError as WalletError }};

export const fetchSession = () => openGame(GAME);

export const serverSpin = (bet: number) => spin<RoundResult>(GAME, bet);
""")

p = os.path.join(dst, 'src', 'game', 'main.ts')
s = open(p).read()
rows = ',\n'.join('  [' + ', '.join(f"'{c}'" for c in col) + ']' for col in grid)
pairs = [
    ("import { mulberry32, playRound, randomSeed, type RoundResult } from '../engine';", "import type { Grid, RoundResult } from '../engine';"),
    ("import { fetchSession, REAL, serverSpin, tellLowBalance, WalletError } from './wallet';",
     "import { fetchSession, serverSpin, tellLowBalance, WalletError } from './wallet';\nimport { fishkas } from '../../../shared/house';\nimport { setBets } from './hud';"),
    ("const rng = mulberry32(randomSeed());\n\n", f"/** The frame's opening picture before the first spin (the browser never deals: ADR 0024). */\nconst OPENING: Grid = [\n{rows},\n];\n\n"),
    ("board.land(playRound(mulberry32(7)).baseSpin.grid);", "board.land(OPENING);"),
    ("    const win = round.totalWin * bet;\n", ""),
    ("if (!tellLowBalance()) hud.message(REAL ? 'Balance exhausted.' : 'Balance exhausted, refresh to reset the demo.');", "if (!tellLowBalance()) hud.message('Not enough Fishkas for this bet.');"),
    ("// The live CSP on apexslots.eu has no 'unsafe-eval': this import MUST come\n// first or Pixi hangs on boot when served from the site.", "// A Content Security Policy without 'unsafe-eval' needs this import first, or Pixi hangs on boot."),
]
for old, new in pairs:
    if old not in s:
        sys.exit(f'main.ts: not found: {old[:70]!r}')
    s = s.replace(old, new)
i = s.index("  // Played for real, the opening balance"); j = s.index("  loaderProgress(90);")
s = s[:i] + """  // The opening balance and the bets come from the server; if it can't be reached the game still boots, shows
  // zero, and says why.
  let balance = 0;
  let sessionTrouble = '';
  try {
    const session = await fetchSession();
    balance = session.balance;
    setBets(session.bets);
  } catch (err) {
    sessionTrouble = err instanceof WalletError ? err.message : 'The server did not answer. Reload to try again.';
  }
""" + s[j:]
i = s.index("    let round: RoundResult;"); j = s.index("    await board.spin(round.baseSpin.grid);")
s = s[:i] + """    let round: RoundResult;
    let settledBalance: number;
    let win: number;
    // the press starts the show: the reels roll while the server deals, and the answer lands them
    board.prime();
    try {
      const dealt = await serverSpin(bet);
      round = dealt.round;
      win = dealt.win;
      settledBalance = dealt.balance;
      hud.setBalance(settledBalance - win);
    } catch (err) {
      hud.message(err instanceof WalletError ? err.message : 'The spin failed. Nothing was charged.');
      if (err instanceof WalletError && err.balance !== undefined) hud.setBalance(err.balance);
      // the reels come back down where they were
      await board.abort();
      busy = false;
      hud.setBusy(false);
      return;
    }

""" + s[j:]
s = s.replace("hud.message(`WIN ${win.toFixed(2)}`);", "hud.message(`WIN ${fishkas(win)}`);")
if 'toFixed' in s or 'REAL' in s or 'playRound' in s:
    sys.exit('main.ts: leftovers ' + ', '.join(w for w in ['toFixed', 'REAL', 'playRound'] if w in s))
open(p, 'w').write(s)

p = os.path.join(dst, 'src', 'game', 'hud.ts')
s = open(p).read()
s = re.sub(r"import \{ REAL \} from './wallet';\n\nconst DEMO = REAL \? '' : 'DEMO ';\n\nconst ALL_BETS = \[[^\]]*\];\n/\*\*[^\n]*\*/\nconst BETS = REAL \? ALL_BETS.filter\(\(b\) => b <= 5\) : ALL_BETS;\n",
           """import { fishkas } from '../../../shared/house';

/** The bets in Fishkas, as the server offers them for this game (set before the HUD is built). */
let BETS: number[] = [10];

export function setBets(bets: number[]): void {
  if (bets.length > 0) BETS = bets;
}
""", s)
s = s.replace("private betIndex = BETS.indexOf(1);", "private betIndex = 0;")
s = s.replace("${DEMO}BALANCE", "FISHKAS").replace('<span id="m-balance">0.00</span>', '<span id="m-balance">0</span>').replace('<span id="m-win">0.00</span>', '<span id="m-win">0</span>').replace('<div class="bigwin-amount">0.00</div>', '<div class="bigwin-amount">0</div>')
s = s.replace("this.bet.toFixed(2)", "fishkas(this.bet)").replace("v.toFixed(2)", "fishkas(v)").replace("n.toFixed(2)", "fishkas(n)")
s = s.replace("    return BETS[this.betIndex];", "    return BETS[Math.min(this.betIndex, BETS.length - 1)];")
if 'REAL' in s or 'toFixed' in s or 'setBets' not in s:
    sys.exit('hud.ts: not adapted')
open(p, 'w').write(s)

p = os.path.join(dst, 'src', 'game', 'gameinfo.ts')
s = open(p).read()
s = re.sub(r"\(\(([^;]*?)\) \* bet\)\.toFixed\(2\)", r"fishkas((\1) * bet)", s)
s = re.sub(r"\(([^()]+?)\)\.toFixed\(2\)", r"fishkas(\1)", s)
s = s.replace("${bet.toFixed(2)}", "${fishkas(bet)} FISHKAS")
s = s.replace("import { symbolArt } from './assets';", "import { symbolArt } from './assets';\nimport { fishkas } from '../../../shared/house';")
if 'toFixed' in s:
    sys.exit('gameinfo.ts: toFixed left: ' + ', '.join(l.strip() for l in s.splitlines() if 'toFixed' in l))
open(p, 'w').write(s)

edit('src/game/assets.ts', [("import.meta.env.BASE_URL + path", f"'/play/{gid}/' + path")])
p = os.path.join(dst, 'src', 'game', 'style.css')
s = re.sub(r"url\((['\"]?)/assets/", lambda m: f"url({m.group(1)}/play/{gid}/assets/", open(p).read())
open(p, 'w').write(s)
p = os.path.join(dst, 'index.html')
s = open(p).read().replace('href="/src/game/style.css"', 'href="./src/game/style.css"').replace('src="/src/game/main.ts"', 'src="./src/game/main.ts"').replace('"/assets/art/', f'"/play/{gid}/assets/art/')
s = re.sub(r"<title>(.*?) - Demo</title>", r"<title>\1</title>", s)
open(p, 'w').write(s)
print('ok', gid)
