import type { ComponentType, LazyExoticComponent } from 'react'
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
 * Board component per game slug, loaded on demand. Games add themselves here when their UI ships
 * (Nardi in Phase 6).
 */
export const boards: Partial<Record<string, LazyExoticComponent<ComponentType<BoardProps>>>> = {}
