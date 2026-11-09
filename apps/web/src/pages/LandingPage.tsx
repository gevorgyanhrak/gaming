import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { RankLadder } from '@/features/levels/components/RankLadder'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'
import { BoltIcon, BotIcon, FriendsIcon } from '@/shared/ui/icons'

const ways = [
  { key: 'online', Icon: BoltIcon },
  { key: 'bots', Icon: BotIcon },
  { key: 'friends', Icon: FriendsIcon },
] as const

export function LandingPage() {
  const { t } = useTranslation()
  const config = usePublicConfig()
  const { data: user } = useCurrentUser()
  // Ivory tiles only where they stay small next to the picture.
  const wide = useMediaQuery('(min-width: 1024px)')

  return (
    <>
      {config.data ? <title>{config.data.name}</title> : null}

      <section className="home-hero">
        <Container className="flex min-h-[32rem] items-center pt-14 pb-28 lg:min-h-[40rem]">
          <div className="mx-auto max-w-3xl text-center lg:mx-0 lg:text-left">
            <p className="inline-flex items-center rounded-full border border-gold/45 bg-emerald-900/70 px-3 py-1 text-xs font-semibold text-gold-light">
              {t('landing.eyebrow')}
            </p>
            <h1 className="mt-5 font-display text-display-sm font-bold text-balance whitespace-pre-line text-fg lg:text-display">
              {t('landing.title')}
            </h1>
            <p className="mx-auto mt-5 max-w-xl text-lg text-fg lg:mx-0">{t('landing.subtitle')}</p>
            <div className="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
              {/* Nothing while the account is unknown: a player must not be invited to sign up. */}
              {user ? (
                <ButtonLink to="/dashboard" variant="gold" size="lg">
                  {t('landing.playNow')}
                </ButtonLink>
              ) : user === null ? (
                <ButtonLink to="/register" variant="gold" size="lg">
                  {t('landing.join')}
                </ButtonLink>
              ) : null}
              {/* A plain link: the browser's own jump to the section below. */}
              <a
                href="#games"
                className="inline-flex min-h-12 items-center justify-center rounded-control border border-border-strong/70 bg-emerald-800 px-7 py-3 text-base font-semibold text-fg transition-colors duration-150 hover:bg-surface-raised"
              >
                {t('landing.cta')}
              </a>
            </div>
          </div>
        </Container>
      </section>

      <Container className="relative -mt-20 pb-12">
        <section
          id="games"
          aria-labelledby="home-games"
          className="club-panel-glass scroll-mt-24 rounded-panel p-5 sm:p-6"
        >
          <h2 id="home-games" className="mb-5 text-2xl font-bold text-fg">
            {t('landing.gamesHeading')}
          </h2>
          <GameGrid />

          <ul className="mt-8 grid gap-4 sm:grid-cols-3">
            {ways.map(({ key, Icon }) => (
              <li
                key={key}
                className={`flex gap-4 rounded-card p-5 ${wide ? 'surface-ivory' : 'bg-emerald-900/60'}`}
              >
                <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-ivory-100 text-emerald-800">
                  <Icon className="size-6" />
                </span>
                <span>
                  <span className="block font-semibold text-fg">
                    {t(`landing.ways.${key}.title`)}
                  </span>
                  <span className="mt-1 block text-sm text-muted">
                    {t(`landing.ways.${key}.text`)}
                  </span>
                </span>
              </li>
            ))}
          </ul>
        </section>
      </Container>

      <section className="surface-walnut">
        <Container className="py-8">
          <h2 className="mb-5 text-2xl font-bold text-fg">{t('ranks.title')}</h2>
          <RankLadder />
        </Container>
      </section>
    </>
  )
}
