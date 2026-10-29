import {
  useEffect,
  useRef,
  useState,
  ViewTransition,
  type CSSProperties,
  type ReactElement,
} from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Badge } from '@/shared/ui/Badge'
import { ChevronIcon, PlayIcon } from '@/shared/ui/icons'
import type { GameSummary } from '../queries'
import { artTransition, titleTransition } from '../transitions'
import { GameArtwork } from './GameArtwork'
import { PlayerCount } from './PlayerCount'

const AUTOPLAY_MS = 6000

/** How far slide `index` sits from the active one, the short way round the ring. */
function offsetOf(index: number, active: number, count: number) {
  let d = index - active
  if (d > count / 2) d -= count
  if (d < -count / 2) d += count
  return d
}

/**
 * The games as a ring of slides: the chosen one large in the middle, its neighbours turned away to
 * either side. Arrows, dots, swiping and the arrow keys move it; it turns by itself until the
 * player takes over (never with reduced motion). Opening a game carries its art into the game page.
 */
export function GameCarousel({ games }: { games: GameSummary[] }) {
  const { t } = useTranslation()
  const [active, setActive] = useState(0)
  const [paused, setPaused] = useState(false)
  const swipe = useRef<number | null>(null)
  const count = games.length

  const go = (delta: number) => {
    setPaused(true)
    setActive((current) => (current + delta + count) % count)
  }

  useEffect(() => {
    if (paused || count < 2) return
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return
    const timer = window.setInterval(() => setActive((i) => (i + 1) % count), AUTOPLAY_MS)
    return () => window.clearInterval(timer)
  }, [paused, count])

  return (
    <section
      aria-roledescription={t('carousel.carousel')}
      aria-label={t('carousel.label')}
      className="@container relative"
      onMouseEnter={() => setPaused(true)}
      onFocus={() => setPaused(true)}
      onKeyDown={(event) => {
        if (event.key === 'ArrowLeft') go(-1)
        if (event.key === 'ArrowRight') go(1)
      }}
      onPointerDown={(event) => {
        swipe.current = event.clientX
      }}
      onPointerUp={(event) => {
        if (swipe.current === null) return
        const dx = event.clientX - swipe.current
        swipe.current = null
        if (Math.abs(dx) > 40) go(dx < 0 ? 1 : -1)
      }}
    >
      <ul className="relative h-[100cqw] [perspective:1600px] sm:h-[min(23.75rem,50cqw)]">
        {games.map((game, index) => {
          const d = offsetOf(index, active, count)
          const near = Math.abs(d)
          const isActive = d === 0
          const style: CSSProperties = {
            transform: `translateX(calc(-50% + ${d * 58}%)) scale(${1 - near * 0.16}) rotateY(${-d * 22}deg)`,
            zIndex: 10 - near,
            opacity: near > 2 ? 0 : 1,
            pointerEvents: near > 2 ? 'none' : undefined,
          }
          return (
            <li
              key={game.slug}
              aria-roledescription={t('carousel.slide')}
              aria-label={t('carousel.position', { index: index + 1, count })}
              className="absolute top-0 left-1/2 w-[min(38rem,80%)] transition-[transform,opacity] duration-700 ease-[cubic-bezier(0.2,0.8,0.2,1)] [transform-style:preserve-3d] motion-reduce:transition-none"
              style={style}
            >
              <Link
                to={`/games/${game.slug}`}
                tabIndex={isActive ? 0 : -1}
                onClick={(event) => {
                  // A click on a side slide brings it to the middle first; the keyboard opens it.
                  if (!isActive && event.detail > 0) {
                    event.preventDefault()
                    setPaused(true)
                    setActive(index)
                  }
                }}
                className={`group relative block overflow-hidden rounded-[1.75rem] border shadow-2xl shadow-black/60 transition-[filter,border-color] duration-500 ${
                  isActive ? 'border-gold/60' : 'border-gold/15 brightness-[0.55] saturate-[0.8]'
                }`}
              >
                <Shared name={isActive ? artTransition(game.slug) : null}>
                  <GameArtwork
                    game={game}
                    className="aspect-[4/5] w-full transition-transform duration-700 group-hover:scale-105 sm:aspect-[16/10]"
                  />
                </Shared>
                <div
                  aria-hidden="true"
                  className="absolute inset-0 bg-linear-to-t from-black/90 via-black/30 to-transparent"
                />
                {game.is_playable ? null : (
                  <span className="absolute top-4 left-4">
                    <Badge tone="warning">{t('catalog.comingSoon')}</Badge>
                  </span>
                )}
                <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-4 p-5 sm:p-7">
                  <div className="min-w-0">
                    <Shared name={isActive ? titleTransition(game.slug) : null}>
                      <h3 className="text-gold-gradient text-3xl font-extrabold tracking-tight sm:text-5xl">
                        {game.name}
                      </h3>
                    </Shared>
                    <p className="mt-1 text-sm font-medium text-fg/85">
                      <PlayerCount game={game} />
                    </p>
                    <p
                      className={`mt-2 line-clamp-2 max-w-md text-sm text-fg/75 transition-opacity duration-500 ${isActive ? 'opacity-100' : 'opacity-0'}`}
                    >
                      {game.description}
                    </p>
                  </div>
                  {game.is_playable ? (
                    <span
                      aria-hidden="true"
                      className={`flex shrink-0 items-center gap-2 rounded-full border border-white/15 bg-linear-to-b from-accent-light to-accent px-5 py-3 text-sm font-bold text-white uppercase shadow-lg transition duration-500 ${
                        isActive ? 'scale-100 opacity-100' : 'scale-75 opacity-0'
                      }`}
                    >
                      <PlayIcon className="size-4" />
                      {t('catalog.play')}
                    </span>
                  ) : null}
                </div>
              </Link>
            </li>
          )
        })}
      </ul>

      {count > 1 ? (
        <div className="mt-5 flex items-center justify-center gap-4">
          <button
            type="button"
            onClick={() => go(-1)}
            aria-label={t('carousel.previous')}
            className="flex size-11 items-center justify-center rounded-full border border-gold/30 bg-surface-raised/70 text-gold transition hover:border-gold hover:bg-surface-raised"
          >
            <ChevronIcon className="size-5 rotate-180" />
          </button>
          <div className="flex items-center gap-2">
            {games.map((game, index) => (
              <button
                key={game.slug}
                type="button"
                aria-label={t('carousel.show', { name: game.name })}
                aria-current={index === active ? 'true' : undefined}
                onClick={() => {
                  setPaused(true)
                  setActive(index)
                }}
                className={`h-2.5 rounded-full transition-all duration-500 ${
                  index === active ? 'w-8 bg-gold' : 'w-2.5 bg-fg/25 hover:bg-fg/50'
                }`}
              />
            ))}
          </div>
          <button
            type="button"
            onClick={() => go(1)}
            aria-label={t('carousel.next')}
            className="flex size-11 items-center justify-center rounded-full border border-gold/30 bg-surface-raised/70 text-gold transition hover:border-gold hover:bg-surface-raised"
          >
            <ChevronIcon className="size-5" />
          </button>
        </div>
      ) : null}
    </section>
  )
}

/** Names an element that travels to the next page; only the slide in the middle carries one. */
function Shared({ name, children }: { name: string | null; children: ReactElement }) {
  return name ? <ViewTransition name={name}>{children}</ViewTransition> : children
}
