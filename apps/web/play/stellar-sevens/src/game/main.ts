// A Content Security Policy without 'unsafe-eval' needs this import first, or Pixi hangs on boot.
import 'pixi.js/unsafe-eval';
import { Application } from 'pixi.js';
import type { Grid, RoundResult } from '../engine';
import { Board, BOARD_H, BOARD_W, loadSymbolTextures } from './board';
import { Hud } from './hud';
import { initFit } from './fit';
import { fetchSession, serverSpin, tellLowBalance, WalletError } from './wallet';
import { fishkas } from '../../../shared/house';
import { setBets } from './hud';

/** The frame's opening picture before the first spin (the browser never deals: ADR 0024). */
const OPENING: Grid = [
  ['CHERRY', 'SEVEN', 'LEMON'],
  ['BELL', 'SEVEN', 'PLUM'],
  ['MELON', 'SEVEN', 'ORANGE'],
];

function loaderProgress(pct: number): void {
  const fill = document.querySelector<HTMLElement>('.loader-fill');
  if (fill) fill.style.width = `${pct}%`;
}

async function boot(): Promise<void> {
  initFit();
  loaderProgress(15);

  const app = new Application();
  await app.init({
    width: BOARD_W,
    height: BOARD_H,
    backgroundAlpha: 0,
    antialias: true,
    powerPreference: 'high-performance',
    resolution: Math.min(window.devicePixelRatio || 1, 2),
    autoDensity: true,
  });
  // autoDensity writes inline width/height styles; clear both so the
  // responsive #stage canvas CSS rules control the displayed size.
  app.canvas.style.width = '';
  app.canvas.style.height = '';
  document.getElementById('stage')!.appendChild(app.canvas);
  loaderProgress(45);

  const board = new Board(app, await loadSymbolTextures());
  loaderProgress(75);

  // The opening balance and the bets come from the server; if it can't be reached the game still boots, shows
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
  loaderProgress(90);

  let busy = false;

  const hud = new Hud(document.getElementById('ui')!, {
    onSpin: () => {
      if (busy) {
        board.hurry();
        return;
      }
      void play();
    },
  });
  hud.setBalance(balance);
  if (sessionTrouble) hud.message(sessionTrouble);

  // land an opening grid so the frame never sits empty
  board.land(OPENING);

  async function play(): Promise<void> {
    const bet = hud.bet;
    if (hud.balance < bet) {
      if (!tellLowBalance()) hud.message('Not enough Fishkas for this bet.');
      return;
    }

    busy = true;
    hud.setBusy(true);
    hud.setWin(0);
    hud.message('');

    let round: RoundResult;
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

    await board.spin(round.baseSpin.grid);

    if (win > 0) {
      const show = board.presentWins(round.baseSpin);
      await hud.countWin(win);
      if (win >= bet * 20) await hud.bigWin(win);
      await show;
      hud.message(`WIN ${fishkas(win)}`);
    }
    hud.setBalance(settledBalance);

    busy = false;
    hud.setBusy(false);
  }

  // loader away, then the intro waits for its click
  loaderProgress(100);
  await new Promise((r) => setTimeout(r, 350));
  const loader = document.getElementById('loader');
  if (loader) {
    loader.classList.add('done');
    setTimeout(() => loader.remove(), 600);
  }

  const intro = document.getElementById('intro');
  if (intro) {
    const dismiss = (): void => {
      intro.classList.add('done');
      setTimeout(() => intro.remove(), 500);
      window.removeEventListener('keydown', onKey, true);
    };
    const onKey = (e: KeyboardEvent): void => {
      if (e.code === 'Space' || e.code === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        dismiss();
      }
    };
    intro.addEventListener('click', dismiss, { once: true });
    window.addEventListener('keydown', onKey, true);
  }
}

void boot();
