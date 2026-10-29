import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameScene } from '@/features/catalog/components/GameArt'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { DashboardGames } from '@/features/play/components/DashboardGames'
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
    <Container className="space-y-10 py-10">
      <title>{t('dashboard.title')}</title>
      <section className="relative overflow-hidden rounded-3xl border border-gold/20 bg-linear-to-r from-accent/25 via-surface to-surface p-6 shadow-xl shadow-black/30 sm:p-8">
        <GameScene
          slug="poker"
          className="pointer-events-none absolute inset-y-0 right-0 hidden h-full w-1/2 [mask-image:linear-gradient(to_right,transparent,black)] opacity-30 md:block"
        />
        <div className="relative flex flex-col gap-5 sm:flex-row sm:items-center">
          <div className="flex items-center gap-4">
            <Avatar url={user.profile.avatar_url} name={name} size="md" />
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
      </section>

      <section>
        <h2 className="mb-6 text-2xl font-bold">{t('dashboard.gamesHeading')}</h2>
        <GameGrid />
      </section>

      <DashboardGames />
    </Container>
  )
}
