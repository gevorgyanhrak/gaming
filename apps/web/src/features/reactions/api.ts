import { isApiError } from '@/shared/api/errors'
import { apiRequest } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/api/types'
import { randomId } from '@/shared/lib/uuid'

/** What can be sent (GET /reactions): emojis by category, predefined message keys by category. */
export type ReactionCatalog = ApiResponse<'v1.reactions'>['data']
/** An emoji or a predefined message from one seat to another (`to_seat`), or to everyone (null). */
export type Reaction = ApiResponse<'v1.rooms.reactions.store'>['data']
export type ReactionKind = 'emoji' | 'message'

export const reactionCatalogKey = ['reactions', 'catalog'] as const

export const fetchReactionCatalog = (signal?: AbortSignal) =>
  apiRequest<ApiResponse<'v1.reactions'>>('/api/v1/reactions', { signal }).then((r) => r.data)

/** The room's reactions after sequence number `after` (all it keeps when null), oldest first. */
export const fetchRoomReactions = (room: string, after: number | null, signal?: AbortSignal) =>
  apiRequest<ApiResponse<'v1.rooms.reactions.index'>>(
    `/api/v1/rooms/${encodeURIComponent(room)}/reactions${after === null ? '' : `?after=${after}`}`,
    { signal },
  ).then((r) => r.data)

/**
 * Sends once per call: a failed attempt (network, server busy) is retried once with the same client id,
 * which the server delivers only once.
 */
export async function sendReaction(
  room: string,
  kind: ReactionKind,
  value: string,
  toSeat: number | null,
) {
  const body = { client_id: randomId(), kind, value, to_seat: toSeat }
  const post = () =>
    apiRequest<ApiResponse<'v1.rooms.reactions.store'>>(
      `/api/v1/rooms/${encodeURIComponent(room)}/reactions`,
      { method: 'POST', body },
    ).then((r) => r.data)
  try {
    return await post()
  } catch (error) {
    if (isApiError(error) && error.status < 500) throw error
    return post()
  }
}
