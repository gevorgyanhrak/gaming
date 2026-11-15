import { act, screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { winFixture } from '@/test/handlers'
import { emit } from '@/test/realtime'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('Big Wins live', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('lists the week’s wins from the server, and says so when there are none', async () => {
    renderRoute('/')
    expect(await screen.findByText(/No wins yet this week/)).toBeInTheDocument()
  })

  it('shows each win with the player, the game and the amount, newest first', async () => {
    server.use(
      http.get('*/api/v1/wins', () =>
        HttpResponse.json({
          data: [
            winFixture({
              id: 'p1',
              amount: 150_000,
              player: { username: 'hrak', name: 'Hrak', avatar_url: null, level: 3 },
            }),
            winFixture({
              id: 'p2',
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
          id: 'p9',
          amount: 200_000,
          player: { username: 'ani', name: 'Ani', avatar_url: null, level: 8 },
        }),
      ),
    )

    const feed = screen.getByRole('region', { name: 'BIG WINS LIVE' })
    expect((await within(feed).findAllByText('+200,000')).length).toBeGreaterThan(0)
    const party = screen.getByRole('status', { name: '' })
    expect(within(party).getByText('Congratulations!')).toBeInTheDocument()
    expect(within(party).getByText('Ani')).toBeInTheDocument()
  })

  it('doesn’t celebrate a win below the amount the server set', async () => {
    renderRoute('/')
    await screen.findByText(/No wins yet this week/)

    act(() => emit('wins', 'win.recorded', winFixture({ id: 'p3', amount: 900, celebrate: false })))

    expect((await screen.findAllByText('+900')).length).toBeGreaterThan(0)
    expect(screen.queryByText('Congratulations!')).not.toBeInTheDocument()
  })

  it('marks an AI player’s win and never celebrates it', async () => {
    renderRoute('/')
    await screen.findByText(/No wins yet this week/)

    act(() =>
      emit(
        'wins',
        'win.recorded',
        winFixture({
          id: 'a4',
          amount: 500_000,
          is_ai: true,
          celebrate: false,
          player: { username: null, name: 'Aram', avatar_url: null, level: 30 },
        }),
      ),
    )

    const feed = screen.getByRole('region', { name: 'BIG WINS LIVE' })
    const row = await within(feed).findByRole('listitem')
    expect(within(row).getByText('AI')).toBeInTheDocument()
    expect(
      within(row).getByText('Aram (AI player) won 500,000 Fishkas in Blackjack'),
    ).toBeInTheDocument()
    expect(screen.queryByText('Congratulations!')).not.toBeInTheDocument()
  })

  it('puts the next win in the spotlight about every 5 seconds, and can be paused', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    server.use(
      http.get('*/api/v1/wins', () =>
        HttpResponse.json({
          data: [
            winFixture({
              id: 'p1',
              player: { username: 'ani', name: 'Ani', avatar_url: null, level: 3 },
            }),
            winFixture({
              id: 'p2',
              player: { username: 'vardan', name: 'Vardan', avatar_url: null, level: 5 },
            }),
          ],
        }),
      ),
    )
    renderRoute('/')

    const spot = () => screen.getByText('Recent win').parentElement as HTMLElement
    await screen.findByText('Recent win')
    expect(within(spot()).getByText('Ani')).toBeInTheDocument()

    act(() => {
      vi.advanceTimersByTime(5000)
    })
    expect(within(spot()).getByText('Vardan')).toBeInTheDocument()

    act(() => screen.getByRole('button', { name: 'Pause the wins' }).click())
    act(() => {
      vi.advanceTimersByTime(10_000)
    })
    expect(within(spot()).getByText('Vardan')).toBeInTheDocument()
  })
})
