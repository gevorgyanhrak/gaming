import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameScene } from '@/features/catalog/components/GameArt'
import { GameGrid } from '@/features/catalog/components/GameGrid'
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

      <section className="border-b border-border">
        <Container className="grid items-center gap-12 py-16 sm:py-20 lg:grid-cols-[1.1fr_1fr]">
          <div className="text-center lg:text-left">
            <p className="inline-flex items-center gap-2 rounded-full border border-gold/30 bg-gold/10 px-3 py-1 text-xs font-semibold tracking-wide text-gold-light uppercase">
              {t('landing.eyebrow')}
            </p>
            <h1 className="mt-5 text-4xl leading-tight font-extrabold tracking-tight text-balance text-gold sm:text-6xl">
              {t('landing.title')}
            </h1>
            <p className="mx-auto mt-5 max-w-xl text-lg text-muted lg:mx-0">
              {t('landing.subtitle')}
            </p>
            <div className="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
              {user ? (
                <ButtonLink to="/dashboard" variant="gold" className="px-7 py-3 text-base">
                  {t('landing.playNow')}
                </ButtonLink>
              ) : (
                <ButtonLink to="/register" variant="gold" className="px-7 py-3 text-base">
                  {t('landing.join')}
                </ButtonLink>
              )}
              <ButtonLink to="/games" variant="secondary" className="px-7 py-3 text-base">
                {t('landing.cta')}
              </ButtonLink>
            </div>
          </div>

          {/* A spread of game artwork, like cards dealt onto the table. */}
          <div aria-hidden="true" className="relative mx-auto h-64 w-full max-w-md sm:h-80">
            <GameScene
              slug="chess"
              className="absolute top-2 left-0 w-3/5 -rotate-6 rounded-2xl border border-white/10 shadow-2xl shadow-black/60"
            />
            <GameScene
              slug="poker"
              className="absolute top-0 right-0 w-3/5 rotate-6 rounded-2xl border border-white/10 shadow-2xl shadow-black/60"
            />
            <GameScene
              slug="durak"
              className="absolute bottom-0 left-1/2 w-3/5 -translate-x-1/2 rounded-2xl border border-gold/40 shadow-2xl shadow-black/60"
            />
          </div>
        </Container>
      </section>

      <Container className="py-12">
        <ul className="grid gap-4 sm:grid-cols-3">
          {ways.map(({ key, Icon }) => (
            <li
              key={key}
              className="flex gap-4 rounded-2xl border border-border bg-surface/70 p-5 backdrop-blur"
            >
              <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-accent/15 text-accent-text">
                <Icon className="size-6" />
              </span>
              <span>
                <span className="block font-semibold">{t(`landing.ways.${key}.title`)}</span>
                <span className="mt-1 block text-sm text-muted">
                  {t(`landing.ways.${key}.text`)}
                </span>
              </span>
            </li>
          ))}
        </ul>
      </Container>

      <Container className="pb-16">
        <h2 className="mb-6 text-2xl font-bold">{t('landing.gamesHeading')}</h2>
        <GameGrid />
      </Container>
    </>
  )
}
