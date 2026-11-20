import { useCountdown } from '../useCountdown'
import { TurnTimer } from './TurnTimer'
import { useTranslation } from 'react-i18next'
import { AiTag } from '@/shared/ui/AiTag'
import { seatTrigger } from '@/features/players/seatTrigger'
import { Avatar } from '@/shared/ui/Avatar'
import { Badge } from '@/shared/ui/Badge'
import type { ParticipantResult, Room } from '../api'
import { resultLabel, resultTone } from '../labels'
import { tableFaces } from '@/shared/lib/aiPortraits'

/** 4:05, 0:09 */
const formatClock = (seconds: number) =>
  `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`

type Player = Room['participants'][number]

/**
 * The players above a board that doesn't seat them itself (Nardi, Checkers, Chess, finished card
 * tables): a plate per seat with avatar, level, name, clock and result. Two players face each other
 * across a "vs" medal, you on the left; more players wrap in a row.
 */
export function PlayerList({
  room,
  mySeat,
  seatsToAct,
  clocks = null,
  turnTimer = null,
}: {
  room: Room
  mySeat: number | null
  seatsToAct: number[]
  /** Chess clocks: seconds left per seat. */
  clocks?: (number | null)[] | null
  /** A per-turn timer (no chess clock): seconds left for the seat to act, out of the turn's length. */
  turnTimer?: { seconds: number; total: number } | null
}) {
  const { t } = useTranslation()
  const duel = room.participants.length === 2
  const faces = tableFaces(
    // AI players, and seats AI took over (ADR 0028: the seat shows an AI face from then on).
    room.participants
      .filter((p) => p.is_bot || p.replaced)
      .map((p) => ({ seat: p.seat, name: p.name ?? '' })),
  )
  // You first: on the left of a duel.
  const players = [...room.participants].sort(
    (a, b) => Number(b.seat === mySeat) - Number(a.seat === mySeat) || a.seat - b.seat,
  )

  const plate = (player: Player, mirrored: boolean) => {
    const toAct = room.status === 'active' && seatsToAct.includes(player.seat)
    const clock = clocks?.[player.seat]

    return (
      <li
        key={player.seat}
        data-seat-anchor={player.seat}
        aria-current={toAct ? 'step' : undefined}
        className={`club-panel flex min-w-0 items-center gap-2.5 rounded-card p-2 transition-shadow duration-200 sm:gap-3 sm:p-2.5 ${
          mirrored ? 'flex-row-reverse text-right' : ''
        } ${toAct ? 'shadow-[0_0_0_2px_var(--color-turn),0_0_22px_rgb(242_193_78/0.3)]' : ''}`}
      >
        <span {...seatTrigger(player.seat, t('playerCard.open', { name: player.name ?? '?' }))}>
          <Avatar
            url={player.avatar_url}
            ai={player.is_bot || player.replaced}
            face={faces[player.seat]}
            name={player.name ?? '?'}
            size="md"
            level={player.level}
            frame={player.frame}
          />
        </span>
        <div className="min-w-0 flex-1">
          <p
            className="truncate text-sm font-semibold sm:text-base"
            title={player.username ? `@${player.username}` : undefined}
          >
            {player.name} {player.is_bot ? <AiTag className="align-middle" /> : null}
          </p>
          <div
            className={`mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-muted ${mirrored ? 'justify-end' : ''}`}
          >
            {clock != null ? (
              <span
                className={`rounded-full px-2.5 py-0.5 font-mono text-base font-bold tabular-nums ${toAct ? 'bg-turn text-ink' : 'bg-emerald-900 text-fg'}`}
                aria-label={t('room.clock', { time: formatClock(clock) })}
              >
                {formatClock(clock)}
              </span>
            ) : null}
            {player.result && !player.replaced ? (
              // A long result word in a narrow plate (a phone) truncates instead of spilling over.
              <Badge
                tone={resultTone[player.result as ParticipantResult]}
                className="max-w-full min-w-0"
              >
                <span className="truncate">
                  {t(resultLabel[player.result as ParticipantResult])}
                </span>
              </Badge>
            ) : player.seat === mySeat ? (
              <span>{t('room.youSeat')}</span>
            ) : null}
            {player.return_until ? <ReturnCountdown until={player.return_until} /> : null}
            {player.replaced ? (
              // The seat plays on with AI: one short line, no result badge (ADR 0028).
              <span className="inline-flex min-w-0 items-center gap-1 font-semibold text-accent-text">
                <AiTag />
                <span className="truncate">{t('room.seat.replaced')}</span>
              </span>
            ) : null}
            {toAct && clock == null && turnTimer ? (
              <TurnTimer
                seconds={turnTimer.seconds}
                total={turnTimer.total}
                mine={player.seat === mySeat}
              />
            ) : null}
            {toAct && clock == null ? (
              <span className="inline-flex items-center gap-1 font-semibold text-turn">
                <span
                  aria-hidden="true"
                  className="size-2 rounded-full bg-turn motion-safe:animate-pulse"
                />
                {t('room.toMove')}
              </span>
            ) : null}
          </div>
        </div>
      </li>
    )
  }

  if (duel) {
    const [left, right] = players as [Player, Player]
    return (
      <ul
        className="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 sm:gap-3"
        aria-label={t('room.players')}
      >
        {plate(left, false)}
        <li aria-hidden="true" className="list-none">
          <span className="club-medal flex size-8 items-center justify-center rounded-full text-xs font-extrabold text-ink sm:size-10 sm:text-sm">
            {t('room.versus')}
          </span>
        </li>
        {plate(right, true)}
      </ul>
    )
  }

  return (
    <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3" aria-label={t('room.players')}>
      {players.map((player) => plate(player, false))}
    </ul>
  )
}

/** A disconnected player's return window, counting down on their plate (ADR 0028). */
function ReturnCountdown({ until }: { until: string }) {
  const { t } = useTranslation()
  const seconds = useCountdown(until, null)
  if (seconds === null) return null
  return (
    <span
      role="status"
      className="inline-flex items-center gap-1 font-semibold text-danger tabular-nums"
    >
      <span
        aria-hidden="true"
        className="size-2 rounded-full bg-danger motion-safe:animate-pulse"
      />
      {t('room.seat.returning', { count: seconds })}
    </span>
  )
}
