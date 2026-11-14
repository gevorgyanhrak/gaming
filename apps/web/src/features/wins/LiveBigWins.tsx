import { useTranslation } from 'react-i18next'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { Avatar } from '@/shared/ui/Avatar'
import { Skeleton } from '@/shared/ui/Skeleton'
import { TrophyIcon } from '@/shared/ui/icons'
import { useWinsFeed } from './useWinsFeed'

/** "5 min ago" in the reader's language. */
function ago(iso: string, lang: string) {
  const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000)
  const rtf = new Intl.RelativeTimeFormat(lang, { numeric: 'auto' })
  const abs = Math.abs(seconds)
  if (abs < 60) return rtf.format(seconds, 'second')
  if (abs < 3600) return rtf.format(Math.round(seconds / 60), 'minute')
  if (abs < 86400) return rtf.format(Math.round(seconds / 3600), 'hour')
  return rtf.format(Math.round(seconds / 86400), 'day')
}

/**
 * BIG WINS LIVE: the biggest wins of the week across every game, biggest first, from the server's ledger (people
 * only — AI never wins Fishkas). A new win slides in with a gold flash. Nothing invented: an empty week says so.
 */
export function LiveBigWins() {
  const { t, i18n } = useTranslation()
  const { wins, fresh } = useWinsFeed()
  const format = (n: number) => n.toLocaleString(i18n.language)

  return (
    <section
      aria-labelledby="big-wins"
      className="relative overflow-hidden rounded-3xl border border-gold/30 bg-[radial-gradient(ellipse_at_50%_0%,rgb(240_196_98/0.14),transparent_60%),linear-gradient(180deg,#120c06,#0a0f0c)] p-4 shadow-[0_18px_40px_rgb(0_0_0/0.5)] sm:p-5"
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
        <ol className="mt-3 space-y-2">
          {(wins.data ?? []).map((win, i) => (
            <li
              key={win.id}
              className={`flex items-center gap-3 rounded-2xl border px-3 py-2 transition ${
                win.id === fresh
                  ? 'border-gold bg-gold/15 shadow-[0_0_24px_rgb(240_196_98/0.35)] motion-safe:animate-rise'
                  : 'border-white/5 bg-black/30'
              }`}
            >
              <span
                aria-hidden="true"
                className={`w-5 text-center text-sm font-extrabold tabular-nums ${i < 3 ? 'text-gold-light' : 'text-muted'}`}
              >
                {i + 1}
              </span>
              <Avatar
                url={win.player.avatar_url}
                name={win.player.name}
                level={win.player.level}
                size="sm"
              />
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-bold">{win.player.name}</span>
                <span className="flex items-center gap-1 text-xs text-muted">
                  <GameIcon
                    slug={win.game.slug}
                    name={win.game.name}
                    className="size-3.5 text-gold"
                  />
                  <span className="truncate">{win.game.name}</span>
                  <span aria-hidden="true">·</span>
                  <time dateTime={win.won_at}>{ago(win.won_at, i18n.language)}</time>
                </span>
              </span>
              <span className="flex items-center gap-1 text-base font-extrabold text-gold-light tabular-nums sm:text-lg">
                +{format(win.amount)}
                <FishkaIcon className="size-5" />
              </span>
              <span className="sr-only">
                {t('wins.line', {
                  name: win.player.name,
                  amount: format(win.amount),
                  game: win.game.name,
                })}
              </span>
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}
