import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { TrophyIcon } from '@/shared/ui/icons'
import { Leaderboard } from './Leaderboard'

/** The home page's leaderboard: the top five, with a link to every board. */
export function LeaderboardCard() {
  const { t } = useTranslation()
  return (
    <section
      aria-labelledby="home-leaders"
      className="rounded-3xl border border-gold/30 bg-[linear-gradient(180deg,#120c06,#0a0f0c)] p-4 shadow-[0_18px_40px_rgb(0_0_0/0.5)] sm:p-5"
    >
      <div className="mb-3 flex items-center justify-between gap-2">
        <h2
          id="home-leaders"
          className="flex items-center gap-2 font-display text-xl font-bold sm:text-2xl"
        >
          <TrophyIcon className="size-5 text-gold" aria-hidden="true" />
          <span className="casino-gold">{t('leaders.title')}</span>
        </h2>
        <Link to="/leaderboards" className="text-sm font-semibold text-accent-text hover:underline">
          {t('leaders.all')}
        </Link>
      </div>
      <Leaderboard compact />
    </section>
  )
}
