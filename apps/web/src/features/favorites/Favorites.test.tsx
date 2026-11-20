import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { gameFixture, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const card = { slug: 'card', name: 'Card games', sort_order: 20 }

function serve(initial: { kind: 'game' | 'slot'; slug: string }[] = []) {
  let saved = [...initial]
  const calls: string[] = []
  server.use(
    http.get('*/api/v1/me', () => HttpResponse.json({ data: userFixture() })),
    http.get('*/api/v1/games', () =>
      HttpResponse.json({
        data: [
          gameFixture({
            slug: 'durak',
            name: 'Durak',
            category: card,
            min_players: 2,
            max_players: 6,
            playing_now: 2,
          }),
          gameFixture({
            slug: 'chess',
            name: 'Chess',
            min_players: 2,
            max_players: 2,
            playing_now: 9,
          }),
          gameFixture({
            slug: 'blot',
            name: 'Blot',
            category: card,
            min_players: 4,
            max_players: 4,
            playing_now: null,
          }),
        ],
      }),
    ),
    http.get('*/api/v1/me/favorites', () => HttpResponse.json({ data: saved })),
    http.put('*/api/v1/me/favorites/:kind/:slug', ({ params }) => {
      calls.push(`PUT ${String(params.kind)}/${String(params.slug)}`)
      saved = [{ kind: params.kind as 'game', slug: params.slug as string }, ...saved]
      return HttpResponse.json({ data: saved })
    }),
    http.delete('*/api/v1/me/favorites/:kind/:slug', ({ params }) => {
      calls.push(`DELETE ${String(params.kind)}/${String(params.slug)}`)
      saved = saved.filter((f) => f.slug !== params.slug)
      return HttpResponse.json({ data: saved })
    }),
  )
  return calls
}

describe('Favourites', () => {
  it('saves a game with the heart and lists it under the Favourites pill', async () => {
    const calls = serve()
    renderRoute('/dashboard')

    const heart = await screen.findByRole('button', { name: 'Add Durak to favourites' })
    expect(heart).toHaveAttribute('aria-pressed', 'false')
    await userEvent.click(heart)
    expect(
      await screen.findByRole('button', { name: 'Remove Durak from favourites' }),
    ).toHaveAttribute('aria-pressed', 'true')
    expect(calls).toEqual(['PUT game/durak'])

    await userEvent.click(await screen.findByRole('radio', { name: /Favourites/ }))
    const list = screen.getAllByRole('list').at(-1)!
    expect(
      within(list)
        .getAllByRole('link', { name: /Durak|Chess|Blot/ })
        .map((l) => l.textContent),
    ).toEqual([expect.stringContaining('Durak')])

    await userEvent.click(screen.getByRole('button', { name: 'Remove Durak from favourites' }))
    expect(calls).toEqual(['PUT game/durak', 'DELETE game/durak'])
    expect(
      await screen.findByText('No favourites yet. Tap ♥ on a game to keep it here.'),
    ).toBeInTheDocument()
  })

  it('shows the saved games first on the dashboard, and no shelf without any', async () => {
    serve([{ kind: 'game', slug: 'chess' }])
    renderRoute('/')
    const shelf = await screen.findByRole('region', { name: 'Your favourites' })
    expect(await within(shelf).findByRole('link', { name: /Chess/ })).toHaveAttribute(
      'href',
      '/games/chess',
    )
  })

  it('has no hearts for guests', async () => {
    renderRoute('/dashboard')
    await screen.findAllByRole('link', { name: /Nardi|Chess|Durak|Poker/ })
    expect(screen.queryByRole('button', { name: /favourites/ })).not.toBeInTheDocument()
  })
})

describe('Games page filters', () => {
  it('filters by players and sorts, shows what applies as removable chips, and clears them', async () => {
    serve()
    renderRoute('/dashboard')
    await screen.findByRole('button', { name: 'Add Durak to favourites' })
    const titles = () => screen.getAllByRole('heading', { level: 3 }).map((h) => h.textContent)

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Players' }), 'many')
    expect(titles()).toEqual(['Durak', 'Blot'])
    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Sort by' }), 'playing')
    expect(titles()).toEqual(['Durak', 'Blot'])
    expect(screen.getByText('2 games')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Remove filter: 3 or more' }))
    expect(titles()).toEqual(['Chess', 'Durak', 'Blot'])

    await userEvent.click(screen.getByRole('button', { name: 'Remove filter: Playing now' }))
    expect(titles()).toEqual(['Durak', 'Chess', 'Blot'])
    expect(screen.queryByRole('button', { name: /Remove filter/ })).not.toBeInTheDocument()
  })
})
