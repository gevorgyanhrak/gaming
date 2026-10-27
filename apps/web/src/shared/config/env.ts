export const env = {
  /** Empty means same origin (the dev server proxies API paths to Laravel). */
  apiBaseUrl: (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/+$/, ''),
} as const
