import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { apiRequest } from '@/shared/api/http'
import type { ApiResponse, Schemas } from '@/shared/api/types'

export type GameSummary = Schemas['GameResource']
export type GameDetail = Schemas['GameDetailResource']

// Game names and rules are translated server-side, so the locale is part of every key.
export const catalogKeys = {
  all: (locale: string) => ['games', locale] as const,
  detail: (locale: string, slug: string) => ['games', locale, slug] as const,
}

export function useGames() {
  const { i18n } = useTranslation()

  return useQuery<GameSummary[]>({
    queryKey: catalogKeys.all(i18n.language),
    queryFn: ({ signal }) =>
      apiRequest<ApiResponse<'v1.games.index'>>('/api/v1/games', { signal }).then((r) => r.data),
    // A language switch keeps the old names on screen until the new ones arrive.
    placeholderData: keepPreviousData,
  })
}

export function useGame(slug: string) {
  const { i18n } = useTranslation()

  return useQuery({
    queryKey: catalogKeys.detail(i18n.language, slug),
    queryFn: ({ signal }) =>
      apiRequest<ApiResponse<'v1.games.show'>>(`/api/v1/games/${encodeURIComponent(slug)}`, {
        signal,
      }).then((r) => r.data),
    // Only for a language switch: another game never borrows this one's page.
    placeholderData: (previous, previousQuery) =>
      previousQuery?.queryKey[2] === slug ? previous : undefined,
  })
}
