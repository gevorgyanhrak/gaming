import type { ParseKeys } from 'i18next'
import { lazy, type ComponentType, type LazyExoticComponent } from 'react'
import type { GameAction, RoomState } from '@/features/play/api'

/**
 * Props every game board receives. Boards only render the server's view and offer the server's
 * legal actions; they never implement rules.
 */
export interface BoardProps {
  state: RoomState
  onAction: (action: GameAction) => void
  pending: boolean
  /** Display names by seat, for boards that show the players (poker). */
  players?: Partial<Record<number, string>>
  /**
   * Not passed at the table any more: bots appear as ordinary players there (owner, 2026-11-12). Boards
   * still accept it until their old "Bot" pills are removed (TECHNICAL_DEBT).
   */
  bots?: Partial<Record<number, boolean>>
  /** Levels by seat, for boards that show the players' avatars. */
  levels?: Partial<Record<number, number>>
  /** Avatar frames by seat, for boards that draw their own avatars. */
  frames?: Partial<Record<number, string | null>>
}

/**
 * Board component per game slug, loaded on demand.
 */
export const boards: Partial<Record<string, LazyExoticComponent<ComponentType<BoardProps>>>> = {
  nardi: lazy(() => import('./nardi/NardiBoard')),
  checkers: lazy(() => import('./checkers/CheckersBoard')),
  chess: lazy(() => import('./chess/ChessBoard')),
  durak: lazy(() => import('./durak/DurakBoard')),
  poker: lazy(() => import('./poker/PokerBoard')),
  blackjack: lazy(() => import('./blackjack/BlackjackBoard')),
  blot: lazy(() => import('./blot/BlotBoard')),
  domino: lazy(() => import('./domino/DominoBoard')),
  ludo: lazy(() => import('./ludo/LudoBoard')),
  pool: lazy(() => import('./pool/PoolBoard')),
  rummy: lazy(() => import('./rummy/RummyBoard')),
}

/**
 * A small picture of each mode, for games whose modes differ enough to show (Nardi: Long and Short).
 * The game page shows them as cards to choose from.
 */
export const modePreviews: Partial<
  Record<string, LazyExoticComponent<ComponentType<{ mode: string }>>>
> = {
  nardi: lazy(() => import('./nardi/NardiModePreview')),
}

export type View = Record<string, unknown>

/** What the room page and the sounds need to know about a game, without loading its board. */
export interface GameTraits {
  /** The board draws every player at a seat, so the room page needn't list them while playing. */
  seatsOnBoard: boolean
  /** Which "move" sound fits: pieces on wood or cards. */
  material: 'wood' | 'cards'
  /** Whether the step from one view to the next took something (only where it's cheap to see). */
  capture?: (prev: View, next: View) => boolean
  /** Whether the step finished a round inside the game (a poker hand) that `seat` won. */
  roundWon?: (prev: View, next: View, seat: number) => boolean
  /** What the header calls a pause nobody acts in: before the next round, or (by the view) a deal being shown. */
  nextRound?: ParseKeys | ((view: View) => ParseKeys)
  /** What the lobby says about the buy-in of a game played for chips (amount, min, small, big). */
  buyIn?: ParseKeys
  /** What a chip table's badge says it plays for (small, big: the group's minimum bet and half of it). */
  tableTerms?: ParseKeys
}

const pieces = (view: View) =>
  Array.isArray(view.board) ? view.board.filter((piece) => piece !== null).length : 0
const length = (value: unknown) => (Array.isArray(value) ? value.length : 0)
const sum = (value: unknown) =>
  Array.isArray(value) ? value.reduce((total: number, n) => total + Number(n || 0), 0) : 0

export const traits: Partial<Record<string, GameTraits>> = {
  chess: { seatsOnBoard: false, material: 'wood', capture: (a, b) => pieces(b) < pieces(a) },
  checkers: {
    seatsOnBoard: false,
    material: 'wood',
    capture: (a, b) => length(b.captured) > length(a.captured) || pieces(b) < pieces(a),
  },
  // A hit sends a checker to the bar.
  nardi: { seatsOnBoard: false, material: 'wood', capture: (a, b) => sum(b.bar) > sum(a.bar) },
  // The defender gives up and takes the cards.
  durak: { seatsOnBoard: true, material: 'cards', capture: (a, b) => !a.taking && !!b.taking },
  poker: {
    seatsOnBoard: true,
    material: 'cards',
    roundWon: (a, b, seat) => {
      const before = a.last_hand as { number: number } | null
      const after = b.last_hand as { number: number; pots: { winners: number[] }[] } | null
      return (
        !!after &&
        after.number > (before?.number ?? 0) &&
        after.pots.some((pot) => pot.winners.includes(seat))
      )
    },
    nextRound: 'poker.nextHand',
    buyIn: 'play.pokerBuyIn',
  },
  blackjack: {
    seatsOnBoard: true,
    material: 'cards',
    roundWon: (a, b, seat) => {
      const before = a.last_round as { number: number } | null
      const after = b.last_round as { number: number; net: number[] } | null
      return !!after && after.number > (before?.number ?? 0) && (after.net[seat] ?? 0) > 0
    },
    nextRound: 'blackjack.nextRound',
    buyIn: 'play.blackjackBuyIn',
    tableTerms: 'play.tableMinBet',
  },
  // Partnerships (seats 0 and 2 against 1 and 3): a hand "won" is one your team scored more in.
  blot: {
    seatsOnBoard: true,
    material: 'cards',
    roundWon: (a, b, seat) => {
      const before = a.last_hand as { number: number } | null
      const after = b.last_hand as { number: number; scored: number[] } | null
      return (
        !!after &&
        after.number > (before?.number ?? 0) &&
        (after.scored[seat % 2] ?? 0) > (after.scored[1 - (seat % 2)] ?? 0)
      )
    },
    // The server's "dealing" pause shows the deal; the one after a deal waits for the next.
    nextRound: (view) =>
      (view as { phase?: string }).phase === 'dealing' ? 'blot.dealing' : 'blot.nextHand',
  },
  domino: {
    seatsOnBoard: true,
    material: 'wood',
    roundWon: (a, b, seat) => {
      const before = a.last_round as { number: number } | null
      const after = b.last_round as { number: number; winner: number | null } | null
      return !!after && after.number > (before?.number ?? 0) && after.winner === seat
    },
    nextRound: 'domino.nextRound',
  },
  // A token sent back to its yard.
  ludo: {
    seatsOnBoard: true,
    material: 'wood',
    capture: (a, b) => {
      const last = b.last as { captured: unknown[] } | null
      return a.last !== b.last && !!last && last.captured.length > 0
    },
  },
  // A shot that potted a ball.
  pool: {
    seatsOnBoard: true,
    material: 'wood',
    capture: (a, b) => {
      const before = a.last_shot as { number: number } | null
      const after = b.last_shot as { number: number; potted: number[] } | null
      return (
        !!after && after.number > (before?.number ?? 0) && after.potted.some((ball) => ball !== 0)
      )
    },
    nextRound: 'pool.rolling',
  },
  rummy: {
    seatsOnBoard: true,
    material: 'cards',
    roundWon: (a, b, seat) => {
      const before = a.last_round as { number: number } | null
      const after = b.last_round as { number: number; winner: number | null } | null
      return !!after && after.number > (before?.number ?? 0) && after.winner === seat
    },
    nextRound: 'rummy.nextRound',
  },
}

export const traitsOf = (slug: string): GameTraits =>
  traits[slug] ?? { seatsOnBoard: false, material: 'wood' }
