import { apiRequest } from '@/shared/api/http'
import type { Schemas } from '@/shared/api/types'

export type LeaderboardRow = Schemas['LeaderboardRowResource']
export type Period = 'day' | 'week' | 'month'

export const leaderboardKey = (period: Period, game: string | null) =>
  ['leaderboards', period, game ?? 'all'] as const

export const fetchLeaderboard = (period: Period, game: string | null, signal?: AbortSignal) =>
  apiRequest<{ data: LeaderboardRow[] }>(
    `/api/v1/leaderboards?period=${period}${game ? `&game=${encodeURIComponent(game)}` : ''}`,
    { signal },
  ).then((r) => r.data)
