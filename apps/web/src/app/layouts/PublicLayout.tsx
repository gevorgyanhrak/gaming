import { useEffect, useRef, useState, ViewTransition, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, NavLink, Outlet, useLocation } from 'react-router'
import { updateSettings } from '@/features/account/api'
import { AccountUnavailable } from '@/features/auth/components/AccountUnavailable'
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
import { useCasinoAmbience } from '@/features/sound/useCasinoAmbience'
import { useGameInProgress } from '@/features/play/gameInProgress'
import { openOutOfFishkas } from '@/features/wallet/outOfFishkas'
import { useWalletSync, useZeroBalancePrompt } from '@/features/wallet/hooks'
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
  HomeIcon,
  MenuIcon,
  SpadeIcon,
  StarIcon,
  TrophyIcon,
  UserIcon,
} from '@/shared/ui/icons'
import { BrandLogo, BrandMark } from '@/shared/ui/BrandMark'
import { Skeleton } from '@/shared/ui/Skeleton'
import { useRefreshOnLanguageChange } from '../useRefreshOnLanguageChange'

// min-w-0: five equal tabs share the width even when a label (hy "Ֆիշկաներ") is wider than its share; the label
// steps down a size under 375 px and, if it still can't fit, ends in "…" (TabLabel).
const tabLink = ({ isActive }: { isActive: boolean }) =>
  `relative flex min-h-16 min-w-0 flex-1 flex-col items-center justify-center gap-1 px-0.5 text-xs font-semibold transition-colors max-[374px]:text-[0.6875rem] max-[374px]:tracking-tight ${
    isActive
      ? 'text-gold-light before:absolute before:top-0 before:h-0.5 before:w-8 before:rounded-full before:bg-gold before:shadow-[0_0_12px_var(--color-gold)]'
      : 'text-muted hover:text-fg'
  }`

const iconButton =
  'flex size-11 shrink-0 items-center justify-center rounded-control border border-white/10 bg-white/5 text-fg transition-colors hover:border-gold/50 hover:bg-white/10'

/** The site's sections: one list for the header row, the tablet section bar and the phone's More panel. */
function useSections(home: string) {
  const { t } = useTranslation()
  const { pathname, search } = useLocation()
  const casino =
    pathname === '/dashboard' && new URLSearchParams(search).get('category') === 'casino'
  return [
    { to: home, label: t('nav.home'), icon: <HomeIcon />, active: pathname === home },
    {
      to: '/dashboard',
      label: t('nav.games'),
      icon: <GridIcon />,
      active: (pathname === '/dashboard' || pathname.startsWith('/games/')) && !casino,
    },
    {
      to: '/dashboard?category=casino',
      label: t('nav.casino'),
      icon: <SpadeIcon />,
      active: casino,
    },
    {
      to: '/ranks',
      label: t('nav.ranks'),
      icon: <StarIcon className="size-5" />,
      active: pathname === '/ranks',
    },
    {
      to: '/leaderboards',
      label: t('leaders.title'),
      icon: <TrophyIcon />,
      active: pathname === '/leaderboards',
    },
  ]
}

/** The header's sections, inline from 1280 px (tablets: SectionBar; phones: the tab bar and MoreMenu). */
function SectionLinks({ home }: { home: string }) {
  const { t } = useTranslation()
  const links = useSections(home)

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
 * The app's shell (TASK-048, HayBet item 5): a header with the sections, the balance and account, the page,
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

  const home = '/'
  const atTable = pathname.startsWith('/rooms/')
  // A game table keeps its own look (owner, 2026-11-17): the classic tokens for the whole page, popups included.
  useEffect(() => {
    document.body.classList.toggle('classic-scope', atTable)
    return () => document.body.classList.remove('classic-scope')
  }, [atTable])
  const showBar = !wide && !atTable
  // Quiet casino ambience and the welcome chime around the games, never over a table (item 8).
  useCasinoAmbience(!atTable)

  const brand = config.data ? (
    // The HayBet mark and name (item 7); phones keep the header for search and the balance: the mark alone, the name
    // for screen readers.
    <BrandLogo
      name={config.data.name}
      logoUrl={config.data.logo_url}
      wordClassName="sr-only text-[1.05rem] sm:not-sr-only"
    />
  ) : (
    <>
      <BrandMark />
      <Skeleton className="hidden h-5 w-32 sm:block" />
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

      <div className={`flex min-h-dvh min-w-0 flex-1 flex-col ${showBar ? 'pb-tabbar' : ''}`}>
        {/* At a table the room draws its own bar (TableBar); during maintenance the header stays. */}
        {atTable && !inMaintenance ? null : (
          // HayBet item 5 (2026-11-20): no side menu. The sections sit in the header — inline from 1280 px, on a
          // second row of the header from 768 px, and on phones in the "More" panel that drops from the header
          // (next to the tab bar).
          <header className="sticky top-0 z-20 border-b border-white/6 bg-night-900/75 backdrop-blur-xl backdrop-saturate-150">
            {/* A thin gold light along the bottom edge. */}
            <span
              aria-hidden="true"
              className="pointer-events-none absolute inset-x-0 -bottom-px h-px bg-linear-to-r from-transparent via-gold/45 to-transparent"
            />
            <div className="flex h-header items-center justify-between gap-1.5 px-2.5 sm:gap-3 sm:px-6">
              {/* Phones show the logo alone, which must not shrink; from sm the name may truncate. */}
              <div className="flex shrink-0 items-center gap-2 sm:min-w-0 sm:shrink sm:gap-3">
                {atTable ? (
                  <Link to={home} className={iconButton} aria-label={t('nav.back')}>
                    <BackIcon />
                  </Link>
                ) : null}
                {atTable ? null : <MoreMenu home={home} language={language} />}
                {/* The logo always leads to the home page (owner, 2026-11-17). On narrow phones it gives way: under
                  375 px, or under 400 px for a player, whose balance needs the room (ru "10,1 тыс."). */}
                <Link
                  to="/"
                  className={`flex min-w-0 items-center gap-2.5 font-display text-[0.95rem] font-semibold tracking-tight text-fg ${
                    user.data ? 'max-[399px]:hidden' : 'max-[374px]:hidden'
                  }`}
                >
                  {brand}
                </Link>
              </div>
              {atTable ? null : <SectionLinks home={home} />}
              {atTable ? null : <SiteSearch />}
              <nav
                aria-label={t('nav.main')}
                className="flex items-center gap-0.5 min-[390px]:gap-1 sm:gap-2"
              >
                <OnlineCounter />
                {/* Under 360 px the speaker is only in the More panel, so the balance and the account fit. */}
                <SoundToggle className="max-[359px]:hidden" />
                {/* While the account is unknown: a placeholder, or nothing once it failed (AccountUnavailable). */}
                {user.data === undefined ? (
                  user.isPending ? (
                    <Skeleton className="size-9 rounded-full" />
                  ) : null
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
            </div>
            {atTable ? null : <SectionBar home={home} />}
          </header>
        )}

        <AccountUnavailable />
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
              <span className="flex items-center gap-2">
                <BrandMark className="size-6" />
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
            <TabLabel>{t('nav.home')}</TabLabel>
          </NavLink>
          <NavLink to="/dashboard" className={tabLink}>
            <GridIcon />
            <TabLabel>{t('nav.games')}</TabLabel>
          </NavLink>
          {user.data ? (
            <>
              <NavLink to="/wallet" className={tabLink}>
                <FishkaIcon className="size-6" />
                <TabLabel>{t('nav.walletShort')}</TabLabel>
              </NavLink>
              <NavLink to="/ranks" className={tabLink}>
                <TrophyIcon />
                <TabLabel>{t('nav.ranks')}</TabLabel>
              </NavLink>
              <NavLink to="/profile" className={tabLink}>
                <UserIcon />
                <TabLabel>{t('nav.profile')}</TabLabel>
              </NavLink>
            </>
          ) : user.data === undefined ? null : (
            <NavLink to="/login" className={tabLink}>
              <UserIcon />
              <TabLabel>{t('nav.login')}</TabLabel>
            </NavLink>
          )}
        </nav>
      ) : null}
    </div>
  )
}

function TabLabel({ children }: { children: ReactNode }) {
  return <span className="block max-w-full truncate">{children}</span>
}

/** The sections on a second header row, for tablets and small laptops (768–1279 px). */
function SectionBar({ home }: { home: string }) {
  const { t } = useTranslation()
  const links = useSections(home)

  return (
    <nav
      aria-label={t('nav.sections')}
      className="hidden items-center justify-center gap-1 border-t border-white/5 px-4 py-1.5 md:flex xl:hidden"
    >
      {links.map((link) => (
        <Link
          key={link.to}
          to={link.to}
          aria-current={link.active ? 'page' : undefined}
          className={`flex min-h-10 items-center gap-2 rounded-full px-3.5 text-sm font-semibold whitespace-nowrap transition-colors duration-150 ${
            link.active
              ? 'bg-gold/15 text-gold-light shadow-[inset_0_0_0_1px_rgb(245_192_79/0.35)]'
              : 'text-muted hover:bg-white/5 hover:text-fg'
          }`}
        >
          <span className="flex size-5 items-center justify-center text-gold">{link.icon}</span>
          {link.label}
        </Link>
      ))}
    </nav>
  )
}

/**
 * Phones (under 768 px): one "More" button that drops a compact panel from the header — every section as a tile
 * (the tab bar has only five places), then sounds and the language. Closes on a choice, Escape, a tap outside or a
 * route change.
 */
function MoreMenu({ home, language }: { home: string; language: ReactNode }) {
  const { t } = useTranslation()
  const { pathname, search } = useLocation()
  const sections = useSections(home)
  const [open, setOpen] = useState(false)
  const [openedAt, setOpenedAt] = useState(pathname + search)
  const rootRef = useRef<HTMLDivElement>(null)
  if (open && openedAt !== pathname + search) {
    setOpenedAt(pathname + search)
    setOpen(false)
  }

  useEffect(() => {
    if (!open) return
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false)
    }
    const onPointer = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false)
    }
    window.addEventListener('keydown', onKey)
    window.addEventListener('pointerdown', onPointer)
    return () => {
      window.removeEventListener('keydown', onKey)
      window.removeEventListener('pointerdown', onPointer)
    }
  }, [open])

  return (
    <div ref={rootRef} className="md:hidden">
      <button
        type="button"
        onClick={() => {
          setOpenedAt(pathname + search)
          setOpen((now) => !now)
        }}
        aria-label={t('nav.menu')}
        aria-expanded={open}
        aria-controls="more-menu"
        className={iconButton}
      >
        {open ? <CloseIcon /> : <MenuIcon />}
      </button>
      {open ? (
        <div
          id="more-menu"
          className="absolute inset-x-0 top-full border-b border-gold/25 bg-night-900/95 px-3 pt-3 pb-4 shadow-[0_24px_40px_-12px_rgb(0_0_0/0.7)] backdrop-blur-xl motion-safe:animate-[fade-in_150ms_ease-out]"
        >
          <nav aria-label={t('nav.sections')}>
            <ul className="grid grid-cols-3 gap-1.5 min-[360px]:gap-2">
              {sections.map((section) => (
                <li key={section.to}>
                  <Link
                    to={section.to}
                    onClick={() => setOpen(false)}
                    aria-current={section.active ? 'page' : undefined}
                    className="flex min-h-16 flex-col items-center justify-center gap-1 rounded-2xl border border-white/8 bg-white/5 py-2 text-center text-[11px] leading-tight font-semibold text-fg aria-[current=page]:border-gold/50 aria-[current=page]:bg-gold/12 aria-[current=page]:text-gold-light"
                  >
                    <span className="flex size-6 items-center justify-center text-gold">
                      {section.icon}
                    </span>
                    <span className="w-full">{section.label}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
          <div className="mt-3 flex items-center justify-between gap-3 border-t border-white/8 pt-3 text-sm">
            <span className="flex items-center gap-2 text-muted">
              {t('sound.toggle')}
              <SoundToggle />
            </span>
            {language}
          </div>
        </div>
      ) : null}
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
  // Fishkas at zero: offer the top-up and the packages (once a session, never over a game or a table).
  const inGame = useGameInProgress()
  const atTable = useLocation().pathname.startsWith('/rooms/')
  useZeroBalancePrompt(inGame || atTable, openOutOfFishkas)
  return (
    <>
      <LevelUpDialog />
      <DailyBonusDialog />
      <OutOfFishkasDialog />
    </>
  )
}
