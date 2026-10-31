import { act, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import {
  gameFixture,
  roomFixture,
  roomStateFixture,
  signedInAs,
  userFixture,
  walletFixture,
} from '@/test/handlers'
import { emit } from '@/test/realtime'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const checkers = gameFixture({
  slug: 'tictactoe',
  name: 'Tic-tac-toe',
  is_playable: true,
  modes: [
    { id: 7, slug: 'classic', name: 'Classic', description: null, min_players: 2, max_players: 2 },
  ],
})

function serveGame() {
  server.use(
    signedInAs(userFixture({ id: 1 })),
    http.get('*/api/v1/games/tictactoe', () => HttpResponse.json({ data: checkers })),
    http.get('*/api/v1/rooms/room-9', () =>
      HttpResponse.json({ data: roomFixture({ id: 'room-9' }) }),
    ),
    http.get('*/api/v1/rooms/room-9/state', () =>
      HttpResponse.json({ data: roomStateFixture({ room: 'room-9' }) }),
    ),
  )
}

describe('Fishkas', () => {
  it('shows the balance in the header and follows changes from the server', async () => {
    serveGame()
    let wallet = walletFixture()
    server.use(http.get('*/api/v1/me/wallet', () => HttpResponse.json({ data: wallet })))
    renderRoute('/games/tictactoe')

    const balance = await screen.findByRole('link', { name: /^Your Fishkas: 5,000/ })
    expect(balance).toHaveAttribute('href', '/wallet')
    expect(await screen.findByText('Entry: 500 Fishkas')).toBeInTheDocument()

    wallet = walletFixture({ balance: 4500, total_spent: 500 })
    act(() => emit('user.1', 'wallet.updated', { balance: 4500, change: -500 }))

    expect(await screen.findByRole('link', { name: /^Your Fishkas: 4,500/ })).toBeInTheDocument()
    expect(screen.getByText('−500 Fishkas')).toBeInTheDocument()
  })

  it('offers free Fishkas when a game can’t start, then lets you play', async () => {
    serveGame()
    let wallet = walletFixture({
      balance: 0,
      refill: { available: true, available_at: null, amount: 5000 },
    })
    let refills = 0
    server.use(
      http.get('*/api/v1/me/wallet', () => HttpResponse.json({ data: wallet })),
      http.post('*/api/v1/rooms', () =>
        wallet.balance < 500
          ? HttpResponse.json(
              {
                message: 'You need 500 Fishkas to play this game.',
                code: 'insufficient_fishkas',
                balance: 0,
                needed: 500,
              },
              { status: 422 },
            )
          : HttpResponse.json({ data: roomFixture({ id: 'room-9' }) }, { status: 201 }),
      ),
      http.post('*/api/v1/me/wallet/refill', () => {
        refills++
        wallet = walletFixture({
          balance: 5000,
          refill: { available: false, available_at: null, amount: 5000 },
        })
        return HttpResponse.json({ data: wallet }, { status: 201 })
      }),
    )
    const { router } = renderRoute('/games/tictactoe')

    await userEvent.click(await screen.findByRole('button', { name: 'Start game' }))

    const dialog = await screen.findByRole('dialog', { name: 'Your Fishkas Have Run Out!' })
    expect(dialog).toHaveTextContent('Your balance has reached zero.')
    const take = within(dialog).getByRole('button', { name: 'Get 5,000 Free Fishkas' })
    await userEvent.dblClick(take)

    expect(
      await within(dialog).findByRole('heading', { name: '+5,000 Fishkas!' }),
    ).toBeInTheDocument()
    expect(refills).toBe(1)
    await userEvent.click(within(dialog).getByRole('button', { name: 'Continue' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(await screen.findByRole('link', { name: /^Your Fishkas: 5,000/ })).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Start game' }))
    await screen.findByText('Your turn')
    expect(router.state.location.pathname).toBe('/rooms/room-9')
  })

  it('says when the next free bonus comes if it was taken recently', async () => {
    serveGame()
    server.use(
      http.get('*/api/v1/me/wallet', () =>
        HttpResponse.json({
          data: walletFixture({
            balance: 120,
            refill: { available: false, available_at: '2026-10-31T10:00:00+00:00', amount: 5000 },
          }),
        }),
      ),
      http.post('*/api/v1/rooms', () =>
        HttpResponse.json(
          { message: 'You need 500 Fishkas to play this game.', code: 'insufficient_fishkas' },
          { status: 422 },
        ),
      ),
    )
    renderRoute('/games/tictactoe')

    await userEvent.click(await screen.findByRole('button', { name: 'Start game' }))

    const dialog = await screen.findByRole('dialog')
    expect(dialog).toHaveTextContent('You have 120 left; a game costs 500.')
    expect(await within(dialog).findByText(/^The next free bonus is available/)).toBeInTheDocument()
    // Nothing to take now: no bonus button and no "get a new bonus" line, just OK.
    expect(within(dialog).queryByRole('button', { name: /Free Fishkas/ })).not.toBeInTheDocument()
    expect(dialog).not.toHaveTextContent('Get a new bonus')
    // The page behind can't be reached while the popup is open.
    expect(document.getElementById('root')).toHaveAttribute('inert')
    await userEvent.click(within(dialog).getByRole('button', { name: 'OK' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(document.getElementById('root')).not.toHaveAttribute('inert')
  })

  it('lists the wallet history', async () => {
    server.use(
      signedInAs(),
      http.get('*/api/v1/me/wallet/transactions', () =>
        HttpResponse.json({
          data: [
            {
              id: 2,
              type: 'game_entry_fee',
              amount: -500,
              balance_after: 4500,
              description: null,
              reference_id: 'room-1',
              created_at: '2026-10-30T10:00:00+00:00',
            },
            {
              id: 1,
              type: 'registration_bonus',
              amount: 5000,
              balance_after: 5000,
              description: null,
              reference_id: null,
              created_at: '2026-10-30T09:00:00+00:00',
            },
          ],
          links: { first: null, last: null, prev: null, next: null },
          meta: {
            current_page: 1,
            from: 1,
            last_page: 1,
            links: [],
            path: null,
            per_page: 20,
            to: 2,
            total: 2,
          },
        }),
      ),
    )
    renderRoute('/wallet')

    expect(await screen.findByRole('heading', { name: 'Your Fishkas' })).toBeInTheDocument()
    const items = within(await screen.findByRole('list')).getAllByRole('listitem')
    expect(items[0]).toHaveTextContent('Game entry')
    expect(items[0]).toHaveTextContent('−500')
    expect(items[1]).toHaveTextContent('Welcome bonus')
    expect(items[1]).toHaveTextContent('+5,000')
  })
})
