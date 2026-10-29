import type { TFunction } from 'i18next'
import type { GameStat } from './api'

/** Wins out of every game played (timeouts included), as a percentage; a dash before the first game. */
export const winRate = (row: GameStat, locale: string) =>
  row.played > 0
    ? new Intl.NumberFormat(locale, { style: 'percent' }).format(row.wins / row.played)
    : '—'

/** The counts that add up to "played", in display order. */
export const recordCounts = (row: GameStat, t: TFunction) => [
  { key: 'played', label: t('dashboard.played'), value: row.played },
  { key: 'wins', label: t('dashboard.wins'), value: row.wins },
  { key: 'losses', label: t('dashboard.losses'), value: row.losses },
  { key: 'draws', label: t('dashboard.draws'), value: row.draws },
  { key: 'timedOut', label: t('dashboard.timedOut'), value: row.abandoned },
]

/** A win streak of two or more is worth a little colour. */
export const streakTone = (row: GameStat) =>
  row.current_streak >= 2 ? 'text-accent-text' : 'text-muted'
