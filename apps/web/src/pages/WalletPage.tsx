import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { fetchFishkaHistory, walletHistoryKey, type FishkaTransaction } from '@/features/wallet/api'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { useWallet } from '@/features/wallet/hooks'
import { openOutOfFishkas } from '@/features/wallet/outOfFishkas'
import { Button } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'

/** The player's Fishkas: balance, totals, how fees work, and every change (newest first). */
export function WalletPage() {
  const { t, i18n } = useTranslation()
  const wallet = useWallet()
  const [page, setPage] = useState(1)
  const history = useQuery({
    queryKey: [...walletHistoryKey, page],
    queryFn: ({ signal }) => fetchFishkaHistory(page, signal),
  })
  const format = (n: number) => n.toLocaleString(i18n.language)
  // A total with its direction, but a plain 0 when nothing moved.
  const signed = (n: number, sign: string) => (n === 0 ? format(0) : `${sign}${format(n)}`)

  return (
    <Container className="space-y-6 py-10">
      <title>{t('fishka.walletTitle')}</title>
      <h1 className="text-gold-gradient text-3xl font-extrabold">{t('fishka.walletTitle')}</h1>

      {wallet.isPending ? (
        <Skeleton className="h-36 w-full" />
      ) : wallet.isError ? (
        <QueryErrorState error={wallet.error} onRetry={() => void wallet.refetch()} />
      ) : (
        <section className="club-panel grid gap-4 rounded-3xl p-5 sm:grid-cols-3 sm:p-6">
          <div className="flex items-center gap-3 sm:col-span-3">
            <FishkaIcon className="size-12" />
            <div>
              <p className="text-xs font-semibold tracking-wider text-muted uppercase">
                {t('fishka.balance')}
              </p>
              <p className="text-3xl font-extrabold text-gold-light tabular-nums">
                {format(wallet.data.balance)}
              </p>
            </div>
            {wallet.data.refill.available ? (
              <Button variant="gold" className="ml-auto min-h-11" onClick={openOutOfFishkas}>
                {t('fishka.getBonus', {
                  count: wallet.data.refill.amount,
                  amount: format(wallet.data.refill.amount),
                })}
              </Button>
            ) : null}
          </div>
          <Stat
            label={t('fishka.totalEarned')}
            value={signed(wallet.data.total_earned, '+')}
            tone={wallet.data.total_earned ? 'text-beaten' : ''}
          />
          <Stat
            label={t('fishka.totalSpent')}
            value={signed(wallet.data.total_spent, '−')}
            tone={wallet.data.total_spent ? 'text-take' : ''}
          />
          <p className="text-sm text-muted sm:col-span-3">
            {t('fishka.rules', {
              count: wallet.data.entry_fee,
              fee: format(wallet.data.entry_fee),
            })}{' '}
            {t('fishka.note')}
          </p>
        </section>
      )}

      <section aria-labelledby="fishka-history" className="space-y-3">
        <h2 id="fishka-history" className="text-xl font-bold">
          {t('fishka.history')}
        </h2>
        {history.isPending ? (
          <Skeleton className="h-48 w-full" />
        ) : history.isError ? (
          <QueryErrorState error={history.error} onRetry={() => void history.refetch()} />
        ) : history.data.data.length === 0 ? (
          <EmptyState title={t('fishka.historyEmpty')} />
        ) : (
          <>
            <ul className="club-panel divide-y divide-gold/10 rounded-3xl">
              {history.data.data.map((entry) => (
                <Entry key={entry.id} entry={entry} />
              ))}
            </ul>
            {history.data.meta.last_page > 1 ? (
              <div className="flex items-center justify-between">
                <Button
                  variant="secondary"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => p - 1)}
                >
                  {t('history.previous')}
                </Button>
                <span className="text-sm text-muted">
                  {t('history.page', { page, total: history.data.meta.last_page })}
                </span>
                <Button
                  variant="secondary"
                  disabled={page >= history.data.meta.last_page}
                  onClick={() => setPage((p) => p + 1)}
                >
                  {t('history.next')}
                </Button>
              </div>
            ) : null}
          </>
        )}
      </section>
    </Container>
  )
}

function Stat({ label, value, tone }: { label: string; value: string; tone: string }) {
  return (
    <div className="rounded-2xl bg-black/20 p-3">
      <p className="text-xs font-semibold tracking-wider text-muted uppercase">{label}</p>
      <p className={`text-xl font-bold tabular-nums ${tone}`}>{value}</p>
    </div>
  )
}

function Entry({ entry }: { entry: FishkaTransaction }) {
  const { t, i18n } = useTranslation()
  const gain = entry.amount > 0
  return (
    <li className="flex items-center gap-3 px-4 py-3">
      <div className="min-w-0 flex-1">
        <p className="font-semibold">{t(`fishka.type.${entry.type}`)}</p>
        <p className="text-xs text-muted">
          {new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
          }).format(new Date(entry.created_at))}
        </p>
      </div>
      <div className="text-right tabular-nums">
        <p className={`font-bold ${gain ? 'text-beaten' : 'text-take'}`}>
          {gain ? '+' : '−'}
          {Math.abs(entry.amount).toLocaleString(i18n.language)}
        </p>
        <p className="text-xs text-muted">{entry.balance_after.toLocaleString(i18n.language)}</p>
      </div>
    </li>
  )
}
