import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useWallet } from '../hooks'
import { FishkaIcon } from './FishkaIcon'

/**
 * The balance in the header, linking to the wallet. When it changes the number bumps and the change
 * floats up beside it (green for a gain, red for a cost); screen readers hear the change.
 */
export function FishkaBalance() {
  const { t, i18n } = useTranslation()
  const wallet = useWallet()
  const balance = wallet.data?.balance
  const format = (n: number) => n.toLocaleString(i18n.language)

  // The last change, derived while rendering: a new balance after a known one is a change to show.
  const [seen, setSeen] = useState<{ balance?: number; delta: number; id: number }>({
    balance,
    delta: 0,
    id: 0,
  })
  if (balance !== undefined && balance !== seen.balance) {
    setSeen({
      balance,
      delta: seen.balance === undefined ? 0 : balance - seen.balance,
      id: seen.id + 1,
    })
  }

  if (balance === undefined) return null
  const { delta, id } = seen

  return (
    <Link
      to="/wallet"
      aria-label={t('fishka.balanceLabel', { amount: format(balance) })}
      className="relative flex min-h-10 items-center gap-1.5 rounded-full border border-gold/35 bg-black/30 py-1 pr-3 pl-1.5 text-sm font-bold text-gold-light shadow-inner transition hover:border-gold"
    >
      <FishkaIcon className="size-6 shrink-0" />
      <span
        key={id}
        className={`tabular-nums ${delta ? 'motion-safe:animate-[fishka-bump_450ms_ease-out]' : ''}`}
      >
        {format(balance)}
      </span>
      {delta ? (
        <span
          key={`delta-${id}`}
          aria-hidden="true"
          className={`pointer-events-none absolute top-full right-2 mt-1 rounded-full bg-black/80 px-2 py-0.5 text-xs font-extrabold whitespace-nowrap tabular-nums motion-safe:animate-[fishka-delta_1800ms_ease-out_forwards] motion-reduce:hidden ${
            delta > 0 ? 'text-beaten' : 'text-take'
          }`}
        >
          {delta > 0 ? '+' : '−'}
          {format(Math.abs(delta))}
        </span>
      ) : null}
      <span className="sr-only" role="status">
        {delta
          ? t(delta > 0 ? 'fishka.gained' : 'fishka.spent', {
              count: Math.abs(delta),
              amount: format(Math.abs(delta)),
            })
          : ''}
      </span>
    </Link>
  )
}
