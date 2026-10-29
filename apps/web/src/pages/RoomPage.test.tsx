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
    expect(screen.getByText('Waiting for the other player')).toBeInTheDocument()
    expect(body).toMatchObject({ expected_version: 0, action: { type: 'place', cell: 0 } })
    expect(body.client_action_id).toMatch(/^[0-9a-f-]{36}$/)
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
    renderRoute('/rooms/room-1')

    expect(await screen.findByText('You won!')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Play again' })).toHaveAttribute(
      'href',
      '/games/tictactoe',
    )
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
