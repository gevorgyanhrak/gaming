import { http, HttpResponse } from 'msw'
import { afterEach, describe, expect, it } from 'vitest'
import i18n from '@/shared/i18n'
import { server } from '@/test/server'
import { ApiError } from './errors'
import { apiRequest } from './http'

describe('apiRequest', () => {
  afterEach(() => {
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
  })

  it('returns the parsed JSON body and sends the active language', async () => {
    await i18n.changeLanguage('ru')
    let acceptLanguage: string | null = null

    server.use(
      http.get('*/api/ping', ({ request }) => {
        acceptLanguage = request.headers.get('Accept-Language')
        return HttpResponse.json({ pong: true })
      }),
    )

    await expect(apiRequest('/api/ping')).resolves.toEqual({ pong: true })
    expect(acceptLanguage).toBe('ru')
  })

  it('normalizes Laravel validation errors', async () => {
    server.use(
      http.post('*/api/things', () =>
        HttpResponse.json(
          {
            message: 'The name field is required.',
            errors: { name: ['The name field is required.'] },
          },
          { status: 422 },
        ),
      ),
    )

    const error = await apiRequest('/api/things', { method: 'POST', body: {} }).catch(
      (e: unknown) => e,
    )

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({
      status: 422,
      message: 'The name field is required.',
      fieldErrors: { name: ['The name field is required.'] },
    })
  })

  it('reports unreachable servers as network errors', async () => {
    server.use(http.get('*/api/down', () => HttpResponse.error()))

    const error = await apiRequest('/api/down').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).isNetworkError).toBe(true)
  })

  it('sends the decoded XSRF token on state-changing requests only', async () => {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent('tok=en/1')}; path=/`
    const seen: Record<string, string | null> = {}

    server.use(
      http.get('*/api/read', ({ request }) => {
        seen.get = request.headers.get('X-XSRF-TOKEN')
        return HttpResponse.json({})
      }),
      http.post('*/api/write', ({ request }) => {
        seen.post = request.headers.get('X-XSRF-TOKEN')
        return new HttpResponse(null, { status: 204 })
      }),
    )

    await apiRequest('/api/read')
    await expect(apiRequest('/api/write', { method: 'POST' })).resolves.toBeUndefined()

    expect(seen).toEqual({ get: null, post: 'tok=en/1' })
  })

  it('fetches the CSRF cookie before the first state-changing request', async () => {
    const order: string[] = []
    server.use(
      http.get('*/sanctum/csrf-cookie', () => {
        order.push('csrf')
        document.cookie = 'XSRF-TOKEN=fresh; path=/'
        return new HttpResponse(null, { status: 204 })
      }),
      http.post('*/api/write', ({ request }) => {
        order.push(`post:${request.headers.get('X-XSRF-TOKEN')}`)
        return new HttpResponse(null, { status: 204 })
      }),
    )

    await apiRequest('/api/write', { method: 'POST' })

    expect(order).toEqual(['csrf', 'post:fresh'])
  })

  it('refreshes the CSRF cookie and retries once after a token mismatch', async () => {
    document.cookie = 'XSRF-TOKEN=stale; path=/'
    let attempts = 0
    server.use(
      http.get('*/sanctum/csrf-cookie', () => {
        document.cookie = 'XSRF-TOKEN=renewed; path=/'
        return new HttpResponse(null, { status: 204 })
      }),
      http.post('*/api/write', ({ request }) => {
        attempts += 1
        return request.headers.get('X-XSRF-TOKEN') === 'renewed'
          ? HttpResponse.json({ ok: true })
          : HttpResponse.json({ message: 'CSRF token mismatch.' }, { status: 419 })
      }),
    )

    await expect(apiRequest('/api/write', { method: 'POST' })).resolves.toEqual({ ok: true })
    expect(attempts).toBe(2)
  })

  it('sends FormData without forcing a JSON content type', async () => {
    document.cookie = 'XSRF-TOKEN=t; path=/'
    let contentType: string | null = null
    server.use(
      http.post('*/api/upload', ({ request }) => {
        contentType = request.headers.get('Content-Type')
        return HttpResponse.json({})
      }),
    )
    const body = new FormData()
    body.append('avatar', new Blob(['x'], { type: 'image/png' }), 'a.png')

    await apiRequest('/api/upload', { method: 'POST', body })

    expect(contentType).toMatch(/^multipart\/form-data/)
  })
})
