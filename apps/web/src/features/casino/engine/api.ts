import { apiRequest } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/api/types'
import type { Slot } from '../slots'

/** An engine slot as the player opens it (ADR 0026): bets, lines, the screen where they left it. */
export type EngineSlot = ApiResponse<'v1.slots.engine.show'>['data']
/** One spin's answer: the new screen, the wins, the money, free games. */
export type EngineSpin = ApiResponse<'v1.slots.engine.spin'>['data']
export type EngineWin = EngineSpin['wins'][number]

export const engineSlotKey = (slug: string) => ['slots', slug, 'engine'] as const
export const recentSlotsKey = ['me', 'slots', 'recent'] as const

export const fetchEngineSlot = (slug: string, signal?: AbortSignal) =>
  apiRequest<ApiResponse<'v1.slots.engine.show'>>(
    `/api/v1/slots/${encodeURIComponent(slug)}/engine`,
    { signal },
  ).then((r) => r.data)

/** One spin: the server and its engine decide the screen and the wins; the key makes a retry safe. */
export const spinEngineSlot = (slug: string, body: { bet: number; lines?: number; key: string }) =>
  apiRequest<ApiResponse<'v1.slots.engine.spin'>>(
    `/api/v1/slots/${encodeURIComponent(slug)}/engine/spin`,
    { method: 'POST', body },
  ).then((r) => r.data)

/** The slots the signed-in player spun last. */
export const fetchRecentSlots = (signal?: AbortSignal) =>
  apiRequest<{ data: Slot[] }>('/api/v1/me/slots/recent', { signal }).then((r) => r.data)

/** The reels of a screen: columns of symbol numbers from the top (0 is an empty cell). */
export function gridOf(screen: Record<string, unknown> | null | undefined): number[][] {
  const grid = screen?.grid
  if (!Array.isArray(grid)) return []
  return grid.map((column) =>
    Array.isArray(column) ? column.map((s) => (typeof s === 'number' ? s : 0)) : [],
  )
}

/** Cells ("reel-row", 1-based) that are part of a win. */
export function winningCells(wins: EngineWin[]): Set<string> {
  const cells = new Set<string>()
  for (const win of wins) for (const [x, y] of win.xy ?? []) cells.add(`${x}-${y}`)
  return cells
}
