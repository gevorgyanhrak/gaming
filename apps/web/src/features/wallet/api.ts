import { apiRequest } from '@/shared/api/http'
import { isApiError } from '@/shared/api/errors'
import type { ApiResponse, Schemas } from '@/shared/api/types'

export type Wallet = ApiResponse<'v1.me.wallet.show'>['data']
export type FishkaTransaction = Schemas['FishkaTransactionResource']
export type FishkaPage = ApiResponse<'v1.me.wallet.transactions'>

export const walletKey = ['me', 'wallet'] as const
export const walletHistoryKey = ['me', 'wallet', 'transactions'] as const

const unwrap = <T>(r: { data: T }) => r.data

export const fetchWallet = (signal?: AbortSignal) =>
  apiRequest<ApiResponse<'v1.me.wallet.show'>>('/api/v1/me/wallet', { signal }).then(unwrap)

export const fetchFishkaHistory = (page: number, signal?: AbortSignal) =>
  apiRequest<FishkaPage>(`/api/v1/me/wallet/transactions?page=${page}`, { signal })

/** The "out of Fishkas" bonus; the server decides whether it's available. */
export const refillFishkas = () =>
  apiRequest<ApiResponse<'v1.me.wallet.refill'>>('/api/v1/me/wallet/refill', {
    method: 'POST',
  }).then(unwrap)

/** A game couldn't start because the balance can't pay for the seat. */
export const isOutOfFishkas = (error: unknown) =>
  isApiError(error) && error.code === 'insufficient_fishkas'
