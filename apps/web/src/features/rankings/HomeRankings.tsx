import { useQuery } from '@tanstack/react-query'
import { useId, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { TrophyIcon } from '@/shared/ui/icons'
import {
  fetchPlatformRanking,
  platformRankingKey,
  RANKING_REFRESH_MS,
  RANKING_STALE_MS,
  type PlatformBoard,
} from './api'
import { RankingList } from './RankingList'

/** The home page's leaderboard: the Top 10 people by level or by Fishkas, switched with a select (no reload). */
export function HomeRankings() {
  const { t, i18n } = useTranslation()
  const selectId = useId()
  const [board, setBoard] = useState<PlatformBoard>('level')
  const rows = useQuery({
    queryKey: platformRankingKey(board),
    queryFn: ({ signal }) => fetchPlatformRanking(board, signal),
    staleTime: RANKING_STALE_MS,
    refetchInterval: RANKING_REFRESH_MS,
    placeholderData: (previous) => previous,
  })
  const format = (n: number) => n.toLocaleString(i18n.language)
  const title = board === 'level' ? t('rankings.byLevel') : t('rankings.byChips')

  return (
    <section
      aria-labelledby="home-rankings"
      className="rounded-3xl border border-gold/30 bg-[linear-gradient(180deg,#120c06,#0a0f0c)] p-4 shadow-[0_18px_40px_rgb(0_0_0/0.5)] sm:p-5"
    >
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2
          id="home-rankings"
          className="flex items-center gap-2 font-display text-xl font-bold sm:text-2xl"
        >
          <TrophyIcon className="size-5 text-gold" aria-hidden="true" />
          <span className="casino-gold">{t('rankings.title')}</span>
        </h2>
        <label htmlFor={selectId} className="sr-only">
          {t('rankings.show')}
        </label>
        <select
          id={selectId}
          value={board}
          onChange={(e) => setBoard(e.target.value as PlatformBoard)}
          className="min-h-10 rounded-full border border-gold/40 bg-black/50 px-4 text-sm font-semibold text-gold-light focus:border-gold focus:outline-none"
        >
          <option value="level">{t('rankings.byLevel')}</option>
          <option value="chips">{t('rankings.byChips')}</option>
        </select>
      </div>
      <RankingList
        rows={rows.data}
        loading={rows.isPending}
        error={rows.isError}
        empty={t('rankings.empty')}
        label={title}
        value={(row) =>
          board === 'chips' ? (
            <>
              {format(row.value)}
              <FishkaIcon className="size-4" />
            </>
          ) : (
            t('rankings.level', { level: row.value })
          )
        }
      />
    </section>
  )
}
