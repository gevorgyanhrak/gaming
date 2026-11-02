import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import {
  dailyBonusFixture,
  gameFixture,
  roomFixture,
  roomStateFixture,
  signedInAs,
  walletFixture,
} from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

function serve(bonus = dailyBonusFixture({ claimed: false, day: 3, amount: 2000 })) {
  let state = bonus
  let wallet = walletFixture()
  const posts: number[] = []
  server.use(
    signedInAs(),
    http.get('*/api/v1/games/chess', () => HttpResponse.json({ data: gameFixture() })),
    http.get('*/api/v1/me/wallet', () => HttpResponse.json({ data: wallet })),
    http.get('*/api/v1/me/daily-bonus', () => HttpResponse.json({ data: state })),
    http.post('*/api/v1/me/daily-bonus', () => {
      posts.push(1)
      const first = !state.claimed
      if (first) {
        state = { ...state, claimed: true }
        wallet = walletFixture({ balance: wallet.balance + state.amount })
      }
      return HttpResponse.json(
        { data: { ...state, collected: first ? state.amount : 0 } },
        { status: first ? 201 : 200 },
      )
    }),
  )
  return posts
}

describe('Daily bonus', () => {
  it('offers today’s bonus on arrival and adds it once when collected', async () => {
    const posts = serve()
    renderRoute('/games/chess')

    const dialog = await screen.findByRole('dialog', { name: 'Daily bonus' })
    const days = within(dialog).getAllByRole('listitem')
    expect(days).toHaveLength(5)
    expect(days[2]).toHaveAttribute('aria-current', 'date')
    expect(within(days[0]!).getByLabelText('Collected')).toBeInTheDocument()
    expect(
      within(dialog).getByText(
        'Play every day and grab more Fishkas! 2 days to go until your double bonus!',
      ),
    ).toBeInTheDocument()
    // Day 3's tile and the chest's amount.
    expect(within(dialog).getAllByText('2,000')).toHaveLength(2)

    await userEvent.dblClick(within(dialog).getByRole('button', { name: 'Collect' }))

    expect(await within(dialog).findByText('+2,000')).toBeInTheDocument()
    expect(posts).toHaveLength(1)
    expect(await screen.findByRole('link', { name: /^Your Fishkas: 7,000/ })).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('stays closed for the visit after "Later", and the wallet page opens it again', async () => {
    serve()
    const { router } = renderRoute('/games/chess')

    await userEvent.click(await screen.findByRole('button', { name: 'Later' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

    await router.navigate('/wallet')
    await userEvent.click(await screen.findByRole('button', { name: 'Collect' }))
    expect(await screen.findByRole('dialog', { name: 'Daily bonus' })).toBeInTheDocument()
  })

  it('says when the double bonus day has come, and when today’s was taken', async () => {
    serve(dailyBonusFixture({ claimed: false, day: 5, amount: 5000 }))
    renderRoute('/games/chess')
    expect(
      await screen.findByText('Double bonus today! Come back tomorrow to keep it.'),
    ).toBeInTheDocument()
  })

  it('never opens over a game in progress', async () => {
    serve()
    server.use(
      http.get('*/api/v1/rooms/room-1', () => HttpResponse.json({ data: roomFixture() })),
      http.get('*/api/v1/rooms/room-1/state', () =>
        HttpResponse.json({ data: roomStateFixture() }),
      ),
    )
    renderRoute('/rooms/room-1')

    await screen.findByRole('heading', { name: 'Tic-tac-toe' })
    expect(screen.queryByRole('dialog', { name: 'Daily bonus' })).not.toBeInTheDocument()
  })

  it('does not offer it once today’s was collected', async () => {
    serve(dailyBonusFixture({ claimed: true }))
    renderRoute('/games/chess')
    await screen.findByRole('heading', { name: 'Chess' })
    expect(screen.queryByRole('dialog', { name: 'Daily bonus' })).not.toBeInTheDocument()
  })
})
