import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useLocation } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { useLevelUpOpen } from '@/features/levels/popup'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
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
  const { data: user } = useCurrentUser()
  const open = useDailyBonusOpen()
  const levelUp = useLevelUpOpen()
  const { pathname } = useLocation()
  const waiting = bonus.data && !bonus.data.claimed

  useEffect(() => {
    if (waiting && user && !pathname.startsWith('/rooms/')) offerDailyBonus(user.id)
  }, [waiting, user, pathname])

  // A level-up popup goes first; this one waits for it.
  return open && bonus.data && !levelUp ? <Dialog bonus={bonus.data} /> : null
}

function Dialog({ bonus }: { bonus: DailyBonus }) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const chest = useRef<HTMLDivElement>(null)
  const format = (n: number) => n.toLocaleString(i18n.language)

  // One request per click burst (a double click must not show an error for the second).
  const sent = useRef(false)
  const collect = useMutation({
    mutationFn: collectDailyBonus,
    onSuccess: (next) => {
      queryClient.setQueryData(dailyBonusKey, next)
      void queryClient.invalidateQueries({ queryKey: walletKey })
      // The chips take off from the chest and the popup steps aside at once, so the header balance
      // is in plain view (not under the backdrop) when they land and it counts up.
      flyFishkas(chest.current, 'in')
      window.setTimeout(closeDailyBonus, 250)
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

  const collected = collect.data?.collected ?? 0
  const done = bonus.claimed
  const last = bonus.days.length
  const toDouble = last - bonus.day

  return (
    <Modal
      onClose={closeDailyBonus}
      labelledBy="daily-bonus-title"
      describedBy="daily-bonus-body"
      size="lg"
      className="text-center"
    >
      {/* Deep green felt at the top, warming to gold where the chest stands. */}
      <div className="bg-emerald-800 px-4 pt-6 pb-5 sm:px-7">
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

        <p id="daily-bonus-body" className="mt-4 text-sm font-semibold text-white/90 sm:text-base">
          {done
            ? t('daily.comeBack', { amount: format(bonus.tomorrow) })
            : toDouble > 0
              ? t('daily.toDouble', { count: toDouble })
              : t('daily.doubleToday')}
        </p>
      </div>

      <div className="surface-ivory relative rounded-none px-4 pt-2 pb-6 sm:px-7">
        <div className="flex items-center justify-center gap-4 sm:gap-6">
          <div ref={chest} className="relative">
            <Chest open={!done || collected > 0} />
          </div>
          <p
            className="flex items-center gap-2 text-4xl font-extrabold text-ink tabular-nums sm:text-5xl"
            aria-live="polite"
          >
            <FishkaIcon className="size-10 sm:size-12" />
            {collected > 0
              ? `+${format(collected)}`
              : done
                ? t('daily.collectedAmount', { amount: format(bonus.amount) })
                : format(bonus.amount)}
          </p>
        </div>

        {collect.isError ? (
          <p role="alert" className="mt-2 text-sm text-danger">
            {t('daily.failed')}
          </p>
        ) : null}

        {/* Collect first in the tab order everywhere; on wide screens Later sits to its left. The
              Collect slot stays (disabled, "Collected") so the sheet doesn't jump. */}
        <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <Button
            variant="gold"
            className="min-h-12 text-lg sm:min-w-48"
            onClick={take}
            disabled={done || collect.isPending}
          >
            {done
              ? `✓ ${t('daily.collected')}`
              : collect.isPending
                ? t('daily.collecting')
                : t('daily.collect')}
          </Button>
          <Button
            variant="secondary"
            className="min-h-12 sm:order-first sm:min-w-32"
            onClick={closeDailyBonus}
          >
            {done ? t('daily.close') : t('daily.later')}
          </Button>
        </div>
      </div>
    </Modal>
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
            ? 'bg-emerald-650 text-white/85'
            : 'bg-walnut-700 text-white/90'
      }`}
      style={{
        clipPath: first
          ? 'polygon(0 0, 86% 0, 100% 50%, 86% 100%, 0 100%)'
          : 'polygon(0 0, 86% 0, 100% 50%, 86% 100%, 0 100%, 14% 50%)',
      }}
    >
      <span className="text-[0.7rem] font-extrabold tracking-wide uppercase sm:text-xs">
        {t('daily.day', { day })}
      </span>
      {state === 'past' || state === 'done' ? (
        <span className="text-lg leading-none font-black">
          <span aria-hidden="true">✓</span>
          <span className="sr-only">{t('daily.collected')}</span>
        </span>
      ) : double ? (
        // The double day: "×2" where the others show a chip.
        <span className="flex h-5 items-center rounded-full bg-[#b3261e] px-1.5 text-xs font-black text-white sm:h-6 sm:text-sm">
          <span aria-hidden="true">×2</span>
          <span className="sr-only">{t('daily.doubleDay')}</span>
        </span>
      ) : (
        <>
          <FishkaIcon className="size-5 sm:size-6" />
          <span className="sr-only">{t(lit ? 'daily.today' : 'daily.upcoming')}</span>
        </>
      )}
      <span className="max-w-full truncate text-[0.7rem] font-bold tabular-nums sm:text-xs">
        {amount}
      </span>
    </li>
  )
}

/** Our own chest: wood and brass, lid up and light pouring out while there is a bonus inside. */
function Chest({ open }: { open: boolean }) {
  return (
    <svg viewBox="0 0 120 100" className="h-24 w-28 sm:h-28 sm:w-32" aria-hidden="true">
      <defs>
        <radialGradient id="chest-glow" cx="50%" cy="50%" r="60%">
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
            cy="44"
            rx="58"
            ry="42"
            fill="url(#chest-glow)"
            className="motion-safe:animate-pulse"
          />
          {/* Lid, tipped back behind the box. */}
          <path d="M20 42 L30 6 H90 L100 42 Z" fill="#5a1a10" stroke="#3a0f08" strokeWidth="2" />
          <path d="M30 6 H90" stroke="url(#chest-brass)" strokeWidth="4" />
        </>
      ) : null}
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
      <rect x="16" y="84" width="88" height="6" fill="url(#chest-brass)" />
      <rect
        x="52"
        y="56"
        width="16"
        height="18"
        rx="3"
        fill="url(#chest-brass)"
        stroke="#6b4a12"
        strokeWidth="1.5"
      />
      <circle cx="60" cy="64" r="2.5" fill="#4a3410" />
      {open ? (
        // Chips heaped over the rim.
        <g stroke="#2b1408" strokeWidth="1.5">
          <ellipse cx="38" cy="44" rx="12" ry="5" fill="#e6e1d3" />
          <ellipse cx="60" cy="41" rx="12" ry="5" fill="#c73a2c" />
          <ellipse cx="82" cy="44" rx="12" ry="5" fill="#2f6fb3" />
          <ellipse cx="49" cy="36" rx="11" ry="4.5" fill="#d9b44a" />
          <ellipse cx="71" cy="36" rx="11" ry="4.5" fill="#e6e1d3" />
        </g>
      ) : (
        <>
          <path
            d="M16 42 Q60 22 104 42 Z"
            fill="url(#chest-wood)"
            stroke="#4a160d"
            strokeWidth="2"
          />
          <rect x="16" y="42" width="88" height="7" fill="url(#chest-brass)" />
        </>
      )}
    </svg>
  )
}
