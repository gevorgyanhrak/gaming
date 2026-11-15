import { apiRequest } from '@/shared/api/http'
import type { ApiResponse, Schemas } from '@/shared/api/types'

export type RankingRow = Schemas['RankingRowResource']
export type GameRanking = ApiResponse<'v1.rankings.game'>
export type PlatformBoard = 'level' | 'chips'

export const gameRankingKey = (slug: string) => ['rankings', 'game', slug] as const
export const platformRankingKey = (board: PlatformBoard) => ['rankings', board] as const

export const fetchGameRanking = (slug: string, signal?: AbortSignal) =>
  apiRequest<GameRanking>(`/api/v1/rankings/games/${encodeURIComponent(slug)}`, { signal })

export const fetchPlatformRanking = (board: PlatformBoard, signal?: AbortSignal) =>
  apiRequest<ApiResponse<'v1.rankings.platform'>>(`/api/v1/rankings/${board}`, { signal }).then(
    (r) => r.data,
  )

/** Boards are kept 30 s on the server; refreshed when the page comes back into view and every minute. */
export const RANKING_STALE_MS = 30_000
export const RANKING_REFRESH_MS = 60_000
