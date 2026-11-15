import { usePublicConfig } from '@/features/platform/usePublicConfig'
import type { Room } from '@/features/play/api'

/** Mirrors the server (RoomReactions::open): minutes after the end a table still takes reactions. */
const OPEN_AFTER_END_MS = 10 * 60_000

export function reactionsOpen(room: Pick<Room, 'status' | 'finished_at'>, now = Date.now()) {
  if (room.status === 'waiting' || room.status === 'active') return true
  return (
    room.status === 'finished' &&
    !!room.finished_at &&
    now - Date.parse(room.finished_at) < OPEN_AFTER_END_MS
  )
}

/** Whether this player can send reactions at this table now (the admin has them on, the table takes them). */
export function useReactionsEnabled(room: Room | undefined, mySeat: number | null) {
  const config = usePublicConfig()
  return canReact(Boolean(config.data?.play.reactions), room, mySeat)
}

export const canReact = (on: boolean, room: Room | undefined, mySeat: number | null) =>
  on && !!room && mySeat !== null && reactionsOpen(room)
