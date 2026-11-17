import { useQuery } from '@tanstack/react-query'
import { useRef, type CSSProperties, type PointerEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { MAIN_GAMES } from '@/features/casino/mainGames'
import { fetchSlots, slotsKey } from '@/features/casino/slots'
import { GameArtwork } from '@/features/catalog/components/GameArtwork'
import { useGames } from '@/features/catalog/queries'
import { useOnlineCount } from '@/features/presence/useOnlineCount'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { ButtonLink } from '@/shared/ui/Button'
import { NightSky } from './NightSky'
import { Container } from '@/shared/ui/Container'

/** The fan: left, centre (in front), right. */
const FAN = [
  { x: '-58%', y: '8%', rotate: -14, z: 1, delay: '0s' },
  { x: '0%', y: '-4%', rotate: 0, z: 3, delay: '1.2s' },
  { x: '58%', y: '8%', rotate: 14, z: 2, delay: '2.4s' },
]

/**
 * The top of the home page (Midnight Gold, 2026-11-17): a big title with a passing light, the way in, live numbers
 * that are all real (people online, games, slots), and the three main games fanned out in 3D, leaning after the
 * pointer, under the night sky (NightSky). Every motion stops under reduced motion.
 */
export function HomeHero() {
  const { t, i18n } = useTranslation()
  const { data: user } = useCurrentUser()
  const games = useGames()
  const slots = useQuery({ queryKey: slotsKey, queryFn: ({ signal }) => fetchSlots(signal) })
  const online = useOnlineCount()
  const sectionRef = useRef<HTMLElement>(null)
  const number = (n: number) => n.toLocaleString(i18n.language)

  const main = MAIN_GAMES.map((slug) => games.data?.find((g) => g.slug === slug)).filter(
    (g) => g !== undefined,
  )
  const gameCount = games.data?.length
  const slotCount = slots.data?.filter((s) => s.play_path !== null).length

  // The fan leans a little after the pointer (−1…1 on each axis); nothing on touch-only screens.
  const lean = (event: PointerEvent<HTMLElement>) => {
    const box = event.currentTarget.getBoundingClientRect()
    const style = sectionRef.current?.style
    style?.setProperty('--px', (((event.clientX - box.left) / box.width) * 2 - 1).toFixed(3))
    style?.setProperty('--py', (((event.clientY - box.top) / box.height) * 2 - 1).toFixed(3))
  }

  return (
    <section
      ref={sectionRef}
      onPointerMove={(e) => e.pointerType === 'mouse' && lean(e)}
      className="relative isolate overflow-hidden border-b border-white/6 [--px:0] [--py:0]"
    >
      <NightSky />

      <Container className="grid items-center gap-10 py-12 sm:py-16 lg:min-h-[34rem] lg:grid-cols-[1.05fr_1fr] lg:py-20">
        <div className="text-center lg:text-left">
          <p className="inline-flex items-center gap-2 rounded-full border border-white/12 bg-white/5 px-3.5 py-1.5 text-xs font-semibold tracking-wide text-fg backdrop-blur motion-safe:animate-rise">
            <span
              aria-hidden="true"
              className="size-1.5 rounded-full bg-gold shadow-[0_0_10px_var(--color-gold)]"
            />
            {t('casino.eyebrow')}
          </p>
          <h1 className="hero-title mt-5 text-4xl leading-[1.08] font-bold text-balance [animation-delay:80ms] motion-safe:animate-rise sm:text-5xl xl:text-6xl">
            {t('casino.title')}
          </h1>
          <p className="mx-auto mt-5 max-w-xl text-base text-muted [animation-delay:160ms] motion-safe:animate-rise sm:text-lg lg:mx-0">
            {t('casino.subtitle')}
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3 [animation-delay:240ms] motion-safe:animate-rise lg:justify-start">
            {user === null ? (
              <>
                <ButtonLink to="/register" size="lg" className="group">
                  {t('casino.join')}
                  <Arrow />
                </ButtonLink>
                <ButtonLink to="/login" size="lg" variant="secondary">
                  {t('nav.login')}
                </ButtonLink>
              </>
            ) : (
              <>
                <ButtonLink to="#showcase" size="lg" className="group">
                  {t('casino.play')}
                  <Arrow />
                </ButtonLink>
                <ButtonLink to="/games" size="lg" variant="secondary">
                  {t('home.explore')}
                </ButtonLink>
              </>
            )}
          </div>

          {/* Real numbers only: each shows once the server has said it. */}
          <dl className="mt-9 flex flex-wrap justify-center gap-x-8 gap-y-4 [animation-delay:320ms] motion-safe:animate-rise lg:justify-start">
            {online !== null ? (
              <Stat value={number(online)} label={t('home.onlineNow')} live />
            ) : null}
            {gameCount !== undefined ? (
              <Stat value={number(gameCount)} label={t('home.games', { count: gameCount })} />
            ) : null}
            {slotCount ? (
              <Stat value={number(slotCount)} label={t('home.slots', { count: slotCount })} />
            ) : null}
          </dl>
          <p className="mt-7 inline-flex items-center gap-2 text-xs text-muted">
            <FishkaIcon className="size-4 shrink-0" />
            {t('casino.playMoney')}
          </p>
        </div>

        {main.length === 3 ? (
          <div className="relative mx-auto hidden h-[25rem] w-full max-w-[34rem] [perspective:1400px] lg:block">
            <div
              className="absolute inset-0 transition-transform duration-500 ease-out [transform-style:preserve-3d]"
              style={{
                transform: 'rotateY(calc(var(--px) * 7deg)) rotateX(calc(var(--py) * -6deg))',
              }}
            >
              {main.map((game, i) => {
                const fan = FAN[i]!
                return (
                  <Link
                    key={game.slug}
                    to={`/games/${game.slug}`}
                    aria-label={t('casino.playGame', { game: game.name })}
                    className="hero-card group absolute top-1/2 left-1/2 w-52 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-accent-text"
                    style={
                      {
                        zIndex: fan.z,
                        '--fan-x': fan.x,
                        '--fan-y': fan.y,
                        '--fan-r': `${fan.rotate}deg`,
                        animationDelay: fan.delay,
                      } as CSSProperties
                    }
                  >
                    <span className="hover-shine block overflow-hidden rounded-[1.4rem] border border-white/15 bg-night-800 shadow-[0_30px_60px_-20px_rgb(0_0_0/0.85),0_0_0_1px_rgb(245_192_79/0.18)] transition duration-300 group-hover:border-gold/70 group-hover:shadow-[0_34px_70px_-20px_rgb(0_0_0/0.9),0_0_40px_rgb(245_192_79/0.35)]">
                      <GameArtwork game={game} className="aspect-[3/3.6] w-full" />
                      <span className="flex items-center justify-between gap-2 px-3.5 py-3">
                        <span className="truncate font-display text-sm font-semibold text-fg">
                          {game.name}
                        </span>
                        <span className="rounded-full bg-gold/15 px-2 py-0.5 text-[0.65rem] font-bold tracking-wider text-gold-light uppercase">
                          {t('casino.top', { n: i + 1 })}
                        </span>
                      </span>
                    </span>
                  </Link>
                )
              })}
            </div>
          </div>
        ) : null}
      </Container>
    </section>
  )
}

function Stat({ value, label, live = false }: { value: string; label: string; live?: boolean }) {
  return (
    <div className="flex flex-col items-center lg:items-start">
      <dt className="sr-only">{label}</dt>
      <dd className="flex items-center gap-2 font-display text-2xl font-bold text-fg tabular-nums">
        {live ? (
          <span aria-hidden="true" className="relative flex size-2.5">
            <span className="absolute inline-flex size-full rounded-full bg-live opacity-75 motion-safe:animate-ping" />
            <span className="relative inline-flex size-2.5 rounded-full bg-live" />
          </span>
        ) : null}
        {value}
      </dd>
      <dd aria-hidden="true" className="text-xs font-semibold tracking-wide text-muted uppercase">
        {label}
      </dd>
    </div>
  )
}

function Arrow() {
  return (
    <svg
      viewBox="0 0 24 24"
      aria-hidden="true"
      className="size-4 fill-none stroke-current stroke-[2.5] transition-transform duration-200 group-hover:translate-x-1"
    >
      <path d="M5 12h14M13 6l6 6-6 6" />
    </svg>
  )
}
