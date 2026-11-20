import { useQuery } from '@tanstack/react-query'
import { apiRequest } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/api/types'
import { randomId } from '@/shared/lib/uuid'

export type ChipPackagesResponse = ApiResponse<'v1.chip-packages.index'>
export type ChipPackage = ChipPackagesResponse['data'][number]

export const chipPackagesKey = ['chip-packages'] as const

/** The packages on sale, and whether buying works now (false until a payment provider is connected; ADR 0027). */
export function useChipPackages() {
  return useQuery({
    queryKey: chipPackagesKey,
    queryFn: ({ signal }) => apiRequest<ChipPackagesResponse>('/api/v1/chip-packages', { signal }),
    staleTime: 60_000,
  })
}

/** Starts buying a package: the server makes the order and says where to pay (the provider's page). */
export const createPaymentOrder = (slug: string, key = randomId()) =>
  apiRequest<ApiResponse<'v1.me.payments.orders.store'>>('/api/v1/me/payments/orders', {
    method: 'POST',
    body: { package: slug, key },
  })
