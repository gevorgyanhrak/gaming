import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { gameFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

function serve() {
  server.use(
    http.get('*/api/v1/games', () =>
      HttpResponse.json({
        data: [
          gameFixture({ slug: 'poker', name: 'Poker' }),
          gameFixture({ slug: 'durak', name: 'Durak' }),
          gameFixture({ slug: 'domino', name: 'Domino' }),
          gameFixture({ slug: 'chess', name: 'Chess' }),
          gameFixture({ slug: 'nardi', name: 'Backgammon' }),
        ],
      }),
    ),
    http.get('*/api/v1/slots', () =>
      HttpResponse.json({
        data: [
          {
            slug: 'stellar-sevens',
            name: 'Stellar Sevens',
            provider: 'APEXSLOTS (open source)',
            image_url: null,
            min_bet: 10,
            max_bet: 1000,
            play_path: '/play/stellar-sevens/',
          },
        ],
      }),
    ),
  )
}

describe('Header search', () => {
  it('suggests games and slots as you type and opens one with the keyboard', async () => {
    serve()
    const { router } = renderRoute('/')
    // The header's box (md and up; jsdom shows both, the box comes first).
    const box = (await screen.findAllByRole('combobox', { name: 'Search games and slots' }))[0]!
    await userEvent.type(box, 'd')
    const list = screen.getByRole('listbox', { name: 'Search games and slots' })
    expect(
      within(list)
        .getAllByRole('option')
        .map((o) => o.textContent),
    ).toEqual([
      expect.stringContaining('Durak'),
      expect.stringContaining('Domino'),
      'All results for “d”',
    ])
    await userEvent.type(box, 'o')
    expect(within(list).getAllByRole('option')[0]).toHaveTextContent('Domino')

    await userEvent.keyboard('{ArrowDown}')
    expect(box).toHaveAttribute('aria-activedescendant', within(list).getAllByRole('option')[0]!.id)
    await userEvent.keyboard('{Enter}')
    expect(router.state.location.pathname).toBe('/games/domino')
  })

  it('says when nothing matches, and Enter shows every match on the games page', async () => {
    serve()
    const { router } = renderRoute('/')
    const box = (await screen.findAllByRole('combobox', { name: 'Search games and slots' }))[0]!
    await userEvent.type(box, 'zzz')
    expect(screen.getByText('Nothing matches “zzz”.')).toBeInTheDocument()

    await userEvent.clear(box)
    await userEvent.type(box, 'sev{Enter}')
    expect(router.state.location.pathname).toBe('/games')
    expect(router.state.location.search).toBe('?q=sev')
    expect(await screen.findByRole('link', { name: /Stellar Sevens/ })).toBeInTheDocument()
  })

  it('finds games by their Armenian names typed in Latin letters and opens the right one', async () => {
    serve()
    const { router } = renderRoute('/')
    const box = (await screen.findAllByRole('combobox', { name: 'Search games and slots' }))[0]!
    await userEvent.type(box, 'shaxmat')
    const list = screen.getByRole('listbox', { name: 'Search games and slots' })
    const first = within(list).getAllByRole('option')[0]!
    expect(first).toHaveTextContent('Chess')
    // The name it was found by, with the typed part marked.
    expect(within(first).getByText('shaxmat', { selector: 'mark' })).toBeInTheDocument()
    await userEvent.click(first)
    expect(router.state.location.pathname).toBe('/games/chess')

    await userEvent.type(box, 'nard')
    expect(within(list).getAllByRole('option')[0]).toHaveTextContent('Backgammon')
  })

  it('the games page filters by the same names', async () => {
    serve()
    renderRoute('/games?q=shahmat')
    expect(await screen.findByRole('link', { name: /Chess/ })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Poker/ })).not.toBeInTheDocument()
  })
})
