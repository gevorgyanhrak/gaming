import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import { roomFixture, roomStateFixture, signedInAs, userFixture } from '@/test/handlers'
import { emit } from '@/test/realtime'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

// A minimal board: shows the version and offers the legal actions as buttons.
vi.mock('@/games/registry', async () => {
  const { createElement, lazy } = await import('react')
  type Props = {
    state: { version: number; legal_actions: { type: string; cell?: number }[] }
    onAction: (a: unknown) => void
  }
  const TestBoard = ({ state, onAction }: Props) =>
    createElement('div', null, [
      createElement('p', { key: 'v' }, `version ${state.version}`),
      ...state.legal_actions.map((action, i) =>
        createElement(
          'button',
          { key: i, type: 'button', onClick: () => onAction(action) },
          `play ${action.cell}`,
        ),
      ),
    ])
  const board = lazy(() => Promise.resolve({ default: TestBoard }))
  return {
    boards: { tictactoe: board, cardtable: board },
    // "cardtable" stands for Durak/Poker: a board that seats every player itself.
    traitsOf: (slug: string) => ({ seatsOnBoard: slug === 'cardtable', material: 'wood' }),
  }
})

function serveRoom(room = roomFixture(), state = roomStateFixture()) {
  server.use(
    signedInAs(userFixture({ id: 1, username: 'alice' })),
    http.get('*/api/v1/rooms/room-1', () => HttpResponse.json({ data: room })),
    http.get('*/api/v1/rooms/room-1/state', () => HttpResponse.json({ data: state })),
  )
}

describe('Room', () => {
  it('shows players, whose turn it is, and plays a legal move', async () => {
    serveRoom()
    let body: Record<string, unknown> = {}
    server.use(
      http.post('*/api/v1/rooms/room-1/actions', async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({
          data: roomStateFixture({ version: 1, seats_to_act: [1], legal_actions: [] }),
        })
      }),
    )
    renderRoute('/rooms/room-1')

    expect(await screen.findByText('Your turn')).toBeInTheDocument()
    expect(screen.getByText('Bot')).toBeInTheDocument()
    await userEvent.click(await screen.findByRole('button', { name: 'play 0' }))

    expect(await screen.findByText('version 1')).toBeInTheDocument()
    expect(screen.getByText('Waiting for Aram')).toBeInTheDocument()
    expect(body).toMatchObject({ expected_version: 0, action: { type: 'place', cell: 0 } })
    expect(body.client_action_id).toMatch(/^[0-9a-f-]{36}$/)
  })

  it('faces two players off with you first and marks who is to move', async () => {
    serveRoom(roomFixture(), roomStateFixture({ seat: 1, seats_to_act: [0], legal_actions: [] }))
    renderRoute('/rooms/room-1')

    expect(await screen.findByText('To move')).toBeInTheDocument()
    const list = screen.getByRole('list', { name: 'Players' })
    const plates = list.querySelectorAll('li[data-seat-anchor]')
    // Seat 1 (Aram) is "you" in this state, so it comes first.
    expect(plates[0]).toHaveAttribute('data-seat-anchor', '1')
    expect(plates[1]).toHaveAttribute('aria-current', 'step')
    expect(plates[1]).toHaveTextContent('To move')
    expect(screen.getByText('vs')).toBeInTheDocument()
  })

  it('shows the table bar in place of the site header', async () => {
    serveRoom()
    renderRoute('/rooms/room-1')

    expect(await screen.findByRole('heading', { name: 'Tic-tac-toe' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Back' })).toHaveAttribute('href', '/dashboard')
    expect(screen.queryByRole('navigation', { name: 'Main' })).not.toBeInTheDocument()
  })

  it('reports that the player is on the game page', async () => {
    serveRoom()
    let reports = 0
    server.use(
      http.post('*/api/v1/rooms/room-1/presence', () => {
        reports++
        return new HttpResponse(null, { status: 204 })
      }),
    )
    renderRoute('/rooms/room-1')

    await screen.findByText('Your turn')
    await vi.waitFor(() => expect(reports).toBeGreaterThan(0))
  })

  it('shows chess clocks: the running one counts down to the deadline', async () => {
    serveRoom(
      roomFixture(),
      roomStateFixture({
        deadline: '2026-10-27T10:02:05+00:00',
        clocks: [125_000, 300_000],
      }),
    )
    renderRoute('/rooms/room-1')

    expect(await screen.findByLabelText('Time left: 5:00')).toBeInTheDocument()
    expect(
      await screen.findByLabelText('Time left: 2:05', {}, { timeout: 2000 }),
    ).toBeInTheDocument()
    expect(screen.queryByText(/seconds left/)).not.toBeInTheDocument()
  })

  it('applies the next update from the server and refetches after a gap', async () => {
    let stateRequests = 0
    server.use(
      signedInAs(userFixture({ id: 1 })),
      http.get('*/api/v1/rooms/room-1', () => HttpResponse.json({ data: roomFixture() })),
      http.get('*/api/v1/rooms/room-1/state', () => {
        stateRequests += 1
        return HttpResponse.json({
          data: roomStateFixture({ version: stateRequests === 1 ? 0 : 5 }),
        })
      }),
    )
    renderRoute('/rooms/room-1')
    await screen.findByText('version 0')

    emit('user.1', 'room.updated', roomStateFixture({ version: 1 }))
    expect(await screen.findByText('version 1')).toBeInTheDocument()
    expect(stateRequests).toBe(1)

    emit('user.1', 'room.updated', roomStateFixture({ version: 4 }))
    expect(await screen.findByText('version 5')).toBeInTheDocument()
    expect(stateRequests).toBe(2)
  })

  it('shows the invite code while waiting and starts when a friend joins', async () => {
    let started = false
    server.use(
      signedInAs(userFixture({ id: 1 })),
      http.get('*/api/v1/rooms/room-1', () =>
        HttpResponse.json({
          data: started
            ? roomFixture({ type: 'private' })
            : roomFixture({
                type: 'private',
                status: 'waiting',
                invite_code: 'ABC234',
                participants: [roomFixture().participants[0]!],
              }),
        }),
      ),
      http.get('*/api/v1/rooms/room-1/state', () =>
        HttpResponse.json({ data: roomStateFixture() }),
      ),
    )
    renderRoute('/rooms/room-1')

    expect(await screen.findByText('ABC234')).toBeInTheDocument()

    started = true
    emit('user.1', 'room.updated', roomStateFixture({ version: 0 }))

    expect(await screen.findByText('Your turn')).toBeInTheDocument()
  })

  it('folds the result away to show the final position, and opens it again', async () => {
    serveRoom(
      roomFixture({
        status: 'finished',
        end_reason: 'completed',
        participants: [
          { ...roomFixture().participants[0]!, result: 'win' },
          { ...roomFixture().participants[1]!, result: 'loss' },
        ],
      }),
      roomStateFixture({ status: 'finished', seats_to_act: [], legal_actions: [] }),
    )
    renderRoute('/rooms/room-1')

    // Phones and tablets: a sheet over the table.
    expect(await screen.findByRole('dialog', { name: 'You won!' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Show the table' }))

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Game over' })).toHaveTextContent('You won!')
    expect(screen.getByRole('button', { name: 'Play again' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Result' }))
    expect(await screen.findByRole('dialog', { name: 'You won!' })).toBeInTheDocument()
  })

  it('shows the result when the game is over', async () => {
    serveRoom(
      roomFixture({
        status: 'finished',
        end_reason: 'completed',
        participants: [
          { ...roomFixture().participants[0]!, result: 'win' },
          { ...roomFixture().participants[1]!, result: 'loss' },
        ],
      }),
      roomStateFixture({ status: 'finished', seats_to_act: [], legal_actions: [] }),
    )

    let body: Record<string, unknown> = {}
    server.use(
      http.post('*/api/v1/rooms', async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: roomFixture({ id: 'room-2' }) }, { status: 201 })
      }),
    )
    const { router } = renderRoute('/rooms/room-1')

    await screen.findByRole('heading', { name: 'You won!' })
    await waitFor(() => expect(screen.getByRole('heading', { name: 'You won!' })).toHaveFocus())
    expect(screen.queryByText(/^Waiting/)).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Back to lobby' })).toHaveAttribute(
      'href',
      '/games/tictactoe',
    )
    expect(screen.getByText('Same settings: Classic · Level: Easy')).toBeInTheDocument()

    // The same game again: same mode, table size and bot level.
    await userEvent.click(screen.getByRole('button', { name: 'Play again' }))
    await waitFor(() => expect(router.state.location.pathname).toBe('/rooms/room-2'))
    expect(body).toMatchObject({ game_mode_id: 1, type: 'bot', bot_difficulty: 'easy' })
  })

  it('says the player left when their game ended for leaving the page', async () => {
    serveRoom(
      roomFixture({
        status: 'finished',
        end_reason: 'abandon',
        participants: [
          { ...roomFixture().participants[0]!, result: 'abandoned' },
          { ...roomFixture().participants[1]!, result: 'win' },
        ],
      }),
      roomStateFixture({ status: 'finished', seats_to_act: [], legal_actions: [] }),
    )
    renderRoute('/rooms/room-1')

    expect(await screen.findByRole('heading', { name: 'You left the game.' })).toBeInTheDocument()
  })

  it('ends the game for a player who lost all their chips while the table plays on', async () => {
    let roomCalls = 0
    serveRoom()
    server.use(
      http.get('*/api/v1/rooms/room-1', () => {
        roomCalls++
        // The room shows the result once the player's game is over (the first read is from before).
        const result = roomCalls === 1 ? null : ('loss' as const)
        return HttpResponse.json({
          data: roomFixture({
            game: { ...roomFixture().game, slug: 'cardtable' },
            participants: [
              { ...roomFixture().participants[0]!, result },
              roomFixture().participants[1]!,
            ],
          }),
        })
      }),
      http.get('*/api/v1/rooms/room-1/state', () =>
        HttpResponse.json({
          data: roomStateFixture({
            playing: false,
            seats_to_act: [1],
            legal_actions: [],
            view: { stacks: [0, 2000] },
          }),
        }),
      ),
    )
    renderRoute('/rooms/room-1')

    expect(await screen.findByRole('heading', { name: "You're out of chips." })).toBeInTheDocument()
    expect(screen.getByText('The game goes on for the others.')).toBeInTheDocument()
    // Out of the game: no "Waiting for…" turn status for them (there is a seat to act).
    expect(screen.queryByText(/^Waiting for/)).not.toBeInTheDocument()
    expect(roomCalls).toBe(2)
  })

  it('calls Nardi’s mars by its name, without promising double Fishkas', async () => {
    serveRoom(
      roomFixture({
        status: 'finished',
        end_reason: 'completed',
        participants: [
          { ...roomFixture().participants[0]!, result: 'win', score: 2 },
          { ...roomFixture().participants[1]!, result: 'loss', score: null },
        ],
      }),
      roomStateFixture({ status: 'finished', seats_to_act: [], legal_actions: [] }),
    )
    renderRoute('/rooms/room-1')

    expect(await screen.findByText('Mars! The opponent bore off no checkers.')).toBeInTheDocument()
  })

  it('offers only the lobby after a private game', async () => {
    serveRoom(
      roomFixture({
        type: 'private',
        status: 'finished',
        participants: roomFixture().participants.map((p) => ({ ...p, result: 'draw' })),
      }),
      roomStateFixture({ status: 'finished', seats_to_act: [], legal_actions: [] }),
    )
    renderRoute('/rooms/room-1')

    expect(await screen.findByRole('heading', { name: "It's a draw." })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Play again' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Back to lobby' })).toBeInTheDocument()
  })

  it('asks for confirmation before resigning', async () => {
    serveRoom()
    let left = false
    server.use(
      http.post('*/api/v1/rooms/room-1/leave', () => {
        left = true
        return HttpResponse.json({ data: roomFixture({ status: 'finished' }) })
      }),
    )
    renderRoute('/rooms/room-1')

    await userEvent.click(await screen.findByRole('button', { name: 'Resign' }))
    expect(left).toBe(false)
    await userEvent.click(screen.getByRole('button', { name: 'Yes, resign' }))

    await waitFor(() => expect(left).toBe(true))
  })

  it('puts "your turn" in the tab title until the player moves', async () => {
    serveRoom()
    server.use(
      http.post('*/api/v1/rooms/room-1/actions', () =>
        HttpResponse.json({
          data: roomStateFixture({ version: 1, seats_to_act: [1], legal_actions: [] }),
        }),
      ),
    )
    renderRoute('/rooms/room-1')

    await waitFor(() => expect(document.title).toBe('● Your turn · Tic-tac-toe'))
    await userEvent.click(await screen.findByRole('button', { name: 'play 0' }))
    await waitFor(() => expect(document.title).toBe('Tic-tac-toe'))
  })

  it('shows the result in the tab title when the game is over', async () => {
    serveRoom(
      roomFixture({
        status: 'finished',
        end_reason: 'completed',
        participants: roomFixture().participants.map((p) => ({
          ...p,
          result: p.seat === 0 ? 'win' : 'loss',
        })),
      }),
      roomStateFixture({
        status: 'finished',
        seats_to_act: [],
        legal_actions: [],
        result: { end_reason: 'completed', seats: ['win', 'loss'] },
      }),
    )
    renderRoute('/rooms/room-1')

    await waitFor(() => expect(document.title).toBe('Won · Tic-tac-toe'))
  })

  it('leaves the players to the board at card tables while the game is on', async () => {
    const table = { game: { slug: 'cardtable', name: 'Cards' } }
    serveRoom(roomFixture(table))
    const { unmount } = renderRoute('/rooms/room-1')

    expect(await screen.findByText('version 0')).toBeInTheDocument()
    expect(screen.queryByRole('list', { name: 'Players' })).not.toBeInTheDocument()
    unmount()

    serveRoom(
      roomFixture({
        ...table,
        status: 'finished',
        participants: roomFixture().participants.map((p) => ({ ...p, result: 'loss' })),
      }),
      roomStateFixture({ status: 'finished', seats_to_act: [], legal_actions: [] }),
    )
    renderRoute('/rooms/room-1')
    expect(await screen.findByRole('list', { name: 'Players' })).toBeInTheDocument()
  })
})
