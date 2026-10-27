import { http, HttpResponse } from 'msw'
import type { CurrentUser } from '@/features/auth/api'
import type { Room, RoomState } from '@/features/play/api'
import type { GameDetail, GameSummary } from '@/features/catalog/queries'
import type { PublicConfig } from '@/features/platform/usePublicConfig'

// Test fixtures for MSW only; never imported by application code.

export const configFixture: PublicConfig = {
  name: 'Test Platform',
  logo_url: null,
  favicon_url: null,
  default_locale: 'en',
  supported_locales: ['en', 'ru', 'hy'],
  maintenance: { enabled: false, message: null },
  registration: {
    enabled: true,
    methods: ['email', 'phone'],
    require_email_verification: true,
    require_phone_verification: true,
    requires_terms: false,
  },
  pages: [],
}

export function gameFixture(overrides: Partial<GameDetail> = {}): GameDetail {
  return {
    slug: 'chess',
    name: 'Chess',
    description: 'Checkmate the king.',
    icon_url: null,
    thumbnail_url: null,
    min_players: 2,
    max_players: 2,
    is_playable: false,
    rules: 'First paragraph.\n\nSecond paragraph.',
    modes: [],
    ...overrides,
  }
}

export function summaryOf({ rules, modes, ...summary }: GameDetail): GameSummary {
  return summary
}

export function userFixture(overrides: Partial<CurrentUser> = {}): CurrentUser {
  return {
    id: 1,
    username: 'alice',
    email: 'alice@example.com',
    phone: null,
    email_verified: true,
    phone_verified: false,
    has_password: true,
    locale: 'en',
    created_at: '2026-01-15T10:00:00+00:00',
    last_active_at: '2026-10-27T09:00:00+00:00',
    can_access_admin: false,
    profile: {
      display_name: null,
      avatar_url: null,
      bio: null,
      country: null,
      preferences: { sound: true },
    },
    ...overrides,
  }
}

const unauthenticated = () => HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 })

/** Signs the test user in: /me returns them until the handlers are reset. */
export function signedInAs(user: CurrentUser = userFixture()) {
  return http.get('*/api/v1/me', () => HttpResponse.json({ data: user }))
}

export const handlers = [
  // Visitors are guests unless a test uses signedInAs().
  http.get('*/api/v1/me', unauthenticated),
  http.get('*/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })),
  http.get('*/api/v1/me/rooms', () => HttpResponse.json({ data: [] })),
  http.get('*/api/v1/me/stats', () => HttpResponse.json({ data: [] })),
  http.get('*/api/v1/matchmaking', () => HttpResponse.json({ data: { ticket: null, room: null } })),
  http.get('*/api/v1/config', () => HttpResponse.json({ data: configFixture })),
  http.get('*/api/v1/games', () => HttpResponse.json({ data: [summaryOf(gameFixture())] })),
]

export function roomFixture(overrides: Partial<Room> = {}): Room {
  return {
    id: 'room-1',
    game: { slug: 'tictactoe', name: 'Tic-tac-toe' },
    mode: { slug: 'classic', name: 'Classic' },
    type: 'bot',
    status: 'active',
    invite_code: null,
    seats: 2,
    participants: [
      { seat: 0, is_bot: false, name: 'alice', username: 'alice', avatar_url: null, result: null },
      { seat: 1, is_bot: true, name: 'Aram', username: null, avatar_url: null, result: null },
    ],
    end_reason: null,
    created_at: '2026-10-27T10:00:00+00:00',
    started_at: '2026-10-27T10:00:00+00:00',
    finished_at: null,
    ...overrides,
  }
}

export function roomStateFixture(overrides: Partial<RoomState> = {}): RoomState {
  return {
    room: 'room-1',
    status: 'active',
    version: 0,
    seat: 0,
    seats_to_act: [0],
    deadline: null,
    server_time: '2026-10-27T10:00:00+00:00',
    view: { board: [null, null, null] },
    legal_actions: [{ type: 'place', cell: 0 }],
    result: null,
    ...overrides,
  }
}
