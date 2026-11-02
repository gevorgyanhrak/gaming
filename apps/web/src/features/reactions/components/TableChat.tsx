import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import type { Reaction, ReactionKind } from '../api'
import { messageText } from '../labels'
import { setMuted, useReactionsMuted } from '../prefs'

/** One tap from the chat itself; the rest are in the picker. */
const QUICK = ['good_luck', 'nice_move', 'gg'] as const

/**
 * The table chat under the board: who sent what to whom (emojis and predefined messages, oldest at the
 * top), the buttons that open the picker, a few one-tap messages, and muting the others' animations.
 * There is no typing: only what the catalog offers can be sent.
 */
export function TableChat({
  log,
  mySeat,
  names,
  quickTo,
  error,
  onOpen,
  onQuick,
}: {
  log: Reaction[]
  mySeat: number | null
  names: Partial<Record<number, string>>
  /** Who the one-tap messages are for (the opponent, or everyone at a bigger table). */
  quickTo: number | null
  error: string | null
  onOpen: (tab: ReactionKind) => void
  onQuick: (key: string, toSeat: number | null) => void
}) {
  const { t, i18n } = useTranslation()
  const muted = useReactionsMuted()
  const list = useRef<HTMLOListElement>(null)
  const time = new Intl.DateTimeFormat(i18n.language, { hour: '2-digit', minute: '2-digit' })
  const who = (seat: number) => (seat === mySeat ? t('reactions.you') : (names[seat] ?? '?'))

  // Newest at the bottom, in view.
  useEffect(() => {
    const el = list.current
    if (el) el.scrollTop = el.scrollHeight
  }, [log.length])

  return (
    <section
      aria-labelledby="table-chat-title"
      className="club-frame rounded-2xl p-3 sm:p-4"
      data-table-chat=""
    >
      <div className="flex items-center justify-between gap-2">
        <h2
          id="table-chat-title"
          className="text-sm font-bold tracking-wide text-gold-light uppercase"
        >
          {t('reactions.chat.title')}
        </h2>
        <button
          type="button"
          aria-pressed={muted}
          onClick={() => setMuted(!muted)}
          className="rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-muted hover:border-gold/50 hover:text-white"
        >
          {muted ? t('reactions.chat.unmute') : t('reactions.chat.mute')}
        </button>
      </div>

      <ol
        ref={list}
        role="log"
        aria-live="polite"
        aria-label={t('reactions.chat.title')}
        className="mt-2 max-h-36 min-h-14 space-y-1 overflow-y-auto overscroll-contain rounded-xl bg-black/30 px-3 py-2 text-sm"
      >
        {log.length === 0 ? (
          <li className="py-2 text-center text-muted">{t('reactions.chat.empty')}</li>
        ) : (
          log.map((r) => (
            <li key={r.id} className="flex min-w-0 items-baseline gap-2">
              <time dateTime={r.sent_at} className="shrink-0 text-xs text-muted tabular-nums">
                {time.format(new Date(r.sent_at))}
              </time>
              <span className="min-w-0">
                <span
                  className={`font-bold ${r.from_seat === mySeat ? 'text-accent-text' : 'text-gold-light'}`}
                >
                  {who(r.from_seat)}
                </span>
                {r.to_seat !== null ? (
                  <span className="text-muted"> → {who(r.to_seat)}</span>
                ) : null}
                <span className="text-muted">: </span>
                {r.kind === 'emoji' ? (
                  <span className="text-lg leading-none">{r.value}</span>
                ) : (
                  <span>{messageText(t, r.value)}</span>
                )}
              </span>
            </li>
          ))
        )}
      </ol>

      <div className="mt-2 flex items-center gap-1.5 overflow-x-auto pb-0.5">
        <button
          type="button"
          onClick={() => onOpen('emoji')}
          aria-label={t('reactions.emojiButton')}
          title={t('reactions.emojiButton')}
          className="flex size-11 shrink-0 items-center justify-center rounded-full bg-linear-to-b from-gold-light via-gold to-gold-dark text-2xl shadow-lg shadow-gold/20 transition hover:brightness-110 active:scale-95"
        >
          <span aria-hidden="true">😊</span>
        </button>
        <button
          type="button"
          onClick={() => onOpen('message')}
          className="flex min-h-11 shrink-0 items-center gap-1.5 rounded-full border border-gold/40 bg-black/30 px-3.5 text-sm font-semibold hover:border-gold"
        >
          <span aria-hidden="true">💬</span>
          {t('reactions.messagesButton')}
        </button>
        {QUICK.map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => onQuick(key, quickTo)}
            className="min-h-11 shrink-0 rounded-full border border-border bg-black/20 px-3 text-sm font-semibold text-muted hover:border-gold/50 hover:text-white"
          >
            {messageText(t, key)}
          </button>
        ))}
      </div>
      {error ? (
        <p role="alert" className="mt-1.5 text-xs text-take">
          {error}
        </p>
      ) : null}
    </section>
  )
}
