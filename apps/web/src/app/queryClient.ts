import { QueryClient } from '@tanstack/react-query'
import { isApiError } from '@/shared/api/errors'

export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        // Client errors (4xx) won't succeed on retry; network and server errors might.
        retry: (failureCount, error) =>
          isApiError(error) && error.status >= 400 && error.status < 500 ? false : failureCount < 2,
      },
    },
  })
}
