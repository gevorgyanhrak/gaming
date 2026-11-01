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
  /** Seats played by bots, for boards that show the players. */
  bots?: Partial<Record<number, boolean>>
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
}

type View = Record<string, unknown>

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
  /** What the header calls the pause before the next round (a translation key). */
  nextRound?: ParseKeys
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
  },
}

export const traitsOf = (slug: string): GameTraits =>
  traits[slug] ?? { seatsOnBoard: false, material: 'wood' }
