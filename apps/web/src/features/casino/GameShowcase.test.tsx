import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { gameFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('Casino home', () => {
  it('shows Blackjack, Poker and Blot as the main games, in that order, and the others as links below', async () => {
    server.use(
      http.get('*/api/v1/games', () =>
        HttpResponse.json({
          data: ['chess', 'blot', 'nardi', 'poker', 'blackjack'].map((slug, i) =>
            gameFixture({ slug, name: slug[0]!.toUpperCase() + slug.slice(1), sort_order: i }),
          ),
        }),
      ),
    )
    renderRoute('/')

    const main = await screen.findByRole('region', { name: 'Main games' })
    const cards = await within(main).findAllByRole('link', { name: /Play / })
    expect(cards.map((c) => c.getAttribute('href'))).toEqual([
      '/games/blackjack',
      '/games/poker',
      '/games/blot',
    ])
    const more = screen.getByRole('region', { name: 'More games' })
    expect(within(more).getByRole('link', { name: 'Chess' })).toHaveAttribute(
      'href',
      '/games/chess',
    )
    expect(within(more).getByRole('link', { name: 'Nardi' })).toBeInTheDocument()
    // Play money, said plainly.
    expect(screen.getByText(/Fishkas are free play money/)).toBeInTheDocument()
  })
})
