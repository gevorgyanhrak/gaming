import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import type { GameStat, Room } from '@/features/play/api'
import { roomFixture, signedInAs, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const stat: GameStat = {
  game: { slug: 'chess', name: 'Chess' },
  played: 6,
  wins: 2,
  losses: 1,
  draws: 1,
  abandoned: 2,
  current_streak: 1,
  best_streak: 3,
  last_played_at: '2026-10-28T10:00:00+00:00',
}

describe('Dashboard statistics', () => {
  it('adds up: timeouts, win rate and streaks are shown', async () => {
    server.use(
      signedInAs(userFixture({ username: 'alice' })),
      http.get('*/api/v1/me/stats', () => HttpResponse.json({ data: [stat] })),
    )
    renderRoute('/dashboard')

    const table = await screen.findByRole('table')
    const row = within(table).getByRole('row', { name: /Chess/ })
    const cells = within(row)
      .getAllByRole('cell')
      .map((cell) => cell.textContent)
    // Played, wins, losses, draws, timed out, win rate, streak, best streak.
    expect(cells).toEqual(['6', '2', '1', '1', '2', '33%', '1', '3'])

    // The narrow layout carries the same numbers.
    expect(screen.getByText('Win streak 1 · Best 3')).toBeInTheDocument()
    expect(screen.getAllByText('33%')).toHaveLength(2)
  })
})

describe('History', () => {
  function serveHistory(rooms: Room[]) {
    server.use(
      signedInAs(userFixture({ username: 'alice' })),
      http.get('*/api/v1/me/games', () =>
        HttpResponse.json({ data: rooms, meta: { current_page: 1, last_page: 1 } }),
      ),
    )
  }
  const finished = (result: 'win' | 'loss' | 'draw' | 'abandoned', end_reason: string) =>
    roomFixture({
      id: `room-${result}`,
      status: 'finished',
      end_reason,
      finished_at: '2026-10-28T10:00:00+00:00',
      participants: roomFixture().participants.map((p) =>
        p.seat === 0 ? { ...p, result } : { ...p, result: null },
      ),
    })

  it('shows the outcome and how the game ended', async () => {
    serveHistory([
      finished('win', 'completed'),
      finished('loss', 'resign'),
      finished('draw', 'completed'),
      finished('abandoned', 'timeout'),
    ])
    renderRoute('/history')

    const rows = await screen.findAllByRole('link', { name: /Tic-tac-toe/ })
    expect(rows.map((row) => row.textContent)).toEqual([
      expect.stringMatching(/WonPlayed to the end$/),
      expect.stringMatching(/LostBy resignation$/),
      expect.stringMatching(/DrawPlayed to the end$/),
      expect.stringMatching(/Timed outOn time$/),
    ])
    expect(within(rows[0]!).getByText('Won').className).toContain('bg-success/20')
    expect(within(rows[1]!).getByText('Lost').className).toContain('bg-danger/20')
  })
})
