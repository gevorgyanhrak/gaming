import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { delay, http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { gameFixture, summaryOf } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

// The sidebar links every game too; these tests are about the catalog itself.
const catalog = () => within(screen.getByRole('main'))

describe('Games catalog', () => {
  it('shows a loading state, then the games', async () => {
    server.use(
      http.get('*/api/v1/games', async () => {
        await delay(50)
        return HttpResponse.json({
          data: [
            summaryOf(gameFixture({ slug: 'nardi', name: 'Nardi', is_playable: true })),
            summaryOf(
              gameFixture({ slug: 'poker', name: 'Poker', min_players: 2, max_players: 6 }),
            ),
          ],
        })
      }),
    )

    renderRoute('/games')

    expect(screen.getByTestId('game-grid-loading')).toBeInTheDocument()

    const nardi = await catalog().findByRole('link', { name: /^(?!Quick).*Nardi/ })
    const poker = catalog().getByRole('link', { name: /^(?!Quick).*Poker/ })

    expect(nardi).toHaveAttribute('href', '/games/nardi')
    expect(within(nardi).getByText('2 players')).toBeInTheDocument()
    expect(within(nardi).queryByText('Coming soon')).not.toBeInTheDocument()
    expect(within(poker).getByText('2–6 players')).toBeInTheDocument()
    expect(within(poker).getByText('Coming soon')).toBeInTheDocument()
  })

  it('shows an empty state when no games are available', async () => {
    server.use(http.get('*/api/v1/games', () => HttpResponse.json({ data: [] })))

    renderRoute('/games')

    expect(await screen.findByText('No games are available right now.')).toBeInTheDocument()
  })

  it('shows an error and recovers on retry', async () => {
    let calls = 0
    server.use(
      http.get('*/api/v1/games', () => {
        calls += 1
        return calls === 1
          ? HttpResponse.json({ message: 'Server Error' }, { status: 500 })
          : HttpResponse.json({ data: [summaryOf(gameFixture())] })
      }),
    )

    renderRoute('/games')

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Something went wrong')

    await userEvent.click(within(alert).getByRole('button', { name: 'Try again' }))

    expect(await catalog().findByRole('link', { name: /^(?!Quick).*Chess/ })).toBeInTheDocument()
  })

  it('refetches games in the selected language', async () => {
    server.use(
      http.get('*/api/v1/games', ({ request }) => {
        const name = request.headers.get('Accept-Language') === 'ru' ? 'Шахматы' : 'Chess'
        return HttpResponse.json({ data: [summaryOf(gameFixture({ name }))] })
      }),
    )

    renderRoute('/games')
    await catalog().findByRole('link', { name: /^(?!Quick).*Chess/ })

    await userEvent.selectOptions(await screen.findByLabelText('Language'), 'ru')

    expect(await catalog().findByRole('link', { name: /^(?!Quick).*Шахматы/ })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Игры')
  })

  it('opens the ☰ menu with the sections and the language — no list of games — and closes it with Escape', async () => {
    server.use(
      http.get('*/api/v1/games', () =>
        HttpResponse.json({
          data: [summaryOf(gameFixture({ slug: 'durak', name: 'Durak', is_playable: true }))],
        }),
      ),
    )
    renderRoute('/games')

    await userEvent.click(await screen.findByRole('button', { name: 'Menu' }))
    const menu = screen.getByRole('dialog', { name: 'Menu' })
    expect(within(menu).getByRole('link', { name: 'Leaderboards' })).toHaveAttribute(
      'href',
      '/leaderboards',
    )
    expect(within(menu).queryByRole('link', { name: 'Durak' })).not.toBeInTheDocument()
    expect(within(menu).getByRole('combobox', { name: 'Language' })).toBeInTheDocument()

    await userEvent.keyboard('{Escape}')
    expect(screen.queryByRole('dialog', { name: 'Menu' })).not.toBeInTheDocument()
  })

  const board = { slug: 'board', name: 'Board games', sort_order: 10 }
  const card = { slug: 'card', name: 'Card games', sort_order: 20 }

  function serveCategories() {
    server.use(
      http.get('*/api/v1/games', () =>
        HttpResponse.json({
          data: [
            summaryOf(gameFixture({ slug: 'poker', name: 'Poker', category: card })),
            summaryOf(gameFixture({ slug: 'chess', name: 'Chess', category: board })),
            summaryOf(gameFixture({ slug: 'nardi', name: 'Nardi', category: board })),
            summaryOf(gameFixture({ slug: 'lotto', name: 'Lotto', category: null })),
          ],
        }),
      ),
    )
  }

  it('offers a filter per category, in the admin order, others last', async () => {
    serveCategories()
    renderRoute('/games')

    await catalog().findByRole('link', { name: /^(?!Quick).*Chess/ })
    expect(
      catalog()
        .getAllByRole('radio')
        .map((r) => r.closest('label')?.textContent),
    ).toEqual(['All games4', 'Board games2', 'Card games1', 'More games1'])
  })

  it('filters by category, and the link keeps the choice', async () => {
    serveCategories()
    const { router } = renderRoute('/games')

    await catalog().findByRole('link', { name: /^(?!Quick).*Chess/ })
    await userEvent.click(catalog().getByRole('radio', { name: /Card games/ }))

    expect(catalog().getByRole('link', { name: /^(?!Quick).*Poker/ })).toBeInTheDocument()
    expect(catalog().queryByRole('link', { name: /^(?!Quick).*Chess/ })).not.toBeInTheDocument()
    expect(router.state.location.search).toBe('?category=card')

    await userEvent.click(catalog().getByRole('radio', { name: /All games/ }))
    expect(catalog().getByRole('link', { name: /^(?!Quick).*Chess/ })).toBeInTheDocument()
    expect(router.state.location.search).toBe('')
  })

  it('has no filter with a single category', async () => {
    server.use(
      http.get('*/api/v1/games', () =>
        HttpResponse.json({ data: [summaryOf(gameFixture({ category: board }))] }),
      ),
    )
    renderRoute('/games')

    await catalog().findByRole('link', { name: /^(?!Quick).*Chess/ })
    expect(catalog().queryByRole('radio')).not.toBeInTheDocument()
  })
})
