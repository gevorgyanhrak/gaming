import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Card } from '@/shared/ui/Card'
import { Skeleton } from '@/shared/ui/Skeleton'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { fetchMyRooms, fetchMyStats, myRoomsKey, myStatsKey } from '../api'
import { recordCounts, winRate } from '../records'
import { GameRecord } from './GameRecord'
import { RoomSummary } from './RoomSummary'

/** Dashboard sections: games to resume and per-game statistics. Hidden while there is nothing to show. */
export function DashboardGames() {
  const { t, i18n } = useTranslation()
  const rooms = useQuery({
    queryKey: myRoomsKey,
    queryFn: ({ signal }) => fetchMyRooms(signal),
  })
  const stats = useQuery({
    queryKey: myStatsKey,
    queryFn: ({ signal }) => fetchMyStats(signal),
  })

  if (rooms.isPending || stats.isPending) return <Skeleton className="h-32 w-full" />

  return (
    <div className="grid gap-6">
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
        <Card title={t('dashboard.stats')} className="@container">
          {/* Wide card: one table row per game. */}
          <table className="hidden w-full text-sm @3xl:table">
            <thead className="text-left text-xs text-muted">
              <tr>
                <th scope="col" className="pb-2 font-medium">
                  {t('dashboard.game')}
                </th>
                {recordCounts(stats.data[0]!, t).map(({ key, label }) => (
                  <th key={key} scope="col" className="pb-2 pl-3 text-right font-medium">
                    {label}
                  </th>
                ))}
                <th scope="col" className="pb-2 pl-3 text-right font-medium">
                  {t('dashboard.winRate')}
                </th>
                <th scope="col" className="pb-2 pl-3 text-right font-medium">
                  {t('dashboard.streak')}
                </th>
                <th scope="col" className="pb-2 pl-3 text-right font-medium">
                  {t('dashboard.bestStreak')}
                </th>
              </tr>
            </thead>
            <tbody className="tabular-nums">
              {stats.data.map((row) => (
                <tr key={row.game.slug} className="border-t border-border">
                  <th scope="row" className="py-2.5 text-left font-normal">
                    <span className="flex items-center gap-2">
                      <GameIcon
                        slug={row.game.slug}
                        name={row.game.name}
                        className="size-5 text-gold"
                      />
                      {row.game.name}
                    </span>
                  </th>
                  {recordCounts(row, t).map(({ key, value }) => (
                    <td key={key} className="py-2.5 pl-3 text-right">
                      {value}
                    </td>
                  ))}
                  <td className="py-2.5 pl-3 text-right font-semibold">
                    {winRate(row, i18n.language)}
                  </td>
                  <td
                    className={`py-2.5 pl-3 text-right ${row.current_streak >= 2 ? 'text-accent-text' : ''}`}
                  >
                    {row.current_streak}
                  </td>
                  <td className="py-2.5 pl-3 text-right">{row.best_streak}</td>
                </tr>
              ))}
            </tbody>
          </table>

          {/* Narrow card: one block per game, with the numbers wrapping as needed. */}
          <ul className="@3xl:hidden">
            {stats.data.map((row) => (
              <li
                key={row.game.slug}
                className="border-t border-border py-3 first:border-t-0 first:pt-0"
              >
                <GameRecord row={row} />
              </li>
            ))}
          </ul>

          <p className="mt-3 text-xs text-muted">{t('dashboard.statsNote')}</p>
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
