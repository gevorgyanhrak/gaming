import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMessage } from '@/features/auth/forms'
import { useCurrentUser } from '@/features/auth/hooks'
import { useCountdown } from '@/features/play/useCountdown'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { walletKey } from '@/features/wallet/api'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Skeleton } from '@/shared/ui/Skeleton'
import { claimGift, fetchGifts, giftsKey, spinWheel, type Gifts } from './api'

/** hh:mm:ss for a number of seconds. */
const clock = (s: number) =>
  [Math.floor(s / 3600), Math.floor((s % 3600) / 60), s % 60]
    .map((n) => String(n).padStart(2, '0'))
    .join(':')

/**
 * Free Fishkas besides the daily bonus: a gift every few hours (with the time to the next one) and the
 * daily lucky wheel. Gifts, never purchases: the server decides and credits them.
 */
export function FreeGifts({ className = '' }: { className?: string }) {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const queryClient = useQueryClient()
  const message = useMessage()
  const [wheelOpen, setWheelOpen] = useState(false)
  const gifts = useQuery({
    queryKey: giftsKey,
    queryFn: ({ signal }) => fetchGifts(signal),
    enabled: !!user,
  })
  const giftLeft = useCountdown(gifts.data?.gift.available ? null : gifts.data?.gift.next_at, null)
  const claim = useMutation({
    mutationFn: claimGift,
    onSuccess: (data) => {
      queryClient.setQueryData(giftsKey, data)
      void queryClient.invalidateQueries({ queryKey: walletKey })
    },
  })
  // The countdown ran out: the next gift is there.
  const due = giftLeft === 0 && gifts.data?.gift.available === false
  useEffect(() => {
    if (due) void queryClient.invalidateQueries({ queryKey: giftsKey })
  }, [due, queryClient])

  if (!user) return null
  if (gifts.isLoading) return <Skeleton className={`h-36 rounded-panel ${className}`} />
  if (!gifts.data) return null
  const { gift, wheel } = gifts.data

  return (
    <section className={`rounded-panel p-5 sm:p-6 ${className}`} aria-label={t('gifts.heading')}>
      <h2 className="mb-4 text-xl font-bold">{t('gifts.heading')}</h2>
      <div className="grid gap-3 sm:grid-cols-2">
        <div className="flex items-center gap-3 rounded-2xl border border-gold/25 bg-black/25 p-3">
          <span className="text-4xl" aria-hidden="true">
            🎁
          </span>
          <div className="min-w-0 flex-1">
            <p className="font-semibold">{t('gifts.next')}</p>
            <p className="text-sm text-muted tabular-nums" role="timer">
              {gift.available ? t('gifts.ready') : clock(giftLeft ?? 0)}
            </p>
          </div>
          <span className="flex items-center gap-1 text-xl font-extrabold text-gold-light tabular-nums">
            {gift.amount}
            <FishkaIcon className="size-5" />
          </span>
        </div>
        <div className="flex items-center gap-3 rounded-2xl border border-gold/25 bg-black/25 p-3">
          <WheelIcon className="size-11 shrink-0" />
          <div className="min-w-0 flex-1">
            <p className="font-semibold">{t('gifts.wheel')}</p>
            <p className="text-sm text-muted">
              {wheel.available ? t('gifts.wheelReady') : t('gifts.wheelTomorrow')}
            </p>
          </div>
          <Button
            variant="secondary"
            disabled={!wheel.available}
            onClick={() => setWheelOpen(true)}
          >
            {t('gifts.spin')}
          </Button>
        </div>
      </div>
      {gift.available ? (
        <Button
          className="mt-4 w-full sm:w-auto"
          onClick={() => claim.mutate()}
          loading={claim.isPending}
        >
          {t('gifts.collect', { amount: gift.amount })}
        </Button>
      ) : null}
      {claim.error ? (
        <div className="mt-3">
          <Alert tone="danger">{message(claim.error.message)}</Alert>
        </div>
      ) : null}
      {wheelOpen ? <LuckyWheel prizes={wheel.prizes} onClose={() => setWheelOpen(false)} /> : null}
    </section>
  )
}

/** The wheel: spins to the slice the server chose, then shows the prize. */
function LuckyWheel({ prizes, onClose }: { prizes: number[]; onClose: () => void }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const message = useMessage()
  const [angle, setAngle] = useState(0)
  const [won, setWon] = useState<number | null>(null)
  const slice = 360 / prizes.length
  const spin = useMutation({
    mutationFn: spinWheel,
    onSuccess: (data) => {
      // Five turns, then stop with the chosen slice under the pointer at the top.
      setAngle(360 * 5 + (360 - (data.index * slice + slice / 2)))
      window.setTimeout(
        () => {
          setWon(data.amount)
          queryClient.setQueryData<Gifts>(giftsKey, { gift: data.gift, wheel: data.wheel })
          void queryClient.invalidateQueries({ queryKey: walletKey })
        },
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 0 : 4200,
      )
    },
  })
  const colors = ['#7a1f2b', '#1f5a3a', '#8a6a1c', '#2b3f7a', '#6a2b7a', '#1f5a6a']

  return (
    <Modal onClose={onClose} label={t('gifts.wheel')} size="md">
      <div className="space-y-5 p-5 text-center">
        <h2 className="text-gold-gradient text-2xl font-extrabold">{t('gifts.wheel')}</h2>
        <div className="relative mx-auto size-64">
          <span
            className="absolute -top-1 left-1/2 z-10 -translate-x-1/2 text-3xl text-gold-light drop-shadow"
            aria-hidden="true"
          >
            ▼
          </span>
          <svg
            viewBox="-100 -100 200 200"
            className="size-full rounded-full shadow-[0_0_0_6px_#c9a04a,0_12px_30px_rgb(0_0_0/0.5)] transition-transform duration-[4000ms] ease-[cubic-bezier(0.12,0.75,0.2,1)] motion-reduce:transition-none"
            style={{ transform: `rotate(${angle}deg)` }}
            aria-hidden="true"
          >
            {prizes.map((amount, i) => {
              const a0 = ((i * slice - 90) * Math.PI) / 180
              const a1 = (((i + 1) * slice - 90) * Math.PI) / 180
              const mid = ((i + 0.5) * slice - 90) * (Math.PI / 180)
              return (
                <g key={i}>
                  <path
                    d={`M0 0 L${100 * Math.cos(a0)} ${100 * Math.sin(a0)} A100 100 0 0 1 ${100 * Math.cos(a1)} ${100 * Math.sin(a1)} Z`}
                    fill={colors[i % colors.length]}
                    stroke="#e9c46a"
                    strokeWidth="1"
                  />
                  <text
                    x={64 * Math.cos(mid)}
                    y={64 * Math.sin(mid)}
                    fill="#fff4d6"
                    fontSize="13"
                    fontWeight="800"
                    textAnchor="middle"
                    dominantBaseline="middle"
                    transform={`rotate(${(i + 0.5) * slice} ${64 * Math.cos(mid)} ${64 * Math.sin(mid)})`}
                  >
                    {amount}
                  </text>
                </g>
              )
            })}
            <circle r="16" fill="#c9a04a" stroke="#7a5a1c" strokeWidth="3" />
          </svg>
        </div>
        <p className="min-h-7 text-lg font-bold text-gold-light" role="status">
          {won !== null ? t('gifts.won', { amount: won }) : null}
        </p>
        {spin.error ? <Alert tone="danger">{message(spin.error.message)}</Alert> : null}
        {won === null ? (
          <Button onClick={() => spin.mutate()} loading={spin.isPending} disabled={angle !== 0}>
            {t('gifts.spin')}
          </Button>
        ) : (
          <Button onClick={onClose}>{t('gifts.done')}</Button>
        )}
      </div>
    </Modal>
  )
}

function WheelIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="-12 -12 24 24" className={className} aria-hidden="true">
      {[0, 1, 2, 3, 4, 5].map((i) => (
        <path
          key={i}
          d={`M0 0 L${11 * Math.cos((i * Math.PI) / 3)} ${11 * Math.sin((i * Math.PI) / 3)} A11 11 0 0 1 ${11 * Math.cos(((i + 1) * Math.PI) / 3)} ${11 * Math.sin(((i + 1) * Math.PI) / 3)} Z`}
          fill={['#7a1f2b', '#1f5a3a', '#8a6a1c'][i % 3]}
        />
      ))}
      <circle r="3" fill="#c9a04a" />
    </svg>
  )
}
