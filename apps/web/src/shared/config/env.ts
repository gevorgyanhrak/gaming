export const env = {
  /** Empty means same origin (the dev server proxies API paths to Laravel). */
  apiBaseUrl: (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/+$/, ''),
  reverb: {
    key: import.meta.env.VITE_REVERB_APP_KEY ?? '',
    // Empty: the host the page was opened from (works for localhost and for a phone on the LAN, even
    // after the computer's address changes).
    host: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    port: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    tls: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
  },
} as const
