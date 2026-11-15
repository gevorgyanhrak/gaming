import { useTranslation } from 'react-i18next'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import type { Reaction } from '../api'
import { messageText } from '../labels'

/**
 * The table's reactions as text, for screen readers: a visually hidden live log of what was sent (the flights over
 * the avatars show it to everyone else), and an error when a send failed. Sending starts from a player's avatar.
 */
export function ReactionLog({
  log,
  mySeat,
  names,
  levels = {},
  error,
}: {
  log: Reaction[]
  mySeat: number | null
  names: Partial<Record<number, string>>
  /** Levels by seat: the sender's badge in front of each line. */
  levels?: Partial<Record<number, number>>
  error: string | null
}) {
  const { t, i18n } = useTranslation()
  const time = new Intl.DateTimeFormat(i18n.language, { hour: '2-digit', minute: '2-digit' })
  const who = (seat: number) => (seat === mySeat ? t('reactions.you') : (names[seat] ?? '?'))

  return (
    <>
      <ol role="log" aria-live="polite" aria-label={t('reactions.chat.title')} className="sr-only">
        {log.length === 0 ? (
          <li className="py-2 text-center text-muted">{t('reactions.chat.empty')}</li>
        ) : (
          log.map((r) => (
            <Line
              key={r.id}
              reaction={r}
              who={who}
              level={levels[r.from_seat]}
              time={time}
              mine={r.from_seat === mySeat}
            />
          ))
        )}
      </ol>

      {error ? (
        <p
          role="alert"
          className="fixed inset-x-4 bottom-20 z-50 mx-auto max-w-sm rounded-xl border border-danger/50 bg-black/90 px-3 py-2 text-center text-sm text-danger shadow-lg"
        >
          {error}
        </p>
      ) : null}
    </>
  )
}

function Line({
  reaction: r,
  who,
  level,
  time,
  mine,
}: {
  reaction: Reaction
  who: (seat: number) => string
  level: number | undefined
  time: Intl.DateTimeFormat
  mine: boolean
}) {
  const { t } = useTranslation()
  return (
    // A chat bubble: yours on the right.
    <li className={`flex ${mine ? 'justify-end' : 'justify-start'}`}>
      <span
        className={`max-w-[85%] rounded-2xl px-3 py-1.5 ${mine ? 'rounded-br-md bg-accent/25' : 'rounded-bl-md bg-white/10'}`}
      >
        <span className="flex items-center gap-1.5 text-xs">
          {level !== undefined ? <LevelBadge level={level} size="sm" /> : null}
          <span className={`font-bold ${mine ? 'text-accent-text' : 'text-gold-light'}`}>
            {who(r.from_seat)}
          </span>
          {r.to_seat !== null ? <span className="text-muted">→ {who(r.to_seat)}</span> : null}
          <time dateTime={r.sent_at} className="ml-auto pl-2 text-muted tabular-nums">
            {time.format(new Date(r.sent_at))}
          </time>
        </span>
        <span className="block">
          {r.kind === 'emoji' ? (
            <span className="text-2xl leading-none">{r.value}</span>
          ) : (
            messageText(t, r.value)
          )}
        </span>
      </span>
    </li>
  )
}
