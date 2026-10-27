import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { gameFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('Game page', () => {
  it('shows the game and its rules as paragraphs', async () => {
    server.use(
      http.get('*/api/v1/games/nardi', () =>
        HttpResponse.json({
          data: gameFixture({ slug: 'nardi', name: 'Nardi', rules: 'Roll the dice.\n\nBear off.' }),
        }),
      ),
    )

    renderRoute('/games/nardi')

    expect(screen.getByTestId('game-loading')).toBeInTheDocument()
    expect(await screen.findByRole('heading', { level: 1, name: 'Nardi' })).toBeInTheDocument()
    expect(screen.getByText('Roll the dice.')).toBeInTheDocument()
    expect(screen.getByText('Bear off.')).toBeInTheDocument()
    expect(screen.getByText(/isn't available to play yet/)).toBeInTheDocument()
  })

  it('shows a not-found message for unknown games', async () => {
    server.use(
      http.get('*/api/v1/games/unknown', () =>
        HttpResponse.json({ message: 'Not found.' }, { status: 404 }),
      ),
    )

    renderRoute('/games/unknown')

    expect(await screen.findByText("We couldn't find that game.")).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('renders the 404 page for unknown routes', async () => {
    renderRoute('/no/such/page')

    expect(await screen.findByRole('heading', { name: 'Page not found' })).toBeInTheDocument()
  })
})
