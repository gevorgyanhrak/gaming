import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { GamePoster } from '@/features/catalog/components/GamePoster'
import { GameShowcase } from '@/features/casino/GameShowcase'
import { MAIN_GAMES } from '@/features/casino/mainGames'
import { FavoritesShelf } from '@/features/favorites/FavoritesShelf'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { LiveBigWins } from '@/features/wins/LiveBigWins'
import { HomeRankings } from '@/features/rankings/HomeRankings'
import { useGames, type GameSummary } from '@/features/catalog/queries'
import { fetchMyStats, myStatsKey } from '@/features/play/api'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { openDailyBonus, useDailyBonus } from '@/features/wallet/dailyBonus'
import { GiftTiles } from '@/features/gifts/FreeGifts'
import { Skeleton } from '@/shared/ui/Skeleton'
import { DashboardGames } from '@/features/play/components/DashboardGames'
import { LevelCard } from '@/features/levels/components/LevelCard'
import { Avatar } from '@/shared/ui/Avatar'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'
import { NightSky } from '@/features/home/NightSky'
import { formatDate } from '@/shared/lib/dates'

/**
 * Signed-in home: games to resume, statistics, and the catalog.
 */
export function DashboardPage() {
  const { t, i18n } = useTranslation()
  const { data: user } = useCurrentUser()

  if (!user) return null

  const name = user.profile.display_name ?? user.username
  const memberSince = formatDate(user.created_at, i18n.language, 'long')

  return (
    <>
      <title>{t('dashboard.title')}</title>
      {/* A compact welcome strip: who you are, your level (owner: in the banner), and today's free Fishkas. */}
      <section className="relative isolate overflow-hidden border-b border-white/6">
        <NightSky />
        <Container className="py-3 lg:py-4">
          <div className="flex flex-wrap items-center gap-x-5 gap-y-3">
            <div className="flex min-w-0 flex-1 items-center gap-3">
              <Avatar
                url={user.profile.avatar_url}
                name={name}
                size="md"
                level={user.level}
                frame={user.looks.frame}
              />
              <div className="min-w-0">
                <h1 className="text-gold-gradient font-display text-lg leading-tight font-bold break-words sm:text-xl lg:text-2xl">
                  {t('dashboard.welcome', { name })}
                </h1>
                <p className="truncate text-xs text-muted">
                  {t('dashboard.memberSince', { date: memberSince })}
                </p>
              </div>
            </div>
            <LevelCard compact className="w-full bg-black/25 sm:w-80 lg:w-96" />
            <ButtonLink to="/games" variant="gold" className="shrink-0">
              {t('dashboard.play')}
            </ButtonLink>
          </div>
          <ul className="mt-3 grid grid-cols-3 gap-2" aria-label={t('dashboard.perks')}>
            <DailyBonusTile tileClassName={PERK_TILE} />
            <GiftTiles tileClassName={PERK_TILE} />
          </ul>
        </Container>
      </section>

      <Container className="space-y-5 pt-5 pb-12">
        <FavoritesShelf />
        <section id="showcase" aria-labelledby="top-games" className="scroll-mt-24">
          <h2 id="top-games" className="mb-3 text-2xl font-bold text-fg">
            {t('casino.topGames')}
          </h2>
          <GameShowcase />
        </section>
        <section id="games" aria-labelledby="all-games" className="scroll-mt-24">
          <h2 id="all-games" className="mb-3 text-2xl font-bold text-fg">
            {t('casino.allGames')}
          </h2>
          <GameGrid banners exclude={MAIN_GAMES} />
        </section>
        <TryNext />
        <div className="grid gap-5 lg:grid-cols-2 [&>*]:min-w-0">
          <LiveBigWins />
          <HomeRankings />
        </div>
        <DashboardGames />
      </Container>
    </>
  )
}

/** A perk tile in the welcome strip: icon, label and state, all one button. */
const PERK_TILE =
  'flex min-h-14 w-full flex-col items-center gap-1 rounded-2xl border border-gold/30 bg-black/35 px-1.5 py-2 text-center text-fg transition-colors hover:border-gold/70 hover:bg-black/50 aria-disabled:cursor-default aria-disabled:hover:border-gold/30 aria-disabled:hover:bg-black/35 sm:flex-row sm:gap-3 sm:px-3 sm:text-left'

/** Today's daily bonus as a tile: opens the bonus popup (collect, or see the streak). */
function DailyBonusTile({ tileClassName }: { tileClassName: string }) {
  const { t, i18n } = useTranslation()
  const bonus = useDailyBonus()
  if (bonus.isLoading) {
    return (
      <li>
        <Skeleton className="h-14 rounded-2xl" />
      </li>
    )
  }
  if (!bonus.data) return null
  const { claimed, amount } = bonus.data

  return (
    <li>
      <button type="button" className={tileClassName} onClick={openDailyBonus}>
        <FishkaIcon className="size-7 shrink-0" />
        <span className="min-w-0 leading-tight sm:text-left">
          <span className="block text-[11px] font-semibold sm:truncate sm:text-sm">
            {t('daily.title')}
          </span>
          <span
            className={`block text-[11px] sm:truncate sm:text-xs ${claimed ? 'text-muted' : 'font-bold text-gold-light'}`}
          >
            {claimed ? t('daily.collected') : `+${amount.toLocaleString(i18n.language)}`}
          </span>
        </span>
      </button>
    </li>
  )
}

/** Durak is always offered here (owner, 2026-11-16) besides the games played least. */
const ALWAYS_TRY = ['durak']
const TRY_COUNT = 3

/** Games to try: the ones the player played least, Durak always among them; poster cards like the catalog's. */
function TryNext() {
  const { t } = useTranslation()
  const games = useGames()
  const stats = useQuery({ queryKey: myStatsKey, queryFn: ({ signal }) => fetchMyStats(signal) })
  if (!games.data || !stats.data || games.data.length < 2) return null

  const played = (slug: string) => stats.data.find((row) => row.game.slug === slug)?.played ?? 0
  const playable = games.data.filter((g) => g.is_playable)
  const pinned = playable.filter((g) => ALWAYS_TRY.includes(g.slug))
  const rest = playable
    .filter((g) => !ALWAYS_TRY.includes(g.slug))
    .sort((a, b) => played(a.slug) - played(b.slug))
  const picks: GameSummary[] = [...pinned, ...rest].slice(0, TRY_COUNT)
  if (picks.length === 0) return null

  return (
    <section aria-labelledby="try-next" className="club-panel-glass rounded-panel p-4 sm:p-5">
      <h2 id="try-next" className="mb-3 text-lg font-bold">
        {t('home.tryNext')}
      </h2>
      <ul className="grid grid-cols-3 gap-2 sm:gap-3">
        {picks.map((game) => (
          <li key={game.slug} className="flex">
            <div className="flex w-full flex-col">
              <GamePoster game={game} morph={false} />
            </div>
          </li>
        ))}
      </ul>
    </section>
  )
}
