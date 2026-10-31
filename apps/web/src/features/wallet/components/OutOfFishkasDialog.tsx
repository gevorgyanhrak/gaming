import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef } from 'react'
import { createPortal } from 'react-dom'
import { useTranslation } from 'react-i18next'
import { useMessage } from '@/features/auth/forms'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import { refillFishkas, walletKey } from '../api'
import { useWallet } from '../hooks'
import { closeOutOfFishkas, useOutOfFishkasOpen } from '../outOfFishkas'
import { FishkaIcon } from './FishkaIcon'

/**
 * The "out of Fishkas" popup: opens when a game can't start for lack of Fishkas. Rendered into
 * <body>, so it covers the screen whatever it was opened from (the app frame's backdrop blur would
 * otherwise make `fixed` relative to the frame).
 */
export function OutOfFishkasDialog() {
  return useOutOfFishkasOpen() ? createPortal(<Dialog />, document.body) : null
}

function Dialog() {
  const { t, i18n } = useTranslation()
  const message = useMessage()
  const queryClient = useQueryClient()
  const wallet = useWallet()
  const panel = useRef<HTMLDivElement>(null)
  const format = (n: number) => n.toLocaleString(i18n.language)

  // The server decides. One request per click burst: a double click lands twice before the button
  // re-renders as disabled, so a ref drops the second one (it would fail and hide the success).
  const sent = useRef(false)
  const refill = useMutation({
    mutationFn: refillFishkas,
    onSuccess: (next) => queryClient.setQueryData(walletKey, next),
    onError: () => {
      sent.current = false
    },
  })
  const take = () => {
    if (sent.current) return
    sent.current = true
    refill.mutate()
  }

  const { refetch } = wallet
  useEffect(() => {
    void refetch()
  }, [refetch])

  const busy = refill.isPending
  const busyRef = useRef(busy)
  useEffect(() => {
    busyRef.current = busy
  }, [busy])

  // Modal: the app behind is inert (no focus, no clicks) and doesn't scroll; Escape closes unless the
  // bonus is being added; focus returns to where it was.
  useEffect(() => {
    const opener = document.activeElement as HTMLElement | null
    const app = document.getElementById('root')
    const overflow = document.body.style.overflow
    app?.setAttribute('inert', '')
    document.body.style.overflow = 'hidden'
    panel.current?.focus()
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !busyRef.current) closeOutOfFishkas()
    }
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
      app?.removeAttribute('inert')
      document.body.style.overflow = overflow
      opener?.focus()
    }
  }, [])

  const data = wallet.data
  const amount = data?.refill.amount ?? 5000
  const nextAt = data?.refill.available_at
    ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }).format(
        new Date(data.refill.available_at),
      )
    : null
  const canTake = !!data?.refill.available && !busy
  // Known not to be available (taken recently, or still enough to play): say when, offer only "OK".
  const waiting = !!data && !data.refill.available

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
      <div
        aria-hidden="true"
        className="absolute inset-0 bg-black/65 backdrop-blur-sm motion-safe:animate-[fade-in_200ms_ease-out]"
        onClick={() => !busy && closeOutOfFishkas()}
      />
      <div
        ref={panel}
        role="dialog"
        aria-modal="true"
        aria-labelledby="out-of-fishkas-title"
        aria-describedby="out-of-fishkas-body"
        tabIndex={-1}
        className="club-frame relative mb-[env(safe-area-inset-bottom)] w-full max-w-md rounded-3xl p-6 text-center outline-none motion-safe:animate-rise"
      >
        <span className="club-medal mx-auto mb-4 flex size-16 items-center justify-center rounded-full">
          <FishkaIcon className="size-10" />
        </span>

        {refill.isSuccess ? (
          <>
            <h2 id="out-of-fishkas-title" className="text-gold-gradient text-2xl font-extrabold">
              {t('fishka.addedTitle', { count: amount, amount: format(amount) })}
            </h2>
            <p id="out-of-fishkas-body" className="mt-2 text-muted">
              {t('fishka.addedBody', {
                count: refill.data.balance,
                amount: format(refill.data.balance),
              })}
            </p>
            <Button
              variant="gold"
              className="mt-6 min-h-12 w-full text-base"
              onClick={closeOutOfFishkas}
            >
              {t('fishka.continue')}
            </Button>
          </>
        ) : (
          <>
            <h2 id="out-of-fishkas-title" className="text-2xl font-extrabold">
              {t('fishka.outTitle')}
            </h2>
            <p id="out-of-fishkas-body" className="mt-2 text-muted">
              {t('fishka.outBody')}{' '}
              {data && data.balance > 0 && data.balance < data.entry_fee
                ? t('fishka.outLeft', {
                    count: data.balance,
                    amount: format(data.balance),
                    fee: format(data.entry_fee),
                  })
                : data?.balance === 0
                  ? t('fishka.outZero')
                  : null}{' '}
              {waiting ? null : t('fishka.outCta')}
            </p>
            {waiting && nextAt ? (
              <p className="mt-3 text-sm text-accent-text">
                {t('fishka.nextBonus', { time: nextAt })}
              </p>
            ) : null}
            {refill.error ? (
              <div className="mt-4 text-left">
                <Alert tone="danger">{message(refill.error.message)}</Alert>
              </div>
            ) : null}
            <div className="mt-6 flex flex-col gap-2">
              {waiting ? (
                <Button variant="secondary" className="min-h-11 w-full" onClick={closeOutOfFishkas}>
                  {t('fishka.ok')}
                </Button>
              ) : (
                <>
                  <Button
                    variant="gold"
                    className="min-h-12 w-full text-base"
                    disabled={!canTake}
                    aria-busy={busy}
                    onClick={take}
                  >
                    {busy
                      ? t('fishka.adding')
                      : t('fishka.getBonus', { count: amount, amount: format(amount) })}
                  </Button>
                  <Button
                    variant="secondary"
                    className="min-h-11 w-full"
                    disabled={busy}
                    onClick={closeOutOfFishkas}
                  >
                    {t('fishka.cancel')}
                  </Button>
                </>
              )}
            </div>
          </>
        )}
      </div>
    </div>
  )
}
