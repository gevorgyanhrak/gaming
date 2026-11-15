import { apiRequest } from '@/shared/api/http'
import type { Schemas } from '@/shared/api/types'

export type Banner = Schemas['CasinoBannerResource']

export const bannersKey = ['banners'] as const

export const fetchBanners = (signal?: AbortSignal) =>
  apiRequest<{ data: Banner[] }>('/api/v1/banners', { signal }).then((r) => r.data)

/** A link inside the app (a path) rather than an https address elsewhere. */
export const isInternal = (link: string) => link.startsWith('/') && !link.startsWith('//')
