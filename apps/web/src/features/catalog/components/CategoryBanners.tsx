import { useTranslation } from 'react-i18next'
import type { GameGroup } from './GameLobby'
import { GamePosterScene } from './HomeArt'

/** Each banner's light: felt green for "All", then wine, teal, plum… by position. */
const TINTS = [
  'from-[#0f5a45] via-[#0b3b2e] to-[#071a14]',
  'from-[#7a1f33] via-[#4a1220] to-[#1a070c]',
  'from-[#0e6a66] via-[#0a3f3d] to-[#061918]',
  'from-[#4b2c7a] via-[#2c1a49] to-[#120a1f]',
  'from-[#7a4a14] via-[#4a2c0b] to-[#1c1004]',
]

/**
 * The home page's categories as banners: each with a fan of its games' posters, its name and how many games it
 * has; "All" first. Each opens its games in a popup.
 */
export function CategoryBanners({
  groups,
  total,
  onSelect,
}: {
  groups: GameGroup[]
  total: number
  onSelect: (key: string | null) => void
}) {
  const { t } = useTranslation()
  const options = [
    {
      key: null,
      name: t('casino.allGames'),
      count: total,
      slugs: groups.flatMap((g) => g.games.map((game) => game.slug)),
      names: groups.flatMap((g) => g.games.map((game) => game.name)),
    },
    ...groups.map((g) => ({
      key: g.key,
      name: g.name,
      count: g.count ?? g.games.length,
      slugs: g.games.map((game) => game.slug),
      names: g.games.map((game) => game.name),
    })),
  ]

  return (
    <div
      role="group"
      aria-label={t('lobby.filterLabel')}
      className="mb-5 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3"
    >
      {options.map((option, i) => {
        return (
          <button
            key={option.key ?? 'all'}
            type="button"
            aria-haspopup="dialog"
            onClick={() => onSelect(option.key)}
            className={`group hover-shine relative flex min-h-32 overflow-hidden rounded-3xl border bg-linear-to-br p-3 text-left shadow-[0_14px_30px_rgb(0_0_0/0.45)] transition duration-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-text motion-safe:hover:scale-[1.02] sm:min-h-32 sm:p-4 ${TINTS[i % TINTS.length]} border-gold/25 hover:border-gold/60 ${i === 0 ? 'col-span-2 lg:col-span-1' : ''}`}
          >
            {/* A fan of the category's posters on the right, lifting on hover. */}
            <span
              aria-hidden="true"
              className="absolute top-2 right-1 h-14 w-24 transition-transform duration-500 ease-out [--fan:12px] motion-safe:group-hover:scale-105 sm:top-1/2 sm:-right-3 sm:h-24 sm:w-40 sm:-translate-y-1/2 sm:[--fan:22px] sm:motion-safe:group-hover:-translate-y-[55%]"
            >
              {option.slugs.slice(0, 3).map((slug, j) => (
                <span
                  key={slug}
                  className="absolute top-0 right-6 h-12 w-16 overflow-hidden rounded-lg border border-gold/40 shadow-lg sm:h-20 sm:w-28 sm:rounded-xl"
                  style={{
                    transform: `rotate(${(j - 1) * 9}deg) translateX(calc(var(--fan) * ${1 - j}))`,
                    zIndex: 3 - Math.abs(j - 1),
                  }}
                >
                  <GamePosterScene
                    slug={slug}
                    name={option.names[j] ?? slug}
                    className="block size-full"
                  />
                </span>
              ))}
            </span>
            {/* Phones: the fan sits above the words (pt-12 keeps them clear of it); from sm it is beside them. */}
            <span className="relative z-10 mt-auto flex min-w-0 flex-col justify-end pt-12 sm:mt-0 sm:max-w-[60%] sm:pt-0">
              <span className="font-display text-base leading-tight font-bold break-words text-fg drop-shadow max-[359px]:text-sm sm:text-2xl">
                {option.name}
              </span>
              <span className="mt-1 text-xs font-semibold text-gold-light">
                {t('casino.gameCount', { count: option.count })}
              </span>
            </span>
          </button>
        )
      })}
    </div>
  )
}
