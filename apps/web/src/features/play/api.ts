import { apiRequest } from '@/shared/api/http'
import type { ApiResponse, Schemas } from '@/shared/api/types'
import { randomId } from '@/shared/lib/uuid'

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
  /** Players in the same queue, you included (bigger tables wait for several). */
  waiting?: number | null
}
export type GameStat = Schemas['GameStatResource']
export type GameAction = { type: string } & Record<string, unknown>
export type BotDifficulty = 'easy' | 'medium' | 'hard'

export const matchmakingKey = ['matchmaking'] as const
/** Games the player is currently in (GET /me/rooms). */
export const myRoomsKey = ['me', 'rooms'] as const
/** Per-game statistics (GET /me/stats). */
export const myStatsKey = ['me', 'stats'] as const
/** A page of finished games (GET /me/games). */
export const myGamesKey = (page: number) => ['me', 'games', page] as const

/**
 * Queries whose answers carry translated names (games, modes) but whose keys don't hold the language:
 * rooms (not their state), the player's rooms, games and statistics, and public profiles.
 */
export function translatedQuery(key: readonly unknown[]): boolean {
  const [scope, name] = key
  if (scope === 'rooms') return key.length === 2
  if (scope === 'me') return name === 'rooms' || name === 'games' || name === 'stats'
  return scope === 'users'
}

type RoomResponse = { data: Room }
const unwrap = <T>(r: { data: T }) => r.data

/** `players` (tables for more than two): everyone at the table, you included. */
export const createBotRoom = (gameModeId: number, difficulty: BotDifficulty, players = 2) =>
  apiRequest<RoomResponse>('/api/v1/rooms', {
    method: 'POST',
    body: {
      game_mode_id: gameModeId,
      type: 'bot',
      bot_difficulty: difficulty,
      ...(players > 2 ? { opponents: players - 1 } : {}),
    },
  }).then(unwrap)

export const createPrivateRoom = (gameModeId: number, players?: number) =>
  apiRequest<RoomResponse>('/api/v1/rooms', {
    method: 'POST',
    body: { game_mode_id: gameModeId, type: 'private', ...(players ? { seats: players } : {}) },
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
    body: { client_action_id: randomId(), expected_version: expectedVersion, action },
  }).then(unwrap)

/** "Still on the game page" (see usePresence). */
export const sendPresence = (id: string) =>
  apiRequest<null>(`/api/v1/rooms/${encodeURIComponent(id)}/presence`, { method: 'POST' })

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
