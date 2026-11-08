import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { useTranslation } from 'react-i18next'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import { CloseIcon } from '@/shared/ui/icons'
import type { ReactionCatalog, ReactionKind } from '../api'
import { emojiCategoryName, messageCategoryName, messageText } from '../labels'
import { recentEmojis, rememberEmoji } from '../prefs'

export type Recipient = { seat: number; name: string; bot: boolean }

/**
 * The emoji and quick-message picker: who it's for (one player or everyone), then an emoji (by
 * category, recently used first, or found by name in any of the UI's languages) or a predefined
 * message; one tap sends and closes. A sheet from the bottom on phones, a panel on wider screens; the
 * page behind is inert while it is open.
 */
export function ReactionPicker({
  catalog,
  recipients,
  myLevel,
  initialTab,
  onSend,
  onClose,
}: {
  catalog: ReactionCatalog
  /** The other players at the table. */
  recipients: Recipient[]
  /** The sender's level: exclusive emojis open at the level shown on them (ADR 0018). */
  myLevel: number
  initialTab: ReactionKind
  onSend: (kind: ReactionKind, value: string, toSeat: number | null) => void
  onClose: () => void
}) {
  const { t } = useTranslation()
  const [tab, setTab] = useState<ReactionKind>(initialTab)
  // One opponent: it's for them; at a bigger table: for everyone until a player is chosen.
  const [to, setTo] = useState<number | null>(
    recipients.length === 1 ? (recipients[0]?.seat ?? null) : null,
  )
  const [query, setQuery] = useState('')
  const [recent] = useState(recentEmojis)
  const panel = useRef<HTMLDivElement>(null)
  const grid = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const opener = document.activeElement as HTMLElement | null
    const app = document.getElementById('root')
    app?.setAttribute('inert', '')
    panel.current?.focus()
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
      app?.removeAttribute('inert')
      opener?.focus()
    }
  }, [onClose])

  const send = (kind: ReactionKind, value: string) => {
    if (kind === 'emoji') rememberEmoji(value)
    onSend(kind, value, to)
    onClose()
  }

  const found = useMemo(() => {
    const words = query.trim().toLocaleLowerCase()
    if (!words) return null
    // "cards" finds "card": an English plural also matches its singular.
    const terms = words.length > 3 && words.endsWith('s') ? [words, words.slice(0, -1)] : [words]
    const all = catalog.emoji.flatMap((category) => category.emojis)
    return [
      ...new Set(
        all
          .filter(
            (e) =>
              terms.some((term) => e.keywords.toLocaleLowerCase().includes(term)) ||
              e.emoji === words,
          )
          .map((e) => e.emoji),
      ),
    ]
  }, [catalog, query])

  const unlocked = catalog.exclusive.filter((e) => e.level <= myLevel).map((e) => e.emoji)
  const recentKnown = recent.filter(
    (emoji) =>
      unlocked.includes(emoji) ||
      catalog.emoji.some((category) => category.emojis.some((e) => e.emoji === emoji)),
  )

  // A word for screen readers: the first (English) search word of the emoji.
  const words = useMemo(
    () =>
      new Map(
        catalog.emoji.flatMap((c) =>
          c.emojis.map((e) => [e.emoji, e.keywords.split(' ')[0] ?? '']),
        ),
      ),
    [catalog],
  )
  const emojiButton = (emoji: string, key: string) => (
    <button
      key={key}
      type="button"
      onClick={() => send('emoji', emoji)}
      className="flex size-11 items-center justify-center rounded-xl text-[1.7rem] leading-none transition hover:scale-110 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-accent-text active:scale-95"
      aria-label={`${emoji} ${words.get(emoji) ?? ''}`.trim()}
    >
      {emoji}
    </button>
  )

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-end sm:justify-end sm:p-6">
      <div
        aria-hidden="true"
        className="absolute inset-0 bg-black/45 motion-safe:animate-[fade-in_160ms_ease-out] sm:bg-black/25"
        onClick={onClose}
      />
      <div
        ref={panel}
        role="dialog"
        aria-modal="true"
        aria-label={t('reactions.title')}
        tabIndex={-1}
        className="club-frame relative flex max-h-[78vh] w-full flex-col rounded-t-3xl pb-[env(safe-area-inset-bottom)] outline-none motion-safe:animate-rise sm:max-h-[min(36rem,80vh)] sm:w-[25rem] sm:rounded-3xl"
      >
        <div className="flex items-center justify-between gap-2 px-4 pt-4">
          <h2 className="text-base font-bold text-gold-light">{t('reactions.title')}</h2>
          <button
            type="button"
            onClick={onClose}
            className="flex size-11 items-center justify-center rounded-full text-muted hover:bg-white/10 hover:text-white"
            aria-label={t('reactions.close')}
          >
            <CloseIcon className="size-5" />
          </button>
        </div>

        {recipients.length > 0 ? (
          <fieldset className="px-4 pt-2">
            <legend className="mb-1.5 text-xs font-semibold tracking-wider text-muted uppercase">
              {t('reactions.sendTo')}
            </legend>
            <div className="flex gap-1.5 overflow-x-auto pb-1">
              {[{ seat: null, name: t('reactions.everyone'), bot: false }, ...recipients].map(
                (r) => (
                  <button
                    key={r.seat ?? 'all'}
                    type="button"
                    aria-pressed={to === r.seat}
                    onClick={() => setTo(r.seat)}
                    className={`min-h-11 shrink-0 rounded-full border px-3.5 text-sm font-semibold transition ${
                      to === r.seat
                        ? 'border-gold bg-gold/15 text-gold-light'
                        : 'border-border text-muted hover:border-gold/50 hover:text-white'
                    }`}
                  >
                    {r.name}
                    {r.bot ? (
                      <span className="ml-1 text-xs opacity-70">· {t('room.bot')}</span>
                    ) : null}
                  </button>
                ),
              )}
            </div>
          </fieldset>
        ) : null}

        <div
          role="tablist"
          className="mx-4 mt-3 grid grid-cols-2 rounded-xl bg-black/30 p-1"
          onKeyDown={(event) => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return
            const next = tab === 'emoji' ? 'message' : 'emoji'
            setTab(next)
            document.getElementById(`reaction-tab-${next}`)?.focus()
          }}
        >
          {(['emoji', 'message'] as const).map((kind) => (
            <button
              key={kind}
              id={`reaction-tab-${kind}`}
              type="button"
              role="tab"
              aria-selected={tab === kind}
              aria-controls={`reaction-panel-${kind}`}
              tabIndex={tab === kind ? 0 : -1}
              onClick={() => setTab(kind)}
              className={`min-h-11 rounded-lg text-sm font-bold transition ${
                tab === kind ? 'bg-gold text-black shadow' : 'text-muted hover:text-white'
              }`}
            >
              {t(kind === 'emoji' ? 'reactions.emojiTab' : 'reactions.messagesTab')}
            </button>
          ))}
        </div>

        {tab === 'emoji' ? (
          <div
            id="reaction-panel-emoji"
            role="tabpanel"
            aria-labelledby="reaction-tab-emoji"
            className="flex min-h-0 flex-1 flex-col"
          >
            <div className="shrink-0 px-4 pt-3">
              <input
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder={t('reactions.search')}
                aria-label={t('reactions.search')}
                className="w-full rounded-xl border border-border bg-black/30 px-3 py-2 text-sm placeholder:text-muted focus:border-gold focus:outline-none"
              />
            </div>
            {found ? null : (
              <nav
                aria-label={t('reactions.categories')}
                className="flex shrink-0 gap-0.5 overflow-x-auto px-3 pt-2"
              >
                {catalog.exclusive.length ? (
                  <button
                    type="button"
                    title={t('reactions.exclusive')}
                    aria-label={t('reactions.exclusive')}
                    onClick={() =>
                      grid.current
                        ?.querySelector('[data-category="exclusive"]')
                        ?.scrollIntoView({ block: 'start', behavior: 'smooth' })
                    }
                    className="flex size-11 shrink-0 items-center justify-center rounded-lg text-xl hover:bg-white/10"
                  >
                    {catalog.exclusive[0]?.emoji}
                  </button>
                ) : null}
                {catalog.emoji.map((category) => (
                  <button
                    key={category.slug}
                    type="button"
                    title={emojiCategoryName(t, category.slug)}
                    aria-label={emojiCategoryName(t, category.slug)}
                    onClick={() =>
                      grid.current
                        ?.querySelector(`[data-category="${category.slug}"]`)
                        ?.scrollIntoView({ block: 'start', behavior: 'smooth' })
                    }
                    className="flex size-11 shrink-0 items-center justify-center rounded-lg text-xl hover:bg-white/10"
                  >
                    {category.emojis[0]?.emoji}
                  </button>
                ))}
              </nav>
            )}
            <div
              ref={grid}
              className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pt-1 pb-4"
            >
              {found ? (
                found.length ? (
                  <div className="grid grid-cols-7 gap-0.5 sm:grid-cols-8">
                    {found.map((emoji) => emojiButton(emoji, emoji))}
                  </div>
                ) : (
                  <p className="px-2 py-6 text-center text-sm text-muted">
                    {t('reactions.noResults')}
                  </p>
                )
              ) : (
                <>
                  {recentKnown.length ? (
                    <Section title={t('reactions.recent')}>
                      {recentKnown.map((emoji) => emojiButton(emoji, `recent-${emoji}`))}
                    </Section>
                  ) : null}
                  {catalog.exclusive.length ? (
                    <Section slug="exclusive" title={t('reactions.exclusive')}>
                      {catalog.exclusive.map((e) =>
                        e.level <= myLevel ? (
                          emojiButton(e.emoji, `exclusive-${e.emoji}`)
                        ) : (
                          // Locked: shown with the level that opens it; the server refuses it anyway.
                          <span
                            key={`exclusive-${e.emoji}`}
                            role="img"
                            aria-label={t('reactions.lockedEmoji', {
                              emoji: e.emoji,
                              level: e.level,
                            })}
                            title={t('reactions.lockedEmoji', { emoji: e.emoji, level: e.level })}
                            className="relative flex size-11 items-center justify-center rounded-xl text-[1.7rem] leading-none"
                          >
                            <span aria-hidden="true" className="opacity-35 grayscale">
                              {e.emoji}
                            </span>
                            <LevelBadge
                              level={e.level}
                              size="sm"
                              className="absolute right-0 bottom-0.5"
                            />
                          </span>
                        ),
                      )}
                    </Section>
                  ) : null}
                  {catalog.emoji.map((category) => (
                    <Section
                      key={category.slug}
                      slug={category.slug}
                      title={emojiCategoryName(t, category.slug)}
                    >
                      {category.emojis.map((e) =>
                        emojiButton(e.emoji, `${category.slug}-${e.emoji}`),
                      )}
                    </Section>
                  ))}
                </>
              )}
            </div>
          </div>
        ) : (
          <div
            id="reaction-panel-message"
            role="tabpanel"
            aria-labelledby="reaction-tab-message"
            className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pt-3 pb-4"
          >
            {catalog.messages.map((category) => (
              <section key={category.slug} className="mb-3">
                <h3 className="mb-1.5 text-xs font-semibold tracking-wider text-muted uppercase">
                  {messageCategoryName(t, category.slug)}
                </h3>
                <div className="flex flex-wrap gap-1.5">
                  {category.keys.map((key) => (
                    <button
                      key={key}
                      type="button"
                      onClick={() => send('message', key)}
                      className="min-h-11 rounded-full border border-gold/30 bg-black/30 px-3.5 py-2 text-sm font-semibold transition hover:border-gold hover:bg-gold/15 active:scale-95"
                    >
                      {messageText(t, key)}
                    </button>
                  ))}
                </div>
              </section>
            ))}
          </div>
        )}
      </div>
    </div>,
    document.body,
  )
}

function Section({ title, slug, children }: { title: string; slug?: string; children: ReactNode }) {
  return (
    <section data-category={slug} className="scroll-mt-1 pt-2">
      <h3 className="mb-1 px-1 text-xs font-semibold tracking-wider text-muted uppercase">
        {title}
      </h3>
      <div className="grid grid-cols-7 gap-0.5 sm:grid-cols-8">{children}</div>
    </section>
  )
}
