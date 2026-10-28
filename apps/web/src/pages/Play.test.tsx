import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import {
  gameFixture,
  roomFixture,
  roomStateFixture,
  signedInAs,
  summaryOf,
  userFixture,
} from '@/test/handlers'
import { emit } from '@/test/realtime'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const playable = gameFixture({
  slug: 'tictactoe',
  name: 'Tic-tac-toe',
  is_playable: true,
  modes: [
    { id: 7, slug: 'classic', name: 'Classic', description: null, min_players: 2, max_players: 2 },
  ],
})

function serveGame() {
  server.use(
    http.get('*/api/v1/games/tictactoe', () => HttpResponse.json({ data: playable })),
    http.get('*/api/v1/rooms/room-9', () =>
      HttpResponse.json({ data: roomFixture({ id: 'room-9' }) }),
    ),
    http.get('*/api/v1/rooms/room-9/state', () =>
      HttpResponse.json({ data: roomStateFixture({ room: 'room-9' }) }),
    ),
  )
}

describe('Playing a game', () => {
  it('asks guests to log in', async () => {
    serveGame()
    renderRoute('/games/tictactoe')

    expect(await screen.findByRole('link', { name: 'Log in to play' })).toHaveAttribute(
      'href',
      '/login?next=%2Fgames%2Ftictactoe',
    )
  })

  it('starts a bot game at the chosen level', async () => {
    serveGame()
    let body: unknown
    server.use(
      signedInAs(),
      http.post('*/api/v1/rooms', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: roomFixture({ id: 'room-9' }) }, { status: 201 })
      }),
    )
    const { router } = renderRoute('/games/tictactoe')

    await userEvent.click(await screen.findByRole('radio', { name: 'Hard' }))
    await userEvent.click(screen.getByRole('button', { name: 'Play against a bot' }))

    await screen.findByText('Your turn')
    expect(router.state.location.pathname).toBe('/rooms/room-9')
    expect(body).toEqual({ game_mode_id: 7, type: 'bot', bot_difficulty: 'hard' })
  })

  it('lets you choose the table size for games with more than two players', async () => {
    const poker = gameFixture({
      slug: 'tictactoe',
      is_playable: true,
      modes: [
        {
          id: 8,
          slug: 'holdem',
          name: 'Holdem',
          description: null,
          min_players: 2,
          max_players: 6,
        },
      ],
    })
    const bodies: unknown[] = []
    server.use(
      signedInAs(),
      http.get('*/api/v1/games/tictactoe', () => HttpResponse.json({ data: poker })),
      http.post('*/api/v1/rooms', async ({ request }) => {
        bodies.push(await request.json())
        return HttpResponse.json({ error: 'stop here' }, { status: 500 })
      }),
    )
    renderRoute('/games/tictactoe')

    const size = await screen.findByRole('group', { name: 'Table size' })
    expect(within(size).getByRole('radio', { name: '4 players' })).toBeChecked()
    await userEvent.click(within(size).getByRole('radio', { name: '3 players' }))
    await userEvent.click(screen.getByRole('button', { name: 'Play against a bot' }))
    await userEvent.click(screen.getByRole('button', { name: 'Play with a friend' }))

    await vi.waitFor(() => expect(bodies).toHaveLength(2))
    expect(bodies[0]).toMatchObject({ type: 'bot', opponents: 2 })
    expect(bodies[1]).toEqual({ game_mode_id: 8, type: 'private', seats: 3 })
  })

  it('searches for an opponent and goes to the room when matched', async () => {
    serveGame()
    const queued = {
      ticket: {
        status: 'queued',
        game_mode_id: 7,
        joined_at: '2026-10-27T10:00:00+00:00',
        bot_offer_at: null,
      },
      room: null,
      waiting: 3,
    }
    server.use(
      signedInAs(userFixture({ id: 1 })),
      http.post('*/api/v1/matchmaking', () => HttpResponse.json({ data: queued }, { status: 201 })),
      http.get('*/api/v1/matchmaking', () => HttpResponse.json({ data: queued })),
    )
    const { router } = renderRoute('/games/tictactoe')

    await userEvent.click(await screen.findByRole('button', { name: 'Find an opponent' }))
    expect(await screen.findByText('Looking for an opponent…')).toBeInTheDocument()
    expect(screen.getByText('3 players are waiting; the table starts soon.')).toBeInTheDocument()

    emit('user.1', 'matchmaking.matched', { room: 'room-9' })

    await screen.findByText('Your turn')
    expect(router.state.location.pathname).toBe('/rooms/room-9')
  })

  it('offers a bot game when no opponent is found', async () => {
    serveGame()
    let accepted = false
    server.use(
      signedInAs(userFixture({ id: 1 })),
      http.get('*/api/v1/matchmaking', () =>
        HttpResponse.json({
          data: accepted
            ? { ticket: null, room: 'room-9' }
            : {
                ticket: {
                  status: 'bot_offered',
                  game_mode_id: 7,
                  joined_at: '2026-10-27T10:00:00+00:00',
                  bot_offer_at: null,
                },
                room: null,
              },
        }),
      ),
      http.post('*/api/v1/matchmaking/accept-bot', () => {
        accepted = true
        return HttpResponse.json({ data: { ticket: null, room: 'room-9' } }, { status: 201 })
      }),
    )
    const { router } = renderRoute('/games/tictactoe')

    expect(await screen.findByText(/No one is available right now/)).toBeInTheDocument()
    await userEvent.click(screen.getAllByRole('button', { name: 'Play against a bot' })[0]!)

    await screen.findByText('Your turn')
    expect(router.state.location.pathname).toBe('/rooms/room-9')
  })

  it('explains when verification is required', async () => {
    serveGame()
    server.use(
      signedInAs(userFixture({ email_verified: false })),
      http.post('*/api/v1/matchmaking', () =>
        HttpResponse.json(
          {
            message: 'Verify your email address to play.',
            reason: 'verification_required',
            channel: 'email',
          },
          { status: 403 },
        ),
      ),
    )
    renderRoute('/games/tictactoe')

    await userEvent.click(await screen.findByRole('button', { name: 'Find an opponent' }))

    expect(await screen.findByText(/Verify your email address to play/)).toBeInTheDocument()
  })

  it('shows games in progress and statistics on the dashboard', async () => {
    server.use(
      signedInAs(),
      http.get('*/api/v1/me/rooms', () => HttpResponse.json({ data: [roomFixture()] })),
      http.get('*/api/v1/me/stats', () =>
        HttpResponse.json({
          data: [
            {
              game: { slug: 'tictactoe', name: 'Tic-tac-toe' },
              played: 3,
              wins: 2,
              losses: 1,
              draws: 0,
              abandoned: 0,
              current_streak: 1,
              best_streak: 2,
              last_played_at: null,
            },
          ],
        }),
      ),
      http.get('*/api/v1/games', () => HttpResponse.json({ data: [summaryOf(playable)] })),
    )
    renderRoute('/dashboard')

    expect(await screen.findByRole('heading', { name: 'Games in progress' })).toBeInTheDocument()
    expect(screen.getByText('vs Aram')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Your statistics' })).toBeInTheDocument()
  })
})
