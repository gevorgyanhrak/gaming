import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { signedInAs, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('Route access', () => {
  it('sends guests to the login page and remembers where they were going', async () => {
    const { router } = renderRoute('/settings')

    expect(await screen.findByRole('heading', { name: 'Log in' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(router.state.location.search).toBe('?next=%2Fsettings')
  })

  it('sends signed-in users away from the login page', async () => {
    server.use(signedInAs(userFixture({ username: 'alice' })))
    const { router } = renderRoute('/login')

    expect(await screen.findByRole('heading', { name: 'Welcome, alice' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/dashboard')
  })

  it('logs out from the account menu', async () => {
    let loggedOut = false
    server.use(
      signedInAs(),
      http.post('*/api/v1/auth/logout', () => {
        loggedOut = true
        return new HttpResponse(null, { status: 204 })
      }),
    )
    const { router } = renderRoute('/dashboard')

    await userEvent.click(await screen.findByRole('button', { name: 'Account menu' }))
    // From now on the session is gone.
    server.use(
      http.get('*/api/v1/me', () =>
        HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 }),
      ),
    )
    await userEvent.click(screen.getByRole('button', { name: 'Log out' }))

    const header = screen.getByRole('banner')
    expect(await within(header).findByRole('link', { name: 'Log in' })).toBeInTheDocument()
    expect(loggedOut).toBe(true)
    expect(router.state.location.pathname).toBe('/')
  })

  it('reminds users to verify their contact', async () => {
    server.use(signedInAs(userFixture({ email_verified: false })))
    renderRoute('/dashboard')

    expect(await screen.findByText('Please verify your email address.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Verify now' })).toHaveAttribute(
      'href',
      '/verify?channel=email',
    )
  })
})
