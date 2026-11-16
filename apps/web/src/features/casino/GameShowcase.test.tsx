import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { gameFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const card = { slug: 'card', name: 'Card games', sort_order: 20 }
const board = { slug: 'board', name: 'Board games', sort_order: 10 }

function serveGames() {
  const games = [
    ['chess', board],
    ['blot', card],
    ['nardi', board],
    ['poker', card],
    ['blackjack', card],
    ['durak', card],
  ] as const
  server.use(
    http.get('*/api/v1/games', () =>
      HttpResponse.json({
        data: games.map(([slug, category]) =>
          gameFixture({ slug, name: slug[0]!.toUpperCase() + slug.slice(1), category }),
        ),
      }),
    ),
  )
}

describe('Casino home', () => {
  it('shows Blackjack, Poker and Blot as the top games, in that order, with their place', async () => {
    serveGames()
    renderRoute('/')

    const top = await screen.findByRole('region', { name: 'Top games' })
    const cards = await within(top).findAllByRole('link', { name: /Play / })
    expect(cards.map((c) => c.getAttribute('href'))).toEqual([
      '/games/blackjack',
      '/games/poker',
      '/games/blot',
    ])
    expect(within(cards[0]!).getByText('TOP 1')).toBeInTheDocument()
    expect(within(cards[2]!).getByText('TOP 3')).toBeInTheDocument()
    // Play money, said plainly.
    expect(screen.getByText(/Fishkas are free play money/)).toBeInTheDocument()
  })

  it('lists every other game with its poster, and opens a category banner in a popup', async () => {
    serveGames()
    renderRoute('/')

    const all = await screen.findByRole('region', { name: 'All games' })
    const posters = await within(all).findAllByRole('link', { name: /Chess|Nardi|Durak/ })
    expect(posters.map((p) => p.getAttribute('href'))).toEqual([
      '/games/chess',
      '/games/nardi',
      '/games/durak',
    ])
    // The top games are above, not repeated under "All".
    expect(within(all).queryByRole('link', { name: /Poker/ })).not.toBeInTheDocument()

    // A banner opens its category in a popup, the top games too; the list below stays as it was.
    const filters = within(all).getByRole('group', { name: 'Show games by category' })
    await userEvent.click(within(filters).getByRole('button', { name: /Card games/ }))
    const popup = await screen.findByRole('dialog', { name: 'Card games' })
    expect(within(popup).getByRole('link', { name: /Poker/ })).toBeInTheDocument()
    expect(within(popup).getByRole('link', { name: /Durak/ })).toBeInTheDocument()
    expect(within(popup).queryByRole('link', { name: /Chess/ })).not.toBeInTheDocument()
  })
})
