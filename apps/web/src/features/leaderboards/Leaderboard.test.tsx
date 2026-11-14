import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { gameFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const row = (
  rank: number,
  username: string,
  total: number,
  game = { slug: 'poker', name: 'Poker' },
) => ({
  rank,
  total,
  wins: 2,
  player: { username, name: username, avatar_url: null, level: 5 },
  game,
})

describe('Leaderboards', () => {
  it('ranks the period’s winners and switches period and game on the server', async () => {
    const asked: string[] = []
    server.use(
      http.get('*/api/v1/games', () =>
        HttpResponse.json({
          data: [
            gameFixture({ slug: 'blot', name: 'Blot' }),
            gameFixture({ slug: 'poker', name: 'Poker' }),
          ],
        }),
      ),
      http.get('*/api/v1/leaderboards', ({ request }) => {
        const url = new URL(request.url)
        asked.push(`${url.searchParams.get('period')}:${url.searchParams.get('game') ?? 'all'}`)
        return HttpResponse.json({
          data:
            url.searchParams.get('game') === 'blot'
              ? [row(1, 'gor', 2_000, { slug: 'blot', name: 'Blot' })]
              : [row(1, 'hrak', 7_000), row(2, 'ani', 5_000)],
        })
      }),
    )
    renderRoute('/leaderboards')

    const list = await screen.findByRole('list')
    expect(within(list).getAllByRole('listitem')).toHaveLength(2)
    expect(within(list).getByRole('link', { name: 'hrak' })).toHaveAttribute('href', '/u/hrak')
    expect(within(list).getByText('7,000')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('radio', { name: 'Today' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Blot' }))
    expect(await screen.findByRole('link', { name: 'gor' })).toBeInTheDocument()
    expect(asked).toContain('day:blot')
  })

  it('says when nobody has won yet', async () => {
    renderRoute('/leaderboards')
    expect(await screen.findByText('No winners in this period yet.')).toBeInTheDocument()
  })
})
