import { useLayoutEffect, useRef, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { PlayerProfilePopup } from '@/features/players/PlayerProfilePopup'
import { AiTag } from '@/shared/ui/AiTag'
import { Avatar } from '@/shared/ui/Avatar'
import { Skeleton } from '@/shared/ui/Skeleton'
import type { RankingRow } from './api'

const MEDALS = [
  'linear-gradient(135deg,#fff3c4,#e2b84f 55%,#a87a1e)',
  'linear-gradient(135deg,#ffffff,#c9ced6 55%,#8a919c)',
  'linear-gradient(135deg,#ffd9b0,#c9803e 55%,#7d4a1d)',
]

const rowKey = (row: RankingRow) => (row.is_ai ? `ai:${row.name}` : `u:${row.username}`)

/**
 * A Top 10: place (the top three with gold, silver and bronze medals), avatar (tap → the player's card), name (AI
 * marked), and the value ranked by. When places change, rows glide to their new place (off under reduced motion).
 */
export function RankingList({
  rows,
  loading,
  error,
  empty,
  value,
  label,
}: {
  rows: RankingRow[] | undefined
  loading: boolean
  error: boolean
  empty: string
  /** How a row's value shows: points, Fishkas, level. */
  value: (row: RankingRow) => ReactNode
  label: string
}) {
  const { t } = useTranslation()
  const [open, setOpen] = useState<RankingRow | null>(null)
  const listRef = useRef<HTMLOListElement>(null)
  const places = useRef(new Map<string, number>())

  // FLIP: a row that moved starts where it was and glides to where it is now.
  useLayoutEffect(() => {
    const list = listRef.current
    if (!list) return
    const still = matchMedia?.('(prefers-reduced-motion: reduce)').matches !== false
    const next = new Map<string, number>()
    for (const item of list.querySelectorAll<HTMLElement>('[data-rank-key]')) {
      const key = item.dataset.rankKey ?? ''
      const top = item.offsetTop
      next.set(key, top)
      const before = places.current.get(key)
      if (!still && typeof item.animate === 'function') {
        if (before === undefined && places.current.size > 0) {
          item.animate(
            [
              { opacity: 0, transform: 'scale(0.96)' },
              { opacity: 1, transform: 'none' },
            ],
            {
              duration: 320,
              easing: 'ease-out',
            },
          )
        } else if (before !== undefined && before !== top) {
          item.animate(
            [{ transform: `translateY(${before - top}px)` }, { transform: 'translateY(0)' }],
            { duration: 450, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' },
          )
        }
      }
    }
    places.current = next
  }, [rows])

  if (loading) {
    return (
      <div className="space-y-2" data-testid="ranking-loading">
        {[0, 1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-12 rounded-xl" />
        ))}
      </div>
    )
  }
  if (error) return <p className="text-sm text-muted">{t('rankings.error')}</p>
  if (!rows || rows.length === 0) {
    return <p className="rounded-xl bg-black/30 px-4 py-3 text-sm text-fg/80">{empty}</p>
  }

  return (
    <>
      <ol ref={listRef} aria-label={label} className="@container space-y-1.5">
        {rows.map((row) => (
          <li
            key={rowKey(row)}
            data-rank-key={rowKey(row)}
            className={`flex items-center gap-3 rounded-xl border px-3 py-2 ${
              row.rank <= 3
                ? 'border-gold/35 bg-gold/[0.08] shadow-[inset_0_0_18px_rgb(226_184_79/0.08)]'
                : 'border-white/5 bg-black/30'
            }`}
          >
            <span
              className={`flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-extrabold tabular-nums ${row.rank <= 3 ? 'shadow-[0_2px_6px_rgb(0_0_0/0.5)]' : 'text-muted'}`}
              style={
                row.rank <= 3 ? { background: MEDALS[row.rank - 1], color: '#2a1b0c' } : undefined
              }
            >
              <span className="sr-only">{t('rankings.place')} </span>
              {row.rank <= 3 ? (
                <span aria-hidden="true" className="relative">
                  {row.rank === 1 ? (
                    <span className="absolute -top-3 left-1/2 -translate-x-1/2 text-[0.6rem]">
                      👑
                    </span>
                  ) : null}
                  {row.rank}
                </span>
              ) : (
                row.rank
              )}
            </span>
            <button
              type="button"
              onClick={() => setOpen(row)}
              aria-label={t('rankings.openCard', { name: row.name })}
              className="shrink-0 rounded-full focus-visible:outline-2 focus-visible:outline-gold"
            >
              <Avatar
                url={row.avatar_url}
                name={row.name}
                level={row.level}
                frame={row.frame}
                ai={row.is_ai}
                size="sm"
              />
            </button>
            {/* In a narrow list (a phone, a side column) the score goes under the name, so the name keeps room. */}
            <span className="flex min-w-0 flex-1 flex-col @xs:flex-row @xs:items-center @xs:gap-3">
              <span className="flex min-w-0 flex-1 items-center gap-1.5">
                <span className="truncate text-sm font-bold">{row.name}</span>
                {row.is_ai ? <AiTag /> : null}
              </span>
              <span className="flex shrink-0 items-center gap-1 text-sm font-extrabold text-gold-light tabular-nums @xs:text-base">
                {value(row)}
              </span>
            </span>
          </li>
        ))}
      </ol>
      {open ? (
        <PlayerProfilePopup
          player={{
            name: open.name,
            username: open.username,
            is_bot: open.is_ai,
            level: open.level,
            avatar_url: open.avatar_url,
          }}
          onClose={() => setOpen(null)}
        />
      ) : null}
    </>
  )
}
