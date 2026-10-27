import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { signedInAs, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('Verification', () => {
  it('verifies the contact and continues to the dashboard', async () => {
    server.use(
      signedInAs(userFixture({ email_verified: false })),
      http.post('*/api/v1/auth/verification/verify', () =>
        HttpResponse.json({ data: userFixture({ email_verified: true }) }),
      ),
    )
    const { router } = renderRoute('/verify?channel=email')

    expect(await screen.findByText(/sent to alice@example.com/)).toBeInTheDocument()
    await userEvent.type(screen.getByLabelText('Code'), '123456')
    await userEvent.click(screen.getByRole('button', { name: 'Verify' }))

    await screen.findByRole('heading', { name: /Welcome/ })
    expect(router.state.location.pathname).toBe('/dashboard')
    expect(screen.queryByText('Please verify your email address.')).not.toBeInTheDocument()
  })

  it('shows an invalid code error', async () => {
    server.use(
      signedInAs(userFixture({ email_verified: false })),
      http.post('*/api/v1/auth/verification/verify', () =>
        HttpResponse.json(
          {
            message: 'The code is invalid or has expired.',
            errors: { code: ['The code is invalid or has expired.'] },
          },
          { status: 422 },
        ),
      ),
    )
    renderRoute('/verify?channel=email')

    await userEvent.type(await screen.findByLabelText('Code'), '000000')
    await userEvent.click(screen.getByRole('button', { name: 'Verify' }))

    expect(await screen.findByText('The code is invalid or has expired.')).toBeInTheDocument()
  })

  it('resends a code and then cools down', async () => {
    server.use(
      signedInAs(userFixture({ email_verified: false })),
      http.post('*/api/v1/auth/verification/send', () =>
        HttpResponse.json({ message: 'sent' }, { status: 202 }),
      ),
    )
    renderRoute('/verify?channel=email')

    await userEvent.click(await screen.findByRole('button', { name: 'Send a new code' }))

    expect(await screen.findByText('We sent you a new code.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /New code in 60/ })).toBeDisabled()
  })
})
