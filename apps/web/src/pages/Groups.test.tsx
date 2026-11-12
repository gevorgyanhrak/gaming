import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import type { PlayGroup } from '@/features/play/groups'
import { gameFixture, roomFixture, signedInAs, walletFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const stakeGroups: PlayGroup[] = [
  { slug: 'beginner', kind: 'stake', position: 1, min_balance: 500, min_bet: 500, max_bet: 2000 },
  { slug: 'casual', kind: 'stake', position: 2, min_balance: 5000, min_bet: 2000, max_bet: 10000 },
  {
    slug: 'experienced',
    kind: 'stake',
    position: 3,
    min_balance: 25000,
    min_bet: 10000,
    max_bet: 50000,
  },
  {
    slug: 'professional',
    kind: 'stake',
    position: 4,
    min_balance: 100000,
    min_bet: 50000,
    max_bet: 200000,
  },
  {
    slug: 'master',
    kind: 'stake',
    position: 5,
    min_balance: 500000,
    min_bet: 100000,
    max_bet: 500000,
  },
]
const pokerGroups: PlayGroup[] = [
  { slug: 'beginner', kind: 'chips', position: 1, min_balance: 100, min_bet: 1, max_bet: null },
  {
    slug: 'intermediate',
    kind: 'chips',
    position: 2,
    min_balance: 1000,
    min_bet: 5,
    max_bet: null,
  },
]

function serve(slug: string, groups: PlayGroup[], balance: number) {
  const game = gameFixture({
    slug,
    name: slug,
    is_playable: true,
    modes: [
      {
        id: 7,
        slug: 'classic',
        name: 'Classic',
        description: null,
        min_players: 2,
        max_players: 2,
        target: null,
      },
    ],
    groups,
  })
  server.use(
    signedInAs(),
    http.get(`*/api/v1/games/${slug}`, () => HttpResponse.json({ data: game })),
    http.get('*/api/v1/me/wallet', () => HttpResponse.json({ data: walletFixture({ balance }) })),
  )
}

describe('Play groups in the lobby', () => {
  it('shows every group, locks the ones the balance can’t enter, and sends the group and bet', async () => {
    serve('checkers', stakeGroups, 30000)
    let body: unknown
    server.use(
      http.post('*/api/v1/rooms', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: roomFixture({ id: 'room-9' }) }, { status: 201 })
      }),
    )
    renderRoute('/games/checkers')

    const picker = await screen.findByRole('group', { name: 'Group' })
    const tiles = within(picker).getAllByRole('radio')
    expect(tiles).toHaveLength(5)
    expect(within(picker).getByRole('radio', { name: /Beginner/ })).toBeChecked()
    expect(within(picker).getByRole('radio', { name: /Professional/ })).toBeDisabled()
    expect(picker).toHaveTextContent('Needs 100,000')
    expect(picker).toHaveTextContent('Bet 10,000–50,000')

    await userEvent.click(within(picker).getByRole('radio', { name: /Experienced/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Max' }))
    expect(
      screen.getByText('The winner takes the pot: 60,000 Fishkas.', { exact: false }),
    ).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Start game' }))

    expect(body).toMatchObject({ type: 'bot', group: 'experienced', bet: 30000 })
  })

  it('offers no bet to choose in Blot: it plays at the group’s minimum bet', async () => {
    serve('blot', stakeGroups, 30000)
    let body: unknown
    server.use(
      http.post('*/api/v1/rooms', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: roomFixture({ id: 'room-9' }) }, { status: 201 })
      }),
    )
    renderRoute('/games/blot')

    const picker = await screen.findByRole('group', { name: 'Group' })
    await userEvent.click(within(picker).getByRole('radio', { name: /Experienced/ }))
    expect(screen.queryByText('Your bet')).not.toBeInTheDocument()
    expect(screen.queryByRole('slider')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Max' })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Start game' }))

    expect(body).toMatchObject({ type: 'bot', group: 'experienced', bet: 10000 })
  })

  it('tells poker players they bring their whole balance, with the group’s blinds', async () => {
    serve('poker', pokerGroups, 5000)
    renderRoute('/games/poker')

    await userEvent.click(await screen.findByRole('radio', { name: /Intermediate/ }))

    expect(
      screen.getByText('You bring your whole balance to the table: 5,000 Fishkas. Blinds 2/5.'),
    ).toBeInTheDocument()
    expect(screen.queryByText('Your bet')).not.toBeInTheDocument()
  })
})
