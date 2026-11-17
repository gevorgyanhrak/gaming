import { useEffect } from 'react'
import { useCurrentUser } from '@/features/auth/hooks'
import { playSound } from '@/shared/lib/sounds'
import { WinBurst } from '../components/WinBurst'
import { clearBanners, DURATION, useBanner, type Banner } from './store'

/**
 * Where table banners appear: over the table bar at the top (it only repeats what the table shows), never over a
 * seat or the board, and never taking a click. One per table page; a screen
 * reader hears each one.
 */
export function TableBanners() {
  const banner = useBanner()
  const { data: user } = useCurrentUser()
  const sound = Boolean(user?.profile.preferences.sound)
  useEffect(() => clearBanners, [])
  // An all-in is heard as chips pushed in; a win already has the game's own sound, joins and leaves stay quiet.
  const id = banner?.id
  const kind = banner?.kind
  useEffect(() => {
    if (sound && kind === 'allIn') playSound('chips', 'cards')
  }, [id, kind, sound])

  return (
    <div
      role="status"
      aria-live="polite"
      className="pointer-events-none fixed inset-x-0 top-1.5 z-40 flex justify-center px-4"
    >
      {banner ? <BannerView key={banner.id} banner={banner} /> : null}
    </div>
  )
}

function BannerView({ banner }: { banner: Banner }) {
  const style = { '--banner-ms': `${DURATION[banner.kind]}ms` } as React.CSSProperties

  if (banner.kind === 'allIn' || banner.kind === 'winner') {
    const allIn = banner.kind === 'allIn'
    return (
      <div
        style={style}
        className={`relative isolate overflow-hidden rounded-2xl border-2 px-7 py-2.5 text-center shadow-[0_18px_40px_rgb(0_0_0/0.55)] motion-safe:animate-[banner-in_var(--banner-ms)_cubic-bezier(0.2,0.9,0.2,1)_both] ${
          allIn
            ? 'border-[#ff9a5a] bg-linear-to-b from-[#b3261e] via-[#7d1712] to-[#4a0c08]'
            : 'border-gold bg-linear-to-b from-[#3d2a08] via-[#1f1504] to-[#120c02]'
        }`}
      >
        {allIn ? null : <WinBurst />}
        {/* A light sweeping across the plate once. */}
        <span
          aria-hidden="true"
          className="absolute inset-y-0 -left-1/3 -z-10 w-1/3 skew-x-[-20deg] bg-white/25 blur-md motion-safe:animate-[banner-sweep_900ms_200ms_ease-out_both] motion-reduce:hidden"
        />
        <p
          className={`font-display text-3xl leading-none font-black tracking-wider uppercase italic drop-shadow-[0_2px_0_rgb(0_0_0/0.6)] sm:text-4xl ${
            allIn ? 'text-[#ffe2c4]' : 'text-gold-gradient'
          }`}
        >
          {banner.title}
        </p>
        {banner.detail ? (
          <p className="mt-1 text-sm font-bold text-white/90 tabular-nums">{banner.detail}</p>
        ) : null}
      </div>
    )
  }

  // Minor: a slim pill (joined, left, a lost hand) — quiet on purpose.
  const dot =
    banner.kind === 'joined'
      ? 'bg-[#4ade80]'
      : banner.kind === 'left'
        ? 'bg-white/40'
        : 'bg-[#f87171]'
  return (
    <div
      style={style}
      className="flex items-center gap-2 rounded-full border border-white/15 bg-black/75 px-4 py-1.5 text-sm font-semibold text-white shadow-lg backdrop-blur-sm motion-safe:animate-[pill-in_var(--banner-ms)_ease-out_both]"
    >
      <span aria-hidden="true" className={`size-2 rounded-full ${dot}`} />
      <span>{banner.title}</span>
      {banner.detail ? <span className="text-white/70 tabular-nums">{banner.detail}</span> : null}
    </div>
  )
}
