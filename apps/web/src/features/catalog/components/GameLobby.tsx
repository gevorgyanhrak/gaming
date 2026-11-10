import { useId, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router'
import type { GameSummary } from '../queries'
import { GamePoster } from './GamePoster'

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

/** Filter pills (one Tab stop, arrow keys between them) over the posters. */
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

  const shown = selected ? selected.games : games
  const featured = !selected && shown.length >= 3

  return (
    <div>
      {groups.length < 2 ? null : (
        <div className="surface-walnut mb-5 rounded-card px-4 py-2">
          <CategoryPills
            groups={groups}
            total={games.length}
            selected={selected?.key ?? null}
            onSelect={select}
          />
        </div>
      )}
      {/* "All": the first game (the admin's order) is featured, a double-width tile with wide art. */}
      <ul
        className={`grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 ${featured ? 'lg:grid-cols-6' : 'lg:grid-cols-5'}`}
      >
        {shown.map((game, i) => (
          <li
            key={game.slug}
            className={`flex ${featured && i === 0 ? 'col-span-2 sm:col-span-3 lg:col-span-2' : ''}`}
          >
            <div className="flex w-full flex-col">
              <GamePoster game={game} featured={featured && i === 0} />
            </div>
          </li>
        ))}
      </ul>
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
