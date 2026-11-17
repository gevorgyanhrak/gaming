import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { GameArtwork } from '@/features/catalog/components/GameArtwork'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { useGames } from '@/features/catalog/queries'
import { themeOf } from '@/games/themes'
import { Skeleton } from '@/shared/ui/Skeleton'
import { MAIN_GAMES } from './mainGames'

/**
 * The three main games as large cards, each in its own room (games/themes.ts) slowly drifting behind its art,
 * with its description and a Play button; they rise in one after another and lift with a gold glow on hover.
 * Each wears its TOP place. The other games follow in the lobby below (smaller posters, by category). Names and
 * descriptions come from the catalog (admin-managed).
 */
export function GameShowcase() {
  const { t } = useTranslation()
  const games = useGames()

  if (games.isPending) {
    return (
      <div className="grid gap-4 md:grid-cols-3">
        {MAIN_GAMES.map((slug) => (
          <Skeleton key={slug} className="h-80 rounded-3xl" />
        ))}
      </div>
    )
  }
  const list = games.data ?? []
  const main = MAIN_GAMES.map((slug) => list.find((g) => g.slug === slug)).filter(
    (g) => g !== undefined,
  )

  return (
    <div>
      <ul className="grid gap-4 md:grid-cols-3">
        {main.map((game, i) => {
          const theme = themeOf(game.slug)
          return (
            <li
              key={game.slug}
              className="motion-safe:animate-rise"
              style={{ animationDelay: `${120 + i * 140}ms` }}
            >
              <Link
                to={`/games/${game.slug}`}
                className="group hover-shine relative flex h-full flex-col overflow-hidden rounded-[1.6rem] border border-white/10 bg-night-800 shadow-[0_24px_50px_-20px_rgb(0_0_0/0.85)] transition duration-300 hover:border-gold/60 hover:shadow-[0_30px_60px_-20px_rgb(0_0_0/0.9),0_0_0_1px_rgb(245_192_79/0.35),0_0_48px_-8px_rgb(245_192_79/0.4)] focus-visible:outline-2 focus-visible:outline-accent-text motion-safe:hover:scale-[1.02]"
              >
                {theme ? (
                  <span
                    aria-hidden="true"
                    className="absolute inset-0 -z-0 bg-cover opacity-35 transition-transform duration-[2500ms] ease-out group-hover:scale-110"
                    style={{
                      backgroundImage: `${theme.overlay}, url(${JSON.stringify(theme.image)})`,
                      backgroundPosition: 'center',
                    }}
                  />
                ) : null}
                {/* A soft night fade over the room, so the words below stay clear. */}
                <span
                  aria-hidden="true"
                  className="absolute inset-0 bg-linear-to-b from-transparent via-night-800/60 to-night-800"
                />
                {/* The place: a brass plate with a slow shine. */}
                <span className="top-badge absolute top-5 left-5 z-10 inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-black tracking-widest text-[#2a1b0c] uppercase shadow-[0_4px_14px_rgb(0_0_0/0.5)]">
                  <span aria-hidden="true">♛</span>
                  {t('casino.top', { n: i + 1 })}
                </span>
                <span className="relative block p-3 pb-0">
                  <GameArtwork
                    game={game}
                    className="aspect-[16/11] w-full rounded-[1.15rem] shadow-[0_14px_30px_-10px_rgb(0_0_0/0.8)] ring-1 ring-white/10 transition duration-500 group-hover:brightness-110"
                  />
                </span>
                <span className="relative flex flex-1 flex-col gap-2 p-4">
                  <span className="flex items-center gap-2">
                    <GameIcon slug={game.slug} name={game.name} className="size-5 text-gold" />
                    <span className="font-display text-xl font-bold text-fg">{game.name}</span>
                  </span>
                  <span className="line-clamp-2 text-sm text-muted">{game.description}</span>
                  <span className="mt-auto inline-flex min-h-11 items-center justify-center gap-2 rounded-control bg-(image:--btn-gold) px-5 text-sm font-bold text-(--btn-gold-ink) shadow-(--btn-gold-shadow) transition group-hover:brightness-110">
                    {t('casino.playGame', { game: game.name })}
                    <span
                      aria-hidden="true"
                      className="transition-transform group-hover:translate-x-1"
                    >
                      →
                    </span>
                  </span>
                </span>
              </Link>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
