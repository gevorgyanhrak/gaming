import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Card } from '@/shared/ui/Card'
import { Skeleton } from '@/shared/ui/Skeleton'
import { fetchMyRooms, fetchMyStats } from '../api'
import { RoomSummary } from './RoomSummary'

/** Dashboard sections: games to resume and per-game statistics. Hidden while there is nothing to show. */
export function DashboardGames() {
  const { t } = useTranslation()
  const rooms = useQuery({
    queryKey: ['me', 'rooms'],
    queryFn: ({ signal }) => fetchMyRooms(signal),
  })
  const stats = useQuery({
    queryKey: ['me', 'stats'],
    queryFn: ({ signal }) => fetchMyStats(signal),
  })

  if (rooms.isPending || stats.isPending) return <Skeleton className="h-32 w-full" />

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      {rooms.data && rooms.data.length > 0 ? (
        <Card title={t('dashboard.inProgress')}>
          <ul className="space-y-2">
            {rooms.data.map((room) => (
              <li key={room.id}>
                <RoomSummary room={room} />
              </li>
            ))}
          </ul>
        </Card>
      ) : null}
      {stats.data && stats.data.length > 0 ? (
        <Card title={t('dashboard.stats')}>
          <table className="w-full text-sm">
            <thead className="text-left text-muted">
              <tr>
                <th className="pb-2 font-medium">{t('dashboard.game')}</th>
                <th className="pb-2 font-medium">{t('dashboard.played')}</th>
                <th className="pb-2 font-medium">{t('dashboard.wins')}</th>
                <th className="pb-2 font-medium">{t('dashboard.losses')}</th>
                <th className="pb-2 font-medium">{t('dashboard.draws')}</th>
              </tr>
            </thead>
            <tbody>
              {stats.data.map((row) => (
                <tr key={row.game.slug} className="border-t border-border">
                  <td className="py-2">{row.game.name}</td>
                  <td className="py-2">{row.played}</td>
                  <td className="py-2">{row.wins}</td>
                  <td className="py-2">{row.losses}</td>
                  <td className="py-2">{row.draws}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <Link
            to="/history"
            className="mt-4 inline-block text-sm font-medium text-accent-text hover:underline"
          >
            {t('dashboard.history')}
          </Link>
        </Card>
      ) : null}
    </div>
  )
}
