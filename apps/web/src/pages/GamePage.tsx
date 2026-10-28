import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { GameArtwork } from '@/features/catalog/components/GameArtwork'
import { PlayerCount } from '@/features/catalog/components/PlayerCount'
import { useGame } from '@/features/catalog/queries'
import { PlayPanel } from '@/features/play/components/PlayPanel'
import { isApiError } from '@/shared/api/errors'
import { Badge } from '@/shared/ui/Badge'
import { splitParagraphs } from '@/shared/lib/paragraphs'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'

export function GamePage() {
  const { t } = useTranslation()
  const { slug = '' } = useParams()
  const game = useGame(slug)

  return (
    <Container className="py-10">
      <Link to="/games" className="text-sm font-medium text-accent-text hover:underline">
        ← {t('game.back')}
      </Link>

      <div className="mt-6">
        {game.isPending ? (
          <div data-testid="game-loading" className="space-y-4">
            <Skeleton className="h-10 w-1/3" />
            <Skeleton className="h-5 w-2/3" />
            <Skeleton className="h-40 w-full" />
          </div>
        ) : game.isError ? (
          isApiError(game.error) && game.error.isNotFound ? (
            <EmptyState title={t('game.notFound')} />
          ) : (
            <QueryErrorState error={game.error} onRetry={() => void game.refetch()} />
          )
        ) : (
          <article>
            <title>{game.data.name}</title>
            <header className="relative overflow-hidden rounded-3xl border border-border shadow-xl shadow-black/40">
              <GameArtwork game={game.data} className="absolute inset-0 h-full w-full" />
              <div
                aria-hidden="true"
                className="absolute inset-0 bg-linear-to-r from-bg via-bg/85 to-bg/20"
              />
              <div className="relative max-w-2xl px-6 py-10 sm:px-10 sm:py-14">
                <div className="flex flex-wrap items-center gap-3">
                  <h1 className="text-4xl font-extrabold tracking-tight text-gold sm:text-5xl">
                    {game.data.name}
                  </h1>
                  {game.data.is_playable ? null : (
                    <Badge tone="warning">{t('catalog.comingSoon')}</Badge>
                  )}
                </div>
                <p className="mt-3 text-fg/85 sm:text-lg">{game.data.description}</p>
                <p className="mt-4 inline-flex rounded-full border border-white/10 bg-black/40 px-3 py-1 text-sm font-medium text-fg/90">
                  <PlayerCount game={game.data} />
                </p>
              </div>
            </header>

            {game.data.is_playable ? (
              <div className="mt-8">
                <PlayPanel game={game.data} />
              </div>
            ) : (
              <p className="mt-8 rounded-xl border border-border bg-surface px-4 py-3 text-sm text-muted">
                {t('game.notPlayable')}
              </p>
            )}

            {game.data.modes && game.data.modes.length > 0 ? (
              <section className="mt-10">
                <h2 className="text-xl font-semibold">{t('game.modes')}</h2>
                <ul className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  {game.data.modes.map((mode) => (
                    <li
                      key={mode.slug}
                      className="rounded-2xl border border-border bg-surface/80 p-4"
                    >
                      <p className="font-medium">{mode.name}</p>
                      {mode.description ? (
                        <p className="mt-1 text-sm text-muted">{mode.description}</p>
                      ) : null}
                      <p className="mt-2 text-xs font-medium tracking-wide text-muted uppercase">
                        <PlayerCount game={mode} />
                      </p>
                    </li>
                  ))}
                </ul>
              </section>
            ) : null}

            <section className="mt-10 max-w-3xl">
              <h2 className="text-xl font-semibold">{t('game.rules')}</h2>
              <div className="mt-4 space-y-4 leading-relaxed text-fg/90">
                {splitParagraphs(game.data.rules).map((paragraph, index) => (
                  <p key={index}>{paragraph}</p>
                ))}
              </div>
            </section>
          </article>
        )}
      </div>
    </Container>
  )
}
