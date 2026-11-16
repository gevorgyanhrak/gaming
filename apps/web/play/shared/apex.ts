/**
 * The APEXSLOTS wallet protocol (their `src/game/wallet.ts`: session, spin, deliver, amounts in cents) spoken
 * over our house slot API (ADR 0024), so the feature games' clients keep their own code. One Fishka is shown as
 * one unit: "cents" here are Fishkas × 100. Our server credits a win with the spin, so nothing is ever held back
 * or pending, and `deliverRound` only reads the balance.
 */
import { HouseError, openGame, spin, tellLowBalance } from './house'

export type PlaySpec = { type: 'base' }

export class ApexWalletError extends Error {
  readonly balanceCents?: number
  /** Never set here (no held wins); kept so the clients' checks still type. */
  readonly pending?: never

  constructor(message: string, balanceCents?: number) {
    super(message)
    this.balanceCents = balanceCents
  }
}

export interface ApexPendingRound<Round> {
  roundId: string
  play: string
  betCents: number
  costCents: number
  winCents: number
  round: Round
}

export interface ApexServerRound<Round> {
  roundId: string
  round: Round
  winCents: number
  costCents: number
  balanceCents: number
  held?: boolean
  replay?: boolean
}

const toError = (error: unknown) =>
  error instanceof HouseError
    ? new ApexWalletError(
        error.message,
        error.balance === undefined ? undefined : error.balance * 100,
      )
    : new ApexWalletError('Something went wrong. Your Fishkas are safe; try again.')

export function apexWallet<Round>(game: string) {
  /** The bets the server offers, read by the HUD (`setBets`) once the session is open. */
  let bets: number[] = []

  return {
    tellLowBalance,
    bets: () => bets,

    async fetchSession(): Promise<{
      balanceCents: number
      pending: ApexPendingRound<Round> | null
    }> {
      try {
        const session = await openGame(game)
        bets = session.bets
        return { balanceCents: session.balance * 100, pending: null }
      } catch (error) {
        throw toError(error)
      }
    },

    async deliverRound(): Promise<number> {
      try {
        return (await openGame(game)).balance * 100
      } catch (error) {
        throw toError(error)
      }
    },

    async serverSpin(_spec: PlaySpec, betCents: number): Promise<ApexServerRound<Round>> {
      try {
        const dealt = await spin<Round>(game, Math.round(betCents / 100))
        return {
          roundId: dealt.round_id,
          round: dealt.round,
          winCents: dealt.win * 100,
          costCents: dealt.bet * 100,
          balanceCents: dealt.balance * 100,
          held: false,
          replay: dealt.replay,
        }
      } catch (error) {
        throw toError(error)
      }
    },
  }
}
