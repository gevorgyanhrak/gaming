import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useWallet } from '../hooks'
import { FishkaIcon } from './FishkaIcon'

/**
 * The balance in the header, linking to the wallet: the wallet plus Fishkas at a poker table right
 * now (they're yours too). When it changes the number bumps and the change floats beside it (green for
 * a gain, red for a cost); screen readers hear the change. Flying chips aim at `data-fishka-balance`.
 */
export function FishkaBalance() {
  const { t, i18n } = useTranslation()
  const wallet = useWallet()
  const balance = wallet.data === undefined ? undefined : wallet.data.balance + wallet.data.at_table
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
      data-fishka-balance=""
      aria-label={
        wallet.data?.at_table
          ? t('fishka.balanceLabelTable', {
              amount: format(balance),
              table: format(wallet.data.at_table),
            })
          : t('fishka.balanceLabel', { amount: format(balance) })
      }
      className="relative flex min-h-11 items-center gap-1.5 rounded-full border border-gold/45 bg-emerald-900/80 py-1 pr-3 pl-1.5 text-sm font-bold text-gold-light shadow-inner transition-colors duration-150 hover:border-gold"
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
          className={`pointer-events-none absolute top-full right-2 mt-1 rounded-full bg-emerald-900 px-2 py-0.5 text-xs font-extrabold whitespace-nowrap tabular-nums motion-safe:animate-[fishka-delta_1800ms_ease-out_forwards] motion-reduce:hidden ${
            delta > 0 ? 'text-success' : 'text-danger'
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
