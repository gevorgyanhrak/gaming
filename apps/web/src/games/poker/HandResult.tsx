import { useEffect, useRef, type ReactNode } from 'react'
import type { ResultSummary } from './result'

/** The plate in the middle of the table that says who won. */
export function ResultPlate({ summary }: { summary: ResultSummary }) {
  return (
    <div className="max-w-[70%] rounded-2xl bg-black/70 px-4 py-2 text-center shadow-lg sm:max-w-[90%]">
      <p
        className={
          summary.mine
            ? 'text-gold-gradient text-xl font-extrabold sm:text-3xl'
            : 'text-base font-bold text-white sm:text-xl'
        }
      >
        {summary.headline}
      </p>
      {summary.details.map((line) => (
        <p key={line} className="text-sm text-white/80">
          {line}
        </p>
      ))}
    </div>
  )
}

const reducedMotion = () =>
  typeof window !== 'undefined' &&
  typeof window.matchMedia === 'function' &&
  window.matchMedia('(prefers-reduced-motion: reduce)').matches

/**
 * A few chips sliding from the middle of the table to a winner's seat, then fading. Decorative;
 * skipped under reduced motion and where the browser can't animate.
 */
export function ChipFlight({
  to,
  delay,
  children,
}: {
  to: { left: string; top: string }
  delay: number
  children: ReactNode
}) {
  const el = useRef<HTMLSpanElement>(null)
  useEffect(() => {
    const node = el.current
    if (!node || typeof node.animate !== 'function' || reducedMotion()) return
    const flight = node.animate(
      [
        { left: '50%', top: '50%', opacity: 0 },
        { left: '50%', top: '50%', opacity: 1, offset: 0.1 },
        { left: to.left, top: to.top, opacity: 1, offset: 0.85 },
        { left: to.left, top: to.top, opacity: 0 },
      ],
      { duration: 700, delay, easing: 'cubic-bezier(.2,.8,.2,1)', fill: 'both' },
    )
    return () => flight.cancel()
  }, [to.left, to.top, delay])

  return (
    <span
      ref={el}
      aria-hidden="true"
      className="pointer-events-none absolute z-20 -translate-x-1/2 -translate-y-1/2 opacity-0"
      style={{ left: '50%', top: '50%' }}
    >
      {children}
    </span>
  )
}
