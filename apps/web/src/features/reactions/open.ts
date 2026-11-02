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
