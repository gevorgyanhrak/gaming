import { useState, ViewTransition, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, NavLink, Outlet, useLocation } from 'react-router'
import { updateSettings } from '@/features/account/api'
import { UserMenu } from '@/features/auth/components/UserMenu'
import { VerificationBanner } from '@/features/auth/components/VerificationBanner'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { useGames } from '@/features/catalog/queries'
import { HomeBackdrop } from '@/features/catalog/components/HomeBackdrop'
import { BigWinCelebration } from '@/features/wins/BigWinCelebration'
import { AchievementToast } from '@/features/achievements/AchievementToast'
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
  ChevronIcon,
  GridIcon,
  HistoryIcon,
  HomeIcon,
  SettingsIcon,
  TrophyIcon,
  UserIcon,
} from '@/shared/ui/icons'
import { Skeleton } from '@/shared/ui/Skeleton'
import { Tooltip } from '@/shared/ui/Tooltip'
import { useRefreshOnLanguageChange } from '../useRefreshOnLanguageChange'

const railLink = ({ isActive }: { isActive: boolean }) =>
  `relative flex min-h-11 items-center gap-3 rounded-control px-3 text-sm font-medium transition-colors duration-150 ${
    isActive
      ? 'bg-emerald-800 text-fg shadow-[inset_2px_0_0_var(--color-gold)]'
      : 'text-muted hover:bg-emerald-800/70 hover:text-fg'
  }`

const tabLink = ({ isActive }: { isActive: boolean }) =>
  `flex min-h-16 flex-1 flex-col items-center justify-center gap-1 text-xs font-medium ${
    isActive ? 'text-gold-light' : 'text-muted'
  }`

const iconButton =
  'flex size-11 shrink-0 items-center justify-center rounded-control border border-border-strong/50 bg-emerald-800 text-fg transition-colors hover:border-gold-light'

const readRail = (): boolean | null => {
  try {
    const stored = localStorage.getItem('rail')
    return stored === null ? null : stored === 'icons'
  } catch {
    return null
  }
}

/**
 * The app's shell (TASK-048): a left rail from 1024 px, a header with the balance and account, the page,
 * and a bottom bar on phones and tablets. At a table the room shows its own bar (TableBar) instead.
 */
export function PublicLayout() {
  const { t } = useTranslation()
  useRefreshOnLanguageChange()
  const config = usePublicConfig()
  const user = useCurrentUser()
  const { pathname } = useLocation()
  const wide = useMediaQuery('(min-width: 1024px)')
  useFavicon(config.data?.favicon_url)

  // Staff can still use the app during maintenance, to check it before reopening.
  const inMaintenance = Boolean(config.data?.maintenance.enabled) && !user.data?.can_access_admin

  const home = user.data ? '/dashboard' : '/'
  const atTable = pathname.startsWith('/rooms/')
  // The landing page and a signed-in player's home show the one big picture.
  const isHome = !inMaintenance && (pathname === '/' || pathname === '/dashboard')
  const showRail = wide && !atTable
  const showBar = !wide && !atTable

  const brand = (
    <>
      <img
        src={config.data?.logo_url ?? '/favicon.svg'}
        alt=""
        className="size-8 shrink-0 object-contain"
      />
      {config.data ? (
        <span className="truncate">{config.data.name}</span>
      ) : (
        <Skeleton className="h-5 w-32" />
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
      {isHome ? (
        <HomeBackdrop className="pointer-events-none fixed inset-x-0 top-0 -z-10 h-lvh w-full" />
      ) : null}

      {showRail ? <Rail home={home} signedIn={Boolean(user.data)} language={language} /> : null}

      <div className={`flex min-h-dvh min-w-0 flex-1 flex-col ${showBar ? 'pb-tabbar' : ''}`}>
        {/* At a table the room draws its own bar (TableBar); during maintenance the header stays. */}
        {atTable && !inMaintenance ? null : (
          <header className="sticky top-0 z-20 flex h-header items-center justify-between gap-3 border-b border-gold/20 bg-emerald-900 px-3 sm:px-6">
            <div className="flex min-w-0 items-center gap-3">
              {atTable ? (
                <Link to={home} className={iconButton} aria-label={t('nav.back')}>
                  <BackIcon />
                </Link>
              ) : null}
              {showRail ? null : (
                <Link to={home} className="flex min-w-0 items-center gap-2.5 font-bold text-fg">
                  {brand}
                </Link>
              )}
            </div>
            <nav aria-label={t('nav.main')} className="flex items-center gap-1 sm:gap-2">
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
                    className="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium whitespace-nowrap text-fg hover:text-gold-light"
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

        <footer className="border-t border-gold/20 bg-emerald-900 py-6">
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
            {showRail ? null : language}
          </Container>
        </footer>
      </div>

      {showBar ? (
        <nav
          aria-label={t('nav.shortcuts')}
          data-tabbar
          className="fixed inset-x-0 bottom-0 z-20 flex border-t border-gold/20 bg-emerald-900 pb-[env(safe-area-inset-bottom)]"
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

/** The left rail: every section and game, labelled from 1280 px, icons with tooltips below; the player can toggle it. */
function Rail({
  home,
  signedIn,
  language,
}: {
  home: string
  signedIn: boolean
  language: ReactNode
}) {
  const { t } = useTranslation()
  const config = usePublicConfig()
  const games = useGames()
  const roomy = useMediaQuery('(min-width: 1280px)')
  const [choice, setChoice] = useState(readRail)
  const iconsOnly = choice ?? !roomy

  const toggle = () => {
    const next = !iconsOnly
    setChoice(next)
    try {
      localStorage.setItem('rail', next ? 'icons' : 'full')
    } catch {
      // Only a convenience: the choice simply isn't remembered.
    }
  }

  const link = (to: string, label: string, icon: ReactNode, end = false) => {
    const item = (
      <NavLink to={to} end={end} className={railLink} aria-label={iconsOnly ? label : undefined}>
        <span className="flex size-6 shrink-0 items-center justify-center">{icon}</span>
        {iconsOnly ? null : <span className="truncate">{label}</span>}
      </NavLink>
    )
    return iconsOnly ? (
      <Tooltip key={to} text={label}>
        {item}
      </Tooltip>
    ) : (
      <div key={to}>{item}</div>
    )
  }

  return (
    <aside
      className={`sticky top-0 flex h-dvh shrink-0 flex-col gap-5 border-r border-gold/20 bg-emerald-900 px-3 py-3 ${iconsOnly ? 'w-rail-icons' : 'w-rail'}`}
    >
      <Link to={home} className="flex h-10 items-center gap-2.5 px-2 font-bold text-fg">
        <img
          src={config.data?.logo_url ?? '/favicon.svg'}
          alt=""
          className="size-8 shrink-0 object-contain"
        />
        {iconsOnly ? (
          <span className="sr-only">{config.data?.name}</span>
        ) : (
          <span className="truncate">{config.data?.name}</span>
        )}
      </Link>

      <nav aria-label={t('nav.sections')} className="flex min-h-0 flex-1 flex-col gap-5">
        <div className="flex flex-col gap-1">
          {link(home, t('nav.home'), <HomeIcon />, true)}
          {link('/games', t('nav.games'), <GridIcon />, true)}
        </div>

        <div className="flex flex-col gap-1">
          {iconsOnly ? null : (
            <p className="px-3 pb-1 text-xs font-semibold text-muted">{t('nav.games')}</p>
          )}
          {games.isPending
            ? Array.from({ length: 5 }, (_, i) => <Skeleton key={i} className="mx-3 my-1.5 h-5" />)
            : (games.data ?? []).map((game) =>
                link(
                  `/games/${game.slug}`,
                  game.name,
                  <GameIcon slug={game.slug} name={game.name} />,
                ),
              )}
        </div>

        {signedIn ? (
          <div className="flex flex-col gap-1">
            {link('/wallet', t('nav.wallet'), <FishkaIcon className="size-6" />)}
            {link('/ranks', t('nav.ranks'), <TrophyIcon />)}
            {link('/history', t('nav.history'), <HistoryIcon />)}
            {link('/profile', t('nav.profile'), <UserIcon />)}
          </div>
        ) : null}

        <div className="mt-auto flex flex-col gap-1">
          {signedIn ? link('/settings', t('nav.settings'), <SettingsIcon />) : null}
          {iconsOnly ? null : <div className="px-3 py-1">{language}</div>}
          <button
            type="button"
            aria-expanded={!iconsOnly}
            aria-label={t('nav.toggleRail')}
            onClick={toggle}
            className="flex min-h-11 items-center justify-center rounded-control text-muted hover:bg-emerald-800/70 hover:text-fg"
          >
            <ChevronIcon className={`size-5 ${iconsOnly ? '' : 'rotate-180'}`} />
          </button>
        </div>
      </nav>
    </aside>
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
