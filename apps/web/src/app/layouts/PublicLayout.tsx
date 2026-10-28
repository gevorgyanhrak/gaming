import { useTranslation } from 'react-i18next'
import { Link, NavLink, Outlet } from 'react-router'
import { updateSettings } from '@/features/account/api'
import { UserMenu } from '@/features/auth/components/UserMenu'
import { VerificationBanner } from '@/features/auth/components/VerificationBanner'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { useGames } from '@/features/catalog/queries'
import { ActiveGamesBar } from '@/features/play/components/ActiveGamesBar'
import { MaintenanceScreen } from '@/features/platform/MaintenanceScreen'
import { useFavicon } from '@/features/platform/useFavicon'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { isLocale } from '@/shared/i18n'
import { LanguageSwitcher } from '@/shared/i18n/LanguageSwitcher'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'
import { GridIcon, HistoryIcon, HomeIcon, SettingsIcon, UserIcon } from '@/shared/ui/icons'
import { Skeleton } from '@/shared/ui/Skeleton'

const navLink = ({ isActive }: { isActive: boolean }) =>
  `rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors ${
    isActive ? 'bg-surface-raised text-fg' : 'text-muted hover:text-fg'
  }`

const sideLink = ({ isActive }: { isActive: boolean }) =>
  `relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
    isActive
      ? 'bg-accent/15 text-fg before:absolute before:inset-y-1.5 before:left-0 before:w-0.5 before:rounded-full before:bg-gold'
      : 'text-muted hover:bg-surface-raised hover:text-fg'
  }`

const tabLink = ({ isActive }: { isActive: boolean }) =>
  `flex flex-1 flex-col items-center gap-1 py-2 text-[0.7rem] font-medium ${
    isActive ? 'text-gold' : 'text-muted'
  }`

export function PublicLayout() {
  const { t } = useTranslation()
  const config = usePublicConfig()
  const user = useCurrentUser()
  useFavicon(config.data?.favicon_url)

  // Staff can still use the app during maintenance, to check it before reopening.
  const inMaintenance = Boolean(config.data?.maintenance.enabled) && !user.data?.can_access_admin

  const home = user.data ? '/dashboard' : '/'

  return (
    <div className="flex min-h-dvh flex-col">
      <header className="sticky top-0 z-20 border-b border-border bg-bg/85 backdrop-blur">
        <div className="flex h-16 items-center justify-between gap-4 px-4 sm:px-6">
          <Link to={home} className="flex min-w-0 items-center gap-2.5 font-bold text-fg">
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
            {/* Signed-in players have the tab bar on phones and everyone has the sidebar on desktops. */}
            {user.data ? null : (
              <NavLink
                to="/games"
                className={(state) => `${navLink(state)} hidden sm:block lg:hidden`}
              >
                {t('nav.games')}
              </NavLink>
            )}
            {user.isPending ? (
              <Skeleton className="size-9 rounded-full" />
            ) : user.data ? (
              <UserMenu user={user.data} />
            ) : (
              <>
                <NavLink to="/login" className={navLink}>
                  {t('nav.login')}
                </NavLink>
                <ButtonLink to="/register" className="px-3 py-2 whitespace-nowrap">
                  {t('nav.register')}
                </ButtonLink>
              </>
            )}
          </nav>
        </div>
      </header>

      {user.data ? <VerificationBanner user={user.data} /> : null}
      {user.data && !inMaintenance ? <ActiveGamesBar /> : null}

      <div className="flex flex-1">
        <aside className="sticky top-16 hidden h-[calc(100dvh-4rem)] w-60 shrink-0 overflow-y-auto border-r border-border bg-surface/40 px-3 py-5 lg:block">
          <SideNav home={home} signedIn={Boolean(user.data)} />
        </aside>

        <div className={`flex min-w-0 flex-1 flex-col ${user.data ? 'pb-16 lg:pb-0' : ''}`}>
          <main className="lobby-glow flex-1">
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
                    ? t('footer.copyright', {
                        year: new Date().getFullYear(),
                        name: config.data.name,
                      })
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
                          if (isLocale(locale))
                            void updateSettings({ locale }).catch(() => undefined)
                        }
                      : undefined
                  }
                />
              ) : null}
            </Container>
          </footer>
        </div>
      </div>

      {user.data ? (
        <nav
          aria-label={t('nav.shortcuts')}
          className="fixed inset-x-0 bottom-0 z-20 flex border-t border-border bg-bg/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden"
        >
          <NavLink to="/dashboard" className={tabLink}>
            <HomeIcon />
            {t('nav.home')}
          </NavLink>
          <NavLink to="/games" className={tabLink}>
            <GridIcon />
            {t('nav.games')}
          </NavLink>
          <NavLink to="/history" className={tabLink}>
            <HistoryIcon />
            {t('nav.historyShort')}
          </NavLink>
          <NavLink to="/profile" className={tabLink}>
            <UserIcon />
            {t('nav.profile')}
          </NavLink>
        </nav>
      ) : null}
    </div>
  )
}

/** The desktop sidebar: sections, every game with its icon, and the player's own pages. */
function SideNav({ home, signedIn }: { home: string; signedIn: boolean }) {
  const { t } = useTranslation()
  const games = useGames()

  return (
    <nav aria-label={t('nav.sections')} className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <NavLink to={home} end className={sideLink}>
          <HomeIcon />
          {t('nav.home')}
        </NavLink>
        <NavLink to="/games" end className={sideLink}>
          <GridIcon />
          {t('nav.games')}
        </NavLink>
      </div>

      <div className="flex flex-col gap-1">
        <p className="px-3 pb-1 text-xs font-semibold tracking-wider text-muted uppercase">
          {t('nav.games')}
        </p>
        {games.isPending
          ? Array.from({ length: 5 }, (_, i) => <Skeleton key={i} className="mx-3 my-1.5 h-5" />)
          : (games.data ?? []).map((game) => (
              <NavLink key={game.slug} to={`/games/${game.slug}`} className={sideLink}>
                <GameIcon slug={game.slug} name={game.name} />
                <span className="truncate">{game.name}</span>
              </NavLink>
            ))}
      </div>

      {signedIn ? (
        <div className="flex flex-col gap-1">
          <NavLink to="/history" className={sideLink}>
            <HistoryIcon />
            {t('nav.history')}
          </NavLink>
          <NavLink to="/profile" className={sideLink}>
            <UserIcon />
            {t('nav.profile')}
          </NavLink>
          <NavLink to="/settings" className={sideLink}>
            <SettingsIcon />
            {t('nav.settings')}
          </NavLink>
        </div>
      ) : null}
    </nav>
  )
}
