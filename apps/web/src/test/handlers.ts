import { http, HttpResponse } from 'msw'
import type { GameDetail, GameSummary } from '@/features/catalog/queries'
import type { PublicConfig } from '@/features/platform/usePublicConfig'

// Test fixtures for MSW only; never imported by application code.

export const configFixture: PublicConfig = {
  name: 'Test Platform',
  default_locale: 'en',
  supported_locales: ['en', 'ru', 'hy'],
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
    ...overrides,
  }
}

export function summaryOf({ rules, ...summary }: GameDetail): GameSummary {
  return summary
}

export const handlers = [
  http.get('*/api/v1/config', () => HttpResponse.json({ data: configFixture })),
  http.get('*/api/v1/games', () => HttpResponse.json({ data: [summaryOf(gameFixture())] })),
]
