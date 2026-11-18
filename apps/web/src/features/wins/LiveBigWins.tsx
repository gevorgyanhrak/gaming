import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { AiTag } from '@/shared/ui/AiTag'
import { Avatar } from '@/shared/ui/Avatar'
import { Skeleton } from '@/shared/ui/Skeleton'
import { TrophyIcon } from '@/shared/ui/icons'
import type { Win } from './api'
import { useWinsFeed } from './useWinsFeed'
import { formatAgo } from '@/shared/lib/dates'

/** A new win steps into the spotlight about this often (owner: one about every 5 seconds). */
const SPOTLIGHT_MS = 5000

/**
 * BIG WINS LIVE: the latest wins of the week across every game, newest first, from the server (people's from the
 * ledger; AI players' from real games, marked AI). A spotlight steps through them, one about every 5 seconds
 * (paused on hover, focus or with its button); a win that just happened takes it at once with a gold flash.
 * Nothing invented: an empty week says so.
 */
export function LiveBigWins() {
  const { t, i18n } = useTranslation()
  const { wins, fresh } = useWinsFeed()
  const format = (n: number) => n.toLocaleString(i18n.language)
  const list = wins.data ?? []
  const [step, setStep] = useState(0)
  const [paused, setPaused] = useState(false)
  const [held, setHeld] = useState(false)
  // A win that just arrived is first in the list: show it now.
  const [seenFresh, setSeenFresh] = useState(fresh)
  if (fresh !== seenFresh) {
    setSeenFresh(fresh)
    setStep(0)
  }

  useEffect(() => {
    if (paused || held || list.length < 2) return
    const timer = window.setInterval(() => setStep((s) => s + 1), SPOTLIGHT_MS)
    return () => window.clearInterval(timer)
  }, [paused, held, list.length])

  const current = list.length > 0 ? list[step % list.length] : undefined

  return (
    <section
      aria-labelledby="big-wins"
      className="club-panel relative overflow-hidden rounded-[1.6rem] p-4 sm:p-6"
    >
      <h2
        id="big-wins"
        className="flex items-center gap-2 font-display text-xl font-bold tracking-wide sm:text-2xl"
      >
        <span aria-hidden="true" className="relative flex size-2.5">
          <span className="absolute inline-flex size-full rounded-full bg-[#ff5a4e] opacity-75 motion-safe:animate-ping" />
          <span className="relative inline-flex size-2.5 rounded-full bg-[#ff5a4e]" />
        </span>
        <span className="casino-gold">{t('wins.title')}</span>
      </h2>
      <p className="mt-0.5 text-xs text-muted">{t('wins.subtitle')}</p>

      {wins.isPending ? (
        <div className="mt-3 space-y-2">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-14 rounded-2xl" />
          ))}
        </div>
      ) : wins.isError ? (
        <p className="mt-3 text-sm text-muted">{t('wins.error')}</p>
      ) : (wins.data ?? []).length === 0 ? (
        <p className="mt-4 flex items-center gap-2 rounded-2xl bg-black/30 px-4 py-3 text-sm text-fg/80">
          <TrophyIcon className="size-5 text-gold" aria-hidden="true" />
          {t('wins.empty')}
        </p>
      ) : (
        <>
          {current ? (
            <div
              className="relative mt-3"
              onMouseEnter={() => setHeld(true)}
              onMouseLeave={() => setHeld(false)}
              onFocus={() => setHeld(true)}
              onBlur={() => setHeld(false)}
            >
              <Spotlight
                key={`${current.id}-${step}`}
                win={current}
                label={t('wins.spotlight')}
                amount={format(current.amount)}
                when={formatAgo(current.won_at, i18n.language)}
              />
              {list.length > 1 ? (
                <button
                  type="button"
                  onClick={() => setPaused(!paused)}
                  aria-label={paused ? t('wins.play') : t('wins.pause')}
                  title={paused ? t('wins.play') : t('wins.pause')}
                  className="absolute top-2 right-2 flex size-8 items-center justify-center rounded-full border border-gold/30 bg-black/40 text-xs text-gold-light hover:border-gold"
                >
                  <span aria-hidden="true">{paused ? '▶' : '❚❚'}</span>
                </button>
              ) : null}
            </div>
          ) : null}
          <ol className="mt-3 max-h-[22rem] space-y-2 overflow-y-auto overscroll-contain pr-1">
            {list.map((win) => (
              <li
                key={win.id}
                className={`flex items-center gap-3 rounded-2xl border px-3 py-2 transition ${
                  win.id === fresh
                    ? 'border-gold bg-gold/15 shadow-[0_0_24px_rgb(240_196_98/0.35)] motion-safe:animate-rise'
                    : 'border-white/5 bg-black/30'
                }`}
              >
                <Avatar
                  url={win.player.avatar_url}
                  ai={win.is_ai}
                  name={win.player.name}
                  level={win.player.level}
                  size="sm"
                />
                {/* Name and amount on one line, the game and time under both: the full width for each. */}
                <span className="min-w-0 flex-1">
                  <span className="flex min-w-0 items-center gap-2">
                    <span className="flex min-w-0 flex-1 items-center gap-1.5">
                      <span className="truncate text-sm font-bold">{win.player.name}</span>
                      {win.is_ai ? <AiTag /> : null}
                    </span>
                    <span className="flex shrink-0 items-center gap-1 text-base font-extrabold text-gold-light tabular-nums sm:text-lg">
                      +{format(win.amount)}
                      <FishkaIcon className="size-5" />
                    </span>
                  </span>
                  <span className="flex min-w-0 items-center gap-1 text-xs text-muted">
                    <GameIcon
                      slug={win.game.slug}
                      name={win.game.name}
                      className="size-3.5 shrink-0 text-gold"
                    />
                    <span className="truncate">{win.game.name}</span>
                    <span aria-hidden="true">·</span>
                    <time dateTime={win.won_at} className="shrink-0 whitespace-nowrap">
                      {formatAgo(win.won_at, i18n.language)}
                    </time>
                  </span>
                </span>
                <span className="sr-only">
                  {t(win.is_ai ? 'wins.lineAi' : 'wins.line', {
                    name: win.player.name,
                    amount: format(win.amount),
                    game: win.game.name,
                  })}
                </span>
              </li>
            ))}
          </ol>
        </>
      )}
    </section>
  )
}

/** The win in the spotlight: large, rising in with a gold sweep. */
function Spotlight({
  win,
  label,
  amount,
  when,
}: {
  win: Win
  label: string
  amount: string
  when: string
}) {
  return (
    <div
      className={`relative overflow-hidden rounded-2xl border p-3 motion-safe:animate-rise ${
        win.is_ai
          ? 'border-sky-300/30 bg-linear-to-r from-sky-950/60 via-black/40 to-black/30'
          : 'border-gold/50 bg-linear-to-r from-gold/20 via-black/40 to-black/30 shadow-[0_0_28px_rgb(240_196_98/0.25)]'
      }`}
    >
      <span
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 -translate-x-full bg-linear-to-r from-transparent via-white/15 to-transparent motion-safe:animate-[spot-sweep_1.4s_ease-out_both]"
      />
      {/* Room on the right for the pause button, which sits level with this line. */}
      <p className="pr-12 text-[0.65rem] font-bold tracking-widest text-gold-light/80 uppercase">
        {label}
      </p>
      <div className="mt-1 flex items-center gap-3">
        <Avatar
          url={win.player.avatar_url}
          name={win.player.name}
          level={win.player.level}
          ai={win.is_ai}
        />
        <div className="min-w-0 flex-1">
          {/* The amount moves under the name when both can't fit (a phone, beside the pause button). */}
          <p className="flex min-w-0 flex-wrap items-center gap-x-2">
            <span className="flex min-w-24 flex-1 items-center gap-1.5">
              <span className="truncate font-bold">{win.player.name}</span>
              {win.is_ai ? <AiTag /> : null}
            </span>
            <span className="flex shrink-0 items-center gap-1 text-xl font-black text-gold-light tabular-nums sm:text-2xl">
              +{amount}
              <FishkaIcon className="size-6" />
            </span>
          </p>
          <p className="flex min-w-0 items-center gap-1 text-xs text-muted">
            <GameIcon
              slug={win.game.slug}
              name={win.game.name}
              className="size-3.5 shrink-0 text-gold"
            />
            <span className="truncate">{win.game.name}</span>
            <span aria-hidden="true">·</span>
            <time dateTime={win.won_at} className="shrink-0 whitespace-nowrap">
              {when}
            </time>
          </p>
        </div>
      </div>
    </div>
  )
}
