import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronIcon } from '@/shared/ui/icons'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import type { Reaction, ReactionKind } from '../api'
import { messageText } from '../labels'
import { setMuted, useReactionsMuted } from '../prefs'

/** One tap from the chat itself; the rest are in the picker. */
const QUICK = ['good_luck', 'nice_move', 'gg'] as const

/**
 * The table chat, a slim bar between the room's header and the board: a few emojis and phrases sent with one
 * tap, the pickers (＋ every emoji, 💬 every message), the latest line and a count of new ones; opened, the
 * chat as bubbles (yours on the right), the phrases and muting the others' animations. No typing: only what
 * the catalog offers.
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
  const muted = useReactionsMuted()
  const [open, setOpen] = useState(false)
  // Lines seen when the chat was last open: the rest are new (the count on the chat button).
  const [seen, setSeen] = useState(log.length)
  const list = useRef<HTMLOListElement>(null)
  const time = new Intl.DateTimeFormat(i18n.language, { hour: '2-digit', minute: '2-digit' })
  const who = (seat: number) => (seat === mySeat ? t('reactions.you') : (names[seat] ?? '?'))
  const unread = open ? 0 : Math.max(0, log.length - seen)
  const latest = log.at(-1)
  if (open && seen !== log.length) setSeen(log.length)

  // Opened over the table: Escape closes it.
  useEffect(() => {
    if (!open) return
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false)
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [open])

  // Newest at the bottom, in view.
  useEffect(() => {
    const el = list.current
    if (el) el.scrollTop = el.scrollHeight
  }, [log.length, open])

  const round =
    'flex size-10 shrink-0 items-center justify-center rounded-full transition active:scale-90 motion-safe:hover:-translate-y-0.5'

  return (
    // The bar keeps its place; opened, the chat lies over the table instead of pushing it down.
    <div className="relative min-h-[3.25rem]">
      <section
        aria-label={t('reactions.chat.title')}
        className={`club-frame rounded-2xl p-1.5 ${open ? 'absolute inset-x-0 top-0 z-30 shadow-2xl shadow-black/60' : ''}`}
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
          <div className="hidden min-w-0 items-center gap-1 lg:flex">
            {open
              ? null
              : QUICK.map((key) => (
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

          {/* The latest line, small (closed); the whole chat below when open. */}
          <div className="min-w-0 flex-1">
            {!open && latest ? (
              <button
                type="button"
                onClick={() => setOpen(true)}
                className="flex w-full min-w-0 items-center gap-1.5 rounded-full bg-black/30 px-3 py-1.5 text-left text-sm"
              >
                <span
                  className={`shrink-0 font-bold ${latest.from_seat === mySeat ? 'text-accent-text' : 'text-gold-light'}`}
                >
                  {who(latest.from_seat)}
                </span>
                <span className="min-w-0 truncate text-fg/90">
                  {latest.kind === 'emoji' ? latest.value : messageText(t, latest.value)}
                </span>
              </button>
            ) : null}
          </div>

          <button
            type="button"
            aria-expanded={open}
            aria-controls="table-chat-more"
            aria-label={t('reactions.chat.title')}
            onClick={() => setOpen(!open)}
            className={`${round} relative border border-border text-muted hover:border-gold/50 hover:text-white`}
          >
            <ChevronIcon
              className={`size-5 transition-transform ${open ? '-rotate-90' : 'rotate-90'}`}
            />
            {unread > 0 ? (
              <span className="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-[#e5584b] px-1 text-[0.65rem] font-extrabold text-white">
                <span className="sr-only">{t('reactions.chat.unread', { count: unread })}</span>
                <span aria-hidden="true">{unread > 9 ? '9+' : unread}</span>
              </span>
            ) : null}
          </button>
        </div>

        {/* The log: hidden while closed (the latest line is in the bar), as chat bubbles when open. */}
        <ol
          ref={list}
          role="log"
          aria-live="polite"
          aria-label={t('reactions.chat.title')}
          className={
            open
              ? 'mt-1.5 max-h-52 min-h-14 space-y-1.5 overflow-y-auto overscroll-contain rounded-xl bg-black/30 p-2 text-sm'
              : 'sr-only'
          }
        >
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

        {open ? (
          <div id="table-chat-more" className="mt-1.5 flex flex-wrap items-center gap-1.5">
            {QUICK.map((key) => (
              <button
                key={key}
                type="button"
                onClick={() => onQuick(key, quickTo)}
                className="min-h-10 rounded-full border border-gold/25 bg-black/25 px-3.5 text-sm font-semibold text-fg/85 hover:border-gold/60 hover:text-white"
              >
                {messageText(t, key)}
              </button>
            ))}
            <button
              type="button"
              aria-pressed={muted}
              onClick={() => setMuted(!muted)}
              className={`ml-auto min-h-10 rounded-full border px-3.5 text-sm font-semibold ${
                muted
                  ? 'border-gold bg-gold/15 text-gold-light'
                  : 'border-border text-muted hover:border-gold/50 hover:text-white'
              }`}
            >
              {t('reactions.chat.mute')}
            </button>
          </div>
        ) : null}

        {error ? (
          <p role="alert" className="mt-1.5 px-1 text-xs text-danger">
            {error}
          </p>
        ) : null}
      </section>
    </div>
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
