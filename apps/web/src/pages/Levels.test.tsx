import { act, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import type { LevelStatus, LevelUp } from '@/features/levels/api'
import { LevelProgress } from '@/features/levels/components/LevelProgress'
import { Avatar } from '@/shared/ui/Avatar'
import {
  dailyBonusFixture,
  gameFixture,
  levelFixture,
  signedInAs,
  walletFixture,
} from '@/test/handlers'
import { emit } from '@/test/realtime'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const up = (level: number, fishkas: number, overrides: Partial<LevelUp> = {}): LevelUp => ({
  level,
  fishkas,
  items: [],
  is_milestone: false,
  ...overrides,
})

function serve(first: LevelStatus) {
  let status = first
  let wallet = walletFixture()
  const posts: number[] = []
  server.use(
    signedInAs(),
    http.get('*/api/v1/games/chess', () => HttpResponse.json({ data: gameFixture() })),
    http.get('*/api/v1/me/wallet', () => HttpResponse.json({ data: wallet })),
    http.get('*/api/v1/me/level', () => HttpResponse.json({ data: status })),
    http.post('*/api/v1/me/level-ups/claim', () => {
      posts.push(1)
      const collected = status.pending.reduce((sum, level) => sum + level.fishkas, 0)
      const claimed = status.pending.map((level) => level.level)
      status = { ...status, pending: [] }
      wallet = walletFixture({ balance: wallet.balance + collected })
      return HttpResponse.json(
        { data: { collected, claimed, status } },
        { status: collected > 0 ? 201 : 200 },
      )
    }),
  )
  return {
    posts,
    set: (next: LevelStatus) => {
      status = next
    },
  }
}

describe('Level-up popup', () => {
  it('shows levels reached while away and collects them once', async () => {
    const { posts } = serve(levelFixture({ level: 4, pending: [up(3, 400), up(4, 450)] }))
    renderRoute('/games/chess')

    const dialog = await screen.findByRole('dialog', { name: 'Level up!' })
    expect(within(dialog).getByText('From level 2 to 4!')).toBeInTheDocument()
    expect(within(dialog).getByText(/\+850/)).toBeInTheDocument()

    await userEvent.dblClick(within(dialog).getByRole('button', { name: 'Collect' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(posts).toHaveLength(1)
    expect(await screen.findByRole('link', { name: /^Your Fishkas: 5,850/ })).toBeInTheDocument()
  })

  it('goes before the daily bonus, which opens once it is done', async () => {
    serve(levelFixture({ level: 2, pending: [up(2, 350)] }))
    server.use(
      http.get('*/api/v1/me/daily-bonus', () =>
        HttpResponse.json({ data: dailyBonusFixture({ claimed: false, day: 1, amount: 500 }) }),
      ),
    )
    renderRoute('/games/chess')

    const dialog = await screen.findByRole('dialog', { name: 'Level up!' })
    expect(screen.queryByRole('dialog', { name: 'Daily bonus' })).not.toBeInTheDocument()

    await userEvent.click(within(dialog).getByRole('button', { name: 'Collect' }))

    expect(await screen.findByRole('dialog', { name: 'Daily bonus' })).toBeInTheDocument()
    expect(screen.queryByRole('dialog', { name: 'Level up!' })).not.toBeInTheDocument()
  })

  it('stays closed after "Later" when XP arrives without a new level', async () => {
    serve(levelFixture({ level: 1, pending: [up(1, 100)] }))
    renderRoute('/games/chess')

    const dialog = await screen.findByRole('dialog', { name: 'Level up!' })
    await userEvent.click(within(dialog).getByRole('button', { name: 'Later' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())

    act(() => emit('user.1', 'level.updated', { level: 1, pending: 1 }))

    await new Promise((resolve) => setTimeout(resolve, 50))
    expect(screen.queryByRole('dialog', { name: 'Level up!' })).not.toBeInTheDocument()
  })

  it('opens again when a new level is reached during the visit', async () => {
    const { set } = serve(levelFixture({ level: 1, pending: [] }))
    renderRoute('/games/chess')
    await screen.findByRole('link', { name: /^Your Fishkas/ })

    set(levelFixture({ level: 2, pending: [up(2, 350)] }))
    act(() => emit('user.1', 'level.updated', { level: 2, pending: 1 }))

    expect(await screen.findByRole('dialog', { name: 'Level up!' })).toBeInTheDocument()
  })

  it('marks a milestone', async () => {
    serve(levelFixture({ level: 5, pending: [up(5, 2500, { is_milestone: true })] }))
    renderRoute('/games/chess')

    const dialog = await screen.findByRole('dialog', { name: 'Level up!' })
    expect(within(dialog).getByText('Milestone')).toBeInTheDocument()
    expect(within(dialog).getByText('You reached level 5!')).toBeInTheDocument()
  })
})

describe('Level progress', () => {
  it('shows progress within the level and what is left', () => {
    render(<LevelProgress status={{ level: 12, xp: 3400, level_xp: 1000, next_level_xp: 4000 }} />)

    expect(screen.getAllByText('Level 12').length).toBeGreaterThan(0)
    expect(screen.getByText('2,400 / 3,000 XP')).toBeInTheDocument()
    expect(screen.getByText(/600 XP until level 13/)).toBeInTheDocument()
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '80')
  })

  it('is full at the top level', () => {
    render(<LevelProgress status={{ level: 100, xp: 9000, level_xp: 9000, next_level_xp: null }} />)

    expect(screen.getByText('Top level')).toBeInTheDocument()
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '100')
  })
})

describe('Avatar', () => {
  it('shows the level badge when it has one', () => {
    render(<Avatar url={null} name="Aram" level={42} />)
    expect(screen.getByText('42')).toBeInTheDocument()
  })

  it('keeps the frame without a level', () => {
    const { container } = render(<Avatar url={null} name="Aram" frame="gold" />)
    expect(container.firstElementChild?.className).toContain('from-gold-light')
  })
})
