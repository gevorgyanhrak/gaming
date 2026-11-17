import { useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { fetchRecentSlots, recentSlotsKey } from '@/features/casino/engine/api'
import { SlotPoster } from '@/features/casino/SlotPoster'
import { fetchSlots, slotsKey, type Slot } from '@/features/casino/slots'
import { useFavorites } from '@/features/favorites/favorites'
import { search, termsOf } from '@/features/search/gameSearch'
import { Container } from '@/shared/ui/Container'
import { SectionHeading } from '@/shared/ui/SectionHeading'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'

const PAGE = 48
const FAVORITES = '_favorites'
const HOUSE = '_house'

/**
 * The slot hall: every slot on the platform — the engine's games by their makers (ADR 0026) and the house slots —
 * with the makers as filters (taken from the games themselves), search by name or maker, favourites, the slots you
 * played last, and the grid in pages. Fishkas only: play money.
 */
export function SlotHallPage() {
  const { t } = useTranslation()
  const [params, setParams] = useSearchParams()
  const { data: user } = useCurrentUser()
  const slots = useQuery({ queryKey: slotsKey, queryFn: ({ signal }) => fetchSlots(signal) })
  const recent = useQuery({
    queryKey: recentSlotsKey,
    queryFn: ({ signal }) => fetchRecentSlots(signal),
    enabled: !!user,
  })
  const favorites = useFavorites()
  const [query, setQuery] = useState(() => params.get('q') ?? '')
  const [limit, setLimit] = useState(PAGE)
  const maker = params.get('maker')
  const sort = params.get('sort') === 'name' ? 'name' : 'recommended'

  const all = useMemo(() => slots.data ?? [], [slots.data])
  const makers = useMemo(() => {
    const counts = new Map<string, number>()
    for (const slot of all) {
      const key = slot.studio ?? HOUSE
      counts.set(key, (counts.get(key) ?? 0) + 1)
    }
    return [...counts].sort((a, b) => b[1] - a[1])
  }, [all])
  const favoriteSlugs = new Set(
    (favorites.data ?? []).filter((f) => f.kind === 'slot').map((f) => f.slug),
  )

  const filtered = all.filter((slot) =>
    maker === FAVORITES
      ? favoriteSlugs.has(slot.slug)
      : maker
        ? (slot.studio ?? HOUSE) === maker
        : true,
  )
  const found = query.trim()
    ? search(filtered, query, (slot) => [...termsOf(slot.slug, slot.name), slot.studio ?? '']).map(
        (m) => m.item,
      )
    : sort === 'name'
      ? filtered.toSorted((a, b) => a.name.localeCompare(b.name))
      : filtered
  const shown = found.slice(0, limit)

  const set = (name: string, value: string | null) => {
    setLimit(PAGE)
    setParams(
      (current) => {
        if (value) current.set(name, value)
        else current.delete(name)
        return current
      },
      { replace: true, preventScrollReset: true },
    )
  }
  const makerLabel = (key: string) => (key === HOUSE ? t('slotHall.house') : key)
  const chip = (key: string | null, label: string, count: number) => (
    <button
      key={key ?? 'all'}
      type="button"
      aria-pressed={maker === key}
      onClick={() => set('maker', key)}
      className={`min-h-10 shrink-0 rounded-full border px-3.5 text-sm font-semibold whitespace-nowrap transition-colors ${
        maker === key
          ? 'border-gold bg-gold/15 text-gold-light'
          : 'border-white/15 text-muted hover:border-gold/50 hover:text-fg'
      }`}
    >
      {label} <span className="text-xs opacity-70">{count}</span>
    </button>
  )

  return (
    <Container className="py-6 sm:py-8">
      <title>{t('slotHall.title')}</title>
      <SectionHeading eyebrow={t('slotHall.eyebrow')} title={t('slotHall.title')} />
      <p className="-mt-3 mb-5 max-w-2xl text-sm text-muted">{t('slotHall.lead')}</p>

      {user && recent.data && recent.data.length > 0 ? (
        <section aria-labelledby="recent-slots" className="mb-8">
          <h2 id="recent-slots" className="mb-3 font-display text-lg font-bold text-fg">
            {t('slotHall.recent')}
          </h2>
          <ul className="flex snap-x gap-3 overflow-x-auto pb-2 [&>li]:w-36 [&>li]:shrink-0 [&>li]:snap-start sm:[&>li]:w-44">
            {recent.data.map((slot) => (
              <li key={slot.slug} className="flex">
                <SlotPoster slot={slot} />
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <label className="relative block flex-1">
          <span className="sr-only">{t('slotHall.search')}</span>
          <input
            type="search"
            value={query}
            onChange={(e) => {
              setQuery(e.target.value)
              setLimit(PAGE)
            }}
            placeholder={t('slotHall.search')}
            className="placeholder:text-fg-muted min-h-11 w-full rounded-full border border-border bg-surface px-4 text-sm text-fg focus:border-accent-text focus:outline-none"
          />
        </label>
        <label className="flex items-center gap-2 text-sm text-muted">
          {t('slotHall.sort')}
          <select
            value={sort}
            onChange={(e) => set('sort', e.target.value === 'name' ? 'name' : null)}
            className="min-h-11 rounded-full border border-white/15 bg-white/5 px-4 text-sm font-semibold text-fg focus:border-gold focus:outline-none"
          >
            <option value="recommended">{t('slotHall.sortRecommended')}</option>
            <option value="name">{t('slotHall.sortName')}</option>
          </select>
        </label>
      </div>

      <nav aria-label={t('slotHall.makers')} className="mb-5 flex gap-2 overflow-x-auto pb-1">
        {chip(null, t('slotHall.all'), all.length)}
        {user && favoriteSlugs.size > 0
          ? chip(FAVORITES, t('favorites.title'), favoriteSlugs.size)
          : null}
        {makers.map(([key, count]) => chip(key, makerLabel(key), count))}
      </nav>

      {slots.isPending ? (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          {Array.from({ length: 10 }, (_, i) => (
            <li key={i}>
              <Skeleton className="aspect-[3/4] w-full rounded-card" />
            </li>
          ))}
        </ul>
      ) : slots.isError ? (
        <QueryErrorState error={slots.error} onRetry={() => void slots.refetch()} />
      ) : found.length === 0 ? (
        <EmptyState
          title={
            query.trim()
              ? t('lobby.noMatch', { query: query.trim() })
              : maker === FAVORITES
                ? t('favorites.empty')
                : t('slotHall.empty')
          }
        />
      ) : (
        <>
          <p role="status" className="mb-3 text-sm text-muted">
            {t('slotHall.count', { count: found.length })}
          </p>
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
            {shown.map((slot: Slot) => (
              <li key={slot.slug} className="flex motion-safe:animate-rise">
                <SlotPoster slot={slot} />
              </li>
            ))}
          </ul>
          {found.length > shown.length ? (
            <div className="mt-6 flex justify-center">
              <button
                type="button"
                onClick={() => setLimit((n) => n + PAGE)}
                className="min-h-11 rounded-full border border-gold/50 px-6 text-sm font-bold text-gold-light transition-colors hover:bg-gold/10"
              >
                {t('slotHall.more', { count: found.length - shown.length })}
              </button>
            </div>
          ) : null}
        </>
      )}
      <p className="mt-8 text-center text-[11px] text-muted">{t('slotHall.note')}</p>
    </Container>
  )
}
