import { http, HttpResponse } from 'msw'
import type { CurrentUser } from '@/features/auth/api'
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
  http.get('*/api/v1/config', () => HttpResponse.json({ data: configFixture })),
  http.get('*/api/v1/games', () => HttpResponse.json({ data: [summaryOf(gameFixture())] })),
]
