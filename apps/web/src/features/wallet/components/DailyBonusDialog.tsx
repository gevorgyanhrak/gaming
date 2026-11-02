import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef } from 'react'
import { createPortal } from 'react-dom'
import { useTranslation } from 'react-i18next'
import { useLocation } from 'react-router'
import { Button } from '@/shared/ui/Button'
import { walletKey } from '../api'
import {
  closeDailyBonus,
  collectDailyBonus,
  dailyBonusKey,
  offerDailyBonus,
  useDailyBonus,
  useDailyBonusOpen,
  type DailyBonus,
} from '../dailyBonus'
import { flyFishkas } from '../flyFishkas'
import { FishkaIcon } from './FishkaIcon'

/**
 * The daily bonus (TASK-044): a row of the streak's days with today's lit up, the chest with today's
 * Fishkas, and "Collect". It offers itself once per visit while today's bonus is waiting — never over a
 * game in progress — and opens from the wallet page. The server decides the day and the amount and
 * gives it once a day.
 */
export function DailyBonusDialog() {
  const bonus = useDailyBonus()
  const open = useDailyBonusOpen()
  const { pathname } = useLocation()
  const waiting = bonus.data && !bonus.data.claimed

  useEffect(() => {
    if (waiting && !pathname.startsWith('/rooms/')) offerDailyBonus()
  }, [waiting, pathname])

  return open && bonus.data ? createPortal(<Dialog bonus={bonus.data} />, document.body) : null
}

function Dialog({ bonus }: { bonus: DailyBonus }) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const panel = useRef<HTMLDivElement>(null)
  const chest = useRef<HTMLDivElement>(null)
  const format = (n: number) => n.toLocaleString(i18n.language)

  // One request per click burst (a double click must not show an error for the second).
  const sent = useRef(false)
  const collect = useMutation({
    mutationFn: collectDailyBonus,
    onSuccess: (next) => {
      queryClient.setQueryData(dailyBonusKey, next)
      void queryClient.invalidateQueries({ queryKey: walletKey })
      flyFishkas(chest.current, 'in', () => window.setTimeout(closeDailyBonus, 700))
    },
    onError: () => {
      sent.current = false
    },
  })
  const take = () => {
    if (sent.current) return
    sent.current = true
    collect.mutate()
  }

  // Modal: the app behind is inert and doesn't scroll; Escape closes; focus returns where it was.
  useEffect(() => {
    const opener = document.activeElement as HTMLElement | null
    const app = document.getElementById('root')
    const overflow = document.body.style.overflow
    app?.setAttribute('inert', '')
    document.body.style.overflow = 'hidden'
    panel.current?.focus()
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') closeDailyBonus()
    }
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
      app?.removeAttribute('inert')
      document.body.style.overflow = overflow
      opener?.focus()
    }
  }, [])

  const collected = collect.data?.collected ?? 0
  const done = bonus.claimed
  const last = bonus.days.length
  const toDouble = last - bonus.day
  const tomorrow = bonus.days[Math.min(bonus.day, last - 1)]?.amount ?? bonus.amount

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center p-3 sm:items-center sm:p-4">
      <div
        aria-hidden="true"
        className="absolute inset-0 bg-black/70 backdrop-blur-sm motion-safe:animate-[fade-in_200ms_ease-out]"
        onClick={closeDailyBonus}
      />
      <div
        ref={panel}
        role="dialog"
        aria-modal="true"
        aria-labelledby="daily-bonus-title"
        aria-describedby="daily-bonus-body"
        tabIndex={-1}
        className="club-frame relative mb-[env(safe-area-inset-bottom)] w-full max-w-xl overflow-hidden rounded-3xl text-center outline-none motion-safe:animate-rise"
      >
        {/* Deep green felt at the top, warming to gold where the chest stands. */}
        <div className="bg-linear-to-b from-[#0d3a2a] via-[#123f2e] to-[#3b2a14] px-4 pt-6 pb-5 sm:px-7">
          <h2
            id="daily-bonus-title"
            className="text-gold-gradient text-3xl font-extrabold tracking-wide uppercase sm:text-4xl"
          >
            {t('daily.title')}
          </h2>

          <ol className="mt-5 flex" aria-label={t('daily.days')}>
            {bonus.days.map(({ day, amount }) => (
              <DayTile
                key={day}
                day={day}
                amount={format(amount)}
                state={
                  day < bonus.day ? 'past' : day === bonus.day ? (done ? 'done' : 'today') : 'next'
                }
                double={day === last}
                first={day === 1}
              />
            ))}
          </ol>

          <p
            id="daily-bonus-body"
            className="mt-4 text-sm font-semibold text-white/90 sm:text-base"
          >
            {done
              ? t('daily.comeBack', { amount: format(tomorrow) })
              : toDouble > 0
                ? t('daily.toDouble', { count: toDouble })
                : t('daily.doubleToday')}
          </p>
        </div>

        <div className="relative bg-linear-to-b from-[#3b2a14] to-[#24180c] px-4 pt-2 pb-6 sm:px-7">
          <div className="flex items-center justify-center gap-4 sm:gap-6">
            <div ref={chest} className="relative">
              <Chest open={!done || collected > 0} />
            </div>
            <p
              className="flex items-center gap-2 text-4xl font-extrabold text-gold-light tabular-nums drop-shadow-[0_2px_6px_rgb(0_0_0/0.6)] sm:text-5xl"
              aria-live="polite"
            >
              <FishkaIcon className="size-10 sm:size-12" />
              {collected > 0 ? `+${format(collected)}` : format(bonus.amount)}
            </p>
          </div>

          {collect.isError ? (
            <p role="alert" className="mt-2 text-sm text-[#f87171]">
              {t('daily.failed')}
            </p>
          ) : null}

          <div className="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
            <Button variant="secondary" className="min-h-12 sm:min-w-32" onClick={closeDailyBonus}>
              {done ? t('daily.close') : t('daily.later')}
            </Button>
            {done ? null : (
              <Button
                variant="gold"
                className="min-h-12 text-lg sm:min-w-48"
                onClick={take}
                disabled={collect.isPending}
              >
                {collect.isPending ? t('daily.collecting') : t('daily.collect')}
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

/** One day of the streak, shaped like a ticket pointing to the next. */
function DayTile({
  day,
  amount,
  state,
  double,
  first,
}: {
  day: number
  amount: string
  state: 'past' | 'done' | 'today' | 'next'
  double: boolean
  first: boolean
}) {
  const { t } = useTranslation()
  const lit = state === 'today' || state === 'done'
  return (
    <li
      aria-current={lit ? 'date' : undefined}
      className={`relative -ml-1.5 flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 py-2.5 pr-3 pl-4 first:ml-0 ${
        lit
          ? 'z-10 scale-105 bg-linear-to-b from-gold-light via-gold to-gold-dark text-black shadow-[0_0_24px_rgb(214_171_78/0.7)]'
          : state === 'past'
            ? 'bg-[#1d5a41] text-white/80'
            : 'bg-[#4a2a1f] text-white/85'
      }`}
      style={{
        clipPath: first
          ? 'polygon(0 0, 86% 0, 100% 50%, 86% 100%, 0 100%)'
          : 'polygon(0 0, 86% 0, 100% 50%, 86% 100%, 0 100%, 14% 50%)',
      }}
    >
      <span className="text-[0.65rem] font-extrabold tracking-wide uppercase sm:text-xs">
        {t('daily.day', { day })}
      </span>
      {state === 'past' || state === 'done' ? (
        <span aria-label={t('daily.collected')} className="text-lg leading-none font-black">
          ✓
        </span>
      ) : (
        <FishkaIcon className="size-5 sm:size-6" />
      )}
      <span className="max-w-full truncate text-[0.65rem] font-bold tabular-nums sm:text-xs">
        {amount}
      </span>
      {double ? (
        <span className="absolute top-0.5 right-4 rounded-full bg-[#e0483e] px-1 text-[0.6rem] font-black text-white">
          ×2
        </span>
      ) : null}
    </li>
  )
}

/** Our own chest: wood and brass, lid up and light pouring out while there is a bonus inside. */
function Chest({ open }: { open: boolean }) {
  return (
    <svg viewBox="0 0 120 100" className="h-24 w-28 sm:h-28 sm:w-32" aria-hidden="true">
      <defs>
        <radialGradient id="chest-glow" cx="50%" cy="60%" r="60%">
          <stop offset="0" stopColor="#ffe9a8" stopOpacity="0.95" />
          <stop offset="1" stopColor="#ffe9a8" stopOpacity="0" />
        </radialGradient>
        <linearGradient id="chest-wood" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0" stopColor="#b5452f" />
          <stop offset="1" stopColor="#7a2618" />
        </linearGradient>
        <linearGradient id="chest-brass" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0" stopColor="#f6dc8a" />
          <stop offset="1" stopColor="#b7892f" />
        </linearGradient>
      </defs>
      {open ? (
        <>
          <ellipse
            cx="60"
            cy="48"
            rx="56"
            ry="40"
            fill="url(#chest-glow)"
            className="motion-safe:animate-pulse"
          />
          {/* Lid, tipped back. */}
          <path
            d="M18 40 L28 8 H92 L102 40 Z"
            fill="url(#chest-wood)"
            stroke="#4a160d"
            strokeWidth="2"
          />
          <path d="M28 8 H92" stroke="url(#chest-brass)" strokeWidth="4" />
          {/* Chips inside. */}
          <g stroke="#2b1408" strokeWidth="1.5">
            <ellipse cx="44" cy="46" rx="11" ry="5" fill="#e6e1d3" />
            <ellipse cx="60" cy="44" rx="11" ry="5" fill="#c73a2c" />
            <ellipse cx="76" cy="46" rx="11" ry="5" fill="#2f6fb3" />
          </g>
        </>
      ) : (
        <path d="M18 42 Q60 20 102 42 Z" fill="url(#chest-wood)" stroke="#4a160d" strokeWidth="2" />
      )}
      {/* Body. */}
      <rect
        x="16"
        y="42"
        width="88"
        height="50"
        rx="5"
        fill="url(#chest-wood)"
        stroke="#4a160d"
        strokeWidth="2"
      />
      <rect x="16" y="42" width="88" height="7" fill="url(#chest-brass)" />
      <rect x="16" y="84" width="88" height="6" fill="url(#chest-brass)" />
      <rect
        x="52"
        y="54"
        width="16"
        height="18"
        rx="3"
        fill="url(#chest-brass)"
        stroke="#6b4a12"
        strokeWidth="1.5"
      />
      <circle cx="60" cy="62" r="2.5" fill="#4a3410" />
    </svg>
  )
}
