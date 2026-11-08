import {
  useCallback,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  ViewTransition,
  type KeyboardEvent,
  type ReactElement,
} from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router'
import { ChevronIcon, PlayIcon } from '@/shared/ui/icons'
import type { GameSummary } from '../queries'
import { artTransition, titleTransition } from '../transitions'
import { CategoryCover, CategoryCrest, GamePosterScene } from './HomeArt'
import { PlayerCount } from './PlayerCount'

const OTHER = '_other'

export type GameGroup = { key: string; name: string; order: number; games: GameSummary[] }

/** Shelves come from the games themselves: a category without games never appears. */
function groupGames(games: GameSummary[], otherName: string): GameGroup[] {
  const groups = new Map<string, GameGroup>()
  for (const game of games) {
    const key = game.category?.slug ?? OTHER
    const group = groups.get(key) ?? {
      key,
      name: game.category?.name ?? otherName,
      order: game.category?.sort_order ?? Number.POSITIVE_INFINITY,
      games: [],
    }
    group.games.push(game)
    groups.set(key, group)
  }
  return [...groups.values()].sort((a, b) => a.order - b.order || a.name.localeCompare(b.name))
}

const noMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

/** Filter pills (one Tab stop, arrow keys between them), then shelves or one category's grid. */
export function GameLobby({ games }: { games: GameSummary[] }) {
  const { t } = useTranslation()
  const [params, setParams] = useSearchParams()
  const groups = useMemo(() => groupGames(games, t('lobby.other')), [games, t])
  const selected = groups.find((group) => group.key === params.get('category')) ?? null

  const select = (key: string | null) =>
    setParams(
      (current) => {
        if (key) current.set('category', key)
        else current.delete('category')
        return current
      },
      { replace: true, preventScrollReset: true },
    )

  if (groups.length < 2) {
    return <GameCarousel games={games} label={t('carousel.label')} />
  }

  return (
    <div>
      <CategoryPills
        groups={groups}
        total={games.length}
        selected={selected?.key ?? null}
        onSelect={select}
      />
      {selected ? (
        <section aria-label={selected.name} className="mt-8">
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
            {selected.games.map((game) => (
              <li key={game.slug}>
                <GameCard game={game} headingLevel={3} />
              </li>
            ))}
          </ul>
        </section>
      ) : (
        <div className="mt-8 space-y-10 sm:space-y-12">
          {groups.map((group) => (
            <GameCarousel
              key={group.key}
              games={group.games}
              label={group.name}
              heading={
                <>
                  <CategoryCrest slug={group.key} name={group.name} />
                  <div className="min-w-0">
                    <h3 className="truncate text-xl font-bold text-fg">{group.name}</h3>
                    <p className="text-sm text-muted">
                      {t('lobby.count', { count: group.games.length })}
                    </p>
                  </div>
                </>
              }
              coverSlug={group.key}
              onSeeAll={() => select(group.key)}
            />
          ))}
        </div>
      )}
    </div>
  )
}

function CategoryPills({
  groups,
  total,
  selected,
  onSelect,
}: {
  groups: GameGroup[]
  total: number
  selected: string | null
  onSelect: (key: string | null) => void
}) {
  const { t } = useTranslation()
  const name = useId()
  const options: { key: string | null; label: string; count: number }[] = [
    { key: null, label: t('lobby.all'), count: total },
    ...groups.map((group) => ({ key: group.key, label: group.name, count: group.games.length })),
  ]

  return (
    <fieldset>
      <legend className="sr-only">{t('lobby.filterLabel')}</legend>
      {/* Phones scroll sideways rather than wrap: the rail below keeps its place. */}
      <div className="relative -mx-4 flex snap-x [scrollbar-width:none] gap-2 overflow-x-auto px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:flex-wrap lg:px-0 [&::-webkit-scrollbar]:hidden">
        {options.map(({ key, label, count }) => (
          <label
            key={key ?? 'all'}
            className="flex min-h-11 shrink-0 cursor-pointer snap-start items-center gap-2 rounded-full border border-border bg-surface px-4 text-sm font-semibold whitespace-nowrap text-fg transition-colors hover:border-accent-text has-checked:border-transparent has-checked:bg-linear-to-b has-checked:from-gold-light has-checked:to-gold has-checked:text-black has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-accent-text"
          >
            <input
              type="radio"
              name={name}
              checked={selected === key}
              onChange={() => onSelect(key)}
              className="sr-only"
            />
            {/* Not colour alone: gold on paper is under 3:1, so the chosen pill also gets a tick. */}
            {selected === key ? (
              <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
                className="size-4"
                fill="none"
                stroke="currentColor"
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="m5 13 4 4L19 7" />
              </svg>
            ) : null}
            {label}
            <span className="tabular-nums opacity-75">{count}</span>
          </label>
        ))}
      </div>
    </fieldset>
  )
}

/**
 * A shelf of games on a native scrolling rail: fits as many as the width allows, swipes natively,
 * and never moves on its own. One Tab stop; arrow keys, Home and End walk the games. Arrows and the
 * "4–6 of 12" line appear only when the games don't all fit.
 */
export function GameCarousel({
  games,
  label,
  heading,
  coverSlug,
  onSeeAll,
}: {
  games: GameSummary[]
  label: string
  heading?: ReactElement
  coverSlug?: string
  onSeeAll?: () => void
}) {
  const { t } = useTranslation()
  const rail = useRef<HTMLUListElement>(null)
  const timer = useRef<number | undefined>(undefined)
  const [current, setCurrent] = useState(0)
  const [view, setView] = useState({ canPrev: false, canNext: false, from: 1, to: games.length })
  const overflowing = view.canPrev || view.canNext

  const cards = () => [...(rail.current?.querySelectorAll<HTMLElement>('[data-game-card]') ?? [])]

  const measure = useCallback(() => {
    const element = rail.current
    if (!element) return
    const box = element.getBoundingClientRect()
    const visible = cards().flatMap((card, index) => {
      const rect = card.getBoundingClientRect()
      return rect.left >= box.left - 2 && rect.right <= box.right + 2 ? [index] : []
    })
    const first = visible[0] ?? 0
    setView({
      canPrev: element.scrollLeft > 4,
      canNext: element.scrollLeft + element.clientWidth < element.scrollWidth - 4,
      from: first + 1,
      to: (visible.at(-1) ?? first) + 1,
    })
    // Tab should land on what's on screen, unless the player is already walking the rail.
    if (!element.contains(document.activeElement)) setCurrent(first)
  }, [])

  useEffect(() => {
    measure()
    window.addEventListener('resize', measure)
    return () => {
      window.removeEventListener('resize', measure)
      window.clearTimeout(timer.current)
    }
  }, [measure, games.length])

  const page = (direction: 1 | -1) =>
    rail.current?.scrollBy?.({
      left: direction * rail.current.clientWidth * 0.9,
      behavior: noMotion() ? 'auto' : 'smooth',
    })

  const onKeyDown = (event: KeyboardEvent) => {
    const list = cards()
    const at = list.indexOf(document.activeElement as HTMLElement)
    if (at < 0) return
    const to =
      event.key === 'ArrowRight'
        ? at + 1
        : event.key === 'ArrowLeft'
          ? at - 1
          : event.key === 'Home'
            ? 0
            : event.key === 'End'
              ? list.length - 1
              : -1
    const card = list[to]
    if (!card) return
    event.preventDefault()
    setCurrent(to)
    card.focus({ preventScroll: true })
    card.scrollIntoView?.({
      inline: 'nearest',
      block: 'nearest',
      behavior: noMotion() ? 'auto' : 'smooth',
    })
  }

  const arrow =
    'flex size-11 shrink-0 items-center justify-center rounded-full border border-border bg-surface text-fg transition-colors hover:border-accent-text aria-disabled:cursor-default aria-disabled:opacity-40 aria-disabled:hover:border-border'

  return (
    <section aria-roledescription={t('carousel.carousel')} aria-label={label}>
      <div className="mb-4 flex items-center justify-between gap-4">
        <div className="flex min-w-0 items-center gap-3">{heading}</div>
        {overflowing ? (
          <div className="flex shrink-0 gap-2">
            {/* aria-disabled, not disabled: a button that disables itself would drop keyboard focus. */}
            <button
              type="button"
              aria-label={t('carousel.previousPage')}
              aria-disabled={!view.canPrev}
              onClick={() => view.canPrev && page(-1)}
              className={arrow}
            >
              <ChevronIcon className="size-5 rotate-180" />
            </button>
            <button
              type="button"
              aria-label={t('carousel.nextPage')}
              aria-disabled={!view.canNext}
              onClick={() => view.canNext && page(1)}
              className={arrow}
            >
              <ChevronIcon className="size-5" />
            </button>
          </div>
        ) : null}
      </div>

      <ul
        ref={rail}
        onKeyDown={onKeyDown}
        onScroll={() => {
          window.clearTimeout(timer.current)
          timer.current = window.setTimeout(measure, 100)
        }}
        className="relative -mx-4 flex snap-x snap-mandatory scroll-px-4 [scrollbar-width:none] gap-4 overflow-x-auto px-4 py-2 sm:-mx-6 sm:scroll-px-6 sm:px-6 lg:-mx-2 lg:snap-proximity lg:scroll-px-2 lg:gap-5 lg:px-2 [&::-webkit-scrollbar]:hidden"
      >
        {coverSlug ? (
          // Takes up any width the cards leave free, so a two-game shelf still fills its row.
          <li
            aria-hidden="true"
            className="hidden min-w-0 snap-start overflow-hidden rounded-2xl lg:block lg:flex-[1_0_calc((100%-3.75rem)/4)]"
          >
            <CategoryCover slug={coverSlug} className="block size-full" />
          </li>
        ) : null}
        {games.map((game, index) => (
          <li
            key={game.slug}
            aria-roledescription={t('carousel.slide')}
            aria-label={t('carousel.position', { index: index + 1, count: games.length })}
            className="w-[78%] shrink-0 snap-start sm:w-[44%] lg:w-[calc((100%-3.75rem)/4)]"
          >
            <GameCard
              game={game}
              headingLevel={heading ? 4 : 3}
              tabIndex={index === current ? 0 : -1}
              onFocus={() => setCurrent(index)}
            />
          </li>
        ))}
      </ul>

      {overflowing ? (
        <div className="mt-2 flex min-h-11 items-center justify-between gap-4">
          <p aria-live="polite" className="text-sm text-muted tabular-nums">
            {view.from === view.to
              ? t('carousel.position', { index: view.from, count: games.length })
              : t('carousel.range', { from: view.from, to: view.to, count: games.length })}
          </p>
          {onSeeAll ? (
            <button
              type="button"
              onClick={onSeeAll}
              aria-label={t('lobby.seeAllLabel', { category: label })}
              className="min-h-11 rounded-lg px-2 text-sm font-semibold text-accent-text hover:underline"
            >
              {t('lobby.seeAll')}
            </button>
          ) : null}
        </div>
      ) : null}
    </section>
  )
}

/** Uploaded artwork first, else the poster. Text sits below the art, never on it. */
function GameCard({
  game,
  headingLevel,
  tabIndex,
  onFocus,
}: {
  game: GameSummary
  headingLevel: 3 | 4
  tabIndex?: number
  onFocus?: () => void
}) {
  const { t } = useTranslation()
  const Title = headingLevel === 3 ? 'h3' : 'h4'
  return (
    <Link
      to={`/games/${game.slug}`}
      data-game-card=""
      tabIndex={tabIndex}
      onFocus={onFocus}
      className="group flex h-full flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-md shadow-black/10 transition-[transform,box-shadow,border-color] duration-200 hover:border-accent-text hover:shadow-xl motion-safe:hover:-translate-y-1"
    >
      <ViewTransition name={artTransition(game.slug)}>
        <div className="relative aspect-[16/10] overflow-hidden">
          {game.thumbnail_url ? (
            <img
              src={game.thumbnail_url}
              alt=""
              loading="lazy"
              className="size-full object-cover"
            />
          ) : (
            <GamePosterScene
              slug={game.slug}
              name={game.name}
              className="block size-full transition-transform duration-500 motion-safe:group-hover:scale-105"
            />
          )}
          {game.is_playable ? null : (
            <span className="absolute top-3 left-3">
              {/* A solid chip: a tinted Badge would not hold 4.5:1 over art. */}
              <span className="rounded-full bg-home-paper px-2.5 py-1 text-xs font-bold text-home-ink shadow-sm">
                {t('catalog.comingSoon')}
              </span>
            </span>
          )}
        </div>
      </ViewTransition>
      <div className="flex flex-1 flex-col p-4">
        <ViewTransition name={titleTransition(game.slug)}>
          <Title className="text-lg font-bold text-fg">{game.name}</Title>
        </ViewTransition>
        <p className="mt-0.5 text-sm font-medium text-muted">
          <PlayerCount game={game} />
        </p>
        <p className="mt-2 line-clamp-2 min-h-10 text-sm text-muted">{game.description}</p>
        {game.is_playable ? (
          <span
            aria-hidden="true"
            className="mt-3 inline-flex items-center gap-1.5 self-start rounded-full border border-white/15 bg-linear-to-b from-accent-light to-accent px-4 py-2 text-sm font-bold text-white uppercase"
          >
            <PlayIcon className="size-4" />
            {t('catalog.play')}
          </span>
        ) : null}
      </div>
    </Link>
  )
}
