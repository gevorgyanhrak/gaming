import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import type { RankingRow } from './api'
import { gameFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

/** The game's own page data. */
const page = (slug: string, name: string) =>
  http.get(`*/api/v1/games/${slug}`, () =>
    HttpResponse.json({ data: gameFixture({ slug, name, is_playable: false }) }),
  )

const row = (overrides: Partial<RankingRow>): RankingRow => ({
  rank: 1,
  is_ai: false,
  username: 'ani',
  name: 'Ani',
  avatar_url: null,
  level: 5,
  frame: null,
  value: 40,
  ...overrides,
})

describe('Rankings', () => {
  it('shows a game’s Top 10 by points on its page, top three with medals and AI marked', async () => {
    server.use(
      page('durak', 'Durak'),
      http.get('*/api/v1/rankings/games/durak', () =>
        HttpResponse.json({
          kind: 'points',
          data: [
            row({ rank: 1, name: 'Davit', username: null, is_ai: true, value: 195 }),
            row({ rank: 2, value: 180 }),
            row({ rank: 3, name: 'Vardan', username: 'vardan', value: 90 }),
            row({ rank: 4, name: 'Lilit', username: 'lilit', value: 10 }),
          ],
        }),
      ),
    )
    renderRoute('/games/durak')

    const list = await screen.findByRole('list', { name: /Top 10/ })
    const rows = within(list).getAllByRole('listitem')
    expect(rows).toHaveLength(4)
    expect(within(rows[0]!).getByText('Davit')).toBeInTheDocument()
    expect(within(rows[0]!).getByText('AI')).toBeInTheDocument()
    expect(within(rows[0]!).getByText('195 pts')).toBeInTheDocument()
    expect(within(rows[3]!).getByText('10 pts')).toBeInTheDocument()

    // The avatar opens the player's card.
    await userEvent.click(within(rows[1]!).getByRole('button', { name: 'Ani’s card' }))
    expect(await screen.findByRole('dialog')).toBeInTheDocument()
  })

  it('ranks Poker by Fishkas, and shows no board for a game without one', async () => {
    server.use(
      page('poker', 'Poker'),
      page('domino', 'Domino'),
      http.get('*/api/v1/rankings/games/poker', () =>
        HttpResponse.json({ kind: 'chips', data: [row({ value: 55000 })] }),
      ),
      http.get('*/api/v1/rankings/games/domino', () => HttpResponse.json({ kind: null, data: [] })),
    )
    const { unmount } = renderRoute('/games/poker')
    const list = await screen.findByRole('list', { name: /Top 10/ })
    expect(within(list).getByText('55,000')).toBeInTheDocument()
    expect(screen.getByText('The players with the most Fishkas.')).toBeInTheDocument()
    unmount()

    renderRoute('/games/domino')
    await screen.findByText('Rules')
    expect(screen.queryByRole('list', { name: /Top 10/ })).not.toBeInTheDocument()
  })

  it('switches the home leaderboard between highest level and most Fishkas without a reload', async () => {
    server.use(
      http.get('*/api/v1/rankings/level', () =>
        HttpResponse.json({
          data: [row({ name: 'Hayk', username: 'hayk', value: 40, level: 40 })],
        }),
      ),
      http.get('*/api/v1/rankings/chips', () =>
        HttpResponse.json({ data: [row({ name: 'Mane', username: 'mane', value: 90000 })] }),
      ),
    )
    renderRoute('/')

    const board = await screen.findByRole('region', { name: 'Top players' })
    expect(await within(board).findByText('Hayk')).toBeInTheDocument()

    await userEvent.selectOptions(within(board).getByRole('combobox', { name: 'Show' }), 'chips')
    expect(await within(board).findByText('Mane')).toBeInTheDocument()
    expect(within(board).getByText('90,000')).toBeInTheDocument()
    expect(within(board).getByRole('list', { name: 'Most Fishkas' })).toBeInTheDocument()
  })
})
