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
        <div className="flex gap-4 overflow-hidden lg:gap-5">
          {[0, 1, 2, 3].map((i) => (
            <Skeleton
              key={i}
              className="aspect-[4/5] w-[78%] shrink-0 rounded-2xl sm:w-[44%] lg:aspect-[16/10] lg:w-[calc((100%-3.75rem)/4)]"
            />
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
