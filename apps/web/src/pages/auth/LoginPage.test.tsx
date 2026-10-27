import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

async function fillAndSubmit(login: string, password: string) {
  await userEvent.type(await screen.findByLabelText('Email or phone number'), login)
  await userEvent.type(screen.getByLabelText('Password'), password)
  await userEvent.click(screen.getByRole('button', { name: 'Log in' }))
}

describe('Login', () => {
  it('validates required fields without calling the API', async () => {
    let called = false
    server.use(
      http.post('*/api/v1/auth/login', () => {
        called = true
        return HttpResponse.json({})
      }),
    )
    renderRoute('/login')

    await userEvent.click(await screen.findByRole('button', { name: 'Log in' }))

    expect(await screen.findAllByText('This field is required.')).toHaveLength(2)
    expect(called).toBe(false)
  })

  it('shows the API error on the login field', async () => {
    server.use(
      http.post('*/api/v1/auth/login', () =>
        HttpResponse.json(
          {
            message: 'These credentials do not match our records.',
            errors: { login: ['These credentials do not match our records.'] },
          },
          { status: 422 },
        ),
      ),
    )
    renderRoute('/login')

    await fillAndSubmit('alice@example.com', 'wrong-password')

    expect(
      await screen.findByText('These credentials do not match our records.'),
    ).toBeInTheDocument()
    expect(screen.getByLabelText('Email or phone number')).toHaveAttribute('aria-invalid', 'true')
  })

  it('signs in and continues to the requested page', async () => {
    let body: unknown
    server.use(
      http.post('*/api/v1/auth/login', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: userFixture({ username: 'alice' }) })
      }),
    )
    const { router } = renderRoute('/login?next=%2Fprofile')

    await fillAndSubmit('alice@example.com', 'password1')

    expect(await screen.findByRole('heading', { name: 'alice' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/profile')
    expect(body).toEqual({ login: 'alice@example.com', password: 'password1', remember: true })
    expect(
      within(screen.getByRole('banner')).getByRole('button', { name: 'Account menu' }),
    ).toBeInTheDocument()
  })

  it('never redirects outside the app after signing in', async () => {
    server.use(http.post('*/api/v1/auth/login', () => HttpResponse.json({ data: userFixture() })))
    const { router } = renderRoute('/login?next=%2F%2Fevil.example')

    await fillAndSubmit('alice@example.com', 'password1')

    await screen.findByRole('heading', { name: /Welcome/ })
    expect(router.state.location.pathname).toBe('/dashboard')
  })
})
