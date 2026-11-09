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
 * The table chat, a bar between the room's header and the board so it is in view while playing: the
 * picker buttons and the latest line; opened, the whole log (oldest at the top), a few one-tap
 * messages and muting the others' animations. There is no typing: only what the catalog offers.
 */
export function TableChat({
  log,
  mySeat,
  names,
  levels = {},
  quickTo,
  error,
  onOpen,
  onQuick,
}: {
  log: Reaction[]
  mySeat: number | null
  names: Partial<Record<number, string>>
  /** Levels by seat: the sender's badge in front of each line. */
  levels?: Partial<Record<number, number>>
  /** Who the one-tap messages are for (the opponent, or everyone at a bigger table). */
  quickTo: number | null
  error: string | null
  onOpen: (tab: ReactionKind) => void
  onQuick: (key: string, toSeat: number | null) => void
}) {
  const { t, i18n } = useTranslation()
  const muted = useReactionsMuted()
  const [open, setOpen] = useState(false)
  const list = useRef<HTMLOListElement>(null)
  const time = new Intl.DateTimeFormat(i18n.language, { hour: '2-digit', minute: '2-digit' })
  const who = (seat: number) => (seat === mySeat ? t('reactions.you') : (names[seat] ?? '?'))

  // Opened over the table: Escape closes it.
  useEffect(() => {
    if (!open) return
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false)
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [open])

  // Newest at the bottom, in view (closed, the log shows just that line).
  useEffect(() => {
    const el = list.current
    if (el) el.scrollTop = el.scrollHeight
  }, [log.length, open])

  return (
    // The bar keeps its place; opened, the chat lies over the table instead of pushing it down.
    <div className="relative min-h-[3.75rem]">
      <section
        aria-label={t('reactions.chat.title')}
        className={`club-frame rounded-2xl p-2 ${open ? 'absolute inset-x-0 top-0 z-30 shadow-2xl shadow-black/60' : ''}`}
        data-table-chat=""
      >
        <div className="flex flex-wrap items-center gap-1.5">
          <button
            type="button"
            onClick={() => onOpen('emoji')}
            aria-label={t('reactions.emojiButton')}
            title={t('reactions.emojiButton')}
            className="flex size-11 shrink-0 items-center justify-center rounded-full bg-linear-to-b from-gold-light via-gold to-gold-dark text-2xl shadow-gold/20 shadow-lg transition hover:brightness-110 active:scale-95"
          >
            <span aria-hidden="true">😊</span>
          </button>
          <button
            type="button"
            onClick={() => onOpen('message')}
            aria-label={t('reactions.messagesButton')}
            className="flex min-h-11 min-w-11 shrink-0 items-center justify-center gap-1.5 rounded-full border border-gold/40 bg-black/30 px-3 text-sm font-semibold hover:border-gold"
          >
            <span aria-hidden="true" className="text-lg">
              💬
            </span>
            <span className="hidden sm:inline">{t('reactions.messagesButton')}</span>
          </button>

          <ol
            ref={list}
            role="log"
            aria-live="polite"
            aria-label={t('reactions.chat.title')}
            className={`min-w-0 overflow-y-auto overscroll-contain rounded-xl bg-black/30 px-3 text-sm ${
              open
                ? 'order-last max-h-44 min-h-14 basis-full space-y-1 py-2'
                : 'h-11 flex-1 overflow-hidden leading-[2.75rem]'
            }`}
          >
            {log.length === 0 ? (
              <li className={open ? 'py-2 text-center text-muted' : 'truncate text-muted'}>
                {t('reactions.chat.empty')}
              </li>
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
          {open ? <span className="flex-1" /> : null}

          <button
            type="button"
            aria-expanded={open}
            aria-controls="table-chat-more"
            aria-label={t('reactions.chat.title')}
            onClick={() => setOpen(!open)}
            className="flex size-11 shrink-0 items-center justify-center rounded-full border border-border text-muted hover:border-gold/50 hover:text-white"
          >
            <ChevronIcon
              className={`size-5 transition-transform ${open ? '-rotate-90' : 'rotate-90'}`}
            />
          </button>
        </div>

        {open ? (
          <div id="table-chat-more" className="mt-2 flex flex-wrap items-center gap-1.5">
            {QUICK.map((key) => (
              <button
                key={key}
                type="button"
                onClick={() => onQuick(key, quickTo)}
                className="min-h-11 rounded-full border border-border bg-black/20 px-3.5 text-sm font-semibold text-muted hover:border-gold/50 hover:text-white"
              >
                {messageText(t, key)}
              </button>
            ))}
            <button
              type="button"
              aria-pressed={muted}
              onClick={() => setMuted(!muted)}
              className={`ml-auto min-h-11 rounded-full border px-3.5 text-sm font-semibold ${
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
    <li className="flex min-w-0 items-baseline gap-2">
      <time dateTime={r.sent_at} className="shrink-0 text-xs text-muted tabular-nums">
        {time.format(new Date(r.sent_at))}
      </time>
      <span className="min-w-0 truncate">
        {level !== undefined ? (
          <LevelBadge level={level} size="sm" className="mr-1 align-[1px]" />
        ) : null}
        <span className={`font-bold ${mine ? 'text-accent-text' : 'text-gold-light'}`}>
          {who(r.from_seat)}
        </span>
        {r.to_seat !== null ? <span className="text-muted"> → {who(r.to_seat)}</span> : null}
        <span className="text-muted">: </span>
        {r.kind === 'emoji' ? (
          <span className="text-lg leading-none">{r.value}</span>
        ) : (
          <span>{messageText(t, r.value)}</span>
        )}
      </span>
    </li>
  )
}
