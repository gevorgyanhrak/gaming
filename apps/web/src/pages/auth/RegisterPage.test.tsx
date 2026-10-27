import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { configFixture, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

async function fillCommon() {
  await userEvent.type(screen.getByLabelText('Username'), 'new_player')
  await userEvent.type(screen.getByLabelText('Password'), 'secret-pass-1')
  await userEvent.type(screen.getByLabelText('Confirm password'), 'secret-pass-1')
}

describe('Registration', () => {
  it('asks only for the identifier of the chosen method', async () => {
    renderRoute('/register')

    expect(await screen.findByLabelText('Email address')).toBeInTheDocument()
    expect(screen.queryByLabelText('Phone number')).not.toBeInTheDocument()

    await userEvent.click(screen.getByLabelText('Phone'))

    expect(screen.getByLabelText('Phone number')).toBeInTheDocument()
    expect(screen.queryByLabelText('Email address')).not.toBeInTheDocument()
  })

  it('validates the password confirmation before submitting', async () => {
    renderRoute('/register')
    await userEvent.type(await screen.findByLabelText('Email address'), 'new@example.com')
    await userEvent.type(screen.getByLabelText('Username'), 'new_player')
    await userEvent.type(screen.getByLabelText('Password'), 'secret-pass-1')
    await userEvent.type(screen.getByLabelText('Confirm password'), 'different-1')
    await userEvent.click(screen.getByRole('button', { name: 'Create account' }))

    expect(await screen.findByText("Passwords don't match.")).toBeInTheDocument()
  })

  it('shows API errors on their fields', async () => {
    server.use(
      http.post('*/api/v1/auth/register', () =>
        HttpResponse.json(
          {
            message: 'The username has already been taken.',
            errors: { username: ['The username has already been taken.'] },
          },
          { status: 422 },
        ),
      ),
    )
    renderRoute('/register')
    await userEvent.type(await screen.findByLabelText('Email address'), 'new@example.com')
    await fillCommon()
    await userEvent.click(screen.getByRole('button', { name: 'Create account' }))

    expect(await screen.findByText('The username has already been taken.')).toBeInTheDocument()
  })

  it('registers by phone and continues to verification', async () => {
    let body: Record<string, unknown> = {}
    server.use(
      http.post('*/api/v1/auth/register', async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json(
          { data: userFixture({ email: null, phone: '+37477123456', phone_verified: false }) },
          { status: 201 },
        )
      }),
    )
    const { router } = renderRoute('/register')

    await userEvent.click(await screen.findByLabelText('Phone'))
    await userEvent.type(screen.getByLabelText('Phone number'), '+374 77 123456')
    await fillCommon()
    await userEvent.click(screen.getByRole('button', { name: 'Create account' }))

    expect(
      await screen.findByRole('heading', { name: 'Verify your phone number' }),
    ).toBeInTheDocument()
    expect(router.state.location.search).toBe('?channel=phone&sent=1')
    expect(body).toMatchObject({ method: 'phone', phone: '+374 77 123456', username: 'new_player' })
    // The resend button starts on cooldown because a code was just sent.
    expect(screen.getByRole('button', { name: /New code in/ })).toBeDisabled()
  })

  it('explains when registration is closed', async () => {
    server.use(
      http.get('*/api/v1/config', () =>
        HttpResponse.json({
          data: {
            ...configFixture,
            registration: { ...configFixture.registration, enabled: false },
          },
        }),
      ),
    )
    renderRoute('/register')

    expect(await screen.findByText(/Registration is currently closed/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Create account' })).not.toBeInTheDocument()
  })
})
