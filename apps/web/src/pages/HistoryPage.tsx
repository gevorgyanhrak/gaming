import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { fetchMyGames, myGamesKey } from '@/features/play/api'
import { RoomSummary } from '@/features/play/components/RoomSummary'
import { Button } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'

export function HistoryPage() {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const games = useQuery({
    queryKey: myGamesKey(page),
    queryFn: ({ signal }) => fetchMyGames(page, signal),
  })

  return (
    <Container className="py-10">
      <title>{t('history.title')}</title>
      <h1 className="mb-6 text-3xl font-bold">{t('history.title')}</h1>
      {games.isPending ? (
        <Skeleton className="h-48 w-full" />
      ) : games.isError ? (
        <QueryErrorState error={games.error} onRetry={() => void games.refetch()} />
      ) : games.data.data.length === 0 ? (
        <EmptyState title={t('history.empty')} />
      ) : (
        <div className="space-y-3">
          <ul className="space-y-2">
            {games.data.data.map((room) => (
              <li key={room.id}>
                <RoomSummary room={room} />
              </li>
            ))}
          </ul>
          {games.data.meta.last_page > 1 ? (
            <div className="flex items-center justify-between">
              <Button
                variant="secondary"
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
              >
                {t('history.previous')}
              </Button>
              <span className="text-sm text-muted">
                {t('history.page', { page, total: games.data.meta.last_page })}
              </span>
              <Button
                variant="secondary"
                disabled={page >= games.data.meta.last_page}
                onClick={() => setPage((p) => p + 1)}
              >
                {t('history.next')}
              </Button>
            </div>
          ) : null}
        </div>
      )}
    </Container>
  )
}
