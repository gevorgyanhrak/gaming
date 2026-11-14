import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { GameArtwork } from '@/features/catalog/components/GameArtwork'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { useGames } from '@/features/catalog/queries'
import { themeOf } from '@/games/themes'
import { Skeleton } from '@/shared/ui/Skeleton'

/** The platform's main games (owner, 2026-11-14), in this order; the others are one row below. */
const MAIN_GAMES = ['blackjack', 'poker', 'blot'] as const

/**
 * The three main games as large cards, each in its own room (games/themes.ts) slowly drifting behind its art,
 * with its description and a Play button; they rise in one after another and lift with a gold glow on hover.
 * Then every other game as a compact link. Names and descriptions come from the catalog (admin-managed).
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
  const others = list.filter((g) => !(MAIN_GAMES as readonly string[]).includes(g.slug))

  return (
    <div className="space-y-6">
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
                className="group relative flex h-full flex-col overflow-hidden rounded-3xl border border-gold/30 bg-black/50 shadow-[0_18px_40px_rgb(0_0_0/0.55)] transition duration-300 hover:border-gold/70 hover:shadow-[0_24px_60px_rgb(0_0_0/0.6),0_0_0_3px_rgb(226_184_79/0.25),0_0_40px_rgb(226_184_79/0.25)] focus-visible:outline-2 focus-visible:outline-accent-text motion-safe:hover:-translate-y-1.5"
              >
                {theme ? (
                  <span
                    aria-hidden="true"
                    className="absolute inset-0 -z-0 bg-cover opacity-70 transition-transform duration-[2500ms] ease-out group-hover:scale-110"
                    style={{
                      backgroundImage: `${theme.overlay}, url(${JSON.stringify(theme.image)})`,
                      backgroundPosition: 'center',
                    }}
                  />
                ) : null}
                <span className="relative block p-3 pb-0">
                  <GameArtwork
                    game={game}
                    className="aspect-[16/10] w-full rounded-2xl shadow-lg transition duration-500 group-hover:brightness-110"
                  />
                </span>
                <span className="relative flex flex-1 flex-col gap-2 p-4">
                  <span className="flex items-center gap-2">
                    <GameIcon slug={game.slug} name={game.name} className="size-6 text-gold" />
                    <span className="casino-gold font-display text-2xl font-bold">{game.name}</span>
                  </span>
                  <span className="line-clamp-2 text-sm text-fg/80">{game.description}</span>
                  <span className="mt-auto inline-flex min-h-11 items-center justify-center rounded-control bg-linear-to-b from-[#f7d774] to-[#c99a32] px-5 text-sm font-extrabold tracking-wide text-[#2a1b0c] uppercase shadow-[0_6px_18px_rgb(0_0_0/0.45)] transition group-hover:brightness-110 motion-safe:group-hover:animate-gold-glow">
                    {t('casino.playGame', { game: game.name })}
                  </span>
                </span>
              </Link>
            </li>
          )
        })}
      </ul>

      {others.length > 0 ? (
        <section aria-labelledby="more-games">
          <h2
            id="more-games"
            className="mb-2 text-sm font-semibold tracking-wider text-gold-light uppercase"
          >
            {t('casino.moreGames')}
          </h2>
          <ul className="flex flex-wrap gap-2">
            {others.map((game) => (
              <li key={game.slug}>
                <Link
                  to={`/games/${game.slug}`}
                  className="flex min-h-11 items-center gap-2 rounded-full border border-gold/25 bg-black/40 px-3.5 text-sm font-semibold transition hover:border-gold/60 hover:bg-black/60 motion-safe:hover:-translate-y-0.5"
                >
                  <GameIcon slug={game.slug} name={game.name} className="size-4 text-gold" />
                  {game.name}
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  )
}
