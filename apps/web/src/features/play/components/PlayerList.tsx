import { useTranslation } from 'react-i18next'
import { AiTag } from '@/shared/ui/AiTag'
import { Avatar } from '@/shared/ui/Avatar'
import { Badge } from '@/shared/ui/Badge'
import type { ParticipantResult, Room } from '../api'
import { resultLabel, resultTone } from '../labels'

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
}: {
  room: Room
  mySeat: number | null
  seatsToAct: number[]
  /** Chess clocks: seconds left per seat. */
  clocks?: (number | null)[] | null
}) {
  const { t } = useTranslation()
  const duel = room.participants.length === 2
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
        <Avatar
          url={player.avatar_url}
          name={player.name ?? '?'}
          size="md"
          level={player.level}
          frame={player.frame}
        />
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
            {player.result ? (
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
