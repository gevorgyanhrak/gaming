import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, type CSSProperties } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { useUserChannel } from '@/shared/realtime/useUserChannel'
import { Avatar } from '@/shared/ui/Avatar'
import { Button } from '@/shared/ui/Button'
import { CardBack } from '@/games/cards/PlayingCard'
import { cancelMatchmaking, fetchMatchmaking, matchmakingHeartbeat, matchmakingKey } from '../api'
import { useCountdown } from '../useCountdown'

/**
 * While searching: who's waiting and cancel. Goes to the room once matched (event, or the polled status as a
 * fallback). Sends a heartbeat so abandoned searches are dropped. When people can't be found in time the
 * server fills the seats with AI; nothing here tells the player so. Drawn as a small table whose seats fill
 * with the players found, the countdown in the middle.
 */
export function MatchmakingStatus({
  seatsFor = () => 2,
}: {
  seatsFor?: (modeId: number) => number
}) {
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
  // Seats at the table: the table formed, or the mode's size until then (you take the bottom one).
  const count = Math.max(
    2,
    Math.min(6, table?.length ?? (ticket ? seatsFor(ticket.game_mode_id) : 2)),
  )
  const me = table?.findIndex((p) => p.name === user?.username) ?? -1
  const players = Array.from({ length: count }, (_, i) =>
    table
      ? (table[(i + Math.max(0, me)) % table.length] ?? null)
      : i === 0 && user
        ? { name: user.username, avatar_url: user.profile.avatar_url, level: user.level }
        : null,
  )
  return (
    <div
      className="club-panel relative overflow-hidden rounded-3xl p-4 sm:p-5"
      role="status"
      aria-label={table ? t('play.tableReady') : t('play.searching')}
    >
      {/* A small felt table: seats fill with players as they're found; the countdown in the middle. */}
      <div className="relative mx-auto aspect-[16/10] w-full max-w-md">
        <div aria-hidden="true" className="table-rim absolute inset-[9%_7%] rounded-[50%]">
          <div className="table-felt h-full w-full rounded-[50%]" />
        </div>

        <div className="absolute top-1/2 left-1/2 grid size-24 -translate-x-1/2 -translate-y-1/2 place-items-center sm:size-28">
          {/* The fan being shuffled behind the countdown. */}
          {table
            ? null
            : [-14, 0, 14].map((fan, i) => (
                <span
                  key={fan}
                  aria-hidden="true"
                  className="absolute top-1/2 left-1/2 motion-safe:animate-shuffle"
                  style={
                    {
                      '--fan': `${fan}deg`,
                      transform: `translate(-50%, -50%) rotate(${fan}deg)`,
                      animationDelay: `${i * 300}ms`,
                    } as CSSProperties
                  }
                >
                  <CardBack size="sm" />
                </span>
              ))}
          <svg viewBox="0 0 36 36" className="absolute inset-0 -rotate-90" aria-hidden="true">
            <circle
              cx="18"
              cy="18"
              r="15.5"
              className="fill-black/55 stroke-gold/25"
              strokeWidth="2.5"
            />
            {share !== null ? (
              <circle
                cx="18"
                cy="18"
                r="15.5"
                fill="none"
                className="stroke-gold transition-[stroke-dashoffset] duration-500 ease-linear"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeDasharray={97.4}
                strokeDashoffset={97.4 * (1 - share)}
              />
            ) : null}
          </svg>
          <span className="relative text-3xl font-extrabold text-gold-light tabular-nums drop-shadow sm:text-4xl">
            {left ?? '…'}
          </span>
        </div>

        <ul aria-label={t('play.tablePlayers')}>
          {players.map((player, i) => {
            const angle = Math.PI / 2 + (i * 2 * Math.PI) / count
            return (
              <li
                key={i}
                className="absolute flex w-20 -translate-x-1/2 -translate-y-1/2 flex-col items-center gap-0.5"
                style={{
                  left: `${50 + 45 * Math.cos(angle)}%`,
                  top: `${50 + 42 * Math.sin(angle)}%`,
                }}
              >
                {player ? (
                  <span
                    className="contents motion-safe:animate-rise"
                    style={{ animationDelay: `${i * 120}ms` }}
                  >
                    <span className="rounded-full shadow-[0_6px_16px_rgb(0_0_0/0.55)] ring-2 ring-gold">
                      <Avatar
                        url={player.avatar_url}
                        name={player.name}
                        level={player.level}
                        size="sm"
                      />
                    </span>
                    <span className="max-w-20 truncate rounded-md bg-black/60 px-1.5 text-[0.7rem] font-semibold">
                      {player.name}
                    </span>
                  </span>
                ) : (
                  <>
                    <span
                      aria-hidden="true"
                      className="grid size-10 place-items-center rounded-full border-2 border-dashed border-gold/50 bg-black/40 text-gold-light motion-safe:animate-seat-search"
                      style={{ animationDelay: `${i * 200}ms` }}
                    >
                      ?
                    </span>
                    <span className="sr-only">{t('play.seatSearching')}</span>
                  </>
                )}
              </li>
            )
          })}
        </ul>
      </div>

      <div className="mt-2 space-y-0.5 text-center">
        <p className="font-semibold">{table ? t('play.tableReady') : t('play.searching')}</p>
        {left !== null ? (
          <p className="text-sm text-muted">{t('play.startsIn', { count: left })}</p>
        ) : null}
        {!table && (status.data?.waiting ?? 0) > 1 ? (
          <p className="text-sm">{t('play.tableWaiting', { count: status.data?.waiting ?? 0 })}</p>
        ) : null}
      </div>
      <div className="mt-3 flex justify-center">
        <Button variant="secondary" onClick={() => cancel.mutate()} loading={cancel.isPending}>
          {t('play.cancelSearch')}
        </Button>
      </div>
    </div>
  )
}
