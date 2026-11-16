// Only the game's types and the data its screens show: the rules run on the server (ADR 0024).
export * from './types';
export * from './config/paylines';
export * from './config/paytable';
export * from './config/rtp';

import { BLANK, REELS, SCATTER, SYMBOL_ORDER, type Expansion, type Grid, type SymbolId } from './types';

/** The expanding-symbol pool (for the info screen's odds), as the server draws from it. */
export const EXPAND_POOL: SymbolId[] = [2, 2, 2, 2, 3, 3, 3, 3, 4, 5, 6, 7, 8, 9, 9, 10, 10, 11, 11, 12, 12, 12, 12, 12, 12, 13, 13, 13].map(
  (n) => SYMBOL_ORDER[n - 1],
);

/**
 * Display only: the covering a free-game grid shows (the server sends the same as `expand`), so the board can
 * draw the covered reels as one tall symbol.
 */
export function expansionOf(grid: Grid): Expansion | undefined {
  const covered = (col: SymbolId[]): SymbolId | null => {
    if (col[0] === BLANK && col[1] === SCATTER && col[2] === BLANK) return SCATTER;
    if (col[0] !== BLANK && col[0] === col[1] && col[1] === col[2]) return col[0];
    return null;
  };
  const symbol = covered(grid[0]);
  if (symbol === null) return undefined;
  let reels = 1;
  while (reels < REELS && covered(grid[reels]) === symbol) reels++;
  return reels >= 2 ? { symbol, reels } : undefined;
}
