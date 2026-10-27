import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import type { ParticipantResult, Room } from '../api'
import { resultLabel, statusLabel } from '../labels'

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

  return (
    <Link
      to={`/rooms/${room.id}`}
      className="flex items-center justify-between gap-3 rounded-xl border border-border bg-surface px-4 py-3 hover:bg-surface-raised"
    >
      <span className="min-w-0">
        <span className="block truncate font-medium">
          {room.game.name} · {room.mode.name}
        </span>
        <span className="block truncate text-sm text-muted">
          {t('history.against', { names: opponents || '—' })}
        </span>
      </span>
      <span className="shrink-0 text-right text-sm">
        <span className="block font-medium">
          {me?.result
            ? t(resultLabel[me.result as ParticipantResult])
            : t(statusLabel[room.status])}
        </span>
        <span className="block text-muted">
          {new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(new Date(when))}
        </span>
      </span>
    </Link>
  )
}
