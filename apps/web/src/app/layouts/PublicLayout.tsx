import { useTranslation } from 'react-i18next'
import { Link, NavLink, Outlet } from 'react-router'
import { updateSettings } from '@/features/account/api'
import { UserMenu } from '@/features/auth/components/UserMenu'
import { VerificationBanner } from '@/features/auth/components/VerificationBanner'
import { useCurrentUser } from '@/features/auth/hooks'
import { MaintenanceScreen } from '@/features/platform/MaintenanceScreen'
import { useFavicon } from '@/features/platform/useFavicon'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { isLocale } from '@/shared/i18n'
import { LanguageSwitcher } from '@/shared/i18n/LanguageSwitcher'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'

const navLink = ({ isActive }: { isActive: boolean }) =>
  `rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
    isActive ? 'bg-surface-raised text-fg' : 'text-muted hover:text-fg'
  }`

export function PublicLayout() {
  const { t } = useTranslation()
  const config = usePublicConfig()
  const user = useCurrentUser()
  useFavicon(config.data?.favicon_url)

  // Staff can still use the app during maintenance, to check it before reopening.
  const inMaintenance = Boolean(config.data?.maintenance.enabled) && !user.data?.can_access_admin

  return (
    <div className="flex min-h-dvh flex-col">
      <header className="sticky top-0 z-10 border-b border-border bg-bg/85 backdrop-blur">
        <Container className="flex h-16 items-center justify-between gap-4">
          <Link
            to={user.data ? '/dashboard' : '/'}
            className="flex min-w-0 items-center gap-2.5 font-bold text-fg"
          >
            <img
              src={config.data?.logo_url ?? '/favicon.svg'}
              alt=""
              className="size-8 object-contain"
            />
            {config.data ? (
              <span className="truncate">{config.data.name}</span>
            ) : (
              <Skeleton className="h-5 w-32" />
            )}
          </Link>
          <nav aria-label={t('nav.main')} className="flex items-center gap-1 sm:gap-2">
            <NavLink to="/games" className={navLink}>
              {t('nav.games')}
            </NavLink>
            {user.isPending ? (
              <Skeleton className="size-9 rounded-full" />
            ) : user.data ? (
              <UserMenu user={user.data} />
            ) : (
              <>
                <NavLink to="/login" className={navLink}>
                  {t('nav.login')}
                </NavLink>
                <ButtonLink to="/register" className="px-3 py-2">
                  {t('nav.register')}
                </ButtonLink>
              </>
            )}
          </nav>
        </Container>
      </header>

      {user.data ? <VerificationBanner user={user.data} /> : null}

      <main className="flex-1">
        {inMaintenance ? (
          <MaintenanceScreen message={config.data?.maintenance.message ?? null} />
        ) : (
          <Outlet />
        )}
      </main>

      <footer className="border-t border-border py-6">
        <Container className="flex flex-col items-center justify-between gap-4 text-sm text-muted sm:flex-row">
          <div className="flex flex-col items-center gap-2 sm:flex-row sm:gap-6">
            <span>
              {config.data
                ? t('footer.copyright', { year: new Date().getFullYear(), name: config.data.name })
                : null}
            </span>
            {config.data && config.data.pages.length > 0 ? (
              <nav aria-label={t('footer.pages')} className="flex gap-4">
                {config.data.pages.map((page) => (
                  <Link key={page.slug} to={`/pages/${page.slug}`} className="hover:text-fg">
                    {page.title}
                  </Link>
                ))}
              </nav>
            ) : null}
          </div>
          {config.data ? (
            <LanguageSwitcher
              serverLocales={config.data.supported_locales}
              // Signed-in users keep their choice across devices.
              onChange={
                user.data
                  ? (locale) => {
                      if (isLocale(locale)) void updateSettings({ locale }).catch(() => undefined)
                    }
                  : undefined
              }
            />
          ) : null}
        </Container>
      </footer>
    </div>
  )
}
