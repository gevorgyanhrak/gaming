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
}

/**
 * Board component per game slug, loaded on demand.
 */
export const boards: Partial<Record<string, LazyExoticComponent<ComponentType<BoardProps>>>> = {
  nardi: lazy(() => import('./nardi/NardiBoard')),
  checkers: lazy(() => import('./checkers/CheckersBoard')),
}
