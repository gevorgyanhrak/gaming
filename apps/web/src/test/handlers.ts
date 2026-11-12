import { http, HttpResponse } from 'msw'
import type { Wallet } from '@/features/wallet/api'
import type { CurrentUser } from '@/features/auth/api'
import type { Room, RoomState } from '@/features/play/api'
import type { GameDetail, GameSummary } from '@/features/catalog/queries'
import type { PublicConfig } from '@/features/platform/usePublicConfig'
import type { DailyBonus } from '@/features/wallet/dailyBonus'
import type { LevelStatus } from '@/features/levels/api'

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
  play: { abandon_after_seconds: 15, reactions: true },
  pages: [],
}

export function gameFixture(overrides: Partial<GameDetail> = {}): GameDetail {
  return {
    slug: 'chess',
    category: { slug: 'board', name: 'Board games', sort_order: 10 },
    name: 'Chess',
    description: 'Checkmate the king.',
    icon_url: null,
    thumbnail_url: null,
    min_players: 2,
    max_players: 2,
    is_playable: false,
    rules: 'First paragraph.\n\nSecond paragraph.',
    modes: [],
    groups: [],
    ...overrides,
  }
}

export function summaryOf({ rules, modes, groups, ...summary }: GameDetail): GameSummary {
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
    level: 1,
    looks: { frame: null, badge: null },
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

/** A wallet summary (GET /me/wallet). */
export function walletFixture(overrides: Partial<Wallet> = {}): Wallet {
  return {
    balance: 5000,
    total_earned: 5000,
    total_spent: 0,
    entry_fee: 500,
    at_table: 0,
    refill: { available: false, available_at: null, amount: 5000 },
    ...overrides,
  }
}

/** Today's daily bonus (GET /me/daily-bonus); collected unless a test says otherwise. */
export function dailyBonusFixture(overrides: Partial<DailyBonus> = {}): DailyBonus {
  return {
    day: 1,
    claimed: true,
    amount: 1000,
    tomorrow: 1500,
    days: [1000, 1500, 2000, 2500, 5000].map((amount, i) => ({ day: i + 1, amount })),
    ...overrides,
  }
}

/** A small reaction catalog (GET /reactions). */
export const reactionCatalogFixture = {
  emoji: [
    {
      slug: 'smileys',
      emojis: [
        { emoji: '😀', keywords: 'grin smile улыбка ժպիտ' },
        { emoji: '😂', keywords: 'laugh смех ծիծաղ' },
      ],
    },
    { slug: 'love', emojis: [{ emoji: '❤️', keywords: 'heart love сердце սիրտ' }] },
  ],
  messages: [
    { slug: 'greetings', keys: ['hello', 'good_luck'] },
    { slug: 'praise', keys: ['nice_move', 'good_game', 'gg'] },
  ],
  exclusive: [{ emoji: '🔱', level: 10 }],
}

/** The player's level (GET /me/level): level 1, nothing waiting unless a test says so. */
export function levelFixture(overrides: Partial<LevelStatus> = {}): LevelStatus {
  return {
    level: 1,
    xp: 30,
    level_xp: 0,
    next_level_xp: 80,
    max_level: 100,
    frame: null,
    badge: null,
    next_reward: { level: 2, fishkas: 350, items: [], is_milestone: false },
    pending: [],
    ...overrides,
  }
}

export const handlers = [
  http.get('*/api/v1/me/wallet', () => HttpResponse.json({ data: walletFixture() })),
  // Visitors are guests unless a test uses signedInAs().
  http.get('*/api/v1/me', unauthenticated),
  http.get('*/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })),
  http.get('*/api/v1/me/stats', () => HttpResponse.json({ data: [] })),
  http.get('*/api/v1/me/rooms', () => HttpResponse.json({ data: [] })),
  http.post('*/api/v1/rooms/:id/presence', () => new HttpResponse(null, { status: 204 })),
  http.get('*/api/v1/matchmaking', () =>
    HttpResponse.json({ data: { ticket: null, room: null, waiting: null } }),
  ),
  http.get('*/api/v1/config', () => HttpResponse.json({ data: configFixture })),
  http.get('*/api/v1/reactions', () => HttpResponse.json({ data: reactionCatalogFixture })),
  http.get('*/api/v1/me/daily-bonus', () => HttpResponse.json({ data: dailyBonusFixture() })),
  http.get('*/api/v1/me/level', () => HttpResponse.json({ data: levelFixture() })),
  http.get('*/api/v1/rooms/:id/reactions', () => HttpResponse.json({ data: [] })),
  http.get('*/api/v1/games', () => HttpResponse.json({ data: [summaryOf(gameFixture())] })),
]

export function roomFixture(overrides: Partial<Room> = {}): Room {
  return {
    id: 'room-1',
    game: { slug: 'tictactoe', name: 'Tic-tac-toe' },
    mode: { id: 1, slug: 'classic', name: 'Classic' },
    type: 'bot',
    status: 'active',
    group: null,
    stake: 0,
    invite_code: null,
    seats: 2,
    participants: [
      {
        seat: 0,
        is_bot: false,
        name: 'alice',
        username: 'alice',
        avatar_url: null,
        level: 1,
        frame: null,
        result: null,
        score: null,
      },
      {
        seat: 1,
        is_bot: true,
        name: 'Aram',
        username: null,
        avatar_url: null,
        level: 42,
        frame: null,
        result: null,
        score: null,
      },
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
    playing: true,
    seats_to_act: [0],
    turn_seconds: 60,
    deadline: null,
    server_time: '2026-10-27T10:00:00+00:00',
    clocks: null,
    view: { board: [null, null, null] },
    legal_actions: [{ type: 'place', cell: 0 }],
    result: null,
    ...overrides,
  }
}
