import { useId, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { SlotPoster } from '@/features/casino/SlotPoster'
import { fetchSlots, slotsKey } from '@/features/casino/slots'
import { useFavorites } from '@/features/favorites/favorites'
import { CloseIcon } from '@/shared/ui/icons'
import {
  arrange,
  favoriteItems,
  itemKey,
  PLAYERS,
  SORTS,
  type LobbyItem,
  type Players,
  type Sort,
} from '../lobbyItems'
import type { GameSummary } from '../queries'
import { CategoryBanners } from './CategoryBanners'
import { CategoryPopup } from './CategoryPopup'
import { GamePoster } from './GamePoster'

const OTHER = '_other'
/** The casino category's slug (migration add_casino_category); it also holds the slots. */
const CASINO = 'casino'
/** `?category=` for the "All" banner's popup (category slugs never start with "_"). */
const ALL = '_all'
/** `?category=` for the player's favourites on the games page. */
const FAVORITES = '_favorites'

const isOneOf = (list: readonly string[], value: string | null) =>
  value !== null && list.includes(value)

export type GameGroup = {
  key: string
  name: string
  order: number
  games: GameSummary[]
  /** How many the category holds when it lists more than its games (the casino's slots). */
  count?: number
}

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

/**
 * Filter pills (one Tab stop, arrow keys between them) over the posters; on the home page, category banners
 * instead, and "All" leaves out the games already shown larger above (`exclude`).
 */
export function GameLobby({
  games,
  banners = false,
  exclude = [],
}: {
  games: GameSummary[]
  /** Category banners (home) instead of pills; no featured tile. */
  banners?: boolean
  /** Games left out of "All" (the top games, shown above); a category still lists them. */
  exclude?: readonly string[]
}) {
  const { t } = useTranslation()
  const [params, setParams] = useSearchParams()
  // Slots are games of the Casino category (owner, 2026-11-16): counted and listed with it, and under All.
  const slotList = useQuery({ queryKey: slotsKey, queryFn: ({ signal }) => fetchSlots(signal) })
  const slots = useMemo(
    () => (slotList.data ?? []).filter((slot) => slot.play_path !== null),
    [slotList.data],
  )
  const groups = useMemo(
    () =>
      groupGames(games, t('lobby.other')).map((group) =>
        group.key === CASINO ? { ...group, count: group.games.length + slots.length } : group,
      ),
    [games, t, slots.length],
  )
  const category = params.get('category')
  const chosen = groups.find((group) => group.key === category) ?? null
  // On the home page a banner opens its games in a popup (`?category=`, so Back closes it); the grid below
  // keeps showing every game. Elsewhere the pills filter the grid.
  const selected = banners ? null : chosen
  const popup = !banners
    ? null
    : category === ALL
      ? { name: t('casino.allGames'), games, slots }
      : chosen
        ? { name: chosen.name, games: chosen.games, slots: chosen.key === CASINO ? slots : [] }
        : null

  const select = (key: string | null) =>
    setParams(
      (current) => {
        if (key) current.set('category', key)
        else current.delete('category')
        return current
      },
      // A popup opens as a new history entry, so the phone's Back button closes it; closing replaces it.
      { replace: !banners || !key, preventScrollReset: true },
    )

  // The header search's "all results" arrives as ?q=.
  const [query, setQuery] = useState(() => params.get('q') ?? '')
  const needle = query.trim().toLocaleLowerCase()
  const favorites = useFavorites()
  const favoriteCount = favorites.data?.length ?? 0
  // The games page's extra filters (pills mode); the home page's grid only searches.
  const sort =
    !banners && isOneOf(SORTS, params.get('sort')) ? (params.get('sort') as Sort) : 'recommended'
  const players =
    !banners && isOneOf(PLAYERS, params.get('players'))
      ? (params.get('players') as Players)
      : undefined
  const favoritesOnly = !banners && category === FAVORITES

  const base: LobbyItem[] = favoritesOnly
    ? favoriteItems(favorites.data ?? [], games, slots)
    : [
        // A search looks through every game, the ones shown larger above too.
        ...(needle
          ? games
          : selected
            ? selected.games
            : games.filter((g) => !exclude.includes(g.slug))
        ).map((game): LobbyItem => ({ kind: 'game', game })),
        ...(selected && selected.key !== CASINO ? [] : slots).map((slot): LobbyItem => ({
          kind: 'slot',
          slot,
        })),
      ]
  const shown = arrange(base, { query, players, sort })
  const filtered = !!selected || favoritesOnly || !!players || sort !== 'recommended'
  const featured =
    !banners && !filtered && !needle && shown.length >= 3 && shown[0]?.kind === 'game'

  const setParam = (name: string, value: string | null) =>
    setParams(
      (current) => {
        if (value) current.set(name, value)
        else current.delete(name)
        return current
      },
      { replace: true, preventScrollReset: true },
    )
  const chips = [
    favoritesOnly ? { label: t('favorites.filter'), clear: () => select(null) } : null,
    selected ? { label: selected.name, clear: () => select(null) } : null,
    players
      ? { label: t(`lobbyFilters.${players}`), clear: () => setParam('players', null) }
      : null,
    sort !== 'recommended'
      ? { label: t(`lobbyFilters.${sort}`), clear: () => setParam('sort', null) }
      : null,
  ].filter((chip) => chip !== null)

  return (
    <div>
      {banners ? (
        <CategoryBanners
          groups={groups}
          total={games.length + slots.length}
          onSelect={(key) => select(key ?? ALL)}
        />
      ) : groups.length < 2 ? null : (
        <div className="surface-walnut mb-5 rounded-card px-4 py-2">
          <CategoryPills
            groups={groups}
            total={games.length + slots.length}
            favorites={favoriteCount}
            selected={favoritesOnly ? FAVORITES : (selected?.key ?? null)}
            onSelect={select}
          />
        </div>
      )}
      <div className="mb-4 flex flex-wrap items-end gap-3">
        <label className="relative block w-full sm:max-w-sm sm:flex-1">
          <span className="sr-only">{t('lobby.search')}</span>
          <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
            className="text-fg-muted pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 fill-none stroke-current stroke-2"
          >
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
          </svg>
          <input
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t('lobby.search')}
            className="placeholder:text-fg-muted min-h-11 w-full rounded-full border border-border bg-surface pr-4 pl-10 text-sm text-fg focus:border-accent-text focus:outline-none"
          />
        </label>
        {banners ? null : (
          <div className="flex w-full gap-3 sm:ml-auto sm:w-auto">
            <FilterSelect
              label={t('lobbyFilters.players')}
              value={players ?? ''}
              onChange={(value) => setParam('players', value || null)}
              options={[
                { value: '', label: t('lobbyFilters.any') },
                ...PLAYERS.map((value) => ({ value, label: t(`lobbyFilters.${value}`) })),
              ]}
            />
            <FilterSelect
              label={t('lobbyFilters.sort')}
              value={sort}
              onChange={(value) => setParam('sort', value === 'recommended' ? null : value)}
              options={SORTS.map((value) => ({ value, label: t(`lobbyFilters.${value}`) }))}
            />
          </div>
        )}
      </div>
      {/* What narrows the list, each removable, and how many it leaves (Baymard: applied filters overview). */}
      {chips.length > 0 ? (
        <div className="mb-4 flex flex-wrap items-center gap-2" aria-live="polite">
          <span className="text-sm font-semibold text-fg">
            {t('lobbyFilters.results', { count: shown.length })}
          </span>
          {chips.map((chip) => (
            <button
              key={chip.label}
              type="button"
              onClick={chip.clear}
              aria-label={t('lobbyFilters.remove', { label: chip.label })}
              className="inline-flex min-h-9 items-center gap-1.5 rounded-full border border-gold/40 bg-black/25 pr-2.5 pl-3.5 text-sm font-semibold text-fg transition-colors hover:border-gold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-text"
            >
              {chip.label}
              <CloseIcon className="size-4" />
            </button>
          ))}
          {chips.length > 1 ? (
            <button
              type="button"
              onClick={() =>
                setParams(new URLSearchParams(), { replace: true, preventScrollReset: true })
              }
              className="min-h-9 px-2 text-sm font-semibold text-gold-light underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-accent-text"
            >
              {t('lobbyFilters.clear')}
            </button>
          ) : null}
        </div>
      ) : null}
      {shown.length === 0 ? (
        <p role="status" className="text-fg-muted py-8 text-center">
          {needle
            ? t('lobby.noMatch', { query: query.trim() })
            : favoritesOnly
              ? t('favorites.empty')
              : t('lobbyFilters.results', { count: 0 })}
        </p>
      ) : null}
      {/* "All": the first game (the admin's order) is featured, a double-width tile with wide art. */}
      <ul
        key={favoritesOnly ? FAVORITES : (selected?.key ?? 'all')}
        className={`grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 ${featured ? 'lg:grid-cols-6' : 'lg:grid-cols-5'}`}
      >
        {shown.map((item, i) => (
          <li
            key={itemKey(item)}
            className={`flex motion-safe:animate-rise ${featured && i === 0 ? 'col-span-2 sm:col-span-3 lg:col-span-2' : ''}`}
            style={{ animationDelay: `${Math.min(i, 8) * 45}ms` }}
          >
            <div className="flex w-full flex-col">
              {item.kind === 'game' ? (
                <GamePoster game={item.game} featured={featured && i === 0} />
              ) : (
                <SlotPoster slot={item.slot} />
              )}
            </div>
          </li>
        ))}
      </ul>
      {popup ? (
        <CategoryPopup
          name={popup.name}
          games={popup.games}
          slots={popup.slots}
          onClose={() => select(null)}
        />
      ) : null}
    </div>
  )
}

function CategoryPills({
  groups,
  total,
  favorites,
  selected,
  onSelect,
}: {
  groups: GameGroup[]
  total: number
  /** How many favourites the player has: their pill shows when there are any. */
  favorites: number
  selected: string | null
  onSelect: (key: string | null) => void
}) {
  const { t } = useTranslation()
  const name = useId()
  const options: { key: string | null; label: string; count: number }[] = [
    { key: null, label: t('lobby.all'), count: total },
    ...groups.map((group) => ({
      key: group.key,
      label: group.name,
      count: group.count ?? group.games.length,
    })),
    ...(favorites > 0 ? [{ key: FAVORITES, label: t('favorites.filter'), count: favorites }] : []),
  ]

  return (
    <fieldset className="min-w-0">
      <legend className="sr-only">{t('lobby.filterLabel')}</legend>
      {/* Phones scroll sideways rather than wrap: the rail below keeps its place. */}
      <div className="relative -mx-4 -my-1.5 flex snap-x scroll-px-4 [scrollbar-width:none] gap-2 overflow-x-auto px-4 py-1.5 sm:-mx-6 sm:scroll-px-6 sm:px-6 lg:-mx-2 lg:flex-wrap lg:px-2 [&::-webkit-scrollbar]:hidden">
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

/** A labelled native select: the phone's own picker, keyboard and screen readers for free. */
function FilterSelect({
  label,
  value,
  options,
  onChange,
}: {
  label: string
  value: string
  options: { value: string; label: string }[]
  onChange: (value: string) => void
}) {
  return (
    <label className="text-fg-muted flex min-w-0 flex-1 flex-col gap-1 text-xs font-semibold sm:flex-none">
      {label}
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="min-h-11 w-full rounded-full border border-border bg-surface px-4 text-sm font-semibold text-fg focus:border-accent-text focus:outline-none sm:w-44"
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  )
}
