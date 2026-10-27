import { useTranslation } from 'react-i18next'
import { Avatar } from '@/shared/ui/Avatar'
import { Badge } from '@/shared/ui/Badge'
import type { ParticipantResult, Room } from '../api'
import { resultLabel } from '../labels'

export function PlayerList({
  room,
  mySeat,
  seatsToAct,
}: {
  room: Room
  mySeat: number | null
  seatsToAct: number[]
}) {
  const { t } = useTranslation()

  return (
    <ul className="grid gap-3 sm:grid-cols-2" aria-label={t('room.players')}>
      {room.participants.map((player) => {
        const toAct = room.status === 'active' && seatsToAct.includes(player.seat)

        return (
          <li
            key={player.seat}
            className={`flex items-center gap-3 rounded-xl border bg-surface p-3 ${toAct ? 'border-accent-text' : 'border-border'}`}
            aria-current={toAct ? 'step' : undefined}
          >
            <Avatar url={player.avatar_url} name={player.name ?? '?'} />
            <div className="min-w-0 flex-1">
              <p className="flex items-center gap-2 truncate font-medium">
                {player.name}
                {player.is_bot ? <Badge>{t('room.bot')}</Badge> : null}
                {player.seat === mySeat ? (
                  <span className="text-sm text-muted">({t('room.you')})</span>
                ) : null}
              </p>
              {player.username ? (
                <p className="truncate text-sm text-muted">@{player.username}</p>
              ) : null}
            </div>
            {player.result ? (
              <Badge tone={player.result === 'win' ? 'neutral' : 'warning'}>
                {t(resultLabel[player.result as ParticipantResult])}
              </Badge>
            ) : null}
          </li>
        )
      })}
    </ul>
  )
}
