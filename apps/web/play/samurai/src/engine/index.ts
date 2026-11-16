// Only the game's types and the data its screens show: the rules run on the server (ADR 0024).
export * from './types';
export * from './config/paylines';
export * from './config/paytable';
export * from './config/rtp';

import { REELS, WILD, type Grid } from './types';

/**
 * Display only: the reels to show as wild in a free game (each that shows a SAMURAI). The server applies the same
 * rule when it scores the spin and sends `wildReels`; the board only needs it to light the reels.
 */
export function wildReelsOf(grid: Grid): number[] {
  const out: number[] = [];
  for (let x = 0; x < REELS; x++) if (grid[x].includes(WILD)) out.push(x);
  return out;
}
