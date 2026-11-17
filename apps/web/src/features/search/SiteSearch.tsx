import { useQuery } from '@tanstack/react-query'
import { useEffect, useId, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { fetchSlots, slotsKey } from '@/features/casino/slots'
import { GamePosterScene } from '@/features/catalog/components/HomeArt'
import { useGames } from '@/features/catalog/queries'
import { Modal } from '@/shared/ui/Modal'
import { CloseIcon } from '@/shared/ui/icons'
import { rank } from './rank'

/** Few suggestions on purpose: long lists slow people down (Baymard, autocomplete research). */
const LIMIT = 6

type Suggestion = {
  key: string
  name: string
  /** The Latin slug, matched too. */
  alt: string
  /** Its shelf: the game's category, or "Slots". */
  group: string
  to: string
  art: { image: string | null; slug: string }
}

/**
 * The header's game search: suggestions as you type (names that start with the text first), arrow keys and
 * Enter to open one, Enter on the text itself for every match on the games page. A box in the header from
 * `md`; below that an icon that opens it full screen.
 */
export function SiteSearch() {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  return (
    <>
      <div className="hidden max-w-md min-w-0 flex-1 md:block">
        <SearchBox />
      </div>
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-label={t('search.open')}
        className="mr-auto grid size-11 shrink-0 place-items-center rounded-full text-fg transition-colors hover:text-gold-light focus-visible:outline-2 focus-visible:outline-accent-text md:hidden"
      >
        <SearchIcon className="size-5" />
      </button>
      {open ? (
        <Modal onClose={() => setOpen(false)} label={t('search.label')} fullScreenOnPhone size="lg">
          <div className="flex items-start gap-2 p-3">
            <div className="min-w-0 flex-1">
              <SearchBox autoFocus inline onDone={() => setOpen(false)} />
            </div>
            <button
              type="button"
              onClick={() => setOpen(false)}
              aria-label={t('search.close')}
              className="grid size-11 shrink-0 place-items-center rounded-full border border-gold/30 text-fg focus-visible:outline-2 focus-visible:outline-accent-text"
            >
              <CloseIcon />
            </button>
          </div>
        </Modal>
      ) : null}
    </>
  )
}

function SearchBox({
  autoFocus = false,
  inline = false,
  onDone,
}: {
  autoFocus?: boolean
  /** The list sits under the box in the page flow (the phone's full-screen search), not floating over it. */
  inline?: boolean
  onDone?: () => void
}) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const listId = useId()
  const input = useRef<HTMLInputElement>(null)
  const [query, setQuery] = useState('')
  const [expanded, setExpanded] = useState(false)
  const [active, setActive] = useState(-1)
  const games = useGames()
  const slots = useQuery({ queryKey: slotsKey, queryFn: ({ signal }) => fetchSlots(signal) })
  // Here rather than the autoFocus attribute: this effect runs before the popup's own, which then leaves it.
  useEffect(() => {
    if (autoFocus) input.current?.focus()
  }, [autoFocus])

  const all = useMemo<Suggestion[]>(
    () => [
      ...(games.data ?? []).map((game) => ({
        key: game.slug,
        name: game.name,
        alt: game.slug,
        group: game.category?.name ?? t('search.games'),
        to: `/games/${game.slug}`,
        art: { image: game.thumbnail_url, slug: game.slug },
      })),
      ...(slots.data ?? [])
        .filter((slot) => slot.play_path !== null)
        .map((slot) => ({
          key: `slot-${slot.slug}`,
          name: slot.name,
          alt: slot.slug,
          group: t('search.slots'),
          to: `/casino/slots/${slot.slug}`,
          art: { image: slot.image_url, slug: slot.slug },
        })),
    ],
    [games.data, slots.data, t],
  )
  const needle = query.trim().toLocaleLowerCase()
  const matches = needle ? rank(all, needle) : []
  const shown = matches.slice(0, LIMIT)
  // The last row: every match on the games page.
  const rows = needle ? shown.length + 1 : 0
  const showList = expanded && needle.length > 0

  const go = (to: string) => {
    setExpanded(false)
    setQuery('')
    setActive(-1)
    input.current?.blur()
    onDone?.()
    void navigate(to)
  }
  const goAll = () => go(`/games?q=${encodeURIComponent(query.trim())}`)

  return (
    <div className="relative">
      <label className="relative block">
        <span className="sr-only">{t('search.label')}</span>
        <SearchIcon className="text-fg-muted pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2" />
        <input
          ref={input}
          type="search"
          role="combobox"
          aria-expanded={showList}
          aria-controls={listId}
          aria-autocomplete="list"
          aria-activedescendant={showList && active >= 0 ? `${listId}-${active}` : undefined}
          autoComplete="off"
          enterKeyHint="search"
          value={query}
          placeholder={t('search.placeholder')}
          onChange={(e) => {
            setQuery(e.target.value)
            setExpanded(true)
            setActive(-1)
          }}
          onFocus={() => setExpanded(true)}
          // Later than a click on a suggestion, which navigates first.
          onBlur={() => window.setTimeout(() => setExpanded(false), 150)}
          onKeyDown={(e) => {
            if (e.key === 'ArrowDown' && rows > 0) {
              e.preventDefault()
              setExpanded(true)
              setActive((i) => (i + 1) % rows)
            } else if (e.key === 'ArrowUp' && rows > 0) {
              e.preventDefault()
              setActive((i) => (i <= 0 ? rows - 1 : i - 1))
            } else if (e.key === 'Enter' && needle) {
              e.preventDefault()
              const pick = shown[active]
              if (pick) go(pick.to)
              else goAll()
            } else if (e.key === 'Escape' && showList) {
              // Closes the list; a second Escape closes the full-screen search.
              e.stopPropagation()
              e.nativeEvent.stopImmediatePropagation()
              setExpanded(false)
            }
          }}
          className="placeholder:text-fg-muted min-h-11 w-full rounded-full border border-border bg-surface pr-4 pl-10 text-sm text-fg focus:border-accent-text focus:outline-none"
        />
      </label>
      <ul
        id={listId}
        role="listbox"
        aria-label={t('search.label')}
        hidden={!showList}
        className={
          inline
            ? 'mt-2'
            : 'club-frame absolute inset-x-0 top-full z-30 mt-2 overflow-hidden rounded-card p-1.5 shadow-raised motion-safe:animate-[fade-in_150ms_ease-out]'
        }
      >
        {shown.map((item, i) => (
          <li
            key={item.key}
            id={`${listId}-${i}`}
            role="option"
            aria-selected={active === i}
            // Keep the focus in the box while the pointer chooses.
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => go(item.to)}
            onMouseEnter={() => setActive(i)}
            className={`flex min-h-12 cursor-pointer items-center gap-3 rounded-xl px-2 py-1.5 ${active === i ? 'bg-white/10' : ''}`}
          >
            <span className="size-10 shrink-0 overflow-hidden rounded-lg border border-gold/30 bg-black">
              {item.art.image ? (
                <img src={item.art.image} alt="" className="size-full object-cover" />
              ) : (
                <GamePosterScene
                  slug={item.art.slug}
                  name={item.name}
                  className="block size-full"
                />
              )}
            </span>
            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm font-semibold text-fg">
                <Highlight text={item.name} needle={needle} />
              </span>
              <span className="text-fg-muted block truncate text-xs">{item.group}</span>
            </span>
          </li>
        ))}
        {needle && shown.length === 0 ? (
          <li role="presentation" className="text-fg-muted px-3 py-3 text-sm">
            {t('search.noResults', { query: query.trim() })}
          </li>
        ) : null}
        {needle ? (
          <li
            id={`${listId}-${shown.length}`}
            role="option"
            aria-selected={active === shown.length}
            onMouseDown={(e) => e.preventDefault()}
            onClick={goAll}
            onMouseEnter={() => setActive(shown.length)}
            className={`mt-1 flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border-t border-gold/15 px-3 text-sm font-semibold text-gold-light ${active === shown.length ? 'bg-white/10' : ''}`}
          >
            <SearchIcon className="size-4 shrink-0" />
            <span className="truncate">{t('search.seeAll', { query: query.trim() })}</span>
          </li>
        ) : null}
      </ul>
    </div>
  )
}

/** The matched part in bold, so people see why a suggestion is there. */
function Highlight({ text, needle }: { text: string; needle: string }) {
  const at = text.toLocaleLowerCase().indexOf(needle)
  if (at < 0) return text
  return (
    <>
      {text.slice(0, at)}
      <mark className="bg-transparent font-extrabold text-gold-light">
        {text.slice(at, at + needle.length)}
      </mark>
      {text.slice(at + needle.length)}
    </>
  )
}

function SearchIcon({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden="true"
      className={`fill-none stroke-current stroke-2 ${className ?? ''}`}
    >
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    </svg>
  )
}
