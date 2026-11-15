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
