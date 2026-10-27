import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import { roomFixture, signedInAs } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('Games in progress bar', () => {
  it('links back to a running game and leaves it with the close button', async () => {
    let inGame = true
    server.use(
      signedInAs(),
      http.get('*/api/v1/me/rooms', () =>
        HttpResponse.json({
          data: inGame
            ? [roomFixture({ id: 'room-7', game: { slug: 'chess', name: 'Chess' } })]
            : [],
        }),
      ),
      http.post('*/api/v1/rooms/room-7/leave', () => {
        inGame = false
        return HttpResponse.json({ data: roomFixture({ id: 'room-7', status: 'finished' }) })
      }),
    )
    renderRoute('/games')

    expect(await screen.findByText(/You are in a game of Chess/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Return to the game' })).toHaveAttribute(
      'href',
      '/rooms/room-7',
    )
    expect(
      await screen.findByText('Come back within 15 seconds, or the game is lost.'),
    ).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Leave the Chess game' }))
    expect(screen.getByText('Leave? The game counts as lost.')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Leave' }))

    await vi.waitFor(() =>
      expect(screen.queryByText(/You are in a game of Chess/)).not.toBeInTheDocument(),
    )
  })

  it('stays hidden for guests and on the game’s own page', async () => {
    renderRoute('/games')
    expect(await screen.findByRole('link', { name: 'Log in' })).toBeInTheDocument()
    expect(screen.queryByText(/You are in a game/)).not.toBeInTheDocument()
  })
})
