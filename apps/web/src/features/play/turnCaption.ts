import type { TFunction } from 'i18next'
import type { RoomState } from './api'

/**
 * Who the table is waiting for, or null when there's no one to wait for: the game is over, nobody
 * is to act, or it's the player's own turn (boards then show their own prompt).
 */
export function waitingCaption(
  state: Pick<RoomState, 'seat' | 'seats_to_act' | 'result'>,
  players: Partial<Record<number, string>> | undefined,
  t: TFunction,
): string | null {
  const acting = state.seats_to_act
  if (state.result || acting.length === 0) return null
  if (state.seat !== null && acting.includes(state.seat)) return null
  if (acting.length > 1) return t('room.waitingForOthers')
  const name = players?.[acting[0]!]
  return name ? t('room.waitingFor', { name }) : t('room.theirTurn')
}
