import type { CSSProperties } from 'react'
import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'

/** Gold dust rising and chips drifting: fixed positions (no randomness between renders), off under reduced motion. */
const DUST = Array.from({ length: 22 }, (_, i) => ({
  left: (i * 37) % 100,
  delay: (i * 0.73) % 9,
  size: 2 + (i % 3),
}))
const CHIPS = [
  { left: '6%', top: '22%', color: '#b51f2a', size: 54, delay: 0, tilt: -12 },
  { left: '88%', top: '18%', color: '#1d6b3e', size: 46, delay: 1.4, tilt: 10 },
  { left: '80%', top: '68%', color: '#13284a', size: 60, delay: 2.2, tilt: -6 },
  { left: '12%', top: '72%', color: '#e0b24a', size: 40, delay: 3.1, tilt: 14 },
]

/** A casino chip: a coloured disc with the white edge spots and an inner ring. */
function Chip({ color, size }: { color: string; size: number }) {
  return (
    <svg viewBox="0 0 100 100" width={size} height={size} aria-hidden="true">
      <circle cx="50" cy="50" r="48" fill={color} />
      <circle
        cx="50"
        cy="50"
        r="48"
        fill="none"
        stroke="#fff"
        strokeWidth="9"
        strokeDasharray="14 11"
      />
      <circle
        cx="50"
        cy="50"
        r="31"
        fill="none"
        stroke="#fff"
        strokeOpacity="0.75"
        strokeWidth="3"
      />
      <circle cx="50" cy="50" r="22" fill="#000" fillOpacity="0.15" />
    </svg>
  )
}

/**
 * The top of the home page: a dark, lamp-lit casino hero — gold title with a slow light sweep, gold dust rising,
 * chips drifting — and the way in (Play for a player, Join for a guest). Fishkas are said plainly to be play money.
 */
export function CasinoHero() {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()

  return (
    <section className="relative isolate overflow-hidden border-b border-gold/20 bg-[radial-gradient(ellipse_at_50%_-10%,#3a2a12_0%,#160d06_45%,#07110c_100%)]">
      <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10">
        <div className="absolute inset-x-0 top-0 h-2/3 bg-[radial-gradient(ellipse_at_50%_0%,rgb(255_210_120/0.22),transparent_70%)]" />
        {DUST.map((d, i) => (
          <span
            key={i}
            className="absolute bottom-0 rounded-full bg-[#f6d27a] opacity-0 shadow-[0_0_8px_#f6d27a] motion-safe:animate-gold-dust"
            style={{
              left: `${d.left}%`,
              width: d.size,
              height: d.size,
              animationDelay: `${d.delay}s`,
            }}
          />
        ))}
        {CHIPS.map((c, i) => (
          <span
            key={i}
            className="absolute opacity-70 drop-shadow-[0_10px_18px_rgb(0_0_0/0.6)] motion-safe:animate-float-chip max-sm:hidden"
            style={
              {
                left: c.left,
                top: c.top,
                animationDelay: `${c.delay}s`,
                '--tilt': `${c.tilt}deg`,
              } as CSSProperties
            }
          >
            <Chip color={c.color} size={c.size} />
          </span>
        ))}
      </div>

      <Container className="flex min-h-[22rem] flex-col items-center justify-center py-12 text-center sm:min-h-[26rem]">
        <p className="rounded-full border border-gold/40 bg-black/40 px-3 py-1 text-xs font-semibold tracking-[0.2em] text-gold-light uppercase motion-safe:animate-rise">
          {t('casino.eyebrow')}
        </p>
        <h1 className="casino-gold mt-4 font-display text-4xl font-bold text-balance motion-safe:animate-gold-sweep sm:text-6xl">
          {t('casino.title')}
        </h1>
        <p className="mt-4 max-w-xl text-base text-fg/85 [animation-delay:120ms] motion-safe:animate-rise sm:text-lg">
          {t('casino.subtitle')}
        </p>
        <div className="mt-7 flex flex-wrap justify-center gap-3 [animation-delay:220ms] motion-safe:animate-rise">
          {user ? (
            <ButtonLink
              to="#showcase"
              variant="gold"
              size="lg"
              className="motion-safe:animate-gold-glow"
            >
              {t('casino.play')}
            </ButtonLink>
          ) : user === null ? (
            <ButtonLink
              to="/register"
              variant="gold"
              size="lg"
              className="motion-safe:animate-gold-glow"
            >
              {t('casino.join')}
            </ButtonLink>
          ) : null}
        </div>
        <p className="mt-4 text-xs text-muted">{t('casino.playMoney')}</p>
      </Container>
    </section>
  )
}
