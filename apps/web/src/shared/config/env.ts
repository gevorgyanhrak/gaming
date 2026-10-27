export const env = {
  /** Empty means same origin (the dev server proxies API paths to Laravel). */
  apiBaseUrl: (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/+$/, ''),
  reverb: {
    key: import.meta.env.VITE_REVERB_APP_KEY ?? '',
    host: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    port: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    tls: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
  },
} as const
