import { useTranslation } from 'react-i18next'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'
import { useGames } from '../queries'
import { GameCard } from './GameCard'

const grid = 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3'

export function GameGrid() {
  const { t } = useTranslation()
  const games = useGames()

  if (games.isPending) {
    return (
      <div className={grid} data-testid="game-grid-loading">
        {Array.from({ length: 3 }, (_, i) => (
          <Skeleton key={i} className="h-72 rounded-2xl" />
        ))}
      </div>
    )
  }

  if (games.isError) {
    return <QueryErrorState error={games.error} onRetry={() => void games.refetch()} />
  }

  if (games.data.length === 0) {
    return <EmptyState title={t('catalog.empty')} />
  }

  return (
    <ul className={grid}>
      {games.data.map((game) => (
        <li key={game.slug} className="flex">
          <GameCard game={game} />
        </li>
      ))}
    </ul>
  )
}
