import { useTranslation } from 'react-i18next'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'
import { useGames } from '../queries'
import { GameLobby } from './GameLobby'

/** The game catalogue by category, with its loading, error and empty states. */
export function GameGrid() {
  const { t } = useTranslation()
  const games = useGames()

  if (games.isPending) {
    return (
      <div data-testid="game-grid-loading">
        <div className="mb-8 flex gap-2">
          <Skeleton className="h-11 w-28 rounded-full" />
          <Skeleton className="h-11 w-36 rounded-full" />
          <Skeleton className="h-11 w-36 rounded-full" />
        </div>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
          {[0, 1, 2, 3, 4].map((i) => (
            <Skeleton key={i} className="aspect-[3/3.4] rounded-card" />
          ))}
        </div>
      </div>
    )
  }

  if (games.isError) {
    return <QueryErrorState error={games.error} onRetry={() => void games.refetch()} />
  }

  if (games.data.length === 0) {
    return <EmptyState title={t('catalog.empty')} />
  }

  return <GameLobby games={games.data} />
}
