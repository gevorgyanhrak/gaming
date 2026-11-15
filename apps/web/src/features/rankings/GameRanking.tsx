import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { TrophyIcon } from '@/shared/ui/icons'
import { fetchGameRanking, gameRankingKey, RANKING_REFRESH_MS, RANKING_STALE_MS } from './api'
import { RankingList } from './RankingList'

/**
 * A game's Top 10 on its page: by points (each game on its own; AI players included, marked) or, for Poker and
 * Blackjack, by Fishkas. Nothing for a game without a board.
 */
export function GameRanking({ slug, name }: { slug: string; name: string }) {
  const { t, i18n } = useTranslation()
  const board = useQuery({
    queryKey: gameRankingKey(slug),
    queryFn: ({ signal }) => fetchGameRanking(slug, signal),
    staleTime: RANKING_STALE_MS,
    refetchInterval: RANKING_REFRESH_MS,
  })
  if (board.isSuccess && board.data.kind === null) return null
  const chips = board.data?.kind === 'chips'
  const format = (n: number) => n.toLocaleString(i18n.language)

  return (
    <section
      aria-labelledby={`ranking-${slug}`}
      className="rounded-3xl border border-gold/30 bg-[linear-gradient(180deg,#120c06,#0a0f0c)] p-4 shadow-[0_18px_40px_rgb(0_0_0/0.5)] sm:p-5"
    >
      <h2
        id={`ranking-${slug}`}
        className="mb-1 flex items-center gap-2 font-display text-xl font-bold"
      >
        <TrophyIcon className="size-5 text-gold" aria-hidden="true" />
        <span className="casino-gold">{t('rankings.gameTitle', { game: name })}</span>
      </h2>
      <p className="mb-3 text-xs text-muted">
        {chips ? t('rankings.chipsHint') : t('rankings.pointsHint')}
      </p>
      <RankingList
        rows={board.data?.data}
        loading={board.isPending}
        error={board.isError}
        empty={t('rankings.empty')}
        label={t('rankings.gameTitle', { game: name })}
        value={(row) =>
          chips ? (
            <>
              {format(row.value)}
              <FishkaIcon className="size-4" />
            </>
          ) : (
            t('rankings.points', { count: row.value, formatted: format(row.value) })
          )
        }
      />
    </section>
  )
}
