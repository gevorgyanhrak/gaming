import { useEffect, useRef, useState, ViewTransition, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, NavLink, Outlet, useLocation } from 'react-router'
import { updateSettings } from '@/features/account/api'
import { UserMenu } from '@/features/auth/components/UserMenu'
import { VerificationBanner } from '@/features/auth/components/VerificationBanner'
import { useCurrentUser } from '@/features/auth/hooks'
import { BigWinCelebration } from '@/features/wins/BigWinCelebration'
import { AchievementToast } from '@/features/achievements/AchievementToast'
import { SiteSearch } from '@/features/search/SiteSearch'
import { SoundToggle } from '@/features/sound/SoundToggle'
import { OnlineCounter } from '@/features/presence/OnlineCounter'
import { useOnlinePresence } from '@/shared/realtime/useOnlinePresence'
import { ActiveGamesBar } from '@/features/play/components/ActiveGamesBar'
import { FishkaBalance } from '@/features/wallet/components/FishkaBalance'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { DailyBonusDialog } from '@/features/wallet/components/DailyBonusDialog'
import { LevelUpDialog } from '@/features/levels/components/LevelUpDialog'
import { useLevelSync } from '@/features/levels/hooks'
import { OutOfFishkasDialog } from '@/features/wallet/components/OutOfFishkasDialog'
import { useWalletSync } from '@/features/wallet/hooks'
import { MaintenanceScreen } from '@/features/platform/MaintenanceScreen'
import { useFavicon } from '@/features/platform/useFavicon'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
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
  StarIcon,
  TrophyIcon,
  UserIcon,
} from '@/shared/ui/icons'
import { Skeleton } from '@/shared/ui/Skeleton'
import { useRefreshOnLanguageChange } from '../useRefreshOnLanguageChange'

const tabLink = ({ isActive }: { isActive: boolean }) =>
  `relative flex min-h-16 flex-1 flex-col items-center justify-center gap-1 text-xs font-semibold transition-colors ${
    isActive
      ? 'text-gold-light before:absolute before:top-0 before:h-0.5 before:w-8 before:rounded-full before:bg-gold before:shadow-[0_0_12px_var(--color-gold)]'
      : 'text-muted hover:text-fg'
  }`

const iconButton =
  'flex size-11 shrink-0 items-center justify-center rounded-control border border-white/10 bg-white/5 text-fg transition-colors hover:border-gold/50 hover:bg-white/10'

/** The header's sections (from 1280 px; phones and tablets have the tab bar and the ☰ menu). */
function SectionLinks({ home }: { home: string }) {
  const { t } = useTranslation()
  const { pathname, search } = useLocation()
  const casino = pathname === '/games' && new URLSearchParams(search).get('category') === 'casino'
  const links = [
    { to: home, label: t('nav.home'), active: pathname === home },
    { to: '/games', label: t('nav.games'), active: pathname.startsWith('/games') && !casino },
    { to: '/games?category=casino', label: t('nav.casino'), active: casino },
    { to: '/ranks', label: t('nav.ranks'), active: pathname === '/ranks' },
    { to: '/leaderboards', label: t('leaders.title'), active: pathname === '/leaderboards' },
  ]

  return (
    <nav aria-label={t('nav.sections')} className="hidden items-center gap-0.5 xl:flex">
      {links.map((link) => (
        <Link
          key={link.to}
          to={link.to}
          aria-current={link.active ? 'page' : undefined}
          className={`relative rounded-full px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition-colors duration-150 ${
            link.active ? 'bg-white/8 text-fg' : 'text-muted hover:bg-white/5 hover:text-fg'
          }`}
        >
          {link.label}
          {/* The current section: a short gold line under it. */}
          <span
            aria-hidden="true"
            className={`absolute inset-x-4 -bottom-[0.55rem] h-0.5 rounded-full bg-linear-to-r from-gold-light to-gold shadow-[0_0_10px_var(--color-gold)] transition-opacity duration-200 ${
              link.active ? 'opacity-100' : 'opacity-0'
            }`}
          />
        </Link>
      ))}
    </nav>
  )
}

/**
 * The app's shell (TASK-048): a header with the ☰ menu (MenuDrawer), the balance and account, the page,
 * and a bottom bar on phones and tablets. At a table the room shows its own bar (TableBar) instead.
 */
export function PublicLayout() {
  const { t } = useTranslation()
  useRefreshOnLanguageChange()
  const config = usePublicConfig()
  const user = useCurrentUser()
  useOnlinePresence(user.data?.id)
  const { pathname } = useLocation()
  const wide = useMediaQuery('(min-width: 1024px)')
  useFavicon(config.data?.favicon_url)

  // Staff can still use the app during maintenance, to check it before reopening.
  const inMaintenance = Boolean(config.data?.maintenance.enabled) && !user.data?.can_access_admin

  const home = user.data ? '/dashboard' : '/'
  const atTable = pathname.startsWith('/rooms/')
  // A game table keeps its own look (owner, 2026-11-17): the classic tokens for the whole page, popups included.
  useEffect(() => {
    document.body.classList.toggle('classic-scope', atTable)
    return () => document.body.classList.remove('classic-scope')
  }, [atTable])
  const showBar = !wide && !atTable
  // The ☰ menu (owner, 2026-11-14: no side bar of games; the account's pages and the language, in a menu).
  const [menuOpen, setMenuOpen] = useState(false)

  const brand = (
    <>
      <img
        src={config.data?.logo_url ?? '/favicon.svg'}
        alt=""
        className="size-8 shrink-0 object-contain"
      />
      {config.data ? (
        // Phones keep the header for search and the balance: the logo alone, the name for screen readers.
        <span className="sr-only truncate sm:not-sr-only">{config.data.name}</span>
      ) : (
        <Skeleton className="hidden h-5 w-32 sm:block" />
      )}
    </>
  )

  const language = config.data ? (
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
  ) : null

  return (
    <div className="club-room isolate flex min-h-dvh">
      <BigWinCelebration />
      <AchievementToast />

      <MenuDrawer
        open={menuOpen}
        onClose={() => setMenuOpen(false)}
        home={home}
        signedIn={Boolean(user.data)}
        language={language}
      />

      <div className={`flex min-h-dvh min-w-0 flex-1 flex-col ${showBar ? 'pb-tabbar' : ''}`}>
        {/* At a table the room draws its own bar (TableBar); during maintenance the header stays. */}
        {atTable && !inMaintenance ? null : (
          <header className="sticky top-0 z-20 flex h-header items-center justify-between gap-1.5 border-b border-white/6 bg-night-900/75 px-3 backdrop-blur-xl backdrop-saturate-150 sm:gap-3 sm:px-6">
            {/* A thin gold light along the bottom edge. */}
            <span
              aria-hidden="true"
              className="pointer-events-none absolute inset-x-0 -bottom-px h-px bg-linear-to-r from-transparent via-gold/45 to-transparent"
            />
            {/* Phones show the logo alone, which must not shrink; from sm the name may truncate. */}
            <div className="flex shrink-0 items-center gap-2 sm:min-w-0 sm:shrink sm:gap-3">
              {atTable ? (
                <Link to={home} className={iconButton} aria-label={t('nav.back')}>
                  <BackIcon />
                </Link>
              ) : null}
              {atTable ? null : (
                <button
                  type="button"
                  onClick={() => setMenuOpen(true)}
                  aria-label={t('nav.menu')}
                  aria-expanded={menuOpen}
                  className={iconButton}
                >
                  <MenuIcon />
                </button>
              )}
              {/* Under 380 px the logo gives way to search and the balance (☰ and the tab bar lead home). */}
              <Link
                to={home}
                className="flex min-w-0 items-center gap-2.5 font-display text-[0.95rem] font-semibold tracking-tight text-fg max-[379px]:hidden"
              >
                {brand}
              </Link>
            </div>
            {atTable ? null : <SectionLinks home={home} />}
            {atTable ? null : <SiteSearch />}
            <nav aria-label={t('nav.main')} className="flex items-center gap-1 sm:gap-2">
              <OnlineCounter />
              <SoundToggle />
              {user.isPending ? (
                <Skeleton className="size-9 rounded-full" />
              ) : user.data ? (
                <>
                  {/* Keyed by account: another one signing in starts fresh (no "+N" for the switch). */}
                  <FishkaBalance key={user.data.id} />
                  <UserMenu user={user.data} />
                </>
              ) : (
                <>
                  <NavLink
                    to="/login"
                    // Phones have "Log in" in the tab bar: the header keeps room for Sign up.
                    className="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium whitespace-nowrap text-fg hover:text-gold-light max-sm:hidden"
                  >
                    {t('nav.login')}
                  </NavLink>
                  <ButtonLink to="/register" variant="gold" className="whitespace-nowrap">
                    {t('nav.register')}
                  </ButtonLink>
                </>
              )}
            </nav>
          </header>
        )}

        {user.data ? <VerificationBanner user={user.data} /> : null}
        {user.data ? <WalletSync key={user.data.id} /> : null}
        {user.data && !inMaintenance ? (
          <div className="bg-emerald-900">
            <ActiveGamesBar />
          </div>
        ) : null}

        <main className={`flex-1 ${atTable ? 'table-room' : ''}`}>
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

        <footer className="border-t border-(--chrome-edge) bg-emerald-900 py-8">
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
            {language}
          </Container>
        </footer>
      </div>

      {showBar ? (
        <nav
          aria-label={t('nav.shortcuts')}
          data-tabbar
          className="fixed inset-x-0 bottom-0 z-20 flex border-t border-white/8 bg-night-900/85 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl"
        >
          <NavLink to={home} end className={tabLink}>
            <HomeIcon />
            {t('nav.home')}
          </NavLink>
          <NavLink to="/games" className={tabLink}>
            <GridIcon />
            {t('nav.games')}
          </NavLink>
          {user.data ? (
            <>
              <NavLink to="/wallet" className={tabLink}>
                <FishkaIcon className="size-6" />
                {t('nav.walletShort')}
              </NavLink>
              <NavLink to="/ranks" className={tabLink}>
                <TrophyIcon />
                {t('nav.ranks')}
              </NavLink>
              <NavLink to="/profile" className={tabLink}>
                <UserIcon />
                {t('nav.profile')}
              </NavLink>
            </>
          ) : user.isPending ? null : (
            <NavLink to="/login" className={tabLink}>
              <UserIcon />
              {t('nav.login')}
            </NavLink>
          )}
        </nav>
      ) : null}
    </div>
  )
}

/**
 * The ☰ menu: a panel from the left with the main pages, the player's account pages and the language. Closes on
 * Escape, a tap outside or a choice; the page behind stays as it was.
 */
function MenuDrawer({
  open,
  onClose,
  home,
  signedIn,
  language,
}: {
  open: boolean
  onClose: () => void
  home: string
  signedIn: boolean
  language: ReactNode
}) {
  const { t } = useTranslation()
  const config = usePublicConfig()
  const { pathname } = useLocation()
  const panelRef = useRef<HTMLDivElement>(null)
  const [openedAt, setOpenedAt] = useState(pathname)
  if (open && openedAt !== pathname) {
    setOpenedAt(pathname)
    onClose()
  }

  useEffect(() => {
    if (!open) return
    panelRef.current?.querySelector<HTMLElement>('a, button')?.focus()
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open, onClose])

  if (!open) return null
  const link = (to: string, label: string, icon: ReactNode, end = false) => (
    <NavLink
      to={to}
      end={end}
      onClick={onClose}
      className="flex min-h-11 items-center gap-3 rounded-control px-3 text-sm font-semibold text-fg hover:bg-emerald-800/70 aria-[current=page]:bg-gold/15 aria-[current=page]:text-gold-light"
    >
      <span className="flex size-6 shrink-0 items-center justify-center text-gold">{icon}</span>
      {label}
    </NavLink>
  )

  return (
    <div className="fixed inset-0 z-50">
      <button
        type="button"
        aria-label={t('nav.closeMenu')}
        onClick={onClose}
        className="absolute inset-0 bg-black/60 motion-safe:animate-[fade-in_150ms_ease-out]"
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-label={t('nav.menu')}
        className="absolute inset-y-0 left-0 flex w-[min(20rem,85vw)] flex-col gap-4 border-r border-gold/25 bg-emerald-900 p-3 shadow-[0_0_40px_rgb(0_0_0/0.6)] motion-safe:animate-[drawer-in_200ms_ease-out]"
      >
        <div className="flex items-center justify-between gap-2 px-1">
          <Link
            to={home}
            onClick={onClose}
            className="flex min-w-0 items-center gap-2.5 font-bold text-fg"
          >
            <img
              src={config.data?.logo_url ?? '/favicon.svg'}
              alt=""
              className="size-8 shrink-0 object-contain"
            />
            <span className="truncate">{config.data?.name}</span>
          </Link>
          <button
            type="button"
            onClick={onClose}
            aria-label={t('nav.closeMenu')}
            className={iconButton}
          >
            <CloseIcon />
          </button>
        </div>
        <nav
          aria-label={t('nav.sections')}
          className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto"
        >
          <div className="flex flex-col gap-1">
            {link(home, t('nav.home'), <HomeIcon />, true)}
            {link('/games', t('nav.games'), <GridIcon />, true)}
            {link('/leaderboards', t('leaders.title'), <TrophyIcon />)}
          </div>
          {signedIn ? (
            <div className="flex flex-col gap-1 border-t border-gold/15 pt-3">
              <p className="px-3 pb-1 text-xs font-semibold tracking-wider text-muted uppercase">
                {t('nav.account')}
              </p>
              {link('/profile', t('nav.profile'), <UserIcon />)}
              {link('/wallet', t('nav.wallet'), <FishkaIcon className="size-6" />)}
              {link('/ranks', t('nav.ranks'), <StarIcon className="size-5" />)}
              {link('/history', t('nav.history'), <HistoryIcon />)}
              {link('/settings', t('nav.settings'), <SettingsIcon />)}
            </div>
          ) : (
            <div className="flex flex-col gap-2 border-t border-gold/15 px-1 pt-3">
              <ButtonLink to="/login" variant="secondary" onClick={onClose}>
                {t('nav.login')}
              </ButtonLink>
              <ButtonLink to="/register" variant="gold" onClick={onClose}>
                {t('nav.register')}
              </ButtonLink>
            </div>
          )}
          <div className="mt-auto border-t border-gold/15 px-3 pt-3">{language}</div>
        </nav>
      </div>
    </div>
  )
}

/**
 * Balance and level updates from the server, the level-up and daily bonus popups and the "out of
 * Fishkas" popup, while signed in.
 */
function WalletSync() {
  useWalletSync()
  useLevelSync()
  return (
    <>
      <LevelUpDialog />
      <DailyBonusDialog />
      <OutOfFishkasDialog />
    </>
  )
}
