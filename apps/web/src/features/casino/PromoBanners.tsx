import { useQuery } from '@tanstack/react-query'
import { useEffect, useRef, useState, type CSSProperties } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
import { bannersKey, fetchBanners, isInternal, type Banner } from './banners'

const ADVANCE_MS = 7000

/**
 * The casino's promotion banners, as an administrator set them (picture, title, text, link, order, dates). Shows
 * nothing when there are none — never a placeholder promotion. Turns to the next banner every 7 s unless the
 * pointer or focus is on it, or motion is reduced.
 */
export function PromoBanners() {
  const { t, i18n } = useTranslation()
  const banners = useQuery({
    queryKey: [...bannersKey, i18n.language],
    queryFn: ({ signal }) => fetchBanners(signal),
    staleTime: 60_000,
  })
  const [index, setIndex] = useState(0)
  const [held, setHeld] = useState(false)
  const reduced = useMediaQuery('(prefers-reduced-motion: reduce)')
  const list = banners.data ?? []
  const count = list.length
  const current = count > 0 ? index % count : 0
  const regionRef = useRef<HTMLElement>(null)

  useEffect(() => {
    if (count < 2 || held || reduced) return
    const timer = window.setInterval(() => setIndex((i) => i + 1), ADVANCE_MS)
    return () => window.clearInterval(timer)
  }, [count, held, reduced])

  if (count === 0) return null

  const go = (to: number) => setIndex(((to % count) + count) % count)

  return (
    <section
      ref={regionRef}
      aria-roledescription="carousel"
      aria-label={t('casino.banners.label')}
      className="group/banners relative mb-14"
      onMouseEnter={() => setHeld(true)}
      onMouseLeave={() => setHeld(false)}
      onFocus={() => setHeld(true)}
      onBlur={(e) => {
        if (!regionRef.current?.contains(e.relatedTarget)) setHeld(false)
      }}
    >
      <div className="relative overflow-hidden rounded-[1.75rem] border border-white/10 shadow-[0_30px_60px_-24px_rgb(0_0_0/0.9)]">
        <div
          className="flex transition-transform duration-700 ease-soft motion-reduce:transition-none"
          style={{ transform: `translateX(-${current * 100}%)` }}
        >
          {list.map((banner, i) => (
            <Slide
              key={banner.id}
              banner={banner}
              active={i === current}
              label={t('casino.banners.slide', { n: i + 1, count })}
            />
          ))}
        </div>
        {count > 1 ? (
          <div className="absolute right-4 bottom-4 z-10 flex gap-2 sm:right-6 sm:bottom-6">
            <ArrowButton
              label={t('casino.banners.previous')}
              onClick={() => go(current - 1)}
              back
            />
            <ArrowButton label={t('casino.banners.next')} onClick={() => go(current + 1)} />
          </div>
        ) : null}
      </div>
      {count > 1 ? (
        // One segment per promotion; the current one fills while it is shown (stops when held).
        <div className="mt-4 flex justify-center gap-2">
          {list.map((banner, i) => (
            <button
              key={banner.id}
              type="button"
              onClick={() => go(i)}
              aria-label={t('casino.banners.goTo', { n: i + 1 })}
              aria-current={i === current ? 'true' : undefined}
              className="group/dot flex h-6 items-center"
            >
              <span className="relative block h-1 w-10 overflow-hidden rounded-full bg-white/15 transition-colors group-hover/dot:bg-white/30">
                {i === current ? (
                  // Fills over the slide's time; held (pointer or focus on it), it stays full and still.
                  <span
                    key={`${current}-${held}`}
                    className={`absolute inset-y-0 left-0 w-full origin-left rounded-full bg-gold ${
                      held ? '' : 'motion-safe:animate-[banner-progress_var(--advance)_linear_both]'
                    }`}
                    style={{ '--advance': `${ADVANCE_MS}ms` } as CSSProperties}
                  />
                ) : null}
              </span>
            </button>
          ))}
        </div>
      ) : null}
    </section>
  )
}

function ArrowButton({
  label,
  onClick,
  back = false,
}: {
  label: string
  onClick: () => void
  back?: boolean
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      className="grid size-11 place-items-center rounded-full border border-white/20 bg-black/40 text-fg backdrop-blur-md transition hover:border-gold/70 hover:bg-black/60 hover:text-gold-light"
    >
      <svg
        viewBox="0 0 24 24"
        aria-hidden="true"
        className={`size-5 fill-none stroke-current stroke-2 ${back ? 'rotate-180' : ''}`}
      >
        <path d="m9 6 6 6-6 6" />
      </svg>
    </button>
  )
}

function Slide({ banner, active, label }: { banner: Banner; active: boolean; label: string }) {
  const { t } = useTranslation()
  const cta = banner.cta ?? t('casino.banners.open')
  const button =
    'group/cta hover-shine inline-flex min-h-12 items-center gap-2 rounded-full bg-(image:--btn-gold) px-6 font-bold text-(--btn-gold-ink) shadow-(--btn-gold-shadow) transition hover:brightness-108'
  const arrow = (
    <span aria-hidden="true" className="transition-transform group-hover/cta:translate-x-1">
      →
    </span>
  )

  return (
    <article
      role="group"
      aria-roledescription="slide"
      aria-label={label}
      aria-hidden={!active || undefined}
      inert={!active}
      className="relative isolate flex min-h-60 w-full shrink-0 items-end overflow-hidden bg-[radial-gradient(ellipse_at_85%_15%,rgb(124_92_255/0.45),transparent_55%),radial-gradient(ellipse_at_15%_100%,rgb(245_192_79/0.25),transparent_55%),#0d1222] sm:min-h-80"
    >
      {banner.image_url ? (
        // The picture drifts in slowly while its slide is up (restarts each time it comes back).
        <img
          key={active ? 'on' : 'off'}
          src={banner.image_url}
          alt=""
          className={`absolute inset-0 -z-10 size-full object-cover ${active ? 'motion-safe:animate-[ken-burns_9s_ease-out_both]' : ''}`}
          loading="lazy"
        />
      ) : null}
      <div className="absolute inset-0 -z-10 bg-linear-to-r from-night-950/95 via-night-950/60 to-transparent" />
      <div className="absolute inset-x-0 bottom-0 -z-10 h-1/2 bg-linear-to-t from-night-950/80 to-transparent" />
      <div key={active ? 'shown' : 'hidden'} className="max-w-xl p-6 pb-20 sm:p-10 sm:pb-10">
        <p className="mb-3 inline-flex items-center gap-2 rounded-full border border-gold/40 bg-black/40 px-3 py-1 text-[0.7rem] font-bold tracking-[0.16em] text-gold-light uppercase backdrop-blur motion-safe:animate-rise">
          <span aria-hidden="true" className="size-1.5 rounded-full bg-gold" />
          {t('casino.banners.label')}
        </p>
        <h3 className="font-display text-2xl leading-tight font-bold text-balance text-fg [animation-delay:80ms] motion-safe:animate-rise sm:text-4xl">
          {banner.title}
        </h3>
        {banner.description ? (
          <p className="mt-3 text-sm text-fg/80 [animation-delay:160ms] motion-safe:animate-rise sm:text-base">
            {banner.description}
          </p>
        ) : null}
        {banner.link ? (
          <div className="mt-6 [animation-delay:240ms] motion-safe:animate-rise">
            {isInternal(banner.link) ? (
              <Link to={banner.link} className={button}>
                {cta}
                {arrow}
              </Link>
            ) : (
              <a href={banner.link} target="_blank" rel="noopener noreferrer" className={button}>
                {cta}
                {arrow}
              </a>
            )}
          </div>
        ) : null}
      </div>
    </article>
  )
}
