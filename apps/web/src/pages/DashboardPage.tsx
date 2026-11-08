import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { HomeHeroArt } from '@/features/catalog/components/HomeArt'
import { DashboardGames } from '@/features/play/components/DashboardGames'
import { LevelCard } from '@/features/levels/components/LevelCard'
import { Avatar } from '@/shared/ui/Avatar'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'

/**
 * Signed-in home: games to resume, statistics, and the catalog.
 */
export function DashboardPage() {
  const { t, i18n } = useTranslation()
  const { data: user } = useCurrentUser()

  if (!user) return null

  const name = user.profile.display_name ?? user.username
  const memberSince = new Intl.DateTimeFormat(i18n.language, { dateStyle: 'long' }).format(
    new Date(user.created_at),
  )

  return (
    <>
      <title>{t('dashboard.title')}</title>
      <section className="home-scope-emerald home-hero border-b border-gold/20">
        <Container className="grid items-center gap-8 py-10 lg:grid-cols-[1fr_22rem]">
          <div className="min-w-0">
            <div className="relative flex flex-col gap-5 sm:flex-row sm:items-center">
              <div className="flex items-center gap-4">
                <Avatar
                  url={user.profile.avatar_url}
                  name={name}
                  size="md"
                  level={user.level}
                  frame={user.looks.frame}
                />
                <div className="min-w-0">
                  <h1 className="text-gold-gradient text-2xl font-extrabold sm:text-3xl">
                    {t('dashboard.welcome', { name })}
                  </h1>
                  <p className="mt-0.5 text-xs text-muted">
                    {t('dashboard.memberSince', { date: memberSince })}
                  </p>
                </div>
              </div>
              <div className="flex flex-wrap gap-2 sm:ml-auto">
                <ButtonLink to="/games" variant="gold">
                  {t('dashboard.play')}
                </ButtonLink>
                <ButtonLink to="/profile" variant="secondary">
                  {t('nav.profile')}
                </ButtonLink>
                <ButtonLink to="/settings" variant="secondary">
                  {t('nav.settings')}
                </ButtonLink>
              </div>
            </div>
            <LevelCard className="relative mt-5 max-w-xl bg-black/25" />
          </div>
          <HomeHeroArt className="hidden w-full lg:block" />
        </Container>
      </section>

      <section className="home-scope-ivory py-10 sm:py-12">
        <Container className="space-y-10">
          <div>
            <h2 className="mb-6 text-3xl font-extrabold tracking-tight text-fg">
              {t('dashboard.gamesHeading')}
            </h2>
            <GameGrid />
          </div>
          <DashboardGames />
        </Container>
      </section>
    </>
  )
}
