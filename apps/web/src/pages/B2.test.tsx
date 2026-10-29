import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import type { GameStat, Room } from '@/features/play/api'
import i18n from '@/shared/i18n'
import { roomFixture, signedInAs, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const stat: GameStat = {
  game: { slug: 'chess', name: 'Chess' },
  played: 4,
  wins: 2,
  losses: 1,
  draws: 0,
  abandoned: 1,
  current_streak: 2,
  best_streak: 2,
  last_played_at: '2026-10-28T10:00:00+00:00',
}

const finished = (id: string): Room =>
  roomFixture({
    id,
    status: 'finished',
    end_reason: 'completed',
    finished_at: '2026-10-28T10:00:00+00:00',
    participants: roomFixture().participants.map((p) => ({
      ...p,
      result: p.seat === 0 ? 'win' : 'loss',
    })),
  })

function serveGames(rooms: Room[], total = rooms.length) {
  server.use(
    http.get('*/api/v1/me/games', () =>
      HttpResponse.json({ data: rooms, meta: { current_page: 1, last_page: 1, total } }),
    ),
  )
}

describe('Names follow the language switch', () => {
  it('refetches translated names without reloading', async () => {
    let gameRequests = 0
    server.use(
      signedInAs(userFixture({ username: 'alice' })),
      http.get('*/api/v1/me/games', ({ request }) => {
        gameRequests++
        const ru = request.headers.get('Accept-Language') === 'ru'
        const room = finished('room-1')
        return HttpResponse.json({
          data: [{ ...room, game: { ...room.game, name: ru ? 'Крестики-нолики' : 'Tic-tac-toe' } }],
          meta: { current_page: 1, last_page: 1, total: 1 },
        })
      }),
    )
    renderRoute('/history')

    expect(await screen.findByText(/^Tic-tac-toe ·/)).toBeInTheDocument()
    expect(gameRequests).toBe(1)
    await i18n.changeLanguage('ru')
    expect(await screen.findByText(/^Крестики-нолики ·/)).toBeInTheDocument()
    expect(gameRequests).toBe(2)
  })
})

describe('Profile', () => {
  it('shows the records and the five latest games', async () => {
    server.use(
      signedInAs(userFixture({ username: 'alice' })),
      http.get('*/api/v1/me/stats', () => HttpResponse.json({ data: [stat] })),
    )
    serveGames(
      Array.from({ length: 6 }, (_, i) => finished(`room-${i}`)),
      6,
    )
    renderRoute('/profile')

    const records = await screen.findByRole('region', { name: 'Records' })
    expect(await within(records).findByRole('heading', { name: 'Chess' })).toBeInTheDocument()
    expect(within(records).getByText('50%')).toBeInTheDocument()
    expect(within(records).getByText('Win streak 2 · Best 2')).toBeInTheDocument()

    const recent = screen.getByRole('region', { name: 'Recent games' })
    expect(await within(recent).findAllByRole('link', { name: /Tic-tac-toe/ })).toHaveLength(5)
    expect(within(recent).getByRole('link', { name: 'See all games' })).toHaveAttribute(
      'href',
      '/history',
    )
  })

  it('invites a new player to play instead of showing empty records', async () => {
    server.use(signedInAs(userFixture({ username: 'alice' })))
    serveGames([])
    renderRoute('/profile')

    expect(
      await screen.findByText('No games yet. Play one and your records will show here.'),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Records' })).not.toBeInTheDocument()
  })
})

describe('Invite link', () => {
  it('seats a signed-in player at the table', async () => {
    let body: unknown
    server.use(
      signedInAs(userFixture({ username: 'bob' })),
      http.post('*/api/v1/rooms/join', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: roomFixture({ id: 'room-9' }) })
      }),
      http.get('*/api/v1/rooms/room-9', () =>
        HttpResponse.json({ data: roomFixture({ id: 'room-9' }) }),
      ),
    )
    const { router } = renderRoute('/join/abc123')

    await waitFor(() => expect(router.state.location.pathname).toBe('/rooms/room-9'))
    expect(body).toEqual({ invite_code: 'ABC123' })
  })

  it('explains an invalid code and points to the games', async () => {
    server.use(
      signedInAs(userFixture({ username: 'bob' })),
      http.post('*/api/v1/rooms/join', () =>
        HttpResponse.json(
          { message: 'This invite code is not valid or the room is full.', errors: {} },
          { status: 422 },
        ),
      ),
    )
    renderRoute('/join/NOPE')

    expect(
      await screen.findByText('This invite code is not valid or the room is full.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Choose a game' })).toHaveAttribute('href', '/games')
  })

  it('sends guests through login and back to the table', async () => {
    const { router } = renderRoute('/join/ABC123')

    await waitFor(() => expect(router.state.location.pathname).toBe('/login'))
    expect(router.state.location.search).toBe(`?next=${encodeURIComponent('/join/ABC123')}`)
    expect(
      await screen.findByText("Log in or sign up to join your friend's table."),
    ).toBeInTheDocument()
    // The header has its own "Sign up"; the one in the form footer keeps the destination.
    expect(screen.getAllByRole('link', { name: 'Sign up' }).at(-1)).toHaveAttribute(
      'href',
      `/register?next=${encodeURIComponent('/join/ABC123')}`,
    )
  })

  it('copies the invite link from the waiting table', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { ...navigator, share: undefined, clipboard: { writeText } })
    server.use(
      signedInAs(userFixture({ id: 1, username: 'alice' })),
      http.get('*/api/v1/rooms/room-1', () =>
        HttpResponse.json({
          data: roomFixture({
            type: 'private',
            status: 'waiting',
            invite_code: 'ABC123',
            participants: [roomFixture().participants[0]!],
          }),
        }),
      ),
    )
    renderRoute('/rooms/room-1')

    await userEvent.click(await screen.findByRole('button', { name: 'Copy link' }))
    expect(writeText).toHaveBeenCalledWith(`${window.location.origin}/join/ABC123`)
    expect(await screen.findByRole('button', { name: 'Link copied' })).toBeInTheDocument()
    vi.unstubAllGlobals()
  })
})
