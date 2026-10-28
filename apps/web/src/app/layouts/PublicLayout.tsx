import { useCallback, useEffect, useRef, useState, ViewTransition, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, NavLink, Outlet, useLocation } from 'react-router'
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
import {
  BackIcon,
  CloseIcon,
  GridIcon,
  HistoryIcon,
  HomeIcon,
  MenuIcon,
  SettingsIcon,
  UserIcon,
} from '@/shared/ui/icons'
import { Skeleton } from '@/shared/ui/Skeleton'

const navLink = ({ isActive }: { isActive: boolean }) =>
  `rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors ${
    isActive ? 'bg-surface-raised text-fg' : 'text-muted hover:text-fg'
  }`

const sideLink = ({ isActive }: { isActive: boolean }) =>
  `relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
    isActive
      ? 'bg-gold/15 text-fg before:absolute before:inset-y-2 before:left-0 before:w-0.5 before:rounded-full before:bg-gold'
      : 'text-muted hover:bg-surface-raised hover:text-fg'
  }`

const tabLink = ({ isActive }: { isActive: boolean }) =>
  `flex flex-1 flex-col items-center gap-1 py-2 text-[0.7rem] font-medium ${
    isActive ? 'text-gold' : 'text-muted'
  }`

const iconButton =
  'flex size-10 shrink-0 items-center justify-center rounded-xl border border-gold/25 bg-surface-raised/60 text-fg transition-colors hover:border-gold/60'

/**
 * The app as a window over a card club: header with the menu (or a way back from a table), the
 * page, and the footer, all inside a brass-edged frame. The menu slides in from the left; phones
 * also get a tab bar.
 */
export function PublicLayout() {
  const { t } = useTranslation()
  const config = usePublicConfig()
  const user = useCurrentUser()
  const { pathname } = useLocation()
  const [menuOpen, setMenuOpen] = useState(false)
  const [menuPath, setMenuPath] = useState(pathname)
  const closeMenu = useCallback(() => setMenuOpen(false), [])
  useFavicon(config.data?.favicon_url)

  // Following a link closes the menu.
  if (menuPath !== pathname) {
    setMenuPath(pathname)
    setMenuOpen(false)
  }

  // Staff can still use the app during maintenance, to check it before reopening.
  const inMaintenance = Boolean(config.data?.maintenance.enabled) && !user.data?.can_access_admin

  const home = user.data ? '/dashboard' : '/'
  const atTable = pathname.startsWith('/rooms/')

  return (
    <div className="club-room min-h-dvh sm:p-4 lg:p-6">
      <div
        className={`club-frame mx-auto flex min-h-dvh max-w-[96rem] flex-col overflow-clip sm:min-h-[calc(100dvh-2rem)] sm:rounded-[1.75rem] lg:min-h-[calc(100dvh-3rem)] ${
          user.data ? 'pb-16 lg:pb-0' : ''
        }`}
      >
        <header className="sticky top-0 z-20 border-b border-gold/15 bg-[#1f140f]/80 backdrop-blur">
          <div className="flex h-16 items-center justify-between gap-3 px-3 sm:h-20 sm:px-6">
            <div className="flex min-w-0 items-center gap-3">
              {atTable ? (
                <Link to={home} className={iconButton} aria-label={t('nav.back')}>
                  <BackIcon />
                </Link>
              ) : (
                <button
                  type="button"
                  className={iconButton}
                  aria-label={t('nav.menu')}
                  aria-expanded={menuOpen}
                  aria-controls="main-menu"
                  onClick={() => setMenuOpen(true)}
                >
                  <MenuIcon />
                </button>
              )}
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
            </div>
            <nav aria-label={t('nav.main')} className="flex items-center gap-1 sm:gap-2">
              {user.isPending ? (
                <Skeleton className="size-9 rounded-full" />
              ) : user.data ? (
                <UserMenu user={user.data} />
              ) : (
                <>
                  <NavLink to="/login" className={navLink}>
                    {t('nav.login')}
                  </NavLink>
                  <ButtonLink to="/register" variant="gold" className="px-3 py-2 whitespace-nowrap">
                    {t('nav.register')}
                  </ButtonLink>
                </>
              )}
            </nav>
          </div>
        </header>

        {user.data ? <VerificationBanner user={user.data} /> : null}
        {user.data && !inMaintenance ? <ActiveGamesBar /> : null}

        <main className="flex-1">
          {inMaintenance ? (
            <MaintenanceScreen message={config.data?.maintenance.message ?? null} />
          ) : (
            // Lets pages hand shared elements (a game's art and title) from one to the next.
            <ViewTransition>
              <div>
                <Outlet />
              </div>
            </ViewTransition>
          )}
        </main>

        <footer className="border-t border-gold/15 py-6">
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
                        if (isLocale(locale)) void updateSettings({ locale }).catch(() => undefined)
                      }
                    : undefined
                }
              />
            ) : null}
          </Container>
        </footer>
      </div>

      {menuOpen ? (
        <Drawer onClose={closeMenu}>
          <SideNav home={home} signedIn={Boolean(user.data)} />
        </Drawer>
      ) : null}

      {user.data ? (
        <nav
          aria-label={t('nav.shortcuts')}
          className="fixed inset-x-0 bottom-0 z-20 flex border-t border-gold/20 bg-[#1f140f]/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden"
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

/** The menu panel: slides in from the left over a dimmed page; Escape or a click outside closes it. */
function Drawer({ onClose, children }: { onClose: () => void; children: ReactNode }) {
  const { t } = useTranslation()
  const panel = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const opener = document.activeElement as HTMLElement | null
    panel.current?.focus()
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
      opener?.focus()
    }
  }, [onClose])

  return (
    <div className="fixed inset-0 z-40">
      <div
        aria-hidden="true"
        className="absolute inset-0 bg-black/60 backdrop-blur-sm motion-safe:animate-[fade-in_200ms_ease-out]"
        onClick={onClose}
      />
      <div
        id="main-menu"
        ref={panel}
        role="dialog"
        aria-modal="true"
        aria-label={t('nav.menu')}
        tabIndex={-1}
        className="club-frame absolute inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col overflow-y-auto rounded-r-3xl px-3 py-5 outline-none motion-safe:animate-[drawer-in_260ms_cubic-bezier(0.2,0.8,0.2,1)]"
      >
        <div className="mb-4 flex justify-end">
          <button
            type="button"
            className={iconButton}
            aria-label={t('nav.close')}
            onClick={onClose}
          >
            <CloseIcon />
          </button>
        </div>
        {children}
      </div>
    </div>
  )
}

/** The menu: sections, every game with its icon, and the player's own pages. */
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
