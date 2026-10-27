import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { GameArtwork } from '@/features/catalog/components/GameArtwork'
import { PlayerCount } from '@/features/catalog/components/PlayerCount'
import { useGame } from '@/features/catalog/queries'
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
            <header className="flex flex-col gap-6 sm:flex-row sm:items-center">
              <GameArtwork game={game.data} className="aspect-[16/9] w-full rounded-2xl sm:w-64" />
              <div>
                <div className="flex flex-wrap items-center gap-3">
                  <h1 className="text-3xl font-bold">{game.data.name}</h1>
                  {game.data.is_playable ? null : (
                    <Badge tone="warning">{t('catalog.comingSoon')}</Badge>
                  )}
                </div>
                <p className="mt-2 text-muted">{game.data.description}</p>
                <p className="mt-3 text-sm font-medium text-muted">
                  <PlayerCount game={game.data} />
                </p>
              </div>
            </header>

            {game.data.is_playable ? null : (
              <p className="mt-8 rounded-xl border border-border bg-surface px-4 py-3 text-sm text-muted">
                {t('game.notPlayable')}
              </p>
            )}

            {game.data.modes && game.data.modes.length > 0 ? (
              <section className="mt-10">
                <h2 className="text-xl font-semibold">{t('game.modes')}</h2>
                <ul className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  {game.data.modes.map((mode) => (
                    <li key={mode.slug} className="rounded-xl border border-border bg-surface p-4">
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
