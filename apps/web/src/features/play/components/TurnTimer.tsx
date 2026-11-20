import { useTranslation } from 'react-i18next'

/** From here the timer turns red; in the last seconds it also ticks. */
export const LOW_SECONDS = 10
const TICK_SECONDS = 5

/**
 * The turn timer on the acting player's plate (HayBet item 11; Checkers first, any game with a per-turn timer): the
 * seconds inside a ring that drains with the turn. It follows the server's deadline (useCountdown corrects for the
 * device clock), turns red from 10 s and ticks in the last 5 — gently, and not at all under reduced motion.
 */
export function TurnTimer({
  seconds,
  total,
  mine,
}: {
  seconds: number
  total: number
  mine: boolean
}) {
  const { t } = useTranslation()
  const low = seconds <= LOW_SECONDS
  const share = total > 0 ? Math.min(1, Math.max(0, seconds / total)) : 0
  const radius = 15
  const length = 2 * Math.PI * radius
  const colour = low ? 'var(--color-danger)' : mine ? 'var(--color-turn)' : 'var(--color-gold)'

  return (
    <span
      role="timer"
      aria-live={low ? 'polite' : 'off'}
      aria-label={t('room.secondsLeft', { count: seconds })}
      className={`relative inline-flex size-10 shrink-0 items-center justify-center ${
        low && seconds <= TICK_SECONDS
          ? 'motion-safe:animate-[timer-tick_1s_ease-in-out_infinite]'
          : ''
      }`}
    >
      <svg viewBox="0 0 36 36" aria-hidden="true" className="absolute inset-0 size-full -rotate-90">
        <circle
          cx="18"
          cy="18"
          r={radius}
          fill="rgb(0 0 0 / 0.35)"
          stroke="rgb(255 255 255 / 0.12)"
          strokeWidth="3"
        />
        <circle
          cx="18"
          cy="18"
          r={radius}
          fill="none"
          stroke={colour}
          strokeWidth="3"
          strokeLinecap="round"
          strokeDasharray={length}
          strokeDashoffset={length * (1 - share)}
          className="transition-[stroke-dashoffset,stroke] duration-1000 ease-linear motion-reduce:transition-none"
          style={low ? { filter: `drop-shadow(0 0 4px ${colour})` } : undefined}
        />
      </svg>
      <span
        className={`relative font-mono text-sm font-bold tabular-nums ${low ? 'text-danger' : 'text-fg'}`}
      >
        {seconds}
      </span>
    </span>
  )
}
