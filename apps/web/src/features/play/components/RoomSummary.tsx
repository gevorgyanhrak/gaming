import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { Badge } from '@/shared/ui/Badge'
import type { ParticipantResult, Room } from '../api'
import { endReasonShort, resultLabel, resultTone, statusLabel } from '../labels'

/** One line per room: game, opponents and (for finished games) my result. */
export function RoomSummary({ room }: { room: Room }) {
  const { t, i18n } = useTranslation()
  const { data: user } = useCurrentUser()
  const me = room.participants.find((p) => p.username === user?.username)
  const opponents = room.participants
    .filter((p) => p !== me)
    .map((p) => p.name)
    .join(', ')
  const when = room.finished_at ?? room.started_at ?? room.created_at

  const result = (me?.result ?? null) as ParticipantResult | null
  const reasonKey = result && room.end_reason ? endReasonShort[room.end_reason] : undefined

  return (
    <Link
      to={`/rooms/${room.id}`}
      className="flex min-h-14 items-center gap-3 rounded-xl border border-border bg-surface px-4 py-3 transition-colors hover:bg-surface-raised"
    >
      <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gold/15 text-gold">
        <GameIcon slug={room.game.slug} name={room.game.name} className="size-5" />
      </span>
      <span className="min-w-0 flex-1">
        <span className="block truncate font-medium">
          {room.game.name} · {room.mode.name}
        </span>
        <span className="flex text-sm text-muted">
          <span className="truncate">{t('history.against', { names: opponents || '—' })}</span>
          <span className="shrink-0">
            &nbsp;·{' '}
            {new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(new Date(when))}
          </span>
        </span>
      </span>
      <span className="flex shrink-0 flex-col items-end gap-1 text-right">
        {result ? (
          <Badge tone={resultTone[result]}>{t(resultLabel[result])}</Badge>
        ) : (
          <Badge>{t(statusLabel[room.status])}</Badge>
        )}
        {reasonKey ? <span className="max-w-32 text-xs text-muted">{t(reasonKey)}</span> : null}
      </span>
    </Link>
  )
}
