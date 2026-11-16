/**
 * Where the Fishkas live: on our server (ADR 0024). Replaces the APEXSLOTS original, which also had a demo that
 * dealt in the browser: the same protocol now runs over our API (../../shared/apex), which credits every win
 * with its spin, so no round is ever held back or pending.
 */
import type { RoundResult } from '../engine';
import {
  apexWallet,
  ApexWalletError,
  type ApexPendingRound,
  type ApexServerRound,
} from '../../../shared/apex';

const wallet = apexWallet<RoundResult>('tropic-escape');

/** Always true here: there is no browser-dealt demo. */
export const REAL = true;

export type { PlaySpec } from '../../../shared/apex';
export { ApexWalletError as WalletError };
export type PendingRound = ApexPendingRound<RoundResult>;
export type ServerRound = ApexServerRound<RoundResult>;

export const tellLowBalance = wallet.tellLowBalance;
export const fetchSession = wallet.fetchSession;
/** Wins are credited with the spin: delivering only reads the balance. */
export const deliverRound = (_roundId: string) => wallet.deliverRound();
export const serverSpin = wallet.serverSpin;
/** The bets the server offers, known once the session is open. */
export const offeredBets = wallet.bets;
