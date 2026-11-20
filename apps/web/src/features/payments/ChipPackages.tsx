import { useMutation } from '@tanstack/react-query'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { useMessage } from '@/features/auth/forms'
import { randomId } from '@/shared/lib/uuid'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import { Skeleton } from '@/shared/ui/Skeleton'
import { type ChipPackage, createPaymentOrder, useChipPackages } from './api'

/**
 * The chip packages (HayBet item 6, ADR 0027): Fishkas, bonus, price, the recommended one highlighted. Buying sends
 * the player to the payment provider's page; Fishkas arrive only when the provider confirms the payment. Until a
 * provider is connected the packages show with buying switched off and a plain note — nothing pretends to sell.
 */
export function ChipPackages({ compact = false }: { compact?: boolean }) {
  const { t } = useTranslation()
  const packages = useChipPackages()
  const message = useMessage()
  // One order per tap burst (the server is idempotent per key too).
  const keyRef = useRef<string | null>(null)
  const buy = useMutation({
    mutationFn: (slug: string) => createPaymentOrder(slug, keyRef.current ?? undefined),
    onSuccess: (result) => {
      if (result.redirect_url) window.location.assign(result.redirect_url)
    },
    onSettled: () => {
      keyRef.current = null
    },
  })

  if (packages.isPending) {
    return (
      <div
        className={`grid gap-2 ${compact ? 'grid-cols-1' : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5'}`}
      >
        {Array.from({ length: compact ? 3 : 5 }, (_, i) => (
          <Skeleton key={i} className={compact ? 'h-14 rounded-xl' : 'h-44 rounded-2xl'} />
        ))}
      </div>
    )
  }
  if (packages.isError || !packages.data || packages.data.data.length === 0) return null
  const { data: list, purchasable } = packages.data

  return (
    <section aria-labelledby="chip-packages-title" className="text-left">
      <h3
        id="chip-packages-title"
        className={`font-bold text-fg ${compact ? 'text-base' : 'text-lg'}`}
      >
        {t('payments.title')}
      </h3>
      {purchasable ? null : (
        <p className="mt-1 text-xs text-muted" role="note">
          {t('payments.unavailable')}
        </p>
      )}
      {buy.error ? (
        <div className="mt-2">
          <Alert tone="danger">{message(buy.error.message)}</Alert>
        </div>
      ) : null}
      <ul
        className={`mt-3 grid gap-2 ${compact ? 'grid-cols-1' : 'grid-cols-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5'}`}
      >
        {list.map((pack) => (
          <li key={pack.slug} className={compact ? '' : 'flex'}>
            <PackageCard
              pack={pack}
              compact={compact}
              disabled={!purchasable}
              loading={buy.isPending && buy.variables === pack.slug}
              onBuy={() => {
                if (buy.isPending) return
                keyRef.current = randomId()
                buy.mutate(pack.slug)
              }}
            />
          </li>
        ))}
      </ul>
    </section>
  )
}

function PackageCard({
  pack,
  compact,
  disabled,
  loading,
  onBuy,
}: {
  pack: ChipPackage
  compact: boolean
  disabled: boolean
  loading: boolean
  onBuy: () => void
}) {
  const { t, i18n } = useTranslation()
  const price = new Intl.NumberFormat(i18n.language, {
    style: 'currency',
    currency: pack.currency,
  }).format(pack.price_cents / 100)
  const chips = pack.total_chips.toLocaleString(i18n.language)
  // The seeded packages have names in every language; one an admin adds keeps the name it was given.
  const name = t(`payments.packages.${pack.slug}` as 'payments.packages.large', {
    defaultValue: pack.name,
  })
  const bonus = pack.bonus_chips > 0 ? pack.bonus_chips.toLocaleString(i18n.language) : null
  const highlight = pack.is_recommended
    ? 'border-gold/70 bg-gold/10 shadow-[0_0_0_1px_rgb(245_192_79/0.35),0_12px_30px_-12px_rgb(245_192_79/0.45)]'
    : 'border-white/10 bg-black/25'

  if (compact) {
    return (
      <div className={`flex items-center gap-3 rounded-xl border px-3 py-2 ${highlight}`}>
        <FishkaIcon className="size-7 shrink-0" />
        <span className="min-w-0 flex-1 leading-tight">
          <span className="block font-extrabold text-gold-light tabular-nums">{chips}</span>
          <span className="block truncate text-xs text-muted">
            {pack.is_recommended ? `${t('payments.recommended')} · ` : ''}
            {bonus ? t('payments.bonus', { amount: bonus }) : name}
          </span>
        </span>
        <Button
          size="sm"
          variant={pack.is_recommended ? 'gold' : 'secondary'}
          disabled={disabled}
          loading={loading}
          onClick={onBuy}
        >
          {price}
        </Button>
      </div>
    )
  }

  return (
    <div
      className={`relative flex w-full flex-col items-center gap-2 rounded-2xl border p-4 pt-5 text-center ${highlight}`}
    >
      {pack.is_recommended ? (
        <span className="absolute -top-2.5 rounded-full bg-gold px-2.5 py-0.5 text-[11px] font-bold tracking-wide text-night-900 uppercase">
          {t('payments.recommended')}
        </span>
      ) : null}
      <span className="text-xs font-semibold tracking-wide text-muted uppercase">{name}</span>
      <FishkaIcon className="size-10" />
      <span className="font-display text-xl font-bold text-gold-light tabular-nums">{chips}</span>
      <span className="min-h-4 text-xs text-accent-text">
        {bonus ? t('payments.bonus', { amount: bonus }) : ''}
      </span>
      <Button
        variant={pack.is_recommended ? 'gold' : 'secondary'}
        className="mt-auto w-full"
        disabled={disabled}
        loading={loading}
        onClick={onBuy}
        aria-label={t('payments.buy', { amount: chips, price })}
      >
        {price}
      </Button>
    </div>
  )
}
