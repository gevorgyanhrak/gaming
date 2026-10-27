import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { configFixture, gameFixture, signedInAs, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

function withConfig(overrides: Partial<typeof configFixture>) {
  return http.get('*/api/v1/config', () =>
    HttpResponse.json({ data: { ...configFixture, ...overrides } }),
  )
}

describe('Admin-managed platform settings in the app', () => {
  it('shows the maintenance screen to players', async () => {
    server.use(withConfig({ maintenance: { enabled: true, message: 'Back at noon' } }))
    renderRoute('/games')

    expect(await screen.findByRole('heading', { name: "We'll be right back" })).toBeInTheDocument()
    expect(screen.getByText('Back at noon')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Games', level: 1 })).not.toBeInTheDocument()
  })

  it('lets staff use the app during maintenance', async () => {
    server.use(
      withConfig({ maintenance: { enabled: true, message: null } }),
      signedInAs(userFixture({ can_access_admin: true })),
    )
    renderRoute('/games')

    expect(await screen.findByRole('heading', { name: 'Games', level: 1 })).toBeInTheDocument()
  })

  it('uses the uploaded logo and links published pages in the footer', async () => {
    server.use(
      withConfig({
        logo_url: 'http://localhost/storage/branding/logo.png',
        pages: [{ slug: 'privacy', title: 'Privacy policy' }],
      }),
    )
    renderRoute('/')

    const footer = screen.getByRole('contentinfo')
    expect(await within(footer).findByRole('link', { name: 'Privacy policy' })).toHaveAttribute(
      'href',
      '/pages/privacy',
    )
    expect(screen.getByRole('banner').querySelector('img')).toHaveAttribute(
      'src',
      'http://localhost/storage/branding/logo.png',
    )
  })

  it('renders a content page', async () => {
    server.use(
      http.get('*/api/v1/pages/privacy', () =>
        HttpResponse.json({
          data: {
            slug: 'privacy',
            title: 'Privacy policy',
            body: 'We collect little.\n\nWe keep it safe.',
            updated_at: '2026-10-01T00:00:00+00:00',
          },
        }),
      ),
    )
    renderRoute('/pages/privacy')

    expect(await screen.findByRole('heading', { name: 'Privacy policy' })).toBeInTheDocument()
    expect(screen.getByText('We collect little.')).toBeInTheDocument()
    expect(screen.getByText('We keep it safe.')).toBeInTheDocument()
  })

  it('requires accepting published terms to register', async () => {
    let body: Record<string, unknown> = {}
    server.use(
      withConfig({ registration: { ...configFixture.registration, requires_terms: true } }),
      http.post('*/api/v1/auth/register', async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: userFixture() }, { status: 201 })
      }),
    )
    renderRoute('/register')

    await userEvent.type(await screen.findByLabelText('Email address'), 'new@example.com')
    await userEvent.type(screen.getByLabelText('Username'), 'new_player')
    await userEvent.type(screen.getByLabelText('Password'), 'secret-pass-1')
    await userEvent.type(screen.getByLabelText('Confirm password'), 'secret-pass-1')
    await userEvent.click(screen.getByRole('button', { name: 'Create account' }))

    expect(await screen.findByText('Please accept the terms of service.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'terms of service' })).toHaveAttribute(
      'href',
      '/pages/terms',
    )

    await userEvent.click(screen.getByRole('checkbox', { name: /I accept the/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Create account' }))

    await screen.findByRole('heading', { name: /Verify your/ })
    expect(body).toMatchObject({ accept_terms: true })
  })

  it('lists a game’s modes', async () => {
    server.use(
      http.get('*/api/v1/games/chess', () =>
        HttpResponse.json({
          data: gameFixture({
            modes: [
              {
                id: 1,
                slug: 'blitz',
                name: 'Blitz 5+3',
                description: '5 minutes each.',
                min_players: 2,
                max_players: 2,
              },
            ],
          }),
        }),
      ),
    )
    renderRoute('/games/chess')

    expect(await screen.findByRole('heading', { name: 'Modes' })).toBeInTheDocument()
    expect(screen.getByText('Blitz 5+3')).toBeInTheDocument()
    expect(screen.getByText('5 minutes each.')).toBeInTheDocument()
  })
})
