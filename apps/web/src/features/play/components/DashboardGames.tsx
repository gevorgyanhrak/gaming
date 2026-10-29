import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Card } from '@/shared/ui/Card'
import { Skeleton } from '@/shared/ui/Skeleton'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { fetchMyRooms, fetchMyStats, myRoomsKey, type GameStat } from '../api'
import { RoomSummary } from './RoomSummary'

/** Dashboard sections: games to resume and per-game statistics. Hidden while there is nothing to show. */
export function DashboardGames() {
  const { t, i18n } = useTranslation()
  const rooms = useQuery({
    queryKey: myRoomsKey,
    queryFn: ({ signal }) => fetchMyRooms(signal),
  })
  const stats = useQuery({
    queryKey: ['me', 'stats'],
    queryFn: ({ signal }) => fetchMyStats(signal),
  })

  if (rooms.isPending || stats.isPending) return <Skeleton className="h-32 w-full" />

  const percent = new Intl.NumberFormat(i18n.language, { style: 'percent' })
  const winRate = (row: GameStat) => (row.played > 0 ? percent.format(row.wins / row.played) : '—')
  const counts = (row: GameStat) => [
    { key: 'played', label: t('dashboard.played'), value: row.played },
    { key: 'wins', label: t('dashboard.wins'), value: row.wins },
    { key: 'losses', label: t('dashboard.losses'), value: row.losses },
    { key: 'draws', label: t('dashboard.draws'), value: row.draws },
    { key: 'timedOut', label: t('dashboard.timedOut'), value: row.abandoned },
  ]
  const streakTone = (row: GameStat) => (row.current_streak >= 2 ? 'text-accent-text' : '')

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
                {counts(stats.data[0]!).map(({ key, label }) => (
                  <th key={key} scope="col" className="pb-2 text-right font-medium">
                    {label}
                  </th>
                ))}
                <th scope="col" className="pb-2 text-right font-medium">
                  {t('dashboard.winRate')}
                </th>
                <th scope="col" className="pb-2 text-right font-medium">
                  {t('dashboard.streak')}
                </th>
                <th scope="col" className="pb-2 text-right font-medium">
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
                  {counts(row).map(({ key, value }) => (
                    <td key={key} className="py-2.5 text-right">
                      {value}
                    </td>
                  ))}
                  <td className="py-2.5 text-right font-semibold">{winRate(row)}</td>
                  <td className={`py-2.5 text-right ${streakTone(row)}`}>{row.current_streak}</td>
                  <td className="py-2.5 text-right">{row.best_streak}</td>
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
                <div className="flex items-start justify-between gap-3">
                  <span className="flex items-center gap-2 font-medium">
                    <GameIcon
                      slug={row.game.slug}
                      name={row.game.name}
                      className="size-5 text-gold"
                    />
                    {row.game.name}
                  </span>
                  <span className="text-right">
                    <span className="block text-lg leading-tight font-bold tabular-nums">
                      {winRate(row)}
                    </span>
                    <span className="block text-xs text-muted">{t('dashboard.winRate')}</span>
                  </span>
                </div>
                <dl className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm">
                  {counts(row).map(({ key, label, value }) => (
                    <div key={key} className="flex gap-1 whitespace-nowrap">
                      <dt className="text-muted">{label}</dt>
                      <dd className="font-semibold tabular-nums">{value}</dd>
                    </div>
                  ))}
                </dl>
                {row.best_streak > 0 ? (
                  <p className={`mt-1 text-sm text-muted ${streakTone(row)}`}>
                    {t('dashboard.streakLine', {
                      current: row.current_streak,
                      best: row.best_streak,
                    })}
                  </p>
                ) : null}
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
