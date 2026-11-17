import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCurrentUser } from '@/features/auth/hooks'
import { apiRequest } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/api/types'

export type Favorite = ApiResponse<'v1.me.favorites.index'>['data'][number]
export type FavoriteKind = Favorite['kind']

export const favoritesKey = ['me', 'favorites'] as const

const path = (kind: FavoriteKind, slug: string) =>
  `/api/v1/me/favorites/${kind}/${encodeURIComponent(slug)}`

/** The signed-in player's favourite games and slots, newest first; empty for a guest. */
export function useFavorites() {
  const { data: user } = useCurrentUser()

  return useQuery({
    queryKey: favoritesKey,
    queryFn: ({ signal }) =>
      apiRequest<ApiResponse<'v1.me.favorites.index'>>('/api/v1/me/favorites', { signal }).then(
        (r) => r.data,
      ),
    enabled: !!user,
  })
}

export const isFavorite = (list: Favorite[] | undefined, kind: FavoriteKind, slug: string) =>
  !!list?.some((f) => f.kind === kind && f.slug === slug)

/**
 * Toggles a favourite. The heart changes at once (the server's answer replaces the guess; a failure puts the
 * old list back), as people expect from a save button.
 */
export function useToggleFavorite() {
  const client = useQueryClient()

  return useMutation({
    mutationFn: ({ kind, slug, on }: { kind: FavoriteKind; slug: string; on: boolean }) =>
      apiRequest<ApiResponse<'v1.me.favorites.index'>>(path(kind, slug), {
        method: on ? 'PUT' : 'DELETE',
      }).then((r) => r.data),
    onMutate: async ({ kind, slug, on }) => {
      await client.cancelQueries({ queryKey: favoritesKey })
      const before = client.getQueryData<Favorite[]>(favoritesKey)
      const rest = (before ?? []).filter((f) => !(f.kind === kind && f.slug === slug))
      client.setQueryData<Favorite[]>(favoritesKey, on ? [{ kind, slug }, ...rest] : rest)
      return { before }
    },
    onError: (_error, _vars, context) => client.setQueryData(favoritesKey, context?.before),
    onSuccess: (list) => client.setQueryData(favoritesKey, list),
  })
}
