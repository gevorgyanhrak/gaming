import { useQuery } from '@tanstack/react-query'
import { apiRequest } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/api/types'

export type PublicConfig = ApiResponse<'v1.config'>['data']

export function usePublicConfig() {
  return useQuery({
    queryKey: ['config'],
    queryFn: ({ signal }) =>
      apiRequest<ApiResponse<'v1.config'>>('/api/v1/config', { signal }).then((r) => r.data),
    staleTime: 5 * 60_000,
  })
}
