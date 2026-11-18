import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { gameFixture, roomFixture, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const card = { slug: 'card', name: 'Card games', sort_order: 20 }

function serveGames() {
  server.use(
    http.get('*/api/v1/games', () =>
      HttpResponse.json({
        data: ['blackjack', 'poker', 'blot', 'durak', 'chess'].map((slug) =>
          gameFixture({ slug, name: slug[0]!.toUpperCase() + slug.slice(1), category: card }),
        ),
      }),
    ),
  )
}

describe('Casino lobby', () => {
  it('finds games by name, the top games too, and says when nothing matches', async () => {
    serveGames()
    renderRoute('/')
    const all = await screen.findByRole('region', { name: 'All games' })
    await within(all).findByRole('link', { name: /Durak/ })

    await userEvent.type(within(all).getByRole('searchbox', { name: 'Search games' }), 'po')
    expect(within(all).getByRole('link', { name: /Poker/ })).toBeInTheDocument()
    expect(within(all).queryByRole('link', { name: /Durak/ })).not.toBeInTheDocument()

    await userEvent.type(within(all).getByRole('searchbox', { name: 'Search games' }), 'zzz')
    expect(within(all).getByRole('status')).toHaveTextContent('No game matches “pozzz”.')
  })

  it('lists the slots the server offers as games of the Casino category, counted with it', async () => {
    const casino = { slug: 'casino', name: 'Casino', sort_order: 5 }
    server.use(
      http.get('*/api/v1/games', () =>
        HttpResponse.json({
          data: [
            gameFixture({ slug: 'roulette', name: 'Roulette', category: casino }),
            gameFixture({ slug: 'durak', name: 'Durak', category: card }),
          ],
        }),
      ),
      http.get('*/api/v1/slots', () =>
        HttpResponse.json({
          data: [
            {
              slug: 'stellar-sevens',
              name: 'Stellar Sevens',
              provider: 'APEXSLOTS (open source)',
              image_url: '/play/stellar-sevens/tile.webp',
              min_bet: 10,
              max_bet: 1000,
              play_path: '/play/stellar-sevens/',
            },
          ],
        }),
      ),
    )
    renderRoute('/')

    const all = await screen.findByRole('region', { name: 'All games' })
    // The Casino banner counts its slot with its game and opens them in a popup.
    const banner = await within(all).findByRole('button', { name: /Casino/ })
    expect(banner).toHaveTextContent('2 games')
    await userEvent.click(banner)

    const popup = await screen.findByRole('dialog', { name: 'Casino' })
    expect(within(popup).getByRole('link', { name: /Stellar Sevens/ })).toHaveAttribute(
      'href',
      '/casino/slots/stellar-sevens',
    )
    expect(within(popup).getByRole('link', { name: /Roulette/ })).toBeInTheDocument()
    expect(within(popup).queryByRole('link', { name: /Durak/ })).not.toBeInTheDocument()

    await userEvent.click(within(popup).getByRole('button', { name: 'Close' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('opens every game in a popup from the All banner', async () => {
    serveGames()
    renderRoute('/')
    const all = await screen.findByRole('region', { name: 'All games' })
    await userEvent.click(await within(all).findByRole('button', { name: /All games/ }))

    const popup = await screen.findByRole('dialog', { name: 'All games' })
    expect(within(popup).getAllByRole('link')).toHaveLength(5)
  })

  it('shows the games the player played last, each once', async () => {
    serveGames()
    server.use(
      http.get('*/api/v1/me', () => HttpResponse.json({ data: userFixture() })),
      http.get('*/api/v1/me/games', () =>
        HttpResponse.json({
          data: [
            roomFixture({ game: { ...roomFixture().game, slug: 'durak', name: 'Durak' } }),
            roomFixture({ game: { ...roomFixture().game, slug: 'chess', name: 'Chess' } }),
            roomFixture({ game: { ...roomFixture().game, slug: 'durak', name: 'Durak' } }),
          ],
          meta: { current_page: 1, last_page: 1, total: 3 },
        }),
      ),
    )
    renderRoute('/')

    const recent = await screen.findByRole('region', { name: 'Recently played' })
    const links = await within(recent).findAllByRole('link')
    expect(links.map((l) => l.getAttribute('href'))).toEqual(['/games/durak', '/games/chess'])
  })
})
