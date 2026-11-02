import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { Room } from '@/features/play/api'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { isApiError } from '@/shared/api/errors'
import type { ReactionKind } from '../api'
import { reactionsOpen } from '../open'
import { useReactionsMuted } from '../prefs'
import { useReactionCatalog, useRoomReactions } from '../useRoomReactions'
import { ReactionLayer } from './ReactionLayer'
import { ReactionPicker } from './ReactionPicker'
import { TableChat } from './TableChat'

/** The same reaction to the same player again within this is a double click, not a second send. */
const REPEAT_MS = 1000

/**
 * Emojis and quick messages for one room (TASK-043): the table chat, the picker and the flights over
 * the page. Only for the room's own players, while the admin has them on and the table takes them.
 */
export function RoomReactions({ room, mySeat }: { room: Room; mySeat: number | null }) {
  const { t } = useTranslation()
  const config = usePublicConfig()
  const enabled = Boolean(config.data?.play.reactions) && mySeat !== null && reactionsOpen(room)
  const catalog = useReactionCatalog(enabled)
  const { log, flights, send, landed } = useRoomReactions(room.id, enabled)
  const muted = useReactionsMuted()
  const [picker, setPicker] = useState<ReactionKind | null>(null)
  const [error, setError] = useState<string | null>(null)
  const closePicker = useCallback(() => setPicker(null), [])
  const lastSent = useRef(new Map<string, number>())

  if (!enabled) return null

  const names: Partial<Record<number, string>> = Object.fromEntries(
    room.participants.map((p) => [p.seat, p.name ?? '']),
  )
  const recipients = room.participants
    .filter((p) => p.seat !== mySeat)
    .map((p) => ({ seat: p.seat, name: p.name ?? '?', bot: p.is_bot }))
  const quickTo = recipients.length === 1 ? (recipients[0]?.seat ?? null) : null

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
      <TableChat
        log={log}
        mySeat={mySeat}
        names={names}
        quickTo={quickTo}
        error={error}
        onOpen={setPicker}
        onQuick={(key, toSeat) => deliver('message', key, toSeat)}
      />
      <ReactionLayer
        flights={flights}
        mySeat={mySeat}
        names={names}
        muted={muted}
        onLanded={landed}
      />
      {picker && catalog.data ? (
        <ReactionPicker
          catalog={catalog.data}
          recipients={recipients}
          initialTab={picker}
          onSend={deliver}
          onClose={closePicker}
        />
      ) : null}
    </>
  )
}
