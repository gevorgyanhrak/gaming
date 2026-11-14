import { apiRequest } from '@/shared/api/http'
import type { Schemas } from '@/shared/api/types'

export type Win = Schemas['WinResource']

export const winsKey = ['wins'] as const

export const fetchWins = (signal?: AbortSignal) =>
  apiRequest<{ data: Win[] }>('/api/v1/wins', { signal }).then((r) => r.data)

/** The id of the win that just arrived (for its entrance in the feed); set by the one "wins" listener. */
export const freshWinKey = ['wins', 'fresh'] as const

const KEEP = 12

/** A new win into the feed's list: biggest first, each once, the top KEEP. */
export const withWin = (old: Win[] = [], win: Win) =>
  [win, ...old.filter((w) => w.id !== win.id)].sort((a, b) => b.amount - a.amount).slice(0, KEEP)
