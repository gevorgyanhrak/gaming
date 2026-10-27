import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { Avatar } from '@/shared/ui/Avatar'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'

/**
 * Signed-in home. Recent games, statistics and online counts join this page once
 * gameplay exists (Phase 5); until then it shows only what is real.
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
      <section className="flex flex-col gap-5 rounded-2xl border border-border bg-surface p-6 sm:flex-row sm:items-center">
        <Avatar url={user.profile.avatar_url} name={name} size="lg" />
        <div className="flex-1">
          <h1 className="text-2xl font-bold">{t('dashboard.welcome', { name })}</h1>
          <p className="mt-1 text-sm text-muted">
            {t('dashboard.memberSince', { date: memberSince })}
          </p>
        </div>
        <div className="flex gap-2">
          <ButtonLink to="/profile" variant="secondary">
            {t('nav.profile')}
          </ButtonLink>
          <ButtonLink to="/settings" variant="secondary">
            {t('nav.settings')}
          </ButtonLink>
        </div>
      </section>

      <section>
        <h2 className="mb-6 text-2xl font-bold">{t('dashboard.gamesHeading')}</h2>
        <GameGrid />
      </section>
    </Container>
  )
}
