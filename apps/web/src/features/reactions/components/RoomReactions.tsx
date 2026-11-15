import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import type { Room } from '@/features/play/api'
import { isApiError } from '@/shared/api/errors'
import type { ReactionKind } from '../api'
import { useReactionsEnabled } from '../open'
import { useReactionsMuted } from '../prefs'
import { useReactionCatalog, useRoomReactions } from '../useRoomReactions'
import { ReactionLayer } from './ReactionLayer'
import { ReactionPicker } from './ReactionPicker'
import { ReactionLog } from './ReactionLog'

/** The same reaction to the same player again within this is a double click, not a second send. */
const REPEAT_MS = 1000

/**
 * Emojis and quick messages for one room (TASK-043, reworked 2026-11-16): tapping a player's avatar opens the
 * picker for them (`target`) — an emoji flies to their avatar; tapping your own lets an emoji take your avatar's
 * place for 2 s; a quick message shows over your own avatar. The flights are seen by everyone at the table; a
 * hidden log reads them out. Only for the room's own players, while the admin has them on and the table takes them.
 */
export function RoomReactions({
  room,
  mySeat,
  target,
  onClose,
  onProfile,
}: {
  room: Room
  mySeat: number | null
  /** The seat whose avatar was tapped (yours too), or null. */
  target: number | null
  onClose: () => void
  /** Opens that player's card instead. */
  onProfile: (seat: number) => void
}) {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const enabled = useReactionsEnabled(room, mySeat)
  const catalog = useReactionCatalog(enabled)
  const { log, flights, send, landed } = useRoomReactions(room.id, enabled)
  const muted = useReactionsMuted()
  const [error, setError] = useState<string | null>(null)
  const lastSent = useRef(new Map<string, number>())
  useEffect(() => {
    if (!error) return
    const timer = window.setTimeout(() => setError(null), 4000)
    return () => window.clearTimeout(timer)
  }, [error])

  if (!enabled) return null

  const names: Partial<Record<number, string>> = Object.fromEntries(
    room.participants.map((p) => [p.seat, p.name ?? '']),
  )
  const recipients = room.participants
    .filter((p) => p.seat !== mySeat)
    .map((p) => ({ seat: p.seat, name: p.name ?? '?' }))
  const tapped = target === null ? undefined : room.participants.find((p) => p.seat === target)

  const deliver = (kind: ReactionKind, value: string, toSeat: number | null) => {
    // A double click sends once: the same reaction to the same player within a second is one send.
    const key = `${kind}:${value}:${toSeat ?? 'all'}`
    const now = Date.now()
    if (now - (lastSent.current.get(key) ?? 0) < REPEAT_MS) return
    lastSent.current.set(key, now)
    setError(null)
    send(kind, value, toSeat).catch((e: unknown) => {
      lastSent.current.delete(key)
      setError(
        isApiError(e) && e.status !== 429 && e.status < 500 ? e.message : t('reactions.sendFailed'),
      )
    })
  }

  return (
    <>
      <ReactionLog
        log={log}
        mySeat={mySeat}
        names={names}
        levels={Object.fromEntries(room.participants.map((p) => [p.seat, p.level]))}
        error={error}
      />
      <ReactionLayer
        flights={flights}
        mySeat={mySeat}
        names={names}
        muted={muted}
        onLanded={landed}
      />
      {tapped && catalog.data ? (
        <ReactionPicker
          key={tapped.seat}
          catalog={catalog.data}
          recipients={recipients}
          myLevel={user?.level ?? room.participants.find((p) => p.seat === mySeat)?.level ?? 1}
          initialTab="emoji"
          target={{ seat: tapped.seat, name: tapped.name ?? '?', self: tapped.seat === mySeat }}
          onProfile={() => onProfile(tapped.seat)}
          onSend={deliver}
          onClose={onClose}
        />
      ) : null}
    </>
  )
}
