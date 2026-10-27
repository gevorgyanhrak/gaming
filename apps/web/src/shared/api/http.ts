import { env } from '@/shared/config/env'
import i18n from '@/shared/i18n'
import { ApiError } from './errors'

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

interface RequestOptions {
  method?: Method
  /** JSON-serializable value, or FormData for file uploads. */
  body?: unknown
  signal?: AbortSignal
}

/** Laravel's response when the CSRF token is missing or stale (e.g. after the session expired). */
const CSRF_MISMATCH = 419

/**
 * Calls the Laravel API.
 *
 * Sends session cookies (Sanctum SPA auth), the XSRF token on state-changing requests
 * (fetching the cookie first if needed), and the active UI language. Resolves with the
 * parsed JSON body; rejects with ApiError.
 */
export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const response = await send(path, options)

  if (response.status === CSRF_MISMATCH) {
    await refreshCsrfCookie()
    return parse<T>(await send(path, options))
  }

  return parse<T>(response)
}

async function send(path: string, options: RequestOptions): Promise<Response> {
  const method = options.method ?? 'GET'
  const headers = new Headers({ Accept: 'application/json', 'Accept-Language': i18n.language })
  const isForm = options.body instanceof FormData

  if (options.body !== undefined && !isForm) {
    headers.set('Content-Type', 'application/json')
  }

  if (method !== 'GET') {
    await ensureCsrfCookie()
    const token = readCookie('XSRF-TOKEN')
    if (token) headers.set('X-XSRF-TOKEN', token)
  }

  try {
    return await fetch(`${env.apiBaseUrl}${path}`, {
      method,
      headers,
      body: isForm
        ? (options.body as FormData)
        : options.body === undefined
          ? undefined
          : JSON.stringify(options.body),
      credentials: 'include',
      signal: options.signal,
    })
  } catch (error) {
    // Aborts come from TanStack Query cancelling a stale request; let them through untouched.
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    throw new ApiError(0, 'Network error')
  }
}

async function parse<T>(response: Response): Promise<T> {
  if (response.status === 204) {
    return undefined as T
  }

  const payload: unknown = await response.json().catch(() => null)

  if (!response.ok) {
    throw ApiError.fromResponse(response.status, payload)
  }

  return payload as T
}

let csrfRequest: Promise<void> | null = null

async function ensureCsrfCookie(): Promise<void> {
  if (!readCookie('XSRF-TOKEN')) await refreshCsrfCookie()
}

/** Asks Sanctum for a fresh XSRF-TOKEN cookie; concurrent callers share one request. */
function refreshCsrfCookie(): Promise<void> {
  csrfRequest ??= fetch(`${env.apiBaseUrl}/sanctum/csrf-cookie`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
    .then(() => undefined)
    .catch(() => undefined)
    .finally(() => {
      csrfRequest = null
    })

  return csrfRequest
}

function readCookie(name: string): string | null {
  const match = document.cookie.split('; ').find((cookie) => cookie.startsWith(`${name}=`))
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null
}
