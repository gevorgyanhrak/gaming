import { useTranslation } from 'react-i18next'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import type { Reaction, ReactionKind } from '../api'
import { messageText } from '../labels'

/** One tap from the chat itself; the rest are in the picker. */
const QUICK = ['good_luck', 'nice_move', 'gg'] as const

/**
 * The table chat, a slim bar between the room's header and the board: a few emojis and phrases sent with one
 * tap and the pickers (＋ every emoji, 💬 every message). What arrives flies to the seats; the lines are kept
 * in a visually hidden live log for screen readers. No typing: only what the catalog offers.
 */
export function TableChat({
  log,
  mySeat,
  names,
  levels = {},
  quickTo,
  quickEmojis = [],
  error,
  onOpen,
  onQuick,
  onEmoji,
}: {
  log: Reaction[]
  mySeat: number | null
  names: Partial<Record<number, string>>
  /** Levels by seat: the sender's badge in front of each line. */
  levels?: Partial<Record<number, number>>
  /** Who the one-tap messages are for (the opponent, or everyone at a bigger table). */
  quickTo: number | null
  /** A few everyday emojis from the catalog, sent with one tap. */
  quickEmojis?: string[]
  error: string | null
  onOpen: (tab: ReactionKind) => void
  onQuick: (key: string, toSeat: number | null) => void
  onEmoji?: (emoji: string, toSeat: number | null) => void
}) {
  const { t, i18n } = useTranslation()
  const time = new Intl.DateTimeFormat(i18n.language, { hour: '2-digit', minute: '2-digit' })
  const who = (seat: number) => (seat === mySeat ? t('reactions.you') : (names[seat] ?? '?'))

  const round =
    'flex size-10 shrink-0 items-center justify-center rounded-full transition active:scale-90 motion-safe:hover:-translate-y-0.5'

  return (
    <section
      aria-label={t('reactions.chat.title')}
      className="club-frame rounded-2xl p-1.5"
      data-table-chat=""
    >
      <div className="flex items-center gap-1">
        {/* One tap: an everyday emoji to the table; ＋ opens every emoji. */}
        <div className="flex shrink-0 items-center gap-0.5">
          {quickEmojis.map((emoji) => (
            <button
              key={emoji}
              type="button"
              onClick={() => onEmoji?.(emoji, quickTo)}
              aria-label={t('reactions.sendEmoji', { emoji })}
              className={`${round} text-xl hover:bg-white/10`}
            >
              <span aria-hidden="true">{emoji}</span>
            </button>
          ))}
          <button
            type="button"
            onClick={() => onOpen('emoji')}
            aria-label={t('reactions.emojiButton')}
            title={t('reactions.emojiButton')}
            className={`${round} bg-linear-to-b from-gold-light via-gold to-gold-dark text-lg font-black text-[#2a1b0c] shadow-gold/20 shadow-lg`}
          >
            {quickEmojis.length > 0 ? '+' : <span aria-hidden="true">😊</span>}
          </button>
        </div>

        {/* Quick phrases in the bar where there's room; 💬 opens them all. */}
        <div className="hidden min-w-0 items-center gap-1 md:flex">
          {QUICK.map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => onQuick(key, quickTo)}
              className="min-h-9 shrink-0 rounded-full border border-gold/25 bg-black/25 px-3 text-xs font-semibold text-fg/85 transition hover:border-gold/60 hover:text-white"
            >
              {messageText(t, key)}
            </button>
          ))}
        </div>
        <button
          type="button"
          onClick={() => onOpen('message')}
          aria-label={t('reactions.messagesButton')}
          title={t('reactions.messagesButton')}
          className={`${round} border border-gold/30 bg-black/30 text-lg hover:border-gold`}
        >
          <span aria-hidden="true">💬</span>
        </button>
      </div>

      {/* The log, for screen readers: what is said at the table. */}
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
        <p role="alert" className="mt-1.5 px-1 text-xs text-danger">
          {error}
        </p>
      ) : null}
    </section>
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
