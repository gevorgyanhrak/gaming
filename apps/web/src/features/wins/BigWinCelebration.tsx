import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type CSSProperties } from 'react'
import { useTranslation } from 'react-i18next'
import { useLocation } from 'react-router'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { playUiSound } from '@/shared/lib/sounds'
import { usePublicChannel } from '@/shared/realtime/usePublicChannel'
import { Avatar } from '@/shared/ui/Avatar'
import { CloseIcon } from '@/shared/ui/icons'
import { freshWinKey, winsKey, withWin, type Win } from './api'

const SPARKS = Array.from({ length: 18 }, (_, i) => ({
  angle: (i * 360) / 18,
  distance: 70 + (i % 3) * 22,
}))

/**
 * A big win (the server marks it `celebrate`: at or above the admin's amount, 100,000 by default) celebrated on
 * every open page as it happens: the winner's avatar and name, the amount and the game, gold sparks. A card at the
 * top that goes by itself after 7 s (or when closed) — never over a game in progress, never blocking anything.
 * It is also the app's one listener on the "wins" channel (two would stop each other's listening).
 */
export function BigWinCelebration() {
  const { t, i18n } = useTranslation()
  const { pathname } = useLocation()
  const queryClient = useQueryClient()
  const [win, setWin] = useState<Win | null>(null)
  const atTable = pathname.startsWith('/rooms/')

  // The app's one listener on "wins": the feed's list, its newest entry, and the celebration.
  usePublicChannel('wins', {
    'win.recorded': (next: Win) => {
      queryClient.setQueryData<Win[]>(winsKey, (old) => withWin(old, next))
      queryClient.setQueryData(freshWinKey, next.id)
      if (next.celebrate && !window.location.pathname.startsWith('/rooms/')) {
        setWin(next)
        playUiSound('fanfare')
      }
    },
  })

  useEffect(() => {
    if (!win) return
    const timer = window.setTimeout(() => setWin(null), 7000)
    return () => window.clearTimeout(timer)
  }, [win])

  if (!win || atTable) return null
  const amount = win.amount.toLocaleString(i18n.language)

  return (
    <div className="pointer-events-none fixed inset-x-0 top-3 z-50 flex justify-center px-3">
      <div
        role="status"
        aria-live="polite"
        className="pointer-events-auto relative flex w-full max-w-md items-center gap-4 overflow-visible rounded-3xl border border-gold bg-[radial-gradient(ellipse_at_30%_0%,rgb(240_196_98/0.3),transparent_60%),linear-gradient(180deg,#2a1c0b,#120c06)] px-4 py-3 shadow-[0_20px_60px_rgb(0_0_0/0.6),0_0_40px_rgb(240_196_98/0.35)] motion-safe:animate-rise"
      >
        <span aria-hidden="true" className="pointer-events-none absolute top-1/2 left-12">
          {SPARKS.map((s, i) => (
            <span
              key={i}
              className="absolute size-1.5 rounded-full bg-[#ffd877] opacity-0 shadow-[0_0_8px_#ffd877] motion-safe:animate-[spark_1200ms_ease-out_both]"
              style={
                {
                  '--sx': `${Math.cos((s.angle * Math.PI) / 180) * s.distance}px`,
                  '--sy': `${Math.sin((s.angle * Math.PI) / 180) * s.distance}px`,
                  animationDelay: `${(i % 4) * 60}ms`,
                } as CSSProperties
              }
            />
          ))}
        </span>
        <span className="relative rounded-full shadow-[0_0_20px_rgb(240_196_98/0.6)] ring-2 ring-gold">
          <Avatar
            url={win.player.avatar_url}
            name={win.player.name}
            level={win.player.level}
            size="md"
          />
        </span>
        <span className="min-w-0 flex-1">
          <span className="casino-gold block font-display text-lg font-bold tracking-widest uppercase">
            {t('wins.congrats')}
          </span>
          <span className="block truncate text-base font-extrabold uppercase">
            {win.player.name}
          </span>
          <span className="flex items-center gap-1 text-sm text-fg/90">
            {t('wins.wonIn', { amount, game: win.game.name })}
            <FishkaIcon className="size-4" />
          </span>
        </span>
        <button
          type="button"
          onClick={() => setWin(null)}
          aria-label={t('wins.close')}
          className="flex size-9 shrink-0 items-center justify-center rounded-full border border-white/20 text-white/70 hover:text-white"
        >
          <CloseIcon className="size-4" />
        </button>
      </div>
    </div>
  )
}
