// Only the game's types and the data its screens show: the rules run on the server (ADR 0024).
export * from './types';
export * from './config/paylines';
export * from './config/paytable';
export * from './config/rtp';

import { SCAT_MIN } from './config/paytable';
import { HEIRS, REELS, ROWS, type Grid, type Heir } from './types';

/**
 * Display only: the heir a base grid shows 3+ of (the heiress first), so the board can stage whose free games
 * follow. The server decides and sends the same caller (`bonus.caller`).
 */
export function callerOf(grid: Grid): Heir | null {
  for (const heir of HEIRS) {
    let count = 0;
    for (let x = 0; x < REELS; x++) for (let y = 0; y < ROWS; y++) if (grid[x][y] === heir) count++;
    if (count >= SCAT_MIN) return heir;
  }
  return null;
}
