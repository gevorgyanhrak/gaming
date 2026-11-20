import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { gameFixture, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

function signedIn() {
  server.use(
    http.get('*/api/v1/me', () => HttpResponse.json({ data: userFixture() })),
    http.get('*/api/v1/games', () =>
      HttpResponse.json({
        data: ['chess', 'nardi', 'durak', 'poker'].map((slug) =>
          gameFixture({ slug, name: slug[0]!.toUpperCase() + slug.slice(1), is_playable: true }),
        ),
      }),
    ),
    http.get('*/api/v1/me/stats', () => HttpResponse.json({ data: [] })),
    http.get('*/api/v1/me/gifts', () =>
      HttpResponse.json({
        data: {
          gift: { available: true, amount: 300, next_at: null },
          wheel: { available: true, prizes: [50, 100, 200] },
        },
      }),
    ),
  )
}

describe('Dashboard welcome strip', () => {
  it('puts the daily bonus, the free gift and the lucky wheel side by side in the welcome strip', async () => {
    signedIn()
    renderRoute('/')

    const perks = await screen.findByRole('list', { name: "Today's free Fishkas" })
    expect(
      await within(perks).findByRole('button', { name: /Free gifts.*Collect 300/ }),
    ).toBeInTheDocument()
    expect(within(perks).getByRole('button', { name: /Lucky wheel.*Spin/ })).toBeInTheDocument()

    await userEvent.click(within(perks).getByRole('button', { name: /Lucky wheel/ }))
    expect(await screen.findByRole('dialog', { name: 'Lucky wheel' })).toBeInTheDocument()
  })

  it('offers Durak under Try next, as a game card', async () => {
    signedIn()
    renderRoute('/')

    const tryNext = await screen.findByRole('region', { name: 'Try next' })
    const cards = await within(tryNext).findAllByRole('link')
    expect(cards[0]).toHaveAttribute('href', '/games/durak')
    expect(cards).toHaveLength(3)
  })
})
