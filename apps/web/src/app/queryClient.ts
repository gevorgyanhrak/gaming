import { MutationCache, QueryClient } from '@tanstack/react-query'
import { isOutOfFishkas, walletKey } from '@/features/wallet/api'
import { openOutOfFishkas } from '@/features/wallet/outOfFishkas'
import { isApiError } from '@/shared/api/errors'

export function createQueryClient(): QueryClient {
  const client: QueryClient = new QueryClient({
    // Starting a game, taking a bonus or leaving a table can change the balance: refresh it after
    // every change the player makes. A game that can't start for lack of Fishkas opens the popup.
    mutationCache: new MutationCache({
      onError: (error) => {
        if (isOutOfFishkas(error)) openOutOfFishkas()
      },
      onSettled: (_data, _error, _variables, _context, mutation) =>
        mutation.meta?.signsOut ? undefined : client.invalidateQueries({ queryKey: walletKey }),
    }),
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        // Client errors (4xx) won't succeed on retry; network and server errors might.
        retry: (failureCount, error) =>
          isApiError(error) && error.status >= 400 && error.status < 500 ? false : failureCount < 2,
      },
    },
  })
  return client
}
