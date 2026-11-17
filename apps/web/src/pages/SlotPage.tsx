import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { EngineSlotGame } from '@/features/casino/engine/EngineSlotGame'
import { fetchSlots, isSlotMessage, slotsKey } from '@/features/casino/slots'
import { walletKey } from '@/features/wallet/api'
import { openOutOfFishkas } from '@/features/wallet/outOfFishkas'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'

/**
 * A slot. An engine slot (ADR 0026) is drawn by our EngineSlotGame. A house slot (ADR 0024): the game's own page
 * (/play/<id>/, same origin) in a frame. The server deals every
 * round; the frame tells this page after each spin (the wallet refetches) and when the Fishkas run short (the
 * app's "out of Fishkas" popup opens).
 */
export function SlotPage() {
  const { slug = '' } = useParams()
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const slots = useQuery({ queryKey: slotsKey, queryFn: ({ signal }) => fetchSlots(signal) })
  const slot = slots.data?.find((s) => s.slug === slug)

  useEffect(() => {
    const onMessage = (event: MessageEvent) => {
      if (event.origin !== window.location.origin || !isSlotMessage(event.data)) return
      if (event.data.type === 'slot:low-balance') openOutOfFishkas()
      else void queryClient.invalidateQueries({ queryKey: walletKey })
    }
    window.addEventListener('message', onMessage)
    return () => window.removeEventListener('message', onMessage)
  }, [queryClient])

  return (
    <Container className="py-4">
      {slot ? <title>{slot.name}</title> : null}
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <Link
          to={slot?.kind === 'engine' ? '/casino' : '/'}
          className="inline-flex min-h-11 items-center text-sm font-semibold text-accent-text hover:underline"
        >
          ← {t('slots.back')}
        </Link>
        <p className="text-xs text-muted">{t('slots.playMoney')}</p>
      </div>
      {slots.isPending ? (
        <Skeleton className="h-[70dvh] w-full rounded-panel" />
      ) : slots.isError ? (
        <QueryErrorState error={slots.error} onRetry={() => void slots.refetch()} />
      ) : slot?.kind === 'engine' ? (
        // An engine slot (ADR 0026): our own screen for it, the server's engine deals.
        <EngineSlotGame slug={slot.slug} />
      ) : !slot?.play_path ? (
        <EmptyState title={t('slots.unavailable')} />
      ) : (
        <iframe
          src={slot.play_path}
          title={slot.name}
          className="block h-[calc(100dvh-9rem)] min-h-[28rem] w-full rounded-panel border border-gold/30 bg-black shadow-[0_18px_40px_rgb(0_0_0/0.55)]"
          allow="autoplay; fullscreen"
        />
      )}
      {slot && slot.kind !== 'engine' ? (
        <p className="mt-2 text-center text-[11px] text-muted">
          {t('slots.credit', { provider: slot.provider })}
        </p>
      ) : null}
    </Container>
  )
}
