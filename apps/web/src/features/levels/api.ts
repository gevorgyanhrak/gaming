import { apiRequest } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/api/types'

/** The player's level and progress, and the level-ups whose rewards wait to be collected (ADR 0018). */
export type LevelStatus = ApiResponse<'v1.me.level.show'>['data']
export type LevelClaimed = ApiResponse<'v1.me.level-ups.claim'>['data']
export type LevelUp = LevelStatus['pending'][number]
export type LevelItem = LevelUp['items'][number]

export const levelKey = ['me', 'level'] as const

export const fetchLevel = (signal?: AbortSignal) =>
  apiRequest<ApiResponse<'v1.me.level.show'>>('/api/v1/me/level', { signal }).then((r) => r.data)

/** Collect every pending level-up's reward; the server pays each once (`collected` 0 for a repeat). */
export const claimLevelUps = () =>
  apiRequest<ApiResponse<'v1.me.level-ups.claim'>>('/api/v1/me/level-ups/claim', {
    method: 'POST',
  }).then((r) => r.data)
