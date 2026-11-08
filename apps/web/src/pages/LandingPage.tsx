import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { HomeHeroArt } from '@/features/catalog/components/HomeArt'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
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

  return (
    <>
      {config.data ? <title>{config.data.name}</title> : null}

      <section className="home-scope-emerald home-hero border-b border-gold/20">
        <Container className="grid items-center gap-8 py-12 sm:py-16 lg:grid-cols-[1fr_1.05fr] lg:gap-12 lg:py-20">
          <div className="text-center lg:text-left">
            <p className="inline-flex items-center rounded-full border border-gold/45 bg-black/15 px-3 py-1 text-xs font-semibold tracking-wide text-gold-light uppercase">
              {t('landing.eyebrow')}
            </p>
            <h1 className="mt-5 text-4xl leading-tight font-extrabold tracking-tight text-balance whitespace-pre-line text-fg sm:text-5xl lg:text-5xl xl:text-[3.25rem]">
              {t('landing.title')}
            </h1>
            <p className="mx-auto mt-5 max-w-xl text-lg text-muted lg:mx-0">
              {t('landing.subtitle')}
            </p>
            <div className="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
              {/* Same rule as today: nothing while the account is unknown. */}
              {user ? (
                <ButtonLink to="/dashboard" variant="gold" className="min-h-11 px-7 py-3 text-base">
                  {t('landing.playNow')}
                </ButtonLink>
              ) : user === null ? (
                <ButtonLink to="/register" variant="gold" className="min-h-11 px-7 py-3 text-base">
                  {t('landing.join')}
                </ButtonLink>
              ) : null}
              {/* A plain link: the browser's own jump to the section below. */}
              <a
                href="#games"
                className="inline-flex min-h-11 items-center justify-center rounded-xl border border-border bg-surface px-7 py-3 text-base font-semibold text-fg transition hover:bg-surface-raised"
              >
                {t('landing.cta')}
              </a>
            </div>
          </div>
          <HomeHeroArt className="mx-auto block w-full max-w-[22rem] sm:max-w-[34rem] lg:max-w-none" />
        </Container>
      </section>

      <section id="games" className="home-scope-ivory scroll-mt-24 py-12 sm:py-16">
        <Container>
          <h2 className="mb-6 text-3xl font-extrabold tracking-tight text-fg">
            {t('landing.gamesHeading')}
          </h2>
          <GameGrid />
        </Container>
      </section>

      <section className="home-scope-ivory home-band-honey">
        <Container className="py-12">
          <ul className="grid gap-4 sm:grid-cols-3">
            {ways.map(({ key, Icon }) => (
              <li
                key={key}
                className="flex gap-4 rounded-2xl border border-home-line bg-home-paper p-5 shadow-sm"
              >
                <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-home-emerald-800 text-home-cream">
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
        </Container>
      </section>
    </>
  )
}
