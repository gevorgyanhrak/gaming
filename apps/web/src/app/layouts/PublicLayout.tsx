import { useTranslation } from 'react-i18next'
import { Link, NavLink, Outlet } from 'react-router'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { LanguageSwitcher } from '@/shared/i18n/LanguageSwitcher'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'

const navLink = ({ isActive }: { isActive: boolean }) =>
  `rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
    isActive ? 'bg-surface-raised text-fg' : 'text-muted hover:text-fg'
  }`

export function PublicLayout() {
  const { t } = useTranslation()
  const config = usePublicConfig()

  return (
    <div className="flex min-h-dvh flex-col">
      <header className="sticky top-0 z-10 border-b border-border bg-bg/85 backdrop-blur">
        <Container className="flex h-16 items-center justify-between gap-4">
          <Link to="/" className="flex items-center gap-2.5 font-bold text-fg">
            <img src="/favicon.svg" alt="" className="size-8" />
            {config.data ? (
              <span className="truncate">{config.data.name}</span>
            ) : (
              <Skeleton className="h-5 w-32" />
            )}
          </Link>
          <nav aria-label={t('nav.main')} className="flex items-center gap-1">
            <NavLink to="/games" className={navLink}>
              {t('nav.games')}
            </NavLink>
          </nav>
        </Container>
      </header>

      <main className="flex-1">
        <Outlet />
      </main>

      <footer className="border-t border-border py-6">
        <Container className="flex flex-col items-center justify-between gap-4 text-sm text-muted sm:flex-row">
          <span>
            {config.data
              ? t('footer.copyright', { year: new Date().getFullYear(), name: config.data.name })
              : null}
          </span>
          {config.data ? <LanguageSwitcher serverLocales={config.data.supported_locales} /> : null}
        </Container>
      </footer>
    </div>
  )
}
