import { useTranslation } from 'react-i18next'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'
import { useGames } from '../queries'
import { GameCarousel } from './GameCarousel'

/** The game catalogue as a carousel, with its loading, error and empty states. */
export function GameGrid() {
  const { t } = useTranslation()
  const games = useGames()

  if (games.isPending) {
    return (
      <div data-testid="game-grid-loading" className="flex justify-center">
        <Skeleton className="aspect-[4/5] w-[min(38rem,80%)] rounded-[1.75rem] sm:aspect-[16/10]" />
      </div>
    )
  }

  if (games.isError) {
    return <QueryErrorState error={games.error} onRetry={() => void games.refetch()} />
  }

  if (games.data.length === 0) {
    return <EmptyState title={t('catalog.empty')} />
  }

  return <GameCarousel games={games.data} />
}
