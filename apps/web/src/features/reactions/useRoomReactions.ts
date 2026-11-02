import { useQuery } from '@tanstack/react-query'
import { useCallback, useEffect, useRef, useState } from 'react'
import { useCurrentUser } from '@/features/auth/hooks'
import { useRealtimeConnected } from '@/shared/realtime/useRealtimeConnected'
import { useUserChannel } from '@/shared/realtime/useUserChannel'
import {
  fetchReactionCatalog,
  fetchRoomReactions,
  reactionCatalogKey,
  sendReaction,
  type Reaction,
  type ReactionKind,
} from './api'

/** Polling while the live connection is down. */
const POLL_MS = 3000
/** At most this many reactions found by catching up are animated (older ones only join the chat). */
const CATCH_UP_FLIGHTS = 3
const LOG_SIZE = 50

export function useReactionCatalog(enabled = true) {
  return useQuery({
    queryKey: reactionCatalogKey,
    queryFn: ({ signal }) => fetchReactionCatalog(signal),
    staleTime: Infinity,
    enabled,
  })
}

/**
 * The emojis and messages of one room. Live ones arrive as `room.reaction` on the player's own channel;
 * the room's history is read once (the table chat), and while the socket is down — and right after it
 * comes back — what was missed is fetched by sequence number. Every reaction is shown once: by id.
 *
 * `log` is the table chat (oldest first); `flights` are the reactions to animate now (remove them with
 * `landed` when their animation ends).
 */
export function useRoomReactions(roomId: string, enabled: boolean) {
  const { data: user } = useCurrentUser()
  const live = useRealtimeConnected()
  const [log, setLog] = useState<Reaction[]>([])
  const [flights, setFlights] = useState<Reaction[]>([])
  const seen = useRef(new Set<string>())
  const lastSeq = useRef<number | null>(null)
  const [loaded, setLoaded] = useState(false)

  const accept = useCallback(
    (items: Reaction[], animate: 'all' | 'latest' | 'none') => {
      const fresh = items.filter((r) => r.room === roomId && !seen.current.has(r.id))
      if (fresh.length === 0) return
      for (const r of fresh) {
        seen.current.add(r.id)
        lastSeq.current = Math.max(lastSeq.current ?? 0, r.seq)
      }
      setLog((prev) => [...prev, ...fresh].sort((a, b) => a.seq - b.seq).slice(-LOG_SIZE))
      if (animate === 'none') return
      const fly = animate === 'all' ? fresh : fresh.slice(-CATCH_UP_FLIGHTS)
      setFlights((prev) => [...prev, ...fly].slice(-12))
    },
    [roomId],
  )

  // The history: shown in the chat, not animated (it already happened).
  useEffect(() => {
    if (!enabled || loaded) return
    const controller = new AbortController()
    fetchRoomReactions(roomId, null, controller.signal)
      .then((items) => {
        accept(items, 'none')
        setLoaded(true)
      })
      .catch(() => undefined)
    return () => controller.abort()
  }, [enabled, loaded, roomId, accept])

  // Catch up: at once when the socket comes back, and every few seconds while it is down.
  useEffect(() => {
    if (!enabled || !loaded) return
    const controller = new AbortController()
    const catchUp = () =>
      void fetchRoomReactions(roomId, lastSeq.current, controller.signal)
        .then((items) => accept(items, 'latest'))
        .catch(() => undefined)
    catchUp()
    const timer = live ? undefined : setInterval(catchUp, POLL_MS)
    return () => {
      controller.abort()
      if (timer) clearInterval(timer)
    }
  }, [enabled, loaded, live, roomId, accept])

  useUserChannel(user?.id, {
    'room.reaction': (payload: Reaction) => {
      if (enabled) accept([payload], 'all')
    },
  })

  const send = useCallback(
    async (kind: ReactionKind, value: string, toSeat: number | null) => {
      accept([await sendReaction(roomId, kind, value, toSeat)], 'all')
    },
    [roomId, accept],
  )

  const landed = useCallback((id: string) => {
    setFlights((prev) => prev.filter((r) => r.id !== id))
  }, [])

  return { log, flights, send, landed }
}
