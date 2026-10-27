import { apiRequest } from '@/shared/api/http'
import type { ApiResponse, Schemas } from '@/shared/api/types'

export type ParticipantResult = 'win' | 'loss' | 'draw' | 'abandoned'
export type RoomStatus = 'waiting' | 'active' | 'finished' | 'aborted'

export type Room = Schemas['RoomResource']
export type RoomState = NonNullable<ApiResponse<'v1.rooms.state'>['data']>
/** Matchmaking status. Declared here: the ticket's shape isn't inferable from the API docs. */
export interface Matchmaking {
  ticket: {
    status: 'queued' | 'bot_offered'
    game_mode_id: number
    joined_at: string
    bot_offer_at: string | null
  } | null
  room: string | null
}
export type GameStat = Schemas['GameStatResource']
export type GameAction = { type: string } & Record<string, unknown>
export type BotDifficulty = 'easy' | 'medium' | 'hard'

export const matchmakingKey = ['matchmaking'] as const

type RoomResponse = { data: Room }
const unwrap = <T>(r: { data: T }) => r.data

export const createBotRoom = (gameModeId: number, difficulty: BotDifficulty) =>
  apiRequest<RoomResponse>('/api/v1/rooms', {
    method: 'POST',
    body: { game_mode_id: gameModeId, type: 'bot', bot_difficulty: difficulty },
  }).then(unwrap)

export const createPrivateRoom = (gameModeId: number) =>
  apiRequest<RoomResponse>('/api/v1/rooms', {
    method: 'POST',
    body: { game_mode_id: gameModeId, type: 'private' },
  }).then(unwrap)

export const joinRoom = (inviteCode: string) =>
  apiRequest<RoomResponse>('/api/v1/rooms/join', {
    method: 'POST',
    body: { invite_code: inviteCode },
  }).then(unwrap)

export const fetchRoom = (id: string, signal?: AbortSignal) =>
  apiRequest<RoomResponse>(`/api/v1/rooms/${encodeURIComponent(id)}`, { signal }).then(unwrap)

export const fetchRoomState = (id: string, signal?: AbortSignal) =>
  apiRequest<ApiResponse<'v1.rooms.state'>>(`/api/v1/rooms/${encodeURIComponent(id)}/state`, {
    signal,
  }).then(unwrap)

export const submitAction = (id: string, action: GameAction, expectedVersion: number) =>
  apiRequest<{ data: RoomState }>(`/api/v1/rooms/${encodeURIComponent(id)}/actions`, {
    method: 'POST',
    body: { client_action_id: crypto.randomUUID(), expected_version: expectedVersion, action },
  }).then(unwrap)

export const leaveRoom = (id: string) =>
  apiRequest<{ data: Room }>(`/api/v1/rooms/${encodeURIComponent(id)}/leave`, {
    method: 'POST',
  }).then(unwrap)

export const startMatchmaking = (gameModeId: number) =>
  apiRequest<{ data: Matchmaking }>('/api/v1/matchmaking', {
    method: 'POST',
    body: { game_mode_id: gameModeId },
  }).then(unwrap)

export const fetchMatchmaking = (signal?: AbortSignal) =>
  apiRequest<{ data: Matchmaking }>('/api/v1/matchmaking', { signal }).then(unwrap)

export const matchmakingHeartbeat = () =>
  apiRequest<{ data: Matchmaking }>('/api/v1/matchmaking/heartbeat', { method: 'POST' }).then(
    unwrap,
  )

export const cancelMatchmaking = () => apiRequest<void>('/api/v1/matchmaking', { method: 'DELETE' })

export const acceptBotGame = (difficulty: BotDifficulty = 'medium') =>
  apiRequest<{ data: Matchmaking }>('/api/v1/matchmaking/accept-bot', {
    method: 'POST',
    body: { bot_difficulty: difficulty },
  }).then(unwrap)

export const fetchMyRooms = (signal?: AbortSignal) =>
  apiRequest<{ data: Room[] }>('/api/v1/me/rooms', { signal }).then(unwrap)

export const fetchMyGames = (page: number, signal?: AbortSignal) =>
  apiRequest<{ data: Room[]; meta: { current_page: number; last_page: number; total: number } }>(
    `/api/v1/me/games?page=${page}`,
    { signal },
  )

export const fetchMyStats = (signal?: AbortSignal) =>
  apiRequest<{ data: GameStat[] }>('/api/v1/me/stats', { signal }).then(unwrap)
