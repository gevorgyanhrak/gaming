import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { createQueryClient } from '@/app/queryClient'
import { ApiError } from '@/shared/api/errors'
import { userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const tooMany = () => HttpResponse.json({ message: 'Too Many Attempts.' }, { status: 429 })

describe('When the account cannot be loaded', () => {
  it('says so with a way to try again, and never shows the visitor as a guest', async () => {
    let fail = true
    server.use(
      http.get('*/api/v1/me', () =>
        fail ? tooMany() : HttpResponse.json({ data: userFixture() }),
      ),
    )
    renderRoute('/')

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent("We couldn't load your account.")
    // Unknown is not "signed out": no Log in / Sign up in the header or the tab bar.
    expect(screen.queryByRole('link', { name: 'Log in' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Sign up' })).not.toBeInTheDocument()

    fail = false
    await userEvent.click(within(alert).getByRole('button', { name: 'Try again' }))

    expect(await screen.findByRole('link', { name: /^Your Fishkas:/ })).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('on a page for signed-in players shows one message in the page, not a second bar', async () => {
    server.use(http.get('*/api/v1/me', tooMany))
    renderRoute('/wallet')

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent("We couldn't load your account.")
    expect(within(alert).getByRole('button', { name: 'Try again' })).toBeInTheDocument()
    expect(screen.getAllByRole('alert')).toHaveLength(1)
    expect(screen.queryByTestId('page-loading')).not.toBeInTheDocument()
  })

  it('waits as long as the server asks (Retry-After) before trying again', () => {
    const retryDelay = createQueryClient().getDefaultOptions().queries?.retryDelay
    if (typeof retryDelay !== 'function') throw new Error('retryDelay is not a function')
    const limited = new ApiError(429, 'Too Many Attempts.')
    limited.retryAfter = 3
    expect(retryDelay(1, limited)).toBe(3000)
    expect(retryDelay(1, new ApiError(500, 'Server error'))).toBe(2000)
    limited.retryAfter = 60
    expect(retryDelay(1, limited)).toBe(16_000)
  })
})
