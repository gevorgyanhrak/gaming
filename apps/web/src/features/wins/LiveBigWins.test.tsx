import { act, screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { winFixture } from '@/test/handlers'
import { emit } from '@/test/realtime'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('Big Wins live', () => {
  it('lists the week’s biggest wins from the server, and says so when there are none', async () => {
    renderRoute('/')
    expect(await screen.findByText(/No wins yet this week/)).toBeInTheDocument()
  })

  it('shows each win with the player, the game and the amount, biggest first', async () => {
    server.use(
      http.get('*/api/v1/wins', () =>
        HttpResponse.json({
          data: [
            winFixture({
              id: 1,
              amount: 150_000,
              player: { username: 'hrak', name: 'Hrak', avatar_url: null, level: 3 },
            }),
            winFixture({
              id: 2,
              amount: 4_000,
              celebrate: false,
              game: { slug: 'blot', name: 'Blot' },
            }),
          ],
        }),
      ),
    )
    renderRoute('/')

    const feed = await screen.findByRole('region', { name: 'BIG WINS LIVE' })
    const rows = await within(feed).findAllByRole('listitem')
    expect(rows).toHaveLength(2)
    expect(within(rows[0]!).getByText('+150,000')).toBeInTheDocument()
    expect(within(rows[0]!).getByText('Hrak won 150,000 Fishkas in Blackjack')).toBeInTheDocument()
    expect(within(rows[1]!).getByText('Blot')).toBeInTheDocument()
  })

  it('adds a new win live, and celebrates a big one', async () => {
    renderRoute('/')
    await screen.findByText(/No wins yet this week/)

    act(() =>
      emit(
        'wins',
        'win.recorded',
        winFixture({
          id: 9,
          amount: 200_000,
          player: { username: 'ani', name: 'Ani', avatar_url: null, level: 8 },
        }),
      ),
    )

    const feed = screen.getByRole('region', { name: 'BIG WINS LIVE' })
    expect(await within(feed).findByText('+200,000')).toBeInTheDocument()
    const party = screen.getByRole('status', { name: '' })
    expect(within(party).getByText('Congratulations!')).toBeInTheDocument()
    expect(within(party).getByText('Ani')).toBeInTheDocument()
  })

  it('doesn’t celebrate a win below the amount the server set', async () => {
    renderRoute('/')
    await screen.findByText(/No wins yet this week/)

    act(() => emit('wins', 'win.recorded', winFixture({ id: 3, amount: 900, celebrate: false })))

    expect(await screen.findByText('+900')).toBeInTheDocument()
    expect(screen.queryByText('Congratulations!')).not.toBeInTheDocument()
  })
})
