import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { useUserChannel } from '@/shared/realtime/useUserChannel'
import { Avatar } from '@/shared/ui/Avatar'
import { Button } from '@/shared/ui/Button'
import { cancelMatchmaking, fetchMatchmaking, matchmakingHeartbeat, matchmakingKey } from '../api'
import { useCountdown } from '../useCountdown'

/**
 * While searching: who's waiting and cancel. Goes to the room once matched (event, or the polled status as a
 * fallback). Sends a heartbeat so abandoned searches are dropped. When people can't be found in time the
 * server fills the seats with AI; nothing here tells the player so.
 */
export function MatchmakingStatus() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()

  const status = useQuery({
    queryKey: matchmakingKey,
    queryFn: ({ signal }) => fetchMatchmaking(signal),
    refetchInterval: 5000,
  })
  const ticket = status.data?.ticket ?? null
  const room = status.data?.room ?? null
  const table = status.data?.table ?? null
  const startsAt = ticket ? status.data?.starts_at : null
  const left = useCountdown(startsAt, null)
  // The whole countdown: from joining the queue to the start.
  const total =
    ticket && startsAt
      ? Math.max(1, (new Date(startsAt).getTime() - new Date(ticket.joined_at).getTime()) / 1000)
      : null

  useEffect(() => {
    if (room) void navigate(`/rooms/${room}`)
  }, [room, navigate])

  useEffect(() => {
    if (!ticket) return
    const timer = setInterval(() => void matchmakingHeartbeat().catch(() => undefined), 15_000)
    return () => clearInterval(timer)
  }, [ticket])

  useUserChannel(user?.id, {
    'matchmaking.matched': (payload: { room: string }) => void navigate(`/rooms/${payload.room}`),
    // The table is being prepared: fetch who sits at it.
    'matchmaking.table': () => void queryClient.invalidateQueries({ queryKey: matchmakingKey }),
  })

  const cancel = useMutation({
    mutationFn: cancelMatchmaking,
    onSuccess: () => queryClient.setQueryData(matchmakingKey, { ticket: null, room: null }),
  })

  if (!ticket) return null

  const share = left !== null && total ? Math.min(1, left / total) : null
  return (
    <div className="space-y-4 rounded-2xl border border-accent/40 bg-accent/10 p-4" role="status">
      <div className="flex items-center gap-4">
        {/* The countdown in a radar: rings pulse out while the search goes on. */}
        <div className="relative grid size-20 shrink-0 place-items-center">
          {table ? null : (
            <>
              <span className="absolute inset-0 rounded-full border-2 border-accent/50 motion-safe:animate-ping" />
              <span className="absolute inset-2 rounded-full border border-accent/40 [animation-delay:400ms] motion-safe:animate-ping" />
            </>
          )}
          <svg viewBox="0 0 36 36" className="absolute inset-0 -rotate-90" aria-hidden="true">
            <circle
              cx="18"
              cy="18"
              r="16"
              fill="none"
              className="stroke-white/10"
              strokeWidth="3"
            />
            {share !== null ? (
              <circle
                cx="18"
                cy="18"
                r="16"
                fill="none"
                className="stroke-accent transition-[stroke-dashoffset] duration-500 ease-linear"
                strokeWidth="3"
                strokeLinecap="round"
                strokeDasharray={100.5}
                strokeDashoffset={100.5 * (1 - share)}
              />
            ) : null}
          </svg>
          <span className="text-2xl font-extrabold text-gold-light tabular-nums">
            {left ?? '…'}
          </span>
        </div>
        <div className="min-w-0 space-y-1">
          <p className="font-semibold">{table ? t('play.tableReady') : t('play.searching')}</p>
          {left !== null ? (
            <p className="text-sm text-muted">{t('play.startsIn', { count: left })}</p>
          ) : null}
          {!table && (status.data?.waiting ?? 0) > 1 ? (
            <p className="text-sm">
              {t('play.tableWaiting', { count: status.data?.waiting ?? 0 })}
            </p>
          ) : null}
        </div>
      </div>
      {table ? (
        <ul aria-label={t('play.tablePlayers')} className="flex flex-wrap gap-3">
          {table.map((player, i) => (
            <li
              key={`${player.name}-${i}`}
              className="flex flex-col items-center gap-1 motion-safe:animate-rise"
              style={{ animationDelay: `${i * 120}ms` }}
            >
              <Avatar url={player.avatar_url} name={player.name} level={player.level} size="sm" />
              <span className="max-w-20 truncate text-xs font-semibold">{player.name}</span>
            </li>
          ))}
        </ul>
      ) : null}
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" onClick={() => cancel.mutate()} loading={cancel.isPending}>
          {t('play.cancelSearch')}
        </Button>
      </div>
    </div>
  )
}
