import { useTranslation } from 'react-i18next'
import { Avatar } from '@/shared/ui/Avatar'
import { Badge } from '@/shared/ui/Badge'
import type { ParticipantResult, Room } from '../api'
import { resultLabel } from '../labels'

/** 4:05, 0:09 */
const formatClock = (seconds: number) =>
  `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`

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

  return (
    <ul className="flex flex-wrap gap-2" aria-label={t('room.players')}>
      {room.participants.map((player) => {
        const toAct = room.status === 'active' && seatsToAct.includes(player.seat)

        return (
          <li
            key={player.seat}
            className={`flex min-w-0 items-center gap-2 rounded-full border bg-surface/80 py-1 pr-1.5 pl-1 transition-colors ${
              toAct ? 'border-gold shadow-[0_0_0_3px_rgb(214_171_78/0.2)]' : 'border-border'
            }`}
            aria-current={toAct ? 'step' : undefined}
          >
            <Avatar url={player.avatar_url} name={player.name ?? '?'} size="sm" />
            <span
              className="min-w-0 truncate text-sm font-medium"
              title={player.username ? `@${player.username}` : undefined}
            >
              {player.name}
            </span>
            {player.seat === mySeat ? (
              <span className="text-xs text-muted">({t('room.you')})</span>
            ) : null}
            {player.is_bot ? <Badge>{t('room.bot')}</Badge> : null}
            {clocks && clocks[player.seat] != null ? (
              <span
                className={`rounded-full px-2.5 py-0.5 font-mono text-base font-bold tabular-nums ${toAct ? 'bg-gold text-black' : 'bg-bg text-muted'}`}
                aria-label={t('room.clock', { time: formatClock(clocks[player.seat] ?? 0) })}
              >
                {formatClock(clocks[player.seat] ?? 0)}
              </span>
            ) : null}
            {player.result ? (
              <Badge tone={player.result === 'win' ? 'neutral' : 'warning'}>
                {t(resultLabel[player.result as ParticipantResult])}
              </Badge>
            ) : null}
            <span aria-hidden="true" className="w-1" />
          </li>
        )
      })}
    </ul>
  )
}
