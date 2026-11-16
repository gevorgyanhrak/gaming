/**
 * Where the Fishkas live: on our server (ADR 0024). Changed from the APEXSLOTS original, which also had a demo
 * that dealt in the browser: here every round is dealt by the server through ../../shared/house, and this module
 * only keeps the names the rest of the game uses. STELLAR SEVENS is stateless, so nothing is ever held back.
 */
import type { RoundResult } from '../engine';
import { HouseError, openGame, spin, tellLowBalance } from '../../../shared/house';

export const GAME = 'stellar-sevens';

/** Always true here: there is no browser-dealt demo. */
export const REAL = true;

export { tellLowBalance };
export { HouseError as WalletError };

export const fetchSession = () => openGame(GAME);

export const serverSpin = (bet: number) => spin<RoundResult>(GAME, bet);
