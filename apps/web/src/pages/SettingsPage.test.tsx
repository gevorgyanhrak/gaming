import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { signedInAs, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('Settings', () => {
  it('saves the profile', async () => {
    let body: unknown
    server.use(
      signedInAs(),
      http.patch('*/api/v1/me/profile', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({
          data: userFixture({
            profile: { ...userFixture().profile, display_name: 'Alice A.', country: 'AM' },
          }),
        })
      }),
    )
    renderRoute('/settings')

    await userEvent.type(await screen.findByLabelText('Display name'), 'Alice A.')
    await userEvent.selectOptions(screen.getByLabelText('Country'), 'AM')
    await userEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('Saved.')).toBeInTheDocument()
    expect(body).toEqual({ display_name: 'Alice A.', bio: null, country: 'AM' })
  })

  it('adds a phone number in two steps', async () => {
    server.use(
      signedInAs(),
      http.post('*/api/v1/me/contact', () =>
        HttpResponse.json({ message: 'sent' }, { status: 202 }),
      ),
      http.post('*/api/v1/me/contact/verify', () =>
        HttpResponse.json({ data: userFixture({ phone: '+37477123456', phone_verified: true }) }),
      ),
    )
    renderRoute('/settings')

    const contacts = (await screen.findByRole('heading', { name: 'Email and phone' })).closest(
      'section',
    )!
    await userEvent.click(within(contacts).getByRole('button', { name: 'Add' }))
    await userEvent.type(within(contacts).getByLabelText('New phone number'), '+374 77 123456')
    await userEvent.click(within(contacts).getByRole('button', { name: 'Send code' }))

    expect(
      await within(contacts).findByText('Enter the code we sent to +374 77 123456.'),
    ).toBeInTheDocument()
    await userEvent.type(within(contacts).getByLabelText('Code'), '123456')
    await userEvent.click(within(contacts).getByRole('button', { name: 'Confirm' }))

    expect(await within(contacts).findByText('+37477123456')).toBeInTheDocument()
    expect(within(contacts).getAllByText('Verified')).toHaveLength(2)
  })

  it('shows a wrong current password on its field', async () => {
    server.use(
      signedInAs(),
      http.put('*/api/v1/me/password', () =>
        HttpResponse.json(
          {
            message: 'The password is incorrect.',
            errors: { current_password: ['The password is incorrect.'] },
          },
          { status: 422 },
        ),
      ),
    )
    renderRoute('/settings')

    await userEvent.type(await screen.findByLabelText('Current password'), 'nope-nope-1')
    await userEvent.type(screen.getByLabelText('New password'), 'brand-new-1')
    await userEvent.type(screen.getAllByLabelText('Confirm password')[0]!, 'brand-new-1')
    await userEvent.click(screen.getByRole('button', { name: 'Change password' }))

    expect(await screen.findByText('The password is incorrect.')).toBeInTheDocument()
  })
})
