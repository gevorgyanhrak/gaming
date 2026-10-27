import { env } from '@/shared/config/env'
import i18n from '@/shared/i18n'
import { ApiError } from './errors'

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

interface RequestOptions {
  method?: Method
  body?: unknown
  signal?: AbortSignal
}

/**
 * Calls the Laravel API.
 *
 * Sends session cookies (Sanctum SPA auth), the XSRF token on state-changing requests,
 * and the active UI language. Resolves with the parsed JSON body; rejects with ApiError.
 */
export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method ?? 'GET'
  const headers = new Headers({ Accept: 'application/json', 'Accept-Language': i18n.language })

  if (options.body !== undefined) {
    headers.set('Content-Type', 'application/json')
  }

  if (method !== 'GET') {
    const token = readCookie('XSRF-TOKEN')
    if (token) headers.set('X-XSRF-TOKEN', token)
  }

  let response: Response
  try {
    response = await fetch(`${env.apiBaseUrl}${path}`, {
      method,
      headers,
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      credentials: 'include',
      signal: options.signal,
    })
  } catch (error) {
    // Aborts come from TanStack Query cancelling a stale request; let them through untouched.
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    throw new ApiError(0, 'Network error')
  }

  if (response.status === 204) {
    return undefined as T
  }

  const payload: unknown = await response.json().catch(() => null)

  if (!response.ok) {
    throw ApiError.fromResponse(response.status, payload)
  }

  return payload as T
}

function readCookie(name: string): string | null {
  const match = document.cookie.split('; ').find((cookie) => cookie.startsWith(`${name}=`))
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null
}
