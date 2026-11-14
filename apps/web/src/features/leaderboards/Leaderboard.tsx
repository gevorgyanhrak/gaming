import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { Avatar } from '@/shared/ui/Avatar'
import { Segmented } from '@/shared/ui/Segmented'
import { Skeleton } from '@/shared/ui/Skeleton'
import { fetchLeaderboard, leaderboardKey, type Period } from './api'

const PERIODS: Period[] = ['day', 'week', 'month']
/** The main games get their own boards (owner, 2026-11-14); the full page offers every game. */
const MAIN = ['blackjack', 'poker', 'blot']
const MEDALS = ['#f6d27a', '#d6dbe3', '#d39360']

/**
 * A leaderboard: who won the most Fishkas today, this week or this month — overall or in one game — from the
 * server's game results (people only). Rank, avatar, name, the game, the total. `compact` shows the top five
 * without the game picker (the home page); the full board has every game.
 */
export function Leaderboard({
  compact = false,
  games = MAIN,
}: {
  compact?: boolean
  games?: { slug: string; name: string }[] | string[]
}) {
  const { t, i18n } = useTranslation()
  const [period, setPeriod] = useState<Period>('week')
  const [game, setGame] = useState<string | null>(null)
  const board = useQuery({
    queryKey: leaderboardKey(period, game),
    queryFn: ({ signal }) => fetchLeaderboard(period, game, signal),
  })
  const rows = (board.data ?? []).slice(0, compact ? 5 : 10)
  const format = (n: number) => n.toLocaleString(i18n.language)
  const choices = games.map((g) =>
    typeof g === 'string' ? { slug: g, name: t(`leaders.game.${g}` as 'leaders.game.blot') } : g,
  )

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center gap-2">
        <Segmented
          label={t('leaders.period')}
          value={period}
          options={PERIODS.map((p) => ({ value: p, label: t(`leaders.periods.${p}`) }))}
          onChange={setPeriod}
        />
        {compact ? null : (
          <div role="group" aria-label={t('leaders.gameFilter')} className="flex flex-wrap gap-1.5">
            {[{ slug: null, name: t('leaders.all') }, ...choices].map((g) => (
              <button
                key={g.slug ?? 'all'}
                type="button"
                aria-pressed={game === g.slug}
                onClick={() => setGame(g.slug)}
                className="flex min-h-9 items-center gap-1.5 rounded-full border border-gold/25 bg-black/30 px-3 text-sm font-semibold transition hover:border-gold/60 aria-pressed:border-gold aria-pressed:bg-gold/15 aria-pressed:text-gold-light"
              >
                {g.slug ? (
                  <GameIcon slug={g.slug} name={g.name} className="size-4 text-gold" />
                ) : null}
                {g.name}
              </button>
            ))}
          </div>
        )}
      </div>

      {board.isPending ? (
        <div className="space-y-2">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-12 rounded-xl" />
          ))}
        </div>
      ) : board.isError ? (
        <p className="text-sm text-muted">{t('leaders.error')}</p>
      ) : rows.length === 0 ? (
        <p className="rounded-xl bg-black/30 px-4 py-3 text-sm text-fg/80">{t('leaders.empty')}</p>
      ) : (
        <ol className="space-y-1.5">
          {rows.map((row) => (
            <li
              key={`${row.rank}-${row.player.username}`}
              className={`flex items-center gap-3 rounded-xl border px-3 py-2 ${row.rank <= 3 ? 'border-gold/30 bg-gold/[0.07]' : 'border-white/5 bg-black/30'}`}
            >
              <span
                className="flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-extrabold tabular-nums"
                style={
                  row.rank <= 3 ? { background: MEDALS[row.rank - 1], color: '#2a1b0c' } : undefined
                }
              >
                <span className="sr-only">{t('leaders.rank')} </span>
                {row.rank}
              </span>
              <Avatar
                url={row.player.avatar_url}
                name={row.player.name}
                level={row.player.level}
                size="sm"
              />
              <Link
                to={`/u/${row.player.username}`}
                className="min-w-0 flex-1 truncate text-sm font-bold hover:underline"
              >
                {row.player.name}
              </Link>
              {row.game ? (
                <span
                  className="hidden items-center gap-1 text-xs text-muted sm:flex"
                  title={row.game.name}
                >
                  <GameIcon
                    slug={row.game.slug}
                    name={row.game.name}
                    className="size-4 text-gold"
                  />
                  {row.game.name}
                </span>
              ) : null}
              <span className="flex items-center gap-1 font-extrabold text-gold-light tabular-nums">
                {format(row.total)}
                <FishkaIcon className="size-4" />
              </span>
            </li>
          ))}
        </ol>
      )}
    </div>
  )
}
