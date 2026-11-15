import { useQuery } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
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
      className="relative mb-8"
      onMouseEnter={() => setHeld(true)}
      onMouseLeave={() => setHeld(false)}
      onFocus={() => setHeld(true)}
      onBlur={(e) => {
        if (!regionRef.current?.contains(e.relatedTarget)) setHeld(false)
      }}
    >
      <div className="overflow-hidden rounded-2xl border border-gold/30 shadow-[0_18px_40px_-18px_rgb(0_0_0/0.8)]">
        <div
          className="flex transition-transform duration-500 ease-out motion-reduce:transition-none"
          style={{ transform: `translateX(-${current * 100}%)` }}
        >
          {list.map((banner, i) => (
            <Slide
              key={banner.id}
              banner={banner}
              hidden={i !== current}
              label={t('casino.banners.slide', { n: i + 1, count })}
            />
          ))}
        </div>
      </div>
      {count > 1 ? (
        <div className="mt-3 flex items-center justify-center gap-3">
          <button
            type="button"
            onClick={() => go(current - 1)}
            aria-label={t('casino.banners.previous')}
            className="grid size-9 place-items-center rounded-full border border-gold/40 text-gold hover:bg-gold/10"
          >
            ‹
          </button>
          {list.map((banner, i) => (
            <button
              key={banner.id}
              type="button"
              onClick={() => go(i)}
              aria-label={t('casino.banners.goTo', { n: i + 1 })}
              aria-current={i === current ? 'true' : undefined}
              className={
                i === current
                  ? 'h-2 w-6 rounded-full bg-gold'
                  : 'size-2 rounded-full bg-gold/35 hover:bg-gold/60'
              }
            />
          ))}
          <button
            type="button"
            onClick={() => go(current + 1)}
            aria-label={t('casino.banners.next')}
            className="grid size-9 place-items-center rounded-full border border-gold/40 text-gold hover:bg-gold/10"
          >
            ›
          </button>
        </div>
      ) : null}
    </section>
  )
}

function Slide({ banner, hidden, label }: { banner: Banner; hidden: boolean; label: string }) {
  const { t } = useTranslation()
  const cta = banner.cta ?? t('casino.banners.open')
  const button =
    'hover-shine inline-flex min-h-11 items-center rounded-full bg-[#3ddc84] px-5 font-bold text-[#06210f] shadow-lg motion-safe:transition-transform motion-safe:hover:scale-105'

  return (
    <article
      role="group"
      aria-roledescription="slide"
      aria-label={label}
      aria-hidden={hidden || undefined}
      inert={hidden}
      className="relative isolate flex min-h-48 w-full shrink-0 items-end bg-[radial-gradient(ellipse_at_80%_20%,#3a2a12_0%,#160d06_55%,#07110c_100%)] sm:min-h-60"
    >
      {banner.image_url ? (
        <img
          src={banner.image_url}
          alt=""
          className="absolute inset-0 -z-10 size-full object-cover"
          loading="lazy"
        />
      ) : null}
      <div className="absolute inset-0 -z-10 bg-gradient-to-r from-black/80 via-black/45 to-transparent" />
      <div className="max-w-xl p-5 sm:p-8">
        <h3 className="casino-gold font-display text-2xl font-bold sm:text-3xl">{banner.title}</h3>
        {banner.description ? (
          <p className="mt-2 text-sm text-fg/90 sm:text-base">{banner.description}</p>
        ) : null}
        {banner.link ? (
          <div className="mt-4">
            {isInternal(banner.link) ? (
              <Link to={banner.link} className={button}>
                {cta}
              </Link>
            ) : (
              <a href={banner.link} target="_blank" rel="noopener noreferrer" className={button}>
                {cta}
              </a>
            )}
          </div>
        ) : null}
      </div>
    </article>
  )
}
