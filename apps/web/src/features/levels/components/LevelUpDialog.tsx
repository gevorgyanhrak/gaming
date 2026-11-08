import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { useTranslation } from 'react-i18next'
import { authKeys, useCurrentUser } from '@/features/auth/hooks'
import { useGameInProgress } from '@/features/play/gameInProgress'
import { fetchReactionCatalog, reactionCatalogKey } from '@/features/reactions/api'
import { walletKey } from '@/features/wallet/api'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { flyFishkas } from '@/features/wallet/flyFishkas'
import { Button } from '@/shared/ui/Button'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import { claimLevelUps, levelKey, type LevelStatus } from '../api'
import { useLevel } from '../hooks'
import { itemLabel } from '../items'
import { closeLevelUp, offerLevelUp, useLevelUpOpen } from '../popup'

/**
 * The level-up popup (TASK-047): previous level → new level with a burst of confetti, what the new
 * levels give, and "Collect". It offers itself once per visit while rewards wait (reached while away,
 * too) and again when a level is reached during the visit — never over a game in progress. It goes
 * before the daily bonus, which waits for it. The server decides the levels and pays each reward once.
 */
export function LevelUpDialog() {
  const level = useLevel()
  const { data: user } = useCurrentUser()
  const open = useLevelUpOpen()
  const playing = useGameInProgress()
  const waiting = (level.data?.pending.length ?? 0) > 0
  const settled = level.data !== undefined && !waiting

  useEffect(() => {
    if (waiting && user && !playing) offerLevelUp(user.id)
  }, [waiting, user, playing])

  // Nothing left to collect (collected here or in another tab): stop holding the daily bonus back.
  useEffect(() => {
    if (open && settled) closeLevelUp()
  }, [open, settled])

  return open && waiting && !playing && level.data
    ? createPortal(<Dialog status={level.data} />, document.body)
    : null
}

function Dialog({ status }: { status: LevelStatus }) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const panel = useRef<HTMLDivElement>(null)
  const chips = useRef<HTMLParagraphElement>(null)
  const format = (n: number) => n.toLocaleString(i18n.language)
  // The exclusive emojis each level opens, to show an emoji pack's emojis.
  const catalog = useQuery({
    queryKey: reactionCatalogKey,
    queryFn: ({ signal }) => fetchReactionCatalog(signal),
    staleTime: Infinity,
  })

  // Shown as it was when it opened: collecting empties the list, and the popup steps aside then.
  const [pending] = useState(status.pending)
  const from = (pending[0]?.level ?? status.level) - 1
  const to = pending[pending.length - 1]?.level ?? status.level
  const fishkas = pending.reduce((sum, up) => sum + up.fishkas, 0)
  const milestone = pending.some((up) => up.is_milestone)

  const sent = useRef(false)
  const collect = useMutation({
    mutationFn: claimLevelUps,
    onSuccess: (next) => {
      void queryClient.invalidateQueries({ queryKey: walletKey })
      void queryClient.invalidateQueries({ queryKey: authKeys.me, exact: true })
      if (next.collected > 0) flyFishkas(chips.current, 'in')
      // The empty list closes the popup, so it stays up a moment for the chips to leave it.
      window.setTimeout(() => queryClient.setQueryData(levelKey, next.status), 250)
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
      if (event.key === 'Escape') closeLevelUp()
    }
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
      app?.removeAttribute('inert')
      document.body.style.overflow = overflow
      opener?.focus()
    }
  }, [])

  useEffect(() => confetti(panel.current, milestone ? 90 : 50), [milestone])

  const items = pending.flatMap((up) => up.items.map((item) => ({ item, level: up.level })))

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center p-3 sm:items-center sm:p-4">
      <div
        aria-hidden="true"
        className="absolute inset-0 bg-black/70 backdrop-blur-sm motion-safe:animate-[fade-in_200ms_ease-out]"
        onClick={closeLevelUp}
      />
      <div
        ref={panel}
        role="dialog"
        aria-modal="true"
        aria-labelledby="level-up-title"
        aria-describedby="level-up-body"
        tabIndex={-1}
        className="club-frame relative mb-[env(safe-area-inset-bottom)] w-full max-w-md overflow-hidden rounded-3xl text-center outline-none motion-safe:animate-rise"
      >
        <div
          className={`relative px-5 pt-6 pb-5 ${
            milestone
              ? 'bg-linear-to-b from-[#4a1430] via-[#3a1a3f] to-[#3b2a14]'
              : 'bg-linear-to-b from-[#0d3a2a] via-[#123f2e] to-[#3b2a14]'
          }`}
        >
          {milestone ? (
            <p className="mx-auto mb-2 w-fit rounded-full bg-[#b3261e] px-3 py-0.5 text-xs font-black tracking-widest text-white uppercase">
              {t('level.milestone')}
            </p>
          ) : null}
          <h2
            id="level-up-title"
            className="text-gold-gradient text-3xl font-extrabold tracking-wide uppercase sm:text-4xl"
          >
            {t('level.levelUp')}
          </h2>

          <div className="mt-5 flex items-center justify-center gap-4" aria-hidden="true">
            <LevelBadge level={Math.max(1, from)} size="lg" className="opacity-70" />
            <span className="text-2xl text-gold-light">→</span>
            <LevelBadge
              level={to}
              size="lg"
              className="scale-125 motion-safe:animate-[level-pop_700ms_cubic-bezier(0.2,0.8,0.2,1.4)_both]"
            />
          </div>

          <p id="level-up-body" className="mt-4 text-base font-semibold text-white/90">
            {to - from > 1
              ? t('level.reachedMany', { from: Math.max(1, from), to })
              : t('level.reached', { level: to })}
          </p>
        </div>

        <div className="bg-linear-to-b from-[#3b2a14] to-[#24180c] px-5 pt-4 pb-6">
          <h3 className="text-xs font-semibold tracking-wider text-gold-light/80 uppercase">
            {t('level.rewards')}
          </h3>
          <p
            ref={chips}
            className="mt-2 flex items-center justify-center gap-2 text-4xl font-extrabold text-gold-light tabular-nums drop-shadow-[0_2px_6px_rgb(0_0_0/0.6)]"
          >
            <FishkaIcon className="size-10" />+{format(fishkas)}
            <span className="sr-only">{t('level.fishkas')}</span>
          </p>
          {items.length ? (
            <ul className="mt-3 flex flex-wrap justify-center gap-2">
              {items.map(({ item, level }) => (
                <li
                  key={`${level}-${item.kind}-${item.key}`}
                  className="rounded-full border border-gold/40 bg-black/30 px-3 py-1 text-sm font-semibold"
                >
                  {itemLabel(t, item)}
                  {item.kind === 'emoji_pack' ? (
                    <span className="ml-1.5">
                      {catalog.data?.exclusive
                        .filter((e) => e.level === level)
                        .map((e) => e.emoji)
                        .join('')}
                    </span>
                  ) : null}
                </li>
              ))}
            </ul>
          ) : null}

          {collect.isError ? (
            <p role="alert" className="mt-3 text-sm text-[#f87171]">
              {t('level.failed')}
            </p>
          ) : null}

          <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-center">
            <Button
              variant="gold"
              className="min-h-12 text-lg sm:min-w-48"
              onClick={take}
              disabled={collect.isPending || collect.isSuccess}
            >
              {collect.isPending || collect.isSuccess ? t('level.collecting') : t('level.collect')}
            </Button>
            <Button
              variant="secondary"
              className="min-h-12 sm:order-first sm:min-w-32"
              onClick={closeLevelUp}
            >
              {t('level.later')}
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}

const COLOURS = ['#f7dc8f', '#d6ab4e', '#e8606c', '#5fd4c4', '#b98cf3', '#ffffff']

/** Paper confetti falling over the popup. Purely visual; skipped under reduced motion. */
function confetti(host: HTMLElement | null, count: number): () => void {
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  if (!host || reduced || typeof host.animate !== 'function') return () => {}
  const pieces: HTMLElement[] = []
  for (let i = 0; i < count; i++) {
    const piece = document.createElement('span')
    piece.setAttribute('aria-hidden', 'true')
    const size = 5 + Math.random() * 6
    piece.style.cssText = `position:absolute;top:-12px;left:${Math.random() * 100}%;width:${size}px;height:${size * 1.6}px;background:${COLOURS[i % COLOURS.length]};border-radius:1px;pointer-events:none;z-index:5`
    host.appendChild(piece)
    pieces.push(piece)
    const drift = (Math.random() - 0.5) * 160
    const turn = (Math.random() - 0.5) * 900
    piece
      .animate(
        [
          { transform: 'translate(0, 0) rotate(0deg)', opacity: 1 },
          {
            transform: `translate(${drift}px, ${host.clientHeight + 40}px) rotate(${turn}deg)`,
            opacity: 0.9,
          },
        ],
        {
          duration: 1800 + Math.random() * 1400,
          delay: Math.random() * 500,
          easing: 'cubic-bezier(0.25, 0.4, 0.5, 1)',
          fill: 'forwards',
        },
      )
      .addEventListener('finish', () => piece.remove())
  }
  return () => pieces.forEach((piece) => piece.remove())
}
