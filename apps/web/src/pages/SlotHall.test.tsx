import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import type { Slot } from '@/features/casino/slots'
import type { EngineSlot, EngineSpin } from '@/features/casino/engine/api'
import { signedInAs } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const slot = (
  slug: string,
  name: string,
  studio: string | null,
  kind: Slot['kind'] = 'engine',
): Slot => ({
  slug,
  name,
  provider: kind === 'engine' ? 'Slotopol engine' : 'APEXSLOTS',
  studio,
  kind,
  image_url: null,
  min_bet: 1,
  max_bet: 100000,
  play_path: kind === 'house' ? `/play/${slug}/` : null,
})

const hall = [
  slot('novomatic-bookofradeluxe', 'Book of Ra Deluxe', 'Novomatic'),
  slot('novomatic-sizzlinghot', 'Sizzling Hot', 'Novomatic'),
  slot('netent-gonzosquest', "Gonzo's Quest", 'NetEnt'),
  slot('stellar-sevens', 'Stellar Sevens', null, 'house'),
]

const opened = (over: Partial<EngineSlot> = {}): EngineSlot => ({
  slug: 'novomatic-bookofradeluxe',
  name: 'Book of Ra Deluxe',
  studio: 'Novomatic',
  reels: 5,
  rows: 3,
  symbols: 10,
  features: ['line_select', 'free_games_retrigger'],
  balance: 5000,
  bets: [1, 2, 5],
  lines: { min: 1, max: 10, fixed: false, default: 10 },
  unit: 10,
  state: {
    grid: [
      [1, 2, 3],
      [4, 5, 6],
      [7, 8, 9],
      [1, 2, 3],
      [4, 5, 6],
    ],
  },
  free: false,
  ...over,
})

const spun = (over: Partial<EngineSpin> = {}): EngineSpin => ({
  round_id: '01ROUND',
  bet: 20,
  win: 40,
  balance: 5020,
  replay: false,
  screen: {
    grid: [
      [3, 3, 3],
      [3, 1, 2],
      [3, 4, 5],
      [6, 7, 8],
      [9, 1, 2],
    ],
  },
  wins: [
    {
      pay: 20,
      mp: 2,
      sym: 3,
      num: 3,
      li: 1,
      xy: [
        [1, 1],
        [2, 1],
        [3, 1],
      ],
    },
  ],
  free: false,
  next: false,
  free_left: 0,
  series_win: 0,
  round: 'k',
  ...over,
})

function serve() {
  const sent: unknown[] = []
  server.use(
    signedInAs(),
    http.get('*/api/v1/slots', () => HttpResponse.json({ data: hall })),
    http.get('*/api/v1/me/slots/recent', () => HttpResponse.json({ data: [hall[2]] })),
    http.get('*/api/v1/slots/:slug/engine', () => HttpResponse.json({ data: opened() })),
  )
  return sent
}

describe('Slot hall', () => {
  it('lists every slot with its makers as filters, recently played, and search by name or maker', async () => {
    serve()
    renderRoute('/casino')

    const makers = await screen.findByRole('navigation', { name: 'Makers' })
    expect(
      within(makers)
        .getAllByRole('button')
        .map((b) => b.textContent),
    ).toEqual(['All 4', 'Novomatic 2', 'NetEnt 1', 'House slots 1'])
    expect(await screen.findByRole('heading', { name: 'Played recently' })).toBeInTheDocument()
    expect(screen.getByText('4 slots')).toBeInTheDocument()

    await userEvent.click(within(makers).getByRole('button', { name: /NetEnt/ }))
    expect(screen.getByText('1 slot')).toBeInTheDocument()

    await userEvent.click(within(makers).getByRole('button', { name: /All/ }))
    await userEvent.type(
      screen.getByRole('searchbox', { name: 'Search slots or makers' }),
      'novomatic',
    )
    expect(screen.getByText('2 slots')).toBeInTheDocument()
  })
})

describe('Engine slot screen', () => {
  it('spins with the chosen bet and lines, shows the reels, the win and the winning line', async () => {
    serve()
    let body: Record<string, unknown> | null = null
    server.use(
      http.post('*/api/v1/slots/:slug/engine/spin', async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: spun() })
      }),
    )
    renderRoute('/casino/slots/novomatic-bookofradeluxe')

    expect(await screen.findByRole('heading', { name: 'Book of Ra Deluxe' })).toBeInTheDocument()
    expect(screen.getAllByRole('img', { name: /^Symbol / })).toHaveLength(15)
    await userEvent.click(screen.getByRole('button', { name: 'More: Bet per line' }))
    expect(screen.getByText('Spin costs').nextElementSibling).toHaveTextContent('20')

    await userEvent.click(screen.getByRole('button', { name: 'Spin' }))
    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({ bet: 2, lines: 10 })
    expect(String(body!.key)).toMatch(/^[\w-]{8,}$/)
    expect(await screen.findByText('+40 Fishkas')).toBeInTheDocument()
    expect(
      within(screen.getByRole('list', { name: 'Wins' })).getByText('Line 1'),
    ).toBeInTheDocument()
  })

  it('in free games the spin is free and the bet cannot change', async () => {
    serve()
    server.use(
      http.get('*/api/v1/slots/:slug/engine', () =>
        HttpResponse.json({
          data: opened({
            free: true,
            lines: { min: 20, max: 20, fixed: true, default: 20 },
            unit: 20,
          }),
        }),
      ),
      http.post('*/api/v1/slots/:slug/engine/spin', async ({ request }) => {
        const body = (await request.json()) as Record<string, unknown>
        // A game with fixed lines sends no lines at all.
        expect(body).not.toHaveProperty('lines')
        return HttpResponse.json({
          data: spun({ free: true, bet: 0, next: true, free_left: 4, series_win: 120 }),
        })
      }),
    )
    renderRoute('/casino/slots/novomatic-bookofradeluxe')

    expect(await screen.findByRole('button', { name: 'Free spin' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'More: Bet' })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: 'Free spin' }))
    expect(await screen.findByText(/Free games · 4 left · won 120/)).toBeInTheDocument()
  })

  it('says why a spin was refused, and charges nothing it was not told about', async () => {
    serve()
    server.use(
      http.post('*/api/v1/slots/:slug/engine/spin', () =>
        HttpResponse.json(
          { message: 'This game is not available right now.', code: 'slot_game_off' },
          { status: 422 },
        ),
      ),
    )
    renderRoute('/casino/slots/novomatic-bookofradeluxe')
    await userEvent.click(await screen.findByRole('button', { name: 'Spin' }))
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'This game is not available right now.',
    )
  })
})
