import { apiRequest } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/api/types'

export type Gifts = ApiResponse<'v1.me.gifts.show'>['data']

export const giftsKey = ['me', 'gifts'] as const

const unwrap = <T>(r: { data: T }) => r.data

export const fetchGifts = (signal?: AbortSignal) =>
  apiRequest<ApiResponse<'v1.me.gifts.show'>>('/api/v1/me/gifts', { signal }).then(unwrap)

export const claimGift = () =>
  apiRequest<ApiResponse<'v1.me.gifts.claim'>>('/api/v1/me/gifts/claim', { method: 'POST' }).then(
    unwrap,
  )

export const spinWheel = () =>
  apiRequest<ApiResponse<'v1.me.gifts.spin'>>('/api/v1/me/gifts/spin', { method: 'POST' }).then(
    unwrap,
  )
