import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import { roomFixture, roomStateFixture, signedInAs, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

// A board that only draws the seats' avatars, marked like every real board's.
vi.mock('@/games/registry', async () => {
  const { createElement, lazy } = await import('react')
  const { seatTrigger } = await import('@/features/players/seatTrigger')
  const Board = ({ players }: { players: Record<number, string> }) =>
    createElement(
      'div',
      null,
      Object.entries(players).map(([seat, name]) =>
        createElement(
          'span',
          { key: seat, ...seatTrigger(Number(seat), `Show ${name}’s card`) },
          name,
        ),
      ),
    )
  return {
    boards: { tictactoe: lazy(() => Promise.resolve({ default: Board })) },
    traitsOf: () => ({ seatsOnBoard: true, material: 'wood' }),
  }
})

function serve() {
  server.use(
    signedInAs(userFixture({ id: 9, username: 'carol' })),
    http.get('*/api/v1/rooms/room-1', () =>
      HttpResponse.json({
        data: roomFixture({
          participants: [
            { ...roomFixture().participants[0]!, seat: 0, name: 'Alice', username: 'alice' },
            {
              ...roomFixture().participants[1]!,
              seat: 1,
              name: 'Aram',
              username: null,
              is_bot: true,
            },
          ],
        }),
      }),
    ),
    http.get('*/api/v1/rooms/room-1/state', () =>
      HttpResponse.json({ data: roomStateFixture({ seat: null }) }),
    ),
    http.get('*/api/v1/users/alice/card', () =>
      HttpResponse.json({
        data: {
          username: 'alice',
          name: 'Alice',
          avatar_url: null,
          level: 22,
          level_xp: 1000,
          xp: 1500,
          next_level_xp: 2000,
          balance: 48_000,
          played: 40,
          wins: 25,
          losses: 15,
          win_rate: 63,
          biggest_win: { amount: 120_000, game: { slug: 'poker', name: 'Poker' } },
          favourite_game: { slug: 'blot', name: 'Blot' },
          streak: 3,
          online: true,
          playing: { slug: 'chess', name: 'Chess' },
          achievements: [],
        },
      }),
    ),
  )
}

describe('Player card', () => {
  it('opens a person’s card from their avatar, with their real record, and closes with Escape', async () => {
    serve()
    renderRoute('/rooms/room-1')

    await userEvent.click(await screen.findByRole('button', { name: 'Show Alice’s card' }))
    const card = await screen.findByRole('dialog', { name: /Alice/ })
    expect(await within(card).findByText('63%')).toBeInTheDocument()
    expect(within(card).getByText('48,000')).toBeInTheDocument()
    expect(within(card).getByText('+120,000')).toBeInTheDocument()
    expect(within(card).getByText('Playing Chess now')).toBeInTheDocument()
    expect(within(card).getByRole('link', { name: 'Full profile' })).toHaveAttribute(
      'href',
      '/u/alice',
    )

    await userEvent.keyboard('{Escape}')
    expect(screen.queryByRole('dialog', { name: /Alice/ })).not.toBeInTheDocument()
  })

  it('gives an AI player a short card marked AI, with nothing invented', async () => {
    serve()
    renderRoute('/rooms/room-1')

    await userEvent.click(await screen.findByRole('button', { name: /Show Aram/ }))
    const card = await screen.findByRole('dialog', { name: /Aram/ })
    expect(within(card).getByText('AI')).toBeInTheDocument()
    expect(within(card).getByText(/An AI player/)).toBeInTheDocument()
    expect(within(card).queryByText('Win rate')).not.toBeInTheDocument()
  })
})
