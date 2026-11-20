import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { chipPackagesFixture, signedInAs, walletFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const OUT_TITLE = /Fishkas Have Run Out/

describe('Chip packages', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    sessionStorage.clear()
  })

  it('shows the five packages on the wallet page, the best value marked, and says plainly that buying is off', async () => {
    server.use(signedInAs())
    renderRoute('/wallet')

    const section = (await screen.findByRole('heading', { name: 'Get more Fishkas' })).closest(
      'section',
    )!
    expect(within(section).getAllByRole('listitem')).toHaveLength(5)
    expect(within(section).getByText('Best value')).toBeInTheDocument()
    expect(within(section).getByRole('note')).toHaveTextContent(
      'Buying Fishkas isn’t available yet'.replace('’', "'"),
    )
    const buy = within(section).getByRole('button', { name: 'Buy 60,000 Fishkas for $10.00' })
    expect(buy).toBeDisabled()
  })

  it('sends the player to the provider’s page when buying works — Fishkas come only from the provider', async () => {
    const assign = vi.fn()
    vi.stubGlobal('location', { ...window.location, assign })
    let body: unknown
    server.use(
      signedInAs(),
      http.get('*/api/v1/chip-packages', () =>
        HttpResponse.json({ data: chipPackagesFixture(), purchasable: true }),
      ),
      http.post('*/api/v1/me/payments/orders', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json(
          {
            data: {
              id: '01J',
              status: 'pending',
              price_cents: 500,
              currency: 'USD',
              chips: 27500,
              paid_at: null,
              created_at: '2026-11-20T10:00:00Z',
            },
            redirect_url: 'https://pay.example/checkout/01J',
          },
          { status: 201 },
        )
      }),
    )
    renderRoute('/wallet')

    await userEvent.click(
      await screen.findByRole('button', { name: 'Buy 27,500 Fishkas for $5.00' }),
    )

    await waitFor(() => expect(assign).toHaveBeenCalledWith('https://pay.example/checkout/01J'))
    expect(body).toMatchObject({ package: 'small' })
  })

  it('offers the top-up and the packages when the Fishkas reach zero — once a session, never at a table', async () => {
    server.use(
      signedInAs(),
      http.get('*/api/v1/me/wallet', () =>
        HttpResponse.json({ data: walletFixture({ balance: 0, at_table: 0 }) }),
      ),
    )
    const { unmount } = renderRoute('/ranks')

    const popup = await screen.findByRole('dialog', { name: OUT_TITLE })
    expect(
      await within(popup).findByRole('heading', { name: 'Get more Fishkas' }),
    ).toBeInTheDocument()
    unmount()

    // Not again in the same session.
    renderRoute('/ranks')
    await screen.findByRole('banner')
    await new Promise((resolve) => setTimeout(resolve, 100))
    expect(screen.queryByRole('dialog', { name: OUT_TITLE })).not.toBeInTheDocument()
  })
})
