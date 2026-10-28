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

    const nardi = await catalog().findByRole('link', { name: /Nardi/ })
    const poker = catalog().getByRole('link', { name: /Poker/ })

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

    expect(await catalog().findByRole('link', { name: /Chess/ })).toBeInTheDocument()
  })

  it('refetches games in the selected language', async () => {
    server.use(
      http.get('*/api/v1/games', ({ request }) => {
        const name = request.headers.get('Accept-Language') === 'ru' ? 'Шахматы' : 'Chess'
        return HttpResponse.json({ data: [summaryOf(gameFixture({ name }))] })
      }),
    )

    renderRoute('/games')
    await catalog().findByRole('link', { name: /Chess/ })

    await userEvent.selectOptions(await screen.findByLabelText('Language'), 'ru')

    expect(await catalog().findByRole('link', { name: /Шахматы/ })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Игры')
  })

  it('lists every game in the menu', async () => {
    server.use(
      http.get('*/api/v1/games', () =>
        HttpResponse.json({
          data: [summaryOf(gameFixture({ slug: 'durak', name: 'Durak', is_playable: true }))],
        }),
      ),
    )

    renderRoute('/games')
    await userEvent.click(await screen.findByRole('button', { name: 'Menu' }))

    const menu = await screen.findByRole('navigation', { name: 'Sections' })
    expect(await within(menu).findByRole('link', { name: 'Durak' })).toHaveAttribute(
      'href',
      '/games/durak',
    )

    await userEvent.keyboard('{Escape}')
    expect(screen.queryByRole('navigation', { name: 'Sections' })).not.toBeInTheDocument()
  })

  it('turns the carousel with the arrows and the dots', async () => {
    server.use(
      http.get('*/api/v1/games', () =>
        HttpResponse.json({
          data: ['Nardi', 'Chess', 'Durak'].map((name) =>
            summaryOf(gameFixture({ slug: name.toLowerCase(), name, is_playable: true })),
          ),
        }),
      ),
    )

    renderRoute('/games')
    const dot = (name: string) => screen.getByRole('button', { name: `Show ${name}` })

    await catalog().findByRole('link', { name: /Nardi/ })
    expect(dot('Nardi')).toHaveAttribute('aria-current', 'true')

    await userEvent.click(screen.getByRole('button', { name: 'Next game' }))
    expect(dot('Chess')).toHaveAttribute('aria-current', 'true')

    await userEvent.click(screen.getByRole('button', { name: 'Previous game' }))
    await userEvent.click(screen.getByRole('button', { name: 'Previous game' }))
    expect(dot('Durak')).toHaveAttribute('aria-current', 'true')

    await userEvent.click(dot('Nardi'))
    expect(dot('Nardi')).toHaveAttribute('aria-current', 'true')
  })
})
